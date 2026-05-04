<?php
$pageTitle = 'Attendance';
require_once __DIR__ . '/../includes/header.php';
requireRole(['admin', 'teacher']);

$db = getDB();

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    $date      = sanitize($_POST['date']);
    $sectionId = (int)$_POST['section_id'];
    $records   = $_POST['attendance'] ?? [];

    foreach ($records as $studentId => $status) {
        $studentId = (int)$studentId;
        $status    = sanitize($status);
        $remarks   = sanitize($_POST['remarks'][$studentId] ?? '');

        $existing = $db->prepare("SELECT id FROM attendance WHERE student_id=? AND date=?");
        $existing->execute([$studentId, $date]);
        $row = $existing->fetch();

        if ($row) {
            $db->prepare("UPDATE attendance SET status=?, remarks=?, recorded_by=? WHERE id=?")
               ->execute([$status, $remarks, $_SESSION['user_id'], $row['id']]);
        } else {
            $db->prepare("INSERT INTO attendance (student_id,date,status,remarks,recorded_by) VALUES (?,?,?,?,?)")
               ->execute([$studentId, $date, $status, $remarks, $_SESSION['user_id']]);
        }
    }

    auditLog('SAVE_ATTENDANCE', 'attendance', $sectionId, "Saved attendance for $date");
    setFlash('success', 'Attendance saved.');
    redirect(APP_URL . "/admin/attendance.php?section_id=$sectionId&date=$date");
}

$sectionId = (int)($_GET['section_id'] ?? 0);
$date      = sanitize($_GET['date'] ?? date('Y-m-d'));

$sections = $db->query("SELECT sec.*, gl.grade_name FROM sections sec JOIN grade_levels gl ON sec.grade_level_id=gl.id ORDER BY gl.id, sec.section_name")->fetchAll();

$students = [];
$existingAttendance = [];

if ($sectionId) {
    $stmt = $db->prepare("SELECT * FROM students WHERE section_id=? AND enrollment_status='enrolled' ORDER BY last_name, first_name");
    $stmt->execute([$sectionId]);
    $students = $stmt->fetchAll();

    $attStmt = $db->prepare("SELECT student_id, status, remarks FROM attendance WHERE date=? AND student_id IN (SELECT id FROM students WHERE section_id=?)");
    $attStmt->execute([$date, $sectionId]);
    foreach ($attStmt->fetchAll() as $a) {
        $existingAttendance[$a['student_id']] = $a;
    }
}

// Summary for selected section/date
$summary = ['present'=>0,'absent'=>0,'late'=>0,'excused'=>0];
foreach ($existingAttendance as $a) {
    $summary[$a['status']] = ($summary[$a['status']] ?? 0) + 1;
}
?>

<div class="page-header">
    <div>
        <h1><i class="bi bi-calendar-check me-2 text-primary"></i>Attendance</h1>
    </div>
</div>

<?php showFlash(); ?>

<div class="row g-3">
    <div class="col-md-3">
        <div class="card">
            <div class="card-header">Filter</div>
            <div class="card-body">
                <form method="GET">
                    <div class="mb-3">
                        <label class="form-label">Section</label>
                        <select name="section_id" class="form-select" onchange="this.form.submit()">
                            <option value="">— Select Section —</option>
                            <?php foreach ($sections as $sec): ?>
                                <option value="<?= $sec['id'] ?>" <?= $sectionId==$sec['id']?'selected':'' ?>>
                                    <?= htmlspecialchars($sec['grade_name'] . ' — ' . $sec['section_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Date</label>
                        <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($date) ?>" onchange="this.form.submit()">
                    </div>
                </form>

                <?php if ($sectionId && $students): ?>
                <hr>
                <div class="small fw-700 mb-2">Summary for <?= formatDate($date) ?></div>
                <?php foreach ($summary as $s => $cnt): ?>
                <div class="d-flex justify-content-between small mb-1">
                    <span class="text-capitalize"><?= $s ?></span>
                    <span class="fw-700"><?= $cnt ?></span>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-9">
        <?php if ($sectionId && $students): ?>
        <div class="card">
            <div class="card-header">
                Attendance — <?= formatDate($date) ?>
                <span class="ms-2 text-muted small">(<?= count($students) ?> students)</span>
            </div>
            <div class="card-body p-0">
                <form method="POST">
                    <input type="hidden" name="save_attendance" value="1">
                    <input type="hidden" name="section_id" value="<?= $sectionId ?>">
                    <input type="hidden" name="date" value="<?= htmlspecialchars($date) ?>">

                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Student</th>
                                <th>Present</th>
                                <th>Absent</th>
                                <th>Late</th>
                                <th>Excused</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $i => $s):
                                $att = $existingAttendance[$s['id']] ?? null;
                                $currentStatus = $att['status'] ?? 'present';
                            ?>
                            <tr>
                                <td class="text-muted small"><?= $i + 1 ?></td>
                                <td class="fw-600"><?= htmlspecialchars($s['last_name'] . ', ' . $s['first_name']) ?></td>
                                <?php foreach (['present','absent','late','excused'] as $status): ?>
                                <td class="text-center">
                                    <input type="radio" name="attendance[<?= $s['id'] ?>]" value="<?= $status ?>"
                                           class="form-check-input" <?= $currentStatus === $status ? 'checked' : '' ?>>
                                </td>
                                <?php endforeach; ?>
                                <td>
                                    <input type="text" name="remarks[<?= $s['id'] ?>]" class="form-control form-control-sm"
                                           value="<?= htmlspecialchars($att['remarks'] ?? '') ?>" placeholder="Optional">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div class="p-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i>Save Attendance
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php elseif ($sectionId): ?>
        <div class="card"><div class="card-body text-center text-muted py-5">No enrolled students in this section.</div></div>
        <?php else: ?>
        <div class="card"><div class="card-body text-center text-muted py-5">
            <i class="bi bi-calendar-check fs-1 d-block mb-2 opacity-25"></i>Select a section and date to record attendance.
        </div></div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
