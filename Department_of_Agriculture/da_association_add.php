<?php
include('da_header .php'); 
require_once '../includes/config.php';

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $region = trim($_POST['region']);
    $province = trim($_POST['province']);
    $municipality = trim($_POST['municipality']);
    $barangay = trim($_POST['barangay']);
    $address = trim($_POST['address']);
    $password = password_hash('association123', PASSWORD_DEFAULT); // Default password

    // ✅ 1. Check if email already exists in users (to prevent duplicate logins)
    $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $checkStmt->bind_param("s", $email);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows > 0) {
        $message = "❌ Email already exists in the system!";
    } else {
        // ✅ 2. Insert into associations table
        $stmt = $conn->prepare("INSERT INTO associations 
            (name, email, phone, password, region, province, municipality, barangay, address)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssss", $name, $email, $phone, $password, $region, $province, $municipality, $barangay, $address);

        if ($stmt->execute()) {
            // ✅ 3. Also insert into users table for login
            $user_stmt = $conn->prepare("INSERT INTO users (name, email, password, user_role) VALUES (?, ?, ?, 'association')");
            $user_stmt->bind_param("sss", $name, $email, $password);
            $user_stmt->execute();
            $user_stmt->close();

            $message = "✅ Association added successfully! (Default password: association123)";
        } else {
            $message = "❌ Error adding association: " . $stmt->error;
        }

        $stmt->close();
    }

    $checkStmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Association | Department of Agriculture</title>
    <style>
        .main-content {
            margin: 40px auto;
            width: 80%;
            background: #fff;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 3px 8px rgba(0,0,0,0.2);
            font-family: Arial, sans-serif;
        }

        .main-content h2 {
            text-align: center;
            color: #228B22;
            margin-bottom: 25px;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.2);
        }

        form {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            justify-content: space-between;
        }

        label {
            width: 100%;
            font-weight: bold;
            color: #333;
        }

        input, textarea, select {
            width: 100%;
            padding: 10px;
            border-radius: 6px;
            border: 1px solid #ccc;
            box-sizing: border-box;
        }

        .half {
            width: 48%;
        }

        textarea {
            resize: none;
        }

        .submit-btn {
            background-color: #228B22;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 12px 20px;
            cursor: pointer;
            font-weight: bold;
            width: 100%;
            margin-top: 20px;
            transition: background 0.3s;
        }

        .submit-btn:hover {
            background-color: #1e7b1e;
        }

        .message {
            text-align: center;
            margin-bottom: 20px;
            font-weight: bold;
            color: #b30000;
        }

        .success {
            color: green;
        }
    </style>
</head>
<body>
    <div class="main-content">
        <h2>Add New Association</h2>

        <?php if ($message): ?>
            <div class="message <?php echo (strpos($message, '✅') !== false) ? 'success' : ''; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST">
            <div class="half">
                <label for="name">Association Name</label>
                <input type="text" name="name" id="name" required>
            </div>

            <div class="half">
                <label for="email">Email Address</label>
                <input type="email" name="email" id="email" required>
            </div>

            <div class="half">
                <label for="phone">Phone Number</label>
                <input type="text" name="phone" id="phone" required>
            </div>

            <div class="half">
                <label for="region">Region</label>
                <input type="text" name="region" id="region" required>
            </div>

            <div class="half">
                <label for="province">Province</label>
                <input type="text" name="province" id="province" required>
            </div>

            <div class="half">
                <label for="municipality">Municipality</label>
                <input type="text" name="municipality" id="municipality" required>
            </div>

            <div class="half">
                <label for="barangay">Barangay</label>
                <input type="text" name="barangay" id="barangay" required>
            </div>

            <div style="width: 100%;">
                <label for="address">Address</label>
                <textarea name="address" id="address" rows="3" required></textarea>
            </div>

            <button type="submit" class="submit-btn">Add Association</button>
        </form>
    </div>
</body>
</html>
