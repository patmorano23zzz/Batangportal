<?php
$pageTitle = 'Document Types';
require_once __DIR__ . '/../includes/header.php';
requireRole(['admin']);

$db = getDB();

// ── Handle POST actions ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $did  = (int)($_POST['doc_id'] ?? 0);
        $name = sanitize($_POST['doc_name'] ?? '');
        $code = sanitize($_POST['doc_code'] ?? '');
        $desc = sanitize($_POST['description'] ?? '');
        $days = max(1, (int)($_POST['processing_days'] ?? 3));
        $fee  = max(0, (float)($_POST['fee'] ?? 0));
        $reqs = sanitize($_POST['requirements'] ?? '');
        $active = isset($_POST['is_active']) ? 1 : 0;

        if (empty($name)) {
            setFlash('error', 'Document name is required.');
            redirect(APP_URL . '/admin/document-types.php' . ($did ? "?edit=$did" : '?add=1'));
        }

        if ($did) {
            $db->prepare("
                UPDATE document_types
                SET doc_name=?, doc_code=?, description=?, processing_days=?, fee=?, requirements=?, is_active=?
                WHERE id=?
            ")->execute([$name, $code, $desc, $days, $fee, $reqs, $active, $did]);
            auditLog('UPDATE_DOC_TYPE', 'document_types', $did, "Updated: $name");
            setFlash('success', 'Document type updated successfully.');
        } else {
            $db->prepare("
                INSERT INTO document_types (doc_name, doc_code, description, processing_days, fee, requirements, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ")->execute([$name, $code, $desc, $days, $fee, $reqs, $active]);
            auditLog('ADD_DOC_TYPE', 'document_types', $db->lastInsertId(), "Added: $name");
            setFlash('success', 'Document type added successfully.');
        }
        redirect(APP_URL . '/admin/document-types.php');
    }

    if ($action === 'delete') {
        $did = (int)$_POST['doc_id'];
        // Check if any requests use this type
        $inUse = $db->prepare("SELECT COUNT(*) FROM document_requests WHERE document_type_id = ?");
        $inUse->execute([$did]);
        if ($inUse->fetchColumn() > 0) {
            setFlash('error', 'Cannot delete — this document type has existing requests. Deactivate it instead.');
        } else {
            $db->prepare("DELETE FROM document_types WHERE id = ?")->execute([$did]);
            auditLog('DELETE_DOC_TYPE', 'document_types', $did);
            setFlash('success', 'Document type deleted.');
        }
        redirect(APP_URL . '/admin/document-types.php');
    }

    if ($action === 'toggle') {
        $did = (int)$_POST['doc_id'];
        $db->prepare("UPDATE document_types SET is_active = NOT is_active WHERE id = ?")->execute([$did]);
        setFlash('success', 'Status updated.');
        redirect(APP_URL . '/admin/document-types.php');
    }

    // ── Bulk actions ──────────────────────────────────────
    if ($action === 'bulk' && isset($_POST['bulk_action'], $_POST['bulk_ids'])) {
        $ids        = array_map('intval', (array)$_POST['bulk_ids']);
        $bulkAction = sanitize($_POST['bulk_action']);
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            if ($bulkAction === 'delete') {
                $safe = [];
                foreach ($ids as $bid) {
                    $inUse = $db->prepare("SELECT COUNT(*) FROM document_requests WHERE document_type_id=?");
                    $inUse->execute([$bid]);
                    if ($inUse->fetchColumn() == 0) $safe[] = $bid;
                }
                if ($safe) {
                    $ph = implode(',', array_fill(0, count($safe), '?'));
                    $db->prepare("DELETE FROM document_types WHERE id IN ($ph)")->execute($safe);
                }
                $skipped = count($ids) - count($safe);
                $msg = count($safe) . " type(s) deleted.";
                if ($skipped) $msg .= " $skipped skipped (have existing requests — deactivate instead).";
                setFlash('success', $msg);
            } elseif ($bulkAction === 'activate') {
                $db->prepare("UPDATE document_types SET is_active=1 WHERE id IN ($placeholders)")->execute($ids);
                setFlash('success', count($ids) . " type(s) activated.");
            } elseif ($bulkAction === 'deactivate') {
                $db->prepare("UPDATE document_types SET is_active=0 WHERE id IN ($placeholders)")->execute($ids);
                setFlash('success', count($ids) . " type(s) deactivated.");
            }
            auditLog('BULK_DOC_TYPES', 'document_types', null, "Action=$bulkAction IDs=" . implode(',', $ids));
        }
        redirect(APP_URL . '/admin/document-types.php');
    }
}

