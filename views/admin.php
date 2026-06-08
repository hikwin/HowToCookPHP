<?php declare(strict_types=1);
// views/admin.php - Admin dashboard: sync trigger, stats, settings
require_admin();

$sync_log    = [];
$sync_done   = false;
$git_done    = false;
$error_msg   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'git_sync') {
        require_once __DIR__ . '/../git.php';
        $url = get_setting('repo_url', 'https://github.com/Anduin2017/HowToCook.git');
        try {
            git_sync($url);
            $sync_log[] = '✅ Git 拉取完成。';
            $git_done   = true;
        } catch (RuntimeException $e) {
            $error_msg = 'Git 同步失败：' . $e->getMessage();
        }
    } elseif ($action === 'index') {
        require_once __DIR__ . '/../sync.php';
        $stats = sync_recipes(function (string $m) use (&$sync_log) {
            $sync_log[] = $m;
        });
        sync_tips(function (string $m) use (&$sync_log) {
            $sync_log[] = $m;
        });
        $sync_done = true;
    } elseif ($action === 'settings') {
        $site_name   = trim($_POST['site_name'] ?? '');
        $repo_url    = trim($_POST['repo_url'] ?? '');
        $repo_backup = trim($_POST['repo_backup'] ?? '');
        if ($site_name) set_setting('site_name', $site_name);
        if ($repo_url)  set_setting('repo_url', $repo_url);
        if ($repo_backup) set_setting('repo_backup_url', $repo_backup);
        flash('success', '设置已保存');
        header('Location: ' . base_url('admin'));
        exit;
    }
}

// Stats
$total_recipes  = (int)db_scalar("SELECT COUNT(*) FROM recipes WHERE is_deleted=0");
$total_users    = (int)db_scalar("SELECT COUNT(*) FROM users");
$total_comments = (int)db_scalar("SELECT COUNT(*) FROM recipe_comments");
$total_likes    = (int)db_scalar("SELECT COUNT(*) FROM recipe_likes");
$total_images   = (int)db_scalar("SELECT COUNT(*) FROM recipe_images");

// Settings
$site_name   = get_setting('site_name', 'HowToCook');
$repo_url    = get_setting('repo_url', 'https://github.com/Anduin2017/HowToCook.git');
$repo_backup = get_setting('repo_backup_url', 'https://gitee.com/Anduin2017/HowToCook.git');



ob_start();
?>
<div class="container-fluid p-4">
    <h2 class="fw-bold mb-4">🛠️ 管理后台</h2>

    <!-- Stats -->
    <div class="row g-3 mb-5">
        <?php foreach ([
            ['🍽️', '菜谱总数', $total_recipes, 'primary'],
            ['👥', '注册用户', $total_users, 'success'],
            ['💬', '评论数', $total_comments, 'info'],
            ['👍', '点赞数', $total_likes, 'warning'],
            ['🖼️', '图片数', $total_images, 'secondary'],
        ] as [$icon, $label, $val, $color]): ?>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card border-0 shadow-sm text-center p-3">
                <div style="font-size:2rem;"><?= $icon ?></div>
                <div class="fw-bold fs-4 text-<?= $color ?>"><?= number_format($val) ?></div>
                <div class="text-muted small"><?= e($label) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-4">
        <!-- Sync controls -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body p-4">
                    <h5 class="fw-semibold mb-3">📥 数据同步</h5>
                    <p class="text-muted small mb-3">
                        先执行 "拉取仓库" 获取最新菜谱，再执行 "重新索引" 更新数据库。<br>
                        <strong>注意：索引可能耗时数分钟，请耐心等待。</strong>
                    </p>

                    <?php if ($error_msg): ?>
                    <div class="alert alert-danger"><?= e($error_msg) ?></div>
                    <?php endif; ?>

                    <?php if ($sync_log): ?>
                    <div class="bg-dark text-light rounded-3 p-3 mb-3" style="font-family:monospace;font-size:0.8rem;max-height:300px;overflow-y:auto;">
                        <?php foreach ($sync_log as $line): ?>
                        <div><?= e($line) ?></div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <div class="d-flex gap-2 flex-wrap">
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="git_sync">
                            <button type="submit" class="btn btn-outline-primary">
                                🔄 拉取 Git 仓库
                            </button>
                        </form>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="index">
                            <button type="submit" class="btn btn-primary"
                                    onclick="return confirm('确定开始索引？这可能需要数分钟。')">
                                📂 重新索引菜谱
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Support / GitHub Star Card -->
            <div class="card shadow-sm border-0 rounded-4 mt-4">
                <div class="card-body p-4">
                    <h5 class="fw-semibold mb-2">⭐ 支持本项目</h5>
                    <p class="text-muted small mb-3">
                        如果你觉得本项目对你有帮助，请给个 Star 支持一下！你的支持是作者持续维护的最大动力 💖
                    </p>
                    <a href="https://github.com/hikwin/HowToCookPHP" target="_blank" class="btn btn-outline-primary fw-semibold d-inline-flex align-items-center gap-2">
                        <span>⭐ 前往 GitHub 点个 Star</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Settings -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body p-4">
                    <h5 class="fw-semibold mb-3">⚙️ 全局设置</h5>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="settings">
                        <div class="mb-3">
                            <label class="form-label">站点名称</label>
                            <input type="text" name="site_name" class="form-control"
                                   value="<?= e($site_name) ?>" maxlength="50">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">菜谱仓库 URL（主）</label>
                            <input type="url" name="repo_url" class="form-control"
                                   value="<?= e($repo_url) ?>" maxlength="300"
                                   placeholder="https://github.com/Anduin2017/HowToCook.git">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">菜谱仓库 URL（备用）</label>
                            <input type="url" name="repo_backup" class="form-control"
                                   value="<?= e($repo_backup) ?>" maxlength="300"
                                   placeholder="https://gitee.com/Anduin2017/HowToCook.git">
                        </div>
                        <button type="submit" class="btn btn-success">保存设置</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

</div>
<?php
$content    = ob_get_clean();
$page_title = '管理后台';
$no_sidebar = true;
require __DIR__ . '/layout.php';
