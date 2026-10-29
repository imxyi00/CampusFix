<?php
require "config.php";
require_login();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: dashboard.php");
    exit;
}

require_csrf_token();

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

$prevStmt = $conn->prepare("SELECT status, assigned_to, completion_photo FROM requests WHERE id = ? LIMIT 1");
$prevStmt->bind_param("i", $requestId);
$prevStmt->execute();
$prevRow = $prevStmt->get_result()->fetch_assoc();
$prevStmt->close();
$oldStatus = (string)($prevRow["status"] ?? "Pending");
$oldAssignedTo = isset($prevRow["assigned_to"]) && $prevRow["assigned_to"] !== null ? (int)$prevRow["assigned_to"] : null;
$oldCompletionPhoto = $prevRow["completion_photo"] ?? null;

if (!$prevRow) {
    header("Location: dashboard.php?error=" . urlencode("Request was not found."));
    exit;
}

if (in_array($role, ["admin_staff", "system_admin"], true)) {
    // Only authorized managers can assign or reassign requests.
    $assignedId = null;
    if ($assignedTo === "") {
        // Requests may remain unassigned.
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
    }

    $conn->begin_transaction();
    try {
        if ($assignedId === null) {
            $stmt = $conn->prepare("UPDATE requests SET assigned_to = NULL, status = ? WHERE id = ?");
            $stmt->bind_param("si", $status, $requestId);
        } else {
            $stmt = $conn->prepare("UPDATE requests SET assigned_to = ?, status = ? WHERE id = ?");
            $stmt->bind_param("isi", $assignedId, $status, $requestId);
        }

        if (!$stmt->execute() || !record_request_progress($conn, $requestId, $status)) {
            throw new RuntimeException("Unable to update request progress.");
        }
        $conn->commit();
        firebase_sync_request_from_db($conn, $requestId);
        notify_request_updated($conn, $requestId, $oldStatus, $oldAssignedTo, $status, $assignedId);
    } catch (mysqli_sql_exception | RuntimeException $exception) {
        $conn->rollback();
        error_log("CampusFix request update failed: " . $exception->getMessage());
        header("Location: manage_requests.php?error=" . urlencode("Unable to update request."));
        exit;
    }
    header("Location: " . $returnTo . (str_contains($returnTo, "?") ? "&" : "?") . "success=" . urlencode("Request updated successfully."));
    exit;
}

if (in_array($role, ["maintenance", "itso"], true)) {
    // Personnel can only update the status of requests assigned to themselves.
    $newCompletionPhoto = null;
    $upload = $_FILES["completion_photo"] ?? null;
    if ($upload && $upload["error"] !== UPLOAD_ERR_NO_FILE) {
        $allowedMimeTypes = ["image/jpeg" => "jpg", "image/png" => "png", "image/webp" => "webp"];
        if ($upload["error"] !== UPLOAD_ERR_OK || $upload["size"] > 5 * 1024 * 1024) {
            header("Location: assigned_requests.php?error=" . urlencode("Completion photo must be a valid image no larger than 5 MB."));
            exit;
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload["tmp_name"]);
        if (!isset($allowedMimeTypes[$mime]) || @getimagesize($upload["tmp_name"]) === false) {
            header("Location: assigned_requests.php?error=" . urlencode("Only valid JPG, PNG, and WEBP completion photos are allowed."));
            exit;
        }

        $newCompletionPhoto = bin2hex(random_bytes(16)) . "." . $allowedMimeTypes[$mime];
        if (!photo_store($upload["tmp_name"], $newCompletionPhoto, $mime)) {
            header("Location: assigned_requests.php?error=" . urlencode("Unable to save the completion photo."));
            exit;
        }
    }

    if ($status === "Completed" && !$newCompletionPhoto && !$oldCompletionPhoto) {
        header("Location: assigned_requests.php?error=" . urlencode("Add a completion photo before marking this request completed."));
        exit;
    }

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("UPDATE requests SET status = ?, completion_photo = COALESCE(?, completion_photo) WHERE id = ? AND assigned_to = ?");
        $stmt->bind_param("ssii", $status, $newCompletionPhoto, $requestId, $userId);
        if (!$stmt->execute()) {
            throw new RuntimeException("Unable to update request status.");
        }

        if ($stmt->affected_rows === 0) {
            $check = $conn->prepare("SELECT id FROM requests WHERE id = ? AND assigned_to = ?");
            $check->bind_param("ii", $requestId, $userId);
            $check->execute();
            if (!$check->get_result()->fetch_assoc()) {
                $conn->rollback();
                photo_delete($newCompletionPhoto);
                header("Location: assigned_requests.php?error=" . urlencode("You can only update requests assigned to your account."));
                exit;
            }
        }

        if (!record_request_progress($conn, $requestId, $status)) {
            throw new RuntimeException("Unable to save request progress.");
        }
        $conn->commit();
        firebase_sync_request_from_db($conn, $requestId);
        notify_request_updated($conn, $requestId, $oldStatus, $oldAssignedTo, $status, $userId);
    } catch (mysqli_sql_exception | RuntimeException $exception) {
        $conn->rollback();
        photo_delete($newCompletionPhoto);
        error_log("CampusFix request progress update failed: " . $exception->getMessage());
        header("Location: assigned_requests.php?error=" . urlencode("Unable to update request progress."));
        exit;
    }
    if ($newCompletionPhoto && $oldCompletionPhoto) {
        photo_delete($oldCompletionPhoto);
    }

    $successMessage = "Request #{$requestId} status updated to {$status}.";
    if ($newCompletionPhoto) {
        $successMessage .= " Completion photo saved.";
    }
    header("Location: assigned_requests.php?success=" . urlencode($successMessage));
    exit;
}

header("Location: dashboard.php?error=" . urlencode("You are not authorized to update requests."));
exit;

function record_request_progress(mysqli $conn, int $requestId, string $status): bool {
    $stagesByStatus = [
        "Pending" => [],
        "Assigned" => ["reviewed", "assigned"],
        "In Progress" => ["reviewed", "assigned", "repair"],
        "Completed" => ["reviewed", "assigned", "repair", "resolved"]
    ];
    $stmt = $conn->prepare(
        "INSERT IGNORE INTO request_progress (request_id, stage) VALUES (?, ?)"
    );
    foreach ($stagesByStatus[$status] as $stage) {
        $stmt->bind_param("is", $requestId, $stage);
        if (!$stmt->execute()) {
            return false;
        }
    }
    return true;
}
?>
