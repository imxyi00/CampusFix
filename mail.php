<?php
/**
 * CampusFix Email Service (Gmail App Password SMTP + Local Dev Fallback)
 * Zero third-party dependencies — uses native PHP TLS sockets over smtp.gmail.com:465.
 *
 * HOW TO ENABLE REAL GMAIL SENDING (100% FREE):
 * 1. Go to your Google Account -> Security -> Enable 2-Step Verification
 * 2. Search "App Passwords" in Google Account settings
 * 3. Create an App Password (e.g. "CampusFix") and copy the 16-character code
 * 4. Set CAMPUSFIX_SMTP_EMAIL and CAMPUSFIX_SMTP_APP_PASSWORD in the server environment
 */

define("GMAIL_SMTP_EMAIL", getenv("CAMPUSFIX_SMTP_EMAIL") ?: "");
define("GMAIL_SMTP_APP_PASSWORD", getenv("CAMPUSFIX_SMTP_APP_PASSWORD") ?: "");
define("GMAIL_FROM_NAME", "CampusFix · T.I.P. QC");

/**
 * Ensure password_resets table exists in MySQL.
 */
function ensure_password_resets_table(mysqli $conn): void {
    $conn->query(
        "CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token_hash CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            used TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_token_hash (token_hash),
            CONSTRAINT fk_reset_user
                FOREIGN KEY (user_id) REFERENCES users(id)
                ON DELETE CASCADE
        )"
    );
}

/**
 * Build base URL for links inside emails.
 */
function campusfix_base_url(): string {
    $https = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off")
        || ((int)($_SERVER["SERVER_PORT"] ?? 80) === 443);
    $scheme = $https ? "https" : "http";
    $host = $_SERVER["HTTP_HOST"] ?? "localhost";
    $scriptDir = rtrim(str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? "/CampusFix/index.php")), "/");
    return "{$scheme}://{$host}{$scriptDir}";
}

/**
 * Read multiline SMTP response from socket and verify expected status code.
 */
function smtp_expect($socket, array $expectedCodes): bool {
    $response = "";
    while ($line = fgets($socket, 515)) {
        $response .= $line;
        if (isset($line[3]) && $line[3] === " ") {
            break;
        }
    }
    $code = (int)substr($response, 0, 3);
    if (!in_array($code, $expectedCodes, true)) {
        error_log("CampusFix SMTP error (expected " . implode("/", $expectedCodes) . "): " . trim($response));
        return false;
    }
    return true;
}

/**
 * Send HTML email via Gmail SMTP (ssl://smtp.gmail.com:465) or log to dev outbox if unconfigured.
 *
 * @return array{sent: bool, mode: string, error?: string}
 */
function send_campusfix_email(string $toEmail, string $toName, string $subject, string $htmlBody): array {
    $smtpUser = trim(GMAIL_SMTP_EMAIL);
    $smtpPass = str_replace(" ", "", trim(GMAIL_SMTP_APP_PASSWORD));

    // Always append to local dev outbox log for debugging/verification
    $logDir = __DIR__ . DIRECTORY_SEPARATOR . "uploads";
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $logEntry = sprintf(
        "[%s] TO: %s <%s> | SUBJECT: %s\n",
        date("Y-m-d H:i:s"),
        $toName,
        $toEmail,
        $subject
    );
    @file_put_contents($logDir . DIRECTORY_SEPARATOR . "email_outbox.log", $logEntry, FILE_APPEND);

    if ($smtpUser === "" || $smtpPass === "") {
        error_log("CampusFix SMTP is not configured in the server environment.");
        return ["sent" => false, "mode" => "unconfigured", "error" => "Email service is not configured. Contact the system administrator."];
    }

    $context = stream_context_create([
        "ssl" => [
            "verify_peer"       => false,
            "verify_peer_name"  => false,
            "allow_self_signed" => true
        ]
    ]);

    $socket = @stream_socket_client(
        "ssl://smtp.gmail.com:465",
        $errno,
        $errstr,
        8,
        STREAM_CLIENT_CONNECT,
        $context
    );

    if (!$socket) {
        error_log("CampusFix SMTP connect failed: {$errstr} ({$errno})");
        return ["sent" => false, "mode" => "smtp", "error" => "SMTP connection failed."];
    }

    stream_set_timeout($socket, 8);

    $commands = [
        [null, [220]],
        ["EHLO localhost\r\n", [250]],
        ["AUTH LOGIN\r\n", [334]],
        [base64_encode($smtpUser) . "\r\n", [334]],
        [base64_encode($smtpPass) . "\r\n", [235]],
        ["MAIL FROM:<{$smtpUser}>\r\n", [250]],
        ["RCPT TO:<{$toEmail}>\r\n", [250, 251]],
        ["DATA\r\n", [354]]
    ];

    foreach ($commands as [$cmd, $codes]) {
        if ($cmd !== null) {
            fwrite($socket, $cmd);
        }
        if (!smtp_expect($socket, $codes)) {
            fclose($socket);
            return ["sent" => false, "mode" => "smtp", "error" => "SMTP authentication or handshake failed."];
        }
    }

    $encodedSubject = "=?UTF-8?B?" . base64_encode($subject) . "?=";
    $encodedFromName = "=?UTF-8?B?" . base64_encode(GMAIL_FROM_NAME) . "?=";
    $encodedToName = "=?UTF-8?B?" . base64_encode($toName) . "?=";

    $headers = [
        "Date: " . date("r"),
        "From: {$encodedFromName} <{$smtpUser}>",
        "To: {$encodedToName} <{$toEmail}>",
        "Subject: {$encodedSubject}",
        "MIME-Version: 1.0",
        "Content-Type: text/html; charset=UTF-8",
        "Content-Transfer-Encoding: base64"
    ];

    $message = implode("\r\n", $headers) . "\r\n\r\n"
        . chunk_split(base64_encode($htmlBody)) . "\r\n.\r\n";

    fwrite($socket, $message);
    $ok = smtp_expect($socket, [250]);
    fwrite($socket, "QUIT\r\n");
    fclose($socket);

    return ["sent" => $ok, "mode" => "smtp"];
}

