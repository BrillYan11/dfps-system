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

if ($conv_id) {
    // In a real "Delete for me" scenario, we might just remove the participant record
    // or mark it as deleted for that specific user.
    // For this implementation, we will remove the user from the conversation.
    // If no participants are left, the conversation stays but is orphaned.
    
    $stmt = $conn->prepare("DELETE FROM conversation_participants WHERE conversation_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $conv_id, $user_id);
    $stmt->execute();
    $stmt->close();
}

// Redirect back to message.php
$redirect_path = ($role === 'FARMER') ? '../../farmer/message.php' : '../../buyer/message.php';
header("Location: " . $redirect_path);
exit;
