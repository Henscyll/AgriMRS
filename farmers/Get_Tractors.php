<?php
session_start();
require_once '../includes/config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$association_id = isset($_GET['association_id']) ? intval($_GET['association_id']) : 0;

if ($association_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid association ID']);
    exit();
}

// Fetch tractors for this association
$query = "SELECT id, machine_name, type, image_path, description, status 
          FROM machines 
          WHERE association_id = ? AND type = 'Tractor' AND status = 'Active'
          ORDER BY machine_name ASC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $association_id);
$stmt->execute();
$result = $stmt->get_result();

$tractors = [];
while ($row = $result->fetch_assoc()) {
    $tractors[] = $row;
}

echo json_encode([
    'success' => true,
    'tractors' => $tractors
]);
?>