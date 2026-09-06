<?php
require "config.php";
require_roles(["maintenance", "itso"]);

$stmt = $conn->prepare("SELECT r.*, requester.name AS requester_name FROM requests r JOIN users requester ON r.requester_id = requester.id WHERE r.assigned_to = ? ORDER BY r.created_at DESC");
$stmt->bind_param("i", $_SESSION["user_id"]);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
    </div>

    <?php if (isset($_GET["success"])): ?>
        <div class="alert success"><?= htmlspecialchars($_GET["success"]) ?></div>
    <?php endif; ?>
    <?php if (isset($_GET["error"])): ?>
        <div class="alert error"><?= htmlspecialchars($_GET["error"]) ?></div>
    <?php endif; ?>

    <div class="request-grid">
        <?php while ($row = $result->fetch_assoc()): ?>
            <article class="request-card">
                <div class="request-card-top">
                    <h3>#<?= $row["id"] ?> - <?= htmlspecialchars($row["title"]) ?></h3>
                    <span class="status status-<?= strtolower(str_replace(' ', '-', $row["status"])) ?>"><?= htmlspecialchars($row["status"]) ?></span>
                </div>
                <p><strong>Requester:</strong> <?= htmlspecialchars($row["requester_name"]) ?></p>
                <p><strong>Category:</strong> <?= htmlspecialchars($row["category"]) ?></p>
                <p><strong>Location:</strong> <?= htmlspecialchars($row["location"]) ?></p>
                <p><strong>Description:</strong> <?= nl2br(htmlspecialchars($row["description"])) ?></p>

                <?php if (!empty($row["photo"])): ?>
                    <img class="request-photo" src="uploads/<?= htmlspecialchars($row["photo"]) ?>" alt="Request photo">
                <?php endif; ?>

                <form action="update_request.php" method="POST" class="update-form">
                    <input type="hidden" name="request_id" value="<?= $row["id"] ?>">
                    <label>Status</label>
                    <select name="status">
                        <?php foreach (["Assigned", "In Progress", "Completed"] as $status): ?>
                            <option value="<?= $status ?>" <?= $row["status"] === $status ? "selected" : "" ?>><?= $status ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit">Update Status</button>
                </form>
            </article>
        <?php endwhile; ?>
    </div>
</main>
</body>
</html>
