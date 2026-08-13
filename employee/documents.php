<?php
require_once __DIR__ . '/../includes/auth_check.php';
checkRole(['employee']);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/employee_documents.php';

ensureEmployeeDocumentsTable($conn);

$employee_id = (int) $_SESSION['user_id'];
$documentTypes = documentTypes();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $documentType = trim($_POST['document_type'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($documentType === '' || $title === '') {
        $message = '<div class="alert error">Please complete the document type and title.</div>';
    } elseif (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] !== UPLOAD_ERR_OK) {
        $message = '<div class="alert error">Please choose a document to upload.</div>';
    } else {
        $file = $_FILES['document_file'];
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
        $maxBytes = 10 * 1024 * 1024;

        if (!in_array($extension, $allowedExtensions, true)) {
            $message = '<div class="alert error">Allowed files: PDF, DOC, DOCX, JPG, JPEG, or PNG.</div>';
        } elseif ((int) $file['size'] > $maxBytes) {
            $message = '<div class="alert error">File must be 10MB or smaller.</div>';
        } else {
            $uploadDir = __DIR__ . '/../uploads/201_files/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $safeOriginal = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($file['name']));
            $storedName = time() . '_' . $employee_id . '_' . $safeOriginal;
            $targetPath = $uploadDir . $storedName;
            $publicPath = 'uploads/201_files/' . $storedName;
            $fileType = $file['type'] ?: $extension;
            $fileSize = (int) $file['size'];

            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $stmt = $conn->prepare("
                    INSERT INTO employee_documents (
                        employee_id,
                        document_type,
                        title,
                        file_name,
                        original_name,
                        file_path,
                        file_type,
                        file_size,
                        notes
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param(
                    "issssssis",
                    $employee_id,
                    $documentType,
                    $title,
                    $storedName,
                    $safeOriginal,
                    $publicPath,
                    $fileType,
                    $fileSize,
                    $notes
                );

                if ($stmt->execute()) {
                    header("Location: documents.php?uploaded=1");
                    exit;
                }

                $message = '<div class="alert error">Upload saved, but the database record failed.</div>';
            } else {
                $message = '<div class="alert error">Document upload failed. Please try again.</div>';
            }
        }
    }
}

$stmt = $conn->prepare("
    SELECT *
    FROM employee_documents
    WHERE employee_id = ?
    ORDER BY uploaded_at DESC
");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$documents = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My 201 Documents</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="dashboard.css">
<style>
.document-layout {
  display: grid;
  gap: 20px;
  grid-template-columns: minmax(280px, 380px) minmax(0, 1fr);
}
.panel {
  background: #101826;
  border: 1px solid #263244;
  border-radius: 8px;
  padding: 18px;
}
.panel h2 { color: #f8fafc; font-size: 18px; margin-bottom: 14px; }
.field { display: grid; gap: 7px; margin-bottom: 13px; }
.field label { color: #94a3b8; font-size: 12px; font-weight: 700; text-transform: uppercase; }
.field input, .field select, .field textarea {
  background: #0b1220;
  border: 1px solid #263244;
  border-radius: 8px;
  color: #e5e7eb;
  padding: 10px 11px;
  width: 100%;
}
.field textarea { min-height: 95px; resize: vertical; }
.btn {
  align-items: center;
  background: #3251ff;
  border: 0;
  border-radius: 8px;
  color: #fff;
  cursor: pointer;
  display: inline-flex;
  font-weight: 700;
  gap: 8px;
  justify-content: center;
  padding: 11px 14px;
  text-decoration: none;
  width: 100%;
}
.alert { border-radius: 8px; margin-bottom: 12px; padding: 10px 12px; }
.alert.success { background: #14532d; color: #bbf7d0; }
.alert.error { background: #7f1d1d; color: #fecaca; }
.doc-grid { display: grid; gap: 13px; }
.doc-card {
  background: #0b1220;
  border: 1px solid #263244;
  border-radius: 8px;
  padding: 14px;
}
.doc-head {
  align-items: flex-start;
  display: flex;
  gap: 12px;
  justify-content: space-between;
}
.doc-card h3 { color: #f8fafc; font-size: 15px; }
.doc-card p { color: #94a3b8; font-size: 13px; line-height: 1.45; margin-top: 7px; }
.badge {
  border-radius: 999px;
  display: inline-flex;
  font-size: 11px;
  font-weight: 800;
  padding: 6px 9px;
  text-transform: uppercase;
}
.green { background: rgba(34, 197, 94, 0.13); color: #86efac; }
.red { background: rgba(239, 68, 68, 0.13); color: #fca5a5; }
.blue { background: rgba(96, 165, 250, 0.13); color: #93c5fd; }
.doc-link {
  color: #bfdbfe;
  display: inline-flex;
  gap: 8px;
  margin-top: 10px;
  text-decoration: none;
}
.doc-link:hover { text-decoration: underline; }
.empty {
  border: 1px dashed #334155;
  border-radius: 8px;
  color: #94a3b8;
  padding: 28px;
  text-align: center;
}
@media (max-width: 920px) {
  .document-layout { grid-template-columns: 1fr; }
}
</style>
</head>
<body>
<?php renderSidebar('employee', 'employee.documents'); ?>

<div class="main">
  <div class="task-section-header">
    <div>
      <p class="eyebrow">Employee Records</p>
      <h2>My 201 Documents</h2>
    </div>
    <span class="task-count"><?= $documents ? (int) $documents->num_rows : 0; ?> file<?= $documents && $documents->num_rows === 1 ? '' : 's'; ?></span>
  </div>
  <br>
  <?php if (isset($_GET['uploaded'])): ?>
    <div class="alert success"><i class="fa-solid fa-circle-check"></i> Document submitted to HR.</div>
  <?php endif; ?>
  <?php if ($message !== '') echo $message; ?>

  <div class="document-layout">
    <section class="panel">
      <h2><i class="fa-solid fa-file-arrow-up"></i> Submit Document</h2>
      <form method="POST" enctype="multipart/form-data">
        <div class="field">
          <label for="document_type">Document Type</label>
          <select id="document_type" name="document_type" required>
            <option value="">Select type</option>
            <?php foreach ($documentTypes as $type): ?>
              <option value="<?= htmlspecialchars($type); ?>"><?= htmlspecialchars($type); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="title">Title</label>
          <input type="text" id="title" name="title" placeholder="Example: Updated Resume" required>
        </div>
        <div class="field">
          <label for="document_file">File</label>
          <input type="file" id="document_file" name="document_file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
        </div>
        <div class="field">
          <label for="notes">Notes</label>
          <textarea id="notes" name="notes" placeholder="Optional message for HR"></textarea>
        </div>
        <button class="btn" type="submit"><i class="fa-solid fa-upload"></i> Submit to HR</button>
      </form>
    </section>

    <section class="panel">
      <h2><i class="fa-solid fa-folder-open"></i> Submitted Documents</h2>
      <div class="doc-grid">
        <?php if ($documents && $documents->num_rows > 0): ?>
          <?php while ($document = $documents->fetch_assoc()): ?>
            <article class="doc-card">
              <div class="doc-head">
                <div>
                  <h3><?= htmlspecialchars($document['title']); ?></h3>
                  <p><?= htmlspecialchars($document['document_type']); ?> • Uploaded <?= date("M d, Y h:i A", strtotime($document['uploaded_at'])); ?></p>
                </div>
                <span class="badge <?= htmlspecialchars(documentStatusClass($document['status'])); ?>"><?= htmlspecialchars($document['status']); ?></span>
              </div>
              <?php if (!empty($document['notes'])): ?>
                <p><?= nl2br(htmlspecialchars($document['notes'])); ?></p>
              <?php endif; ?>
              <?php if (!empty($document['hr_notes'])): ?>
                <p><strong style="color:#cbd5e1;">HR note:</strong> <?= nl2br(htmlspecialchars($document['hr_notes'])); ?></p>
              <?php endif; ?>
              <a class="doc-link" href="../<?= htmlspecialchars($document['file_path']); ?>" target="_blank">
                <i class="fa-solid fa-download"></i> View File
              </a>
            </article>
          <?php endwhile; ?>
        <?php else: ?>
          <div class="empty">No documents submitted yet.</div>
        <?php endif; ?>
      </div>
    </section>
  </div>
</div>
</body>
</html>
