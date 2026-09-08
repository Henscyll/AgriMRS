<?php
session_start();
include('includes/db_connection.php');

// Optional: Set your local timezone explicitly if not set in php.ini
date_default_timezone_set('Asia/Manila');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

$message     = "";
$messageType = "";
$step        = "email";

/**
 * Helper function to send email via SMTP
 */
function sendResetEmail($recipientEmail, $code) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'feinghuatan148@gmail.com'; // Change to your actual Gmail address
        $mail->Password   = 'ptpd uubt dpjb qlgm';          // Your App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('feinghuatan148@gmail.com', 'AMRMS Support');
        $mail->addAddress($recipientEmail);

        $mail->isHTML(true);
        $mail->Subject = 'Your Password Reset Code - AMRMS';
        $mail->Body    = "
            <div style='font-family: Arial, sans-serif; padding: 20px; color: #333;'>
                <h2 style='color: #2d8a2d;'>Password Reset Request</h2>
                <p>You requested a password reset. Use the code below to reset your password:</p>
                <div style='background: #f0fdf0; border: 1px solid #bbf7d0; padding: 15px; font-size: 24px; font-weight: bold; letter-spacing: 5px; text-align: center; color: #1a5e1a; width: 200px; border-radius: 8px;'>
                    {$code}
                </div>
                <p style='margin-top: 20px; font-size: 12px; color: #777;'>This code will expire in 15 minutes. If you did not request this, please ignore this email.</p>
            </div>
        ";
        $mail->AltBody = "Your 6-digit password reset code is: {$code}";

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/* ── STEP 1: Send reset code ── */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['send_code'])) {
    $email = trim($_POST['email']);
    $found = false;

    $tables = [
        "SELECT id FROM farmers      WHERE email = ?",
        "SELECT id FROM operators    WHERE email = ?",
        "SELECT id FROM associations WHERE email = ?",
        "SELECT id FROM presidents   WHERE email = ?",
        "SELECT id FROM users        WHERE email = ?",
    ];
    foreach ($tables as $sql) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) { 
            $found = true; 
            $stmt->close(); 
            break; 
        }
        $stmt->close();
    }

    if ($found) {
        $code    = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        $del = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
        $del->bind_param("s", $email);
        $del->execute();
        $del->close();

        $ins = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
        $ins->bind_param("sss", $email, $code, $expires);
        $ins->execute();
        $ins->close();

        if (sendResetEmail($email, $code)) {
            $_SESSION['reset_email'] = $email;
            $step        = "code";
            $message     = "A 6-digit reset code has been sent to <strong>" . htmlspecialchars($email) . "</strong>.";
            $messageType = "success";
        } else {
            $message     = "Failed to send the reset email. Please check server connection.";
            $messageType = "error";
            $step        = "email";
        }
    } else {
        $message     = "Email not found. Please check and try again.";
        $messageType = "error";
        $step        = "email";
    }
}

/* ── STEP 2: Verify code ── */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['verify_code'])) {
    $email     = trim($_POST['email'] ?? $_SESSION['reset_email'] ?? '');
    $inputCode = trim($_POST['code']);
    $now       = date('Y-m-d H:i:s');
    $step      = "code";

    $stmt = $conn->prepare(
        "SELECT id FROM password_resets
         WHERE email = ? AND token = ? AND expires_at > ?
         ORDER BY id DESC LIMIT 1"
    );
    $stmt->bind_param("sss", $email, $inputCode, $now);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->close();
        $newToken = bin2hex(random_bytes(32));
        $upd = $conn->prepare("UPDATE password_resets SET token = ? WHERE email = ? AND token = ?");
        $upd->bind_param("sss", $newToken, $email, $inputCode);
        $upd->execute();
        $upd->close();

        header("Location: reset_password.php?token=$newToken");
        exit;
    } else {
        $stmt->close();
        $message     = "Invalid or expired code. Please try again.";
        $messageType = "error";
        $step        = "code";
    }
}

/* ── RESEND ── */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['resend_code'])) {
    $email = trim($_POST['email'] ?? $_SESSION['reset_email'] ?? '');
    if ($email) {
        $code    = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        $del = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
        $del->bind_param("s", $email);
        $del->execute();
        $del->close();

        $ins = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
        $ins->bind_param("sss", $email, $code, $expires);
        $ins->execute();
        $ins->close();

        if (sendResetEmail($email, $code)) {
            $_SESSION['reset_email'] = $email;
            $step        = "code";
            $message     = "A new code has been sent to <strong>" . htmlspecialchars($email) . "</strong>.";
            $messageType = "success";
        } else {
            $step        = "code";
            $message     = "Failed to resend email. Please try again.";
            $messageType = "error";
        }
    }
}

