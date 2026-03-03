<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <title>EMS | Home</title>
    <link rel="stylesheet" href="css/style.css">
</head>

<body>

    <!-- navbar of homepage -->
    <div class="navbar-container">
        <div class="logo">MoneyMap</div>

        <div class="navbar">
           <a href="login.php"><button class="login-btn">LOGIN</button></a>
           <a href="register.php"><button class="login-btn register-nav">REGISTER</button></a>
        </div>
    </div>

    <!--hero section of homepage-->
    <div class="hero-container">

        <div class="hero-text-content">
            <div class="slogan">
                Upgrade the Way You Track <br> Expenses <span>Effortlessly</span> and <span>Effectively</span>
            </div>
            
            <div class="hero-description">
                Our expense management system simplifies the process, allowing you to seamlessly add, update, and manage your expenses. Stop relying on spreadsheets and start organizing your financial life today.
            </div>
            
            <div class="sign-body">
                <a href="register.php"><button class="signup-btn">Get Started <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                        width="24" height="24" fill="rgba(255,255,255,1)">
                        <path
                            d="M16.1716 10.9999L10.8076 5.63589L12.2218 4.22168L20 11.9999L12.2218 19.778L10.8076 18.3638L16.1716 12.9999H4V10.9999H16.1716Z">
                        </path>
                    </svg></button></a>
            </div>
        </div>

        <div class="image">
            <img src="images/8878499.jpg" alt="Expense Management Dashboard Illustration">
        </div>
    </div>

    <!-- Footer -->
    <footer style="text-align: center; padding: 30px 20px; color: #777; font-size: 14px; margin-top: auto; width: 100%;">
        <p>&copy; <?php echo date("Y"); ?> MoneyMap. All rights reserved.</p>
    </footer>

</body>
</html>
