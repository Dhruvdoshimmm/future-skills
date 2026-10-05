<?php
require_once __DIR__ . '/includes/config.php';

/* ---------- helpers local to the admin ---------- */

/** Rebuild content from POST, keeping only keys that already exist (structure whitelist). */
function merge_input(array $existing, $input): array {
    $input = is_array($input) ? $input : [];
    $out = [];
    foreach ($existing as $k => $v) {
        if (is_array($v)) {
            $out[$k] = merge_input($v, $input[$k] ?? []);
        } else {
            $val = isset($input[$k]) && is_string($input[$k]) ? trim($input[$k]) : (string)$v;
            $out[$k] = mb_substr(str_replace("\r\n", "\n", $val), 0, 2000);
        }
    }
    return $out;
}

function label(string $key): string { return ucwords(str_replace(['_', '-'], ' ', $key)); }

function render_fields($node, string $name, int $depth = 0): void {
    foreach ($node as $k => $v) {
        $fieldName = $name . '[' . $k . ']';
        if (is_array($v)) {
            $isList = array_is_list($v);
            echo '<fieldset class="fs fs-' . min($depth, 2) . '"><legend>' . e($isList ? label((string)$k) . ' (' . count($v) . ')' : label((string)$k)) . '</legend>';
            render_fields($v, $fieldName, $depth + 1);
            echo '</fieldset>';
        } else {
            $id = 'f_' . md5($fieldName);
            $long = mb_strlen((string)$v) > 70 || str_contains((string)$v, "\n") || in_array($k, ['text', 'a', 'intro', 'success'], true);
            echo '<div class="form-row"><label for="' . $id . '">' . e(is_int($k) ? '#' . ($k + 1) : label((string)$k)) . '</label>';
            if ($long) echo '<textarea id="' . $id . '" name="' . e($fieldName) . '" rows="3">' . e((string)$v) . '</textarea>';
            else echo '<input type="text" id="' . $id . '" name="' . e($fieldName) . '" value="' . e((string)$v) . '">';
            echo '</div>';
        }
    }
}

/* ---------- authentication ---------- */
$loginError = '';
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'login') {
    if (!csrf_check()) {
        $loginError = 'Session expired. Please try again.';
    } elseif (($_SESSION['login_fail'] ?? 0) >= 5 && time() - ($_SESSION['login_fail_at'] ?? 0) < 300) {
        $loginError = 'Too many attempts. Please wait 5 minutes.';
    } else {
        $u = (string)($_POST['username'] ?? '');
        $p = (string)($_POST['password'] ?? '');
        $pwErr = admin_check_password($p);
        if (hash_equals(ADMIN_USER, $u) && $pwErr === null) {
            session_regenerate_id(true);
            $_SESSION['is_admin'] = true;
            $_SESSION['login_fail'] = 0;
            header('Location: admin.php'); exit;
        }
        $_SESSION['login_fail'] = ($_SESSION['login_fail'] ?? 0) + 1;
        $_SESSION['login_fail_at'] = time();
        usleep(500000);
        $loginError = (hash_equals(ADMIN_USER, $u) && $pwErr !== null) ? $pwErr : 'Invalid username or password.';
    }
}

if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: admin.php'); exit;
}

/* ---------- login screen ---------- */
if (!is_admin()) {
    $pageTitle = 'Admin Login';
    require __DIR__ . '/header.php';
    ?>
    <section class="section">
        <div class="container narrow">
            <div class="form-card login-card">
                <h2><i class="fa-solid fa-lock"></i> Admin Login</h2>
                <?php if ($loginError): ?><div class="alert alert-error"><?= e($loginError) ?></div><?php endif; ?>
                <form method="post" action="admin.php">
                    <?= csrf_field() ?><input type="hidden" name="action" value="login">
                    <div class="form-row"><label for="username">Username</label>
                        <input type="text" id="username" name="username" required autocomplete="username"></div>
                    <div class="form-row"><label for="password">Password</label>
                        <input type="password" id="password" name="password" required autocomplete="current-password"></div>
                    <button class="btn btn-primary btn-block" type="submit">Sign In</button>
                </form>
            </div>
        </div>
    </section>
    <?php
    require __DIR__ . '/footer.php';
    exit;
}

/* ---------- chat polling (admin) ---------- */
if (($_GET['poll'] ?? '') === '1') {
    session_write_close();
    $cid = (int)($_GET['c'] ?? 0);
    db()->prepare("UPDATE chat_messages SET admin_read = 1 WHERE user_id = ? AND sender = 'user' AND admin_read = 0")->execute([$cid]);
    json_out(['ok' => true] + chat_payload(chat_fetch($cid)));
}

