<?php
require "config.php";
require_roles(["admin_staff", "system_admin"]);

function report_escape(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
}

function report_date_is_valid(string $value): bool {
    $date = DateTimeImmutable::createFromFormat("!Y-m-d", $value);
    $errors = DateTimeImmutable::getLastErrors();
    return $date !== false
        && ($errors === false || ($errors["warning_count"] === 0 && $errors["error_count"] === 0))
        && $date->format("Y-m-d") === $value;
}

function csv_safe_value(mixed $value): string {
    $value = (string)$value;
    if (preg_match('/^[\x00-\x20]*[=+\-@]/', $value)) {
        return "'" . $value;
    }
    return $value;
}

$today = new DateTimeImmutable("today");
$startDate = $_GET["start_date"] ?? $today->format("Y-01-01");
$endDate = $_GET["end_date"] ?? $today->format("Y-m-d");
$dateError = "";

if (!is_string($startDate) || !report_date_is_valid($startDate)
    || !is_string($endDate) || !report_date_is_valid($endDate)) {
    $dateError = "Enter valid start and end dates.";
} elseif ($startDate < "1000-01-01" || $endDate > $today->format("Y-m-d")) {
    $dateError = "Choose dates supported by the database, up to today.";
} elseif ($startDate > $endDate) {
    $dateError = "The start date must be on or before the end date.";
} elseif ((new DateTimeImmutable($startDate))->diff(new DateTimeImmutable($endDate))->days > 3660) {
    $dateError = "The selected date range cannot exceed 10 years.";
}

if ($dateError !== "") {
    http_response_code(400);
    $startDate = $today->format("Y-01-01");
    $endDate = $today->format("Y-m-d");
}
$endExclusive = (new DateTimeImmutable($endDate))->modify("+1 day")->format("Y-m-d");

$summaryStmt = $conn->prepare(
    "SELECT COUNT(r.id) AS total,
            COALESCE(SUM(r.status = 'Completed'), 0) AS completed,
            AVG(CASE WHEN r.status = 'Completed'
                THEN TIMESTAMPDIFF(SECOND, r.created_at, COALESCE(resolved.completed_at, r.updated_at)) / 3600
                ELSE NULL END) AS average_resolution_hours
     FROM requests r
     LEFT JOIN request_progress resolved
       ON resolved.request_id = r.id AND resolved.stage = 'resolved'
     WHERE r.created_at >= ? AND r.created_at < ?"
);
$summaryStmt->bind_param("ss", $startDate, $endExclusive);
$summaryStmt->execute();
$summary = $summaryStmt->get_result()->fetch_assoc();
$totalRequests = (int)$summary["total"];
$completedRequests = (int)$summary["completed"];
$resolutionRate = $totalRequests > 0 ? ($completedRequests / $totalRequests) * 100 : 0;

$months = [];
$periodStart = new DateTimeImmutable(substr($startDate, 0, 7) . "-01");
$periodEnd = new DateTimeImmutable(substr($endDate, 0, 7) . "-01");
for ($month = $periodStart; $month <= $periodEnd; $month = $month->modify("+1 month")) {
    $months[$month->format("Y-m")] = 0;
}
$monthlyStmt = $conn->prepare(
    "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month_key, COUNT(*) AS total
     FROM requests
     WHERE created_at >= ? AND created_at < ?
     GROUP BY DATE_FORMAT(created_at, '%Y-%m')
     ORDER BY month_key"
);
$monthlyStmt->bind_param("ss", $startDate, $endExclusive);
$monthlyStmt->execute();
$monthlyResult = $monthlyStmt->get_result();
while ($row = $monthlyResult->fetch_assoc()) {
    if (array_key_exists($row["month_key"], $months)) {
        $months[$row["month_key"]] = (int)$row["total"];
    }
}

$categoryStmt = $conn->prepare(
    "SELECT category, COUNT(*) AS total
     FROM requests
     WHERE created_at >= ? AND created_at < ?
     GROUP BY category
     ORDER BY total DESC, category ASC"
);
$categoryStmt->bind_param("ss", $startDate, $endExclusive);
$categoryStmt->execute();
$categoryResult = $categoryStmt->get_result();
$categoryRows = [];
while ($row = $categoryResult->fetch_assoc()) {
    $categoryRows[] = ["category" => $row["category"], "total" => (int)$row["total"]];
}

