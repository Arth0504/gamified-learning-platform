<?php
define('TESTING_MODE', true);
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../teacher/generate-ai-quiz-ajax.php';

echo "Database connected successfully.\n";

// Let's verify our duplicate checking function works correctly
$test_q1 = "What is a proper noun?";
$test_q2 = "What is proper noun?"; // near identical
$test_q3 = "Which of the following is a pronoun?"; // different

echo "Comparing near-duplicates:\n";
similar_text(strtolower($test_q1), strtolower($test_q2), $sim);
echo "Similarity between '$test_q1' and '$test_q2': $sim%\n";

$is_dup = is_duplicate_question($test_q1, [$test_q2], 70.0);
echo "is_duplicate_question result (threshold 70%): " . ($is_dup ? "DUPLICATE" : "UNIQUE") . "\n";

// Let's generate a 20-question fallback quiz
$topic = "Nouns and Pronouns";
$difficulty = "easy";
$count = 20;

echo "\nGenerating fallback questions ($count count) for topic '$topic'...\n";
$questions = generate_fallback_questions($topic, $difficulty, $count);

echo "Total generated questions: " . count($questions) . "\n";

// Let's verify pairwise uniqueness for all 20 generated questions
$has_duplicates = false;
$seen = [];
foreach ($questions as $idx => $q) {
    $text = $q['question_text'];
    echo "Question " . ($idx + 1) . ": $text\n";
    
    $dup_match = null;
    foreach ($seen as $s_q) {
        if (is_duplicate_question($text, [$s_q], 70.0, 'Nouns and Pronouns')) {
            $dup_match = $s_q;
            break;
        }
    }
    if ($dup_match) {
        echo "--> [ERROR] Duplicate detected for: '$text' (Matched with: '$dup_match')\n";
        $has_duplicates = true;
    }
    $seen[] = $text;
}

if (!$has_duplicates) {
    echo "\n[SUCCESS] All 20 questions are pairwise unique!\n";
} else {
    echo "\n[FAIL] Duplicates were found!\n";
}
?>
