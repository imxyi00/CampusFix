<?php
require "config.php";

if (isset($_SESSION["user_id"])) {
    header("Location: dashboard.php");
    exit;
}

ensure_password_resets_table($conn);

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    require_csrf_token();

    $email = trim($_POST["email"] ?? "");

    if ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        $stmt = $conn->prepare("SELECT id, name, email FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user) {
            $userId = (int)$user["id"];
            $rawToken = bin2hex(random_bytes(32));
            $tokenHash = hash("sha256", $rawToken);

            // Invalidate previous unused tokens for this user
            $inv = $conn->prepare("UPDATE password_resets SET used = 1 WHERE user_id = ? AND used = 0");
            $inv->bind_param("i", $userId);
            $inv->execute();
            $inv->close();

            // Store hashed token with 1-hour expiration
            $ins = $conn->prepare(
                "INSERT INTO password_resets (user_id, token_hash, expires_at)
                 VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))"
            );
            $ins->bind_param("is", $userId, $tokenHash);
            $ins->execute();
            $ins->close();

            $resetUrl = campusfix_base_url() . "/reset_password.php?token=" . urlencode($rawToken);
            $mailResult = send_password_reset_email($user["email"], $user["name"], $resetUrl);

            if (!empty($mailResult["sent"])) {
                $success = "A password reset link has been sent to your email inbox.";
            } else {
                $error = $mailResult["error"] ?? "Unable to send reset email. Please check Gmail settings in mail.php.";
            }
        } else {
            // Generic message to prevent email enumeration
            $success = "If that email is registered, a password reset link has been sent to your email inbox.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="assets/cflogo.png">
    <title>Forgot Password - CampusFix</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth-page">
    <div class="auth-card login-card">
        <div class="auth-logo-wrap">
            <img src="assets/cflogo.png" alt="T.I.P. Logo" id="cflogo">
        </div>
        <span class="eyebrow">T.I.P. QUEZON CITY</span>
        <h1>Forgot Password</h1>
        <p class="auth-subtitle">Enter your registered email address and we will send you a password reset link.</p>

        <?php if ($error !== ""): ?>
            <div class="alert error flash-notification" role="alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success !== ""): ?>
            <div class="alert success flash-notification" role="status"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST" novalidate data-confirm-title="Send a password reset link?" data-confirm-message="If the email belongs to an account, a password reset link will be sent." data-confirm-label="Send Reset Link">
            <?= csrf_input() ?>
            <label for="forgotEmail">Email Address</label>
            <input type="email" name="email" id="forgotEmail" value="<?= htmlspecialchars($_POST["email"] ?? "") ?>" placeholder="yourname@tip.edu.ph" required autocomplete="email">

            <button type="submit" class="authbtn">Send Reset Link</button>
        </form>

        <p class="switch-link"><a href="index.php">Back to login</a></p>
    </div>
    <script src="assets/script.js"></script>
</body>
</html>
