<?php
require "config.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit;
}

require_csrf_token();

$email = trim($_POST["email"] ?? "");
$password = $_POST["password"] ?? "";

$stmt = $conn->prepare("SELECT id, name, password, role FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($user = $result->fetch_assoc()) {
    if (password_verify($password, $user["password"])) {
        session_regenerate_id(true);
        $_SESSION["user_id"] = $user["id"];
        $_SESSION["name"] = $user["name"];
        $_SESSION["role"] = $user["role"];
        unset($_SESSION["csrf_token"]);

        header("Location: dashboard.php");
        exit;
    }
}

$errorMsg = "Invalid email or password. Please check your credentials and try again.";

header("Location: index.php?error=" . urlencode($errorMsg));
exit;
?>
