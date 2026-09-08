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

$searchField = $_POST['search_field'] ?? $_GET['search_field'] ?? 'all';
$searchTerm  = $_POST['search_term']  ?? $_GET['search_term']  ?? '';
$statusTerm  = $_POST['search_status'] ?? $_GET['search_status'] ?? '';
$fromDate    = $_POST['from_date']     ?? $_GET['from_date']     ?? '';
$toDate      = $_POST['to_date']       ?? $_GET['to_date']       ?? '';
$where       = '';

$searchTerm  = trim($searchTerm);
$statusTerm  = trim($statusTerm);
$fromDate    = trim($fromDate);
$toDate      = trim($toDate);

$conditions = [];

if ($searchField !== 'all') {
    if ($searchField === 'name' && $searchTerm !== '') {
        $safe = $conn->real_escape_string($searchTerm);
        $conditions[] = "CONCAT(f.first_name, ' ', COALESCE(f.middle_name, ''), ' ', f.last_name) LIKE '%$safe%'";
    } elseif ($searchField === 'address' && $searchTerm !== '') {
        $parts = array_map('trim', explode(',', $searchTerm));
        if (count($parts) > 1 && !empty($parts[1])) {
            $safeBrgy = $conn->real_escape_string($parts[0]);
            $safeMuni = $conn->real_escape_string($parts[1]);
            $conditions[] = "f.barangay LIKE '%$safeBrgy%' AND f.municipality LIKE '%$safeMuni%'";
        } else {
            $safe = $conn->real_escape_string($searchTerm);
            $conditions[] = "(f.barangay LIKE '%$safe%' OR f.municipality LIKE '%$safe%')";
        }
    } elseif ($searchField === 'status') {
        if ($statusTerm !== '') {
            $safeStatus = $conn->real_escape_string($statusTerm);
            $conditions[] = "f.status = '$safeStatus'";
        }
        if ($fromDate !== '' && $toDate !== '') {
            $safeFrom = $conn->real_escape_string($fromDate);
            $safeTo   = $conn->real_escape_string($toDate);
            $conditions[] = "DATE(f.created_at) BETWEEN '$safeFrom' AND '$safeTo'";
        }
    } elseif ($searchField === 'date') {
        if ($fromDate !== '' && $toDate !== '') {
            $safeFrom = $conn->real_escape_string($fromDate);
            $safeTo   = $conn->real_escape_string($toDate);
            $conditions[] = "DATE(f.created_at) BETWEEN '$safeFrom' AND '$safeTo'";
        }
    }
}

if (count($conditions) > 0) {
    $where = "WHERE " . implode(" AND ", $conditions);
}

$totalResult = $conn->query("SELECT COUNT(DISTINCT f.id) AS total FROM farmers f $where");
$totalRows   = $totalResult->fetch_assoc()['total'];
$totalPages  = ceil($totalRows / $limit);

// Fetch associations for dropdowns
$assocResult = $conn->query("SELECT id, name FROM associations ORDER BY name ASC");
$associations = [];
if ($assocResult) {
    while ($row = $assocResult->fetch_assoc()) {
        $associations[] = $row;
    }
}

$query = "SELECT f.id,
          CONCAT(f.first_name, ' ',
                 CASE WHEN f.middle_name IS NOT NULL AND f.middle_name != '' 
                      THEN CONCAT(LEFT(f.middle_name,1), '. ') ELSE '' END,
                 f.last_name) AS full_name,
          f.email, f.province, f.municipality, f.barangay, f.phone, f.status,
          DATE_FORMAT(f.created_at, '%m/%d/%Y') AS registered_at,
          COUNT(DISTINCT fl.id) AS lot_count,
          COALESCE(latest_lot.farm_size, 0) AS display_farm_size,
          COALESCE(latest_assoc.name, direct_assoc.name) AS association_name
          FROM farmers f 
          LEFT JOIN farmer_lots fl ON f.id = fl.farmer_id
          LEFT JOIN associations direct_assoc ON f.association_id = direct_assoc.id
          LEFT JOIN (
              SELECT fl_latest.farmer_id, fl_latest.farm_size
              FROM farmer_lots fl_latest
              WHERE fl_latest.id = (
                  SELECT MAX(fl_sub.id) 
                  FROM farmer_lots fl_sub 
                  WHERE fl_sub.farmer_id = fl_latest.farmer_id
              )
          ) latest_lot ON f.id = latest_lot.farmer_id
          LEFT JOIN (
              SELECT fl1.farmer_id, a.name
              FROM farmer_lots fl1
              JOIN associations a ON fl1.association_id = a.id
              WHERE fl1.status = 'Active'
                AND fl1.id = (
                    SELECT MAX(fl2.id) 
                    FROM farmer_lots fl2 
                    WHERE fl2.farmer_id = fl1.farmer_id 
                      AND fl2.status = 'Active' 
                      AND fl2.association_id IS NOT NULL
                )
          ) latest_assoc ON f.id = latest_assoc.farmer_id
          $where 
          GROUP BY f.id
          ORDER BY f.created_at DESC
          LIMIT $limit OFFSET $offset";
$result = $conn->query($query);

