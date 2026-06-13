<?php
require_once __DIR__ . '/../includes/auth_check.php';
checkRole(['hr']);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/task_schema.php';

ensureTaskSchema($conn);

$hr_id = (int) $_SESSION['user_id'];

// Get all submitted tasks for this HR
$stmt = $conn->prepare("
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
        u.full_name AS employee_name,
        u.employee_id AS emp_id
    FROM tasks t
    JOIN users u ON t.employee_id = u.id
    WHERE t.hr_id = ?
    AND t.submission_file IS NOT NULL
    AND t.submission_file != ''
    ORDER BY t.submitted_at DESC
");
$stmt->bind_param("i", $hr_id);
$stmt->execute();
$submissions = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Task Submissions Review</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="dashboard.css">

<style>
.main { margin-left: 240px; padding: 20px; }

.topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
.topbar h1 { font-size: 28px; color: #e5e7eb; }

.submissions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 18px;
    margin-top: 20px;
}

.submission-card {
    background: #111827;
    border: 1px solid #1f2937;
    border-radius: 12px;
    padding: 18px;
    transition: 0.2s;
}

.submission-card:hover {
    transform: translateY(-4px);
    border-color: rgba(50, 81, 255, 0.4);
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 12px;
}

.card-header h3 {
    color: #3251ff;
    font-size: 16px;
    margin: 0;
}

.status-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-badge.pending { background: #374151; color: #9ca3af; }
.status-badge.submitted { background: #14532d; color: #4ade80; }
.status-badge.reviewed { background: #1e3a8a; color: #60a5fa; }
.status-badge.scored { background: #4c1d95; color: #c4b5fd; }

.employee-info {
    color: #9ca3af;
    font-size: 13px;
    margin-bottom: 10px;
}

.task-meta {
    display: flex;
    flex-direction: column;
    gap: 6px;
    color: #cbd5e1;
    font-size: 12px;
    margin-bottom: 12px;
}

.task-meta i { margin-right: 6px; color: #60a5fa; }

.score-display {
    background: #0b1220;
    padding: 10px;
    border-radius: 8px;
    margin-bottom: 12px;
    text-align: center;
}

.score-display .score-value {
    font-size: 28px;
    font-weight: bold;
    color: #3251ff;
}

.score-display .score-label {
    font-size: 11px;
    color: #9ca3af;
}

.action-buttons {
    display: flex;
    gap: 8px;
    margin-top: 12px;
}

.btn-view, .btn-score {
    flex: 1;
    padding: 8px 12px;
    border: none;
    border-radius: 6px;
    font-size: 12px;
    cursor: pointer;
    transition: 0.2s;
    text-decoration: none;
    display: inline-block;
    text-align: center;
}

.btn-view {
    background: #3251ff;
    color: white;
}

.btn-view:hover {
    background: #2540cc;
}

.btn-score {
    background: #1f2937;
    color: #60a5fa;
    border: 1px solid #1f2937;
}

.btn-score:hover {
    background: #111827;
    border-color: #3251ff;
}

.no-submissions {
    text-align: center;
    color: #9ca3af;
    padding: 40px;
}

.filter-tabs {
    display: flex;
    gap: 12px;
    margin-bottom: 20px;
    border-bottom: 1px solid #1f2937;
    padding-bottom: 15px;
}

.filter-tab {
    padding: 8px 16px;
    background: transparent;
    border: none;
    color: #9ca3af;
    cursor: pointer;
    font-size: 14px;
    border-bottom: 2px solid transparent;
    transition: 0.2s;
}

.filter-tab.active {
    color: #3251ff;
    border-bottom-color: #3251ff;
}
</style>
</head>

<body>

<?php renderSidebar('hr', 'hr.task_submissions'); ?>

<div class="main">

<div class="topbar">
    <h1><i class="fa-solid fa-clipboard-check"></i> Task Submissions</h1>
</div>

<?php if ($submissions && $submissions->num_rows > 0): ?>

<div class="submissions-grid">
    <?php while ($task = $submissions->fetch_assoc()): 
        $statusClass = $task['hr_score'] !== null ? 'scored' : ($task['status'] === 'reviewed' ? 'reviewed' : ($task['status'] === 'submitted' ? 'submitted' : 'pending'));
        $statusLabel = $task['hr_score'] !== null ? 'Scored' : ucfirst((string) $task['status']);
    ?>
        <div class="submission-card">
            <div class="card-header">
                <h3><?= htmlspecialchars($task['title']); ?></h3>
                <span class="status-badge <?= $statusClass ?>">
                    <?= htmlspecialchars($statusLabel); ?>
                </span>
            </div>

            <div class="employee-info">
                👤 <strong><?= htmlspecialchars($task['employee_name']); ?></strong>
                <?php if ($task['emp_id']): ?>
                    <br><span style="font-size:11px;"><?= htmlspecialchars($task['emp_id']); ?></span>
                <?php endif; ?>
            </div>

            <div class="task-meta">
                <div><i class="fa-solid fa-calendar"></i> Deadline: <?= $task['deadline'] ? date("M d, Y", strtotime($task['deadline'])) : 'No deadline' ?></div>
                <?php if ($task['submitted_at']): ?>
                    <div><i class="fa-solid fa-clock"></i> Submitted: <?= date("M d, Y h:i A", strtotime($task['submitted_at'])) ?></div>
                <?php endif; ?>
            </div>

            <?php if ($task['hr_score'] !== null): ?>
                <div class="score-display">
                    <div class="score-value" style="color: <?= $task['hr_score'] >= 70 ? '#22c55e' : ($task['hr_score'] >= 50 ? '#f59e0b' : '#ef4444') ?>">
                        <?= (int) $task['hr_score'] ?>%
                    </div>
                    <div class="score-label">HR Score</div>
                </div>
            <?php endif; ?>

            <div class="action-buttons">
                <a href="view_submission.php?id=<?= $task['id'] ?>" class="btn-view">
                    <i class="fa-solid fa-eye"></i> View
                </a>
                <a href="score_submission.php?id=<?= $task['id'] ?>" class="btn-score">
                    <i class="fa-solid fa-star"></i> <?= $task['hr_score'] !== null ? 'Edit' : 'Score' ?>
                </a>
            </div>
        </div>
    <?php endwhile; ?>
</div>

<?php else: ?>

<div class="no-submissions">
    <div style="font-size: 48px; margin-bottom: 15px;">📭</div>
    <p>No task submissions to review yet.</p>
    <p style="font-size: 12px; margin-top: 10px;">Employees will submit their work here.</p>
</div>

<?php endif; ?>

</div>

</body>
</html>
