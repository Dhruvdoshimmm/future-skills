<?php
require_once __DIR__ . '/includes/config.php';

/* ---------- Polling endpoint (JSON) ---------- */
if (($_GET['poll'] ?? '') === '1') {
    session_write_close();
    $u = current_user();
    if (!$u) json_out(['ok' => false, 'auth' => false, 'messages' => [], 'last' => 0], 401);
    json_out(['ok' => true] + chat_payload(chat_fetch((int)$u['id'])));
}

$user = require_user();
$uid = (int)$user['id'];
$errors = [];


if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send') {
    $text = trim((string)($_POST['message'] ?? ''));
    $error = null;
    if (!csrf_check()) {
        $error = 'Session expired. Please reload the page and try again.';
    } elseif ($text === '' || mb_strlen($text) > 1000) {
        $error = 'Message must be 1-1000 characters.';
    } else {
        $st = db()->prepare("SELECT COUNT(*) FROM chat_messages WHERE user_id = ? AND sender = 'user' AND created_at > ?");
        $st->execute([$uid, date('Y-m-d H:i:s', time() - 60)]);
        if ((int)$st->fetchColumn() >= 20) $error = 'You are sending messages too quickly. Please wait a moment.';
    }

    if ($error === null) {
        chat_add($uid, 'user', $text);
        if (is_ajax()) json_out(['ok' => true] + chat_payload(chat_fetch($uid)));
        header('Location: chat.php'); exit;
    }
    if (is_ajax()) json_out(['ok' => false, 'error' => $error], 400);
    $errors[] = $error;
}

$payload = chat_payload(chat_fetch($uid));
$pageTitle = 'Live Chat';
require __DIR__ . '/header.php';
?>
<section class="page-hero">
    <div class="container">
        <h1>Live Support &amp; Chat</h1>
        <p>Chat directly with the Future Skills team about lab setups and programs.</p>
    </div>
</section>

<section class="section">
    <div class="container narrow">
        <?php if ($errors): ?><div class="alert alert-error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

        <div class="chat-top">
            <p><i class="fa-solid fa-user-check"></i> <strong><?= e($user['name'] !== '' ? $user['name'] : 'Guest') ?></strong> &middot; <?= e($user['phone']) ?>
                <?php if ($user['name'] === ''): ?> &middot; <a href="account.php?welcome=1&amp;next=chat.php">add your name</a><?php endif; ?></p>
            <a class="link-btn blue" href="account.php">My Account</a>
        </div>

        <div class="chat-container">
            <div class="chat-box" id="chatBox" data-me="user" data-me-label="You" data-them-label="Admin Team"
                 data-poll="chat.php?poll=1" data-last="<?= (int)$payload['last'] ?>" data-interval="4000">
                <?php render_chat_messages($payload['messages'], 'user', 'You', 'Admin Team'); ?>
            </div>
            <form method="post" action="chat.php" class="chat-input-form" id="chatForm">
                <?= csrf_field() ?><input type="hidden" name="action" value="send">
                <input type="text" name="message" placeholder="Type your message..." autocomplete="off" maxlength="1000" required>
                <button type="submit"><i class="fa-solid fa-paper-plane"></i> Send</button>
            </form>
        </div>
        <p class="center-note small">Your full conversation is saved to your account. Log in with your phone number from any device to continue it.</p>
    </div>
</section>
<?php require __DIR__ . '/footer.php'; ?>
