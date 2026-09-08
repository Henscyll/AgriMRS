<?php
session_start();
include('dashboard_itadmin.php');
include('../includes/db_connection.php');

// ✅ Ensure IT Admin is logged in
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'it admin') {
    header("Location: ../login.php");
    exit;
}

// ✅ Get association ID from URL
$association_id = isset($_GET['association_id']) ? (int) $_GET['association_id'] : 0;
if ($association_id <= 0) {
    header("Location: tractor.php");
    exit;
}

// ✅ Fetch machines linked to this association
$sql = "
    SELECT am.*, a.name AS association_name
    FROM agricultural_machines am
    INNER JOIN associations a ON a.id = am.association_id
    WHERE a.id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $association_id);
$stmt->execute();
$machines = $stmt->get_result();

// ✅ Get association name
$association_name = "Association";
if ($machines->num_rows > 0) {
    $firstRow = $machines->fetch_assoc();
    $association_name = $firstRow['association_name'];
    // Rewind result pointer so we don’t lose first row
    $machines->data_seek(0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($association_name) ?> - Machines</title>
  <style>
    .back-btn {
      display: inline-block;
      margin-top: 10px;
      margin-bottom: 15px;
      padding: 8px 14px;
      background: #08611bff;
      color: white;
      border-radius: 5px;
      text-decoration: none;
    }
    .back-btn:hover {
      background: #0ac533cc;
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
  </style>
</head>
<body>

<!-- ✅ Back button outside the container -->
<a href="admin_machines.php" class="back-btn">← Back to Associations</a>

<div class="container">
  <h1>Machines for <?= htmlspecialchars($association_name) ?></h1>

  <table>
    <thead>
      <tr>
        <th>Image</th>
        <th>Machine Name</th>
        <th>Type</th>
        <th>Description</th>
        <th>Rate (₱)</th>
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
            <td><?= htmlspecialchars($row['machine_type']) ?></td>
            <td><?= htmlspecialchars($row['description']) ?></td>
            <td>
              <?php if (isset($row['price'])): ?>
                ₱<?= number_format($row['price'], 2) ?>
              <?php else: ?>
                N/A
              <?php endif; ?>
            </td>
          </tr>
        <?php endwhile; ?>
      <?php else: ?>
        <tr><td colspan="5">No machines found for this association.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
</body>
</html>
