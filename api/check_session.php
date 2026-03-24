<?php
require_once __DIR__ . '/../db.php';

session_start();
validateSession();

echo json_encode(["status" => "ok", "user_id" => (int)$_SESSION['user_id']]);
