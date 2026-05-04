<?php
$pageTitle = 'My Documents';
require_once __DIR__ . '/../includes/header.php';
requireRole(['parent']);

$db = getDB();
$userId = $_SESSION['user_id'];

// Get children
$children = $db->prepare("SELECT id, first_name, last_name FROM students WHERE parent_user_id=?");
$children->execute([$userId]);
$children = $children->fetchAll();

$studentId = (int)($_GET['student_id'] ?? ($children[0]['id'] ?? 0));

// Verify ownership
$owned = false;
foreach ($children as $c) {
    if ($c['id'] == $studentId) { $owned = true; break; }
}

$docs = [];
if ($owned) {
    $stmt = $db->prepare("SELECT * FROM student_documents WHERE student_id=? ORDER BY created_at DESC");
    $stmt->execute([$studentId]);
    $docs = $stmt->fetchAll();
}
?>

<div class="page-header">
    <h1><i class="bi bi-folder2-open me-2 text-primary"></i>My Documents</h1>
</div>

<!-- Child Selector -->
<?php if (count($children) > 1): ?>
<div class="mb-3">
    <form method="GET" class="d-flex gap-2 align-items-center">
        <label class="form-label mb-0 small fw-600">Child:</label>
        <select name="student_id" class="form-select form-select-sm" style="max-width:250px" onchange="this.form.submit()">
            <?php foreach ($children as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $studentId==$c['id']?'selected':'' ?>>
                    <?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>
</div>
<?php endif; ?>

<?php if ($docs): ?>
<div class="row g-3">
    <?php foreach ($docs as $doc): ?>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-start gap-3">
                    <div class="text-primary" style="font-size:2rem">
                        <?php $icons = ['pdf'=>'bi-file-earmark-pdf','doc'=>'bi-file-earmark-word','docx'=>'bi-file-earmark-word','jpg'=>'bi-file-earmark-image','jpeg'=>'bi-file-earmark-image','png'=>'bi-file-earmark-image']; ?>
                        <i class="bi <?= $icons[$doc['file_type']] ?? 'bi-file-earmark' ?>"></i>
                    </div>
                    <div>
                        <div class="fw-700 small"><?= htmlspecialchars($doc['doc_type']) ?></div>
                        <?php if ($doc['title']): ?><div class="small text-muted"><?= htmlspecialchars($doc['title']) ?></div><?php endif; ?>
                        <div class="small text-muted"><?= formatDate($doc['created_at'], 'M d, Y') ?></div>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-transparent">
                <a href="<?= APP_URL . '/' . $doc['file_path'] ?>" target="_blank" class="btn btn-sm btn-outline-primary w-100">
                    <i class="bi bi-download me-1"></i>Download
                </a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="card"><div class="card-body text-center text-muted py-5">No documents available yet.</div></div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
