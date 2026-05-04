<?php
$pageTitle = 'My Children';
require_once __DIR__ . '/../includes/header.php';
requireRole(['parent']);

$db = getDB();
$userId = $_SESSION['user_id'];

$children = $db->prepare("
    SELECT s.*, sec.section_name, gl.grade_name
    FROM students s
    LEFT JOIN sections sec ON s.section_id=sec.id
    LEFT JOIN grade_levels gl ON sec.grade_level_id=gl.id
    WHERE s.parent_user_id=?
    ORDER BY s.last_name, s.first_name
");
$children->execute([$userId]);
$children = $children->fetchAll();
?>

<div class="page-header">
    <h1><i class="bi bi-people-fill me-2 text-primary"></i>My Children</h1>
</div>

<?php if ($children): ?>
<div class="row g-3">
    <?php foreach ($children as $child): ?>
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <?php if (!empty($child['profile_photo'])): ?>
                        <img src="<?= APP_URL . '/' . $child['profile_photo'] ?>" class="rounded-circle" width="64" height="64" style="object-fit:cover">
                    <?php else: ?>
                        <div class="avatar" style="width:64px;height:64px;font-size:1.6rem"><?= strtoupper(substr($child['first_name'], 0, 1)) ?></div>
                    <?php endif; ?>
                    <div>
                        <h5 class="fw-700 mb-0"><?= htmlspecialchars($child['first_name'] . ' ' . ($child['middle_name'] ? $child['middle_name'][0] . '. ' : '') . $child['last_name']) ?></h5>
                        <div class="text-muted small"><?= htmlspecialchars($child['grade_name'] ?? '—') ?> — <?= htmlspecialchars($child['section_name'] ?? '—') ?></div>
                        <?= statusBadge($child['enrollment_status']) ?>
                    </div>
                </div>

                <table class="table table-sm table-borderless mb-0">
                    <tr><td class="text-muted small" width="40%">LRN</td><td class="small fw-600"><?= htmlspecialchars($child['lrn'] ?? '—') ?></td></tr>
                    <tr><td class="text-muted small">Date of Birth</td><td class="small"><?= formatDate($child['date_of_birth']) ?> (<?= age($child['date_of_birth']) ?> yrs)</td></tr>
                    <tr><td class="text-muted small">Gender</td><td class="small"><?= htmlspecialchars($child['gender']) ?></td></tr>
                    <tr><td class="text-muted small">School Year</td><td class="small"><?= htmlspecialchars($child['school_year'] ?? '—') ?></td></tr>
                </table>
            </div>
            <div class="card-footer bg-transparent d-flex gap-2 flex-wrap">
                <a href="grades.php?student_id=<?= $child['id'] ?>" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-journal-check me-1"></i>Grades
                </a>
                <a href="attendance.php?student_id=<?= $child['id'] ?>" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-calendar-check me-1"></i>Attendance
                </a>
                <a href="request-document.php?student_id=<?= $child['id'] ?>" class="btn btn-sm btn-outline-success">
                    <i class="bi bi-file-earmark-plus me-1"></i>Request Doc
                </a>
                <a href="student-documents.php?student_id=<?= $child['id'] ?>" class="btn btn-sm btn-outline-info">
                    <i class="bi bi-folder2-open me-1"></i>Documents
                </a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="card">
    <div class="card-body text-center text-muted py-5">
        <i class="bi bi-people fs-1 d-block mb-2 opacity-25"></i>
        No children linked to your account.<br>
        <span class="small">Please contact the school administrator to link your child's record.</span>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
