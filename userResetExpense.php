<?php
session_start();
include "db.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    die("Unauthorized access.");
}

$user_id = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["reset"])) {
    // Delete all expenses for the logged-in user
    $stmt = $conn->prepare("DELETE FROM expenses WHERE user_id = ?");
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("i", $user_id);

    if ($stmt->execute()) {
        echo "Expenses reset successfully.";
    } else {
        echo "Error resetting expenses: " . $stmt->error;
    }

    $stmt->close();
}

$conn->close();
?>