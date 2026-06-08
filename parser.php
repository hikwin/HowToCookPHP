<?php
/**
 * parser.php - Recipe Markdown parser and custom Markdown-to-HTML renderer.
 *
 * Two responsibilities:
 *  1. ParsedRecipe: extract structured data (name, category, sections) from .md files.
 *  2. markdown_to_html(): convert stored Markdown text to safe HTML for display.
 *
 * Security:
 *  - Output of markdown_to_html() is safe to embed in HTML (no XSS).
 *  - All user-injected strings go through htmlspecialchars before interpolation.
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

// ─── Recipe Parser ────────────────────────────────────────────────────────────

class ParsedRecipe {
    public string  $name        = '';
    public string  $category    = '';
    public ?string $group_name  = null;
    public int     $difficulty  = 0;
    public ?float  $calories    = null;
    public string  $description = '';
    public string  $ingredients = '';
    public string  $calculation = '';
    public string  $steps       = '';
    public string  $notes       = '';
    /** @var string[] */
    public array   $image_file_names = [];
}

/**
 * Parse a recipe Markdown file into a ParsedRecipe struct.
 *
 * @param string $relative_file_path  Repo-relative path, e.g. "dishes/vegetable_dish/西红柿炒鸡蛋.md"
 * @param string $markdown            Raw markdown content
 */
function parse_recipe(string $relative_file_path, string $markdown): ParsedRecipe {
    $recipe = new ParsedRecipe();

    // Derive category and group_name from path
    $parts = explode('/', $relative_file_path);
    $recipe->category   = $parts[1] ?? 'unknown';
    $recipe->group_name = (count($parts) >= 4) ? $parts[2] : null;
    $recipe->name       = pathinfo($parts[count($parts) - 1], PATHINFO_FILENAME);

    // Collect image references
    preg_match_all('/!\[[^\]]*\]\((?P<file>[^)]+)\)/', $markdown, $img_matches);
    $recipe->image_file_names = array_values(array_unique(array_filter($img_matches['file'])));

    // Strip image tags from markdown before section parsing
    $stripped = preg_replace('/!\[[^\]]*\]\([^)]+\)/', '', $markdown);

    $lines = explode("\n", $stripped);

    $current_section  = null;
    $found_difficulty = false;

    $desc_lines        = [];
    $ingredients_lines = [];
    $calculation_lines = [];
    $steps_lines       = [];
    $notes_lines       = [];

    foreach ($lines as $raw_line) {
        $line = rtrim($raw_line);

        // Skip H1 title
        if (str_starts_with($line, '# ')) continue;

        // Detect section headers
        if (str_starts_with($line, '## ')) {
            $current_section = trim(substr($line, 3));
            continue;
        }

        // Difficulty line (stars anywhere)
        if (!$found_difficulty && str_contains($line, '★')) {
            $recipe->difficulty = substr_count($line, '★');
            $found_difficulty   = true;
            continue;
        }

        // Calorie line: "预估卡路里：NNN大卡"
        if (preg_match('/^预估卡路里：\s*(\d+(?:\.\d+)?)\s*大卡/', $line, $matches)) {
            $recipe->calories = (float)$matches[1];
            continue;
        }

        switch ($current_section) {
            case null:
                $desc_lines[] = $line;
                break;
            case '必备原料和工具':
                $ingredients_lines[] = $line;
                break;
            case '计算':
                $calculation_lines[] = $line;
                break;
            case '操作':
                $steps_lines[] = $line;
                break;
            case '附加内容':
                $notes_lines[] = $line;
                break;
        }
    }

    $recipe->description = trim(implode("\n", $desc_lines));
    $recipe->ingredients = trim(implode("\n", $ingredients_lines));
    $recipe->calculation = trim(implode("\n", $calculation_lines));
    $recipe->steps       = trim(implode("\n", $steps_lines));
    $recipe->notes       = trim(implode("\n", $notes_lines));

    return $recipe;
}

// ─── Markdown to HTML Renderer ────────────────────────────────────────────────

/**
 * Convert a subset of Markdown to safe HTML.
 *
 * Supported:
 *  - Headings: # H1 through ### H3
 *  - Bold: **text** and __text__
 *  - Italic: *text* and _text_
 *  - Unordered lists: - item or * item
 *  - Ordered lists: 1. item
 *  - Checkboxes: - [ ] and - [x]
 *  - Code spans: `code`
 *  - Horizontal rule: ---
 *  - Tables: | col | col |
 *  - Blank lines → paragraph breaks
 *
 * Security: All literal text is HTML-escaped before rendering.
 */
