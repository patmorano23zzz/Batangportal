<?php
$pageTitle = 'My Requests';
require_once __DIR__ . '/../includes/header.php';
requireRole(['parent']);

$db = getDB();
$userId = $_SESSION['user_id'];

// Handle cancel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_id'])) {
    $reqId = (int)$_POST['cancel_id'];
    $check = $db->prepare("SELECT id FROM document_requests WHERE id=? AND requested_by=? AND status='pending'");
    $check->execute([$reqId, $userId]);
    if ($check->fetch()) {
        $db->prepare("UPDATE document_requests SET status='cancelled' WHERE id=?")->execute([$reqId]);
        setFlash('success', 'Request cancelled.');
    }
    redirect(APP_URL . '/portal/my-requests.php');
}

$status = sanitize($_GET['status'] ?? '');
$where  = ['dr.requested_by = ?'];
$params = [$userId];
if ($status) { $where[] = "dr.status=?"; $params[] = $status; }
$whereStr = implode(' AND ', $where);

$requests = $db->prepare("
    SELECT dr.*, dt.doc_name, dt.processing_days, s.first_name, s.last_name
    FROM document_requests dr
    JOIN document_types dt ON dr.document_type_id=dt.id
    JOIN students s ON dr.student_id=s.id
    WHERE $whereStr
    ORDER BY dr.date_requested DESC
");
$requests->execute($params);
$requests = $requests->fetchAll();
?>

<div class="page-header">
    <div>
        <h1><i class="bi bi-file-earmark-text me-2 text-primary"></i>My Document Requests</h1>
    </div>
    <a href="<?= APP_URL ?>/portal/request-document.php" class="btn btn-primary">
        <i class="bi bi-plus me-1"></i>New Request
    </a>
</div>

<?php showFlash(); ?>

<!-- Status Filter Tabs -->
<ul class="nav nav-pills mb-3">
    <li class="nav-item"><a class="nav-link <?= !$status?'active':'' ?>" href="my-requests.php">All</a></li>
    <?php foreach (['pending','processing','ready','released','rejected','cancelled'] as $s): ?>
    <li class="nav-item"><a class="nav-link <?= $status==$s?'active':'' ?>" href="my-requests.php?status=<?= $s ?>"><?= ucfirst($s) ?></a></li>
    <?php endforeach; ?>
</ul>

<?php if ($requests): ?>
<div class="row g-3">
    <?php foreach ($requests as $r): ?>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <code class="small text-muted"><?= htmlspecialchars($r['request_number']) ?></code>
                        <?php if ($r['priority'] === 'urgent'): ?>
                            <span class="badge bg-danger ms-1">Urgent</span>
                        <?php endif; ?>
                    </div>
                    <?= statusBadge($r['status']) ?>
                </div>

                <h6 class="fw-700 mb-1"><?= htmlspecialchars($r['doc_name']) ?></h6>
                <div class="small text-muted mb-2">
                    For: <strong><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?></strong>
                    &nbsp;·&nbsp; <?= $r['copies'] ?> cop<?= $r['copies'] > 1 ? 'ies' : 'y' ?>
                </div>
                <div class="small text-muted mb-2">
                    Purpose: <?= htmlspecialchars($r['purpose']) ?>
                </div>

                <?php if ($r['admin_notes']): ?>
                <div class="alert alert-info py-1 px-2 small mb-2">
                    <i class="bi bi-chat-left-text me-1"></i><?= htmlspecialchars($r['admin_notes']) ?>
                </div>
                <?php endif; ?>

                <div class="d-flex justify-content-between align-items-center mt-2">
                    <div class="small text-muted">
                        <i class="bi bi-clock me-1"></i><?= formatDateTime($r['date_requested']) ?>
                    </div>
                    <?php if ($r['status'] === 'pending'): ?>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="cancel_id" value="<?= $r['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Cancel this request?">
                            <i class="bi bi-x-circle me-1"></i>Cancel
                        </button>
                    </form>
                    <?php elseif ($r['status'] === 'ready'): ?>
                    <span class="badge bg-success p-2">
                        <i class="bi bi-check-circle me-1"></i>Ready for pickup!
                    </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Status Timeline -->
            <div class="card-footer bg-transparent">
                <div class="d-flex justify-content-between small">
                    <?php
                    $steps = ['pending'=>'Submitted','processing'=>'Processing','ready'=>'Ready','released'=>'Released'];
                    $stepOrder = array_keys($steps);
                    $currentIdx = array_search($r['status'], $stepOrder);
                    foreach ($steps as $s => $label):
                        $idx = array_search($s, $stepOrder);
                        $done = $currentIdx !== false && $idx <= $currentIdx && !in_array($r['status'], ['rejected','cancelled']);
                    ?>
                    <div class="text-center" style="flex:1">
                        <div class="rounded-circle mx-auto mb-1 d-flex align-items-center justify-content-center"
                             style="width:24px;height:24px;background:<?= $done ? '#1a73e8' : '#e0e0e0' ?>;color:<?= $done ? '#fff' : '#999' ?>;font-size:.7rem">
                            <?= $done ? '✓' : ($idx + 1) ?>
                        </div>
                        <div style="font-size:.65rem;color:<?= $done ? '#1a73e8' : '#999' ?>"><?= $label ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="card">
    <div class="card-body text-center text-muted py-5">
        <i class="bi bi-file-earmark fs-1 d-block mb-2 opacity-25"></i>
        No requests found.
        <div class="mt-3">
            <a href="<?= APP_URL ?>/portal/request-document.php" class="btn btn-primary">
                <i class="bi bi-plus me-1"></i>Make a Request
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
