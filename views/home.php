<?php
/**
 * views/home.php - Landing page: hero search, features, and featured recipes.
 */
declare(strict_types=1);

// ── Data ──────────────────────────────────────────────────────────────────────

$total_recipes = (int)db_scalar("SELECT COUNT(*) FROM recipes WHERE is_deleted=0");

// Top liked recipes with cover images for the featured section
$featured_raw = db_rows(
    "SELECT r.*, ri.logical_path as cover_path,
        (SELECT COUNT(*) FROM recipe_likes rl WHERE rl.recipe_id=r.id) as like_count
     FROM recipes r
     LEFT JOIN recipe_images ri ON ri.recipe_id=r.id AND ri.is_cover=1
     WHERE r.is_deleted=0 AND ri.logical_path IS NOT NULL
     ORDER BY like_count DESC, r.id DESC
     LIMIT 8"
);
$like_counts = [];
foreach ($featured_raw as $r) {
    $like_counts[$r['id']] = $r['like_count'];
}
$recipes = $featured_raw;

// ── HTML ──────────────────────────────────────────────────────────────────────
ob_start();
?>
<!-- ── Hero Section ──────────────────────────────────────────────────── -->
<section class="hero-section">
    <div class="hero-bg-grid"></div>
    <div class="container py-5">
        <div class="row justify-content-center text-center">
            <div class="col-lg-8">
                <div class="mb-3 d-flex justify-content-center gap-2">
                    <span class="badge bg-primary-subtle text-primary-emphasis rounded-pill px-3">开源</span>
                    <span class="badge bg-success-subtle text-success-emphasis rounded-pill px-3">永久免费</span>
                    <span class="badge bg-info-subtle text-info-emphasis rounded-pill px-3">MIT License</span>
                </div>
                <h1 class="display-4 fw-black text-white mb-3 hero-title">
                    探索 <?= number_format($total_recipes) ?> 道免费菜谱
                </h1>
                <p class="lead text-white-60 mb-4">
                    社区共创 · 开源分享 · 精确到克的烹饪指南
                </p>
                <!-- Search bar -->
                <form method="get" action="<?= e(base_url('browse')) ?>" class="hero-search-form">
                    <div class="input-group input-group-lg shadow-lg">
                        <span class="input-group-text bg-white border-0">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                        </span>
                        <input type="text" name="q" class="form-control border-0"
                               placeholder="搜索菜名、食材、分类…"
                               maxlength="60" autofocus>
                        <button class="btn btn-primary px-4 fw-semibold" type="submit">搜索</button>
                    </div>
                </form>
                <div class="mt-3 d-flex justify-content-center gap-2">
                    <a href="<?= e(base_url('browse')) ?>" class="btn btn-outline-light btn-pill">
                        🍽️ 浏览全部菜谱
                    </a>
                    <a href="<?= e(base_url('browse') . '?random=1') ?>" class="btn btn-outline-light btn-pill">
                        🎲 随机一道菜
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ── Features ──────────────────────────────────────────────────────── -->
<section class="py-6 bg-light">
    <div class="container">
        <div class="text-center mb-5">
            <p class="text-primary text-uppercase fw-bold small mb-1">为什么选择我们</p>
            <h2 class="h1 fw-black">烹饪，就该这么简单。</h2>
        </div>
        <div class="row g-4">
            <?php
            $features = [
                ['🎯', '精确到克', '每道菜谱都有精确到克/毫升的用量，告别"适量"和"少许"的模糊描述。'],
                ['👥', '社区共创', '所有菜谱来自 HowToCook 开源项目，任何人都可以通过 GitHub 贡献和完善。'],
                ['💻', '完全开源', '菜谱内容和本站代码均开源，透明可审计，永久免费使用。'],
                ['🔍', '强力搜索', '按食材、菜名或分类快速找到今天想做的菜。'],
                ['📸', '图文并茂', '大量菜谱配有精美图片和分步操作图解。'],
                ['✏️', '在 GitHub 编辑', '发现错误？直接在 GitHub 上提 PR，帮助整个社区。'],
            ];
            foreach ($features as [$icon, $title, $desc]):
            ?>
            <div class="col-12 col-md-6 col-lg-4">
                <div class="feature-card h-100">
                    <div class="feature-icon"><?= $icon ?></div>
                    <h4 class="fw-bold mt-3 mb-2"><?= e($title) ?></h4>
                    <p class="text-muted mb-0"><?= e($desc) ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ── Featured Recipes ───────────────────────────────────────────────── -->
<?php if (count($recipes) > 0): ?>
<section class="py-6 border-top">
    <div class="container">
        <div class="text-center mb-4">
            <p class="text-primary text-uppercase fw-bold small mb-1">精选推荐</p>
            <h2 class="h1 fw-black">社区最爱菜谱</h2>
            <p class="text-muted">看看大家最喜欢做哪些菜。</p>
        </div>
        <div class="row g-3">
            <?php require __DIR__ . '/_recipe_cards.php'; ?>
        </div>
        <div class="text-center mt-4">
            <a href="<?= e(base_url('browse')) ?>" class="btn btn-primary btn-lg btn-pill px-5">
                🍽️ 浏览全部菜谱
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<?php
$content = ob_get_clean();
$page_title = '首页';
$no_sidebar = true;
require __DIR__ . '/layout.php';
