<?php
$pageTitle = 'Grades Management';
require_once __DIR__ . '/../includes/header.php';
requireRole(['admin', 'teacher']);

$db = getDB();

// ── Save grades (all 4 quarters at once) ─────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_grades'])) {
    $studentId  = (int)$_POST['student_id'];
    $schoolYear = sanitize($_POST['school_year']);
    $gradesPost = $_POST['grades'] ?? []; // [subject_id][quarter] = grade

    $saved = 0;
    foreach ($gradesPost as $subjectId => $quarters) {
        $subjectId = (int)$subjectId;
        foreach ($quarters as $quarter => $grade) {
            $quarter = (int)$quarter;
            $grade   = ($grade === '' || $grade === null) ? null : (float)$grade;

            $existing = $db->prepare("SELECT id FROM grades WHERE student_id=? AND subject_id=? AND school_year=? AND quarter=?");
            $existing->execute([$studentId, $subjectId, $schoolYear, $quarter]);
            $row = $existing->fetch();

            if ($row) {
                $db->prepare("UPDATE grades SET grade=?, encoded_by=? WHERE id=?")
                   ->execute([$grade, $_SESSION['user_id'], $row['id']]);
            } else {
                $db->prepare("INSERT INTO grades (student_id,subject_id,school_year,quarter,grade,encoded_by) VALUES (?,?,?,?,?,?)")
                   ->execute([$studentId, $subjectId, $schoolYear, $quarter, $grade, $_SESSION['user_id']]);
            }
            $saved++;
        }
    }

    auditLog('SAVE_GRADES', 'grades', $studentId, "Saved all quarters for student #$studentId, S.Y. $schoolYear");
    setFlash('success', 'Grades saved successfully.');
    redirect(APP_URL . "/admin/grades.php?student_id=$studentId&school_year=" . urlencode($schoolYear));
}

// ── Subject management ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subject_action'])) {
    $action = $_POST['subject_action'];

    if ($action === 'add') {
        $name    = sanitize($_POST['subject_name'] ?? '');
        $code    = sanitize($_POST['subject_code'] ?? '');
        $gradeId = (int)$_POST['grade_level_id'];
        if ($name && $gradeId) {
            try {
                $db->prepare("INSERT INTO subjects (subject_name, subject_code, grade_level_id) VALUES (?,?,?)")
                   ->execute([$name, $code, $gradeId]);
                setFlash('success', "Subject '$name' added.");
            } catch (PDOException $e) {
                setFlash('error', "Subject '$name' already exists for this grade level.");
            }
        }
        redirect(APP_URL . '/admin/grades.php?tab=subjects');
    }

    if ($action === 'edit') {
        $sid  = (int)$_POST['subject_id'];
        $name = sanitize($_POST['subject_name'] ?? '');
        $code = sanitize($_POST['subject_code'] ?? '');
        if ($name && $sid) {
            $db->prepare("UPDATE subjects SET subject_name=?, subject_code=? WHERE id=?")
               ->execute([$name, $code, $sid]);
            setFlash('success', 'Subject updated.');
        }
        redirect(APP_URL . '/admin/grades.php?tab=subjects');
    }

    if ($action === 'delete') {
        $sid = (int)$_POST['subject_id'];
        // Check if grades exist for this subject
        $inUse = $db->prepare("SELECT COUNT(*) FROM grades WHERE subject_id=?");
        $inUse->execute([$sid]);
        if ($inUse->fetchColumn() > 0) {
            setFlash('error', 'Cannot delete — grades already recorded for this subject.');
        } else {
            $db->prepare("DELETE FROM subjects WHERE id=?")->execute([$sid]);
            setFlash('success', 'Subject deleted.');
        }
        redirect(APP_URL . '/admin/grades.php?tab=subjects');
    }

    // ── Bulk delete subjects ──────────────────────────────
    if ($action === 'bulk_delete' && isset($_POST['bulk_ids'])) {
        $ids     = array_map('intval', (array)$_POST['bulk_ids']);
        $deleted = 0;
        $skipped = 0;
        foreach ($ids as $sid) {
            $inUse = $db->prepare("SELECT COUNT(*) FROM grades WHERE subject_id=?");
            $inUse->execute([$sid]);
            if ($inUse->fetchColumn() > 0) {
                $skipped++;
            } else {
                $db->prepare("DELETE FROM subjects WHERE id=?")->execute([$sid]);
                $deleted++;
            }
        }
        auditLog('BULK_DELETE_SUBJECTS', 'subjects', null, "Deleted IDs: " . implode(',', $ids));
        $msg = "$deleted subject(s) deleted.";
        if ($skipped) $msg .= " $skipped skipped (have recorded grades).";
        setFlash($skipped && !$deleted ? 'error' : 'success', $msg);
        redirect(APP_URL . '/admin/grades.php?tab=subjects');
    }
}

