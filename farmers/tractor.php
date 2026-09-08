<?php
session_start();
require '../includes/config.php';
include 'farmers_header.php';
include_once '../includes/farmer_auth.php';

$user_id = $_SESSION['user_id'];
$farmer_query = $conn->prepare("SELECT id FROM farmers WHERE user_id = ?");
$farmer_query->bind_param("i", $user_id);
$farmer_query->execute();
$farmer_result = $farmer_query->get_result();

if ($farmer_result->num_rows === 0) {
    die("Error: Farmer profile not found. Please contact administrator.");
}

$farmer_data = $farmer_result->fetch_assoc();
$farmer_id   = $farmer_data['id'];

$message = "";
$message_type = "";

/* ── Farmer lots ── */
$lots_query = $conn->prepare("
    SELECT id, lot_number, farm_location, farm_size 
    FROM farmer_lots 
    WHERE farmer_id = ? AND status = 'Active'
    ORDER BY lot_number
");
$lots_query->bind_param("i", $farmer_id);
$lots_query->execute();
$farmer_lots = $lots_query->get_result();
$has_lots    = $farmer_lots->num_rows > 0;

$lots_array = [];
if ($has_lots) {
    $farmer_lots->data_seek(0);
    while ($lot = $farmer_lots->fetch_assoc()) $lots_array[] = $lot;
}

/* ── Handle booking POST ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $machine_id   = intval($_POST['machine_id']);
    $booking_date = $_POST['booking_date'];   /* arrives as YYYY-MM-DD from hidden field */
    $lot_id       = !empty($_POST['lot_id']) ? intval($_POST['lot_id']) : null;
    $notes        = $_POST['notes'] ?? '';

    if ($lot_id) {
        $verify_lot = $conn->prepare("SELECT id, farm_size FROM farmer_lots WHERE id = ? AND farmer_id = ?");
        $verify_lot->bind_param("ii", $lot_id, $farmer_id);
        $verify_lot->execute();
        $lot_result = $verify_lot->get_result();

        if ($lot_result->num_rows === 0) {
            $message = "❌ Invalid lot selection.";
            $message_type = "error";
        } else {
            $lot_data  = $lot_result->fetch_assoc();
            $farm_size = $lot_data['farm_size'];

            $check = $conn->prepare("
                SELECT id FROM bookings 
                WHERE machine_id = ? AND booking_date = ?
                AND status IN ('Pending','Approved')
            ");
            $check->bind_param("is", $machine_id, $booking_date);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                $message = "❌ This tractor is already booked on this date.";
                $message_type = "error";
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO bookings (machine_id, farmer_id, lot_id, booking_date, farm_size, notes)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("iiisds", $machine_id, $farmer_id, $lot_id, $booking_date, $farm_size, $notes);
                if ($stmt->execute()) {
                    $message = "✅ Booking submitted! Your booking ID is #" . $stmt->insert_id;
                    $message_type = "success";
                } else {
                    $message = "❌ Error: " . $stmt->error;
                    $message_type = "error";
                }
            }
        }
    } else {
        $message = "❌ Please select a lot for this booking.";
        $message_type = "error";
    }
}

/* ── Search / filter ── */
$search        = $_GET['search']        ?? '';
$filter        = $_GET['filter']        ?? 'all';
$field_changed = $_GET['field_changed'] ?? '0';

$where  = "WHERE m.type = 'Tractor' AND m.status = 'Active'";
$params = [];

if ($field_changed !== '1' && !empty($search)) {
    if ($filter === 'association') {
        $where   .= " AND a.name LIKE ?";
        $params[] = "%$search%";
    } elseif ($filter === 'machine') {
        $where   .= " AND m.machine_name LIKE ?";
        $params[] = "%$search%";
    } elseif ($filter === 'municipality') {
        $where   .= " AND a.municipality LIKE ?";
        $params[] = "%$search%";
    }
}

$sql = "
    SELECT m.*, a.name AS association_name, a.municipality
    FROM machines m
    LEFT JOIN associations a ON m.association_id = a.id
    $where
    ORDER BY m.created_at DESC
";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param(str_repeat("s", count($params)), ...$params);
}
$stmt->execute();
$tractors = $stmt->get_result();

