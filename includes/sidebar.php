<?php
declare(strict_types=1);

require_once __DIR__ . '/routes.php';

function renderSidebar(string $role, string $active = ''): void
{
    renderSidebarStyles();

    $menus = [
        'admin' => [
            ['admin.dashboard', 'fa-chart-line', 'Dashboard'],
            ['admin.users', 'fa-user', 'Create Employees'],
            ['admin.departments', 'fa-building', 'Departments'],
            ['admin.attendance', 'fa-clock', 'Attendance'],
            ['admin.payroll', 'fa-money-bill', 'Payroll'],
            ['admin.login_logs', 'fa-clock-rotate-left', 'Login Logs'],
            ['#', 'fa-chart-pie', 'Reports'],
            ['logout', 'fa-right-from-bracket', 'Logout'],
        ],
        'hr' => [
            ['hr.dashboard', 'fa-chart-line', 'Dashboard'],
            ['hr.task_submissions', 'fa-clipboard-check', 'Review Submissions'],
            ['hr.201_files', 'fa-folder-open', '201 Files'],
            ['hr.leave_requests', 'fa-umbrella', 'Leave Requests'],
            ['hr.attendance', 'fa-clock', 'Attendance'],
            ['hr.kpi', 'fa-gauge-high', 'Employee KPIs'],
            ['hr.payroll', 'fa-money-bill', 'Payroll'],
            ['#', 'fa-chart-pie', 'Reports'],
            ['logout', 'fa-right-from-bracket', 'Logout'],
        ],
        'employee' => [
            ['employee.dashboard', 'fa-chart-line', 'Dashboard'],
            ['employee.documents', 'fa-folder-open', 'My Documents'],
            ['employee.tasks', 'fa-tasks', 'Tasks'],
            ['employee.leave_request', 'fa-umbrella', 'Request Leave'],
            ['employee.attendance', 'fa-clock', 'Attendance'],
            ['employee.kpi', 'fa-gauge-high', 'My KPI'],
            ['employee.payroll', 'fa-money-bill', 'Payroll'],
            ['#', 'fa-chart-pie', 'Reports'],
            ['logout', 'fa-right-from-bracket', 'Logout'],
        ],
    ];

    echo '<div class="sidebar">';
    echo '<h2>Pagecom HRIS</h2>';

    foreach ($menus[$role] ?? [] as [$routeName, $icon, $label]) {
        $href = $routeName === '#' ? '#' : route($routeName);
        $class = $active === $routeName ? ' class="active"' : '';
        echo '<a href="' . htmlspecialchars($href) . '"' . $class . '>';
        echo '<i class="fa-solid ' . htmlspecialchars($icon) . '"></i> ';
        echo htmlspecialchars($label);
        echo '</a>';
    }

    echo '</div>';
    renderIdleTimeoutScript();
}

function renderSidebarStyles(): void
{
    static $rendered = false;

    if ($rendered) {
        return;
    }

    $rendered = true;

    echo <<<HTML
<style>
body {
    min-height: 100vh;
    overflow-x: hidden;
}

.sidebar {
    width: 240px !important;
    height: 100vh !important;
    min-height: 100vh !important;
    background: var(--sidebar, #020617) !important;
    padding: 20px !important;
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    z-index: 1000 !important;
    overflow-y: auto !important;
}

.sidebar h2 {
    color: var(--accent, #3251ff) !important;
    margin-bottom: 30px !important;
}

.sidebar a {
    display: flex !important;
    align-items: center !important;
    gap: 10px !important;
    padding: 12px !important;
    color: var(--muted, #9ca3af) !important;
    text-decoration: none !important;
    border-radius: 10px !important;
    margin-bottom: 10px !important;
    transition: background 0.2s ease, color 0.2s ease !important;
}

.sidebar a:hover {
    background: var(--accent, #3251ff) !important;
    color: #000 !important;
}

.sidebar a.active {
    background: var(--accent, #3251ff) !important;
    color: #000 !important;
}

.main {
    margin-left: 240px !important;
    width: calc(100% - 240px) !important;
    flex: none !important;
}

@media (max-width: 780px) {
    .sidebar {
        width: 100% !important;
        height: auto !important;
        min-height: 0 !important;
        position: sticky !important;
        top: 0 !important;
    }

    .main {
        margin-left: 0 !important;
        width: 100% !important;
    }
}
</style>
HTML;
}

function renderIdleTimeoutScript(): void
{
    static $rendered = false;

    if ($rendered) {
        return;
    }

    $rendered = true;
    $timeoutUrl = route('timeout_logout');
    $keepaliveUrl = route('keepalive');

    echo <<<HTML
<script>
(function () {
    const timeoutMs = 15 * 60 * 1000;
    const timeoutUrl = "{$timeoutUrl}";
    const keepaliveUrl = "{$keepaliveUrl}";
    let timer = null;
    let lastKeepalive = 0;

    function forceTimeoutLogout() {
        window.location.href = timeoutUrl;
    }

    function keepSessionAlive() {
        const now = Date.now();
        if (now - lastKeepalive < 60000) {
            return;
        }

        lastKeepalive = now;
        fetch(keepaliveUrl, { method: "POST", credentials: "same-origin" }).catch(function () {});
    }

    function resetIdleTimer() {
        clearTimeout(timer);
        keepSessionAlive();
        timer = setTimeout(forceTimeoutLogout, timeoutMs);
    }

    ["click", "keydown", "mousemove", "scroll", "touchstart"].forEach(function (eventName) {
        window.addEventListener(eventName, resetIdleTimer, { passive: true });
    });

    resetIdleTimer();
})();
</script>
HTML;
}
?>
