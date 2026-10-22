<?php
/**
 * Firebase Cloud Firestore integration for CampusFix
 * Uses native OpenSSL + cURL to authenticate via Service Account OAuth2 JWT.
 * No heavy third-party vendor dependencies required.
 */

define("FIREBASE_PROJECT_ID", "errorlog-cfdaf");
define("FIREBASE_SERVICE_ACCOUNT_PATH", __DIR__ . DIRECTORY_SEPARATOR . "firebase-service-account.json");

/**
 * Obtain an OAuth2 access token for Google Cloud Datastore / Firestore.
 * Caches token in temp dir until near expiration.
 */
function firebase_get_access_token(): ?string {
    $tempDir = sys_get_temp_dir();
    $cacheFile = $tempDir . DIRECTORY_SEPARATOR . "campusfix_fb_token.json";

    if (file_exists($cacheFile)) {
        $cached = json_decode(@file_get_contents($cacheFile), true);
        if ($cached && isset($cached["token"], $cached["expires_at"]) && time() < ($cached["expires_at"] - 90)) {
            return $cached["token"];
        }
    }

    if (!file_exists(FIREBASE_SERVICE_ACCOUNT_PATH)) {
        error_log("Firebase: Service account credentials missing: " . FIREBASE_SERVICE_ACCOUNT_PATH);
        return null;
    }

    $sa = json_decode(@file_get_contents(FIREBASE_SERVICE_ACCOUNT_PATH), true);
    if (!$sa || empty($sa["private_key"]) || empty($sa["client_email"])) {
        error_log("Firebase: Invalid service account file.");
        return null;
    }

    $now = time();
    $header = ["alg" => "RS256", "typ" => "JWT"];
    $payload = [
        "iss"   => $sa["client_email"],
        "sub"   => $sa["client_email"],
        "aud"   => "https://oauth2.googleapis.com/token",
        "iat"   => $now,
        "exp"   => $now + 3600,
        "scope" => "https://www.googleapis.com/auth/datastore"
    ];

    $b64Header = rtrim(strtr(base64_encode(json_encode($header)), "+/", "-_"), "=");
    $b64Payload = rtrim(strtr(base64_encode(json_encode($payload)), "+/", "-_"), "=");
    $signingInput = $b64Header . "." . $b64Payload;

    $signature = "";
    if (!openssl_sign($signingInput, $signature, $sa["private_key"], OPENSSL_ALGO_SHA256)) {
        error_log("Firebase: OpenSSL signing error.");
        return null;
    }

    $b64Sig = rtrim(strtr(base64_encode($signature), "+/", "-_"), "=");
    $jwt = $signingInput . "." . $b64Sig;

    $ch = curl_init("https://oauth2.googleapis.com/token");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            "grant_type" => "urn:ietf:params:oauth:grant-type:jwt-bearer",
            "assertion"  => $jwt
        ]),
        CURLOPT_TIMEOUT        => 6,
        CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) {
        error_log("Firebase: OAuth token request failed with HTTP " . $httpCode);
        return null;
    }

    $res = json_decode($response, true);
    if (!isset($res["access_token"])) {
        return null;
    }

    @file_put_contents($cacheFile, json_encode([
        "token"      => $res["access_token"],
        "expires_at" => $now + (int)($res["expires_in"] ?? 3600)
    ]));

    return $res["access_token"];
}

/**
 * Convert PHP array into Firestore REST fields format.
 */
function firebase_to_firestore_fields(array $data): array {
    $fields = [];
    foreach ($data as $key => $val) {
        if (is_int($val)) {
            $fields[$key] = ["integerValue" => (string)$val];
        } elseif (is_bool($val)) {
            $fields[$key] = ["booleanValue" => $val];
        } elseif (is_array($val)) {
            $fields[$key] = ["mapValue" => ["fields" => firebase_to_firestore_fields($val)]];
        } elseif ($val === null) {
            $fields[$key] = ["nullValue" => null];
        } else {
            $fields[$key] = ["stringValue" => (string)$val];
        }
    }
    return $fields;
}

