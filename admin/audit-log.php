<?php
$pageTitle = 'Audit Log';
require_once __DIR__ . '/../includes/header.php';
requireRole(['admin']);

$db = getDB();

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;
$offset  = ($page - 1) * $perPage;

$total = $db->query("SELECT COUNT(*) FROM audit_log")->fetchColumn();
$logs  = $db->prepare("
    SELECT al.*, u.full_name, u.username
    FROM audit_log al
    LEFT JOIN users u ON al.user_id=u.id
    ORDER BY al.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$logs->execute();
$logs = $logs->fetchAll();
?>

<div class="page-header">
    <h1><i class="bi bi-clock-history me-2 text-primary"></i>Audit Log</h1>
</div>

<div class="card">
    <div class="card-header small text-muted">Showing <?= number_format($total) ?> total entries</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr><th>Date/Time</th><th>User</th><th>Action</th><th>Table</th><th>Record ID</th><th>Details</th><th>IP</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td class="small text-muted"><?= formatDateTime($log['created_at']) ?></td>
                        <td class="small"><?= htmlspecialchars($log['full_name'] ?? 'System') ?></td>
                        <td><code class="small"><?= htmlspecialchars($log['action']) ?></code></td>
                        <td class="small text-muted"><?= htmlspecialchars($log['table_name'] ?? '—') ?></td>
                        <td class="small"><?= $log['record_id'] ?? '—' ?></td>
                        <td class="small"><?= htmlspecialchars($log['details'] ?? '—') ?></td>
                        <td class="small text-muted"><?= htmlspecialchars($log['ip_address'] ?? '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($total > $perPage): ?>
    <div class="card-footer d-flex justify-content-end">
        <?= paginate($total, $perPage, $page, 'audit-log.php?') ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
