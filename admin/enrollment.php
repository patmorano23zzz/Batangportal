<?php
$pageTitle = 'Enrollment';
require_once __DIR__ . '/../includes/header.php';
requireRole(['admin', 'teacher']);

$db = getDB();

// Quick enrollment status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_enrollment'])) {
    $studentId = (int)$_POST['student_id'];
    $status    = sanitize($_POST['enrollment_status']);
    $sectionId = (int)($_POST['section_id'] ?? 0) ?: null;
    $sy        = sanitize($_POST['school_year'] ?? currentSchoolYear());

    $db->prepare("UPDATE students SET enrollment_status=?, section_id=?, school_year=? WHERE id=?")
       ->execute([$status, $sectionId, $sy, $studentId]);

    auditLog('UPDATE_ENROLLMENT', 'students', $studentId, "Status: $status");
    setFlash('success', 'Enrollment updated.');
    redirect(APP_URL . '/admin/enrollment.php');
}

$schoolYear = sanitize($_GET['school_year'] ?? currentSchoolYear());
$gradeId    = (int)($_GET['grade'] ?? 0);

$where  = ['1=1'];
$params = [];
if ($gradeId) { $where[] = "sec.grade_level_id=?"; $params[] = $gradeId; }
$whereStr = implode(' AND ', $where);

$students = $db->prepare("
    SELECT s.*, sec.section_name, gl.grade_name, gl.id as grade_level_id
    FROM students s
    LEFT JOIN sections sec ON s.section_id=sec.id
    LEFT JOIN grade_levels gl ON sec.grade_level_id=gl.id
    WHERE $whereStr
    ORDER BY gl.id, s.last_name, s.first_name
");
$students->execute($params);
$students = $students->fetchAll();

$grades   = $db->query("SELECT * FROM grade_levels ORDER BY id")->fetchAll();
$sections = $db->query("SELECT sec.*, gl.grade_name FROM sections sec JOIN grade_levels gl ON sec.grade_level_id=gl.id ORDER BY gl.id, sec.section_name")->fetchAll();

// Stats
$stats = $db->query("SELECT enrollment_status, COUNT(*) as cnt FROM students GROUP BY enrollment_status")->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<div class="page-header">
    <div>
        <h1><i class="bi bi-person-plus-fill me-2 text-primary"></i>Enrollment</h1>
    </div>
    <a href="student-form.php" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i>Enroll New Student</a>
</div>

<?php showFlash(); ?>

<!-- Stats -->
<div class="row g-3 mb-4">
    <?php
    $statConfig = [
        'enrolled'    => ['Enrolled', 'success', 'bi-person-check'],
        'transferred' => ['Transferred', 'info', 'bi-arrow-right-circle'],
        'dropped'     => ['Dropped', 'danger', 'bi-person-x'],
        'graduated'   => ['Graduated', 'primary', 'bi-mortarboard'],
    ];
    foreach ($statConfig as $s => [$label, $color, $icon]):
    ?>
    <div class="col-6 col-md-3">
        <div class="card text-center">
            <div class="card-body py-3">
                <i class="bi <?= $icon ?> text-<?= $color ?> fs-3 mb-1"></i>
                <div class="fw-800 fs-4"><?= $stats[$s] ?? 0 ?></div>
                <div class="small text-muted"><?= $label ?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filter -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <select name="grade" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Grades</option>
                    <?php foreach ($grades as $g): ?>
                        <option value="<?= $g['id'] ?>" <?= $gradeId==$g['id']?'selected':'' ?>><?= htmlspecialchars($g['grade_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr><th>Student</th><th>Grade & Section</th><th>Status</th><th>School Year</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $s): ?>
                    <tr>
                        <td>
                            <div class="fw-600"><?= htmlspecialchars($s['last_name'] . ', ' . $s['first_name']) ?></div>
                            <div class="small text-muted"><?= htmlspecialchars($s['lrn'] ?? '') ?></div>
                        </td>
                        <td class="small"><?= htmlspecialchars(($s['grade_name'] ?? '') . ' — ' . ($s['section_name'] ?? 'Unassigned')) ?></td>
                        <td><?= statusBadge($s['enrollment_status']) ?></td>
                        <td class="small"><?= htmlspecialchars($s['school_year'] ?? '—') ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#enrollModal"
                                    data-id="<?= $s['id'] ?>" data-status="<?= $s['enrollment_status'] ?>"
                                    data-section="<?= $s['section_id'] ?>" data-sy="<?= htmlspecialchars($s['school_year'] ?? '') ?>">
                                <i class="bi bi-pencil"></i> Update
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Enrollment Update Modal -->
<div class="modal fade" id="enrollModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="update_enrollment" value="1">
                <input type="hidden" name="student_id" id="modalStudentId">
                <div class="modal-header">
                    <h5 class="modal-title">Update Enrollment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Enrollment Status</label>
                        <select name="enrollment_status" id="modalStatus" class="form-select">
                            <option value="enrolled">Enrolled</option>
                            <option value="transferred">Transferred</option>
                            <option value="dropped">Dropped</option>
                            <option value="graduated">Graduated</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Section</label>
                        <select name="section_id" id="modalSection" class="form-select">
                            <option value="">— Unassigned —</option>
                            <?php foreach ($sections as $sec): ?>
                                <option value="<?= $sec['id'] ?>"><?= htmlspecialchars($sec['grade_name'] . ' — ' . $sec['section_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">School Year</label>
                        <input type="text" name="school_year" id="modalSY" class="form-control" value="<?= currentSchoolYear() ?>">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('enrollModal').addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    document.getElementById('modalStudentId').value = btn.dataset.id;
    document.getElementById('modalStatus').value    = btn.dataset.status;
    document.getElementById('modalSection').value   = btn.dataset.section || '';
    document.getElementById('modalSY').value        = btn.dataset.sy || '<?= currentSchoolYear() ?>';
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
