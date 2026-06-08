<?php
/**
 * sync.php - Recipe indexer.
 *
 * Scans the dishes/ directory in the cloned repository, parses each .md file,
 * copies images to public/recipe-images/, and upserts records into SQLite.
 *
 * Can be invoked:
 *  - From the admin page (web request with CSRF + admin check)
 *  - From CLI: php sync.php
 *
 * Security:
 *  - All file paths validated against REPO_DIR before reading.
 *  - Image files validated against allowed extensions.
 *  - No user input flows into filesystem or DB operations.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/git.php';
require_once __DIR__ . '/parser.php';

define('IMG_DIR',     __DIR__ . '/public/recipe-images');
define('IMG_WEB',     'recipe-images');
define('ALLOWED_IMG', ['jpg','jpeg','png','gif','webp','svg']);

/**
 * Main sync entry-point.
 *
 * @param callable|null $log  Callback(string) for progress messages
 * @return array Summary stats
 */
function sync_recipes(?callable $log = null): array {
    $log ??= static fn(string $m) => null;

    $dishes_dir = REPO_DIR . '/dishes';

    if (!is_dir($dishes_dir)) {
        $log("⚠️  dishes/ directory not found at: $dishes_dir");
        $log("Run admin sync first to clone the repository.");
        return ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'deleted' => 0];
    }

    if (!is_dir(IMG_DIR)) {
        mkdir(IMG_DIR, 0750, true);
    }

    // Collect all markdown files
    $md_files = [];
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dishes_dir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iter as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'md') {
            $md_files[] = $file->getPathname();
        }
    }

    $log("Found " . count($md_files) . " markdown files.");

    $inserted       = 0;
    $updated        = 0;
    $skipped        = 0;
    $valid_paths    = [];

    foreach ($md_files as $abs_path) {
        // Validate path is inside REPO_DIR (prevent path traversal)
        $real_abs  = realpath($abs_path);
        $real_repo = realpath(REPO_DIR);
        if (!$real_abs || !$real_repo || !str_starts_with($real_abs, $real_repo . DIRECTORY_SEPARATOR)) {
            $log("⚠️  Skipping suspicious path: $abs_path");
            continue;
        }

        $rel_path = str_replace('\\', '/', substr($real_abs, strlen($real_repo) + 1));
        $valid_paths[] = $rel_path;

        try {
            $last_modified = git_file_last_modified($rel_path);
            $existing = db_row(
                "SELECT id, file_last_modified FROM recipes WHERE file_path = ?",
                [$rel_path]
            );

            if ($existing && $existing['file_last_modified'] === $last_modified && $last_modified !== '') {
                $skipped++;
                continue;
            }

            $content = file_get_contents($real_abs);
            if ($content === false) {
                $log("⚠️  Cannot read: $rel_path");
                continue;
            }

            $parsed    = parse_recipe($rel_path, $content);
            $recipe_dir = dirname($real_abs);
            $img_paths  = copy_images($recipe_dir, $parsed->image_file_names, $log);

            if ($existing === null) {
                db_exec(
                    "INSERT INTO recipes
                     (name, category, group_name, file_path, difficulty, calories,
                      description, ingredients, calculation, steps, notes, file_last_modified)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?)",
                    [
                        $parsed->name, $parsed->category, $parsed->group_name,
                        $rel_path, $parsed->difficulty, $parsed->calories,
                        $parsed->description, $parsed->ingredients, $parsed->calculation,
                        $parsed->steps, $parsed->notes, $last_modified,
                    ]
                );
                $recipe_id = (int)db_last_id();
                insert_images($recipe_id, $img_paths);
                $inserted++;
            } else {
                $recipe_id = (int)$existing['id'];
                db_exec(
                    "UPDATE recipes SET name=?, category=?, group_name=?, difficulty=?, calories=?,
                     description=?, ingredients=?, calculation=?, steps=?, notes=?,
                     file_last_modified=?, is_deleted=0
                     WHERE id=?",
                    [
                        $parsed->name, $parsed->category, $parsed->group_name,
                        $parsed->difficulty, $parsed->calories,
                        $parsed->description, $parsed->ingredients, $parsed->calculation,
                        $parsed->steps, $parsed->notes, $last_modified,
                        $recipe_id,
                    ]
                );
                db_exec("DELETE FROM recipe_images WHERE recipe_id=?", [$recipe_id]);
                insert_images($recipe_id, $img_paths);
                $updated++;
            }
        } catch (Throwable $e) {
            $log("❌ Error processing $rel_path: " . $e->getMessage());
        }
    }

    // Mark deleted recipes (files no longer in repo)
    $all_db = db_rows("SELECT id, file_path FROM recipes WHERE is_deleted=0");
    $deleted = 0;
    foreach ($all_db as $row) {
        if (!in_array($row['file_path'], $valid_paths, true)) {
            db_exec("UPDATE recipes SET is_deleted=1 WHERE id=?", [$row['id']]);
            $deleted++;
        }
    }

    $log("✅ Sync complete. Inserted: $inserted, Updated: $updated, Skipped: $skipped, Deleted: $deleted");

    return compact('inserted', 'updated', 'skipped', 'deleted');
}

/**
 * Copy image files from the recipe directory to public/recipe-images/.
 * Returns list of web-relative paths like "recipe-images/uuid.jpg".
 *
 * @param  string[]   $image_file_names  Paths relative to recipe dir
 * @return string[]
 */
