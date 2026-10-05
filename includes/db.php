<?php
/**
 * Database layer (PDO).
 *  - Production (Railway): MySQL, configured by environment variables (see db_config()).
 *  - Local development with no MySQL configured: automatic SQLite file (data/app.sqlite).
 * Tables are created automatically on first use (same schema as database/schema.sql).
 */
declare(strict_types=1);

/** Resolve connection settings from the environment. */
function db_config(): array {
    $url = getenv('MYSQL_URL') ?: getenv('DATABASE_URL') ?: '';
    if ($url !== '' && str_starts_with($url, 'mysql')) {
        $p = parse_url($url);
        if ($p && !empty($p['host'])) {
            return ['driver' => 'mysql', 'host' => $p['host'], 'port' => (int)($p['port'] ?? 3306),
                    'user' => urldecode($p['user'] ?? ''), 'pass' => urldecode($p['pass'] ?? ''),
                    'name' => ltrim($p['path'] ?? '', '/')];
        }
    }
    $host = getenv('MYSQLHOST') ?: getenv('DB_HOST') ?: '';
    if ($host !== '') {
        return ['driver' => 'mysql', 'host' => $host,
                'port' => (int)(getenv('MYSQLPORT') ?: getenv('DB_PORT') ?: 3306),
                'user' => getenv('MYSQLUSER') ?: getenv('DB_USER') ?: 'root',
                'pass' => getenv('MYSQLPASSWORD') ?: getenv('DB_PASS') ?: '',
                'name' => getenv('MYSQLDATABASE') ?: getenv('DB_NAME') ?: 'railway'];
    }
    return ['driver' => 'sqlite'];
}

function db_driver(): string { return db_config()['driver']; }

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $cfg = db_config();
    try {
        if ($cfg['driver'] === 'mysql') {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $cfg['host'], $cfg['port'], $cfg['name']);
            $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT            => 8,
            ]);
        } else {
            if (!is_dir(DATA_PATH)) @mkdir(DATA_PATH, 0775, true);
            $pdo = new PDO('sqlite:' . DATA_PATH . '/app.sqlite', null, null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $pdo->exec('PRAGMA journal_mode = WAL; PRAGMA foreign_keys = ON; PRAGMA busy_timeout = 5000;');
        }
        db_install($pdo, $cfg['driver']);
    } catch (Throwable $e) {
        error_log('DB error: ' . $e->getMessage());
        http_response_code(503);
        exit('The database is not reachable right now. Please try again in a moment.');
    }
    return $pdo;
}

/** Create tables if they do not exist yet (cheap check on every request). */
function db_install(PDO $pdo, string $driver): void {
    try {
        $pdo->query('SELECT 1 FROM inquiry_replies LIMIT 1');   // last table created: if it exists, everything does
        return;
    } catch (Throwable $e) { /* not installed yet */ }

    if ($driver === 'mysql') {
        $sql = (string)file_get_contents(BASE_PATH . '/database/schema.sql');
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);            // strip full-line comments
        $sql = preg_replace('/--[^\n]*/', '', (string)$sql);       // strip trailing comments
        foreach (array_filter(array_map('trim', explode(';', (string)$sql))) as $stmt) $pdo->exec($stmt);
        return;
    }
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS site_content (id INTEGER PRIMARY KEY, data TEXT NOT NULL, updated_at TEXT NOT NULL);
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT, phone TEXT NOT NULL UNIQUE,
            name TEXT NOT NULL DEFAULT '', school TEXT NOT NULL DEFAULT '', email TEXT NOT NULL DEFAULT '',
            created_at TEXT NOT NULL, last_login_at TEXT);
        CREATE TABLE IF NOT EXISTS otp_requests (
            id INTEGER PRIMARY KEY AUTOINCREMENT, phone TEXT NOT NULL, ip TEXT NOT NULL DEFAULT '',
            code_hash TEXT NOT NULL, attempts INTEGER NOT NULL DEFAULT 0, used INTEGER NOT NULL DEFAULT 0,
            created_at INTEGER NOT NULL, expires_at INTEGER NOT NULL);
        CREATE INDEX IF NOT EXISTS idx_otp_phone ON otp_requests (phone, created_at);
        CREATE INDEX IF NOT EXISTS idx_otp_ip ON otp_requests (ip, created_at);
        CREATE TABLE IF NOT EXISTS chat_messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            sender TEXT NOT NULL, body TEXT NOT NULL, created_at TEXT NOT NULL, admin_read INTEGER NOT NULL DEFAULT 0);
        CREATE INDEX IF NOT EXISTS idx_chat_user ON chat_messages (user_id, id);
        CREATE TABLE IF NOT EXISTS inquiries (
            id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, school TEXT NOT NULL DEFAULT '',
            email TEXT NOT NULL, phone TEXT NOT NULL, phone_norm TEXT NOT NULL DEFAULT '', message TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'Pending', reply TEXT, created_at TEXT NOT NULL, replied_at TEXT);
        CREATE INDEX IF NOT EXISTS idx_inq_phone ON inquiries (phone_norm);
        CREATE INDEX IF NOT EXISTS idx_inq_status ON inquiries (status, id);
        CREATE TABLE IF NOT EXISTS inquiry_replies (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            inquiry_id INTEGER NOT NULL REFERENCES inquiries(id) ON DELETE CASCADE,
            sender TEXT NOT NULL, body TEXT NOT NULL, created_at TEXT NOT NULL);
        CREATE INDEX IF NOT EXISTS idx_reply_inquiry ON inquiry_replies (inquiry_id, id);
    ");
}

