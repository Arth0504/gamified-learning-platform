<?php
$page_title = "Manage Students";
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/middleware.php';

require_role('Admin');

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? 'list';
$edit_student = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        set_flash_message('danger', 'CSRF validation failed.');
    } else {
        $student_id = intval($_POST['student_id'] ?? 0);
        $points = intval($_POST['points'] ?? 0);
        $xp = intval($_POST['xp'] ?? 0);
        $streak_count = intval($_POST['streak_count'] ?? 0);
        
        if ($student_id <= 0) {
            set_flash_message('danger', 'Invalid student ID.');
        } else {
            $stmt = $pdo->prepare("UPDATE students SET points = ?, xp = ?, streak_count = ? WHERE id = ?");
            $stmt->execute([$points, $xp, $streak_count, $student_id]);
            
            log_activity($pdo, $user_id, 'Admin Update Student Profile', "Updated student ID: $student_id (Pts: $points, XP: $xp, Streak: $streak_count)");
            set_flash_message('success', 'Student metrics updated successfully!');
            header("Location: students.php");
            exit();
        }
    }
}

// Handle Delete Student Profile (Deletes matching User cascade)
if ($action === 'delete' && isset($_GET['id'])) {
    $student_id = $_GET['id'];
    
    // Find user ID linked to student
    $stmt = $pdo->prepare("SELECT user_id, (SELECT full_name FROM users WHERE id = user_id) AS name FROM students WHERE id = ?");
    $stmt->execute([$student_id]);
    $student_record = $stmt->fetch();
    
    if ($student_record) {
        $stmt_del = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt_del->execute([$student_record['user_id']]);
        
        log_activity($pdo, $user_id, 'Admin Delete Student User', "Deleted student: " . $student_record['name'] . " (ID: $student_id)");
        set_flash_message('success', 'Student record deleted successfully!');
    }
    header("Location: students.php");
    exit();
}

// Load edit form details
if ($action === 'edit' && isset($_GET['id'])) {
    $student_id = $_GET['id'];
    $stmt = $pdo->prepare("
        SELECT s.*, u.full_name, u.username 
        FROM students s 
        JOIN users u ON s.user_id = u.id 
        WHERE s.id = ?
    ");
    $stmt->execute([$student_id]);
    $edit_student = $stmt->fetch();
    if (!$edit_student) {
        set_flash_message('danger', 'Student not found.');
        header("Location: students.php");
        exit();
    }
}

// Fetch all students
$students_stmt = $pdo->query("
    SELECT s.*, u.full_name, u.username, u.email 
    FROM students s 
    JOIN users u ON s.user_id = u.id 
    ORDER BY s.created_at DESC
");
$students = $students_stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="font-heading font-weight-bold text-success">
            <?php echo $action === 'edit' ? 'Adjust Student Metrics' : 'Students Database Directory'; ?>
        </h2>
    </div>
</div>

<?php if ($action === 'list'): ?>
    <div class="row">
        <div class="col-12">
            <div class="glass-card">
                <?php if (empty($students)): ?>
                    <div class="text-center py-5 text-secondary">No students registered in system.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle table-hover">
                            <thead>
                                <tr>
                                    <th>Full Name</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Points Balance</th>
                                    <th>XP Total</th>
                                    <th>Streak</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $s): ?>
                                    <tr>
                                        <td><strong class="text-dark-theme-compatible"><?php echo e($s['full_name']); ?></strong></td>
                                        <td>@<?php echo e($s['username']); ?></td>
                                        <td><?php echo e($s['email']); ?></td>
                                        <td><span class="badge bg-warning text-dark"><?php echo $s['points']; ?> Pts</span></td>
                                        <td><span class="badge bg-info"><?php echo $s['xp']; ?> XP</span></td>
                                        <td><span class="badge bg-light text-danger border"><i class="bi bi-fire"></i> <?php echo $s['streak_count']; ?> days</span></td>
                                        <td>
                                            <a href="students.php?action=edit&id=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Edit Metrics">
                                                <i class="bi bi-pencil"></i> Edit
                                            </a>
                                            <a href="students.php?action=delete&id=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline-danger ms-1" onclick="return confirm('Are you sure you want to delete this student and their account?');" title="Delete">
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

<?php elseif ($action === 'edit' && $edit_student): ?>
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="glass-card">
                <form action="students.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo e(generate_csrf_token()); ?>">
                    <input type="hidden" name="student_id" value="<?php echo $edit_student['id']; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label font-heading text-secondary">Student Name</label>
                        <input type="text" class="form-control" value="<?php echo e($edit_student['full_name']); ?> (@<?php echo e($edit_student['username']); ?>)" readonly disabled>
                    </div>

                    <div class="mb-3">
                        <label for="points" class="form-label font-heading">Points Balance</label>
                        <input type="number" class="form-control" id="points" name="points" value="<?php echo intval($edit_student['points']); ?>" min="0">
                    </div>

                    <div class="mb-3">
                        <label for="xp" class="form-label font-heading">XP Balance</label>
                        <input type="number" class="form-control" id="xp" name="xp" value="<?php echo intval($edit_student['xp']); ?>" min="0">
                    </div>

                    <div class="mb-3">
                        <label for="streak_count" class="form-label font-heading">Streak Count (Days)</label>
                        <input type="number" class="form-control" id="streak_count" name="streak_count" value="<?php echo intval($edit_student['streak_count']); ?>" min="0">
                    </div>

                    <div class="mt-4 border-top pt-3 border-color">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Save Changes</button>
                        <a href="students.php" class="btn btn-outline-secondary ms-2">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
include_once __DIR__ . '/../includes/footer.php';
?>
