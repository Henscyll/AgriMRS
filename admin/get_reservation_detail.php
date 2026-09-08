<?php
header('Content-Type: application/json');
require_once '../includes/config.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid booking ID']);
    exit;
}

$id = intval($_GET['id']);

$query = "
    SELECT
        b.id              AS booking_id,
        b.booking_date,
        b.status,
        b.created_at,
        b.farm_size,
        b.notes,

        /* Farmer name parts */
        f.first_name      AS farmer_first_name,
        f.middle_name     AS farmer_middle_name,
        f.last_name       AS farmer_last_name,
        f.phone           AS farmer_phone,
        f.email           AS farmer_email,

        /* Farmer home address */
        f.province        AS farmer_province,
        f.municipality    AS farmer_municipality,
        f.barangay        AS farmer_barangay,

        /* Lot details */
        fl.lot_number,
        fl.farm_location  AS farm_location,
        fl.province       AS lot_province,
        fl.municipality   AS lot_municipality,
        fl.barangay       AS lot_barangay,
        COALESCE(fl.farm_size, b.farm_size) AS farm_size_final,

        /* Machine */
        m.type            AS machine_type,
        m.machine_name,
        m.price_per_hectare,

        /* Association */
        a.name            AS association_name

    FROM bookings b
    JOIN farmers      f  ON b.farmer_id     = f.id
    JOIN machines     m  ON b.machine_id    = m.id
    JOIN associations a  ON m.association_id = a.id
    LEFT JOIN farmer_lots fl ON b.lot_id    = fl.id
    WHERE b.id = ?
    LIMIT 1
";

$stmt = $conn->prepare($query);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Query prepare failed: ' . $conn->error]);
    exit;
}

$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Reservation not found']);
    exit;
}

$row = $result->fetch_assoc();

echo json_encode([
    'success'     => true,
    'reservation' => [
        'booking_id'          => $row['booking_id'],
        'booking_date'        => $row['booking_date'],
        'status'              => $row['status'],
        'created_at'          => $row['created_at'],

        'farmer_first_name'   => $row['farmer_first_name']   ?? '',
        'farmer_middle_name'  => $row['farmer_middle_name']  ?? '',
        'farmer_last_name'    => $row['farmer_last_name']    ?? '',
        'farmer_phone'        => $row['farmer_phone']        ?? '',
        'farmer_email'        => $row['farmer_email']        ?? '',

        'farmer_province'     => $row['farmer_province']     ?? '',
        'farmer_municipality' => $row['farmer_municipality'] ?? '',
        'farmer_barangay'     => $row['farmer_barangay']     ?? '',

        'lot_number'          => $row['lot_number']          ?? '',
        'farm_size'           => $row['farm_size_final'],

        'farm_location'       => $row['farm_location']       ?? '',
        'lot_province'        => $row['lot_province']        ?? '',
        'lot_municipality'    => $row['lot_municipality']    ?? '',
        'lot_barangay'        => $row['lot_barangay']        ?? '',

        'machine_type'        => $row['machine_type']        ?? '',
        'machine_name'        => $row['machine_name']        ?? '',
        'price_per_hectare'   => $row['price_per_hectare']   ?? 0,

        'association_name'    => $row['association_name']    ?? '',
        'notes'               => $row['notes']               ?? '',
    ]
]);

$stmt->close();