$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html>
<head>
<title>Book Tractor</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.css">
<style>
:root {
    --primary:       #2e7d32;
    --primary-color: #2e7d32;
    --primary-dark:  #1b5e20;
    --primary-light: #4caf50;
    --danger:        #d32f2f;
    --warning:       #f57c00;
    --bg-light:      #f5f5f5;
    --border:        #e0e0e0;
    --text:          #212121;
    --text-muted:    #757575;
    --text-dark: #212121;
    --text-light: #757575;

}
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; color:var(--text); }

/* ── Scrollable area below fixed header ── */
.main-scroll-container {
    position:fixed; top:120px; left:0; right:0; bottom:0;
    overflow-y:scroll; overflow-x:hidden;
}
.main-scroll-container::-webkit-scrollbar { width:8px; }
.main-scroll-container::-webkit-scrollbar-thumb { background:var(--primary); border-radius:10px; }

.page-content {
    max-width:1400px; margin:0 auto;
    padding:16px 16px 80px;
}

/* ── Page header ── */
.page-header { margin-bottom:12px; }
.page-header h2 {
    font-size:22px; color:white;
    text-shadow:0 2px 6px rgba(0,0,0,.45);
    display:flex; align-items:center; gap:8px;
}

/* ── Alert ── */
.alert {
    padding:11px 15px; border-radius:8px; margin-bottom:12px;
    display:flex; align-items:center; gap:9px; font-size:14px;
    animation:slideDown .3s ease;
}
@keyframes slideDown {
    from { opacity:0; transform:translateY(-8px); }
    to   { opacity:1; transform:translateY(0); }
}
.alert.success { background:#e8f5e9; color:#388e3c; border-left:4px solid #388e3c; }
.alert.error   { background:#ffebee; color:#d32f2f; border-left:4px solid #d32f2f; }

/* ── No lots warning ── */
.no-lots-warning {
    background:#fff3e0; border:2px solid var(--warning);
    border-radius:8px; padding:12px; margin-bottom:12px;
    display:flex; align-items:flex-start; gap:10px; font-size:14px;
}
.no-lots-warning i { color:var(--warning); font-size:20px; }
.no-lots-warning h3 { color:var(--warning); font-size:15px; margin-bottom:4px; }
.no-lots-warning p  { color:var(--text-muted); margin-bottom:8px; }
.no-lots-warning a {
    color:white; background:var(--warning); padding:6px 12px;
    border-radius:6px; text-decoration:none;
    display:inline-flex; align-items:center; gap:6px;
    font-weight:600; font-size:13px;
}

/* ══════════════════════════════════
   SEARCH BAR
══════════════════════════════════ */
.search-container {
    padding:10px 12px; border-radius:8px;
    box-shadow:0 1px 3px rgba(0,0,0,.1);
    margin-bottom:14px;
    display:flex; gap:8px; align-items:center; flex-wrap:wrap;
}
.filter-select {
    padding:8px 12px; border:1px solid var(--border);
    border-radius:6px; font-size:14px; background:white;
    cursor:pointer; min-width:150px;
}
.filter-select:focus { outline:none; border-color:var(--primary); }

.search-input-wrapper { flex:1; position:relative; }
.search-input {
    padding: 8px 40px 8px 12px;
    border: 1px solid var(--border-color);
    color: black;
    border-radius: 6px;
    font-size: 14px;
}
.search-input:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 2px rgba(46, 125, 50, 0.1);
}
.search-btn, .clear-btn {
    margin-right: 820px;
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    font-size: 14px;
    display: inline-flex;
    align-items: center;
    gap: 1px;
    transition: all 0.3s;
}
.search-btn {
    background: var(--primary-color);
    color: white;
}
.search-btn:hover { background:var(--primary-dark); }
.search-btn:hover {
    background: var(--primary-dark);
}

.clear-btn {
    background: #e0e0e0;
    color: var(--text-dark);
    text-decoration: none;
}

.clear-btn:hover {
    background: #bdbdbd;
}

/* ══════════════════════════════════
   GRID — 6 col desktop / 2 col mobile
══════════════════════════════════ */
.tractor-grid {
    display:grid;
    grid-template-columns:repeat(6,1fr);
    gap:12px;
}

/* Card */
.card {
    background:white; border-radius:10px; overflow:hidden;
    box-shadow:0 1px 4px rgba(0,0,0,.1);
    border:2px solid transparent; transition:all .25s;
    display:flex; flex-direction:column;
}
.card:hover {
    transform:translateY(-4px);
    box-shadow:0 6px 18px rgba(0,0,0,.15);
    border-color:var(--primary);
}
.card-image { position:relative; height:110px; overflow:hidden; background:#f5f5f5; }
.card-image img { width:100%; height:100%; object-fit:cover; transition:transform .3s; }
.card:hover .card-image img { transform:scale(1.06); }
.card-badge {
    position:absolute; top:7px; right:7px;
    background:rgba(46,125,50,.93); color:white;
    padding:3px 8px; border-radius:12px;
    font-size:10px; font-weight:700;
    display:flex; align-items:center; gap:3px;
}
.price-ribbon {
    position:absolute; bottom:0; left:0; right:0;
    background:linear-gradient(to top,rgba(0,0,0,.65),transparent);
    color:white; padding:8px 8px 5px;
    font-size:12px; font-weight:700;
}
.card-content { padding:10px; flex:1; display:flex; flex-direction:column; }
.card-title {
    font-size:13px; font-weight:700; color:var(--text);
    margin-bottom:5px; line-height:1.3;
    display:flex; align-items:center; gap:5px;
}
.card-title i { color:var(--primary); font-size:11px; flex-shrink:0; }
.info-item {
    display:flex; align-items:center; gap:5px;
    font-size:11px; color:var(--text-muted); margin-bottom:3px;
}
.info-item i { color:var(--primary); font-size:10px; width:11px; }
.card-description {
    font-size:11px; color:var(--text-muted); line-height:1.4;
    margin:5px 0 8px; flex:1;
    display:-webkit-box; -webkit-line-clamp:2;
    -webkit-box-orient:vertical; overflow:hidden;
}
.btn-book {
    width:100%; padding:8px 6px; font-size:12px; font-weight:700;
    background:linear-gradient(135deg,var(--primary),var(--primary-light));
    color:white; border:none; border-radius:6px; cursor:pointer;
    display:flex; align-items:center; justify-content:center; gap:5px;
    transition:all .25s; margin-top:auto;
}
.btn-book:hover:not(:disabled) { transform:translateY(-2px); box-shadow:0 4px 12px rgba(46,125,50,.4); }
.btn-book:disabled { background:var(--border); cursor:not-allowed; opacity:.7; }

.empty-state {
    text-align:center; padding:50px 20px;
    background:white; border-radius:8px;
    box-shadow:0 1px 3px rgba(0,0,0,.1);
    grid-column:1/-1;
}
.empty-state i  { font-size:55px; color:var(--border); margin-bottom:14px; display:block; }
.empty-state h3 { margin-bottom:6px; }
.empty-state p  { color:var(--text-muted); font-size:14px; }

/* ══════════════════════════════════
   BOOKING MODAL
══════════════════════════════════ */
.modal {
    display:none; position:fixed; z-index:1000; inset:0;
    background:rgba(0,0,0,.5); animation:fadeIn .25s ease;
}
@keyframes fadeIn { from{opacity:0;} to{opacity:1;} }
.modal-content {
    background:white; margin:4% auto;
    border-radius:12px; width:92%; max-width:500px;
    max-height:88vh; display:flex; flex-direction:column;
    box-shadow:0 10px 40px rgba(0,0,0,.3);
    animation:slideUp .3s ease;
}
@keyframes slideUp {
    from { transform:translateY(40px); opacity:0; }
    to   { transform:translateY(0);    opacity:1; }
}
.modal-header {
    background:linear-gradient(135deg,var(--primary),var(--primary-light));
    color:white; padding:18px 20px;
    border-radius:12px 12px 0 0;
    display:flex; justify-content:space-between; align-items:center;
    flex-shrink:0;
}
.modal-header h3 { margin:0; font-size:18px; display:flex; align-items:center; gap:9px; }
.close { color:white; font-size:26px; cursor:pointer; line-height:1; transition:.2s; }
.close:hover { transform:scale(1.2); }

.modal-body { padding:20px; overflow-y:auto; flex:1; max-height:55vh; }
.modal-body::-webkit-scrollbar { width:7px; }
.modal-body::-webkit-scrollbar-thumb { background:var(--primary); border-radius:10px; }

.machine-info-modal {
    background:var(--bg-light); padding:13px;
    border-radius:8px; margin-bottom:16px;
}
.machine-info-modal h4 { margin:0 0 8px; color:var(--primary-dark); font-size:15px; }

.form-group { margin-bottom:15px; }
.form-group label {
    display:block; font-size:13px; font-weight:600;
    color:var(--text); margin-bottom:5px;
}
.form-group label .required { color:var(--danger); }

.form-group select,
.form-group textarea {
    width:100%; padding:9px 11px;
    border:2px solid var(--border); border-radius:6px;
    font-size:14px; font-family:inherit; transition:.25s;
}
.form-group select:focus,
.form-group textarea:focus {
    outline:none; border-color:var(--primary);
    box-shadow:0 0 0 3px rgba(46,125,50,.1);
}
.form-group textarea { resize:vertical; min-height:75px; }

/* ── Flatpickr date input ── */
.date-input-wrapper { position:relative; }
.date-input-wrapper .flatpickr-input {
    width:100% !important;
    padding:9px 40px 9px 11px !important;
    border:2px solid var(--border) !important;
    border-radius:6px !important;
    font-size:14px !important;
    font-family:inherit !important;
    background:white !important;
    color:var(--text) !important;
    cursor:pointer !important;
    box-sizing:border-box !important;
    transition:border-color .25s, box-shadow .25s !important;
}
.date-input-wrapper .flatpickr-input:focus {
    outline:none !important;
    border-color:var(--primary) !important;
    box-shadow:0 0 0 3px rgba(46,125,50,.1) !important;
}
.date-cal-icon {
    position:absolute; right:11px; top:50%;
    transform:translateY(-50%);
    color:var(--primary); font-size:16px; pointer-events:none;
}

.lot-info {
    background:#e8f5e9; padding:11px; border-radius:6px;
    font-size:13px; margin-top:7px;
    border-left:4px solid var(--primary);
}
.lot-info strong { color:var(--primary-dark); }

.guidelines-box {
    background:#f5f5f5; border-left:4px solid var(--primary);
    padding:11px; border-radius:6px;
    font-size:12px; color:var(--text-muted); line-height:1.75;
}
.terms-box {
    background:#fff3e0; border-left:4px solid var(--warning);
    padding:11px; border-radius:6px;
    font-size:12px; color:var(--text-muted); line-height:1.75;
}

.modal-footer { padding:0 20px 20px; display:flex; gap:10px; flex-shrink:0; }
.btn-submit {
    flex:1; padding:11px; font-size:14px; font-weight:700;
    background:linear-gradient(135deg,var(--primary),var(--primary-light));
    color:white; border:none; border-radius:7px; cursor:pointer;
    display:flex; align-items:center; justify-content:center; gap:7px; transition:.25s;
}
.btn-submit:hover { transform:translateY(-2px); box-shadow:0 4px 12px rgba(46,125,50,.4); }
.btn-cancel {
    padding:11px 18px; background:white; color:var(--text);
    border:2px solid var(--border); border-radius:7px;
    font-size:14px; font-weight:600; cursor:pointer; transition:.2s;
}
.btn-cancel:hover { background:var(--bg-light); }

/* ── Confirm modal ── */
.confirm-modal { display:none; position:fixed; z-index:1001; inset:0; background:rgba(0,0,0,.6); }
.confirm-content {
    background:white; margin:14% auto; border-radius:12px;
    width:90%; max-width:380px;
    box-shadow:0 8px 30px rgba(0,0,0,.3); animation:slideUp .3s ease;
}
.confirm-header {
    background:linear-gradient(135deg,var(--warning),#ef6c00);
    color:white; padding:18px; border-radius:12px 12px 0 0; text-align:center;
}
.confirm-header i  { font-size:44px; margin-bottom:8px; display:block; }
.confirm-header h3 { margin:0; font-size:18px; }
.confirm-body      { padding:22px; text-align:center; }
.confirm-body p    { font-size:14px; line-height:1.6; margin-bottom:8px; }
.confirm-footer    { padding:0 22px 22px; display:flex; gap:10px; }
.btn-yes { flex:1; padding:11px; background:var(--primary); color:white; border:none; border-radius:6px; font-weight:700; cursor:pointer; font-size:14px; }
.btn-yes:hover { background:var(--primary-dark); }
.btn-no  { flex:1; padding:11px; background:white; color:var(--text); border:2px solid var(--border); border-radius:6px; font-weight:700; cursor:pointer; font-size:14px; }
.btn-no:hover { background:var(--bg-light); }

/* ══════════════════════════════════
   RESPONSIVE
══════════════════════════════════ */
@media (max-width: 1100px) { .tractor-grid { grid-template-columns:repeat(4,1fr); } }
@media (max-width: 800px)  { .tractor-grid { grid-template-columns:repeat(3,1fr); } }
@media (max-width: 600px) {
    .tractor-grid         { grid-template-columns:repeat(2,1fr); gap:10px; }
    .card-image           { height:130px; }
    .card-content         { padding:8px; }
    .card-title           { font-size:12px; }
    .search-container     { flex-wrap:wrap; }
    .filter-select        { width:100%; min-width:unset; }
    .search-input-wrapper { width:100%; }
    .search-btn           { flex:1; justify-content:center; }
    .modal-content        { width:96%; margin:6% auto; }
    .modal-footer         { flex-direction:column; }
}
</style>
</head>
<body>

<div class="main-scroll-container">
<div class="page-content">

    <div class="page-header">
        <h2> Available Tractors</h2>
    </div>

    <?php if ($message): ?>
        <div class="alert <?= $message_type ?>">
            <i class="fas fa-<?= $message_type==='success'?'check-circle':'exclamation-circle' ?>"></i>
            <span><?= $message ?></span>
        </div>
    <?php endif; ?>

    <?php if (!$has_lots): ?>
        <div class="no-lots-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <div>
                <h3>No Farm Lots Registered</h3>
                <p>Register at least one farm lot before booking a tractor.</p>
                <a href="my_account.php"><i class="fas fa-plus-circle"></i> Add Farm Lot</a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Search bar -->
    <form method="GET" class="search-container" id="searchForm">
        <input type="hidden" name="field_changed" id="field_changed" value="0">


        <div class="search-input-wrapper" id="searchInputWrapper"
             style="<?= $filter==='all' ? 'display:none;' : '' ?>">
            <input type="text"
                   name="search"
                   id="searchInput"
                   class="search-input"
                   placeholder="<?= $filter==='association'?'Search by association...':($filter==='machine'?'Search by machine name...':'Search by municipality...') ?>"
                   value="<?= ($field_changed==='1' || $filter==='all') ? '' : htmlspecialchars($search) ?>">
        </div>

        <button type="submit" class="search-btn" id="searchBtn"
                style="<?= $filter==='all' ? 'display:none;' : '' ?>">
            <i class="fas fa-search"></i> Search
        </button>

        <?php if (!empty($search) && $filter !== 'all' && $field_changed !== '1'): ?>
            <a href="tractor.php?filter=<?= urlencode($filter) ?>" class="clear-btn">
                <i class="fas fa-times"></i> Clear
            </a>
        <?php endif; ?>
    </form>

    <!-- Tractor Grid -->
    <div class="tractor-grid">
        <?php if ($tractors->num_rows > 0): ?>
            <?php while ($row = $tractors->fetch_assoc()): ?>
            <div class="card">
                <div class="card-image">
                    <img src="<?= !empty($row['image_path']) ? htmlspecialchars($row['image_path']) : '../uploads/default-tractor.jpg' ?>"
                         alt="<?= htmlspecialchars($row['machine_name']) ?>"
                         onerror="this.src='../uploads/default-tractor.jpg'">
                    <!-- <span class="card-badge">
                        <i class="fas fa-circle-check"></i>
                        <?= htmlspecialchars($row['status']) ?>
                    </span> -->
                    <div class="price-ribbon">
                        ₱<?= number_format($row['price_per_hectare'],2) ?>/ha
                    </div>
                </div>
                <div class="card-content">
                    <h3 class="card-title">
                        <i class="fas fa-tractor"></i>
                        <?= htmlspecialchars($row['machine_name']) ?>
                    </h3>
                    <div class="info-item">
                        <i class="fas fa-users"></i>
                        <span><?= htmlspecialchars($row['association_name']) ?></span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <span><?= htmlspecialchars($row['municipality']) ?></span>
                    </div>
                    <?php if (!empty($row['description'])): ?>
                        <p class="card-description"><?= htmlspecialchars($row['description']) ?></p>
                    <?php endif; ?>
                    <button type="button"
                            class="btn-book"
                            onclick="openBookingModal(<?= $row['id'] ?>,'<?= addslashes($row['machine_name']) ?>','<?= addslashes($row['association_name']) ?>','<?= addslashes($row['municipality']) ?>',<?= $row['price_per_hectare'] ?>)"
                            <?= !$has_lots ? 'disabled' : '' ?>>
                        <i class="fas fa-calendar-check"></i>
                        <?= $has_lots ? 'Book Now' : 'Add Lot First' ?>
                    </button>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-search"></i>
                <h3>No Tractors Found</h3>
                <p>No active tractors match your search.</p>
            </div>
        <?php endif; ?>
    </div>

</div>
</div><!-- /.main-scroll-container -->

<!-- ══════════════════════════════════
     BOOKING MODAL
══════════════════════════════════ -->
<div id="bookingModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-calendar-check"></i> Book Tractor</h3>
            <span class="close" onclick="closeModal()">&times;</span>
        </div>
        <form id="bookingForm" onsubmit="return false;">
            <div class="modal-body">
                <input type="hidden" name="machine_id" id="modal_machine_id">

                <div class="machine-info-modal">
                    <h4 id="modal_machine_name"></h4>
                    <div class="info-item"><i class="fas fa-users"></i><span id="modal_association_name"></span></div>
                    <div class="info-item"><i class="fas fa-map-marker-alt"></i><span id="modal_municipality"></span></div>
                    <div class="info-item"><i class="fas fa-peso-sign"></i><span><strong id="modal_price"></strong> per hectare</span></div>
                </div>

                <div class="form-group">
                    <label>Select Your Farm Lot <span class="required">*</span></label>
                    <select name="lot_id" id="modal_lot_id" required>
                        <option value="">-- Select a lot --</option>
                        <?php foreach ($lots_array as $lot): ?>
                            <option value="<?= $lot['id'] ?>"
                                    data-location="<?= htmlspecialchars($lot['farm_location']) ?>"
                                    data-size="<?= $lot['farm_size'] ?>">
                                Lot #<?= htmlspecialchars($lot['lot_number']) ?> (<?= number_format($lot['farm_size'],2) ?> ha)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="lot-info" id="modal_lot_info" style="display:none;">
                        <div><strong>Location:</strong> <span id="modal_lot_location"></span></div>
                        <div><strong>Size:</strong> <span id="modal_lot_size"></span> ha</div>
                        <div><strong>Estimated Cost:</strong> ₱<span id="modal_lot_cost"></span></div>
                    </div>
                </div>

                <!-- ── Flatpickr date picker — MM/DD/YYYY display, YYYY-MM-DD submitted ── -->
                <div class="form-group">
                    <label>Booking Date <span class="required">*</span></label>
                    <div class="date-input-wrapper">
                        <input type="text"
                               id="booking_date_display"
                               placeholder="mm/dd/yyyy"
                               readonly>
                        <!-- Hidden field holds YYYY-MM-DD for PHP -->
                        <input type="hidden" name="booking_date" id="booking_date">
                        <i class="fas fa-calendar-alt date-cal-icon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label>Notes (Optional)</label>
                    <textarea name="notes" id="notes" placeholder="Any special instructions..."></textarea>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-info-circle"></i> Booking Guidelines</label>
                    <div class="guidelines-box">
                        <div><strong>Preparation:</strong> Ensure your farm lot is accessible on the scheduled date.</div>
                        <div><strong>Cancellation:</strong> Cancel up to 24 hours before without penalty.</div>
                        <div><strong>Payment:</strong> Collected after service based on actual hectares.</div>
                        <div><strong>Confirmation:</strong> Association contacts you 24 hours before your booking.</div>
                    </div>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-shield-alt"></i> Service Agreement</label>
                    <div class="terms-box">
                        <div>• Operator arrives within 2 hours of scheduled time.</div>
                        <div>• Service quality guaranteed by the association.</div>
                        <div>• Equipment damage assessed and resolved fairly.</div>
                        <div>• By confirming, you agree to these terms.</div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                <button type="button" class="btn-submit" onclick="showConfirmation()">
                    <i class="fas fa-check"></i> Confirm Booking
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Confirmation modal -->
<div id="confirmModal" class="confirm-modal">
    <div class="confirm-content">
        <div class="confirm-header">
            <i class="fas fa-question-circle"></i>
            <h3>Confirm Your Booking</h3>
        </div>
        <div class="confirm-body">
            <p><strong>Are you sure you want to proceed?</strong></p>
            <p>Please review all details before confirming.</p>
        </div>
        <div class="confirm-footer">
            <button class="btn-no"  onclick="closeConfirmation()">No, Go Back</button>
            <button class="btn-yes" onclick="submitBooking()">Yes, Confirm</button>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.js"></script>
<script>
/* ── Flatpickr — MM/DD/YYYY display, YYYY-MM-DD into hidden field ── */
flatpickr('#booking_date_display', {
    dateFormat: 'm/d/Y',        /* display: MM/DD/YYYY */
    minDate:    'today',
    allowInput: false,
    onChange: function(selectedDates) {
        if (selectedDates.length) {
            const d = selectedDates[0];
            document.getElementById('booking_date').value =
                d.getFullYear() + '-' +
                String(d.getMonth() + 1).padStart(2, '0') + '-' +
                String(d.getDate()).padStart(2, '0');
        } else {
            document.getElementById('booking_date').value = '';
        }
    }
});

/* ── Filter — toggle search input visibility ── */
const placeholders = {
    association:  'Search by association name...',
    machine:      'Search by machine name...',
    municipality: 'Search by municipality...',
};

function handleFilterChange() {
    const f       = document.getElementById('filterSelect').value;
    const wrapper = document.getElementById('searchInputWrapper');
    const btn     = document.getElementById('searchBtn');
    const input   = document.getElementById('searchInput');

    input.value = '';
    document.getElementById('field_changed').value = '1';

    if (f === 'all') {
        wrapper.style.display = 'none';
        btn.style.display     = 'none';
        document.getElementById('searchForm').submit();
    } else {
        wrapper.style.display = '';
        btn.style.display     = '';
        input.placeholder     = placeholders[f] || 'Search...';
        document.getElementById('searchForm').submit();
    }
}

/* ── Booking modal ── */
let currentPricePerHa = 0;

function openBookingModal(machineId, machineName, association, municipality, pricePerHa) {
    currentPricePerHa = pricePerHa;
    document.getElementById('modal_machine_id').value             = machineId;
    document.getElementById('modal_machine_name').textContent     = machineName;
    document.getElementById('modal_association_name').textContent = association;
    document.getElementById('modal_municipality').textContent     = municipality;
    document.getElementById('modal_price').textContent =
        '₱' + parseFloat(pricePerHa).toLocaleString('en-PH',{minimumFractionDigits:2});

    document.getElementById('modal_lot_id').value           = '';
    document.getElementById('booking_date_display').value   = '';
    document.getElementById('booking_date').value           = '';
    document.getElementById('notes').value                  = '';
    document.getElementById('modal_lot_info').style.display = 'none';
    document.getElementById('bookingModal').style.display   = 'block';
}

function closeModal() { document.getElementById('bookingModal').style.display = 'none'; }

function showConfirmation() {
    if (!document.getElementById('modal_lot_id').value)  { alert('Please select a farm lot');  return; }
    if (!document.getElementById('booking_date').value)  { alert('Please select a booking date'); return; }
    document.getElementById('confirmModal').style.display = 'block';
}
function closeConfirmation() { document.getElementById('confirmModal').style.display = 'none'; }

function submitBooking() {
    const form = document.createElement('form');
    form.method = 'POST'; form.action = '';
    [
        { name:'machine_id',   value: document.getElementById('modal_machine_id').value },
        { name:'lot_id',       value: document.getElementById('modal_lot_id').value },
        { name:'booking_date', value: document.getElementById('booking_date').value },
        { name:'notes',        value: document.getElementById('notes').value },
    ].forEach(f => {
        const inp = document.createElement('input');
        inp.type='hidden'; inp.name=f.name; inp.value=f.value;
        form.appendChild(inp);
    });
    document.body.appendChild(form);
    form.submit();
}

/* Close on backdrop */
window.onclick = function(e) {
    if (e.target === document.getElementById('bookingModal'))  closeModal();
    if (e.target === document.getElementById('confirmModal'))  closeConfirmation();
};

/* Lot info on select */
document.getElementById('modal_lot_id').addEventListener('change', function() {
    const sel     = this.options[this.selectedIndex];
    const infoDiv = document.getElementById('modal_lot_info');
    if (this.value) {
        const size = parseFloat(sel.getAttribute('data-size'));
        document.getElementById('modal_lot_location').textContent = sel.getAttribute('data-location');
        document.getElementById('modal_lot_size').textContent     = size.toFixed(2);
        document.getElementById('modal_lot_cost').textContent     =
            (size * currentPricePerHa).toLocaleString('en-PH',{minimumFractionDigits:2});
        infoDiv.style.display = 'block';
    } else {
        infoDiv.style.display = 'none';
    }
});
</script>

</body>
</html>