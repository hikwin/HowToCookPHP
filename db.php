<?php
/**
 * db.php - Database initialisation and schema migration for HowToCookViewer.
 * Uses PDO + SQLite3. All queries use prepared statements (SQL injection prevention).
 */

declare(strict_types=1);

define('DB_DIR',  __DIR__ . '/data');
define('DB_PATH', DB_DIR . '/howtocook.db');

/**
 * Return a singleton PDO connection to the SQLite database.
 * Creates the database and runs schema migrations on first call.
 */
function get_db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    if (!is_dir(DB_DIR)) {
        mkdir(DB_DIR, 0750, true);
    }

    $pdo = new PDO('sqlite:' . DB_PATH, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    // Enable WAL mode for better concurrent read performance
    $pdo->exec('PRAGMA journal_mode=WAL;');
    $pdo->exec('PRAGMA foreign_keys=ON;');

    migrate($pdo);
    return $pdo;
}

/**
 * Run schema migrations in order. Each migration is idempotent.
 */
function migrate(PDO $pdo): void {
    // Track schema version
    $pdo->exec("CREATE TABLE IF NOT EXISTS schema_version (version INTEGER PRIMARY KEY)");
    $current = (int)($pdo->query("SELECT COALESCE(MAX(version),0) FROM schema_version")->fetchColumn());

    $migrations = [];

    // ── Migration 1: core tables ─────────────────────────────────────────────
    $migrations[1] = <<<SQL
    CREATE TABLE IF NOT EXISTS recipes (
        id                  INTEGER PRIMARY KEY AUTOINCREMENT,
        name                TEXT NOT NULL,
        category            TEXT NOT NULL DEFAULT '',
        group_name          TEXT,
        file_path           TEXT NOT NULL UNIQUE,
        difficulty          INTEGER NOT NULL DEFAULT 0,
        calories            REAL,
        description         TEXT NOT NULL DEFAULT '',
        ingredients         TEXT NOT NULL DEFAULT '',
        calculation         TEXT NOT NULL DEFAULT '',
        steps               TEXT NOT NULL DEFAULT '',
        notes               TEXT NOT NULL DEFAULT '',
        file_last_modified  TEXT NOT NULL DEFAULT '',
        is_deleted          INTEGER NOT NULL DEFAULT 0
    );

    CREATE TABLE IF NOT EXISTS recipe_images (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        recipe_id   INTEGER NOT NULL REFERENCES recipes(id) ON DELETE CASCADE,
        logical_path TEXT NOT NULL,
        is_cover    INTEGER NOT NULL DEFAULT 0
    );

    CREATE TABLE IF NOT EXISTS users (
        id           INTEGER PRIMARY KEY AUTOINCREMENT,
        email        TEXT NOT NULL UNIQUE COLLATE NOCASE,
        display_name TEXT NOT NULL,
        password_hash TEXT NOT NULL,
        is_admin     INTEGER NOT NULL DEFAULT 0,
        created_at   TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS recipe_comments (
        id                INTEGER PRIMARY KEY AUTOINCREMENT,
        recipe_id         INTEGER NOT NULL REFERENCES recipes(id) ON DELETE CASCADE,
        user_id           INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        parent_comment_id INTEGER REFERENCES recipe_comments(id) ON DELETE CASCADE,
        content           TEXT NOT NULL,
        created_at        TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS recipe_likes (
        user_id   INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        recipe_id INTEGER NOT NULL REFERENCES recipes(id) ON DELETE CASCADE,
        PRIMARY KEY (user_id, recipe_id)
    );

    CREATE TABLE IF NOT EXISTS recipe_favorites (
        user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        recipe_id  INTEGER NOT NULL REFERENCES recipes(id) ON DELETE CASCADE,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        PRIMARY KEY (user_id, recipe_id)
    );

    CREATE TABLE IF NOT EXISTS global_settings (
        key   TEXT PRIMARY KEY,
        value TEXT
    );

    CREATE INDEX IF NOT EXISTS idx_recipes_category    ON recipes(category);
    CREATE INDEX IF NOT EXISTS idx_recipes_difficulty  ON recipes(difficulty);
    CREATE INDEX IF NOT EXISTS idx_recipes_is_deleted  ON recipes(is_deleted);
    CREATE INDEX IF NOT EXISTS idx_recipe_images_recipe ON recipe_images(recipe_id);
    CREATE INDEX IF NOT EXISTS idx_comments_recipe     ON recipe_comments(recipe_id);
    CREATE INDEX IF NOT EXISTS idx_likes_recipe        ON recipe_likes(recipe_id);
    CREATE INDEX IF NOT EXISTS idx_favorites_recipe    ON recipe_favorites(recipe_id);
    SQL;

    // ── Migration 2: cooking tips table ──────────────────────────────────────
    $migrations[2] = <<<SQL
    CREATE TABLE IF NOT EXISTS tips (
        id                  INTEGER PRIMARY KEY AUTOINCREMENT,
        title               TEXT NOT NULL,
        category            TEXT NOT NULL,
        file_path           TEXT NOT NULL UNIQUE,
        content             TEXT NOT NULL,
        file_last_modified  TEXT NOT NULL DEFAULT '',
        is_deleted          INTEGER NOT NULL DEFAULT 0
    );
    CREATE INDEX IF NOT EXISTS idx_tips_category ON tips(category);
    CREATE INDEX IF NOT EXISTS idx_tips_is_deleted ON tips(is_deleted);
    SQL;

    foreach ($migrations as $version => $sql) {
        if ($current < $version) {
            $pdo->exec($sql);
            $pdo->exec("INSERT OR REPLACE INTO schema_version(version) VALUES($version)");
        }
    }

    // Default global settings (including default admin credentials)
    $defaults = [
        'site_name'           => 'HowToCook',
        'repo_url'            => 'https://github.com/Anduin2017/HowToCook.git',
        'repo_backup_url'     => 'https://gitee.com/Anduin2017/HowToCook.git',
        'default_admin_email' => 'admin@default.com',
        'default_admin_name'  => 'Admin',
        'default_admin_pass'  => 'Admin@123456!',
    ];
    $ins = $pdo->prepare("INSERT OR IGNORE INTO global_settings(key,value) VALUES(?,?)");
    foreach ($defaults as $k => $v) {
        $ins->execute([$k, $v]);
    }

    // Seed default admin if no users exist (reads from configurable settings)
    $count = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($count === 0) {
        $adminEmail = (string)$pdo->query("SELECT value FROM global_settings WHERE key='default_admin_email'")->fetchColumn();
        $adminName  = (string)$pdo->query("SELECT value FROM global_settings WHERE key='default_admin_name'")->fetchColumn();
        $adminPass  = (string)$pdo->query("SELECT value FROM global_settings WHERE key='default_admin_pass'")->fetchColumn();
        $hash = password_hash($adminPass, PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $pdo->prepare("INSERT INTO users (email, display_name, password_hash, is_admin) VALUES (?, ?, ?, 1)");
        $stmt->execute([$adminEmail, $adminName, $hash]);
    }
}

// ─── Query Helpers ────────────────────────────────────────────────────────────

/**
 * Fetch a single row as associative array, or null.
 */
function db_row(string $sql, array $params = []): ?array {
    $stmt = get_db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row !== false ? $row : null;
}

/**
 * Fetch all rows as associative arrays.
 */
function db_rows(string $sql, array $params = []): array {
    $stmt = get_db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Fetch a single scalar value.
 */
function db_scalar(string $sql, array $params = []) {
    $stmt = get_db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

/**
 * Execute an INSERT/UPDATE/DELETE statement.
 * Returns the number of affected rows.
 */
function db_exec(string $sql, array $params = []): int {
    $stmt = get_db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

/**
 * Return the last inserted row ID.
 */
function db_last_id(): string {
    return get_db()->lastInsertId();
}

/**
 * Get a global setting value by key, or a default.
 */
function get_setting(string $key, string $default = ''): string {
    $val = db_scalar("SELECT value FROM global_settings WHERE key = ?", [$key]);
    return $val !== false && $val !== null ? (string)$val : $default;
}

/**
 * Set a global setting value.
 */
function set_setting(string $key, string $value): void {
    db_exec("INSERT OR REPLACE INTO global_settings(key,value) VALUES(?,?)", [$key, $value]);
}
