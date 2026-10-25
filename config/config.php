<?php
ob_start();
// Secure session initialization
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax'
    ]);
}

// Inactivity Session Timeout Logic (30 minutes = 1800 seconds)
define('SESSION_TIMEOUT', 1800);
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        // Preserve language selection before session wipe
        $_saved_lang = $_SESSION['lang'] ?? ($_COOKIE['rural_learn_lang'] ?? 'en');

        // Invalidate session
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        
        // Restart clean session for flash notification
        session_start([
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax'
        ]);
        // Restore language into the new session
        $_SESSION['lang'] = $_saved_lang;
        $_SESSION['flash_message'] = [
            'type' => 'warning',
            'message' => 'Your session has expired due to inactivity. Please log in again.'
        ];
        
        $current_script = basename($_SERVER['PHP_SELF']);
        $public_pages = ['login.php', 'register.php', 'forgot-password.php', 'reset-password.php', 'logout.php', 'index.php'];
        if (!in_array($current_script, $public_pages)) {
            header("Location: /gamified learning platform/login.php");
            exit();
        }
    } else {
        $_SESSION['last_activity'] = time();
    }
}

// Global Definitions
define('BASE_URL', '/gamified learning platform/');

// XSS Protection Helper
function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// CSRF Generation and Verification Helpers
function generate_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token(?string $token): bool {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Activity Logging Helper
function log_activity($pdo, ?int $userId, string $action, ?string $details = null): bool {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        return $stmt->execute([$userId, $action, $details, $ip]);
    } catch (Exception $e) {
        // Fail silently to avoid breaking execution if logging fails
        return false;
    }
}

// Flash Messages
function set_flash_message(string $type, string $message): void {
    $_SESSION['flash_message'] = [
        'type' => $type, // 'success', 'danger', 'info', 'warning'
        'message' => $message
    ];
}

function display_flash_message(): string {
    if (!isset($_SESSION['flash_message'])) {
        return '';
    }
    $flash = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
    
    return '
    <div class="alert alert-' . e($flash['type']) . ' alert-dismissible fade show" role="alert">
        ' . e($flash['message']) . '
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>';
}
?>
