<?php
require_once __DIR__ . '/config/translate.php';
$page_title = t('common.register');
$no_layout = true;
$simple_footer = true;
include_once __DIR__ . '/includes/header.php';
include_once __DIR__ . '/includes/middleware.php';

redirect_if_logged_in();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verify_csrf_token($csrf_token)) {
        set_flash_message('danger', __('auth.csrf_failed'));
    } elseif (empty($full_name) || empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        set_flash_message('danger', __('auth.fill_all_fields'));
    } elseif ($password !== $confirm_password) {
        set_flash_message('danger', __('auth.passwords_dont_match'));
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        set_flash_message('danger', __('auth.invalid_email'));
    } else {
        try {
            // Check if username already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                set_flash_message('danger', __('auth.username_taken'));
            } else {
                // Check if email already exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    set_flash_message('danger', __('auth.email_taken'));
                } else {
                    // Start database transaction
                    $pdo->beginTransaction();
                    
                    // Insert into users
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $role_student_id = 3; // Student role is 3
                    
                    $stmt_user = $pdo->prepare("INSERT INTO users (role_id, username, email, password_hash, full_name, status) VALUES (?, ?, ?, ?, ?, 'active')");
                    $stmt_user->execute([$role_student_id, $username, $email, $hashed_password, $full_name]);
                    $new_user_id = $pdo->lastInsertId();
                    
                    // Insert into students
                    $stmt_student = $pdo->prepare("INSERT INTO students (user_id, points, xp, streak_count) VALUES (?, 0, 0, 0)");
                    $stmt_student->execute([$new_user_id]);
                    
                    // Log registration activity
                    log_activity($pdo, $new_user_id, 'Register', 'New student registered successfully');
                    
                    $pdo->commit();
                    
                    set_flash_message('success', __('auth.register_success'));
                    header("Location: " . BASE_URL . "login.php");
                    exit();
                }
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            set_flash_message('danger', __('auth.register_failed') . $e->getMessage());
        }
    }
}
?>

