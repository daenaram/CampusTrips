<?php

function isAccountLocked(PDO $pdo, string $email): bool
{
    $stmt = $pdo->prepare("
        SELECT locked_out
        FROM users
        WHERE email = ?
    ");
    $stmt->execute([$email]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || empty($user['locked_out'])) {
        return false;
    }

    return time() < strtotime($user['locked_out']);
}

function recordFailedLogin(PDO $pdo, string $email): void
{
    $stmt = $pdo->prepare("
        SELECT failed_attempts
        FROM users
        WHERE email = ?
    ");
    $stmt->execute([$email]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        return;
    }

    $attempts = (int)$user['failed_attempts'] + 1;

    if ($attempts >= 3) {
        $lockUntil = date("Y-m-d H:i:s", strtotime("+15 minutes"));

        $stmt = $pdo->prepare("
            UPDATE users
            SET failed_attempts = 0,
                locked_out = ?
            WHERE email = ?
        ");
        $stmt->execute([$lockUntil, $email]);
    } else {
        $stmt = $pdo->prepare("
            UPDATE users
            SET failed_attempts = ?
            WHERE email = ?
        ");
        $stmt->execute([$attempts, $email]);
    }
}

function resetFailedLogins(PDO $pdo, string $email): void
{
    $stmt = $pdo->prepare("
        UPDATE users
        SET failed_attempts = 0,
            locked_out = NULL
        WHERE email = ?
    ");
    $stmt->execute([$email]);
}