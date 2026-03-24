<?php
require_once __DIR__ . '/../db.php';

session_start();
checkAdminAccess('super_admin');

$pdo = getPDO();

$status = $_GET['status'] ?? 'all';

$sql = "SELECT st.*, u.username, u.name AS user_name, u.email AS user_email
        FROM support_tickets st
        LEFT JOIN users u ON u.id = st.user_id
        ORDER BY st.created_at DESC";

if ($status === 'pending') {
    $sql = "SELECT st.*, u.username, u.name AS user_name, u.email AS user_email
            FROM support_tickets st
            LEFT JOIN users u ON u.id = st.user_id
            WHERE st.status = 'pending'
            ORDER BY st.created_at DESC";
} elseif ($status === 'resolved') {
    $sql = "SELECT st.*, u.username, u.name AS user_name, u.email AS user_email
            FROM support_tickets st
            LEFT JOIN users u ON u.id = st.user_id
            WHERE st.status = 'resolved'
            ORDER BY st.created_at DESC";
}

$tickets = $pdo->query($sql)->fetchAll();

$pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status = 'pending'")->fetchColumn();

jsonResponse([
    'success' => true,
    'tickets' => $tickets,
    'pending_count' => $pendingCount,
    'csrf_token' => generateCsrfToken(),
]);
