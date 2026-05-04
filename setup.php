<?php
/**
 * BatangPortal Setup Script
 * Safe to run multiple times — uses IF NOT EXISTS and INSERT IGNORE.
 */

$host   = 'localhost';
$user   = 'root';
$pass   = '';
$dbName = 'batangportal';

$errors   = [];
$messages = [];

try {
    // Connect without selecting a database first
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $messages[] = '✅ Connected to MySQL successfully.';

    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $messages[] = "✅ Database '$dbName' created/verified.";

    // Switch to the database
    $pdo->exec("USE `$dbName`");

    // Read and clean the SQL file
    $sql = file_get_contents(__DIR__ . '/db/batangportal.sql');

    // Strip the CREATE DATABASE and USE lines — already handled above
    $sql = preg_replace('/^\s*CREATE\s+DATABASE\b[^;]+;\s*/mi', '', $sql);
    $sql = preg_replace('/^\s*USE\s+\w+\s*;\s*/mi', '', $sql);

    // Remove SQL line comments (-- ...) to avoid splitting on semicolons inside comments
    $sql = preg_replace('/--[^\n]*/', '', $sql);

    // Split on semicolons and run each statement individually
    // This way one skipped duplicate doesn't abort the whole setup
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    $tableCount = 0;
    $insertCount = 0;

    foreach ($statements as $stmt) {
        if (empty($stmt)) continue;
        try {
            $pdo->exec($stmt);
            if (stripos($stmt, 'CREATE TABLE') !== false) $tableCount++;
            if (stripos($stmt, 'INSERT') !== false) $insertCount++;
        } catch (PDOException $e) {
            // 1050 = table already exists, 1062 = duplicate entry — safe to skip
            if (!in_array($e->errorInfo[1], [1050, 1062])) {
                $errors[] = '⚠️ Statement warning: ' . $e->getMessage();
            }
        }
    }

    $messages[] = "✅ Schema applied ($tableCount table(s) processed, $insertCount insert(s) processed).";

    // Create upload directories
    $dirs = [
        __DIR__ . '/uploads/profiles',
        __DIR__ . '/uploads/students',
        __DIR__ . '/uploads/school',
        __DIR__ . '/uploads/school-docs',
        __DIR__ . '/uploads/student-docs',
    ];
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) mkdir($dir, 0755, true);
    }
    $messages[] = '✅ Upload directories ready.';

    $setupOk = true;

} catch (PDOException $e) {
    $errors[] = '❌ Database error: ' . $e->getMessage();
    $setupOk = false;
} catch (Exception $e) {
    $errors[] = '❌ Error: ' . $e->getMessage();
    $setupOk = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BatangPortal Setup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;700;800&display=swap" rel="stylesheet">
    <style>body{font-family:'Nunito',sans-serif;background:#f4f6fb}</style>
</head>
<body>
<div class="container py-5" style="max-width:600px">
    <div class="text-center mb-4">
        <i class="bi bi-mortarboard-fill text-primary" style="font-size:3rem"></i>
        <h2 class="fw-800 mt-2">BatangPortal Setup</h2>
        <p class="text-muted">Philippine Elementary School Portal</p>
    </div>

    <?php if ($errors): ?>
    <div class="alert alert-warning">
        <h6 class="fw-700">Notices</h6>
        <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($messages): ?>
    <div class="card mb-4">
        <div class="card-body">
            <?php foreach ($messages as $m): ?>
            <div class="mb-1"><?= htmlspecialchars($m) ?></div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($setupOk)): ?>
    <div class="alert alert-success">
        <h5 class="fw-700">🎉 Setup Complete!</h5>
        <p class="mb-2">BatangPortal has been installed successfully.</p>
        <hr>
        <p class="mb-1"><strong>Default Admin Login:</strong></p>
        <p class="mb-1">Username: <code>admin</code></p>
        <p class="mb-3">Password: <code>Admin@123</code></p>
        <p class="small text-muted mb-0">⚠️ Please change the admin password after first login.</p>
    </div>
    <div class="d-grid">
        <a href="login.php" class="btn btn-primary btn-lg">Go to Login Page →</a>
    </div>
    <?php else: ?>
    <div class="alert alert-danger">
        <h6 class="fw-700">Setup Failed</h6>
        <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
    </div>
    <div class="d-grid">
        <a href="setup.php" class="btn btn-warning">Retry Setup</a>
    </div>
    <?php endif; ?>
</div>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</body>
</html>
