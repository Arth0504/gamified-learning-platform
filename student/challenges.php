<?php
$page_title = "Daily Challenges";
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/middleware.php';

require_role('Student');

$user_id = $_SESSION['user_id'];

// Get Student ID
$stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
$stmt->execute([$user_id]);
$student_id = $stmt->fetchColumn();

// Fetch Today's Challenge
$today = date('Y-m-d');
$challenge_stmt = $pdo->prepare("SELECT * FROM daily_challenges WHERE challenge_date = ?");
$challenge_stmt->execute([$today]);
$challenge = $challenge_stmt->fetch();

$student_challenge = null;
$progress_percent = 0;

if ($challenge) {
    // Get student progress for this challenge
    $sc_stmt = $pdo->prepare("SELECT * FROM student_challenges WHERE student_id = ? AND challenge_id = ?");
    $sc_stmt->execute([$student_id, $challenge['id']]);
    $student_challenge = $sc_stmt->fetch();
    
    if ($student_challenge) {
        $val = intval($student_challenge['progress_value']);
        $target = intval($challenge['target_value']);
        $progress_percent = min(round(($val / $target) * 100), 100);
    }
}

// Fetch historical challenge attempts (include expired ones using LEFT JOIN)
$history_stmt = $pdo->prepare("
    SELECT dc.*, sc.status, sc.progress_value, sc.completed_at 
    FROM daily_challenges dc
    LEFT JOIN student_challenges sc ON dc.id = sc.challenge_id AND sc.student_id = ?
    WHERE dc.challenge_date != ? 
    ORDER BY dc.challenge_date DESC
");
$history_stmt->execute([$student_id, $today]);
$history = $history_stmt->fetchAll();

// Calculate time remaining until midnight
$midnight = strtotime('tomorrow midnight');
$diff = max(0, $midnight - time());
$hours_left = floor($diff / 3600);
$mins_left = floor(($diff % 3600) / 60);
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="font-heading font-weight-bold text-success"><i class="bi bi-calendar2-check-fill text-success me-2"></i>Daily Challenges</h2>
        <p class="text-secondary">Complete challenges every day to earn bonus points and boost your learning streak!</p>
    </div>
</div>

<div class="row g-4">
    <!-- Today's Challenge Box -->
    <div class="col-lg-6">
        <div class="glass-card">
            <h4 class="font-heading text-success border-bottom pb-2 mb-4 border-color">
                <i class="bi bi-clock-fill me-2 text-warning"></i>Today's Challenge
            </h4>
            
            <?php if (!$challenge): ?>
                <div class="text-center py-5 text-secondary">
                    <i class="bi bi-calendar-x fs-2 mb-2"></i>
                    <p class="small">No active challenge for today. Check back tomorrow!</p>
                </div>
            <?php else: ?>
                <div class="p-4 border rounded border-color text-center mb-4 bg-surface-inner">
                    <h5 class="font-heading text-dark-theme-compatible mb-3"><?php echo e($challenge['description']); ?></h5>
                    
                    <div class="mb-4">
                        <span class="badge bg-warning text-dark px-3 py-2 rounded font-weight-bold fs-6">
                            +<?php echo $challenge['points_reward']; ?> Coins
                        </span>
                        <span class="badge bg-success px-3 py-2 rounded font-weight-bold fs-6 ms-2">
                            +<?php echo $challenge['xp_reward']; ?> XP
                        </span>
                    </div>

                    <!-- Progress Indicator -->
                    <div class="mb-2 text-start small d-flex justify-content-between text-secondary">
                        <span>Your Progress</span>
                        <span><?php 
                            $val = $student_challenge ? $student_challenge['progress_value'] : 0;
                            echo $val . ' / ' . $challenge['target_value']; 
                        ?> Completed (<?php echo $progress_percent; ?>%)</span>
                    </div>
                    <div class="progress mb-3" style="height: 12px;">
                        <div class="progress-bar bg-success progress-bar-striped progress-bar-animated" role="progressbar" style="width: <?php echo $progress_percent; ?>%;" aria-valuenow="<?php echo $progress_percent; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    
                    <!-- Time Remaining -->
                    <div class="text-secondary small mb-4 flex items-center justify-center gap-1">
                        <span class="material-symbols-outlined text-[16px]">schedule</span>
                        <span><?php echo "$hours_left hours $mins_left minutes remaining today"; ?></span>
                    </div>
                    
                    <?php if ($student_challenge && $student_challenge['status'] === 'completed'): ?>
                        <div class="alert alert-success text-center py-2.5 mb-0 font-weight-bold fs-6">
                            <i class="bi bi-check-circle-fill me-1"></i> Challenge Completed! Rewards Claimed.
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info text-center py-2.5 mb-0 small border-info">
                            <i class="bi bi-info-circle me-1"></i> Progress updates automatically as you read lessons or attempt quizzes.
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Challenges History List -->
    <div class="col-lg-6">
        <div class="glass-card">
            <h4 class="font-heading text-success border-bottom pb-2 mb-4 border-color">
                <i class="bi bi-calendar-week-fill me-2"></i>Previous Challenges History
            </h4>
            
            <?php if (empty($history)): ?>
                <div class="text-center py-5 text-secondary">
                    <i class="bi bi-card-list fs-2 mb-2"></i>
                    <p class="small">You haven't participated in any previous daily challenges.</p>
                </div>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($history as $h): ?>
                        <div class="list-group-item bg-transparent border-color px-0 py-3 d-flex justify-content-between align-items-center">
                            <div>
                                <strong class="text-dark-theme-compatible d-block"><?php echo e($h['description']); ?></strong>
                                <span class="small text-secondary"><i class="bi bi-calendar-event me-1"></i> Date: <?php echo date('M d, Y', strtotime($h['challenge_date'])); ?></span>
                            </div>
                            <div class="text-end">
                                <span class="badge <?php echo $h['status'] === 'completed' ? 'bg-success' : 'bg-danger'; ?>">
                                    <?php echo $h['status'] === 'completed' ? 'Completed' : 'Failed / Expired'; ?>
                                </span>
                                <div class="text-secondary small mt-1">+<?php echo $h['points_reward']; ?> Coins, +<?php echo $h['xp_reward']; ?> XP</div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* CSS class backgrounds styles for widgets panels compatibility */
.bg-surface-inner {
    background-color: var(--bg-base);
}
</style>
<?php
include_once __DIR__ . '/../includes/footer.php';
?>
