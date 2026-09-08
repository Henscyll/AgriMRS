<?php
session_start();
require_once '../includes/db_connection.php';
include('operator_dashboard.php');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'operator') {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

/* ── Get operator record ── */
$op_stmt = $conn->prepare("
    SELECT o.*, a.name AS association_name, a.municipality AS assoc_municipality,
           a.province AS assoc_province, a.phone AS assoc_phone
    FROM operators o
    JOIN associations a ON o.association_id = a.id
    WHERE o.user_id = ?
");
$op_stmt->bind_param("i", $user_id);
$op_stmt->execute();
$operator = $op_stmt->get_result()->fetch_assoc();
$op_stmt->close();

if (!$operator) die("Operator profile not found.");
$operator_id = $operator['id'];

$success = $error = '';

/* ══════════════════════════════
   UPDATE PROFILE (phone, municipality, barangay only)
══════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'update_profile') {
        $phone        = trim($_POST['phone']);
        $municipality = trim($_POST['municipality']);
        $barangay     = trim($_POST['barangay']);

        $upd = $conn->prepare("UPDATE operators SET phone=?, municipality=?, barangay=? WHERE user_id=?");
        $upd->bind_param("sssi", $phone, $municipality, $barangay, $user_id);
        if ($upd->execute()) {
            $success = "Profile updated successfully!";
            $op_stmt = $conn->prepare("
                SELECT o.*, a.name AS association_name, a.municipality AS assoc_municipality,
                       a.province AS assoc_province, a.phone AS assoc_phone
                FROM operators o
                JOIN associations a ON o.association_id = a.id
                WHERE o.user_id = ?
            ");
            $op_stmt->bind_param("i", $user_id);
            $op_stmt->execute();
            $operator = $op_stmt->get_result()->fetch_assoc();
            $op_stmt->close();
        } else {
            $error = "Error updating profile.";
        }
        $upd->close();
    }

    /* ══════════════════════════════
       CHANGE PASSWORD
    ══════════════════════════════ */
    elseif ($_POST['action'] === 'change_password') {
        $new_password     = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if (empty($new_password) || empty($confirm_password)) {
            $error = "All password fields are required.";
        } elseif ($new_password !== $confirm_password) {
            $error = "New passwords do not match.";
        } elseif (strlen($new_password) < 6) {
            $error = "Password must be at least 6 characters.";
        } else {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $upd_pw = $conn->prepare("UPDATE users SET password=? WHERE id=?");
            $upd_pw->bind_param("si", $hashed, $user_id);
            $success = $upd_pw->execute() ? "Password changed successfully!" : "Error changing password.";
            $upd_pw->close();
        }
    }

    /* ══════════════════════════════
       DEACTIVATE LOT
    ══════════════════════════════ */
    elseif ($_POST['action'] === 'deactivate_lot') {
        $lot_id = (int)$_POST['lot_id'];
        $deact = $conn->prepare("UPDATE farm_lots SET status='Inactive' WHERE id=? AND operator_id=?");
        $deact->bind_param("ii", $lot_id, $operator_id);
        $success = $deact->execute() ? "Farm lot deactivated." : "Error deactivating lot.";
        $deact->close();
    }
}

/* ── Stats ── */
$stats_stmt = $conn->prepare("
    SELECT
        COUNT(*)                                          AS total_assignments,
        COUNT(CASE WHEN b.status='Completed' THEN 1 END) AS completed,
        COUNT(CASE WHEN b.status='Approved'  THEN 1 END) AS active
    FROM bookings b
    JOIN machine_operators mo ON b.machine_id = mo.machine_id
    WHERE mo.operator_id = ? AND mo.status = 'Active'
");
$stats_stmt->bind_param("i", $operator_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
$stats_stmt->close();

/* ── Assigned machines ── */
$machines_stmt = $conn->prepare("
    SELECT m.id, m.machine_name, m.type, m.image_path, m.status, m.price_per_hectare
    FROM machines m
    JOIN machine_operators mo ON m.id = mo.machine_id
    WHERE mo.operator_id = ? AND mo.status = 'Active'
    ORDER BY m.machine_name ASC
");
$machines_stmt->bind_param("i", $operator_id);
$machines_stmt->execute();
$machines_result = $machines_stmt->get_result();
$machines = [];
while ($m = $machines_result->fetch_assoc()) $machines[] = $m;
$machines_stmt->close();

/* ── Farm lots ── */
$lots_stmt = $conn->prepare("
    SELECT id, lot_name, area_hectares, barangay, municipality, province, status
    FROM farm_lots
    WHERE operator_id = ?
    ORDER BY lot_name ASC
");
$lots_stmt->bind_param("i", $operator_id);
$lots_stmt->execute();
$lots_result = $lots_stmt->get_result();
$lots = [];
while ($l = $lots_result->fetch_assoc()) $lots[] = $l;
$lots_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Account - Operator</title>
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
    margin-bottom: 1.25rem;
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

/* ── Generic white card (machines, lots) ── */
.plain-card {
    background: #fff;
    border-radius: 16px;
    border: 1px solid #e5e7eb;
    overflow: hidden;
    margin-bottom: 1.25rem;
}
.plain-card-header {
    display: flex; align-items: center; gap: 9px;
    font-size: 15px; font-weight: 600; color: #2d6a2d;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid #f0f0f0;
}
.plain-card-header i { font-size: 17px; }
.plain-card-body { padding: 1.25rem; }

/* ── Machines grid ── */
.machines-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 14px; }
.machine-card { border: 1.5px solid #e5e7eb; border-radius: 12px; overflow: hidden; transition: border-color .2s, box-shadow .2s; }
.machine-card:hover { border-color: #2d7a2d; box-shadow: 0 4px 12px rgba(45,122,45,.12); }
.machine-img-wrap { width: 100%; height: 120px; background: #f9fafb; display: flex; align-items: center; justify-content: center; border-bottom: 1px solid #f3f4f6; overflow: hidden; }
.machine-img-wrap img { width: 100%; height: 100%; object-fit: cover; }
.machine-img-wrap .no-img { font-size: 36px; color: #d1d5db; }
.machine-info { padding: 10px 12px; }
.machine-name { font-size: 13px; font-weight: 700; color: #1f2937; margin-bottom: 4px; }
.machine-type-badge { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 11px; font-weight: 600; background: #dcfce7; color: #15803d; margin-bottom: 6px; }
.machine-meta { font-size: 12px; color: #6b7280; display: flex; flex-direction: column; gap: 3px; }
.machine-meta span { display: flex; align-items: center; gap: 5px; }
.machine-meta i { color: #2d7a2d; width: 12px; }
.m-badge { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 11px; font-weight: 600; }
.m-badge.available   { background: #d1fae5; color: #065f46; }
.m-badge.unavailable { background: #fee2e2; color: #991b1b; }

/* ── Farm lots ── */
.lot-item { display: flex; align-items: center; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f3f4f6; gap: 12px; }
.lot-item:last-child { border-bottom: none; }
.lot-left { display: flex; align-items: flex-start; gap: 10px; }
.lot-left > i { color: #2d7a2d; font-size: 15px; margin-top: 2px; }
.lot-name { font-size: 14px; font-weight: 600; color: #1f2937; }
.lot-meta { font-size: 12px; color: #6b7280; margin-top: 2px; }
.lot-right { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
.lot-status { padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
.lot-status.active   { background: #d1fae5; color: #065f46; }
.lot-status.inactive { background: #fee2e2; color: #991b1b; }
.btn-deactivate { padding: 5px 12px; background: #fff; color: #dc2626; border: 1.5px solid #fca5a5; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; transition: background 0.15s; white-space: nowrap; }
.btn-deactivate:hover { background: #fee2e2; }

/* ── Empty state ── */
.empty-state { text-align: center; padding: 36px 20px; color: #9ca3af; font-size: 14px; }
.empty-state i { font-size: 38px; margin-bottom: 10px; display: block; opacity: .3; }

/* ════════════════════════════════
   MODALS
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
    width: 92%; max-width: 480px;
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

.modal-field label { display: block; font-size: 12px; color: #6b7280; margin-bottom: 4px; }
.modal-field input {
    width: 100%;
    border: 1.5px solid #e5e7eb;
    border-radius: 8px;
    padding: 8px 10px;
    font-size: 13px; color: #1f2937;
    font-family: inherit;
    transition: border-color 0.2s;
}
.modal-field input:focus { outline: none; border-color: #2d6a2d; box-shadow: 0 0 0 3px rgba(45,106,45,0.1); }
.modal-field input:disabled { background: #f3f4f6; color: #9ca3af; cursor: not-allowed; }

.modal-actions { display: flex; gap: 10px; margin-top: 1.1rem; }
.modal-actions button {
    flex: 1; padding: 10px; border-radius: 8px;
    font-size: 13px; font-weight: 600; cursor: pointer; border: none;
    transition: opacity 0.15s, transform 0.15s;
}
.modal-actions button:hover { opacity: 0.88; transform: translateY(-1px); }
.btn-cancel-modal { background: #f3f4f6; color: #374151; border: 1px solid #e5e7eb !important; }
.btn-save-modal   { background: linear-gradient(135deg,#2d6a2d,#1a4a1a); color: #fff; }

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
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 1rem; font-size: 26px;
}
.confirm-icon-wrap.warn   { background: #fef3c7; color: #d97706; }
.confirm-icon-wrap.danger { background: #fee2e2; color: #dc2626; }
.confirm-box h3 { font-size: 17px; font-weight: 600; margin-bottom: 6px; color: #1f2937; }
.confirm-box p  { font-size: 13px; color: #6b7280; margin-bottom: 1.5rem; }
.confirm-actions { display: flex; gap: 10px; }
.confirm-actions button {
    flex: 1; padding: 10px; border-radius: 8px;
    font-size: 13px; font-weight: 600; cursor: pointer; border: none;
    transition: opacity 0.15s;
}
.confirm-actions button:hover { opacity: 0.88; }
.btn-no        { background: #f3f4f6; color: #374151; border: 1px solid #e5e7eb !important; }
.btn-yes-green { background: linear-gradient(135deg,#2d6a2d,#1a4a1a); color: #fff; }
.btn-yes-red   { background: linear-gradient(135deg,#dc2626,#991b1b); color: #fff; }

/* ── Responsive ── */
@media (max-width: 600px) {
    .main-scroll-container { top: 70px; }
    .hero-info-grid { grid-template-columns: 1fr; }
    .modal-grid { grid-template-columns: 1fr; }
    .modal-grid .full { grid-column: 1; }
    .machines-grid { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 420px) {
    .machines-grid { grid-template-columns: 1fr; }
}
</style>
</head>
<body>

<div class="main-scroll-container">
<div class="container">

    <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- ═══════════════════════════════
         PROFILE HERO CARD
    ═══════════════════════════════ -->
    <div class="profile-hero">
        <div class="avatar-circle">
            <i class="fas fa-hard-hat"></i>
        </div>
        <h2><?= htmlspecialchars($operator['name']) ?></h2>
        <p class="hero-email"><?= htmlspecialchars($operator['email']) ?></p>
        <span class="hero-badge"><i class="fas fa-id-badge"></i> Machine Operator</span>

        <div class="hero-info-grid">
            <div class="hero-info-item">
                <div class="lbl"><i class="fas fa-phone"></i> Phone number</div>
                <div class="val" id="hero-phone"><?= htmlspecialchars($operator['phone'] ?: '—') ?></div>
            </div>
            <div class="hero-info-item">
                <div class="lbl"><i class="fas fa-map"></i> Province</div>
                <div class="val"><?= htmlspecialchars($operator['assoc_province'] ?: '—') ?></div>
            </div>
            <div class="hero-info-item">
                <div class="lbl"><i class="fas fa-city"></i> Municipality</div>
                <div class="val" id="hero-municipality"><?= htmlspecialchars($operator['municipality'] ?: '—') ?></div>
            </div>
            <div class="hero-info-item">
                <div class="lbl"><i class="fas fa-map-pin"></i> Barangay</div>
                <div class="val" id="hero-barangay"><?= htmlspecialchars($operator['barangay'] ?: '—') ?></div>
            </div>
            <div class="hero-info-item full">
                <div class="lbl"><i class="fas fa-building"></i> Association</div>
                <div class="val"><?= htmlspecialchars($operator['association_name']) ?></div>
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
                <i class="fas fa-key"></i> Update password
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
        <p class="modal-sub">You can update your phone number, municipality, and barangay.</p>
        <div class="modal-grid">
            <div class="modal-field full">
                <label>Full name (read-only)</label>
                <input type="text" value="<?= htmlspecialchars($operator['name']) ?>" disabled />
            </div>
            <div class="modal-field full">
                <label>Email (read-only)</label>
                <input type="email" value="<?= htmlspecialchars($operator['email']) ?>" disabled />
            </div>
            <div class="modal-field full">
                <label>Phone number</label>
                <input type="tel" id="edit-phone" value="<?= htmlspecialchars($operator['phone'] ?? '') ?>" placeholder="Enter phone number" />
            </div>
            <div class="modal-field">
                <label>Municipality</label>
                <input type="text" id="edit-municipality" value="<?= htmlspecialchars($operator['municipality'] ?? '') ?>" placeholder="Enter municipality" />
            </div>
            <div class="modal-field">
                <label>Barangay</label>
                <input type="text" id="edit-barangay" value="<?= htmlspecialchars($operator['barangay'] ?? '') ?>" placeholder="Enter barangay" />
            </div>
        </div>
        <div class="modal-actions">
            <button class="btn-save-modal"   onclick="confirmSaveProfile()">Save changes</button>
            <button class="btn-cancel-modal" onclick="closeEditModal()">Cancel</button>
            
        </div>
    </div>
</div>

<!-- ── Confirm Save Profile ── -->
<div class="modal-overlay" id="confirmSaveModal">
    <div class="confirm-box">
        <div class="confirm-icon-wrap warn"><i class="fas fa-question"></i></div>
        <h3>Save changes?</h3>
        <p>Are you sure you want to update your profile information?</p>
        <div class="confirm-actions">
            <button class="btn-no"        onclick="closeConfirmSave()">No, cancel</button>
            <button class="btn-yes-green" onclick="submitProfile()">Yes, save</button>
        </div>
    </div>
</div>

<!-- ── Confirm Update Password ── -->
<div class="modal-overlay" id="confirmPwModal">
    <div class="confirm-box">
        <div class="confirm-icon-wrap warn"><i class="fas fa-key"></i></div>
        <h3>Update password?</h3>
        <p>Are you sure you want to change your password?</p>
        <div class="confirm-actions">
            <button class="btn-no"        onclick="closeConfirmPw()">No, cancel</button>
            <button class="btn-yes-green" onclick="submitPassword()">Yes, update</button>
        </div>
    </div>
</div>

<!-- ── Confirm Deactivate Lot ── -->
<div class="modal-overlay" id="confirmDeactivateModal">
    <div class="confirm-box">
        <div class="confirm-icon-wrap danger"><i class="fas fa-ban"></i></div>
        <h3>Deactivate farm lot?</h3>
        <p id="deactivate-msg">Are you sure you want to deactivate this lot? This cannot be undone.</p>
        <div class="confirm-actions">
            <button class="btn-no"      onclick="closeDeactivateModal()">No, cancel</button>
            <button class="btn-yes-red" onclick="submitDeactivateLot()">Yes, deactivate</button>
        </div>
    </div>
</div>

<!-- Hidden forms -->
<form id="realProfileForm" method="POST" action="" style="display:none;">
    <input type="hidden" name="action"        value="update_profile">
    <input type="hidden" name="phone"         id="f-phone">
    <input type="hidden" name="municipality"  id="f-municipality">
    <input type="hidden" name="barangay"      id="f-barangay">
</form>

<form id="realPwForm" method="POST" action="" style="display:none;">
    <input type="hidden" name="action"           value="change_password">
    <input type="hidden" name="new_password"     id="f-pw1">
    <input type="hidden" name="confirm_password" id="f-pw2">
</form>

<form id="realDeactivateForm" method="POST" action="" style="display:none;">
    <input type="hidden" name="action"  value="deactivate_lot">
    <input type="hidden" name="lot_id"  id="f-lot-id">
</form>

<script>
/* ── Edit profile modal ── */
function openEditModal()    { document.getElementById('editModal').classList.add('open'); }
function closeEditModal()   { document.getElementById('editModal').classList.remove('open'); }
function closeConfirmSave() { document.getElementById('confirmSaveModal').classList.remove('open'); }
function closeConfirmPw()   { document.getElementById('confirmPwModal').classList.remove('open'); }

function confirmSaveProfile() {
    closeEditModal();
    document.getElementById('confirmSaveModal').classList.add('open');
}

function submitProfile() {
    document.getElementById('f-phone').value        = document.getElementById('edit-phone').value;
    document.getElementById('f-municipality').value = document.getElementById('edit-municipality').value;
    document.getElementById('f-barangay').value     = document.getElementById('edit-barangay').value;
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

/* ── Deactivate lot ── */
let _pendingLotId = null;
function confirmDeactivateLot(id, name) {
    _pendingLotId = id;
    document.getElementById('deactivate-msg').textContent =
        'Are you sure you want to deactivate "' + name + '"? This cannot be undone.';
    document.getElementById('confirmDeactivateModal').classList.add('open');
}
function closeDeactivateModal() { document.getElementById('confirmDeactivateModal').classList.remove('open'); }
function submitDeactivateLot() {
    document.getElementById('f-lot-id').value = _pendingLotId;
    document.getElementById('realDeactivateForm').submit();
}

/* ── Toggle password visibility ── */
function togglePw(id, btn) {
    const inp  = document.getElementById(id);
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
['editModal','confirmSaveModal','confirmPwModal','confirmDeactivateModal'].forEach(id => {
    document.getElementById(id).addEventListener('click', function(e) {
        if (e.target === this) this.classList.remove('open');
    });
});
</script>

</body>
</html>