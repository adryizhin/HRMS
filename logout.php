<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/login_logger.php';
require_once __DIR__ . '/includes/session.php';

$user_id = $_SESSION['user_id'] ?? null;
$token   = $_SESSION['session_token'] ?? null;

if ($user_id && $token) {
    logLogout($conn, (int) $user_id, $token, 'logout');
}

// always destroy session
$_SESSION = [];
session_destroy();

// only redirect if NOT AJAX/beacon
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    exit(); // silent for AJAX
}

header("Location: " . route('login'));
exit();
?>
