<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/challenges-helper.php';

echo "Daily Challenge Progression Test Simulation:\n";

// 1. Get teststudent details
$stmt = $pdo->prepare("
    SELECT s.id AS student_id, u.id AS user_id, u.username, s.points, s.xp 
    FROM users u 
    JOIN students s ON s.user_id = u.id 
    WHERE u.username = 'teststudent'
");
$stmt->execute();
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    echo "teststudent not found. Let's fallback to student 'student'.\n";
    $stmt = $pdo->prepare("
        SELECT s.id AS student_id, u.id AS user_id, u.username, s.points, s.xp 
        FROM users u 
        JOIN students s ON s.user_id = u.id 
        WHERE u.username = 'student'
    ");
    $stmt->execute();
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$student) {
    echo "No student found to run the test.\n";
    exit();
}

$student_id = $student['student_id'];
echo "Testing with Student: {$student['username']} (ID: $student_id)\n";
echo "Initial Stats: Coins: {$student['points']}, XP: {$student['xp']}\n";

// 2. Fetch today's challenge
$today = date('Y-m-d');
$chal_stmt = $pdo->prepare("SELECT * FROM daily_challenges WHERE challenge_date = ?");
$chal_stmt->execute([$today]);
$challenge = $chal_stmt->fetch(PDO::FETCH_ASSOC);

if (!$challenge) {
    echo "No challenge exists for today. Let's pre-generate one.\n";
    $challenge = get_or_generate_daily_challenge($pdo);
}

echo "Today's Challenge: '{$challenge['description']}' (Type: {$challenge['target_type']}, Target: {$challenge['target_value']})\n";

// 3. Simulating progress increment
echo "Simulating activity: answering 5 questions correctly...\n";
// Track progress
track_student_challenge_progress($pdo, $student_id, $challenge['target_type'], 5);

// 4. Fetch stats and challenge status after simulation
$sc_stmt = $pdo->prepare("SELECT * FROM student_challenges WHERE student_id = ? AND challenge_id = ?");
$sc_stmt->execute([$student_id, $challenge['id']]);
$sc = $sc_stmt->fetch(PDO::FETCH_ASSOC);

$stud_stmt = $pdo->prepare("SELECT points, xp FROM students WHERE id = ?");
$stud_stmt->execute([$student_id]);
$student_after = $stud_stmt->fetch(PDO::FETCH_ASSOC);

echo "\nAfter Simulation Stats:\n";
echo "Coins: {$student_after['points']} (Gained: " . ($student_after['points'] - $student['points']) . ")\n";
echo "XP: {$student_after['xp']} (Gained: " . ($student_after['xp'] - $student['xp']) . ")\n";
echo "Challenge Progress: {$sc['progress_value']}/{$challenge['target_value']}\n";
echo "Challenge Status: {$sc['status']} (Completed At: " . ($sc['completed_at'] ?? 'N/A') . ")\n";

if ($sc['status'] === 'completed') {
    echo "\n[PASS] Daily Challenge completed and rewards successfully credited!\n";
} else {
    echo "\n[FAIL] Daily Challenge did not complete.\n";
}
?>
