<?php
require_once __DIR__ . '/../includes/auth_check.php';
checkRole(['hr']);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/task_schema.php';

ensureTaskSchema($conn);
$hr_id = (int) $_SESSION['user_id'];

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) die("Invalid Task ID");

/* GET TASK */
$stmt = $conn->prepare("
    SELECT t.*, u.full_name
    FROM tasks t
    JOIN users u ON t.employee_id = u.id
    WHERE t.id = ? AND t.hr_id = ?
");
$stmt->bind_param("ii", $id, $hr_id);
$stmt->execute();
$result = $stmt->get_result();
if (!$result || $result->num_rows == 0) die("Task not found");
$task = $result->fetch_assoc();

$filePath = "../uploads/submissions/" . $task['submission_file'];

/* =========================
   EXTRACT TEXT FROM FILE
========================= */
function extractText($filePath)
{
    if (!file_exists($filePath)) return "";
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

    if ($ext === "txt") {
        return trim(file_get_contents($filePath));
    }

    if ($ext === "docx") {
        $zip = new ZipArchive;
        if ($zip->open($filePath) === true) {
            $xml = $zip->getFromName("word/document.xml");
            $zip->close();
            if ($xml) {
                $text = strip_tags($xml);
                $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
                return trim(preg_replace('/\s+/', ' ', $text));
            }
        }
        return "";
    }

    if ($ext === "pdf") {
        $escaped = escapeshellarg($filePath);
        $output  = shell_exec("pdftotext $escaped -");
        return $output ? trim(preg_replace('/\s+/', ' ', $output)) : "";
    }

    return "";
}

/* =========================
   GEMINI AI SCORING — FIXED
   Handles markdown-wrapped JSON,
   malformed responses, and saves
   detailed sub-scores to the DB.
========================= */
function getGeminiScore($text)
{
    $apiKey = "AIzaSyAzXXOJ0ySfvwxTLflBrfSCGl6dGE2tAbo";
    $text   = substr($text, 0, 8000);

    $prompt = <<<PROMPT
You are an HR document evaluator. Analyze the text below and return ONLY a valid JSON object — no markdown, no backticks, no explanation outside the JSON.

Return exactly this structure:
{
  "overall_score": <integer 0-100>,
  "grammar_score": <integer 0-100>,
  "spelling_score": <integer 0-100>,
  "clarity_score": <integer 0-100>,
  "relevance_score": <integer 0-100>,
  "feedback": "<concise paragraph explaining the scores>"
}

Scoring rubric:
- grammar_score: sentence structure, tense consistency, punctuation
- spelling_score: correctly spelled words, no typos
- clarity_score: easy to understand, logical flow, well-organized
- relevance_score: stays on topic, addresses the task purpose
- overall_score: weighted average of the four sub-scores

TEXT TO EVALUATE:
$text
PROMPT;

    $payload = json_encode([
        "contents" => [[
            "parts" => [["text" => $prompt]]
        ]]
    ]);

    $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=$apiKey");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ["Content-Type: application/json"],
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 30,
    ]);

    $response = curl_exec($ch);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        return defaultResult("cURL error: $curlErr");
    }

    $res = json_decode($response, true);
    $raw = $res['candidates'][0]['content']['parts'][0]['text'] ?? "";

    /* Strip markdown fences if Gemini wraps output */
    $cleaned = preg_replace('/```(?:json)?\s*/i', '', $raw);
    $cleaned = preg_replace('/```/', '', $cleaned);
    $cleaned = trim($cleaned);

    /* Extract first {...} block (handles leading/trailing garbage) */
    if (preg_match('/\{.*\}/s', $cleaned, $matches)) {
        $cleaned = $matches[0];
    }

    $data = json_decode($cleaned, true);

    if (json_last_error() !== JSON_ERROR_NONE || !isset($data['overall_score'])) {
        return defaultResult("AI returned an unparseable response. Raw: " . htmlspecialchars(substr($raw, 0, 300)));
    }

    return [
        'overall_score'   => clamp((int)($data['overall_score']   ?? 0)),
        'grammar_score'   => clamp((int)($data['grammar_score']   ?? 0)),
        'spelling_score'  => clamp((int)($data['spelling_score']  ?? 0)),
        'clarity_score'   => clamp((int)($data['clarity_score']   ?? 0)),
        'relevance_score' => clamp((int)($data['relevance_score'] ?? 0)),
        'feedback'        => htmlspecialchars(trim($data['feedback'] ?? 'No feedback provided.')),
    ];
}

function clamp(int $v): int { return max(0, min(100, $v)); }

function defaultResult(string $msg): array {
    return [
        'overall_score'   => 0,
        'grammar_score'   => 0,
        'spelling_score'  => 0,
        'clarity_score'   => 0,
        'relevance_score' => 0,
        'feedback'        => $msg,
    ];
}

/* =========================
   SCORE COLOR HELPER
========================= */
function scoreColor(int $score): string {
    if ($score >= 80) return '#22c55e'; // green
    if ($score >= 55) return '#f59e0b'; // amber
    return '#ef4444';                   // red
}

/* =========================
   MAIN LOGIC
========================= */
$ai = [];

