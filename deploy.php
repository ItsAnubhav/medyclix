<?php
/**
 * Web-triggered deployment endpoint.
 *
 * Server setup:
 *   1. Set MEDYCLIX_DEPLOY_TOKEN to a long random secret.
 *   2. Ensure the web/PHP user can run Git and write to this repository.
 *   3. Ensure origin authentication is configured if the repository is private.
 *   4. Call: https://your-domain.example/deploy.php?token=YOUR_SECRET
 *
 * HTTPS is required because query-string tokens may otherwise be intercepted.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

const DEPLOY_REMOTE = 'origin';
const DEPLOY_BRANCH = 'master';
const DEPLOY_REPOSITORY = 'https://github.com/ItsAnubhav/medyclix.git';

$repositoryPath = __DIR__;
$deploymentToken = getenv('MEDYCLIX_DEPLOY_TOKEN');
$providedToken = $_SERVER['HTTP_X_DEPLOY_TOKEN'] ?? ($_GET['token'] ?? '');

function respond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function runGit(string $repositoryPath, array $arguments): string
{
    $command = 'git -C ' . escapeshellarg($repositoryPath);
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

if (!is_string($deploymentToken) || strlen($deploymentToken) < 32) {
    respond(500, [
        'status' => 'error',
        'message' => 'MEDYCLIX_DEPLOY_TOKEN is missing or shorter than 32 characters.',
    ]);
}

if (!is_string($providedToken) || !hash_equals($deploymentToken, $providedToken)) {
    respond(401, ['status' => 'error', 'message' => 'Unauthorized.']);
}

if (!is_dir($repositoryPath . DIRECTORY_SEPARATOR . '.git')) {
    respond(500, ['status' => 'error', 'message' => 'deploy.php is not located in the Git repository root.']);
}

$lockPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'medyclix-deploy.lock';
$lockHandle = fopen($lockPath, 'c');
if ($lockHandle === false || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
    respond(409, ['status' => 'error', 'message' => 'Another deployment is already running.']);
}

try {
    $remoteUrl = rtrim(runGit($repositoryPath, ['remote', 'get-url', DEPLOY_REMOTE]), '/');
    if ($remoteUrl !== rtrim(DEPLOY_REPOSITORY, '/')) {
        throw new RuntimeException("Unexpected origin URL: {$remoteUrl}");
    }

    $currentBranch = runGit($repositoryPath, ['branch', '--show-current']);
    if ($currentBranch !== DEPLOY_BRANCH) {
        throw new RuntimeException("Expected branch " . DEPLOY_BRANCH . ", found {$currentBranch}.");
    }

    // Ignore untracked runtime files, but never overwrite tracked local changes.
    $trackedChanges = runGit($repositoryPath, ['status', '--porcelain', '--untracked-files=no']);
    if ($trackedChanges !== '') {
        throw new RuntimeException('Tracked local changes exist; deployment was stopped.');
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
