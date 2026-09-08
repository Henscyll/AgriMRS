<?php
session_start();
require_once '../includes/db_connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'farmer') {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

$farmer_query = "SELECT id FROM farmers WHERE user_id = ?";
$farmer_stmt = $conn->prepare($farmer_query);
$farmer_stmt->bind_param("i", $user_id);
$farmer_stmt->execute();
$farmer_result = $farmer_stmt->get_result();

if ($farmer_result->num_rows === 0) {
    die("Farmer profile not found");
}

$farmer_data = $farmer_result->fetch_assoc();
$farmer_id = $farmer_data['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'update_profile') {
            $name = trim($_POST['name']);
            $email = trim($_POST['email']);
            $phone = trim($_POST['phone']);
            $province = trim($_POST['province']);
            $municipality = trim($_POST['municipality']);
            $barangay = trim($_POST['barangay']);

            if (empty($name) || empty($email) || empty($phone)) {
                $error_message = "Name, email, and phone are required.";
            } else {
                $update_user = $conn->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
                $update_user->bind_param("ssi", $name, $email, $user_id);
                $update_farmer = $conn->prepare("UPDATE farmers SET name = ?, email = ?, phone = ?, province = ?, municipality = ?, barangay = ? WHERE id = ?");
                $update_farmer->bind_param("ssssssi", $name, $email, $phone, $province, $municipality, $barangay, $farmer_id);
                if ($update_user->execute() && $update_farmer->execute()) {
                    $success_message = "Profile updated successfully!";
                } else {
                    $error_message = "Error updating profile.";
                }
            }
        } elseif ($_POST['action'] === 'change_password') {
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];

            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                $error_message = "All password fields are required.";
            } elseif ($new_password !== $confirm_password) {
                $error_message = "New passwords do not match.";
            } elseif (strlen($new_password) < 6) {
                $error_message = "Password must be at least 6 characters.";
            } else {
                $verify_stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
                $verify_stmt->bind_param("i", $user_id);
                $verify_stmt->execute();
                $user_data = $verify_stmt->get_result()->fetch_assoc();
                if (password_verify($current_password, $user_data['password'])) {
                    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                    $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $update_stmt->bind_param("si", $hashed, $user_id);
                    if ($update_stmt->execute()) {
                        $success_message = "Password changed successfully!";
                    } else {
                        $error_message = "Error changing password.";
                    }
                } else {
                    $error_message = "Current password is incorrect.";
                }
            }
        } elseif ($_POST['action'] === 'add_lot') {
            $lot_number = trim($_POST['lot_number']);
            $farm_size = floatval($_POST['farm_size']);
            $farm_location = trim($_POST['farm_location']);
            $lot_province = trim($_POST['lot_province']);
            $lot_municipality = trim($_POST['lot_municipality']);
            $lot_barangay = trim($_POST['lot_barangay']);

            if (empty($lot_number) || $farm_size <= 0 || empty($farm_location)) {
                $error_message = "Lot number, size, and location are required.";
            } else {
                $insert_lot = $conn->prepare("INSERT INTO farmer_lots (farmer_id, lot_number, farm_size, farm_location, province, municipality, barangay, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Active')");
                $insert_lot->bind_param("isdssss", $farmer_id, $lot_number, $farm_size, $farm_location, $lot_province, $lot_municipality, $lot_barangay);
                if ($insert_lot->execute()) {
                    $success_message = "Lot added successfully!";
                } else {
                    $error_message = "Error adding lot.";
                }
            }
        } elseif ($_POST['action'] === 'delete_lot') {
            $lot_id = intval($_POST['lot_id']);
            $delete_lot = $conn->prepare("DELETE FROM farmer_lots WHERE id = ? AND farmer_id = ?");
            $delete_lot->bind_param("ii", $lot_id, $farmer_id);
            if ($delete_lot->execute()) {
                $success_message = "Lot removed successfully!";
            } else {
                $error_message = "Error removing lot.";
            }
        }
    }
}

$query = "SELECT u.id, u.name, u.email, u.created_at,
                 f.phone, f.province, f.municipality, f.barangay, f.farm_size, f.status
          FROM users u
          INNER JOIN farmers f ON u.id = f.user_id
          WHERE u.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$farmer = $stmt->get_result()->fetch_assoc();

