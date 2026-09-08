<?php
require_once '../includes/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$assoc_id   = intval($_POST['association_id'] ?? 0);
$first_name = trim($_POST['new_first_name']   ?? '');
$middle_name= trim($_POST['new_middle_name']  ?? '');
$last_name  = trim($_POST['new_last_name']    ?? '');
$sex        = trim($_POST['new_sex']          ?? '');
$dob        = trim($_POST['new_dob']          ?? '');
$age        = intval($_POST['new_age']        ?? 0);
$email      = trim($_POST['new_email']        ?? '');
$phone      = trim($_POST['new_phone']        ?? '');
$province   = trim($_POST['new_province']     ?? 'Zamboanga del Sur');
$municipality= trim($_POST['new_municipality']?? '');
$barangay   = trim($_POST['new_barangay']    ?? '');

if ($assoc_id <= 0 || empty($first_name) || empty($last_name) || empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Please fill out all required fields.']);
    exit;
}

// Convert date format for MySQL
$dob_mysql = null;
if (!empty($dob)) {
    $dobObj = DateTime::createFromFormat('m/d/Y', $dob);
    if ($dobObj) {
        $dob_mysql = $dobObj->format('Y-m-d');
    }
}

$conn->begin_transaction();

try {
    $hashed_pass = password_hash('123456', PASSWORD_DEFAULT);
    $full_name   = trim("$first_name $middle_name $last_name");

    // 1. Create User account for new president
    $stmt_user = $conn->prepare("INSERT INTO users (name, email, password, user_role) VALUES (?, ?, ?, 'associations')");
    $stmt_user->bind_param("sss", $full_name, $email, $hashed_pass);
    $stmt_user->execute();
    $new_user_id = $conn->insert_id;
    $stmt_user->close();

    // 2. Insert into presidents table
    $stmt_pres = $conn->prepare("INSERT INTO presidents 
        (user_id, association_id, first_name, middle_name, last_name, sex, date_of_birth, age, province, municipality, barangay, phone, email) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt_pres->bind_param("iisssssisssss", 
        $new_user_id, $assoc_id, $first_name, $middle_name, $last_name, 
        $sex, $dob_mysql, $age, $province, $municipality, $barangay, $phone, $email
    );
    $stmt_pres->execute();
    $new_pres_id = $conn->insert_id;
    $stmt_pres->close();

    // 3. Update President ID link in Associations table
    $stmt_assoc = $conn->prepare("UPDATE associations SET president_id = ? WHERE id = ?");
    $stmt_assoc->bind_param("ii", $new_pres_id, $assoc_id);
    $stmt_assoc->execute();
    $stmt_assoc->close();

    $conn->commit();

    echo json_encode(['success' => true, 'message' => 'President updated successfully!']);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
exit;