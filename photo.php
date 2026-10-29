<?php
// Serves a request photo stored in MySQL. Reached via vercel.json rewrite /uploads/<name> -> photo.php?f=<name>.
require __DIR__ . "/config.php";

$name = $_GET["f"] ?? "";
if (!preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $name)) {
    http_response_code(404);
    exit;
}

$stmt = $conn->prepare("SELECT mime, data FROM photos WHERE name = ?");
$stmt->bind_param("s", $name);
$stmt->execute();
$photo = $stmt->get_result()->fetch_assoc();
if (!$photo) {
    http_response_code(404);
    exit;
}

header("Content-Type: " . $photo["mime"]);
header("Cache-Control: public, max-age=31536000, immutable");
header("X-Content-Type-Options: nosniff");
echo $photo["data"];