// ── Load edit target or blank form ───────────────────────
$editDoc  = null;
$showForm = isset($_GET['add']) || isset($_GET['edit']);

if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM document_types WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editDoc = $stmt->fetch();
    if (!$editDoc) {
        setFlash('error', 'Document type not found.');
        redirect(APP_URL . '/admin/document-types.php');
    }
}

$docTypes = $db->query("SELECT * FROM document_types ORDER BY doc_name")->fetchAll();
?>

<div class="page-header">
    <div>
        <h1><i class="bi bi-file-earmark-plus me-2 text-primary"></i>Document Types</h1>
        <nav aria-label="breadcrumb"><ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Document Types</li>
        </ol></nav>
    </div>
    <?php if (!$showForm): ?>
    <a href="document-types.php?add=1" class="btn btn-primary">
        <i class="bi bi-plus me-1"></i>Add Document Type
    </a>
    <?php endif; ?>
</div>

<?php showFlash(); ?>

<div class="row g-3">

    <!-- ── ADD / EDIT FORM ── -->
    <?php if ($showForm): ?>
    <div class="col-lg-5">
        <div class="card border-primary">
            <div class="card-header bg-primary text-white fw-700">
                <i class="bi bi-<?= $editDoc ? 'pencil' : 'plus-circle' ?> me-2"></i>
                <?= $editDoc ? 'Edit Document Type' : 'Add New Document Type' ?>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="save">
                    <?php if ($editDoc): ?>
                        <input type="hidden" name="doc_id" value="<?= $editDoc['id'] ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label">Document Name <span class="text-danger">*</span></label>
                        <input type="text" name="doc_name" class="form-control"
                               value="<?= htmlspecialchars($editDoc['doc_name'] ?? '') ?>"
                               placeholder="e.g. Form 137 (Permanent Record)" required autofocus>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Short Code</label>
                        <input type="text" name="doc_code" class="form-control"
                               value="<?= htmlspecialchars($editDoc['doc_code'] ?? '') ?>"
                               placeholder="e.g. F137" maxlength="20">
                        <div class="form-text">Used as a short identifier (optional).</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2"
                                  placeholder="Brief description shown to parents..."
                        ><?= htmlspecialchars($editDoc['description'] ?? '') ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Requirements / Notes</label>
                        <textarea name="requirements" class="form-control" rows="2"
                                  placeholder="e.g. Bring original birth certificate, 2x2 photo..."
                        ><?= htmlspecialchars($editDoc['requirements'] ?? '') ?></textarea>
                        <div class="form-text">Shown to parents when requesting this document.</div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label">Processing Days</label>
                            <div class="input-group">
                                <input type="number" name="processing_days" class="form-control"
                                       min="1" max="60"
                                       value="<?= (int)($editDoc['processing_days'] ?? 3) ?>">
                                <span class="input-group-text">day(s)</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Fee</label>
                            <div class="input-group">
                                <span class="input-group-text">₱</span>
                                <input type="number" name="fee" class="form-control"
                                       min="0" step="0.01"
                                       value="<?= number_format((float)($editDoc['fee'] ?? 0), 2, '.', '') ?>">
                            </div>
                            <div class="form-text">Set 0 for free.</div>
                        </div>
                    </div>

                    <div class="form-check form-switch mb-4">
                        <input type="checkbox" name="is_active" class="form-check-input" id="isActive"
                               <?= ($editDoc['is_active'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="isActive">
                            Active <span class="text-muted small">(visible to parents in request form)</span>
                        </label>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="bi bi-save me-1"></i><?= $editDoc ? 'Update' : 'Add' ?> Document Type
                        </button>
                        <a href="document-types.php" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── DOCUMENT TYPES TABLE ── -->
    <div class="<?= $showForm ? 'col-lg-7' : 'col-12' ?>">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><?= count($docTypes) ?> document type(s)</span>
                <?php if (!$showForm): ?>
                <a href="document-types.php?add=1" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-plus me-1"></i>Add New
                </a>
                <?php endif; ?>
            </div>

            <!-- Bulk Toolbar -->
            <div id="bulkToolbar" class="d-none border-bottom px-3 py-2 bg-light">
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <span class="fw-700 text-primary small"><i class="bi bi-check2-square me-1"></i><span id="bulkCount">0</span> selected</span>
                    <form method="POST" data-bulk-form>
                        <input type="hidden" name="action" value="bulk">
                        <select name="bulk_action" class="form-select form-select-sm d-inline-block w-auto me-1">
                            <option value="">— Action —</option>
                            <option value="activate">Activate</option>
                            <option value="deactivate">Deactivate</option>
                            <option value="delete">Delete</option>
                        </select>
                        <button type="submit" class="btn btn-sm btn-outline-primary">Apply</button>
                    </form>
                    <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" onclick="deselectAll()"><i class="bi bi-x me-1"></i>Clear</button>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle" id="docTypesTable">
                        <thead>
                            <tr>
                                <th width="36"><input type="checkbox" class="form-check-input" id="selectAll"></th>
                                <th>Document Name</th>
                                <th>Code</th>
                                <th class="text-center">Days</th>
                                <th class="text-center">Fee</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($docTypes): foreach ($docTypes as $dt): ?>
                            <tr class="<?= $editDoc && $editDoc['id'] == $dt['id'] ? 'table-primary' : '' ?>">
                                <td><input type="checkbox" class="form-check-input" name="ids[]" value="<?= $dt['id'] ?>"></td>
                                <td>
                                    <div class="fw-600"><?= htmlspecialchars($dt['doc_name']) ?></div>
                                    <?php if ($dt['description']): ?>
                                        <div class="small text-muted"><?= htmlspecialchars(mb_strimwidth($dt['description'], 0, 70, '…')) ?></div>
                                    <?php endif; ?>
                                    <?php if ($dt['requirements']): ?>
                                        <div class="small text-info mt-1">
                                            <i class="bi bi-list-check me-1"></i><?= htmlspecialchars(mb_strimwidth($dt['requirements'], 0, 60, '…')) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><code class="small"><?= htmlspecialchars($dt['doc_code'] ?? '—') ?></code></td>
                                <td class="text-center small"><?= (int)$dt['processing_days'] ?></td>
                                <td class="text-center small">
                                    <?= $dt['fee'] > 0 ? '<span class="text-danger fw-600">₱' . number_format($dt['fee'], 2) . '</span>' : '<span class="text-success">Free</span>' ?>
                                </td>
                                <td class="text-center">
                                    <?= $dt['is_active']
                                        ? '<span class="badge bg-success">Active</span>'
                                        : '<span class="badge bg-secondary">Inactive</span>' ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-1 justify-content-center">
                                        <!-- Edit -->
                                        <a href="document-types.php?edit=<?= $dt['id'] ?>"
                                           class="btn btn-sm btn-outline-primary"
                                           data-bs-toggle="tooltip" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <!-- Toggle active -->
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="doc_id" value="<?= $dt['id'] ?>">
                                            <button type="submit"
                                                    class="btn btn-sm <?= $dt['is_active'] ? 'btn-outline-warning' : 'btn-outline-success' ?>"
                                                    data-bs-toggle="tooltip"
                                                    title="<?= $dt['is_active'] ? 'Deactivate' : 'Activate' ?>"
                                                    data-confirm="<?= $dt['is_active'] ? 'Deactivate this document type?' : 'Activate this document type?' ?>">
                                                <i class="bi bi-<?= $dt['is_active'] ? 'eye-slash' : 'eye' ?>"></i>
                                            </button>
                                        </form>
                                        <!-- Delete -->
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="doc_id" value="<?= $dt['id'] ?>">
                                            <button type="submit"
                                                    class="btn btn-sm btn-outline-danger"
                                                    data-bs-toggle="tooltip" title="Delete"
                                                    data-confirm="Delete '<?= htmlspecialchars($dt['doc_name']) ?>'? This cannot be undone.">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">
                                    <i class="bi bi-file-earmark fs-1 d-block mb-2 opacity-25"></i>
                                    No document types yet.
                                    <a href="document-types.php?add=1" class="d-block mt-2">Add the first one</a>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div><!-- end row -->

<?php if ($editDoc): ?>
<script>document.addEventListener('DOMContentLoaded', () => new bootstrap.Modal(document.getElementById('docModal'))?.show());</script>
<?php endif; ?>

<script>
initBulkSelect({ tableId:'docTypesTable', checkboxName:'ids[]', counterId:'bulkCount', toolbarId:'bulkToolbar', selectAllId:'selectAll' });
function deselectAll() {
    document.querySelectorAll('#docTypesTable input[name="ids[]"]').forEach(cb => { cb.checked=false; cb.closest('tr').classList.remove('table-active'); });
    document.getElementById('selectAll').checked=false;
    document.getElementById('bulkToolbar').classList.add('d-none');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
