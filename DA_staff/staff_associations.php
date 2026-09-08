<?php
include('dastaff_header.php');
require_once '../includes/config.php';

$modal_error   = '';
$modal_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_association'])) {
    $name         = trim($_POST['name']);
    $email        = trim($_POST['email']);
    $password     = trim($_POST['password']);
    $confirm_pass = trim($_POST['confirm_password']);
    $province     = trim($_POST['province']);
    $municipality = trim($_POST['municipality']);
    $barangay     = trim($_POST['barangay']);
    $phone        = trim($_POST['phone']);

    $pres_first   = trim($_POST['president_first_name']  ?? '');
    $pres_mid     = trim($_POST['president_middle_name']  ?? '');
    $pres_last    = trim($_POST['president_last_name']    ?? '');
    $pres_sex     = trim($_POST['president_sex']          ?? '');
    $pres_dob     = trim($_POST['president_dob']          ?? '');
    $pres_age     = intval($_POST['president_age']        ?? 0);
    $pres_prov    = trim($_POST['president_province']     ?? '');
    $pres_muni    = trim($_POST['president_municipality'] ?? '');
    $pres_brgy    = trim($_POST['president_barangay']     ?? '');
    $pres_phone   = trim($_POST['president_phone']        ?? '');
    $pres_email   = trim($_POST['president_email']        ?? '');

    if ($password !== $confirm_pass) {
        $modal_error = 'Passwords do not match.';
    } else {
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $modal_error = 'This association email is already registered.';
            $check->close();
        } else {
            $check->close();

            $check_pres = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $check_pres->bind_param("s", $pres_email);
            $check_pres->execute();
            $check_pres->store_result();

            if ($check_pres->num_rows > 0) {
                $modal_error = 'President email is already registered.';
                $check_pres->close();
            } else {
                $check_pres->close();

                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                $pres_dob_mysql = null;
                if (!empty($pres_dob)) {
                    $dobObj = DateTime::createFromFormat('m/d/Y', $pres_dob);
                    if ($dobObj) {
                        $pres_dob_mysql = $dobObj->format('Y-m-d');
                    }
                }

                $conn->begin_transaction();

                try {
                    // 1. Insert association user account
                    $stmt_assoc_user = $conn->prepare(
                        "INSERT INTO users (name, email, password, user_role) VALUES (?, ?, ?, 'associations')"
                    );
                    $stmt_assoc_user->bind_param("sss", $name, $email, $hashed_password);
                    $stmt_assoc_user->execute();
                    $assoc_user_id = $conn->insert_id;
                    $stmt_assoc_user->close();

                    // 2. Insert president user account
                    $pres_full_name  = trim("$pres_first $pres_mid $pres_last");
                    $pres_hashed     = password_hash('123456', PASSWORD_DEFAULT);
                    $pres_role       = 'associations';

                    $stmt_pres_user = $conn->prepare(
                        "INSERT INTO users (name, email, password, user_role) VALUES (?, ?, ?, ?)"
                    );
                    $stmt_pres_user->bind_param("ssss", $pres_full_name, $pres_email, $pres_hashed, $pres_role);
                    $stmt_pres_user->execute();
                    $pres_user_id = $conn->insert_id;
                    $stmt_pres_user->close();

                    // 3. Insert association record
                    $stmt_assoc = $conn->prepare(
                        "INSERT INTO associations 
                         (name, email, password, province, municipality, barangay, phone, user_id)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                    );
                    $stmt_assoc->bind_param(
                        "sssssssi",
                        $name, $email, $hashed_password,
                        $province, $municipality, $barangay,
                        $phone, $assoc_user_id
                    );
                    $stmt_assoc->execute();
                    $assoc_id = $conn->insert_id;
                    $stmt_assoc->close();

                    // 4. Insert president record
                    $stmt_pres = $conn->prepare(
                        "INSERT INTO presidents 
                         (user_id, association_id, first_name, middle_name, last_name,
                          sex, date_of_birth, age, province, municipality, barangay, phone, email)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    );
                    $stmt_pres->bind_param(
                        "iisssssisssss",
                        $pres_user_id, $assoc_id,
                        $pres_first, $pres_mid, $pres_last,
                        $pres_sex, $pres_dob_mysql, $pres_age,
                        $pres_prov, $pres_muni, $pres_brgy,
                        $pres_phone, $pres_email
                    );
                    $stmt_pres->execute();
                    $pres_id = $conn->insert_id;
                    $stmt_pres->close();

                    // 5. Update association with president_id
                    $stmt_update = $conn->prepare(
                        "UPDATE associations SET president_id = ? WHERE id = ?"
                    );
                    $stmt_update->bind_param("ii", $pres_id, $assoc_id);
                    $stmt_update->execute();
                    $stmt_update->close();

                    $conn->commit();
                    $modal_success = 'Association added successfully!';

                } catch (Exception $e) {
                    $conn->rollback();
                    $modal_error = 'Error adding association: ' . $e->getMessage();
                }
            }
        }
    }
}

