<?php
session_start();
require_once '../includes/db_connection.php';
include('dashboard_president.php');

/* ===============================
   AUTH CHECK
================================ */
if (!isset($_SESSION['association_id'])) {
    header("Location: ../login.php");
    exit;
}
$association_id = $_SESSION['association_id'];

/* ===============================
   GET OPERATOR ID
================================ */
if (!isset($_GET['id'])) {
    header("Location: association_operators.php");
    exit;
}
$operator_id = (int)$_GET['id'];

/* ===============================
   FETCH OPERATOR DETAILS
================================ */
$operator_sql = "
    SELECT 
        o.*,
        u.email as user_email,
        u.created_at as account_created
    FROM operators o
    LEFT JOIN users u ON o.user_id = u.id
    WHERE o.id = ? AND o.association_id = ?
";

$stmt = $conn->prepare($operator_sql);
$stmt->bind_param("ii", $operator_id, $association_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: association_operators.php");
    exit;
}

$operator = $result->fetch_assoc();
$stmt->close();

/* ===============================
   FETCH ASSIGNED MACHINES
================================ */
$machines_sql = "
    SELECT 
        m.id,
        m.machine_name,
        m.type,
        m.status,
        m.price_per_hectare,
        mo.assigned_at,
        mo.status as assignment_status
    FROM machine_operators mo
    INNER JOIN machines m ON mo.machine_id = m.id
    WHERE mo.operator_id = ?
    ORDER BY mo.assigned_at DESC
";

$stmt = $conn->prepare($machines_sql);
$stmt->bind_param("i", $operator_id);
$stmt->execute();
$machines_result = $stmt->get_result();

$assigned_machines = [];
while ($row = $machines_result->fetch_assoc()) {
    $assigned_machines[] = $row;
}
$stmt->close();

/* ===============================
   FETCH TRANSACTION HISTORY
================================ */
$transactions_sql = "
    SELECT 
        b.id as booking_id,
        b.booking_date,
        b.status as booking_status,
        b.farm_size,
        b.created_at,
        b.updated_at,
        m.machine_name,
        m.type as machine_type,
        m.price_per_hectare,
        f.name as farmer_name,
        f.phone as farmer_phone,
        fl.lot_number,
        fl.farm_location,
        (b.farm_size * m.price_per_hectare) as total_amount
    FROM bookings b
    INNER JOIN machines m ON b.machine_id = m.id
    INNER JOIN machine_operators mo 
        ON m.id = mo.machine_id 
        AND mo.operator_id = ?
    INNER JOIN farmers f ON b.farmer_id = f.id
    LEFT JOIN farmer_lots fl ON b.lot_id = fl.id
    WHERE mo.status = 'Active'
    ORDER BY b.booking_date DESC, b.created_at DESC
";

$stmt = $conn->prepare($transactions_sql);
$stmt->bind_param("i", $operator_id);
$stmt->execute();
$transactions_result = $stmt->get_result();

$transactions = [];
$total_completed = 0;
$total_revenue = 0;
$total_hectares = 0;

while ($row = $transactions_result->fetch_assoc()) {
    $transactions[] = $row;
    if ($row['booking_status'] === 'Completed') {
        $total_completed++;
        $total_revenue += $row['total_amount'];
        $total_hectares += $row['farm_size'];
    }
}
$stmt->close();
?>

<!DOCTYPE html>
<html>
<head>
<title>Operator Profile - <?= htmlspecialchars($operator['name']) ?></title>

<style>
.main-content {

    max-width: 1400px;
}

.page-header {
    text-align: center;
    color: white;
    margin-bottom: 20px;
}

.back-button {
    display: inline-block;
    margin-bottom: 15px;
    padding: 8px 15px;
    background: #6c757d;
    color: white;
    text-decoration: none;
    border-radius: 4px;
}

.back-button:hover {
    background: #5a6268;
}

.profile-container {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 20px;
    margin-bottom: 20px;
}

.profile-card {
    background: white;
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0,0,0,.1);
}

