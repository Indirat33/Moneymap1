<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION['user_id'];
    $category = $_POST['category'];
    $amount = $_POST['amount'];
    $period = $_POST['period'];

    // Input validation
    if (empty($category) || empty($amount) || empty($period)) {
        $_SESSION['error'] = "All fields are required.";
        header("Location: user_dashboard.php#budgets");
        exit();
    }

    if (!is_numeric($amount) || $amount <= 0) {
        $_SESSION['error'] = "Invalid budget amount.";
        header("Location: user_dashboard.php#budgets");
        exit();
    }

    // Insert into database
    $stmt = $conn->prepare("INSERT INTO budgets (user_id, category, amount, period) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isds", $user_id, $category, $amount, $period);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Budget added successfully.";
    } else {
        $_SESSION['error'] = "Something went wrong. Please try again.";
    }

    $stmt->close();
    header("Location: user_dashboard.php#budgets");
    exit();
} else {
    header("Location: user_dashboard.php");
    exit();
}
?>
