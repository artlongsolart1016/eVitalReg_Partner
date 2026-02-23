<?php

require_once 'classes/SecurityHelper.php';

session_start();

// Log logout
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    SecurityHelper::auditLog('LOGOUT', "User logged out");
}

// Destroy session
session_unset();
session_destroy();

// Redirect to login
header('Location: login.php');
exit;
?>
