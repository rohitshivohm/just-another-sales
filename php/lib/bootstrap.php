<?php

declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dbPath = __DIR__ . '/../../data/app.sqlite';
    if (!is_dir(dirname($dbPath))) {
        mkdir(dirname($dbPath), 0775, true);
    }

    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    migrate($pdo);
    seed($pdo);

    return $pdo;
}

function migrate(PDO $db): void
{
    $db->exec('CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        created_at TEXT NOT NULL
    )');

    $db->exec('CREATE TABLE IF NOT EXISTS sessions (
        token TEXT PRIMARY KEY,
        user_id INTEGER NOT NULL,
        expires_at TEXT NOT NULL,
        created_at TEXT NOT NULL
    )');

    $db->exec('CREATE TABLE IF NOT EXISTS leads (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        phone TEXT NOT NULL,
        city TEXT NOT NULL,
        status TEXT NOT NULL,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )');

    $db->exec('CREATE TABLE IF NOT EXISTS call_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        lead_id INTEGER NOT NULL,
        status TEXT NOT NULL,
        attempt INTEGER NOT NULL,
        duration INTEGER NOT NULL,
        transcript TEXT NOT NULL,
        summary TEXT NOT NULL,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )');

    $db->exec('CREATE TABLE IF NOT EXISTS call_queue (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        lead_id INTEGER NOT NULL,
        status TEXT NOT NULL,
        attempt INTEGER NOT NULL,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )');

    $db->exec('CREATE TABLE IF NOT EXISTS config (
        id INTEGER PRIMARY KEY CHECK (id = 1),
        language_style TEXT NOT NULL,
        tone TEXT NOT NULL,
        opening_script TEXT NOT NULL,
        question_flow TEXT NOT NULL,
        closing_statement TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )');
}

function seed(PDO $db): void
{
    $countUsers = (int) $db->query('SELECT COUNT(*) AS c FROM users')->fetch()['c'];
    if ($countUsers === 0) {
        $username = getenv('ADMIN_USERNAME') ?: 'admin';
        $password = getenv('ADMIN_PASSWORD') ?: 'admin123';
        $stmt = $db->prepare('INSERT INTO users (username, password, created_at) VALUES (?, ?, ?)');
        $stmt->execute([$username, password_hash($password, PASSWORD_BCRYPT), gmdate('c')]);
    }

    $countLeads = (int) $db->query('SELECT COUNT(*) AS c FROM leads')->fetch()['c'];
    if ($countLeads === 0) {
        $now = gmdate('c');
        $stmt = $db->prepare('INSERT INTO leads (name, phone, city, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)');
        foreach ([
            ['Rahul Sharma', '+15551230001', 'Austin', 'New'],
            ['Neha Kapoor', '+15551230002', 'Seattle', 'Warm'],
            ['Aman Verma', '+15551230003', 'Chicago', 'Hot']
        ] as $lead) {
            $stmt->execute([$lead[0], $lead[1], $lead[2], $lead[3], $now, $now]);
        }
    }

    $countConfig = (int) $db->query('SELECT COUNT(*) AS c FROM config')->fetch()['c'];
    if ($countConfig === 0) {
        $stmt = $db->prepare('INSERT INTO config (id, language_style, tone, opening_script, question_flow, closing_statement, updated_at)
            VALUES (1, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            'English',
            'friendly',
            'Hello! This is Alex from GrowthEdge. Is this a good time to talk?',
            json_encode([
                'Are you currently evaluating sales automation tools?',
                'What is your current process for follow-up calls?',
                'Would improving conversion rates in 30 days be valuable for you?'
            ], JSON_THROW_ON_ERROR),
            'Thanks for your time. I will share the next steps over SMS and email.',
            gmdate('c')
        ]);
    }
}

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

function parse_json_body(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}
