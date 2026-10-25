<?php
$page_title = "Achievements & Rewards";
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/middleware.php';

require_role('Student');

$user_id = $_SESSION['user_id'];

// Get Student ID & Points
$stmt = $pdo->prepare("SELECT id, points, xp FROM students WHERE user_id = ?");
$stmt->execute([$user_id]);
$student = $stmt->fetch();
$student_id = $student['id'] ?? 0;

if (!$student_id) {
    set_flash_message('danger', 'Student profile not found.');
    header("Location: " . BASE_URL . "login.php");
    exit();
}

// Handle Reward Redemption
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'redeem') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    $reward_id = intval($_POST['reward_id'] ?? 0);
    
    if (!verify_csrf_token($csrf_token)) {
        set_flash_message('danger', 'CSRF validation failed.');
    } else {
        // Fetch reward cost
        $rew_stmt = $pdo->prepare("SELECT * FROM rewards WHERE id = ?");
        $rew_stmt->execute([$reward_id]);
        $reward = $rew_stmt->fetch();
        
        if ($reward) {
            $cost = intval($reward['cost_points']);
            
            if ($student['points'] < $cost) {
                set_flash_message('danger', __('student.insufficient_points'));
            } else {
                // Security / Requirement check for Certificate
                if (($reward['resource_type'] ?? '') === 'certificate') {
                    $comp_stmt = $pdo->prepare("SELECT COUNT(*) FROM course_enrollments WHERE student_id = ? AND status = 'completed'");
                    $comp_stmt->execute([$student_id]);
                    if ($comp_stmt->fetchColumn() == 0) {
                        set_flash_message('danger', 'You must complete at least one course to redeem a Personalized Certificate.');
                        header("Location: achievements.php");
                        exit();
                    }
                }
                
                try {
                    $pdo->beginTransaction();
                    
                    // Deduct Points
                    $new_points = $student['points'] - $cost;
                    $up_student = $pdo->prepare("UPDATE students SET points = ? WHERE id = ?");
                    $up_student->execute([$new_points, $student_id]);
                    
                    // Insert Unlock
                    $ins_unlock = $pdo->prepare("INSERT INTO student_rewards (student_id, reward_id) VALUES (?, ?)");
                    $ins_unlock->execute([$student_id, $reward_id]);
                    
                    // Log activity
                    log_activity($pdo, $user_id, 'Redeem Reward', "Redeemed reward: " . $reward['title'] . " (Cost: $cost Points)");
                    
                    // Notify student
                    $notif = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
                    $notif->execute([
                        $user_id,
                        'Reward Unlocked!',
                        "You successfully redeemed '{$reward['title']}' for {$cost} Points! Check your Unlocked items.",
                        'badge'
                    ]);
                    
                    $pdo->commit();
                    
                    set_flash_message('success', __('student.reward_redeemed'));
                    
                    // Download immediately after redemption if certificate or file is ready
                    $has_file = (!empty($reward['file_path']) && file_exists(__DIR__ . '/../uploads/resources/' . basename($reward['file_path'])));
                    if (($reward['resource_type'] ?? '') === 'certificate' || $has_file) {
                        header("Location: download-resource.php?reward_id=" . $reward_id);
                    } else {
                        header("Location: achievements.php");
                    }
                    exit();
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    set_flash_message('danger', 'Redemption failed: ' . $e->getMessage());
                }
            }
        } else {
            set_flash_message('danger', 'Reward item not found.');
        }
    }
}

