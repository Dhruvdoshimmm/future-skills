<?php
require_once __DIR__ . '/includes/config.php';

$errors = [];
$old = ['name' => '', 'school' => '', 'email' => '', 'phone' => '', 'message' => ''];
if ($u = current_user()) {   // prefill for logged-in visitors
    $old = ['name' => $u['name'], 'school' => $u['school'], 'email' => $u['email'], 'phone' => $u['phone'], 'message' => ''];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'inquiry_reply') {
    // Visitor replying inside an existing inquiry conversation
    $err = $u ? inquiry_handle_user_reply($u) : 'Please log in to reply.';
    if ($err === null) { flash('Your reply has been sent.'); header('Location: contact.php#inq-' . (int)($_POST['inquiry_id'] ?? 0)); exit; }
    $errors[] = $err;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors[] = 'Session expired. Please try again.';
    } elseif (!empty($_POST['website'])) {
        // Honeypot filled: pretend success, store nothing.
        flash(c('contact.success'));
        header('Location: contact.php'); exit;
    } else {
        foreach ($old as $k => $_) $old[$k] = trim((string)($_POST[$k] ?? ''));

        if ($old['name'] === '' || mb_strlen($old['name']) > 100) $errors[] = 'Please enter your name (max 100 characters).';
        if ($old['school'] === '' || mb_strlen($old['school']) > 150) $errors[] = 'Please enter your school name (max 150 characters).';
        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($old['email']) > 150) $errors[] = 'Please enter a valid email address.';
        if (!preg_match('/^[0-9+\-\s()]{7,20}$/', $old['phone'])) $errors[] = 'Please enter a valid phone number.';
        if (mb_strlen($old['message']) < 5 || mb_strlen($old['message']) > 2000) $errors[] = 'Message must be between 5 and 2000 characters.';

        // Basic rate limit: one submission per 20 seconds per session.
        if (!$errors && time() - ($_SESSION['last_submit'] ?? 0) < 20) $errors[] = 'Please wait a few seconds before sending another message.';

        if (!$errors) {
            try {
                db()->prepare('INSERT INTO inquiries (name, school, email, phone, phone_norm, message, status, created_at)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                    ->execute([$old['name'], $old['school'], $old['email'], $old['phone'],
                               normalize_phone($old['phone']) ?? '', $old['message'], 'Pending', date('Y-m-d H:i:s')]);
                $_SESSION['last_submit'] = time();
                flash(c('contact.success'));
                header('Location: contact.php'); exit;   // Post/Redirect/Get
            } catch (PDOException $e) {
                error_log('Inquiry insert failed: ' . $e->getMessage());
            }
            $errors[] = 'Sorry, we could not save your message. Please try again later.';
        }
    }
}

$pageTitle = 'Contact Us';
require __DIR__ . '/header.php';
$flash = flash();
?>

<section class="page-hero">
    <div class="container">
        <h1><?= e(c('contact.title')) ?></h1>
        <p><?= e(c('contact.subtitle')) ?></p>
    </div>
</section>

<section class="section">
    <div class="container contact-grid">
        <div class="form-card">
            <p class="lead small"><?= e(c('contact.intro')) ?></p>

            <?php if ($flash): ?><div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['msg']) ?></div><?php endif; ?>
            <?php if ($errors): ?>
                <div class="alert alert-error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
            <?php endif; ?>

            <form method="post" action="contact.php" novalidate>
                <?= csrf_field() ?>
                <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">
                <div class="form-row two">
                    <div>
                        <label for="name">Name</label>
                        <input type="text" id="name" name="name" required maxlength="100" value="<?= e($old['name']) ?>">
                    </div>
                    <div>
                        <label for="school">School Name</label>
                        <input type="text" id="school" name="school" required maxlength="150" value="<?= e($old['school']) ?>">
                    </div>
                </div>
                <div class="form-row two">
                    <div>
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" required maxlength="150" value="<?= e($old['email']) ?>">
                    </div>
                    <div>
                        <label for="phone">Phone</label>
                        <input type="tel" id="phone" name="phone" required maxlength="20" value="<?= e($old['phone']) ?>">
                    </div>
                </div>
                <div class="form-row">
                    <label for="message">Message</label>
                    <textarea id="message" name="message" rows="6" required maxlength="2000"><?= e($old['message']) ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Send Message</button>
            </form>
        </div>

        <aside class="info-card">
            <h3>Get in touch</h3>
            <ul class="contact-list dark">
                <li><i class="fa-solid fa-envelope"></i> <?= e(c('site.email')) ?></li>
                <li><i class="fa-solid fa-phone"></i> <?= e(c('site.phone')) ?></li>
                <li><i class="fa-solid fa-location-dot"></i> <?= e(c('site.address')) ?></li>
            </ul>
        </aside>
    </div>
</section>

<section class="section section-alt">
    <div class="container narrow">
        <h2 class="card-heading">Your Inquiries &amp; Replies</h2>
        <?php if (!$u): ?>
            <div class="form-card">
                <p class="muted-dark">Want to see our replies and continue the conversation about your inquiry?
                    <a href="login.php?next=contact.php"><strong>Log in with your phone number</strong></a> (the same number you used in the form above).</p>
            </div>
        <?php else: $mine = user_inquiries($u['phone']); ?>
            <div class="form-card">
                <?php if (!$mine): ?>
                    <p class="muted-dark">No inquiries yet for <?= e($u['phone']) ?>. Send one using the form above and our replies will appear here.</p>
                <?php else: render_user_inquiries($mine, 'contact.php'); endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/footer.php'; ?>
