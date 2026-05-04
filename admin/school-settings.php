<?php
$pageTitle = 'School Settings';
require_once __DIR__ . '/../includes/header.php';
requireRole(['admin']);

$db = getDB();
$schoolInfo = $db->query("SELECT * FROM school_info LIMIT 1")->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'school_name'    => sanitize($_POST['school_name'] ?? ''),
        'school_id'      => sanitize($_POST['school_id'] ?? ''),
        'address'        => sanitize($_POST['address'] ?? ''),
        'district'       => sanitize($_POST['district'] ?? ''),
        'division'       => sanitize($_POST['division'] ?? ''),
        'region'         => sanitize($_POST['region'] ?? ''),
        'principal_name' => sanitize($_POST['principal_name'] ?? ''),
        'contact_number' => sanitize($_POST['contact_number'] ?? ''),
        'email'          => sanitize($_POST['email'] ?? ''),
        'school_year'    => sanitize($_POST['school_year'] ?? ''),
    ];

    // Handle logo upload
    if (!empty($_FILES['logo']['name'])) {
        $upload = uploadFile($_FILES['logo'], 'school');
        if ($upload['success']) {
            $data['logo'] = $upload['file_path'];
        }
    }

    if ($schoolInfo) {
        $sets = implode(', ', array_map(fn($k) => "$k=:$k", array_keys($data)));
        $data['id'] = $schoolInfo['id'];
        $db->prepare("UPDATE school_info SET $sets WHERE id=:id")->execute($data);
    } else {
        $cols = implode(', ', array_keys($data));
        $vals = ':' . implode(', :', array_keys($data));
        $db->prepare("INSERT INTO school_info ($cols) VALUES ($vals)")->execute($data);
    }

    auditLog('UPDATE_SCHOOL_SETTINGS', 'school_info', null, 'Updated school settings');
    setFlash('success', 'School settings saved.');
    redirect(APP_URL . '/admin/school-settings.php');
}
?>

<div class="page-header">
    <div>
        <h1><i class="bi bi-gear-fill me-2 text-primary"></i>School Settings</h1>
    </div>
</div>

<?php showFlash(); ?>

<div class="card" style="max-width:700px">
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">School Name <span class="text-danger">*</span></label>
                    <input type="text" name="school_name" class="form-control" value="<?= htmlspecialchars($schoolInfo['school_name'] ?? '') ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">School ID</label>
                    <input type="text" name="school_id" class="form-control" value="<?= htmlspecialchars($schoolInfo['school_id'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">School Year</label>
                    <input type="text" name="school_year" class="form-control" value="<?= htmlspecialchars($schoolInfo['school_year'] ?? currentSchoolYear()) ?>" placeholder="2025-2026">
                </div>
                <div class="col-12">
                    <label class="form-label">Address</label>
                    <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($schoolInfo['address'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">District</label>
                    <input type="text" name="district" class="form-control" value="<?= htmlspecialchars($schoolInfo['district'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Division</label>
                    <input type="text" name="division" class="form-control" value="<?= htmlspecialchars($schoolInfo['division'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Region</label>
                    <input type="text" name="region" class="form-control" value="<?= htmlspecialchars($schoolInfo['region'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Principal's Name</label>
                    <input type="text" name="principal_name" class="form-control" value="<?= htmlspecialchars($schoolInfo['principal_name'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Contact Number</label>
                    <input type="text" name="contact_number" class="form-control" value="<?= htmlspecialchars($schoolInfo['contact_number'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($schoolInfo['email'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">School Logo</label>
                    <input type="file" name="logo" class="form-control" accept="image/*">
                    <?php if (!empty($schoolInfo['logo'])): ?>
                        <img src="<?= APP_URL . '/' . $schoolInfo['logo'] ?>" class="mt-2 rounded" height="60" alt="Logo">
                    <?php endif; ?>
                </div>
            </div>
            <div class="mt-4">
                <button type="submit" class="btn btn-primary px-4">
                    <i class="bi bi-save me-1"></i>Save Settings
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
