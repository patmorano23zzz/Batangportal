<?php
$pageTitle = 'Document Requests';
require_once __DIR__ . '/../includes/header.php';
requireRole(['admin', 'teacher']);

$db = getDB();

// ── Single status update ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $reqId  = (int)$_POST['req_id'];
    $status = sanitize($_POST['status']);
    $notes  = sanitize($_POST['admin_notes'] ?? '');
    $valid  = ['pending','processing','ready','released','rejected','cancelled'];

    if (in_array($status, $valid)) {
        $now = date('Y-m-d H:i:s');
        $db->prepare("UPDATE document_requests SET status=?,admin_notes=?,processed_by=?,date_processed=? WHERE id=?")
           ->execute([$status, $notes, $_SESSION['user_id'], $now, $reqId]);

        if ($status === 'released') {
            $db->prepare("UPDATE document_requests SET date_released=?,released_by=? WHERE id=?")
               ->execute([$now, $_SESSION['user_id'], $reqId]);
        }

        $req = $db->prepare("SELECT dr.*,u.id as parent_id FROM document_requests dr JOIN users u ON dr.requested_by=u.id WHERE dr.id=?");
        $req->execute([$reqId]);
        $reqData = $req->fetch();
        if ($reqData) {
            sendNotification($reqData['parent_id'], 'Request Update',
                "Your request #{$reqData['request_number']} is now: " . strtoupper($status),
                'request_update', $reqId);
        }
        auditLog('UPDATE_REQUEST', 'document_requests', $reqId, "Status: $status");
        setFlash('success', 'Request status updated.');
    }
    redirect(APP_URL . '/admin/requests.php');
}

// ── Bulk actions ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'], $_POST['bulk_ids'])) {
    $ids    = array_map('intval', (array)$_POST['bulk_ids']);
    $action = sanitize($_POST['bulk_action']);
    $valid  = ['processing','ready','released','rejected','cancelled','delete'];

    if (!empty($ids) && in_array($action, $valid)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        if ($action === 'delete' && isAdmin()) {
            $db->prepare("DELETE FROM document_requests WHERE id IN ($placeholders)")->execute($ids);
            auditLog('BULK_DELETE_REQUESTS', 'document_requests', null, "Deleted IDs: ".implode(',',$ids));
            setFlash('success', count($ids)." request(s) deleted.");
        } else {
            $now = date('Y-m-d H:i:s');
            $db->prepare("UPDATE document_requests SET status=?,processed_by=?,date_processed=? WHERE id IN ($placeholders)")
               ->execute(array_merge([$action, $_SESSION['user_id'], $now], $ids));

            // Notify each parent
            $rows = $db->prepare("SELECT dr.request_number,dr.requested_by FROM document_requests dr WHERE dr.id IN ($placeholders)");
            $rows->execute($ids);
            foreach ($rows->fetchAll() as $r) {
                sendNotification($r['requested_by'], 'Request Update',
                    "Your request #{$r['request_number']} is now: ".strtoupper($action),
                    'request_update');
            }
            auditLog('BULK_UPDATE_REQUESTS', 'document_requests', null, "Set status=$action for IDs: ".implode(',',$ids));
            setFlash('success', count($ids)." request(s) updated to ".ucfirst($action).".");
        }
    }
    redirect(APP_URL . '/admin/requests.php?' . http_build_query(array_diff_key($_GET,['page'=>''])));
}

