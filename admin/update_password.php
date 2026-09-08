<?php
session_start();

$conn = new mysqli("localhost", "root", "", "agri_machinery");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 1. Check if user is logged in
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    http_response_code(401);
    echo "Unauthorized";
    exit;
}

// 2. Get submitted form data
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// 3. Validate
if (empty($new_password) || $new_password !== $confirm_password) {
    http_response_code(400);
    echo "Passwords do not match or are empty.";
    exit;
}

// 4. Hash password & update database
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT); // Recommended secure hashing

$stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
$stmt->bind_param("si", $hashed_password, $user_id);

if ($stmt->execute()) {
    echo "Success";
} else {
    http_response_code(500);
    echo "Database update failed";
}

$stmt->close();
$conn->close();
?>