function markdown_to_html(string $markdown): string {
    if (trim($markdown) === '') return '';

    $lines = explode("\n", $markdown);
    $output = '';
    $in_list       = false;
    $list_type     = '';   // 'ul' or 'ol'
    $in_table      = false;
    $table_buf     = [];
    $para_lines    = [];

    $flush_para = function () use (&$para_lines, &$output): void {
        $text = trim(implode(' ', $para_lines));
        if ($text !== '') {
            $output .= '<p>' . inline_md($text) . '</p>' . "\n";
        }
        $para_lines = [];
    };

    $flush_list = function () use (&$in_list, &$list_type, &$output): void {
        if ($in_list) {
            $output .= "</{$list_type}>\n";
            $in_list   = false;
            $list_type = '';
        }
    };

    $flush_table = function () use (&$in_table, &$table_buf, &$output): void {
        if (!$in_table || empty($table_buf)) return;
        $output .= '<div class="table-responsive"><table class="table table-bordered table-sm">' . "\n";
        $is_header = true;
        foreach ($table_buf as $row_text) {
            // Skip separator rows (---|---)
            if (preg_match('/^[\|\s\-:]+$/', $row_text)) continue;
            $cells = array_map('trim', explode('|', trim($row_text, ' |')));
            if ($is_header) {
                $output .= '<thead><tr>';
                foreach ($cells as $cell) {
                    $output .= '<th>' . inline_md($cell) . '</th>';
                }
                $output .= '</tr></thead><tbody>' . "\n";
                $is_header = false;
            } else {
                $output .= '<tr>';
                foreach ($cells as $cell) {
                    $output .= '<td>' . inline_md($cell) . '</td>';
                }
                $output .= '</tr>' . "\n";
            }
        }
        $output   .= '</tbody></table></div>' . "\n";
        $in_table  = false;
        $table_buf = [];
    };

    foreach ($lines as $line) {
        $trimmed = rtrim($line);

        // Blank line
        if (trim($trimmed) === '') {
            $flush_para();
            $flush_list();
            $flush_table();
            continue;
        }

        // Horizontal rule
        if (preg_match('/^-{3,}$/', $trimmed) || preg_match('/^\*{3,}$/', $trimmed)) {
            $flush_para(); $flush_list(); $flush_table();
            $output .= '<hr>' . "\n";
            continue;
        }

        // Headings
        if (preg_match('/^(#{1,3})\s+(.+)$/', $trimmed, $m)) {
            $flush_para(); $flush_list(); $flush_table();
            $level = strlen($m[1]) + 2; // H1 → h3, H2 → h4, H3 → h5 (shift for card context)
            $level = min($level, 6);
            $output .= "<h{$level}>" . inline_md($m[2]) . "</h{$level}>\n";
            continue;
        }

        // Table row
        if (str_starts_with($trimmed, '|')) {
            $flush_para(); $flush_list();
            if (!$in_table) $in_table = true;
            $table_buf[] = $trimmed;
            continue;
        }
        if ($in_table) {
            $flush_table();
        }

        // Checkbox list items
        if (preg_match('/^[\-\*]\s+\[( |x)\]\s+(.*)$/i', $trimmed, $m)) {
            $flush_para();
            if (!$in_list || $list_type !== 'ul') {
                $flush_list();
                $output   .= '<ul class="list-unstyled">' . "\n";
                $in_list   = true;
                $list_type = 'ul';
            }
            $checked = strtolower($m[1]) === 'x';
            $icon    = $checked
                ? '<span style="color:#198754;">✅</span> '
                : '<span style="color:#adb5bd;">⬜</span> ';
            $output .= '<li>' . $icon . inline_md($m[2]) . '</li>' . "\n";
            continue;
        }

        // Unordered list
        if (preg_match('/^[\-\*\+]\s+(.+)$/', $trimmed, $m)) {
            $flush_para();
            if (!$in_list || $list_type !== 'ul') {
                $flush_list();
                $output   .= '<ul>' . "\n";
                $in_list   = true;
                $list_type = 'ul';
            }
            $output .= '<li>' . inline_md($m[1]) . '</li>' . "\n";
            continue;
        }

        // Ordered list
        if (preg_match('/^\d+\.\s+(.+)$/', $trimmed, $m)) {
            $flush_para();
            if (!$in_list || $list_type !== 'ol') {
                $flush_list();
                $output   .= '<ol>' . "\n";
                $in_list   = true;
                $list_type = 'ol';
            }
            $output .= '<li>' . inline_md($m[1]) . '</li>' . "\n";
            continue;
        }

        // Regular paragraph line
        $flush_list(); $flush_table();
        $para_lines[] = $trimmed;
    }

    $flush_para();
    $flush_list();
    $flush_table();

    return $output;
}

