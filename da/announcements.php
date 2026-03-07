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

// Handle Announcement Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_announcement'])) {
    $title = trim($_POST['title']);
    $body = trim($_POST['body']);
    $area_id = filter_input(INPUT_POST, 'area_id', FILTER_VALIDATE_INT) ?: null;

    if (empty($title) || empty($body)) {
        $error_msg = "Title and body are required.";
    } else {
        $stmt = $conn->prepare("INSERT INTO announcements (da_id, area_id, title, body) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiss", $da_id, $area_id, $title, $body);
        if ($stmt->execute()) {
            $success_msg = "Announcement posted successfully!";
            
            // Create a system notification for all relevant users
            $notif_query = "SELECT id FROM users WHERE role != 'DA'";
            if ($area_id) {
                $notif_query .= " AND area_id = $area_id";
            }
            $user_res = $conn->query($notif_query);
            while($u = $user_res->fetch_assoc()) {
                NotificationModel::createNotification($conn, $u['id'], 'ANNOUNCEMENT', $title, $body, 'notification.php');
            }
        } else {
            $error_msg = "Error: " . $conn->error;
        }
        $stmt->close();
    }
}

// Fetch existing announcements
$announcements = $conn->query("
    SELECT a.*, ar.name as area_name, u.first_name, u.last_name 
    FROM announcements a 
    LEFT JOIN areas ar ON a.area_id = ar.id 
    JOIN users u ON a.da_id = u.id 
    ORDER BY a.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

// Fetch areas for the dropdown
$areas = $conn->query("SELECT id, name FROM areas ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

include '../includes/universal_header.php';
?>

<main class="container-fluid px-4 my-4">
    <div class="row g-4">
        <!-- Create Announcement Form -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-primary text-white py-3 border-0 rounded-top-4">
                    <h5 class="mb-0 fw-bold">Post New Announcement</h5>
                </div>
                <div class="card-body">
                    <?php if ($success_msg): ?>
                        <div class="alert alert-success"><?php echo $success_msg; ?></div>
                    <?php endif; ?>
                    <?php if ($error_msg): ?>
                        <div class="alert alert-danger"><?php echo $error_msg; ?></div>
                    <?php endif; ?>

                    <form method="POST" action="announcements.php">
                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input type="text" name="title" class="form-control rounded-3" placeholder="Urgent: Market Update" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Target Area</label>
                            <select name="area_id" class="form-select rounded-3">
                                <option value="">All Areas (Global)</option>
                                <?php foreach($areas as $area): ?>
                                    <option value="<?php echo $area['id']; ?>"><?php echo htmlspecialchars($area['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Message Body</label>
                            <textarea name="body" class="form-control rounded-3" rows="5" placeholder="Details of the announcement..." required></textarea>
                        </div>
                        <button type="submit" name="create_announcement" class="btn btn-primary w-100 py-2 rounded-pill fw-bold">Post Announcement</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Announcements History -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-transparent py-3 border-0">
                    <h5 class="mb-0 fw-bold">Announcement History</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="bg-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Area</th>
                                    <th>Title</th>
                                    <th>Author</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($announcements)): ?>
                                    <tr><td colspan="5" class="text-center py-4">No announcements posted yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach($announcements as $ann): ?>
                                        <tr>
                                            <td><small><?php echo date('M j, Y h:i A', strtotime($ann['created_at'])); ?></small></td>
                                            <td><span class="badge <?php echo $ann['area_id'] ? 'bg-info' : 'bg-secondary'; ?>"><?php echo htmlspecialchars($ann['area_name'] ?: 'Global'); ?></span></td>
                                            <td class="fw-semibold"><?php echo htmlspecialchars($ann['title']); ?></td>
                                            <td><?php echo htmlspecialchars($ann['first_name'].' '.$ann['last_name']); ?></td>
                                            <td class="text-end">
                                                <button class="btn btn-sm btn-outline-primary rounded-circle" title="View details"><i class="bi bi-eye"></i></button>
                                                <button class="btn btn-sm btn-outline-danger rounded-circle" title="Delete"><i class="bi bi-trash"></i></button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/universal_footer.php'; ?>
