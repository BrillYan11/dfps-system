<?php
session_start();
include '../../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'DA') {
    header("Location: ../../login.php");
    exit;
}

$user_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$status = filter_input(INPUT_GET, 'status', FILTER_VALIDATE_INT);
$role_redirect = filter_input(INPUT_GET, 'role', FILTER_UNSAFE_RAW);

if ($user_id !== null && $status !== null) {
    $stmt = $conn->prepare("UPDATE users SET is_active = ? WHERE id = ? AND role != 'DA'");
    $stmt->bind_param("ii", $status, $user_id);
    $stmt->execute();
    $stmt->close();
}

$redirect_url = "../../da/users.php";
if ($role_redirect) {
    $redirect_url .= "?role=" . $role_redirect;
}

header("Location: " . $redirect_url);
exit;
