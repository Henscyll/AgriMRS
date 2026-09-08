<?php
include('dashboard_itadmin.php');
require_once '../includes/config.php';

$id = $_GET['id'] ?? 0;
$message = "";

// ✅ Fetch farmer data
$sql = "SELECT * FROM farmers WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$farmer = $result->fetch_assoc();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $farm_size = $_POST['farm_size'] ?? '';
    $barangay = $_POST['barangay'] ?? '';
    $municipality = $_POST['municipality'] ?? '';
    $province = $_POST['province'] ?? '';
    $region = $_POST['region'] ?? '';
    $status = $_POST['status'] ?? '';

    $update_sql = "UPDATE farmers 
                   SET name=?, email=?, phone=?, farm_size=?, barangay=?, municipality=?, province=?, region=?, status=? 
                   WHERE id=?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("sssssssssi", 
        $name, $email, $phone, $farm_size, $barangay, $municipality, $province, $region, $status, $id
    );

    if ($update_stmt->execute()) {
        $message = "✅ Farmer updated successfully!";
        // Refresh farmer data
        $stmt = $conn->prepare("SELECT * FROM farmers WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $farmer = $stmt->get_result()->fetch_assoc();
    } else {
        $message = "❌ Error updating farmer.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Farmer</title>
  <style>
    .form-container {
      max-width: 700px;
      margin: 30px auto;
      padding: 20px;
      background: #fff;
      border-radius: 10px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    h2 {
      color: #2d7a2d;
      margin-bottom: 20px;
    }
    .form-group {
      margin-bottom: 15px;
    }
    label {
      display: block;
      font-weight: bold;
      margin-bottom: 6px;
    }
    input, select {
      width: 100%;
      padding: 8px;
      border: 1px solid #ccc;
      border-radius: 5px;
    }
    .btn {
      display: inline-block;
      margin-top: 15px;
      padding: 8px 14px;
      background: #2d7a2d;
      color: white;
      text-decoration: none;
      border-radius: 5px;
      border: none;
      cursor: pointer;
    }
    .btn:hover {
      background: #256526;
    }
    .message {
      margin-bottom: 15px;
      color: green;
      font-weight: bold;
    }
  </style>
</head>
<body>
  <div class="form-container">
    <h2>Edit Farmer</h2>
    <?php if ($message): ?>
      <div class="message"><?= $message ?></div>
    <?php endif; ?>

    <?php if ($farmer): ?>
    <form method="post">
      <div class="form-group">
        <label>Full Name</label>
        <input type="text" name="name" value="<?= htmlspecialchars($farmer['name']) ?>" required>
      </div>
      <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" value="<?= htmlspecialchars($farmer['email']) ?>" required>
      </div>
      <div class="form-group">
        <label>Phone</label>
        <input type="text" name="phone" value="<?= htmlspecialchars($farmer['phone']) ?>" required>
      </div>
      <div class="form-group">
        <label>Farm Area Size</label>
        <input type="text" name="farm_size" value="<?= htmlspecialchars($farmer['farm_size']) ?>">
      </div>
      <div class="form-group">
        <label>Barangay</label>
        <input type="text" name="barangay" value="<?= htmlspecialchars($farmer['barangay']) ?>">
      </div>
      <div class="form-group">
        <label>Municipality</label>
        <input type="text" name="municipality" value="<?= htmlspecialchars($farmer['municipality']) ?>">
      </div>
      <div class="form-group">
        <label>Province</label>
        <input type="text" name="province" value="<?= htmlspecialchars($farmer['province']) ?>">
      </div>
      <div class="form-group">
        <label>Status</label>
        <select name="status">
          <option value="active" <?= $farmer['status'] === 'active' ? 'selected' : '' ?>>Active</option>
          <option value="inactive" <?= $farmer['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
      </div>
      <button type="submit" class="btn">💾 Save Changes</button>
      <a href="farmer_profile.php?id=<?= $id ?>" class="btn">⬅ Back</a>
    </form>
    <?php else: ?>
      <p>Farmer not found.</p>
      <a href="admin_farmers.php" class="btn">⬅ Back</a>
    <?php endif; ?>
  </div>
</body>
</html>
