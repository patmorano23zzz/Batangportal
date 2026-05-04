<?php
$pageTitle = 'My Dashboard';
require_once __DIR__ . '/../includes/header.php';
requireRole(['parent']);

$db = getDB();
$userId = $_SESSION['user_id'];

// Get linked children
$children = $db->prepare("
    SELECT s.*, sec.section_name, gl.grade_name
    FROM students s
    LEFT JOIN sections sec ON s.section_id=sec.id
    LEFT JOIN grade_levels gl ON sec.grade_level_id=gl.id
    WHERE s.parent_user_id=? AND s.enrollment_status='enrolled'
    ORDER BY s.last_name, s.first_name
");
$children->execute([$userId]);
$children = $children->fetchAll();

// My recent requests
$myRequests = $db->prepare("
    SELECT dr.*, dt.doc_name
    FROM document_requests dr
    JOIN document_types dt ON dr.document_type_id=dt.id
    WHERE dr.requested_by=?
    ORDER BY dr.date_requested DESC LIMIT 5
");
$myRequests->execute([$userId]);
$myRequests = $myRequests->fetchAll();

// Announcements
$announcements = $db->query("
    SELECT * FROM announcements
    WHERE is_published=1 AND target_audience IN ('all','parents')
    ORDER BY created_at DESC LIMIT 5
")->fetchAll();
?>

<div class="page-header">
    <div>
        <h1><i class="bi bi-house-fill me-2 text-primary"></i>Welcome, <?= htmlspecialchars(explode(' ', $_SESSION['full_name'])[0]) ?>!</h1>
        <p class="text-muted mb-0 small"><?= date('l, F d, Y') ?> &nbsp;·&nbsp; S.Y. <?= currentSchoolYear() ?></p>
    </div>
    <a href="<?= APP_URL ?>/portal/request-document.php" class="btn btn-primary">
        <i class="bi bi-file-earmark-plus me-1"></i>Request Document
    </a>
</div>

<!-- Children Cards -->
<?php if ($children): ?>
<div class="row g-3 mb-4">
    <?php foreach ($children as $child): ?>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <?php if (!empty($child['profile_photo'])): ?>
                    <img src="<?= APP_URL . '/' . $child['profile_photo'] ?>" class="rounded-circle" width="56" height="56" style="object-fit:cover">
                <?php else: ?>
                    <div class="avatar" style="width:56px;height:56px;font-size:1.4rem"><?= strtoupper(substr($child['first_name'], 0, 1)) ?></div>
                <?php endif; ?>
                <div>
                    <div class="fw-700"><?= htmlspecialchars($child['first_name'] . ' ' . $child['last_name']) ?></div>
                    <div class="small text-muted"><?= htmlspecialchars($child['grade_name'] ?? '—') ?> — <?= htmlspecialchars($child['section_name'] ?? '—') ?></div>
                    <div class="small text-muted">LRN: <?= htmlspecialchars($child['lrn'] ?? '—') ?></div>
                </div>
            </div>
            <div class="card-footer bg-transparent d-flex gap-2">
                <a href="<?= APP_URL ?>/portal/grades.php?student_id=<?= $child['id'] ?>" class="btn btn-sm btn-outline-primary flex-grow-1">
                    <i class="bi bi-journal-check me-1"></i>Grades
                </a>
                <a href="<?= APP_URL ?>/portal/attendance.php?student_id=<?= $child['id'] ?>" class="btn btn-sm btn-outline-secondary flex-grow-1">
                    <i class="bi bi-calendar-check me-1"></i>Attendance
                </a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="alert alert-info">
    <i class="bi bi-info-circle me-2"></i>No enrolled children linked to your account. Please contact the school administrator.
</div>
<?php endif; ?>

<div class="row g-3">
    <!-- Recent Requests -->
    <div class="col-md-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-file-earmark-text me-2 text-primary"></i>My Recent Requests</span>
                <a href="<?= APP_URL ?>/portal/my-requests.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Request #</th>
                            <th>Document</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($myRequests): foreach ($myRequests as $r): ?>
                        <tr>
                            <td><code class="small"><?= htmlspecialchars($r['request_number']) ?></code></td>
                            <td class="small"><?= htmlspecialchars($r['doc_name']) ?></td>
                            <td><?= statusBadge($r['status']) ?></td>
                            <td class="small text-muted"><?= formatDate($r['date_requested'], 'M d') ?></td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="4" class="text-center text-muted py-4">No requests yet</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Announcements -->
    <div class="col-md-5">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-megaphone me-2 text-primary"></i>Announcements</span>
                <a href="<?= APP_URL ?>/portal/announcements.php" class="btn btn-sm btn-outline-primary">All</a>
            </div>
            <div class="card-body p-0">
                <?php if ($announcements): foreach ($announcements as $a): ?>
                <div class="p-3 border-bottom">
                    <div class="d-flex gap-2 align-items-start">
                        <span class="badge bg-info mt-1"><?= ucfirst($a['category']) ?></span>
                        <div>
                            <div class="fw-600 small"><?= htmlspecialchars($a['title']) ?></div>
                            <div class="text-muted" style="font-size:.75rem"><?= formatDate($a['created_at'], 'M d, Y') ?></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; else: ?>
                <div class="p-3 text-muted small text-center">No announcements</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
