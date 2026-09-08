<?php
/**
 * get_reservations.php
 * Filtered search version — used by DA Staff reservation page.
 * Supports: name, association, municipality, barangay, status, date filters.
 */
session_start();
require_once '../includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$search_field    = $_GET['search_field']    ?? 'all';
$farmer_name     = trim($_GET['farmer_name']     ?? '');
$association_name= trim($_GET['association_name'] ?? '');
$municipality    = trim($_GET['municipality']    ?? '');
$barangay        = trim($_GET['barangay']        ?? '');
$status          = trim($_GET['status']          ?? '');
$from_date       = trim($_GET['from_date']       ?? '');
$to_date         = trim($_GET['to_date']         ?? '');

$where  = [];
$params = [];
$types  = '';

/* ── Build WHERE conditions ── */
if ($search_field === 'name' && $farmer_name !== '') {
    $where[]  = "CONCAT(f.first_name,' ',COALESCE(f.middle_name,''),' ',f.last_name) LIKE ?";
    $params[] = '%' . $farmer_name . '%';
    $types   .= 's';
}

if ($search_field === 'association' && $association_name !== '') {
    $where[]  = "a.name LIKE ?";
    $params[] = '%' . $association_name . '%';
    $types   .= 's';
}

if (($search_field === 'municipality') && $municipality !== '') {
    $where[]  = "f.municipality LIKE ?";
    $params[] = '%' . $municipality . '%';
    $types   .= 's';
}

if ($search_field === 'barangay') {
    if ($municipality !== '') {
        $where[]  = "f.municipality LIKE ?";
        $params[] = '%' . $municipality . '%';
        $types   .= 's';
    }
    if ($barangay !== '') {
        $where[]  = "f.barangay LIKE ?";
        $params[] = '%' . $barangay . '%';
        $types   .= 's';
    }
}

if ($search_field === 'status') {
    if ($status !== '') {
        $where[]  = "b.status = ?";
        $params[] = $status;
        $types   .= 's';
    }
    if ($from_date !== '') {
        $where[]  = "b.booking_date >= ?";
        $params[] = $from_date;
        $types   .= 's';
    }
    if ($to_date !== '') {
        $where[]  = "b.booking_date <= ?";
        $params[] = $to_date;
        $types   .= 's';
    }
}

if ($search_field === 'date') {
    if ($from_date !== '') {
        $where[]  = "b.booking_date >= ?";
        $params[] = $from_date;
        $types   .= 's';
    }
    if ($to_date !== '') {
        $where[]  = "b.booking_date <= ?";
        $params[] = $to_date;
        $types   .= 's';
    }
}

$whereSQL = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "
    SELECT
        b.id                            AS booking_id,
        b.booking_date,
        b.farm_location,
        b.farm_size,
        b.notes,
        b.status,
        b.created_at,

        f.first_name                    AS farmer_first_name,
        f.middle_name                   AS farmer_middle_name,
        f.last_name                     AS farmer_last_name,
        CONCAT(
            f.first_name, ' ',
            COALESCE(NULLIF(f.middle_name,''), ''), ' ',
            f.last_name
        )                               AS farmer_name,

        f.email                         AS farmer_email,
        f.phone                         AS farmer_phone,
        f.province                      AS farmer_province,
        f.municipality                  AS farmer_municipality,
        f.barangay                      AS farmer_barangay,

        m.machine_name,
        m.type                          AS machine_type,
        m.price_per_hectare,

        fl.lot_number,
        fl.farm_location                AS lot_location,
        fl.municipality                 AS lot_municipality,
        fl.barangay                     AS lot_barangay,
        fl.province                     AS lot_province,

        a.id                            AS association_id,
        a.name                          AS association_name

    FROM bookings b
    JOIN machines      m  ON b.machine_id  = m.id
    JOIN farmers       f  ON b.farmer_id   = f.id
    JOIN associations  a  ON m.association_id = a.id
    LEFT JOIN farmer_lots fl ON b.lot_id   = fl.id

    $whereSQL

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

if (count($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}

if (!$result) {
    echo json_encode(['success' => false, 'message' => $conn->error]);
    exit;
}

$reservations = [];
while ($row = $result->fetch_assoc()) {
    $row['farmer_name'] = preg_replace('/\s+/', ' ', trim($row['farmer_name']));
    $reservations[] = $row;
}

echo json_encode(['success' => true, 'reservations' => $reservations]);