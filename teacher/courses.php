<?php
$page_title = "Manage Courses";
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/middleware.php';

require_role('Teacher');

$user_id = $_SESSION['user_id'];

// Get Teacher ID
$stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
$stmt->execute([$user_id]);
$teacher = $stmt->fetch();

if (!$teacher) {
    // Auto-create teacher record if missing
    $stmt = $pdo->prepare("INSERT INTO teachers (user_id, specialization) VALUES (?, 'General')");
    $stmt->execute([$user_id]);
    $teacher_id = $pdo->lastInsertId();
} else {
    $teacher_id = $teacher['id'];
}

// Ensure Upload Directory exists
$upload_dir = dirname(__DIR__) . '/assets/uploads';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Handle Form Submissions
$action = $_GET['action'] ?? 'list';
$edit_course = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        set_flash_message('danger', 'CSRF validation failed.');
    } else {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $difficulty_level = $_POST['difficulty_level'] ?? 'easy';
        $status = $_POST['status'] ?? 'draft';
        
        // Handle file upload
        $image_path = $_POST['existing_image'] ?? '';
        if (isset($_FILES['course_image']) && $_FILES['course_image']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['course_image']['tmp_name'];
            $file_name = time() . '_' . basename($_FILES['course_image']['name']);
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (in_array($file_ext, $allowed_exts)) {
                if (move_uploaded_file($file_tmp, $upload_dir . '/' . $file_name)) {
                    $image_path = 'assets/uploads/' . $file_name;
                }
            } else {
                set_flash_message('warning', 'Invalid image file format. Using existing image or placeholder.');
            }
        }

        if (empty($title)) {
            set_flash_message('danger', 'Course Title is required.');
        } elseif (isset($_POST['course_id'])) {
            // Edit Course
            $course_id = $_POST['course_id'];
            // Verify ownership
            $check = $pdo->prepare("SELECT id FROM courses WHERE id = ? AND teacher_id = ?");
            $check->execute([$course_id, $teacher_id]);
            
            if ($check->fetch()) {
                $stmt = $pdo->prepare("UPDATE courses SET title = ?, description = ?, category = ?, difficulty_level = ?, image_path = ?, status = ? WHERE id = ?");
                $stmt->execute([$title, $description, $category, $difficulty_level, $image_path, $status, $course_id]);
                
                log_activity($pdo, $user_id, 'Edit Course', "Updated course: $title (ID: $course_id)");
                set_flash_message('success', 'Course updated successfully!');
                header("Location: courses.php");
                exit();
            } else {
                set_flash_message('danger', 'Access denied.');
            }
        } else {
            // Add Course
            $stmt = $pdo->prepare("INSERT INTO courses (teacher_id, title, description, category, difficulty_level, image_path, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$teacher_id, $title, $description, $category, $difficulty_level, $image_path, $status]);
            
            log_activity($pdo, $user_id, 'Create Course', "Created new course: $title");
            set_flash_message('success', 'Course created successfully!');
            header("Location: courses.php");
            exit();
        }
    }
}

// Handle Delete Course
if ($action === 'delete' && isset($_GET['id'])) {
    $course_id = $_GET['id'];
    // Verify ownership
    $check = $pdo->prepare("SELECT id, title FROM courses WHERE id = ? AND teacher_id = ?");
    $check->execute([$course_id, $teacher_id]);
    $c = $check->fetch();
    
    if ($c) {
        $stmt = $pdo->prepare("DELETE FROM courses WHERE id = ?");
        $stmt->execute([$course_id]);
        
        log_activity($pdo, $user_id, 'Delete Course', "Deleted course: " . $c['title'] . " (ID: $course_id)");
        set_flash_message('success', 'Course deleted successfully!');
    } else {
        set_flash_message('danger', 'Unauthorized or course not found.');
    }
    header("Location: courses.php");
    exit();
}

// Edit Mode Load
if ($action === 'edit' && isset($_GET['id'])) {
    $course_id = $_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$course_id, $teacher_id]);
    $edit_course = $stmt->fetch();
    if (!$edit_course) {
        set_flash_message('danger', 'Course not found.');
        header("Location: courses.php");
        exit();
    }
}

