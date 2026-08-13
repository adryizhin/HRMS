<?php
require_once __DIR__ . '/../includes/auth_check.php';
checkRole(['hr']);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/employee_documents.php';
require_once __DIR__ . '/../includes/attendance.php';
require_once __DIR__ . '/../includes/task_schema.php';

ensureEmployeeDocumentsTable($conn);
ensureAttendanceTable($conn);
ensureTaskSchema($conn);

$hr_id = (int) $_SESSION['user_id'];
$selectedEmployeeId = isset($_GET['employee_id']) ? (int) $_GET['employee_id'] : 0;
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $documentId = isset($_POST['document_id']) ? (int) $_POST['document_id'] : 0;
    $status = $_POST['status'] ?? '';
    $hrNotes = trim($_POST['hr_notes'] ?? '');

    if ($documentId > 0 && in_array($status, ['submitted', 'verified', 'rejected'], true)) {
        $stmt = $conn->prepare("
            UPDATE employee_documents
            SET status = ?,
                hr_notes = ?,
                reviewed_by = ?,
                reviewed_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("ssii", $status, $hrNotes, $hr_id, $documentId);

        if ($stmt->execute()) {
            $redirectEmployee = isset($_POST['employee_id']) ? (int) $_POST['employee_id'] : 0;
            header("Location: 201_files.php?employee_id={$redirectEmployee}&updated=1");
            exit;
        }

        $message = '<div class="alert error">Unable to update document status.</div>';
    }
}

