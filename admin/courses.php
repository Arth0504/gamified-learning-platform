<?php
$page_title = "Global Course Administration";
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/middleware.php';

require_role('Admin');

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? 'list';
$edit_course = null;

// Ensure Upload Directory exists
$upload_dir = dirname(__DIR__) . '/assets/uploads';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Fetch list of all teachers for dropdown selection
$teachers_stmt = $pdo->query("
    SELECT t.id, u.full_name 
    FROM teachers t 
    JOIN users u ON t.user_id = u.id
");
$teachers_list = $teachers_stmt->fetchAll();

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
        $teacher_id = intval($_POST['teacher_id'] ?? 0);
        
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
            }
        }

        if (empty($title) || $teacher_id <= 0) {
            set_flash_message('danger', 'Course Title and Assigned Teacher are required.');
        } elseif (isset($_POST['course_id'])) {
            // Edit Course
            $course_id = $_POST['course_id'];
            $stmt = $pdo->prepare("UPDATE courses SET teacher_id = ?, title = ?, description = ?, category = ?, difficulty_level = ?, image_path = ?, status = ? WHERE id = ?");
            $stmt->execute([$teacher_id, $title, $description, $category, $difficulty_level, $image_path, $status, $course_id]);
            
            log_activity($pdo, $user_id, 'Admin Edit Course', "Admin updated course ID: $course_id");
            set_flash_message('success', 'Course updated successfully!');
            header("Location: courses.php");
            exit();
        } else {
            // Add Course
            $stmt = $pdo->prepare("INSERT INTO courses (teacher_id, title, description, category, difficulty_level, image_path, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$teacher_id, $title, $description, $category, $difficulty_level, $image_path, $status]);
            
            log_activity($pdo, $user_id, 'Admin Create Course', "Admin created course: $title");
            set_flash_message('success', 'Course created successfully!');
            header("Location: courses.php");
            exit();
        }
    }
}

// Handle Delete Course
if ($action === 'delete' && isset($_GET['id'])) {
    $course_id = $_GET['id'];
    $stmt = $pdo->prepare("DELETE FROM courses WHERE id = ?");
    $stmt->execute([$course_id]);
    
    log_activity($pdo, $user_id, 'Admin Delete Course', "Admin deleted course ID: $course_id");
    set_flash_message('success', 'Course deleted successfully!');
    header("Location: courses.php");
    exit();
}

// Edit Mode Load
if ($action === 'edit' && isset($_GET['id'])) {
    $course_id = $_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
    $stmt->execute([$course_id]);
    $edit_course = $stmt->fetch();
    if (!$edit_course) {
        set_flash_message('danger', 'Course not found.');
        header("Location: courses.php");
        exit();
    }
}

// Fetch all courses in the system
$courses_stmt = $pdo->query("
    SELECT c.*, u.full_name AS teacher_name, 
           (SELECT COUNT(*) FROM subjects WHERE course_id = c.id) as subjects_count 
    FROM courses c 
    JOIN teachers t ON c.teacher_id = t.id
    JOIN users u ON t.user_id = u.id 
    ORDER BY c.created_at DESC
");
$courses = $courses_stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h2 class="font-heading font-weight-bold text-success">
            <?php echo $action === 'edit' ? 'Edit Course (Admin)' : ($action === 'add' ? 'Create New Course (Admin)' : 'System Courses'); ?>
        </h2>
    </div>
    <div class="col-md-4 text-md-end">
        <?php if ($action === 'list'): ?>
            <a href="courses.php?action=add" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> Add Course
            </a>
        <?php else: ?>
            <a href="courses.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to Courses
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($action === 'list'): ?>
    <!-- LIST COURSES VIEW -->
    <div class="row">
        <div class="col-12">
            <div class="glass-card">
                <?php if (empty($courses)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-journal-x text-secondary display-4 mb-3"></i>
                        <p class="text-secondary"><?php echo e(__('common.no_records')); ?></p>
                        <a href="courses.php?action=add" class="btn btn-primary">Create First Course</a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle table-hover">
                            <thead>
                                <tr>
                                    <th>Banner</th>
                                    <th>Title</th>
                                    <th>Teacher</th>
                                    <th>Category</th>
                                    <th>Difficulty</th>
                                    <th>Subjects</th>
                                    <th>Status</th>
                                    <th style="width: 180px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($courses as $c): ?>
                                    <tr>
                                        <td>
                                            <?php if ($c['image_path']): ?>
                                                <img src="<?php echo BASE_URL . e($c['image_path']); ?>" alt="Banner" class="rounded" style="width: 50px; height: 35px; object-fit: cover;">
                                            <?php else: ?>
                                                <span class="badge bg-secondary">None</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong class="text-dark-theme-compatible"><?php echo e($c['title']); ?></strong>
                                        </td>
                                        <td class="text-secondary small"><?php echo e($c['teacher_name']); ?></td>
                                        <td>
                                            <span class="badge bg-success"><?php echo e($c['category']); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary"><?php echo ucfirst($c['difficulty_level']); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-secondary border"><?php echo $c['subjects_count']; ?></span>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $c['status'] === 'published' ? 'bg-info' : 'bg-warning text-dark'; ?>">
                                                <?php echo ucfirst($c['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="courses.php?action=edit&id=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="courses.php?action=delete&id=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline-danger ms-1" onclick="return confirm('Are you sure you want to delete this course and all child subjects/lessons/quizzes?');" title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

<?php elseif ($action === 'add' || $action === 'edit'): ?>
    <!-- ADD/EDIT VIEW -->
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
                        <input type="text" class="form-control" id="title" name="title" value="<?php echo $edit_course ? e($edit_course['title']) : ''; ?>" placeholder="e.g. Basic Mathematics" required>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label font-heading">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="4" required><?php echo $edit_course ? e($edit_course['description']) : ''; ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="teacher_id" class="form-label font-heading">Assigned Teacher</label>
                            <select class="form-select" id="teacher_id" name="teacher_id" required>
                                <option value="">-- Select Teacher --</option>
                                <?php foreach ($teachers_list as $t): ?>
                                    <option value="<?php echo $t['id']; ?>" <?php echo ($edit_course && $edit_course['teacher_id'] == $t['id']) ? 'selected' : ''; ?>>
                                        <?php echo e($t['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="category" class="form-label font-heading">Category</label>
                            <input type="text" class="form-control" id="category" name="category" value="<?php echo $edit_course ? e($edit_course['category']) : ''; ?>" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="difficulty_level" class="form-label font-heading">Difficulty</label>
                            <select class="form-select" id="difficulty_level" name="difficulty_level">
                                <option value="easy" <?php echo ($edit_course && $edit_course['difficulty_level'] === 'easy') ? 'selected' : ''; ?>>Easy</option>
                                <option value="medium" <?php echo ($edit_course && $edit_course['difficulty_level'] === 'medium') ? 'selected' : ''; ?>>Medium</option>
                                <option value="hard" <?php echo ($edit_course && $edit_course['difficulty_level'] === 'hard') ? 'selected' : ''; ?>>Hard</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label font-heading">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="draft" <?php echo ($edit_course && $edit_course['status'] === 'draft') ? 'selected' : ''; ?>>Draft</option>
                                <option value="published" <?php echo ($edit_course && $edit_course['status'] === 'published') ? 'selected' : ''; ?>>Published</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="course_image" class="form-label font-heading">Banner Image</label>
                        <input type="file" class="form-control" id="course_image" name="course_image">
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
