<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';

if (isset($conn) && $conn instanceof mysqli) {
    return;
}

const DB_HOST = 'localhost';
const DB_USER = 'root';
const DB_PASS = '';
const DB_NAME = 'hris';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

$conn->set_charset('utf8mb4');
?>
