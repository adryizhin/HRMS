<?php
require '../includes/auth_check.php';
checkRole(['hr']);
require '../config.php';
require_once __DIR__ . '/../includes/attendance_page.php';

renderAttendancePage($conn, 'hr', true);
?>
