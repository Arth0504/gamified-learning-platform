<?php
$page_title = "Partnerships";
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
                <?= t('partnerships.badge') ?>
            </span>
            <h1 class="font-headline-xl text-3xl sm:text-4xl lg:text-headline-xl text-on-background leading-tight">
                <?= t('partnerships.title') ?>
            </h1>
            <p class="font-body-lg text-body-lg text-on-surface-variant leading-relaxed">
                <?= t('partnerships.subtitle') ?>
            </p>
        </div>

        <!-- Partnership options grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-lg">
            <!-- Option 1: Schools -->
            <div class="glass-card flex flex-col justify-between p-md h-full rounded-2xl hover:scale-[1.02] hover:-translate-y-1 transition-all duration-300">
                <div class="space-y-sm">
                    <div class="w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center text-primary">
                        <span class="material-symbols-outlined text-[28px]" style="font-variation-settings: 'FILL' 1;">school</span>
                    </div>
                    <h3 class="font-headline-sm text-headline-sm text-on-surface font-bold"><?= t('partnerships.schools_title') ?></h3>
                    <p class="font-body-sm text-body-sm text-on-surface-variant leading-relaxed">
                        <?= t('partnerships.schools_desc') ?>
                    </p>
                </div>
                <div class="pt-md border-t border-outline-variant/20 mt-md flex justify-between items-center">
                    <a href="mailto:partners@eduquest.org?subject=School%20Partnership" class="text-xs font-bold text-primary text-decoration-none hover:underline"><?= t('partnerships.schools_cta') ?></a>
                </div>
            </div>

            <!-- Option 2: NGOs -->
            <div class="glass-card flex flex-col justify-between p-md h-full rounded-2xl hover:scale-[1.02] hover:-translate-y-1 transition-all duration-300">
                <div class="space-y-sm">
                    <div class="w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center text-primary">
                        <span class="material-symbols-outlined text-[28px]" style="font-variation-settings: 'FILL' 1;">favorite</span>
                    </div>
                    <h3 class="font-headline-sm text-headline-sm text-on-surface font-bold"><?= t('partnerships.ngos_title') ?></h3>
                    <p class="font-body-sm text-body-sm text-on-surface-variant leading-relaxed">
                        <?= t('partnerships.ngos_desc') ?>
                    </p>
                </div>
                <div class="pt-md border-t border-outline-variant/20 mt-md flex justify-between items-center">
                    <a href="mailto:partners@eduquest.org?subject=NGO%20Partnership" class="text-xs font-bold text-primary text-decoration-none hover:underline"><?= t('partnerships.ngos_cta') ?></a>
                </div>
            </div>

            <!-- Option 3: Educational Partners -->
            <div class="glass-card flex flex-col justify-between p-md h-full rounded-2xl hover:scale-[1.02] hover:-translate-y-1 transition-all duration-300">
                <div class="space-y-sm">
                    <div class="w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center text-primary">
                        <span class="material-symbols-outlined text-[28px]" style="font-variation-settings: 'FILL' 1;">import_contacts</span>
                    </div>
                    <h3 class="font-headline-sm text-headline-sm text-on-surface font-bold"><?= t('partnerships.edu_title') ?></h3>
                    <p class="font-body-sm text-body-sm text-on-surface-variant leading-relaxed">
                        <?= t('partnerships.edu_desc') ?>
                    </p>
                </div>
                <div class="pt-md border-t border-outline-variant/20 mt-md flex justify-between items-center">
                    <a href="mailto:partners@eduquest.org?subject=Content%20Partnership" class="text-xs font-bold text-primary text-decoration-none hover:underline"><?= t('partnerships.edu_cta') ?></a>
                </div>
            </div>

            <!-- Option 4: Government Collaboration -->
            <div class="glass-card flex flex-col justify-between p-md h-full rounded-2xl hover:scale-[1.02] hover:-translate-y-1 transition-all duration-300">
                <div class="space-y-sm">
                    <div class="w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center text-primary">
                        <span class="material-symbols-outlined text-[28px]" style="font-variation-settings: 'FILL' 1;">gavel</span>
                    </div>
                    <h3 class="font-headline-sm text-headline-sm text-on-surface font-bold"><?= t('partnerships.gov_title') ?></h3>
                    <p class="font-body-sm text-body-sm text-on-surface-variant leading-relaxed">
                        <?= t('partnerships.gov_desc') ?>
                    </p>
                </div>
                <div class="pt-md border-t border-outline-variant/20 mt-md flex justify-between items-center">
                    <a href="mailto:partners@eduquest.org?subject=Government%20Collaboration" class="text-xs font-bold text-primary text-decoration-none hover:underline"><?= t('partnerships.gov_cta') ?></a>
                </div>
            </div>

            <!-- Option 5: Sponsorship Opportunities -->
            <div class="glass-card flex flex-col justify-between p-md h-full rounded-2xl hover:scale-[1.02] hover:-translate-y-1 transition-all duration-300">
                <div class="space-y-sm">
                    <div class="w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center text-primary">
                        <span class="material-symbols-outlined text-[28px]" style="font-variation-settings: 'FILL' 1;">volunteer_activism</span>
                    </div>
                    <h3 class="font-headline-sm text-headline-sm text-on-surface font-bold"><?= t('partnerships.sponsor_title') ?></h3>
                    <p class="font-body-sm text-body-sm text-on-surface-variant leading-relaxed">
                        <?= t('partnerships.sponsor_desc') ?>
                    </p>
                </div>
                <div class="pt-md border-t border-outline-variant/20 mt-md flex justify-between items-center">
                    <a href="mailto:partners@eduquest.org?subject=CSR%20Sponsorship" class="text-xs font-bold text-primary text-decoration-none hover:underline"><?= t('partnerships.sponsor_cta') ?></a>
                </div>
            </div>
        </div>
    </div>
</main>

<?php
include_once __DIR__ . '/includes/footer.php';
?>
