<?php
$pageTitle = 'Unauthorized';
require_once __DIR__ . '/includes/header.php';
?>
<div class="text-center py-5">
    <i class="bi bi-shield-exclamation text-danger" style="font-size:4rem"></i>
    <h2 class="mt-3">Access Denied</h2>
    <p class="text-muted">You don't have permission to access this page.</p>
    <a href="<?= APP_URL ?>" class="btn btn-primary">Go to Dashboard</a>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
