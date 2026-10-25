<?php
// Determine current year dynamically
$current_year = date('Y');
?>

<?php if (!($no_layout ?? false)): ?>
        </div> <!-- End of content-container -->
        
        <footer class="main-footer bg-white border-t border-outline-variant/30 py-4 px-6 text-on-surface-variant text-body-sm mt-auto">
            <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                <div>
                    &copy; <?php echo $current_year; ?> <strong><?php echo e(__('common.app_name')); ?></strong>. All rights reserved.
                </div>
                <div class="flex flex-wrap items-center gap-4 text-xs font-semibold">
                    <a href="<?php echo BASE_URL; ?>methodology.php" class="hover:text-primary transition-colors text-decoration-none">Methodology</a>
                    <a href="<?php echo BASE_URL; ?>impact.php" class="hover:text-primary transition-colors text-decoration-none">Impact</a>
                    <a href="<?php echo BASE_URL; ?>partnerships.php" class="hover:text-primary transition-colors text-decoration-none">Partnerships</a>
                    <span class="text-outline-variant/50">|</span>
                    <span class="text-on-surface-variant/80 font-normal">Made by LB Infotech</span>
                </div>
            </div>
        </footer>
    </div> <!-- End of main-content -->
</div> <!-- End of dashboard-wrapper -->

<?php else: ?>
    <!-- Public/Non-dashboard layout footer -->
    <?php if (!($simple_footer ?? false)): ?>
        <footer class="bg-surface-container-highest w-full mt-auto border-t border-outline-variant/30 py-8">
            <div class="flex flex-col md:flex-row justify-between items-center px-gutter max-w-container-max mx-auto gap-md">
                <div class="space-y-xs text-center md:text-left">
                    <span class="font-headline-sm text-headline-sm font-black text-primary"><?php echo e(__('common.app_name')); ?></span>
                    <p class="font-body-sm text-body-sm text-on-surface-variant">&copy; <?php echo $current_year; ?> <?php echo e(__('common.app_name')); ?>. All rights reserved.</p>
                    <p class="text-xs text-outline font-medium">Made by LB Infotech</p>
                </div>
                <div class="flex flex-wrap justify-center gap-md font-semibold">
                    <a class="font-body-sm text-body-sm text-on-surface-variant hover:text-primary transition-colors text-decoration-none" href="<?php echo BASE_URL; ?>methodology.php">Methodology</a>
                    <a class="font-body-sm text-body-sm text-on-surface-variant hover:text-primary transition-colors text-decoration-none" href="<?php echo BASE_URL; ?>impact.php">Impact</a>
                    <a class="font-body-sm text-body-sm text-on-surface-variant hover:text-primary transition-colors text-decoration-none" href="<?php echo BASE_URL; ?>partnerships.php">Partnerships</a>
                </div>
            </div>
        </footer>
    <?php else: ?>
        <!-- Minimal footer for login/register pages -->
        <footer class="w-full py-4 text-center mt-auto border-t border-outline-variant/10 bg-transparent">
            <p class="font-label-sm text-label-sm text-outline-variant/80">&copy; <?php echo $current_year; ?> <?php echo e(__('common.app_name')); ?>. All rights reserved. | Made by LB Infotech</p>
        </footer>
    <?php endif; ?>
<?php endif; ?>

<!-- Bootstrap 5 Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Chart.js for Dashboards & Analytics Reports -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Custom App JS -->
<script src="<?php echo BASE_URL; ?>assets/js/app.js"></script>
</body>
</html>
