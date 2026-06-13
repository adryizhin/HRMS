<?php
require_once __DIR__ . '/../includes/auth_check.php';
checkRole(['employee']);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/task_schema.php';

ensureTaskSchema($conn);

$id      = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$user_id = (int) $_SESSION['user_id'];

if ($id <= 0) {
    header("Location: dashboard.php");
    exit;
}

/* VERIFY OWNERSHIP + GET ATTACHMENT */
$stmt = $conn->prepare("
    SELECT submission_file
    FROM tasks
    WHERE id = ? AND employee_id = ?
");
$stmt->bind_param("ii", $id, $user_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row) {
    die("Task not found or access denied.");
}

/* DELETE ATTACHMENT FROM SERVER */
if (!empty($row['submission_file'])) {
    $path = __DIR__ . "/../uploads/submissions/" . basename($row['submission_file']);
    if (file_exists($path)) {
        unlink($path);
    }
}

/* CLEAR SUBMISSION */
$del = $conn->prepare("
    UPDATE tasks
    SET submission_file = NULL,
        submitted_at = NULL,
        status = 'pending',
        ai_score = NULL,
        grammar_score = NULL,
        spelling_score = NULL,
        clarity_score = NULL,
        relevance_score = NULL,
        ai_feedback = NULL
    WHERE id = ? AND employee_id = ?
");
$del->bind_param("ii", $id, $user_id);
$del->execute();

header("Location: dashboard.php");
exit;
?>
