<?php
declare(strict_types=1);

function ensureEmployeeDocumentsTable(mysqli $conn): void
{
    static $checked = false;

    if ($checked) {
        return;
    }

    $conn->query("
        CREATE TABLE IF NOT EXISTS employee_documents (
            id INT NOT NULL AUTO_INCREMENT,
            employee_id INT NOT NULL,
            document_type VARCHAR(100) NOT NULL,
            title VARCHAR(160) NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            file_type VARCHAR(120) DEFAULT NULL,
            file_size INT DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            status ENUM('submitted','verified','rejected') NOT NULL DEFAULT 'submitted',
            hr_notes TEXT DEFAULT NULL,
            reviewed_by INT DEFAULT NULL,
            reviewed_at DATETIME DEFAULT NULL,
            uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY employee_id (employee_id),
            KEY reviewed_by (reviewed_by),
            CONSTRAINT employee_documents_employee_fk
                FOREIGN KEY (employee_id) REFERENCES users (id)
                ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT employee_documents_reviewer_fk
                FOREIGN KEY (reviewed_by) REFERENCES users (id)
                ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $checked = true;
}

function documentStatusClass(string $status): string
{
    if ($status === 'verified') {
        return 'green';
    }

    if ($status === 'rejected') {
        return 'red';
    }

    return 'blue';
}

function documentTypes(): array
{
    return [
        'Resume / CV',
        'Government ID',
        'Birth Certificate',
        'Diploma / TOR',
        'Employment Contract',
        'Medical Record',
        'Training Certificate',
        'Performance Record',
        'Other',
    ];
}
?>
