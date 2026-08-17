<?php
declare(strict_types=1);

require_once __DIR__ . '/attendance.php';
require_once __DIR__ . '/sidebar.php';

function money(float $amount): string
{
    return 'PHP ' . number_format($amount, 2);
}

function payslipDefaultCutoff(): array
{
    $today = new DateTimeImmutable('today', new DateTimeZone('Asia/Manila'));
    $day = (int) $today->format('d');

    if ($day <= 15) {
        $start = $today->modify('first day of this month');
        $end = $today->setDate((int) $today->format('Y'), (int) $today->format('m'), 15);
    } else {
        $start = $today->setDate((int) $today->format('Y'), (int) $today->format('m'), 16);
        $end = $today->modify('last day of this month');
    }

    return [$start->format('Y-m-d'), $end->format('Y-m-d')];
}

function payslipCountWorkdays(string $startDate, string $endDate): int
{
    $start = new DateTimeImmutable($startDate);
    $end = new DateTimeImmutable($endDate);
    $days = 0;

    for ($date = $start; $date <= $end; $date = $date->modify('+1 day')) {
        $dayOfWeek = (int) $date->format('N');
        if ($dayOfWeek <= 5) {
            $days++;
        }
    }

    return $days;
}

function payslipAttendanceSummary(mysqli $conn, int $employeeId, string $startDate, string $endDate): array
{
    ensureAttendanceTable($conn);

    $stmt = $conn->prepare("
        SELECT
            SUM(status = 'present') AS present_count,
            SUM(status = 'late') AS late_count,
            SUM(status = 'absent') AS absent_count,
            COUNT(*) AS recorded_count
        FROM attendance
        WHERE user_id = ?
        AND attendance_date BETWEEN ? AND ?
    ");
    $stmt->bind_param("iss", $employeeId, $startDate, $endDate);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: [];

    return [
        'present' => (int) ($row['present_count'] ?? 0),
        'late' => (int) ($row['late_count'] ?? 0),
        'absent' => (int) ($row['absent_count'] ?? 0),
        'recorded' => (int) ($row['recorded_count'] ?? 0),
    ];
}

function ensurePayslipTable(mysqli $conn): void
{
    static $checked = false;

    if ($checked) {
        return;
    }

    $conn->query("
        CREATE TABLE IF NOT EXISTS payslips (
            id INT NOT NULL AUTO_INCREMENT,
            user_id INT NOT NULL,
            cutoff_start DATE NOT NULL,
            cutoff_end DATE NOT NULL,
            payout_date DATE NOT NULL,
            daily_rate DECIMAL(10,2) NOT NULL DEFAULT 755.00,
            paid_days INT NOT NULL DEFAULT 0,
            present_days INT NOT NULL DEFAULT 0,
            late_days INT NOT NULL DEFAULT 0,
            absent_days INT NOT NULL DEFAULT 0,
            unrecorded_days INT NOT NULL DEFAULT 0,
            allowance DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            deduction DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            tax DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            gross_pay DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            total_deductions DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            net_pay DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            generated_by INT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_user_cutoff (user_id, cutoff_start, cutoff_end),
            KEY user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $checked = true;
}

function payslipFind(mysqli $conn, int $userId, string $cutoffStart, string $cutoffEnd): ?array
{
    ensurePayslipTable($conn);

    $stmt = $conn->prepare("
        SELECT *
        FROM payslips
        WHERE user_id = ?
        AND cutoff_start = ?
        AND cutoff_end = ?
        LIMIT 1
    ");
    $stmt->bind_param("iss", $userId, $cutoffStart, $cutoffEnd);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    return $row ?: null;
}

function payslipSave(mysqli $conn, array $data): void
{
    ensurePayslipTable($conn);

    $stmt = $conn->prepare("
        INSERT INTO payslips (
            user_id, cutoff_start, cutoff_end, payout_date, daily_rate,
            paid_days, present_days, late_days, absent_days, unrecorded_days,
            allowance, deduction, tax, gross_pay, total_deductions, net_pay, generated_by
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            payout_date = VALUES(payout_date),
            daily_rate = VALUES(daily_rate),
            paid_days = VALUES(paid_days),
            present_days = VALUES(present_days),
            late_days = VALUES(late_days),
            absent_days = VALUES(absent_days),
            unrecorded_days = VALUES(unrecorded_days),
            allowance = VALUES(allowance),
            deduction = VALUES(deduction),
            tax = VALUES(tax),
            gross_pay = VALUES(gross_pay),
            total_deductions = VALUES(total_deductions),
            net_pay = VALUES(net_pay),
            generated_by = VALUES(generated_by)
    ");

    $userId = (int) $data['user_id'];
    $cutoffStart = (string) $data['cutoff_start'];
    $cutoffEnd = (string) $data['cutoff_end'];
    $payoutDate = (string) $data['payout_date'];
    $dailyRate = (float) $data['daily_rate'];
    $paidDays = (int) $data['paid_days'];
    $presentDays = (int) $data['present_days'];
    $lateDays = (int) $data['late_days'];
    $absentDays = (int) $data['absent_days'];
    $unrecordedDays = (int) $data['unrecorded_days'];
    $allowance = (float) $data['allowance'];
    $deduction = (float) $data['deduction'];
    $tax = (float) $data['tax'];
    $grossPay = (float) $data['gross_pay'];
    $totalDeductions = (float) $data['total_deductions'];
    $netPay = (float) $data['net_pay'];
    $generatedBy = (int) $data['generated_by'];

    $stmt->bind_param(
        "isssdiiiiiddddddi",
        $userId,
        $cutoffStart,
        $cutoffEnd,
        $payoutDate,
        $dailyRate,
        $paidDays,
        $presentDays,
        $lateDays,
        $absentDays,
        $unrecordedDays,
        $allowance,
        $deduction,
        $tax,
        $grossPay,
        $totalDeductions,
        $netPay,
        $generatedBy
    );
    $stmt->execute();
}

function payslipApplySavedValues(array $savedPayslip, string &$payoutDate, float &$dailyRate, int &$paidDays, array &$attendance, int &$unrecordedDays, float &$allowance, float &$deduction, float &$tax, float &$grossPay, float &$totalDeductions, float &$netPay): void
{
    $payoutDate = $savedPayslip['payout_date'];
    $dailyRate = (float) $savedPayslip['daily_rate'];
    $paidDays = (int) $savedPayslip['paid_days'];
    $attendance = [
        'present' => (int) $savedPayslip['present_days'],
        'late' => (int) $savedPayslip['late_days'],
        'absent' => (int) $savedPayslip['absent_days'],
        'recorded' => (int) $savedPayslip['present_days'] + (int) $savedPayslip['late_days'] + (int) $savedPayslip['absent_days'],
    ];
    $unrecordedDays = (int) $savedPayslip['unrecorded_days'];
    $allowance = (float) $savedPayslip['allowance'];
    $deduction = (float) $savedPayslip['deduction'];
    $tax = (float) $savedPayslip['tax'];
    $grossPay = (float) $savedPayslip['gross_pay'];
    $totalDeductions = (float) $savedPayslip['total_deductions'];
    $netPay = (float) $savedPayslip['net_pay'];
}

function renderPayslipPage(mysqli $conn, string $role): void
{
    ensureAttendanceTable($conn);
    ensureUserShiftColumn($conn);
    ensurePayslipTable($conn);

    [$defaultStart, $defaultEnd] = payslipDefaultCutoff();
    $canGenerate = $role === 'hr';
    $currentUserId = (int) ($_SESSION['user_id'] ?? 0);
    $input = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
    $dailyRate = 755.00;
    $employeeId = $canGenerate ? (int) ($input['employee_id'] ?? 0) : $currentUserId;
    $cutoffStart = $input['cutoff_start'] ?? $defaultStart;
    $cutoffEnd = $input['cutoff_end'] ?? $defaultEnd;
    $payoutDate = $input['payout_date'] ?? $cutoffEnd;
    $allowance = $canGenerate ? max(0, (float) ($input['allowance'] ?? 0)) : 0.00;
    $deduction = $canGenerate ? max(0, (float) ($input['deduction'] ?? 0)) : 0.00;
    $tax = $canGenerate ? max(0, (float) ($input['tax'] ?? 0)) : 0.00;
    $notice = '';

    $employees = $conn->query("
        SELECT users.id, users.employee_id, users.full_name, users.role, departments.name AS department_name
        FROM users
        LEFT JOIN departments ON users.department_id = departments.id
        WHERE users.role IN ('employee', 'hr', 'admin')
        AND users.status = 'active'
        ORDER BY FIELD(users.role, 'employee', 'hr', 'admin'), users.full_name ASC
    ");

    if ($employeeId === 0 && $employees && $employees->num_rows > 0) {
        $firstEmployee = $employees->fetch_assoc();
        $employeeId = (int) $firstEmployee['id'];
        $employees->data_seek(0);
    }

    $employee = null;
    if ($employeeId > 0) {
        $stmt = $conn->prepare("
            SELECT users.id, users.employee_id, users.full_name, users.role, departments.name AS department_name
            FROM users
            LEFT JOIN departments ON users.department_id = departments.id
            WHERE users.id = ?
            AND users.role IN ('employee', 'hr', 'admin')
            LIMIT 1
        ");
        $stmt->bind_param("i", $employeeId);
        $stmt->execute();
        $employee = $stmt->get_result()->fetch_assoc();
    }

    $attendance = $employee
        ? payslipAttendanceSummary($conn, $employeeId, $cutoffStart, $cutoffEnd)
        : ['present' => 0, 'late' => 0, 'absent' => 0, 'recorded' => 0];

    $workdays = payslipCountWorkdays($cutoffStart, $cutoffEnd);
    $paidDays = $attendance['present'] + $attendance['late'];
    $unrecordedDays = max(0, $workdays - $attendance['recorded']);
    $grossPay = $paidDays * $dailyRate + $allowance;
    $totalDeductions = $deduction + $tax;
    $netPay = max(0, $grossPay - $totalDeductions);
    $savedPayslip = $employee ? payslipFind($conn, $employeeId, $cutoffStart, $cutoffEnd) : null;

    if ($canGenerate && $_SERVER['REQUEST_METHOD'] === 'POST' && $employee) {
        payslipSave($conn, [
            'user_id' => $employeeId,
            'cutoff_start' => $cutoffStart,
            'cutoff_end' => $cutoffEnd,
            'payout_date' => $payoutDate,
            'daily_rate' => $dailyRate,
            'paid_days' => $paidDays,
            'present_days' => $attendance['present'],
            'late_days' => $attendance['late'],
            'absent_days' => $attendance['absent'],
            'unrecorded_days' => $unrecordedDays,
            'allowance' => $allowance,
            'deduction' => $deduction,
            'tax' => $tax,
            'gross_pay' => $grossPay,
            'total_deductions' => $totalDeductions,
            'net_pay' => $netPay,
            'generated_by' => $currentUserId,
        ]);
        $savedPayslip = payslipFind($conn, $employeeId, $cutoffStart, $cutoffEnd);
        $notice = 'Payslip generated and saved.';
    }

    if ($savedPayslip && (!$canGenerate || $_SERVER['REQUEST_METHOD'] !== 'POST')) {
        payslipApplySavedValues(
            $savedPayslip,
            $payoutDate,
            $dailyRate,
            $paidDays,
            $attendance,
            $unrecordedDays,
            $allowance,
            $deduction,
            $tax,
            $grossPay,
            $totalDeductions,
            $netPay
        );
    }

    $activeRoute = $role . '.payroll';
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payslip Generator</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root {
    --bg:#0b1220;
    --panel:#111827;
    --panel-soft:#172033;
    --line:#22304a;
    --text:#e5e7eb;
    --muted:#9ca3af;
    --accent:#3251ff;
    --green:#22c55e;
    --red:#fb7185;
    --yellow:#facc15;
}
* { box-sizing:border-box; }
body {
    margin:0;
    min-height:100vh;
    background:var(--bg);
    color:var(--text);
    font-family:Arial, sans-serif;
}
.main {
    padding:24px;
}
.topbar {
    display:flex;
    justify-content:space-between;
    gap:16px;
    align-items:flex-start;
    margin-bottom:18px;
}
.topbar h1 {
    margin:0 0 6px;
    font-size:28px;
}
.topbar p {
    margin:0;
    color:var(--muted);
    font-size:14px;
}
.generator {
    display:grid;
    grid-template-columns:minmax(280px, 380px) minmax(0, 1fr);
    gap:20px;
    align-items:start;
}
.form-panel,
.payslip {
    background:var(--panel);
    border:1px solid var(--line);
    border-radius:8px;
}
.form-panel {
    padding:18px;
}
.form-panel h2 {
    margin:0 0 16px;
    font-size:17px;
}
.field {
    margin-bottom:13px;
}
.field label {
    display:block;
    margin-bottom:7px;
    color:var(--muted);
    font-size:12px;
    font-weight:700;
    text-transform:uppercase;
}
.field input,
.field select {
    width:100%;
    border:1px solid var(--line);
    border-radius:8px;
    background:#0f172a;
    color:var(--text);
    padding:11px 12px;
    outline:none;
}
.field input:focus,
.field select:focus {
    border-color:var(--accent);
}
.actions {
    display:flex;
    gap:10px;
    margin-top:16px;
}
.btn {
    border:0;
    border-radius:8px;
    padding:11px 14px;
    cursor:pointer;
    font-weight:700;
    color:#fff;
    background:var(--accent);
    text-decoration:none;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:8px;
}
.btn.secondary {
    background:#1f2937;
    color:var(--text);
}
.payslip-header {
    padding:24px;
    background:linear-gradient(135deg, #0b1220 0%, #111827 55%, #172033 100%);
    border-bottom:1px solid var(--line);
}
.payslip-meta {
    display:flex;
    justify-content:space-between;
    gap:16px;
    align-items:flex-start;
}
.eyebrow {
    display:flex;
    gap:10px;
    align-items:center;
    color:var(--muted);
    font-size:11px;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:0;
}
.paid-badge {
    color:#86efac;
    background:rgba(34,197,94,.14);
    border:1px solid rgba(34,197,94,.35);
    border-radius:999px;
    padding:5px 9px;
}
.employee-name {
    margin:12px 0 6px;
    font-size:30px;
    line-height:1.05;
}
.subtitle {
    margin:0;
    color:var(--muted);
    font-size:14px;
}
.month-pill {
    background:#1f2937;
    border:1px solid var(--line);
    border-radius:8px;
    min-width:88px;
    padding:10px;
    text-align:center;
    font-weight:800;
}
.month-pill span {
    display:block;
    color:var(--muted);
    font-size:12px;
    margin-top:3px;
}
.summary-grid {
    display:grid;
    grid-template-columns:repeat(4, minmax(0, 1fr));
    gap:12px;
    margin-top:24px;
}
.metric {
    min-height:96px;
    background:var(--panel-soft);
    border:1px solid var(--line);
    border-radius:8px;
    padding:14px;
}
.metric label {
    display:block;
    color:var(--muted);
    font-size:11px;
    font-weight:800;
    text-transform:uppercase;
    margin-bottom:8px;
}
.metric strong {
    display:block;
    font-size:22px;
    line-height:1.15;
}
.metric small {
    color:var(--green);
    display:block;
    margin-top:7px;
    font-size:12px;
}
.metric.warning strong,
.metric.warning small { color:var(--yellow); }
.metric.danger strong,
.metric.danger small { color:var(--red); }
.payslip-body {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:18px;
    background:#f8fafc;
    color:#111827;
    padding:24px;
}
.breakdown {
    background:#fff;
    border:1px solid #e5e7eb;
    border-radius:8px;
    overflow:hidden;
}
.breakdown-head,
.line-item {
    display:grid;
    grid-template-columns:1fr auto;
    gap:12px;
    align-items:start;
}
.breakdown-head {
    background:#f1f5f9;
    color:#334155;
    padding:12px 14px;
    font-size:12px;
    font-weight:800;
    text-transform:uppercase;
}
.line-item {
    padding:13px 14px;
    border-top:1px solid #e5e7eb;
    font-size:13px;
}
.line-item small {
    display:block;
    color:#64748b;
    margin-top:3px;
}
.line-item strong {
    white-space:nowrap;
}
.line-item.danger {
    color:#be123c;
    background:#fff1f2;
}
.payslip-footer {
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
    color:#64748b;
    background:#fff;
    border-top:1px solid #e5e7eb;
    padding:14px 24px;
    font-size:12px;
}
.empty-note {
    color:var(--muted);
    padding:18px;
}
@media (max-width: 1100px) {
    .generator,
    .payslip-body {
        grid-template-columns:1fr;
    }
    .summary-grid {
        grid-template-columns:repeat(2, minmax(0, 1fr));
    }
}
@media (max-width: 640px) {
    .main { padding:16px; }
    .topbar,
    .payslip-meta,
    .payslip-footer {
        flex-direction:column;
    }
    .summary-grid {
        grid-template-columns:1fr;
    }
    .employee-name {
        font-size:24px;
    }
}
@media print {
    .sidebar,
    .form-panel,
    .topbar,
    .btn {
        display:none !important;
    }
    body {
        background:#fff;
    }
    .main {
        margin:0 !important;
        width:100% !important;
        padding:0;
    }
    .generator {
        display:block;
    }
    .payslip {
        border:0;
        border-radius:0;
    }
}
</style>
</head>
<body>
<?php renderSidebar($role, $activeRoute); ?>

<main class="main">
    <div class="topbar">
        <div>
            <h1><?= $canGenerate ? 'Payslip Generator' : 'My Payroll'; ?></h1>
            <p><?= $canGenerate ? 'Generate and save cut-off payslips using a fixed daily salary of ' . money($dailyRate) . '.' : 'View payroll slips generated by HR for your account.'; ?></p>
        </div>
        <?php if ($employee && ($canGenerate || $savedPayslip)): ?>
            <button class="btn secondary" type="button" onclick="window.print()">
                <i class="fa-solid fa-file-pdf"></i> PDF
            </button>
        <?php endif; ?>
    </div>

    <?php if ($notice !== ''): ?>
        <div class="form-panel" style="margin-bottom:16px;color:#86efac;background:#14532d;border-color:#166534;">
            <?= htmlspecialchars($notice); ?>
        </div>
    <?php endif; ?>

    <div class="generator">
        <section class="form-panel">
            <h2><?= $canGenerate ? 'Generate Payslip' : 'Payroll Cut-off'; ?></h2>
            <form method="<?= $canGenerate ? 'POST' : 'GET'; ?>">
                <?php if ($canGenerate): ?>
                    <div class="field">
                        <label for="employee_id">Payee</label>
                        <select id="employee_id" name="employee_id" required>
                            <?php if (!$employees || $employees->num_rows === 0): ?>
                                <option value="">No active users</option>
                            <?php else: ?>
                                <?php while ($row = $employees->fetch_assoc()): ?>
                                    <option value="<?= (int) $row['id']; ?>" <?= (int) $row['id'] === $employeeId ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($row['full_name']); ?> - <?= strtoupper(htmlspecialchars($row['role'])); ?> (<?= htmlspecialchars($row['employee_id'] ?? 'No ID'); ?>)
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                <?php endif; ?>
                <div class="field">
                    <label for="cutoff_start">Cut-off Start</label>
                    <input id="cutoff_start" type="date" name="cutoff_start" value="<?= htmlspecialchars($cutoffStart); ?>" required>
                </div>
                <div class="field">
                    <label for="cutoff_end">Cut-off End</label>
                    <input id="cutoff_end" type="date" name="cutoff_end" value="<?= htmlspecialchars($cutoffEnd); ?>" required>
                </div>
                <?php if ($canGenerate): ?>
                    <div class="field">
                        <label for="payout_date">Payout Date</label>
                        <input id="payout_date" type="date" name="payout_date" value="<?= htmlspecialchars($payoutDate); ?>" required>
                    </div>
                    <div class="field">
                        <label for="allowance">Allowances</label>
                        <input id="allowance" type="number" name="allowance" min="0" step="0.01" value="<?= htmlspecialchars((string) $allowance); ?>">
                    </div>
                    <div class="field">
                        <label for="deduction">Other Deductions</label>
                        <input id="deduction" type="number" name="deduction" min="0" step="0.01" value="<?= htmlspecialchars((string) $deduction); ?>">
                    </div>
                    <div class="field">
                        <label for="tax">Withholding Tax</label>
                        <input id="tax" type="number" name="tax" min="0" step="0.01" value="<?= htmlspecialchars((string) $tax); ?>">
                    </div>
                <?php endif; ?>
                <div class="actions">
                    <button class="btn" type="submit"><i class="fa-solid <?= $canGenerate ? 'fa-wand-magic-sparkles' : 'fa-magnifying-glass'; ?>"></i> <?= $canGenerate ? 'Generate & Save' : 'View'; ?></button>
                    <a class="btn secondary" href="<?= htmlspecialchars(route($activeRoute)); ?>">Reset</a>
                </div>
            </form>
        </section>

        <section class="payslip">
            <?php if (!$employee): ?>
                <div class="empty-note">Select an active user to generate a payslip.</div>
            <?php elseif (!$canGenerate && !$savedPayslip): ?>
                <div class="empty-note">No payroll slip has been generated by HR for this cut-off.</div>
            <?php else: ?>
                <div class="payslip-header">
                    <div class="payslip-meta">
                        <div>
                            <div class="eyebrow">
                                <span>Official Salary Breakdown</span>
                                <span class="paid-badge"><i class="fa-solid fa-circle-check"></i> Generated</span>
                            </div>
                            <h2 class="employee-name"><?= htmlspecialchars($employee['full_name']); ?></h2>
                            <p class="subtitle">
                                <?= strtoupper(htmlspecialchars($employee['role'])); ?>
                                &nbsp;•&nbsp; <?= htmlspecialchars($employee['department_name'] ?? 'Unassigned Department'); ?>
                                &nbsp;•&nbsp; Cut-off:
                                <?= date('M d', strtotime($cutoffStart)); ?> - <?= date('M d, Y', strtotime($cutoffEnd)); ?>
                                &nbsp;•&nbsp; Payout: <?= date('Y-m-d', strtotime($payoutDate)); ?>
                            </p>
                        </div>
                        <div class="month-pill">
                            <?= date('M', strtotime($cutoffEnd)); ?>
                            <span><?= date('Y', strtotime($cutoffEnd)); ?></span>
                        </div>
                    </div>

                    <div class="summary-grid">
                        <div class="metric">
                            <label>Gross Earnings</label>
                            <strong><?= money($grossPay); ?></strong>
                            <small><?= $paidDays; ?> paid day<?= $paidDays === 1 ? '' : 's'; ?> at <?= money($dailyRate); ?></small>
                        </div>
                        <div class="metric warning">
                            <label>Other Deductions</label>
                            <strong>- <?= money($deduction); ?></strong>
                            <small>Manual deductions</small>
                        </div>
                        <div class="metric danger">
                            <label>Withholding Tax</label>
                            <strong>- <?= money($tax); ?></strong>
                            <small>Entered tax amount</small>
                        </div>
                        <div class="metric">
                            <label>Net Take-home Pay</label>
                            <strong><?= money($netPay); ?></strong>
                            <small><?= $totalDeductions > 0 ? number_format(($netPay / max($grossPay, 1)) * 100, 1) . '% after deductions' : 'No deductions applied'; ?></small>
                        </div>
                    </div>
                </div>

                <div class="payslip-body">
                    <div class="breakdown">
                        <div class="breakdown-head">
                            <span>Earnings & Allowances</span>
                            <span>Amount</span>
                        </div>
                        <div class="line-item">
                            <div>
                                Basic Daily Salary
                                <small><?= $paidDays; ?> paid day<?= $paidDays === 1 ? '' : 's'; ?> x <?= money($dailyRate); ?></small>
                            </div>
                            <strong><?= money($paidDays * $dailyRate); ?></strong>
                        </div>
                        <div class="line-item">
                            <div>
                                Allowances
                                <small>Additional cut-off allowance</small>
                            </div>
                            <strong><?= money($allowance); ?></strong>
                        </div>
                        <div class="line-item">
                            <div>
                                Regular Present Days
                                <small><?= $attendance['present']; ?> present record<?= $attendance['present'] === 1 ? '' : 's'; ?></small>
                            </div>
                            <strong><?= $attendance['present']; ?></strong>
                        </div>
                        <div class="line-item">
                            <div>
                                Late But Paid Days
                                <small><?= $attendance['late']; ?> late record<?= $attendance['late'] === 1 ? '' : 's'; ?></small>
                            </div>
                            <strong><?= $attendance['late']; ?></strong>
                        </div>
                    </div>

                    <div class="breakdown">
                        <div class="breakdown-head">
                            <span>Deductions & Taxes</span>
                            <span>Amount</span>
                        </div>
                        <div class="line-item danger">
                            <div>
                                Absences
                                <small><?= $attendance['absent']; ?> absent record<?= $attendance['absent'] === 1 ? '' : 's'; ?></small>
                            </div>
                            <strong><?= money($attendance['absent'] * $dailyRate); ?></strong>
                        </div>
                        <div class="line-item danger">
                            <div>
                                Unrecorded Workdays
                                <small><?= $unrecordedDays; ?> workday<?= $unrecordedDays === 1 ? '' : 's'; ?> without attendance record</small>
                            </div>
                            <strong><?= money($unrecordedDays * $dailyRate); ?></strong>
                        </div>
                        <div class="line-item">
                            <div>
                                Other Deductions
                                <small>Loans, cash advances, or adjustments</small>
                            </div>
                            <strong><?= money($deduction); ?></strong>
                        </div>
                        <div class="line-item danger">
                            <div>
                                Withholding Tax
                                <small>Manual tax entry for this cut-off</small>
                            </div>
                            <strong><?= money($tax); ?></strong>
                        </div>
                    </div>
                </div>

                <div class="payslip-footer">
                    <span><i class="fa-solid fa-shield-halved"></i> Pagecom HRIS generated payroll summary</span>
                    <span>Payee ID: <?= htmlspecialchars($employee['employee_id'] ?? 'N/A'); ?></span>
                </div>
            <?php endif; ?>
        </section>
    </div>
</main>
</body>
</html>
<?php
}
?>
