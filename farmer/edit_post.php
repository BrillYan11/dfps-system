<?php
session_start();
include '../includes/db.php';

// Authentication and Authorization Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'FARMER') {
    header("Location: ../login.php");
    exit;
}

$farmer_id = $_SESSION['user_id'];
$post_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$post_id) {
    header("Location: index.php");
    exit;
}

// 1. Fetch the existing post data and verify ownership
$stmt = $conn->prepare("
    SELECT p.*, pi.id as image_id, pi.file_path
    FROM posts p
    LEFT JOIN post_images pi ON p.id = pi.post_id
    WHERE p.id = ? AND p.farmer_id = ?
    LIMIT 1
");
$stmt->bind_param("ii", $post_id, $farmer_id);
$stmt->execute();
$result = $stmt->get_result();
$post = $result->fetch_assoc();
$stmt->close();

if (!$post) {
    // Farmer does not own this post or post does not exist
    header("Location: index.php");
    exit;
}

// Fetch produce options for the dropdown
$produce_options = '';
$produce_result = $conn->query("SELECT id, name, unit FROM produce WHERE is_active = 1 ORDER BY name ASC");
if ($produce_result) {
    while ($row = $produce_result->fetch_assoc()) {
        $selected = ($row['id'] == $post['produce_id']) ? 'selected' : '';
        $produce_options .= '<option value="' . htmlspecialchars($row['id']) . '" data-unit="' . htmlspecialchars($row['unit']) . '" ' . $selected . '>' . htmlspecialchars($row['name']) . '</option>';
    }
}

$error_message = '';
$success_message = '';

// Handle Form Submission for UPDATE
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and retrieve form data
    $title = filter_input(INPUT_POST, 'title', FILTER_UNSAFE_RAW);
    $description = filter_input(INPUT_POST, 'description', FILTER_UNSAFE_RAW);
    $produce_id = filter_input(INPUT_POST, 'produce_id', FILTER_VALIDATE_INT);
    $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_FLOAT);
    $unit = filter_input(INPUT_POST, 'unit', FILTER_UNSAFE_RAW);
    $status = filter_input(INPUT_POST, 'status', FILTER_UNSAFE_RAW);

    if (!$title || !$produce_id || $price === false || $quantity === false || !$unit || !$status) {
        $error_message = "Please fill in all required fields correctly.";
    } else {
        $conn->begin_transaction();
        try {
            // Update the posts table
            $update_stmt = $conn->prepare("UPDATE posts SET title = ?, description = ?, produce_id = ?, price = ?, quantity = ?, unit = ?, status = ? WHERE id = ? AND farmer_id = ?");
            $update_stmt->bind_param("ssiddssii", $title, $description, $produce_id, $price, $quantity, $unit, $status, $post_id, $farmer_id);
            if (!$update_stmt->execute()) {
                throw new Exception("Error updating post details: " . $update_stmt->error);
            }
            $update_stmt->close();

            // Image Upload Handling
            if (isset($_FILES['post_image']) && $_FILES['post_image']['error'] == UPLOAD_ERR_OK) {
                // New image uploaded, so delete the old one first
                if (!empty($post['file_path']) && file_exists('../' . $post['file_path'])) {
                    unlink('../' . $post['file_path']);
                    $del_img_stmt = $conn->prepare("DELETE FROM post_images WHERE id = ?");
                    $del_img_stmt->bind_param("i", $post['image_id']);
                    $del_img_stmt->execute();
                    $del_img_stmt->close();
                }

                $upload_dir = '../uploads/';
                $filename = uniqid() . '-' . basename($_FILES['post_image']['name']);
                $target_file = $upload_dir . $filename;
                $image_path_for_db = 'uploads/' . $filename;

                if (move_uploaded_file($_FILES['post_image']['tmp_name'], $target_file)) {
                    $img_stmt = $conn->prepare("INSERT INTO post_images (post_id, file_path) VALUES (?, ?)");
                    $img_stmt->bind_param("is", $post_id, $image_path_for_db);
                    if (!$img_stmt->execute()) {
                        throw new Exception("Error saving new image reference.");
                    }
                    $img_stmt->close();
                } else {
                    throw new Exception("Failed to upload new image.");
                }
            }

            $conn->commit();
            $success_message = "Post updated successfully!";
            header("refresh:2;url=index.php");

        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "An error occurred: " . $e->getMessage();
        }
    }
}


