<?php
require_once '../includes/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    if (!isset($_GET['municipality']) || empty($_GET['municipality'])) {
        echo json_encode([
            'success' => false, 
            'message' => 'Municipality parameter is required',
            'associations' => []
        ]);
        exit;
    }

    $municipality = trim($_GET['municipality']);

    // Check database connection
    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    // Query to get associations from the selected municipality
    $stmt = $conn->prepare("
        SELECT id, name, municipality
        FROM associations 
        WHERE LOWER(TRIM(municipality)) = LOWER(TRIM(?))
        ORDER BY name ASC
    ");
    
    if (!$stmt) {
        throw new Exception('Query preparation failed: ' . $conn->error);
    }

    $stmt->bind_param("s", $municipality);
    
    if (!$stmt->execute()) {
        throw new Exception('Query execution failed: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $associations = [];
    
    while ($row = $result->fetch_assoc()) {
        $associations[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'municipality' => $row['municipality']
        ];
    }

    $stmt->close();

    echo json_encode([
        'success' => true,
        'associations' => $associations,
        'count' => count($associations),
        'municipality' => $municipality
    ]);

} catch (Exception $e) {
    error_log('get_associations.php error: ' . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred',
        'error' => $e->getMessage(),
        'associations' => []
    ]);
}
?>