// ── View single request ───────────────────────────────────
$viewId  = (int)($_GET['view'] ?? 0);
$viewReq = null;
if ($viewId) {
    $stmt = $db->prepare("
        SELECT dr.*, dt.doc_name, dt.doc_code, dt.processing_days,
               s.first_name, s.last_name, s.lrn, s.date_of_birth, s.gender,
               sec.section_name, gl.grade_name,
               u.full_name as requester_name, u.contact_number as requester_contact,
               p.full_name as processor_name
        FROM document_requests dr
        JOIN document_types dt ON dr.document_type_id=dt.id
        JOIN students s ON dr.student_id=s.id
        LEFT JOIN sections sec ON s.section_id=sec.id
        LEFT JOIN grade_levels gl ON sec.grade_level_id=gl.id
        JOIN users u ON dr.requested_by=u.id
        LEFT JOIN users p ON dr.processed_by=p.id
        WHERE dr.id=?
    ");
    $stmt->execute([$viewId]);
    $viewReq = $stmt->fetch();
}

// ── Filters ───────────────────────────────────────────────
$search  = sanitize($_GET['search']   ?? '');
$status  = sanitize($_GET['status']   ?? '');
$docType = (int)($_GET['doc_type']    ?? 0);
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$where  = ['1=1'];
$params = [];
if ($search)  { $where[] = "(dr.request_number LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR s.lrn LIKE ?)"; $params = array_merge($params, ["%$search%","%$search%","%$search%","%$search%"]); }
if ($status)  { $where[] = "dr.status=?";              $params[] = $status; }
if ($docType) { $where[] = "dr.document_type_id=?";    $params[] = $docType; }

$whereStr = implode(' AND ', $where);

$total = $db->prepare("SELECT COUNT(*) FROM document_requests dr JOIN students s ON dr.student_id=s.id WHERE $whereStr");
$total->execute($params);
$total = $total->fetchColumn();

$stmt = $db->prepare("
    SELECT dr.*, dt.doc_name, s.first_name, s.last_name, s.lrn, u.full_name as requester
    FROM document_requests dr
    JOIN document_types dt ON dr.document_type_id=dt.id
    JOIN students s ON dr.student_id=s.id
    JOIN users u ON dr.requested_by=u.id
    WHERE $whereStr
    ORDER BY FIELD(dr.status,'pending','processing','ready','released','rejected','cancelled'), dr.date_requested DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$requests = $stmt->fetchAll();

$docTypes = $db->query("SELECT * FROM document_types WHERE is_active=1 ORDER BY doc_name")->fetchAll();
?>

<div class="page-header">
    <div>
        <h1><i class="bi bi-file-earmark-text me-2 text-primary"></i>Document Requests</h1>
        <nav aria-label="breadcrumb"><ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Requests</li>
        </ol></nav>
    </div>
</div>

<?php showFlash(); ?>

<?php if ($viewReq): ?>
<div class="card mb-4 border-primary">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <span><i class="bi bi-file-earmark-text me-2"></i>Request — <?= htmlspecialchars($viewReq['request_number']) ?></span>
        <a href="requests.php" class="btn btn-sm btn-light">Close</a>
    </div>
    <div class="card-body">
        <div class="row g-4">
            <div class="col-md-6">
                <h6 class="fw-700 text-muted mb-2">STUDENT</h6>
                <table class="table table-sm table-borderless">
                    <tr><td class="text-muted" width="40%">Name</td><td class="fw-600"><?= htmlspecialchars($viewReq['first_name'].' '.$viewReq['last_name']) ?></td></tr>
                    <tr><td class="text-muted">LRN</td><td><?= htmlspecialchars($viewReq['lrn'] ?? '—') ?></td></tr>
                    <tr><td class="text-muted">Grade & Section</td><td><?= htmlspecialchars(($viewReq['grade_name']??'').' '.($viewReq['section_name']??'')) ?></td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6 class="fw-700 text-muted mb-2">REQUEST</h6>
                <table class="table table-sm table-borderless">
                    <tr><td class="text-muted" width="40%">Document</td><td class="fw-600"><?= htmlspecialchars($viewReq['doc_name']) ?></td></tr>
                    <tr><td class="text-muted">Purpose</td><td><?= htmlspecialchars($viewReq['purpose']??'—') ?></td></tr>
                    <tr><td class="text-muted">Copies</td><td><?= $viewReq['copies'] ?></td></tr>
                    <tr><td class="text-muted">Priority</td><td><?= ucfirst($viewReq['priority']) ?></td></tr>
                    <tr><td class="text-muted">Requested By</td><td><?= htmlspecialchars($viewReq['requester_name']) ?></td></tr>
                    <tr><td class="text-muted">Date</td><td><?= formatDateTime($viewReq['date_requested']) ?></td></tr>
                    <tr><td class="text-muted">Status</td><td><?= statusBadge($viewReq['status']) ?></td></tr>
                </table>
            </div>
        </div>
        <hr>
        <h6 class="fw-700 mb-3">Update Status</h6>
        <form method="POST" class="row g-3">
            <input type="hidden" name="update_status" value="1">
            <input type="hidden" name="req_id" value="<?= $viewReq['id'] ?>">
            <div class="col-md-3">
                <select name="status" class="form-select" required>
                    <?php foreach (['pending','processing','ready','released','rejected','cancelled'] as $s): ?>
                        <option value="<?= $s ?>" <?= $viewReq['status']==$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <input type="text" name="admin_notes" class="form-control" value="<?= htmlspecialchars($viewReq['admin_notes']??'') ?>" placeholder="Admin notes (optional)">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-check-circle me-1"></i>Update</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Filters -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <?php if ($viewId): ?><input type="hidden" name="view" value="<?= $viewId ?>"><?php endif; ?>
            <div class="col-md-4">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search request #, student, LRN..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Status</option>
                    <?php foreach (['pending','processing','ready','released','rejected','cancelled'] as $s): ?>
                        <option value="<?= $s ?>" <?= $status==$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select name="doc_type" class="form-select form-select-sm">
                    <option value="">All Document Types</option>
                    <?php foreach ($docTypes as $dt): ?>
                        <option value="<?= $dt['id'] ?>" <?= $docType==$dt['id']?'selected':'' ?>><?= htmlspecialchars($dt['doc_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1"><i class="bi bi-search"></i> Filter</button>
                <a href="requests.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Bulk Toolbar -->
<div id="bulkToolbar" class="d-none mb-2">
    <div class="card border-primary">
        <div class="card-body py-2 d-flex align-items-center gap-3 flex-wrap">
            <span class="fw-700 text-primary small">
                <i class="bi bi-check2-square me-1"></i><span id="bulkCount">0</span> selected
            </span>
            <form method="POST" data-bulk-form>
                <select name="bulk_action" class="form-select form-select-sm d-inline-block w-auto me-1">
                    <option value="">— Set Status —</option>
                    <option value="processing">Set Processing</option>
                    <option value="ready">Set Ready</option>
                    <option value="released">Set Released</option>
                    <option value="rejected">Set Rejected</option>
                    <option value="cancelled">Set Cancelled</option>
                </select>
                <button type="submit" class="btn btn-sm btn-outline-primary">Apply</button>
            </form>
            <?php if (isAdmin()): ?>
            <form method="POST" data-bulk-form data-confirm-label="Delete">
                <input type="hidden" name="bulk_action" value="delete">
                <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash me-1"></i>Delete</button>
            </form>
            <?php endif; ?>
            <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" onclick="deselectAll()">
                <i class="bi bi-x me-1"></i>Clear
            </button>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><?= number_format($total) ?> request(s)</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="requestsTable">
                <thead>
                    <tr>
                        <th width="36"><input type="checkbox" class="form-check-input" id="selectAll"></th>
                        <th>Request #</th>
                        <th>Student</th>
                        <th>Document</th>
                        <th>Requested By</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($requests): foreach ($requests as $r): ?>
                    <tr class="<?= $r['status']==='pending'?'table-warning':'' ?>">
                        <td><input type="checkbox" class="form-check-input" name="ids[]" value="<?= $r['id'] ?>"></td>
                        <td><code class="small"><?= htmlspecialchars($r['request_number']) ?></code></td>
                        <td>
                            <div class="fw-600"><?= htmlspecialchars($r['first_name'].' '.$r['last_name']) ?></div>
                            <div class="small text-muted"><?= htmlspecialchars($r['lrn']??'') ?></div>
                        </td>
                        <td class="small"><?= htmlspecialchars($r['doc_name']) ?></td>
                        <td class="small"><?= htmlspecialchars($r['requester']) ?></td>
                        <td><?= $r['priority']==='urgent' ? '<span class="badge bg-danger">Urgent</span>' : '<span class="badge bg-secondary">Normal</span>' ?></td>
                        <td><?= statusBadge($r['status']) ?></td>
                        <td class="small text-muted"><?= formatDate($r['date_requested'],'M d, Y') ?></td>
                        <td>
                            <a href="requests.php?view=<?= $r['id'] ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i> Process
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="9" class="text-center text-muted py-5">
                        <i class="bi bi-file-earmark fs-1 d-block mb-2 opacity-25"></i>No requests found
                    </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($total > $perPage): ?>
    <div class="card-footer d-flex justify-content-end">
        <?= paginate($total, $perPage, $page, 'requests.php?'.http_build_query(array_diff_key($_GET,['page'=>'']))) ?>
    </div>
    <?php endif; ?>
</div>

<script>
initBulkSelect({ tableId:'requestsTable', checkboxName:'ids[]', counterId:'bulkCount', toolbarId:'bulkToolbar', selectAllId:'selectAll' });
function deselectAll() {
    document.querySelectorAll('#requestsTable input[name="ids[]"]').forEach(cb => { cb.checked=false; cb.closest('tr').classList.remove('table-active'); });
    document.getElementById('selectAll').checked=false;
    document.getElementById('bulkToolbar').classList.add('d-none');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
