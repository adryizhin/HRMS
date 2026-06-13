<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once __DIR__ . '/session.php';

function login(string $email, string $password)
{
    global $conn;

    $stmt = $conn->prepare("
        SELECT *
        FROM users
        WHERE email = ?
        AND status = 'active'
        LIMIT 1
    ");
    $stmt->bind_param("s", $email);
    $stmt->execute();

    $user = $stmt->get_result()->fetch_assoc();

    return ($user && password_verify($password, $user['password'])) ? $user : false;
}
?>
