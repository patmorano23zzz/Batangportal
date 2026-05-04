<?php
$pageTitle = 'School Documents';
require_once __DIR__ . '/../includes/header.php';
requireRole(['parent']);

$db = getDB();

$documents = $db->query("
    SELECT sd.*, u.full_name as uploader
    FROM school_documents sd
    LEFT JOIN users u ON sd.uploaded_by=u.id
    WHERE sd.is_public=1
    ORDER BY sd.created_at DESC
")->fetchAll();
?>

<div class="page-header">
    <h1><i class="bi bi-folder-fill me-2 text-primary"></i>School Documents</h1>
</div>

<?php if ($documents): ?>
<div class="row g-3">
    <?php foreach ($documents as $doc): ?>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-start gap-3">
                    <div class="text-primary" style="font-size:2rem">
                        <?php
                        $icons = ['pdf'=>'bi-file-earmark-pdf','doc'=>'bi-file-earmark-word','docx'=>'bi-file-earmark-word','xls'=>'bi-file-earmark-excel','xlsx'=>'bi-file-earmark-excel'];
                        $icon = $icons[$doc['file_type']] ?? 'bi-file-earmark';
                        ?>
                        <i class="bi <?= $icon ?>"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-700"><?= htmlspecialchars($doc['title']) ?></div>
                        <div class="small text-muted"><?= htmlspecialchars($doc['description'] ?? '') ?></div>
                        <div class="small text-muted mt-1">
                            <span class="badge bg-info"><?= ucfirst($doc['category']) ?></span>
                            <?php if ($doc['school_year']): ?>
                                <span class="ms-1">S.Y. <?= htmlspecialchars($doc['school_year']) ?></span>
                            <?php endif; ?>
                        </div>
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
<div class="card"><div class="card-body text-center text-muted py-5">No public documents available.</div></div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
