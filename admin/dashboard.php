<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';
requireRole(['admin', 'teacher']);

$db = getDB();

$totalStudents = $db->query("SELECT COUNT(*) FROM students WHERE enrollment_status='enrolled'")->fetchColumn();
$totalRequests = $db->query("SELECT COUNT(*) FROM document_requests")->fetchColumn();
$pendingReqs   = $db->query("SELECT COUNT(*) FROM document_requests WHERE status='pending'")->fetchColumn();
$readyReqs     = $db->query("SELECT COUNT(*) FROM document_requests WHERE status='ready'")->fetchColumn();
$totalParents  = $db->query("SELECT COUNT(*) FROM users WHERE role='parent'")->fetchColumn();
$totalTeachers = $db->query("SELECT COUNT(*) FROM users WHERE role='teacher'")->fetchColumn();

$recentRequests = $db->query("
    SELECT dr.*, dt.doc_name, s.first_name, s.last_name, u.full_name as requester
    FROM document_requests dr
    JOIN document_types dt ON dr.document_type_id=dt.id
    JOIN students s ON dr.student_id=s.id
    JOIN users u ON dr.requested_by=u.id
    ORDER BY dr.date_requested DESC LIMIT 8
")->fetchAll();

$announcements = $db->query("
    SELECT * FROM announcements WHERE is_published=1 ORDER BY created_at DESC LIMIT 4
")->fetchAll();

$reqStats = $db->query("
    SELECT status, COUNT(*) as cnt FROM document_requests GROUP BY status
")->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1><i class="bi bi-speedometer2 me-2 text-primary"></i>Dashboard</h1>
        <p class="text-muted small mb-0">
            <i class="bi bi-calendar3 me-1"></i><?= date('l, F d, Y') ?>
            &nbsp;·&nbsp; S.Y. <?= currentSchoolYear() ?>
        </p>
    </div>
    <?php if (isAdmin()): ?>
    <a href="<?= APP_URL ?>/admin/student-form.php" class="btn btn-primary btn-sm">
        <i class="bi bi-person-plus me-1"></i>Add Student
    </a>
    <?php endif; ?>
</div>

<!-- ── STAT CARDS ── -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <a href="<?= APP_URL ?>/admin/students.php" class="text-decoration-none">
            <div class="stat-card" style="background:linear-gradient(135deg,#1a73e8,#1557b0)">
                <i class="bi bi-people-fill stat-icon"></i>
                <div>
                    <div class="stat-value"><?= number_format($totalStudents) ?></div>
                    <div class="stat-label">Enrolled Students</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-lg-3">
        <a href="<?= APP_URL ?>/admin/requests.php?status=pending" class="text-decoration-none">
            <div class="stat-card" style="background:linear-gradient(135deg,#f59e0b,#d97706)">
                <i class="bi bi-hourglass-split stat-icon"></i>
                <div>
                    <div class="stat-value"><?= number_format($pendingReqs) ?></div>
                    <div class="stat-label">Pending Requests</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-lg-3">
        <a href="<?= APP_URL ?>/admin/requests.php?status=ready" class="text-decoration-none">
            <div class="stat-card" style="background:linear-gradient(135deg,#10b981,#059669)">
                <i class="bi bi-check-circle-fill stat-icon"></i>
                <div>
                    <div class="stat-value"><?= number_format($readyReqs) ?></div>
                    <div class="stat-label">Ready for Release</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-lg-3">
        <a href="<?= APP_URL ?>/admin/requests.php" class="text-decoration-none">
            <div class="stat-card" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed)">
                <i class="bi bi-file-earmark-text-fill stat-icon"></i>
                <div>
                    <div class="stat-value"><?= number_format($totalRequests) ?></div>
                    <div class="stat-label">Total Requests</div>
                </div>
            </div>
        </a>
    </div>
</div>

<!-- ── SECONDARY STATS ── -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card text-center py-3">
            <div class="fw-800 fs-4 text-primary"><?= number_format($totalParents) ?></div>
            <div class="small text-muted fw-600">Parent Accounts</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center py-3">
            <div class="fw-800 fs-4 text-success"><?= number_format($totalTeachers) ?></div>
            <div class="small text-muted fw-600">Teachers</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center py-3">
            <?php $processing = $reqStats['processing'] ?? 0; ?>
            <div class="fw-800 fs-4 text-info"><?= number_format($processing) ?></div>
            <div class="small text-muted fw-600">Processing</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center py-3">
            <?php $released = $reqStats['released'] ?? 0; ?>
            <div class="fw-800 fs-4 text-secondary"><?= number_format($released) ?></div>
            <div class="small text-muted fw-600">Released</div>
        </div>
    </div>
</div>

<!-- ── MAIN CONTENT ROW ── -->
<div class="row g-3">

    <!-- Recent Requests -->
    <div class="col-12 col-xl-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-file-earmark-text me-2 text-primary"></i>Recent Document Requests</span>
                <a href="<?= APP_URL ?>/admin/requests.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if ($recentRequests): ?>
                <!-- Mobile: card list -->
                <div class="d-md-none">
                    <?php foreach ($recentRequests as $r): ?>
                    <div class="p-3 border-bottom d-flex justify-content-between align-items-start gap-2">
                        <div style="min-width:0">
                            <div class="fw-700 small text-truncate"><?= htmlspecialchars($r['first_name'].' '.$r['last_name']) ?></div>
                            <div class="small text-muted text-truncate"><?= htmlspecialchars($r['doc_name']) ?></div>
                            <div class="mt-1"><?= statusBadge($r['status']) ?></div>
                        </div>
                        <a href="<?= APP_URL ?>/admin/requests.php?view=<?= $r['id'] ?>"
                           class="btn btn-sm btn-outline-secondary flex-shrink-0">
                            <i class="bi bi-eye"></i>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <!-- Desktop: table -->
                <div class="d-none d-md-block table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Request #</th>
                                <th>Student</th>
                                <th>Document</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentRequests as $r): ?>
                            <tr>
                                <td><code class="small"><?= htmlspecialchars($r['request_number']) ?></code></td>
                                <td>
                                    <div class="fw-600"><?= htmlspecialchars($r['first_name'].' '.$r['last_name']) ?></div>
                                    <div class="small text-muted">by <?= htmlspecialchars($r['requester']) ?></div>
                                </td>
                                <td class="small"><?= htmlspecialchars($r['doc_name']) ?></td>
                                <td><?= statusBadge($r['status']) ?></td>
                                <td class="small text-muted"><?= formatDate($r['date_requested'],'M d') ?></td>
                                <td>
                                    <a href="<?= APP_URL ?>/admin/requests.php?view=<?= $r['id'] ?>"
                                       class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="p-5 text-center text-muted">
                    <i class="bi bi-file-earmark fs-1 d-block mb-2 opacity-25"></i>No requests yet
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right column -->
    <div class="col-12 col-xl-4">

        <!-- Request Summary -->
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-bar-chart me-2 text-primary"></i>Request Summary</div>
            <div class="card-body">
                <?php
                $statuses = ['pending'=>'warning','processing'=>'info','ready'=>'primary','released'=>'success','rejected'=>'danger'];
                foreach ($statuses as $s => $color):
                    $cnt = $reqStats[$s] ?? 0;
                    $pct = $totalRequests > 0 ? round($cnt / $totalRequests * 100) : 0;
                ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="small fw-700 text-capitalize"><?= $s ?></span>
                        <span class="badge bg-<?= $color ?>"><?= $cnt ?></span>
                    </div>
                    <div class="progress" style="height:7px">
                        <div class="progress-bar bg-<?= $color ?>" style="width:<?= $pct ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Announcements -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-megaphone me-2 text-primary"></i>Announcements</span>
                <a href="<?= APP_URL ?>/admin/announcements.php" class="btn btn-sm btn-outline-primary">Manage</a>
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
                <div class="p-4 text-center text-muted small">No announcements</div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
