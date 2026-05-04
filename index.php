<?php
require_once __DIR__ . '/config/functions.php';
startSession();
if (isLoggedIn()) {
    redirect(APP_URL . '/' . ($_SESSION['role'] === 'parent' ? 'portal' : 'admin') . '/dashboard.php');
} else {
    redirect(APP_URL . '/login.php');
}
