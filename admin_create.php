<?php
require_once __DIR__ . '/config.php';

function createUser($employee_id, $name, $email, $password, $role, $department_id = null) {
    global $conn;

    $hashed = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("
        INSERT INTO users (employee_id, full_name, email, password, role, department_id)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("sssssi", $employee_id, $name, $email, $hashed, $role, $department_id);

    return $stmt->execute();
}
?>
