<?php
session_start();
include "db.php";

// Check if the logged-in user is an admin
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== 'admin') {
    echo "Unauthorized";
    exit();
}

if (isset($_GET["id"])) {
    $user_id = $_GET["id"];

    // Prepare the query to update the user's status to 'approved'
    $stmt = $conn->prepare("UPDATE users SET status = 'approved' WHERE id = ?");
    $stmt->bind_param("i", $user_id);

    if ($stmt->execute()) {
        // Success message
        echo "<script>alert('User approved successfully!'); window.location.href = 'admin_dashboard.php';</script>";
    } else {
        // Log the error
        echo "<script>alert('Error approving user: " . $conn->error . "'); window.location.href = 'admin_dashboard.php';</script>";
    }

    $stmt->close();
} else {
    echo "No user selected for approval.";
}
?>