/**
 * Wrap email content in CampusFix branded HTML template.
 */
function campusfix_email_template(string $heading, string $bodyHtml, ?string $ctaLabel = null, ?string $ctaUrl = null): string {
    $ctaBlock = "";
    if ($ctaLabel && $ctaUrl) {
        $safeUrl = htmlspecialchars($ctaUrl, ENT_QUOTES, "UTF-8");
        $safeLabel = htmlspecialchars($ctaLabel, ENT_QUOTES, "UTF-8");
        $ctaBlock = "
            <div style='margin: 24px 0;'>
                <a href='{$safeUrl}' style='display:inline-block;background:#f5b400;color:#171717;font-weight:700;text-decoration:none;padding:12px 22px;border-radius:10px;'>
                    {$safeLabel}
                </a>
            </div>
        ";
    }

    $safeHeading = htmlspecialchars($heading, ENT_QUOTES, "UTF-8");
    return "
    <div style='font-family:Segoe UI,Arial,sans-serif;max-width:560px;margin:0 auto;background:#ffffff;border:1px solid #e6e2d8;border-radius:14px;overflow:hidden;'>
        <div style='background:#171717;color:#f5b400;padding:18px 24px;'>
            <div style='font-size:11px;letter-spacing:1.2px;font-weight:700;text-transform:uppercase;'>T.I.P. Quezon City</div>
            <div style='font-size:20px;font-weight:800;color:#ffffff;margin-top:2px;'>CampusFix</div>
        </div>
        <div style='padding:24px;color:#1f2937;line-height:1.6;font-size:15px;'>
            <h2 style='margin:0 0 12px 0;color:#111827;font-size:19px;'>{$safeHeading}</h2>
            {$bodyHtml}
            {$ctaBlock}
            <hr style='border:none;border-top:1px solid #eee;margin:24px 0 12px;'>
            <p style='font-size:12px;color:#6b7280;margin:0;'>Automated notification from CampusFix Facility &amp; Service Request Management System.</p>
        </div>
    </div>";
}

/**
 * Send Forgot Password reset link email.
 */
function send_password_reset_email(string $toEmail, string $toName, string $resetUrl): array {
    $safeName = htmlspecialchars($toName, ENT_QUOTES, "UTF-8");
    $safeUrl = htmlspecialchars($resetUrl, ENT_QUOTES, "UTF-8");
    $body = "
        <p>Hello <strong>{$safeName}</strong>,</p>
        <p>We received a request to reset your CampusFix password. Click the button below to set a new password. This link expires in <strong>1 hour</strong>.</p>
        <p style='font-size:13px;color:#4b5563;word-break:break-all;'>Or copy this link:<br><a href='{$safeUrl}'>{$safeUrl}</a></p>
        <p style='font-size:13px;color:#6b7280;'>If you did not request a password reset, you can safely ignore this email.</p>
    ";
    $html = campusfix_email_template("Reset Your CampusFix Password", $body, "Reset Password", $resetUrl);
    return send_campusfix_email($toEmail, $toName, "CampusFix Password Reset Request", $html);
}

/**
 * Send email notification when a new request is submitted.
 */
