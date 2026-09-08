<?php
session_start();
include('dashboard_president.php');
require_once '../includes/db_connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'associations') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";

$stmt = $conn->prepare("SELECT id, name FROM associations WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) die("❌ Association not linked to this account.");
$association = $result->fetch_assoc();
$association_id   = $association['id'];
$association_name = $association['name'];

/* ── APPROVE / DECLINE ── */
if (isset($_POST['action'], $_POST['booking_id'])) {
    $booking_id = (int)$_POST['booking_id'];
    $status = ($_POST['action'] === 'approve') ? 'Approved' : 'Declined';
    $stmt2 = $conn->prepare("UPDATE bookings b JOIN machines m ON b.machine_id = m.id SET b.status = ? WHERE b.id = ? AND m.association_id = ?");
    $stmt2->bind_param("sii", $status, $booking_id, $association_id);
    if ($stmt2->execute()) {
        $message = "✅ Booking successfully " . ($status === 'Approved' ? 'approved' : 'declined') . ".";
    } else {
        $message = "❌ Error updating booking.";
    }
    header("Location: " . $_SERVER['PHP_SELF'] . "?msg=" . urlencode($message));
    exit();
}
if (isset($_GET['msg'])) $message = $_GET['msg'];

/* ── FETCH ALL BOOKINGS for this association ── */
$bookings_sql = "
    SELECT
        b.id AS booking_id,
        b.booking_date,
        b.farm_location,
        b.farm_size,
        b.notes,
        b.status,
        b.created_at,
        CONCAT(f.first_name, ' ', f.last_name) AS farmer_name,
        f.email    AS farmer_email,
        f.phone    AS farmer_phone,
        f.province AS farmer_province,
        f.municipality AS farmer_municipality,
        f.barangay AS farmer_barangay,
        m.machine_name,
        m.type     AS machine_type,
        m.price_per_hectare,
        fl.lot_number,
        fl.farm_location AS lot_location,
        fl.municipality,
        fl.barangay,
        fl.province,
        a.name AS association_name
    FROM bookings b
    JOIN machines m     ON b.machine_id = m.id
    JOIN farmers f      ON b.farmer_id  = f.id
    JOIN associations a ON m.association_id = a.id
    LEFT JOIN farmer_lots fl ON b.lot_id = fl.id
    WHERE m.association_id = ?
    ORDER BY
        CASE WHEN b.status='Pending'   THEN 1
             WHEN b.status='Approved'  THEN 2
             WHEN b.status='Completed' THEN 3
             ELSE 4 END,
        b.booking_date DESC, b.created_at DESC
";
$bstmt = $conn->prepare($bookings_sql);
$bstmt->bind_param("i", $association_id);
$bstmt->execute();
$bres = $bstmt->get_result();
$booking_rows = [];
while ($r = $bres->fetch_assoc()) $booking_rows[] = $r;
$bstmt->close();

/* ── Encode for JS ── */
$bookings_json = json_encode($booking_rows);

/* ── CALENDAR DATA ── */
$color_map = [
    'Pending'   => ['bg' => '#f59e0b', 'border' => '#d97706'],
    'Approved'  => ['bg' => '#3b82f6', 'border' => '#2563eb'],
    'Completed' => ['bg' => '#16a34a', 'border' => '#15803d'],
    'Declined'  => ['bg' => '#ef4444', 'border' => '#dc2626'],
];
$calendar_data = [];
foreach ($booking_rows as $row) {
    $colors   = $color_map[$row['status']] ?? ['bg' => '#6b7280', 'border' => '#4b5563'];
    $location = $row['lot_location'] ?: $row['farm_location'];
    $calendar_data[] = [
        'id'              => $row['booking_id'],
        'title'           => $row['farmer_name'] . ' — ' . $row['machine_name'],
        'start'           => $row['booking_date'],
        'backgroundColor' => $colors['bg'],
        'borderColor'     => $colors['border'],
        'textColor'       => '#ffffff',
        'extendedProps'   => [
            'status'          => $row['status'],
            'farmer'          => $row['farmer_name'],
            'phone'           => $row['farmer_phone'],
            'email'           => $row['farmer_email'],
            'machine'         => $row['machine_name'],
            'machine_type'    => $row['machine_type'],
            'lot'             => $row['lot_number'] ?? '',
            'location'        => $location,
            'municipality'    => $row['municipality'] ?? '',
            'barangay'        => $row['barangay'] ?? '',
            'farm_size'       => $row['farm_size'],
            'price'           => $row['price_per_hectare'],
            'notes'           => $row['notes'] ?? '',
        ]
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Association Reservations</title>
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.css">
<style>
    :root {
        --primary:      #16a34a;
        --primary-dark: #15803d;
        --primary-light:#dcfce7;
        --text:         #1f2937;
        --text-muted:   #6b7280;
        --border:       #d1d5db;
    }
    * { box-sizing: border-box; }
    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: var(--text); margin: 0; }

    /* ── Welcome / header ── */
    .usernames { color: #000000; font-size: 16px; font-weight: bold;
                 text-shadow: 1px 1px 3px rgba(0,0,0,0.6); margin-bottom: 6px; }
    .usernames a { color: #000000; margin-left: 15px; text-decoration: underline; font-weight: normal; }

    .content-wrapper { position: absolute; top: 120px; left: 0; right: 0; bottom: 0;
                       overflow-y: auto; padding: 16px; }

    h2 { font-size: 28px; color: #2d7a2d; font-weight: bold; text-align: center;
         text-shadow: 1px 1px 3px rgba(14,4,4,0.7); margin: 6px 0 12px; }

    /* ── Search bar ── */
    .search-bar { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
    .search-bar select,
    .search-bar input[type="text"],
    .search-bar button { padding: 8px 12px; font-size: 13px; border: 1px solid #ccc;
                         border-radius: 6px; font-family: inherit; }
    .search-bar select  { background: #fff; color: #000; cursor: pointer; min-width: 130px; }
    .search-bar input   { background: #fff; color: #000; min-width: 160px; }
    .search-bar button  { background-color: #2d7a2d; color: white; border: none; cursor: pointer;
                          display: inline-flex; align-items: center; gap: 6px; transition: background 0.2s; }
    .search-bar button:hover { background-color: #256725; }
    .search-bar .flatpickr-input {
        padding: 8px 12px !important; font-size: 13px !important; border: 1px solid #ccc !important;
        border-radius: 6px !important; background: #fff !important; color: #333 !important;
        cursor: pointer !important; width: 130px !important; box-sizing: border-box !important; font-family: inherit !important;
    }
    .search-bar label { color: #000000; font-weight: bold; font-size: 14px;
                        text-shadow: 1px 1px 3px rgba(14,4,4,0.7); white-space: nowrap; }

    /* ── Message ── */
    .message { padding: 12px 18px; border-radius: 8px; margin-bottom: 12px;
               display: flex; align-items: center; gap: 10px;
               background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; font-size: 14px; }
    .message.error { background: #fee2e2; color: #991b1b; border-left-color: #ef4444; }

    /* ── Table ── */
    .table-container { border: 1px solid var(--border); border-radius: 12px; overflow: hidden;
                       background: rgba(255,255,255,0.95); margin-bottom: 16px; }
    .table-scroll { overflow-x: auto; overflow-y: hidden; }
    .table-scroll table  { table-layout: fixed; }
    .table-scroll tbody  { display: block; max-height: 215px; overflow-y: auto; overflow-x: hidden; width: 100%; }
    .table-scroll thead  { display: table; width: 100%; table-layout: fixed; }
    .table-scroll tbody tr { display: table; width: 100%; table-layout: fixed; }

    table { width: 100%; border-collapse: collapse; font-size: 11px; min-width: 1200px; }

    thead { position: sticky; top: 0; z-index: 10;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark)); }
    thead th {
        color: white;
        text-align: center;
        padding: 10px 4px;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 10.5px;
        letter-spacing: 0.3px;
        white-space: normal;
        word-break: break-word;
        line-height: 1.35;
        vertical-align: middle;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    tbody tr { transition: background 0.15s ease; border-bottom: 1px solid var(--border); cursor: pointer; }
    tbody tr:hover        { background: var(--primary-light); }
    tbody tr.selected-row { background: #bbf7d0 !important; border-left: 4px solid var(--primary-dark); }
    tbody td {
        padding: 8px 5px;
        color: var(--text);
        vertical-align: middle;
        font-size: 11px;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        word-wrap: break-word;
        text-align: center;
        line-height: 1.3;
    }
    tbody td.td-left { text-align: left; }
    tbody tr:last-child { border-bottom: none; }

    /* ── Column widths (14 columns now) ── */
    thead th:nth-child(1),  tbody td:nth-child(1)  { width: 3%;  }   /* # */
thead th:nth-child(2),  tbody td:nth-child(2)  { width: 7%;  }   /* Date Applied */
thead th:nth-child(3),  tbody td:nth-child(3)  { width: 9%;  }   /* Farmer Name */
thead th:nth-child(4),  tbody td:nth-child(4)  { width: 9%;  }   /* Farmer Address */
thead th:nth-child(5),  tbody td:nth-child(5)  { width: 7%;  }   /* Farmer Phone */
thead th:nth-child(6),  tbody td:nth-child(6)  { width: 9%;  }   /* Farm Location */
thead th:nth-child(7),  tbody td:nth-child(7)  { width: 7%;  }   /* Farm Lot */
thead th:nth-child(8),  tbody td:nth-child(8)  { width: 6%;  }   /* Farm Size */
thead th:nth-child(9),  tbody td:nth-child(9)  { width: 7%;  }   /* Machine Type */
thead th:nth-child(10), tbody td:nth-child(10) { width: 9%;  }   /* Machine Name */
thead th:nth-child(11), tbody td:nth-child(11) { width: 7%;  }   /* Price/ha */
thead th:nth-child(12), tbody td:nth-child(12) { width: 8%;  }   /* Total Amount */
thead th:nth-child(13), tbody td:nth-child(13) { width: 8%;  }   /* Scheduled Date */
thead th:nth-child(14), tbody td:nth-child(14) { width: 9%;  }   /* Status */

    .badge { display: inline-block; padding: 3px 8px; border-radius: 12px;
             font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px; }


    .no-data { text-align: center; padding: 60px 20px; color: var(--text-muted); }
    .no-data i { font-size: 56px; color: var(--border); margin-bottom: 14px; display: block; }

    /* text helpers */
    .fw600 { font-weight: 600; }
    .fw700 { font-weight: 700; }
    .clr-green { color: #16a34a; }
    .clr-muted { color: #6b7280; font-size: 10px; }

    /* ── Action buttons ── */
    .action-buttons { display: flex; justify-content: center; gap: 10px;
                      margin-top: 14px; margin-bottom: 20px; flex-wrap: wrap; }
    .action-buttons button { padding: 10px 22px; border-radius: 6px; border: none;
                             color: white; cursor: pointer; font-size: 14px; font-weight: 600;
                             transition: background 0.2s, transform 0.2s;
                             display: inline-flex; align-items: center; gap: 8px; }
    .action-buttons button:hover { transform: scale(1.03); }
    .btn-approve  { background-color: #2d7a2d; }
    .btn-approve:hover  { background-color: #15803d !important; }
    .btn-decline  { background-color: #2d7a2d; }
    .btn-decline:hover  { background-color: #1a5c1a !important; }
    .btn-calendar { background-color: #2d7a2d; }
    .btn-calendar:hover { background-color: #1a5c1a !important; }
    .btn-print    { background-color: #2d7a2d; }
    .btn-print:hover    { background-color: #1a5c1a !important; }

    /* ── Modal ── */
    #viewModal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6);
                 z-index: 9999; justify-content: center; align-items: center; backdrop-filter: blur(3px); }
    .modal-inner { background: white; border-radius: 14px; width: 700px; max-width: 96%;
                   max-height: 90vh; overflow-y: auto;
                   box-shadow: 0 20px 40px rgba(0,0,0,0.25); }
    .modal-inner::-webkit-scrollbar { width: 5px; }
    .modal-inner::-webkit-scrollbar-thumb { background: #2d7a2d; border-radius: 6px; }
    .modal-head { padding: 16px 24px; border-radius: 14px 14px 0 0;
                  display: flex; justify-content: space-between; align-items: center;
                  position: sticky; top: 0; z-index: 5;
                  background: linear-gradient(135deg, #16a34a, #15803d); }
    .modal-head .title { color: white; font-size: 17px; font-weight: 700; }
    .modal-head .sub   { color: rgba(255,255,255,0.82); font-size: 12px; margin-top: 2px; }
    .modal-close { width: 32px; height: 32px; background: rgba(255,255,255,0.2);
                   border: none; border-radius: 50%; color: white; font-size: 18px;
                   cursor: pointer; display: flex; align-items: center; justify-content: center; }
    .modal-close:hover { background: rgba(255,255,255,0.35); }
    .modal-body { padding: 18px 20px 20px; }

    /* info boxes */
    .ibox { background: #f0fdf4; border-radius: 8px; padding: 10px 13px; border: 1px solid #e5f0e8; }
    .ibox-label { font-size: 10px; font-weight: 700; color: #6b7280;
                  text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px; }
    .ibox-value { font-size: 13px; font-weight: 600; color: #1f2937; line-height: 1.35; }
    .ibox-sub   { font-size: 11px; font-weight: 500; color: #6b7280; margin-top: 2px; }
    .ibox-total { background: #f0fdf4; border-radius: 8px; padding: 10px 13px; border: 1px solid #bbf7d0; }

    /* spinner */
    .spinner { border: 4px solid #e5e7eb; border-top: 4px solid #16a34a;
               border-radius: 50%; width: 36px; height: 36px;
               animation: spin 1s linear infinite; margin: 0 auto 12px; }
    @keyframes spin { 0%{transform:rotate(0deg)} 100%{transform:rotate(360deg)} }

    /* ── Print ── */
    #printArea { display: none; }

    /* ══ CALENDAR MODAL ══ */
    #calendarModal {
        display: none; position: fixed; inset: 0;
        background: rgba(0,0,0,0.6); backdrop-filter: blur(4px);
        justify-content: center; align-items: center;
        z-index: 10000; padding: 12px;
    }
    .cal-modal-box {
        background: #fff; border-radius: 16px;
        width: 100%; max-width: 1100px; height: 94vh;
        display: flex; flex-direction: column;
        box-shadow: 0 24px 60px rgba(0,0,0,0.28); overflow: hidden;
    }
    .cal-modal-head {
        background: linear-gradient(120deg, #1a5c1a, #2d7a2d);
        padding: 14px 20px;
        display: flex; align-items: center; justify-content: space-between;
        flex-shrink: 0; flex-wrap: wrap; gap: 8px;
    }
    .cal-modal-title { display: flex; align-items: center; gap: 12px; }
    .cal-modal-title .title-text { color: #fff; font-size: 1.05rem; font-weight: 700; }
    .cal-modal-title .title-sub  { color: rgba(255,255,255,0.8); font-size: 0.78rem; margin-top: 2px; }
    .cal-legend-wrap { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
    .cal-legend-pill {
        display: flex; align-items: center; gap: 5px;
        font-size: 12px; font-weight: 600; color: #fff;
        background: rgba(255,255,255,0.15); padding: 4px 10px; border-radius: 20px;
    }
    .cal-legend-dot { width: 10px; height: 10px; border-radius: 3px; flex-shrink: 0; }
    .cal-modal-close {
        background: rgba(255,255,255,0.15); border: none; color: #fff;
        font-size: 22px; width: 34px; height: 34px; border-radius: 50%;
        cursor: pointer; display: flex; align-items: center; justify-content: center;
        transition: background 0.2s, transform 0.2s; flex-shrink: 0;
    }
    .cal-modal-close:hover { background: rgba(255,255,255,0.28); transform: rotate(90deg); }
    .cal-modal-body { padding: 16px; flex: 1; overflow: hidden; display: flex; flex-direction: column; min-height: 0; }
    .cal-modal-body .fc { flex: 1; min-height: 0; height: 100%; }
    .cal-modal-body .fc-view-harness { flex: 1 !important; min-height: 0 !important; }
    .cal-modal-body .fc .fc-toolbar-title { font-size: 18px !important; font-weight: 700 !important; color: #1f2937; }
    .cal-modal-body .fc .fc-button-primary { background: #2d7a2d !important; border-color: #2d7a2d !important; font-weight: 600 !important; }
    .cal-modal-body .fc .fc-button-primary:hover { background: #1a5c1a !important; border-color: #1a5c1a !important; }
    .cal-modal-body .fc .fc-button-primary:not(:disabled).fc-button-active { background: #1a5c1a !important; border-color: #1a5c1a !important; }
    .cal-modal-body .fc .fc-col-header-cell { background: #2d7a2d; }
    .cal-modal-body .fc .fc-col-header-cell-cushion { color: white !important; font-weight: 700; text-decoration: none !important; }
    .cal-modal-body .fc .fc-daygrid-day-number { color: #374151; font-weight: 500; text-decoration: none !important; }
    .cal-modal-body .fc .fc-daygrid-day.fc-day-today { background: #f0fdf4 !important; }
    .cal-modal-body .fc .fc-daygrid-day.fc-day-today .fc-daygrid-day-number {
        background: #2d7a2d; color: white; border-radius: 50%;
        width: 26px; height: 26px; display: flex; align-items: center; justify-content: center;
    }
    .cal-modal-body .fc-event { border-radius: 5px !important; font-size: 12px !important; padding: 2px 5px !important; cursor: pointer !important; }
    .cal-modal-body .fc-event:hover { opacity: 0.85 !important; }
    .cal-modal-body .fc .fc-col-header,
    .cal-modal-body .fc .fc-daygrid-body,
    .cal-modal-body .fc table { width: 100% !important; }
    .cal-modal-body .fc .fc-scrollgrid { width: 100% !important; table-layout: fixed !important; }
    .cal-modal-body .fc .fc-col-header-cell,
    .cal-modal-body .fc .fc-daygrid-day { width: calc(100% / 7) !important; }
    .fc-month-select, .fc-year-select {
        padding: 5px 10px; font-size: 15px; font-weight: 700; color: #1f2937;
        border: 2px solid #d1d5db; border-radius: 8px; background: #fff;
        cursor: pointer; outline: none; transition: border-color 0.2s, box-shadow 0.2s; font-family: inherit;
    }
    .fc-month-select:focus, .fc-year-select:focus { border-color: #2d7a2d; box-shadow: 0 0 0 3px rgba(45,122,45,0.15); }
    .fc-month-select:hover, .fc-year-select:hover { border-color: #2d7a2d; }
    .fc-nav-selects { display: flex; align-items: center; gap: 8px; }

    @media print {
        body > *:not(#printArea) { display: none !important; }
        #printArea { display: block !important; padding: 24px 32px; }
        #printArea h2 { text-align: center; font-size: 20px; margin-bottom: 16px; }
        #printArea table { width: 100%; border-collapse: collapse; font-size: 11px; }
        #printArea thead th { background: #16a34a !important; color: white !important; padding: 8px 6px;
                              -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        #printArea tbody td { padding: 7px 6px; border-bottom: 1px solid #d1d5db; }
    }
</style>
</head>
<body>

<!-- Hidden print area -->
<div id="printArea">
    <h2>List of Reservations — <?= htmlspecialchars($association_name) ?></h2>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Reservation Date</th>
                <th>Farmer Name</th>
                <th>Farmer Address</th>
                <th>Contact No.</th>
                <th>Farm Location</th>
                <th>Farm Lot</th>
                <th>Farm Size</th>
                
                <th>Machine Name</th>
                <th>Machine Type</th>
                <th>Amount/ha</th>
                <th>Total Amount</th>
                <th>Scheduled </th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody id="printTableBody"></tbody>
    </table>
</div>

<div class="content-wrapper">

    <div class="usernames">
        Welcome <?= htmlspecialchars($association_name) ?>
        <a href="../logout.php">Logout</a>
    </div>

    <h2>List of Reservations</h2>

    <!-- ── Search bar ── -->
    <div class="search-bar">
        <select id="searchField" onchange="handleFieldChange()">
            <option value="all">All</option>
            <option value="farmer_name">Farmer Name</option>
            <option value="machine_type">Machine Type</option>
            <option value="machine_name">Machine Name</option>
            <option value="municipality">Municipality</option>
            <option value="barangay">Barangay</option>
            <option value="status">Status</option>
            <option value="date">Booking Date</option>
        </select>
        <div id="dynamicInputs" style="display:contents;"></div>
        <button id="searchBtn" onclick="doSearch()" style="display:none;">Search</button>
        <span style="margin-left:auto; color: #000000; font-weight:bold; font-size:14px;
                     text-shadow:1px 1px 3px rgba(0,0,0,0.5); white-space:nowrap;">
            Total Records: <span id="totalCount"><?= count($booking_rows) ?></span>
        </span>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
    <div class="message <?= strpos($message,'❌')!==false ? 'error' : '' ?>">
        <i class="fas fa-<?= strpos($message,'❌')!==false ? 'exclamation-circle' : 'check-circle' ?>"></i>
        <?= htmlspecialchars($message) ?>
    </div>
    <?php endif; ?>

    <!-- ── Table ── -->
    <div class="table-container">
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Reservation Date</th>
                        <th>Farmer Name</th>
                        <th>Farmer Address</th>
                        <th>Contact No.</th>
                        <th>Farm Location</th>
                        <th>Farm Lot</th>
                        <th>Farm Size</th>
                        <th>Machine Type</th>
                        <th>Machine Name</th>
                        <th>Amount / Hectare</th>
                        <th>Total Amount</th>
                        <th>Scheduled Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="reservationTable">
                   <?php if (count($booking_rows) > 0):
                    $i = 1;
                    foreach ($booking_rows as $r):
                        $farmSize = (float)($r['farm_size'] ?? 0);
                        $price    = (float)($r['price_per_hectare'] ?? 0);
                        $total    = $farmSize * $price;
                        $farmerAddr = trim(
                            ($r['farmer_province']     ? $r['farmer_province']     . ', ' : '') .
                            ($r['farmer_municipality'] ? $r['farmer_municipality'] . ', ' : '') .
                            ($r['farmer_barangay']     ?? ''),
                            ', '
                        );
                ?>
                <tr class="booking-row"
                    data-id="<?= $r['booking_id'] ?>"
                    data-status="<?= htmlspecialchars($r['status']) ?>"
                    title="Double-click to view details">
                    <td><?= $i++ ?></td>
                    <td><?= date('m/d/Y', strtotime($r['created_at'])) ?></td>
                    <td class="td-left">
                        <span class="fw600"><?= htmlspecialchars($r['farmer_name']) ?></span>
                    </td>
                    <td class="td-left">
                        <span><?= htmlspecialchars($farmerAddr ?: '—') ?></span>
                    </td>
                    <td>
                        <span class="fw600"><?= htmlspecialchars($r['farmer_phone'] ?? '—') ?></span>
                    </td>
                    <td class="td-left">
                        <?php
                            $loc = $r['lot_location'] ?: ($r['farm_location'] ?: '—');
                            $locAddr = trim(
                                ($r['municipality'] ? $r['municipality'] . ', ' : '') .
                                ($r['barangay']     ?? ''),
                                ', '
                            );
                        ?>
                        <span class="fw600"><?= htmlspecialchars($loc) ?></span><br>
                        <span class="clr-muted"><?= htmlspecialchars($locAddr ?: '') ?></span>
                    </td>
                    <td>
                        <span class="fw700"><?= htmlspecialchars($r['lot_number'] ?? '—') ?></span>
                    </td>
                    <td>
                        <span class="fw600"><?= number_format($farmSize, 2) ?> ha</span>
                    </td>
                    <td><?= htmlspecialchars($r['machine_type']) ?></td>
                    <td class="fw600"><?= htmlspecialchars($r['machine_name']) ?></td>
                    <td class="fw600">₱<?= number_format($price, 2) ?></td>
                    <td class="fw700 clr-green">₱<?= number_format($total, 2) ?></td>
                    <td><?= date('m/d/Y', strtotime($r['booking_date'])) ?></td>
                    <td><span class="badge badge-<?= $r['status'] ?>"><?= $r['status'] ?></span></td>
                </tr>
                <?php endforeach; else: ?>
                <tr>
                    <td colspan="14" class="no-data">
                        <i class="fas fa-inbox"></i>
                        <p>No records found</p>
                    </td>
                </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ── Action Buttons ── -->
    <div class="action-buttons">
        <form method="POST" id="actionForm">
            <input type="hidden" name="booking_id" id="action-booking-id">
            <input type="hidden" name="action"     id="action-type">
        </form>
        <button class="btn-approve" id="btnApprove" onclick="submitAction('approve')">Approve</button>
        <button class="btn-decline" id="btnDecline" onclick="submitAction('decline')">Decline</button>
        <button class="btn-print" id="btnPrint" onclick="printList()">Print</button>
    </div>

</div><!-- /.content-wrapper -->


<!-- ══════════════════════════════════════════════
     VIEW RESERVATION MODAL
══════════════════════════════════════════════ -->
<div id="viewModal">
  <div class="modal-inner">
    <div class="modal-head">
      <div>
        <div class="title" id="modalTitle">Reservation Details</div>
        <div class="sub"   id="modalSubtitle">—</div>
      </div>
      <div style="display:flex; gap:8px; align-items:center;">
        <button onclick="printSingle()" style="padding:6px 14px; background:rgba(255,255,255,0.2);
                color:white; border:1px solid rgba(255,255,255,0.35); border-radius:6px;
                font-size:13px; font-weight:600; cursor:pointer;">
            <i class="fas fa-print"></i> Print
        </button>
        <button class="modal-close" onclick="closeModal()">&times;</button>
      </div>
    </div>
    <div class="modal-body" id="modalBody">
        <div style="text-align:center;padding:40px;color:#6b7280;">
            <div class="spinner"></div>Loading...
        </div>
    </div>
  </div>
</div>


<!-- ══════════════════════════════════════════════
     CALENDAR MODAL
══════════════════════════════════════════════ -->
<div id="calendarModal">
  <div class="cal-modal-box">
    <div class="cal-modal-head">
      <div class="cal-modal-title">
        <i class="fas fa-calendar-alt" style="color:#fff; font-size:22px;"></i>
        <div>
          <div class="title-text">Reservation Calendar</div>
          <div class="title-sub">Click an event to view details</div>
        </div>
      </div>
      <div style="display:flex; align-items:center; gap:14px; flex-wrap:wrap;">
        <div class="cal-legend-wrap">
          <div class="cal-legend-pill"><div class="cal-legend-dot" style="background:#f59e0b;"></div>Pending</div>
          <div class="cal-legend-pill"><div class="cal-legend-dot" style="background:#3b82f6;"></div>Approved</div>
          <div class="cal-legend-pill"><div class="cal-legend-dot" style="background:#16a34a;"></div>Completed</div>
          <div class="cal-legend-pill"><div class="cal-legend-dot" style="background:#ef4444;"></div>Declined</div>
        </div>
        <button class="cal-modal-close" onclick="closeCalendarModal()">&times;</button>
      </div>
    </div>
    <div class="cal-modal-body">
      <div id="modalCalendar" style="flex:1; min-height:0; height:100%;"></div>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.js"></script>
<script>
/* ── All booking data ── */
const allBookings    = <?= $bookings_json ?>;
const calendarEvents = <?= json_encode($calendar_data) ?>;

let selectedBooking   = null;
let visibleBookings   = [...allBookings];
let fpFrom = null, fpTo = null;

/* ══════════════════════════════════════
   SEARCH / FILTER
══════════════════════════════════════ */
function handleFieldChange() {
    const field = document.getElementById('searchField').value;
    const area  = document.getElementById('dynamicInputs');
    const btn   = document.getElementById('searchBtn');

    if (fpFrom) { fpFrom.destroy(); fpFrom = null; }
    if (fpTo)   { fpTo.destroy();   fpTo   = null; }
    area.innerHTML = '';
    btn.style.display = (field === 'all') ? 'none' : 'inline-flex';

    if (field === 'farmer_name') {
        area.innerHTML = `<input type="text" id="inp1" placeholder="Enter farmer name..." style="min-width:200px;">`;
    }
    if (field === 'machine_name') {
        area.innerHTML = `<input type="text" id="inp1" placeholder="Enter machine name..." style="min-width:200px;">`;
    }
    if (field === 'machine_type') {
        area.innerHTML = `
            <select id="inp1" style="min-width:150px;">
                <option value="" disabled selected hidden>All Types</option>
                <option value="Tractor">Tractor</option>
                <option value="Harvester">Harvester</option>
            </select>`;
    }
    if (field === 'municipality') {
        area.innerHTML = `<input type="text" id="inp1" placeholder="Enter municipality..." style="min-width:200px;">`;
    }
    if (field === 'barangay') {
        area.innerHTML = `
            <input type="text" id="inp1" placeholder="Enter municipality..." style="min-width:155px;">
            <input type="text" id="inp2" placeholder="Enter barangay..."    style="min-width:155px;">`;
    }
    if (field === 'status') {
        area.innerHTML = `
            <select id="inp1" style="min-width:140px;">
                <option value="" disabled selected hidden>All Status</option>
                <option value="Pending">Pending</option>
                <option value="Approved">Approved</option>
                <option value="Completed">Completed</option>
                <option value="Declined">Declined</option>
                <option value="Canceled">Canceled</option>
            </select>
            <label>From</label>
            <input type="text" id="filterFrom" placeholder="mm/dd/yyyy" readonly style="width:130px;">
            <label>To</label>
            <input type="text" id="filterTo" placeholder="mm/dd/yyyy" readonly style="width:130px;">`;
        setTimeout(() => {
            fpFrom = flatpickr('#filterFrom', { dateFormat: 'm/d/Y', allowInput: false });
            fpTo   = flatpickr('#filterTo',   { dateFormat: 'm/d/Y', allowInput: false });
        }, 0);
    }
    if (field === 'date') {
        area.innerHTML = `
            <label>From</label>
            <input type="text" id="filterFrom" placeholder="mm/dd/yyyy" readonly style="width:130px;">
            <label>To</label>
            <input type="text" id="filterTo" placeholder="mm/dd/yyyy" readonly style="width:130px;">`;
        setTimeout(() => {
            fpFrom = flatpickr('#filterFrom', { dateFormat: 'm/d/Y', allowInput: false });
            fpTo   = flatpickr('#filterTo',   { dateFormat: 'm/d/Y', allowInput: false });
        }, 0);
    }

    if (field === 'all') renderTable(allBookings);
}

function toYMD(s) {
    if (!s) return '';
    const p = s.split('/');
    return p.length !== 3 ? '' : p[2]+'-'+p[0].padStart(2,'0')+'-'+p[1].padStart(2,'0');
}

function doSearch() {
    const field = document.getElementById('searchField').value;
    let filtered = [...allBookings];

    if (field === 'farmer_name') {
        const term = (document.getElementById('inp1')?.value || '').toLowerCase();
        filtered = filtered.filter(r => r.farmer_name.toLowerCase().includes(term));
    }
    if (field === 'machine_name') {
        const term = (document.getElementById('inp1')?.value || '').toLowerCase();
        filtered = filtered.filter(r => r.machine_name.toLowerCase().includes(term));
    }
    if (field === 'machine_type') {
        const term = document.getElementById('inp1')?.value || '';
        if (term) filtered = filtered.filter(r => r.machine_type === term);
    }
    if (field === 'municipality') {
        const term = (document.getElementById('inp1')?.value || '').toLowerCase();
        filtered = filtered.filter(r => (r.municipality || '').toLowerCase().includes(term));
    }
    if (field === 'barangay') {
        const muni = (document.getElementById('inp1')?.value || '').toLowerCase();
        const brgy = (document.getElementById('inp2')?.value || '').toLowerCase();
        if (muni) filtered = filtered.filter(r => (r.municipality || '').toLowerCase().includes(muni));
        if (brgy) filtered = filtered.filter(r => (r.barangay || '').toLowerCase().includes(brgy));
    }
    if (field === 'status') {
        const st   = document.getElementById('inp1')?.value || '';
        const from = toYMD(document.getElementById('filterFrom')?.value || '');
        const to   = toYMD(document.getElementById('filterTo')?.value   || '');
        if (st)   filtered = filtered.filter(r => r.status === st);
        if (from) filtered = filtered.filter(r => r.booking_date >= from);
        if (to)   filtered = filtered.filter(r => r.booking_date <= to);
    }
    if (field === 'date') {
        const from = toYMD(document.getElementById('filterFrom')?.value || '');
        const to   = toYMD(document.getElementById('filterTo')?.value   || '');
        if (from) filtered = filtered.filter(r => r.booking_date >= from);
        if (to)   filtered = filtered.filter(r => r.booking_date <= to);
    }

    renderTable(filtered);
}

/* ══════════════════════════════════════
   RENDER TABLE
══════════════════════════════════════ */
function renderTable(rows) {
    visibleBookings = rows;
    resetSelection();
    document.getElementById('totalCount').textContent = rows.length;
    const tbody = document.getElementById('reservationTable');
    tbody.innerHTML = '';

    if (!rows.length) {
        tbody.innerHTML = `<tr><td colspan="14" class="no-data">
            <i class="fas fa-inbox"></i><p>No records found</p></td></tr>`;
        return;
    }

    rows.forEach((r, idx) => {
        const farmSize = parseFloat(r.farm_size) || 0;
        const price    = parseFloat(r.price_per_hectare) || 0;
        const total    = farmSize * price;
        const farmerAddr = [r.farmer_province, r.farmer_municipality, r.farmer_barangay].filter(Boolean).join(', ') || '—';

        const tr = document.createElement('tr');
        tr.className      = 'booking-row';
        tr.dataset.id     = r.booking_id;
        tr.dataset.status = r.status;
        tr.title          = 'Double-click to view details';
        tr.innerHTML =
            `<td>${idx + 1}</td>` +
            `<td>${fmtDate(r.created_at)}</td>` +
            `<td class="td-left"><span class="fw600">${esc(r.farmer_name)}</span></td>` +
            `<td class="td-left">${esc(farmerAddr)}</td>` +
            `<td class="fw600">${esc(r.farmer_phone || '—')}</td>` +
            `<td class="td-left"><span class="fw600">${esc(r.lot_location || r.farm_location || '—')}</span><br><span class="clr-muted">${esc([r.municipality, r.barangay].filter(Boolean).join(', '))}</span></td>` +
            `<td><span class="fw700">${esc(r.lot_number||'—')}</span></td>` +
            `<td><span class="fw600">${farmSize.toFixed(2)} ha</span></td>` +
            `<td>${esc(r.machine_type)}</td>` +
            `<td class="fw600">${esc(r.machine_name)}</td>` +
            `<td class="fw600">₱${fmt(price)}</td>` +
            `<td class="fw700 clr-green">₱${fmt(total)}</td>` +
            `<td>${fmtDate(r.booking_date)}</td>` +
            `<td><span class="badge badge-${esc(r.status)}">${esc(r.status)}</span></td>`;

        tr.addEventListener('click',    () => selectRow(tr, r));
        tr.addEventListener('dblclick', () => { selectRow(tr, r); openModal(); });
        tbody.appendChild(tr);
    });
}

/* ══════════════════════════════════════
   ROW SELECTION
══════════════════════════════════════ */
function selectRow(row, booking) {
    document.querySelectorAll('.booking-row').forEach(r => r.classList.remove('selected-row'));
    row.classList.add('selected-row');
    selectedBooking = booking;
}
function resetSelection() { selectedBooking = null; }

/* Initial row events (PHP-rendered rows) */
document.querySelectorAll('.booking-row').forEach(row => {
    const id = row.dataset.id;
    const booking = allBookings.find(b => String(b.booking_id) === String(id));
    if (!booking) return;
    row.addEventListener('click',    () => selectRow(row, booking));
    row.addEventListener('dblclick', () => { selectRow(row, booking); openModal(); });
});

/* ══════════════════════════════════════
   APPROVE / DECLINE
══════════════════════════════════════ */
function submitAction(type) {
    if (!selectedBooking) { alert('Please select a booking row first.'); return; }
    if (selectedBooking.status !== 'Pending') { alert('Only Pending bookings can be approved or declined.'); return; }
    if (!confirm('Are you sure you want to ' + type + ' this booking?')) return;
    document.getElementById('action-booking-id').value = selectedBooking.booking_id;
    document.getElementById('action-type').value       = type;
    document.getElementById('actionForm').submit();
}

/* ══════════════════════════════════════
   VIEW MODAL
══════════════════════════════════════ */
function openModal() {
    if (!selectedBooking) return;
    const r        = selectedBooking;
    const farmSize = parseFloat(r.farm_size) || 0;
    const price    = parseFloat(r.price_per_hectare) || 0;
    const total    = farmSize * price;
    const location = r.lot_location || r.farm_location || '—';
    const fullAddr = [r.province, r.municipality, r.barangay].filter(Boolean).join(', ') || '—';
    const farmerAddr = [r.farmer_province, r.farmer_municipality, r.farmer_barangay].filter(Boolean).join(', ') || '—';

    const sc = {
        Pending:   { bg:'#fef3c7', color:'#92400e' },
        Approved:  { bg:'#d1fae5', color:'#065f46' },
        Completed: { bg:'#dbeafe', color:'#1e40af' },
        Declined:  { bg:'#fee2e2', color:'#991b1b' },
    }[r.status] || { bg:'#f3f4f6', color:'#374151' };

    document.getElementById('modalTitle').textContent    = 'Reservation #' + String(r.booking_id).padStart(5, '0');
    document.getElementById('modalSubtitle').textContent =
        'Applied: ' + fmtDate(r.created_at) + '  |  Booking: ' + fmtDate(r.booking_date);

    document.getElementById('modalBody').innerHTML = `
      <div style="display:flex;justify-content:flex-end;margin-bottom:12px;">
        <span style="background:${sc.bg};color:${sc.color};padding:5px 16px;border-radius:999px;
                     font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;">
          ${esc(r.status)}
        </span>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px;">
        ${iBox('Date Applied',  fmtDate(r.created_at))}
        ${iBox('Booking Date',  fmtDate(r.booking_date))}
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:8px;margin-bottom:8px;">
        ${iBox('Farmer Name',   esc(r.farmer_name))}
        <div class="ibox">
          <div class="ibox-label">Machine</div>
          <div class="ibox-value">${esc(r.machine_type||'')}</div>
          <div class="ibox-sub">${esc(r.machine_name||'')}</div>
        </div>
        <div class="ibox">
          <div class="ibox-label">Farmer Contact</div>
          <div class="ibox-value" style="font-size:12px;">${esc(r.farmer_phone||'—')}</div>
          <div class="ibox-sub">${esc(r.farmer_email||'')}</div>
        </div>
        
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:8px;">
        <div class="ibox">
          <div class="ibox-label">Lot / Farm Size</div>
          <div class="ibox-value" style="font-size:12px;">${esc(r.lot_number||'—')}</div>
          <div class="ibox-sub" style="font-weight:700;color:#1f2937;font-size:13px;">${farmSize.toFixed(2)} ha</div>
        </div>
        <div class="ibox">
          <div class="ibox-label">Farmer Address</div>
          <div class="ibox-value" style="font-size:11px;">${esc(farmerAddr)}</div>
        </div>
        ${iBox('Price / ha', '₱' + fmt(price))}
        <div class="ibox-total">
          <div class="ibox-label">Total Amount</div>
          <div style="font-size:18px;font-weight:800;color:#16a34a;margin-top:2px;">₱${fmt(total)}</div>
        </div>
      </div>
      <div style="margin-bottom:${r.notes?'8px':'0'};">
        <div class="ibox">
          <div class="ibox-label">Farm Location</div>
          <div class="ibox-value">${esc(location)}</div>
          <div class="ibox-sub">${esc(fullAddr)}</div>
        </div>
      </div>
      ${r.notes ? `<div style="margin-top:8px;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;
                               padding:10px 13px;font-size:12px;color:#92400e;">
        <span style="font-weight:700;font-size:10px;text-transform:uppercase;letter-spacing:.4px;">Notes:&nbsp;</span>${esc(r.notes)}
      </div>` : ''}
      <div style="margin-top:12px;padding-top:8px;border-top:1px solid #e5e7eb;
                  display:flex;justify-content:space-between;font-size:10px;color:#9ca3af;">
        <span>Booking ID: #${String(r.booking_id).padStart(5,'0')}</span>
        <span>Status: ${esc(r.status)}</span>
      </div>`;

    document.getElementById('viewModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    document.getElementById('viewModal').style.display = 'none';
    document.body.style.overflow = '';
}

/* ══════════════════════════════════════
   PRINT
══════════════════════════════════════ */
function printList() {
    if (!visibleBookings.length) { alert('No records to print.'); return; }
    const pb = document.getElementById('printTableBody');
    pb.innerHTML = '';
    visibleBookings.forEach((r, idx) => {
        const sz    = parseFloat(r.farm_size) || 0;
        const price = parseFloat(r.price_per_hectare) || 0;
        const farmerAddr = [r.farmer_province, r.farmer_municipality, r.farmer_barangay].filter(Boolean).join(', ') || '—';
        const tr = document.createElement('tr');
        tr.innerHTML =
            `<td>${idx+1}</td>` +
            `<td>${fmtDate(r.created_at)}</td>` +
            `<td>${esc(r.farmer_name)}</td>` +
            `<td>${esc(farmerAddr)}</td>` +
            `<td>${esc(r.farmer_phone || '—')}</td>` +
            `<td>${esc(r.lot_location || r.farm_location || '—')}</td>` +
            `<td>${esc(r.lot_number||'—')}</td>` +
            `<td>${sz.toFixed(2)} ha</td>` +
            `<td>${esc(r.machine_name)}</td>` +
            `<td>${esc(r.machine_type)}</td>` +
            `<td>₱${fmt(price)}</td>` +
            `<td>₱${fmt(sz * price)}</td>` +
            `<td>${fmtDate(r.booking_date)}</td>` +
            `<td>${esc(r.status)}</td>`;
        pb.appendChild(tr);
    });
    document.getElementById('printArea').style.display = 'block';
    window.addEventListener('afterprint', function h() {
        document.getElementById('printArea').style.display = 'none';
        window.removeEventListener('afterprint', h);
    });
    window.print();
}

function printSingle() {
    if (!selectedBooking) return;
    const r     = selectedBooking;
    const sz    = parseFloat(r.farm_size) || 0;
    const price = parseFloat(r.price_per_hectare) || 0;
    const farmerAddr = [r.farmer_province, r.farmer_municipality, r.farmer_barangay].filter(Boolean).join(', ') || '—';
    const pb = document.getElementById('printTableBody');
    pb.innerHTML = '';
    const tr = document.createElement('tr');
    tr.innerHTML =
        `<td>1</td>` +
        `<td>${fmtDate(r.created_at)}</td>` +
        `<td>${esc(r.farmer_name)}</td>` +
        `<td>${esc(farmerAddr)}</td>` +
        `<td>${esc(r.farmer_phone || '—')}</td>` +
        `<td>${esc(r.lot_location || r.farm_location || '—')}</td>` +
        `<td>${esc(r.lot_number||'—')}</td>` +
        `<td>${sz.toFixed(2)} ha</td>` +
        `<td>${esc(r.machine_name)}</td>` +
        `<td>${esc(r.machine_type)}</td>` +
        `<td>₱${fmt(price)}</td>` +
        `<td>₱${fmt(sz * price)}</td>` +
        `<td>${fmtDate(r.booking_date)}</td>` +
        `<td>${esc(r.status)}</td>`;
    pb.appendChild(tr);
    closeModal();
    document.getElementById('printArea').style.display = 'block';
    window.addEventListener('afterprint', function h() {
        document.getElementById('printArea').style.display = 'none';
        window.removeEventListener('afterprint', h);
    });
    window.print();
}

/* ══════════════════════════════════════
   CALENDAR MODAL
══════════════════════════════════════ */
let modalCalInstance = null;

function openCalendarModal() {
    const modal = document.getElementById('calendarModal');
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';

    if (!modalCalInstance) {
        modalCalInstance = new FullCalendar.Calendar(
            document.getElementById('modalCalendar'), {
            initialView:    'dayGridMonth',
            height:         '100%',
            headerToolbar:  { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek' },
            buttonText:     { today: 'today', month: 'month', week: 'week' },
            events:         calendarEvents,
            dayMaxEvents:   3,
            fixedWeekCount: false,
            eventDisplay:   'block',
            eventDidMount: function(info) {
                const p = info.event.extendedProps;
                info.el.title = p.farmer + ' | ' + p.machine + ' | ' + p.status;
            },
            eventClick: function(info) {
                openCalendarEventDetail(info.event);
            },
            datesSet: function() {
                setTimeout(injectCalendarSelects, 50);
            }
        });
        modalCalInstance.render();
        setTimeout(injectCalendarSelects, 150);
    } else {
        setTimeout(() => { modalCalInstance.updateSize(); injectCalendarSelects(); }, 60);
    }
}

function injectCalendarSelects() {
    if (!modalCalInstance) return;
    document.querySelectorAll('.fc-nav-selects').forEach(el => el.remove());
    const toolbar = document.querySelector('#modalCalendar .fc-toolbar-chunk:nth-child(2)');
    if (!toolbar) return;
    const currentDate  = modalCalInstance.getDate();
    const currentYear  = currentDate.getFullYear();
    const currentMonth = currentDate.getMonth();
    const monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    const monthOpts = monthNames.map((m, i) =>
        `<option value="${i}"${i === currentMonth ? ' selected' : ''}>${m}</option>`
    ).join('');
    let yearOpts = '';
    for (let y = 2000; y <= 2040; y++) {
        yearOpts += `<option value="${y}"${y === currentYear ? ' selected' : ''}>${y}</option>`;
    }
    const wrap = document.createElement('div');
    wrap.className = 'fc-nav-selects';
    wrap.innerHTML =
        `<select class="fc-month-select" onchange="calJumpTo()">${monthOpts}</select>` +
        `<select class="fc-year-select"  onchange="calJumpTo()">${yearOpts}</select>`;
    const title = toolbar.querySelector('.fc-toolbar-title');
    if (title) {
        title.style.display = 'none';
        toolbar.insertBefore(wrap, title);
    }
}

function calJumpTo() {
    const monthSel = document.querySelector('.fc-month-select');
    const yearSel  = document.querySelector('.fc-year-select');
    if (!monthSel || !yearSel || !modalCalInstance) return;
    const month = parseInt(monthSel.value);
    const year  = parseInt(yearSel.value);
    if (isNaN(month) || isNaN(year)) return;
    modalCalInstance.gotoDate(new Date(year, month, 1));
}

function closeCalendarModal() {
    document.getElementById('calendarModal').style.display = 'none';
    document.body.style.overflow = '';
}

function openCalendarEventDetail(event) {
    const p = event.extendedProps;
    const farmSize = parseFloat(p.farm_size) || 0;
    const price    = parseFloat(p.price)     || 0;
    const total    = farmSize * price;
    const sc = {
        Pending:   { bg:'#fef3c7', color:'#92400e' },
        Approved:  { bg:'#d1fae5', color:'#065f46' },
        Completed: { bg:'#dbeafe', color:'#1e40af' },
        Declined:  { bg:'#fee2e2', color:'#991b1b' },
    }[p.status] || { bg:'#f3f4f6', color:'#374151' };
    const d       = new Date(event.startStr + 'T00:00:00');
    const dateStr = d.toLocaleDateString('en-US', { year:'numeric', month:'long', day:'numeric' });

    document.getElementById('modalTitle').textContent    = p.farmer + ' — ' + p.machine;
    document.getElementById('modalSubtitle').textContent = 'Booking: ' + dateStr;

    document.getElementById('modalBody').innerHTML = `
      <div style="display:flex;justify-content:flex-end;margin-bottom:12px;">
        <span style="background:${sc.bg};color:${sc.color};padding:5px 16px;border-radius:999px;
                     font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;">
          ${esc(p.status)}
        </span>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:8px;margin-bottom:8px;">
        ${iBox('Farmer Name',   esc(p.farmer))}
        <div class="ibox">
          <div class="ibox-label">Machine</div>
          <div class="ibox-value">${esc(p.machine_type||'')}</div>
          <div class="ibox-sub">${esc(p.machine||'')}</div>
        </div>
        <div class="ibox">
          <div class="ibox-label">Farmer Contact</div>
          <div class="ibox-value" style="font-size:12px;">${esc(p.phone||'—')}</div>
          <div class="ibox-sub">${esc(p.email||'')}</div>
        </div>
        <div class="ibox">
          <div class="ibox-label">Lot / Farm Size</div>
          <div class="ibox-value" style="font-size:12px;">${esc(p.lot||'—')}</div>
          <div class="ibox-sub" style="font-weight:700;color:#1f2937;font-size:13px;">${farmSize.toFixed(2)} ha</div>
        </div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr 2fr;gap:8px;margin-bottom:${p.notes?'8px':'0'};">
        ${iBox('Price / ha', '₱' + fmt(price))}
        <div class="ibox-total">
          <div class="ibox-label">Total Amount</div>
          <div style="font-size:18px;font-weight:800;color:#16a34a;margin-top:2px;">₱${fmt(total)}</div>
        </div>
        <div class="ibox">
          <div class="ibox-label">Farm Location</div>
          <div class="ibox-value">${esc(p.location||'—')}</div>
          <div class="ibox-sub">${[p.municipality,p.barangay].filter(Boolean).join(', ')||''}</div>
        </div>
      </div>
      ${p.notes ? `<div style="margin-top:8px;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;
                               padding:10px 13px;font-size:12px;color:#92400e;">
        <span style="font-weight:700;font-size:10px;text-transform:uppercase;letter-spacing:.4px;">Notes:&nbsp;</span>${esc(p.notes)}
      </div>` : ''}`;

    closeCalendarModal();
    document.getElementById('viewModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

document.getElementById('calendarModal').addEventListener('click', function(e) {
    if (e.target === this) closeCalendarModal();
});

/* ══════════════════════════════════════
   HELPERS
══════════════════════════════════════ */
function iBox(label, value) {
    return `<div class="ibox"><div class="ibox-label">${label}</div><div class="ibox-value">${value}</div></div>`;
}
function fmtDate(d) {
    if (!d) return 'N/A';
    const dt = new Date(d);
    return String(dt.getMonth()+1).padStart(2,'0') + '/' +
           String(dt.getDate()).padStart(2,'0') + '/' + dt.getFullYear();
}
function fmt(n) {
    return parseFloat(n||0).toLocaleString('en-US', { minimumFractionDigits:2, maximumFractionDigits:2 });
}
function esc(t) {
    if (!t) return '';
    return String(t).replace(/[&<>"']/g, c =>
        ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
}

document.getElementById('viewModal').addEventListener('click', function(e) { if (e.target === this) closeModal(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeModal(); closeCalendarModal(); } });
document.addEventListener('DOMContentLoaded', () => handleFieldChange());
</script>
</body>
</html>