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
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("UPDATE requests SET status = ? WHERE id = ? AND assigned_to = ?");
        $stmt->bind_param("sii", $status, $requestId, $userId);
        if (!$stmt->execute()) {
            throw new RuntimeException("Unable to update request status.");
        }

        if ($stmt->affected_rows === 0) {
            $check = $conn->prepare("SELECT id FROM requests WHERE id = ? AND assigned_to = ?");
            $check->bind_param("ii", $requestId, $userId);
            $check->execute();
            if (!$check->get_result()->fetch_assoc()) {
                $conn->rollback();
                header("Location: assigned_requests.php?error=" . urlencode("You can only update requests assigned to your account."));
                exit;
            }
        }

        if (!record_request_progress($conn, $requestId, $status)) {
            throw new RuntimeException("Unable to save request progress.");
        }
        $conn->commit();
    } catch (mysqli_sql_exception | RuntimeException $exception) {
        $conn->rollback();
        error_log("CampusFix request progress update failed: " . $exception->getMessage());
        header("Location: assigned_requests.php?error=" . urlencode("Unable to update request progress."));
        exit;
    }

    header("Location: assigned_requests.php?success=" . urlencode("Request status updated successfully."));
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
