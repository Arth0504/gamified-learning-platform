<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/config.php';

if (!defined('TESTING_MODE')) {
    header('Content-Type: application/json');

    // Ensure only teachers can access
    if (session_status() === PHP_SESSION_NONE || !isset($_SESSION['role_name']) || $_SESSION['role_name'] !== 'Teacher') {
        echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
        exit();
    }

    // Verify CSRF
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        echo json_encode(['success' => false, 'message' => 'CSRF validation failed.']);
        exit();
    }

    $course_id = intval($_POST['course_id'] ?? 0);
    $subject_id = intval($_POST['subject_id'] ?? 0);
    $lesson_id = intval($_POST['lesson_id'] ?? 0);
    $topic = trim($_POST['topic'] ?? '');
    $difficulty = $_POST['difficulty'] ?? 'easy';
    $raw_questions = $_POST['questions'] ?? '';
    $questions_count = intval($_POST['questions_count'] ?? 0);

    if (empty($topic) || empty($raw_questions) || $course_id === 0) {
        echo json_encode(['success' => false, 'message' => 'Required parameters missing.']);
        exit();
    }

    $questions = json_decode($raw_questions, true);
    if (!is_array($questions) || empty($questions)) {
        echo json_encode(['success' => false, 'message' => 'Invalid questions data format.']);
        exit();
    }
}

// Helper functions for deduplication in Save Quiz
function is_duplicate_question_save($new_text, $existing_texts, $similarity_threshold = 70.0, $topic = '') {
    $strip_phrases = [
        'fill in the blank based on the lesson: "',
        'true or false: according to the lesson content, "',
        'based on the lesson content, complete the statement: "',
        '" (topic: nouns & pronouns)',
        '" (topic: nouns and pronouns)',
        '"'
    ];
    if (!empty($topic)) {
        $t_lower = strtolower($topic);
        $strip_phrases[] = $t_lower;
        $strip_phrases[] = str_replace(' and ', ' & ', $t_lower);
        $strip_phrases[] = str_replace(' & ', ' and ', $t_lower);
    }
    
    $clean_new = strtolower($new_text);
    foreach ($strip_phrases as $phrase) {
        $clean_new = str_replace($phrase, '', $clean_new);
    }
    $clean_new = trim($clean_new, '." ');
    
    $new_clean = preg_replace('/[^a-z0-9]/', '', $clean_new);
    $new_hash = md5($new_clean);
    
    foreach ($existing_texts as $exist_text) {
        $clean_exist = strtolower($exist_text);
        foreach ($strip_phrases as $phrase) {
            $clean_exist = str_replace($phrase, '', $clean_exist);
        }
        $clean_exist = trim($clean_exist, '." ');
        
        $exist_clean = preg_replace('/[^a-z0-9]/', '', $clean_exist);
        $exist_hash = md5($exist_clean);
        
        if ($new_hash === $exist_hash || $new_clean === $exist_clean) {
            return true;
        }
        
        similar_text($clean_new, $clean_exist, $percent);
        if ($percent >= $similarity_threshold) {
            return true;
        }
        
        similar_text($new_clean, $exist_clean, $percent_clean);
        if ($percent_clean >= $similarity_threshold) {
            return true;
        }
    }
    return false;
}

function has_duplicate_options_save($options) {
    $seen_options = [];
    foreach ($options as $opt) {
        $text = strtolower(trim($opt['option_text'] ?? ''));
        if (empty($text)) continue;
        if (in_array($text, $seen_options)) {
            return true;
        }
        $seen_options[] = $text;
    }
    return false;
}

