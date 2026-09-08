<?php
include('dashboard_itadmin.php');

$conn = new mysqli("localhost", "root", "", "agri_machinery");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$admin_id = $_SESSION['user_id'] ?? null;
$admin_query = $conn->query("SELECT id, name, email FROM users WHERE id = '$admin_id' LIMIT 1");
$admin = $admin_query->fetch_assoc();

$sql = "SELECT u.id, u.name, u.email, u.user_role, u.created_at,
               ds.first_name, ds.middle_name, ds.last_name,
               ds.date_of_birth, ds.age,
               ds.province, ds.municipality, ds.barangay
        FROM users u
        LEFT JOIN da_staff ds ON ds.user_id = u.id
        WHERE u.user_role IN ('it admin', 'department of agriculture','da staff')
        ORDER BY u.id ASC";
$result = $conn->query($sql);

$all_rows = [];
if ($result) {
    while ($r = $result->fetch_assoc()) $all_rows[] = $r;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Account Settings | AMRMS</title>
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

  .tab-buttons {
    display:flex; justify-content:center; gap:12px; margin-bottom:25px;
    background:white; padding:8px; border-radius:12px; box-shadow:var(--shadow-md);
    max-width:500px; margin-left:auto; margin-right:auto;
  }
  .tab-btn {
    flex:1; background:transparent; color:var(--text-light); border:none;
    padding:12px 24px; cursor:pointer; border-radius:8px; font-size:15px; font-weight:500;
    transition:all 0.3s ease; display:flex; align-items:center; justify-content:center; gap:8px;
  }
  .tab-btn:hover { background:var(--hover-green); color:var(--primary-green); }
  .tab-btn.active { background:var(--primary-green); color:white; box-shadow:var(--shadow-sm); }
  .tab-content { display:none; animation:fadeIn 0.3s ease; }
  .tab-content.active { display:block; }
  @keyframes fadeIn { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:translateY(0)} }

  .table-wrapper { background:white; border-radius:12px; box-shadow:var(--shadow-lg); overflow:hidden; border: 1px solid #ddd; }

  .table-container { overflow-x:auto; max-height:380px; overflow-y:auto; }
  table { width:100%; border-collapse:collapse; }

  thead { background:var(--primary-green); position:sticky; top:0; z-index:10; }
  th {
    padding:13px 18px; text-align:left; font-size:12px; font-weight:700;
    color:white; letter-spacing:.6px; white-space:nowrap;
  }

  tbody tr { border-bottom:1px solid #f3f4f6; transition:background .15s; cursor:pointer; }
  tbody tr:hover { background:var(--hover-green); }

  td { padding:14px 18px; font-size:14px; color:var(--text-dark); vertical-align:middle; }

  .name-cell { display:flex; align-items:center; gap:10px; }
  .name-avatar {
    width:36px; height:36px; border-radius:50%;
    background:var(--primary-green); color:white;
    display:flex; align-items:center; justify-content:center;
    font-weight:700; font-size:14px; flex-shrink:0;
  }
  .name-cell .nm { font-weight:600; font-size:14px; color:var(--text-dark); }
  .name-cell .em { font-size:12px; color:var(--text-light); }


  .action-bar { margin-top:20px; display:flex; justify-content:center; align-items:center; gap:12px; }

  .add-staff-btn {
    background:var(--primary-green); color:white; border:none;
    padding:12px 26px; border-radius:8px; font-size:14px; font-weight:600;
    cursor:pointer; display:inline-flex; align-items:center; gap:8px;
    box-shadow:var(--shadow-md); transition:all .25s;
  }
  .add-staff-btn:hover { background:var(--dark-green); transform:scale(1.02); }

  .my-account-container { max-width:600px; margin:0 auto; }
  .account-card { background:white; border-radius:12px; box-shadow:var(--shadow-lg); overflow:hidden; border: 1px solid #ddd; }
  .account-header { background:var(--primary-green); color:white; padding:10px; text-align:center; }
  .account-header i { font-size:25px;opacity:.9; }
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
  .input-group input, .input-group select { width:100%; padding:10px 38px; border:1px solid var(--border-color); border-radius:8px; font-size:14px; transition:all .3s; font-family:inherit; box-sizing:border-box; }
  .input-group input:focus, .input-group select:focus { outline:none; border-color:var(--primary-green); }

  .modal { display:none; position:fixed; z-index:9999; inset:0; background:rgba(0,0,0,.4); backdrop-filter:blur(4px); justify-content:center; align-items:center; }
  .modal-content { width:580px; max-width:95%; background:white; border-radius:12px; box-shadow:0 8px 25px rgba(0,0,0,0.2); max-height:92vh; overflow-y:auto; animation: fadeIn 0.25s ease-in-out; }

  .modal-header { background:var(--primary-green); color:white; padding:22px 24px; position:relative; border-radius:12px 12px 0 0; }
  .modal-header h2 { font-size:22px; font-weight:600; margin:0 0 4px; }
  .modal-header p  { font-size:13px; opacity:.9; margin:0; }
  .close-btn { position:absolute; right:18px; top:16px; cursor:pointer; font-size:24px; color:white; width:32px; height:32px; display:flex; align-items:center; justify-content:center; border-radius:50%; transition:all .3s; }
  .close-btn:hover { background:rgba(255,255,255,.2); }

  .modal-body { padding:22px 24px; }
  .section-label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--primary-green); border-bottom:1px solid #e5e7eb; padding-bottom:5px; margin:6px 0 14px; }

  .form-row-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; }
  .form-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; }

  .form-group { margin-bottom:14px; }
  .form-group label { display:block; font-size:13px; font-weight:500; color:var(--text-dark); margin-bottom:6px; }

  .form-group .input-wrapper input, .form-group .input-wrapper select {
    width:100%; padding:10px 12px 10px 34px;
    border:1px solid var(--border-color); border-radius:7px;
    font-size:13.5px; font-family:inherit; transition:border-color .2s;
    box-sizing:border-box; background:white;
  }
  .form-group .input-wrapper input:focus, .form-group .input-wrapper select:focus { outline:none; border-color:var(--primary-green); }
  .form-group .input-wrapper input[readonly] { background:#f5f5f5; cursor:default; color:#555; }
  .form-group .input-wrapper select:disabled { background:#f3f4f6; cursor:not-allowed; }
  .form-group .input-wrapper i.input-icon { position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#9ca3af; font-size:13px; pointer-events:none; }

  .form-actions { display: flex; gap: 10px; margin-top: 15px; }
  .register-btn { flex: 1; background:var(--primary-green); border:none; padding:12px; color:white; border-radius:8px; cursor:pointer; font-size:15px; font-weight:600; transition:all .3s; display:flex; align-items:center; justify-content:center; gap:8px; }
  .register-btn:hover:not(:disabled) { background:var(--dark-green); }
  .register-btn:disabled { background-color: #a5d6a5; cursor: not-allowed; opacity: 0.6; }

  .btn-close-modal { flex: 1; border: 1px solid var(--gray-border); background: var(--white); color: var(--gray-text); padding:12px; border-radius:8px; cursor:pointer; font-size:15px; font-weight:600;}
.btn-cancel { padding: 7px 20px; border-radius: var(--radius); border: 1px solid var(--gray-border); background: var(--white); font-size: 13px; cursor: pointer; color: var(--gray-text); }
  .update-btn { width:100%; background:var(--primary-green); color:white; border:none; padding:12px; border-radius:8px; font-size:15px; font-weight:600; cursor:pointer; transition:all .3s; display:flex; align-items:center; justify-content:center; gap:8px; margin-top:8px; }
  .update-btn:hover:not(:disabled) { background:var(--dark-green); }
  .update-btn:disabled { background-color: #a5d6a5; cursor: not-allowed; opacity: 0.6; }

  .inline-error { color: #dc2626; font-size: 12px; margin-top: 4px; display: none; font-weight: 500; }

  .view-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px 20px; }
  .view-item label { display:block; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.04em; color:#9ca3af; margin-bottom:3px; }
  .view-item span { font-size:14px; color:var(--text-dark); font-weight:500; }
  .view-item.full { grid-column:1/-1; }
  .view-section-label { grid-column:1/-1; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--primary-green); border-bottom:1px solid #e5e7eb; padding-bottom:4px; margin-top:4px; }

  .modal-footer { padding:0 24px 20px; display:flex; justify-content:flex-end; }
  .btn-close-footer { padding:9px 22px; border-radius:7px; border:1px solid #d1d5db; background:white; color:#6b7280; font-size:14px; cursor:pointer; font-weight:500; }
  .btn-close-footer:hover { background:#f9fafb; }

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
    .tab-buttons { flex-direction:column; max-width:100%; }
    .form-row-3,.form-row,.password-grid { grid-template-columns:1fr; }
    .view-grid { grid-template-columns:1fr; }
    .view-item.full,.view-section-label { grid-column:1; }
    .action-bar { flex-direction:column; }
  }
</style>
</head>

<body>
<div class="main-content">

  <div class="container">
    <div class="page-header"><h2>Account Settings</h2></div>

    <div class="tab-buttons">
      <button class="tab-btn active" data-tab="userAccounts"><i class="fas fa-users"></i> User Accounts</button>
      <button class="tab-btn" data-tab="myAccount"><i class="fas fa-user-circle"></i> My Account</button>
    </div>

    <!-- USER ACCOUNTS TAB -->
    <div id="userAccounts" class="tab-content active">
      <div class="table-wrapper">
        <div class="table-container">
          <table>
            <thead>
              <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Registered Date</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($all_rows)): ?>
                <?php foreach ($all_rows as $i => $row): ?>
                  <tr data-index="<?= $i ?>" ondblclick="viewByIndex(<?= $i ?>)">
                    <td><?= htmlspecialchars($row['name']) ?></div></td>
                    <td><?= htmlspecialchars($row['email']) ?></td>
                    <td>
                        <?php
                            $role = strtolower(trim($row['user_role']));

                            if ($role === 'it admin') {
                                $displayRole = 'IT Admin';
                            } elseif ($role === 'da staff') {
                                $displayRole = 'DA Staff';
                            } elseif ($role === 'department of agriculture') {
                                $displayRole = 'DA Official';
                            } else {
                                $displayRole = $row['user_role']; // fallback
                            }
                        ?>
                        <span style="font-weight:bold;">
                            <?= htmlspecialchars($displayRole) ?>
                        </span>
                    </td>
                    <td><?= date('M d, Y',strtotime($row['created_at'])) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="4" style="text-align:center;padding:40px;color:var(--text-light);">No users found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="action-bar">
        <button class="add-staff-btn" onclick="openModal('addStaffModal')">
          <i class="fas fa-plus"></i> Add DA Staff
        </button>
      </div>
    </div>

    <!-- MY ACCOUNT TAB -->
    <div id="myAccount" class="tab-content">
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
                  <p><?= htmlspecialchars($admin['name'] ?? 'Not set') ?></p>
                </div>
              </div>
              <div class="info-row">
                <div class="info-icon"><i class="fas fa-envelope"></i></div>
                <div class="info-content">
                  <h4>Email Address</h4>
                  <p><?= htmlspecialchars($admin['email'] ?? 'Not set') ?></p>
                </div>
              </div>
            </div>

            <!-- Password Form -->
            <form method="POST" action="update_password.php" class="password-form" id="adminPasswordForm">
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

              <button type="button" class="update-btn" id="updatePwBtn" disabled onclick="submitAdminPassword()">
                 Update Password
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<!-- ADD DA STAFF MODAL -->
<div class="modal" id="addStaffModal">
  <div class="modal-content">
    <div class="modal-header">
      <h2>Add DA Staff</h2>
      <p>Fill out the details below to create a new staff account</p>
    </div>
    <div class="modal-body">
      <form method="POST" action="add_staff.php" id="addStaffForm" onsubmit="return handleAddStaffSubmit(event)">

        <div class="section-label">Personal Information</div>

        <div class="form-row-3">
          <div class="form-group">
            <label>First Name <span style="color:red">*</span></label>
            <div class="input-wrapper">
              <i class="fas fa-user input-icon"></i>
              <input type="text" name="first_name" id="add_first_name" placeholder="First name" required oninput="sanitizeNameInput(this); validateAddStaffForm();">
            </div>
          </div>
          <div class="form-group">
            <label>Middle Name</label>
            <div class="input-wrapper">
              <i class="fas fa-user input-icon"></i>
              <input type="text" name="middle_name" id="add_middle_name" placeholder="Middle name" oninput="sanitizeNameInput(this); validateAddStaffForm();">
            </div>
          </div>
          <div class="form-group">
            <label>Last Name <span style="color:red">*</span></label>
            <div class="input-wrapper">
              <i class="fas fa-user input-icon"></i>
              <input type="text" name="last_name" id="add_last_name" placeholder="Last name" required oninput="sanitizeNameInput(this); validateAddStaffForm();">
            </div>
          </div>
        </div>
        <div class="inline-error" id="nameExistsError">User with this name already exists.</div>

        <div class="form-row" style="margin-top:10px;">
          <div class="form-group">
            <label>Date of Birth <span style="color:red">*</span></label>
            <div class="input-wrapper">
              <i class="fas fa-calendar-alt input-icon"></i>
              <input type="text" name="date_of_birth" id="dob_txt"
                     placeholder="mm/dd/yyyy" maxlength="10" required
                     oninput="handleDobInput(this); validateAddStaffForm();" autocomplete="off">
            </div>
          </div>
          <div class="form-group">
            <label>Age</label>
            <div class="input-wrapper">
              <i class="fas fa-hashtag input-icon"></i>
              <input type="number" name="age" id="age_field" placeholder="Auto-computed" readonly>
            </div>
          </div>
        </div>

        <div class="section-label">Location</div>

        <div class="form-group">
          <label>Province <span style="color:red">*</span></label>
          <div class="input-wrapper">
            <i class="fas fa-map-marker-alt input-icon"></i>
            <input type="text" name="province" id="add_province" value="Zamboanga Del Sur" required readonly>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Municipality / City <span style="color:red">*</span></label>
            <div class="input-wrapper">
              <i class="fas fa-city input-icon"></i>
              <select name="municipality" id="add_municipality" required onchange="updateBarangays(); validateAddStaffForm();">
                <option value="">Select Municipality</option>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label>Barangay <span style="color:red">*</span></label>
            <div class="input-wrapper">
              <i class="fas fa-map-pin input-icon"></i>
              <select name="barangay" id="add_barangay" required disabled onchange="validateAddStaffForm();">
                <option value="">Select Barangay</option>
              </select>
            </div>
          </div>
        </div>

        <div class="section-label">Account Information</div>

        <div class="form-group">
          <label>Email Address <span style="color:red">*</span></label>
          <div class="input-wrapper">
            <i class="fas fa-envelope input-icon"></i>
            <input type="email" name="email" id="add_email" placeholder="Email address" required oninput="validateAddStaffForm()">
          </div>
          <div class="inline-error" id="emailExistsError">Email has already been taken.</div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Password <span style="color:red">*</span></label>
            <div class="input-wrapper">
              <i class="fas fa-lock input-icon"></i>
              <input type="password" name="password" id="modal_password"
                     placeholder="Min. 8 characters" required minlength="8" oninput="validateAddStaffForm()">
              <i class="fas fa-eye toggle-password" onclick="togglePassword('modal_password', this)"></i>
            </div>
          </div>
          <div class="form-group">
            <label>Confirm Password <span style="color:red">*</span></label>
            <div class="input-wrapper">
              <i class="fas fa-check-circle input-icon"></i>
              <input type="password" name="confirm_password" id="modal_confirm_password"
                     placeholder="Re-enter password" required minlength="8" oninput="validateAddStaffForm()">
              <i class="fas fa-eye toggle-password" onclick="togglePassword('modal_confirm_password', this)"></i>
            </div>
          </div>
        </div>
        <div class="inline-error" id="addPasswordMatchError">Passwords do not match.</div>

        <div class="form-actions">
          <button class="register-btn" id="addStaffBtn" type="submit" disabled>
             Add
          </button>
          <button type="button" class="btn-close-modal" onclick="closeModal('addStaffModal')">
             Close
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- VIEW USER MODAL -->
<div class="modal" id="viewModal">
  <div class="modal-content" style="max-width:520px;">
    <div class="modal-header">
      <h2 id="vm-title">User Details</h2>
      <p id="vm-subtitle"></p>
    </div>
    <div class="modal-body">
      <div class="view-grid">
        <div class="view-section-label">Personal Information</div>
        <div class="view-item"><label>First Name</label><span id="vm-first">—</span></div>
        <div class="view-item"><label>Middle Name</label><span id="vm-middle">—</span></div>
        <div class="view-item"><label>Last Name</label><span id="vm-last">—</span></div>
        <div class="view-item"><label>Full Name</label><span id="vm-name">—</span></div>
        <div class="view-item"><label>Date of Birth</label><span id="vm-dob">—</span></div>
        <div class="view-item"><label>Age</label><span id="vm-age">—</span></div>

        <div class="view-section-label">Location</div>
        <div class="view-item"><label>Province</label><span id="vm-province">—</span></div>
        <div class="view-item"><label>Municipality</label><span id="vm-municipality">—</span></div>
        <div class="view-item full"><label>Barangay</label><span id="vm-barangay">—</span></div>

        <div class="view-section-label">Account Information</div>
        <div class="view-item full"><label>Email</label><span id="vm-email">—</span></div>
        <div class="view-item"><label>Role</label><span id="vm-role">—</span></div>
        <div class="view-item"><label>Registered</label><span id="vm-created">—</span></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn-close-footer" onclick="closeModal('viewModal')">Close</button>
    </div>
  </div>
</div>

<!-- ABOUT-US RESPONSE POPUP MODAL -->
<div id="responseModal" class="modal">
  <div class="about-popup-content">
    <p id="responseModalText" style="font-weight:bold;"></p>
    <button type="button" id="responseOkBtn" onclick="closeResponseModal()">OK</button>
  </div>
</div>

<script>
const allUsers = <?= json_encode($all_rows, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;

const zamboangaDelSurData = {
  "Pagadian City": ["Alegria", "Balangasan", "Balintawak", "Baloyboan", "Banale", "Bogo", "Bomba", "Buenavista", "Bulatok", "Bulawan", "Dampalan", "Danlugan", "Dao", "Datagan", "Deborok", "Ditoray", "Dumagoc", "Gatas", "Gubac", "Gubang", "Kagawasan", "Kahayagan", "Kalasan", "Kawit", "La Suerte", "Lala", "Lapidian", "Lenienza", "Lizon Valley", "Lourdes", "Lower Sibatang", "Lumad", "Lumbia", "Macasing", "Manga", "Muricay", "Napolan", "Palpalan", "Pedulonan", "Poloyagan", "San Francisco", "San Jose", "San Pedro", "Santa Lucia", "Santa Maria", "Santiago", "Santo Niño", "Tawagan Sur", "Tiguma", "Tuburan", "Tulangan", "Tulawas", "Upper Sibatang", "White Beach"],
  "Aurora": ["Acad", "Alang-alang", "Alegria", "Anonang", "Bagong Mandaue", "Bagong Maslog", "Bagong Oslob", "Bagong Pitogo", "Baki", "Balas", "Balide", "Balintawak", "Bayabas", "Bemposa", "Cabilinan", "Campo Uno", "Ceboneg", "Commonwealth", "Gubaan", "Inasagan", "Inroad", "Kahayagan East (Katipunan)", "Kahayagan West", "Kauswagan", "La Paz (Tinibtiban)", "La Victoria", "Lantungan", "Libertad", "Lintugop", "Lubid", "Maguikay", "Mahayahay", "Monte Alegre", "Montela", "Napo", "Panaghiusa", "Poblacion", "Resthouse", "Romarate", "San Jose", "San Juan", "Sapa Loboc", "Tagulalo", "Waterfall"],
  "Bayog": ["Baking", "Balukbahan", "Balumbunan", "Bantal", "Bobuan", "Camp Blessing", "Canoayan", "Conacon", "Dagum", "Damit", "Datagan", "Depase", "Depili", "Depore", "Deporehan", "Dimalinao", "Kahayagan", "Kanipaan", "Lamare", "Liba", "Matin-ao", "Matun-og", "Pangi (San Isidro)", "Poblacion", "Pulang Bato", "Salawagan", "Sigacad", "Supon"],
  "Dimataling": ["Bacayawan", "Baha", "Balanagan", "Baluno", "Binuay", "Buburay", "Grap", "Josefina", "Kagawasan", "Lalab", "Libertad", "Magahis", "Mahayag", "Mercedes", "Poblacion", "Saloagan", "San Roque", "Sugbay Uno", "Sumbato", "Sumpot", "Tinggabulong", "Tiniguangan", "Tipangi", "Upper Ludiong"],
  "Dinas": ["Bacawan", "Benuatan", "Beray", "Don Jose", "Dongos", "East Migpulao", "Guinicolalay", "Ignacio Garrata (New Mirapao)", "Kinacap", "Legarda 1", "Legarda 2", "Legarda 3", "Lower Dimaya", "Lucoban", "Ludiong", "Nangka", "Nian", "Old Mirapao", "Pisa-an", "Poblacion", "Proper Dimaya", "Sagacad", "Sambulawan", "San Isidro", "Songayan", "Sumpotan", "Tarakan", "Upper Dimaya", "Upper Sibul", "West Migpulao"],
  "Dumalinao": ["Anonang", "Bag-ong Misamis", "Bag-ong Silao", "Baga", "Baloboan", "Banta-ao", "Bibilik", "Calingayan", "Camalig", "Camanga", "Cuatro-cuatro", "Locuban", "Malasik", "Mama (San Juan)", "Matab-ang", "Mecolong", "Metokong", "Motosawa", "Pag-asa (Poblacion)", "Paglaum (Poblacion)", "Pantad", "Piniglibano", "Rebokon", "San Agustin", "Sibucao", "Sumadat", "Tikwas", "Tina", "Tubo-Pait", "Upper Dumalinao"],
  "Dumingag": ["Bag-ong Valencia", "Bagong Kauswagan", "Bagong Silang", "Bucayan", "Calumanggi", "Canibong", "Caridad", "Danlugan", "Dapiwak", "Datu Totocan", "Dilud", "Ditulan", "Dulian", "Dulop", "Guintananan", "Guitran", "Gumpingan", "La Fortuna", "Labangon", "Libertad", "Licabang", "Lipawan", "Lower Landing", "Lower Timonan", "Macasing", "Mahayahay", "Malagalad", "Manlabay", "Maralag", "Marangan", "New Basak", "Saad", "Salvador", "San Juan", "San Pablo (Poblacion)", "San Pedro (Poblacion)", "San Vicente", "Senote", "Sinonok", "Sunop", "Tagun", "Tamurayan", "Upper Landing", "Upper Timonan"],
  "Guipos": ["Bagong Oroquieta", "Baguitan", "Balongating", "Canunan", "Dacsol", "Dagohoy", "Dalapang", "Datagan", "Poblacion", "Guling", "Katipunan", "Lintum", "Litan", "Magting", "Regla", "Sikatuna", "Singclot"],
  "Josefina": ["Bogo Calabat", "Dawa", "Ebarle", "Gumahan", "Leonardo", "Litapan", "Lower Bagong Tudela", "Mansanas", "Moradji", "Nemeño", "Nopulan", "Sebukang", "Tagaytay Hill", "Upper Bagong Tudela"],
  "Kumalarang": ["Bogayo", "Bolisong", "Boyugan East", "Boyugan West", "Bualan", "Diplo", "Gawil", "Gusom", "Kitaan Dagat", "Lantawan", "Limamawan", "Mahayahay", "Pangi", "Picanan", "Poblacion", "Salagmanok", "Secade", "Suminalum"],
  "Labangan": ["Bagalupa", "Balimbingan", "Binayan", "Bokong", "Bulanit", "Cogonan", "Combo", "Dalapang", "Dimasangca", "Dipaya", "Langapod", "Lantian", "Lower Campo Islam", "Lower Pulacan", "Lower Sang-an", "New Labangan", "Noboran", "Old Labangan", "San Isidro", "Santa Cruz", "Tapodoc", "Tawagan Norte", "Upper Campo Islam", "Upper Pulacan", "Upper Sang-an"],
  "Lakewood": ["Baking", "Bagong Kahayag", "Biswangan", "Bululawan", "Dagum", "Gasa", "Gatub", "Poblacion", "Lukuan", "Matalang", "Sapang Pinoles", "Sebuguey", "Tiwales", "Tubod"],
  "Lapuyan": ["Bulawan", "Carpoc", "Danganan", "Dansal", "Dumara", "Linokmadalum", "Luanan", "Lubusan", "Mahalingeb", "Mandeg", "Maralag", "Maruing", "Molum", "Pampang", "Pantad", "Pingalay", "Poblacion", "Salambuyan", "San Jose", "Sayog", "Tabon", "Talabob", "Tiguha", "Tininghalang", "Tipasan", "Tugaya"],
  "Mahayag": ["Bag-ong Balamban", "Bag-ong Dalaguete", "Boniao", "Delusom", "Diwan", "Guripan", "Kaangayan", "Kabuhi", "Lourmah", "Lower Salug Daku", "Lower Santo Niño", "Malubo", "Manguiles", "Marabanan", "Panagaan", "Paraiso", "Pedagan", "Poblacion", "Pugwan", "San Isidro", "San Jose", "San Vicente", "Santa Cruz", "Sicpao", "Tuboran", "Tulan", "Tumapic", "Upper Salug Daku", "Upper Santo Niño"],
  "Margosatubig": ["Balintawak", "Bularong", "Digon", "Guinimanan", "Igat Island", "Josefina", "Kalian", "Kolot", "Limbatong", "Limamawan", "Lumbog", "Magahis", "Poblacion", "Sagua", "Talanusa", "Tiguian", "Tulapok"],
  "Midsalip": ["Bacahan", "Balonai", "Bibilop", "Buloron", "Cabaloran", "Canipay Norte", "Canipay Sur", "Cumaron", "Dakayakan", "Duelic", "Dumalinao", "Ecuan", "Golictop", "Guinabot", "Guitalos", "Guma", "Kahayagan", "Licuro-an", "Lumpunid", "Matalang", "New Katipunan", "New Unidos", "Palili", "Pawan", "Pili", "Pisompongan", "Piwan", "Poblacion A", "Poblacion B", "Sigapod", "Timbaboy", "Tulbong", "Tuluan"],
  "Molave": ["Alicia", "Ariosa", "Bagong Argao", "Bagong Gutlang", "Blancia", "Bogo Capalaran", "Culo", "Dalaon", "Dipolo", "Dontulan", "Gonosan", "Lower Dimalinao", "Lower Dimorok", "Mabuhay", "Madasigon", "Makuguihon", "Maloloy-on", "Miligan", "Parasan", "Rizal", "Santo Rosario", "Silangit", "Simata", "Sudlon", "Upper Dimorok"],
  "Pitogo": ["Balabawan", "Balong-balong", "Colojo", "Liasan", "Liguac", "Limbayan", "Lower Paniki-an", "Matin-ao", "Panubigan", "Poblacion", "Punta Flecha", "Sugbay Dos", "Tongao", "Upper Paniki-an"],
  "Ramon Magsaysay": ["Bagong Opon", "Bambong Daku", "Bambong Diut", "Bobongan", "Campo IV", "Campo V", "Caniangan", "Dipalusan", "Eastern Bobongan", "Esperanza", "Gapasan", "Katipunan", "Kauswagan", "Lower Sambulawan", "Mabini", "Magsaysay", "Malating", "Paradise", "Pasingkalan", "Poblacion", "San Fernando", "Santo Rosario", "Sapa Anding", "Sinaguing", "Switch", "Upper Laperian", "Wakat"],
  "San Miguel": ["Betinan", "Bulawan", "Calube", "Concepcion", "Dao-an", "Dumalian", "Fatima", "Langilan", "Lantawan", "Laperian", "Libuganan", "Limonan", "Mati", "Ocapan", "Poblacion", "San Isidro", "Sayog", "Tapian"],
  "San Pablo": ["Bag-ong Misamis", "Bubual", "Buton", "Culasian", "Daplayan", "Kalilangan", "Kapamanok", "Kondum", "Lumbayao", "Mabuhay", "Marcos Village", "Miasin", "Molansong", "Pantad", "Pao", "Payag", "Poblacion", "Pongapong", "Sacbulan", "Sagasan", "San Juan", "Senior", "Songgoy", "Tandubuay", "Taniapan", "Ticala Island", "Tubo-pait", "Villakapa"],
  "Sominot": ["Bag-ong Baroy", "Bag-ong Oroquieta", "Barubuhan", "Bulanay", "Datagan", "Eastern Poblacion", "Lantawan", "Libertad", "Lumangoy", "New Carmen", "Picturan", "Poblacion", "Rizal", "San Miguel", "Santo Niño", "Sawa", "Tungawan", "Upper Sicpao"],
  "Tabina": ["Abong-abong", "Baganian", "Baya-baya", "Capisan", "Concepcion", "Culabay", "Doña Josefina", "Lumbia", "Mabuhay", "Malim", "Manikaan", "New Oroquieta", "Poblacion", "San Francisco", "Tultolan"],
  "Tambulig": ["Alang-alang", "Angeles", "Bag-ong Kauswagan", "Bag-ong Tabogon", "Balugo", "Cabgan", "Calolot", "Dimalinao", "Fabian", "Gabunon", "Happy Valley", "Kapalaran", "Libato", "Limamawan", "Lower Liasan", "Lower Lodiong", "Lower Tiparak", "Lower Usogan", "Maya-maya", "New Village", "Pelocoban", "Riverside", "Sagrada Familia", "San Jose", "San Vicente", "Sumalig", "Tuluan", "Tungawan", "Upper Liason", "Upper Lodiong", "Upper Tiparak"],
  "Tigbao": ["Begong", "Busol", "Caluma", "Diana Countryside", "Guinlin", "Lacarayan", "Lacupayan", "Libayoy", "Limas", "Longmot", "Maragang", "Mate", "Nangan-nangan", "New Tuburan", "Nilo", "Tigbao", "Timolan", "Upper Nilo"],
  "Tukuran": ["Alindahaw", "Baclay", "Balimbingan", "Buenasuerte", "Camanga", "Curvada", "Laperian", "Libertad", "Lower Bayao", "Luy-a", "Manilan", "Manlayag", "Militar", "Navalan", "Panduma Senior", "Sambulawan", "San Antonio", "San Carlos", "Santo Niño", "Santo Rosario", "Sugod", "Tabuan", "Tagulo", "Tinotungan", "Upper Bayao"],
  "Vincenzo A. Sagun": ["Bui-os", "Cogon", "Danan", "Kabatan", "Kapatagan", "Limason", "Linoguayan", "Lumbal", "Lunib", "Maculay", "Maraya", "Sagucan", "Waling-waling", "Ambulon"]
};

let refreshOnResponseClose = false;

function sanitizeNameInput(inp) {
  inp.value = inp.value.replace(/[0-9]/g, '');
}

function initLocationDropdowns() {
  const muniSelect = document.getElementById('add_municipality');
  muniSelect.innerHTML = '<option value="">Select Municipality</option>';
  
  Object.keys(zamboangaDelSurData).sort().forEach(muni => {
    const opt = document.createElement('option');
    opt.value = muni;
    opt.textContent = muni;
    muniSelect.appendChild(opt);
  });

  const bgySelect = document.getElementById('add_barangay');
  bgySelect.innerHTML = '<option value="">Select Barangay</option>';
  bgySelect.disabled = true;
}

function updateBarangays() {
  const muniSelect = document.getElementById('add_municipality');
  const bgySelect = document.getElementById('add_barangay');
  const selectedMuni = muniSelect.value;

  bgySelect.innerHTML = '<option value="">Select Barangay</option>';
  
  if (selectedMuni && zamboangaDelSurData[selectedMuni]) {
    bgySelect.disabled = false;
    zamboangaDelSurData[selectedMuni].sort().forEach(bgy => {
      const opt = document.createElement('option');
      opt.value = bgy;
      opt.textContent = bgy;
      bgySelect.appendChild(opt);
    });
  } else {
    bgySelect.disabled = true;
  }
}

document.addEventListener('DOMContentLoaded', () => {
  initLocationDropdowns();
});

// Tabs Switcher
document.querySelectorAll('.tab-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById(btn.dataset.tab).classList.add('active');
  });
});

function viewByIndex(index) {
  openViewModal(allUsers[index]);
}

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
    closeModal('viewModal');
    closeModal('responseModal');
  }
});

// Birthdate MM/DD/YYYY Input Mask Validation
function handleDobInput(inp) {
  let raw = inp.value.replace(/\D/g, '');
  let month = '';
  let day = '';
  let year = '';

  if (raw.length > 0) {
    let m1 = raw.charAt(0);
    if (m1 > '1') m1 = '';
    
    let m2 = '';
    if (raw.length > 1 && m1 !== '') {
      m2 = raw.charAt(1);
      if (m1 === '1' && m2 > '2') m2 = '';
      if (m1 === '0' && m2 === '0') m2 = '';
    }
    month = m1 + m2;
  }

  if (raw.length >= 3) {
    let d1 = raw.charAt(2);
    if (d1 > '3') d1 = '';
    
    let d2 = '';
    if (raw.length > 3 && d1 !== '') {
      d2 = raw.charAt(3);
      if (d1 === '3' && d2 > '1') d2 = '';
      if (d1 === '0' && d2 === '0') d2 = '';
    }
    day = d1 + d2;
  }

  if (raw.length >= 5) {
    year = raw.substring(4, 8);
  }

  let formatted = month;
  if (month.length === 2) formatted += '/';
  if (day) formatted += day;
  if (day.length === 2) formatted += '/';
  if (year) formatted += year;

  inp.value = formatted;

  if (month.length === 2 && day.length === 2 && year.length === 4) {
    const mm = parseInt(month, 10) - 1;
    const dd = parseInt(day, 10);
    const yy = parseInt(year, 10);
    const birth = new Date(yy, mm, dd);

    if (!isNaN(birth.getTime()) && yy > 1900) {
      const today = new Date();
      let age = today.getFullYear() - birth.getFullYear();
      const mo = today.getMonth() - birth.getMonth();
      if (mo < 0 || (mo === 0 && today.getDate() < birth.getDate())) age--;
      document.getElementById('age_field').value = age >= 0 ? age : '';
    } else {
      document.getElementById('age_field').value = '';
    }
  } else {
    document.getElementById('age_field').value = '';
  }
}

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

  btn.disabled = !(np !== '' && cp !== '');
}