// ── Load data ─────────────────────────────────────────────
$activeTab  = sanitize($_GET['tab'] ?? 'grades');
$studentId  = (int)($_GET['student_id'] ?? 0);
$schoolYear = sanitize($_GET['school_year'] ?? currentSchoolYear());

$students = $db->query("
    SELECT s.id, s.first_name, s.last_name, s.lrn,
           sec.section_name, gl.grade_name, sec.grade_level_id
    FROM students s
    LEFT JOIN sections sec ON s.section_id = sec.id
    LEFT JOIN grade_levels gl ON sec.grade_level_id = gl.id
    WHERE s.enrollment_status = 'enrolled'
    ORDER BY gl.id, s.last_name, s.first_name
")->fetchAll();

$selectedStudent = null;
$subjects        = [];
$gradeData       = []; // [subject_id][quarter] = grade

if ($studentId) {
    foreach ($students as $s) {
        if ($s['id'] == $studentId) { $selectedStudent = $s; break; }
    }

    if ($selectedStudent && $selectedStudent['grade_level_id']) {
        $subStmt = $db->prepare("
            SELECT DISTINCT id, subject_name, subject_code
            FROM subjects
            WHERE grade_level_id = ?
            ORDER BY subject_name
        ");
        $subStmt->execute([$selectedStudent['grade_level_id']]);
        $subjects = $subStmt->fetchAll();

        // Load all quarters at once
        $gradeStmt = $db->prepare("
            SELECT subject_id, quarter, grade
            FROM grades
            WHERE student_id = ? AND school_year = ?
        ");
        $gradeStmt->execute([$studentId, $schoolYear]);
        foreach ($gradeStmt->fetchAll() as $g) {
            $gradeData[$g['subject_id']][$g['quarter']] = $g['grade'];
        }
    }
}

// For subject management tab
$gradeLevels    = $db->query("SELECT * FROM grade_levels ORDER BY id")->fetchAll();
$allSubjects    = $db->query("
    SELECT s.*, gl.grade_name
    FROM subjects s
    JOIN grade_levels gl ON s.grade_level_id = gl.id
    ORDER BY gl.id, s.subject_name
")->fetchAll();

$editSubject = null;
if (isset($_GET['edit_subject'])) {
    $stmt = $db->prepare("SELECT * FROM subjects WHERE id=?");
    $stmt->execute([(int)$_GET['edit_subject']]);
    $editSubject = $stmt->fetch();
}
?>

<div class="page-header">
    <div>
        <h1><i class="bi bi-journal-check me-2 text-primary"></i>Grades Management</h1>
    </div>
</div>

<?php showFlash(); ?>

<!-- Tabs -->
<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'grades' ? 'active' : '' ?>"
           href="grades.php<?= $studentId ? "?student_id=$studentId&school_year=" . urlencode($schoolYear) : '?tab=grades' ?>">
            <i class="bi bi-journal-check me-1"></i>Enter Grades
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'subjects' ? 'active' : '' ?>" href="grades.php?tab=subjects">
            <i class="bi bi-list-ul me-1"></i>Manage Subjects
        </a>
    </li>
</ul>

<?php if ($activeTab === 'grades'): ?>
<!-- ══════════════════════════════════════════════════════
     GRADES TAB
══════════════════════════════════════════════════════ -->
<div class="row g-3">

    <!-- Student selector -->
    <div class="col-lg-3">
        <div class="card">
            <div class="card-header fw-700">Select Student</div>
            <div class="card-body">
                <form method="GET">
                    <input type="hidden" name="tab" value="grades">
                    <div class="mb-3">
                        <label class="form-label small">Student</label>
                        <select name="student_id" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">— Select —</option>
                            <?php
                            $curGrade = '';
                            foreach ($students as $s):
                                if ($s['grade_name'] !== $curGrade) {
                                    if ($curGrade) echo '</optgroup>';
                                    echo '<optgroup label="' . htmlspecialchars($s['grade_name'] ?? 'Unassigned') . '">';
                                    $curGrade = $s['grade_name'];
                                }
                            ?>
                            <option value="<?= $s['id'] ?>" <?= $studentId == $s['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['last_name'] . ', ' . $s['first_name']) ?>
                                <?= $s['section_name'] ? ' (' . $s['section_name'] . ')' : '' ?>
                            </option>
                            <?php endforeach; if ($curGrade) echo '</optgroup>'; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">School Year</label>
                        <input type="text" name="school_year" class="form-control form-control-sm"
                               value="<?= htmlspecialchars($schoolYear) ?>">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm w-100">Load</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Grade entry -->
    <div class="col-lg-9">
        <?php if ($selectedStudent && $subjects): ?>
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <div class="fw-700">
                        <?= htmlspecialchars($selectedStudent['last_name'] . ', ' . $selectedStudent['first_name']) ?>
                    </div>
                    <div class="small text-muted">
                        <?= htmlspecialchars($selectedStudent['grade_name'] . ' — ' . ($selectedStudent['section_name'] ?? '')) ?>
                        &nbsp;·&nbsp; S.Y. <?= htmlspecialchars($schoolYear) ?>
                    </div>
                </div>
                <span class="badge bg-primary"><?= count($subjects) ?> subject(s)</span>
            </div>
            <div class="card-body p-0">
                <form method="POST">
                    <input type="hidden" name="save_grades" value="1">
                    <input type="hidden" name="student_id" value="<?= $studentId ?>">
                    <input type="hidden" name="school_year" value="<?= htmlspecialchars($schoolYear) ?>">

                    <div class="table-responsive">
                        <table class="table table-bordered mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="min-width:180px">Subject</th>
                                    <th class="text-center" width="110">Q1</th>
                                    <th class="text-center" width="110">Q2</th>
                                    <th class="text-center" width="110">Q3</th>
                                    <th class="text-center" width="110">Q4</th>
                                    <th class="text-center" width="90">Final</th>
                                    <th class="text-center" width="80">Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($subjects as $sub):
                                    $q = [];
                                    for ($i = 1; $i <= 4; $i++) {
                                        $q[$i] = $gradeData[$sub['id']][$i] ?? null;
                                    }
                                    $filled = array_filter($q, fn($v) => $v !== null);
                                    $final  = count($filled) > 0
                                        ? round(array_sum($filled) / count($filled), 2)
                                        : null;
                                    $passed = $final !== null && $final >= 75;
                                ?>
                                <tr>
                                    <td class="small fw-600"><?= htmlspecialchars($sub['subject_name']) ?></td>
                                    <?php for ($i = 1; $i <= 4; $i++): ?>
                                    <td class="p-1">
                                        <input type="number"
                                               name="grades[<?= $sub['id'] ?>][<?= $i ?>]"
                                               class="form-control form-control-sm text-center grade-input"
                                               min="60" max="100" step="0.01"
                                               placeholder="—"
                                               value="<?= $q[$i] !== null ? htmlspecialchars($q[$i]) : '' ?>">
                                    </td>
                                    <?php endfor; ?>
                                    <td class="text-center fw-700 small
                                        <?= $final === null ? 'text-muted' : ($final >= 90 ? 'text-success' : ($final >= 75 ? 'text-primary' : 'text-danger')) ?>">
                                        <?= $final !== null ? number_format($final, 2) : '—' ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($final !== null): ?>
                                            <span class="badge <?= $passed ? 'bg-success' : 'bg-danger' ?>">
                                                <?= $passed ? 'P' : 'F' ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="p-3 border-top d-flex gap-2 align-items-center">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i>Save All Grades
                        </button>
                        <span class="small text-muted">Grades are saved for all 4 quarters at once.</span>
                    </div>
                </form>
            </div>
        </div>

        <?php elseif ($studentId): ?>
        <div class="card">
            <div class="card-body text-center text-muted py-5">
                <i class="bi bi-journal-x fs-1 d-block mb-2 opacity-25"></i>
                No subjects found for this student's grade level.<br>
                <a href="grades.php?tab=subjects" class="btn btn-sm btn-outline-primary mt-3">
                    <i class="bi bi-plus me-1"></i>Add Subjects
                </a>
            </div>
        </div>
        <?php else: ?>
        <div class="card">
            <div class="card-body text-center text-muted py-5">
                <i class="bi bi-journal-check fs-1 d-block mb-2 opacity-25"></i>
                Select a student to enter grades.
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php else: ?>
<!-- ══════════════════════════════════════════════════════
     SUBJECTS TAB
══════════════════════════════════════════════════════ -->
<div class="row g-3">

    <!-- Add / Edit form -->
    <div class="col-lg-4">
        <div class="card border-primary">
            <div class="card-header bg-primary text-white fw-700">
                <i class="bi bi-<?= $editSubject ? 'pencil' : 'plus-circle' ?> me-2"></i>
                <?= $editSubject ? 'Edit Subject' : 'Add Subject' ?>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="subject_action" value="<?= $editSubject ? 'edit' : 'add' ?>">
                    <?php if ($editSubject): ?>
                        <input type="hidden" name="subject_id" value="<?= $editSubject['id'] ?>">
                    <?php endif; ?>

                    <?php if (!$editSubject): ?>
                    <div class="mb-3">
                        <label class="form-label">Grade Level <span class="text-danger">*</span></label>
                        <select name="grade_level_id" class="form-select" required>
                            <option value="">— Select —</option>
                            <?php foreach ($gradeLevels as $gl): ?>
                                <option value="<?= $gl['id'] ?>"><?= htmlspecialchars($gl['grade_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label">Subject Name <span class="text-danger">*</span></label>
                        <input type="text" name="subject_name" class="form-control"
                               value="<?= htmlspecialchars($editSubject['subject_name'] ?? '') ?>"
                               placeholder="e.g. Mathematics" required autofocus>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Subject Code</label>
                        <input type="text" name="subject_code" class="form-control"
                               value="<?= htmlspecialchars($editSubject['subject_code'] ?? '') ?>"
                               placeholder="e.g. MATH" maxlength="20">
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="bi bi-save me-1"></i><?= $editSubject ? 'Update' : 'Add Subject' ?>
                        </button>
                        <?php if ($editSubject): ?>
                        <a href="grades.php?tab=subjects" class="btn btn-outline-secondary">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Subjects list -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header fw-700 d-flex justify-content-between align-items-center">
                <span>All Subjects (<?= count($allSubjects) ?>)</span>
            </div>

            <!-- Bulk Toolbar -->
            <div id="subjectBulkToolbar" class="d-none border-bottom px-3 py-2 bg-light">
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <span class="fw-700 text-primary small">
                        <i class="bi bi-check2-square me-1"></i>
                        <span id="subjectBulkCount">0</span> selected
                    </span>
                    <form method="POST" id="subjectBulkForm">
                        <input type="hidden" name="subject_action" value="bulk_delete">
                        <button type="submit" class="btn btn-sm btn-danger">
                            <i class="bi bi-trash me-1"></i>Delete Selected
                        </button>
                    </form>
                    <button type="button" class="btn btn-sm btn-outline-secondary ms-auto"
                            onclick="deselectSubjects()">
                        <i class="bi bi-x me-1"></i>Clear
                    </button>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle" id="subjectsTable">
                        <thead class="table-light">
                            <tr>
                                <th width="36">
                                    <input type="checkbox" class="form-check-input" id="subjectSelectAll"
                                           title="Select all">
                                </th>
                                <th>Subject Name</th>
                                <th>Code</th>
                                <th>Grade Level</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($allSubjects):
                                $curGrade = '';
                                foreach ($allSubjects as $sub):
                                    if ($sub['grade_name'] !== $curGrade):
                                        $curGrade = $sub['grade_name'];
                            ?>
                            <tr class="table-secondary">
                                <td></td>
                                <td colspan="4" class="fw-700 small py-1 px-2">
                                    <i class="bi bi-mortarboard me-1"></i><?= htmlspecialchars($curGrade) ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <tr class="<?= $editSubject && $editSubject['id'] == $sub['id'] ? 'table-primary' : '' ?>">
                                <td>
                                    <input type="checkbox" class="form-check-input"
                                           name="ids[]" value="<?= $sub['id'] ?>">
                                </td>
                                <td class="fw-600 small"><?= htmlspecialchars($sub['subject_name']) ?></td>
                                <td>
                                    <code class="small" style="color:#e74c3c">
                                        <?= htmlspecialchars($sub['subject_code'] ?? '—') ?>
                                    </code>
                                </td>
                                <td class="small text-muted"><?= htmlspecialchars($sub['grade_name']) ?></td>
                                <td class="text-center">
                                    <div class="d-flex gap-1 justify-content-center">
                                        <a href="grades.php?tab=subjects&edit_subject=<?= $sub['id'] ?>"
                                           class="btn btn-sm btn-outline-primary"
                                           data-bs-toggle="tooltip" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="subject_action" value="delete">
                                            <input type="hidden" name="subject_id" value="<?= $sub['id'] ?>">
                                            <button type="submit"
                                                    class="btn btn-sm btn-outline-danger"
                                                    data-bs-toggle="tooltip" title="Delete"
                                                    data-confirm="Delete '<?= htmlspecialchars($sub['subject_name']) ?>'?">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; else: ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-5">
                                    No subjects yet. Add one using the form.
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>
<?php endif; ?>

<script>
<?php if ($activeTab === 'subjects'): ?>
(function () {
    const table     = document.getElementById('subjectsTable');
    const toolbar   = document.getElementById('subjectBulkToolbar');
    const counter   = document.getElementById('subjectBulkCount');
    const selectAll = document.getElementById('subjectSelectAll');
    const bulkForm  = document.getElementById('subjectBulkForm');

    if (!table) return;

    // Only rows that have a checkbox (skip grade-level header rows)
    function getCheckboxes() {
        return Array.from(table.querySelectorAll('input[name="ids[]"]'));
    }

    function getChecked() {
        return getCheckboxes().filter(cb => cb.checked);
    }

    function updateToolbar() {
        const checked = getChecked();
        const n = checked.length;
        counter.textContent = n;
        toolbar.classList.toggle('d-none', n === 0);

        const all = getCheckboxes();
        selectAll.indeterminate = n > 0 && n < all.length;
        selectAll.checked       = all.length > 0 && n === all.length;
    }

    // Select-all header checkbox
    selectAll.addEventListener('change', function () {
        getCheckboxes().forEach(cb => {
            cb.checked = this.checked;
            cb.closest('tr').classList.toggle('table-active', this.checked);
        });
        updateToolbar();
    });

    // Individual row checkbox
    table.addEventListener('change', function (e) {
        if (e.target.name === 'ids[]') {
            e.target.closest('tr').classList.toggle('table-active', e.target.checked);
            updateToolbar();
        }
    });

    // Click anywhere on a data row (not on buttons/links/inputs) to toggle
    table.addEventListener('click', function (e) {
        const row = e.target.closest('tr');
        if (!row) return;
        if (e.target.closest('a, button, input, label, form')) return;
        const cb = row.querySelector('input[name="ids[]"]');
        if (!cb) return; // header rows have no checkbox
        cb.checked = !cb.checked;
        row.classList.toggle('table-active', cb.checked);
        updateToolbar();
    });

    // Bulk form submit — inject selected IDs then confirm
    bulkForm.addEventListener('submit', function (e) {
        const checked = getChecked();
        if (checked.length === 0) {
            e.preventDefault();
            alert('Please select at least one subject.');
            return;
        }
        if (!confirm(`Delete ${checked.length} selected subject(s)?\n\nSubjects with recorded grades will be skipped.`)) {
            e.preventDefault();
            return;
        }
        // Remove any previously injected IDs
        this.querySelectorAll('.bulk-id-input').forEach(el => el.remove());
        // Inject selected IDs
        checked.forEach(cb => {
            const h = document.createElement('input');
            h.type      = 'hidden';
            h.name      = 'bulk_ids[]';
            h.value     = cb.value;
            h.className = 'bulk-id-input';
            this.appendChild(h);
        });
    });
})();

function deselectSubjects() {
    document.querySelectorAll('#subjectsTable input[name="ids[]"]').forEach(cb => {
        cb.checked = false;
        cb.closest('tr').classList.remove('table-active');
    });
    document.getElementById('subjectSelectAll').checked = false;
    document.getElementById('subjectBulkToolbar').classList.add('d-none');
    document.getElementById('subjectBulkCount').textContent = '0';
}
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
