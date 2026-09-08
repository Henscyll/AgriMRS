<?php
session_start();
require_once '../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$current_password = $_POST['current_password'];
$new_password = $_POST['new_password'];
$confirm_password = $_POST['confirm_password'];

if ($new_password !== $confirm_password) {
    echo "<script>alert('New passwords do not match!'); window.history.back();</script>";
    exit();
}

// Fetch current password
$stmt = $conn->prepare("SELECT password FROM users WHERE id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Verify current password
if (!password_verify($current_password, $user['password'])) {
    echo "<script>alert('Incorrect current password!'); window.history.back();</script>";
    exit();
}

// Update password
$new_hash = password_hash($new_password, PASSWORD_DEFAULT);

$update = $conn->prepare("UPDATE users SET password=? WHERE id=?");
$update->bind_param("si", $new_hash, $user_id);
$update->execute();

echo "<script>alert('Password changed successfully!'); window.location.href='staff_account_setting.php';</script>";
