<?php
/**
 * views/detail.php - Full recipe detail page.
 * Features: calorie gauge, difficulty meter, comment section, likes & favorites.
 */
declare(strict_types=1);

require_once __DIR__ . '/../parser.php';

// ── Validate input ─────────────────────────────────────────────────────────
$recipe_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($recipe_id <= 0) {
    http_response_code(404);
    echo '<p>菜谱不存在。</p>';
    exit;
}

// ── Load recipe ────────────────────────────────────────────────────────────
$recipe = db_row(
    "SELECT r.* FROM recipes r WHERE r.id=? AND r.is_deleted=0",
    [$recipe_id]
);
if (!$recipe) {
    http_response_code(404);
    $content = '<div class="p-5 text-center"><h3>菜谱不存在</h3><a href="' . e(base_url('browse')) . '">返回浏览</a></div>';
    $page_title = '404';
    require __DIR__ . '/layout.php';
    exit;
}

$images = db_rows(
    "SELECT * FROM recipe_images WHERE recipe_id=? ORDER BY is_cover DESC",
    [$recipe_id]
);

$cover = null;
foreach ($images as $img) {
    if ($img['is_cover']) { $cover = $img; break; }
}

// ── User interaction ─────────────────────────────────────────────────────
$user      = current_user();
$user_id   = $user ? (int)$user['id'] : 0;
$is_liked  = $user_id && db_scalar(
    "SELECT 1 FROM recipe_likes WHERE user_id=? AND recipe_id=?", [$user_id, $recipe_id]
);
$is_fav    = $user_id && db_scalar(
    "SELECT 1 FROM recipe_favorites WHERE user_id=? AND recipe_id=?", [$user_id, $recipe_id]
);
$like_count = (int)db_scalar(
    "SELECT COUNT(*) FROM recipe_likes WHERE recipe_id=?", [$recipe_id]
);

// ── Comments ─────────────────────────────────────────────────────────────
$comments_raw = db_rows(
    "SELECT c.*, u.display_name, u.email
     FROM recipe_comments c
     JOIN users u ON u.id=c.user_id
     WHERE c.recipe_id=? AND c.parent_comment_id IS NULL
     ORDER BY c.created_at ASC",
    [$recipe_id]
);
// Attach replies
$comments = [];
foreach ($comments_raw as $c) {
    $c['replies'] = db_rows(
        "SELECT c2.*, u.display_name, u.email
         FROM recipe_comments c2
         JOIN users u ON u.id=c2.user_id
         WHERE c2.parent_comment_id=?
         ORDER BY c2.created_at ASC",
        [$c['id']]
    );
    $comments[] = $c;
}

// ── Render markdown ───────────────────────────────────────────────────────
$md   = build_recipe_markdown($recipe);
$html = markdown_to_html($md);

// Cover image prepended
if ($cover) {
    $cover_url = base_url($cover['logical_path']);
    $html = '<div class="recipe-cover-hero mb-4"><img src="' . e($cover_url) . '" alt="' . e($recipe['name']) . '" class="img-fluid rounded-3 shadow"></div>' . $html;
}

// ── Contributors ─────────────────────────────────────────────────────────
$repo_url     = get_setting('repo_url', 'https://github.com/Anduin2017/HowToCook.git');
$repo_web_url = (substr($repo_url, -4) === '.git') ? substr($repo_url, 0, -4) : $repo_url;
$file_path    = $recipe['file_path'];

require_once __DIR__ . '/../git.php';
$contributors = [];
try { $contributors = git_file_contributors($file_path); } catch (Throwable $e) {}

$github_edit_url    = $repo_web_url . '/edit/master/' . $file_path;
$github_history_url = $repo_web_url . '/commits/master/' . $file_path;

// ── Extra images ──────────────────────────────────────────────────────────
$extra_images = array_filter($images, function($i) { return !$i['is_cover']; });

