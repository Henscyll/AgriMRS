<?php
session_start();
require_once '../includes/config.php';

header('Content-Type: application/json');

// Role Guard
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['da staff', 'department of agriculture', 'it admin'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

$farmer_id  = intval($_POST['farmer_id'] ?? 0);
$new_status = trim($_POST['new_status']  ?? '');

if (!$farmer_id || !in_array($new_status, ['Active', 'Inactive'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid farmer ID or status value.']);
    exit;
}

$stmt = $conn->prepare("UPDATE farmers SET status = ? WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("si", $new_status, $farmer_id);
    if ($stmt->execute()) {
        $stmt->close();
        echo json_encode(['status' => 'success', 'message' => "Farmer status updated to $new_status successfully."]);
        exit;
    } else {
        $error = $stmt->error;
        $stmt->close();
        echo json_encode(['status' => 'error', 'message' => 'Failed to update farmer status: ' . $error]);
        exit;
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Database query preparation failed.']);
    exit;
}