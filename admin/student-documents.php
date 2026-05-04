<?php
$pageTitle = 'Student Documents';
require_once __DIR__ . '/../includes/header.php';
requireRole(['admin', 'teacher']);

$db = getDB();

// Handle upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action'] ?? '';
    $studentId = (int)$_POST['student_id'];

    if ($action === 'upload') {
        $docType    = sanitize($_POST['doc_type'] ?? '');
        $title      = sanitize($_POST['title'] ?? '');
        $schoolYear = sanitize($_POST['school_year'] ?? currentSchoolYear());
        $notes      = sanitize($_POST['notes'] ?? '');

        if (empty($_FILES['document']['name'])) { setFlash('error', 'Please select a file.'); redirect(APP_URL . "/admin/student-documents.php?student_id=$studentId"); }

        $upload = uploadFile($_FILES['document'], 'student-docs');
        if (!$upload['success']) { setFlash('error', $upload['error']); redirect(APP_URL . "/admin/student-documents.php?student_id=$studentId"); }

        $db->prepare("INSERT INTO student_documents (student_id,doc_type,title,file_path,file_name,file_size,file_type,school_year,uploaded_by,notes) VALUES (?,?,?,?,?,?,?,?,?,?)")
           ->execute([$studentId, $docType, $title, $upload['file_path'], $upload['file_name'], $upload['file_size'], $upload['file_type'], $schoolYear, $_SESSION['user_id'], $notes]);

        setFlash('success', 'Document uploaded.');
        redirect(APP_URL . "/admin/student-documents.php?student_id=$studentId");
    }

    if ($action === 'delete') {
        $docId = (int)$_POST['doc_id'];
        $doc = $db->prepare("SELECT * FROM student_documents WHERE id=?");
        $doc->execute([$docId]);
        $doc = $doc->fetch();
        if ($doc) {
            $fp = __DIR__ . '/../' . $doc['file_path'];
            if (file_exists($fp)) unlink($fp);
            $db->prepare("DELETE FROM student_documents WHERE id=?")->execute([$docId]);
        }
        setFlash('success', 'Document deleted.');
        redirect(APP_URL . "/admin/student-documents.php?student_id=$studentId");
    }
}

$studentId = (int)($_GET['student_id'] ?? 0);
$student   = null;

if ($studentId) {
    $stmt = $db->prepare("SELECT s.*, sec.section_name, gl.grade_name FROM students s LEFT JOIN sections sec ON s.section_id=sec.id LEFT JOIN grade_levels gl ON sec.grade_level_id=gl.id WHERE s.id=?");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch();
}

$students = $db->query("SELECT s.id, s.first_name, s.last_name, sec.section_name, gl.grade_name FROM students s LEFT JOIN sections sec ON s.section_id=sec.id LEFT JOIN grade_levels gl ON sec.grade_level_id=gl.id ORDER BY s.last_name, s.first_name")->fetchAll();

$docs = [];
if ($student) {
    $stmt = $db->prepare("SELECT sd.*, u.full_name as uploader FROM student_documents sd LEFT JOIN users u ON sd.uploaded_by=u.id WHERE sd.student_id=? ORDER BY sd.created_at DESC");
    $stmt->execute([$studentId]);
    $docs = $stmt->fetchAll();
}

$docTypeOptions = ['Birth Certificate','Form 137','Form 138','Report Card','Good Moral Certificate','Medical Certificate','Baptismal Certificate','ID Picture','Transfer Credentials','Other'];
?>

<div class="page-header">
    <div><h1><i class="bi bi-person-vcard me-2 text-primary"></i>Student Documents</h1></div>
</div>

<?php showFlash(); ?>

<div class="row g-3">
    <div class="col-md-3">
        <div class="card">
            <div class="card-header">Select Student</div>
            <div class="card-body">
                <form method="GET">
                    <select name="student_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">— Select Student —</option>
                        <?php foreach ($students as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= $studentId==$s['id']?'selected':'' ?>>
                                <?= htmlspecialchars($s['last_name'] . ', ' . $s['first_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-9">
        <?php if ($student): ?>
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>
                    <strong><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></strong>
                    <span class="text-muted small ms-2"><?= htmlspecialchars($student['grade_name'] . ' — ' . $student['section_name']) ?></span>
                </span>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
                    <i class="bi bi-upload me-1"></i>Upload
                </button>
            </div>
            <div class="card-body p-0">
                <?php if ($docs): ?>
                <table class="table table-hover mb-0">
                    <thead><tr><th>Type</th><th>Title</th><th>School Year</th><th>Uploaded By</th><th>Date</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($docs as $doc): ?>
                        <tr>
                            <td class="small fw-600"><?= htmlspecialchars($doc['doc_type']) ?></td>
                            <td class="small"><?= htmlspecialchars($doc['title'] ?? '') ?></td>
                            <td class="small"><?= htmlspecialchars($doc['school_year'] ?? '—') ?></td>
                            <td class="small"><?= htmlspecialchars($doc['uploader'] ?? '—') ?></td>
                            <td class="small text-muted"><?= formatDate($doc['created_at'], 'M d, Y') ?></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="<?= APP_URL . '/' . $doc['file_path'] ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-download"></i></a>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="student_id" value="<?= $studentId ?>">
                                        <input type="hidden" name="doc_id" value="<?= $doc['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Delete this document?"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="p-4 text-center text-muted">No documents uploaded for this student.</div>
                <?php endif; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="card"><div class="card-body text-center text-muted py-5">Select a student to manage their documents.</div></div>
        <?php endif; ?>
    </div>
</div>

<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload">
                <input type="hidden" name="student_id" value="<?= $studentId ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Upload Student Document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Document Type</label>
                        <select name="doc_type" class="form-select" required>
                            <?php foreach ($docTypeOptions as $opt): ?>
                                <option value="<?= $opt ?>"><?= $opt ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Title / Description</label>
                        <input type="text" name="title" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">School Year</label>
                        <input type="text" name="school_year" class="form-control" value="<?= currentSchoolYear() ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">File</label>
                        <input type="file" name="document" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-upload me-1"></i>Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
