<?php
$page_title = "Browse Courses";
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/middleware.php';

require_role('Student');

$user_id = $_SESSION['user_id'];

// Get Student ID
$stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
$stmt->execute([$user_id]);
$student = $stmt->fetch();
$student_id = $student['id'] ?? 0;

if (!$student_id) {
    set_flash_message('danger', 'Student profile not found.');
    header("Location: " . BASE_URL . "login.php");
    exit();
}

// Handle enrollment request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'enroll') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    $course_to_enroll = intval($_POST['course_id'] ?? 0);
    
    if (!verify_csrf_token($csrf_token)) {
        set_flash_message('danger', 'CSRF validation failed.');
    } else {
        // Check if course is published
        $chk_stmt = $pdo->prepare("SELECT id, title FROM courses WHERE id = ? AND status = 'published'");
        $chk_stmt->execute([$course_to_enroll]);
        $course_info = $chk_stmt->fetch();
        
        if ($course_info) {
            // Insert enrollment
            try {
                $enroll_stmt = $pdo->prepare("INSERT INTO course_enrollments (student_id, course_id, progress_percent, status) VALUES (?, ?, 0, 'active')");
                $enroll_stmt->execute([$student_id, $course_to_enroll]);
                
                log_activity($pdo, $user_id, 'Enroll Course', "Enrolled in course: " . $course_info['title'] . " (ID: $course_to_enroll)");
                
                // Add system notification
                $notif_stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
                $notif_stmt->execute([
                    $user_id,
                    'Course Enrolled!',
                    "You have successfully enrolled in " . $course_info['title'] . ". Start learning today!",
                    'course'
                ]);
                
                set_flash_message('success', 'Successfully enrolled in course!');
                header("Location: course-view.php?course_id=" . $course_to_enroll);
                exit();
            } catch (PDOException $e) {
                // If unique constraint triggers
                set_flash_message('info', 'You are already enrolled in this course.');
                header("Location: course-view.php?course_id=" . $course_to_enroll);
                exit();
            }
        } else {
            set_flash_message('danger', 'Course not available.');
        }
    }
}

// Get Search & Category Filters
$search = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');

// Build Query
$query_str = "
    SELECT c.*, u.full_name AS teacher_name, 
           (SELECT COUNT(*) FROM subjects WHERE course_id = c.id) as subjects_count,
           e.id AS enrollment_id, e.progress_percent
    FROM courses c 
    JOIN teachers t ON c.teacher_id = t.id
    JOIN users u ON t.user_id = u.id 
    LEFT JOIN course_enrollments e ON e.course_id = c.id AND e.student_id = ?
    WHERE c.status = 'published'
";
$params = [$student_id];

