<?php
$pageTitle = 'Student Records';
require_once __DIR__ . '/../includes/header.php';
requireRole(['admin', 'teacher']);

$db = getDB();

// ── Single delete ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id']) && isAdmin()) {
    $db->prepare("DELETE FROM students WHERE id = ?")->execute([(int)$_POST['delete_id']]);
    auditLog('DELETE_STUDENT', 'students', $_POST['delete_id']);
    setFlash('success', 'Student record deleted.');
    redirect(APP_URL . '/admin/students.php');
}

// ── Bulk actions ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'], $_POST['bulk_ids']) && isAdmin()) {
    $ids    = array_map('intval', (array)$_POST['bulk_ids']);
    $action = sanitize($_POST['bulk_action']);
    $count  = 0;

    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        if ($action === 'delete') {
            $db->prepare("DELETE FROM students WHERE id IN ($placeholders)")->execute($ids);
            $count = count($ids);
            auditLog('BULK_DELETE_STUDENTS', 'students', null, "Deleted IDs: " . implode(',', $ids));
            setFlash('success', "$count student record(s) deleted.");

        } elseif (in_array($action, ['enrolled','transferred','dropped','graduated'])) {
            $db->prepare("UPDATE students SET enrollment_status = ? WHERE id IN ($placeholders)")
               ->execute(array_merge([$action], $ids));
            $count = count($ids);
            auditLog('BULK_UPDATE_STATUS', 'students', null, "Set status=$action for IDs: " . implode(',', $ids));
            setFlash('success', "$count student(s) updated to " . ucfirst($action) . ".");
        }
    }
    redirect(APP_URL . '/admin/students.php?' . http_build_query(array_diff_key($_GET, ['page'=>''])));
}

// ── Filters ───────────────────────────────────────────────
$search    = sanitize($_GET['search'] ?? '');
$gradeId   = (int)($_GET['grade']   ?? 0);
$sectionId = (int)($_GET['section'] ?? 0);
$status    = sanitize($_GET['status'] ?? '');
$page      = max(1, (int)($_GET['page'] ?? 1));
$perPage   = 20;
$offset    = ($page - 1) * $perPage;

$where  = ['1=1'];
$params = [];

if ($search) {
    $where[]  = "(s.first_name LIKE ? OR s.last_name LIKE ? OR s.lrn LIKE ? OR s.middle_name LIKE ?)";
    $params   = array_merge($params, ["%$search%","%$search%","%$search%","%$search%"]);
}
if ($gradeId)   { $where[] = "sec.grade_level_id = ?"; $params[] = $gradeId; }
if ($sectionId) { $where[] = "s.section_id = ?";       $params[] = $sectionId; }
if ($status)    { $where[] = "s.enrollment_status = ?"; $params[] = $status; }

$whereStr  = implode(' AND ', $where);
$countStmt = $db->prepare("SELECT COUNT(*) FROM students s LEFT JOIN sections sec ON s.section_id=sec.id WHERE $whereStr");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();

