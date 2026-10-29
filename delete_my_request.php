<?php
require "config.php";
require_roles(["requester"]);

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: my_requests.php");
    exit;
}

require_csrf_token();

$requestId = filter_var($_POST["request_id"] ?? null, FILTER_VALIDATE_INT);
if (!$requestId || $requestId < 1) {
    header("Location: my_requests.php?error=" . urlencode("A valid request is required."));
    exit;
}

$userId = (int)$_SESSION["user_id"];
$stmt = $conn->prepare("SELECT photo, completion_photo FROM requests WHERE id = ? AND requester_id = ? AND status = 'Pending'");
$stmt->bind_param("ii", $requestId, $userId);
$stmt->execute();
$request = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$request) {
    header("Location: my_requests.php?error=" . urlencode("Only your pending requests can be deleted."));
    exit;
}

$stmt = $conn->prepare("DELETE FROM requests WHERE id = ? AND requester_id = ? AND status = 'Pending'");
$stmt->bind_param("ii", $requestId, $userId);
if (!$stmt->execute() || $stmt->affected_rows !== 1) {
    header("Location: my_requests.php?error=" . urlencode("Unable to delete the request. It may have been updated."));
    exit;
}

foreach ([$request["photo"], $request["completion_photo"]] as $filename) photo_delete($filename);

firebase_delete_request((int)$requestId);
header("Location: my_requests.php?success=" . urlencode("Request #{$requestId} was deleted."));
exit;
?>
