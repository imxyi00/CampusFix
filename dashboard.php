<?php
require "config.php";
require_login();

$userId = (int)$_SESSION["user_id"];
$name = $_SESSION["name"];
$role = $_SESSION["role"];

if ($role === "requester") {
    $stmt = $conn->prepare("SELECT COUNT(*) total, COALESCE(SUM(status='Pending'),0) pending, COALESCE(SUM(status IN ('Assigned','In Progress')),0) active, COALESCE(SUM(status='Completed'),0) completed FROM requests WHERE requester_id = ?");
    $stmt->bind_param("i", $userId);
} elseif (in_array($role, ["maintenance", "itso"], true)) {
    $stmt = $conn->prepare("SELECT COUNT(*) total, COALESCE(SUM(status='Pending'),0) pending, COALESCE(SUM(status IN ('Assigned','In Progress')),0) active, COALESCE(SUM(status='Completed'),0) completed FROM requests WHERE assigned_to = ?");
    $stmt->bind_param("i", $userId);
} else {
    $stmt = $conn->prepare("SELECT COUNT(*) total, COALESCE(SUM(status='Pending'),0) pending, COALESCE(SUM(status IN ('Assigned','In Progress')),0) active, COALESCE(SUM(status='Completed'),0) completed FROM requests");
}
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

$total = (int)$stats["total"];
$pending = (int)$stats["pending"];
$active = (int)$stats["active"];
$completed = (int)$stats["completed"];

$recent = [];
$allRequests = [];
$notifications = [];
$selectedProgress = null;

if ($role === "requester") {
    $stmt = $conn->prepare("SELECT r.*, u.name AS assigned_name FROM requests r LEFT JOIN users u ON r.assigned_to = u.id WHERE r.requester_id = ? ORDER BY r.created_at DESC");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $allRequestsResult = $stmt->get_result();
    while ($row = $allRequestsResult->fetch_assoc()) $allRequests[] = $row;
    $recent = array_slice($allRequests, 0, 5);

    $stmt = $conn->prepare("SELECT r.id, r.title, r.status, r.updated_at FROM requests r WHERE r.requester_id = ? ORDER BY r.updated_at DESC");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $notificationResult = $stmt->get_result();
    while ($row = $notificationResult->fetch_assoc()) $notifications[] = $row;

    foreach ($recent as $row) {
        if ($row["status"] !== "Completed") {
            $selectedProgress = $row;
            break;
        }
    }
    if (!$selectedProgress && !empty($recent)) $selectedProgress = $recent[0];
}

$categoryRows = [];
$workloadRows = [];
$attentionRows = [];
$adminRecent = [];

if (in_array($role, ["admin_staff", "system_admin"], true)) {
    $catResult = $conn->query("SELECT category, COUNT(*) total FROM requests GROUP BY category ORDER BY total DESC LIMIT 6");
    while ($row = $catResult->fetch_assoc()) $categoryRows[] = $row;

    $workResult = $conn->query("SELECT u.name, u.role, COUNT(r.id) active_tasks FROM users u LEFT JOIN requests r ON r.assigned_to = u.id AND r.status IN ('Assigned','In Progress') WHERE u.role IN ('maintenance','itso') GROUP BY u.id ORDER BY active_tasks DESC, u.name LIMIT 6");
    while ($row = $workResult->fetch_assoc()) $workloadRows[] = $row;

    $attResult = $conn->query("SELECT r.id, r.title, r.status, r.created_at, r.assigned_to, u.name AS requester_name FROM requests r JOIN users u ON r.requester_id = u.id WHERE r.status = 'Pending' OR (r.status <> 'Completed' AND r.created_at < DATE_SUB(NOW(), INTERVAL 2 DAY)) ORDER BY r.created_at ASC LIMIT 6");
    while ($row = $attResult->fetch_assoc()) $attentionRows[] = $row;

    $recentResult = $conn->query("SELECT r.id, r.title, r.category, r.status, r.created_at, u.name AS requester_name FROM requests r JOIN users u ON r.requester_id = u.id ORDER BY r.created_at DESC LIMIT 6");
    while ($row = $recentResult->fetch_assoc()) $adminRecent[] = $row;
}

