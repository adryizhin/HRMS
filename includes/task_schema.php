<?php
declare(strict_types=1);

function ensureTaskSchema(mysqli $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $columns = [
        'employee_id' => "ALTER TABLE tasks ADD COLUMN employee_id INT(11) DEFAULT NULL AFTER id",
        'hr_id' => "ALTER TABLE tasks ADD COLUMN hr_id INT(11) DEFAULT NULL AFTER employee_id",
        'description' => "ALTER TABLE tasks ADD COLUMN description TEXT DEFAULT NULL AFTER title",
        'deadline' => "ALTER TABLE tasks ADD COLUMN deadline DATE DEFAULT NULL AFTER description",
        'file_attachment' => "ALTER TABLE tasks ADD COLUMN file_attachment VARCHAR(255) DEFAULT NULL AFTER deadline",
        'submission_file' => "ALTER TABLE tasks ADD COLUMN submission_file VARCHAR(255) DEFAULT NULL AFTER file_attachment",
        'submitted_at' => "ALTER TABLE tasks ADD COLUMN submitted_at DATETIME DEFAULT NULL AFTER submission_file",
        'status' => "ALTER TABLE tasks ADD COLUMN status ENUM('pending','submitted','reviewed') NOT NULL DEFAULT 'pending' AFTER submitted_at",
        'ai_score' => "ALTER TABLE tasks ADD COLUMN ai_score INT(11) DEFAULT NULL AFTER status",
        'grammar_score' => "ALTER TABLE tasks ADD COLUMN grammar_score INT(11) DEFAULT NULL AFTER ai_score",
        'spelling_score' => "ALTER TABLE tasks ADD COLUMN spelling_score INT(11) DEFAULT NULL AFTER grammar_score",
        'clarity_score' => "ALTER TABLE tasks ADD COLUMN clarity_score INT(11) DEFAULT NULL AFTER spelling_score",
        'relevance_score' => "ALTER TABLE tasks ADD COLUMN relevance_score INT(11) DEFAULT NULL AFTER clarity_score",
        'ai_feedback' => "ALTER TABLE tasks ADD COLUMN ai_feedback TEXT DEFAULT NULL AFTER relevance_score",
        'hr_score' => "ALTER TABLE tasks ADD COLUMN hr_score INT(11) DEFAULT NULL AFTER ai_feedback",
        'hr_feedback' => "ALTER TABLE tasks ADD COLUMN hr_feedback TEXT DEFAULT NULL AFTER hr_score",
    ];

    $existing = [];
    $result = $conn->query("SHOW COLUMNS FROM tasks");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $existing[$row['Field']] = true;
        }
    }

    foreach ($columns as $column => $sql) {
        if (!isset($existing[$column])) {
            $conn->query($sql);
        }
    }

    $done = true;
}
?>
