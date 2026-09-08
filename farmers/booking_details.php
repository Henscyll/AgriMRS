<?php
session_start();
require_once '../includes/db_connection.php';
include('farmers_header.php');

// Check if user is logged in and is a farmer
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'farmer') {
    header("Location: ../login.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: my_reservation.php");
    exit;
}

$booking_id = (int)$_GET['id'];
$farmer_id = $_SESSION['user_id'];

// Get farmer table ID
$farmer_query = "SELECT id FROM farmers WHERE user_id = ?";
$farmer_stmt = $conn->prepare($farmer_query);
$farmer_stmt->bind_param("i", $farmer_id);
$farmer_stmt->execute();
$farmer_result = $farmer_stmt->get_result();
$farmer_data = $farmer_result->fetch_assoc();
$farmer_table_id = $farmer_data['id'];
$farmer_stmt->close();

// Fetch booking details
$query = "SELECT b.*, 
                 m.machine_name, 
                 m.type, 
                 m.image_path,
                 m.description as machine_description,
                 m.price_per_hectare,
                 a.name AS association_name,
                 a.phone AS association_phone,
                 a.email AS association_email,
                 a.municipality,
                 a.province,
                 a.barangay,
                 fl.lot_number,
                 fl.farm_location,
                 fl.farm_size as lot_farm_size
          FROM bookings b
          INNER JOIN machines m ON b.machine_id = m.id
          INNER JOIN associations a ON m.association_id = a.id
          LEFT JOIN farmer_lots fl ON b.lot_id = fl.id
          WHERE b.id = ? AND b.farmer_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $booking_id, $farmer_table_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error_message'] = "Booking not found.";
    header("Location: my_reservation.php");
    exit;
}

$booking = $result->fetch_assoc();
$stmt->close();

