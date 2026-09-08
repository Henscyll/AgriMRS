<?php
session_start();
require_once '../includes/db_connection.php';
include('operator_dashboard.php');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'operator') {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

/* ── Get operator record ── */
$op_stmt = $conn->prepare("
    SELECT o.*, a.name AS association_name
    FROM operators o
    JOIN associations a ON o.association_id = a.id
    WHERE o.user_id = ? AND o.status = 'Active'
");
$op_stmt->bind_param("i", $user_id);
$op_stmt->execute();
$operator = $op_stmt->get_result()->fetch_assoc();
$op_stmt->close();

if (!$operator) die("Operator account not found or inactive.");
$operator_id      = $operator['id'];
$operator_name    = $operator['name'];
$association_name = $operator['association_name'];

$success = $error = '';

/* ── Filter params ── */
$search_field  = $_GET['search_field'] ?? 'All';
$search_term   = $_GET['search_term']  ?? '';
$type_filter   = $_GET['machine_type'] ?? '';
$status_filter = $_GET['status']       ?? '';
$field_changed = $_GET['field_changed'] ?? '0';

/* ── Build WHERE ── */
$where  = ["mo.operator_id = ?", "mo.status = 'Active'"];
$params = [$operator_id];
$types  = "i";

if ($field_changed !== '1') {
    if ($search_field === 'machine_name' && !empty($search_term)) {
        $where[] = "m.machine_name LIKE '%" . $conn->real_escape_string($search_term) . "%'";
    } elseif ($search_field === 'machine_type' && !empty($type_filter)) {
        $where[] = "m.type = '" . $conn->real_escape_string($type_filter) . "'";
    } elseif ($search_field === 'status' && !empty($status_filter)) {
        $where[] = "m.status = '" . $conn->real_escape_string($status_filter) . "'";
    }
}

$where_sql = implode(" AND ", $where);

/* ── Fetch machines ── */
$stmt = $conn->prepare("
    SELECT m.id, m.machine_name, m.type, m.status, m.image_path,
           m.price_per_hectare, m.description, m.created_at,
           mo.assigned_at,
           (SELECT COUNT(*) FROM bookings b
            WHERE b.machine_id = m.id AND b.status = 'Completed') AS completed_bookings,
           (SELECT COUNT(*) FROM bookings b
            WHERE b.machine_id = m.id AND b.status = 'Pending') AS pending_bookings
    FROM machines m
    JOIN machine_operators mo ON m.id = mo.machine_id
    WHERE $where_sql
    ORDER BY m.machine_name ASC
");
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result   = $stmt->get_result();
$machines = [];
while ($r = $result->fetch_assoc()) $machines[] = $r;
$stmt->close();
$total = count($machines);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Machines - Operator</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; overflow:hidden; }

/* ── Scrollable wrapper ── */
.main-scroll-container {
    position:fixed; top:120px; left:0; right:0; bottom:0;
}
.container { max-width:1400px; margin:0 auto; padding:20px 20px 80px; }

/* ── Welcome bar ── */
.welcome-bar {
    display:flex; align-items:center; justify-content:space-between;
    margin-bottom:14px; flex-wrap:wrap; gap:8px;
}
.welcome-bar .welcome-text {
    color: #000000; font-size:15px; font-weight:600;
    text-shadow:0 1px 4px rgba(0,0,0,.4);
}
.welcome-bar .logout-link {
    color:#000000; font-size:13px; font-weight:600;
    background:rgba(255,255,255,.2); border:1px solid rgba(255,255,255,.4);
    padding:5px 14px; border-radius:6px; text-decoration:none;
    transition:.2s;
}
.welcome-bar .logout-link:hover { background:rgba(255,255,255,.35); }

/* ── Alerts ── */
.alert { padding:11px 16px; border-radius:7px; margin-bottom:16px;
         display:flex; align-items:center; gap:9px; font-size:14px; }
.alert-success { background:#d1fae5; color:#065f46; border-left:4px solid #10b981; }
.alert-error   { background:#fee2e2; color:#991b1b; border-left:4px solid #ef4444; }

/* ── Page title ── */
.page-title-bar {
    display:flex; justify-content:center; align-items:center;
    margin-bottom:18px; position:relative;
}
.page-title-bar h1 {
    font-size:26px; font-weight:700; color: #2d7a2d;
    text-shadow:0 2px 6px rgba(0,0,0,.45);
    text-align:center;
}
.btn-print {
    position:absolute; right:0;
    padding:9px 18px; background:#2d7a2d; color:white;
    border:none; border-radius:7px; font-size:13px;
    font-weight:600; cursor:pointer;
    display:flex; align-items:center; gap:7px;
    transition:.2s; text-shadow:none;
}
.btn-print:hover { background:#1a5c1a; }

/* ── Filter bar ── */
.search-form {
    margin-bottom:16px; display:flex;
    flex-wrap:wrap; align-items:center; gap:10px;
}
.search-form select,
.search-form input[type="text"],
.search-form button {
    padding:8px 12px; font-size:13px;
    border:1px solid #2d7a2d; border-radius:6px;
}
.search-form button { background:#2d7a2d; color:white; border:none; cursor:pointer; transition:.2s; }
.search-form button:hover { background:#1a5c1a; }
.total-count {
    margin-left:auto; color: #000000; font-weight:bold; font-size:14px;
    text-shadow:1px 1px 3px rgba(0,0,0,.5); white-space:nowrap;
}

/* ── Hint text ── */
.dbl-click-hint {
    color:rgba(255,255,255,.85); font-size:12px; font-style:italic;
    margin-bottom:8px; display:flex; align-items:center; gap:6px;
    text-shadow:0 1px 3px rgba(0,0,0,.4);
}

/* ── Table container ── */
.table-container {
    border:1px solid #ddd; border-radius:8px;
    background:white; box-shadow:0 2px 5px rgba(0,0,0,.1);
    overflow:hidden; margin-bottom:18px;
}
.table-container table {
    width:100%; border-collapse:collapse; table-layout:fixed;
}
.table-container thead { display:table; width:100%; table-layout:fixed; }
.table-container tbody {
    display:block; max-height:360px;
    overflow-y:auto; overflow-x:hidden; width:100%;
}
.table-container tbody::-webkit-scrollbar { width:8px; }
.table-container tbody::-webkit-scrollbar-track { background:#f1f1f1; border-radius:4px; }
.table-container tbody::-webkit-scrollbar-thumb { background:#2d7a2d; border-radius:4px; }
.table-container tbody::-webkit-scrollbar-thumb:hover { background:#1a5c1a; }
.table-container tbody tr {
    display:table; width:100%; table-layout:fixed;
    cursor:pointer; transition:background .15s;
}
.table-container tbody tr:hover    { background:#f8fffe; }
.table-container tbody tr.selected { background:#d1fae5 !important; }

th, td {
    padding:11px 13px; text-align:left;
    border-bottom:1px solid #eee; vertical-align:middle;
}
th { background:#2d7a2d; color:white; font-weight:normal; font-size:13px; }

/* Column widths — 7 columns */
th:nth-child(1), td:nth-child(1) { width:5%;  }
th:nth-child(2), td:nth-child(2) { width:15%; }
th:nth-child(3), td:nth-child(3) { width:10%; }
th:nth-child(4), td:nth-child(4) { width:14%; }
th:nth-child(5), td:nth-child(5) { width:24%; }
th:nth-child(6), td:nth-child(6) { width:14%; }
th:nth-child(7), td:nth-child(7) { width:18%; }

/* Machine thumbnail */
.machine-img {
    width:42px; height:42px; border-radius:6px; object-fit:cover;
    border:1px solid #e5e7eb; background:#f9fafb;
    display:block;
}
.machine-name { font-weight:600; color:#1f2937; font-size:13px; }

/* Type badge */
.type-badge {
    font-size:11px; padding:2px 7px;
    background:#dcfce7; color:#15803d; border-radius:8px; display:inline-block;
}

/* Status badge */
.status-badge {
    display:inline-block; padding:3px 9px;
    border-radius:20px; font-size:12px; font-weight:700;
}
.status-available          { background:#d1fae5; color:#065f46; }
.status-in-use             { background:#dbeafe; color:#1e40af; }
.status-under-maintenance  { background:#fef3c7; color:#92400e; }
.status-inactive           { background:#fee2e2; color:#991b1b; }
.status-active             { background:#d1fae5; color:#065f46; }

/* ── MODAL ── */
.modal-overlay {
    display:none; position:fixed; inset:0;
    background:rgba(0,0,0,.55); z-index:9999;
    align-items:center; justify-content:center;
    padding:20px; backdrop-filter:blur(3px);
}
.modal-overlay.open { display:flex; }
.modal-box {
    background:white; border-radius:14px;
    box-shadow:0 20px 50px rgba(0,0,0,.25);
    width:100%; max-width:560px; max-height:90vh;
    overflow-y:auto;
    animation:modalIn .25s ease;
}
.modal-box::-webkit-scrollbar { width:6px; }
.modal-box::-webkit-scrollbar-thumb { background:#2d7a2d; border-radius:4px; }
@keyframes modalIn {
    from { opacity:0; transform:translateY(-18px) scale(.97); }
    to   { opacity:1; transform:translateY(0) scale(1); }
}

.modal-head {
    background:linear-gradient(135deg,#2d7a2d,#1a5c1a);
    padding:18px 22px; border-radius:14px 14px 0 0;
    display:flex; justify-content:space-between; align-items:flex-start;
}
.modal-head h3 { color:white; font-size:18px; font-weight:700; margin:0; }
.modal-head p  { color:rgba(255,255,255,.8); font-size:13px; margin:3px 0 0; }
.modal-x {
    width:30px; height:30px; background:rgba(255,255,255,.2);
    border:none; border-radius:50%; color:white; font-size:17px;
    cursor:pointer; display:flex; align-items:center; justify-content:center;
    flex-shrink:0; transition:.2s;
}
.modal-x:hover { background:rgba(255,255,255,.35); }

/* Small image inside modal */
.modal-img-wrap {
    display:flex; justify-content:center; align-items:center;
    padding:14px 0 6px;
    border-bottom:1px solid #e5e7eb;
}
.modal-img-wrap img {
    width:140px; height:110px; object-fit:cover;
    border-radius:8px; border:1px solid #e5e7eb;
}
.modal-img-wrap .no-img { font-size:40px; color:#d1d5db; }

.modal-bd { padding:18px 22px; }

.m-sec {
    font-size:11px; font-weight:700; color:#6b7280;
    text-transform:uppercase; letter-spacing:.5px;
    margin:0 0 10px; display:flex; align-items:center; gap:8px;
}
.m-sec::after { content:''; flex:1; height:1px; background:#e5e7eb; }

.detail-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:16px; }
.detail-item { background:#f9fafb; border-radius:8px; padding:10px 13px; }
.detail-item .lbl { font-size:10px; color:#9ca3af; font-weight:700; text-transform:uppercase; letter-spacing:.4px; }
.detail-item .val { font-size:14px; color:#1f2937; font-weight:600; margin-top:3px; }
.detail-item.full { grid-column:1/-1; }

/* Booking stat pills */
.booking-pills { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:16px; }
.booking-pill {
    flex:1; min-width:100px; text-align:center;
    padding:12px 10px; border-radius:10px; border:2px solid #e5e7eb;
}
.booking-pill .pill-num { font-size:24px; font-weight:700; line-height:1; }
.booking-pill .pill-lbl { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.3px; margin-top:4px; color:#6b7280; }
.pill-pending   { border-color:#fde68a; background:#fffbeb; }
.pill-pending   .pill-num { color:#92400e; }
.pill-completed { border-color:#6ee7b7; background:#ecfdf5; }
.pill-completed .pill-num { color:#065f46; }

.m-foot {
    padding:12px 22px; background:#f9fafb;
    border-top:1px solid #e5e7eb;
    display:flex; gap:10px; justify-content:flex-end;
    border-radius:0 0 14px 14px;
}
.btn-close-modal {
    padding:9px 22px; background:white; color:#6b7280;
    border:2px solid #e5e7eb; border-radius:6px;
    font-size:14px; font-weight:600; cursor:pointer; transition:.2s;
}
.btn-close-modal:hover { background:#f3f4f6; }

/* ── Print styles ── */
@media print {
    .search-form, .dbl-click-hint, .welcome-bar,
    .btn-print, .modal-overlay { display:none !important; }
    .main-scroll-container { position:static !important; top:0 !important; }
    body { overflow:visible !important; }
    .table-container tbody { max-height:none !important; overflow:visible !important; display:table-row-group !important; }
    .table-container table { table-layout:auto !important; }
    .table-container thead { display:table-header-group !important; }
    .page-title-bar h1 { color:#000 !important; text-shadow:none !important; }
    .container { padding:10px !important; }
}
</style>
</head>
<body>

<!-- ══════════════════════════════════════
     VIEW MACHINE MODAL
══════════════════════════════════════ -->
<div class="modal-overlay" id="viewModal">
    <div class="modal-box">
        <div class="modal-head">
            <div>
                <h3 id="v-modal-title">Machine Details</h3>
                <p id="v-modal-sub">Assigned machine information</p>
            </div>
            <button class="modal-x" onclick="closeModal('viewModal')"><i class="fas fa-times"></i></button>
        </div>

        <!-- Small image -->
        <div class="modal-img-wrap" id="v-img-wrap">
            <i class="fas fa-tractor no-img"></i>
        </div>

        <div class="modal-bd">
            <div class="booking-pills">
                <div class="booking-pill pill-pending">
                    <div class="pill-num" id="v-pending">0</div>
                    <div class="pill-lbl">Pending</div>
                </div>
                <div class="booking-pill pill-completed">
                    <div class="pill-num" id="v-completed">0</div>
                    <div class="pill-lbl">Completed</div>
                </div>
            </div>

            <div class="m-sec">Machine Information</div>
            <div class="detail-grid">
                <div class="detail-item">
                    <div class="lbl">Machine Name</div>
                    <div class="val" id="v-name">—</div>
                </div>
                <div class="detail-item">
                    <div class="lbl">Machine Type</div>
                    <div class="val" id="v-type">—</div>
                </div>
                <div class="detail-item">
                    <div class="lbl">Status</div>
                    <div class="val" id="v-status">—</div>
                </div>
                <div class="detail-item">
                    <div class="lbl">Rate / Ha</div>
                    <div class="val" id="v-price">—</div>
                </div>
                <div class="detail-item full">
                    <div class="lbl">Description</div>
                    <div class="val" id="v-description" style="font-weight:400;font-size:13px;color:#374151;line-height:1.6;">—</div>
                </div>
            </div>

            <div class="m-sec">Assignment Info</div>
            <div class="detail-grid">
                <div class="detail-item">
                    <div class="lbl">Date Acquired</div>
                    <div class="val" id="v-assigned">—</div>
                </div>
                <div class="detail-item">
                    <div class="lbl">Association</div>
                    <div class="val"><?= htmlspecialchars($association_name) ?></div>
                </div>
            </div>
        </div>

        <div class="m-foot">
            <button class="btn-close-modal" onclick="closeModal('viewModal')">Close</button>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════
     PAGE
══════════════════════════════════════ -->
<div class="main-scroll-container">
<div class="container">

    <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i><?= $success ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Welcome bar -->
    <div class="welcome-bar">
        <span class="welcome-text">Welcome, <?= htmlspecialchars($operator_name) ?>  <a class="logout-link" href="../logout.php">Logout</a> </span>
       
    </div>

    <!-- Page title -->
    <div class="page-title-bar">
        <h1>My Machines</h1>
        
    </div>

    <!-- Filter bar -->
    <form class="search-form" method="GET" id="searchForm">
        <input type="hidden" name="field_changed" id="field_changed" value="0">

        <select name="search_field" id="search_field" onchange="handleFieldChange()">
            <option value="All"          <?= $search_field==='All'          ?'selected':'' ?>>All</option>
            <option value="machine_name" <?= $search_field==='machine_name' ?'selected':'' ?>>Machine Name</option>
            <option value="machine_type" <?= $search_field==='machine_type' ?'selected':'' ?>>Machine Type</option>
            <option value="status"       <?= $search_field==='status'       ?'selected':'' ?>>Status</option>
        </select>

        <input type="text" name="search_term" id="textInput"
               placeholder="Enter machine name..."
               value="<?= htmlspecialchars($search_term) ?>"
               style="display:none;">

        <select name="machine_type" id="typeInput" style="display:none;">
            <option value="">All Types</option>
            <option value="Tractor"   <?= $type_filter==='Tractor'   ?'selected':'' ?>>Tractor</option>
            <option value="Harvester" <?= $type_filter==='Harvester' ?'selected':'' ?>>Harvester</option>
        </select>

        <select name="status" id="statusInput" style="display:none;">
            <option value="">All Statuses</option>
            <option value="Available"         <?= $status_filter==='Available'         ?'selected':'' ?>>Available</option>
            <option value="In Use"            <?= $status_filter==='In Use'            ?'selected':'' ?>>In Use</option>
            <option value="Under Maintenance" <?= $status_filter==='Under Maintenance' ?'selected':'' ?>>Under Maintenance</option>
            <option value="Inactive"          <?= $status_filter==='Inactive'          ?'selected':'' ?>>Inactive</option>
        </select>

        <button type="submit" id="searchBtn" style="display:none;">Search</button>

        <span class="total-count">Total: <?= $total ?> machine<?= $total!==1?'s':'' ?></span>
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
                    <th>Rate / Ha</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($total > 0): ?>
                <?php $i = 1; foreach ($machines as $m): ?>
                <tr class="machine-row"
                    data-id="<?= $m['id'] ?>"
                    data-name="<?= htmlspecialchars($m['machine_name'], ENT_QUOTES) ?>"
                    data-type="<?= htmlspecialchars($m['type'], ENT_QUOTES) ?>"
                    data-status="<?= htmlspecialchars($m['status'], ENT_QUOTES) ?>"
                    data-price="<?= $m['price_per_hectare'] ? '₱'.number_format($m['price_per_hectare'],2) : '—' ?>"
                    data-description="<?= htmlspecialchars($m['description'] ?? '', ENT_QUOTES) ?>"
                    data-image="<?= htmlspecialchars($m['image_path'] ?? '', ENT_QUOTES) ?>"
                    data-assigned="<?= $m['assigned_at'] ? date('M d, Y', strtotime($m['assigned_at'])) : date('M d, Y', strtotime($m['created_at'])) ?>"
                    data-completed="<?= $m['completed_bookings'] ?>"
                    data-pending="<?= $m['pending_bookings'] ?>">

                    <td><?= $i++ ?></td>
                    <td style="font-size:12px;color:#6b7280;">
                        <?= $m['assigned_at'] ? date('m/d/Y', strtotime($m['assigned_at'])) : date('m/d/Y', strtotime($m['created_at'])) ?>
                    </td>
                    <td>
                        <?php if ($m['image_path']): ?>
                            <img src="<?= htmlspecialchars($m['image_path']) ?>"
                                 class="machine-img"
                                 alt="<?= htmlspecialchars($m['machine_name']) ?>"
                                 onerror="this.src='../images/default-tractor.jpg'">
                        <?php else: ?>
                            <div class="machine-img" style="display:flex;align-items:center;justify-content:center;font-size:18px;color:#d1d5db;">
                                <i class="fas fa-tractor"></i>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="type-badge">
                            <?= htmlspecialchars($m['type']) ?>
                        </span>
                    </td>
                    <td class="machine-name"><?= htmlspecialchars($m['machine_name']) ?></td>
                    <td>
                        <?= $m['price_per_hectare'] ? '₱'.number_format($m['price_per_hectare'],2) : '<span style="color:#9ca3af;">—</span>' ?>
                    </td>
                    <td>
                        <?php $sc = strtolower(str_replace(' ','-',$m['status'])); ?>
                        <span class="status-badge status-<?= $sc ?>">
                            <?= htmlspecialchars($m['status']) ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php else: ?>
                <tr>
                    <td colspan="7" style="text-align:center;padding:40px;color:#9ca3af;">
                        No machines found
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>
</div>

<script>
/* ── Filter toggles ── */
function toggleInputs() {
    const f = document.getElementById('search_field').value;
    document.getElementById('textInput').style.display   = (f==='machine_name') ? 'inline-block' : 'none';
    document.getElementById('typeInput').style.display   = (f==='machine_type') ? 'inline-block' : 'none';
    document.getElementById('statusInput').style.display = (f==='status')       ? 'inline-block' : 'none';
    document.getElementById('searchBtn').style.display   = (f!=='All')          ? 'inline-block' : 'none';
}
window.addEventListener('DOMContentLoaded', toggleInputs);

function handleFieldChange() {
    document.getElementById('textInput').value   = '';
    document.getElementById('typeInput').value   = '';
    document.getElementById('statusInput').value = '';
    document.getElementById('field_changed').value = '1';
    document.getElementById('searchForm').submit();
}

/* ── Double-click to open view modal ── */
document.querySelectorAll('.machine-row').forEach(function(row) {
    row.addEventListener('dblclick', function() {
        openViewModal(this);
    });
    row.addEventListener('click', function() {
        document.querySelectorAll('.machine-row').forEach(r => r.classList.remove('selected'));
        this.classList.add('selected');
    });
});

/* ── Modal helpers ── */
function closeModal(id) {
    document.getElementById(id).classList.remove('open');
}
document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) closeModal(this.id);
    });
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.open').forEach(function(m) { closeModal(m.id); });
    }
});

/* ── View modal ── */
function openViewModal(row) {
    const d = row.dataset;

    document.getElementById('v-modal-title').textContent = d.name;
    document.getElementById('v-modal-sub').textContent   = d.type + ' — ' + d.status;

    const wrap = document.getElementById('v-img-wrap');
    wrap.innerHTML = d.image
        ? '<img src="' + d.image + '" alt="' + d.name + '" onerror="this.parentElement.innerHTML=\'<i class=\\\'fas fa-tractor no-img\\\'></i>\'">'
        : '<i class="fas fa-tractor no-img"></i>';

    document.getElementById('v-pending').textContent     = d.pending;
    document.getElementById('v-completed').textContent   = d.completed;
    document.getElementById('v-name').textContent        = d.name;
    document.getElementById('v-type').textContent        = d.type;
    document.getElementById('v-price').textContent       = d.price;
    document.getElementById('v-assigned').textContent    = d.assigned;
    document.getElementById('v-description').textContent = d.description || 'No description available.';

    const sc = d.status.toLowerCase().replace(/ /g,'-');
    document.getElementById('v-status').innerHTML =
        '<span class="status-badge status-' + sc + '">' + d.status + '</span>';

    document.getElementById('viewModal').classList.add('open');
}
</script>

</body>
</html>