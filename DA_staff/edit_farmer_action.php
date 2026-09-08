<?php
session_start();
require_once '../includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['da staff', 'department of agriculture', 'it admin'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

$id           = intval($_POST['id'] ?? 0);
$phone        = $conn->real_escape_string(trim($_POST['phone']        ?? ''));
$municipality = $conn->real_escape_string(trim($_POST['municipality'] ?? ''));
$barangay     = $conn->real_escape_string(trim($_POST['barangay']     ?? ''));
$assoc_id     = isset($_POST['association_id']) && $_POST['association_id'] !== '' ? intval($_POST['association_id']) : 0;

if (!$id) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid farmer ID.']);
    exit;
}

$conn->begin_transaction();

try {
    // Correctly updates association_id without removing it
    $assocVal = $assoc_id > 0 ? $assoc_id : 'NULL';
    $conn->query("
        UPDATE farmers SET
            phone          = '$phone',
            municipality   = '$municipality',
            barangay       = '$barangay',
            association_id = $assocVal
        WHERE id = $id
    ");

    if (!empty($_POST['new_lots']) && is_array($_POST['new_lots'])) {
        foreach ($_POST['new_lots'] as $lot) {
            $lot_number    = $conn->real_escape_string(trim($lot['lot_number']    ?? ''));
            $farm_size     = floatval($lot['farm_size']   ?? 0);
            $province      = $conn->real_escape_string(trim($lot['province']      ?? 'Zamboanga Del Sur'));
            $municipality2 = $conn->real_escape_string(trim($lot['municipality']  ?? ''));
            $barangay2     = $conn->real_escape_string(trim($lot['barangay']      ?? ''));
            $farm_location = $conn->real_escape_string(trim($lot['farm_location'] ?? ''));

            if ($lot_number === '' || $farm_size <= 0 || $municipality2 === '' || $barangay2 === '' || $farm_location === '') {
                continue;
            }

            $conn->query("
                INSERT INTO farmer_lots 
                    (farmer_id, lot_number, farm_location, farm_size, province, municipality, barangay, association_id, status, created_at)
                VALUES 
                    ($id, '$lot_number', '$farm_location', $farm_size, '$province', '$municipality2', '$barangay2', $assocVal, 'Active', NOW())
            ");
        }
    }

    if (!empty($_POST['lot_status']) && is_array($_POST['lot_status'])) {
        $stmt_lot = $conn->prepare("UPDATE farmer_lots SET status = ? WHERE id = ? AND farmer_id = ?");
        foreach ($_POST['lot_status'] as $lot_id => $status) {
            $lot_id_int = intval($lot_id);
            $status_str = in_array($status, ['Active', 'Inactive']) ? $status : 'Active';
            $stmt_lot->bind_param("sii", $status_str, $lot_id_int, $id);
            $stmt_lot->execute();
        }
        $stmt_lot->close();
    }

    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => 'Farmer profile updated successfully.']);
    exit;

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => 'Update failed: ' . $e->getMessage()]);
    exit;
}