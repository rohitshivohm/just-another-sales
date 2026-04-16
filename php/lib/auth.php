<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function login(PDO $db, string $username, string $password): ?array
{
    $stmt = $db->prepare('SELECT id, username, password FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        return null;
    }

    $token = bin2hex(random_bytes(24));
    $now = gmdate('c');
    $expires = gmdate('c', time() + 8 * 3600);

    $insert = $db->prepare('INSERT INTO sessions (token, user_id, expires_at, created_at) VALUES (?, ?, ?, ?)');
    $insert->execute([$token, (int) $user['id'], $expires, $now]);

    return ['token' => $token, 'user' => ['id' => (int) $user['id'], 'username' => $user['username']]];
}

function require_auth(PDO $db): array
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $token = str_starts_with($header, 'Bearer ') ? substr($header, 7) : '';
    if ($token === '') {
        json_response(['error' => 'Missing auth token'], 401);
    }

    $stmt = $db->prepare('SELECT s.token, s.expires_at, u.id, u.username
        FROM sessions s JOIN users u ON s.user_id = u.id WHERE s.token = ? LIMIT 1');
    $stmt->execute([$token]);
    $session = $stmt->fetch();

    if (!$session || strtotime($session['expires_at']) < time()) {
        json_response(['error' => 'Invalid or expired token'], 401);
    }

    return ['id' => (int) $session['id'], 'username' => $session['username'], 'token' => $session['token']];
}

function logout(PDO $db, string $token): void
{
    $stmt = $db->prepare('DELETE FROM sessions WHERE token = ?');
    $stmt->execute([$token]);
}
