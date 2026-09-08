<?php
session_start();
include('../includes/db_connection.php');

header('Content-Type: application/json');

// Check if machine_id is provided
if (!isset($_GET['machine_id']) || !is_numeric($_GET['machine_id'])) {
    echo json_encode(['error' => 'Invalid machine ID']);
    exit;
}

$machine_id = intval($_GET['machine_id']);

// Fetch assigned operators for this machine
$query = "
    SELECT 
        mo.id as assignment_id,
        o.id as operator_id,
        o.name,
        o.email,
        o.phone,
        o.license_number,
        o.experience_years,
        mo.assigned_date,
        mo.status
    FROM machine_operators mo
    INNER JOIN operators o ON mo.operator_id = o.id
    WHERE mo.machine_id = ? AND mo.status = 'Active'
    ORDER BY mo.assigned_date DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $machine_id);
$stmt->execute();
$result = $stmt->get_result();

$operators = [];
while ($row = $result->fetch_assoc()) {
    $operators[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode(['operators' => $operators]);
?>