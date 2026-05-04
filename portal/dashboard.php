<?php
$pageTitle = 'My Dashboard';
require_once __DIR__ . '/../includes/header.php';
requireRole(['parent']);

$db     = getDB();
$userId = $_SESSION['user_id'];

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

$myRequests = $db->prepare("
    SELECT dr.*, dt.doc_name
    FROM document_requests dr
    JOIN document_types dt ON dr.document_type_id=dt.id
    WHERE dr.requested_by=?
    ORDER BY dr.date_requested DESC LIMIT 5
");
$myRequests->execute([$userId]);
$myRequests = $myRequests->fetchAll();

$announcements = $db->query("
    SELECT * FROM announcements
    WHERE is_published=1 AND target_audience IN ('all','parents')
    ORDER BY created_at DESC LIMIT 4
")->fetchAll();

// Count pending requests
$pendingCount = $db->prepare("SELECT COUNT(*) FROM document_requests WHERE requested_by=? AND status='pending'");
$pendingCount->execute([$userId]);
$pendingCount = $pendingCount->fetchColumn();

$readyCount = $db->prepare("SELECT COUNT(*) FROM document_requests WHERE requested_by=? AND status='ready'");
$readyCount->execute([$userId]);
$readyCount = $readyCount->fetchColumn();

$firstName = htmlspecialchars(explode(' ', $_SESSION['full_name'])[0]);
?>

<!-- Welcome Header -->
<div class="page-header">
    <div>
        <h1>👋 Welcome back, <?= $firstName ?>!</h1>
        <p class="text-muted small mb-0">
            <i class="bi bi-calendar3 me-1"></i><?= date('l, F d, Y') ?>
            &nbsp;·&nbsp; S.Y. <?= currentSchoolYear() ?>
        </p>
    </div>
    <a href="<?= APP_URL ?>/portal/request-document.php" class="btn btn-primary">
        <i class="bi bi-file-earmark-plus me-1"></i>
        <span class="d-none d-sm-inline">Request Document</span>
        <span class="d-sm-none">Request</span>
    </a>
</div>

<!-- ── MINI STAT CARDS ── -->
<?php if ($children): ?>
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card" style="background:linear-gradient(135deg,#1a73e8,#1557b0)">
            <i class="bi bi-people-fill stat-icon"></i>
            <div>
                <div class="stat-value"><?= count($children) ?></div>
                <div class="stat-label">My Children</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card" style="background:linear-gradient(135deg,#f59e0b,#d97706)">
            <i class="bi bi-hourglass-split stat-icon"></i>
            <div>
                <div class="stat-value"><?= $pendingCount ?></div>
                <div class="stat-label">Pending Requests</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <a href="<?= APP_URL ?>/portal/my-requests.php?status=ready" class="text-decoration-none">
            <div class="stat-card" style="background:linear-gradient(135deg,#10b981,#059669)">
                <i class="bi bi-bell-fill stat-icon"></i>
                <div>
                    <div class="stat-value"><?= $readyCount ?></div>
                    <div class="stat-label">Ready for Pickup</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="<?= APP_URL ?>/portal/my-requests.php" class="text-decoration-none">
            <div class="stat-card" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed)">
                <i class="bi bi-file-earmark-text-fill stat-icon"></i>
                <div>
                    <div class="stat-value"><?= count($myRequests) ?></div>
                    <div class="stat-label">Recent Requests</div>
                </div>
            </div>
        </a>
    </div>
</div>
<?php endif; ?>

<!-- ── QUICK ACTIONS ── -->
<div class="row g-2 mb-4">
    <div class="col-4 col-sm-2">
        <a href="<?= APP_URL ?>/portal/request-document.php" class="quick-action"
           style="background:linear-gradient(135deg,#1a73e8,#1557b0)">
            <i class="bi bi-file-earmark-plus"></i>Request Doc
        </a>
    </div>
    <div class="col-4 col-sm-2">
        <a href="<?= APP_URL ?>/portal/grades.php" class="quick-action"
           style="background:linear-gradient(135deg,#10b981,#059669)">
            <i class="bi bi-journal-check"></i>Grades
        </a>
    </div>
    <div class="col-4 col-sm-2">
        <a href="<?= APP_URL ?>/portal/attendance.php" class="quick-action"
           style="background:linear-gradient(135deg,#f59e0b,#d97706)">
            <i class="bi bi-calendar-check"></i>Attendance
        </a>
    </div>
    <div class="col-4 col-sm-2">
        <a href="<?= APP_URL ?>/portal/my-requests.php" class="quick-action"
           style="background:linear-gradient(135deg,#8b5cf6,#7c3aed)">
            <i class="bi bi-clock-history"></i>My Requests
        </a>
    </div>
    <div class="col-4 col-sm-2">
        <a href="<?= APP_URL ?>/portal/announcements.php" class="quick-action"
           style="background:linear-gradient(135deg,#ef4444,#dc2626)">
            <i class="bi bi-megaphone"></i>News
        </a>
    </div>
    <div class="col-4 col-sm-2">
        <a href="<?= APP_URL ?>/portal/student-documents.php" class="quick-action"
           style="background:linear-gradient(135deg,#0891b2,#0e7490)">
            <i class="bi bi-folder2-open"></i>Documents
        </a>
    </div>
</div>

<!-- ── CHILDREN CARDS ── -->
<?php if ($children): ?>
<h6 class="fw-800 text-muted mb-2" style="font-size:.75rem;text-transform:uppercase;letter-spacing:.08em">
    <i class="bi bi-people me-1"></i>My Children
</h6>
<div class="row g-3 mb-4">
    <?php
    $bannerColors = ['#1a73e8','#10b981','#f59e0b','#8b5cf6','#ef4444','#0891b2'];
    foreach ($children as $i => $child):
        $color = $bannerColors[$i % count($bannerColors)];
    ?>
    <div class="col-12 col-sm-6 col-lg-4">
        <div class="child-card card">
            <div class="child-banner" style="background:<?= $color ?>"></div>
            <div class="card-body">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <?php if (!empty($child['profile_photo'])): ?>
                        <img src="<?= APP_URL.'/'.$child['profile_photo'] ?>"
                             class="rounded-circle flex-shrink-0"
                             width="52" height="52" style="object-fit:cover;border:3px solid <?= $color ?>20">
                    <?php else: ?>
                        <div class="avatar flex-shrink-0"
                             style="width:52px;height:52px;font-size:1.3rem;background:<?= $color ?>18;color:<?= $color ?>">
                            <?= strtoupper(substr($child['first_name'],0,1)) ?>
                        </div>
                    <?php endif; ?>
                    <div style="min-width:0">
                        <div class="fw-800 text-truncate">
                            <?= htmlspecialchars($child['first_name'].' '.$child['last_name']) ?>
                        </div>
                        <div class="small text-muted">
                            <?= htmlspecialchars($child['grade_name'] ?? '—') ?>
                            <?= $child['section_name'] ? ' — '.htmlspecialchars($child['section_name']) : '' ?>
                        </div>
                        <?php if ($child['lrn']): ?>
                        <div class="small text-muted">LRN: <?= htmlspecialchars($child['lrn']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?= APP_URL ?>/portal/grades.php?student_id=<?= $child['id'] ?>"
                       class="btn btn-sm flex-grow-1 fw-700"
                       style="background:<?= $color ?>18;color:<?= $color ?>;border:1px solid <?= $color ?>30">
                        <i class="bi bi-journal-check me-1"></i>Grades
                    </a>
                    <a href="<?= APP_URL ?>/portal/attendance.php?student_id=<?= $child['id'] ?>"
                       class="btn btn-sm btn-outline-secondary flex-grow-1">
                        <i class="bi bi-calendar-check me-1"></i>Attendance
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="alert alert-info d-flex align-items-center gap-2 mb-4">
    <i class="bi bi-info-circle-fill fs-5"></i>
    <div>No enrolled children linked to your account. Please contact the school administrator.</div>
</div>
<?php endif; ?>

<!-- ── BOTTOM ROW ── -->
<div class="row g-3">

    <!-- Recent Requests -->
    <div class="col-12 col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-file-earmark-text me-2 text-primary"></i>My Recent Requests</span>
                <a href="<?= APP_URL ?>/portal/my-requests.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if ($myRequests): ?>
                <!-- Mobile view -->
                <div class="d-md-none">
                    <?php foreach ($myRequests as $r): ?>
                    <div class="p-3 border-bottom d-flex justify-content-between align-items-center gap-2">
                        <div style="min-width:0">
                            <div class="small fw-700 text-truncate"><?= htmlspecialchars($r['doc_name']) ?></div>
                            <div class="small text-muted"><?= formatDate($r['date_requested'],'M d, Y') ?></div>
                        </div>
                        <?= statusBadge($r['status']) ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <!-- Desktop view -->
                <div class="d-none d-md-block table-responsive">
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
                            <?php foreach ($myRequests as $r): ?>
                            <tr>
                                <td><code class="small"><?= htmlspecialchars($r['request_number']) ?></code></td>
                                <td class="small"><?= htmlspecialchars($r['doc_name']) ?></td>
                                <td><?= statusBadge($r['status']) ?></td>
                                <td class="small text-muted"><?= formatDate($r['date_requested'],'M d') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="p-5 text-center text-muted">
                    <i class="bi bi-file-earmark fs-1 d-block mb-2 opacity-25"></i>
                    No requests yet.
                    <div class="mt-2">
                        <a href="<?= APP_URL ?>/portal/request-document.php" class="btn btn-sm btn-primary">
                            <i class="bi bi-plus me-1"></i>Make a Request
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Announcements -->
    <div class="col-12 col-lg-5">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-megaphone me-2 text-primary"></i>Announcements</span>
                <a href="<?= APP_URL ?>/portal/announcements.php" class="btn btn-sm btn-outline-primary">All</a>
            </div>
            <div class="card-body p-0">
                <?php if ($announcements): foreach ($announcements as $a): ?>
                <div class="ann-item">
                    <div class="d-flex gap-2 align-items-start">
                        <span class="badge bg-info mt-1 flex-shrink-0"><?= ucfirst($a['category']) ?></span>
                        <div style="min-width:0">
                            <div class="fw-700 small text-truncate"><?= htmlspecialchars($a['title']) ?></div>
                            <div class="text-muted" style="font-size:.73rem"><?= formatDate($a['created_at'],'M d, Y') ?></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; else: ?>
                <div class="p-4 text-center text-muted small">No announcements at this time.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
