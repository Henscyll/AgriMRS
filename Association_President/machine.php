<?php
session_start();
include('../includes/db_connection.php');

// Simulate session if not set
$association_id = $_SESSION['association_id'] ?? 1;

$message = "";
$error = "";

// Handle Add Machine
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_machine'])) {
    $machine_name = $_POST['machine_name'];
    $machine_type = $_POST['machine_type'];
    $description = $_POST['description'];

    // Handle Image Upload
    $image_path = "";
    if (isset($_FILES['machine_image']) && $_FILES['machine_image']['error'] === 0) {
        $image_name = time() . "_" . basename($_FILES['machine_image']['name']);
        $target_dir = "../uploads/";
        $target_file = $target_dir . $image_name;

        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($imageFileType, $allowedTypes)) {
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            if (move_uploaded_file($_FILES['machine_image']['tmp_name'], $target_file)) {
                $image_path = "../uploads/" . $image_name;
            } else {
                $error = "Failed to upload image.";
            }
        } else {
            $error = "Invalid file type. Only JPG, JPEG, PNG & GIF allowed.";
        }
    }

    // Insert into DB
    if (empty($error)) {
        $stmt = $conn->prepare("INSERT INTO machines (machine_name, type, description, image_path, association_id, status) VALUES (?, ?, ?, ?, ?, 'Active')");
        $stmt->bind_param("ssssi", $machine_name, $machine_type, $description, $image_path, $association_id);

        if ($stmt->execute()) {
            $message = "Machine added successfully!";
        } else {
            $error = "Error adding machine: " . $conn->error;
        }
        $stmt->close();
    }
}

// Handle Assign Operator
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['assign_operator'])) {
    $machine_id = $_POST['machine_id'];
    $operator_id = $_POST['operator_id'];

    // Check if already assigned
    $check_stmt = $conn->prepare("SELECT id FROM machine_operators WHERE machine_id = ? AND operator_id = ? AND status = 'Active'");
    $check_stmt->bind_param("ii", $machine_id, $operator_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows > 0) {
        $error = "This operator is already assigned to this machine!";
    } else {
        $stmt = $conn->prepare("INSERT INTO machine_operators (machine_id, operator_id, status) VALUES (?, ?, 'Active')");
        $stmt->bind_param("ii", $machine_id, $operator_id);

        if ($stmt->execute()) {
            $message = "Operator assigned successfully!";
        } else {
            $error = "Error assigning operator: " . $conn->error;
        }
        $stmt->close();
    }
    $check_stmt->close();
}

// Handle Remove Operator Assignment
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['remove_operator'])) {
    $assignment_id = $_POST['assignment_id'];

    $stmt = $conn->prepare("DELETE FROM machine_operators WHERE id = ?");
    $stmt->bind_param("i", $assignment_id);

    if ($stmt->execute()) {
        $message = "Operator removed from machine successfully!";
    } else {
        $error = "Error removing operator: " . $conn->error;
    }
    $stmt->close();
}

// Fetch Machines with assigned operators
$machines_query = "
    SELECT 
        m.*,
        GROUP_CONCAT(
            CONCAT(o.name, ' (', o.phone, ')') 
            SEPARATOR ', '
        ) as assigned_operators,
        GROUP_CONCAT(mo.id SEPARATOR ',') as assignment_ids
    FROM machines m
    LEFT JOIN machine_operators mo ON m.id = mo.machine_id AND mo.status = 'Active'
    LEFT JOIN operators o ON mo.operator_id = o.id
    WHERE m.association_id = ?
    GROUP BY m.id
    ORDER BY m.created_at DESC
";
$stmt = $conn->prepare($machines_query);
$stmt->bind_param("i", $association_id);
$stmt->execute();
$machines_result = $stmt->get_result();
$machines = [];
while ($row = $machines_result->fetch_assoc()) {
    $machines[] = $row;
}
$stmt->close();

