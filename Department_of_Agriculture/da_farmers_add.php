<?php
include('da_header.php');
require_once '../includes/config.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $farm_size = trim($_POST['farm_size']); 
    $province = trim($_POST['province']);
    $municipality = trim($_POST['municipality']);
    $barangay = trim($_POST['barangay']);
    $status = trim($_POST['status']);

    if ($name && $email && $phone && $farm_size && $province && $municipality && $barangay && $status) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Invalid email format.";
        } else {
            // Check if email already exists
            $checkStmt = $conn->prepare("SELECT id FROM farmers WHERE email = ?");
            $checkStmt->bind_param("s", $email);
            $checkStmt->execute();
            $checkStmt->store_result();
            if ($checkStmt->num_rows > 0) {
                $message = "Email already exists.";
            } else {
                $stmt = $conn->prepare("INSERT INTO farmers (name, email, phone, farm_size, province, municipality, barangay, status) 
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssssss", $name, $email, $phone, $farm_size, $province, $municipality, $barangay, $status);
                if ($stmt->execute()) {
                    $message = "Farmer added successfully!";
                } else {
                    $message = "Error: " . $stmt->error;
                }
                $stmt->close();
            }
            $checkStmt->close();
        }
    } else {
        $message = "Please fill in all fields.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Farmer</title>
    <link rel="stylesheet" href="../styles/farmers.css">
    <style>
        .main-content {
            background: #fff;
            max-width: 460px;
            margin: 40px auto;
            padding: 32px 28px;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(44, 62, 80, 0.08);
        }
        h2 {
            color: #2d7d46;
            margin-bottom: 22px;
            text-align: center;
            font-weight: 600;
        }
        .back-btn {
            display: inline-block;
            margin-bottom: 18px;
            padding: 9px 22px;
            background: linear-gradient(90deg, #e0e0e0 60%, #c8e6c9 100%);
            border-radius: 6px;
            text-decoration: none;
            color: #2d7d46;
            font-weight: 500;
            border: none;
            box-shadow: 0 2px 8px rgba(44, 62, 80, 0.06);
            transition: background 0.2s, color 0.2s;
        }
        .back-btn:hover {
            background: #2d7d46;
            color: #fff;
        }
        form label {
            display: block;
            margin-bottom: 13px;
            font-weight: 500;
            color: #34495e;
        }
        input[type="text"], input[type="email"], select {
            width: 100%;
            padding: 9px 12px;
            margin-top: 5px;
            border: 1px solid #bdbdbd;
            border-radius: 5px;
            box-sizing: border-box;
            font-size: 15px;
            background: #f7fafc;
            transition: border 0.2s;
        }
        input[type="text"]:focus, input[type="email"]:focus, select:focus {
            border: 1.5px solid #2d7d46;
            outline: none;
        }
        input[type="submit"] {
            background: linear-gradient(90deg, #2d7d46 60%, #388e3c 100%);
            color: #fff;
            border: none;
            padding: 12px 0;
            width: 100%;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 18px;
            box-shadow: 0 2px 8px rgba(44, 62, 80, 0.06);
            transition: background 0.2s;
        }
        input[type="submit"]:hover {
            background: #388e3c;
        }
        .alert {
            background: #ffebee;
            color: #c62828;
            padding: 11px;
            border-radius: 5px;
            margin-bottom: 17px;
            text-align: center;
            font-weight: 500;
            box-shadow: 0 2px 8px rgba(44, 62, 80, 0.04);
        }
    </style>
</head>
<body>

<a href="admin_farmers.php" class="back-btn" style="margin: 40px auto 0 40px; display: block; max-width: 120px;">&larr; Back</a>
<div class="main-content">
    <h2>Add Farmer</h2>
    <?php if ($message): ?>
        <div class="alert"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <form method="post">
        <label>Name: 
            <input type="text" name="name" placeholder="Enter your name" required>
        </label><br>
        <label>Email: 
            <input type="email" name="email" placeholder="Enter your email" required>
        </label><br>
        <label>Phone: 
            <input type="text" name="phone" placeholder="Enter phone number" required>
        </label><br>
        <label>Farm area size (in hectares): 
            <input type="text" name="farm_size" placeholder="Enter Farm Size" required>
        </label><br>
        <label>Province: 
            <input type="text" name="province" placeholder="Enter province" required>
        </label><br>
        <label>Municipality: 
            <input type="text" name="municipality" placeholder="Enter municipality" required>
        </label><br>
        <label>Barangay: 
            <input type="text" name="barangay" placeholder="Enter barangay" required>
        </label><br>
        <label>Status: 
            <select name="status" required>
                <option value="">-- Select Status --</option>
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
            </select>
        </label><br>
        <input type="submit" value="Add Farmer">
    </form>
</div>
</body>
</html>
