<?php
session_start();
require_once '../includes/db_connection.php';
include('dashboard_president.php');

if (!isset($_SESSION['association_id'])) {
    header("Location: ../login.php");
    exit;
}
$association_id = $_SESSION['association_id'];

$message = "";
$error   = "";

/* ── ENSURE TABLE EXISTS ── */
$conn->query("
    CREATE TABLE IF NOT EXISTS machine_rate_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        machine_id INT NOT NULL,
        association_id INT NOT NULL,
        rate_type ENUM('price','operator_rate') NOT NULL,
        old_value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        new_value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        effective_date DATE NOT NULL DEFAULT (CURDATE()),
        changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        changed_by INT DEFAULT NULL,
        INDEX(machine_id), INDEX(association_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

/* ── ADD NEW MACHINE PRICE ── */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_machine_price'], $_POST['confirmed'])) {
    $machine_id     = intval($_POST['machine_id']);
    $new_price      = floatval($_POST['price_per_hectare']);
    $effective_date = trim($_POST['effective_date']);

    $r = $conn->prepare("SELECT price_per_hectare FROM machines WHERE id = ? AND association_id = ?");
    $r->bind_param("ii", $machine_id, $association_id);
    $r->execute();
    $old = $r->get_result()->fetch_assoc();
    $r->close();
    $old_price = $old ? floatval($old['price_per_hectare']) : 0;

    if ($new_price < 0) {
        $error = "Price cannot be negative.";
    } elseif (empty($effective_date)) {
        $error = "Effective date is required.";
    } else {
        if ($effective_date <= date('Y-m-d')) {
            $s = $conn->prepare("UPDATE machines SET price_per_hectare = ? WHERE id = ? AND association_id = ?");
            $s->bind_param("dii", $new_price, $machine_id, $association_id);
            $s->execute();
            $s->close();
        }
        $uid = $_SESSION['user_id'] ?? 0;
        $h = $conn->prepare("INSERT INTO machine_rate_history (machine_id,association_id,rate_type,old_value,new_value,effective_date,changed_by) VALUES (?,?,'price',?,?,?,?)");
        $h->bind_param("iiddsi", $machine_id, $association_id, $old_price, $new_price, $effective_date, $uid);
        $h->execute();
        $h->close();
        $message = "New price ₱" . number_format($new_price, 2) . " saved — effective " . date('m/d/Y', strtotime($effective_date)) . ".";
    }
}

/* ── ADD NEW OPERATOR RATE ── */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_operator_rate'], $_POST['confirmed'])) {
    $machine_id     = intval($_POST['machine_id']);
    $new_rate       = floatval($_POST['operator_rate_per_hectare']);
    $effective_date = trim($_POST['effective_date']);

    $r = $conn->prepare("SELECT operator_rate_per_hectare FROM machines WHERE id = ? AND association_id = ?");
    $r->bind_param("ii", $machine_id, $association_id);
    $r->execute();
    $old = $r->get_result()->fetch_assoc();
    $r->close();
    $old_rate = $old ? floatval($old['operator_rate_per_hectare']) : 0;

    if ($new_rate < 0) {
        $error = "Rate cannot be negative.";
    } elseif (empty($effective_date)) {
        $error = "Effective date is required.";
    } else {
        if ($effective_date <= date('Y-m-d')) {
            $s = $conn->prepare("UPDATE machines SET operator_rate_per_hectare = ? WHERE id = ? AND association_id = ?");
            $s->bind_param("dii", $new_rate, $machine_id, $association_id);
            $s->execute();
            $s->close();
        }
        $uid = $_SESSION['user_id'] ?? 0;
        $h = $conn->prepare("INSERT INTO machine_rate_history (machine_id,association_id,rate_type,old_value,new_value,effective_date,changed_by) VALUES (?,?,'operator_rate',?,?,?,?)");
        $h->bind_param("iiddsi", $machine_id, $association_id, $old_rate, $new_rate, $effective_date, $uid);
        $h->execute();
        $h->close();
        $message = "New operator rate ₱" . number_format($new_rate, 2) . " saved — effective " . date('m/d/Y', strtotime($effective_date)) . ".";
    }
}

