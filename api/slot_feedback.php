<?php
require_once __DIR__ . '/../db.php';

session_start();
validateSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$isBeacon = stripos($contentType, 'text/plain') !== false;
if (!$isBeacon) {
    validateCsrfToken();
}

$pdo = getPDO();
$userId = (int)$_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

$accountId = (int)($input['account_id'] ?? 0);
$platformId = (int)($input['platform_id'] ?? 0);
$status = ($input['status'] ?? '');

if ($accountId < 1 || $platformId < 1) {
    jsonResponse(['success' => false, 'message' => 'Missing account_id or platform_id.'], 400);
}

if (!in_array($status, ['success', 'fail'])) {
    jsonResponse(['success' => false, 'message' => 'Status must be "success" or "fail".'], 400);
}

$sessionStmt = $pdo->prepare("
    SELECT id FROM account_sessions
    WHERE user_id = ? AND account_id = ? AND platform_id = ?
    ORDER BY created_at DESC LIMIT 1
");
$sessionStmt->execute([$userId, $accountId, $platformId]);
if (!$sessionStmt->fetch()) {
    jsonResponse(['success' => false, 'message' => 'No session found for this slot.'], 403);
}

$now = date('Y-m-d H:i:s');

define('COOLDOWN_MINUTES', 10);

if ($status === 'success') {
    $pdo->prepare("
        UPDATE platform_accounts
        SET success_count = success_count + 1,
            last_success_at = ?,
            health_status = 'healthy',
            cooldown_until = NULL,
            updated_at = ?
        WHERE id = ?
    ")->execute([$now, $now, $accountId]);
} else {
    $cooldownUntil = date('Y-m-d H:i:s', strtotime('+' . COOLDOWN_MINUTES . ' minutes'));

    $pdo->prepare("
        UPDATE platform_accounts
        SET fail_count = fail_count + 1,
            last_failed_at = ?,
            cooldown_until = ?,
            updated_at = ?
        WHERE id = ?
    ")->execute([$now, $cooldownUntil, $now, $accountId]);

    $statsStmt = $pdo->prepare("SELECT success_count, fail_count FROM platform_accounts WHERE id = ?");
    $statsStmt->execute([$accountId]);
    $stats = $statsStmt->fetch();

    if ($stats) {
        $total = $stats['success_count'] + $stats['fail_count'];
        $failRate = $total > 0 ? $stats['fail_count'] / $total : 0;

        if ($failRate >= 0.7 && $total >= 5) {
            $newHealth = 'unhealthy';
        } elseif ($failRate >= 0.4 && $total >= 3) {
            $newHealth = 'degraded';
        } else {
            $newHealth = 'healthy';
        }

        $pdo->prepare("UPDATE platform_accounts SET health_status = ?, updated_at = ? WHERE id = ?")->execute([$newHealth, $now, $accountId]);

        error_log("slot_feedback: Slot#{$accountId} marked as {$newHealth} (fail_rate=" . round($failRate * 100) . "%, total={$total}), cooldown until {$cooldownUntil}");
    }

    $pdo->prepare("UPDATE account_sessions SET status = 'inactive' WHERE account_id = ? AND user_id = ? AND status = 'active'")->execute([$accountId, $userId]);
    error_log("slot_feedback: Released active sessions for failed Slot#{$accountId}");
}

error_log("slot_feedback: user#{$userId} reported {$status} for Slot#{$accountId} platform#{$platformId}");

jsonResponse([
    'success' => true,
    'message' => 'Feedback recorded.',
    'status' => $status,
]);
