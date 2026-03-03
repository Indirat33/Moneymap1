<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "User not logged in.";
    exit;
}

// Database connection
include 'db.php';

// Check if expenseID is set
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['expenseID'])) {
    $id = intval($_POST['expenseID']);
    $user_id = $_SESSION['user_id'];

    // Prepare and execute the DELETE query
    $query = "DELETE FROM expenses WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($query);

    if (!$stmt) {
        die("SQL Error: " . $conn->error); // Log SQL error if prepare fails
    }

    $stmt->bind_param("ii", $id, $user_id);
    if ($stmt->execute()) {
        echo "<script>alert('Expense deleted successfully.'); 
            window.location.href='user_dashboard.php';</script>";
    } else {
        echo "<script>alert('Error deleting expense.'); 
            window.location.href='user_dashboard.php';</script>";
    }
    $stmt->close();
} else {
    echo "<script>alert('Invalid request.'); 
        window.location.href='user_dashboard.php';</script>";
}

$conn->close();
?>
