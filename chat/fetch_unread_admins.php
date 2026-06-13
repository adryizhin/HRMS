<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/session.php';

if (!isset($_SESSION['user_id'])) {
    exit;
}

$user_id = (int) $_SESSION['user_id'];

// Get all admins who have sent unread messages to this HR
$stmt = $conn->prepare("
    SELECT DISTINCT u.id, u.full_name, 
           COUNT(CASE WHEN cm.status != 'seen' THEN 1 END) as unread_count
    FROM users u
    INNER JOIN chat_messages cm ON u.id = cm.sender_id
    WHERE u.role = 'admin' 
    AND cm.receiver_id = ?
    GROUP BY u.id, u.full_name
    ORDER BY MAX(cm.created_at) DESC
");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$admins = [];
while ($row = $result->fetch_assoc()) {
    $admins[] = $row;
}

echo json_encode($admins);
