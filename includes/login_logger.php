<?php
declare(strict_types=1);

function logLogin(mysqli $conn, int $user_id, string $username, string $role, string $session_token): void
{
    $stmt = $conn->prepare("
        INSERT INTO login_logs (user_id, username, role, session_token, login_time, last_ping)
        VALUES (?, ?, ?, ?, NOW(), NOW())
    ");
    $stmt->bind_param("isss", $user_id, $username, $role, $session_token);
    $stmt->execute();
}

function ensureLoginLogReasonColumn(mysqli $conn): void
{
    static $checked = false;

    if ($checked) {
        return;
    }

    $result = $conn->query("SHOW COLUMNS FROM login_logs LIKE 'logout_reason'");

    if ($result && $result->num_rows === 0) {
        $conn->query("
            ALTER TABLE login_logs
            ADD COLUMN logout_reason VARCHAR(30) DEFAULT NULL AFTER logout_time
        ");
    }

    $checked = true;
}

function logLogout(mysqli $conn, int $user_id, string $session_token, string $reason = 'logout'): void
{
    ensureLoginLogReasonColumn($conn);

    $stmt = $conn->prepare("
        UPDATE login_logs
        SET logout_time = NOW(),
            logout_reason = ?
        WHERE user_id = ?
        AND session_token = ?
        AND logout_time IS NULL
    ");
    $stmt->bind_param("sis", $reason, $user_id, $session_token);
    $stmt->execute();
}
?>
