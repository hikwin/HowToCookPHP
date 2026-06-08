<?php
/**
 * views/layout.php - Base HTML layout template.
 *
 * Usage:
 *   $page_title = 'My Title';
 *   $body_class = 'extra-class';
 *   $content    = '<p>Page body</p>';
 *   require __DIR__ . '/../views/layout.php';
 *
 * Variables expected:
 *   $page_title  string  Page <title>
 *   $content     string  Main content HTML (already escaped / safe)
 *   $head_extra  string  Optional extra <head> content
 *   $scripts     string  Optional extra scripts at end of <body>
 *   $no_sidebar  bool    If true, renders without the side category bar
 */
declare(strict_types=1);

if (!isset($page_title)) $page_title = 'HowToCook';
if (!isset($head_extra)) $head_extra = '';
if (!isset($scripts))    $scripts    = '';
if (!isset($no_sidebar)) $no_sidebar = false;

$site_name = get_setting('site_name', 'HowToCook');
$user      = current_user();

$categories_list = db_rows(
    "SELECT category, COUNT(*) as cnt FROM recipes WHERE is_deleted=0 GROUP BY category ORDER BY cnt DESC"
);

// Active route for sidebar highlighting
$current_page_id = $route ?? $_GET['page'] ?? 'home';
if ($current_page_id === '') {
    $current_page_id = 'home';
}
$current_cat     = $_GET['category'] ?? '';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= e($site_name) ?> - 社区驱动的中文菜谱网站，免费开源">
    <title><?= e($page_title) ?> - <?= e($site_name) ?></title>
    <link rel="stylesheet" href="<?= e(base_url('css/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(base_url('css/site.css')) ?>">
    <?= $head_extra ?>
</head>
<body class="<?= $no_sidebar ? 'no-sidebar' : '' ?>">

<!-- ── Navbar ──────────────────────────────────────────────────────────── -->
<nav class="navbar navbar-expand-lg navbar-dark navbar-glass fixed-top">
    <div class="container-fluid px-3">
        <!-- Brand -->
        <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="<?= e(base_url('browse')) ?>">
            <span class="brand-icon">🍳</span>
            <span><?= e($site_name) ?></span>
        </a>

        <!-- Mobile toggle -->
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navMain">
            <!-- Search bar -->
            <form class="d-flex ms-lg-3 flex-grow-1 me-lg-4 my-2 my-lg-0" method="get" action="<?= e(base_url('browse')) ?>">
                <div class="input-group">
                    <input class="form-control bg-white-10 border-white-15 text-white placeholder-white-50"
                           type="search" name="q"
                           value="<?= e($_GET['q'] ?? '') ?>"
                           placeholder="搜索菜谱、食材…"
                           maxlength="60">
                    <button class="btn btn-primary" type="submit">搜索</button>
                </div>
            </form>

            <ul class="navbar-nav ms-auto align-items-lg-center gap-1">
                <li class="nav-item">
                    <a class="nav-link <?= $current_page_id === 'browse' ? 'active' : '' ?>"
                       href="<?= e(base_url('browse')) ?>">
                        🍽️ 浏览菜谱
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page_id === 'ingredients' ? 'active' : '' ?>"
                       href="<?= e(base_url('ingredients')) ?>">
                        🔍 食材反查
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page_id === 'tips' ? 'active' : '' ?>"
                       href="<?= e(base_url('tips')) ?>">
                        💡 烹饪技巧
                    </a>
                </li>

                <?php if ($user): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= e(base_url('favorites')) ?>">
                            ❤️ 我的收藏
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center gap-2"
                           href="#" data-bs-toggle="dropdown">
                            <div class="user-avatar-sm">
                                <?= mb_substr((string)$user['display_name'], 0, 1) ?>
                            </div>
                            <?= e($user['display_name']) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?= e(base_url('profile')) ?>">⚙️ 个人设置</a></li>
                            <?php if ((int)$user['is_admin'] === 1): ?>
                                <li><a class="dropdown-item" href="<?= e(base_url('admin')) ?>">🛠️ 管理后台</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="post" action="<?= e(base_url('logout')) ?>">
                                    <?= csrf_field() ?>
                                    <button class="dropdown-item text-danger" type="submit">🚪 退出登录</button>
                                </form>
                            </li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item d-flex align-items-center gap-2 ps-2 ps-lg-0">
                        <a class="nav-link p-2 p-lg-2" href="<?= e(base_url('login')) ?>">登录</a>
                        <a class="btn btn-outline-light btn-sm ms-2 ms-lg-0" href="<?= e(base_url('register')) ?>">注册</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- ── Page wrapper ────────────────────────────────────────────────────── -->
