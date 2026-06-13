<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth_check.php';

$user_id = (int) $_SESSION['user_id'];

$stmt = $conn->prepare("
    UPDATE notifications 
    SET is_read = 1 
    WHERE user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
?>
