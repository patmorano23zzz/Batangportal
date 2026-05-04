<?php
$pageTitle = 'Change Password';
require_once __DIR__ . '/includes/header.php';
requireLogin();

$db = getDB();
$userId = $_SESSION['user_id'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $userRow = $db->prepare("SELECT password FROM users WHERE id=?");
    $userRow->execute([$userId]);
    $userRow = $userRow->fetch();

    if (!password_verify($current, $userRow['password'])) {
        $error = 'Current password is incorrect.';
    } elseif (strlen($new) < 8) {
        $error = 'New password must be at least 8 characters.';
    } elseif ($new !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($new, PASSWORD_DEFAULT), $userId]);
        auditLog('CHANGE_PASSWORD', 'users', $userId);
        setFlash('success', 'Password changed successfully.');
        redirect(APP_URL . '/profile.php');
    }
}
?>

<div class="page-header">
    <h1><i class="bi bi-key me-2 text-primary"></i>Change Password</h1>
</div>

<div class="card" style="max-width:400px">
    <div class="card-body">
        <?php if ($error): ?>
        <div class="alert alert-danger small"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Current Password</label>
                <input type="password" name="current_password" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">New Password</label>
                <input type="password" name="new_password" class="form-control" required minlength="8">
                <div class="form-text">Minimum 8 characters.</div>
            </div>
            <div class="mb-4">
                <label class="form-label">Confirm New Password</label>
                <input type="password" name="confirm_password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Change Password</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
