<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/session.php';

if (!isset($_SESSION['user_id'], $_GET['receiver_id'])) {
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$receiver_id = (int) $_GET['receiver_id'];

/* =========================
   MARK AS DELIVERED
   (messages sent to me but not yet delivered)
========================= */
$delivered = $conn->prepare("
    UPDATE chat_messages 
    SET status = 'delivered'
    WHERE receiver_id = ?
    AND status = 'sent'
");
$delivered->bind_param("i", $user_id);
$delivered->execute();

/* =========================
   FETCH MESSAGES
========================= */
$stmt = $conn->prepare("
SELECT * FROM chat_messages
WHERE 
    (sender_id = ? AND receiver_id = ?)
    OR
    (sender_id = ? AND receiver_id = ?)
ORDER BY created_at ASC
");
$stmt->bind_param("iiii", $user_id, $receiver_id, $receiver_id, $user_id);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {

    $class = ($row['sender_id'] == $user_id) ? 'user' : 'bot';

    /* =========================
       MARK AS SEEN
       (only if I am receiver and message is visible)
    ========================= */
    if ($row['receiver_id'] == $user_id && $row['status'] != 'seen') {
        $seen = $conn->prepare("
            UPDATE chat_messages 
            SET status = 'seen', seen_at = NOW()
            WHERE id = ?
        ");
        $seen->bind_param("i", $row['id']);
        $seen->execute();
    }

    echo "<div class='msg $class'>";
    echo htmlspecialchars($row['message']);

    /* STATUS LABEL (for sender only) */
    if ($row['sender_id'] == $user_id) {

        echo "<div style='font-size:10px; opacity:0.7; margin-top:3px;'>";

        if ($row['status'] == 'seen') {
            echo "Seen ✔";
        } elseif ($row['status'] == 'delivered') {
            echo "Delivered ✔";
        } else {
            echo "Sent";
        }

        echo "</div>";
    }

    echo "</div>";
}
