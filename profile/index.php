<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$error_message = '';
$success_message = '';

// Fetch current user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['delete_picture'])) {
        if ($user['profile_picture'] && file_exists('../' . $user['profile_picture'])) {
            unlink('../' . $user['profile_picture']);
        }
        $update_stmt = $conn->prepare("UPDATE users SET profile_picture = NULL WHERE id = ?");
        $update_stmt->bind_param("i", $user_id);
        if ($update_stmt->execute()) {
            $success_message = "Profile picture removed.";
            // Refresh user data
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
        $update_stmt->close();
    } else {
        $first_name = filter_input(INPUT_POST, 'first_name', FILTER_UNSAFE_RAW);
        $last_name = filter_input(INPUT_POST, 'last_name', FILTER_UNSAFE_RAW);
        $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
        $phone = filter_input(INPUT_POST, 'phone', FILTER_UNSAFE_RAW);
        $address = filter_input(INPUT_POST, 'address', FILTER_UNSAFE_RAW);
        $bio = filter_input(INPUT_POST, 'bio', FILTER_UNSAFE_RAW);
        $additional_details = filter_input(INPUT_POST, 'additional_details', FILTER_UNSAFE_RAW);

        // Profile picture upload
        $profile_picture = $user['profile_picture'];
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/profile_pics/';
            $filename = uniqid() . '-' . basename($_FILES['profile_picture']['name']);
            $target_file = $upload_dir . $filename;

            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_file)) {
                $profile_picture = 'uploads/profile_pics/' . $filename;
                // Delete old picture if exists
                if ($user['profile_picture'] && file_exists('../' . $user['profile_picture'])) {
                    unlink('../' . $user['profile_picture']);
                }
            } else {
                $error_message = "Failed to upload profile picture.";
            }
        }

        if (empty($error_message)) {
            $update_stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ?, bio = ?, additional_details = ?, profile_picture = ? WHERE id = ?");
            $update_stmt->bind_param("ssssssssi", $first_name, $last_name, $email, $phone, $address, $bio, $additional_details, $profile_picture, $user_id);

            if ($update_stmt->execute()) {
                $success_message = "Profile updated successfully!";
                // Refresh user data
                $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
                $stmt->close();
            } else {
                $error_message = "Error updating profile: " . $conn->error;
            }
            $update_stmt->close();
        }
    }
}

// Use universal header
include '../includes/universal_header.php';
?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white py-3">
                    <h4 class="mb-0">My Profile</h4>
                </div>
                <div class="card-body p-4">
                    <?php if ($success_message): ?>
                        <div class="alert alert-success"><?php echo $success_message; ?></div>
                    <?php endif; ?>
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger"><?php echo $error_message; ?></div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data">
                        <div class="row mb-4 align-items-center">
                            <div class="col-auto">
                                <div class="rounded-circle border bg-light d-flex align-items-center justify-content-center overflow-hidden" style="width: 120px; height: 120px;">
                                    <img src="../<?php echo $user['profile_picture'] ?? ''; ?>" 
                                         class="w-100 h-100 <?php echo empty($user['profile_picture']) ? 'd-none' : ''; ?>" 
                                         style="object-fit: cover;" 
                                         id="profile-preview">
                                    <i class="bi bi-person-circle text-secondary <?php echo !empty($user['profile_picture']) ? 'd-none' : ''; ?>" 
                                       style="font-size: 80px;" 
                                       id="profile-preview-icon"></i>
                                </div>
                            </div>
                            <div class="col">
                                <label class="form-label">Profile Picture</label>
                                <div class="d-flex gap-2">
                                    <input type="file" name="profile_picture" class="form-control" accept="image/*" onchange="previewImage(this)">
                                    <?php if (!empty($user['profile_picture'])): ?>
                                        <button type="submit" name="delete_picture" class="btn btn-outline-danger" onclick="return confirm('Remove your profile picture?')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">First Name</label>
                                <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Last Name</label>
                                <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Email Address</label>
                                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone Number</label>
                                <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Detailed Address</label>
                            <input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($user['address']); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Bio / Short Description</label>
                            <textarea name="bio" class="form-control" rows="3"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Additional Details</label>
                            <textarea name="additional_details" class="form-control" rows="3" placeholder="Any other relevant information..."><?php echo htmlspecialchars($user['additional_details'] ?? ''); ?></textarea>
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-primary px-4">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function previewImage(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('profile-preview');
            const icon = document.getElementById('profile-preview-icon');
            preview.src = e.target.result;
            preview.classList.remove('d-none');
            if (icon) icon.classList.add('d-none');
        }
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php
include '../includes/universal_footer.php';
?>