$resetEmail = $_SESSION['reset_email'] ?? $_POST['email'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password – AMRMS</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --green-dark:  #1a5e1a;
    --green-main:  #2d8a2d;
    --green-glow:  rgba(45,138,45,0.15);
    --green-light: #e8f5e8;
    --gray-bg:     #f4f7f4;
    --gray-border: #d1e8d1;
    --text-dark:   #1a2e1a;
    --text-mid:    #4a6a4a;
    --text-muted:  #7a9a7a;
    --error:       #dc2626;
    --error-bg:    #fef2f2;
    --success-bg:  #f0fdf0;
  }

  html, body {
    min-height: 100vh;
    font-family: 'Poppins', sans-serif;
    background: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  /* ── Card ── */
  .card {
    background: #fff;
    border-radius: 20px;
    width: 420px;
    max-width: calc(100vw - 32px);
    box-shadow: 0 8px 40px rgba(0,0,0,0.12), 0 0 0 1px rgba(0,0,0,0.06);
    overflow: hidden;
    animation: slideUp 0.4s cubic-bezier(0.22,1,0.36,1) both;
  }
  @keyframes slideUp {
    from { opacity: 0; transform: translateY(30px); }
    to   { opacity: 1; transform: translateY(0);    }
  }

  /* ── Card header ── */
  .card-header {
    background: linear-gradient(135deg, var(--green-dark) 0%, var(--green-main) 100%);
    padding: 30px 36px 26px;
    text-align: center;
    position: relative;
    overflow: hidden;
  }
  .card-header::before {
    content: '';
    position: absolute;
    width: 180px; height: 180px;
    border-radius: 50%;
    background: rgba(255,255,255,0.05);
    top: -60px; right: -40px;
  }
  .card-header::after {
    content: '';
    position: absolute;
    width: 100px; height: 100px;
    border-radius: 50%;
    background: rgba(255,255,255,0.04);
    bottom: -30px; left: -20px;
  }

  .icon-wrap {
    width: 60px; height: 60px;
    background: rgba(255,255,255,0.18);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 12px;
    border: 1.5px solid rgba(255,255,255,0.25);
    position: relative; z-index: 1;
  }
  .icon-wrap i { font-size: 24px; color: #fff; }

  .card-header h2 {
    color: #fff;
    font-size: 19px;
    font-weight: 700;
    position: relative; z-index: 1;
  }
  .card-header p {
    color: rgba(255,255,255,0.72);
    font-size: 12.5px;
    margin-top: 4px;
    position: relative; z-index: 1;
  }

  /* ── Step dots ── */
  .steps {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    margin-bottom: 22px;
  }
  .step-dot {
    height: 7px;
    border-radius: 4px;
    background: var(--gray-border);
    transition: background 0.3s, width 0.3s;
    width: 7px;
  }
  .step-dot.active { background: var(--green-main); width: 22px; }
  .step-dot.done   { background: var(--green-main); }

  /* ── Card body ── */
  .card-body { padding: 28px 36px 10px; }

  /* ── Alert ── */
  .alert {
    padding: 11px 14px;
    border-radius: 9px;
    font-size: 13px;
    margin-bottom: 20px;
    display: flex;
    align-items: flex-start;
    gap: 9px;
    line-height: 1.5;
  }
  .alert i { font-size: 14px; margin-top: 1px; flex-shrink: 0; }
  .alert.success { background: var(--success-bg); color: #166534; border: 1px solid #bbf7d0; }
  .alert.error   { background: var(--error-bg);   color: var(--error); border: 1px solid #fecaca; }

  /* ── Form group ── */
  .form-group { margin-bottom: 18px; }
  .form-group label {
    display: block;
    font-size: 11.5px;
    font-weight: 600;
    color: var(--text-mid);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 6px;
  }

  .input-wrap { position: relative; }
  .input-wrap i.input-icon {
    position: absolute;
    left: 13px; top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
    font-size: 14px;
    pointer-events: none;
    transition: color 0.2s;
  }
  .input-wrap input {
    width: 100%;
    padding: 11px 13px 11px 38px;
    border: 2px solid var(--gray-border);
    border-radius: 9px;
    font-size: 13.5px;
    font-family: 'Poppins', sans-serif;
    color: var(--text-dark);
    background: var(--gray-bg);
    transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
    outline: none;
  }
  .input-wrap input:focus {
    border-color: var(--green-main);
    background: #fff;
    box-shadow: 0 0 0 3px var(--green-glow);
  }
  .input-wrap:focus-within i.input-icon { color: var(--green-main); }

  /* ── Code digit boxes ── */
  .code-input-wrap {
    display: flex;
    gap: 8px;
    justify-content: center;
    margin: 4px 0;
  }
  .code-input-wrap input {
    width: 50px; height: 58px;
    text-align: center;
    font-size: 22px;
    font-weight: 700;
    border: 2px solid var(--gray-border);
    border-radius: 10px;
    background: var(--gray-bg);
    color: var(--text-dark);
    font-family: 'Poppins', sans-serif;
    outline: none;
    transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
    -moz-appearance: textfield;
  }
  .code-input-wrap input::-webkit-outer-spin-button,
  .code-input-wrap input::-webkit-inner-spin-button { -webkit-appearance: none; }
  .code-input-wrap input:focus {
    border-color: var(--green-main);
    background: #fff;
    box-shadow: 0 0 0 3px var(--green-glow);
  }
  .code-input-wrap input.filled {
    border-color: var(--green-main);
    background: var(--green-light);
    color: var(--green-dark);
  }

  /* ── Button ── */
  .btn-submit {
    width: 100%;
    padding: 12px;
    background: linear-gradient(135deg, var(--green-main) 0%, var(--green-dark) 100%);
    color: #fff;
    border: none;
    border-radius: 9px;
    font-size: 14px;
    font-weight: 600;
    font-family: 'Poppins', sans-serif;
    cursor: pointer;
    transition: opacity 0.2s, transform 0.15s, box-shadow 0.2s;
    box-shadow: 0 4px 14px rgba(26,94,26,0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    margin-top: 4px;
  }
  .btn-submit:hover:not(:disabled) {
    opacity: 0.9;
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(26,94,26,0.35);
  }
  .btn-submit:disabled { opacity: 0.5; cursor: not-allowed; }

  /* ── Email hint ── */
  .email-hint {
    font-size: 12.5px;
    color: var(--text-muted);
    text-align: center;
    margin-bottom: 18px;
  }
  .email-hint strong { color: var(--green-dark); }

  /* ── Resend ── */
  .resend-wrap {
    text-align: center;
    margin: 14px 0 4px;
    font-size: 12.5px;
    color: var(--text-muted);
  }
  .resend-btn {
    background: none;
    border: none;
    color: var(--green-main);
    font-weight: 600;
    font-family: 'Poppins', sans-serif;
    font-size: 12.5px;
    cursor: pointer;
    padding: 0;
    text-decoration: underline;
  }
  .resend-btn:hover { color: var(--green-dark); }
  #timer { font-weight: 600; color: var(--green-dark); }

  /* ── Footer ── */
  .card-footer {
    padding: 16px 36px 26px;
    text-align: center;
  }
  .card-footer a {
    font-size: 13px;
    color: var(--text-muted);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-weight: 500;
    transition: color 0.2s;
  }
  .card-footer a:hover { color: var(--green-main); }
</style>
</head>
<body>

<div class="card">

  <!-- Header -->
  <div class="card-header">
    <div class="icon-wrap">
      <i class="fas <?= $step === 'code' ? 'fa-shield-halved' : 'fa-lock-open' ?>"></i>
    </div>
    <h2><?= $step === 'code' ? 'Enter Reset Code' : 'Forgot Password?' ?></h2>
    <p><?= $step === 'code' ? 'Check your email for the 6-digit code' : 'Enter your email to receive a reset code' ?></p>
  </div>

  <!-- Body -->
  <div class="card-body">

    <!-- Step dots -->
    <div class="steps">
      <div class="step-dot <?= $step === 'email' ? 'active' : 'done' ?>"></div>
      <div class="step-dot <?= $step === 'code'  ? 'active' : '' ?>"></div>
      <div class="step-dot"></div>
    </div>

    <!-- Alert -->
    <?php if ($message): ?>
      <div class="alert <?= $messageType ?>">
        <i class="fas <?= $messageType === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
        <span><?= $message ?></span>
      </div>
    <?php endif; ?>

    <!-- STEP 1: Email -->
    <?php if ($step === 'email'): ?>
    <form method="POST" autocomplete="off">
      <div class="form-group">
        <label>Email Address</label>
        <div class="input-wrap">
          <i class="fas fa-envelope input-icon"></i>
          <input type="email" name="email"
                 placeholder="Enter your registered email"
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                 required autofocus>
        </div>
      </div>
      <button type="submit" name="send_code" class="btn-submit">
         Send Reset Code
      </button>
    </form>

    <!-- STEP 2: Code -->
    <?php else: ?>
    <p class="email-hint">
      Code sent to <strong><?= htmlspecialchars($resetEmail) ?></strong>
    </p>

    <form method="POST" autocomplete="off" id="codeForm">
      <!-- Hidden field ensures email is passed even if session drops -->
      <input type="hidden" name="email" value="<?= htmlspecialchars($resetEmail) ?>">

      <div class="form-group">
        <label style="text-align:center;display:block;">6-Digit Code</label>
        <div class="code-input-wrap">
          <input type="number" class="digit" min="0" max="9" inputmode="numeric">
          <input type="number" class="digit" min="0" max="9" inputmode="numeric">
          <input type="number" class="digit" min="0" max="9" inputmode="numeric">
          <input type="number" class="digit" min="0" max="9" inputmode="numeric">
          <input type="number" class="digit" min="0" max="9" inputmode="numeric">
          <input type="number" class="digit" min="0" max="9" inputmode="numeric">
        </div>
        <input type="hidden" name="code" id="codeHidden">
      </div>

      <button type="submit" name="verify_code" class="btn-submit" id="verifyBtn" disabled>
        <i class="fas fa-check-circle"></i> Verify Code
      </button>
    </form>

    <div class="resend-wrap">
      <span id="timerMsg">Resend code in <span id="timer">2:00</span></span>
      <form method="POST" id="resendForm" style="display:none;">
        <input type="hidden" name="email" value="<?= htmlspecialchars($resetEmail) ?>">
        <button type="submit" name="resend_code" class="resend-btn">Resend Code</button>
      </form>
    </div>
    <?php endif; ?>

  </div><!-- /card-body -->

  <!-- Footer -->
  <div class="card-footer">
    <a href="/agri_system/login.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
  </div>

</div>

<script>
const digits    = document.querySelectorAll('.digit');
const hidden    = document.getElementById('codeHidden');
const verifyBtn = document.getElementById('verifyBtn');

function syncCode() {
    if (!hidden) return;
    const code = [...digits].map(d => d.value).join('');
    hidden.value = code;
    if (verifyBtn) verifyBtn.disabled = code.length < 6;
    digits.forEach(d => d.classList.toggle('filled', d.value !== ''));
}

digits.forEach((input, idx) => {
    input.addEventListener('input', () => {
        if (input.value.length > 1) input.value = input.value.slice(-1);
        syncCode();
        if (input.value && idx < digits.length - 1) digits[idx + 1].focus();
    });
    input.addEventListener('keydown', e => {
        if (e.key === 'Backspace' && !input.value && idx > 0) {
            digits[idx - 1].value = '';
            digits[idx - 1].focus();
            syncCode();
        }
        if (!/[0-9]/.test(e.key) && !['Backspace','Tab','ArrowLeft','ArrowRight'].includes(e.key)) {
            e.preventDefault();
        }
    });
    input.addEventListener('paste', e => {
        e.preventDefault();
        const text = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
        [...text].slice(0, 6).forEach((ch, i) => { if (digits[i]) digits[i].value = ch; });
        syncCode();
        digits[Math.min(text.length, digits.length - 1)].focus();
    });
});

<?php if ($step === 'code'): ?>
(function () {
    let secs = 120;
    const timerEl  = document.getElementById('timer');
    const timerMsg = document.getElementById('timerMsg');
    const resendF  = document.getElementById('resendForm');

    const tick = setInterval(() => {
        secs--;
        if (timerEl) {
            const m = Math.floor(secs / 60);
            const s = secs % 60;
            timerEl.textContent = m + ':' + String(s).padStart(2, '0');
        }
        if (secs <= 0) {
            clearInterval(tick);
            if (timerMsg) timerMsg.style.display = 'none';
            if (resendF)  resendF.style.display  = 'inline';
        }
    }, 1000);

    if (digits.length) digits[0].focus();
})();
<?php endif; ?>
</script>
</body>
</html>