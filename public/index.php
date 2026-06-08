<?php
/**
 * public/index.php - Front controller for HowToCookViewer PHP edition.
 *
 * URL scheme (query string based, no .htaccess required):
 *   /              → home
 *   /browse        → browse
 *   /recipe/{id}   → recipe detail
 *   /login         → login page
 *   /register      → register page
 *   /logout        → POST logout
 *   /profile       → profile settings
 *   /favorites     → favorites
 *   /admin         → admin dashboard
 *   /like/{id}     → POST toggle like
 *   /favorite/{id} → POST toggle favorite
 *   /comment/{id}  → POST add comment
 *   /comment/delete/{id} → POST delete comment
 *
 * Security:
 *  - Security headers sent on every response.
 *  - All POST routes verify CSRF token before processing.
 *  - User input is validated/escaped at usage point.
 */

declare(strict_types=1);

// ── Bootstrap ──────────────────────────────────────────────────────────────────

define('BASE_DIR', dirname(__DIR__));

require BASE_DIR . '/helpers.php';
require BASE_DIR . '/db.php';
require BASE_DIR . '/auth.php';

// Start session & send security headers before any output
auth_start();
send_security_headers();

// ── Routing ───────────────────────────────────────────────────────────────────

/**
 * Simple router: parse PATH_INFO or REQUEST_URI into a route string.
 * Returns something like 'recipe/42', 'browse', 'login', etc.
 */
function get_route(): string {
    $uri    = $_SERVER['REQUEST_URI'] ?? '/';
    $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    // Strip query string
    $path   = strtok($uri, '?');
    // Strip script path prefix
    $base   = rtrim(dirname($script), '/');
    if ($base && str_starts_with($path, $base)) {
        $path = substr($path, strlen($base));
    }
    return trim($path, '/');
}

$route  = get_route();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// ─── Route dispatch ──────────────────────────────────────────────────────────

// ── POST: Logout ──────────────────────────────────────────────────────────────
if ($route === 'logout' && $method === 'POST') {
    verify_csrf();
    logout();
    header('Location: ' . base_url());
    exit;
}

// ── POST: Like toggle ─────────────────────────────────────────────────────────
if (preg_match('#^like/(\d+)$#', $route, $m) && $method === 'POST') {
    require_login();
    verify_csrf();
    $rid  = (int)$m[1];
    $uid  = (int)(current_user()['id']);
    $has  = db_scalar("SELECT 1 FROM recipe_likes WHERE user_id=? AND recipe_id=?", [$uid, $rid]);
    if ($has) {
        db_exec("DELETE FROM recipe_likes WHERE user_id=? AND recipe_id=?", [$uid, $rid]);
    } else {
        db_exec("INSERT OR IGNORE INTO recipe_likes(user_id,recipe_id) VALUES(?,?)", [$uid, $rid]);
    }
    header('Location: ' . base_url('recipe/' . $rid) . '#top');
    exit;
}

// ── POST: Favorite toggle ─────────────────────────────────────────────────────
if (preg_match('#^favorite/(\d+)$#', $route, $m) && $method === 'POST') {
    require_login();
    verify_csrf();
    $rid = (int)$m[1];
    $uid = (int)(current_user()['id']);
    $has = db_scalar("SELECT 1 FROM recipe_favorites WHERE user_id=? AND recipe_id=?", [$uid, $rid]);
    if ($has) {
        db_exec("DELETE FROM recipe_favorites WHERE user_id=? AND recipe_id=?", [$uid, $rid]);
    } else {
        db_exec("INSERT OR IGNORE INTO recipe_favorites(user_id,recipe_id,created_at) VALUES(?,?,datetime('now'))", [$uid, $rid]);
    }
    header('Location: ' . base_url('recipe/' . $rid) . '#top');
    exit;
}

