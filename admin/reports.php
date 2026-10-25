<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/translate.php';
require_once __DIR__ . '/../includes/middleware.php';

require_role('Admin');

$user_id = $_SESSION['user_id'];
$report_type = $_GET['type'] ?? 'student'; // student, teacher, course, quiz

// HANDLE CSV DOWNLOAD IN-PLACE
if (isset($_GET['download']) && $_GET['download'] === '1') {
    ob_end_clean(); // Clear headers output
    
    $filename = "admin_" . $report_type . "_report_" . date('Ymd_His') . ".csv";
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    
    $output = fopen('php://output', 'w');
    
    if ($report_type === 'student') {
        fputcsv($output, ['Full Name', 'Username', 'Email', 'Points', 'XP', 'Streak Days', 'Last Activity']);
        
        $stmt = $pdo->query("
            SELECT u.full_name, u.username, u.email, s.points, s.xp, s.streak_count, s.last_activity_date 
            FROM students s 
            JOIN users u ON s.user_id = u.id
        ");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, $row);
        }
        
    } elseif ($report_type === 'teacher') {
        fputcsv($output, ['Full Name', 'Email', 'Specialization', 'Bio', 'Created Courses']);
        
        $stmt = $pdo->query("
            SELECT u.full_name, u.email, t.specialization, t.bio, 
                   (SELECT COUNT(*) FROM courses WHERE teacher_id = t.id) AS courses_count 
            FROM teachers t 
            JOIN users u ON t.user_id = u.id
        ");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, $row);
        }
        
    } elseif ($report_type === 'course') {
        fputcsv($output, ['Course Title', 'Category', 'Difficulty', 'Teacher Name', 'Syllabus Subjects', 'Enrollments Count', 'Status']);
        
        $stmt = $pdo->query("
            SELECT c.title, c.category, c.difficulty_level, u.full_name AS teacher_name, 
                   (SELECT COUNT(*) FROM subjects WHERE course_id = c.id) AS subjects_count,
                   (SELECT COUNT(*) FROM course_enrollments WHERE course_id = c.id) AS enrollments_count,
                   c.status
            FROM courses c 
            JOIN teachers t ON c.teacher_id = t.id
            JOIN users u ON t.user_id = u.id
        ");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, $row);
        }
        
    } elseif ($report_type === 'quiz') {
        fputcsv($output, ['Quiz Title', 'Course Context', 'Lesson Link', 'Passing Score (%)', 'Time Limit', 'Total Attempts', 'Pass Rate (%)']);
        
        $stmt = $pdo->query("
            SELECT q.title, c.title AS course_title, l.title AS lesson_title, q.passing_score, q.time_limit_mins,
                   (SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = q.id) AS attempts,
                   (SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = q.id AND passed = 1) AS passes
            FROM quizzes q 
            JOIN courses c ON q.course_id = c.id 
            LEFT JOIN lessons l ON q.lesson_id = l.id
        ");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $attempts = intval($row['attempts']);
            $passes = intval($row['passes']);
            $pass_rate = $attempts > 0 ? round(($passes / $attempts) * 100, 1) : 0;
            
            fputcsv($output, [
                $row['title'], $row['course_title'], $row['lesson_title'] ?: 'General Quiz', 
                $row['passing_score'], $row['time_limit_mins'] . " Mins", $attempts, $pass_rate . "%"
            ]);
        }
    }
    
    fclose($output);
    exit();
}

$page_title = "Administrative Reports";
include_once __DIR__ . '/../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h2 class="font-heading font-weight-bold text-success">Administrative Reports Center</h2>
        <p class="text-secondary">Analyze system metrics, student distributions, course lists, and test passes.</p>
    </div>
    <div class="col-md-4 text-md-end d-flex align-items-end justify-content-md-end">
        <a href="reports.php?type=<?php echo $report_type; ?>&download=1" class="btn btn-success font-weight-bold">
            <i class="bi bi-file-earmark-excel me-1"></i> Download CSV Spreadsheet
        </a>
    </div>
</div>

<!-- Tabs selector -->
<div class="row mb-4">
    <div class="col-12">
        <div class="glass-card py-2 px-3">
            <ul class="nav nav-pills gap-2">
                <li class="nav-item">
                    <a class="nav-link rounded font-heading <?php echo $report_type === 'student' ? 'bg-success text-white active' : 'text-secondary bg-transparent'; ?>" href="reports.php?type=student">
                        <i class="bi bi-people me-1"></i> Students Stats
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded font-heading <?php echo $report_type === 'teacher' ? 'bg-success text-white active' : 'text-secondary bg-transparent'; ?>" href="reports.php?type=teacher">
                        <i class="bi bi-person-workspace me-1"></i> Teachers Stats
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded font-heading <?php echo $report_type === 'course' ? 'bg-success text-white active' : 'text-secondary bg-transparent'; ?>" href="reports.php?type=course">
                        <i class="bi bi-journal-text me-1"></i> Courses Stats
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded font-heading <?php echo $report_type === 'quiz' ? 'bg-success text-white active' : 'text-secondary bg-transparent'; ?>" href="reports.php?type=quiz">
                        <i class="bi bi-question-square me-1"></i> Quiz Stats
                    </a>
                </li>
            </ul>
        </div>
    </div>
</div>

