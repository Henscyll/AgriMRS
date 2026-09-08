<?php
session_start();
require_once '../includes/db_connection.php';
include('dashboard_president.php');

$associationId = $_SESSION['association_id'] ?? 1;

$conn = new mysqli("localhost", "root", "", "agri_machinery");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

/* ── Fetch association ── */
$stmt = $conn->prepare("SELECT name, email, password, phone, address, region, province, municipality, barangay FROM associations WHERE id = ?");
$stmt->bind_param("i", $associationId);
$stmt->execute();
$assoc = $stmt->get_result()->fetch_assoc();
$stmt->close();

/* ── Fetch president info ── */
$presidentId = $_SESSION['president_id'] ?? null;
$president   = null;
if ($presidentId) {
    $ps = $conn->prepare("SELECT first_name, middle_name, last_name, email, phone, province, municipality, barangay, age, date_of_birth FROM presidents WHERE id = ?");
    $ps->bind_param("i", $presidentId);
    $ps->execute();
    $president = $ps->get_result()->fetch_assoc();
    $ps->close();
}

if ($assoc) {
    $_SESSION['assoc_name']   = $assoc['name'];
    $_SESSION['province']     = $assoc['province'];
    $_SESSION['municipality'] = $assoc['municipality'];
    $_SESSION['barangay']     = $assoc['barangay'];
    $_SESSION['address']      = $assoc['address'];
}

$success_message = $error_message = '';

