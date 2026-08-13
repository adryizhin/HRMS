<?php
require_once __DIR__ . '/../includes/auth_check.php';
checkRole(['employee']);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/kpi.php';

$user_id = (int) $_SESSION['user_id'];
$kpi = calculateEmployeeKpi($conn, $user_id);
$factors = $kpi['factors'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My KPI</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="dashboard.css">
<style>
.kpi-hero {
  align-items: center;
  background: #101826;
  border: 1px solid #263244;
  border-radius: 8px;
  display: grid;
  gap: 22px;
  grid-template-columns: minmax(0, 1fr) 220px;
  margin-bottom: 22px;
  padding: 22px;
}
.kpi-hero h1 { color: #f8fafc; font-size: 28px; margin-bottom: 8px; }
.kpi-hero p { color: #94a3b8; font-size: 14px; line-height: 1.5; }
.kpi-score {
  background: #0b1220;
  border: 1px solid #263244;
  border-radius: 8px;
  padding: 20px;
  text-align: center;
}
.kpi-score strong { display: block; font-size: 48px; line-height: 1; }
.kpi-score span { color: #cbd5e1; display: block; font-size: 13px; margin-top: 8px; text-transform: uppercase; }
.excellent { color: #22c55e; }
.good { color: #60a5fa; }
.watch { color: #f59e0b; }
.risk { color: #ef4444; }
.muted { color: #94a3b8; }
.factor-grid {
  display: grid;
  gap: 16px;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
}
.factor-card {
  background: #101826;
  border: 1px solid #263244;
  border-radius: 8px;
  padding: 16px;
}
.factor-head {
  align-items: center;
  display: flex;
  justify-content: space-between;
  gap: 12px;
}
.factor-card h3 { color: #f8fafc; font-size: 15px; }
.factor-weight {
  background: #0b1220;
  border: 1px solid #263244;
  border-radius: 999px;
  color: #cbd5e1;
  font-size: 12px;
  padding: 5px 9px;
}
.factor-value { font-size: 30px; font-weight: 800; margin: 14px 0 8px; }
.factor-card p { color: #94a3b8; font-size: 13px; line-height: 1.45; }
.progress {
  background: #0b1220;
  border-radius: 999px;
  height: 9px;
  margin-top: 13px;
  overflow: hidden;
}
.progress span { background: #3251ff; display: block; height: 100%; }
@media (max-width: 780px) {
  .kpi-hero { grid-template-columns: 1fr; }
}
</style>
</head>
<body>
<?php renderSidebar('employee', 'employee.kpi'); ?>

<div class="main">
  <div class="kpi-hero">
    <div>
      <p class="eyebrow">Performance Indicator</p>
      <h1>My KPI</h1>
      <p>Your KPI is calculated from available attendance records, HR-rated submissions, task completion, deadline discipline, and overdue task health. Missing categories are skipped until data exists.</p>
    </div>
    <div class="kpi-score">
      <strong class="<?= htmlspecialchars($kpi['color_class']); ?>"><?= number_format((float) $kpi['overall'], 1); ?>%</strong>
      <span><?= htmlspecialchars($kpi['label']); ?></span>
    </div>
  </div>

  <div class="cards">
    <div class="card">
      <h3>Assigned Tasks</h3>
      <h1><?= (int) $kpi['stats']['assigned']; ?></h1>
      <i class="fa-solid fa-list-check"></i>
    </div>
    <div class="card">
      <h3>Submitted Work</h3>
      <h1><?= (int) $kpi['stats']['submitted']; ?></h1>
      <i class="fa-solid fa-paper-plane"></i>
    </div>
    <div class="card">
      <h3>HR Ratings</h3>
      <h1><?= (int) $kpi['stats']['scored']; ?></h1>
      <i class="fa-solid fa-star"></i>
    </div>
    <div class="card">
      <h3>Attendance Records</h3>
      <h1><?= (int) $kpi['stats']['attendance_total']; ?></h1>
      <i class="fa-solid fa-clock"></i>
    </div>
  </div>

  <div class="factor-grid">
    <?php foreach ($factors as $factor): ?>
      <?php
        $score = $factor['score'];
        $displayScore = $score === null ? null : clampScore((float) $score);
      ?>
      <article class="factor-card">
        <div class="factor-head">
          <h3><?= htmlspecialchars($factor['label']); ?></h3>
          <span class="factor-weight"><?= (int) $factor['weight']; ?>% weight</span>
        </div>
        <div class="factor-value <?= $displayScore === null ? 'muted' : htmlspecialchars(scoreColorClass($displayScore)); ?>">
          <?= $displayScore === null ? 'N/A' : number_format($displayScore, 1) . '%'; ?>
        </div>
        <p><?= htmlspecialchars($factor['detail']); ?></p>
        <div class="progress"><span style="width: <?= $displayScore === null ? 0 : $displayScore; ?>%;"></span></div>
      </article>
    <?php endforeach; ?>
  </div>
</div>
</body>
</html>