.profile-card h3 {
    margin: 0 0 20px 0;
    color: #2d7a2d;
    border-bottom: 2px solid #2d7a2d;
    padding-bottom: 10px;
}

.profile-info {
    margin-bottom: 15px;
}

.profile-info label {
    display: block;
    font-weight: bold;
    color: #666;
    margin-bottom: 5px;
    font-size: 13px;
}

.profile-info .value {
    font-size: 15px;
    color: #333;
}

.status-badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: bold;
}

.status-active {
    background: #d4edda;
    color: #155724;
}

.status-inactive {
    background: #f8d7da;
    color: #721c24;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
}

.stat-box {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 6px;
    border-left: 4px solid #2d7a2d;
    text-align: center;
}

.stat-box h4 {
    margin: 0;
    color: #2d7a2d;
    font-size: 24px;
}

.stat-box p {
    margin: 5px 0 0 0;
    color: #666;
    font-size: 13px;
}

.section {
    background: white;
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0,0,0,.1);
    margin-bottom: 20px;
}

.section h3 {
    margin: 0 0 20px 0;
    color: #2d7a2d;
    border-bottom: 2px solid #2d7a2d;
    padding-bottom: 10px;
}

.table-container {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th, td {
    padding: 12px;
    border-bottom: 1px solid #ddd;
    text-align: left;
}

th {
    background: #2d7a2d;
    color: white;
    font-weight: 600;
}

tr:hover {
    background: #f8f9fa;
}

.machine-badge {
    background: #e7f4e7;
    color: #2d572c;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    display: inline-block;
}

.booking-pending {
    background: #fff3cd;
    color: #856404;
}

.booking-approved {
    background: #cce5ff;
    color: #004085;
}

.booking-completed {
    background: #d4edda;
    color: #155724;
}

.booking-cancelled {
    background: #f8d7da;
    color: #721c24;
}

.no-data {
    text-align: center;
    padding: 30px;
    color: #999;
    font-style: italic;
}

.action-buttons {
    margin-top: 20px;
    display: flex;
    justify-content: center;
    gap: 15px;
}

.action-buttons button {
    padding: 10px 20px;
    border-radius: 6px;
    border: none;
    background-color: #2d7a2d;
    color: white;
    cursor: pointer;
    font-size: 14px;
}

.action-buttons button:hover {
    background-color: #256725;
}

@media print {
    .back-button,
    .action-buttons {
        display: none;
    }
}

@media (max-width: 768px) {
    .profile-container {
        grid-template-columns: 1fr;
    }
}
</style>
</head>

<body>

<div class="main-content">

<a href="association_operators.php" class="back-button">← Back to Operators List</a>

<h2 class="page-header">Operator Profile</h2>

<!-- Profile Information -->
<div class="profile-container">
    <!-- Left Column - Personal Info -->
    <div class="profile-card">
        <h3>Personal Information</h3>
        
        <div class="profile-info">
            <label>Full Name</label>
            <div class="value"><?= htmlspecialchars($operator['name']) ?></div>
        </div>

        <div class="profile-info">
            <label>Email Address</label>
            <div class="value"><?= htmlspecialchars($operator['email'] ?: $operator['user_email'] ?: 'N/A') ?></div>
        </div>

        <div class="profile-info">
            <label>Phone Number</label>
            <div class="value"><?= htmlspecialchars($operator['phone']) ?></div>
        </div>

        <div class="profile-info">
            <label>License Number</label>
            <div class="value"><?= htmlspecialchars($operator['license_number'] ?: 'N/A') ?></div>
        </div>

        <div class="profile-info">
            <label>Account Status</label>
            <div class="value">
                <span class="status-badge status-<?= strtolower($operator['status']) ?>">
                    <?= htmlspecialchars($operator['status']) ?>
                </span>
            </div>
        </div>

        <div class="profile-info">
            <label>Member Since</label>
            <div class="value"><?= date('F d, Y', strtotime($operator['created_at'])) ?></div>
        </div>
    </div>

    <!-- Right Column - Statistics -->
    <div class="profile-card">
        <h3>Performance Statistics</h3>
        
        <div class="stats-grid">
            <div class="stat-box">
                <h4><?= count($assigned_machines) ?></h4>
                <p>Assigned Machines</p>
            </div>
            
            <div class="stat-box">
                <h4><?= $total_completed ?></h4>
                <p>Completed Jobs</p>
            </div>
            
            <div class="stat-box">
                <h4><?= number_format($total_hectares, 2) ?></h4>
                <p>Total Hectares</p>
            </div>
            
            <div class="stat-box">
                <h4>₱<?= number_format($total_revenue, 2) ?></h4>
                <p>Total Revenue</p>
            </div>
        </div>

        <div style="margin-top: 20px;">
            <div class="profile-info">
                <label>Total Bookings</label>
                <div class="value"><?= count($transactions) ?> transactions</div>
            </div>

            <div class="profile-info">
                <label>Average Job Size</label>
                <div class="value">
                    <?= $total_completed > 0 ? number_format($total_hectares / $total_completed, 2) : '0.00' ?> hectares
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Assigned Machines -->
<div class="section">
    <h3>Assigned Machines</h3>
    
    <?php if (count($assigned_machines) > 0): ?>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Machine Name</th>
                    <th>Type</th>
                    <th>Price/Hectare</th>
                    <th>Machine Status</th>
                    <th>Assignment Status</th>
                    <th>Assigned Date</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1; foreach ($assigned_machines as $machine): ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><strong><?= htmlspecialchars($machine['machine_name']) ?></strong></td>
                    <td><span class="machine-badge"><?= htmlspecialchars($machine['type']) ?></span></td>
                    <td>₱<?= number_format($machine['price_per_hectare'] ?? 0, 2) ?></td>
                    <td><?= htmlspecialchars($machine['status']) ?></td>
                    <td>
                        <span class="status-badge status-<?= strtolower($machine['assignment_status']) ?>">
                            <?= htmlspecialchars($machine['assignment_status']) ?>
                        </span>
                    </td>
                    <td><?= date('M d, Y', strtotime($machine['assigned_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="no-data">No machines assigned to this operator</div>
    <?php endif; ?>
</div>

<!-- Transaction History -->
<div class="section">
    <h3>Transaction History</h3>
    
    <?php if (count($transactions) > 0): ?>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Booking Date</th>
                    <th>Machine</th>
                    <th>Farmer</th>
                    <th>Lot Number</th>
                    <th>Location</th>
                    <th>Farm Size</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Updated</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1; foreach ($transactions as $trans): ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><?= date('M d, Y', strtotime($trans['booking_date'])) ?></td>
                    <td>
                        <strong><?= htmlspecialchars($trans['machine_name']) ?></strong><br>
                        <small style="color: #666;"><?= htmlspecialchars($trans['machine_type']) ?></small>
                    </td>
                    <td>
                        <?= htmlspecialchars($trans['farmer_name']) ?><br>
                        <small style="color: #666;"><?= htmlspecialchars($trans['farmer_phone']) ?></small>
                    </td>
                    <td><?= htmlspecialchars($trans['lot_number'] ?: 'N/A') ?></td>
                    <td><small><?= htmlspecialchars($trans['farm_location'] ?: 'N/A') ?></small></td>
                    <td><?= number_format($trans['farm_size'], 2) ?> ha</td>
                    <td><strong>₱<?= number_format($trans['total_amount'], 2) ?></strong></td>
                    <td>
                        <span class="status-badge booking-<?= strtolower($trans['booking_status']) ?>">
                            <?= htmlspecialchars($trans['booking_status']) ?>
                        </span>
                    </td>
                    <td><small><?= date('M d, Y h:i A', strtotime($trans['updated_at'])) ?></small></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="no-data">No transaction history found for this operator</div>
    <?php endif; ?>
</div>

<!-- Action Buttons -->
<div class="action-buttons">
    <button onclick="window.print()">Print Profile</button>
</div>

</div>

</body>
</html>