$employees = $conn->query("
    SELECT
        u.id,
        u.employee_id,
        u.full_name,
        u.email,
        u.status,
        d.name AS department_name,
        COUNT(ed.id) AS document_count,
        SUM(ed.status = 'verified') AS verified_count,
        SUM(ed.status = 'submitted') AS pending_count
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.id
    LEFT JOIN employee_documents ed ON ed.employee_id = u.id
    WHERE u.role = 'employee'
    GROUP BY u.id, u.employee_id, u.full_name, u.email, u.status, d.name
    ORDER BY u.full_name ASC
");

$selectedEmployee = null;
$documents = null;
$attendance = ['present' => 0, 'late' => 0, 'absent' => 0, 'total' => 0];
$taskStats = ['assigned' => 0, 'submitted' => 0, 'reviewed' => 0, 'average_score' => null];
$leaveRecords = null;

if ($selectedEmployeeId > 0) {
    $stmt = $conn->prepare("
        SELECT u.*, d.name AS department_name
        FROM users u
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE u.id = ? AND u.role = 'employee'
    ");
    $stmt->bind_param("i", $selectedEmployeeId);
    $stmt->execute();
    $selectedEmployee = $stmt->get_result()->fetch_assoc();

    if ($selectedEmployee) {
        $documentsStmt = $conn->prepare("
            SELECT ed.*, reviewer.full_name AS reviewer_name
            FROM employee_documents ed
            LEFT JOIN users reviewer ON ed.reviewed_by = reviewer.id
            WHERE ed.employee_id = ?
            ORDER BY ed.uploaded_at DESC
        ");
        $documentsStmt->bind_param("i", $selectedEmployeeId);
        $documentsStmt->execute();
        $documents = $documentsStmt->get_result();

        $attendance = attendanceSummary($conn, $selectedEmployeeId);

        $taskStmt = $conn->prepare("
            SELECT
                COUNT(*) AS assigned,
                SUM(submission_file IS NOT NULL AND submission_file != '') AS submitted,
                SUM(status = 'reviewed') AS reviewed,
                AVG(hr_score) AS average_score
            FROM tasks
            WHERE employee_id = ?
        ");
        $taskStmt->bind_param("i", $selectedEmployeeId);
        $taskStmt->execute();
        $taskRow = $taskStmt->get_result()->fetch_assoc() ?: [];
        $taskStats = [
            'assigned' => (int) ($taskRow['assigned'] ?? 0),
            'submitted' => (int) ($taskRow['submitted'] ?? 0),
            'reviewed' => (int) ($taskRow['reviewed'] ?? 0),
            'average_score' => $taskRow['average_score'] !== null ? (float) $taskRow['average_score'] : null,
        ];

        $leaveStmt = $conn->prepare("
            SELECT leave_type, start_date, end_date, status
            FROM leave_requests
            WHERE employee_id = ?
            ORDER BY id DESC
            LIMIT 5
        ");
        $leaveStmt->bind_param("i", $selectedEmployeeId);
        $leaveStmt->execute();
        $leaveRecords = $leaveStmt->get_result();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>201 Files</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="dashboard.css">
<style>
.files-layout {
  display: grid;
  gap: 18px;
  grid-template-columns: minmax(270px, 340px) minmax(0, 1fr);
}
.panel {
  background: #111827;
  border: 1px solid #1f2937;
  border-radius: 8px;
  padding: 16px;
}
.panel h2 { color: #f8fafc; font-size: 18px; margin-bottom: 14px; }
.employee-list { display: grid; gap: 9px; max-height: 690px; overflow-y: auto; }
.employee-item {
  background: #0b1220;
  border: 1px solid #263244;
  border-radius: 8px;
  color: #e5e7eb;
  display: block;
  padding: 12px;
  text-decoration: none;
}
.employee-item.active, .employee-item:hover { border-color: #3251ff; }
.employee-item strong { display: block; font-size: 14px; }
.employee-item span { color: #94a3b8; display: block; font-size: 12px; margin-top: 3px; }
.file-header {
  align-items: flex-start;
  display: flex;
  justify-content: space-between;
  gap: 14px;
  margin-bottom: 16px;
}
.file-header h1 { color: #f8fafc; font-size: 26px; }
.file-header p { color: #94a3b8; font-size: 14px; margin-top: 5px; }
.summary-grid {
  display: grid;
  gap: 12px;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  margin-bottom: 16px;
}
.summary-card {
  background: #0b1220;
  border: 1px solid #263244;
  border-radius: 8px;
  padding: 13px;
}
.summary-card span { color: #94a3b8; display: block; font-size: 12px; margin-bottom: 6px; }
.summary-card strong { color: #60a5fa; display: block; font-size: 24px; }
.doc-grid { display: grid; gap: 13px; }
.doc-card {
  background: #0b1220;
  border: 1px solid #263244;
  border-radius: 8px;
  padding: 14px;
}
.doc-head {
  align-items: flex-start;
  display: flex;
  gap: 12px;
  justify-content: space-between;
}
.doc-card h3 { color: #f8fafc; font-size: 15px; }
.doc-card p { color: #94a3b8; font-size: 13px; line-height: 1.45; margin-top: 7px; }
.badge {
  border-radius: 999px;
  display: inline-flex;
  font-size: 11px;
  font-weight: 800;
  padding: 6px 9px;
  text-transform: uppercase;
}
.green { background: rgba(34, 197, 94, 0.13); color: #86efac; }
.red { background: rgba(239, 68, 68, 0.13); color: #fca5a5; }
.blue { background: rgba(96, 165, 250, 0.13); color: #93c5fd; }
.gray { background: #1f2937; color: #cbd5e1; }
.doc-link {
  color: #bfdbfe;
  display: inline-flex;
  gap: 8px;
  margin-top: 10px;
  text-decoration: none;
}
.doc-link:hover { text-decoration: underline; }
.review-form {
  border-top: 1px solid #263244;
  display: grid;
  gap: 10px;
  grid-template-columns: minmax(130px, 170px) minmax(0, 1fr) auto;
  margin-top: 13px;
  padding-top: 13px;
}
.review-form select,
.review-form input {
  background: #111827;
  border: 1px solid #263244;
  border-radius: 8px;
  color: #e5e7eb;
  padding: 9px 10px;
}
.review-form button {
  background: #3251ff;
  border: 0;
  border-radius: 8px;
  color: #fff;
  cursor: pointer;
  font-weight: 700;
  padding: 9px 12px;
}
.records-list { display: grid; gap: 9px; }
.record-row {
  align-items: center;
  background: #0b1220;
  border: 1px solid #263244;
  border-radius: 8px;
  color: #cbd5e1;
  display: flex;
  justify-content: space-between;
  gap: 12px;
  padding: 11px;
}
.empty {
  border: 1px dashed #334155;
  border-radius: 8px;
  color: #94a3b8;
  padding: 28px;
  text-align: center;
}
.alert { border-radius: 8px; margin-bottom: 12px; padding: 10px 12px; }
.alert.success { background: #14532d; color: #bbf7d0; }
.alert.error { background: #7f1d1d; color: #fecaca; }
@media (max-width: 980px) {
  .files-layout { grid-template-columns: 1fr; }
  .review-form { grid-template-columns: 1fr; }
}
</style>
</head>
<body>
<?php renderSidebar('hr', 'hr.201_files'); ?>

<div class="main">
  <div class="files-layout">
    <aside class="panel">
      <h2><i class="fa-solid fa-users"></i> Employees</h2>
      <div class="employee-list">
        <?php if ($employees && $employees->num_rows > 0): ?>
          <?php while ($employee = $employees->fetch_assoc()): ?>
            <a class="employee-item <?= (int) $employee['id'] === $selectedEmployeeId ? 'active' : ''; ?>" href="201_files.php?employee_id=<?= (int) $employee['id']; ?>">
              <strong><?= htmlspecialchars($employee['full_name']); ?></strong>
              <span><?= htmlspecialchars($employee['employee_id'] ?: 'No employee ID'); ?></span>
              <span><?= htmlspecialchars($employee['department_name'] ?: 'Unassigned'); ?></span>
              <span><?= (int) $employee['document_count']; ?> docs • <?= (int) $employee['verified_count']; ?> verified • <?= (int) $employee['pending_count']; ?> pending</span>
            </a>
          <?php endwhile; ?>
        <?php else: ?>
          <div class="empty">No employees found.</div>
        <?php endif; ?>
      </div>
    </aside>

    <main>
      <?php if (isset($_GET['updated'])): ?>
        <div class="alert success"><i class="fa-solid fa-circle-check"></i> Document review updated.</div>
      <?php endif; ?>
      <?php if ($message !== '') echo $message; ?>

      <?php if ($selectedEmployee): ?>
        <div class="file-header">
          <div>
            <p class="eyebrow">201 File</p>
            <br>
            <h1><?= htmlspecialchars($selectedEmployee['full_name']); ?></h1>
            <p>
              <?= htmlspecialchars($selectedEmployee['employee_id'] ?: 'No employee ID'); ?>
              • <?= htmlspecialchars($selectedEmployee['email']); ?>
              • <?= htmlspecialchars($selectedEmployee['department_name'] ?: 'Unassigned'); ?>
            </p>
          </div>
          <span class="badge gray"><?= htmlspecialchars($selectedEmployee['status'] ?? 'active'); ?></span>
        </div>

        <div class="summary-grid">
          <div class="summary-card"><span>Attendance Records</span><strong><?= (int) $attendance['total']; ?></strong></div>
          <div class="summary-card"><span>Present / Late / Absent</span><strong><?= (int) $attendance['present']; ?> / <?= (int) $attendance['late']; ?> / <?= (int) $attendance['absent']; ?></strong></div>
          <div class="summary-card"><span>Assigned Tasks</span><strong><?= (int) $taskStats['assigned']; ?></strong></div>
          <div class="summary-card"><span>Average HR Score</span><strong><?= $taskStats['average_score'] === null ? 'N/A' : number_format((float) $taskStats['average_score'], 1) . '%'; ?></strong></div>
        </div>

        <section class="panel" style="margin-bottom:16px;">
          <h2><i class="fa-solid fa-folder-open"></i> Documents Collected</h2>
          <div class="doc-grid">
            <?php if ($documents && $documents->num_rows > 0): ?>
              <?php while ($document = $documents->fetch_assoc()): ?>
                <article class="doc-card">
                  <div class="doc-head">
                    <div>
                      <h3><?= htmlspecialchars($document['title']); ?></h3>
                      <p><?= htmlspecialchars($document['document_type']); ?> • Uploaded <?= date("M d, Y h:i A", strtotime($document['uploaded_at'])); ?></p>
                    </div>
                    <span class="badge <?= htmlspecialchars(documentStatusClass($document['status'])); ?>"><?= htmlspecialchars($document['status']); ?></span>
                  </div>
                  <?php if (!empty($document['notes'])): ?>
                    <p><strong style="color:#cbd5e1;">Employee note:</strong> <?= nl2br(htmlspecialchars($document['notes'])); ?></p>
                  <?php endif; ?>
                  <?php if (!empty($document['hr_notes'])): ?>
                    <p><strong style="color:#cbd5e1;">HR note:</strong> <?= nl2br(htmlspecialchars($document['hr_notes'])); ?></p>
                  <?php endif; ?>
                  <?php if (!empty($document['reviewer_name'])): ?>
                    <p>Reviewed by <?= htmlspecialchars($document['reviewer_name']); ?><?= $document['reviewed_at'] ? ' on ' . date("M d, Y h:i A", strtotime($document['reviewed_at'])) : ''; ?></p>
                  <?php endif; ?>
                  <a class="doc-link" href="../<?= htmlspecialchars($document['file_path']); ?>" target="_blank">
                    <i class="fa-solid fa-download"></i> View / Download File
                  </a>

                  <form class="review-form" method="POST">
                    <input type="hidden" name="document_id" value="<?= (int) $document['id']; ?>">
                    <input type="hidden" name="employee_id" value="<?= (int) $selectedEmployeeId; ?>">
                    <select name="status" aria-label="Document status">
                      <option value="submitted" <?= $document['status'] === 'submitted' ? 'selected' : ''; ?>>Submitted</option>
                      <option value="verified" <?= $document['status'] === 'verified' ? 'selected' : ''; ?>>Verified</option>
                      <option value="rejected" <?= $document['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                    <input type="text" name="hr_notes" value="<?= htmlspecialchars($document['hr_notes'] ?? ''); ?>" placeholder="HR notes">
                    <button type="submit"><i class="fa-solid fa-save"></i> Save</button>
                  </form>
                </article>
              <?php endwhile; ?>
            <?php else: ?>
              <div class="empty">This employee has not submitted documents yet.</div>
            <?php endif; ?>
          </div>
        </section>

        <section class="panel">
          <h2><i class="fa-solid fa-clock-rotate-left"></i> Recent Leave Records</h2>
          <div class="records-list">
            <?php if ($leaveRecords && $leaveRecords->num_rows > 0): ?>
              <?php while ($leave = $leaveRecords->fetch_assoc()): ?>
                <div class="record-row">
                  <span><?= htmlspecialchars($leave['leave_type']); ?> • <?= date("M d, Y", strtotime($leave['start_date'])); ?> to <?= date("M d, Y", strtotime($leave['end_date'])); ?></span>
                  <span class="badge <?= htmlspecialchars(documentStatusClass($leave['status'] === 'approved' ? 'verified' : ($leave['status'] === 'rejected' ? 'rejected' : 'submitted'))); ?>"><?= htmlspecialchars($leave['status']); ?></span>
                </div>
              <?php endwhile; ?>
            <?php else: ?>
              <div class="empty">No leave records found.</div>
            <?php endif; ?>
          </div>
        </section>
      <?php else: ?>
        <div class="panel">
          <div class="empty">Select an employee to view their 201 file.</div>
        </div>
      <?php endif; ?>
    </main>
  </div>
</div>
</body>
</html>
