<?php
$page_title = "Manage Lessons";
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/middleware.php';

require_role('Teacher');

$user_id = $_SESSION['user_id'];
$subject_id = $_GET['subject_id'] ?? $_POST['subject_id'] ?? null;

if (!$subject_id) {
    set_flash_message('danger', 'Subject ID is missing.');
    header("Location: courses.php");
    exit();
}

// Get Teacher ID
$stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
$stmt->execute([$user_id]);
$teacher = $stmt->fetch();
$teacher_id = $teacher['id'] ?? 0;

// Verify Subject Ownership
$subject_stmt = $pdo->prepare("
    SELECT s.*, c.title AS course_title, c.id AS course_id 
    FROM subjects s 
    JOIN courses c ON s.course_id = c.id 
    WHERE s.id = ? AND c.teacher_id = ?
");
$subject_stmt->execute([$subject_id, $teacher_id]);
$subject = $subject_stmt->fetch();

if (!$subject) {
    set_flash_message('danger', 'Subject not found or unauthorized access.');
    header("Location: courses.php");
    exit();
}

// Ensure Upload Directory exists
$upload_dir = dirname(__DIR__) . '/assets/uploads';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Handle Form Submissions
$action = $_GET['action'] ?? 'list';
$edit_lesson = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        set_flash_message('danger', 'CSRF validation failed.');
    } else {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $content_type = $_POST['content_type'] ?? 'text';
        $external_url = trim($_POST['external_url'] ?? '');
        $duration_mins = intval($_POST['duration_mins'] ?? 0);
        $is_downloadable = isset($_POST['is_downloadable']) ? 1 : 0;
        $sort_order = intval($_POST['sort_order'] ?? 0);
        
        // Handle File upload for PDF or Video
        $file_path = $_POST['existing_file'] ?? '';
        if (isset($_FILES['lesson_file']) && $_FILES['lesson_file']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['lesson_file']['tmp_name'];
            $file_name = time() . '_' . basename($_FILES['lesson_file']['name']);
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            // Validate extension according to type
            $allowed = false;
            if ($content_type === 'pdf' && $file_ext === 'pdf') {
                $allowed = true;
            } elseif ($content_type === 'video' && in_array($file_ext, ['mp4', 'webm', 'ogg', 'avi'])) {
                $allowed = true;
            }
            
            if ($allowed) {
                if (move_uploaded_file($file_tmp, $upload_dir . '/' . $file_name)) {
                    $file_path = 'assets/uploads/' . $file_name;
                }
            } else {
                set_flash_message('warning', 'Uploaded file extension does not match the lesson content type.');
            }
        }
        
        if (empty($title)) {
            set_flash_message('danger', 'Lesson Title is required.');
        } elseif (isset($_POST['lesson_id'])) {
            // Edit Lesson
            $lesson_id = $_POST['lesson_id'];
            
            // Verify ownership
            $check = $pdo->prepare("
                SELECT l.id 
                FROM lessons l 
                JOIN subjects s ON l.subject_id = s.id 
                JOIN courses c ON s.course_id = c.id 
                WHERE l.id = ? AND c.teacher_id = ?
            ");
            $check->execute([$lesson_id, $teacher_id]);
            
            if ($check->fetch()) {
                $stmt = $pdo->prepare("
                    UPDATE lessons 
                    SET title = ?, content = ?, content_type = ?, file_path = ?, external_url = ?, duration_mins = ?, is_downloadable = ?, sort_order = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$title, $content, $content_type, $file_path, $external_url, $duration_mins, $is_downloadable, $sort_order, $lesson_id]);
                
                log_activity($pdo, $user_id, 'Edit Lesson', "Updated lesson: $title (ID: $lesson_id)");
                set_flash_message('success', 'Lesson updated successfully!');
                header("Location: lessons.php?subject_id=" . $subject_id);
                exit();
            } else {
                set_flash_message('danger', 'Access denied.');
            }
        } else {
            // Add Lesson
            $stmt = $pdo->prepare("
                INSERT INTO lessons (subject_id, title, content, content_type, file_path, external_url, duration_mins, is_downloadable, sort_order) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$subject_id, $title, $content, $content_type, $file_path, $external_url, $duration_mins, $is_downloadable, $sort_order]);
            
            log_activity($pdo, $user_id, 'Create Lesson', "Created lesson: $title under subject: " . $subject['title']);
            set_flash_message('success', 'Lesson created successfully!');
            header("Location: lessons.php?subject_id=" . $subject_id);
            exit();
        }
    }
}

