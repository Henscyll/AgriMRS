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
   GET LEDGER ID
================================ */
if (!isset($_GET['id'])) {
    header("Location: payment_management.php");
    exit;
}
$ledger_id = (int)$_GET['id'];

/* ===============================
   FETCH LEDGER DETAILS
   FIX: f.name → CONCAT(f.first_name, ..., f.last_name)
================================ */
$ledger_sql = "
    SELECT 
        pl.*,
        CONCAT(
            f.first_name,
            CASE WHEN f.middle_name IS NOT NULL AND f.middle_name != ''
                 THEN CONCAT(' ', LEFT(f.middle_name,1), '.')
                 ELSE '' END,
            ' ', f.last_name
        ) AS farmer_name,
        f.phone as farmer_phone,
        m.machine_name,
        m.type as machine_type,
        b.booking_date,
        b.farm_size,
        fl.lot_number,
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
   FETCH PAYMENT TRANSACTIONS
   FIX: fetch all into array so we can use count() and loop freely
================================ */
$transactions_sql = "
    SELECT 
        pt.*,
        u.name as collected_by_name,
        u.email as collected_by_email
    FROM payment_transactions pt
    LEFT JOIN users u ON pt.collected_by = u.id
    WHERE pt.ledger_id = ?
    ORDER BY pt.payment_date DESC, pt.created_at DESC
";

$stmt = $conn->prepare($transactions_sql);
$stmt->bind_param("i", $ledger_id);
$stmt->execute();
$tx_result = $stmt->get_result();
$transactions = [];
while ($row = $tx_result->fetch_assoc()) {
    $transactions[] = $row;
}
$stmt->close();
$tx_count = count($transactions);
?>
<!DOCTYPE html>
<html>
<head>
<title>Payment History</title>
<style>
.main-content { max-width: 1400px; padding: 20px; }

.page-header { text-align: center; color: white; margin-bottom: 20px; }

