<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/login_logger.php';
require_once __DIR__ . '/includes/session.php';

$user_id = $_SESSION['user_id'] ?? null;
$token = $_SESSION['session_token'] ?? null;

if ($user_id && $token) {
    logLogout($conn, (int) $user_id, $token, 'timeout');
}

$_SESSION = [];
session_destroy();

header('Location: ' . route('login') . '?timeout=1');
exit();
?>
