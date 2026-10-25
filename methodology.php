<?php
$page_title = "Methodology";
$no_layout = true;
include_once __DIR__ . '/includes/header.php';
include_once __DIR__ . '/includes/middleware.php';
include_once __DIR__ . '/includes/public_navbar.php';
?>

<style>
/* ─── Page Background ─────────────────────────── */
.mpage-bg {
    min-height: 100vh;
    background: linear-gradient(160deg, #f0f4ff 0%, #faf8ff 50%, #f5f0ff 100%);
    padding-top: 96px;
    padding-bottom: 80px;
}

/* ─── Hero ────────────────────────────────────── */
.mpage-hero {
    text-align: center;
    max-width: 640px;
    margin: 0 auto 64px;
    padding: 0 24px;
}
.mpage-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    background: linear-gradient(135deg, rgba(0,74,198,0.10), rgba(113,42,226,0.08));
    border: 1px solid rgba(0,74,198,0.16);
    color: #004ac6;
    padding: 6px 18px;
    border-radius: 9999px;
    font-size: 11.5px;
    font-weight: 700;
    letter-spacing: 0.07em;
    text-transform: uppercase;
    margin-bottom: 20px;
}
.mpage-title {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: clamp(32px, 5vw, 52px);
    font-weight: 800;
    color: #191b23;
    line-height: 1.15;
    letter-spacing: -0.02em;
    margin: 0 0 16px;
}
.mpage-title span {
    background: linear-gradient(135deg, #004ac6, #712ae2);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}
.mpage-subtitle {
    font-family: 'Inter', sans-serif;
    font-size: 16px;
    color: #434655;
    line-height: 1.65;
    margin: 0;
}

/* ─── Cards Grid ──────────────────────────────── */
.mpage-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 24px;
    max-width: 1100px;
    margin: 0 auto;
    padding: 0 24px;
}
@media (max-width: 991px) {
    .mpage-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 575px) {
    .mpage-grid { grid-template-columns: 1fr; gap: 16px; }
}

/* ─── Single Card ─────────────────────────────── */
.m-card {
    position: relative;
    background: rgba(255, 255, 255, 0.78);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid rgba(195, 198, 215, 0.45);
    border-radius: 20px;
    padding: 32px 28px 28px;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 14px;
    box-shadow:
        0 1px 3px rgba(0,0,0,0.04),
        0 4px 16px rgba(0,74,198,0.05);
    transition:
        transform 0.28s cubic-bezier(0.34, 1.56, 0.64, 1),
        box-shadow 0.28s ease,
        border-color 0.28s ease;
    overflow: hidden;
    cursor: default;
}
.m-card::before {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 20px;
    background: linear-gradient(135deg, rgba(255,255,255,0.6) 0%, rgba(255,255,255,0) 60%);
    pointer-events: none;
    z-index: 0;
}
.m-card:hover {
    transform: translateY(-8px) scale(1.015);
    box-shadow:
        0 2px 8px rgba(0,0,0,0.05),
        0 16px 40px rgba(0,74,198,0.12);
    border-color: rgba(0,74,198,0.22);
}
.m-card > * { position: relative; z-index: 1; }

/* Step number badge */
.m-step-num {
    position: absolute;
    top: 18px;
    right: 22px;
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 11px;
    font-weight: 700;
    color: rgba(0,74,198,0.3);
    letter-spacing: 0.06em;
    z-index: 1;
}

/* Icon circle */
.m-icon-wrap {
    width: 58px;
    height: 58px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: #fff;
    flex-shrink: 0;
    box-shadow: 0 4px 14px rgba(0,0,0,0.12);
}
.ig-1 { background: linear-gradient(135deg, #004ac6, #2563eb); }
.ig-2 { background: linear-gradient(135deg, #712ae2, #9c55ff); }
.ig-3 { background: linear-gradient(135deg, #0ea5e9, #38bdf8); }
.ig-4 { background: linear-gradient(135deg, #d97706, #f59e0b); }
.ig-5 { background: linear-gradient(135deg, #059669, #34d399); }
.ig-6 { background: linear-gradient(135deg, #be185d, #f472b6); }

/* Card label */
.m-label {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 18px;
    font-weight: 700;
    color: #191b23;
    margin: 0;
    line-height: 1.3;
}

/* Card description */
.m-desc {
    font-family: 'Inter', sans-serif;
    font-size: 14px;
    color: #505366;
    line-height: 1.65;
    margin: 0;
}

/* Accent bottom line */
.m-accent {
    display: block;
    width: 32px;
    height: 3px;
    border-radius: 9999px;
    margin-top: 4px;
}
.ma-1 { background: linear-gradient(90deg, #004ac6, #2563eb); }
.ma-2 { background: linear-gradient(90deg, #712ae2, #9c55ff); }
.ma-3 { background: linear-gradient(90deg, #0ea5e9, #38bdf8); }
.ma-4 { background: linear-gradient(90deg, #d97706, #f59e0b); }
.ma-5 { background: linear-gradient(90deg, #059669, #34d399); }
.ma-6 { background: linear-gradient(90deg, #be185d, #f472b6); }

/* ─── Banner Section ──────────────────────────── */
.mpage-banner {
    max-width: 860px;
    margin: 72px auto 0;
    padding: 0 24px;
}
.mpage-banner-inner {
    background: linear-gradient(135deg, #004ac6 0%, #712ae2 55%, #9c55ff 100%);
    border-radius: 24px;
    padding: 52px 48px;
    text-align: center;
    position: relative;
    overflow: hidden;
    box-shadow: 0 8px 40px rgba(0,74,198,0.25);
}
.mpage-banner-inner::before {
    content: '';
    position: absolute;
    top: -60px; right: -60px;
    width: 220px; height: 220px;
    border-radius: 50%;
    background: rgba(255,255,255,0.07);
    pointer-events: none;
}
.mpage-banner-inner::after {
    content: '';
    position: absolute;
    bottom: -80px; left: -40px;
    width: 280px; height: 280px;
    border-radius: 50%;
    background: rgba(255,255,255,0.05);
    pointer-events: none;
}
.banner-icon {
    font-size: 40px;
    display: block;
    margin-bottom: 20px;
    filter: drop-shadow(0 2px 8px rgba(0,0,0,0.2));
}
.banner-text {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: clamp(18px, 3vw, 24px);
    font-weight: 700;
    color: #fff;
    line-height: 1.5;
    margin: 0 auto;
    max-width: 620px;
    position: relative;
    z-index: 1;
    letter-spacing: -0.01em;
}
@media (max-width: 575px) {
    .mpage-banner-inner { padding: 36px 24px; }
    .banner-text { font-size: 17px; }
}
</style>

<main class="mpage-bg">

    <!-- ── Hero ── -->
    <div class="mpage-hero">
        <div class="mpage-eyebrow">
            <i class="bi bi-mortarboard-fill"></i>
            <?= t('methodology.eyebrow') ?>
        </div>
        <h1 class="mpage-title"><?= t('methodology.title_plain') ?> <span><?= t('methodology.title_colored') ?></span></h1>
        <p class="mpage-subtitle"><?= t('methodology.subtitle') ?></p>
    </div>

    <!-- ── 6 Cards Grid ── -->
    <div class="mpage-grid">

        <!-- 1. Learn -->
        <div class="m-card">
            <span class="m-step-num">01</span>
            <div class="m-icon-wrap ig-1">
                <i class="bi bi-book-fill"></i>
            </div>
            <span class="m-accent ma-1"></span>
            <h3 class="m-label"><?= t('methodology.step1_title') ?></h3>
            <p class="m-desc"><?= t('methodology.step1_desc') ?></p>
        </div>

        <!-- 2. Practice -->
        <div class="m-card">
            <span class="m-step-num">02</span>
            <div class="m-icon-wrap ig-2">
                <i class="bi bi-pencil-fill"></i>
            </div>
            <span class="m-accent ma-2"></span>
            <h3 class="m-label"><?= t('methodology.step2_title') ?></h3>
            <p class="m-desc"><?= t('methodology.step2_desc') ?></p>
        </div>

        <!-- 3. Quiz -->
        <div class="m-card">
            <span class="m-step-num">03</span>
            <div class="m-icon-wrap ig-3">
                <i class="bi bi-question-circle-fill"></i>
            </div>
            <span class="m-accent ma-3"></span>
            <h3 class="m-label"><?= t('methodology.step3_title') ?></h3>
            <p class="m-desc"><?= t('methodology.step3_desc') ?></p>
        </div>

        <!-- 4. Earn XP -->
        <div class="m-card">
            <span class="m-step-num">04</span>
            <div class="m-icon-wrap ig-4">
                <i class="bi bi-trophy-fill"></i>
            </div>
            <span class="m-accent ma-4"></span>
            <h3 class="m-label"><?= t('methodology.step4_title') ?></h3>
            <p class="m-desc"><?= t('methodology.step4_desc') ?></p>
        </div>

        <!-- 5. Compete -->
        <div class="m-card">
            <span class="m-step-num">05</span>
            <div class="m-icon-wrap ig-5">
                <i class="bi bi-award-fill"></i>
            </div>
            <span class="m-accent ma-5"></span>
            <h3 class="m-label"><?= t('methodology.step5_title') ?></h3>
            <p class="m-desc"><?= t('methodology.step5_desc') ?></p>
        </div>

        <!-- 6. Grow -->
        <div class="m-card">
            <span class="m-step-num">06</span>
            <div class="m-icon-wrap ig-6">
                <i class="bi bi-graph-up-arrow"></i>
            </div>
            <span class="m-accent ma-6"></span>
            <h3 class="m-label"><?= t('methodology.step6_title') ?></h3>
            <p class="m-desc"><?= t('methodology.step6_desc') ?></p>
        </div>

    </div>

    <!-- ── Banner ── -->
    <div class="mpage-banner">
        <div class="mpage-banner-inner">
            <span class="banner-icon">🎓</span>
            <p class="banner-text">
                <?= t('methodology.banner_text') ?>
            </p>
        </div>
    </div>

</main>

<?php
include_once __DIR__ . '/includes/footer.php';
?>
