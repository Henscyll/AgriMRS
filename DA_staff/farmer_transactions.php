<?php
include('dastaff_header.php');
require_once '../includes/config.php';

$farmer_id = $_GET['id'] ?? 0;

// Get farmer information
$farmer_sql = "SELECT * FROM farmers WHERE id = ?";
$farmer_stmt = $conn->prepare($farmer_sql);
$farmer_stmt->bind_param("i", $farmer_id);
$farmer_stmt->execute();
$farmer_result = $farmer_stmt->get_result();
$farmer = $farmer_result->fetch_assoc();

if (!$farmer) {
    header("Location: staff_farmers.php");
    exit();
}

// Filter parameters
$status_filter = $_GET['status'] ?? 'all';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query with filters
$where_conditions = ["b.farmer_id = ?"];
$params = [$farmer_id];
$param_types = "i";

if ($status_filter !== 'all') {
    $where_conditions[] = "b.status = ?";
    $params[] = $status_filter;
    $param_types .= "s";
}

if ($date_from) {
    $where_conditions[] = "b.booking_date >= ?";
    $params[] = $date_from;
    $param_types .= "s";
}

if ($date_to) {
    $where_conditions[] = "b.booking_date <= ?";
    $params[] = $date_to;
    $param_types .= "s";
}

$where_clause = implode(" AND ", $where_conditions);

// Get all bookings/transactions
$bookings_sql = "SELECT b.*, m.machine_name, m.type as machine_type, 
                 fl.lot_number, fl.farm_location, fl.farm_size,
                 a.name as association_name
                 FROM bookings b
                 LEFT JOIN machines m ON b.machine_id = m.id
                 LEFT JOIN farmer_lots fl ON b.lot_id = fl.id
                 LEFT JOIN associations a ON m.association_id = a.id
                 WHERE $where_clause
                 ORDER BY b.created_at DESC";

$bookings_stmt = $conn->prepare($bookings_sql);
$bookings_stmt->bind_param($param_types, ...$params);
$bookings_stmt->execute();
$bookings_result = $bookings_stmt->get_result();
$bookings = $bookings_result->fetch_all(MYSQLI_ASSOC);

