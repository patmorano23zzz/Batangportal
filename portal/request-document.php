<?php
$pageTitle = 'Request Document';
require_once __DIR__ . '/../includes/header.php';
requireRole(['parent']);

$db = getDB();
$userId = $_SESSION['user_id'];

// Get children linked to this parent
$children = $db->prepare("
    SELECT s.*, sec.section_name, gl.grade_name
    FROM students s
    LEFT JOIN sections sec ON s.section_id = sec.id
    LEFT JOIN grade_levels gl ON sec.grade_level_id = gl.id
    WHERE s.parent_user_id = ?
");
$children->execute([$userId]);
$children = $children->fetchAll();

// Fetch active document types ONCE — used for both the form and the info panel
$docTypes = $db->query("SELECT * FROM document_types WHERE is_active = 1 ORDER BY doc_name")->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studentId = (int)$_POST['student_id'];
    $docTypeId = (int)$_POST['document_type_id'];
    $purpose   = sanitize($_POST['purpose'] ?? '');
    $copies    = max(1, (int)($_POST['copies'] ?? 1));
    $priority  = sanitize($_POST['priority'] ?? 'normal');

    // Validate student belongs to this parent
    $check = $db->prepare("SELECT id FROM students WHERE id = ? AND parent_user_id = ?");
    $check->execute([$studentId, $userId]);
    if (!$check->fetch()) $errors[] = 'Invalid student selected.';
    if (!$docTypeId)      $errors[] = 'Please select a document type.';
    if (empty($purpose))  $errors[] = 'Please state the purpose of the request.';

    if (empty($errors)) {
        $reqNumber = generateRequestNumber();

        $docType = $db->prepare("SELECT * FROM document_types WHERE id = ?");
        $docType->execute([$docTypeId]);
        $docType = $docType->fetch();

        $db->prepare("
            INSERT INTO document_requests
                (request_number, student_id, requested_by, document_type_id, purpose, copies, priority, fee_amount)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $reqNumber, $studentId, $userId, $docTypeId,
            $purpose, $copies, $priority,
            ($docType['fee'] ?? 0) * $copies,
        ]);

        $newId = $db->lastInsertId();

        // Notify all admins
        $admins = $db->query("SELECT id FROM users WHERE role = 'admin' AND is_active = 1")->fetchAll();
        foreach ($admins as $admin) {
            sendNotification($admin['id'], 'New Document Request', "New request $reqNumber submitted.", 'request_update', $newId);
        }

        auditLog('SUBMIT_REQUEST', 'document_requests', $newId, "Submitted request: $reqNumber");
        setFlash('success', "Request submitted! Your request number is <strong>$reqNumber</strong>. You will be notified once it's ready.");
        redirect(APP_URL . '/portal/my-requests.php');
    }
}
?>

<div class="page-header">
    <div>
        <h1><i class="bi bi-file-earmark-plus me-2 text-primary"></i>Request a Document</h1>
        <nav aria-label="breadcrumb"><ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Request Document</li>
        </ol></nav>
    </div>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger">
    <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<?php if (!$children): ?>
<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle me-2"></i>
    No children linked to your account. Please contact the school administrator.
</div>
<?php else: ?>

