<?php

session_start();
include "db.php";

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION["role"] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Fetch all users
$result = $conn->query("SELECT id, name, email, role, status FROM users ORDER BY created_at DESC");

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Fira+Sans:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Itim&display=swap"
        rel="stylesheet">
    <title>Manage Users</title>
    <link rel="stylesheet" href="css/admin_dashboard.css">
</head>

<body>

    <div class="dashboard-container">
        <div class="main-content">
            <h1>Manage Users</h1>
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($user = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['name']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo htmlspecialchars($user['role']); ?></td>
                            <td>
                                <?php 
                                // Check the status of the user
                                if ($user['status'] == 'approved') {
                                    echo '<span class="approved">Approved</span>';
                                } elseif ($user['status'] == 'pending') {
                                    echo '<span class="pending">Pending</span>';
                                } else {
                                    echo '<span class="unknown-status">Unknown</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <?php if ($user['status'] == 'pending'): ?>
                                    <a href="approve_user.php?approve=<?php echo $user['id']; ?>" class="approve-btn">Approve</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>

</html>
