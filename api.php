<?php

declare(strict_types=1);

require_once __DIR__ . '/php/lib/bootstrap.php';
require_once __DIR__ . '/php/lib/auth.php';
require_once __DIR__ . '/php/lib/call_service.php';

$db = db();
$route = $_GET['route'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($route === 'auth/login' && $method === 'POST') {
    $body = parse_json_body();
    $username = trim((string) ($body['username'] ?? ''));
    $password = (string) ($body['password'] ?? '');

    if ($username === '' || $password === '') {
        json_response(['error' => 'Username and password are required'], 400);
    }

    $result = login($db, $username, $password);
    if (!$result) {
        json_response(['error' => 'Invalid credentials'], 401);
    }

    json_response($result);
}

$user = require_auth($db);
process_queue($db, 3);

if ($route === 'auth/logout' && $method === 'POST') {
    logout($db, $user['token']);
    json_response(['ok' => true]);
}

if ($route === 'dashboard/metrics' && $method === 'GET') {
    $totalLeads = (int) $db->query('SELECT COUNT(*) AS c FROM leads')->fetch()['c'];
    $callsToday = (int) $db->query("SELECT COUNT(*) AS c FROM call_logs WHERE date(created_at) = date('now')")->fetch()['c'];
    $connectedCalls = (int) $db->query("SELECT COUNT(*) AS c FROM call_logs WHERE status = 'Completed'")->fetch()['c'];
    $hotLeads = (int) $db->query("SELECT COUNT(*) AS c FROM leads WHERE status = 'Hot'")->fetch()['c'];
    $conversionRate = $totalLeads > 0 ? round(($hotLeads / $totalLeads) * 100, 2) : 0;

    json_response(compact('totalLeads', 'callsToday', 'connectedCalls', 'hotLeads', 'conversionRate'));
}

if ($route === 'leads' && $method === 'GET') {
    $filters = [];
    $params = [];

    if (!empty($_GET['status'])) {
        $filters[] = 'LOWER(status) = LOWER(?)';
        $params[] = trim((string) $_GET['status']);
    }
    if (!empty($_GET['city'])) {
        $filters[] = 'LOWER(city) LIKE LOWER(?)';
        $params[] = '%' . trim((string) $_GET['city']) . '%';
    }
    if (!empty($_GET['q'])) {
        $filters[] = '(LOWER(name) LIKE LOWER(?) OR LOWER(phone) LIKE LOWER(?) OR LOWER(city) LIKE LOWER(?))';
        $q = '%' . trim((string) $_GET['q']) . '%';
        $params[] = $q;
        $params[] = $q;
        $params[] = $q;
    }

    $sql = 'SELECT * FROM leads';
    if (count($filters) > 0) {
        $sql .= ' WHERE ' . implode(' AND ', $filters);
    }
    $sql .= ' ORDER BY id DESC';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    json_response($stmt->fetchAll());
}

if ($route === 'leads' && $method === 'POST') {
    $body = parse_json_body();
    $name = trim((string) ($body['name'] ?? ''));
    $phone = trim((string) ($body['phone'] ?? ''));
    $city = trim((string) ($body['city'] ?? ''));

    if ($name === '' || $phone === '' || $city === '') {
        json_response(['error' => 'name, phone and city are required'], 400);
    }

    $now = gmdate('c');
    $stmt = $db->prepare('INSERT INTO leads (name, phone, city, status, created_at, updated_at) VALUES (?, ?, ?, "New", ?, ?)');
    $stmt->execute([$name, $phone, $city, $now, $now]);
    $id = (int) $db->lastInsertId();

    $lead = $db->query('SELECT * FROM leads WHERE id = ' . $id)->fetch();
    json_response($lead, 201);
}

if ($route === 'leads/upload-csv' && $method === 'POST') {
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        json_response(['error' => 'CSV file is required'], 400);
    }

    $content = file_get_contents($_FILES['file']['tmp_name']);
    $rows = preg_split('/\r\n|\r|\n/', (string) $content);
    $rows = array_values(array_filter($rows, fn ($x) => trim((string) $x) !== ''));
    if (count($rows) < 2) {
        json_response(['error' => 'No valid rows found. Expected columns: name,phone,city'], 400);
    }

    $headers = array_map(fn ($h) => strtolower(trim($h)), str_getcsv($rows[0]));
    $nameIdx = array_search('name', $headers, true);
    $phoneIdx = array_search('phone', $headers, true);
    $cityIdx = array_search('city', $headers, true);

    if ($nameIdx === false || $phoneIdx === false || $cityIdx === false) {
        json_response(['error' => 'Expected columns: name,phone,city'], 400);
    }

    $created = 0;
    $now = gmdate('c');
    $stmt = $db->prepare('INSERT INTO leads (name, phone, city, status, created_at, updated_at) VALUES (?, ?, ?, "New", ?, ?)');

    for ($i = 1; $i < count($rows); $i++) {
        $cols = str_getcsv($rows[$i]);
        $name = trim((string) ($cols[$nameIdx] ?? ''));
        $phone = trim((string) ($cols[$phoneIdx] ?? ''));
        $city = trim((string) ($cols[$cityIdx] ?? ''));
        if ($name === '' || $phone === '' || $city === '') {
            continue;
        }
        $stmt->execute([$name, $phone, $city, $now, $now]);
        $created++;
    }

    json_response(['created' => $created], 201);
}

