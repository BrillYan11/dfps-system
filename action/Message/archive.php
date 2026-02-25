<?php
session_start();
include '../../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$conv_id = filter_input(INPUT_GET, 'conv_id', FILTER_VALIDATE_INT);
$action = filter_input(INPUT_GET, 'action', FILTER_UNSAFE_RAW) ?: 'archive'; // 'archive' or 'unarchive'

if ($conv_id) {
    $is_archived = ($action === 'archive') ? 1 : 0;
    $stmt = $conn->prepare("UPDATE conversation_participants SET is_archived = ? WHERE conversation_id = ? AND user_id = ?");
    $stmt->bind_param("iii", $is_archived, $conv_id, $user_id);
    $stmt->execute();
    $stmt->close();
}

// Redirect back to message.php
$redirect_path = ($role === 'FARMER') ? '../../farmer/message.php' : '../../buyer/message.php';
header("Location: " . $redirect_path);
exit;
