<?php
$page_title = "Manage Store Rewards";
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/middleware.php';

require_role('Admin');

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? 'list';
$edit_reward = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        set_flash_message('danger', 'CSRF validation failed.');
    } else {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $cost_points = intval($_POST['cost_points'] ?? 10);
        $resource_type = $_POST['resource_type'] ?? 'pdf';
        
        if (empty($title) || empty($description)) {
            set_flash_message('danger', 'Reward Title and Description are required.');
        } else {
            // Process File Upload if provided
            $file_path = null;
            $file_size = null;
            
            if (isset($_FILES['resource_file']) && $_FILES['resource_file']['error'] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['resource_file']['tmp_name'];
                $file_name = preg_replace('/[^a-zA-Z0-9\._-]/', '_', basename($_FILES['resource_file']['name']));
                $upload_dir = __DIR__ . '/../uploads/resources/';
                
                // Check folder exists
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $destination = $upload_dir . $file_name;
                
                if (move_uploaded_file($file_tmp, $destination)) {
                    $file_path = 'uploads/resources/' . $file_name;
                    $file_size = filesize($destination);
                } else {
                    set_flash_message('danger', 'Failed to move uploaded file.');
                }
            }

            if (isset($_POST['reward_id'])) {
                // Edit Reward
                $reward_id = $_POST['reward_id'];
                
                // Get old file info
                $old_stmt = $pdo->prepare("SELECT file_path, file_size FROM rewards WHERE id = ?");
                $old_stmt->execute([$reward_id]);
                $old_reward = $old_stmt->fetch();
                
                if ($file_path === null) {
                    // Retain old file if none uploaded
                    $file_path = $old_reward['file_path'] ?? null;
                    $file_size = $old_reward['file_size'] ?? null;
                }
                
                $stmt = $pdo->prepare("
                    UPDATE rewards 
                    SET title = ?, description = ?, cost_points = ?, file_path = ?, file_size = ?, resource_type = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$title, $description, $cost_points, $file_path, $file_size, $resource_type, $reward_id]);
                
                log_activity($pdo, $user_id, 'Admin Update Reward', "Updated reward: $title (ID: $reward_id)");
                set_flash_message('success', 'Reward updated successfully!');
                header("Location: rewards.php");
                exit();
            } else {
                // Add Reward
                $stmt = $pdo->prepare("
                    INSERT INTO rewards (title, description, cost_points, file_path, file_size, resource_type) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$title, $description, $cost_points, $file_path, $file_size, $resource_type]);
                
                log_activity($pdo, $user_id, 'Admin Create Reward', "Created new reward item: $title");
                set_flash_message('success', 'Reward item created successfully!');
                header("Location: rewards.php");
                exit();
            }
        }
    }
}

// Handle Delete Reward
if ($action === 'delete' && isset($_GET['id'])) {
    $reward_id = $_GET['id'];
    $stmt = $pdo->prepare("DELETE FROM rewards WHERE id = ?");
    $stmt->execute([$reward_id]);
    
    log_activity($pdo, $user_id, 'Admin Delete Reward', "Deleted reward ID: $reward_id");
    set_flash_message('success', 'Reward deleted successfully!');
    header("Location: rewards.php");
    exit();
}

// Edit Mode Load
if ($action === 'edit' && isset($_GET['id'])) {
    $reward_id = $_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM rewards WHERE id = ?");
    $stmt->execute([$reward_id]);
    $edit_reward = $stmt->fetch();
    if (!$edit_reward) {
        set_flash_message('danger', 'Reward item not found.');
        header("Location: rewards.php");
        exit();
    }
}

