<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/session.php';

if (!isset($_SESSION['user_id'])) exit;

$user_id = (int) $_SESSION['user_id'];
$sender_id = (int) $_POST['receiver_id'];

/* Mark only messages that:
   - were sent by receiver
   - and received by current user
   - and NOT yet seen
*/
$stmt = $conn->prepare("
    UPDATE chat_messages 
    SET is_seen = 1,
        status = 'seen',
        seen_at = NOW()
    WHERE sender_id = ?
    AND receiver_id = ?
    AND status != 'seen'
");
$stmt->bind_param("ii", $sender_id, $user_id);
$stmt->execute();