include '../header/headerfarmer.php';
?>

<div class="container my-4">
  <div class="row justify-content-center">
    <div class="col-12 col-md-10 col-lg-8">

      <div class="d-flex align-items-center mb-3">
        <a href="index.php" class="btn btn-sm btn-outline-secondary me-2"><i class="bi bi-arrow-left"></i></a>
        <h3 class="mb-0">Edit Product Post</h3>
      </div>

      <?php if ($success_message): ?>
          <div class="alert alert-success"><?php echo $success_message; ?></div>
      <?php endif; ?>
      <?php if ($error_message): ?>
          <div class="alert alert-danger"><?php echo $error_message; ?></div>
      <?php endif; ?>

      <?php if (empty($success_message)): ?>
      <div class="card">
        <div class="card-body p-4">
          <form method="POST" action="edit_post.php?id=<?php echo $post_id; ?>" enctype="multipart/form-data">
            <div class="mb-3">
              <label class="form-label">Post Title</label>
              <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($post['title']); ?>" required>
            </div>

            <div class="mb-3">
              <label class="form-label">Description</label>
              <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($post['description']); ?></textarea>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Produce Type</label>
                    <select name="produce_id" id="produce-select" class="form-select" required>
                        <?php echo $produce_options; ?>
                    </select>
                </div>
                 <div class="col-md-6">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="ACTIVE" <?php echo ($post['status'] == 'ACTIVE') ? 'selected' : ''; ?>>Active</option>
                        <option value="SOLD" <?php echo ($post['status'] == 'SOLD') ? 'selected' : ''; ?>>Sold</option>
                        <option value="HIDDEN" <?php echo ($post['status'] == 'HIDDEN') ? 'selected' : ''; ?>>Hidden</option>
                    </select>
                </div>
            </div>

            <div class="row g-3 mb-3">
              <div class="col-md-4">
                <label class="form-label">Price</label>
                <div class="input-group">
                  <span class="input-group-text">₱</span>
                  <input type="number" name="price" step="0.01" class="form-control" value="<?php echo htmlspecialchars($post['price']); ?>" required>
                </div>
              </div>
              <div class="col-md-4">
                <label class="form-label">Quantity</label>
                <input type="number" name="quantity" step="0.01" class="form-control" value="<?php echo htmlspecialchars($post['quantity']); ?>" required>
              </div>
              <div class="col-md-4">
                <label class="form-label">Unit</label>
                <input type="text" name="unit" id="unit-input" class="form-control" value="<?php echo htmlspecialchars($post['unit']); ?>" required>
              </div>
            </div>

            <div class="mb-4">
              <label for="post_image" class="form-label">Change Product Image</label>
              <div class="d-flex align-items-center">
                  <?php if (!empty($post['file_path'])): ?>
                    <img src="../<?php echo htmlspecialchars($post['file_path']); ?>" width="100" class="me-3 rounded">
                  <?php endif; ?>
                  <input class="form-control" type="file" id="post_image" name="post_image" accept="image/png, image/jpeg, image/gif">
              </div>
              <small class="form-text text-muted">Upload a new photo to replace the current one.</small>
            </div>

            <div class="text-end">
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
          </form>
        </div>
      </div>
      <?php endif; ?>

    </div>
  </div>
</div>

<script>
document.getElementById('produce-select').addEventListener('change', function() {
    var selectedOption = this.options[this.selectedIndex];
    var unit = selectedOption.getAttribute('data-unit');
    if (unit) {
        document.getElementById('unit-input').value = unit;
    }
});
</script>

<?php include '../footer/footerfarmer.php'; ?>
