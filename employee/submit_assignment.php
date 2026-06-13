<?php
require_once __DIR__ . '/../includes/auth_check.php';
checkRole(['employee']);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/task_schema.php';

ensureTaskSchema($conn);

$user_id = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $assignment_id = isset($_POST['assignment_id']) ? (int) $_POST['assignment_id'] : 0;
    if ($assignment_id <= 0) {
        die("Invalid task.");
    }

    $task = $conn->prepare("SELECT id, submission_file FROM tasks WHERE id = ? AND employee_id = ?");
    $task->bind_param("ii", $assignment_id, $user_id);
    $task->execute();
    $existing = $task->get_result()->fetch_assoc();

    if (!$existing) {
        die("Task not found or access denied.");
    }

    if (!isset($_FILES['submission_file']) || $_FILES['submission_file']['error'] !== UPLOAD_ERR_OK) {
        die("No file uploaded.");
    }

    $file = $_FILES['submission_file'];

    $original = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($file["name"]));
    $fileName = time() . "_" . $user_id . "_" . $original;
    $targetDir = __DIR__ . "/../uploads/submissions/";
    $targetFile = $targetDir . $fileName;

    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    if (!move_uploaded_file($file["tmp_name"], $targetFile)) {
        die("Upload failed.");
    }

    if (!empty($existing['submission_file'])) {
        $oldPath = $targetDir . basename($existing['submission_file']);
        if (is_file($oldPath)) {
            unlink($oldPath);
        }
    }

    $stmt = $conn->prepare("
        UPDATE tasks
        SET submission_file = ?,
            submitted_at = NOW(),
            status = 'submitted',
            ai_score = NULL,
            grammar_score = NULL,
            spelling_score = NULL,
            clarity_score = NULL,
            relevance_score = NULL,
            ai_feedback = NULL
        WHERE id = ? AND employee_id = ?
    ");

    $stmt->bind_param(
        "sii",
        $fileName,
        $assignment_id,
        $user_id
    );

    if ($stmt->execute()) {
        header("Location: dashboard.php?success=1");
        exit;
    } else {
        die("Database error: " . $conn->error);
    }
}
