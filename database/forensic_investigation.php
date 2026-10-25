<?php
// Define TESTING_MODE so we can load the functions without executing the procedural logic
define('TESTING_MODE', true);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../teacher/generate-ai-quiz-ajax.php';
require_once __DIR__ . '/../teacher/save-ai-quiz-ajax.php';

echo "==================================================\n";
echo "FORENSIC INVESTIGATION: 20-QUESTION LIFECYCLE TRACE\n";
echo "==================================================\n\n";

// 1. STAGE 1: AI / Fallback generation
$topic = "Nouns and Pronouns";
$difficulty = "easy";
$questions_count = 20;

// Fetch lesson contents to simulate context
$subject_id = 1;
$lesson_id = 1;

$lessons_stmt = $pdo->prepare("SELECT title, content FROM lessons WHERE id = ? AND subject_id = ?");
$lessons_stmt->execute([$lesson_id, $subject_id]);
$lessons_list = $lessons_stmt->fetchAll(PDO::FETCH_ASSOC);

$context_content = "";
foreach ($lessons_list as $l_item) {
    $context_content .= "Lesson: " . $l_item['title'] . "\nContent:\n" . $l_item['content'] . "\n\n";
}

$subj_stmt = $pdo->prepare("SELECT title FROM subjects WHERE id = ?");
$subj_stmt->execute([$subject_id]);
$subject_title = $subj_stmt->fetchColumn() ?: 'General';

// Call the fallback generator
echo "--- STAGE 1: Generated Questions (Before Save) ---\n";
$generated_questions = generate_fallback_questions($topic, $difficulty, $questions_count, $context_content, $subject_title);

if (count($generated_questions) !== $questions_count) {
    echo "[ERROR] Generated count (" . count($generated_questions) . ") does not match requested ($questions_count)\n";
}

$stage1_texts = [];
foreach ($generated_questions as $idx => $q) {
    $stage1_texts[$idx] = $q['question_text'];
    echo "Q" . ($idx + 1) . ": {$q['question_text']}\n";
    if ($q['question_type'] === 'mcq' || $q['question_type'] === 'tf') {
        foreach ($q['options'] as $opt) {
            echo "   - [ " . ($opt['is_correct'] ? 'X' : ' ') . " ] {$opt['option_text']}\n";
        }
    } else {
        echo "   - Expected Answer: {$q['options'][0]['option_text']}\n";
    }
}

// 2. STAGE 2: Save Process validation
echo "\n--- STAGE 2: Save Process (Simulating save-ai-quiz-ajax.php verification) ---\n";
$seen_questions = [];
$seen_hashes = [];
$has_duplicates = false;

foreach ($generated_questions as $idx => $q) {
    $q_text = trim($q['question_text'] ?? '');
    $normalized = strtolower(preg_replace('/[^a-z0-9]/', '', $q_text));
    $hash = md5($normalized);
    
    if (in_array($hash, $seen_hashes)) {
        echo "[ERROR] Duplicate exact hash detected for Q" . ($idx + 1) . ": '$q_text'\n";
        $has_duplicates = true;
    }
    
    if (is_duplicate_question_save($q_text, $seen_questions, 70.0, $topic)) {
        echo "[ERROR] Near-duplicate similarity detected for Q" . ($idx + 1) . ": '$q_text'\n";
        $has_duplicates = true;
    }
    
    $seen_questions[] = $q_text;
    $seen_hashes[] = $hash;
}

if (!$has_duplicates) {
    echo "[SUCCESS] Save validation PASSED: all 20 questions are unique and valid!\n";
} else {
    echo "[FAIL] Save validation FAILED due to duplicates.\n";
}

