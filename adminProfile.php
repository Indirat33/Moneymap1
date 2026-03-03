<?php
session_start();
include 'db.php';

if (isset($_SESSION['user_id']) && isset($_FILES['profile_picture'])) {
    $user_id = $_SESSION['user_id'];
    $target_dir = "uploads/";

    // Create the uploads directory if it doesn't exist
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true); // Create directory with read/write permissions
    }

    // Generate a unique file name to avoid conflicts
    $file_name = uniqid() . '_' . basename($_FILES['profile_picture']['name']);
    $target_file = $target_dir . $file_name;
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

    // Check if the file is an image
    $check = getimagesize($_FILES['profile_picture']['tmp_name']);
    if ($check === false) {
        die("File is not an image.");
    }

    // Save the file
    if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_file)) {
        // Update the database
        $stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
        $stmt->bind_param("si", $file_name, $user_id);
        $stmt->execute();
        $stmt->close();

        // Update session with new profile picture
        $_SESSION['profile_picture'] = $file_name;

        header('Location: admin_dashboard.php');
    } else {
        die("Error uploading file. Please check directory permissions.");
    }
} else {
    die("Invalid request.");
}
?>