<?php
require_once '../includes/config.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: da_staff_association.php');
    exit;
}

$id          = intval($_POST['id']                    ?? 0);
$phone       = trim($_POST['phone']                   ?? '');
$pres_email  = trim($_POST['president_email']         ?? '');
$pres_phone  = trim($_POST['president_phone']         ?? '');
$pres_muni   = trim($_POST['president_municipality']  ?? '');
$pres_brgy   = trim($_POST['president_barangay']      ?? '');

if ($id <= 0) {
    $_SESSION['flash_error'] = 'Invalid association ID.';
    header('Location: staff_associations.php');
    exit;
}

$conn->begin_transaction();
try {
    // 1. Update association phone
    $s1 = $conn->prepare("UPDATE associations SET phone = ? WHERE id = ?");
    $s1->bind_param("si", $phone, $id);
    $s1->execute();
    $s1->close();

    // 2. Fetch President details for user table syncing
    $pres_query = $conn->prepare("SELECT user_id FROM presidents WHERE association_id = ?");
    $pres_query->bind_param("i", $id);
    $pres_query->execute();
    $pres_res = $pres_query->get_result();
    $pres_data = $pres_res->fetch_assoc();
    $pres_user_id = $pres_data['user_id'] ?? null;
    $pres_query->close();

    // 3. Update president record
    $s2 = $conn->prepare("UPDATE presidents SET
        email        = ?,
        phone        = ?,
        municipality = ?,
        barangay     = ?
        WHERE association_id = ?");
    $s2->bind_param(
        "ssssi",
        $pres_email,
        $pres_phone,
        $pres_muni,
        $pres_brgy,
        $id
    );
    $s2->execute();
    $s2->close();

    // 4. Update president email in user table if linked user_id exists
    if ($pres_user_id) {
        $s3 = $conn->prepare("UPDATE users SET email = ? WHERE id = ?");
        $s3->bind_param("si", $pres_email, $pres_user_id);
        $s3->execute();
        $s3->close();
    }

    $conn->commit();
    $_SESSION['flash_success'] = 'Association updated successfully!';

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['flash_error'] = 'Failed to update: ' . $e->getMessage();
}

header('Location: staff_associations.php');
exit;