<?php
require_once '../includes/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $machine_name   = trim($_POST['machine_name'] ?? '');
    $type           = trim($_POST['type'] ?? '');
    $association_id = intval($_POST['association_id'] ?? 0);
    $description    = isset($_POST['description']) ? trim($_POST['description']) : NULL;
    $status         = 'Active';

    if (empty($machine_name) || empty($type) || empty($association_id) || empty($_FILES['image']['name'])) {
        echo json_encode(['status' => 'error', 'message' => 'Please fill in all required fields.']);
        exit();
    }

    // Server-side validation for duplicate name
    $check_stmt = $conn->prepare("SELECT id FROM machines WHERE LOWER(machine_name) = LOWER(?) LIMIT 1");
    $check_stmt->bind_param("s", $machine_name);
    $check_stmt->execute();
    $check_stmt->store_result();
    if ($check_stmt->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Machine already exists.']);
        $check_stmt->close();
        exit();
    }
    $check_stmt->close();

    $image_path = "";
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $targetDir = "../uploads/";
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        
        $fileExtension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $fileName      = time() . '_' . bin2hex(random_bytes(4)) . '.' . $fileExtension;
        $targetFile    = $targetDir . $fileName;

        if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFile)) {
            $image_path = $targetFile;
        }
    }

    if (empty($image_path)) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to upload image.']);
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO machines (machine_name, type, image_path, description, association_id, status) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssis", $machine_name, $type, $image_path, $description, $association_id, $status);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Machine added successfully!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $stmt->error]);
    }

    $stmt->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
}
?>