<?php

declare(strict_types=1);

require_once __DIR__ . '/call_engine.php';

function get_config(PDO $db): array
{
    $row = $db->query('SELECT * FROM config WHERE id = 1')->fetch();
    return [
        'language_style' => $row['language_style'],
        'tone' => $row['tone'],
        'opening_script' => $row['opening_script'],
        'question_flow' => json_decode($row['question_flow'], true, 512, JSON_THROW_ON_ERROR),
        'closing_statement' => $row['closing_statement']
    ];
}

function queue_call(PDO $db, int $leadId): array
{
    $leadStmt = $db->prepare('SELECT id FROM leads WHERE id = ? LIMIT 1');
    $leadStmt->execute([$leadId]);
    if (!$leadStmt->fetch()) {
        return ['queued' => false, 'reason' => 'Lead not found'];
    }

    $q = $db->prepare('SELECT id FROM call_queue WHERE lead_id = ? AND status IN ("pending", "processing") LIMIT 1');
    $q->execute([$leadId]);
    if ($q->fetch()) {
        return ['queued' => false, 'reason' => 'Lead already in queue or active'];
    }

    $now = gmdate('c');
    $ins = $db->prepare('INSERT INTO call_queue (lead_id, status, attempt, created_at, updated_at) VALUES (?, "pending", 1, ?, ?)');
    $ins->execute([$leadId, $now, $now]);

    return ['queued' => true];
}

function process_queue(PDO $db, int $limit = 2): void
{
    $db->beginTransaction();
    $stmt = $db->prepare('SELECT * FROM call_queue WHERE status = "pending" ORDER BY id ASC LIMIT ?');
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll();

    foreach ($items as $item) {
        $update = $db->prepare('UPDATE call_queue SET status = "processing", updated_at = ? WHERE id = ?');
        $update->execute([gmdate('c'), $item['id']]);
    }
    $db->commit();

    foreach ($items as $item) {
        process_queue_item($db, $item);
    }
}

function process_queue_item(PDO $db, array $item): void
{
    $leadStmt = $db->prepare('SELECT * FROM leads WHERE id = ? LIMIT 1');
    $leadStmt->execute([(int) $item['lead_id']]);
    $lead = $leadStmt->fetch();

    if (!$lead) {
        $db->prepare('DELETE FROM call_queue WHERE id = ?')->execute([$item['id']]);
        return;
    }

    $config = get_config($db);
    $attempt = (int) $item['attempt'];
    $call = simulate_call($lead, $config, $attempt);
    $now = gmdate('c');

    $ins = $db->prepare('INSERT INTO call_logs (lead_id, status, attempt, duration, transcript, summary, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $ins->execute([(int) $lead['id'], $call['status'], $attempt, $call['duration'], $call['transcript'], $call['summary'], $now, $now]);

    if ($call['status'] === 'No Answer' && $attempt < 2) {
        $db->prepare('UPDATE call_queue SET status = "pending", attempt = ?, updated_at = ? WHERE id = ?')
            ->execute([$attempt + 1, $now, $item['id']]);
        return;
    }

    $db->prepare('DELETE FROM call_queue WHERE id = ?')->execute([$item['id']]);

    $leadStatus = $call['status'] === 'Completed' ? $call['leadScore'] : 'Called';
    $db->prepare('UPDATE leads SET status = ?, updated_at = ? WHERE id = ?')
        ->execute([$leadStatus, $now, (int) $lead['id']]);
}
