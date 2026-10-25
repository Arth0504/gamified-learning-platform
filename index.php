<?php
$page_title = "Welcome";
$no_layout = true; // Use simple landing template without dashboard frames
include_once __DIR__ . '/includes/header.php';
include_once __DIR__ . '/includes/middleware.php';

include_once __DIR__ . '/includes/public_navbar.php';
?>

<main class="flex-grow pt-16">
    <!-- Hero Section -->
    <section class="relative py-xl bg-gradient-to-br from-primary/10 via-secondary/5 to-transparent overflow-hidden">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_70%_120%,rgba(113,42,226,0.15),transparent_50%)]"></div>
        <div class="max-w-container-max mx-auto px-gutter grid grid-cols-1 lg:grid-cols-12 gap-lg items-center relative z-10">
            <div class="lg:col-span-6 space-y-md text-center lg:text-left">
                <span class="inline-block bg-primary/10 text-primary dark:bg-primary/20 dark:text-primary-fixed-dim px-sm py-xs rounded-full font-label-sm text-label-sm uppercase tracking-wider">
                    <?php echo e(__('home.hero_badge')); ?>
                </span>
                <h1 class="font-headline-xl text-headline-xl text-on-background leading-tight">
                    <?php echo e(__('home.hero_title')); ?>
                </h1>
                <p class="font-body-lg text-body-lg text-on-surface-variant max-w-xl mx-auto lg:mx-0">
                    <?php echo e(__('home.hero_subtitle')); ?>
                </p>
                <div class="flex flex-col sm:flex-row gap-md justify-center lg:justify-start pt-base">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="<?php echo BASE_URL; ?><?php 
                            echo $_SESSION['role_name'] === 'Admin' ? 'admin/dashboard.php' : 
                                ($_SESSION['role_name'] === 'Teacher' ? 'teacher/dashboard.php' : 'student/dashboard.php'); 
                        ?>" class="bg-primary text-white hover:bg-primary-hover px-lg py-base rounded-lg font-label-md text-label-md transition-all shadow-lg hover:-translate-y-0.5 text-decoration-none">
                            <i class="bi bi-arrow-right-circle me-2"></i><?php echo e(__('student.continue_learning')); ?>
                        </a>
                    <?php else: ?>
                        <a href="<?php echo BASE_URL; ?>login.php" class="bg-primary text-white hover:bg-primary-hover px-lg py-base rounded-lg font-label-md text-label-md transition-all shadow-lg hover:-translate-y-0.5 text-decoration-none">
                            <i class="bi bi-box-arrow-in-right me-2"></i><?php echo e(__('auth.login_title')); ?>
                        </a>
                        <a href="<?php echo BASE_URL; ?>register.php" class="bg-transparent border border-outline text-on-surface dark:text-white hover:bg-surface-container-low px-lg py-base rounded-lg font-label-md text-label-md transition-all hover:-translate-y-0.5 text-decoration-none">
                            <i class="bi bi-pencil-square me-2"></i><?php echo e(__('auth.register_title')); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="lg:col-span-6 flex justify-center">
                <div class="relative w-full max-w-lg aspect-square">
                    <div class="absolute inset-0 bg-gradient-to-tr from-primary/30 to-secondary/20 rounded-[2rem] blur-3xl opacity-60"></div>
                    <div class="relative w-full h-full bg-white dark:bg-surface-container-highest rounded-[2rem] shadow-2xl p-md border border-outline-variant/30 overflow-hidden transform rotate-1">
                        <img class="w-full h-full object-cover rounded-xl" alt="Students studying with tablets" src="https://lh3.googleusercontent.com/aida-public/AB6AXuB_6ZjFwN_MPeC5bzRIWTibSZIYtv_gXDZeulLNuQqWeTMM8LFSUPSMnR23uFtsK1qw2RMNxM1UxW9B1jdjxHBoUoptuhwbF074kHe5mq5kv6grt7j2Mn01dKw5W2Dlv_pjjrmvci2_nM0QfBD5EAow8t9RToiX5CVlxD6HtP-ZMO3LozxqneNGrZF8wtO4HAAe1oJluFYsuz16RWthjb296FzzalTEnsUqPoxonAKKoCpam3NiRz2J"/>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-xl bg-surface-container-low/20">
        <div class="max-w-container-max mx-auto px-gutter space-y-xl">
            <div class="text-center max-w-2xl mx-auto space-y-xs">
                <span class="font-label-sm text-label-sm text-secondary uppercase tracking-wider"><?php echo e(__('home.features_label')); ?></span>
                <h2 class="font-headline-lg text-headline-lg text-on-surface"><?php echo e(__('home.features_title')); ?></h2>
                <p class="font-body-md text-body-md text-on-surface-variant"><?php echo e(__('home.features_subtitle')); ?></p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-md">
                <div class="glass-card p-md rounded-xl col-span-1 md:col-span-2 group hover:bg-white/60 dark:hover:bg-surface-container-highest/60 transition-all duration-300">
                    <span class="material-symbols-outlined text-primary text-4xl mb-base block" style="font-variation-settings: 'FILL' 1;">cloud_off</span>
                    <h3 class="font-headline-md text-headline-md mb-xs"><?php echo e(__('home.offline_title')); ?></h3>
                    <p class="font-body-md text-body-md text-on-surface-variant mb-md"><?php echo e(__('home.offline_desc')); ?></p>
                    <div class="h-48 rounded-lg overflow-hidden">
                        <img class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" alt="3D cloud sync render" src="https://lh3.googleusercontent.com/aida-public/AB6AXuB_6ZjFwN_MPeC5bzRIWTibSZIYtv_gXDZeulLNuQqWeTMM8LFSUPSMnR23uFtsK1qw2RMNxM1UxW9B1jdjxHBoUoptuhwbF074kHe5mq5kv6grt7j2Mn01dKw5W2Dlv_pjjrmvci2_nM0QfBD5EAow8t9RToiX5CVlxD6HtP-ZMO3LozxqneNGrZF8wtO4HAAe1oJluFYsuz16RWthjb296FzzalTEnsUqPoxonAKKoCpam3NiRz2J"/>
                    </div>
                </div>
                
                <div class="glass-card p-md rounded-xl group hover:bg-white/60 dark:hover:bg-surface-container-highest/60 transition-all duration-300">
                    <span class="material-symbols-outlined text-secondary text-4xl mb-base block" style="font-variation-settings: 'FILL' 1;">psychology</span>
                    <h3 class="font-headline-md text-headline-md mb-xs"><?php echo e(__('home.ai_title')); ?></h3>
                    <p class="font-body-md text-body-md text-on-surface-variant"><?php echo e(__('home.ai_desc')); ?></p>
                </div>
                
                <div class="glass-card p-md rounded-xl group hover:bg-white/60 dark:hover:bg-surface-container-highest/60 transition-all duration-300">
                    <span class="material-symbols-outlined text-tertiary text-4xl mb-base block" style="font-variation-settings: 'FILL' 1;">groups</span>
                    <h3 class="font-headline-md text-headline-md mb-xs"><?php echo e(__('home.hubs_title')); ?></h3>
                    <p class="font-body-md text-body-md text-on-surface-variant"><?php echo e(__('home.hubs_desc')); ?></p>
                </div>
                
                <div class="glass-card p-md rounded-xl col-span-1 md:col-span-2 flex flex-col md:flex-row gap-md group hover:bg-white/60 dark:hover:bg-surface-container-highest/60 transition-all duration-300">
                    <div class="flex-1">
                        <span class="material-symbols-outlined text-primary-container text-4xl mb-base block" style="font-variation-settings: 'FILL' 1;">emoji_events</span>
                        <h3 class="font-headline-md text-headline-md mb-xs"><?php echo e(__('home.quests_title')); ?></h3>
                        <p class="font-body-md text-body-md text-on-surface-variant"><?php echo e(__('home.quests_desc')); ?></p>
                    </div>
                    <div class="w-full md:w-64 h-48 rounded-lg overflow-hidden">
                        <img class="w-full h-full object-cover" alt="Gamification 3D trophy" src="https://lh3.googleusercontent.com/aida-public/AB6AXuA-tbOR7SB3sTDY3eaf0LYA5myCbPK0_iGi5kuVz1iqZ_sTjLYDnCVSnChLu1hn1lUdlEjz0Pz1NBZwbRd_6ZwBlMqD8QfVqWWkFQ-2iK7lw02ShawXOKqgCXrWovSG0-9TM0z62GAoVVs6JnnJnyKOMaxwe2vpfIcVQNbF5gizZTMVjJhwqO9_oYyRz6dfv54wUpzkVvNtGj7tupo1a97gzE98s_DCzfwBHOjLvp4rVEVZziOuT-6d"/>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section class="py-xl bg-surface">
        <div class="max-w-container-max mx-auto px-gutter">
            <div class="flex flex-col md:flex-row justify-between items-end mb-xl gap-md">
                <div class="max-w-xl">
                    <h2 class="font-headline-lg text-headline-lg mb-base"><?php echo e(__('home.journey_title')); ?></h2>
                    <p class="font-body-md text-body-md text-on-surface-variant"><?php echo e(__('home.journey_subtitle')); ?></p>
                </div>
                <a class="text-primary font-label-md text-label-md flex items-center gap-xs group text-decoration-none" href="<?php echo BASE_URL; ?>methodology.php">
                    <?php echo e(__('home.methodology_link')); ?> <span class="material-symbols-outlined group-hover:translate-x-1 transition-transform">arrow_forward</span>
                </a>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-lg relative">
                <!-- Connector Line (Desktop Only) -->
                <div class="hidden md:block absolute top-12 left-0 w-full h-[2px] bg-outline-variant/30 z-0"></div>
                <!-- Step 1 -->
                <div class="relative z-10">
                    <div class="w-16 h-16 rounded-full bg-white dark:bg-surface-container-high border-2 border-primary flex items-center justify-center font-headline-md text-primary mb-md shadow-lg">01</div>
                    <h4 class="font-headline-sm text-headline-sm mb-xs"><?php echo e(__('home.step1_title')); ?></h4>
                    <p class="font-body-sm text-body-sm text-on-surface-variant"><?php echo e(__('home.step1_desc')); ?></p>
                </div>
                <!-- Step 2 -->
                <div class="relative z-10">
                    <div class="w-16 h-16 rounded-full bg-white dark:bg-surface-container-high border-2 border-primary flex items-center justify-center font-headline-md text-primary mb-md shadow-lg">02</div>
                    <h4 class="font-headline-sm text-headline-sm mb-xs"><?php echo e(__('home.step2_title')); ?></h4>
                    <p class="font-body-sm text-body-sm text-on-surface-variant"><?php echo e(__('home.step2_desc')); ?></p>
                </div>
                <!-- Step 3 -->
                <div class="relative z-10">
                    <div class="w-16 h-16 rounded-full bg-white dark:bg-surface-container-high border-2 border-primary flex items-center justify-center font-headline-md text-primary mb-md shadow-lg">03</div>
                    <h4 class="font-headline-sm text-headline-sm mb-xs"><?php echo e(__('home.step3_title')); ?></h4>
                    <p class="font-body-sm text-body-sm text-on-surface-variant"><?php echo e(__('home.step3_desc')); ?></p>
                </div>
                <!-- Step 4 -->
                <div class="relative z-10">
                    <div class="w-16 h-16 rounded-full bg-primary flex items-center justify-center font-headline-md text-on-primary mb-md shadow-lg">04</div>
                    <h4 class="font-headline-sm text-headline-sm mb-xs"><?php echo e(__('home.step4_title')); ?></h4>
                    <p class="font-body-sm text-body-sm text-on-surface-variant"><?php echo e(__('home.step4_desc')); ?></p>
                </div>
            </div>
        </div>
    </section>

    <!-- Statistics Section -->
    <section class="py-xl bg-primary text-on-primary relative overflow-hidden">
        <div class="relative z-10 max-w-container-max mx-auto px-gutter grid grid-cols-2 md:grid-cols-4 gap-xl text-center">
            <div class="space-y-xs">
                <p class="font-headline-xl text-headline-xl">250K+</p>
                <p class="font-label-md text-label-md opacity-80 uppercase"><?php echo e(__('home.stat_students')); ?></p>
            </div>
            <div class="space-y-xs">
                <p class="font-headline-xl text-headline-xl">1.2K+</p>
                <p class="font-label-md text-label-md opacity-80 uppercase"><?php echo e(__('home.stat_schools')); ?></p>
            </div>
            <div class="space-y-xs">
                <p class="font-headline-xl text-headline-xl">450+</p>
                <p class="font-label-md text-label-md opacity-80 uppercase"><?php echo e(__('home.stat_courses')); ?></p>
            </div>
            <div class="space-y-xs">
                <p class="font-headline-xl text-headline-xl">92%</p>
                <p class="font-label-md text-label-md opacity-80 uppercase"><?php echo e(__('home.stat_satisfaction')); ?></p>
            </div>
        </div>
    </section>

    <!-- Testimonials -->
    <section class="py-xl bg-surface-container-low/30">
        <div class="max-w-container-max mx-auto px-gutter">
            <div class="text-center mb-xl">
                <h2 class="font-headline-lg text-headline-lg mb-base"><?php echo e(__('home.testimonials_title')); ?></h2>
                <p class="font-body-md text-body-md text-on-surface-variant max-w-2xl mx-auto"><?php echo e(__('home.testimonials_sub')); ?></p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-md">
                <!-- Testimonial 1 -->
                <div class="bg-white dark:bg-surface-container-high p-md rounded-xl shadow-xl hover:shadow-2xl transition-shadow duration-300 flex flex-col gap-md border border-outline-variant/10">
                    <div class="flex gap-xs text-secondary-container">
                        <span class="material-symbols-outlined text-secondary" style="font-variation-settings: 'FILL' 1;">star</span>
                        <span class="material-symbols-outlined text-secondary" style="font-variation-settings: 'FILL' 1;">star</span>
                        <span class="material-symbols-outlined text-secondary" style="font-variation-settings: 'FILL' 1;">star</span>
                        <span class="material-symbols-outlined text-secondary" style="font-variation-settings: 'FILL' 1;">star</span>
                        <span class="material-symbols-outlined text-secondary" style="font-variation-settings: 'FILL' 1;">star</span>
                    </div>
                    <p class="font-body-md text-body-md italic text-on-surface-variant"><?php echo e(__('home.t1_quote')); ?></p>
                    <div class="flex items-center gap-md mt-auto">
                        <div class="w-12 h-12 rounded-full overflow-hidden">
                            <img class="w-full h-full object-cover" alt="Student portrait Amara K." src="https://lh3.googleusercontent.com/aida-public/AB6AXuAHO9FczYkYJObagXnK9JmvWDZ3o33f2VThZi6XDfbTEW6EmqL-O6_kd_LSc1_cK4QwKUIfgz6DKjLcrX2HeXKbJljs59vtQPBzymPkkath7TMnTbAp-zSszXFE8MONno6wKsmI_XHB-Z24V-96uBjsaA01BK3z4gsohBdqi9XSA4bh27FypDI6rjMIIQlZRo4Ty-u-uEeA048LrU7H6ZIiDWiwT1wk2lcVKps-w1hJ8QMQZSy0mkRo"/>
                        </div>
                        <div>
                            <p class="font-label-md text-label-md text-on-surface"><?php echo e(__('home.t1_author')); ?></p>
                            <p class="font-body-sm text-body-sm text-on-surface-variant"><?php echo e(__('home.t1_role')); ?></p>
                        </div>
                    </div>
                </div>
                <!-- Testimonial 2 -->
                <div class="bg-white dark:bg-surface-container-high p-md rounded-xl shadow-xl hover:shadow-2xl transition-shadow duration-300 flex flex-col gap-md border border-outline-variant/10">
                    <div class="flex gap-xs text-secondary-container">
                        <span class="material-symbols-outlined text-secondary" style="font-variation-settings: 'FILL' 1;">star</span>
                        <span class="material-symbols-outlined text-secondary" style="font-variation-settings: 'FILL' 1;">star</span>
                        <span class="material-symbols-outlined text-secondary" style="font-variation-settings: 'FILL' 1;">star</span>
                        <span class="material-symbols-outlined text-secondary" style="font-variation-settings: 'FILL' 1;">star</span>
                        <span class="material-symbols-outlined text-secondary" style="font-variation-settings: 'FILL' 1;">star</span>
                    </div>
                    <p class="font-body-md text-body-md italic text-on-surface-variant"><?php echo e(__('home.t2_quote')); ?></p>
                    <div class="flex items-center gap-md mt-auto">
                        <div class="w-12 h-12 rounded-full overflow-hidden">
                            <img class="w-full h-full object-cover" alt="Educator portrait Dr. Samuel O." src="https://lh3.googleusercontent.com/aida-public/AB6AXuDbuLgzgWdPcYjJcWDZ_6eKx4qR3OQXBCfYyK6ErKK5M4tvT_ylz9gwBYy_Jjzq1dmPvZNoVGCPu7Dk-N8ioQSOpV4cnWOzG7rEfuBZPe2B2p7VifIhflJEYxSO9Fjj8NRP5HsqMqydJTWBJ92MPQX7i3Qm9s2bNQqOWu2TJsH9Q7goK1iFCiYJaXeoqA6nLArU87Q_k7RVSdkfaz0s5AcUcaQzpiYu1c2KCXYSSeYdWuTpn5SAhmOx"/>
                        </div>
                        <div>
                            <p class="font-label-md text-label-md text-on-surface"><?php echo e(__('home.t2_author')); ?></p>
                            <p class="font-body-sm text-body-sm text-on-surface-variant"><?php echo e(__('home.t2_role')); ?></p>
                        </div>
                    </div>
                </div>
                <!-- Testimonial 3 -->
                <div class="bg-white dark:bg-surface-container-high p-md rounded-xl shadow-xl hover:shadow-2xl transition-shadow duration-300 flex flex-col gap-md border border-outline-variant/10">
                    <div class="flex gap-xs text-secondary-container">
                        <span class="material-symbols-outlined text-secondary" style="font-variation-settings: 'FILL' 1;">star</span>
                        <span class="material-symbols-outlined text-secondary" style="font-variation-settings: 'FILL' 1;">star</span>
                        <span class="material-symbols-outlined text-secondary" style="font-variation-settings: 'FILL' 1;">star</span>
                        <span class="material-symbols-outlined text-secondary" style="font-variation-settings: 'FILL' 1;">star</span>
                        <span class="material-symbols-outlined text-secondary" style="font-variation-settings: 'FILL' 1;">star</span>
                    </div>
                    <p class="font-body-md text-body-md italic text-on-surface-variant"><?php echo e(__('home.t3_quote')); ?></p>
                    <div class="flex items-center gap-md mt-auto">
                        <div class="w-12 h-12 rounded-full overflow-hidden">
                            <img class="w-full h-full object-cover" alt="Parent portrait Lucia M." src="https://lh3.googleusercontent.com/aida-public/AB6AXuAn648Qh1TkI6ZMNrtfOYcw8HhUn1WZOUN2opyMQbkpqsoPxN1gl0a7tR44fjX2Ku2DSc6IgNYznWhn-rpjw7MaaZT9gop0w4JZFJut1oISqJTLOrTTlvHJfqhhIOH3QcoKk3bUz_XmzNg5EHIgCY0DP2JXcuJ9jhArVzvFpRorSezv9HgbBwzhAZhyoQC2pzAW_ZX1rBMbV64_cW7W-G53LI2xNbKx603BYHpaCq8hsKwLJOrnGHMT"/>
                        </div>
                        <div>
                            <p class="font-label-md text-label-md text-on-surface"><?php echo e(__('home.t3_author')); ?></p>
                            <p class="font-body-sm text-body-sm text-on-surface-variant"><?php echo e(__('home.t3_role')); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-xl">
        <div class="max-w-container-max mx-auto px-gutter">
            <div class="bg-inverse-surface rounded-[2rem] p-lg md:p-xl text-center relative overflow-hidden group">
                <div class="absolute inset-0 bg-primary/20 opacity-0 group-hover:opacity-100 transition-opacity duration-700 pointer-events-none"></div>
                <div class="relative z-10 max-w-2xl mx-auto space-y-md">
                    <h2 class="font-headline-xl text-headline-xl text-white"><?php echo e(__('home.cta_title')); ?></h2>
                    <p class="font-body-lg text-body-lg text-white/80"><?php echo e(__('home.cta_subtitle')); ?></p>
                    <div class="flex flex-col sm:flex-row gap-md justify-center pt-md">
                        <?php if (!isset($_SESSION['user_id'])): ?>
                            <a href="<?php echo BASE_URL; ?>register.php" class="bg-white text-primary px-lg py-base rounded-lg font-label-md text-label-md hover:bg-surface-container-low transition-colors shadow-lg text-decoration-none"><?php echo e(__('home.cta_partner')); ?></a>
                        <?php endif; ?>
                        <a href="mailto:support@eduquest.org" class="bg-transparent border border-white/30 text-white px-lg py-base rounded-lg font-label-md text-label-md hover:bg-white/10 transition-colors text-decoration-none"><?php echo e(__('home.cta_contact')); ?></a>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<script>
    // Smooth reveal on scroll interaction
    const observerOptions = {
        threshold: 0.1
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('opacity-100');
                entry.target.classList.remove('opacity-0', 'translate-y-10');
            }
        });
    }, observerOptions);

    document.querySelectorAll('section > div').forEach(el => {
        el.classList.add('transition-all', 'duration-1000', 'opacity-0', 'translate-y-10');
        observer.observe(el);
    });

    // Simple parallax effect for hero image
    window.addEventListener('scroll', () => {
        const scrolled = window.pageYOffset;
        const heroImage = document.querySelector('.transform.rotate-1');
        if (heroImage) {
            heroImage.style.transform = `rotate(1deg) translateY(${scrolled * 0.1}px)`;
        }
    });
</script>

<?php
include_once __DIR__ . '/includes/footer.php';
?>