// Get Teacher's Courses
$stmt = $pdo->prepare("SELECT c.*, (SELECT COUNT(*) FROM subjects WHERE course_id = c.id) as subjects_count FROM courses c WHERE c.teacher_id = ? ORDER BY c.created_at DESC");
$stmt->execute([$teacher_id]);
$courses = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h2 class="font-heading font-weight-bold text-success">
            <?php echo $action === 'edit' ? 'Edit Course' : ($action === 'add' ? 'Add New Course' : 'My Courses'); ?>
        </h2>
    </div>
    <div class="col-md-4 text-md-end">
        <?php if ($action === 'list'): ?>
            <a href="courses.php?action=add" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> <?php echo e(__('teacher.create_course')); ?>
            </a>
        <?php else: ?>
            <a href="courses.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> <?php echo e(__('common.back')); ?>
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($action === 'list'): ?>
    <!-- LIST COURSES VIEW -->
    <div class="row g-4">
        <?php if (empty($courses)): ?>
            <div class="col-12">
                <div class="glass-card text-center py-5">
                    <i class="bi bi-journal-x text-secondary display-3 mb-3"></i>
                    <p class="text-secondary fs-5"><?php echo e(__('common.no_records')); ?></p>
                    <a href="courses.php?action=add" class="btn btn-primary mt-2">Add Your First Course</a>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($courses as $c): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="glass-card h-100 d-flex flex-column">
                        <?php if ($c['image_path']): ?>
                            <img src="<?php echo BASE_URL . e($c['image_path']); ?>" alt="Course Image" class="card-img-top rounded mb-3" style="height: 160px; object-fit: cover;">
                        <?php else: ?>
                            <div class="bg-success text-white rounded mb-3 d-flex align-items-center justify-content-center" style="height: 160px;">
                                <i class="bi bi-journal-bookmark fs-1"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mb-2">
                            <span class="badge bg-success"><?php echo e($c['category']); ?></span>
                            <span class="badge bg-secondary"><?php echo e(ucfirst($c['difficulty_level'])); ?></span>
                            <span class="badge <?php echo $c['status'] === 'published' ? 'bg-info' : 'bg-warning text-dark'; ?>"><?php echo ucfirst($c['status']); ?></span>
                        </div>
                        
                        <h4 class="font-heading text-dark-theme-compatible mb-2"><?php echo e($c['title']); ?></h4>
                        <p class="text-secondary small flex-grow-1"><?php echo e(substr($c['description'], 0, 100)); ?>...</p>
                        
                        <div class="border-top pt-3 mt-3 border-color">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="small text-secondary"><i class="bi bi-folder-fill me-1"></i><?php echo $c['subjects_count']; ?> Subjects</span>
                            </div>
                            
                            <div class="d-flex flex-wrap gap-2">
                                <a href="subjects.php?course_id=<?php echo $c['id']; ?>" class="btn btn-sm btn-primary">
                                    <i class="bi bi-gear-fill me-1"></i> Subjects
                                </a>
                                <a href="courses.php?action=edit&id=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="courses.php?action=delete&id=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this course and all its subjects/lessons/quizzes?');" title="Delete">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

<?php elseif ($action === 'add' || $action === 'edit'): ?>
    <!-- ADD/EDIT FORM VIEW -->
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="glass-card">
                <form action="courses.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo e(generate_csrf_token()); ?>">
                    
                    <?php if ($edit_course): ?>
                        <input type="hidden" name="course_id" value="<?php echo $edit_course['id']; ?>">
                        <input type="hidden" name="existing_image" value="<?php echo e($edit_course['image_path']); ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="title" class="form-label font-heading">Course Title</label>
                        <input type="text" class="form-control" id="title" name="title" value="<?php echo $edit_course ? e($edit_course['title']) : ''; ?>" placeholder="e.g. Basic Science & Plants" required>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label font-heading">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="4" placeholder="Describe what the students will learn..." required><?php echo $edit_course ? e($edit_course['description']) : ''; ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="category" class="form-label font-heading">Category</label>
                            <input type="text" class="form-control" id="category" name="category" value="<?php echo $edit_course ? e($edit_course['category']) : ''; ?>" placeholder="e.g. Science, Mathematics" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="difficulty_level" class="form-label font-heading">Difficulty Level</label>
                            <select class="form-select" id="difficulty_level" name="difficulty_level">
                                <option value="easy" <?php echo ($edit_course && $edit_course['difficulty_level'] === 'easy') ? 'selected' : ''; ?>>Easy</option>
                                <option value="medium" <?php echo ($edit_course && $edit_course['difficulty_level'] === 'medium') ? 'selected' : ''; ?>>Medium</option>
                                <option value="hard" <?php echo ($edit_course && $edit_course['difficulty_level'] === 'hard') ? 'selected' : ''; ?>>Hard</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="course_image" class="form-label font-heading">Course Banner Image</label>
                        <input type="file" class="form-control" id="course_image" name="course_image" accept="image/*">
                        <?php if ($edit_course && $edit_course['image_path']): ?>
                            <div class="mt-2 small text-secondary">
                                Current image: <a href="<?php echo BASE_URL . e($edit_course['image_path']); ?>" target="_blank"><?php echo basename($edit_course['image_path']); ?></a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="status" class="form-label font-heading">Publish Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="draft" <?php echo ($edit_course && $edit_course['status'] === 'draft') ? 'selected' : ''; ?>>Draft</option>
                            <option value="published" <?php echo ($edit_course && $edit_course['status'] === 'published') ? 'selected' : ''; ?>>Published</option>
                        </select>
                    </div>

                    <div class="mt-4 border-top pt-3 border-color">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Save Course</button>
                        <a href="courses.php" class="btn btn-outline-secondary ms-2">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
include_once __DIR__ . '/../includes/footer.php';
?>
