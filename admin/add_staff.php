<?php
// add_staff.php
session_start();

header('Content-Type: application/json');

$conn = new mysqli("localhost", "root", "", "agri_machinery");
if ($conn->connect_error) {
    echo json_encode(['status' => 'error', 'message' => "Connection failed: " . $conn->connect_error]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => "Invalid request method."]);
    exit;
}

// ── Collect & sanitize inputs ──────────────────────────────────
$first_name   = trim($_POST['first_name']   ?? '');
$middle_name  = trim($_POST['middle_name']  ?? '');
$last_name    = trim($_POST['last_name']    ?? '');
$date_of_birth = trim($_POST['date_of_birth'] ?? '');
$age          = isset($_POST['age']) && $_POST['age'] !== '' ? (int)$_POST['age'] : null;
$province     = trim($_POST['province']     ?? '');
$municipality = trim($_POST['municipality'] ?? '');
$barangay     = trim($_POST['barangay']     ?? '');
$email        = trim($_POST['email']        ?? '');
$password     = $_POST['password']          ?? '';
$confirm      = $_POST['confirm_password']  ?? '';

// ── Sanitize Names (Remove numbers) ─────────────────────────────
$first_name  = preg_replace('/[0-9]/', '', $first_name);
$middle_name = preg_replace('/[0-9]/', '', $middle_name);
$last_name   = preg_replace('/[0-9]/', '', $last_name);

// ── Parse MM/DD/YYYY to YYYY-MM-DD ─────────────────────────────
$formatted_dob = null;
if (!empty($date_of_birth)) {
    $parts = explode('/', $date_of_birth);
    if (count($parts) === 3 && strlen($parts[2]) === 4) {
        $m = (int)$parts[0];
        $d = (int)$parts[1];
        $y = (int)$parts[2];
        if (checkdate($m, $d, $y)) {
            $formatted_dob = sprintf('%04d-%02d-%02d', $y, $m, $d);
        }
    }
}

// ── Full Name ──────────────────────────────────────────────────
$middle_initial = $middle_name ? ' ' . $middle_name . ' ' : ' ';
$full_name = trim($first_name . $middle_initial . $last_name);

// ── Validation ─────────────────────────────────────────────────
$errors = [];

if (empty($first_name)) $errors[] = "First name is required.";
if (empty($last_name))  $errors[] = "Last name is required.";
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "A valid email address is required.";
if (empty($password))   $errors[] = "Password is required.";
if (strlen($password) < 8) $errors[] = "Password must be at least 8 characters.";
if ($password !== $confirm) $errors[] = "Passwords do not match.";

// Duplicate email check
$check = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$check->bind_param("s", $email);
$check->execute();
$check->store_result();
if ($check->num_rows > 0) $errors[] = "An account with this email already exists.";
$check->close();

if (!empty($errors)) {
    echo json_encode(['status' => 'error', 'message' => implode(' ', $errors)]);
    exit;
}

// ── Insert into users ──────────────────────────────────────────
$hashed = password_hash($password, PASSWORD_BCRYPT);

$stmt = $conn->prepare("INSERT INTO users (name, email, password, user_role) VALUES (?, ?, ?, 'da staff')");
$stmt->bind_param("sss", $full_name, $email, $hashed);

if (!$stmt->execute()) {
    echo json_encode(['status' => 'error', 'message' => "Failed to create user account: " . $stmt->error]);
    exit;
}

$user_id = $conn->insert_id;
$stmt->close();

// ── Insert into da_staff ───────────────────────────────────────
$mn_value   = !empty($middle_name) ? $middle_name : null;
$prov_value = !empty($province)    ? $province    : null;
$muni_value = !empty($municipality)? $municipality: null;
$bgy_value  = !empty($barangay)    ? $barangay    : null;

$stmt2 = $conn->prepare("
    INSERT INTO da_staff
        (user_id, first_name, middle_name, last_name, date_of_birth, age, province, municipality, barangay)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
");

if (!$stmt2) {
    $conn->query("DELETE FROM users WHERE id = $user_id");
    echo json_encode(['status' => 'error', 'message' => "Prepare statement failed: " . $conn->error]);
    exit;
}

$stmt2->bind_param(
    "issssisss",
    $user_id,
    $first_name,
    $mn_value,
    $last_name,
    $formatted_dob,
    $age,
    $prov_value,
    $muni_value,
    $bgy_value
);

if (!$stmt2->execute()) {
    $conn->query("DELETE FROM users WHERE id = $user_id");
    echo json_encode(['status' => 'error', 'message' => "Failed to save staff profile: " . $stmt2->error]);
    exit;
}

$stmt2->close();
$conn->close();

echo json_encode(['status' => 'success', 'message' => "Account added successfully!"]);
exit;
?>