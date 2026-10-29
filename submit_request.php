<?php
require "config.php";
require_roles(["requester"]);

$userId = (int)$_SESSION["user_id"];
$editId = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT) ?: 0;
$editingRequest = null;
if ($editId > 0) {
    $stmt = $conn->prepare("SELECT * FROM requests WHERE id = ? AND requester_id = ? AND status = 'Pending'");
    $stmt->bind_param("ii", $editId, $userId);
    $stmt->execute();
    $editingRequest = $stmt->get_result()->fetch_assoc();
    if (!$editingRequest) {
        http_response_code(404);
        exit("Only your pending requests can be edited.");
    }
}

$maxFileSize = 5 * 1024 * 1024; // 5 MB
$allowedMimeTypes = [
    "image/jpeg" => "jpg",
    "image/png"  => "png",
    "image/webp" => "webp"
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    require_csrf_token();

    $editId = (int)($_POST["request_id"] ?? 0);
    $editingRequest = null;
    if ($editId > 0) {
        $stmt = $conn->prepare("SELECT * FROM requests WHERE id = ? AND requester_id = ? AND status = 'Pending'");
        $stmt->bind_param("ii", $editId, $userId);
        $stmt->execute();
        $editingRequest = $stmt->get_result()->fetch_assoc();
        if (!$editingRequest) {
            http_response_code(404);
            exit("Only your pending requests can be edited.");
        }
    }

    $title = trim($_POST["title"] ?? "");
    $category = trim($_POST["category"] ?? "");
    $location = trim($_POST["location"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $photo = null;

    if ($title === "" || $category === "" || $location === "" || $description === "") {
        $error = "Please complete all required fields.";
    } elseif (!$editingRequest && (!isset($_FILES["photo"]) || $_FILES["photo"]["error"] === UPLOAD_ERR_NO_FILE)) {
        $error = "Photo attachment is required.";
    } elseif (isset($_FILES["photo"]) && $_FILES["photo"]["error"] !== UPLOAD_ERR_NO_FILE && $_FILES["photo"]["error"] !== UPLOAD_ERR_OK) {
        $error = "The photo upload failed. Please try again.";
    } elseif (isset($_FILES["photo"]) && $_FILES["photo"]["error"] === UPLOAD_ERR_OK && $_FILES["photo"]["size"] > $maxFileSize) {
        $error = "Photo must be 5 MB or smaller.";
    } elseif (isset($_FILES["photo"]) && $_FILES["photo"]["error"] === UPLOAD_ERR_OK) {
        $tmpName = $_FILES["photo"]["tmp_name"];
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmpName);
        $imageInfo = @getimagesize($tmpName);

        if (!isset($allowedMimeTypes[$mime]) || $imageInfo === false) {
            $error = "Only valid JPG, PNG, and WEBP images are allowed.";
        } else {
            $photo = bin2hex(random_bytes(16)) . "." . $allowedMimeTypes[$mime];
            if (!photo_store($tmpName, $photo, $mime)) {
                $error = "Unable to save the uploaded photo.";
                $photo = null;
            }
        }
    }

    if (empty($error)) {
        if ($editingRequest) {
            $savedPhoto = $photo ?? $editingRequest["photo"];
            $stmt = $conn->prepare("UPDATE requests SET title = ?, category = ?, location = ?, description = ?, photo = ? WHERE id = ? AND requester_id = ? AND status = 'Pending'");
            $stmt->bind_param("sssssii", $title, $category, $location, $description, $savedPhoto, $editId, $userId);
            $updated = $stmt->execute();
            $verifyStmt = $conn->prepare("SELECT status FROM requests WHERE id = ? AND requester_id = ?");
            $verifyStmt->bind_param("ii", $editId, $userId);
            $verifyStmt->execute();
            $verifyRow = $verifyStmt->get_result()->fetch_assoc();
            if ($updated && ($verifyRow["status"] ?? null) === "Pending") {
                if ($photo && $photo !== $editingRequest["photo"]) photo_delete($editingRequest["photo"]);
                firebase_sync_request_from_db($conn, $editId);
                header("Location: my_requests.php?success=" . urlencode("Request updated successfully."));
                exit;
            }
            photo_delete($photo);
            $error = "Unable to update request.";
        } else {
            $stmt = $conn->prepare("INSERT INTO requests (requester_id, title, category, location, description, photo, status) VALUES (?, ?, ?, ?, ?, ?, 'Pending')");
            $stmt->bind_param("isssss", $userId, $title, $category, $location, $description, $photo);

            if ($stmt->execute()) {
                $newRequestId = (int)$conn->insert_id;
                firebase_sync_request_from_db($conn, $newRequestId);
                notify_request_submitted($conn, $newRequestId);
                header("Location: my_requests.php?success=" . urlencode("Request submitted successfully."));
                exit;
            } else {
                photo_delete($photo);
                $error = "Unable to submit request.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="assets/cflogo.png">
    <title><?= $editingRequest ? "Edit Request - CampusFix" : "Submit Request - CampusFix" ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php include "partials/nav.php"; ?>

<main class="container">
    <div class="page-heading">
        <div>
            <span class="eyebrow"><?= $editingRequest ? "EDIT REQUEST" : "NEW REQUEST" ?></span>
            <h1><?= $editingRequest ? "Edit Request #" . (int)$editingRequest["id"] : "Submit Facility / Service Request" ?></h1>
        </div>
        <a class="btn secondary" href="my_requests.php">Back to My Requests</a>
    </div>

    <section class="panel form-panel">
        <?php if (!empty($error)): ?>
            <div class="alert error flash-notification" role="alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" data-confirm-title="<?= $editingRequest ? "Save request changes?" : "Submit this request?" ?>" data-confirm-message="Please confirm that the request details are correct before submitting." data-confirm-label="<?= $editingRequest ? "Save Changes" : "Submit Request" ?>">
            <?= csrf_input() ?>
            <input type="hidden" name="request_id" value="<?= (int)($editingRequest["id"] ?? 0) ?>">
            <label>Request Title</label>
            <input type="text" name="title" maxlength="150" value="<?= htmlspecialchars($_POST["title"] ?? $editingRequest["title"] ?? "") ?>" required>

            <label>Category</label>
            <select name="category" required>
                <option value="">Select category</option>
                <?php foreach (["IT / Computer", "Air Conditioning", "Electrical", "Equipment Setup", "Room / Facility", "Other"] as $option): ?>
                    <option value="<?= htmlspecialchars($option) ?>" <?= ($_POST["category"] ?? $editingRequest["category"] ?? "") === $option ? "selected" : "" ?>><?= htmlspecialchars($option) ?></option>
                <?php endforeach; ?>
            </select>

            <label>Location</label>
            <input type="text" name="location" maxlength="150" value="<?= htmlspecialchars($_POST["location"] ?? $editingRequest["location"] ?? "") ?>" placeholder="Example: Building A, Room 301" required>

            <label>Description</label>
            <textarea name="description" rows="5" maxlength="2000" required><?= htmlspecialchars($_POST["description"] ?? $editingRequest["description"] ?? "") ?></textarea>

            <label>Photo Attachment <span class="required-badge"><?= $editingRequest ? "(optional)" : "(required)" ?></span></label>
            <?php if ($editingRequest && !empty($editingRequest["photo"])): ?><p class="muted">Current photo:</p><img class="request-photo review-photo" src="uploads/<?= htmlspecialchars($editingRequest["photo"]) ?>" alt="Current request photo"><?php endif; ?>
            <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" id="photo" <?= $editingRequest ? "" : "required" ?>>
            <small class="field-help">JPG, PNG, or WEBP only. Maximum file size: 5 MB. Leave empty to keep the current photo.</small>

            <div id="photoPreview"></div>

            <button type="submit" class="authbtn"><?= $editingRequest ? "Save Changes" : "Submit Request" ?></button>
        </form>
    </section>
</main>

<script src="assets/script.js"></script>
</body>
</html>
