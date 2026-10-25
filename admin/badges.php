<?php
$page_title = "Manage System Badges";
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/middleware.php';

require_role('Admin');

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? 'list';
$edit_badge = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        set_flash_message('danger', 'CSRF validation failed.');
    } else {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $criteria_type = $_POST['criteria_type'] ?? 'points';
        $criteria_value = intval($_POST['criteria_value'] ?? 1);
        
        if (empty($name) || empty($description)) {
            set_flash_message('danger', 'Badge Name and Description are required.');
        } elseif (isset($_POST['badge_id'])) {
            // Edit Badge
            $badge_id = $_POST['badge_id'];
            $stmt = $pdo->prepare("UPDATE badges SET name = ?, description = ?, criteria_type = ?, criteria_value = ? WHERE id = ?");
            $stmt->execute([$name, $description, $criteria_type, $criteria_value, $badge_id]);
            
            log_activity($pdo, $user_id, 'Admin Update Badge', "Updated badge: $name (ID: $badge_id)");
            set_flash_message('success', 'Badge updated successfully!');
            header("Location: badges.php");
            exit();
        } else {
            // Add Badge
            $stmt = $pdo->prepare("INSERT INTO badges (name, description, criteria_type, criteria_value) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $description, $criteria_type, $criteria_value]);
            
            log_activity($pdo, $user_id, 'Admin Create Badge', "Created new badge: $name");
            set_flash_message('success', 'Badge created successfully!');
            header("Location: badges.php");
            exit();
        }
    }
}

// Handle Delete Badge
if ($action === 'delete' && isset($_GET['id'])) {
    $badge_id = $_GET['id'];
    $stmt = $pdo->prepare("DELETE FROM badges WHERE id = ?");
    $stmt->execute([$badge_id]);
    
    log_activity($pdo, $user_id, 'Admin Delete Badge', "Deleted badge ID: $badge_id");
    set_flash_message('success', 'Badge deleted successfully!');
    header("Location: badges.php");
    exit();
}

// Edit Mode Load
if ($action === 'edit' && isset($_GET['id'])) {
    $badge_id = $_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM badges WHERE id = ?");
    $stmt->execute([$badge_id]);
    $edit_badge = $stmt->fetch();
    if (!$edit_badge) {
        set_flash_message('danger', 'Badge not found.');
        header("Location: badges.php");
        exit();
    }
}

// Fetch all Badges
$badges = $pdo->query("SELECT * FROM badges ORDER BY id ASC")->fetchAll();
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h2 class="font-heading font-weight-bold text-success">
            <?php echo $action === 'edit' ? 'Edit Badge' : ($action === 'add' ? 'Create New Badge' : 'Achievements Badges'); ?>
        </h2>
    </div>
    <div class="col-md-4 text-md-end">
        <?php if ($action === 'list'): ?>
            <a href="badges.php?action=add" class="btn btn-primary">
                <i class="bi bi-award-fill me-1"></i> Add Badge
            </a>
        <?php else: ?>
            <a href="badges.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to List
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($action === 'list'): ?>
    <div class="row">
        <div class="col-12">
            <div class="glass-card">
                <?php if (empty($badges)): ?>
                    <div class="text-center py-5 text-secondary">No system badges configured yet.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle table-hover">
                            <thead>
                                <tr>
                                    <th>Badge Name</th>
                                    <th>Description</th>
                                    <th>Criteria Type</th>
                                    <th>Trigger Value</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($badges as $b): ?>
                                    <tr>
                                        <td><strong class="text-dark-theme-compatible"><?php echo e($b['name']); ?></strong></td>
                                        <td class="text-secondary small"><?php echo e($b['description']); ?></td>
                                        <td><span class="badge bg-success"><?php echo e($b['criteria_type']); ?></span></td>
                                        <td><span class="badge bg-secondary"><?php echo $b['criteria_value']; ?></span></td>
                                        <td>
                                            <a href="badges.php?action=edit&id=<?php echo $b['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="badges.php?action=delete&id=<?php echo $b['id']; ?>" class="btn btn-sm btn-outline-danger ms-1" onclick="return confirm('Are you sure you want to delete this badge?');">
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
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="glass-card">
                <form action="badges.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo e(generate_csrf_token()); ?>">
                    
                    <?php if ($edit_badge): ?>
                        <input type="hidden" name="badge_id" value="<?php echo $edit_badge['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="name" class="form-label font-heading">Badge Name</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo $edit_badge ? e($edit_badge['name']) : ''; ?>" placeholder="e.g. Master Solver" required>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label font-heading">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3" placeholder="Explain how the student earns this badge..." required><?php echo $edit_badge ? e($edit_badge['description']) : ''; ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="criteria_type" class="form-label font-heading">Unlock Trigger</label>
                            <select class="form-select" id="criteria_type" name="criteria_type">
                                <option value="points" <?php echo ($edit_badge && $edit_badge['criteria_type'] === 'points') ? 'selected' : ''; ?>>Earning Points</option>
                                <option value="quizzes" <?php echo ($edit_badge && $edit_badge['criteria_type'] === 'quizzes') ? 'selected' : ''; ?>>Quizzes Passed</option>
                                <option value="streak" <?php echo ($edit_badge && $edit_badge['criteria_type'] === 'streak') ? 'selected' : ''; ?>>Streak Days</option>
                                <option value="course_completion" <?php echo ($edit_badge && $edit_badge['criteria_type'] === 'course_completion') ? 'selected' : ''; ?>>Course Completions</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="criteria_value" class="form-label font-heading">Target Goal Value</label>
                            <input type="number" class="form-control" id="criteria_value" name="criteria_value" value="<?php echo $edit_badge ? intval($edit_badge['criteria_value']) : '1'; ?>" min="1" required>
                        </div>
                    </div>

                    <div class="mt-4 border-top pt-3 border-color">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Save Badge</button>
                        <a href="badges.php" class="btn btn-outline-secondary ms-2">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
include_once __DIR__ . '/../includes/footer.php';
?>
