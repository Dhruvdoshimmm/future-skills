(function () {
  'use strict';

  if (typeof firebase === 'undefined' || !window.FIREBASE_CONFIG) {
    console.error('Firebase SDK or configuration is missing.');
    return;
  }

  // Initialize Firebase if not already initialized
  if (!firebase.apps.length) {
    firebase.initializeApp(window.FIREBASE_CONFIG);
  }

  var auth = firebase.auth();
  auth.useDeviceLanguage();

  var phoneForm = document.getElementById('firebasePhoneForm');
  var codeForm = document.getElementById('firebaseCodeForm');
  var phoneSection = document.getElementById('fbPhoneSection');
  var codeSection = document.getElementById('fbCodeSection');
  var alertBox = document.getElementById('fbAlertBox');
  var pendingPhoneSpan = document.getElementById('fbPendingPhone');
  var btnSendOtp = document.getElementById('btnSendOtp');
  var btnVerifyOtp = document.getElementById('btnVerifyOtp');
  var btnResendOtp = document.getElementById('btnResendOtp');
  var btnChangePhone = document.getElementById('btnChangePhone');
  var recaptchaContainer = document.getElementById('recaptcha-container');

  var confirmationResult = null;
  var currentPhone = '';

  function showAlert(msg, type) {
    if (!alertBox) return;
    alertBox.className = 'alert alert-' + (type || 'error');
    alertBox.textContent = msg;
    alertBox.style.display = 'block';
  }

  function hideAlert() {
    if (!alertBox) return;
    alertBox.style.display = 'none';
    alertBox.textContent = '';
  }

  function normalizePhone(raw) {
    var s = (raw || '').trim().replace(/[\s\-().]/g, '');
    if (!s) return null;
    if (s.charAt(0) === '+') {
      return /^\+[1-9]\d{7,14}$/.test(s) ? s : null;
    }
    if (/^\d+$/.test(s)) {
      if (s.length === 10 && parseInt(s.charAt(0), 10) >= 6) return '+91' + s;
      if (s.length === 11 && s.charAt(0) === '0' && parseInt(s.charAt(1), 10) >= 6) return '+91' + s.substring(1);
      if (s.length === 12 && s.indexOf('91') === 0 && parseInt(s.charAt(2), 10) >= 6) return '+' + s;
    }
    return null;
  }

  function initRecaptcha() {
    if (window.recaptchaVerifier) {
      try { window.recaptchaVerifier.clear(); } catch (e) {}
    }
    window.recaptchaVerifier = new firebase.auth.RecaptchaVerifier(recaptchaContainer, {
      size: 'invisible',
      callback: function () {
        // reCAPTCHA solved
      },
      'expired-callback': function () {
        showAlert('reCAPTCHA expired. Please click Send Code again.', 'error');
      }
    });
  }

  // Initialise reCAPTCHA
  initRecaptcha();

  if (phoneForm) {
    phoneForm.addEventListener('submit', function (e) {
      e.preventDefault();
      hideAlert();

      var phoneInput = document.getElementById('phone');
      var rawPhone = phoneInput ? phoneInput.value : '';
      var formattedPhone = normalizePhone(rawPhone);

      if (!formattedPhone) {
        showAlert('Please enter a valid 10-digit mobile number or full international format (+919876543210).', 'error');
        return;
      }

      currentPhone = formattedPhone;
      btnSendOtp.disabled = true;
      btnSendOtp.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Sending OTP...';

      auth.signInWithPhoneNumber(formattedPhone, window.recaptchaVerifier)
        .then(function (result) {
          confirmationResult = result;
          btnSendOtp.disabled = false;
          btnSendOtp.innerHTML = '<i class="fa-solid fa-message"></i> Send Code';

          if (pendingPhoneSpan) pendingPhoneSpan.textContent = currentPhone;
          if (phoneSection) phoneSection.style.display = 'none';
          if (codeSection) codeSection.style.display = 'block';

          var codeInput = document.getElementById('code');
          if (codeInput) { codeInput.value = ''; codeInput.focus(); }
          showAlert('Verification code sent to ' + currentPhone + ' via SMS.', 'success');
        })
        .catch(function (error) {
          console.error('Firebase Auth Error:', error);
          btnSendOtp.disabled = false;
          btnSendOtp.innerHTML = '<i class="fa-solid fa-message"></i> Send Code';

          // Reset reCAPTCHA widget on error
          initRecaptcha();

          var msg = 'Could not send SMS code.';
          if (error.code === 'auth/invalid-phone-number') {
            msg = 'The phone number format is invalid. Please check the number.';
          } else if (error.code === 'auth/too-many-requests') {
            msg = 'Too many attempts. Please wait a few minutes before trying again.';
          } else if (error.code === 'auth/captcha-check-failed') {
            msg = 'reCAPTCHA verification failed. Please try again.';
          } else if (error.message) {
            msg = error.message;
          }
          showAlert(msg, 'error');
        });
    });
  }

  if (codeForm) {
    codeForm.addEventListener('submit', function (e) {
      e.preventDefault();
      hideAlert();

      var codeInput = document.getElementById('code');
      var code = codeInput ? codeInput.value.trim() : '';

      if (!code || code.length !== 6 || !/^\d{6}$/.test(code)) {
        showAlert('Please enter the full 6-digit verification code.', 'error');
        return;
      }

      if (!confirmationResult) {
        showAlert('Session expired. Please request a new code.', 'error');
        return;
      }

      btnVerifyOtp.disabled = true;
      btnVerifyOtp.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Verifying...';

      confirmationResult.confirm(code)
        .then(function (result) {
          var user = result.user;
          user.getIdToken().then(function (idToken) {
            // Send verified status to backend login.php
            var csrfToken = document.querySelector('input[name="csrf"]');
            var nextInput = document.querySelector('input[name="next"]');

            var formData = new FormData();
            formData.append('action', 'firebase_login');
            formData.append('phone', currentPhone || user.phoneNumber);
            formData.append('idToken', idToken);
            if (csrfToken) formData.append('csrf', csrfToken.value);
            if (nextInput) formData.append('next', nextInput.value);

            fetch('login.php', {
              method: 'POST',
              body: formData,
              headers: { 'X-Requested-With': 'fetch' },
              credentials: 'same-origin'
            })
              .then(function (res) { return res.json(); })
              .then(function (data) {
                if (data.ok && data.redirect) {
                  window.location.href = data.redirect;
                } else if (data.error) {
                  showAlert(data.error, 'error');
                  btnVerifyOtp.disabled = false;
                  btnVerifyOtp.innerHTML = 'Verify &amp; Login';
                } else {
                  window.location.reload();
                }
              })
              .catch(function (err) {
                console.error('Server sync error:', err);
                showAlert('Verified with Firebase! Redirecting...', 'success');
                window.location.href = nextInput ? nextInput.value : 'account.php';
              });
          });
        })
        .catch(function (error) {
          console.error('Code confirmation error:', error);
          btnVerifyOtp.disabled = false;
          btnVerifyOtp.innerHTML = 'Verify &amp; Login';

          var msg = 'Invalid verification code.';
          if (error.code === 'auth/invalid-verification-code') {
            msg = 'The code you entered is incorrect. Please check and try again.';
          } else if (error.code === 'auth/code-expired') {
            msg = 'This code has expired. Please request a new code.';
          } else if (error.message) {
            msg = error.message;
          }
          showAlert(msg, 'error');
        });
    });
  }

  if (btnResendOtp) {
    btnResendOtp.addEventListener('click', function (e) {
      e.preventDefault();
      if (phoneForm) {
        phoneForm.dispatchEvent(new Event('submit'));
      }
    });
  }

  if (btnChangePhone) {
    btnChangePhone.addEventListener('click', function (e) {
      e.preventDefault();
      hideAlert();
      confirmationResult = null;
      if (codeSection) codeSection.style.display = 'none';
      if (phoneSection) phoneSection.style.display = 'block';
      var phoneInput = document.getElementById('phone');
      if (phoneInput) { phoneInput.focus(); phoneInput.select(); }
    });
  }
})();
