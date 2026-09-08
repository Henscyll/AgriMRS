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
        f.first_name,
        f.middle_name,
        f.last_name,

        /* Lot details */
        fl.lot_number,
        fl.farm_location  AS lot_farm_location,
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
    JOIN farmers   f  ON b.farmer_id      = f.id
    JOIN machines  m  ON b.machine_id     = m.id
    JOIN associations a ON m.association_id = a.id
    LEFT JOIN farmer_lots fl ON b.lot_id  = fl.id
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

/* ── Build full name with middle initial ── */
$firstName  = trim($row['first_name']  ?? '');
$middleName = trim($row['middle_name'] ?? '');
$lastName   = trim($row['last_name']   ?? '');

$fullName = $firstName;
if ($middleName !== '') {
    $fullName .= ' ' . strtoupper(mb_substr($middleName, 0, 1)) . '.';
}
$fullName .= ' ' . $lastName;

/* ── Build location string ──
   Hierarchy: province > municipality > barangay > specific farm_location
   Result: "Zamboanga Del Sur, Labangan, Lower Pulacan – Purok Lawag"
*/
$province     = trim($row['lot_province']       ?? '');
$municipality = trim($row['lot_municipality']   ?? '');
$barangay     = trim($row['lot_barangay']       ?? '');
$farmLocation = trim($row['lot_farm_location']  ?? '');

$addressParts = array_filter([$province, $municipality, $barangay]);
$fullAddress  = implode(', ', $addressParts);

/* Specific farm location (e.g. "Purok Lawag") shown separately */
$specificLocation = $farmLocation; // e.g. "Purok Lawag"

echo json_encode([
    'success'     => true,
    'reservation' => [
        'booking_id'        => $row['booking_id'],
        'booking_date'      => $row['booking_date'],
        'status'            => $row['status'],
        'created_at'        => $row['created_at'],

        /* Name */
        'first_name'        => $firstName,
        'middle_name'       => $middleName,
        'last_name'         => $lastName,
        'farmer_name'       => $fullName,           // "Charlemagne G. Pulmano"

        /* Lot */
        'lot_number'        => $row['lot_number']   ?? '',
        'farm_size'         => $row['farm_size_final'],

        /* Location — all parts available separately */
        'farm_location'     => $specificLocation,   // "Purok Lawag"
        'barangay'          => $barangay,            // "Lower Pulacan"
        'municipality'      => $municipality,        // "Labangan"
        'province'          => $province,            // "Zamboanga del sur"
        'full_address'      => $fullAddress,         // "Zamboanga del sur, Labangan, Lower Pulacan"

        /* Machine */
        'machine_type'      => $row['machine_type'],
        'machine_name'      => $row['machine_name'],
        'price_per_hectare' => $row['price_per_hectare'],

        /* Association */
        'association_name'  => $row['association_name'],

        /* Notes */
        'notes'             => $row['notes'] ?? '',
    ]
]);

$stmt->close();