// Fetch available operators for this association
$operators_query = "SELECT o.* FROM operators o WHERE o.association_id = ? AND o.status = 'Active' ORDER BY o.name";
$stmt = $conn->prepare($operators_query);
$stmt->bind_param("i", $association_id);
$stmt->execute();
$operators_result = $stmt->get_result();
$operators = [];
while ($row = $operators_result->fetch_assoc()) {
    $operators[] = $row;
}
$stmt->close();
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
    .card {
        background-color: #ffffff;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        margin-bottom: 20px;
    }
    .form-label {
        font-weight: bold;
        color: #333;
    }
    .btn-success {
        background-color: #28a745;
        border: none;
    }
    .btn-success:hover {
        background-color: #218838;
    }
    .btn-primary {
        background-color: #007bff;
        border: none;
    }
    .btn-primary:hover {
        background-color: #0056b3;
    }
    .alert-success {
        background-color: #d4edda;
        border-left: 4px solid #28a745;
        color: #155724;
        padding: 12px;
        margin-bottom: 20px;
        border-radius: 4px;
    }
    .alert-danger {
        background-color: #f8d7da;
        border-left: 4px solid #dc3545;
        color: #721c24;
        padding: 12px;
        margin-bottom: 20px;
        border-radius: 4px;
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
        padding: 12px;
    }
    .table td {
        padding: 12px;
        text-align: center;
        vertical-align: middle;
    }
    .table img {
        width: 80px;
        height: 80px;
        object-fit: cover;
        border-radius: 4px;
        border: 1px solid #ccc;
    }
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0,0,0,0.5);
    }
    .modal-content {
        background-color: #fefefe;
        margin: 5% auto;
        padding: 20px;
        border: 1px solid #888;
        border-radius: 10px;
        width: 90%;
        max-width: 600px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    }
    .modal-header {
        border-bottom: 2px solid #4CAF50;
        padding-bottom: 10px;
        margin-bottom: 20px;
    }
    .modal-header h3 {
        color: #2d572c;
        margin: 0;
    }
    .close {
        color: #aaa;
        float: right;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
    }
    .close:hover,
    .close:focus {
        color: #000;
    }
    .operator-badge {
        background-color: #e7f4e7;
        color: #2d572c;
        padding: 4px 8px;
        border-radius: 4px;
        display: inline-block;
        margin: 2px;
        font-size: 0.9em;
    }
    .btn-assign {
        background-color: #17a2b8;
        color: white;
        border: none;
        padding: 6px 12px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 0.9em;
    }
    .btn-assign:hover {
        background-color: #138496;
    }
    .assigned-operators-list {
        max-height: 200px;
        overflow-y: auto;
        margin-top: 10px;
    }
    .operator-item {
        background-color: #f8f9fa;
        padding: 10px;
        margin-bottom: 8px;
        border-radius: 4px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .no-operators {
        color: #999;
        font-style: italic;
        padding: 10px;
        text-align: center;
    }
</style>

<main class="container py-4">
    <h2 class="mb-4">Manage Agricultural Machines</h2>

    <?php if ($message): ?>
        <div class="alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Add Machine Form -->
    <div class="card">
        <h5 class="mb-3">Add New Machine</h5>
        <form method="POST" enctype="multipart/form-data">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Machine Name</label>
                    <input type="text" name="machine_name" class="form-control" required>
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Type</label>
                    <select name="machine_type" class="form-select" required>
                        <option value="Tractor">Tractor</option>
                        <option value="Harvester">Harvester</option>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="3" required></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label">Machine Image</label>
                <input type="file" name="machine_image" class="form-control" accept="image/*">
                <small class="text-muted">Accepted formats: JPG, JPEG, PNG, GIF</small>
            </div>

            <button type="submit" name="add_machine" class="btn btn-success">
                <i class="fas fa-plus"></i> Add Machine
            </button>
        </form>
    </div>

    <!-- Machines List -->
    <div class="card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Existing Machines</h5>
            <span class="badge bg-success"><?= count($machines) ?> Machine(s)</span>
        </div>

        <?php if (count($machines) > 0): ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Machine Name</th>
                            <th>Type</th>
                            <th>Description</th>
                            <th>Assigned Operators</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($machines as $machine): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($machine['image_path'])): ?>
                                        <img src="<?= htmlspecialchars($machine['image_path']) ?>" alt="Machine Image">
                                    <?php else: ?>
                                        <div style="width:80px;height:80px;background:#eee;display:flex;align-items:center;justify-content:center;border-radius:4px;">
                                            No Image
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?= htmlspecialchars($machine['machine_name']) ?></strong></td>
                                <td>
                                    <span class="badge bg-info"><?= htmlspecialchars($machine['type']) ?></span>
                                </td>
                                <td style="text-align: left; max-width: 250px;">
                                    <?= htmlspecialchars($machine['description']) ?>
                                </td>
                                <td style="max-width: 200px;">
                                    <?php if (!empty($machine['assigned_operators'])): ?>
                                        <div class="operator-badge">
                                            <?= htmlspecialchars($machine['assigned_operators']) ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">No operators assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-success"><?= htmlspecialchars($machine['status']) ?></span>
                                </td>
                                <td>
                                    <button class="btn btn-assign btn-sm" onclick="openAssignModal(<?= $machine['id'] ?>, '<?= htmlspecialchars($machine['machine_name']) ?>')">
                                        <i class="fas fa-user-plus"></i> Assign Operator
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-tractor fa-3x text-muted mb-3"></i>
                <p class="text-muted">No machines added yet. Add your first machine above!</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Action Buttons -->
    <div class="mt-3 text-end">
        <a href="manage_operators.php" class="btn btn-primary">
            <i class="fas fa-users"></i> Manage Operators
        </a>
        <a href="association_agricultural_edit.php" class="btn btn-warning">
            <i class="fas fa-edit"></i> Edit Machines
        </a>
        <a href="association_agricultural_remove.php" class="btn btn-danger">
            <i class="fas fa-trash"></i> Remove Machines
        </a>
    </div>