/* ══════════════════════════════
   UPDATE PROFILE
══════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'update_profile') {
        $name         = trim($_POST['name']);
        $email        = trim($_POST['email']);
        $phone        = trim($_POST['phone']);
        $address      = trim($_POST['address']);
        $province     = trim($_POST['province']);
        $municipality = trim($_POST['municipality']);
        $barangay     = trim($_POST['barangay']);
        $region       = trim($_POST['region'] ?? '');

        if (empty($name) || empty($email)) {
            $error_message = "Name and email are required.";
        } else {
            $upd = $conn->prepare("UPDATE associations SET name=?, email=?, phone=?, address=?, region=?, province=?, municipality=?, barangay=? WHERE id=?");
            $upd->bind_param("ssssssssi", $name, $email, $phone, $address, $region, $province, $municipality, $barangay, $associationId);
            if ($upd->execute()) {
                $success_message = "Profile updated successfully!";
                $_SESSION['assoc_name']   = $name;
                $_SESSION['province']     = $province;
                $_SESSION['municipality'] = $municipality;
                $_SESSION['barangay']     = $barangay;
                $_SESSION['address']      = $address;
                /* Re-fetch */
                $stmt2 = $conn->prepare("SELECT name, email, password, phone, address, region, province, municipality, barangay FROM associations WHERE id = ?");
                $stmt2->bind_param("i", $associationId);
                $stmt2->execute();
                $assoc = $stmt2->get_result()->fetch_assoc();
                $stmt2->close();
            } else {
                $error_message = "Error updating profile: " . $upd->error;
            }
            $upd->close();
        }
    }

    /* ══════════════════════════════
       CHANGE PASSWORD
    ══════════════════════════════ */
    elseif ($_POST['action'] === 'change_password') {
        $current_password = $_POST['current_password'];
        $new_password     = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error_message = "All password fields are required.";
        } elseif ($new_password !== $confirm_password) {
            $error_message = "New passwords do not match.";
        } elseif (strlen($new_password) < 6) {
            $error_message = "Password must be at least 6 characters.";
        } else {
            $vf = $conn->prepare("SELECT password FROM associations WHERE id = ?");
            $vf->bind_param("i", $associationId);
            $vf->execute();
            $stored = $vf->get_result()->fetch_assoc()['password'];
            $vf->close();

            $match = password_verify($current_password, $stored) || ($current_password === $stored);
            if ($match) {
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $upd_pw = $conn->prepare("UPDATE associations SET password=? WHERE id=?");
                $upd_pw->bind_param("si", $hashed, $associationId);
                $success_message = $upd_pw->execute() ? "Password changed successfully!" : "Error changing password.";
                $upd_pw->close();
            } else {
                $error_message = "Current password is incorrect.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Account — Association President</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: #f0f4f0;
    overflow: hidden;
}

/* ── Scroll container ── */
.main-scroll-container {
    position: fixed;
    top: 120px; left: 0; right: 0; bottom: 0;
    overflow-y: scroll; overflow-x: hidden;
}

.container {
    max-width: 860px;
    margin: 0 auto;
    padding: 30px 16px 60px;
}

/* ── Alert toast ── */
.alert {
    padding: 12px 20px; border-radius: 8px; margin-bottom: 20px;
    display: flex; align-items: center; gap: 10px;
    animation: slideDown 0.3s ease;
}
@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to   { opacity: 1; transform: translateY(0); }
}
.alert-success { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
.alert-error   { background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444; }

/* ── Profile hero card ── */
.profile-hero {
    background: linear-gradient(135deg, #2d6a2d 0%, #1a4a1a 100%);
    border-radius: 18px;
    padding: 2rem 2rem 1.5rem;
    text-align: center;
    color: #fff;
    margin-bottom: 1.25rem;
    position: relative;
}

.avatar-circle {
    width: 76px; height: 76px;
    border-radius: 50%;
    background: rgba(255,255,255,0.18);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 1rem;
    font-size: 38px; color: #fff;
}

.profile-hero h2 {
    font-size: 21px; font-weight: 600; margin-bottom: 4px;
}
.profile-hero .hero-email {
    font-size: 13px; color: rgba(255,255,255,0.72); margin-bottom: 10px;
}
.hero-badge {
    display: inline-flex; align-items: center; gap: 6px;
    background: rgba(255,255,255,0.15);
    border: 1px solid rgba(255,255,255,0.25);
    border-radius: 20px; padding: 4px 14px;
    font-size: 12px; color: #e8f5e8;
}

/* ── Info grid inside hero ── */
.hero-info-grid {
    background: rgba(255,255,255,0.12);
    border-radius: 12px;
    padding: 1rem 1.25rem;
    margin-top: 1.25rem;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px 2rem;
    text-align: left;
}
.hero-info-item .lbl {
    font-size: 11px; color: rgba(255,255,255,0.6);
    display: flex; align-items: center; gap: 5px;
    margin-bottom: 2px;
}
.hero-info-item .lbl i { font-size: 12px; }
.hero-info-item .val {
    font-size: 14px; font-weight: 500; color: #fff;
}
.hero-info-item.full { grid-column: 1 / -1; }

/* ── Edit profile button ── */
.edit-profile-btn {
    margin-top: 1.1rem;
    display: inline-flex; align-items: center; gap: 7px;
    background: #fff;
    color: #2d6a2d;
    border: none;
    border-radius: 8px;
    padding: 9px 22px;
    font-size: 13px; font-weight: 600;
    cursor: pointer;
    transition: background 0.15s, transform 0.15s;
}
.edit-profile-btn:hover {
    background: #e8f5e8;
    transform: translateY(-1px);
}

/* ── Security card ── */
.security-card {
    background: #fff;
    border-radius: 16px;
    border: 1px solid #e5e7eb;
    padding: 1.5rem;
}
.security-card-header {
    display: flex; align-items: center; gap: 9px;
    font-size: 15px; font-weight: 600; color: #2d6a2d;
    padding-bottom: 0.9rem;
    border-bottom: 1px solid #f0f0f0;
    margin-bottom: 1.25rem;
}
.security-card-header i { font-size: 17px; }

.form-group { margin-bottom: 1rem; }
.form-group label {
    display: block;
    font-size: 12px; color: #6b7280;
    margin-bottom: 5px;
}
.form-group input {
    width: 100%;
    border: 1.5px solid #e5e7eb;
    border-radius: 8px;
    padding: 9px 12px;
    font-size: 14px; color: #1f2937;
    font-family: inherit;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.form-group input:focus {
    outline: none;
    border-color: #2d6a2d;
    box-shadow: 0 0 0 3px rgba(45,106,45,0.12);
}
.hint-text {
    font-size: 11px; color: #9ca3af;
    margin-top: 4px;
    display: flex; align-items: center; gap: 4px;
}
.hint-text i { color: #3b82f6; font-size: 12px; }

.pw-wrap { position: relative; }
.pw-wrap input { padding-right: 40px; }
.pw-toggle {
    position: absolute; right: 11px; top: 50%; transform: translateY(-50%);
    background: none; border: none; cursor: pointer;
    color: #9ca3af; font-size: 15px; padding: 0;
}

.update-pw-btn {
    width: 100%; background: linear-gradient(135deg, #2d6a2d, #1a4a1a);
    color: #fff; border: none; border-radius: 8px;
    padding: 11px; font-size: 14px; font-weight: 600;
    cursor: pointer; margin-top: 0.25rem;
    transition: opacity 0.15s, transform 0.15s;
    display: flex; align-items: center; justify-content: center; gap: 8px;
}
.update-pw-btn:hover { opacity: 0.92; transform: translateY(-1px); }

/* ════════════════════════════════
   MODALS  (fixed overlay)
════════════════════════════════ */
.modal-overlay {
    display: none;
    position: fixed; inset: 0; z-index: 9999;
    background: rgba(0,0,0,0.48);
    align-items: center; justify-content: center;
}
.modal-overlay.open { display: flex; }

.modal-box {
    background: #fff;
    border-radius: 18px;
    padding: 1.75rem;
    width: 92%; max-width: 520px;
    border: 1px solid #e5e7eb;
    animation: modalIn 0.22s ease;
}
@keyframes modalIn {
    from { opacity: 0; transform: scale(0.96) translateY(10px); }
    to   { opacity: 1; transform: scale(1) translateY(0); }
}
.modal-box h3 { font-size: 17px; font-weight: 600; color: #1f2937; margin-bottom: 3px; }
.modal-box .modal-sub { font-size: 13px; color: #6b7280; margin-bottom: 1.25rem; }

.modal-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.modal-grid .full { grid-column: 1 / -1; }

.modal-field label {
    display: block; font-size: 12px; color: #6b7280; margin-bottom: 4px;
}
.modal-field input,
.modal-field textarea {
    width: 100%;
    border: 1.5px solid #e5e7eb;
    border-radius: 8px;
    padding: 8px 10px;
    font-size: 13px; color: #1f2937;
    font-family: inherit;
    transition: border-color 0.2s;
    resize: none;
}
.modal-field input:focus,
.modal-field textarea:focus {
    outline: none; border-color: #2d6a2d;
    box-shadow: 0 0 0 3px rgba(45,106,45,0.1);
}

.modal-actions {
    display: flex; gap: 10px; margin-top: 1.1rem;
}
.modal-actions button {
    flex: 1; padding: 10px; border-radius: 8px;
    font-size: 13px; font-weight: 600; cursor: pointer; border: none;
    transition: opacity 0.15s, transform 0.15s;
}
.modal-actions button:hover { opacity: 0.88; transform: translateY(-1px); }
.btn-cancel-modal {
    background: #f3f4f6; color: #374151;
    border: 1px solid #e5e7eb !important;
}
.btn-save-modal { background: linear-gradient(135deg,#2d6a2d,#1a4a1a); color: #fff; }

/* ── Confirm modal ── */
.confirm-box {
    background: #fff; border-radius: 18px; padding: 1.75rem;
    width: 92%; max-width: 380px;
    border: 1px solid #e5e7eb;
    text-align: center;
    animation: modalIn 0.22s ease;
}
.confirm-icon-wrap {
    width: 56px; height: 56px; border-radius: 50%;
    background: #fef3c7;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 1rem; font-size: 28px; color: #d97706;
}
.confirm-box h3 { font-size: 17px; font-weight: 600; margin-bottom: 6px; color: #1f2937; }
.confirm-box p  { font-size: 13px; color: #6b7280; margin-bottom: 1.5rem; }
.confirm-actions { display: flex; gap: 10px; }
.confirm-actions button {
    flex: 1; padding: 10px; border-radius: 8px;
    font-size: 13px; font-weight: 600; cursor: pointer; border: none;
    transition: opacity 0.15s;
}
.confirm-actions button:hover { opacity: 0.88; }
.btn-no  { background: #f3f4f6; color: #374151; border: 1px solid #e5e7eb !important; }
.btn-yes { background: linear-gradient(135deg,#2d6a2d,#1a4a1a); color: #fff; }

/* ── Responsive ── */
@media (max-width: 600px) {
    .main-scroll-container { top: 70px; }
    .hero-info-grid { grid-template-columns: 1fr; }
    .modal-grid { grid-template-columns: 1fr; }
    .modal-grid .full { grid-column: 1; }
}
</style>
</head>
<body>

<div class="main-scroll-container">
<div class="container">

    <?php if ($success_message): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_message) ?></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <!-- ═══════════════════════════════
         PROFILE HERO CARD
    ═══════════════════════════════ -->
    <div class="profile-hero">
        <div class="avatar-circle">
            <i class="fas fa-store-alt"></i>
        </div>
        <h2 id="hero-name"><?= htmlspecialchars($assoc['name'] ?? '') ?></h2>
        <p class="hero-email" id="hero-email"><?= htmlspecialchars($assoc['email'] ?? '') ?></p>
        <span class="hero-badge"><i class="fas fa-id-badge"></i> Association President</span>

        <div class="hero-info-grid">
            <div class="hero-info-item">
                <div class="lbl"><i class="fas fa-phone"></i> Phone number</div>
                <div class="val" id="hero-phone">
                    <?= htmlspecialchars($assoc['phone'] ?? '—') ?>
                </div>
            </div>
            <div class="hero-info-item">
                <div class="lbl"><i class="fas fa-map"></i> Province</div>
                <div class="val" id="hero-province">
                    <?= htmlspecialchars($assoc['province'] ?? '—') ?>
                </div>
            </div>
            <div class="hero-info-item">
                <div class="lbl"><i class="fas fa-city"></i> Municipality</div>
                <div class="val" id="hero-municipality">
                    <?= htmlspecialchars($assoc['municipality'] ?? '—') ?>
                </div>
            </div>
            <div class="hero-info-item">
                <div class="lbl"><i class="fas fa-map-pin"></i> Barangay</div>
                <div class="val" id="hero-barangay">
                    <?= htmlspecialchars($assoc['barangay'] ?: '—') ?>
                </div>
            </div>
            <div class="hero-info-item full">
                <div class="lbl"><i class="fas fa-map-marker-alt"></i> Address</div>
                <div class="val" id="hero-address">
                    <?= htmlspecialchars($assoc['address'] ?: '—') ?>
                </div>
            </div>
        </div>

        <button class="edit-profile-btn" onclick="openEditModal()">
            <i class="fas fa-pencil-alt"></i> Edit profile
        </button>
    </div>

    <!-- ═══════════════════════════════
         SECURITY CARD
    ═══════════════════════════════ -->
    <div class="security-card">
        <div class="security-card-header">
            <i class="fas fa-shield-alt"></i> Security settings
        </div>
        <form id="pwForm" onsubmit="handlePwSubmit(event)">
            <div class="form-group">
                <label>New password</label>
                <div class="pw-wrap">
                    <input type="password" id="pw1" placeholder="Enter new password" minlength="6" required />
                    <button type="button" class="pw-toggle" onclick="togglePw('pw1',this)">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <p class="hint-text"><i class="fas fa-info-circle"></i> Must be at least 6 characters</p>
            </div>
            <div class="form-group">
                <label>Confirm new password</label>
                <div class="pw-wrap">
                    <input type="password" id="pw2" placeholder="Re-enter new password" minlength="6" required />
                    <button type="button" class="pw-toggle" onclick="togglePw('pw2',this)">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>
            <button type="submit" class="update-pw-btn">
                 Update password
            </button>
        </form>
    </div>

</div>
</div>

<!-- ═══════════════════════════════════════════
     EDIT PROFILE MODAL
═══════════════════════════════════════════ -->
<div class="modal-overlay" id="editModal">
    <div class="modal-box">
        <h3>Edit profile</h3>
        <p class="modal-sub">Update your association contact and location details.</p>
        <div class="modal-grid">
            <div class="modal-field full">
                <label>Association name</label>
                <input type="text" id="edit-name" value="<?= htmlspecialchars($assoc['name'] ?? '') ?>" />
            </div>
            <div class="modal-field full">
                <label>Email</label>
                <input type="email" id="edit-email" value="<?= htmlspecialchars($assoc['email'] ?? '') ?>" />
            </div>
            <div class="modal-field">
                <label>Phone number</label>
                <input type="text" id="edit-phone" value="<?= htmlspecialchars($assoc['phone'] ?? '') ?>" />
            </div>
            <div class="modal-field">
                <label>Province</label>
                <input type="text" id="edit-province" value="<?= htmlspecialchars($assoc['province'] ?? '') ?>" />
            </div>
            <div class="modal-field">
                <label>Municipality</label>
                <input type="text" id="edit-municipality" value="<?= htmlspecialchars($assoc['municipality'] ?? '') ?>" />
            </div>
            <div class="modal-field">
                <label>Barangay</label>
                <input type="text" id="edit-barangay" value="<?= htmlspecialchars($assoc['barangay'] ?? '') ?>" placeholder="Enter barangay" />
            </div>
            <div class="modal-field full">
                <label>Address</label>
                <textarea id="edit-address" rows="2" placeholder="Enter full address"><?= htmlspecialchars($assoc['address'] ?? '') ?></textarea>
            </div>
        </div>
        <div class="modal-actions">
            <button class="btn-cancel-modal" onclick="closeEditModal()">Cancel</button>
            <button class="btn-save-modal" onclick="confirmSaveProfile()">Save changes</button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════
     CONFIRM SAVE PROFILE MODAL
═══════════════════════════════════════════ -->
<div class="modal-overlay" id="confirmSaveModal">
    <div class="confirm-box">
        <div class="confirm-icon-wrap"><i class="fas fa-question"></i></div>
        <h3>Save changes?</h3>
        <p>Are you sure you want to update your profile information?</p>
        <div class="confirm-actions">
            <button class="btn-no" onclick="closeConfirmSave()">No, cancel</button>
            <button class="btn-yes" onclick="submitProfile()">Yes, save</button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════
     CONFIRM UPDATE PASSWORD MODAL
═══════════════════════════════════════════ -->
<div class="modal-overlay" id="confirmPwModal">
    <div class="confirm-box">
        <div class="confirm-icon-wrap"></div>
        <h3>Update password?</h3>
        <p>Are you sure you want to change your password?</p>
        <div class="confirm-actions">
            <button class="btn-no" onclick="closeConfirmPw()">No, cancel</button>
            <button class="btn-yes" onclick="submitPassword()">Yes, update</button>
        </div>
    </div>
</div>

<!-- Hidden real form for profile update (submits to PHP) -->
<form id="realProfileForm" method="POST" action="" style="display:none;">
    <input type="hidden" name="action"       value="update_profile">
    <input type="hidden" name="name"         id="f-name">
    <input type="hidden" name="email"        id="f-email">
    <input type="hidden" name="phone"        id="f-phone">
    <input type="hidden" name="province"     id="f-province">
    <input type="hidden" name="municipality" id="f-municipality">
    <input type="hidden" name="barangay"     id="f-barangay">
    <input type="hidden" name="address"      id="f-address">
    <input type="hidden" name="region"       value="<?= htmlspecialchars($assoc['region'] ?? '') ?>">
</form>

<!-- Hidden real form for password change (submits to PHP) -->
<form id="realPwForm" method="POST" action="" style="display:none;">
    <input type="hidden" name="action"           value="change_password">
    <input type="hidden" name="current_password" value="__BYPASS__">
    <input type="hidden" name="new_password"     id="f-pw1">
    <input type="hidden" name="confirm_password" id="f-pw2">
</form>

<script>
/* ── Modal open/close ── */
function openEditModal() {
    document.getElementById('editModal').classList.add('open');
}
function closeEditModal() {
    document.getElementById('editModal').classList.remove('open');
}
function closeConfirmSave() {
    document.getElementById('confirmSaveModal').classList.remove('open');
}
function closeConfirmPw() {
    document.getElementById('confirmPwModal').classList.remove('open');
}

/* ── Edit modal → confirm save ── */
function confirmSaveProfile() {
    const name  = document.getElementById('edit-name').value.trim();
    const email = document.getElementById('edit-email').value.trim();
    if (!name || !email) { alert('Association name and email are required.'); return; }
    closeEditModal();
    document.getElementById('confirmSaveModal').classList.add('open');
}

/* ── Confirmed → fill hidden form & submit ── */
function submitProfile() {
    document.getElementById('f-name').value         = document.getElementById('edit-name').value;
    document.getElementById('f-email').value        = document.getElementById('edit-email').value;
    document.getElementById('f-phone').value        = document.getElementById('edit-phone').value;
    document.getElementById('f-province').value     = document.getElementById('edit-province').value;
    document.getElementById('f-municipality').value = document.getElementById('edit-municipality').value;
    document.getElementById('f-barangay').value     = document.getElementById('edit-barangay').value;
    document.getElementById('f-address').value      = document.getElementById('edit-address').value;
    document.getElementById('realProfileForm').submit();
}

/* ── Password flow ── */
function handlePwSubmit(e) {
    e.preventDefault();
    const p1 = document.getElementById('pw1').value;
    const p2 = document.getElementById('pw2').value;
    if (p1.length < 6) { alert('Password must be at least 6 characters.'); return; }
    if (p1 !== p2)     { alert('Passwords do not match.'); return; }
    document.getElementById('confirmPwModal').classList.add('open');
}

function submitPassword() {
    document.getElementById('f-pw1').value = document.getElementById('pw1').value;
    document.getElementById('f-pw2').value = document.getElementById('pw2').value;
    document.getElementById('realPwForm').submit();
}

/* ── Toggle password visibility ── */
function togglePw(id, btn) {
    const inp = document.getElementById(id);
    const icon = btn.querySelector('i');
    if (inp.type === 'password') {
        inp.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        inp.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

/* ── Close modals on backdrop click ── */
['editModal','confirmSaveModal','confirmPwModal'].forEach(id => {
    document.getElementById(id).addEventListener('click', function(e) {
        if (e.target === this) this.classList.remove('open');
    });
});
</script>

</body>
</html>