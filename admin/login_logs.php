<?php
date_default_timezone_set('Asia/Manila');
require '../config.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/sidebar.php';
checkRole(['admin']);

/* CLEAN DUPLICATE ACTIVE SESSIONS */
if (isset($_POST['cleanup_duplicates'])) {

    $query = "
    DELETE l1 FROM login_logs l1
    INNER JOIN login_logs l2 
    ON l1.user_id = l2.user_id 
    AND l1.id < l2.id
    WHERE l1.logout_time IS NULL 
    AND l2.logout_time IS NULL
    ";

    $conn->query($query);

    header("Location: login_logs.php");
    exit();
}

/* EXPORT TO CSV (EXCEL FORMAT) */
if (isset($_POST['export_excel'])) {

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=login_logs.csv');

    $output = fopen('php://output', 'w');

    // headers
    fputcsv($output, [
        'Name',
        'Role',
        'Login Time',
        'Logout Time',
        'Logout Reason',
        'Status'
    ]);

    $query = "SELECT * FROM login_logs ORDER BY id DESC";
    $result = $conn->query($query);

    while ($row = $result->fetch_assoc()) {

        $reason = $row['logout_reason'] ?? '';
        $status = !$row['logout_time'] ? 'Active' : ($reason === 'timeout' ? 'Timed Out' : 'Inactive');

        fputcsv($output, [
            $row['username'],
            strtoupper($row['role']),
            $row['login_time'],
            $row['logout_time'] ?? 'Still Logged In',
            $reason === 'timeout' ? 'Timeout' : ($reason === 'logout' ? 'Logout' : ''),
            $status
        ]);
    }

    fclose($output);
    exit();
}

/* FETCH LOGS */
$query = "SELECT * FROM login_logs ORDER BY id DESC";
$logs = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login Logs</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="login_logs.css">
</head>

<body>

<?php renderSidebar('admin', 'admin.login_logs'); ?>

<!-- MAIN -->
<div class="main">

<h1>Login Logs</h1>
<br>

<div class="top-actions">

    <!-- CLEAN DUPLICATES -->
    <form method="POST" onsubmit="return confirm('Clean duplicate active sessions?');">
        <button name="cleanup_duplicates" class="clean-btn">
            <i class="fa-solid fa-broom"></i> Clean Sessions
        </button>
    </form>

    <!-- EXPORT -->
    <form method="POST">
        <button name="export_excel" class="clean-btn export-btn">
            <i class="fa-solid fa-file-excel"></i> Export to Excel
        </button>
    </form>

</div>

<table class="table">
<thead>
<tr>
  <th>Name</th>
  <th>Role</th>
  <th>Login Time</th>
  <th>Logout Time</th>
  <th>Logout Reason</th>
  <th>Status</th>
</tr>
</thead>

<tbody>
<?php while($row = $logs->fetch_assoc()): ?>
<tr>
  <td><?= htmlspecialchars($row['username']); ?></td>

  <td>
    <span class="badge <?= $row['role']; ?>">
      <?= strtoupper($row['role']); ?>
    </span>
  </td>

  <td><?= date("M d, Y h:i A", strtotime($row['login_time'])); ?></td>

  <td style="text-align:center;">
    <?= $row['logout_time']
         ? date("M d, Y h:i A", strtotime($row['logout_time']))
         : 'Still Login';
    ?>
  </td>

  <td style="text-align:center;">
    <?php if (($row['logout_reason'] ?? '') === 'timeout'): ?>
        Time Out
    <?php elseif (($row['logout_reason'] ?? '') === 'logout'): ?>
        Logout
    <?php else: ?>
        -
    <?php endif; ?>
  </td>

  <td>
    <?php if (($row['logout_reason'] ?? '') === 'timeout'): ?>
        <span class="status inactive">Timed Out</span>
    <?php elseif ($row['logout_time']): ?>
        <span class="status inactive">Inactive</span>
    <?php else: ?>
        <span class="status active">Active</span>
    <?php endif; ?>
  </td>

</tr>
<?php endwhile; ?>
</tbody>

</table>

</div>

</body>
</html>
