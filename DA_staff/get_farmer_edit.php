<?php
session_start();
require_once '../includes/config.php';

header('Content-Type: application/json');

$id = intval($_GET['id'] ?? 0);

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit;
}

$farmerRes = $conn->query("SELECT * FROM farmers WHERE id = $id LIMIT 1");
if (!$farmerRes || $farmerRes->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Farmer not found']);
    exit;
}

$farmer = $farmerRes->fetch_assoc();

// Fetch latest active lot's association name or fallback to farmers.association_id
$latestAssocRes = $conn->query("
    SELECT a.id AS association_id, a.name AS association_name 
    FROM farmer_lots fl
    JOIN associations a ON fl.association_id = a.id
    WHERE fl.farmer_id = $id 
      AND fl.status = 'Active' 
      AND fl.association_id IS NOT NULL
    ORDER BY fl.id DESC 
    LIMIT 1
");

if ($latestAssocRes && $latestAssocRes->num_rows > 0) {
    $assocData = $latestAssocRes->fetch_assoc();
    $farmer['latest_association_id']   = $assocData['association_id'];
    $farmer['latest_association_name'] = $assocData['association_name'];
} else {
    // Fallback to farmers.association_id
    $fallbackRes = $conn->query("
        SELECT a.id AS association_id, a.name AS association_name 
        FROM farmers f
        JOIN associations a ON f.association_id = a.id
        WHERE f.id = $id
        LIMIT 1
    ");
    if ($fallbackRes && $fallbackRes->num_rows > 0) {
        $assocData = $fallbackRes->fetch_assoc();
        $farmer['latest_association_id']   = $assocData['association_id'];
        $farmer['latest_association_name'] = $assocData['association_name'];
    } else {
        $farmer['latest_association_id']   = null;
        $farmer['latest_association_name'] = null;
    }
}

// Fetch all lots (Active and Inactive)
$lotsRes = $conn->query("SELECT * FROM farmer_lots WHERE farmer_id = $id ORDER BY id ASC");
$existing_lots = [];
if ($lotsRes) {
    while ($lot = $lotsRes->fetch_assoc()) {
        $existing_lots[] = $lot;
    }
}

$farmer['existing_lots'] = $existing_lots;

echo json_encode(['success' => true, 'farmer' => $farmer]);
exit;