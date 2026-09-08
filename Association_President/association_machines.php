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

/* ── SET MACHINE STATUS ── */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['set_status'])) {
    $machine_id = (int)$_POST['machine_id'];
    $new_status = trim($_POST['new_status']);
    $allowed    = ['Active', 'Inactive', 'Under Maintenance', 'Damaged'];
    if (!in_array($new_status, $allowed)) {
        $error = "Invalid status value.";
    } else {
        $s = $conn->prepare("UPDATE machines SET status = ? WHERE id = ? AND association_id = ?");
        $s->bind_param("sii", $new_status, $machine_id, $association_id);
        if ($s->execute()) $message = "Machine status updated to \"$new_status\" successfully!";
        else $error = "Failed to update machine status.";
        $s->close();
    }
}

/* ── ASSIGN OPERATOR ── */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['assign_operator'])) {
    $machine_id  = $_POST['machine_id'];
    $operator_id = $_POST['operator_id'];
    $chk = $conn->prepare("SELECT id FROM machine_operators WHERE machine_id=? AND operator_id=? AND status='Active'");
    $chk->bind_param("ii", $machine_id, $operator_id);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        $error = "Operator already assigned to this machine.";
    } else {
        $s = $conn->prepare("INSERT INTO machine_operators (machine_id,operator_id,status) VALUES (?,?,'Active')");
        $s->bind_param("ii", $machine_id, $operator_id);
        $s->execute();
        $message = "Operator assigned successfully!";
        $s->close();
    }
    $chk->close();
}

/* ── UNASSIGN OPERATOR ── */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['unassign_operator'])) {
    $machine_id  = (int)$_POST['machine_id'];
    $operator_id = (int)$_POST['operator_id'];
    $v = $conn->prepare("SELECT mo.id FROM machine_operators mo INNER JOIN machines m ON mo.machine_id=m.id WHERE mo.machine_id=? AND mo.operator_id=? AND m.association_id=? AND mo.status='Active'");
    $v->bind_param("iii", $machine_id, $operator_id, $association_id);
    $v->execute();
    if ($v->get_result()->num_rows > 0) {
        $s = $conn->prepare("UPDATE machine_operators SET status='Inactive' WHERE machine_id=? AND operator_id=?");
        $s->bind_param("ii", $machine_id, $operator_id);
        if ($s->execute()) $message = "Operator unassigned successfully!";
        else $error = "Failed to unassign operator.";
        $s->close();
    } else {
        $error = "Assignment not found or unauthorized.";
    }
    $v->close();
}

/* ── FETCH MACHINES ── */
$search_field  = $_GET['search_field']   ?? 'All';
$search_term   = $_GET['search_term']    ?? '';
$status_filter = $_GET['statusDropdown'] ?? '';
$date_from     = $_GET['date_from']      ?? '';
$date_to       = $_GET['date_to']        ?? '';
$field_changed = $_GET['field_changed']  ?? '0';

$where_parts = ["m.association_id = ?"];
if ($field_changed !== '1' && !empty($search_term)) {
    if ($search_field === 'machine_name')
        $where_parts[] = "m.machine_name LIKE '%" . $conn->real_escape_string($search_term) . "%'";
    elseif ($search_field === 'type')
        $where_parts[] = "m.type = '" . $conn->real_escape_string($search_term) . "'";
}
if ($search_field === 'Status' && $field_changed !== '1') {
    if (!empty($status_filter)) $where_parts[] = "m.status = '" . $conn->real_escape_string($status_filter) . "'";
    if (!empty($date_from))     $where_parts[] = "DATE(m.created_at) >= '" . $conn->real_escape_string($date_from) . "'";
    if (!empty($date_to))       $where_parts[] = "DATE(m.created_at) <= '" . $conn->real_escape_string($date_to) . "'";
}
if ($search_field === 'registered_date' && $field_changed !== '1') {
    if (!empty($date_from)) $where_parts[] = "DATE(m.created_at) >= '" . $conn->real_escape_string($date_from) . "'";
    if (!empty($date_to))   $where_parts[] = "DATE(m.created_at) <= '" . $conn->real_escape_string($date_to) . "'";
}
$where_sql = "WHERE " . implode(" AND ", $where_parts);

