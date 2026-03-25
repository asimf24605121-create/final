<?php
require_once __DIR__ . '/../db.php';

session_start();
checkAdminAccess('super_admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}

validateCsrfToken();

$input = json_decode(file_get_contents('php://input'), true);
$id = (int)($input['id'] ?? 0);
$action = $input['action'] ?? '';

if ($id <= 0) {
    jsonResponse(['success' => false, 'message' => 'ID is required.'], 400);
}

if (!in_array($action, ['read', 'delete', 'reply'], true)) {
    jsonResponse(['success' => false, 'message' => 'Invalid action.'], 400);
}

$pdo = getPDO();

if ($action === 'read') {
    $pdo->prepare("UPDATE contact_messages SET is_read = 1, status = 'read' WHERE id = ?")->execute([$id]);
    jsonResponse(['success' => true, 'message' => 'Marked as read.']);
} elseif ($action === 'reply') {
    $replyMessage = trim($input['reply_message'] ?? '');
    if ($replyMessage === '') {
        jsonResponse(['success' => false, 'message' => 'Reply message cannot be empty.'], 400);
    }

    $stmt = $pdo->prepare("SELECT whatsapp_number, name FROM contact_messages WHERE id = ?");
    $stmt->execute([$id]);
    $contact = $stmt->fetch();

    if (!$contact) {
        jsonResponse(['success' => false, 'message' => 'Message not found.'], 404);
    }

    $pdo->prepare("UPDATE contact_messages SET is_read = 1, status = 'replied', admin_reply = ? WHERE id = ?")->execute([$replyMessage, $id]);

    logActivity($_SESSION['user_id'], "contact_replied: id={$id} name={$contact['name']}", getClientIP());

    $waNumber = preg_replace('/[^0-9]/', '', $contact['whatsapp_number'] ?? '');
    $waUrl = '';
    if ($waNumber) {
        $waUrl = 'https://wa.me/' . $waNumber . '?text=' . rawurlencode($replyMessage);
    }

    jsonResponse([
        'success' => true,
        'message' => 'Reply saved and status updated.',
        'whatsapp_url' => $waUrl,
    ]);
} else {
    $pdo->prepare("DELETE FROM contact_messages WHERE id = ?")->execute([$id]);
    logActivity($_SESSION['user_id'], "contact_deleted: id={$id}", getClientIP());
    jsonResponse(['success' => true, 'message' => 'Message deleted.']);
}
