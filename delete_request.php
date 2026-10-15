<?php
require "config.php";
require_roles(["system_admin"]);

if ($_SERVER["REQUEST_METHOD"] !== "POST") { header("Location: manage_requests.php"); exit; }
$requestId = (int)($_POST["request_id"] ?? 0);
$returnTo = $_POST["return_to"] ?? "manage_requests.php";
if (!preg_match('/^manage_requests\.php(?:\?.*)?$/', $returnTo)) $returnTo = "manage_requests.php";

$stmt = $conn->prepare("SELECT photo FROM requests WHERE id = ?");
$stmt->bind_param("i", $requestId); $stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
if (!$row) { header("Location: {$returnTo}?error=" . urlencode("Request was not found.")); exit; }

$stmt = $conn->prepare("DELETE FROM requests WHERE id = ?");
$stmt->bind_param("i", $requestId);
if ($stmt->execute()) {
    if (!empty($row["photo"])) { $path = __DIR__ . "/uploads/" . basename($row["photo"]); if (is_file($path)) @unlink($path); }
    firebase_delete_request($requestId);
    header("Location: {$returnTo}" . (str_contains($returnTo, "?") ? "&" : "?") . "success=" . urlencode("Request #{$requestId} was deleted.")); exit;
}
header("Location: {$returnTo}" . (str_contains($returnTo, "?") ? "&" : "?") . "error=" . urlencode("Unable to delete the request.")); exit;
