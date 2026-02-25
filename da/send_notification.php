<?php
session_start();
include '../includes/db.php';
include '../includes/NotificationModel.php';

// Authentication and Authorization Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'DA') {
    header("Location: ../login.php");
    exit;
}

$da_id = $_SESSION['user_id'];
$success_msg = '';
$error_msg = '';

// Handle Notification Dispatch
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notification'])) {
    $target_role = filter_input(INPUT_POST, 'target_role', FILTER_UNSAFE_RAW);
    $target_area = filter_input(INPUT_POST, 'target_area', FILTER_VALIDATE_INT) ?: null;
    $title = trim($_POST['title']);
    $body = trim($_POST['body']);

    if (empty($title) || empty($body)) {
        $error_msg = "Title and body are required.";
    } else {
        // Build query for users to notify
        $query = "SELECT id FROM users WHERE role = ?";
        $params = [$target_role];
        $types = 's';
        if ($target_area) {
            $query .= " AND area_id = ?";
            $params[] = $target_area;
            $types .= 'i';
        }

        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_ids = [];
        while($row = $result->fetch_assoc()) { $user_ids[] = $row['id']; }
        $stmt->close();

        if (!empty($user_ids)) {
            $count = 0;
            foreach ($user_ids as $u_id) {
                if (NotificationModel::createNotification($conn, $u_id, 'SYSTEM_ALERT', $title, $body, 'notification.php')) {
                    $count++;
                }
            }
            $success_msg = "System alert sent to $count users successfully!";
        } else {
            $error_msg = "No users found matching the selected criteria.";
        }
    }
}

// Fetch areas for the dropdown
$areas = $conn->query("SELECT id, name FROM areas ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

include '../header/headerda.php';
?>

<main class="container-fluid px-4 my-4">
    <div class="row g-4">
        <!-- Notification Dispatch Form -->
        <div class="col-lg-6 mx-auto">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-success text-white py-3 border-0">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-send-fill me-2"></i>Dispatch System Alert</h5>
                </div>
                <div class="card-body p-4">
                    <?php if ($success_msg): ?>
                        <div class="alert alert-success d-flex align-items-center"><i class="bi bi-check-circle-fill me-2"></i><?php echo $success_msg; ?></div>
                    <?php endif; ?>
                    <?php if ($error_msg): ?>
                        <div class="alert alert-danger d-flex align-items-center"><i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error_msg; ?></div>
                    <?php endif; ?>

                    <form method="POST" action="send_notification.php">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Target Role</label>
                                <select name="target_role" class="form-select rounded-3 shadow-none border-2" required>
                                    <option value="FARMER">All Farmers</option>
                                    <option value="BUYER">All Buyers</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Target Area</label>
                                <select name="target_area" class="form-select rounded-3 shadow-none border-2">
                                    <option value="">All Locations (Global)</option>
                                    <?php foreach($areas as $area): ?>
                                        <option value="<?php echo $area['id']; ?>"><?php echo htmlspecialchars($area['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Alert Title</label>
                            <input type="text" name="title" class="form-control rounded-3 shadow-none border-2" placeholder="Urgent System Alert" required>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">Alert Message</label>
                            <textarea name="body" class="form-control rounded-3 shadow-none border-2" rows="6" placeholder="Details for the system alert..." required></textarea>
                            <small class="text-muted">Users will see this as a high-priority system notification.</small>
                        </div>

                        <div class="d-grid">
                            <button type="submit" name="send_notification" class="btn btn-primary btn-lg rounded-pill fw-bold shadow-sm">
                                <i class="bi bi-broadcast me-2"></i> Broadcast Alert
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include '../footer/footerda.php'; ?>
