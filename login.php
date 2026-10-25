<?php
require_once __DIR__ . '/config/translate.php';
$page_title = t('common.login');
$no_layout = true;
$simple_footer = true;
include_once __DIR__ . '/includes/header.php';
include_once __DIR__ . '/includes/middleware.php';

redirect_if_logged_in();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username_email = trim($_POST['username_email'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verify_csrf_token($csrf_token)) {
        set_flash_message('danger', __('auth.csrf_failed'));
    } elseif (empty($username_email) || empty($password)) {
        set_flash_message('danger', __('auth.fill_all_fields'));
    } else {
        // Query database
        $stmt = $pdo->prepare("SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.username = ? OR u.email = ?");
        $stmt->execute([$username_email, $username_email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            if ($user['status'] !== 'active') {
                set_flash_message('danger', __('auth.deactivated'));
            } else {
                // Initialize session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role_name'] = $user['role_name'];
                $_SESSION['role_id'] = $user['role_id'];
                
                log_activity($pdo, $user['id'], 'Login', 'User logged in successfully');
                
                // Gamification Streak Calculation for Student logins
                if ($user['role_name'] === 'Student') {
                    $stmt_student = $pdo->prepare("SELECT id, streak_count, last_activity_date FROM students WHERE user_id = ?");
                    $stmt_student->execute([$user['id']]);
                    $student = $stmt_student->fetch();
                    
                    if ($student) {
                        $today = date('Y-m-d');
                        $last_active = $student['last_activity_date'];
                        $streak = $student['streak_count'];
                        
                        if ($last_active) {
                            $yesterday = date('Y-m-d', strtotime('-1 day'));
                            if ($last_active === $today) {
                                // Logged in today already, streak remains unchanged
                            } elseif ($last_active === $yesterday) {
                                $streak++;
                            } else {
                                $streak = 1;
                            }
                        } else {
                            $streak = 1;
                        }
                        
                        // Save changes to student record
                        $update_stmt = $pdo->prepare("UPDATE students SET streak_count = ?, last_activity_date = ? WHERE id = ?");
                        $update_stmt->execute([$streak, $today, $student['id']]);
                        
                        // Check if student badges need unlocking (Consistent Scholar = 3 day streak)
                        $badge_stmt = $pdo->prepare("SELECT * FROM badges WHERE criteria_type = 'streak' AND criteria_value <= ?");
                        $badge_stmt->execute([$streak]);
                        $badges = $badge_stmt->fetchAll();
                        
                        foreach ($badges as $b) {
                            $check_sb = $pdo->prepare("SELECT id FROM student_badges WHERE student_id = ? AND badge_id = ?");
                            $check_sb->execute([$student['id'], $b['id']]);
                            if (!$check_sb->fetch()) {
                                $award_stmt = $pdo->prepare("INSERT INTO student_badges (student_id, badge_id) VALUES (?, ?)");
                                $award_stmt->execute([$student['id'], $b['id']]);
                                
                                // Notification
                                $notif_stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
                                $notif_stmt->execute([
                                    $user['id'], 
                                    'New Badge Earned!', 
                                    "You unlocked the badge: " . $b['name'] . "!", 
                                    'badge'
                                ]);
                            }
                        }
                    }
                }
                
                set_flash_message('success', __('auth.login_success'));
                
                // Role dashboards redirection
                if ($user['role_name'] === 'Admin') {
                    header("Location: " . BASE_URL . "admin/dashboard.php");
                } elseif ($user['role_name'] === 'Teacher') {
                    header("Location: " . BASE_URL . "teacher/dashboard.php");
                } else {
                    header("Location: " . BASE_URL . "student/dashboard.php");
                }
                exit();
            }
        } else {
            set_flash_message('danger', __('auth.invalid_credentials'));
        }
    }
}
?>

<div class="bg-pattern min-h-screen flex items-center justify-center p-md font-body-md text-on-background">
    <main class="w-full max-w-[480px] relative py-12">
        <!-- Decorative Ambient Blurs -->
        <div class="absolute -top-24 -left-24 w-64 h-64 bg-primary/10 rounded-full blur-[100px] pointer-events-none"></div>
        <div class="absolute -bottom-24 -right-24 w-64 h-64 bg-secondary/10 rounded-full blur-[100px] pointer-events-none"></div>
        
        <!-- Login Card -->
        <section class="glass-panel rounded-xl shadow-xl overflow-hidden p-lg relative z-10">
            <!-- Branding Header -->
            <header class="text-center mb-xl">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-white/60 dark:bg-surface-container-high/60 rounded-xl shadow-sm mb-base">
                    <span class="material-symbols-outlined text-[40px] text-primary" style="font-variation-settings: 'FILL' 1;">school</span>
                </div>
                <h1 class="font-headline-lg text-headline-lg text-on-surface"><?= t('common.app_name') ?></h1>
                <p class="font-body-sm text-body-sm text-on-surface-variant mt-xs"><?= t('auth.empowering_tagline') ?></p>
            </header>
            
            <?php echo display_flash_message(); ?>
            
            <!-- Login Form -->
            <form action="login.php" method="POST" class="space-y-4">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo e(generate_csrf_token()); ?>">
                
                <!-- Email / Username Input -->
                <div>
                    <label class="font-label-md text-label-md text-on-surface block px-xs mb-2" for="username_email"><?= t('auth.username') ?> / <?= t('auth.email') ?></label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-outline">person</span>
                        <input class="w-full h-12 pl-11 pr-4 bg-surface-container-low border border-outline-variant/30 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none text-body-md" id="username_email" name="username_email" placeholder="e.g. student" required type="text"/>
                    </div>
                </div>
                
                <!-- Password Input -->
                <div>
                    <div class="flex flex-wrap justify-between items-center gap-x-2 gap-y-1 mb-2">
                        <label class="font-label-md text-label-md text-on-surface block px-xs" for="password"><?= t('auth.password') ?></label>
                        <a class="font-label-sm text-label-sm text-primary hover:text-secondary transition-colors" href="<?php echo BASE_URL; ?>forgot-password.php"><?= t('auth.forgot_password') ?></a>
                    </div>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-outline">lock</span>
                        <input class="w-full h-12 pl-11 pr-11 bg-surface-container-low border border-outline-variant/30 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none text-body-md" id="password" name="password" placeholder="••••••••" required type="password"/>
                        <button class="absolute right-3.5 top-1/2 -translate-y-1/2 text-outline hover:text-primary transition-colors flex items-center justify-center" id="togglePassword" type="button">
                            <span class="material-symbols-outlined">visibility</span>
                        </button>
                    </div>
                </div>
                
                <!-- Action Button -->
                <button class="w-full h-12 rounded-lg bg-gradient-to-r from-primary to-secondary text-white font-label-md text-label-md shadow-lg hover:shadow-xl hover:scale-[1.01] active:scale-[0.99] transition-all flex items-center justify-center gap-2 mt-6" type="submit">
                    <?= t('common.login') ?>
                    <span class="material-symbols-outlined text-md">login</span>
                </button>
            </form>
            
            <!-- Registration Link -->
            <footer class="mt-lg pt-md border-t border-outline-variant/20 text-center">
                <p class="font-body-sm text-body-sm text-on-surface-variant flex flex-wrap justify-center items-center gap-x-1.5 gap-y-1">
                    <span><?= t('auth.signup_prompt') ?></span>
                    <a class="font-label-md text-label-md text-secondary hover:text-primary transition-colors font-bold" href="register.php"><?= t('auth.register_title') ?></a>
                </p>
            </footer>
        </section>
    </main>
</div>

<!-- Password Visibility Toggler Script -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    const togglePassword = document.querySelector('#togglePassword');
    const passwordInput = document.querySelector('#password');

    if (togglePassword && passwordInput) {
        togglePassword.addEventListener('click', () => {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            const icon = togglePassword.querySelector('span');
            if (type === 'text') {
                icon.textContent = 'visibility_off';
            } else {
                icon.textContent = 'visibility';
            }
        });
    }
});
</script>

<?php
include_once __DIR__ . '/includes/footer.php';
?>
