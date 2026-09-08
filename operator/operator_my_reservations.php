<?php
session_start();
require_once '../includes/db_connection.php';

/* ── Auth check BEFORE any include ── */
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'operator') {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

/* ── Load operator data BEFORE any include ── */
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

/* ── MARK AS COMPLETED — handle POST before any HTML output (PRG pattern) ── */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['complete_booking'])) {
    $booking_id  = (int)$_POST['booking_id'];
    $result_flag = 'error';
    $result_msg  = 'Unknown error.';

    if ($booking_id > 0) {
        /* Verify the booking belongs to this operator and is still Approved */
        $verify = $conn->prepare("
            SELECT b.id, b.machine_id, b.farmer_id, b.lot_id, b.farm_size
            FROM bookings b
            JOIN machine_operators mo ON mo.machine_id = b.machine_id
            WHERE b.id = ?
              AND mo.operator_id = ?
              AND mo.status = 'Active'
              AND b.status = 'Approved'
            LIMIT 1
        ");
        $verify->bind_param("ii", $booking_id, $operator_id);
        $verify->execute();
        $vresult = $verify->get_result();

        if ($vresult->num_rows > 0) {
            $booking_row = $vresult->fetch_assoc();

            /*
             * STEP 1: Compute discount info in PHP (moved OUT of trigger).
             * The trigger `trg_create_payment_ledger` previously did
             * UPDATE bookings inside an AFTER UPDATE ON bookings trigger —
             * MySQL forbids this. We now calculate and write discount fields
             * HERE in PHP, BEFORE the status update, so the trigger never
             * needs to touch bookings again.
             */
            $machine_id = $booking_row['machine_id'];
            $farmer_id  = $booking_row['farmer_id'];
            $lot_id     = $booking_row['lot_id'];

            /* Get machine price + association */
            $mq = $conn->prepare("SELECT price_per_hectare, association_id FROM machines WHERE id = ?");
            $mq->bind_param("i", $machine_id);
            $mq->execute();
            $mrow = $mq->get_result()->fetch_assoc();
            $mq->close();

            $price_per_ha   = (float)($mrow['price_per_hectare'] ?? 0);
            $machine_assoc  = (int)($mrow['association_id'] ?? 0);

            /* Get effective farm size from lot or booking */
            $farm_size = 0;
            if ($lot_id) {
                $lq = $conn->prepare("SELECT farm_size FROM farmer_lots WHERE id = ?");
                $lq->bind_param("i", $lot_id);
                $lq->execute();
                $lrow = $lq->get_result()->fetch_assoc();
                $lq->close();
                $farm_size = (float)($lrow['farm_size'] ?? 0);
            }
            if (!$farm_size) {
                $farm_size = (float)($booking_row['farm_size'] ?? 0);
            }

            $base_total = $farm_size * $price_per_ha;

            /* Check if farmer belongs to same association */
            $fq = $conn->prepare("SELECT association_id FROM farmers WHERE id = ?");
            $fq->bind_param("i", $farmer_id);
            $fq->execute();
            $frow = $fq->get_result()->fetch_assoc();
            $fq->close();

            $farmer_assoc = (int)($frow['association_id'] ?? 0);

            if ($farmer_assoc && $farmer_assoc === $machine_assoc) {
                $discount_pct    = 5.00;
                $discount_amount = $base_total * 0.05;
                $total_amount    = $base_total - $discount_amount;
            } else {
                $discount_pct    = 0.00;
                $discount_amount = 0.00;
                $total_amount    = $base_total;
            }

            /*
             * STEP 2: Update booking — set status = Completed AND discount fields
             * in ONE statement so the trigger receives everything it needs
             * without having to UPDATE bookings itself.
             */
            $upd = $conn->prepare("
                UPDATE bookings 
                SET status           = 'Completed',
                    discount_percent = ?,
                    discount_amount  = ?,
                    discounted_total = ?,
                    updated_at       = NOW()
                WHERE id = ? AND status = 'Approved'
            ");
            $upd->bind_param("dddi", $discount_pct, $discount_amount, $total_amount, $booking_id);
            $upd->execute();

            if ($upd->affected_rows > 0) {
                $result_flag = 'success';
                $result_msg  = '✅ Work assignment marked as completed!';
            } else {
                $result_msg = '❌ Update failed or already completed. Error: ' . ($upd->error ?: 'no rows affected');
            }
            $upd->close();

        } else {
            $result_msg = '❌ Booking not found, not assigned to you, or not Approved.';
        }
        $verify->close();
    } else {
        $result_msg = '❌ Invalid booking ID.';
    }

    /* PRG: redirect to GET so refresh doesn't resubmit */
    header("Location: " . $_SERVER['PHP_SELF'] . "?result=" . urlencode($result_flag) . "&msg=" . urlencode($result_msg));
    exit;
}

/* ── Read flash message from redirect ── */
$message = "";
$error   = "";
if (!empty($_GET['result'])) {
    if ($_GET['result'] === 'success') {
        $message = htmlspecialchars(urldecode($_GET['msg'] ?? ''));
    } else {
        $error = htmlspecialchars(urldecode($_GET['msg'] ?? ''));
    }
}

/* ── Now safe to include dashboard (outputs HTML header/nav) ── */
include('operator_dashboard.php');

/* ── FILTER PARAMS ── */
$search_field  = $_GET['search_field']   ?? 'All';
$search_term   = $_GET['search_term']    ?? '';
$status_filter = $_GET['statusDropdown'] ?? '';
$from_date     = $_GET['from_date']      ?? '';
$to_date       = $_GET['to_date']        ?? '';
$field_changed = $_GET['field_changed']  ?? '0';

/* ── BUILD WHERE ── */
$where  = ["mo.operator_id = ?", "mo.status = 'Active'", "b.status IN ('Approved','Completed')"];
$params = [$operator_id];
$types  = "i";

if ($field_changed !== '1') {
    if ($search_field === 'farmer' && !empty($search_term)) {
        $safe    = $conn->real_escape_string($search_term);
        $where[] = "CONCAT(f.first_name,' ',COALESCE(f.middle_name,''),' ',f.last_name) LIKE '%$safe%'";
    } elseif ($search_field === 'machine' && !empty($search_term)) {
        $where[] = "m.machine_name LIKE '%" . $conn->real_escape_string($search_term) . "%'";
    } elseif ($search_field === 'location' && !empty($search_term)) {
        $where[] = "b.farm_location LIKE '%" . $conn->real_escape_string($search_term) . "%'";
    } elseif ($search_field === 'Status') {
        if (!empty($status_filter)) {
            $where[] = "b.status = '" . $conn->real_escape_string($status_filter) . "'";
        }
        if (!empty($from_date) && !empty($to_date)) {
            $where[] = "b.booking_date BETWEEN '"
                     . $conn->real_escape_string($from_date) . "' AND '"
                     . $conn->real_escape_string($to_date) . "'";
        }
    }
}

$where_sql = implode(" AND ", $where);

/* ── FETCH ── */
$stmt = $conn->prepare("
    SELECT b.id AS booking_id, b.booking_date, b.farm_location, b.farm_size,
           b.notes, b.status, b.created_at,
           m.id AS machine_id, m.machine_name, m.type AS machine_type,
           m.image_path, m.price_per_hectare,
           CONCAT(f.first_name,' ',COALESCE(f.middle_name,''),' ',f.last_name) AS farmer_name,
           f.phone AS farmer_phone, f.email AS farmer_email,
           f.barangay, f.municipality, f.province,
           fl.farm_size AS lot_size
    FROM bookings b
    JOIN machines m           ON b.machine_id = m.id
    JOIN machine_operators mo ON mo.machine_id = m.id
    JOIN farmers f            ON b.farmer_id = f.id
    LEFT JOIN farmer_lots fl  ON b.lot_id = fl.id
    WHERE $where_sql
    ORDER BY CASE WHEN b.status='Approved' THEN 1 ELSE 2 END, b.booking_date ASC
");
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result   = $stmt->get_result();
$bookings = [];
while ($r = $result->fetch_assoc()) {
    $size           = $r['lot_size'] ?? $r['farm_size'] ?? 0;
    $r['eff_size']  = $size;
    $r['total_amt'] = $size * $r['price_per_hectare'];
    $bookings[]     = $r;
}
$stmt->close();
$total = count($bookings);

/* Pass rows as JSON for modal */
$rows_json = json_encode(array_column(
    array_map(fn($r) => [
        'id'            => $r['booking_id'],
        'status'        => $r['status'],
        'farmer_name'   => $r['farmer_name'],
        'farmer_phone'  => $r['farmer_phone'],
        'farmer_email'  => $r['farmer_email'],
        'machine_name'  => $r['machine_name'],
        'machine_type'  => $r['machine_type'],
        'image_path'    => $r['image_path'],
        'booking_date'  => $r['booking_date'],
        'farm_location' => $r['farm_location'],
        'farm_size'     => $r['eff_size'],
        'barangay'      => $r['barangay'],
        'municipality'  => $r['municipality'],
        'province'      => $r['province'],
        'notes'         => $r['notes'] ?? '',
        'created_at'    => $r['created_at'],
        'rate_per_ha'   => $r['price_per_hectare'],
        'total_amt'     => $r['total_amt'],
    ], $bookings),
    null, 'id'
));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Work Assignments</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.css">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'Segoe UI',Arial,sans-serif; }

.main-content { padding: 20px; }

/* ── Search form ── */
.search-form {
    margin-bottom: 18px; display: flex;
    flex-wrap: wrap; align-items: center; gap: 10px;
}
.search-form select,
.search-form input[type="text"],
.search-form button {
    padding: 8px 12px; font-size: 13px;
    border: 1px solid #ccc; border-radius: 6px;
}
.search-form button { background: #2d7a2d; color: white; border: none; cursor: pointer; }
.search-form button:hover { background: #256725; }
.flatpickr-input {
    padding: 8px 12px !important; font-size: 13px !important;
    border: 1px solid #ccc !important; border-radius: 6px !important;
    background: white !important; color: #333 !important;
    width: 130px !important; box-sizing: border-box !important; cursor: pointer !important;
}
.flatpickr-input:focus { outline: none !important; border-color: #2d7a2d !important; }
label.filter-lbl {
    color: #000000; font-weight: bold; font-size: 14px;
    margin-right: 2px; text-shadow: 1px 1px 3px rgba(14,4,4,0.7);
}

/* ── Table ── */
.table-container {
    border: 1px solid #ddd; border-radius: 10px;
    background: white; box-shadow: 0 2px 6px rgba(0,0,0,.08);
    overflow-x: auto; -webkit-overflow-scrolling: touch;
}
.table-container::-webkit-scrollbar { height: 6px; }
.table-container::-webkit-scrollbar-track { background: #f1f1f1; }
.table-container::-webkit-scrollbar-thumb { background: #2d7a2d; border-radius: 4px; }

.table-inner { min-width: 1100px; }
.table-inner table { width: 100%; border-collapse: collapse; table-layout: fixed; }
.table-inner thead { display: table; width: 100%; table-layout: fixed; }
.table-inner tbody {
    display: block; max-height: 340px;
    overflow-y: auto; overflow-x: hidden; width: 100%;
}
.table-inner tbody::-webkit-scrollbar { width: 8px; }
.table-inner tbody::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 4px; }
.table-inner tbody::-webkit-scrollbar-thumb { background: #2d7a2d; border-radius: 4px; }
.table-inner tbody::-webkit-scrollbar-thumb:hover { background: #1a5c1a; }
.table-inner tbody tr {
    display: table; width: 100%; table-layout: fixed;
    cursor: pointer; transition: background .15s;
}
.table-inner tbody tr:hover    { background: #f8fffe; }
.table-inner tbody tr.selected { background: #d1fae5 !important; border-left: 4px solid #2d7a2d; }

th, td {
    padding: 10px 11px; text-align: left;
    border-bottom: 1px solid #eee; font-size: 13px;
    vertical-align: middle;
}
th { background: #2d7a2d; color: white; font-weight: 600; white-space: nowrap; }

th:nth-child(1),  td:nth-child(1)  { width: 40px;  }
th:nth-child(2),  td:nth-child(2)  { width: 100px; }
th:nth-child(3),  td:nth-child(3)  { width: 140px; }
th:nth-child(4),  td:nth-child(4)  { width: 170px; }
th:nth-child(5),  td:nth-child(5)  { width: 100px; }
th:nth-child(6),  td:nth-child(6)  { width: 90px;  }
th:nth-child(7),  td:nth-child(7)  { width: 90px;  }
th:nth-child(8),  td:nth-child(8)  { width: 120px; }
th:nth-child(9),  td:nth-child(9)  { width: 100px; }
th:nth-child(10), td:nth-child(10) { width: 110px; }
th:nth-child(11), td:nth-child(11) { width: 90px;  }

.badge { padding: 4px 9px; border-radius: 20px; font-size: 11px; font-weight: 700; display: inline-block; white-space: nowrap; }
.badge-approved  { background: #dbeafe; color: #1e40af; }
.badge-completed { background: #d1fae5; color: #065f46; }
.badge-pending   { background: #fef3c7; color: #92400e; }
.badge-cancelled { background: #fee2e2; color: #991b1b; }

.dbl-hint {
    text-align: center; font-size: 12px; color: rgba(255,255,255,0.75);
    margin-bottom: 6px; font-style: italic;
}

/* ── Action Buttons ── */
.action-buttons {
    margin-top: 18px; display: flex;
    justify-content: center; gap: 14px; flex-wrap: wrap;
}
.action-buttons button {
    padding: 10px 22px; border-radius: 8px; border: none;
    background: #2d7a2d; color: white;
    cursor: pointer; font-size: 14px; font-weight: 600;
    transition: 0.2s; display: flex; align-items: center; gap: 7px;
}
.action-buttons button:hover    { background: #256725; transform: scale(1.03); }
.action-buttons button:disabled { background: #2d7a2d; cursor: not-allowed; transform: none; }

/* ── Modal ── */
.modal-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,0.55); z-index: 9999;
    justify-content: center; align-items: center;
    backdrop-filter: blur(3px);
}
.modal-overlay.active { display: flex; }
.modal-box {
    background: white; width: 600px; max-width: 96%;
    max-height: 90vh; overflow-y: auto;
    border-radius: 14px; box-shadow: 0 20px 40px rgba(0,0,0,0.2);
}
.modal-box::-webkit-scrollbar { width: 6px; }
.modal-box::-webkit-scrollbar-thumb { background: #2d7a2d; border-radius: 4px; }

.modal-head {
    background: linear-gradient(135deg, #2d7a2d, #1a5c1a);
    padding: 20px 24px; border-radius: 14px 14px 0 0;
    display: flex; justify-content: space-between; align-items: flex-start;
}
.modal-head h3 { color: white; font-size: 20px; font-weight: 700; margin: 0; }
.modal-head p  { color: rgba(255,255,255,.8); font-size: 13px; margin: 4px 0 0; }
.modal-x {
    width: 32px; height: 32px; background: rgba(255,255,255,.2);
    border: none; border-radius: 50%; color: white; font-size: 18px;
    cursor: pointer; display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.modal-x:hover { background: rgba(255,255,255,.35); }
.modal-bd { padding: 22px 24px; }

.m-alert { padding: 11px 14px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; display: flex; align-items: center; gap: 8px; }
.m-alert.ok  { background: #d1fae5; color: #065f46; border: 1px solid #86efac; }
.m-alert.err { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

.m-sec {
    font-size: 12px; font-weight: 700; color: #6b7280;
    text-transform: uppercase; letter-spacing: .5px;
    margin-bottom: 10px; margin-top: 4px;
    display: flex; align-items: center; gap: 8px;
}
.m-sec::after { content: ''; flex: 1; height: 1px; background: #e5e7eb; }

.detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 14px; }
.detail-item { background: #f9fafb; border-radius: 8px; padding: 10px 13px; }
.detail-item .lbl { font-size: 11px; color: #9ca3af; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; }
.detail-item .val { font-size: 14px; color: #1f2937; font-weight: 600; margin-top: 3px; }
.detail-item.full { grid-column: 1 / -1; }

.total-box {
    background: linear-gradient(135deg, #2d7a2d, #1a5c1a);
    border-radius: 10px; padding: 14px 18px;
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 14px;
}
.total-box .lbl { color: rgba(255,255,255,.8); font-size: 13px; font-weight: 600; }
.total-box .val { color: #fff; font-size: 22px; font-weight: 800; }

.m-foot {
    padding: 14px 24px; background: #f9fafb;
    border-top: 1px solid #e5e7eb;
    display: flex; gap: 10px; justify-content: flex-end;
    border-radius: 0 0 14px 14px;
}
.m-close-btn {
    padding: 9px 22px; background: white; color: #6b7280;
    border: 2px solid #e5e7eb; border-radius: 6px;
    font-size: 14px; font-weight: 600; cursor: pointer;
}
.m-close-btn:hover { background: #f9fafb; }

/* ── Print ── */
@media print {
    .search-form, .action-buttons, .welcome-bar { display: none !important; }
    body * { visibility: hidden; }
    #printArea, #printArea * { visibility: visible; }
    #printArea { position: absolute; top: 0; left: 0; width: 100%; padding: 30px; }
}
.print-header { text-align:center; margin-bottom:24px; border-bottom:2px solid #2d7a2d; padding-bottom:16px; }
.print-header h2 { color:#2d7a2d; margin:0 0 4px; font-size:22px; }
.print-header p  { color:#6b7280; margin:0; font-size:13px; }
.print-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px; }
.print-item { background:#f9fafb; border:1px solid #e5e7eb; border-radius:6px; padding:10px 14px; }
.print-item .lbl { font-size:10px; color:#9ca3af; font-weight:700; text-transform:uppercase; }
.print-item .val { font-size:14px; color:#1f2937; font-weight:600; margin-top:2px; }
.print-item.full { grid-column:1/-1; }
.print-section-title { font-size:12px; font-weight:700; color:#6b7280; text-transform:uppercase; margin:16px 0 8px; border-bottom:1px solid #e5e7eb; padding-bottom:4px; }
.print-total { background:#f0fdf4; border:2px solid #2d7a2d; border-radius:8px; padding:12px 16px; margin-top:12px; display:flex; justify-content:space-between; }
.print-total .lbl { font-size:13px; font-weight:700; color:#1a5c1a; }
.print-total .val { font-size:18px; font-weight:800; color:#1a5c1a; }
.print-footer { margin-top:30px; text-align:center; font-size:11px; color:#9ca3af; border-top:1px solid #e5e7eb; padding-top:12px; }
</style>
</head>
<body>

<div class="main-content">

<!-- Welcome Bar -->
<div style="display:flex; align-items:center; gap:14px; padding:4px 0 10px 0; font-size:14px; color: #000000;">
    <span>Welcome <strong><?= htmlspecialchars($operator_name) ?></strong></span>
    <a href="../logout.php" style="color: #000000; text-decoration:underline; font-weight:600;">Logout</a>
</div>

<h2 style="text-align:center; color: #2d7a2d; margin-bottom:6px;">Work Assignments</h2>


<?php if ($message): ?>
    <div class="m-alert ok"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="m-alert err"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Search / Filter -->
<form class="search-form" method="GET" id="searchForm">
    <input type="hidden" name="field_changed" id="field_changed" value="0">

    <select name="search_field" id="search_field" onchange="handleFieldChange()">
        <option value="All"      <?= $search_field==='All'      ?'selected':'' ?>>All</option>
        <option value="farmer"   <?= $search_field==='farmer'   ?'selected':'' ?>>Farmer Name</option>
        
        
        <option value="Status"   <?= $search_field==='Status'   ?'selected':'' ?>>Status</option>
    </select>

    <input type="text" name="search_term" id="textInput" placeholder="Enter search..."
           value="<?= htmlspecialchars($search_term) ?>" style="display:none;">

    <select name="statusDropdown" id="statusDropdown" style="display:none;">
        <option value="">All Statuses</option>
        <option value="Approved"  <?= $status_filter==='Approved'  ?'selected':'' ?>>Approved</option>
        <option value="Completed" <?= $status_filter==='Completed' ?'selected':'' ?>>Completed</option>
    </select>

    <label class="filter-lbl" id="from_label" style="display:none;">From</label>
    <input type="text" id="from_date_display" placeholder="mm/dd/yyyy" readonly style="display:none;">
    <input type="hidden" name="from_date" id="from_date" value="<?= htmlspecialchars($from_date) ?>">

    <label class="filter-lbl" id="to_label" style="display:none;">To</label>
    <input type="text" id="to_date_display" placeholder="mm/dd/yyyy" readonly style="display:none;">
    <input type="hidden" name="to_date" id="to_date" value="<?= htmlspecialchars($to_date) ?>">

    <button type="submit" id="searchBtn" style="display:none;"> Search</button>

    <span style="margin-left:auto; color: #2d7a2d; font-weight:bold; font-size:14px;
                 text-shadow:1px 1px 3px rgba(14,4,4,0.7); white-space:nowrap;">
        Total: <?= $total ?> work assignment(s)
    </span>
</form>

<!-- Table -->
<div class="table-container">
  <div class="table-inner">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Booking Date</th>
          <th>Farmer Name</th>
          <th>Farm Location</th>
          <th>Phone</th>
          <th>Farm Size</th>
          <th>Machine Type</th>
          <th>Machine Name</th>
          <th>Rate / Ha</th>
          <th>Total Amount</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($total > 0): ?>
        <?php $i = 1; foreach ($bookings as $b): ?>
        <tr class="booking-row"
            data-id="<?= $b['booking_id'] ?>"
            data-status="<?= htmlspecialchars($b['status']) ?>"
            data-farmer="<?= htmlspecialchars($b['farmer_name']) ?>"
            data-farmer-phone="<?= htmlspecialchars($b['farmer_phone']) ?>"
            data-farmer-email="<?= htmlspecialchars($b['farmer_email']) ?>"
            data-machine="<?= htmlspecialchars($b['machine_name']) ?>"
            data-machine-type="<?= htmlspecialchars($b['machine_type']) ?>"
            data-machine-img="<?= htmlspecialchars($b['image_path'] ?? '') ?>"
            data-booking-date="<?= htmlspecialchars(date('M d, Y', strtotime($b['booking_date']))) ?>"
            data-farm-size="<?= $b['eff_size'] ?>"
            data-farm-location="<?= htmlspecialchars($b['farm_location']) ?>"
            data-barangay="<?= htmlspecialchars($b['barangay']) ?>"
            data-municipality="<?= htmlspecialchars($b['municipality']) ?>"
            data-province="<?= htmlspecialchars($b['province']) ?>"
            data-rate="<?= $b['price_per_hectare'] ?>"
            data-total="<?= $b['total_amt'] ?>"
            data-notes="<?= htmlspecialchars($b['notes'] ?? '') ?>"
            data-created="<?= htmlspecialchars(date('M d, Y h:i A', strtotime($b['created_at']))) ?>">

            <td><?= $i++ ?></td>
            <td><?= date('M d, Y', strtotime($b['booking_date'])) ?></td>
            <td><strong><?= htmlspecialchars($b['farmer_name']) ?></strong></td>
            <td style="white-space:normal; line-height:1.4;">
                <?= htmlspecialchars($b['farm_location'] ?: $b['barangay']) ?><br>
                <small style="color:#6b7280;">
                    <?= htmlspecialchars($b['municipality'].', '.$b['province']) ?>
                </small>
            </td>
            <td><?= htmlspecialchars($b['farmer_phone']) ?></td>
            <td><?= $b['eff_size'] ? number_format($b['eff_size'], 2).' ha' : '—' ?></td>
            <td>
                <span class="badge" style="background:#f0fdf4;color:#166534;border:1px solid #bbf7d0;">
                    <?= htmlspecialchars($b['machine_type']) ?>
                </span>
            </td>
            <td><?= htmlspecialchars($b['machine_name']) ?></td>
            <td>₱<?= number_format($b['price_per_hectare'], 2) ?></td>
            <td><strong>₱<?= number_format($b['total_amt'], 2) ?></strong></td>
            <td>
                <span class="badge badge-<?= strtolower($b['status']) ?>">
                    <?= htmlspecialchars($b['status']) ?>
                </span>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php else: ?>
        <tr>
            <td colspan="11" style="text-align:center; padding:40px; color:#9ca3af;">
                <i class="fas fa-calendar-xmark" style="font-size:32px; display:block; margin-bottom:10px;"></i>
                No work assignments found
            </td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Action Buttons -->
<div class="action-buttons">
    <button id="btnComplete" onclick="submitComplete()" disabled>
         Completed
    </button>
    <button onclick="printSelected()">
         Print
    </button>
</div>

<!-- Hidden complete form -->
<form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" id="completeForm" style="display:none;">
    <input type="hidden" name="complete_booking" value="1">
    <input type="hidden" name="booking_id" id="completeBookingId" value="">
</form>

</div><!-- /.main-content -->

<!-- VIEW DETAILS MODAL -->
<div class="modal-overlay" id="viewModal">
  <div class="modal-box">
    <div class="modal-head">
      <div>
        <h3> Work Assignment Details</h3>
        <p id="view-subtitle">Booking information</p>
      </div>
      <button class="modal-x" onclick="closeModal('viewModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-bd">

      <div class="m-sec"><i class="fas fa-user"></i> Farmer Information</div>
      <div class="detail-grid">
        <div class="detail-item"><div class="lbl">Name</div><div class="val" id="v-farmer">—</div></div>
        <div class="detail-item"><div class="lbl">Phone</div><div class="val" id="v-phone">—</div></div>
        <div class="detail-item"><div class="lbl">Email</div><div class="val" id="v-email">—</div></div>
        <div class="detail-item"><div class="lbl">Address</div><div class="val" id="v-farmer-loc">—</div></div>
      </div>

      <div class="m-sec"><i class="fas fa-tractor"></i> Machine &amp; Booking</div>
      <div class="detail-grid">
        <div class="detail-item"><div class="lbl">Machine Name</div><div class="val" id="v-machine">—</div></div>
        <div class="detail-item"><div class="lbl">Machine Type</div><div class="val" id="v-machine-type">—</div></div>
        <div class="detail-item"><div class="lbl">Booking Date</div><div class="val" id="v-date">—</div></div>
        <div class="detail-item"><div class="lbl">Status</div><div class="val" id="v-status">—</div></div>
      </div>

      <div class="m-sec"><i class="fas fa-map-marker-alt"></i> Farm Details</div>
      <div class="detail-grid">
        <div class="detail-item"><div class="lbl">Farm Location</div><div class="val" id="v-location">—</div></div>
        <div class="detail-item"><div class="lbl">Barangay</div><div class="val" id="v-barangay">—</div></div>
        <div class="detail-item"><div class="lbl">Municipality</div><div class="val" id="v-municipality">—</div></div>
        <div class="detail-item"><div class="lbl">Province</div><div class="val" id="v-province">—</div></div>
        <div class="detail-item"><div class="lbl">Farm Size</div><div class="val" id="v-size">—</div></div>
      </div>

      <div class="m-sec"><i class="fas fa-peso-sign"></i> Payment Summary</div>
      <div class="detail-grid" style="margin-bottom:12px;">
        <div class="detail-item"><div class="lbl">Rate per Hectare</div><div class="val" id="v-rate">—</div></div>
        <div class="detail-item"><div class="lbl">Farm Size</div><div class="val" id="v-size2">—</div></div>
      </div>
      <div class="total-box">
        <span class="lbl"><i class="fas fa-calculator"></i> &nbsp;Total Amount</span>
        <span class="val" id="v-total">₱0.00</span>
      </div>

      <div id="v-notes-wrap" style="display:none;">
        <div class="m-sec"><i class="fas fa-note-sticky"></i> Notes</div>
        <div class="detail-item full" style="margin-bottom:14px;">
          <div class="val" id="v-notes" style="font-weight:400; font-size:13px; color:#374151; white-space:pre-wrap;"></div>
        </div>
      </div>

    </div>
    <div class="m-foot">
      <button class="m-close-btn" onclick="closeModal('viewModal')">Close</button>
    </div>
  </div>
</div>

<!-- Print Area -->
<div id="printArea" style="display:none;">
  <div class="print-header">
    <h2>Agricultural Machineries Reservation &amp; Monitoring System</h2>
    <p>Work Assignment Report — Printed: <span id="print-date"></span></p>
  </div>
  <div class="print-section-title">Machine</div>
  <div class="print-grid">
    <div class="print-item"><div class="lbl">Machine Name</div><div class="val" id="pr-machine">—</div></div>
    <div class="print-item"><div class="lbl">Machine Type</div><div class="val" id="pr-type">—</div></div>
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
    <div class="print-item"><div class="lbl">Province</div><div class="val" id="pr-province">—</div></div>
  </div>
  <div class="print-section-title">Payment</div>
  <div class="print-grid">
    <div class="print-item"><div class="lbl">Rate / Hectare</div><div class="val" id="pr-rate">—</div></div>
    <div class="print-item"><div class="lbl">Farm Size</div><div class="val" id="pr-size2">—</div></div>
  </div>
  <div class="print-total">
    <span class="lbl">Total Amount</span>
    <span class="val" id="pr-total">—</span>
  </div>
  <div id="pr-notes-wrap" style="display:none;">
    <div class="print-section-title">Notes</div>
    <div class="print-item full"><div class="val" id="pr-notes">—</div></div>
  </div>
  <div class="print-footer">
    Agricultural Machineries Reservation &amp; Monitoring System — Operator: <?= htmlspecialchars($operator_name) ?>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.js"></script>
<script>
const rowData = <?= $rows_json ?>;

/* ── Filter ── */
function handleFieldChange() {
    document.getElementById('textInput').value      = '';
    document.getElementById('statusDropdown').value = '';
    document.getElementById('from_date').value      = '';
    document.getElementById('to_date').value        = '';
    if (window.fpFrom) fpFrom.clear();
    if (window.fpTo)   fpTo.clear();
    document.getElementById('field_changed').value  = '1';
    document.getElementById('searchForm').submit();
}

function toggleInputs() {
    const field    = document.getElementById('search_field').value;
    const isStatus = field === 'Status';
    const isText   = (field === 'farmer' || field === 'machine' || field === 'location');
    document.getElementById('textInput').style.display          = isText   ? 'inline-block' : 'none';
    document.getElementById('statusDropdown').style.display     = isStatus ? 'inline-block' : 'none';
    document.getElementById('from_date_display').style.display  = isStatus ? 'inline-block' : 'none';
    document.getElementById('to_date_display').style.display    = isStatus ? 'inline-block' : 'none';
    document.getElementById('from_label').style.display         = isStatus ? 'inline-block' : 'none';
    document.getElementById('to_label').style.display           = isStatus ? 'inline-block' : 'none';
    document.getElementById('searchBtn').style.display          = (field !== 'All') ? 'inline-block' : 'none';
    if (field === 'farmer')   document.getElementById('textInput').placeholder = 'Enter farmer name...';
    if (field === 'machine')  document.getElementById('textInput').placeholder = 'Enter machine name...';
    if (field === 'location') document.getElementById('textInput').placeholder = 'Enter location...';
}
window.onload = toggleInputs;

/* ── Flatpickr ── */
window.fpFrom = flatpickr('#from_date_display', {
    dateFormat: 'm/d/Y', allowInput: false,
    onChange: function(d) {
        document.getElementById('from_date').value = d.length
            ? d[0].getFullYear()+'-'+String(d[0].getMonth()+1).padStart(2,'0')+'-'+String(d[0].getDate()).padStart(2,'0')
            : '';
    }
});
window.fpTo = flatpickr('#to_date_display', {
    dateFormat: 'm/d/Y', allowInput: false,
    onChange: function(d) {
        document.getElementById('to_date').value = d.length
            ? d[0].getFullYear()+'-'+String(d[0].getMonth()+1).padStart(2,'0')+'-'+String(d[0].getDate()).padStart(2,'0')
            : '';
    }
});
<?php if ($from_date): ?>fpFrom.setDate('<?= $from_date ?>', true, 'Y-m-d');<?php endif; ?>
<?php if ($to_date):   ?>fpTo.setDate('<?= $to_date ?>',   true, 'Y-m-d');<?php endif; ?>

/* ── Row selection ── */
let selectedId   = null;
let selectedData = null;

document.querySelectorAll('.booking-row').forEach(function(row) {
    row.addEventListener('click', function() {
        const clickedId = this.dataset.id;
        if (selectedId === clickedId) {
            this.classList.remove('selected');
            selectedId   = null;
            selectedData = null;
            document.getElementById('btnComplete').disabled = true;
            return;
        }
        document.querySelectorAll('.booking-row').forEach(r => r.classList.remove('selected'));
        this.classList.add('selected');
        selectedId = clickedId;
        selectedData = {
            id:           this.dataset.id,
            status:       this.dataset.status,
            farmer:       this.dataset.farmer,
            farmerPhone:  this.dataset.farmerPhone,
            farmerEmail:  this.dataset.farmerEmail,
            machine:      this.dataset.machine,
            machineType:  this.dataset.machineType,
            machineImg:   this.dataset.machineImg,
            bookingDate:  this.dataset.bookingDate,
            farmSize:     this.dataset.farmSize,
            farmLocation: this.dataset.farmLocation,
            barangay:     this.dataset.barangay,
            municipality: this.dataset.municipality,
            province:     this.dataset.province,
            rate:         this.dataset.rate,
            total:        this.dataset.total,
            notes:        this.dataset.notes,
            created:      this.dataset.created,
        };
        document.getElementById('btnComplete').disabled = (selectedData.status !== 'Approved');
    });

    row.addEventListener('dblclick', function() {
        if (selectedData) openViewModal();
    });
});

/* ── Modals ── */
function openModal(id)  { document.getElementById(id).classList.add('active');    document.body.style.overflow = 'hidden'; }
function closeModal(id) { document.getElementById(id).classList.remove('active'); document.body.style.overflow = ''; }

document.getElementById('viewModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal('viewModal');
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal('viewModal');
});

function fmt(n)   { return '₱' + parseFloat(n || 0).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2}); }
function fmtHa(n) { return n ? parseFloat(n).toFixed(2) + ' ha' : '—'; }

/* ── View Modal ── */
function openViewModal() {
    if (!selectedData) return;
    const s = selectedData;
    document.getElementById('view-subtitle').textContent  = 'Booking #' + s.id + ' — ' + s.bookingDate;
    document.getElementById('v-farmer').textContent       = s.farmer       || '—';
    document.getElementById('v-phone').textContent        = s.farmerPhone  || '—';
    document.getElementById('v-email').textContent        = s.farmerEmail  || '—';
    document.getElementById('v-farmer-loc').textContent   = (s.barangay || '') + ', ' + (s.municipality || '') + ', ' + (s.province || '');
    document.getElementById('v-machine').textContent      = s.machine      || '—';
    document.getElementById('v-machine-type').textContent = s.machineType  || '—';
    document.getElementById('v-date').textContent         = s.bookingDate  || '—';
    document.getElementById('v-status').innerHTML         =
        '<span class="badge badge-' + s.status.toLowerCase() + '">' + s.status + '</span>';
    document.getElementById('v-location').textContent     = s.farmLocation || '—';
    document.getElementById('v-barangay').textContent     = s.barangay     || '—';
    document.getElementById('v-municipality').textContent = s.municipality  || '—';
    document.getElementById('v-province').textContent     = s.province     || '—';
    document.getElementById('v-size').textContent         = fmtHa(s.farmSize);
    document.getElementById('v-rate').textContent         = fmt(s.rate);
    document.getElementById('v-size2').textContent        = fmtHa(s.farmSize);
    document.getElementById('v-total').textContent        = fmt(s.total);

    const nw = document.getElementById('v-notes-wrap');
    if (s.notes && s.notes.trim()) {
        document.getElementById('v-notes').textContent = s.notes;
        nw.style.display = 'block';
    } else { nw.style.display = 'none'; }

    fillPrintArea();
    openModal('viewModal');
}

/* ── Fill Print Area ── */
function fillPrintArea() {
    if (!selectedData) return;
    const s = selectedData;
    document.getElementById('print-date').textContent  = new Date().toLocaleDateString('en-US',{year:'numeric',month:'long',day:'numeric'});
    document.getElementById('pr-machine').textContent  = s.machine      || '—';
    document.getElementById('pr-type').textContent     = s.machineType  || '—';
    document.getElementById('pr-farmer').textContent   = s.farmer       || '—';
    document.getElementById('pr-phone').textContent    = s.farmerPhone  || '—';
    document.getElementById('pr-email').textContent    = s.farmerEmail  || '—';
    document.getElementById('pr-date').textContent     = s.bookingDate  || '—';
    document.getElementById('pr-status').textContent   = s.status       || '—';
    document.getElementById('pr-size').textContent     = fmtHa(s.farmSize);
    document.getElementById('pr-location').textContent = s.farmLocation || '—';
    document.getElementById('pr-barangay').textContent = s.barangay     || '—';
    document.getElementById('pr-muni').textContent     = s.municipality  || '—';
    document.getElementById('pr-province').textContent = s.province     || '—';
    document.getElementById('pr-rate').textContent     = fmt(s.rate);
    document.getElementById('pr-size2').textContent    = fmtHa(s.farmSize);
    document.getElementById('pr-total').textContent    = fmt(s.total);
    const pnw = document.getElementById('pr-notes-wrap');
    if (s.notes && s.notes.trim()) {
        document.getElementById('pr-notes').textContent = s.notes;
        pnw.style.display = 'block';
    } else { pnw.style.display = 'none'; }
}

/* ── Print ── */
function printSelected() {
    if (!selectedData) { alert('Please select a row first.'); return; }
    fillPrintArea();
    document.getElementById('printArea').style.display = 'block';
    window.print();
    document.getElementById('printArea').style.display = 'none';
}

/* ── Mark Complete ── */
function submitComplete() {
    if (!selectedData) { alert('Please select a row first.'); return; }
    if (selectedData.status !== 'Approved') { alert('Only Approved bookings can be marked as completed.'); return; }
    if (!confirm('Mark this work assignment as completed?')) return;
    document.getElementById('completeBookingId').value = selectedData.id;
    document.getElementById('completeForm').submit();
}
</script>
</body>
</html>