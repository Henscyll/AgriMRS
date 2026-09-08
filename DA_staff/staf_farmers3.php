<?php
include('dastaff_header.php');
require_once '../includes/config.php';
 
// Flash messages
$successMsg = $_SESSION['success_message'] ?? '';
$errorMsg   = $_SESSION['error_message']   ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);
 
$limit = 10; 
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;
 
$searchField      = $_POST['search_field']    ?? $_GET['search_field']    ?? 'all';
$searchTerm       = $_POST['search_term']     ?? $_GET['search_term']     ?? '';
$municipalityTerm = $_POST['municipality_term'] ?? $_GET['municipality_term'] ?? '';
$barangayTerm     = $_POST['barangay_term']   ?? $_GET['barangay_term']   ?? '';
$statusTerm       = $_POST['search_status']   ?? $_GET['search_status']   ?? '';
$fromDate         = $_POST['from_date']       ?? $_GET['from_date']       ?? '';
$toDate           = $_POST['to_date']         ?? $_GET['to_date']         ?? '';
$fieldChanged     = $_POST['field_changed']   ?? '0';
$where = '';
 
if ($searchField !== 'all' && $fieldChanged !== '1') {
    $searchTerm       = trim($searchTerm);
    $municipalityTerm = trim($municipalityTerm);
    $barangayTerm     = trim($barangayTerm);
    $statusTerm       = trim($statusTerm);
    $fromDate         = trim($fromDate);
    $toDate           = trim($toDate);
    $conditions = [];
 
    if ($searchField === 'name' && $searchTerm !== '') {
        $safe = $conn->real_escape_string($searchTerm);
        $conditions[] = "CONCAT(f.first_name,' ',COALESCE(f.middle_name,''),' ',f.last_name) LIKE '%$safe%'";
    } elseif ($searchField === 'municipality' && $searchTerm !== '') {
        $safe = $conn->real_escape_string($searchTerm);
        $conditions[] = "f.municipality LIKE '%$safe%'";
    } elseif ($searchField === 'barangay' && ($barangayTerm !== '' || $municipalityTerm !== '')) {
        $safeBarangay    = $conn->real_escape_string($barangayTerm);
        $safeMunicipality = $conn->real_escape_string($municipalityTerm);
        $conditions[] = "f.barangay LIKE '%$safeBarangay%'";
        if ($safeMunicipality !== '') {
            $conditions[] = "f.municipality LIKE '%$safeMunicipality%'";
        }
    } elseif ($searchField === 'status') {
        $safeStatus = $conn->real_escape_string($statusTerm);
        if ($safeStatus !== '') {
            $conditions[] = "f.status LIKE '%$safeStatus%'";
        }
        if ($fromDate !== '' && $toDate !== '') {
            $safeFrom = $conn->real_escape_string($fromDate);
            $safeTo   = $conn->real_escape_string($toDate);
            $conditions[] = "(DATE(f.created_at) BETWEEN '$safeFrom' AND '$safeTo')";
        }
    } elseif ($searchField === 'registered') {
        if ($fromDate !== '' && $toDate !== '') {
            $safeFrom = $conn->real_escape_string($fromDate);
            $safeTo   = $conn->real_escape_string($toDate);
            $conditions[] = "(DATE(f.created_at) BETWEEN '$safeFrom' AND '$safeTo')";
        }
    } elseif ($searchField === 'date') {
        if ($fromDate !== '' && $toDate !== '') {
            $safeFrom = $conn->real_escape_string($fromDate);
            $safeTo   = $conn->real_escape_string($toDate);
            $conditions[] = "(DATE(f.created_at) BETWEEN '$safeFrom' AND '$safeTo')";
        }
    }
    if (count($conditions) > 0) {
        $where = "WHERE " . implode(" AND ", $conditions);
    }
}
 
$totalResult = $conn->query("SELECT COUNT(DISTINCT f.id) AS total FROM farmers f $where");
$totalRows   = $totalResult->fetch_assoc()['total'];
$totalPages  = ceil($totalRows / $limit);
 
$query = "SELECT f.id,
          CONCAT(f.first_name, ' ',
                 CASE WHEN f.middle_name IS NOT NULL AND f.middle_name != '' 
                      THEN CONCAT(LEFT(f.middle_name,1), '. ') ELSE '' END,
                 f.last_name) AS full_name,
          f.email, f.province, f.municipality, f.barangay, f.phone, f.status,
          DATE_FORMAT(f.created_at, '%m/%d/%Y') AS registered_at,
          COUNT(DISTINCT fl.id) AS lot_count,
          COALESCE(SUM(fl.farm_size), 0) AS total_farm_size
          FROM farmers f 
          LEFT JOIN farmer_lots fl ON f.id = fl.farmer_id AND fl.status = 'Active'
          $where 
          GROUP BY f.id
          ORDER BY f.created_at DESC
          LIMIT $limit OFFSET $offset";
