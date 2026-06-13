<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/session.php';

if (!isset($_SESSION['user_id'], $_SESSION['session_token'])) {
    http_response_code(401);
    exit();
}

$stmt = $conn->prepare("
    UPDATE login_logs
    SET last_ping = NOW()
    WHERE user_id = ?
    AND session_token = ?
    AND logout_time IS NULL
");
$stmt->bind_param("is", $_SESSION['user_id'], $_SESSION['session_token']);
$stmt->execute();

http_response_code(204);
?>
