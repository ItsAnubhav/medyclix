<?php
/**
 * Pull origin/master onto this server.
 *
 * One-time setup on the server:
 *   1. The site root must be a git checkout of master, with this file in that root.
 *   2. Copy application/config/deploy_token.example.php to application/config/deploy_token.php
 *      and replace the placeholder with a random secret of at least 32 characters.
 *      An environment variable named MEDYCLIX_DEPLOY_TOKEN works instead of that file.
 *   3. The PHP user must be able to run git and write this checkout.
 *   4. If the GitHub repository is private, give that same user read access.
 *
 * Deploy after you push to master:
 *   curl -fsS -H "X-Deploy-Token: YOUR_SECRET" https://hms.medyclix.com/deploy.php
 *   php deploy.php
 *
 * Or let GitHub do it. Webhook payload URL is https://hms.medyclix.com/deploy.php,
 * content type application/json, secret is the same token, and the event is push.
 * Use HTTPS. A token in the query string is accepted and can end up in access logs.
 */

declare(strict_types=1);

const DEPLOY_REMOTE = 'origin';
const DEPLOY_BRANCH = 'master';
const DEPLOY_REPOSITORY = 'github.com/itsanubhav/medyclix';

$isCli = PHP_SAPI === 'cli';
if (!$isCli) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex');
}

set_time_limit(120);

function respond(int $status, array $payload): void
{
    if (PHP_SAPI !== 'cli') {
        http_response_code($status);
    }

    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
    exit($status >= 400 ? 1 : 0);
}

function deploymentToken(): string
{
    $candidates = [
        getenv('MEDYCLIX_DEPLOY_TOKEN'),
        $_SERVER['MEDYCLIX_DEPLOY_TOKEN'] ?? null,
        $_SERVER['REDIRECT_MEDYCLIX_DEPLOY_TOKEN'] ?? null,
    ];

    foreach ($candidates as $candidate) {
        if (!is_string($candidate)) {
            continue;
        }

        $candidate = trim($candidate);
        if (strlen($candidate) >= 32) {
            return $candidate;
        }
    }

    $tokenFile = __DIR__ . '/application/config/deploy_token.php';
    if (!is_file($tokenFile)) {
        return '';
    }

    if (!defined('MEDYCLIX_DEPLOY')) {
        define('MEDYCLIX_DEPLOY', true);
    }

    $loaded = include $tokenFile;

    return is_string($loaded) ? trim($loaded) : '';
}

function tokensMatch(string $known, string $provided): bool
{
    if ($provided === '') {
        return false;
    }

    return hash_equals($known, $provided);
}

function githubPush(): ?array
{
    $event = $_SERVER['HTTP_X_GITHUB_EVENT'] ?? '';
    $signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
    if ($event === '' && $signature === '') {
        return null;
    }

    $payload = file_get_contents('php://input');
    if (!is_string($payload)) {
        $payload = '';
    }

    $expected = 'sha256=' . hash_hmac('sha256', $payload, deploymentToken());
    if (!is_string($signature) || !tokensMatch($expected, $signature)) {
        respond(401, ['status' => 'error', 'message' => 'Unauthorized.']);
    }

    if ($event === 'ping') {
        respond(200, ['status' => 'success', 'message' => 'Webhook ping received.']);
    }

    if ($event !== 'push') {
        respond(200, ['status' => 'skipped', 'message' => 'Ignored GitHub event.']);
    }

    $decoded = json_decode($payload, true);
    if (!is_array($decoded)) {
        respond(400, ['status' => 'error', 'message' => 'Invalid webhook payload.']);
    }

    if (!empty($decoded['deleted']) || ($decoded['ref'] ?? '') !== 'refs/heads/' . DEPLOY_BRANCH) {
        respond(200, [
            'status' => 'skipped',
            'message' => 'Ignored push outside ' . DEPLOY_BRANCH . '.',
        ]);
    }

    return $decoded;
}

function normalizeRepository(string $url): string
{
    $url = strtolower(trim($url));
    $url = preg_replace('#\.git$#', '', $url) ?? $url;
    $url = preg_replace('#^git@github\.com:#', 'github.com/', $url) ?? $url;
    $url = preg_replace('#^ssh://git@github\.com/#', 'github.com/', $url) ?? $url;
    $url = preg_replace('#^https?://#', '', $url) ?? $url;

    return rtrim($url, '/');
}

