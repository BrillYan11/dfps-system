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
$redirect_url = filter_input(INPUT_GET, 'redirect', FILTER_SANITIZE_URL);

if ($notification_id) {
    // Mark the specific notification as read
    NotificationModel::markAsRead($conn, $notification_id, $user_id);
}

// Redirect the user
if ($redirect_url) {
    $role = strtoupper($_SESSION['role'] ?? '');
    $final_redirect = $redirect_url;
    
    // 1. Absolute URLs - leave as is
    if (strpos($redirect_url, 'http') === 0) {
        $final_redirect = $redirect_url;
    }
    // 2. Already escaped from action folder - ensure it has enough ../
    elseif (strpos($redirect_url, '../../') === 0) {
        $final_redirect = $redirect_url;
    }
    elseif (strpos($redirect_url, '../') === 0) {
        // If it only has one ../, it's only escaping the Notification folder, not the action folder.
        $final_redirect = '../' . $redirect_url;
    }
    // 3. No slash at all - simple filename like 'index.php'
    elseif (strpos($redirect_url, '/') === false) {
        if ($role === 'BUYER') {
            $final_redirect = '../../buyer/' . $redirect_url;
        } elseif ($role === 'FARMER') {
            $final_redirect = '../../farmer/' . $redirect_url;
        } elseif ($role === 'DA') {
            $final_redirect = '../../da/' . $redirect_url;
        } else {
            $final_redirect = '../../' . $redirect_url;
        }
    }
    // 4. Has a slash but not absolute (e.g. 'buyer/index.php')
    else {
        // Ensure it escapes the action/Notification folder
        $final_redirect = '../../' . ltrim($redirect_url, '/');
    }
    
    header("Location: " . $final_redirect);
    exit;
} else {
    // Fallback redirect if no URL is provided
    $role = strtoupper($_SESSION['role'] ?? '');
    $fallback_path = '../../index.php';
    if ($role === 'BUYER') {
        $fallback_path = '../../buyer/notification.php';
    } elseif ($role === 'FARMER') {
        $fallback_path = '../../farmer/notification.php';
    }
    header("Location: " . $fallback_path);
    exit;
}

// Final safety exit
header("Location: ../../index.php");
exit;
exit;
