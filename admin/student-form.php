<?php
$pageTitle = 'Student Form';
require_once __DIR__ . '/../includes/header.php';
requireRole(['admin', 'teacher']);

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
$student = null;
$isEdit = false;

if ($id) {
    $stmt = $db->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->execute([$id]);
    $student = $stmt->fetch();
    if (!$student) { setFlash('error', 'Student not found.'); redirect(APP_URL . '/admin/students.php'); }
    $isEdit = true;
    $pageTitle = 'Edit Student';
} else {
    $pageTitle = 'Add Student';
}

$grades   = $db->query("SELECT * FROM grade_levels ORDER BY id")->fetchAll();
$sections = $db->query("SELECT sec.*, gl.grade_name FROM sections sec JOIN grade_levels gl ON sec.grade_level_id = gl.id ORDER BY gl.id, sec.section_name")->fetchAll();
$parents  = $db->query("SELECT id, full_name, username FROM users WHERE role='parent' ORDER BY full_name")->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'lrn'               => sanitize($_POST['lrn'] ?? ''),
        'first_name'        => sanitize($_POST['first_name'] ?? ''),
        'middle_name'       => sanitize($_POST['middle_name'] ?? ''),
        'last_name'         => sanitize($_POST['last_name'] ?? ''),
        'suffix'            => sanitize($_POST['suffix'] ?? ''),
        'gender'            => sanitize($_POST['gender'] ?? ''),
        'date_of_birth'     => sanitize($_POST['date_of_birth'] ?? ''),
        'place_of_birth'    => sanitize($_POST['place_of_birth'] ?? ''),
        'nationality'       => sanitize($_POST['nationality'] ?? 'Filipino'),
        'religion'          => sanitize($_POST['religion'] ?? ''),
        'address'           => sanitize($_POST['address'] ?? ''),
        'barangay'          => sanitize($_POST['barangay'] ?? ''),
        'municipality'      => sanitize($_POST['municipality'] ?? ''),
        'province'          => sanitize($_POST['province'] ?? ''),
        'zip_code'          => sanitize($_POST['zip_code'] ?? ''),
        'mother_tongue'     => sanitize($_POST['mother_tongue'] ?? ''),
        'ip_group'          => sanitize($_POST['ip_group'] ?? ''),
        'father_name'       => sanitize($_POST['father_name'] ?? ''),
        'father_occupation' => sanitize($_POST['father_occupation'] ?? ''),
        'father_contact'    => sanitize($_POST['father_contact'] ?? ''),
        'mother_name'       => sanitize($_POST['mother_name'] ?? ''),
        'mother_occupation' => sanitize($_POST['mother_occupation'] ?? ''),
        'mother_contact'    => sanitize($_POST['mother_contact'] ?? ''),
        'guardian_name'     => sanitize($_POST['guardian_name'] ?? ''),
        'guardian_relationship' => sanitize($_POST['guardian_relationship'] ?? ''),
        'guardian_contact'  => sanitize($_POST['guardian_contact'] ?? ''),
        'section_id'        => (int)($_POST['section_id'] ?? 0) ?: null,
        'enrollment_status' => sanitize($_POST['enrollment_status'] ?? 'enrolled'),
        'date_enrolled'     => sanitize($_POST['date_enrolled'] ?? ''),
        'school_year'       => sanitize($_POST['school_year'] ?? currentSchoolYear()),
        'height_cm'         => !empty($_POST['height_cm']) ? (float)$_POST['height_cm'] : null,
        'weight_kg'         => !empty($_POST['weight_kg']) ? (float)$_POST['weight_kg'] : null,
        'blood_type'        => sanitize($_POST['blood_type'] ?? ''),
        'parent_user_id'    => (int)($_POST['parent_user_id'] ?? 0) ?: null,
    ];

    // Validation
    if (empty($data['first_name'])) $errors[] = 'First name is required.';
    if (empty($data['last_name']))  $errors[] = 'Last name is required.';
    if (empty($data['gender']))     $errors[] = 'Gender is required.';
    if (empty($data['date_of_birth'])) $errors[] = 'Date of birth is required.';

    // LRN uniqueness
    if (!empty($data['lrn'])) {
        $lrnCheck = $db->prepare("SELECT id FROM students WHERE lrn = ? AND id != ?");
        $lrnCheck->execute([$data['lrn'], $id]);
        if ($lrnCheck->fetch()) $errors[] = 'LRN already exists.';
    }

    // Handle photo upload
    if (!empty($_FILES['profile_photo']['name'])) {
        $upload = uploadFile($_FILES['profile_photo'], 'students');
        if ($upload['success']) {
            $data['profile_photo'] = $upload['file_path'];
        } else {
            $errors[] = $upload['error'];
        }
    }

    if (empty($errors)) {
        if ($isEdit) {
            $sets = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($data)));
            $data['id'] = $id;
            $stmt = $db->prepare("UPDATE students SET $sets WHERE id = :id");
            $stmt->execute($data);
            auditLog('UPDATE_STUDENT', 'students', $id, "Updated student: {$data['first_name']} {$data['last_name']}");
            setFlash('success', 'Student record updated successfully.');
        } else {
            $cols = implode(', ', array_keys($data));
            $vals = ':' . implode(', :', array_keys($data));
            $stmt = $db->prepare("INSERT INTO students ($cols) VALUES ($vals)");
            $stmt->execute($data);
            $newId = $db->lastInsertId();
            auditLog('ADD_STUDENT', 'students', $newId, "Added student: {$data['first_name']} {$data['last_name']}");
            setFlash('success', 'Student added successfully.');
        }
        redirect(APP_URL . '/admin/students.php');
    }
}

