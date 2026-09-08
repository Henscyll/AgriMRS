<?php
include('da_header.php');

$conn = new mysqli("localhost", "root", "", "agri_machinery");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$da_user_id = $_SESSION['user_id'] ?? null;

// Get DA Official info
$da_query = $conn->query("SELECT id, name, email FROM users WHERE id = '$da_user_id' LIMIT 1");
$da_user = $da_query ? $da_query->fetch_assoc() : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Account Settings | AMRMS</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
  :root {
    --primary-green: #2d7a2d;
    --dark-green: #1a5c1a;
    --light-green: #d1fae5;
    --hover-green: #f0fdf4;
    --text-dark: #1f2937;
    --text-light: #6b7280;
    --border-color: #e5e7eb;
    --white: #fff;
    --gray-bg: #ffffff;
    --gray-border: #ddd;
    --gray-text: #666;
    --shadow-sm: 0 1px 2px 0 rgba(0,0,0,0.05);
    --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1);
    --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1);
  }

  body { margin:0; padding:0; font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; }

  .main-content { height: auto !important; padding: 50px 20px; }

  .container { max-width:1200px; margin:0 auto; }

  .page-header { text-align:center; margin-bottom:20px; }
  .page-header h2 { font-size: 28px; color: #2d7a2d; font-weight: bold; text-align: center; text-shadow: 1px 1px 3px rgba(14,4,4,0.7); margin: 10px 0 0 0; }

  .my-account-container { max-width:600px; margin:0 auto; }
  .account-card { background:white; border-radius:12px; box-shadow:var(--shadow-lg); overflow:hidden; border: 1px solid #ddd; }
  .account-header { background:var(--primary-green); color:white; padding:10px; text-align:center; }
  .account-header i { font-size:25px; opacity:.9; }
  .account-body { padding:28px 32px; }

  .info-section { background:var(--hover-green); padding:20px; border-radius:8px; margin-bottom:24px; border:1px solid #d1fae5; }
  .info-row { display:flex; align-items:center; gap:16px; margin-bottom:16px; }
  .info-row:last-child { margin-bottom:0; }
  .info-icon { width:42px; height:42px; background:white; border-radius:8px; display:flex; align-items:center; justify-content:center; color:var(--primary-green); font-size:18px; border:1px solid #e5e7eb; flex-shrink:0; }
  .info-content h4 { font-size:12px; color:var(--text-light); font-weight:600; text-transform:uppercase; margin:0 0 2px 0; letter-spacing:0.5px; }
  .info-content p { font-size:15px; color:var(--text-dark); font-weight:600; margin:0; }

  .password-form { margin-top:20px; }
  .form-section-title { font-size:16px; font-weight:600; color:var(--text-dark); margin-bottom:18px; display:flex; align-items:center; gap:8px; border-bottom:1px solid #e5e7eb; padding-bottom:8px; }
  .password-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
  .input-group { margin-bottom:16px; }
  .input-group label { display:block; font-size:13px; font-weight:500; color:var(--text-dark); margin-bottom:6px; }
  .input-wrapper { position:relative; }
  .input-wrapper i.input-icon { position:absolute; left:14px; top:50%; transform:translateY(-50%); color:var(--text-light); }
  .input-wrapper .toggle-password { position:absolute; right:14px; top:50%; transform:translateY(-50%); color:var(--text-light); cursor:pointer; transition:color .3s; font-size:15px; }
  .input-wrapper .toggle-password:hover { color:var(--primary-green); }
  .input-group input { width:100%; padding:10px 38px; border:1px solid var(--border-color); border-radius:8px; font-size:14px; transition:all .3s; font-family:inherit; box-sizing:border-box; }
  .input-group input:focus { outline:none; border-color:var(--primary-green); }

  .update-btn { width:100%; background:var(--primary-green); color:white; border:none; padding:12px; border-radius:8px; font-size:15px; font-weight:600; cursor:pointer; transition:all .3s; display:flex; align-items:center; justify-content:center; gap:8px; margin-top:8px; }
  .update-btn:hover:not(:disabled) { background:var(--dark-green); }
  .update-btn:disabled { background-color: #a5d6a5; cursor: not-allowed; opacity: 0.6; }

  .inline-error { color: #dc2626; font-size: 12px; margin-top: 4px; display: none; font-weight: 500; }

  .modal { display:none; position:fixed; z-index:9999; inset:0; background:rgba(0,0,0,.4); backdrop-filter:blur(4px); justify-content:center; align-items:center; }

  .about-popup-content {
    background: #fff;
    border-radius: 16px;
    padding: 30px 25px;
    width: 90%;
    max-width: 400px;
    text-align: center;
    box-shadow: 0 8px 25px rgba(0,0,0,0.2);
    position: relative;
    animation: fadeIn 0.25s ease-in-out;
  }
  .about-popup-content p {
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 20px;
    color: #333;
  }
  .about-popup-content button {
    background-color: #2d7a2d;
    color: white;
    border: none;
    padding: 8px 30px;
    cursor: pointer;
    border-radius: 6px;
    font-size: 16px;
    font-weight: 600;
    transition: background-color 0.3s;
  }
  .about-popup-content button:hover {
    background-color: #256725;
  }

  @keyframes fadeIn {
    from {opacity: 0; transform: translateY(-15px);}
    to {opacity: 1; transform: translateY(0);}
  }

  @media(max-width:768px) {
    .main-content { padding:15px 10px 30px; }
    .password-grid { grid-template-columns:1fr; }
  }
</style>
</head>

<body>
<div class="main-content">
  <div class="container">
    <div class="page-header"><h2>Account Settings</h2></div>

    <div class="my-account-container">
      <div class="account-card">
        <div class="account-header">
          <i class="fas fa-user-circle"></i>
        </div>
        <div class="account-body">
          <div class="info-section">
            <div class="info-row">
              <div class="info-icon"><i class="fas fa-user"></i></div>
              <div class="info-content">
                <h4>Full Name</h4>
                <p><?= htmlspecialchars($da_user['name'] ?? 'Not set') ?></p>
              </div>
            </div>
            <div class="info-row">
              <div class="info-icon"><i class="fas fa-envelope"></i></div>
              <div class="info-content">
                <h4>Email Address</h4>
                <p><?= htmlspecialchars($da_user['email'] ?? 'Not set') ?></p>
              </div>
            </div>
          </div>

          <!-- Password Form -->
          <form method="POST" action="update_password.php" class="password-form" id="daPasswordForm">
            <div class="form-section-title"><i class="fas fa-lock"></i> Change Password</div>

            <div class="password-grid">
              <div class="input-group">
                <label>New Password</label>
                <div class="input-wrapper">
                  <i class="fas fa-key input-icon"></i>
                  <input type="password" name="new_password" id="new_password"
                         required minlength="6" placeholder="Enter new password" oninput="validatePasswordForm()">
                  <i class="fas fa-eye toggle-password" onclick="togglePassword('new_password', this)"></i>
                </div>
              </div>

              <div class="input-group">
                <label>Confirm New Password</label>
                <div class="input-wrapper">
                  <i class="fas fa-check-circle input-icon"></i>
                  <input type="password" name="confirm_password" id="confirm_password"
                         required minlength="6" placeholder="Re-enter new password" oninput="validatePasswordForm()">
                  <i class="fas fa-eye toggle-password" onclick="togglePassword('confirm_password', this)"></i>
                </div>
                <div class="inline-error" id="passwordMatchError">Passwords do not match.</div>
              </div>
            </div>

            <button type="button" class="update-btn" id="updatePwBtn" disabled onclick="submitDaPassword()">
               Update Password
            </button>
          </form>
        </div>
      </div>
    </div>

  </div>
</div>

<!-- RESPONSE POPUP MODAL -->
<div id="responseModal" class="modal">
  <div class="about-popup-content">
    <p id="responseModalText" style="font-weight:bold;"></p>
    <button type="button" id="responseOkBtn" onclick="closeResponseModal()">OK</button>
  </div>
</div>

<script>
let refreshOnResponseClose = false;

function openModal(id)  { document.getElementById(id).style.display = 'flex'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }

function showResponseModal(msg, shouldRefresh = false) {
  refreshOnResponseClose = shouldRefresh;
  document.getElementById('responseModalText').textContent = msg;
  openModal('responseModal');
}

function closeResponseModal() {
  closeModal('responseModal');
  if (refreshOnResponseClose) {
    window.location.reload();
  }
}

document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    closeModal('responseModal');
  }
});

function togglePassword(id, icon) {
  const inp = document.getElementById(id);
  if (!icon) icon = inp.nextElementSibling;
  if (inp.type === 'password') {
    inp.type = 'text';
    icon.classList.replace('fa-eye','fa-eye-slash');
  } else {
    inp.type = 'password';
    icon.classList.replace('fa-eye-slash','fa-eye');
  }
}

function validatePasswordForm() {
  const np = document.getElementById('new_password').value.trim();
  const cp = document.getElementById('confirm_password').value.trim();
  const btn = document.getElementById('updatePwBtn');
  const errorElement = document.getElementById('passwordMatchError');
  
  if (np !== '' && cp !== '' && np !== cp) {
    errorElement.style.display = 'block';
  } else {
    errorElement.style.display = 'none';
  }

  btn.disabled = !(np !== '' && cp !== '' && np === cp);
}

function submitDaPassword() {
  const np = document.getElementById('new_password').value.trim();
  const cp = document.getElementById('confirm_password').value.trim();
  
  if (np !== cp) {
    document.getElementById('passwordMatchError').style.display = 'block';
    return;
  }
  
  const form = document.getElementById('daPasswordForm');
  
  fetch(form.action, { method: 'POST', body: new FormData(form) })
    .then(res => {
      if (!res.ok) {
        throw new Error('Failed to update password.');
      }
      return res.text();
    })
    .then(() => {
      showResponseModal('Password changed successfully!', false);
      form.reset();
      validatePasswordForm();
    })
    .catch(err => {
      showResponseModal('Failed to update password. Please check your connection or session.', false);
    });
}
</script>

<?php $conn->close(); ?>
</body>
</html>