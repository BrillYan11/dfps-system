<?php
session_start();
include '../includes/db.php';

// Authentication and Authorization Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'FARMER') {
    header("Location: ../login.php");
    exit;
}

$farmer_id = $_SESSION['user_id'];
$error_message = '';
$success_message = '';

// Fetch produce options for the dropdown
$produce_options = '';
$produce_result = $conn->query("SELECT id, name, unit FROM produce WHERE is_active = 1 ORDER BY name ASC");
if ($produce_result && $produce_result->num_rows > 0) {
    while ($row = $produce_result->fetch_assoc()) {
        $produce_options .= '<option value="' . htmlspecialchars($row['id']) . '" data-unit="' . htmlspecialchars($row['unit']) . '">' . htmlspecialchars($row['name']) . '</option>';
    }
}

// Fetch the farmer's area
$area_id = null;
$area_name = 'N/A';
$user_stmt = $conn->prepare("SELECT a.id, a.name FROM users u JOIN areas a ON u.area_id = a.id WHERE u.id = ?");
$user_stmt->bind_param("i", $farmer_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
if ($user_row = $user_result->fetch_assoc()) {
    $area_id = $user_row['id'];
    $area_name = $user_row['name'];
}
$user_stmt->close();


// Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and retrieve form data
    $title = filter_input(INPUT_POST, 'title', FILTER_UNSAFE_RAW);
    $description = filter_input(INPUT_POST, 'description', FILTER_UNSAFE_RAW);
    $produce_id = filter_input(INPUT_POST, 'produce_id', FILTER_VALIDATE_INT);
    $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_FLOAT);
    $unit = filter_input(INPUT_POST, 'unit', FILTER_UNSAFE_RAW);
    $post_area_id = $area_id; // Area is pre-determined by farmer's profile

    // Image Upload Handling
    $image_path = null;
    if (isset($_FILES['post_image']) && $_FILES['post_image']['error'] == UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        $filename = uniqid() . '-' . basename($_FILES['post_image']['name']);
        $target_file = $upload_dir . $filename;

        if (move_uploaded_file($_FILES['post_image']['tmp_name'], $target_file)) {
            // Store relative path for web access
            $image_path = 'uploads/' . $filename;
        } else {
            $error_message = 'Failed to upload image.';
        }
    }


    // Validation
    if (!$title || !$produce_id || $price === false || $quantity === false || !$unit) {
        $error_message = "Please fill in all required fields correctly.";
    } elseif ($price <= 0) {
        $error_message = "Price must be a positive number.";
    } else {
        // Start transaction
        $conn->begin_transaction();

        try {
            // Prepare an insert statement for the post
            $stmt = $conn->prepare("INSERT INTO posts (farmer_id, produce_id, title, description, price, quantity, unit, area_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iissddsi", $farmer_id, $produce_id, $title, $description, $price, $quantity, $unit, $post_area_id);

            if (!$stmt->execute()) {
                throw new Exception("Error creating post: " . $stmt->error);
            }

            $post_id = $stmt->insert_id;
            $stmt->close();

            // If an image was uploaded, insert it into the post_images table
            if ($image_path && $post_id) {
                $img_stmt = $conn->prepare("INSERT INTO post_images (post_id, file_path) VALUES (?, ?)");
                $img_stmt->bind_param("is", $post_id, $image_path);
                if (!$img_stmt->execute()) {
                    throw new Exception("Error saving image reference: " . $img_stmt->error);
                }
                $img_stmt->close();
            }

            // Commit transaction
            $conn->commit();
            $success_message = "Your product has been posted successfully!";
            // Redirect after a short delay
            header("refresh:2;url=index.php");

        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "An error occurred. " . $e->getMessage();
            // If image was uploaded but DB failed, delete the orphaned file
            if ($image_path && file_exists('../' . $image_path)) {
                unlink('../' . $image_path);
            }
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
        <h3 class="mb-0">Create a New Product Post</h3>
      </div>


      <?php if ($success_message): ?>
          <div class="alert alert-success"><?php echo $success_message; ?></div>
      <?php endif; ?>
      <?php if ($error_message): ?>
          <div class="alert alert-danger"><?php echo $error_message; ?></div>
      <?php endif; ?>

      <?php if (empty($success_message)): // Hide form on success ?>
      <div class="card">
        <div class="card-body p-4">

          <form method="POST" action="add_post.php" enctype="multipart/form-data">

            <div class="mb-3">
              <label class="form-label">Post Title</label>
              <input type="text" name="title" class="form-control" placeholder="e.g., Fresh Organic Carrots" required>
              <small class="form-text text-muted">A catchy and descriptive title for your product.</small>
            </div>

            <div class="mb-3">
              <label class="form-label">Description</label>
              <textarea name="description" class="form-control" rows="3" placeholder="Add details about the product, harvest date, etc."></textarea>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Produce Type</label>
                    <select name="produce_id" id="produce-select" class="form-select" required>
                        <option value="" selected disabled>Select a produce</option>
                        <?php echo $produce_options; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Your Area</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($area_name); ?>" disabled>
                    <small class="form-text text-muted">Your posts are automatically tagged to your registered area.</small>
                </div>
            </div>


            <div class="row g-3 mb-3">
              <div class="col-md-4">
                <label class="form-label">Price</label>
                <div class="input-group">
                  <span class="input-group-text">₱</span>
                  <input type="number" name="price" step="0.01" class="form-control" required>
                </div>
              </div>
              <div class="col-md-4">
                <label class="form-label">Quantity</label>
                <input type="number" name="quantity" step="0.01" class="form-control" required>
              </div>
              <div class="col-md-4">
                <label class="form-label">Unit</label>
                <input type="text" name="unit" id="unit-input" class="form-control" placeholder="e.g., kg, bundle" required>
              </div>
            </div>

            <div class="mb-4">
              <label for="post_image" class="form-label">Product Image</label>
              <input class="form-control" type="file" id="post_image" name="post_image" accept="image/png, image/jpeg, image/gif">
              <small class="form-text text-muted">Upload a clear photo of your product. (Optional)</small>
            </div>

            <div class="text-end">
                <button type="submit" class="btn btn-primary">Post My Product</button>
            </div>

          </form>
        </div>
      </div>
      <?php endif; ?>

    </div>
  </div>
</div>

<script>
// Script to auto-populate the 'unit' based on produce selection
document.getElementById('produce-select').addEventListener('change', function() {
    var selectedOption = this.options[this.selectedIndex];
    var unit = selectedOption.getAttribute('data-unit');
    if (unit) {
        document.getElementById('unit-input').value = unit;
    }
});
</script>


<?php include '../footer/footerfarmer.php'; ?>
