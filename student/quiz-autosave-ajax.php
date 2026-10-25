<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/middleware.php';

// Force JSON response
header('Content-Type: application/json');

// Ensure user is logged in as student
if (!isset($_SESSION['user_id']) || ($_SESSION['role_name'] ?? '') !== 'Student') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$quiz_id = intval($_POST['quiz_id'] ?? 0);
$answers = $_POST['answers'] ?? [];

if (!$quiz_id) {
    echo json_encode(['status' => 'error', 'message' => 'Missing Quiz ID']);
    exit();
}

// Fetch Quiz to verify it exists and get time limit
$quiz_stmt = $pdo->prepare("SELECT time_limit_mins FROM quizzes WHERE id = ?");
$quiz_stmt->execute([$quiz_id]);
$quiz = $quiz_stmt->fetch();

if (!$quiz) {
    echo json_encode(['status' => 'error', 'message' => 'Quiz not found']);
    exit();
}

// Ensure session exists
if (!isset($_SESSION['quiz'][$quiz_id])) {
    $_SESSION['quiz'][$quiz_id] = [
        'start_time' => time(),
        'answers' => [],
        'security_log_id' => null
    ];
}

// Update answers in session
foreach ($answers as $q_id => $val) {
    $_SESSION['quiz'][$quiz_id]['answers'][intval($q_id)] = $val;
}

// Calculate remaining time
$time_limit_secs = intval($quiz['time_limit_mins']) * 60;
$time_elapsed = time() - $_SESSION['quiz'][$quiz_id]['start_time'];
$time_left = max(0, $time_limit_secs - $time_elapsed);

echo json_encode([
    'status' => 'success',
    'time_left' => $time_left,
    'answers_saved_count' => count($_SESSION['quiz'][$quiz_id]['answers'])
]);
?>
