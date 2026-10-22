<?php
require "config.php";

if (isset($_SESSION["user_id"])) {
    header("Location: dashboard.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";

    // Public registration can only create Requester accounts.
    $role = "requester";
    $passwordRuleError = validate_password_rules($password);

    if ($name === "" || $email === "" || $password === "" || $confirmPassword === "") {
        $error = "Please complete all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif ($passwordRuleError !== null) {
        $error = $passwordRuleError;
    } elseif ($password !== $confirmPassword) {
        $error = "Passwords do not match.";
    } else {
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = "Email is already registered.";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $name, $email, $hashed, $role);

            if ($stmt->execute()) {
                header("Location: index.php?success=" . urlencode("Registration successful. You can now log in."));
                exit;
            } else {
                $error = "Registration failed.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - CampusFix</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth-page">
    <div class="auth-card">
        <div class="auth-logo-wrap">
            <img src="assets\cflogo.png" alt="T.I.P. Logo" id="cflogo">
        </div>
        <span class="eyebrow">T.I.P. QUEZON CITY</span>
        <h1>Create Requester Account</h1>
        <p class="auth-subtitle">Register to submit and track campus facility requests.</p>

        <?php if (!empty($error)): ?>
            <div class="alert error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" id="registerForm">
            <label for="regName">Full Name</label>
            <input type="text" name="name" id="regName" maxlength="100" value="<?= htmlspecialchars($_POST["name"] ?? "") ?>" required>

            <label for="regEmail">Email</label>
            <input type="email" name="email" id="regEmail" maxlength="150" value="<?= htmlspecialchars($_POST["email"] ?? "") ?>" required>

            <label for="regPassword">Password</label>
            <div class="password-wrap">
                <input type="password" name="password" id="regPassword" data-password-validate="signup" minlength="8" required autocomplete="new-password">
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
            <div class="password-feedback" id="regPasswordFeedback" aria-live="polite">
                Must be at least 8 characters with uppercase, lowercase, number, and special character.
            </div>

            <label for="regConfirmPassword">Confirm Password</label>
            <div class="password-wrap">
                <input type="password" name="confirm_password" id="regConfirmPassword" data-confirm-for="regPassword" minlength="8" required autocomplete="new-password">
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
            <div class="password-feedback" id="regConfirmFeedback" aria-live="polite" hidden></div>

            <div class="role-note">
                <strong>Account role:</strong> Requester<br>
                Staff and administrator accounts are created by an authorized System Administrator.
            </div>

            <button type="submit" class="authbtn">Create Account</button>
        </form>

        <p class="switch-link"><a href="index.php">Back to login</a></p>
    </div>
    <script src="assets/script.js"></script>
</body>
</html>
