<?php
session_start();
include '../../includes/db.php';
include '../../includes/NotificationModel.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['unread_count' => 0]);
    exit;
}

$unread_count = NotificationModel::countUnread($conn, $_SESSION['user_id']);
echo json_encode(['unread_count' => $unread_count]);
?>