<div class="page-wrapper <?= $no_sidebar ? 'no-sidebar' : 'with-sidebar' ?>">

<?php if (!$no_sidebar): ?>
<!-- ── Sidebar ─────────────────────────────────────────────────────────── -->
<aside class="sidebar">
    <div class="sidebar-inner">
        <div class="sidebar-section-title">分类浏览</div>
        <nav class="sidebar-nav">
            <a href="<?= e(base_url('tips')) ?>"
               class="sidebar-link <?= $current_page_id === 'tips' ? 'active' : '' ?>">
                <span class="sidebar-icon">💡</span>
                <span>烹饪技巧</span>
            </a>
            <a href="<?= e(base_url('ingredients')) ?>"
               class="sidebar-link <?= $current_page_id === 'ingredients' ? 'active' : '' ?>">
                <span class="sidebar-icon">🔍</span>
                <span>食材反查</span>
            </a>
            <a href="<?= e(base_url('browse')) ?>"
               class="sidebar-link <?= ($current_page_id === 'browse' && $current_cat === '') ? 'active' : '' ?>">
                <span class="sidebar-icon">🍽️</span>
                <span>全部菜谱</span>
                <span class="badge-count"><?= (int)db_scalar("SELECT COUNT(*) FROM recipes WHERE is_deleted=0") ?></span>
            </a>
            <?php foreach ($categories_list as $cat): ?>
            <a href="<?= e(base_url('browse') . '?category=' . urlencode($cat['category'])) ?>"
               class="sidebar-link <?= ($current_page_id === 'browse' && $current_cat === $cat['category']) ? 'active' : '' ?>">
                <span class="sidebar-icon"><?= category_icon($cat['category']) ?></span>
                <span><?= e(category_name($cat['category'])) ?></span>
                <span class="badge-count"><?= (int)$cat['cnt'] ?></span>
            </a>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar-section-title mt-4">难度筛选</div>
        <nav class="sidebar-nav">
            <?php for ($d = 1; $d <= 5; $d++): ?>
            <a href="<?= e(base_url('browse') . '?difficulty=' . $d) ?>"
               class="sidebar-link <?= (($_GET['difficulty'] ?? '') == $d) ? 'active' : '' ?>">
                <span><?= str_repeat('★', $d) . str_repeat('☆', 5 - $d) ?></span>
                <span class="ms-1"><?= ['初学','简单','中等','进阶','专家'][$d - 1] ?></span>
            </a>
            <?php endfor; ?>
        </nav>
    </div>
</aside>
<?php endif; ?>

<!-- ── Main content ────────────────────────────────────────────────────── -->
<main class="main-content">
    <?= render_flashes() ?>
    <?= $content ?>
</main>

</div><!-- .page-wrapper -->

<!-- ── Footer ─────────────────────────────────────────────────────────── -->
<footer class="site-footer">
    <div class="container text-center">
        <p class="mb-1 text-muted small">
            菜谱来自开源项目
            <a href="https://github.com/Anduin2017/HowToCook" target="_blank" rel="noopener">HowToCook</a>
            &nbsp;·&nbsp;
            本站基于 PHP + SQLite 实现
            &nbsp;·&nbsp;
            Power by <a href="https://hik.win/" target="_blank" rel="noopener">Hik.win</a>
        </p>
    </div>
</footer>

<script src="<?= e(base_url('js/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(base_url('js/lucide.min.js')) ?>"></script>
<script src="<?= e(base_url('js/site.js')) ?>"></script>
<?= $scripts ?>
</body>
</html>
