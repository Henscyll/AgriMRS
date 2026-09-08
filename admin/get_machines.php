<?php
require_once '../includes/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    if (!isset($_GET['association_id']) || empty($_GET['association_id'])) {
        echo json_encode([
            'success' => false, 
            'message' => 'Association ID parameter is required',
            'machines' => []
        ]);
        exit;
    }

    $association_id = (int)$_GET['association_id'];

    // Check database connection
    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    // Query to get machines from the selected association
    $stmt = $conn->prepare("
        SELECT id, machine_name, type, status
        FROM machines 
        WHERE association_id = ? AND status = 'Active'
        ORDER BY type ASC, machine_name ASC
    ");
    
    if (!$stmt) {
        throw new Exception('Query preparation failed: ' . $conn->error);
    }

    $stmt->bind_param("i", $association_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Query execution failed: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $machines = [];
    
    while ($row = $result->fetch_assoc()) {
        $machines[] = [
            'id' => (int)$row['id'],
            'machine_name' => $row['machine_name'],
            'type' => $row['type'],
            'status' => $row['status']
        ];
    }

    $stmt->close();

    echo json_encode([
        'success' => true,
        'machines' => $machines,
        'count' => count($machines)
    ]);

} catch (Exception $e) {
    error_log('get_machines.php error: ' . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred',
        'error' => $e->getMessage(),
        'machines' => []
    ]);
}
?>