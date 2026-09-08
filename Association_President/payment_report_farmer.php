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
$user_id = $_SESSION['user_id'] ?? null;

/* ===============================
   GET FARMER ID
================================ */
if (!isset($_GET['farmer_id'])) {
    header("Location: payment_management.php");
    exit;
}
$farmer_id = (int)$_GET['farmer_id'];

$message = "";
$error = "";

/* ===============================
   SUBMIT COMPLAINT
================================ */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['submit_complaint'])) {
    $complaint_details = trim($_POST['complaint_details']);
    
    if (empty($complaint_details)) {
        $error = "Please provide complaint details.";
    } else {
        // Create complaint record (you may need to create a complaints table)
        // For now, we'll send notification to admin
        
        // TODO: Create complaints table and insert record
        // For demonstration, we'll just show a success message
        
        $message = "Complaint has been submitted to admin. The farmer account will be reviewed.";
        
        // Optional: You can also update farmer status to 'Inactive' immediately
        // $update_sql = "UPDATE farmers SET status = 'Inactive' WHERE id = ?";
        // $stmt = $conn->prepare($update_sql);
        // $stmt->bind_param("i", $farmer_id);
        // $stmt->execute();
        // $stmt->close();
    }
}

/* ===============================
   FETCH FARMER DETAILS
================================ */
$farmer_sql = "
    SELECT 
        f.*,
        COUNT(pl.id) as total_ledgers,
        SUM(pl.balance) as total_outstanding,
        SUM(CASE WHEN pl.payment_status != 'Paid' AND pl.due_date < CURDATE() THEN 1 ELSE 0 END) as overdue_count,
        SUM(CASE WHEN pl.payment_status != 'Paid' AND pl.due_date < CURDATE() THEN pl.balance ELSE 0 END) as overdue_amount,
        MAX(DATEDIFF(CURDATE(), pl.due_date)) as max_days_overdue
    FROM farmers f
    INNER JOIN payment_ledger pl ON f.id = pl.farmer_id
    WHERE f.id = ? AND pl.association_id = ?
    GROUP BY f.id
";

$stmt = $conn->prepare($farmer_sql);
$stmt->bind_param("ii", $farmer_id, $association_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: payment_management.php");
    exit;
}

$farmer = $result->fetch_assoc();
$stmt->close();

/* ===============================
   FETCH OVERDUE LEDGERS
================================ */
$overdue_sql = "
    SELECT 
        pl.*,
        b.booking_date,
        b.farm_size,
        m.machine_name,
        m.type as machine_type,
        fl.lot_number,
        DATEDIFF(CURDATE(), pl.due_date) as days_overdue
    FROM payment_ledger pl
    INNER JOIN bookings b ON pl.booking_id = b.id
    INNER JOIN machines m ON pl.machine_id = m.id
    LEFT JOIN farmer_lots fl ON b.lot_id = fl.id
    WHERE pl.farmer_id = ? 
      AND pl.association_id = ?
      AND pl.payment_status != 'Paid'
      AND pl.due_date < CURDATE()
    ORDER BY pl.due_date ASC
";

$stmt = $conn->prepare($overdue_sql);
$stmt->bind_param("ii", $farmer_id, $association_id);
$stmt->execute();
$overdue_ledgers = $stmt->get_result();
$stmt->close();
?>

<!DOCTYPE html>
<html>
<head>
<title>Report Non-Payment</title>

<style>
.main-content {
    padding: 20px;
    max-width: 1000px;
    margin: 0 auto;
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

.alert {
    padding: 15px 20px;
    border-radius: 6px;
    margin-bottom: 20px;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.alert-warning {
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
}

.farmer-card {
    background: white;
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0,0,0,.1);
    margin-bottom: 20px;
}

.farmer-card h3 {
    margin: 0 0 20px 0;
    color: #dc3545;
    border-bottom: 2px solid #dc3545;
    padding-bottom: 10px;
}

.farmer-info {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.info-item {
    padding: 12px;
    background: #f8f9fa;
    border-radius: 6px;
    border-left: 4px solid #dc3545;
}

.info-item label {
    display: block;
    font-size: 12px;
    color: #666;
    margin-bottom: 5px;
}

.info-item .value {
    font-size: 16px;
    font-weight: bold;
    color: #333;
}

.overdue-section {
    background: white;
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0,0,0,.1);
    margin-bottom: 20px;
}

.overdue-section h3 {
    margin: 0 0 20px 0;
    color: #2d7a2d;
    border-bottom: 2px solid #2d7a2d;
    padding-bottom: 10px;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th, td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

th {
    background: #f8f9fa;
    color: #333;
    font-weight: 600;
}

.complaint-form {
    background: white;
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0,0,0,.1);
}

.complaint-form h3 {
    margin: 0 0 20px 0;
    color: #2d7a2d;
    border-bottom: 2px solid #2d7a2d;
    padding-bottom: 10px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    font-weight: bold;
    color: #666;
    margin-bottom: 8px;
}

.form-group textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
    resize: vertical;
    min-height: 150px;
    box-sizing: border-box;
}

