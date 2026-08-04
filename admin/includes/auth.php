<?php
// Single-admin session auth. No user accounts — just one shared password
// for Bernadett, hashed (PBKDF2-SHA256) in the gitignored config.php.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function admin_config(): array {
    static $cfg = null;
    if ($cfg !== null) return $cfg;
    $path = __DIR__ . '/../config.php';
    if (!is_file($path)) {
        http_response_code(500);
        die('admin/config.php is missing — copy admin/config.sample.php and fill in real values.');
    }
    $cfg = require $path;
    return $cfg;
}

function is_logged_in(): bool {
    return !empty($_SESSION['blaya_admin_authed']);
}

function require_login(): void {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

// Basic brute-force throttle: after 5 failed attempts, lock out for 5 minutes.
function login_locked_out(): bool {
    $until = $_SESSION['blaya_login_locked_until'] ?? 0;
    return $until > time();
}

function attempt_login(string $password): bool {
    if (login_locked_out()) return false;

    $cfg = admin_config();
    $calc = hash_pbkdf2(
        'sha256',
        $password,
        $cfg['admin_password_salt'],
        (int)$cfg['admin_password_iterations'],
        32
    );

    $ok = hash_equals($cfg['admin_password_hash'], $calc);

    if ($ok) {
        $_SESSION['blaya_admin_authed'] = true;
        $_SESSION['blaya_login_attempts'] = 0;
        session_regenerate_id(true);
        return true;
    }

    $attempts = ($_SESSION['blaya_login_attempts'] ?? 0) + 1;
    $_SESSION['blaya_login_attempts'] = $attempts;
    if ($attempts >= 5) {
        $_SESSION['blaya_login_locked_until'] = time() + 300;
    }
    usleep(400000); // slow down brute-force guessing a little
    return false;
}

function logout(): void {
    $_SESSION = [];
    session_destroy();
}

function csrf_token(): string {
    if (empty($_SESSION['blaya_csrf'])) {
        $_SESSION['blaya_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['blaya_csrf'];
}

function verify_csrf(?string $token): bool {
    return is_string($token) && hash_equals($_SESSION['blaya_csrf'] ?? '', $token);
}
