<?php
session_start();
require '../includes/config.php';
include 'farmers_header.php';
include_once '../includes/farmer_auth.php';

$farmer_id = $_SESSION['user_id'];
$message = "";
$message_type = "";

// Get farmer info for default location
$farmer_info = $conn->prepare("SELECT province, municipality, barangay FROM farmers WHERE id = ?");
$farmer_info->bind_param("i", $farmer_id);
$farmer_info->execute();
$farmer_data = $farmer_info->get_result()->fetch_assoc();

/* ✅ Handle Add/Edit Lot */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $lot_number = trim($_POST['lot_number']);
        $farm_location = trim($_POST['farm_location']);
        $farm_size = floatval($_POST['farm_size']);
        $province = trim($_POST['province']);
        $municipality = trim($_POST['municipality']);
        $barangay = trim($_POST['barangay']);
        $status = $_POST['status'] ?? 'Active';
        
        // Validation
        if (empty($lot_number) || empty($farm_location) || $farm_size <= 0) {
            $message = "❌ Please fill in all required fields with valid data.";
            $message_type = "error";
        } else {
            if ($action === 'add') {
                // Check for duplicate lot number
                $check = $conn->prepare("SELECT id FROM farmer_lots WHERE farmer_id = ? AND lot_number = ?");
                $check->bind_param("is", $farmer_id, $lot_number);
                $check->execute();
                $check->store_result();
                
                if ($check->num_rows > 0) {
                    $message = "❌ You already have a lot with this number: " . htmlspecialchars($lot_number);
                    $message_type = "error";
                } else {
                    $stmt = $conn->prepare("
                        INSERT INTO farmer_lots (farmer_id, lot_number, farm_location, farm_size, province, municipality, barangay, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->bind_param("issdssss", $farmer_id, $lot_number, $farm_location, $farm_size, $province, $municipality, $barangay, $status);
                    
                    if ($stmt->execute()) {
                        $message = "✅ Farm lot added successfully!";
                        $message_type = "success";
                    } else {
                        $message = "❌ Error adding lot: " . $stmt->error;
                        $message_type = "error";
                    }
                }
            } elseif ($action === 'edit') {
                $lot_id = intval($_POST['lot_id']);
                
                // Verify ownership
                $verify = $conn->prepare("SELECT id FROM farmer_lots WHERE id = ? AND farmer_id = ?");
                $verify->bind_param("ii", $lot_id, $farmer_id);
                $verify->execute();
                
                if ($verify->get_result()->num_rows === 0) {
                    $message = "❌ Invalid lot selection.";
                    $message_type = "error";
                } else {
                    $stmt = $conn->prepare("
                        UPDATE farmer_lots 
                        SET lot_number = ?, farm_location = ?, farm_size = ?, province = ?, municipality = ?, barangay = ?, status = ?
                        WHERE id = ? AND farmer_id = ?
                    ");
                    $stmt->bind_param("ssdsssiii", $lot_number, $farm_location, $farm_size, $province, $municipality, $barangay, $status, $lot_id, $farmer_id);
                    
                    if ($stmt->execute()) {
                        $message = "✅ Farm lot updated successfully!";
                        $message_type = "success";
                    } else {
                        $message = "❌ Error updating lot: " . $stmt->error;
                        $message_type = "error";
                    }
                }
            }
        }
    } elseif ($action === 'delete') {
        $lot_id = intval($_POST['lot_id']);
        
        // Check if lot has active bookings
        $check_bookings = $conn->prepare("
            SELECT COUNT(*) as count FROM bookings 
            WHERE lot_id = ? AND status IN ('Pending', 'Approved')
        ");
        $check_bookings->bind_param("i", $lot_id);
        $check_bookings->execute();
        $booking_count = $check_bookings->get_result()->fetch_assoc()['count'];
        
        if ($booking_count > 0) {
            $message = "❌ Cannot delete this lot. It has active bookings.";
            $message_type = "error";
        } else {
            // Instead of deleting, set status to Inactive
            $stmt = $conn->prepare("UPDATE farmer_lots SET status = 'Inactive' WHERE id = ? AND farmer_id = ?");
            $stmt->bind_param("ii", $lot_id, $farmer_id);
            
            if ($stmt->execute()) {
                $message = "✅ Farm lot deactivated successfully!";
                $message_type = "success";
            } else {
                $message = "❌ Error deactivating lot: " . $stmt->error;
                $message_type = "error";
            }
        }
    }
}

/* 🔍 Get all lots for this farmer */
$lots_query = $conn->prepare("
    SELECT 
        fl.*,
        COUNT(DISTINCT b.id) as total_bookings,
        COUNT(DISTINCT CASE WHEN b.status = 'Completed' THEN b.id END) as completed_bookings
    FROM farmer_lots fl
    LEFT JOIN bookings b ON fl.id = b.lot_id
    WHERE fl.farmer_id = ?
    GROUP BY fl.id
    ORDER BY fl.status DESC, fl.created_at DESC
");
$lots_query->bind_param("i", $farmer_id);
$lots_query->execute();
$lots = $lots_query->get_result();

// Get statistics
$stats_query = $conn->prepare("
    SELECT 
        COUNT(*) as total_lots,
        SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active_lots,
        SUM(CASE WHEN status = 'Active' THEN farm_size ELSE 0 END) as total_active_area,
        SUM(farm_size) as total_area
    FROM farmer_lots
    WHERE farmer_id = ?
");
$stats_query->bind_param("i", $farmer_id);
$stats_query->execute();
$stats = $stats_query->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html>
<head>
<title>My Farm Lots</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
    --primary-color: #2e7d32;
    --primary-dark: #1b5e20;
    --primary-light: #4caf50;
    --danger-color: #d32f2f;
    --warning-color: #f57c00;
    --info-color: #0288d1;
    --success-color: #388e3c;
    --bg-light: #f5f5f5;
    --border-color: #e0e0e0;
    --text-dark: #212121;
    --text-light: #757575;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: var(--bg-light);
    color: var(--text-dark);
}

.page-content {
    margin-top: 120px;
    padding: 20px;
    max-width: 1400px;
    margin-left: auto;
    margin-right: auto;
}

/* Header */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    flex-wrap: wrap;
    gap: 15px;
}

.page-header h2 {
    font-size: 28px;
    color: var(--primary-dark);
    display: flex;
    align-items: center;
    gap: 10px;
}

.page-header h2 i {
    color: var(--primary-color);
}

/* Alert */
.alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    animation: slideDown 0.3s ease;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.alert i { font-size: 20px; }

.alert.success {
    background: #e8f5e9;
    color: var(--success-color);
    border-left: 4px solid var(--success-color);
}

.alert.error {
    background: #ffebee;
    color: var(--danger-color);
    border-left: 4px solid var(--danger-color);
}

/* Stats Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 15px;
    transition: transform 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: white;
}

.stat-icon.primary { background: linear-gradient(135deg, var(--primary-color), var(--primary-light)); }
.stat-icon.info { background: linear-gradient(135deg, #0288d1, #03a9f4); }
.stat-icon.success { background: linear-gradient(135deg, #388e3c, #66bb6a); }
.stat-icon.warning { background: linear-gradient(135deg, #f57c00, #ff9800); }

.stat-content h3 {
    font-size: 28px;
    font-weight: 700;
    color: var(--text-dark);
    margin-bottom: 4px;
}

.stat-content p {
    font-size: 13px;
    color: var(--text-light);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Buttons */
.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    text-decoration: none;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
    color: white;
    box-shadow: 0 2px 8px rgba(46, 125, 50, 0.3);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(46, 125, 50, 0.4);
}

.btn-secondary {
    background: white;
    color: var(--text-dark);
    border: 2px solid var(--border-color);
}

.btn-secondary:hover {
    background: var(--bg-light);
    border-color: var(--primary-color);
}

.btn-danger {
    background: linear-gradient(135deg, #d32f2f, #f44336);
    color: white;
}

.btn-danger:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(211, 47, 47, 0.4);
}

.btn-sm {
    padding: 6px 12px;
    font-size: 13px;
}

/* Table */
.table-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    overflow: hidden;
}

