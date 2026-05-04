<?php
$pageTitle = 'Notifications';
require_once __DIR__ . '/includes/header.php';
requireLogin();

$db = getDB();
$userId = $_SESSION['user_id'];

// Mark as read
if (isset($_GET['id'])) {
    $db->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?")->execute([(int)$_GET['id'], $userId]);
}

// Mark all read
if (isset($_GET['mark_all'])) {
    $db->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$userId]);
    redirect(APP_URL . '/notifications.php');
}

$notifications = $db->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 50");
$notifications->execute([$userId]);
$notifications = $notifications->fetchAll();
?>

<div class="page-header">
    <h1><i class="bi bi-bell-fill me-2 text-primary"></i>Notifications</h1>
    <a href="notifications.php?mark_all=1" class="btn btn-outline-secondary btn-sm">Mark all as read</a>
</div>

<?php if ($notifications): foreach ($notifications as $n): ?>
<div class="card mb-2 <?= !$n['is_read'] ? 'border-primary' : '' ?>">
    <div class="card-body py-2 px-3">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <?php if (!$n['is_read']): ?><span class="badge bg-primary me-1">New</span><?php endif; ?>
                <strong class="small"><?= htmlspecialchars($n['title'] ?? '') ?></strong>
                <div class="small text-muted"><?= htmlspecialchars($n['message']) ?></div>
            </div>
            <div class="small text-muted text-nowrap ms-3"><?= formatDateTime($n['created_at']) ?></div>
        </div>
    </div>
</div>
<?php endforeach; else: ?>
<div class="card"><div class="card-body text-center text-muted py-5">No notifications.</div></div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