$personnelStmt = $conn->prepare(
    "SELECT u.name, u.role,
            COUNT(r.id) AS assigned_total,
            COALESCE(SUM(r.status = 'Completed'), 0) AS completed_total,
            AVG(CASE WHEN r.status = 'Completed'
                THEN TIMESTAMPDIFF(SECOND, r.created_at, COALESCE(resolved.completed_at, r.updated_at)) / 3600
                ELSE NULL END) AS average_resolution_hours
     FROM users u
     LEFT JOIN requests r
       ON r.assigned_to = u.id
      AND r.created_at >= ?
      AND r.created_at < ?
     LEFT JOIN request_progress resolved
       ON resolved.request_id = r.id AND resolved.stage = 'resolved'
     WHERE u.role IN ('maintenance', 'itso')
     GROUP BY u.id, u.name, u.role
     ORDER BY completed_total DESC, assigned_total DESC, u.name ASC"
);
$personnelStmt->bind_param("ss", $startDate, $endExclusive);
$personnelStmt->execute();
$personnelResult = $personnelStmt->get_result();
$personnelRows = [];
while ($row = $personnelResult->fetch_assoc()) {
    $personnelRows[] = $row;
}

$csvExport = ($_GET["export"] ?? "") === "csv";
if ($csvExport && $dateError === "") {
    $exportStmt = $conn->prepare(
        "SELECT r.id, r.category, r.status, r.created_at, r.updated_at,
                assigned.name AS assigned_name,
                CASE WHEN r.status = 'Completed'
                    THEN COALESCE(resolved.completed_at, r.updated_at)
                    ELSE NULL END AS resolved_at,
                CASE WHEN r.status = 'Completed'
                    THEN TIMESTAMPDIFF(SECOND, r.created_at, COALESCE(resolved.completed_at, r.updated_at)) / 3600
                    ELSE NULL END AS resolution_hours
         FROM requests r
         LEFT JOIN users assigned ON assigned.id = r.assigned_to
         LEFT JOIN request_progress resolved
           ON resolved.request_id = r.id AND resolved.stage = 'resolved'
         WHERE r.created_at >= ? AND r.created_at < ?
         ORDER BY r.created_at, r.id"
    );
    $exportStmt->bind_param("ss", $startDate, $endExclusive);
    $exportStmt->execute();
    $exportResult = $exportStmt->get_result();
    $filename = "campusfix-report-" . $startDate . "-to-" . $endDate . ".csv";
    header("Content-Type: text/csv; charset=utf-8");
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header("X-Content-Type-Options: nosniff");
    $output = fopen("php://output", "wb");
    if ($output === false) {
        throw new RuntimeException("Unable to open CSV output stream.");
    }
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, ["Request ID", "Category", "Status", "Submitted At", "Resolved At", "Assigned Personnel", "Resolution Hours"]);
    while ($row = $exportResult->fetch_assoc()) {
        fputcsv($output, array_map("csv_safe_value", [
            $row["id"],
            $row["category"],
            $row["status"],
            $row["created_at"],
            $row["resolved_at"] ?? "",
            $row["assigned_name"] ?? "Unassigned",
            $row["resolution_hours"] === null ? "" : number_format((float)$row["resolution_hours"], 2, ".", "")
        ]));
    }
    fclose($output);
    exit;
}

$monthLabels = array_map(
    static fn(string $month): string => (new DateTimeImmutable($month . "-01"))->format("M Y"),
    array_keys($months)
);
$monthValues = array_values($months);
$categoryColors = ["#e6a900", "#3c9667", "#4589d3", "#db514a", "#8366b2", "#e47a42", "#38a7a1", "#77736b"];
$categoryChartColors = [];
foreach ($categoryRows as $index => $_row) {
    $categoryChartColors[] = $categoryColors[$index % count($categoryColors)];
}
$chartJsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="assets/cflogo.png">
    <title>Reports &amp; Analytics - CampusFix</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php include "partials/nav.php"; ?>
