<?php declare(strict_types=1);
// views/favorites.php
require_login();
$user    = current_user();
$user_id = (int)$user['id'];

$favorites = db_rows(
    "SELECT r.*, ri.logical_path as cover_path,
        (SELECT COUNT(*) FROM recipe_likes rl WHERE rl.recipe_id=r.id) as like_count
     FROM recipe_favorites rf
     JOIN recipes r ON r.id=rf.recipe_id AND r.is_deleted=0
     LEFT JOIN recipe_images ri ON ri.recipe_id=r.id AND ri.is_cover=1
     WHERE rf.user_id=?
     ORDER BY rf.created_at DESC",
    [$user_id]
);

$like_counts = [];
$recipes     = $favorites;
foreach ($favorites as $r) { $like_counts[$r['id']] = $r['like_count']; }

ob_start();
?>
<div class="p-4">
    <h2 class="fw-bold mb-4">❤️ 我的收藏</h2>
    <?php if (empty($favorites)): ?>
    <div class="text-center py-5">
        <div style="font-size:4rem;">🍽️</div>
        <h4 class="text-muted mt-3">还没有收藏任何菜谱</h4>
        <a href="<?= e(base_url('browse')) ?>" class="btn btn-primary mt-2">去浏览菜谱</a>
    </div>
    <?php else: ?>
    <div class="row g-3">
        <?php require __DIR__ . '/_recipe_cards.php'; ?>
    </div>
    <?php endif; ?>
</div>
<?php
$content    = ob_get_clean();
$page_title = '我的收藏';
require __DIR__ . '/layout.php';
