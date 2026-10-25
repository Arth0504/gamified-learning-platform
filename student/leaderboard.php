<?php
$page_title = "Leaderboard";
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/middleware.php';

require_role('Student');

$user_id = $_SESSION['user_id'];

// Get Student ID
$stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
$stmt->execute([$user_id]);
$student_id = $stmt->fetchColumn();

// Fetch rankings list sorted by points & XP
$rank_stmt = $pdo->query("
    SELECT s.id AS student_id, s.points, s.xp, s.streak_count, u.full_name, u.username 
    FROM students s 
    JOIN users u ON s.user_id = u.id 
    ORDER BY s.points DESC, s.xp DESC, s.id ASC
");
$rankings = $rank_stmt->fetchAll();
?>

<div class="space-y-lg">
    <!-- Header -->
    <header class="flex justify-between items-center mb-lg">
        <div>
            <h2 class="font-headline-lg text-headline-lg text-on-surface mb-xs flex items-center gap-sm">
                <span class="material-symbols-outlined text-amber-500 text-3xl" style="font-variation-settings: 'FILL' 1;">military_tech</span>
                Leaderboard Rankings
            </h2>
            <p class="font-body-md text-body-md text-on-surface-variant">Compete with fellow scholars in your region and climb the leaderboard rankings!</p>
        </div>
    </header>

    <!-- Top 3 Podium Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-md items-end pt-md">
        <!-- Rank 2 (Left) -->
        <?php if (isset($rankings[1])): ?>
            <div class="order-2 md:order-1 glass-card rounded-xl p-md flex flex-col items-center text-center shadow-md border-slate-300/30 hover:translate-y-[-4px] transition-transform duration-300 bg-surface-container-low/60">
                <div class="relative mb-sm">
                    <div class="w-20 h-20 rounded-full bg-primary/5 flex items-center justify-center border-4 border-slate-300 shadow-md">
                        <span class="material-symbols-outlined text-slate-400 text-[40px]">person</span>
                    </div>
                    <div class="absolute -bottom-1.5 -right-1.5 w-8 h-8 bg-gradient-to-r from-slate-300 to-slate-400 rounded-full flex items-center justify-center text-white text-xs font-bold border-2 border-white shadow-sm">2</div>
                </div>
                <h3 class="font-headline-sm text-headline-sm text-on-surface font-bold"><?php echo e($rankings[1]['full_name']); ?></h3>
                <span class="text-xs text-on-surface-variant">@<?php echo e($rankings[1]['username']); ?></span>
                <strong class="text-secondary font-bold text-label-md mt-xs"><?php echo $rankings[1]['points']; ?> Points</strong>
            </div>
        <?php endif; ?>

        <!-- Rank 1 (Middle - Taller) -->
        <?php if (isset($rankings[0])): ?>
            <div class="order-1 md:order-2 glass-card rounded-xl p-lg flex flex-col items-center text-center shadow-xl border-amber-400/40 md:scale-105 hover:translate-y-[-4px] transition-transform duration-300 bg-surface-container-lowest/90 relative">
                <div class="absolute -top-5">
                    <span class="material-symbols-outlined text-4xl text-amber-500 fill-icon" style="font-variation-settings: 'FILL' 1;">military_tech</span>
                </div>
                <div class="relative mb-sm">
                    <div class="w-24 h-24 rounded-full bg-primary/5 flex items-center justify-center border-4 border-amber-400 shadow-lg">
                        <span class="material-symbols-outlined text-amber-500 text-[48px]">person</span>
                    </div>
                    <div class="absolute -bottom-1.5 -right-1.5 w-10 h-10 bg-gradient-to-r from-amber-400 to-yellow-500 rounded-full flex items-center justify-center text-white text-sm font-black border-2 border-white shadow-sm">1</div>
                </div>
                <h3 class="font-headline-sm text-headline-sm text-on-surface font-black"><?php echo e($rankings[0]['full_name']); ?></h3>
                <span class="text-xs text-on-surface-variant">@<?php echo e($rankings[0]['username']); ?></span>
                <strong class="text-primary font-black text-headline-sm mt-xs"><?php echo $rankings[0]['points']; ?> Points</strong>
            </div>
        <?php endif; ?>

        <!-- Rank 3 (Right) -->
        <?php if (isset($rankings[2])): ?>
            <div class="order-3 glass-card rounded-xl p-md flex flex-col items-center text-center shadow-md border-amber-600/30 hover:translate-y-[-4px] transition-transform duration-300 bg-surface-container-low/60">
                <div class="relative mb-sm">
                    <div class="w-20 h-20 rounded-full bg-primary/5 flex items-center justify-center border-4 border-amber-600 shadow-md">
                        <span class="material-symbols-outlined text-amber-700 text-[40px]">person</span>
                    </div>
                    <div class="absolute -bottom-1.5 -right-1.5 w-8 h-8 bg-gradient-to-r from-orange-400 to-amber-600 rounded-full flex items-center justify-center text-white text-xs font-bold border-2 border-white shadow-sm">3</div>
                </div>
                <h3 class="font-headline-sm text-headline-sm text-on-surface font-bold"><?php echo e($rankings[2]['full_name']); ?></h3>
                <span class="text-xs text-on-surface-variant">@<?php echo e($rankings[2]['username']); ?></span>
                <strong class="text-secondary font-bold text-label-md mt-xs"><?php echo $rankings[2]['points']; ?> Points</strong>
            </div>
        <?php endif; ?>
    </div>

    <!-- Complete rankings table -->
    <div class="glass-card rounded-xl overflow-hidden p-md">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-body-sm text-on-surface-variant">
                <thead>
                    <tr class="border-b border-outline-variant/30 text-xs font-semibold uppercase text-on-surface-variant/75">
                        <th class="py-3 px-4" style="width: 100px;">Rank</th>
                        <th class="py-3 px-4">Student</th>
                        <th class="py-3 px-4">Username</th>
                        <th class="py-3 px-4">Points</th>
                        <th class="py-3 px-4">XP</th>
                        <th class="py-3 px-4">Daily Streak</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/20">
                    <?php 
                    $rank = 1;
                    foreach ($rankings as $row): 
                        $is_current = ($row['student_id'] == $student_id);
                    ?>
                        <tr class="<?php echo $is_current ? 'bg-primary/5 dark:bg-primary-container/10 border-l-4 border-primary font-bold' : ''; ?> hover:bg-surface-variant/20 transition-colors">
                            <td class="py-3 px-4">
                                <?php if ($rank === 1): ?>
                                    <span class="inline-flex items-center gap-xs bg-amber-400/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 px-2.5 py-0.5 rounded-full text-xs font-bold font-label-sm">
                                        <span class="material-symbols-outlined text-[14px]" style="font-variation-settings: 'FILL' 1;">military_tech</span>
                                        1st
                                    </span>
                                <?php elseif ($rank === 2): ?>
                                    <span class="inline-flex items-center gap-xs bg-slate-300/10 text-slate-500 border border-slate-300/20 px-2.5 py-0.5 rounded-full text-xs font-bold font-label-sm">
                                        2nd
                                    </span>
                                <?php elseif ($rank === 3): ?>
                                    <span class="inline-flex items-center gap-xs bg-orange-400/10 text-orange-600 border border-orange-400/20 px-2.5 py-0.5 rounded-full text-xs font-bold font-label-sm">
                                        3rd
                                    </span>
                                <?php else: ?>
                                    <span class="inline-block bg-surface-container border border-outline-variant/20 text-on-surface-variant w-6 h-6 rounded-full text-center leading-5 text-xs font-semibold"><?php echo $rank; ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-4">
                                <span class="text-on-surface font-semibold"><?php echo e($row['full_name']); ?></span>
                                <?php if ($is_current): ?>
                                    <span class="ml-1 bg-primary text-white text-[10px] font-bold px-1.5 py-0.5 rounded uppercase">You</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-4 text-xs font-mono">@<?php echo e($row['username']); ?></td>
                            <td class="py-3 px-4 font-bold text-amber-600 dark:text-amber-400"><?php echo $row['points']; ?> Pts</td>
                            <td class="py-3 px-4 font-semibold text-primary dark:text-primary-fixed-dim"><?php echo $row['xp']; ?> XP</td>
                            <td class="py-3 px-4">
                                <span class="inline-flex items-center gap-xs bg-error/10 text-error px-2 py-0.5 rounded border border-error/20 text-xs font-semibold">
                                    <span class="material-symbols-outlined text-[14px]" style="font-variation-settings: 'FILL' 1;">local_fire_department</span>
                                    <?php echo $row['streak_count']; ?> days
                                </span>
                            </td>
                        </tr>
                    <?php 
                        $rank++;
                    endforeach; 
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
include_once __DIR__ . '/../includes/footer.php';
?>