function runGit(string $repositoryPath, array $arguments): string
{
    $command = 'GIT_TERMINAL_PROMPT=0 git'
        . ' -c ' . escapeshellarg('safe.directory=' . $repositoryPath)
        . ' -C ' . escapeshellarg($repositoryPath);

    foreach ($arguments as $argument) {
        $command .= ' ' . escapeshellarg($argument);
    }

    $command .= ' 2>&1';

    $output = [];
    $exitCode = 0;
    exec($command, $output, $exitCode);

    if ($exitCode !== 0) {
        throw new RuntimeException(
            'Git command failed: git ' . implode(' ', $arguments) . "\n" . implode("\n", $output)
        );
    }

    return trim(implode("\n", $output));
}

if (!function_exists('exec')) {
    respond(500, ['status' => 'error', 'message' => 'PHP exec() is disabled on this server.']);
}

$token = deploymentToken();
if (strlen($token) < 32 || strpos($token, 'replace-with-a-random-secret') !== false) {
    respond(500, [
        'status' => 'error',
        'message' => 'Set MEDYCLIX_DEPLOY_TOKEN, or create application/config/deploy_token.php from deploy_token.example.php, with a secret of at least 32 characters.',
    ]);
}

if (!$isCli && githubPush() === null) {
    $provided = $_SERVER['HTTP_X_DEPLOY_TOKEN'] ?? ($_POST['token'] ?? ($_GET['token'] ?? ''));
    if (!is_string($provided) || !tokensMatch($token, $provided)) {
        respond(401, ['status' => 'error', 'message' => 'Unauthorized.']);
    }
}

$repositoryPath = __DIR__;
if (!is_dir($repositoryPath . DIRECTORY_SEPARATOR . '.git')) {
    respond(500, ['status' => 'error', 'message' => 'deploy.php is not located in the Git repository root.']);
}

$lockPath = $repositoryPath . '/application/cache/deploy.lock';
$lockHandle = fopen($lockPath, 'c');
if ($lockHandle === false) {
    respond(500, [
        'status' => 'error',
        'message' => 'Could not create application/cache/deploy.lock. That directory must be writable by PHP.',
    ]);
}

if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
    respond(409, ['status' => 'error', 'message' => 'Another deployment is already running.']);
}

try {
    $remoteUrl = normalizeRepository(runGit($repositoryPath, ['remote', 'get-url', DEPLOY_REMOTE]));
    if ($remoteUrl !== DEPLOY_REPOSITORY) {
        throw new RuntimeException("Unexpected origin URL: {$remoteUrl}");
    }

    $currentBranch = runGit($repositoryPath, ['branch', '--show-current']);
    if ($currentBranch !== DEPLOY_BRANCH) {
        throw new RuntimeException('Expected branch ' . DEPLOY_BRANCH . ", found {$currentBranch}.");
    }

    $trackedChanges = runGit($repositoryPath, ['status', '--porcelain', '--untracked-files=no']);
    if ($trackedChanges !== '') {
        throw new RuntimeException("Tracked local changes exist; deployment was stopped.\n" . $trackedChanges);
    }

    runGit($repositoryPath, ['fetch', '--prune', DEPLOY_REMOTE, DEPLOY_BRANCH]);

    $before = runGit($repositoryPath, ['rev-parse', 'HEAD']);
    $target = runGit($repositoryPath, ['rev-parse', DEPLOY_REMOTE . '/' . DEPLOY_BRANCH]);

    if ($before === $target) {
        respond(200, [
            'status' => 'success',
            'message' => 'Already up to date.',
            'commit' => $before,
        ]);
    }

    $mergeBase = runGit($repositoryPath, ['merge-base', 'HEAD', DEPLOY_REMOTE . '/' . DEPLOY_BRANCH]);
    if ($mergeBase !== $before) {
        throw new RuntimeException('Local master has diverged from origin/master; deployment was stopped.');
    }

    $output = runGit($repositoryPath, ['merge', '--ff-only', DEPLOY_REMOTE . '/' . DEPLOY_BRANCH]);

    if (function_exists('opcache_reset')) {
        opcache_reset();
    }
    clearstatcache(true);

    respond(200, [
        'status' => 'success',
        'message' => 'Deployment completed.',
        'from' => $before,
        'to' => $target,
        'output' => $output,
    ]);
} catch (Throwable $exception) {
    error_log('[medyclix deploy] ' . $exception->getMessage());
    respond(500, ['status' => 'error', 'message' => $exception->getMessage()]);
} finally {
    if (is_resource($lockHandle)) {
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
    }
}
