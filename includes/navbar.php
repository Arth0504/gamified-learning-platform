<?php
$navbar_points = 0;
$navbar_streak = 0;
$has_student_stats = false;

if (isset($_SESSION['user_id']) && $_SESSION['role_name'] === 'Student') {
    $stmt = $pdo->prepare("SELECT points, streak_count FROM students WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $student_info = $stmt->fetch();
    if ($student_info) {
        $navbar_points = $student_info['points'];
        $navbar_streak = $student_info['streak_count'];
        $has_student_stats = true;
    }
}

// Helper: active CSS class for language dropdown items
if (!function_exists('lang_opt_class')) {
    function lang_opt_class(string $lang, string $current): string {
        $base = 'dropdown-item flex items-center rounded-lg px-3 py-2 text-body-sm text-on-surface hover:bg-surface-container-low lang-opt';
        if ($lang === $current) {
            return $base . ' lang-opt-active bg-primary/10 text-primary font-semibold';
        }
        return $base;
    }
}
?>
<nav class="main-navbar flex justify-between items-center bg-white dark:bg-surface-container-high border-b border-outline-variant/30 px-6 py-3 h-16">
    <div class="flex items-center gap-3">
        <!-- Mobile Sidebar Toggler -->
        <button id="toggle-sidebar" class="toggle-sidebar-btn lg:hidden p-2 text-on-surface-variant hover:text-primary transition-colors focus:outline-none" aria-label="Toggle Sidebar">
            <span class="material-symbols-outlined">menu</span>
        </button>
        <span class="navbar-brand font-headline-sm text-headline-sm font-black text-primary dark:text-primary-fixed-dim lg:hidden">
            <?php echo e(__('common.app_name')); ?>
        </span>
    </div>
    
    <div class="navbar-actions flex items-center gap-4">
        <!-- Student Gamified Metrics -->
        <?php if ($has_student_stats): ?>
            <div class="hidden sm:flex items-center gap-3">
                <span class="flex items-center gap-1.5 bg-surface-container-low dark:bg-surface-container-highest px-3 py-1 rounded-full font-label-md text-label-md text-on-surface" title="<?php echo e(__('student.streak_status')); ?>">
                    <span class="material-symbols-outlined text-orange-500 fill-icon" style="font-variation-settings: 'FILL' 1;">local_fire_department</span>
                    <?php echo e(__('common.streak_days', ['days' => $navbar_streak])); ?>
                </span>
                <span class="flex items-center gap-1.5 bg-surface-container-low dark:bg-surface-container-highest px-3 py-1 rounded-full font-label-md text-label-md text-on-surface" title="<?php echo e(__('student.points_balance')); ?>">
                    <span class="material-symbols-outlined text-yellow-500 fill-icon" style="font-variation-settings: 'FILL' 1;">stars</span>
                    <?php echo e($navbar_points); ?> <?php echo e(__('common.points')); ?>
                </span>
            </div>
        <?php endif; ?>

        <!-- Language Selector Dropdown -->
        <div class="dropdown" style="width: 155px;" data-active-lang="<?php echo $current_lang; ?>">
            <button class="btn dropdown-toggle w-full flex items-center justify-between border border-outline-variant/30 text-on-surface bg-white hover:bg-surface-container-low px-3 py-1.5 rounded-lg text-body-sm transition-colors font-semibold" type="button" id="langDropdownDashboard" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[18px]">language</span>
                    <?php
                        if ($current_lang === 'en')      echo '🇬🇧 English';
                        elseif ($current_lang === 'hi')  echo '🇮🇳 हिन्दी';
                        elseif ($current_lang === 'gu')  echo '🇮🇳 ગુજરાતી';
                    ?>
                </span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-xl border border-outline-variant/30 p-1.5 rounded-xl mt-2" aria-labelledby="langDropdownDashboard" style="background-color: var(--bg-surface); min-width: 165px; z-index: 1050;">
                <li><a class="<?php echo lang_opt_class('en', $current_lang); ?>" href="#" data-lang="en">🇬🇧 English</a></li>
                <li><a class="<?php echo lang_opt_class('hi', $current_lang); ?>" href="#" data-lang="hi">🇮🇳 हिन्दी (Hindi)</a></li>
                <li><a class="<?php echo lang_opt_class('gu', $current_lang); ?>" href="#" data-lang="gu">🇮🇳 ગુજરાતી (Gujarati)</a></li>
            </ul>
        </div>



        <!-- Profile Dropdown -->
        <?php if (isset($_SESSION['user_id'])): ?>
            <div class="dropdown relative">
                <a class="flex items-center text-decoration-none dropdown-toggle text-primary focus:outline-none" href="#" role="button" id="profileDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="bg-primary text-white rounded-full flex items-center justify-center font-bold" style="width: 38px; height: 38px; font-size: 15px;">
                        <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                    </div>
                    <span class="ms-2 hidden sm:inline font-label-md text-label-md text-on-surface dark:text-white"><?php echo e($_SESSION['full_name']); ?></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow-xl border border-outline-variant/30 mt-2 p-2 rounded-xl" aria-labelledby="profileDropdown" style="background-color: var(--bg-surface); min-width: 180px;">
                    <li>
                        <div class="dropdown-header text-xs text-on-surface-variant px-3 py-1 uppercase tracking-wider font-semibold">
                            Role: <?php echo e($_SESSION['role_name']); ?>
                        </div>
                    </li>
                    <li><hr class="dropdown-divider my-1 border-outline-variant/30"></li>
                    <li>
                        <a class="dropdown-item flex items-center gap-2 rounded-lg px-3 py-2 text-body-sm text-on-surface hover:bg-surface-container-low transition-colors" href="<?php echo BASE_URL; ?>profile.php">
                            <span class="material-symbols-outlined text-[18px]">person</span>
                            <?php echo e(__('common.profile')); ?>
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item flex items-center gap-2 rounded-lg px-3 py-2 text-body-sm text-error hover:bg-error-container/20 transition-colors" href="<?php echo BASE_URL; ?>logout.php">
                            <span class="material-symbols-outlined text-[18px]">logout</span>
                            <?php echo e(__('common.logout')); ?>
                        </a>
                    </li>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</nav>
