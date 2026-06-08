<?php
/**
 * helpers.php - Security utilities, CSRF, output encoding, category mappings.
 * Security: All user-controlled output MUST go through e() before rendering.
 */

declare(strict_types=1);

// ─── PHP 7.4 Polyfills ────────────────────────────────────────────────────────

if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool {
        return strlen($needle) === 0 || strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool {
        return strlen($needle) === 0 || substr($haystack, -strlen($needle)) === $needle;
    }
}
if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool {
        return strlen($needle) === 0 || strpos($haystack, $needle) !== false;
    }
}


/**
 * Safely escape a string for HTML output (XSS prevention).
 */
function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ─── Security Headers ─────────────────────────────────────────────────────────

/**
 * Send hardened HTTP security headers.
 * Must be called before any output is sent.
 */
function send_security_headers(): void {
    // Clickjacking protection
    header('X-Frame-Options: SAMEORIGIN');
    // MIME-type sniffing prevention
    header('X-Content-Type-Options: nosniff');
    // Referrer policy
    header('Referrer-Policy: strict-origin-when-cross-origin');
    // Permissions policy - disable unused browser features
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    // CSP: allow self + trusted CDNs for Bootstrap/Lucide loaded locally
    // TODO(security): Tighten CSP nonces for inline scripts in production.
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https://www.gravatar.com; font-src 'self'; frame-ancestors 'self';");
}

// ─── CSRF Token Utilities ────────────────────────────────────────────────────

/**
 * Generate and store a CSRF token in the session, or retrieve existing one.
 */
function csrf_token(): string {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

/**
 * Return an HTML hidden input field with the CSRF token.
 */
function csrf_field(): string {
    return '<input type="hidden" name="_csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Verify that the POST request contains a valid CSRF token.
 * Terminates the request if validation fails.
 */
function verify_csrf(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $submitted = $_POST['_csrf_token'] ?? '';
    $expected  = $_SESSION['_csrf_token'] ?? '';
    if (!$submitted || !$expected || !hash_equals($expected, $submitted)) {
        http_response_code(403);
        die('CSRF token validation failed.');
    }
}

// ─── Category Mappings ───────────────────────────────────────────────────────

const CATEGORY_MAP = [
    'vegetable_dish' => '蔬菜类',
    'meat_dish'      => '肉类',
    'aquatic'        => '水产类',
    'breakfast'      => '早餐',
    'staple'         => '主食',
    'soup'           => '汤羹',
    'drink'          => '饮品',
    'dessert'        => '甜点',
    'condiment'      => '调味料',
    'semi-finished'  => '半成品',
    'template'       => '模板',
];

const CATEGORY_ICONS = [
    'vegetable_dish' => '🥬',
    'meat_dish'      => '🥩',
    'aquatic'        => '🐟',
    'breakfast'      => '🍳',
    'staple'         => '🍚',
    'soup'           => '🍲',
    'drink'          => '🥤',
    'dessert'        => '🍰',
    'condiment'      => '🧂',
    'semi-finished'  => '🥡',
    'template'       => '📋',
];

/**
 * Get the display name for a category slug.
 */
function category_name(string $slug): string {
    return CATEGORY_MAP[$slug] ?? $slug;
}

/**
 * Get the emoji icon for a category slug.
 */
function category_icon(string $slug): string {
    return CATEGORY_ICONS[$slug] ?? '🍽️';
}

// ─── Gravatar ────────────────────────────────────────────────────────────────

/**
 * Return the Gravatar URL for a given email address.
 */
function gravatar_url(string $email, int $size = 40): string {
    $hash = md5(strtolower(trim($email)));
    return "https://www.gravatar.com/avatar/{$hash}?d=identicon&s={$size}";
}

// ─── URL Helpers ─────────────────────────────────────────────────────────────

/**
 * Return the base URL path prefix (for use inside index.php router).
 */
function base_url(string $path = ''): string {
    // On Windows, dirname() returns '\' for root-level paths.
    // We normalize to forward slashes and trim both slash types.
    $script_name = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
    $base = rtrim(str_replace('\\', '/', dirname($script_name)), '/');
    if ($base === '.' || $base === '') {
        $base = '';
    }
    return $base . '/' . ltrim($path, '/');
}

/**
 * Build query string from array, omitting nulls and empty strings.
 */
function build_query(array $params): string {
    $filtered = array_filter($params, fn($v) => $v !== null && $v !== '');
    return $filtered ? '?' . http_build_query($filtered) : '';
}

// ─── Flash Messages ──────────────────────────────────────────────────────────

/**
 * Set a flash message in the session.
 */
function flash(string $type, string $message): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

/**
 * Retrieve and clear all flash messages. Returns array of ['type','message'].
 */
function get_flashes(): array {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $flashes = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $flashes;
}

/**
 * Render flash messages as Bootstrap alerts.
 */
function render_flashes(): string {
    $html = '';
    foreach (get_flashes() as $f) {
        $type = in_array($f['type'], ['success','danger','warning','info'], true)
            ? $f['type'] : 'info';
        $html .= '<div class="alert alert-' . e($type) . ' alert-dismissible fade show" role="alert">'
            . e($f['message'])
            . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }
    return $html;
}

// ─── Difficulty Stars ─────────────────────────────────────────────────────────

/**
 * Return filled/empty star string for a difficulty value (1-5).
 */
function difficulty_stars(int $difficulty): string {
    $filled = str_repeat('★', max(0, $difficulty));
    $empty  = str_repeat('☆', max(0, 5 - $difficulty));
    return $filled . $empty;
}

// ─── Pagination ───────────────────────────────────────────────────────────────

/**
 * Simple integer-clamped page extraction from query string.
 */
function current_page(int $min = 1): int {
    return max($min, (int)($_GET['page'] ?? 1));
}
