<?php
session_start();

$farmerId = 1; // Or use: $_SESSION['farmer_id'];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $passwordInput = $_POST['password'];
    $address = $_POST['address'];

    // If password field is already hashed (e.g., starts with $2y$), don't hash it again
    if (strpos($passwordInput, '$2y$') === 0) {
        $hashedPassword = $passwordInput;
    } else {
        $hashedPassword = password_hash($passwordInput, PASSWORD_DEFAULT);
    }

    $conn = new mysqli("localhost", "root", "", "agri_machinery");

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Update farmer details
    $stmt = $conn->prepare("UPDATE farmers SET name = ?, email = ?, password = ?, address = ? WHERE id = ?");
    $stmt->bind_param("ssssi", $name, $email, $hashedPassword, $address, $farmerId);

    if ($stmt->execute()) {
        // Optionally update session data
        $_SESSION['farmer_name'] = $name;
        $_SESSION['address'] = $address;

        echo "<script>alert('Account updated successfully!'); window.location.href='account.php';</script>";
    } else {
        echo "<script>alert('Update failed.'); window.history.back();</script>";
    }

    $stmt->close();
    $conn->close();
}
?>
