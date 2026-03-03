<?php
session_start();

// Database connection
include 'db.php';

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    die("Unauthorized access.");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION['user_id'];
    $current_password = htmlspecialchars($_POST['current_password']);
    $new_password = htmlspecialchars($_POST['new_password']);
    $confirm_password = htmlspecialchars($_POST['confirm_password']);

    if ($new_password !== $confirm_password) {
        echo "<p style='color:red;'>New passwords do not match.</p>";
        exit;
    }

    // Fetch the current password hash from the database
    $sql = "SELECT password FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->store_result();
        $stmt->bind_result($hashed_password);

        if ($stmt->fetch()) {
            // Verify current password
            if (password_verify($current_password, $hashed_password)) {
                // Update to new password
                $new_hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
                $update_sql = "UPDATE users SET password = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);

                if ($update_stmt) {
                    $update_stmt->bind_param("si", $new_hashed_password, $user_id);
                    if ($update_stmt->execute()) {
                        echo "<script>alert('Password changed successfully.');</script>";
                        echo "<script>window.location.href = 'login.php';</script>";
                        exit;
                    } else {
                        echo "<p style='color:red;'>Error updating password.</p>";
                    }
                    $update_stmt->close();
                }
            } else {
                echo "<p style='color:red;'>Incorrect current password.</p>";
            }
        } else {
            echo "<p style='color:red;'>User not found.</p>";
        }

        $stmt->close();
    } else {
        echo "Error: " . $conn->error;
    }
}

$conn->close();
?>