// Handle Delete Lesson
if ($action === 'delete' && isset($_GET['id'])) {
    $lesson_id = $_GET['id'];
    
    // Verify ownership
    $check = $pdo->prepare("
        SELECT l.id, l.title 
        FROM lessons l 
        JOIN subjects s ON l.subject_id = s.id 
        JOIN courses c ON s.course_id = c.id 
        WHERE l.id = ? AND c.teacher_id = ?
    ");
    $check->execute([$lesson_id, $teacher_id]);
    $les = $check->fetch();
    
    if ($les) {
        $stmt = $pdo->prepare("DELETE FROM lessons WHERE id = ?");
        $stmt->execute([$lesson_id]);
        
        log_activity($pdo, $user_id, 'Delete Lesson', "Deleted lesson: " . $les['title'] . " (ID: $lesson_id)");
        set_flash_message('success', 'Lesson deleted successfully!');
    } else {
        set_flash_message('danger', 'Unauthorized or lesson not found.');
    }
    header("Location: lessons.php?subject_id=" . $subject_id);
    exit();
}

// Edit Mode Load
if ($action === 'edit' && isset($_GET['id'])) {
    $lesson_id = $_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM lessons WHERE id = ? AND subject_id = ?");
    $stmt->execute([$lesson_id, $subject_id]);
    $edit_lesson = $stmt->fetch();
    if (!$edit_lesson) {
        set_flash_message('danger', 'Lesson not found.');
        header("Location: lessons.php?subject_id=" . $subject_id);
        exit();
    }
}

// Get Subject Lessons
$stmt = $pdo->prepare("
    SELECT l.*, (SELECT COUNT(*) FROM quizzes WHERE lesson_id = l.id) as quizzes_count 
    FROM lessons l 
    WHERE l.subject_id = ? 
    ORDER BY l.sort_order ASC, l.created_at ASC
");
$stmt->execute([$subject_id]);
$lessons = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-md-8">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="courses.php" class="text-success text-decoration-none">Courses</a></li>
                <li class="breadcrumb-item"><a href="subjects.php?course_id=<?php echo $subject['course_id']; ?>" class="text-success text-decoration-none"><?php echo e($subject['course_title']); ?></a></li>
                <li class="breadcrumb-item text-secondary active" aria-current="page"><?php echo e($subject['title']); ?></li>
            </ol>
        </nav>
        <h2 class="font-heading font-weight-bold text-success">
            <?php echo $action === 'edit' ? 'Edit Lesson' : ($action === 'add' ? 'Add Lesson' : 'Subject Lessons'); ?>
        </h2>
    </div>
    <div class="col-md-4 text-md-end d-flex align-items-end justify-content-md-end gap-2">
        <?php if ($action === 'list'): ?>
            <a href="lessons.php?subject_id=<?php echo $subject_id; ?>&action=add" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> <?php echo e(__('teacher.add_lesson')); ?>
            </a>
            <a href="subjects.php?course_id=<?php echo $subject['course_id']; ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to Subjects
            </a>
        <?php else: ?>
            <a href="lessons.php?subject_id=<?php echo $subject_id; ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Cancel
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($action === 'list'): ?>
    <!-- LIST LESSONS VIEW -->
    <div class="row">
        <div class="col-12">
            <div class="glass-card">
                <?php if (empty($lessons)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-book text-secondary display-4 mb-3"></i>
                        <p class="text-secondary"><?php echo e(__('common.no_records')); ?></p>
                        <a href="lessons.php?subject_id=<?php echo $subject_id; ?>&action=add" class="btn btn-primary">Add Your First Lesson</a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle table-hover">
                            <thead>
                                <tr>
                                    <th style="width: 80px;">Order</th>
                                    <th>Title</th>
                                    <th>Type</th>
                                    <th>Duration</th>
                                    <th>Associated Quiz</th>
                                    <th style="width: 300px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lessons as $l): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-secondary px-2.5 py-1.5"><?php echo $l['sort_order']; ?></span>
                                        </td>
                                        <td>
                                            <strong class="text-dark-theme-compatible"><?php echo e($l['title']); ?></strong>
                                        </td>
                                        <td>
                                            <?php if ($l['content_type'] === 'text'): ?>
                                                <span class="badge bg-success"><i class="bi bi-file-text me-1"></i> Text</span>
                                            <?php elseif ($l['content_type'] === 'video'): ?>
                                                <span class="badge bg-info"><i class="bi bi-play-btn me-1"></i> Video</span>
                                            <?php elseif ($l['content_type'] === 'pdf'): ?>
                                                <span class="badge bg-danger"><i class="bi bi-file-pdf me-1"></i> PDF</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-secondary small">
                                            <?php echo $l['duration_mins']; ?> mins
                                        </td>
                                        <td>
                                            <?php if ($l['quizzes_count'] > 0): ?>
                                                <span class="badge bg-warning text-dark"><i class="bi bi-check-circle-fill me-1"></i> Configured</span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-secondary border"><i class="bi bi-dash-circle me-1"></i> Missing</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="quizzes.php?lesson_id=<?php echo $l['id']; ?>&course_id=<?php echo $subject['course_id']; ?>" class="btn btn-sm btn-warning text-dark font-weight-bold">
                                                <i class="bi bi-question-square-fill me-1"></i> Quiz
                                            </a>
                                            <a href="lessons.php?subject_id=<?php echo $subject_id; ?>&action=edit&id=<?php echo $l['id']; ?>" class="btn btn-sm btn-outline-secondary mx-1" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="lessons.php?subject_id=<?php echo $subject_id; ?>&action=delete&id=<?php echo $l['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this lesson and its quizzes?');" title="Delete">
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
        <div class="col-lg-10">
            <div class="glass-card">
                <form action="lessons.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo e(generate_csrf_token()); ?>">
                    <input type="hidden" name="subject_id" value="<?php echo $subject_id; ?>">
                    
                    <?php if ($edit_lesson): ?>
                        <input type="hidden" name="lesson_id" value="<?php echo $edit_lesson['id']; ?>">
                        <input type="hidden" name="existing_file" value="<?php echo e($edit_lesson['file_path']); ?>">
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label for="title" class="form-label font-heading">Lesson Title</label>
                            <input type="text" class="form-control" id="title" name="title" value="<?php echo $edit_lesson ? e($edit_lesson['title']) : ''; ?>" placeholder="e.g. Lesson 1: Animals on the Farm" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="content_type" class="form-label font-heading">Content Type</label>
                            <select class="form-select" id="content_type" name="content_type" onchange="toggleTypeFields()">
                                <option value="text" <?php echo ($edit_lesson && $edit_lesson['content_type'] === 'text') ? 'selected' : ''; ?>>Read Text</option>
                                <option value="video" <?php echo ($edit_lesson && $edit_lesson['content_type'] === 'video') ? 'selected' : ''; ?>>Watch Video</option>
                                <option value="pdf" <?php echo ($edit_lesson && $edit_lesson['content_type'] === 'pdf') ? 'selected' : ''; ?>>Download PDF Document</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="content" class="form-label font-heading">Lesson Text Content</label>
                        <textarea class="form-control" id="content" name="content" rows="8" placeholder="Type the educational text content here..."><?php echo $edit_lesson ? e($edit_lesson['content']) : ''; ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3" id="video-url-block" style="display:none;">
                            <label for="external_url" class="form-label font-heading">Embed Video Link (YouTube Embed Link)</label>
                            <input type="url" class="form-control" id="external_url" name="external_url" value="<?php echo $edit_lesson ? e($edit_lesson['external_url']) : ''; ?>" placeholder="e.g. https://www.youtube.com/embed/XXXXXX">
                            <div class="form-text small">Use YouTube's embed link syntax for video stream rendering.</div>
                        </div>

                        <div class="col-md-6 mb-3" id="file-upload-block" style="display:none;">
                            <label for="lesson_file" class="form-label font-heading">Upload Lesson File (PDF or MP4/WebM Video)</label>
                            <input type="file" class="form-control" id="lesson_file" name="lesson_file">
                            <?php if ($edit_lesson && $edit_lesson['file_path']): ?>
                                <div class="mt-2 small text-secondary">
                                    Current file: <a href="<?php echo BASE_URL . e($edit_lesson['file_path']); ?>" target="_blank"><?php echo basename($edit_lesson['file_path']); ?></a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="duration_mins" class="form-label font-heading">Duration (Minutes)</label>
                            <input type="number" class="form-control" id="duration_mins" name="duration_mins" value="<?php echo $edit_lesson ? intval($edit_lesson['duration_mins']) : '10'; ?>" min="1">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="sort_order" class="form-label font-heading">Sort Order</label>
                            <input type="number" class="form-control" id="sort_order" name="sort_order" value="<?php echo $edit_lesson ? intval($edit_lesson['sort_order']) : '0'; ?>" min="0">
                        </div>
                        <div class="col-md-4 mb-3 d-flex align-items-center">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" id="is_downloadable" name="is_downloadable" <?php echo (!$edit_lesson || $edit_lesson['is_downloadable'] == 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label font-heading" for="is_downloadable">
                                    Allow file download
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 border-top pt-3 border-color">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Save Lesson</button>
                        <a href="lessons.php?subject_id=<?php echo $subject_id; ?>" class="btn btn-outline-secondary ms-2">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    function toggleTypeFields() {
        var type = document.getElementById("content_type").value;
        var videoBlock = document.getElementById("video-url-block");
        var fileBlock = document.getElementById("file-upload-block");
        
        if (type === 'text') {
            videoBlock.style.display = "none";
            fileBlock.style.display = "none";
        } else if (type === 'video') {
            videoBlock.style.display = "block";
            fileBlock.style.display = "block"; // Video could be uploaded locally or loaded via URL
        } else if (type === 'pdf') {
            videoBlock.style.display = "none";
            fileBlock.style.display = "block"; // PDF is uploaded
        }
    }
    document.addEventListener("DOMContentLoaded", toggleTypeFields);
    </script>
<?php endif; ?>

<?php
include_once __DIR__ . '/../includes/footer.php';
?>
