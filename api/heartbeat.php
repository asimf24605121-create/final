<?php
require_once __DIR__ . '/../db.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['success' => false, 'active' => false, 'status' => 'inactive', 'message' => 'No active session.', 'session_expired' => true], 401);
}

$sessionToken = $_SESSION['session_token'] ?? null;
if ($sessionToken) {
    $pdo_check = getPDO();
    $chk = $pdo_check->prepare("SELECT status, last_activity, logout_reason FROM user_sessions WHERE session_token = ?");
    $chk->execute([$sessionToken]);
    $sessRow = $chk->fetch();
    if (!$sessRow || $sessRow['status'] !== 'active') {
        $reason = $sessRow['logout_reason'] ?? 'Session terminated';
        session_destroy();
        jsonResponse(['success' => false, 'active' => false, 'status' => 'inactive', 'message' => $reason, 'session_expired' => true, 'logout_reason' => $reason], 401);
    }
    $lastActivity = strtotime($sessRow['last_activity']);
    $timeout = SESSION_INACTIVITY_TIMEOUT_MINUTES * 60;
    if ($lastActivity && (time() - $lastActivity) > $timeout) {
        $pdo_check->prepare("UPDATE user_sessions SET status = 'inactive', logout_reason = 'Session expired due to inactivity' WHERE session_token = ?")->execute([$sessionToken]);
        session_destroy();
        jsonResponse(['success' => false, 'active' => false, 'status' => 'inactive', 'message' => 'Session expired due to inactivity.', 'session_expired' => true, 'logout_reason' => 'Session expired due to inactivity'], 401);
    }
    touchSession($sessionToken);
}

$pdo    = getPDO();
$userId = (int)$_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT is_active FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user || !$user['is_active']) {
    jsonResponse(['success' => true, 'active' => false, 'status' => 'inactive']);
}

$today = date('Y-m-d');
$stmt2 = $pdo->prepare("
    SELECT COUNT(*) FROM user_subscriptions
    WHERE user_id = ? AND is_active = 1 AND end_date >= ?
");
$stmt2->execute([$userId, $today]);
$activeSubs = (int)$stmt2->fetchColumn();

if ($activeSubs === 0) {
    jsonResponse(['success' => true, 'active' => false, 'status' => 'inactive']);
}

jsonResponse([
    'success'     => true,
    'active'      => true,
    'status'      => 'active',
    'active_subs' => $activeSubs,
    'session_timeout_minutes' => SESSION_INACTIVITY_TIMEOUT_MINUTES,
]);
