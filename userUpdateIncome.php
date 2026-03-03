<?php
session_start();

// Database connection
include 'db.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "User not logged in.";
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve and sanitize input
    $amount = isset($_POST['amount']) ? trim($_POST['amount']) : null;

    // Validate input
    if (!is_numeric($amount) || $amount <= 0) {
        echo "Invalid income amount.";
        exit;
    }

    // Update the income if it exists
    $updateQuery = "UPDATE income SET income = ? WHERE user_id = ?";
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param("di", $amount, $user_id);

    if ($updateStmt->execute()) {
        if ($updateStmt->affected_rows > 0) {
            
            echo "<script>alert('income updated successfully.'); 
                window.location.href='user_dashboard.php';</script>";
        } else {
            echo "<script>alert('Income not found for the user.'); 
                window.location.href='user_dashboard.php';</script>";
        }
    } else {
        echo "Error updating income: " . $conn->error;
    }

    $updateStmt->close();
}

$conn->close();
?>