/* ---------- Site content (editable from the admin panel) ---------- */

function content_load(): array {
    $st = db()->query('SELECT data FROM site_content WHERE id = 1');
    $raw = $st->fetchColumn();
    if ($raw !== false) {
        $d = json_decode((string)$raw, true);
        if (is_array($d)) return $d;
    }
    // First run: seed the database from the default content shipped with the app.
    $seed = read_json(SEED_CONTENT_FILE);
    if ($seed) content_save($seed);
    return $seed;
}

function content_save(array $data): bool {
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) return false;
    $pdo = db(); $now = date('Y-m-d H:i:s');
    $upd = $pdo->prepare('UPDATE site_content SET data = ?, updated_at = ? WHERE id = 1');
    $upd->execute([$json, $now]);
    if ($upd->rowCount() === 0) {
        $chk = $pdo->query('SELECT 1 FROM site_content WHERE id = 1');
        if (!$chk->fetchColumn()) $pdo->prepare('INSERT INTO site_content (id, data, updated_at) VALUES (1, ?, ?)')->execute([$json, $now]);
    }
    return true;
}

/* ---------- Live chat queries ---------- */

/** Last $limit messages of a user's conversation, oldest first. */
function chat_fetch(int $userId, int $limit = 200): array {
    $st = db()->prepare('SELECT t.id, t.sender, t.body, t.created_at FROM (
                            SELECT id, sender, body, created_at FROM chat_messages WHERE user_id = :u ORDER BY id DESC LIMIT :l
                         ) AS t ORDER BY t.id ASC');
    $st->bindValue(':u', $userId, PDO::PARAM_INT);
    $st->bindValue(':l', $limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
}

function chat_add(int $userId, string $sender, string $body): void {
    $st = db()->prepare('INSERT INTO chat_messages (user_id, sender, body, created_at, admin_read) VALUES (?, ?, ?, ?, ?)');
    $st->execute([$userId, $sender, $body, date('Y-m-d H:i:s'), $sender === 'admin' ? 1 : 0]);
}

/** Shape used by both the server-side renderer and the JSON polling endpoints. */
function chat_payload(array $rows): array {
    $msgs = []; $last = 0;
    foreach ($rows as $r) {
        $msgs[] = [
            'from' => $r['sender'] === 'admin' ? 'admin' : 'user',
            'text' => (string)$r['body'],
            'time' => date('d M, h:i A', strtotime((string)$r['created_at']) ?: time()),
        ];
        $last = max($last, (int)$r['id']);
    }
    return ['messages' => $msgs, 'last' => $last];
}