function status_class(string $status): string {
    return "status-" . strtolower(str_replace(" ", "-", $status));
}
function progress_active(string $current, string $step): bool {
    $order = ["Pending" => 1, "Assigned" => 2, "In Progress" => 3, "Completed" => 4];
    return ($order[$current] ?? 1) >= ($order[$step] ?? 1);
}
function progress_label(string $current): string {
    return match ($current) {
        "Pending" => "Submitted and awaiting review",
        "Assigned" => "Reviewed and assigned",
        "In Progress" => "Work is currently in progress",
        "Completed" => "Request has been completed",
        default => "Request status"
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="assets/cflogo.png">
    <title>Dashboard - CampusFix</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php include "partials/nav.php"; ?>

<main class="container">
    <?php if (isset($_GET["error"])): ?><div class="alert error"><?= htmlspecialchars($_GET["error"]) ?></div><?php endif; ?>
    <?php if (isset($_GET["success"])): ?><div class="alert success"><?= htmlspecialchars($_GET["success"]) ?></div><?php endif; ?>

    <?php if ($role === "requester"): ?>
        <div class="page-heading dashboard-heading">
            <div>
                <span class="eyebrow">CAMPUSFIX DASHBOARD</span>
                <h1>Welcome back, <?= htmlspecialchars($name) ?></h1>
                <p class="muted">Track your campus facility requests and their progress.</p>
            </div>
            <a class="btn" href="submit_request.php">+ Submit Request</a>
        </div>


        <section class="stats-grid">
            <a class="stat-card stat-link" href="my_requests.php"><span>Total Requests</span><strong><?= $total ?></strong><small>All your submissions</small></a>
            <a class="stat-card stat-link" href="my_requests.php?status=Pending"><span>Pending</span><strong><?= $pending ?></strong><small>Waiting for review</small></a>
            <a class="stat-card stat-link" href="my_requests.php?status=active"><span>Active</span><strong><?= $active ?></strong><small>Assigned or in progress</small></a>
            <a class="stat-card stat-link" href="my_requests.php?status=Completed"><span>Completed</span><strong><?= $completed ?></strong><small>Resolved requests</small></a>
        </section>

        <div class="panel help-card">
                <span class="eyebrow">CAMPUS SUPPORT</span>
                <h2>Need to report a problem?</h2>
                <p class="muted">The CampusFix process begins when a user submits a facility or service request with the necessary details. Administrative staff reviews the request and assigns it to the appropriate maintenance or ITSO personnel. The assigned personnel handles the concern and updates its status. The requester can track the progress until the request is completed and recorded in the system.</p>
                <a class="text-link" href="help_support.php">Visit Help &amp; Support</a>
         </div>

        <section class="dashboard-two-col">
                    
            <div class="panel dashboard-panel">
                <div class="panel-title-row"><div><span class="eyebrow">REQUEST HISTORY</span><h2>My Recent Requests</h2></div><button class="text-link dashboard-list-trigger" type="button" data-dialog-open="allRequestsDialog">See All</button></div>
                <?php if ($recent): ?>
                    <div class="mini-list">
                    <?php foreach ($recent as $row): ?>
                        <a class="mini-request" href="my_requests.php?request_id=<?= (int)$row["id"] ?>">
                            <div><strong>#<?= (int)$row["id"] ?> · <?= htmlspecialchars($row["title"]) ?></strong><span><?= htmlspecialchars($row["category"]) ?> · <?= htmlspecialchars($row["location"]) ?></span></div>
                            <span class="status <?= status_class($row["status"]) ?>"><?= htmlspecialchars($row["status"]) ?></span>
                        </a>
                    <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state"><strong>No requests yet.</strong><span>Submit your first facility or service request to get started.</span></div>
                <?php endif; ?>
            </div>

            <div class="panel dashboard-panel">
                <div class="panel-title-row"><div><span class="eyebrow">STATUS TRACKING</span><h2>Request Progress</h2></div></div>
                <?php if ($selectedProgress): ?>
                    <div class="progress-request"><strong>#<?= (int)$selectedProgress["id"] ?> · <?= htmlspecialchars($selectedProgress["title"]) ?></strong><span class="muted"><?= htmlspecialchars($selectedProgress["location"]) ?></span></div>
                    <div class="progress-steps">
                        <?php foreach (["Pending" => "Submitted", "Assigned" => "Reviewed / Assigned", "In Progress" => "In Progress", "Completed" => "Completed"] as $step => $label): ?>
                            <div class="progress-step <?= progress_active($selectedProgress["status"], $step) ? "done" : "" ?> <?= $selectedProgress["status"] === $step ? "current" : "" ?>"><span><?= progress_active($selectedProgress["status"], $step) ? "✓" : "○" ?></span><div><strong><?= $label ?></strong><?php if ($selectedProgress["status"] === $step): ?><small><?= htmlspecialchars(progress_label($selectedProgress["status"])) ?></small><?php endif; ?></div></div>
                        <?php endforeach; ?>
                    </div>
                    <a class="btn secondary full-btn" href="my_requests.php?request_id=<?= (int)$selectedProgress["id"] ?>">View Request</a>
                <?php else: ?>
                    <div class="empty-state compact"><strong>No active request to track.</strong><span>Your pending and completed requests will appear here when available.</span></div>
                <?php endif; ?>
            </div>


        </section>

        <section class="dashboard-two-col">
            
            <div class="panel dashboard-panel">
                <div class="panel-title-row"><div><span class="eyebrow">UPDATES</span><h2>Notifications</h2></div><button class="text-link dashboard-list-trigger" type="button" data-dialog-open="allNotificationsDialog">View All</button></div>
                <?php if ($notifications): ?>
                    <div class="notification-list dashboard-notification-preview<?= count($notifications) > 5 ? " is-scrollable" : "" ?>">
                    <?php foreach ($notifications as $row): ?>
                        <div class="notification-item"><span class="notification-dot">●</span><div><strong>Request #<?= (int)$row["id"] ?> · <?= htmlspecialchars($row["title"]) ?></strong><p>Status: <b><?= htmlspecialchars($row["status"]) ?></b></p><small>Updated <?= htmlspecialchars($row["updated_at"]) ?></small></div></div>
                    <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state compact"><strong>No notifications yet.</strong><span>Updates to your requests will appear here.</span></div>
                <?php endif; ?>
            </div>


        </section>

        <dialog class="dashboard-list-dialog" id="allRequestsDialog" aria-labelledby="allRequestsDialogTitle">
            <div class="dashboard-dialog-header">
                <div><span class="eyebrow">REQUEST HISTORY</span><h2 id="allRequestsDialogTitle">All My Requests</h2></div>
                <button class="modal-close" type="button" data-dialog-close aria-label="Close all requests">×</button>
            </div>
            <div class="dashboard-dialog-content">
                <?php if ($allRequests): ?>
                    <div class="mini-list">
                        <?php foreach ($allRequests as $row): ?>
                            <a class="mini-request" href="tracking.php?id=<?= (int)$row["id"] ?>">
                                <div><strong>#<?= (int)$row["id"] ?> · <?= htmlspecialchars($row["title"]) ?></strong><span><?= htmlspecialchars($row["category"]) ?> · <?= htmlspecialchars($row["location"]) ?></span></div>
                                <span class="status <?= status_class($row["status"]) ?>"><?= htmlspecialchars($row["status"]) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state compact"><strong>No requests yet.</strong><span>Submit your first facility or service request to get started.</span></div>
                <?php endif; ?>
            </div>
            <div class="dashboard-dialog-footer"><a class="btn secondary" href="my_requests.php">Open My Requests</a></div>
        </dialog>

        <dialog class="dashboard-list-dialog" id="allNotificationsDialog" aria-labelledby="allNotificationsDialogTitle">
            <div class="dashboard-dialog-header">
                <div><span class="eyebrow">UPDATES</span><h2 id="allNotificationsDialogTitle">All Notifications</h2></div>
                <button class="modal-close" type="button" data-dialog-close aria-label="Close notifications">×</button>
            </div>
            <div class="dashboard-dialog-content">
                <?php if ($notifications): ?>
                    <div class="notification-list">
                        <?php foreach ($notifications as $row): ?>
                            <a class="notification-item dashboard-notification-link" href="tracking.php?id=<?= (int)$row["id"] ?>">
                                <span class="notification-dot" aria-hidden="true">●</span>
                                <span><strong>Request #<?= (int)$row["id"] ?> · <?= htmlspecialchars($row["title"]) ?></strong><span class="dashboard-notification-status">Status: <b><?= htmlspecialchars($row["status"]) ?></b></span><small>Updated <?= htmlspecialchars($row["updated_at"]) ?></small></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state compact"><strong>No notifications yet.</strong><span>Updates to your requests will appear here.</span></div>
                <?php endif; ?>
            </div>
            <div class="dashboard-dialog-footer"><a class="btn secondary" href="my_requests.php">Open My Requests</a></div>
        </dialog>

    <?php elseif (in_array($role, ["admin_staff", "system_admin"], true)): ?>
        <div class="page-heading dashboard-heading">
            <div><span class="eyebrow">OPERATIONS DASHBOARD</span><h1>Welcome back, <?= htmlspecialchars($name) ?></h1><p class="muted">Monitor requests, workload, and items that need attention.</p></div>
            <a class="btn" href="manage_requests.php">Review Requests</a>
        </div>
        <section class="stats-grid">
            <a class="stat-card stat-link" href="manage_requests.php"><span>Total Requests</span><strong><?= $total ?></strong><small>All campus requests</small></a>
            <a class="stat-card stat-link" href="manage_requests.php?status=Pending"><span>Pending</span><strong><?= $pending ?></strong><small>Waiting for review</small></a>
            <a class="stat-card stat-link" href="manage_requests.php?status=active"><span>Active</span><strong><?= $active ?></strong><small>Assigned or in progress</small></a>
            <a class="stat-card stat-link" href="manage_requests.php?status=Completed"><span>Completed</span><strong><?= $completed ?></strong><small>Resolved requests</small></a>
        </section>
        <section class="dashboard-two-col admin-top-grid">
            <div class="panel dashboard-panel"><div class="panel-title-row"><div><span class="eyebrow">REQUEST ANALYTICS</span><h2>Requests by Category</h2></div><a class="text-link" href="manage_requests.php">View All</a></div>
                <?php if ($categoryRows): $maxCat = max(array_column($categoryRows, "total")); foreach ($categoryRows as $row): $pct = $maxCat ? ((int)$row["total"] / $maxCat) * 100 : 0; ?><div class="bar-row"><div><span><?= htmlspecialchars($row["category"]) ?></span><strong><?= (int)$row["total"] ?></strong></div><div class="bar-track"><i style="width: <?= $pct ?>%"></i></div></div><?php endforeach; else: ?><div class="empty-state compact"><strong>No request data yet.</strong></div><?php endif; ?>
            </div>
            <div class="panel dashboard-panel"><div class="panel-title-row"><div><span class="eyebrow">ATTENTION</span><h2>Needs Attention</h2></div></div>
                <?php if ($attentionRows): ?><div class="mini-list compact-list"><?php foreach ($attentionRows as $row): ?><a class="mini-request" href="manage_requests.php?request_id=<?= (int)$row["id"] ?>"><div><strong>#<?= (int)$row["id"] ?> · <?= htmlspecialchars($row["title"]) ?></strong><span><?= $row["status"] === "Pending" ? "Awaiting review" : "Waiting more than 2 days" ?></span></div><span class="status <?= status_class($row["status"]) ?>"><?= htmlspecialchars($row["status"]) ?></span></a><?php endforeach; ?></div><?php else: ?><div class="empty-state compact"><strong>Nothing needs immediate attention.</strong><span>New or delayed requests will appear here.</span></div><?php endif; ?>
            </div>
        </section>
        <section class="dashboard-two-col">
            <div class="panel dashboard-panel"><div class="panel-title-row"><div><span class="eyebrow">LATEST ACTIVITY</span><h2>Recent Requests</h2></div><a class="text-link" href="manage_requests.php">Manage Requests</a></div><div class="mini-list compact-list"><?php if ($adminRecent): foreach ($adminRecent as $row): ?><a class="mini-request" href="manage_requests.php?request_id=<?= (int)$row["id"] ?>"><div><strong>#<?= (int)$row["id"] ?> · <?= htmlspecialchars($row["title"]) ?></strong><span><?= htmlspecialchars($row["requester_name"]) ?> · <?= htmlspecialchars($row["category"]) ?></span></div><span class="status <?= status_class($row["status"]) ?>"><?= htmlspecialchars($row["status"]) ?></span></a><?php endforeach; else: ?><div class="empty-state compact"><strong>No requests yet.</strong></div><?php endif; ?></div></div>
            <div class="panel dashboard-panel"><div class="panel-title-row"><div><span class="eyebrow">PERSONNEL</span><h2>Personnel Workload</h2></div></div><?php if ($workloadRows): ?><div class="workload-list"><?php foreach ($workloadRows as $row): ?><div class="workload-row"><div><strong><?= htmlspecialchars($row["name"]) ?></strong><span><?= htmlspecialchars(role_label($row["role"])) ?></span></div><b><?= (int)$row["active_tasks"] ?> active</b></div><?php endforeach; ?></div><?php else: ?><div class="empty-state compact"><strong>No personnel accounts yet.</strong></div><?php endif; ?></div>
        </section>

    <?php else: ?>
        <div class="page-heading dashboard-heading"><div><span class="eyebrow">MY WORK DASHBOARD</span><h1>Welcome back, <?= htmlspecialchars($name) ?></h1><p class="muted">Review and update requests assigned to your account.</p></div><a class="btn" href="assigned_requests.php">View Assigned Requests</a></div>
        <section class="stats-grid"><a class="stat-card stat-link" href="assigned_requests.php"><span>Total Assigned</span><strong><?= $total ?></strong><small>Your assigned requests</small></a><a class="stat-card stat-link" href="assigned_requests.php?status=Pending"><span>Pending</span><strong><?= $pending ?></strong><small>Awaiting action</small></a><a class="stat-card stat-link" href="assigned_requests.php?status=active"><span>Active</span><strong><?= $active ?></strong><small>Assigned or in progress</small></a><a class="stat-card stat-link" href="assigned_requests.php?status=Completed"><span>Completed</span><strong><?= $completed ?></strong><small>Finished tasks</small></a></section>
        <section class="panel accent-panel"><span class="eyebrow">TASK MANAGEMENT</span><h2>Assigned Requests</h2><p class="muted">Open your assigned work to review request details and update its progress.</p><a class="btn secondary" href="assigned_requests.php">Open Assigned Requests</a></section>
    <?php endif; ?>
</main>
<script src="assets/script.js"></script>
<?php if (isset($_SESSION["user_id"])): ?>
<div id="firebaseLiveWatcher" data-user-id="<?= (int)$userId ?>" data-requester-id="<?= (int)$userId ?>" data-user-role="<?= htmlspecialchars($role) ?>" data-server-time="<?= time() ?>" data-firebase-sync-available="<?= is_file(FIREBASE_SERVICE_ACCOUNT_PATH) ? "true" : "false" ?>"></div>
<script src="assets/request-polling.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.12.0/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.12.0/firebase-firestore-compat.js"></script>
<script src="assets/firebase-tracking.js"></script>
<?php endif; ?>
</body>
</html>
