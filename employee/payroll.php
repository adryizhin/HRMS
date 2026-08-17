<?php
require '../includes/auth_check.php';
checkRole(['employee']);
require '../config.php';
require_once __DIR__ . '/../includes/payslip_page.php';

renderPayslipPage($conn, 'employee');
?>
