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
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Manage Requests - CampusFix</title><link rel="stylesheet" href="assets/style.css"></head>
<body>
<?php include "partials/nav.php"; ?>
<main class="container">
    <div class="page-heading"><div><span class="eyebrow">REQUEST MANAGEMENT</span><h1>Manage Requests</h1><p class="muted">Search, filter, review, assign, and update submitted requests.</p></div></div>
    <?php if (isset($_GET["success"])): ?><div class="alert success"><?= htmlspecialchars($_GET["success"]) ?></div><?php endif; ?>
    <?php if (isset($_GET["error"])): ?><div class="alert error"><?= htmlspecialchars($_GET["error"]) ?></div><?php endif; ?>

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
        <div class="panel-title-row"><div><span class="eyebrow">REQUEST LIST</span><h2>Submitted Requests</h2></div><span class="result-count"><?= $result->num_rows ?> result(s)</span></div>
        <div class="table-wrap">
            <table><thead><tr><th>ID</th><th>Request</th><th>Category</th><th>Location</th><th>Status</th><th>Assigned To</th><th>Date</th><th>Action</th></tr></thead>
            <tbody>
            <?php if ($result->num_rows > 0): while ($row = $result->fetch_assoc()): ?>
                <tr class="<?= $focusId === (int)$row["id"] ? "highlight-row" : "" ?>">
                    <td>#<?= (int)$row["id"] ?></td><td><strong><?= htmlspecialchars($row["title"]) ?></strong><small class="table-sub"><?= htmlspecialchars($row["requester_name"]) ?></small></td><td><?= htmlspecialchars($row["category"]) ?></td><td><?= htmlspecialchars($row["location"]) ?></td><td><span class="status <?= status_class($row["status"]) ?>"><?= htmlspecialchars($row["status"]) ?></span></td><td><?= htmlspecialchars($row["assigned_name"] ?? "Not assigned") ?></td><td><?= htmlspecialchars($row["created_at"]) ?></td><td><a class="btn small-btn" href="manage_requests.php?<?= htmlspecialchars(keep_query(["request_id" => (int)$row["id"]])) ?>#request-review">Review</a></td>
                </tr>
                <?php if ($focusId === (int)$row["id"]): ?>
                <tr id="request-review"><td colspan="8"><div class="review-panel">
                    <div class="review-header"><div><span class="eyebrow">REQUEST #<?= (int)$row["id"] ?></span><h3><?= htmlspecialchars($row["title"]) ?></h3><p class="muted">Submitted by <?= htmlspecialchars($row["requester_name"]) ?> on <?= htmlspecialchars($row["created_at"]) ?></p></div><a class="btn secondary small-btn" href="manage_requests.php?<?= htmlspecialchars(keep_query(["request_id" => 0])) ?>">Close</a></div>
                    <div class="review-details"><div><strong>Category</strong><span><?= htmlspecialchars($row["category"]) ?></span></div><div><strong>Location</strong><span><?= htmlspecialchars($row["location"]) ?></span></div><div><strong>Current Status</strong><span class="status <?= status_class($row["status"]) ?>"><?= htmlspecialchars($row["status"]) ?></span></div><div><strong>Last Updated</strong><span><?= htmlspecialchars($row["updated_at"]) ?></span></div></div>
                    <div class="review-description"><strong>Description</strong><p><?= nl2br(htmlspecialchars($row["description"])) ?></p></div>
                    <?php if (!empty($row["photo"])): ?><img class="request-photo review-photo" src="uploads/<?= htmlspecialchars($row["photo"]) ?>" alt="Request photo"><?php endif; ?>
                    <form action="update_request.php" method="POST" class="review-form"><input type="hidden" name="request_id" value="<?= (int)$row["id"] ?>"><input type="hidden" name="return_to" value="manage_requests.php?<?= htmlspecialchars(keep_query(["request_id" => (int)$row["id"]])) ?>#request-review"><div><label>Assign To</label><select name="assigned_to"><option value="">Not assigned</option><?php foreach ($personnelRows as $p): ?><option value="<?= (int)$p["id"] ?>" <?= (int)$row["assigned_to"] === (int)$p["id"] ? "selected" : "" ?>><?= htmlspecialchars($p["name"] . " - " . role_label($p["role"])) ?></option><?php endforeach; ?></select></div><div><label>Status</label><select name="status"><?php foreach (["Pending","Assigned","In Progress","Completed"] as $s): ?><option value="<?= $s ?>" <?= $row["status"] === $s ? "selected" : "" ?>><?= $s ?></option><?php endforeach; ?></select></div><div class="review-actions"><button type="submit" class="authbtn">Save Changes</button><?php if ($_SESSION["role"] === "system_admin"): ?><button type="button" class="btn danger-btn" data-confirm-delete="<?= (int)$row["id"] ?>" data-title="<?= htmlspecialchars($row["title"], ENT_QUOTES) ?>">Delete Request</button><?php endif; ?></div></form>
                </div></td></tr>
                <?php endif; ?>
            <?php endwhile; else: ?><tr><td colspan="8" class="empty-cell">No requests match the selected filters.</td></tr><?php endif; ?>
            </tbody></table>
        </div>
    </section>
</main>
<div class="modal-backdrop" id="deleteModal" hidden><div class="confirm-modal"><button class="modal-close" type="button" id="closeDeleteModal">×</button><span class="eyebrow">PERMANENT ACTION</span><h2>Delete Request?</h2><p>Are you sure you want to delete <strong id="deleteRequestLabel"></strong>?</p><p class="muted">This action cannot be undone and should only be used for incorrect or unwanted records.</p><form action="delete_request.php" method="POST"><input type="hidden" name="request_id" id="deleteRequestId"><input type="hidden" name="return_to" value="manage_requests.php"><div class="modal-actions"><button type="button" class="btn secondary" id="cancelDelete">Cancel</button><button type="submit" class="btn danger-btn">Delete Request</button></div></form></div></div>
<script src="assets/script.js"></script>
<div id="firebaseLiveWatcher" data-user-id="<?= (int)$_SESSION["user_id"] ?>" data-user-role="<?= htmlspecialchars($_SESSION["role"]) ?>"></div>
<script src="https://www.gstatic.com/firebasejs/10.12.0/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.12.0/firebase-firestore-compat.js"></script>
<script src="assets/firebase-tracking.js"></script>
</body></html>
