<?php
$pageTitle = 'My Profile';
require_once __DIR__ . '/includes/header.php';
requireLogin();

$db = getDB();
$userId = $_SESSION['user_id'];

$userRow = $db->prepare("SELECT * FROM users WHERE id=?");
$userRow->execute([$userId]);
$userRow = $userRow->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = sanitize($_POST['full_name'] ?? '');
    $email    = sanitize($_POST['email'] ?? '');
    $contact  = sanitize($_POST['contact_number'] ?? '');
    $address  = sanitize($_POST['address'] ?? '');

    $data = ['full_name'=>$fullName,'email'=>$email,'contact_number'=>$contact,'address'=>$address,'id'=>$userId];

    if (!empty($_FILES['profile_photo']['name'])) {
        $upload = uploadFile($_FILES['profile_photo'], 'profiles');
        if ($upload['success']) {
            $data['profile_photo'] = $upload['file_path'];
        }
    }

    $sets = implode(', ', array_map(fn($k) => "$k=:$k", array_diff(array_keys($data), ['id'])));
    $db->prepare("UPDATE users SET $sets WHERE id=:id")->execute($data);

    $_SESSION['full_name'] = $fullName;
    if (isset($data['profile_photo'])) $_SESSION['profile_photo'] = $data['profile_photo'];

    setFlash('success', 'Profile updated.');
    redirect(APP_URL . '/profile.php');
}
?>

<div class="page-header">
    <h1><i class="bi bi-person me-2 text-primary"></i>My Profile</h1>
</div>

<?php showFlash(); ?>

<div class="card" style="max-width:600px">
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <div class="text-center mb-4">
                <?php if (!empty($userRow['profile_photo'])): ?>
                    <img src="<?= APP_URL . '/' . $userRow['profile_photo'] ?>" class="rounded-circle" width="80" height="80" style="object-fit:cover">
                <?php else: ?>
                    <div class="avatar mx-auto" style="width:80px;height:80px;font-size:2rem"><?= strtoupper(substr($userRow['full_name'], 0, 1)) ?></div>
                <?php endif; ?>
                <div class="mt-2">
                    <input type="file" name="profile_photo" class="form-control form-control-sm" accept="image/*" style="max-width:250px;margin:auto">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Full Name</label>
                <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($userRow['full_name']) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Username</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($userRow['username']) ?>" disabled>
            </div>
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($userRow['email'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Contact Number</label>
                <input type="text" name="contact_number" class="form-control" value="<?= htmlspecialchars($userRow['contact_number'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Address</label>
                <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($userRow['address'] ?? '') ?>">
            </div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Changes</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
