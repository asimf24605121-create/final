<?php
require_once __DIR__ . '/../db.php';

session_start();
checkAdminAccess('super_admin');

$pdo = getPDO();

$cutoff = date('Y-m-d H:i:s', strtotime('-' . SESSION_INACTIVITY_TIMEOUT_MINUTES . ' minutes'));
$pdo->prepare("UPDATE user_sessions SET status = 'inactive', logout_reason = 'Session expired due to inactivity' WHERE status = 'active' AND last_activity < ? AND logout_reason IS NULL")->execute([$cutoff]);

$stmt = $pdo->query("
    SELECT
        s.id,
        s.user_id,
        u.username,
        u.name AS user_name,
        s.device_id,
        s.ip_address,
        s.device_type,
        s.browser,
        s.os,
        s.status,
        s.last_activity,
        s.created_at
    FROM user_sessions s
    LEFT JOIN users u ON u.id = s.user_id
    WHERE s.status = 'active'
    ORDER BY s.last_activity DESC
    LIMIT 100
");
$sessions = $stmt->fetchAll();

$suspicious = [];
foreach ($sessions as &$s) {
    $s['is_suspicious'] = false;
    $check = $pdo->prepare("SELECT COUNT(DISTINCT ip_address) as ip_count FROM user_sessions WHERE user_id = ? AND status = 'active'");
    $check->execute([$s['user_id']]);
    $ipCount = (int)$check->fetchColumn();
    if ($ipCount > 2) {
        $s['is_suspicious'] = true;
        $s['suspicious_reason'] = 'Multiple IPs active';
    }
}
unset($s);

jsonResponse([
    'success'    => true,
    'sessions'   => $sessions,
    'csrf_token' => generateCsrfToken(),
]);
