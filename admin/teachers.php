<?php
$page_title = "Manage Teachers";
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/middleware.php';

require_role('Admin');

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? 'list';
$edit_teacher = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        set_flash_message('danger', 'CSRF validation failed.');
    } else {
        $full_name = trim($_POST['full_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $specialization = trim($_POST['specialization'] ?? '');
        $bio = trim($_POST['bio'] ?? '');

        if (empty($full_name) || empty($username) || empty($email)) {
            set_flash_message('danger', 'Full Name, Username, and Email are required.');
        } elseif (isset($_POST['teacher_id'])) {
            // Edit Teacher Specialization / Bio
            $teacher_id = $_POST['teacher_id'];
            
            try {
                $pdo->beginTransaction();
                
                // Update User Full name & Email
                $stmt_usr = $pdo->prepare("UPDATE users u JOIN teachers t ON t.user_id = u.id SET u.full_name = ?, u.email = ? WHERE t.id = ?");
                $stmt_usr->execute([$full_name, $email, $teacher_id]);
                
                // Update Teacher info
                $stmt_t = $pdo->prepare("UPDATE teachers SET specialization = ?, bio = ? WHERE id = ?");
                $stmt_t->execute([$specialization, $bio, $teacher_id]);
                
                $pdo->commit();
                
                log_activity($pdo, $user_id, 'Admin Edit Teacher Profile', "Updated teacher: $full_name (ID: $teacher_id)");
                set_flash_message('success', 'Teacher profile updated successfully!');
                header("Location: teachers.php");
                exit();
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                set_flash_message('danger', 'Update failed: ' . $e->getMessage());
            }
        } else {
            // Add New Teacher Account
            if (empty($password)) {
                set_flash_message('danger', 'Password is required for new accounts.');
            } else {
                try {
                    // Check Username / Email duplicates
                    $chk = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                    $chk->execute([$username, $email]);
                    if ($chk->fetch()) {
                        set_flash_message('danger', 'Username or Email is already registered.');
                    } else {
                        $pdo->beginTransaction();
                        
                        $hashed = password_hash($password, PASSWORD_DEFAULT);
                        $role_teacher = 2; // Teacher role id is 2
                        
                        // Insert User
                        $ins_usr = $pdo->prepare("INSERT INTO users (role_id, username, email, password_hash, full_name, status) VALUES (?, ?, ?, ?, ?, 'active')");
                        $ins_usr->execute([$role_teacher, $username, $email, $hashed, $full_name]);
                        $new_usr_id = $pdo->lastInsertId();
                        
                        // Insert Teacher profile
                        $ins_t = $pdo->prepare("INSERT INTO teachers (user_id, specialization, bio) VALUES (?, ?, ?)");
                        $ins_t->execute([$new_usr_id, $specialization, $bio]);
                        
                        $pdo->commit();
                        
                        log_activity($pdo, $user_id, 'Admin Create Teacher Account', "Created teacher account: $full_name");
                        set_flash_message('success', 'Teacher account created successfully!');
                        header("Location: teachers.php");
                        exit();
                    }
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    set_flash_message('danger', 'Creation failed: ' . $e->getMessage());
                }
            }
        }
    }
}

// Handle Delete Teacher Profile
if ($action === 'delete' && isset($_GET['id'])) {
    $teacher_id = $_GET['id'];
    
    // Find user ID linked to teacher
    $stmt = $pdo->prepare("SELECT user_id, (SELECT full_name FROM users WHERE id = user_id) AS name FROM teachers WHERE id = ?");
    $stmt->execute([$teacher_id]);
    $teacher_record = $stmt->fetch();
    
    if ($teacher_record) {
        $stmt_del = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt_del->execute([$teacher_record['user_id']]);
        
        log_activity($pdo, $user_id, 'Admin Delete Teacher User', "Deleted teacher: " . $teacher_record['name'] . " (ID: $teacher_id)");
        set_flash_message('success', 'Teacher record deleted successfully!');
    }
    header("Location: teachers.php");
    exit();
}

// Load Edit Form details
if ($action === 'edit' && isset($_GET['id'])) {
    $teacher_id = $_GET['id'];
    $stmt = $pdo->prepare("
        SELECT t.*, u.full_name, u.username, u.email 
        FROM teachers t 
        JOIN users u ON t.user_id = u.id 
        WHERE t.id = ?
    ");
    $stmt->execute([$teacher_id]);
    $edit_teacher = $stmt->fetch();
    if (!$edit_teacher) {
        set_flash_message('danger', 'Teacher record not found.');
        header("Location: teachers.php");
        exit();
    }
}

// Fetch all teachers
$teachers_stmt = $pdo->query("
    SELECT t.*, u.full_name, u.username, u.email, 
           (SELECT COUNT(*) FROM courses WHERE teacher_id = t.id) AS courses_count 
    FROM teachers t 
    JOIN users u ON t.user_id = u.id 
    ORDER BY t.created_at DESC
");
$teachers = $teachers_stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h2 class="font-heading font-weight-bold text-success">
            <?php echo $action === 'edit' ? 'Edit Teacher Details' : ($action === 'add' ? 'Create Teacher Account' : 'Teachers Directory'); ?>
        </h2>
    </div>
    <div class="col-md-4 text-md-end">
        <?php if ($action === 'list'): ?>
            <a href="teachers.php?action=add" class="btn btn-primary">
                <i class="bi bi-person-plus-fill me-1"></i> Add Teacher
            </a>
        <?php else: ?>
            <a href="teachers.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to Directory
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($action === 'list'): ?>
    <div class="row">
        <div class="col-12">
            <div class="glass-card">
                <?php if (empty($teachers)): ?>
                    <div class="text-center py-5 text-secondary">No teachers registered in system.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle table-hover">
                            <thead>
                                <tr>
                                    <th>Full Name</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Specialization</th>
                                    <th>Created Courses</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($teachers as $t): ?>
                                    <tr>
                                        <td><strong class="text-dark-theme-compatible"><?php echo e($t['full_name']); ?></strong></td>
                                        <td>@<?php echo e($t['username']); ?></td>
                                        <td><?php echo e($t['email']); ?></td>
                                        <td><?php echo e($t['specialization'] ?: 'General'); ?></td>
                                        <td><span class="badge bg-secondary"><?php echo $t['courses_count']; ?> Courses</span></td>
                                        <td>
                                            <a href="teachers.php?action=edit&id=<?php echo $t['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                                <i class="bi bi-pencil"></i> Edit
                                            </a>
                                            <a href="teachers.php?action=delete&id=<?php echo $t['id']; ?>" class="btn btn-sm btn-outline-danger ms-1" onclick="return confirm('Are you sure you want to delete this teacher account?');">
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

<?php elseif ($action === 'add' || $action === 'edit'): ?>
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="glass-card">
                <form action="teachers.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo e(generate_csrf_token()); ?>">
                    
                    <?php if ($edit_teacher): ?>
                        <input type="hidden" name="teacher_id" value="<?php echo $edit_teacher['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="full_name" class="form-label font-heading">Full Name</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo $edit_teacher ? e($edit_teacher['full_name']) : ''; ?>" placeholder="e.g. Suresh Kumar" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label font-heading">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo $edit_teacher ? e($edit_teacher['email']) : ''; ?>" placeholder="e.g. suresh@rurallearn.org" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="username" class="form-label font-heading">Username</label>
                            <input type="text" class="form-control" id="username" name="username" value="<?php echo $edit_teacher ? e($edit_teacher['username']) : ''; ?>" placeholder="e.g. suresh123" <?php echo $edit_teacher ? 'disabled readonly' : 'required'; ?>>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label font-heading">Password <?php echo $edit_teacher ? '<span class="text-muted small">(Leave blank if unchanged)</span>' : ''; ?></label>
                            <input type="password" class="form-control" id="password" name="password" <?php echo $edit_teacher ? '' : 'required'; ?>>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="specialization" class="form-label font-heading">Specialization Area</label>
                        <input type="text" class="form-control" id="specialization" name="specialization" value="<?php echo $edit_teacher ? e($edit_teacher['specialization']) : ''; ?>" placeholder="e.g. Science and English Literacy">
                    </div>

                    <div class="mb-3">
                        <label for="bio" class="form-label font-heading">Instructor Biography</label>
                        <textarea class="form-control" id="bio" name="bio" rows="4" placeholder="Brief teacher overview..."><?php echo $edit_teacher ? e($edit_teacher['bio']) : ''; ?></textarea>
                    </div>

                    <div class="mt-4 border-top pt-3 border-color">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Save Teacher Profile</button>
                        <a href="teachers.php" class="btn btn-outline-secondary ms-2">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
include_once __DIR__ . '/../includes/footer.php';
?>
