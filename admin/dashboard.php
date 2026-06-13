<?php
require '../includes/auth_check.php';
checkRole(['admin']);
require '../config.php';
require_once __DIR__ . '/../includes/sidebar.php';

$admin_id = (int) $_SESSION['user_id'];

date_default_timezone_set('Asia/Manila');

/* =========================
   DASHBOARD COUNTS
========================= */

/* TOTAL EMPLOYEES */
$result = $conn->query("
    SELECT COUNT(*) AS total 
    FROM users 
    WHERE role='employee'
");

$totalEmployees = $result ? $result->fetch_assoc()['total'] : 0;

/* TOTAL HR */
$result = $conn->query("
    SELECT COUNT(*) AS total 
    FROM users 
    WHERE role='hr'
");

$totalHR = $result ? $result->fetch_assoc()['total'] : 0;

/* PENDING TASKS */
$result = $conn->query("
    SELECT COUNT(*) AS total 
    FROM tasks
    WHERE status='pending'
");

$pendingRequests = $result ? $result->fetch_assoc()['total'] : 0;

/* TOTAL TASK RECORDS */
$result = $conn->query("
    SELECT COUNT(*) AS total 
    FROM tasks
");

$systemLogs = $result ? $result->fetch_assoc()['total'] : 0;

/* =========================
   LOGIN LOGS
========================= */

$query = "
SELECT * 
FROM login_logs 
ORDER BY id DESC
";

$logs = $conn->query($query);

/* =========================
   ACTIVE USERS
========================= */

$activeQuery = "
SELECT * 
FROM login_logs
WHERE logout_time IS NULL
ORDER BY login_time DESC
";

$activeUsers = $conn->query($activeQuery);

/* =========================
   TIME AGO FUNCTION
========================= */

function timeAgo($datetime) {

    if (!$datetime) {
        return "0 sec";
    }

    $time = strtotime($datetime);

    if (!$time) {
        return "0 sec";
    }

    $diff = time() - $time;

    $hours = floor($diff / 3600);
    $minutes = floor(($diff % 3600) / 60);
    $seconds = $diff % 60;

    $output = "";

    if ($hours > 0) {
        $output .= $hours . " hr ";
    }

    if ($minutes > 0) {
        $output .= $minutes . " min ";
    }

    if ($hours == 0 && $seconds > 0) {
        $output .= $seconds . " sec";
    }

    return trim($output);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="shortcut icon" href="images/logo.png" type="image/x-icon">
<title>Pagecom HRIS</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="dashboard.css">
<link rel="stylesheet" href="chat.css">

<style>

</style>
</head>

<body>

<?php renderSidebar('admin', 'admin.dashboard'); ?>

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
    font-size:15px;">Admin</div>
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
      <h3>Pending Requests</h3>
      <h1><?= $pendingRequests; ?></h1>
      <i class="fa-solid fa-clock"></i>
    </div>

    <div class="card">
      <h3>System Logs</h3>
      <h1><?= $systemLogs; ?></h1>
      <i class="fa-solid fa-database"></i>
    </div>
</div>


<!-- CHAT LIST (HR SELECTION) & ADMIN LIST -->
<div style="padding:12px; width: 30%; color:white;">
    <h3>Chat With HR</h3>
    <br>
    <?php
    $hrs = $conn->query("SELECT id, full_name FROM users WHERE role='hr'");
    while($hr = $hrs->fetch_assoc()):
    ?>
        <div onclick="startChat(<?= $hr['id']; ?>, '<?= htmlspecialchars($hr['full_name']) ?>')" style="
            padding:8px;
            margin:5px 0;
            background:#1f2937;
            border-radius:8px;
            cursor:pointer;
        ">
            <?= htmlspecialchars($hr['full_name']); ?>
        </div>
    <?php endwhile; ?>
    
    <h3 style="margin-top:20px;">Chat With Other Admins</h3>
    <br>
    <?php
    $otherAdmins = $conn->query("SELECT id, full_name FROM users WHERE role='admin' AND id != ".$admin_id);
    if ($otherAdmins->num_rows > 0):
        while($otherAdmin = $otherAdmins->fetch_assoc()):
        ?>
            <div onclick="startChat(<?= $otherAdmin['id']; ?>, '<?= htmlspecialchars($otherAdmin['full_name']) ?>')" style="
                padding:8px;
                margin:5px 0;
                background:#1f2937;
                border-radius:8px;
                cursor:pointer;
            ">
                <?= htmlspecialchars($otherAdmin['full_name']); ?>
            </div>
        <?php
        endwhile;
    else:
        ?>
        <div style="color:#9ca3af;font-size:12px;">No other admins available</div>
        <?php
    endif;
    ?>
</div>

</div>
<div style=" margin-bottom:-1px; padding:15px; background:#111827; border-radius:12px; border:1px solid #1f2937; "> <h1 style = "text-align:center">Online Users</h1> <br> <?php if ($activeUsers->num_rows > 0): ?> <?php while($a = $activeUsers->fetch_assoc()): ?> <div style=" display:flex; justify-content:space-between; align-items:center; padding:12px; margin-bottom:10px; background:#0b1220; border-radius:10px; border:1px solid #1f2937; "> <!-- LEFT --> <div style="display:flex; flex-direction:column;"> <div style="display:flex; align-items:center; gap:8px;"> <span class="dot"></span> <strong><?= htmlspecialchars($a['username']); ?></strong> </div> </div> <!-- RIGHT --> <span class="badge <?= $a['role']; ?>"> <?= strtoupper($a['role']); ?> </span> </div> <?php endwhile; ?> <?php else: ?> <div style=" text-align:center; color:#9ca3af; padding:10px; "> No active users </div> <?php endif; ?> </div> </div>
<!-- CHAT FLOAT -->
<div id="chatAvatar" onclick="openChat()">
    <span id="chatNotif"></span>
    <img src="../images/chatbox.png">
</div>

<div id="chatBox">
    <div class="chat-header">
        <span>Chat</span>
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

/* OPEN CHAT */
function startChat(id, name) {
    receiver_id = id;
    document.getElementById("chatBox").style.display = "flex";
    document.querySelector(".chat-header span").innerText = "Chat with " + name;

    loadMessages(true);
}

/* OPEN FLOAT CHAT */
function openChat() {
    document.getElementById("chatBox").style.display = "flex";
    document.getElementById("chatNotif").style.display = "none";

    if (receiver_id) {
        loadMessages(true);
        markAsSeen(); // mark messages as seen when opened
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

/* LOAD MESSAGES (REAL TIME) */
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

/* REAL TIME CHECK (FASTER) */
setInterval(() => {
    if (receiver_id) checkNewMessages();
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
}, 1000); // 🔥 faster = more real-time

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

            // 🔴 NOTIFICATION DOT
            document.getElementById("chatNotif").style.display = "block";

            // 🔔 browser alert (optional)
            console.log("New message received!");

            // auto scroll
            chatBody.scrollTop = chatBody.scrollHeight;
        }

        chatBody.innerHTML = data;
        lastMessageCount = newCount;
    });
}

/* MARK AS SEEN */
function markAsSeen() {
    if (!receiver_id) return;

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
