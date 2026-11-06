<?php
declare(strict_types=1);

/*
 * Accounts and sessions (issues #1, #2, #6, #7).
 */

const SESSION_IDLE_SECONDS = 1800;
const PASSWORD_MIN_LENGTH = 12;
// Hash of a random value: used so a login for an unknown email costs the same time as a real one.
const DUMMY_PASSWORD_HASH = '$2y$10$wpqoIDl6OKaVSISZDr7aNOIWDobYhk3z/b7nPJNLdEmNk4xdsfoCS';

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');   // reject session IDs the server did not create
    ini_set('session.use_only_cookies', '1');  // never accept IDs from the URL
    ini_set('session.use_trans_sid', '0');

    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_name('campus_notes_sid');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,       // true automatically once served over HTTPS
        'httponly' => true,       // not readable from JavaScript
        'samesite' => 'Lax',      // not sent on cross-site POSTs
    ]);
    session_start();

    $now = time();
    if (isset($_SESSION['last_seen']) && $now - (int) $_SESSION['last_seen'] > SESSION_IDLE_SECONDS) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['last_seen'] = $now;
}

function current_user_id(): ?int
{
    $id = $_SESSION['user_id'] ?? null;
    return is_int($id) && $id > 0 ? $id : null;
}

/** Returns the user row on success, null on any failure (same result for wrong email or wrong password). */
function authenticate(string $email, string $password): ?array
{
    $stmt = db()->prepare('SELECT id, email, password_hash, role FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        password_verify($password, DUMMY_PASSWORD_HASH);
        return null;
    }
    if (!password_verify($password, (string) $user['password_hash'])) {
        return null;
    }
    if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
        $update = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $update->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
    }
    return $user;
}

function login_user(array $user): void
{
    session_regenerate_id(true);          // new session ID after login (prevents session fixation)
    $_SESSION = [
        'user_id' => (int) $user['id'],
        'email' => (string) $user['email'],
        'role' => (string) $user['role'], // read from the database, never from the request
        'last_seen' => time(),
    ];
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 3600,
            'path' => $p['path'],
            'domain' => $p['domain'],
            'secure' => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'] ?: 'Lax',
        ]);
    }
    session_destroy();
}

/**
 * Creates a student account. Returns null on success or an error message.
 * The role is always the database default ('student'); any role sent by the client is ignored.
 */
function register_user(string $email, string $password): ?string
{
    $email = trim($email);
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 254) {
        return 'Enter a valid email address.';
    }
    if (text_length($password) < PASSWORD_MIN_LENGTH || strlen($password) > 72) {
        return 'Password must be ' . PASSWORD_MIN_LENGTH . ' to 72 characters.';
    }

    try {
        $stmt = db()->prepare('INSERT INTO users (email, password_hash) VALUES (?, ?)');
        $stmt->execute([$email, password_hash($password, PASSWORD_DEFAULT)]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {      // duplicate email
            return 'Registration failed. Try a different email address.';
        }
        throw $e;
    }
    return null;
}