/* ---------- authenticated actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== 'login') {
    if (!csrf_check()) {
        flash('Security token mismatch. Please try again.', 'error');
        header('Location: admin.php'); exit;
    }

    if ($action === 'save_content') {
        $new = merge_input(content_load(), $_POST['content'] ?? []);
        $ok = content_save($new);
        flash($ok ? 'Content saved successfully.' : 'Could not save the content to the database.', $ok ? 'success' : 'error');
        header('Location: admin.php?tab=content'); exit;
    }

    if ($action === 'chat_reply' || $action === 'chat_delete') {
        $cid = (int)($_POST['id'] ?? 0);
        $back = 'Location: admin.php?tab=chats&c=' . $cid;
        $exists = db()->prepare('SELECT 1 FROM users WHERE id = ?');
        $exists->execute([$cid]);
        if (!$exists->fetchColumn()) {
            if (is_ajax()) json_out(['ok' => false, 'error' => 'Conversation not found.'], 404);
            flash('Conversation not found.', 'error'); header('Location: admin.php?tab=chats'); exit;
        }
        if ($action === 'chat_reply') {
            $text = trim((string)($_POST['text'] ?? ''));
            if ($text === '' || mb_strlen($text) > 1000) {
                if (is_ajax()) json_out(['ok' => false, 'error' => 'Reply must be 1-1000 characters.'], 400);
                flash('Reply must be 1-1000 characters.', 'error'); header($back); exit;
            }
            chat_add($cid, 'admin', $text);
            db()->prepare("UPDATE chat_messages SET admin_read = 1 WHERE user_id = ? AND sender = 'user'")->execute([$cid]);
            if (is_ajax()) json_out(['ok' => true] + chat_payload(chat_fetch($cid)));
            header($back); exit;
        }
        db()->prepare('DELETE FROM chat_messages WHERE user_id = ?')->execute([$cid]);
        flash('Conversation deleted.');
        header('Location: admin.php?tab=chats'); exit;
    }

    if (in_array($action, ['reply', 'mark_pending', 'mark_replied', 'delete'], true)) {
        $id = (int)($_POST['id'] ?? 0);
        $pdo = db(); $now = date('Y-m-d H:i:s');
        $chk = $pdo->prepare('SELECT 1 FROM inquiries WHERE id = ?'); $chk->execute([$id]);
        if (!$chk->fetchColumn()) {
            flash('Message not found.', 'error');
        } elseif ($action === 'reply') {
            $text = trim((string)($_POST['reply'] ?? ''));
            if ($text === '') {
                flash('Reply text cannot be empty.', 'error');
            } else {
                inquiry_add_reply($id, 'admin', mb_substr($text, 0, 2000));
                flash('Reply sent. The visitor can see it and reply back from their account.');
            }
        } elseif ($action === 'mark_pending') {
            $pdo->prepare("UPDATE inquiries SET status = 'Pending', replied_at = NULL WHERE id = ?")->execute([$id]);
            flash('Message marked as Pending.');
        } elseif ($action === 'mark_replied') {
            $pdo->prepare("UPDATE inquiries SET status = 'Replied', replied_at = ? WHERE id = ?")->execute([$now, $id]);
            flash('Message marked as Replied.');
        } else {
            $pdo->prepare('DELETE FROM inquiries WHERE id = ?')->execute([$id]);
            flash('Message deleted.');
        }
        header('Location: admin.php?tab=messages'); exit;
    }
}

/* ---------- dashboard ---------- */
$tab = in_array($_GET['tab'] ?? '', ['content', 'chats'], true) ? $_GET['tab'] : 'messages';
$allChats = db()->query("SELECT u.id, u.name, u.school, u.phone, u.email,
        (SELECT MAX(id) FROM chat_messages WHERE user_id = u.id) AS last_id,
        (SELECT body FROM chat_messages WHERE user_id = u.id ORDER BY id DESC LIMIT 1) AS last_body,
        (SELECT COUNT(*) FROM chat_messages WHERE user_id = u.id AND sender = 'user' AND admin_read = 0) AS unread
    FROM users u WHERE EXISTS (SELECT 1 FROM chat_messages WHERE user_id = u.id) ORDER BY last_id DESC")->fetchAll();
$unreadChats = count(array_filter($allChats, fn($c) => (int)$c['unread'] > 0));
$selChat = null;
if ($tab === 'chats' && $allChats) {
    $selId = (int)($_GET['c'] ?? 0);
    foreach ($allChats as $c) { if ((int)$c['id'] === $selId) { $selChat = $c; break; } }
    $selChat = $selChat ?? $allChats[0];
    if ((int)$selChat['unread'] > 0) {
        db()->prepare("UPDATE chat_messages SET admin_read = 1 WHERE user_id = ? AND sender = 'user'")->execute([$selChat['id']]);
        $unreadChats = max(0, $unreadChats - 1);
    }
}
$content = content_load();
$filter = $_GET['status'] ?? 'all';
$pending = (int)db()->query("SELECT COUNT(*) FROM inquiries WHERE status = 'Pending'")->fetchColumn();
if (in_array($filter, ['Pending', 'Replied'], true)) {
    $st = db()->prepare('SELECT * FROM inquiries WHERE status = ? ORDER BY id DESC'); $st->execute([$filter]);
} else {
    $st = db()->query('SELECT * FROM inquiries ORDER BY id DESC');
}
$messages = inquiries_attach_threads($st->fetchAll());

$pageTitle = 'Admin Panel';
require __DIR__ . '/header.php';
$flash = flash();
?>
<section class="page-hero slim">
    <div class="container admin-top">
        <h1>Admin Panel</h1>
        <a class="btn btn-outline" href="admin.php?logout=1"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>
</section>

<section class="section">
    <div class="container">
        <?php if ($flash): ?><div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['msg']) ?></div><?php endif; ?>

        <div class="tabs">
            <a href="admin.php?tab=messages" class="<?= $tab === 'messages' ? 'active' : '' ?>"><i class="fa-solid fa-inbox"></i> Inquiries <span class="badge"><?= $pending ?> pending</span></a>
            <a href="admin.php?tab=chats" class="<?= $tab === 'chats' ? 'active' : '' ?>"><i class="fa-solid fa-comments"></i> Live Chat <?php if ($unreadChats): ?><span class="badge"><?= $unreadChats ?> new</span><?php endif; ?></a>
            <a href="admin.php?tab=content" class="<?= $tab === 'content' ? 'active' : '' ?>"><i class="fa-solid fa-pen-to-square"></i> Content Management</a>
        </div>

