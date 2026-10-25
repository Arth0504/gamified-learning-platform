<?php
$page_title = "Track Student Progress";
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/middleware.php';

require_role('Teacher');

$user_id = $_SESSION['user_id'];

// Get Teacher ID
$stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
$stmt->execute([$user_id]);
$teacher = $stmt->fetch();
$teacher_id = $teacher['id'] ?? 0;

// Fetch enrolled students progress in courses taught by this teacher
$progress_stmt = $pdo->prepare("
    SELECT e.progress_percent, e.status AS enroll_status, e.enrolled_at,
           c.title AS course_title, c.id AS course_id,
           u.full_name AS student_name, s.id AS student_id,
           (SELECT AVG(qa.score) FROM quiz_attempts qa WHERE qa.student_id = s.id AND qa.quiz_id IN (SELECT id FROM quizzes WHERE course_id = c.id)) AS avg_quiz_score
    FROM course_enrollments e 
    JOIN courses c ON e.course_id = c.id 
    JOIN students s ON e.student_id = s.id
    JOIN users u ON s.user_id = u.id 
    WHERE c.teacher_id = ?
    ORDER BY e.enrolled_at DESC
");
$progress_stmt->execute([$teacher_id]);
$student_progress = $progress_stmt->fetchAll();
?>

<div class="space-y-lg">
    <!-- Header -->
    <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-md mb-lg">
        <div>
            <h2 class="font-headline-lg text-headline-lg text-on-surface mb-xs flex items-center gap-sm font-bold">
                <span class="material-symbols-outlined text-primary text-3xl" style="font-variation-settings: 'FILL' 1;">insights</span>
                Student Progress Tracking
            </h2>
            <p class="font-body-md text-body-md text-on-surface-variant">View the course completion percentages and quiz scores of students enrolled in your modules.</p>
        </div>
    </header>

    <!-- Complete Progress list -->
    <div class="glass-card rounded-xl p-md">
        <?php if (empty($student_progress)): ?>
            <div class="text-center py-xl space-y-md text-on-surface-variant">
                <span class="material-symbols-outlined text-on-surface-variant text-5xl">group</span>
                <p class="font-body-md text-body-md">No students enrolled in your courses yet.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-body-sm text-on-surface-variant">
                    <thead>
                        <tr class="border-b border-outline-variant/30 text-xs font-semibold uppercase text-on-surface-variant/75">
                            <th class="py-3 px-4">Student Name</th>
                            <th class="py-3 px-4">Course Title</th>
                            <th class="py-3 px-4" style="max-width: 250px;">Completion Progress</th>
                            <th class="py-3 px-4">Average Quiz Score</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4">Enrolled Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/20">
                        <?php foreach ($student_progress as $row): 
                            $avg_score = $row['avg_quiz_score'] ? round($row['avg_quiz_score'], 1) . '%' : 'N/A';
                        ?>
                            <tr class="hover:bg-surface-variant/10 transition-colors">
                                <td class="py-3 px-4"><span class="text-on-surface font-semibold"><?php echo e($row['student_name']); ?></span></td>
                                <td class="py-3 px-4"><?php echo e($row['course_title']); ?></td>
                                <td class="py-3 px-4">
                                    <div class="flex items-center gap-sm">
                                        <div class="flex-grow h-2 bg-surface-container rounded-full overflow-hidden">
                                            <div class="h-full bg-primary rounded-full transition-all duration-1000" style="width: <?php echo $row['progress_percent']; ?>%;"></div>
                                        </div>
                                        <span class="font-bold text-on-surface text-xs"><?php echo $row['progress_percent']; ?>%</span>
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold bg-primary/10 text-primary uppercase"><?php echo $avg_score; ?></span>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold uppercase <?php echo $row['enroll_status'] === 'completed' ? 'bg-green-500/10 text-green-500' : 'bg-on-surface-variant/10 text-on-surface-variant'; ?>">
                                        <?php echo ucfirst($row['enroll_status']); ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-xs font-semibold"><?php echo date('M d, Y', strtotime($row['enrolled_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
include_once __DIR__ . '/../includes/footer.php';
?>
