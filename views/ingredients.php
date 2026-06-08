<?php
/**
 * views/ingredients.php - Ingredients Reverse Lookup page.
 * Allows users to click ingredient chips on the left to filter recipes on the right.
 */
declare(strict_types=1);

// ── 1. Fetch all active recipes to parse ingredients ──────────────────────────
$all_recipes = db_rows("SELECT id, name, ingredients, category FROM recipes WHERE is_deleted=0");

$ingredient_counts = []; // name => occurrence count

foreach ($all_recipes as $r) {
    $lines = explode("\n", $r['ingredients']);
    $recipe_words = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;
        
        // Detect bulleted lists
        if (preg_match('/^[\-\*\+]\s+(.+)$/u', $line, $matches)) {
            $item = trim($matches[1]);
            // Strip markdown bold / italic formatting
            $item = str_replace(['**', '*'], '', $item);
            // Split by colon (Chinese/English) or whitespace
            $parts = preg_split('/[:：\s]/u', $item, 2);
            $name = trim($parts[0]);
            
            // Clean up notes in parentheses like "盐 (适量)" or "鸡蛋 (2个)"
            $name = preg_replace('/[\(（].*$/u', '', $name);
            $name = trim($name);
            
            // Apply sane constraints (short names, no numeric values)
            if ($name !== '' && mb_strlen($name) < 15 && !preg_match('/^[0-9]/', $name)) {
                $recipe_words[] = $name;
            }
        }
    }
    
    // De-duplicate ingredients per recipe
    $recipe_words = array_unique($recipe_words);
    foreach ($recipe_words as $w) {
        if (!isset($ingredient_counts[$w])) {
            $ingredient_counts[$w] = 0;
        }
        $ingredient_counts[$w]++;
    }
}

// Sort by occurrence count descending
arsort($ingredient_counts);

// ── 2. Query initial recipe list (all recipes, sorted by likes) ───────────────
$initial_recipes = db_rows(
    "SELECT r.*, ri.logical_path as cover_path,
        (SELECT COUNT(*) FROM recipe_likes rl WHERE rl.recipe_id=r.id) as like_count
     FROM recipes r
     LEFT JOIN recipe_images ri ON ri.recipe_id=r.id AND ri.is_cover=1
     WHERE r.is_deleted=0
     ORDER BY (SELECT COUNT(*) FROM recipe_likes rl WHERE rl.recipe_id=r.id) DESC, r.name ASC"
);

$recipes = $initial_recipes;
$like_counts = [];
foreach ($recipes as $r) {
    $like_counts[$r['id']] = $r['like_count'];
}

// Renders inside layout.php with no default sidebar
$no_sidebar = true;
$page_title = '菜谱反查';

ob_start();
?>
<div class="container-fluid py-4 px-md-5">
    <div class="row g-4">
        <!-- ── Left Column: Ingredients Selector ── -->
        <div class="col-12 col-md-4 col-lg-3">
            <div class="card shadow-sm border-0 sticky-top" style="top: calc(var(--navbar-h) + 1.5rem); max-height: calc(100vh - var(--navbar-h) - 3rem); overflow: hidden; display: flex; flex-direction: column; border-radius: var(--radius-lg);">
                <div class="card-header bg-white border-0 pt-4 pb-2 px-4 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold d-flex align-items-center gap-2" style="color: var(--primary);">
                        <i data-lucide="cherry" style="width:20px;height:20px;stroke-width:2.5;"></i>
                        <span>食材库 (<span id="total-ingredients-count"><?= count($ingredient_counts) ?></span>)</span>
                    </h5>
                    <button id="clear-ingredients-btn" class="btn btn-outline-secondary btn-sm rounded-pill px-3" style="display:none; font-size: 0.8rem;">
                        清除
                    </button>
                </div>
                
                <div class="px-4 pb-3">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0 text-muted">
                            <i data-lucide="search" style="width:16px;height:16px;"></i>
                        </span>
                        <input type="search" id="ingredient-filter-input" class="form-control bg-light border-0" placeholder="搜索食材..." autocomplete="off">
                    </div>
                </div>
                
                <div class="card-body px-4 pb-4 pt-1" style="overflow-y: auto; flex-grow: 1;">
                    <div class="d-flex flex-wrap gap-2" id="ingredients-pool">
                        <?php foreach ($ingredient_counts as $name => $count): ?>
                        <button class="btn btn-outline-secondary btn-sm ingredient-chip rounded-pill px-3 py-1" 
                                data-name="<?= e($name) ?>"
                                style="font-size: 0.82rem; transition: all 0.2s ease;">
                            <?= e($name) ?> <span class="badge bg-secondary-subtle text-secondary-emphasis rounded-pill ms-1" style="font-size: 0.72rem;"><?= $count ?></span>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Right Column: Matching Results ── -->
        <div class="col-12 col-md-8 col-lg-9">
            <!-- Selected indicators bar -->
            <div class="p-3 bg-white rounded-4 shadow-sm mb-4 border border-light d-flex align-items-center justify-content-between flex-wrap gap-2" id="selected-bar" style="display: none !important;">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="text-muted small fw-bold">已选食材：</span>
                    <div class="d-flex flex-wrap gap-2" id="selected-tags-container">
                        <!-- Dynamically filled -->
                    </div>
                </div>
            </div>

            <!-- Title & Loader -->
            <div class="d-flex align-items-center justify-content-between mb-4">
                <h4 class="mb-0 fw-bold d-flex align-items-center gap-2">
                    <span id="results-title">全部菜谱</span>
                    <span class="badge bg-primary-subtle text-primary fw-normal rounded-pill px-3" style="font-size: 0.9rem;" id="matching-count">
                        <?= count($recipes) ?> 道
                    </span>
                </h4>
                <!-- Spinner loader -->
                <div class="spinner-border text-primary spinner-border-sm" role="status" id="search-spinner" style="display: none;">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>

            <!-- Recipe Grid -->
            <div class="row g-3" id="recipe-grid">
                <?php if (empty($recipes)): ?>
                <div class="text-center py-5">
                    <div style="font-size:4rem;">🍽️</div>
                    <h4 class="text-muted mt-3">未找到菜谱</h4>
                </div>
                <?php else: ?>
                    <?php require __DIR__ . '/_recipe_cards.php'; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

