<?php
session_start();

$host = "localhost";
$user = "root";
$pass = "";
$db   = "campusfix";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

function require_login(): void {
    global $conn;

    if (!isset($_SESSION["user_id"])) {
        header("Location: index.php?error=" . urlencode("Please log in first."));
        exit;
    }

    $stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION["user_id"]);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        session_unset();
        session_destroy();

        header("Location: index.php?error=" . urlencode("Your account no longer exists. Please log in again."));
        exit;
    }

    $stmt->close();
}

function require_roles(array $roles): void {
    require_login();

    if (!in_array($_SESSION["role"] ?? "", $roles, true)) {
        header("Location: dashboard.php?error=" . urlencode("You are not authorized to access that page."));
        exit;
    }
}

function role_label(string $role): string {
    return match ($role) {
        "admin_staff" => "Administrative Staff",
        "maintenance" => "Maintenance Personnel",
        "itso" => "ITSO Personnel",
        "system_admin" => "System Administrator",
        default => "Requester"
    };
}
?>