<?php
session_start();
include '../../includes/db.php';
include '../../includes/NotificationModel.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$notification_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($notification_id) {
    // Dismiss the specific notification
    NotificationModel::dismissNotification($conn, $notification_id, $user_id);
}

// Determine the correct redirect path
$role = $_SESSION['role'];
$redirect_path = '../../index.php'; // Default fallback
if ($role === 'BUYER') {
    $redirect_path = '../../buyer/notification.php';
} elseif ($role === 'FARMER') {
    $redirect_path = '../../farmer/notification.php';
}

header("Location: " . $redirect_path);
exit;
