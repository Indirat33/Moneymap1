<?php
session_start();
include "db.php";

// If already logged in, redirect to dashboard
if (isset($_SESSION["user_id"])) {
    header("Location: " . ($_SESSION["role"] === "admin" ? "admin_dashboard.php" : "user_dashboard.php"));
    exit();
}

$errors = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Validate inputs
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    } elseif (preg_match('/^[0-9]+@/', $email)) {
        $errors[] = "Email cannot start with only numbers (e.g., 123@gmail.com)";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT id, name, password, role, status FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->bind_result($id, $name, $hashed_password, $role, $status);
            $stmt->fetch();

            if ($status === "pending") {
                $errors[] = "Your account is pending approval.";
            } elseif (!password_verify($password, $hashed_password)) {
                $errors[] = "Incorrect password.";
            } else {
                $_SESSION["user_id"] = $id;
                $_SESSION["name"] = $name;
                $_SESSION["role"] = $role;
                
                header("Location: " . ($role === "admin" ? "admin_dashboard.php" : "user_dashboard.php"));
                exit();
            }
        } else {
            $errors[] = "Email does not exist.";
        }
        $stmt->close();
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/login.css">
    <title>Login</title>
</head>
<body>
    <div class="login-container">
        <h2>Login</h2>
        <?php if (!empty($errors)): ?>
            <div class="error-box">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo $error; ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST" id="loginForm">
            <label for="email">Email</label>
            <input type="email" name="email" id="email" pattern="^(?!\d+@)[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$" title="Email cannot start with just numbers (e.g., 123@gmail.com)" required>

            <label for="password">Password</label>
            <input type="password" name="password" id="password" required>

            <button type="submit">Login</button>
        </form>
        <p>Don't have an account? <a href="register.php">Register here</a></p>
    </div>

    <!-- Footer -->
    <footer style="position: absolute; bottom: 20px; text-align: center; width: 100%; color: #777; font-size: 13px;">
        <p>&copy; <?php echo date("Y"); ?> MoneyMap. All rights reserved.</p>
    </footer>

    <script>
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value;
            
            // Client side numeric email check
            if (/^\d+@/.test(email)) {
                e.preventDefault();
                alert('Email cannot start with only numbers (e.g., 123@gmail.com)');
            }
        });
    </script>
</body>
</html>
