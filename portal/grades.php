<?php
$pageTitle = 'Grades';
require_once __DIR__ . '/../includes/header.php';
requireRole(['parent']);

$db     = getDB();
$userId = $_SESSION['user_id'];

// Children linked to this parent
$childStmt = $db->prepare("
    SELECT s.id, s.first_name, s.last_name, s.middle_name,
           sec.section_name, gl.grade_name, sec.grade_level_id
    FROM students s
    LEFT JOIN sections sec ON s.section_id = sec.id
    LEFT JOIN grade_levels gl ON sec.grade_level_id = gl.id
    WHERE s.parent_user_id = ?
    ORDER BY s.last_name, s.first_name
");
$childStmt->execute([$userId]);
$children = $childStmt->fetchAll();

$studentId  = (int)($_GET['student_id'] ?? ($children[0]['id'] ?? 0));
$schoolYear = sanitize($_GET['school_year'] ?? currentSchoolYear());

// Verify ownership
$selectedStudent = null;
foreach ($children as $c) {
    if ($c['id'] == $studentId) { $selectedStudent = $c; break; }
}

// Load subjects and grades for all 4 quarters at once
$subjects  = [];
$gradeData = []; // [subject_id][quarter] = grade

if ($selectedStudent && $selectedStudent['grade_level_id']) {

    // Distinct subjects for this grade level — no duplicates
    $subStmt = $db->prepare("
        SELECT DISTINCT id, subject_name, subject_code
        FROM subjects
        WHERE grade_level_id = ?
        ORDER BY subject_name
    ");
    $subStmt->execute([$selectedStudent['grade_level_id']]);
    $subjects = $subStmt->fetchAll();

    // All grades for this student/year in one query
    $gradeStmt = $db->prepare("
        SELECT subject_id, quarter, grade
        FROM grades
        WHERE student_id = ? AND school_year = ?
        ORDER BY quarter
    ");
    $gradeStmt->execute([$studentId, $schoolYear]);
    foreach ($gradeStmt->fetchAll() as $g) {
        $gradeData[$g['subject_id']][$g['quarter']] = $g['grade'];
    }
}

// Helper: color-code a grade value
function gradeColor($g) {
    if ($g === null) return 'text-muted';
    if ($g >= 90)   return 'text-success fw-700';
    if ($g >= 75)   return 'text-primary fw-600';
    return 'text-danger fw-700';
}
?>

<div class="page-header">
    <div>
        <h1><i class="bi bi-journal-check me-2 text-primary"></i>Grades</h1>
    </div>
    <?php if ($selectedStudent && $subjects): ?>
    <button class="btn btn-outline-secondary no-print" data-print>
        <i class="bi bi-printer me-1"></i>Print
    </button>
    <?php endif; ?>
</div>

<!-- Selector -->
<div class="card mb-3 no-print">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small fw-600">Student</label>
                <select name="student_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach ($children as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $studentId == $c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-600">School Year</label>
                <input type="text" name="school_year" class="form-control form-control-sm"
                       value="<?= htmlspecialchars($schoolYear) ?>" placeholder="e.g. 2025-2026">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-search me-1"></i>Load
                </button>
            </div>
        </form>
    </div>
</div>

<?php if (!$children): ?>
    <div class="alert alert-warning">No children linked to your account. Please contact the school administrator.</div>

<?php elseif ($selectedStudent && $subjects): ?>

<!-- Grade Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <div class="fw-700">
                <?= htmlspecialchars($selectedStudent['first_name'] . ' ' . $selectedStudent['last_name']) ?>
            </div>
            <div class="small text-muted">
                <?= htmlspecialchars($selectedStudent['grade_name'] ?? '—') ?>
                <?= $selectedStudent['section_name'] ? '— ' . htmlspecialchars($selectedStudent['section_name']) : '' ?>
                &nbsp;·&nbsp; S.Y. <?= htmlspecialchars($schoolYear) ?>
            </div>
        </div>
        <!-- Legend -->
        <div class="d-flex gap-3 small no-print">
            <span class="text-success fw-700">90–100 Outstanding</span>
            <span class="text-primary fw-600">75–89 Passed</span>
            <span class="text-danger fw-700">Below 75 Failed</span>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="min-width:180px">Subject</th>
                        <th class="text-center" width="80">Q1</th>
                        <th class="text-center" width="80">Q2</th>
                        <th class="text-center" width="80">Q3</th>
                        <th class="text-center" width="80">Q4</th>
                        <th class="text-center" width="90">Final</th>
                        <th class="text-center" width="90">Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $totalFinals  = [];
                    foreach ($subjects as $sub):
                        $q = [];
                        for ($i = 1; $i <= 4; $i++) {
                            $q[$i] = isset($gradeData[$sub['id']][$i])
                                ? (float)$gradeData[$sub['id']][$i]
                                : null;
                        }
                        $filled = array_filter($q, fn($v) => $v !== null);
                        $final  = count($filled) > 0
                            ? round(array_sum($filled) / count($filled), 2)
                            : null;
                        $passed = $final !== null && $final >= 75;
                        if ($final !== null) $totalFinals[] = $final;
                    ?>
                    <tr>
                        <td class="fw-600 small"><?= htmlspecialchars($sub['subject_name']) ?></td>
                        <?php for ($i = 1; $i <= 4; $i++): ?>
                        <td class="text-center small <?= gradeColor($q[$i]) ?>">
                            <?= $q[$i] !== null ? number_format($q[$i], 2) : '<span class="text-muted">—</span>' ?>
                        </td>
                        <?php endfor; ?>
                        <td class="text-center fw-700 <?= gradeColor($final) ?>">
                            <?= $final !== null ? number_format($final, 2) : '<span class="text-muted">—</span>' ?>
                        </td>
                        <td class="text-center">
                            <?php if ($final !== null): ?>
                                <span class="badge <?= $passed ? 'bg-success' : 'bg-danger' ?>">
                                    <?= $passed ? 'Passed' : 'Failed' ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <?php if (!empty($totalFinals)): ?>
                <tfoot class="table-light fw-700">
                    <tr>
                        <td colspan="5" class="text-end small">General Average</td>
                        <?php $gwa = round(array_sum($totalFinals) / count($totalFinals), 2); ?>
                        <td class="text-center <?= gradeColor($gwa) ?>"><?= number_format($gwa, 2) ?></td>
                        <td class="text-center">
                            <span class="badge <?= $gwa >= 75 ? 'bg-success' : 'bg-danger' ?>">
                                <?= $gwa >= 75 ? 'Passed' : 'Failed' ?>
                            </span>
                        </td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>

    <div class="card-footer small text-muted no-print">
        <i class="bi bi-info-circle me-1"></i>
        Passing grade: <strong>75</strong> &nbsp;·&nbsp;
        Final grade = average of available quarters &nbsp;·&nbsp;
        Contact the class adviser for grade concerns.
    </div>
</div>

<?php elseif ($selectedStudent): ?>
    <div class="card">
        <div class="card-body text-center text-muted py-5">
            <i class="bi bi-journal-x fs-1 d-block mb-2 opacity-25"></i>
            No grades recorded yet for S.Y. <?= htmlspecialchars($schoolYear) ?>.
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
