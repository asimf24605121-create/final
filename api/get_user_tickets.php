<?php
require_once __DIR__ . '/../db.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized.'], 401);
}

$pdo = getPDO();
$userId = (int)$_SESSION['user_id'];

$tickets = $pdo->prepare("SELECT id, platform_name, message, status, created_at, resolved_at FROM support_tickets WHERE user_id = ? ORDER BY created_at DESC");
$tickets->execute([$userId]);
$rows = $tickets->fetchAll();

$pendingPlatforms = [];
foreach ($rows as $r) {
    if ($r['status'] === 'pending') {
        $pendingPlatforms[] = $r['platform_name'];
    }
}

jsonResponse([
    'success' => true,
    'tickets' => $rows,
    'pending_platforms' => $pendingPlatforms,
]);
