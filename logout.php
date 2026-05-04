<?php
require_once __DIR__ . '/config/functions.php';
startSession();
if (isLoggedIn()) {
    auditLog('LOGOUT', 'users', $_SESSION['user_id'], 'User logged out');
}
session_destroy();
redirect(APP_URL . '/login.php');