// Perform actual DB inserts inside a transaction to inspect records, then roll back
try {
    $pdo->beginTransaction();
    
    $quiz_insert = $pdo->prepare("
        INSERT INTO quizzes (lesson_id, course_id, title, description, time_limit_mins, passing_score, max_attempts, points_reward, xp_reward)
        VALUES (?, 1, 'Forensic Test Quiz', 'Trace test', 20, 60, 3, 50, 100)
    ");
    $quiz_insert->execute([$lesson_id]);
    $quiz_id = $pdo->lastInsertId();
    
    $q_insert = $pdo->prepare("
        INSERT INTO questions (quiz_id, question_text, question_type, points, difficulty)
        VALUES (?, ?, ?, ?, ?)
    ");
    $opt_insert = $pdo->prepare("
        INSERT INTO question_options (question_id, option_text, is_correct)
        VALUES (?, ?, ?)
    ");
    
    foreach ($generated_questions as $q) {
        $q_insert->execute([$quiz_id, $q['question_text'], $q['question_type'], 10, $difficulty]);
        $question_id = $pdo->lastInsertId();
        
        foreach ($q['options'] as $opt) {
            $opt_insert->execute([$question_id, $opt['option_text'], $opt['is_correct']]);
        }
    }
    
    // 3. STAGE 3: Database records verification
    echo "\n--- STAGE 3: Questions Retrieved from Database ---\n";
    $db_q_stmt = $pdo->prepare("SELECT id, question_text, question_type FROM questions WHERE quiz_id = ? ORDER BY id ASC");
    $db_q_stmt->execute([$quiz_id]);
    $db_questions = $db_q_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stage3_texts = [];
    foreach ($db_questions as $idx => $q) {
        $stage3_texts[$idx] = $q['question_text'];
        echo "Q" . ($idx + 1) . " (DB ID: {$q['id']}): {$q['question_text']}\n";
        
        $db_opt_stmt = $pdo->prepare("SELECT option_text, is_correct FROM question_options WHERE question_id = ? ORDER BY id ASC");
        $db_opt_stmt->execute([$q['id']]);
        $opts = $db_opt_stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($opts as $opt) {
            echo "   - [ " . ($opt['is_correct'] ? 'X' : ' ') . " ] {$opt['option_text']}\n";
        }
    }
    
    // 4. STAGE 4: Student view rendering query simulation
    echo "\n--- STAGE 4: Student View Rendering (student/quiz-view.php simulation) ---\n";
    $student_q_stmt = $pdo->prepare("SELECT * FROM questions WHERE quiz_id = ? ORDER BY id ASC");
    $student_q_stmt->execute([$quiz_id]);
    $student_questions = $student_q_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stage4_texts = [];
    foreach ($student_questions as $idx => $q) {
        $stage4_texts[$idx] = $q['question_text'];
        echo "Q" . ($idx + 1) . ": {$q['question_text']} [type: {$q['question_type']}]\n";
        
        // Emulate option retrieval
        $opt_stmt = $pdo->prepare("SELECT id, option_text FROM question_options WHERE question_id = ? ORDER BY id ASC");
        $opt_stmt->execute([$q['id']]);
        $opts = $opt_stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($opts as $opt) {
            echo "   - Option ID {$opt['id']}: {$opt['option_text']}\n";
        }
    }
    
    // 5. Stage comparisons
    echo "\n=== FORENSIC STAGE COMPARISON ===\n";
    $mismatches = 0;
    for ($i = 0; $i < $questions_count; $i++) {
        $t1 = $stage1_texts[$i] ?? 'N/A';
        $t3 = $stage3_texts[$i] ?? 'N/A';
        $t4 = $stage4_texts[$i] ?? 'N/A';
        
        echo "Q" . ($i + 1) . ":\n";
        echo "  - Stage 1 (Before Save): '$t1'\n";
        echo "  - Stage 3 (In Database): '$t3'\n";
        echo "  - Stage 4 (Student View): '$t4'\n";
        
        if ($t1 !== $t3 || $t3 !== $t4) {
            echo "  [MISMATCH DETECTED!]\n";
            $mismatches++;
        } else {
            echo "  [OK]\n";
        }
    }
    
    echo "\nTotal Mismatches Across Stages: $mismatches\n";
    
    $pdo->rollBack();
    echo "\nDatabase transaction rolled back successfully.\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "Error during DB stage: " . $e->getMessage() . "\n";
}
