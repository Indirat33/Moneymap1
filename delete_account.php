<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    echo "<script>alert('User not logged in.'); window.location.href='index.php';</script>";
    exit;
}

// Database connection
include 'db.php';

$user_id = $_SESSION['user_id'];

// Start a transaction
$conn->begin_transaction();

try {
    // Delete related data from dailyexpenses
    $query1 = "DELETE FROM expenses WHERE user_id = ?";
    $stmt1 = $conn->prepare($query1);
    $stmt1->bind_param("i", $user_id);
    $stmt1->execute();
    $stmt1->close();

    $query2 = "DELETE FROM income WHERE user_id = ?";
    $stmt2 = $conn->prepare($query2);
    $stmt2->bind_param("i", $user_id);
    $stmt2->execute();
    $stmt2->close();
    // Delete the user
    $query3 = "DELETE FROM users WHERE id = ?";
    $stmt3 = $conn->prepare($query3);
    $stmt3->bind_param("i", $user_id);
    $stmt3->execute();
    $stmt3->close();

    // Commit the transaction
    $conn->commit();

    // Destroy the session
    session_unset();
    session_destroy();
    echo "<script>alert('Account deleted successfully.'); window.location.href='index.php';</script>";
} catch (Exception $e) {
    // Rollback the transaction on error
    $conn->rollback();
    echo "<script>alert('Failed to delete account.'); window.location.href='index.php';</script>";
}

$conn->close();
?>
