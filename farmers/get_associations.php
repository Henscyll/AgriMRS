<?php
require_once '../includes/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    $municipality = $_GET['municipality'] ?? '';
    
    if (empty($municipality)) {
        echo json_encode([
            'success' => false,
            'message' => 'Municipality parameter is required',
            'associations' => []
        ]);
        exit;
    }
    
    // Fetch associations for the given municipality
    $stmt = $conn->prepare("
        SELECT id, name, municipality, barangay, phone
        FROM associations
        WHERE municipality = ?
        ORDER BY name ASC
    ");
    
    $stmt->bind_param("s", $municipality);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $associations = [];
    while ($row = $result->fetch_assoc()) {
        $associations[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'municipality' => $row['municipality'],
            'barangay' => $row['barangay'],
            'phone' => $row['phone']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'associations' => $associations,
        'count' => count($associations)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error',
        'error' => $e->getMessage(),
        'associations' => []
    ]);
}
?>