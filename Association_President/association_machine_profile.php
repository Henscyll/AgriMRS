<?php
session_start();
require_once '../includes/db_connection.php';
include('dashboard_president.php');

if (!isset($_SESSION['association_id'])) {
    header("Location: ../login.php");
    exit;
}
$association_id = $_SESSION['association_id'];

$machine_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$machine_id) {
    header("Location: association_machines.php");
    exit;
}

/* ── Fetch machine (must belong to this association) ── */
$stmt = $conn->prepare("
    SELECT m.*,
           DATE_FORMAT(m.created_at, '%M %d, %Y') AS created_fmt
    FROM machines m
    WHERE m.id = ? AND m.association_id = ?
");
$stmt->bind_param("ii", $machine_id, $association_id);
$stmt->execute();
$machine = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$machine) {
    header("Location: association_machines.php");
    exit;
}

/* ── Fetch assigned operators ── */
$op_stmt = $conn->prepare("
    SELECT o.id, o.name, o.email, o.phone, o.license_number, o.status,
           mo.assigned_at
    FROM machine_operators mo
    INNER JOIN operators o ON mo.operator_id = o.id
    WHERE mo.machine_id = ? AND mo.status = 'Active'
    ORDER BY o.name
");
$op_stmt->bind_param("i", $machine_id);
$op_stmt->execute();
$operators_result = $op_stmt->get_result();
$assigned_operators = [];
while ($row = $operators_result->fetch_assoc()) $assigned_operators[] = $row;
$op_stmt->close();

/* ── Fetch booking history for this machine ── */
$bk_stmt = $conn->prepare("
    SELECT b.id, b.booking_date, b.status, b.farm_size, b.farm_location, b.notes,
           DATE_FORMAT(b.created_at,'%m/%d/%Y') AS requested_at,
           CONCAT(f.first_name,' ',COALESCE(f.middle_name,''),' ',f.last_name) AS farmer_name,
           f.phone AS farmer_phone,
           fl.lot_number
    FROM bookings b
    INNER JOIN farmers f  ON b.farmer_id  = f.id
    LEFT  JOIN farmer_lots fl ON b.lot_id = fl.id
    WHERE b.machine_id = ?
    ORDER BY b.booking_date DESC
    LIMIT 20
");
$bk_stmt->bind_param("i", $machine_id);
$bk_stmt->execute();
$bookings_result = $bk_stmt->get_result();
$bookings = [];
while ($row = $bookings_result->fetch_assoc()) $bookings[] = $row;
$bk_stmt->close();

/* ── Status badge helper ── */
function statusBadge($status) {
    $map = [
        'Active'           => ['#d1fae5','#065f46'],
        'Inactive'         => ['#fee2e2','#991b1b'],
        'Under Maintenance'=> ['#fef3c7','#92400e'],
        'Pending'          => ['#fef9c3','#854d0e'],
        'Approved'         => ['#dbeafe','#1e40af'],
        'Completed'        => ['#d1fae5','#065f46'],
        'Declined'         => ['#fee2e2','#991b1b'],
    ];
    [$bg, $color] = $map[$status] ?? ['#f3f4f6','#374151'];
    return "<span style='background:{$bg};color:{$color};padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700;'>{$status}</span>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($machine['machine_name']) ?> — Machine Profile</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* { box-sizing: border-box; }
.main-content { padding: 20px; max-width: 1100px; margin: 0 auto; }

/* ── Back button ── */
.back-btn {
    display: inline-flex; align-items: center; gap: 8px;
    background: rgba(255,255,255,0.15); color: white;
    padding: 8px 16px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.3);
    font-size: 14px; font-weight: 600; text-decoration: none;
    margin-bottom: 18px; transition: background 0.2s;
}
.back-btn:hover { background: rgba(255,255,255,0.25); }

/* ── Profile hero card ── */
.hero-card {
    background: white; border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    overflow: hidden; margin-bottom: 20px;
}
.hero-top {
    background: linear-gradient(135deg, #2d7a2d, #1a5c1a);
    padding: 28px 32px; display: flex; align-items: center; gap: 28px;
}
.machine-avatar {
    width: 110px; height: 110px; border-radius: 12px;
    object-fit: cover; border: 4px solid rgba(255,255,255,0.3);
    flex-shrink: 0;
}
.machine-avatar-placeholder {
    width: 110px; height: 110px; border-radius: 12px;
    background: rgba(255,255,255,0.15); border: 4px solid rgba(255,255,255,0.3);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.hero-info h1 { color: white; font-size: 1.6rem; font-weight: 700; margin: 0 0 6px; }
.hero-info .sub { color: rgba(255,255,255,0.8); font-size: 14px; display: flex; align-items: center; gap: 6px; }
.hero-info .badges { display: flex; gap: 8px; margin-top: 10px; flex-wrap: wrap; }
.badge-pill {
    padding: 4px 14px; border-radius: 20px; font-size: 12px; font-weight: 700;
    background: rgba(255,255,255,0.2); color: white;
}

/* ── Stats row ── */
.stats-row {
    display: grid; grid-template-columns: repeat(4, 1fr);
    border-top: 1px solid #f3f4f6;
}
.stat-box {
    padding: 18px 24px; text-align: center; border-right: 1px solid #f3f4f6;
}
.stat-box:last-child { border-right: none; }
.stat-box .stat-val { font-size: 1.6rem; font-weight: 800; color: #2d7a2d; }
.stat-box .stat-lbl { font-size: 12px; color: #6b7280; margin-top: 2px; }

/* ── Content grid ── */
.content-grid {
    display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;
}
.card {
    background: white; border-radius: 14px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.07); overflow: hidden;
}
.card-header {
    background: #f8fafc; padding: 14px 20px;
    border-bottom: 1px solid #e5e7eb;
    display: flex; align-items: center; gap: 8px;
}
.card-header h3 {
    font-size: 14px; font-weight: 700; color: #374151;
    margin: 0; text-transform: uppercase; letter-spacing: 0.05em;
}
.card-body { padding: 18px 20px; }

/* ── Info rows ── */
.info-row {
    display: flex; justify-content: space-between; align-items: flex-start;
    padding: 10px 0; border-bottom: 1px solid #f3f4f6;
}
.info-row:last-child { border-bottom: none; }
.info-label { font-size: 13px; color: #9ca3af; font-weight: 500; }
.info-value { font-size: 13px; color: #1f2937; font-weight: 600; text-align: right; max-width: 60%; }

/* ── Operator cards ── */
.op-card {
    background: #f0fdf4; border: 1px solid #bbf7d0;
    border-radius: 10px; padding: 14px 16px; margin-bottom: 10px;
    display: flex; align-items: center; gap: 14px;
}
.op-avatar {
    width: 44px; height: 44px; border-radius: 50%;
    background: #2d7a2d; display: flex; align-items: center; justify-content: center;
    color: white; font-size: 18px; flex-shrink: 0;
}
.op-name { font-weight: 700; font-size: 14px; color: #1f2937; }
.op-meta { font-size: 12px; color: #6b7280; margin-top: 2px; }
.no-data {
    text-align: center; padding: 24px; color: #9ca3af;
    background: #f9fafb; border-radius: 8px; font-size: 13px;
    border: 1px dashed #e5e7eb;
}

/* ── Bookings table ── */
.bookings-card { margin-bottom: 20px; }
.bookings-table-wrap { overflow-x: auto; }
table.bt { width: 100%; border-collapse: collapse; }
table.bt th {
    background: #2d7a2d; color: white; padding: 10px 14px;
    font-size: 13px; font-weight: 600; text-align: left; white-space: nowrap;
}
table.bt td { padding: 10px 14px; font-size: 13px; border-bottom: 1px solid #f3f4f6; }
table.bt tbody tr:hover { background: #f8fafc; }
table.bt tbody tr:last-child td { border-bottom: none; }

@media print {
    .back-btn, .action-bar { display: none !important; }
    body { background: white !important; }
    .main-content { max-width: 100%; padding: 0; }
    .hero-top { background: #2d7a2d !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    table.bt th { background: #2d7a2d !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
</style>
</head>
<body>
<div class="main-content">

    <a href="association_machines.php" class="back-btn">
        <i class="fas fa-arrow-left"></i> Back to Machines
    </a>

    <!-- ══ HERO CARD ══ -->
    <div class="hero-card">
        <div class="hero-top">
            <?php if (!empty($machine['image_path'])): ?>
                <img src="<?= htmlspecialchars($machine['image_path']) ?>"
                     class="machine-avatar" alt="Machine Image">
            <?php else: ?>
                <div class="machine-avatar-placeholder">
                    <i class="fas fa-tractor" style="font-size:40px; color:rgba(255,255,255,0.6);"></i>
                </div>
            <?php endif; ?>
            <div class="hero-info">
                <h1><?= htmlspecialchars($machine['machine_name']) ?></h1>
                <div class="sub">
                    <i class="fas fa-calendar-alt"></i>
                    Added on <?= htmlspecialchars($machine['created_fmt']) ?>
                </div>
                <div class="badges">
                    <span class="badge-pill"><i class="fas fa-cogs" style="margin-right:4px;"></i><?= htmlspecialchars($machine['type']) ?></span>
                    <span class="badge-pill"
                          style="background:<?= $machine['status']==='Active' ? 'rgba(255,255,255,0.9)' : 'rgba(255,100,100,0.5)' ?>;
                                 color:<?= $machine['status']==='Active' ? '#065f46' : 'white' ?>;">
                        <?= htmlspecialchars($machine['status']) ?>
                    </span>
                </div>
            </div>
            <!-- Print button -->
            <button onclick="window.print()"
                    style="margin-left:auto; padding:9px 18px; background:rgba(255,255,255,0.2);
                           color:white; border:1px solid rgba(255,255,255,0.4); border-radius:8px;
                           font-size:13px; font-weight:600; cursor:pointer; align-self:flex-start;
                           display:flex; align-items:center; gap:6px;">
                <i class="fas fa-print"></i> Print
            </button>
        </div>

        <!-- Stats row -->
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-val">₱<?= number_format($machine['price_per_hectare'] ?? 0, 2) ?></div>
                <div class="stat-lbl">Price / Hectare</div>
            </div>
            <div class="stat-box">
                <div class="stat-val"><?= count($assigned_operators) ?></div>
                <div class="stat-lbl">Assigned Operators</div>
            </div>
            <div class="stat-box">
                <div class="stat-val"><?= count($bookings) ?></div>
                <div class="stat-lbl">Recent Bookings</div>
            </div>
            <div class="stat-box">
                <?php
                $completed = array_filter($bookings, fn($b) => $b['status'] === 'Completed');
                $totalHa   = array_sum(array_column($completed, 'farm_size'));
                ?>
                <div class="stat-val"><?= number_format($totalHa, 2) ?> ha</div>
                <div class="stat-lbl">Total Area Serviced</div>
            </div>
        </div>
    </div>


    <!-- ══ DETAILS + OPERATORS ══ -->
    <div class="content-grid">

        <!-- Machine Details -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-info-circle" style="color:#2d7a2d;"></i>
                <h3>Machine Details</h3>
            </div>
            <div class="card-body">
                <div class="info-row">
                    <span class="info-label">Machine ID</span>
                    <span class="info-value">#<?= $machine['id'] ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Machine Name</span>
                    <span class="info-value"><?= htmlspecialchars($machine['machine_name']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Type</span>
                    <span class="info-value"><?= htmlspecialchars($machine['type']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Status</span>
                    <span class="info-value"><?= statusBadge($machine['status']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Price / Hectare</span>
                    <span class="info-value" style="color:#2d7a2d;">
                        <?= ($machine['price_per_hectare'] > 0)
                            ? '₱'.number_format($machine['price_per_hectare'],2)
                            : '<span style="color:#9ca3af;">Not Set</span>' ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Operator Rate / ha</span>
                    <span class="info-value">
                        <?= ($machine['operator_rate_per_hectare'] > 0)
                            ? '₱'.number_format($machine['operator_rate_per_hectare'],2)
                            : '<span style="color:#9ca3af;">Not Set</span>' ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Description</span>
                    <span class="info-value">
                        <?= !empty($machine['description'])
                            ? htmlspecialchars($machine['description'])
                            : '<span style="color:#9ca3af;">—</span>' ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Date Added</span>
                    <span class="info-value"><?= htmlspecialchars($machine['created_fmt']) ?></span>
                </div>
            </div>
        </div>

        <!-- Assigned Operators -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-users" style="color:#2d7a2d;"></i>
                <h3>Assigned Operators</h3>
            </div>
            <div class="card-body">
                <?php if (count($assigned_operators) > 0): ?>
                    <?php foreach ($assigned_operators as $op): ?>
                    <div class="op-card">
                        <div class="op-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <div>
                            <div class="op-name"><?= htmlspecialchars($op['name']) ?></div>
                            <div class="op-meta">
                                <i class="fas fa-envelope" style="margin-right:4px;"></i><?= htmlspecialchars($op['email'] ?? '—') ?>
                            </div>
                            <div class="op-meta">
                                <i class="fas fa-phone" style="margin-right:4px;"></i><?= htmlspecialchars($op['phone']) ?>
                            </div>
                            <?php if (!empty($op['license_number'])): ?>
                            <div class="op-meta">
                                <i class="fas fa-id-card" style="margin-right:4px;"></i><?= htmlspecialchars($op['license_number']) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div style="margin-left:auto;">
                            <?= statusBadge($op['status']) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-data">
                        <i class="fas fa-user-slash" style="font-size:24px; margin-bottom:8px; display:block;"></i>
                        No operators currently assigned to this machine.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>


    <!-- ══ BOOKING HISTORY ══ -->
    <div class="card bookings-card">
        <div class="card-header">
            <i class="fas fa-history" style="color:#2d7a2d;"></i>
            <h3>Recent Booking History</h3>
            <span style="margin-left:auto; font-size:12px; color:#6b7280; font-weight:400; text-transform:none;">
                Last <?= count($bookings) ?> bookings
            </span>
        </div>
        <div class="card-body" style="padding:0;">
            <div class="bookings-table-wrap">
                <?php if (count($bookings) > 0): ?>
                <table class="bt">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Booking Date</th>
                            <th>Farmer</th>
                            <th>Lot #</th>
                            <th>Farm Size (ha)</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>Requested</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($bookings as $b): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= htmlspecialchars(date('m/d/Y', strtotime($b['booking_date']))) ?></td>
                            <td>
                                <div style="font-weight:600;"><?= htmlspecialchars(trim($b['farmer_name'])) ?></div>
                                <div style="font-size:11px; color:#6b7280;"><?= htmlspecialchars($b['farmer_phone'] ?? '') ?></div>
                            </td>
                            <td><?= htmlspecialchars($b['lot_number'] ?? '—') ?></td>
                            <td><?= number_format($b['farm_size'] ?? 0, 2) ?> ha</td>
                            <td><?= htmlspecialchars($b['farm_location'] ?? '—') ?></td>
                            <td><?= statusBadge($b['status']) ?></td>
                            <td><?= htmlspecialchars($b['requested_at']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="no-data" style="margin:16px;">
                    <i class="fas fa-calendar-times" style="font-size:24px; margin-bottom:8px; display:block;"></i>
                    No booking history found for this machine.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>
</body>
</html>