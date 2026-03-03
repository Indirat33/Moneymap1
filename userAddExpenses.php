<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    die("User not logged In.");
}

$user_id = $_SESSION['user_id'];

// Database connection
include 'db.php';


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $date = htmlspecialchars($_POST['date']);
    $currentDate = date('Y-m-d');

    $itemName = htmlspecialchars($_POST['item_name']);
    $category = htmlspecialchars($_POST['category']);
    $amount = htmlspecialchars($_POST['amount']);

    if (empty($date) || empty($itemName) || empty($category) || empty($amount)) {
        echo "<script>alert('Please fill in all fields.'); 
            window.location.href='user_dashboard.php';</script>";
        exit;
    }

    if ($date > $currentDate) {
        echo "<script>alert('future date not allowed.'); 
            window.location.href='user_dashboard.php';</script>";
        exit;
    }

    // Check if user_id exists in users table
    $checkuser_id = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $checkuser_id->bind_param("i", $user_id);
    $checkuser_id->execute();
    $result = $checkuser_id->get_result();

    if ($result->num_rows === 0) {
        die("Invalid user_id. Please log in again.");
    }

    $checkuser_id->close();

    // Insert the data into dailyexpenses table
    $sql = "INSERT INTO expenses (user_id, date, itemName, category, amount) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("isssi", $user_id, $date, $itemName, $category, $amount);

        if ($stmt->execute()) {
            echo "<script>alert('Expense added successfully.'); 
                window.location.href='user_dashboard.php';</script>";
        } else {
            echo "Error: " . $stmt->error;
        }

        $stmt->close();
    } else {
        echo "Error: " . $conn->error;
    }
}

$conn->close();
?>
