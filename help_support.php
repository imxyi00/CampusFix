<?php
require "config.php";
require_login();

$role = $_SESSION["role"] ?? "requester";
$submissionUrl = $role === "requester" ? "submit_request.php" : "manage_requests.php";
$trackingUrl = $role === "requester" ? "my_requests.php" : (
    in_array($role, ["maintenance", "itso"], true) ? "assigned_requests.php" : "manage_requests.php"
);
$faqs = [
    [
        "question" => "How do I submit a request?",
        "answer" => 'Choose <a href="' . $submissionUrl . '">Submit Request</a> and enter a clear title, category, location, and description. Add a photo if it helps explain the issue. You can check your submission in My Requests afterward.'
    ],
    [
        "question" => "How can I track my request?",
        "answer" => $role === "requester"
            ? 'Open <a href="my_requests.php">My Requests</a> and select <strong>View</strong> beside a request to see its status and progress timeline.'
            : 'Open <a href="' . $trackingUrl . '">your request list</a> to review requests and their latest status. Requesters can use the View action in My Requests to see the full progress timeline.'
    ],
    [
        "question" => "What do the different request statuses mean?",
        "answer" => '<strong>Pending</strong> means the request is waiting for review. <strong>Assigned</strong> means it has been assigned to maintenance or ITSO personnel. <strong>In Progress</strong> means work is underway. <strong>Completed</strong> means the request has been resolved.'
    ],
    [
        "question" => "How can I get further assistance?",
        "answer" => $role === "requester"
            ? 'For help with an existing issue, contact your campus administrative staff through your usual campus support channel and include the request ID. You can find the ID and current status in <a href="my_requests.php">My Requests</a>.'
            : 'For an issue you cannot resolve in CampusFix, contact your system administrator through your usual campus support channel and include the request ID.'
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help &amp; Support - CampusFix</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php include "partials/nav.php"; ?>
<main class="container support-page">
    <div class="page-heading">
        <div>
            <span class="eyebrow">CAMPUSFIX SUPPORT</span>
            <h1>Help &amp; Support</h1>
            <p class="muted">Find answers to common questions about submitting and following service requests.</p>
        </div>
        <a class="btn" href="<?= htmlspecialchars($submissionUrl, ENT_QUOTES, "UTF-8") ?>"><?= $role === "requester" ? "Submit a Request" : "Manage Requests" ?></a>
    </div>

    <section class="panel faq-panel" aria-label="Frequently asked questions">
        <div class="panel-title-row">
            <div><span class="eyebrow">QUICK ANSWERS</span><h2>Frequently Asked Questions</h2></div>
        </div>
        <div class="faq-list">
            <?php foreach ($faqs as $index => $faq): ?>
                <article class="faq-item">
                    <h3>
                        <button class="faq-question" type="button" aria-expanded="false" aria-controls="faq-answer-<?= $index ?>">
                            <span><?= htmlspecialchars($faq["question"], ENT_QUOTES, "UTF-8") ?></span>
                            <span class="faq-toggle" aria-hidden="true">+</span>
                        </button>
                    </h3>
                    <div class="faq-answer" id="faq-answer-<?= $index ?>" hidden>
                        <p><?= $faq["answer"] ?></p>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
</main>
<script src="assets/script.js"></script>
</body>
</html>
