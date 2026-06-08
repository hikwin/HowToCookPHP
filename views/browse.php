<?php
/**
 * views/browse.php - Recipe browser: search, category & difficulty filters, infinite scroll.
 */
declare(strict_types=1);

const PAGE_SIZE = 24;

// ── Input validation ─────────────────────────────────────────────────────────
$q          = trim($_GET['q'] ?? '');
$category   = $_GET['category'] ?? '';
$difficulty = isset($_GET['difficulty']) ? (int)$_GET['difficulty'] : 0;
$sort_by    = $_GET['sort'] ?? '';
$page       = current_page();

// Whitelist category against known values
$valid_categories = array_keys(CATEGORY_MAP);
if ($category && !in_array($category, $valid_categories, true)) $category = '';

// Clamp difficulty
$difficulty = max(0, min(5, $difficulty));

// Handle random redirect
if (isset($_GET['random'])) {
    $where  = 'is_deleted=0';
    $params = [];
    if ($category) { $where .= ' AND category=?'; $params[] = $category; }
    if ($difficulty) { $where .= ' AND difficulty=?'; $params[] = $difficulty; }
    $ids = db_rows("SELECT id FROM recipes WHERE $where", $params);
    if ($ids) {
        $random_id = $ids[array_rand($ids)]['id'];
        header('Location: ' . base_url('recipe/' . $random_id));
        exit;
    }
}

// ── Query ─────────────────────────────────────────────────────────────────────
$where  = ['r.is_deleted=0'];
$params = [];

if ($q !== '') {
    // Replace full-width commas and spaces with standard counterparts
    $normalized = str_replace(['，', '　'], [',', ' '], $q);
    // Convert commas to spaces
    $normalized = str_replace(',', ' ', $normalized);
    // Collapse multiple whitespaces into a single space
    $normalized = preg_replace('/\s+/', ' ', $normalized);
    $normalized = trim($normalized);

    if ($normalized !== '') {
        $words = explode(' ', $normalized);
        foreach ($words as $word) {
            if ($word !== '') {
                $where[]  = "(r.name LIKE ? OR r.description LIKE ? OR r.ingredients LIKE ?)";
                $like     = '%' . $word . '%';
                $params[] = $like;
                $params[] = $like;
                $params[] = $like;
            }
        }
    }
}
if ($category !== '') {
    $where[]  = 'r.category=?';
    $params[] = $category;
}
if ($difficulty > 0) {
    $where[]  = 'r.difficulty=?';
    $params[] = $difficulty;
}

$where_sql = implode(' AND ', $where);

if ($sort_by === 'likes_desc') {
    $order_sql = '(SELECT COUNT(*) FROM recipe_likes rl WHERE rl.recipe_id=r.id) DESC';
} elseif ($sort_by === 'likes_asc') {
    $order_sql = '(SELECT COUNT(*) FROM recipe_likes rl WHERE rl.recipe_id=r.id) ASC';
} elseif ($sort_by === 'difficulty_desc') {
    $order_sql = 'r.difficulty DESC';
} elseif ($sort_by === 'difficulty_asc') {
    $order_sql = 'r.difficulty ASC';
} elseif ($sort_by === 'calories_desc') {
    $order_sql = 'r.calories DESC';
} elseif ($sort_by === 'calories_asc') {
    $order_sql = 'r.calories ASC';
} else {
    $order_sql = '(SELECT COUNT(*) FROM recipe_likes rl WHERE rl.recipe_id=r.id) DESC, r.name ASC';
}

$total = (int)db_scalar(
    "SELECT COUNT(*) FROM recipes r WHERE $where_sql",
    $params
);

$offset  = ($page - 1) * PAGE_SIZE;
$recipes_raw = db_rows(
    "SELECT r.*, ri.logical_path as cover_path,
        (SELECT COUNT(*) FROM recipe_likes rl WHERE rl.recipe_id=r.id) as like_count
     FROM recipes r
     LEFT JOIN recipe_images ri ON ri.recipe_id=r.id AND ri.is_cover=1
     WHERE $where_sql
     ORDER BY $order_sql
     LIMIT ? OFFSET ?",
    [...$params, PAGE_SIZE, $offset]
);

$recipes     = $recipes_raw;
$like_counts = [];
foreach ($recipes_raw as $r) { $like_counts[$r['id']] = $r['like_count']; }