$printFromFormatted = (!empty($fromDate)) ? date('F j, Y', strtotime($fromDate)) : '';
$printToFormatted   = (!empty($toDate)) ? date('F j, Y', strtotime($toDate)) : '';
$hasDateRange       = ($searchField === 'status' || $searchField === 'date') && !empty($fromDate) && !empty($toDate);
?>
<!DOCTYPE html>
<html lang="en-US">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Farmers | AMRMS</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.css">
<style>
    .main-content { height: auto !important; padding: 50px 20px; }
    h2 { font-size: 28px; color: #2d7a2d; font-weight: bold; text-align: center; text-shadow: 1px 1px 3px rgba(14,4,4,0.7); margin: 10px 0 0 0; padding-bottom: 1px; }
    form#searchForm { display: flex; align-items: center; flex-wrap: wrap; gap: 10px; margin-top: 15px; }
    select, input[type="text"], input[type="date"], button { padding: 8px 12px; font-size: 13px; border: 1px solid #ccc; border-radius: 6px; }
    button { background-color: #2d7a2d; color: white; border: none; cursor: pointer; transition: background-color 0.3s; }
    button:hover:not(:disabled) { background-color: #256725; }
    button:disabled { background-color: #a5d6a5; cursor: not-allowed; opacity: 0.6; }
    .flatpickr-input { padding: 8px 12px !important; font-size: 14px !important; border: 1px solid #ccc !important; border-radius: 6px !important; background: white !important; color: #333 !important; cursor: pointer !important; width: 130px !important; box-sizing: border-box !important; }
    .flatpickr-input:focus { outline: none !important; border-color: #2d7a2d !important; }
    
    .table-container {
      border: 1px solid #ddd; 
      border-radius: 8px; 
      background-color: white;
      box-shadow: 0 2px 5px rgba(0,0,0,0.1); 
      margin-top: 10px; 
      max-height: 330px; 
      overflow-y: auto;
      overflow-x: auto;
    }
    table { width: 100%; border-collapse: collapse; background-color: white; table-layout: fixed; }
    
    table th:nth-child(1), table td:nth-child(1) { width: 100px; white-space: nowrap; }
    table th:nth-child(2), table td:nth-child(2) { width: 140px; }
    table th:nth-child(3), table td:nth-child(3) { width: 170px; word-break: break-all; }
    table th:nth-child(4), table td:nth-child(4) { width: 110px; white-space: nowrap; }
    table th:nth-child(5), table td:nth-child(5) { width: 220px; word-break: break-word; }
    table th:nth-child(6), table td:nth-child(6) { width: 120px; }
    table th:nth-child(7), table td:nth-child(7) { width: 80px; }
    table th:nth-child(8), table td:nth-child(8) { width: 80px; }
    table th:nth-child(9), table td:nth-child(9) { width: 70px; }
    
    th, td { 
      padding: 10px 10px; 
      text-align: center !important; 
      border-bottom: 1px solid #ddd; 
      vertical-align: middle; 
      line-height: 1.35;
      font-size: 13px; 
      box-sizing: border-box;
    }
    
    thead { position: sticky; top: 0; z-index: 5; }
    th { background-color: #2d7a2d; color: white; font-weight: 600; white-space: nowrap; }
    tbody tr { cursor: pointer; }
    tr:hover { background-color: #f9fafb; }
    tr.selected { background-color: #c3e6cb !important; }
    
    .view-container { display: flex; justify-content: center; align-items: center; gap: 10px; margin-top: 20px; }
    .view-container button { background-color: #2d7a2d; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-size: 15px; transition: .2s; display: inline-flex; align-items: center; gap: 6px; font-weight: 600; }
    .view-container button:hover:not(:disabled) { background-color: #1a5c1a; transform: scale(1.03); }
    .view-container button:disabled { background-color: #a5d6a5; cursor: not-allowed; opacity: 0.6; transform: none; }
    
    label.search-lbl { color: #000000; font-weight: bold; font-size: 14px; margin-right: 5px; text-shadow: 1px 1px 3px rgba(14,4,4,0.7); }
    .badge { display: inline-block; padding: 3px 7px; border-radius: 12px; font-size: 11px; font-weight: 600; } 
    .badge-assoc { display: inline-block; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; color: #000000; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 110px; }
    .badge-no-assoc { display: inline-block; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; background: #f3f4f6; color: #9ca3af; }
    .preparedBy, .print-subtitle, .report-date { display: none;}
    
    .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.55); backdrop-filter: blur(6px); justify-content: center; align-items: center; z-index: 9999; padding: 16px; }
    @keyframes modalIn { from { opacity:0; transform: scale(0.96) translateY(16px); } to { opacity:1; transform: scale(1) translateY(0); } }
    .modal-content { background: #fff; border-radius: 16px; width: 100%; max-width: 760px; max-height: 90vh; display: flex; flex-direction: column; box-shadow: 0 20px 50px rgba(0,0,0,0.22); animation: modalIn 0.25s cubic-bezier(.34,1.2,.64,1) both; overflow: hidden; }
    .modal-header { background: linear-gradient(120deg, #1e6b35 0%, #2d9148 60%, #37a85a 100%); padding: 16px 22px; display: flex; align-items: center; gap: 12px; flex-shrink: 0; position: relative; }
    .modal-header-text h2 { color: #fff; margin: 0; font-size: 1.1rem; font-weight: 700; text-shadow: none; text-align: left; }
    .close-modal { position: absolute; top: 16px; right: 18px; background: rgba(255,255,255,0.15); border: none; color: #fff; font-size: 20px; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background 0.2s, transform 0.2s; }
    .close-modal:hover { background: rgba(255,255,255,0.28); transform: rotate(90deg); }
    .modal-body { padding: 16px 22px 12px; overflow-y: auto; flex: 1; }
    
    .section-label { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #2d7d46; margin: 0 0 8px; display: flex; align-items: center; gap: 6px; }
    .section-label::after { content: ''; flex: 1; height: 1px; background: #d4edda; }
    .section-block { margin-bottom: 14px; text-align: left; }
    .form-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px 12px; }
    .form-group { display: flex; flex-direction: column; text-align: left; }
    .form-group label { color: #374151; font-weight: 600; font-size: 0.75rem; margin-bottom: 4px; display: flex; align-items: center; gap: 4px; text-shadow: none; }
    .required-star { color: #dc2626; }
    .form-group input, .form-group select { width: 100%; padding: 8px 10px; border: 1.5px solid #e5e7eb; border-radius: 7px; font-size: 0.83rem; background: #f9fafb; color: #111; font-family: inherit; box-sizing: border-box; transition: border-color 0.2s, box-shadow 0.2s, background 0.2s; }
    .form-group input:focus, .form-group select:focus { outline: none; border-color: #2d9148; background: #fff; box-shadow: 0 0 0 3px rgba(45,145,72,0.12); }
    
    .lot-section { background: #f0fdf4; border: 1.5px dashed #86efac; border-radius: 10px; padding: 12px 14px; margin-top: 4px; }
    .lot-item { background: #fff; border: 1.5px solid #e5e7eb; border-radius: 8px; padding: 12px 14px; margin-bottom: 10px; }
    .lot-item-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
    .lot-number-display { font-weight: 700; color: #2d7d46; font-size: 0.82rem; }
    .remove-lot-btn { background: #fef2f2; color: #dc2626; border: 1.5px solid #fecaca; padding: 3px 9px; border-radius: 6px; cursor: pointer; font-size: 0.75rem; font-weight: 600; }
    .add-lot-btn { background: #f0fdf4; color: #166534; border: 1.5px solid #86efac; padding: 7px 14px; border-radius: 7px; cursor: pointer; font-size: 0.82rem; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; margin-top: 4px; }
    .add-lot-btn:hover{ background-color: #166534; color: #f0fdf4;}

    .lot-card-btn { padding: 4px 12px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; border: none; cursor: pointer; transition: all 0.2s; }
    .lot-btn-activate { background-color: #108043; color: white; }
    .lot-btn-activate:hover:not(:disabled) { background-color: #0b5e31; }
    .lot-btn-deactivate { background-color: #e50000; color: white; }
    .lot-btn-deactivate:hover:not(:disabled) { background-color: #b30000; }
    .lot-card-btn:disabled { opacity: 0.45; cursor: not-allowed; }

    .modal-footer { padding: 12px 22px; background: #f9fafb; border-top: 1px solid #e5e7eb; display: flex; gap: 10px; justify-content: flex-end; flex-shrink: 0; }
    .btn { padding: 9px 22px; border: none; border-radius: 8px; font-size: 0.88rem; font-weight: 700; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 7px; font-family: inherit; }
    .btn-primary { background: linear-gradient(135deg, #1e6b35, #2d9148); color: #fff; }
    .btn-primary:disabled { background: #a5d6a5 !important; cursor: not-allowed; opacity: 0.6; }
    .btn-secondary { background: #fff; color: #6b7280; border: 1.5px solid #e5e7eb; }
    
    .field-hint { font-size: 0.72rem; margin-top: 3px; display: none; align-items: center; gap: 4px; font-weight: 500; }
    .field-hint.error { color: #dc2626; display: flex; }
    .field-hint.success { color: #16a34a; display: flex; }
    
    .ro { padding: 8px 10px; border: 1.5px solid #e5e7eb; border-radius: 7px; font-size: 0.83rem; background: #f0f2f5; color: #6b7280; cursor: not-allowed; width: 100%; box-sizing: border-box; font-style: italic; }
    .ew { padding: 8px 10px; border: 1.5px solid #2d9148; border-radius: 7px; font-size: 0.83rem; background: #fff; color: #111; width: 100%; box-sizing: border-box; outline: none; }

    #confirmModal, #responseModal { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:10000; justify-content:center; align-items:center; }

    .discount-banner { display: flex; align-items: center; gap: 8px; background-color: #eefcf3; border: 1.5px solid #86efac; color: #15803d; font-size: 0.78rem; font-weight: 600; padding: 8px 12px; border-radius: 8px; box-sizing: border-box; }
    
    .preparedBy, .print-subtitle, .report-date { display: none;}
    
    @media print {
      form, .view-container, .usernames-bar, header, nav, .header, .navbar, .dashboard-header, .top-bar { display: none !important; }
      .main-content h2 { display: block !important; color: #2d7a2d !important; text-shadow: none !important; margin: 0 0 5px 0 !important; padding: 0 !important; text-align: center !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
      .print-subtitle { display: block !important; text-align: center; font-size: 14px; font-weight: bold; color: #333; margin-bottom: 15px; }
      .report-date { display: block !important; text-align: left; font-size: 12px; color: #333; margin-bottom: 10px; font-weight: bold; }
      body { background: white !important; overflow: visible !important; margin: 0 !important; padding: 0 !important; }
      .main-content { height: auto !important; overflow: visible !important; padding: 0 !important; margin-top: 0 !important; }
      .table-container { border: none !important; box-shadow: none !important; overflow: visible !important; margin-top: 0 !important; max-height: none !important; }
      table { table-layout: auto !important; width: 100% !important; }
      thead { position: static !important; display: table-header-group !important; }
      tbody tr { display: table-row !important; }
      table th, table td, table th:nth-child(n), table td:nth-child(n) { width: auto !important; white-space: normal !important; word-break: normal !important; word-wrap: normal !important; padding: 8px 6px !important; font-size: 11px !important; text-align: center !important; }
      th { background-color: #2d7a2d !important; color: white !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
      .preparedBy { display: block !important; text-align: left; margin-top: 85px !important; font-size: 15px !important; color: #000000 !important; }
    }

    @media(max-width:640px) { .main-content { padding: 10px; } }
</style>
</head>
<body>
<div class="main-content">
    <h2>List of Farmers</h2>

    <?php if ($hasDateRange): ?>
        <div class="print-subtitle">From <?= htmlspecialchars($printFromFormatted) ?> to <?= htmlspecialchars($printToFormatted) ?></div>
    <?php endif; ?>

    <form method="POST" action="" id="searchForm">
        <select name="search_field" id="search_field" onchange="handleFieldChange()">
            <option value="all"     <?= $searchField==='all'     ? 'selected':'' ?>>All</option>
            <option value="name"    <?= $searchField==='name'    ? 'selected':'' ?>>Farmer Name</option>
            <option value="address" <?= $searchField==='address' ? 'selected':'' ?>>Farmer Address</option>
            <option value="status"  <?= $searchField==='status'  ? 'selected':'' ?>>Status</option>
            <option value="date"    <?= $searchField==='date'    ? 'selected':'' ?>>Date Registered</option>
        </select>

        <input type="text" name="search_term" id="search_term" placeholder="Search..." value="<?= htmlspecialchars($searchTerm) ?>" style="display:none;" oninput="validateSearchForm()">

        <select name="search_status" id="search_status" style="display:none;" onchange="validateSearchForm()">
            <option value="" disabled <?= $statusTerm==='' ? 'selected':'' ?> hidden>Select Status</option>
            <option value="Active"   <?= $statusTerm==='Active'   ? 'selected':'' ?>>Active</option>
            <option value="Inactive" <?= $statusTerm==='Inactive' ? 'selected':'' ?>>Inactive</option>
        </select>

        <label for="from_date_display" id="from_label" class="search-lbl" style="display:none;">From</label>
        <input type="text" id="from_date_display" placeholder="mm/dd/yyyy" readonly style="display:none; width:130px;">
        <input type="hidden" name="from_date" id="from_date" value="<?= htmlspecialchars($fromDate) ?>">

        <label for="to_date_display" id="to_label" class="search-lbl" style="display:none;">To</label>
        <input type="text" id="to_date_display" placeholder="mm/dd/yyyy" readonly style="display:none; width:130px;">
        <input type="hidden" name="to_date" id="to_date" value="<?= htmlspecialchars($toDate) ?>">

        <button type="submit" id="searchBtn" style="display:none;" disabled>Search</button>
    </form>

    <div class="report-date">Report Date: <?= date('F j, Y g:i A') ?></div>

    <div class="table-container" id="printSection">
        <table id="farmerTable">
            <thead>
                <tr>
                    <th>Date Registered</th>
                    <th>Farmer Name</th>
                    <th>Email</th>
                    <th>Contact No.</th>
                    <th>Farmer Address</th>
                    <th>Association</th>
                    <th>Farmer Lots</th>
                    <th>Farm Size</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($result && $result->num_rows > 0):
                while ($row = $result->fetch_assoc()): ?>
                <tr data-id="<?= $row['id'] ?>" data-status="<?= htmlspecialchars($row['status']) ?>">
                    <td><?= htmlspecialchars($row['registered_at']) ?></td>
                    <td><?= htmlspecialchars($row['full_name']) ?></td>
                    <td><?= htmlspecialchars($row['email']) ?></td>
                    <td><?= htmlspecialchars($row['phone']) ?></td>
                    <td><?= htmlspecialchars($row['barangay'].', '.$row['municipality'].', '.$row['province']) ?></td>
                    <td>
                        <?php if ($row['association_name']): ?>
                            <span class="badge-assoc" title="<?= htmlspecialchars($row['association_name']) ?>"><?= htmlspecialchars($row['association_name']) ?></span>
                        <?php else: ?>
                            <span class="badge-no-assoc">None</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge"><?= $row['lot_count'] ?> lot(s)</span></td>
                    <td><?= number_format($row['display_farm_size'] ?? 0, 2) ?> ha</td>
                    <td><span class="status-cell" style="font-weight:bold;"><?= htmlspecialchars($row['status']) ?></span></td>
                </tr>
            <?php endwhile; else: ?>
                <tr><td colspan="9" style="text-align:center; padding:20px; color:#555;">No records found</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        
        <div class="preparedBy">
            Prepared By:
            <span style="font-weight:bold;">DA Staff</span>
        </div>
    </div>

    <div class="view-container">
        <button type="button" id="addBtn">Add</button>
        <button type="button" id="editBtn" disabled>Edit</button>
        <button type="button" id="activateBtn" disabled>Activate</button>
        <button type="button" id="deactivateBtn" disabled>Deactivate</button>
        <button type="button" id="printBtn" <?= ($totalRows == 0) ? 'disabled' : '' ?>>Print</button>
    </div>
</div>

<!-- ADD FARMER MODAL -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-header-text">
                <h2>Add New Farmer</h2>
            </div>
        </div>
        <div class="modal-body">
            <form action="addstaff_farmers.php" method="POST" id="addFarmerForm">

                <div class="section-block">
                    <p class="section-label">👤 Personal Information</p>
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:8px 12px;margin-bottom:10px;">
                        <div class="form-group">
                            <label>First Name <span class="required-star">*</span></label>
                            <input type="text" name="first_name" id="add_first_name" placeholder="First name" required oninput="checkNameDuplicate(); validateAddForm();">
                            <span class="field-hint" id="first_name_hint"></span>
                        </div>
                        <div class="form-group">
                            <label>Middle Name</label>
                            <input type="text" name="middle_name" id="add_middle_name" placeholder="Middle name" oninput="validateAddForm()">
                        </div>
                        <div class="form-group">
                            <label>Last Name <span class="required-star">*</span></label>
                            <input type="text" name="last_name" id="add_last_name" placeholder="Last name" required oninput="checkNameDuplicate(); validateAddForm();">
                            <span class="field-hint" id="last_name_hint"></span>
                        </div>
                        <div class="form-group">
                            <label>Sex <span class="required-star">*</span></label>
                            <select name="sex" id="add_sex" required onchange="validateAddForm()">
                                <option value="" disabled selected hidden>Select sex</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:8px 12px;">
                        <div class="form-group">
                            <label>Date of Birth <span class="required-star">*</span></label>
                            <input type="text" id="dob_display" placeholder="mm/dd/yyyy" readonly style="cursor:pointer;">
                            <input type="hidden" name="date_of_birth" id="dob_input">
                            <span class="field-hint" id="dob_hint"></span>
                        </div>
                        <div class="form-group">
                            <label>Age</label>
                            <input type="number" name="age" id="age_input" placeholder="Auto" readonly style="background:#f0fdf4;color:#166534;">
                        </div>
                        <div class="form-group">
                            <label>Email <span class="required-star">*</span></label>
                            <input type="email" name="email" id="add_email" placeholder="example@gmail.com" required oninput="checkEmailDuplicate(); validateAddForm();">
                            <span class="field-hint" id="email_hint"></span>
                        </div>
                        <div class="form-group">
                            <label>Phone <span class="required-star">*</span></label>
                            <input type="tel" name="phone" id="add_phone" placeholder="09XXXXXXXXX" maxlength="11" required oninput="validatePhone(this); validateAddForm();">
                            <span class="field-hint" id="phone_hint"></span>
                        </div>
                    </div>
                </div>

                <div class="section-block">
                    <p class="section-label">📍 Address</p>
                    <div class="form-grid-3">
                        <div class="form-group">
                            <label>Province <span class="required-star">*</span></label>
                            <input type="text" name="province" value="Zamboanga Del Sur" readonly style="background:#f0fdf4;color:#166534;">
                        </div>
                        <div class="form-group">
                            <label>Municipality <span class="required-star">*</span></label>
                            <select name="municipality" id="add_municipality" required onchange="updateBarangays('add_municipality', 'add_barangay'); validateAddForm();">
                                <option value="">Select Municipality</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Barangay <span class="required-star">*</span></label>
                            <select name="barangay" id="add_barangay" required disabled onchange="validateAddForm()">
                                <option value="">Select Barangay</option>
                            </select>
                        </div>
                    </div>
                </div>

                <input type="hidden" name="password" value="123456">
                <input type="hidden" name="confirm_password" value="123456">

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
            <button type="button" class="btn btn-primary" id="saveAddBtn" disabled onclick="submitAddFarmer()">Add Farmer</button>
            <button type="button" class="btn btn-secondary" onclick="closeAddModal()">Close</button>
        </div>
    </div>
</div>

<!-- EDIT FARMER MODAL -->
<div id="editModal" class="modal">
    <div class="modal-content" style="max-width:860px;">
        <div class="modal-header">
            <div class="modal-header-text">
                <h2>Edit Farmer</h2>
            </div>
        </div>
        <div class="modal-body">
            <form action="edit_farmer_action.php" method="POST" id="editFarmerForm">
                <input type="hidden" name="id" id="editFarmerId">

                <div class="section-block">
                    <p class="section-label">👤 Personal Information (Read-Only)</p>
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:8px 12px;margin-bottom:10px;">
                        <div class="form-group">
                            <label>First Name</label>
                            <input type="text" id="editFirstName" name="first_name" readonly class="ro">
                        </div>
                        <div class="form-group">
                            <label>Middle Name</label>
                            <input type="text" id="editMiddleName" name="middle_name" readonly class="ro">
                        </div>
                        <div class="form-group">
                            <label>Last Name</label>
                            <input type="text" id="editLastName" name="last_name" readonly class="ro">
                        </div>
                        <div class="form-group">
                            <label>Sex</label>
                            <input type="text" id="editSexDisplay" name="sex" readonly class="ro">
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:8px 12px;">
                        <div class="form-group">
                            <label>Date of Birth</label>
                            <input type="text" id="editDobDisplay" readonly class="ro">
                        </div>
                        <div class="form-group">
                            <label>Age</label>
                            <input type="text" id="editAge" readonly class="ro">
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="text" id="editEmail" name="email" readonly class="ro">
                        </div>
                        <div class="form-group">
                            <label>Phone <span class="required-star">*</span></label>
                            <input type="text" id="editPhone" name="phone" placeholder="09XXXXXXXXX" class="ew" required oninput="validateEditForm()">
                        </div>
                    </div>
                </div>

                <div class="section-block">
                    <p class="section-label">📍 Location Details & Association</p>
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:8px 12px;margin-bottom:8px;">
                        <div class="form-group">
                            <label>Province</label>
                            <input type="text" id="editProvince" name="province" readonly class="ro">
                        </div>
                        <div class="form-group">
                            <label>Municipality <span class="required-star">*</span></label>
                            <select name="municipality" id="editMunicipality" class="ew" required onchange="updateBarangays('editMunicipality', 'editBarangay'); validateEditForm();">
                                <option value="">Select Municipality</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Barangay <span class="required-star">*</span></label>
                            <select name="barangay" id="editBarangay" class="ew" required onchange="validateEditForm()">
                                <option value="">Select Barangay</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <input type="text" id="editStatus" name="status" readonly class="ro">
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px 12px;">
                        <div class="form-group">
                            <div>
                                <label>Association <span style="font-weight: normal; color: #6b7280; font-size: 0.7rem;">(overall — can be overridden per lot)</span></label>
                                <input type="text" id="editAssociationDisplay" readonly class="ro" placeholder="None (No Active Association)">
                            </div>
                            <div id="edit_discount_container" style="display:none; margin-top: 6px;">
                                <div class="discount-banner">🏷️ <span class="discount-text">This farmer qualifies for a <b>5% discount</b> on machines owned by the association.</span></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="section-block">
                    <p class="section-label">🌾 Farm Lots</p>
                    <div id="editExistingLots" style="margin-bottom:8px;"></div>
                    <div class="lot-section">
                        <div style="font-size:0.75rem;font-weight:600;color:#166534;margin-bottom:8px;">➕ New Lots to Add</div>
                        <div id="editLotsContainer"></div>
                        <button type="button" class="add-lot-btn" onclick="addEditLot()">＋ Add Farm Lot</button>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-primary" id="saveEditBtn" onclick="submitEditFarmer()">Save Changes</button>
            <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Close</button>
        </div>
    </div>
</div>

<!-- CONFIRMATION MODAL -->
<div id="confirmModal">
    <div style="background:white;border-radius:14px;width:100%;max-width:380px;box-shadow:0 20px 50px rgba(0,0,0,0.3);overflow:hidden;">
        <div id="confirmHeader" style="background:linear-gradient(135deg,#1e6b35,#2d9148);padding:18px 22px;">
            <div style="color:white;font-size:1rem;font-weight:700;" id="confirmTitle">Confirm Action</div>
        </div>
        <div style="padding:20px 22px; text-align:left;">
            <p id="confirmMessage" style="color:#374151;font-size:0.9rem;margin-bottom:20px;line-height:1.5;"></p>
            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <button id="confirmYesBtn" onclick="confirmYes()" style="padding:9px 20px;background:#2d7a2d;color:white;border:none;border-radius:8px;font-size:0.88rem;font-weight:700;cursor:pointer;">Yes</button>
                <button onclick="confirmNo()" style="padding:9px 20px;background:#f5f5f5;color:#555;border:1px solid #ddd;border-radius:8px;font-size:0.88rem;font-weight:600;cursor:pointer;">No</button>
            </div>
        </div>
    </div>
</div>

<!-- RESPONSE POPUP MODAL -->
<div id="responseModal" class="modal">
    <div style="background:#fff;border-radius:16px;padding:30px 25px;width:90%;max-width:400px;text-align:center;box-shadow:0 8px 25px rgba(0,0,0,0.2);">
        <div id="responseIcon" style="font-size:3rem;margin-bottom:10px;">✅</div>
        <p id="responseText" style="font-size:16px;font-weight:600;margin-bottom:20px;color:#333;"></p>
        <button type="button" onclick="closeResponseModal()" style="background-color:#2d7a2d;color:white;border:none;padding:8px 30px;cursor:pointer;border-radius:6px;font-size:16px;font-weight:600;">OK</button>
    </div>
</div>

<form method="POST" id="statusForm" action="update_farmer_status.php" style="display:none;">
    <input type="hidden" name="farmer_id" id="sf_farmer_id">
    <input type="hidden" name="new_status" id="sf_new_status">
</form>

<script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.js"></script>
<script>
let wasSearched = <?= ($searchField !== 'all' && ($searchTerm !== '' || $statusTerm !== '' || ($fromDate !== '' && $toDate !== ''))) ? 'true' : 'false' ?>;
let selectedRowId = null;
let selectedRowStatus = null;
let confirmCallback = null;
let nameValid = true;
let emailValid = true;

const associationsData = <?php echo json_encode($associations); ?>;

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

document.addEventListener('DOMContentLoaded', () => {
    initLocationDropdowns('add_municipality', 'add_barangay');
    initLocationDropdowns('editMunicipality', 'editBarangay');
    toggleInputs();
    validateSearchForm();

    document.querySelectorAll('#farmerTable tbody tr[data-id]').forEach(row => {
        row.addEventListener('click', () => {
            document.querySelectorAll('#farmerTable tbody tr').forEach(r => r.classList.remove('selected'));
            row.classList.add('selected');
            selectedRowId = row.getAttribute('data-id');
            selectedRowStatus = row.getAttribute('data-status');
            
            document.getElementById('editBtn').disabled = false;
            document.getElementById('activateBtn').disabled = (selectedRowStatus === 'Active');
            document.getElementById('deactivateBtn').disabled = (selectedRowStatus === 'Inactive');
        });
        row.addEventListener('dblclick', () => {
            const id = row.getAttribute('data-id');
            if (id) window.location.href = `staff_farmers_profile.php?id=${id}`;
        });
    });
});

function toggleDiscountBadge(selectElem, containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;

    const val = selectElem.value;
    if (val) {
        const selectedOption = selectElem.options[selectElem.selectedIndex];
        const name = selectedOption.getAttribute('data-name') || selectedOption.text;
        const textSpan = container.querySelector('.discount-text');
        if (textSpan) {
            textSpan.innerHTML = `This lot qualifies for a <b>5% discount</b> on machines owned by <b>${name}</b>.`;
        }
        container.style.display = 'block';
    } else {
        container.style.display = 'none';
    }
}

function initLocationDropdowns(muniElemId, bgyElemId) {
    const muniSelect = document.getElementById(muniElemId);
    if (!muniSelect) return;
    
    muniSelect.innerHTML = '<option value="">Select Municipality</option>';
    
    Object.keys(zamboangaDelSurData).sort().forEach(muni => {
        const opt = document.createElement('option');
        opt.value = muni;
        opt.textContent = muni;
        muniSelect.appendChild(opt);
    });

    const bgySelect = document.getElementById(bgyElemId);
    if (bgySelect) {
        bgySelect.innerHTML = '<option value="">Select Barangay</option>';
        bgySelect.disabled = true;
    }
}

function updateBarangays(muniElemId, bgyElemId, selectedBarangay = '') {
    const muniSelect = document.getElementById(muniElemId);
    const bgySelect = document.getElementById(bgyElemId);
    if (!muniSelect || !bgySelect) return;

    const selectedMuni = muniSelect.value;
    bgySelect.innerHTML = '<option value="">Select Barangay</option>';
    
    if (selectedMuni && zamboangaDelSurData[selectedMuni]) {
        bgySelect.disabled = false;
        zamboangaDelSurData[selectedMuni].sort().forEach(bgy => {
            const opt = document.createElement('option');
            opt.value = bgy;
            opt.textContent = bgy;
            if (bgy === selectedBarangay) opt.selected = true;
            bgySelect.appendChild(opt);
        });
    } else {
        bgySelect.disabled = true;
    }
}

function checkNameDuplicate() {
    const fn = document.getElementById('add_first_name').value.trim();
    const ln = document.getElementById('add_last_name').value.trim();
    const fnHint = document.getElementById('first_name_hint');
    const lnHint = document.getElementById('last_name_hint');

    if (!fn || !ln) {
        resetHint(fnHint, document.getElementById('add_first_name'));
        resetHint(lnHint, document.getElementById('add_last_name'));
        nameValid = true;
        validateAddForm();
        return;
    }

    const formData = new FormData();
    formData.append('first_name', fn);
    formData.append('last_name', ln);

    fetch('check_farmer_duplicate.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.exists) {
                nameValid = false;
                setInputError(document.getElementById('add_first_name'));
                setInputError(document.getElementById('add_last_name'));
                fnHint.className = 'field-hint error';
                fnHint.textContent = 'User with this name already exists.';
            } else {
                nameValid = true;
                setInputOk(document.getElementById('add_first_name'));
                setInputOk(document.getElementById('add_last_name'));
                resetHint(fnHint);
                resetHint(lnHint);
            }
            validateAddForm();
        });
}

function checkEmailDuplicate() {
    const email = document.getElementById('add_email').value.trim();
    const hint = document.getElementById('email_hint');
    const input = document.getElementById('add_email');

    if (!email || !validateEmail(input)) {
        emailValid = false;
        validateAddForm();
        return;
    }

    const formData = new FormData();
    formData.append('email', email);

    fetch('check_farmer_duplicate.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.email_exists) {
                emailValid = false;
                setInputError(input);
                hint.className = 'field-hint error';
                hint.textContent = 'Email has already been taken.';
            } else {
                emailValid = true;
                setInputOk(input);
                hint.className = 'field-hint success';
                hint.textContent = '';
            }
            validateAddForm();
        });
}

function formatLocalDate(dateObj) {
    const year = dateObj.getFullYear();
    const month = String(dateObj.getMonth() + 1).padStart(2, '0');
    const day = String(dateObj.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

document.getElementById('printBtn').addEventListener('click', () => { window.print(); });

function handleFieldChange() {
    document.getElementById('search_term').value = '';
    document.getElementById('search_status').value = '';
    document.getElementById('from_date').value = '';
    document.getElementById('to_date').value = '';
    if(window.fpFrom) window.fpFrom.clear();
    if(window.fpTo)   {
        window.fpTo.clear();
        window.fpTo.set('minDate', null);
    }
    
    if (wasSearched) {
        document.getElementById('searchForm').submit();
        return;
    }

    toggleInputs();
    
    if (document.getElementById('search_field').value === 'all') {
        document.getElementById('searchForm').submit();
    } else {
        validateSearchForm();
    }
}

function toggleInputs() {
    const field = document.getElementById('search_field').value;
    const isStatus = field === 'status';
    const isDate   = field === 'date';
    const isAll    = field === 'all';
    const isText   = field === 'name' || field === 'address';

    document.getElementById('searchBtn').style.display         = isAll ? 'none' : 'inline-block';
    document.getElementById('search_term').style.display        = isText ? 'inline-block' : 'none';
    document.getElementById('search_status').style.display      = isStatus ? 'inline-block' : 'none';
    document.getElementById('from_date_display').style.display = (isStatus || isDate) ? 'inline-block' : 'none';
    document.getElementById('to_date_display').style.display   = (isStatus || isDate) ? 'inline-block' : 'none';
    document.getElementById('from_label').style.display        = (isStatus || isDate) ? 'inline-block' : 'none';
    document.getElementById('to_label').style.display          = (isStatus || isDate) ? 'inline-block' : 'none';

    const inp = document.getElementById('search_term');
    if (field === 'name') {
        inp.placeholder = 'Enter farmer name...';
    } else if (field === 'address') {
        inp.placeholder = 'barangay, municipality';
    } else {
        inp.placeholder = 'Search...';
    }
}

function validateSearchForm() {
    const field = document.getElementById('search_field').value;
    const btn   = document.getElementById('searchBtn');
    let isValid = false;

    if (field === 'name' || field === 'address') {
        const val = document.getElementById('search_term').value.trim();
        isValid = val.length > 0;
        
        if (val.length === 0 && wasSearched) {
            document.getElementById('searchForm').submit();
            return;
        }
    } else if (field === 'status') {
        const st = document.getElementById('search_status').value;
        const fd = document.getElementById('from_date').value;
        const td = document.getElementById('to_date').value;
        isValid = st !== '' || (fd !== '' && td !== '');
    } else if (field === 'date') {
        const fd = document.getElementById('from_date').value;
        const td = document.getElementById('to_date').value;
        isValid = fd !== '' && td !== '';
    }

    btn.disabled = !isValid;
}

window.fpFrom = flatpickr('#from_date_display', {
    dateFormat:'m/d/Y',
    allowInput:false,
    onChange(dates) { 
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
        validateSearchForm();
    }
});

window.fpTo = flatpickr('#to_date_display', {
    dateFormat:'m/d/Y',
    allowInput:false,
    onChange(dates) { 
        document.getElementById('to_date').value = dates.length ? formatLocalDate(dates[0]) : ''; 
        validateSearchForm();
    }
});

<?php if ($fromDate): ?>
    window.fpFrom.setDate('<?= htmlspecialchars($fromDate) ?>', true, 'Y-m-d');
    const initMinDate = new Date('<?= htmlspecialchars($fromDate) ?>T00:00:00');
    initMinDate.setDate(initMinDate.getDate() + 1);
    window.fpTo.set('minDate', initMinDate);
<?php endif; ?>

<?php if ($toDate): ?>
    window.fpTo.setDate('<?= htmlspecialchars($toDate) ?>', true, 'Y-m-d');
<?php endif; ?>

let lotCounter = 0;
let fpDob = null;

document.getElementById('addBtn').addEventListener('click', () => {
    document.getElementById('addModal').style.display = 'flex';
    if (!lotCounter) addLot();
    setTimeout(initDobPicker, 80);
});

function closeAddModal() {
    document.getElementById('addModal').style.display = 'none';
    document.getElementById('addFarmerForm').reset();
    document.getElementById('lotsContainer').innerHTML = '';
    lotCounter = 0;
    if (fpDob) { fpDob.clear(); fpDob = null; }
    initLocationDropdowns('add_municipality', 'add_barangay');
    validateAddForm();
}

function initDobPicker() {
    if (fpDob) return;
    fpDob = flatpickr('#dob_display', {
        dateFormat: 'm/d/Y', maxDate: 'today', allowInput: false,
        onChange(selectedDates) {
            if (!selectedDates.length) {
                document.getElementById('dob_input').value = '';
                document.getElementById('age_input').value = '';
                validateAddForm();
                return;
            }
            const d = selectedDates[0];
            const yyyy = d.getFullYear(), mm = String(d.getMonth()+1).padStart(2,'0'), dd2 = String(d.getDate()).padStart(2,'0');
            document.getElementById('dob_input').value = `${yyyy}-${mm}-${dd2}`;
            const today = new Date();
            let age = today.getFullYear() - yyyy;
            const mDiff = today.getMonth() - d.getMonth();
            if (mDiff < 0 || (mDiff === 0 && today.getDate() < d.getDate())) age--;
            document.getElementById('age_input').value = age >= 0 ? age : '';
            validateAddForm();
        }
    });
}

function addLot() {
    lotCounter++;   
    const lc = lotCounter;
    const wrap = document.createElement('div');
    wrap.className = 'lot-item';
    wrap.id = `lot-${lc}`;

    let assocOptions = '<option value="">Select Association</option>';
    associationsData.forEach(a => {
        assocOptions += `<option value="${a.id}" data-name="${a.name}">${a.name}</option>`;
    });

    wrap.innerHTML = `
        <div class="lot-item-header">
            <span class="lot-number-display">Lot #${lc}</span>
        </div>
        <div class="form-grid-3" style="margin-bottom:8px;">
            <div class="form-group">
                <label>Lot Number <span class="required-star">*</span></label>
                <input type="text" name="lots[${lc}][lot_number]" placeholder="e.g., LOT-001" required oninput="validateAddForm()">
            </div>
            <div class="form-group">
                <label>Farm Size (ha) <span class="required-star">*</span></label>
                <input type="number" step="0.01" min="0.01" name="lots[${lc}][farm_size]" placeholder="0.00" required oninput="validateAddForm()">
            </div>
            <div class="form-group">
                <label>Province</label>
                <input type="text" name="lots[${lc}][province]" value="Zamboanga Del Sur" readonly style="background:#f0fdf4;color:#166534;">
            </div>
        </div>
        <div class="form-grid-3" style="margin-bottom:8px;">
            <div class="form-group">
                <label>Municipality <span class="required-star">*</span></label>
                <select name="lots[${lc}][municipality]" id="add_lot_muni_${lc}" required onchange="updateBarangays('add_lot_muni_${lc}', 'add_lot_bgy_${lc}'); validateAddForm();">
                    <option value="">Select Municipality</option>
                </select>
            </div>
            <div class="form-group">
                <label>Barangay <span class="required-star">*</span></label>
                <select name="lots[${lc}][barangay]" id="add_lot_bgy_${lc}" required disabled onchange="validateAddForm()">
                    <option value="">Select Barangay</option>
                </select>
            </div>
            <div class="form-group">
                <label>Farm Location <span class="required-star">*</span></label>
                <input type="text" name="lots[${lc}][farm_location]" placeholder="Specific location" required oninput="validateAddForm()">
            </div>
        </div>
        <div style="display:grid; grid-template-columns: 1fr 1.5fr; gap: 8px 12px; align-items: start; margin-top: 6px;">
            <div class="form-group">
                <label>Association <span style="font-weight: normal; color: #6b7280; font-size: 0.7rem;">(grants 5% discount)</span> <span class="required-star">*</span></label>
                <select name="lots[${lc}][association_id]" id="add_lot_assoc_${lc}" required onchange="toggleDiscountBadge(this, 'add_lot_discount_container_${lc}')">
                    ${assocOptions}
                </select>
            </div>
            <div id="add_lot_discount_container_${lc}" style="display:none; margin-top: 18px;">
                <div class="discount-banner">🏷️ <span class="discount-text">This lot qualifies for a <b>5% discount</b> on machines owned by association.</span></div>
            </div>
        </div>`;

    document.getElementById('lotsContainer').appendChild(wrap);
    initLocationDropdowns(`add_lot_muni_${lc}`, `add_lot_bgy_${lc}`);
    validateAddForm();
}

function removeLot(id) {
    const el = document.getElementById(`lot-${id}`);
    if (el) el.remove();
    validateAddForm();
}

function validateAddForm() {
    const form = document.getElementById('addFarmerForm');
    const requiredInputs = form.querySelectorAll('input[required], select[required]');
    let allFilled = true;

    requiredInputs.forEach(input => {
        if (!input.value.trim()) allFilled = false;
    });

    if (!document.getElementById('dob_input').value) allFilled = false;
    if (!nameValid || !emailValid) allFilled = false;

    document.getElementById('saveAddBtn').disabled = !allFilled;
}

function submitAddFarmer() {
    const form = document.getElementById('addFarmerForm');
    fetch(form.action, { method: 'POST', body: new FormData(form) })
        .then(r => r.json())
        .then(data => {
            closeAddModal();
            if(data.status === 'success') {
                showResponseModal('✅', 'Farmer Added Successfully!');
            } else {
                showResponseModal('⚠️', data.message || 'Error saving farmer record.');
            }
        })
        .catch(() => {
            closeAddModal();
            showResponseModal('✅', 'Farmer Added Successfully!');
        });
}

let editLotCounter = 0;

document.getElementById('editBtn').addEventListener('click', () => {
    if (!selectedRowId) return;
    fetch('get_farmer_edit.php?id=' + selectedRowId)
        .then(r => r.json())
        .then(data => {
            if (!data.success) { showResponseModal('⚠️', 'Failed to load farmer data'); return; }
            const f = data.farmer;

            document.getElementById('editFarmerId').value     = f.id;
            document.getElementById('editFirstName').value    = f.first_name    || '';
            document.getElementById('editMiddleName').value   = f.middle_name   || '';
            document.getElementById('editLastName').value     = f.last_name     || '';
            document.getElementById('editSexDisplay').value   = f.sex           || '';
            document.getElementById('editDobDisplay').value   = f.date_of_birth || '';
            document.getElementById('editAge').value          = f.age ? f.age + ' yrs' : '';
            document.getElementById('editEmail').value        = f.email         || '';
            document.getElementById('editPhone').value        = f.phone         || '';
            document.getElementById('editProvince').value     = f.province      || '';
            document.getElementById('editStatus').value       = f.status        || 'Active';

            const editMuniSelect = document.getElementById('editMunicipality');
            editMuniSelect.value = f.municipality || '';
            updateBarangays('editMunicipality', 'editBarangay', f.barangay || '');

            // Set read-only association display from latest active lot
            const assocDisplay = document.getElementById('editAssociationDisplay');
            const discountContainer = document.getElementById('edit_discount_container');

            if (f.latest_association_name) {
                assocDisplay.value = f.latest_association_name;
                if (discountContainer) {
                    discountContainer.querySelector('.discount-text').innerHTML = 
                        `Member receives <b>5% off</b> on association machines.`;
                    discountContainer.style.display = 'block';
                }
            } else {
                assocDisplay.value = 'None (No Active Association)';
                if (discountContainer) discountContainer.style.display = 'none';
            }

            editLotCounter = 0;
            document.getElementById('editLotsContainer').innerHTML = '';

            const assocMap = <?php echo json_encode(array_column($associations, 'name', 'id')); ?>;
            const existingDiv = document.getElementById('editExistingLots');
            existingDiv.innerHTML = '';
            
            if (f.existing_lots && f.existing_lots.length > 0) {
                f.existing_lots.forEach((lot, i) => {
                    const assocName = (lot.association_id && assocMap[lot.association_id]) ? assocMap[lot.association_id] : 'None';
                    const isActive  = (lot.status || 'Active') === 'Active';
                    const lotDivId  = `existing-lot-card-${lot.id}`;

                    existingDiv.innerHTML += `
                        <div id="${lotDivId}" 
                             style="background:#fff; border:1.5px solid #e5e7eb; border-radius:8px; padding:8px 12px; margin-bottom:6px; display:flex; flex-wrap:wrap; gap:8px; align-items:center;">
                            
                            <input type="hidden" name="lot_status[${lot.id}]" id="lot-status-input-${lot.id}" value="${isActive ? 'Active' : 'Inactive'}">
                            
                            <span style="font-weight:700; color:#2d7d46; font-size:0.82rem; min-width:45px;">Lot ${i+1}</span>
                            
                            <span style="background:#e8f5e9; color:#1b5e20; padding:2px 8px; border-radius:10px; font-size:0.78rem; font-weight:600;">
                                ${lot.lot_number}
                            </span>
                            
                            <span style="color:#374151; font-size:0.8rem;">
                                📍 ${lot.farm_location ? lot.farm_location + ', ' : ''}${lot.barangay}, ${lot.municipality}
                            </span>
                            
                            <span style="color:#374151; font-size:0.8rem;">
                                🌾 ${parseFloat(lot.farm_size).toFixed(2)} ha
                            </span>
                            
                            <span style="background:#f3f4f6; color:#6b7280; padding:2px 8px; border-radius:10px; font-size:0.78rem; font-weight:600;">
                                🏷️ ${assocName}
                            </span>
                            
                            <span id="lot-status-badge-${lot.id}" 
                                  style="margin-left:auto; background:${isActive ? '#dcfce7' : '#fee2e2'}; color:${isActive ? '#15803d' : '#dc2626'}; padding:2px 10px; border-radius:10px; font-size:0.75rem; font-weight:700;">
                                ● ${isActive ? 'Active' : 'Inactive'}
                            </span>
                            
                            <button type="button" 
                                    id="lot-activate-btn-${lot.id}" 
                                    class="lot-card-btn lot-btn-activate" 
                                    ${isActive ? 'disabled' : ''} 
                                    onclick="setLotStatus(${lot.id}, 'Active')">
                                Activate
                            </button>
                            
                            <button type="button" 
                                    id="lot-deactivate-btn-${lot.id}" 
                                    class="lot-card-btn lot-btn-deactivate" 
                                    ${!isActive ? 'disabled' : ''} 
                                    onclick="setLotStatus(${lot.id}, 'Inactive')">
                                Deactivate
                            </button>
                        </div>`;
                });
            } else {
                existingDiv.innerHTML = '<div style="color:#9ca3af;font-size:0.8rem;font-style:italic;margin-bottom:8px;">No existing lots found.</div>';
            }
            document.getElementById('editModal').style.display = 'flex';
            validateEditForm();
        })
        .catch(err => showResponseModal('⚠️', 'Error loading farmer data'));
});

function setLotStatus(lotId, newStatus) {
    const input         = document.getElementById(`lot-status-input-${lotId}`);
    const badge         = document.getElementById(`lot-status-badge-${lotId}`);
    const activateBtn   = document.getElementById(`lot-activate-btn-${lotId}`);
    const deactivateBtn = document.getElementById(`lot-deactivate-btn-${lotId}`);

    if (input) input.value = newStatus;

    if (newStatus === 'Active') {
        if (badge) {
            badge.textContent = '● Active';
            badge.style.background = '#dcfce7';
            badge.style.color      = '#15803d';
        }
        if (activateBtn) activateBtn.disabled   = true;
        if (deactivateBtn) deactivateBtn.disabled = false;
    } else {
        if (badge) {
            badge.textContent = '● Inactive';
            badge.style.background = '#fee2e2';
            badge.style.color      = '#dc2626';
        }
        if (activateBtn) activateBtn.disabled   = false;
        if (deactivateBtn) deactivateBtn.disabled = true;
    }
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

function addEditLot() {
    editLotCounter++;
    const lc = editLotCounter;
    const wrap = document.createElement('div');
    wrap.className = 'lot-item';
    wrap.id = `edit-lot-${lc}`;

    let assocOptions = '<option value="">Select Association</option>';
    associationsData.forEach(a => {
        assocOptions += `<option value="${a.id}" data-name="${a.name}">${a.name}</option>`;
    });

    wrap.innerHTML = `
        <div class="lot-item-header">
            <span class="lot-number-display">New Lot #${lc}</span>
        </div>
        <div class="form-grid-3" style="margin-bottom:8px;">
            <div class="form-group">
                <label>Lot Number <span class="required-star">*</span></label>
                <input type="text" name="new_lots[${lc}][lot_number]" placeholder="e.g., LOT-001" class="ew" required oninput="validateEditForm()">
            </div>
            <div class="form-group">
                <label>Farm Size (ha) <span class="required-star">*</span></label>
                <input type="number" step="0.01" min="0.01" name="new_lots[${lc}][farm_size]" placeholder="0.00" class="ew" required oninput="validateEditForm()">
            </div>
            <div class="form-group">
                <label>Province</label>
                <input type="text" name="new_lots[${lc}][province]" value="Zamboanga Del Sur" readonly class="ro">
            </div>
        </div>
        <div class="form-grid-3" style="margin-bottom:8px;">
            <div class="form-group">
                <label>Municipality <span class="required-star">*</span></label>
                <select name="new_lots[${lc}][municipality]" id="edit_lot_muni_${lc}" class="ew" required onchange="updateBarangays('edit_lot_muni_${lc}', 'edit_lot_bgy_${lc}'); validateEditForm();">
                    <option value="">Select Municipality</option>
                </select>
            </div>
            <div class="form-group">
                <label>Barangay <span class="required-star">*</span></label>
                <select name="new_lots[${lc}][barangay]" id="edit_lot_bgy_${lc}" class="ew" required disabled onchange="validateEditForm()">
                    <option value="">Select Barangay</option>
                </select>
            </div>
            <div class="form-group">
                <label>Farm Location <span class="required-star">*</span></label>
                <input type="text" name="new_lots[${lc}][farm_location]" placeholder="Specific location" class="ew" required oninput="validateEditForm()">
            </div>
        </div>
        <div style="display:grid; grid-template-columns: 1fr 1.5fr; gap: 8px 12px; align-items: start; margin-top: 6px;">
            <div class="form-group">
                <label>Association <span style="font-weight: normal; color: #6b7280; font-size: 0.7rem;">(grants 5% discount)</span> <span class="required-star">*</span></label>
                <select name="lots[${lc}][association_id]" id="edit_lot_assoc_${lc}" required onchange="toggleDiscountBadge(this, 'edit_lot_discount_container_${lc}')">
                    ${assocOptions}
                </select>
            </div>
            <div id="edit_lot_discount_container_${lc}" style="display:none; margin-top: 18px;">
                <div class="discount-banner">🏷️ <span class="discount-text">This lot qualifies for a <b>5% discount</b> on machines owned by association.</span></div>
            </div>
        </div>`;

    document.getElementById('editLotsContainer').appendChild(wrap);
    initLocationDropdowns(`edit_lot_muni_${lc}`, `edit_lot_bgy_${lc}`);
    validateEditForm();
}

function removeEditLot(id) {
    const el = document.getElementById(`edit-lot-${id}`);
    if (el) el.remove();
    validateEditForm();
}

function validateEditForm() {
    const form = document.getElementById('editFarmerForm');
    const requiredInputs = form.querySelectorAll('input[required], select[required]');
    let allFilled = true;

    requiredInputs.forEach(input => {
        if (!input.value.trim()) allFilled = false;
    });

    document.getElementById('saveEditBtn').disabled = !allFilled;
}

function submitEditFarmer() {
    const form = document.getElementById('editFarmerForm');
    fetch(form.action, { method: 'POST', body: new FormData(form) })
        .then(r => r.json())
        .then(data => {
            closeEditModal();
            if (data.status === 'success') {
                showResponseModal('✅', 'Farmer Profile Updated Successfully!');
            } else {
                showResponseModal('⚠️', data.message || 'Error updating farmer profile.');
            }
        })
        .catch(() => {
            closeEditModal();
            showResponseModal('✅', 'Farmer Profile Updated Successfully!');
        });
}

document.getElementById('activateBtn').addEventListener('click', () => {
    if (!selectedRowId) return;
    showConfirm(
        'Activate Farmer',
        'Are you sure you want to activate this farmer account?',
        () => {
            const formData = new FormData();
            formData.append('farmer_id', selectedRowId);
            formData.append('new_status', 'Active');

            fetch('update_farmer_status.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success') {
                        showResponseModal('✅', 'Farmer Successfully Activated!');
                    } else {
                        showResponseModal('⚠️', data.message || 'Failed to activate farmer.');
                    }
                })
                .catch(() => showResponseModal('⚠️', 'Connection error occurred.'));
        }
    );
});

document.getElementById('deactivateBtn').addEventListener('click', () => {
    if (!selectedRowId) return;
    showConfirm(
        'Deactivate Farmer',
        'Are you sure you want to deactivate this farmer account?',
        () => {
            const formData = new FormData();
            formData.append('farmer_id', selectedRowId);
            formData.append('new_status', 'Inactive');

            fetch('update_farmer_status.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success') {
                        showResponseModal('🔴', 'Farmer Successfully Deactivated!');
                    } else {
                        showResponseModal('⚠️', data.message || 'Failed to deactivate farmer.');
                    }
                })
                .catch(() => showResponseModal('⚠️', 'Connection error occurred.'));
        }
    );
});

function showConfirm(title, message, onYes) {
    document.getElementById('confirmTitle').textContent = title;
    document.getElementById('confirmMessage').textContent = message;
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

function showResponseModal(icon, text) {
    document.getElementById('responseIcon').textContent = icon;
    const responseText = document.getElementById('responseText');
    responseText.textContent = text;
    responseText.style.fontWeight = '700';
    document.getElementById('responseModal').style.display = 'flex';
}

function closeResponseModal() {
    document.getElementById('responseModal').style.display = 'none';
    window.location.reload();
}

function validateEmail(input) {
    const val = input.value.trim();
    const hint = document.getElementById('email_hint');
    const regex = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
    if (val === '') { resetHint(hint, input); return true; }
    if (!regex.test(val)) {
        setInputError(input); hint.className = 'field-hint error'; hint.textContent = 'Enter a valid email'; return false;
    }
    setInputOk(input); hint.className = 'field-hint success'; hint.textContent = ''; return true;
}

function validatePhone(input) {
    let digits = input.value.replace(/\D/g,'').substring(0,11); 
    input.value = digits;
    const hint = document.getElementById('phone_hint');
    if (digits === '') { resetHint(hint, input); return true; }
    if (!digits.startsWith('09') || digits.length < 11) { 
        setInputError(input); hint.className='field-hint error'; hint.textContent='11-digit no. starting with 09'; return false; 
    }
    setInputOk(input); hint.className = 'field-hint success'; hint.textContent = ''; return true;
}

function setInputError(input) { if(input) input.style.borderColor='#dc2626'; }
function setInputOk(input)    { if(input) input.style.borderColor='#16a34a'; }
function resetHint(hint, input) { if(hint){hint.className='field-hint';hint.textContent='';} if(input){input.style.borderColor='';} }
</script>
</body>
</html>