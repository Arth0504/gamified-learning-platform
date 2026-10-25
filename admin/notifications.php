<?php
$page_title = "Global Announcements";
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/middleware.php';

require_role('Admin');

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        set_flash_message('danger', 'CSRF validation failed.');
    } else {
        $title = trim($_POST['title'] ?? '');
        $message = trim($_POST['message'] ?? '');
        
        if (empty($title) || empty($message)) {
            set_flash_message('danger', 'Both Title and Message are required.');
        } else {
            try {
                $pdo->beginTransaction();
                
                // Get all users in the system
                $users_stmt = $pdo->query("SELECT id FROM users");
                $users_list = $users_stmt->fetchAll(PDO::FETCH_COLUMN);
                
                // Insert announcement notification for all users
                $ins_stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'announcement')");
                
                foreach ($users_list as $uid) {
                    $ins_stmt->execute([$uid, $title, $message]);
                }
                
                $pdo->commit();
                
                log_activity($pdo, $user_id, 'Send Global Announcement', "Sent announcement: '$title' to all users.");
                set_flash_message('success', 'Announcement broadcasted to all users successfully!');
                header("Location: notifications.php");
                exit();
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                set_flash_message('danger', 'Broadcast failed: ' . $e->getMessage());
            }
        }
    }
}

// Fetch list of recent announcements sent (unique by title/message for clean display)
$announcements_stmt = $pdo->query("
    SELECT title, message, created_at, COUNT(*) as sent_count 
    FROM notifications 
    WHERE type = 'announcement' 
    GROUP BY title, message, created_at 
    ORDER BY created_at DESC 
    LIMIT 6
");
$announcements = $announcements_stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="font-heading font-weight-bold text-success"><i class="bi bi-megaphone-fill me-2 text-success"></i>Global Announcements Control</h2>
        <p class="text-secondary">Broadcast announcements and important notifications to all system students and teachers.</p>
    </div>
</div>

<div class="row g-4">
    <!-- Broadcast Form -->
    <div class="col-lg-6">
        <div class="glass-card">
            <h4 class="font-heading text-success border-bottom pb-2 mb-3 border-color">New Broadcast Announcement</h4>
            <form action="notifications.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo e(generate_csrf_token()); ?>">
                
                <div class="mb-3">
                    <label for="title" class="form-label font-heading">Announcement Title</label>
                    <input type="text" class="form-control" id="title" name="title" placeholder="e.g. Server Maintenance or Exam Schedule" required>
                </div>
                
                <div class="mb-3">
                    <label for="message" class="form-label font-heading">Message Content</label>
                    <textarea class="form-control" id="message" name="message" rows="5" placeholder="Type the notification details here..." required></textarea>
                </div>
                
                <div class="mt-4 border-top pt-3 border-color">
                    <button type="submit" class="btn btn-primary w-100 py-2.5 font-weight-bold">
                        <i class="bi bi-broadcast me-1"></i> Broadcast Announcement
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Sent Announcements Log -->
    <div class="col-lg-6">
        <div class="glass-card">
            <h4 class="font-heading text-success border-bottom pb-2 mb-3 border-color">Broadcast History</h4>
            <?php if (empty($announcements)): ?>
                <div class="text-center py-5 text-secondary small">No announcements broadcasted yet.</div>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($announcements as $ann): ?>
                        <div class="list-group-item bg-transparent border-color px-0 py-3">
                            <div class="d-flex w-100 justify-content-between">
                                <strong class="text-dark-theme-compatible"><?php echo e($ann['title']); ?></strong>
                                <span class="small text-secondary"><?php echo date('M d, Y', strtotime($ann['created_at'])); ?></span>
                            </div>
                            <p class="text-secondary small mt-1 mb-2"><?php echo e($ann['message']); ?></p>
                            <span class="badge bg-light text-secondary border small">Broadcasted to <?php echo $ann['sent_count']; ?> users</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php
include_once __DIR__ . '/../includes/footer.php';
?>
