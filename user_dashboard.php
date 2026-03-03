<?php
session_start();
include 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user profile information
$stmt = $conn->prepare("SELECT profile_picture, name, email FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$profile_picture = $user['profile_picture'] ?? 'default_profile.png';

// Fetch dashboard statistics
$total_expenses = 0;
$total_income = 0;

// Get total expenses
$stmt = $conn->prepare("SELECT SUM(Amount) as total FROM expenses WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$expense_data = $result->fetch_assoc();
$total_expenses = $expense_data['total'] ?? 0;

// Get total income
$stmt = $conn->prepare("SELECT SUM(amount) AS total FROM income WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$income_data = $result->fetch_assoc();
$total_income = $income_data['total'] ?? 0;

$balance = $total_income - $total_expenses;

// Fetch recent expenses (last 5)
$stmt = $conn->prepare("SELECT Date, ItemName, Category, Amount FROM expenses WHERE user_id = ? ORDER BY Date DESC LIMIT 5");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_expenses = $stmt->get_result();

// Fetch all expenses for current user
$stmt = $conn->prepare("SELECT Date, ItemName, Category, Amount FROM expenses WHERE user_id = ? ORDER BY Date DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$all_expenses = $stmt->get_result();

// Fetch all income for current user
$stmt = $conn->prepare("SELECT amount, date_added, source FROM income WHERE user_id = ? ORDER BY date_added DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$all_income = $stmt->get_result();

// Fetch expenses by category for charts
$stmt = $conn->prepare("SELECT Category, SUM(Amount) as total FROM expenses WHERE user_id = ? GROUP BY Category");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$category_expenses = $stmt->get_result();

// Fetch monthly expenses for the current year
$stmt = $conn->prepare("
    SELECT MONTH(Date) as month, SUM(Amount) as total 
    FROM expenses 
    WHERE user_id = ? AND YEAR(Date) = YEAR(CURDATE()) 
    GROUP BY MONTH(Date) 
    ORDER BY MONTH(Date)
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$monthly_expenses = $stmt->get_result();

// --- ALGORITHM 1: Dynamic Budget Tracking ---
// Fetch user's budgets and calculate progress based on current month's expenses
$stmt = $conn->prepare("SELECT id, category, amount, period FROM budgets WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$budgets_result = $stmt->get_result();
$user_budgets = [];

while ($budget = $budgets_result->fetch_assoc()) {
    $category = $budget['category'];
    $budget_amount = $budget['amount'];
    
    // Calculate total spent in this category for the current month
    $spent_stmt = $conn->prepare("SELECT SUM(Amount) as spent FROM expenses WHERE user_id = ? AND Category = ? AND MONTH(Date) = MONTH(CURDATE()) AND YEAR(Date) = YEAR(CURDATE())");
    $spent_stmt->bind_param("is", $user_id, $category);
    $spent_stmt->execute();
    $spent_result = $spent_stmt->get_result();
    $spent_data = $spent_result->fetch_assoc();
    $spent_amount = $spent_data['spent'] ?? 0;
    
    // Algorithm math
    $percentage = ($budget_amount > 0) ? round(($spent_amount / $budget_amount) * 100) : 0;
    
    // Determine status
    if ($percentage >= 100) {
        $status = 'exceeded';
        $status_text = 'Over budget by NPR ' . number_format($spent_amount - $budget_amount, 2);
        $progress_class = 'exceeded';
    } elseif ($percentage >= 80) {
        $status = 'warning';
        $status_text = $percentage . '% of budget used';
        $progress_class = ''; // default orange-ish
    } else {
        $status = 'good';
        $status_text = 'On track';
        $progress_class = 'good';
    }

    $user_budgets[] = [
        'category' => $category,
        'budget_amount' => $budget_amount,
        'spent_amount' => $spent_amount,
        'percentage' => min($percentage, 100), // Cap bar at 100%
        'display_percentage' => $percentage,
        'period' => $budget['period'],
        'status' => $status,
        'status_text' => $status_text,
        'progress_class' => $progress_class
    ];
}

// --- ALGORITHM 2: Expense Forecasting (Linear Regression) ---
// Predict next month's spending based on the last 3 months of data
$forecast_amount = 0;
// We fetch monthly totals for the last 3 months
$stmt = $conn->prepare("
    SELECT MONTH(Date) as m, YEAR(Date) as y, SUM(Amount) as total 
    FROM expenses 
    WHERE user_id = ? AND Date >= DATE_SUB(LAST_DAY(CURDATE() - INTERVAL 3 MONTH), INTERVAL 1 MONTH) + INTERVAL 1 DAY
    GROUP BY YEAR(Date), MONTH(Date) 
    ORDER BY YEAR(Date) ASC, MONTH(Date) ASC
    LIMIT 3
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$history_result = $stmt->get_result();

$x = []; // Time periods (1, 2, 3)
$y = []; // Spent amounts
$i = 1;
while ($row = $history_result->fetch_assoc()) {
    $x[] = $i;
    $y[] = $row['total'];
    $i++;
}

// Perform simple linear regression to project period 4 (next month)
if (count($x) >= 2) {
    $n = count($x);
    $sum_x = array_sum($x);
    $sum_y = array_sum($y);
    $sum_xy = 0;
    $sum_xx = 0;

    for ($j = 0; $j < $n; $j++) {
        $sum_xy += ($x[$j] * $y[$j]);
        $sum_xx += ($x[$j] * $x[$j]);
    }

    // Calculate slope (m) and intercept (b)
    $denominator = ($n * $sum_xx) - ($sum_x * $sum_x);
    if ($denominator != 0) {
        $slope = (($n * $sum_xy) - ($sum_x * $sum_y)) / $denominator;
        $intercept = ($sum_y - ($slope * $sum_x)) / $n;
        
        // Predict for next period ($n + 1)
        $next_period = $n + 1;
        $forecast_amount = ($slope * $next_period) + $intercept;
    } else {
        // Fallback: simple average if linear regression fails (e.g. constant X, unlikely here)
        $forecast_amount = $sum_y / $n;
    }
} elseif (count($x) == 1) {
    // Only 1 month of data, guess it'll be the same
    $forecast_amount = $y[0];
} else {
    // No data, prediction is 0
    $forecast_amount = 0;
}
$forecast_amount = max(0, $forecast_amount); // Ensure it doesn't go below 0
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EMS | Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fira+Sans:wght@400;500;600;700&family=Itim&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="user_dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <div class="navbar">
        <div class="nav-links">
            <button class="tab-link active" data-tab="dashboard">
                <svg width="24" height="24" fill="currentColor">
                    <path d="M12 3C12.5523 3 13 3.44771 13 4L13 10C13 10.5523 12.5523 11 12 11L4 11C3.44772 11 3 10.5523 3 10L3 4C3 3.44772 3.44772 3 4 3L12 3ZM20 3C20.5523 3 21 3.44771 21 4L21 10C21 10.5523 20.5523 11 20 11L16 11C15.4477 11 15 10.5523 15 10L15 4C15 3.44771 15.4477 3 16 3L20 3ZM20 13C20.5523 13 21 13.4477 21 14L21 20C21 20.5523 20.5523 21 20 21L12 21C11.4477 21 11 20.5523 11 20L11 14C11 13.4477 11.4477 13 12 13L20 13ZM3 14C3 13.4477 3.44772 13 4 13L8 13C8.55229 13 9 13.4477 9 14L9 20C9 20.5523 8.55229 21 8 21L4 21C3.44772 21 3 20.5523 3 20L3 14Z"></path>
                </svg>
                Dashboard
            </button>
            <button class="tab-link" data-tab="expenses">
                <svg width="24" height="24" fill="currentColor">
                    <path d="M17 6h5v2h-2v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V8H2V6h5V3a1 1 0 0 1 1-1h8a1 1 0 0 1 1 1v3zm-8 5v6h2v-6H9zm4 0v6h2v-6h-2zM9 4v2h6V4H9z" />
                </svg>
                Expenses
            </button>
            <button class="tab-link" data-tab="income">
                <svg width="24" height="24" fill="currentColor">
                    <path d="M12 2c5.523 0 10 4.477 10 10s-4.477 10-10 10S2 17.523 2 12 6.477 2 12 2zm0 2a8 8 0 1 0 0 16 8 8 0 0 0 0-16zm1 3v4h4v2h-4v4h-2v-4H7v-2h4V7h2z" />
                </svg>
                Income
            </button>
            <button class="tab-link" data-tab="reports">
                <svg width="24" height="24" fill="currentColor">
                    <path d="M21 8v12.993A1 1 0 0 1 20.007 22H3.993A.993.993 0 0 1 3 21.008V2.992C3 2.455 3.449 2 4.002 2h10.995L21 8zm-2 1h-5V4H5v16h14V9z" />
                </svg>
                Reports
            </button>
            <button class="tab-link" data-tab="charts">
                <svg width="24" height="24" fill="currentColor">
                    <path d="M5 3V19H21V21H3V3H5ZM19.9393 5.93934L22.0607 8.06066L16 14.1213L13 11.121L9.06066 15.0607L6.93934 12.9393L13 6.87868L16 9.879L19.9393 5.93934Z"></path>
                </svg>
                Charts
            </button>
            <button class="tab-link" data-tab="budgets">
                <svg width="24" height="24" fill="currentColor">
                    <path d="M3 3h18a1 1 0 0 1 1 1v16a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1zm1 2v14h16V5H4zm4.5 9h7a.5.5 0 1 0 0-1h-7a.5.5 0 0 0 0 1z" />
                </svg>
                Budgets
            </button>
            <button class="tab-link" data-tab="settings">
                <svg width="24" height="24" fill="currentColor">
                    <path d="M2.13127 13.6308C1.9492 12.5349 1.95521 11.434 2.13216 10.3695C3.23337 10.3963 4.22374 9.86798 4.60865 8.93871C4.99357 8.00944 4.66685 6.93557 3.86926 6.17581C4.49685 5.29798 5.27105 4.51528 6.17471 3.86911C6.9345 4.66716 8.0087 4.99416 8.93822 4.60914C9.86774 4.22412 10.3961 3.23332 10.369 2.13176C11.4649 1.94969 12.5658 1.9557 13.6303 2.13265C13.6036 3.23385 14.1319 4.22422 15.0612 4.60914C15.9904 4.99406 17.0643 4.66733 17.8241 3.86975C18.7019 4.49734 19.4846 5.27153 20.1308 6.1752C19.3327 6.93499 19.0057 8.00919 19.3907 8.93871C19.7757 9.86823 20.7665 10.3966 21.8681 10.3695C22.0502 11.4654 22.0442 12.5663 21.8672 13.6308C20.766 13.6041 19.7756 14.1324 19.3907 15.0616C19.0058 15.9909 19.3325 17.0648 20.1301 17.8245C19.5025 18.7024 18.7283 19.4851 17.8247 20.1312C17.0649 19.3332 15.9907 19.0062 15.0612 19.3912C14.1316 19.7762 13.6033 20.767 13.6303 21.8686C12.5344 22.0507 11.4335 22.0447 10.3691 21.8677C10.3958 20.7665 9.86749 19.7761 8.93822 19.3912C8.00895 19.0063 6.93508 19.333 6.17532 20.1306C5.29749 19.503 4.51479 18.7288 3.86862 17.8252C4.66667 17.0654 4.99367 15.9912 4.60865 15.0616C4.22363 14.1321 3.23284 13.6038 2.13127 13.6308ZM11.9997 15.0002C13.6565 15.0002 14.9997 13.657 14.9997 12.0002C14.9997 10.3433 13.6565 9.00018 11.9997 9.00018C10.3428 9.00018 8.99969 10.3433 8.99969 12.0002C8.99969 13.657 10.3428 15.0002 11.9997 15.0002Z"></path>
                </svg>
                Settings
            </button>
            <button class="tab-link" data-tab="profile">
                <svg width="24" height="24" fill="currentColor">
                    <circle cx="12" cy="8" r="4" />
                    <path d="M4 20c0-4 4-7 8-7s8 3 8 7" />
                </svg>
                Profile
            </button>
            <a href="logout.php" style="text-decoration: none;">
                <button class="tab-link logout-btn-nav" style="color: #ff4757;">
                    <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M16 17v-3H9v-4h7V7l5 5-5 5M14 2a2 2 0 0 1 2 2v2h-2V4H5v16h9v-2h2v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9z"></path>
                    </svg>
                    Logout
                </button>
            </a>
        </div>
        <div class="profile">
            <img src="uploads/<?php echo htmlspecialchars($profile_picture); ?>" alt="profile">
        </div>
    </div>

    <div class="main-content">
        <!-- Dashboard Tab -->
        <section id="dashboard" class="tab-section active">
            <div class="dashboard-header">
                <h2>Dashboard Overview</h2>
                <p class="dashboard-subtitle">Welcome back, <?php echo htmlspecialchars($user['name']); ?>!</p>
            </div>

            <div class="stats-grid">
                <div class="stat-card total-income">
                    <div class="stat-icon">
                        <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2c5.523 0 10 4.477 10 10s-4.477 10-10 10S2 17.523 2 12 6.477 2 12 2zm0 2a8 8 0 1 0 0 16 8 8 0 0 0 0-16zm1 3v4h4v2h-4v4h-2v-4H7v-2h4V7h2z" />
                        </svg>
                    </div>
                    <div class="stat-info">
                        <h3>Total Income</h3>
                        <p class="stat-value">NPR <?php echo number_format($total_income, 2); ?></p>
                        <span class="stat-trend positive">+12.5%</span>
                    </div>
                </div>

                <div class="stat-card total-expense">
                    <div class="stat-icon">
                        <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M17 6h5v2h-2v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V8H2V6h5V3a1 1 0 0 1 1-1h8a1 1 0 0 1 1 1v3zm-8 5v6h2v-6H9zm4 0v6h2v-6h-2zM9 4v2h6V4H9z" />
                        </svg>
                    </div>
                    <div class="stat-info">
                        <h3>Total Expenses</h3>
                        <p class="stat-value">NPR <?php echo number_format($total_expenses, 2); ?></p>
                        <span class="stat-trend negative">-8.2%</span>
                    </div>
                </div>

                <div class="stat-card balance <?php echo $balance >= 0 ? 'positive' : 'negative'; ?>">
                    <div class="stat-icon">
                        <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 22C6.477 22 2 17.523 2 12S6.477 2 12 2s10 4.477 10 10-4.477 10-10 10zm-1-11v6h2v-6h3l-4-4-4 4h3z" />
                        </svg>
                    </div>
                    <div class="stat-info">
                        <h3>Current Balance</h3>
                        <p class="stat-value">NPR <?php echo number_format($balance, 2); ?></p>
                        <span class="stat-trend <?php echo $balance >= 0 ? 'positive' : 'negative'; ?>">
                            <?php echo $balance >= 0 ? 'Surplus' : 'Deficit'; ?>
                        </span>
                    </div>
                </div>

                <div class="stat-card total-expense">
                    <div class="stat-icon" style="background: rgba(153, 102, 255, 0.2); color: #9966FF;">
                        <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M2 13H8V21H2V13ZM9 3H15V21H9V3ZM16 8H22V21H16V8Z" />
                        </svg>
                    </div>
                    <div class="stat-info">
                        <h3>Predicted Expense (Next Month)</h3>
                        <p class="stat-value">NPR <?php echo number_format($forecast_amount, 2); ?></p>
                        <span class="stat-trend neutral">Based on AI Linear Regression</span>
                    </div>
                </div>
            </div>

            <div class="dashboard-content">
                <div class="recent-transactions">
                    <div class="section-header">
                        <h3>Recent Transactions</h3>
                        <button class="view-all-btn">View All</button>
                    </div>
                    <div class="transaction-list">
                        <?php if ($recent_expenses->num_rows > 0): ?>
                            <?php while ($expense = $recent_expenses->fetch_assoc()): ?>
                                <div class="transaction-item">
                                    <div class="transaction-info">
                                        <div class="transaction-category"><?php echo htmlspecialchars($expense['Category']); ?></div>
                                        <div class="transaction-name"><?php echo htmlspecialchars($expense['ItemName']); ?></div>
                                        <div class="transaction-date"><?php echo date('M j, Y', strtotime($expense['Date'])); ?></div>
                                    </div>
                                    <div class="transaction-amount expense">-NPR <?php echo number_format($expense['Amount'], 2); ?></div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <p>No recent transactions found</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="quick-actions">
                    <h3>Quick Actions</h3>
                    <div class="action-buttons">
                        <button class="action-btn" data-tab="expenses">
                            <svg width="20" height="20" fill="currentColor">
                                <path d="M17 6h5v2h-2v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V8H2V6h5V3a1 1 0 0 1 1-1h8a1 1 0 0 1 1 1v3z" />
                            </svg>
                            Add Expense
                        </button>
                        <button class="action-btn" data-tab="income">
                            <svg width="20" height="20" fill="currentColor">
                                <path d="M12 2c5.523 0 10 4.477 10 10s-4.477 10-10 10S2 17.523 2 12 6.477 2 12 2zm0 2a8 8 0 1 0 0 16 8 8 0 0 0 0-16zm1 3v4h4v2h-4v4h-2v-4H7v-2h4V7h2z" />
                            </svg>
                            Add Income
                        </button>
                        <button class="action-btn" data-tab="reports">
                            <svg width="20" height="20" fill="currentColor">
                                <path d="M21 8v12.993A1 1 0 0 1 20.007 22H3.993A.993.993 0 0 1 3 21.008V2.992C3 2.455 3.449 2 4.002 2h10.995L21 8z" />
                            </svg>
                            Generate Report
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <!-- Expenses Tab -->
        <section id="expenses" class="tab-section">
            <div class="section-header">
                <h2>Expense Management</h2>
                <button class="primary-btn" onclick="showAddExpenseModal()">+ Add New Expense</button>
            </div>

            <div class="expenses-content">
                <div class="expense-form-card">
                    <h3>Add New Expense</h3>
                    <form class="expense-form" id="expenseForm" action="userAddExpenses.php" method="POST">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="expense-item">Item Name</label>
                                <input type="text" id="expense-item" name="item_name" required>
                            </div>
                            <div class="form-group">
                                <label for="expense-amount">Amount (NPR)</label>
                                <input type="number" id="expense-amount" name="amount" step="0.01" min="0" required>
                            </div>
                            <div class="form-group">
                                <label for="expense-category">Category</label>
                                <select id="expense-category" name="category" required>
                                    <option value="">Select Category</option>
                                    <option value="Food Drinks">Food & Drinks</option>
                                    <option value="Transportation">Transportation</option>
                                    <option value="Housing">Housing</option>
                                    <option value="Entertainment">Entertainment</option>
                                    <option value="Personal Care">Personal Care</option>
                                    <option value="Miscellaneous">Miscellaneous</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="expense-date">Date</label>
                                <input type="date" id="expense-date" name="date" required>
                            </div>
                        </div>
                        <button type="submit" class="submit-btn">Add Expense</button>
                    </form>
                </div>

                <div class="expenses-table-card">
                    <h3>All Expenses</h3>
                    <div class="table-container">
                        <table class="expenses-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Item</th>
                                    <th>Category</th>
                                    <th>Amount</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($all_expenses->num_rows > 0): ?>
                                    <?php while ($expense = $all_expenses->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo date('M j, Y', strtotime($expense['Date'])); ?></td>
                                            <td><?php echo htmlspecialchars($expense['ItemName']); ?></td>
                                            <td>
                                                <span class="category-badge <?php echo strtolower(str_replace(' ', '-', $expense['Category'])); ?>">
                                                    <?php echo htmlspecialchars($expense['Category']); ?>
                                                </span>
                                            </td>
                                            <td class="amount">NPR <?php echo number_format($expense['Amount'], 2); ?></td>
                                            <td class="actions">
                                                <button class="edit-btn">Edit</button>
                                                <form action="userDeleteExpense.php" method="POST" style="display:inline;">
                                                    <button class="delete-btn">Delete</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="empty-state">No expenses found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <!-- Income Tab -->
        <section id="income" class="tab-section">
            <div class="section-header">
                <h2>Income Management</h2>
                <button class="primary-btn">+ Add New Income</button>
            </div>

            <div class="income-content">
                <div class="income-form-card">
                    <h3>Add New Income</h3>
                    <form class="income-form" action="userAddIncome.php" method="POST">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="income-amount">Amount (NPR)</label>
                                <input type="number" id="income-amount" name="amount" step="0.01" min="0" required>
                            </div>
                            <div class="form-group">
                                <label for="income-source">Source</label>
                                <select id="income-source" name="source" required>
                                    <option value="">Select Source</option>
                                    <option value="salary">Salary</option>
                                    <option value="freelance">Freelance</option>
                                    <option value="investment">Investment</option>
                                    <option value="business">Business</option>
                                    <option value="gift">Gift</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="income-date">Date</label>
                                <input type="date" id="income-date" name="income_date" required>
                            </div>
                        </div>
                        <button type="submit" class="submit-btn">Add Income</button>
                    </form>
                </div>

                <div class="income-table-card">
                    <h3>Income History</h3>
                    <div class="table-container">
                        <table class="income-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Source</th>
                                    <th>Amount</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($all_income->num_rows > 0): ?>
                                    <?php while ($income = $all_income->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo date('M j, Y', strtotime($income['date_added'])); ?></td>
                                            <!-- add source of income -->
                                            <td><?php echo htmlspecialchars($income['source']); ?></td>
                                            <td class="amount income">+NPR <?php echo number_format($income['amount'], 2); ?></td>
                                            <td class="actions">
                                                <button class="edit-btn">Edit</button>
                                                <button class="delete-btn">Delete</button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="empty-state">No income records found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <!-- Charts Tab -->
        <section id="charts" class="tab-section">
            <div class="section-header">
                <h2>Financial Analytics</h2>
            </div>

            <div class="charts-grid">
                <div class="chart-card">
                    <h3>Expenses by Category</h3>
                    <canvas id="categoryChart"></canvas>
                </div>
                <div class="chart-card">
                    <h3>Monthly Spending Trend</h3>
                    <canvas id="monthlyChart"></canvas>
                </div>
                <div class="chart-card full-width">
                    <h3>Income vs Expenses</h3>
                    <canvas id="incomeExpenseChart"></canvas>
                </div>
            </div>
        </section>

        <!-- Reports Tab -->
        <section id="reports" class="tab-section">
            <div class="section-header">
                <h2>Financial Reports</h2>
            </div>

            <div class="reports-content">
                <div class="report-generator">
                    <h3>Generate Custom Report</h3>
                    <form class="report-form">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="report-type">Report Type</label>
                                <select id="report-type" required>
                                    <option value="expenses">Expenses Summary</option>
                                    <option value="income">Income Summary</option>
                                    <option value="category">Category Analysis</option>
                                    <option value="monthly">Monthly Overview</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="report-period">Time Period</label>
                                <select id="report-period" required>
                                    <option value="this-month">This Month</option>
                                    <option value="last-month">Last Month</option>
                                    <option value="this-quarter">This Quarter</option>
                                    <option value="this-year">This Year</option>
                                    <option value="custom">Custom Range</option>
                                </select>
                            </div>
                            <div class="form-group custom-range" style="display: none;">
                                <label for="start-date">Start Date</label>
                                <input type="date" id="start-date">
                            </div>
                            <div class="form-group custom-range" style="display: none;">
                                <label for="end-date">End Date</label>
                                <input type="date" id="end-date">
                            </div>
                        </div>
                        <button type="submit" class="submit-btn">Generate Report</button>
                    </form>
                </div>

                <div class="report-summary">
                    <h3>Quick Summary</h3>
                    <div class="summary-grid">
                        <div class="summary-item">
                            <h4>This Month</h4>
                            <p>Expenses: NPR <?php echo number_format($total_expenses, 2); ?></p>
                            <p>Income: NPR <?php echo number_format($total_income, 2); ?></p>
                        </div>
                        <div class="summary-item">
                            <h4>Top Category</h4>
                            <p>Most spending in Food & Drinks</p>
                        </div>
                        <div class="summary-item">
                            <h4>Savings Rate</h4>
                            <p><?php echo $total_income > 0 ? round(($balance / $total_income) * 100, 1) : 0; ?>%</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Budgets Tab -->
        <section id="budgets" class="tab-section">
            <div class="section-header">
                <h2>Budget Management</h2>
                <button class="primary-btn" onclick="document.getElementById('budget-form-container').scrollIntoView();">+ Set New Budget</button>
            </div>

            <div class="budgets-content">
                <div class="budget-form-card" id="budget-form-container">
                    <h3>Create Budget</h3>
                    <form class="budget-form" action="add_budget.php" method="POST">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="budget-category">Category</label>
                                <select id="budget-category" name="category" required>
                                    <option value="">Select Category</option>
                                    <option value="Food & Drinks">Food & Drinks</option>
                                    <option value="Transportation">Transportation</option>
                                    <option value="Housing">Housing</option>
                                    <option value="Entertainment">Entertainment</option>
                                    <option value="Personal Care">Personal Care</option>
                                    <option value="Miscellaneous">Miscellaneous</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="budget-amount">Amount (NPR)</label>
                                <input type="number" id="budget-amount" name="amount" step="0.01" min="1" required>
                            </div>
                            <div class="form-group">
                                <label for="budget-period">Period</label>
                                <select id="budget-period" name="period" required>
                                    <option value="monthly">Monthly</option>
                                    <option value="weekly">Weekly</option>
                                    <option value="quarterly">Quarterly</option>
                                    <option value="yearly">Yearly</option>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="submit-btn" name="add_budget">Create Budget</button>
                    </form>
                    <?php if (isset($_SESSION['error'])): ?>
                        <p style="color: red; margin-top: 10px;"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></p>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['success'])): ?>
                        <p style="color: green; margin-top: 10px;"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></p>
                    <?php endif; ?>
                </div>

                <div class="budget-overview">
                    <h3>Current budgets</h3>
                    <div class="budget-cards">
                        <?php if (empty($user_budgets)): ?>
                            <div class="empty-state">
                                <p>No budgets set yet. Create one above!</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($user_budgets as $b): ?>
                            <div class="budget-card">
                                <div class="budget-header">
                                    <h4><?php echo htmlspecialchars($b['category']); ?></h4>
                                    <span class="budget-period"><?php echo ucfirst(htmlspecialchars($b['period'])); ?></span>
                                </div>
                                <div class="budget-progress">
                                    <div class="progress-info">
                                        <span>NPR <?php echo number_format($b['spent_amount'], 2); ?> / NPR <?php echo number_format($b['budget_amount'], 2); ?></span>
                                        <span class="percentage"><?php echo $b['display_percentage']; ?>%</span>
                                    </div>
                                    <div class="progress-bar">
                                        <div class="progress-fill <?php echo $b['progress_class']; ?>" style="width: <?php echo $b['percentage']; ?>%"></div>
                                    </div>
                                    <div class="budget-status <?php echo $b['progress_class']; ?>">
                                        <?php if ($b['status'] == 'exceeded'): ?>
                                            <svg width="16" height="16" fill="currentColor">
                                                <path d="M12 2C13.1 2 14 2.9 14 4V20C14 21.1 13.1 22 12 22H4C2.9 22 2 21.1 2 20V4C2 2.9 2.9 2 4 2H12ZM12 4H4V20H12V4ZM11 15H5V17H11V15ZM9 7H7V13H9V7Z" />
                                            </svg>
                                        <?php elseif ($b['status'] == 'warning'): ?>
                                            <svg width="16" height="16" fill="currentColor">
                                                <path d="M12 2C13.1 2 14 2.9 14 4V20C14 21.1 13.1 22 12 22H4C2.9 22 2 21.1 2 20V4C2 2.9 2.9 2 4 2H12ZM12 4H4V20H12V4ZM7 19H9V17H7V19ZM7 16H9V8H7V16Z" />
                                            </svg>
                                        <?php else: ?>
                                            <svg width="16" height="16" fill="currentColor">
                                                <path d="M12 2C13.1 2 14 2.9 14 4V20C14 21.1 13.1 22 12 22H4C2.9 22 2 21.1 2 20V4C2 2.9 2.9 2 4 2H12ZM12 4H4V20H12V4ZM11 16L6.5 12.5L7.91 11.09L11 14.18L16.59 8.59L18 10L11 16Z" />
                                            </svg>
                                        <?php endif; ?>
                                        <?php echo $b['status_text']; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

                        <div class="budget-card">
                            <div class="budget-header">
                                <h4>Transportation</h4>
                                <span class="budget-period">Monthly</span>
                            </div>
                            <div class="budget-progress">
                                <div class="progress-info">
                                    <span>NPR 8,500 / NPR 15,000</span>
                                    <span class="percentage">57%</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill good" style="width: 57%"></div>
                                </div>
                                <div class="budget-status good">
                                    <svg width="16" height="16" fill="currentColor">
                                        <path d="M12 2C13.1 2 14 2.9 14 4V20C14 21.1 13.1 22 12 22H4C2.9 22 2 21.1 2 20V4C2 2.9 2.9 2 4 2H12ZM12 4H4V20H12V4ZM11 16L6.5 12.5L7.91 11.09L11 14.18L16.59 8.59L18 10L11 16Z" />
                                    </svg>
                                    On track
                                </div>
                            </div>
                        </div>

                        <div class="budget-card">
                            <div class="budget-header">
                                <h4>Entertainment</h4>
                                <span class="budget-period">Monthly</span>
                            </div>
                            <div class="budget-progress">
                                <div class="progress-info">
                                    <span>NPR 12,000 / NPR 10,000</span>
                                    <span class="percentage">120%</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill exceeded" style="width: 100%"></div>
                                </div>
                                <div class="budget-status exceeded">
                                    <svg width="16" height="16" fill="currentColor">
                                        <path d="M12 2C13.1 2 14 2.9 14 4V20C14 21.1 13.1 22 12 22H4C2.9 22 2 21.1 2 20V4C2 2.9 2.9 2 4 2H12ZM12 4H4V20H12V4ZM11 15H5V17H11V15ZM9 7H7V13H9V7Z" />
                                    </svg>
                                    Over budget by NPR 2,000
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Settings Tab -->
        <section id="settings" class="tab-section">
            <div class="section-header">
                <h2>Settings</h2>
            </div>

            <div class="settings-content">
                <div class="settings-card">
                    <h3>Account Security</h3>
                    <form class="password-form">
                        <div class="form-group">
                            <label for="current-password">Current Password</label>
                            <input type="password" id="current-password" required>
                        </div>
                        <div class="form-group">
                            <label for="new-password">New Password</label>
                            <input type="password" id="new-password" required>
                        </div>
                        <div class="form-group">
                            <label for="confirm-password">Confirm New Password</label>
                            <input type="password" id="confirm-password" required>
                        </div>
                        <button type="submit" class="submit-btn">Update Password</button>
                    </form>
                </div>

                <div class="settings-card">
                    <h3>Expense Categories</h3>
                    <div class="category-management">
                        <form class="add-category-form">
                            <div class="form-group">
                                <label for="new-category">Add New Category</label>
                                <div class="input-group">
                                    <input type="text" id="new-category" placeholder="Category name" required>
                                    <button type="submit" class="add-btn">Add</button>
                                </div>
                            </div>
                        </form>

                        <div class="category-list">
                            <h4>Current Categories</h4>
                            <ul class="categories">
                                <li class="category-item">
                                    <span class="category-name">Food & Drinks</span>
                                    <div class="category-actions">
                                        <button class="edit-btn">Edit</button>
                                        <button class="delete-btn">Delete</button>
                                    </div>
                                </li>
                                <li class="category-item">
                                    <span class="category-name">Transportation</span>
                                    <div class="category-actions">
                                        <button class="edit-btn">Edit</button>
                                        <button class="delete-btn">Delete</button>
                                    </div>
                                </li>
                                <li class="category-item">
                                    <span class="category-name">Housing</span>
                                    <div class="category-actions">
                                        <button class="edit-btn">Edit</button>
                                        <button class="delete-btn">Delete</button>
                                    </div>
                                </li>
                                <li class="category-item">
                                    <span class="category-name">Entertainment</span>
                                    <div class="category-actions">
                                        <button class="edit-btn">Edit</button>
                                        <button class="delete-btn">Delete</button>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="settings-card">
                    <h3>Preferences</h3>
                    <div class="preferences">
                        <div class="preference-item">
                            <label for="currency">Default Currency</label>
                            <select id="currency">
                                <option value="NPR">NPR (Nepalese Rupee)</option>
                                <option value="USD">USD (US Dollar)</option>
                                <option value="EUR">EUR (Euro)</option>
                                <option value="INR">INR (Indian Rupee)</option>
                            </select>
                        </div>
                        <div class="preference-item">
                            <label for="date-format">Date Format</label>
                            <select id="date-format">
                                <option value="MM/DD/YYYY">MM/DD/YYYY</option>
                                <option value="DD/MM/YYYY">DD/MM/YYYY</option>
                                <option value="YYYY-MM-DD">YYYY-MM-DD</option>
                            </select>
                        </div>
                        <div class="preference-item">
                            <div class="toggle-group">
                                <label class="toggle-label">Email Notifications</label>
                                <div class="toggle-switch">
                                    <input type="checkbox" id="email-notifications" checked>
                                    <label for="email-notifications" class="toggle-slider"></label>
                                </div>
                            </div>
                        </div>
                        <div class="preference-item">
                            <div class="toggle-group">
                                <label class="toggle-label">Budget Alerts</label>
                                <div class="toggle-switch">
                                    <input type="checkbox" id="budget-alerts" checked>
                                    <label for="budget-alerts" class="toggle-slider"></label>
                                </div>
                            </div>
                        </div>
                        <button class="submit-btn">Save Preferences</button>
                    </div>
                </div>
            </div>
        </section>

        <!-- Profile Tab -->
        <section id="profile" class="tab-section">
            <div class="section-header">
                <h2>Profile Settings</h2>
            </div>

            <div class="profile-content">
                <div class="profile-card">
                    <div class="profile-header">
                        <div class="profile-avatar">
                            <img src="uploads/<?php echo htmlspecialchars($profile_picture); ?>" alt="Profile Picture">
                            <div class="avatar-overlay">
                                <svg width="24" height="24" fill="currentColor">
                                    <path d="M12 2C13.1 2 14 2.9 14 4C14 5.1 13.1 6 12 6C10.9 6 10 5.1 10 4C10 2.9 10.9 2 12 2ZM21 9V7L19 5L17 7V9C17 10.1 16.1 11 15 11V13H17L19 15L21 13H19V9ZM15 12H13V14H11V16H13V18H15V16H17V14H15V12Z" />
                                </svg>
                            </div>
                        </div>
                        <div class="profile-info">
                            <h3><?php echo htmlspecialchars($user['name']); ?></h3>
                            <p><?php echo htmlspecialchars($user['email']); ?></p>
                            <span class="member-since">Member since March 2025</span>
                        </div>
                    </div>

                    <form class="profile-form" action="userProfilePicture.php" method="POST" enctype="multipart/form-data">
                        <div class="form-section">
                            <h4>Personal Information</h4>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="full-name">Full Name</label>
                                    <input type="text" id="full-name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="email">Email Address</label>
                                    <input type="email" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="phone">Phone Number</label>
                                    <input type="tel" id="phone" placeholder="+977 98xxxxxxxx">
                                </div>
                                <div class="form-group">
                                    <label for="location">Location</label>
                                    <input type="text" id="location" placeholder="City, Country">
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h4>Profile Picture</h4>
                            <div class="file-upload">
                                <input type="file" id="profile-picture" accept="image/*" hidden>
                                <label for="profile-picture" class="file-upload-label">
                                    <svg width="20" height="20" fill="currentColor">
                                        <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z" />
                                    </svg>
                                    Choose New Picture
                                </label>
                                <span class="file-info">JPG, PNG or GIF (Max 2MB)</span>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="button" class="cancel-btn">Cancel</button>
                            <button type="submit" class="submit-btn">Update Profile</button>
                        </div>
                    </form>
                </div>

                <div class="profile-stats">
                    <h3>Account Statistics</h3>
                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="stat-icon">
                                <svg width="24" height="24" fill="currentColor">
                                    <path d="M17 6h5v2h-2v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V8H2V6h5V3a1 1 0 0 1 1-1h8a1 1 0 0 1 1 1v3z" />
                                </svg>
                            </div>
                            <div class="stat-details">
                                <h4><?php echo $all_expenses->num_rows; ?></h4>
                                <p>Total Transactions</p>
                            </div>
                        </div>

                        <div class="stat-item">
                            <div class="stat-icon">
                                <svg width="24" height="24" fill="currentColor">
                                    <path d="M21 8v12.993A1 1 0 0 1 20.007 22H3.993A.993.993 0 0 1 3 21.008V2.992C3 2.455 3.449 2 4.002 2h10.995L21 8z" />
                                </svg>
                            </div>
                            <div class="stat-details">
                                <h4>12</h4>
                                <p>Categories Used</p>
                            </div>
                        </div>

                        <div class="stat-item">
                            <div class="stat-icon">
                                <svg width="24" height="24" fill="currentColor">
                                    <path d="M12 2c5.523 0 10 4.477 10 10s-4.477 10-10 10S2 17.523 2 12 6.477 2 12 2zm0 2a8 8 0 1 0 0 16 8 8 0 0 0 0-16zm1 3v4h4v2h-4v4h-2v-4H7v-2h4V7h2z" />
                                </svg>
                            </div>
                            <div class="stat-details">
                                <h4>3</h4>
                                <p>Active Budgets</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <script>
        // Tab navigation logic
        document.querySelectorAll('.tab-link').forEach(function(tab) {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.tab-link').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                document.querySelectorAll('.tab-section').forEach(section => section.classList.remove('active'));
                document.getElementById(this.getAttribute('data-tab')).classList.add('active');
            });
        });

        // Quick action buttons
        document.querySelectorAll('.action-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const targetTab = this.getAttribute('data-tab');
                if (targetTab) {
                    document.querySelectorAll('.tab-link').forEach(t => t.classList.remove('active'));
                    document.querySelector(`[data-tab="${targetTab}"]`).classList.add('active');
                    document.querySelectorAll('.tab-section').forEach(section => section.classList.remove('active'));
                    document.getElementById(targetTab).classList.add('active');
                }
            });
        });

        // Report period change handler
        document.getElementById('report-period').addEventListener('change', function() {
            const customRanges = document.querySelectorAll('.custom-range');
            if (this.value === 'custom') {
                customRanges.forEach(range => range.style.display = 'block');
            } else {
                customRanges.forEach(range => range.style.display = 'none');
            }
        });

        // Set current date as default for expense and income forms
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('expense-date').value = today;
            document.getElementById('income-date').value = today;
        });

        // Initialize Charts
        function initializeCharts() {
            // Category Chart
            const categoryCtx = document.getElementById('categoryChart')?.getContext('2d');
            if (categoryCtx) {
                new Chart(categoryCtx, {
                    type: 'doughnut',
                    data: {
                        labels: [
                            <?php
                            $category_expenses->data_seek(0);
                            $labels = [];
                            $data = [];
                            while ($row = $category_expenses->fetch_assoc()) {
                                $labels[] = "'" . $row['Category'] . "'";
                                $data[] = $row['total'];
                            }
                            echo implode(', ', $labels);
                            ?>
                        ],
                        datasets: [{
                            data: [<?php echo implode(', ', $data); ?>],
                            backgroundColor: [
                                '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40'
                            ],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'bottom',
                            }
                        }
                    }
                });
            }

            // Monthly Chart
            const monthlyCtx = document.getElementById('monthlyChart')?.getContext('2d');
            if (monthlyCtx) {
                new Chart(monthlyCtx, {
                    type: 'line',
                    data: {
                        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                        datasets: [{
                            label: 'Monthly Expenses',
                            data: [
                                <?php
                                $monthly_data = array_fill(0, 12, 0);
                                $monthly_expenses->data_seek(0);
                                while ($row = $monthly_expenses->fetch_assoc()) {
                                    $monthly_data[$row['month'] - 1] = $row['total'];
                                }
                                echo implode(', ', $monthly_data);
                                ?>
                            ],
                            borderColor: '#36A2EB',
                            backgroundColor: 'rgba(54, 162, 235, 0.1)',
                            tension: 0.4,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }

            // Income vs Expense Chart
            const incomeExpenseCtx = document.getElementById('incomeExpenseChart')?.getContext('2d');
            if (incomeExpenseCtx) {
                new Chart(incomeExpenseCtx, {
                    type: 'bar',
                    data: {
                        labels: ['Income', 'Expenses', 'Balance'],
                        datasets: [{
                            label: 'Amount (NPR)',
                            data: [
                                <?php echo $total_income; ?>,
                                <?php echo $total_expenses; ?>,
                                <?php echo $balance; ?>
                            ],
                            backgroundColor: ['#4BC0C0', '#FF6384', '#36A2EB'],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }
        }

        // Initialize charts when DOM is loaded
        document.addEventListener('DOMContentLoaded', initializeCharts);

        // --- Client Side Form Validation ---
        function validateAmountForm(event) {
            const form = event.target;
            const amountInput = form.querySelector('input[name="amount"]');
            
            if (amountInput) {
                const amount = parseFloat(amountInput.value);
                if (isNaN(amount) || amount <= 0) {
                    event.preventDefault();
                    alert("Please enter a valid amount greater than 0.");
                    amountInput.focus();
                }
            }
        }

        // Attach validation to forms
        const expenseForm = document.getElementById('expenseForm');
        if (expenseForm) expenseForm.addEventListener('submit', validateAmountForm);

        const incomeForm = document.querySelector('.income-form');
        if (incomeForm) incomeForm.addEventListener('submit', validateAmountForm);

        const budgetForm = document.querySelector('.budget-form');
        if (budgetForm) budgetForm.addEventListener('submit', validateAmountForm);

    </script>

    <!-- Footer -->
    <footer style="text-align: center; padding: 20px; color: #777; font-size: 14px; margin-top: auto; width: 100%; border-top: 1px solid #eee; background: white;">
        <p>&copy; <?php echo date("Y"); ?> MoneyMap. All rights reserved.</p>
    </footer>

</body>

</html>