<?php
require '../includes/auth_check.php';
checkRole(['employee']);
require '../config.php';
require_once __DIR__ . '/../includes/sidebar.php';

$employee_id = $_SESSION['user_id'];

// FETCH HR LIST
$hrs = $conn->query("SELECT id, full_name FROM users WHERE role='hr'");

// HANDLE SUBMIT
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $hr_id = $_POST['hr_id'];
    $type = $_POST['leave_type'];
    $start = $_POST['start_date'];
    $end = $_POST['end_date'];
    $reason = $_POST['reason'];

    $stmt = $conn->prepare("
        INSERT INTO leave_requests 
        (employee_id, hr_id, leave_type, start_date, end_date, reason)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param("iissss", $employee_id, $hr_id, $type, $start, $end, $reason);

    if ($stmt->execute()) {
        $success = "Leave request sent successfully!";
    } else {
        $error = "Error: " . $conn->error;
    }
}

$notifQuery = $conn->query("
    SELECT * FROM notifications 
    WHERE user_id = $employee_id 
    ORDER BY id DESC
");

$notifCount = $conn->query("
    SELECT COUNT(*) as total 
    FROM notifications 
    WHERE user_id = $employee_id AND is_read = 0
")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="shortcut icon" href="images/logo.png" type="image/x-icon">
<title>Employee Dashboard</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="request_leave.css">
</head>

<body>

<?php renderSidebar('employee', 'employee.leave_request'); ?>

<!-- MAIN -->
<div class="main">

  <div class="topbar">
    <h1>Employee Dashboard</h1>
    <div>Welcome, <?php echo $_SESSION['name']; ?></div>
    <div class="icons">
      <div class="icons notification" style="position:relative;">

  <i class="fa-solid fa-bell" onclick="toggleNotif()"></i>

  <?php if($notifCount > 0): ?>
    <span style="
      position:absolute;
      top:-5px;
      right:40px;
      background:red;
      color:white;
      font-size:10px;
      padding:3px 6px;
      border-radius:50%;
    ">
      <?= $notifCount ?>
    </span>
  <?php endif; ?>

  <!-- DROPDOWN -->
  <div id="notifBox" style="
      display:none;
      position:absolute;
      right:0;
      top:30px;
      width:300px;
      background:#111827;
      border-radius:10px;
      box-shadow:0 10px 25px rgba(0,0,0,0.5);
      max-height:300px;
      overflow-y:auto;
      z-index:999;
  ">

    <?php if($notifQuery->num_rows > 0): ?>
      <?php while($n = $notifQuery->fetch_assoc()): ?>
        <div style="
            padding:10px;
            border-bottom:1px solid #1f2937;
            font-size:13px;
        ">
          <?= $n['message']; ?><br>
          <small style="color:gray;">
            <?= date("M d, h:i A", strtotime($n['created_at'])); ?>
          </small>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <div style="padding:10px;">No notifications</div>
    <?php endif; ?>

  </div>

</div>
    </div>
  </div>

    <!-- CARDS -->
    <div class="cards">
      <div class="card">
        <h3>Tasks</h3>
        <?php
        $result = $conn->query("SELECT COUNT(*) as total FROM tasks WHERE uploaded_by = $employee_id");
        $data = $result->fetch_assoc();
        ?>
        <h1><?php echo $data['total']; ?></h1>
        <i class="fa-solid fa-tasks"></i>
      </div>

      <div class="card">
        <h3>My Payroll</h3>
        <h1>8</h1>
        <i class="fa-solid fa-money-bill"></i>
      </div>

      <div class="card">
        <h3>Leaves Remaining</h3>
        <h1>15</h1>
        <i class="fa-solid fa-clock"></i>
      </div>

      <div class="card">
        <h3>System Logs</h3>
        <h1>25</h1>
        <i class="fa-solid fa-database"></i>
      </div>
    </div>

  <!-- REQUEST LEAVE FORM -->
  <div class="leave-container">
    <div class="leave-card">

      <h2 style="text-align:center; margin-bottom:15px;">Request Leave</h2>

      <?php if (!empty($success)): ?>
        <div class="success"><?php echo $success; ?></div>
      <?php endif; ?>

      <?php if (!empty($error)): ?>
        <div class="error"><?php echo $error; ?></div>
      <?php endif; ?>

      <form method="POST">

        <select name="hr_id" required>
          <option value="">Select HR</option>
          <?php while($hr = $hrs->fetch_assoc()): ?>
            <option value="<?= $hr['id']; ?>">
              <?= $hr['full_name']; ?>
            </option>
          <?php endwhile; ?>
        </select>

        <select name="leave_type" required>
          <option value="">Leave Type</option>
          <option value="Sick Leave">Sick Leave</option>
          <option value="Vacation Leave">Vacation Leave</option>
        </select>

        <input type="date" name="start_date" required>
        <input type="date" name="end_date" required>

        <textarea name="reason" placeholder="Reason" required></textarea>

        <button type="submit">Submit Request</button>

      </form>

    </div>
  </div>

</div>
<script>
function toggleNotif() {
    let box = document.getElementById("notifBox");
    box.style.display = (box.style.display === "block") ? "none" : "block";

    // mark as read when opened
    if (box.style.display === "block") {
        fetch('mark_read.php');
    }
}
</script>
</body>
</html>
