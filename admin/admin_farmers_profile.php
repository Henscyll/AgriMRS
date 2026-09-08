<?php
include('dashboard_itadmin.php');
require_once '../includes/config.php';

$id = $_GET['id'] ?? 0;

$sql = "SELECT f.*, a.name AS association_name FROM farmers f LEFT JOIN associations a ON f.association_id = a.id WHERE f.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$farmer = $stmt->get_result()->fetch_assoc();

$lots_sql = "SELECT fl.*, a.name AS association_name
             FROM farmer_lots fl
             LEFT JOIN farmers f2 ON fl.farmer_id = f2.id
             LEFT JOIN associations a ON f2.association_id = a.id
             WHERE fl.farmer_id = ?
             ORDER BY fl.created_at DESC";
$lots_stmt = $conn->prepare($lots_sql);
$lots_stmt->bind_param("i", $id);
$lots_stmt->execute();
$lots = $lots_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$bookings_sql = "SELECT b.*,
                        m.machine_name, m.type AS machine_type, m.price_per_hectare,
                        fl.lot_number, fl.farm_location,
                        fl.province AS lot_province, fl.municipality AS lot_municipality, fl.barangay AS lot_barangay,
                        COALESCE(fl.farm_size, b.farm_size) AS farm_size,
                        a.name AS association_name
                 FROM bookings b
                 LEFT JOIN machines m ON b.machine_id = m.id
                 LEFT JOIN farmer_lots fl ON b.lot_id = fl.id
                 LEFT JOIN associations a ON m.association_id = a.id
                 WHERE b.farmer_id = ?
                 ORDER BY b.created_at DESC";
$bookings_stmt = $conn->prepare($bookings_sql);
$bookings_stmt->bind_param("i", $id);
$bookings_stmt->execute();
$bookings = $bookings_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Compute a display total per booking (uses the discounted total when one was recorded)
foreach ($bookings as &$b) {
    $farmSize = (float)($b['farm_size'] ?? 0);
    $rate     = (float)($b['price_per_hectare'] ?? 0);
    $b['total_amount'] = (!empty($b['discounted_total']))
        ? (float)$b['discounted_total']
        : $farmSize * $rate;
}
unset($b);

// Transaction History = only bookings that have actually been completed / paid
$transactions = array_values(array_filter($bookings, fn($b) => strtolower($b['status']) === 'completed'));

$total_lots         = count($lots);
$active_lots         = count(array_filter($lots, fn($l) => $l['status'] === 'Active'));
$total_farm_size     = array_sum(array_column($lots, 'farm_size'));
$total_bookings      = count($bookings);
$total_transactions  = count($transactions);

if (!empty($farmer['first_name'])) {
    $mi        = !empty($farmer['middle_name']) ? ' ' . strtoupper(substr($farmer['middle_name'],0,1)) . '.' : '';
    $full_name = $farmer['first_name'] . $mi . ' ' . $farmer['last_name'];
} else {
    $full_name = $farmer['name'] ?? 'Unknown';
}

$farmer_address = trim(implode(', ', array_filter([
    $farmer['province'] ?? '', $farmer['municipality'] ?? '', $farmer['barangay'] ?? ''
])));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Farmer Profile – <?= htmlspecialchars($full_name) ?></title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'Segoe UI', system-ui, sans-serif; overflow:hidden; }

/* ── Scrollable wrapper (below fixed header) ── */
.content-wrapper {
    position: absolute;
    top: 120px; left: 0; right: 0; bottom: 0;
    overflow-y: auto;
    padding: 18px 20px 40px;
}

