<?php
$page_title = "Impact";
$no_layout = true;
include_once __DIR__ . '/includes/header.php';
include_once __DIR__ . '/includes/middleware.php';

include_once __DIR__ . '/includes/public_navbar.php';
?>

<main class="flex-grow pt-24 pb-xl bg-gradient-to-b from-primary/5 to-transparent min-h-screen">
    <div class="max-w-container-max mx-auto px-gutter space-y-xl">
        <!-- Hero Title -->
        <div class="text-center max-w-3xl mx-auto space-y-md py-6">
            <span class="inline-block bg-primary/10 text-primary px-4 py-1.5 rounded-full font-label-sm text-label-sm uppercase tracking-wider">
                <?= t('impact.badge') ?>
            </span>
            <h1 class="font-headline-xl text-3xl sm:text-4xl lg:text-headline-xl text-on-background leading-tight">
                <?= t('impact.title') ?>
            </h1>
            <p class="font-body-lg text-body-lg text-on-surface-variant leading-relaxed">
                <?= t('impact.subtitle') ?>
            </p>
        </div>

        <!-- Student Growth Statistics Row -->
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
            <div class="glass-card text-center p-md rounded-2xl border border-outline-variant/30">
                <span class="material-symbols-outlined text-4xl text-primary mb-xs" style="font-variation-settings: 'FILL' 1;">groups</span>
                <h3 class="font-headline-lg text-headline-lg text-on-surface font-black">25,000+</h3>
                <p class="font-body-sm text-body-sm text-on-surface-variant font-medium"><?= t('impact.stat_students') ?></p>
            </div>
            <div class="glass-card text-center p-md rounded-2xl border border-outline-variant/30">
                <span class="material-symbols-outlined text-4xl text-green-600 mb-xs" style="font-variation-settings: 'FILL' 1;">trending_up</span>
                <h3 class="font-headline-lg text-headline-lg text-on-surface font-black">85%</h3>
                <p class="font-body-sm text-body-sm text-on-surface-variant font-medium"><?= t('impact.stat_improvement') ?></p>
            </div>
            <div class="glass-card text-center p-md rounded-2xl border border-outline-variant/30">
                <span class="material-symbols-outlined text-4xl text-orange-500 mb-xs" style="font-variation-settings: 'FILL' 1;">local_fire_department</span>
                <h3 class="font-headline-lg text-headline-lg text-on-surface font-black">94%</h3>
                <p class="font-body-sm text-body-sm text-on-surface-variant font-medium"><?= t('impact.stat_retention') ?></p>
            </div>
            <div class="glass-card text-center p-md rounded-2xl border border-outline-variant/30">
                <span class="material-symbols-outlined text-4xl text-secondary mb-xs" style="font-variation-settings: 'FILL' 1;">school</span>
                <h3 class="font-headline-lg text-headline-lg text-on-surface font-black">120+</h3>
                <p class="font-body-sm text-body-sm text-on-surface-variant font-medium"><?= t('impact.stat_schools') ?></p>
            </div>
        </section>

        <!-- Detailed Impact Content -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-lg pt-4">
            <!-- Left Grid -->
            <div class="space-y-lg">
                <div class="glass-card p-md rounded-2xl">
                    <h3 class="font-headline-sm text-headline-sm text-primary font-bold mb-xs"><?= t('impact.edu_title') ?></h3>
                    <p class="font-body-sm text-body-sm text-on-surface-variant leading-relaxed">
                        <?= t('impact.edu_desc') ?>
                    </p>
                </div>
                
                <div class="glass-card p-md rounded-2xl">
                    <h3 class="font-headline-sm text-headline-sm text-primary font-bold mb-xs"><?= t('impact.rural_title') ?></h3>
                    <p class="font-body-sm text-body-sm text-on-surface-variant leading-relaxed">
                        <?= t('impact.rural_desc') ?>
                    </p>
                </div>
            </div>

            <!-- Right Grid -->
            <div class="space-y-lg">
                <div class="glass-card p-md rounded-2xl">
                    <h3 class="font-headline-sm text-headline-sm text-secondary font-bold mb-xs"><?= t('impact.gamification_title') ?></h3>
                    <p class="font-body-sm text-body-sm text-on-surface-variant leading-relaxed">
                        <?= t('impact.gamification_desc') ?>
                    </p>
                </div>

                <div class="glass-card p-md rounded-2xl">
                    <h3 class="font-headline-sm text-headline-sm text-secondary font-bold mb-xs"><?= t('impact.vision_title') ?></h3>
                    <p class="font-body-sm text-body-sm text-on-surface-variant leading-relaxed">
                        <?= t('impact.vision_desc') ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
</main>

<?php
include_once __DIR__ . '/includes/footer.php';
?>
