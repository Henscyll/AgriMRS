<?php
include('dashboard_itadmin.php');
require_once '../includes/config.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    echo "<script>alert('Invalid machine ID.'); window.location.href='staff_machines.php';</script>";
    exit();
}

// Fetch machine + association + president details
$sql = "SELECT m.*, 
               a.name           AS association_name, 
               a.province       AS assoc_province,
               a.municipality   AS assoc_municipality, 
               a.barangay       AS assoc_barangay,
               a.email          AS assoc_email,
               a.phone          AS assoc_phone,
               p.first_name     AS president_first_name,
               p.middle_name    AS president_middle_name,
               p.last_name      AS president_last_name,
               p.phone          AS president_phone,
               p.email          AS president_email
        FROM machines m 
        LEFT JOIN associations a ON m.association_id = a.id 
        LEFT JOIN presidents   p ON a.president_id   = p.id
        WHERE m.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$machine = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch assigned operator
$op_sql = "SELECT o.name, o.email, o.phone, o.license_number, o.status, mo.assigned_at
           FROM machine_operators mo
           JOIN operators o ON mo.operator_id = o.id
           WHERE mo.machine_id = ? AND mo.status = 'Active'
           LIMIT 1";
$op_stmt = $conn->prepare($op_sql);
$op_stmt->bind_param("i", $id);
$op_stmt->execute();
$operator = $op_stmt->get_result()->fetch_assoc();
$op_stmt->close();

// Fetch booking history for this machine matching farmer profile data fields
$bookings_sql = "SELECT b.*, 
                        f.first_name, f.middle_name, f.last_name, f.phone AS farmer_phone, f.email AS farmer_email,
                        f.province AS farmer_province, f.municipality AS farmer_municipality, f.barangay AS farmer_barangay,
                        m.machine_name, m.type AS machine_type, m.price_per_hectare,
                        fl.lot_number, fl.farm_location,
                        fl.province AS lot_province, fl.municipality AS lot_municipality, fl.barangay AS lot_barangay,
                        COALESCE(fl.farm_size, b.farm_size) AS farm_size,
                        a.name AS association_name
                 FROM bookings b
                 LEFT JOIN farmers f  ON b.farmer_id = f.id
                 LEFT JOIN machines m ON b.machine_id = m.id
                 LEFT JOIN farmer_lots fl ON b.lot_id = fl.id
                 LEFT JOIN associations a ON m.association_id = a.id
                 WHERE b.machine_id = ?
                 ORDER BY b.created_at DESC";
$bk_stmt = $conn->prepare($bookings_sql);
$bk_stmt->bind_param("i", $id);
$bk_stmt->execute();
$bookings = $bk_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$bk_stmt->close();

// Compute a display total per booking (uses the discounted total when recorded)
foreach ($bookings as &$b) {
    // Format full farmer name
    if (!empty($b['first_name'])) {
        $mi = !empty($b['middle_name']) ? ' ' . strtoupper(substr($b['middle_name'], 0, 1)) . '.' : '';
        $b['farmer_full_name'] = $b['first_name'] . $mi . ' ' . $b['last_name'];
    } else {
        $b['farmer_full_name'] = 'Unknown';
    }

    // Format farmer full address
    $b['farmer_address'] = trim(implode(', ', array_filter([
        $b['farmer_province'] ?? '', $b['farmer_municipality'] ?? '', $b['farmer_barangay'] ?? ''
    ])));

    $farmSize = (float)($b['farm_size'] ?? 0);
    $rate     = (float)($b['price_per_hectare'] ?? 0);
    $b['total_amount'] = (!empty($b['discounted_total']))
        ? (float)$b['discounted_total']
        : $farmSize * $rate;
}
unset($b);

$total_bookings = count($bookings);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Machine Profile<?= $machine ? ' – '.htmlspecialchars($machine['machine_name']) : '' ?></title>
  <style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'Segoe UI', system-ui, sans-serif; overflow:hidden; }

