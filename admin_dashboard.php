<?php
session_start();
include "db.php";

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== "admin") {
    header("Location: login.php");
    exit();
}

// Summary queries
$total_users = $conn->query("SELECT COUNT(id) AS total_users FROM users")->fetch_assoc()['total_users'];
$total_expenses = $conn->query("SELECT COALESCE(SUM(Amount),0) AS total_expenses FROM expenses")->fetch_assoc()['total_expenses'];
$total_income = $conn->query("SELECT COALESCE(SUM(amount),0) AS total_income FROM income")->fetch_assoc()['total_income'];

// Recent users
$recent_users = $conn->query("SELECT name, email, created_at, status FROM users ORDER BY created_at DESC LIMIT 5");

// Recent expenses
$recent_expenses = $conn->query("SELECT e.Date, e.ItemName, e.Category, e.Amount, u.name FROM expenses e JOIN users u ON e.user_id=u.id ORDER BY e.Date DESC LIMIT 5");

// Users table
$users_table = $conn->query("SELECT id, name, email, role, status, profile_picture, created_at FROM users ORDER BY created_at DESC");

// Expenses table
$expenses_table = $conn->query("SELECT e.id, e.Date, e.ItemName, e.Category, e.Amount, u.name FROM expenses e JOIN users u ON e.user_id=u.id ORDER BY e.Date DESC");

// --- ALGORITHM 3: Global Data Aggregation ---
// Categories summary
$category_chart_query = "SELECT Category, SUM(Amount) as total FROM expenses GROUP BY Category";
$category_chart_result = $conn->query($category_chart_query);
$category_labels = [];
$category_data = [];
while ($row = $category_chart_result->fetch_assoc()) {
    $category_labels[] = "'" . $row['Category'] . "'";
    $category_data[] = $row['total'];
}
$category_labels_str = implode(',', $category_labels);
$category_data_str = implode(',', $category_data);

// Income vs Expenses Monthly (Current Year)
$income_monthly = array_fill(1, 12, 0);
$expense_monthly = array_fill(1, 12, 0);

$inc_q = $conn->query("SELECT MONTH(income_date) as m, SUM(amount) as total FROM income WHERE YEAR(income_date) = YEAR(CURDATE()) GROUP BY MONTH(income_date)");
while ($row = $inc_q->fetch_assoc()) {
    $income_monthly[$row['m']] = $row['total'];
}

$exp_q = $conn->query("SELECT MONTH(Date) as m, SUM(Amount) as total FROM expenses WHERE YEAR(Date) = YEAR(CURDATE()) GROUP BY MONTH(Date)");
while ($row = $exp_q->fetch_assoc()) {
    $expense_monthly[$row['m']] = $row['total'];
}