// ── POST: Add comment ─────────────────────────────────────────────────────────
if (preg_match('#^comment/(\d+)$#', $route, $m) && $method === 'POST') {
    require_login();
    verify_csrf();
    $recipe_id = (int)$m[1];
    $uid       = (int)(current_user()['id']);
    $content   = trim($_POST['content'] ?? '');
    $parent_id = isset($_POST['parent_comment_id']) ? (int)$_POST['parent_comment_id'] : null;

    if (strlen($content) < 1 || strlen($content) > 1000) {
        flash('danger', '评论内容不能为空，且不超过 1000 字。');
    } else {
        // Verify recipe exists
        $recipe_exists = db_scalar("SELECT 1 FROM recipes WHERE id=? AND is_deleted=0", [$recipe_id]);
        if (!$recipe_exists) {
            flash('danger', '菜谱不存在。');
        } else {
            // Verify parent comment belongs to same recipe (if provided)
            if ($parent_id) {
                $parent = db_row("SELECT id FROM recipe_comments WHERE id=? AND recipe_id=?", [$parent_id, $recipe_id]);
                if (!$parent) $parent_id = null;
            }

            // Rate limit: max 10 comments per user per day
            $today_count = (int)db_scalar(
                "SELECT COUNT(*) FROM recipe_comments
                 WHERE user_id=? AND date(created_at)=date('now')",
                [$uid]
            );
            if ($today_count >= 10) {
                flash('danger', '你今天的评论数已达上限（10条），请明天再来。');
            } else {
                db_exec(
                    "INSERT INTO recipe_comments (recipe_id, user_id, parent_comment_id, content)
                     VALUES (?, ?, ?, ?)",
                    [$recipe_id, $uid, $parent_id, $content]
                );
                flash('success', '评论发布成功！');
            }
        }
    }
    header('Location: ' . base_url('recipe/' . $recipe_id) . '#comments');
    exit;
}

// ── POST: Delete comment ──────────────────────────────────────────────────────
if (preg_match('#^comment/delete/(\d+)$#', $route, $m) && $method === 'POST') {
    require_login();
    verify_csrf();
    $cid  = (int)$m[1];
    $uid  = (int)(current_user()['id']);
    $comment = db_row("SELECT * FROM recipe_comments WHERE id=?", [$cid]);
    if ($comment && (int)$comment['user_id'] === $uid) {
        db_exec("DELETE FROM recipe_comments WHERE id=?", [$cid]);
        flash('success', '评论已删除。');
        header('Location: ' . base_url('recipe/' . $comment['recipe_id']) . '#comments');
    } else {
        http_response_code(403);
        die('不允许的操作。');
    }
    exit;
}

// ── GET/POST: recipe detail ───────────────────────────────────────────────────
if (preg_match('#^recipe/(\d+)$#', $route, $m)) {
    $_GET['id'] = $m[1];
    require BASE_DIR . '/views/detail.php';
    exit;
}

// ── GET/POST: tip detail ───────────────────────────────────────────────────────
if (preg_match('#^tip/(\d+)$#', $route, $m)) {
    $_GET['id'] = $m[1];
    require BASE_DIR . '/views/tip_detail.php';
    exit;
}

// ── GET/POST: named routes ────────────────────────────────────────────────────
$named_routes = [
    ''            => '/views/home.php',
    'browse'      => '/views/browse.php',
    'ingredients' => '/views/ingredients.php',
    'tips'        => '/views/tips.php',
    'login'       => '/views/login.php',
    'register'    => '/views/register.php',
    'profile'     => '/views/profile.php',
    'favorites'   => '/views/favorites.php',
    'admin'       => '/views/admin.php',
];

if (array_key_exists($route, $named_routes)) {
    require BASE_DIR . $named_routes[$route];
    exit;
}

// ── 404 fallback ─────────────────────────────────────────────────────────────
http_response_code(404);
ob_start();
?>
<div class="container text-center py-5">
    <div style="font-size:5rem;">🍽️</div>
    <h1 class="display-4 fw-bold mt-3">404</h1>
    <p class="text-muted fs-5">页面不存在</p>
    <a href="<?= e(base_url()) ?>" class="btn btn-primary btn-lg mt-2">返回首页</a>
</div>
<?php
$content    = ob_get_clean();
$page_title = '页面未找到';
$no_sidebar = true;
require BASE_DIR . '/views/layout.php';
