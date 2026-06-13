<?php
require_once __DIR__ . '/../includes/auth_check.php';
checkRole(['hr']);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/task_schema.php';

ensureTaskSchema($conn);

$hr_id = (int) $_SESSION['user_id'];
$task_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($task_id <= 0) die("Invalid Task ID");

// Get task details
$result = $conn->query("
    SELECT 
        t.id,
        t.title,
        t.description,
        t.employee_id,
        t.hr_id,
        t.deadline,
        t.submission_file,
        t.submitted_at,
        t.status,
        t.hr_score,
        t.hr_feedback,
        t.ai_score,
        t.ai_feedback,
        u.full_name AS employee_name,
        u.employee_id AS emp_id
    FROM tasks t
    JOIN users u ON t.employee_id = u.id
    WHERE t.id = $task_id AND t.hr_id = $hr_id
");

if (!$result || $result->num_rows == 0) die("Task not found or unauthorized");
$task = $result->fetch_assoc();

// Handle form submission
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hr_score = isset($_POST['hr_score']) ? (int) $_POST['hr_score'] : 0;
    $hr_feedback = isset($_POST['hr_feedback']) ? trim($_POST['hr_feedback']) : '';

    // Validate score
    if ($hr_score < 0 || $hr_score > 100) {
        $message = '<div class="alert error">Score must be between 0 and 100</div>';
    } else {
        // Update task with HR score
        $update = $conn->prepare("
            UPDATE tasks
            SET hr_score = ?, 
                hr_feedback = ?,
                status = 'reviewed'
            WHERE id = ? AND hr_id = ?
        ");
        
        $update->bind_param("isii", $hr_score, $hr_feedback, $task_id, $hr_id);
        
        if ($update->execute()) {
            $message = '<div class="alert success">✓ Score saved successfully</div>';
            $task['hr_score'] = $hr_score;
            $task['hr_feedback'] = $hr_feedback;
        } else {
            $message = '<div class="alert error">Error saving score. Please try again.</div>';
        }
    }
}

$filePath = "../uploads/submissions/" . $task['submission_file'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Score Task Submission</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="dashboard.css">

<style>
.main { margin-left: 240px; padding: 20px; }

.header {
    margin-bottom: 25px;
}

.header h1 {
    font-size: 28px;
    color: #e5e7eb;
    margin-bottom: 8px;
}

.header p {
    color: #9ca3af;
    font-size: 14px;
}

.content-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

@media (max-width: 1200px) {
    .content-grid {
        grid-template-columns: 1fr;
    }
}

.card {
    background: #111827;
    border: 1px solid #1f2937;
    border-radius: 12px;
    padding: 20px;
}

.card h3 {
    color: #e5e7eb;
    font-size: 16px;
    margin-top: 0;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.card h3 i {
    color: #3251ff;
}

.info-section {
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #1f2937;
}

.info-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.info-label {
    color: #9ca3af;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 4px;
}

.info-value {
    color: #e5e7eb;
    font-size: 14px;
}

.ai-score-display {
    background: #0b1220;
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 15px;
}

.score-value {
    font-size: 32px;
    font-weight: bold;
    color: #3251ff;
}

.score-label {
    color: #9ca3af;
    font-size: 12px;
    margin-top: 5px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    color: #9ca3af;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 8px;
}

.score-input-container {
    display: flex;
    align-items: center;
    gap: 15px;
}

.score-input-container input {
    flex: 1;
    padding: 10px;
    background: #0b1220;
    border: 1px solid #1f2937;
    border-radius: 8px;
    color: #e5e7eb;
    font-size: 14px;
}

.score-input-container input:focus {
    outline: none;
    border-color: #3251ff;
}

.score-display-inline {
    font-size: 24px;
    font-weight: bold;
    color: #3251ff;
    min-width: 50px;
    text-align: center;
}

textarea {
    width: 100%;
    padding: 12px;
    background: #0b1220;
    border: 1px solid #1f2937;
    border-radius: 8px;
    color: #e5e7eb;
    font-size: 14px;
    min-height: 120px;
    resize: vertical;
}

textarea:focus {
    outline: none;
    border-color: #3251ff;
}

.form-actions {
    display: flex;
    gap: 12px;
    margin-top: 20px;
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    cursor: pointer;
    transition: 0.2s;
}

.btn-primary {
    background: #3251ff;
    color: white;
    flex: 1;
}

.btn-primary:hover {
    background: #2540cc;
}

.btn-secondary {
    background: transparent;
    color: #60a5fa;
    border: 1px solid #1f2937;
}

.btn-secondary:hover {
    background: #111827;
    border-color: #3251ff;
}

.alert {
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.alert.success {
    background: #14532d;
    color: #4ade80;
}

.alert.error {
    background: #7f1d1d;
    color: #fecaca;
}

.download-section {
    background: #0b1220;
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 15px;
}

.download-section a {
    color: #60a5fa;
    text-decoration: none;
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.download-section a:hover {
    text-decoration: underline;
}
</style>
</head>

<body>

<?php renderSidebar('hr', 'hr.task_submissions'); ?>

<div class="main">

<div class="header">
    <h1><i class="fa-solid fa-star"></i> Score Task Submission</h1>
    <p>Evaluate and provide feedback on employee's work</p>
</div>

<?php if (!empty($message)) echo $message; ?>

<div class="content-grid">
    <!-- LEFT: Task Information -->
    <div>
        <div class="card">
            <h3><i class="fa-solid fa-file-lines"></i> Task Details</h3>

            <div class="info-section">
                <div class="info-label">Employee</div>
                <div class="info-value">
                    <?= htmlspecialchars($task['employee_name']); ?>
                    <?php if ($task['emp_id']): ?>
                        <br><span style="color:#9ca3af;font-size:12px;"><?= htmlspecialchars($task['emp_id']); ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="info-section">
                <div class="info-label">Task Title</div>
                <div class="info-value"><?= htmlspecialchars($task['title']); ?></div>
            </div>

            <div class="info-section">
                <div class="info-label">Description</div>
                <div class="info-value" style="white-space:pre-wrap;">
                    <?= htmlspecialchars($task['description']); ?>
                </div>
            </div>

            <div class="info-section">
                <div class="info-label">Deadline</div>
                <div class="info-value"><?= date("M d, Y", strtotime($task['deadline'])) ?></div>
            </div>

            <div class="info-section">
                <div class="info-label">Submitted</div>
                <div class="info-value">
                    <?= $task['submitted_at'] ? date("M d, Y h:i A", strtotime($task['submitted_at'])) : '<span style="color:#9ca3af;">Not submitted</span>' ?>
                </div>
            </div>

            <?php if (!empty($task['submission_file'])): ?>
            <div class="info-section">
                <div class="info-label">Submission</div>
                <div class="download-section">
                    <a href="../uploads/submissions/<?= htmlspecialchars($task['submission_file']) ?>" target="_blank">
                        <i class="fa-solid fa-download"></i>
                        Download File
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($task['ai_score']): ?>
        <div class="card" style="margin-top:20px;">
            <h3><i class="fa-solid fa-robot"></i> AI Evaluation (Reference)</h3>

            <div class="ai-score-display">
                <div class="score-value"><?= (int) $task['ai_score'] ?></div>
                <div class="score-label">Overall AI Score</div>
            </div>

            <div class="info-section" style="margin-bottom:0;padding-bottom:0;border-bottom:none;">
                <div class="info-label">AI Feedback</div>
                <div class="info-value" style="white-space:pre-wrap;font-size:12px;">
                    <?= htmlspecialchars($task['ai_feedback']); ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- RIGHT: Scoring Form -->
    <div>
        <div class="card">
            <h3><i class="fa-solid fa-pen-to-square"></i> HR Score</h3>

            <form method="POST">
                <div class="form-group">
                    <label for="hr_score">Score (0-100)</label>
                    <div class="score-input-container">
                        <input type="range" id="scoreRange" min="0" max="100" 
                               value="<?= $task['hr_score'] !== null ? (int) $task['hr_score'] : 0 ?>"
                               onchange="updateScoreDisplay()">
                        <input type="number" id="hr_score" name="hr_score" min="0" max="100"
                               value="<?= $task['hr_score'] !== null ? (int) $task['hr_score'] : 0 ?>"
                               onchange="syncScoreInputs()">
                        <div class="score-display-inline" id="scoreDisplay">
                            <?= $task['hr_score'] !== null ? (int) $task['hr_score'] : 0 ?>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="hr_feedback">Feedback & Comments</label>
                    <textarea id="hr_feedback" name="hr_feedback" placeholder="Provide constructive feedback to the employee..."><?= htmlspecialchars($task['hr_feedback'] ?? '') ?></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-save"></i> Save Score
                    </button>
                    <a href="task_submissions.php" class="btn btn-secondary">
                        <i class="fa-solid fa-arrow-left"></i> Back
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

</div>

<script>
function updateScoreDisplay() {
    const range = document.getElementById('scoreRange').value;
    document.getElementById('hr_score').value = range;
    document.getElementById('scoreDisplay').textContent = range;
}

function syncScoreInputs() {
    const input = document.getElementById('hr_score').value;
    document.getElementById('scoreRange').value = input;
    document.getElementById('scoreDisplay').textContent = input;
}
</script>

</body>
</html>
