<?php
$pageTitle = 'School Documents';
require_once __DIR__ . '/../includes/header.php';
requireRole(['admin', 'teacher']);

$db = getDB();

// Handle upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'upload') {
        $title      = sanitize($_POST['title'] ?? '');
        $category   = sanitize($_POST['category'] ?? 'other');
        $desc       = sanitize($_POST['description'] ?? '');
        $schoolYear = sanitize($_POST['school_year'] ?? currentSchoolYear());
        $isPublic   = isset($_POST['is_public']) ? 1 : 0;

        if (empty($title)) { setFlash('error', 'Title is required.'); redirect(APP_URL . '/admin/school-documents.php'); }
        if (empty($_FILES['document']['name'])) { setFlash('error', 'Please select a file.'); redirect(APP_URL . '/admin/school-documents.php'); }

        $upload = uploadFile($_FILES['document'], 'school-docs');
        if (!$upload['success']) { setFlash('error', $upload['error']); redirect(APP_URL . '/admin/school-documents.php'); }

        $db->prepare("INSERT INTO school_documents (title,category,description,file_path,file_name,file_size,file_type,school_year,uploaded_by,is_public) VALUES (?,?,?,?,?,?,?,?,?,?)")
           ->execute([$title, $category, $desc, $upload['file_path'], $upload['file_name'], $upload['file_size'], $upload['file_type'], $schoolYear, $_SESSION['user_id'], $isPublic]);

        auditLog('UPLOAD_SCHOOL_DOC', 'school_documents', $db->lastInsertId(), "Uploaded: $title");
        setFlash('success', 'Document uploaded successfully.');
        redirect(APP_URL . '/admin/school-documents.php');
    }

    if ($action === 'delete' && isAdmin()) {
        $docId = (int)$_POST['doc_id'];
        $doc = $db->prepare("SELECT * FROM school_documents WHERE id=?");
        $doc->execute([$docId]);
        $doc = $doc->fetch();
        if ($doc) {
            $filePath = __DIR__ . '/../' . $doc['file_path'];
            if (file_exists($filePath)) unlink($filePath);
            $db->prepare("DELETE FROM school_documents WHERE id=?")->execute([$docId]);
            setFlash('success', 'Document deleted.');
        }
        redirect(APP_URL . '/admin/school-documents.php');
    }

    // ── Bulk delete ───────────────────────────────────────
    if ($action === 'bulk_delete' && isAdmin() && isset($_POST['bulk_ids'])) {
        $ids = array_map('intval', (array)$_POST['bulk_ids']);
        $deleted = 0;
        foreach ($ids as $did) {
            $doc = $db->prepare("SELECT * FROM school_documents WHERE id=?");
            $doc->execute([$did]);
            $doc = $doc->fetch();
            if ($doc) {
                $fp = __DIR__ . '/../' . $doc['file_path'];
                if (file_exists($fp)) unlink($fp);
                $db->prepare("DELETE FROM school_documents WHERE id=?")->execute([$did]);
                $deleted++;
            }
        }
        auditLog('BULK_DELETE_SCHOOL_DOCS', 'school_documents', null, "Deleted IDs: ".implode(',',$ids));
        setFlash('success', "$deleted document(s) deleted.");
        redirect(APP_URL . '/admin/school-documents.php');
    }
}

$category   = sanitize($_GET['category'] ?? '');
$search     = sanitize($_GET['search'] ?? '');
$schoolYear = sanitize($_GET['school_year'] ?? '');

$where  = ['1=1'];
$params = [];
if ($category) { $where[] = "category=?"; $params[] = $category; }
if ($search)   { $where[] = "title LIKE ?"; $params[] = "%$search%"; }
if ($schoolYear) { $where[] = "school_year=?"; $params[] = $schoolYear; }

$whereStr = implode(' AND ', $where);
$stmt = $db->prepare("SELECT sd.*, u.full_name as uploader FROM school_documents sd LEFT JOIN users u ON sd.uploaded_by=u.id WHERE $whereStr ORDER BY sd.created_at DESC");
$stmt->execute($params);
$documents = $stmt->fetchAll();

$categories = ['memorandum','policy','form','report','announcement','other'];
$years = $db->query("SELECT DISTINCT school_year FROM school_documents ORDER BY school_year DESC")->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="page-header">
    <div>
        <h1><i class="bi bi-folder-fill me-2 text-primary"></i>School Documents</h1>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
        <i class="bi bi-upload me-1"></i>Upload Document
    </button>
