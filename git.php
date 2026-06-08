<?php
/**
 * git.php - Safe git execution helpers.
 *
 * Security:
 *  - Never passes unvalidated user input to shell commands.
 *  - All string arguments are strictly hardcoded or validated before use.
 *  - Uses proc_open with array arguments to avoid shell interpretation.
 */

declare(strict_types=1);

define('REPO_DIR', __DIR__ . '/data/repo');

/**
 * Clone or update the HowToCook git repository.
 *
 * @param string $url  The remote URL (validated to be http(s) or git scheme)
 * @return bool  true on success
 * @throws RuntimeException on failure
 */
function git_sync(string $url): bool {
    // Validate URL scheme to prevent command injection
    if (!preg_match('#^(https?://|git://)[a-zA-Z0-9._\-/:@]+$#', $url)) {
        throw new RuntimeException('Invalid repository URL format.');
    }

    if (!is_dir(REPO_DIR)) {
        mkdir(REPO_DIR, 0750, true);
    }

    $git_dir = REPO_DIR . '/.git';

    if (is_dir($git_dir)) {
        // Pull latest changes
        [$code, $out, $err] = run_git(['git', 'fetch', '--depth=1', 'origin'], REPO_DIR);
        if ($code !== 0) throw new RuntimeException("git fetch failed: $err");
        [$code, , $err] = run_git(['git', 'reset', '--hard', 'origin/master'], REPO_DIR);
        if ($code !== 0) throw new RuntimeException("git reset failed: $err");
    } else {
        // Clone fresh
        [$code, , $err] = run_git(['git', 'clone', '--depth=1', $url, REPO_DIR]);
        if ($code !== 0) throw new RuntimeException("git clone failed: $err");
    }

    return true;
}

/**
 * Get the last commit timestamp for a file in the repository.
 *
 * @param string $relative_path  Repo-relative path like "dishes/..."
 * @return string  ISO 8601 timestamp, or empty string on failure
 */
function git_file_last_modified(string $relative_path): string {
    // Validate path: must not escape the repository
    if (str_contains($relative_path, '..') || str_contains($relative_path, "\0")) {
        return '';
    }

    [$code, $out] = run_git(
        ['git', 'log', '-1', '--format=%cI', '--', $relative_path],
        REPO_DIR
    );

    return ($code === 0) ? trim($out) : '';
}

/**
 * Get git log contributors for a file.
 * Returns array of ['name' => '...', 'email' => '...'] entries.
 */
function git_file_contributors(string $relative_path): array {
    if (str_contains($relative_path, '..') || str_contains($relative_path, "\0")) {
        return [];
    }

    [$code, $out] = run_git(
        ['git', 'log', '--pretty=format:%aN|%aE', '--', $relative_path],
        REPO_DIR
    );

    if ($code !== 0 || trim($out) === '') return [];

    $map = [];
    foreach (explode("\n", $out) as $line) {
        $parts = explode('|', $line, 2);
        if (count($parts) < 2) continue;
        [$name, $email] = $parts;
        $email = trim($email);
        $name  = trim($name);
        if (!isset($map[$email])) {
            $map[$email] = ['name' => $name, 'email' => $email, 'count' => 0];
        }
        $map[$email]['count']++;
    }

    usort($map, fn($a, $b) => $b['count'] - $a['count']);
    return array_values($map);
}

/**
 * Execute a git command safely using proc_open (no shell interpretation).
 *
 * @param array  $cmd   Command and arguments as array
 * @param string $cwd   Working directory
 * @return array  [exit_code, stdout, stderr]
 */
function run_git(array $cmd, string $cwd = ''): array {
    // Validate that the first element is the 'git' binary
    if ($cmd[0] !== 'git') {
        throw new RuntimeException('Only git commands are allowed.');
    }

    $desc = [
        0 => ['pipe', 'r'],  // stdin
        1 => ['pipe', 'w'],  // stdout
        2 => ['pipe', 'w'],  // stderr
    ];

    $opts = [];
    if ($cwd !== '' && is_dir($cwd)) {
        $opts['cwd'] = $cwd;
    }

    $proc = proc_open($cmd, $desc, $pipes, $opts['cwd'] ?? null);
    if (!is_resource($proc)) {
        return [1, '', 'Failed to spawn git process'];
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    $code = proc_close($proc);
    return [$code, $stdout ?? '', $stderr ?? ''];
}

/**
 * Check if git is available in PATH.
 */
function git_available(): bool {
    [$code] = run_git(['git', '--version']);
    return $code === 0;
}
