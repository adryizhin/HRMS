<?php
declare(strict_types=1);

require_once __DIR__ . '/attendance.php';
require_once __DIR__ . '/task_schema.php';

function scoreColorClass(float $score): string
{
    if ($score >= 85) {
        return 'excellent';
    }

    if ($score >= 70) {
        return 'good';
    }

    if ($score >= 50) {
        return 'watch';
    }

    return 'risk';
}

function performanceLabel(float $score): string
{
    if ($score >= 85) {
        return 'Excellent';
    }

    if ($score >= 70) {
        return 'On Track';
    }

    if ($score >= 50) {
        return 'Needs Support';
    }

    return 'At Risk';
}

function clampScore(float $score): float
{
    return max(0.0, min(100.0, $score));
}

function calculateEmployeeKpi(mysqli $conn, int $employeeId): array
{
    ensureAttendanceTable($conn);
    ensureTaskSchema($conn);

    $weights = [
        'attendance' => 35,
        'hr_rating' => 35,
        'completion' => 15,
        'deadline' => 10,
        'task_health' => 5,
    ];

    $attendanceStmt = $conn->prepare("
        SELECT
            SUM(status = 'present') AS present_count,
            SUM(status = 'late') AS late_count,
            SUM(status = 'absent') AS absent_count,
            COUNT(*) AS total_count
        FROM attendance
        WHERE user_id = ?
    ");
    $attendanceStmt->bind_param("i", $employeeId);
    $attendanceStmt->execute();
    $attendance = $attendanceStmt->get_result()->fetch_assoc() ?: [];

    $present = (int) ($attendance['present_count'] ?? 0);
    $late = (int) ($attendance['late_count'] ?? 0);
    $absent = (int) ($attendance['absent_count'] ?? 0);
    $attendanceTotal = (int) ($attendance['total_count'] ?? 0);
    $attendanceScore = $attendanceTotal > 0
        ? (($present + ($late * 0.75)) / $attendanceTotal) * 100
        : null;

    $taskStmt = $conn->prepare("
        SELECT
            COUNT(*) AS assigned_count,
            SUM(submission_file IS NOT NULL AND submission_file != '') AS submitted_count,
            SUM(status = 'reviewed') AS reviewed_count,
            SUM(hr_score IS NOT NULL) AS scored_count,
            AVG(hr_score) AS average_hr_score,
            SUM(
                submission_file IS NOT NULL
                AND submission_file != ''
                AND submitted_at IS NOT NULL
                AND deadline IS NOT NULL
                AND submitted_at <= CONCAT(deadline, ' 23:59:59')
            ) AS on_time_count,
            SUM(
                submission_file IS NOT NULL
                AND submission_file != ''
                AND submitted_at IS NOT NULL
                AND deadline IS NOT NULL
            ) AS deadline_submission_count,
            SUM(
                (submission_file IS NULL OR submission_file = '')
                AND deadline IS NOT NULL
                AND deadline < CURDATE()
            ) AS overdue_pending_count
        FROM tasks
        WHERE employee_id = ?
    ");
    $taskStmt->bind_param("i", $employeeId);
    $taskStmt->execute();
    $tasks = $taskStmt->get_result()->fetch_assoc() ?: [];

    $assigned = (int) ($tasks['assigned_count'] ?? 0);
    $submitted = (int) ($tasks['submitted_count'] ?? 0);
    $reviewed = (int) ($tasks['reviewed_count'] ?? 0);
    $scored = (int) ($tasks['scored_count'] ?? 0);
    $onTime = (int) ($tasks['on_time_count'] ?? 0);
    $deadlineSubmissions = (int) ($tasks['deadline_submission_count'] ?? 0);
    $overduePending = (int) ($tasks['overdue_pending_count'] ?? 0);

    $hrScore = $scored > 0 ? (float) $tasks['average_hr_score'] : null;
    $completionScore = $assigned > 0 ? ($submitted / $assigned) * 100 : null;
    $deadlineScore = $deadlineSubmissions > 0 ? ($onTime / $deadlineSubmissions) * 100 : null;
    $taskHealthScore = $assigned > 0 ? (($assigned - $overduePending) / $assigned) * 100 : null;

    $factors = [
        'attendance' => [
            'label' => 'Attendance',
            'weight' => $weights['attendance'],
            'score' => $attendanceScore,
            'detail' => "{$present} present, {$late} late, {$absent} absent",
        ],
        'hr_rating' => [
            'label' => 'HR Rating',
            'weight' => $weights['hr_rating'],
            'score' => $hrScore,
            'detail' => "{$scored} rated submission" . ($scored === 1 ? '' : 's'),
        ],
        'completion' => [
            'label' => 'Work Completion',
            'weight' => $weights['completion'],
            'score' => $completionScore,
            'detail' => "{$submitted} of {$assigned} assigned tasks submitted",
        ],
        'deadline' => [
            'label' => 'Deadline Discipline',
            'weight' => $weights['deadline'],
            'score' => $deadlineScore,
            'detail' => "{$onTime} of {$deadlineSubmissions} deadline-tracked submissions on time",
        ],
        'task_health' => [
            'label' => 'Open Task Health',
            'weight' => $weights['task_health'],
            'score' => $taskHealthScore,
            'detail' => "{$overduePending} overdue pending task" . ($overduePending === 1 ? '' : 's'),
        ],
    ];

    $availableWeight = 0;
    $weightedScore = 0.0;

    foreach ($factors as $factor) {
        if ($factor['score'] === null) {
            continue;
        }

        $availableWeight += $factor['weight'];
        $weightedScore += clampScore((float) $factor['score']) * $factor['weight'];
    }

    $overall = $availableWeight > 0 ? $weightedScore / $availableWeight : 0.0;

    return [
        'overall' => round($overall, 1),
        'label' => $availableWeight > 0 ? performanceLabel($overall) : 'No Data Yet',
        'color_class' => $availableWeight > 0 ? scoreColorClass($overall) : 'muted',
        'available_weight' => $availableWeight,
        'factors' => $factors,
        'stats' => [
            'attendance_total' => $attendanceTotal,
            'assigned' => $assigned,
            'submitted' => $submitted,
            'reviewed' => $reviewed,
            'scored' => $scored,
            'overdue_pending' => $overduePending,
        ],
    ];
}
?>