.usernames { color: #000000; font-size:15px; margin-bottom:10px; }
.usernames a { color: #000000; margin-left:8px; text-decoration:underline; }

/* ── Top bar ── */
.top-bar {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 18px;
    flex-wrap: wrap;
}
.btn-back {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    background: rgba(255,255,255,0.92);
    color: #2d7d46;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.84rem;
    text-decoration: none;
    border: 1px solid rgba(255,255,255,0.5);
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    transition: all 0.2s;
    white-space: nowrap;
}
.btn-back:hover { background:#2d7d46; color:#fff; transform:translateX(-3px); }

.page-title {
    color: #131111;
    font-size: 1.15rem;
    font-weight: 700;
    text-shadow: 0 1px 4px rgba(0,0,0,0.35);
    flex: 1;
}
.status-pill {
    display: inline-block;
    padding: 3px 12px;
    border-radius: 20px;
    font-size: 0.74rem;
    font-weight: 700;
    vertical-align: middle;
    margin-left: 8px;
}
.status-pill.active   { background:#22c55e; color:#fff; }
.status-pill.inactive { background:#ef4444; color:#fff; }

/* ── Main layout ── */
.main-wrap { max-width: 920px; margin: 0 auto; padding: 50px 0px;  }

/* ── Details card ── */
.details-card {
    background: rgba(255,255,255,0.94);
    backdrop-filter: blur(10px);
    border-radius: 14px;
    box-shadow: 0 3px 20px rgba(0,0,0,0.10);
    overflow: hidden;
    margin-bottom: 18px;
}
.card-head {
    background: linear-gradient(90deg,rgba(45,125,70,0.12),transparent);
    border-bottom: 1px solid rgba(45,125,70,0.13);
    padding: 11px 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.card-head-title {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: #2d7d46;
    display: flex;
    align-items: center;
    gap: 7px;
}
.card-body { padding: 16px 18px; }

.fields-grid { display:grid; gap:8px 12px; margin-bottom:8px; }
.fg-4 { grid-template-columns: repeat(4,1fr); }
.fg-3 { grid-template-columns: repeat(3,1fr); }

.field-cell {
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: 7px 10px;
    background: rgba(45,125,70,0.04);
    border-radius: 8px;
    border: 1px solid rgba(45,125,70,0.09);
}
.field-lbl {
    font-size: 0.65rem;
    color: #888;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.field-val {
    font-size: 0.81rem;
    color: #1a1a1a;
    font-weight: 600;
    word-break: break-word;
}

.sec-divider {
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: #2d7d46;
    margin: 12px 0 8px;
    display: flex;
    align-items: center;
    gap: 8px;
    opacity: 0.8;
}
.sec-divider::after { content:''; flex:1; height:1px; background:#d4edda; }

.assoc-pill {
    display: inline-block;
    background: #e8f5e9;
    color: #1b5e20;
    border-radius: 12px;
    padding: 3px 10px;
    font-size: 0.78rem;
    font-weight: 600;
}

/* ── Action buttons ── */
.action-btns {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}
.action-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 11px 22px;
    background: linear-gradient(135deg,#1e6b35,#2d9148);
    color: #fff;
    border: none;
    border-radius: 9px;
    font-size: 0.9rem;
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(45,145,72,0.28);
    transition: transform 0.18s, box-shadow 0.18s;
    font-family: inherit;
}
.action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 16px rgba(45,145,72,0.38);
}
.btn-badge {
    background: rgba(255,255,255,0.22);
    border-radius: 20px;
    padding: 1px 8px;
    font-size: 0.75rem;
    font-weight: 700;
}

/* ══════════ MODALS ══════════ */
@keyframes modalPop {
    from { opacity:0; transform:scale(0.95) translateY(16px); }
    to   { opacity:1; transform:scale(1)    translateY(0); }
}
.modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.50);
    backdrop-filter: blur(5px);
    z-index: 9999;
    justify-content: center;
    align-items: center;
    padding: 16px;
}
.modal-overlay.open { display:flex; }
.modal-box {
    background: #fff;
    border-radius: 16px;
    width: 100%;
    display: flex;
    flex-direction: column;
    box-shadow: 0 20px 60px rgba(0,0,0,0.24);
    animation: modalPop 0.25s cubic-bezier(.34,1.3,.64,1) both;
    overflow: hidden;
}
.modal-hd {
    background: linear-gradient(135deg,#1a5c2e,#2d7d46);
    padding: 16px 22px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
}
.modal-hd h3 { color:#fff; font-size:0.98rem; font-weight:700; display:flex; align-items:center; gap:8px; }
.modal-close {
    background: rgba(255,255,255,0.15);
    border: none;
    color: #fff;
    width: 30px; height: 30px;
    border-radius: 50%;
    cursor: pointer;
    font-size: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.2s, transform 0.2s;
    flex-shrink: 0;
}
.modal-close:hover { background:rgba(255,255,255,0.28); transform:rotate(90deg); }
.modal-ft {
    padding: 12px 22px;
    border-top: 1px solid #f0f0f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-shrink: 0;
    background: #f9fafb;
}
/* ── Tables inside modals ── */
.m-table { width:100%; border-collapse:collapse; font-size:0.81rem; }
.m-table thead th {
    background: #f0fdf4;
    color: #2d7d46;
    font-weight: 700;
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 10px 12px;
    border-bottom: 2px solid #d4edda;
    text-align: left;
    white-space: nowrap;
    position: sticky;
    top: 0;
    z-index: 3;
}
.m-table td { padding:10px 12px; color:#333; border-bottom:1px solid #f0f0f0; vertical-align:middle; }
.m-table tbody tr:last-child td { border-bottom:none; }
.m-table tbody tr { transition:background 0.15s; cursor:pointer; }
.m-table tbody tr:hover { background:#f9fafb; }

/* status badges */
.s-badge { display:inline-block; padding:3px 10px; border-radius:12px; font-size:0.71rem; font-weight:700; white-space:nowrap; }
.s-badge.completed { background:#dcfce7; color:#15803d; }
.s-badge.pending   { background:#fef9c3; color:#854d0e; }
.s-badge.approved  { background:#dbeafe; color:#1d4ed8; }
.s-badge.declined  { background:#fee2e2; color:#dc2626; }
.s-badge.active    { background:#dcfce7; color:#15803d; }
.s-badge.inactive  { background:#f3f4f6; color:#6b7280; }

/* lot cards inside modal */
.lot-card {
    background: #f9fafb;
    border: 1.5px solid #e5e7eb;
    border-radius: 10px;
    padding: 14px 16px;
    margin-bottom: 10px;
}
.lot-card:last-child { margin-bottom:0; }
.lot-card-top { display:flex; align-items:center; justify-content:space-between; margin-bottom:10px; }
.lot-num { font-weight:800; color:#1e6b35; font-size:0.95rem; }
.lot-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:8px 14px; }
.lot-field label { font-size:0.62rem; font-weight:700; text-transform:uppercase; letter-spacing:0.07em; color:#9ca3af; display:block; margin-bottom:2px; }
.lot-field .lv { font-size:0.83rem; color:#111827; font-weight:500; }

.dblclick-hint { font-size:0.7rem; color:#aaa; text-align:center; padding:6px; font-style:italic; }
.empty-state { text-align:center; padding:36px 16px; color:#bbb; font-size:0.84rem; }

/* detail cell used in booking detail */
.d-cell {
    background: #f9fafb;
    border-radius: 8px;
    padding: 10px 13px;
    border: 1px solid #e5e7eb;
}
.d-cell-lbl { font-size:10px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:.4px; margin-bottom:3px; }
.d-cell-val { font-size:13px; font-weight:600; color:#1f2937; }
.d-cell.hl  { background:#f0fdf4; border-color:#bbf7d0; }
.d-cell.hl .d-cell-val { font-size:15px; font-weight:800; color:#16a34a; }

/* not found */
.not-found {
    text-align:center;
    padding: 60px 20px;
    background: rgba(255,255,255,0.92);
    border-radius: 16px;
    max-width: 440px;
    margin: 50px auto;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
}

@media (max-width:640px) {
    .fg-4, .fg-3 { grid-template-columns:1fr 1fr; }
    .lot-grid { grid-template-columns:1fr 1fr; }
    .action-btns { flex-direction:column; }
    .action-btn { width:100%; justify-content:center; }
}
</style>
</head>
<body>
<div class="content-wrapper">

  <div class="main-wrap">

  <?php if ($farmer): ?>

    <!-- Top bar -->
    <div class="top-bar">
      <a href="admin_farmers.php" class="btn-back">← Back to Farmers List</a>
      <div class="page-title">
        <?= htmlspecialchars($full_name) ?>
        <span class="status-pill <?= strtolower($farmer['status']) === 'active' ? 'active' : 'inactive' ?>">
          <?= htmlspecialchars($farmer['status']) ?>
        </span>
      </div>
    </div>

    <!-- ── Farmer Details Card ── -->
    <div class="details-card">
      <div class="card-head">
        <span class="card-head-title">Farmer Details</span>
        <span style="font-size:0.72rem;color:#888;">Date Registered: <?= !empty($farmer['created_at']) ? date('m/d/Y', strtotime($farmer['created_at'])) : '—' ?></span>
      </div>
      <div class="card-body">

        <div class="fields-grid fg-4">
          <div class="field-cell"><span class="field-lbl">First Name</span><span class="field-val"><?= htmlspecialchars($farmer['first_name'] ?? '—') ?></span></div>
          <div class="field-cell"><span class="field-lbl">Middle Name</span><span class="field-val"><?= htmlspecialchars($farmer['middle_name'] ?: '—') ?></span></div>
          <div class="field-cell"><span class="field-lbl">Last Name</span><span class="field-val"><?= htmlspecialchars($farmer['last_name'] ?? '—') ?></span></div>
          <div class="field-cell"><span class="field-lbl">Sex</span><span class="field-val"><?= htmlspecialchars($farmer['sex'] ?? '—') ?></span></div>
        </div>

        <div class="fields-grid fg-4">
          <div class="field-cell"><span class="field-lbl">Date of Birth</span><span class="field-val"><?= !empty($farmer['date_of_birth']) ? date('m/d/Y', strtotime($farmer['date_of_birth'])) : '—' ?></span></div>
          <div class="field-cell"><span class="field-lbl">Age</span><span class="field-val"><?= !empty($farmer['age']) ? $farmer['age'].' yrs' : '—' ?></span></div>
          <div class="field-cell"><span class="field-lbl">Email</span><span class="field-val" style="word-break:break-all;"><?= htmlspecialchars($farmer['email'] ?? '—') ?></span></div>
          <div class="field-cell"><span class="field-lbl">Contact No.</span><span class="field-val"><?= htmlspecialchars($farmer['phone'] ?? '—') ?></span></div>
        </div>

        <div class="sec-divider">Location</div>
        <div class="fields-grid fg-3">
          <div class="field-cell"><span class="field-lbl">Province</span><span class="field-val"><?= htmlspecialchars($farmer['province'] ?? '—') ?></span></div>
          <div class="field-cell"><span class="field-lbl">Municipality</span><span class="field-val"><?= htmlspecialchars($farmer['municipality'] ?? '—') ?></span></div>
          <div class="field-cell"><span class="field-lbl">Barangay</span><span class="field-val"><?= htmlspecialchars($farmer['barangay'] ?? '—') ?></span></div>
        </div>

        <?php if (!empty($farmer['association_name'])): ?>
        <div class="sec-divider">Association</div>
        <div style="padding:4px 0;">
          <span class="assoc-pill"><?= htmlspecialchars($farmer['association_name']) ?></span>
          <span style="font-size:0.76rem;color:#6b7280;margin-left:8px;">Member receives 5% off on association machines</span>
        </div>
        <?php endif; ?>

        <!-- Action buttons -->
        <div style="margin-top:18px;padding-top:14px;border-top:1px solid #f0f0f0;">
          <div class="action-btns">
            <button class="action-btn" onclick="openModal('lotsModal')">
              Farm Lots
            </button>
            <button class="action-btn" onclick="openModal('reservationModal')">
              Reservation History
            </button>
          </div>
        </div>

      </div>
    </div>

  <?php else: ?>
    <div class="not-found">
      <h3 style="margin:10px 0 6px;">Farmer Not Found</h3>
      <p style="color:#888;font-size:0.9rem;">The farmer profile you're looking for doesn't exist.</p>
      <a href="staff_farmers.php" style="display:inline-flex;margin-top:14px;padding:9px 20px;background:#2d7d46;color:#fff;border-radius:8px;text-decoration:none;font-weight:700;">← Back to Farmers</a>
    </div>
  <?php endif; ?>

  </div><!-- /main-wrap -->
</div><!-- /content-wrapper -->

<?php if ($farmer): ?>

<!-- ══════════════════════════════════════
     FARM LOTS MODAL
══════════════════════════════════════ -->
<div id="lotsModal" class="modal-overlay">
  <div class="modal-box" style="max-width:900px; max-height:88vh;">
    <div class="modal-hd">
      <h3>Farm Lots — <?= htmlspecialchars($full_name) ?></h3>
      <button class="modal-close" onclick="closeModal('lotsModal')">&times;</button>
    </div>
    <div style="overflow-y:auto; flex:1;">
      <?php if (empty($lots)): ?>
        <div class="empty-state" style="padding:48px 16px;"><p>No farm lots registered yet.</p></div>
      <?php else: ?>
      <table class="m-table">
        <thead>
          <tr>
            <th>Lot Number</th>
            <th>Farm Size</th>
            <th>Barangay</th>
            <th>Municipality</th>
            <th>Province</th>
            <th>Association</th>
            <th>Date Added</th>
            <th style="text-align:center;">Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($lots as $i => $lot): ?>
          <tr>
            <td style="font-weight:700;color:#1e6b35;"><?= htmlspecialchars($lot['lot_number']) ?></td>
            <td style="font-weight:600;"><?= number_format($lot['farm_size'],2) ?> ha</td>
            <td><?= htmlspecialchars($lot['barangay'] ?? '—') ?></td>
            <td><?= htmlspecialchars($lot['municipality'] ?? '—') ?></td>
            <td><?= htmlspecialchars($lot['province'] ?? '—') ?></td>
            <td>
              <?php if (!empty($lot['association_name'])): ?>
                <span style="display:inline-block;background:#e8f5e9;color:#1b5e20;border-radius:10px;padding:2px 9px;font-size:0.72rem;font-weight:700;">
                  <?= htmlspecialchars($lot['association_name']) ?>
                </span>
              <?php else: ?>
                <span style="color:#9ca3af;font-size:0.78rem;">None</span>
              <?php endif; ?>
            </td>
            <td style="white-space:nowrap;"><?= !empty($lot['created_at']) ? date('m/d/Y', strtotime($lot['created_at'])) : '—' ?></td>
            <td style="text-align:center;">
              <span class="s-badge <?= strtolower($lot['status']) === 'active' ? 'active' : 'inactive' ?>">
                <?= htmlspecialchars($lot['status']) ?>
              </span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
    <div class="modal-ft">
      <span style="font-size:0.8rem;color:#888;"><?= $total_lots ?> lot(s)</span>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════
     RESERVATION HISTORY MODAL
══════════════════════════════════════ -->
<div id="reservationModal" class="modal-overlay">
  <div class="modal-box" style="max-width:1100px; max-height:88vh;">
    <div class="modal-hd">
      <h3>Reservation History — <?= htmlspecialchars($full_name) ?></h3>
      <button class="modal-close" onclick="closeModal('reservationModal')">&times;</button>
    </div>
    <div style="overflow-y:auto; flex:1;">
      <?php if (empty($bookings)): ?>
        <div class="empty-state" style="padding:48px 16px;"><p>No reservations yet.</p></div>
      <?php else: ?>
      <div style="overflow-x:auto;">
      <table class="m-table">
        <thead>
          <tr>
            <th>Reservation Date</th>
            <th>Farmer Name</th>
            <th>Farm Lot</th>
            <th>Farm Size</th>
            <th>Machine Type</th>
            <th>Machine Name</th>
            <th>Total Amount</th>
            <th>Schedule</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($bookings as $b):
            $farm_location = trim(implode(', ', array_filter([
                $b['lot_province'] ?? '', $b['lot_municipality'] ?? '', $b['lot_barangay'] ?? ''
            ])));
          ?>
          <tr ondblclick='openBookingDetail(<?= json_encode($b) ?>)' title="Double-click to view details">
            <td style="white-space:nowrap;"><?= !empty($b['created_at']) ? date('m/d/Y', strtotime($b['created_at'])) : '—' ?></td>
            <td style="font-weight:600;white-space:nowrap;"><?= htmlspecialchars($full_name) ?></td>
            <td style="font-weight:700;color:#1e6b35;"><?= htmlspecialchars($b['lot_number'] ?? '—') ?></td>
            <td><?= isset($b['farm_size']) ? number_format($b['farm_size'],2).'/Ha' : '—' ?></td>
            <td style="font-size:0.83rem;color:#555;"><?= htmlspecialchars($b['machine_type'] ?? '—') ?></td>
            <td style="font-weight:600;font-size:0.83rem;"><?= htmlspecialchars($b['machine_name'] ?? 'N/A') ?></td>
            <td style="font-weight:700;"><?= '₱'.number_format($b['total_amount'],2) ?></td>
            <td style="white-space:nowrap;"><?= !empty($b['booking_date']) ? date('m/d/Y', strtotime($b['booking_date'])) : '—' ?></td>
            <td><span class="s-badge <?= strtolower($b['status']) ?>"><?= htmlspecialchars($b['status']) ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
      <!-- <div class="dblclick-hint">Double-click a row to view details</div> -->
      <?php endif; ?>
    </div>
    <div class="modal-ft">
      <span style="font-size:0.8rem;color:#888;"><?= $total_bookings ?> reservation<?= $total_bookings !== 1 ? 's' : '' ?> total</span>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════
     RESERVATION DETAIL MODAL
══════════════════════════════════════ -->

<div id="bookingDetailModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:9999; justify-content:center; align-items:center; backdrop-filter:blur(3px);">
  <div style="background:white; border-radius:14px; width:750px; max-width:96%; max-height:90vh; overflow-y:auto; box-shadow:0 20px 40px rgba(0,0,0,0.25);">
    <div style="background:linear-gradient(135deg,#2d7a2d,#1a5c1a); padding:16px 24px; border-radius:14px 14px 0 0; display:flex; justify-content:space-between; align-items:center; position:sticky; top:0; z-index:10;">
      <div>
        <div style="color:white; font-size:17px; font-weight:700;" id="modalTitle">Reservation Details</div>
        <!-- <div style="color:rgba(255,255,255,0.8); font-size:12px; margin-top:2px;" id="modalSubtitle">Loading...</div> -->
      </div>
      <div style="display:flex; gap:10px; align-items:center;">
        <button onclick="closeModal('bookingDetailModal')" style="width:32px; height:32px; background:rgba(255,255,255,0.2); border:none; border-radius:50%; color:white; font-size:18px; cursor:pointer; display:flex; align-items:center; justify-content:center;">&times;</button>
      </div>
    </div>
    <div id="modalBody" style="padding:18px 20px 20px;">
      <div style="text-align:center; padding:40px; color:#6b7280;"><div class="spinner"></div>Loading...</div>
    </div>
  </div>
</div>

<script>
/* ── Modal open/close ── */
function openModal(id) {
    const el = document.getElementById(id);
    if (el) {
        el.classList.add('open');
        el.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(id) {
    const el = document.getElementById(id);
    if (el) {
        el.classList.remove('open');
        el.style.display = 'none';
        
        // Only restore scroll if no other modal is open
        const anyOpen = document.querySelectorAll('.modal-overlay.open, #bookingDetailModal[style*="display: flex"]').length > 0;
        if (!anyOpen) document.body.style.overflow = '';
    }
}

// Close on backdrop click
document.querySelectorAll('.modal-overlay, #bookingDetailModal').forEach(el => {
    el.addEventListener('click', function(e) {
        if (e.target === this) closeModal(this.id);
    });
});

// Escape key
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        closeModal('lotsModal');
        closeModal('reservationModal');
        closeModal('bookingDetailModal');
    }
});

/* ── Helpers ── */
function esc(str) {
    if (str === null || str === undefined) return '—';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function fmtDate(d) {
    if (!d) return '—';
    const dt = new Date(d);
    if (isNaN(dt)) return esc(d);
    return (dt.getMonth()+1).toString().padStart(2,'0') + '/' +
           dt.getDate().toString().padStart(2,'0') + '/' +
           dt.getFullYear();
}

function fmtMoney(n) {
    return parseFloat(n || 0).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
}

function iBox(label, value) {
    return `<div class="d-cell">
        <div class="d-cell-lbl">${esc(label)}</div>
        <div class="d-cell-val">${esc(value)}</div>
    </div>`;
}

/* ── Booking Detail Modal Renderer ── */
function openBookingDetail(b) {
    if (!b) return;

    const farmSize   = parseFloat(b.farm_size) || 0;
    const rate       = parseFloat(b.price_per_hectare) || 0;
    const total      = parseFloat(b.total_amount) || (farmSize * rate);
    
    const farmerName = <?= json_encode($full_name) ?>;
    const farmerAddr = <?= json_encode($farmer_address) ?>;
    const farmerPhone = <?= json_encode($farmer['phone'] ?? '—') ?>;
    const farmerEmail = <?= json_encode($farmer['email'] ?? '—') ?>;

    const farmLoc = [b.lot_province, b.lot_municipality, b.lot_barangay].filter(Boolean).join(', ') || '—';
    const specificLoc = b.farm_location || '—';

    /* document.getElementById('modalTitle').textContent = 'Reservation #' + String(b.id || b.booking_id || '0').padStart(5, '0');
    document.getElementById('modalSubtitle').textContent = 'Applied: ' + fmtDate(b.created_at) + '  |  Schedule: ' + fmtDate(b.booking_date); */

    document.getElementById('modalBody').innerHTML = `
        <div style="display:flex; justify-content:flex-end; margin-bottom:12px;">
            <span class="s-badge ${esc((b.status || '').toLowerCase())}">${esc(b.status || 'Pending')}</span>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:8px;">
            ${iBox('Date Applied', fmtDate(b.created_at))}
            ${iBox('Schedule', fmtDate(b.booking_date))}
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px; margin-bottom:8px;">
            ${iBox('Farmer Name', farmerName)}
            ${iBox('Machine Type', b.machine_type || '—')}
            ${iBox('Machine Name', b.machine_name || '—')}
        </div>

        <div style="display:grid; grid-template-columns:2fr 1fr 1fr; gap:8px; margin-bottom:8px;">
            ${iBox('Farmer Address', farmerAddr || '—')}
            ${iBox('Contact No.', farmerPhone)}
            ${iBox('Email', farmerEmail)}
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr 1fr 1fr 1fr; gap:8px; margin-bottom:8px;">
            ${iBox('Farm Lot', b.lot_number || '—')}
            ${iBox('Farm Size', farmSize.toFixed(2) + ' ha')}
            ${iBox('Price / ha', '₱' + fmtMoney(rate))}
            <div class="d-cell hl">
                <div class="d-cell-lbl">Total Amount</div>
                <div class="d-cell-val">₱${fmtMoney(total)}</div>
            </div>
            ${iBox('Association', b.association_name || 'N/A')}
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:8px;">
            ${iBox('Farm Location', farmLoc)}
            ${iBox('Specific Location', specificLoc)}
        </div>

        ${b.notes ? `
        <div style="margin-top:8px; background:#fffbeb; border:1px solid #fde68a; border-radius:8px; padding:10px 13px; font-size:12px; color:#92400e;">
            <span style="font-weight:700; font-size:10px; text-transform:uppercase;">Notes:&nbsp;</span>${esc(b.notes)}
        </div>` : ''}
    `;

    openModal('bookingDetailModal');
}
</script>

<?php endif; ?>
</body>
</html>