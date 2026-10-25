<?php
$page_title = "Lesson View";
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/middleware.php';

require_role('Student');

$user_id = $_SESSION['user_id'];
$lesson_id = $_GET['lesson_id'] ?? null;

if (!$lesson_id) {
    set_flash_message('danger', 'Lesson ID is missing.');
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

// Fetch Lesson details and check parent course enrollment
$lesson_stmt = $pdo->prepare("
    SELECT l.*, s.title AS subject_title, s.course_id 
    FROM lessons l 
    JOIN subjects s ON l.subject_id = s.id 
    WHERE l.id = ?
");
$lesson_stmt->execute([$lesson_id]);
$lesson = $lesson_stmt->fetch();

if (!$lesson) {
    set_flash_message('danger', 'Lesson not found.');
    header("Location: courses.php");
    exit();
}

$course_id = $lesson['course_id'];

// Verify Enrollment
$enroll_stmt = $pdo->prepare("SELECT id FROM course_enrollments WHERE course_id = ? AND student_id = ?");
$enroll_stmt->execute([$course_id, $student_id]);
$enrollment = $enroll_stmt->fetch();

if (!$enrollment) {
    set_flash_message('danger', 'You must be enrolled in this course to view its lessons.');
    header("Location: courses.php");
    exit();
}

// Fetch Lesson Completion Progress
$prog_stmt = $pdo->prepare("SELECT status FROM progress WHERE lesson_id = ? AND student_id = ?");
$prog_stmt->execute([$lesson_id, $student_id]);
$progress = $prog_stmt->fetch();
$is_completed = ($progress && $progress['status'] === 'completed');

// Fetch Associated Quiz
$quiz_stmt = $pdo->prepare("SELECT id, title FROM quizzes WHERE lesson_id = ?");
$quiz_stmt->execute([$lesson_id]);
$quiz = $quiz_stmt->fetch();

// Load points/XP settings rules
$points_to_award = 10;
$xp_to_award = 20;

// Fetch settings
$set_stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$settings_map = $set_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
if (isset($settings_map['points_per_lesson'])) {
    $points_to_award = intval($settings_map['points_per_lesson']);
}
if (isset($settings_map['xp_per_lesson'])) {
    $xp_to_award = intval($settings_map['xp_per_lesson']);
}

// Handle Mark Completed Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'complete') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verify_csrf_token($csrf_token)) {
        set_flash_message('danger', 'CSRF validation failed.');
    } else {
        try {
            $pdo->beginTransaction();
            
            // 1. Record Lesson Progress
            $progress_stmt = $pdo->prepare("
                INSERT INTO progress (student_id, lesson_id, status, completed_at) 
                VALUES (?, ?, 'completed', CURRENT_TIMESTAMP)
                ON DUPLICATE KEY UPDATE status = 'completed', completed_at = CURRENT_TIMESTAMP
            ");
            $progress_stmt->execute([$student_id, $lesson_id]);
            
            // 2. Award Points & XP to Student profile
            $new_points = $student['points'] + $points_to_award;
            $new_xp = $student['xp'] + $xp_to_award;
            
            $award_stmt = $pdo->prepare("UPDATE students SET points = ?, xp = ? WHERE id = ?");
            $award_stmt->execute([$new_points, $new_xp, $student_id]);
            
            // Create notification for points
            $notif_pt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
            $notif_pt->execute([
                $user_id,
                'Lesson Completed!',
                "Great job! You earned +{$points_to_award} Points and +{$xp_to_award} XP.",
                'info'
            ]);
            
            // 3. Recalculate Course Completion percentage
            $total_l_stmt = $pdo->prepare("SELECT COUNT(*) FROM lessons l JOIN subjects s ON l.subject_id = s.id WHERE s.course_id = ?");
            $total_l_stmt->execute([$course_id]);
            $total_lessons_count = $total_l_stmt->fetchColumn();
            
            $comp_l_stmt = $pdo->prepare("
                SELECT COUNT(DISTINCT p.lesson_id) 
                FROM progress p 
                JOIN lessons l ON p.lesson_id = l.id 
                JOIN subjects s ON l.subject_id = s.id 
                WHERE s.course_id = ? AND p.student_id = ? AND p.status = 'completed'
            ");
            $comp_l_stmt->execute([$course_id, $student_id]);
            $comp_lessons_count = $comp_l_stmt->fetchColumn();
            
            $progress_percent = $total_lessons_count > 0 ? round(($comp_lessons_count / $total_lessons_count) * 100) : 0;
            
            $status_enroll = ($progress_percent >= 100) ? 'completed' : 'active';
            $completed_at = ($progress_percent >= 100) ? date('Y-m-d H:i:s') : null;
            
            $update_enroll = $pdo->prepare("UPDATE course_enrollments SET progress_percent = ?, status = ?, completed_at = ? WHERE student_id = ? AND course_id = ?");
            $update_enroll->execute([$progress_percent, $status_enroll, $completed_at, $student_id, $course_id]);
            
            // 4. Daily Challenge tracking
            track_student_challenge_progress($pdo, $student_id, 'lesson_read', 1);
            track_student_challenge_progress($pdo, $student_id, 'course_activity', 1);
            track_student_challenge_progress($pdo, $student_id, 'streak_maintain', 1);
            track_student_challenge_progress($pdo, $student_id, 'xp_earn', $xp_to_award);

            // 5. Evaluate and issue Badges
            // Check Point Explorer Badge (id=3 / criteria_value=100)
            if ($new_points >= 100) {
                $check_badge = $pdo->prepare("SELECT id FROM student_badges WHERE student_id = ? AND badge_id = 3");
                $check_badge->execute([$student_id]);
                if (!$check_badge->fetch()) {
                    $award_badge = $pdo->prepare("INSERT INTO student_badges (student_id, badge_id) VALUES (?, 3)");
                    $award_badge->execute([$student_id]);
                    
                    $notif_bg = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
                    $notif_bg->execute([
                        $user_id,
                        'New Badge Unlocked!',
                        'Congratulations! You unlocked the Point Explorer Badge for reaching 100 points!',
                        'badge'
                    ]);
                }
            }

            // Check Graduate Badge (id=4 / criteria_value=1)
            if ($progress_percent >= 100) {
                $check_grad = $pdo->prepare("SELECT id FROM student_badges WHERE student_id = ? AND badge_id = 4");
                $check_grad->execute([$student_id]);
                if (!$check_grad->fetch()) {
                    $award_grad = $pdo->prepare("INSERT INTO student_badges (student_id, badge_id) VALUES (?, 4)");
                    $award_grad->execute([$student_id]);
                    
                    $notif_grad = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
                    $notif_grad->execute([
                        $user_id,
                        'Course Graduate!',
                        'Incredible! You completed a full course and earned the Graduate Badge!',
                        'badge'
                    ]);
                }
            }
            
            $pdo->commit();
            
            log_activity($pdo, $user_id, 'Mark Lesson Complete', "Completed lesson ID: $lesson_id");
            set_flash_message('success', "Lesson marked complete! You earned +{$points_to_award} Points and +{$xp_to_award} XP!");
            header("Location: course-view.php?course_id=" . $course_id);
            exit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            set_flash_message('danger', 'Operation failed: ' . $e->getMessage());
        }
    }
}
?>

<div class="space-y-lg">
    <!-- Breadcrumbs & Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-md">
        <div>
            <div class="flex items-center gap-xs font-label-md text-label-md text-on-surface-variant mb-xs">
                <a href="courses.php" class="text-primary hover:underline text-decoration-none">Courses</a>
                <span class="material-symbols-outlined text-[14px]">chevron_right</span>
                <a href="course-view.php?course_id=<?php echo $course_id; ?>" class="text-primary hover:underline text-decoration-none">Syllabus</a>
                <span class="material-symbols-outlined text-[14px]">chevron_right</span>
                <span class="text-on-surface-variant/70"><?php echo e($lesson['title']); ?></span>
            </div>
            <div class="flex items-center gap-sm mb-xs">
                <span class="bg-primary/10 text-primary border border-primary/20 px-2 py-0.5 rounded-full text-xs font-bold font-label-sm uppercase"><?php echo e($lesson['subject_title']); ?></span>
            </div>
            <h2 class="font-headline-lg text-headline-lg text-on-surface font-bold"><?php echo e($lesson['title']); ?></h2>
        </div>
        <div>
            <a href="course-view.php?course_id=<?php echo $course_id; ?>" class="inline-flex items-center gap-xs border border-outline-variant/30 text-on-surface hover:bg-surface-variant/20 px-md py-2 rounded-lg font-label-md text-label-md text-decoration-none transition-colors">
                <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                Back to Syllabus
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-gutter">
        <!-- Main Content Reader (Left side) -->
        <div class="lg:col-span-8 self-start">
            <div class="glass-card rounded-xl p-md md:p-lg min-h-0 flex flex-col justify-between">
                <!-- Content Rendering Depending on Type -->
                <div class="space-y-md">
                    <!-- Lesson Image rendering if file is a picture -->
                    <?php if ($lesson['file_path'] && in_array(strtolower(pathinfo($lesson['file_path'], PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])): ?>
                        <div class="mb-md">
                            <img src="<?php echo BASE_URL . e($lesson['file_path']); ?>" alt="Lesson Image" class="w-full rounded-xl shadow-md border border-outline-variant/20 max-h-96 object-cover">
                        </div>
                    <?php endif; ?>

                    <?php if ($lesson['content_type'] === 'text'): ?>
                        <div class="font-body-lg text-body-lg text-on-surface leading-relaxed whitespace-pre-line">
                            <?php echo nl2br(e($lesson['content'])); ?>
                        </div>

                    <?php elseif ($lesson['content_type'] === 'video'): ?>
                        <?php if ($lesson['external_url']): ?>
                            <div class="aspect-video w-full rounded-xl overflow-hidden shadow-lg border border-outline-variant/30 bg-black">
                                <iframe src="<?php echo e($lesson['external_url']); ?>" title="Video player" allowfullscreen class="w-full h-full border-0"></iframe>
                            </div>
                        <?php elseif ($lesson['file_path']): ?>
                            <video src="<?php echo BASE_URL . e($lesson['file_path']); ?>" controls class="w-full rounded-xl shadow-lg border border-outline-variant/30" style="max-height: 450px;"></video>
                        <?php endif; ?>
                        <div class="font-body-lg text-body-lg text-on-surface leading-relaxed mt-md whitespace-pre-line">
                            <?php echo nl2br(e($lesson['content'])); ?>
                        </div>

                    <?php elseif ($lesson['content_type'] === 'pdf'): ?>
                        <?php if ($lesson['file_path']): ?>
                            <div class="p-md rounded-xl bg-surface-container-high/40 border border-outline-variant/30 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-md">
                                <div class="flex gap-sm">
                                    <span class="material-symbols-outlined text-red-500 text-4xl">picture_as_pdf</span>
                                    <div>
                                        <h4 class="font-headline-sm text-headline-sm text-on-surface font-semibold">PDF Lesson Resource</h4>
                                        <p class="font-body-sm text-body-sm text-on-surface-variant">Download or view the accompanying PDF textbook for self-study guides.</p>
                                    </div>
                                </div>
                                <?php if ($lesson['is_downloadable']): ?>
                                    <a href="<?php echo BASE_URL . e($lesson['file_path']); ?>" download class="inline-flex items-center gap-xs bg-primary text-white hover:bg-primary-hover px-md py-2 rounded-lg font-label-md text-label-md text-decoration-none transition-colors shadow-sm" target="_blank">
                                        <span class="material-symbols-outlined text-[18px]">download</span>
                                        Download PDF
                                    </a>
                                <?php endif; ?>
                            </div>
                            
                            <div class="hidden md:block border border-outline-variant/30 rounded-xl overflow-hidden shadow-inner bg-surface-container mt-md">
                                <embed src="<?php echo BASE_URL . e($lesson['file_path']); ?>" type="application/pdf" width="100%" height="600px">
                            </div>
                        <?php endif; ?>
                        <div class="font-body-lg text-body-lg text-on-surface leading-relaxed mt-md whitespace-pre-line">
                            <?php echo nl2br(e($lesson['content'])); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar Tools (Right side) -->
        <div class="lg:col-span-4 space-y-lg self-start">
            <!-- Actions -->
            <div class="glass-card rounded-xl p-md space-y-md">
                <h3 class="font-headline-sm text-headline-sm text-on-surface pb-xs border-b border-outline-variant/30">Lesson Actions</h3>
                
                <?php if ($is_completed): ?>
                    <div class="bg-green-500/10 border border-green-500/20 text-green-500 py-3 rounded-lg flex items-center justify-center gap-xs font-bold text-center">
                        <span class="material-symbols-outlined text-[20px]" style="font-variation-settings: 'FILL' 1;">check_circle</span>
                        Lesson Completed!
                    </div>
                <?php else: ?>
                    <form action="lesson-view.php?lesson_id=<?php echo $lesson_id; ?>" method="POST" class="m-0">
                        <input type="hidden" name="csrf_token" value="<?php echo e(generate_csrf_token()); ?>">
                        <input type="hidden" name="action" value="complete">
                        
                        <button type="submit" class="w-full flex items-center justify-center gap-2 bg-success text-white hover:bg-green-600 py-2.5 rounded-lg font-label-md text-label-md transition-colors shadow-md">
                            <span class="material-symbols-outlined text-[20px]">check_box</span>
                            <?php echo e(__('student.completed_btn')); ?>
                        </button>
                    </form>
                <?php endif; ?>

                <?php if ($quiz): ?>
                    <a href="quiz-view.php?quiz_id=<?php echo $quiz['id']; ?>" class="flex items-center justify-center gap-2 bg-secondary text-white hover:bg-secondary-hover py-2.5 rounded-lg font-label-md text-label-md text-decoration-none transition-colors shadow-md">
                        <span class="material-symbols-outlined text-[20px]">quiz</span>
                        <?php echo e(__('student.quiz_button')); ?>
                    </a>
                <?php endif; ?>
            </div>

            <!-- Details -->
            <div class="glass-card rounded-xl p-md space-y-md">
                <h3 class="font-headline-sm text-headline-sm text-on-surface pb-xs border-b border-outline-variant/30">Lesson Details</h3>
                <div class="flex flex-col gap-sm text-body-sm text-on-surface-variant">
                    <div class="flex justify-between items-center">
                        <span class="flex items-center gap-xs"><span class="material-symbols-outlined text-[16px]">schedule</span> Study Duration:</span>
                        <strong class="text-on-surface font-semibold"><?php echo $lesson['duration_mins']; ?> minutes</strong>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="flex items-center gap-xs"><span class="material-symbols-outlined text-[16px]">stars</span> Points Awarded:</span>
                        <strong class="text-on-surface font-semibold">+<?php echo $points_to_award; ?> Pts</strong>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="flex items-center gap-xs"><span class="material-symbols-outlined text-[16px]">trending_up</span> XP Gain:</span>
                        <strong class="text-on-surface font-semibold">+<?php echo $xp_to_award; ?> XP</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
include_once __DIR__ . '/../includes/footer.php';
?>
