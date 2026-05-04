<?php
require_once __DIR__ . '/../config/functions.php';

// Start output buffering immediately so redirect() works
// even after HTML has started outputting
ob_start();

startSession();

// Check session timeout
if (isLoggedIn()) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        session_destroy();
        redirect(APP_URL . '/login.php?timeout=1');
    }
    $_SESSION['last_activity'] = time();
}

$user = currentUser();
$unreadCount = isLoggedIn() ? getUnreadCount($user['user_id']) : 0;

// Get school info
$db = getDB();
$schoolInfo = $db->query("SELECT * FROM school_info LIMIT 1")->fetch();
$schoolName = $schoolInfo['school_name'] ?? APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? sanitize($pageTitle) . ' — ' : '' ?><?= htmlspecialchars($schoolName) ?></title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="<?= APP_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>

<?php if (isLoggedIn()): ?>
<!-- TOP NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top shadow-sm">
    <div class="container-fluid px-3">
        <a class="navbar-brand d-flex align-items-center gap-2 fw-bold" href="<?= APP_URL ?>/<?= isAdmin() || isTeacher() ? 'admin/' : 'portal/' ?>dashboard.php">
            <?php if (!empty($schoolInfo['logo'])): ?>
                <img src="<?= APP_URL . '/' . $schoolInfo['logo'] ?>" alt="Logo" height="36" class="rounded">
            <?php else: ?>
                <i class="bi bi-mortarboard-fill fs-4"></i>
            <?php endif; ?>
            <span class="d-none d-md-inline"><?= htmlspecialchars($schoolName) ?></span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-1">
                <!-- Notifications -->
                <li class="nav-item dropdown">
                    <a class="nav-link position-relative" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-bell-fill fs-5"></i>
                        <?php if ($unreadCount > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:.6rem">
                                <?= $unreadCount > 99 ? '99+' : $unreadCount ?>
                            </span>
                        <?php endif; ?>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end shadow" style="min-width:320px;max-height:400px;overflow-y:auto">
                        <h6 class="dropdown-header">Notifications</h6>
                        <?php
                        $notifStmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
                        $notifStmt->execute([$user['user_id']]);
                        $notifs = $notifStmt->fetchAll();
                        if ($notifs):
                            foreach ($notifs as $n):
                                $readClass = $n['is_read'] ? '' : 'fw-semibold bg-light';
                        ?>
                            <a class="dropdown-item py-2 <?= $readClass ?>" href="<?= APP_URL ?>/notifications.php?id=<?= $n['id'] ?>">
                                <div class="small text-muted"><?= formatDateTime($n['created_at']) ?></div>
                                <div><?= htmlspecialchars($n['title'] ?? '') ?></div>
                                <div class="small text-muted text-truncate"><?= htmlspecialchars($n['message']) ?></div>
                            </a>
                        <?php endforeach; else: ?>
                            <div class="dropdown-item text-muted small py-3 text-center">No notifications</div>
                        <?php endif; ?>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-center small" href="<?= APP_URL ?>/notifications.php">View all</a>
                    </div>
                </li>

                <!-- User Menu -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" data-bs-toggle="dropdown">
                        <?php if (!empty($user['profile_photo'])): ?>
                            <img src="<?= APP_URL . '/' . $user['profile_photo'] ?>" class="rounded-circle" width="30" height="30" style="object-fit:cover">
                        <?php else: ?>
                            <div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center fw-bold" style="width:30px;height:30px;font-size:.8rem">
                                <?= strtoupper(substr($user['full_name'] ?? 'U', 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                        <span class="d-none d-md-inline"><?= htmlspecialchars($user['full_name'] ?? '') ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow">
                        <li><span class="dropdown-item-text small text-muted"><?= ucfirst($user['role'] ?? '') ?></span></li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li><a class="dropdown-item" href="<?= APP_URL ?>/profile.php"><i class="bi bi-person me-2"></i>My Profile</a></li>
                        <li><a class="dropdown-item" href="<?= APP_URL ?>/change-password.php"><i class="bi bi-key me-2"></i>Change Password</a></li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li><a class="dropdown-item text-danger" href="<?= APP_URL ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- SIDEBAR + CONTENT WRAPPER -->
<div class="d-flex" id="wrapper">
    <!-- SIDEBAR -->
    <div id="sidebar" class="sidebar bg-white shadow-sm">
        <div class="sidebar-inner py-3">
            <?php if (isAdmin() || isTeacher()): ?>
            <!-- ADMIN/TEACHER MENU -->
            <div class="sidebar-section">
                <div class="sidebar-label">Main</div>
                <a href="<?= APP_URL ?>/admin/dashboard.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </div>
            <div class="sidebar-section">
                <div class="sidebar-label">Students</div>
                <a href="<?= APP_URL ?>/admin/students.php" class="sidebar-link">
                    <i class="bi bi-people-fill"></i> Student Records
                </a>
                <a href="<?= APP_URL ?>/admin/enrollment.php" class="sidebar-link">
                    <i class="bi bi-person-plus-fill"></i> Enrollment
                </a>
                <a href="<?= APP_URL ?>/admin/grades.php" class="sidebar-link">
                    <i class="bi bi-journal-check"></i> Grades
                </a>
                <a href="<?= APP_URL ?>/admin/attendance.php" class="sidebar-link">
                    <i class="bi bi-calendar-check"></i> Attendance
                </a>
            </div>
            <div class="sidebar-section">
                <div class="sidebar-label">Documents</div>
                <a href="<?= APP_URL ?>/admin/requests.php" class="sidebar-link">
                    <i class="bi bi-file-earmark-text"></i> Document Requests
                    <?php
                    $pendingCount = $db->query("SELECT COUNT(*) FROM document_requests WHERE status='pending'")->fetchColumn();
                    if ($pendingCount > 0): ?>
                        <span class="badge bg-danger ms-auto"><?= $pendingCount ?></span>
                    <?php endif; ?>
                </a>
                <a href="<?= APP_URL ?>/admin/school-documents.php" class="sidebar-link">
                    <i class="bi bi-folder-fill"></i> School Documents
                </a>
                <a href="<?= APP_URL ?>/admin/student-documents.php" class="sidebar-link">
                    <i class="bi bi-person-vcard"></i> Student Documents
                </a>
            </div>
            <?php if (isAdmin()): ?>
            <div class="sidebar-section">
                <div class="sidebar-label">Management</div>
                <a href="<?= APP_URL ?>/admin/sections.php" class="sidebar-link">
                    <i class="bi bi-diagram-3"></i> Sections
                </a>
                <a href="<?= APP_URL ?>/admin/users.php" class="sidebar-link">
                    <i class="bi bi-person-gear"></i> User Accounts
                </a>
                <a href="<?= APP_URL ?>/admin/announcements.php" class="sidebar-link">
                    <i class="bi bi-megaphone-fill"></i> Announcements
                </a>
                <a href="<?= APP_URL ?>/admin/document-types.php" class="sidebar-link">
                    <i class="bi bi-file-earmark-plus"></i> Document Types
                </a>
                <a href="<?= APP_URL ?>/admin/school-settings.php" class="sidebar-link">
                    <i class="bi bi-gear-fill"></i> School Settings
                </a>
                <a href="<?= APP_URL ?>/admin/audit-log.php" class="sidebar-link">
                    <i class="bi bi-clock-history"></i> Audit Log
                </a>
            </div>
            <?php endif; ?>

            <?php else: ?>
            <!-- PARENT MENU -->
            <div class="sidebar-section">
                <div class="sidebar-label">My Portal</div>
                <a href="<?= APP_URL ?>/portal/dashboard.php" class="sidebar-link">
                    <i class="bi bi-house-fill"></i> Dashboard
                </a>
                <a href="<?= APP_URL ?>/portal/my-children.php" class="sidebar-link">
                    <i class="bi bi-people-fill"></i> My Children
                </a>
                <a href="<?= APP_URL ?>/portal/grades.php" class="sidebar-link">
                    <i class="bi bi-journal-check"></i> Grades
                </a>
                <a href="<?= APP_URL ?>/portal/attendance.php" class="sidebar-link">
                    <i class="bi bi-calendar-check"></i> Attendance
                </a>
            </div>
            <div class="sidebar-section">
                <div class="sidebar-label">Documents</div>
                <a href="<?= APP_URL ?>/portal/request-document.php" class="sidebar-link">
                    <i class="bi bi-file-earmark-plus"></i> Request Document
                </a>
                <a href="<?= APP_URL ?>/portal/my-requests.php" class="sidebar-link">
                    <i class="bi bi-file-earmark-text"></i> My Requests
                </a>
                <a href="<?= APP_URL ?>/portal/student-documents.php" class="sidebar-link">
                    <i class="bi bi-folder2-open"></i> My Documents
                </a>
            </div>
            <div class="sidebar-section">
                <div class="sidebar-label">School</div>
                <a href="<?= APP_URL ?>/portal/announcements.php" class="sidebar-link">
                    <i class="bi bi-megaphone"></i> Announcements
                </a>
                <a href="<?= APP_URL ?>/portal/school-documents.php" class="sidebar-link">
                    <i class="bi bi-folder-fill"></i> School Documents
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <!-- END SIDEBAR -->

    <!-- MAIN CONTENT -->
    <div id="content" class="flex-grow-1 p-4" style="min-width:0">
<?php endif; ?>
