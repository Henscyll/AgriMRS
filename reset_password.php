<?php
session_start();
date_default_timezone_set('Asia/Manila');

include('includes/db_connection.php');

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$message = '';
$messageType = '';
$isSuccess = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $token = $_POST['token'] ?? '';
    $currentTime = date('Y-m-d H:i:s');

    if ($newPassword !== $confirmPassword) {
        $message = "Passwords do not match. Please try again.";
        $messageType = "error";
    } else {
        // Fetch token record
        $stmt = $conn->prepare("SELECT email, expires_at FROM password_resets WHERE token = ? ORDER BY id DESC LIMIT 1");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            if ($row['expires_at'] >= $currentTime) {
                $email = $row['email'];
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

                // Update password across user tables safely
                $tables = ['farmers', 'operators', 'associations', 'presidents', 'users'];
                $updated = false;

                foreach ($tables as $table) {
                    try {
                        $upd = $conn->prepare("UPDATE `$table` SET password = ? WHERE email = ?");

                        if (!$upd) {
                            continue;
                        }

                        $upd->bind_param("ss", $hashedPassword, $email);
                        $upd->execute();

                        if ($upd->affected_rows > 0) {
                            $updated = true;
                            $upd->close();
                            break;
                        }
                        $upd->close();
                    } catch (mysqli_sql_exception $e) {
                        // Table or column doesn't exist — safely skip to the next table
                        continue;
                    }
                }

                if ($updated) {
                    // Clean up used token
                    $del = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
                    if ($del) {
                        $del->bind_param("s", $email);
                        $del->execute();
                        $del->close();
                    }

                    // Redirect directly to login with success flag
                    header("Location: login.php?status=reset_success");
                    exit;
                } else {
                    $message = "User account not found in system records.";
                    $messageType = "error";
                }
            } else {
                $message = "Reset token has expired. Please request a new one.";
                $messageType = "error";
            }
        } else {
            $message = "Invalid reset token.";
            $messageType = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password – AMRMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --green-dark: #1a5e1a;
            --green-main: #2d8a2d;
            --green-glow: rgba(45, 138, 45, 0.15);
            --green-light: #e8f5e8;
            --gray-bg: #f4f7f4;
            --gray-border: #d1e8d1;
            --text-dark: #1a2e1a;
            --text-mid: #4a6a4a;
            --text-muted: #7a9a7a;
            --error: #dc2626;
            --error-bg: #fef2f2;
            --success-bg: #f0fdf0;
        }

        html,
        body {
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
            box-shadow: 0 8px 40px rgba(0, 0, 0, 0.12), 0 0 0 1px rgba(0, 0, 0, 0.06);
            overflow: hidden;
            animation: slideUp 0.4s cubic-bezier(0.22, 1, 0.36, 1) both;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
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
            width: 180px;
            height: 180px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.05);
            top: -60px;
            right: -40px;
        }

        .card-header::after {
            content: '';
            position: absolute;
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.04);
            bottom: -30px;
            left: -20px;
        }

        .icon-wrap {
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.18);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
            border: 1.5px solid rgba(255, 255, 255, 0.25);
            position: relative;
            z-index: 1;
        }

        .icon-wrap i {
            font-size: 24px;
            color: #fff;
        }

        .card-header h2 {
            color: #fff;
            font-size: 19px;
            font-weight: 700;
            position: relative;
            z-index: 1;
        }

        .card-header p {
            color: rgba(255, 255, 255, 0.72);
            font-size: 12.5px;
            margin-top: 4px;
            position: relative;
            z-index: 1;
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

        .step-dot.done {
            background: var(--green-main);
        }

        .step-dot.active {
            background: var(--green-main);
            width: 22px;
        }

        /* ── Card body ── */
        .card-body {
            padding: 28px 36px 28px;
        }

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

        .alert i {
            font-size: 14px;
            margin-top: 1px;
            flex-shrink: 0;
        }

        .alert.success {
            background: var(--success-bg);
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .alert.error {
            background: var(--error-bg);
            color: var(--error);
            border: 1px solid #fecaca;
        }

        /* ── Form group ── */
        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-size: 11.5px;
            font-weight: 600;
            color: var(--text-mid);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }

        .input-wrap {
            position: relative;
        }

        .input-wrap i.input-icon {
            position: absolute;
            left: 13px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 14px;
            pointer-events: none;
            transition: color 0.2s;
        }

        .input-wrap input {
            width: 100%;
            padding: 11px 38px 11px 38px;
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

        .input-wrap:focus-within i.input-icon {
            color: var(--green-main);
        }

        /* ── Eye Icon Toggle Styling ── */
        .toggle-password {
            position: absolute;
            right: 13px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--text-muted);
            font-size: 14px;
            transition: color 0.2s;
            user-select: none;
        }

        .toggle-password:hover {
            color: var(--green-main);
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
            box-shadow: 0 4px 14px rgba(26, 94, 26, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            margin-top: 8px;
            text-decoration: none;
        }

        .btn-submit:hover {
            opacity: 0.9;
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(26, 94, 26, 0.35);
        }

        /* Disabled button styling */
        .btn-submit:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            box-shadow: none;
            transform: none;
        }
    </style>
</head>

<body>

    <div class="card">

        <!-- Header -->
        <div class="card-header">
            <div class="icon-wrap">
                <i class="fas fa-key"></i>
            </div>
            <h2>Set New Password</h2>
            <p>Create a strong password for your account</p>
        </div>

        <!-- Body -->
        <div class="card-body">

            <!-- Step dots (Step 3 Active) -->
            <div class="steps">
                <div class="step-dot done"></div>
                <div class="step-dot done"></div>
                <div class="step-dot active"></div>
            </div>

            <!-- Alert -->
            <?php if ($message): ?>
                <div class="alert <?= $messageType ?>">
                    <i class="fas <?= $messageType === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
                    <span><?= $message ?></span>
                </div>
            <?php endif; ?>

            <?php if ($isSuccess): ?>
                <a href="login.php" class="btn-submit">
                    <i class="fas fa-right-to-bracket"></i> Login Now
                </a>
            <?php else: ?>
                <form method="POST" autocomplete="off">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                    <div class="form-group">
                        <label>New Password</label>
                        <div class="input-wrap">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password" name="new_password" id="new_password" placeholder="Enter new password"
                                required minlength="6">
                            <span class="toggle-password" onclick="togglePassword('new_password', 'eyeIcon1')">
                                <i class="fa-regular fa-eye" id="eyeIcon1"></i>
                            </span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <div class="input-wrap">
                            <i class="fas fa-check-double input-icon"></i>
                            <input type="password" name="confirm_password" id="confirm_password"
                                placeholder="Re-enter new password" required minlength="6">
                            <span class="toggle-password" onclick="togglePassword('confirm_password', 'eyeIcon2')">
                                <i class="fa-regular fa-eye" id="eyeIcon2"></i>
                            </span>
                        </div>
                    </div>

                    <button type="submit" class="btn-submit" id="submitBtn" disabled>
                        <i class="fas fa-save"></i> Save New Password
                    </button>
                </form>
            <?php endif; ?>

        </div><!-- /card-body -->

    </div>

    <script>
        function togglePassword(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);

            if (input.type === "password") {
                input.type = "text";
                icon.classList.remove("fa-eye");
                icon.classList.add("fa-eye-slash");
            } else {
                input.type = "password";
                icon.classList.remove("fa-eye-slash");
                icon.classList.add("fa-eye");
            }
        }

        document.addEventListener("DOMContentLoaded", function () {
            const newPassword = document.getElementById("new_password");
            const confirmPassword = document.getElementById("confirm_password");
            const submitBtn = document.getElementById("submitBtn");

            if (newPassword && confirmPassword && submitBtn) {
                function checkInputs() {
                    const val1 = newPassword.value.trim();
                    const val2 = confirmPassword.value.trim();

                    if (val1.length >= 6 && val2.length >= 6) {
                        submitBtn.disabled = false;
                    } else {
                        submitBtn.disabled = true;
                    }
                }

                newPassword.addEventListener("input", checkInputs);
                confirmPassword.addEventListener("input", checkInputs);
            }
        });
    </script>

</body>

</html>