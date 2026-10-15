<?php
require "config.php";
require_roles(["requester"]);

$userId = (int)$_SESSION["user_id"];
$statusFilter = $_GET["status"] ?? "";
$focusId = (int)($_GET["request_id"] ?? 0);
$where = "r.requester_id = ?";
$params = [$userId]; $types = "i";
if ($statusFilter === "active") $where .= " AND r.status IN ('Assigned','In Progress')";
elseif (in_array($statusFilter, ["Pending","Assigned","In Progress","Completed"], true)) { $where .= " AND r.status = ?"; $params[] = $statusFilter; $types .= "s"; }
if ($focusId > 0) { $where .= " AND r.id = ?"; $params[] = $focusId; $types .= "i"; }

$stmt = $conn->prepare("SELECT r.*, u.name AS assigned_name FROM requests r LEFT JOIN users u ON r.assigned_to = u.id WHERE {$where} ORDER BY r.created_at DESC");
$stmt->bind_param($types, ...$params); $stmt->execute(); $result = $stmt->get_result();
function status_class(string $status): string { return "status-" . strtolower(str_replace(" ", "-", $status)); }
function active_step(string $current, string $step): bool { $o=["Pending"=>1,"Assigned"=>2,"In Progress"=>3,"Completed"=>4]; return ($o[$current]??1)>=($o[$step]??1); }
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>My Requests - CampusFix</title><link rel="stylesheet" href="assets/style.css"></head>
<body><?php include "partials/nav.php"; ?><main class="container">
<div class="page-heading"><div><span class="eyebrow">REQUEST HISTORY</span><h1>My Requests</h1><p class="muted">View the requests submitted from your account.</p></div><a class="btn" href="submit_request.php">+ New Request</a></div>
<?php if (isset($_GET["success"])): ?><div class="alert success"><?= htmlspecialchars($_GET["success"]) ?></div><?php endif; ?>
<div class="request-tabs"><a class="<?= $statusFilter===""?"active":"" ?>" href="my_requests.php">All</a><a class="<?= $statusFilter==="Pending"?"active":"" ?>" href="my_requests.php?status=Pending">Pending</a><a class="<?= $statusFilter==="active"?"active":"" ?>" href="my_requests.php?status=active">Active</a><a class="<?= $statusFilter==="Completed"?"active":"" ?>" href="my_requests.php?status=Completed">Completed</a></div>
<div class="table-wrap"><table><thead><tr><th>ID</th><th>Title</th><th>Category</th><th>Location</th><th>Status</th><th>Assigned To</th><th>Date</th><th>Action</th></tr></thead><tbody>
<?php if ($result->num_rows > 0): while ($row=$result->fetch_assoc()): ?><tr class="<?= $focusId===(int)$row["id"]?"highlight-row":"" ?>"><td>#<?= (int)$row["id"] ?></td><td><strong><?= htmlspecialchars($row["title"]) ?></strong></td><td><?= htmlspecialchars($row["category"]) ?></td><td><?= htmlspecialchars($row["location"]) ?></td><td><span class="status <?= status_class($row["status"]) ?>"><?= htmlspecialchars($row["status"]) ?></span></td><td><?= htmlspecialchars($row["assigned_name"] ?? "Not assigned") ?></td><td><?= htmlspecialchars($row["created_at"]) ?></td><td><a class="btn small-btn" href="tracking.php?id=<?= (int)$row["id"] ?>">View</a></td></tr>
<?php if ($focusId===(int)$row["id"]): ?><tr id="request-details"><td colspan="8"><div class="review-panel"><div class="review-header"><div><span class="eyebrow">REQUEST #<?= (int)$row["id"] ?></span><h3><?= htmlspecialchars($row["title"]) ?></h3><p class="muted">Submitted <?= htmlspecialchars($row["created_at"]) ?> · Last updated <?= htmlspecialchars($row["updated_at"]) ?></p></div><a class="btn secondary small-btn" href="my_requests.php?<?= http_build_query(["status"=>$statusFilter]) ?>">Close</a></div><div class="review-details"><div><strong>Category</strong><span><?= htmlspecialchars($row["category"]) ?></span></div><div><strong>Location</strong><span><?= htmlspecialchars($row["location"]) ?></span></div><div><strong>Assigned To</strong><span><?= htmlspecialchars($row["assigned_name"] ?? "Not assigned") ?></span></div><div><strong>Status</strong><span class="status <?= status_class($row["status"]) ?>"><?= htmlspecialchars($row["status"]) ?></span></div></div><div class="review-description"><strong>Description</strong><p><?= nl2br(htmlspecialchars($row["description"])) ?></p></div><?php if (!empty($row["photo"])): ?><img class="request-photo review-photo" src="uploads/<?= htmlspecialchars($row["photo"]) ?>" alt="Request photo"><?php endif; ?><div class="progress-steps request-detail-progress"><?php foreach (["Pending"=>"Submitted","Assigned"=>"Reviewed / Assigned","In Progress"=>"In Progress","Completed"=>"Completed"] as $step=>$label): ?><div class="progress-step <?= active_step($row["status"],$step)?"done":"" ?> <?= $row["status"]===$step?"current":"" ?>"><span><?= active_step($row["status"],$step)?"✓":"○" ?></span><div><strong><?= $label ?></strong></div></div><?php endforeach; ?></div></div></td></tr><?php endif; ?>
<?php endwhile; else: ?><tr><td colspan="8" class="empty-cell">No requests found for this filter.</td></tr><?php endif; ?></tbody></table></div>
</main>
<div id="firebaseLiveWatcher" data-requester-id="<?= (int)$userId ?>"></div>
<script src="https://www.gstatic.com/firebasejs/10.12.0/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.12.0/firebase-firestore-compat.js"></script>
<script src="assets/firebase-tracking.js"></script>
</body></html>
