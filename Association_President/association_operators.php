
<?php
session_start();
require_once '../includes/db_connection.php';
include('dashboard_president.php');

if (!isset($_SESSION['association_id'])) {
    header("Location: ../login.php");
    exit;
}
$association_id = $_SESSION['association_id'];

$message = "";
$error   = "";

/* ===============================
   ADD OPERATOR
================================ */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_operator'])) {
    $first_name  = trim($_POST['first_name']);
    $middle_name = trim($_POST['middle_name']);
    $last_name   = trim($_POST['last_name']);
    $sex         = trim($_POST['sex']);
    $dob         = trim($_POST['date_of_birth']);
    $age         = intval($_POST['age']);
    $email       = trim($_POST['email']);
    $phone       = trim($_POST['phone']);
    $province    = trim($_POST['province'] ?? 'Zamboanga del Sur');
    $municipality = trim($_POST['municipality'] ?? '');
    $barangay    = trim($_POST['barangay'] ?? '');
    $password    = '123456';
    $full_name   = trim("$first_name $middle_name $last_name");

    if (empty($first_name) || empty($last_name) || empty($sex) || empty($dob) || empty($email) || empty($phone)) {
        $error = "All required fields must be filled!";
    } else {
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = "Email already registered!";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $conn->begin_transaction();
            try {
                // Insert into users
                $stmt1 = $conn->prepare("INSERT INTO users (name, email, password, user_role) VALUES (?, ?, ?, 'operator')");
                $stmt1->bind_param("sss", $full_name, $email, $hashed_password);
                $stmt1->execute();
                $user_id = $conn->insert_id;
                $stmt1->close();

                // Insert into operators with location columns directly
                $stmt2 = $conn->prepare("INSERT INTO operators (user_id, association_id, name, email, phone, password, province, municipality, barangay, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active')");
                $stmt2->bind_param("iisssssss", $user_id, $association_id, $full_name, $email, $phone, $hashed_password, $province, $municipality, $barangay);
                $stmt2->execute();
                $stmt2->close();

                $conn->commit();
                $message = "Operator registered successfully!";
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Registration failed: " . $e->getMessage();
            }
        }
        $check->close();
    }
}

/* ===============================
   SET STATUS
================================ */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['set_status'])) {
    $operator_id = intval($_POST['operator_id']);
    $new_status  = trim($_POST['new_status']);
    if (in_array($new_status, ['Active', 'Inactive'])) {
        $s = $conn->prepare("UPDATE operators SET status = ? WHERE id = ? AND association_id = ?");
        $s->bind_param("sii", $new_status, $operator_id, $association_id);
        if ($s->execute()) {
            $message = "Operator status updated to $new_status!";
        } else {
            $error = "Failed to update status.";
        }
        $s->close();
    }
}

$assoc_stmt = $conn->prepare("SELECT name FROM associations WHERE id = ?");
$assoc_stmt->bind_param("i", $association_id);
$assoc_stmt->execute();
$assoc_row = $assoc_stmt->get_result()->fetch_assoc();
$assoc_stmt->close();
$association_name = $assoc_row['name'] ?? 'Association';

$message    = "";
$error      = "";
$new_or_num = "";

/* ===============================
   FETCH OPERATORS
================================ */
$search_field  = $_GET['search_field']   ?? 'All';
$search_term   = $_GET['search_term']    ?? '';
$status_filter = $_GET['statusDropdown'] ?? '';
$field_changed = $_GET['field_changed']  ?? '0';

$where_parts = ["o.association_id = ?"];
if ($search_field !== 'All' && $field_changed !== '1' && !empty($search_term)) {
    if ($search_field === 'name') {
        $where_parts[] = "o.name LIKE '%" . $conn->real_escape_string($search_term) . "%'";
    }
}
if ($search_field === 'Status' && $field_changed !== '1' && !empty($status_filter)) {
    $where_parts[] = "o.status = '" . $conn->real_escape_string($status_filter) . "'";
}

$where_sql = "WHERE " . implode(" AND ", $where_parts);

$operators_sql = "
    SELECT
        o.*,
        TRIM(CONCAT_WS(', ', NULLIF(o.barangay,''), NULLIF(o.municipality,''), NULLIF(o.province,''))) AS display_address,
        u.email AS user_email,
        COUNT(DISTINCT mo.machine_id) AS total_machines,
        GROUP_CONCAT(DISTINCT m.machine_name SEPARATOR ', ') AS assigned_machines
    FROM operators o
    LEFT JOIN users u ON o.user_id = u.id
    LEFT JOIN machine_operators mo ON o.id = mo.operator_id AND mo.status = 'Active'
    LEFT JOIN machines m ON mo.machine_id = m.id
    $where_sql
    GROUP BY o.id
    ORDER BY o.created_at DESC
";

$stmt = $conn->prepare($operators_sql);
$stmt->bind_param("i", $association_id);
$stmt->execute();
$result    = $stmt->get_result();
$operators = [];
while ($row = $result->fetch_assoc()) $operators[] = $row;
$stmt->close();
?>
<!DOCTYPE html>
<html>