<?php if ($tab === 'messages'): ?>
        <div class="filters">
            <?php foreach (['all' => 'All', 'Pending' => 'Pending', 'Replied' => 'Replied'] as $k => $lbl): ?>
                <a href="admin.php?tab=messages&status=<?= e($k) ?>" class="chip <?= $filter === $k ? 'active' : '' ?>"><?= e($lbl) ?></a>
            <?php endforeach; ?>
        </div>

        <?php if (!$messages): ?>
            <p class="empty">No messages to show.</p>
        <?php else: ?>
        <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Date</th><th>Sender</th><th>Message</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($messages as $m): $isPending = ($m['status'] ?? '') === 'Pending'; ?>
                <tr>
                    <td data-label="Date"><?= e($m['created_at'] ?? '') ?></td>
                    <td data-label="Sender">
                        <strong><?= e($m['name'] ?? '') ?></strong><br>
                        <?php if (!empty($m['school'])): ?><em><?= e($m['school']) ?></em><br><?php endif; ?>
                        <a href="mailto:<?= e($m['email'] ?? '') ?>"><?= e($m['email'] ?? '') ?></a><br>
                        <small><?= e($m['phone'] ?? '') ?></small>
                    </td>
                    <td data-label="Message">
                        <?php render_inquiry_thread($m, 'admin', 'You (Admin)', (string)$m['name']); ?>
                    </td>
                    <td data-label="Status"><span class="status <?= $isPending ? 'pending' : 'replied' ?>"><?= e($m['status'] ?? 'Pending') ?></span></td>
                    <td data-label="Actions" class="actions">
                        <details class="reply-form">
                            <summary class="btn btn-sm btn-primary"><i class="fa-solid fa-reply"></i> Reply</summary>
                            <form method="post" action="admin.php">
                                <?= csrf_field() ?><input type="hidden" name="action" value="reply"><input type="hidden" name="id" value="<?= e($m['id'] ?? '') ?>">
                                <textarea name="reply" rows="4" placeholder="Type your reply..." required></textarea>
                                <div class="reply-btns">
                                    <button class="btn btn-sm btn-primary" type="submit">Send Reply</button>
                                    <a class="btn btn-sm btn-outline-dark" href="mailto:<?= e($m['email'] ?? '') ?>?subject=<?= rawurlencode('Re: Your inquiry - ' . c('site.brand')) ?>"><i class="fa-solid fa-envelope"></i> Open in Email</a>
                                </div>
                            </form>
                        </details>
                        <form method="post" action="admin.php" class="inline">
                            <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($m['id'] ?? '') ?>">
                            <?php if ($isPending): ?>
                                <button class="btn btn-sm btn-outline-dark" name="action" value="mark_replied" type="submit">Mark Replied</button>
                            <?php else: ?>
                                <button class="btn btn-sm btn-outline-dark" name="action" value="mark_pending" type="submit">Mark Pending</button>
                            <?php endif; ?>
                            <button class="btn btn-sm btn-danger" name="action" value="delete" type="submit" onclick="return confirm('Delete this message permanently?')"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>

