<?php
include('dastaff_header.php');
require_once '../includes/config.php';

$associations = $conn->query("SELECT id, name, province, municipality, barangay FROM associations ORDER BY name");

$search_field  = $_GET['search_field']   ?? 'All';
$search_term   = $_GET['search_term']    ?? '';
$type_filter   = $_GET['typeDropdown']   ?? '';
$status_filter = $_GET['statusDropdown'] ?? '';
$from_date     = $_GET['from_date']      ?? '';
$to_date       = $_GET['to_date']        ?? '';
$field_changed = $_GET['field_changed']  ?? '0';

$where = [];

if ($search_field !== 'All' && $field_changed !== '1') {
    if (!empty($search_term)) {
        $clean_term = $conn->real_escape_string(trim($search_term));
        if ($search_field === 'name') {
            $where[] = "m.machine_name LIKE '%$clean_term%'";
        } elseif ($search_field === 'association') {
            $where[] = "a.name LIKE '%$clean_term%'";
        } elseif ($search_field === 'address') {
            $parts = array_map('trim', explode(',', $search_term));
            if (count($parts) > 1 && !empty($parts[1])) {
                $safeBrgy = $conn->real_escape_string($parts[0]);
                $safeMuni = $conn->real_escape_string($parts[1]);
                $where[] = "a.barangay LIKE '%$safeBrgy%' AND a.municipality LIKE '%$safeMuni%'";
            } else {
                $where[] = "(a.barangay LIKE '%$clean_term%' OR a.municipality LIKE '%$clean_term%')";
            }
        }
    }

    if ($search_field === 'type' && !empty($type_filter)) {
        $where[] = "m.type='" . $conn->real_escape_string($type_filter) . "'";
    }

    if ($search_field === 'Status') {
        if (!empty($status_filter)) {
            $where[] = "m.status='" . $conn->real_escape_string($status_filter) . "'";
        }
        if (!empty($from_date) && !empty($to_date)) {
            $where[] = "DATE(m.created_at) BETWEEN '" . $conn->real_escape_string($from_date) . "' AND '" . $conn->real_escape_string($to_date) . "'";
        } elseif (!empty($from_date)) {
            $where[] = "DATE(m.created_at) >= '" . $conn->real_escape_string($from_date) . "'";
        } elseif (!empty($to_date)) {
            $where[] = "DATE(m.created_at) <= '" . $conn->real_escape_string($to_date) . "'";
        }
    }

    if ($search_field === 'date_registered') {
        if (!empty($from_date) && !empty($to_date)) {
            $where[] = "DATE(m.created_at) BETWEEN '" . $conn->real_escape_string($from_date) . "' AND '" . $conn->real_escape_string($to_date) . "'";
        } elseif (!empty($from_date)) {
            $where[] = "DATE(m.created_at) >= '" . $conn->real_escape_string($from_date) . "'";
        } elseif (!empty($to_date)) {
            $where[] = "DATE(m.created_at) <= '" . $conn->real_escape_string($to_date) . "'";
        }
    }
}

$where_sql = !empty($where) ? "WHERE " . implode(" AND ", $where) : '';

$sql = "SELECT m.*, 
               a.name AS association_name, 
               a.province, 
               a.municipality, 
               a.barangay
        FROM machines m 
        LEFT JOIN associations a ON m.association_id = a.id
        $where_sql
        ORDER BY m.created_at DESC";

$machines = $conn->query($sql);
$record_count = $machines ? $machines->num_rows : 0;

