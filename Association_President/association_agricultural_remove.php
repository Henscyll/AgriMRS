<?php
session_start();
include('../includes/db_connection.php');

// ✅ Require login as association president
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'association president') {
    die("❌ Unauthorized access. Please log in as an association president.");
}

// ✅ Use user_id as association_id
if (!isset($_SESSION['user_id'])) {
    die("❌ Error: No association account found.");
}

$association_id = $_SESSION['user_id'];  // 🔑 use user_id for ownership

// ✅ Require machine id
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("❌ Error: No machine ID provided.");
}

$machine_id = intval($_GET['id']);

// ✅ Check if machine belongs to this association
$stmt = $conn->prepare("SELECT image_path FROM agricultural_machines WHERE id = ? AND association_id = ?");
$stmt->bind_param("ii", $machine_id, $association_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("❌ Error: Machine not found or you don’t have permission to delete it.");
}

$row = $result->fetch_assoc();
$image_path = $row['image_path'] ?? null;
$stmt->close();

// ✅ Delete machine
$delete_stmt = $conn->prepare("DELETE FROM agricultural_machines WHERE id = ? AND association_id = ?");
$delete_stmt->bind_param("ii", $machine_id, $association_id);

if ($delete_stmt->execute()) {
    // Optionally delete image file
    if ($image_path && file_exists("../" . $image_path)) {
        unlink("../" . $image_path);
    }

    // Redirect with success
    header("Location: association_agricultural_machine.php?msg=Machine+removed+successfully");
    exit;
} else {
    die("❌ Error: Failed to delete machine.");
}
?>