$total_pages = (int)ceil($total / PAGE_SIZE);
$has_more    = $total > $page * PAGE_SIZE;

// AJAX partial return
if (isset($_GET['partial'])) {
    header('X-Has-More: ' . ($has_more ? 'true' : 'false'));
    foreach ($recipes as $recipe) {
        $like_counts = [$recipe['id'] => $recipe['like_count']];
        require __DIR__ . '/_recipe_cards.php';
    }
    exit;
}

// ── Category display name ─────────────────────────────────────────────────────
if ($category)        $display = category_icon($category) . ' ' . category_name($category);
elseif ($difficulty)  $display = str_repeat('★', $difficulty) . ' 难度';
elseif ($sort_by === 'likes_desc') $display = '❤️ 最多点赞';
elseif ($sort_by === 'difficulty_desc') $display = '★ 最高难度';
elseif ($sort_by === 'calories_desc') $display = '🔥 最高卡路里';
elseif ($q !== '')    $display = '🔍 "' . mb_substr($q, 0, 30) . '"';
else                  $display = '🍽️ 全部菜谱';

// ── HTML ──────────────────────────────────────────────────────────────────────
ob_start();
?>
<!-- ── Browse Header ──────────────────────────────────────────────────── -->
<div class="browse-header p-3">
    <!-- Filter bar -->
    <form method="get" action="<?= e(base_url('browse')) ?>" class="filter-bar">
        <input type="hidden" name="category" value="<?= e($category) ?>">
        <input type="hidden" name="difficulty" value="<?= e((string)$difficulty) ?>">
        <input type="hidden" name="q" value="<?= e($q) ?>">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex gap-2 align-items-center">
                <input type="hidden" name="sort" id="sort-field-input" value="<?= e($sort_by) ?>">
                <button type="button" class="btn btn-sm <?= $sort_by === '' ? 'btn-primary' : 'btn-outline-secondary' ?>" id="btn-sort-default">
                    默认排序
                </button>
                <button type="button" class="btn btn-sm <?= str_starts_with($sort_by, 'likes_') ? 'btn-primary' : 'btn-outline-secondary' ?>" id="btn-sort-likes">
                    按点赞<?= $sort_by === 'likes_desc' ? ' ↓' : ($sort_by === 'likes_asc' ? ' ↑' : '') ?>
                </button>
                <button type="button" class="btn btn-sm <?= str_starts_with($sort_by, 'difficulty_') ? 'btn-primary' : 'btn-outline-secondary' ?>" id="btn-sort-difficulty">
                    按难度<?= $sort_by === 'difficulty_desc' ? ' ↓' : ($sort_by === 'difficulty_asc' ? ' ↑' : '') ?>
                </button>
                <button type="button" class="btn btn-sm <?= str_starts_with($sort_by, 'calories_') ? 'btn-primary' : 'btn-outline-secondary' ?>" id="btn-sort-calories">
                    按卡路里<?= $sort_by === 'calories_desc' ? ' ↓' : ($sort_by === 'calories_asc' ? ' ↑' : '') ?>
                </button>
                <?php if ($q || $category || $difficulty || $sort_by): ?>
                <a href="<?= e(base_url('browse')) ?>" class="btn btn-outline-secondary btn-sm">✕ 清除</a>
                <?php endif; ?>
            </div>
            
            <div class="d-flex gap-2 flex-wrap">
                <?php if ($category || $difficulty): ?>
                <a href="<?= e(base_url('browse') . '?random=1' . ($category ? '&category=' . urlencode($category) : '') . ($difficulty ? '&difficulty=' . $difficulty : '')) ?>"
                   class="btn btn-outline-secondary btn-sm">
                    🎲 随机一道
                </a>
                <?php endif; ?>
                <a href="https://github.com/Anduin2017/HowToCook/fork" target="_blank" rel="noopener"
                   class="btn btn-outline-success btn-sm">
                    ➕ 贡献菜谱
                </a>
            </div>
        </div>
    </form>
</div>

<!-- ── Recipe Grid ────────────────────────────────────────────────────── -->
<?php if (empty($recipes)): ?>
<div class="text-center py-5">
    <div style="font-size:4rem;">🍽️</div>
    <h4 class="text-muted mt-3">未找到菜谱</h4>
    <p class="text-muted">试试其他搜索词，或者
        <a href="<?= e(base_url('browse')) ?>">浏览全部菜谱</a>。
    </p>
</div>
<?php else: ?>

