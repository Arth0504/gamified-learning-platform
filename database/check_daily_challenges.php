<?php
require_once __DIR__ . '/../config/db.php';

echo "Daily Challenges Database Status:\n";

// 1. Fetch current settings
$stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'auto_generate_challenges'");
$stmt->execute();
$auto_gen = $stmt->fetchColumn();
echo "Auto-Generation Setting: " . ($auto_gen === '1' ? "ENABLED" : "DISABLED") . "\n\n";

// 2. Fetch all daily challenges
$challenges = $pdo->query("SELECT * FROM daily_challenges ORDER BY challenge_date DESC")->fetchAll(PDO::FETCH_ASSOC);
echo "Total Challenges in database: " . count($challenges) . "\n";
foreach ($challenges as $c) {
    echo "- ID: {$c['id']}, Date: {$c['challenge_date']}, Type: {$c['target_type']}, Value: {$c['target_value']}, Rewards: {$c['points_reward']} Points / {$c['xp_reward']} XP\n";
    echo "  Desc: \"{$c['description']}\"\n";
    
    // Fetch completions
    $stmt_sc = $pdo->prepare("
        SELECT sc.*, u.username 
        FROM student_challenges sc 
        JOIN students s ON sc.student_id = s.id
        JOIN users u ON s.user_id = u.id
        WHERE sc.challenge_id = ?
    ");
    $stmt_sc->execute([$c['id']]);
    $scs = $stmt_sc->fetchAll(PDO::FETCH_ASSOC);
    echo "  Participation: " . count($scs) . " students\n";
    foreach ($scs as $sc) {
        echo "    * Student: {$sc['username']}, Progress: {$sc['progress_value']}/{$c['target_value']}, Status: {$sc['status']} (" . ($sc['completed_at'] ?? 'In Progress') . ")\n";
    }
}
?>
