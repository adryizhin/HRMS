<?php
require_once __DIR__ . '/../includes/auth_check.php';
checkRole(['hr']);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/task_schema.php';

ensureTaskSchema($conn);

$hr_id = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit;
}

/* =========================
   VALIDATION
========================= */
$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$deadline = trim($_POST['deadline'] ?? '');
$employee_ids = $_POST['employee_ids'] ?? [];

if ($title === '' || $description === '' || $deadline === '' || empty($employee_ids) || !is_array($employee_ids)) {
    header("Location: dashboard.php?task_error=1");
    exit;
}

$deadlineDate = DateTime::createFromFormat('Y-m-d', $deadline);
if (!$deadlineDate || $deadlineDate->format('Y-m-d') !== $deadline) {
    header("Location: dashboard.php?task_error=1");
    exit;
}

/* =========================
   FILE UPLOAD (OPTIONAL)
========================= */
$file_name = null;
$file_path = null;
$file_type = null;

if (!empty($_FILES['file']['name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {

    $uploadDir = __DIR__ . "/../uploads/tasks/";

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $original = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($_FILES['file']['name']));
    $file_name = time() . "_" . $original;
    $target_path = $uploadDir . $file_name;
    $file_path = "uploads/tasks/" . $file_name;
    $file_type = $_FILES['file']['type'] ?: pathinfo($file_name, PATHINFO_EXTENSION);

    if (!move_uploaded_file($_FILES['file']['tmp_name'], $target_path)) {
        header("Location: dashboard.php?task_error=1");
        exit;
    }
}

/* =========================
   ASSIGNMENT BATCH ID
========================= */
$batch_id = uniqid("batch_");

/* =========================
   INSERT TASKS PER EMPLOYEE
========================= */
$stmt = $conn->prepare("
    INSERT INTO tasks (
        assignment_batch,
        title,
        description,
        deadline,
        employee_id,
        hr_id,
        file_attachment,
        file_name,
        file_path,
        file_type,
        uploaded_by,
        status,
        created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
");

if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$success_count = 0;

foreach ($employee_ids as $emp_id) {

    $emp_id = (int) $emp_id;
    if ($emp_id <= 0) {
        continue;
    }

    $stmt->bind_param(
        "ssssiissssi",
        $batch_id,
        $title,
        $description,
        $deadline,
        $emp_id,
        $hr_id,
        $file_name,
        $file_name,
        $file_path,
        $file_type,
        $hr_id
    );

    if ($stmt->execute()) {
        $success_count++;
    }
}

$stmt->close();

/* =========================
   REDIRECT BACK
========================= */
header("Location: dashboard.php?task_sent=" . $success_count);
exit;
