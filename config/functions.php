<?php
require_once __DIR__ . '/database.php';

// ============================================================
// SESSION HELPERS
// ============================================================
function startSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function isLoggedIn() {
    startSession();
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirect(APP_URL . '/login.php');
    }
}

function requireRole($roles) {
    requireLogin();
    if (!is_array($roles)) $roles = [$roles];
    if (!in_array($_SESSION['role'], $roles)) {
        redirect(APP_URL . '/unauthorized.php');
    }
}

function currentUser() {
    startSession();
    return $_SESSION ?? [];
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isTeacher() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'teacher';
}

function isParent() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'parent';
}

// ============================================================
// REDIRECT & URL
// ============================================================
function redirect($url) {
    $url = filter_var($url, FILTER_SANITIZE_URL);
    if (!headers_sent()) {
        header("Location: $url");
        exit();
    }
    // Headers already sent — flush buffer and use JS redirect
    echo "<script>window.location.href=" . json_encode($url) . ";</script>";
    echo "<noscript><meta http-equiv='refresh' content='0;url=" . htmlspecialchars($url) . "'></noscript>";
    exit();
}

function baseUrl($path = '') {
    return APP_URL . '/' . ltrim($path, '/');
}

// ============================================================
// SANITIZATION & VALIDATION
// ============================================================
function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function sanitizeArray($data) {
    return array_map('sanitize', $data);
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// ============================================================
// FLASH MESSAGES
// ============================================================
function setFlash($type, $message) {
    startSession();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    startSession();
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function showFlash() {
    $flash = getFlash();
    if ($flash) {
        $type = $flash['type']; // success, error, warning, info
        $alertClass = [
            'success' => 'alert-success',
            'error'   => 'alert-danger',
            'warning' => 'alert-warning',
            'info'    => 'alert-info',
        ][$type] ?? 'alert-info';
        echo "<div class='alert {$alertClass} alert-dismissible fade show' role='alert'>
                " . htmlspecialchars($flash['message']) . "
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
              </div>";
    }
}

// ============================================================
// REQUEST NUMBER GENERATOR
// ============================================================
function generateRequestNumber() {
    $db = getDB();
    $year = date('Y');
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM document_requests WHERE YEAR(date_requested) = ?");
    $stmt->execute([$year]);
    $row = $stmt->fetch();
    $seq = str_pad($row['cnt'] + 1, 5, '0', STR_PAD_LEFT);
    return "REQ-{$year}-{$seq}";
}

// ============================================================
// FILE UPLOAD
// ============================================================
function uploadFile($file, $subfolder = 'general') {
    $uploadDir = UPLOAD_PATH . $subfolder . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_FILE_TYPES)) {
        return ['success' => false, 'error' => 'File type not allowed.'];
    }

    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'error' => 'File size exceeds 10MB limit.'];
    }

    $newName = uniqid('file_', true) . '.' . $ext;
    $destination = $uploadDir . $newName;

    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return [
            'success'   => true,
            'file_path' => 'uploads/' . $subfolder . '/' . $newName,
            'file_name' => $file['name'],
            'file_size' => $file['size'],
            'file_type' => $ext,
        ];
    }

    return ['success' => false, 'error' => 'Failed to upload file.'];
}

// ============================================================
// AUDIT LOG
// ============================================================
function auditLog($action, $table = null, $recordId = null, $details = null) {
    startSession();
    $db = getDB();
    $userId = $_SESSION['user_id'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $stmt = $db->prepare("INSERT INTO audit_log (user_id, action, table_name, record_id, details, ip_address) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$userId, $action, $table, $recordId, $details, $ip]);
}

// ============================================================
// NOTIFICATIONS
// ============================================================
function sendNotification($userId, $title, $message, $type = 'system', $refId = null) {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type, reference_id) VALUES (?,?,?,?,?)");
    $stmt->execute([$userId, $title, $message, $type, $refId]);
}

function getUnreadCount($userId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return $stmt->fetch()['cnt'];
}

// ============================================================
// DATE HELPERS
// ============================================================
function formatDate($date, $format = 'F d, Y') {
    if (!$date) return '—';
    return date($format, strtotime($date));
}

function formatDateTime($dt) {
    if (!$dt) return '—';
    return date('M d, Y h:i A', strtotime($dt));
}

function age($dob) {
    if (!$dob) return '—';
    return date_diff(date_create($dob), date_create('today'))->y;
}

// ============================================================
// STATUS BADGE
// ============================================================
function statusBadge($status) {
    $map = [
        'pending'    => 'warning',
        'processing' => 'info',
        'ready'      => 'primary',
        'released'   => 'success',
        'rejected'   => 'danger',
        'cancelled'  => 'secondary',
        'enrolled'   => 'success',
        'transferred'=> 'info',
        'dropped'    => 'danger',
        'graduated'  => 'primary',
        'paid'       => 'success',
        'unpaid'     => 'danger',
        'waived'     => 'secondary',
    ];
    $color = $map[$status] ?? 'secondary';
    return "<span class='badge bg-{$color}'>" . ucfirst($status) . "</span>";
}

// ============================================================
// PAGINATION
// ============================================================
function paginate($total, $perPage, $currentPage, $url) {
    $totalPages = ceil($total / $perPage);
    if ($totalPages <= 1) return '';

    $html = "<nav><ul class='pagination pagination-sm mb-0'>";
    for ($i = 1; $i <= $totalPages; $i++) {
        $active = $i == $currentPage ? 'active' : '';
        $html .= "<li class='page-item {$active}'><a class='page-link' href='{$url}&page={$i}'>{$i}</a></li>";
    }
    $html .= "</ul></nav>";
    return $html;
}

// ============================================================
// SCHOOL YEAR HELPER
// ============================================================
function currentSchoolYear() {
    $month = (int)date('m');
    $year = (int)date('Y');
    if ($month >= 6) {
        return $year . '-' . ($year + 1);
    }
    return ($year - 1) . '-' . $year;
}
