<?php
require "config.php";
require_roles(["admin_staff", "system_admin"]);

$search = trim($_GET["search"] ?? "");
$statusFilter = $_GET["status"] ?? "";
$categoryFilter = $_GET["category"] ?? "";
$assignedFilter = $_GET["assigned_to"] ?? "";
$sort = $_GET["sort"] ?? "newest";
$focusId = (int)($_GET["request_id"] ?? 0);

$where = [];
$params = [];
$types = "";

if ($search !== "") {
    $where[] = "(r.id = ? OR r.title LIKE ? OR r.description LIKE ? OR r.location LIKE ? OR r.category LIKE ? OR requester.name LIKE ? OR COALESCE(assigned.name,'') LIKE ?)";
    $params[] = ctype_digit($search) ? (int)$search : -1;
    $types .= "i";
    $like = "%{$search}%";
    for ($i = 0; $i < 6; $i++) { $params[] = $like; $types .= "s"; }
}
if ($statusFilter === "active") $where[] = "r.status IN ('Assigned','In Progress')";
elseif (in_array($statusFilter, ["Pending", "Assigned", "In Progress", "Completed"], true)) { $where[] = "r.status = ?"; $params[] = $statusFilter; $types .= "s"; }
if ($categoryFilter !== "") { $where[] = "r.category = ?"; $params[] = $categoryFilter; $types .= "s"; }
if ($assignedFilter === "unassigned") $where[] = "r.assigned_to IS NULL";
elseif (ctype_digit((string)$assignedFilter) && (int)$assignedFilter > 0) { $where[] = "r.assigned_to = ?"; $params[] = (int)$assignedFilter; $types .= "i"; }

$order = match ($sort) {
    "oldest" => "r.created_at ASC",
    "az" => "r.title ASC",
    "pending" => "FIELD(r.status,'Pending','Assigned','In Progress','Completed'), r.created_at DESC",
    "unassigned" => "(r.assigned_to IS NOT NULL) ASC, r.created_at DESC",
    default => "r.created_at DESC"
};

$sql = "SELECT r.*, requester.name AS requester_name, assigned.name AS assigned_name, assigned.role AS assigned_role FROM requests r JOIN users requester ON r.requester_id = requester.id LEFT JOIN users assigned ON r.assigned_to = assigned.id";
if ($where) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY {$order}";
$stmt = $conn->prepare($sql);
if ($types !== "") $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$allRequests = $conn->query("SELECT r.id, r.title, r.category, r.location, r.status, r.created_at, requester.name AS requester_name FROM requests r JOIN users requester ON r.requester_id = requester.id ORDER BY r.created_at DESC");
$reviewRequest = null;

$personnel = $conn->query("SELECT id, name, role FROM users WHERE role IN ('maintenance','itso') ORDER BY name");
$personnelRows = [];
while ($p = $personnel->fetch_assoc()) $personnelRows[] = $p;

$categories = [];
$catResult = $conn->query("SELECT DISTINCT category FROM requests ORDER BY category");
while ($c = $catResult->fetch_assoc()) $categories[] = $c["category"];

