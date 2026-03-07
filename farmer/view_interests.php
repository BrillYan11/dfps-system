<?php
session_start();
include '../includes/db.php';

// Authentication and Authorization Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'FARMER') {
    header("Location: ../login.php");
    exit;
}

$farmer_id = $_SESSION['user_id'];
$post_id = filter_input(INPUT_GET, 'post_id', FILTER_VALIDATE_INT);

if (!$post_id) {
    header("Location: index.php");
    exit;
}

// 1. Verify the farmer owns the post
$post_stmt = $conn->prepare("SELECT title FROM posts WHERE id = ? AND farmer_id = ?");
$post_stmt->bind_param("ii", $post_id, $farmer_id);
$post_stmt->execute();
$post_result = $post_stmt->get_result();
if ($post_result->num_rows == 0) {
    // This farmer does not own this post, or post doesn't exist
    header("Location: index.php");
    exit;
}
$post = $post_result->fetch_assoc();
$post_stmt->close();


// 2. Fetch all users who have expressed interest in this post
$interests = [];
$interest_query = "
    SELECT
        u.id,
        u.first_name,
        u.last_name,
        u.email,
        u.phone,
        pi.created_at AS interest_date
    FROM post_interests pi
    JOIN users u ON pi.buyer_id = u.id
    WHERE pi.post_id = ?
    ORDER BY pi.created_at DESC
";
$stmt = $conn->prepare($interest_query);
$stmt->bind_param("i", $post_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $interests[] = $row;
}
$stmt->close();

include '../includes/universal_header.php';
?>

<div class="container my-4">
    <div class="d-flex align-items-center mb-3">
        <a href="index.php" class="btn btn-sm btn-outline-secondary me-2"><i class="bi bi-arrow-left"></i> Back to Products</a>
    </div>

    <h3>Buyers Interested In: "<?php echo htmlspecialchars($post['title']); ?>"</h3>
    <p class="text-muted">This list shows all buyers who have expressed interest in your product.</p>

    <div class="card">
        <div class="card-body">
            <?php if (empty($interests)): ?>
                <div class="text-center p-4">
                    <p class="mb-0">No buyers have expressed interest in this post yet.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Contact Email</th>
                                <th>Contact Phone</th>
                                <th>Date of Interest</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($interests as $interest): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($interest['first_name'] . ' ' . $interest['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($interest['email']); ?></td>
                                    <td><?php echo htmlspecialchars($interest['phone']); ?></td>
                                    <td><?php echo date('F j, Y, g:i a', strtotime($interest['interest_date'])); ?></td>
                                    <td>
                                        <a href="message.php?receiver_id=<?php echo $interest['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="bi bi-chat-dots-fill"></i> Message
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/universal_footer.php'; ?>