// Fetch all rewards
$rewards = $pdo->query("SELECT * FROM rewards ORDER BY cost_points ASC")->fetchAll();
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h2 class="font-heading font-weight-bold text-success">
            <?php echo $action === 'edit' ? 'Edit Store Reward' : ($action === 'add' ? 'Add New Reward Item' : 'Store Reward Items'); ?>
        </h2>
    </div>
    <div class="col-md-4 text-md-end">
        <?php if ($action === 'list'): ?>
            <a href="rewards.php?action=add" class="btn btn-primary">
                <i class="bi bi-gift-fill me-1"></i> Add Reward Item
            </a>
        <?php else: ?>
            <a href="rewards.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to List
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($action === 'list'): ?>
    <div class="row">
        <div class="col-12">
            <div class="glass-card">
                <?php if (empty($rewards)): ?>
                    <div class="text-center py-5 text-secondary">No rewards configured in store yet.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle table-hover">
                            <thead>
                                <tr>
                                    <th>Reward Title</th>
                                    <th>Description</th>
                                    <th>Resource Type</th>
                                    <th>Point Cost</th>
                                    <th>File Size / Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rewards as $r): ?>
                                    <tr>
                                        <td><strong class="text-dark-theme-compatible"><?php echo e($r['title']); ?></strong></td>
                                        <td class="text-secondary small"><?php echo e($r['description']); ?></td>
                                        <td>
                                            <span class="badge bg-info text-dark uppercase"><?php echo e($r['resource_type'] ?? 'pdf'); ?></span>
                                        </td>
                                        <td><span class="badge bg-warning text-dark"><?php echo $r['cost_points']; ?> Points</span></td>
                                        <td>
                                            <?php if (($r['resource_type'] ?? '') === 'certificate'): ?>
                                                <span class="text-muted small">Generated Dynamically</span>
                                            <?php elseif (!empty($r['file_path']) && file_exists(__DIR__ . '/../' . $r['file_path'])): ?>
                                                <span class="text-success small"><i class="bi bi-file-earmark-check-fill me-1"></i><?php echo round($r['file_size']/1024, 1); ?> KB</span>
                                            <?php else: ?>
                                                <span class="text-danger small"><i class="bi bi-file-earmark-x-fill me-1"></i>No File</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="rewards.php?action=edit&id=<?php echo $r['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="rewards.php?action=delete&id=<?php echo $r['id']; ?>" class="btn btn-sm btn-outline-danger ms-1" onclick="return confirm('Are you sure you want to delete this reward?');">
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
                <form action="rewards.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo e(generate_csrf_token()); ?>">
                    
                    <?php if ($edit_reward): ?>
                        <input type="hidden" name="reward_id" value="<?php echo $edit_reward['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="title" class="form-label font-heading">Reward Title</label>
                        <input type="text" class="form-control" id="title" name="title" value="<?php echo $edit_reward ? e($edit_reward['title']) : ''; ?>" placeholder="e.g. Science E-Booklet" required>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label font-heading">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3" placeholder="Explain what the student unlocks..." required><?php echo $edit_reward ? e($edit_reward['description']) : ''; ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="resource_type" class="form-label font-heading">Resource Type</label>
                        <select class="form-select" id="resource_type" name="resource_type" required>
                            <option value="worksheet" <?php echo ($edit_reward && ($edit_reward['resource_type'] ?? '') === 'worksheet') ? 'selected' : ''; ?>>Worksheet</option>
                            <option value="certificate" <?php echo ($edit_reward && ($edit_reward['resource_type'] ?? '') === 'certificate') ? 'selected' : ''; ?>>Certificate</option>
                            <option value="notes" <?php echo ($edit_reward && ($edit_reward['resource_type'] ?? '') === 'notes') ? 'selected' : ''; ?>>Study Notes</option>
                            <option value="pdf" <?php echo ($edit_reward && ($edit_reward['resource_type'] ?? '') === 'pdf') ? 'selected' : ''; ?>>PDF E-Book</option>
                            <option value="other" <?php echo ($edit_reward && ($edit_reward['resource_type'] ?? '') === 'other') ? 'selected' : ''; ?>>Other Resource</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="resource_file" class="form-label font-heading">Upload Resource File (PDF only)</label>
                        <input type="file" class="form-control" id="resource_file" name="resource_file" accept=".pdf">
                        <?php if ($edit_reward && !empty($edit_reward['file_path'])): ?>
                            <div class="form-text text-muted">Current file: <?php echo basename($edit_reward['file_path']); ?> (<?php echo round($edit_reward['file_size']/1024, 1); ?> KB)</div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="cost_points" class="form-label font-heading">Points Cost</label>
                        <input type="number" class="form-control" id="cost_points" name="cost_points" value="<?php echo $edit_reward ? intval($edit_reward['cost_points']) : '50'; ?>" min="1" required>
                    </div>

                    <div class="mt-4 border-top pt-3 border-color">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Save Reward Item</button>
                        <a href="rewards.php" class="btn btn-outline-secondary ms-2">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
include_once __DIR__ . '/../includes/footer.php';
?>
