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
   GET LEDGER ID
================================ */
if (!isset($_GET['id'])) {
    header("Location: payment_management.php");
    exit;
}
$ledger_id = (int)$_GET['id'];

$message = "";
$error = "";

/* ===============================
   PROCESS PAYMENT
================================ */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['record_payment'])) {
    $amount = (float)$_POST['amount'];
    $payment_date = $_POST['payment_date'];
    $notes = trim($_POST['notes']);
    
    // Validation
    if ($amount <= 0) {
        $error = "Amount must be greater than zero.";
    } else {
        // Get ledger balance
        $check_sql = "SELECT balance, payment_status FROM payment_ledger WHERE id = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("i", $ledger_id);
        $stmt->execute();
        $ledger = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$ledger) {
            $error = "Ledger not found.";
        } else if ($ledger['payment_status'] === 'Paid') {
            $error = "This account is already fully paid.";
        } else if ($amount > $ledger['balance']) {
            $error = "Payment amount (₱" . number_format($amount, 2) . ") exceeds remaining balance (₱" . number_format($ledger['balance'], 2) . ").";
        } else {
            // Generate OR number
            $or_number = 'OR-' . date('Ymd') . '-' . str_pad($ledger_id, 5, '0', STR_PAD_LEFT) . '-' . time();
            
            // Insert payment transaction
            $insert_sql = "
                INSERT INTO payment_transactions (ledger_id, or_number, amount, payment_date, collected_by, notes)
                VALUES (?, ?, ?, ?, ?, ?)
            ";
            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param("isdsis", $ledger_id, $or_number, $amount, $payment_date, $user_id, $notes);
            
            if ($stmt->execute()) {
                $message = "Payment recorded successfully! OR Number: " . $or_number;
                // Redirect to refresh the page
                header("Location: payment_collect.php?id=" . $ledger_id . "&success=1&or=" . urlencode($or_number));
                exit;
            } else {
                $error = "Failed to record payment: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

/* ===============================
   SUCCESS MESSAGE FROM REDIRECT
================================ */
if (isset($_GET['success']) && isset($_GET['or'])) {
    $message = "Payment recorded successfully! OR Number: " . htmlspecialchars($_GET['or']);
}

/* ===============================
   FETCH LEDGER DETAILS
================================ */
$ledger_sql = "
    SELECT 
        pl.*,
        f.name as farmer_name,
        f.phone as farmer_phone,
        f.email as farmer_email,
        m.machine_name,
        m.type as machine_type,
        b.booking_date,
        b.farm_size,
        fl.lot_number,
        fl.farm_location,
        CASE 
            WHEN pl.payment_status != 'Paid' AND pl.due_date < CURDATE() THEN 'Overdue'
            ELSE pl.payment_status
        END as current_status,
        DATEDIFF(CURDATE(), pl.due_date) as days_overdue
    FROM payment_ledger pl
    INNER JOIN farmers f ON pl.farmer_id = f.id
    INNER JOIN machines m ON pl.machine_id = m.id
    INNER JOIN bookings b ON pl.booking_id = b.id
    LEFT JOIN farmer_lots fl ON b.lot_id = fl.id
    WHERE pl.id = ? AND pl.association_id = ?
";

$stmt = $conn->prepare($ledger_sql);
$stmt->bind_param("ii", $ledger_id, $association_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: payment_management.php");
    exit;
}

$ledger = $result->fetch_assoc();
$stmt->close();

/* ===============================
   FETCH PAYMENT HISTORY
================================ */
$history_sql = "
    SELECT 
        pt.*,
        u.name as collected_by_name
    FROM payment_transactions pt
    LEFT JOIN users u ON pt.collected_by = u.id
    WHERE pt.ledger_id = ?
    ORDER BY pt.payment_date DESC, pt.created_at DESC
";

$stmt = $conn->prepare($history_sql);
$stmt->bind_param("i", $ledger_id);
$stmt->execute();
$payment_history = $stmt->get_result();
$stmt->close();
?>

<!DOCTYPE html>
<html>
<head>
<title>Collect Payment</title>

<style>
.main-content {
    padding: 20px;
    max-width: 1200px;
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
    padding: 12px 20px;
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

.info-section {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.info-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0,0,0,.1);
}

.info-card h3 {
    margin: 0 0 15px 0;
    color: #2d7a2d;
    border-bottom: 2px solid #2d7a2d;
    padding-bottom: 8px;
}

.info-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #f0f0f0;
}

.info-row label {
    font-weight: bold;
    color: #666;
}

.info-row .value {
    color: #333;
    text-align: right;
}

.balance-highlight {
    background: #fff3cd;
    padding: 20px;
    border-radius: 8px;
    text-align: center;
    margin: 20px 0;
    border: 2px solid #ffc107;
}

.balance-highlight h2 {
    margin: 0 0 10px 0;
    color: #856404;
    font-size: 36px;
}

.balance-highlight p {
    margin: 0;
    color: #666;
}

.status-badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: bold;
}

.status-unpaid {
    background: #fff3cd;
    color: #856404;
}

.status-partial {
    background: #cce5ff;
    color: #004085;
}

.status-paid {
    background: #d4edda;
    color: #155724;
}

.status-overdue {
    background: #f8d7da;
    color: #721c24;
}

.payment-form {
    background: white;
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0,0,0,.1);
    margin-bottom: 20px;
}

