<?php
session_start();
include('farmers_header.php');
include('../includes/db_connection.php');

// ✅ Ensure farmer is logged in
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'farmer') {
    header("Location: ../login.php");
    exit;
}

// ✅ Get association ID from URL
$association_id = isset($_GET['association_id']) ? (int) $_GET['association_id'] : 0;
if ($association_id <= 0) {
    header("Location: tractor.php");
    exit;
}

// ✅ Fetch tractors linked to this association
$sql = "
    SELECT am.* 
    FROM agricultural_machines am
    INNER JOIN associations a ON a.id = am.association_id
    WHERE a.id = ? AND am.machine_type = 'Tractor'
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $association_id);
$stmt->execute();
$machines = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Association Tractors</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background: #f5f8f2;
      margin: 0;
      padding: 20px;
    }
    .back-btn {
      display: inline-block;
      margin-top: 10px;
      margin-bottom: 15px;
      padding: 8px 14px;
      background: #6c757d;
      color: white;
      border-radius: 5px;
      text-decoration: none;
    }
    .back-btn:hover {
      background: #5a6268;
    }
    .container {
      max-width: 1000px;
      margin: 0 auto;
      padding: 20px;
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    h1 {
      text-align: center;
      color: #2d572c;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 20px;
    }
    th, td {
      border: 1px solid #ccc;
      padding: 12px;
      text-align: center;
    }
    th {
      background: #2d572c;
      color: white;
    }
    tr:nth-child(even) {
      background: #f1f8f5;
    }
    .machine-img {
      width: 100px;
      border-radius: 8px;
    }
    .book-btn {
      background: #2196F3;
      color: white;
      padding: 6px 12px;
      border-radius: 5px;
      text-decoration: none;
    }
    .book-btn:hover {
      background: #1976D2;
    }
  </style>
</head>
<body>

<!-- ✅ Back button outside the container -->
<a href="tractor.php" class="back-btn">← Back to Associations</a>

<div class="container">
  <h1>Available Tractors</h1>

  <table>
    <thead>
      <tr>
        <th>Image</th>
        <th>Machine Name</th>
        <th>Description</th>
        <th>Rate (₱)</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($machines->num_rows > 0): ?>
        <?php while ($row = $machines->fetch_assoc()): ?>
          <tr>
            <td>
              <?php if (!empty($row['image_path'])): ?>
                <img src="../<?= htmlspecialchars($row['image_path']) ?>" class="machine-img" alt="Machine">
              <?php else: ?>
                No Image
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($row['machine_name']) ?></td>
            <td><?= htmlspecialchars($row['description']) ?></td>
            <td>₱<?= number_format($row['price'], 2) ?></td>
            <td>
              <!-- ✅ Redirect to bookingtractors.php -->
              <a href="booking_tractors.php?id=<?= $row['id'] ?>" class="book-btn">Book</a>
            </td>
          </tr>
        <?php endwhile; ?>
      <?php else: ?>
        <tr><td colspan="5">No tractors found for this association.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
</body>
</html>
