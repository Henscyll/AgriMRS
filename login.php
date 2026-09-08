<?php
session_start();

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = $_POST['email'];   
    $password_input = $_POST['password'];

    $conn = new mysqli("localhost", "root", "", "agri_machinery");
    if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

    // ✅ Check users table (handles ALL user types including farmers)
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user_result = $stmt->get_result();

    if ($user_result && $user_result->num_rows === 1) {
        $user = $user_result->fetch_assoc();

        $password_valid = false;

        // ✅ FLEXIBLE PASSWORD VERIFICATION
        if (password_verify($password_input, $user['password'])) {
            $password_valid = true;
        }
        elseif ($password_input === $user['password']) {
            $password_valid = true;
            
            // Re-hash for security
            $new_hash = password_hash($password_input, PASSWORD_DEFAULT);
            $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update->bind_param("si", $new_hash, $user['id']);
            $update->execute();
        }

        if ($password_valid) {
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role']  = $user['user_role'];
            $_SESSION['user_name']  = $user['name'];
            $_SESSION['user_id']    = $user['id'];

            switch ($user['user_role']) {
                case 'it admin':
                    header("Location: admin/dashboard_itadmin.php");
                    exit;

                case 'associations':
                    $assocStmt = $conn->prepare("SELECT id FROM associations WHERE email = ?");
                    $assocStmt->bind_param("s", $user['email']);
                    $assocStmt->execute();
                    $assocResult = $assocStmt->get_result();

                    if ($assocResult && $assocResult->num_rows > 0) {
                        $assoc = $assocResult->fetch_assoc();
                        $_SESSION['association_id'] = $assoc['id'];
                        header("Location: Association_President/dashboard_president.php");
                    } else {
                        die("❌ Error: No association record found for this user.");
                    }
                    exit;

                case 'operator':
                    $operatorStmt = $conn->prepare("
                        SELECT o.id, o.association_id, a.name as association_name 
                        FROM operators o
                        JOIN associations a ON o.association_id = a.id
                        WHERE o.user_id = ? AND o.status = 'Active'
                    ");
                    $operatorStmt->bind_param("i", $user['id']);
                    $operatorStmt->execute();
                    $operatorResult = $operatorStmt->get_result();

                    if ($operatorResult && $operatorResult->num_rows > 0) {
                        $operator = $operatorResult->fetch_assoc();
                        $_SESSION['operator_id'] = $operator['id'];
                        $_SESSION['association_id'] = $operator['association_id'];
                        $_SESSION['association_name'] = $operator['association_name'];
                        header("Location: operator/operator_dashboard.php");
                    } else {
                        die("❌ Error: No operator record found or account is inactive.");
                    }
                    $operatorStmt->close();
                    exit;

                case 'farmer':
                    header("Location: farmers/tractor.php");
                    exit;

                case 'department of agriculture':
                    header("Location: Department_of_Agriculture/da_header.php");
                    exit;

                case 'da staff':
                    header("Location: DA_Staff/dastaff_header.php");
                    exit;

                default:
                    header("Location: index.php");
                    exit;
            }
        } else {
            $error = "Incorrect password.";
        }
    } else {
        $error = "Email not found.";
    }

    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - AgriMach</title>
    <link href="images/da3.png" rel="icon">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles/login_styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* ── Centered Success Modal ── */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0;
            width: 100vw; height: 100vh;
            background: rgba(0, 0, 0, 0.45);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }
        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        .modal-card {
            background: #ffffff;
            width: 360px;
            max-width: calc(100vw - 32px);
            border-radius: 16px;
            padding: 28px 24px;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            transform: scale(0.85);
            transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .modal-overlay.active .modal-card {
            transform: scale(1);
        }
        .modal-icon {
            width: 64px;
            height: 64px;
            background: #e8f5e8;
            color: #2d8a2d;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            margin: 0 auto 16px;
        }
        .modal-card h3 {
            font-size: 19px;
            color: #1a2e1a;
            margin-bottom: 8px;
            font-weight: 700;
        }
        .modal-card p {
            font-size: 13.5px;
            color: #555;
            line-height: 1.5;
            margin-bottom: 22px;
        }
        .modal-btn {
            width: 100%;
            padding: 11px;
            background: linear-gradient(135deg, #2d8a2d 0%, #1a5e1a 100%);
            color: #ffffff;
            border: none;
            border-radius: 9px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        .modal-btn:hover {
            opacity: 0.9;
        }
    </style>
</head>
<body>

    <!-- Success Modal Pop-up -->
    <?php if (isset($_GET['status']) && $_GET['status'] === 'reset_success'): ?>
    <div class="modal-overlay active" id="successModal">
        <div class="modal-card">
            <div class="modal-icon">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <h3>Password Reset Successful</h3>
            <button class="modal-btn" onclick="closeModal()">OK</button>
        </div>
    </div>
    <?php endif; ?>

    <div class="cover-loginbox">
        <div class="header-box">
            <h1>Agricultural Machineries Reservation and Monitoring Systems</h1>
            <div class="login-box" role="form" aria-label="Login Form">
                <h2>Login</h2>
                <?php if (!empty($error)) echo "<p class='error' aria-live='polite'>$error</p>"; ?>
                <form method="post" autocomplete="on">
                    <div class="input-group">
                        <label for="email">Email</label>
                        <input type="email" name="email" id="email" required autocomplete="email" placeholder="Enter your email">
                    </div>
                    <div class="input-group password-group">
                        <label for="password">Password</label>
                        <div class="password-wrapper">
                            <input type="password" name="password" id="password" required autocomplete="current-password" placeholder="Enter your password">
                            <span class="toggle-password" onclick="togglePassword()">
                                <i class="fa-regular fa-eye" id="eyeIcon"></i>
                            </span>
                        </div>
                    </div>
                    <button type="submit">Login</button>
                </form>
                <div class="forgotpass">
                    <a href="forgotpass.php">Forgot password?</a>
                </div>
            </div>
        </div>
    </div>

    <script>
    function togglePassword() {
        const passwordInput = document.getElementById('password');
        const eyeIcon = document.getElementById('eyeIcon');
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            eyeIcon.classList.remove('fa-eye');
            eyeIcon.classList.add('fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            eyeIcon.classList.remove('fa-eye-slash');
            eyeIcon.classList.add('fa-eye');
        }
    }

    function closeModal() {
        const modal = document.getElementById('successModal');
        if (modal) {
            modal.classList.remove('active');
            // Clean URL query parameters without reloading
            window.history.replaceState({}, document.title, window.location.pathname);
        }
    }
    </script>
</body>
</html>