<!-- Report Display Board -->
<div class="row">
    <div class="col-12">
        <div class="glass-card">
            
            <?php if ($report_type === 'student'): 
                $data = $pdo->query("
                    SELECT u.full_name, u.username, u.email, s.points, s.xp, s.streak_count 
                    FROM students s 
                    JOIN users u ON s.user_id = u.id
                ")->fetchAll();
            ?>
                <h5 class="font-heading text-success border-bottom pb-2 mb-3 border-color">Students Performance Distribution</h5>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Points Balance</th>
                                <th>XP Total</th>
                                <th>Streak Days</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data as $row): ?>
                                <tr>
                                    <td><strong class="text-dark-theme-compatible"><?php echo e($row['full_name']); ?></strong></td>
                                    <td>@<?php echo e($row['username']); ?></td>
                                    <td><?php echo e($row['email']); ?></td>
                                    <td><span class="badge bg-warning text-dark"><?php echo $row['points']; ?> Pts</span></td>
                                    <td><span class="badge bg-info"><?php echo $row['xp']; ?> XP</span></td>
                                    <td><span class="badge bg-light text-danger border"><i class="bi bi-fire"></i> <?php echo $row['streak_count']; ?> Days</span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($report_type === 'teacher'): 
                $data = $pdo->query("
                    SELECT u.full_name, u.email, t.specialization, 
                           (SELECT COUNT(*) FROM courses WHERE teacher_id = t.id) AS courses_count 
                    FROM teachers t 
                    JOIN users u ON t.user_id = u.id
                ")->fetchAll();
            ?>
                <h5 class="font-heading text-success border-bottom pb-2 mb-3 border-color">Teachers Registration Directory</h5>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Instructor Name</th>
                                <th>Email</th>
                                <th>Specialization</th>
                                <th>Created Courses</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data as $row): ?>
                                <tr>
                                    <td><strong class="text-dark-theme-compatible"><?php echo e($row['full_name']); ?></strong></td>
                                    <td><?php echo e($row['email']); ?></td>
                                    <td><?php echo e($row['specialization'] ?: 'General'); ?></td>
                                    <td><span class="badge bg-secondary"><?php echo $row['courses_count']; ?> Courses</span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($report_type === 'course'): 
                $data = $pdo->query("
                    SELECT c.title, c.category, c.difficulty_level, u.full_name AS teacher_name, 
                           (SELECT COUNT(*) FROM subjects WHERE course_id = c.id) AS subjects_count,
                           (SELECT COUNT(*) FROM course_enrollments WHERE course_id = c.id) AS enrollments_count,
                           c.status
                    FROM courses c 
                    JOIN teachers t ON c.teacher_id = t.id
                    JOIN users u ON t.user_id = u.id
                ")->fetchAll();
            ?>
                <h5 class="font-heading text-success border-bottom pb-2 mb-3 border-color">Course Enrollment Lists</h5>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Course Title</th>
                                <th>Category</th>
                                <th>Difficulty</th>
                                <th>Instructor</th>
                                <th>Subjects</th>
                                <th>Enrolled Students</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data as $row): ?>
                                <tr>
                                    <td><strong class="text-dark-theme-compatible"><?php echo e($row['title']); ?></strong></td>
                                    <td><span class="badge bg-success"><?php echo e($row['category']); ?></span></td>
                                    <td><span class="badge bg-secondary"><?php echo ucfirst($row['difficulty_level']); ?></span></td>
                                    <td><?php echo e($row['teacher_name']); ?></td>
                                    <td><?php echo $row['subjects_count']; ?></td>
                                    <td><span class="badge bg-light text-secondary border"><?php echo $row['enrollments_count']; ?> Enrolled</span></td>
                                    <td><span class="badge <?php echo $row['status'] === 'published' ? 'bg-info' : 'bg-warning text-dark'; ?>"><?php echo ucfirst($row['status']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($report_type === 'quiz'): 
                $data = $pdo->query("
                    SELECT q.title, c.title AS course_title, q.passing_score, q.time_limit_mins,
                           (SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = q.id) AS attempts,
                           (SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = q.id AND passed = 1) AS passes
                    FROM quizzes q 
                    JOIN courses c ON q.course_id = c.id
                ")->fetchAll();
            ?>
                <h5 class="font-heading text-success border-bottom pb-2 mb-3 border-color">Quiz Evaluation Passes</h5>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Quiz Title</th>
                                <th>Parent Course</th>
                                <th>Passing Score</th>
                                <th>Time Limit</th>
                                <th>Total Attempts</th>
                                <th>Success Pass Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data as $row): 
                                $attempts = intval($row['attempts']);
                                $passes = intval($row['passes']);
                                $pass_rate = $attempts > 0 ? round(($passes / $attempts) * 100, 1) : 0;
                            ?>
                                <tr>
                                    <td><strong class="text-dark-theme-compatible"><?php echo e($row['title']); ?></strong></td>
                                    <td><?php echo e($row['course_title']); ?></td>
                                    <td><span class="badge bg-info text-dark"><?php echo $row['passing_score']; ?>%</span></td>
                                    <td><?php echo $row['time_limit_mins']; ?> mins</td>
                                    <td><?php echo $attempts; ?> attempts</td>
                                    <td>
                                        <span class="badge <?php echo $pass_rate >= 50 ? 'bg-success' : 'bg-danger'; ?>">
                                            <?php echo $pass_rate; ?>%
                                        </span>
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

<?php
include_once __DIR__ . '/../includes/footer.php';
?>