function submitAdminPassword() {
  const np = document.getElementById('new_password').value.trim();
  const cp = document.getElementById('confirm_password').value.trim();
  
  if (np !== cp) {
    document.getElementById('passwordMatchError').style.display = 'block';
    return;
  }
  
  const form = document.getElementById('adminPasswordForm');
  
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

function validateAddStaffForm() {
  const form = document.getElementById('addStaffForm');
  const requiredInputs = form.querySelectorAll('input[required], select[required]');
  const btn = document.getElementById('addStaffBtn');
  
  const fname = document.getElementById('add_first_name').value.trim().toLowerCase();
  const mname = document.getElementById('add_middle_name').value.trim().toLowerCase();
  const lname = document.getElementById('add_last_name').value.trim().toLowerCase();
  const email = document.getElementById('add_email').value.trim().toLowerCase();
  const pw = document.getElementById('modal_password').value;
  const cpw = document.getElementById('modal_confirm_password').value;

  let nameExists = false;
  if (fname && lname) {
    nameExists = allUsers.some(u => {
      const uFirst = (u.first_name || '').toLowerCase();
      const uMiddle = (u.middle_name || '').toLowerCase();
      const uLast = (u.last_name || '').toLowerCase();
      return uFirst === fname && uMiddle === mname && uLast === lname;
    });
  }
  document.getElementById('nameExistsError').style.display = nameExists ? 'block' : 'none';

  let emailExists = false;
  if (email) {
    emailExists = allUsers.some(u => (u.email || '').toLowerCase() === email);
  }
  document.getElementById('emailExistsError').style.display = emailExists ? 'block' : 'none';

  let pwMismatch = false;
  if (pw && cpw && pw !== cpw) {
    pwMismatch = true;
  }
  document.getElementById('addPasswordMatchError').style.display = pwMismatch ? 'block' : 'none';

  let allFilled = true;
  requiredInputs.forEach(input => {
    if (!input.value.trim()) allFilled = false;
  });

  btn.disabled = !(allFilled && !nameExists && !emailExists && !pwMismatch);
}

function handleAddStaffSubmit(e) {
  e.preventDefault();
  const form = document.getElementById('addStaffForm');

  fetch(form.action, { method:'POST', body: new FormData(form) })
    .then(res => res.json())
    .then(data => {
      if (data.status === 'success') {
        closeModal('addStaffModal');
        showResponseModal('Account added successfully!', true);
        form.reset();
        initLocationDropdowns();
        validateAddStaffForm();
      } else {
        alert(data.message || 'Error creating user account.');
      }
    })
    .catch(() => {
      closeModal('addStaffModal');
      showResponseModal('Account added successfully!', true);
      form.reset();
      initLocationDropdowns();
      validateAddStaffForm();
    });

  return false;
}

function openViewModal(u) {
  const f = v => (v && String(v).trim()) ? v : '—';
  const fmtDob = d => {
    if (!d || d === '0000-00-00') return '—';
    const p = d.split('-');
    if (p.length !== 3) return d;
    const months = ['January','February','March','April','May','June',
                    'July','August','September','October','November','December'];
    return months[parseInt(p[1],10)-1] + ' ' + parseInt(p[2],10) + ', ' + p[0];
  };
  const fmtDate = d => {
    if (!d) return '—';
    const dt = new Date(d);
    const months = ['January','February','March','April','May','June',
                    'July','August','September','October','November','December'];
    return months[dt.getMonth()] + ' ' + dt.getDate() + ', ' + dt.getFullYear();
  };

  let firstName = u.first_name;
  let lastName = u.last_name;
  if (!firstName && u.name) {
    const parts = u.name.split(' ');
    firstName = parts[0];
    lastName = parts.slice(1).join(' ');
  }

  /* document.getElementById('vm-title').textContent        = f(u.name);
  document.getElementById('vm-subtitle').textContent     = f(u.user_role); */
  document.getElementById('vm-first').textContent        = f(firstName);
  document.getElementById('vm-middle').textContent       = f(u.middle_name);
  document.getElementById('vm-last').textContent         = f(lastName);
  document.getElementById('vm-name').textContent         = f(u.name);
  document.getElementById('vm-dob').textContent          = fmtDob(u.date_of_birth);
  document.getElementById('vm-age').textContent          = u.age ? u.age + ' yrs old' : '—';
  document.getElementById('vm-email').textContent        = f(u.email);
  document.getElementById('vm-province').textContent     = f(u.province);
  document.getElementById('vm-municipality').textContent = f(u.municipality);
  document.getElementById('vm-barangay').textContent     = f(u.barangay);
  const role = (u.user_role || '').toLowerCase().trim();
  let displayRole;
  if (role === 'it admin') {
      displayRole = 'IT Admin';
  } else if (role === 'da staff') {
      displayRole = 'DA Staff';
  } else if (role === 'department of agriculture') {
      displayRole = 'DA Official';
  } else {
      displayRole = u.user_role;
  }
  document.getElementById('vm-role').textContent = displayRole;
  document.getElementById('vm-created').textContent      = fmtDate(u.created_at);

  openModal('viewModal');
}
</script>

<?php $conn->close(); ?>
</body>
</html>