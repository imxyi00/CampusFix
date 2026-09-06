<?php
require "config.php";

if (isset($_SESSION["user_id"])) {
    header("Location: dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CampusFix | T.I.P. QC</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth-page">
    <div class="auth-card login-card">
        <div class="auth-logo-wrap">
            <img src="assets\cflogo.png" alt="T.I.P. Logo" id="cflogo">

        </div>
        <span class="eyebrow">T.I.P. QUEZON CITY</span>
        <h1>CampusFix</h1>
        <p class="auth-subtitle">Campus Facility Maintenance and Services Request Management System</p>

        <?php if (isset($_GET["error"])): ?>
            <div class="alert error"><?= htmlspecialchars($_GET["error"]) ?></div>
        <?php endif; ?>

        <?php if (isset($_GET["success"])): ?>
            <div class="alert success"><?= htmlspecialchars($_GET["success"]) ?></div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <label>Email</label>
            <input type="email" name="email" required autocomplete="email">

            <label>Password</label>
            <input type="password" name="password" required autocomplete="current-password">
            <button type="submit" class="authbtn">Login</button>
        </form>

        <p class="switch-link">No account? <a href="register.php">Register here</a></p>
        <p class="brand-note">A CampusFix prototype for T.I.P. Quezon City</p>
    </div>
</body>
</html>
