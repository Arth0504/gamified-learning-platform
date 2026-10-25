<?php
$page_title = "Manage Subjects";
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/middleware.php';

require_role('Teacher');

$user_id = $_SESSION['user_id'];
$course_id = $_GET['course_id'] ?? $_POST['course_id'] ?? null;

if (!$course_id) {
    set_flash_message('danger', 'Course ID is missing.');
    header("Location: courses.php");
    exit();
}

// Get Teacher ID
$stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
$stmt->execute([$user_id]);
$teacher = $stmt->fetch();
$teacher_id = $teacher['id'] ?? 0;

// Verify Course Ownership
$course_stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND teacher_id = ?");
$course_stmt->execute([$course_id, $teacher_id]);
$course = $course_stmt->fetch();

if (!$course) {
    set_flash_message('danger', 'Course not found or unauthorized access.');
    header("Location: courses.php");
    exit();
}

// Handle Form Submissions
$action = $_GET['action'] ?? 'list';
$edit_subject = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        set_flash_message('danger', 'CSRF validation failed.');
    } else {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $sort_order = intval($_POST['sort_order'] ?? 0);
        
        if (empty($title)) {
            set_flash_message('danger', 'Subject Title is required.');
        } elseif (isset($_POST['subject_id'])) {
            // Edit Subject
            $subject_id = $_POST['subject_id'];
            
            // Verify ownership via course ID
            $check = $pdo->prepare("SELECT s.id FROM subjects s JOIN courses c ON s.course_id = c.id WHERE s.id = ? AND c.teacher_id = ?");
            $check->execute([$subject_id, $teacher_id]);
            
            if ($check->fetch()) {
                $stmt = $pdo->prepare("UPDATE subjects SET title = ?, description = ?, sort_order = ? WHERE id = ?");
                $stmt->execute([$title, $description, $sort_order, $subject_id]);
                
                log_activity($pdo, $user_id, 'Edit Subject', "Updated subject: $title (ID: $subject_id)");
                set_flash_message('success', 'Subject updated successfully!');
                header("Location: subjects.php?course_id=" . $course_id);
                exit();
            } else {
                set_flash_message('danger', 'Access denied.');
            }
        } else {
            // Add Subject
            $stmt = $pdo->prepare("INSERT INTO subjects (course_id, title, description, sort_order) VALUES (?, ?, ?, ?)");
            $stmt->execute([$course_id, $title, $description, $sort_order]);
            
            log_activity($pdo, $user_id, 'Create Subject', "Created subject: $title under course: " . $course['title']);
            set_flash_message('success', 'Subject added successfully!');
            header("Location: subjects.php?course_id=" . $course_id);
            exit();
        }
    }
}

// Handle Delete Subject
if ($action === 'delete' && isset($_GET['id'])) {
    $subject_id = $_GET['id'];
    
    // Verify ownership via course ID
    $check = $pdo->prepare("SELECT s.id, s.title FROM subjects s JOIN courses c ON s.course_id = c.id WHERE s.id = ? AND c.teacher_id = ?");
    $check->execute([$subject_id, $teacher_id]);
    $sub = $check->fetch();
    
    if ($sub) {
        $stmt = $pdo->prepare("DELETE FROM subjects WHERE id = ?");
        $stmt->execute([$subject_id]);
        
        log_activity($pdo, $user_id, 'Delete Subject', "Deleted subject: " . $sub['title'] . " (ID: $subject_id)");
        set_flash_message('success', 'Subject deleted successfully!');
    } else {
        set_flash_message('danger', 'Unauthorized or subject not found.');
    }
    header("Location: subjects.php?course_id=" . $course_id);
    exit();
}

// Edit Mode Load
if ($action === 'edit' && isset($_GET['id'])) {
    $subject_id = $_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM subjects WHERE id = ? AND course_id = ?");
    $stmt->execute([$subject_id, $course_id]);
    $edit_subject = $stmt->fetch();
    if (!$edit_subject) {
        set_flash_message('danger', 'Subject not found.');
        header("Location: subjects.php?course_id=" . $course_id);
        exit();
    }
}

