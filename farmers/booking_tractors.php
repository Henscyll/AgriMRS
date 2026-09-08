<?php
session_start();
include('farmers_header.php');
include('../includes/db_connection.php');

// ✅ Ensure farmer is logged in
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'farmer') {
    header("Location: ../login.php");
    exit;
}

$farmer_id = $_SESSION['user_id']; // logged in farmer’s user_id
$machine_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($machine_id <= 0) {
    header("Location: tractor.php");
    exit;
}

$message = "";

// ✅ Handle Booking Form
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['booking_date'])) {
    $booking_date = $_POST['booking_date'];

    // Check if already booked
    $stmt = $conn->prepare("SELECT * FROM bookings WHERE machine_id = ? AND booking_date = ?");
    $stmt->bind_param("is", $machine_id, $booking_date);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $message = "⚠️ Sorry, this tractor is already booked on " . htmlspecialchars($booking_date);
    } else {
        // Insert new booking
        $stmt = $conn->prepare("INSERT INTO bookings (machine_id, farmer_id, booking_date) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $machine_id, $farmer_id, $booking_date);

        if ($stmt->execute()) {
            $message = "✅ Successfully booked for " . htmlspecialchars($booking_date);
        } else {
            $message = "❌ Error: " . $conn->error;
        }
    }
}

// ✅ Fetch machine details
$stmt = $conn->prepare("SELECT * FROM agricultural_machines WHERE id = ?");
$stmt->bind_param("i", $machine_id);
$stmt->execute();
$machine = $stmt->get_result()->fetch_assoc();

// ✅ Fetch existing bookings
$stmt = $conn->prepare("SELECT booking_date FROM bookings WHERE machine_id = ? ORDER BY booking_date ASC");
$stmt->bind_param("i", $machine_id);
$stmt->execute();
$bookings = $stmt->get_result();
$booked_dates = [];
while ($row = $bookings->fetch_assoc()) {
    $booked_dates[] = $row['booking_date'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Book Tractor</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background: #f5f8f2;
      margin: 0;
      padding: 20px;
    }
    .container {
      max-width: 700px;
      margin: auto;
      background: #fff;
      padding: 20px;
      border-radius: 12px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    h2 {
      text-align: center;
      color: #2d572c;
    }
    .alert {
      margin-bottom: 15px;
      padding: 10px;
      background: #e6f4ea;
      border-left: 4px solid #28a745;
    }
    .form-group {
      margin: 15px 0;
    }
    label {
      font-weight: bold;
      display: block;
      margin-bottom: 6px;
    }
    input[type="date"], button {
      padding: 10px;
      width: 100%;
      border-radius: 6px;
      border: 1px solid #ccc;
    }
    button {
      background: #28a745;
      color: white;
      border: none;
      cursor: pointer;
    }
    button:hover {
      background: #218838;
    }
    .booked-list {
      margin-top: 20px;
    }
    .booked-list ul {
      list-style: none;
      padding: 0;
    }
    .booked-list li {
      background: #f1f8f5;
      padding: 8px;
      margin-bottom: 6px;
      border-radius: 6px;
    }
  </style>
</head>
<body>
<div class="container">
  <h2>Book Tractor: <?= htmlspecialchars($machine['machine_name']) ?></h2>

  <?php if ($message): ?>
    <div class="alert"><?= $message ?></div>
  <?php endif; ?>

  <form method="POST">
    <div class="form-group">
      <label for="booking_date">Select Date:</label>
      <input type="date" id="booking_date" name="booking_date" required 
             min="<?= date('Y-m-d') ?>">
    </div>
    <button type="submit">Confirm Booking</button>
  </form>

  <div class="booked-list">
    <h3>Already Booked Dates</h3>
    <?php if (!empty($booked_dates)): ?>
      <ul>
        <?php foreach ($booked_dates as $date): ?>
          <li><?= htmlspecialchars($date) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p>No bookings yet.</p>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