// Fetch all system badges, marking which ones are earned by the student
$badges_stmt = $pdo->prepare("
    SELECT b.*, sb.awarded_at 
    FROM badges b 
    LEFT JOIN student_badges sb ON b.id = sb.badge_id AND sb.student_id = ?
    ORDER BY b.id ASC
");
$badges_stmt->execute([$student_id]);
$badges = $badges_stmt->fetchAll();

// Fetch rewards list
$rewards_stmt = $pdo->query("SELECT * FROM rewards ORDER BY cost_points ASC");
$rewards = $rewards_stmt->fetchAll();

// Fetch student unlocked rewards
$unlocked_rewards_stmt = $pdo->prepare("
    SELECT r.*, sr.unlocked_at 
    FROM student_rewards sr 
    JOIN rewards r ON sr.reward_id = r.id 
    WHERE sr.student_id = ? 
    ORDER BY sr.unlocked_at DESC
");
$unlocked_rewards_stmt->execute([$student_id]);
$unlocked_rewards = $unlocked_rewards_stmt->fetchAll();
?>

<div class="space-y-lg">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-md">
        <div>
            <h2 class="font-headline-lg text-headline-lg text-on-surface mb-xs flex items-center gap-sm">
                <span class="material-symbols-outlined text-primary text-3xl" style="font-variation-settings: 'FILL' 1;">military_tech</span>
                Achievements & Store
            </h2>
            <p class="font-body-md text-body-md text-on-surface-variant">Unlock achievements by studying lessons, and redeem your reward points for textbooks and worksheets!</p>
        </div>
        <div>
            <span class="inline-flex items-center gap-sm bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 px-4 py-2.5 rounded-full font-bold text-label-md shadow-sm">
                <span class="material-symbols-outlined text-[20px]" style="font-variation-settings: 'FILL' 1;">stars</span>
                <?php echo $student['points']; ?> Points Available
            </span>
        </div>
    </div>

    <!-- BADGES SECTION -->
    <div class="glass-card rounded-xl p-md">
        <div class="flex items-center gap-sm mb-md pb-xs border-b border-outline-variant/30">
            <span class="material-symbols-outlined text-primary text-2xl">workspace_premium</span>
            <h3 class="font-headline-sm text-headline-sm text-on-surface">My Earned Badges</h3>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-md">
            <?php foreach ($badges as $b): ?>
                <div class="relative group">
                    <div class="glass-card rounded-xl p-sm flex flex-col items-center text-center gap-xs hover:translate-y-[-2px] transition-transform duration-200 <?php echo !$b['awarded_at'] ? 'opacity-50 grayscale hover:grayscale-0 hover:opacity-90' : ''; ?>">
                        <!-- Visual circular symbol -->
                        <div class="mb-xs">
                            <?php if ($b['awarded_at']): ?>
                                <div class="w-16 h-16 rounded-full bg-primary/10 flex items-center justify-center text-primary shadow-inner">
                                    <?php if ($b['id'] == 1): ?>
                                        <span class="material-symbols-outlined text-[36px]" style="font-variation-settings: 'FILL' 1;">speed</span>
                                    <?php elseif ($b['id'] == 2): ?>
                                        <span class="material-symbols-outlined text-[36px]" style="font-variation-settings: 'FILL' 1;">local_fire_department</span>
                                    <?php elseif ($b['id'] == 3): ?>
                                        <span class="material-symbols-outlined text-[36px]" style="font-variation-settings: 'FILL' 1;">emoji_events</span>
                                    <?php elseif ($b['id'] == 4): ?>
                                        <span class="material-symbols-outlined text-[36px]" style="font-variation-settings: 'FILL' 1;">school</span>
                                    <?php else: ?>
                                        <span class="material-symbols-outlined text-[36px]" style="font-variation-settings: 'FILL' 1;">workspace_premium</span>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="w-16 h-16 rounded-full bg-surface-container border-2 border-dashed border-outline-variant/30 flex items-center justify-center text-on-surface-variant/40">
                                    <span class="material-symbols-outlined text-[28px]">lock</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <h4 class="font-headline-sm text-xs font-bold text-on-surface"><?php echo e($b['name']); ?></h4>
                        <p class="text-[10px] text-on-surface-variant leading-tight max-w-[120px]"><?php echo e($b['description']); ?></p>
                        
                        <?php if ($b['awarded_at']): ?>
                            <span class="text-[9px] bg-green-500/10 text-green-500 px-1.5 py-0.5 rounded font-bold uppercase mt-xs">Earned</span>
                            <div class="text-[9px] text-on-surface-variant/70 mt-1 font-semibold">
                                <?php echo date('M d, Y', strtotime($b['awarded_at'])); ?>
                            </div>
                        <?php else: ?>
                            <span class="text-[9px] bg-surface-container text-on-surface-variant/50 px-1.5 py-0.5 rounded font-semibold uppercase mt-xs">Locked</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- REWARDS & PURCHASES GRID -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-gutter">
        <!-- Rewards Store (Left) -->
        <div class="lg:col-span-7">
            <div class="glass-card rounded-xl p-md h-full space-y-md">
                <div class="flex items-center gap-sm mb-md pb-xs border-b border-outline-variant/30">
                    <span class="material-symbols-outlined text-primary text-2xl">store</span>
                    <h3 class="font-headline-sm text-headline-sm text-on-surface">Store: Unlock Resources</h3>
                </div>
                
                <div class="flex flex-col gap-sm">
                    <?php if (empty($rewards)): ?>
                        <div class="text-center py-xl text-on-surface-variant">No reward items configured in store.</div>
                    <?php else: ?>
                        <?php foreach ($rewards as $r): ?>
                            <div class="p-sm border border-outline-variant/20 rounded-xl bg-surface-container-low/50 dark:bg-surface-container-highest/10 flex flex-col sm:flex-row sm:items-center justify-between gap-sm">
                                <div class="flex items-center gap-sm">
                                    <div class="w-12 h-12 rounded-full bg-amber-500/10 text-amber-500 flex items-center justify-center">
                                        <span class="material-symbols-outlined text-2xl">picture_as_pdf</span>
                                    </div>
                                    <div>
                                        <h4 class="font-headline-sm text-headline-sm text-on-surface font-bold"><?php echo e($r['title']); ?></h4>
                                        <p class="font-body-sm text-xs text-on-surface-variant"><?php echo e($r['description']); ?></p>
                                    </div>
                                </div>
                                <div class="flex sm:flex-col items-center sm:items-end justify-between gap-sm shrink-0">
                                    <span class="text-xs font-bold text-amber-600 bg-amber-500/10 px-2 py-0.5 rounded border border-amber-500/20">
                                        <?php echo $r['cost_points']; ?> Points
                                    </span>
                                    <form action="achievements.php" method="POST" class="m-0 w-full sm:w-auto">
                                        <input type="hidden" name="csrf_token" value="<?php echo e(generate_csrf_token()); ?>">
                                        <input type="hidden" name="action" value="redeem">
                                        <input type="hidden" name="reward_id" value="<?php echo $r['id']; ?>">
                                        
                                        <button type="submit" class="w-full bg-primary text-white hover:bg-primary-hover disabled:bg-surface-container-highest disabled:text-on-surface-variant/40 px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm transition-colors" <?php echo $student['points'] < $r['cost_points'] ? 'disabled' : ''; ?>>
                                            Redeem
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Unlocked Rewards (Right) -->
        <div class="lg:col-span-5">
            <div class="glass-card rounded-xl p-md h-full space-y-md">
                <div class="flex items-center gap-sm mb-md pb-xs border-b border-outline-variant/30">
                    <span class="material-symbols-outlined text-primary text-2xl">folder_shared</span>
                    <h3 class="font-headline-sm text-headline-sm text-on-surface">My Unlocked Items</h3>
                </div>
                
                <?php if (empty($unlocked_rewards)): ?>
                    <div class="text-center py-xl space-y-md text-on-surface-variant">
                        <span class="material-symbols-outlined text-secondary text-5xl">folder_open</span>
                        <p class="font-body-md text-body-md">You haven't unlocked any rewards yet.</p>
                    </div>
                <?php else: ?>
                    <div class="flex flex-col gap-sm divide-y divide-outline-variant/20">
                        <?php foreach ($unlocked_rewards as $ur): ?>
                            <?php 
                            // Determine if resource actually exists on the server or is certificate
                            $is_cert = ($ur['resource_type'] ?? '') === 'certificate';
                            $full_path = __DIR__ . '/../uploads/resources/' . basename($ur['file_path']);
                            $file_exists = ($is_cert || (!empty($ur['file_path']) && file_exists($full_path)));
                            
                            $size_text = 'Dynamic PDF';
                            if (!$is_cert && !empty($ur['file_size'])) {
                                $size_text = round($ur['file_size'] / 1024, 1) . ' KB';
                            }
                            ?>
                            <div class="flex justify-between items-center pt-sm first:pt-0">
                                <div>
                                    <h4 class="font-headline-sm text-headline-sm text-on-surface font-semibold"><?php echo e($ur['title']); ?></h4>
                                    <p class="text-[10px] text-on-surface-variant m-0">Unlocked: <?php echo date('M d, Y', strtotime($ur['unlocked_at'])); ?></p>
                                    <div class="flex items-center gap-xs mt-1">
                                        <span class="px-sm py-0.5 rounded-full text-[9px] font-bold uppercase bg-primary/10 text-primary">
                                            <?php echo e($ur['resource_type'] ?? 'PDF'); ?>
                                        </span>
                                        <span class="px-sm py-0.5 rounded-full text-[9px] font-bold uppercase bg-surface-container-high/40 text-on-surface-variant">
                                            <?php echo $size_text; ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="shrink-0">
                                    <?php if ($file_exists): ?>
                                        <a href="download-resource.php?reward_id=<?php echo $ur['id']; ?>" class="inline-flex items-center gap-xs bg-primary text-white hover:bg-primary-hover px-2.5 py-1.5 rounded-lg text-xs font-bold text-decoration-none transition-colors shadow-sm">
                                            <span class="material-symbols-outlined text-[16px]">download</span>
                                            Download
                                        </a>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-xs bg-outline-variant/10 text-on-surface-variant/40 px-2.5 py-1.5 rounded-lg text-xs font-bold select-none cursor-not-allowed" title="Resource file not uploaded yet.">
                                            <span class="material-symbols-outlined text-[16px]">unpublished</span>
                                            Unavailable
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
include_once __DIR__ . '/../includes/footer.php';
?>
