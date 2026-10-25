<?php
$page_title = "Teacher Dashboard";
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/middleware.php';

require_role('Teacher');

$user_id = $_SESSION['user_id'];

// Get Teacher ID
$stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
$stmt->execute([$user_id]);
$teacher = $stmt->fetch();
$teacher_id = $teacher['id'] ?? 0;

if (!$teacher_id) {
    // Auto-create teacher record if missing
    $stmt = $pdo->prepare("INSERT INTO teachers (user_id, specialization) VALUES (?, 'General')");
    $stmt->execute([$user_id]);
    $teacher_id = $pdo->lastInsertId();
}

// 1. Total Courses Created
$courses_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE teacher_id = ?");
$courses_count_stmt->execute([$teacher_id]);
$courses_count = $courses_count_stmt->fetchColumn();

// 2. Total Enrolled Students (Count unique student IDs enrolled in this teacher's courses)
$students_count_stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT e.student_id) 
    FROM course_enrollments e 
    JOIN courses c ON e.course_id = c.id 
    WHERE c.teacher_id = ?
");
$students_count_stmt->execute([$teacher_id]);
$students_count = $students_count_stmt->fetchColumn();

// 3. Quiz Attempts Count
$attempts_count_stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM quiz_attempts qa 
    JOIN quizzes q ON qa.quiz_id = q.id 
    JOIN courses c ON q.course_id = c.id 
    WHERE c.teacher_id = ?
");
$attempts_count_stmt->execute([$teacher_id]);
$attempts_count = $attempts_count_stmt->fetchColumn();