<?php elseif ($tab === 'chats'): ?>
        <?php if (!$selChat): ?>
            <p class="empty">No chat conversations yet. They will appear here when logged-in visitors send a message.</p>
        <?php else: $selPayload = chat_payload(chat_fetch((int)$selChat['id'])); $selName = $selChat['name'] !== '' ? $selChat['name'] : $selChat['phone']; ?>
        <div class="chat-admin">
            <aside class="chat-list">
                <?php foreach ($allChats as $c): $isSel = (int)$c['id'] === (int)$selChat['id']; ?>
                    <a href="admin.php?tab=chats&amp;c=<?= (int)$c['id'] ?>" class="chat-item <?= $isSel ? 'active' : '' ?>">
                        <strong><?= e($c['name'] !== '' ? $c['name'] : $c['phone']) ?><?php if ((int)$c['unread'] > 0 && !$isSel): ?> <span class="dot"></span><?php endif; ?></strong>
                        <small><?= e(trim($c['school'] . ' ' . ($c['name'] !== '' ? '· ' . $c['phone'] : ''), ' ·')) ?></small>
                        <span class="preview"><?= e(mb_strimwidth((string)$c['last_body'], 0, 60, '...')) ?></span>
                    </a>
                <?php endforeach; ?>
            </aside>
            <section class="chat-thread">
                <div class="chat-head">
                    <div><strong><?= e($selName) ?></strong><?= $selChat['school'] !== '' ? ' &middot; ' . e($selChat['school']) : '' ?><br>
                        <small><a href="tel:<?= e($selChat['phone']) ?>"><?= e($selChat['phone']) ?></a>
                        <?php if ($selChat['email'] !== ''): ?> &middot; <a href="mailto:<?= e($selChat['email']) ?>"><?= e($selChat['email']) ?></a><?php endif; ?></small></div>
                    <form method="post" action="admin.php" onsubmit="return confirm('Delete this whole conversation?')">
                        <?= csrf_field() ?><input type="hidden" name="action" value="chat_delete"><input type="hidden" name="id" value="<?= (int)$selChat['id'] ?>">
                        <button class="btn btn-sm btn-danger" type="submit"><i class="fa-solid fa-trash"></i></button>
                    </form>
                </div>
                <div class="chat-container">
                    <div class="chat-box" id="chatBox" data-me="admin" data-me-label="You (Admin)" data-them-label="<?= e($selName) ?>"
                         data-poll="admin.php?tab=chats&amp;c=<?= (int)$selChat['id'] ?>&amp;poll=1" data-last="<?= (int)$selPayload['last'] ?>" data-interval="5000">
                        <?php render_chat_messages($selPayload['messages'], 'admin', 'You (Admin)', $selName); ?>
                    </div>
                    <form method="post" action="admin.php" class="chat-input-form" id="chatForm">
                        <?= csrf_field() ?><input type="hidden" name="action" value="chat_reply"><input type="hidden" name="id" value="<?= (int)$selChat['id'] ?>">
                        <input type="text" name="text" placeholder="Type your reply..." autocomplete="off" maxlength="1000" required>
                        <button type="submit"><i class="fa-solid fa-paper-plane"></i> Send</button>
                    </form>
                </div>
            </section>
        </div>
        <?php endif; ?>

<?php else: ?>
        <form method="post" action="admin.php" class="content-form">
            <?= csrf_field() ?><input type="hidden" name="action" value="save_content">
            <p class="muted-dark">Edit any text or title below. Changes are written directly to <code>data/content.json</code> and appear on the site immediately.</p>
            <?php foreach ($content as $section => $fields): ?>
                <details class="sec" <?= $section === 'home' ? 'open' : '' ?>>
                    <summary><?= e(label((string)$section)) ?> <?= in_array($section, ['site'], true) ? '(Header / Footer / Contact details)' : 'Page' ?></summary>
                    <div class="sec-body"><?php render_fields($fields, 'content[' . $section . ']'); ?></div>
                </details>
            <?php endforeach; ?>
            <div class="save-bar"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save All Changes</button></div>
        </form>
<?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/footer.php'; ?>