<div class="bg-pattern min-h-screen flex flex-col font-body-md text-on-surface">
    <!-- Registration Layout Canvas -->
    <main class="flex-grow flex items-center justify-center px-gutter py-xl">
        <div class="w-full max-w-[1000px] grid md:grid-cols-2 glass-card rounded-xl overflow-hidden min-h-[640px] shadow-2xl bg-white/40 dark:bg-surface-container-highest/20 backdrop-blur-xl border border-white/20">
            <!-- Left Side: Visual/Context -->
            <div class="hidden md:flex flex-col justify-between p-lg relative bg-primary text-on-primary">
                <div class="absolute inset-0 opacity-20">
                    <img class="w-full h-full object-cover" alt="Geometric lines abstract background" src="https://lh3.googleusercontent.com/aida-public/AB6AXuBVXYu7qZQIHLzNEIjFQ8lU_QfgQrVBZYvY-LbMqQeazo1fYGiMfwlemPaJoHuS49TsCLmusVoKBhAJFmVwiN0VOFxwYd3mVqwVsUk7_wKbS5k8JYMPwcqU_fiqP-t9N4lzNXRT3byjnDgJ8gZf2YEN4jEAhEUSMVt7YXEjabNHPBUjbrmAmF85YkpFyDtfwUZSRcgaTC2JrOrYsXgw9fwXy79hvhrsVDL61LNIV7CBCIcGSzHNSJ1N"/>
                </div>
                <div class="relative z-10 space-y-md">
                    <div class="flex items-center gap-sm">
                        <span class="material-symbols-outlined text-[32px]">school</span>
                        <span class="font-headline-md text-headline-md font-bold tracking-tight"><?= t('common.app_name') ?></span>
                    </div>
                    <h2 class="font-headline-lg text-headline-lg mb-base leading-tight"><?= t('register.panel_title') ?></h2>
                    <p class="font-body-lg text-body-lg opacity-90 max-w-sm"><?= t('register.panel_subtitle') ?></p>
                </div>
                <div class="relative z-10">
                    <div class="flex flex-col gap-md">
                        <div class="flex items-start gap-sm">
                            <span class="material-symbols-outlined bg-white/10 p-base rounded-lg">verified_user</span>
                            <div>
                                <p class="font-label-md text-label-md"><?= t('register.quality_title') ?></p>
                                <p class="font-body-sm text-body-sm opacity-70"><?= t('register.quality_desc') ?></p>
                            </div>
                        </div>
                        <div class="flex items-start gap-sm">
                            <span class="material-symbols-outlined bg-white/10 p-base rounded-lg">public</span>
                            <div>
                                <p class="font-label-md text-label-md"><?= t('register.access_title') ?></p>
                                <p class="font-body-sm text-body-sm opacity-70"><?= t('register.access_desc') ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Side: Registration Form -->
            <div class="p-md md:p-lg flex flex-col bg-surface-container-lowest/40 dark:bg-surface-container-highest/10">
                <div class="mb-lg">
                    <div class="flex flex-wrap justify-between items-baseline gap-x-4 gap-y-2 mb-sm">
                        <h1 class="font-headline-md text-headline-md text-on-surface font-bold"><?= t('auth.register_title') ?></h1>
                        <a class="text-primary font-label-md text-label-md hover:underline text-decoration-none font-bold" href="login.php"><?= t('auth.login_link') ?></a>
                    </div>
                    <p class="text-on-surface-variant font-body-sm text-body-sm"><?= t('auth.signup_subtext') ?></p>
                </div>
                
                <?php echo display_flash_message(); ?>
                
                <!-- Registration Form -->
                <form action="register.php" method="POST" class="flex-grow flex flex-col gap-4">
                    <!-- CSRF Token -->
                    <input type="hidden" name="csrf_token" value="<?php echo e(generate_csrf_token()); ?>">
                    
                    <!-- Full Name -->
                    <div>
                        <label class="font-label-md text-label-md text-on-surface-variant block mb-2" for="full_name"><?= t('auth.full_name') ?></label>
                        <div class="relative">
                            <input class="w-full h-12 pl-11 pr-4 bg-surface-container-low border border-outline-variant/30 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none text-body-md" id="full_name" name="full_name" placeholder="Ramesh Patel" required type="text"/>
                            <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-outline-variant">badge</span>
                        </div>
                    </div>
                    
                    <!-- Username -->
                    <div>
                        <label class="font-label-md text-label-md text-on-surface-variant block mb-2" for="username"><?= t('auth.username') ?></label>
                        <div class="relative">
                            <input class="w-full h-12 pl-11 pr-4 bg-surface-container-low border border-outline-variant/30 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none text-body-md" id="username" name="username" placeholder="ramesh123" required type="text"/>
                            <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-outline-variant">person</span>
                        </div>
                    </div>
                    
                    <!-- Email Address -->
                    <div>
                        <label class="font-label-md text-label-md text-on-surface-variant block mb-2" for="emailInput"><?= t('auth.email') ?></label>
                        <div class="relative">
                            <input class="w-full h-12 pl-11 pr-11 bg-surface-container-low border border-outline-variant/30 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none text-body-md" id="emailInput" name="email" oninput="validateEmail(this.value)" placeholder="ramesh@gmail.com" required type="email"/>
                            <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-outline-variant">mail</span>
                            <span class="material-symbols-outlined absolute right-3.5 top-1/2 -translate-y-1/2 hidden" id="emailStatus"></span>
                        </div>
                    </div>
                    
                    <!-- Password Inputs Row -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="font-label-md text-label-md text-on-surface-variant block mb-2" for="password"><?= t('auth.password') ?></label>
                            <div class="relative">
                                <input class="w-full h-12 pl-11 pr-4 bg-surface-container-low border border-outline-variant/30 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none text-body-md" id="password" name="password" placeholder="••••••••" required type="password"/>
                                <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-outline-variant">lock</span>
                            </div>
                        </div>
                        <div>
                            <label class="font-label-md text-label-md text-on-surface-variant block mb-2" for="confirm_password"><?= t('auth.confirm_password') ?></label>
                            <div class="relative">
                                <input class="w-full h-12 pl-11 pr-4 bg-surface-container-low border border-outline-variant/30 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none text-body-md" id="confirm_password" name="confirm_password" placeholder="••••••••" required type="password"/>
                                <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-outline-variant">shield_check</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-2">
                        <label class="flex items-center gap-2 cursor-pointer group">
                             <input class="w-5 h-5 rounded border-outline-variant text-primary focus:ring-primary/20" required type="checkbox"/>
                            <span class="text-body-sm text-on-surface-variant group-hover:text-on-surface transition-colors"><?= t('auth.terms_agree') ?></span>
                        </label>
                    </div>
                    
                    <button class="w-full h-12 rounded-lg bg-gradient-to-r from-primary to-secondary text-white font-label-md text-label-md shadow-lg hover:shadow-xl hover:scale-[1.01] active:scale-[0.99] transition-all flex items-center justify-center gap-2 mt-4" type="submit">
                        <?= t('common.register') ?>
                        <span class="material-symbols-outlined text-md font-black">person_add</span>
                    </button>
                </form>
            </div>
        </div>
    </main>
</div>

<script>
function validateEmail(email) {
    const status = document.getElementById('emailStatus');
    const input = document.getElementById('emailInput');
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    
    if (email.length === 0) {
        status.classList.add('hidden');
        input.classList.remove('ring-error/20', 'ring-2');
        return;
    }

    status.classList.remove('hidden');
    if (re.test(email)) {
        status.textContent = 'check_circle';
        status.className = 'material-symbols-outlined absolute right-sm top-1/2 -translate-y-1/2 text-success-container text-green-500';
        input.classList.remove('ring-error/20');
        input.classList.add('ring-green-500/10');
    } else {
        status.textContent = 'error';
        status.className = 'material-symbols-outlined absolute right-sm top-1/2 -translate-y-1/2 text-error';
        input.classList.remove('ring-green-500/10');
        input.classList.add('ring-error/20', 'ring-2');
    }
}

// micro-interaction for form movement
document.querySelectorAll('input').forEach(input => {
    input.addEventListener('focus', () => {
        input.parentElement.classList.add('scale-[1.01]');
    });
    input.addEventListener('blur', () => {
        input.parentElement.classList.remove('scale-[1.01]');
    });
});
</script>

<?php
include_once __DIR__ . '/includes/footer.php';
?>