if (!empty($search)) {
    $query_str .= " AND (c.title LIKE ? OR c.description LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

if (!empty($category)) {
    $query_str .= " AND c.category = ?";
    $params[] = $category;
}

$query_str .= " ORDER BY c.created_at DESC";

$stmt = $pdo->prepare($query_str);
$stmt->execute($params);
$courses = $stmt->fetchAll();

// Get unique categories for sidebar filter
$cat_stmt = $pdo->query("SELECT DISTINCT category FROM courses WHERE status = 'published' AND category IS NOT NULL AND category != ''");
$categories = $cat_stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="space-y-lg">
    <!-- Header & Search -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-md mb-lg">
        <div>
            <h2 class="font-headline-lg text-headline-lg text-on-surface mb-xs"><?php echo e(__('nav.courses')); ?></h2>
            <p class="font-body-md text-body-md text-on-surface-variant">Expand your knowledge by subscribing to educational modules.</p>
        </div>
        
        <!-- Search Bar -->
        <div class="w-full md:w-96">
            <form action="courses.php" method="GET" class="flex gap-2">
                <div class="relative flex-grow">
                    <input type="text" name="search" class="w-full pl-10 pr-4 py-2 bg-surface-container-low border border-outline-variant/30 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none text-body-sm dark:text-white" placeholder="Search courses..." value="<?php echo e($search); ?>">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline-variant text-[20px]">search</span>
                </div>
                <?php if (!empty($category)): ?>
                    <input type="hidden" name="category" value="<?php echo e($category); ?>">
                <?php endif; ?>
                <button type="submit" class="bg-primary text-white hover:bg-primary-hover p-2 rounded-lg transition-colors flex items-center justify-center">
                    <span class="material-symbols-outlined text-[20px]">search</span>
                </button>
                <?php if (!empty($search) || !empty($category)): ?>
                    <a href="courses.php" class="bg-surface-container-high hover:bg-surface-container-highest text-on-surface p-2 rounded-lg transition-colors flex items-center justify-center text-decoration-none" title="Clear Filters">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-gutter">
        <!-- Categories Filter Side Menu -->
        <div class="lg:col-span-3">
            <div class="glass-card rounded-xl p-md">
                <div class="flex items-center gap-xs mb-md pb-xs border-b border-outline-variant/30">
                    <span class="material-symbols-outlined text-primary text-[20px]">filter_alt</span>
                    <h3 class="font-headline-sm text-headline-sm text-on-surface">Categories</h3>
                </div>
                <div class="flex flex-col gap-1">
                    <a href="courses.php<?php echo !empty($search) ? '?search=' . urlencode($search) : ''; ?>" class="flex items-center gap-sm px-sm py-2 rounded-lg text-body-sm text-decoration-none transition-colors <?php echo empty($category) ? 'bg-primary text-white font-semibold' : 'text-on-surface-variant hover:bg-surface-variant/50'; ?>">
                        <span class="material-symbols-outlined text-[18px]">apps</span>
                        All Subjects
                    </a>
                    <?php foreach ($categories as $cat): ?>
                        <a href="courses.php?category=<?php echo urlencode($cat); ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="flex items-center gap-sm px-sm py-2 rounded-lg text-body-sm text-decoration-none transition-colors <?php echo $category === $cat ? 'bg-primary text-white font-semibold' : 'text-on-surface-variant hover:bg-surface-variant/50'; ?>">
                            <span class="material-symbols-outlined text-[18px] <?php echo $category === $cat ? 'text-white' : 'text-primary'; ?>">bookmark</span>
                            <?php echo e($cat); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Courses Grid -->
        <div class="lg:col-span-9">
            <?php if (empty($courses)): ?>
                <div class="glass-card rounded-xl text-center py-xl space-y-md">
                    <span class="material-symbols-outlined text-secondary text-5xl">menu_book</span>
                    <p class="font-body-lg text-body-lg text-on-surface-variant">No published courses found matching your criteria.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-md">
                    <?php foreach ($courses as $c): ?>
                        <div class="glass-card rounded-xl overflow-hidden p-md flex flex-col h-full hover:translate-y-[-4px] transition-transform shadow-md hover:shadow-xl">
                            <!-- Image banner -->
                            <div class="relative h-44 w-full rounded-lg overflow-hidden mb-sm bg-surface-container-high flex items-center justify-center">
                                <?php if ($c['image_path']): ?>
                                    <img src="<?php echo BASE_URL . e($c['image_path']); ?>" alt="Banner" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <span class="material-symbols-outlined text-primary text-[64px]">menu_book</span>
                                <?php endif; ?>
                                <div class="absolute top-2 left-2 flex gap-1">
                                    <span class="bg-primary text-white text-[10px] font-bold px-2 py-0.5 rounded-full uppercase"><?php echo e($c['category']); ?></span>
                                    <span class="bg-secondary text-white text-[10px] font-bold px-2 py-0.5 rounded-full uppercase"><?php echo e($c['difficulty_level']); ?></span>
                                </div>
                            </div>

                            <h4 class="font-headline-sm text-headline-sm text-on-surface font-bold mb-xs"><?php echo e($c['title']); ?></h4>
                            <p class="font-body-sm text-body-sm text-on-surface-variant flex-grow mb-md"><?php echo e(substr($c['description'], 0, 120)); ?>...</p>

                            <div class="border-t border-outline-variant/30 pt-md space-y-md mt-auto">
                                <div class="flex justify-between items-center text-xs text-on-surface-variant">
                                    <span class="flex items-center gap-xs"><span class="material-symbols-outlined text-[16px]">person</span> <?php echo e($c['teacher_name']); ?></span>
                                    <span class="flex items-center gap-xs"><span class="material-symbols-outlined text-[16px]">folder</span> <?php echo $c['subjects_count']; ?> Units</span>
                                </div>

                                <?php if ($c['enrollment_id']): ?>
                                    <!-- Progress Bar -->
                                    <div class="space-y-1">
                                        <div class="flex justify-between font-label-sm text-label-sm text-on-surface-variant">
                                            <span>Progress</span>
                                            <span><?php echo $c['progress_percent']; ?>%</span>
                                        </div>
                                        <div class="w-full h-1.5 bg-surface-container rounded-full overflow-hidden">
                                            <div class="bg-primary h-full transition-all duration-500" style="width: <?php echo $c['progress_percent']; ?>%;"></div>
                                        </div>
                                    </div>
                                    
                                    <a href="course-view.php?course_id=<?php echo $c['id']; ?>" class="inline-flex w-full items-center justify-center gap-2 bg-secondary text-white hover:bg-secondary-hover py-2.5 rounded-lg font-label-md text-label-md text-decoration-none transition-colors shadow-sm">
                                        <span class="material-symbols-outlined text-[18px]">play_circle</span>
                                        <?php echo e(__('student.continue_learning')); ?>
                                    </a>
                                <?php else: ?>
                                    <form action="courses.php" method="POST" class="m-0">
                                        <input type="hidden" name="csrf_token" value="<?php echo e(generate_csrf_token()); ?>">
                                        <input type="hidden" name="course_id" value="<?php echo $c['id']; ?>">
                                        <input type="hidden" name="action" value="enroll">
                                        
                                        <button type="submit" class="w-full flex items-center justify-center gap-2 bg-primary text-white hover:bg-primary-hover py-2.5 rounded-lg font-label-md text-label-md transition-colors shadow-sm">
                                            <span class="material-symbols-outlined text-[18px]">add_circle</span>
                                            <?php echo e(__('student.enroll_btn')); ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
include_once __DIR__ . '/../includes/footer.php';
?>
