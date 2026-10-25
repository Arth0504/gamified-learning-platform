<?php
// Role-Based Access Control Middlewares

/**
 * Ensures the user is logged in. Redirects to login page if not.
 */
function require_login(): void {
    if (!isset($_SESSION['user_id'])) {
        set_flash_message('danger', 'You must log in to access this page.');
        header("Location: " . BASE_URL . "login.php");
        exit();
    }
}

/**
 * Ensures the logged in user belongs to one of the allowed roles.
 * Expects an array or string of role names (e.g. 'Admin', ['Teacher', 'Admin']).
 */
function require_role($allowed_roles): void {
    require_login();
    
    $user_role = $_SESSION['role_name'] ?? '';
    $allowed = is_array($allowed_roles) ? $allowed_roles : [$allowed_roles];
    
    if (!in_array($user_role, $allowed)) {
        set_flash_message('danger', 'Unauthorized access! You do not have permissions for this page.');
        
        // Redirect based on current user role to avoid infinite loops
        if ($user_role === 'Admin') {
            header("Location: " . BASE_URL . "admin/dashboard.php");
        } elseif ($user_role === 'Teacher') {
            header("Location: " . BASE_URL . "teacher/dashboard.php");
        } elseif ($user_role === 'Student') {
            header("Location: " . BASE_URL . "student/dashboard.php");
        } else {
            header("Location: " . BASE_URL . "login.php");
        }
        exit();
    }
}

/**
 * Redirects logged in users away from login/registration pages directly to their dashboards.
 */
function redirect_if_logged_in(): void {
    if (isset($_SESSION['user_id'])) {
        $user_role = $_SESSION['role_name'] ?? '';
        if ($user_role === 'Admin') {
            header("Location: " . BASE_URL . "admin/dashboard.php");
        } elseif ($user_role === 'Teacher') {
            header("Location: " . BASE_URL . "teacher/dashboard.php");
        } elseif ($user_role === 'Student') {
            header("Location: " . BASE_URL . "student/dashboard.php");
        }
        exit();
    }
}
?>
