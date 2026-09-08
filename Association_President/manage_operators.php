<?php
session_start();
require_once '../includes/db_connection.php';
include('dashboard_president.php');

if (!isset($_SESSION['association_id'])) {
    header("Location: ../login.php");
    exit;
}
$association_id = $_SESSION['association_id'];

$message = "";
$error = "";

/* ===============================
   ADD OPERATOR & CREATE USER ACCOUNT
================================ */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_operator'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $license = trim($_POST['license_number']);
    $password = $_POST['password'];
    
    // Validate
    if (empty($name) || empty($email) || empty($phone) || empty($password)) {
        $error = "All fields except license number are required!";
    } else {
        // Check if email already exists
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = "Email already registered!";
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // 1. Insert into users table
                $stmt1 = $conn->prepare(
                    "INSERT INTO users (name, email, password, user_role) 
                     VALUES (?, ?, ?, 'operator')"
                );
                $stmt1->bind_param("sss", $name, $email, $hashed_password);
                $stmt1->execute();
                $user_id = $conn->insert_id;
                $stmt1->close();
                
                // 2. Insert into operators table
                $stmt2 = $conn->prepare(
                    "INSERT INTO operators (user_id, association_id, name, email, phone, license_number, password, status) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, 'Active')"
                );
                $stmt2->bind_param("iisssss", $user_id, $association_id, $name, $email, $phone, $license, $hashed_password);
                $stmt2->execute();
                $stmt2->close();
                
                // Commit transaction
                $conn->commit();
                $message = "Operator registered successfully! They can now login.";
                
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Registration failed: " . $e->getMessage();
            }
        }
        $check->close();
    }
}

/* ===============================
   DELETE OPERATOR
================================ */
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    
    // Get user_id first
    $get_user = $conn->prepare("SELECT user_id FROM operators WHERE id = ? AND association_id = ?");
    $get_user->bind_param("ii", $delete_id, $association_id);
    $get_user->execute();
    $result = $get_user->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $user_id = $row['user_id'];
        
        // Delete from users table (cascade will delete from operators)
        $del_stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $del_stmt->bind_param("i", $user_id);
        $del_stmt->execute();
        $del_stmt->close();
        
        $message = "Operator deleted successfully!";
    }
    $get_user->close();
}

/* ===============================
   FETCH OPERATORS
================================ */
$stmt = $conn->prepare(
    "SELECT o.*, 
            COUNT(DISTINCT mo.machine_id) as assigned_machines
     FROM operators o
     LEFT JOIN machine_operators mo ON o.id = mo.operator_id AND mo.status = 'Active'
     WHERE o.association_id = ?
     GROUP BY o.id
     ORDER BY o.created_at DESC"
);
$stmt->bind_param("i", $association_id);
$stmt->execute();
$operators = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
<title>Manage Operators</title>
<style>
.main-content {
    padding: 20px;
}

.message {
    padding: 10px;
    margin: 10px 0;
    border-radius: 5px;
    text-align: center;
}

.success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.form-container {
    background: white;
    padding: 25px;
    border-radius: 8px;
    max-width: 600px;
    margin: 20px auto;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.form-container h3 {
    margin-top: 0;
    color: #2d7a2d;
    border-bottom: 2px solid #2d7a2d;
    padding-bottom: 10px;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
    color: #333;
}

.form-group input {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    box-sizing: border-box;
}

.btn {
    background: #2d7a2d;
    color: white;
    padding: 12px 25px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    width: 100%;
}

.btn:hover {
    background: #256725;
}

.table-container {
    background: white;
    padding: 20px;
    border-radius: 8px;
    margin-top: 30px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

table {
    width: 100%;
    border-collapse: collapse;
}

th, td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

th {
    background: #2d7a2d;
    color: white;
}

tr:hover {
    background: #f5f5f5;
}

.badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: bold;
}

.badge-active {
    background: #d4edda;
    color: #155724;
}

.badge-inactive {
    background: #f8d7da;
    color: #721c24;
}

.btn-delete {
    background: #dc3545;
    color: white;
    padding: 6px 12px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 13px;
}

.btn-delete:hover {
    background: #c82333;
}

.btn-back {
    background: #6c757d;
    color: white;
    padding: 10px 20px;
    text-decoration: none;
    border-radius: 4px;
    display: inline-block;
    margin-top: 20px;
}

.btn-back:hover {
    background: #5a6268;
}
</style>
</head>

<body>

<div class="main-content">
<h2 style="text-align:center; color:white;">Manage Operators</h2>

<?php if ($message): ?>
<div class="message success"><?= $message ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="message error"><?= $error ?></div>
<?php endif; ?>

<!-- REGISTRATION FORM -->
<div class="form-container">
<h3>Register New Operator</h3>
<form method="POST">
    <div class="form-group">
        <label>Full Name *</label>
        <input type="text" name="name" required>
    </div>
    
    <div class="form-group">
        <label>Email *</label>
        <input type="email" name="email" required>
    </div>
    
    <div class="form-group">
        <label>Phone Number *</label>
        <input type="text" name="phone" required pattern="[0-9]{11}" 
               placeholder="09XXXXXXXXX" maxlength="11">
    </div>
    
    <div class="form-group">
        <label>License Number</label>
        <input type="text" name="license_number" placeholder="Optional">
    </div>
    
    <div class="form-group">
        <label>Password *</label>
        <input type="password" name="password" required minlength="6">
        <small style="color:#666;">Minimum 6 characters</small>
    </div>
    
    <button type="submit" name="add_operator" class="btn">Register Operator</button>
</form>
</div>

<!-- OPERATORS TABLE -->
<div class="table-container">
<h3 style="color:#2d7a2d;">Registered Operators</h3>

<table>
<thead>
<tr>
    <th>#</th>
    <th>Name</th>
    <th>Email</th>
    <th>Phone</th>
    <th>License</th>
    <th>Machines</th>
    <th>Status</th>
    <th>Registered</th>
    <th>Action</th>
</tr>
</thead>
<tbody>
<?php if ($operators->num_rows > 0): ?>
    <?php $i = 1; while ($op = $operators->fetch_assoc()): ?>
    <tr>
        <td><?= $i++ ?></td>
        <td><?= htmlspecialchars($op['name']) ?></td>
        <td><?= htmlspecialchars($op['email']) ?></td>
        <td><?= htmlspecialchars($op['phone']) ?></td>
        <td><?= $op['license_number'] ? htmlspecialchars($op['license_number']) : 'N/A' ?></td>
        <td><?= $op['assigned_machines'] ?> machine(s)</td>
        <td>
            <span class="badge badge-<?= strtolower($op['status']) ?>">
                <?= $op['status'] ?>
            </span>
        </td>
        <td><?= date('M d, Y', strtotime($op['created_at'])) ?></td>
        <td>
            <button class="btn-delete" 
                    onclick="if(confirm('Delete this operator? This will also remove their login access.')) 
                             window.location.href='?delete_id=<?= $op['id'] ?>'">
                Delete
            </button>
        </td>
    </tr>
    <?php endwhile; ?>
<?php else: ?>
    <tr>
        <td colspan="9" align="center">No operators registered yet</td>
    </tr>
<?php endif; ?>
</tbody>
</table>
</div>

<div style="text-align:center;">
    <a href="association_agricultural.php" class="btn-back">← Back to Machines</a>
</div>

</div>

</body>
</html>