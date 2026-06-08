<?php
/**
 * auth.php - Session management, authentication, and authorization.
 * Security:
 *  - Passwords hashed with bcrypt (cost=12).
 *  - Session regenerated on privilege change to prevent session fixation.
 *  - CSRF tokens required for all state-changing POST requests.
 *  - HttpOnly + SameSite=Lax session cookie flags configured at session_start().
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

/**
 * Start the session with hardened cookie settings.
 * Call once at the top of the request lifecycle.
 */
function auth_start(): void {
    if (session_status() !== PHP_SESSION_NONE) return;

    session_set_cookie_params([
        'lifetime' => 0,          // Session cookie (expires on browser close)
        'path'     => '/',
        'domain'   => '',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true,        // Not accessible via JavaScript (XSS mitigation)
        'samesite' => 'Lax',      // CSRF mitigation
    ]);
    session_start();
}

/**
 * Return the currently logged-in user row, or null if not authenticated.
 */
function current_user(): ?array {
    auth_start();
    $uid = $_SESSION['user_id'] ?? null;
    if (!$uid) return null;
    return db_row("SELECT * FROM users WHERE id = ?", [(int)$uid]);
}

/**
 * Return true if a user is currently logged in.
 */
function is_logged_in(): bool {
    return current_user() !== null;
}

/**
 * Return true if the current user is an administrator.
 */
function is_admin(): bool {
    $user = current_user();
    return $user !== null && (int)$user['is_admin'] === 1;
}

/**
 * Require the user to be logged in; redirect to login page otherwise.
 */
function require_login(): void {
    if (!is_logged_in()) {
        $return = urlencode($_SERVER['REQUEST_URI'] ?? '/');
        header('Location: /?page=login&return=' . $return);
        exit;
    }
}

/**
 * Require the user to be an admin; return 403 otherwise.
 */
function require_admin(): void {
    if (!is_admin()) {
        http_response_code(403);
        die('Access denied.');
    }
}

/**
 * Attempt to log in with email and password.
 * Returns the user array on success, or null on failure.
 * Security: Uses hash_equals-safe password_verify, not string comparison.
 */
function attempt_login(string $email, string $password): ?array {
    // Sanitize email before DB query
    $email = trim($email);
    if (strlen($email) > 254 || strlen($password) > 256) return null;

    $user = db_row("SELECT * FROM users WHERE email = ?", [$email]);
    if (!$user) return null;

    if (!password_verify($password, $user['password_hash'])) return null;

    // Regenerate session ID on successful login (session fixation prevention)
    auth_start();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];

    return $user;
}

/**
 * Register a new user.
 * Returns ['ok' => true, 'user' => [...]] or ['ok' => false, 'error' => '...'].
 */
function attempt_register(string $email, string $display_name, string $password): array {
    $email        = trim($email);
    $display_name = trim($display_name);

    // Input validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => '邮箱格式不正确'];
    }
    if (strlen($email) > 254) {
        return ['ok' => false, 'error' => '邮箱过长'];
    }
    if (strlen($display_name) < 2 || strlen($display_name) > 30) {
        return ['ok' => false, 'error' => '昵称长度应在 2-30 字符之间'];
    }
    // Password strength: min 8 chars, allow all characters
    if (strlen($password) < 8) {
        return ['ok' => false, 'error' => '密码至少 8 位'];
    }
    if (strlen($password) > 256) {
        return ['ok' => false, 'error' => '密码过长'];
    }

    // Check uniqueness
    $existing = db_row("SELECT id FROM users WHERE email = ?", [$email]);
    if ($existing) {
        return ['ok' => false, 'error' => '该邮箱已被注册'];
    }

    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    db_exec(
        "INSERT INTO users (email, display_name, password_hash) VALUES (?, ?, ?)",
        [$email, $display_name, $hash]
    );
    $uid = (int)db_last_id();

    // If this is the very first registered user, promote to admin automatically
    $total = (int)db_scalar("SELECT COUNT(*) FROM users");
    if ($total === 1) {
        db_exec("UPDATE users SET is_admin=1 WHERE id=?", [$uid]);
    }

    auth_start();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $uid;

    return ['ok' => true, 'user' => db_row("SELECT * FROM users WHERE id = ?", [$uid])];
}

/**
 * Log out the current user. Destroys the session and clears the cookie.
 */
function logout(): void {
    auth_start();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/**
 * Update the email of an existing user (does NOT change is_admin).
 * Returns '' on success or an error message string.
 */
function update_email(int $uid, string $new_email): string {
    $new_email = trim($new_email);
    if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        return '邮箱格式不正确';
    }
    if (strlen($new_email) > 254) {
        return '邮箱过长';
    }
    $existing = db_row("SELECT id FROM users WHERE email = ? AND id != ?", [$new_email, $uid]);
    if ($existing) {
        return '该邮箱已被其他账号使用';
    }
    db_exec("UPDATE users SET email=? WHERE id=?", [$new_email, $uid]);
    return '';
}

/**
 * Update the display name of an existing user (does NOT change is_admin).
 * Returns '' on success or an error message string.
 */
function update_display_name(int $uid, string $name): string {
    $name = trim($name);
    if (strlen($name) < 2 || strlen($name) > 30) {
        return '昵称长度应在 2-30 字符之间';
    }
    db_exec("UPDATE users SET display_name=? WHERE id=?", [$name, $uid]);
    return '';
}