</main>

<!-- Assign Operator Modal -->
<div id="assignOperatorModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Assign Operator to Machine</h3>
            <span class="close" onclick="closeAssignModal()">&times;</span>
        </div>
        <form method="POST" id="assignOperatorForm">
            <input type="hidden" name="machine_id" id="modal_machine_id">
            
            <div class="mb-3">
                <strong>Machine:</strong> <span id="modal_machine_name"></span>
            </div>

            <div class="mb-3">
                <label class="form-label">Select Operator</label>
                <select name="operator_id" class="form-select" required>
                    <option value="">-- Choose an operator --</option>
                    <?php foreach ($operators as $operator): ?>
                        <option value="<?= $operator['id'] ?>">
                            <?= htmlspecialchars($operator['name']) ?> 
                            (<?= htmlspecialchars($operator['phone']) ?>) 
                            - <?= $operator['experience_years'] ?> years exp.
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if (count($operators) == 0): ?>
                <div class="alert-danger">
                    No operators available. Please add operators first in the 
                    <a href="manage_operators.php">Manage Operators</a> section.
                </div>
            <?php endif; ?>

            <div class="d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-secondary" onclick="closeAssignModal()">Cancel</button>
                <button type="submit" name="assign_operator" class="btn btn-success" <?= count($operators) == 0 ? 'disabled' : '' ?>>
                    Assign Operator
                </button>
            </div>
        </form>

        <!-- Currently Assigned Operators -->
        <div class="mt-4">
            <h6>Currently Assigned Operators:</h6>
            <div class="assigned-operators-list" id="assignedOperatorsList">
                <div class="no-operators">Loading...</div>
            </div>
        </div>
    </div>
</div>

<script>
// Modal Functions
function openAssignModal(machineId, machineName) {
    document.getElementById('modal_machine_id').value = machineId;
    document.getElementById('modal_machine_name').textContent = machineName;
    document.getElementById('assignOperatorModal').style.display = 'block';
    
    // Load assigned operators
    loadAssignedOperators(machineId);
}

function closeAssignModal() {
    document.getElementById('assignOperatorModal').style.display = 'none';
}

function loadAssignedOperators(machineId) {
    fetch(`get_assigned_operators.php?machine_id=${machineId}`)
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('assignedOperatorsList');
            if (data.operators && data.operators.length > 0) {
                container.innerHTML = data.operators.map(op => `
                    <div class="operator-item">
                        <div>
                            <strong>${op.name}</strong><br>
                            <small>${op.phone} | ${op.experience_years} years exp.</small>
                        </div>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="assignment_id" value="${op.assignment_id}">
                            <button type="submit" name="remove_operator" class="btn btn-sm btn-danger" 
                                    onclick="return confirm('Remove this operator from the machine?')">
                                <i class="fas fa-times"></i> Remove
                            </button>
                        </form>
                    </div>
                `).join('');
            } else {
                container.innerHTML = '<div class="no-operators">No operators assigned yet</div>';
            }
        })
        .catch(error => {
            console.error('Error loading operators:', error);
            document.getElementById('assignedOperatorsList').innerHTML = 
                '<div class="alert-danger">Error loading operators</div>';
        });
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('assignOperatorModal');
    if (event.target == modal) {
        closeAssignModal();
    }
}
</script>

<?php include('../footer.php'); ?>