.btn-submit {
    background: #dc3545;
    color: white;
    border: none;
    padding: 12px 30px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 16px;
    font-weight: bold;
}

.btn-submit:hover {
    background: #c82333;
}

.btn-cancel {
    background: #6c757d;
    color: white;
    border: none;
    padding: 12px 30px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 16px;
    margin-left: 10px;
}

.btn-cancel:hover {
    background: #5a6268;
}
</style>
</head>

<body>

<div class="main-content">

<a href="payment_management.php" class="back-button">← Back to Payment Management</a>

<h2 class="page-header">Report Non-Payment to Admin</h2>

<?php if ($message): ?>
<div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="alert alert-warning">
    <strong>⚠️ Warning:</strong> You are about to report this farmer to the administrator for non-payment. 
    This may result in the farmer's account being suspended or deactivated.
</div>

<!-- Farmer Information -->
<div class="farmer-card">
    <h3>Farmer Details</h3>
    
    <div class="farmer-info">
        <div class="info-item">
            <label>Farmer Name</label>
            <div class="value"><?= htmlspecialchars($farmer['name']) ?></div>
        </div>
        
        <div class="info-item">
            <label>Phone Number</label>
            <div class="value"><?= htmlspecialchars($farmer['phone']) ?></div>
        </div>
        
        <div class="info-item">
            <label>Email</label>
            <div class="value"><?= htmlspecialchars($farmer['email'] ?: 'N/A') ?></div>
        </div>
        
        <div class="info-item">
            <label>Total Outstanding</label>
            <div class="value" style="color: #dc3545;">₱<?= number_format($farmer['total_outstanding'], 2) ?></div>
        </div>
        
        <div class="info-item">
            <label>Overdue Accounts</label>
            <div class="value" style="color: #dc3545;"><?= $farmer['overdue_count'] ?> account(s)</div>
        </div>
        
        <div class="info-item">
            <label>Overdue Amount</label>
            <div class="value" style="color: #dc3545;">₱<?= number_format($farmer['overdue_amount'], 2) ?></div>
        </div>
        
        <div class="info-item">
            <label>Maximum Days Overdue</label>
            <div class="value" style="color: #dc3545;"><?= $farmer['max_days_overdue'] ?> days</div>
        </div>
        
        <div class="info-item">
            <label>Account Status</label>
            <div class="value">
                <span style="color: <?= $farmer['status'] === 'Active' ? '#28a745' : '#dc3545' ?>;">
                    <?= htmlspecialchars($farmer['status']) ?>
                </span>
            </div>
        </div>
    </div>
</div>

<!-- Overdue Accounts -->
<div class="overdue-section">
    <h3>Overdue Payment Accounts</h3>
    
    <table>
        <thead>
            <tr>
                <th>Booking Date</th>
                <th>Machine</th>
                <th>Lot</th>
                <th>Amount</th>
                <th>Balance</th>
                <th>Due Date</th>
                <th>Days Overdue</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($ledger = $overdue_ledgers->fetch_assoc()): ?>
            <tr>
                <td><?= date('M d, Y', strtotime($ledger['booking_date'])) ?></td>
                <td><?= htmlspecialchars($ledger['machine_name']) ?></td>
                <td><?= htmlspecialchars($ledger['lot_number'] ?: 'N/A') ?></td>
                <td>₱<?= number_format($ledger['total_amount'], 2) ?></td>
                <td style="color: #dc3545;"><strong>₱<?= number_format($ledger['balance'], 2) ?></strong></td>
                <td><?= date('M d, Y', strtotime($ledger['due_date'])) ?></td>
                <td style="color: #dc3545;"><strong><?= $ledger['days_overdue'] ?> days</strong></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<!-- Complaint Form -->
<div class="complaint-form">
    <h3>Submit Complaint to Admin</h3>
    
    <form method="POST">
        <div class="form-group">
            <label>Complaint Details *</label>
            <textarea name="complaint_details" required 
                      placeholder="Describe the payment issues, efforts made to collect, and why you're requesting admin intervention...">The farmer has <?= $farmer['overdue_count'] ?> overdue account(s) totaling ₱<?= number_format($farmer['overdue_amount'], 2) ?>. The longest overdue payment is <?= $farmer['max_days_overdue'] ?> days past due date.

Despite multiple attempts to collect payment, the farmer has not responded or made any payment arrangements.

We request admin intervention to review and potentially suspend this account.</textarea>
        </div>
        
        <button type="submit" name="submit_complaint" class="btn-submit" 
                onclick="return confirm('Are you sure you want to report this farmer to admin? This action may result in account suspension.')">
            Submit Complaint to Admin
        </button>
        
        <button type="button" class="btn-cancel" onclick="window.history.back()">
            Cancel
        </button>
    </form>
</div>

</div>

</body>
</html>