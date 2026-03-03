<?php
session_start();
include "db.php";

$errors = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role']; // 'user' or 'admin'

    if (empty($name)) $errors[] = "Name is required";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    } elseif (preg_match('/^[0-9]+@/', $email)) {
        $errors[] = "Email cannot start with only numbers (e.g., 123@gmail.com)";
    }
    if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters";
    if ($password !== $confirm_password) $errors[] = "Passwords do not match";

    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $errors[] = "Email is already registered";
    }
    $stmt->close();

    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Check if there is already an admin
        $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin'");
        $stmt->execute();
        $stmt->bind_result($admin_count);
        $stmt->fetch();
        $stmt->close();

        // If no admin exists, approve this one, otherwise set status as pending
        if ($admin_count == 0) {
            $status = "approved"; // First admin gets approved
        } else {
            $status = "pending"; // Other admins are pending approval
        }

        $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $name, $email, $hashed_password, $role, $status);

        if ($stmt->execute()) {
            if ($role === "admin" && $status === "approved") {
                echo "<script>alert('First admin account created successfully! You can log in now.'); 
                window.location.href='login.php';</script>";
            } else {
                echo "<script>alert('User registered! Wait for admin approval.'); window.location.href='login.php';</script>";
            }
            exit();
        } else {
            $errors[] = "Registration failed, try again.";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
    <link rel="stylesheet" href="css/register.css">
</head>

<body>
    <div class="register-container">
        <h2>Register</h2>
        <?php if (!empty($errors)): ?>
            <div class="error-box">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo $error; ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST">
            <label for="name">Full Name</label>
            <input type="text" name="name" id="name" pattern="[A-Za-z ]+" title="Please enter only alphabets (letters)." required>
            <label for="email">Email</label>
            <input type="email" name="email" id="email" pattern="^(?!\d+@)[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$" title="Email cannot start with just numbers (e.g., 123@gmail.com)" required>

            <label for="password">Password</label>
            <input type="password" name="password" id="password" required>

            <label for="confirm_password">Confirm Password</label>
            <input type="password" name="confirm_password" id="confirm_password" required>

            <label for="role">Register as:</label>
            <select name="role" id="role">
                <option value="user">User</option>
                <option value="admin">Admin</option>
            </select>

            <button type="submit">Register</button>
        </form>
        <p>Already have an account? <a href="login.php">Login here</a></p>
    </div>

    <!-- Footer -->
    <footer style="position: absolute; bottom: 20px; text-align: center; width: 100%; color: #777; font-size: 13px;">
        <p>&copy; <?php echo date("Y"); ?> MoneyMap. All rights reserved.</p>
    </footer>

    <script>
        document.querySelector('form').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            // Client side numeric email check
            if (/^\d+@/.test(email)) {
                e.preventDefault();
                alert('Email cannot start with only numbers (e.g., 123@gmail.com)');
                return;
            }

            if (password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long.');
                return;
            }
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
            }
        });
    </script>
</body>
</html>
