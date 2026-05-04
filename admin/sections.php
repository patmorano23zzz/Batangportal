<?php
$pageTitle = 'Sections';
require_once __DIR__ . '/../includes/header.php';
requireRole(['admin']);

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $sid       = (int)($_POST['section_id'] ?? 0);
        $gradeId   = (int)$_POST['grade_level_id'];
        $name      = sanitize($_POST['section_name'] ?? '');
        $adviserId = (int)($_POST['adviser_id'] ?? 0) ?: null;
        $sy        = sanitize($_POST['school_year'] ?? currentSchoolYear());

        if ($sid) {
            $db->prepare("UPDATE sections SET grade_level_id=?,section_name=?,adviser_id=?,school_year=? WHERE id=?")
               ->execute([$gradeId, $name, $adviserId, $sy, $sid]);
            setFlash('success', 'Section updated.');
        } else {
            $db->prepare("INSERT INTO sections (grade_level_id,section_name,adviser_id,school_year) VALUES (?,?,?,?)")
               ->execute([$gradeId, $name, $adviserId, $sy]);
            setFlash('success', 'Section added.');
        }
        redirect(APP_URL . '/admin/sections.php');
    }

    if ($action === 'delete') {
        $db->prepare("DELETE FROM sections WHERE id=?")->execute([(int)$_POST['section_id']]);
        setFlash('success', 'Section deleted.');
        redirect(APP_URL . '/admin/sections.php');
    }
}

$editSection = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM sections WHERE id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $editSection = $stmt->fetch();
}

$sections = $db->query("
    SELECT sec.*, gl.grade_name, u.full_name as adviser_name,
           (SELECT COUNT(*) FROM students WHERE section_id=sec.id AND enrollment_status='enrolled') as student_count
    FROM sections sec
    JOIN grade_levels gl ON sec.grade_level_id=gl.id
    LEFT JOIN users u ON sec.adviser_id=u.id
    ORDER BY gl.id, sec.section_name
")->fetchAll();

$grades   = $db->query("SELECT * FROM grade_levels ORDER BY id")->fetchAll();
$teachers = $db->query("SELECT id, full_name FROM users WHERE role IN ('teacher','admin') AND is_active=1 ORDER BY full_name")->fetchAll();
?>

<div class="page-header">
    <div>
        <h1><i class="bi bi-diagram-3 me-2 text-primary"></i>Sections</h1>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#sectionModal">
        <i class="bi bi-plus me-1"></i>Add Section
    </button>
</div>

<?php showFlash(); ?>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Grade Level</th>
                    <th>Section Name</th>
                    <th>Adviser</th>
                    <th>School Year</th>
                    <th>Students</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sections as $sec): ?>
                <tr>
                    <td><?= htmlspecialchars($sec['grade_name']) ?></td>
                    <td class="fw-600"><?= htmlspecialchars($sec['section_name']) ?></td>
                    <td><?= htmlspecialchars($sec['adviser_name'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($sec['school_year'] ?? '—') ?></td>
                    <td><span class="badge bg-primary"><?= $sec['student_count'] ?></span></td>
                    <td>
                        <div class="d-flex gap-1">
                            <a href="sections.php?edit=<?= $sec['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="section_id" value="<?= $sec['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Delete this section?"><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="sectionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="save">
                <?php if ($editSection): ?><input type="hidden" name="section_id" value="<?= $editSection['id'] ?>"><?php endif; ?>
                <div class="modal-header">
                    <h5 class="modal-title"><?= $editSection ? 'Edit Section' : 'Add Section' ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Grade Level</label>
                        <select name="grade_level_id" class="form-select" required>
                            <?php foreach ($grades as $g): ?>
                                <option value="<?= $g['id'] ?>" <?= ($editSection['grade_level_id'] ?? '') == $g['id'] ? 'selected' : '' ?>><?= htmlspecialchars($g['grade_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Section Name</label>
                        <input type="text" name="section_name" class="form-control" value="<?= htmlspecialchars($editSection['section_name'] ?? '') ?>" required placeholder="e.g. Sampaguita">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Adviser</label>
                        <select name="adviser_id" class="form-select">
                            <option value="">— None —</option>
                            <?php foreach ($teachers as $t): ?>
                                <option value="<?= $t['id'] ?>" <?= ($editSection['adviser_id'] ?? '') == $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">School Year</label>
                        <input type="text" name="school_year" class="form-control" value="<?= htmlspecialchars($editSection['school_year'] ?? currentSchoolYear()) ?>">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($editSection): ?>
<script>document.addEventListener('DOMContentLoaded', () => new bootstrap.Modal(document.getElementById('sectionModal')).show());</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
