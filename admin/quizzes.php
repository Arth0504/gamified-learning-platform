<?php
$page_title = "Global Quiz Administration";
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/middleware.php';

require_role('Admin');

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? 'list';

// Handle Delete Quiz
if ($action === 'delete' && isset($_GET['id'])) {
    $quiz_id = $_GET['id'];
    $stmt = $pdo->prepare("DELETE FROM quizzes WHERE id = ?");
    $stmt->execute([$quiz_id]);
    
    log_activity($pdo, $user_id, 'Admin Delete Quiz', "Admin deleted quiz ID: $quiz_id");
    set_flash_message('success', 'Quiz deleted successfully!');
    header("Location: quizzes.php");
    exit();
}

// Fetch all quizzes
$quizzes_stmt = $pdo->query("
    SELECT q.*, c.title AS course_title, l.title AS lesson_title 
    FROM quizzes q 
    JOIN courses c ON q.course_id = c.id 
    LEFT JOIN lessons l ON q.lesson_id = l.id 
    ORDER BY c.title ASC, q.created_at DESC
");
$quizzes = $quizzes_stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="font-heading font-weight-bold text-success">System Quizzes</h2>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="glass-card">
            <?php if (empty($quizzes)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-question-square text-secondary display-4 mb-3"></i>
                    <p class="text-secondary"><?php echo e(__('common.no_records')); ?></p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle table-hover">
                        <thead>
                            <tr>
                                <th>Quiz Title</th>
                                <th>Course Context</th>
                                <th>Lesson Link</th>
                                <th>Time Limit</th>
                                <th>Passing Score</th>
                                <th>Rewards</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($quizzes as $q): ?>
                                <tr>
                                    <td>
                                        <strong class="text-dark-theme-compatible"><?php echo e($q['title']); ?></strong>
                                    </td>
                                    <td class="text-secondary small"><?php echo e($q['course_title']); ?></td>
                                    <td class="text-secondary small"><?php echo $q['lesson_title'] ? e($q['lesson_title']) : '<span class="text-muted italic">General Course Quiz</span>'; ?></td>
                                    <td><span class="badge bg-secondary"><?php echo $q['time_limit_mins']; ?> mins</span></td>
                                    <td><span class="badge bg-info text-dark"><?php echo $q['passing_score']; ?>%</span></td>
                                    <td>
                                        <span class="badge bg-warning text-dark">+<?php echo $q['points_reward']; ?> Pts</span>
                                        <span class="badge bg-success">+<?php echo $q['xp_reward']; ?> XP</span>
                                    </td>
                                    <td>
                                        <a href="quizzes.php?action=delete&id=<?php echo $q['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this quiz?');">
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
