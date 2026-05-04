<?php
require_once __DIR__ . '/config/functions.php';
startSession();

// Already logged in
if (isLoggedIn()) {
    redirect(APP_URL . '/' . ($_SESSION['role'] === 'parent' ? 'portal' : 'admin') . '/dashboard.php');
}

$error = '';
$timeout = isset($_GET['timeout']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter your username and password.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['username']   = $user['username'];
            $_SESSION['full_name']  = $user['full_name'];
            $_SESSION['role']       = $user['role'];
            $_SESSION['profile_photo'] = $user['profile_photo'];
            $_SESSION['last_activity'] = time();

            auditLog('LOGIN', 'users', $user['id'], 'User logged in');

            $redirect = $user['role'] === 'parent' ? '/portal/dashboard.php' : '/admin/dashboard.php';
            redirect(APP_URL . $redirect);
        } else {
            $error = 'Invalid username or password.';
            auditLog('LOGIN_FAILED', 'users', null, "Failed login attempt for: $username");
        }
    }
}

$db = getDB();
$schoolInfo = $db->query("SELECT * FROM school_info LIMIT 1")->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — <?= htmlspecialchars($schoolInfo['school_name'] ?? 'BatangPortal') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="<?= APP_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="login-wrapper">
    <div class="login-card card p-4 p-md-5 mx-3">
        <!-- Logo & School Name -->
        <div class="text-center mb-4">
            <?php if (!empty($schoolInfo['logo'])): ?>
                <img src="<?= APP_URL . '/' . $schoolInfo['logo'] ?>" alt="Logo" height="72" class="mb-3">
            <?php else: ?>
                <div class="mb-3">
                    <i class="bi bi-mortarboard-fill text-primary" style="font-size:3.5rem"></i>
                </div>
            <?php endif; ?>
            <h4 class="fw-800 mb-0"><?= htmlspecialchars($schoolInfo['school_name'] ?? 'BatangPortal') ?></h4>
            <p class="text-muted small mb-0">School Portal System</p>
            <?php if (!empty($schoolInfo['division'])): ?>
                <p class="text-muted small"><?= htmlspecialchars($schoolInfo['division']) ?></p>
            <?php endif; ?>
        </div>

        <?php if ($timeout): ?>
            <div class="alert alert-warning small py-2">Your session has expired. Please log in again.</div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger small py-2"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <div class="mb-3">
                <label class="form-label">Username</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input type="text" name="username" class="form-control" placeholder="Enter username"
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autofocus>
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" name="password" id="passwordInput" class="form-control" placeholder="Enter password" required>
                    <button type="button" class="btn btn-outline-secondary" onclick="togglePassword()">
                        <i class="bi bi-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100 py-2 fw-700">
                <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
            </button>
        </form>

        <div class="text-center mt-4 small text-muted">
            <p class="mb-2">
                No account yet?
                <a href="<?= APP_URL ?>/register.php" class="fw-700 text-decoration-none">Register here</a>
            </p>
            <p class="mb-1">For account issues, contact the school administrator.</p>
            <p class="mb-0">&copy; <?= date('Y') ?> BatangPortal — DepEd Philippines</p>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePassword() {
    const input = document.getElementById('passwordInput');
    const icon = document.getElementById('eyeIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'bi bi-eye';
    }
}
</script>
</body>
</html>
