<?php
require '../config.php';
require '../includes/auth_check.php';
require_once __DIR__ . '/../includes/sidebar.php';

checkRole(['admin']);

/* =========================
   ADD USER
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name        = $_POST['full_name'] ?? '';
    $email       = $_POST['email'] ?? '';
    $password    = $_POST['password'] ?? '';
    $role        = $_POST['role'] ?? '';
    $department_id = !empty($_POST['department_id']) ? (int) $_POST['department_id'] : null;
    $work_shift = ($_POST['work_shift'] ?? 'morning') === 'night' ? 'night' : 'morning';

    // Auto-generate employee_id
    $last = $conn->query("SELECT MAX(id) AS max_id FROM users")->fetch_assoc();
    $employee_id = 'EMP-' . str_pad(($last['max_id'] ?? 0) + 1, 5, '0', STR_PAD_LEFT);

    $hashed = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("
        INSERT INTO users (employee_id, full_name, email, password, role, department_id, work_shift)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param("sssssis", $employee_id, $name, $email, $hashed, $role, $department_id, $work_shift);

    if ($stmt->execute()) {
        header("Location: create_user_account.php");
        exit();
    } else {
        $error = $stmt->error;
    }
}

/* =========================
   DELETE USER
========================= */
if (isset($_GET['delete'])) {

    $id = (int) $_GET['delete'];

    if (isset($_SESSION['user_id']) && $id == $_SESSION['user_id']) {
        $error = "You cannot delete your own account.";
    } else {

        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            header("Location: create_user_account.php?deleted=1");
            exit();
        } else {
            $error = $stmt->error;
        }
    }
}

/* =========================
   FETCH USERS
========================= */
$users = $conn->query("
    SELECT users.*, departments.name AS department_name
    FROM users
    LEFT JOIN departments ON users.department_id = departments.id
    ORDER BY users.id DESC
");

$departments = $conn->query("
    SELECT id, name FROM departments ORDER BY name ASC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="shortcut icon" href="images/logo.png" type="image/x-icon">
<title>Pagecom HRIS</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="create_user_account.css">
</head>

<body>

<?php renderSidebar('admin', 'admin.users'); ?>

<div class="main">
<div class="container">

<!-- FORM -->
<div class="form-card">
  <h2>Create User Account</h2>

  <?php if (!empty($error)): ?>
    <div style="background:#ef4444;padding:10px;border-radius:8px;margin-bottom:10px;">
      <?= $error; ?>
    </div>
  <?php endif; ?>

  <form method="POST">

    
    <div class="form-group">
      <input type="text" name="full_name" placeholder="Full Name" required>
    </div>

    <div class="form-group">
      <input type="email" name="email" placeholder="Email" required>
    </div>

    <div class="form-group">
      <input type="password" name="password" placeholder="Password" required>
    </div>

    <div class="form-group">
      <select name="role" required>
        <option value="">Select Role</option>
        <option value="admin">Admin</option>
        <option value="hr">HR</option>
        <option value="employee">Employee</option>
      </select>
    </div>

    <div class="form-group">
      <select name="department_id">
        <option value="">Select Department</option>
        <?php while ($department = $departments->fetch_assoc()): ?>
          <option value="<?= $department['id']; ?>">
            <?= htmlspecialchars($department['name']); ?>
          </option>
        <?php endwhile; ?>
      </select>
    </div>

    <div class="form-group">
      <select name="work_shift" required>
        <option value="morning">Morning Shift</option>
        <option value="night">Night Shift</option>
      </select>
    </div>

    <button type="submit">Create Account</button>
  </form>
</div>

<!-- TABLE -->
<div class="table-card">
  <br>
  <h2>Pagecom's Employees List</h2>
  <br>

  <?php if (isset($_GET['deleted'])): ?>
    <div style="background:#22c55e;padding:10px;border-radius:8px;margin-bottom:10px;">
      User deleted successfully!
    </div>
  <?php endif; ?>

  <div class="table-wrapper">

    <table>
      <tr>
        <th>Name</th>
        <th>Email</th>
        <th>Role</th>
        <th>Department</th>
        <th>Shift</th>
        <th>Action</th>
      </tr>

      <?php while ($row = $users->fetch_assoc()): ?>
      <tr>
        <td><?= htmlspecialchars($row['full_name']); ?></td>
        <td><?= htmlspecialchars($row['email']); ?></td>
        <td>
          <span class="badge <?= $row['role']; ?>">
            <?= strtoupper($row['role']); ?>
          </span>
        </td>
        <td><?= htmlspecialchars($row['department_name'] ?? 'Unassigned'); ?></td>
        <td><?= ucfirst(htmlspecialchars($row['work_shift'] ?? 'morning')); ?></td>
        <td>
          <a class="delete-btn"
             href="?delete=<?= $row['id']; ?>"
             onclick="return confirm('Delete this user?')">
             Delete
          </a>
        </td>
      </tr>
      <?php endwhile; ?>

    </table>

  </div>

</div>

</div>
</div>

</body>
</html>