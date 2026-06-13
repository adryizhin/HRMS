<?php
require_once __DIR__ . '/../includes/auth_check.php';
checkRole(['hr']);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/task_schema.php';

ensureTaskSchema($conn);

$hr_id = (int) $_SESSION['user_id'];

$result = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role='employee'");
$totalEmployees = $result ? $result->fetch_assoc()['total'] : 0;

$result = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role='hr'");
$totalHR = $result ? $result->fetch_assoc()['total'] : 0;

$result = $conn->query("
    SELECT COUNT(*) AS total 
    FROM tasks
    WHERE hr_id = $hr_id AND status='pending'
");
$pendingRequests = $result ? $result->fetch_assoc()['total'] : 0;

$result = $conn->query("SELECT COUNT(*) AS total FROM tasks WHERE hr_id = $hr_id");
$systemLogs = $result ? $result->fetch_assoc()['total'] : 0;

$employees = $conn->query("
    SELECT users.id, users.employee_id, users.full_name, departments.name AS department_name
    FROM users
    LEFT JOIN departments ON users.department_id = departments.id
    WHERE users.role = 'employee'
    AND users.status = 'active'
    ORDER BY users.full_name ASC
");

$taskBatches = $conn->query("
    SELECT
        COALESCE(t.assignment_batch, CONCAT('single-', t.id)) AS batch_key,
        MIN(t.id) AS sample_task_id,
        t.title,
        t.description,
        t.file_attachment,
        t.deadline,
        MIN(t.created_at) AS created_at,
        COUNT(*) AS recipient_count,
        SUM(t.status = 'pending') AS pending_count,
        SUM(t.status = 'submitted') AS submitted_count,
        SUM(t.status = 'reviewed') AS reviewed_count
    FROM tasks t
    WHERE t.hr_id = $hr_id
    GROUP BY batch_key, t.title, t.description, t.file_attachment, t.deadline
    ORDER BY created_at DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HR Dashboard</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="dashboard.css">

<style>
/* ===== CHAT AVATAR ===== */
#chatAvatar {
    position: fixed;
    bottom: 25px;
    right: 25px;
    width: 70px;
    height: 70px;
    border-radius: 50%;
    overflow: hidden;
    cursor: pointer;
    z-index: 1000;
    background: #111827;
    padding: 4px;
    box-shadow: 0 0 15px rgba(50,81,255,0.6), 0 4px 15px rgba(0,0,0,0.4);
    animation: pulse 2s infinite;
    transition: 0.2s;
}

#chatAvatar:hover {
    transform: scale(1.1);
}

#chatAvatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 50%;
}

@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(50,81,255,0.6); }
    70% { box-shadow: 0 0 0 12px rgba(50,81,255,0); }
    100% { box-shadow: 0 0 0 0 rgba(50,81,255,0); }
}

/* CHAT BOX */
#chatBox {
    position: fixed;
    bottom: 110px;
    right: 25px;
    width: 320px;
    background: #0b1220;
    border-radius: 12px;
    border: 1px solid #1f2937;
    display: none;
    flex-direction: column;
    z-index: 1000;
}

.chat-header {
    background: #111827;
    padding: 12px 14px;
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.chat-header i {
    width: 30px;
    height: 30px;
    display: flex;
    justify-content: center;
    align-items: center;
    cursor: pointer;
    border-radius: 50%;
}

.chat-header i:hover {
    background: #1f2937;
}

.chat-body {
    height: 260px;
    overflow-y: auto;
    padding: 12px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    background: #0b1220;
}

.msg {
    padding: 10px 14px;
    border-radius: 18px;
    max-width: 75%;
    font-size: 13px;
}

.msg.user {
    background: #3251ff;
    color: #fff;
    align-self: flex-end;
}

.msg.bot {
    background: #1f2937;
    color: #d1d5db;
    align-self: flex-start;
}

.chat-footer {
    display: flex;
    border-top: 1px solid #1f2937;
    background: #111827;
}

.chat-footer input {
    flex: 1;
    padding: 10px;
    border: none;
    outline: none;
    background: transparent;
    color: white;
}

.chat-footer button {
    background: #3251ff;
    border: none;
    padding: 10px 14px;
    color: white;
    cursor: pointer;
}
/* NOTIFICATION DOT */
#chatNotif {
    position: absolute;
    top: 10px;
    right: 8px;
    width: 12px;
    height: 12px;
    background: red;
    border-radius: 50%;
    display: none;
    box-shadow: 0 0 10px red;
}

.task-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 18px;
  margin-top: 15px;
}

.task-card {
  background: #111827;
  border: 1px solid #1f2937;
  border-radius: 14px;
  padding: 16px;
  transition: 0.2s;
}

.task-card:hover {
  transform: translateY(-4px);
  border-color: rgba(50,81,255,0.4);
}