// ── HTML ──────────────────────────────────────────────────────────────────
ob_start();
?>
<div class="container-fluid p-4">
    <!-- Breadcrumb + Actions -->
    <div class="row mb-3 align-items-center g-2">
        <div class="col">
            <nav>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?= e(base_url('browse')) ?>">全部菜谱</a></li>
                    <li class="breadcrumb-item">
                        <a href="<?= e(base_url('browse') . '?category=' . urlencode($recipe['category'])) ?>">
                            <?= e(category_icon($recipe['category']) . ' ' . category_name($recipe['category'])) ?>
                        </a>
                    </li>
                    <li class="breadcrumb-item active"><?= e($recipe['name']) ?></li>
                </ol>
            </nav>
        </div>
        <div class="col-auto d-flex gap-2 flex-wrap">
            <a href="<?= e($github_edit_url) ?>" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm">
                ✏️ 在 GitHub 上编辑
            </a>
            <?php if ($user): ?>
            <!-- Like -->
            <form method="post" action="<?= e(base_url('like/' . $recipe_id)) ?>" class="d-inline">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm <?= $is_liked ? 'btn-warning' : 'btn-outline-warning' ?>">
                    👍 <?= $is_liked ? '已点赞' : '点赞' ?>
                    <span class="badge ms-1 <?= $is_liked ? 'bg-white text-dark' : 'bg-warning text-dark' ?>"><?= $like_count ?></span>
                </button>
            </form>
            <!-- Favorite -->
            <form method="post" action="<?= e(base_url('favorite/' . $recipe_id)) ?>" class="d-inline">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm <?= $is_fav ? 'btn-danger' : 'btn-outline-danger' ?>">
                    ❤️ <?= $is_fav ? '已收藏' : '收藏' ?>
                </button>
            </form>
            <?php else: ?>
            <a href="<?= e(base_url('login')) ?>" class="btn btn-outline-warning btn-sm">
                👍 点赞 <span class="badge bg-warning text-dark ms-1"><?= $like_count ?></span>
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Main card -->
    <div class="row">
        <div class="col-12 col-lg-8 offset-lg-2">
            <div class="card shadow-sm recipe-detail-card">
                <div class="card-body p-4 p-md-5">

                    <!-- Info cards row (Difficulty & Calories) -->
                    <div class="row g-3 mb-4">
                        <!-- Difficulty card -->
                        <div class="<?= $recipe['calories'] ? 'col-12 col-md-6' : 'col-12' ?>">
                            <div class="p-3 bg-light rounded-4 shadow-sm border-start border-4 border-warning h-100 d-flex flex-column justify-content-between">
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                    <div>
                                        <div class="text-muted small text-uppercase fw-bold mb-1">难度 / Difficulty</div>
                                        <div class="d-flex align-items-baseline gap-2">
                                            <h4 class="mb-0 fw-bold"><?= (int)$recipe['difficulty'] ?></h4>
                                            <span class="text-muted small">/ 5</span>
                                        </div>
                                    </div>
                                    <span id="difficulty-label" class="badge rounded-pill px-3 py-1.5 fw-bold" style="font-size:0.85rem;"></span>
                                </div>
                                <div class="difficulty-meter mt-2" data-difficulty="<?= (int)$recipe['difficulty'] ?>">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <span class="difficulty-flame" data-index="<?= $i ?>">🔥</span>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Calorie card -->
                        <?php if ($recipe['calories']): ?>
                        <div class="col-12 col-md-6">
                            <div class="p-3 rounded-4 shadow-sm border-start border-4 thermometer-card h-100 d-flex flex-column justify-content-between"
                                 style="background: #ffffff; border-color: #e0e0e0; transition: border-color 0.5s ease;">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <div>
                                        <div class="text-muted small text-uppercase fw-bold mb-1">卡路里 / Calories</div>
                                        <h4 class="mb-0 fw-bold">
                                            <span class="calorie-display-val">0</span>
                                            <span class="text-muted fs-6 fw-normal">kcal</span>
                                        </h4>
                                    </div>
                                    <span id="thermometer-badge" class="badge rounded-pill px-3 py-1.5 fw-bold shadow-sm" style="font-size:0.85rem;"></span>
                                </div>

                                <!-- Thermometer Widget -->
                                <div class="thermometer-widget d-flex align-items-center mt-3" data-calories="<?= (float)$recipe['calories'] ?>">
                                    <div class="thermometer-bulb">
                                        <div class="thermometer-bulb-inner"></div>
                                    </div>
                                    <div class="thermometer-stem-wrapper flex-grow-1">
                                        <div class="thermometer-stem-track">
                                            <div class="thermometer-zone zone-green" style="width: 30%;" title="低热量区 (0-300 kcal)"></div>
                                            <div class="thermometer-zone zone-orange" style="width: 40%;" title="中热量区 (300-600 kcal)"></div>
                                            <div class="thermometer-zone zone-red" style="width: 30%;" title="高热量区 (600+ kcal)"></div>
                                            <div class="thermometer-fill-level"></div>
                                            <div class="thermometer-pointer">
                                                <div class="pointer-bubble">0 kcal</div>
                                                <div class="pointer-arrow"></div>
                                            </div>
                                        </div>
                                        <div class="thermometer-scale position-relative">
                                            <div class="scale-point" style="left: 0%;">
                                                <div class="scale-tick"></div>
                                                <div class="scale-label">0</div>
                                            </div>
                                            <div class="scale-point" style="left: 30%;">
                                                <div class="scale-tick"></div>
                                                <div class="scale-label">300</div>
                                            </div>
                                            <div class="scale-point" style="left: 70%;">
                                                <div class="scale-tick"></div>
                                                <div class="scale-label">600</div>
                                            </div>
                                            <div class="scale-point" style="left: 100%;">
                                                <div class="scale-tick"></div>
                                                <div class="scale-label">1000+</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Recipe markdown content -->
                    <div class="recipe-markdown">
                        <?= $html ?>
                    </div>

                    <!-- Extra images -->
                    <?php if ($extra_images): ?>
                    <h5 class="mt-4 mb-3">📷 更多图片</h5>
                    <div class="row g-3">
                        <?php foreach ($extra_images as $img): ?>
                        <div class="col-6 col-md-4">
                            <img src="<?= e(base_url($img['logical_path'])) ?>"
                                 alt="菜谱图片"
                                 class="img-fluid rounded-3 shadow-sm"
                                 loading="lazy">
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Notice -->
                <div class="px-4 px-md-5 pb-3">
                    <div class="alert alert-primary alert-dismissible">
                        <button class="btn-close" data-bs-dismiss="alert"></button>
                        <h6 class="alert-heading">社区内容</h6>
                        <p class="mb-0 small">
                            本菜谱版权归社区所有，依 The Unlicense 协议发布至公共领域，可自由使用。
                            <a href="<?= e($github_history_url) ?>" target="_blank" rel="noopener">查看修改历史</a>
                        </p>
                    </div>
                </div>

                <div class="card-footer text-muted small">
                    最后更新：<?= e($recipe['file_last_modified'] ?: '未知') ?>
                </div>
            </div>

            <!-- Contributors -->
            <?php if ($contributors): ?>
            <div class="mt-4">
                <h5 class="mb-3">👤 贡献者</h5>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($contributors as $c): ?>
                    <?php
                    $avatar   = gravatar_url($c['email'], 40);
                    $profile  = (substr($c['email'], -strlen('@users.noreply.github.com')) === '@users.noreply.github.com')
                        ? 'https://github.com/' . explode('@', explode('+', $c['email'])[count(explode('+', $c['email'])) - 1])[0]
                        : $repo_web_url . '/commits?author=' . urlencode($c['email']);
                    ?>
                    <a href="<?= e($profile) ?>" target="_blank" rel="noopener"
                       title="<?= e($c['name']) ?> (<?= (int)$c['count'] ?> 次提交)">
                        <img src="<?= e($avatar) ?>" alt="<?= e($c['name']) ?>"
                             class="rounded-circle border shadow-sm"
                             width="40" height="40" style="object-fit:cover;">
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Comments -->
            <div class="mt-4" id="comments">
                <h5 class="mb-3">💬 评论 (<?= count($comments) ?>)</h5>

                <?php if ($user): ?>
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="post" action="<?= e(base_url('comment/' . $recipe_id)) ?>">
                            <?= csrf_field() ?>
                            <div class="mb-2">
                                <textarea name="content" class="form-control" rows="3"
                                          placeholder="写下你的评论…" maxlength="1000" required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm">发布评论</button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

                <?php foreach ($comments as $comment): ?>
                <div class="card mb-3" id="comment-<?= (int)$comment['id'] ?>">
                    <div class="card-body">
                        <div class="d-flex gap-3">
                            <img src="<?= e(gravatar_url($comment['email'], 40)) ?>"
                                 alt="<?= e($comment['display_name']) ?>"
                                 class="rounded-circle flex-shrink-0"
                                 width="40" height="40">
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <div>
                                        <strong><?= e($comment['display_name']) ?></strong>
                                        <small class="text-muted ms-2"><?= e($comment['created_at']) ?></small>
                                    </div>
                                    <?php if ($user_id === (int)$comment['user_id']): ?>
                                    <form method="post" action="<?= e(base_url('comment/delete/' . $comment['id'])) ?>" class="d-inline">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-link btn-sm text-danger p-0"
                                                onclick="return confirm('确定删除此评论及所有回复吗？')">删除</button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                                <p class="mb-2"><?= e($comment['content']) ?></p>

                                <!-- Replies -->
                                <?php if ($comment['replies']): ?>
                                <div class="border-start ps-3 mt-2">
                                    <?php foreach ($comment['replies'] as $reply): ?>
                                    <div class="d-flex gap-2 mb-2 pt-2" id="comment-<?= (int)$reply['id'] ?>">
                                        <img src="<?= e(gravatar_url($reply['email'], 32)) ?>"
                                             alt="<?= e($reply['display_name']) ?>"
                                             class="rounded-circle flex-shrink-0"
                                             width="32" height="32">
                                        <div class="flex-grow-1">
                                            <strong><?= e($reply['display_name']) ?></strong>
                                            <small class="text-muted ms-1"><?= e($reply['created_at']) ?></small>
                                            <?php if ($user_id === (int)$reply['user_id']): ?>
                                            <form method="post" action="<?= e(base_url('comment/delete/' . $reply['id'])) ?>" class="d-inline ms-2">
                                                <?= csrf_field() ?>
                                                <button class="btn btn-link btn-sm text-danger p-0 small"
                                                        onclick="return confirm('确定删除此回复吗？')">删除</button>
                                            </form>
                                            <?php endif; ?>
                                            <p class="mb-0 mt-1"><?= e($reply['content']) ?></p>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>

                                <!-- Reply form -->
                                <?php if ($user): ?>
                                <div class="mt-2">
                                    <button class="btn btn-link btn-sm p-0 text-secondary toggle-reply"
                                            data-target="reply-form-<?= (int)$comment['id'] ?>">回复</button>
                                    <div id="reply-form-<?= (int)$comment['id'] ?>" class="d-none mt-2">
                                        <form method="post" action="<?= e(base_url('comment/' . $recipe_id)) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="parent_comment_id" value="<?= (int)$comment['id'] ?>">
                                            <textarea name="content" class="form-control form-control-sm" rows="2"
                                                      placeholder="回复 <?= e($comment['display_name']) ?>…"
                                                      maxlength="1000" required></textarea>
                                            <button type="submit" class="btn btn-secondary btn-sm mt-1">发送</button>
                                        </form>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php if (empty($comments)): ?>
                <p class="text-muted text-center py-3">暂无评论，来做第一个！</p>
                <?php endif; ?>

                <?php if (!$user): ?>
                <div class="text-center py-3">
                    <a href="<?= e(base_url('login')) ?>" class="btn btn-outline-primary">
                        🔐 登录后发表评论
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
$page_title = $recipe['name'];

