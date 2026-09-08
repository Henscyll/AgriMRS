<?php
require_once '../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $machine_name = $_POST['machine_name'];
    $type = $_POST['type'];
    $description = $_POST['description'];
    $association_id = $_POST['association_id'];
    $status = $_POST['status'];

    $image_path = "";
    if (!empty($_FILES['image']['name'])) {
        $targetDir = "../uploads/";
        if (!is_dir($targetDir)) mkdir($targetDir);
        $fileName = time() . "_" . basename($_FILES['image']['name']);
        $targetFile = $targetDir . $fileName;
        if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFile)) {
            $image_path = $targetFile;
        }
    }

    $stmt = $conn->prepare("INSERT INTO machines (machine_name, type, image_path, description, association_id, status) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssis", $machine_name, $type, $image_path, $description, $association_id, $status);

    if ($stmt->execute()) {
        header("Location: machines.php?success=1");
    } else {
        echo "Error: " . $stmt->error;
    }
}
?>
