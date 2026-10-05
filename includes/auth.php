<?php
/**
 * Visitor accounts: phone number + one-time code (OTP) login.
 */
declare(strict_types=1);

/* ---------- SMS / OTP settings (can also be set as environment variables) ---------- */
/* 'demo': OTP is shown on screen, ONLY for requests coming from the server itself (localhost).
 * 'sms' : OTP is sent by SMS through the gateway below. Use 'sms' in production. */
define('OTP_MODE', getenv('OTP_MODE') ?: 'demo');
define('TWILIO_SID',   getenv('TWILIO_SID')   ?: '');
define('TWILIO_TOKEN', getenv('TWILIO_TOKEN') ?: '');
define('TWILIO_FROM',  getenv('TWILIO_FROM')  ?: '');   // your Twilio number, e.g. +1415XXXXXXX
define('FAST2SMS_KEY', getenv('FAST2SMS_KEY') ?: '');   // Fast2SMS API key (Indian numbers only)
const OTP_TTL = 600;        // seconds a code stays valid
const OTP_MAX_ATTEMPTS = 5; // wrong guesses allowed per code

/** Normalise to +<country><number>. 10-digit Indian numbers get +91. Returns null if invalid. */
function normalize_phone(string $raw): ?string {
    $s = preg_replace('/[\s\-().]/', '', trim($raw)) ?? '';
    if ($s === '') return null;
    if ($s[0] === '+') {
        return preg_match('/^\+[1-9]\d{7,14}$/', $s) ? $s : null;
    }
    if (!ctype_digit($s)) return null;
    if (strlen($s) === 10 && $s[0] >= '6') return '+91' . $s;
    if (strlen($s) === 11 && $s[0] === '0' && $s[1] >= '6') return '+91' . substr($s, 1);
    if (strlen($s) === 12 && str_starts_with($s, '91') && $s[2] >= '6') return '+' . $s;
    return null;
}

function otp_demo_allowed(): bool {
    return OTP_MODE === 'demo'
        && in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)
        && !isset($_SERVER['HTTP_X_FORWARDED_FOR']);   // never in front of a proxy
}

function sms_configured(): bool {
    return FAST2SMS_KEY !== '' || (TWILIO_SID !== '' && TWILIO_TOKEN !== '' && TWILIO_FROM !== '');
}

