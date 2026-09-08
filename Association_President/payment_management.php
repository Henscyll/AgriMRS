<?php
session_start();
require_once '../includes/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'associations') {
    header('Location: ../login.php');
    exit;
}

$association_id = $_SESSION['association_id'];
include('dashboard_president.php');
$association_id = $_SESSION['association_id'];
$user_id = $_SESSION['user_id'] ?? null;

/* ── Fetch association name ── */
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
   PROCESS PAYMENT
================================ */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['record_payment'])) {
    $ledger_id = (int)$_POST['ledger_id'];
    $amount    = (float)$_POST['amount'];
    $notes     = trim($_POST['notes'] ?? '');

    /* Payment date comes from the hidden input (defaults to today, capped at today) */
    $posted_date = $_POST['payment_date'] ?? '';
    $dt = DateTime::createFromFormat('Y-m-d', $posted_date);
    if ($dt && $dt->format('Y-m-d') === $posted_date && $posted_date <= date('Y-m-d')) {
        $payment_date = $posted_date;
    } else {
        $payment_date = date('Y-m-d');
    }

    if ($amount <= 0) {
        $error = "Amount must be greater than zero.";
    } else {
        $check_sql = "SELECT balance, payment_status FROM payment_ledger WHERE id = ? AND association_id = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("ii", $ledger_id, $association_id);
        $stmt->execute();
        $ledger_row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$ledger_row) {
            $error = "Ledger not found.";
        } elseif ($ledger_row['payment_status'] === 'Paid') {
            $error = "This account is already fully paid.";
        } elseif ($amount > $ledger_row['balance']) {
            $error = "Amount (₱" . number_format($amount, 2) . ") exceeds balance (₱" . number_format($ledger_row['balance'], 2) . ").";
        } else {
            /* Use submitted OR number or generate a default one */
            $posted_or = trim($_POST['or_number'] ?? '');
            if (!empty($posted_or)) {
                $new_or_num = $posted_or;
            } else {
                $new_or_num = 'OR-' . date('Ymd') . '-' . str_pad($ledger_id, 5, '0', STR_PAD_LEFT) . '-' . time();
            }

            /* Check or_number in payment_transactions table to guarantee uniqueness */
            $check_or = $conn->prepare("SELECT id FROM payment_transactions WHERE or_number = ?");
            $check_or->bind_param("s", $new_or_num);
            $check_or->execute();
            if ($check_or->get_result()->fetch_assoc()) {
                $new_or_num = 'OR-' . date('Ymd') . '-' . str_pad($ledger_id, 5, '0', STR_PAD_LEFT) . '-' . (time() + rand(1, 9999));
            }
            $check_or->close();

            $ins = $conn->prepare("INSERT INTO payment_transactions (ledger_id, or_number, amount, payment_date, collected_by, notes) VALUES (?,?,?,?,?,?)");
            $ins->bind_param("isdsis", $ledger_id, $new_or_num, $amount, $payment_date, $user_id, $notes);
            if ($ins->execute()) {
                $message = "Payment recorded successfully.";
            } else {
                $error = "Failed to record payment.";
                $new_or_num = "";
            }
            $ins->close();
        }
    }
}

/* ===============================
   FILTER PARAMETERS
================================ */
$search_field  = $_GET['search_field']   ?? 'All';
$search_term   = $_GET['search_term']    ?? '';
$status_filter = $_GET['statusDropdown'] ?? '';
$type_filter   = $_GET['typeDropdown']   ?? '';
$date_from     = $_GET['date_from']      ?? '';
$date_to       = $_GET['date_to']        ?? '';
$muni_term     = $_GET['muni_term']      ?? '';
$brgy_term     = $_GET['brgy_term']      ?? '';
$field_changed = $_GET['field_changed']  ?? '0';

/* ===============================
   BUILD WHERE CONDITIONS
================================ */
$where_conditions = ["pl.association_id = ?"];
$params      = [$association_id];
$param_types = "i";

if ($field_changed !== '1') {
    if ($search_field === 'farmer_name' && !empty($search_term)) {
        $escaped = $conn->real_escape_string($search_term);
        $where_conditions[] = "(f.first_name LIKE '%$escaped%' OR f.last_name LIKE '%$escaped%' OR CONCAT(f.first_name,' ',f.last_name) LIKE '%$escaped%')";
    } elseif ($search_field === 'municipality' && !empty($muni_term)) {
        $where_conditions[] = "fl.municipality LIKE '%" . $conn->real_escape_string($muni_term) . "%'";
    } elseif ($search_field === 'barangay') {
        if (!empty($muni_term)) {
            $where_conditions[] = "fl.municipality LIKE '%" . $conn->real_escape_string($muni_term) . "%'";
        }
        if (!empty($brgy_term)) {
            $where_conditions[] = "fl.barangay LIKE '%" . $conn->real_escape_string($brgy_term) . "%'";
        }
    } elseif ($search_field === 'machine_type' && !empty($type_filter)) {
        $where_conditions[] = "m.type = '" . $conn->real_escape_string($type_filter) . "'";
    } elseif ($search_field === 'Status') {
        if (!empty($status_filter)) {
            if ($status_filter === 'overdue') {
                $where_conditions[] = "(pl.payment_status != 'Paid' AND pl.due_date < CURDATE())";
            } else {
                $where_conditions[] = "pl.payment_status = '" . $conn->real_escape_string($status_filter) . "'";
            }
        }
    } elseif ($search_field === 'reservation_date') {
        if (!empty($date_from)) {
            $where_conditions[] = "DATE(b.created_at) >= '" . $conn->real_escape_string($date_from) . "'";
        }
        if (!empty($date_to)) {
            $where_conditions[] = "DATE(b.created_at) <= '" . $conn->real_escape_string($date_to) . "'";
        }
    }
}

$where_clause = implode(" AND ", $where_conditions);

/* ===============================
   FETCH PAYMENT LEDGERS
================================ */
$ledger_rows = [];
$ledger_sql = "
    SELECT
        pl.*,
        CONCAT(
            f.first_name,
            CASE WHEN f.middle_name IS NOT NULL AND f.middle_name != ''
                 THEN CONCAT(' ', LEFT(f.middle_name,1), '.')
                 ELSE '' END,
            ' ', f.last_name
        ) AS farmer_name,
        f.phone AS farmer_phone,
        f.province AS farmer_province,
        f.municipality AS farmer_municipality,
        f.barangay AS farmer_barangay,
        f.status AS farmer_status,
        m.machine_name,
        m.type AS machine_type,
        b.booking_date,
        b.created_at AS reservation_date,
        b.farm_size,
        fl.lot_number,
        fl.farm_location,
        fl.province     AS lot_province,
        fl.municipality  AS lot_municipality,
        fl.barangay      AS lot_barangay,
        CASE
            WHEN pl.payment_status != 'Paid' AND pl.due_date < CURDATE() THEN 'Overdue'
            ELSE pl.payment_status
        END AS current_status,
        DATEDIFF(CURDATE(), pl.due_date) AS days_overdue,
        (SELECT COUNT(*) FROM payment_transactions WHERE ledger_id = pl.id) AS payment_count
    FROM payment_ledger pl
    INNER JOIN farmers f ON pl.farmer_id = f.id
    INNER JOIN machines m ON pl.machine_id = m.id
    INNER JOIN bookings b ON pl.booking_id = b.id
    LEFT JOIN farmer_lots fl ON b.lot_id = fl.id
    WHERE $where_clause
    ORDER BY
        CASE
            WHEN pl.payment_status != 'Paid' AND pl.due_date < CURDATE() THEN 1
            WHEN pl.payment_status = 'Unpaid' THEN 2
            WHEN pl.payment_status = 'Partial' THEN 3
            ELSE 4
        END,
        pl.due_date ASC
";

$stmt = $conn->prepare($ledger_sql);
if ($stmt) {
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($r = $result->fetch_assoc()) $ledger_rows[] = $r;
    $stmt->close();
} else {
    $error = "Query error: " . $conn->error;
}

/* ===============================
   FETCH ALL TRANSACTIONS FOR JS
================================ */
$all_transactions = [];
if (!empty($ledger_rows)) {
    $ledger_ids = array_column($ledger_rows, 'id');
    $ids_placeholder = implode(',', array_fill(0, count($ledger_ids), '?'));
    $tx_sql = "
        SELECT pt.*, u.name AS collected_by_name
        FROM payment_transactions pt
        LEFT JOIN users u ON pt.collected_by = u.id
        WHERE pt.ledger_id IN ($ids_placeholder)
        ORDER BY pt.payment_date DESC, pt.created_at DESC
    ";
    $tx_stmt = $conn->prepare($tx_sql);
    $types = str_repeat('i', count($ledger_ids));
    $tx_stmt->bind_param($types, ...$ledger_ids);
    $tx_stmt->execute();
    $tx_result = $tx_stmt->get_result();
    while ($row = $tx_result->fetch_assoc()) {
        $all_transactions[$row['ledger_id']][] = $row;
    }
    $tx_stmt->close();
}

