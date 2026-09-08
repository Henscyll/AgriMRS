<?php
require_once '../includes/config.php';
header('Content-Type: application/json');

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit;
}

$stmt = $conn->prepare("
    SELECT 
        a.id, a.name, a.email, a.phone,
        a.province, a.municipality, a.barangay,
        p.first_name  AS president_first_name,
        p.middle_name AS president_middle_name,
        p.last_name   AS president_last_name,
        p.sex         AS president_sex,
        p.date_of_birth AS president_dob,
        p.age         AS president_age,
        p.province    AS president_province,
        p.municipality AS president_municipality,
        p.barangay    AS president_barangay,
        p.phone       AS president_phone,
        p.email       AS president_email
    FROM associations a
    LEFT JOIN presidents p ON p.association_id = a.id
    WHERE a.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Association not found']);
    exit;
}

$row = $result->fetch_assoc();

if (!empty($row['president_dob']) && $row['president_dob'] !== '0000-00-00') {
    $dob = new DateTime($row['president_dob']);
    $row['president_dob'] = $dob->format('m/d/Y');
} else {
    $row['president_dob'] = '';
}

echo json_encode([
    'success'     => true,
    'association' => $row
]);
$stmt->close();