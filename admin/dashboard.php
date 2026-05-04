<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';
requireRole(['admin', 'teacher']);

$db = getDB();

// Stats
$totalStudents  = $db->query("SELECT COUNT(*) FROM students WHERE enrollment_status='enrolled'")->fetchColumn();
$totalRequests  = $db->query("SELECT COUNT(*) FROM document_requests")->fetchColumn();
$pendingReqs    = $db->query("SELECT COUNT(*) FROM document_requests WHERE status='pending'")->fetchColumn();
$readyReqs      = $db->query("SELECT COUNT(*) FROM document_requests WHERE status='ready'")->fetchColumn();
$totalParents   = $db->query("SELECT COUNT(*) FROM users WHERE role='parent'")->fetchColumn();
$totalTeachers  = $db->query("SELECT COUNT(*) FROM users WHERE role='teacher'")->fetchColumn();

// Recent requests
$recentRequests = $db->query("
    SELECT dr.*, dt.doc_name, s.first_name, s.last_name, u.full_name as requester
    FROM document_requests dr
    JOIN document_types dt ON dr.document_type_id = dt.id
    JOIN students s ON dr.student_id = s.id
    JOIN users u ON dr.requested_by = u.id
    ORDER BY dr.date_requested DESC LIMIT 8
")->fetchAll();

// Recent announcements
$announcements = $db->query("
    SELECT * FROM announcements WHERE is_published=1 ORDER BY created_at DESC LIMIT 5
")->fetchAll();

// Requests by status (for chart)
$reqStats = $db->query("
    SELECT status, COUNT(*) as cnt FROM document_requests GROUP BY status
")->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<div class="page-header">
    <div>
        <h1><i class="bi bi-speedometer2 me-2 text-primary"></i>Dashboard</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item active">Dashboard</li>
            </ol>
        </nav>
    </div>
    <div class="text-muted small">
        <i class="bi bi-calendar3 me-1"></i><?= date('l, F d, Y') ?>
        &nbsp;|&nbsp; S.Y. <?= currentSchoolYear() ?>
    </div>
</div>

<!-- STAT CARDS -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card" style="background:linear-gradient(135deg,#1a73e8,#1557b0)">
            <i class="bi bi-people-fill stat-icon"></i>
            <div>
                <div class="stat-value"><?= number_format($totalStudents) ?></div>
                <div class="stat-label">Enrolled Students</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card" style="background:linear-gradient(135deg,#f59e0b,#d97706)">
            <i class="bi bi-hourglass-split stat-icon"></i>
            <div>
                <div class="stat-value"><?= number_format($pendingReqs) ?></div>
                <div class="stat-label">Pending Requests</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card" style="background:linear-gradient(135deg,#10b981,#059669)">
            <i class="bi bi-check-circle-fill stat-icon"></i>
            <div>
                <div class="stat-value"><?= number_format($readyReqs) ?></div>
                <div class="stat-label">Ready for Release</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed)">
            <i class="bi bi-file-earmark-text-fill stat-icon"></i>
            <div>
                <div class="stat-value"><?= number_format($totalRequests) ?></div>
                <div class="stat-label">Total Requests</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Recent Requests -->
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-file-earmark-text me-2 text-primary"></i>Recent Document Requests</span>
                <a href="<?= APP_URL ?>/admin/requests.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
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
                            <?php if ($recentRequests): foreach ($recentRequests as $r): ?>
                            <tr>
                                <td><code class="small"><?= htmlspecialchars($r['request_number']) ?></code></td>
                                <td>
                                    <div class="fw-600"><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?></div>
                                    <div class="small text-muted">by <?= htmlspecialchars($r['requester']) ?></div>
                                </td>
                                <td class="small"><?= htmlspecialchars($r['doc_name']) ?></td>
                                <td><?= statusBadge($r['status']) ?></td>
                                <td class="small text-muted"><?= formatDate($r['date_requested'], 'M d') ?></td>
                                <td>
                                    <a href="<?= APP_URL ?>/admin/requests.php?view=<?= $r['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; else: ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No requests yet</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column -->
    <div class="col-lg-4">
        <!-- Quick Stats -->
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-bar-chart me-2 text-primary"></i>Request Summary</div>
            <div class="card-body">
                <?php
                $statuses = ['pending'=>'warning','processing'=>'info','ready'=>'primary','released'=>'success','rejected'=>'danger'];
                foreach ($statuses as $s => $color):
                    $cnt = $reqStats[$s] ?? 0;
                    $pct = $totalRequests > 0 ? round($cnt / $totalRequests * 100) : 0;
                ?>
                <div class="mb-2">
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="fw-600 text-capitalize"><?= $s ?></span>
                        <span><?= $cnt ?></span>
                    </div>
                    <div class="progress" style="height:6px">
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
                <div class="p-3 border-bottom">
                    <div class="fw-600 small"><?= htmlspecialchars($a['title']) ?></div>
                    <div class="text-muted" style="font-size:.78rem"><?= formatDate($a['created_at'], 'M d, Y') ?></div>
                </div>
                <?php endforeach; else: ?>
                <div class="p-3 text-muted small text-center">No announcements</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
