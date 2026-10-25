<?php
require_once __DIR__ . '/config/translate.php';
$page_title = t('auth.forgot_password_title');
$no_layout = true;
$simple_footer = true;
include_once __DIR__ . '/includes/header.php';
include_once __DIR__ . '/includes/middleware.php';

redirect_if_logged_in();

$simulated_link = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verify_csrf_token($csrf_token)) {
        set_flash_message('danger', __('auth.csrf_failed'));
    } elseif (empty($email)) {
        set_flash_message('danger', __('auth.fill_email'));
    } else {
        // Query email
        $stmt = $pdo->prepare("SELECT id, username FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Generate token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Save to DB
            $update_stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?");
            $update_stmt->execute([$token, $expires, $user['id']]);
            
            log_activity($pdo, $user['id'], 'Password Reset Request', 'Generated password reset token');
            
            set_flash_message('success', __('auth.reset_instructions'));
            
            // Build simulator link
            $simulated_link = BASE_URL . "reset-password.php?token=" . $token;
        } else {
            // Under production we might pretend it was sent, but for demo we show failure
            set_flash_message('danger', __('auth.email_not_found'));
        }
    }
}
?>

<div class="bg-pattern min-h-screen flex items-center justify-center p-md font-body-md text-on-background">
    <main class="w-full max-w-[480px] relative py-12">
        <!-- Decorative Ambient Blurs -->
        <div class="absolute -top-24 -left-24 w-64 h-64 bg-primary/10 rounded-full blur-[100px] pointer-events-none"></div>
        <div class="absolute -bottom-24 -right-24 w-64 h-64 bg-secondary/10 rounded-full blur-[100px] pointer-events-none"></div>
        
        <!-- Forgot Password Card -->
        <section class="glass-panel rounded-xl shadow-xl overflow-hidden p-lg relative z-10">
            <!-- Branding Header -->
            <header class="text-center mb-lg">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-white/60 dark:bg-surface-container-high/60 rounded-xl shadow-sm mb-base">
                    <span class="material-symbols-outlined text-[40px] text-primary" style="font-variation-settings: 'FILL' 1;">key</span>
                </div>
                <h1 class="font-headline-lg text-headline-lg text-on-surface"><?= t('auth.forgot_password_title') ?></h1>
                <p class="font-body-sm text-body-sm text-on-surface-variant mt-xs"><?= t('auth.forgot_subtext') ?></p>
            </header>
            
            <?php echo display_flash_message(); ?>

            <!-- Simulated Link Box (Tailwind style) -->
            <?php if ($simulated_link): ?>
                <div class="bg-amber-500/10 border border-amber-500/30 rounded-lg p-4 my-4 space-y-2 text-amber-800 dark:text-amber-300">
                    <h6 class="font-bold flex items-center gap-1 text-sm">
                        <span class="material-symbols-outlined text-base">developer_mode</span> 
                        [<?= t('auth.simulator_title') ?>]
                    </h6>
                    <p class="text-xs leading-relaxed"><?= t('auth.simulator_desc') ?></p>
                    <a href="<?php echo e($simulated_link); ?>" class="w-full h-10 bg-amber-500 hover:bg-amber-600 text-white font-semibold rounded-lg shadow-md hover:scale-[1.01] transition-all flex items-center justify-center gap-1 text-xs text-decoration-none">
                        <?= t('auth.reset_now') ?>
                        <span class="material-symbols-outlined text-sm">arrow_forward</span>
                    </a>
                </div>
            <?php endif; ?>
            
            <!-- Forgot Password Form -->
            <form action="forgot-password.php" method="POST" class="space-y-md">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo e(generate_csrf_token()); ?>">
                
                <!-- Email Input -->
                <div class="space-y-sm">
                    <label class="font-label-md text-label-md text-on-surface block px-xs" for="email"><?= t('auth.email') ?></label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-outline">mail</span>
                        <input class="w-full h-12 pl-11 pr-4 bg-surface-container-low border border-outline-variant/30 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none text-body-md" id="email" name="email" placeholder="e.g. email@example.com" required type="email"/>
                    </div>
                </div>
                
                <!-- Action Button -->
                <button class="w-full h-12 rounded-lg bg-gradient-to-r from-primary to-secondary text-white font-label-md text-label-md shadow-lg hover:shadow-xl hover:scale-[1.01] active:scale-[0.99] transition-all flex items-center justify-center gap-2 mt-4" type="submit">
                    <?= t('auth.reset_btn') ?>
                    <span class="material-symbols-outlined text-md">envelope</span>
                </button>
            </form>
            
            <!-- Back to Login Link -->
            <footer class="mt-lg pt-md border-t border-outline-variant/20 text-center">
                <p class="font-body-sm text-body-sm text-on-surface-variant">
                    <a class="font-label-md text-label-md text-secondary hover:text-primary transition-colors" href="login.php"><?= t('auth.signin_prompt') ?></a>
                </p>
            </footer>
        </section>
    </main>
</div>

<?php
include_once __DIR__ . '/includes/footer.php';
?>
