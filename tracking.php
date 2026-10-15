<?php
require "config.php";
require_roles(["requester"]);

$requestId = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
if (!$requestId || $requestId < 1) {
    http_response_code(404);
    $request = null;
} else {
    $stmt = $conn->prepare(
        "SELECT r.*, assigned.name AS assigned_name
         FROM requests r
         LEFT JOIN users assigned ON r.assigned_to = assigned.id
         WHERE r.id = ? AND r.requester_id = ?"
    );
    $stmt->bind_param("ii", $requestId, $_SESSION["user_id"]);
    $stmt->execute();
    $request = $stmt->get_result()->fetch_assoc();
    if (!$request) {
        http_response_code(404);
    }
}

function tracking_escape(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
}

function tracking_time(?string $value): string {
    return $value ? date("M j, Y · g:i A", strtotime($value)) : "Not started";
}

$progress = [];
if ($request) {
    $stmt = $conn->prepare("SELECT stage, completed_at FROM request_progress WHERE request_id = ?");
    $stmt->bind_param("i", $requestId);
    $stmt->execute();
    $progressResult = $stmt->get_result();
    while ($stage = $progressResult->fetch_assoc()) {
        $progress[$stage["stage"]] = $stage["completed_at"];
    }
}

$currentStage = match ($request["status"] ?? "") {
    "Assigned" => "assigned",
    "In Progress" => "repair",
    default => null
};
$stages = [
    ["key" => "submitted", "label" => "Request Submitted", "time" => $request["created_at"] ?? null],
    ["key" => "reviewed", "label" => "Request Reviewed", "time" => $progress["reviewed"] ?? null],
    ["key" => "assigned", "label" => "Assigned to Maintenance", "time" => $progress["assigned"] ?? null],
    ["key" => "repair", "label" => "Repair", "time" => $progress["repair"] ?? null],
    ["key" => "resolved", "label" => "Resolved", "time" => $progress["resolved"] ?? null]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Request - CampusFix</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php include "partials/nav.php"; ?>
<main class="container tracking-page" id="trackingContainer" data-request-id="<?= $request ? (int)$request["id"] : 0 ?>" data-initial-status="<?= $request ? tracking_escape($request["status"]) : "" ?>">
    <div class="page-heading">
        <div>
            <span class="eyebrow">REQUEST TRACKING</span>
            <h1><?= $request ? "Track Request #" . (int)$request["id"] : "Request not found" ?></h1>
            <p class="muted"><?= $request ? "Follow the latest progress on your request." : "This request could not be found in your account." ?></p>
        </div>
        <a class="btn secondary" href="my_requests.php">Back to My Requests</a>
    </div>

    <?php if ($request): ?>
        <section class="panel tracking-summary">
            <div class="tracking-title-row">
                <div>
                    <span class="eyebrow">REQUEST DETAILS</span>
                    <h2><?= tracking_escape($request["title"]) ?></h2>
                </div>
                <span id="requestStatusBadge" class="status status-<?= strtolower(str_replace(" ", "-", tracking_escape($request["status"]))) ?>"><?= tracking_escape($request["status"]) ?></span>
            </div>
            <div class="tracking-info-grid">
                <div><strong>Request ID</strong><span>#<?= (int)$request["id"] ?></span></div>
                <div><strong>Category</strong><span><?= tracking_escape($request["category"]) ?></span></div>
                <div><strong>Location</strong><span><?= tracking_escape($request["location"]) ?></span></div>
                <div><strong>Assigned Personnel</strong><span id="assignedPersonnelName"><?= tracking_escape($request["assigned_name"] ?? "Not assigned") ?></span></div>
                <div><strong>Date Submitted</strong><span><?= tracking_escape(tracking_time($request["created_at"])) ?></span></div>
                <div><strong>Last Updated</strong><span id="lastUpdatedTime"><?= tracking_escape(tracking_time($request["updated_at"])) ?></span></div>
            </div>
        </section>

        <section class="panel tracking-progress-panel" aria-labelledby="progress-heading">
            <div class="panel-title-row">
                <div><span class="eyebrow">PROGRESS UPDATES</span><h2 id="progress-heading">Request timeline</h2></div>
                <span class="tracking-refresh-note" id="liveStatusNote">Connecting live updates...</span>
            </div>
            <ol class="tracking-timeline">
                <?php foreach ($stages as $index => $stage):
                    $isCurrent = $stage["key"] === $currentStage && empty($progress["resolved"]);
                    $isDone = $stage["key"] === "submitted"
                        || (!empty($progress[$stage["key"]]) && !$isCurrent)
                        || ($request["status"] === "Completed");
                    $state = $isDone ? "done" : ($isCurrent ? "current" : "upcoming");
                ?>
                    <li class="tracking-stage <?= $state ?>" data-stage="<?= $stage["key"] ?>">
                        <span class="tracking-marker" aria-hidden="true"><?= $isDone ? "✓" : ($isCurrent ? "●" : ($index + 1)) ?></span>
                        <div class="tracking-stage-content">
                            <strong><?= tracking_escape($stage["label"]) ?></strong>
                            <time><?= tracking_escape(tracking_time($stage["time"])) ?></time>
                            <?php if ($isCurrent): ?><small>Currently in progress</small><?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ol>
            <p class="muted tracking-history-note">Request Reviewed is recorded when an administrator first assigns the request.</p>
        </section>
    <?php else: ?>
        <section class="panel"><p class="muted">You can only track requests submitted from your account.</p></section>
    <?php endif; ?>
</main>
<script src="https://www.gstatic.com/firebasejs/10.12.0/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.12.0/firebase-firestore-compat.js"></script>
<script src="assets/firebase-tracking.js"></script>
</body>
</html>
