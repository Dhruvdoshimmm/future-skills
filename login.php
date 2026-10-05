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
    } elseif ($action === 'firebase_login') {
        $phoneInput = trim((string)($_POST['phone'] ?? ''));
        $phone = normalize_phone($phoneInput);
        if (!$phone) {
            $err = 'Please enter a valid mobile number.';
            if (is_ajax()) json_out(['ok' => false, 'error' => $err]);
            $errors[] = $err;
        } else {
            $user = user_login($phone);
            $target = ($user['name'] === '' ? 'account.php?welcome=1&next=' . urlencode($next) : $next);
            if (is_ajax()) {
                json_out(['ok' => true, 'redirect' => $target]);
            }
            header('Location: ' . $target);
            exit;
        }
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
        <p>Access your chat history, inquiries and profile from any device using Firebase Mobile Auth.</p>
    </div>
</section>

<section class="section">
    <div class="container narrow">
        <div class="form-card login-card">
            <!-- Firebase Recaptcha Container -->
            <div id="recaptcha-container"></div>

            <div id="fbAlertBox" class="alert" style="display:none;"></div>

            <?php if ($flash && $pending): ?><div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['msg']) ?></div><?php endif; ?>
            <?php if ($errors): ?><div class="alert alert-error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

            <!-- STEP 1: Phone Entry Section -->
            <div id="fbPhoneSection" style="<?= $pending ? 'display:none;' : 'display:block;' ?>">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.2rem; flex-wrap:wrap; gap:.5rem;">
                    <h2 class="card-heading" style="margin-bottom:0;"><i class="fa-solid fa-mobile-screen"></i> Enter your mobile number</h2>
                    <span class="badge" style="background:#0288d1; padding:.3rem .7rem; font-size:.8rem;"><i class="fa-solid fa-bolt"></i> Firebase Auth</span>
                </div>
                <form id="firebasePhoneForm" method="post" action="login.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="send_otp">
                    <input type="hidden" name="next" value="<?= e($next) ?>">
                    <div class="form-row">
                        <label for="phone">Mobile number</label>
                        <input type="tel" id="phone" name="phone" required maxlength="20" placeholder="98765 43210 or +91 98765 43210" value="<?= e($phoneInput) ?>" autofocus autocomplete="tel">
                    </div>
                    <button id="btnSendOtp" class="btn btn-primary btn-block" type="submit">
                        <i class="fa-solid fa-paper-plane"></i> Send Code via Firebase
                    </button>
                </form>
                <p class="center-note small">New here? Just enter your number: your account is created automatically after verification.</p>
            </div>

            <!-- STEP 2: Code Verification Section -->
            <div id="fbCodeSection" style="<?= $pending ? 'display:block;' : 'display:none;' ?>">
                <h2 class="card-heading"><i class="fa-solid fa-shield-halved"></i> Enter the 6-digit code</h2>
                <p class="muted-dark">We sent a verification code to <strong id="fbPendingPhone"><?= e($pending ?? '') ?></strong>.</p>
                <?php if (!empty($_SESSION['demo_code'])): ?>
                    <div class="alert alert-info"><strong>Demo mode (localhost fallback):</strong> your code is <code><?= e($_SESSION['demo_code']) ?></code></div>
                <?php endif; ?>
                <form id="firebaseCodeForm" method="post" action="login.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="verify">
                    <input type="hidden" name="next" value="<?= e($next) ?>">
                    <div class="form-row">
                        <label for="code">Verification code</label>
                        <input type="text" id="code" name="code" class="otp-input" required inputmode="numeric" pattern="\d{6}" maxlength="6" autocomplete="one-time-code" autofocus>
                    </div>
                    <button id="btnVerifyOtp" class="btn btn-primary btn-block" type="submit">Verify &amp; Login</button>
                </form>
                <div class="login-links">
                    <button id="btnResendOtp" class="link-btn blue" type="button">Resend code</button>
                    <button id="btnChangePhone" class="link-btn blue" type="button">Change number</button>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Firebase SDK & Client App -->
<script>
    window.FIREBASE_CONFIG = <?= firebase_config_json() ?>;
</script>
<script src="https://www.gstatic.com/firebasejs/10.12.0/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.12.0/firebase-auth-compat.js"></script>
<script src="assets/js/firebase-login.js"></script>
<?php require __DIR__ . '/footer.php'; ?>
