<?php
session_start();
require_once '../includes/config.php';

header('Content-Type: application/json');

$first_name = trim($_POST['first_name'] ?? '');
$last_name  = trim($_POST['last_name']  ?? '');
$email      = trim($_POST['email']      ?? '');

$response = [
    'exists'       => false,
    'email_exists' => false
];

if (!empty($first_name) && !empty($last_name)) {
    $sf = $conn->real_escape_string($first_name);
    $sl = $conn->real_escape_string($last_name);
    $res = $conn->query("SELECT id FROM farmers WHERE LOWER(first_name) = LOWER('$sf') AND LOWER(last_name) = LOWER('$sl') LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $response['exists'] = true;
    }
}

if (!empty($email)) {
    $se = $conn->real_escape_string($email);
    $c1 = $conn->query("SELECT id FROM users WHERE email = '$se' LIMIT 1");
    $c2 = $conn->query("SELECT id FROM farmers WHERE email = '$se' LIMIT 1");
    if (($c1 && $c1->num_rows > 0) || ($c2 && $c2->num_rows > 0)) {
        $response['email_exists'] = true;
    }
}

echo json_encode($response);
exit;