<?php
$page_title = "Admin Dashboard";
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/middleware.php';

require_role('Admin');

$user_id = $_SESSION['user_id'];

// Stats counters
$students_count = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$teachers_count = $pdo->query("SELECT COUNT(*) FROM teachers")->fetchColumn();
$courses_count  = $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
$lessons_count  = $pdo->query("SELECT COUNT(*) FROM lessons")->fetchColumn();
$quizzes_count  = $pdo->query("SELECT COUNT(*) FROM quizzes")->fetchColumn();
$total_users    = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

// Fetch role stats for Chart
$role_stats_stmt = $pdo->query("
    SELECT r.name AS role_name, COUNT(u.id) AS user_count 
    FROM users u 
    JOIN roles r ON u.role_id = r.id 
    GROUP BY r.id
");
$role_stats = $role_stats_stmt->fetchAll();

// Top 5 Performing Students
$top_students_stmt = $pdo->query("
    SELECT s.points, s.xp, u.full_name 
    FROM students s 
    JOIN users u ON s.user_id = u.id 
    ORDER BY s.points DESC, s.xp DESC 
    LIMIT 5
");
$top_students = $top_students_stmt->fetchAll();

// Fetch 5 Recent Activity Logs
$logs_stmt = $pdo->query("
    SELECT al.*, u.username 
    FROM activity_logs al 
    LEFT JOIN users u ON al.user_id = u.id 
    ORDER BY al.created_at DESC 
    LIMIT 5
");
$logs = $logs_stmt->fetchAll();
?>

<div class="space-y-lg">
    <!-- Header -->
    <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-md mb-lg">
        <div>
            <h2 class="font-headline-lg text-headline-lg text-on-surface mb-xs font-bold"><?php echo e(__('admin.welcome')); ?></h2>
            <p class="font-body-md text-body-md text-on-surface-variant">System Overview and platform operations dashboard.</p>
        </div>
        <div class="flex items-center gap-sm shrink-0">
            <a href="students.php" class="inline-flex items-center gap-xs bg-primary text-white hover:bg-primary-hover px-md py-2.5 rounded-lg font-label-md text-label-md text-decoration-none transition-colors shadow-md">
                <span class="material-symbols-outlined text-[18px]">group</span>
                Manage Students
            </a>
        </div>
    </header>

    <!-- STATS CARDS GRID -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-md">
        <!-- Students -->
        <div class="glass-card rounded-xl p-sm flex flex-col items-center text-center gap-xs hover:translate-y-[-2px] transition-transform duration-200">
            <div class="w-10 h-10 rounded-full bg-primary/10 text-primary flex items-center justify-center">
                <span class="material-symbols-outlined text-[20px]" style="font-variation-settings: 'FILL' 1;">school</span>
            </div>
            <div>
                <span class="text-[10px] font-label-sm text-on-surface-variant block uppercase tracking-wider">Students</span>
                <strong class="font-headline-sm text-headline-sm text-on-surface font-bold"><?php echo $students_count; ?></strong>
            </div>
        </div>

        <!-- Teachers -->
        <div class="glass-card rounded-xl p-sm flex flex-col items-center text-center gap-xs hover:translate-y-[-2px] transition-transform duration-200">
            <div class="w-10 h-10 rounded-full bg-secondary/10 text-secondary flex items-center justify-center">
                <span class="material-symbols-outlined text-[20px]" style="font-variation-settings: 'FILL' 1;">person_celebrate</span>
            </div>
            <div>
                <span class="text-[10px] font-label-sm text-on-surface-variant block uppercase tracking-wider">Teachers</span>
                <strong class="font-headline-sm text-headline-sm text-on-surface font-bold"><?php echo $teachers_count; ?></strong>
            </div>
        </div>

        <!-- Courses -->
        <div class="glass-card rounded-xl p-sm flex flex-col items-center text-center gap-xs hover:translate-y-[-2px] transition-transform duration-200">
            <div class="w-10 h-10 rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                <span class="material-symbols-outlined text-[20px]" style="font-variation-settings: 'FILL' 1;">library_books</span>
            </div>
            <div>
                <span class="text-[10px] font-label-sm text-on-surface-variant block uppercase tracking-wider">Courses</span>
                <strong class="font-headline-sm text-headline-sm text-on-surface font-bold"><?php echo $courses_count; ?></strong>
            </div>
        </div>

        <!-- Lessons -->
        <div class="glass-card rounded-xl p-sm flex flex-col items-center text-center gap-xs hover:translate-y-[-2px] transition-transform duration-200">
            <div class="w-10 h-10 rounded-full bg-red-500/10 text-red-500 flex items-center justify-center">
                <span class="material-symbols-outlined text-[20px]" style="font-variation-settings: 'FILL' 1;">menu_book</span>
            </div>
            <div>
                <span class="text-[10px] font-label-sm text-on-surface-variant block uppercase tracking-wider">Lessons</span>
                <strong class="font-headline-sm text-headline-sm text-on-surface font-bold"><?php echo $lessons_count; ?></strong>
            </div>
        </div>

        <!-- Quizzes -->
        <div class="glass-card rounded-xl p-sm flex flex-col items-center text-center gap-xs hover:translate-y-[-2px] transition-transform duration-200">
            <div class="w-10 h-10 rounded-full bg-green-500/10 text-green-500 flex items-center justify-center">
                <span class="material-symbols-outlined text-[20px]" style="font-variation-settings: 'FILL' 1;">quiz</span>
            </div>
            <div>
                <span class="text-[10px] font-label-sm text-on-surface-variant block uppercase tracking-wider">Quizzes</span>
                <strong class="font-headline-sm text-headline-sm text-on-surface font-bold"><?php echo $quizzes_count; ?></strong>
            </div>
        </div>

        <!-- Total Users -->
        <div class="glass-card rounded-xl p-sm flex flex-col items-center text-center gap-xs hover:translate-y-[-2px] transition-transform duration-200">
            <div class="w-10 h-10 rounded-full bg-slate-500/10 text-slate-500 flex items-center justify-center">
                <span class="material-symbols-outlined text-[20px]" style="font-variation-settings: 'FILL' 1;">lock</span>
            </div>
            <div>
                <span class="text-[10px] font-label-sm text-on-surface-variant block uppercase tracking-wider">Total Users</span>
                <strong class="font-headline-sm text-headline-sm text-on-surface font-bold"><?php echo $total_users; ?></strong>
            </div>
        </div>
    </div>

    <!-- MAIN GRID -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-gutter">
        <!-- Role breakdown Pie Chart -->
        <div class="glass-card rounded-xl p-md flex flex-col space-y-md card-fit-height">
            <div class="flex items-center gap-sm pb-xs border-b border-outline-variant/30">
                <span class="material-symbols-outlined text-primary text-2xl">pie_chart</span>
                <h3 class="font-headline-sm text-headline-sm text-on-surface">User Breakdown</h3>
            </div>
            <div class="relative w-full flex-grow flex items-center justify-center">
                <canvas id="rolesChart" style="max-height: 240px;"></canvas>
            </div>
        </div>

        <!-- Top Performers List -->
        <div class="glass-card rounded-xl p-md flex flex-col space-y-md card-fit-height">
            <div class="flex items-center gap-sm pb-xs border-b border-outline-variant/30">
                <span class="material-symbols-outlined text-amber-500 text-2xl" style="font-variation-settings: 'FILL' 1;">military_tech</span>
                <h3 class="font-headline-sm text-headline-sm text-on-surface">Top Student Performers</h3>
            </div>
            <div class="flex flex-col gap-sm divide-y divide-outline-variant/20 flex-grow justify-center">
                <?php foreach ($top_students as $idx => $ts): ?>
                    <div class="flex justify-between items-center pt-xs first:pt-0">
                        <div class="flex items-center gap-sm">
                            <span class="inline-flex w-5 h-5 rounded-full bg-green-500/10 text-green-500 text-[10px] font-bold items-center justify-center"><?php echo $idx + 1; ?></span>
                            <span class="font-body-md text-on-surface font-semibold text-xs"><?php echo e($ts['full_name']); ?></span>
                        </div>
                        <span class="text-xs text-amber-600 bg-amber-500/10 border border-amber-500/20 px-2 py-0.5 rounded font-bold"><?php echo $ts['points']; ?> Pts</span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- System Logs -->
        <div class="glass-card rounded-xl p-md flex flex-col space-y-md card-fit-height">
            <div class="flex items-center gap-sm pb-xs border-b border-outline-variant/30">
                <span class="material-symbols-outlined text-primary text-2xl">terminal</span>
                <h3 class="font-headline-sm text-headline-sm text-on-surface">Activity Audits</h3>
            </div>
            <div class="flex flex-col gap-sm divide-y divide-outline-variant/20 text-xs">
                <?php foreach ($logs as $log): ?>
                    <div class="pt-sm first:pt-0 space-y-0.5">
                        <div class="flex justify-between text-on-surface-variant font-semibold">
                            <span>@<?php echo e($log['username'] ?? 'System'); ?></span>
                            <span class="text-[10px] opacity-75"><?php echo date('M d H:i', strtotime($log['created_at'])); ?></span>
                        </div>
                        <strong class="text-on-surface text-xs block"><?php echo e($log['action']); ?></strong>
                        <p class="text-[11px] text-on-surface-variant/80 truncate mb-0"><?php echo e($log['details']); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const ctx = document.getElementById('rolesChart').getContext('2d');
    
    const labels = [<?php foreach ($role_stats as $rs) echo '"' . e($rs['role_name']) . '",'; ?>];
    const data = [<?php foreach ($role_stats as $rs) echo $rs['user_count'] . ','; ?>];
    
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: [
                    '#004ac6', // Primary Blue
                    '#712ae2', // Secondary Purple
                    '#ffa000'  // Amber
                ],
                borderWidth: 1.5,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { 
                    position: 'bottom',
                    labels: { 
                        color: '#737686',
                        font: { family: 'Plus Jakarta Sans', size: 11, weight: 'bold' }
                    }
                }
            }
        }
    });
});
</script>

<?php
include_once __DIR__ . '/../includes/footer.php';
?>