$printFromFormatted = (!empty($from_date)) ? date('F j, Y', strtotime($from_date)) : '';
$printToFormatted   = (!empty($to_date)) ? date('F j, Y', strtotime($to_date)) : '';
$hasDateRange       = ($search_field === 'Status' || $search_field === 'date_registered') && !empty($from_date) && !empty($to_date);
?>
<!DOCTYPE html>
<html lang="en-US">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Machines | AMRMS</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.css">
<style>
    .main-content { height: auto !important; padding: 50px 20px; }
    h2 { font-size: 28px; color: #2d7a2d; font-weight: bold; text-align: center; text-shadow: 1px 1px 3px rgba(14,4,4,0.7); margin: 10px 0 0 0; padding-bottom: 1px; }
    
    form.search-form { display: flex; align-items: center; flex-wrap: wrap; gap: 10px; margin-top: 15px; }
    select, input[type="text"], input[type="date"], button { padding: 8px 12px; font-size: 13px; border: 1px solid #ccc; border-radius: 6px; }
    form.search-form button { background-color: #2d7a2d; color: white; border: none; cursor: pointer; transition: background-color 0.3s, opacity 0.3s; }
    form.search-form button:hover:not(:disabled) { background-color: #256725; }
    form.search-form button:disabled { background-color: #a0a0a0; cursor: not-allowed; opacity: 0.6; }
    
    .flatpickr-input { padding: 8px 12px !important; font-size: 14px !important; border: 1px solid #ccc !important; border-radius: 6px !important; background: white !important; color: #333 !important; cursor: pointer !important; width: 130px !important; box-sizing: border-box !important; }
    .flatpickr-input:focus { outline: none !important; border-color: #2d7a2d !important; }
    label { color: #000000; font-weight: bold; font-size: 14px; margin-right: 5px; text-shadow: 1px 1px 3px rgba(14,4,4,0.7); }
    
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
    
    table th:nth-child(1), table td:nth-child(1) { width: 90px;  white-space: nowrap; }
    table th:nth-child(2), table td:nth-child(2) { width: 110px; white-space: nowrap; }
    table th:nth-child(3), table td:nth-child(3) { width: 120px; }
    table th:nth-child(4), table td:nth-child(4) { width: 140px; }
    table th:nth-child(5), table td:nth-child(5) { width: 80px;  }
    table th:nth-child(6), table td:nth-child(6) { width: 130px; }
    table th:nth-child(7), table td:nth-child(7) { width: 110px; }
    table th:nth-child(8), table td:nth-child(8) { width: 180px; word-break: break-word; }
    table th:nth-child(9), table td:nth-child(9) { width: 110px; }
    
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
    tr.table-active { background-color: #d9fdd9 !important; }
    img.machine-img { width: 45px; height: 45px; border-radius: 6px; object-fit: cover; display: block; margin: 0 auto; }
    
    .view-container { display: flex; justify-content: center; align-items: center; gap: 10px; margin-top: 20px; }
    .view-container button { background-color: #2d7a2d; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-size: 15px; transition: .2s; display: inline-flex; align-items: center; gap: 6px; font-weight: 600; }
    .view-container button:hover:not(:disabled) { background-color: #1a5c1a; transform: scale(1.03); }

    .preparedBy, .print-subtitle, .report-date { display: none;}

    /* ── Modal Overlay ── */
    .modal-overlay {
      display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55);
      z-index:9999; justify-content:center; align-items:center; backdrop-filter:blur(3px);
    }
    .modal-overlay.active { display:flex; }

    .modal-box {
      background:white; width:680px; max-width:96%;
      max-height:92vh; overflow-y:auto;
      border-radius:14px; box-shadow:0 20px 40px rgba(0,0,0,0.2);
    }
    .modal-box::-webkit-scrollbar { width:6px; }
    .modal-box::-webkit-scrollbar-thumb { background:#2d7a2d; border-radius:4px; }

    /* ── Modal Header ── */
    .modal-head {
      background:linear-gradient(135deg,#2d7a2d,#1a5c1a);
      padding:18px 24px; border-radius:14px 14px 0 0;
      display:flex; justify-content:space-between; align-items:center;
      position:sticky; top:0; z-index:10;
    }
    .modal-head h3 { color:white; font-size:18px; font-weight:700; margin:0; }
    .modal-head p  { color:rgba(255,255,255,0.8); font-size:12px; margin:3px 0 0; }

    /* ── Modal Body ── */
    .modal-bd { padding:20px 24px; }

    .m-sec {
      font-size:11px; font-weight:700; color:#6b7280; text-transform:uppercase;
      letter-spacing:.5px; margin:0 0 10px;
      display:flex; align-items:center; gap:8px;
    }
    .m-sec::after { content:''; flex:1; height:1px; background:#e5e7eb; }

    .m-row-2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px; }
    .m-row-1 { display:grid; grid-template-columns:1fr; gap:12px; margin-bottom:14px; }

    .m-grp { display:flex; flex-direction:column; gap:4px; }
    .m-grp label {
      font-size:12px; font-weight:700; color:#374151;
      text-shadow:none; text-transform:uppercase; letter-spacing:0.3px;
    }
    .m-grp label .req { color:#dc2626; }

    .m-wrap { position:relative; }
    .m-ico {
      position:absolute; left:11px; top:50%;
      transform:translateY(-50%); color:#9ca3af; font-size:13px; pointer-events:none;
    }

    .m-grp input,
    .m-grp select,
    .m-grp textarea {
      width:100%; padding:9px 12px 9px 34px;
      border:2px solid #e5e7eb; border-radius:7px;
      font-size:13px; font-family:inherit; color:#1f2937;
      transition:border-color .2s; background:white; box-sizing:border-box;
    }
    .m-grp input:focus,
    .m-grp select:focus,
    .m-grp textarea:focus {
      outline:none; border-color:#2d7a2d;
      box-shadow:0 0 0 3px rgba(45,122,45,.1);
    }

    .inline-error { color: #dc2626; font-size: 12px; margin-top: 4px; display: none; font-weight: 500; }

    /* ── Image Picker Field ── */
    .image-picker-box {
      width: 100%;
      height: 160px;
      border: 2px dashed #cbd5e1;
      border-radius: 12px;
      background: #f8fafc;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      cursor: pointer;
      position: relative;
      overflow: hidden;
      transition: all 0.2s ease-in-out;
    }
    .image-picker-box:hover {
      border-color: #2d7a2d;
      background: #f1f5f9;
    }
    .image-picker-box .placeholder-content {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 6px;
      color: #64748b;
    }
    .image-picker-box .placeholder-content i {
      font-size: 28px;
      color: #94a3b8;
    }
    .image-picker-box .placeholder-content span {
      font-size: 13px;
      font-weight: 600;
    }
    .image-picker-box img#imgPreview {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: none;
      position: absolute;
      inset: 0;
    }

    .m-hr { border:none; border-top:2px solid #f0f0f0; margin:6px 0 14px; }

    /* ── Modal Footer ── */
    .m-foot {
      padding:14px 24px; background:#f9fafb; border-top:1px solid #e5e7eb;
      display:flex; gap:10px; justify-content:flex-end; border-radius:0 0 14px 14px;
    }
    .m-cancel {
      padding:9px 20px; background:white; color:#6b7280;
      border:2px solid #e5e7eb; border-radius:6px; font-size:14px; font-weight:600; cursor:pointer;
    }
    .m-cancel:hover { background:#f9fafb; }
    .m-submit {
      padding:9px 22px; background:#2d7a2d; color:white; border:none;
      border-radius:6px; font-size:14px; font-weight:600; cursor:pointer;
      display:flex; align-items:center; gap:6px;
      transition: opacity 0.2s, background-color 0.2s;
    }
    .m-submit:hover:not(:disabled) { background:#256725; }
    .m-submit:disabled {
      background-color: #a0a0a0;
      cursor: not-allowed;
      opacity: 0.6;
    }

    /* ── Response Popup Modal (Account Settings Style) ── */
    .modal {
      display: none;
      position: fixed;
      z-index: 10000;
      inset: 0;
      background: rgba(0,0,0,0.4);
      backdrop-filter: blur(4px);
      justify-content: center;
      align-items: center;
    }
    .about-popup-content {
      background: #fff;
      border-radius: 16px;
      padding: 30px 25px;
      width: 90%;
      max-width: 400px;
      text-align: center;
      box-shadow: 0 8px 25px rgba(0,0,0,0.2);
      position: relative;
      animation: fadeIn 0.25s ease-in-out;
    }
    .about-popup-content p {
      font-size: 16px;
      font-weight: 600;
      margin-bottom: 20px;
      color: #333;
    }
    .about-popup-content button {
      background-color: #2d7a2d;
      color: white;
      border: none;
      padding: 8px 30px;
      cursor: pointer;
      border-radius: 6px;
      font-size: 16px;
      font-weight: 600;
      transition: background-color 0.3s;
    }
    .about-popup-content button:hover {
      background-color: #256725;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-15px); }
      to { opacity: 1; transform: translateY(0); }
    }

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

    @media(max-width:640px) { 
      .main-content { padding: 10px; } 
      .m-row-2 { grid-template-columns:1fr; }
      .modal-box { width:100%; border-radius:14px 14px 0 0; }
    }
</style>
</head>
<body>
<div class="main-content">

  <h2>List of Machines</h2>

  <?php if ($hasDateRange): ?>
      <div class="print-subtitle">From <?= htmlspecialchars($printFromFormatted) ?> to <?= htmlspecialchars($printToFormatted) ?></div>
  <?php endif; ?>

  <!-- Search form -->
  <form method="GET" action="" id="searchForm" class="search-form">
    <input type="hidden" name="field_changed" id="field_changed" value="0">

    <select name="search_field" id="search_field" onchange="handleFieldChange()">
      <option value="All"             <?= ($search_field==='All')             ?'selected':'' ?>>All</option>
      <option value="type"            <?= ($search_field==='type')            ?'selected':'' ?>>Machine Type</option>
      <option value="name"            <?= ($search_field==='name')            ?'selected':'' ?>>Machine Name</option>
      <option value="association"     <?= ($search_field==='association')     ?'selected':'' ?>>Association Name</option>
      <option value="address"         <?= ($search_field==='address')         ?'selected':'' ?>>Address</option>
      <option value="Status"          <?= ($search_field==='Status')          ?'selected':'' ?>>Status</option>
      <option value="date_registered" <?= ($search_field==='date_registered') ?'selected':'' ?>>Registered Date</option>
    </select>

    <select name="typeDropdown" id="typeDropdown" style="display:none;" onchange="validateFormState()">
      <option value="" disabled selected hidden>Select Type</option>
      <option value="Harvester" <?= ($type_filter=='Harvester')?'selected':'' ?>>Harvester</option>
      <option value="Tractor"   <?= ($type_filter=='Tractor')  ?'selected':'' ?>>Tractor</option>
    </select>

    <input type="text" name="search_term" id="textInput" placeholder="Enter search..."
           value="<?= htmlspecialchars($search_term) ?>" style="display:none;" oninput="handleInputClear()">

    <select name="statusDropdown" id="statusDropdown" style="display:none;" onchange="validateFormState()">
      <option value="" disabled selected hidden>Select Status</option>
      <option value="Active"            <?= ($status_filter==='Active')           ?'selected':'' ?>>Active</option>
      <option value="Under Maintenance" <?= ($status_filter==='Under Maintenance')?'selected':'' ?>>Under Maintenance</option>
      <option value="Damaged"           <?= ($status_filter==='Damaged')          ?'selected':'' ?>>Damaged</option>
    </select>

    <label for="from_date_display" id="from_label" style="display:none;">From</label>
    <input type="text"   id="from_date_display" placeholder="mm/dd/yyyy" readonly style="display:none; width:130px;">
    <input type="hidden" name="from_date" id="from_date" value="<?= htmlspecialchars($from_date) ?>">

    <label for="to_date_display" id="to_label" style="display:none;">To</label>
    <input type="text"   id="to_date_display" placeholder="mm/dd/yyyy" readonly style="display:none; width:130px;">
    <input type="hidden" name="to_date" id="to_date" value="<?= htmlspecialchars($to_date) ?>">

    <button type="submit" id="searchBtn" style="display:none;" disabled>Search</button>

  </form>

  <div class="report-date">Report Date: <?= date('F j, Y g:i A') ?></div>

  <!-- Table -->
  <div class="table-container" id="printSection">
    <table>
      <thead>
        <tr>
          <th>Machine ID</th>
          <th>Registered Date</th>
          <th>Machine Type</th>
          <th>Machine Name</th>
          <th>Image</th>
          <th>Association</th>
          <th>Rent Amount/ha</th>
          <th>Address</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php
        if ($record_count > 0) {
          while ($row = $machines->fetch_assoc()) {
            $address = trim($row['barangay'].', '.$row['municipality'].', '.$row['province'], ', ');
            $price = number_format((float)$row['price_per_hectare'], 2);
            echo "<tr class='clickable-row' data-id='{$row['id']}'>
              <td>{$row['id']}</td>
              <td>" . date('m/d/Y', strtotime($row['created_at'])) . "</td>
              <td>" . htmlspecialchars($row['type']) . "</td>
              <td>" . htmlspecialchars($row['machine_name']) . "</td>
              <td><img src='{$row['image_path']}' class='machine-img'></td>
              <td>" . htmlspecialchars($row['association_name'] ?? '') . "</td>
              <td>₱{$price}</td>
              <td>" . htmlspecialchars($address) . "</td>
              <td><span style='font-weight:bold;'>" . htmlspecialchars($row['status']) . "</span></td>
            </tr>";
          }
        } else {
          echo "<tr><td colspan='9' style='text-align:center; padding:20px; color:#555;'>No records found</td></tr>";
        }
        ?>
      </tbody>
    </table>

    <div class="preparedBy">
        Prepared By:
        <span style="font-weight:bold;">DA Staff</span>
    </div>
  </div>

  <div class="view-container">
    <button id="addBtn">Add</button>
    <button id="printBtn" <?= ($record_count === 0) ? 'disabled' : '' ?>>Print</button>
  </div>

</div>

<!-- ══ ADD MACHINE MODAL ══ -->
<div class="modal-overlay" id="addModal">
  <div class="modal-box">

    <!-- Header -->
    <div class="modal-head">
      <div>
        <h3><i class="fas fa-tractor"></i>&nbsp; Add Machine</h3>
        <p>Fill in the details to register a new machine</p>
      </div>
    </div>

    <!-- Body -->
    <div class="modal-bd">
      <form action="machine_add_process.php" method="POST" enctype="multipart/form-data" id="addMachineForm">

        <!-- Machine Details -->
        <div class="m-sec"><i class="fas fa-info-circle"></i> Machine Details</div>

        <!-- Row 1: Machine Name & Machine Type -->
        <div class="m-row-2">
          <div class="m-grp">
            <label>Machine Name <span class="req">*</span></label>
            <div class="m-wrap">
              <i class="fas fa-cog m-ico"></i>
              <input type="text" name="machine_name" id="addMachineName" placeholder="e.g. Yanmar L4208" required>
            </div>
            <div class="inline-error" id="machineExistsError">Machine already exists</div>
          </div>

          <div class="m-grp">
            <label>Machine Type <span class="req">*</span></label>
            <div class="m-wrap">
              <i class="fas fa-tag m-ico"></i>
              <select name="type" id="addMachineType" required>
                <option value="" disabled selected hidden>Select Type</option>
                <option value="Tractor">Tractor</option>
                <option value="Harvester">Harvester</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Row 2: Machine Image -->
        <div class="m-row-1">
          <div class="m-grp">
            <label>Machine Image <span class="req">*</span></label>
            <input type="file" name="image" id="machineImageInput" accept="image/*" required style="display:none;" onchange="previewImage(this)">
            
            <div class="image-picker-box" onclick="document.getElementById('machineImageInput').click();">
              <div class="placeholder-content" id="imgPlaceholder">
                <i class="fas fa-cloud-arrow-up"></i>
                <span>Click to upload machine image</span>
              </div>
              <img id="imgPreview" src="" alt="Machine Preview">
            </div>
          </div>
        </div>

        <hr class="m-hr">

        <!-- Association -->
        <div class="m-sec"><i class="fas fa-handshake"></i> Association</div>

        <div class="m-row-1">
          <div class="m-grp">
            <label>Association <span class="req">*</span></label>
            <div class="m-wrap">
              <i class="fas fa-users m-ico"></i>
              <select name="association_id" id="associationSelect" required>
                <option value="" disabled selected hidden>Select Association</option>
                <?php
                if ($associations && $associations->num_rows > 0) {
                    $associations->data_seek(0);
                    while ($assoc = $associations->fetch_assoc()):
                    ?>
                      <option value="<?= $assoc['id'] ?>">
                        <?= htmlspecialchars($assoc['name']) ?>
                      </option>
                    <?php 
                    endwhile; 
                }
                ?>
              </select>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="m-foot" style="margin:20px -24px -20px;border-radius:0 0 14px 14px;">
          <button type="button" class="m-submit" id="addMachineSubmitBtn" disabled onclick="submitAddMachine()">
             Add Machine
          </button>
          <button type="button" class="m-cancel" onclick="closeAddModal()">Close</button>
        </div>

      </form>
    </div>
  </div>
</div>

<!-- ══ RESPONSE POPUP MODAL (ACCOUNT SETTINGS STYLE) ══ -->
<div id="responseModal" class="modal">
  <div class="about-popup-content">
    <p id="responseModalText" style="font-weight:bold;"></p>
    <button type="button" id="responseOkBtn" onclick="closeResponseModal()">OK</button>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.js"></script>
<script>
let wasPreviouslyPopulated = false;
let shouldReloadOnOk = false;
let machineNameExists = false;

function formatLocalDate(dateObj) {
    const year = dateObj.getFullYear();
    const month = String(dateObj.getMonth() + 1).padStart(2, '0');
    const day = String(dateObj.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

/* ── Response Modal Functions ── */
function showResponseModal(msg, isSuccess = false) {
    shouldReloadOnOk = isSuccess; // Flag to reload strictly on clicking OK
    document.getElementById('responseModalText').textContent = msg;
    document.getElementById('responseModal').style.display = 'flex';
}

function closeResponseModal() {
    document.getElementById('responseModal').style.display = 'none';
    if (shouldReloadOnOk) {
        shouldReloadOnOk = false;
        window.location.reload(); // Reloads strictly after clicking OK
    }
}

/* ── Search logic ── */
function handleFieldChange() {
    const selectedField = document.getElementById('search_field').value;
    window.location.href = window.location.pathname + '?search_field=' + encodeURIComponent(selectedField);
}

function validateFormState() {
    const field     = document.getElementById('search_field').value;
    const textVal   = document.getElementById('textInput').value.trim();
    const typeVal   = document.getElementById('typeDropdown').value;
    const statusVal = document.getElementById('statusDropdown').value;
    const fromVal   = document.getElementById('from_date').value;
    const toVal     = document.getElementById('to_date').value;
    const searchBtn = document.getElementById('searchBtn');

    let isValid = false;

    if (['name', 'association', 'address'].includes(field)) {
        isValid = textVal.length > 0;
        if (textVal.length > 0) wasPreviouslyPopulated = true;
    } else if (field === 'type') {
        isValid = typeVal !== '';
    } else if (field === 'Status') {
        isValid = statusVal !== '' || (fromVal !== '' && toVal !== '');
    } else if (field === 'date_registered') {
        isValid = fromVal !== '' && toVal !== '';
    }

    searchBtn.disabled = !isValid;
}

function handleInputClear() {
    const textVal = document.getElementById('textInput').value.trim();
    validateFormState();
    
    if (textVal === '' && wasPreviouslyPopulated) {
        wasPreviouslyPopulated = false;
        window.location.href = window.location.pathname + '?search_field=' + encodeURIComponent(document.getElementById('search_field').value);
    }
}

function toggleInputs() {
    const field     = document.getElementById('search_field').value;
    const isStatus  = field === 'Status';
    const isDate    = field === 'date_registered';
    const isType    = field === 'type';
    const isText    = ['name', 'association', 'address'].includes(field);

    const textInput = document.getElementById('textInput');
    textInput.style.display = isText ? 'inline-block' : 'none';
    
    if (field === 'address') {
        textInput.placeholder = "barangay, municipality";
    } else {
        textInput.placeholder = "Enter search...";
    }

    document.getElementById('typeDropdown').style.display      = isType             ? 'inline-block' : 'none';
    document.getElementById('statusDropdown').style.display    = isStatus           ? 'inline-block' : 'none';
    document.getElementById('from_date_display').style.display = (isStatus||isDate) ? 'inline-block' : 'none';
    document.getElementById('to_date_display').style.display   = (isStatus||isDate) ? 'inline-block' : 'none';
    document.getElementById('from_label').style.display        = (isStatus||isDate) ? 'inline-block' : 'none';
    document.getElementById('to_label').style.display          = (isStatus||isDate) ? 'inline-block' : 'none';
    document.getElementById('searchBtn').style.display         = field !== 'All'    ? 'inline-block' : 'none';

    if (textInput.value.trim() !== '') wasPreviouslyPopulated = true;
    validateFormState();
}

/* ── Flatpickr initialization ── */
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
            wasPreviouslyPopulated = true;
        } else {
            document.getElementById('from_date').value = '';
            window.fpTo.set('minDate', null);
            if (wasPreviouslyPopulated) {
                window.location.href = window.location.pathname + '?search_field=' + encodeURIComponent(document.getElementById('search_field').value);
            }
        }
        validateFormState();
    }
});

window.fpTo = flatpickr('#to_date_display', {
    dateFormat:'m/d/Y', 
    allowInput:false,
    onChange(dates) {
        if (dates.length) {
            document.getElementById('to_date').value = formatLocalDate(dates[0]);
            wasPreviouslyPopulated = true;
        } else {
            document.getElementById('to_date').value = '';
            if (wasPreviouslyPopulated) {
                window.location.href = window.location.pathname + '?search_field=' + encodeURIComponent(document.getElementById('search_field').value);
            }
        }
        validateFormState();
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

window.onload = toggleInputs;

/* ── Modal Controls & Reset Form ── */
function resetAddForm() {
    const form = document.getElementById('addMachineForm');
    form.reset();
    document.getElementById('imgPreview').style.display = 'none';
    document.getElementById('imgPreview').src = '';
    document.getElementById('imgPlaceholder').style.display = 'flex';
    document.getElementById('machineExistsError').style.display = 'none';
    machineNameExists = false;
    validateAddMachineForm();
}

function openAddModal()  { document.getElementById('addModal').classList.add('active'); document.body.style.overflow='hidden'; }
function closeAddModal() { 
    document.getElementById('addModal').classList.remove('active'); 
    document.body.style.overflow='';
    resetAddForm();
}

document.getElementById('addBtn').addEventListener('click', openAddModal);
document.getElementById('addModal').addEventListener('click', function(e) { if (e.target===this) closeAddModal(); });
document.addEventListener('keydown', e => { 
    if (e.key === 'Escape') {
        closeAddModal();
        closeResponseModal();
    }
});

/* ── Image preview ── */
function previewImage(input) {
    const placeholder = document.getElementById('imgPlaceholder');
    const img  = document.getElementById('imgPreview');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => { 
          img.src = e.target.result; 
          img.style.display = 'block'; 
          placeholder.style.display = 'none';
        };
        reader.readAsDataURL(input.files[0]);
    } else {
        img.style.display = 'none';
        placeholder.style.display = 'flex';
        img.src = '';
    }
    validateAddMachineForm();
}

/* ── Check Machine Existence Logic ── */
function checkMachineExists() {
    const name = document.getElementById('addMachineName').value.trim();
    const errorElem = document.getElementById('machineExistsError');

    if (name === '') {
        machineNameExists = false;
        errorElem.style.display = 'none';
        validateAddMachineForm();
        return;
    }

    fetch('check_machine_exists.php?name=' + encodeURIComponent(name))
        .then(res => res.json())
        .then(data => {
            if (data.exists) {
                machineNameExists = true;
                errorElem.style.display = 'block';
            } else {
                machineNameExists = false;
                errorElem.style.display = 'none';
            }
            validateAddMachineForm();
        })
        .catch(() => {
            validateAddMachineForm();
        });
}

/* ── Form Input Validation ── */
function validateAddMachineForm() {
    const name = document.getElementById('addMachineName').value.trim();
    const type = document.getElementById('addMachineType').value;
    const imageInput = document.getElementById('machineImageInput');
    const assoc = document.getElementById('associationSelect').value;
    const submitBtn = document.getElementById('addMachineSubmitBtn');

    const hasImage = imageInput.files && imageInput.files.length > 0;

    submitBtn.disabled = !(name !== '' && type !== '' && hasImage && assoc !== '' && !machineNameExists);
}

document.getElementById('addMachineName').addEventListener('input', checkMachineExists);
document.getElementById('addMachineType').addEventListener('change', validateAddMachineForm);
document.getElementById('associationSelect').addEventListener('change', validateAddMachineForm);

/* ── AJAX Submission Handler ── */
function submitAddMachine() {
    const form = document.getElementById('addMachineForm');
    const formData = new FormData(form);

    fetch(form.action, {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            closeAddModal(); // Close add modal before opening notice
            showResponseModal('Machine added successfully.', true); // Shows modal popup with reload enabled
        } else {
            showResponseModal(data.message || 'Failed to add machine.', false);
        }
    })
    .catch(() => {
        showResponseModal('Failed to add machine. Please check your connection.', false);
    });
}

/* ── Table Interactions ── */
document.querySelectorAll('.clickable-row').forEach(row => {
    row.addEventListener('click', () => {
        document.querySelectorAll('.clickable-row').forEach(r => r.classList.remove('table-active'));
        row.classList.add('table-active');
    });
    row.addEventListener('dblclick', () => {
        const id = row.getAttribute('data-id');
        if (id) window.location.href = 'machine_profile.php?id=' + id;
    });
});

document.getElementById('printBtn').addEventListener('click', () => window.print());
</script>
</body>
</html>