/**
 * Sync request details and progress stages from MySQL to Firestore.
 */
function firebase_sync_request_from_db(mysqli $conn, int $requestId): bool {
    try {
        $stmt = $conn->prepare(
            "SELECT r.*, u.name AS assigned_name, req_user.name AS requester_name
             FROM requests r
             LEFT JOIN users u ON r.assigned_to = u.id
             LEFT JOIN users req_user ON r.requester_id = req_user.id
             WHERE r.id = ?"
        );
        $stmt->bind_param("i", $requestId);
        $stmt->execute();
        $request = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$request) {
            return false;
        }

        $progress = [];
        $stmtProgress = $conn->prepare("SELECT stage, completed_at FROM request_progress WHERE request_id = ?");
        $stmtProgress->bind_param("i", $requestId);
        $stmtProgress->execute();
        $pRes = $stmtProgress->get_result();
        while ($row = $pRes->fetch_assoc()) {
            $progress[$row["stage"]] = $row["completed_at"];
        }
        $stmtProgress->close();

        $token = firebase_get_access_token();
        if (!$token) {
            return false;
        }

        $docPayload = [
            "id"             => (int)$request["id"],
            "requester_id"   => (int)$request["requester_id"],
            "requester_name" => (string)($request["requester_name"] ?? "Requester"),
            "assigned_to"    => $request["assigned_to"] !== null ? (int)$request["assigned_to"] : 0,
            "title"          => (string)$request["title"],
            "category"       => (string)$request["category"],
            "location"       => (string)$request["location"],
            "status"         => (string)$request["status"],
            "assigned_name"  => (string)($request["assigned_name"] ?? "Not assigned"),
            "created_at"    => (string)$request["created_at"],
            "updated_at"    => (string)$request["updated_at"],
            "stages"        => [
                "submitted" => $request["created_at"],
                "reviewed"  => $progress["reviewed"] ?? null,
                "assigned"  => $progress["assigned"] ?? null,
                "repair"    => $progress["repair"] ?? null,
                "resolved"  => $progress["resolved"] ?? null,
            ]
        ];

        $url = "https://firestore.googleapis.com/v1/projects/" . FIREBASE_PROJECT_ID . "/databases/(default)/documents/requests/" . $requestId;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => "PATCH",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => json_encode(["fields" => firebase_to_firestore_fields($docPayload)]),
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer " . $token,
                "Content-Type: application/json"
            ],
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4
        ]);

        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($code >= 200 && $code < 300);
    } catch (Throwable $e) {
        error_log("Firebase sync exception for request #" . $requestId . ": " . $e->getMessage());
        return false;
    }
}

/**
 * Delete a request document from Firestore when permanently removed.
 */
function firebase_delete_request(int $requestId): bool {
    try {
        $token = firebase_get_access_token();
        if (!$token) {
            return false;
        }

        $url = "https://firestore.googleapis.com/v1/projects/" . FIREBASE_PROJECT_ID . "/databases/(default)/documents/requests/" . $requestId;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => "DELETE",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer " . $token
            ],
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4
        ]);

        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($code >= 200 && $code < 300);
    } catch (Throwable $e) {
        error_log("Firebase delete exception for request #" . $requestId . ": " . $e->getMessage());
        return false;
    }
}

/**
 * Bulk sync all requests in MySQL to Firestore.
 */
function firebase_sync_all_requests(mysqli $conn): int {
    $res = $conn->query("SELECT id FROM requests ORDER BY id ASC");
    if (!$res) return 0;

    $synced = 0;
    while ($row = $res->fetch_assoc()) {
        if (firebase_sync_request_from_db($conn, (int)$row["id"])) {
            $synced++;
        }
    }
    return $synced;
}
