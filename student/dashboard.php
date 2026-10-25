<?php
$page_title = "Student Dashboard";
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/middleware.php';

require_role('Student');

$user_id = $_SESSION['user_id'];

// Get Student Profile joined with user details
$stmt = $pdo->prepare("
    SELECT s.*, u.profile_pic, u.full_name, u.username 
    FROM students s 
    JOIN users u ON s.user_id = u.id 
    WHERE s.user_id = ?
");
$stmt->execute([$user_id]);
$student = $stmt->fetch();
$student_id = $student['id'] ?? 0;

if (!$student_id) {
    set_flash_message('danger', 'Student profile not found.');
    header("Location: " . BASE_URL . "login.php");
    exit();
}

// Fetch Achievements count
$badges_query = $pdo->prepare("SELECT COUNT(*) FROM student_badges WHERE student_id = ?");
$badges_query->execute([$student_id]);
$achievements_count = $badges_query->fetchColumn();

// Calculate real-time Rank Position
$rank_stmt = $pdo->query("SELECT id FROM students ORDER BY points DESC, xp DESC, id ASC");
$rank_list = $rank_stmt->fetchAll(PDO::FETCH_COLUMN);
$current_rank = array_search($student_id, $rank_list) + 1;

// Fetch enrolled courses with progress
$courses_stmt = $pdo->prepare("
    SELECT c.*, e.progress_percent, e.status AS enroll_status 
    FROM course_enrollments e 
    JOIN courses c ON e.course_id = c.id 
    WHERE e.student_id = ? 
    LIMIT 3
");
$courses_stmt->execute([$student_id]);
$enrolled_courses = $courses_stmt->fetchAll();

// Fetch student profile adaptive recommendations
require_once __DIR__ . '/../includes/recommendation_helper.php';
$recommendations = get_profile_recommendations($student_id, $pdo);

// Fetch today's challenge progress
$today = date('Y-m-d');
$dc_stmt = $pdo->prepare("
    SELECT sc.*, dc.description, dc.target_value, dc.points_reward, dc.xp_reward 
    FROM student_challenges sc 
    JOIN daily_challenges dc ON sc.challenge_id = dc.id 
    WHERE sc.student_id = ? AND dc.challenge_date = ?
");
$dc_stmt->execute([$student_id, $today]);
$today_challenge = $dc_stmt->fetch();

// Fetch latest 4 notifications
$notif_stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 4");
$notif_stmt->execute([$user_id]);
$notifications = $notif_stmt->fetchAll();

// Fetch quiz attempts history for the chart
$quiz_history_stmt = $pdo->prepare("
    SELECT qa.score, qa.attempted_at, q.title AS quiz_title 
    FROM quiz_attempts qa 
    JOIN quizzes q ON qa.quiz_id = q.id 
    WHERE qa.student_id = ? 
    ORDER BY qa.attempted_at ASC 
    LIMIT 6
");
$quiz_history_stmt->execute([$student_id]);
$quiz_history = $quiz_history_stmt->fetchAll();

// Handle marking notification as read
if (isset($_GET['read_notif'])) {
    $notif_id = intval($_GET['read_notif']);
    $read_stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $read_stmt->execute([$notif_id, $user_id]);
    header("Location: dashboard.php");
    exit();
}
?>

<div class="space-y-lg">
    <!-- Header -->
    <header class="flex justify-between items-end mb-lg">
        <div>
            <h2 class="font-headline-lg text-headline-lg text-on-surface mb-xs">
                <?php echo e(__('student.welcome', ['name' => $_SESSION['full_name']])); ?>
            </h2>
            <p class="font-body-md text-body-md text-on-surface-variant">Welcome back! Review your performance metrics and continue your quests.</p>
        </div>
    </header>

    <!-- STATS COUNTERS -->
    <section class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-md">
        <!-- XP Card -->
        <div class="glass-card rounded-xl p-md flex items-center gap-md hover:translate-y-[-2px] transition-transform">
            <div class="w-14 h-14 rounded-full bg-primary/10 flex items-center justify-center text-primary">
                <span class="material-symbols-outlined text-3xl" style="font-variation-settings: 'FILL' 1;">stars</span>
            </div>
            <div>
                <p class="font-label-sm text-label-sm text-on-surface-variant"><?php echo e(__('student.points_balance')); ?></p>
                <h3 class="font-headline-sm text-headline-sm text-on-surface"><?php echo $student['points']; ?></h3>
                <span class="text-xs font-label-sm text-primary">+120 to Level 15</span>
            </div>
        </div>
        <!-- Level Card -->
        <div class="bg-primary rounded-xl p-md flex items-center gap-md text-on-primary shadow-lg hover:shadow-xl transition-shadow">
            <div class="w-14 h-14 rounded-full bg-white/20 flex items-center justify-center">
                <span class="material-symbols-outlined text-3xl">school</span>
            </div>
            <div>
                <p class="font-label-sm text-label-sm opacity-80">Experience (XP)</p>
                <h3 class="font-headline-sm text-headline-sm"><?php echo $student['xp']; ?> XP</h3>
                <div class="w-24 h-1.5 bg-white/20 rounded-full mt-1 overflow-hidden">
                    <div class="bg-white h-full" style="width: 85%"></div>
                </div>
            </div>
        </div>
        <!-- Daily Streak Card -->
        <div class="glass-card rounded-xl p-md flex items-center gap-md hover:translate-y-[-2px] transition-transform">
            <div class="w-14 h-14 rounded-full bg-error-container/30 flex items-center justify-center text-error">
                <span class="material-symbols-outlined text-3xl" style="font-variation-settings: 'FILL' 1;">local_fire_department</span>
            </div>
            <div>
                <p class="font-label-sm text-label-sm text-on-surface-variant"><?php echo e(__('student.streak_status')); ?></p>
                <h3 class="font-headline-sm text-headline-sm text-on-surface"><?php echo $student['streak_count']; ?> Days</h3>
                <span class="text-xs font-label-sm text-error">Keep it up!</span>
            </div>
        </div>
        <!-- Current Rank Card -->
        <div class="glass-card rounded-xl p-md flex items-center gap-md hover:translate-y-[-2px] transition-transform">
            <div class="w-14 h-14 rounded-full bg-secondary-container/10 flex items-center justify-center text-secondary">
                <span class="material-symbols-outlined text-3xl" style="font-variation-settings: 'FILL' 1;">military_tech</span>
            </div>
            <div>
                <p class="font-label-sm text-label-sm text-on-surface-variant"><?php echo e(__('student.current_rank')); ?></p>
                <h3 class="font-headline-sm text-headline-sm text-on-surface">#<?php echo $current_rank; ?></h3>
                <span class="text-xs font-label-sm text-secondary">Top Ranks</span>
            </div>
        </div>
    </section>

    <!-- CONTENT COLUMNS -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-gutter">
        <!-- Active Courses & Chart (Left Side) -->
        <div class="lg:col-span-7 space-y-lg">
            <!-- Active Courses -->
            <div class="glass-card rounded-xl p-md">
                <div class="flex items-center gap-sm mb-md pb-xs border-b border-outline-variant/30">
                    <span class="material-symbols-outlined text-primary text-2xl">menu_book</span>
                    <h3 class="font-headline-sm text-headline-sm text-on-surface">My Active Courses</h3>
                </div>
                
                <?php if (empty($enrolled_courses)): ?>
                    <div class="text-center py-xl space-y-md">
                        <span class="material-symbols-outlined text-secondary text-5xl">auto_stories</span>
                        <p class="font-body-md text-body-md text-on-surface-variant">You are not enrolled in any courses yet.</p>
                        <a href="courses.php" class="inline-block bg-primary text-white hover:bg-primary-hover px-md py-2 rounded-lg font-label-md text-label-md text-decoration-none">Browse Catalog</a>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 gap-md">
                        <?php foreach ($enrolled_courses as $ec): ?>
                            <div class="p-md border border-outline-variant/30 rounded-xl bg-surface-container-low/50 dark:bg-surface-container-highest/20 flex flex-col gap-sm">
                                <div class="flex justify-between items-start">
                                    <h4 class="font-headline-sm text-headline-sm text-on-surface"><?php echo e($ec['title']); ?></h4>
                                    <span class="px-sm py-1 rounded-full text-label-sm font-label-sm <?php echo $ec['enroll_status'] === 'completed' ? 'bg-green-500/10 text-green-500 border border-green-500/20' : 'bg-secondary/10 text-secondary border border-secondary/20'; ?>">
                                        <?php echo ucfirst($ec['enroll_status']); ?>
                                    </span>
                                </div>
                                
                                <div class="space-y-1">
                                    <div class="flex justify-between font-label-sm text-label-sm text-on-surface-variant">
                                        <span>Completion Progress</span>
                                        <span><?php echo $ec['progress_percent']; ?>%</span>
                                    </div>
                                    <div class="w-full h-2 bg-surface-container rounded-full overflow-hidden">
                                        <div class="bg-primary h-full" style="width: <?php echo $ec['progress_percent']; ?>%"></div>
                                    </div>
                                </div>
                                
                                <div class="pt-xs">
                                    <a href="course-view.php?course_id=<?php echo $ec['id']; ?>" class="inline-flex items-center gap-xs bg-primary text-white hover:bg-primary-hover px-md py-sm rounded-lg font-label-md text-label-md text-decoration-none transition-colors">
                                        <?php echo e(__('student.continue_learning')); ?>
                                        <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Quiz Performance Chart -->
            <div class="glass-card rounded-xl p-md">
                <div class="flex items-center gap-sm mb-md pb-xs border-b border-outline-variant/30">
                    <span class="material-symbols-outlined text-primary text-2xl">analytics</span>
                    <h3 class="font-headline-sm text-headline-sm text-on-surface">Quiz Performance Tracking</h3>
                </div>
                <?php if (empty($quiz_history)): ?>
                    <div class="text-center py-lg text-on-surface-variant">
                        <p class="font-body-sm text-body-sm">No quiz history to display. Complete a quiz to see analytics!</p>
                    </div>
                <?php else: ?>
                    <div class="relative w-full h-64">
                        <canvas id="quizHistoryChart" class="w-full h-full"></canvas>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Challenges & Notifications (Right Side) -->
        <div class="lg:col-span-5 space-y-lg">
            <!-- Profile Dashboard Card -->
            <div class="glass-card rounded-xl p-md flex flex-col items-center text-center gap-sm">
                <div class="relative group w-24 h-24">
                    <?php if ($student['profile_pic'] && file_exists(__DIR__ . '/../' . $student['profile_pic'])): ?>
                        <img src="<?php echo e(BASE_URL . $student['profile_pic']); ?>" alt="Profile picture" class="w-full h-full object-cover rounded-full border-4 border-primary/20 shadow-md">
                    <?php else: ?>
                        <div class="w-full h-full rounded-full bg-primary/10 text-primary border-4 border-primary/20 flex items-center justify-center font-bold text-headline-lg shadow-md uppercase">
                            <?php echo strtoupper(substr($student['username'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="space-y-xs">
                    <h3 class="font-headline-sm text-headline-sm text-on-surface font-bold"><?php echo e($student['full_name']); ?></h3>
                    <p class="text-xs text-on-surface-variant font-semibold bg-surface-container px-2.5 py-1 rounded-full inline-block">
                        <?php echo e($student['stream'] ? $student['stream'] . ' Department' : 'No Academic Stream Listed'); ?>
                    </p>
                </div>

                <div class="w-full grid grid-cols-3 gap-xs mt-xs border-t border-outline-variant/20 pt-sm">
                    <div class="text-center space-y-0.5 border-r border-outline-variant/20">
                        <span class="text-[10px] text-on-surface-variant block uppercase font-semibold">Experience</span>
                        <strong class="text-sm font-bold text-primary"><?php echo $student['xp']; ?> XP</strong>
                    </div>
                    <div class="text-center space-y-0.5 border-r border-outline-variant/20">
                        <span class="text-[10px] text-on-surface-variant block uppercase font-semibold">Rank</span>
                        <strong class="text-sm font-bold text-amber-500">#<?php echo $current_rank; ?></strong>
                    </div>
                    <div class="text-center space-y-0.5">
                        <span class="text-[10px] text-on-surface-variant block uppercase font-semibold">Badges</span>
                        <strong class="text-sm font-bold text-green-500"><?php echo $achievements_count; ?></strong>
                    </div>
                </div>

                <div class="w-full mt-xs">
                    <a href="<?php echo BASE_URL; ?>profile.php" class="w-full inline-flex items-center justify-center gap-xs bg-primary/10 text-primary hover:bg-primary/20 py-2 rounded-lg font-label-md text-label-md text-decoration-none transition-colors">
                        <span class="material-symbols-outlined text-[18px]">edit_note</span>
                        Edit Profile Details
                    </a>
                </div>
            </div>

            <!-- Daily Challenge Widget -->
            <div class="p-md rounded-xl bg-gradient-to-br from-tertiary-fixed to-tertiary-fixed-dim/40 border border-tertiary/20 text-on-tertiary-fixed">
                <div class="flex items-center gap-xs mb-sm pb-xs border-b border-tertiary/10">
                    <span class="material-symbols-outlined text-tertiary text-2xl" style="font-variation-settings: 'FILL' 1;">local_fire_department</span>
                    <h3 class="font-headline-sm text-headline-sm text-on-tertiary-fixed font-bold">Today's Quest Challenge</h3>
                </div>
                <?php if (!$today_challenge): ?>
                    <p class="font-body-sm text-body-sm text-on-tertiary-fixed-variant mb-0">Visit the <a href="challenges.php" class="text-primary underline text-decoration-none">Daily Challenges</a> board to claim today's challenge!</p>
                <?php else: ?>
                    <h4 class="font-headline-sm text-headline-sm text-on-tertiary-fixed font-semibold mb-sm"><?php echo e($today_challenge['description']); ?></h4>
                    
                    <?php 
                    $pct = min(100, round(($today_challenge['progress_value'] / $today_challenge['target_value']) * 100));
                    $midnight = strtotime('tomorrow midnight');
                    $diff = max(0, $midnight - time());
                    $hours_left = floor($diff / 3600);
                    $mins_left = floor(($diff % 3600) / 60);
                    ?>
                    
                    <div class="space-y-xs">
                        <div class="flex justify-between font-label-sm text-label-sm text-on-tertiary-fixed-variant">
                            <span>Quest Progress: <?php echo $today_challenge['progress_value']; ?> / <?php echo $today_challenge['target_value']; ?> (<?php echo $pct; ?>%)</span>
                            <span class="font-bold text-tertiary">+<?php echo $today_challenge['points_reward']; ?> Coins, +<?php echo $today_challenge['xp_reward']; ?> XP</span>
                        </div>
                        <div class="w-full h-2 bg-white/35 rounded-full overflow-hidden">
                            <div class="bg-tertiary h-full" style="width: <?php echo $pct; ?>%"></div>
                        </div>
                        <div class="flex justify-between items-center text-[11px] text-on-tertiary-fixed-variant/80 pt-1">
                            <span class="flex items-center gap-0.5"><span class="material-symbols-outlined text-[12px]">schedule</span> <?php echo "$hours_left hrs $mins_left mins remaining"; ?></span>
                            <?php if ($today_challenge['status'] === 'completed'): ?>
                                <span class="text-success font-bold flex items-center gap-0.5"><span class="material-symbols-outlined text-[12px]">check_circle</span> Completed!</span>
                            <?php else: ?>
                                <span class="text-warning font-semibold">Active</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <a href="challenges.php" class="inline-block w-full text-center py-2 bg-white text-tertiary hover:bg-surface-container-lowest transition-colors rounded-lg font-label-md text-label-md text-decoration-none mt-md shadow-sm">View Quest Progress</a>
                <?php endif; ?>
            </div>

            <!-- Recent Notifications -->
            <div class="glass-card rounded-xl p-md">
                <div class="flex items-center gap-sm mb-md pb-xs border-b border-outline-variant/30">
                    <span class="material-symbols-outlined text-primary text-2xl">notifications</span>
                    <h3 class="font-headline-sm text-headline-sm text-on-surface">Inbox Notifications</h3>
                </div>
                
                <?php if (empty($notifications)): ?>
                    <div class="text-center py-lg text-on-surface-variant font-body-sm text-body-sm">
                        No recent notifications.
                    </div>
                <?php else: ?>
                    <div class="flex flex-col gap-sm divide-y divide-outline-variant/20">
                        <?php foreach ($notifications as $n): ?>
                            <div class="flex justify-between items-start pt-sm first:pt-0 <?php echo !$n['is_read'] ? 'font-semibold text-on-surface' : 'text-on-surface-variant'; ?>">
                                <div class="max-w-[75%] space-y-1">
                                    <p class="font-body-sm text-body-sm leading-snug"><?php echo e($n['title']); ?></p>
                                    <p class="font-body-xs text-xs text-on-surface-variant/75 font-normal leading-normal"><?php echo e($n['message']); ?></p>
                                </div>
                                <?php if (!$n['is_read']): ?>
                                    <a href="dashboard.php?read_notif=<?php echo $n['id']; ?>" class="text-[11px] font-label-sm text-primary hover:text-secondary border border-primary/20 px-2 py-0.5 rounded text-decoration-none">
                                        Dismiss
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- RECOMMENDED FOR YOU PORTAL -->
    <section class="space-y-md mt-lg">
        <div class="flex items-center gap-xs pb-xs border-b border-outline-variant/30">
            <span class="material-symbols-outlined text-primary text-[28px] animate-pulse">auto_awesome</span>
            <h3 class="font-headline-md text-headline-md text-on-surface font-bold">Recommended For You</h3>
        </div>

        <?php if (empty($recommendations['courses']) && empty($recommendations['curated_pathways'])): ?>
            <div class="glass-card rounded-xl text-center py-lg text-on-surface-variant font-body-md">
                Complete your <a href="../profile.php" class="text-primary hover:underline font-semibold text-decoration-none">Academic Profile</a> to receive personalized learning recommendations!
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-md">
                <!-- A. Database Course Recommendations -->
                <?php foreach ($recommendations['courses'] as $rec_c): ?>
                    <div class="glass-card rounded-xl overflow-hidden p-md flex flex-col h-full hover:translate-y-[-4px] transition-all duration-200 border border-outline-variant/20 shadow-md">
                        <div class="relative h-32 w-full rounded-lg overflow-hidden mb-sm bg-surface-container-high flex items-center justify-center">
                            <?php if ($rec_c['image_path']): ?>
                                <img src="<?php echo BASE_URL . e($rec_c['image_path']); ?>" alt="Banner" class="w-full h-full object-cover">
                            <?php else: ?>
                                <span class="material-symbols-outlined text-primary text-4xl">menu_book</span>
                            <?php endif; ?>
                            <div class="absolute top-2 left-2 flex gap-1">
                                <span class="bg-primary text-white text-[9px] font-bold px-2 py-0.5 rounded-full uppercase"><?php echo e($rec_c['category']); ?></span>
                            </div>
                        </div>
                        <h4 class="font-headline-sm text-sm text-on-surface font-bold mb-xs"><?php echo e($rec_c['title']); ?></h4>
                        <p class="font-body-xs text-xs text-on-surface-variant flex-grow mb-md leading-relaxed"><?php echo e(substr($rec_c['description'], 0, 80)); ?>...</p>
                        
                        <div class="pt-sm border-t border-outline-variant/20 mt-auto flex items-center justify-between">
                            <span class="text-[10px] text-on-surface-variant font-semibold">By: <?php echo e($rec_c['teacher_name']); ?></span>
                            <form action="courses.php" method="POST" class="m-0">
                                <input type="hidden" name="csrf_token" value="<?php echo e(generate_csrf_token()); ?>">
                                <input type="hidden" name="course_id" value="<?php echo $rec_c['id']; ?>">
                                <input type="hidden" name="action" value="enroll">
                                <button type="submit" class="bg-primary text-white text-xs px-3 py-1.5 rounded-md hover:bg-primary-hover border-0 transition-colors shadow-sm font-semibold">
                                    Enroll
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- B. Curated Study Pathway Recommendations -->
                <?php foreach ($recommendations['curated_pathways'] as $path): ?>
                    <div class="glass-card rounded-xl p-md flex flex-col h-full hover:translate-y-[-4px] transition-all duration-200 border border-primary/10 bg-primary/5 shadow-md">
                        <div class="flex justify-between items-start mb-sm">
                            <div class="w-10 h-10 rounded-full bg-primary/15 flex items-center justify-center text-primary">
                                <span class="material-symbols-outlined text-[22px]"><?php echo $path['icon']; ?></span>
                            </div>
                            <span class="bg-secondary/15 text-secondary border border-secondary/20 text-[9px] font-bold px-2 py-0.5 rounded-full uppercase">
                                <?php echo $path['level']; ?>
                            </span>
                        </div>
                        <h4 class="font-headline-sm text-sm text-on-surface font-bold mb-xs"><?php echo $path['title']; ?></h4>
                        <p class="font-body-xs text-xs text-on-surface-variant flex-grow mb-md leading-relaxed"><?php echo $path['description']; ?></p>
                        
                        <div class="pt-sm border-t border-outline-variant/15 mt-auto flex items-center justify-between">
                            <span class="text-[9px] text-secondary font-bold uppercase tracking-wider">Curated Subject Path</span>
                            <span class="material-symbols-outlined text-secondary text-[16px] animate-pulse">star</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php if (!empty($quiz_history)): ?>
    <script>
    document.addEventListener("DOMContentLoaded", () => {
        const ctx = document.getElementById('quizHistoryChart').getContext('2d');
        
        // Prepare Chart Data
        const labels = [<?php foreach ($quiz_history as $qh) echo '"' . e(substr($qh['quiz_title'],0,12)) . '",'; ?>];
        const scores = [<?php foreach ($quiz_history as $qh) echo $qh['score'] . ','; ?>];
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Quiz Scores (%)',
                    data: scores,
                    borderColor: '#004ac6',
                    backgroundColor: 'rgba(0, 74, 198, 0.05)',
                    borderWidth: 3,
                    tension: 0.3,
                    fill: true,
                    pointBackgroundColor: '#712ae2',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 6,
                    pointHoverRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        grid: {
                            color: 'rgba(115, 118, 134, 0.1)'
                        },
                        ticks: { color: 'var(--text-secondary)', font: { family: 'Inter' } }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: { color: 'var(--text-secondary)', font: { family: 'Inter' } }
                    }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
    });
    </script>
<?php endif; ?>

<?php
include_once __DIR__ . '/../includes/footer.php';
?>
