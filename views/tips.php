<?php
/**
 * views/tips.php - Cooking Tips index page.
 */
declare(strict_types=1);

$all_tips = db_rows("SELECT id, title, category FROM tips WHERE is_deleted=0 ORDER BY id ASC");

$grouped_tips = [
    'root'     => [],
    'learn'    => [],
    'advanced' => []
];

foreach ($all_tips as $t) {
    if (isset($grouped_tips[$t['category']])) {
        $grouped_tips[$t['category']][] = $t;
    } else {
        $grouped_tips['root'][] = $t;
    }
}

$cat_names = [
    'root'     => '💡 基础入门 / Kitchen Prep',
    'learn'    => '📖 新手技巧 / Learn to Cook',
    'advanced' => '🎓 进阶技巧 / Advanced Cooking'
];

$cat_icons = [
    'root'     => 'sparkles',
    'learn'    => 'book-open',
    'advanced' => 'graduation-cap'
];

$page_title = '烹饪技巧';
$no_sidebar = true;

ob_start();
?>
<div class="container py-5">
    <div class="text-center mb-5">
        <h1 class="display-5 fw-bold mb-2 text-primary d-flex align-items-center justify-content-center gap-3">
            🍳 <span>烹饪技巧 / Cooking Tips</span>
        </h1>
        <p class="text-muted fs-5">掌握烹饪基本功，让下厨变得轻松有趣</p>
    </div>

    <div class="row g-4 justify-content-center">
        <?php foreach (['root', 'learn', 'advanced'] as $cat): ?>
        <?php if (empty($grouped_tips[$cat])) continue; ?>
        <div class="col-12 col-md-6 col-lg-4">
            <div class="card shadow-sm border-0 h-100 rounded-4 transition-all hover-translate-y" style="background: #ffffff;">
                <div class="card-body p-4 d-flex flex-column h-100">
                    <div class="d-flex align-items-center gap-3 mb-4 pb-2 border-bottom border-light">
                        <div class="bg-primary-light text-primary rounded-3 p-2 d-flex align-items-center justify-content-center">
                            <i data-lucide="<?= $cat_icons[$cat] ?>" style="width: 24px; height: 24px; stroke-width: 2.5;"></i>
                        </div>
                        <h5 class="card-title fw-bold mb-0 text-dark" style="font-size: 1.1rem;"><?= e($cat_names[$cat]) ?></h5>
                    </div>

                    <div class="list-group list-group-flush flex-grow-1">
                        <?php foreach ($grouped_tips[$cat] as $tip): ?>
                        <a href="<?= e(base_url('tip/' . $tip['id'])) ?>" 
                           class="list-group-item list-group-item-action border-0 px-0 py-2.5 d-flex align-items-center justify-content-between text-muted hover-text-primary"
                           style="background: transparent; font-size: 0.92rem; transition: all 0.2s ease;">
                            <div class="d-flex align-items-center gap-2">
                                <i data-lucide="file-text" style="width:15px;height:15px;stroke-width:2;"></i>
                                <span class="fw-semibold text-truncate" style="max-width: 220px;"><?= e($tip['title']) ?></span>
                            </div>
                            <i data-lucide="chevron-right" class="arrow-icon" style="width:14px;height:14px;opacity:0.5;transition: transform 0.2s ease;"></i>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<style>
.hover-translate-y {
    transition: transform 0.25s ease, box-shadow 0.25s ease;
}
.hover-translate-y:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08) !important;
}
.hover-text-primary:hover {
    color: var(--primary) !important;
}
.hover-text-primary:hover .arrow-icon {
    transform: translateX(3px);
    opacity: 1 !important;
}
</style>
<?php
$content = ob_get_clean();
$scripts = <<<HTML
<script>
if (typeof lucide !== 'undefined') {
    lucide.createIcons();
}
</script>
HTML;

require BASE_DIR . '/views/layout.php';