// 4. Average Quiz score in this teacher's courses
$avg_score_stmt = $pdo->prepare("
    SELECT AVG(qa.score) 
    FROM quiz_attempts qa 
    JOIN quizzes q ON qa.quiz_id = q.id 
    JOIN courses c ON q.course_id = c.id 
    WHERE c.teacher_id = ?
");
$avg_score_stmt->execute([$teacher_id]);
$avg_quiz_score = $avg_score_stmt->fetchColumn();
$avg_quiz_score = $avg_quiz_score ? round($avg_quiz_score, 1) : 0;

// Fetch enrollment counts per course for chart
$chart_stmt = $pdo->prepare("
    SELECT c.title, COUNT(e.id) AS enrollments 
    FROM courses c 
    LEFT JOIN course_enrollments e ON c.id = e.course_id 
    WHERE c.teacher_id = ? 
    GROUP BY c.id
    ORDER BY enrollments DESC 
    LIMIT 5
");
$chart_stmt->execute([$teacher_id]);
$course_stats = $chart_stmt->fetchAll();

// Fetch 5 recent enrollments activities
$recent_stmt = $pdo->prepare("
    SELECT e.enrolled_at, c.title AS course_title, u.full_name AS student_name 
    FROM course_enrollments e 
    JOIN courses c ON e.course_id = c.id 
    JOIN students s ON e.student_id = s.id
    JOIN users u ON s.user_id = u.id 
    WHERE c.teacher_id = ? 
    ORDER BY e.enrolled_at DESC 
    LIMIT 5
");
$recent_stmt->execute([$teacher_id]);
$recent_activities = $recent_stmt->fetchAll();
?>

<div class="space-y-lg">
    <!-- Header -->
    <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-md">
        <div>
            <h2 class="font-headline-lg text-headline-lg text-on-surface mb-xs">
                <?php echo e(__('teacher.welcome', ['name' => $_SESSION['full_name']])); ?>
            </h2>
            <p class="font-body-md text-body-md text-on-surface-variant">Monitor your syllabus, analyze quiz scores, and view student activities.</p>
        </div>
        <div class="flex items-center gap-sm shrink-0">
            <a href="reports.php" class="inline-flex items-center gap-xs border border-outline-variant/30 text-on-surface hover:bg-surface-variant/20 px-md py-2.5 rounded-lg font-label-md text-label-md text-decoration-none transition-colors">
                <span class="material-symbols-outlined text-[18px]">analytics</span>
                View Analytics Reports
            </a>
            <a href="courses.php" class="inline-flex items-center gap-xs bg-primary text-white hover:bg-primary-hover px-md py-2.5 rounded-lg font-label-md text-label-md text-decoration-none transition-colors shadow-md">
                <span class="material-symbols-outlined text-[18px]">add</span>
                Manage Courses
            </a>
        </div>
    </header>

    <!-- STATS CARDS -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-md">
        <!-- Card 1 -->
        <div class="glass-card rounded-xl p-md flex items-center gap-md">
            <div class="w-12 h-12 rounded-full bg-primary/10 text-primary flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-2xl" style="font-variation-settings: 'FILL' 1;">menu_book</span>
            </div>
            <div>
                <span class="text-xs font-label-sm text-on-surface-variant block uppercase tracking-wider"><?php echo e(__('teacher.total_courses')); ?></span>
                <strong class="font-headline-sm text-headline-sm text-on-surface font-bold"><?php echo $courses_count; ?> Courses</strong>
            </div>
        </div>
        
        <!-- Card 2 -->
        <div class="glass-card rounded-xl p-md flex items-center gap-md">
            <div class="w-12 h-12 rounded-full bg-secondary/10 text-secondary flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-2xl" style="font-variation-settings: 'FILL' 1;">group</span>
            </div>
            <div>
                <span class="text-xs font-label-sm text-on-surface-variant block uppercase tracking-wider"><?php echo e(__('teacher.total_students')); ?></span>
                <strong class="font-headline-sm text-headline-sm text-on-surface font-bold"><?php echo $students_count; ?> Students</strong>
            </div>
        </div>
        
        <!-- Card 3 -->
        <div class="glass-card rounded-xl p-md flex items-center gap-md">
            <div class="w-12 h-12 rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-2xl" style="font-variation-settings: 'FILL' 1;">fact_check</span>
            </div>
            <div>
                <span class="text-xs font-label-sm text-on-surface-variant block uppercase tracking-wider">Total Quiz Attempts</span>
                <strong class="font-headline-sm text-headline-sm text-on-surface font-bold"><?php echo $attempts_count; ?> Attempts</strong>
            </div>
        </div>
        
        <!-- Card 4 -->
        <div class="glass-card rounded-xl p-md flex items-center gap-md">
            <div class="w-12 h-12 rounded-full bg-green-500/10 text-green-500 flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-2xl" style="font-variation-settings: 'FILL' 1;">workspace_premium</span>
            </div>
            <div>
                <span class="text-xs font-label-sm text-on-surface-variant block uppercase tracking-wider">Average Quiz Score</span>
                <strong class="font-headline-sm text-headline-sm text-on-surface font-bold"><?php echo $avg_quiz_score; ?>%</strong>
            </div>
        </div>
    </div>

    <!-- MAIN BODY GRID -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-gutter">
        <!-- Enrollments Chart -->
        <div class="lg:col-span-7">
            <div class="glass-card rounded-xl p-md space-y-md">
                <div class="flex items-center gap-sm pb-xs border-b border-outline-variant/30">
                    <span class="material-symbols-outlined text-primary text-2xl">bar_chart</span>
                    <h3 class="font-headline-sm text-headline-sm text-on-surface">Students Enrollment Statistics</h3>
                </div>
                <?php if (empty($course_stats)): ?>
                    <div class="text-center py-xl text-on-surface-variant">No active courses statistics available.</div>
                <?php else: ?>
                    <div class="relative w-full">
                        <canvas id="courseEnrollmentChart" style="max-height: 280px;"></canvas>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Actions -->
        <div class="lg:col-span-5">
            <div class="glass-card rounded-xl p-md space-y-md card-fit-height">
                <div class="flex items-center gap-sm pb-xs border-b border-outline-variant/30">
                    <span class="material-symbols-outlined text-primary text-2xl">schedule</span>
                    <h3 class="font-headline-sm text-headline-sm text-on-surface">Recent Student Enrollments</h3>
                </div>
                
                <?php if (empty($recent_activities)): ?>
                    <div class="text-center py-xl text-on-surface-variant">
                        No recent activities recorded.
                    </div>
                <?php else: ?>
                    <div class="flex flex-col gap-sm divide-y divide-outline-variant/20">
                        <?php foreach ($recent_activities as $ra): ?>
                            <div class="pt-sm first:pt-0">
                                <div class="flex justify-between items-center">
                                    <strong class="font-headline-sm text-body-md text-on-surface font-bold"><?php echo e($ra['student_name']); ?></strong>
                                    <span class="text-xs text-on-surface-variant"><?php echo date('M d, Y', strtotime($ra['enrolled_at'])); ?></span>
                                </div>
                                <p class="text-xs text-on-surface-variant mt-1 mb-0">Enrolled in: <span class="text-primary font-semibold"><?php echo e($ra['course_title']); ?></span></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($course_stats)): ?>
    <script>
    document.addEventListener("DOMContentLoaded", () => {
        const ctx = document.getElementById('courseEnrollmentChart').getContext('2d');
        
        const labels = [<?php foreach ($course_stats as $stat) echo '"' . e(substr($stat['title'], 0, 15)) . '",'; ?>];
        const data = [<?php foreach ($course_stats as $stat) echo $stat['enrollments'] . ','; ?>];
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Students Enrolled',
                    data: data,
                    backgroundColor: 'rgba(0, 74, 198, 0.75)',
                    borderColor: '#004ac6',
                    borderWidth: 1.5,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(115, 118, 134, 0.15)'
                        },
                        ticks: { 
                            color: '#737686', 
                            stepSize: 1,
                            font: { family: 'Plus Jakarta Sans', size: 10 }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: { 
                            color: '#737686',
                            font: { family: 'Plus Jakarta Sans', size: 10 }
                        }
                    }
                },
                plugins: {
                    legend: { 
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
<?php endif; ?>

<?php
include_once __DIR__ . '/../includes/footer.php';
?>