$lots_query = "SELECT * FROM farmer_lots WHERE farmer_id = ? ORDER BY created_at DESC";
$lots_stmt = $conn->prepare($lots_query);
$lots_stmt->bind_param("i", $farmer_id);
$lots_stmt->execute();
$lots = $lots_stmt->get_result();
$lots_rows = [];
while ($l = $lots->fetch_assoc()) $lots_rows[] = $l;
$lots_count = count($lots_rows);

$stats_query = "SELECT COUNT(*) as total_bookings,
                    COUNT(CASE WHEN status = 'Completed' THEN 1 END) as completed,
                    COUNT(CASE WHEN status = 'Pending' THEN 1 END) as pending
                FROM bookings WHERE farmer_id = ?";
$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param("i", $farmer_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

include('farmers_header.php');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account - Farmer</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow: hidden;
        }

        .main-scroll-container {
            position: fixed;
            top: 120px; left: 0; right: 0; bottom: 0;
            overflow-y: scroll;
            overflow-x: hidden;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 15px 15px 60px 15px;
        }

        /* ── Alerts ── */
        .alert {
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.3s ease;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .alert-success { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
        .alert-error   { background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444; }

        /* ── Page header — transparent, centered ── */
        .page-header {
            background: transparent;
            padding: 10px 20px 14px;
            border-radius: 8px;
            box-shadow: none;
            margin-bottom: 15px;
            text-align: center;
        }
        .page-title {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .page-title i {
            width: 38px; height: 38px;
            background: linear-gradient(135deg, #16a34a, #15803d);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            color: white; font-size: 18px;
        }
        .page-title h1 {
            font-size: 22px;
            color: #ffffff;
            text-shadow: 0 2px 6px rgba(0,0,0,0.45);
            font-weight: 700;
        }

        /* ── Stats ── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 15px;
        }
        .stat-card {
            background: white;
            padding: 16px 10px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.12);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            gap: 6px;
            transition: transform 0.2s;
            border-top: 4px solid #16a34a;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .stat-card i { font-size: 22px; color: #16a34a; }
        .stat-info h3 { font-size: 22px; color: #1f2937; font-weight: 700; line-height: 1.2; }
        .stat-info p  { font-size: 11px; color: #6b7280; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px; line-height: 1.3; }

        /* ── Tab navigation ── */
        .tab-navigation {
            background: white;
            padding: 5px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            margin-bottom: 15px;
            display: flex;
            gap: 5px;
        }
        .tab-btn {
            flex: 1; padding: 10px 15px;
            background: transparent; border: none; border-radius: 6px;
            cursor: pointer; font-weight: 600; font-size: 13px;
            color: #6b7280; transition: all 0.3s;
            display: flex; align-items: center; justify-content: center; gap: 6px;
        }
        .tab-btn:hover { background: #f3f4f6; }
        .tab-btn.active { background: linear-gradient(135deg, #16a34a, #15803d); color: white; }

        .tab-content { display: none; }
        .tab-content.active { display: block; }

        /* ── Cards ── */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }
        .card-header {
            display: flex; align-items: center; gap: 10px;
            margin-bottom: 15px; padding-bottom: 12px;
            border-bottom: 2px solid #f3f4f6;
        }
        .card-header i {
            width: 32px; height: 32px;
            background: linear-gradient(135deg, #16a34a, #15803d);
            border-radius: 6px;
            display: flex; align-items: center; justify-content: center;
            color: white; font-size: 14px;
        }
        .card-header h2 { font-size: 16px; color: #1f2937; }

        /* ── Forms ── */
        .form-group { margin-bottom: 15px; }
        .form-group label {
            display: block; margin-bottom: 5px;
            font-weight: 600; font-size: 13px; color: #374151;
        }
        .form-group input,
        .form-group select {
            width: 100%; padding: 10px 12px;
            border: 2px solid #e5e7eb; border-radius: 6px;
            font-size: 13px; transition: all 0.3s;
        }
        .form-group input:focus,
        .form-group select:focus {
            outline: none; border-color: #16a34a;
            box-shadow: 0 0 0 4px rgba(22,163,74,0.1);
        }
        .password-wrapper { position: relative; }
        .password-toggle {
            position: absolute; right: 12px; top: 50%;
            transform: translateY(-50%);
            background: none; border: none; cursor: pointer;
            color: #6b7280; font-size: 18px;
        }

        /* ── Buttons ── */
        .btn {
            padding: 10px 20px; border: none; border-radius: 6px;
            font-size: 13px; font-weight: 600; cursor: pointer;
            transition: all 0.3s;
            display: inline-flex; align-items: center; gap: 6px;
        }
        .btn-primary { background: linear-gradient(135deg, #2d7a2d, #15803d); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(22,163,74,0.3); }
        .btn-secondary { background: white; color: #6b7280; border: 2px solid #e5e7eb; }
        .btn-danger { background: #2d7a2d; color: white; }
        .btn-danger:hover { background: #15803d; }

        /* ── Lots section ── */
        .lots-action-bar {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin-top: 18px;
            flex-wrap: wrap;
        }
        .lots-action-bar .btn {
            padding: 10px 22px;
            font-size: 14px;
        }

        .lot-item {
            background: #f9fafb;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 12px;
            position: relative;
        }
        .lot-item-header {
            display: flex; justify-content: space-between; align-items: start;
            margin-bottom: 12px;
        }
        .lot-number { font-size: 15px; font-weight: 700; color: #16a34a; }
        .lot-info { display: grid; grid-template-columns: repeat(2,1fr); gap: 10px; }
        .info-item { display: flex; flex-direction: column; gap: 3px; }
        .info-label { font-size: 10px; color: #6b7280; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px; }
        .info-value { font-size: 13px; color: #1f2937; font-weight: 600; }

        .btn-remove-lot {
            background: #fee2e2; color: #dc2626;
            border: 2px solid #fecaca;
            padding: 6px 12px; border-radius: 5px;
            cursor: pointer; font-size: 11px; font-weight: 600;
            transition: all 0.2s;
        }
        .btn-remove-lot:hover { background: #dc2626; color: white; border-color: #dc2626; }

        .empty-lots { text-align: center; padding: 30px 20px; color: #6b7280; }
        .empty-lots i { font-size: 40px; color: #d1d5db; margin-bottom: 12px; display: block; }
        .empty-lots h3 { font-size: 16px; margin-bottom: 5px; }
        .empty-lots p  { font-size: 13px; }

        /* Add lot form — transparent background, dashed border */
        .add-lot-form {
            background: transparent;
            border: 2px dashed #16a34a;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .add-lot-form h3 { font-size: 15px; margin-bottom: 12px; color: #065f46; }

        /* Selected lot highlight */
        .lot-item.selected { border-color: #16a34a; background: #f0fdf4; }
        .lot-item { cursor: pointer; transition: border-color 0.2s, background 0.2s; }
        .lot-item:hover { border-color: #86efac; }

        /* ══════════════════════════════
           MODAL — only new CSS added
        ══════════════════════════════ */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.55);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .modal-overlay.open { display: flex; }

        .modal-box {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 560px;
            max-height: 90vh;
            overflow-y: auto;
            animation: modalIn 0.25s ease;
        }
        @keyframes modalIn {
            from { opacity: 0; transform: translateY(-18px) scale(0.97); }
            to   { opacity: 1; transform: translateY(0)    scale(1);    }
        }
        .modal-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 18px 20px 14px;
            border-bottom: 2px solid #f3f4f6;
        }
        .modal-header-left { display: flex; align-items: center; gap: 10px; }
        .modal-header-left i {
            width: 32px; height: 32px;
            background: linear-gradient(135deg, #16a34a, #15803d);
            border-radius: 6px;
            display: flex; align-items: center; justify-content: center;
            color: white; font-size: 14px;
        }
        .modal-header h2 { font-size: 16px; color: #1f2937; }
        .modal-close {
            background: none; border: none; cursor: pointer;
            font-size: 22px; color: #6b7280;
            padding: 4px 8px; border-radius: 4px; transition: background 0.2s;
            line-height: 1;
        }
        .modal-close:hover { background: #f3f4f6; color: #1f2937; }
        .modal-body { padding: 20px; }

        /* View detail rows */
        .view-row {
            display: flex; flex-direction: column; gap: 3px;
            padding: 11px 0;
            border-bottom: 1px solid #f3f4f6;
        }
        .view-row:last-child { border-bottom: none; padding-bottom: 0; }
        .view-row-label {
            font-size: 10px; color: #6b7280; font-weight: 600;
            text-transform: uppercase; letter-spacing: 0.4px;
        }
        .view-row-value { font-size: 14px; color: #1f2937; font-weight: 600; }
        .status-badge {
            display: inline-block; padding: 3px 12px;
            border-radius: 20px; font-size: 12px; font-weight: 600;
            background: #d1fae5; color: #065f46;
        }

        /* ── Responsive ── */
        @media (max-width: 768px) {
            .content-grid { grid-template-columns: 1fr; }
            .lot-info { grid-template-columns: 1fr; }
            .tab-navigation { flex-direction: column; }
            .stats-grid { gap: 8px; }
            .stat-card { padding: 14px 8px; border-radius: 10px; }
            .stat-info h3 { font-size: 20px; }
            .stat-info p  { font-size: 10px; }
            .lots-action-bar { gap: 8px; }
            .lots-action-bar .btn { flex: 1; justify-content: center; }
            .modal-box { border-radius: 10px; }
        }
    </style>
</head>
<body>

<!-- ══════════════════════════════════════
     ADD LOT MODAL
══════════════════════════════════════ -->
<div class="modal-overlay" id="addLotModal">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-header-left">
                <i class="fas fa-plus-circle"></i>
                <h2>Add New Farm Lot</h2>
            </div>
            <button class="modal-close" onclick="closeModal('addLotModal')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_lot">
                <div class="content-grid">
                    <div class="form-group">
                        <label>Lot Number</label>
                        <input type="text" name="lot_number" placeholder="e.g., LOT-001" required>
                    </div>
                    <div class="form-group">
                        <label>Farm Size (Hectares)</label>
                        <input type="number" step="0.01" name="farm_size" placeholder="0.00" required>
                    </div>
                    <div class="form-group" style="grid-column:1/-1;">
                        <label>Farm Location</label>
                        <input type="text" name="farm_location" placeholder="Specific location" required>
                    </div>
                    <div class="form-group">
                        <label>Province</label>
                        <input type="text" name="lot_province" placeholder="Province">
                    </div>
                    <div class="form-group">
                        <label>Municipality</label>
                        <input type="text" name="lot_municipality" placeholder="Municipality">
                    </div>
                    <div class="form-group" style="grid-column:1/-1;">
                        <label>Barangay</label>
                        <input type="text" name="lot_barangay" placeholder="Barangay">
                    </div>
                </div>
                <div style="display:flex;gap:10px;margin-top:10px;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Lot</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addLotModal')">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════
     VIEW LOT MODAL
══════════════════════════════════════ -->
<div class="modal-overlay" id="viewLotModal">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-header-left">
                <i class="fas fa-map-pin"></i>
                <h2 id="viewModalTitle">Lot Details</h2>
            </div>
            <button class="modal-close" onclick="closeModal('viewLotModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="view-row">
                <span class="view-row-label">Lot Number</span>
                <span class="view-row-value" id="vLotNumber">—</span>
            </div>
            <div class="view-row">
                <span class="view-row-label">Farm Size</span>
                <span class="view-row-value" id="vFarmSize">—</span>
            </div>
            <div class="view-row">
                <span class="view-row-label">Status</span>
                <span class="view-row-value"><span class="status-badge" id="vStatus">—</span></span>
            </div>
            <div class="view-row">
                <span class="view-row-label">Farm Location</span>
                <span class="view-row-value" id="vFarmLocation">—</span>
            </div>
            <div class="view-row">
                <span class="view-row-label">Province</span>
                <span class="view-row-value" id="vProvince">—</span>
            </div>
            <div class="view-row">
                <span class="view-row-label">Municipality</span>
                <span class="view-row-value" id="vMunicipality">—</span>
            </div>
            <div class="view-row">
                <span class="view-row-label">Barangay</span>
                <span class="view-row-value" id="vBarangay">—</span>
            </div>
        </div>
    </div>
</div>

<div class="main-scroll-container">
<div class="container">

    <?php if ($success_message): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i><?= htmlspecialchars($success_message) ?></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <!-- ── Page Header ── -->
    <div class="page-header">
        <div class="page-title">
            <h1>My Account</h1>
        </div>
    </div>

    <!-- ── Stats ── -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-info">
                <h3><?= $lots_count ?></h3>
                <p>Farm Lots</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-info">
                <h3><?= number_format($farmer['farm_size'], 2) ?></h3>
                <p>Total Farm (ha)</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-info">
                <h3><?= $stats['total_bookings'] ?></h3>
                <p>Total Bookings</p>
            </div>
        </div>
    </div>

    <!-- ── Tab Navigation ── -->
    <div class="tab-navigation">
        <button class="tab-btn active" onclick="switchTab('profile', this)">
            <i class="fas fa-user"></i> Profile
        </button>
        <button class="tab-btn" onclick="switchTab('lots', this)">
            <i class="fas fa-map"></i> My Lots
        </button>
        <button class="tab-btn" onclick="switchTab('password', this)">
            <i class="fas fa-lock"></i> Security
        </button>
    </div>

    <!-- ══════════════════════════════════════
         PROFILE TAB
    ══════════════════════════════════════ -->
    <div id="profile-tab" class="tab-content active">
        <div class="content-grid">
            <!-- Personal Info -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-user"></i>
                    <h2>Personal Information</h2>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Full Name</label>
                        <input type="text" name="name" value="<?= htmlspecialchars($farmer['name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Email</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($farmer['email']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-phone"></i> Phone Number</label>
                        <input type="tel" name="phone" value="<?= htmlspecialchars($farmer['phone'] ?? '') ?>" required>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                </form>
            </div>

            <!-- Location -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-map-marker-alt"></i>
                    <h2>Location Information</h2>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_profile">
                    <input type="hidden" name="name"  value="<?= htmlspecialchars($farmer['name']) ?>">
                    <input type="hidden" name="email" value="<?= htmlspecialchars($farmer['email']) ?>">
                    <input type="hidden" name="phone" value="<?= htmlspecialchars($farmer['phone'] ?? '') ?>">
                    <div class="form-group">
                        <label><i class="fas fa-map"></i> Province</label>
                        <input type="text" name="province" value="<?= htmlspecialchars($farmer['province'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-city"></i> Municipality</label>
                        <input type="text" name="municipality" value="<?= htmlspecialchars($farmer['municipality'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-map-pin"></i> Barangay</label>
                        <input type="text" name="barangay" value="<?= htmlspecialchars($farmer['barangay'] ?? '') ?>">
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Location</button>
                </form>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════
         LOTS TAB
    ══════════════════════════════════════ -->
    <div id="lots-tab" class="tab-content">

        <!-- Lots list card -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-map-marked-alt"></i>
                <h2>My Farm Lots</h2>
            </div>

            <?php if ($lots_count > 0): ?>
                <?php foreach ($lots_rows as $lot): ?>
                <div class="lot-item"
                     id="lot-<?= $lot['id'] ?>"
                     onclick="selectLot(<?= $lot['id'] ?>)"
                     data-lot-number="<?= htmlspecialchars($lot['lot_number'], ENT_QUOTES) ?>"
                     data-farm-size="<?= number_format($lot['farm_size'],2) ?>"
                     data-status="<?= htmlspecialchars($lot['status'], ENT_QUOTES) ?>"
                     data-farm-location="<?= htmlspecialchars($lot['farm_location'], ENT_QUOTES) ?>"
                     data-province="<?= htmlspecialchars($lot['province'] ?? '', ENT_QUOTES) ?>"
                     data-municipality="<?= htmlspecialchars($lot['municipality'] ?? '', ENT_QUOTES) ?>"
                     data-barangay="<?= htmlspecialchars($lot['barangay'] ?? '', ENT_QUOTES) ?>">
                    <div class="lot-item-header">
                        <div class="lot-number">
                            <i class="fas fa-map-pin"></i>
                            Lot <?= htmlspecialchars($lot['lot_number']) ?>
                        </div>
                        <span style="font-size:11px;color:#6b7280;"><?= htmlspecialchars($lot['status']) ?></span>
                    </div>
                    <div class="lot-info">
                        <div class="info-item">
                            <span class="info-label">Farm Size</span>
                            <span class="info-value"><?= number_format($lot['farm_size'],2) ?> ha</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Status</span>
                            <span class="info-value"><?= htmlspecialchars($lot['status']) ?></span>
                        </div>
                        <div class="info-item" style="grid-column:1/-1;">
                            <span class="info-label">Location</span>
                            <span class="info-value"><?= htmlspecialchars($lot['farm_location']) ?></span>
                        </div>
                        <?php if ($lot['province'] || $lot['municipality'] || $lot['barangay']): ?>
                        <div class="info-item" style="grid-column:1/-1;">
                            <span class="info-label">Address</span>
                            <span class="info-value">
                                <?= htmlspecialchars($lot['barangay'] ?? '') ?>
                                <?= $lot['municipality'] ? ', '.htmlspecialchars($lot['municipality']) : '' ?>
                                <?= $lot['province']    ? ', '.htmlspecialchars($lot['province'])    : '' ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-lots">
                    <i class="fas fa-map-marked-alt"></i>
                    <h3>No Farm Lots Yet</h3>
                    <p>Click "Add New Lot" below to register your first farm lot.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- ── Action buttons — outside & below the card, transparent bg ── -->
        <div class="lots-action-bar">
            <button class="btn btn-primary" onclick="openModal('addLotModal')">
                 Add
            </button>
            <button id="btnViewLot" class="btn btn-primary" disabled onclick="openViewModal()" style="background:linear-gradient(135deg,#2d7a2d,#15803d);">
                 View
            </button>
            <form id="deleteLotForm" method="POST" action="" style="display:inline;"
                  onsubmit="return confirm('Are you sure you want to remove this lot?');">
                <input type="hidden" name="action" value="delete_lot">
                <input type="hidden" name="lot_id" id="selectedLotId" value="">
                <button type="submit" id="btnRemoveLot" class="btn btn-danger" disabled>
                 Remove
                </button>
            </form>
        </div>

    </div><!-- end lots-tab -->

    <!-- ══════════════════════════════════════
         PASSWORD TAB
    ══════════════════════════════════════ -->
    <div id="password-tab" class="tab-content">
        <div class="card" style="max-width:600px;margin:0 auto;">
            <div class="card-header">
                <i class="fas fa-lock"></i>
                <h2>Change Password</h2>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="change_password">
                <div class="form-group">
                    <label><i class="fas fa-key"></i> Current Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="current_password" name="current_password" required>
                        <button type="button" class="password-toggle" onclick="togglePassword('current_password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> New Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="new_password" name="new_password" minlength="6" required>
                        <button type="button" class="password-toggle" onclick="togglePassword('new_password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Confirm New Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="confirm_password" name="confirm_password" minlength="6" required>
                        <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-key"></i> Change Password</button>
            </form>
        </div>
    </div>

    <?php include('../footer.php'); ?>
</div>
</div>

<script>
/* ── Tab switching ── */
function switchTab(tabName, btn) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById(tabName + '-tab').classList.add('active');
    btn.classList.add('active');
}

/* ── Password toggle ── */
function togglePassword(fieldId) {
    const field  = document.getElementById(fieldId);
    const icon   = field.parentElement.querySelector('.password-toggle i');
    field.type   = field.type === 'password' ? 'text' : 'password';
    icon.classList.toggle('fa-eye');
    icon.classList.toggle('fa-eye-slash');
}

/* ── Modal helpers ── */
function openModal(id) {
    document.getElementById(id).classList.add('open');
}
function closeModal(id) {
    document.getElementById(id).classList.remove('open');
}
/* Close on backdrop click */
document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) closeModal(this.id);
    });
});
/* Close on Escape */
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.open').forEach(function(m) {
            closeModal(m.id);
        });
    }
});

/* ── Lot row selection ── */
let selectedLotId = null;
let selectedLotEl = null;

function selectLot(id) {
    if (selectedLotId === id) {
        document.getElementById('lot-' + id).classList.remove('selected');
        selectedLotId = null;
        selectedLotEl = null;
        document.getElementById('selectedLotId').value   = '';
        document.getElementById('btnViewLot').disabled   = true;
        document.getElementById('btnRemoveLot').disabled = true;
        return;
    }
    if (selectedLotId) document.getElementById('lot-' + selectedLotId).classList.remove('selected');
    selectedLotId = id;
    selectedLotEl = document.getElementById('lot-' + id);
    selectedLotEl.classList.add('selected');
    document.getElementById('selectedLotId').value   = id;
    document.getElementById('btnViewLot').disabled   = false;
    document.getElementById('btnRemoveLot').disabled = false;
}

/* ── Open View modal — reads data-* from the selected row ── */
function openViewModal() {
    if (!selectedLotEl) return;
    const d = selectedLotEl.dataset;
    document.getElementById('viewModalTitle').textContent = 'Lot ' + d.lotNumber;
    document.getElementById('vLotNumber').textContent     = d.lotNumber;
    document.getElementById('vFarmSize').textContent      = d.farmSize + ' hectares';
    document.getElementById('vStatus').textContent        = d.status;
    document.getElementById('vFarmLocation').textContent  = d.farmLocation  || '—';
    document.getElementById('vProvince').textContent      = d.province      || '—';
    document.getElementById('vMunicipality').textContent  = d.municipality  || '—';
    document.getElementById('vBarangay').textContent      = d.barangay      || '—';
    openModal('viewLotModal');
}
</script>

</body>
</html>