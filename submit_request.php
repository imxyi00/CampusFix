<?php
require "config.php";
require_roles(["requester"]);

$maxFileSize = 5 * 1024 * 1024; // 5 MB
$allowedMimeTypes = [
    "image/jpeg" => "jpg",
    "image/png"  => "png",
    "image/webp" => "webp"
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $title = trim($_POST["title"] ?? "");
    $category = trim($_POST["category"] ?? "");
    $location = trim($_POST["location"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $photo = null;

    if ($title === "" || $category === "" || $location === "" || $description === "") {
        $error = "Please complete all required fields.";
    } elseif (!isset($_FILES["photo"]) || $_FILES["photo"]["error"] === UPLOAD_ERR_NO_FILE) {
        $error = "Photo attachment is required.";
    } elseif ($_FILES["photo"]["error"] !== UPLOAD_ERR_OK) {
        $error = "The photo upload failed. Please try again.";
    } elseif ($_FILES["photo"]["size"] > $maxFileSize) {
        $error = "Photo must be 5 MB or smaller.";
    } else {
        $tmpName = $_FILES["photo"]["tmp_name"];
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmpName);
        $imageInfo = @getimagesize($tmpName);

        if (!isset($allowedMimeTypes[$mime]) || $imageInfo === false) {
            $error = "Only valid JPG, PNG, and WEBP images are allowed.";
        } else {
            $uploadDir = __DIR__ . "/uploads/";
            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
                $error = "Unable to prepare the upload folder.";
            } else {
                $photo = bin2hex(random_bytes(16)) . "." . $allowedMimeTypes[$mime];
                if (!move_uploaded_file($tmpName, $uploadDir . $photo)) {
                    $error = "Unable to save the uploaded photo.";
                    $photo = null;
                }
            }
        }
    }

    if (empty($error)) {
        $stmt = $conn->prepare("INSERT INTO requests (requester_id, title, category, location, description, photo, status) VALUES (?, ?, ?, ?, ?, ?, 'Pending')");
        $stmt->bind_param("isssss", $_SESSION["user_id"], $title, $category, $location, $description, $photo);

        if ($stmt->execute()) {
            $newRequestId = (int)$conn->insert_id;
            firebase_sync_request_from_db($conn, $newRequestId);
            notify_request_submitted($conn, $newRequestId);
            header("Location: my_requests.php?success=" . urlencode("Request submitted successfully."));
            exit;
        } else {
            if ($photo && file_exists(__DIR__ . "/uploads/" . $photo)) {
                unlink(__DIR__ . "/uploads/" . $photo);
            }
            $error = "Unable to submit request.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Request - CampusFix</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php include "partials/nav.php"; ?>

<main class="container">
    <section class="panel form-panel">
        <span class="eyebrow">NEW REQUEST</span>
        <h1>Submit Facility / Service Request</h1>

        <?php if (!empty($error)): ?>
            <div class="alert error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <label>Request Title</label>
            <input type="text" name="title" maxlength="150" required>

            <label>Category</label>
            <select name="category" required>
                <option value="">Select category</option>
                <option>IT / Computer</option>
                <option>Air Conditioning</option>
                <option>Electrical</option>
                <option>Equipment Setup</option>
                <option>Room / Facility</option>
                <option>Other</option>
            </select>

            <label>Location</label>
            <input type="text" name="location" maxlength="150" placeholder="Example: Building A, Room 301" required>

            <label>Description</label>
            <textarea name="description" rows="5" maxlength="2000" required></textarea>

            <label>Photo Attachment <span class="required-badge">(required)</span></label>
            <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" id="photo" required>
            <small class="field-help">Required. JPG, PNG, or WEBP only. Maximum file size: 5 MB.</small>

            <div id="photoPreview"></div>

            <button type="submit" class="authbtn">Submit Request</button>
        </form>
    </section>
</main>

<script src="assets/script.js"></script>
</body>
</html>
