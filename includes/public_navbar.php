<?php
$current_page = basename($_SERVER['PHP_SELF']);

if (!function_exists('lang_opt_class')) {
    function lang_opt_class(string $lang, string $current): string {
        $base = 'dropdown-item flex items-center rounded-lg px-3 py-2 text-body-sm text-on-surface hover:bg-surface-container-low lang-opt';
        if ($lang === $current) {
            return $base . ' lang-opt-active bg-primary/10 text-primary font-semibold';
        }
        return $base;
    }
}

// Dynamically resolve courses link
$courses_link = BASE_URL . 'login.php';
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role_name'] === 'Admin') {
        $courses_link = BASE_URL . 'admin/courses.php';
    } elseif ($_SESSION['role_name'] === 'Teacher') {
        $courses_link = BASE_URL . 'teacher/courses.php';
    } else {
        $courses_link = BASE_URL . 'student/courses.php';
    }
}
?>
<!-- TopNavBar -->
<header class="fixed top-0 w-full z-50 bg-white/80 backdrop-blur-xl border-b border-outline-variant/20 shadow-sm">
    <div class="flex justify-between items-center px-gutter py-base max-w-container-max mx-auto h-16">
        <div class="flex items-center gap-base">
            <a href="<?php echo BASE_URL; ?>" class="flex items-center gap-2 text-decoration-none">
                <span class="font-headline-sm text-headline-sm font-black text-primary">
                    <?php echo e(__('common.app_name')); ?>
                </span>
            </a>
        </div>
        <nav class="hidden md:flex items-center gap-md">
            <a class="font-body-md text-body-md text-decoration-none <?php echo $current_page === 'courses.php' ? 'text-primary font-bold border-b-2 border-primary py-1 px-2' : 'text-on-surface-variant hover:text-primary transition-colors'; ?>" href="<?php echo $courses_link; ?>"><?php echo e(__('nav.courses')); ?></a>
            <a class="font-body-md text-body-md text-decoration-none <?php echo $current_page === 'methodology.php' ? 'text-primary font-bold border-b-2 border-primary py-1 px-2' : 'text-on-surface-variant hover:text-primary transition-colors'; ?>" href="<?php echo BASE_URL; ?>methodology.php">Methodology</a>
            <a class="font-body-md text-body-md text-decoration-none <?php echo $current_page === 'impact.php' ? 'text-primary font-bold border-b-2 border-primary py-1 px-2' : 'text-on-surface-variant hover:text-primary transition-colors'; ?>" href="<?php echo BASE_URL; ?>impact.php">Impact</a>
            <a class="font-body-md text-body-md text-decoration-none <?php echo $current_page === 'partnerships.php' ? 'text-primary font-bold border-b-2 border-primary py-1 px-2' : 'text-on-surface-variant hover:text-primary transition-colors'; ?>" href="<?php echo BASE_URL; ?>partnerships.php">Partnerships</a>
        </nav>
        <div class="flex items-center gap-md">
            <!-- Language Selector Dropdown -->
            <div class="dropdown" style="width: 155px;" data-active-lang="<?php echo $current_lang; ?>">
                <button class="btn dropdown-toggle w-full flex items-center justify-between border border-outline-variant/30 text-on-surface bg-white hover:bg-surface-container-low px-3 py-1.5 rounded-lg text-body-sm transition-colors font-semibold" type="button" id="langDropdownPublic" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[18px]">language</span>
                        <?php
                            if ($current_lang === 'en')      echo '🇬🇧 English';
                            elseif ($current_lang === 'hi')  echo '🇮🇳 हिन्दी';
                            elseif ($current_lang === 'gu')  echo '🇮🇳 ગુજરાતી';
                        ?>
                    </span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-xl border border-outline-variant/30 p-1.5 rounded-xl mt-2" aria-labelledby="langDropdownPublic" style="background-color: var(--bg-surface); min-width: 160px; z-index: 1050;">
                    <li><a class="<?php echo lang_opt_class('en', $current_lang); ?>" href="#" data-lang="en">🇬🇧 English</a></li>
                    <li><a class="<?php echo lang_opt_class('hi', $current_lang); ?>" href="#" data-lang="hi">🇮🇳 हिन्दी (Hindi)</a></li>
                    <li><a class="<?php echo lang_opt_class('gu', $current_lang); ?>" href="#" data-lang="gu">🇮🇳 ગુજરાતી (Gujarati)</a></li>
                </ul>
            </div>

            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="<?php echo BASE_URL; ?><?php 
                    echo $_SESSION['role_name'] === 'Admin' ? 'admin/dashboard.php' : 
                        ($_SESSION['role_name'] === 'Teacher' ? 'teacher/dashboard.php' : 'student/dashboard.php'); 
                ?>" class="hidden sm:inline-block bg-primary text-white hover:bg-primary-hover px-md py-2 rounded-lg font-label-md text-label-md transition-colors text-decoration-none">
                    <i class="bi bi-speedometer2 me-1"></i> <?php echo e(__('common.dashboard')); ?>
                </a>
            <?php else: ?>
                <a href="<?php echo BASE_URL; ?>login.php" class="hidden sm:inline-block bg-primary text-white hover:bg-primary-hover px-md py-2 rounded-lg font-label-md text-label-md transition-colors text-decoration-none">
                    <i class="bi bi-box-arrow-in-right me-1"></i> <?php echo e(__('common.login')); ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
</header>