.task-card h3 {
  color: #3251ff;
  font-size: 15px;
}

.task-card a {
  display: inline-block;
  margin-top: 8px;
  color: #60a5fa;
  font-size: 13px;
  text-decoration: none;
}

.task-card a:hover {
  text-decoration: underline;
}

.task-composer {
  background: #111827;
  border: 1px solid #1f2937;
  border-radius: 12px;
  padding: 18px;
  margin: 22px 0;
}

.task-composer h2,
.sent-tasks h2 {
  font-size: 18px;
  margin-bottom: 14px;
}

.form-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(240px, 360px);
  gap: 16px;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 7px;
  margin-bottom: 12px;
}

.field label {
  color: #9ca3af;
  font-size: 12px;
}

.field input,
.field textarea {
  width: 100%;
  background: #0b1220;
  color: #e5e7eb;
  border: 1px solid #1f2937;
  border-radius: 8px;
  padding: 11px 12px;
  outline: none;
}

.field textarea {
  min-height: 130px;
  resize: vertical;
}

.employee-picker {
  background: #0b1220;
  border: 1px solid #1f2937;
  border-radius: 10px;
  max-height: 315px;
  overflow-y: auto;
  padding: 8px;
}

.employee-option {
  display: grid;
  grid-template-columns: 18px 1fr;
  gap: 10px;
  padding: 9px;
  border-radius: 8px;
  color: #e5e7eb;
  cursor: pointer;
}

.employee-option:hover {
  background: #111827;
}

.employee-meta {
  color: #9ca3af;
  display: block;
  font-size: 11px;
  margin-top: 2px;
}

.composer-actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-top: 12px;
  flex-wrap: wrap;
}

.send-task-btn {
  background: #3251ff;
  border: 0;
  border-radius: 8px;
  color: white;
  cursor: pointer;
  font-weight: 600;
  padding: 11px 16px;
}

.select-all {
  color: #60a5fa;
  font-size: 13px;
  cursor: pointer;
}

.alert {
  border-radius: 8px;
  margin-bottom: 12px;
  padding: 10px 12px;
}