<div class="p-3">
    <div class="row g-3" id="recipe-grid">
        <?php require __DIR__ . '/_recipe_cards.php'; ?>
    </div>

    <?php if ($has_more): ?>
    <div id="load-sentinel" class="text-center py-4"
         data-page="<?= $page ?>"
         data-q="<?= e($q) ?>"
         data-category="<?= e($category) ?>"
         data-difficulty="<?= e((string)$difficulty) ?>"
         data-sort="<?= e($sort_by) ?>">
        <div class="spinner-border text-secondary" role="status" id="load-spinner" style="display:none;">
            <span class="visually-hidden">加载中…</span>
        </div>
    </div>
    <?php endif; ?>

    <!-- Pagination (for no-JS fallback) -->
    <?php if ($total_pages > 1): ?>
    <nav class="mt-3 d-none" aria-label="分页导航" id="pagination-nav">
        <ul class="pagination justify-content-center flex-wrap">
            <?php for ($p = 1; $p <= min($total_pages, 10); $p++): ?>
            <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                <a class="page-link" href="?<?= http_build_query(array_filter(array_merge($_GET, ['page' => $p]), function($v) { return $v !== ''; })) ?>">
                    <?= $p ?>
                </a>
            </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
$page_title = $display;
$scripts = <<<HTML
<script>
(function () {
    const form = document.querySelector('.filter-bar');
    if (!form) return;
    const sortInput = document.getElementById('sort-field-input');
    const btnDefault = document.getElementById('btn-sort-default');
    const btnLikes = document.getElementById('btn-sort-likes');
    const btnDifficulty = document.getElementById('btn-sort-difficulty');
    const btnCalories = document.getElementById('btn-sort-calories');
    
    if (btnDefault) {
        btnDefault.addEventListener('click', function() {
            sortInput.value = '';
            form.submit();
        });
    }
    if (btnLikes) {
        btnLikes.addEventListener('click', function() {
            const current = sortInput.value;
            if (current === 'likes_desc') {
                sortInput.value = 'likes_asc';
            } else {
                sortInput.value = 'likes_desc';
            }
            form.submit();
        });
    }
    if (btnDifficulty) {
        btnDifficulty.addEventListener('click', function() {
            const current = sortInput.value;
            if (current === 'difficulty_desc') {
                sortInput.value = 'difficulty_asc';
            } else {
                sortInput.value = 'difficulty_desc';
            }
            form.submit();
        });
    }
    if (btnCalories) {
        btnCalories.addEventListener('click', function() {
            const current = sortInput.value;
            if (current === 'calories_desc') {
                sortInput.value = 'calories_asc';
            } else {
                sortInput.value = 'calories_desc';
            }
            form.submit();
        });
    }
})();

(function () {
    const sentinel = document.getElementById('load-sentinel');
    if (!sentinel) return;

    let loading = false;
    let nextPage = parseInt(sentinel.dataset.page) + 1;
    const grid   = document.getElementById('recipe-grid');
    const spinner = document.getElementById('load-spinner');

    function buildUrl(page) {
        const p = new URLSearchParams({
            page,
            partial: '1',
            q:          sentinel.dataset.q || '',
            category:   sentinel.dataset.category || '',
            difficulty: sentinel.dataset.difficulty || '',
            sort:       sentinel.dataset.sort || '',
        });
        // Remove empties
        [...p.entries()].forEach(([k,v]) => { if (!v) p.delete(k); });
        return '?' + p.toString();
    }

    async function loadMore() {
        if (loading) return;
        loading = true;
        spinner.style.display = '';
        try {
            const resp = await fetch(buildUrl(nextPage));
            if (!resp.ok) { sentinel.remove(); return; }
            const html = await resp.text();
            const hasMore = resp.headers.get('X-Has-More') === 'true';
            nextPage++;

            const tmp = document.createElement('div');
            tmp.innerHTML = html;
            while (tmp.firstChild) grid.appendChild(tmp.firstChild);

            if (typeof lucide !== 'undefined') lucide.createIcons();
            if (!hasMore) sentinel.remove();
        } finally {
            loading = false;
            spinner.style.display = 'none';
        }
    }

    new IntersectionObserver(
        entries => { if (entries[0].isIntersecting) loadMore(); },
        { rootMargin: '200px' }
    ).observe(sentinel);
})();
</script>
HTML;
require __DIR__ . '/layout.php';
