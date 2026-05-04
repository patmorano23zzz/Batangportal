<?php
$pageTitle = 'Announcements';
require_once __DIR__ . '/../includes/header.php';
requireRole(['parent']);

$db = getDB();

$announcements = $db->query("
    SELECT a.*, u.full_name as poster
    FROM announcements a
    LEFT JOIN users u ON a.posted_by=u.id
    WHERE a.is_published=1 AND a.target_audience IN ('all','parents')
    ORDER BY a.created_at DESC
")->fetchAll();
?>

<div class="page-header">
    <h1><i class="bi bi-megaphone me-2 text-primary"></i>Announcements</h1>
</div>

<?php if ($announcements): foreach ($announcements as $a): ?>
<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex gap-2 mb-2">
            <span class="badge bg-info"><?= ucfirst($a['category']) ?></span>
        </div>
        <h5 class="fw-700"><?= htmlspecialchars($a['title']) ?></h5>
        <p class="mb-2"><?= nl2br(htmlspecialchars($a['content'])) ?></p>
        <div class="small text-muted">
            <i class="bi bi-person me-1"></i><?= htmlspecialchars($a['poster'] ?? 'School Admin') ?>
            &nbsp;·&nbsp;
            <i class="bi bi-clock me-1"></i><?= formatDateTime($a['created_at']) ?>
        </div>
    </div>
</div>
<?php endforeach; else: ?>
<div class="card"><div class="card-body text-center text-muted py-5">No announcements at this time.</div></div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
