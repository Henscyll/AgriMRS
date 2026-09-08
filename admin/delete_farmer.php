<?php
session_start();
require_once '../includes/config.php';

// ✅ Ensure IT admin is logged in
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'it admin') {
    header("Location: ../login.php");
    exit;
}

// ✅ Check if farmer_id is sent
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['farmer_id'])) {
    $farmer_id = intval($_POST['farmer_id']);

    if ($farmer_id > 0) {
        // Prepare delete query
        $stmt = $conn->prepare("DELETE FROM farmers WHERE id = ?");
        $stmt->bind_param("i", $farmer_id);

        if ($stmt->execute()) {
            $_SESSION['message'] = "Farmer account deleted successfully.";
        } else {
            $_SESSION['message'] = "Error deleting farmer: " . $stmt->error;
        }

        $stmt->close();
    } else {
        $_SESSION['message'] = "Invalid farmer ID.";
    }
} else {
    $_SESSION['message'] = "No farmer selected for deletion.";
}

// ✅ Redirect back to admin farmers page
header("Location: admin_farmers.php");
exit;
?>
