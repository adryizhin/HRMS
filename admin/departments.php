<?php
require '../config.php';
require '../includes/auth_check.php';
require_once __DIR__ . '/../includes/sidebar.php';
checkRole(['admin']);

  /* =========================
    ACTIONS
  ========================= */
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {

      if (isset($_POST['add'])) {
          $name = trim($_POST['name']);
          $stmt = $conn->prepare("INSERT INTO departments (name) VALUES (?)");
          $stmt->bind_param("s", $name);
          $stmt->execute();
      }

      if (isset($_POST['edit'])) {
          $stmt = $conn->prepare("UPDATE departments SET name=? WHERE id=?");
          $stmt->bind_param("si", $_POST['name'], $_POST['id']);
          $stmt->execute();
      }

      if (isset($_POST['delete'])) {
          $stmt = $conn->prepare("DELETE FROM departments WHERE id=?");
          $stmt->bind_param("i", $_POST['id']);
          $stmt->execute();
      }

      if (isset($_POST['assign'])) {
          $stmt = $conn->prepare("UPDATE users SET department_id=? WHERE id=?");
          $stmt->bind_param("ii", $_POST['dept_id'], $_POST['user_id']);
          $stmt->execute();
      }

      header("Location: departments.php");
      exit();
  }

  /* =========================
    DATA
  ========================= */
  $departments = $conn->query("SELECT * FROM departments")->fetch_all(MYSQLI_ASSOC);
  $users = $conn->query("SELECT * FROM users")->fetch_all(MYSQLI_ASSOC);

  $employees = $conn->query("
      SELECT users.*, departments.name AS dept_name
      FROM users
      LEFT JOIN departments ON users.department_id = departments.id
  ")->fetch_all(MYSQLI_ASSOC);
  ?>

  <!DOCTYPE html>
  <html>
  <head>
  <title>Departments</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="department.css">
  </head>

  <body>

  <?php renderSidebar('admin', 'admin.departments'); ?>

  <div class="main">

  <div class="page-title">Departments Management</div>

  <div class="grid">

    <div class="card">
      <h3>Add Department</h3>
      <form method="POST">
        <input type="text" name="name" required placeholder = "Add Department...">
        <button class="btn btn-success" name="add">Add Department</button>
      </form>
    </div>

  </div>

  <div class="card" style="margin-top:20px;">

  <table>
  <tr>
    <th>Department</th>
    <th>Actions</th>
  </tr>

  <?php foreach ($departments as $d): ?>
  <tr>
    <td><?= htmlspecialchars($d['name']) ?></td>

    <td>
      <div class="actions">

        <button class="btn btn-primary"
          onclick="openEdit(<?= $d['id'] ?>,'<?= htmlspecialchars($d['name'], ENT_QUOTES) ?>')">
          Edit
        </button>

        <button class="btn btn-danger"
          onclick="openDelete(<?= $d['id'] ?>,'<?= htmlspecialchars($d['name'], ENT_QUOTES) ?>')">
          Delete
        </button>

        <button class="btn btn-warning"
          onclick="openDetails(<?= $d['id'] ?>)">
          Details
        </button>

      </div>
    </td>
  </tr>
  <?php endforeach; ?>

  </table>

  </div>

  </div>

  <!-- EDIT -->
  <div id="editModal" class="modal">
    <div class="modal-content">
      <h3>Edit Department</h3>
      <form method="POST">
        <input type="hidden" name="id" id="edit_id">
        <input type="text" name="name" id="edit_name" required>
        <button class="btn btn-success" name="edit">Update</button>
      </form>
    </div>
  </div>

  <!-- DELETE -->
  <div id="deleteModal" class="modal">
    <div class="modal-content">
      <h3>Confirm Delete</h3>
      <p id="delete_text"></p>

      <form method="POST">
        <input type="hidden" name="id" id="delete_id">

        <div class="modal-actions">
          <button type="button" class="btn btn-warning" onclick="closeDelete()">Cancel</button>
          <button class="btn btn-danger" name="delete">Yes Delete</button>
        </div>
      </form>
    </div>
  </div>

  <!-- DETAILS -->
  <div id="detailsModal" class="modal">
    <div class="modal-content">
      <h3>Department Employees</h3>
      <div class="emp-list" id="empList"></div>
    </div>
  </div>

  <script>

  let employees = <?= json_encode($employees); ?>;

  /* EDIT */
  function openEdit(id, name){
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('editModal').style.display = 'flex';
  }

  /* DELETE */
  function openDelete(id, name){
    document.getElementById('delete_id').value = id;
    document.getElementById('delete_text').innerText =
      "Are you sure you want to delete '" + name + "' department?";
    document.getElementById('deleteModal').style.display = 'flex';
  }

  function closeDelete(){
    document.getElementById('deleteModal').style.display = 'none';
  }

  /* DETAILS */
  function openDetails(id){
    let list = "";

    employees.forEach(e => {
      if(e.department_id == id){
        list += "<p>" + e.full_name + "</p>";
      }
    });

    if(list === "") list = "<p>No employees found</p>";

    document.getElementById('empList').innerHTML = list;
    document.getElementById('detailsModal').style.display = 'flex';
  }

  /* CLOSE MODAL */
  window.onclick = function(e){
    if(e.target.classList.contains('modal')){
      e.target.style.display = 'none';
    }
  }

  </script>

  </body>
  </html>