$machines_sql = "
    SELECT m.*,
        DATE_FORMAT(m.created_at,'%m/%d/%Y') AS assigned_at_fmt,
        GROUP_CONCAT(o.name SEPARATOR ', ') AS assigned_operators
    FROM machines m
    LEFT JOIN machine_operators mo ON m.id=mo.machine_id AND mo.status='Active'
    LEFT JOIN operators o ON mo.operator_id=o.id
    $where_sql
    GROUP BY m.id
    ORDER BY m.created_at DESC
";
$stmt = $conn->prepare($machines_sql);
$stmt->bind_param("i", $association_id);
$stmt->execute();
$machines = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
// Fetch association name
$assoc_name_stmt = $conn->prepare("SELECT name FROM associations WHERE id = ?");
$assoc_name_stmt->bind_param("i", $association_id);
$assoc_name_stmt->execute();
$assoc_name_row = $assoc_name_stmt->get_result()->fetch_assoc();
$assoc_name_stmt->close();
$association_name = $assoc_name_row['name'] ?? $_SESSION['user_role'];

/* ── OPERATORS FOR DROPDOWN ── */
$op_stmt = $conn->prepare("SELECT * FROM operators WHERE association_id=? AND status='Active' ORDER BY name");
$op_stmt->bind_param("i", $association_id);
$op_stmt->execute();
$operators = $op_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$op_stmt->close();