function keep_query(array $extra = []): string { return http_build_query(array_merge($_GET, $extra)); }
function status_class(string $status): string { return "status-" . strtolower(str_replace(" ", "-", $status)); }
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><link rel="icon" type="image/png" href="assets/cflogo.png"><title>Manage Requests - CampusFix</title><link rel="stylesheet" href="assets/style.css"></head>
<body>
<?php include "partials/nav.php"; ?>
<main class="container">
    <div class="page-heading"><div><span class="eyebrow">REQUEST MANAGEMENT</span><h1>Manage Requests</h1><p class="muted">Search, filter, review, assign, and update submitted requests.</p></div></div>
    <?php if (isset($_GET["success"])): ?><div class="alert success flash-notification" data-toast-title="Changes saved" role="status"><?= htmlspecialchars($_GET["success"]) ?></div><?php endif; ?>
    <?php if (isset($_GET["error"])): ?><div class="alert error flash-notification" role="alert"><?= htmlspecialchars($_GET["error"]) ?></div><?php endif; ?>

    <section class="panel filter-panel">
        <form method="GET" class="filter-form">
            <div class="filter-search"><label>Search</label><input type="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="ID, title, requester, location, category..."></div>
            <div><label>Status</label><select name="status"><option value="">All statuses</option><?php foreach (["Pending","Assigned","In Progress","Completed"] as $s): ?><option value="<?= $s ?>" <?= $statusFilter === $s ? "selected" : "" ?>><?= $s ?></option><?php endforeach; ?><option value="active" <?= $statusFilter === "active" ? "selected" : "" ?>>Active</option></select></div>
            <div><label>Category</label><select name="category"><option value="">All categories</option><?php foreach ($categories as $c): ?><option value="<?= htmlspecialchars($c) ?>" <?= $categoryFilter === $c ? "selected" : "" ?>><?= htmlspecialchars($c) ?></option><?php endforeach; ?></select></div>
            <div><label>Assigned To</label><select name="assigned_to"><option value="">Everyone</option><option value="unassigned" <?= $assignedFilter === "unassigned" ? "selected" : "" ?>>Unassigned</option><?php foreach ($personnelRows as $p): ?><option value="<?= (int)$p["id"] ?>" <?= (string)$assignedFilter === (string)$p["id"] ? "selected" : "" ?>><?= htmlspecialchars($p["name"]) ?></option><?php endforeach; ?></select></div>
            <div><label>Sort</label><select name="sort"><option value="newest" <?= $sort === "newest" ? "selected" : "" ?>>Newest first</option><option value="oldest" <?= $sort === "oldest" ? "selected" : "" ?>>Oldest first</option><option value="az" <?= $sort === "az" ? "selected" : "" ?>>Title A-Z</option><option value="pending" <?= $sort === "pending" ? "selected" : "" ?>>Pending first</option><option value="unassigned" <?= $sort === "unassigned" ? "selected" : "" ?>>Unassigned first</option></select></div>
            <div class="filter-actions"><button type="submit" class="authbtn">Apply Filters</button><a class="btn secondary" href="manage_requests.php">Reset</a></div>
        </form>
    </section>

    <section class="panel">
        <div class="panel-title-row"><div><span class="eyebrow">REQUEST LIST</span><h2>Submitted Requests</h2></div><div class="panel-title-actions"><span class="result-count"><?= $result->num_rows ?> result(s)</span><button class="text-link dashboard-list-trigger" type="button" data-dialog-open="allManagedRequestsDialog">See All</button></div></div>
        <div class="table-wrap">
            <table><thead><tr><th>ID</th><th>Request</th><th>Category</th><th>Location</th><th>Status</th><th>Assigned To</th><th>Date</th><th>Action</th></tr></thead>
            <tbody>
            <?php if ($result->num_rows > 0): while ($row = $result->fetch_assoc()): ?>
                <?php if ($focusId === (int)$row["id"]) $reviewRequest = $row; ?>
                <tr class="<?= $focusId === (int)$row["id"] ? "highlight-row" : "" ?>" data-live-request-id="<?= (int)$row["id"] ?>">
                    <td>#<?= (int)$row["id"] ?></td><td><strong><?= htmlspecialchars($row["title"]) ?></strong><small class="table-sub"><?= htmlspecialchars($row["requester_name"]) ?></small></td><td><?= htmlspecialchars($row["category"]) ?></td><td><?= htmlspecialchars($row["location"]) ?></td><td><span class="status <?= status_class($row["status"]) ?>" data-live-status><?= htmlspecialchars($row["status"]) ?></span></td><td data-live-assignee><?= htmlspecialchars($row["assigned_name"] ?? "Not assigned") ?></td><td><?= htmlspecialchars($row["created_at"]) ?></td><td><a class="btn small-btn" href="manage_requests.php?<?= htmlspecialchars(keep_query(["request_id" => (int)$row["id"]])) ?>">Review</a></td>
                </tr>
            <?php endwhile; else: ?><tr><td colspan="8" class="empty-cell">No requests match the selected filters.</td></tr><?php endif; ?>
            </tbody></table>
        </div>
    </section>
</main>
<dialog class="dashboard-list-dialog" id="allManagedRequestsDialog" aria-labelledby="allManagedRequestsDialogTitle">
    <div class="dashboard-dialog-header"><div><span class="eyebrow">REQUEST MANAGEMENT</span><h2 id="allManagedRequestsDialogTitle">All Submitted Requests</h2></div><button class="modal-close" type="button" data-dialog-close aria-label="Close all requests">×</button></div>
    <div class="dashboard-dialog-content">
        <?php if ($allRequests->num_rows > 0): ?><div class="mini-list">
            <?php while ($row = $allRequests->fetch_assoc()): ?>
                <a class="mini-request" href="manage_requests.php?request_id=<?= (int)$row["id"] ?>"><div><strong>#<?= (int)$row["id"] ?> · <?= htmlspecialchars($row["title"]) ?></strong><span><?= htmlspecialchars($row["requester_name"]) ?> · <?= htmlspecialchars($row["category"]) ?> · <?= htmlspecialchars($row["location"]) ?> · <?= htmlspecialchars($row["created_at"]) ?></span></div><span class="status <?= status_class($row["status"]) ?>"><?= htmlspecialchars($row["status"]) ?></span></a>
            <?php endwhile; ?>
        </div><?php else: ?><div class="empty-state compact"><strong>No requests found.</strong><span>Submitted requests will appear here.</span></div><?php endif; ?>
    </div>
    <div class="dashboard-dialog-footer"><a class="btn secondary" href="manage_requests.php">Open Manage Requests</a></div>
