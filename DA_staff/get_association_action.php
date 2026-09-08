<?php
require_once '../includes/config.php';
session_start();

$id            = intval($_POST['id']);
$email         = trim($_POST['email']);
$phone         = trim($_POST['phone']);
$pres_phone    = trim($_POST['president_phone']);
$pres_email    = trim($_POST['president_email'] ?? '');

$stmt = $conn->prepare("UPDATE associations SET email=?, phone=?, president_phone=?, president_email=? WHERE id=?");
$stmt->bind_param("ssssi", $email, $phone, $pres_phone, $pres_email, $id);

if ($stmt->execute()) {
    // Also update email in users table
    $conn->query("UPDATE users SET email='".  $conn->real_escape_string($email)."' WHERE email=(SELECT email FROM (SELECT email FROM associations WHERE id=$id) AS tmp)");
    $_SESSION['success_message'] = 'Association updated successfully!';
} else {
    $_SESSION['error_message'] = 'Error updating association.';
}
header('Location: staff_associations.php');
exit;
?>