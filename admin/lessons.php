<?php
$page_title = "Global Lesson Administration";
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/middleware.php';

require_role('Admin');

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? 'list';

// Handle Delete Lesson
if ($action === 'delete' && isset($_GET['id'])) {
    $lesson_id = $_GET['id'];
    $stmt = $pdo->prepare("DELETE FROM lessons WHERE id = ?");
    $stmt->execute([$lesson_id]);
    
    log_activity($pdo, $user_id, 'Admin Delete Lesson', "Admin deleted lesson ID: $lesson_id");
    set_flash_message('success', 'Lesson deleted successfully!');
    header("Location: lessons.php");
    exit();
}

// Fetch all lessons
$lessons_stmt = $pdo->query("
    SELECT l.*, s.title AS subject_title, c.title AS course_title 
    FROM lessons l 
    JOIN subjects s ON l.subject_id = s.id 
    JOIN courses c ON s.course_id = c.id 
    ORDER BY c.title ASC, s.sort_order ASC, l.sort_order ASC
");
$lessons = $lessons_stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="font-heading font-weight-bold text-success">System Lessons</h2>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="glass-card">
            <?php if (empty($lessons)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-book text-secondary display-4 mb-3"></i>
                    <p class="text-secondary"><?php echo e(__('common.no_records')); ?></p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle table-hover">
                        <thead>
                            <tr>
                                <th>Lesson Title</th>
                                <th>Subject</th>
                                <th>Course</th>
                                <th>Content Type</th>
                                <th>Duration</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lessons as $l): ?>
                                <tr>
                                    <td>
                                        <strong class="text-dark-theme-compatible"><?php echo e($l['title']); ?></strong>
                                    </td>
                                    <td class="text-secondary small"><?php echo e($l['subject_title']); ?></td>
                                    <td class="text-secondary small"><?php echo e($l['course_title']); ?></td>
                                    <td>
                                        <?php if ($l['content_type'] === 'text'): ?>
                                            <span class="badge bg-success"><i class="bi bi-file-text me-1"></i> Text</span>
                                        <?php elseif ($l['content_type'] === 'video'): ?>
                                            <span class="badge bg-info"><i class="bi bi-play-btn me-1"></i> Video</span>
                                        <?php elseif ($l['content_type'] === 'pdf'): ?>
                                            <span class="badge bg-danger"><i class="bi bi-file-pdf me-1"></i> PDF</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-secondary small"><?php echo $l['duration_mins']; ?> mins</td>
                                    <td>
                                        <a href="lessons.php?action=delete&id=<?php echo $l['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this lesson?');">
                                            <i class="bi bi-trash"></i> Delete
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

<?php
include_once __DIR__ . '/../includes/footer.php';
?>
