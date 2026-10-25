<?php
$page_title = "Reset Password";
$no_layout = true;
$simple_footer = true;
include_once __DIR__ . '/includes/header.php';
include_once __DIR__ . '/includes/middleware.php';

redirect_if_logged_in();

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');

if (empty($token)) {
    set_flash_message('danger', 'Invalid or missing password reset token.');
    header("Location: login.php");
    exit();
}

// Find user with active token
$stmt = $pdo->prepare("SELECT id, username FROM users WHERE reset_token = ? AND reset_token_expires > NOW()");
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user) {
    set_flash_message('danger', 'The reset token is invalid or has expired.');
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verify_csrf_token($csrf_token)) {
        set_flash_message('danger', 'CSRF validation failed.');
    } elseif (empty($password) || empty($confirm_password)) {
        set_flash_message('danger', 'Please enter all fields.');
    } elseif ($password !== $confirm_password) {
        set_flash_message('danger', __('auth.passwords_dont_match'));
    } else {
        // Hash and update
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        
        $update_stmt = $pdo->prepare("UPDATE users SET password_hash = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?");
        $update_stmt->execute([$hashed, $user['id']]);
        
        log_activity($pdo, $user['id'], 'Password Reset Success', 'Successfully reset user password');
        
        set_flash_message('success', 'Your password has been successfully reset! You can now login.');
        header("Location: login.php");
        exit();
    }
}
?>

<div class="container py-5 d-flex justify-content-center align-items-center" style="min-height: 80vh;">
    <div class="card glass-card" style="width: 100%; max-width: 450px; padding: 2rem;">
        <div class="text-center mb-4">
            <a href="<?php echo BASE_URL; ?>" class="text-decoration-none text-success">
                <i class="bi bi-shield-lock-fill fs-1"></i>
            </a>
            <h2 class="font-heading font-weight-bold text-success mt-2">Enter New Password</h2>
            <p class="text-secondary small">Choose a strong password for your account, @<?php echo e($user['username']); ?>.</p>
        </div>
        
        <?php echo display_flash_message(); ?>
        
        <form action="reset-password.php" method="POST">
            <!-- CSRF Token -->
            <input type="hidden" name="csrf_token" value="<?php echo e(generate_csrf_token()); ?>">
            
            <!-- Hidden Token -->
            <input type="hidden" name="token" value="<?php echo e($token); ?>">
            
            <div class="mb-3">
                <label for="password" class="form-label font-heading"><?php echo e(__('auth.password')); ?></label>
                <div class="input-group">
                    <span class="input-group-text bg-transparent border-end-0 border-color"><i class="bi bi-lock text-success"></i></span>
                    <input type="password" class="form-control border-start-0" id="password" name="password" required>
                </div>
            </div>

            <div class="mb-3">
                <label for="confirm_password" class="form-label font-heading"><?php echo e(__('auth.confirm_password')); ?></label>
                <div class="input-group">
                    <span class="input-group-text bg-transparent border-end-0 border-color"><i class="bi bi-shield-check text-success"></i></span>
                    <input type="password" class="form-control border-start-0" id="confirm_password" name="confirm_password" required>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary w-100 py-2.5 mt-2">
                <i class="bi bi-check-circle me-1"></i> Reset Password
            </button>
        </form>
    </div>
</div>

<?php
include_once __DIR__ . '/includes/footer.php';
?>