// Check session success alerts
if (isset($_SESSION['flash_success'])) {
    $modal_success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

// Fetch existing data for real-time duplication checking via Javascript
$existing_assoc_query = $conn->query("SELECT name, email, phone FROM associations");
$existing_assocs = [];
if ($existing_assoc_query) {
    while ($r = $existing_assoc_query->fetch_assoc()) $existing_assocs[] = $r;
}

$existing_pres_query = $conn->query("SELECT first_name, middle_name, last_name, email, phone FROM presidents");
$existing_pres = [];
if ($existing_pres_query) {
    while ($r = $existing_pres_query->fetch_assoc()) $existing_pres[] = $r;
}

// Search & Filter state matching DA Official Association
$search_field  = $_GET['search_field']  ?? 'All';
$search_term   = $_GET['search_term']   ?? '';
$status_filter = $_GET['statusDropdown'] ?? '';
$from_date     = $_GET['from_date']     ?? '';
$to_date       = $_GET['to_date']       ?? '';
$field_changed = $_GET['field_changed'] ?? '0';

$search_term   = trim($search_term);
$status_filter = trim($status_filter);
$from_date     = trim($from_date);
$to_date       = trim($to_date);

$where = [];
$col_check  = $conn->query("SHOW COLUMNS FROM associations LIKE 'status'");
$has_status = ($col_check && $col_check->num_rows > 0);

if ($field_changed !== '1') {
    if ($search_field === 'name' && $search_term !== '') {
        $safe = $conn->real_escape_string($search_term);
        $where[] = "a.name LIKE '%$safe%'";
    } elseif ($search_field === 'address' && $search_term !== '') {
        $parts = array_map('trim', explode(',', $search_term));
        if (count($parts) > 1 && !empty($parts[1])) {
            $safeBrgy = $conn->real_escape_string($parts[0]);
            $safeMuni = $conn->real_escape_string($parts[1]);
            $where[] = "a.barangay LIKE '%$safeBrgy%' AND a.municipality LIKE '%$safeMuni%'";
        } else {
            $safe = $conn->real_escape_string($search_term);
            $where[] = "(a.barangay LIKE '%$safe%' OR a.municipality LIKE '%$safe%' OR a.province LIKE '%$safe%')";
        }
    } elseif ($search_field === 'president' && $search_term !== '') {
        $safe = $conn->real_escape_string($search_term);
        $where[] = "(p.first_name LIKE '%$safe%' OR p.middle_name LIKE '%$safe%' OR p.last_name LIKE '%$safe%' OR CONCAT(p.first_name, ' ', COALESCE(p.middle_name,''), ' ', p.last_name) LIKE '%$safe%')";
    } elseif ($search_field === 'Status') {
        if (!empty($status_filter) && $has_status) {
            $safeStatus = $conn->real_escape_string($status_filter);
            $where[] = "a.status = '$safeStatus'";
        }
        if (!empty($from_date) && !empty($to_date)) {
            $safeFrom = $conn->real_escape_string($from_date);
            $safeTo   = $conn->real_escape_string($to_date);
            $where[]  = "DATE(a.created_at) BETWEEN '$safeFrom' AND '$safeTo'";
        }
    } elseif ($search_field === 'registered_date') {
        if (!empty($from_date) && !empty($to_date)) {
            $safeFrom = $conn->real_escape_string($from_date);
            $safeTo   = $conn->real_escape_string($to_date);
            $where[]  = "DATE(a.created_at) BETWEEN '$safeFrom' AND '$safeTo'";
        }
    }
}

$where_sql  = !empty($where) ? "WHERE " . implode(" AND ", $where) : '';
$status_col = $has_status ? ", a.status" : ", 'Active' AS status";

$query = "SELECT 
            a.id, a.name, a.province, a.municipality, a.barangay, a.phone,
            DATE_FORMAT(a.created_at, '%m/%d/%Y') AS registered_at
            $status_col,
            CONCAT(p.first_name, ' ', COALESCE(p.middle_name,''), ' ', p.last_name) AS president_name
          FROM associations a
          LEFT JOIN presidents p ON p.id = a.president_id
          $where_sql
          ORDER BY a.name";
$result = $conn->query($query);
$totalRows = ($result) ? $result->num_rows : 0;

$printFromFormatted = (!empty($from_date)) ? date('F j, Y', strtotime($from_date)) : '';
$printToFormatted   = (!empty($to_date))   ? date('F j, Y', strtotime($to_date))   : '';
$hasDateRange       = ($search_field === 'Status' || $search_field === 'registered_date') && !empty($from_date) && !empty($to_date);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Associations | AMRMS</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.css">
  <style>
    .main-content {
      height: auto !important;
      padding: 50px 20px;
    }

    h2 {
      font-size: 28px;
      color: #2d7a2d;
      font-weight: bold;
      text-align: center;
      text-shadow: 1px 1px 3px rgba(14, 4, 4, 0.7);
      margin: 10px 0 0 0;
      padding-bottom: 1px;
    }

    .search-form {
      display: flex;
      align-items: center;
      flex-wrap: wrap;
      gap: 10px;
      margin-top: 15px;
    }

    select, input[type="text"], button {
      padding: 8px 12px;
      font-size: 13px;
      border: 1px solid #ccc;
      border-radius: 6px;
    }

    button {
      background-color: #2d7a2d;
      color: white;
      border: none;
      cursor: pointer;
      transition: background-color 0.3s, opacity 0.3s;
    }

    button:hover:not(:disabled) { background-color: #256725; }

    button:disabled {
      background-color: #a5d6a5 !important;
      color: #ffffff !important;
      cursor: not-allowed !important;
      opacity: 0.6;
    }

    .flatpickr-input {
      padding: 8px 12px !important;
      font-size: 14px !important;
      border: 1px solid #ccc !important;
      border-radius: 6px !important;
      background: white !important;
      color: #333 !important;
      cursor: pointer !important;
      width: 130px !important;
      box-sizing: border-box !important;
    }

    .flatpickr-input:focus {
      outline: none !important;
      border-color: #2d7a2d !important;
    }

    label {
      color: #000000;
      font-weight: bold;
      font-size: 14px;
      margin-right: 5px;
      text-shadow: 1px 1px 3px rgba(14, 4, 4, 0.7);
    }

    #from_label, #to_label { color: #000000; }

    .action-buttons {
      display: flex;
      justify-content: center;
      align-items: center;
      gap: 10px;
      margin-top: 20px;
    }

    .action-buttons button {
      background-color: #2d7a2d;
      color: white;
      border: none;
      padding: 10px 20px;
      border-radius: 6px;
      font-size: 15px;
      cursor: pointer;
      transition: 0.2s;
      font-weight: 600;
    }

    .action-buttons button:hover:not(:disabled) {
      background-color: #1a5c1a;
      transform: scale(1.03);
    }

    .table-container {
      border: 1px solid #ddd;
      border-radius: 8px;
      background-color: white;
      box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
      margin-top: 10px;
      max-height: 330px;
      overflow-y: auto;
      overflow-x: auto;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      background-color: white;
      table-layout: fixed;
    }

    table th:nth-child(1), table td:nth-child(1) { width: 120px; white-space: nowrap; }
    table th:nth-child(2), table td:nth-child(2) { width: 220px; word-break: break-word; }
    table th:nth-child(3), table td:nth-child(3) { width: 250px; word-break: break-word; }
    table th:nth-child(4), table td:nth-child(4) { width: 180px; word-break: break-word; }
    table th:nth-child(5), table td:nth-child(5) { width: 120px; white-space: nowrap; }
    table th:nth-child(6), table td:nth-child(6) { width: 90px; }

    th, td {
      padding: 10px 10px;
      text-align: center !important;
      border-bottom: 1px solid #ddd;
      vertical-align: middle;
      line-height: 1.35;
      font-size: 13px;
      box-sizing: border-box;
    }

    thead {
      position: sticky;
      top: 0;
      z-index: 5;
    }

    th {
      background-color: #2d7a2d;
      color: white;
      font-weight: 600;
      white-space: nowrap;
    }

    tbody tr { cursor: pointer; }
    tr:hover { background-color: #f9fafb; }
    .table-active { background-color: #d9fdd9 !important; }

    .preparedBy, .print-subtitle, .report-date { display: none; }

    /* Inline Validation Hints */
    .field-hint {
      font-size: 0.75rem;
      margin-top: 3px;
      display: none;
      align-items: center;
      gap: 4px;
      font-weight: 500;
    }
    .field-hint.error   { color: #dc2626; display: flex; }
    .field-hint.success { color: #16a34a; display: flex; }

    /* Modal Overlay & Styling */
    .modal-overlay {
      display: none; position: fixed; inset: 0;
      background: rgba(0,0,0,0.55); z-index: 9999;
      justify-content: center; align-items: center;
      backdrop-filter: blur(3px);
    }
    .modal-overlay.active { display: flex; }
    .modal-box {
      background: white; border-radius: 14px; width: 920px;
      max-width: 96%; max-height: 92vh; overflow-y: auto;
      box-shadow: 0 20px 40px rgba(0,0,0,0.2);
    }
    .modal-head {
      background: linear-gradient(135deg, #2d7a2d, #1a5c1a);
      padding: 20px 24px; border-radius: 14px 14px 0 0;
      display: flex; justify-content: space-between; align-items: center;
      position: sticky; top: 0; z-index: 10;
    }
    .modal-head h3 { color: white; font-size: 20px; font-weight: 700; margin: 0; }
    .modal-head p  { color: rgba(255,255,255,0.8); font-size: 13px; margin: 4px 0 0; }
    .modal-x {
      width: 32px; height: 32px; background: rgba(255,255,255,0.2);
      border: none; border-radius: 50%; color: white; font-size: 18px;
      cursor: pointer; display: flex; align-items: center; justify-content: center;
    }
    .modal-x:hover { background: rgba(255,255,255,0.35); }
    .modal-bd { padding: 28px 32px; }
    .m-sec {
      font-size: 12px; font-weight: 700; color: #2d7a2d;
      text-transform: uppercase; letter-spacing: .5px; margin: 0px 0 12px;
      display: flex; align-items: center; gap: 8px;
      padding-bottom: 8px; border-bottom: 2px solid #e8f5e9;
    }
    .m-sec:first-child { margin-top: 0; }
    .m-row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; margin-bottom: 12px; }
    .m-row-4 { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 12px; margin-bottom: 12px; }
    .m-grp { display: flex; flex-direction: column; gap: 5px; }
    .m-grp label { font-size: 13px; font-weight: 600; color: #374151; text-shadow: none; }
    .m-grp label .req { color: #dc2626; }
    .m-wrap { position: relative; }
    .m-wrap .m-ico {
      position: absolute; left: 11px; top: 50%;
      transform: translateY(-50%); color: #9ca3af; font-size: 13px; pointer-events: none;
    }
    .m-grp input, .m-grp select {
      width: 100%; padding: 9px 12px 9px 34px;
      border: 2px solid #e5e7eb; border-radius: 7px;
      font-size: 14px; font-family: inherit; color: #1f2937;
      transition: border-color .2s, background .2s; background: white; box-sizing: border-box;
    }
    .m-grp select { padding-left: 34px; appearance: none; cursor: pointer; }
    .m-grp input:focus, .m-grp select:focus { outline: none; border-color: #2d7a2d; box-shadow: 0 0 0 3px rgba(45,122,45,.1); }
    .m-foot { display: flex; gap: 10px; justify-content: flex-end; padding-top: 12px; border-top: 1px solid #e5e7eb; margin-top: 16px; }
    .m-submit { padding: 10px 24px; background: #2d7a2d; color: white; border: none; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px; }
    .m-submit:hover:not(:disabled) { background: #256725; }
    .m-submit:disabled { background-color: #a5d6a5 !important; cursor: not-allowed !important; opacity: 0.6; }

    /* EDIT MODAL */
    @keyframes modalIn {
      from { opacity:0; transform: scale(0.96) translateY(16px); }
      to   { opacity:1; transform: scale(1) translateY(0); }
    }
    #editModal {
      display: none; position: fixed; inset: 0;
      background: rgba(0,0,0,0.55); backdrop-filter: blur(6px);
      justify-content: center; align-items: center; z-index: 9999; padding: 16px;
    }
    .edit-modal-box {
      background: #fff; border-radius: 16px; width: 100%; max-width: 700px;
      max-height: 90vh; display: flex; flex-direction: column;
      box-shadow: 0 20px 50px rgba(0,0,0,0.22);
      animation: modalIn 0.25s cubic-bezier(.34,1.2,.64,1) both;
      overflow: hidden;
    }
    .edit-modal-head {
      background: linear-gradient(120deg, #1e6b35 0%, #2d9148 60%, #37a85a 100%);
      padding: 16px 22px; display: flex; align-items: center;
      justify-content: space-between; flex-shrink: 0;
    }
    .edit-modal-head .head-title { color: #fff; font-size: 1.05rem; font-weight: 700; }
    .edit-modal-head .head-sub   { color: rgba(255,255,255,0.8); font-size: 0.78rem; margin-top: 2px; }
    .edit-modal-close {
      background: rgba(255,255,255,0.15); border: none; color: #fff;
      font-size: 20px; width: 32px; height: 32px; border-radius: 50%;
      cursor: pointer; display: flex; align-items: center; justify-content: center;
    }
    .edit-modal-close:hover { background: rgba(255,255,255,0.28); transform: rotate(90deg); }
    .edit-modal-body { padding: 20px 22px; overflow-y: auto; flex: 1; }
    .edit-modal-footer {
      padding: 12px 22px; background: #f9fafb; border-top: 1px solid #e5e7eb;
      display: flex; gap: 10px; justify-content: flex-end; flex-shrink: 0;
    }
    .edit-sec-label {
      font-size: 0.68rem; font-weight: 700; text-transform: uppercase;
      letter-spacing: 0.06em; color: #2d7d46; margin: 0 0 10px;
      display: flex; align-items: center; gap: 6px; width: 100%;
    }
    .edit-sec-label::after { content: ''; flex: 1; height: 1px; background: #d4edda; display: block; }
    .ef-grp { display: flex; flex-direction: column; gap: 4px; }
    .ef-grp label { color: #374151; font-weight: 600; font-size: 0.75rem; margin-bottom: 2px; text-shadow: none; }
    .ef-grp label .req { color: #dc2626; }
    .ef-input-ro {
      padding: 8px 10px; border: 1.5px solid #e5e7eb; border-radius: 7px;
      font-size: 0.83rem; background: #f3f4f6; color: #9ca3af;
      cursor: not-allowed; width: 100%; box-sizing: border-box; font-family: inherit;
    }
    .ef-input-rw {
      padding: 8px 10px; border: 1.5px solid #e5e7eb; border-radius: 7px;
      font-size: 0.83rem; background: #f9fafb; color: #111;
      width: 100%; box-sizing: border-box; font-family: inherit;
      outline: none; transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
    }
    .ef-input-rw:focus { border-color: #2d9148; background: #fff; box-shadow: 0 0 0 3px rgba(45,145,72,0.12); }

    /* Print Styles matching DA Official */
    @media print {
      .search-form, .action-buttons, .usernames-bar, header, nav, .header, .navbar { display: none !important; }

      h2 {
        display: block !important;
        color: #2d7a2d !important;
        text-shadow: none !important;
        margin: 0 0 5px 0 !important;
        padding: 0 !important;
        text-align: center !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
      }

      .print-subtitle {
        display: block !important;
        text-align: center;
        font-size: 14px;
        font-weight: bold;
        color: #333;
        margin-bottom: 15px;
      }

      .report-date {
        display: block !important;
        text-align: left;
        font-size: 12px;
        color: #333;
        margin-bottom: 10px;
        font-weight: bold;
      }

      body {
        background: white !important;
        overflow: visible !important;
        margin: 0 !important;
        padding: 0 !important;
      }

      .main-content {
        height: auto !important;
        overflow: visible !important;
        padding: 0 !important;
        margin-top: 0 !important;
      }

      .table-container {
        border: none !important;
        box-shadow: none !important;
        overflow: visible !important;
        margin-top: 0 !important;
        max-height: none !important;
      }

      table { table-layout: auto !important; width: 100% !important; }
      thead { position: static !important; display: table-header-group !important; }
      tbody tr { display: table-row !important; }

      table th, table td, table th:nth-child(n), table td:nth-child(n) {
        width: auto !important;
        white-space: normal !important;
        word-break: normal !important;
        word-wrap: normal !important;
        padding: 8px 6px !important;
        font-size: 11px !important;
        text-align: center !important;
      }

      th {
        background-color: #2d7a2d !important;
        color: white !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
      }

      .preparedBy {
        display: block !important;
        text-align: left;
        margin-top: 85px !important;
        font-size: 15px !important;
        color: #000000 !important;
      }
    }
  </style>
</head>

<body>
<div class="main-content">
  <h2>List of Associations</h2>

  <?php if ($hasDateRange): ?>
      <div class="print-subtitle">From <?= htmlspecialchars($printFromFormatted) ?> to <?= htmlspecialchars($printToFormatted) ?></div>
  <?php endif; ?>

  <form class="search-form" method="GET" id="searchForm">
    <input type="hidden" name="field_changed" id="field_changed" value="0">
    <select name="search_field" id="search_field" onchange="handleFieldChange()">
      <option value="All"             <?= ($search_field === 'All')             ? 'selected' : '' ?>>All</option>
      <option value="name"            <?= ($search_field === 'name')            ? 'selected' : '' ?>>Association Name</option>
      <option value="address"         <?= ($search_field === 'address')         ? 'selected' : '' ?>>Address</option>
      <option value="president"       <?= ($search_field === 'president')       ? 'selected' : '' ?>>Association President</option>
      <option value="Status"          <?= ($search_field === 'Status')          ? 'selected' : '' ?>>Status</option>
      <option value="registered_date" <?= ($search_field === 'registered_date') ? 'selected' : '' ?>>Registered Date</option>
    </select>

    <input type="text" name="search_term" id="textInput" placeholder="Enter search..."
           value="<?= htmlspecialchars($search_term) ?>" style="display:none;" oninput="validateAndCheckState()">

    <select name="statusDropdown" id="statusDropdown" style="display:none;" onchange="validateAndCheckState()">
      <option value="" disabled <?= ($status_filter === '') ? 'selected' : '' ?> hidden>Select Status</option>
      <option value="Active"   <?= ($status_filter === 'Active')   ? 'selected' : '' ?>>Active</option>
      <option value="Inactive" <?= ($status_filter === 'Inactive') ? 'selected' : '' ?>>Inactive</option>
    </select>

    <label for="from_date_display" id="from_label" style="display:none;">From</label>
    <input type="text" id="from_date_display" placeholder="mm/dd/yyyy" readonly style="display:none; width:130px;">
    <input type="hidden" name="from_date" id="from_date" value="<?= htmlspecialchars($from_date) ?>">

    <label for="to_date_display" id="to_label" style="display:none;">To</label>
    <input type="text" id="to_date_display" placeholder="mm/dd/yyyy" readonly style="display:none; width:130px;">
    <input type="hidden" name="to_date" id="to_date" value="<?= htmlspecialchars($to_date) ?>">

    <button type="submit" id="searchBtn" style="display:none;" disabled>Search</button>
  </form>

  <div class="report-date">Report Date: <?= date('F j, Y g:i A') ?></div>

  <div class="table-container">
    <table>
      <thead>
        <tr>
          <th>Registered Date</th>
          <th>Association Name</th>
          <th>Address</th>
          <th>Association President</th>
          <th>Association Contact No.</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php 
        if ($result && $result->num_rows > 0) {
          while ($row = $result->fetch_assoc()) {
            $address  = trim($row['barangay'].', '.$row['municipality'].', '.$row['province'], ', ');
            $status   = htmlspecialchars($row['status'] ?? 'Active');
            $regAt    = htmlspecialchars($row['registered_at'] ?? '—');
            $presName = trim((string)($row['president_name'] ?? ''));
            $presName = ($presName === '') ? '—' : htmlspecialchars($presName);
            echo "<tr class='clickable-row' data-id='{$row['id']}'>
                    <td>{$regAt}</td>
                    <td>" . htmlspecialchars($row['name']) . "</td>
                    <td>" . htmlspecialchars($address) . "</td>
                    <td>{$presName}</td>
                    <td>" . htmlspecialchars($row['phone']) . "</td>
                    <td><span style='font-weight:bold;'>{$status}</span></td>
                  </tr>";
          }
        } else {
          echo "<tr><td colspan='6' style='text-align:center; padding:20px; color:#555;'>No records found</td></tr>";
        }
        ?>
      </tbody>
    </table>
    <div class="preparedBy">
        Prepared By:
        <span style="font-weight:bold;">DA Staff</span>
    </div>
  </div>

  <div class="action-buttons">
    <button id="addBtn">Add</button>
    <button id="editBtn" disabled>Edit</button>
    <button id="printBtn" <?= ($totalRows == 0) ? 'disabled' : '' ?>>Print</button>
  </div>
</div>

<!-- ADD ASSOCIATION MODAL -->
<div class="modal-overlay" id="addModal">
  <div class="modal-box">
    <div class="modal-head">
      <div>
        <h3>Add Association</h3>
        <p>Fill in the details to register a new association</p>
      </div>
    </div>

    <div class="modal-bd">
      <form method="POST" action="" id="addAssocForm">
        <input type="hidden" name="add_association" value="1">

        <div class="m-sec">🏢 Association Information</div>
        <div class="m-row-3">
          <div class="m-grp">
            <label>Association Name <span class="req">*</span></label>
            <div class="m-wrap">
              <i class="fas fa-users m-ico"></i>
              <input type="text" name="name" id="add_assoc_name" placeholder="Enter association name"
                     value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required oninput="validateAddFormState()">
            </div>
            <span class="field-hint" id="add_assoc_name_hint"></span>
          </div>
          <div class="m-grp">
            <label>Association Email <span class="req">*</span></label>
            <div class="m-wrap">
              <i class="fas fa-envelope m-ico"></i>
              <input type="email" name="email" id="add_assoc_email" placeholder="email@example.com"
                     value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required oninput="validateAddFormState()">
            </div>
            <span class="field-hint" id="add_assoc_email_hint"></span>
          </div>
          <div class="m-grp">
            <label>Association Phone <span class="req">*</span></label>
            <div class="m-wrap">
              <i class="fas fa-phone m-ico"></i>
              <input type="text" name="phone" id="add_assoc_phone" placeholder="09XXXXXXXXX" maxlength="11"
                     value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required oninput="validateAddFormState()">
            </div>
            <span class="field-hint" id="add_assoc_phone_hint"></span>
          </div>
        </div>

        <div class="m-row-3">
          <div class="m-grp">
            <label>Province</label>
            <div class="m-wrap">
              <i class="fas fa-map m-ico"></i>
              <input type="text" name="province" value="Zamboanga del Sur" readonly style="background:#f0fdf4; color:#166534; cursor:default; border-color:#86efac;">
            </div>
          </div>
          <div class="m-grp">
            <label>Municipality <span class="req">*</span></label>
            <div class="m-wrap">
              <i class="fas fa-city m-ico"></i>
              <select name="municipality" id="add_assoc_municipality" required onchange="updateAddBarangays(); validateAddFormState();">
                <option value="" disabled selected hidden>Select Municipality</option>
              </select>
            </div>
          </div>
          <div class="m-grp">
            <label>Barangay <span class="req">*</span></label>
            <div class="m-wrap">
              <i class="fas fa-home m-ico"></i>
              <select name="barangay" id="add_assoc_barangay" required disabled onchange="validateAddFormState();">
                <option value="" disabled selected hidden>Select Barangay</option>
              </select>
            </div>
          </div>
        </div>

        <div class="m-sec"><i class="fas fa-user-tie"></i> Association President Details</div>
        <div class="m-row-4">
          <div class="m-grp">
            <label>First Name <span class="req">*</span></label>
            <div class="m-wrap">
              <i class="fas fa-user m-ico"></i>
              <input type="text" name="president_first_name" id="add_pres_first" placeholder="First name"
                     value="<?= htmlspecialchars($_POST['president_first_name'] ?? '') ?>" required oninput="validateAddFormState()">
            </div>
          </div>
          <div class="m-grp">
            <label>Middle Name</label>
            <div class="m-wrap">
              <i class="fas fa-user m-ico"></i>
              <input type="text" name="president_middle_name" id="add_pres_mid" placeholder="Middle name"
                     value="<?= htmlspecialchars($_POST['president_middle_name'] ?? '') ?>" oninput="validateAddFormState()">
            </div>
          </div>
          <div class="m-grp">
            <label>Last Name <span class="req">*</span></label>
            <div class="m-wrap">
              <i class="fas fa-user m-ico"></i>
              <input type="text" name="president_last_name" id="add_pres_last" placeholder="Last name"
                     value="<?= htmlspecialchars($_POST['president_last_name'] ?? '') ?>" required oninput="validateAddFormState()">
            </div>
          </div>
          <div class="m-grp">
            <label>Sex <span class="req">*</span></label>
            <div class="m-wrap">
              <i class="fas fa-venus-mars m-ico"></i>
              <select name="president_sex" id="add_pres_sex" required onchange="validateAddFormState()">
                <option value="" disabled selected hidden>Select sex</option>
                <option value="Male"   <?= (($_POST['president_sex'] ?? '') === 'Male')   ? 'selected' : '' ?>>Male</option>
                <option value="Female" <?= (($_POST['president_sex'] ?? '') === 'Female') ? 'selected' : '' ?>>Female</option>
              </select>
            </div>
          </div>
        </div>

        <span class="field-hint" id="add_pres_name_hint" style="margin-bottom:10px;"></span>

        <div class="m-row-4">
          <div class="m-grp">
            <label>Date of Birth <span class="req">*</span></label>
            <div class="m-wrap">
              <input type="text" name="president_dob" id="pres_dob_fp" placeholder="mm/dd/yyyy" readonly required
                     value="<?= htmlspecialchars($_POST['president_dob'] ?? '') ?>" style="cursor:pointer; padding-left:12px;">
            </div>
          </div>
          <div class="m-grp">
            <label>Age</label>
            <div class="m-wrap">
              <i class="fas fa-hashtag m-ico"></i>
              <input type="number" name="president_age" id="pres_age_input" placeholder="Auto-calculated" min="1" max="120" readonly
                     value="<?= htmlspecialchars($_POST['president_age'] ?? '') ?>">
            </div>
          </div>
          <div class="m-grp">
            <label>President Email <span class="req">*</span></label>
            <div class="m-wrap">
              <i class="fas fa-envelope m-ico"></i>
              <input type="email" name="president_email" id="add_pres_email" placeholder="president@example.com"
                     value="<?= htmlspecialchars($_POST['president_email'] ?? '') ?>" required oninput="validateAddFormState()">
            </div>
            <span class="field-hint" id="add_pres_email_hint"></span>
          </div>
          <div class="m-grp">
            <label>President Phone <span class="req">*</span></label>
            <div class="m-wrap">
              <i class="fas fa-phone m-ico"></i>
              <input type="text" name="president_phone" id="add_pres_phone" placeholder="09XXXXXXXXX" maxlength="11"
                     value="<?= htmlspecialchars($_POST['president_phone'] ?? '') ?>" required oninput="validateAddFormState()">
            </div>
            <span class="field-hint" id="add_pres_phone_hint"></span>
          </div>
        </div>

        <div class="m-row-3">
          <div class="m-grp">
            <label>Province</label>
            <div class="m-wrap">
              <i class="fas fa-map m-ico"></i>
              <input type="text" name="president_province" value="Zamboanga del Sur" readonly style="background:#f0fdf4; color:#166534; cursor:default; border-color:#86efac;">
            </div>
          </div>
          <div class="m-grp">
            <label>Municipality <span class="req">*</span></label>
            <div class="m-wrap">
              <i class="fas fa-city m-ico"></i>
              <select name="president_municipality" id="add_pres_municipality" required onchange="updateAddPresBarangays(); validateAddFormState();">
                <option value="" disabled selected hidden>Select Municipality</option>
              </select>
            </div>
          </div>
          <div class="m-grp">
            <label>Barangay <span class="req">*</span></label>
            <div class="m-wrap">
              <i class="fas fa-home m-ico"></i>
              <select name="president_barangay" id="add_pres_barangay" required disabled onchange="validateAddFormState();">
                <option value="" disabled selected hidden>Select Barangay</option>
              </select>
            </div>
          </div>
        </div>

        <div class="m-sec"><i class="fas fa-lock"></i> Login Credentials</div>
        <input type="hidden" name="password" value="123456">
        <input type="hidden" name="confirm_password" value="123456">
        <div style="display:flex; align-items:center; gap:10px; padding:11px 14px; background:#f0fdf4; border:1.5px solid #86efac; border-radius:8px; font-size:13px; color:#166534; margin-bottom:4px;">
          <i class="fas fa-info-circle" style="font-size:15px; color:#2d7a2d;"></i>
          <span>Default password is automatically set to <strong>123456</strong>. The association can change it after logging in.</span>
        </div>

        <div class="m-foot">
          <button type="submit" class="m-submit" id="addSubmitBtn" disabled>Add Association</button>
          <button type="button" onclick="closeAddModal()" style="padding:9px 18px; background:#f5f5f5; color:#666; border:1px solid #e0e0e0; border-radius:8px; font-size:0.88rem; font-weight:600; cursor:pointer;">
            Close
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- EDIT ASSOCIATION MODAL -->
<div id="editModal">
  <div class="edit-modal-box">
    <div class="edit-modal-head">
      <div>
        <div class="head-title">✎ Edit Association</div>
        <div class="head-sub">Only editable fields are active</div>
      </div>
    </div>

    <div class="edit-modal-body">
      <form action="edit_association_action.php" method="POST" id="editAssocForm">
        <input type="hidden" name="id" id="editAssocId">

        <div class="edit-sec-label">
          Association Information
          <span style="font-weight:400; color:#9ca3af; text-transform:none; font-size:0.68rem;">(read-only)</span>
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px 12px; margin-bottom:14px;">
          <div class="ef-grp">
            <label>Association Name</label>
            <input class="ef-input-ro" type="text" id="editAssocName" readonly>
          </div>
          <div class="ef-grp">
            <label>Association Email</label>
            <input class="ef-input-ro" type="text" id="editAssocEmail" readonly>
          </div>
          <div class="ef-grp">
            <label>Association Phone <span class="req">*</span></label>
            <input class="ef-input-rw" type="text" name="phone" id="editAssocPhone"
                   placeholder="09XXXXXXXXX" maxlength="11" oninput="validateEditFormState()">
            <span class="field-hint" id="editAssocPhone_hint"></span>
          </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px 12px; margin-bottom:14px;">
          <div class="ef-grp">
            <label>Province</label>
            <input class="ef-input-ro" type="text" id="editAssocProvince" readonly>
          </div>
          <div class="ef-grp">
            <label>Municipality</label>
            <input class="ef-input-ro" type="text" id="editAssocMunicipality" readonly>
          </div>
          <div class="ef-grp">
            <label>Barangay</label>
            <input class="ef-input-ro" type="text" id="editAssocBarangay" readonly>
          </div>
        </div>

        <div class="edit-sec-label" style="margin-top:6px;">
          President Information
          <span style="font-weight:400; color:#9ca3af; text-transform:none; font-size:0.68rem;">(read-only — use Change President to reassign)</span>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:8px 12px; margin-bottom:14px;">
          <div class="ef-grp">
            <label>First Name</label>
            <input class="ef-input-ro" type="text" id="editPresFirstName" readonly>
          </div>
          <div class="ef-grp">
            <label>Middle Name</label>
            <input class="ef-input-ro" type="text" id="editPresMiddleName" readonly>
          </div>
          <div class="ef-grp">
            <label>Last Name</label>
            <input class="ef-input-ro" type="text" id="editPresLastName" readonly>
          </div>
          <div class="ef-grp">
            <label>Sex</label>
            <input class="ef-input-ro" type="text" id="editPresSex" readonly>
          </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:8px 12px; margin-bottom:14px;">
          <div class="ef-grp">
            <label>Date of Birth</label>
            <input class="ef-input-ro" type="text" id="editPresDob" readonly>
          </div>
          <div class="ef-grp">
            <label>Age</label>
            <input class="ef-input-ro" type="text" id="editPresAge" readonly>
          </div>
          <div class="ef-grp">
            <label>President Email <span class="req">*</span></label>
            <input class="ef-input-rw" type="email" name="president_email" id="editPresEmail"
                   placeholder="president@example.com" oninput="validateEditFormState()">
            <span class="field-hint" id="editPresEmail_hint"></span>
          </div>
          <div class="ef-grp">
            <label>President Phone <span class="req">*</span></label>
            <input class="ef-input-rw" type="text" name="president_phone" id="editPresPhone"
                   placeholder="09XXXXXXXXX" maxlength="11" oninput="validateEditFormState()">
            <span class="field-hint" id="editPresPhone_hint"></span>
          </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px 12px; margin-bottom:6px;">
          <div class="ef-grp">
            <label>Province</label>
            <input class="ef-input-ro" type="text" id="editPresProvince" readonly>
          </div>
          <div class="ef-grp">
            <label>Municipality <span class="req">*</span></label>
            <select name="president_municipality" id="editPresMunicipality" class="ef-input-rw" onchange="updateEditPresBarangays(); validateEditFormState();">
              <option value="" disabled selected hidden>Select Municipality</option>
            </select>
          </div>
          <div class="ef-grp">
            <label>Barangay <span class="req">*</span></label>
            <select name="president_barangay" id="editPresBarangay" class="ef-input-rw" disabled onchange="validateEditFormState();">
              <option value="" disabled selected hidden>Select Barangay</option>
            </select>
          </div>
        </div>

        <div style="margin-top:14px; padding-top:12px; border-top:2px dashed #e5e7eb; display:flex; align-items:center; justify-content:space-between;">
          <div style="font-size:0.78rem; color:#6b7280;">
            <i class="fas fa-info-circle" style="color:#3b82f6;"></i>
            To assign a new president, click the button →
          </div>
          <button type="button" onclick="openChangePresidentModal()" style="padding:9px 18px; background: #2d7a2d; color:white; border:none; border-radius:8px; font-size:0.88rem; font-weight:600; cursor:pointer; display:flex; align-items:center; gap:6px;">
            <i class="fas fa-user-edit"></i> Change President
          </button>
        </div>
      </form>
    </div>

    <div class="edit-modal-footer">
      <button type="submit" form="editAssocForm" id="editSaveBtn" disabled style="padding:9px 22px; background:#2d7d46; color:white; border:none; border-radius:8px; font-size:0.88rem; font-weight:700; cursor:pointer;">
        Save 
      </button>
      <button type="button" onclick="closeEditModal()" style="padding:9px 18px; background:#f5f5f5; color:#666; border:1px solid #e0e0e0; border-radius:8px; font-size:0.88rem; font-weight:600; cursor:pointer;">
        Close
      </button>
    </div>
  </div>
</div>

<!-- CHANGE PRESIDENT MODAL -->
<div id="changePresModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); backdrop-filter:blur(4px); justify-content:center; align-items:center; z-index:10000; padding:16px;">
  <div style="background:#fff; border-radius:16px; width:100%; max-width:600px; box-shadow:0 24px 60px rgba(0,0,0,0.25); overflow:hidden; animation: modalIn 0.25s cubic-bezier(.34,1.2,.64,1) both;">

    <div style="background:#2d7a2d; color:#fff; padding:18px 22px; display:flex; align-items:center; justify-content:space-between;">
      <div>
        <div style="font-size:1.05rem; font-weight:700;"><i class="fas fa-user-edit" style="margin-right:8px;"></i>Change President</div>
        <div style="color:rgba(251,252,251,0.8); font-size:0.78rem; margin-top:3px;">Assign a new president to this association</div>
      </div>
    </div>

    <div style="padding:24px 26px;">
      <form id="changePresForm" action="change_president_action.php" method="POST">
        <input type="hidden" name="association_id" id="cpAssocId">

        <div style="margin-bottom:18px;">
          <div style="font-size:0.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:#6b7280; margin-bottom:8px;">Current President</div>
          <div style="background:#f3f4f6; border-radius:8px; padding:11px 14px; font-size:14px; color:#374151; display:flex; align-items:center; gap:8px;">
            <i class="fas fa-user-circle" style="color:#9ca3af; font-size:18px;"></i>
            <span id="cpCurrentPresName" style="font-weight:600;"></span>
          </div>
        </div>

        <div style="font-size:0.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:#2d7a2d; margin-bottom:10px; padding-bottom:6px; border-bottom:2px solid #e8f5e9;">New President Details</div>

        <div style="display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:10px; margin-bottom:6px;">
          <div class="ef-grp">
            <label style="font-size:0.75rem; font-weight:600; color:#374151;">First Name <span style="color:#dc2626;">*</span></label>
            <input class="ef-input-rw" type="text" name="new_first_name" id="cpFirstName" placeholder="First name" oninput="validateChangePresState()">
          </div>
          <div class="ef-grp">
            <label style="font-size:0.75rem; font-weight:600; color:#374151;">Middle Name <span style="color:#dc2626;">*</span></label>
            <input class="ef-input-rw" type="text" name="new_middle_name" id="cpMiddleName" placeholder="Middle name" oninput="validateChangePresState()">
          </div>
          <div class="ef-grp">
            <label style="font-size:0.75rem; font-weight:600; color:#374151;">Last Name <span style="color:#dc2626;">*</span></label>
            <input class="ef-input-rw" type="text" name="new_last_name" id="cpLastName" placeholder="Last name" oninput="validateChangePresState()">
          </div>
          <div class="ef-grp">
            <label style="font-size:0.75rem; font-weight:600; color:#374151;">Sex <span style="color:#dc2626;">*</span></label>
            <select name="new_sex" id="cpSex" class="ef-input-rw" style="padding:8px 10px;" onchange="validateChangePresState()">
              <option value="" disabled selected hidden>Select sex</option>
              <option value="Male">Male</option>
              <option value="Female">Female</option>
            </select>
          </div>
        </div>

        <span class="field-hint" id="cp_name_hint" style="margin-bottom:10px;"></span>

        <div style="display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:10px; margin-bottom:12px;">
          <div class="ef-grp">
            <label style="font-size:0.75rem; font-weight:600; color:#374151;">Date of Birth <span style="color:#dc2626;">*</span></label>
            <input class="ef-input-rw" type="text" name="new_dob" id="cpDob" placeholder="mm/dd/yyyy" readonly style="cursor:pointer;">
          </div>
          <div class="ef-grp">
            <label style="font-size:0.75rem; font-weight:600; color:#374151;">Age</label>
            <input class="ef-input-ro" type="text" name="new_age" id="cpAge" placeholder="Auto-calculated" readonly style="background:#f0fdf4; color:#166534;">
          </div>
          <div class="ef-grp">
            <label style="font-size:0.75rem; font-weight:600; color:#374151;">Email <span style="color:#dc2626;">*</span></label>
            <input class="ef-input-rw" type="email" name="new_email" id="cpEmail" placeholder="president@example.com" oninput="validateChangePresState()">
            <span class="field-hint" id="cp_email_hint"></span>
          </div>
          <div class="ef-grp">
            <label style="font-size:0.75rem; font-weight:600; color:#374151;">Phone <span style="color:#dc2626;">*</span></label>
            <input class="ef-input-rw" type="text" name="new_phone" id="cpPhone" placeholder="09XXXXXXXXX" maxlength="11" oninput="validateChangePresState()">
            <span class="field-hint" id="cp_phone_hint"></span>
          </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px; margin-bottom:6px;">
          <div class="ef-grp">
            <label style="font-size:0.75rem; font-weight:600; color:#374151;">Province</label>
            <input class="ef-input-ro" type="text" name="new_province" id="cpProvince" value="Zamboanga del Sur" readonly style="background:#f0fdf4; color:#166534;">
          </div>
          <div class="ef-grp">
            <label style="font-size:0.75rem; font-weight:600; color:#374151;">Municipality <span style="color:#dc2626;">*</span></label>
            <select name="new_municipality" id="cpMunicipality" class="ef-input-rw" onchange="updateCpBarangays(); validateChangePresState();">
              <option value="" disabled selected hidden>Select Municipality</option>
            </select>
          </div>
          <div class="ef-grp">
            <label style="font-size:0.75rem; font-weight:600; color:#374151;">Barangay <span style="color:#dc2626;">*</span></label>
            <select name="new_barangay" id="cpBarangay" class="ef-input-rw" disabled onchange="validateChangePresState();">
              <option value="" disabled selected hidden>Select Barangay</option>
            </select>
          </div>
        </div>
      </form>
    </div>

    <div style="padding:14px 26px; background:#f9fafb; border-top:1px solid #e5e7eb; display:flex; gap:10px; justify-content:flex-end;">
      <button type="submit" form="changePresForm" id="cpSaveBtn" disabled style="padding:9px 22px; background:#2d7a2d; color:white; border:none; border-radius:8px; font-size:0.88rem; font-weight:700; cursor:pointer;">
        Save
      </button>
      <button onclick="closeChangePresModal()" style="padding:9px 18px; background:#f5f5f5; color:#666; border:1px solid #e0e0e0; border-radius:8px; font-size:0.88rem; font-weight:600; cursor:pointer;">
        Close
      </button>
    </div>
  </div>
</div>

<!-- POPUP RESPONSE MODAL -->
<div id="responsePopupModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); backdrop-filter:blur(6px); justify-content:center; align-items:center; z-index:12000; padding:16px;">
  <div style="background:#fff; border-radius:16px; width:100%; max-width:380px; box-shadow:0 20px 50px rgba(0,0,0,0.3); overflow:hidden; text-align:center;">
    <div style="background:linear-gradient(135deg,#14532d,#16a34a); padding:28px 22px 20px;">
      <div style="font-size:3rem; line-height:1; margin-bottom:8px;">✅</div>
      <div style="color:#fff; font-size:1.1rem; font-weight:700;" id="rpm_title">Success!</div>
    </div>
    <div style="padding:20px 22px 24px;">
      <p id="rpm_message" style="color:#374151; font-size:0.92rem; margin:0 0 20px; line-height:1.55;"></p>
      <button onclick="closeResponsePopupModal()" style="padding:10px 32px; background:linear-gradient(135deg,#14532d,#16a34a); color:white; border:none; border-radius:8px; font-size:0.9rem; font-weight:700; cursor:pointer;">OK</button>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.js"></script>
<script>
const existingAssocs = <?= json_encode($existing_assocs) ?>;
const existingPres   = <?= json_encode($existing_pres) ?>;

const zamboangaDelSurData = {
  "Pagadian City": ["Alegria", "Balangasan", "Balintawak", "Baloyboan", "Banale", "Bogo", "Bomba", "Buenavista", "Bulatok", "Bulawan", "Dampalan", "Danlugan", "Dao", "Datagan", "Deborok", "Ditoray", "Dumagoc", "Gatas", "Gubac", "Gubang", "Kagawasan", "Kahayagan", "Kalasan", "Kawit", "La Suerte", "Lala", "Lapidian", "Lenienza", "Lizon Valley", "Lourdes", "Lower Sibatang", "Lumad", "Lumbia", "Macasing", "Manga", "Muricay", "Napolan", "Palpalan", "Pedulonan", "Poloyagan", "San Francisco", "San Jose", "San Pedro", "Santa Lucia", "Santa Maria", "Santiago", "Santo Niño", "Tawagan Sur", "Tiguma", "Tuburan", "Tulangan", "Tulawas", "Upper Sibatang", "White Beach"],
  "Aurora": ["Acad", "Alang-alang", "Alegria", "Anonang", "Bagong Mandaue", "Bagong Maslog", "Bagong Oslob", "Bagong Pitogo", "Baki", "Balas", "Balide", "Balintawak", "Bayabas", "Bemposa", "Cabilinan", "Campo Uno", "Ceboneg", "Commonwealth", "Gubaan", "Inasagan", "Inroad", "Kahayagan East (Katipunan)", "Kahayagan West", "Kauswagan", "La Paz (Tinibtiban)", "La Victoria", "Lantungan", "Libertad", "Lintugop", "Lubid", "Maguikay", "Mahayahay", "Monte Alegre", "Montela", "Napo", "Panaghiusa", "Poblacion", "Resthouse", "Romarate", "San Jose", "San Juan", "Sapa Loboc", "Tagulalo", "Waterfall"],
  "Bayog": ["Baking", "Balukbahan", "Balumbunan", "Bantal", "Bobuan", "Camp Blessing", "Canoayan", "Conacon", "Dagum", "Damit", "Datagan", "Depase", "Depili", "Depore", "Deporehan", "Dimalinao", "Kahayagan", "Kanipaan", "Lamare", "Liba", "Matin-ao", "Matun-og", "Pangi (San Isidro)", "Poblacion", "Pulang Bato", "Salawagan", "Sigacad", "Supon"],
  "Dimataling": ["Bacayawan", "Baha", "Balanagan", "Baluno", "Binuay", "Buburay", "Grap", "Josefina", "Kagawasan", "Lalab", "Libertad", "Magahis", "Mahayag", "Mercedes", "Poblacion", "Saloagan", "San Roque", "Sugbay Uno", "Sumbato", "Sumpot", "Tinggabulong", "Tiniguangan", "Tipangi", "Upper Ludiong"],
  "Dinas": ["Bacawan", "Benuatan", "Beray", "Don Jose", "Dongos", "East Migpulao", "Guinicolalay", "Ignacio Garrata (New Mirapao)", "Kinacap", "Legarda 1", "Legarda 2", "Legarda 3", "Lower Dimaya", "Lucoban", "Ludiong", "Nangka", "Nian", "Old Mirapao", "Pisa-an", "Poblacion", "Proper Dimaya", "Sagacad", "Sambulawan", "San Isidro", "Songayan", "Sumpotan", "Tarakan", "Upper Dimaya", "Upper Sibul", "West Migpulao"],
  "Dumalinao": ["Anonang", "Bag-ong Misamis", "Bag-ong Silao", "Baga", "Baloboan", "Banta-ao", "Bibilik", "Calingayan", "Camalig", "Camanga", "Cuatro-cuatro", "Locuban", "Malasik", "Mama (San Juan)", "Matab-ang", "Mecolong", "Metokong", "Motosawa", "Pag-asa (Poblacion)", "Paglaum (Poblacion)", "Pantad", "Piniglibano", "Rebokon", "San Agustin", "Sibucao", "Sumadat", "Tikwas", "Tina", "Tubo-Pait", "Upper Dumalinao"],
  "Dumingag": ["Bag-ong Valencia", "Bagong Kauswagan", "Bagong Silang", "Bucayan", "Calumanggi", "Canibong", "Caridad", "Danlugan", "Dapiwak", "Datu Totocan", "Dilud", "Ditulan", "Dulian", "Dulop", "Guintananan", "Guitran", "Gumpingan", "La Fortuna", "Labangon", "Libertad", "Licabang", "Lipawan", "Lower Landing", "Lower Timonan", "Macasing", "Mahayahay", "Malagalad", "Manlabay", "Maralag", "Marangan", "New Basak", "Saad", "Salvador", "San Juan", "San Pablo (Poblacion)", "San Pedro (Poblacion)", "San Vicente", "Senote", "Sinonok", "Sunop", "Tagun", "Tamurayan", "Upper Landing", "Upper Timonan"],
  "Guipos": ["Bagong Oroquieta", "Baguitan", "Balongating", "Canunan", "Dacsol", "Dagohoy", "Dalapang", "Datagan", "Poblacion", "Guling", "Katipunan", "Lintum", "Litan", "Magting", "Regla", "Sikatuna", "Singclot"],
  "Josefina": ["Bogo Calabat", "Dawa", "Ebarle", "Gumahan", "Leonardo", "Litapan", "Lower Bagong Tudela", "Mansanas", "Moradji", "Nemeño", "Nopulan", "Sebukang", "Tagaytay Hill", "Upper Bagong Tudela"],
  "Kumalarang": ["Bogayo", "Bolisong", "Boyugan East", "Boyugan West", "Bualan", "Diplo", "Gawil", "Gusom", "Kitaan Dagat", "Lantawan", "Limamawan", "Mahayahay", "Pangi", "Picanan", "Poblacion", "Salagmanok", "Secade", "Suminalum"],
  "Labangan": ["Bagalupa", "Balimbingan", "Binayan", "Bokong", "Bulanit", "Cogonan", "Combo", "Dalapang", "Dimasangca", "Dipaya", "Langapod", "Lantian", "Lower Campo Islam", "Lower Pulacan", "Lower Sang-an", "New Labangan", "Noboran", "Old Labangan", "San Isidro", "Santa Cruz", "Tapodoc", "Tawagan Norte", "Upper Campo Islam", "Upper Pulacan", "Upper Sang-an"],
  "Lakewood": ["Baking", "Bagong Kahayag", "Biswangan", "Bululawan", "Dagum", "Gasa", "Gatub", "Poblacion", "Lukuan", "Matalang", "Sapang Pinoles", "Sebuguey", "Tiwales", "Tubod"],
  "Lapuyan": ["Bulawan", "Carpoc", "Danganan", "Dansal", "Dumara", "Linokmadalum", "Luanan", "Lubusan", "Mahalingeb", "Mandeg", "Maralag", "Maruing", "Molum", "Pampang", "Pantad", "Pingalay", "Poblacion", "Salambuyan", "San Jose", "Sayog", "Tabon", "Talabob", "Tiguha", "Tininghalang", "Tipasan", "Tugaya"],
  "Mahayag": ["Bag-ong Balamban", "Bag-ong Dalaguete", "Boniao", "Delusom", "Diwan", "Guripan", "Kaangayan", "Kabuhi", "Lourmah", "Lower Salug Daku", "Lower Santo Niño", "Malubo", "Manguiles", "Marabanan", "Panagaan", "Paraiso", "Pedagan", "Poblacion", "Pugwan", "San Isidro", "San Jose", "San Vicente", "Santa Cruz", "Sicpao", "Tuboran", "Tulan", "Tumapic", "Upper Salug Daku", "Upper Santo Niño"],
  "Margosatubig": ["Balintawak", "Bularong", "Digon", "Guinimanan", "Igat Island", "Josefina", "Kalian", "Kolot", "Limbatong", "Limamawan", "Lumbog", "Magahis", "Poblacion", "Sagua", "Talanusa", "Tiguian", "Tulapok"],
  "Midsalip": ["Bacahan", "Balonai", "Bibilop", "Buloron", "Cabaloran", "Canipay Norte", "Canipay Sur", "Cumaron", "Dakayakan", "Duelic", "Dumalinao", "Ecuan", "Golictop", "Guinabot", "Guitalos", "Guma", "Kahayagan", "Licuro-an", "Lumpunid", "Matalang", "New Katipunan", "New Unidos", "Palili", "Pawan", "Pili", "Pisompongan", "Piwan", "Poblacion A", "Poblacion B", "Sigapod", "Timbaboy", "Tulbong", "Tuluan"],
  "Molave": ["Alicia", "Ariosa", "Bagong Argao", "Bagong Gutlang", "Blancia", "Bogo Capalaran", "Culo", "Dalaon", "Dipolo", "Dontulan", "Gonosan", "Lower Dimalinao", "Lower Dimorok", "Mabuhay", "Madasigon", "Makuguihon", "Maloloy-on", "Miligan", "Parasan", "Rizal", "Santo Rosario", "Silangit", "Simata", "Sudlon", "Upper Dimorok"],
  "Pitogo": ["Balabawan", "Balong-balong", "Colojo", "Liasan", "Liguac", "Limbayan", "Lower Paniki-an", "Matin-ao", "Panubigan", "Poblacion", "Punta Flecha", "Sugbay Dos", "Tongao", "Upper Paniki-an"],
  "Ramon Magsaysay": ["Bagong Opon", "Bambong Daku", "Bambong Diut", "Bobongan", "Campo IV", "Campo V", "Caniangan", "Dipalusan", "Eastern Bobongan", "Esperanza", "Gapasan", "Katipunan", "Kauswagan", "Lower Sambulawan", "Mabini", "Magsaysay", "Malating", "Paradise", "Pasingkalan", "Poblacion", "San Fernando", "Santo Rosario", "Sapa Anding", "Sinaguing", "Switch", "Upper Laperian", "Wakat"],
  "San Miguel": ["Betinan", "Bulawan", "Calube", "Concepcion", "Dao-an", "Dumalian", "Fatima", "Langilan", "Lantawan", "Laperian", "Libuganan", "Limonan", "Mati", "Ocapan", "Poblacion", "San Isidro", "Sayog", "Tapian"],
  "San Pablo": ["Bag-ong Misamis", "Bubual", "Buton", "Culasian", "Daplayan", "Kalilangan", "Kapamanok", "Kondum", "Lumbayao", "Mabuhay", "Marcos Village", "Miasin", "Molansong", "Pantad", "Pao", "Payag", "Poblacion", "Pongapong", "Sacbulan", "Sagasan", "San Juan", "Senior", "Songgoy", "Tandubuay", "Taniapan", "Ticala Island", "Tubo-pait", "Villakapa"],
  "Sominot": ["Bag-ong Baroy", "Bag-ong Oroquieta", "Barubuhan", "Bulanay", "Datagan", "Eastern Poblacion", "Lantawan", "Libertad", "Lumangoy", "New Carmen", "Picturan", "Poblacion", "Rizal", "San Miguel", "Santo Niño", "Sawa", "Tungawan", "Upper Sicpao"],
  "Tabina": ["Abong-abong", "Baganian", "Baya-baya", "Capisan", "Concepcion", "Culabay", "Doña Josefina", "Lumbia", "Mabuhay", "Malim", "Manikaan", "New Oroquieta", "Poblacion", "San Francisco", "Tultolan"],
  "Tambulig": ["Alang-alang", "Angeles", "Bag-ong Kauswagan", "Bag-ong Tabogon", "Balugo", "Cabgan", "Calolot", "Dimalinao", "Fabian", "Gabunon", "Happy Valley", "Kapalaran", "Libato", "Limamawan", "Lower Liasan", "Lower Lodiong", "Lower Tiparak", "Lower Usogan", "Maya-maya", "New Village", "Pelocoban", "Riverside", "Sagrada Familia", "San Jose", "San Vicente", "Sumalig", "Tuluan", "Tungawan", "Upper Liason", "Upper Lodiong", "Upper Tiparak"],
  "Tigbao": ["Begong", "Busol", "Caluma", "Diana Countryside", "Guinlin", "Lacarayan", "Lacupayan", "Libayoy", "Limas", "Longmot", "Maragang", "Mate", "Nangan-nangan", "New Tuburan", "Nilo", "Tigbao", "Timolan", "Upper Nilo"],
  "Tukuran": ["Alindahaw", "Baclay", "Balimbingan", "Buenasuerte", "Camanga", "Curvada", "Laperian", "Libertad", "Lower Bayao", "Luy-a", "Manilan", "Manlayag", "Militar", "Navalan", "Panduma Senior", "Sambulawan", "San Antonio", "San Carlos", "Santo Niño", "Santo Rosario", "Sugod", "Tabuan", "Tagulo", "Tinotungan", "Upper Bayao"],
  "Vincenzo A. Sagun": ["Bui-os", "Cogon", "Danan", "Kabatan", "Kapatagan", "Limason", "Linoguayan", "Lumbal", "Lunib", "Maculay", "Maraya", "Sagucan", "Waling-waling", "Ambulon"]
};

// Populate Municipality/Barangay Options
function populateMunicipalitySelects() {
  const selects = ['add_assoc_municipality', 'add_pres_municipality', 'editPresMunicipality', 'cpMunicipality'];
  selects.forEach(id => {
    const el = document.getElementById(id);
    if(!el) return;
    const currentVal = el.value;
    el.innerHTML = '<option value="" disabled selected hidden>Select Municipality</option>';
    Object.keys(zamboangaDelSurData).sort().forEach(muni => {
      const opt = document.createElement('option');
      opt.value = muni;
      opt.textContent = muni;
      el.appendChild(opt);
    });
    if(currentVal) el.value = currentVal;
  });
}

function updateAddBarangays() {
  const muni = document.getElementById('add_assoc_municipality').value;
  const bgy = document.getElementById('add_assoc_barangay');
  bgy.innerHTML = '<option value="" disabled selected hidden>Select Barangay</option>';
  if (muni && zamboangaDelSurData[muni]) {
    bgy.disabled = false;
    zamboangaDelSurData[muni].sort().forEach(item => {
      const opt = document.createElement('option');
      opt.value = item; opt.textContent = item;
      bgy.appendChild(opt);
    });
  } else {
    bgy.disabled = true;
  }
}

function updateAddPresBarangays() {
  const muni = document.getElementById('add_pres_municipality').value;
  const bgy = document.getElementById('add_pres_barangay');
  bgy.innerHTML = '<option value="" disabled selected hidden>Select Barangay</option>';
  if (muni && zamboangaDelSurData[muni]) {
    bgy.disabled = false;
    zamboangaDelSurData[muni].sort().forEach(item => {
      const opt = document.createElement('option');
      opt.value = item; opt.textContent = item;
      bgy.appendChild(opt);
    });
  } else {
    bgy.disabled = true;
  }
}

function updateEditPresBarangays(selectedBrgy = '') {
  const muni = document.getElementById('editPresMunicipality').value;
  const bgy = document.getElementById('editPresBarangay');
  bgy.innerHTML = '<option value="" disabled selected hidden>Select Barangay</option>';
  if (muni && zamboangaDelSurData[muni]) {
    bgy.disabled = false;
    zamboangaDelSurData[muni].sort().forEach(item => {
      const opt = document.createElement('option');
      opt.value = item; opt.textContent = item;
      bgy.appendChild(opt);
    });
    if(selectedBrgy) bgy.value = selectedBrgy;
  } else {
    bgy.disabled = true;
  }
}

function updateCpBarangays() {
  const muni = document.getElementById('cpMunicipality').value;
  const bgy = document.getElementById('cpBarangay');
  bgy.innerHTML = '<option value="" disabled selected hidden>Select Barangay</option>';
  if (muni && zamboangaDelSurData[muni]) {
    bgy.disabled = false;
    zamboangaDelSurData[muni].sort().forEach(item => {
      const opt = document.createElement('option');
      opt.value = item; opt.textContent = item;
      bgy.appendChild(opt);
    });
  } else {
    bgy.disabled = true;
  }
}

/* SEARCH & PRINT HANDLERS */
let wasSearched = <?= ($search_field !== 'All' && ($search_term !== '' || $status_filter !== '' || ($from_date !== '' && $to_date !== ''))) ? 'true' : 'false' ?>;

function formatLocalDate(dateObj) {
  const year = dateObj.getFullYear();
  const month = String(dateObj.getMonth() + 1).padStart(2, '0');
  const day = String(dateObj.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

window.fpFrom = flatpickr('#from_date_display', {
  dateFormat: 'm/d/Y',
  allowInput: false,
  onChange: function (dates) {
    if (dates.length) {
      const formattedDate = formatLocalDate(dates[0]);
      document.getElementById('from_date').value = formattedDate;

      const minToDate = new Date(dates[0]);
      minToDate.setDate(minToDate.getDate() + 1);
      window.fpTo.set('minDate', minToDate);

      if (window.fpTo.selectedDates[0] && window.fpTo.selectedDates[0] <= dates[0]) {
        window.fpTo.clear();
        document.getElementById('to_date').value = '';
      }
    } else {
      document.getElementById('from_date').value = '';
      window.fpTo.set('minDate', null);
    }
    validateAndCheckState();
  }
});

window.fpTo = flatpickr('#to_date_display', {
  dateFormat: 'm/d/Y',
  allowInput: false,
  onChange: function (dates) {
    document.getElementById('to_date').value = dates.length ? formatLocalDate(dates[0]) : '';
    validateAndCheckState();
  }
});

<?php if ($from_date): ?>
  window.fpFrom.setDate('<?= htmlspecialchars($from_date) ?>', true, 'Y-m-d');
  const initMinDate = new Date('<?= htmlspecialchars($from_date) ?>T00:00:00');
  initMinDate.setDate(initMinDate.getDate() + 1);
  window.fpTo.set('minDate', initMinDate);
<?php endif; ?>

<?php if ($to_date): ?>
  window.fpTo.setDate('<?= htmlspecialchars($to_date) ?>', true, 'Y-m-d');
<?php endif; ?>

function handleFieldChange() {
  document.getElementById('textInput').value = '';
  document.getElementById('statusDropdown').value = '';
  document.getElementById('from_date').value = '';
  document.getElementById('to_date').value = '';
  if (window.fpFrom) window.fpFrom.clear();
  if (window.fpTo) {
    window.fpTo.clear();
    window.fpTo.set('minDate', null);
  }

  if (wasSearched) {
    document.getElementById('field_changed').value = '1';
    document.getElementById('searchForm').submit();
    return;
  }

  toggleInputs();

  if (document.getElementById('search_field').value === 'All') {
    document.getElementById('field_changed').value = '1';
    document.getElementById('searchForm').submit();
  } else {
    validateAndCheckState();
  }
}

function toggleInputs() {
  const field = document.getElementById('search_field').value;
  const isStatus = (field === 'Status');
  const isAll = (field === 'All');
  const isRegDate = (field === 'registered_date');

  document.getElementById('textInput').style.display = (!isStatus && !isAll && !isRegDate) ? 'inline-block' : 'none';
  document.getElementById('statusDropdown').style.display = isStatus ? 'inline-block' : 'none';
  document.getElementById('from_date_display').style.display = (isStatus || isRegDate) ? 'inline-block' : 'none';
  document.getElementById('to_date_display').style.display = (isStatus || isRegDate) ? 'inline-block' : 'none';
  document.getElementById('from_label').style.display = (isStatus || isRegDate) ? 'inline-block' : 'none';
  document.getElementById('to_label').style.display = (isStatus || isRegDate) ? 'inline-block' : 'none';
  document.getElementById('searchBtn').style.display = !isAll ? 'inline-block' : 'none';

  const ph = document.getElementById('textInput');
  if (field === 'president') {
    ph.placeholder = 'Search by president name...';
  } else if (field === 'name') {
    ph.placeholder = 'Search by association name...';
  } else if (field === 'address') {
    ph.placeholder = 'barangay, municipality';
  } else {
    ph.placeholder = 'Enter search...';
  }
}

function validateAndCheckState() {
  const field = document.getElementById('search_field').value;
  const btn = document.getElementById('searchBtn');
  let isValid = false;

  if (field === 'name' || field === 'address' || field === 'president') {
    const val = document.getElementById('textInput').value.trim();
    isValid = val.length > 0;

    if (val.length === 0 && wasSearched) {
      document.getElementById('field_changed').value = '1';
      document.getElementById('searchForm').submit();
      return;
    }
  } else if (field === 'Status') {
    const st = document.getElementById('statusDropdown').value;
    const fd = document.getElementById('from_date').value;
    const td = document.getElementById('to_date').value;
    isValid = st !== '' || (fd !== '' && td !== '');
  } else if (field === 'registered_date') {
    const fd = document.getElementById('from_date').value;
    const td = document.getElementById('to_date').value;
    isValid = fd !== '' && td !== '';
  }

  btn.disabled = !isValid;
}

/* REAL-TIME VALIDATION HELPERS & STATE CHECKS */
function setFieldError(input, hint, msg) {
  input.style.borderColor = '#dc2626'; input.style.background = '#fef2f2';
  if (hint) { hint.className = 'field-hint error'; hint.textContent = msg; }
}

function setFieldOk(input, hint, msg) {
  input.style.borderColor = '#16a34a'; input.style.background = '#f0fdf4';
  if (hint) { hint.className = 'field-hint success'; hint.textContent = msg; }
}

function resetField(input, hint) {
  input.style.borderColor = ''; input.style.background = '';
  if (hint) { hint.className = 'field-hint'; hint.textContent = ''; }
}

// 1. ADD ASSOCIATION FORM VALIDATION
function validateAddFormState() {
  const btn = document.getElementById('addSubmitBtn');
  
  const nameInput = document.getElementById('add_assoc_name');
  const emailInput = document.getElementById('add_assoc_email');
  const phoneInput = document.getElementById('add_assoc_phone');
  const muniInput = document.getElementById('add_assoc_municipality');
  const brgyInput = document.getElementById('add_assoc_barangay');

  const presFirst = document.getElementById('add_pres_first');
  const presMid   = document.getElementById('add_pres_mid');
  const presLast  = document.getElementById('add_pres_last');
  const presSex   = document.getElementById('add_pres_sex');
  const presDob   = document.getElementById('pres_dob_fp');
  const presEmail = document.getElementById('add_pres_email');
  const presPhone = document.getElementById('add_pres_phone');
  const presMuni  = document.getElementById('add_pres_municipality');
  const presBrgy  = document.getElementById('add_pres_barangay');

  const nameHint  = document.getElementById('add_assoc_name_hint');
  const emailHint = document.getElementById('add_assoc_email_hint');
  const phoneHint = document.getElementById('add_assoc_phone_hint');
  const presNameHint  = document.getElementById('add_pres_name_hint');
  const presEmailHint = document.getElementById('add_pres_email_hint');
  const presPhoneHint = document.getElementById('add_pres_phone_hint');

  let valid = true;

  // Validation Checks: Association Name
  const nameVal = nameInput.value.trim().toLowerCase();
  if (!nameVal) { resetField(nameInput, nameHint); valid = false; }
  else if (existingAssocs.some(a => a.name.toLowerCase() === nameVal)) {
    setFieldError(nameInput, nameHint, 'Association name has already been taken.'); valid = false;
  } else { setFieldOk(nameInput, nameHint, ''); }

  // Validation Checks: Association Email
  const emailVal = emailInput.value.trim().toLowerCase();
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
  if (!emailVal) { resetField(emailInput, emailHint); valid = false; }
  else if (!emailRegex.test(emailVal)) {
    setFieldError(emailInput, emailHint, 'Enter a valid email'); valid = false;
  } else if (existingAssocs.some(a => a.email.toLowerCase() === emailVal) || existingPres.some(p => p.email.toLowerCase() === emailVal)) {
    setFieldError(emailInput, emailHint, 'Association email has already been taken.'); valid = false;
  } else { setFieldOk(emailInput, emailHint, ''); }

  // Validation Checks: Association Phone
  let phoneVal = phoneInput.value.replace(/\D/g, '').substring(0, 11);
  phoneInput.value = phoneVal;
  if (!phoneVal) { resetField(phoneInput, phoneHint); valid = false; }
  else if (!phoneVal.startsWith('09') || phoneVal.length < 11) {
    setFieldError(phoneInput, phoneHint, 'Must be 11 digits starting with 09'); valid = false;
  } else if (existingAssocs.some(a => a.phone === phoneVal) || existingPres.some(p => p.phone === phoneVal)) {
    setFieldError(phoneInput, phoneHint, 'Phone number has already been taken.'); valid = false;
  } else { setFieldOk(phoneInput, phoneHint, ''); }

  // Validation Checks: Location & Dropdowns
  if (!muniInput.value) valid = false;
  if (!brgyInput.value) valid = false;

  // Validation Checks: President Name
  const pf = presFirst.value.trim().toLowerCase();
  const pm = presMid.value.trim().toLowerCase();
  const pl = presLast.value.trim().toLowerCase();
  if (!pf || !pl) { resetField(presFirst, presNameHint); valid = false; }
  else if (existingPres.some(p => p.first_name.toLowerCase() === pf && (p.middle_name||'').toLowerCase() === pm && p.last_name.toLowerCase() === pl)) {
    setFieldError(presFirst, presNameHint, 'President name has already been taken.'); valid = false;
  } else { setFieldOk(presFirst, presNameHint, ''); }

  if (!presSex.value) valid = false;
  if (!presDob.value) valid = false;

  // Validation Checks: President Email
  const pEmailVal = presEmail.value.trim().toLowerCase();
  if (!pEmailVal) { resetField(presEmail, presEmailHint); valid = false; }
  else if (!emailRegex.test(pEmailVal)) {
    setFieldError(presEmail, presEmailHint, 'Enter a valid email'); valid = false;
  } else if (existingAssocs.some(a => a.email.toLowerCase() === pEmailVal) || existingPres.some(p => p.email.toLowerCase() === pEmailVal)) {
    setFieldError(presEmail, presEmailHint, 'Email has already been taken.'); valid = false;
  } else { setFieldOk(presEmail, presEmailHint, ''); }

  // Validation Checks: President Phone
  let pPhoneVal = presPhone.value.replace(/\D/g, '').substring(0, 11);
  presPhone.value = pPhoneVal;
  if (!pPhoneVal) { resetField(presPhone, presPhoneHint); valid = false; }
  else if (!pPhoneVal.startsWith('09') || pPhoneVal.length < 11) {
    setFieldError(presPhone, presPhoneHint, 'Must be 11 digits starting with 09'); valid = false;
  } else if (existingAssocs.some(a => a.phone === pPhoneVal) || existingPres.some(p => p.phone === pPhoneVal)) {
    setFieldError(presPhone, presPhoneHint, 'Phone number has already been taken.'); valid = false;
  } else { setFieldOk(presPhone, presPhoneHint, ''); }

  if (!presMuni.value) valid = false;
  if (!presBrgy.value) valid = false;

  btn.disabled = !valid;
}

// 2. EDIT ASSOCIATION FORM VALIDATION
let originalEditPresEmail = '';
let originalEditPresPhone = '';
let originalEditAssocPhone = '';

function validateEditFormState() {
  const saveBtn = document.getElementById('editSaveBtn');
  const assocPhone = document.getElementById('editAssocPhone');
  const presEmail  = document.getElementById('editPresEmail');
  const presPhone  = document.getElementById('editPresPhone');
  const presMuni   = document.getElementById('editPresMunicipality');
  const presBrgy   = document.getElementById('editPresBarangay');

  const phoneHint     = document.getElementById('editAssocPhone_hint');
  const presEmailHint = document.getElementById('editPresEmail_hint');
  const presPhoneHint = document.getElementById('editPresPhone_hint');

  let valid = true;
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

  // Check Assoc Phone
  let aPhoneVal = assocPhone.value.replace(/\D/g, '').substring(0, 11);
  assocPhone.value = aPhoneVal;
  if (!aPhoneVal) { resetField(assocPhone, phoneHint); valid = false; }
  else if (!aPhoneVal.startsWith('09') || aPhoneVal.length < 11) {
    setFieldError(assocPhone, phoneHint, 'Must be 11 digits starting with 09'); valid = false;
  } else if (aPhoneVal !== originalEditAssocPhone && (existingAssocs.some(a => a.phone === aPhoneVal) || existingPres.some(p => p.phone === aPhoneVal))) {
    setFieldError(assocPhone, phoneHint, 'Phone number has already been taken.'); valid = false;
  } else { setFieldOk(assocPhone, phoneHint, ''); }

  // Check Pres Email
  let pEmailVal = presEmail.value.trim().toLowerCase();
  if (!pEmailVal) { resetField(presEmail, presEmailHint); valid = false; }
  else if (!emailRegex.test(pEmailVal)) {
    setFieldError(presEmail, presEmailHint, 'Enter a valid email'); valid = false;
  } else if (pEmailVal !== originalEditPresEmail && (existingAssocs.some(a => a.email.toLowerCase() === pEmailVal) || existingPres.some(p => p.email.toLowerCase() === pEmailVal))) {
    setFieldError(presEmail, presEmailHint, 'Email has already been taken.'); valid = false;
  } else { setFieldOk(presEmail, presEmailHint, ''); }

  // Check Pres Phone
  let pPhoneVal = presPhone.value.replace(/\D/g, '').substring(0, 11);
  presPhone.value = pPhoneVal;
  if (!pPhoneVal) { resetField(presPhone, presPhoneHint); valid = false; }
  else if (!pPhoneVal.startsWith('09') || pPhoneVal.length < 11) {
    setFieldError(presPhone, presPhoneHint, 'Must be 11 digits starting with 09'); valid = false;
  } else if (pPhoneVal !== originalEditPresPhone && (existingAssocs.some(a => a.phone === pPhoneVal) || existingPres.some(p => p.phone === pPhoneVal))) {
    setFieldError(presPhone, presPhoneHint, 'Phone number has already been taken.'); valid = false;
  } else { setFieldOk(presPhone, presPhoneHint, ''); }

  if (!presMuni.value) valid = false;
  if (!presBrgy.value) valid = false;

  saveBtn.disabled = !valid;
}

// 3. CHANGE PRESIDENT FORM VALIDATION
function validateChangePresState() {
  const saveBtn = document.getElementById('cpSaveBtn');

  const fnInput = document.getElementById('cpFirstName');
  const mnInput = document.getElementById('cpMiddleName');
  const lnInput = document.getElementById('cpLastName');
  const sexInput = document.getElementById('cpSex');
  const dobInput = document.getElementById('cpDob');
  const emailInput = document.getElementById('cpEmail');
  const phoneInput = document.getElementById('cpPhone');
  const muniInput = document.getElementById('cpMunicipality');
  const brgyInput = document.getElementById('cpBarangay');

  const nameHint  = document.getElementById('cp_name_hint');
  const emailHint = document.getElementById('cp_email_hint');
  const phoneHint = document.getElementById('cp_phone_hint');

  let valid = true;
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

  // Check Name
  const fn = fnInput.value.trim().toLowerCase();
  const mn = mnInput.value.trim().toLowerCase();
  const ln = lnInput.value.trim().toLowerCase();

  if (!fn || !ln) { resetField(fnInput, nameHint); valid = false; }
  else if (existingPres.some(p => p.first_name.toLowerCase() === fn && (p.middle_name||'').toLowerCase() === mn && p.last_name.toLowerCase() === ln)) {
    setFieldError(fnInput, nameHint, 'President name has already been taken.'); valid = false;
  } else { setFieldOk(fnInput, nameHint, ''); }

  if(!sexInput.value) valid = false;
  if(!dobInput.value) valid = false;

  // Check Email
  const emailVal = emailInput.value.trim().toLowerCase();
  if (!emailVal) { resetField(emailInput, emailHint); valid = false; }
  else if (!emailRegex.test(emailVal)) {
    setFieldError(emailInput, emailHint, 'Enter a valid email'); valid = false;
  } else if (existingAssocs.some(a => a.email.toLowerCase() === emailVal) || existingPres.some(p => p.email.toLowerCase() === emailVal)) {
    setFieldError(emailInput, emailHint, 'Email has already been taken.'); valid = false;
  } else { setFieldOk(emailInput, emailHint, ''); }

  // Check Phone
  let phoneVal = phoneInput.value.replace(/\D/g, '').substring(0, 11);
  phoneInput.value = phoneVal;
  if (!phoneVal) { resetField(phoneInput, phoneHint); valid = false; }
  else if (!phoneVal.startsWith('09') || phoneVal.length < 11) {
    setFieldError(phoneInput, phoneHint, 'Must be 11 digits starting with 09'); valid = false;
  } else if (existingAssocs.some(a => a.phone === phoneVal) || existingPres.some(p => p.phone === phoneVal)) {
    setFieldError(phoneInput, phoneHint, 'Phone number has already been taken.'); valid = false;
  } else { setFieldOk(phoneInput, phoneHint, ''); }

  if (!muniInput.value) valid = false;
  if (!brgyInput.value) valid = false;

  saveBtn.disabled = !valid;
}

/* INITIALIZE MODALS & ACTIONS */
document.addEventListener('DOMContentLoaded', () => {
  toggleInputs();
  validateAndCheckState();
  populateMunicipalitySelects();

  flatpickr('#pres_dob_fp', {
    dateFormat: 'm/d/Y',
    maxDate: 'today',
    allowInput: false,
    onChange: function(dates) {
      if (dates.length) {
        const dob   = dates[0];
        const today = new Date();
        let age = today.getFullYear() - dob.getFullYear();
        const m = today.getMonth() - dob.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) age--;
        document.getElementById('pres_age_input').value = age >= 0 ? age : '';
      } else {
        document.getElementById('pres_age_input').value = '';
      }
      validateAddFormState();
    }
  });

  <?php if (!empty($modal_success)): ?>
    showResponsePopupModal('<?= htmlspecialchars($modal_success) ?>');
  <?php endif; ?>
});

const rows = document.querySelectorAll('.clickable-row');
let selectedRowId = null;

rows.forEach(row => {
  row.addEventListener('click', () => {
    rows.forEach(r => r.classList.remove('table-active'));
    row.classList.add('table-active');
    selectedRowId = row.getAttribute('data-id');
    document.getElementById('editBtn').disabled = false;
  });
  row.addEventListener('dblclick', () => {
    const id = row.getAttribute('data-id');
    if (id) window.location.href = 'association_profile.php?id=' + id;
  });
});

document.getElementById('addBtn').addEventListener('click', () => openAddModal());
document.getElementById('editBtn').addEventListener('click', () => {
  if (!selectedRowId) return;
  loadEditModal(selectedRowId);
});
document.getElementById('printBtn').addEventListener('click', () => window.print());

function showResponsePopupModal(message) {
  document.getElementById('rpm_message').textContent = message;
  document.getElementById('responsePopupModal').style.display = 'flex';
}
function closeResponsePopupModal() {
  document.getElementById('responsePopupModal').style.display = 'none';
}

/* ADD MODAL HANDLERS */
function openAddModal() {
  document.getElementById('addAssocForm').reset();
  updateAddBarangays();
  updateAddPresBarangays();
  validateAddFormState();
  document.getElementById('addModal').classList.add('active');
  document.body.style.overflow = 'hidden';
}
function closeAddModal() {
  document.getElementById('addModal').classList.remove('active');
  document.body.style.overflow = '';
}

/* EDIT MODAL HANDLERS */
function loadEditModal(id) {
  fetch('get_association_edit.php?id=' + id)
    .then(r => r.json())
    .then(data => {
      if (!data.success) { alert('Failed to load association data.'); return; }
      const a = data.association;

      document.getElementById('editAssocId').value           = a.id                  || '';
      document.getElementById('editAssocName').value         = a.name                || '';
      document.getElementById('editAssocEmail').value        = a.email               || '';
      document.getElementById('editAssocProvince').value     = a.province            || '';
      document.getElementById('editAssocMunicipality').value = a.municipality        || '';
      document.getElementById('editAssocBarangay').value     = a.barangay            || '';
      document.getElementById('editPresSex').value           = a.president_sex       || '';
      document.getElementById('editPresDob').value           = a.president_dob       || '';
      document.getElementById('editPresProvince').value      = a.president_province  || '';

      if (a.president_dob) {
        const dob   = new Date(a.president_dob);
        const today = new Date();
        let age = today.getFullYear() - dob.getFullYear();
        const m = today.getMonth() - dob.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) age--;
        document.getElementById('editPresAge').value = age >= 0 ? age + ' yrs' : '';
      } else {
        document.getElementById('editPresAge').value = a.president_age ? a.president_age + ' yrs' : '';
      }

      document.getElementById('editAssocPhone').value       = a.phone               || '';
      document.getElementById('editPresFirstName').value    = a.president_first_name || '';
      document.getElementById('editPresMiddleName').value   = a.president_middle_name|| '';
      document.getElementById('editPresLastName').value     = a.president_last_name  || '';
      document.getElementById('editPresEmail').value        = a.president_email     || '';
      document.getElementById('editPresPhone').value        = a.president_phone     || '';
      
      document.getElementById('editPresMunicipality').value = a.president_municipality || '';
      updateEditPresBarangays(a.president_barangay || '');

      originalEditAssocPhone = a.phone || '';
      originalEditPresEmail  = a.president_email || '';
      originalEditPresPhone  = a.president_phone || '';

      validateEditFormState();

      document.getElementById('editModal').style.display = 'flex';
      document.body.style.overflow = 'hidden';
    })
    .catch(() => alert('Error loading association data.'));
}

function closeEditModal() {
  document.getElementById('editModal').style.display = 'none';
  document.body.style.overflow = '';
}

/* CHANGE PRESIDENT MODAL HANDLERS */
let cpFlatpickr = null;

function openChangePresidentModal() {
  const assocId   = document.getElementById('editAssocId').value;
  const firstName = document.getElementById('editPresFirstName').value;
  const midName   = document.getElementById('editPresMiddleName').value;
  const lastName  = document.getElementById('editPresLastName').value;
  const fullName  = [firstName, midName, lastName].filter(Boolean).join(' ');

  document.getElementById('cpAssocId').value               = assocId;
  document.getElementById('cpCurrentPresName').textContent = fullName || 'Unknown';

  /* AJAX SUBMIT FOR CHANGE PRESIDENT */
  document.getElementById('changePresForm').addEventListener('submit', function (e) {
    e.preventDefault(); // Prevent full page refresh

    const form = this;
    const formData = new FormData(form);

    fetch('change_president_action.php', {
      method: 'POST',
      body: formData
    })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // 1. Extract updated president values directly from input fields
          const newFirst = document.getElementById('cpFirstName').value.trim();
          const newMid   = document.getElementById('cpMiddleName').value.trim();
          const newLast  = document.getElementById('cpLastName').value.trim();
          const newSex   = document.getElementById('cpSex').value;
          const newDob   = document.getElementById('cpDob').value;
          const newAge   = document.getElementById('cpAge').value;
          const newEmail = document.getElementById('cpEmail').value.trim();
          const newPhone = document.getElementById('cpPhone').value.trim();
          const newMuni  = document.getElementById('cpMunicipality').value;
          const newBrgy  = document.getElementById('cpBarangay').value;

          // 2. Dynamically update Edit Association Modal fields
          document.getElementById('editPresFirstName').value = newFirst;
          document.getElementById('editPresMiddleName').value = newMid;
          document.getElementById('editPresLastName').value = newLast;
          document.getElementById('editPresSex').value = newSex;
          document.getElementById('editPresDob').value = newDob;
          document.getElementById('editPresAge').value = newAge ? newAge + ' yrs' : '';
          document.getElementById('editPresEmail').value = newEmail;
          document.getElementById('editPresPhone').value = newPhone;
          
          document.getElementById('editPresMunicipality').value = newMuni;
          updateEditPresBarangays(newBrgy);

          // 3. Update comparison variables for Edit Modal validation state
          originalEditPresEmail = newEmail;
          originalEditPresPhone = newPhone;

          // 4. Re-run validation on Edit Modal to enable Save button safely
          validateEditFormState();

          // 5. Close Change President modal only
          closeChangePresModal();

          // 6. Show success modal alert
          showResponsePopupModal(data.message || 'President changed successfully!');
        } else {
          alert(data.message || 'Error updating president. Please try again.');
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('An unexpected server error occurred.');
      });
  });
  updateCpBarangays();

  if (cpFlatpickr) cpFlatpickr.destroy();
  cpFlatpickr = flatpickr('#cpDob', {
    dateFormat: 'm/d/Y',
    maxDate: 'today',
    allowInput: false,
    onChange: function(dates) {
      if (dates.length) {
        const dob   = dates[0];
        const today = new Date();
        let age = today.getFullYear() - dob.getFullYear();
        const m = today.getMonth() - dob.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) age--;
        document.getElementById('cpAge').value = age >= 0 ? age : '';
      } else {
        document.getElementById('cpAge').value = '';
      }
      validateChangePresState();
    }
  });

  validateChangePresState();
  document.getElementById('changePresModal').style.display = 'flex';
}

function closeChangePresModal() {
  document.getElementById('changePresModal').style.display = 'none';
}
</script>

</body>
</html