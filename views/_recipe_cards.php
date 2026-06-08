<?php
/**
 * views/_recipe_cards.php - Reusable recipe card grid partial.
 *
 * Expected variables:
 *   $recipes      array   Array of recipe rows with images_cover
 *   $like_counts  array   [recipe_id => count]
 *   $json_only    bool    If true, outputs raw JSON for AJAX pagination
 */
declare(strict_types=1);
?>
<?php foreach ($recipes as $recipe): ?>
<?php
$cover_url = '';
if (!empty($recipe['cover_path'])) {
    $cover_url = e(base_url($recipe['cover_path'] . '?w=400'));
}
$stars = difficulty_stars((int)$recipe['difficulty']);
$likes = $like_counts[$recipe['id']] ?? 0;
?>
<div class="col-12 col-sm-6 col-md-4 col-xl-3">
    <a href="<?= e(base_url('recipe/' . $recipe['id'])) ?>" class="text-decoration-none">
        <div class="card h-100 shadow-sm recipe-card">
            <?php if ($cover_url): ?>
            <img src="<?= $cover_url ?>"
                 class="card-img-top recipe-cover"
                 alt="<?= e($recipe['name']) ?>"
                 loading="lazy"
                 onerror="this.style.display='none'; this.parentElement.querySelector('.cover-fallback').classList.remove('d-none');">
            <?php endif; ?>
            <div class="cover-fallback <?= $cover_url ? 'd-none' : '' ?>">
                <span class="fallback-icon"><?= category_icon($recipe['category']) ?></span>
            </div>
            <div class="card-body pb-2">
                <h6 class="card-title mb-1 fw-bold text-truncate"><?= e($recipe['name']) ?></h6>
                <div class="d-flex align-items-center justify-content-between">
                    <small class="text-warning"><?= e($stars) ?></small>
                </div>
                <?php if ($recipe['calories']): ?>
                <div class="mt-1">
                    <small class="text-muted">
                        <i data-lucide="flame" style="width:13px;height:13px;"></i>
                        <?= (int)$recipe['calories'] ?> kcal
                    </small>
                </div>
                <?php endif; ?>
                <?php if (!empty($recipe['description'])): ?>
                <p class="card-text text-muted small mt-1 mb-0 description-clamp">
                    <?= e(mb_substr($recipe['description'], 0, 80)) ?>
                </p>
                <?php endif; ?>
            </div>
            <div class="card-footer py-1 px-3 d-flex align-items-center justify-content-between">
                <span class="badge bg-secondary-subtle text-secondary-emphasis small">
                    <?= e(category_name($recipe['category'])) ?>
                </span>
                <span class="text-muted d-flex align-items-center gap-1" style="font-size: 0.72rem;">
                    <i data-lucide="thumbs-up" style="width:11px;height:11px;stroke-width:2.5;"></i>
                    <?= (int)$likes ?>
                </span>
            </div>
        </div>
    </a>
</div>
<?php endforeach; ?>
