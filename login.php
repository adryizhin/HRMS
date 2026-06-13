<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="shortcut icon" href="images/logo.png" type="image/x-icon">
<title>Pagecom HRIS</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&display=swap" rel="stylesheet">
<link rel="stylesheet" href="login/login.css">
</head>

<body>
<div class="wrapper">

  <!-- LEFT PANEL  -->
  <div class="left-panel">
    <div id="particles-js"></div>
    <div class="left-overlay"></div>
    <div class="left-content">

      <div class="brand">
        <img src="images/logo.png" alt="Pagecom Logo">
        <span class="brand-name">Pagecom INC.</span>
      </div>

      <div class="hero-text">
        <p class="hero-label">Human Resource Information System</p>
        <h1 class="hero-title">
          Manage people,<br>
          <em>not paperwork.</em>
        </h1>
        <p class="hero-sub">
          Streamline HR operations from attendance and payroll
          to employee records — all in one unified platform.
        </p>

        <div class="stat-row">
          <div class="stat">
            <span class="stat-num">360°</span>
            <span class="stat-lbl">Employee view</span>
          </div>
          <div class="stat">
            <span class="stat-num">Real-time</span>
            <span class="stat-lbl">Attendance</span>
          </div>
          <div class="stat">
            <span class="stat-num">Secure</span>
            <span class="stat-lbl">Role access</span>
          </div>
        </div>
      </div>

    </div>
  </div>

  <!--  RIGHT PANEL  -->
  <div class="right-panel">
    <div class="form-box">

      <div class="form-header">
        <img src="images./pagecom logo.png" alt="logo">
      </div>

      <form method="POST" action="login/login_process.php" oninput="detectRole()">

        <div class="field">
          <label for="email">Email address</label>
          <input type="email" id="email" name="email" placeholder="you@pagecom.com" required>
        </div>

        <div class="field">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" placeholder="••••••••" required>
        </div>

        <div class="role-bar" id="roleBar">
          <div class="role-dot" id="roleDot"></div>
          <span class="role-text" id="roleText">Detecting role…</span>
        </div>

        <button type="submit" class="btn-signin">Sign In</button>

      </form>

    </div>
  </div>

</div>

<!-- ══ POPUP ══ -->
<div id="popup" class="popup">
  <div class="popup-content">
    <div class="popup-icon"><i class="fa-solid fa-circle-xmark"></i></div>
    <p class="popup-msg" id="popupMessage">Invalid email or password.</p>
    <button class="popup-btn" onclick="closePopup()">Dismiss</button>
  </div>
</div>

<script src = "login/indicator.js"></script>

<?php if (isset($_GET['error'])): ?>
<script>
window.onload = function () { showPopup("Invalid email or password."); };
</script>
<?php endif; ?>

<?php if (isset($_GET['timeout'])): ?>
<script>
window.onload = function () { showPopup("Your session timed out after 15 minutes of inactivity."); };
</script>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/tsparticles@2/tsparticles.bundle.min.js"></script>
<script>
  tsParticles.load("particles-js", {
  background: { color: "#0a0a0a" },
  fpsLimit: 60,
  particles: {
    number: { value: 60, density: { enable: true, area: 900 } },
    color: { value: "#ffffff" },
    shape: { type: "circle" },
    opacity: { value: 0.15 },
    size: { value: 2 },
    links: {
      enable: true,
      distance: 140,
      color: "#ffffff",
      opacity: 0.07,
      width: 0.8
    },
    move: {
      enable: true,
      speed: 0.8,
      direction: "none",
      outModes: { default: "bounce" }
    }
  },
  interactivity: {
    events: {
      onHover: { enable: true, mode: "grab" },
      onClick:  { enable: true, mode: "push" }
    },
    modes: {
      grab: { distance: 160, links: { opacity: 0.25 } },
      push: { quantity: 3 }
    }
  },
  detectRetina: true
});
</script>
</body>
</html>
