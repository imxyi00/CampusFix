<?php
require "config.php";
require_login();

$role = $_SESSION["role"] ?? "requester";
$roleTitle = role_label($role);

switch ($role) {
    case "admin_staff":
        $primaryActionUrl = "manage_requests.php";
        $primaryActionLabel = "Manage Requests";
        $roleDescription = "Guidance for Administrative Staff on triaging requests, assigning personnel, and generating reports.";
        $faqs = [
            [
                "question" => "How do I review and assign incoming facility requests?",
                "answer" => 'Open <a href="manage_requests.php">Manage Requests</a>, click <strong>Review</strong> on any Pending request, select the appropriate Maintenance or ITSO personnel under <strong>Assign To</strong>, set the status to <strong>Assigned</strong>, and click <strong>Save Changes</strong>.'
            ],
            [
                "question" => "How do I filter or search through campus requests?",
                "answer" => 'In <a href="manage_requests.php">Manage Requests</a>, use the search bar and status/category/assignee dropdowns to narrow requests (for example, filtering by <strong>Pending</strong> or <strong>Unassigned</strong> to prioritize new submissions).'
            ],
            [
                "question" => "How do I generate analytics and export CSV reports?",
                "answer" => 'Go to <a href="reports.php">Reports</a>, select your start and end dates, and click <strong>Apply Filter</strong> to view request volume, category breakdown, and resolution metrics. Click <strong>Export CSV</strong> to download the filtered dataset.'
            ],
            [
                "question" => "What if a staff member needs a new account or role change?",
                "answer" => 'Only a <strong>System Administrator</strong> can create privileged accounts or revoke user access via Manage Users. Contact your System Administrator for account provisioning.'
            ]
        ];
        break;

    case "maintenance":
        $primaryActionUrl = "assigned_requests.php";
        $primaryActionLabel = "Assigned Requests";
        $roleDescription = "Guidance for Maintenance Personnel on handling physical facility, air conditioning, electrical, and room repairs.";
        $faqs = [
            [
                "question" => "Where can I see the maintenance tasks assigned to me?",
                "answer" => 'Open <a href="assigned_requests.php">Assigned Requests</a> to view all facility, electrical, air conditioning, and room repair requests currently assigned to your account.'
            ],
            [
                "question" => "How do I update the status of a repair I am working on?",
                "answer" => 'In <a href="assigned_requests.php">Assigned Requests</a>, open <strong>Details &amp; Update</strong>, change the status to <strong>In Progress</strong> when work begins, and attach a completion photo before choosing <strong>Completed</strong>. Changes notify the requester in real time.'
            ],
            [
                "question" => "What if a request assigned to me belongs to ITSO instead?",
                "answer" => 'Coordinate with Administrative Staff or a System Administrator so they can reassign the ticket to the appropriate ITSO personnel in Manage Requests.'
            ],
            [
                "question" => "How do I verify the exact location and issue details before dispatch?",
                "answer" => 'Each card in <a href="assigned_requests.php">Assigned Requests</a> displays the building/room location, full description, and the required photo attachment uploaded by the requester.'
            ]
        ];
        break;

    case "itso":
        $primaryActionUrl = "assigned_requests.php";
        $primaryActionLabel = "Assigned Requests";
        $roleDescription = "Guidance for ITSO Personnel on resolving IT, computer, network, and audiovisual equipment setup requests.";
        $faqs = [
            [
                "question" => "Where do I view IT and equipment requests assigned to me?",
                "answer" => 'Go to <a href="assigned_requests.php">Assigned Requests</a> to see all IT / Computer and Equipment Setup tickets routed to you.'
            ],
            [
                "question" => "How do I record progress on an IT support ticket?",
                "answer" => 'In <a href="assigned_requests.php">Assigned Requests</a>, open <strong>Details &amp; Update</strong>, set the status to <strong>In Progress</strong> while diagnosing or configuring equipment, then attach a completion photo before marking the job <strong>Completed</strong>.'
            ],
            [
                "question" => "Can I view the photo attached by the requester?",
                "answer" => 'Yes. Open <strong>Details &amp; Update</strong> on an assigned request to view the requester photo and full description. Attach a completion photo there before setting the request to <strong>Completed</strong>.'
            ],
            [
                "question" => "What if a ticket requires electrical or facility maintenance first?",
                "answer" => 'Notify Administrative Staff or the System Administrator with the Request ID so they can reassign or coordinate with Maintenance Personnel.'
            ]
        ];
        break;

    case "system_admin":
        $primaryActionUrl = "manage_users.php";
        $primaryActionLabel = "Manage Users";
        $roleDescription = "Guidance for System Administrators on user provisioning, access revocation, request oversight, and system reports.";
        $faqs = [
            [
                "question" => "How do I create staff accounts or revoke user access?",
                "answer" => 'Open <a href="manage_users.php">Manage Users</a> to create Administrative Staff, Maintenance, ITSO, or System Administrator accounts. To remove an account, click <strong>Revoke Access</strong> beside the user in the Registered Accounts table.'
            ],
            [
                "question" => "How do I oversee or delete invalid service requests?",
                "answer" => 'In <a href="manage_requests.php">Manage Requests</a>, click <strong>Review</strong> on any request to assign personnel, update status, or use <strong>Delete Request</strong> to permanently remove duplicate or invalid records.'
            ],
            [
                "question" => "How do I audit system-wide performance and export reports?",
                "answer" => 'Visit <a href="reports.php">Reports</a> to inspect request volume trends, category distribution, average resolution time, and personnel workload, or export filtered records to CSV.'
            ],
            [
                "question" => "What password security rules are enforced across accounts?",
                "answer" => 'All newly created accounts must use passwords of at least 8 characters containing an uppercase letter, lowercase letter, number, and special character.'
            ]
        ];
        break;

    default: // requester
        $primaryActionUrl = "submit_request.php";
        $primaryActionLabel = "Submit a Request";
        $roleDescription = "Guidance for Requesters on submitting campus facility or IT requests and tracking live repair progress.";
        $faqs = [
            [
                "question" => "How do I submit a facility or service request?",
                "answer" => 'Choose <a href="submit_request.php">Submit Request</a> and enter a clear title, category, location, description, and the required photo attachment of the issue. You can check your submission in <a href="my_requests.php">My Requests</a> afterward.'
            ],
            [
                "question" => "How can I track my request in real time?",
                "answer" => 'Open <a href="my_requests.php">My Requests</a> and select <strong>View</strong> beside a request to see its live status, 5-stage progress timeline, the original report photo, and any completion evidence.'
            ],
            [
                "question" => "What do the different request statuses mean?",
                "answer" => '<strong>Pending</strong> requests can still be edited or deleted by their requester. <strong>Assigned</strong> means routed to Maintenance or ITSO. <strong>In Progress</strong> means repair work is underway. <strong>Completed</strong> means resolved and includes a completion photo from assigned personnel.'
            ],
            [
                "question" => "How can I follow up on an urgent request?",
                "answer" => 'Contact campus Administrative Staff through your usual support channel and provide your Request ID, which is listed in <a href="my_requests.php">My Requests</a>.'
            ]
        ];
        break;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="assets/cflogo.png">
    <title>Help &amp; Support - CampusFix</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php include "partials/nav.php"; ?>
<main class="container support-page">
    <div class="page-heading">
        <div>
            <span class="eyebrow">CAMPUSFIX SUPPORT · <?= htmlspecialchars(strtoupper($roleTitle), ENT_QUOTES, "UTF-8") ?></span>
            <h1>Help &amp; Support</h1>
            <p class="muted"><?= htmlspecialchars($roleDescription, ENT_QUOTES, "UTF-8") ?></p>
        </div>
        <a class="btn" href="<?= htmlspecialchars($primaryActionUrl, ENT_QUOTES, "UTF-8") ?>"><?= htmlspecialchars($primaryActionLabel, ENT_QUOTES, "UTF-8") ?></a>
    </div>

    <section class="panel faq-panel" aria-label="Frequently asked questions">
        <div class="panel-title-row">
            <div><span class="eyebrow"><?= htmlspecialchars(strtoupper($roleTitle), ENT_QUOTES, "UTF-8") ?> GUIDE</span><h2>Frequently Asked Questions</h2></div>
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