/* ── FETCH MACHINES ── */
$s = $conn->prepare("
    SELECT m.id, m.machine_name, m.type, m.status,
           m.price_per_hectare, m.operator_rate_per_hectare,
           a.name AS association_name
    FROM machines m
    LEFT JOIN associations a ON m.association_id = a.id
    WHERE m.association_id = ?
    ORDER BY m.machine_name ASC
");
$s->bind_param("i", $association_id);
$s->execute();
$machines = $s->get_result()->fetch_all(MYSQLI_ASSOC);
$s->close();

/* ── FETCH OPERATORS ── */
$os = $conn->prepare("
    SELECT o.id, o.name AS operator_name, o.status,
           a.name AS association_name,
           m.operator_rate_per_hectare,
           (
               SELECT mrh.effective_date
               FROM machine_rate_history mrh
               JOIN machine_operators mo2 ON mo2.machine_id = mrh.machine_id
               WHERE mo2.operator_id = o.id
                 AND mrh.rate_type = 'operator_rate'
                 AND mrh.association_id = o.association_id
               ORDER BY mrh.effective_date DESC
               LIMIT 1
           ) AS effective_date
    FROM operators o
    LEFT JOIN associations a ON o.association_id = a.id
    LEFT JOIN machine_operators mo ON mo.operator_id = o.id AND mo.status = 'Active'
    LEFT JOIN machines m ON m.id = mo.machine_id
    WHERE o.association_id = ?
    GROUP BY o.id
    ORDER BY o.name ASC
");
$os->bind_param("i", $association_id);
$os->execute();
$operators = $os->get_result()->fetch_all(MYSQLI_ASSOC);
$os->close();

/* ── FETCH EFFECTIVE DATES FOR MACHINES ── */
$machine_eff = [];
foreach ($machines as $m) {
    $mid = intval($m['id']);
    $eq = $conn->prepare("
        SELECT effective_date FROM machine_rate_history
        WHERE machine_id = ? AND association_id = ? AND rate_type = 'price'
          AND effective_date <= CURDATE()
        ORDER BY effective_date DESC LIMIT 1
    ");
    $eq->bind_param("ii", $mid, $association_id);
    $eq->execute();
    $row = $eq->get_result()->fetch_assoc();
    $machine_eff[$mid]['price'] = $row ? $row['effective_date'] : null;
    $eq->close();

    $eq2 = $conn->prepare("
        SELECT effective_date FROM machine_rate_history
        WHERE machine_id = ? AND association_id = ? AND rate_type = 'operator_rate'
          AND effective_date <= CURDATE()
        ORDER BY effective_date DESC LIMIT 1
    ");
    $eq2->bind_param("ii", $mid, $association_id);
    $eq2->execute();
    $row2 = $eq2->get_result()->fetch_assoc();
    $machine_eff[$mid]['operator_rate'] = $row2 ? $row2['effective_date'] : null;
    $eq2->close();

    /* upcoming price */
    $uq = $conn->prepare("
        SELECT new_value, effective_date FROM machine_rate_history
        WHERE machine_id = ? AND association_id = ? AND rate_type = 'price'
          AND effective_date > CURDATE()
        ORDER BY effective_date ASC LIMIT 1
    ");
    $uq->bind_param("ii", $mid, $association_id);
    $uq->execute();
    $machine_eff[$mid]['upcoming_price'] = $uq->get_result()->fetch_assoc();
    $uq->close();
}

/* ── BUILD OPERATOR -> MACHINE MAP (for the operator add-rate dropdown) ── */
$operator_machine_map = [];
foreach ($operators as $op) {
    $mq = $conn->prepare("
        SELECT m.id, m.machine_name, m.price_per_hectare, m.operator_rate_per_hectare
        FROM machine_operators mo
        JOIN machines m ON m.id = mo.machine_id
        WHERE mo.operator_id = ? AND mo.status = 'Active'
        LIMIT 1
    ");
    $mq->bind_param("i", $op['id']);
    $mq->execute();
    $op_machine = $mq->get_result()->fetch_assoc();
    $mq->close();
    $operator_machine_map[$op['id']] = $op_machine ?: null;
}

$js_message = addslashes($message);
$js_error   = addslashes($error);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>System Settings</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Segoe UI', sans-serif; background: rgb(253, 253, 253); }

.main-content { padding: 30px 20px; }

.welcome-bar {
    color: #000; font-size: 14px; font-weight: 600; margin-bottom: 10px;
    display: flex; align-items: center; gap: 8px;
}
.welcome-bar a { color: #000; text-decoration: underline; font-weight: 700; }

.pg-title {
    text-align: center; color: #2d7a2d; font-size: 26px; font-weight: 700;
    margin-bottom: 28px; letter-spacing: .3px;
}

/* ── ALERTS ── */
.alert {
    padding: 11px 16px; border-radius: 8px; font-size: 13px; margin-bottom: 18px;
    display: flex; align-items: center; gap: 9px;
    max-width: 900px; margin-left: auto; margin-right: auto;
}
.alert-ok  { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
.alert-err { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

/* ── TAB SWITCHER ── */
.tab-switcher {
    display: flex; background: #fff; border-radius: 50px; padding: 5px;
    width: fit-content; margin: 0 auto 28px;
    box-shadow: 0 4px 20px rgba(0,0,0,.12); gap: 4px;
}
.tab-btn {
    padding: 10px 28px; border-radius: 50px; border: none; background: transparent;
    font-size: 14px; font-weight: 600; color: #555; cursor: pointer;
    display: flex; align-items: center; gap: 7px; transition: all .2s;
}
.tab-btn.active { background: #2e7d32; color: #fff; box-shadow: 0 2px 10px rgba(46,125,50,.35); }
.tab-btn:hover:not(.active) { background: #f0fdf4; color: #2e7d32; }
.tab-panel { display: none; }
.tab-panel.active { display: block; }

/* ── CARD ── */
.card-wrap {
    background: #fff; border-radius: 16px;
    box-shadow: 0 4px 24px rgba(0,0,0,.08);
    max-width: 1100px; margin: 0 auto; overflow: hidden;
}
.card-head {
    background: #2e7d32; color: #fff; padding: 16px 24px;
    display: flex; align-items: center; justify-content: space-between;
}
.card-head-left { display: flex; align-items: center; gap: 10px; font-size: 16px; font-weight: 700; }
.card-head-sub  { font-size: 12px; opacity: .8; font-weight: 400; margin-left: 4px; }
.count-badge {
    background: rgba(255,255,255,.25); color: #fff;
    border-radius: 20px; padding: 3px 12px; font-size: 12px; font-weight: 700;
}

/* ── TABLE ── */
.tbl-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; font-size: 13px; }
thead tr { background: #f7fdf7; border-bottom: 1.5px solid #e8f0e8; }
th {
    padding: 12px 16px; text-align: left; font-weight: 700;
    color: #546e57; font-size: 12px; text-transform: uppercase;
    letter-spacing: .5px; white-space: nowrap;
}
td { padding: 12px 16px; color: #333; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
tbody tr:last-child td { border-bottom: none; }
tbody tr:hover td { background: #f7fdf7; }

/* ── BADGES ── */
.badge {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700;
}


.sdot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; flex-shrink: 0; }
.sdot-a { background: #43a047; } .sdot-i { background: #ef5350; } .sdot-m { background: #fb8c00; }

.machine-id { font-family: monospace; font-size: 12px; color: #78909c; }
.amount     { font-weight: 700; color: #2e7d32; }
.na         { color: #bdbdbd; font-style: italic; }
.eff-date   { font-size: 11px; color: #9e9e9e; margin-top: 2px; }

/* ── ROW SELECTION ── */
.selectable-row { cursor: pointer; }
.selectable-row.row-selected td {
    background: #e8f5e9 !important;
    box-shadow: inset 3px 0 0 #2e7d32;
}
.row-disabled { opacity: .55; cursor: not-allowed; }
.row-disabled td { background: #fafafa !important; }

/* ── ACTION FOOTER (centered, below table) ── */
.action-footer {
    display: flex; justify-content: center; align-items: center;
    padding: 18px 24px; background: #f7fdf7; border-top: 1.5px solid #e8f0e8;
}
.btn-action {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 10px 26px; background: #2e7d32;
    border: none; border-radius: 8px;
    color: #fff; font-size: 13px; font-weight: 700; cursor: pointer;
    transition: all .15s;
}
.btn-action:hover { background: #1b5e20; }
.btn-action:disabled { background: #bdbdbd; cursor: not-allowed; }

/* ── MODALS ── */
.modal-bg {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,.5); z-index: 9999;
    justify-content: center; align-items: center; backdrop-filter: blur(3px);
}
.modal-bg.open { display: flex; }
.modal-box {
    background: #fff; border-radius: 14px; width: 440px; max-width: 95%;
    max-height: 92vh; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,.22);
}
.modal-hd {
    background: #2e7d32; color: #fff; padding: 16px 20px;
    display: flex; justify-content: space-between; align-items: center;
}
.modal-hd h3 { font-size: 15px; font-weight: 700; }
.modal-x {
    width: 30px; height: 30px; background: rgba(255,255,255,.2);
    border: none; border-radius: 50%; color: #fff; font-size: 15px;
    cursor: pointer; display: flex; align-items: center; justify-content: center;
}
.modal-x:hover { background: rgba(255,255,255,.35); }
.modal-bd { padding: 20px; overflow-y: auto; max-height: calc(92vh - 60px); }

.info-box { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; padding: 12px 16px; margin-bottom: 16px; }
.irow { display: flex; justify-content: space-between; font-size: 13px; padding: 5px 0; border-bottom: 1px solid #f3f4f6; }
.irow:last-child { border-bottom: none; }
.irow .lbl { color: #9ca3af; } .irow .val { font-weight: 700; color: #111827; }

.modal-notice { background: #f0fdf4; border-left: 3px solid #2e7d32; border-radius: 0 8px 8px 0; padding: 10px 13px; font-size: 12px; color: #374151; line-height: 1.5; margin-bottom: 14px; }
.modal-warn   { background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 9px 12px; font-size: 11px; color: #92400e; margin-bottom: 14px; line-height: 1.5; }

.fgrp { margin-bottom: 14px; }
.fgrp label { display: block; font-size: 12px; font-weight: 700; color: #374151; margin-bottom: 5px; }
.fgrp input {
    width: 100%; padding: 9px 12px; border: 1.5px solid #e5e7eb;
    border-radius: 8px; font-size: 13px; color: #111827;
    outline: none; transition: border-color .15s;
}
.fgrp input:focus { border-color: #2e7d32; box-shadow: 0 0 0 3px rgba(46,125,50,.1); }
.pw { position: relative; }
.pw .sym { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #2e7d32; font-weight: 700; font-size: 13px; pointer-events: none; }
.pw input { padding-left: 24px; }
.pct-preview { font-size: 12px; color: #2e7d32; font-weight: 600; margin-top: 5px; min-height: 16px; }

.modal-ft { display: flex; gap: 10px; justify-content: flex-end; padding-top: 12px; border-top: 1px solid #f3f4f6; margin-top: 4px; }
.btn-confirm { padding: 9px 22px; background: #2e7d32; color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 6px; }
.btn-confirm:hover { background: #1b5e20; }
.btn-cancel { padding: 9px 18px; background: #fff; color: #6b7280; border: 1.5px solid #e5e7eb; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; }
.btn-cancel:hover { background: #f9fafb; }

/* ── CONFIRM DIALOG ── */
#confirmDialog {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,.45); z-index: 10000;
    justify-content: center; align-items: center; backdrop-filter: blur(2px);
}
#confirmDialog.open { display: flex; }
.cd-box { background: #fff; border-radius: 14px; width: 360px; max-width: 92%; box-shadow: 0 24px 60px rgba(0,0,0,.25); overflow: hidden; }
.cd-head { background: #2e7d32; color: #fff; padding: 14px 20px; }
.cd-title { font-size: 15px; font-weight: 700; }
.cd-sub   { font-size: 12px; opacity: .8; margin-top: 2px; }
.cd-body  { padding: 20px 24px; font-size: 14px; color: #374151; line-height: 1.6; }
.cd-foot  { padding: 14px 20px; display: flex; gap: 10px; justify-content: flex-end; border-top: 1px solid #f3f4f6; }
.cd-yes { padding: 9px 26px; background: #2e7d32; color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; }
.cd-yes:hover { background: #1b5e20; }
.cd-no  { padding: 9px 20px; background: #fff; color: #6b7280; border: 1.5px solid #e5e7eb; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; }
.cd-no:hover { background: #f9fafb; }

/* ── SUCCESS DIALOG ── */
#successDialog {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,.45); z-index: 10000;
    justify-content: center; align-items: center; backdrop-filter: blur(2px);
}
#successDialog.open { display: flex; }
.sd-box { background: #fff; border-radius: 14px; width: 360px; max-width: 92%; box-shadow: 0 24px 60px rgba(0,0,0,.25); overflow: hidden; text-align: center; }
.sd-icon  { padding: 28px 20px 10px; }
.sd-icon i { font-size: 48px; color: #2e7d32; }
.sd-title { font-size: 17px; font-weight: 700; color: #1b5e20; margin-bottom: 6px; padding: 0 20px; }
.sd-msg   { font-size: 13px; color: #6b7280; padding: 0 24px 20px; line-height: 1.6; }
.sd-foot  { padding: 14px 20px; border-top: 1px solid #f3f4f6; }
.sd-ok { width: 100%; padding: 10px; background: #2e7d32; color: #fff; border: none; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer; }
.sd-ok:hover { background: #1b5e20; }

.empty-state { padding: 40px 20px; text-align: center; color: #bdbdbd; }
.empty-state i { font-size: 36px; margin-bottom: 10px; opacity: .4; }
.empty-state p { font-size: 13px; }
</style>
</head>
<body>
<div class="main-content">

<div class="welcome-bar">
    Welcome <?= htmlspecialchars($_SESSION['association_name'] ?? 'Association') ?>
    &nbsp;<a href="../logout.php">Logout</a>
</div>

<h2 class="pg-title">System Settings</h2>

<!-- ══ TAB SWITCHER ══ -->
<div class="tab-switcher">
    <button class="tab-btn active" onclick="switchTab('machine-rate', this)">
        <i class="fas fa-tag"></i> Machine Rate
    </button>
    <button class="tab-btn" onclick="switchTab('operator-rate', this)">
        <i class="fas fa-hard-hat"></i> Operator Rate
    </button>
</div>

<!-- ══════════════════════════════
     TAB 1: MACHINE RATE TABLE
══════════════════════════════ -->
<div class="tab-panel active" id="tab-machine-rate">
    <div class="card-wrap">
        <div class="card-head">
            <div class="card-head-left">
                <i class="fas fa-tag"></i>
                Machine Price Per Hectare
                <span class="card-head-sub">— Farmer charge per ha</span>
            </div>
            <span class="count-badge"><?= count($machines) ?> machines</span>
        </div>
        <?php if ($machines): ?>
        <div class="tbl-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Machine ID</th>
                        <th>Machine name</th>
                        <th>Machine type</th>
                        <th>Association</th>
                        <th>Rent amount / ha</th>
                        <th>Effective date</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($machines as $m):
                    $mid   = intval($m['id']);
                    $price = floatval($m['price_per_hectare']);
                    $eff_price = $machine_eff[$mid]['price'] ?? null;
                    $upcoming  = $machine_eff[$mid]['upcoming_price'] ?? null;

                    $status_class = match($m['status']) {
                        'Active'            => 'b-active',
                        'Inactive'          => 'b-inactive',
                        'Under Maintenance' => 'b-maint',
                        default             => 'b-active'
                    };
                    $dot_class = match($m['status']) {
                        'Active'            => 'sdot-a',
                        'Inactive'          => 'sdot-i',
                        'Under Maintenance' => 'sdot-m',
                        default             => 'sdot-a'
                    };
                ?>
                <tr class="selectable-row" data-row-group="machine"
                    data-id="<?= $mid ?>"
                    data-name="<?= htmlspecialchars(addslashes($m['machine_name'])) ?>"
                    data-price="<?= $price ?>"
                    onclick="selectRow(this,'machine')">
                    <td><span class="machine-id">#<?= $mid ?></span></td>
                    <td>
                        <?= htmlspecialchars($m['machine_name']) ?>
                        <span class="badge <?= $status_class ?>">
                            <span class="sdot <?= $dot_class ?>"></span>
                            <?= $m['status'] ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge <?= $m['type'] === 'Harvester' ? 'b-harvester' : 'b-tractor' ?>">
                            <i class="fas <?= $m['type'] === 'Harvester' ? 'fa-tractor' : 'fa-truck-monster' ?>" style="font-size:10px"></i>
                            <?= $m['type'] ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($m['association_name']) ?></td>
                    <td>
                        <?php if ($price > 0): ?>
                            <span class="amount">₱<?= number_format($price, 2) ?></span>
                            <?php if ($upcoming): ?>
                                <br><span class="badge b-upcoming" style="margin-top:4px">
                                    <i class="fas fa-clock" style="font-size:9px"></i>
                                    → ₱<?= number_format($upcoming['new_value'], 2) ?> on <?= date('M d', strtotime($upcoming['effective_date'])) ?>
                                </span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="na">Not set</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($eff_price): ?>
                            <?= date('M d, Y', strtotime($eff_price)) ?>
                        <?php else: ?>
                            <span class="na">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="action-footer">
            <button class="btn-action" id="btnAddMachinePrice" onclick="openRateModalFromRow('machine','price')" disabled>
                <i class="fas fa-plus"></i> Add rate
            </button>
        </div>
        <?php else: ?>
        <div class="empty-state"><i class="fas fa-tractor"></i><p>No machines found.</p></div>
        <?php endif; ?>
    </div>
</div>

<!-- ══════════════════════════════
     TAB 2: OPERATOR RATE TABLE
══════════════════════════════ -->
<div class="tab-panel" id="tab-operator-rate">
    <div class="card-wrap">
        <div class="card-head">
            <div class="card-head-left">
                <i class="fas fa-hard-hat"></i>
                Operator Rate Per Hectare
                <span class="card-head-sub">— Operator earning per ha</span>
            </div>
            <span class="count-badge"><?= count($operators) ?> operators</span>
        </div>
        <?php if ($operators): ?>
        <div class="tbl-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Operator ID</th>
                        <th>Operator name</th>
                        <th>Association</th>
                        <th>Operator rate / ha</th>
                        <th>Effectivity</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($operators as $op):
                    $op_rate = floatval($op['operator_rate_per_hectare']);
                    $op_eff  = $op['effective_date'] ?? null;
                    $opm     = $operator_machine_map[$op['id']] ?? null;
                    $has_machine = (bool) $opm;
                ?>
                <tr class="<?= $has_machine ? 'selectable-row' : 'row-disabled' ?>" data-row-group="operator"
                    <?php if ($has_machine): ?>
                    data-id="<?= intval($opm['id']) ?>"
                    data-name="<?= htmlspecialchars(addslashes($opm['machine_name'])) ?>"
                    data-price="<?= floatval($opm['price_per_hectare']) ?>"
                    data-rate="<?= floatval($opm['operator_rate_per_hectare']) ?>"
                    onclick="selectRow(this,'operator')"
                    <?php endif; ?>>
                    <td><span class="machine-id">#<?= $op['id'] ?></span></td>
                    <td><?= htmlspecialchars($op['operator_name']) ?></td>
                    <td><?= htmlspecialchars($op['association_name']) ?></td>
                    <td>
                        <?php if ($op_rate > 0): ?>
                            <span class="amount">₱<?= number_format($op_rate, 2) ?></span>
                        <?php else: ?>
                            <span class="na">Not set</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($op_eff): ?>
                            <?= date('M d, Y', strtotime($op_eff)) ?>
                        <?php else: ?>
                            <span class="na">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="action-footer">
            <button class="btn-action" id="btnAddOperatorRate" onclick="openRateModalFromRow('operator','operator_rate')" disabled>
                <i class="fas fa-plus"></i> Add rate
            </button>
        </div>
        <?php else: ?>
        <div class="empty-state"><i class="fas fa-hard-hat"></i><p>No operators found.</p></div>
        <?php endif; ?>
    </div>
</div>

</div><!-- /.main-content -->


<!-- ══════════════════════
     ADD RATE MODAL
══════════════════════ -->
<div class="modal-bg" id="modalRate">
    <div class="modal-box">
        <div class="modal-hd">
            <h3 id="rateModalTitle"><i class="fas fa-plus"></i> Add New Rate</h3>
            <button class="modal-x" onclick="cancelRateModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-bd">
            <div class="modal-notice" id="rateNotice"></div>
            <div class="info-box">
                <div class="irow"><span class="lbl">Machine</span><span class="val" id="rm_machine">—</span></div>
                <div class="irow"><span class="lbl" id="rm_cur_label">Current price</span><span class="val" id="rm_current">—</span></div>
                <div class="irow" id="rm_fprice_row" style="display:none">
                    <span class="lbl">Farmer price / ha</span><span class="val" id="rm_fprice">—</span>
                </div>
            </div>
            <form method="POST" id="formRate">
                <input type="hidden" name="confirmed" value="1">
                <input type="hidden" name="machine_id" id="rm_mid">
                <input type="hidden" name="rate_mode"  id="rm_mode_hidden">

                <div class="fgrp">
                    <label id="rm_rate_label">New price (₱ per hectare) <span style="color:#ef5350">*</span></label>
                    <div class="pw">
                        <span class="sym">₱</span>
                        <input type="number" name="new_rate_value" id="rm_rate_input"
                               min="0" step="0.01" placeholder="0.00" oninput="calcPct(this.value)">
                    </div>
                    <div class="pct-preview" id="rm_pct_preview"></div>
                </div>

                <div class="fgrp">
                    <label>Effective date <span style="color:#ef5350">*</span></label>
                    <input type="date" name="effective_date" id="rm_eff_date">
                </div>

                <div class="modal-warn">
                    <i class="fas fa-info-circle"></i>
                    <strong>Note:</strong> If the effective date is today or in the past, the rate updates immediately.
                    Future dates are logged as <em>upcoming</em>.
                </div>

                <input type="hidden" name="price_per_hectare"         id="rm_price_field">
                <input type="hidden" name="operator_rate_per_hectare" id="rm_oprate_field">
                <input type="hidden" name="add_machine_price"         id="rm_act_price">
                <input type="hidden" name="add_operator_rate"         id="rm_act_oprate">

                <div class="modal-ft">
                    <button type="button" class="btn-confirm" onclick="askSaveConfirm()">
                        <i class="fas fa-save"></i> Save
                    </button>
                    <button type="button" class="btn-cancel" onclick="cancelRateModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ══════════════════════
     CONFIRM DIALOG
══════════════════════ -->
<div id="confirmDialog">
    <div class="cd-box">
        <div class="cd-head">
            <div class="cd-title" id="cd_title">Add New Rate</div>
            <div class="cd-sub">Please confirm your action</div>
        </div>
        <div class="cd-body" id="cd_body">Are you sure you want to add new rate?</div>
        <div class="cd-foot">
            <button class="cd-yes" id="cd_yes_btn">Yes</button>
            <button class="cd-no"  onclick="closeConfirmDialog()">No</button>
        </div>
    </div>
</div>


<!-- ══════════════════════
     SUCCESS DIALOG
══════════════════════ -->
<div id="successDialog">
    <div class="sd-box">
        <div class="sd-icon"><i class="fas fa-check-circle"></i></div>
        <div class="sd-title">Successfully Added!</div>
        <div class="sd-msg" id="sd_msg">The new rate has been added successfully.</div>
        <div class="sd-foot">
            <button class="sd-ok" onclick="closeSuccessDialog()">OK</button>
        </div>
    </div>
</div>


<script>
/* ── Tab switching ── */
function switchTab(name, btn) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    btn.classList.add('active');
}

/* ── Helpers ── */
function peso(n) {
    return '₱' + parseFloat(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function openModal(id)  { document.getElementById(id).classList.add('open');    document.body.style.overflow = 'hidden'; }
function closeModal(id) { document.getElementById(id).classList.remove('open'); document.body.style.overflow = ''; }

/* ── Row selection (click a row to select it, then use Add rate) ── */
const _selected = { machine: null, operator: null };

function selectRow(tr, group) {
    document.querySelectorAll('.selectable-row[data-row-group="' + group + '"]').forEach(r => r.classList.remove('row-selected'));
    tr.classList.add('row-selected');
    _selected[group] = {
        id:    tr.dataset.id,
        name:  tr.dataset.name,
        price: parseFloat(tr.dataset.price) || 0,
        rate:  parseFloat(tr.dataset.rate)  || 0
    };
    const btnId = group === 'machine' ? 'btnAddMachinePrice' : 'btnAddOperatorRate';
    document.getElementById(btnId).disabled = false;
}

/* ── Open rate modal using the currently selected row ── */
function openRateModalFromRow(group, mode) {
    const sel = _selected[group];
    if (!sel) return;

    const currentRate = mode === 'operator_rate' ? sel.rate : sel.price;
    openRateModal(sel.id, mode, sel.name, sel.price, currentRate);
}

/* ── Rate modal ── */
let _rateMode = 'price', _farmerPrice = 0;

function openRateModal(machineId, mode, machineName, farmerPrice, currentRate) {
    _rateMode    = mode;
    _farmerPrice = parseFloat(farmerPrice) || 0;

    ['rm_act_price','rm_act_oprate','rm_price_field','rm_oprate_field'].forEach(id => {
        document.getElementById(id).disabled = true;
    });

    if (mode === 'price') {
        document.getElementById('rateModalTitle').innerHTML = '<i class="fas fa-tag"></i> Add New Machine Price';
        document.getElementById('rm_rate_label').innerHTML  = 'New price (₱ per hectare) <span style="color:#ef5350">*</span>';
        document.getElementById('rm_cur_label').textContent = 'Current price';
        document.getElementById('rateNotice').textContent   = 'Only bookings created on or after the effective date will use this new price. Past bookings remain unchanged.';
        document.getElementById('rm_fprice_row').style.display = 'none';
        document.getElementById('rm_act_price').disabled   = false;
        document.getElementById('rm_price_field').disabled = false;
        document.getElementById('rm_act_price').value      = '1';
    } else {
        document.getElementById('rateModalTitle').innerHTML = '<i class="fas fa-hard-hat"></i> Add New Operator Rate';
        document.getElementById('rm_rate_label').innerHTML  = 'New operator rate (₱ per hectare) <span style="color:#ef5350">*</span>';
        document.getElementById('rm_cur_label').textContent = 'Current operator rate';
        document.getElementById('rateNotice').textContent   = 'Past completed bookings and operator earnings are NOT affected. Only new bookings after the effective date will use this rate.';
        document.getElementById('rm_fprice_row').style.display = 'flex';
        document.getElementById('rm_fprice').textContent   = _farmerPrice > 0 ? peso(_farmerPrice) + '/ha' : 'Not set';
        document.getElementById('rm_act_oprate').disabled  = false;
        document.getElementById('rm_oprate_field').disabled = false;
        document.getElementById('rm_act_oprate').value     = '1';
    }

    document.getElementById('rm_mid').value           = machineId;
    document.getElementById('rm_machine').textContent = machineName;
    document.getElementById('rm_current').textContent = currentRate > 0 ? peso(currentRate) + '/ha' : 'Not set';
    document.getElementById('rm_rate_input').value    = '';
    document.getElementById('rm_eff_date').value      = new Date().toISOString().split('T')[0];
    document.getElementById('rm_pct_preview').textContent = '';
    openModal('modalRate');
}

function calcPct(val) {
    if (_rateMode !== 'operator_rate' || _farmerPrice <= 0) return;
    const pct = ((parseFloat(val) || 0) / _farmerPrice * 100).toFixed(1);
    document.getElementById('rm_pct_preview').textContent = pct + '% of farmer price (' + peso(_farmerPrice) + '/ha)';
}

function cancelRateModal() { closeModal('modalRate'); }

function askSaveConfirm() {
    const val  = document.getElementById('rm_rate_input').value;
    const date = document.getElementById('rm_eff_date').value;
    if (!val || parseFloat(val) < 0) { alert('Please enter a valid rate amount.'); return; }
    if (!date) { alert('Please select an effective date.'); return; }

    document.getElementById('cd_title').textContent = 'Add New Rate';
    document.getElementById('cd_body').textContent  = 'Are you sure you want to add new rate?';

    document.getElementById('cd_yes_btn').onclick = function () {
        closeConfirmDialog();
        if (_rateMode === 'price') {
            document.getElementById('rm_price_field').value  = val;
        } else {
            document.getElementById('rm_oprate_field').value = val;
        }
        document.getElementById('formRate').submit();
    };

    document.getElementById('confirmDialog').classList.add('open');
}

function closeConfirmDialog() { document.getElementById('confirmDialog').classList.remove('open'); }

function closeSuccessDialog() {
    document.getElementById('successDialog').classList.remove('open');
    document.body.style.overflow = '';
}

document.addEventListener('DOMContentLoaded', function () {
    const saved = <?= json_encode($js_message) ?>;
    const err   = <?= json_encode($js_error) ?>;
    if (saved) {
        document.getElementById('sd_msg').textContent = 'The new rate has been added successfully. ' + saved;
        document.getElementById('successDialog').classList.add('open');
        document.body.style.overflow = 'hidden';
    }
    if (err) alert(err);
});

document.getElementById('confirmDialog').addEventListener('click', function (e) {
    if (e.target === this) closeConfirmDialog();
});
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { closeConfirmDialog(); closeModal('modalRate'); }
});
</script>
</body>
</html>