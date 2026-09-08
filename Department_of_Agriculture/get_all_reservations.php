<?php
/**
 * get_all_reservations.php
 * Used by DA Staff reservation page.
 * Returns ALL bookings with full farmer name (first + middle + last),
 * farmer address fields (province, municipality, barangay), phone, etc.
 */
session_start();
require_once '../includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$sql = "
    SELECT
        b.id                            AS booking_id,
        b.booking_date,
        b.farm_location,
        b.farm_size,
        b.notes,
        b.status,
        b.created_at,

        /* Full name with middle name */
        f.first_name                    AS farmer_first_name,
        f.middle_name                   AS farmer_middle_name,
        f.last_name                     AS farmer_last_name,
        CONCAT(
            f.first_name, ' ',
            COALESCE(NULLIF(f.middle_name,''), ''), ' ',
            f.last_name
        )                               AS farmer_name,

        /* Contact & address */
        f.email                         AS farmer_email,
        f.phone                         AS farmer_phone,
        f.province                      AS farmer_province,
        f.municipality                  AS farmer_municipality,
        f.barangay                      AS farmer_barangay,

        /* Machine */
        m.machine_name,
        m.type                          AS machine_type,
        m.price_per_hectare,

        /* Lot */
        fl.lot_number,
        fl.farm_location                AS lot_location,
        fl.municipality                 AS lot_municipality,
        fl.barangay                     AS lot_barangay,
        fl.province                     AS lot_province,

        /* Association */
        a.id                            AS association_id,
        a.name                          AS association_name

    FROM bookings b
    JOIN machines      m  ON b.machine_id  = m.id
    JOIN farmers       f  ON b.farmer_id   = f.id
    JOIN associations  a  ON m.association_id = a.id
    LEFT JOIN farmer_lots fl ON b.lot_id   = fl.id

    ORDER BY
        CASE
            WHEN b.status = 'Pending'   THEN 1
            WHEN b.status = 'Approved'  THEN 2
            WHEN b.status = 'Completed' THEN 3
            ELSE 4
        END,
        b.booking_date DESC,
        b.created_at   DESC
";

$result = $conn->query($sql);

if (!$result) {
    echo json_encode(['success' => false, 'message' => $conn->error]);
    exit;
}

$reservations = [];
while ($row = $result->fetch_assoc()) {
    // Clean up the farmer_name (remove double spaces from empty middle name)
    $row['farmer_name'] = preg_replace('/\s+/', ' ', trim($row['farmer_name']));
    $reservations[] = $row;
}

echo json_encode(['success' => true, 'reservations' => $reservations]);