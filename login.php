<?php
session_start();
include 'includes/db.php';

$error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error_message = "Please enter both email and password.";
    } else {
        // Prepare a select statement to prevent SQL injection
        $stmt = $conn->prepare("SELECT id, password_hash, role FROM users WHERE email = ? AND is_active = 1 LIMIT 1");

        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows == 1) {
                $stmt->bind_result($user_id, $password_hash, $role);
                $stmt->fetch();

                // Verify the password
                if (password_verify($password, $password_hash)) {
                    // Password is correct, start a new session
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['role'] = $role;

                    // Redirect user based on role
                    if ($role == 'FARMER') {
                        header("Location: farmer/index.php");
                    } elseif ($role == 'BUYER') {
                        header("Location: buyer/index.php");
                    } elseif ($role == 'DA') {
                        header("Location: da/index.php");
                    } else {
                        header("Location: index.php");
                    }
                    exit();
                } else {
                    $error_message = "Invalid password.";
                }
            } else {
                $error_message = "No account found with that email address.";
            }
            $stmt->close();
        } else {
            $error_message = "Database error. Please try again later.";
        }
    }
    $conn->close();
}

include 'header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-4">
            <div class="card">
                <div class="card-header text-center">
                    <h2>Login</h2>
                </div>
                <div class="card-body">
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger"><?php echo $error_message; ?></div>
                    <?php endif; ?>
                    <form action="login.php" method="POST">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email address</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">Login</button>
                        </div>
                        <div class="mt-3 text-center">
                            <a href="#">Forgot Password?</a>
                        </div>
                        <div class="mt-2 text-center">
                            <a href="register.php">Don't have an account? Register here.</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
