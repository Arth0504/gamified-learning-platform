<?php
$page_title = "Manage Daily Challenges";
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/middleware.php';

require_role('Admin');

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? 'list';

// Fetch auto generation setting
$set_stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'auto_generate_challenges'");
$set_stmt->execute();
$auto_gen_setting = $set_stmt->fetchColumn();
if ($auto_gen_setting === false) {
    $auto_gen_setting = '1';
}

// Handle toggling auto-generation setting
if ($action === 'toggle_auto') {
    $new_val = ($auto_gen_setting === '1') ? '0' : '1';
    
    // Update or insert setting
    $check = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = 'auto_generate_challenges'");
    $check->execute();
    if ($check->fetchColumn() > 0) {
        $up = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'auto_generate_challenges'");
        $up->execute([$new_val]);
    } else {
        $ins = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('auto_generate_challenges', ?)");
        $ins->execute([$new_val]);
    }
    
    log_activity($pdo, $user_id, 'Admin Toggle Auto Challenge Gen', "Set auto-generation to $new_val");
    set_flash_message('success', 'Auto-generation setting updated successfully!');
    header("Location: challenges.php");
    exit();
}

// Handle deleting challenge
if ($action === 'delete' && isset($_GET['id'])) {
    $cid = intval($_GET['id']);
    
    // Check if challenge exists
    $c_check = $pdo->prepare("SELECT * FROM daily_challenges WHERE id = ?");
    $c_check->execute([$cid]);
    $chal = $c_check->fetch();
    
    if ($chal) {
        $del = $pdo->prepare("DELETE FROM daily_challenges WHERE id = ?");
        $del->execute([$cid]);
        
        log_activity($pdo, $user_id, 'Admin Delete Challenge', "Deleted challenge '{$chal['description']}' for date {$chal['challenge_date']}");
        set_flash_message('success', 'Daily challenge deleted successfully.');
    } else {
        set_flash_message('danger', 'Challenge not found.');
    }
    header("Location: challenges.php");
    exit();
}

// Handle manual challenge creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        set_flash_message('danger', 'CSRF validation failed.');
    } else {
        $description = trim($_POST['description'] ?? '');
        $target_type = trim($_POST['target_type'] ?? '');
        $target_value = intval($_POST['target_value'] ?? 1);
        $points_reward = intval($_POST['points_reward'] ?? 10);
        $xp_reward = intval($_POST['xp_reward'] ?? 20);
        $challenge_date = $_POST['challenge_date'] ?? date('Y-m-d');
        
        if (empty($description) || empty($target_type) || empty($challenge_date)) {
            set_flash_message('danger', 'Description, target type, and challenge date are required.');
        } else {
            // Check if there is already a challenge for this date
            $date_check = $pdo->prepare("SELECT COUNT(*) FROM daily_challenges WHERE challenge_date = ?");
            $date_check->execute([$challenge_date]);
            if ($date_check->fetchColumn() > 0) {
                set_flash_message('danger', 'A daily challenge already exists for date ' . htmlspecialchars($challenge_date) . '. Please select another date or delete the existing one.');
            } else {
                $ins = $pdo->prepare("
                    INSERT INTO daily_challenges (description, target_type, target_value, points_reward, xp_reward, challenge_date)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $ins->execute([$description, $target_type, $target_value, $points_reward, $xp_reward, $challenge_date]);
                
                log_activity($pdo, $user_id, 'Admin Create Challenge', "Manually created daily challenge for date: $challenge_date");
                set_flash_message('success', 'Daily challenge manually created successfully!');
                header("Location: challenges.php");
                exit();
            }
        }
    }
}

// Fetch all challenges with statistics
$challenges_query = "
    SELECT dc.*, 
           (SELECT COUNT(*) FROM student_challenges WHERE challenge_id = dc.id) AS participation_count,
           (SELECT COUNT(*) FROM student_challenges WHERE challenge_id = dc.id AND status = 'completed') AS completion_count
    FROM daily_challenges dc
    ORDER BY dc.challenge_date DESC
";
$challenges = $pdo->query($challenges_query)->fetchAll();

