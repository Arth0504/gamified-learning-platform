<?php
require_once __DIR__ . '/../config/db.php';

// Find the last created quiz
$quiz_stmt = $pdo->query("SELECT * FROM quizzes ORDER BY id DESC LIMIT 1");
$quiz = $quiz_stmt->fetch(PDO::FETCH_ASSOC);

if (!$quiz) {
    echo "No quizzes found in database.\n";
    exit();
}

$quiz_id = $quiz['id'];
echo "Checking Quiz ID $quiz_id: '" . $quiz['title'] . "'\n";

// Fetch all questions for this quiz
$q_stmt = $pdo->prepare("SELECT * FROM questions WHERE quiz_id = ? ORDER BY id ASC");
$q_stmt->execute([$quiz_id]);
$questions = $q_stmt->fetchAll(PDO::FETCH_ASSOC);

$count = count($questions);
echo "Total Questions in database: $count\n\n";

$has_duplicates = false;
$seen_hashes = [];

// Helper for normalize and hash
function normalize_str($str) {
    return strtolower(preg_replace('/[^a-z0-9]/', '', $str));
}

for ($i = 0; $i < $count; $i++) {
    $q1_text = $questions[$i]['question_text'];
    $q1_norm = normalize_str($q1_text);
    $q1_hash = md5($q1_norm);
    
    echo "Question " . ($i + 1) . ": \"" . $q1_text . "\"\n";
    echo "   Normalized: \"$q1_norm\"\n";
    echo "   MD5 Hash: $q1_hash\n";
    
    // Check against all previous questions
    for ($j = 0; $j < $i; $j++) {
        $q2_text = $questions[$j]['question_text'];
        $q2_norm = normalize_str($q2_text);
        
        // 1. Exact match
        if ($q1_norm === $q2_norm) {
            echo "   --> [DUPLICATE ALERT] Exact match with Question " . ($j + 1) . "!\n";
            $has_duplicates = true;
        }
        
        // 2. Similarity match
        similar_text($q1_text, $q2_text, $percent);
        similar_text($q1_norm, $q2_norm, $percent_norm);
        
        $max_percent = max($percent, $percent_norm);
        if ($max_percent >= 70.0) {
            echo "   --> [DUPLICATE ALERT] Similarity of " . round($max_percent, 1) . "% with Question " . ($j + 1) . "!\n";
            $has_duplicates = true;
        }
    }
    echo "\n";
}

if ($has_duplicates) {
    echo "FAIL: Duplicate questions detected in Quiz ID $quiz_id!\n";
} else {
    echo "SUCCESS: All $count questions are 100% unique (Pairwise similarity < 70%).\n";
}
?>