$result = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en-US">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Farmer Accounts</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.css">
<style>
    .main-content {
        height: calc(200vh - 150px);
        padding: 0 20px;
    }
    .usernames { right: 180px; color: #fff; font-size: 16px; }
    .usernames a { color: #ffffff; margin-left: 10px; text-decoration: underline; }
    h2 {
        font-size: 28px; color: #fff; font-weight: bold; text-align: center;
        text-shadow: 1px 1px 3px rgba(14,4,4,0.7); margin: 10px 0 0 0; padding-bottom: 1px;
    }
    .flash-msg {
        padding: 12px 18px; border-radius: 8px; margin: 10px 0;
        font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 10px;
    }
    .flash-success { background:#e8f5e9; color:#1b5e20; border-left:4px solid #2d7a2d; }
    .flash-error   { background:#fdecea; color:#b71c1c; border-left:4px solid #d32f2f; }
 
    form {
        display: flex; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 15px;
    }
    select, input[type="text"], input[type="date"], button {
        padding: 8px 12px; font-size: 13px; border: 1px solid #ccc; border-radius: 6px;
    }
    button { background-color: #2d7a2d; color: white; border: none; cursor: pointer; transition: background-color 0.3s; }
    button:hover { background-color: #256725; }
    .flatpickr-input {
        padding: 8px 12px !important; font-size: 14px !important; border: 1px solid #ccc !important;
        border-radius: 6px !important; background: white !important; color: #333 !important;
        cursor: pointer !important; width: 130px !important; box-sizing: border-box !important;
    }
    .flatpickr-input:focus { outline: none !important; border-color: #2d7a2d !important; }
 
    .table-container {
        border: 1px solid #ddd; border-radius: 8px; background-color: white;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-top: 10px;
    }
    
    table { 
        width: 100%; border-collapse: collapse; background-color: white; table-layout: fixed; 
    }
    table th:nth-child(1), table td:nth-child(1) { width: 36px; text-align: center; padding-left: 6px; padding-right: 6px; }
    table th:nth-child(2), table td:nth-child(2) { width: 100px; white-space: nowrap; padding-left: 8px; padding-right: 6px; }
    table th:nth-child(3), table td:nth-child(3) { width: 150px; padding-left: 8px; padding-right: 6px; }
    table th:nth-child(4), table td:nth-child(4) { width: 200px; padding-left: 8px; padding-right: 6px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    table th:nth-child(5), table td:nth-child(5) { width: 220px; max-width: 220px; padding-left: 8px; padding-right: 6px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    table th:nth-child(6), table td:nth-child(6) { width: 60px; text-align: center; padding-left: 4px; padding-right: 4px; }
    table th:nth-child(7), table td:nth-child(7) { width: 105px; text-align: right; padding-right: 12px; white-space: nowrap; }
    table th:nth-child(8), table td:nth-child(8) { width: 60px; text-align: center; padding-left: 4px; padding-right: 4px; }

    th, td {
        padding: 11px 8px; text-align: left; border-bottom: 1px solid #ddd;
        vertical-align: middle; word-wrap: break-word; overflow: hidden;
        text-overflow: ellipsis; font-size: 14px;
    }
    th { background-color: #2d7a2d; color: white; font-weight: normal; white-space: nowrap; font-size: 14px; }
    
    thead { display:table; width:100%; table-layout:fixed; }
    tbody { display:block; max-height:245px; overflow-y:auto; overflow-x:hidden; width:100%; }
    tbody tr { display:table; width:100%; table-layout:fixed; cursor:pointer; }
    tr:hover { background-color:#f1f1f1; }
    tr.selected { background-color:#c3e6cb !important; }
 
    .view-container { display:flex; justify-content:center; align-items:center; gap:10px; margin-top:20px; }
    .view-container button { background-color:#2d7a2d; color:white; border:none; padding:10px 20px; border-radius:6px; cursor:pointer; font-size:15px; }
    .view-container button:hover { background-color:#256725; }
    label { color:#fff; font-weight:bold; font-size:14px; margin-right:5px; text-shadow:1px 1px 3px rgba(14,4,4,0.7); }
    .badge { display:inline-block; padding:3px 7px; border-radius:12px; font-size:11px; font-weight:600; background:#e3f2fd; color:#1976d2; }

    /* ── Modal ── */
    .modal {
        display: none; position: fixed; inset: 0;
        background: rgba(0,0,0,0.55); backdrop-filter: blur(6px);
        justify-content: center; align-items: center; z-index: 9999;
        padding: 16px;
    }
    @keyframes modalIn {
        from { opacity:0; transform: scale(0.96) translateY(16px); }
        to   { opacity:1; transform: scale(1) translateY(0); }
    }
    .modal-content {
        background: #fff; border-radius: 16px; width: 100%; max-width: 620px;
        max-height: 90vh; display: flex; flex-direction: column;
        box-shadow: 0 20px 50px rgba(0,0,0,0.22);
        animation: modalIn 0.25s cubic-bezier(.34,1.2,.64,1) both;
        overflow: hidden;
    }
    .modal-header {
        background: linear-gradient(120deg, #1e6b35 0%, #2d9148 60%, #37a85a 100%);
        padding: 16px 22px; display: flex; align-items: center; gap: 12px;
        flex-shrink: 0; position: relative;
    }
    .modal-header-text h2 { color: #fff; margin: 0; font-size: 1.1rem; font-weight: 700; text-shadow: none; text-align: left; }
    .modal-header-text p { color: rgba(255,255,255,0.82); margin: 1px 0 0; font-size: 0.78rem; }
    .close-modal {
        position: absolute; top: 16px; right: 18px;
        background: rgba(255,255,255,0.15); border: none; color: #fff;
        font-size: 20px; width: 32px; height: 32px; border-radius: 50%;
        cursor: pointer; display: flex; align-items: center; justify-content: center;
        transition: background 0.2s, transform 0.2s;
    }
    .close-modal:hover { background: rgba(255,255,255,0.28); transform: rotate(90deg); }
    .modal-body {
        padding: 16px 22px 12px; overflow-y: auto; flex: 1;
        scrollbar-width: thin; scrollbar-color: #2d9148 #f0f0f0;
    }
    .modal-body::-webkit-scrollbar { width: 6px; }
    .modal-body::-webkit-scrollbar-thumb { background: #2d9148; border-radius: 6px; }
    .section-label {
        font-size: 0.68rem; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.08em; color: #2d7d46; margin: 0 0 8px;
        display: flex; align-items: center; gap: 6px;
    }
    .section-label::after { content: ''; flex: 1; height: 1px; background: #d4edda; }
    .section-block { margin-bottom: 14px; }
    .form-grid   { display: grid; grid-template-columns: 1fr 1fr;     gap: 8px 12px; }
    .form-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px 12px; }
    .form-group  { display: flex; flex-direction: column; }
    .form-group.full-width { grid-column: 1 / -1; }
    .form-group label {
        color: #374151; font-weight: 600; font-size: 0.75rem;
        margin-bottom: 4px; display: flex; align-items: center; gap: 4px; text-shadow: none;
    }
    .required-star { color: #dc2626; }
    .form-group input, .form-group select {
        width: 100%; padding: 8px 10px; border: 1.5px solid #e5e7eb;
        border-radius: 7px; font-size: 0.83rem; background: #f9fafb;
        color: #111; font-family: inherit; box-sizing: border-box;
        transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
    }
    .form-group input:focus, .form-group select:focus {
        outline: none; border-color: #2d9148; background: #fff;
        box-shadow: 0 0 0 3px rgba(45,145,72,0.12);
    }
    .form-group input::placeholder { color: #b0b8c1; font-size: 0.8rem; }
    .password-wrapper { position: relative; }
    .password-toggle {
        position: absolute; right: 10px; top: 50%; transform: translateY(-50%);
        background: none; border: none; cursor: pointer; font-size: 1rem;
        color: #6b7280; padding: 4px; line-height: 1;
    }
    .password-toggle:hover { color: #2d9148; background: none; }
    .lot-section {
        background: #f0fdf4; border: 1.5px dashed #86efac;
        border-radius: 10px; padding: 12px 14px; margin-top: 4px;
    }
    .lot-item {
        background: #fff; border: 1.5px solid #e5e7eb; border-radius: 8px;
        padding: 10px 12px; margin-bottom: 8px; transition: box-shadow 0.2s;
    }
    .lot-item:hover { box-shadow: 0 2px 8px rgba(45,145,72,0.1); }
    .lot-item-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
    .lot-number-display { font-weight: 700; color: #2d7d46; font-size: 0.82rem; }
    .remove-lot-btn {
        background: #fef2f2; color: #dc2626; border: 1.5px solid #fecaca;
        padding: 3px 9px; border-radius: 6px; cursor: pointer; font-size: 0.75rem;
        font-weight: 600; transition: background 0.2s;
    }
    .remove-lot-btn:hover { background: #fee2e2; }
    .add-lot-btn {
        background: #f0fdf4; color: #166534; border: 1.5px solid #86efac;
        padding: 7px 14px; border-radius: 7px; cursor: pointer; font-size: 0.82rem;
        font-weight: 600; display: inline-flex; align-items: center; gap: 5px;
        margin-top: 4px; transition: background 0.2s;
    }
    .add-lot-btn:hover { background: #dcfce7; }
    .modal-footer {
        padding: 12px 22px; background: #f9fafb; border-top: 1px solid #e5e7eb;
        display: flex; gap: 10px; justify-content: flex-start;
        flex-shrink: 0; flex-wrap: wrap;
    }
    .btn {
        padding: 10px 24px; border: none; border-radius: 9px; font-size: 0.9rem;
        font-weight: 700; cursor: pointer; transition: all 0.2s;
        display: flex; align-items: center; gap: 7px; font-family: inherit;
    }
    .btn-primary {
        background: linear-gradient(135deg, #1e6b35, #2d9148);
        color: #fff; box-shadow: 0 2px 8px rgba(45,145,72,0.28);
    }
    .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 4px 14px rgba(45,145,72,0.38); }
    .btn-secondary {
        background: #fff; color: #6b7280; border: 1.5px solid #e5e7eb; margin-left: auto;
    }
    .btn-secondary:hover { background: #f3f4f6; border-color: #d1d5db; }
    .status-badge {
        display: inline-block; padding: 2px 8px; border-radius: 20px;
        font-size: 0.72rem; font-weight: 600; background: #dcfce7; color: #15803d; margin-left: 4px;
    }
    .info-box {
        background: #f0fdf4; border-left: 3px solid #22c55e; padding: 9px 12px;
        border-radius: 8px; margin-bottom: 14px; font-size: 0.8rem; color: #166534;
        display: flex; align-items: center; gap: 8px;
    }

    /* ── Field inline error hint ── */
    .field-hint {
        font-size: 0.72rem; margin-top: 3px; display: none;
        align-items: center; gap: 4px; font-weight: 500;
    }
    .field-hint.error   { color: #dc2626; display: flex; }
    .field-hint.success { color: #16a34a; display: flex; }

    /* ── DOB flatpickr inside modal ── */
    #dob_display {
        width: 100% !important; box-sizing: border-box !important;
        background: #f9fafb !important; color: #111 !important;
        border: 1.5px solid #e5e7eb !important; border-radius: 7px !important;
        padding: 8px 10px !important; font-size: 0.83rem !important;
        cursor: pointer !important;
    }
    #dob_display:focus { border-color: #2d9148 !important; box-shadow: 0 0 0 3px rgba(45,145,72,0.12) !important; }

    /* Confirmation mini-modal */
    #confirmModal { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:10000; justify-content:center; align-items:center; }

    @media(max-width:640px) {
        .form-grid, .form-grid-3 { grid-template-columns: 1fr; }
        .modal-body { padding: 16px; }
        .modal-footer { flex-direction: column-reverse; }
        .btn-secondary { margin-left: 0; }
        .btn { width: 100%; justify-content: center; }
    }

    /* ── Flatpickr month dropdown: compact scrollable list (3 visible) ── */
    .flatpickr-months .flatpickr-month { height: 38px; }
    .flatpickr-current-month .flatpickr-monthDropdown-months {
        /* Make it look like a normal select, not a huge list */
        appearance: auto !important;
        -webkit-appearance: auto !important;
        max-height: 80px !important; /* ~3 items */
        overflow-y: auto !important;
        font-size: 0.85rem !important;
        padding: 2px 4px !important;
        border: 1px solid #ccc !important;
        border-radius: 4px !important;
        background: #fff !important;
        cursor: pointer !important;
    }
    /* Make the month select render as a native scrollable <select> of small height */
    .flatpickr-monthDropdown-months {
        height: 22px !important;
        font-size: 0.85rem !important;
    }
    /* Flatpickr calendar: keep compact inside modal */
    .flatpickr-calendar { font-size: 13px !important; width: 280px !important; }
    .flatpickr-day { height: 32px !important; line-height: 32px !important; font-size: 12px !important; }
    .flatpickr-weekday { font-size: 11px !important; }
</style>
</head>
<body>
<div class="main-content">
    <div class="usernames">
        Welcome <?= htmlspecialchars($_SESSION['user_role']) ?>
        <a href="/agri_system/logout.php">Logout</a>
    </div>

    <h2>List of Farmers</h2>

    <?php if ($successMsg): ?>
        <div class="flash-msg flash-success">✅ <?= htmlspecialchars($successMsg) ?></div>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
        <div class="flash-msg flash-error">❌ <?= $errorMsg ?></div>
    <?php endif; ?>

    <form method="POST" action="" id="searchForm">
        <input type="hidden" name="field_changed" id="field_changed" value="0">
        <select name="search_field" id="search_field" onchange="handleFieldChange()">
            <option value="all"          <?= $searchField==='all'          ? 'selected':'' ?>>All</option>
            <option value="name"         <?= $searchField==='name'         ? 'selected':'' ?>>Name</option>
            <option value="municipality" <?= $searchField==='municipality' ? 'selected':'' ?>>Municipality</option>
            <option value="barangay"     <?= $searchField==='barangay'     ? 'selected':'' ?>>Barangay</option>
            <option value="status"       <?= $searchField==='status'       ? 'selected':'' ?>>Status</option>
            <option value="date"         <?= $searchField==='date'         ? 'selected':'' ?>>Date</option>
        </select>

        <input type="text" name="search_term"       id="search_term"       placeholder="Enter name..."        value="<?= htmlspecialchars($searchTerm) ?>"       style="display:none;">
        <input type="text" name="municipality_term" id="municipality_term" placeholder="Enter municipality..." value="<?= htmlspecialchars($municipalityTerm) ?>" style="display:none;">
        <input type="text" name="barangay_term"     id="barangay_term"     placeholder="Enter barangay..."     value="<?= htmlspecialchars($barangayTerm) ?>"     style="display:none;">

        <select name="search_status" id="search_status" style="display:none;">
            <option value="">Select Status</option>
            <option value="Active"   <?= $statusTerm==='Active'   ? 'selected':'' ?>>Active</option>
            <option value="Inactive" <?= $statusTerm==='Inactive' ? 'selected':'' ?>>Inactive</option>
        </select>

        <label for="from_date_display" id="from_label" style="display:none;">From</label>
        <input type="text"   id="from_date_display" placeholder="mm/dd/yyyy" readonly style="display:none; width:130px;">
        <input type="hidden" name="from_date" id="from_date" value="<?= htmlspecialchars($fromDate) ?>">

        <label for="to_date_display" id="to_label" style="display:none;">To</label>
        <input type="text"   id="to_date_display" placeholder="mm/dd/yyyy" readonly style="display:none; width:130px;">
        <input type="hidden" name="to_date" id="to_date" value="<?= htmlspecialchars($toDate) ?>">

        <button type="submit" id="searchBtn" style="display:none;">Search</button>
    </form>

    <div class="table-container" id="printSection">
        <table id="farmerTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Created At</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Address</th>
                    <th>Lots</th>
                    <th>Total Farm Size</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($result && $result->num_rows > 0):
                $no = $offset + 1;
                while ($row = $result->fetch_assoc()): ?>
                <tr data-id="<?= $row['id'] ?>">
                    <td><?= $no++ ?></td>
                    <td><?= htmlspecialchars($row['registered_at']) ?></td>
                    <td><?= htmlspecialchars($row['full_name']) ?></td>
                    <td><?= htmlspecialchars($row['email']) ?></td>
                    <td><?= htmlspecialchars($row['province'].', '.$row['municipality'].', '.$row['barangay']) ?></td>
                    <td><span class="badge"><?= $row['lot_count'] ?> lot(s)</span></td>
                    <td><?= number_format($row['total_farm_size'] ?? 0, 2) ?> ha</td>
                    <td><?= htmlspecialchars($row['status']) ?></td>
                </tr>
            <?php endwhile; else: ?>
                <tr>
                    <td colspan="8" style="text-align:center; padding:20px; color:#555;">
                        No records found<?= $searchTerm ? ' matching "'.htmlspecialchars($searchTerm).'"' : '' ?>.
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="view-container">
        <button id="addBtn">Add</button>
        <button id="viewBtn" disabled>View</button>
        <button id="editBtn" disabled>Edit</button>
        <button id="printBtn">Print</button>
    </div>
</div>

<!-- ══ ADD FARMER MODAL ══ -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-header-text">
                <h2>Add New Farmer</h2>
                <p>Register a new farmer account to the system</p>
            </div>
            <button class="close-modal" onclick="closeModal()" title="Close">&times;</button>
        </div>
        <div class="modal-body">
            <div class="info-box">
                ℹ️&nbsp; Fields marked <strong style="color:#dc2626">&thinsp;*</strong> are required.
            </div>
            <form action="addstaff_farmers_process.php" method="POST" id="addFarmerForm">

                <div class="section-block">
                    <p class="section-label">👤 Personal Information</p>
                    <div class="form-grid-3">
                        <div class="form-group">
                            <label>First Name <span class="required-star">*</span></label>
                            <input type="text" name="first_name" placeholder="First name" required>
                        </div>
                        <div class="form-group">
                            <label>Middle Name</label>
                            <input type="text" name="middle_name" placeholder="Optional">
                        </div>
                        <div class="form-group">
                            <label>Last Name <span class="required-star">*</span></label>
                            <input type="text" name="last_name" placeholder="Last name" required>
                        </div>
                    </div>
                </div>

                <div class="section-block">
                    <div class="form-grid-3">
                        <div class="form-group">
                            <label>Sex <span class="required-star">*</span></label>
                            <select name="sex" required>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>

                        <!-- ── CHANGED: Calendar DOB via flatpickr ── -->
                        <div class="form-group">
                            <label>Date of Birth <span class="required-star">*</span> <small style="color:#9ca3af;font-weight:400">(click to pick)</small></label>
                            <!-- visible calendar picker -->
                            <input type="text" id="dob_display" placeholder="mm/dd/yyyy" readonly>
                            <!-- hidden field submitted to server -->
                            <input type="hidden" name="date_of_birth" id="dob_input">
                            <span class="field-hint" id="dob_hint"></span>
                        </div>

                        <div class="form-group">
                            <label>Age</label>
                            <input type="number" name="age" id="age_input" placeholder="Auto" min="1" max="120" readonly
                                   style="background:#f0fdf4; color:#166534; cursor:default;">
                        </div>
                    </div>
                </div>

                <div class="section-block">
                    <div class="form-grid">

                        <!-- ── CHANGED: Email with live validation ── -->
                        <div class="form-group">
                            <label>Email <span class="required-star">*</span></label>
                            <input type="email" name="email" id="add_email"
                                   placeholder="example@gmail.com" required
                                   oninput="validateEmail(this)" onblur="validateEmail(this)">
                            <span class="field-hint" id="email_hint"></span>
                        </div>

                        <!-- ── CHANGED: Phone with PH format validation ── -->
                        <div class="form-group">
                            <label>Phone <span class="required-star">*</span>
                                <small style="color:#9ca3af;font-weight:400">(09XXXXXXXXX)</small>
                            </label>
                            <input type="tel" name="phone" id="add_phone"
                                   placeholder="09XXXXXXXXX" maxlength="11" required
                                   oninput="validatePhone(this)" onblur="validatePhone(this)">
                            <span class="field-hint" id="phone_hint"></span>
                        </div>

                    </div>
                </div>

                <div class="section-block">
                    <p class="section-label">📍 Address</p>
                    <div class="form-grid-3">
                        <div class="form-group">
                            <label>Province <span class="required-star">*</span></label>
                            <input type="text" name="province" value="Zamboanga Del Sur" readonly
                                   style="background:#f0fdf4; color:#166534; cursor:default; border-color:#86efac;">
                        </div>
                        <div class="form-group">
                            <label>Municipality <span class="required-star">*</span></label>
                            <input type="text" name="municipality" placeholder="Municipality" required>
                        </div>
                        <div class="form-group">
                            <label>Barangay <span class="required-star">*</span></label>
                            <input type="text" name="barangay" placeholder="Barangay" required>
                        </div>
                    </div>
                </div>

                <!-- Password – hidden, auto-set to 123456, not shown to staff -->
                <input type="hidden" id="password"         name="password"         value="123456">
                <input type="hidden" id="confirm_password" name="confirm_password" value="123456">

                <div class="section-block">
                    <p class="section-label">🌾 Farm Lots</p>
                    <div class="lot-section">
                        <div id="lotsContainer"></div>
                        <button type="button" class="add-lot-btn" onclick="addLot()">＋ Add Another Lot</button>
                    </div>
                </div>

            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-primary" onclick="confirmAddFarmer()">Add Farmer</button>
            <button type="button" class="btn btn-secondary" onclick="confirmCancel()">Cancel</button>
        </div>
    </div>
</div>

<!-- ══ CONFIRMATION MODAL ══ -->
<div id="confirmModal">
    <div style="background:white; border-radius:14px; width:100%; max-width:360px; box-shadow:0 20px 50px rgba(0,0,0,0.3); overflow:hidden; animation:modalIn 0.2s ease both;">
        <div style="background:linear-gradient(135deg,#1e6b35,#2d9148); padding:18px 22px;">
            <div style="color:white; font-size:1rem; font-weight:700;" id="confirmTitle">Confirm Action</div>
            <div style="color:rgba(255,255,255,0.8); font-size:0.8rem; margin-top:2px;" id="confirmSubtitle"></div>
        </div>
        <div style="padding:20px 22px;">
            <p id="confirmMessage" style="color:#374151; font-size:0.9rem; margin-bottom:20px; line-height:1.5;"></p>
            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button onclick="confirmYes()" style="padding:9px 20px; background:linear-gradient(135deg,#1e6b35,#2d9148); color:white; border:none; border-radius:8px; font-size:0.88rem; font-weight:700; cursor:pointer;">Yes</button>
                <button onclick="confirmNo()"  style="padding:9px 20px; background:#f5f5f5; color:#555; border:1px solid #ddd; border-radius:8px; font-size:0.88rem; font-weight:600; cursor:pointer;">No</button>
            </div>
        </div>
    </div>
</div>

<!-- ══ VALIDATION ERROR MODAL ══ -->
<div id="validationModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); backdrop-filter:blur(6px); justify-content:center; align-items:center; z-index:10001; padding:16px;">
    <div style="background:#fff; border-radius:16px; width:100%; max-width:380px; box-shadow:0 20px 50px rgba(0,0,0,0.3); overflow:hidden; animation:modalIn 0.25s cubic-bezier(.34,1.2,.64,1) both;">
        <div style="background:linear-gradient(135deg,#b91c1c,#dc2626); padding:16px 22px; display:flex; align-items:center; justify-content:space-between;">
            <div>
                <div style="color:#fff; font-size:1rem; font-weight:700;" id="valModalTitle">Required Fields Missing</div>
                <div style="color:rgba(255,255,255,0.8); font-size:0.78rem; margin-top:2px;" id="valModalSubtitle">Please complete the form</div>
            </div>
            <button onclick="closeValidationModal()" style="background:rgba(255,255,255,0.15); border:none; color:#fff; font-size:20px; width:32px; height:32px; border-radius:50%; cursor:pointer; display:flex; align-items:center; justify-content:center;">&times;</button>
        </div>
        <div style="padding:20px 22px;">
            <div style="display:flex; align-items:flex-start; gap:12px; margin-bottom:20px;">
                <div style="font-size:2rem; line-height:1;">⚠️</div>
                <p id="valModalMessage" style="color:#374151; font-size:0.9rem; margin:0; line-height:1.5;"></p>
            </div>
            <div style="display:flex; justify-content:flex-end;">
                <button onclick="closeValidationModal()" style="padding:9px 24px; background:linear-gradient(135deg,#b91c1c,#dc2626); color:white; border:none; border-radius:8px; font-size:0.88rem; font-weight:700; cursor:pointer;">OK</button>
            </div>
        </div>
    </div>
</div>

<!-- ══ EDIT FARMER MODAL ══ -->
<div id="editModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); backdrop-filter:blur(6px); justify-content:center; align-items:center; z-index:9999; padding:16px;">
    <div style="background:#fff; border-radius:16px; width:100%; max-width:680px; max-height:90vh; display:flex; flex-direction:column; box-shadow:0 20px 50px rgba(0,0,0,0.22); animation:modalIn 0.25s cubic-bezier(.34,1.2,.64,1) both; overflow:hidden;">
        <div style="background:linear-gradient(120deg,#1e6b35 0%,#2d9148 60%,#37a85a 100%); padding:16px 22px; display:flex; align-items:center; justify-content:space-between; flex-shrink:0;">
            <div>
                <div style="color:#fff; font-size:1.05rem; font-weight:700;">✎ Edit Farmer Profile</div>
                <div style="color:rgba(255,255,255,0.8); font-size:0.78rem; margin-top:2px;">Only editable fields are active</div>
            </div>
            <button onclick="closeEditModal()" style="background:rgba(255,255,255,0.15); border:none; color:#fff; font-size:20px; width:32px; height:32px; border-radius:50%; cursor:pointer; display:flex; align-items:center; justify-content:center;">&times;</button>
        </div>
        <div style="padding:20px 22px; overflow-y:auto; flex:1;">
            <form action="edit_farmer_action.php" method="POST" id="editFarmerForm">
                <input type="hidden" name="id" id="editFarmerId">
                <div style="font-size:0.68rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:#2d7d46;margin:0 0 8px;display:flex;align-items:center;gap:6px;width:100%;">
                    Personal Information <span style="font-weight:400;color:#9ca3af;text-transform:none;">(read-only)</span>
                    <span style="flex:1;height:1px;background:#d4edda;display:block;"></span>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px 12px;margin-bottom:14px;">
                    <div class="form-group">
                        <label style="color:#374151;font-weight:600;font-size:0.75rem;margin-bottom:4px;">First Name</label>
                        <input type="text" id="editFirstName" name="first_name" readonly style="padding:8px 10px;border:1.5px solid #e5e7eb;border-radius:7px;font-size:0.83rem;background:#f3f4f6;color:#9ca3af;cursor:not-allowed;width:100%;box-sizing:border-box;">
                    </div>
                    <div class="form-group">
                        <label style="color:#374151;font-weight:600;font-size:0.75rem;margin-bottom:4px;">Middle Name</label>
                        <input type="text" id="editMiddleName" name="middle_name" readonly style="padding:8px 10px;border:1.5px solid #e5e7eb;border-radius:7px;font-size:0.83rem;background:#f3f4f6;color:#9ca3af;cursor:not-allowed;width:100%;box-sizing:border-box;">
                    </div>
                    <div class="form-group">
                        <label style="color:#374151;font-weight:600;font-size:0.75rem;margin-bottom:4px;">Last Name</label>
                        <input type="text" id="editLastName" name="last_name" readonly style="padding:8px 10px;border:1.5px solid #e5e7eb;border-radius:7px;font-size:0.83rem;background:#f3f4f6;color:#9ca3af;cursor:not-allowed;width:100%;box-sizing:border-box;">
                    </div>
                    <div class="form-group">
                        <label style="color:#374151;font-weight:600;font-size:0.75rem;margin-bottom:4px;">Sex <span style="font-weight:400;color:#9ca3af;">(read-only)</span></label>
                        <input type="text" id="editSexDisplay" name="sex" readonly style="padding:8px 10px;border:1.5px solid #e5e7eb;border-radius:7px;font-size:0.83rem;background:#f3f4f6;color:#9ca3af;cursor:not-allowed;width:100%;box-sizing:border-box;">
                    </div>
                    <div class="form-group">
                        <label style="color:#374151;font-weight:600;font-size:0.75rem;margin-bottom:4px;">Date of Birth <span style="font-weight:400;color:#9ca3af;">(read-only)</span></label>
                        <input type="text" id="editDobDisplay" name="date_of_birth" readonly style="padding:8px 10px;border:1.5px solid #e5e7eb;border-radius:7px;font-size:0.83rem;background:#f3f4f6;color:#9ca3af;cursor:not-allowed;width:100%;box-sizing:border-box;">
                    </div>
                    <div class="form-group">
                        <label style="color:#374151;font-weight:600;font-size:0.75rem;margin-bottom:4px;">Age <span style="font-weight:400;color:#9ca3af;">(read-only)</span></label>
                        <input type="text" id="editAge" readonly style="padding:8px 10px;border:1.5px solid #e5e7eb;border-radius:7px;font-size:0.83rem;background:#f3f4f6;color:#9ca3af;cursor:not-allowed;width:100%;box-sizing:border-box;">
                    </div>
                </div>
                <div style="font-size:0.68rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:#2d7d46;margin:0 0 8px;display:flex;align-items:center;gap:6px;width:100%;">
                    Contact
                    <span style="flex:1;height:1px;background:#d4edda;display:block;"></span>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px 12px;margin-bottom:14px;">
                    <div class="form-group">
                        <label style="color:#374151;font-weight:600;font-size:0.75rem;margin-bottom:4px;">Email <span style="color:#dc2626;">*</span></label>
                        <input type="email" id="editEmail" name="email" required placeholder="Email address" style="padding:8px 10px;border:1.5px solid #e5e7eb;border-radius:7px;font-size:0.83rem;background:#f9fafb;color:#111;width:100%;box-sizing:border-box;outline:none;transition:border-color 0.2s;">
                    </div>
                    <div class="form-group">
                        <label style="color:#374151;font-weight:600;font-size:0.75rem;margin-bottom:4px;">Phone <span style="color:#dc2626;">*</span></label>
                        <input type="text" id="editPhone" name="phone" placeholder="09XX-XXX-XXXX" style="padding:8px 10px;border:1.5px solid #e5e7eb;border-radius:7px;font-size:0.83rem;background:#f9fafb;color:#111;width:100%;box-sizing:border-box;outline:none;transition:border-color 0.2s;">
                    </div>
                </div>
                <div style="font-size:0.68rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:#2d7d46;margin:0 0 8px;display:flex;align-items:center;gap:6px;width:100%;">
                    Location &amp; Account
                    <span style="flex:1;height:1px;background:#d4edda;display:block;"></span>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:8px 12px;margin-bottom:10px;">
                    <div class="form-group">
                        <label style="color:#374151;font-weight:600;font-size:0.75rem;margin-bottom:4px;">Province <span style="font-weight:400;color:#9ca3af;">(read-only)</span></label>
                        <input type="text" id="editProvince" name="province" readonly style="padding:8px 10px;border:1.5px solid #e5e7eb;border-radius:7px;font-size:0.83rem;background:#f3f4f6;color:#9ca3af;cursor:not-allowed;width:100%;box-sizing:border-box;">
                    </div>
                    <div class="form-group">
                        <label style="color:#374151;font-weight:600;font-size:0.75rem;margin-bottom:4px;">Municipality <span style="color:#dc2626;">*</span></label>
                        <input type="text" id="editMunicipality" name="municipality" required placeholder="Municipality" style="padding:8px 10px;border:1.5px solid #e5e7eb;border-radius:7px;font-size:0.83rem;background:#f9fafb;color:#111;width:100%;box-sizing:border-box;outline:none;">
                    </div>
                    <div class="form-group">
                        <label style="color:#374151;font-weight:600;font-size:0.75rem;margin-bottom:4px;">Barangay <span style="color:#dc2626;">*</span></label>
                        <input type="text" id="editBarangay" name="barangay" required placeholder="Barangay" style="padding:8px 10px;border:1.5px solid #e5e7eb;border-radius:7px;font-size:0.83rem;background:#f9fafb;color:#111;width:100%;box-sizing:border-box;outline:none;">
                    </div>
                    <div class="form-group">
                        <label style="color:#374151;font-weight:600;font-size:0.75rem;margin-bottom:4px;">Status</label>
                        <select id="editStatus" name="status" onchange="toggleEditInactiveReason()" style="padding:8px 10px;border:1.5px solid #e5e7eb;border-radius:7px;font-size:0.83rem;background:#f9fafb;color:#111;width:100%;box-sizing:border-box;outline:none;">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div id="editInactiveReasonBox" style="display:none; margin-top:6px; width:100%;">
                    <div style="font-size:0.68rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:#2d7d46;margin:0 0 6px;display:flex;align-items:center;gap:6px;width:100%;">
                        Reason for Inactivation
                        <span style="flex:1;height:1px;background:#d4edda;display:block;"></span>
                    </div>
                    <textarea id="editInactiveReason" name="inactive_reason" rows="6"
                        placeholder="Please provide the reason for setting this farmer as Inactive..."
                        style="padding:9px 12px;border:1.5px solid #fde68a;border-radius:8px;font-size:0.85rem;outline:none;font-family:inherit;background:#fffbeb;color:#111;resize:vertical;width:100%;box-sizing:border-box;min-height:130px;display:block;"></textarea>
                </div>
            </form>
        </div>
        <div style="padding:12px 22px;background:#f9fafb;border-top:1px solid #e5e7eb;display:flex;gap:10px;justify-content:flex-end;flex-shrink:0;">
            <button type="button" onclick="closeEditModal()" style="padding:9px 18px;background:#f5f5f5;color:#666;border:1px solid #e0e0e0;border-radius:8px;font-size:0.88rem;font-weight:600;cursor:pointer;">Cancel</button>
            <button type="submit" form="editFarmerForm" style="padding:9px 22px;background:#2d7d46;color:white;border:none;border-radius:8px;font-size:0.88rem;font-weight:700;cursor:pointer;">Save Changes</button>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.js"></script>
<script>
// ── Table row selection ───────────────────────────────────────────────────────
let selectedRowId = null;

document.querySelectorAll('#farmerTable tbody tr[data-id]').forEach(row => {
    row.addEventListener('click', () => {
        document.querySelectorAll('#farmerTable tbody tr').forEach(r => r.classList.remove('selected'));
        row.classList.add('selected');
        selectedRowId = row.getAttribute('data-id');
        document.getElementById('viewBtn').disabled = false;
        document.getElementById('editBtn').disabled = false;
    });
    row.addEventListener('dblclick', () => {
        const id = row.getAttribute('data-id');
        if (id) window.location.href = 'staff_farmers_profile_updated.php?id=' + id;
    });
});

document.getElementById('viewBtn').addEventListener('click', () => {
    if (selectedRowId) window.location.href = 'staff_farmers_profile_updated.php?id=' + selectedRowId;
});

document.getElementById('editBtn').addEventListener('click', () => {
    if (!selectedRowId) return;
    fetch('get_farmer_edit.php?id=' + selectedRowId)
        .then(r => r.json())
        .then(data => {
            if (!data.success) { alert('Failed to load farmer data.'); return; }
            const f = data.farmer;
            document.getElementById('editFarmerId').value       = f.id;
            document.getElementById('editFirstName').value      = f.first_name   || '';
            document.getElementById('editMiddleName').value     = f.middle_name  || '';
            document.getElementById('editLastName').value       = f.last_name    || '';
            document.getElementById('editSexDisplay').value     = f.sex          || '';
            document.getElementById('editDobDisplay').value     = f.date_of_birth || '';
            document.getElementById('editAge').value            = f.age ? f.age + ' yrs' : '';
            document.getElementById('editEmail').value          = f.email        || '';
            document.getElementById('editPhone').value          = f.phone        || '';
            document.getElementById('editProvince').value       = f.province     || '';
            document.getElementById('editMunicipality').value   = f.municipality || '';
            document.getElementById('editBarangay').value       = f.barangay     || '';
            document.getElementById('editStatus').value         = f.status       || 'Active';
            document.getElementById('editInactiveReason').value = f.inactive_reason || '';
            toggleEditInactiveReason();
            document.getElementById('editModal').style.display = 'flex';
        })
        .catch(() => alert('Error loading farmer data.'));
});

document.getElementById('addBtn').addEventListener('click', () => {
    document.getElementById('addModal').style.display = 'flex';
});

// ── Print ─────────────────────────────────────────────────────────────────────
document.getElementById('printBtn').addEventListener('click', () => {
    const headers = [...document.querySelectorAll('#farmerTable thead th')].map(th => th.innerText.trim());
    const rows    = [];
    document.querySelectorAll('#farmerTable tbody tr[data-id]').forEach(tr => {
        rows.push([...tr.querySelectorAll('td')].map(td => td.innerText.trim()));
    });
    let tbl = '<table><thead><tr>' + headers.map(h=>`<th>${h}</th>`).join('') + '</tr></thead><tbody>';
    rows.forEach(r => { tbl += '<tr>' + r.map(c=>`<td>${c}</td>`).join('') + '</tr>'; });
    tbl += '</tbody></table>';
    const now = new Date().toLocaleDateString('en-US',{year:'numeric',month:'2-digit',day:'2-digit'});
    const win = window.open('','','height=700,width=1000');
    win.document.write(`<html><head><title>Farmer List</title><style>
        body{font-family:Arial,sans-serif;margin:30px}
        h2{text-align:center;color:#2d7a2d}
        p.meta{text-align:right;color:#555;font-size:12px;margin-bottom:16px}
        table{width:100%;border-collapse:collapse}
        th{background:#2d7a2d;color:#fff;padding:10px 12px;text-align:left;font-size:13px;border:1px solid #2d7a2d;-webkit-print-color-adjust:exact;print-color-adjust:exact}
        td{padding:9px 12px;border:1px solid #ddd;font-size:13px;color:#000}
        tbody tr:nth-child(even){background:#f9f9f9}
        @media print{th{background:#2d7a2d!important;color:#fff!important}}
    </style></head><body>
        <h2>List of Farmers</h2>
        <p class="meta">Printed on: ${now}</p>${tbl}
    </body></html>`);
    win.document.close();
    setTimeout(()=>win.print(), 400);
});

// ── Search form ───────────────────────────────────────────────────────────────
function handleFieldChange() {
    ['search_term','municipality_term','barangay_term'].forEach(id => document.getElementById(id).value='');
    document.getElementById('search_status').value='';
    document.getElementById('from_date').value='';
    document.getElementById('to_date').value='';
    if(window.fpFrom) fpFrom.clear();
    if(window.fpTo)   fpTo.clear();
    document.getElementById('field_changed').value='1';
    document.getElementById('searchForm').submit();
}

function toggleInputs() {
    const field        = document.getElementById('search_field').value;
    const isStatus     = field === 'status';
    const isRegistered = field === 'registered';
    const isDate       = field === 'date';
    const isAll        = field === 'all';

    document.getElementById('searchBtn').style.display         = isAll ? 'none' : 'inline-block';
    document.getElementById('search_term').style.display       = (field==='barangay'||isStatus||isAll||isDate) ? 'none' : 'inline-block';
    document.getElementById('municipality_term').style.display = field==='barangay' ? 'inline-block' : 'none';
    document.getElementById('barangay_term').style.display     = field==='barangay' ? 'inline-block' : 'none';
    document.getElementById('search_status').style.display     = isStatus ? 'inline-block' : 'none';
    document.getElementById('from_date_display').style.display = (isStatus||isRegistered||isDate) ? 'inline-block' : 'none';
    document.getElementById('to_date_display').style.display   = (isStatus||isRegistered||isDate) ? 'inline-block' : 'none';
    document.getElementById('from_label').style.display        = (isStatus||isRegistered||isDate) ? 'inline-block' : 'none';
    document.getElementById('to_label').style.display          = (isStatus||isRegistered||isDate) ? 'inline-block' : 'none';
    const inp = document.getElementById('search_term');
    inp.placeholder = field==='name' ? 'Enter name...' : field==='municipality' ? 'Enter municipality...' : 'Enter search...';
}
window.onload = toggleInputs;

// ── Flatpickr – search filters ────────────────────────────────────────────────
window.fpFrom = flatpickr('#from_date_display', {
    dateFormat:'m/d/Y', allowInput:false,
    onChange(dates) { document.getElementById('from_date').value = dates.length ? dates[0].toISOString().slice(0,10) : ''; }
});
window.fpTo = flatpickr('#to_date_display', {
    dateFormat:'m/d/Y', allowInput:false,
    onChange(dates) { document.getElementById('to_date').value = dates.length ? dates[0].toISOString().slice(0,10) : ''; }
});
<?php if ($fromDate): ?>window.fpFrom.setDate('<?= htmlspecialchars($fromDate) ?>', true, 'Y-m-d');<?php endif; ?>
<?php if ($toDate):   ?>window.fpTo.setDate('<?= htmlspecialchars($toDate) ?>', true, 'Y-m-d');<?php endif; ?>

// ── CHANGED: Flatpickr – DOB calendar in Add Farmer modal ────────────────────
// Initialized lazily when the modal opens so the element is in the DOM
let fpDob = null;
function initDobPicker() {
    if (fpDob) return; // already initialized
    fpDob = flatpickr('#dob_display', {
        dateFormat: 'm/d/Y',        // display format
        altInput: false,
        maxDate: 'today',           // can't pick future dates
        allowInput: false,
        disableMobile: true,        // always use flatpickr calendar
        onChange(selectedDates) {
            if (!selectedDates.length) {
                document.getElementById('dob_input').value = '';
                document.getElementById('age_input').value = '';
                setHint('dob_hint', '', '');
                return;
            }
            const d = selectedDates[0];
            // Store in Y-m-d for the server
            const yyyy = d.getFullYear();
            const mm   = String(d.getMonth() + 1).padStart(2, '0');
            const dd   = String(d.getDate()).padStart(2, '0');
            document.getElementById('dob_input').value = `${yyyy}-${mm}-${dd}`;

            // Compute age
            const today = new Date();
            let age = today.getFullYear() - yyyy;
            const mDiff = today.getMonth() - d.getMonth();
            if (mDiff < 0 || (mDiff === 0 && today.getDate() < d.getDate())) age--;
            document.getElementById('age_input').value = age >= 0 ? age : '';
            setHint('dob_hint', 'success', `Age computed: ${age} yrs`);
        }
    });
}

document.getElementById('addBtn').addEventListener('click', () => {
    document.getElementById('addModal').style.display = 'flex';
    // Small delay to let the modal render before initializing flatpickr
    setTimeout(initDobPicker, 80);
});

// ── CHANGED: Email live validation ────────────────────────────────────────────
function validateEmail(input) {
    const val   = input.value.trim();
    const hint  = document.getElementById('email_hint');
    // RFC-ish check + must have a dot in domain part
    const regex = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

    if (val === '') {
        resetHint(hint, input);
        return true;
    }
    if (!regex.test(val)) {
        setInputError(input);
        hint.className = 'field-hint error';
        hint.textContent = '⚠ Enter a valid email address (e.g. juan@gmail.com)';
        return false;
    }
    // Extra friendly warning for obviously wrong domains
    const domain = val.split('@')[1].toLowerCase();
    if (!domain.includes('.')) {
        setInputError(input);
        hint.className = 'field-hint error';
        hint.textContent = '⚠ Email domain looks incomplete (e.g. @gmail.com)';
        return false;
    }
    setInputOk(input);
    hint.className = 'field-hint success';
    hint.textContent = '✓ Email looks good';
    return true;
}

// ── CHANGED: Philippine phone validation ──────────────────────────────────────
// Rules: exactly 11 digits, starts with 09
function validatePhone(input) {
    // Strip non-digits while typing
    let digits = input.value.replace(/\D/g, '').substring(0, 11);
    input.value = digits;

    const hint = document.getElementById('phone_hint');

    if (digits === '') {
        resetHint(hint, input);
        return true;
    }
    if (!digits.startsWith('09')) {
        setInputError(input);
        hint.className = 'field-hint error';
        hint.textContent = '⚠ Number must start with 09';
        return false;
    }
    if (digits.length < 11) {
        setInputError(input);
        hint.className = 'field-hint error';
        hint.textContent = `⚠ Must be 11 digits — ${11 - digits.length} more needed`;
        return false;
    }
    // Exactly 11 digits starting with 09 ✓
    setInputOk(input);
    hint.className = 'field-hint success';
    hint.textContent = '✓ Valid Philippine number';
    return true;
}

// ── Hint helpers ──────────────────────────────────────────────────────────────
function setHint(id, type, msg) {
    const el = document.getElementById(id);
    if (!el) return;
    el.className = type ? `field-hint ${type}` : 'field-hint';
    el.textContent = msg;
}
function setInputError(input) {
    input.style.borderColor = '#dc2626';
    input.style.background  = '#fef2f2';
}
function setInputOk(input) {
    input.style.borderColor = '#16a34a';
    input.style.background  = '#f0fdf4';
}
function resetHint(hint, input) {
    if (hint) { hint.className = 'field-hint'; hint.textContent = ''; }
    if (input) { input.style.borderColor = '#e5e7eb'; input.style.background = '#f9fafb'; }
}

// ── Lot management ────────────────────────────────────────────────────────────
let lotCounter = 0;
window.addEventListener('DOMContentLoaded', addLot);

function addLot() {
    lotCounter++;
    const wrap = document.createElement('div');
    wrap.className = 'lot-item';
    wrap.id = `lot-${lotCounter}`;
    const lc = lotCounter;
    wrap.innerHTML = `
        <div class="lot-item-header">
            <span class="lot-number-display">Lot #${lc}</span>
            ${lc > 1 ? `<button type="button" class="remove-lot-btn" onclick="removeLot(${lc})">✕ Remove</button>` : ''}
        </div>
        <div class="form-grid-3" style="margin-bottom:8px;">
            <div class="form-group">
                <label>Lot Number <span class="required-star">*</span></label>
                <input type="text" name="lots[${lc}][lot_number]" placeholder="e.g., LOT-001" required>
            </div>
            <div class="form-group">
                <label>Farm Size (ha) <span class="required-star">*</span></label>
                <input type="number" step="0.01" min="0.01" name="lots[${lc}][farm_size]" placeholder="0.00" required>
            </div>
            <div class="form-group">
                <label>Province</label>
                <input type="text" name="lots[${lc}][province]" value="Zamboanga Del Sur" readonly
                       style="background:#f0fdf4;color:#166534;cursor:default;border-color:#86efac;">
            </div>
        </div>
        <div class="form-grid-3">
            <div class="form-group">
                <label>Municipality</label>
                <input type="text" name="lots[${lc}][municipality]" placeholder="Municipality">
            </div>
            <div class="form-group">
                <label>Barangay</label>
                <input type="text" name="lots[${lc}][barangay]" placeholder="Barangay">
            </div>
            <div class="form-group">
                <label>Farm Location <span class="required-star">*</span></label>
                <input type="text" name="lots[${lc}][farm_location]" placeholder="Specific location" required>
            </div>
        </div>`;
    document.getElementById('lotsContainer').appendChild(wrap);
}

function removeLot(id) {
    const el = document.getElementById(`lot-${id}`);
    if (el) el.remove();
}

// ── Confirmation modal ────────────────────────────────────────────────────────
let confirmCallback = null;

function showConfirm(title, subtitle, message, onYes) {
    document.getElementById('confirmTitle').textContent    = title;
    document.getElementById('confirmSubtitle').textContent = subtitle;
    document.getElementById('confirmMessage').textContent  = message;
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

// ── Validation modal ──────────────────────────────────────────────────────────
function showValidationModal(title, subtitle, message) {
    document.getElementById('valModalTitle').textContent    = title;
    document.getElementById('valModalSubtitle').textContent = subtitle;
    document.getElementById('valModalMessage').textContent  = message;
    document.getElementById('validationModal').style.display = 'flex';
}
function showValidationError() {
    showValidationModal(
        'Required Fields Missing',
        'Please complete the form before submitting',
        'Please fill in all required fields. Fields highlighted in red must be completed before adding the farmer.'
    );
}
function showPasswordError() {
    showValidationModal(
        'Password Mismatch',
        'Passwords do not match',
        'The password and confirm password fields do not match. Please make sure both passwords are identical.'
    );
}
function closeValidationModal() {
    document.getElementById('validationModal').style.display = 'none';
}

// ── CHANGED: Add Farmer → confirm with email + phone guards ──────────────────
function confirmAddFarmer() {
    const form = document.getElementById('addFarmerForm');
    let valid  = true;
    let firstError = null;

    // Check standard required fields
    form.querySelectorAll('input[required], select[required]').forEach(el => {
        if (!el.value.trim()) {
            el.style.borderColor = '#d32f2f';
            el.style.background  = '#fef2f2';
            valid = false;
            if (!firstError) firstError = el;
        } else {
            // don't reset greenlit fields
            if (el.style.borderColor !== 'rgb(22, 163, 74)') {
                el.style.borderColor = '#e5e7eb';
                el.style.background  = '#f9fafb';
            }
        }
    });

    // Also validate email format
    const emailOk = validateEmail(document.getElementById('add_email'));
    if (!emailOk) valid = false;

    // Also validate phone format
    const phoneOk = validatePhone(document.getElementById('add_phone'));
    if (!phoneOk) valid = false;

    // DOB hidden field check
    const dobVal = document.getElementById('dob_input').value;
    const dobDisplay = document.getElementById('dob_display');
    if (!dobVal) {
        dobDisplay.style.borderColor = '#d32f2f';
        dobDisplay.style.background  = '#fef2f2';
        setHint('dob_hint', 'error', '⚠ Please select a date of birth');
        valid = false;
    }

    if (!valid) { showValidationError(); return; }

    showConfirm(
        'Add Farmer',
        'Please confirm your action',
        'Are you sure you want to add this farmer to the system?',
        () => form.submit()
    );
}

// ── Cancel → confirm ──────────────────────────────────────────────────────────
function confirmCancel() {
    showConfirm(
        'Cancel Registration',
        'Unsaved data will be lost',
        'Are you sure you want to cancel? All entered information will be discarded.',
        () => closeModal()
    );
}

// ── Close Add modal ───────────────────────────────────────────────────────────
function closeModal() {
    document.getElementById('addModal').style.display = 'none';
    document.getElementById('addFarmerForm').reset();
    document.getElementById('lotsContainer').innerHTML = '';
    lotCounter = 0;
    addLot();
    // Reset DOB picker
    if (fpDob) { fpDob.clear(); }
    document.getElementById('dob_input').value = '';
    document.getElementById('age_input').value = '';
    // Reset hint spans
    ['email_hint','phone_hint','dob_hint'].forEach(id => setHint(id,'',''));
}

// ── Close Edit modal ──────────────────────────────────────────────────────────
function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

// ── Edit inactive reason toggle ───────────────────────────────────────────────
function toggleEditInactiveReason() {
    const sel = document.getElementById('editStatus');
    const box = document.getElementById('editInactiveReasonBox');
    const txt = document.getElementById('editInactiveReason');
    if (sel.value === 'Inactive') {
        box.style.display = 'block';
        txt.required = true;
    } else {
        box.style.display = 'none';
        txt.required = false;
        txt.value = '';
    }
}

function togglePass(inputId, iconId) {
    const inp  = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    if (inp.type === 'password') { inp.type = 'text';     icon.textContent = '🙈'; }
    else                         { inp.type = 'password'; icon.textContent = '👁'; }
}

// ── Backdrop clicks ───────────────────────────────────────────────────────────
window.addEventListener('click', e => {
    if (e.target === document.getElementById('addModal'))        confirmCancel();
    if (e.target === document.getElementById('validationModal')) closeValidationModal();
    if (e.target === document.getElementById('editModal'))       closeEditModal();
});

// ── Escape key ────────────────────────────────────────────────────────────────
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        if (document.getElementById('confirmModal').style.display === 'flex') {
            confirmNo();
        } else if (document.getElementById('addModal').style.display === 'flex') {
            confirmCancel();
        } else if (document.getElementById('editModal').style.display === 'flex') {
            closeEditModal();
        }
    }
});
</script>

</body>
</html>