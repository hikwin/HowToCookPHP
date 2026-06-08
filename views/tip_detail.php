<?php
/**
 * views/tip_detail.php - Detail page for a specific cooking tip.
 */
declare(strict_types=1);

require_once BASE_DIR . '/parser.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$tip = db_row("SELECT * FROM tips WHERE id = ? AND is_deleted = 0", [$id]);

if (!$tip) {
    http_response_code(404);
    ob_start();
    ?>
    <div class="container text-center py-5">
        <div style="font-size:5rem;">💡</div>
        <h1 class="display-4 fw-bold mt-3">404</h1>
        <p class="text-muted fs-5">教程不存在</p>
        <a href="<?= e(base_url('tips')) ?>" class="btn btn-primary btn-lg mt-2">返回教程列表</a>
    </div>
    <?php
    $content    = ob_get_clean();
    $page_title = '教程未找到';
    $no_sidebar = true;
    require BASE_DIR . '/views/layout.php';
    exit;
}

$rendered_html = markdown_to_html($tip['content']);
$github_edit_url = "https://github.com/Anduin2017/HowToCook/blob/master/" . $tip['file_path'];

$cat_labels = [
    'root'     => '基础入门',
    'learn'    => '新手学厨',
    'advanced' => '进阶技巧'
];
$display_category = $cat_labels[$tip['category']] ?? $tip['category'];

$page_title = $tip['title'];
$no_sidebar = true;

ob_start();
?>
<div class="container py-5">
    <div class="row">
        <div class="col-12 col-lg-8 offset-lg-2">
            <!-- Back & Edit Action Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <a href="<?= e(base_url('tips')) ?>" class="btn btn-outline-secondary btn-sm rounded-pill px-3 d-flex align-items-center gap-1">
                    <i data-lucide="arrow-left" style="width:14px;height:14px;"></i>
                    <span>返回技巧列表</span>
                </a>
                <a href="<?= e($github_edit_url) ?>" target="_blank" rel="noopener" class="btn btn-outline-success btn-sm rounded-pill px-3 d-flex align-items-center gap-1">
                    <i data-lucide="edit-3" style="width:14px;height:14px;"></i>
                    <span>在 GitHub 上编辑</span>
                </a>
            </div>

            <!-- Content Card -->
            <div class="card shadow-sm border-0 rounded-4" style="background: #ffffff;">
                <div class="card-body p-4 p-md-5">
                    <div class="mb-4 pb-3 border-bottom border-light">
                        <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-1.5 mb-2 font-size-8 fw-semibold">
                            <?= e($display_category) ?>
                        </span>
                        <h1 class="fw-bold text-dark mt-1 mb-2"><?= e($tip['title']) ?></h1>
                        <div class="text-muted small">
                            更新时间：<?= e($tip['file_last_modified'] ?: '未知') ?>
                        </div>
                    </div>

                    <!-- Rendered Markdown HTML -->
                    <div class="recipe-markdown" style="font-size: 1.02rem; line-height: 1.75;">
                        <?= $rendered_html ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
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
