<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/translate.php';
require_once dirname(__DIR__) . '/includes/challenges-helper.php';

// Default layout variable checks
$no_layout = $no_layout ?? false;
$page_title = $page_title ?? null;
?>
<!DOCTYPE html>
<html lang="<?php echo e($current_lang); ?>" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ? e($page_title) . ' | ' . e(__('common.app_name')) : e(__('common.app_name')); ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Google Fonts & Material Symbols -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS (via CDN) -->
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        "surface-container-lowest": "#ffffff",
                        "secondary-container": "#8a4cfc",
                        "surface-container": "#ededf9",
                        "on-tertiary-fixed-variant": "#7d2d00",
                        "on-surface-variant": "#434655",
                        "surface-dim": "#d9d9e5",
                        "secondary-fixed": "#eaddff",
                        "primary": "#004ac6",
                        "tertiary-fixed-dim": "#ffb596",
                        "on-primary-fixed": "#00174b",
                        "surface-container-high": "#e7e7f3",
                        "surface-container-low": "#f3f3fe",
                        "on-error": "#ffffff",
                        "secondary": "#712ae2",
                        "on-background": "#191b23",
                        "on-primary-fixed-variant": "#003ea8",
                        "on-surface": "#191b23",
                        "secondary-fixed-dim": "#d2bbff",
                        "inverse-primary": "#b4c5ff",
                        "error": "#ba1a1a",
                        "surface-container-highest": "#e1e2ed",
                        "on-primary": "#ffffff",
                        "tertiary-container": "#bc4800",
                        "on-primary-container": "#eeefff",
                        "outline": "#737686",
                        "error-container": "#ffdad6",
                        "background": "#faf8ff",
                        "on-secondary-fixed-variant": "#5a00c6",
                        "surface-variant": "#e1e2ed",
                        "inverse-surface": "#2e3039",
                        "on-secondary-container": "#fffbff",
                        "primary-container": "#2563eb",
                        "outline-variant": "#c3c6d7",
                        "on-tertiary": "#ffffff",
                        "tertiary-fixed": "#ffdbcd",
                        "surface": "#faf8ff",
                        "primary-fixed": "#dbe1ff",
                        "primary-fixed-dim": "#b4c5ff",
                        "on-secondary": "#ffffff",
                        "on-tertiary-fixed": "#360f00",
                        "on-error-container": "#93000a",
                        "on-secondary-fixed": "#25005a",
                        "on-tertiary-container": "#ffede6",
                        "surface-bright": "#faf8ff",
                        "tertiary": "#943700",
                        "surface-tint": "#0053db",
                        "inverse-on-surface": "#f0f0fb"
                    },
                    borderRadius: {
                        "DEFAULT": "0.25rem",
                        "lg": "0.5rem",
                        "xl": "0.75rem",
                        "full": "9999px"
                    },
                    spacing: {
                        "lg": "48px",
                        "gutter": "24px",
                        "base": "8px",
                        "xs": "4px",
                        "xl": "80px",
                        "container-max": "1240px",
                        "md": "24px",
                        "sm": "12px"
                    },
                    fontFamily: {
                        "body-sm": ["Inter"],
                        "headline-lg": ["Plus Jakarta Sans"],
                        "headline-xl": ["Plus Jakarta Sans"],
                        "label-sm": ["Inter"],
                        "headline-sm": ["Plus Jakarta Sans"],
                        "body-md": ["Inter"],
                        "label-md": ["Inter"],
                        "headline-md": ["Plus Jakarta Sans"],
                        "body-lg": ["Inter"]
                    },
                    fontSize: {
                        "body-sm": ["14px", {"lineHeight": "1.5", "fontWeight": "400"}],
                        "headline-lg": ["32px", {"lineHeight": "1.25", "letterSpacing": "-0.01em", "fontWeight": "700"}],
                        "headline-xl": ["48px", {"lineHeight": "1.2", "letterSpacing": "-0.02em", "fontWeight": "700"}],
                        "label-sm": ["12px", {"lineHeight": "1", "fontWeight": "500"}],
                        "headline-sm": ["20px", {"lineHeight": "1.4", "fontWeight": "600"}],
                        "body-md": ["16px", {"lineHeight": "1.5", "fontWeight": "400"}],
                        "label-md": ["14px", {"lineHeight": "1", "letterSpacing": "0.01em", "fontWeight": "600"}],
                        "headline-md": ["24px", {"lineHeight": "1.3", "fontWeight": "600"}],
                        "body-lg": ["18px", {"lineHeight": "1.6", "fontWeight": "400"}]
                    }
                }
            }
        };
    </script>
    
    <!-- Custom Style Sheet -->
    <link href="<?php echo BASE_URL; ?>assets/css/style.css?v=1.3" rel="stylesheet">
</head>
<body>
<?php if (!$no_layout): ?>
<div class="dashboard-wrapper">
    <!-- Sidebar inclusion -->
    <?php include __DIR__ . '/sidebar.php'; ?>
    
    <!-- Content Wrapper -->
    <div class="main-content">
        <!-- Navbar inclusion -->
        <?php include __DIR__ . '/navbar.php'; ?>
        
        <!-- Main page content container -->
        <div class="content-container">
            <?php echo display_flash_message(); ?>
<?php endif; ?>