/* ── Helper: build stacked farm-location HTML (Province / Municipality / Barangay) ── */
function buildFarmLocationHtml($province, $municipality, $barangay)
{
    $html = '';
    if (!empty($province)) {
        $html .= '<span style="font-size:10px;color:#000000;">' . htmlspecialchars($province) . '</span><br>';
    }
    $html .= '<span class="fw600">' . htmlspecialchars($municipality ?: '—') . '</span><br>';
    $html .= '<span class="clr-muted">' . htmlspecialchars($barangay ?: '') . '</span>';
    return $html;
}

/* ── Helper: plain-text version for data-attributes / print ── */
function buildFarmLocationText($province, $municipality, $barangay)
{
    $parts = array_filter([$province, $municipality, $barangay]);
    return $parts ? implode(', ', $parts) : '—';
}

/* ===============================
   BUILD RECEIPT DATA (after a successful payment)
================================ */
$receipt = null;
if ($message && $new_or_num) {
    $paid_ledger_id = (int)($_POST['ledger_id'] ?? 0);
    foreach ($ledger_rows as $lr) {
        if ((int)$lr['id'] === $paid_ledger_id) {
            $receipt = [
                'or_number'       => $new_or_num,
                'date'            => date('F d, Y', strtotime($payment_date)),
                'association'     => $association_name,
                'farmer'          => $lr['farmer_name'],
                'farmer_phone'    => $lr['farmer_phone'] ?: '—',
                'machine_type'    => $lr['machine_type'],
                'machine_name'    => $lr['machine_name'],
                'amount_paid'     => $amount,
                'total_amount'    => $lr['total_amount'],
                'amount_paid_total' => $lr['amount_paid'],
                'balance'         => $lr['balance'],
                'status'          => $lr['current_status'],
                'lot'             => $lr['lot_number'] ?? '—',
                'farm_size'       => $lr['farm_size'],
            ];
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.css">
    <style>
        :root {
            --primary: #16a34a;
            --primary-dark: #15803d;
            --primary-light: #dcfce7;
            --text: #000000;
            --text-muted: #000000;
            --border: #d1d5db;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #000000;
            margin: 0;
        }

        .usernames {
            color: #000000;
            font-size: 16px;
            font-weight: bold;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.6);
            margin-bottom: 6px;
        }

        .usernames a {
            color: #000000;
            margin-left: 15px;
            text-decoration: underline;
            font-weight: normal;
        }

        .content-wrapper {
            position: absolute;
            top: 120px;
            left: 0;
            right: 0;
            bottom: 0;
            overflow-y: auto;
            padding: 16px;
        }

        h2 {
            font-size: 26px;
            color: #2d7a2d;
            font-weight: bold;
            text-align: center;
            text-shadow: 1px 1px 3px rgba(14, 4, 4, 0.7);
            margin: 8px 0 10px;
        }

        /* ── Search bar ── */
        .search-bar {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 12px;
        }

        .search-bar select,
        .search-bar input[type="text"],
        .search-bar button {
            padding: 8px 12px;
            font-size: 13px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-family: inherit;
        }

        .search-bar select {
            background: #fff;
            color: #000;
            cursor: pointer;
            min-width: 130px;
        }

        .search-bar input {
            background: #fff;
            color: #000;
            min-width: 160px;
        }

        .search-bar button {
            background-color: #2d7a2d;
            color: #000000;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: background 0.2s;
        }

        .search-bar button:hover {
            background-color: #256725;
        }

        .search-bar .flatpickr-input {
            padding: 8px 12px !important;
            font-size: 13px !important;
            border: 1px solid #ccc !important;
            border-radius: 6px !important;
            background: #fff !important;
            color: #000000 !important;
            cursor: pointer !important;
            width: 130px !important;
            box-sizing: border-box !important;
            font-family: inherit !important;
        }

        .search-bar label {
            color: #000000;
            font-weight: bold;
            font-size: 14px;
            text-shadow: 1px 1px 3px rgba(14, 4, 4, 0.7);
            white-space: nowrap;
        }

        /* ── Alert ── */
        .message {
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
            background: #d1fae5;
            color: #000000;
            border-left: 4px solid #10b981;
            font-size: 14px;
        }

        .message.error {
            background: #fee2e2;
            color: #000000;
            border-left-color: #ef4444;
        }

        /* ── Table ── */
        .table-container {
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.95);
            margin-bottom: 16px;
        }

        .table-scroll {
            overflow-x: auto;
            overflow-y: hidden;
        }

        .table-scroll table {
            table-layout: fixed;
        }

        .table-scroll tbody {
            display: block;
            max-height: 260px;
            overflow-y: auto;
            overflow-x: hidden;
            width: 100%;
        }

        .table-scroll thead {
            display: table;
            width: 100%;
            table-layout: fixed;
        }

        .table-scroll tbody tr {
            display: table;
            width: 100%;
            table-layout: fixed;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            min-width: 1500px;
        }

        thead {
    position: sticky;
    top: 0;
    z-index: 10;
    background: #fff !important;
}

thead th {
    background: #fff !important;
    background-color: #fff !important;
    color: #000000;
    ...
}

        thead th {
            background: #fff !important;
            background-color: #fff !important;
            color: #000000;
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

        tbody tr {
            transition: background 0.15s ease;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
        }

        tbody tr:hover {
            background: var(--primary-light);
        }

        tbody tr.selected-row {
            background: #bbf7d0 !important;
            border-left: 4px solid var(--primary-dark);
        }

        tbody td {
            padding: 8px 5px;
            color: #000000;
            vertical-align: middle;
            font-size: 11px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            word-wrap: break-word;
            text-align: center;
            line-height: 1.3;
        }

        tbody td.td-left {
            text-align: left;
        }

        tbody tr:last-child {
            border-bottom: none;
        }

        /* ── Column widths (16 columns) ── */
        thead th:nth-child(1),
        tbody td:nth-child(1) {
            width: 3%;
        }

        thead th:nth-child(2),
        tbody td:nth-child(2) {
            width: 7%;
        }

        thead th:nth-child(3),
        tbody td:nth-child(3) {
            width: 7%;
        }

        thead th:nth-child(4),
        tbody td:nth-child(4) {
            width: 8%;
        }

        thead th:nth-child(5),
        tbody td:nth-child(5) {
            width: 6%;
        }

        thead th:nth-child(6),
        tbody td:nth-child(6) {
            width: 8%;
        }

        thead th:nth-child(7),
        tbody td:nth-child(7) {
            width: 5%;
        }

        thead th:nth-child(8),
        tbody td:nth-child(8) {
            width: 5%;
        }

        thead th:nth-child(9),
        tbody td:nth-child(9) {
            width: 6%;
        }

        thead th:nth-child(10),
        tbody td:nth-child(10) {
            width: 7%;
        }

        thead th:nth-child(11),
        tbody td:nth-child(11) {
            width: 7%;
        }

        thead th:nth-child(12),
        tbody td:nth-child(12) {
            width: 6%;
        }

        thead th:nth-child(13),
        tbody td:nth-child(13) {
            width: 6%;
        }

        thead th:nth-child(14),
        tbody td:nth-child(14) {
            width: 6%;
        }

        thead th:nth-child(15),
        tbody td:nth-child(15) {
            width: 6%;
        }

        thead th:nth-child(16),
        tbody td:nth-child(16) {
            width: 7%;
        }

        .fw600 {
            font-weight: 600;
        }

        .fw700 {
            font-weight: 700;
        }

        .clr-green,
        .clr-red,
        .clr-muted {
            color: #000000 !important;
        }

        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: #000000;
        }

        .no-data i {
            font-size: 56px;
            color: var(--border);
            margin-bottom: 14px;
            display: block;
        }

        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #000000;
        }

        .badge-inactive {
            background: #fee2e2;
            color: #000000;
            padding: 2px 5px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: 700;
            margin-left: 3px;
        }

        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 14px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .action-buttons button,
        .action-buttons a {
            padding: 10px 22px;
            border-radius: 6px;
            border: none;
            background-color: #2d7a2d;
            color: #fff;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: background 0.2s, transform 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .action-buttons button:hover,
        .action-buttons a:hover {
            background-color: #1a5c1a;
            transform: scale(1.03);
        }

        .action-buttons button:disabled {
            cursor: not-allowed;
            transform: none;
        }

        /* ══ MODAL BASE (PROFESSIONAL OFFICE THEME) ══ */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.65);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(4px);
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-box {
            background: #ffffff;
            width: 520px;
            max-width: 95%;
            max-height: 92vh;
            overflow-y: auto;
            border-radius: 12px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25), 0 0 0 1px rgba(0, 0, 0, 0.05);
        }

        .modal-box::-webkit-scrollbar {
            width: 6px;
        }

        .modal-box::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }

        /* Executive Header */
        .modal-head {
            background: linear-gradient(135deg, #15803d, #14532d);
            padding: 22px 24px 16px;
            border-radius: 12px 12px 0 0;
            position: relative;
            color: #ffffff;
            border-bottom: 1px solid #166534;
        }

        .modal-head h3 {
            color: #ffffff;
            font-size: 20px;
            font-weight: 700;
            margin: 0 0 4px 0;
            display: flex;
            align-items: center;
            gap: 8px;
            letter-spacing: -0.3px;
        }

        .modal-head p {
            color: #dcfce7;
            font-size: 13.5px;
            font-weight: 500;
            margin: 0;
        }

        .modal-x {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 32px;
            height: 32px;
            background: rgba(255, 255, 255, 0.15);
            border: none;
            border-radius: 6px;
            color: #ffffff;
            font-size: 16px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.15s, transform 0.15s;
        }

        .modal-x:hover {
            background: rgba(255, 255, 255, 0.25);
        }

        /* Integrated Meta Strip (Date & OR Number) */
        .office-meta-strip {
            background: rgba(0, 0, 0, 0.18);
            margin: 16px -24px -16px;
            padding: 10px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            font-size: 12.5px;
            color: #f0fdf4;
        }

        .office-meta-strip span {
            font-weight: 600;
            color: #ffffff;
        }

        .modal-bd {
            padding: 24px;
        }

        /* Professional Office Data Cards */
        .office-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 12px;
        }

        .office-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px 14px;
            transition: border-color 0.15s;
        }

        .office-card:hover {
            border-color: #cbd5e1;
        }

        .office-card .ibox-label {
            font-size: 11px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            margin-bottom: 4px;
        }

        .office-card .ibox-value {
            font-size: 14.5px;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.3;
        }

        /* Balance Card Highlight */
        .office-card.balance-highlight {
            grid-column: 1 / -1;
            background: #fef2f2;
            border: 1px solid #fecdd3;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 18px;
        }

        .office-card.balance-highlight .ibox-label {
            color: #991b1b;
            margin-bottom: 0;
            font-size: 11.5px;
        }

        .office-card.balance-highlight .ibox-value {
            color: #991b1b;
            font-size: 18px;
            font-weight: 800;
        }

        /* Section Divider */
        .m-sec {
            font-size: 12px;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            margin: 20px 0 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .m-sec::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e2e8f0;
        }

        /* Form Inputs */
        .m-grp {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 16px;
        }

        .m-grp label {
            font-size: 13.5px;
            font-weight: 600;
            color: #1e293b;
        }

        .m-grp label .req {
            color: #dc2626;
            margin-left: 2px;
        }

        .m-wrap {
            position: relative;
        }

        .m-wrap .m-ico {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            font-size: 14px;
            pointer-events: none;
        }

        .m-grp input {
            width: 100%;
            padding: 11px 14px 11px 38px;
            border: 1.5px solid #cbd5e1;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            font-family: inherit;
            color: #0f172a;
            transition: all 0.2s;
            background: #ffffff;
            box-sizing: border-box;
        }

        .m-grp input:focus {
            outline: none;
            border-color: #16a34a;
            box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.12);
        }

        /* Office Footer */
        .m-foot {
            padding: 16px 24px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            border-radius: 0 0 12px 12px;
        }

        .m-cancel {
            padding: 10px 22px;
            background: #ffffff;
            color: #475569;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s;
        }

        .m-cancel:hover {
            background: #f1f5f9;
            color: #0f172a;
            border-color: #94a3b8;
        }

        .m-submit {
            padding: 10px 26px;
            background: #16a34a;
            color: #ffffff;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            transition: all 0.15s;
        }

        .m-submit:hover {
            background: #15803d;
            box-shadow: 0 4px 6px -1px rgba(22, 163, 74, 0.2);
        }

        /* Legacy modal styles for History / Receipt */
        #historyModal .modal-box {
            width: 820px;
            max-width: 96%;
        }

        .hist-header-strip {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
            flex-wrap: wrap;
            gap: 8px;
        }

        .hist-summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin-bottom: 8px;
        }

        .hist-amount-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin-bottom: 16px;
        }

        .hist-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-bottom: 8px;
        }

        .hist-tx-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 12px;
        }

        .hist-tx-card {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            background: #fafafa;
            overflow: hidden;
        }

        .hist-tx-card:hover {
            box-shadow: 0 2px 10px rgba(0, 0, 0, .07);
        }

        .hist-tx-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 12px 16px 10px;
            background: linear-gradient(135deg, #16a34a, #15803d);
            color: #fff;
        }

        .hist-tx-or-block .or-tag {
            font-size: 10px;
            color: #fff;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }

        .hist-tx-or-block .or-val {
            font-size: 13px;
            font-weight: 700;
            color: #fff;
            font-family: 'Courier New', monospace;
            letter-spacing: 0.3px;
        }

        .hist-tx-amt-block .amt-tag {
            font-size: 10px;
            color: #fff;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-align: right;
            margin-bottom: 2px;
        }

        .hist-tx-amt-block .amt-val {
            font-size: 22px;
            font-weight: 800;
            color: #fff;
            text-align: right;
        }

        .hist-tx-body {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 0;
            padding: 0;
        }

        .hist-tx-field {
            padding: 10px 16px;
            border-right: 1px solid #e5e7eb;
            border-bottom: 1px solid #e5e7eb;
        }

        .hist-tx-field:last-child {
            border-right: none;
        }

        .hist-tx-field.full-width {
            grid-column: 1 / -1;
            border-right: none;
        }

        .hist-tx-field .lbl {
            font-size: 10px;
            color: #64748b;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 3px;
        }

        .hist-tx-field .val {
            font-size: 13px;
            color: #0f172a;
            font-weight: 600;
        }

        .hist-no-tx {
            text-align: center;
            padding: 40px 20px;
            color: #64748b;
            font-size: 15px;
        }

        .hist-no-tx i {
            font-size: 36px;
            display: block;
            margin-bottom: 10px;
            color: #d1d5db;
        }

        .hist-tx-count-badge {
            font-size: 12px;
            font-weight: 700;
            background: #e9f5e9;
            color: #16a34a;
            padding: 3px 10px;
            border-radius: 12px;
            margin-left: 8px;
        }

        .overdue-pill {
            background: #fee2e2;
            color: #991b1b;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        /* ── Print ── */
        #printArea,
        #receiptPrintArea {
            display: none;
        }

        @media print {
            body>*:not(.print-active) {
                display: none !important;
            }

            .print-active {
                display: block !important;
                padding: 24px 32px;
            }

            #printArea h2 {
                text-align: center;
                font-size: 20px;
                margin-bottom: 16px;
            }

            #printArea table {
                width: 100%;
                border-collapse: collapse;
                font-size: 10px;
            }

            #printArea thead th {
                background: #fff !important;
                color: #000000 !important;
                padding: 8px 5px;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            #printArea tbody td {
                padding: 6px 5px;
                border-bottom: 1px solid #d1d5db;
            }

            #receiptPrintArea {
                padding: 0;
            }

            #receiptPrintArea .receipt-paper {
                width: 380px;
                margin: 0 auto;
            }
        }

        /* ── Receipt ── */
        .receipt-paper {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            overflow: hidden;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .receipt-head {
            background: linear-gradient(135deg, #16a34a, #15803d);
            color: #fff;
            text-align: center;
            padding: 22px 20px 18px;
        }

        .receipt-head .check-circle {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-size: 26px;
        }

        .receipt-head h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 800;
            color: #fff;
        }

        .receipt-head p {
            margin: 4px 0 0;
            font-size: 12px;
            color: #dcfce7;
        }

        .receipt-or {
            text-align: center;
            padding: 14px 20px;
            border-bottom: 1px dashed #d1d5db;
            background: #f8fafc;
        }

        .receipt-or .lbl {
            font-size: 10px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .receipt-or .val {
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
            font-family: 'Courier New', monospace;
            letter-spacing: 0.5px;
            margin-top: 2px;
        }

        .receipt-body {
            padding: 16px 20px;
        }

        .receipt-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 7px 0;
            border-bottom: 1px dotted #e5e7eb;
            font-size: 13px;
        }

        .receipt-row:last-child {
            border-bottom: none;
        }

        .receipt-row .rk {
            color: #64748b;
            font-weight: 600;
        }

        .receipt-row .rv {
            color: #0f172a;
            font-weight: 700;
            text-align: right;
        }

        .receipt-amount-box {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 8px;
            padding: 14px 16px;
            margin: 12px 0;
            text-align: center;
        }

        .receipt-amount-box .lbl {
            font-size: 10px;
            font-weight: 700;
            color: #166534;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .receipt-amount-box .val {
            font-size: 26px;
            font-weight: 800;
            color: #15803d;
            margin-top: 2px;
        }

        .receipt-balance-box {
            background: #fff5f5;
            border: 1px solid #fecaca;
            border-radius: 8px;
            padding: 10px 16px;
            margin-bottom: 4px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .receipt-balance-box.paid {
            background: #f0fdf4;
            border-color: #bbf7d0;
        }

        .receipt-balance-box .lbl {
            font-size: 11px;
            font-weight: 700;
            color: #991b1b;
            text-transform: uppercase;
        }

        .receipt-balance-box .val {
            font-size: 15px;
            font-weight: 800;
            color: #991b1b;
        }

        .receipt-balance-box.paid .val,
        .receipt-balance-box.paid .lbl {
            color: #166534;
        }

        .receipt-foot {
            text-align: center;
            padding: 12px 20px 18px;
            font-size: 10.5px;
            color: #64748b;
            border-top: 1px solid #f0f0f0;
        }

        /* ══ CONFIRM DIALOG ══ */
        .confirm-box {
            width: 380px;
            max-width: 92%;
        }

        .confirm-body {
            padding: 26px 24px 8px;
            text-align: center;
        }

        .confirm-icon {
            width: 54px;
            height: 54px;
            border-radius: 50%;
            background: #fee2e2;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 14px;
            font-size: 24px;
            color: #dc2626;
        }

        .confirm-icon.ok {
            background: #dcfce7;
            color: #16a34a;
        }

        .confirm-title {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 6px;
        }

        .confirm-text {
            font-size: 13px;
            color: #64748b;
            margin: 0 0 6px;
            line-height: 1.4;
        }

        .confirm-foot {
            padding: 16px 24px 22px;
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        .confirm-yes {
            padding: 9px 26px;
            background: #16a34a;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }

        .confirm-yes:hover {
            background: #15803d;
        }

        .confirm-no {
            padding: 9px 26px;
            background: white;
            color: #475569;
            border: 2px solid #e5e7eb;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }

        .confirm-no:hover {
            background: #f9fafb;
        }

        thead,
        thead th {
            background: #fff ;
            background-color: #2d7a2d;
        }
    </style>
</head>

<body>

    <!-- Hidden print area -->
    <div id="printArea">
        <h2>Payment Management — <?= htmlspecialchars($association_name) ?></h2>
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
                    <th>Total Amount</th>
                    <th>Paid</th>
                    <th>Balance</th>
                    <th>Due Date</th>
                    <th>Schedule</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody id="printTableBody"></tbody>
        </table>
    </div>

    <!-- Hidden receipt print area -->
    <div id="receiptPrintArea">
        <div class="receipt-paper">
            <div class="receipt-head">
                <div class="check-circle"><i class="fas fa-check"></i></div>
                <h3>Payment Successful!</h3>
                <p id="rp-association">—</p>
            </div>
            <div class="receipt-or">
                <div class="lbl">Official Receipt No.</div>
                <div class="val" id="rp-or">—</div>
            </div>
            <div class="receipt-body">
                <div class="receipt-row"><span class="rk">Date</span><span class="rv" id="rp-date">—</span></div>
                <div class="receipt-row"><span class="rk">Farmer</span><span class="rv" id="rp-farmer">—</span></div>
                <div class="receipt-row"><span class="rk">Contact No.</span><span class="rv" id="rp-phone">—</span></div>
                <div class="receipt-row"><span class="rk">Machine Type</span><span class="rv" id="rp-machinetype">—</span></div>
                <div class="receipt-row"><span class="rk">Machine Name</span><span class="rv" id="rp-machinename">—</span></div>
                <div class="receipt-row"><span class="rk">Farm Lot / Size</span><span class="rv" id="rp-lot">—</span></div>

                <div class="receipt-amount-box">
                    <div class="lbl">Amount Paid</div>
                    <div class="val" id="rp-amount">₱0.00</div>
                </div>

                <div class="receipt-row"><span class="rk">Total Amount</span><span class="rv" id="rp-total">—</span></div>
                <div class="receipt-row"><span class="rk">Total Paid to Date</span><span class="rv" id="rp-paidtotal">—</span></div>

                <div class="receipt-balance-box" id="rp-balance-box">
                    <span class="lbl">Remaining Balance</span>
                    <span class="val" id="rp-balance">—</span>
                </div>
            </div>
            <div class="receipt-foot">Thank you! This receipt was generated by AMRMS.</div>
        </div>
    </div>

    <div class="content-wrapper">

        <div class="usernames">
            Welcome <?= htmlspecialchars($association_name) ?>
            <a href="../logout.php">Logout</a>
        </div>

        <h2>Payment Management</h2>

        <?php if ($error): ?>
            <div class="message error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- ── Search bar ── -->
        <form class="search-bar" method="GET" id="searchForm">
            <input type="hidden" name="field_changed" id="field_changed" value="0">

            <select name="search_field" id="searchField" onchange="handleFieldChange()">
                <option value="All" <?= ($search_field === 'All')          ? 'selected' : '' ?>>All</option>
                <option value="farmer_name" <?= ($search_field === 'farmer_name')  ? 'selected' : '' ?>>Farmer Name</option>
                <option value="municipality" <?= ($search_field === 'municipality') ? 'selected' : '' ?>>Municipality</option>
                <option value="barangay" <?= ($search_field === 'barangay')     ? 'selected' : '' ?>>Barangay</option>
                <option value="machine_type" <?= ($search_field === 'machine_type') ? 'selected' : '' ?>>Machine Type</option>
                <option value="Status" <?= ($search_field === 'Status')       ? 'selected' : '' ?>>Status</option>
                <option value="reservation_date" <?= ($search_field === 'reservation_date') ? 'selected' : '' ?>>Reservation Date</option>
            </select>

            <div id="dynamicInputs" style="display:contents;"></div>

            <button type="submit" id="searchBtn" style="display:none;">Search</button>

            <span style="margin-left:auto; color: #2d7a2d;font-weight:bold;font-size:14px;text-shadow:1px 1px 3px rgba(14,4,4,0.7);white-space:nowrap;">
                Total Records: <span id="totalCount"><?= count($ledger_rows) ?></span>
            </span>
        </form>

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
                            <th>Total Amount</th>
                            <th>Paid</th>
                            <th>Balance</th>
                            <th>Due Date</th>
                            <th>Schedule</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="ledgerTable">
                        <?php if (count($ledger_rows) > 0):
                            $i = 1;
                            foreach ($ledger_rows as $ledger):
                                $farmerAddr = trim(
                                    ($ledger['farmer_province']     ? $ledger['farmer_province']     . ', ' : '') .
                                        ($ledger['farmer_municipality'] ? $ledger['farmer_municipality'] . ', ' : '') .
                                        ($ledger['farmer_barangay']     ?? ''),
                                    ', '
                                );
                                $farmLocText = buildFarmLocationText($ledger['lot_province'], $ledger['lot_municipality'], $ledger['lot_barangay']);
                                $isOverdue = ($ledger['days_overdue'] > 0 && $ledger['current_status'] === 'Overdue');
                        ?>
                                <tr class="ledger-row"
                                    data-id="<?= $ledger['id'] ?>"
                                    data-status="<?= htmlspecialchars($ledger['current_status']) ?>"
                                    data-farmer="<?= htmlspecialchars($ledger['farmer_name']) ?>"
                                    data-farmeraddr="<?= htmlspecialchars($farmerAddr) ?>"
                                    data-phone="<?= htmlspecialchars($ledger['farmer_phone']) ?>"
                                    data-machinetype="<?= htmlspecialchars($ledger['machine_type']) ?>"
                                    data-machine="<?= htmlspecialchars($ledger['machine_name']) ?>"
                                    data-total="<?= $ledger['total_amount'] ?>"
                                    data-paid="<?= $ledger['amount_paid'] ?>"
                                    data-balance="<?= $ledger['balance'] ?>"
                                    data-payments="<?= $ledger['payment_count'] ?>"
                                    data-reservationdate="<?= $ledger['reservation_date'] ? date('M d, Y', strtotime($ledger['reservation_date'])) : '—' ?>"
                                    data-booking="<?= date('M d, Y', strtotime($ledger['booking_date'])) ?>"
                                    data-schedule="<?= date('m/d/Y', strtotime($ledger['booking_date'])) ?>"
                                    data-due="<?= date('M d, Y', strtotime($ledger['due_date'])) ?>"
                                    data-dueformatted="<?= date('m/d/Y', strtotime($ledger['due_date'])) ?>"
                                    data-daysoverdue="<?= $ledger['days_overdue'] ?>"
                                    data-lot="<?= htmlspecialchars($ledger['lot_number'] ?? '') ?>"
                                    data-farmlocation="<?= htmlspecialchars($farmLocText) ?>"
                                    data-farmsize="<?= number_format($ledger['farm_size'], 2) ?>"
                                    data-farmerstatus="<?= htmlspecialchars($ledger['farmer_status']) ?>"
                                    title="Double-click to view payment history">

                                    <td><?= $i++ ?></td>
                                    <td class="fw600">
                                        <?= $ledger['reservation_date'] ? date('m/d/Y', strtotime($ledger['reservation_date'])) : '—' ?>
                                    </td>
                                    <td class="td-left">
                                        <span class="fw600"><?= htmlspecialchars($ledger['farmer_name']) ?></span>
                                        <?php if ($ledger['farmer_status'] === 'Inactive'): ?>
                                            <span class="badge-inactive">INACTIVE</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="td-left"><?= htmlspecialchars($farmerAddr ?: '—') ?></td>
                                    <td class="fw600"><?= htmlspecialchars($ledger['farmer_phone'] ?? '—') ?></td>
                                    <td class="td-left">
                                        <?= buildFarmLocationHtml($ledger['lot_province'], $ledger['lot_municipality'], $ledger['lot_barangay']) ?>
                                    </td>
                                    <td class="fw700"><?= htmlspecialchars($ledger['lot_number'] ?? '—') ?></td>
                                    <td class="fw600"><?= number_format($ledger['farm_size'], 2) ?> ha</td>
                                    <td><?= htmlspecialchars($ledger['machine_type']) ?></td>
                                    <td class="fw600"><?= htmlspecialchars($ledger['machine_name']) ?></td>
                                    <td class="fw700">₱<?= number_format($ledger['total_amount'], 2) ?></td>
                                    <td class="fw600">₱<?= number_format($ledger['amount_paid'], 2) ?></td>
                                    <td class="fw700">
                                        ₱<?= number_format($ledger['balance'], 2) ?>
                                    </td>
                                    <td>
                                        <span class="fw600">
                                            <?= date('m/d/Y', strtotime($ledger['due_date'])) ?>
                                        </span>
                                        <?php if ($isOverdue): ?>
                                            <br><span style="color:#000000;"><?= $ledger['days_overdue'] ?>d overdue</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="fw600"><?= date('m/d/Y', strtotime($ledger['booking_date'])) ?></td>
                                    <td>
                                        <span class="badge badge-<?= $ledger['current_status'] ?>">
                                            <?= htmlspecialchars($ledger['current_status']) ?>
                                        </span>
                                        <?php if ($ledger['payment_count'] > 0): ?>
                                            <br><span><?= $ledger['payment_count'] ?> pay</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach;
                        else: ?>
                            <tr>
                                <td colspan="16" class="no-data">
                                    <i class="fas fa-inbox"></i>
                                    <p>No records found</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="action-buttons">
            <button id="btnCollect" onclick="openPaymentModal()" disabled>
                Payment
            </button>
            <button onclick="printList()">
                Print
            </button>
        </div>

    </div><!-- /.content-wrapper -->


    <!-- ══════════════════════════════════════════════════════════
     PAYMENT MODAL (Professional Work Office Re-design)
    ══════════════════════════════════════════════════════════ -->
    <div class="modal-overlay" id="paymentModal">
        <div class="modal-box">
            <div class="modal-head">
                <h3><i class="fas fa-peso-sign"></i> Collect Payment</h3>
                <p id="pay-subtitle">Select an account from the table</p>

                <div class="office-meta-strip">
                    <div><i class="fas fa-calendar-day" style="margin-right: 4px;"></i> Date: <span id="pay-current-date-text">—</span></div>
                    <div><i class="fas fa-receipt" style="margin-right: 4px;"></i> Official Receipt No.: <span id="pay-or-number-text" style="font-family: 'Courier New', monospace;">—</span></div>
                </div>
            </div>

            <div class="modal-bd">
                <div class="office-grid">
                    <div class="office-card">
                        <div class="ibox-label">Farmer</div>
                        <div class="ibox-value" id="pay-farmer">—</div>
                    </div>
                    <div class="office-card">
                        <div class="ibox-label">Machine Type</div>
                        <div class="ibox-value" id="pay-machine-type">—</div>
                    </div>
                    <div class="office-card">
                        <div class="ibox-label">Machine Name</div>
                        <div class="ibox-value" id="pay-machine">—</div>
                    </div>
                    <div class="office-card">
                        <div class="ibox-label">Total Amount</div>
                        <div class="ibox-value" id="pay-total">—</div>
                    </div>
                    <div class="office-card">
                        <div class="ibox-label">Already Paid</div>
                        <div class="ibox-value" id="pay-paid">—</div>
                    </div>
                    <div class="office-card balance-highlight">
                        <div class="ibox-label">Remaining Balance</div>
                        <div class="ibox-value" id="pay-balance">—</div>
                    </div>
                </div>

                <div class="m-sec"><i class="fas fa-coins"></i> PAYMENT DETAILS</div>

                <form method="POST" id="paymentForm" onsubmit="return false;">
                    <input type="hidden" name="record_payment" value="1">
                    <input type="hidden" name="ledger_id" id="pay-ledger-id">
                    <input type="hidden" name="payment_date" id="pay-date-hidden">
                    <input type="hidden" name="or_number" id="pay-or-number">

                    <div class="m-grp">
                        <label>Amount to Collect <span class="req">*</span></label>
                        <div class="m-wrap">
                            <i class="fas fa-peso-sign m-ico"></i>
                            <input type="number" name="amount" id="pay-amount" step="0.01" min="0.01" required placeholder="Enter amount...">
                        </div>
                    </div>

                    <div class="m-foot" style="margin: 24px -24px -24px;">
                        <button type="button" class="m-submit" onclick="confirmPay()">
                            <i class="fas fa-check"></i> Pay
                        </button>
                        <button type="button" class="m-cancel" onclick="confirmCancelPayment()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <!-- ══════════════════════════════════
     CONFIRM PAY MODAL
══════════════════════════════════ -->
    <div class="modal-overlay" id="confirmPayModal">
        <div class="modal-box confirm-box">
            <div class="confirm-body">
                <div class="confirm-icon ok"><i class="fas fa-peso-sign"></i></div>
                <div class="confirm-title">Are you sure you want to pay?</div>
                <div class="confirm-text" id="confirmPayText">Please confirm this payment.</div>
            </div>
            <div class="confirm-foot">
                <button type="button" class="confirm-yes" onclick="proceedPay()">Yes, Pay</button>
                <button type="button" class="confirm-no" onclick="closeConfirmPayModal()">No</button>
            </div>
        </div>
    </div>


    <!-- ══════════════════════════════════
     CONFIRM CANCEL MODAL
══════════════════════════════════ -->
    <div class="modal-overlay" id="confirmCancelModal">
        <div class="modal-box confirm-box">
            <div class="confirm-body">
                <div class="confirm-icon"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="confirm-title">Are you sure you want to cancel?</div>
                <div class="confirm-text">Any unsaved payment details will be lost.</div>
            </div>
            <div class="confirm-foot">
                <button type="button" class="confirm-yes" onclick="proceedCancelPayment()">Yes</button>
                <button type="button" class="confirm-no" onclick="closeConfirmCancelModal()">No</button>
            </div>
        </div>
    </div>


    <!-- ══════════════════════════════════
     RECEIPT MODAL (shown after a successful payment)
══════════════════════════════════ -->
    <div class="modal-overlay" id="receiptModal">
        <div class="modal-box" style="width:420px;">
            <div style="padding:0;">
                <div class="receipt-paper" style="border:none; border-radius:14px 14px 0 0;">
                    <div class="receipt-head" style="border-radius:14px 14px 0 0; position:relative;">
                        <div class="check-circle"><i class="fas fa-check"></i></div>
                        <h3>Payment Successful!</h3>
                        <p id="rm-association">—</p>
                    </div>
                    <div class="receipt-or">
                        <div class="lbl">Official Receipt No.</div>
                        <div class="val" id="rm-or">—</div>
                    </div>
                    <div class="receipt-body">
                        <div class="receipt-row"><span class="rk">Date</span><span class="rv" id="rm-date">—</span></div>
                        <div class="receipt-row"><span class="rk">Farmer</span><span class="rv" id="rm-farmer">—</span></div>
                        <div class="receipt-row"><span class="rk">Contact No.</span><span class="rv" id="rm-phone">—</span></div>
                        <div class="receipt-row"><span class="rk">Machine Type</span><span class="rv" id="rm-machinetype">—</span></div>
                        <div class="receipt-row"><span class="rk">Machine Name</span><span class="rv" id="rm-machinename">—</span></div>
                        <div class="receipt-row"><span class="rk">Farm Lot / Size</span><span class="rv" id="rm-lot">—</span></div>

                        <div class="receipt-amount-box">
                            <div class="lbl">Amount Paid</div>
                            <div class="val" id="rm-amount">₱0.00</div>
                        </div>

                        <div class="receipt-row"><span class="rk">Total Amount</span><span class="rv" id="rm-total">—</span></div>
                        <div class="receipt-row"><span class="rk">Total Paid to Date</span><span class="rv" id="rm-paidtotal">—</span></div>

                        <div class="receipt-balance-box" id="rm-balance-box">
                            <span class="lbl">Remaining Balance</span>
                            <span class="val" id="rm-balance">—</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="m-foot">
                <button type="button" class="m-submit" onclick="printReceipt()">
                    <i class="fas fa-print"></i> Print Receipt
                </button>
                <button type="button" class="m-cancel" onclick="closeReceiptModal()">Close</button>
            </div>
        </div>
    </div>


    <!-- ══════════════════════════════════
     PAYMENT HISTORY MODAL
══════════════════════════════════ -->
    <div class="modal-overlay" id="historyModal">
        <div class="modal-box">
            <div class="modal-head">
                <div style="flex:1;">
                    <h3><i class="fas fa-clock-rotate-left"></i> Payment History</h3>
                    <p id="hist-subtitle">Transaction records for this account</p>
                </div>
            </div>
            <div class="modal-bd">

                <div class="hist-header-strip">
                    <span id="hist-status-wrap"></span>
                    <span id="hist-overdue-pill" style="display:none;" class="overdue-pill">
                        <i class="fas fa-clock"></i> <span id="hist-overdue-days"></span>
                    </span>
                </div>

                <div class="hist-summary-grid">
                    <div class="ibox">
                        <div class="ibox-label">Reservation Date</div>
                        <div class="ibox-value" id="hist-reservation-date">—</div>
                    </div>
                    <div class="ibox">
                        <div class="ibox-label">Schedule</div>
                        <div class="ibox-value" id="hist-booking-date">—</div>
                    </div>
                    <div class="ibox">
                        <div class="ibox-label">Due Date</div>
                        <div class="ibox-value" id="hist-due-date">—</div>
                    </div>
                    <div class="ibox">
                        <div class="ibox-label">Payments Made</div>
                        <div class="ibox-value" id="hist-pay-count">—</div>
                    </div>
                </div>

                <div class="hist-info-grid" style="margin-bottom:8px;">
                    <div class="ibox">
                        <div class="ibox-label">Farmer</div>
                        <div class="ibox-value" id="hist-farmer">—</div>
                        <div class="ibox-sub" id="hist-phone">—</div>
                        <div class="ibox-sub" id="hist-farmeraddr" style="margin-top:3px;">—</div>
                    </div>
                    <div class="ibox">
                        <div class="ibox-label">Machine</div>
                        <div class="ibox-value" id="hist-machine">—</div>
                        <div class="ibox-sub">
                            Lot: <span id="hist-lot">—</span> &nbsp;|&nbsp; <span id="hist-farmsize">—</span> ha
                        </div>
                        <div class="ibox-sub" id="hist-farmlocation" style="margin-top:3px;">—</div>
                    </div>
                </div>

                <div class="hist-amount-grid">
                    <div class="ibox">
                        <div class="ibox-label">Total Amount</div>
                        <div style="font-size:18px; font-weight:800; color: #000000; margin-top:2px;" id="hist-total">—</div>
                    </div>
                    <div class="ibox-total">
                        <div class="ibox-label">Amount Paid</div>
                        <div style="font-size:18px; font-weight:800; color: #000000; margin-top:2px;" id="hist-paid">—</div>
                    </div>
                    <div class="ibox-balance">
                        <div class="ibox-label">Balance</div>
                        <div style="font-size:18px; font-weight:800; color: #000000; margin-top:2px;" id="hist-balance">—</div>
                    </div>
                </div>

                <hr class="m-hr">

                <div class="m-sec">
                    <i class="fas fa-list-ul"></i> Transactions
                    <span class="hist-tx-count-badge" id="hist-tx-count">0 records</span>
                </div>

                <div class="hist-tx-list" id="hist-tx-list">
                    <div class="hist-no-tx">
                        <i class="fas fa-inbox"></i> No payment transactions recorded yet.
                    </div>
                </div>

            </div>
            <div class="m-foot">
                <button type="button" class="m-cancel" onclick="closeHistoryModal()">Close</button>
                <button type="button" class="m-submit" onclick="window.print()">
                    Print
                </button>
            </div>
        </div>
    </div>


    <script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.js"></script>
    <script>
        const allTransactions = <?= json_encode($all_transactions) ?>;
        const allLedgers = <?= json_encode($ledger_rows) ?>;
        let selectedLedger = null;

        /* ══════════════════════════════════════
           SEARCH / FILTER
        ══════════════════════════════════════ */
        let fpFrom = null,
            fpTo = null;

        function handleFieldChange() {
            const field = document.getElementById('searchField').value;
            const area = document.getElementById('dynamicInputs');
            const btn = document.getElementById('searchBtn');

            if (fpFrom) {
                fpFrom.destroy();
                fpFrom = null;
            }
            if (fpTo) {
                fpTo.destroy();
                fpTo = null;
            }
            area.innerHTML = '';
            btn.style.display = (field === 'All') ? 'none' : 'inline-flex';

            if (field === 'farmer_name') {
                area.innerHTML = `<input type="text" name="search_term" placeholder="Enter farmer name..." style="min-width:200px;">`;
            }
            if (field === 'municipality') {
                area.innerHTML = `<input type="text" name="muni_term" placeholder="Enter municipality..." style="min-width:200px;">`;
            }
            if (field === 'barangay') {
                area.innerHTML = `
            <input type="text" name="muni_term" placeholder="Enter municipality..." style="min-width:155px;">
            <input type="text" name="brgy_term" placeholder="Enter barangay..." style="min-width:155px;">`;
            }
            if (field === 'machine_type') {
                area.innerHTML = `
            <select name="typeDropdown" style="min-width:150px;">
                <option value="" disabled selected hidden>Select type</option>
                <option value="Tractor">Tractor</option>
                <option value="Harvester">Harvester</option>
            </select>`;
            }
            if (field === 'Status') {
                area.innerHTML = `
            <select name="statusDropdown" style="min-width:140px;">
                <option value="" disabled selected hidden>Select status</option>
                <option value="Unpaid">Unpaid</option>
                <option value="Partial">Partial</option>
                <option value="Paid">Paid</option>
                <option value="overdue">Overdue</option>
            </select>`;
            }
            if (field === 'reservation_date') {
                area.innerHTML = `
            <label style="color:#000000;font-weight:bold;font-size:14px;white-space:nowrap;">From</label>
            <input type="text" id="filterFrom" placeholder="mm/dd/yyyy" readonly style="width:130px;">
            <input type="hidden" name="date_from" id="date_from">
            <label style="color:#000000;font-weight:bold;font-size:14px;white-space:nowrap;">To</label>
            <input type="text" id="filterTo" placeholder="mm/dd/yyyy" readonly style="width:130px;">
            <input type="hidden" name="date_to" id="date_to">`;
                setTimeout(() => {
                    fpFrom = flatpickr('#filterFrom', {
                        dateFormat: 'm/d/Y',
                        allowInput: false,
                        onChange: d => {
                            document.getElementById('date_from').value = d.length ? fmt_ymd(d[0]) : '';
                        }
                    });
                    fpTo = flatpickr('#filterTo', {
                        dateFormat: 'm/d/Y',
                        allowInput: false,
                        onChange: d => {
                            document.getElementById('date_to').value = d.length ? fmt_ymd(d[0]) : '';
                        }
                    });
                }, 0);
            }
            if (field === 'All') {
                document.getElementById('field_changed').value = '1';
                document.getElementById('searchForm').submit();
            }
        }

        function fmt_ymd(d) {
            return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
        }

        document.addEventListener('DOMContentLoaded', function() {
            const field = document.getElementById('searchField').value;
            if (field !== 'All') handleFieldChange();
        });

        /* ══════════════════════════════════════
           ROW SELECTION
        ══════════════════════════════════════ */
        function buildLedger(row) {
            return {
                id: row.dataset.id,
                status: row.dataset.status,
                farmer: row.dataset.farmer,
                phone: row.dataset.phone,
                farmerAddr: row.dataset.farmeraddr,
                machine: row.dataset.machine,
                machineType: row.dataset.machinetype,
                total: parseFloat(row.dataset.total),
                paid: parseFloat(row.dataset.paid),
                balance: parseFloat(row.dataset.balance),
                payments: parseInt(row.dataset.payments),
                reservationDate: row.dataset.reservationdate,
                bookingDate: row.dataset.booking,
                schedule: row.dataset.schedule,
                dueDate: row.dataset.due,
                dueFormatted: row.dataset.dueformatted,
                daysOverdue: parseInt(row.dataset.daysoverdue),
                lot: row.dataset.lot,
                farmLocation: row.dataset.farmlocation,
                farmSize: row.dataset.farmsize,
                farmerStatus: row.dataset.farmerstatus
            };
        }

        document.querySelectorAll('.ledger-row').forEach(function(row) {
            row.addEventListener('click', function() {
                document.querySelectorAll('.ledger-row').forEach(r => r.classList.remove('selected-row'));
                this.classList.add('selected-row');
                selectedLedger = buildLedger(this);
                document.getElementById('btnCollect').disabled = (selectedLedger.status === 'Paid');
            });
            row.addEventListener('dblclick', function() {
                document.querySelectorAll('.ledger-row').forEach(r => r.classList.remove('selected-row'));
                this.classList.add('selected-row');
                selectedLedger = buildLedger(this);
                openHistoryModal(selectedLedger);
            });
        });

        /* ══════════════════════════════════════
           PAYMENT MODAL (Collect Payment)
        ══════════════════════════════════════ */
        function openPaymentModal() {
            if (!selectedLedger) return;
            if (selectedLedger.status === 'Paid') {
                alert('This account is already fully paid.');
                return;
            }

            document.getElementById('pay-subtitle').textContent = 'Farmer: ' + selectedLedger.farmer;
            document.getElementById('pay-farmer').textContent = selectedLedger.farmer;
            document.getElementById('pay-machine-type').textContent = selectedLedger.machineType;
            document.getElementById('pay-machine').textContent = selectedLedger.machine;
            document.getElementById('pay-total').textContent = '₱' + fmt(selectedLedger.total);
            document.getElementById('pay-paid').textContent = '₱' + fmt(selectedLedger.paid);
            document.getElementById('pay-balance').textContent = '₱' + fmt(selectedLedger.balance);
            document.getElementById('pay-ledger-id').value = selectedLedger.id;
            document.getElementById('pay-amount').value = '';
            document.getElementById('pay-amount').max = selectedLedger.balance;

            /* Date */
            const today = new Date();
            const todayIso = today.toISOString().split('T')[0];
            const todayFormatted = today.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: '2-digit'
            });
            document.getElementById('pay-current-date-text').textContent = todayFormatted;
            document.getElementById('pay-date-hidden').value = todayIso;

            /* Auto Generate Official Receipt No. (Format: OR-YYYYMMDD-00000-0000000000) */
            const ymd = today.getFullYear() + String(today.getMonth() + 1).padStart(2, '0') + String(today.getDate()).padStart(2, '0');
            const ledgerPadded = String(selectedLedger.id).padStart(5, '0');
            let ts = Math.floor(Date.now() / 1000);
            let genOR = 'OR-' + ymd + '-' + ledgerPadded + '-' + ts;

            /* Ensure uniqueness against existing known records */
            let exists = false;
            do {
                exists = false;
                for (let lid in allTransactions) {
                    if (allTransactions[lid] && allTransactions[lid].some(tx => tx.or_number === genOR)) {
                        exists = true;
                        ts++;
                        genOR = 'OR-' + ymd + '-' + ledgerPadded + '-' + ts;
                        break;
                    }
                }
            } while (exists);

            document.getElementById('pay-or-number-text').textContent = genOR;
            document.getElementById('pay-or-number').value = genOR;

            document.getElementById('paymentModal').classList.add('active');
            document.body.style.overflow = 'hidden';
            setTimeout(() => document.getElementById('pay-amount').focus(), 150);
        }

        function closePaymentModal() {
            document.getElementById('paymentModal').classList.remove('active');
            document.body.style.overflow = '';
        }
        document.getElementById('paymentModal').addEventListener('click', function(e) {
            if (e.target === this) closePaymentModal();
        });

        /* ══════════════════════════════════════
           CONFIRM PAY
        ══════════════════════════════════════ */
        function confirmPay() {
            const amountInput = document.getElementById('pay-amount');

            if (!amountInput.reportValidity()) {
                return;
            }

            const amount = parseFloat(amountInput.value || 0);
            if (!amount || amount <= 0) {
                alert('Please enter a valid amount.');
                return;
            }
            if (selectedLedger && amount > selectedLedger.balance) {
                alert('Amount exceeds the remaining balance.');
                return;
            }

            const orNum = document.getElementById('pay-or-number').value;
            document.getElementById('confirmPayText').textContent =
                'You are about to record a payment of ₱' + fmt(amount) +
                (selectedLedger ? ' for ' + selectedLedger.farmer : '') +
                ' under Official Receipt No. ' + orNum + '.';

            document.getElementById('confirmPayModal').classList.add('active');
        }

        function closeConfirmPayModal() {
            document.getElementById('confirmPayModal').classList.remove('active');
        }

        function proceedPay() {
            closeConfirmPayModal();
            document.getElementById('paymentForm').submit();
        }
        document.getElementById('confirmPayModal').addEventListener('click', function(e) {
            if (e.target === this) closeConfirmPayModal();
        });

        /* ══════════════════════════════════════
           CONFIRM CANCEL
        ══════════════════════════════════════ */
        function confirmCancelPayment() {
            document.getElementById('confirmCancelModal').classList.add('active');
        }

        function closeConfirmCancelModal() {
            document.getElementById('confirmCancelModal').classList.remove('active');
        }

        function proceedCancelPayment() {
            closeConfirmCancelModal();
            closePaymentModal();
        }
        document.getElementById('confirmCancelModal').addEventListener('click', function(e) {
            if (e.target === this) closeConfirmCancelModal();
        });

        /* ══════════════════════════════════════
           PAYMENT HISTORY MODAL
        ══════════════════════════════════════ */
        function openHistoryModal(ledger) {
            document.getElementById('hist-subtitle').textContent = 'Farmer: ' + ledger.farmer;

            const statusMap = {
                'Paid': 'badge-Paid',
                'Unpaid': 'badge-Unpaid',
                'Partial': 'badge-Partial',
                'Overdue': 'badge-Overdue'
            };
            document.getElementById('hist-status-wrap').innerHTML =
                '<span class="badge ' + (statusMap[ledger.status] || '') + '">' + ledger.status + '</span>';

            const pill = document.getElementById('hist-overdue-pill');
            if (ledger.daysOverdue > 0 && ledger.status === 'Overdue') {
                document.getElementById('hist-overdue-days').textContent = ledger.daysOverdue + ' days overdue';
                pill.style.display = 'inline-flex';
            } else {
                pill.style.display = 'none';
            }

            document.getElementById('hist-reservation-date').textContent = ledger.reservationDate;
            document.getElementById('hist-booking-date').textContent = ledger.bookingDate;
            document.getElementById('hist-due-date').textContent = ledger.dueDate;
            document.getElementById('hist-pay-count').textContent = ledger.payments + ' payment(s)';
            document.getElementById('hist-farmer').textContent = ledger.farmer;
            document.getElementById('hist-phone').textContent = ledger.phone || '—';
            document.getElementById('hist-farmeraddr').textContent = ledger.farmerAddr || '—';
            document.getElementById('hist-machine').textContent = ledger.machine;
            document.getElementById('hist-lot').textContent = ledger.lot || '—';
            document.getElementById('hist-farmsize').textContent = ledger.farmSize;
            document.getElementById('hist-farmlocation').textContent = ledger.farmLocation || '—';
            document.getElementById('hist-total').textContent = '₱' + fmt(ledger.total);
            document.getElementById('hist-paid').textContent = '₱' + fmt(ledger.paid);
            document.getElementById('hist-balance').textContent = '₱' + fmt(ledger.balance);

            const txList = document.getElementById('hist-tx-list');
            const txCount = document.getElementById('hist-tx-count');
            const transactions = allTransactions[ledger.id] || [];
            txCount.textContent = transactions.length + ' record' + (transactions.length !== 1 ? 's' : '');

            if (transactions.length === 0) {
                txList.innerHTML = '<div class="hist-no-tx"><i class="fas fa-inbox"></i>No payment transactions recorded yet.</div>';
            } else {
                txList.innerHTML = transactions.map((tx) => {
                    const notesHtml = tx.notes ?
                        `<div class="hist-tx-field full-width">
                       <div class="lbl">Notes</div>
                       <div class="val">${esc(tx.notes)}</div>
                   </div>` :
                        '';
                    return `
            <div class="hist-tx-card">
                <div class="hist-tx-top">
                    <div class="hist-tx-or-block">
                        <div class="or-tag">Official Receipt No.</div>
                        <div class="or-val">${esc(tx.or_number)}</div>
                    </div>
                    <div class="hist-tx-amt-block">
                        <div class="amt-tag">Amount Paid</div>
                        <div class="amt-val">₱${fmt(tx.amount)}</div>
                    </div>
                </div>
                <div class="hist-tx-body">
                    <div class="hist-tx-field">
                        <div class="lbl">Payment Date</div>
                        <div class="val">${formatDate(tx.payment_date)}</div>
                    </div>
                    <div class="hist-tx-field">
                        <div class="lbl">Farmer Name</div>
                        <div class="val">${esc(ledger.farmer)}</div>
                    </div>
                    <div class="hist-tx-field">
                        <div class="lbl">Farm Location</div>
                        <div class="val">${esc(ledger.farmLocation || '—')}</div>
                    </div>
                    <div class="hist-tx-field">
                        <div class="lbl">Farm Lot</div>
                        <div class="val">${esc(ledger.lot || '—')}</div>
                    </div>
                    <div class="hist-tx-field">
                        <div class="lbl">Farm Size</div>
                        <div class="val">${esc(ledger.farmSize)} ha</div>
                    </div>
                    <div class="hist-tx-field">
                        <div class="lbl">Reservation Date</div>
                        <div class="val">${esc(ledger.reservationDate)}</div>
                    </div>
                    <div class="hist-tx-field">
                        <div class="lbl">Schedule</div>
                        <div class="val">${esc(ledger.bookingDate)}</div>
                    </div>
                    <div class="hist-tx-field">
                        <div class="lbl">Collected By</div>
                        <div class="val">${esc(tx.collected_by_name || 'N/A')}</div>
                    </div>
                    <div class="hist-tx-field">
                        <div class="lbl">Due Date</div>
                        <div class="val">${esc(ledger.dueDate)}</div>
                    </div>
                    <div class="hist-tx-field">
                        <div class="lbl">Status</div>
                        <div class="val"><span class="badge badge-${esc(ledger.status)}">${esc(ledger.status)}</span></div>
                    </div>
                    ${notesHtml}
                </div>
            </div>`;
                }).join('');
            }

            document.getElementById('historyModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeHistoryModal() {
            document.getElementById('historyModal').classList.remove('active');
            document.body.style.overflow = '';
        }
        document.getElementById('historyModal').addEventListener('click', function(e) {
            if (e.target === this) closeHistoryModal();
        });

        /* ══════════════════════════════════════
           PRINT
        ══════════════════════════════════════ */
        function printList() {
            const pb = document.getElementById('printTableBody');
            pb.innerHTML = '';
            document.querySelectorAll('.ledger-row').forEach((row, idx) => {
                const l = buildLedger(row);
                const tr = document.createElement('tr');
                tr.innerHTML =
                    `<td>${idx+1}</td>` +
                    `<td>${esc(l.reservationDate)}</td>` +
                    `<td>${esc(l.farmer)}</td>` +
                    `<td>${esc(l.farmerAddr||'—')}</td>` +
                    `<td>${esc(l.phone||'—')}</td>` +
                    `<td>${esc(l.farmLocation||'—')}</td>` +
                    `<td>${esc(l.lot||'—')}</td>` +
                    `<td>${l.farmSize} ha</td>` +
                    `<td>${esc(l.machineType)}</td>` +
                    `<td>${esc(l.machine)}</td>` +
                    `<td>₱${fmt(l.total)}</td>` +
                    `<td>₱${fmt(l.paid)}</td>` +
                    `<td>₱${fmt(l.balance)}</td>` +
                    `<td>${l.dueFormatted}</td>` +
                    `<td>${l.schedule}</td>` +
                    `<td>${esc(l.status)}</td>`;
                pb.appendChild(tr);
            });
            const area = document.getElementById('printArea');
            area.classList.add('print-active');
            window.addEventListener('afterprint', function h() {
                area.classList.remove('print-active');
                window.removeEventListener('afterprint', h);
            });
            window.print();
        }

        /* ══════════════════════════════════════
           RECEIPT MODAL (Payment Successful)
        ══════════════════════════════════════ */
        function openReceiptModal(r) {
            document.getElementById('rm-association').textContent = r.association;
            document.getElementById('rm-or').textContent = r.or_number;
            document.getElementById('rm-date').textContent = r.date;
            document.getElementById('rm-farmer').textContent = r.farmer;
            document.getElementById('rm-phone').textContent = r.farmer_phone;
            document.getElementById('rm-machinetype').textContent = r.machine_type;
            document.getElementById('rm-machinename').textContent = r.machine_name;
            document.getElementById('rm-lot').textContent = r.lot + ' / ' + r.farm_size + ' ha';
            document.getElementById('rm-amount').textContent = '₱' + fmt(r.amount_paid);
            document.getElementById('rm-total').textContent = '₱' + fmt(r.total_amount);
            document.getElementById('rm-paidtotal').textContent = '₱' + fmt(r.amount_paid_total);
            document.getElementById('rm-balance').textContent = '₱' + fmt(r.balance);

            const box = document.getElementById('rm-balance-box');
            if (parseFloat(r.balance) <= 0) {
                box.classList.add('paid');
            } else {
                box.classList.remove('paid');
            }

            document.getElementById('receiptModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeReceiptModal() {
            document.getElementById('receiptModal').classList.remove('active');
            document.body.style.overflow = '';
        }
        document.getElementById('receiptModal').addEventListener('click', function(e) {
            if (e.target === this) closeReceiptModal();
        });

        function printReceipt() {
            const r = window.__lastReceipt;
            if (!r) return;

            document.getElementById('rp-association').textContent = r.association;
            document.getElementById('rp-or').textContent = r.or_number;
            document.getElementById('rp-date').textContent = r.date;
            document.getElementById('rp-farmer').textContent = r.farmer;
            document.getElementById('rp-phone').textContent = r.farmer_phone;
            document.getElementById('rp-machinetype').textContent = r.machine_type;
            document.getElementById('rp-machinename').textContent = r.machine_name;
            document.getElementById('rp-lot').textContent = r.lot + ' / ' + r.farm_size + ' ha';
            document.getElementById('rp-amount').textContent = '₱' + fmt(r.amount_paid);
            document.getElementById('rp-total').textContent = '₱' + fmt(r.total_amount);
            document.getElementById('rp-paidtotal').textContent = '₱' + fmt(r.amount_paid_total);
            document.getElementById('rp-balance').textContent = '₱' + fmt(r.balance);

            const box = document.getElementById('rp-balance-box');
            if (parseFloat(r.balance) <= 0) {
                box.classList.add('paid');
            } else {
                box.classList.remove('paid');
            }

            const area = document.getElementById('receiptPrintArea');
            area.classList.add('print-active');
            window.addEventListener('afterprint', function h() {
                area.classList.remove('print-active');
                window.removeEventListener('afterprint', h);
            });
            window.print();
        }

        /* ══════════════════════════════════════
           HELPERS
        ══════════════════════════════════════ */
        function fmt(n) {
            return parseFloat(n || 0).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function formatDate(str) {
            if (!str) return '—';
            const d = new Date(str + 'T00:00:00');
            return d.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: '2-digit'
            });
        }

        function esc(t) {
            if (!t) return '';
            return String(t).replace(/[&<>"']/g, c =>
                ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                } [c]));
        }

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                closeConfirmPayModal();
                closeConfirmCancelModal();
                closePaymentModal();
                closeHistoryModal();
                closeReceiptModal();
            }
        });

        <?php if ($message && $receipt): ?>
            document.addEventListener('DOMContentLoaded', function() {
                const lid = '<?= (int)($_POST['ledger_id'] ?? 0) ?>';
                if (lid) {
                    const row = document.querySelector('.ledger-row[data-id="' + lid + '"]');
                    if (row) row.click();
                }

                const receiptData = <?= json_encode($receipt) ?>;
                window.__lastReceipt = receiptData;
                openReceiptModal(receiptData);
            });
        <?php endif; ?>
    </script>
</body>

</html>