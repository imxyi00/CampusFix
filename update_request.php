<?php
require "config.php";
require_login();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: dashboard.php");
    exit;
}

$userId = (int)$_SESSION["user_id"];
$role = $_SESSION["role"];
$requestId = (int)($_POST["request_id"] ?? 0);
$status = $_POST["status"] ?? "Pending";
$assignedTo = $_POST["assigned_to"] ?? "";
$returnTo = $_POST["return_to"] ?? "manage_requests.php";
if (!preg_match('/^manage_requests\.php(?:\?.*)?$/', $returnTo)) $returnTo = "manage_requests.php";

$allowedStatuses = ["Pending", "Assigned", "In Progress", "Completed"];
if (!in_array($status, $allowedStatuses, true)) {
    header("Location: dashboard.php?error=" . urlencode("Invalid request status."));
    exit;
}

if (in_array($role, ["admin_staff", "system_admin"], true)) {
    // Only authorized managers can assign or reassign requests.
    if ($assignedTo === "") {
        $stmt = $conn->prepare("UPDATE requests SET assigned_to = NULL, status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $requestId);
    } else {
        $assignedId = (int)$assignedTo;
        $check = $conn->prepare("SELECT id FROM users WHERE id = ? AND role IN ('maintenance', 'itso')");
        $check->bind_param("i", $assignedId);
        $check->execute();
        if (!$check->get_result()->fetch_assoc()) {
            header("Location: manage_requests.php?error=" . urlencode("Invalid personnel assignment."));
            exit;
        }

        if ($status === "Pending") $status = "Assigned";
        $stmt = $conn->prepare("UPDATE requests SET assigned_to = ?, status = ? WHERE id = ?");
        $stmt->bind_param("isi", $assignedId, $status, $requestId);
    }

    $stmt->execute();
    header("Location: " . $returnTo . (str_contains($returnTo, "?") ? "&" : "?") . "success=" . urlencode("Request updated successfully."));
    exit;
}

if (in_array($role, ["maintenance", "itso"], true)) {
    // Personnel can only update the status of requests assigned to themselves.
    $stmt = $conn->prepare("UPDATE requests SET status = ? WHERE id = ? AND assigned_to = ?");
    $stmt->bind_param("sii", $status, $requestId, $userId);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        header("Location: assigned_requests.php?error=" . urlencode("You can only update requests assigned to your account."));
        exit;
    }

    header("Location: assigned_requests.php?success=" . urlencode("Request status updated successfully."));
    exit;
}

header("Location: dashboard.php?error=" . urlencode("You are not authorized to update requests."));
exit;
?>
