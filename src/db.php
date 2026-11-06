<?php
declare(strict_types=1);

/*
 * Single database connection (issues #1 and #8).
 * - Credentials come only from environment variables; there is no root/empty-password fallback.
 * - Real prepared statements (emulation off) and exceptions on error.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $user = getenv('LAB_DB_USER');
    $password = getenv('LAB_DB_PASSWORD');
    if ($user === false || $user === '' || $password === false) {
        throw new RuntimeException('Database credentials are not configured. Set LAB_DB_USER and LAB_DB_PASSWORD.');
    }
    if (strtolower($user) === 'root') {
        throw new RuntimeException('Refusing to connect as root. Use the least-privilege lab account.');
    }

    // LAB_DB_DSN is optional (used only for automated testing); normally the MySQL DSN below is used.
    $dsn = getenv('LAB_DB_DSN') ?: sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        getenv('LAB_DB_HOST') ?: '127.0.0.1',
        getenv('LAB_DB_PORT') ?: '3306',
        getenv('LAB_DB_NAME') ?: 'notes_lab'
    );

    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}
