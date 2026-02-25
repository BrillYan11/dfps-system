<?php
session_start();
include '../includes/db.php';
include '../includes/NotificationModel.php'; // Include the NotificationModel

// Authentication and Authorization Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'BUYER') {
    header("Location: ../login.php");
    exit;
}

$buyer_id = $_SESSION['user_id'];
$post_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$post_id) {
    header("Location: index.php");
    exit;
}

// Fetch post details first to use in logic below
$post = null;
$query = "
    SELECT
        p.id, p.title, p.description, p.price, p.quantity, p.unit, p.created_at,
        pr.name AS produce_name,
        a.name AS area_name,
        u.id AS farmer_id,
        u.first_name AS farmer_first_name,
        u.last_name AS farmer_last_name,
        u.email AS farmer_email
    FROM posts p
    JOIN produce pr ON p.produce_id = pr.id
    JOIN users u ON p.farmer_id = u.id
    LEFT JOIN areas a ON p.area_id = a.id
    WHERE p.id = ? AND p.status = 'ACTIVE'
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $post_id);
$stmt->execute();
$result = $stmt->get_result();
$post = $result->fetch_assoc();
$stmt->close();

// If post not found, redirect
if (!$post) {
    header("Location: index.php");
    exit;
}


// Handle "Express Interest" action
$interest_error = '';
$interest_success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['express_interest'])) {
    // Check if already interested
    $check_stmt = $conn->prepare("SELECT id FROM post_interests WHERE post_id = ? AND buyer_id = ?");
    $check_stmt->bind_param("ii", $post_id, $buyer_id);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows > 0) {
        $interest_error = "You have already expressed interest in this product.";
    } else {
        $conn->begin_transaction();
        try {
            // Insert interest into the database
            $insert_stmt = $conn->prepare("INSERT INTO post_interests (post_id, buyer_id) VALUES (?, ?)");
            $insert_stmt->bind_param("ii", $post_id, $buyer_id);
            if (!$insert_stmt->execute()) {
                throw new Exception("Failed to record interest.");
            }
            $insert_stmt->close();

            // --- Create a notification for the farmer ---
            $buyer_info_stmt = $conn->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
            $buyer_info_stmt->bind_param("i", $buyer_id);
            $buyer_info_stmt->execute();
            $buyer_info_result = $buyer_info_stmt->get_result()->fetch_assoc();
            $buyer_name = $buyer_info_result['first_name'] . ' ' . $buyer_info_result['last_name'];
            $buyer_info_stmt->close();

            $notif_title = "New Interest in your Post!";
            $notif_body = htmlspecialchars($buyer_name) . " is interested in your post: \"" . htmlspecialchars($post['title']) . "\".";
            $notif_link = "view_interests.php?post_id=" . $post_id; // Relative to farmer/ directory

            NotificationModel::createNotification($conn, $post['farmer_id'], 'NEW_INTEREST', $notif_title, $notif_body, $notif_link);

            $conn->commit();
            $interest_success = "Your interest has been noted! The farmer has been notified.";

        } catch (Exception $e) {
            $conn->rollback();
            $interest_error = "There was an error expressing interest. Please try again. " . $e->getMessage();
        }
    }
    $check_stmt->close();
}


// Fetch post images
$images = [];
$img_stmt = $conn->prepare("SELECT file_path FROM post_images WHERE post_id = ? ORDER BY id ASC");
$img_stmt->bind_param("i", $post_id);
$img_stmt->execute();
$img_result = $img_stmt->get_result();
while ($row = $img_result->fetch_assoc()) {
    $images[] = $row['file_path'];
}
$img_stmt->close();


include '../header/headerbuyer.php';
?>

<div class="container my-4">

    <div class="d-flex align-items-center mb-3">
      <a href="index.php" class="btn btn-sm btn-outline-secondary me-2"><i class="bi bi-arrow-left"></i> Back to Market</a>
    </div>

    <?php if ($interest_success): ?>
        <div class="alert alert-success"><?php echo $interest_success; ?></div>
    <?php endif; ?>
    <?php if ($interest_error): ?>
        <div class="alert alert-danger"><?php echo $interest_error; ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Image Carousel -->
        <div class="col-lg-7">
            <?php if (!empty($images)): ?>
                <div id="postCarousel" class="carousel slide" data-bs-ride="carousel">
                    <div class="carousel-indicators">
                        <?php foreach ($images as $i => $image): ?>
                            <button type="button" data-bs-target="#postCarousel" data-bs-slide-to="<?php echo $i; ?>" class="<?php echo $i === 0 ? 'active' : ''; ?>" aria-current="true" aria-label="Slide <?php echo $i + 1; ?>"></button>
                        <?php endforeach; ?>
                    </div>
                    <div class="carousel-inner">
                        <?php foreach ($images as $i => $image): ?>
                            <div class="carousel-item <?php echo $i === 0 ? 'active' : ''; ?>">
                                <img src="../<?php echo htmlspecialchars($image); ?>" class="d-block w-100" style="height: 500px; object-fit: cover;" alt="Product Image">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button class="carousel-control-prev" type="button" data-bs-target="#postCarousel" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Previous</span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#postCarousel" data-bs-slide="next">
                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Next</span>
                    </button>
                </div>
            <?php else: ?>
                <img src="https://via.placeholder.com/800x500.png?text=No+Image+Available" class="img-fluid rounded" alt="No Image">
            <?php endif; ?>
        </div>

        <!-- Product Details -->
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-body">
                    <h2 class="card-title"><?php echo htmlspecialchars($post['title']); ?></h2>
                    <p class="text-muted">Posted on <?php echo date('F j, Y', strtotime($post['created_at'])); ?></p>

                    <p class="display-6 my-3">₱<?php echo htmlspecialchars(number_format($post['price'], 2)); ?> / <?php echo htmlspecialchars($post['unit']); ?></p>

                    <p class="card-text"><?php echo nl2br(htmlspecialchars($post['description'])); ?></p>

                    <table class="table table-sm table-borderless mt-3">
                        <tr>
                            <th style="width: 120px;">Produce Type</th>
                            <td><span class="badge bg-success"><?php echo htmlspecialchars($post['produce_name']); ?></span></td>
                        </tr>
                        <tr>
                            <th>Quantity</th>
                            <td><?php echo htmlspecialchars($post['quantity']); ?> <?php echo htmlspecialchars($post['unit']); ?> available</td>
                        </tr>
                        <tr>
                            <th>Farmer</th>
                            <td><?php echo htmlspecialchars($post['farmer_first_name'] . ' ' . $post['farmer_last_name']); ?></td>
                        </tr>
                        <tr>
                            <th>Location</th>
                            <td><i class="bi bi-geo-alt-fill"></i> <?php echo htmlspecialchars($post['area_name']); ?></td>
                        </tr>
                    </table>

                    <div class="d-grid gap-2 mt-4">
                        <form method="POST" action="view_post.php?id=<?php echo $post_id; ?>">
                            <button type="submit" name="express_interest" class="btn btn-primary btn-lg w-100">
                                <i class="bi bi-heart-fill"></i> Express Interest
                            </button>
                        </form>
                        <a href="message.php?receiver_id=<?php echo $post['farmer_id']; ?>&post_id=<?php echo $post_id; ?>" class="btn btn-outline-secondary btn-lg w-100">
                            <i class="bi bi-chat-dots-fill"></i> Send a Message
                        </a>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../footer/footerbuyer.php'; ?>
