<?php
// Serverless (Vercel): no persistent disk, so keep sessions in MySQL.
$sessionDb = null;
if (getenv("VERCEL")) {
    session_set_save_handler(new class implements SessionHandlerInterface {
        public function open(string $path, string $name): bool { return true; }
        public function close(): bool { return true; }
        public function read(string $id): string|false {
            global $sessionDb;
            $stmt = $sessionDb->prepare("SELECT data FROM sessions WHERE id = ? AND expires_at > UNIX_TIMESTAMP()");
            $stmt->bind_param("s", $id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            return $row["data"] ?? "";
        }
        public function write(string $id, string $data): bool {
            global $sessionDb;
            $expires = time() + 86400;
            $stmt = $sessionDb->prepare("REPLACE INTO sessions (id, data, expires_at) VALUES (?, ?, ?)");
            $stmt->bind_param("ssi", $id, $data, $expires);
            return $stmt->execute();
        }
        public function destroy(string $id): bool {
            global $sessionDb;
            $stmt = $sessionDb->prepare("DELETE FROM sessions WHERE id = ?");
            $stmt->bind_param("s", $id);
            return $stmt->execute();
        }
        public function gc(int $max): int|false { return 0; }
    }, true);
}

session_set_cookie_params([
    "httponly" => true,
    "secure" => !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off",
    "samesite" => "Lax"
]);

$host = trim(getenv("CAMPUSFIX_DB_HOST") ?: "localhost");
$user = trim(getenv("CAMPUSFIX_DB_USER") ?: "root");
$pass = getenv("CAMPUSFIX_DB_PASSWORD") ?: "";
$db = trim(getenv("CAMPUSFIX_DB_NAME") ?: "campusfix");
$port = (int)(getenv("CAMPUSFIX_DB_PORT") ?: 3306);

// vercel-php's bundled glibc NSS can fail C-level getaddrinfo() on AWS Lambda; fall back to Global Accelerator IP.
if (getenv("VERCEL") && $host === "gateway01.ap-southeast-1.prod.aws.tidbcloud.com") {
    $host = "75.2.106.174";
}

try {
    $conn = mysqli_init();
    // Managed MySQL (e.g. TiDB Cloud) requires TLS: set CAMPUSFIX_DB_SSL=1.
    $flags = 0;
    if (getenv("CAMPUSFIX_DB_SSL")) {
        $conn->ssl_set(null, null, null, null, null);
        $flags = MYSQLI_CLIENT_SSL | MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT;
    }
    $conn->real_connect($host, $user, $pass, $db, $port, null, $flags);
} catch (mysqli_sql_exception $exception) {
    error_log("CampusFix database connection failed: " . $exception->getMessage());
    http_response_code(503);
    exit("CampusFix is temporarily unavailable. Please try again later.");
}

if (!$conn->set_charset("utf8mb4")) {
    error_log("CampusFix could not configure the database connection charset.");
    http_response_code(503);
    exit("CampusFix is temporarily unavailable. Please try again later.");
}

$sessionDb = $conn;
session_start();

require_once __DIR__ . "/firebase.php";
require_once __DIR__ . "/mail.php";

// Photos live in MySQL (photos table) because serverless hosts have no writable disk. Served by photo.php at /uploads/<name>.
function photo_store(string $tmpFile, string $name, string $mime): bool {
    global $conn;
    $data = file_get_contents($tmpFile);
    if ($data === false) return false;
    $stmt = $conn->prepare("INSERT INTO photos (name, mime, data) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $name, $mime, $data);
    return $stmt->execute();
}

function photo_delete(?string $name): void {
    global $conn;
    if (!$name) return;
    $stmt = $conn->prepare("DELETE FROM photos WHERE name = ?");
    $stmt->bind_param("s", $name);
    if (!$stmt->execute()) error_log("CampusFix could not remove photo {$name}.");
}

function csrf_token(): string {
    if (!isset($_SESSION["csrf_token"]) || !is_string($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }

    return $_SESSION["csrf_token"];
}

function csrf_input(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, "UTF-8") . '">';
}

function require_csrf_token(): void {
    $submittedToken = $_POST["csrf_token"] ?? null;
    $sessionToken = $_SESSION["csrf_token"] ?? null;

    if (!is_string($submittedToken) || !is_string($sessionToken) || !hash_equals($sessionToken, $submittedToken)) {
        http_response_code(403);
        exit("Your session expired or this form could not be verified. Reload the page and try again.");
    }
}

function require_login(): void {
    global $conn;

    if (!isset($_SESSION["user_id"])) {
        header("Location: index.php?error=" . urlencode("Please log in first."));
        exit;
    }

    $stmt = $conn->prepare("SELECT name, role FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION["user_id"]);
    $stmt->execute();
    $result = $stmt->get_result();

    $user = $result->fetch_assoc();
    if (!$user) {
        session_unset();
        session_destroy();

        header("Location: index.php?error=" . urlencode("Your account no longer exists. Please log in again."));
        exit;
    }

    if (!in_array($user["role"], ["requester", "admin_staff", "system_admin", "maintenance", "itso"], true)) {
        session_unset();
        session_destroy();
        header("Location: index.php?error=" . urlencode("Your account role is invalid. Please log in again."));
        exit;
    }

    $_SESSION["name"] = $user["name"];
    $_SESSION["role"] = $user["role"];
    $stmt->close();
}

function require_roles(array $roles): void {
    require_login();

    if (!in_array($_SESSION["role"] ?? "", $roles, true)) {
        header("Location: dashboard.php?error=" . urlencode("You are not authorized to access that page."));
        exit;
    }
}

function role_label(string $role): string {
    return match ($role) {
        "admin_staff" => "Administrative Staff",
        "maintenance" => "Maintenance Personnel",
        "itso" => "ITSO Personnel",
        "system_admin" => "System Administrator",
        default => "Requester"
    };
}

function validate_password_rules(string $password): ?string {
    if (
        strlen($password) < 8 ||
        !preg_match('/[A-Z]/', $password) ||
        !preg_match('/[a-z]/', $password) ||
        !preg_match('/[0-9]/', $password) ||
        !preg_match('/[^A-Za-z0-9]/', $password)
    ) {
        return "Password must be at least 8 characters and include uppercase, lowercase, number, and special character.";
    }
    return null;
}
?>
