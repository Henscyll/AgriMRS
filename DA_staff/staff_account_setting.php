<?php
include('dastaff_header.php');

$conn = new mysqli("localhost", "root", "", "agri_machinery");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$staff_id = $_SESSION['user_id'] ?? null;

// Get DA Staff info — join users + da_staff
$stmt = $conn->prepare("
    SELECT u.id, u.name, u.email,
           d.first_name, d.middle_name, d.last_name,
           d.date_of_birth, d.age,
           d.province, d.municipality, d.barangay
    FROM users u
    LEFT JOIN da_staff d ON d.user_id = u.id
    WHERE u.id = ?
    LIMIT 1
");
$stmt->bind_param("i", $staff_id);
$stmt->execute();
$staff = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Format DOB
$dob_display = 'Not set';
if (!empty($staff['date_of_birth']) && $staff['date_of_birth'] !== '0000-00-00') {
    $dob_display = date('F j, Y', strtotime($staff['date_of_birth']));
}

// Full address
$addr_parts = array_filter([
    $staff['barangay']     ?? '',
    $staff['municipality'] ?? '',
    $staff['province']     ?? '',
]);
$address_display = $addr_parts ? implode(', ', $addr_parts) : 'Not set';
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

  /* ── 2-Column Info Grid matching screenshot layout ── */
  .info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    background: var(--hover-green);
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 24px;
    border: 1px solid #d1fae5;
  }
  .info-item {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
  }
  .info-item.full {
    grid-column: 1 / -1;
  }
  .info-icon {
    width: 42px;
    height: 42px;
    background: white;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-green);
    font-size: 16px;
    flex-shrink: 0;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
  }
  .info-content .label {
    font-size: 12px;
    color: var(--text-light);
    font-weight: 500;
    margin-bottom: 2px;
  }
  .info-content .value {
    font-size: 14px;
    color: var(--text-dark);
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  /* Password Form Styling */
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
    .info-grid { grid-template-columns:1fr; }
    .info-item.full { grid-column: auto; }
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
          
          <!-- ── Grid Details Section ── -->
          <div class="info-grid">
            <!-- Full Name -->
            <div class="info-item">
              <div class="info-icon"><i class="fas fa-user"></i></div>
              <div class="info-content">
                <div class="label">Full Name</div>
                <div class="value" title="<?= htmlspecialchars($staff['name'] ?? '') ?>">
                  <?= htmlspecialchars($staff['name'] ?? 'Not set') ?>
                </div>
              </div>
            </div>

            <!-- Email -->
            <div class="info-item">
              <div class="info-icon"><i class="fas fa-envelope"></i></div>
              <div class="info-content">
                <div class="label">Email</div>
                <div class="value" title="<?= htmlspecialchars($staff['email'] ?? '') ?>">
                  <?= htmlspecialchars($staff['email'] ?? 'Not set') ?>
                </div>
              </div>
            </div>

            <!-- Date of Birth -->
            <div class="info-item">
              <div class="info-icon"><i class="fas fa-calendar-alt"></i></div>
              <div class="info-content">
                <div class="label">Date of Birth</div>
                <div class="value"><?= htmlspecialchars($dob_display) ?></div>
              </div>
            </div>

            <!-- Age -->
            <div class="info-item">
              <div class="info-icon"><i class="fas fa-hashtag"></i></div>
              <div class="info-content">
                <div class="label">Age</div>
                <div class="value">
                  <?= !empty($staff['age']) ? htmlspecialchars($staff['age']) . ' yrs' : 'Not set' ?>
                </div>
              </div>
            </div>

            <!-- Address (Full Width Row) -->
            <div class="info-item full">
              <div class="info-icon"><i class="fas fa-map-marker-alt"></i></div>
              <div class="info-content" style="min-width:0; flex:1;">
                <div class="label">Address</div>
                <div class="value" style="white-space:normal; word-break:break-word;">
                  <?= htmlspecialchars($address_display) ?>
                </div>
              </div>
            </div>
          </div>

          <!-- ── DA Official Style Password Form ── -->
          <form method="POST" action="update_staff_password.php" class="password-form" id="staffPasswordForm">
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

            <button type="button" class="update-btn" id="updatePwBtn" disabled onclick="submitStaffPassword()">
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

  btn.disabled = !(np !== '' && cp !== '' && np === cp && np.length >= 6);
}

function submitStaffPassword() {
  const np = document.getElementById('new_password').value.trim();
  const cp = document.getElementById('confirm_password').value.trim();
  
  if (np !== cp) {
    document.getElementById('passwordMatchError').style.display = 'block';
    return;
  }
  
  const form = document.getElementById('staffPasswordForm');
  
  fetch(form.action, { method: 'POST', body: new FormData(form) })
    .then(async (res) => {
      const text = await res.text();
      if (!res.ok) {
        throw new Error(`Server returned HTTP ${res.status}: ${text}`);
      }
      return text;
    })
    .then(() => {
      showResponseModal('Password changed successfully!', false);
      form.reset();
      validatePasswordForm();
    })
    .catch(err => {
      console.error('Password Update Error:', err); // Check console for exact output
      showResponseModal('Failed to update password. Please check your connection or session.', false);
    });
}
</script>

<?php $conn->close(); ?>
</body>
</html>