function notify_request_submitted(mysqli $conn, int $requestId): void {
    try {
        $stmt = $conn->prepare(
            "SELECT r.*, u.name AS requester_name, u.email AS requester_email
             FROM requests r
             JOIN users u ON r.requester_id = u.id
             WHERE r.id = ?"
        );
        $stmt->bind_param("i", $requestId);
        $stmt->execute();
        $req = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$req) return;

        $trackUrl = campusfix_base_url() . "/tracking.php?id=" . (int)$req["id"];
        $safeTitle = htmlspecialchars($req["title"], ENT_QUOTES, "UTF-8");
        $safeCat = htmlspecialchars($req["category"], ENT_QUOTES, "UTF-8");
        $safeLoc = htmlspecialchars($req["location"], ENT_QUOTES, "UTF-8");
        $safeName = htmlspecialchars($req["requester_name"], ENT_QUOTES, "UTF-8");

        $body = "
            <p>Hi <strong>{$safeName}</strong>,</p>
            <p>Your facility/service request <strong>#{$requestId}</strong> has been submitted and is awaiting review.</p>
            <ul style='padding-left:18px;'>
                <li><strong>Title:</strong> {$safeTitle}</li>
                <li><strong>Category:</strong> {$safeCat}</li>
                <li><strong>Location:</strong> {$safeLoc}</li>
                <li><strong>Status:</strong> Pending</li>
            </ul>
        ";
        $html = campusfix_email_template("Request #{$requestId} Submitted", $body, "Track Request Live", $trackUrl);
        send_campusfix_email($req["requester_email"], $req["requester_name"], "CampusFix: Request #{$requestId} Submitted", $html);
    } catch (Throwable $e) {
        error_log("CampusFix notify_request_submitted error: " . $e->getMessage());
    }
}

/**
 * Send email notifications when a request is assigned or its status changes.
 */
function notify_request_updated(
    mysqli $conn,
    int $requestId,
    string $oldStatus,
    ?int $oldAssignedTo,
    string $newStatus,
    ?int $newAssignedTo
): void {
    try {
        $stmt = $conn->prepare(
            "SELECT r.*,
                    req_user.name AS requester_name,
                    req_user.email AS requester_email,
                    ass_user.name AS assigned_name,
                    ass_user.email AS assigned_email
             FROM requests r
             JOIN users req_user ON r.requester_id = req_user.id
             LEFT JOIN users ass_user ON r.assigned_to = ass_user.id
             WHERE r.id = ?"
        );
        $stmt->bind_param("i", $requestId);
        $stmt->execute();
        $req = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$req) return;

        $safeTitle = htmlspecialchars($req["title"], ENT_QUOTES, "UTF-8");
        $safeCat = htmlspecialchars($req["category"], ENT_QUOTES, "UTF-8");
        $safeLoc = htmlspecialchars($req["location"], ENT_QUOTES, "UTF-8");
        $safeStatus = htmlspecialchars($newStatus, ENT_QUOTES, "UTF-8");
        $safeAssigned = htmlspecialchars($req["assigned_name"] ?? "Not assigned", ENT_QUOTES, "UTF-8");

        // 1. Notify assigned Maintenance / ITSO personnel if newly assigned
        if ($newAssignedTo !== null && $newAssignedTo > 0 && $newAssignedTo !== $oldAssignedTo && !empty($req["assigned_email"])) {
            $assignedUrl = campusfix_base_url() . "/assigned_requests.php";
            $safeTech = htmlspecialchars($req["assigned_name"], ENT_QUOTES, "UTF-8");
            $techBody = "
                <p>Hello <strong>{$safeTech}</strong>,</p>
                <p>Request <strong>#{$requestId}</strong> has been assigned to you.</p>
                <ul style='padding-left:18px;'>
                    <li><strong>Title:</strong> {$safeTitle}</li>
                    <li><strong>Category:</strong> {$safeCat}</li>
                    <li><strong>Location:</strong> {$safeLoc}</li>
                    <li><strong>Status:</strong> {$safeStatus}</li>
                </ul>
            ";
            $techHtml = campusfix_email_template("New Task Assigned: Request #{$requestId}", $techBody, "Open Assigned Requests", $assignedUrl);
            send_campusfix_email($req["assigned_email"], $req["assigned_name"], "CampusFix: Task Assigned (#{$requestId})", $techHtml);
        }

        // 2. Notify requester if status or assignment changed
        if ($newStatus !== $oldStatus || $newAssignedTo !== $oldAssignedTo) {
            $trackUrl = campusfix_base_url() . "/tracking.php?id=" . (int)$req["id"];
            $safeReqName = htmlspecialchars($req["requester_name"], ENT_QUOTES, "UTF-8");
            $reqBody = "
                <p>Hi <strong>{$safeReqName}</strong>,</p>
                <p>There is an update on your request <strong>#{$requestId} ({$safeTitle})</strong>:</p>
                <ul style='padding-left:18px;'>
                    <li><strong>Current Status:</strong> {$safeStatus}</li>
                    <li><strong>Assigned Personnel:</strong> {$safeAssigned}</li>
                    <li><strong>Location:</strong> {$safeLoc}</li>
                </ul>
            ";
            $reqHtml = campusfix_email_template("Request #{$requestId} Updated to {$newStatus}", $reqBody, "View Live Progress", $trackUrl);
            send_campusfix_email($req["requester_email"], $req["requester_name"], "CampusFix: Request #{$requestId} is now {$newStatus}", $reqHtml);
        }
    } catch (Throwable $e) {
        error_log("CampusFix notify_request_updated error: " . $e->getMessage());
    }
}
