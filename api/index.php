<?php
// Vercel front controller: maps the URL path to a PHP file in the project root.
$root = dirname(__DIR__);
chdir($root);

if (($_SERVER["HTTP_X_FORWARDED_PROTO"] ?? "") === "https") $_SERVER["HTTPS"] = "on";

$path = trim(parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH), "/");
if ($path === "") $path = "index.php";
if (isset($_GET["__photo"])) $path = "photo.php"; // /uploads/<name> rewrite

// Only top-level *.php files are routable (no partials/, api/, config internals via subpaths).
$file = $root . "/" . $path;
if (!preg_match('/^[A-Za-z0-9_]+\.php$/', $path) || !is_file($file)) {
    http_response_code(404);
    exit("Not found");
}

$_SERVER["SCRIPT_NAME"] = "/" . $path;
$_SERVER["SCRIPT_FILENAME"] = $file;
require $file;
