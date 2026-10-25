<?php
// Set layout variables if starting secure quiz session
$start_param = $_GET['start'] ?? null;
if ($start_param === '1') {
    $no_layout = true;
    $simple_footer = true;
}

$page_title = "Quiz Portal";
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/middleware.php';

require_role('Student');

$user_id = $_SESSION['user_id'];
$quiz_id = $_GET['quiz_id'] ?? $_POST['quiz_id'] ?? null;

if (!$quiz_id) {
    set_flash_message('danger', 'Quiz ID is missing.');
    header("Location: courses.php");
    exit();
}

// Get Student ID
$stmt = $pdo->prepare("SELECT id, points, xp FROM students WHERE user_id = ?");
$stmt->execute([$user_id]);
$student = $stmt->fetch();
$student_id = $student['id'] ?? 0;

if (!$student_id) {
    set_flash_message('danger', 'Student profile not found.');
    header("Location: " . BASE_URL . "login.php");
    exit();
}

// Fetch Quiz details and verify enrollment
$quiz_stmt = $pdo->prepare("
    SELECT q.*, c.title AS course_title, c.id AS course_id, l.title AS lesson_title 
    FROM quizzes q 
    JOIN courses c ON q.course_id = c.id 
    LEFT JOIN lessons l ON q.lesson_id = l.id 
    WHERE q.id = ?
");
$quiz_stmt->execute([$quiz_id]);
$quiz = $quiz_stmt->fetch();

if (!$quiz) {
    set_flash_message('danger', 'Quiz not found.');
    header("Location: courses.php");
    exit();
}

$course_id = $quiz['course_id'];

// Verify Course Enrollment
$enroll_stmt = $pdo->prepare("SELECT id FROM course_enrollments WHERE course_id = ? AND student_id = ?");
$enroll_stmt->execute([$course_id, $student_id]);
$enrollment = $enroll_stmt->fetch();

if (!$enrollment) {
    set_flash_message('danger', 'You must be enrolled in this course to attempt quizzes.');
    header("Location: courses.php");
    exit();
}

// Count student quiz attempts
$attempts_stmt = $pdo->prepare("SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = ? AND student_id = ?");
$attempts_stmt->execute([$quiz_id, $student_id]);
$attempts_count = $attempts_stmt->fetchColumn();

// Fetch previous quiz attempts for summary
$prev_attempts_stmt = $pdo->prepare("SELECT * FROM quiz_attempts WHERE quiz_id = ? AND student_id = ? ORDER BY attempted_at DESC");
$prev_attempts_stmt->execute([$quiz_id, $student_id]);
$previous_attempts = $prev_attempts_stmt->fetchAll();

// Check if maximum attempt limit reached
$limit_reached = ($attempts_count >= $quiz['max_attempts']);

// Count how many questions exist for this quiz
$q_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM questions WHERE quiz_id = ?");
$q_count_stmt->execute([$quiz_id]);
$quiz_questions_count = $q_count_stmt->fetchColumn() ?: 0;

$quiz_state = 'intro';
if (isset($_GET['start']) && $_GET['start'] === '1' && !$limit_reached && $quiz_questions_count > 0) {
    $quiz_state = 'active';
    
    // Initialize secure quiz session
    if (!isset($_SESSION['quiz'][$quiz_id])) {
        $_SESSION['quiz'][$quiz_id] = [
            'start_time' => time(),
            'answers' => [],
            'security_log_id' => null
        ];
        
        // Initialize security log entry
        $log_stmt = $pdo->prepare("INSERT INTO quiz_security_logs (user_id, quiz_id, warning_count, focus_loss_count, fullscreen_exit_count, exit_attempts_count) VALUES (?, ?, 0, 0, 0, 0)");
        $log_stmt->execute([$user_id, $quiz_id]);
        $_SESSION['quiz'][$quiz_id]['security_log_id'] = $pdo->lastInsertId();
    }
}

// Check server-side warning count violation on load to force auto submit
$force_auto_submit = false;
$log_id = null;
if ($quiz_state === 'active') {
    $log_id = $_SESSION['quiz'][$quiz_id]['security_log_id'] ?? null;
    if (!$log_id) {
        $sec_stmt = $pdo->prepare("SELECT id, warning_count FROM quiz_security_logs WHERE user_id = ? AND quiz_id = ? ORDER BY id DESC LIMIT 1");
        $sec_stmt->execute([$user_id, $quiz_id]);
        $sec_log = $sec_stmt->fetch();
        if ($sec_log) {
            $log_id = $sec_log['id'];
            $_SESSION['quiz'][$quiz_id]['security_log_id'] = $log_id;
            if ($sec_log['warning_count'] >= 3) {
                $force_auto_submit = true;
            }
        }
    } else {
        $sec_stmt = $pdo->prepare("SELECT warning_count FROM quiz_security_logs WHERE id = ?");
        $sec_stmt->execute([$log_id]);
        $warning_count = $sec_stmt->fetchColumn();
        if ($warning_count !== false && $warning_count >= 3) {
            $force_auto_submit = true;
        }
    }
}

$attempt_result = null;
$review_questions = [];
$attempt_id = null;

$review_attempt_id = isset($_GET['review_attempt_id']) ? intval($_GET['review_attempt_id']) : null;
if ($review_attempt_id) {
    // Load attempt details
    $att_stmt = $pdo->prepare("SELECT * FROM quiz_attempts WHERE id = ? AND student_id = ?");
    $att_stmt->execute([$review_attempt_id, $student_id]);
    $att_res = $att_stmt->fetch();
    
    if ($att_res) {
        $quiz_state = 'result';
        
        $sec_stmt = $pdo->prepare("SELECT * FROM quiz_security_logs WHERE user_id = ? AND quiz_id = ? ORDER BY created_at DESC LIMIT 1");
        $sec_stmt->execute([$user_id, $quiz_id]);
        $sec_log = $sec_stmt->fetch();
        
        $attempt_result = [
            'score' => $att_res['score'],
            'passed' => $att_res['passed'],
            'correct_answers' => $att_res['correct_answers'],
            'total_questions' => $att_res['total_questions'],
            'points_gained' => 0, 
            'xp_gained' => 0,
            'time_spent_secs' => $att_res['time_spent_secs'],
            'warning_count' => $sec_log['warning_count'] ?? 0,
            'focus_loss_count' => $sec_log['focus_loss_count'] ?? 0,
            'fullscreen_exit_count' => $sec_log['fullscreen_exit_count'] ?? 0,
            'exit_attempts_count' => $sec_log['exit_attempts_count'] ?? 0,
            'auto_submitted' => $sec_log['auto_submitted'] ?? 0
        ];
        
        $attempt_id = $review_attempt_id;
    }
}

// Handle Quiz Submission
if (($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_quiz') || $force_auto_submit) {
    if (!$force_auto_submit) {
        $csrf_token = $_POST['csrf_token'] ?? '';
        
        if (!verify_csrf_token($csrf_token)) {
            set_flash_message('danger', 'CSRF validation failed.');
            header("Location: quiz-view.php?quiz_id=" . $quiz_id);
            exit();
        }
    }
    
    if ($limit_reached) {
        set_flash_message('danger', 'Attempt limit reached for this quiz.');
        header("Location: quiz-view.php?quiz_id=" . $quiz_id);
        exit();
    }
    
    // Fetch all questions for this quiz
    $q_stmt = $pdo->prepare("SELECT id, question_type, points FROM questions WHERE quiz_id = ?");
    $q_stmt->execute([$quiz_id]);
    $questions_list = $q_stmt->fetchAll();
    
    $total_questions = count($questions_list);
    
    if ($total_questions === 0) {
        set_flash_message('danger', 'This quiz has no questions.');
        header("Location: quiz-view.php?quiz_id=" . $quiz_id);
        exit();
    }
    
    $correct_answers = 0;
    $time_spent_secs = 0;
    if ($force_auto_submit) {
        $start_time = $_SESSION['quiz'][$quiz_id]['start_time'] ?? time();
        $time_spent_secs = time() - $start_time;
    } else {
        $time_spent_secs = intval($_POST['time_spent_secs'] ?? 0);
    }
    $processed_answers = []; // q_id => [selected_option_id, short_answer_text, is_correct]
    
    try {
        $pdo->beginTransaction();
        
        foreach ($questions_list as $q) {
            $is_correct = 0;
            $opt_id = null;
            $short_text = null;
            
            if ($q['question_type'] === 'mcq' || $q['question_type'] === 'tf') {
                if ($force_auto_submit) {
                    $opt_id = isset($_SESSION['quiz'][$quiz_id]['answers'][$q['id']]) ? intval($_SESSION['quiz'][$quiz_id]['answers'][$q['id']]) : null;
                } else {
                    $opt_id = isset($_POST['question_' . $q['id']]) ? intval($_POST['question_' . $q['id']]) : null;
                }
                if ($opt_id) {
                    $check_opt = $pdo->prepare("SELECT is_correct FROM question_options WHERE id = ? AND question_id = ?");
                    $check_opt->execute([$opt_id, $q['id']]);
                    $opt = $check_opt->fetch();
                    if ($opt && $opt['is_correct'] == 1) {
                        $is_correct = 1;
                        $correct_answers++;
                    }
                }
            } elseif ($q['question_type'] === 'short_answer') {
                if ($force_auto_submit) {
                    $short_text = isset($_SESSION['quiz'][$quiz_id]['answers'][$q['id']]) ? trim($_SESSION['quiz'][$quiz_id]['answers'][$q['id']]) : '';
                } else {
                    $short_text = isset($_POST['question_text_' . $q['id']]) ? trim($_POST['question_text_' . $q['id']]) : '';
                }
                if ($short_text !== '') {
                    // Fetch correct answer option from DB
                    $check_opt = $pdo->prepare("SELECT option_text FROM question_options WHERE question_id = ? ORDER BY id ASC LIMIT 1");
                    $check_opt->execute([$q['id']]);
                    $correct_text = $check_opt->fetchColumn();
                    
                    // Case-insensitive, trimmed text comparison
                    if ($correct_text !== false && strcasecmp(trim($correct_text), $short_text) === 0) {
                        $is_correct = 1;
                        $correct_answers++;
                    }
                }
            }
            
            $processed_answers[$q['id']] = [
                'selected_option_id' => $opt_id,
                'short_answer_text' => $short_text,
                'is_correct' => $is_correct
            ];
        }
        
        $score_pct = ($correct_answers / $total_questions) * 100;
        $passed = ($score_pct >= $quiz['passing_score']) ? 1 : 0;
        
        // Insert quiz attempt record
        $ins_attempt = $pdo->prepare("
            INSERT INTO quiz_attempts (student_id, quiz_id, score, total_questions, correct_answers, time_spent_secs, passed) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $ins_attempt->execute([$student_id, $quiz_id, $score_pct, $total_questions, $correct_answers, $time_spent_secs, $passed]);
        $attempt_id = $pdo->lastInsertId();
        
        // Insert quiz answers record
        $ins_ans = $pdo->prepare("
            INSERT INTO quiz_answers (quiz_attempt_id, question_id, selected_option_id, is_correct, short_answer_text) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        foreach ($processed_answers as $q_id => $ans_details) {
            $ins_ans->execute([
                $attempt_id, 
                $q_id, 
                $ans_details['selected_option_id'], 
                $ans_details['is_correct'],
                $ans_details['short_answer_text']
            ]);
        }
        
        $points_gained = 0;
        $xp_gained = 0;
        
        // 1. Award points/XP on passing score
        if ($passed) {
            $points_gained = $quiz['points_reward'];
            $xp_gained = $quiz['xp_reward'];
            
            $new_pts = $student['points'] + $points_gained;
            $new_xp = $student['xp'] + $xp_gained;
            
            $up_student = $pdo->prepare("UPDATE students SET points = ?, xp = ? WHERE id = ?");
            $up_student->execute([$new_pts, $new_xp, $student_id]);
            
            // Notification
            $notif_stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
            $notif_stmt->execute([
                $user_id,
                'Quiz Passed!',
                "Congratulations! You passed '{$quiz['title']}' with {$score_pct}%! Earned +{$points_gained} Points and +{$xp_gained} XP.",
                'quiz'
            ]);
            
            // 2. Check and Award Badges
            // Check Quick Learner Badge (id=1 / criteria_type = 'quizzes', criteria_value = 1)
            $passed_quizzes_stmt = $pdo->prepare("SELECT COUNT(*) FROM quiz_attempts WHERE student_id = ? AND passed = 1");
            $passed_quizzes_stmt->execute([$student_id]);
            $passed_quizzes_count = $passed_quizzes_stmt->fetchColumn();
            
            if ($passed_quizzes_count >= 1) {
                $check_badge = $pdo->prepare("SELECT id FROM student_badges WHERE student_id = ? AND badge_id = 1");
                $check_badge->execute([$student_id]);
                if (!$check_badge->fetch()) {
                    $award_badge = $pdo->prepare("INSERT INTO student_badges (student_id, badge_id) VALUES (?, 1)");
                    $award_badge->execute([$student_id]);
                    
                    $notif_bg = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
                    $notif_bg->execute([
                        $user_id,
                        'New Badge Earned!',
                        "You unlocked the Quick Learner Badge for passing your first quiz!",
                        'badge'
                    ]);
                }
            }
            
            // Check Point Explorer Badge (id=3 / criteria_value=100)
            if ($new_pts >= 100) {
                $check_badge3 = $pdo->prepare("SELECT id FROM student_badges WHERE student_id = ? AND badge_id = 3");
                $check_badge3->execute([$student_id]);
                if (!$check_badge3->fetch()) {
                    $award_badge3 = $pdo->prepare("INSERT INTO student_badges (student_id, badge_id) VALUES (?, 3)");
                    $award_badge3->execute([$student_id]);
                    
                    $notif_bg3 = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
                    $notif_bg3->execute([
                        $user_id,
                        'New Badge Earned!',
                        "You unlocked the Point Explorer Badge for reaching 100 points!",
                        'badge'
                    ]);
                }
            }
            
            // 3. Daily Challenge tracking
            $correct_answers_count = 0;
            foreach ($processed_answers as $ans_details) {
                if (intval($ans_details['is_correct']) === 1) {
                    $correct_answers_count++;
                }
            }
            
            track_student_challenge_progress($pdo, $student_id, 'quiz_attempt', 1);
            if ($passed) {
                track_student_challenge_progress($pdo, $student_id, 'quiz_completion', 1);
            }
            if ($score_pct >= 80) {
                track_student_challenge_progress($pdo, $student_id, 'quiz_score_80', 1);
            }
            track_student_challenge_progress($pdo, $student_id, 'correct_answers_5', $correct_answers_count);
            track_student_challenge_progress($pdo, $student_id, 'course_activity', 1);
            track_student_challenge_progress($pdo, $student_id, 'streak_maintain', 1);
            if ($xp_gained > 0) {
                track_student_challenge_progress($pdo, $student_id, 'xp_earn', $xp_gained);
            }
        }
        
        // Update auto-submitted status in security log if auto-submitted
        $is_auto_submitted = $force_auto_submit ? 1 : intval($_POST['auto_submitted'] ?? 0);
        $log_id = $_SESSION['quiz'][$quiz_id]['security_log_id'] ?? null;
        if ($log_id) {
            if ($is_auto_submitted) {
                $up_log = $pdo->prepare("UPDATE quiz_security_logs SET auto_submitted = 1 WHERE id = ?");
                $up_log->execute([$log_id]);
            }
            
            // Fetch the security log to show in the result display
            $sec_stmt = $pdo->prepare("SELECT * FROM quiz_security_logs WHERE id = ?");
            $sec_stmt->execute([$log_id]);
            $sec_log = $sec_stmt->fetch();
        } else {
            // Fallback: fetch the latest log for the user/quiz
            $sec_stmt = $pdo->prepare("SELECT * FROM quiz_security_logs WHERE user_id = ? AND quiz_id = ? ORDER BY created_at DESC LIMIT 1");
            $sec_stmt->execute([$user_id, $quiz_id]);
            $sec_log = $sec_stmt->fetch();
        }
        
        $pdo->commit();
        
        log_activity($pdo, $user_id, 'Submit Quiz Attempt', "Attempted quiz: {$quiz['title']} (Score: $score_pct%)");
        
        // Fetch results for the summary view
        $quiz_state = 'result';
        $attempt_result = [
            'score' => $score_pct,
            'passed' => $passed,
            'correct_answers' => $correct_answers,
            'total_questions' => $total_questions,
            'points_gained' => $points_gained,
            'xp_gained' => $xp_gained,
            'time_spent_secs' => $time_spent_secs,
            'warning_count' => $sec_log['warning_count'] ?? 0,
            'focus_loss_count' => $sec_log['focus_loss_count'] ?? 0,
            'fullscreen_exit_count' => $sec_log['fullscreen_exit_count'] ?? 0,
            'exit_attempts_count' => $sec_log['exit_attempts_count'] ?? 0,
            'auto_submitted' => $sec_log['auto_submitted'] ?? 0
        ];
        
        // Clear active secure quiz session variables
        unset($_SESSION['quiz'][$quiz_id]);
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        set_flash_message('danger', 'Quiz submission failed. Error: ' . $e->getMessage());
        header("Location: quiz-view.php?quiz_id=" . $quiz_id);
        exit();
    }
}

if ($quiz_state === 'result' && $attempt_id) {
    $review_stmt = $pdo->prepare("
        SELECT q.*, qa.selected_option_id, qa.is_correct, qa.short_answer_text 
        FROM questions q 
        LEFT JOIN quiz_answers qa ON q.id = qa.question_id AND qa.quiz_attempt_id = ? 
        WHERE q.quiz_id = ? 
        ORDER BY q.id ASC
    ");
    $review_stmt->execute([$attempt_id, $quiz_id]);
    $review_questions = $review_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($review_questions as &$rq) {
        $opt_stmt = $pdo->prepare("SELECT id, option_text, is_correct FROM question_options WHERE question_id = ? ORDER BY id ASC");
        $opt_stmt->execute([$rq['id']]);
        $rq['options'] = $opt_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($rq);
}

// Fetch active questions & options if state is active
$questions = [];
if ($quiz_state === 'active') {
    $q_stmt = $pdo->prepare("SELECT * FROM questions WHERE quiz_id = ? ORDER BY id ASC");
    $q_stmt->execute([$quiz_id]);
    $questions = $q_stmt->fetchAll();
    
    // Debug Logging: Log question count and array content
    error_log("Quiz ID: $quiz_id - Fetched questions count: " . count($questions));
    error_log("Fetched questions data: " . print_r($questions, true));
    
    if (count($questions) === 0) {
        set_flash_message('danger', 'This quiz has no questions yet.');
        header("Location: quiz-view.php?quiz_id=" . $quiz_id);
        exit();
    }
    
    // Shuffle options to randomize answers slightly for visual excellence
    foreach ($questions as &$q) {
        $opt_stmt = $pdo->prepare("SELECT id, option_text FROM question_options WHERE question_id = ? ORDER BY id ASC");
        $opt_stmt->execute([$q['id']]);
        $q['options'] = $opt_stmt->fetchAll();
    }
    unset($q);
}
?>

<?php if ($no_layout ?? false): ?>
<div class="min-h-screen bg-background py-8 px-4 flex flex-col justify-between">
    <div class="container mx-auto max-w-5xl">
        <header class="w-full flex justify-between items-center mb-6 pb-4 border-b border-outline-variant/30">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-primary text-3xl font-black">school</span>
                <span class="font-headline-sm text-headline-sm font-black text-primary">Rural Learning Quest</span>
            </div>
            <div class="text-right">
                <span class="text-xs font-semibold text-on-surface-variant block uppercase tracking-wider">Active Quest</span>
                <strong class="font-headline-sm text-sm font-bold text-on-surface"><?php echo e($quiz['title']); ?></strong>
            </div>
        </header>
<?php endif; ?>

<div class="space-y-lg">
    <!-- Breadcrumb & Title -->
    <?php if (!($no_layout ?? false)): ?>
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-md">
        <div>
            <div class="flex items-center gap-xs font-label-md text-label-md text-on-surface-variant mb-xs">
                <a href="courses.php" class="text-primary hover:underline text-decoration-none">Courses</a>
                <span class="material-symbols-outlined text-[14px]">chevron_right</span>
                <a href="course-view.php?course_id=<?php echo $course_id; ?>" class="text-primary hover:underline text-decoration-none"><?php echo e($quiz['course_title']); ?></a>
                <span class="material-symbols-outlined text-[14px]">chevron_right</span>
                <span class="text-on-surface-variant/70"><?php echo e($quiz['title']); ?></span>
            </div>
            <div class="flex items-center gap-sm mb-xs">
                <span class="inline-flex items-center gap-xs bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 px-2 py-0.5 rounded-full text-xs font-bold font-label-sm uppercase">
                    <span class="material-symbols-outlined text-[14px]" style="font-variation-settings: 'FILL' 1;">extension</span>
                    Quiz Quest
                </span>
            </div>
            <h2 class="font-headline-lg text-headline-lg text-on-surface font-bold"><?php echo e($quiz['title']); ?></h2>
        </div>
        <div>
            <?php if ($quiz_state === 'intro' || $quiz_state === 'result'): ?>
                <a href="course-view.php?course_id=<?php echo $course_id; ?>" class="inline-flex items-center gap-xs border border-outline-variant/30 text-on-surface hover:bg-surface-variant/20 px-md py-2 rounded-lg font-label-md text-label-md text-decoration-none transition-colors">
                    <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                    Back to Course
                </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- 1. INTRO VIEW STATE -->
    <?php if ($quiz_state === 'intro'): ?>
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-gutter">
            <!-- Quiz Info Box -->
            <div class="lg:col-span-6 self-start">
                <div class="glass-card rounded-xl p-md space-y-md">
                    <h3 class="font-headline-sm text-headline-sm text-on-surface pb-xs border-b border-outline-variant/30">Quest Guidelines</h3>
                    <p class="font-body-md text-body-md text-on-surface-variant leading-relaxed"><?php echo e($quiz['description']); ?></p>
                    
                    <div class="grid grid-cols-2 gap-md pt-xs">
                        <div class="p-sm border border-outline-variant/20 rounded-xl bg-surface-container-low/50 dark:bg-surface-container-highest/10 text-center space-y-xs">
                            <span class="material-symbols-outlined text-primary text-2xl" style="font-variation-settings: 'FILL' 1;">timer</span>
                            <span class="block text-xs font-label-sm text-on-surface-variant">Time Limit</span>
                            <strong class="block text-body-md text-on-surface font-bold"><?php echo $quiz['time_limit_mins']; ?> mins</strong>
                        </div>
                        <div class="p-sm border border-outline-variant/20 rounded-xl bg-surface-container-low/50 dark:bg-surface-container-highest/10 text-center space-y-xs">
                            <span class="material-symbols-outlined text-green-500 text-2xl" style="font-variation-settings: 'FILL' 1;">check_circle</span>
                            <span class="block text-xs font-label-sm text-on-surface-variant">Passing Score</span>
                            <strong class="block text-body-md text-on-surface font-bold"><?php echo $quiz['passing_score']; ?>%</strong>
                        </div>
                        <div class="p-sm border border-outline-variant/20 rounded-xl bg-surface-container-low/50 dark:bg-surface-container-highest/10 text-center space-y-xs">
                            <span class="material-symbols-outlined text-amber-500 text-2xl" style="font-variation-settings: 'FILL' 1;">badge</span>
                            <span class="block text-xs font-label-sm text-on-surface-variant">Attempts Left</span>
                            <strong class="block text-body-md text-on-surface font-bold"><?php echo ($quiz['max_attempts'] - $attempts_count); ?> / <?php echo $quiz['max_attempts']; ?></strong>
                        </div>
                        <div class="p-sm border border-outline-variant/20 rounded-xl bg-surface-container-low/50 dark:bg-surface-container-highest/10 text-center space-y-xs">
                            <span class="material-symbols-outlined text-red-500 text-2xl" style="font-variation-settings: 'FILL' 1;">redeem</span>
                            <span class="block text-xs font-label-sm text-on-surface-variant">Quest Rewards</span>
                            <strong class="block text-body-md text-on-surface font-bold">+<?php echo $quiz['points_reward']; ?> Pts | +<?php echo $quiz['xp_reward']; ?> XP</strong>
                        </div>
                    </div>
                    
                    <div class="pt-sm border-t border-outline-variant/30">
                        <?php if ($limit_reached): ?>
                            <div class="bg-error-container/20 border border-error-container/30 text-error py-3 rounded-lg flex items-center justify-center gap-xs font-bold text-center">
                                <span class="material-symbols-outlined text-[20px]">warning</span>
                                Attempt limit reached. You cannot attempt this quiz again.
                            </div>
                        <?php elseif ($quiz_questions_count === 0): ?>
                            <div class="bg-amber-500/10 border border-amber-500/20 text-amber-600 dark:text-amber-400 py-3 rounded-lg flex items-center justify-center gap-xs font-bold text-center">
                                <span class="material-symbols-outlined text-[20px]">warning</span>
                                This quiz has no questions yet. Please check back later.
                            </div>
                        <?php else: ?>
                            <a href="quiz-view.php?quiz_id=<?php echo $quiz_id; ?>&start=1" target="_blank" onclick="window.open(this.href, 'secureQuizPortal', 'width=1200,height=900,menubar=no,status=no,toolbar=no,resizable=yes'); return false;" class="flex w-full items-center justify-center gap-xs bg-primary text-white hover:bg-primary-hover py-3 rounded-lg font-label-md text-label-md text-decoration-none transition-colors shadow-md">
                                <span class="material-symbols-outlined text-[20px]">play_circle</span>
                                Start Quiz Quest
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Previous Attempts List -->
            <div class="lg:col-span-6 self-start">
                <div class="glass-card rounded-xl p-md h-full space-y-md">
                    <h3 class="font-headline-sm text-headline-sm text-on-surface pb-xs border-b border-outline-variant/30">Quest History</h3>
                    <?php if (empty($previous_attempts)): ?>
                        <div class="text-center py-xl space-y-md text-on-surface-variant">
                            <span class="material-symbols-outlined text-secondary text-5xl">fact_check</span>
                            <p class="font-body-md text-body-md">You have not attempted this quiz yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="flex flex-col gap-sm divide-y divide-outline-variant/20">
                            <?php foreach ($previous_attempts as $att): ?>
                                <div class="flex justify-between items-center pt-sm first:pt-0">
                                    <div class="space-y-1">
                                        <div class="flex items-center gap-xs">
                                            <span class="px-sm py-0.5 rounded-full text-xs font-bold font-label-sm <?php echo $att['passed'] ? 'bg-green-500/10 text-green-500' : 'bg-red-500/10 text-red-500'; ?>">
                                                <?php echo $att['passed'] ? 'Passed' : 'Failed'; ?>
                                            </span>
                                            <strong class="font-headline-sm text-headline-sm text-on-surface"><?php echo $att['score']; ?>%</strong>
                                        </div>
                                        <div class="font-body-xs text-xs text-on-surface-variant">
                                            Date: <?php echo date('M d, Y h:i A', strtotime($att['attempted_at'])); ?>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-sm">
                                        <div class="text-xs text-on-surface-variant flex items-center gap-xs">
                                            <span class="material-symbols-outlined text-[16px]">schedule</span>
                                            Time: <?php echo gmdate("i:s", $att['time_spent_secs']); ?>
                                        </div>
                                        <a href="quiz-view.php?quiz_id=<?php echo $quiz_id; ?>&review_attempt_id=<?php echo $att['id']; ?>" class="inline-flex items-center gap-xs text-xs text-secondary hover:text-secondary-container hover:underline text-decoration-none font-bold">
                                            <span class="material-symbols-outlined text-[16px]">rate_review</span>
                                            Review
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    <!-- 2. ACTIVE QUIZ STATE -->
    <?php elseif ($quiz_state === 'active'): ?>
        <style>
        .secure-quiz-active {
            user-select: none !important;
            -webkit-user-select: none !important;
            -moz-user-select: none !important;
            -ms-user-select: none !important;
        }
        </style>

        <!-- Secure Start Overlay -->
        <div id="secure-overlay" class="fixed inset-0 bg-background/95 backdrop-blur-md z-50 flex items-center justify-center p-md">
            <div class="glass-card rounded-xl p-lg max-w-md w-full text-center space-y-md border border-primary/20 shadow-2xl">
                <span class="material-symbols-outlined text-primary text-5xl animate-pulse">security</span>
                <h3 class="font-headline-md text-headline-md text-on-surface font-bold">Secure Quiz Session</h3>
                <p class="font-body-md text-body-md text-on-surface-variant leading-relaxed">
                    This quiz is protected by Secure Mode. Exiting full screen, switching tabs, or losing window focus will result in security warnings. Multiple violations will automatically submit your quiz.
                </p>
                <div class="p-sm bg-surface-container-high/40 rounded-lg text-left text-xs font-semibold space-y-xs text-on-surface-variant">
                    <div>🔒 Right-click, Copy, Cut, Paste and Text Selection are disabled.</div>
                    <div>⚠️ Exiting fullscreen counts as a focus loss violation.</div>
                    <div>🚫 3 violations will trigger immediate automatic submission.</div>
                </div>
                <button type="button" id="start-secure-btn" class="w-full flex items-center justify-center gap-xs bg-primary text-white hover:bg-primary-hover py-3 rounded-lg font-label-md text-label-md transition-colors shadow-md font-bold">
                    <span class="material-symbols-outlined text-[20px]">lock_open</span>
                    Enter Secure Fullscreen & Begin
                </button>
            </div>
        </div>

        <div class="flex flex-col items-center">
            <!-- Floating Timer -->
            <div class="quiz-timer flex items-center gap-sm bg-surface-container-high border border-primary/20 px-6 py-2.5 rounded-full shadow-lg text-primary mb-lg z-50 sticky top-20" id="timer-widget">
                <span class="material-symbols-outlined animate-spin text-[20px]" style="font-variation-settings: 'FILL' 1;">timer</span>
                <span class="font-label-md text-label-md font-bold text-on-surface">Time Remaining: <span id="timer-countdown">00:00</span></span>
            </div>
            
            <div class="w-full max-w-3xl">
                <div class="glass-card rounded-xl p-md md:p-lg">
                    <form id="quiz-form" action="quiz-view.php" method="POST" class="space-y-lg m-0">
                        <input type="hidden" name="csrf_token" value="<?php echo e(generate_csrf_token()); ?>">
                        <input type="hidden" name="quiz_id" value="<?php echo $quiz_id; ?>">
                        <input type="hidden" name="action" value="submit_quiz">
                        <input type="hidden" id="time_spent_secs" name="time_spent_secs" value="0">
                        <input type="hidden" name="auto_submitted" id="auto_submitted" value="0">
                        
                        <?php foreach ($questions as $idx => $q): ?>
                            <div class="space-y-sm pb-lg border-b border-outline-variant/30 last:border-b-0 last:pb-0">
                                <div class="flex justify-between items-center">
                                    <h4 class="font-headline-sm text-headline-sm text-on-surface-variant font-bold">
                                        Question <?php echo $idx + 1; ?> of <?php echo count($questions); ?> 
                                        <span class="text-xs text-secondary font-normal bg-secondary/10 px-2 py-0.5 rounded-full ml-xs"><?php echo strtoupper($q['question_type']); ?></span>
                                    </h4>
                                    <span class="bg-primary/10 text-primary px-sm py-0.5 rounded-full text-xs font-bold">+<?php echo $q['points']; ?> Pts</span>
                                </div>
                                <p class="font-body-lg text-body-lg text-on-surface font-semibold mb-sm"><?php echo e($q['question_text']); ?></p>
                                
                                <?php if ($q['question_type'] === 'mcq' || $q['question_type'] === 'tf'): ?>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-sm">
                                        <?php foreach ($q['options'] as $opt): ?>
                                            <?php 
                                            $checked = '';
                                            if (isset($_SESSION['quiz'][$quiz_id]['answers'][$q['id']]) && intval($_SESSION['quiz'][$quiz_id]['answers'][$q['id']]) === intval($opt['id'])) {
                                                $checked = 'checked';
                                            }
                                            ?>
                                            <div class="relative group">
                                                <input class="btn-check hidden" type="radio" name="question_<?php echo $q['id']; ?>" id="option_<?php echo $opt['id']; ?>" value="<?php echo $opt['id']; ?>" <?php echo $checked; ?> required>
                                                <label class="btn-option w-full flex items-center p-md rounded-xl border border-outline-variant/30 hover:border-primary hover:bg-primary/5 text-left cursor-pointer transition-all duration-200" for="option_<?php echo $opt['id']; ?>">
                                                    <span class="w-8 h-8 shrink-0 flex items-center justify-center border-2 border-outline-variant rounded-full font-label-md text-label-md text-on-surface-variant mr-md transition-colors check-circle"></span>
                                                    <span class="font-body-md text-body-md text-on-surface select-none"><?php echo e($opt['option_text']); ?></span>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php elseif ($q['question_type'] === 'short_answer'): ?>
                                    <?php 
                                    $saved_text = $_SESSION['quiz'][$quiz_id]['answers'][$q['id']] ?? '';
                                    ?>
                                    <div class="w-full">
                                        <input type="text" name="question_text_<?php echo $q['id']; ?>" class="w-full h-12 px-sm bg-surface-container-low border border-outline-variant/30 rounded-lg text-body-md" placeholder="Type your answer here..." value="<?php echo e($saved_text); ?>" required style="color: black !important;">
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="pt-md">
                            <button type="submit" class="w-full flex items-center justify-center gap-xs bg-primary text-white hover:bg-primary-hover py-3.5 rounded-lg font-label-md text-label-md transition-colors shadow-md active:scale-[0.98]">
                                <span class="material-symbols-outlined text-[20px]">send</span>
                                Submit Quiz Answers
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <script>
        document.addEventListener("DOMContentLoaded", () => {
            // Server-computed times
            <?php 
            $time_limit_secs = $quiz['time_limit_mins'] * 60;
            $time_elapsed = isset($_SESSION['quiz'][$quiz_id]['start_time']) ? (time() - $_SESSION['quiz'][$quiz_id]['start_time']) : 0;
            $remaining_secs = max(0, $time_limit_secs - $time_elapsed);
            ?>
            var totalSecs = <?php echo $remaining_secs; ?>;
            var timeSpent = <?php echo $time_elapsed; ?>;
            
            var timerDisplay = document.getElementById("timer-countdown");
            var timeSpentInput = document.getElementById("time_spent_secs");
            var form = document.getElementById("quiz-form");
            
            var startSecureBtn = document.getElementById("start-secure-btn");
            var secureOverlay = document.getElementById("secure-overlay");
            
            // Security Violation Counters initialized from DB
            var warningCount = <?php echo intval($sec_log['warning_count'] ?? 0); ?>;
            var focusLossCount = <?php echo intval($sec_log['focus_loss_count'] ?? 0); ?>;
            var fullscreenExitCount = <?php echo intval($sec_log['fullscreen_exit_count'] ?? 0); ?>;
            var exitAttemptsCount = <?php echo intval($sec_log['exit_attempts_count'] ?? 0); ?>;
            var lastViolationTime = 0;
            var isQuizStarted = false;
            
            // Disable right click, copy, cut, paste, and text selection
            document.addEventListener('contextmenu', e => e.preventDefault());
            document.addEventListener('copy', e => e.preventDefault());
            document.addEventListener('cut', e => e.preventDefault());
            document.addEventListener('paste', e => e.preventDefault());
            
            function enterFullscreen() {
                var elem = document.documentElement;
                if (elem.requestFullscreen) {
                    elem.requestFullscreen();
                } else if (elem.webkitRequestFullscreen) {
                    elem.webkitRequestFullscreen();
                } else if (elem.msRequestFullscreen) {
                    elem.msRequestFullscreen();
                }
            }
            
            function triggerViolation(type) {
                if (!isQuizStarted) return;
                var now = Date.now();
                if (now - lastViolationTime < 2000) {
                    return; // Throttle double triggers
                }
                lastViolationTime = now;
                
                if (type === 'focus_loss') {
                    focusLossCount++;
                } else if (type === 'fullscreen_exit') {
                    fullscreenExitCount++;
                } else if (type === 'back_button' || type === 'unload') {
                    exitAttemptsCount++;
                }
                
                warningCount++;
                
                // Update security violations on server
                var reportData = new URLSearchParams();
                reportData.append('quiz_id', '<?php echo $quiz_id; ?>');
                reportData.append('action', 'update_violations');
                reportData.append('warning_count', warningCount);
                reportData.append('focus_loss_count', focusLossCount);
                reportData.append('fullscreen_exit_count', fullscreenExitCount);
                reportData.append('exit_attempts_count', exitAttemptsCount);
                
                fetch('quiz-security-ajax.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: reportData.toString()
                });
                
                if (warningCount >= 3) {
                    isQuizStarted = false; // Disable further checks during submit
                    clearInterval(countdown);
                    saveQuizState();
                    alert("Security Violation: You have left the secure quiz environment 3 times. The quiz is being submitted automatically.");
                    document.getElementById("auto_submitted").value = "1";
                    setTimeout(() => {
                        form.submit();
                    }, 500);
                } else {
                    var msg = "Please remain in the quiz.";
                    if (warningCount === 2) {
                        msg = "Final warning.";
                    }
                    alert(msg + " (Warning #" + warningCount + " / 2. Further violations will result in automatic submission.)");
                    if (type === 'fullscreen_exit') {
                        setTimeout(() => {
                            alert("Please click anywhere on the page to return to secure full screen mode.");
                        }, 500);
                    }
                }
            }
            
            // Auto Save Answers Function
            function saveQuizState() {
                if (!isQuizStarted) return;
                var answersData = new URLSearchParams();
                answersData.append('quiz_id', '<?php echo $quiz_id; ?>');
                
                var inputs = form.querySelectorAll('input[type="radio"]:checked, input[type="text"]');
                inputs.forEach(input => {
                    if (input.type === 'radio') {
                        answersData.append('answers[' + input.name.replace('question_', '') + ']', input.value);
                    } else if (input.type === 'text') {
                        answersData.append('answers[' + input.name.replace('question_text_', '') + ']', input.value);
                    }
                });

                fetch('quiz-autosave-ajax.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: answersData.toString()
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        console.log('Quiz auto-saved. Time left: ' + data.time_left);
                        if (Math.abs(totalSecs - data.time_left) > 5) {
                            totalSecs = data.time_left;
                        }
                    }
                })
                .catch(err => console.error('Auto-save error:', err));
            }

            // Sync/Beacon save before unload/reload
            function saveQuizStateSync() {
                var answersData = new URLSearchParams();
                answersData.append('quiz_id', '<?php echo $quiz_id; ?>');
                var inputs = form.querySelectorAll('input[type="radio"]:checked, input[type="text"]');
                inputs.forEach(input => {
                    if (input.type === 'radio') {
                        answersData.append('answers[' + input.name.replace('question_', '') + ']', input.value);
                    } else if (input.type === 'text') {
                        answersData.append('answers[' + input.name.replace('question_text_', '') + ']', input.value);
                    }
                });
                
                navigator.sendBeacon('quiz-autosave-ajax.php', answersData);
                
                var exitData = new FormData();
                exitData.append('quiz_id', '<?php echo $quiz_id; ?>');
                exitData.append('action', 'exit_attempt');
                navigator.sendBeacon('quiz-security-ajax.php', exitData);
            }
            
            // Listeners for Focus and Fullscreen violations
            window.addEventListener('blur', () => { triggerViolation('focus_loss'); });
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) { triggerViolation('focus_loss'); }
            });
            document.addEventListener('fullscreenchange', () => {
                if (!document.fullscreenElement && isQuizStarted) {
                    triggerViolation('fullscreen_exit');
                }
            });
            
            // Listeners for auto-saving on answer change
            form.addEventListener('click', (e) => {
                if (e.target.matches('input[type="radio"]')) {
                    saveQuizState();
                }
            });
            
            let saveTimeout;
            form.addEventListener('input', (e) => {
                if (e.target.matches('input[type="text"]')) {
                    clearTimeout(saveTimeout);
                    saveTimeout = setTimeout(saveQuizState, 500);
                }
            });
            
            // Auto save every 15 seconds
            setInterval(saveQuizState, 15000);
            
            // Start Secure Session Trigger
            var countdown;
            function startTimer() {
                countdown = setInterval(() => {
                    totalSecs--;
                    timeSpent++;
                    
                    timeSpentInput.value = timeSpent;
                    
                    var mins = Math.floor(totalSecs / 60);
                    var secs = totalSecs % 60;
                    
                    mins = mins < 10 ? "0" + mins : mins;
                    secs = secs < 10 ? "0" + secs : secs;
                    
                    timerDisplay.innerText = mins + ":" + secs;
                    
                    if (totalSecs <= 0) {
                        clearInterval(countdown);
                        alert("Time is up! Submitting your quiz automatically.");
                        form.submit();
                    }
                }, 1000);
            }
            
            if (startSecureBtn && secureOverlay) {
                startSecureBtn.addEventListener("click", () => {
                    enterFullscreen();
                    
                    // Push state for back button block
                    window.history.pushState(null, "", window.location.href);
                    
                    document.body.classList.add("secure-quiz-active");
                    isQuizStarted = true;
                    
                    // Initialize secure log on DB
                    var startLogData = new URLSearchParams();
                    startLogData.append('quiz_id', '<?php echo $quiz_id; ?>');
                    startLogData.append('action', 'start');
                    
                    fetch('quiz-security-ajax.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: startLogData.toString()
                    });
                    
                    secureOverlay.style.display = 'none';
                    startTimer();
                });
            } else {
                // Fail-safe for reload or non-overlay starts
                document.body.classList.add("secure-quiz-active");
                isQuizStarted = true;
                startTimer();
            }
            
            // Intercept Back Button
            window.addEventListener('popstate', function(event) {
                window.history.pushState(null, "", window.location.href);
                triggerViolation('back_button');
            });
            
            // Unload / Close / Refresh protection
            window.addEventListener('beforeunload', (e) => {
                if (isQuizStarted) {
                    saveQuizStateSync();
                    e.preventDefault();
                    e.returnValue = 'Please remain in the quiz.';
                    return e.returnValue;
                }
            });
            
            form.addEventListener("submit", () => {
                isQuizStarted = false;
                clearInterval(countdown);
            });
        });
        </script>

    <!-- 3. RESULT VIEW STATE -->
    <?php elseif ($quiz_state === 'result' && $attempt_result): ?>
        <div class="flex flex-col gap-lg max-w-4xl mx-auto pb-xl">
            <!-- Summary Header Card -->
            <div class="glass-card rounded-2xl p-md md:p-lg text-center space-y-md shadow-xl border border-white/20 bg-white">
                <div class="flex flex-col items-center">
                    <?php if ($attempt_result['passed']): ?>
                        <div class="w-20 h-20 rounded-full bg-green-500/10 flex items-center justify-center text-green-500 mb-sm animate-bounce">
                            <span class="material-symbols-outlined text-[64px]" style="font-variation-settings: 'FILL' 1;">check_circle</span>
                        </div>
                        <h2 class="font-headline-lg text-headline-lg text-green-500 font-bold">Passed successfully!</h2>
                        <p class="font-body-md text-body-md text-on-surface-variant max-w-sm"><?php echo e(__('quiz.congrats_pass')); ?></p>
                    <?php else: ?>
                        <div class="w-20 h-20 rounded-full bg-red-500/10 flex items-center justify-center text-red-500 mb-sm">
                            <span class="material-symbols-outlined text-[64px]" style="font-variation-settings: 'FILL' 1;">error</span>
                        </div>
                        <h2 class="font-headline-lg text-headline-lg text-red-500 font-bold">Try Again</h2>
                        <p class="font-body-md text-body-md text-on-surface-variant max-w-sm"><?php echo e(__('quiz.try_again_fail')); ?></p>
                    <?php endif; ?>
                </div>
                
                <div class="py-sm">
                    <p class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider">Attempt Score</p>
                    <h3 class="font-headline-xl text-[48px] font-black <?php echo $attempt_result['passed'] ? 'text-green-500' : 'text-red-500'; ?>"><?php echo $attempt_result['score']; ?>%</h3>
                </div>
                
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-md border-t border-outline-variant/30 pt-md text-center">
                    <div class="space-y-xs">
                        <span class="text-xs font-label-sm text-on-surface-variant block">Total Questions</span>
                        <strong class="font-headline-sm text-headline-sm text-on-surface"><?php echo $attempt_result['total_questions']; ?></strong>
                    </div>
                    <div class="space-y-xs">
                        <span class="text-xs font-label-sm text-on-surface-variant block">Correct Answers</span>
                        <strong class="font-headline-sm text-headline-sm text-green-500"><?php echo $attempt_result['correct_answers']; ?></strong>
                    </div>
                    <div class="space-y-xs">
                        <span class="text-xs font-label-sm text-on-surface-variant block">Wrong Answers</span>
                        <strong class="font-headline-sm text-headline-sm text-red-500"><?php echo ($attempt_result['total_questions'] - $attempt_result['correct_answers']); ?></strong>
                    </div>
                    <div class="space-y-xs">
                        <span class="text-xs font-label-sm text-on-surface-variant block">Points Awarded</span>
                        <strong class="font-headline-sm text-headline-sm text-amber-500">+<?php echo $attempt_result['points_gained']; ?></strong>
                    </div>
                    <div class="space-y-xs">
                        <span class="text-xs font-label-sm text-on-surface-variant block">XP Gained</span>
                        <strong class="font-headline-sm text-headline-sm text-primary">+<?php echo $attempt_result['xp_gained']; ?></strong>
                    </div>
                    <div class="space-y-xs">
                        <span class="text-xs font-label-sm text-on-surface-variant block">Time Taken</span>
                        <strong class="font-headline-sm text-headline-sm text-on-surface"><?php echo gmdate("i:s", $attempt_result['time_spent_secs'] ?? 0); ?></strong>
                    </div>
                </div>
                
                <!-- Security Report Display -->
                <div class="border-t border-outline-variant/30 pt-md mt-md text-left space-y-xs">
                    <h4 class="font-headline-sm text-xs font-bold text-on-surface uppercase tracking-wider flex items-center gap-xs">
                        <span class="material-symbols-outlined text-primary text-[16px]">security</span>
                        Session Security Report
                    </h4>
                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-sm pt-xs text-center text-xs">
                        <div class="p-xs border border-outline-variant/20 rounded-lg bg-surface-container-low/50">
                            <span class="block text-on-surface-variant font-medium">Warnings</span>
                            <strong class="block text-sm font-bold text-on-surface"><?php echo $attempt_result['warning_count'] ?? 0; ?></strong>
                        </div>
                        <div class="p-xs border border-outline-variant/20 rounded-lg bg-surface-container-low/50">
                            <span class="block text-on-surface-variant font-medium">Focus Losses</span>
                            <strong class="block text-sm font-bold text-on-surface"><?php echo $attempt_result['focus_loss_count'] ?? 0; ?></strong>
                        </div>
                        <div class="p-xs border border-outline-variant/20 rounded-lg bg-surface-container-low/50">
                            <span class="block text-on-surface-variant font-medium">Fullscreen Exits</span>
                            <strong class="block text-sm font-bold text-on-surface"><?php echo $attempt_result['fullscreen_exit_count'] ?? 0; ?></strong>
                        </div>
                        <div class="p-xs border border-outline-variant/20 rounded-lg bg-surface-container-low/50">
                            <span class="block text-on-surface-variant font-medium">Exit Attempts</span>
                            <strong class="block text-sm font-bold text-on-surface"><?php echo $attempt_result['exit_attempts_count'] ?? 0; ?></strong>
                        </div>
                        <div class="p-xs border border-outline-variant/20 rounded-lg bg-surface-container-low/50">
                            <span class="block text-on-surface-variant font-medium">Auto-Submitted</span>
                            <strong class="block text-sm font-bold <?php echo ($attempt_result['auto_submitted'] ?? 0) ? 'text-red-500 font-extrabold' : 'text-green-500'; ?>">
                                <?php echo ($attempt_result['auto_submitted'] ?? 0) ? 'Yes' : 'No'; ?>
                            </strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Learning Insights Card -->
            <?php
            $lesson_info_stmt = $pdo->prepare("
                SELECT l.title AS lesson_title, s.title AS subject_title 
                FROM quizzes q 
                LEFT JOIN lessons l ON q.lesson_id = l.id 
                LEFT JOIN subjects s ON l.subject_id = s.id 
                WHERE q.id = ?
            ");
            $lesson_info_stmt->execute([$quiz_id]);
            $lesson_info = $lesson_info_stmt->fetch(PDO::FETCH_ASSOC);
            $topic_name = $lesson_info['lesson_title'] ?? $quiz['title'];
            $chapter_name = $lesson_info['subject_title'] ?? 'this chapter';
            
            $is_strong = ($attempt_result['score'] >= 80);
            $weak_topics = $is_strong ? 'None' : $topic_name;
            $strong_topics = $is_strong ? $topic_name : 'None';
            $recommendation = $is_strong 
                ? "Excellent job! You have fully mastered this topic. We recommend proceeding to the next chapter or taking advanced topic challenges."
                : "Revise " . e($chapter_name) . " – " . e($topic_name) . ". We suggest reading the lesson notes and focusing on the definitions of concepts before retrying.";
            ?>
            <div class="glass-card rounded-2xl p-md md:p-lg space-y-md shadow-lg border border-white/20 text-left bg-white">
                <h3 class="font-headline-sm text-headline-sm text-primary font-bold flex items-center gap-xs">
                    <span class="material-symbols-outlined">insights</span>
                    Learning Insights & Recommendations
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-md pt-xs">
                    <div class="p-sm rounded-xl bg-green-500/5 border border-green-500/10 space-y-1">
                        <span class="text-xs font-semibold text-green-600 block uppercase">Strong Topics</span>
                        <p class="font-body-md text-on-surface font-semibold"><?php echo e($strong_topics); ?></p>
                    </div>
                    <div class="p-sm rounded-xl bg-red-500/5 border border-red-500/10 space-y-1">
                        <span class="text-xs font-semibold text-red-600 block uppercase">Weak Topics</span>
                        <p class="font-body-md text-on-surface font-semibold"><?php echo e($weak_topics); ?></p>
                    </div>
                </div>
                <div class="p-sm rounded-xl bg-primary/5 border border-primary/10 mt-sm space-y-1">
                    <span class="text-xs font-semibold text-primary block uppercase">Personalized Actionable Recommendation</span>
                    <p class="font-body-md text-on-surface italic">"<?php echo e($recommendation); ?>"</p>
                </div>
            </div>

            <!-- Course Feedback Card (if not reviewed yet) -->
            <?php
            $fb_stmt = $pdo->prepare("SELECT id FROM course_feedback WHERE student_id = ? AND course_id = ?");
            $fb_stmt->execute([$student_id, $course_id]);
            $has_submitted_feedback = $fb_stmt->fetch();
            
            if (!$has_submitted_feedback):
            ?>
            <div id="feedback_card" class="glass-card rounded-2xl p-md md:p-lg space-y-md shadow-lg border border-white/20 text-left bg-white">
                <h3 class="font-headline-sm text-headline-sm text-secondary font-bold flex items-center gap-xs">
                    <span class="material-symbols-outlined text-secondary">rate_review</span>
                    Share Your Feedback on this Course
                </h3>
                <p class="font-body-sm text-on-surface-variant text-xs">Help us improve! Since you completed a quiz in this course, share your experience below.</p>
                
                <form id="courseFeedbackForm" class="space-y-sm">
                    <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                    
                    <!-- Star Rating -->
                    <div class="flex items-center gap-xs">
                        <span class="text-sm font-semibold text-on-surface-variant mr-xs">Rating:</span>
                        <div class="flex gap-1">
                            <?php for ($star = 1; $star <= 5; $star++): ?>
                                <button type="button" class="star-btn text-3xl focus:outline-none text-surface-container-highest" style="background:transparent; border:none;" data-value="<?php echo $star; ?>">★</button>
                            <?php endfor; ?>
                        </div>
                        <input type="hidden" name="rating" id="feedback_rating_value" value="5">
                    </div>
                    
                    <!-- Title -->
                    <div>
                        <label class="block text-xs font-semibold text-on-surface-variant mb-1" for="feedback_title">Review Title</label>
                        <input type="text" id="feedback_title" name="title" class="w-full p-2 border border-outline-variant/30 rounded-lg text-sm" style="color:black !important;" placeholder="e.g. Excellent course, highly interactive!" required>
                    </div>
                    
                    <!-- Message -->
                    <div>
                        <label class="block text-xs font-semibold text-on-surface-variant mb-1" for="feedback_message">Feedback Message</label>
                        <textarea id="feedback_message" name="message" rows="3" class="w-full p-2 border border-outline-variant/30 rounded-lg text-sm" style="color:black !important;" placeholder="Describe what you liked or how we can improve..." required></textarea>
                    </div>
                    
                    <button type="submit" class="w-full bg-secondary text-white hover:bg-secondary/90 py-2.5 rounded-lg font-label-md text-label-md transition-colors shadow border-0">
                        Submit Course Feedback
                    </button>
                </form>
                
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const stars = document.querySelectorAll('.star-btn');
                    const ratingInput = document.getElementById('feedback_rating_value');
                    
                    function updateStars(val) {
                        stars.forEach((star, idx) => {
                            if (idx < val) {
                                star.classList.add('text-amber-500');
                                star.classList.remove('text-surface-container-highest');
                            } else {
                                star.classList.remove('text-amber-500');
                                star.classList.add('text-surface-container-highest');
                            }
                        });
                    }
                    
                    updateStars(5); // default
                    
                    stars.forEach(star => {
                        star.addEventListener('click', function() {
                            const val = parseInt(this.getAttribute('data-value'));
                            ratingInput.value = val;
                            updateStars(val);
                        });
                    });
                    
                    const form = document.getElementById('courseFeedbackForm');
                    if (form) {
                        form.addEventListener('submit', function(e) {
                            e.preventDefault();
                            const formData = new FormData(this);
                            
                            fetch('save-feedback-ajax.php', {
                                method: 'POST',
                                body: formData
                            })
                            .then(res => res.json())
                            .then(data => {
                                if (data.success) {
                                    document.getElementById('feedback_card').innerHTML = `
                                        <div class="bg-green-500/10 border border-green-500/20 text-green-500 p-md rounded-xl text-center font-bold">
                                            Thank you! Your feedback has been submitted successfully.
                                        </div>
                                    `;
                                } else {
                                    alert('Error: ' + data.message);
                                }
                            })
                            .catch(err => {
                                alert('Request error: ' + err.message);
                            });
                        });
                    }
                });
                </script>
            </div>
            <?php endif; ?>

            <!-- Detailed Question Review Section -->
            <div class="space-y-md text-left">
                <h3 class="font-headline-sm text-headline-sm text-on-surface font-bold border-b border-outline-variant/30 pb-2">
                    Detailed Questions Review
                </h3>
                
                <?php foreach ($review_questions as $idx => $rq): ?>
                    <div class="glass-card rounded-xl p-md border <?php echo $rq['is_correct'] ? 'border-green-500/20 bg-green-500/[0.01]' : 'border-red-500/20 bg-red-500/[0.01]'; ?> space-y-sm mb-4 bg-white">
                        <!-- Header / Marks -->
                        <div class="flex justify-between items-center text-xs">
                            <span class="font-bold text-on-surface-variant block">Question <?php echo $idx + 1; ?></span>
                            <span class="px-sm py-0.5 rounded-full font-bold font-label-sm <?php echo $rq['is_correct'] ? 'bg-green-500/10 text-green-500' : 'bg-red-500/10 text-red-500'; ?>">
                                Marks: <?php echo $rq['is_correct'] ? $rq['points'] : 0; ?> / <?php echo $rq['points']; ?>
                            </span>
                        </div>
                        
                        <!-- Question Text -->
                        <p class="font-body-md text-on-surface font-semibold"><?php echo e($rq['question_text']); ?></p>
                        
                        <!-- Answers and Options -->
                        <?php if ($rq['question_type'] === 'mcq' || $rq['question_type'] === 'tf'): ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-sm pt-xs">
                                <?php foreach ($rq['options'] as $opt): 
                                    $is_selected = ($opt['id'] == $rq['selected_option_id']);
                                    $is_correct_opt = ($opt['is_correct'] == 1);
                                    
                                    $border_class = "border-outline-variant/30 bg-surface-container-low/30";
                                    $icon = "";
                                    
                                    if ($is_selected && $rq['is_correct']) {
                                        $border_class = "border-green-500/50 bg-green-500/5 text-green-600 font-semibold";
                                        $icon = "✅ (Your Answer)";
                                    } elseif ($is_selected && !$rq['is_correct']) {
                                        $border_class = "border-red-500/50 bg-red-500/5 text-red-600 font-semibold";
                                        $icon = "❌ (Your Answer)";
                                    } elseif ($is_correct_opt) {
                                        $border_class = "border-green-500/50 bg-green-500/5 text-green-600 font-semibold";
                                        $icon = "✅ (Correct Answer)";
                                    }
                                ?>
                                    <div class="p-sm rounded-lg border <?php echo $border_class; ?> text-xs flex justify-between items-center">
                                        <span><?php echo e($opt['option_text']); ?></span>
                                        <span class="text-xs font-bold"><?php echo $icon; ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php elseif ($rq['question_type'] === 'short_answer'): 
                            $correct_text = $rq['options'][0]['option_text'] ?? '';
                            $is_blank = (trim($rq['short_answer_text'] ?? '') === '');
                        ?>
                            <div class="space-y-sm pt-xs text-xs">
                                <div class="p-sm rounded-lg border <?php echo $rq['is_correct'] ? 'border-green-500/50 bg-green-500/5 text-green-600' : 'border-red-500/5 bg-red-500/5 text-red-600'; ?>">
                                    <strong>Your Answer:</strong> 
                                    <?php echo $is_blank ? '<span class="italic text-on-surface-variant/50">Left Blank</span> ❌' : e($rq['short_answer_text']) . ($rq['is_correct'] ? ' ✅' : ' ❌'); ?>
                                </div>
                                <?php if (!$rq['is_correct']): ?>
                                    <div class="p-sm rounded-lg border border-green-500/50 bg-green-500/5 text-green-600">
                                        <strong>Correct Answer:</strong> <?php echo e($correct_text); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Explanation -->
                        <?php if (!empty($rq['explanation'])): ?>
                            <div class="p-sm bg-surface-container-low/60 rounded-lg border border-outline-variant/20 mt-xs text-xs space-y-1">
                                <strong class="text-primary block uppercase tracking-wider text-[10px]">Explanation:</strong>
                                <p class="text-on-surface-variant leading-relaxed"><?php echo e($rq['explanation']); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Actions Row -->
            <div class="flex gap-md pt-lg border-t border-outline-variant/30">
                <a href="course-view.php?course_id=<?php echo $course_id; ?>" class="w-1/2 inline-flex items-center justify-center gap-xs bg-primary text-white hover:bg-primary-hover py-3 rounded-lg font-label-md text-label-md text-decoration-none transition-colors shadow-md border-0">
                    <span class="material-symbols-outlined text-[20px]">play_circle</span>
                    Continue Lessons
                </a>
                <a href="quiz-view.php?quiz_id=<?php echo $quiz_id; ?>" class="w-1/2 inline-flex items-center justify-center gap-xs bg-surface-container text-on-surface hover:bg-surface-container-high py-3 rounded-lg font-label-md text-label-md text-decoration-none transition-colors shadow border-0">
                    <span class="material-symbols-outlined text-[20px]">refresh</span>
                    Retake Quiz / Back to Intro
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php if ($no_layout ?? false): ?>
    </div>
</div>
<?php endif; ?>

<style>
/* MCQ Input Radio Style Customizations */
.btn-check:checked + .btn-option {
    border-color: #004ac6 !important;
    background-color: rgba(0, 74, 198, 0.04) !important;
    box-shadow: 0 4px 12px rgba(0, 74, 198, 0.05);
}
.btn-check:checked + .btn-option .check-circle {
    border-color: #004ac6 !important;
    background-color: #004ac6 !important;
}
.btn-check:checked + .btn-option .check-circle::after {
    content: '';
    display: block;
    width: 10px;
    height: 10px;
    background-color: #ffffff;
    border-radius: 50%;
}
</style>

<?php
include_once __DIR__ . '/../includes/footer.php';
?>