/* ── Scrollable wrapper ── */
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
    color: #2d7d46;
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
.status-pill.active      { background:#22c55e; color:#fff; }
.status-pill.inactive    { background:#ef4444; color:#fff; }
.status-pill.maintenance { background:#f59e0b; color:#fff; }

/* ── Main layout ── */
.main-wrap { max-width: 980px; margin: 0 auto; padding: 50px 0px;  }

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

/* ── Two-column layout inside card ── */
.card-inner-grid {
    display: grid;
    grid-template-columns: 200px 1fr;
    gap: 18px;
    align-items: start;
}
.machine-img-wrap {
    width: 200px;
    height: 150px;
    border-radius: 10px;
    overflow: hidden;
    border: 1.5px solid #e5e7eb;
    background: #f3f4f6;
    flex-shrink: 0;
}
.machine-img-wrap img { width:100%; height:100%; object-fit:contain; }

/* ── Field grids ── */
.fields-grid { display:grid; gap:8px 12px; margin-bottom:8px; }
.fg-4 { grid-template-columns: repeat(4,1fr); }
.fg-3 { grid-template-columns: repeat(3,1fr); }
.fg-2 { grid-template-columns: repeat(2,1fr); }

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

/* ── Section divider ── */
.sec-divider {
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: #2d7d46;
    margin: 14px 0 8px;
    display: flex;
    align-items: center;
    gap: 8px;
    opacity: 0.85;
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
    margin-top: 18px;
    padding-top: 14px;
    border-top: 1px solid #f0f0f0;
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
.action-btn:hover { transform:translateY(-2px); box-shadow:0 5px 16px rgba(45,145,72,0.38); }

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
.modal-bd { padding:20px 22px; overflow-y:auto; flex:1; }
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

.dblclick-hint { font-size:0.7rem; color:#aaa; text-align:center; padding:6px; font-style:italic; }
.empty-state { text-align:center; padding:36px 16px; color:#bbb; font-size:0.84rem; }

/* booking detail cells */
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

@media (max-width:700px) {
    .card-inner-grid { grid-template-columns: 1fr; }
    .machine-img-wrap { width:100%; height:180px; }
    .fg-4, .fg-3 { grid-template-columns: 1fr 1fr; }
    .action-btns { flex-direction:column; }
    .action-btn { width:100%; justify-content:center; }
}
  </style>
</head>
<body>
<div class="content-wrapper">

  <div class="main-wrap">

  <?php if ($machine):
    $s  = $machine['status'];
    $sc = ($s === 'Active') ? 'active' : (($s === 'Inactive') ? 'inactive' : 'maintenance');
  ?>

    <!-- Top bar -->
    <div class="top-bar">
      <a href="admin_machines.php" class="btn-back">← Back to Machines List</a>
      <div class="page-title">
        <?= htmlspecialchars($machine['machine_name']) ?>
        <span class="status-pill <?= $sc ?>"><?= htmlspecialchars($s) ?></span>
      </div>
    </div>

    <!-- ── Machine Details Card ── -->
    <div class="details-card">
      <div class="card-head">
        <span class="card-head-title">Machine Details</span>
        <span style="font-size:0.72rem;color:#888;">Registered Date: <?= !empty($machine['created_at']) ? date('m/d/Y', strtotime($machine['created_at'])) : '—' ?></span>
      </div>
      <div class="card-body">

        <div class="card-inner-grid">
          <!-- Machine image -->
          <div class="machine-img-wrap">
            <img src="<?= htmlspecialchars($machine['image_path'] ?? '') ?>"
                 alt="<?= htmlspecialchars($machine['machine_name']) ?>"
                 onerror="this.src='../assets/no-image.png'">
          </div>

          <!-- Machine fields -->
          <div>
            <div class="fields-grid fg-4">
              <div class="field-cell"><span class="field-lbl">Machine Name</span><span class="field-val"><?= htmlspecialchars($machine['machine_name']) ?></span></div>
              <div class="field-cell"><span class="field-lbl">Machine ID</span><span class="field-val"><?= htmlspecialchars($machine['id']) ?></span></div>
              <div class="field-cell"><span class="field-lbl">Type</span><span class="field-val"><?= htmlspecialchars($machine['type']) ?></span></div>
              <div class="field-cell"><span class="field-lbl">Status</span><span class="field-val"><?= htmlspecialchars($machine['status']) ?></span></div>
            </div>
            <div class="fields-grid fg-2" style="margin-top:0;">
              <div class="field-cell"><span class="field-lbl">Price Per Hectare</span><span class="field-val">₱<?= number_format($machine['price_per_hectare'], 2) ?></span></div>
              <div class="field-cell"><span class="field-lbl">Operator Rate / Ha</span><span class="field-val">₱<?= number_format($machine['operator_rate_per_hectare'] ?? 0, 2) ?></span></div>
            </div>
            <?php if (!empty($machine['description'])): ?>
            <div class="fields-grid" style="margin-top:8px; grid-template-columns:1fr;">
              <div class="field-cell"><span class="field-lbl">Description</span><span class="field-val"><?= htmlspecialchars($machine['description']) ?></span></div>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Association section -->
        <div class="sec-divider">Association</div>
        <div class="fields-grid fg-4">
          <div class="field-cell"><span class="field-lbl">Association Name</span><span class="field-val"><?= htmlspecialchars($machine['association_name'] ?? '—') ?></span></div>
          <div class="field-cell"><span class="field-lbl">Province</span><span class="field-val"><?= htmlspecialchars($machine['assoc_province'] ?? '—') ?></span></div>
          <div class="field-cell"><span class="field-lbl">Municipality</span><span class="field-val"><?= htmlspecialchars($machine['assoc_municipality'] ?? '—') ?></span></div>
          <div class="field-cell"><span class="field-lbl">Barangay</span><span class="field-val"><?= htmlspecialchars($machine['assoc_barangay'] ?? '—') ?></span></div>
        </div>
        <div class="fields-grid fg-2" style="margin-top:0;">
          <div class="field-cell"><span class="field-lbl">Assoc. Email</span><span class="field-val"><?= htmlspecialchars($machine['assoc_email'] ?? '—') ?></span></div>
          <div class="field-cell"><span class="field-lbl">Assoc. Phone</span><span class="field-val"><?= htmlspecialchars($machine['assoc_phone'] ?? '—') ?></span></div>
        </div>

        <!-- Operator section -->
        <div class="sec-divider">Assigned Operator</div>
        <?php if ($operator): ?>
        <div class="fields-grid fg-4">
          <div class="field-cell"><span class="field-lbl">Name</span><span class="field-val"><?= htmlspecialchars($operator['name']) ?></span></div>
          <div class="field-cell"><span class="field-lbl">Email</span><span class="field-val"><?= htmlspecialchars($operator['email'] ?? '—') ?></span></div>
          <div class="field-cell"><span class="field-lbl">Phone</span><span class="field-val"><?= htmlspecialchars($operator['phone']) ?></span></div>
          <div class="field-cell"><span class="field-lbl">Assigned At</span><span class="field-val"><?= date('m/d/Y', strtotime($operator['assigned_at'])) ?></span></div>
        </div>
        <?php else: ?>
        <p style="font-size:0.82rem;color:#9ca3af;padding:6px 0;">No operator currently assigned.</p>
        <?php endif; ?>

        <!-- Action buttons -->
        <div class="action-btns">
          <button class="action-btn" onclick="openModal('reservationModal')">
            Reservation History
          </button>
        </div>

      </div><!-- /card-body -->
    </div><!-- /details-card -->

  <?php else: ?>
    <div class="not-found">
      <h3 style="margin:10px 0 6px;">Machine Not Found</h3>
      <p style="color:#888;font-size:0.9rem;">This machine doesn't exist or has been removed.</p>
      <a href="staff_machines.php" style="display:inline-flex;margin-top:14px;padding:9px 20px;background:#2d7d46;color:#fff;border-radius:8px;text-decoration:none;font-weight:700;">← Back to List</a>
    </div>
  <?php endif; ?>

  </div><!-- /main-wrap -->
</div><!-- /content-wrapper -->

<?php if ($machine): ?>

<!-- ══════════════════════════════════════
     RESERVATION HISTORY MODAL
══════════════════════════════════════ -->
<div id="reservationModal" class="modal-overlay">
  <div class="modal-box" style="max-width:1100px; max-height:88vh;">
    <div class="modal-hd">
      <h3>Reservation History — <?= htmlspecialchars($machine['machine_name']) ?></h3>
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
            <td style="font-weight:600;white-space:nowrap;"><?= htmlspecialchars($b['farmer_full_name']) ?></td>
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
    
    const farmerName  = b.farmer_full_name || '—';
    const farmerAddr  = b.farmer_address || '—';
    const farmerPhone = b.farmer_phone || '—';
    const farmerEmail = b.farmer_email || '—';

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