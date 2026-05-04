    </div><!-- end #content -->
</div><!-- end #wrapper -->

<footer class="footer bg-light border-top py-2 px-4 small text-muted d-flex justify-content-between align-items-center">
    <span>&copy; <?= date('Y') ?> <?= htmlspecialchars($schoolName ?? APP_NAME) ?> — BatangPortal v<?= APP_VERSION ?></span>
    <span>DepEd Philippines</span>
</footer>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Custom JS -->
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
<script src="<?= APP_URL ?>/assets/js/bulk-actions.js"></script>
</body>
</html>
<?php if (ob_get_level() > 0) ob_end_flush(); ?>