<main class="container reports-page">
    <div class="page-heading">
        <div>
            <span class="eyebrow">OPERATIONS INSIGHTS</span>
            <h1>Reports &amp; Analytics</h1>
            <p class="muted">Review request volume, resolution, categories, and maintenance workload.</p>
        </div>
        <a class="btn" href="reports.php?<?= http_build_query(["start_date" => $startDate, "end_date" => $endDate, "export" => "csv"]) ?>">Export CSV</a>
    </div>

    <?php if ($dateError !== ""): ?><div class="alert error"><?= report_escape($dateError) ?> Showing the current-year date range.</div><?php endif; ?>

    <section class="panel report-filter-panel">
        <form method="GET" class="report-filter-form">
            <div><label for="start-date">Start date</label><input id="start-date" type="date" name="start_date" value="<?= report_escape($startDate) ?>" required></div>
            <div><label for="end-date">End date</label><input id="end-date" type="date" name="end_date" value="<?= report_escape($endDate) ?>" required></div>
            <div class="report-filter-actions"><button class="authbtn" type="submit">Apply Filters</button><a class="btn secondary" href="reports.php">Reset</a></div>
        </form>
        <p class="muted report-filter-note">Reports include requests submitted from <?= report_escape($startDate) ?> through <?= report_escape($endDate) ?>.</p>
    </section>

    <section class="stats-grid report-stats">
        <article class="stat-card"><span>Resolution Rate</span><strong><?= number_format($resolutionRate, 1) ?>%</strong><small><?= $completedRequests ?> of <?= $totalRequests ?> requests completed</small></article>
        <article class="stat-card"><span>Average Resolution Time</span><strong><?= $summary["average_resolution_hours"] === null ? "—" : number_format((float)$summary["average_resolution_hours"], 1) . " hrs" ?></strong><small>Completed requests; measured to the resolved-stage timestamp when recorded</small></article>
        <article class="stat-card"><span>Total Issues Reported</span><strong><?= $totalRequests ?></strong><small>Requests submitted in the selected period</small></article>
        <article class="stat-card"><span>Average Satisfaction Rating</span><strong>Not available</strong><small>Ratings are not stored in the current database</small></article>
    </section>

    <section class="report-chart-grid">
        <article class="panel report-chart-panel">
            <div class="panel-title-row"><div><span class="eyebrow">REQUEST VOLUME</span><h2>Monthly Requests</h2></div></div>
            <?php if ($totalRequests > 0): ?>
                <canvas class="report-line-chart" data-labels="<?= report_escape((string)json_encode($monthLabels, $chartJsonFlags)) ?>" data-values="<?= report_escape((string)json_encode($monthValues, $chartJsonFlags)) ?>" role="img" aria-label="Line chart of monthly maintenance request volume"></canvas>
            <?php else: ?>
                <div class="empty-state compact"><strong>No request data in this date range.</strong></div>
            <?php endif; ?>
        </article>
        <article class="panel report-chart-panel">
            <div class="panel-title-row"><div><span class="eyebrow">REQUEST BREAKDOWN</span><h2>Requests by Category</h2></div></div>
            <?php if ($categoryRows): ?>
                <div class="report-doughnut-layout">
                    <canvas class="report-doughnut-chart" data-values="<?= report_escape((string)json_encode(array_column($categoryRows, "total"), $chartJsonFlags)) ?>" data-colors="<?= report_escape((string)json_encode($categoryChartColors, $chartJsonFlags)) ?>" role="img" aria-label="Doughnut chart of requests by maintenance category"></canvas>
                    <ul class="report-chart-legend">
                        <?php foreach ($categoryRows as $index => $row): ?>
                            <li><span class="legend-swatch" style="--legend-color: <?= $categoryChartColors[$index] ?>"></span><span><?= report_escape($row["category"]) ?></span><strong><?= (int)$row["total"] ?></strong></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php else: ?>
                <div class="empty-state compact"><strong>No category data in this date range.</strong></div>
            <?php endif; ?>
        </article>
    </section>

    <section class="panel report-table-panel">
        <div class="panel-title-row"><div><span class="eyebrow">TEAM PERFORMANCE</span><h2>Maintenance Personnel</h2></div><span class="result-count"><?= count($personnelRows) ?> personnel</span></div>
        <p class="muted report-rating-note">Satisfaction ratings are unavailable because the database does not currently store ratings. Older requests without a resolved-stage timestamp use their last-updated time.</p>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Personnel</th><th>Assigned Requests</th><th>Completed Requests</th><th>Average Resolution Time</th><th>Rating</th></tr></thead>
                <tbody>
                <?php if ($personnelRows): foreach ($personnelRows as $person): ?>
                    <tr>
                        <td><strong><?= report_escape($person["name"]) ?></strong><small class="table-sub"><?= report_escape(role_label($person["role"])) ?></small></td>
                        <td><?= (int)$person["assigned_total"] ?></td>
                        <td><?= (int)$person["completed_total"] ?></td>
                        <td><?= $person["average_resolution_hours"] === null ? "—" : number_format((float)$person["average_resolution_hours"], 1) . " hrs" ?></td>
                        <td>Not available</td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="5" class="empty-cell">No maintenance personnel accounts found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
<script src="assets/script.js"></script>
</body>
</html>
