<?php
require_once __DIR__ . '/../db.php';

session_start();
checkAdminAccess('super_admin');

$pdo = getPDO();
$limit = min(100, max(1, (int)($_GET['limit'] ?? 50)));
$status = trim($_GET['status'] ?? 'all');

if ($status !== 'all' && in_array($status, ['success', 'failed', 'blocked', 'disabled'], true)) {
    $stmt = $pdo->prepare("SELECT id, username, ip_address, device_type, browser, os, status, reason, created_at FROM login_attempt_logs WHERE status = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->execute([$status, $limit]);
} else {
    $stmt = $pdo->prepare("SELECT id, username, ip_address, device_type, browser, os, status, reason, created_at FROM login_attempt_logs ORDER BY created_at DESC LIMIT ?");
    $stmt->execute([$limit]);
}
$attempts = $stmt->fetchAll();

$cutoff24h = date('Y-m-d H:i:s', strtotime('-24 hours'));
$failedStmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempt_logs WHERE status = 'failed' AND created_at >= ?");
$failedStmt->execute([$cutoff24h]);
$failedCount = (int)$failedStmt->fetchColumn();
$blockedStmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempt_logs WHERE status = 'blocked' AND created_at >= ?");
$blockedStmt->execute([$cutoff24h]);
$blockedCount = (int)$blockedStmt->fetchColumn();
$successStmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempt_logs WHERE status = 'success' AND created_at >= ?");
$successStmt->execute([$cutoff24h]);
$successCount = (int)$successStmt->fetchColumn();

jsonResponse([
    'success'  => true,
    'attempts' => $attempts,
    'stats_24h' => [
        'failed'  => $failedCount,
        'blocked' => $blockedCount,
        'success' => $successCount,
    ],
    'csrf_token' => generateCsrfToken(),
]);
