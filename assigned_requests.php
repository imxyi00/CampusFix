<?php
require "config.php";
require_roles(["maintenance", "itso"]);

$statusFilter = $_GET["status"] ?? "";
$userId = (int)$_SESSION["user_id"];
$where = "r.assigned_to = ?";
$params = [$userId];
$types = "i";
if ($statusFilter === "active") {
    $where .= " AND r.status IN ('Assigned', 'In Progress')";
} elseif (in_array($statusFilter, ["Pending", "Assigned", "In Progress", "Completed"], true)) {
    $where .= " AND r.status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}

$stmt = $conn->prepare("SELECT r.*, requester.name AS requester_name FROM requests r JOIN users requester ON r.requester_id = requester.id WHERE {$where} ORDER BY r.created_at DESC");
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$allStmt = $conn->prepare("SELECT r.id, r.title, r.category, r.location, r.status, r.created_at, requester.name AS requester_name FROM requests r JOIN users requester ON r.requester_id = requester.id WHERE r.assigned_to = ? ORDER BY r.created_at DESC");
$allStmt->bind_param("i", $userId);
$allStmt->execute();
$allRequests = $allStmt->get_result();
function status_class(string $status): string { return "status-" . strtolower(str_replace(" ", "-", $status)); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="assets/cflogo.png">
    <title>Assigned Requests - CampusFix</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php include "partials/nav.php"; ?>

<main class="container">
    <div class="page-heading">
        <div>
            <span class="eyebrow">MY ASSIGNED WORK</span>
            <h1>Assigned Requests</h1>
            <p class="muted">Only requests assigned to your account are shown here.</p>
        </div>
        <a class="btn secondary" href="dashboard.php">Back to Dashboard</a>
    </div>

    <?php if (isset($_GET["success"])): ?>
        <div class="alert success flash-notification" role="status"><?= htmlspecialchars($_GET["success"]) ?></div>
    <?php endif; ?>
    <?php if (isset($_GET["error"])): ?>
        <div class="alert error flash-notification" role="alert"><?= htmlspecialchars($_GET["error"]) ?></div>
    <?php endif; ?>

    <nav class="request-tabs" aria-label="Filter assigned requests">
        <a class="<?= $statusFilter === "" ? "active" : "" ?>" href="assigned_requests.php">All</a>
        <?php foreach (["Pending", "Assigned", "In Progress", "Completed"] as $status): ?>
            <a class="<?= $statusFilter === $status ? "active" : "" ?>" href="assigned_requests.php?status=<?= urlencode($status) ?>"><?= htmlspecialchars($status) ?></a>
        <?php endforeach; ?>
        <a class="<?= $statusFilter === "active" ? "active" : "" ?>" href="assigned_requests.php?status=active">Active</a>
    </nav>

    <div class="panel-title-row"><div><span class="eyebrow">REQUEST LIST</span><h2>Assigned to Me</h2></div><button class="text-link dashboard-list-trigger" type="button" data-dialog-open="allAssignedRequestsDialog">See All</button></div>
    <div class="request-grid">
        <?php if ($result->num_rows > 0): while ($row = $result->fetch_assoc()): ?>
            <article class="request-card" id="assigned-request-<?= (int)$row["id"] ?>" data-live-request-id="<?= (int)$row["id"] ?>">
                <div class="request-card-top">
                    <h3>#<?= (int)$row["id"] ?> - <?= htmlspecialchars($row["title"]) ?></h3>
                    <span class="status status-<?= strtolower(str_replace(' ', '-', $row["status"])) ?>" data-live-status><?= htmlspecialchars($row["status"]) ?></span>
                </div>
                <div class="assigned-request-details">
                    <p><strong>Requester</strong><span><?= htmlspecialchars($row["requester_name"]) ?></span></p>
                    <p><strong>Category</strong><span><?= htmlspecialchars($row["category"]) ?></span></p>
                    <p><strong>Location</strong><span><?= htmlspecialchars($row["location"]) ?></span></p>
                </div>
                <div class="assigned-card-actions"><span class="muted">Submitted <?= htmlspecialchars(date("M j, Y", strtotime($row["created_at"]))) ?></span><button class="btn small-btn" type="button" data-dialog-open="assignedDetail<?= (int)$row["id"] ?>">Details &amp; Update</button></div>
            </article>
            <dialog class="dashboard-list-dialog" id="assignedDetail<?= (int)$row["id"] ?>" aria-labelledby="assignedDetailTitle<?= (int)$row["id"] ?>">
                <div class="dashboard-dialog-header"><div><span class="eyebrow">ASSIGNED REQUEST #<?= (int)$row["id"] ?></span><h2 id="assignedDetailTitle<?= (int)$row["id"] ?>"><?= htmlspecialchars($row["title"]) ?></h2></div><button class="modal-close" type="button" data-dialog-close aria-label="Close request details">×</button></div>
                <div class="dashboard-dialog-content">
                    <div class="review-details">
                        <div><strong>Requester</strong><span><?= htmlspecialchars($row["requester_name"]) ?></span></div>
                        <div><strong>Category</strong><span><?= htmlspecialchars($row["category"]) ?></span></div>
                        <div><strong>Location</strong><span><?= htmlspecialchars($row["location"]) ?></span></div>
                        <div><strong>Date Submitted</strong><span><?= htmlspecialchars($row["created_at"]) ?></span></div>
                    </div>
                    <div class="review-description"><strong>Reported issue</strong><p><?= nl2br(htmlspecialchars($row["description"])) ?></p></div>
                    <?php if (!empty($row["photo"])): ?><div class="evidence-photo-block"><strong>Requester photo</strong><img class="request-photo review-photo" src="uploads/<?= htmlspecialchars($row["photo"]) ?>" alt="Photo submitted by requester"></div><?php endif; ?>
                    <?php if (!empty($row["completion_photo"])): ?><div class="evidence-photo-block"><strong>Completion proof</strong><img class="request-photo review-photo" src="uploads/<?= htmlspecialchars($row["completion_photo"]) ?>" alt="Photo proving completed work"></div><?php endif; ?>
                    <form action="update_request.php" method="POST" enctype="multipart/form-data" class="update-form assigned-detail-form" data-confirm-title="Save changes?" data-confirm-message="Are you sure you want to update this request? Your selected status and any completion photo will be saved." data-confirm-label="Yes, save changes" data-confirm-cancel-label="No, cancel">
                        <?= csrf_input() ?>
                        <input type="hidden" name="request_id" value="<?= (int)$row["id"] ?>">
                        <div class="update-form-status">
                            <label for="status-<?= (int)$row["id"] ?>">Update status</label>
                            <select id="status-<?= (int)$row["id"] ?>" name="status" data-completion-status>
                                <?php foreach (["Pending", "Assigned", "In Progress", "Completed"] as $status): ?><option value="<?= htmlspecialchars($status) ?>" <?= $row["status"] === $status ? "selected" : "" ?>><?= htmlspecialchars($status) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="completion-photo-field" data-has-completion-photo="<?= empty($row["completion_photo"]) ? "false" : "true" ?>">
                            <label for="completion-photo-<?= (int)$row["id"] ?>">Completion photo <span class="required-badge" data-photo-required hidden>(required to complete)</span></label>
                            <input type="file" id="completion-photo-<?= (int)$row["id"] ?>" name="completion_photo" accept="image/jpeg,image/png,image/webp" data-completion-photo>
                            <small class="field-help">Required before marking Completed unless proof is already saved. JPG, PNG, or WEBP; max 5 MB.</small>
                        </div>
                        <button type="submit" class="authbtn">Save Update</button>
                    </form>
                </div>
            </dialog>
        <?php endwhile; else: ?>
            <div class="panel empty-state"><strong>No assigned requests found.</strong><span>Requests matching this filter will appear here.</span></div>
        <?php endif; ?>
    </div>
</main>
<dialog class="dashboard-list-dialog" id="allAssignedRequestsDialog" aria-labelledby="allAssignedRequestsDialogTitle">
    <div class="dashboard-dialog-header"><div><span class="eyebrow">MY ASSIGNED WORK</span><h2 id="allAssignedRequestsDialogTitle">All Assigned Requests</h2></div><button class="modal-close" type="button" data-dialog-close aria-label="Close all assigned requests">×</button></div>
    <div class="dashboard-dialog-content">
        <?php if ($allRequests->num_rows > 0): ?><div class="mini-list">
            <?php while ($row = $allRequests->fetch_assoc()): ?>
                <a class="mini-request" href="assigned_requests.php?status=<?= urlencode($row["status"]) ?>#assigned-request-<?= (int)$row["id"] ?>"><div><strong>#<?= (int)$row["id"] ?> · <?= htmlspecialchars($row["title"]) ?></strong><span><?= htmlspecialchars($row["requester_name"]) ?> · <?= htmlspecialchars($row["category"]) ?> · <?= htmlspecialchars($row["location"]) ?> · <?= htmlspecialchars($row["created_at"]) ?></span></div><span class="status <?= status_class($row["status"]) ?>"><?= htmlspecialchars($row["status"]) ?></span></a>
            <?php endwhile; ?>
        </div><?php else: ?><div class="empty-state compact"><strong>No assigned requests yet.</strong><span>Requests assigned to your account will appear here.</span></div><?php endif; ?>
    </div>
    <div class="dashboard-dialog-footer"><a class="btn secondary" href="assigned_requests.php">Open Assigned Requests</a></div>
</dialog>
<div id="firebaseLiveWatcher" data-user-id="<?= (int)$_SESSION["user_id"] ?>" data-user-role="<?= htmlspecialchars($_SESSION["role"]) ?>" data-server-time="<?= time() ?>" data-firebase-sync-available="<?= is_file(FIREBASE_SERVICE_ACCOUNT_PATH) ? "true" : "false" ?>"></div>
<script src="assets/script.js"></script>
<script src="assets/request-polling.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.12.0/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.12.0/firebase-firestore-compat.js"></script>
<script src="assets/firebase-tracking.js"></script>
</body>
</html>
