<?php
require_once __DIR__ . '/../db.php';

session_start();
checkAdminAccess('super_admin');

$pdo = getPDO();

$contacts = $pdo->query("SELECT * FROM contact_messages ORDER BY created_at DESC")->fetchAll();
$unreadCount = (int)$pdo->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0")->fetchColumn();

jsonResponse([
    'success' => true,
    'contacts' => $contacts,
    'unread_count' => $unreadCount,
    'csrf_token' => generateCsrfToken(),
]);
