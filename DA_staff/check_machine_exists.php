<?php
require_once '../includes/config.php';

header('Content-Type: application/json');

$name = trim($_GET['name'] ?? '');

if ($name === '') {
    echo json_encode(['exists' => false]);
    exit();
}

$stmt = $conn->prepare("SELECT id FROM machines WHERE LOWER(machine_name) = LOWER(?) LIMIT 1");
$stmt->bind_param("s", $name);
$stmt->execute();
$stmt->store_result();

$exists = ($stmt->num_rows > 0);

$stmt->close();
$conn->close();

echo json_encode(['exists' => $exists]);
exit();
?>