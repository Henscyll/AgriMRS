<?php
session_start();
require_once '../includes/db_connection.php';
include('operator_dashboard.php');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'operator') {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

$operator_query = $conn->prepare("
    SELECT o.*, a.name as association_name 
    FROM operators o
    JOIN associations a ON o.association_id = a.id
    WHERE o.user_id = ? AND o.status = 'Active'
");
$operator_query->bind_param("i", $user_id);
$operator_query->execute();
$operator_result = $operator_query->get_result();

if ($operator_result->num_rows === 0) {
    die("❌ Operator account not found or inactive. Please contact your association.");
}

$operator_data    = $operator_result->fetch_assoc();
$operator_id      = $operator_data['id'];
$operator_name    = $operator_data['name'];
$association_name = $operator_data['association_name'];
$association_id   = $operator_data['association_id'];
$operator_query->close();

$message = "";
$error   = "";

/* ── MARK AS COMPLETED ── */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['complete_booking'])) {
    $booking_id = (int)$_POST['booking_id'];
    $verify = $conn->prepare("
        SELECT b.id FROM bookings b
        JOIN machine_operators mo ON b.machine_id = mo.machine_id
        WHERE b.id = ? AND mo.operator_id = ? AND mo.status = 'Active' AND b.status = 'Approved'
    ");
    $verify->bind_param("ii", $booking_id, $operator_id);
    $verify->execute();
    if ($verify->get_result()->num_rows > 0) {
        $upd = $conn->prepare("UPDATE bookings SET status = 'Completed', updated_at = NOW() WHERE id = ?");
        $upd->bind_param("i", $booking_id);
        $message = $upd->execute() ? "✅ Work assignment marked as completed!" : "❌ Failed: " . $upd->error;
        $upd->close();
    } else {
        $error = "❌ Invalid booking or not assigned to you!";
    }
    $verify->close();
}

/* ── FILTER PARAMS ── */
$search_field  = $_GET['search_field']   ?? 'All';
$search_term   = $_GET['search_term']    ?? '';
$status_filter = $_GET['status']         ?? '';
$type_filter   = $_GET['machine_type']   ?? '';
$date_from     = $_GET['date_from']      ?? '';
$date_to       = $_GET['date_to']        ?? '';
$field_changed = $_GET['field_changed']  ?? '0';

/* ── BUILD WHERE ── */
$where = ["mo.operator_id = ?", "mo.status = 'Active'", "b.status IN ('Approved','Completed')"];
$params = [$operator_id];
$types  = "i";

if ($field_changed !== '1') {
    if ($search_field === 'farmer_name' && !empty($search_term)) {
        $where[] = "CONCAT(f.first_name, ' ', COALESCE(f.middle_name,''), ' ', f.last_name) LIKE '%" . $conn->real_escape_string($search_term) . "%'";
    } elseif ($search_field === 'machine_name' && !empty($search_term)) {
        $where[] = "m.machine_name LIKE '%" . $conn->real_escape_string($search_term) . "%'";
    } elseif ($search_field === 'machine_type' && !empty($type_filter)) {
        $where[] = "m.type = '" . $conn->real_escape_string($type_filter) . "'";
    } elseif ($search_field === 'status' && !empty($status_filter)) {
        $where[] = "b.status = '" . $conn->real_escape_string($status_filter) . "'";
    } elseif ($search_field === 'booking_date') {
        if (!empty($date_from)) $where[] = "DATE(b.booking_date) >= '" . $conn->real_escape_string($date_from) . "'";
        if (!empty($date_to))   $where[] = "DATE(b.booking_date) <= '" . $conn->real_escape_string($date_to) . "'";
    }
}

$where_sql = implode(" AND ", $where);

/* ── FETCH ── */
$stmt = $conn->prepare("
    SELECT b.id AS booking_id, b.booking_date, b.farm_location, b.farm_size, b.notes, b.status, b.created_at,
           m.id AS machine_id, m.machine_name, m.type AS machine_type, m.image_path,
           CONCAT(f.first_name, ' ', COALESCE(f.middle_name, ''), ' ', f.last_name) AS farmer_name,
           f.phone AS farmer_phone, f.email AS farmer_email,
           f.barangay, f.municipality, f.province
    FROM bookings b
    JOIN machines m ON b.machine_id = m.id
    JOIN machine_operators mo ON m.id = mo.machine_id
    JOIN farmers f ON b.farmer_id = f.id
    WHERE $where_sql
    ORDER BY CASE WHEN b.status='Approved' THEN 1 ELSE 2 END, b.booking_date ASC
");
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$rows = [];
while ($r = $result->fetch_assoc()) $rows[] = $r;
$stmt->close();
$total = count($rows);

/* Pass rows as JSON for modal */
$rows_json = json_encode(array_column(
    array_map(fn($r) => [
        'id'           => $r['booking_id'],
        'status'       => $r['status'],
        'farmer_name'  => $r['farmer_name'],
        'farmer_phone' => $r['farmer_phone'],
        'farmer_email' => $r['farmer_email'],
        'machine_name' => $r['machine_name'],
        'machine_type' => $r['machine_type'],
        'booking_date' => $r['booking_date'],
        'farm_location'=> $r['farm_location'],
        'farm_size'    => $r['farm_size'],
        'barangay'     => $r['barangay'],
        'municipality' => $r['municipality'],
        'province'     => $r['province'],
        'notes'        => $r['notes'] ?? '',
        'created_at'   => $r['created_at'],
    ], $rows),
    null, 'id'
));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Work Assignments</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.css">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'Segoe UI',Arial,sans-serif; overflow:hidden; }

/* ── Scrollable area below header ── */
.main-scroll-container {
    position: fixed; top: 70px; left: 0; right: 0; bottom: 0;
    overflow-y: scroll; overflow-x: hidden;
}
.main-scroll-container::-webkit-scrollbar { width: 10px; }
.main-scroll-container::-webkit-scrollbar-track { background: rgba(255,255,255,.15); border-radius:10px; }
.main-scroll-container::-webkit-scrollbar-thumb { background:#2d7a2d; border-radius:10px; border:2px solid rgba(255,255,255,.3); }
.main-scroll-container::-webkit-scrollbar-thumb:hover { background:#1a5c1a; }

.container { max-width:1400px; margin:0 auto; padding:20px 20px 80px; }

/* ── Page title ── */
.page-title-bar {
    display:flex; justify-content:space-between; align-items:center;
    margin-bottom:16px; flex-wrap:wrap; gap:10px;
}
.page-title-bar h1 {
    font-size:24px; font-weight:700; color:#fff;
    text-shadow:0 2px 6px rgba(0,0,0,.45);
    display:flex; align-items:center; gap:10px;
}
.page-title-bar .sub {
    font-size:13px; color:rgba(255,255,255,.85);
    text-shadow:0 1px 3px rgba(0,0,0,.4); font-weight:normal; margin-left:4px;
}

/* ── Filter bar ── */
.search-form {
    margin-bottom:16px; display:flex; flex-wrap:wrap; align-items:center; gap:10px;
}
.search-form select,
.search-form input[type="text"],
.search-form button {
    padding:8px 12px; font-size:13px;
    border:1px solid #2d7a2d; border-radius:6px;
}
.search-form button { background-color:#2d7a2d; color:white; border:none; cursor:pointer; }
.search-form button:hover { background-color:#256725; }
.flatpickr-input {
    padding:8px 12px !important; font-size:13px !important;
    border:1px solid #2d7a2d !important; border-radius:6px !important;
    background:white !important; color:#333 !important;
    width:130px !important; box-sizing:border-box !important; cursor:pointer !important;
}
label.filter-lbl {
    color:#fff; font-weight:bold; font-size:14px;
    margin-right:2px; text-shadow:1px 1px 3px rgba(0,0,0,.5);
}
.total-count {
    margin-left:auto; color:#fff; font-weight:bold; font-size:14px;
    text-shadow:1px 1px 3px rgba(0,0,0,.5); white-space:nowrap;
}

/* ── Alert ── */
.alert { padding:10px 15px; border-radius:6px; margin-bottom:14px;
         display:flex; align-items:center; gap:8px; font-size:14px; }
.alert-success { background:#d1fae5; color:#065f46; border-left:4px solid #10b981; }
.alert-error   { background:#fee2e2; color:#991b1b; border-left:4px solid #ef4444; }

/* ── Table ── */
.table-container {
    border:1px solid #ddd; border-radius:8px;
    background-color:white; box-shadow:0 2px 5px rgba(0,0,0,.1);
    margin-bottom:10px; overflow:hidden;
}
.table-container table { width:100%; border-collapse:collapse; background-color:white; table-layout:fixed; }
.table-container thead { display:table; width:100%; table-layout:fixed; }
.table-container tbody {
    display:block; max-height:320px;
    overflow-y:auto; overflow-x:hidden; width:100%;
}
.table-container tbody::-webkit-scrollbar { width:8px; }
.table-container tbody::-webkit-scrollbar-track { background:#f1f1f1; border-radius:4px; }
.table-container tbody::-webkit-scrollbar-thumb { background:#2d7a2d; border-radius:4px; }
.table-container tbody tr { display:table; width:100%; table-layout:fixed; }
th, td { padding:11px 13px; text-align:left; border-bottom:1px solid #ddd; vertical-align:middle; }
th { background-color:#2d7a2d; color:white; font-weight:normal; font-size:13px; }
tbody tr { cursor:pointer; transition:background 0.15s; }
tbody tr:hover    { background-color:#f1f1f1; }
tbody tr.selected { background-color:#d9fdd9 !important; }

/* ── Cell content ── */
.machine-cell { display:flex; align-items:center; gap:9px; }
.machine-img  { width:40px; height:40px; border-radius:5px; object-fit:cover; border:1px solid #ddd; flex-shrink:0; }
.machine-name { font-weight:600; font-size:13px; color:#1f2937; }
.machine-type-badge {
    font-size:11px; padding:2px 6px;
    background:#dcfce7; color:#15803d; border-radius:8px; display:inline-block; margin-top:2px;
}
.farmer-cell  { font-size:13px; }
.farmer-name  { font-weight:600; color:#1f2937; }
.farmer-sub   { font-size:11px; color:#6b7280; margin-top:2px; }

.status-text { font-size:13px; font-weight:600; }
.status-Approved  { color:#92400e; }
.status-Completed { color:#065f46; }

/* ── Action buttons ── */
.action-buttons {
    display:flex; justify-content:center; gap:12px;
    margin-top:18px; margin-bottom:30px; flex-wrap:wrap;
}
.action-buttons button {
    padding:10px 22px; border-radius:6px; border:none;
    color:white; cursor:pointer; font-size:14px; font-weight:600;
    transition:0.2s; display:inline-flex; align-items:center; gap:8px;
    background-color:#2d7a2d;
}
.action-buttons button:not(:disabled):hover { background-color:#256725; transform:scale(1.03); }
.action-buttons button:disabled { background-color:#2d7a2d; opacity:0.45; cursor:not-allowed; transform:none !important; }
.btn-complete-action { background-color:#16a34a !important; }
.btn-complete-action:not(:disabled):hover { background-color:#15803d !important; }

/* ── Modal ── */
.modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:9999; justify-content:center; align-items:center; backdrop-filter:blur(3px); }
.modal-overlay.active { display:flex; }
.modal-box { background:white; width:540px; max-width:95%; max-height:90vh; overflow-y:auto; border-radius:14px; box-shadow:0 20px 40px rgba(0,0,0,.2); }
.modal-box::-webkit-scrollbar { width:5px; }
.modal-box::-webkit-scrollbar-thumb { background:#2d7a2d; border-radius:6px; }
.modal-head { background:linear-gradient(135deg,#2d7a2d,#1a5c1a); padding:20px 24px; border-radius:14px 14px 0 0; display:flex; justify-content:space-between; align-items:flex-start; }
.modal-head h3 { color:white; font-size:19px; font-weight:700; margin:0; }
.modal-head p  { color:rgba(255,255,255,.82); font-size:13px; margin:4px 0 0; }
.modal-x { width:30px; height:30px; background:rgba(255,255,255,.2); border:none; border-radius:50%; color:white; font-size:17px; cursor:pointer; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.modal-x:hover { background:rgba(255,255,255,.35); }
.modal-bd { padding:20px 22px; }
.m-sec { font-size:11px; font-weight:700; color:#6b7280; text-transform:uppercase; letter-spacing:.5px; margin-bottom:10px; margin-top:2px; display:flex; align-items:center; gap:8px; }
.m-sec::after { content:''; flex:1; height:1px; background:#e5e7eb; }
.detail-grid { display:grid; grid-template-columns:1fr 1fr; gap:9px; margin-bottom:14px; }
.detail-item { background:#f9fafb; border-radius:8px; padding:10px 12px; }
.detail-item.full { grid-column:1/-1; }
.detail-item .lbl { font-size:11px; color:#9ca3af; font-weight:700; text-transform:uppercase; letter-spacing:.4px; }
.detail-item .val { font-size:14px; color:#1f2937; font-weight:600; margin-top:3px; }
.badge-Approved  { background:#fef3c7; color:#92400e; padding:3px 10px; border-radius:20px; font-size:12px; font-weight:700; display:inline-block; }
.badge-Completed { background:#d1fae5; color:#065f46; padding:3px 10px; border-radius:20px; font-size:12px; font-weight:700; display:inline-block; }
.m-foot { padding:12px 22px; background:#f9fafb; border-top:1px solid #e5e7eb; display:flex; justify-content:flex-end; gap:10px; border-radius:0 0 14px 14px; }
.m-close-btn { padding:8px 22px; background:white; color:#6b7280; border:2px solid #e5e7eb; border-radius:6px; font-size:14px; font-weight:600; cursor:pointer; }
.m-close-btn:hover { background:#f9fafb; }
.m-print-btn { padding:8px 22px; background:#6b7280; color:white; border:none; border-radius:6px; font-size:14px; font-weight:600; cursor:pointer; display:flex; align-items:center; gap:6px; }
.m-print-btn:hover { background:#4b5563; }

/* ── Print ── */
@media print {
    body * { visibility:hidden; }
    #printArea, #printArea * { visibility:visible; }
    #printArea { position:absolute; top:0; left:0; width:100%; padding:30px; font-family:'Segoe UI',Arial,sans-serif; }
    .print-header { text-align:center; margin-bottom:24px; border-bottom:2px solid #2d7a2d; padding-bottom:16px; }
    .print-header h2 { color:#2d7a2d; margin:0 0 4px; font-size:22px; }
    .print-header p  { color:#6b7280; margin:0; font-size:13px; }
    .print-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px; }
    .print-item { background:#f9fafb; border:1px solid #e5e7eb; border-radius:6px; padding:10px 14px; }
    .print-item .lbl { font-size:10px; color:#9ca3af; font-weight:700; text-transform:uppercase; }
    .print-item .val { font-size:14px; color:#1f2937; font-weight:600; margin-top:2px; }
    .print-item.full { grid-column:1/-1; }
    .print-section-title { font-size:12px; font-weight:700; color:#6b7280; text-transform:uppercase; margin:16px 0 8px; border-bottom:1px solid #e5e7eb; padding-bottom:4px; }
    .print-footer { margin-top:30px; text-align:center; font-size:11px; color:#9ca3af; border-top:1px solid #e5e7eb; padding-top:12px; }
    .search-form, .action-buttons { display:none; }
}
</style>
</head>
<body>
<div class="main-scroll-container">
<div class="container">

  <?php if ($message): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <!-- ── Page title ── -->
  <div class="page-title-bar">
    <h1>
      <i class="fas fa-tasks" style="background:linear-gradient(135deg,#2d7a2d,#1a5c1a);padding:8px;border-radius:8px;font-size:18px;"></i>
      Work Assignments
      <span class="sub">— <?= htmlspecialchars($association_name) ?></span>
    </h1>
  </div>

  <!-- ── Filter bar ── -->
  <form class="search-form" method="GET" id="searchForm">
    <input type="hidden" name="field_changed" id="field_changed" value="0">

    <select name="search_field" id="search_field" onchange="handleFieldChange()">
      <option value="All"          <?= $search_field==='All'          ?'selected':'' ?>>All</option>
      <option value="farmer_name"  <?= $search_field==='farmer_name'  ?'selected':'' ?>>Farmer Name</option>
      <option value="machine_name" <?= $search_field==='machine_name' ?'selected':'' ?>>Machine Name</option>
      <option value="machine_type" <?= $search_field==='machine_type' ?'selected':'' ?>>Machine Type</option>
      <option value="status"       <?= $search_field==='status'       ?'selected':'' ?>>Status</option>
      <option value="booking_date" <?= $search_field==='booking_date' ?'selected':'' ?>>Booking Date</option>
    </select>

    <input type="text" name="search_term" id="textInput"
           placeholder="Type to search..."
           value="<?= htmlspecialchars($search_term) ?>" style="display:none;">

    <select name="machine_type" id="typeInput" style="display:none;">
      <option value="">All Types</option>
      <option value="Tractor"   <?= $type_filter==='Tractor'   ?'selected':'' ?>>Tractor</option>
      <option value="Harvester" <?= $type_filter==='Harvester' ?'selected':'' ?>>Harvester</option>
    </select>

    <select name="status" id="statusInput" style="display:none;">
      <option value="">All Statuses</option>
      <option value="Approved"  <?= $status_filter==='Approved'  ?'selected':'' ?>>Approved</option>
      <option value="Completed" <?= $status_filter==='Completed' ?'selected':'' ?>>Completed</option>
    </select>

    <label class="filter-lbl" id="from_label" style="display:none;">From</label>
    <input type="text" id="from_date_display" placeholder="mm/dd/yyyy" readonly style="display:none;">
    <input type="hidden" name="date_from" id="date_from" value="<?= htmlspecialchars($date_from) ?>">

    <label class="filter-lbl" id="to_label" style="display:none;">To</label>
    <input type="text" id="to_date_display" placeholder="mm/dd/yyyy" readonly style="display:none;">
    <input type="hidden" name="date_to" id="date_to" value="<?= htmlspecialchars($date_to) ?>">

    <button type="submit" id="searchBtn" style="display:none;">
      <i class="fas fa-search"></i> Search
    </button>

    <span class="total-count">Total Records: <?= $total ?></span>
  </form>

  <!-- ── Table ── -->
  <div class="table-container">
    <table>
      <thead>
        <tr>
          <th style="width:4%">#</th>
          <th style="width:22%">Machine</th>
          <th style="width:20%">Farmer</th>
          <th style="width:14%">Booking Date</th>
          <th style="width:15%">Location</th>
          <th style="width:10%">Farm Size</th>
          <th style="width:15%">Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($total > 0): $i = 1; foreach ($rows as $row): ?>
        <tr class="assignment-row"
            data-id="<?= $row['booking_id'] ?>"
            data-status="<?= htmlspecialchars($row['status']) ?>">
          <td><?= $i++ ?></td>
          <td>
            <div class="machine-cell">
              <img src="<?= !empty($row['image_path']) ? '../'.$row['image_path'] : '../images/default-tractor.jpg' ?>"
                   class="machine-img"
                   onerror="this.src='../images/default-tractor.jpg'">
              <div>
                <div class="machine-name"><?= htmlspecialchars($row['machine_name']) ?></div>
                <span class="machine-type-badge">
                  <i class="fas fa-<?= $row['machine_type']==='Tractor'?'tractor':'cogs' ?>"></i>
                  <?= htmlspecialchars($row['machine_type']) ?>
                </span>
              </div>
            </div>
          </td>
          <td>
            <div class="farmer-cell">
              <div class="farmer-name"><?= htmlspecialchars($row['farmer_name']) ?></div>
              <div class="farmer-sub"><i class="fas fa-phone" style="font-size:10px;margin-right:3px;"></i><?= htmlspecialchars($row['farmer_phone']) ?></div>
              <div class="farmer-sub"><i class="fas fa-map-pin" style="font-size:10px;margin-right:3px;"></i><?= htmlspecialchars($row['barangay']) ?>, <?= htmlspecialchars($row['municipality']) ?></div>
            </div>
          </td>
          <td>
            <i class="fas fa-calendar" style="color:#2d7a2d;margin-right:4px;font-size:12px;"></i>
            <?= date('M d, Y', strtotime($row['booking_date'])) ?>
          </td>
          <td style="font-size:12px;color:#374151;">
            <?= htmlspecialchars($row['farm_location'] ?: $row['barangay'].', '.$row['municipality']) ?>
          </td>
          <td><?= number_format($row['farm_size'],2) ?> ha</td>
          <td>
            <span class="status-text status-<?= $row['status'] ?>"><?= $row['status'] ?></span>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="7" style="text-align:center;padding:40px;color:#9ca3af;">
          <i class="fas fa-clipboard-list" style="font-size:28px;display:block;margin-bottom:8px;opacity:.3;"></i>
          No work assignments found
        </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- ── Action Buttons (outside table) ── -->
  <div class="action-buttons">
    <button id="btnComplete" disabled onclick="submitComplete()" class="btn-complete-action">
     Completed
    </button>
    <button id="btnView" disabled onclick="openModal()">
     View
    </button>
    <button id="btnPrint" disabled onclick="printSelected()">
      <i class="fas fa-print"></i> Print
    </button>
  </div>

  <!-- Hidden complete form -->
  <form method="POST" id="completeForm" style="display:none;">
    <input type="hidden" name="complete_booking" value="1">
    <input type="hidden" name="booking_id" id="completeBookingId">
  </form>

</div>
</div>

<!-- ── DETAIL MODAL ── -->
<div class="modal-overlay" id="detailModal">
  <div class="modal-box">
    <div class="modal-head">
      <div>
        <h3 id="modal-title">Work Assignment Details</h3>
        <p id="modal-subtitle">—</p>
      </div>
      <button class="modal-x" onclick="closeModal()"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-bd">
      <div class="m-sec"><i class="fas fa-tractor"></i> Machine</div>
      <div class="detail-grid">
        <div class="detail-item"><div class="lbl">Machine Name</div><div class="val" id="d-machine">—</div></div>
        <div class="detail-item"><div class="lbl">Type</div><div class="val" id="d-type">—</div></div>
      </div>
      <div class="m-sec"><i class="fas fa-user"></i> Farmer</div>
      <div class="detail-grid">
        <div class="detail-item"><div class="lbl">Name</div><div class="val" id="d-farmer">—</div></div>
        <div class="detail-item"><div class="lbl">Phone</div><div class="val" id="d-phone">—</div></div>
        <div class="detail-item full"><div class="lbl">Email</div><div class="val" id="d-email">—</div></div>
      </div>
      <div class="m-sec"><i class="fas fa-map-marker-alt"></i> Farm Details</div>
      <div class="detail-grid">
        <div class="detail-item"><div class="lbl">Booking Date</div><div class="val" id="d-date">—</div></div>
        <div class="detail-item"><div class="lbl">Status</div><div class="val" id="d-status">—</div></div>
        <div class="detail-item"><div class="lbl">Farm Size</div><div class="val" id="d-size">—</div></div>
        <div class="detail-item"><div class="lbl">Location</div><div class="val" id="d-location">—</div></div>
        <div class="detail-item"><div class="lbl">Barangay</div><div class="val" id="d-barangay">—</div></div>
        <div class="detail-item"><div class="lbl">Municipality</div><div class="val" id="d-muni">—</div></div>
      </div>
      <div id="d-notes-wrap" style="display:none;">
        <div class="m-sec"><i class="fas fa-sticky-note"></i> Notes</div>
        <div class="detail-item full"><div class="val" id="d-notes" style="font-weight:400;font-size:13px;color:#374151;"></div></div>
      </div>
    </div>
    <div class="m-foot">
      <button class="m-close-btn" onclick="closeModal()">Close</button>
      <button class="m-print-btn" onclick="printSelected()"><i class="fas fa-print"></i> Print</button>
    </div>
  </div>
</div>

<!-- ── Print Area ── -->
<div id="printArea" style="display:none;">
  <div class="print-header">
    <h2>Agricultural Machineries Reservation &amp; Monitoring System</h2>
    <p>Work Assignment Report — Printed: <span id="print-date"></span></p>
  </div>
  <div class="print-section-title">Machine</div>
  <div class="print-grid">
    <div class="print-item"><div class="lbl">Machine</div><div class="val" id="pr-machine">—</div></div>
    <div class="print-item"><div class="lbl">Type</div><div class="val" id="pr-type">—</div></div>
  </div>
  <div class="print-section-title">Farmer</div>
  <div class="print-grid">
    <div class="print-item"><div class="lbl">Name</div><div class="val" id="pr-farmer">—</div></div>
    <div class="print-item"><div class="lbl">Phone</div><div class="val" id="pr-phone">—</div></div>
    <div class="print-item full"><div class="lbl">Email</div><div class="val" id="pr-email">—</div></div>
  </div>
  <div class="print-section-title">Farm Details</div>
  <div class="print-grid">
    <div class="print-item"><div class="lbl">Booking Date</div><div class="val" id="pr-date">—</div></div>
    <div class="print-item"><div class="lbl">Status</div><div class="val" id="pr-status">—</div></div>
    <div class="print-item"><div class="lbl">Farm Size</div><div class="val" id="pr-size">—</div></div>
    <div class="print-item"><div class="lbl">Location</div><div class="val" id="pr-location">—</div></div>
    <div class="print-item"><div class="lbl">Barangay</div><div class="val" id="pr-barangay">—</div></div>
    <div class="print-item"><div class="lbl">Municipality</div><div class="val" id="pr-muni">—</div></div>
  </div>
  <div id="pr-notes-wrap" style="display:none;">
    <div class="print-section-title">Notes</div>
    <div class="print-item full"><div class="val" id="pr-notes">—</div></div>
  </div>
  <div class="print-footer">Agricultural Machineries Reservation &amp; Monitoring System — Operator: <?= htmlspecialchars($operator_name) ?></div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.js"></script>
<script>
const rowData = <?= $rows_json ?>;

/* ── Flatpickr ── */
window.fpFrom = flatpickr('#from_date_display', {
    dateFormat:'m/d/Y', allowInput:false,
    onChange:function(d){ document.getElementById('date_from').value = d.length ? d[0].getFullYear()+'-'+String(d[0].getMonth()+1).padStart(2,'0')+'-'+String(d[0].getDate()).padStart(2,'0'):''; }
});
window.fpTo = flatpickr('#to_date_display', {
    dateFormat:'m/d/Y', allowInput:false,
    onChange:function(d){ document.getElementById('date_to').value = d.length ? d[0].getFullYear()+'-'+String(d[0].getMonth()+1).padStart(2,'0')+'-'+String(d[0].getDate()).padStart(2,'0'):''; }
});
<?php if ($date_from): ?>fpFrom.setDate('<?= $date_from ?>', true, 'Y-m-d');<?php endif; ?>
<?php if ($date_to):   ?>fpTo.setDate('<?= $date_to ?>',   true, 'Y-m-d');<?php endif; ?>

/* ── Filter toggle ── */
function toggleInputs() {
    const f = document.getElementById('search_field').value;
    document.getElementById('textInput').style.display            = (f==='farmer_name'||f==='machine_name') ? 'inline-block':'none';
    document.getElementById('typeInput').style.display            = (f==='machine_type') ? 'inline-block':'none';
    document.getElementById('statusInput').style.display          = (f==='status')       ? 'inline-block':'none';
    document.getElementById('from_date_display').style.display    = (f==='booking_date') ? 'inline-block':'none';
    document.getElementById('to_date_display').style.display      = (f==='booking_date') ? 'inline-block':'none';
    document.getElementById('from_label').style.display           = (f==='booking_date') ? 'inline-block':'none';
    document.getElementById('to_label').style.display             = (f==='booking_date') ? 'inline-block':'none';
    document.getElementById('searchBtn').style.display            = (f!=='All')          ? 'inline-block':'none';
    if (f==='farmer_name')  document.getElementById('textInput').placeholder = 'Enter farmer name...';
    if (f==='machine_name') document.getElementById('textInput').placeholder = 'Enter machine name...';
}
window.addEventListener('DOMContentLoaded', toggleInputs);

function handleFieldChange() {
    document.getElementById('textInput').value   = '';
    document.getElementById('typeInput').value   = '';
    document.getElementById('statusInput').value = '';
    document.getElementById('date_from').value   = '';
    document.getElementById('date_to').value     = '';
    if (window.fpFrom) fpFrom.clear();
    if (window.fpTo)   fpTo.clear();
    document.getElementById('field_changed').value = '1';
    document.getElementById('searchForm').submit();
}

/* ── Row selection ── */
let selectedId = null, selectedStatus = null;
document.querySelectorAll('.assignment-row').forEach(function(row) {
    row.addEventListener('click', function() {
        if (selectedId === parseInt(this.dataset.id)) {
            this.classList.remove('selected');
            selectedId = null; selectedStatus = null;
            document.getElementById('btnComplete').disabled = true;
            document.getElementById('btnView').disabled     = true;
            document.getElementById('btnPrint').disabled    = true;
            return;
        }
        document.querySelectorAll('.assignment-row').forEach(r => r.classList.remove('selected'));
        this.classList.add('selected');
        selectedId     = parseInt(this.dataset.id);
        selectedStatus = this.dataset.status;

        document.getElementById('btnView').disabled     = false;
        document.getElementById('btnPrint').disabled    = false;
        document.getElementById('btnComplete').disabled = (selectedStatus !== 'Approved');
    });
});

/* ── Mark complete ── */
function submitComplete() {
    if (!selectedId || selectedStatus !== 'Approved') return;
    if (!confirm('Mark this work assignment as completed?')) return;
    document.getElementById('completeBookingId').value = selectedId;
    document.getElementById('completeForm').submit();
}

/* ── Fill modal/print ── */
function fillDetails(b) {
    const d = new Date(b.booking_date + 'T00:00:00');
    const ds = d.toLocaleDateString('en-US',{year:'numeric',month:'long',day:'numeric'});
    document.getElementById('modal-title').textContent    = b.farmer_name + ' — ' + b.machine_name;
    document.getElementById('modal-subtitle').textContent = ds;
    document.getElementById('d-machine').textContent  = b.machine_name;
    document.getElementById('d-type').textContent     = b.machine_type;
    document.getElementById('d-farmer').textContent   = b.farmer_name;
    document.getElementById('d-phone').textContent    = b.farmer_phone || '—';
    document.getElementById('d-email').textContent    = b.farmer_email || '—';
    document.getElementById('d-date').textContent     = ds;
    document.getElementById('d-status').innerHTML     = '<span class="badge-'+b.status+'">'+b.status+'</span>';
    document.getElementById('d-size').textContent     = b.farm_size ? parseFloat(b.farm_size).toFixed(2)+' ha' : '—';
    document.getElementById('d-location').textContent = b.farm_location || '—';
    document.getElementById('d-barangay').textContent = b.barangay || '—';
    document.getElementById('d-muni').textContent     = b.municipality || '—';
    const nw = document.getElementById('d-notes-wrap');
    if (b.notes) { document.getElementById('d-notes').textContent = b.notes; nw.style.display='block'; }
    else { nw.style.display='none'; }

    /* Print */
    document.getElementById('print-date').textContent  = new Date().toLocaleDateString('en-US',{year:'numeric',month:'long',day:'numeric'});
    document.getElementById('pr-machine').textContent  = b.machine_name;
    document.getElementById('pr-type').textContent     = b.machine_type;
    document.getElementById('pr-farmer').textContent   = b.farmer_name;
    document.getElementById('pr-phone').textContent    = b.farmer_phone || '—';
    document.getElementById('pr-email').textContent    = b.farmer_email || '—';
    document.getElementById('pr-date').textContent     = ds;
    document.getElementById('pr-status').textContent   = b.status;
    document.getElementById('pr-size').textContent     = b.farm_size ? parseFloat(b.farm_size).toFixed(2)+' ha' : '—';
    document.getElementById('pr-location').textContent = b.farm_location || '—';
    document.getElementById('pr-barangay').textContent = b.barangay || '—';
    document.getElementById('pr-muni').textContent     = b.municipality || '—';
    const pnw = document.getElementById('pr-notes-wrap');
    if (b.notes) { document.getElementById('pr-notes').textContent = b.notes; pnw.style.display='block'; }
    else { pnw.style.display='none'; }
}

function openModal() {
    if (!selectedId) return;
    const b = rowData[selectedId];
    if (!b) return;
    fillDetails(b);
    document.getElementById('detailModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closeModal() {
    document.getElementById('detailModal').classList.remove('active');
    document.body.style.overflow = '';
}
function printSelected() {
    if (!selectedId) return;
    const b = rowData[selectedId];
    if (!b) return;
    fillDetails(b);
    document.getElementById('printArea').style.display = 'block';
    window.print();
    document.getElementById('printArea').style.display = 'none';
}

document.getElementById('detailModal').addEventListener('click', function(e){ if(e.target===this) closeModal(); });
document.addEventListener('keydown', function(e){ if(e.key==='Escape') closeModal(); });
</script>
</body>
</html>