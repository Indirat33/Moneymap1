<?php
session_start();
include 'db.php';

// Initialize variables
$name = $email = $profile_picture = "";
$error = "";

// Fetch user details from the database
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT name, email, profile_picture FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $name = $user['name'];
        $email = $user['email'];
        $profile_picture = $user['profile_picture'];
    } else {
        $error = "User not found.";
    }

    $stmt->close();
} else {
    $error = "User not logged in.";
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
    <head>  
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Fira+Sans:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Itim&display=swap"
        rel="stylesheet">
    <title>EMS | Profile</title>
    <link rel="stylesheet" href="css/admin_profile.css">
</head>
<body>

<div class="profile_conatiner">

<!-- Profile Details -->
<div class="profile" onclick="loadadminprofile()">
            <img src="uploads/<?php echo htmlspecialchars($profile_picture); ?>" alt="profile">
        </div>

<div class="profile-details">
    <?php if (isset($error)) { ?>
        <p class="error"><?php echo htmlspecialchars($error); ?></p>
    <?php } else { ?>
        <div class="profile-info">
            <img src="uploads/<?php echo htmlspecialchars($profile_picture); ?>" alt="profile" class="profile-pic">
            <h2><?php echo htmlspecialchars($name); ?></h2>
            <p><?php echo htmlspecialchars($email); ?></p>
        </div>
    <?php } ?>
</div>

<div>
<h2>Username: <?php echo htmlspecialchars($name); ?></h2>
<p>Email: <?php echo htmlspecialchars($email); ?></p>
</div>

<!-- Upload Profile Picture Form -->
<div class="upload-profile-pic">
    <h3>Upload Profile Picture</h3>
    <form action="upload_admin_profile.php" method="post" enctype="multipart/form-data">
        <input type="file" name="profile_picture" accept="image/*" required>
        <button type="submit">Upload</button>
    </form>
</div>

<!-- Change Password Form -->
<div class="change-password-form">
    <h3>Change Password</h3>
    <form action="change_password.php" method="post">
        <div class="form-group">
            <label for="current_password">Current Password:</label>
            <input type="password" name="current_password" id="current_password" required>
        </div>

        <div class="form-group">
            <label for="new_password">New Password:</label>
            <input type="password" name="new_password" id="new_password" required>
        </div>

        <div class="form-group">
            <label for="confirm_password">Confirm New Password:</label>
            <input type="password" name="confirm_password" id="confirm_password" required>
        </div>

        <button type="submit">Change Password</button>
    </form>
</div>
</div>
<script>
    // loadadmin profile function
function loadadminprofile() {
    var contentArea = document.getElementById('content-area');

    fetch('admin_profile.php')
        .then(function (response) {
            return response.text();
        })
        .then(function (data) {
            contentArea.innerHTML = data;
        })
        .catch(function () {
            contentArea.innerHTML = "Error loading the profile content.";
        });
}
</script>
<body>
</html>