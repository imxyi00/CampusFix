<?php
require "config.php";

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("X-Content-Type-Options: nosniff");

if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode(["error" => "Your session has expired. Please sign in again."]);
    exit;
}

$userId = (int)$_SESSION["user_id"];
$authStmt = $conn->prepare("SELECT name, role FROM users WHERE id = ?");
$authStmt->bind_param("i", $userId);
$authStmt->execute();
$user = $authStmt->get_result()->fetch_assoc();
$authStmt->close();

if (!$user || !in_array($user["role"], ["requester", "admin_staff", "system_admin", "maintenance", "itso"], true)) {
    session_unset();
    session_destroy();
    http_response_code(401);
    echo json_encode(["error" => "Your account is no longer available. Please sign in again."]);
    exit;
}

$_SESSION["name"] = $user["name"];
$_SESSION["role"] = $user["role"];

$since = filter_var($_GET["since"] ?? null, FILTER_VALIDATE_INT);
if ($since === false || $since === null || $since < 0 || $since > time() + 60) {
    http_response_code(400);
    echo json_encode(["error" => "A valid update cursor is required."]);
    exit;
}

$role = $_SESSION["role"];
$sql = "SELECT r.id, r.requester_id, r.assigned_to, r.title, r.category, r.location,
               r.status, r.created_at, r.updated_at, r.completion_photo,
               requester.name AS requester_name, assigned.name AS assigned_name,
               progress.reviewed_at, progress.assigned_at, progress.repair_at, progress.resolved_at
        FROM requests r
        JOIN users requester ON requester.id = r.requester_id
        LEFT JOIN users assigned ON assigned.id = r.assigned_to
        LEFT JOIN (
            SELECT request_id,
                   MAX(CASE WHEN stage = 'reviewed' THEN completed_at END) AS reviewed_at,
                   MAX(CASE WHEN stage = 'assigned' THEN completed_at END) AS assigned_at,
                   MAX(CASE WHEN stage = 'repair' THEN completed_at END) AS repair_at,
                   MAX(CASE WHEN stage = 'resolved' THEN completed_at END) AS resolved_at
            FROM request_progress
            GROUP BY request_id
        ) progress ON progress.request_id = r.id
        WHERE r.updated_at >= FROM_UNIXTIME(?) AND r.updated_at <= NOW()";
$params = [max(0, $since - 1)];
$types = "i";

if ($role === "requester") {
    $sql .= " AND r.requester_id = ?";
    $params[] = $userId;
    $types .= "i";
} elseif (in_array($role, ["maintenance", "itso"], true)) {
    $sql .= " AND r.assigned_to = ?";
    $params[] = $userId;
    $types .= "i";
} elseif (!in_array($role, ["admin_staff", "system_admin"], true)) {
    http_response_code(403);
    echo json_encode(["error" => "You are not authorized to view request updates."]);
    exit;
}

$sql .= " ORDER BY r.updated_at DESC, r.id DESC LIMIT 200";

try {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $requests = [];
    while ($row = $result->fetch_assoc()) {
        $row["id"] = (int)$row["id"];
        $row["requester_id"] = (int)$row["requester_id"];
        $row["assigned_to"] = $row["assigned_to"] === null ? null : (int)$row["assigned_to"];
        $row["stages"] = [
            "submitted" => $row["created_at"],
            "reviewed" => $row["reviewed_at"],
            "assigned" => $row["assigned_at"],
            "repair" => $row["repair_at"],
            "resolved" => $row["resolved_at"]
        ];
        unset($row["reviewed_at"], $row["assigned_at"], $row["repair_at"], $row["resolved_at"]);
        $requests[] = $row;
    }

    $clock = $conn->query("SELECT UNIX_TIMESTAMP(NOW()) AS server_time")->fetch_assoc();
    echo json_encode([
        "server_time" => (int)$clock["server_time"],
        "requests" => $requests
    ], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    error_log("CampusFix request update polling failed: " . $exception->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Unable to load request updates."]);
}
?>