.back-button {
    display: inline-block; margin-bottom: 15px; padding: 8px 15px;
    background: #feffff; color: #2d7a2d; text-decoration: none; border-radius: 4px;
    font-weight: 600; font-size: 14px; transition: all 0.2s;
}
.back-button:hover { background: #2d7a2d; color: white; }

/* ── Summary Card ── */
.summary-card {
    background: white; padding: 25px; border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0,0,0,.1); margin-bottom: 20px;
}
.summary-card h3 {
    margin: 0 0 20px 0; color: #2d7a2d;
    border-bottom: 2px solid #2d7a2d; padding-bottom: 10px;
}
.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}
.summary-item {
    padding: 15px; background: #f8f9fa; border-radius: 6px;
    border-left: 4px solid #2d7a2d;
}
.summary-item label {
    display: block; font-size: 12px; color: #666;
    margin-bottom: 5px; text-transform: uppercase;
}
.summary-item .value { font-size: 18px; font-weight: bold; color: #333; }

/* Status badges */
.status-badge   { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; display: inline-block; }
.status-unpaid  { background: #fff3cd; color: #856404; }
.status-partial { background: #cce5ff; color: #004085; }
.status-paid    { background: #d4edda; color: #155724; }
.status-overdue { background: #f8d7da; color: #721c24; }

/* ── Transactions Section ── */
.transactions-section {
    background: white; padding: 25px; border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0,0,0,.1);
}
.transactions-section h3 {
    margin: 0 0 20px 0; color: #2d7a2d;
    border-bottom: 2px solid #2d7a2d; padding-bottom: 10px;
    display: flex; justify-content: space-between; align-items: center;
}
.tx-count-badge {
    font-size: 13px; font-weight: 600; background: #e9f5e9;
    color: #2d7a2d; padding: 3px 10px; border-radius: 12px;
}

.transaction-list { display: flex; flex-direction: column; gap: 15px; }

.transaction-card {
    border: 1px solid #ddd; border-radius: 6px; padding: 20px;
    background: #f8f9fa; transition: box-shadow 0.3s;
}
.transaction-card:hover { box-shadow: 0 4px 8px rgba(0,0,0,.1); }

.transaction-header {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #2d7a2d;
}
.transaction-header .or-number { font-size: 16px; font-weight: bold; color: #2d7a2d; }
.transaction-header .amount    { font-size: 24px; font-weight: bold; color: #28a745; }

.transaction-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}
.detail-item { display: flex; flex-direction: column; }
.detail-item label { font-size: 12px; color: #666; margin-bottom: 3px; text-transform: uppercase; }
.detail-item .value { font-size: 14px; color: #333; font-weight: 500; }

.no-transactions { text-align: center; padding: 40px; color: #999; font-size: 15px; }

/* ── Action Buttons ── */
.action-buttons {
    margin-top: 20px; display: flex;
    justify-content: center; gap: 15px;
}
.action-buttons button,
.action-buttons a {
    padding: 10px 20px; border-radius: 6px; border: none;
    background-color: #2d7a2d; color: white;
    cursor: pointer; text-decoration: none; font-size: 14px;
    font-weight: 600; transition: background 0.2s;
}
.action-buttons button:hover,
.action-buttons a:hover { background-color: #256725; }

@media print {
    .back-button, .action-buttons { display: none; }
    body { background: white !important; }
    .main-content { padding: 0; }
}
</style>
</head>
<body>

<div class="main-content">

<a href="payment_management.php" class="back-button">← Back</a>

<h2 class="page-header">Payment History</h2>

<!-- Account Summary -->
<div class="summary-card">
    <h3>Account Summary</h3>
    <div class="summary-grid">

        <div class="summary-item">
            <label>Farmer Name</label>
            <div class="value"><?= htmlspecialchars($ledger['farmer_name']) ?></div>
        </div>

        <div class="summary-item">
            <label>Phone</label>
            <div class="value"><?= htmlspecialchars($ledger['farmer_phone']) ?></div>
        </div>

        <div class="summary-item">
            <label>Machine</label>
            <div class="value"><?= htmlspecialchars($ledger['machine_name']) ?></div>
        </div>

        <?php if ($ledger['lot_number']): ?>
        <div class="summary-item">
            <label>Lot Number</label>
            <div class="value"><?= htmlspecialchars($ledger['lot_number']) ?></div>
        </div>
        <?php endif; ?>

        <div class="summary-item">
            <label>Booking Date</label>
            <div class="value"><?= date('M d, Y', strtotime($ledger['booking_date'])) ?></div>
        </div>

        <div class="summary-item">
            <label>Farm Size</label>
            <div class="value"><?= number_format($ledger['farm_size'], 2) ?> ha</div>
        </div>

        <div class="summary-item">
            <label>Total Amount</label>
            <div class="value" style="font-size:20px;">₱<?= number_format($ledger['total_amount'], 2) ?></div>
        </div>

        <div class="summary-item">
            <label>Amount Paid</label>
            <div class="value" style="color:#28a745;">₱<?= number_format($ledger['amount_paid'], 2) ?></div>
        </div>

        <div class="summary-item">
            <label>Balance</label>
            <div class="value" style="color:#dc3545; font-size:20px;">₱<?= number_format($ledger['balance'], 2) ?></div>
        </div>

        <div class="summary-item">
            <label>Due Date</label>
            <div class="value">
                <?= date('M d, Y', strtotime($ledger['due_date'])) ?>
                <?php if ($ledger['days_overdue'] > 0 && $ledger['current_status'] === 'Overdue'): ?>
                    <br><small style="color:#dc3545;"><?= $ledger['days_overdue'] ?> days overdue</small>
                <?php endif; ?>
            </div>
        </div>

        <div class="summary-item">
            <label>Payment Status</label>
            <div class="value">
                <span class="status-badge status-<?= strtolower($ledger['current_status']) ?>">
                    <?= htmlspecialchars($ledger['current_status']) ?>
                </span>
            </div>
        </div>

        <div class="summary-item">
            <label>Total Payments</label>
            <div class="value"><?= $tx_count ?> transaction<?= $tx_count !== 1 ? 's' : '' ?></div>
        </div>

    </div>
</div>

<!-- Payment Transactions -->
<div class="transactions-section">
    <h3>
        Payment Transactions
        <span class="tx-count-badge"><?= $tx_count ?> record<?= $tx_count !== 1 ? 's' : '' ?></span>
    </h3>

    <?php if ($tx_count > 0): ?>
    <div class="transaction-list">
        <?php foreach ($transactions as $trans): ?>
        <div class="transaction-card">
            <div class="transaction-header">
                <div class="or-number"><?= htmlspecialchars($trans['or_number']) ?></div>
                <div class="amount">₱<?= number_format($trans['amount'], 2) ?></div>
            </div>
            <div class="transaction-details">
                <div class="detail-item">
                    <label>Payment Date</label>
                    <div class="value"><?= date('F d, Y', strtotime($trans['payment_date'])) ?></div>
                </div>
                <div class="detail-item">
                    <label>Collected By</label>
                    <div class="value"><?= htmlspecialchars($trans['collected_by_name'] ?: 'N/A') ?></div>
                </div>
                <div class="detail-item">
                    <label>Recorded At</label>
                    <div class="value"><?= date('M d, Y h:i A', strtotime($trans['created_at'])) ?></div>
                </div>
                <?php if ($trans['notes']): ?>
                <div class="detail-item" style="grid-column: 1 / -1;">
                    <label>Notes</label>
                    <div class="value"><?= htmlspecialchars($trans['notes']) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="no-transactions">No payment transactions recorded yet.</div>
    <?php endif; ?>
</div>

<!-- Action Buttons -->
<div class="action-buttons">
    <?php if ($ledger['payment_status'] !== 'Paid'): ?>
    <a href="payment_management.php">Collect Payment</a>
    <?php endif; ?>
    <button onclick="window.print()">Print History</button>
</div>

</div>
</body>
</html>
