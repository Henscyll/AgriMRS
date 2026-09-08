<?php
session_start();
require_once '../includes/config.php';

$farmer_id = isset($_POST['farmer_id']) ? intval($_POST['farmer_id']) : (isset($_GET['farmer_id']) ? intval($_GET['farmer_id']) : 0);
$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

if ($farmer_id <= 0) {
    $_SESSION['error'] = "Invalid farmer ID";
    header("Location: staff_farmers.php");
    exit();
}

switch ($action) {
    case 'add':
        addLot($conn, $farmer_id);
        break;
    
    case 'edit':
        editLot($conn, $farmer_id);
        break;
    
    case 'toggle':
        toggleLotStatus($conn, $farmer_id);
        break;
    
    case 'delete':
        deleteLot($conn, $farmer_id);
        break;
    
    default:
        $_SESSION['error'] = "Invalid action";
        header("Location: staff_farmers_lots.php?id=" . $farmer_id);
        exit();
}

function addLot($conn, $farmer_id) {
    $lot_number = trim($_POST['lot_number']);
    $farm_size = floatval($_POST['farm_size']);
    $farm_location = trim($_POST['farm_location']);
    $province = trim($_POST['province']);
    $municipality = trim($_POST['municipality']);
    $barangay = trim($_POST['barangay']);
    
    // Check if lot number already exists for this farmer
    $check_stmt = $conn->prepare("SELECT id FROM farmer_lots WHERE farmer_id = ? AND lot_number = ?");
    $check_stmt->bind_param("is", $farmer_id, $lot_number);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $_SESSION['error'] = "Lot number already exists for this farmer!";
        header("Location: staff_farmers_lots.php?id=" . $farmer_id);
        exit();
    }
    
    try {
        $stmt = $conn->prepare("INSERT INTO farmer_lots (farmer_id, lot_number, farm_location, farm_size, province, municipality, barangay, status) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, 'Active')");
        $stmt->bind_param("issdsss", $farmer_id, $lot_number, $farm_location, $farm_size, $province, $municipality, $barangay);
        $stmt->execute();
        
        $_SESSION['success'] = "Lot added successfully!";
        header("Location: staff_farmers_lots.php?id=" . $farmer_id);
        exit();
    } catch (Exception $e) {
        $_SESSION['error'] = "Error adding lot: " . $e->getMessage();
        header("Location: staff_farmers_lots.php?id=" . $farmer_id);
        exit();
    }
}

function editLot($conn, $farmer_id) {
    $lot_id = intval($_POST['lot_id']);
    $lot_number = trim($_POST['lot_number']);
    $farm_size = floatval($_POST['farm_size']);
    $farm_location = trim($_POST['farm_location']);
    $province = trim($_POST['province']);
    $municipality = trim($_POST['municipality']);
    $barangay = trim($_POST['barangay']);
    
    // Verify lot belongs to this farmer
    $verify_stmt = $conn->prepare("SELECT id FROM farmer_lots WHERE id = ? AND farmer_id = ?");
    $verify_stmt->bind_param("ii", $lot_id, $farmer_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    
    if ($verify_result->num_rows === 0) {
        $_SESSION['error'] = "Lot not found or doesn't belong to this farmer!";
        header("Location: staff_farmers_lots.php?id=" . $farmer_id);
        exit();
    }
    
    // Check if lot number already exists for this farmer (excluding current lot)
    $check_stmt = $conn->prepare("SELECT id FROM farmer_lots WHERE farmer_id = ? AND lot_number = ? AND id != ?");
    $check_stmt->bind_param("isi", $farmer_id, $lot_number, $lot_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $_SESSION['error'] = "Lot number already exists for this farmer!";
        header("Location: staff_farmers_lots.php?id=" . $farmer_id);
        exit();
    }
    
    try {
        $stmt = $conn->prepare("UPDATE farmer_lots 
                                SET lot_number = ?, farm_location = ?, farm_size = ?, province = ?, municipality = ?, barangay = ? 
                                WHERE id = ? AND farmer_id = ?");
        $stmt->bind_param("ssdsssii", $lot_number, $farm_location, $farm_size, $province, $municipality, $barangay, $lot_id, $farmer_id);
        $stmt->execute();
        
        $_SESSION['success'] = "Lot updated successfully!";
        header("Location: staff_farmers_lots.php?id=" . $farmer_id);
        exit();
    } catch (Exception $e) {
        $_SESSION['error'] = "Error updating lot: " . $e->getMessage();
        header("Location: staff_farmers_lots.php?id=" . $farmer_id);
        exit();
    }
}

function toggleLotStatus($conn, $farmer_id) {
    $lot_id = isset($_GET['lot_id']) ? intval($_GET['lot_id']) : 0;
    
    if ($lot_id <= 0) {
        $_SESSION['error'] = "Invalid lot ID";
        header("Location: staff_farmers_lots.php?id=" . $farmer_id);
        exit();
    }
    
    try {
        $stmt = $conn->prepare("UPDATE farmer_lots 
                                SET status = CASE 
                                    WHEN status = 'Active' THEN 'Inactive' 
                                    ELSE 'Active' 
                                END 
                                WHERE id = ? AND farmer_id = ?");
        $stmt->bind_param("ii", $lot_id, $farmer_id);
        $stmt->execute();
        
        $_SESSION['success'] = "Lot status updated successfully!";
        header("Location: staff_farmers_lots.php?id=" . $farmer_id);
        exit();
    } catch (Exception $e) {
        $_SESSION['error'] = "Error updating lot status: " . $e->getMessage();
        header("Location: staff_farmers_lots.php?id=" . $farmer_id);
        exit();
    }
}

function deleteLot($conn, $farmer_id) {
    $lot_id = isset($_GET['lot_id']) ? intval($_GET['lot_id']) : 0;
    
    if ($lot_id <= 0) {
        $_SESSION['error'] = "Invalid lot ID";
        header("Location: staff_farmers_lots.php?id=" . $farmer_id);
        exit();
    }
    
    // Check if lot has any bookings
    $check_stmt = $conn->prepare("SELECT COUNT(*) as booking_count FROM bookings WHERE lot_id = ?");
    $check_stmt->bind_param("i", $lot_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $booking_data = $check_result->fetch_assoc();
    
    if ($booking_data['booking_count'] > 0) {
        $_SESSION['error'] = "Cannot delete lot with existing bookings! Please cancel or complete all bookings first.";
        header("Location: staff_farmers_lots.php?id=" . $farmer_id);
        exit();
    }
    
    try {
        $stmt = $conn->prepare("DELETE FROM farmer_lots WHERE id = ? AND farmer_id = ?");
        $stmt->bind_param("ii", $lot_id, $farmer_id);
        $stmt->execute();
        
        $_SESSION['success'] = "Lot deleted successfully!";
        header("Location: staff_farmers_lots.php?id=" . $farmer_id);
        exit();
    } catch (Exception $e) {
        $_SESSION['error'] = "Error deleting lot: " . $e->getMessage();
        header("Location: staff_farmers_lots.php?id=" . $farmer_id);
        exit();
    }
}
?>