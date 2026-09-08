<?php
session_start();
require_once '../includes/config.php';
include('farmers_header.php');
include_once '../includes/farmer_auth.php';
// Check if user is logged in and is a farmer

$farmer_id = $_SESSION['user_id'];

// Handle status filter
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'All';

// Build query based on filter
$where_clause = "WHERE b.farmer_id = ?";
if ($status_filter !== 'All') {
    $where_clause .= " AND b.status = ?";
}

// Fetch farmer's bookings (with farm lot + payment ledger info)
$query = "SELECT b.*, 
                 m.machine_name, 
                 m.type, 
                 m.image_path,
                 a.name AS association_name,
                 a.phone AS association_phone,
                 a.municipality,
                 a.province,
                 fl.lot_number,
                 pl.id AS ledger_id,
                 pl.total_amount,
                 pl.amount_paid,
                 pl.balance,
                 pl.due_date,
                 pl.payment_status,
                 (SELECT COUNT(*) FROM payment_transactions WHERE ledger_id = pl.id) AS payment_count
          FROM bookings b
          INNER JOIN machines m ON b.machine_id = m.id
          INNER JOIN associations a ON m.association_id = a.id
          LEFT JOIN farmer_lots fl ON b.lot_id = fl.id
          LEFT JOIN payment_ledger pl ON pl.booking_id = b.id
          $where_clause
          ORDER BY b.created_at DESC";

$stmt = $conn->prepare($query);
if ($status_filter !== 'All') {
    $stmt->bind_param("is", $farmer_id, $status_filter);
} else {
    $stmt->bind_param("i", $farmer_id);
}
$stmt->execute();
$bookings = $stmt->get_result();

// Get booking counts by status
$countQuery = "SELECT 
                  COUNT(*) as total,
                  SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
                  SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved,
                  SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
                  SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled
               FROM bookings 
               WHERE farmer_id = ?";
$countStmt = $conn->prepare($countQuery);
$countStmt->bind_param("i", $farmer_id);
$countStmt->execute();
$counts = $countStmt->get_result()->fetch_assoc();

