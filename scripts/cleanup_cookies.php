<?php
require __DIR__ . '/../db.php';

$pdo = getPDO();
$now = date('Y-m-d H:i:s');

$stmt = $pdo->prepare("DELETE FROM cookie_vault WHERE expires_at IS NOT NULL AND expires_at < ?");
$stmt->execute([$now]);
$deleted = $stmt->rowCount();

echo date('Y-m-d H:i:s') . " - Cleaned up {$deleted} expired cookie(s).\n";