$d = $student ?? $_POST ?? [];
?>

<div class="page-header">
    <div>
        <h1><i class="bi bi-person-plus me-2 text-primary"></i><?= $isEdit ? 'Edit Student' : 'Add New Student' ?></h1>
        <nav aria-label="breadcrumb"><ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="students.php">Students</a></li>
            <li class="breadcrumb-item active"><?= $isEdit ? 'Edit' : 'Add' ?></li>
        </ol></nav>
    </div>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger">
    <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">
    <!-- Nav Tabs -->
    <ul class="nav nav-tabs mb-3" id="studentTabs">
        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-personal">Personal Info</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-family">Family Info</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-enrollment">Enrollment</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-health">Health</a></li>
    </ul>

    <div class="tab-content">
        <!-- PERSONAL INFO -->
        <div class="tab-pane fade show active" id="tab-personal">
            <div class="card">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">LRN (Learner Reference Number)</label>
                            <input type="text" name="lrn" id="lrn" class="form-control" maxlength="12" value="<?= htmlspecialchars($d['lrn'] ?? '') ?>" placeholder="12-digit LRN">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($d['last_name'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($d['first_name'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Middle Name</label>
                            <input type="text" name="middle_name" class="form-control" value="<?= htmlspecialchars($d['middle_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">Suffix</label>
                            <input type="text" name="suffix" class="form-control" value="<?= htmlspecialchars($d['suffix'] ?? '') ?>" placeholder="Jr.">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Gender <span class="text-danger">*</span></label>
                            <select name="gender" class="form-select" required>
                                <option value="">Select</option>
                                <option value="Male" <?= ($d['gender'] ?? '') == 'Male' ? 'selected' : '' ?>>Male</option>
                                <option value="Female" <?= ($d['gender'] ?? '') == 'Female' ? 'selected' : '' ?>>Female</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Date of Birth <span class="text-danger">*</span></label>
                            <input type="date" name="date_of_birth" class="form-control" value="<?= htmlspecialchars($d['date_of_birth'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Place of Birth</label>
                            <input type="text" name="place_of_birth" class="form-control" value="<?= htmlspecialchars($d['place_of_birth'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Nationality</label>
                            <input type="text" name="nationality" class="form-control" value="<?= htmlspecialchars($d['nationality'] ?? 'Filipino') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Religion</label>
                            <input type="text" name="religion" class="form-control" value="<?= htmlspecialchars($d['religion'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Mother Tongue</label>
                            <input type="text" name="mother_tongue" class="form-control" value="<?= htmlspecialchars($d['mother_tongue'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">IP Group (if applicable)</label>
                            <input type="text" name="ip_group" class="form-control" value="<?= htmlspecialchars($d['ip_group'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Complete Address</label>
                            <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($d['address'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Barangay</label>
                            <input type="text" name="barangay" class="form-control" value="<?= htmlspecialchars($d['barangay'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Municipality/City</label>
                            <input type="text" name="municipality" class="form-control" value="<?= htmlspecialchars($d['municipality'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Province</label>
                            <input type="text" name="province" class="form-control" value="<?= htmlspecialchars($d['province'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">ZIP Code</label>
                            <input type="text" name="zip_code" class="form-control" value="<?= htmlspecialchars($d['zip_code'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Profile Photo</label>
                            <input type="file" name="profile_photo" class="form-control" accept="image/*">
                            <?php if (!empty($d['profile_photo'])): ?>
                                <img src="<?= APP_URL . '/' . $d['profile_photo'] ?>" class="mt-2 rounded" height="60">
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- FAMILY INFO -->
        <div class="tab-pane fade" id="tab-family">
            <div class="card">
                <div class="card-body">
                    <h6 class="fw-700 mb-3">Father's Information</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-5">
                            <label class="form-label">Father's Name</label>
                            <input type="text" name="father_name" class="form-control" value="<?= htmlspecialchars($d['father_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Occupation</label>
                            <input type="text" name="father_occupation" class="form-control" value="<?= htmlspecialchars($d['father_occupation'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Contact Number</label>
                            <input type="text" name="father_contact" class="form-control" value="<?= htmlspecialchars($d['father_contact'] ?? '') ?>">
                        </div>
                    </div>
                    <h6 class="fw-700 mb-3">Mother's Information</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-5">
                            <label class="form-label">Mother's Name</label>
                            <input type="text" name="mother_name" class="form-control" value="<?= htmlspecialchars($d['mother_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Occupation</label>
                            <input type="text" name="mother_occupation" class="form-control" value="<?= htmlspecialchars($d['mother_occupation'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Contact Number</label>
                            <input type="text" name="mother_contact" class="form-control" value="<?= htmlspecialchars($d['mother_contact'] ?? '') ?>">
                        </div>
                    </div>
                    <h6 class="fw-700 mb-3">Guardian (if different from parents)</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label">Guardian's Name</label>
                            <input type="text" name="guardian_name" class="form-control" value="<?= htmlspecialchars($d['guardian_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Relationship</label>
                            <input type="text" name="guardian_relationship" class="form-control" value="<?= htmlspecialchars($d['guardian_relationship'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Contact Number</label>
                            <input type="text" name="guardian_contact" class="form-control" value="<?= htmlspecialchars($d['guardian_contact'] ?? '') ?>">
                        </div>
                    </div>
                    <h6 class="fw-700 mb-3">Linked Parent Account</h6>
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label">Parent Portal Account</label>
                            <select name="parent_user_id" class="form-select">
                                <option value="">— None —</option>
                                <?php foreach ($parents as $p): ?>
                                    <option value="<?= $p['id'] ?>" <?= ($d['parent_user_id'] ?? '') == $p['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($p['full_name']) ?> (<?= htmlspecialchars($p['username']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Link to a parent account so they can view this student's records.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ENROLLMENT -->
        <div class="tab-pane fade" id="tab-enrollment">
            <div class="card">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Section</label>
                            <select name="section_id" class="form-select">
                                <option value="">— Not Assigned —</option>
                                <?php foreach ($sections as $sec): ?>
                                    <option value="<?= $sec['id'] ?>" <?= ($d['section_id'] ?? '') == $sec['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($sec['grade_name'] . ' — ' . $sec['section_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Enrollment Status</label>
                            <select name="enrollment_status" class="form-select">
                                <option value="enrolled" <?= ($d['enrollment_status'] ?? '') == 'enrolled' ? 'selected' : '' ?>>Enrolled</option>
                                <option value="transferred" <?= ($d['enrollment_status'] ?? '') == 'transferred' ? 'selected' : '' ?>>Transferred</option>
                                <option value="dropped" <?= ($d['enrollment_status'] ?? '') == 'dropped' ? 'selected' : '' ?>>Dropped</option>
                                <option value="graduated" <?= ($d['enrollment_status'] ?? '') == 'graduated' ? 'selected' : '' ?>>Graduated</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Date Enrolled</label>
                            <input type="date" name="date_enrolled" class="form-control" value="<?= htmlspecialchars($d['date_enrolled'] ?? '') ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">School Year</label>
                            <input type="text" name="school_year" class="form-control" value="<?= htmlspecialchars($d['school_year'] ?? currentSchoolYear()) ?>" placeholder="2025-2026">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- HEALTH -->
        <div class="tab-pane fade" id="tab-health">
            <div class="card">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Height (cm)</label>
                            <input type="number" name="height_cm" class="form-control" step="0.01" value="<?= htmlspecialchars($d['height_cm'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Weight (kg)</label>
                            <input type="number" name="weight_kg" class="form-control" step="0.01" value="<?= htmlspecialchars($d['weight_kg'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Blood Type</label>
                            <select name="blood_type" class="form-select">
                                <option value="">Unknown</option>
                                <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bt): ?>
                                    <option value="<?= $bt ?>" <?= ($d['blood_type'] ?? '') == $bt ? 'selected' : '' ?>><?= $bt ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-3 d-flex gap-2">
        <button type="submit" class="btn btn-primary px-4">
            <i class="bi bi-save me-1"></i><?= $isEdit ? 'Update Student' : 'Save Student' ?>
        </button>
        <a href="students.php" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
