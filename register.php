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

    // Public registration can only create Requester accounts.
    $role = "requester";

    if ($name === "" || $email === "" || $password === "") {
        $error = "Please complete all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
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

        <form method="POST">
            <label>Full Name</label>
            <input type="text" name="name" maxlength="100" required>

            <label>Email</label>
            <input type="email" name="email" maxlength="150" required>

            <label>Password</label>
            <input type="password" name="password" minlength="8" required>
            <small class="field-help">At least 8 characters.</small>

            <div class="role-note">
                <strong>Account role:</strong> Requester<br>
                Staff and administrator accounts are created by an authorized System Administrator.
            </div>

            <button type="submit" class="authbtn">Create Account</button>
        </form>

        <p class="switch-link"><a href="index.php">Back to login</a></p>
    </div>
</body>
</html>
