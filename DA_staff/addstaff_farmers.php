<?php
session_start();
require_once '../includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['da staff', 'department of agriculture', 'it admin'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

$first_name     = trim($_POST['first_name']     ?? '');
$middle_name    = trim($_POST['middle_name']    ?? '');
$last_name      = trim($_POST['last_name']      ?? '');
$sex            = trim($_POST['sex']            ?? '');
$email          = trim($_POST['email']          ?? '');
$phone          = trim($_POST['phone']          ?? '');
$province       = trim($_POST['province']       ?? '');
$municipality   = trim($_POST['municipality']   ?? '');
$barangay       = trim($_POST['barangay']       ?? '');
$date_of_birth  = trim($_POST['date_of_birth']  ?? '');
$manual_age     = trim($_POST['age']            ?? '');

if ($date_of_birth !== '' && preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $date_of_birth, $m)) {
    $date_of_birth = "{$m[3]}-{$m[1]}-{$m[2]}";
} elseif ($date_of_birth !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_of_birth)) {
    $date_of_birth = '';
}

$password   = $_POST['password']         ?? '123456';
$confirm_pw = $_POST['confirm_password'] ?? '123456';
$lots       = $_POST['lots']             ?? [];

$display_name = trim("$first_name $last_name");

$errors = [];

if ($first_name === '')   $errors[] = 'First name is required.';
if ($last_name === '')    $errors[] = 'Last name is required.';
if ($sex === '')          $errors[] = 'Sex is required.';
if ($email === '')        $errors[] = 'Email address is required.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address format.';
if ($phone === '')        $errors[] = 'Phone number is required.';
if ($province === '')     $errors[] = 'Province is required.';
if ($municipality === '') $errors[] = 'Municipality is required.';
if ($barangay === '')     $errors[] = 'Barangay is required.';
if (empty($lots))         $errors[] = 'At least one farm lot is required.';

foreach ($lots as $idx => $lot) {
    $lotNum  = trim($lot['lot_number']    ?? '');
    $lotSize = trim($lot['farm_size']     ?? '');
    $lotLoc  = trim($lot['farm_location'] ?? '');
    if ($lotNum === '') $errors[] = "Lot #{$idx}: Lot number is required.";
    if ($lotLoc === '') $errors[] = "Lot #{$idx}: Farm location is required.";
    if ($lotSize === '' || !is_numeric($lotSize) || floatval($lotSize) <= 0) {
        $errors[] = "Lot #{$idx}: Farm size must be a positive number.";
    }
}

if (empty($errors)) {
    $safeEmail = $conn->real_escape_string($email);
    $c1 = $conn->query("SELECT id FROM users WHERE email='$safeEmail' LIMIT 1");
    $c2 = $conn->query("SELECT id FROM farmers WHERE email='$safeEmail' LIMIT 1");
    if ($c1 && $c1->num_rows > 0) $errors[] = "Email \"$email\" is already registered.";
    elseif ($c2 && $c2->num_rows > 0) $errors[] = "A farmer with email \"$email\" already exists.";
}

if (!empty($errors)) {
    echo json_encode(['status' => 'error', 'message' => implode(' ', $errors)]);
    exit;
}

$hashedPw = password_hash($password, PASSWORD_BCRYPT);

$conn->begin_transaction();

try {
    $safeFirst = $conn->real_escape_string($first_name);
    $safeLast  = $conn->real_escape_string($last_name);
    $safeEmail = $conn->real_escape_string($email);
    $fullName  = $conn->real_escape_string("$first_name $last_name");

    $conn->query("
        INSERT INTO users (name, email, password, user_role, created_at)
        VALUES ('$fullName', '$safeEmail', '$hashedPw', 'farmer', NOW())
    ");
    $userId = $conn->insert_id;
    if (!$userId) throw new Exception('Failed to create user account.');

    $safeMiddle = $conn->real_escape_string($middle_name);
    $safeSex    = $conn->real_escape_string($sex);
    $safePhone  = $conn->real_escape_string($phone);
    $safeProv   = $conn->real_escape_string($province);
    $safeMuni   = $conn->real_escape_string($municipality);
    $safeBgy    = $conn->real_escape_string($barangay);
    $safeDOB    = $date_of_birth !== '' ? "'" . $conn->real_escape_string($date_of_birth) . "'" : 'NULL';

    if ($date_of_birth !== '') {
        $ageSQL = "TIMESTAMPDIFF(YEAR, $safeDOB, CURDATE())";
    } elseif ($manual_age !== '' && is_numeric($manual_age)) {
        $ageSQL = intval($manual_age);
    } else {
        $ageSQL = 'NULL';
    }

    $conn->query("
        INSERT INTO farmers
            (first_name, middle_name, last_name, sex,
             email, password, phone,
             province, municipality, barangay,
             date_of_birth, age,
             user_id, status, created_at)
        VALUES
            ('$safeFirst', '$safeMiddle', '$safeLast', '$safeSex',
             '$safeEmail', '$hashedPw', '$safePhone',
             '$safeProv', '$safeMuni', '$safeBgy',
             $safeDOB, $ageSQL,
             $userId, 'Active', NOW())
    ");
    $farmerId = $conn->insert_id;
    if (!$farmerId) throw new Exception('Failed to create farmer profile.');

    $latestLotAssocId = null;

    foreach ($lots as $lot) {
        $lotNum   = $conn->real_escape_string(trim($lot['lot_number']));
        $lotSize  = floatval($lot['farm_size']);
        $lotLoc   = $conn->real_escape_string(trim($lot['farm_location']));
        $lotProv  = $conn->real_escape_string(trim($lot['province'] ?? 'Zamboanga Del Sur'));
        $lotMuni  = $conn->real_escape_string(trim($lot['municipality'] ?? $municipality));
        $lotBgy   = $conn->real_escape_string(trim($lot['barangay'] ?? $barangay));
        
        if (isset($lot['association_id']) && $lot['association_id'] !== '') {
            $assocVal = intval($lot['association_id']);
            $lotAssoc = $assocVal;
            $latestLotAssocId = $assocVal; // Keeps tracks of the latest valid association ID
        } else {
            $lotAssoc = 'NULL';
        }

        $conn->query("
            INSERT INTO farmer_lots
                (farmer_id, lot_number, farm_location, farm_size,
                 province, municipality, barangay, association_id, status, created_at)
            VALUES
                ($farmerId, '$lotNum', '$lotLoc', $lotSize,
                 '$lotProv', '$lotMuni', '$lotBgy', $lotAssoc, 'Active', NOW())
        ");
        if ($conn->affected_rows < 1) throw new Exception("Failed to save lot: $lotNum");
    }

    // Sync latest active lot's association_id back to farmers table
    if ($latestLotAssocId !== null) {
        $conn->query("UPDATE farmers SET association_id = $latestLotAssocId WHERE id = $farmerId");
    }

    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => "Farmer \"$display_name\" registered successfully."]);
    exit;

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => 'Registration failed: ' . $e->getMessage()]);
    exit;
}