$stmt = $db->prepare("
    SELECT s.*, sec.section_name, gl.grade_name
    FROM students s
    LEFT JOIN sections sec ON s.section_id = sec.id
    LEFT JOIN grade_levels gl ON sec.grade_level_id = gl.id
    WHERE $whereStr
    ORDER BY s.last_name, s.first_name
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$students = $stmt->fetchAll();

$grades   = $db->query("SELECT * FROM grade_levels ORDER BY id")->fetchAll();
$sections = $db->query("SELECT sec.*, gl.grade_name FROM sections sec JOIN grade_levels gl ON sec.grade_level_id=gl.id ORDER BY gl.id, sec.section_name")->fetchAll();
?>

<div class="page-header">
    <div>
        <h1><i class="bi bi-people-fill me-2 text-primary"></i>Student Records</h1>
        <nav aria-label="breadcrumb"><ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Students</li>
        </ol></nav>
    </div>
    <a href="<?= APP_URL ?>/admin/student-form.php" class="btn btn-primary">
        <i class="bi bi-person-plus me-1"></i>Add Student
    </a>
</div>

<?php showFlash(); ?>

<!-- Filters -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control form-control-sm"
                       placeholder="Search by name or LRN..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-2">
                <select name="grade" class="form-select form-select-sm">
                    <option value="">All Grades</option>
                    <?php foreach ($grades as $g): ?>
                        <option value="<?= $g['id'] ?>" <?= $gradeId==$g['id']?'selected':'' ?>><?= htmlspecialchars($g['grade_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="section" class="form-select form-select-sm">
                    <option value="">All Sections</option>
                    <?php foreach ($sections as $sec): ?>
                        <option value="<?= $sec['id'] ?>" <?= $sectionId==$sec['id']?'selected':'' ?>><?= htmlspecialchars($sec['grade_name'].' - '.$sec['section_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Status</option>
                    <?php foreach (['enrolled','transferred','dropped','graduated'] as $s): ?>
                        <option value="<?= $s ?>" <?= $status==$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1"><i class="bi bi-search"></i> Filter</button>
                <a href="students.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Bulk Action Toolbar -->
<div id="bulkToolbar" class="d-none mb-2">
    <div class="card border-primary">
        <div class="card-body py-2 d-flex align-items-center gap-3 flex-wrap">
            <span class="fw-700 text-primary small">
                <i class="bi bi-check2-square me-1"></i>
                <span id="bulkCount">0</span> selected
            </span>
            <?php if (isAdmin()): ?>
            <form method="POST" data-bulk-form data-confirm-label="Delete">
                <input type="hidden" name="bulk_action" value="delete">
                <button type="submit" class="btn btn-sm btn-danger">
                    <i class="bi bi-trash me-1"></i>Delete Selected
                </button>
            </form>
            <form method="POST" data-bulk-form>
                <select name="bulk_action" class="form-select form-select-sm d-inline-block w-auto me-1">
                    <option value="">— Change Status —</option>
                    <option value="enrolled">Set Enrolled</option>
                    <option value="transferred">Set Transferred</option>
                    <option value="dropped">Set Dropped</option>
                    <option value="graduated">Set Graduated</option>
                </select>
                <button type="submit" class="btn btn-sm btn-outline-primary">Apply</button>
            </form>
            <?php endif; ?>
            <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" onclick="deselectAll()">
                <i class="bi bi-x me-1"></i>Clear
            </button>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Showing <?= number_format($total) ?> student(s)</span>
        <a href="<?= APP_URL ?>/admin/export-students.php?<?= http_build_query($_GET) ?>" class="btn btn-sm btn-outline-success">
            <i class="bi bi-download me-1"></i>Export CSV
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="studentsTable">
                <thead>
                    <tr>
                        <th width="36">
                            <input type="checkbox" class="form-check-input" id="selectAll" title="Select all">
                        </th>
                        <th>LRN</th>
                        <th>Name</th>
                        <th>Gender</th>
                        <th>Grade & Section</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($students): foreach ($students as $s): ?>
                    <tr>
                        <td><input type="checkbox" class="form-check-input" name="ids[]" value="<?= $s['id'] ?>"></td>
                        <td><code class="small"><?= htmlspecialchars($s['lrn'] ?? '—') ?></code></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <?php if (!empty($s['profile_photo'])): ?>
                                    <img src="<?= APP_URL.'/'.$s['profile_photo'] ?>" class="avatar" alt="">
                                <?php else: ?>
                                    <div class="avatar"><?= strtoupper(substr($s['first_name'],0,1)) ?></div>
                                <?php endif; ?>
                                <div>
                                    <div class="fw-600"><?= htmlspecialchars($s['last_name'].', '.$s['first_name'].' '.($s['middle_name'] ? $s['middle_name'][0].'.' : '')) ?></div>
                                    <div class="small text-muted"><?= formatDate($s['date_of_birth']) ?> (<?= age($s['date_of_birth']) ?> yrs)</div>
                                </div>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($s['gender']) ?></td>
                        <td>
                            <?php if ($s['grade_name']): ?>
                                <span class="badge bg-light text-dark border"><?= htmlspecialchars($s['grade_name']) ?></span>
                                <span class="small"><?= htmlspecialchars($s['section_name'] ?? '') ?></span>
                            <?php else: ?><span class="text-muted small">—</span><?php endif; ?>
                        </td>
                        <td><?= statusBadge($s['enrollment_status']) ?></td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="<?= APP_URL ?>/admin/student-view.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-primary" title="View"><i class="bi bi-eye"></i></a>
                                <a href="<?= APP_URL ?>/admin/student-form.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="bi bi-pencil"></i></a>
                                <?php if (isAdmin()): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="delete_id" value="<?= $s['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Delete this student? This cannot be undone." title="Delete"><i class="bi bi-trash"></i></button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="7" class="text-center text-muted py-5">
                        <i class="bi bi-people fs-1 d-block mb-2 opacity-25"></i>No students found
                    </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($total > $perPage): ?>
    <div class="card-footer d-flex justify-content-end">
        <?= paginate($total, $perPage, $page, 'students.php?'.http_build_query(array_diff_key($_GET,['page'=>'']))) ?>
    </div>
    <?php endif; ?>
</div>

<script>
initBulkSelect({
    tableId:      'studentsTable',
    checkboxName: 'ids[]',
    counterId:    'bulkCount',
    toolbarId:    'bulkToolbar',
    selectAllId:  'selectAll',
});
function deselectAll() {
    document.querySelectorAll('#studentsTable input[name="ids[]"]').forEach(cb => {
        cb.checked = false;
        cb.closest('tr').classList.remove('table-active');
    });
    document.getElementById('selectAll').checked = false;
    document.getElementById('bulkToolbar').classList.add('d-none');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
