<?php
require __DIR__ . '/../db.php';

$pdo = getPDO();
$today = date('Y-m-d');

$stmt = $pdo->prepare("UPDATE user_subscriptions SET is_active = 0 WHERE is_active = 1 AND end_date < ?");
$stmt->execute([$today]);
$expired = $stmt->rowCount();

echo date('Y-m-d H:i:s') . " - Expired {$expired} subscription(s).\n";