$scripts = <<<'HTML'
<script>
(function() {
    const filterInput = document.getElementById('ingredient-filter-input');
    const chipsPool = document.getElementById('ingredients-pool');
    const chips = Array.from(chipsPool.getElementsByClassName('ingredient-chip'));
    const clearBtn = document.getElementById('clear-ingredients-btn');
    const selectedBar = document.getElementById('selected-bar');
    const selectedTagsContainer = document.getElementById('selected-tags-container');
    const resultsTitle = document.getElementById('results-title');
    const matchingCount = document.getElementById('matching-count');
    const recipeGrid = document.getElementById('recipe-grid');
    const spinner = document.getElementById('search-spinner');

    let selectedIngredients = [];

    // 1. Client-side Search / Filtering of Ingredient Chips
    filterInput.addEventListener('input', function() {
        const val = this.value.trim().toLowerCase();
        chips.forEach(chip => {
            const name = chip.dataset.name.toLowerCase();
            if (name.includes(val)) {
                chip.style.display = '';
            } else {
                chip.style.display = 'none';
            }
        });
    });

    // 2. Chip Clicks (Toggle Selection)
    chipsPool.addEventListener('click', function(e) {
        const chip = e.target.closest('.ingredient-chip');
        if (!chip) return;

        const name = chip.dataset.name;
        const idx = selectedIngredients.indexOf(name);

        if (idx > -1) {
            selectedIngredients.splice(idx, 1);
            chip.classList.remove('btn-primary', 'text-white');
            chip.classList.add('btn-outline-secondary');
        } else {
            selectedIngredients.push(name);
            chip.classList.remove('btn-outline-secondary');
            chip.classList.add('btn-primary', 'text-white');
        }

        updateUI();
    });

    // 3. Clear All Button Clicks
    clearBtn.addEventListener('click', function() {
        selectedIngredients = [];
        chips.forEach(chip => {
            chip.classList.remove('btn-primary', 'text-white');
            chip.classList.add('btn-outline-secondary');
        });
        updateUI();
    });

    // 4. Update UI & Fetch Results
    function updateUI() {
        // Toggle selected indicators bar
        if (selectedIngredients.length > 0) {
            clearBtn.style.display = 'inline-block';
            selectedBar.style.display = 'flex';
            selectedBar.classList.remove('d-none');
            
            // Build selected tags list
            selectedTagsContainer.innerHTML = selectedIngredients.map(ing => `
                <span class="badge bg-primary text-white rounded-pill px-3 py-1.5 d-flex align-items-center gap-1 font-size-8" style="cursor: pointer;" onclick="window.removeIngredientTag('${ing}')">
                    ${ing} <i data-lucide="x" style="width:12px;height:12px;"></i>
                </span>
            `).join('');
            if (typeof lucide !== 'undefined') lucide.createIcons();
            
            resultsTitle.textContent = '匹配到的菜谱';
        } else {
            clearBtn.style.display = 'none';
            selectedBar.style.display = 'none';
            selectedBar.classList.add('d-none');
            selectedTagsContainer.innerHTML = '';
            resultsTitle.textContent = '全部菜谱';
        }

        fetchRecipes();
    }

    // Export tag remover globally so inline onclick works
    window.removeIngredientTag = function(name) {
        const idx = selectedIngredients.indexOf(name);
        if (idx > -1) {
            selectedIngredients.splice(idx, 1);
            const chip = chips.find(c => c.dataset.name === name);
            if (chip) {
                chip.classList.remove('btn-primary', 'text-white');
                chip.classList.add('btn-outline-secondary');
            }
            updateUI();
        }
    };

    // 5. AJAX Search query
    let fetchDebounce = null;
    async function fetchRecipes() {
        if (fetchDebounce) clearTimeout(fetchDebounce);
        
        fetchDebounce = setTimeout(async () => {
            spinner.style.display = 'inline-block';
            const q = selectedIngredients.join(' ');
            const url = 'browse?partial=1&q=' + encodeURIComponent(q);

            try {
                const resp = await fetch(url);
                if (!resp.ok) throw new Error('Search failed');
                
                const html = await resp.text();
                recipeGrid.innerHTML = html || '<div class="text-center py-5 col-12"><div style="font-size:4rem;">🍽️</div><h4 class="text-muted mt-3">未找到菜谱</h4></div>';
                
                // Recount matching results in DOM
                const count = recipeGrid.querySelectorAll('.recipe-card').length;
                matchingCount.textContent = count + ' 道';
                
                if (typeof lucide !== 'undefined') lucide.createIcons();
            } catch (err) {
                console.error(err);
                recipeGrid.innerHTML = '<div class="text-center py-5 col-12"><div class="text-danger" style="font-size:4rem;">⚠️</div><h4 class="text-muted mt-3">查询出错，请重试</h4></div>';
                matchingCount.textContent = '0 道';
            } finally {
                spinner.style.display = 'none';
            }
        }, 150);
    }
})();
</script>
HTML;

require BASE_DIR . '/views/layout.php';
