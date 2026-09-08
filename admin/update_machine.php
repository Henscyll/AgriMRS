<?php
session_start();
require_once '../includes/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $machine_id = intval($_POST['machine_id']);
    $machine_name = trim($_POST['machine_name']);
    $type = $_POST['type'];
    $status = $_POST['status'];
    $description = trim($_POST['description']);
    
    $stmt = $conn->prepare("UPDATE machines SET machine_name = ?, type = ?, status = ?, description = ? WHERE id = ?");
    $stmt->bind_param("ssssi", $machine_name, $type, $status, $description, $machine_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>