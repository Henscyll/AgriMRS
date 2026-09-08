<?php
session_start();
include('../includes/db_connection.php');

// ✅ Ensure association president is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'association president') {
    die("❌ Unauthorized. Please log in as association president.");
}

// ✅ Use real association_id from session (set during login)
if (!isset($_SESSION['association_id'])) {
    die("❌ Association ID not found in session. Please log in again.");
}

$association_id = $_SESSION['association_id']; 
$message = "";

// ✅ Handle Add Machine
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_machine'])) {
    $machine_name = trim($_POST['machine_name']);
    $machine_type = trim($_POST['machine_type']);
    $description  = trim($_POST['description']);
    $price        = trim($_POST['price']);

    // Handle Image Upload
    $image_path = "";
    if (isset($_FILES['machine_image']) && $_FILES['machine_image']['error'] === 0) {
        $image_name   = time() . "_" . basename($_FILES['machine_image']['name']);
        $target_dir   = "../uploads/machines/";
        $target_file  = $target_dir . $image_name;

        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        $allowedTypes  = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($imageFileType, $allowedTypes)) {
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            if (move_uploaded_file($_FILES['machine_image']['tmp_name'], $target_file)) {
                $image_path = "uploads/machines/" . $image_name;
            } else {
                $message = "⚠️ Failed to upload image.";
            }
        } else {
            $message = "⚠️ Invalid file type.";
        }
    }

    // ✅ Insert into DB
    $stmt = $conn->prepare("
        INSERT INTO agricultural_machines 
        (association_id, machine_name, machine_type, description, price, image_path) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("isssds", $association_id, $machine_name, $machine_type, $description, $price, $image_path);

    if ($stmt->execute()) {
        $message = "✅ Machine added successfully!";
    } else {
        $message = "❌ Error adding machine: " . $conn->error;
    }
}
?>

<?php include('../header.php'); ?>

<style>
    body {
        background-color: #f5f8f2;
        font-family: Arial, sans-serif;
    }
    h2 {
        color: #2d572c;
        font-weight: bold;
    }
    .top-bar {
        display: flex;
        justify-content: flex-start;
        margin-bottom: 15px;
    }
    .btn-back {
        background-color: #6c757d;
        color: white;
        border: none;
        padding: 6px 15px;
        border-radius: 5px;
        text-decoration: none;
    }
    .btn-back:hover {
        background-color: #5a6268;
        color: white;
    }
    form {
        background-color: #ffffff;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }
    .form-label {
        font-weight: bold;
    }
    .btn-success {
        background-color: #28a745;
        border: none;
    }
    .btn-success:hover {
        background-color: #218838;
    }
    .alert {
        padding: 10px;
        background-color: #e6f4ea;
        border-left: 4px solid #28a745;
        margin-bottom: 20px;
    }
</style>

<main class="container py-4">
    <!-- ✅ Back Button -->
    <div class="top-bar">
        <a href="association_agriculturalmachine.php" class="btn-back">⬅ Back</a>
    </div>

    <h2 class="mb-4">Add Agricultural Machine</h2>

    <?php if ($message): ?>
        <div class="alert"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="mb-4">
        <div class="mb-2">
            <label class="form-label">Machine Name</label>
            <input type="text" name="machine_name" class="form-control" required>
        </div>

        <div class="mb-2">
            <label class="form-label">Type</label>
            <select name="machine_type" class="form-select" required>
                <option value="Tractor">Tractor</option>
                <option value="Harvester">Harvester</option>
                <option value="Other">Other</option>
            </select>
        </div>

        <div class="mb-2">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" required></textarea>
        </div>

        <div class="mb-2">
            <label class="form-label">Price</label>
            <input type="number" step="0.01" name="price" class="form-control" required>
        </div>

        <div class="mb-2">
            <label class="form-label">Image</label>
            <input type="file" name="machine_image" class="form-control" accept="image/*">
        </div>

        <button type="submit" name="add_machine" class="btn btn-success">➕ Add Machine</button>
    </form>
</main>

<?php include('../footer.php'); ?>