$income_monthly_str = implode(',', $income_monthly);
$expense_monthly_str = implode(',', $expense_monthly);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Fira+Sans:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Itim&display=swap"
        rel="stylesheet">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="admin_dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <header class="admin-header">
        <h1>Admin Control Panel</h1>
        <nav class="admin-navbar">
            <ul class="nav-items">
                <li><a href="#" class="nav-link active" data-section="dashboard">Dashboard</a></li>
                <li><a href="#" class="nav-link" data-section="users">Manage Users</a></li>
                <li><a href="#" class="nav-link" data-section="expenses">Manage Expenses</a></li>
                <li><a href="#" class="nav-link" data-section="reports">Reports</a></li>
                <li><a href="#" class="nav-link" data-section="settings">Settings</a></li>
                <li><a href="logout.php" class="nav-link" style="color: #ff4757; font-weight: bold;">Logout</a></li>
            </ul>
        </nav>
    </header>

    <!-- Dashboard Tab -->
    <section id="dashboard" class="content-section active">
        <h2>Dashboard</h2>
        <div class="widget-grid">
            <div class="widget"><h3>Total Users</h3><p><?php echo $total_users; ?></p></div>
            <div class="widget"><h3>Total Expenses</h3><p>NRP. <?php echo number_format($total_expenses,2); ?></p></div>
            <div class="widget"><h3>Total Income</h3><p>NRP. <?php echo number_format($total_income,2); ?></p></div>
        </div>
        <div class="widget">
            <h3>Recent Users</h3>
            <table class="data-table">
                <thead><tr><th>Name</th><th>Email</th><th>Joined</th><th>Status</th></tr></thead>
                <tbody>
                    <?php while ($u = $recent_users->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($u['name']); ?></td>
                        <td><?php echo htmlspecialchars($u['email']); ?></td>
                        <td><?php echo htmlspecialchars($u['created_at']); ?></td>
                        <td><?php echo htmlspecialchars($u['status']); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <div class="widget">
            <h3>Recent Expenses</h3>
            <table class="data-table">
                <thead><tr><th>Date</th><th>Item</th><th>Category</th><th>Amount</th><th>User</th></tr></thead>
                <tbody>
                    <?php while ($e = $recent_expenses->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($e['Date']); ?></td>
                        <td><?php echo htmlspecialchars($e['ItemName']); ?></td>
                        <td><?php echo htmlspecialchars($e['Category']); ?></td>
                        <td>NRP. <?php echo number_format($e['Amount'],2); ?></td>
                        <td><?php echo htmlspecialchars($e['name']); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <div class="widget">
            <h3>Charts</h3>
            <canvas id="expensesByCategoryChart"></canvas>
            <canvas id="incomeVsExpensesChart"></canvas>
        </div>
        <div class="widget">
            <h3>Quick Links</h3>
            <a href="#" class="nav-link" data-section="users">Manage Users</a>
            <a href="#" class="nav-link" data-section="expenses">Manage Expenses</a>
            <a href="#" class="nav-link" data-section="reports">Reports</a>
            <a href="#" class="nav-link" data-section="settings">Settings</a>
        </div>
    </section>

    <!-- Manage Users Tab -->
    <section id="users" class="content-section">
        <h2>Manage Users</h2>
        <form method="get" class="filter-form">
            <select name="role">
                <option value="">All Roles</option>
                <option value="admin">Admin</option>
                <option value="user">User</option>
            </select>
            <select name="status">
                <option value="">All Status</option>
                <option value="approved">Approved</option>
                <option value="pending">Pending</option>
                <option value="rejected">Rejected</option>
            </select>
            <button type="submit">Filter</button>
        </form>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Profile</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Created At</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($user = $users_table->fetch_assoc()): ?>
                <tr>
                    <td><img src="uploads/<?php echo htmlspecialchars($user['profile_picture']); ?>" width="40" height="40" style="border-radius:50%;"></td>
                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td><?php echo htmlspecialchars($user['role']); ?></td>
                    <td><?php echo htmlspecialchars($user['status']); ?></td>
                    <td><?php echo htmlspecialchars($user['created_at']); ?></td>
                    <td>
                        <a href="edit_user.php?id=<?php echo $user['id']; ?>">Edit</a>
                        <a href="approve_user.php?id=<?php echo $user['id']; ?>">Approve</a>
                        <a href="delete_user.php?id=<?php echo $user['id']; ?>">Delete</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <h3>Add User</h3>
        <form action="add_user.php" method="post" enctype="multipart/form-data">
            <input type="text" name="name" placeholder="Name" required>
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password" required>
            <select name="role">
                <option value="user">User</option>
                <option value="admin">Admin</option>
            </select>
            <input type="file" name="profile_picture">
            <button type="submit">Add User</button>
        </form>
    </section>

    <!-- Manage Expenses Tab -->
    <section id="expenses" class="content-section">
        <h2>Manage Expenses</h2>
        <form method="get" class="filter-form">
            <input type="text" name="category" placeholder="Category">
            <input type="date" name="date_from">
            <input type="date" name="date_to">
            <input type="text" name="user" placeholder="User Name">
            <button type="submit">Filter</button>
        </form>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th><th>Item Name</th><th>Category</th><th>Amount</th><th>User</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($exp = $expenses_table->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($exp['Date']); ?></td>
                    <td><?php echo htmlspecialchars($exp['ItemName']); ?></td>
                    <td><?php echo htmlspecialchars($exp['Category']); ?></td>
                    <td>NRP. <?php echo number_format($exp['Amount'],2); ?></td>
                    <td><?php echo htmlspecialchars($exp['name']); ?></td>
                    <td>
                        <a href="edit_expense.php?id=<?php echo $exp['id']; ?>">Edit</a>
                        <a href="delete_expense.php?id=<?php echo $exp['id']; ?>">Delete</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <h3>Add Expense</h3>
        <form action="add_expense.php" method="post">
            <input type="date" name="date" required>
            <input type="text" name="itemName" placeholder="Item Name" required>
            <input type="text" name="category" placeholder="Category" required>
            <input type="number" name="amount" min="1" placeholder="Amount" required>
            <select name="user_id">
                <?php
                $users = $conn->query("SELECT id, name FROM users");
                while ($u = $users->fetch_assoc()) {
                    echo "<option value='{$u['id']}'>" . htmlspecialchars($u['name']) . "</option>";
                }
                ?>
            </select>
            <button type="submit">Add Expense</button>
        </form>
    </section>

    <!-- Reports Tab -->
    <section id="reports" class="content-section">
        <h2>Reports</h2>
        <div class="widget">
            <h3>Expense Reports</h3>
            <canvas id="categorySummaryChart"></canvas>
            <canvas id="monthlyExpenseChart"></canvas>
            <canvas id="topCategoriesChart"></canvas>
        </div>
        <div class="widget">
            <h3>Income Reports</h3>
            <canvas id="monthlyIncomeChart"></canvas>
        </div>
        <div class="widget">
            <h3>Download/Export</h3>
            <a href="export_pdf.php">Export as PDF</a>
            <a href="export_excel.php">Export as Excel</a>
        </div>
    </section>

    <!-- Settings Tab -->
    <section id="settings" class="content-section">
        <h2>Settings</h2>
        <h3>Profile Settings</h3>
        <form action="update_admin_profile.php" method="post" enctype="multipart/form-data">
            <input type="text" name="name" placeholder="Admin Name">
            <input type="email" name="email" placeholder="Admin Email">
            <input type="password" name="password" placeholder="New Password">
            <input type="file" name="profile_picture">
            <button type="submit">Update Profile</button>
        </form>
        <h3>System Settings</h3>
        <form action="update_system_settings.php" method="post">
            <input type="text" name="default_categories" placeholder="Default Categories (comma separated)">
            <button type="submit">Update Categories</button>
        </form>
        <h3>Roles & Permissions</h3>
        <!-- Add role/permission management UI here -->
        <h3>Other</h3>
        <form action="update_notifications.php" method="post">
            <label><input type="checkbox" name="notifications" value="1"> Enable Notifications</label>
            <button type="submit">Save</button>
        </form>
        <form action="backup_restore.php" method="post">
            <button type="submit" name="backup">Backup Data</button>
            <button type="submit" name="restore">Restore Data</button>
        </form>
        <form action="logout.php" method="post">
            <button type="submit">Logout</button>
        </form>
    </section>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const navLinks = document.querySelectorAll('.nav-link');
        navLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                navLinks.forEach(l => l.classList.remove('active'));
                this.classList.add('active');
                document.querySelectorAll('.content-section').forEach(section => {
                    section.classList.remove('active');
                });
                const sectionId = this.getAttribute('data-section');
                document.getElementById(sectionId).classList.add('active');
            });
        });

        // Chart.js initialization for dashboard charts based on Algorithm 3 aggregations
        const categoryCtx = document.getElementById('expensesByCategoryChart');
        if (categoryCtx) {
            new Chart(categoryCtx, {
                type: 'doughnut',
                data: {
                    labels: [<?php echo $category_labels_str; ?>],
                    datasets: [{
                        data: [<?php echo $category_data_str; ?>],
                        backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40']
                    }]
                },
                options: { plugins: { title: { display: true, text: 'Total Expenses by Category' } } }
            });
        }

        const incExpCtx = document.getElementById('incomeVsExpensesChart');
        if (incExpCtx) {
            new Chart(incExpCtx, {
                type: 'bar',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                    datasets: [
                        { label: 'Income', data: [<?php echo $income_monthly_str; ?>], backgroundColor: '#4BC0C0' },
                        { label: 'Expenses', data: [<?php echo $expense_monthly_str; ?>], backgroundColor: '#FF6384' }
                    ]
                },
                options: { plugins: { title: { display: true, text: 'Income vs Expenses (' + new Date().getFullYear() + ')' } } }
            });
        }
    });
    </script>
    
    <!-- Footer -->
    <footer style="text-align: center; padding: 20px; color: #777; font-size: 14px; margin-top: auto; width: 100%; border-top: 1px solid #eee; background: white;">
        <p>&copy; <?php echo date("Y"); ?> MoneyMap Admin Panel. All rights reserved.</p>
    </footer>

</body>
</html>