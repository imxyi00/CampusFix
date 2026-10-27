<?php
require "config.php";

if (isset($_SESSION["user_id"])) {
    header("Location: dashboard.php");
    exit;
}

ensure_password_resets_table($conn);

$rawToken = trim($_GET["token"] ?? $_POST["token"] ?? "");
$error = "";
$validReset = null;

if ($rawToken !== "" && preg_match('/^[a-f0-9]{64}$/i', $rawToken)) {
    $tokenHash = hash("sha256", $rawToken);
    $stmt = $conn->prepare(
        "SELECT pr.id, pr.user_id, u.email, u.name
         FROM password_resets pr
         JOIN users u ON pr.user_id = u.id
         WHERE pr.token_hash = ? AND pr.used = 0 AND pr.expires_at > NOW()
         LIMIT 1"
    );
    $stmt->bind_param("s", $tokenHash);
    $stmt->execute();
    $validReset = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$validReset) {
    $error = "This password reset link is invalid or has expired. Please request a new one.";
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && $validReset) {
    require_csrf_token();

    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";
    $passwordRuleError = validate_password_rules($password);

    if ($password === "" || $confirmPassword === "") {
        $error = "Please enter and confirm your new password.";
    } elseif ($passwordRuleError !== null) {
        $error = $passwordRuleError;
    } elseif ($password !== $confirmPassword) {
        $error = "Passwords do not match.";
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $userId = (int)$validReset["user_id"];
        $resetId = (int)$validReset["id"];

        $conn->begin_transaction();
        try {
            $upd = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $upd->bind_param("si", $hashed, $userId);
            if (!$upd->execute()) {
                throw new RuntimeException("Unable to update password.");
            }
            $upd->close();

            $mark = $conn->prepare("UPDATE password_resets SET used = 1 WHERE id = ?");
            $mark->bind_param("i", $resetId);
            if (!$mark->execute()) {
                throw new RuntimeException("Unable to mark reset token used.");
            }
            $mark->close();

            $conn->commit();
            header("Location: index.php?success=" . urlencode("Password reset successful. You can now log in."));
            exit;
        } catch (Throwable $e) {
            $conn->rollback();
            error_log("CampusFix password reset error: " . $e->getMessage());
            $error = "Unable to reset password. Please try again.";
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
    <title>Reset Password - CampusFix</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth-page">
    <div class="auth-card login-card">
        <div class="auth-logo-wrap">
            <img src="assets/cflogo.png" alt="T.I.P. Logo" id="cflogo">
        </div>
        <span class="eyebrow">T.I.P. QUEZON CITY</span>
        <h1>Set New Password</h1>
        <p class="auth-subtitle">
            <?= $validReset ? "Resetting password for " . htmlspecialchars($validReset["email"]) : "Verify your reset link to continue." ?>
        </p>

        <?php if ($error !== ""): ?>
            <div class="alert error flash-notification" role="alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($validReset): ?>
            <form method="POST" id="resetForm" data-confirm-title="Save your new password?" data-confirm-message="Confirm that you want to change the password for this account." data-confirm-label="Save Password">
                <?= csrf_input() ?>
                <input type="hidden" name="token" value="<?= htmlspecialchars($rawToken) ?>">

                <label for="resetPassword">New Password</label>
                <div class="password-wrap">
                    <input type="password" name="password" id="resetPassword" data-password-validate="signup" minlength="8" required autocomplete="new-password">
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
                <div class="password-feedback" id="resetPasswordFeedback" aria-live="polite">
                    Must be at least 8 characters with uppercase, lowercase, number, and special character.
                </div>

                <label for="resetConfirmPassword">Confirm New Password</label>
                <div class="password-wrap">
                    <input type="password" name="confirm_password" id="resetConfirmPassword" data-confirm-for="resetPassword" minlength="8" required autocomplete="new-password">
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
                <div class="password-feedback" id="resetConfirmFeedback" aria-live="polite" hidden></div>

                <button type="submit" class="authbtn">Save New Password</button>
            </form>
        <?php else: ?>
            <p class="switch-link"><a href="forgot_password.php">Request a new reset link</a></p>
        <?php endif; ?>

        <p class="switch-link"><a href="index.php">Back to login</a></p>
    </div>
    <script src="assets/script.js"></script>
</body>
</html>
