<?php
if (!function_exists('rbj_load_dotenv')) {
    function rbj_load_dotenv(string $path): void
    {
        static $loaded = false;
        if ($loaded || !is_file($path) || !is_readable($path)) {
            $loaded = true;
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            $loaded = true;
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            // Strip UTF-8 BOM if present (common when file is saved from Windows editors).
            $line = preg_replace('/^\xEF\xBB\xBF/', '', $line) ?? $line;
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }
            $key = trim($parts[0]);
            $value = trim($parts[1]);
            if ($key === '') {
                continue;
            }

            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            // .env should be authoritative for app-level RBJ_* settings
            // to avoid stale OS/User env values overriding local config.
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        $loaded = true;
    }
}

rbj_load_dotenv(__DIR__ . '/.env');

if (!function_exists('rbj_env')) {
    function rbj_env(string $key, ?string $default = null): ?string
    {
        $value = getenv($key);
        if ($value !== false && $value !== null) {
            return (string)$value;
        }
        if (array_key_exists($key, $_ENV) && $_ENV[$key] !== null) {
            return (string)$_ENV[$key];
        }
        if (array_key_exists($key, $_SERVER) && $_SERVER[$key] !== null) {
            return (string)$_SERVER[$key];
        }
        return $default;
    }
}

if (!function_exists('rbj_env_bool')) {
    function rbj_env_bool(string $key, bool $default = false): bool
    {
        $raw = rbj_env($key);
        if ($raw === null || $raw === '') {
            return $default;
        }
        $parsed = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $parsed !== null ? $parsed : $default;
    }
}

if (!function_exists('rbj_env_int')) {
    function rbj_env_int(string $key, int $default): int
    {
        $raw = rbj_env($key);
        if ($raw === null || $raw === '') {
            return $default;
        }
        $value = filter_var($raw, FILTER_VALIDATE_INT);
        return $value !== false ? (int)$value : $default;
    }
}

if (!function_exists('rbj_is_https')) {
    function rbj_is_https(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        $forwardedProto = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        return $forwardedProto === 'https';
    }
}

if (!function_exists('rbj_apply_security_headers')) {
    function rbj_apply_security_headers(): void
    {
        static $applied = false;
        if ($applied || headers_sent()) {
            return;
        }

        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
        header('Cross-Origin-Resource-Policy: same-site');
        $applied = true;
    }
}

if (!function_exists('rbj_harden_session_cookie')) {
    function rbj_harden_session_cookie(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE || headers_sent()) {
            return;
        }

        $params = session_get_cookie_params();
        $path = (string)($params['path'] ?? '/');
        if ($path === '') {
            $path = '/';
        }

        setcookie(session_name(), session_id(), [
            'expires' => 0,
            'path' => $path,
            'domain' => (string)($params['domain'] ?? ''),
            'secure' => rbj_is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

@ini_set('expose_php', '0');
@ini_set('session.use_strict_mode', '1');
@ini_set('session.cookie_httponly', '1');
@ini_set('session.cookie_samesite', 'Lax');
@ini_set('session.cookie_secure', rbj_is_https() ? '1' : '0');

rbj_apply_security_headers();
rbj_harden_session_cookie();

// Database configuration (override via .env values).
$servername = trim((string)rbj_env('RBJ_DB_HOST', 'localhost'));
$username = trim((string)rbj_env('RBJ_DB_USER', 'root'));
$password = (string)rbj_env('RBJ_DB_PASS', '');
$dbname = trim((string)rbj_env('RBJ_DB_NAME', 'motofit_db'));
$db_charset = trim((string)rbj_env('RBJ_DB_CHARSET', 'utf8mb4'));
if ($db_charset === '') {
    $db_charset = 'utf8mb4';
}

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset($db_charset);

?>
