<?php
require '../includes/auth_check.php';
checkRole(['employee']);
require '../config.php';
require_once __DIR__ . '/../includes/attendance_page.php';

renderAttendancePage($conn, 'employee', false);
?>
