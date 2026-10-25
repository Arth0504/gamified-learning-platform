<?php
$page_title = "Adaptive Recommendations";
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/middleware.php';

require_role('Student');

$user_id = $_SESSION['user_id'];

// Get Student ID
$stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
$stmt->execute([$user_id]);
$student_id = $stmt->fetchColumn();

// 1. REVISION RECOMMENDATIONS (Weak Topics: Quiz Attempts with Score < 60%)
$weak_attempts_stmt = $pdo->prepare("
    SELECT qa.score, qa.attempted_at, q.title AS quiz_title, l.id AS lesson_id, l.title AS lesson_title, s.title AS subject_title, s.id AS subject_id
    FROM quiz_attempts qa 
    JOIN quizzes q ON qa.quiz_id = q.id 
    JOIN lessons l ON q.lesson_id = l.id
    JOIN subjects s ON l.subject_id = s.id
    WHERE qa.student_id = ? AND qa.score < 60.00
    ORDER BY qa.attempted_at DESC
");
$weak_attempts_stmt->execute([$student_id]);
$weak_attempts = $weak_attempts_stmt->fetchAll();

// Compile unique subjects that need revision
$weak_subjects = [];
$recommended_lessons = [];

if (!empty($weak_attempts)) {
    $weak_subject_ids = [];
    foreach ($weak_attempts as $attempt) {
        if (!in_array($attempt['subject_id'], $weak_subject_ids)) {
            $weak_subject_ids[] = $attempt['subject_id'];
            $weak_subjects[] = [
                'id' => $attempt['subject_id'],
                'title' => $attempt['subject_title'],
                'reason' => "You scored " . $attempt['score'] . "% on the '" . $attempt['quiz_title'] . "' quiz."
            ];
        }
    }
    
    // Fetch up to 4 lessons under these weak subjects that the student can review
    $placeholders = implode(',', array_fill(0, count($weak_subject_ids), '?'));
    $lessons_stmt = $pdo->prepare("
        SELECT l.id, l.title, l.content_type, l.duration_mins, s.title AS subject_title, s.course_id
        FROM lessons l
        JOIN subjects s ON l.subject_id = s.id
        WHERE l.subject_id IN ($placeholders)
        LIMIT 4
    ");
    $lessons_stmt->execute($weak_subject_ids);
    $recommended_lessons = $lessons_stmt->fetchAll();
}

// 2. ADVANCED RECOMMENDATIONS (High Performance: Passed Quizzes with Score >= 90%)
$strong_attempts_stmt = $pdo->prepare("
    SELECT DISTINCT q.course_id, c.category, c.difficulty_level 
    FROM quiz_attempts qa 
    JOIN quizzes q ON qa.quiz_id = q.id
    JOIN courses c ON q.course_id = c.id
    WHERE qa.student_id = ? AND qa.score >= 90.00 AND qa.passed = 1
");
$strong_attempts_stmt->execute([$student_id]);
$strong_categories = $strong_attempts_stmt->fetchAll();

$advanced_courses = [];

if (!empty($strong_categories)) {
    foreach ($strong_categories as $strong) {
        $cat = $strong['category'];
        $diff = $strong['difficulty_level'];
        
        // Define higher difficulty level check
        $next_diffs = [];
        if ($diff === 'easy') {
            $next_diffs = ['medium', 'hard'];
        } elseif ($diff === 'medium') {
            $next_diffs = ['hard'];
        }
        
        if (!empty($next_diffs)) {
            // Find courses in the same category with higher difficulty that the student is NOT enrolled in yet
            $diff_placeholders = implode(',', array_fill(0, count($next_diffs), '?'));
            $adv_stmt = $pdo->prepare("
                SELECT c.*, u.full_name AS teacher_name 
                FROM courses c 
                JOIN teachers t ON c.teacher_id = t.id
                JOIN users u ON t.user_id = u.id
                WHERE c.category = ? AND c.difficulty_level IN ($diff_placeholders) 
                  AND c.status = 'published'
                  AND c.id NOT IN (SELECT course_id FROM course_enrollments WHERE student_id = ?)
                LIMIT 3
            ");
            $bind_params = array_merge([$cat], $next_diffs, [$student_id]);
            $adv_stmt->execute($bind_params);
            $recs = $adv_stmt->fetchAll();
            $advanced_courses = array_merge($advanced_courses, $recs);
        }
        
        // Fallback: If no higher difficulty courses exist, suggest other courses in the same category that the student is NOT enrolled in yet
        if (count($advanced_courses) < 2) {
            $fallback_stmt = $pdo->prepare("
                SELECT c.*, u.full_name AS teacher_name 
                FROM courses c 
                JOIN teachers t ON c.teacher_id = t.id
                JOIN users u ON t.user_id = u.id
                WHERE c.category = ? AND c.status = 'published'
                  AND c.id NOT IN (SELECT course_id FROM course_enrollments WHERE student_id = ?)
                LIMIT 2
            ");
            $fallback_stmt->execute([$cat, $student_id]);
            $recs = $fallback_stmt->fetchAll();
            $advanced_courses = array_merge($advanced_courses, $recs);
        }
    }
}

// Remove duplicate courses if any compiled from loops
$unique_advanced_courses = [];
$seen_course_ids = [];
foreach ($advanced_courses as $ac) {
    if (!in_array($ac['id'], $seen_course_ids)) {
        $seen_course_ids[] = $ac['id'];
        $unique_advanced_courses[] = $ac;
    }
}
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="font-heading font-weight-bold text-success"><i class="bi bi-magic text-success me-2"></i>Adaptive Learning Center</h2>
        <p class="text-secondary">Our engine analyzes your quiz scores to recommend lessons to revise and advanced courses to unlock.</p>
    </div>
</div>

<div class="row g-4">
    <!-- 1. REVISION RECOMMENDATIONS -->
    <div class="col-lg-6">
        <div class="glass-card h-100">
            <h4 class="font-heading text-danger border-bottom pb-2 mb-4 border-color">
                <i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i><?php echo e(__('adaptive.revision')); ?>
            </h4>
            
            <?php if (empty($weak_subjects)): ?>
                <div class="text-center py-5 text-secondary">
                    <i class="bi bi-emoji-smile fs-1 mb-2 text-success"></i>
                    <h5 class="font-heading text-success">No revision needed!</h5>
                    <p class="small mb-0">Excellent work! You have passed all attempted quizzes with flying colors.</p>
                </div>
            <?php else: ?>
                <div class="mb-4">
                    <p class="small text-secondary mb-3">We recommend reviewing the following units due to recent low quiz scores:</p>
                    <?php foreach ($weak_subjects as $ws): ?>
                        <div class="alert alert-danger py-2 px-3 small mb-2 border-danger" role="alert">
                            <i class="bi bi-info-circle-fill me-1"></i>
                            <strong><?php echo e($ws['title']); ?>:</strong> <?php echo e($ws['reason']); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <h5 class="font-heading text-dark-theme-compatible mb-3"><i class="bi bi-book-half me-1"></i>Recommended Revision Lessons</h5>
                <div class="list-group list-group-flush">
                    <?php foreach ($recommended_lessons as $rl): ?>
                        <div class="list-group-item bg-transparent border-color px-0 py-2.5 d-flex align-items-center justify-content-between">
                            <div>
                                <strong class="text-dark-theme-compatible d-block"><?php echo e($rl['title']); ?></strong>
                                <span class="small text-secondary"><?php echo e($rl['subject_title']); ?></span>
                            </div>
                            <a href="lesson-view.php?lesson_id=<?php echo $rl['id']; ?>" class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-book-fill"></i> Study
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 2. ADVANCED RECOMMENDATIONS -->
    <div class="col-lg-6">
        <div class="glass-card h-100">
            <h4 class="font-heading text-success border-bottom pb-2 mb-4 border-color">
                <i class="bi bi-rocket-takeoff-fill me-2 text-success"></i><?php echo e(__('adaptive.advanced')); ?>
            </h4>
            
            <?php if (empty($unique_advanced_courses)): ?>
                <div class="text-center py-5 text-secondary">
                    <i class="bi bi-lightbulb-fill fs-2 mb-2 text-warning"></i>
                    <p class="small">Keep passing quizzes with scores above 90% to unlock advanced course recommendations!</p>
                </div>
            <?php else: ?>
                <p class="small text-secondary mb-3"><?php echo e(__('adaptive.advanced_reason')); ?></p>
                
                <div class="row g-3">
                    <?php foreach ($unique_advanced_courses as $ac): ?>
                        <div class="col-12">
                            <div class="p-3 border rounded border-color d-flex flex-sm-row flex-column align-items-sm-center justify-content-between gap-3 bg-surface-item">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="bg-success text-white rounded d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; min-width: 48px;">
                                        <i class="bi bi-award fs-3"></i>
                                    </div>
                                    <div>
                                        <h5 class="font-heading text-dark-theme-compatible mb-1"><?php echo e($ac['title']); ?></h5>
                                        <p class="text-secondary small mb-0">Category: <?php echo e($ac['category']); ?> | Difficulty: <?php echo ucfirst($ac['difficulty_level']); ?></p>
                                    </div>
                                </div>
                                <div class="text-sm-end text-start">
                                    <a href="courses.php?search=<?php echo urlencode($ac['title']); ?>" class="btn btn-sm btn-success font-weight-bold w-100">
                                        Learn More
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* CSS class background helper for list objects compatibility in themes */
.bg-surface-item {
    background-color: var(--bg-base);
}
</style>
<?php
include_once __DIR__ . '/../includes/footer.php';
?>
