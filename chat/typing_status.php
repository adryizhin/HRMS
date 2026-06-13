<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/session.php';

if (!isset($_SESSION['user_id'], $_POST['receiver_id'], $_POST['is_typing'])) {
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$receiver_id = (int) $_POST['receiver_id'];
$is_typing = (int) $_POST['is_typing'];

/* safer approach: update LAST MESSAGE ONLY via subquery */
$stmt = $conn->prepare("
    UPDATE chat_messages
    SET is_typing = ?
    WHERE id = (
        SELECT id FROM (
            SELECT id FROM chat_messages
            WHERE sender_id = ?
            AND receiver_id = ?
            ORDER BY id DESC
            LIMIT 1
        ) AS temp
    )
");

$stmt->bind_param("iii", $is_typing, $user_id, $receiver_id);
$stmt->execute();