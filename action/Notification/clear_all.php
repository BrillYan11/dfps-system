<?php
session_start();
include '../../includes/db.php';
include '../../includes/NotificationModel.php';

if (!isset($_SESSION['user_id'])) {
    // If user not logged in, do nothing or redirect to login
    header("Location: ../../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role']; // To redirect back to the correct notification page

// Clear all notifications
NotificationModel::clearAllNotifications($conn, $user_id);

// Determine the correct redirect path
$redirect_path = '../../index.php'; // Default fallback
if ($role === 'BUYER') {
    $redirect_path = '../../buyer/notification.php';
} elseif ($role === 'FARMER') {
    $redirect_path = '../../farmer/notification.php';
}

header("Location: " . $redirect_path);
exit;
