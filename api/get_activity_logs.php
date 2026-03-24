<?php
require_once __DIR__ . '/../db.php';

session_start();

checkAdminAccess('super_admin');

$pdo = getPDO();

$limit = min(100, max(1, (int)($_GET['limit'] ?? 50)));

$logs = $pdo->prepare("
    SELECT al.id, al.action, al.ip_address, al.created_at, u.username
    FROM activity_logs al
    LEFT JOIN users u ON u.id = al.user_id
    ORDER BY al.created_at DESC
    LIMIT ?
");
$logs->execute([$limit]);

jsonResponse([
    'success' => true,
    'logs'    => $logs->fetchAll(),
]);