<div class="row g-3">

    <!-- ── REQUEST FORM ── -->
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header fw-700">
                <i class="bi bi-pencil-square me-2 text-primary"></i>Request Form
            </div>
            <div class="card-body">
                <form method="POST" id="requestForm">

                    <div class="mb-3">
                        <label class="form-label">Student <span class="text-danger">*</span></label>
                        <select name="student_id" class="form-select" required>
                            <option value="">— Select your child —</option>
                            <?php foreach ($children as $c): ?>
                                <option value="<?= $c['id'] ?>"
                                    <?= ($_POST['student_id'] ?? '') == $c['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?>
                                    (<?= htmlspecialchars($c['grade_name'] ?? 'N/A') ?>
                                    <?= $c['section_name'] ? '— ' . htmlspecialchars($c['section_name']) : '' ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Document Type <span class="text-danger">*</span></label>
                        <select name="document_type_id" id="docTypeSelect" class="form-select" required onchange="updateDocInfo(this)">
                            <option value="">— Select document —</option>
                            <?php foreach ($docTypes as $dt): ?>
                                <option value="<?= $dt['id'] ?>"
                                        data-days="<?= (int)$dt['processing_days'] ?>"
                                        data-fee="<?= (float)$dt['fee'] ?>"
                                        data-desc="<?= htmlspecialchars($dt['description'] ?? '') ?>"
                                        <?= ($_POST['document_type_id'] ?? '') == $dt['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($dt['doc_name']) ?>
                                    <?= $dt['doc_code'] ? '(' . htmlspecialchars($dt['doc_code']) . ')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <!-- Info box shown when a doc type is selected -->
                        <div id="docInfo" class="mt-2 p-3 rounded border bg-light d-none">
                            <p id="docDesc" class="text-muted small mb-2"></p>
                            <div class="d-flex gap-3 small">
                                <span><i class="bi bi-clock text-primary me-1"></i>Processing: <strong id="docDays"></strong></span>
                                <span><i class="bi bi-cash text-success me-1"></i>Fee: <strong id="docFee"></strong></span>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-4">
                            <label class="form-label">Copies</label>
                            <input type="number" name="copies" class="form-control"
                                   min="1" max="10" value="<?= (int)($_POST['copies'] ?? 1) ?>">
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label">Priority</label>
                            <select name="priority" class="form-select">
                                <option value="normal" <?= ($_POST['priority'] ?? 'normal') === 'normal' ? 'selected' : '' ?>>Normal</option>
                                <option value="urgent" <?= ($_POST['priority'] ?? '')         === 'urgent' ? 'selected' : '' ?>>Urgent</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Purpose / Reason <span class="text-danger">*</span></label>
                        <textarea name="purpose" class="form-control" rows="3" required
                                  placeholder="e.g. For enrollment in another school, for scholarship application..."
                        ><?= htmlspecialchars($_POST['purpose'] ?? '') ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-send me-1"></i>Submit Request
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- ── AVAILABLE DOCUMENTS INFO PANEL ── -->
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header fw-700">
                <i class="bi bi-info-circle me-2 text-primary"></i>Available Documents
            </div>
            <div class="card-body p-0">
                <?php if ($docTypes): ?>
                    <?php foreach ($docTypes as $dt): ?>
                    <div class="doc-item px-3 py-2 border-bottom d-flex justify-content-between align-items-start"
                         data-id="<?= $dt['id'] ?>" style="cursor:pointer"
                         onclick="selectDoc(<?= $dt['id'] ?>)"
                         title="Click to select">
                        <div>
                            <div class="fw-600 small"><?= htmlspecialchars($dt['doc_name']) ?></div>
                            <?php if ($dt['description']): ?>
                                <div class="text-muted" style="font-size:.75rem"><?= htmlspecialchars($dt['description']) ?></div>
                            <?php endif; ?>
                            <div class="mt-1" style="font-size:.72rem">
                                <span class="text-secondary">
                                    <i class="bi bi-clock me-1"></i><?= (int)$dt['processing_days'] ?> day(s)
                                </span>
                                <span class="ms-2 <?= $dt['fee'] > 0 ? 'text-danger' : 'text-success' ?>">
                                    <i class="bi bi-cash me-1"></i><?= $dt['fee'] > 0 ? '₱' . number_format($dt['fee'], 2) : 'Free' ?>
                                </span>
                            </div>
                        </div>
                        <span class="badge bg-light text-secondary border ms-2 mt-1" style="font-size:.65rem;white-space:nowrap">
                            <?= htmlspecialchars($dt['doc_code'] ?? '') ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="p-4 text-center text-muted small">No document types available.</div>
                <?php endif; ?>
            </div>
            <div class="card-footer small text-muted">
                <i class="bi bi-hand-index me-1"></i>Click a document to auto-select it in the form.
            </div>
        </div>
    </div>

</div><!-- end row -->

<script>
// Update the info box when a doc type is chosen in the dropdown
function updateDocInfo(sel) {
    const opt  = sel.options[sel.selectedIndex];
    const info = document.getElementById('docInfo');

    // Highlight the matching item in the sidebar
    document.querySelectorAll('.doc-item').forEach(el => el.classList.remove('table-active', 'bg-primary-subtle'));
    if (opt.value) {
        const match = document.querySelector('.doc-item[data-id="' + opt.value + '"]');
        if (match) match.classList.add('bg-primary-subtle');
    }

    if (!opt.value) { info.classList.add('d-none'); return; }

    document.getElementById('docDesc').textContent = opt.dataset.desc || '—';
    document.getElementById('docDays').textContent  = opt.dataset.days + ' working day(s)';
    const fee = parseFloat(opt.dataset.fee);
    document.getElementById('docFee').textContent   = fee > 0 ? '₱' + fee.toFixed(2) : 'Free';
    info.classList.remove('d-none');
}

// Clicking a sidebar item selects it in the dropdown and triggers the info box
function selectDoc(id) {
    const sel = document.getElementById('docTypeSelect');
    sel.value = id;
    updateDocInfo(sel);
    // Scroll form into view on mobile
    document.getElementById('requestForm').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// If a doc type was pre-selected (e.g. after a failed POST), trigger the info box on load
document.addEventListener('DOMContentLoaded', function () {
    const sel = document.getElementById('docTypeSelect');
    if (sel && sel.value) updateDocInfo(sel);
});
</script>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