/* ── ASSIGNED PER MACHINE ── */
$as_stmt = $conn->prepare("
    SELECT mo.machine_id, mo.operator_id, o.name AS operator_name, o.phone
    FROM machine_operators mo
    INNER JOIN operators o ON mo.operator_id=o.id
    INNER JOIN machines m  ON mo.machine_id=m.id
    WHERE m.association_id=? AND mo.status='Active'
    ORDER BY o.name
");
$as_stmt->bind_param("i", $association_id);
$as_stmt->execute();
$assigned_by_machine = [];
foreach ($as_stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row)
    $assigned_by_machine[$row['machine_id']][] = $row;
$as_stmt->close();
?>
<!DOCTYPE html>
<html>

<head>
    <title>Association Machines</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.css">
    <style>
        /* ── base ── */
        .main-content {
            padding: 20px;
        }

        .usernames {
            color: #000000;
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 6px;
        }

        .usernames a {
            color: #000000;
            margin-left: 15px;
            text-decoration: underline;
            font-weight: normal;
        }

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

        .flatpickr-input {
            padding: 8px 12px !important;
            font-size: 13px !important;
            border: 1px solid #2d7a2d !important;
            border-radius: 6px !important;
            background: white !important;
            color: #333 !important;
            width: 130px !important;
            box-sizing: border-box !important;
            cursor: pointer !important;
        }

        label.filter-lbl {
            color: #fff;
            font-weight: bold;
            font-size: 14px;
            text-shadow: 1px 1px 3px rgba(14, 4, 4, 0.7);
        }

        /* ── table ── */
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
        }

        th,
        td {
            padding: 11px 12px;
            border-bottom: 1px solid #ddd;
            text-align: left;
            font-size: 13px;
        }

        th {
            background: #2d7a2d;
            color: white;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .3px;
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

        img.machine-img {
            width: 48px;
            height: 48px;
            object-fit: cover;
            border-radius: 6px;
        }

        /* ── status badges ── */
        .sb {
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
            display: inline-block;
        }

        .sb-active {
            color: #065f46;
        }

        .sb-inactive {
            color: #991b1b;
        }

        .sb-maint {
            color: #92400e;
        }

        .sb-damaged {
            color: #9a3412;
        }

        .operator-badge {
            color: #2d572c;
            border-radius: 4px;
            font-size: 12px;
            display: inline-block;
        }

        /* ── action buttons ── */
        .action-buttons {
            margin-top: 20px;
            display: flex;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .action-buttons button {
            padding: 9px 18px;
            border-radius: 6px;
            border: none;
            background: #2d7a2d;
            color: white;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: .2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .action-buttons button:hover:not(:disabled) {
            background: #1a5c1a;
            transform: scale(1.03);
        }

        .action-buttons button:disabled {
            background: #2d7a2d;
            cursor: not-allowed;
            transform: none;
        }

        .action-buttons button.btn-maint {
            background: #2d7a2d;
        }

        .action-buttons button.btn-maint:hover:not(:disabled) {
            background: #1a5c1a;
        }

        .action-buttons button.btn-damaged {
            background: #2d7a2d;
        }

        .action-buttons button.btn-damaged:hover:not(:disabled) {
            background: #1a5c1a;
        }

        /* ── alerts ── */
        .alert {
            padding: 11px 15px;
            border-radius: 7px;
            font-size: 13px;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* ── modals ── */
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(3px);
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            width: 520px;
            max-width: 95%;
            max-height: 90vh;
            overflow-y: auto;
            border-radius: 14px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        }

        .modal-content::-webkit-scrollbar {
            width: 5px;
        }

        .modal-content::-webkit-scrollbar-thumb {
            background: #2d7a2d;
            border-radius: 4px;
        }

        .modal-header {
            background: linear-gradient(135deg, #2d7a2d, #1a5c1a);
            padding: 18px 22px;
            border-radius: 14px 14px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .modal-header h3 {
            color: white;
            margin: 0;
            font-size: 18px;
            font-weight: 700;
        }

        .modal-header p {
            color: rgba(255, 255, 255, .8);
            font-size: 12px;
            margin: 3px 0 0;
        }

        .modal-close {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            font-size: 17px;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .modal-close:hover {
            background: rgba(255, 255, 255, 0.35);
        }

        .modal-body {
            padding: 20px 22px;
        }

        .modal-footer {
            padding: 13px 22px;
            background: #f9fafb;
            border-top: 1px solid #e5e7eb;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            border-radius: 0 0 14px 14px;
        }

        .btn-cancel {
            padding: 8px 18px;
            background: white;
            color: #6b7280;
            border: 2px solid #e5e7eb;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-cancel:hover {
            background: #f5f5f5;
        }

        .btn-save {
            padding: 8px 20px;
            background: #2d7a2d;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-save:hover {
            background: #1a5c1a;
        }

        .m-sec {
            font-size: 11px;
            font-weight: 700;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: .5px;
            margin: 12px 0 8px;
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

        /* ── assigned list ── */
        .assigned-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 8px;
            padding: 9px 13px;
            margin-bottom: 8px;
        }

        .assigned-item-name {
            font-weight: 700;
            font-size: 13px;
            color: #1f2937;
        }

        .assigned-item-phone {
            font-size: 11px;
            color: #6b7280;
        }

        .btn-unassign {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fca5a5;
            border-radius: 6px;
            padding: 4px 11px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
        }

        .btn-unassign:hover {
            background: #dc2626;
            color: white;
        }

        .no-ops-msg {
            text-align: center;
            padding: 14px;
            color: #9ca3af;
            background: #f9fafb;
            border-radius: 7px;
            font-size: 12px;
            border: 1px dashed #e5e7eb;
            margin-bottom: 12px;
        }

        /* ── CONFIRM / SUCCESS MODALS ── */
        #confirmModal,
        #successPopup {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .6);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            padding: 16px;
        }

        @keyframes popIn {
            from {
                opacity: 0;
                transform: scale(.93) translateY(10px)
            }

            to {
                opacity: 1;
                transform: none
            }
        }

        .inner-modal {
            background: #fff;
            border-radius: 14px;
            width: 100%;
            max-width: 390px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, .28);
            overflow: hidden;
            animation: popIn .22s ease both;
        }

        .inner-head {
            padding: 18px 22px;
        }

        .inner-head.green {
            background: linear-gradient(135deg, #1e6b35, #2d9148);
        }

        .inner-head.orange {
            background: linear-gradient(135deg, #b45309, #d97706);
        }

        .inner-head.red {
            background: linear-gradient(135deg, #c2410c, #ea580c);
        }

        .inner-head.center-head {
            text-align: center;
            padding: 28px 22px 18px;
        }

        .ih-title {
            color: #fff;
            font-size: 15px;
            font-weight: 700;
        }

        .ih-sub {
            color: rgba(255, 255, 255, .82);
            font-size: 12px;
            margin-top: 2px;
        }

        .ih-icon {
            font-size: 2.8rem;
            line-height: 1;
            margin-bottom: 8px;
        }

        .inner-body {
            padding: 20px 22px;
        }

        .inner-body p {
            color: #374151;
            font-size: 14px;
            margin-bottom: 18px;
            line-height: 1.55;
        }

        .inner-foot {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .btn-yes {
            padding: 9px 22px;
            background: linear-gradient(135deg, #1e6b35, #2d9148);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
        }

        .btn-yes:hover {
            filter: brightness(1.08);
        }

        .btn-no {
            padding: 9px 18px;
            background: #f5f5f5;
            color: #555;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-ok {
            padding: 10px 32px;
            background: linear-gradient(135deg, #14532d, #16a34a);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
        }

        .confirm-detail {
            background: #f8fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 11px 14px;
            margin-bottom: 16px;
            font-size: 13px;
        }

        .cd-row {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
        }

        .cd-lbl {
            color: #6b7280;
        }

        .cd-val {
            font-weight: 700;
            color: #1f2937;
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
        <div class="usernames">
            Welcome <?= htmlspecialchars($association_name) ?>
            <a href="/agri_system/logout.php">Logout</a>
        </div>
        <h2 style="text-align:center;color: #2d7a2d; margin-bottom:16px;">List of Machines</h2>

        <?php if ($message): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Filter Bar -->
        <form class="search-form" method="GET" id="searchForm">
            <input type="hidden" name="field_changed" id="field_changed" value="0">
            <select name="search_field" id="search_field" onchange="handleFieldChange()">
                <option value="All" <?= $search_field === 'All'             ? 'selected' : '' ?>>All</option>
                <option value="type" <?= $search_field === 'type'            ? 'selected' : '' ?>>Machine Type</option>
                <option value="machine_name" <?= $search_field === 'machine_name'    ? 'selected' : '' ?>>Machine Name</option>
                <option value="Status" <?= $search_field === 'Status'          ? 'selected' : '' ?>>Status</option>
                <option value="registered_date" <?= $search_field === 'registered_date' ? 'selected' : '' ?>>Date Acquired</option>
            </select>
            <input type="text" name="search_term" id="textInput" placeholder="Enter search..." value="<?= htmlspecialchars($search_term) ?>" style="display:none;">
            <select name="search_term" id="typeDropdown" style="display:none;">
                <option value="" disabled selected hidden>All Type</option>
                <option value="Tractor" <?= ($search_field === 'type' && $search_term === 'Tractor')  ? 'selected' : '' ?>>Tractor</option>
                <option value="Harvester" <?= ($search_field === 'type' && $search_term === 'Harvester') ? 'selected' : '' ?>>Harvester</option>
            </select>
            <select name="statusDropdown" id="statusDropdown" style="display:none;">
                <option value="" disabled selected hidden>Select Status</option>
                <option value="Active" <?= $status_filter === 'Active'           ? 'selected' : '' ?>>Active</option>
                <option value="Under Maintenance" <?= $status_filter === 'Under Maintenance' ? 'selected' : '' ?>>Under Maintenance</option>
                <option value="Damaged" <?= $status_filter === 'Damaged'          ? 'selected' : '' ?>>Damaged</option>
            </select>
            <label class="filter-lbl" id="from_label" style="display:none;">From</label>
            <input type="text" id="from_date_display" placeholder="mm/dd/yyyy" readonly style="display:none;">
            <input type="hidden" name="date_from" id="date_from" value="<?= htmlspecialchars($date_from) ?>">
            <label class="filter-lbl" id="to_label" style="display:none;">To</label>
            <input type="text" id="to_date_display" placeholder="mm/dd/yyyy" readonly style="display:none;">
            <input type="hidden" name="date_to" id="date_to" value="<?= htmlspecialchars($date_to) ?>">
            <button type="submit" id="searchBtn" style="display:none;"> Search</button>
            <span style="margin-left:auto; color: #000000; font-weight:bold; font-size:14px;
                      white-space:nowrap;">
                Total Machines: <?= count($machines) ?>
            </span>
        </form>

        <!-- Table -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date Acquired</th>
                        <th>Image</th>
                        <th>Machine Type</th>
                        <th>Machine Name</th>
                        <th>Operators</th>
                        <th>Rate/Ha</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="machineTable">
                    <?php if ($machines): $i = 1;
                        foreach ($machines as $m):
                            $st  = $m['status'];
                            $scl = match ($st) {
                                'Active'            => 'sb-active',
                                'Inactive'          => 'sb-inactive',
                                'Under Maintenance' => 'sb-maint',
                                'Damaged'           => 'sb-damaged',
                                default             => 'sb-inactive'
                            };
                    ?>
                            <tr class="machine-row"
                                data-id="<?= $m['id'] ?>"
                                data-name="<?= htmlspecialchars($m['machine_name']) ?>"
                                data-type="<?= htmlspecialchars($m['type']) ?>"
                                data-status="<?= htmlspecialchars($st) ?>"
                                data-image="<?= htmlspecialchars($m['image_path'] ?? '') ?>">
                                <td><?= $i++ ?></td>
                                <td><?= htmlspecialchars($m['assigned_at_fmt'] ?? '—') ?></td>
                                <td>
                                    <?php if ($m['image_path']): ?>
                                        <img src="<?= htmlspecialchars($m['image_path']) ?>" class="machine-img">
                                    <?php else: ?>
                                        <span style="color:#9ca3af;font-size:12px;">No Image</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($m['type']) ?></td>
                                <td><strong><?= htmlspecialchars($m['machine_name']) ?></strong></td>
                                <td>
                                    <?= $m['assigned_operators']
                                        ? '<span class="operator-badge">' . htmlspecialchars($m['assigned_operators']) . '</span>'
                                        : '<span style="color:#9ca3af;">None</span>' ?>
                                </td>
                                <td>₱<?= number_format($m['price_per_hectare'], 2) ?></td>
                                <td><span class="sb <?= $scl ?>"><?= htmlspecialchars($st) ?></span></td>
                            </tr>
                        <?php endforeach;
                    else: ?>
                        <tr>
                            <td colspan="7" style="text-align:center;padding:24px;color:#999;">No records found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <button id="btnAssign" onclick="openAssignModal()" disabled> Operator</button>
            <button id="btnActivate" onclick="triggerStatus('Active')" disabled> Activate</button>
            <button id="btnMaint" onclick="triggerStatus('Under Maintenance')" disabled class="btn-maint"> Under Maintenance</button>
            <button id="btnDamaged" onclick="triggerStatus('Damaged')" disabled class="btn-damaged"> Damaged</button>
            <button onclick="window.print()"> Print</button>
        </div>
    </div>

    <!-- ══════════════════════════════════════
     ASSIGN OPERATOR MODAL
══════════════════════════════════════ -->
    <div id="assignModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h3><i class="fas fa-user-cog"></i> Manage Operators</h3>
                    <p id="assignMachineName">—</p>
                </div>
            </div>
            <div class="modal-body">
                <div class="m-sec"><i class="fas fa-users"></i> Currently Assigned</div>
                <div id="assignedList"></div>
                <div class="m-sec"><i class="fas fa-user-plus"></i> Assign New Operator</div>
                <form method="POST" id="assignForm">
                    <input type="hidden" name="machine_id" id="assign_machine_id">
                    <select name="operator_id" required style="width:100%;padding:9px 12px;border:2px solid #e5e7eb;border-radius:7px;font-size:13px;box-sizing:border-box;margin-bottom:4px;">
                        <option value="" disabled selected hidden>Select Operator</option>
                        <?php foreach ($operators as $op): ?>
                            <option value="<?= $op['id'] ?>"><?= htmlspecialchars($op['name']) ?> (<?= htmlspecialchars($op['phone']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <div class="modal-footer" style="padding:14px 0 0;border-top:none;background:transparent;">
                        <button type="submit" name="assign_operator" class="btn-save"><i class="fas fa-user-plus"></i> Assign</button>
                        <button type="button" class="btn-cancel" onclick="closeModal('assignModal')">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Hidden unassign forms -->
    <?php foreach ($assigned_by_machine as $machine_id => $ops): foreach ($ops as $op): ?>
            <form method="POST" id="unassign-<?= $machine_id ?>-<?= $op['operator_id'] ?>" style="display:none;">
                <input type="hidden" name="unassign_operator" value="1">
                <input type="hidden" name="machine_id" value="<?= $machine_id ?>">
                <input type="hidden" name="operator_id" value="<?= $op['operator_id'] ?>">
            </form>
    <?php endforeach;
    endforeach; ?>

    <!-- Hidden status form -->
    <form method="POST" id="statusForm" style="display:none;">
        <input type="hidden" name="set_status" value="1">
        <input type="hidden" name="machine_id" id="sf_machine_id">
        <input type="hidden" name="new_status" id="sf_new_status">
    </form>

    <!-- ══════════════════════════════════════
     CONFIRM MODAL
══════════════════════════════════════ -->
    <div id="confirmModal">
        <div class="inner-modal">
            <div class="inner-head green" id="cf_head">
                <div class="ih-title" id="cf_title">Confirm Action</div>
                <div class="ih-sub" id="cf_sub">Please confirm</div>
            </div>
            <div class="inner-body">
                <div class="confirm-detail" id="cf_detail"></div>
                <p id="cf_msg">Are you sure you want to proceed?</p>
                <div class="inner-foot">
                    <button class="btn-yes" id="cf_yes_btn" onclick="confirmYes()"><i class="fas fa-check"></i> Yes</button>
                    <button class="btn-no" onclick="confirmNo()">No</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════
     SUCCESS POPUP
══════════════════════════════════════ -->
    <div id="successPopup">
        <div class="inner-modal">
            <div class="inner-head green center-head" id="sc_head">
                <div class="ih-icon" id="sc_icon">✅</div>
                <div class="ih-title" id="sc_title">Success!</div>
                <div class="ih-sub" id="sc_sub"></div>
            </div>
            <div class="inner-body" style="text-align:center;">
                <p id="sc_msg"></p>
                <button class="btn-ok" onclick="closeSuccess()">OK</button>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.js"></script>
    <script>
        const assignedByMachine = <?= json_encode($assigned_by_machine) ?>;
        let selectedMachine = null;
        let confirmCb = null;

        /* ── Flatpickr ── */
        window.fpFrom = flatpickr('#from_date_display', {
            dateFormat: 'm/d/Y',
            allowInput: false,
            onChange: function(d) {
                document.getElementById('date_from').value = d.length ? d[0].getFullYear() + '-' + String(d[0].getMonth() + 1).padStart(2, '0') + '-' + String(d[0].getDate()).padStart(2, '0') : '';
            }
        });
        window.fpTo = flatpickr('#to_date_display', {
            dateFormat: 'm/d/Y',
            allowInput: false,
            onChange: function(d) {
                document.getElementById('date_to').value = d.length ? d[0].getFullYear() + '-' + String(d[0].getMonth() + 1).padStart(2, '0') + '-' + String(d[0].getDate()).padStart(2, '0') : '';
            }
        });
        <?php if ($date_from): ?>fpFrom.setDate('<?= $date_from ?>', true, 'Y-m-d');
        <?php endif; ?>
        <?php if ($date_to):   ?>fpTo.setDate('<?= $date_to ?>', true, 'Y-m-d');
        <?php endif; ?>

        function handleFieldChange() {
            document.getElementById('textInput').value = '';
            document.getElementById('typeDropdown').value = '';
            document.getElementById('statusDropdown').value = '';
            document.getElementById('date_from').value = '';
            document.getElementById('date_to').value = '';
            if (window.fpFrom) fpFrom.clear();
            if (window.fpTo) fpTo.clear();
            document.getElementById('field_changed').value = '1';
            document.getElementById('searchForm').submit();
        }

        function toggleInputs() {
            const f = document.getElementById('search_field').value;
            document.getElementById('textInput').style.display = f === 'machine_name' ? 'inline-block' : 'none';
            document.getElementById('typeDropdown').style.display = f === 'type' ? 'inline-block' : 'none';
            document.getElementById('statusDropdown').style.display = f === 'Status' ? 'inline-block' : 'none';
            document.getElementById('from_date_display').style.display = (f === 'Status' || f === 'registered_date') ? 'inline-block' : 'none';
            document.getElementById('to_date_display').style.display = (f === 'Status' || f === 'registered_date') ? 'inline-block' : 'none';
            document.getElementById('from_label').style.display = (f === 'Status' || f === 'registered_date') ? 'inline-block' : 'none';
            document.getElementById('to_label').style.display = (f === 'Status' || f === 'registered_date') ? 'inline-block' : 'none';
            document.getElementById('searchBtn').style.display = f !== 'All' ? 'inline-block' : 'none';
        }
        window.addEventListener('DOMContentLoaded', toggleInputs);

        /* ── Row selection ── */
        document.querySelectorAll('.machine-row').forEach(function(row) {
            row.addEventListener('click', function() {
                document.querySelectorAll('.machine-row').forEach(r => r.classList.remove('selected'));
                this.classList.add('selected');
                selectedMachine = {
                    id: this.dataset.id,
                    name: this.dataset.name,
                    type: this.dataset.type,
                    status: this.dataset.status,
                    image: this.dataset.image
                };
                document.getElementById('btnAssign').disabled = false;
                document.getElementById('btnActivate').disabled = false;
                document.getElementById('btnMaint').disabled = false;
                document.getElementById('btnDamaged').disabled = false;
            });
            row.addEventListener('dblclick', function() {
                if (this.dataset.id) window.location.href = 'association_machine_profile.php?id=' + this.dataset.id;
            });
        });

        /* ── Modal helpers ── */
        function openModal(id) {
            document.getElementById(id).classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
            document.body.style.overflow = '';
        }
        document.querySelectorAll('.modal').forEach(m => {
            m.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });
        });
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') document.querySelectorAll('.modal.active').forEach(m => {
                m.classList.remove('active');
                document.body.style.overflow = '';
            });
        });

        /* ── STATUS TRIGGER ── */
        const iconMap = {
            'Active': '✅',
            'Under Maintenance': '🔧',
            'Damaged': '⚠️',
            'Inactive': '🔴'
        };
        const headClassMap = {
            'Active': 'green',
            'Under Maintenance': 'orange',
            'Damaged': 'red',
            'Inactive': 'green'
        };

        function triggerStatus(newStatus) {
            if (!selectedMachine) return;
            if (newStatus === selectedMachine.status) {
                alert('Machine is already set to "' + newStatus + '".');
                return;
            }

            /* style the confirm header to match the action */
            const cfHead = document.getElementById('cf_head');
            cfHead.className = 'inner-head ' + (headClassMap[newStatus] || 'green');

            document.getElementById('cf_title').textContent = 'Change Machine Status';
            document.getElementById('cf_sub').textContent = 'Please review and confirm';
            document.getElementById('cf_detail').innerHTML =
                '<div class="cd-row"><span class="cd-lbl">Machine</span><span class="cd-val">' + selectedMachine.name + '</span></div>' +
                '<div class="cd-row"><span class="cd-lbl">Type</span><span class="cd-val">' + selectedMachine.type + '</span></div>' +
                '<div class="cd-row"><span class="cd-lbl">Current Status</span><span class="cd-val">' + selectedMachine.status + '</span></div>' +
                '<div class="cd-row"><span class="cd-lbl">New Status</span><span class="cd-val">' + iconMap[newStatus] + ' ' + newStatus + '</span></div>';
            document.getElementById('cf_msg').textContent = 'Are you sure you want to set this machine to "' + newStatus + '"?';

            confirmCb = function() {
                document.getElementById('sf_machine_id').value = selectedMachine.id;
                document.getElementById('sf_new_status').value = newStatus;

                /* style success popup header */
                document.getElementById('sc_head').className = 'inner-head ' + (headClassMap[newStatus] || 'green') + ' center-head';

                showSuccess(
                    iconMap[newStatus],
                    'Status Updated!',
                    selectedMachine.name,
                    'Machine status has been saved as "' + newStatus + '".',
                    function() {
                        document.getElementById('statusForm').submit();
                    }
                );
            };

            document.getElementById('confirmModal').style.display = 'flex';
        }

        function confirmYes() {
            document.getElementById('confirmModal').style.display = 'none';
            if (confirmCb) {
                confirmCb();
                confirmCb = null;
            }
        }

        function confirmNo() {
            document.getElementById('confirmModal').style.display = 'none';
            confirmCb = null;
        }

        /* ── SUCCESS ── */
        let successCb = null;

        function showSuccess(icon, title, sub, msg, cb) {
            successCb = cb || null;
            document.getElementById('sc_icon').textContent = icon;
            document.getElementById('sc_title').textContent = title;
            document.getElementById('sc_sub').textContent = sub;
            document.getElementById('sc_msg').textContent = msg;
            document.getElementById('successPopup').style.display = 'flex';
        }

        function closeSuccess() {
            document.getElementById('successPopup').style.display = 'none';
            if (successCb) {
                successCb();
                successCb = null;
            }
        }

        /* ── ASSIGN MODAL ── */
        function openAssignModal() {
            if (!selectedMachine) return;
            document.getElementById('assign_machine_id').value = selectedMachine.id;
            document.getElementById('assignMachineName').textContent = selectedMachine.name;
            const listDiv = document.getElementById('assignedList');
            listDiv.innerHTML = '';
            const ops = assignedByMachine[selectedMachine.id] || [];
            if (ops.length === 0) {
                listDiv.innerHTML = '<div class="no-ops-msg"><i class="fas fa-user-slash"></i> No operators assigned yet.</div>';
            } else {
                ops.forEach(function(op) {
                    const d = document.createElement('div');
                    d.className = 'assigned-item';
                    d.innerHTML =
                        '<div>' +
                        '<div class="assigned-item-name"><i class="fas fa-user" style="color:#2d7a2d;margin-right:5px;"></i>' + op.operator_name + '</div>' +
                        '<div class="assigned-item-phone"><i class="fas fa-phone" style="margin-right:4px;"></i>' + (op.phone || '—') + '</div>' +
                        '</div>' +
                        '<button type="button" class="btn-unassign" onclick="doUnassign(' + selectedMachine.id + ',' + op.operator_id + ',\'' + op.operator_name.replace(/'/g, "\\'") + '\')">' +
                        '<i class="fas fa-user-minus"></i> Remove</button>';
                    listDiv.appendChild(d);
                });
            }
            openModal('assignModal');
        }

        function doUnassign(mid, oid, name) {
            if (confirm('Remove ' + name + ' from this machine?')) {
                const f = document.getElementById('unassign-' + mid + '-' + oid);
                if (f) f.submit();
            }
        }

        /* ── auto-dismiss alerts ── */
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.alert').forEach(function(el) {
                setTimeout(function() {
                    el.style.transition = 'opacity .4s';
                    el.style.opacity = '0';
                    setTimeout(function() {
                        el.remove();
                    }, 400);
                }, 5000);
            });
        });
    </script>
</body>

</html>