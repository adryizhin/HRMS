<?php
declare(strict_types=1);

function ensureAttendanceTable(mysqli $conn): void
{
    static $checked = false;

    if ($checked) {
        return;
    }

    $conn->query("
        CREATE TABLE IF NOT EXISTS attendance (
            id INT NOT NULL AUTO_INCREMENT,
            user_id INT NOT NULL,
            attendance_date DATE NOT NULL,
            shift ENUM('morning','night') NOT NULL,
            login_time DATETIME NOT NULL,
            status ENUM('present','late','absent') NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_user_shift_date (user_id, attendance_date, shift),
            KEY user_id (user_id),
            CONSTRAINT attendance_user_fk
                FOREIGN KEY (user_id) REFERENCES users (id)
                ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $checked = true;
}

function ensureUserShiftColumn(mysqli $conn): void
{
    static $checked = false;

    if ($checked) {
        return;
    }

    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'work_shift'");

    if ($result && $result->num_rows === 0) {
        $conn->query("
            ALTER TABLE users
            ADD COLUMN work_shift ENUM('morning','night') NOT NULL DEFAULT 'morning' AFTER department_id
        ");
    }

    $checked = true;
}

function getAttendanceMark(DateTimeImmutable $loginTime, string $shift): array
{
    $date = $loginTime->format('Y-m-d');
    $shift = $shift === 'night' ? 'night' : 'morning';
    $timezone = $loginTime->getTimezone();

    if ($shift === 'morning') {
        $shift = 'morning';
        $shiftDate = $date;
        $lateCutoff = new DateTimeImmutable($shiftDate . ' 08:15:00', $timezone);
        $absentCutoff = new DateTimeImmutable($shiftDate . ' 12:00:00', $timezone);
    } else {
        $shiftDate = ((int) $loginTime->format('G') < 12)
            ? $loginTime->modify('-1 day')->format('Y-m-d')
            : $date;
        $lateCutoff = new DateTimeImmutable($shiftDate . ' 20:15:00', $timezone);
        $absentCutoff = (new DateTimeImmutable($shiftDate . ' 00:00:00', $timezone))->modify('+1 day');
    }

    $status = 'present';

    if ($loginTime >= $absentCutoff) {
        $status = 'absent';
    } elseif ($loginTime > $lateCutoff) {
        $status = 'late';
    }

    return [
        'date' => $shiftDate,
        'shift' => $shift,
        'status' => $status,
    ];
}

function recordAttendanceOnLogin(mysqli $conn, int $userId): void
{
    ensureAttendanceTable($conn);
    ensureUserShiftColumn($conn);

    $loginTime = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
    $shiftStmt = $conn->prepare("SELECT work_shift FROM users WHERE id = ? LIMIT 1");
    $shiftStmt->bind_param("i", $userId);
    $shiftStmt->execute();
    $user = $shiftStmt->get_result()->fetch_assoc();

    $mark = getAttendanceMark($loginTime, $user['work_shift'] ?? 'morning');
    $loginAt = $loginTime->format('Y-m-d H:i:s');

    $stmt = $conn->prepare("
        INSERT INTO attendance (user_id, attendance_date, shift, login_time, status)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            status = IF(login_time > VALUES(login_time), VALUES(status), status),
            login_time = IF(login_time > VALUES(login_time), VALUES(login_time), login_time)
    ");
    $stmt->bind_param(
        "issss",
        $userId,
        $mark['date'],
        $mark['shift'],
        $loginAt,
        $mark['status']
    );
    $stmt->execute();
}

function attendanceSummary(mysqli $conn, ?int $userId = null): array
{
    ensureAttendanceTable($conn);

    if ($userId) {
        $stmt = $conn->prepare("
            SELECT
                SUM(status = 'present') AS present_count,
                SUM(status = 'late') AS late_count,
                SUM(status = 'absent') AS absent_count,
                COUNT(*) AS total_count
            FROM attendance
            WHERE user_id = ?
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
    } else {
        $row = $conn->query("
            SELECT
                SUM(status = 'present') AS present_count,
                SUM(status = 'late') AS late_count,
                SUM(status = 'absent') AS absent_count,
                COUNT(*) AS total_count
            FROM attendance
        ")->fetch_assoc();
    }

    return [
        'present' => (int) ($row['present_count'] ?? 0),
        'late' => (int) ($row['late_count'] ?? 0),
        'absent' => (int) ($row['absent_count'] ?? 0),
        'total' => (int) ($row['total_count'] ?? 0),
    ];
}
?>