/**
 * Process inline Markdown elements (bold, italic, code, links) within a single line.
 * Input text is HTML-escaped first, then safe tokens are re-inserted.
 *
 * Link handling: [text](url) is extracted BEFORE htmlspecialchars so that
 * URLs containing & ? = etc. are not mangled. The href is validated to only
 * allow http/https/ftp schemes; all other schemes (javascript: etc.) are stripped.
 */
function inline_md(string $text): string {
    // ── Step 1: Extract Markdown links BEFORE HTML-escaping ──────────────────
    // Pattern supports URLs with query strings, fragments, and Unicode chars.
    // We allow any character inside (...) except unescaped ) to handle complex URLs.
    $links = [];
    $placeholder_base = "\x02LINK";   // ASCII STX — never appears in normal text
    $text = preg_replace_callback(
        '/\[([^\]]*)\]\(([^)]*(?:\([^)]*\)[^)]*)*)\)/',
        function (array $m) use (&$links, $placeholder_base): string {
            $label  = $m[1];          // raw label text (will be escaped later)
            $url    = trim($m[2]);    // raw URL (preserve & ? = etc.)

            // Security: only allow safe schemes
            if (!preg_match('/^(https?|ftp):\/\//i', $url)) {
                // Render label only (no link)
                return $label;
            }

            $idx = count($links);
            $links[$idx] = ['label' => $label, 'url' => $url];
            return $placeholder_base . $idx . "\x03";   // STX…ETX placeholder
        },
        $text
    );

    // ── Step 2: Escape everything else for XSS safety ────────────────────────
    $safe = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    // ── Step 3: Restore link placeholders as safe <a> tags ───────────────────
    foreach ($links as $idx => $link) {
        $safe_label = htmlspecialchars($link['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safe_url   = htmlspecialchars($link['url'],   ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $placeholder = htmlspecialchars($placeholder_base . $idx . "\x03",
                                        ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $anchor = "<a href=\"{$safe_url}\" target=\"_blank\" rel=\"noopener noreferrer\">{$safe_label}</a>";
        $safe = str_replace($placeholder, $anchor, $safe);
    }

    // ── Step 4: Other inline elements (operate on already-escaped string) ────

    // Code spans
    $safe = preg_replace('/`([^`]+)`/', '<code>$1</code>', $safe);

    // Bold: **text** or __text__
    $safe = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $safe);
    $safe = preg_replace('/__(.+?)__/', '<strong>$1</strong>', $safe);

    // Italic: *text* or _text_  (must come after bold)
    $safe = preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $safe);
    $safe = preg_replace('/_([^_]+)_/', '<em>$1</em>', $safe);

    return $safe;
}


/**
 * Build the full recipe Markdown for the detail page, combining all sections.
 * This is used for rendering on the detail page along with section headers.
 */
function build_recipe_markdown(array $recipe): string {
    $parts = [];

    $name        = $recipe['name'];
    $description = $recipe['description'];
    $ingredients = $recipe['ingredients'];
    $calculation = $recipe['calculation'];
    $steps       = $recipe['steps'];
    $notes       = $recipe['notes'];

    $parts[] = "# {$name}";

    if (!empty($description)) $parts[] = $description;
    if ($recipe['calories'])   $parts[] = "**预估卡路里：{$recipe['calories']} 大卡**";
    if (!empty($ingredients))  $parts[] = "## 必备原料和工具\n{$ingredients}";
    if (!empty($calculation))  $parts[] = "## 计算\n{$calculation}";
    if (!empty($steps))        $parts[] = "## 操作\n{$steps}";
    if (!empty($notes))        $parts[] = "## 附加内容\n{$notes}";

    return implode("\n\n", $parts);
}