// Get Course Subjects
$stmt = $pdo->prepare("SELECT s.*, (SELECT COUNT(*) FROM lessons WHERE subject_id = s.id) as lessons_count FROM subjects s WHERE s.course_id = ? ORDER BY s.sort_order ASC, s.created_at ASC");
$stmt->execute([$course_id]);
$subjects = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-md-8">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="courses.php" class="text-success text-decoration-none">Courses</a></li>
                <li class="breadcrumb-item text-secondary active" aria-current="page"><?php echo e($course['title']); ?></li>
            </ol>
        </nav>
        <h2 class="font-heading font-weight-bold text-success">
            <?php echo $action === 'edit' ? 'Edit Subject' : ($action === 'add' ? 'Add Subject to Course' : 'Course Subjects'); ?>
        </h2>
    </div>
    <div class="col-md-4 text-md-end d-flex align-items-end justify-content-md-end gap-2">
        <?php if ($action === 'list'): ?>
            <a href="subjects.php?course_id=<?php echo $course_id; ?>&action=add" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> <?php echo e(__('teacher.add_subject')); ?>
            </a>
            <a href="courses.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to Courses
            </a>
        <?php else: ?>
            <a href="subjects.php?course_id=<?php echo $course_id; ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Cancel
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($action === 'list'): ?>
    <!-- LIST SUBJECTS VIEW -->
    <div class="row">
        <div class="col-12">
            <div class="glass-card">
                <?php if (empty($subjects)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-tags text-secondary display-4 mb-3"></i>
                        <p class="text-secondary"><?php echo e(__('common.no_records')); ?></p>
                        <a href="subjects.php?course_id=<?php echo $course_id; ?>&action=add" class="btn btn-primary">Add Your First Subject</a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle table-hover">
                            <thead>
                                <tr>
                                    <th style="width: 100px;">Sort Order</th>
                                    <th>Title</th>
                                    <th>Description</th>
                                    <th>Lessons Count</th>
                                    <th style="width: 250px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($subjects as $s): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-secondary px-2.5 py-1.5"><?php echo $s['sort_order']; ?></span>
                                        </td>
                                        <td>
                                            <strong class="text-dark-theme-compatible"><?php echo e($s['title']); ?></strong>
                                        </td>
                                        <td class="text-secondary small">
                                            <?php echo e(substr($s['description'], 0, 100)); ?>...
                                        </td>
                                        <td>
                                            <span class="badge bg-success"><?php echo $s['lessons_count']; ?> Lessons</span>
                                        </td>
                                        <td>
                                            <a href="lessons.php?subject_id=<?php echo $s['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="bi bi-journal-plus me-1"></i> Lessons
                                            </a>
                                            <a href="subjects.php?course_id=<?php echo $course_id; ?>&action=edit&id=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline-secondary mx-1" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="subjects.php?course_id=<?php echo $course_id; ?>&action=delete&id=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this subject and all its lessons/quizzes?');" title="Delete">
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
    <!-- ADD/EDIT FORM VIEW -->
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="glass-card">
                <form action="subjects.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo e(generate_csrf_token()); ?>">
                    <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                    
                    <?php if ($edit_subject): ?>
                        <input type="hidden" name="subject_id" value="<?php echo $edit_subject['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="title" class="form-label font-heading">Subject Title</label>
                        <input type="text" class="form-control" id="title" name="title" value="<?php echo $edit_subject ? e($edit_subject['title']) : ''; ?>" placeholder="e.g. Chapter 1: Counting Things" required>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label font-heading">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3" placeholder="Outline what this subject covers..."><?php echo $edit_subject ? e($edit_subject['description']) : ''; ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="sort_order" class="form-label font-heading">Sort Order</label>
                        <input type="number" class="form-control" id="sort_order" name="sort_order" value="<?php echo $edit_subject ? intval($edit_subject['sort_order']) : '0'; ?>" min="0">
                        <div class="form-text small">Use numbers (0, 1, 2, 3...) to order the subjects in sequence.</div>
                    </div>

                    <div class="mt-4 border-top pt-3 border-color">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Save Subject</button>
                        <a href="subjects.php?course_id=<?php echo $course_id; ?>" class="btn btn-outline-secondary ms-2">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
include_once __DIR__ . '/../includes/footer.php';
?>