// Get payment ledger if exists
$payment_query = "SELECT * FROM payment_ledger WHERE booking_id = ?";
$payment_stmt = $conn->prepare($payment_query);
$payment_stmt->bind_param("i", $booking_id);
$payment_stmt->execute();
$payment_result = $payment_stmt->get_result();
$payment_ledger = $payment_result->num_rows > 0 ? $payment_result->fetch_assoc() : null;
$payment_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Booking Details</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      padding-bottom: 30px;
    }

    .container {
      max-width: 900px;
      margin: 0 auto;
      padding: 15px;
    }

    .back-button {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 8px 12px;
      background: #6b7280;
      color: white;
      text-decoration: none;
      border-radius: 6px;
      font-size: 14px;
      margin-bottom: 15px;
      transition: all 0.3s;
    }

    .back-button:hover {
      background: #4b5563;
    }

    .booking-detail-card {
      background: white;
      border-radius: 8px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
      overflow: hidden;
      margin-bottom: 15px;
    }

    .card-header {
      background: linear-gradient(135deg, #16a34a, #15803d);
      color: white;
      padding: 15px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .card-header h2 {
      font-size: 20px;
      margin: 0;
    }

    .status-badge {
      padding: 6px 14px;
      border-radius: 16px;
      font-size: 12px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .status-pending {
      background: #fef3c7;
      color: #92400e;
    }

    .status-approved {
      background: #dbeafe;
      color: #1e40af;
    }

    .status-completed {
      background: #d1fae5;
      color: #065f46;
    }

    .status-cancelled {
      background: #fee2e2;
      color: #991b1b;
    }

    .card-body {
      padding: 20px;
    }

    .machine-section {
      display: grid;
      grid-template-columns: 150px 1fr;
      gap: 20px;
      margin-bottom: 20px;
      padding-bottom: 20px;
      border-bottom: 2px solid #f3f4f6;
    }

    .machine-image {
      width: 150px;
      height: 150px;
      border-radius: 8px;
      object-fit: cover;
    }

    .machine-info h3 {
      font-size: 22px;
      color: #1f2937;
      margin-bottom: 8px;
    }

    .machine-type {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 4px 10px;
      background: #dcfce7;
      color: #15803d;
      border-radius: 12px;
      font-size: 13px;
      font-weight: 600;
      margin-bottom: 10px;
    }

    .machine-description {
      color: #6b7280;
      font-size: 14px;
      line-height: 1.6;
    }

    .info-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 15px;
      margin-bottom: 20px;
    }

    .info-item {
      background: #f9fafb;
      padding: 12px;
      border-radius: 6px;
      border-left: 3px solid #16a34a;
    }

    .info-label {
      font-size: 12px;
      color: #6b7280;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 4px;
    }

    .info-value {
      font-size: 15px;
      color: #1f2937;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .info-value i {
      color: #16a34a;
    }

    .section-title {
      font-size: 16px;
      color: #1f2937;
      font-weight: 600;
      margin-bottom: 12px;
      padding-bottom: 8px;
      border-bottom: 2px solid #e5e7eb;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .section-title i {
      color: #16a34a;
    }

    .payment-info {
      background: #fef3c7;
      border: 1px solid #fbbf24;
      padding: 15px;
      border-radius: 8px;
      margin-top: 20px;
    }

    .payment-info.paid {
      background: #d1fae5;
      border-color: #10b981;
    }

    .payment-row {
      display: flex;
      justify-content: space-between;
      margin-bottom: 8px;
      font-size: 14px;
    }

    .payment-row.total {
      font-size: 18px;
      font-weight: 700;
      padding-top: 8px;
      border-top: 2px solid #d97706;
    }

    .payment-info.paid .payment-row.total {
      border-top-color: #10b981;
    }

    .action-buttons {
      display: flex;
      gap: 10px;
      margin-top: 20px;
      flex-wrap: wrap;
    }

    .btn {
      padding: 10px 18px;
      border-radius: 6px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s;
      font-size: 14px;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      border: none;
    }

    .btn-cancel {
      background: #dc2626;
      color: white;
    }

    .btn-cancel:hover {
      background: #b91c1c;
    }

    .btn-print {
      background: #6b7280;
      color: white;
    }

    .btn-print:hover {
      background: #4b5563;
    }

    @media (max-width: 768px) {
      .machine-section {
        grid-template-columns: 1fr;
        text-align: center;
      }

      .machine-image {
        margin: 0 auto;
      }

      .info-grid {
        grid-template-columns: 1fr;
      }

      .action-buttons {
        flex-direction: column;
      }

      .btn {
        width: 100%;
        justify-content: center;
      }
    }

    @media print {
      .back-button,
      .action-buttons {
        display: none;
      }
    }
  </style>
</head>
<body>

<div class="container">
  <a href="my_reservation.php" class="back-button">
    <i class="fas fa-arrow-left"></i>
    Back to Reservations
  </a>

  <div class="booking-detail-card">
    <div class="card-header">
      <h2><i class="fas fa-file-alt"></i> Booking Details</h2>
      <span class="status-badge status-<?= strtolower($booking['status']) ?>">
        <?= htmlspecialchars($booking['status']) ?>
      </span>
    </div>

    <div class="card-body">
      <!-- Machine Section -->
      <div class="machine-section">
        <img src="<?= htmlspecialchars($booking['image_path'] ?? '../images/default-tractor.jpg') ?>" 
             alt="<?= htmlspecialchars($booking['machine_name']) ?>" 
             class="machine-image">
        
        <div class="machine-info">
          <h3><?= htmlspecialchars($booking['machine_name']) ?></h3>
          <span class="machine-type">
            <i class="fas fa-<?= $booking['type'] === 'Tractor' ? 'tractor' : 'cogs' ?>"></i>
            <?= htmlspecialchars($booking['type']) ?>
          </span>
          <?php if ($booking['machine_description']): ?>
            <p class="machine-description"><?= htmlspecialchars($booking['machine_description']) ?></p>
          <?php endif; ?>
        </div>
      </div>

      <!-- Booking Information -->
      <div class="section-title">
        <i class="fas fa-info-circle"></i>
        Booking Information
      </div>

      <div class="info-grid">
        <div class="info-item">
          <div class="info-label">Booking Date</div>
          <div class="info-value">
            <i class="fas fa-calendar"></i>
            <?= date('F d, Y', strtotime($booking['booking_date'])) ?>
          </div>
        </div>

        <div class="info-item">
          <div class="info-label">Created On</div>
          <div class="info-value">
            <i class="fas fa-clock"></i>
            <?= date('M d, Y h:i A', strtotime($booking['created_at'])) ?>
          </div>
        </div>

        <?php if ($booking['lot_number']): ?>
        <div class="info-item">
          <div class="info-label">Lot Number</div>
          <div class="info-value">
            <i class="fas fa-map-pin"></i>
            <?= htmlspecialchars($booking['lot_number']) ?>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($booking['farm_location']): ?>
        <div class="info-item">
          <div class="info-label">Farm Location</div>
          <div class="info-value">
            <i class="fas fa-map-marker-alt"></i>
            <?= htmlspecialchars($booking['farm_location']) ?>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($booking['farm_size']): ?>
        <div class="info-item">
          <div class="info-label">Farm Size</div>
          <div class="info-value">
            <i class="fas fa-ruler-combined"></i>
            <?= number_format($booking['farm_size'], 2) ?> hectares
          </div>
        </div>
        <?php endif; ?>

        <?php if ($booking['price_per_hectare']): ?>
        <div class="info-item">
          <div class="info-label">Price per Hectare</div>
          <div class="info-value">
            <i class="fas fa-peso-sign"></i>
            ₱<?= number_format($booking['price_per_hectare'], 2) ?>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <!-- Association Information -->
      <div class="section-title">
        <i class="fas fa-users"></i>
        Association Details
      </div>

      <div class="info-grid">
        <div class="info-item">
          <div class="info-label">Association Name</div>
          <div class="info-value">
            <i class="fas fa-building"></i>
            <?= htmlspecialchars($booking['association_name']) ?>
          </div>
        </div>

        <div class="info-item">
          <div class="info-label">Location</div>
          <div class="info-value">
            <i class="fas fa-location-dot"></i>
            <?= htmlspecialchars($booking['municipality'] . ', ' . $booking['province']) ?>
          </div>
        </div>

        <div class="info-item">
          <div class="info-label">Phone</div>
          <div class="info-value">
            <i class="fas fa-phone"></i>
            <?= htmlspecialchars($booking['association_phone']) ?>
          </div>
        </div>

        <?php if ($booking['association_email']): ?>
        <div class="info-item">
          <div class="info-label">Email</div>
          <div class="info-value">
            <i class="fas fa-envelope"></i>
            <?= htmlspecialchars($booking['association_email']) ?>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <!-- Payment Information -->
      <?php if ($payment_ledger): ?>
        <div class="section-title">
          <i class="fas fa-money-bill-wave"></i>
          Payment Information
        </div>

        <div class="payment-info <?= $payment_ledger['payment_status'] === 'Paid' ? 'paid' : '' ?>">
          <div class="payment-row">
            <span>Total Amount:</span>
            <strong>₱<?= number_format($payment_ledger['total_amount'], 2) ?></strong>
          </div>
          <div class="payment-row">
            <span>Amount Paid:</span>
            <strong style="color: #10b981;">₱<?= number_format($payment_ledger['amount_paid'], 2) ?></strong>
          </div>
          <div class="payment-row total">
            <span>Balance:</span>
            <strong style="color: <?= $payment_ledger['payment_status'] === 'Paid' ? '#10b981' : '#dc2626' ?>;">
              ₱<?= number_format($payment_ledger['balance'], 2) ?>
            </strong>
          </div>
          <div class="payment-row" style="margin-top: 10px;">
            <span>Due Date:</span>
            <strong><?= date('F d, Y', strtotime($payment_ledger['due_date'])) ?></strong>
          </div>
          <div class="payment-row">
            <span>Status:</span>
            <strong><?= htmlspecialchars($payment_ledger['payment_status']) ?></strong>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($booking['notes']): ?>
      <div class="section-title" style="margin-top: 20px;">
        <i class="fas fa-sticky-note"></i>
        Notes
      </div>
      <div style="background: #f9fafb; padding: 12px; border-radius: 6px; color: #6b7280;">
        <?= nl2br(htmlspecialchars($booking['notes'])) ?>
      </div>
      <?php endif; ?>

      <!-- Action Buttons -->
      <div class="action-buttons">
        <?php if ($booking['status'] === 'Pending'): ?>
          <button class="btn btn-cancel" onclick="cancelBooking(<?= $booking['id'] ?>)">
            <i class="fas fa-times"></i>
            Cancel Booking
          </button>
        <?php endif; ?>
        <button class="btn btn-print" onclick="window.print()">
          <i class="fas fa-print"></i>
          Print Details
        </button>
      </div>
    </div>
  </div>
</div>

<script>
function cancelBooking(bookingId) {
  if (confirm('Are you sure you want to cancel this booking? This action cannot be undone.')) {
    window.location.href = 'cancel_booking.php?id=' + bookingId;
  }
}
</script>

</body>
</html>

<?php include('../footer.php'); ?>