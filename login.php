<?php
require_once __DIR__ . '/includes/config.php';

$next = safe_next($_GET['next'] ?? $_POST['next'] ?? null);
if (current_user()) { header('Location: ' . $next); exit; }

$errors = [];
$phoneInput = '';
$action = (string)($_POST['action'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors[] = 'Session expired. Please try again.';
    } elseif ($action === 'send_otp' || $action === 'resend') {
        $phoneInput = trim((string)($_POST['phone'] ?? ''));
        $phone = $action === 'resend' ? ($_SESSION['otp_phone'] ?? null) : normalize_phone($phoneInput);
        if (!$phone) {
            $errors[] = 'Please enter a valid mobile number (10-digit Indian number, or +country code).';
        } else {
            $res = otp_request($phone);
            if ($res['ok']) {
                $_SESSION['otp_phone'] = $phone;
                if (isset($res['demo_code'])) $_SESSION['demo_code'] = $res['demo_code'];
                flash('A 6-digit code has been sent to ' . $phone . '.');
            } else {
                $errors[] = $res['error'];
            }
        }
    } elseif ($action === 'verify') {
        $phone = $_SESSION['otp_phone'] ?? null;
        $code = trim((string)($_POST['code'] ?? ''));
        if (!$phone) {
            $errors[] = 'Please enter your phone number first.';
        } elseif (!preg_match('/^\d{6}$/', $code)) {
            $errors[] = 'Enter the 6-digit code.';
        } elseif (($err = otp_verify($phone, $code)) !== null) {
            $errors[] = $err;
        } else {
            $user = user_login($phone);
            // New visitors complete their profile first, then continue.
            header('Location: ' . ($user['name'] === '' ? 'account.php?welcome=1&next=' . urlencode($next) : $next));
            exit;
        }
    } elseif ($action === 'change') {
        unset($_SESSION['otp_phone'], $_SESSION['demo_code']);
    }
}

$pending = $_SESSION['otp_phone'] ?? null;
$flash = flash();
$pageTitle = 'Login';
require __DIR__ . '/header.php';
?>
<section class="page-hero">
    <div class="container">
        <h1>Login with your Phone</h1>
        <p>Access your chat history, inquiries and profile from any device.</p>
    </div>
</section>

<section class="section">
    <div class="container narrow">
        <div class="form-card login-card">
            <?php if ($flash && $pending): ?><div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['msg']) ?></div><?php endif; ?>
            <?php if ($errors): ?><div class="alert alert-error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

            <?php if (!$pending): ?>
                <h2 class="card-heading"><i class="fa-solid fa-mobile-screen"></i> Enter your mobile number</h2>
                <form method="post" action="login.php">
                    <?= csrf_field() ?><input type="hidden" name="action" value="send_otp"><input type="hidden" name="next" value="<?= e($next) ?>">
                    <div class="form-row"><label for="phone">Mobile number</label>
                        <input type="tel" id="phone" name="phone" required maxlength="20" placeholder="98765 43210 or +91 98765 43210" value="<?= e($phoneInput) ?>" autofocus autocomplete="tel"></div>
                    <button class="btn btn-primary btn-block" type="submit"><i class="fa-solid fa-message"></i> Send Code</button>
                </form>
                <p class="center-note small">New here? Just enter your number: your account is created automatically after verification.</p>
            <?php else: ?>
                <h2 class="card-heading"><i class="fa-solid fa-shield-halved"></i> Enter the 6-digit code</h2>
                <p class="muted-dark">We sent a code to <strong><?= e($pending) ?></strong>.</p>
                <?php if (!empty($_SESSION['demo_code'])): ?>
                    <div class="alert alert-info"><strong>Demo mode (localhost only):</strong> your code is <code><?= e($_SESSION['demo_code']) ?></code></div>
                <?php endif; ?>
                <form method="post" action="login.php">
                    <?= csrf_field() ?><input type="hidden" name="action" value="verify"><input type="hidden" name="next" value="<?= e($next) ?>">
                    <div class="form-row"><label for="code">Verification code</label>
                        <input type="text" id="code" name="code" class="otp-input" required inputmode="numeric" pattern="\d{6}" maxlength="6" autocomplete="one-time-code" autofocus></div>
                    <button class="btn btn-primary btn-block" type="submit">Verify &amp; Login</button>
                </form>
                <div class="login-links">
                    <form method="post" action="login.php"><?= csrf_field() ?><input type="hidden" name="action" value="resend"><input type="hidden" name="next" value="<?= e($next) ?>"><button class="link-btn blue" type="submit">Resend code</button></form>
                    <form method="post" action="login.php"><?= csrf_field() ?><input type="hidden" name="action" value="change"><input type="hidden" name="next" value="<?= e($next) ?>"><button class="link-btn blue" type="submit">Change number</button></form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php require __DIR__ . '/footer.php'; ?>
