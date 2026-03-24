<?php
require __DIR__ . '/../db.php';
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin' || ($_SESSION['admin_level'] ?? '') === 'manager') {
    jsonResponse(['success' => false, 'message' => 'Unauthorized.'], 401);
}

$pdo = getPDO();

$status = $_GET['status'] ?? 'all';
$where = '';
$params = [];
if (in_array($status, ['pending', 'approved', 'rejected'])) {
    $where = 'WHERE pay.status = ?';
    $params[] = $status;
}

$stmt = $pdo->prepare("
    SELECT pay.*, p.name AS platform_name
    FROM payments pay
    JOIN platforms p ON p.id = pay.platform_id
    $where
    ORDER BY pay.created_at DESC
    LIMIT 200
");
$stmt->execute($params);
$payments = $stmt->fetchAll();

jsonResponse(['success' => true, 'payments' => $payments]);