</dialog>
<?php if ($reviewRequest): ?>
<div class="modal-backdrop request-review-modal" id="requestReviewModal" role="dialog" aria-modal="true" aria-labelledby="requestReviewTitle">
    <div class="review-panel" tabindex="-1">
        <div class="review-header">
            <div><span class="eyebrow">REQUEST #<?= (int)$reviewRequest["id"] ?></span><h3 id="requestReviewTitle"><?= htmlspecialchars($reviewRequest["title"]) ?></h3><p class="muted">Submitted by <?= htmlspecialchars($reviewRequest["requester_name"]) ?> on <?= htmlspecialchars($reviewRequest["created_at"]) ?></p></div>
            <a class="btn secondary small-btn" data-close-request-modal href="manage_requests.php?<?= htmlspecialchars(keep_query(["request_id" => 0])) ?>">Close</a>
        </div>
        <div class="review-details">
            <div><strong>Category</strong><span><?= htmlspecialchars($reviewRequest["category"]) ?></span></div>
            <div><strong>Location</strong><span><?= htmlspecialchars($reviewRequest["location"]) ?></span></div>
            <div><strong>Current Status</strong><span class="status <?= status_class($reviewRequest["status"]) ?>"><?= htmlspecialchars($reviewRequest["status"]) ?></span></div>
            <div><strong>Last Updated</strong><span><?= htmlspecialchars($reviewRequest["updated_at"]) ?></span></div>
        </div>
        <div class="review-description"><strong>Description</strong><p><?= nl2br(htmlspecialchars($reviewRequest["description"])) ?></p></div>
        <?php if (!empty($reviewRequest["photo"])): ?><div class="evidence-photo-block"><strong>Requester photo</strong><img class="request-photo review-photo" src="uploads/<?= htmlspecialchars($reviewRequest["photo"]) ?>" alt="Request photo"></div><?php endif; ?>
        <?php if (!empty($reviewRequest["completion_photo"])): ?><div class="evidence-photo-block"><strong>Completion proof</strong><img class="request-photo review-photo" src="uploads/<?= htmlspecialchars($reviewRequest["completion_photo"]) ?>" alt="Photo proving completed work"></div><?php endif; ?>
        <form action="update_request.php" method="POST" class="review-form" data-confirm-title="Save request changes?" data-confirm-message="Please confirm that the assignment and status are correct." data-confirm-label="Save Changes">
            <?= csrf_input() ?>
            <input type="hidden" name="request_id" value="<?= (int)$reviewRequest["id"] ?>">
            <input type="hidden" name="return_to" value="manage_requests.php?<?= htmlspecialchars(keep_query(["request_id" => (int)$reviewRequest["id"]])) ?>">
            <div><label for="review-assigned-to">Assign To</label><select id="review-assigned-to" name="assigned_to"><option value="">Not assigned</option><?php foreach ($personnelRows as $p): ?><option value="<?= (int)$p["id"] ?>" <?= (int)$reviewRequest["assigned_to"] === (int)$p["id"] ? "selected" : "" ?>><?= htmlspecialchars($p["name"] . " - " . role_label($p["role"])) ?></option><?php endforeach; ?></select></div>
            <div><label for="review-status">Status</label><select id="review-status" name="status"><?php foreach (["Pending","Assigned","In Progress","Completed"] as $s): ?><option value="<?= $s ?>" <?= $reviewRequest["status"] === $s ? "selected" : "" ?>><?= $s ?></option><?php endforeach; ?></select></div>
            <div class="review-actions"><button type="submit" class="authbtn">Save Changes</button><?php if ($_SESSION["role"] === "system_admin"): ?><button type="button" class="btn danger-btn" data-confirm-delete="<?= (int)$reviewRequest["id"] ?>" data-title="<?= htmlspecialchars($reviewRequest["title"], ENT_QUOTES) ?>">Delete Request</button><?php endif; ?></div>
        </form>
    </div>
</div>
<?php endif; ?>
<div class="modal-backdrop" id="deleteModal" hidden><div class="confirm-modal" role="dialog" aria-modal="true" aria-labelledby="deleteModalTitle" aria-describedby="deleteModalDescription"><button class="modal-close" type="button" id="closeDeleteModal" aria-label="Close dialog">×</button><span class="eyebrow">PERMANENT ACTION</span><h2 id="deleteModalTitle">Delete Request?</h2><p id="deleteModalDescription">Are you sure you want to delete <strong id="deleteRequestLabel"></strong>?</p><p class="muted">This action cannot be undone and should only be used for incorrect or unwanted records.</p><form action="delete_request.php" method="POST"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, "UTF-8") ?>"><input type="hidden" name="request_id" id="deleteRequestId"><input type="hidden" name="return_to" value="manage_requests.php"><div class="modal-actions"><button type="button" class="btn secondary" id="cancelDelete">Cancel</button><button type="submit" class="btn danger-btn">Delete Request</button></div></form></div></div>
<script src="assets/script.js"></script>
<div id="firebaseLiveWatcher" data-user-id="<?= (int)$_SESSION["user_id"] ?>" data-user-role="<?= htmlspecialchars($_SESSION["role"]) ?>" data-server-time="<?= time() ?>" data-firebase-sync-available="<?= is_file(FIREBASE_SERVICE_ACCOUNT_PATH) ? "true" : "false" ?>"></div>
<script src="assets/request-polling.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.12.0/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.12.0/firebase-firestore-compat.js"></script>
<script src="assets/firebase-tracking.js"></script>
</body></html>
