<?php
session_start();

// Redirect if user is not logged in
if (!isset($_SESSION['user_id'])) {
    die("User not logged in.");
}

$user_id = $_SESSION['user_id'];

// Database connection
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Validate and sanitize inputs
    if (
        !isset($_POST['amount']) || !is_numeric($_POST['amount']) ||
        !isset($_POST['source']) || empty(trim($_POST['source'])) ||
        !isset($_POST['income_date']) || empty($_POST['income_date'])
    ) {
        die("Invalid form data.");
    }

    $amount = floatval($_POST['amount']);
    $source = trim($_POST['source']);
    $income_date = $_POST['income_date']; // YYYY-MM-DD from <input type="date">

    // Prepare insert statement
    $insertIncome = $conn->prepare(
        "INSERT INTO income (user_id, amount, source, income_date)
         VALUES (?, ?, ?, ?)"
    );

    if (!$insertIncome) {
        die("Prepare failed: " . $conn->error);
    }

    $insertIncome->bind_param("idss", $user_id, $amount, $source, $income_date);

    if ($insertIncome->execute()) {
        echo "<script>
                alert('Income added successfully.');
                window.location.href = 'user_dashboard.php';
              </script>";
        exit;
    } else {
        echo "Error: " . $insertIncome->error;
    }

    $insertIncome->close();
}

$conn->close();
?>