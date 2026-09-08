<?php
require_once '../includes/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    $association_id = isset($_GET['association_id']) ? intval($_GET['association_id']) : 0;
    
    if ($association_id <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Association ID parameter is required',
            'machines' => []
        ]);
        exit;
    }
    
    // Fetch machines for the given association
    $stmt = $conn->prepare("
        SELECT id, machine_name, type, status, price_per_hectare, description
        FROM machines
        WHERE association_id = ? AND status = 'Active'
        ORDER BY type ASC, machine_name ASC
    ");
    
    $stmt->bind_param("i", $association_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $machines = [];
    while ($row = $result->fetch_assoc()) {
        $machines[] = [
            'id' => $row['id'],
            'machine_name' => $row['machine_name'],
            'type' => $row['type'],
            'status' => $row['status'],
            'price_per_hectare' => $row['price_per_hectare'],
            'description' => $row['description']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'machines' => $machines,
        'count' => count($machines)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error',
        'error' => $e->getMessage(),
        'machines' => []
    ]);
}
?>