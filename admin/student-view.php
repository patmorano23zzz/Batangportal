<?php
$pageTitle = 'Student Profile';
require_once __DIR__ . '/../includes/header.php';
requireRole(['admin', 'teacher']);

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("
    SELECT s.*, sec.section_name, gl.grade_name, u.full_name as parent_name, u.contact_number as parent_contact
    FROM students s
    LEFT JOIN sections sec ON s.section_id=sec.id
    LEFT JOIN grade_levels gl ON sec.grade_level_id=gl.id
    LEFT JOIN users u ON s.parent_user_id=u.id
    WHERE s.id=?
");
$stmt->execute([$id]);
$student = $stmt->fetch();

if (!$student) { setFlash('error', 'Student not found.'); redirect(APP_URL . '/admin/students.php'); }

// Get grades summary
$grades = $db->prepare("
    SELECT sub.subject_name, g.quarter, g.grade, g.school_year
    FROM grades g
    JOIN subjects sub ON g.subject_id=sub.id
    WHERE g.student_id=?
    ORDER BY g.school_year DESC, sub.subject_name, g.quarter
");
$grades->execute([$id]);
$grades = $grades->fetchAll();

// Get requests
$requests = $db->prepare("
    SELECT dr.*, dt.doc_name FROM document_requests dr JOIN document_types dt ON dr.document_type_id=dt.id
    WHERE dr.student_id=? ORDER BY dr.date_requested DESC LIMIT 10
");
$requests->execute([$id]);
$requests = $requests->fetchAll();

// Get student documents
$docs = $db->prepare("SELECT * FROM student_documents WHERE student_id=? ORDER BY created_at DESC");
$docs->execute([$id]);
$docs = $docs->fetchAll();
?>

<div class="page-header">
    <div>
        <h1><i class="bi bi-person-badge me-2 text-primary"></i>Student Profile</h1>
        <nav aria-label="breadcrumb"><ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="students.php">Students</a></li>
            <li class="breadcrumb-item active"><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></li>
        </ol></nav>
    </div>
    <div class="d-flex gap-2">
        <a href="student-form.php?id=<?= $id ?>" class="btn btn-outline-secondary"><i class="bi bi-pencil me-1"></i>Edit</a>
        <button class="btn btn-outline-secondary no-print" data-print><i class="bi bi-printer me-1"></i>Print</button>
    </div>
</div>

<div class="row g-3">
    <!-- Profile Card -->
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <?php if (!empty($student['profile_photo'])): ?>
                    <img src="<?= APP_URL . '/' . $student['profile_photo'] ?>" class="rounded-circle mb-3" width="100" height="100" style="object-fit:cover">
                <?php else: ?>
                    <div class="avatar mx-auto mb-3" style="width:100px;height:100px;font-size:2.5rem"><?= strtoupper(substr($student['first_name'], 0, 1)) ?></div>
                <?php endif; ?>
                <h5 class="fw-700 mb-0"><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></h5>
                <div class="text-muted small"><?= htmlspecialchars($student['grade_name'] ?? '—') ?> — <?= htmlspecialchars($student['section_name'] ?? '—') ?></div>
                <div class="mt-2"><?= statusBadge($student['enrollment_status']) ?></div>
                <?php if ($student['lrn']): ?>
                    <div class="mt-2 small"><strong>LRN:</strong> <?= htmlspecialchars($student['lrn']) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Details -->
    <div class="col-md-9">
        <ul class="nav nav-tabs mb-3" id="profileTabs">
            <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-info">Personal Info</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-grades">Grades</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-requests">Requests</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-docs">Documents</a></li>
        </ul>

        <div class="tab-content">
            <!-- Personal Info -->
            <div class="tab-pane fade show active" id="tab-info">
                <div class="card">
                    <div class="card-body">
                        <div class="row g-2">
                            <?php
                            $fields = [
                                'Date of Birth' => formatDate($student['date_of_birth']) . ' (Age ' . age($student['date_of_birth']) . ')',
                                'Gender'        => $student['gender'],
                                'Place of Birth'=> $student['place_of_birth'],
                                'Nationality'   => $student['nationality'],
                                'Religion'      => $student['religion'],
                                'Mother Tongue' => $student['mother_tongue'],
                                'Address'       => $student['address'],
                                'Barangay'      => $student['barangay'],
                                'Municipality'  => $student['municipality'],
                                'Province'      => $student['province'],
                                'Blood Type'    => $student['blood_type'],
                                'Height'        => $student['height_cm'] ? $student['height_cm'] . ' cm' : '—',
                                'Weight'        => $student['weight_kg'] ? $student['weight_kg'] . ' kg' : '—',
                            ];
                            foreach ($fields as $label => $value):
                            ?>
                            <div class="col-md-6">
                                <div class="small text-muted"><?= $label ?></div>
                                <div class="fw-600 small"><?= htmlspecialchars($value ?: '—') ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <hr>
                        <h6 class="fw-700">Family Information</h6>
                        <div class="row g-2">
                            <?php
                            $family = [
                                "Father's Name"    => $student['father_name'],
                                "Father's Contact" => $student['father_contact'],
                                "Mother's Name"    => $student['mother_name'],
                                "Mother's Contact" => $student['mother_contact'],
                                'Guardian'         => $student['guardian_name'],
                                'Guardian Contact' => $student['guardian_contact'],
                                'Parent Account'   => $student['parent_name'],
                                'Parent Contact'   => $student['parent_contact'],
                            ];
                            foreach ($family as $label => $value):
                            ?>
                            <div class="col-md-6">
                                <div class="small text-muted"><?= $label ?></div>
                                <div class="fw-600 small"><?= htmlspecialchars($value ?: '—') ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Grades -->
            <div class="tab-pane fade" id="tab-grades">
                <div class="card">
                    <div class="card-body p-0">
                        <?php if ($grades): ?>
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Subject</th><th>School Year</th><th>Q1</th><th>Q2</th><th>Q3</th><th>Q4</th></tr></thead>
                            <tbody>
                                <?php
                                $grouped = [];
                                foreach ($grades as $g) {
                                    $grouped[$g['school_year']][$g['subject_name']][$g['quarter']] = $g['grade'];
                                }
                                foreach ($grouped as $sy => $subjects):
                                    foreach ($subjects as $subName => $qs):
                                ?>
                                <tr>
                                    <td class="small"><?= htmlspecialchars($subName) ?></td>
                                    <td class="small text-muted"><?= htmlspecialchars($sy) ?></td>
                                    <?php for ($q = 1; $q <= 4; $q++): $g = $qs[$q] ?? null; ?>
                                    <td class="small <?= $g !== null ? ($g >= 75 ? 'text-success' : 'text-danger') : '' ?>">
                                        <?= $g !== null ? number_format($g, 2) : '—' ?>
                                    </td>
                                    <?php endfor; ?>
                                </tr>
                                <?php endforeach; endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <div class="p-4 text-center text-muted">No grades recorded yet.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Requests -->
            <div class="tab-pane fade" id="tab-requests">
                <div class="card">
                    <div class="card-body p-0">
                        <?php if ($requests): ?>
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Request #</th><th>Document</th><th>Status</th><th>Date</th></tr></thead>
                            <tbody>
                                <?php foreach ($requests as $r): ?>
                                <tr>
                                    <td><code class="small"><?= htmlspecialchars($r['request_number']) ?></code></td>
                                    <td class="small"><?= htmlspecialchars($r['doc_name']) ?></td>
                                    <td><?= statusBadge($r['status']) ?></td>
                                    <td class="small text-muted"><?= formatDate($r['date_requested'], 'M d, Y') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <div class="p-4 text-center text-muted">No document requests.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Documents -->
            <div class="tab-pane fade" id="tab-docs">
                <div class="card">
                    <div class="card-header d-flex justify-content-between">
                        <span>Student Documents</span>
                        <a href="student-documents.php?student_id=<?= $id ?>" class="btn btn-sm btn-outline-primary">Manage</a>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($docs): ?>
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Type</th><th>Title</th><th>Date</th><th></th></tr></thead>
                            <tbody>
                                <?php foreach ($docs as $doc): ?>
                                <tr>
                                    <td class="small"><?= htmlspecialchars($doc['doc_type']) ?></td>
                                    <td class="small"><?= htmlspecialchars($doc['title'] ?? '') ?></td>
                                    <td class="small text-muted"><?= formatDate($doc['created_at'], 'M d, Y') ?></td>
                                    <td><a href="<?= APP_URL . '/' . $doc['file_path'] ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-download"></i></a></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <div class="p-4 text-center text-muted">No documents uploaded.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
