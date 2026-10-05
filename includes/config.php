<?php
/**
 * Core configuration + helpers (sessions, CSRF, content, JSON helpers). Data lives in the database (see db.php).
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
define('DATA_PATH', BASE_PATH . '/data');           // local SQLite fallback only
define('SEED_CONTENT_FILE', BASE_PATH . '/seed/content.json');

// Optional local overrides (not committed): may call putenv('MYSQL_URL=...') etc.
if (is_file(__DIR__ . '/config.local.php')) require __DIR__ . '/config.local.php';

date_default_timezone_set('Asia/Kolkata');

/* ---------- Proxy awareness (Railway, Cloudflare, etc. terminate HTTPS in front of PHP) ---------- */
function trust_proxy(): bool {
    return getenv('TRUST_PROXY') === '1' || getenv('RAILWAY_ENVIRONMENT') !== false || getenv('RAILWAY_PROJECT_ID') !== false;
}
function is_https(): bool {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
    return trust_proxy() && strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}
function client_ip(): string {
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    if (trust_proxy()) {
        $cand = trim((string)($_SERVER['HTTP_X_REAL_IP'] ?? ''));
        if ($cand === '' && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = array_map('trim', explode(',', (string)$_SERVER['HTTP_X_FORWARDED_FOR']));
            $cand = (string)end($parts);   // entry added by the nearest proxy
        }
        if (filter_var($cand, FILTER_VALIDATE_IP)) $ip = $cand;
    }
    return $ip;
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => is_https()]);
    session_start();
}

/* ---------- Admin login ----------
 * Set ADMIN_PASSWORD (or ADMIN_PASSWORD_HASH from password_hash()) as an environment variable on your host.
 * Fallback default password "admin123" is accepted ONLY when you are on localhost. */
define('ADMIN_USER', getenv('ADMIN_USER') ?: 'admin');

function admin_default_allowed(): bool {
    return in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true) && !isset($_SERVER['HTTP_X_FORWARDED_FOR']);
}
/** @return string|null error message, or null when the password is correct */
function admin_check_password(string $pw): ?string {
    $hash = getenv('ADMIN_PASSWORD_HASH');
    $plain = getenv('ADMIN_PASSWORD');
    if ($hash !== false && $hash !== '') return password_verify($pw, $hash) ? null : 'Invalid username or password.';
    if ($plain !== false && $plain !== '') return hash_equals($plain, $pw) ? null : 'Invalid username or password.';
    if (!admin_default_allowed()) return 'Admin login is disabled until the ADMIN_PASSWORD environment variable is set on the server.';
    return hash_equals('admin123', $pw) ? null : 'Invalid username or password.';
}

function e(?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

/** Read a JSON file (used only to seed default content). */
function read_json(string $file, array $default = []): array {
    if (!is_file($file)) return $default;
    $fh = fopen($file, 'rb');
    if (!$fh) return $default;
    flock($fh, LOCK_SH);
    $raw = stream_get_contents($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
    $data = json_decode((string)$raw, true);
    return is_array($data) ? $data : $default;
}

/** Load site content; missing keys never break pages. */
function content(): array {
    static $c = null;
    if ($c === null) $c = content_load();
    return $c;
}

/** Fetch a value by path, e.g. c('home.hero_title'). */
function c(string $path, string $fallback = ''): string {
    $node = content();
    foreach (explode('.', $path) as $k) {
        if (!is_array($node) || !array_key_exists($k, $node)) return $fallback;
        $node = $node[$k];
    }
    return is_string($node) ? $node : $fallback;
}

function c_list(string $path): array {
    $node = content();
    foreach (explode('.', $path) as $k) {
        if (!is_array($node) || !array_key_exists($k, $node)) return [];
        $node = $node[$k];
    }
    return is_array($node) ? $node : [];
}

/* ---------- CSRF ---------- */
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function csrf_check(): bool {
    return isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$_POST['csrf']);
}

/* ---------- Auth ---------- */
function is_admin(): bool { return !empty($_SESSION['is_admin']); }

function flash(?string $msg = null, string $type = 'success'): ?array {
    if ($msg !== null) { $_SESSION['flash'] = ['msg' => $msg, 'type' => $type]; return null; }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function nav_active(string $page): string {
    return basename($_SERVER['SCRIPT_NAME']) === $page ? ' class="active"' : '';
}

function json_out(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function is_ajax(): bool { return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch'; }

/* ---------- Live chat rendering (data comes from chat_payload() in db.php) ---------- */
function render_chat_messages(array $msgs, string $me, string $meLabel, string $themLabel): void {
    if (!$msgs) { echo '<p class="chat-empty">No messages yet. Say hello!</p>'; return; }
    foreach ($msgs as $m) {
        $mine = $m['from'] === $me;
        echo '<div class="chat-msg ' . ($mine ? 'me' : 'them') . '"><small>' . e($mine ? $meLabel : $themLabel)
           . ' &middot; ' . e($m['time']) . '</small><span>' . e($m['text']) . '</span></div>';
    }
}

/* ---------- Firebase Configuration ---------- */
$envApiKey = trim((string)getenv('FIREBASE_API_KEY'));
$firebaseApiKey = (str_starts_with($envApiKey, 'AIza')) ? $envApiKey : 'AIzaSyBgUWoXtNVwFybnHBLihOfg3AN5o9QwNgo';

define('FIREBASE_CONFIG', [
    'apiKey'            => $firebaseApiKey,
    'authDomain'        => getenv('FIREBASE_AUTH_DOMAIN')        ?: 'future-skills-21690.firebaseapp.com',
    'projectId'         => getenv('FIREBASE_PROJECT_ID')         ?: 'future-skills-21690',
    'storageBucket'     => getenv('FIREBASE_STORAGE_BUCKET')     ?: 'future-skills-21690.firebasestorage.app',
    'messagingSenderId' => getenv('FIREBASE_MESSAGING_SENDER_ID') ?: '913658454846',
    'appId'             => getenv('FIREBASE_APP_ID')             ?: '1:913658454846:web:7487cc06cfa09ae82d026f',
    'measurementId'     => getenv('FIREBASE_MEASUREMENT_ID')     ?: 'G-R6NF9RH122'
]);



function firebase_config_json(): string {
    return json_encode(FIREBASE_CONFIG, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/inquiry.php';

