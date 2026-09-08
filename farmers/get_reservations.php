<?php
require_once '../includes/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    // Get filter parameters
    $municipality = $_GET['municipality'] ?? '';
    $association_id = isset($_GET['association_id']) ? (int)$_GET['association_id'] : 0;
    $machine_id = $_GET['machine_id'] ?? '';
    $from_date = $_GET['from_date'] ?? '';
    $to_date = $_GET['to_date'] ?? '';

    // Build the query with LEFT JOIN to farmer_lots to get farm location and size
    $query = "
        SELECT 
            b.id,
            b.booking_date,
            b.farm_location as booking_farm_location,
            b.farm_size as booking_farm_size,
            b.notes,
            b.status,
            b.created_at,
            b.lot_id,
            f.name as farmer_name,
            f.phone,
            f.email as farmer_email,
            f.barangay as farmer_barangay,
            f.municipality as farmer_municipality,
            m.machine_name,
            m.type as machine_type,
            a.name as association_name,
            a.municipality as association_municipality,
            fl.farm_location as lot_farm_location,
            fl.farm_size as lot_farm_size,
            fl.lot_number
        FROM bookings b
        INNER JOIN farmers f ON b.farmer_id = f.id
        INNER JOIN machines m ON b.machine_id = m.id
        INNER JOIN associations a ON m.association_id = a.id
        LEFT JOIN farmer_lots fl ON b.lot_id = fl.id
        WHERE 1=1
    ";

    $params = [];
    $types = "";

    // Add association filter
    if (!empty($association_id) && $association_id > 0) {
        $query .= " AND m.association_id = ?";
        $params[] = $association_id;
        $types .= "i";
    }

    // Add machine filter
    if (!empty($machine_id) && $machine_id !== 'all') {
        $query .= " AND m.id = ?";
        $params[] = (int)$machine_id;
        $types .= "i";
    }

    // Add date filters
    if (!empty($from_date)) {
        $query .= " AND b.booking_date >= ?";
        $params[] = $from_date;
        $types .= "s";
    }

    if (!empty($to_date)) {
        $query .= " AND b.booking_date <= ?";
        $params[] = $to_date;
        $types .= "s";
    }

    $query .= " ORDER BY b.booking_date DESC, b.created_at DESC";

    // Execute query
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        throw new Exception('Query preparation failed: ' . $conn->error);
    }

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    if (!$stmt->execute()) {
        throw new Exception('Query execution failed: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    $reservations = [];
    
    while ($row = $result->fetch_assoc()) {
        // Determine farm location - priority: lot location > booking location > farmer location
        $farm_location = '';
        if (!empty($row['lot_farm_location'])) {
            // Use location from farmer_lots table
            $farm_location = $row['lot_farm_location'];
        } elseif (!empty($row['booking_farm_location'])) {
            // Use location from bookings table
            $farm_location = $row['booking_farm_location'];
        } else {
            // Build from farmer's barangay and municipality
            $farm_location = $row['farmer_barangay'] . ', ' . $row['farmer_municipality'];
        }

        // Determine farm size - priority: lot size > booking size
        $farm_size = 'N/A';
        if (!empty($row['lot_farm_size'])) {
            $farm_size = number_format($row['lot_farm_size'], 2) . ' hectares';
        } elseif (!empty($row['booking_farm_size'])) {
            $farm_size = number_format($row['booking_farm_size'], 2) . ' hectares';
        }

        $reservations[] = [
            'id' => $row['id'],
            'booking_date' => $row['booking_date'],
            'farmer_name' => $row['farmer_name'],
            'phone' => $row['phone'],
            'farmer_email' => $row['farmer_email'],
            'farm_location' => $farm_location,
            'farm_size' => $farm_size,
            'lot_number' => $row['lot_number'] ?? 'N/A',
            'machine_name' => $row['machine_name'],
            'machine_type' => $row['machine_type'],
            'association_name' => $row['association_name'],
            'status' => $row['status'],
            'notes' => $row['notes'],
            'created_at' => $row['created_at']
        ];
    }

    $stmt->close();

    echo json_encode([
        'success' => true,
        'reservations' => $reservations,
        'count' => count($reservations)
    ]);

} catch (Exception $e) {
    error_log('get_reservations.php error: ' . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred',
        'error' => $e->getMessage(),
        'reservations' => []
    ]);
}
?>