/* Use cached scores if already reviewed */
if (!empty($task['ai_feedback'])) {
    $ai = [
        'overall_score'   => (int)$task['ai_score'],
        'grammar_score'   => (int)$task['grammar_score'],
        'spelling_score'  => (int)$task['spelling_score'],
        'clarity_score'   => (int)$task['clarity_score'],
        'relevance_score' => (int)$task['relevance_score'],
        'feedback'        => htmlspecialchars($task['ai_feedback']),
    ];
} elseif (!empty($task['submission_file']) && file_exists($filePath)) {
    $text = extractText($filePath);

    if (!empty(trim($text))) {
        $ai = getGeminiScore($text);
    } else {
        $ai = defaultResult("Text extraction failed. The file may be image-based or an unsupported format.");
        $ai['overall_score'] = 50;
    }

    /* Save scores to DB so re-opening doesn't re-call Gemini */
    $save = $conn->prepare("
        UPDATE tasks
        SET ai_score        = ?,
            grammar_score   = ?,
            spelling_score  = ?,
            clarity_score   = ?,
            relevance_score = ?,
            ai_feedback     = ?,
            status          = 'reviewed'
        WHERE id = ?
    ");
    $save->bind_param(
        "iiiiisi",
        $ai['overall_score'],
        $ai['grammar_score'],
        $ai['spelling_score'],
        $ai['clarity_score'],
        $ai['relevance_score'],
        $ai['feedback'],
        $id
    );
    $save->execute();
} else {
    $ai = defaultResult("No submission found.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Task Submission Review</title>
<link rel="stylesheet" href="../hr/dashboard.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
.main { margin-left: 240px; padding: 25px; color: #e5e7eb; }

.card-box {
    background: #111827;
    border-radius: 14px;
    padding: 20px;
    margin-bottom: 20px;
    border: 1px solid #1f2937;
}

/* OVERALL SCORE CIRCLE */
.score-circle {
    width: 140px;
    height: 140px;
    border-radius: 50%;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    font-size: 38px;
    font-weight: bold;
    margin: 0 auto 20px;
    border: 5px solid;
    transition: background 0.3s;
}

/* SUB-SCORE GRID */
.sub-scores {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 14px;
    margin-bottom: 20px;
}

.sub-card {
    background: #0f172a;
    border-radius: 10px;
    padding: 14px;
    text-align: center;
    border: 1px solid #1f2937;
}

.sub-card .label {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #9ca3af;
    margin-bottom: 6px;
}

.sub-card .value {
    font-size: 28px;
    font-weight: 700;
}

/* SCORE BAR */
.bar-wrap { background: #1f2937; border-radius: 999px; height: 8px; margin-top: 6px; overflow: hidden; }
.bar-fill  { height: 100%; border-radius: 999px; transition: width 0.6s ease; }

.feedback {
    background: #0f172a;
    padding: 16px;
    border-radius: 10px;
    color: #cbd5e1;
    white-space: pre-wrap;
    line-height: 1.6;
    font-size: 14px;
}
</style>
</head>
<body>

<?php renderSidebar('hr', 'hr.dashboard'); ?>

<div class="main">

<div class="card-box">
    <h2><i class="fa-solid fa-file-lines"></i> Task Submission Review</h2>
    <p><b>Employee:</b> <?= htmlspecialchars($task['full_name']) ?></p>
    <p><b>Task:</b>     <?= htmlspecialchars($task['title']) ?></p>
    <p><b>Deadline:</b> <?= $task['deadline'] ? date("M d, Y", strtotime($task['deadline'])) : 'No deadline' ?></p>
    <p><b>Submitted:</b>
        <?= $task['submitted_at']
            ? date("M d, Y h:i A", strtotime($task['submitted_at']))
            : '<span style="color:#9ca3af;">Not yet submitted</span>' ?>
    </p>
    <?php if (!empty($task['submission_file'])): ?>
    <a href="../uploads/submissions/<?= htmlspecialchars($task['submission_file']) ?>"
       target="_blank"
       style="color:#60a5fa;font-size:13px;">
        📄 Download Submission
    </a>
    <?php endif; ?>
</div>

<div class="card-box">
    <h3 style="margin-bottom:18px;">🤖 AI Evaluation Results</h3>

    <?php
    $oc = scoreColor($ai['overall_score']);
    ?>
    <!-- OVERALL SCORE CIRCLE -->
    <div class="score-circle" style="background:<?= $oc ?>22; border-color:<?= $oc ?>; color:<?= $oc ?>;">
        <span><?= $ai['overall_score'] ?></span>
        <span style="font-size:12px;font-weight:400;color:#9ca3af;">/ 100</span>
    </div>

    <!-- SUB-SCORES -->
    <div class="sub-scores">
        <?php
        $subs = [
            'Grammar'    => $ai['grammar_score'],
            'Spelling'   => $ai['spelling_score'],
            'Clarity'    => $ai['clarity_score'],
            'Relevance'  => $ai['relevance_score'],
        ];
        foreach ($subs as $label => $val):
            $c = scoreColor($val);
        ?>
        <div class="sub-card">
            <div class="label"><?= $label ?></div>
            <div class="value" style="color:<?= $c ?>"><?= $val ?></div>
            <div class="bar-wrap">
                <div class="bar-fill" style="width:<?= $val ?>%;background:<?= $c ?>;"></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- FEEDBACK -->
    <h4 style="margin-bottom:8px;color:#9ca3af;">AI Feedback</h4>
    <div class="feedback"><?= $ai['feedback'] ?></div>
</div>

</div>
</body>
</html>
