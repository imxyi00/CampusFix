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
    <link rel="icon" type="image/png" href="assets/cflogo.png">
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
            <div class="alert error flash-notification" role="alert"><?= htmlspecialchars($_GET["error"]) ?></div>
        <?php endif; ?>

        <?php if (isset($_GET["success"])): ?>
            <div class="alert success flash-notification" role="status"><?= htmlspecialchars($_GET["success"]) ?></div>
        <?php endif; ?>

        <form action="login.php" method="POST" id="loginForm" novalidate>
            <?= csrf_input() ?>
            <label for="loginEmail">Email</label>
            <input type="email" name="email" id="loginEmail" required autocomplete="email">

            <label for="loginPassword">Password</label>
            <div class="password-wrap">
                <input type="password" name="password" id="loginPassword" data-password-validate="signin" required autocomplete="current-password">
                <button type="button" class="password-toggle" aria-label="Show password" aria-pressed="false">
                    <svg class="eye-open" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                    <svg class="eye-closed" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" hidden>
                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                        <line x1="1" y1="1" x2="23" y2="23"></line>
                    </svg>
                </button>
            </div>
            <div class="password-feedback" id="loginPasswordFeedback" aria-live="polite" hidden></div>

            <button type="submit" class="authbtn">Login</button>
        </form>

        <p class="switch-link"><a href="forgot_password.php">Forgot your password?</a></p>
        <p class="switch-link">No account? <a href="register.php">Register here</a></p>
        <p class="brand-note">A CampusFix prototype for T.I.P. Quezon City</p>
    </div>
    <script src="assets/script.js"></script>
</body>
</html>
