<?php
require "config.php";
require_roles(["system_admin"]);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $role = $_POST["role"] ?? "";
    $allowedRoles = ["admin_staff", "maintenance", "itso", "system_admin"];

    if ($name === "" || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8 || !in_array($role, $allowedRoles, true)) {
        $error = "Enter valid account details. Password must be at least 8 characters.";
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

$users = $conn->query("SELECT id, name, email, role, created_at FROM users ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
            <p class="muted">Only System Administrators can create privileged accounts.</p>
        </div>
    </div>

    <?php if (!empty($error)): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if (isset($_GET["success"])): ?><div class="alert success"><?= htmlspecialchars($_GET["success"]) ?></div><?php endif; ?>

    <section class="panel form-panel">
        <h2>Create Staff Account</h2>
        <form method="POST">
            <label>Full Name</label>
            <input type="text" name="name" maxlength="100" required>
            <label>Email</label>
            <input type="email" name="email" maxlength="150" required>
            <label>Temporary Password</label>
            <input type="password" name="password" minlength="8" required>
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
                <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Created</th></tr></thead>
                <tbody>
                <?php while ($user = $users->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($user["name"]) ?></td>
                        <td><?= htmlspecialchars($user["email"]) ?></td>
                        <td><?= htmlspecialchars(role_label($user["role"])) ?></td>
                        <td><?= htmlspecialchars($user["created_at"]) ?></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</body>
</html>