.table-header {
    padding: 20px;
    background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.table-header h3 {
    font-size: 18px;
    display: flex;
    align-items: center;
    gap: 8px;
}

table {
    width: 100%;
    border-collapse: collapse;
}

thead {
    background: var(--bg-light);
}

thead th {
    padding: 15px;
    text-align: left;
    font-weight: 600;
    font-size: 13px;
    color: var(--text-dark);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid var(--border-color);
}

tbody td {
    padding: 15px;
    border-bottom: 1px solid var(--border-color);
    font-size: 14px;
}

tbody tr:hover {
    background: #f9fafb;
}

tbody tr:last-child td {
    border-bottom: none;
}

.badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.badge-success {
    background: #e8f5e9;
    color: var(--success-color);
}

.badge-warning {
    background: #fff3e0;
    color: var(--warning-color);
}

.lot-number {
    font-weight: 700;
    color: var(--primary-dark);
    font-size: 15px;
}

.action-buttons {
    display: flex;
    gap: 8px;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
}

.empty-state i {
    font-size: 64px;
    color: var(--border-color);
    margin-bottom: 20px;
}

.empty-state h3 {
    color: var(--text-dark);
    margin-bottom: 10px;
}

.empty-state p {
    color: var(--text-light);
    margin-bottom: 20px;
}

/* Modal */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.modal-content {
    background: white;
    margin: 5% auto;
    padding: 0;
    border-radius: 12px;
    width: 90%;
    max-width: 600px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.3);
    animation: slideUp 0.3s ease;
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

.modal-header {
    padding: 20px 25px;
    background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
    color: white;
    border-radius: 12px 12px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    font-size: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.close {
    color: white;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    transition: transform 0.2s ease;
}

.close:hover {
    transform: scale(1.2);
}

.modal-body {
    padding: 25px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
}

.form-group {
    margin-bottom: 15px;
}

.form-group.full-width {
    grid-column: 1 / -1;
}

.form-group label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: var(--text-dark);
    margin-bottom: 6px;
}

.form-group label .required {
    color: var(--danger-color);
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 10px 12px;
    border: 2px solid var(--border-color);
    border-radius: 6px;
    font-size: 14px;
    transition: all 0.3s ease;
    font-family: inherit;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(46, 125, 50, 0.1);
}

.form-group textarea {
    resize: vertical;
    min-height: 80px;
}

.modal-footer {
    padding: 20px 25px;
    background: var(--bg-light);
    border-radius: 0 0 12px 12px;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

/* Responsive */
@media (max-width: 768px) {
    .page-content {
        padding: 15px;
        margin-top: 100px;
    }

    .stats-grid {
        grid-template-columns: 1fr;
    }

    .table-container {
        overflow-x: auto;
    }

    table {
        min-width: 800px;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

    .modal-content {
        width: 95%;
        margin: 10% auto;
    }
}
</style>
</head>

<body>

<div class="page-content">
    <div class="page-header">
        <h2><i class="fas fa-map-marked-alt"></i> My Farm Lots</h2>
        <button class="btn btn-primary" onclick="openAddModal()">
            <i class="fas fa-plus"></i>
            Add New Lot
        </button>
    </div>

    <?php if ($message): ?>
        <div class="alert <?= $message_type ?>">
            <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
            <span><?= $message ?></span>
        </div>
    <?php endif; ?>

    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon primary">
                <i class="fas fa-map-marked-alt"></i>
            </div>
            <div class="stat-content">
                <h3><?= $stats['total_lots'] ?></h3>
                <p>Total Lots</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon success">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-content">
                <h3><?= $stats['active_lots'] ?></h3>
                <p>Active Lots</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon info">
                <i class="fas fa-ruler-combined"></i>
            </div>
            <div class="stat-content">
                <h3><?= number_format($stats['total_active_area'], 2) ?> ha</h3>
                <p>Active Farm Area</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon warning">
                <i class="fas fa-chart-area"></i>
            </div>
            <div class="stat-content">
                <h3><?= number_format($stats['total_area'], 2) ?> ha</h3>
                <p>Total Farm Area</p>
            </div>
        </div>
    </div>

    <!-- Lots Table -->
    <div class="table-container">
        <div class="table-header">
            <h3>
                <i class="fas fa-list"></i>
                Farm Lots List
            </h3>
        </div>

        <?php if ($lots->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Lot Number</th>
                        <th>Location</th>
                        <th>Size (hectares)</th>
                        <th>Status</th>
                        <th>Bookings</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($lot = $lots->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <span class="lot-number">
                                    <i class="fas fa-map-marker-alt" style="color: var(--primary-color);"></i>
                                    <?= htmlspecialchars($lot['lot_number']) ?>
                                </span>
                            </td>
                            <td>
                                <div style="line-height: 1.6;">
                                    <strong><?= htmlspecialchars($lot['farm_location']) ?></strong><br>
                                    <small style="color: var(--text-light);">
                                        <?= htmlspecialchars($lot['barangay']) ?>, 
                                        <?= htmlspecialchars($lot['municipality']) ?>
                                    </small>
                                </div>
                            </td>
                            <td>
                                <strong><?= number_format($lot['farm_size'], 2) ?></strong> ha
                            </td>
                            <td>
                                <span class="badge badge-<?= $lot['status'] === 'Active' ? 'success' : 'warning' ?>">
                                    <?= $lot['status'] ?>
                                </span>
                            </td>
                            <td>
                                <div style="font-size: 13px;">
                                    <i class="fas fa-calendar-alt" style="color: var(--primary-color);"></i>
                                    <?= $lot['total_bookings'] ?> total
                                    <?php if ($lot['completed_bookings'] > 0): ?>
                                        <br>
                                        <small style="color: var(--success-color);">
                                            <i class="fas fa-check"></i>
                                            <?= $lot['completed_bookings'] ?> completed
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-secondary btn-sm" onclick='openEditModal(<?= json_encode($lot) ?>)'>
                                        <i class="fas fa-edit"></i>
                                        Edit
                                    </button>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to deactivate this lot?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="lot_id" value="<?= $lot['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-map-marked-alt"></i>
                <h3>No Farm Lots Yet</h3>
                <p>You haven't added any farm lots. Add your first lot to start booking tractors!</p>
                <button class="btn btn-primary" onclick="openAddModal()">
                    <i class="fas fa-plus"></i>
                    Add Your First Lot
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add/Edit Modal -->
<div id="lotModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">
                <i class="fas fa-plus-circle"></i>
                <span id="modalTitleText">Add New Farm Lot</span>
            </h3>
            <span class="close" onclick="closeModal()">&times;</span>
        </div>
        
        <form method="POST" id="lotForm">
            <div class="modal-body">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="lot_id" id="lotId">

                <div class="form-grid">
                    <div class="form-group">
                        <label>
                            Lot Number <span class="required">*</span>
                        </label>
                        <input type="text" name="lot_number" id="lot_number" required placeholder="e.g., 814-A">
                    </div>

                    <div class="form-group">
                        <label>
                            Farm Size (hectares) <span class="required">*</span>
                        </label>
                        <input type="number" name="farm_size" id="farm_size" step="0.01" min="0.01" required placeholder="e.g., 1.60">
                    </div>

                    <div class="form-group full-width">
                        <label>
                            Farm Location <span class="required">*</span>
                        </label>
                        <input type="text" name="farm_location" id="farm_location" required placeholder="Full address of your farm">
                    </div>

                    <div class="form-group">
                        <label>Province</label>
                        <input type="text" name="province" id="province" value="<?= htmlspecialchars($farmer_data['province'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label>Municipality</label>
                        <input type="text" name="municipality" id="municipality" value="<?= htmlspecialchars($farmer_data['municipality'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label>Barangay</label>
                        <input type="text" name="barangay" id="barangay" value="<?= htmlspecialchars($farmer_data['barangay'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" id="status">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                    Cancel
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    <span id="submitBtnText">Add Lot</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const modal = document.getElementById('lotModal');
const lotForm = document.getElementById('lotForm');

function openAddModal() {
    document.getElementById('formAction').value = 'add';
    document.getElementById('modalTitleText').textContent = 'Add New Farm Lot';
    document.getElementById('submitBtnText').textContent = 'Add Lot';
    lotForm.reset();
    modal.style.display = 'block';
    
    // Set default values from farmer profile
    document.getElementById('province').value = '<?= htmlspecialchars($farmer_data['province'] ?? '') ?>';
    document.getElementById('municipality').value = '<?= htmlspecialchars($farmer_data['municipality'] ?? '') ?>';
    document.getElementById('barangay').value = '<?= htmlspecialchars($farmer_data['barangay'] ?? '') ?>';
}

function openEditModal(lot) {
    document.getElementById('formAction').value = 'edit';
    document.getElementById('lotId').value = lot.id;
    document.getElementById('lot_number').value = lot.lot_number;
    document.getElementById('farm_location').value = lot.farm_location;
    document.getElementById('farm_size').value = lot.farm_size;
    document.getElementById('province').value = lot.province || '';
    document.getElementById('municipality').value = lot.municipality || '';
    document.getElementById('barangay').value = lot.barangay || '';
    document.getElementById('status').value = lot.status;
    
    document.getElementById('modalTitleText').textContent = 'Edit Farm Lot';
    document.getElementById('submitBtnText').textContent = 'Update Lot';
    
    modal.style.display = 'block';
}

function closeModal() {
    modal.style.display = 'none';
    lotForm.reset();
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target == modal) {
        closeModal();
    }
}

// Close modal on Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeModal();
    }
});
</script>

</body>
</html>