<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit("invalid");
}

if (!isset($_SESSION['user_id'])) {
    exit("unauthorized");
}

$sender_id = (int) $_SESSION['user_id'];
$receiver_id = (int) $_POST['receiver_id'];
$message = trim($_POST['message']);

if ($message === '') {
    exit("empty");
}

/* OPTIONAL: allow only valid roles if you store it */
if (isset($_SESSION['role'])) {
    $allowed_roles = ['admin', 'hr', 'user'];
    if (!in_array($_SESSION['role'], $allowed_roles)) {
        exit("forbidden");
    }
}

$status = "sent";

$stmt = $conn->prepare("
    INSERT INTO chat_messages 
    (sender_id, receiver_id, message, status, is_delivered, is_seen, created_at)
    VALUES (?, ?, ?, ?, 0, 0, NOW())
");

$stmt->bind_param("iiss", $sender_id, $receiver_id, $message, $status);

echo $stmt->execute() ? "success" : "error";

$stmt->close();