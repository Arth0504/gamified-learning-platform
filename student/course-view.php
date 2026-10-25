<?php
$page_title = "Course View";
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/middleware.php';

require_role('Student');

$user_id = $_SESSION['user_id'];
$course_id = $_GET['course_id'] ?? null;
?>

<style>
/* Resolve Tailwind CSS and Bootstrap collapse conflict */
.accordion-collapse.collapse {
    visibility: visible !important;
}
</style>

<?php
if (!$course_id) {
    set_flash_message('danger', 'Course ID is missing.');
    header("Location: courses.php");
    exit();
}

// Get Student ID
$stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
$stmt->execute([$user_id]);
$student = $stmt->fetch();
$student_id = $student['id'] ?? 0;

// Verify Course Enrollment
$enroll_stmt = $pdo->prepare("
    SELECT e.*, c.title AS course_title, c.description AS course_desc, c.image_path, u.full_name AS teacher_name 
    FROM course_enrollments e 
    JOIN courses c ON e.course_id = c.id 
    JOIN teachers t ON c.teacher_id = t.id
    JOIN users u ON t.user_id = u.id 
    WHERE e.course_id = ? AND e.student_id = ?
");
$enroll_stmt->execute([$course_id, $student_id]);
$enrollment = $enroll_stmt->fetch();

if (!$enrollment) {
    set_flash_message('danger', 'You must be enrolled in this course to view it.');
    header("Location: courses.php");
    exit();
}

// Check if course feedback already exists
$fb_stmt = $pdo->prepare("SELECT id FROM course_feedback WHERE student_id = ? AND course_id = ?");
$fb_stmt->execute([$student_id, $course_id]);
$has_submitted_feedback = $fb_stmt->fetch() ? true : false;

// Get subjects and lessons in sequence
$subjects_stmt = $pdo->prepare("SELECT * FROM subjects WHERE course_id = ? ORDER BY sort_order ASC, id ASC");
$subjects_stmt->execute([$course_id]);
$subjects = $subjects_stmt->fetchAll();

// Array to store lessons mapped by subject_id
$lessons_by_subject = [];
if (!empty($subjects)) {
    // Fetch all lessons for these subjects
    $subject_ids = array_column($subjects, 'id');
    $placeholders = implode(',', array_fill(0, count($subject_ids), '?'));
    
    // Fetch lessons and include completion progress
    $lessons_stmt = $pdo->prepare("
        SELECT l.*, p.status AS completion_status 
        FROM lessons l 
        LEFT JOIN progress p ON l.id = p.lesson_id AND p.student_id = ? 
        WHERE l.subject_id IN ($placeholders) 
        ORDER BY l.sort_order ASC, l.id ASC
    ");
    
    // Bind student ID and subject IDs
    $bind_params = array_merge([$student_id], $subject_ids);
    $lessons_stmt->execute($bind_params);
    $lessons = $lessons_stmt->fetchAll();
    
    foreach ($lessons as $lesson) {
        $lessons_by_subject[$lesson['subject_id']][] = $lesson;
    }
}
?>

<div class="space-y-lg">
    <!-- Breadcrumb & Top Bar -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-md">
        <div>
            <div class="flex items-center gap-xs font-label-md text-label-md text-on-surface-variant mb-xs">
                <a href="courses.php" class="text-primary hover:underline text-decoration-none">Courses</a>
                <span class="material-symbols-outlined text-[14px]">chevron_right</span>
                <span class="text-on-surface-variant/70"><?php echo e($enrollment['course_title']); ?></span>
            </div>
            <h2 class="font-headline-lg text-headline-lg text-on-surface font-bold"><?php echo e($enrollment['course_title']); ?></h2>
            <p class="font-body-sm text-body-sm text-on-surface-variant flex items-center gap-xs"><span class="material-symbols-outlined text-[16px]">school</span> Instructor: <?php echo e($enrollment['teacher_name']); ?></p>
        </div>
        <div>
            <a href="courses.php" class="inline-flex items-center gap-xs border border-outline-variant/30 text-on-surface hover:bg-surface-variant/20 px-md py-2 rounded-lg font-label-md text-label-md text-decoration-none transition-colors">
                <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                Back to Catalog
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-gutter">
        <!-- Left Column: Course Progress & Info -->
        <div class="lg:col-span-4 space-y-lg self-start">
            <div class="glass-card rounded-xl p-md">
                <?php if ($enrollment['image_path']): ?>
                    <img src="<?php echo BASE_URL . e($enrollment['image_path']); ?>" alt="Banner" class="rounded-lg mb-md w-full object-cover max-h-48">
                <?php endif; ?>
                
                <h3 class="font-headline-sm text-headline-sm text-on-surface mb-sm">Course Progress</h3>
                
                <div class="space-y-1">
                    <div class="flex justify-between font-label-sm text-label-sm text-on-surface-variant">
                        <span>Completed Syllabus</span>
                        <span><?php echo $enrollment['progress_percent']; ?>%</span>
                    </div>
                    <div class="w-full h-2.5 bg-surface-container rounded-full overflow-hidden">
                        <div class="bg-primary h-full transition-all duration-500" style="width: <?php echo $enrollment['progress_percent']; ?>%;"></div>
                    </div>
                </div>
                
                <?php if ($enrollment['progress_percent'] === 100): ?>
                    <div class="mt-md bg-green-500/10 border border-green-500/20 text-green-500 py-3 rounded-lg flex items-center justify-center gap-xs font-bold text-center">
                        <span class="material-symbols-outlined text-[20px]" style="font-variation-settings: 'FILL' 1;">verified</span>
                        Course Completed!
                    </div>
                    
                    <?php if (!$has_submitted_feedback): ?>
                        <div id="course_feedback_card" class="mt-md p-sm border border-outline-variant/30 rounded-lg bg-surface-container-lowest/50 text-left space-y-sm bg-white">
                            <h4 class="font-headline-sm text-xs font-bold text-secondary flex items-center gap-xs">
                                <span class="material-symbols-outlined text-[16px]">rate_review</span>
                                Rate this Course
                            </h4>
                            <form id="courseFeedbackFormSidebar" class="space-y-xs">
                                <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                                <div class="flex items-center gap-xs">
                                    <span class="text-xs font-semibold text-on-surface-variant">Rating:</span>
                                    <div class="flex gap-0.5">
                                        <?php for ($star = 1; $star <= 5; $star++): ?>
                                            <button type="button" class="star-btn-sb text-xl focus:outline-none text-surface-container-highest" style="background:transparent; border:none;" data-value="<?php echo $star; ?>">★</button>
                                        <?php endfor; ?>
                                    </div>
                                    <input type="hidden" name="rating" id="feedback_rating_value_sb" value="5">
                                </div>
                                <div>
                                    <input type="text" name="title" class="w-full p-1.5 border border-outline-variant/30 rounded-lg text-xs" style="color:black !important;" placeholder="Review Title" required>
                                </div>
                                <div>
                                    <textarea name="message" rows="2" class="w-full p-1.5 border border-outline-variant/30 rounded-lg text-xs" style="color:black !important;" placeholder="Write message..." required></textarea>
                                </div>
                                <button type="submit" class="w-full bg-secondary text-white hover:bg-secondary/90 py-1.5 rounded-lg text-xs font-bold transition shadow border-0">
                                    Submit Review
                                </button>
                            </form>
                            
                            <script>
                            document.addEventListener('DOMContentLoaded', function() {
                                const stars = document.querySelectorAll('.star-btn-sb');
                                const ratingInput = document.getElementById('feedback_rating_value_sb');
                                
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
                                updateStars(5);
                                
                                stars.forEach(star => {
                                    star.addEventListener('click', function() {
                                        const val = parseInt(this.getAttribute('data-value'));
                                        ratingInput.value = val;
                                        updateStars(val);
                                    });
                                });
                                
                                const form = document.getElementById('courseFeedbackFormSidebar');
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
                                                document.getElementById('course_feedback_card').innerHTML = `
                                                    <div class="bg-green-500/10 border border-green-500/20 text-green-500 p-sm rounded text-center text-xs font-bold">
                                                        Feedback submitted!
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
                <?php endif; ?>
            </div>

            <div class="glass-card rounded-xl p-md">
                <h3 class="font-headline-sm text-headline-sm text-on-surface mb-sm">About this Course</h3>
                <p class="font-body-sm text-body-sm text-on-surface-variant leading-relaxed"><?php echo nl2br(e($enrollment['course_desc'])); ?></p>
            </div>
        </div>

        <!-- Right Column: Course Content Outline -->
        <div class="lg:col-span-8 self-start">
            <div class="glass-card rounded-xl p-md">
                <div class="flex items-center gap-sm mb-md pb-xs border-b border-outline-variant/30">
                    <span class="material-symbols-outlined text-primary text-2xl">format_list_bulleted</span>
                    <h3 class="font-headline-sm text-headline-sm text-on-surface">Lessons Syllabus</h3>
                </div>
                
                <?php if (empty($subjects)): ?>
                    <div class="text-center py-xl space-y-md">
                        <span class="material-symbols-outlined text-secondary text-5xl">auto_stories</span>
                        <p class="font-body-md text-body-md text-on-surface-variant">This course has no syllabus configured yet.</p>
                    </div>
                <?php else: ?>
                    <div class="accordion space-y-md" id="syllabusAccordion">
                        <?php foreach ($subjects as $index => $sub): ?>
                            <div class="accordion-item border border-outline-variant/30 rounded-xl overflow-hidden bg-surface-container-lowest/50">
                                <h2 class="accordion-header m-0" id="heading<?php echo $sub['id']; ?>">
                                    <button class="accordion-button flex justify-between items-center w-full px-md py-4 bg-surface-container-low/40 text-on-surface font-headline-sm text-headline-sm border-0 focus:outline-none focus:ring-0 <?php echo $index === 0 ? '' : 'collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $sub['id']; ?>" aria-expanded="<?php echo $index === 0 ? 'true' : 'false'; ?>" aria-controls="collapse<?php echo $sub['id']; ?>">
                                        <span class="text-left font-bold"><?php echo e($sub['title']); ?></span>
                                        <span class="bg-secondary/15 text-secondary border border-secondary/25 px-sm py-0.5 rounded-full text-xs font-bold mr-6">
                                            <?php echo isset($lessons_by_subject[$sub['id']]) ? count($lessons_by_subject[$sub['id']]) : 0; ?> Lessons
                                        </span>
                                    </button>
                                </h2>
                                <div id="collapse<?php echo $sub['id']; ?>" class="accordion-collapse collapse <?php echo $index === 0 ? 'show' : ''; ?>" aria-labelledby="heading<?php echo $sub['id']; ?>" data-bs-parent="#syllabusAccordion">
                                    <div class="px-md pb-md pt-sm space-y-md">
                                        <?php if (empty($lessons_by_subject[$sub['id']])): ?>
                                            <div class="p-md rounded-xl border border-dashed border-outline-variant/40 bg-surface-container-low/30 text-center text-on-surface-variant/70 text-sm w-full">
                                                <span class="material-symbols-outlined block text-3xl mb-xs text-on-surface-variant/40">auto_stories</span>
                                                No lessons available for this chapter
                                            </div>
                                        <?php else: ?>
                                            <?php 
                                            $chap_lessons = $lessons_by_subject[$sub['id']];
                                            $is_single = (count($chap_lessons) === 1);
                                            ?>
                                            <div class="grid grid-cols-1 <?php echo $is_single ? '' : 'md:grid-cols-2'; ?> gap-md w-full">
                                                <?php foreach ($chap_lessons as $l): ?>
                                                    <div class="p-md border border-outline-variant/30 rounded-xl bg-surface-container-lowest flex flex-col justify-between hover:translate-y-[-2px] transition-all duration-200 shadow-sm hover:shadow-md">
                                                        <div class="space-y-sm">
                                                            <!-- Lesson Header: Type, Duration, Completion Badge -->
                                                            <div class="flex justify-between items-center text-xs mb-1">
                                                                <span class="inline-flex items-center gap-xs font-semibold text-secondary">
                                                                    <?php if ($l['content_type'] === 'text'): ?>
                                                                        <span class="material-symbols-outlined text-[16px]">menu_book</span> Reading
                                                                    <?php elseif ($l['content_type'] === 'video'): ?>
                                                                        <span class="material-symbols-outlined text-[16px]">play_circle</span> Video
                                                                    <?php elseif ($l['content_type'] === 'pdf'): ?>
                                                                        <span class="material-symbols-outlined text-[16px]">picture_as_pdf</span> Document
                                                                    <?php endif; ?>
                                                                    <span>•</span>
                                                                    <span><?php echo $l['duration_mins']; ?> mins</span>
                                                                </span>
                                                                
                                                                <?php if ($l['completion_status'] === 'completed'): ?>
                                                                    <span class="inline-flex items-center gap-xs bg-green-500/10 text-green-500 border border-green-500/25 px-2 py-0.5 rounded-full font-bold font-label-sm">
                                                                        <span class="material-symbols-outlined text-[12px]" style="font-variation-settings: 'FILL' 1;">check_circle</span>
                                                                        Completed
                                                                    </span>
                                                                <?php endif; ?>
                                                            </div>

                                                            <!-- Lesson Thumbnail/Banner (if file_path is an image) -->
                                                            <?php 
                                                            $is_img = ($l['file_path'] && in_array(strtolower(pathinfo($l['file_path'], PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']));
                                                            ?>
                                                            <?php if ($is_img): ?>
                                                                <div class="relative h-32 w-full rounded-lg overflow-hidden my-2 bg-surface-container-high">
                                                                    <img src="<?php echo BASE_URL . e($l['file_path']); ?>" alt="Lesson Thumbnail" class="w-full h-full object-cover">
                                                                </div>
                                                            <?php endif; ?>

                                                            <!-- Title & Description -->
                                                            <h4 class="font-headline-sm text-sm text-on-surface font-bold leading-snug"><?php echo e($l['title']); ?></h4>
                                                            <?php if ($l['content']): ?>
                                                                <p class="font-body-xs text-xs text-on-surface-variant line-clamp-3 leading-relaxed mb-0">
                                                                    <?php echo e(strip_tags($l['content'])); ?>
                                                                </p>
                                                            <?php endif; ?>
                                                        </div>

                                                        <!-- Action button -->
                                                        <div class="pt-md border-t border-outline-variant/15 mt-md">
                                                            <a href="lesson-view.php?lesson_id=<?php echo $l['id']; ?>" class="inline-flex w-full items-center justify-center gap-xs bg-primary text-white hover:bg-primary-hover py-2 rounded-lg font-label-md text-xs text-decoration-none transition-colors shadow-sm font-semibold">
                                                                <span class="material-symbols-outlined text-[16px]">play_circle</span>
                                                                Start Lesson
                                                            </a>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php
include_once __DIR__ . '/../includes/footer.php';
?>
