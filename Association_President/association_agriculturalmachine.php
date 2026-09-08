<?php
session_start();
include('../includes/db_connection.php');

// ✅ Always use the logged-in user as the association
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'association president') {
    die("❌ Unauthorized access. Please log in as an association president.");
}
$association_id = $_SESSION['user_id']; 
$message = "";

// Handle Add Machine
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_machine'])) {
    $machine_name = $_POST['machine_name'];
    $machine_type = $_POST['machine_type'];
    $description  = $_POST['description'];
    $price        = $_POST['price'];

    // Handle Image Upload
    $image_path = "";
    if (isset($_FILES['machine_image']) && $_FILES['machine_image']['error'] === 0) {
        $image_name   = time() . "_" . basename($_FILES['machine_image']['name']);
        $target_dir   = "../uploads/machines/";
        $target_file  = $target_dir . $image_name;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        $allowedTypes  = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($imageFileType, $allowedTypes)) {
            if (move_uploaded_file($_FILES['machine_image']['tmp_name'], $target_file)) {
                $image_path = "uploads/machines/" . $image_name;
            } else {
                $message = "Failed to upload image.";
            }
        } else {
            $message = "Invalid file type.";
        }
    }

    // Insert into DB
    $stmt = $conn->prepare("
        INSERT INTO agricultural_machines 
        (association_id, machine_name, machine_type, description, price, image_path) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("isssds", $association_id, $machine_name, $machine_type, $description, $price, $image_path);

    if ($stmt->execute()) {
        $message = "✅ Machine added successfully!";
    } else {
        $message = "❌ Error adding machine.";
    }
}

// Fetch Machines for this association only
$machines = [];
$stmt = $conn->prepare("SELECT * FROM agricultural_machines WHERE association_id = ?");
$stmt->bind_param("i", $association_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $machines[] = $row;
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
    .table {
        background-color: white;
        border: 1px solid #dee2e6;
        border-radius: 6px;
        overflow: hidden;
    }
    .table th {
        background-color: #4CAF50;
        color: white;
        text-align: center;
    }
    .table td, .table th {
        padding: 10px;
        text-align: center;
        vertical-align: middle;
    }
    .table img {
        width: 80px;
        height: auto;
        border-radius: 4px;
        border: 1px solid #ccc;
    }
    .clickable-row {
        cursor: pointer;
        transition: background-color 0.2s;
    }
    .clickable-row:hover {
        background-color: #e6f2ff;
    }
    .selected-row {
        background-color: #b3d7ff !important;
    }
    @media print {
        .no-print {
            display: none;
        }
    }
</style>

<main class="container py-4">
    <h5>Machines</h5>

    <?php if (!empty($message)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <!-- Table Container -->
    <div class="table-container">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Image</th>
                    <th>Machine Name</th>
                    <th>Type</th>
                    <th>Description</th>
                    <th>Price</th>
                    <th>Added On</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($machines as $machine): ?>
                    <tr class="clickable-row" data-id="<?= $machine['id'] ?>">
                        <td>
                            <?php if (!empty($machine['image_path'])): ?>
                                <img src="../<?= htmlspecialchars($machine['image_path']) ?>" alt="Machine Image">
                            <?php else: ?>
                                No Image
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($machine['machine_name']) ?></td>
                        <td><?= htmlspecialchars($machine['machine_type']) ?></td>
                        <td><?= htmlspecialchars($machine['description']) ?></td>
                        <td>₱<?= number_format($machine['price'], 2) ?></td>
                        <td><?= htmlspecialchars($machine['created_at'] ?? '') ?></td>
                        <td><?= htmlspecialchars($machine['status'] ?? 'Available') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Action Buttons -->
    <div class="mt-3 text-end no-print">
        <a href="association_agricultural_add.php" class="btn btn-success">➕ Add Machine</a>
        <button id="editBtn" class="btn btn-warning" disabled>✏️ Edit Machine</button>
        <button id="deleteBtn" class="btn btn-danger" disabled>🗑️ Remove Machine</button>
        <button type="button" class="btn btn-secondary" onclick="printTable()">🖨️ Print</button>
    </div>
</main>

<script>
function printTable() {
    const printContents = document.querySelector('.table-container').innerHTML;
    const originalContents = document.body.innerHTML;

    document.body.innerHTML = `
        <html>
        <head>
            <title>Print Machines</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
                th { background: #2d7a2d; color: white; }
                h2 { margin-bottom: 20px; text-align: center; }
                img { max-width: 80px; height: auto; }
            </style>
        </head>
        <body>
            <h2>List of Agricultural Machines</h2>
            ${printContents}
        </body>
        </html>
    `;
    window.print();
    document.body.innerHTML = originalContents;
    window.location.reload();
}

document.addEventListener("DOMContentLoaded", function () {
    const rows = document.querySelectorAll(".clickable-row");
    let selectedRow = null;
    let selectedId = null;

    rows.forEach(row => {
        row.addEventListener("click", function () {
            if (selectedRow) selectedRow.classList.remove("selected-row");
            this.classList.add("selected-row");
            selectedRow = this;
            selectedId = this.dataset.id;

            document.getElementById("editBtn").disabled = false;
            document.getElementById("deleteBtn").disabled = false;

            document.getElementById("editBtn").onclick = function() {
                window.location.href = "association_agricultural_edit.php?id=" + selectedId;
            };
            document.getElementById("deleteBtn").onclick = function() {
                if (confirm("Are you sure you want to delete this machine?")) {
                    window.location.href = "association_agricultural_remove.php?id=" + selectedId;
                }
            };
        });
    });
});
</script>

<?php include('../footer.php'); ?>
