<?php
include 'includes/db.php';

$error_message = '';
$success_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and retrieve form data
    $first_name = filter_input(INPUT_POST, 'first_name', FILTER_UNSAFE_RAW);
    $last_name = filter_input(INPUT_POST, 'last_name', FILTER_UNSAFE_RAW);
    $username = filter_input(INPUT_POST, 'username', FILTER_UNSAFE_RAW);
    $address = filter_input(INPUT_POST, 'address', FILTER_UNSAFE_RAW);
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $phone = filter_input(INPUT_POST, 'phone', FILTER_UNSAFE_RAW);
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = filter_input(INPUT_POST, 'role', FILTER_UNSAFE_RAW);
    $city_name = filter_input(INPUT_POST, 'city_name', FILTER_UNSAFE_RAW);

    // Basic validation
    if (!$first_name || !$last_name || !$username || !$address || !$email || !$phone || !$password || !$role || !$city_name) {
        $error_message = "Please fill in all required fields correctly.";
    } elseif ($password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } else {
        // Ensure the area exists in the database
        $stmt = $conn->prepare("INSERT IGNORE INTO areas (name) VALUES (?)");
        $stmt->bind_param("s", $city_name);
        $stmt->execute();
        $stmt->close();

        // Get the area_id
        $stmt = $conn->prepare("SELECT id FROM areas WHERE name = ?");
        $stmt->bind_param("s", $city_name);
        $stmt->execute();
        $res = $stmt->get_result();
        $area_row = $res->fetch_assoc();
        $area_id = $area_row['id'] ?? null;
        $stmt->close();

        if (!$area_id) {
             $error_message = "Invalid area selected.";
        } else {
            // Hash the password for security
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            // Prepare an insert statement to prevent SQL injection
            $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, username, address, email, phone, password_hash, role, area_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

            if ($stmt) {
                $stmt->bind_param("ssssssssi", $first_name, $last_name, $username, $address, $email, $phone, $password_hash, $role, $area_id);

                if ($stmt->execute()) {
                    $success_message = "Registration successful! You can now login.";
                } else {
                    // Check for duplicate entry
                    if ($conn->errno == 1062) {
                        $error_message = "This email or username is already registered.";
                    } else {
                        $error_message = "Error during registration. Please try again later. " . $stmt->error;
                    }
                }
                $stmt->close();
            } else {
                $error_message = "Failed to prepare the registration statement. " . $conn->error;
            }
        }
    }
    $conn->close();
}

include 'includes/universal_header.php';
?>

<div class="container mt-5">
    <h3 class="mb-4">Registration Form</h3>

    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <form method="POST" action="register.php">
        <h5 class="mb-3">Personal Information</h5>

        <!-- Last Name & First Name -->
        <div class="row mb-3">
            <div class="col-md-6">
                <label class="form-label">Last Name</label>
                <input type="text" name="last_name" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">First Name</label>
                <input type="text" name="first_name" class="form-control" required>
            </div>
        </div>

        <!-- Address -->
        <div class="mb-3">
            <label class="form-label">Detailed Address (Street, Brgy)</label>
            <input type="text" name="address" class="form-control" placeholder="e.g. 123 Rizal St, Brgy. Poblacion" required>
        </div>

        <!-- Email & Cellphone -->
        <div class="row mb-3">
            <div class="col-md-6">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Cellphone Number</label>
                <input type="text" name="phone" class="form-control" required>
            </div>
        </div>

        <h5 class="mt-4 mb-3">Location Information</h5>
        <!-- Dynamic Location Selects -->
        <div class="row mb-3">
            <div class="col-md-4">
                <label class="form-label">Region</label>
                <select id="region" class="form-select" required>
                    <option value="" selected disabled>Select region</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Province</label>
                <select id="province" class="form-select" disabled required>
                    <option value="" selected disabled>Select province</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">City / Municipality</label>
                <select id="city" class="form-select" disabled required>
                    <option value="" selected disabled>Select city/municipality</option>
                </select>
                <!-- This hidden input will hold the actual name for the database -->
                <input type="hidden" name="city_name" id="city_name" required>
            </div>
        </div>

        <h5 class="mt-4 mb-3">Account Information</h5>

        <!-- Username & Role -->
        <div class="row mb-3">
            <div class="col-md-6">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Register as</label>
                <select name="role" class="form-select" required>
                    <option value="" selected disabled>Select user type</option>
                    <option value="BUYER">Buyer (General User)</option>
                    <option value="FARMER">Farmer</option>
                </select>
            </div>
        </div>

        <!-- Password & Confirm -->
        <div class="row mb-4">
            <div class="col-md-6">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Confirm Password</label>
                <input type="password" name="confirm_password" class="form-control" required>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Register</button>
    </form>
</div>

<script src="bootstrap/js/location.js"></script>
<?php include 'includes/universal_footer.php'; ?>