<head>
    <title>Association Operators</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.css">
    <style>
        /* ── BASE ── */
        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .main-content {
            padding: 20px;
        }

        .welcome-bar {
            color: #000000;
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 6px;
        }

        .welcome-bar a {
            color: #000000;
            margin-left: 15px;
            text-decoration: underline;
            font-weight: normal;
        }

        /* ── SEARCH ── */
        .search-form {
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
        }

        .search-form select,
        .search-form input[type="text"],
        .search-form button {
            padding: 8px 12px;
            font-size: 13px;
            border: 1px solid #2d7a2d;
            border-radius: 6px;
        }

        .search-form button {
            background: #2d7a2d;
            color: white;
            border: none;
            cursor: pointer;
        }

        .search-form button:hover {
            background: #256725;
        }

        /* ── TABLE ── */
        .table-container {
            border: 1px solid #ddd;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 2px 5px rgba(0, 0, 0, .1);
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        th,
        td {
            padding: 11px 12px;
            border-bottom: 1px solid #ddd;
            text-align: left;
            font-size: 13px;
            vertical-align: middle;
            white-space: nowrap;
        }

        td.addr-cell {
            white-space: normal;
            word-break: keep-all;
            overflow-wrap: normal;
            min-width: 140px;
            max-width: 200px;
        }

        th {
            background: #2d7a2d;
            color: white;
            font-size: 13px;
            white-space: nowrap;
        }

        tbody tr {
            cursor: pointer;
            transition: background .15s;
        }

        tbody tr:hover {
            background: #f1f1f1;
        }

        tbody tr.selected {
            background: #c3e6cb !important;
            border-left: 4px solid #2d7a2d;
        }

        .status-badge {
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            white-space: nowrap;
        }

        .status-active {
            color: #155724;
        }

        .status-inactive {
            color: #721c24;
        }

        .machine-badge {
            color: #2d572c;
            border-radius: 4px;
            display: inline-block;
            font-size: 12px;
            margin: 2px;
            white-space: nowrap;
        }

        /* ── ACTION BUTTONS ── */
        .action-buttons {
            margin-top: 20px;
            display: flex;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .action-buttons button {
            padding: 10px 20px;
            border-radius: 6px;
            border: none;
            background: #2d7a2d;
            color: white;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .action-buttons button:hover:not(:disabled) {
            background: #256725;
            transform: scale(1.03);
        }

        .action-buttons button:disabled {
            background: #2d7a2d;
            cursor: not-allowed;
            transform: none;
        }

        /* ── MODALS ── */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.55);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(3px);
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-box {
            background: white;
            width: 720px;
            max-width: 96%;
            max-height: 90vh;
            overflow-y: auto;
            border-radius: 14px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        }

        .modal-box.wide {
            width: 700px;
        }

        .modal-box::-webkit-scrollbar {
            width: 6px;
        }

        .modal-box::-webkit-scrollbar-thumb {
            background: #2d7a2d;
            border-radius: 4px;
        }

        .modal-head {
            background: linear-gradient(135deg, #2d7a2d, #1a5c1a);
            padding: 20px 24px;
            border-radius: 14px 14px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .modal-head h3 {
            color: white;
            font-size: 20px;
            font-weight: 700;
            margin: 0;
        }

        .modal-head p {
            color: rgba(255, 255, 255, 0.8);
            font-size: 13px;
            margin: 4px 0 0;
        }

        .modal-x {
            width: 32px;
            height: 32px;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            border-radius: 50%;
            color: white;
            font-size: 18px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .modal-x:hover {
            background: rgba(255, 255, 255, 0.35);
        }

        .modal-bd {
            padding: 22px 24px;
        }

        .m-alert {
            padding: 11px 14px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .m-alert.ok {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #86efac;
        }

        .m-alert.err {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        .m-sec {
            font-size: 12px;
            font-weight: 700;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: .5px;
            margin-bottom: 12px;
            margin-top: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .m-sec::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e5e7eb;
        }

        .m-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr;
            gap: 12px;
            margin-bottom: 14px;
        }

        .m-row.col2 {
            grid-template-columns: 1fr 1fr;
        }

        .m-row.col3 {
            grid-template-columns: 1fr 1fr 1fr;
        }

        .m-row.full {
            grid-template-columns: 1fr;
        }

        .m-grp {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .m-grp label {
            font-size: 13px;
            font-weight: 600;
            color: #374151;
        }

        .m-grp label .req {
            color: #dc2626;
        }

        .m-wrap {
            position: relative;
        }

        .m-wrap .m-ico {
            position: absolute;
            left: 11px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 13px;
            pointer-events: none;
        }

        .m-grp input,
        .m-grp select {
            width: 100%;
            padding: 9px 12px 9px 34px;
            border: 2px solid #e5e7eb;
            border-radius: 7px;
            font-size: 14px;
            font-family: inherit;
            color: #1f2937;
            transition: border-color .2s, box-shadow .2s, background .2s;
            background: white;
            box-sizing: border-box;
            appearance: none;
        }

        .m-grp input:focus,
        .m-grp select:focus {
            outline: none;
            border-color: #2d7a2d;
            box-shadow: 0 0 0 3px rgba(45, 122, 45, .1);
        }

        .m-grp input[readonly] {
            background: #f3f4f6;
            color: #6b7280;
            cursor: default;
        }

        .m-grp input.prefilled {
            background: #f0fdf4;
            color: #166534;
            border-color: #86efac;
            font-weight: 600;
        }

        .m-grp input.field-error,
        .m-grp select.field-error {
            border-color: #dc2626 !important;
            background: #fef2f2 !important;
        }

        .m-grp input.field-ok,
        .m-grp select.field-ok {
            border-color: #16a34a !important;
            background: #f0fdf4 !important;
        }

        .field-hint {
            font-size: 12px;
            margin-top: 3px;
            display: none;
            align-items: center;
            gap: 4px;
            font-weight: 500;
        }

        .field-hint.error {
            color: #dc2626;
            display: flex;
        }

        .field-hint.success {
            color: #16a34a;
            display: flex;
        }

        .m-addr-sec {
            background: #f8fffe;
            border: 1px solid #d1fae5;
            border-radius: 8px;
            padding: 14px 16px;
            margin-bottom: 14px;
        }

        .m-addr-sec .m-sec {
            margin-top: 0;
        }

        .m-hr {
            border: none;
            border-top: 2px solid #f0f0f0;
            margin: 14px 0;
        }

        .m-foot {
            padding: 14px 24px;
            background: #f9fafb;
            border-top: 1px solid #e5e7eb;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            border-radius: 0 0 14px 14px;
        }

        .m-cancel {
            padding: 9px 20px;
            background: white;
            color: #6b7280;
            border: 2px solid #e5e7eb;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }

        .m-cancel:hover {
            background: #f9fafb;
        }

        .m-submit {
            padding: 9px 22px;
            background: #2d7a2d;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .m-submit:hover {
            background: #256725;
        }

        .pw-info {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 11px 14px;
            background: #f0fdf4;
            border: 1.5px solid #86efac;
            border-radius: 8px;
            font-size: 13px;
            color: #166534;
            margin-bottom: 4px;
        }

        /* ── Confirm / Success / Validation ── */
        #confirmModal,
        #successPopup,
        #validationModal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.6);
            z-index: 10001;
            justify-content: center;
            align-items: center;
            padding: 16px;
        }

        @keyframes modalIn {
            from {
                opacity: 0;
                transform: scale(0.95) translateY(12px)
            }

            to {
                opacity: 1;
                transform: scale(1) translateY(0)
            }
        }

        .inner-modal {
            background: #fff;
            border-radius: 14px;
            width: 100%;
            max-width: 380px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            animation: modalIn 0.22s ease both;
        }

        .inner-head {
            padding: 18px 22px;
        }

        .inner-head.green {
            background: linear-gradient(135deg, #1e6b35, #2d9148);
        }

        .inner-head.red {
            background: linear-gradient(135deg, #b91c1c, #dc2626);
        }

        .inner-head.center {
            text-align: center;
            padding: 28px 22px 20px;
        }

        .inner-head .ih-title {
            color: #fff;
            font-size: 1rem;
            font-weight: 700;
        }

        .inner-head .ih-sub {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.78rem;
            margin-top: 2px;
        }

        .inner-head .ih-icon {
            font-size: 2.8rem;
            line-height: 1;
            margin-bottom: 8px;
        }

        .inner-body {
            padding: 20px 22px;
        }

        .inner-body p {
            color: #374151;
            font-size: 0.9rem;
            margin-bottom: 20px;
            line-height: 1.5;
        }

        .inner-foot {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .btn-yes {
            padding: 9px 20px;
            background: linear-gradient(135deg, #1e6b35, #2d9148);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 0.88rem;
            font-weight: 700;
            cursor: pointer;
        }

        .btn-no {
            padding: 9px 20px;
            background: #f5f5f5;
            color: #555;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 0.88rem;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-ok {
            padding: 10px 32px;
            background: linear-gradient(135deg, #14532d, #16a34a);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
        }

        .btn-err {
            padding: 9px 24px;
            background: linear-gradient(135deg, #b91c1c, #dc2626);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 0.88rem;
            font-weight: 700;
            cursor: pointer;
        }

        /* ══════════════════════════════════
   PRINT STYLES
══════════════════════════════════ */
        @media print {
            @page {
                size: A4 landscape;
                margin: 12mm 10mm;
            }

            body * {
                visibility: hidden;
            }

            #printArea,
            #printArea * {
                visibility: visible;
            }

            #printArea {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                padding: 0;
                background: white;
            }

            #printArea h2 {
                text-align: center;
                color: #000 !important;
                font-size: 16px;
                margin-bottom: 10px;
            }

            #printArea table {
                width: 100%;
                border-collapse: collapse;
                table-layout: fixed;
                font-size: 10px;
            }

            #printArea th {
                background: #2d7a2d !important;
                color: white !important;
                padding: 6px 5px;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                white-space: nowrap;
                font-size: 10px;
            }

            #printArea td {
                padding: 5px;
                border-bottom: 1px solid #ddd;
                font-size: 10px;
                word-break: break-word;
                overflow-wrap: break-word;
            }

            #printArea table colgroup col:nth-child(1) {
                width: 3%;
            }

            #printArea table colgroup col:nth-child(2) {
                width: 11%;
            }

            #printArea table colgroup col:nth-child(3) {
                width: 15%;
            }

            #printArea table colgroup col:nth-child(4) {
                width: 18%;
            }

            #printArea table colgroup col:nth-child(5) {
                width: 12%;
            }

            #printArea table colgroup col:nth-child(6) {
                width: 20%;
            }

            #printArea table colgroup col:nth-child(7) {
                width: 15%;
            }

            #printArea table colgroup col:nth-child(8) {
                width: 6%;
            }

            #printArea .machine-badge {
                background: none;
                padding: 0;
                font-size: 10px;
                display: inline;
            }

            #printArea .machine-badge+.machine-badge::before {
                content: ', ';
            }

            #printArea .status-badge {
                background: none;
                padding: 0;
                font-weight: bold;
            }
        }

        thead,
        thead th {
            background: #16a34a !important;
            background-color: #16a34a !important;
        }
    </style>