// Pull all rows into an array so we can build both the table and the JS data set
$booking_rows = [];
if ($bookings) {
    while ($row = $bookings->fetch_assoc()) {
        $booking_rows[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Reservations</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root {
      --primary-color: #16a34a;
      --primary-dark: #15803d;
      --primary-light: #dcfce7;
      --pending-color: #f59e0b;
      --approved-color: #3b82f6;
      --completed-color: #10b981;
      --cancelled-color: #ef4444;
      --text-primary: #1f2937;
      --text-secondary: #6b7280;
      --bg-light: #f3f4f6;
      --border-color: #d1d5db;
      --shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
      --shadow-hover: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .main-container {
      max-width: 1300px;
      margin: 0 auto;
      padding: 20px;
    }

    /* Success/Error Messages */
    .alert {
      padding: 10px 14px;
      border-radius: 6px;
      margin-bottom: 16px;
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 12px;
      animation: slideDown 0.3s ease;
    }

    @keyframes slideDown {
      from {
        transform: translateY(-10px);
        opacity: 0;
      }
      to {
        transform: translateY(0);
        opacity: 1;
      }
    }

    .alert-success {
      background: #d1fae5;
      color: #065f46;
      border-left: 4px solid #10b981;
    }

    .alert-error {
      background: #fee2e2;
      color: #991b1b;
      border-left: 4px solid #ef4444;
    }

    /* Page Header */
    .page-header {
      background: white;
      padding: 12px 16px;
      border-radius: 8px;
      box-shadow: var(--shadow);
      margin-bottom: 12px;
    }

    .header-content {
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 12px;
    }

    .header-left {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .header-icon {
      width: 32px;
      height: 32px;
      background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 18px;
    }

    .header-text h1 {
      margin: 0;
      font-size: 20px;
      color: var(--text-primary);
      font-weight: 700;
    }

    .header-text p {
      margin: 2px 0 0;
      color: var(--text-secondary);
      font-size: 12px;
    }

    /* Stats Cards - More Compact */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
      gap: 8px;
      margin-bottom: 12px;
    }

    .stat-card {
      background: white;
      padding: 10px;
      border-radius: 6px;
      box-shadow: var(--shadow);
      transition: all 0.3s ease;
      cursor: pointer;
      border: 2px solid transparent;
    }

    .stat-card:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow-hover);
    }

    .stat-card.active {
      border-color: var(--primary-color);
      box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.1);
    }

    .stat-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 6px;
    }

    .stat-icon {
      width: 28px;
      height: 28px;
      border-radius: 6px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 13px;
      color: white;
    }

    .stat-icon.total { background: linear-gradient(135deg, #6366f1, #4f46e5); }
    .stat-icon.pending { background: linear-gradient(135deg, #f59e0b, #d97706); }
    .stat-icon.approved { background: linear-gradient(135deg, #3b82f6, #2563eb); }
    .stat-icon.completed { background: linear-gradient(135deg, #10b981, #059669); }
    .stat-icon.cancelled { background: linear-gradient(135deg, #ef4444, #dc2626); }

    .stat-label {
      font-size: 11px;
      color: var(--text-secondary);
      font-weight: 500;
      text-transform: uppercase;
      letter-spacing: 0.3px;
    }

    .stat-value {
      font-size: 22px;
      font-weight: 700;
      color: var(--text-primary);
    }

    /* Bookings Section */
    .bookings-section {
      background: white;
      padding: 16px;
      border-radius: 8px;
      box-shadow: var(--shadow);
    }

    .section-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 12px;
      padding-bottom: 12px;
      border-bottom: 2px solid var(--border-color);
    }

    .section-header h2 {
      margin: 0;
      font-size: 16px;
      color: var(--text-primary);
      display: flex;
      align-items: center;
      gap: 6px;
    }

    /* ── Table ── */
    .table-container {
      border: 1px solid var(--border-color);
      border-radius: 10px;
      overflow: hidden;
      margin-bottom: 4px;
    }
    .table-scroll { overflow-x: auto; overflow-y: hidden; }
    .table-scroll table { table-layout: fixed; }
    .table-scroll tbody { display: block; max-height: 460px; overflow-y: auto; overflow-x: hidden; width: 100%; }
    .table-scroll thead { display: table; width: 100%; table-layout: fixed; }
    .table-scroll tbody tr { display: table; width: 100%; table-layout: fixed; }

    .res-table { width: 100%; border-collapse: collapse; font-size: 11px; min-width: 1100px; }

    .res-table thead {
      position: sticky; top: 0; z-index: 5;
      background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    }
    .res-table thead th {
      color: white; text-align: center; padding: 10px 6px; font-weight: 700;
      text-transform: uppercase; font-size: 10.5px; letter-spacing: 0.3px;
      white-space: normal; word-break: break-word; line-height: 1.35;
    }
    .res-table tbody tr {
      transition: background 0.15s ease; border-bottom: 1px solid var(--border-color); cursor: pointer;
    }
    .res-table tbody tr:hover { background: var(--primary-light); }
    .res-table tbody tr.selected-row { background: #bbf7d0 !important; border-left: 4px solid var(--primary-dark); }
    .res-table tbody td {
      padding: 8px 6px; color: var(--text-primary); vertical-align: middle; font-size: 11px;
      word-wrap: break-word; text-align: center; line-height: 1.3;
    }
    .res-table tbody td.td-left { text-align: left; }
    .res-table tbody tr:last-child { border-bottom: none; }

    .fw600 { font-weight: 600; }
    .fw700 { font-weight: 700; }
    .clr-green { color: #16a34a; }
    .clr-red   { color: #dc2626; }
    .clr-muted { color: var(--text-secondary); font-size: 10px; }

    .status-badge {
      padding: 4px 10px;
      border-radius: 12px;
      font-size: 10px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.3px;
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }

    .status-pending   { background: #fef3c7; color: #92400e; }
    .status-approved  { background: #dbeafe; color: #1e40af; }
    .status-completed { background: #d1fae5; color: #065f46; }
    .status-cancelled { background: #fee2e2; color: #991b1b; }
    .status-unpaid    { background: #fee2e2; color: #991b1b; }
    .status-partial   { background: #fef3c7; color: #92400e; }
    .status-paid      { background: #d1fae5; color: #065f46; }

    /* Bottom action buttons (below the table) */
    .action-buttons {
      display: flex; justify-content: center; gap: 10px;
      margin-top: 16px; margin-bottom: 4px; flex-wrap: wrap;
    }
    .action-buttons button,
    .action-buttons a {
      padding: 10px 22px; border-radius: 6px; border: none;
      background-color: var(--primary-color); color: white; cursor: pointer;
      font-size: 14px; font-weight: 600; transition: background 0.2s, transform 0.2s;
      display: inline-flex; align-items: center; gap: 8px; text-decoration: none;
    }
    .action-buttons button:hover,
    .action-buttons a:hover { background-color: var(--primary-dark); transform: scale(1.03); }
    .action-buttons button:disabled { opacity: .5; cursor: not-allowed; transform: none; }
    .action-buttons .btn-cancel-selected { background-color: #dc2626; }
    .action-buttons .btn-cancel-selected:hover { background-color: #b91c1c; }

    /* Modal Styles */
    .modal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.5);
      animation: fadeIn 0.3s ease;
    }

    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }

    .modal.active {
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .modal-content {
      background: white;
      border-radius: 12px;
      max-width: 700px;
      width: 90%;
      max-height: 85vh;
      overflow-y: auto;
      animation: slideUp 0.3s ease;
      box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
    }

    @keyframes slideUp {
      from {
        transform: translateY(50px);
        opacity: 0;
      }
      to {
        transform: translateY(0);
        opacity: 1;
      }
    }

    /* Empty State */
    .empty-state {
      text-align: center;
      padding: 40px 20px;
    }

    .empty-state i {
      font-size: 48px;
      color: var(--border-color);
      margin-bottom: 12px;
    }

    .empty-state h3 {
      color: var(--text-primary);
      margin-bottom: 6px;
      font-size: 16px;
    }

    .empty-state p {
      color: var(--text-secondary);
      margin-bottom: 16px;
      font-size: 13px;
    }

    /* Responsive */
    @media (max-width: 768px) {
      .main-container {
        padding: 12px;
      }

      .stats-grid {
        grid-template-columns: repeat(2, 1fr);
      }

      .header-content {
        flex-direction: column;
        align-items: flex-start;
      }

      .action-buttons button,
      .action-buttons a {
        width: 100%;
        justify-content: center;
      }
    }
  </style>
</head>
<body>

<div class="main-container">
  <?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success">
      <i class="fas fa-check-circle"></i>
      <?= htmlspecialchars($_SESSION['success_message']) ?>
    </div>
    <?php unset($_SESSION['success_message']); ?>
  <?php endif; ?>

  <?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-error">
      <i class="fas fa-exclamation-circle"></i>
      <?= htmlspecialchars($_SESSION['error_message']) ?>
    </div>
    <?php unset($_SESSION['error_message']); ?>
  <?php endif; ?>

  <!-- Page Header -->
  <div class="page-header">
    <div class="header-content">
      <div class="header-left">
        <div class="header-icon">
          <i class="fas fa-calendar-check"></i>
        </div>
        <div class="header-text">
          <h1>My Reservations</h1>
          <p>View and manage your machine bookings — double-click a row to see full details</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Bookings Table -->
  <div class="bookings-section">
    <div class="section-header">
      <h2>
        <i class="fas fa-calendar-alt"></i>
        <?= $status_filter === 'All' ? 'All Reservations' : $status_filter . ' Reservations' ?>
      </h2>
      <span style="font-weight:700; font-size:13px; color:var(--text-primary);">
        Total Records: <?= count($booking_rows) ?>
      </span>
    </div>

    <?php if (count($booking_rows) > 0): ?>
      <div class="table-container">
        <div class="table-scroll">
          <table class="res-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Reservation Date</th>
                <th>Farm Lot</th>
                <th>Farm Size</th>
                <th>Machine Type</th>
                <th>Machine Name</th>
                <th>Amount / Hectare</th>
                <th>Total Amount</th>
                <th>Paid</th>
                <th>Balance</th>
                <th>Schedule</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody id="bookingsTable">
              <?php
              $i = 1;
              foreach ($booking_rows as $booking):
                  $farmSize   = (float)($booking['farm_size'] ?? 0);
                  $totalAmt   = $booking['total_amount'] !== null ? (float)$booking['total_amount'] : null;
                  $paidAmt    = $booking['amount_paid']  !== null ? (float)$booking['amount_paid']  : null;
                  $balanceAmt = $booking['balance']      !== null ? (float)$booking['balance']      : null;
                  $rate       = ($totalAmt !== null && $farmSize > 0) ? ($totalAmt / $farmSize) : null;

                  $hasLedger  = $booking['ledger_id'] !== null;
                  $payStatus  = $hasLedger ? $booking['payment_status'] : null;
                  $payCount   = $hasLedger ? (int)$booking['payment_count'] : 0;

                  // What the Status column shows: payment status once a ledger exists, otherwise booking status
                  $statusLabel = $hasLedger ? $payStatus : $booking['status'];
                  $statusClass = strtolower($hasLedger ? $payStatus : $booking['status']);
              ?>
              <tr class="booking-row"
                  data-id="<?= $booking['id'] ?>"
                  data-status="<?= htmlspecialchars($booking['status']) ?>"
                  title="Double-click to view full details">
                  <td><?= $i++ ?></td>
                  <td class="fw600"><?= date('m/d/Y', strtotime($booking['created_at'])) ?></td>
                  <td class="fw700"><?= htmlspecialchars($booking['lot_number'] ?? '—') ?></td>
                  <td class="fw600"><?= number_format($farmSize, 2) ?> ha</td>
                  <td><?= htmlspecialchars($booking['type']) ?></td>
                  <td class="fw600"><?= htmlspecialchars($booking['machine_name']) ?></td>
                  <td><?= $rate !== null ? '₱' . number_format($rate, 2) : '—' ?></td>
                  <td class="fw700"><?= $totalAmt !== null ? '₱' . number_format($totalAmt, 2) : '—' ?></td>
                  <td class="fw600 clr-green"><?= $paidAmt !== null ? '₱' . number_format($paidAmt, 2) : '—' ?></td>
                  <td class="fw700 <?= ($balanceAmt !== null && $balanceAmt > 0) ? 'clr-red' : 'clr-green' ?>">
                      <?= $balanceAmt !== null ? '₱' . number_format($balanceAmt, 2) : '—' ?>
                  </td>
                  <td class="fw600"><?= date('m/d/Y', strtotime($booking['booking_date'])) ?></td>
                  <td>
                      <span class="status-badge status-<?= htmlspecialchars($statusClass) ?>">
                          <i class="fas fa-circle"></i> <?= htmlspecialchars($statusLabel) ?>
                      </span>
                      <?php if ($hasLedger && $payCount > 0): ?>
                          <div class="clr-muted"><?= $payCount ?> pay</div>
                      <?php endif; ?>
                  </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="action-buttons">
        <a href="tractor.php" class="btn-new-booking-bottom">
          <i class="fas fa-plus"></i> New Booking
        </a>
        <button id="btnCancelSelected" onclick="cancelSelectedBooking()" disabled>
          <i class="fas fa-times"></i> Cancel Booking
        </button>
      </div>
    <?php else: ?>
      <div class="empty-state">
        <i class="fas fa-calendar-times"></i>
        <h3>No Reservations Found</h3>
        <p>You don't have any <?= $status_filter === 'All' ? '' : strtolower($status_filter) ?> reservations yet.</p>
        <a href="tractor.php" class="btn-new-booking-bottom" style="background: var(--primary-color); color:white; padding:10px 22px; border-radius:6px; text-decoration:none; display:inline-flex; align-items:center; gap:8px; font-weight:600;">
          <i class="fas fa-plus"></i>
          Make Your First Booking
        </a>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Modal -->
<div id="bookingModal" class="modal">
  <div class="modal-content" id="modalContent">
    <div style="padding: 20px; text-align: center;">
      <i class="fas fa-spinner fa-spin" style="font-size: 32px; color: var(--primary-color);"></i>
      <p>Loading details...</p>
    </div>
  </div>
</div>

<script>
let selectedBooking = null;

function filterByStatus(status) {
  window.location.href = 'my_reservation.php?status=' + status;
}

/* ── Row select / double-click ── */
document.querySelectorAll('.booking-row').forEach(function (row) {
  row.addEventListener('click', function () {
    document.querySelectorAll('.booking-row').forEach(r => r.classList.remove('selected-row'));
    this.classList.add('selected-row');
    selectedBooking = { id: this.dataset.id, status: this.dataset.status };

    const cancelBtn = document.getElementById('btnCancelSelected');
    cancelBtn.disabled = (selectedBooking.status !== 'Pending');
  });

  row.addEventListener('dblclick', function () {
    document.querySelectorAll('.booking-row').forEach(r => r.classList.remove('selected-row'));
    this.classList.add('selected-row');
    selectedBooking = { id: this.dataset.id, status: this.dataset.status };
    openModal(this.dataset.id);
  });
});

function cancelSelectedBooking() {
  if (!selectedBooking || selectedBooking.status !== 'Pending') return;
  if (confirm('Are you sure you want to cancel this booking?')) {
    window.location.href = 'cancel_booking.php?id=' + selectedBooking.id;
  }
}

function openModal(bookingId) {
  const modal = document.getElementById('bookingModal');
  const modalContent = document.getElementById('modalContent');

  modal.classList.add('active');

  // Load booking details via AJAX
  fetch('booking_details_modal.php?id=' + bookingId)
    .then(response => response.text())
    .then(html => {
      modalContent.innerHTML = html;
    })
    .catch(error => {
      modalContent.innerHTML = '<div style="padding: 20px; text-align: center;"><i class="fas fa-exclamation-triangle" style="font-size: 32px; color: #ef4444;"></i><p>Error loading details</p></div>';
    });
}

function closeModal() {
  const modal = document.getElementById('bookingModal');
  modal.classList.remove('active');
}

// Close modal when clicking outside
window.onclick = function(event) {
  const modal = document.getElementById('bookingModal');
  if (event.target == modal) {
    closeModal();
  }
}

// Close modal on ESC key
document.addEventListener('keydown', function(event) {
  if (event.key === 'Escape') {
    closeModal();
  }
});
</script>

</body>
</html>

<?php include('../footer.php'); ?>