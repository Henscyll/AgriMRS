<?php
session_start();
require_once '../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$username = $_POST['username'];
$email = $_POST['email'];
$phone = $_POST['phone'];

$stmt = $conn->prepare("UPDATE users SET username=?, email=?, phone=? WHERE id=?");
$stmt->bind_param("sssi", $username, $email, $phone, $user_id);

if ($stmt->execute()) {
    echo "<script>alert('Account updated successfully!'); window.location.href='staff_account_setting.php';</script>";
} else {
    echo "<script>alert('Error updating account.'); window.history.back();</script>";
}
