<?php
require "config.php";
require_roles(["system_admin"]);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    require_csrf_token();

    $action = $_POST["action"] ?? "create";

    if ($action === "revoke") {
        $revokeId = (int)($_POST["user_id"] ?? 0);

        if ($revokeId <= 0) {
            $error = "Invalid user selected.";
        } elseif ($revokeId === (int)$_SESSION["user_id"]) {
            $error = "You cannot revoke access for your own active account.";
        } else {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $revokeId);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                header("Location: manage_users.php?success=" . urlencode("User access revoked successfully."));
                exit;
            }
            $error = "Unable to revoke user access.";
        }
    } else {
        $name = trim($_POST["name"] ?? "");
        $email = trim($_POST["email"] ?? "");
        $password = $_POST["password"] ?? "";
        $role = $_POST["role"] ?? "";
        $allowedRoles = ["admin_staff", "maintenance", "itso", "system_admin"];
        $passwordRuleError = validate_password_rules($password);

        if ($name === "" || !filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($role, $allowedRoles, true)) {
            $error = "Enter valid account details.";
        } elseif ($passwordRuleError !== null) {
            $error = $passwordRuleError;
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
                    header("Location: manage_users.php?success=" . urlencode("Staff account created successfully."));
                    exit;
                }
                $error = "Unable to create account.";
            }
        }
    }
}

$users = $conn->query("SELECT id, name, email, role, created_at FROM users ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="assets/cflogo.png">
    <title>Manage Users - CampusFix</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php include "partials/nav.php"; ?>

<main class="container">
    <div class="page-heading">
        <div>
            <span class="eyebrow">SYSTEM ADMINISTRATION</span>
            <h1>Manage Users</h1>
            <p class="muted">Only System Administrators can create privileged accounts or revoke user access.</p>
        </div>
    </div>

    <?php if (!empty($error)): ?><div class="alert error flash-notification" role="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if (isset($_GET["success"])): ?><div class="alert success flash-notification" role="status"><?= htmlspecialchars($_GET["success"]) ?></div><?php endif; ?>

    <section class="panel form-panel">
        <h2>Create Staff Account</h2>
        <form method="POST" data-confirm-title="Create staff account?" data-confirm-message="Confirm that the account details and assigned role are correct." data-confirm-label="Create Account">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="create">
            <label>Full Name</label>
            <input type="text" name="name" maxlength="100" required>
            <label>Email</label>
            <input type="email" name="email" maxlength="150" required>
            <label>Temporary Password</label>
            <div class="password-wrap">
                <input type="password" name="password" id="staffPassword" data-password-validate="signup" minlength="8" required>
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
            <div class="password-feedback" id="staffPasswordFeedback" aria-live="polite">
                Must be at least 8 characters with uppercase, lowercase, number, and special character.
            </div>
            <label>Role</label>
            <select name="role" required>
                <option value="admin_staff">Administrative Staff</option>
                <option value="maintenance">Maintenance Personnel</option>
                <option value="itso">ITSO Personnel</option>
                <option value="system_admin">System Administrator</option>
            </select>
            <button type="submit" class="authbtn">Create Staff Account</button>
        </form>
    </section>

    <section class="panel">
        <h2>Registered Accounts</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Created</th><th>Action</th></tr></thead>
                <tbody>
                <?php while ($user = $users->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($user["name"]) ?></td>
                        <td><?= htmlspecialchars($user["email"]) ?></td>
                        <td><?= htmlspecialchars(role_label($user["role"])) ?></td>
                        <td><?= htmlspecialchars($user["created_at"]) ?></td>
                        <td>
                            <?php if ((int)$user["id"] === (int)$_SESSION["user_id"]): ?>
                                <span class="muted">Current Account</span>
                            <?php else: ?>
                                <form method="POST" class="inline-action-form" data-confirm-title="Revoke account access?" data-confirm-message="This account will be deleted and will no longer be able to sign in." data-confirm-label="Revoke Access">
                                    <?= csrf_input() ?>
                                    <input type="hidden" name="action" value="revoke">
                                    <input type="hidden" name="user_id" value="<?= (int)$user["id"] ?>">
                                    <button type="submit" class="btn danger-btn small-btn">Revoke Access</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
<script src="assets/script.js"></script>
</body>
</html>