function copy_images(string $recipe_dir, array $image_file_names, ?callable $log = null): array {
    $log ??= static fn(string $m) => null;
    $logical_paths = [];

    foreach ($image_file_names as $img_name) {
        // Sanitize the filename: strip leading ./ or ../
        $clean = ltrim(str_replace(['\\', '../', './'], ['/', '', ''], $img_name), '/');
        if ($clean === '' || str_contains($clean, '..')) continue;

        $ext = strtolower(pathinfo($clean, PATHINFO_EXTENSION));
        if (!in_array($ext, ALLOWED_IMG, true)) {
            $log("⚠️  Skipping disallowed image type: $clean");
            continue;
        }

        $src = realpath($recipe_dir . DIRECTORY_SEPARATOR . $clean);
        $real_dir = realpath($recipe_dir);
        if (!$src || !$real_dir || !str_starts_with($src, $real_dir . DIRECTORY_SEPARATOR)) {
            $log("⚠️  Image path outside recipe dir: $clean");
            continue;
        }

        $uuid    = bin2hex(random_bytes(16));
        $logical = IMG_WEB . "/{$uuid}.{$ext}";
        $dest    = IMG_DIR . "/{$uuid}.{$ext}";

        if (@copy($src, $dest)) {
            $logical_paths[] = $logical;
        } else {
            $log("⚠️  Could not copy image: $clean");
        }
    }

    return $logical_paths;
}

/**
 * Insert recipe_images rows for a recipe.
 * The last image in the list is marked as cover (matches C# behavior).
 */
function insert_images(int $recipe_id, array $logical_paths): void {
    $last_idx = count($logical_paths) - 1;
    foreach ($logical_paths as $i => $path) {
        db_exec(
            "INSERT INTO recipe_images (recipe_id, logical_path, is_cover) VALUES (?,?,?)",
            [$recipe_id, $path, $i === $last_idx ? 1 : 0]
        );
    }
}

/**
 * Scan tips/ directory, parse markdown files, and upsert them into sqlite.
 */
function sync_tips(?callable $log = null): array {
    $log ??= static fn(string $m) => null;

    $tips_dir = REPO_DIR . '/tips';

    if (!is_dir($tips_dir)) {
        $log("⚠️  tips/ directory not found at: $tips_dir");
        return ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'deleted' => 0];
    }

    $md_files = [];
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($tips_dir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iter as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'md') {
            $md_files[] = $file->getPathname();
        }
    }

    $log("Found " . count($md_files) . " tip markdown files.");

    $inserted       = 0;
    $updated        = 0;
    $skipped        = 0;
    $valid_paths    = [];

    foreach ($md_files as $abs_path) {
        $real_abs  = realpath($abs_path);
        $real_repo = realpath(REPO_DIR);
        if (!$real_abs || !$real_repo || !str_starts_with($real_abs, $real_repo . DIRECTORY_SEPARATOR)) {
            $log("⚠️  Skipping suspicious path: $abs_path");
            continue;
        }

        $rel_path = str_replace('\\', '/', substr($real_abs, strlen($real_repo) + 1));
        $valid_paths[] = $rel_path;

        try {
            $last_modified = git_file_last_modified($rel_path);
            $existing = db_row(
                "SELECT id, file_last_modified FROM tips WHERE file_path = ?",
                [$rel_path]
            );

            if ($existing && $existing['file_last_modified'] === $last_modified && $last_modified !== '') {
                $skipped++;
                continue;
            }

            $content = file_get_contents($real_abs);
            if ($content === false) {
                $log("⚠️  Cannot read: $rel_path");
                continue;
            }

            $parts = explode('/', $rel_path);
            $category = (count($parts) >= 3) ? $parts[1] : 'root';
            $title = pathinfo($parts[count($parts) - 1], PATHINFO_FILENAME);

            if ($existing === null) {
                db_exec(
                    "INSERT INTO tips (title, category, file_path, content, file_last_modified) VALUES (?,?,?,?,?)",
                    [$title, $category, $rel_path, $content, $last_modified]
                );
                $inserted++;
            } else {
                db_exec(
                    "UPDATE tips SET title=?, category=?, content=?, file_last_modified=?, is_deleted=0 WHERE id=?",
                    [$title, $category, $content, $last_modified, $existing['id']]
                );
                $updated++;
            }
        } catch (Throwable $e) {
            $log("❌ Error processing tip $rel_path: " . $e->getMessage());
        }
    }

    $all_db = db_rows("SELECT id, file_path FROM tips WHERE is_deleted=0");
    $deleted = 0;
    foreach ($all_db as $row) {
        if (!in_array($row['file_path'], $valid_paths, true)) {
            db_exec("UPDATE tips SET is_deleted=1 WHERE id=?", [$row['id']]);
            $deleted++;
        }
    }

    $log("✅ Tips sync complete. Inserted: $inserted, Updated: $updated, Skipped: $skipped, Deleted: $deleted");

    return compact('inserted', 'updated', 'skipped', 'deleted');
}

// ─── CLI entry-point ─────────────────────────────────────────────────────────

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($argv[0] ?? '')) {
    require_once __DIR__ . '/helpers.php';

    $url = get_setting('repo_url', 'https://github.com/Anduin2017/HowToCook.git');

    echo "🔄 Syncing git repository from: $url\n";
    try {
        git_sync($url);
        echo "✅ Git sync complete.\n";
    } catch (RuntimeException $e) {
        echo "❌ Git sync failed: " . $e->getMessage() . "\n";
    }

    echo "📂 Indexing recipes...\n";
    sync_recipes(function (string $msg) { echo $msg . "\n"; });
    echo "📂 Indexing tips...\n";
    sync_tips(function (string $msg) { echo $msg . "\n"; });
}
