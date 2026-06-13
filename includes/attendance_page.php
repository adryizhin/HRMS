<?php
declare(strict_types=1);

require_once __DIR__ . '/attendance.php';
require_once __DIR__ . '/sidebar.php';

function renderAttendancePage(mysqli $conn, string $role, bool $showAll): void
{
    ensureAttendanceTable($conn);
    ensureUserShiftColumn($conn);

    $currentUserId = (int) $_SESSION['user_id'];
    $summary = attendanceSummary($conn, $showAll ? null : $currentUserId);
    $mySummary = attendanceSummary($conn, $currentUserId);

    if ($showAll) {
        $records = $conn->query("
            SELECT a.*, u.full_name, u.role, u.employee_id, u.work_shift
            FROM attendance a
            JOIN users u ON a.user_id = u.id
            ORDER BY a.attendance_date DESC, a.login_time DESC
        ");
    } else {
        $stmt = $conn->prepare("
            SELECT a.*, u.full_name, u.role, u.employee_id, u.work_shift
            FROM attendance a
            JOIN users u ON a.user_id = u.id
            WHERE a.user_id = ?
            ORDER BY a.attendance_date DESC, a.login_time DESC
        ");
        $stmt->bind_param("i", $currentUserId);
        $stmt->execute();
        $records = $stmt->get_result();
    }

    $activeRoute = $role . '.attendance';
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Attendance</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?= htmlspecialchars($role === 'admin' ? 'dashboard.css' : 'dashboard.css'); ?>">
<style>
body {
    background:#0b1220;
    color:#e5e7eb;
}
.main {
    margin-left:240px;
    width:calc(100% - 240px);
    padding:20px;
}
.sidebar a.active {
    background:#22c55e;
    color:#000;
}
.cards {
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(200px,1fr));
    gap:18px;
    margin:22px 0;
}
.card {
    background:#111827;
    border:1px solid #1f2937;
    border-radius:12px;
    padding:18px;
}
.card h3 {
    color:#9ca3af;
    font-size:13px;
    margin-bottom:8px;
}
.card h1 {
    color:#3251ff;
    font-size:30px;
}
.attendance-note {
    color:#9ca3af;
    font-size:13px;
    margin-top:6px;
}
.table-wrap {
    background:#111827;
    border:1px solid #1f2937;
    border-radius:12px;
    overflow-x:auto;
}
table {
    width:100%;
    border-collapse:collapse;
}
th, td {
    border-bottom:1px solid #1f2937;
    padding:12px;
    text-align:left;
    font-size:13px;
}
th {
    color:#9ca3af;
    font-weight:600;
}
.badge {
    border-radius:999px;
    display:inline-block;
    font-size:12px;
    padding:5px 9px;
    text-transform:uppercase;
}
.present { background:#14532d; color:#86efac; }
.late { background:#78350f; color:#fcd34d; }
.absent { background:#7f1d1d; color:#fecaca; }
</style>
</head>
<body>
<?php renderSidebar($role, $activeRoute); ?>

<div class="main">
    <h1>Attendance</h1>
    <p class="attendance-note">
        Morning: present until 8:15 AM, late after 8:15 AM, absent from 12:00 PM onward.
        Night: present until 8:15 PM, late after 8:15 PM, absent from 12:00 AM onward.
    </p>

    <div class="cards">
        <div class="card">
            <h3>Total Records</h3>
            <h1><?= $summary['total']; ?></h1>
        </div>
        <div class="card">
            <h3>Present</h3>
            <h1><?= $summary['present']; ?></h1>
        </div>
        <div class="card">
            <h3>Late</h3>
            <h1><?= $summary['late']; ?></h1>
        </div>
        <div class="card">
            <h3>Absent</h3>
            <h1><?= $summary['absent']; ?></h1>
        </div>
        <?php if ($showAll): ?>
            <div class="card">
                <h3>My Absences</h3>
                <h1><?= $mySummary['absent']; ?></h1>
            </div>
        <?php endif; ?>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <?php if ($showAll): ?>
                        <th>Name</th>
                        <th>Role</th>
                    <?php endif; ?>
                    <th>Date</th>
                    <th>Shift</th>
                    <th>Login Time</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($records && $records->num_rows > 0): ?>
                    <?php while ($row = $records->fetch_assoc()): ?>
                        <tr>
                            <?php if ($showAll): ?>
                                <td><?= htmlspecialchars($row['full_name']); ?></td>
                                <td><?= strtoupper(htmlspecialchars($row['role'])); ?></td>
                            <?php endif; ?>
                            <td><?= date("M d, Y", strtotime($row['attendance_date'])); ?></td>
                            <td><?= ucfirst(htmlspecialchars($row['shift'])); ?></td>
                            <td><?= date("h:i A", strtotime($row['login_time'])); ?></td>
                            <td><span class="badge <?= htmlspecialchars($row['status']); ?>"><?= htmlspecialchars($row['status']); ?></span></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?= $showAll ? 6 : 4; ?>" style="text-align:center;color:#9ca3af;">No attendance records yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
<?php
}
?>