if (!defined('TESTING_MODE')) {
// Check for duplicate questions and options
$seen_questions = [];
$seen_hashes = [];
foreach ($questions as $q) {
    $q_text = trim($q['question_text'] ?? '');
    if (empty($q_text)) continue;
    
    $normalized = strtolower(preg_replace('/[^a-z0-9]/', '', $q_text));
    $hash = md5($normalized);
    
    if (in_array($hash, $seen_hashes)) {
        echo json_encode(['success' => false, 'message' => 'Duplicate question detected (Exact hash collision): "' . htmlspecialchars($q_text) . '". All questions must be unique.']);
        exit();
    }
    
    if (is_duplicate_question_save($q_text, $seen_questions, 70.0, $topic)) {
        echo json_encode(['success' => false, 'message' => 'Duplicate or near-duplicate question detected (Similarity > 70%): "' . htmlspecialchars($q_text) . '". All questions must be unique.']);
        exit();
    }
    if (has_duplicate_options_save($q['options'] ?? [])) {
        echo json_encode(['success' => false, 'message' => 'Duplicate option texts detected inside question: "' . htmlspecialchars($q_text) . '". Option sets must be unique.']);
        exit();
    }
    
    $seen_questions[] = $q_text;
    $seen_hashes[] = $hash;
}

if ($questions_count > 0 && count($seen_questions) !== $questions_count) {
    echo json_encode(['success' => false, 'message' => 'Unique questions count (' . count($seen_questions) . ') does not match the requested count (' . $questions_count . ').']);
    exit();
}

try {
    $pdo->beginTransaction();

    // Verify course ownership
    $user_id = $_SESSION['user_id'];
    $t_stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
    $t_stmt->execute([$user_id]);
    $teacher_id = $t_stmt->fetchColumn() ?: 0;

    $c_stmt = $pdo->prepare("SELECT id FROM courses WHERE id = ? AND teacher_id = ?");
    $c_stmt->execute([$course_id, $teacher_id]);
    if (!$c_stmt->fetch()) {
        throw new Exception("Unauthorized course access.");
    }

    // Calculate dynamic rewards based on question points
    $total_points = 0;
    foreach ($questions as $q) {
        $total_points += ($difficulty === 'easy') ? 10 : (($difficulty === 'medium') ? 15 : 20);
    }
    
    $points_reward = intval($total_points * 0.5);
    $xp_reward = $total_points * 2; // double points as XP

    // 1. Insert the Quiz
    $quiz_title = $topic . " AI Quiz (" . ucfirst($difficulty) . ")";
    $quiz_desc = "AI-generated quiz focusing on: " . $topic;
    
    // Link to the selected lesson_id if set, or fallback to the first lesson in the chosen subject
    $final_lesson_id = ($lesson_id > 0) ? $lesson_id : null;
    if (!$final_lesson_id && $subject_id > 0) {
        $l_stmt = $pdo->prepare("SELECT id FROM lessons WHERE subject_id = ? ORDER BY sort_order ASC LIMIT 1");
        $l_stmt->execute([$subject_id]);
        $final_lesson_id = $l_stmt->fetchColumn() ?: null;
    }

    $quiz_insert = $pdo->prepare("
        INSERT INTO quizzes (lesson_id, course_id, title, description, time_limit_mins, passing_score, max_attempts, points_reward, xp_reward)
        VALUES (?, ?, ?, ?, 20, 60, 3, ?, ?)
    ");
    $quiz_insert->execute([$final_lesson_id, $course_id, $quiz_title, $quiz_desc, $points_reward, $xp_reward]);
    $quiz_id = $pdo->lastInsertId();

    // 2. Insert questions and options
    foreach ($questions as $q) {
        $q_text = trim($q['question_text']);
        $q_type = $q['question_type'];
        $q_points = ($difficulty === 'easy') ? 10 : (($difficulty === 'medium') ? 15 : 20);
        $opts = $q['options'] ?? [];

        $q_insert = $pdo->prepare("
            INSERT INTO questions (quiz_id, question_text, question_type, points, difficulty)
            VALUES (?, ?, ?, ?, ?)
        ");
        $q_insert->execute([$quiz_id, $q_text, $q_type, $q_points, $difficulty]);
        $question_id = $pdo->lastInsertId();

        if ($q_type === 'mcq' || $q_type === 'tf') {
            foreach ($opts as $opt) {
                $opt_text = trim($opt['option_text']);
                $is_correct = intval($opt['is_correct'] ?? 0);

                $opt_insert = $pdo->prepare("
                    INSERT INTO question_options (question_id, option_text, is_correct)
                    VALUES (?, ?, ?)
                ");
                $opt_insert->execute([$question_id, $opt_text, $is_correct]);
            }
        } elseif ($q_type === 'short_answer') {
            $ans_text = trim($opts[0]['option_text'] ?? '');
            if (!empty($ans_text)) {
                $opt_insert = $pdo->prepare("
                    INSERT INTO question_options (question_id, option_text, is_correct)
                    VALUES (?, ?, 1)
                ");
                $opt_insert->execute([$question_id, $ans_text]);
            }
        }
    }

    $pdo->commit();
    
    // Log Activity
    log_activity($pdo, $_SESSION['user_id'], 'Generate AI Quiz', "Created AI quiz: $quiz_title");

    echo json_encode(['success' => true, 'message' => 'AI Quiz created and saved successfully!']);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error saving quiz: ' . $e->getMessage()]);
}
exit();
}
?>