// Challenge Types list for selector
$challenge_types = [
    'lesson_read' => 'Complete Lessons',
    'quiz_attempt' => 'Attempt Quiz',
    'quiz_score_80' => 'Score 80%+ in a Quiz',
    'xp_earn' => 'Earn XP',
    'streak_maintain' => 'Maintain Learning Streak',
    'course_activity' => 'Complete a Course Activity',
    'correct_answers_5' => 'Answer Quiz Questions Correctly',
    'daily_login' => 'Login Daily Challenge'
];
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2 class="font-heading font-weight-bold text-success"><i class="bi bi-calendar2-check-fill me-2"></i>Daily Challenges Manager</h2>
    </div>
    <div class="col-md-6 text-md-end">
        <?php if ($action === 'list'): ?>
            <a href="challenges.php?action=add" class="btn btn-primary">
                <i class="bi bi-plus-circle-fill me-1"></i> Create Challenge Manually
            </a>
            <a href="challenges.php?action=toggle_auto" class="btn <?php echo ($auto_gen_setting === '1') ? 'btn-success' : 'btn-outline-secondary'; ?> ms-2">
                <i class="bi bi-cpu-fill me-1"></i> Auto-Generation: <?php echo ($auto_gen_setting === '1') ? 'ENABLED' : 'DISABLED'; ?>
            </a>
        <?php else: ?>
            <a href="challenges.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to Challenges List
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($action === 'list'): ?>
    <!-- Challenges List Table -->
    <div class="row">
        <div class="col-12">
            <div class="glass-card">
                <h4 class="font-heading text-success border-bottom pb-2 mb-4 border-color">
                    <i class="bi bi-list-stars me-1"></i>System Daily Challenges & Completion Statistics
                </h4>
                
                <?php if (empty($challenges)): ?>
                    <div class="text-center py-5 text-secondary">
                        <i class="bi bi-calendar-x fs-2 mb-2"></i>
                        <p class="small">No daily challenges generated or manually created yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Challenge Date</th>
                                    <th>Description</th>
                                    <th>Target Action & Value</th>
                                    <th>Rewards (Coins / XP)</th>
                                    <th class="text-center">Participation</th>
                                    <th class="text-center">Completions</th>
                                    <th class="text-center">Completion Rate</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($challenges as $c): ?>
                                    <?php 
                                    $rate = 0;
                                    if ($c['participation_count'] > 0) {
                                        $rate = round(($c['completion_count'] / $c['participation_count']) * 100);
                                    }
                                    $is_today = ($c['challenge_date'] === date('Y-m-d'));
                                    ?>
                                    <tr class="<?php echo $is_today ? 'table-warning font-weight-bold' : ''; ?>">
                                        <td>
                                            <?php echo date('M d, Y', strtotime($c['challenge_date'])); ?>
                                            <?php if ($is_today): ?>
                                                <span class="badge bg-success ms-1">Today</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo e($c['description']); ?></td>
                                        <td>
                                            <span class="badge bg-secondary"><?php echo $challenge_types[$c['target_type']] ?? $c['target_type']; ?></span>
                                            <span class="small text-secondary">Target: <?php echo $c['target_value']; ?></span>
                                        </td>
                                        <td>
                                            <span class="text-warning font-weight-bold">+<?php echo $c['points_reward']; ?> Coins</span>, 
                                            <span class="text-success font-weight-bold">+<?php echo $c['xp_reward']; ?> XP</span>
                                        </td>
                                        <td class="text-center font-weight-bold"><?php echo $c['participation_count']; ?> students</td>
                                        <td class="text-center text-success font-weight-bold"><?php echo $c['completion_count']; ?> completed</td>
                                        <td class="text-center">
                                            <div class="d-flex align-items-center justify-content-center gap-2">
                                                <span class="font-weight-bold"><?php echo $rate; ?>%</span>
                                                <div class="progress" style="width: 60px; height: 6px;">
                                                    <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $rate; ?>%;"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-end">
                                            <a href="challenges.php?action=delete&id=<?php echo $c['id']; ?>" class="text-danger hover:underline font-weight-bold text-decoration-none small" onclick="return confirm('Are you sure you want to delete this challenge and all related student progress?')">
                                                <i class="bi bi-trash3-fill"></i> Delete
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

<?php elseif ($action === 'add'): ?>
    <!-- Create Manual Challenge Form -->
    <div class="row justify-content-center">
        <div class="col-lg-8 col-md-10">
            <div class="glass-card">
                <h4 class="font-heading text-success border-bottom pb-2 mb-4 border-color">
                    <i class="bi bi-pencil-square me-1"></i>Create New Daily Challenge
                </h4>
                
                <form action="challenges.php" method="POST" class="space-y-md">
                    <input type="hidden" name="csrf_token" value="<?php echo e(generate_csrf_token()); ?>">
                    
                    <div class="mb-3">
                        <label class="form-label font-weight-semibold text-dark" for="description">Challenge Description / Instruction</label>
                        <input type="text" id="description" name="description" class="form-control" placeholder="e.g. Complete at least 2 Lessons today to level up!" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label font-weight-semibold text-dark" for="target_type">Target Type / Action</label>
                            <select id="target_type" name="target_type" class="form-select" required>
                                <option value="" disabled selected>-- Select Challenge Goal --</option>
                                <?php foreach ($challenge_types as $key => $lbl): ?>
                                    <option value="<?php echo $key; ?>"><?php echo $lbl; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label font-weight-semibold text-dark" for="target_value">Target Goal Value</label>
                            <input type="number" id="target_value" name="target_value" class="form-control" min="1" value="1" required>
                            <span class="small text-secondary block mt-1">E.g., 2 for lessons read, 80 for score threshold, 100 for XP gain.</span>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label font-weight-semibold text-dark" for="points_reward">Coins Reward (Bonus Coins)</label>
                            <input type="number" id="points_reward" name="points_reward" class="form-control" min="0" value="20" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label font-weight-semibold text-dark" for="xp_reward">XP Reward</label>
                            <input type="number" id="xp_reward" name="xp_reward" class="form-control" min="0" value="50" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label font-weight-semibold text-dark" for="challenge_date">Challenge Date</label>
                            <input type="date" id="challenge_date" name="challenge_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    
                    <div class="pt-sm">
                        <button type="submit" class="w-full btn btn-primary py-2.5 font-weight-bold">
                            <i class="bi bi-check-circle me-1"></i> Save Daily Challenge
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
include_once __DIR__ . '/../includes/footer.php';
?>