.payment-form h3 {
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

.form-group input,
.form-group textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
    box-sizing: border-box;
}

.form-group textarea {
    resize: vertical;
    min-height: 80px;
}

.quick-amount {
    display: flex;
    gap: 10px;
    margin-top: 10px;
}

.quick-amount button {
    flex: 1;
    padding: 8px;
    background: #e9ecef;
    border: 1px solid #ddd;
    border-radius: 4px;
    cursor: pointer;
    font-size: 13px;
}

.quick-amount button:hover {
    background: #d4d8db;
}

.btn-submit {
    background: #28a745;
    color: white;
    border: none;
    padding: 12px 30px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 16px;
    font-weight: bold;
}

.btn-submit:hover {
    background: #218838;
}

.payment-history {
    background: white;
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0,0,0,.1);
}

.payment-history h3 {
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

.no-history {
    text-align: center;
    padding: 30px;
    color: #999;
}

@media (max-width: 768px) {
    .info-section {
        grid-template-columns: 1fr;
    }
}
</style>
</head>

<body>

<div class="main-content">

<a href="payment_management.php" class="back-button">← Back to Payment Management</a>

<h2 class="page-header">Collect Payment</h2>

<?php if ($message): ?>
<div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Account Information -->
<div class="info-section">
    <!-- Farmer Details -->
    <div class="info-card">
        <h3>Farmer Information</h3>
        <div class="info-row">
            <label>Name:</label>
            <div class="value"><?= htmlspecialchars($ledger['farmer_name']) ?></div>
        </div>
        <div class="info-row">
            <label>Phone:</label>
            <div class="value"><?= htmlspecialchars($ledger['farmer_phone']) ?></div>
        </div>
        <div class="info-row">
            <label>Email:</label>
            <div class="value"><?= htmlspecialchars($ledger['farmer_email'] ?: 'N/A') ?></div>
        </div>
        <?php if ($ledger['lot_number']): ?>
        <div class="info-row">
            <label>Lot Number:</label>
            <div class="value"><?= htmlspecialchars($ledger['lot_number']) ?></div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Service Details -->
    <div class="info-card">
        <h3>Service Details</h3>
        <div class="info-row">
            <label>Machine:</label>
            <div class="value"><?= htmlspecialchars($ledger['machine_name']) ?></div>
        </div>
        <div class="info-row">
            <label>Type:</label>
            <div class="value"><?= htmlspecialchars($ledger['machine_type']) ?></div>
        </div>
        <div class="info-row">
            <label>Booking Date:</label>
            <div class="value"><?= date('M d, Y', strtotime($ledger['booking_date'])) ?></div>
        </div>
        <div class="info-row">
            <label>Farm Size:</label>
            <div class="value"><?= number_format($ledger['farm_size'], 2) ?> hectares</div>
        </div>
    </div>
</div>

<!-- Payment Summary -->
<div class="info-card">
    <h3>Payment Summary</h3>
    <div class="info-row">
        <label>Total Amount:</label>
        <div class="value" style="font-size: 18px; font-weight: bold;">₱<?= number_format($ledger['total_amount'], 2) ?></div>
    </div>
    <div class="info-row">
        <label>Amount Paid:</label>
        <div class="value" style="color: #28a745;">₱<?= number_format($ledger['amount_paid'], 2) ?></div>
    </div>
    <div class="info-row">
        <label>Remaining Balance:</label>
        <div class="value" style="color: #dc3545; font-size: 18px; font-weight: bold;">₱<?= number_format($ledger['balance'], 2) ?></div>
    </div>
    <div class="info-row">
        <label>Due Date:</label>
        <div class="value">
            <?= date('M d, Y', strtotime($ledger['due_date'])) ?>
            <?php if ($ledger['days_overdue'] > 0): ?>
                <br><span style="color: #dc3545; font-size: 12px;">
                    <strong><?= $ledger['days_overdue'] ?> days overdue</strong>
                </span>
            <?php endif; ?>
        </div>
    </div>
    <div class="info-row">
        <label>Status:</label>
        <div class="value">
            <span class="status-badge status-<?= strtolower($ledger['current_status']) ?>">
                <?= htmlspecialchars($ledger['current_status']) ?>
            </span>
        </div>
    </div>
</div>

<?php if ($ledger['payment_status'] !== 'Paid'): ?>
<!-- Balance Highlight -->
<div class="balance-highlight">
    <h2>₱<?= number_format($ledger['balance'], 2) ?></h2>
    <p>Remaining Balance to Collect</p>
</div>

<!-- Payment Collection Form -->
<div class="payment-form">
    <h3>Record Payment</h3>
    
    <form method="POST">
        <div class="form-group">
            <label>Payment Amount (₱) *</label>
            <input type="number" 
                   name="amount" 
                   id="payment_amount"
                   step="0.01" 
                   min="0.01" 
                   max="<?= $ledger['balance'] ?>"
                   required 
                   placeholder="0.00">
            
            <div class="quick-amount">
                <button type="button" onclick="setAmount(<?= $ledger['balance'] ?>)">
                    Full Payment (₱<?= number_format($ledger['balance'], 2) ?>)
                </button>
                <button type="button" onclick="setAmount(<?= $ledger['balance'] / 2 ?>)">
                    Half (₱<?= number_format($ledger['balance'] / 2, 2) ?>)
                </button>
                <button type="button" onclick="setAmount(<?= $ledger['balance'] / 4 ?>)">
                    Quarter (₱<?= number_format($ledger['balance'] / 4, 2) ?>)
                </button>
            </div>
        </div>

        <div class="form-group">
            <label>Payment Date *</label>
            <input type="date" 
                   name="payment_date" 
                   value="<?= date('Y-m-d') ?>"
                   max="<?= date('Y-m-d') ?>"
                   required>
        </div>

        <div class="form-group">
            <label>Notes (Optional)</label>
            <textarea name="notes" placeholder="Add any notes about this payment..."></textarea>
        </div>

        <button type="submit" name="record_payment" class="btn-submit">
            Record Payment
        </button>
    </form>
</div>
<?php else: ?>
<div class="alert alert-success" style="text-align: center; font-size: 18px;">
    ✓ This account is fully paid!
</div>
<?php endif; ?>

<!-- Payment History -->
<div class="payment-history">
    <h3>Payment History</h3>
    
    <?php if ($payment_history->num_rows > 0): ?>
    <table>
        <thead>
            <tr>
                <th>OR Number</th>
                <th>Date</th>
                <th>Amount</th>
                <th>Collected By</th>
                <th>Notes</th>
                <th>Recorded At</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($payment = $payment_history->fetch_assoc()): ?>
            <tr>
                <td><strong><?= htmlspecialchars($payment['or_number']) ?></strong></td>
                <td><?= date('M d, Y', strtotime($payment['payment_date'])) ?></td>
                <td style="color: #28a745; font-weight: bold;">₱<?= number_format($payment['amount'], 2) ?></td>
                <td><?= htmlspecialchars($payment['collected_by_name'] ?: 'N/A') ?></td>
                <td><?= htmlspecialchars($payment['notes'] ?: '-') ?></td>
                <td><small><?= date('M d, Y h:i A', strtotime($payment['created_at'])) ?></small></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    <?php else: ?>
    <div class="no-history">No payment history yet</div>
    <?php endif; ?>
</div>

</div>

<script>
function setAmount(amount) {
    document.getElementById('payment_amount').value = amount.toFixed(2);
}
</script>

</body>
</html>