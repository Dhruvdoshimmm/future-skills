<?php
/**
 * Inquiry conversations: the visitor's first message (inquiries.message) plus any number of
 * replies from the visitor and the admin (inquiry_replies).
 */
declare(strict_types=1);

const INQUIRY_MAX_REPLIES = 200;

/** Add a reply. Admin replies mark the inquiry "Replied"; visitor replies put it back to "Pending". */
function inquiry_add_reply(int $inquiryId, string $sender, string $body): void {
    $pdo = db(); $now = date('Y-m-d H:i:s');
    $pdo->prepare('INSERT INTO inquiry_replies (inquiry_id, sender, body, created_at) VALUES (?, ?, ?, ?)')
        ->execute([$inquiryId, $sender, $body, $now]);
    if ($sender === 'admin') {
        $pdo->prepare("UPDATE inquiries SET status = 'Replied', replied_at = ? WHERE id = ?")->execute([$now, $inquiryId]);
    } else {
        $pdo->prepare("UPDATE inquiries SET status = 'Pending' WHERE id = ?")->execute([$inquiryId]);
    }
}

/** Attach ['thread' => [...replies]] to each inquiry row (one query for all). */
function inquiries_attach_threads(array $inqs): array {
    if (!$inqs) return $inqs;
    $ids = array_map(fn($i) => (int)$i['id'], $inqs);
    $st = db()->prepare('SELECT inquiry_id, sender, body, created_at FROM inquiry_replies WHERE inquiry_id IN ('
        . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY id ASC');
    $st->execute($ids);
    $by = [];
    foreach ($st->fetchAll() as $r) $by[(int)$r['inquiry_id']][] = $r;
    foreach ($inqs as &$i) $i['thread'] = $by[(int)$i['id']] ?? [];
    unset($i);
    return $inqs;
}

function user_inquiries(string $phone): array {
    $st = db()->prepare('SELECT * FROM inquiries WHERE phone_norm = ? ORDER BY id DESC');
    $st->execute([$phone]);
    return inquiries_attach_threads($st->fetchAll());
}

/** Whole conversation as [from,text,time] items, oldest first. */
function inquiry_messages(array $inq): array {
    $fmt = fn($t) => date('d M, h:i A', strtotime((string)$t) ?: time());
    $out = [['from' => 'user', 'text' => (string)$inq['message'], 'time' => $fmt($inq['created_at'])]];
    // Older single-reply format (before conversations existed)
    if (!empty($inq['reply'])) $out[] = ['from' => 'admin', 'text' => (string)$inq['reply'], 'time' => $fmt($inq['replied_at'] ?? $inq['created_at'])];
    foreach ($inq['thread'] ?? [] as $r) {
        $out[] = ['from' => $r['sender'] === 'admin' ? 'admin' : 'user', 'text' => (string)$r['body'], 'time' => $fmt($r['created_at'])];
    }
    return $out;
}

function render_inquiry_thread(array $inq, string $me, string $meLabel, string $themLabel): void {
    echo '<div class="thread">';
    render_chat_messages(inquiry_messages($inq), $me, $meLabel, $themLabel);
    echo '</div>';
}

/** Handle a visitor's reply POST. Returns an error message, or null on success. */
function inquiry_handle_user_reply(array $user): ?string {
    if (!csrf_check()) return 'Session expired. Please try again.';
    $id = (int)($_POST['inquiry_id'] ?? 0);
    $text = trim((string)($_POST['reply_text'] ?? ''));
    if ($text === '' || mb_strlen($text) > 2000) return 'Reply must be 1-2000 characters.';

    $pdo = db();
    $st = $pdo->prepare('SELECT id FROM inquiries WHERE id = ? AND phone_norm = ?');   // only your own inquiries
    $st->execute([$id, $user['phone']]);
    if (!$st->fetchColumn()) return 'Inquiry not found.';

    $st = $pdo->prepare('SELECT COUNT(*) FROM inquiry_replies WHERE inquiry_id = ?');
    $st->execute([$id]);
    if ((int)$st->fetchColumn() >= INQUIRY_MAX_REPLIES) return 'This conversation has reached its reply limit. Please send a new inquiry.';

    $st = $pdo->prepare("SELECT COUNT(*) FROM inquiry_replies r JOIN inquiries i ON i.id = r.inquiry_id
                         WHERE i.phone_norm = ? AND r.sender = 'user' AND r.created_at > ?");
    $st->execute([$user['phone'], date('Y-m-d H:i:s', time() - 60)]);
    if ((int)$st->fetchColumn() >= 10) return 'You are replying too quickly. Please wait a moment.';

    inquiry_add_reply($id, 'user', $text);
    return null;
}

/** The visitor's inquiries as conversation cards with a reply box on each. */
function render_user_inquiries(array $inqs, string $formAction): void {
    foreach ($inqs as $m):
        $pending = ($m['status'] ?? '') === 'Pending'; ?>
        <div class="inquiry" id="inq-<?= (int)$m['id'] ?>">
            <div class="inq-top"><small>Inquiry #<?= (int)$m['id'] ?> &middot; <?= e($m['created_at']) ?></small>
                <span class="status <?= $pending ? 'pending' : 'replied' ?>"><?= $pending ? 'Awaiting reply' : 'Replied' ?></span></div>
            <?php render_inquiry_thread($m, 'user', 'You', 'Admin Team'); ?>
            <form method="post" action="<?= e($formAction) ?>" class="inq-reply">
                <?= csrf_field() ?><input type="hidden" name="action" value="inquiry_reply"><input type="hidden" name="inquiry_id" value="<?= (int)$m['id'] ?>">
                <textarea name="reply_text" rows="2" maxlength="2000" placeholder="Write a reply..." required></textarea>
                <button class="btn btn-sm btn-primary" type="submit"><i class="fa-solid fa-reply"></i> Send Reply</button>
            </form>
        </div>
    <?php endforeach;
}
