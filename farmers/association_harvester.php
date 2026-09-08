<?php
session_start();
include('../includes/db_connection.php');

// ✅ Ensure farmer is logged in
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'farmer') {
    header("Location: ../login.php");
    exit;
}

// ✅ Get association president ID from URL
$association_id = isset($_GET['association_id']) ? (int) $_GET['association_id'] : 0;
if ($association_id <= 0) {
    header("Location: tractor.php"); // back to associations list
    exit;
}

// ✅ Fetch tractors for this association president
$stmt = $conn->prepare("SELECT * FROM agricultural_machines WHERE association_id = ? AND machine_type = 'Harvester'");
$stmt->bind_param("i", $association_id);
$stmt->execute();
$tractors = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Association Tractors</title>
  <style>
    body {
      font-family: 'Roboto', Arial, sans-serif;
      background: linear-gradient(120deg, #e8f5e9 0%, #f1f8e9 100%);
      margin: 0;
      padding: 0;
    }
    .page-container {
      max-width: 1000px;
      margin: 40px auto;
      padding: 30px 20px;
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 6px 24px rgba(45,122,45,0.12);
    }
    h1 {
      text-align: center;
      color: #2d7a2d;
      margin-bottom: 20px;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      background: #f9fff9;
      border-radius: 10px;
      overflow: hidden;
      border: 2px solid #388e3c;
    }
    th, td {
      padding: 14px 18px;
      text-align: center;
      border: 1px solid #b2dfdb;
      vertical-align: middle;
    }
    th {
      background: linear-gradient(90deg, #2d7a2d 80%, #388e3c 100%);
      color: #fff;
    }
    tr:nth-child(even) td {
      background: #f1f8e9;
    }
    tr:hover td {
      background: #e0f2f1;
      transition: background 0.2s;
    }
    .equipment-type {
      font-weight: 700;
      color: #2d7a2d;
    }
    .rate {
      color: #388e3c;
      font-weight: 700;
    }
    .back-btn {
      display: inline-block;
      margin-bottom: 20px;
      background: #4CAF50;
      color: white;
      padding: 8px 14px;
      border-radius: 6px;
      text-decoration: none;
    }
    .back-btn:hover {
      background: #388e3c;
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
    .machine-img {
      width: 100px;
      height: auto;
      border-radius: 8px;
      border: 1px solid #ccc;
    }
  </style>
</head>
<body>
<div class="page-container">
  <a href="tractor.php" class="back-btn">← Back to Associations</a>
  <h1>Available Tractors</h1>
  <?php
  // ✅ Fetch ONLY tractors of this association
  $sql = "SELECT am.* 
          FROM agricultural_machines am 
          WHERE am.association_id = ? AND am.machine_type = 'Tractor'";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("i", $association_id);
  $stmt->execute();
  $machines = $stmt->get_result();
  ?>
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
            <td class="equipment-type"><?= htmlspecialchars($row['machine_name']) ?></td>
            <td><?= htmlspecialchars($row['description']) ?></td>
            <td class="rate">₱<?= number_format($row['price'], 2) ?></td>
            <td>
              <a href="book_machine.php?id=<?= $row['id'] ?>" class="book-btn">Book</a>
            </td>
          </tr>
        <?php endwhile; ?>
      <?php else: ?>
        <tr><td colspan="5" style="text-align:center;">No tractors found for this association.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
</body>
</html>
<?php include('../footer.php'); ?>
