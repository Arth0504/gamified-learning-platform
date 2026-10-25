<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/middleware.php';

// Force JSON response
header('Content-Type: application/json');

// Ensure student is logged in
if (!isset($_SESSION['user_id']) || ($_SESSION['role_name'] ?? '') !== 'Student') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$quiz_id = intval($_POST['quiz_id'] ?? 0);
$action = $_POST['action'] ?? '';

if (!$quiz_id) {
    echo json_encode(['status' => 'error', 'message' => 'Missing Quiz ID']);
    exit();
}

if ($action === 'start') {
    // Check if security log already exists for this active session in $_SESSION
    $log_id = $_SESSION['quiz'][$quiz_id]['security_log_id'] ?? null;
    
    if (!$log_id) {
        // Initialize security log record
        $stmt = $pdo->prepare("INSERT INTO quiz_security_logs (user_id, quiz_id, warning_count, focus_loss_count, fullscreen_exit_count) VALUES (?, ?, 0, 0, 0)");
        $stmt->execute([$user_id, $quiz_id]);
        $log_id = $pdo->lastInsertId();
        
        $_SESSION['quiz'][$quiz_id]['security_log_id'] = $log_id;
    }
    
    echo json_encode(['status' => 'success', 'log_id' => $log_id]);
    exit();
}

if ($action === 'update_violations') {
    $log_id = $_SESSION['quiz'][$quiz_id]['security_log_id'] ?? null;
    
    $warning_count = intval($_POST['warning_count'] ?? 0);
    $focus_loss_count = intval($_POST['focus_loss_count'] ?? 0);
    $fullscreen_exit_count = intval($_POST['fullscreen_exit_count'] ?? 0);
    $exit_attempts_count = intval($_POST['exit_attempts_count'] ?? 0);
    
    if ($log_id) {
        $stmt = $pdo->prepare("
            UPDATE quiz_security_logs 
            SET warning_count = ?, focus_loss_count = ?, fullscreen_exit_count = ?, exit_attempts_count = ? 
            WHERE id = ?
        ");
        $stmt->execute([$warning_count, $focus_loss_count, $fullscreen_exit_count, $exit_attempts_count, $log_id]);
        echo json_encode(['status' => 'success', 'message' => 'Log updated']);
    } else {
        // Fallback: update latest row
        $stmt = $pdo->prepare("
            UPDATE quiz_security_logs 
            SET warning_count = ?, focus_loss_count = ?, fullscreen_exit_count = ?, exit_attempts_count = ? 
            WHERE user_id = ? AND quiz_id = ? 
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([$warning_count, $focus_loss_count, $fullscreen_exit_count, $exit_attempts_count, $user_id, $quiz_id]);
        echo json_encode(['status' => 'success', 'message' => 'Fallback log updated']);
    }
    exit();
}

if ($action === 'exit_attempt') {
    $log_id = $_SESSION['quiz'][$quiz_id]['security_log_id'] ?? null;
    if ($log_id) {
        $stmt = $pdo->prepare("UPDATE quiz_security_logs SET exit_attempts_count = exit_attempts_count + 1 WHERE id = ?");
        $stmt->execute([$log_id]);
    } else {
        $stmt = $pdo->prepare("UPDATE quiz_security_logs SET exit_attempts_count = exit_attempts_count + 1 WHERE user_id = ? AND quiz_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$user_id, $quiz_id]);
    }
    echo json_encode(['status' => 'success', 'message' => 'Exit attempt recorded']);
    exit();
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
?>
