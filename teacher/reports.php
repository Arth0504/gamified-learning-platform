<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/translate.php';
require_once __DIR__ . '/../includes/middleware.php';

require_role('Teacher');

$user_id = $_SESSION['user_id'];
$report_type = $_GET['type'] ?? 'performance'; // performance, engagement, completion

// Get Teacher ID
$stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
$stmt->execute([$user_id]);
$teacher = $stmt->fetch();
$teacher_id = $teacher['id'] ?? 0;

// HANDLE CSV DOWNLOAD IN-PLACE
if (isset($_GET['download']) && $_GET['download'] === '1') {
    ob_end_clean(); // Flush buffer
    
    $filename = "teacher_" . $report_type . "_report_" . date('Ymd_His') . ".csv";
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    
    $output = fopen('php://output', 'w');
    
    if ($report_type === 'performance') {
        fputcsv($output, ['Student Name', 'Course Enrolled', 'Progress Percent', 'Enrollment Status', 'Enrolled Date']);
        
        $stmt = $pdo->prepare("
            SELECT u.full_name, c.title AS course_title, e.progress_percent, e.status AS enroll_status, e.enrolled_at 
            FROM course_enrollments e 
            JOIN courses c ON e.course_id = c.id 
            JOIN students s ON e.student_id = s.id
            JOIN users u ON s.user_id = u.id 
            WHERE c.teacher_id = ?
        ");
        $stmt->execute([$teacher_id]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, $row);
        }
        
    } elseif ($report_type === 'engagement') {
        fputcsv($output, ['Student Name', 'Course Title', 'Lessons Completed', 'Total Subject Lessons']);
        
        $stmt = $pdo->prepare("
            SELECT u.full_name, c.title AS course_title, 
                   (SELECT COUNT(*) FROM progress p JOIN lessons l ON p.lesson_id = l.id JOIN subjects s ON l.subject_id = s.id WHERE s.course_id = c.id AND p.student_id = e.student_id AND p.status = 'completed') AS completed_lessons,
                   (SELECT COUNT(*) FROM lessons l JOIN subjects s ON l.subject_id = s.id WHERE s.course_id = c.id) AS total_lessons
            FROM course_enrollments e 
            JOIN courses c ON e.course_id = c.id 
            JOIN students s ON e.student_id = s.id
            JOIN users u ON s.user_id = u.id 
            WHERE c.teacher_id = ?
        ");
        $stmt->execute([$teacher_id]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, $row);
        }
        
    } elseif ($report_type === 'completion') {
        fputcsv($output, ['Student Name', 'Completed Course', 'Enrolled Date', 'Completion Date']);
        
        $stmt = $pdo->prepare("
            SELECT u.full_name, c.title AS course_title, e.enrolled_at, e.completed_at 
            FROM course_enrollments e 
            JOIN courses c ON e.course_id = c.id 
            JOIN students s ON e.student_id = s.id
            JOIN users u ON s.user_id = u.id 
            WHERE c.teacher_id = ? AND e.status = 'completed'
        ");
        $stmt->execute([$teacher_id]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, $row);
        }
    } elseif ($report_type === 'security') {
        fputcsv($output, ['Student Name', 'Quiz Title', 'Course Title', 'Warnings Count', 'Focus Losses', 'Fullscreen Exits', 'Auto Submitted', 'Date/Time']);
        
        $stmt = $pdo->prepare("
            SELECT u.full_name, q.title AS quiz_title, c.title AS course_title, 
                   s.warning_count, s.focus_loss_count, s.fullscreen_exit_count, 
                   IF(s.auto_submitted, 'Yes', 'No') AS auto_submitted, s.created_at 
            FROM quiz_security_logs s 
            JOIN users u ON s.user_id = u.id 
            JOIN quizzes q ON s.quiz_id = q.id 
            JOIN courses c ON q.course_id = c.id 
            WHERE c.teacher_id = ? 
            ORDER BY s.created_at DESC
        ");
        $stmt->execute([$teacher_id]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
    exit();
}

$page_title = "Teacher Reports";
include_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-lg">
    <!-- Header -->
    <header class="flex flex-col md:flex-row justify-between items-start md:items-center gap-md">
        <div>
            <div class="flex items-center gap-xs font-label-md text-label-md text-on-surface-variant mb-xs">
                <span>Analytics</span>
                <span class="material-symbols-outlined text-[14px]">chevron_right</span>
                <span class="text-primary font-bold">Detailed Reports</span>
            </div>
            <h2 class="font-headline-lg text-headline-lg text-on-surface mb-xs font-bold">Student Performance Analytics</h2>
            <p class="font-body-md text-body-md text-on-surface-variant">Export spreadsheets of student progress, quiz grades, and completed syllabi.</p>
        </div>
        <div class="shrink-0">
            <a href="reports.php?type=<?php echo $report_type; ?>&download=1" class="inline-flex items-center gap-xs bg-primary text-white hover:bg-primary-hover px-md py-2.5 rounded-lg font-label-md text-label-md text-decoration-none transition-colors shadow-md">
                <span class="material-symbols-outlined text-[20px]">ios_share</span>
                Download CSV Spreadsheet
            </a>
        </div>
    </header>

    <!-- Tabs selector -->
    <div class="glass-card rounded-xl p-xs flex flex-wrap gap-xs">
        <a class="px-md py-2 rounded-lg font-label-md text-label-md text-decoration-none transition-colors <?php echo $report_type === 'performance' ? 'bg-primary text-white active font-bold' : 'text-on-surface-variant hover:bg-surface-variant/20'; ?>" href="reports.php?type=performance">
            <span class="inline-flex items-center gap-xs">
                <span class="material-symbols-outlined text-[18px]">trending_up</span>
                Student Progress
            </span>
        </a>
        <a class="px-md py-2 rounded-lg font-label-md text-label-md text-decoration-none transition-colors <?php echo $report_type === 'engagement' ? 'bg-primary text-white active font-bold' : 'text-on-surface-variant hover:bg-surface-variant/20'; ?>" href="reports.php?type=engagement">
            <span class="inline-flex items-center gap-xs">
                <span class="material-symbols-outlined text-[18px]">visibility</span>
                Lessons Engagement
            </span>
        </a>
        <a class="px-md py-2 rounded-lg font-label-md text-label-md text-decoration-none transition-colors <?php echo $report_type === 'completion' ? 'bg-primary text-white active font-bold' : 'text-on-surface-variant hover:bg-surface-variant/20'; ?>" href="reports.php?type=completion">
            <span class="inline-flex items-center gap-xs">
                <span class="material-symbols-outlined text-[18px]">school</span>
                Completed Students
            </span>
        </a>
        <a class="px-md py-2 rounded-lg font-label-md text-label-md text-decoration-none transition-colors <?php echo $report_type === 'security' ? 'bg-primary text-white active font-bold' : 'text-on-surface-variant hover:bg-surface-variant/20'; ?>" href="reports.php?type=security">
            <span class="inline-flex items-center gap-xs">
                <span class="material-symbols-outlined text-[18px]">security</span>
                Security Violations
            </span>
        </a>
    </div>

    <!-- Report Display Board -->
    <div class="glass-card rounded-xl p-md">
        <?php if ($report_type === 'performance'): 
            $stmt = $pdo->prepare("
                SELECT u.full_name, c.title AS course_title, e.progress_percent, e.status AS enroll_status, e.enrolled_at 
                FROM course_enrollments e 
                JOIN courses c ON e.course_id = c.id 
                JOIN students s ON e.student_id = s.id
                JOIN users u ON s.user_id = u.id 
                WHERE c.teacher_id = ?
            ");
            $stmt->execute([$teacher_id]);
            $data = $stmt->fetchAll();
        ?>
            <h3 class="font-headline-sm text-headline-sm text-on-surface pb-xs border-b border-outline-variant/30 mb-md">Active Students Enrolled</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-body-sm text-on-surface-variant">
                    <thead>
                        <tr class="border-b border-outline-variant/30 text-xs font-semibold uppercase text-on-surface-variant/75">
                            <th class="py-3 px-4">Student Name</th>
                            <th class="py-3 px-4">Course Title</th>
                            <th class="py-3 px-4" style="max-width: 250px;">Completion Progress</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4">Enrolled Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/20">
                        <?php if (empty($data)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-5 text-on-surface-variant">No enrolled students found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($data as $row): ?>
                                <tr class="hover:bg-surface-variant/10 transition-colors">
                                    <td class="py-3 px-4"><span class="text-on-surface font-semibold"><?php echo e($row['full_name']); ?></span></td>
                                    <td class="py-3 px-4"><?php echo e($row['course_title']); ?></td>
                                    <td class="py-3 px-4">
                                        <div class="flex items-center gap-sm">
                                            <div class="flex-grow h-2 bg-surface-container rounded-full overflow-hidden">
                                                <div class="h-full bg-primary rounded-full" style="width: <?php echo $row['progress_percent']; ?>%;"></div>
                                            </div>
                                            <span class="font-bold text-on-surface text-xs"><?php echo $row['progress_percent']; ?>%</span>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold uppercase <?php echo $row['enroll_status'] === 'completed' ? 'bg-green-500/10 text-green-500' : 'bg-on-surface-variant/10 text-on-surface-variant'; ?>">
                                            <?php echo ucfirst($row['enroll_status']); ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-xs font-semibold"><?php echo date('M d, Y', strtotime($row['enrolled_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($report_type === 'engagement'): 
            $stmt = $pdo->prepare("
                SELECT u.full_name, c.title AS course_title, 
                       (SELECT COUNT(*) FROM progress p JOIN lessons l ON p.lesson_id = l.id JOIN subjects s ON l.subject_id = s.id WHERE s.course_id = c.id AND p.student_id = e.student_id AND p.status = 'completed') AS completed_lessons,
                       (SELECT COUNT(*) FROM lessons l JOIN subjects s ON l.subject_id = s.id WHERE s.course_id = c.id) AS total_lessons
                FROM course_enrollments e 
                JOIN courses c ON e.course_id = c.id 
                JOIN students s ON e.student_id = s.id
                JOIN users u ON s.user_id = u.id 
                WHERE c.teacher_id = ?
            ");
            $stmt->execute([$teacher_id]);
            $data = $stmt->fetchAll();
        ?>
            <h3 class="font-headline-sm text-headline-sm text-on-surface pb-xs border-b border-outline-variant/30 mb-md">Lessons Completion Engagement</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-body-sm text-on-surface-variant">
                    <thead>
                        <tr class="border-b border-outline-variant/30 text-xs font-semibold uppercase text-on-surface-variant/75">
                            <th class="py-3 px-4">Student Name</th>
                            <th class="py-3 px-4">Course Title</th>
                            <th class="py-3 px-4">Completed Lessons</th>
                            <th class="py-3 px-4">Total Lessons</th>
                            <th class="py-3 px-4">Engagement Ratio</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/20">
                        <?php if (empty($data)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-5 text-on-surface-variant">No active engagement records found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($data as $row): 
                                $comp = intval($row['completed_lessons']);
                                $tot = intval($row['total_lessons']);
                                $ratio = $tot > 0 ? round(($comp / $tot) * 100) : 0;
                            ?>
                                <tr class="hover:bg-surface-variant/10 transition-colors">
                                    <td class="py-3 px-4"><span class="text-on-surface font-semibold"><?php echo e($row['full_name']); ?></span></td>
                                    <td class="py-3 px-4"><?php echo e($row['course_title']); ?></td>
                                    <td class="py-3 px-4"><span class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold bg-green-500/10 text-green-600 uppercase"><?php echo $comp; ?> Lessons</span></td>
                                    <td class="py-3 px-4 text-xs font-semibold"><?php echo $tot; ?> Lessons</td>
                                    <td class="py-3 px-4">
                                        <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold uppercase <?php echo $ratio >= 70 ? 'bg-green-500/10 text-green-500' : 'bg-amber-500/10 text-amber-600 dark:text-amber-400'; ?>">
                                            <?php echo $ratio; ?>%
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($report_type === 'completion'): 
            $stmt = $pdo->prepare("
                SELECT u.full_name, c.title AS course_title, e.enrolled_at, e.completed_at 
                FROM course_enrollments e 
                JOIN courses c ON e.course_id = c.id 
                JOIN students s ON e.student_id = s.id
                JOIN users u ON s.user_id = u.id 
                WHERE c.teacher_id = ? AND e.status = 'completed'
            ");
            $stmt->execute([$teacher_id]);
            $data = $stmt->fetchAll();
        ?>
            <h3 class="font-headline-sm text-headline-sm text-on-surface pb-xs border-b border-outline-variant/30 mb-md">Course Completions Hall of Fame</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-body-sm text-on-surface-variant">
                    <thead>
                        <tr class="border-b border-outline-variant/30 text-xs font-semibold uppercase text-on-surface-variant/75">
                            <th class="py-3 px-4">Student Name</th>
                            <th class="py-3 px-4">Completed Course</th>
                            <th class="py-3 px-4">Enrolled Date</th>
                            <th class="py-3 px-4">Completion Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/20">
                        <?php if (empty($data)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-5 text-on-surface-variant">No completed students found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($data as $row): ?>
                                <tr class="hover:bg-surface-variant/10 transition-colors">
                                    <td class="py-3 px-4"><span class="text-on-surface font-semibold"><?php echo e($row['full_name']); ?></span></td>
                                    <td class="py-3 px-4"><?php echo e($row['course_title']); ?></td>
                                    <td class="py-3 px-4 text-xs font-semibold"><?php echo date('M d, Y', strtotime($row['enrolled_at'])); ?></td>
                                    <td class="py-3 px-4">
                                        <span class="inline-flex items-center gap-xs bg-green-500/10 text-green-500 px-2 py-0.5 rounded text-[10px] font-bold uppercase">
                                            <span class="material-symbols-outlined text-[14px]">event_available</span>
                                            <?php echo date('M d, Y', strtotime($row['completed_at'])); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif ($report_type === 'security'): 
            $stmt = $pdo->prepare("
                SELECT u.full_name, q.title AS quiz_title, c.title AS course_title, 
                       s.warning_count, s.focus_loss_count, s.fullscreen_exit_count, s.auto_submitted, s.created_at 
                FROM quiz_security_logs s 
                JOIN users u ON s.user_id = u.id 
                JOIN quizzes q ON s.quiz_id = q.id 
                JOIN courses c ON q.course_id = c.id 
                WHERE c.teacher_id = ? 
                ORDER BY s.created_at DESC
            ");
            $stmt->execute([$teacher_id]);
            $data = $stmt->fetchAll();
        ?>
            <h3 class="font-headline-sm text-headline-sm text-on-surface pb-xs border-b border-outline-variant/30 mb-md">Quiz Security Logs Report</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-body-sm text-on-surface-variant">
                    <thead>
                        <tr class="border-b border-outline-variant/30 text-xs font-semibold uppercase text-on-surface-variant/75">
                            <th class="py-3 px-4">Student Name</th>
                            <th class="py-3 px-4">Quiz Title</th>
                            <th class="py-3 px-4">Course Title</th>
                            <th class="py-3 px-4 text-center">Warnings</th>
                            <th class="py-3 px-4 text-center">Focus Losses</th>
                            <th class="py-3 px-4 text-center">Fullscreen Exits</th>
                            <th class="py-3 px-4 text-center">Auto-Submitted</th>
                            <th class="py-3 px-4">Timestamp</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/20">
                        <?php if (empty($data)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-on-surface-variant">No security violations recorded.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($data as $row): ?>
                                <tr class="hover:bg-surface-variant/10 transition-colors">
                                    <td class="py-3 px-4"><span class="text-on-surface font-semibold"><?php echo e($row['full_name']); ?></span></td>
                                    <td class="py-3 px-4"><?php echo e($row['quiz_title']); ?></td>
                                    <td class="py-3 px-4"><?php echo e($row['course_title']); ?></td>
                                    <td class="py-3 px-4 text-center">
                                        <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold <?php echo $row['warning_count'] > 0 ? 'bg-red-500/10 text-red-500' : 'bg-green-500/10 text-green-500'; ?>">
                                            <?php echo $row['warning_count']; ?> Warnings
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-center font-semibold text-xs"><?php echo $row['focus_loss_count']; ?> times</td>
                                    <td class="py-3 px-4 text-center font-semibold text-xs"><?php echo $row['fullscreen_exit_count']; ?> times</td>
                                    <td class="py-3 px-4 text-center">
                                        <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold uppercase <?php echo $row['auto_submitted'] ? 'bg-red-500/15 text-red-500' : 'bg-green-500/10 text-green-500'; ?>">
                                            <?php echo $row['auto_submitted'] ? 'Yes (Auto Submit)' : 'No'; ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-xs font-semibold"><?php echo date('M d, Y H:i A', strtotime($row['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
include_once __DIR__ . '/../includes/footer.php';
?>
