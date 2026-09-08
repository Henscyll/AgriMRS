<?php
session_start();
require_once '../includes/config.php';

// Check if user is logged in and is a farmer
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'farmer') {
    header('Location: ../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $farmer_id = $_SESSION['user_id'];
    $machine_id = $_POST['machine_id'] ?? null;
    $booking_date = $_POST['booking_date'] ?? null;
    $farm_location = $_POST['farm_location'] ?? '';
    $farm_size = $_POST['farm_size'] ?? '';
    $notes = $_POST['notes'] ?? '';

    // Validate required fields
    if (empty($machine_id) || empty($booking_date)) {
        $_SESSION['error_message'] = 'Please fill in all required fields.';
        header('Location: tractor.php');
        exit();
    }

    // Validate that booking date is not in the past
    if (strtotime($booking_date) < strtotime(date('Y-m-d'))) {
        $_SESSION['error_message'] = 'Booking date cannot be in the past.';
        header('Location: tractor.php');
        exit();
    }

    // Check if machine exists and is available
    $machineCheck = $conn->prepare("SELECT id, status FROM machines WHERE id = ?");
    $machineCheck->bind_param("i", $machine_id);
    $machineCheck->execute();
    $machineResult = $machineCheck->get_result();

    if ($machineResult->num_rows === 0) {
        $_SESSION['error_message'] = 'Selected machine does not exist.';
        header('Location: tractor.php');
        exit();
    }

    $machine = $machineResult->fetch_assoc();
    if ($machine['status'] !== 'Active') {
        $_SESSION['error_message'] = 'Selected machine is not available for booking.';
        header('Location: tractor.php');
        exit();
    }

    // Check if there's already a booking for this machine on the same date
    $dateCheck = $conn->prepare("SELECT id FROM bookings WHERE machine_id = ? AND booking_date = ? AND status NOT IN ('Cancelled', 'Completed')");
    $dateCheck->bind_param("is", $machine_id, $booking_date);
    $dateCheck->execute();
    $dateResult = $dateCheck->get_result();

    if ($dateResult->num_rows > 0) {
        $_SESSION['error_message'] = 'This machine is already booked for the selected date. Please choose another date.';
        header('Location: tractor.php');
        exit();
    }

    // Insert the booking with all required fields
    $insertQuery = "INSERT INTO bookings (machine_id, farmer_id, booking_date, farm_location, farm_size, notes, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, 'Pending', NOW())";
    
    $stmt = $conn->prepare($insertQuery);
    $stmt->bind_param("iissss", $machine_id, $farmer_id, $booking_date, $farm_location, $farm_size, $notes);

    if ($stmt->execute()) {
        $_SESSION['success_message'] = 'Booking request submitted successfully! Please wait for approval.';
        header('Location: my_reservation.php');
    } else {
        $_SESSION['error_message'] = 'Failed to create booking. Error: ' . $stmt->error;
        header('Location: tractor.php');
    }

    $stmt->close();
} else {
    header('Location: tractor.php');
}

$conn->close();
?>