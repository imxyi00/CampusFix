<?php
require "config.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit;
}

require_csrf_token();
session_unset();
session_destroy();
header("Location: index.php?success=" . urlencode("You have been logged out successfully."));
exit;
?>