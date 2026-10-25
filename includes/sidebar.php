<?php
$current_page = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role_name'] ?? '';

// Calculate doubt badges
$sidebar_doubt_badge = 0;
if (isset($pdo) && isset($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];
    if ($role === 'Student') {
        $stmt_badge = $pdo->prepare("
            SELECT COUNT(*) 
            FROM doubt_replies dr 
            JOIN doubts d ON dr.doubt_id = d.id 
            JOIN users u ON dr.user_id = u.id 
            JOIN roles r ON u.role_id = r.id 
            WHERE d.student_id = (SELECT id FROM students WHERE user_id = ?) 
              AND r.name = 'Teacher' 
              AND dr.is_read = 0
        ");
        $stmt_badge->execute([$uid]);
        $sidebar_doubt_badge = intval($stmt_badge->fetchColumn());
    } elseif ($role === 'Teacher') {
        $stmt_badge = $pdo->prepare("
            SELECT COUNT(*) 
            FROM doubts d 
            JOIN courses c ON d.course_id = c.id 
            WHERE c.teacher_id = (SELECT id FROM teachers WHERE user_id = ?) 
              AND d.status = 'Pending'
        ");
        $stmt_badge->execute([$uid]);
        $sidebar_doubt_badge = intval($stmt_badge->fetchColumn());
    }
}
?>
<div class="sidebar flex flex-col h-screen w-64 fixed left-0 top-0 bg-surface-container-low border-r border-outline-variant/30 py-md px-sm z-40 transition-all duration-300">
    <a href="<?php echo BASE_URL; ?>" class="brand flex items-center gap-sm px-4 mb-xl text-decoration-none">
        <span class="material-symbols-outlined text-primary text-[32px]">school</span>
        <span class="font-headline-sm text-headline-sm font-bold text-on-surface"><?php echo e(__('common.app_name')); ?></span>
    </a>
    
    <ul class="sidebar-menu flex flex-col gap-1 list-none p-0 m-0">
        <!-- ADMIN LINKS -->
        <?php if ($role === 'Admin'): ?>
            <li>
                <a href="<?php echo BASE_URL; ?>admin/dashboard.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg mx-2 text-decoration-none <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
                    <span class="material-symbols-outlined">dashboard</span>
                    <span><?php echo e(__('common.dashboard')); ?></span>
                </a>
            </li>
            <li>
                <a href="<?php echo BASE_URL; ?>admin/students.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg mx-2 text-decoration-none <?php echo $current_page === 'students.php' ? 'active' : ''; ?>">
                    <span class="material-symbols-outlined">group</span>
                    <span><?php echo e(__('nav.students')); ?></span>
                </a>
            </li>
            <li>
                <a href="<?php echo BASE_URL; ?>admin/teachers.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg mx-2 text-decoration-none <?php echo $current_page === 'teachers.php' ? 'active' : ''; ?>">
                    <span class="material-symbols-outlined">co_present</span>
                    <span><?php echo e(__('nav.teachers')); ?></span>
                </a>
            </li>
            <li>
                <a href="<?php echo BASE_URL; ?>admin/courses.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg mx-2 text-decoration-none <?php echo $current_page === 'courses.php' ? 'active' : ''; ?>">
                    <span class="material-symbols-outlined">menu_book</span>
                    <span><?php echo e(__('nav.courses')); ?></span>
                </a>
            </li>
            <li>
                <a href="<?php echo BASE_URL; ?>admin/subjects.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg mx-2 text-decoration-none <?php echo $current_page === 'subjects.php' ? 'active' : ''; ?>">
                    <span class="material-symbols-outlined">sell</span>
                    <span><?php echo e(__('nav.subjects')); ?></span>
                </a>
            </li>
            <li>
                <a href="<?php echo BASE_URL; ?>admin/lessons.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg mx-2 text-decoration-none <?php echo $current_page === 'lessons.php' ? 'active' : ''; ?>">
                    <span class="material-symbols-outlined">book</span>
                    <span><?php echo e(__('nav.lessons')); ?></span>
                </a>
            </li>
            <li>
                <a href="<?php echo BASE_URL; ?>admin/quizzes.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg mx-2 text-decoration-none <?php echo $current_page === 'quizzes.php' ? 'active' : ''; ?>">
                    <span class="material-symbols-outlined">quiz</span>
                    <span><?php echo e(__('nav.quizzes')); ?></span>
                </a>
            </li>
            <li>
                <a href="<?php echo BASE_URL; ?>admin/badges.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg mx-2 text-decoration-none <?php echo $current_page === 'badges.php' ? 'active' : ''; ?>">
                    <span class="material-symbols-outlined">military_tech</span>
                    <span><?php echo e(__('common.badges')); ?></span>
                </a>
            </li>
            <li>
                <a href="<?php echo BASE_URL; ?>admin/challenges.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg mx-2 text-decoration-none <?php echo $current_page === 'challenges.php' ? 'active' : ''; ?>">
                    <span class="material-symbols-outlined">calendar_today</span>
                    <span>Daily Challenges</span>
                </a>
            </li>
            <li>
                <a href="<?php echo BASE_URL; ?>admin/rewards.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg mx-2 text-decoration-none <?php echo $current_page === 'rewards.php' ? 'active' : ''; ?>">
                    <span class="material-symbols-outlined">redeem</span>
                    <span><?php echo e(__('common.rewards')); ?></span>
                </a>
            </li>
            <li>
                <a href="<?php echo BASE_URL; ?>admin/notifications.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg mx-2 text-decoration-none <?php echo $current_page === 'notifications.php' ? 'active' : ''; ?>">
                    <span class="material-symbols-outlined">campaign</span>
                    <span><?php echo e(__('nav.notifications')); ?></span>
                </a>
            </li>
            <li>
                <a href="<?php echo BASE_URL; ?>admin/reports.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg mx-2 text-decoration-none <?php echo $current_page === 'reports.php' ? 'active' : ''; ?>">
                    <span class="material-symbols-outlined">analytics</span>
                    <span><?php echo e(__('nav.reports')); ?></span>
                </a>
            </li>

        <!-- TEACHER LINKS -->
        <?php elseif ($role === 'Teacher'): ?>
            <li>
                <a href="<?php echo BASE_URL; ?>teacher/dashboard.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg mx-2 text-decoration-none <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
                    <span class="material-symbols-outlined">dashboard</span>
                    <span><?php echo e(__('common.dashboard')); ?></span>
                </a>
            </li>
            <li>
                <a href="<?php echo BASE_URL; ?>teacher/courses.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg mx-2 text-decoration-none <?php echo $current_page === 'courses.php' || $current_page === 'subjects.php' || $current_page === 'lessons.php' ? 'active' : ''; ?>">
                    <span class="material-symbols-outlined">menu_book</span>
                    <span><?php echo e(__('nav.courses')); ?></span>
                </a>
            </li>
            <li>
                <a href="<?php echo BASE_URL; ?>teacher/quizzes.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg mx-2 text-decoration-none <?php echo $current_page === 'quizzes.php' || $current_page === 'questions.php' ? 'active' : ''; ?>">
                    <span class="material-symbols-outlined">quiz</span>
                    <span><?php echo e(__('nav.quizzes')); ?></span>
                </a>
            </li>
            <li>
                <a href="<?php echo BASE_URL; ?>teacher/progress.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg mx-2 text-decoration-none <?php echo $current_page === 'progress.php' ? 'active' : ''; ?>">
                    <span class="material-symbols-outlined">trending_up</span>
                    <span><?php echo e(__('teacher.student_progress')); ?></span>
                </a>
            </li>
            <li>
                <a href="<?php echo BASE_URL; ?>teacher/reports.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg mx-2 text-decoration-none <?php echo $current_page === 'reports.php' ? 'active' : ''; ?>">
                    <span class="material-symbols-outlined">picture_as_pdf</span>
                    <span><?php echo e(__('nav.reports')); ?></span>
                </a>
            </li>
            <li>
                <a href="<?php echo BASE_URL; ?>teacher/doubts.php" class="sidebar-link flex items-center justify-between px-4 py-3 rounded-lg mx-2 text-decoration-none <?php echo $current_page === 'doubts.php' ? 'active' : ''; ?>">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined">question_answer</span>
                        <span>Student Doubts</span>
                    </div>
                    <?php if ($sidebar_doubt_badge > 0): ?>
                        <span class="bg-amber-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full mr-2"><?php echo $sidebar_doubt_badge; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li>
                <a href="<?php echo BASE_URL; ?>teacher/feedback.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg mx-2 text-decoration-none <?php echo $current_page === 'feedback.php' ? 'active' : ''; ?>">
                    <span class="material-symbols-outlined">rate_review</span>
                    <span>Course Feedback</span>
                </a>
            </li>

        <!-- STUDENT LINKS -->
        <?php elseif ($role === 'Student'): ?>
            <li>
                <a href="<?php echo BASE_URL; ?>student/dashboard.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg mx-2 text-decoration-none <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
                    <span class="material-symbols-outlined">dashboard</span>
                    <span><?php echo e(__('common.dashboard')); ?></span>
                </a>
            </li>
            <li>
                <a href="<?php echo BASE_URL; ?>student/courses.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg mx-2 text-decoration-none <?php echo $current_page === 'courses.php' || $current_page === 'course-view.php' || $current_page === 'lesson-view.php' ? 'active' : ''; ?>">
                    <span class="material-symbols-outlined">menu_book</span>
                    <span><?php echo e(__('nav.courses')); ?></span>
                </a>
            </li>
            <li>
                <a href="<?php echo BASE_URL; ?>student/leaderboard.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg mx-2 text-decoration-none <?php echo $current_page === 'leaderboard.php' ? 'active' : ''; ?>">
                    <span class="material-symbols-outlined">leaderboard</span>
                    <span><?php echo e(__('nav.leaderboard')); ?></span>
                </a>
            </li>
            <li>
                <a href="<?php echo BASE_URL; ?>student/challenges.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg mx-2 text-decoration-none <?php echo $current_page === 'challenges.php' ? 'active' : ''; ?>">
                    <span class="material-symbols-outlined">event_available</span>
                    <span><?php echo e(__('nav.challenges')); ?></span>
                </a>
            </li>
            <li>
                <a href="<?php echo BASE_URL; ?>student/achievements.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg mx-2 text-decoration-none <?php echo $current_page === 'achievements.php' ? 'active' : ''; ?>">
                    <span class="material-symbols-outlined">military_tech</span>
                    <span><?php echo e(__('nav.achievements')); ?></span>
                </a>
            </li>
            <li>
                <a href="<?php echo BASE_URL; ?>student/recommendations.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg mx-2 text-decoration-none <?php echo $current_page === 'recommendations.php' ? 'active' : ''; ?>">
                    <span class="material-symbols-outlined">insights</span>
                    <span><?php echo e(__('nav.recommendations')); ?></span>
                </a>
            </li>
            <li>
                <a href="<?php echo BASE_URL; ?>student/doubts.php" class="sidebar-link flex items-center justify-between px-4 py-3 rounded-lg mx-2 text-decoration-none <?php echo $current_page === 'doubts.php' ? 'active' : ''; ?>">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined">question_answer</span>
                        <span>My Doubts</span>
                    </div>
                    <?php if ($sidebar_doubt_badge > 0): ?>
                        <span class="bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full mr-2"><?php echo $sidebar_doubt_badge; ?></span>
                    <?php endif; ?>
                </a>
            </li>
        <?php endif; ?>
        
        <!-- GENERAL PROFILE & LOGOUT -->
        <li><hr class="dropdown-divider border-outline-variant/30 my-3"></li>
        <li>
            <a href="<?php echo BASE_URL; ?>profile.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg mx-2 text-decoration-none <?php echo $current_page === 'profile.php' ? 'active' : ''; ?>">
                <span class="material-symbols-outlined">settings</span>
                <span><?php echo e(__('common.profile')); ?></span>
            </a>
        </li>
        <li>
            <a href="<?php echo BASE_URL; ?>logout.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg mx-2 text-error hover:bg-error-container/20 text-decoration-none">
                <span class="material-symbols-outlined">logout</span>
                <span><?php echo e(__('common.logout')); ?></span>
            </a>
        </li>
    </ul>
</div>
