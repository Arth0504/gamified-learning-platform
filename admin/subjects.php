<?php
$page_title = "Global Subject Administration";
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/middleware.php';

require_role('Admin');

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? 'list';

// Handle Delete Subject
if ($action === 'delete' && isset($_GET['id'])) {
    $subject_id = $_GET['id'];
    $stmt = $pdo->prepare("DELETE FROM subjects WHERE id = ?");
    $stmt->execute([$subject_id]);
    
    log_activity($pdo, $user_id, 'Admin Delete Subject', "Admin deleted subject ID: $subject_id");
    set_flash_message('success', 'Subject deleted successfully!');
    header("Location: subjects.php");
    exit();
}

// Fetch all subjects
$subjects_stmt = $pdo->query("
    SELECT s.*, c.title AS course_title, u.full_name AS teacher_name 
    FROM subjects s 
    JOIN courses c ON s.course_id = c.id 
    JOIN teachers t ON c.teacher_id = t.id
    JOIN users u ON t.user_id = u.id 
    ORDER BY c.title ASC, s.sort_order ASC
");
$subjects = $subjects_stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="font-heading font-weight-bold text-success">System Subjects</h2>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="glass-card">
            <?php if (empty($subjects)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-tags text-secondary display-4 mb-3"></i>
                    <p class="text-secondary"><?php echo e(__('common.no_records')); ?></p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle table-hover">
                        <thead>
                            <tr>
                                <th>Subject Title</th>
                                <th>Course</th>
                                <th>Teacher</th>
                                <th>Sort Order</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subjects as $s): ?>
                                <tr>
                                    <td>
                                        <strong class="text-dark-theme-compatible"><?php echo e($s['title']); ?></strong>
                                    </td>
                                    <td class="text-secondary small"><?php echo e($s['course_title']); ?></td>
                                    <td class="text-secondary small"><?php echo e($s['teacher_name']); ?></td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo $s['sort_order']; ?></span>
                                    </td>
                                    <td>
                                        <a href="subjects.php?action=delete&id=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this subject?');">
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