</head>

<body>
    <div class="main-content">

        <div class="welcome-bar">
            Welcome <?= htmlspecialchars($association_name) ?>
            <a href="../logout.php">Logout</a>
        </div>

        <h2 style="text-align:center; color: #2d7a2d;margin-bottom:16px;">My Operators</h2>

        <!-- Search -->
        <form class="search-form" method="GET" id="searchForm">
            <input type="hidden" name="field_changed" id="field_changed" value="0">
            <select name="search_field" id="search_field" onchange="handleFieldChange()">
                <option value="All" <?= $search_field === 'All'    ? 'selected' : '' ?>>All</option>
                <option value="name" <?= $search_field === 'name'   ? 'selected' : '' ?>>Name</option>
                <option value="Status" <?= $search_field === 'Status' ? 'selected' : '' ?>>Status</option>
            </select>
            <input type="text" name="search_term" id="textInput" placeholder="Search..."
                value="<?= htmlspecialchars($search_term) ?>" style="display:none;">
            <select name="statusDropdown" id="statusDropdown" style="display:none;">
                <option value="">All Status</option>
                <option value="Active" <?= $status_filter === 'Active'   ? 'selected' : '' ?>>Active</option>
                <option value="Inactive" <?= $status_filter === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
            <button type="submit" id="searchBtn" style="display:none;"> Search</button>
            <span style="margin-left:auto; color: #000000; font-weight:bold; font-size:14px;
                      white-space:nowrap;">
                Total Operators: <?= count($operators) ?>
            </span>
        </form>

        <!-- Table -->
        <div id="printArea">
            <h2 style="display:none;">List of Operators</h2>
            <div class="table-container">
                <table>
                    <colgroup>
                        <col>
                        <col>
                        <col>
                        <col>
                        <col>
                        <col>
                        <col>
                        <col>
                    </colgroup>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date Registered</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Address</th>
                            <th>Assigned Machines</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($operators) > 0): $i = 1;
                            foreach ($operators as $op):
                                $parts = array_filter([
                                    $op['barangay']     ?? '',
                                    $op['municipality'] ?? '',
                                    $op['province']     ?? ''
                                ], fn($v) => $v !== '');
                                $display_addr = implode(', ', $parts) ?: '—';
                        ?>
                                <tr class="op-row"
                                    data-id="<?= $op['id'] ?>"
                                    data-status="<?= $op['status'] ?>">
                                    <td><?= $i++ ?></td>
                                    <td><?= date('m/d/Y', strtotime($op['created_at'])) ?></td>
                                    <td><strong><?= htmlspecialchars($op['name']) ?></strong></td>
                                    <td><?= htmlspecialchars($op['email'] ?: ($op['user_email'] ?? 'N/A')) ?></td>
                                    <td><?= htmlspecialchars($op['phone']) ?></td>
                                    <td class="addr-cell"><?= htmlspecialchars($display_addr) ?></td>
                                    <td>
                                        <?php if ($op['assigned_machines']): foreach (explode(', ', $op['assigned_machines']) as $m): ?>
                                                <span class="machine-badge"><?= htmlspecialchars($m) ?></span>
                                            <?php endforeach;
                                        else: ?>
                                            <span style="color:#999;">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= strtolower($op['status']) ?>">
                                            <?= htmlspecialchars($op['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach;
                        else: ?>
                            <tr>
                                <td colspan="8" style="text-align:center;padding:30px;color:#999;font-size:13px;">No records found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <button id="btnAdd"> Add</button>
            <button id="btnActivate" disabled> Activate</button>
            <button id="btnDeactivate" disabled> Deactivate</button>
            <button id="btnPrint"> Print</button>
        </div>
    </div>


    <!-- ═══════════════════════════════════
     ADD OPERATOR MODAL
═══════════════════════════════════ -->
    <div class="modal-overlay" id="addModal">
        <div class="modal-box">
            <div class="modal-head">
                <div>
                    <h3><i class="fas fa-hard-hat"></i> Add Operator</h3>
                    <p>Register a new operator</p>
                </div>
            </div>
            <div class="modal-bd">

                <div class="m-sec"><i class="fas fa-user"></i> Personal Information</div>

                <div class="m-row">
                    <div class="m-grp">
                        <label>First Name <span class="req">*</span></label>
                        <div class="m-wrap"><i class="fas fa-user m-ico"></i>
                            <input type="text" id="f_first_name" placeholder="First name" oninput="liveValidate(this)" onblur="liveValidate(this)">
                        </div>
                        <span class="field-hint" id="hint_first_name"></span>
                    </div>
                    <div class="m-grp">
                        <label>Middle Name</label>
                        <div class="m-wrap"><i class="fas fa-user m-ico"></i>
                            <input type="text" id="f_middle_name" placeholder="Optional">
                        </div>
                    </div>
                    <div class="m-grp">
                        <label>Last Name <span class="req">*</span></label>
                        <div class="m-wrap"><i class="fas fa-user m-ico"></i>
                            <input type="text" id="f_last_name" placeholder="Last name" oninput="liveValidate(this)" onblur="liveValidate(this)">
                        </div>
                        <span class="field-hint" id="hint_last_name"></span>
                    </div>
                    <div class="m-grp">
                        <label>Sex <span class="req">*</span></label>
                        <div class="m-wrap"><i class="fas fa-venus-mars m-ico"></i>
                            <select id="f_sex" onchange="liveValidate(this)">
                                <option value="">-- Select --</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                        <span class="field-hint" id="hint_sex"></span>
                    </div>
                </div>

                <div class="m-row">
                    <div class="m-grp">
                        <label>Date of Birth <span class="req">*</span></label>
                        <div class="m-wrap"><i class="fas fa-calendar m-ico"></i>
                            <input type="text" id="dobPicker" placeholder="mm/dd/yyyy" readonly>
                        </div>
                        <span class="field-hint" id="hint_dob"></span>
                    </div>
                    <div class="m-grp">
                        <label>Age</label>
                        <div class="m-wrap"><i class="fas fa-hashtag m-ico"></i>
                            <input type="number" id="f_age" placeholder="Auto" readonly>
                        </div>
                    </div>
                    <div class="m-grp">
                        <label>Email <span class="req">*</span></label>
                        <div class="m-wrap"><i class="fas fa-envelope m-ico"></i>
                            <input type="email" id="f_email" placeholder="email@example.com" oninput="validateEmail()" onblur="validateEmail()">
                        </div>
                        <span class="field-hint" id="hint_email"></span>
                    </div>
                    <div class="m-grp">
                        <label>Phone <span class="req">*</span></label>
                        <div class="m-wrap"><i class="fas fa-phone m-ico"></i>
                            <input type="text" id="f_phone" placeholder="09XXXXXXXXX" maxlength="11" oninput="validatePhone()" onblur="validatePhone()">
                        </div>
                        <span class="field-hint" id="hint_phone"></span>
                    </div>
                </div>

                <div class="m-addr-sec">
                    <div class="m-sec"><i class="fas fa-map-marker-alt"></i> Address</div>
                    <div class="m-row col3">
                        <div class="m-grp">
                            <label>Province <span class="req">*</span></label>
                            <div class="m-wrap"><i class="fas fa-map m-ico"></i>
                                <input type="text" id="f_province" value="Zamboanga del Sur" class="prefilled"
                                    oninput="liveValidate(this)" onblur="liveValidate(this)">
                            </div>
                            <span class="field-hint" id="hint_province"></span>
                        </div>
                        <div class="m-grp">
                            <label>Municipality <span class="req">*</span></label>
                            <div class="m-wrap"><i class="fas fa-city m-ico"></i>
                                <input type="text" id="f_municipality" placeholder="e.g. Labangan"
                                    oninput="liveValidate(this)" onblur="liveValidate(this)">
                            </div>
                            <span class="field-hint" id="hint_municipality"></span>
                        </div>
                        <div class="m-grp">
                            <label>Barangay <span class="req">*</span></label>
                            <div class="m-wrap"><i class="fas fa-home m-ico"></i>
                                <input type="text" id="f_barangay" placeholder="e.g. Lower Pulacan"
                                    oninput="liveValidate(this)" onblur="liveValidate(this)">
                            </div>
                            <span class="field-hint" id="hint_barangay"></span>
                        </div>
                    </div>
                </div>

                <hr class="m-hr">
                <div class="pw-info">
                    <i class="fas fa-lock" style="font-size:15px;color:#2d7a2d;"></i>
                    <span>Default password is automatically set to <strong>123456</strong>. The operator can change it after logging in.</span>
                </div>

                <div class="m-foot">
                    <button type="button" class="m-submit" onclick="confirmAddOperator()"><i class="fas fa-user-plus"></i> Add Operator</button>
                    <button type="button" class="m-cancel" onclick="confirmCancelAdd()">Cancel</button>
                </div>
            </div>
        </div>
    </div>


    <!-- ── CONFIRM MODAL ── -->
    <div id="confirmModal">
        <div class="inner-modal">
            <div class="inner-head green">
                <div class="ih-title" id="confirmTitle">Confirm Action</div>
                <div class="ih-sub" id="confirmSub"></div>
            </div>
            <div class="inner-body">
                <p id="confirmMsg"></p>
                <div class="inner-foot">
                    <button class="btn-yes" onclick="confirmYes()">Yes</button>
                    <button class="btn-no" onclick="confirmNo()">No</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ── SUCCESS POPUP ── -->
    <div id="successPopup">
        <div class="inner-modal">
            <div class="inner-head green center">
                <div class="ih-icon" id="sucIcon">✅</div>
                <div class="ih-title" id="sucTitle">Success!</div>
                <div class="ih-sub" id="sucSub"></div>
            </div>
            <div class="inner-body" style="text-align:center;">
                <p id="sucMsg"></p>
                <button class="btn-ok" onclick="closeSuccessPopup()">OK</button>
            </div>
        </div>
    </div>

    <!-- ── VALIDATION MODAL ── -->
    <div id="validationModal">
        <div class="inner-modal">
            <div class="inner-head red">
                <div class="ih-title">Required Fields Missing</div>
                <div class="ih-sub">Please complete the form</div>
            </div>
            <div class="inner-body">
                <div style="display:flex;align-items:flex-start;gap:12px;margin-bottom:20px;">
                    <div style="font-size:2rem;line-height:1;">⚠️</div>
                    <p id="valMsg" style="margin:0;"></p>
                </div>
                <div class="inner-foot">
                    <button class="btn-err" onclick="closeValidationModal()">OK</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden status form -->
    <form method="POST" id="statusForm" style="display:none;">
        <input type="hidden" name="set_status" value="1">
        <input type="hidden" name="operator_id" id="statusOperatorId">
        <input type="hidden" name="new_status" id="statusNewValue">
    </form>

    <!-- Hidden add form -->
    <form method="POST" id="realAddForm" style="display:none;">
        <input type="hidden" name="add_operator" value="1">
        <input type="hidden" name="first_name" id="r_first_name">
        <input type="hidden" name="middle_name" id="r_middle_name">
        <input type="hidden" name="last_name" id="r_last_name">
        <input type="hidden" name="sex" id="r_sex">
        <input type="hidden" name="date_of_birth" id="r_dob">
        <input type="hidden" name="age" id="r_age">
        <input type="hidden" name="email" id="r_email">
        <input type="hidden" name="phone" id="r_phone">
        <input type="hidden" name="province" id="r_province">
        <input type="hidden" name="municipality" id="r_municipality">
        <input type="hidden" name="barangay" id="r_barangay">
    </form>


    <script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.js"></script>
    <script>
        let selectedOpId = null;
        let selectedOpStatus = null;
        let selectedOpName = '';
        let confirmCallback = null;
        let successCallback = null;

        /* ── Row selection ── */
        document.querySelectorAll('.op-row').forEach(function(row) {
            row.addEventListener('click', function() {
                document.querySelectorAll('.op-row').forEach(r => r.classList.remove('selected'));
                this.classList.add('selected');
                selectedOpId = this.dataset.id;
                selectedOpStatus = this.dataset.status;
                selectedOpName = this.querySelector('td:nth-child(3)').textContent.trim();
                document.getElementById('btnActivate').disabled = false;
                document.getElementById('btnDeactivate').disabled = false;
            });
            row.addEventListener('dblclick', function() {
                window.location.href = 'association_operator_profile.php?id=' + this.dataset.id;
            });
        });

        /* ── Button listeners ── */
        document.getElementById('btnAdd').addEventListener('click', function() {
            openModal('addModal');
            setTimeout(initDobPicker, 80);
        });

        document.getElementById('btnActivate').addEventListener('click', function() {
            if (!selectedOpId) return;
            showConfirm(
                'Activate Operator', 'Please confirm',
                'Are you sure you want to activate "' + selectedOpName + '"?',
                function() {
                    document.getElementById('statusOperatorId').value = selectedOpId;
                    document.getElementById('statusNewValue').value = 'Active';
                    showSuccess('✅', 'Activated!', 'Status updated',
                        selectedOpName + ' has been set to Active.',
                        function() {
                            document.getElementById('statusForm').submit();
                        }
                    );
                }
            );
        });

        document.getElementById('btnDeactivate').addEventListener('click', function() {
            if (!selectedOpId) return;
            showConfirm(
                'Deactivate Operator', 'Please confirm',
                'Are you sure you want to deactivate "' + selectedOpName + '"?',
                function() {
                    document.getElementById('statusOperatorId').value = selectedOpId;
                    document.getElementById('statusNewValue').value = 'Inactive';
                    showSuccess('🔒', 'Deactivated!', 'Status updated',
                        selectedOpName + ' has been set to Inactive.',
                        function() {
                            document.getElementById('statusForm').submit();
                        }
                    );
                }
            );
        });

        document.getElementById('btnPrint').addEventListener('click', function() {
            doPrint();
        });

        /* ── Generic modal open/close ── */
        function openModal(id) {
            document.getElementById(id).classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
            document.body.style.overflow = '';
        }
        document.querySelectorAll('.modal-overlay').forEach(function(el) {
            el.addEventListener('click', function(e) {
                if (e.target === this) {
                    if (this.id === 'addModal') {
                        confirmCancelAdd();
                    } else {
                        closeModal(this.id);
                    }
                }
            });
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (document.getElementById('confirmModal').style.display === 'flex') {
                    confirmNo();
                    return;
                }
                if (document.getElementById('addModal').classList.contains('active')) {
                    confirmCancelAdd();
                    return;
                }
            }
        });

        /* ── Confirm modal ── */
        function showConfirm(title, sub, msg, onYes) {
            document.getElementById('confirmTitle').textContent = title;
            document.getElementById('confirmSub').textContent = sub;
            document.getElementById('confirmMsg').textContent = msg;
            confirmCallback = onYes;
            document.getElementById('confirmModal').style.display = 'flex';
        }

        function confirmYes() {
            document.getElementById('confirmModal').style.display = 'none';
            if (confirmCallback) confirmCallback();
            confirmCallback = null;
        }

        function confirmNo() {
            document.getElementById('confirmModal').style.display = 'none';
            confirmCallback = null;
        }

        /* ── Success popup ── */
        function showSuccess(icon, title, sub, msg, onOk) {
            document.getElementById('sucIcon').textContent = icon;
            document.getElementById('sucTitle').textContent = title;
            document.getElementById('sucSub').textContent = sub;
            document.getElementById('sucMsg').textContent = msg;
            successCallback = onOk || null;
            document.getElementById('successPopup').style.display = 'flex';
        }

        function closeSuccessPopup() {
            document.getElementById('successPopup').style.display = 'none';
            if (successCallback) {
                successCallback();
                successCallback = null;
            }
        }

        /* ── Validation modal ── */
        function showValidation(msg) {
            document.getElementById('valMsg').textContent = msg;
            document.getElementById('validationModal').style.display = 'flex';
        }

        function closeValidationModal() {
            document.getElementById('validationModal').style.display = 'none';
        }

        /* ── Add Operator Logic ── */
        let fpDob = null;

        function initDobPicker() {
            if (fpDob) return;
            fpDob = flatpickr('#dobPicker', {
                dateFormat: 'm/d/Y',
                maxDate: 'today',
                allowInput: false,
                onChange: function(dates) {
                    if (!dates.length) {
                        document.getElementById('f_age').value = '';
                        setHint('hint_dob', '', '');
                        document.getElementById('dobPicker').classList.remove('field-error', 'field-ok');
                        return;
                    }
                    const d = dates[0],
                        today = new Date();
                    let age = today.getFullYear() - d.getFullYear();
                    const m = today.getMonth() - d.getMonth();
                    if (m < 0 || (m === 0 && today.getDate() < d.getDate())) age--;
                    document.getElementById('f_age').value = age >= 0 ? age : '';
                    document.getElementById('dobPicker').classList.remove('field-error');
                    document.getElementById('dobPicker').classList.add('field-ok');
                    setHint('hint_dob', 'success', '✓ Age computed: ' + age + ' yrs');
                }
            });
        }

        function liveValidate(el) {
            const id = el.id.replace('f_', '');
            if (el.value.trim() === '' || el.value === '') {
                el.classList.add('field-error');
                el.classList.remove('field-ok');
                setHint('hint_' + id, 'error', '⚠ This field is required');
            } else {
                el.classList.remove('field-error');
                el.classList.add('field-ok');
                setHint('hint_' + id, '', '');
            }
        }

        function validateEmail() {
            const el = document.getElementById('f_email');
            const val = el.value.trim();
            const regex = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
            if (val === '') {
                el.classList.remove('field-error', 'field-ok');
                setHint('hint_email', '', '');
                return true;
            }
            if (!regex.test(val)) {
                el.classList.add('field-error');
                el.classList.remove('field-ok');
                setHint('hint_email', 'error', '⚠ Enter a valid email (e.g. juan@gmail.com)');
                return false;
            }
            el.classList.remove('field-error');
            el.classList.add('field-ok');
            setHint('hint_email', 'success', '✓ Email looks good');
            return true;
        }

        function validatePhone() {
            const el = document.getElementById('f_phone');
            let digits = el.value.replace(/\D/g, '').substring(0, 11);
            el.value = digits;
            if (digits === '') {
                el.classList.remove('field-error', 'field-ok');
                setHint('hint_phone', '', '');
                return true;
            }
            if (!digits.startsWith('09')) {
                el.classList.add('field-error');
                el.classList.remove('field-ok');
                setHint('hint_phone', 'error', '⚠ Must start with 09');
                return false;
            }
            if (digits.length < 11) {
                el.classList.add('field-error');
                el.classList.remove('field-ok');
                setHint('hint_phone', 'error', '⚠ Must be 11 digits — ' + (11 - digits.length) + ' more');
                return false;
            }
            el.classList.remove('field-error');
            el.classList.add('field-ok');
            setHint('hint_phone', 'success', '✓ Valid Philippine number');
            return true;
        }

        function setHint(id, type, msg) {
            const el = document.getElementById(id);
            if (!el) return;
            el.className = type ? 'field-hint ' + type : 'field-hint';
            el.textContent = msg;
        }

        function confirmAddOperator() {
            const first = document.getElementById('f_first_name').value.trim();
            const last = document.getElementById('f_last_name').value.trim();
            const sex = document.getElementById('f_sex').value;
            const dob = document.getElementById('dobPicker').value;
            const email = document.getElementById('f_email').value.trim();
            const phone = document.getElementById('f_phone').value.trim();
            const province = document.getElementById('f_province').value.trim();
            const municipality = document.getElementById('f_municipality').value.trim();
            const barangay = document.getElementById('f_barangay').value.trim();
            let valid = true;

            ['f_first_name', 'f_last_name', 'f_sex'].forEach(function(id) {
                const el = document.getElementById(id);
                liveValidate(el);
                if (!el.value || el.value.trim() === '') valid = false;
            });
            ['f_province', 'f_municipality', 'f_barangay'].forEach(function(id) {
                const el = document.getElementById(id);
                liveValidate(el);
                if (!el.value || el.value.trim() === '') valid = false;
            });
            if (!dob) {
                document.getElementById('dobPicker').classList.add('field-error');
                setHint('hint_dob', 'error', '⚠ Please select a date of birth');
                valid = false;
            }
            if (!validateEmail()) valid = false;
            else if (email === '') {
                document.getElementById('f_email').classList.add('field-error');
                setHint('hint_email', 'error', '⚠ This field is required');
                valid = false;
            }
            if (!validatePhone()) valid = false;
            else if (phone === '') {
                document.getElementById('f_phone').classList.add('field-error');
                setHint('hint_phone', 'error', '⚠ This field is required');
                valid = false;
            }
            if (!valid) {
                showValidation('Please fill in all required fields highlighted in red before adding the operator.');
                return;
            }

            showConfirm(
                'Add Operator', 'Please confirm your action',
                'Are you sure you want to register this operator to the system?',
                function() {
                    showSuccess('🎉', 'Operator Added!', 'Registration successful',
                        'The operator has been successfully registered.',
                        function() {
                            document.getElementById('r_first_name').value = first;
                            document.getElementById('r_middle_name').value = document.getElementById('f_middle_name').value.trim();
                            document.getElementById('r_last_name').value = last;
                            document.getElementById('r_sex').value = sex;
                            document.getElementById('r_dob').value = (function() {
                                const fp = document.getElementById('dobPicker')._flatpickr;
                                if (fp && fp.selectedDates[0]) {
                                    const d = fp.selectedDates[0];
                                    const yyyy = d.getFullYear(),
                                        mm = String(d.getMonth() + 1).padStart(2, '0'),
                                        dd2 = String(d.getDate()).padStart(2, '0');
                                    return yyyy + '-' + mm + '-' + dd2;
                                }
                                return dob;
                            })();
                            document.getElementById('r_age').value = document.getElementById('f_age').value;
                            document.getElementById('r_email').value = email;
                            document.getElementById('r_phone').value = phone;
                            document.getElementById('r_province').value = province;
                            document.getElementById('r_municipality').value = municipality;
                            document.getElementById('r_barangay').value = barangay;
                            document.getElementById('realAddForm').submit();
                        }
                    );
                }
            );
        }

        function confirmCancelAdd() {
            showConfirm(
                'Cancel Registration', 'Unsaved data will be lost',
                'Are you sure you want to cancel? All entered information will be discarded.',
                function() {
                    closeAddModal();
                }
            );
        }

        function closeAddModal() {
            closeModal('addModal');
            ['f_first_name', 'f_middle_name', 'f_last_name', 'f_email', 'f_phone'].forEach(function(id) {
                const el = document.getElementById(id);
                if (el) {
                    el.value = '';
                    el.classList.remove('field-error', 'field-ok');
                }
            });
            document.getElementById('f_sex').value = '';
            document.getElementById('f_sex').classList.remove('field-error', 'field-ok');
            document.getElementById('f_age').value = '';
            document.getElementById('f_province').value = 'Zamboanga del Sur';
            document.getElementById('f_municipality').value = '';
            document.getElementById('f_barangay').value = '';
            ['f_province', 'f_municipality', 'f_barangay'].forEach(function(id) {
                const el = document.getElementById(id);
                if (el) el.classList.remove('field-error', 'field-ok');
            });
            if (fpDob) fpDob.clear();
            ['hint_first_name', 'hint_last_name', 'hint_sex', 'hint_dob', 'hint_email', 'hint_phone',
                'hint_province', 'hint_municipality', 'hint_barangay'
            ].forEach(function(id) {
                setHint(id, '', '');
            });
            document.getElementById('dobPicker').classList.remove('field-error', 'field-ok');
        }

        /* ── Print ── */
        function doPrint() {
            const title = document.querySelector('#printArea h2');
            title.style.display = 'block';
            window.print();
            title.style.display = 'none';
        }

        /* ── Search toggle ── */
        function handleFieldChange() {
            document.getElementById('textInput').value = '';
            document.getElementById('statusDropdown').value = '';
            document.getElementById('field_changed').value = '1';
            document.getElementById('searchForm').submit();
        }

        function toggleInputs() {
            const field = document.getElementById('search_field').value;
            document.getElementById('textInput').style.display = (field === 'name') ? 'inline-block' : 'none';
            document.getElementById('statusDropdown').style.display = (field === 'Status') ? 'inline-block' : 'none';
            document.getElementById('searchBtn').style.display = (field !== 'All') ? 'inline-block' : 'none';
        }
        window.onload = toggleInputs;

        <?php if ($message || $error): ?>
            document.addEventListener('DOMContentLoaded', function() {
                <?php if ($message): ?>
                    showSuccess('✅', 'Success!', '', <?= json_encode($message) ?>, function() {
                        window.location.href = window.location.pathname;
                    });
                    document.getElementById('successPopup').style.display = 'flex';
                <?php elseif ($error): ?>
                    openModal('addModal');
                    setTimeout(initDobPicker, 80);
                    showValidation(<?= json_encode($error) ?>);
                <?php endif; ?>
            });
        <?php endif; ?>
    </script>
</body>

</html>