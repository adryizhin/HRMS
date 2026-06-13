<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once __DIR__ . '/routes.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/login_logger.php';

$timeout_duration = 900;

function checkRole(array $allowedRoles = []): void
{
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowedRoles, true)) {
        header('Location: ' . route('login'));
        exit();
    }
}

if (!isset($_SESSION['user_id'], $_SESSION['session_token'])) {
    header('Location: ' . route('login'));
    exit();
}

$stmt = $conn->prepare("
    SELECT last_ping
    FROM login_logs
    WHERE user_id = ?
    AND session_token = ?
    AND logout_time IS NULL
    ORDER BY id DESC
    LIMIT 1
");
$stmt->bind_param("is", $_SESSION['user_id'], $_SESSION['session_token']);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row) {
    session_destroy();
    header('Location: ' . route('login'));
    exit();
}

$last_ping = strtotime($row['last_ping']);
if (!$last_ping || time() - $last_ping > $timeout_duration) {
    logLogout($conn, (int) $_SESSION['user_id'], $_SESSION['session_token'], 'timeout');

    session_unset();
    session_destroy();

    header('Location: ' . route('login'));
    exit();
}

$update = $conn->prepare("
    UPDATE login_logs
    SET last_ping = NOW()
    WHERE user_id = ?
    AND session_token = ?
    AND logout_time IS NULL
");
$update->bind_param("is", $_SESSION['user_id'], $_SESSION['session_token']);
$update->execute();
?>