// Calculate statistics
$total_bookings = count($bookings);
$completed = count(array_filter($bookings, fn($b) => $b['status'] === 'Completed'));
$pending = count(array_filter($bookings, fn($b) => $b['status'] === 'Pending'));
$approved = count(array_filter($bookings, fn($b) => $b['status'] === 'Approved'));
$cancelled = count(array_filter($bookings, fn($b) => $b['status'] === 'Cancelled'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Transaction History - <?= htmlspecialchars($farmer['name']) ?></title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.css">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      overflow: hidden;
    }

    .main-content {
      margin-top: 120px;
      padding: 20px;
      max-width: 1400px;
      margin-left: auto;
      margin-right: auto;
      padding-bottom: 60px;
      height: calc(100vh - 150px);
      overflow-y: auto;
      overflow-x: hidden;
    }

    .main-content::-webkit-scrollbar {
      width: 12px;
    }
    .main-content::-webkit-scrollbar-track {
      background: transparent;
    }

    .main-content::-webkit-scrollbar-thumb {
      background: white;
      border-radius: 10px;
      border: 2px solid #f0f0f0;
      box-shadow: 0 2px 6px rgba(0,0,0,0.2);
    }

    .main-content::-webkit-scrollbar-thumb:hover {
      background: #f8f8f8;
      box-shadow: 0 2px 8px rgba(0,0,0,0.3);
    }
    /* Firefox Scrollbar */
    .main-content {
      scrollbar-width: thin;
      scrollbar-color: white transparent;
    }

    .back-nav {
      margin-bottom: 20px;
    }

    .btn-back {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 20px;
      background: white;
      color: #2d7d46;
      text-decoration: none;
      border-radius: 8px;
      font-weight: 500;
      box-shadow: 0 2px 8px rgba(0,0,0,0.08);
      transition: all 0.2s;
      border: 1px solid #e0e0e0;
    }

    .btn-back:hover {
      background: #2d7d46;
      color: white;
      transform: translateX(-3px);
    }

    .page-header {
      background: white;
      border-radius: 12px;
      padding: 20px 25px;
      margin-bottom: 20px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }

    .page-header h1 {
      font-size: 1.6rem;
      color: #333;
      margin-bottom: 4px;
    }

    .page-subtitle {
      color: #666;
      font-size: 0.9rem;
    }

    /* Uniform White Statistics Cards */
    .stats-grid {
      display: flex;
      gap: 12px;
      margin-bottom: 20px;
      flex-wrap: wrap;
    }

    .stat-card {
      background: white;
      padding: 14px 18px;
      border-radius: 10px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.06);
      flex: 1;
      min-width: 140px;
      display: flex;
      align-items: center;
      gap: 12px;
      transition: all 0.3s ease;
      border-left: 4px solid #2d7d46;
    }

    .stat-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }

    /* All cards have green border */
    .stat-card.total {
      border-left-color: #2d7d46;
    }

    .stat-card.completed {
      border-left-color: #28a745;
    }

    .stat-card.pending {
      border-left-color: #ffc107;
    }

    .stat-card.approved {
      border-left-color: #17a2b8;
    }

    .stat-card.cancelled {
      border-left-color: #dc3545;
    }

    .stat-icon {
      width: 42px;
      height: 42px;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.2rem;
      flex-shrink: 0;
      /* All icons have white background with colored text */
      background: #f8f9fa;
    }

    .stat-card.total .stat-icon {
      color: #2d7d46;
    }

    .stat-card.completed .stat-icon {
      color: #28a745;
    }

    .stat-card.pending .stat-icon {
      color: #ffc107;
    }

    .stat-card.approved .stat-icon {
      color: #17a2b8;
    }

    .stat-card.cancelled .stat-icon {
      color: #dc3545;
    }

    .stat-content {
      flex: 1;
    }

    .stat-value {
      font-size: 1.6rem;
      font-weight: 700;
      color: #333;
      line-height: 1;
      margin-bottom: 4px;
    }

    .stat-label {
      font-size: 0.75rem;
      color: #666;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      font-weight: 600;
    }

    .filters-card {
      background: white;
      border-radius: 12px;
      padding: 20px;
      margin-bottom: 20px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }

    .filters-header {
      font-size: 1.05rem;
      font-weight: 600;
      color: #333;
      margin-bottom: 15px;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .filters-form {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 12px;
      align-items: end;
    }

    .form-group {
      display: flex;
      flex-direction: column;
      gap: 6px;
    }

    .form-group label {
      font-size: 0.8rem;
      font-weight: 600;
      color: #666;
    }

    .form-group select,
    .form-group input {
      padding: 9px 12px;
      border: 2px solid #e0e0e0;
      border-radius: 8px;
      font-size: 0.85rem;
      transition: border-color 0.2s;
    }

    .form-group select:focus,
    .form-group input:focus {
      outline: none;
      border-color: #2d7d46;
    }

    .btn {
      padding: 9px 18px;
      border: none;
      border-radius: 8px;
      font-size: 0.85rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s;
    }

    .btn-primary {
      background: #2d7d46;
      color: white;
    }

    .btn-primary:hover {
      background: #256725;
    }

    .btn-secondary {
      background: #f5f5f5;
      color: #666;
      border: 1px solid #e0e0e0;
    }

    .btn-secondary:hover {
      background: #e0e0e0;
    }

    .transactions-card {
      background: white;
      border-radius: 12px;
      padding: 20px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }

    .transactions-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
      padding-bottom: 15px;
      border-bottom: 2px solid #f0f0f0;
    }

    .transactions-title {
      font-size: 1.1rem;
      font-weight: 600;
      color: #333;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    /* Transaction table with scrollbar */
    .transaction-table-container {
      max-height: 500px;
      overflow-y: auto;
      border: 1px solid #e0e0e0;
      border-radius: 8px;
      margin-top: 15px;
    }

    /* Custom scrollbar for transaction table */
    .transaction-table-container::-webkit-scrollbar {
      width: 8px;
    }

    .transaction-table-container::-webkit-scrollbar-track {
      background: #ffffff;
      border-radius: 4px;
    }

    .transaction-table-container::-webkit-scrollbar-thumb {
      background: #000000;
      border-radius: 4px;
    }

    .transaction-table-container::-webkit-scrollbar-thumb:hover {
      background: #333333;
    }

    .transaction-table {
      width: 100%;
      border-collapse: collapse;
      background: white;
    }

    .transaction-table thead {
      position: sticky;
      top: 0;
      background: #2d7d46;
      z-index: 10;
    }

    .transaction-table th {
      padding: 12px 15px;
      text-align: left;
      font-size: 0.85rem;
      font-weight: 600;
      color: white;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      white-space: nowrap;
      border-bottom: 2px solid #256725;
    }

    .transaction-table tbody tr {
      border-bottom: 1px solid #e0e0e0;
      transition: background 0.2s;
    }

    .transaction-table tbody tr:hover {
      background: #f0f8f1;
    }

    .transaction-table td {
      padding: 14px 15px;
      font-size: 0.85rem;
      color: #333;
      vertical-align: top;
    }

    .transaction-status {
      padding: 6px 14px;
      border-radius: 20px;
      font-size: 0.85rem;
      font-weight: 600;
      white-space: nowrap;
      display: inline-block;
    }

    .status-completed {
      background: #d4edda;
      color: #155724;
    }

    .status-pending {
      background: #fff3cd;
      color: #856404;
    }

    .status-approved {
      background: #d1ecf1;
      color: #0c5460;
    }

    .status-cancelled {
      background: #f8d7da;
      color: #721c24;
    }

    .empty-state {
      text-align: center;
      padding: 60px 20px;
      color: #999;
    }

    .empty-state .icon {
      font-size: 4rem;
      margin-bottom: 20px;
      opacity: 0.5;
    }

    .result-count {
      font-size: 0.85rem;
      color: #666;
      margin-bottom: 15px;
      font-weight: 500;
    }

    /* Black & White Scrollbar for main page */
    ::-webkit-scrollbar {
      width: 8px;
      height: 8px;
    }

    ::-webkit-scrollbar-track {
      background: #ffffff;
      border-radius: 4px;
    }

    ::-webkit-scrollbar-thumb {
      background: #000000;
      border-radius: 4px;
    }

    ::-webkit-scrollbar-thumb:hover {
      background: #333333;
    }

    @media (max-width: 768px) {
      .main-content {
        margin-top: 80px;
      }

      .stats-grid {
        flex-direction: column;
      }

      .stat-card {
        min-width: 100%;
      }

      .filters-form {
        grid-template-columns: 1fr;
      }

      .transaction-table-container {
        max-height: 400px;
      }
    }

    @media (max-width: 480px) {
      .stats-grid {
        gap: 10px;
      }

      .stat-card {
        padding: 12px 14px;
      }

      .stat-icon {
        width: 38px;
        height: 38px;
        font-size: 1rem;
      }

      .stat-value {
        font-size: 1.4rem;
      }
    }
  </style>
</head>
<body>
  <div class="main-content">
    <div class="back-nav">
      <a href="staff_farmers_profile_updated.php?id=<?= $farmer_id ?>" class="btn-back">
        <i class="fas fa-arrow-left"></i> Back to Profile
      </a>
    </div>

    <div class="page-header">
      <h1>Transaction History</h1>
      <p class="page-subtitle">
        Booking records for <strong><?= htmlspecialchars($farmer['name']) ?></strong>
      </p>
    </div>

    <!-- Uniform White Statistics -->
    <div class="stats-grid">
      <div class="stat-card total">
        <div class="stat-icon">
          <i class="fas fa-clipboard-list"></i>
        </div>
        <div class="stat-content">
          <div class="stat-value"><?= $total_bookings ?></div>
          <div class="stat-label">Total</div>
        </div>
      </div>

      <div class="stat-card completed">
        <div class="stat-icon">
          <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-content">
          <div class="stat-value"><?= $completed ?></div>
          <div class="stat-label">Completed</div>
        </div>
      </div>

      <div class="stat-card pending">
        <div class="stat-icon">
          <i class="fas fa-clock"></i>
        </div>
        <div class="stat-content">
          <div class="stat-value"><?= $pending ?></div>
          <div class="stat-label">Pending</div>
        </div>
      </div>

      <div class="stat-card approved">
        <div class="stat-icon">
          <i class="fas fa-thumbs-up"></i>
        </div>
        <div class="stat-content">
          <div class="stat-value"><?= $approved ?></div>
          <div class="stat-label">Approved</div>
        </div>
      </div>

      <div class="stat-card cancelled">
        <div class="stat-icon">
          <i class="fas fa-times-circle"></i>
        </div>
        <div class="stat-content">
          <div class="stat-value"><?= $cancelled ?></div>
          <div class="stat-label">Cancelled</div>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="filters-card">
      <div class="filters-header">
        <i class="fas fa-filter"></i> Filter Transactions
      </div>
      <form method="GET" action="" class="filters-form">
        <input type="hidden" name="id" value="<?= $farmer_id ?>">
        
        <div class="form-group">
          <label><i class="fas fa-tag"></i> Status</label>
          <select name="status">
            <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
            <option value="Pending" <?= $status_filter === 'Pending' ? 'selected' : '' ?>>Pending</option>
            <option value="Approved" <?= $status_filter === 'Approved' ? 'selected' : '' ?>>Approved</option>
            <option value="Completed" <?= $status_filter === 'Completed' ? 'selected' : '' ?>>Completed</option>
            <option value="Cancelled" <?= $status_filter === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
          </select>
        </div>

        <div class="form-group">
          <label><i class="fas fa-calendar"></i> Date From</label>
          <input type="text" id="from_date_display" placeholder="mm/dd/yyyy" readonly>
          <input type="hidden" name="date_from" id="from_date" value="<?= htmlspecialchars($date_from) ?>">
        </div>

        <div class="form-group">
          <label><i class="fas fa-calendar"></i> Date To</label>
          <input type="text" id="to_date_display" placeholder="mm/dd/yyyy" readonly>
          <input type="hidden" name="date_to" id="to_date" value="<?= htmlspecialchars($date_to) ?>">
        </div>

        <div class="form-group">
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-search"></i> Apply
          </button>
        </div>

        <div class="form-group">
          <a href="?id=<?= $farmer_id ?>" class="btn btn-secondary">
            <i class="fas fa-redo"></i> Clear
          </a>
        </div>
      </form>
    </div>

    <!-- Transactions Table -->
    <div class="transactions-card">
      <div class="transactions-header">
        <div class="transactions-title">
          <i class="fas fa-file-alt"></i> Transaction Records
        </div>
      </div>

      <?php if (count($bookings) > 0): ?>
        <div class="result-count">
          <i class="fas fa-info-circle"></i> Showing <?= count($bookings) ?> transaction(s)
        </div>
        
        <div class="transaction-table-container">
          <table class="transaction-table">
            <thead>
              <tr>
                <th>Booking Date</th>
                <th>Machine Name</th>
                <th>Type</th>
                <th>Lot Number</th>
                <th>Location</th>
                <th>Farm Size</th>
                <th>Association</th>
                <th>Status</th>
                <th>Created</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($bookings as $booking): ?>
                <tr>
                  <td><?= date('M d, Y', strtotime($booking['booking_date'])) ?></td>
                  <td><strong><?= htmlspecialchars($booking['machine_name'] ?? 'N/A') ?></strong></td>
                  <td><?= htmlspecialchars($booking['machine_type'] ?? 'N/A') ?></td>
                  <td><?= htmlspecialchars($booking['lot_number'] ?? 'N/A') ?></td>
                  <td><?= htmlspecialchars($booking['farm_location'] ?? 'N/A') ?></td>
                  <td><?= $booking['farm_size'] ? number_format($booking['farm_size'], 2) . ' ha' : 'N/A' ?></td>
                  <td><?= htmlspecialchars($booking['association_name'] ?? 'N/A') ?></td>
                  <td>
                    <span class="transaction-status status-<?= strtolower($booking['status']) ?>">
                      <?= htmlspecialchars($booking['status']) ?>
                    </span>
                  </td>
                  <td><?= date('M d, Y', strtotime($booking['created_at'])) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="empty-state">
          <div class="icon"><i class="fas fa-inbox"></i></div>
          <h3>No Transactions Found</h3>
          <p>
            <?php if ($status_filter !== 'all' || $date_from || $date_to): ?>
              No transactions match your filter criteria. Try adjusting your filters.
            <?php else: ?>
              This farmer hasn't made any bookings yet.
            <?php endif; ?>
          </p>
        </div>
      <?php endif; ?>
    </div>
  </div>

      <script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.js"></script>
    <script>
    window.fpFrom = flatpickr('#from_date_display', {
        dateFormat: 'm/d/Y',
        allowInput: false,
        onChange: function(selectedDates) {
            if (selectedDates.length) {
                const d = selectedDates[0];
                const ymd = d.getFullYear() + '-'
                    + String(d.getMonth()+1).padStart(2,'0') + '-'
                    + String(d.getDate()).padStart(2,'0');
                document.getElementById('from_date').value = ymd;
            } else {
                document.getElementById('from_date').value = '';
            }
        }
    });

    window.fpTo = flatpickr('#to_date_display', {
        dateFormat: 'm/d/Y',
        allowInput: false,
        onChange: function(selectedDates) {
            if (selectedDates.length) {
                const d = selectedDates[0];
                const ymd = d.getFullYear() + '-'
                    + String(d.getMonth()+1).padStart(2,'0') + '-'
                    + String(d.getDate()).padStart(2,'0');
                document.getElementById('to_date').value = ymd;
            } else {
                document.getElementById('to_date').value = '';
            }
        }
    });

    // Pre-fill display if PHP passed back a date value
    <?php if ($date_from): ?>
    window.fpFrom.setDate('<?= htmlspecialchars($date_from) ?>', true, 'Y-m-d');
    <?php endif; ?>
    <?php if ($date_to): ?>
    window.fpTo.setDate('<?= htmlspecialchars($date_to) ?>', true, 'Y-m-d');
    <?php endif; ?>
    </script>

</body>
</html>