.alert.success { background: #14532d; color: #bbf7d0; }
.alert.error { background: #7f1d1d; color: #fecaca; }

.batch-stats {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin: 12px 0;
}

.batch-pill {
  background: #0b1220;
  border: 1px solid #1f2937;
  border-radius: 999px;
  color: #cbd5e1;
  font-size: 12px;
  padding: 6px 10px;
}

@media (max-width: 900px) {
  .form-grid {
    grid-template-columns: 1fr;
  }
}
</style>
</head>

<body>

<?php renderSidebar('hr', 'hr.dashboard'); ?>

<!-- MAIN -->
<div class="main">

<div class="topbar">
  <h1>Welcome, <?= $_SESSION['name']; ?></h1>
  <div style="display:flex; justify-content:flex-end;">
  <div style= "display:inline-block;
   padding:8px 18px;
    background:#3251ff;
    border-radius:20px;
    color:#fffff;
    font-weight:bold;
    font-size:15px;">Human Resource</div>
</div>
</div>

<!-- CARDS -->
<div class="cards">
    <div class="card">
      <h3>Total Employees</h3>
      <h1><?= $totalEmployees; ?></h1>
      <i class="fa-solid fa-users"></i>
    </div>

    <div class="card">
      <h3>HR Staff</h3>
      <h1><?= $totalHR; ?></h1>
      <i class="fa-solid fa-user-tie"></i>
    </div>

    <div class="card">
      <h3>Pending Tasks</h3>
      <h1><?= $pendingRequests; ?></h1>
      <i class="fa-solid fa-clock"></i>
    </div>

    <div class="card">
      <h3>Total Records</h3>
      <h1><?= $systemLogs; ?></h1>
      <i class="fa-solid fa-database"></i>
    </div>
</div>

<!-- CHAT LIST (ADMIN SELECTION) -->
<div style="padding:12px; width: 30%; color:white;">
    <h3>Chat With Admin</h3>
    <br>
    <?php
    $admins = $conn->query("SELECT id, full_name FROM users WHERE role='admin'");
    while($admin = $admins->fetch_assoc()):
    ?>
        <div onclick="startChat(<?= $admin['id']; ?>, '<?= htmlspecialchars($admin['full_name']) ?>')" style="
            padding:8px;
            margin:5px 0;
            background:#1f2937;
            border-radius:8px;
            cursor:pointer;
        ">
            <?= htmlspecialchars($admin['full_name']); ?>
        </div>
    <?php endwhile; ?>
</div>

<div class="task-composer">
  <h2><i class="fa-solid fa-paper-plane"></i> Send Task</h2>

  <?php if (isset($_GET['task_sent'])): ?>
    <div class="alert success">
      Task sent to <?= (int) $_GET['task_sent']; ?> employee<?= (int) $_GET['task_sent'] === 1 ? '' : 's'; ?>.
    </div>
  <?php elseif (isset($_GET['task_error'])): ?>
    <div class="alert error">Task was not sent. Please select at least one active employee and complete the required fields.</div>
  <?php endif; ?>

  <form action="post_task.php" method="POST" enctype="multipart/form-data">
    <div class="form-grid">
      <div>
        <div class="field">
          <label for="title">Task Title</label>
          <input type="text" id="title" name="title" required>
        </div>

        <div class="field">
          <label for="description">Instructions</label>
          <textarea id="description" name="description" required></textarea>
        </div>

        <div class="field">
          <label for="deadline">Deadline</label>
          <input type="date" id="deadline" name="deadline" required>
        </div>

        <div class="field">
          <label for="file">Attachment</label>
          <input type="file" id="file" name="file">
        </div>
      </div>

      <div>
        <div class="composer-actions" style="margin-top:0;margin-bottom:8px;">
          <label style="color:#9ca3af;font-size:12px;">Recipients</label>
          <button type="button" class="select-all" onclick="toggleEmployees()" style="background:none;border:0;">Select All</button>
        </div>

        <div class="employee-picker">
          <?php if ($employees && $employees->num_rows > 0): ?>
            <?php while ($employee = $employees->fetch_assoc()): ?>
              <label class="employee-option">
                <input type="checkbox" name="employee_ids[]" value="<?= $employee['id']; ?>">
                <span>
                  <?= htmlspecialchars($employee['full_name']); ?>
                  <span class="employee-meta">
                    <?= htmlspecialchars($employee['employee_id'] ?: 'No employee ID'); ?>
                    <?= $employee['department_name'] ? ' - ' . htmlspecialchars($employee['department_name']) : ''; ?>
                  </span>
                </span>
              </label>
            <?php endwhile; ?>
          <?php else: ?>
            <div style="color:#9ca3af;padding:10px;">No active employees found.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="composer-actions">
      <span style="color:#9ca3af;font-size:12px;">Each selected employee gets their own submission slot.</span>
      <button class="send-task-btn" type="submit">
        <i class="fa-solid fa-paper-plane"></i> Send Task
      </button>
    </div>
  </form>
</div>

<div class="sent-tasks">
  <h2><i class="fa-solid fa-list-check"></i> Sent Task Batches</h2>

  <div class="task-grid">
    <?php if ($taskBatches && $taskBatches->num_rows > 0): ?>
      <?php while ($batch = $taskBatches->fetch_assoc()): ?>
        <div class="task-card">
          <h3><?= htmlspecialchars($batch['title']); ?></h3>

          <p style="color:#9ca3af;font-size:13px;margin-top:8px;">
            <?= nl2br(htmlspecialchars($batch['description'])); ?>
          </p>

          <div class="batch-stats">
            <span class="batch-pill"><?= (int) $batch['recipient_count']; ?> recipient<?= (int) $batch['recipient_count'] === 1 ? '' : 's'; ?></span>
            <span class="batch-pill"><?= (int) $batch['pending_count']; ?> pending</span>
            <span class="batch-pill"><?= (int) $batch['submitted_count']; ?> submitted</span>
            <span class="batch-pill"><?= (int) $batch['reviewed_count']; ?> reviewed</span>
          </div>

          <p style="color:#fbbf24;font-size:12px;">
            <i class="fa-solid fa-calendar"></i>
            Due: <?= $batch['deadline'] ? date("M d, Y", strtotime($batch['deadline'])) : 'No deadline'; ?>
          </p>

          <?php if (!empty($batch['file_attachment'])): ?>
            <a href="../uploads/tasks/<?= htmlspecialchars($batch['file_attachment']); ?>" target="_blank">
              <i class="fa-solid fa-paperclip"></i> View Attachment
            </a>
          <?php endif; ?>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <div class="task-card">
        <h3>No tasks sent yet</h3>
        <p style="color:#9ca3af;font-size:13px;margin-top:8px;">Sent task batches will appear here.</p>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- FLOAT CHAT -->
<div id="chatAvatar" onclick="openChat()">
    <img src="../images/chatbox.png">
    <span id="chatNotif"></span>
</div>

<div id="chatBox">
    <div class="chat-header">
        <span>Admin Chat Support</span>
        <i class="fa-solid fa-xmark" onclick="closeChat()"></i>
    </div>

    <div id="typingIndicator" style="color:#9ca3af;font-size:12px;padding:5px 12px;display:none;">
    Someone is typing...
</div>
    <div class="chat-body" id="chatBody"></div>

    <div class="chat-footer">
        <input type="text" id="chatInput" placeholder = "Enter message...">
        <button onclick="sendMessage()">
            <i class="fa-solid fa-paper-plane"></i>
        </button>
    </div>
</div>

<script>
const current_user_id = <?= $_SESSION['user_id']; ?>;
let receiver_id = null;

let lastMessageCount = 0;
let employeesSelected = false;

function toggleEmployees() {
    const boxes = document.querySelectorAll('input[name="employee_ids[]"]');
    employeesSelected = !employeesSelected;
    boxes.forEach(box => box.checked = employeesSelected);
}

/* START CHAT */
function startChat(id, name) {
    receiver_id = id;
    document.getElementById("chatBox").style.display = "flex";
    document.querySelector(".chat-header span").innerText = "Chat with " + name;

    loadMessages(true);
    markAsSeen();
}

/* CHECK FOR UNREAD MESSAGES FROM ANY ADMIN */
function checkUnreadAdmins() {
    fetch('../chat/fetch_unread_admins.php')
    .then(res => res.json())
    .then(admins => {
        if (admins.length > 0 && !receiver_id) {
            // Auto-select first admin with unread messages
            const firstAdmin = admins[0];
            startChat(firstAdmin.id, firstAdmin.full_name);
            
            // Show notification
            document.getElementById("chatNotif").style.display = "block";
        } else if (admins.length > 0) {
            // Show notification if there are unread messages
            document.getElementById("chatNotif").style.display = "block";
        }
    })
    .catch(err => console.log("Error fetching unread admins:", err));
}

/* OPEN CHAT */
function openChat() {
    document.getElementById("chatBox").style.display = "flex";
    document.getElementById("chatNotif").style.display = "none";

    if (receiver_id) {
        loadMessages(true);
        markAsSeen();
    } else {
        // If no receiver selected, check for unread messages
        checkUnreadAdmins();
    }
}

function closeChat() {
    document.getElementById("chatBox").style.display = "none";
}

/* SEND MESSAGE */
function sendMessage() {
    const input = document.getElementById("chatInput");
    const message = input.value.trim();

    if (!receiver_id || message === "") return;

    fetch('../chat/send_message.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: `receiver_id=${receiver_id}&message=${encodeURIComponent(message)}`
    }).then(() => {
        input.value = "";
        loadMessages(true);
    });
}

/* LOAD MESSAGES */
function loadMessages(scroll = false) {
    if (!receiver_id) return;
    
    fetch(`../chat/fetch_messages.php?receiver_id=${receiver_id}`)
    .then(res => res.text())
    .then(data => {

        const chatBody = document.getElementById("chatBody");
        chatBody.innerHTML = data;

        if (scroll) {
            chatBody.scrollTop = chatBody.scrollHeight;
        }

        lastMessageCount = chatBody.children.length;
    });
}

/* REAL-TIME CHECK (1s loop = near real-time) */
setInterval(() => {
    if (receiver_id) {
        checkNewMessages();
    } else {
        checkUnreadAdmins();
    }

    function checkTyping() {
        if (!receiver_id) return;

        fetch(`../chat/fetch_messages.php?receiver_id=${receiver_id}`)
        .then(res => res.text())
        .then(data => {

            const temp = document.createElement("div");
            temp.innerHTML = data;

            let typingFound = false;

            temp.querySelectorAll(".msg").forEach(msg => {
                if (msg.dataset.typing === "1") {
                    typingFound = true;
                }
            });

            document.getElementById("typingIndicator").style.display =
                typingFound ? "block" : "none";
        });
    }

}, 1000);

/* CHECK NEW MESSAGES */
function checkNewMessages() {
    if (!receiver_id) return;
    
    fetch(`../chat/fetch_messages.php?receiver_id=${receiver_id}`)
    .then(res => res.text())
    .then(data => {

        const chatBody = document.getElementById("chatBody");

        const temp = document.createElement("div");
        temp.innerHTML = data;

        const newCount = temp.children.length;

        if (newCount > lastMessageCount) {

            document.getElementById("chatNotif").style.display = "block";

            // auto scroll to bottom
            chatBody.scrollTop = chatBody.scrollHeight;
        }

        chatBody.innerHTML = data;
        lastMessageCount = newCount;
    });
}

/* MARK AS SEEN */
function markAsSeen() {
    fetch('../chat/mark_seen.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: `receiver_id=${receiver_id}`
    });
}

let typingTimeout = null;

/* WHEN USER TYPES */
document.getElementById("chatInput").addEventListener("input", function () {

    if (!receiver_id) return;

    fetch('../chat/typing_status.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: `receiver_id=${receiver_id}&is_typing=1`
    });

    clearTimeout(typingTimeout);

    typingTimeout = setTimeout(() => {
        fetch('../chat/typing_status.php', {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: `receiver_id=${receiver_id}&is_typing=0`
        });
    }, 1500);
});
</script>

</body>
</html>
