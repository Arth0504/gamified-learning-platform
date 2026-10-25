<?php
require_once __DIR__ . '/../config/db.php';

echo "=========================================\n";
echo "    DAILY CHALLENGE CRON EXECUTION       \n";
echo "=========================================\n";
echo "Current Time: " . date('Y-m-d H:i:s') . "\n";

// 1. Generate challenge for Today if missing
$today = date('Y-m-d');
echo "Checking challenge for today ($today)...\n";
$stmt_today = $pdo->prepare("SELECT * FROM daily_challenges WHERE challenge_date = ?");
$stmt_today->execute([$today]);
$chal_today = $stmt_today->fetch(PDO::FETCH_ASSOC);

if (!$chal_today) {
    echo "Today's challenge is missing. Auto-generating...\n";
    // We import the helper function by requiring challenges-helper.php
    require_once __DIR__ . '/../includes/challenges-helper.php';
    $chal_today = get_or_generate_daily_challenge($pdo);
    echo "Generated today's challenge: '{$chal_today['description']}' (ID: {$chal_today['id']})\n";
} else {
    echo "Today's challenge already exists: '{$chal_today['description']}'\n";
}

// 2. Generate challenge for Tomorrow (Pre-generation)
$tomorrow = date('Y-m-d', strtotime('+1 day'));
echo "Checking challenge for tomorrow ($tomorrow)...\n";
$stmt_tom = $pdo->prepare("SELECT * FROM daily_challenges WHERE challenge_date = ?");
$stmt_tom->execute([$tomorrow]);
$chal_tom = $stmt_tom->fetch(PDO::FETCH_ASSOC);

if (!$chal_tom) {
    echo "Tomorrow's challenge is missing. Pre-generating...\n";
    
    $templates = [
        [
            'description' => 'Read at least 1 lesson today',
            'target_type' => 'lesson_read',
            'target_value' => 1,
            'points_reward' => 20,
            'xp_reward' => 50
        ],
        [
            'description' => 'Read at least 2 lessons today',
            'target_type' => 'lesson_read',
            'target_value' => 2,
            'points_reward' => 35,
            'xp_reward' => 80
        ],
        [
            'description' => 'Attempt at least 1 quiz today',
            'target_type' => 'quiz_attempt',
            'target_value' => 1,
            'points_reward' => 20,
            'xp_reward' => 50
        ],
        [
            'description' => 'Score 80% or more in any quiz today',
            'target_type' => 'quiz_score_80',
            'target_value' => 1,
            'points_reward' => 40,
            'xp_reward' => 100
        ],
        [
            'description' => 'Earn 50 XP today',
            'target_type' => 'xp_earn',
            'target_value' => 50,
            'points_reward' => 25,
            'xp_reward' => 50
        ],
        [
            'description' => 'Earn 100 XP today',
            'target_type' => 'xp_earn',
            'target_value' => 100,
            'points_reward' => 50,
            'xp_reward' => 100
        ],
        [
            'description' => 'Maintain your learning streak by doing any activity today',
            'target_type' => 'streak_maintain',
            'target_value' => 1,
            'points_reward' => 15,
            'xp_reward' => 40
        ],
        [
            'description' => 'Complete a Course Activity (read a lesson or take a quiz)',
            'target_type' => 'course_activity',
            'target_value' => 1,
            'points_reward' => 20,
            'xp_reward' => 50
        ],
        [
            'description' => 'Answer 5 quiz questions correctly today',
            'target_type' => 'correct_answers_5',
            'target_value' => 5,
            'points_reward' => 30,
            'xp_reward' => 75
        ],
        [
            'description' => 'Log in to your dashboard today',
            'target_type' => 'daily_login',
            'target_value' => 1,
            'points_reward' => 10,
            'xp_reward' => 30
        ]
    ];
    
    $selected = $templates[array_rand($templates)];
    
    $ins_stmt = $pdo->prepare("
        INSERT INTO daily_challenges (description, target_type, target_value, points_reward, xp_reward, challenge_date)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $ins_stmt->execute([
        $selected['description'],
        $selected['target_type'],
        $selected['target_value'],
        $selected['points_reward'],
        $selected['xp_reward'],
        $tomorrow
    ]);
    
    echo "Generated tomorrow's challenge: '{$selected['description']}'\n";
} else {
    echo "Tomorrow's challenge already exists: '{$chal_tom['description']}'\n";
}

echo "Archiving yesterday's in-progress challenges (if any)...\n";
// Yesterday's date
$yesterday = date('Y-m-d', strtotime('-1 day'));
// Find yesterday's challenge id
$stmt_yes = $pdo->prepare("SELECT id FROM daily_challenges WHERE challenge_date = ?");
$stmt_yes->execute([$yesterday]);
$chal_yes_id = $stmt_yes->fetchColumn();

if ($chal_yes_id) {
    // Note: We don't have an 'expired' state in the ENUM of student_challenges status,
    // but the student history page displays incomplete past challenges as Failed / Expired.
    // We can count how many were left incomplete.
    $cnt_stmt = $pdo->prepare("SELECT COUNT(*) FROM student_challenges WHERE challenge_id = ? AND status = 'in_progress'");
    $cnt_stmt->execute([$chal_yes_id]);
    $incomplete_count = $cnt_stmt->fetchColumn();
    echo "Archived $incomplete_count in-progress challenges from yesterday.\n";
}

echo "Daily challenge cron execution completed successfully.\n";
?>