/** Send the OTP through Fast2SMS (OTP route, Indian numbers). Returns null on success or an error message. */
function send_sms_fast2sms(string $phone, string $code): ?string {
    if (!function_exists('curl_init')) return 'SMS login is not configured on this server yet.';
    if (!preg_match('/^\+91([6-9]\d{9})$/', $phone, $m)) {
        return 'SMS login currently supports Indian mobile numbers (+91) only.';
    }
    $ch = curl_init('https://www.fast2sms.com/dev/bulkV2');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['authorization: ' . FAST2SMS_KEY, 'Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_POSTFIELDS => http_build_query(['route' => 'otp', 'variables_values' => $code, 'numbers' => $m[1]]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    $resp = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $j = is_string($resp) ? json_decode($resp, true) : null;
    if (is_array($j) && ($j['return'] ?? false) === true) return null;
    // Log Fast2SMS's reason (never the API key or the code) so it shows in Railway logs.
    $why = is_array($j) ? (is_array($j['message'] ?? null) ? implode('; ', $j['message']) : (string)($j['message'] ?? '')) : 'no response';
    error_log('Fast2SMS send failed, HTTP ' . $http . ', status ' . ($j['status_code'] ?? '?') . ': ' . $why);
    return 'We could not send the SMS right now. Please try again shortly.';
}

/** Send an SMS through Twilio. Returns null on success or an error message. */
function send_sms(string $to, string $body): ?string {
    if (TWILIO_SID === '' || TWILIO_TOKEN === '' || TWILIO_FROM === '' || !function_exists('curl_init')) {
        return 'SMS login is not configured on this server yet.';
    }
    $ch = curl_init('https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode(TWILIO_SID) . '/Messages.json');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['To' => $to, 'From' => TWILIO_FROM, 'Body' => $body]),
        CURLOPT_USERPWD => TWILIO_SID . ':' . TWILIO_TOKEN,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code >= 200 && $code < 300) return null;
    error_log("SMS send failed, HTTP $code");
    return 'We could not send the SMS right now. Please try again shortly.';
}

/**
 * Create + deliver an OTP.
 * @return array{ok:bool,error?:string,demo_code?:string}
 */
function otp_request(string $phone): array {
    $pdo = db(); $now = time(); $ip = client_ip();

    $st = $pdo->prepare('SELECT MAX(created_at) FROM otp_requests WHERE phone = ?');
    $st->execute([$phone]);
    if ($now - (int)$st->fetchColumn() < 30) return ['ok' => false, 'error' => 'Please wait 30 seconds before requesting another code.'];

    $st = $pdo->prepare('SELECT COUNT(*) FROM otp_requests WHERE phone = ? AND created_at > ?');
    $st->execute([$phone, $now - 900]);
    if ((int)$st->fetchColumn() >= 5) return ['ok' => false, 'error' => 'Too many codes requested for this number. Try again in 15 minutes.'];

    $st = $pdo->prepare('SELECT COUNT(*) FROM otp_requests WHERE ip = ? AND created_at > ?');
    $st->execute([$ip, $now - 3600]);
    if ((int)$st->fetchColumn() >= 20) return ['ok' => false, 'error' => 'Too many requests from your network. Please try later.'];

    $demo = otp_demo_allowed();
    if (!$demo && (OTP_MODE !== 'sms' || !sms_configured())) {
        return ['ok' => false, 'error' => 'SMS login is not configured on this server yet.'];
    }

    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $pdo->prepare('UPDATE otp_requests SET used = 1 WHERE phone = ? AND used = 0')->execute([$phone]);
    $pdo->prepare('INSERT INTO otp_requests (phone, ip, code_hash, created_at, expires_at) VALUES (?,?,?,?,?)')
        ->execute([$phone, $ip, password_hash($code, PASSWORD_DEFAULT), $now, $now + OTP_TTL]);
    $id = (int)$pdo->lastInsertId();

    if ($demo) return ['ok' => true, 'demo_code' => $code];

    $err = FAST2SMS_KEY !== ''
        ? send_sms_fast2sms($phone, $code)
        : send_sms($phone, "Your Future Skills login code is $code. It is valid for 10 minutes.");
    if ($err !== null) {
        $pdo->prepare('DELETE FROM otp_requests WHERE id = ?')->execute([$id]);
        return ['ok' => false, 'error' => $err];
    }
    return ['ok' => true];
}

/** Check a code. Returns null if valid, else an error message. */
function otp_verify(string $phone, string $code): ?string {
    $pdo = db();
    $st = $pdo->prepare('SELECT id, code_hash FROM otp_requests WHERE phone = ? AND used = 0 AND expires_at > ? ORDER BY id DESC LIMIT 1');
    $st->execute([$phone, time()]);
    $row = $st->fetch();
    if (!$row) return 'This code has expired. Please request a new one.';

    // Atomically burn one attempt; fails when attempts are exhausted.
    $up = $pdo->prepare('UPDATE otp_requests SET attempts = attempts + 1 WHERE id = ? AND used = 0 AND attempts < ?');
    $up->execute([$row['id'], OTP_MAX_ATTEMPTS]);
    if ($up->rowCount() === 0) {
        $pdo->prepare('UPDATE otp_requests SET used = 1 WHERE id = ?')->execute([$row['id']]);
        return 'Too many wrong attempts. Please request a new code.';
    }
    if (!password_verify($code, $row['code_hash'])) return 'Incorrect code. Please try again.';

    $pdo->prepare('UPDATE otp_requests SET used = 1 WHERE id = ?')->execute([$row['id']]);
    return null;
}

/* ---------- Users / session ---------- */

function user_login(string $phone): array {
    $pdo = db(); $now = date('Y-m-d H:i:s');
    try {
        $pdo->prepare('INSERT INTO users (phone, created_at) VALUES (?, ?)')->execute([$phone, $now]);
    } catch (PDOException $e) {
        if ($e->getCode() !== '23000') throw $e;   // 23000 = already exists (returning user)
    }
    $pdo->prepare('UPDATE users SET last_login_at = ? WHERE phone = ?')->execute([$now, $phone]);
    $st = $pdo->prepare('SELECT * FROM users WHERE phone = ?');
    $st->execute([$phone]);
    $user = $st->fetch();
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$user['id'];
    unset($_SESSION['otp_phone'], $_SESSION['demo_code']);
    return $user;
}

function current_user(): ?array {
    static $cache = false;
    if ($cache !== false) return $cache;
    $cache = null;
    if (!empty($_SESSION['uid'])) {
        $st = db()->prepare('SELECT * FROM users WHERE id = ?');
        $st->execute([(int)$_SESSION['uid']]);
        $cache = $st->fetch() ?: null;
        if (!$cache) unset($_SESSION['uid']);
    }
    return $cache;
}

/** Only allow redirects to our own known pages. */
function safe_next(?string $next, string $default = 'account.php'): string {
    return in_array($next, ['chat.php', 'account.php', 'contact.php'], true) ? $next : $default;
}

function require_user(): array {
    $u = current_user();
    if (!$u) {
        header('Location: login.php?next=' . urlencode(basename($_SERVER['SCRIPT_NAME'])));
        exit;
    }
    return $u;
}