$head_extra = '<style>
.recipe-detail-card { border-radius: 1rem; overflow: hidden; }
.recipe-markdown img { max-width:100%; height:auto; border-radius:8px; margin:1rem 0; }
.recipe-markdown h3 { font-size:1.3rem; font-weight:600; margin-top:2rem; margin-bottom:.75rem; border-bottom:1px solid #dee2e6; padding-bottom:.4rem; }
.recipe-markdown h4 { font-size:1.1rem; font-weight:600; margin-top:1.5rem; }
.recipe-markdown ul, .recipe-markdown ol { padding-left:1.5rem; }
.recipe-markdown li { margin-bottom:.25rem; }
.recipe-markdown p { line-height:1.8; }
.difficulty-meter { display:flex; gap:4px; align-items:center; }
.difficulty-flame { font-size:1.2rem; opacity:.25; transition:all 0.4s ease; }
.difficulty-flame.active { opacity:1; transform:scale(1.1); }
.recipe-cover-hero img { width:100%; max-height:400px; object-fit:cover; }
 
/* ── Calorie Thermometer CSS ── */
.thermometer-card {
    transition: all 0.3s ease;
}
.thermometer-bulb {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: #e0e0e0;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08), inset 0 -2px 5px rgba(0,0,0,0.1);
    border: 2px solid #fff;
    z-index: 2;
    position: relative;
    transition: background-color 0.5s ease;
}
.thermometer-bulb-inner {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background-color: #e0e0e0;
    transition: background-color 0.5s ease, box-shadow 0.5s ease;
}
.thermometer-stem-wrapper {
    margin-left: -8px;
    position: relative;
    z-index: 1;
}
.thermometer-stem-track {
    height: 14px;
    border-radius: 0 7px 7px 0;
    background: #f1f3f5;
    position: relative;
    display: flex;
    overflow: visible;
    box-shadow: inset 0 2px 4px rgba(0,0,0,0.06);
    border: 1.5px solid #fff;
}
.thermometer-zone {
    height: 100%;
}
.thermometer-zone.zone-green {
    background-color: rgba(32, 201, 151, 0.1);
    border-right: 1px dashed rgba(32, 201, 151, 0.3);
}
.thermometer-zone.zone-orange {
    background-color: rgba(245, 158, 11, 0.1);
    border-right: 1px dashed rgba(245, 158, 11, 0.3);
}
.thermometer-zone.zone-red {
    background-color: rgba(239, 68, 68, 0.1);
    border-radius: 0 5px 5px 0;
}
.thermometer-fill-level {
    position: absolute;
    left: 0;
    top: 0;
    height: 100%;
    width: 0%;
    border-radius: 0 5px 5px 0;
    transition: width 1.5s cubic-bezier(0.1, 0.8, 0.2, 1);
    z-index: 2;
}
.thermometer-pointer {
    position: absolute;
    left: 0%;
    top: -34px;
    transform: translateX(-50%);
    z-index: 10;
    display: flex;
    flex-direction: column;
    align-items: center;
    transition: left 1.5s cubic-bezier(0.1, 0.8, 0.2, 1);
}
.pointer-bubble {
    background: #212529;
    color: #fff;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 700;
    white-space: nowrap;
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    transition: background-color 0.5s ease;
}
.pointer-arrow {
    width: 0;
    height: 0;
    border-left: 5px solid transparent;
    border-right: 5px solid transparent;
    border-top: 5px solid #212529;
    margin-top: -1px;
    transition: border-top-color 0.5s ease;
}
.thermometer-scale {
    height: 20px;
    position: relative;
    margin-top: 4px;
}
.scale-point {
    position: absolute;
    transform: translateX(-50%);
    display: flex;
    flex-direction: column;
    align-items: center;
}
.scale-tick {
    width: 1.5px;
    height: 4px;
    background-color: #dee2e6;
    margin-bottom: 2px;
}
.scale-label {
    font-size: 0.65rem;
    font-weight: 600;
    color: #868e96;
    white-space: nowrap;
}
</style>';

$scripts = <<<HTML
<script>
// ── Reply toggle ─────────────────────────────────────────────────────────
document.querySelectorAll('.toggle-reply').forEach(btn => {
    btn.addEventListener('click', e => {
        e.preventDefault();
        const t = document.getElementById(btn.dataset.target);
        if (t) t.classList.toggle('d-none');
    });
});

// ── Difficulty meter ──────────────────────────────────────────────────────
(function() {
    const meter = document.querySelector('.difficulty-meter');
    if (!meter) return;
    const d = parseInt(meter.dataset.difficulty) || 0;
    const flames = meter.querySelectorAll('.difficulty-flame');
    const label  = document.getElementById('difficulty-label');

    const colors = ['#20c997','#20c997','#ffc107','#fd7e14','#dc3545','#dc3545'];
    const levels = ['初学','简单','中等','进阶','专家'];
    const color  = colors[Math.min(d, 5)];

    label.textContent = levels[Math.max(0, Math.min(d-1, 4))] || '未知';
    label.style.backgroundColor = color;
    label.style.color = d <= 2 ? '#212529' : '#fff';

    flames.forEach((flame, i) => {
        if (i < d) {
            setTimeout(() => { flame.classList.add('active'); }, i * 100);
        }
    });
})();

// ── Calorie Thermometer ───────────────────────────────────────────────────
(function() {
    const widget = document.querySelector('.thermometer-widget');
    if (!widget) return;
    const cal = parseFloat(widget.dataset.calories) || 0;
    
    const card = document.querySelector('.thermometer-card');
    const bulbInner = widget.querySelector('.thermometer-bulb-inner');
    const fill = widget.querySelector('.thermometer-fill-level');
    const pointer = widget.querySelector('.thermometer-pointer');
    const bubble = widget.querySelector('.pointer-bubble');
    const arrow = widget.querySelector('.pointer-arrow');
    const displayVal = document.querySelector('.calorie-display-val');
    const badge = document.getElementById('thermometer-badge');

    // Color levels based on user choice (0-300, 300-600, 600+)
    const getLevel = c => {
        if (c <= 300) {
            return {
                color: '#10B981', // Soothing Teal-Green for Low Calorie
                gradient: 'linear-gradient(90deg, #34D399 0%, #10B981 100%)',
                glow: '0 0 12px rgba(16, 185, 129, 0.6)',
                label: '低热量 (0-300 kcal)'
            };
        } else if (c <= 600) {
            return {
                color: '#F59E0B', // Bright Orange for Moderate Calorie
                gradient: 'linear-gradient(90deg, #FBBF24 0%, #F59E0B 100%)',
                glow: '0 0 12px rgba(245, 158, 11, 0.6)',
                label: '中热量 (300-600 kcal)'
            };
        } else {
            return {
                color: '#EF4444', // High Alert Red for High Calorie Warning
                gradient: 'linear-gradient(90deg, #F87171 0%, #EF4444 100%)',
                glow: '0 0 12px rgba(239, 68, 68, 0.7)',
                label: '高热量 (600+ kcal)'
            };
        }
    };
    
    const level = getLevel(cal);

    // Calculate non-linear scale positioning for precise visualization
    let pct = 0;
    if (cal <= 300) {
        pct = (cal / 300) * 30; // 0-300 kcal maps to 0-30% of the bar
    } else if (cal <= 600) {
        pct = 30 + ((cal - 300) / 300) * 40; // 300-600 kcal maps to 30-70% of the bar
    } else {
        pct = 70 + ((cal - 600) / 400) * 30; // 600-1000 kcal maps to 70-100% of the bar
    }
    pct = Math.min(Math.max(pct, 0), 100);

    // Apply color styles and transitions
    setTimeout(() => {
        fill.style.width = pct + '%';
        fill.style.background = level.gradient;
        
        pointer.style.left = pct + '%';
        
        bulbInner.style.backgroundColor = level.color;
        bulbInner.style.boxShadow = level.glow;
        
        bubble.style.backgroundColor = level.color;
        arrow.style.borderTopColor = level.color;
        
        if (card) {
            card.style.borderLeftColor = level.color;
        }
    }, 150);

    // Update text badge
    badge.textContent = level.label;
    badge.style.backgroundColor = level.color;
    badge.style.color = '#fff';

    // Animate calorie numbers
    const dur = 1500;
    const start = performance.now();
    (function tick(now) {
        const prog = Math.min((now - start) / dur, 1);
        const ease = 1 - Math.pow(1 - prog, 3); // easeOutCubic
        const currentVal = Math.round(cal * ease);
        
        displayVal.textContent = currentVal.toLocaleString();
        bubble.textContent = currentVal + ' kcal';
        
        if (prog < 1) requestAnimationFrame(tick);
    })(performance.now());
})();
</script>
HTML;

require __DIR__ . '/layout.php';
