<?php
session_start();
require_once '../includes/config.php';

// Check if user is logged in and is a farmer
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'farmer') {
    header("Location: ../login.php");
    exit();
}

$farmer_id = $_SESSION['user_id'];
$booking_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($booking_id <= 0) {
    $_SESSION['error_message'] = "Invalid booking ID.";
    header("Location: my_reservation.php");
    exit();
}

// Verify booking belongs to the farmer and is in pending status
$checkQuery = "SELECT id, status FROM bookings WHERE id = ? AND farmer_id = ?";
$stmt = $conn->prepare($checkQuery);
$stmt->bind_param("ii", $booking_id, $farmer_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error_message'] = "Booking not found or you don't have permission to cancel it.";
    header("Location: my_reservation.php");
    exit();
}

$booking = $result->fetch_assoc();

if ($booking['status'] !== 'Pending') {
    $_SESSION['error_message'] = "Only pending bookings can be cancelled.";
    header("Location: my_reservation.php");
    exit();
}

// Update booking status to cancelled
$updateQuery = "UPDATE bookings SET status = 'Cancelled' WHERE id = ?";
$updateStmt = $conn->prepare($updateQuery);
$updateStmt->bind_param("i", $booking_id);

if ($updateStmt->execute()) {
    $_SESSION['success_message'] = "Booking cancelled successfully.";
} else {
    $_SESSION['error_message'] = "Error cancelling booking. Please try again.";
}

header("Location: my_reservation.php");
exit();
?>