if ($route === 'calls/start' && $method === 'POST') {
    $body = parse_json_body();
    $leadId = (int) ($body['leadId'] ?? 0);
    if ($leadId <= 0) {
        json_response(['error' => 'leadId is required'], 400);
    }

    $result = queue_call($db, $leadId);
    if (!$result['queued']) {
        json_response($result, 409);
    }

    process_queue($db, 1);
    json_response(['message' => 'Call queued', 'leadId' => $leadId], 202);
}

if ($route === 'calls/bulk-start' && $method === 'POST') {
    $body = parse_json_body();
    $leadIds = $body['leadIds'] ?? [];
    if (!is_array($leadIds) || count($leadIds) === 0) {
        json_response(['error' => 'leadIds array is required'], 400);
    }

    $results = [];
    foreach ($leadIds as $leadId) {
        $id = (int) $leadId;
        $results[] = ['leadId' => $id] + queue_call($db, $id);
    }

    process_queue($db, 2);
    json_response(['results' => $results], 202);
}

if ($route === 'calls/queue' && $method === 'GET') {
    $queued = $db->query("SELECT lead_id FROM call_queue WHERE status = 'pending' ORDER BY id ASC")->fetchAll();
    $active = $db->query("SELECT lead_id FROM call_queue WHERE status = 'processing' ORDER BY id ASC")->fetchAll();

    json_response([
        'queued' => array_map(fn ($r) => (int) $r['lead_id'], $queued),
        'active' => array_map(fn ($r) => (int) $r['lead_id'], $active)
    ]);
}

if ($route === 'calls/logs' && $method === 'GET') {
    $sql = 'SELECT c.*, l.name as lead_name, l.phone as lead_phone, l.city as lead_city, l.status as lead_status
        FROM call_logs c
        JOIN leads l ON l.id = c.lead_id
        ORDER BY c.id DESC';

    $rows = $db->query($sql)->fetchAll();
    $payload = array_map(function ($row) {
        return [
            'id' => (int) $row['id'],
            'leadId' => (int) $row['lead_id'],
            'status' => $row['status'],
            'attempt' => (int) $row['attempt'],
            'duration' => (int) $row['duration'],
            'transcript' => $row['transcript'],
            'summary' => $row['summary'],
            'createdAt' => $row['created_at'],
            'lead' => [
                'id' => (int) $row['lead_id'],
                'name' => $row['lead_name'],
                'phone' => $row['lead_phone'],
                'city' => $row['lead_city'],
                'status' => $row['lead_status']
            ]
        ];
    }, $rows);

    json_response($payload);
}

if ($route === 'config' && $method === 'GET') {
    $c = get_config($db);
    json_response([
        'languageStyle' => $c['language_style'],
        'tone' => $c['tone'],
        'openingScript' => $c['opening_script'],
        'questionFlow' => $c['question_flow'],
        'closingStatement' => $c['closing_statement']
    ]);
}

if ($route === 'config' && $method === 'PUT') {
    $body = parse_json_body();
    $existing = get_config($db);

    $next = [
        'language_style' => $body['languageStyle'] ?? $existing['language_style'],
        'tone' => $body['tone'] ?? $existing['tone'],
        'opening_script' => $body['openingScript'] ?? $existing['opening_script'],
        'question_flow' => $body['questionFlow'] ?? $existing['question_flow'],
        'closing_statement' => $body['closingStatement'] ?? $existing['closing_statement']
    ];

    if (!is_array($next['question_flow'])) {
        $next['question_flow'] = $existing['question_flow'];
    }

    $stmt = $db->prepare('UPDATE config SET language_style = ?, tone = ?, opening_script = ?, question_flow = ?, closing_statement = ?, updated_at = ? WHERE id = 1');
    $stmt->execute([
        (string) $next['language_style'],
        (string) $next['tone'],
        (string) $next['opening_script'],
        json_encode(array_values($next['question_flow']), JSON_THROW_ON_ERROR),
        (string) $next['closing_statement'],
        gmdate('c')
    ]);

    $c = get_config($db);
    json_response([
        'languageStyle' => $c['language_style'],
        'tone' => $c['tone'],
        'openingScript' => $c['opening_script'],
        'questionFlow' => $c['question_flow'],
        'closingStatement' => $c['closing_statement']
    ]);
}

json_response(['error' => 'Route not found'], 404);