</div>

<?php showFlash(); ?>

<!-- Bulk Toolbar -->
<?php if (isAdmin()): ?>
<div id="bulkToolbar" class="d-none mb-2">
    <div class="card border-primary">
        <div class="card-body py-2 d-flex align-items-center gap-3 flex-wrap">
            <span class="fw-700 text-primary small"><i class="bi bi-check2-square me-1"></i><span id="bulkCount">0</span> selected</span>
            <form method="POST" data-bulk-form data-confirm-label="Delete">
                <input type="hidden" name="action" value="bulk_delete">
                <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash me-1"></i>Delete Selected</button>
            </form>
            <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" onclick="deselectAll()"><i class="bi bi-x me-1"></i>Clear</button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Filters -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search documents..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-2">
                <select name="category" class="form-select form-select-sm">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat ?>" <?= $category==$cat?'selected':'' ?>><?= ucfirst($cat) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="school_year" class="form-select form-select-sm">
                    <option value="">All Years</option>
                    <?php foreach ($years as $y): ?>
                        <option value="<?= $y ?>" <?= $schoolYear==$y?'selected':'' ?>><?= $y ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1"><i class="bi bi-search"></i></button>
                <a href="school-documents.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="schoolDocsTable">
                <thead>
                    <tr>
                        <?php if (isAdmin()): ?><th width="36"><input type="checkbox" class="form-check-input" id="selectAll"></th><?php endif; ?>
                        <th>Title</th>
                        <th>Category</th>
                        <th>School Year</th>
                        <th>Visibility</th>
                        <th>Uploaded By</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($documents): foreach ($documents as $doc): ?>
                    <tr>
                        <?php if (isAdmin()): ?><td><input type="checkbox" class="form-check-input" name="ids[]" value="<?= $doc['id'] ?>"></td><?php endif; ?>
                        <td>
                            <div class="fw-600"><?= htmlspecialchars($doc['title']) ?></div>
                            <?php if ($doc['description']): ?>
                                <div class="small text-muted"><?= htmlspecialchars(substr($doc['description'], 0, 80)) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge bg-info"><?= ucfirst($doc['category']) ?></span></td>
                        <td><?= htmlspecialchars($doc['school_year'] ?? '—') ?></td>
                        <td><?= $doc['is_public'] ? '<span class="badge bg-success">Public</span>' : '<span class="badge bg-secondary">Admin Only</span>' ?></td>
                        <td class="small"><?= htmlspecialchars($doc['uploader'] ?? '—') ?></td>
                        <td class="small text-muted"><?= formatDate($doc['created_at'], 'M d, Y') ?></td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="<?= APP_URL . '/' . $doc['file_path'] ?>" target="_blank" class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="View/Download">
                                    <i class="bi bi-download"></i>
                                </a>
                                <?php if (isAdmin()): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="doc_id" value="<?= $doc['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Delete this document?">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="<?= isAdmin() ? 8 : 7 ?>" class="text-center text-muted py-5">
                        <i class="bi bi-folder2-open fs-1 d-block mb-2 opacity-25"></i>No documents uploaded yet
                    </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1">    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload">
                <div class="modal-header">
                    <h5 class="modal-title">Upload School Document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <select name="category" class="form-select">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat ?>"><?= ucfirst($cat) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">School Year</label>
                        <input type="text" name="school_year" class="form-control" value="<?= currentSchoolYear() ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">File <span class="text-danger">*</span></label>
                        <input type="file" name="document" class="form-control" required accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                        <div class="form-text">Allowed: PDF, Word, Excel, Images. Max 10MB.</div>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="is_public" class="form-check-input" id="isPublic">
                        <label class="form-check-label" for="isPublic">Visible to parents/guardians</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-upload me-1"></i>Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if (isAdmin()): ?>
<script>
initBulkSelect({ tableId:'schoolDocsTable', checkboxName:'ids[]', counterId:'bulkCount', toolbarId:'bulkToolbar', selectAllId:'selectAll' });
function deselectAll() {
    document.querySelectorAll('#schoolDocsTable input[name="ids[]"]').forEach(cb => { cb.checked=false; cb.closest('tr').classList.remove('table-active'); });
    document.getElementById('selectAll').checked=false;
    document.getElementById('bulkToolbar').classList.add('d-none');
}
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
