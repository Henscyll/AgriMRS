<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'associations') {
    header("Location: ../login.php");
    exit();
}

include '../includes/config.php';
include 'dashboard_president.php';

$user_id = $_SESSION['user_id'];

// Get the association linked to this logged-in user
$stmt = $conn->prepare("SELECT id, name, municipality, barangay, province, phone FROM associations WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$assoc_row = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$assoc_row) die("Association not linked.");

$association_id   = $assoc_row['id'];
$association_name = $assoc_row['name'];
$assoc_location   = trim(($assoc_row['barangay'] ?? '') . ', ' . ($assoc_row['municipality'] ?? '') . ', ' . ($assoc_row['province'] ?? ''));

// Get logged-in user's display name
$logged_in_name = $_SESSION['name'] ?? $_SESSION['username'] ?? $association_name;

// Period filter
$period = $_GET['period'] ?? 'all';
$year   = (int)($_GET['year']  ?? date('Y'));
$month  = str_pad((int)($_GET['month'] ?? date('m')), 2, '0', STR_PAD_LEFT);

// ── President info ──
$pres = $conn->query("
    SELECT p.first_name, p.last_name, p.email, p.phone, p.barangay, p.municipality
    FROM presidents p WHERE p.association_id = $association_id LIMIT 1
")->fetch_assoc();
$pres_name  = $pres ? trim(($pres['first_name'] ?? '') . ' ' . ($pres['last_name'] ?? '')) : 'No President Assigned';
$pres_init  = $pres ? strtoupper(substr(trim($pres['first_name'] ?? 'P'), 0, 1)) : 'P';

// ── Escape filter values ──
$esc_year  = $conn->real_escape_string($year);
$esc_month = $conn->real_escape_string($month);

// ── Date condition for payment_transactions (for period_collected) ──
$date_cond_tx = "1=1";
if ($period === 'month') {
    $date_cond_tx = "YEAR(pt.payment_date)='$esc_year' AND MONTH(pt.payment_date)='$esc_month'";
} elseif ($period === 'year') {
    $date_cond_tx = "YEAR(pt.payment_date)='$esc_year'";
}

// ── Date condition for payment_ledger (based on ledger created_at = when booking completed) ──
// This filters which ledger records (billing accounts) fall within the period
$date_cond_pl = "1=1";
if ($period === 'month') {
    $date_cond_pl = "YEAR(pl.created_at)='$esc_year' AND MONTH(pl.created_at)='$esc_month'";
} elseif ($period === 'year') {
    $date_cond_pl = "YEAR(pl.created_at)='$esc_year'";
}

// ── Period collected (sum of actual payments made in the period) ──
$period_collected = 0;
if ($period !== 'all') {
    $period_collected = $conn->query("
        SELECT COALESCE(SUM(pt.amount),0) AS collected
        FROM payment_transactions pt
        JOIN payment_ledger pl ON pt.ledger_id = pl.id
        WHERE pl.association_id = $association_id AND $date_cond_tx
    ")->fetch_assoc()['collected'];
}

// ── Main payment summary — filtered by period ──
$summary = $conn->query("
    SELECT
        COUNT(DISTINCT pl.id)  AS total_accounts,
        COALESCE(SUM(pl.total_amount),0)  AS total_billed,
        COALESCE(SUM(pl.amount_paid),0)   AS total_collected,
        COALESCE(SUM(pl.balance),0)       AS total_outstanding,
        SUM(CASE WHEN pl.payment_status='Paid'    THEN 1 ELSE 0 END) AS paid_count,
        SUM(CASE WHEN pl.payment_status='Partial' THEN 1 ELSE 0 END) AS partial_count,
        SUM(CASE WHEN pl.payment_status='Unpaid'  THEN 1 ELSE 0 END) AS unpaid_count,
        SUM(CASE WHEN pl.payment_status!='Paid' AND pl.due_date < CURDATE() THEN 1 ELSE 0 END) AS overdue_count,
        COALESCE(SUM(CASE WHEN pl.payment_status!='Paid' AND pl.due_date < CURDATE() THEN pl.balance ELSE 0 END),0) AS overdue_amount
    FROM payment_ledger pl
    WHERE pl.association_id = $association_id AND $date_cond_pl
")->fetch_assoc();

$pct         = $summary['total_billed'] > 0 ? round(($summary['total_collected'] / $summary['total_billed']) * 100, 1) : 0;
$occ_rate    = $summary['total_accounts'] > 0 ? round(($summary['paid_count'] / $summary['total_accounts']) * 100) : 0;
$active_rate = $summary['total_accounts'] > 0
    ? round((($summary['partial_count'] + $summary['paid_count']) / $summary['total_accounts']) * 100) : 0;

// ── Counts (always all-time for resources strip) ──
$machine_count  = (int)$conn->query("SELECT COUNT(*) AS c FROM machines  WHERE association_id=$association_id AND status='Active'")->fetch_assoc()['c'];
$operator_count = (int)$conn->query("SELECT COUNT(*) AS c FROM operators WHERE association_id=$association_id AND status='Active'")->fetch_assoc()['c'];
$farmer_count   = (int)$conn->query("SELECT COUNT(DISTINCT farmer_id) AS c FROM payment_ledger WHERE association_id=$association_id AND $date_cond_pl")->fetch_assoc()['c'];
$booking_count  = (int)$conn->query("SELECT COUNT(*) AS c FROM bookings b JOIN machines m ON b.machine_id=m.id WHERE m.association_id=$association_id")->fetch_assoc()['c'];

// ── Operator compensation ──
$operator_dues = $conn->query("
    SELECT
        o.id AS op_id,
        o.name AS op_name,
        o.phone AS op_phone,
        m.machine_name,
        m.type AS machine_type,
        m.operator_rate_per_hectare,
        COALESCE(SUM(CASE WHEN b.status='Completed' THEN COALESCE(fl.farm_size, b.farm_size, 0) ELSE 0 END), 0) AS total_hectares,
        COALESCE(SUM(CASE WHEN b.status='Completed' THEN COALESCE(fl.farm_size, b.farm_size, 0) * m.operator_rate_per_hectare ELSE 0 END), 0) AS total_earned
    FROM operators o
    LEFT JOIN machine_operators mo ON mo.operator_id = o.id AND mo.status = 'Active'
    LEFT JOIN machines m ON m.id = mo.machine_id AND m.association_id = $association_id
    LEFT JOIN bookings b ON b.machine_id = m.id
    LEFT JOIN farmer_lots fl ON fl.id = b.lot_id
    WHERE o.association_id = $association_id AND o.status = 'Active'
    GROUP BY o.id, m.id
    ORDER BY total_earned DESC
");
$operator_rows = [];
$total_operator_due = 0;
while ($r = $operator_dues->fetch_assoc()) {
    $operator_rows[] = $r;
    $total_operator_due += (float)$r['total_earned'];
}

// ── Operator payments already paid out ──
$op_paid_map = [];
$op_paid_res = $conn->query("
    SELECT operator_id, COALESCE(SUM(total_amount),0) AS paid_out
    FROM operator_payments
    WHERE association_id=$association_id AND payment_status='Paid'
    GROUP BY operator_id
");
while ($r = $op_paid_res->fetch_assoc()) {
    $op_paid_map[$r['operator_id']] = (float)$r['paid_out'];
}
$total_op_paid_out = array_sum($op_paid_map);

// ── Farmer summary — filtered by period ──
$farmers_summary = $conn->query("
    SELECT
        CONCAT(f.first_name,' ',COALESCE(f.middle_name,''),' ',f.last_name) AS farmer_name,
        f.phone, f.barangay, f.municipality,
        SUM(pl.total_amount) AS billed,
        SUM(pl.amount_paid)  AS collected,
        SUM(pl.balance)      AS outstanding,
        MAX(CASE WHEN pl.payment_status!='Paid' AND pl.due_date < CURDATE() THEN 1 ELSE 0 END) AS has_overdue,
        MAX(pl.payment_status) AS pay_status,
        COUNT(pl.id) AS account_count
    FROM payment_ledger pl
    JOIN farmers f ON pl.farmer_id = f.id
    WHERE pl.association_id = $association_id AND $date_cond_pl
    GROUP BY f.id ORDER BY outstanding DESC
");
$farmers_rows = [];
while ($r = $farmers_summary->fetch_assoc()) $farmers_rows[] = $r;

// ── Recent transactions — filtered by period ──
$tx_period_cond = "1=1";
if ($period === 'month') {
    $tx_period_cond = "YEAR(pt.payment_date)='$esc_year' AND MONTH(pt.payment_date)='$esc_month'";
} elseif ($period === 'year') {
    $tx_period_cond = "YEAR(pt.payment_date)='$esc_year'";
}

$recent_tx = $conn->query("
    SELECT pt.or_number, pt.amount, pt.payment_date,
           CONCAT(f.first_name,' ',f.last_name) AS farmer_name,
           m.machine_name, m.type
    FROM payment_transactions pt
    JOIN payment_ledger pl ON pt.ledger_id = pl.id
    JOIN farmers f ON pl.farmer_id = f.id
    JOIN machines m ON pl.machine_id = m.id
    WHERE pl.association_id = $association_id AND $tx_period_cond
    ORDER BY pt.created_at DESC LIMIT 8
");
$recent_rows = [];
while ($r = $recent_tx->fetch_assoc()) $recent_rows[] = $r;

// ── Machine breakdown ──
$machines_res = $conn->query("
    SELECT m.machine_name, m.type, m.price_per_hectare, m.operator_rate_per_hectare, m.status,
           COUNT(b.id) AS total_bookings,
           SUM(CASE WHEN b.status='Completed' THEN 1 ELSE 0 END) AS completed,
           COALESCE(SUM(CASE WHEN b.status='Completed' THEN COALESCE(fl.farm_size,b.farm_size,0) ELSE 0 END),0) AS total_ha
    FROM machines m
    LEFT JOIN bookings b ON b.machine_id = m.id
    LEFT JOIN farmer_lots fl ON fl.id = b.lot_id
    WHERE m.association_id = $association_id
    GROUP BY m.id ORDER BY completed DESC
");
$machines_rows = [];
while ($r = $machines_res->fetch_assoc()) $machines_rows[] = $r;

// ── Period label helper ──
function periodLabel($period, $month, $year) {
    if ($period === 'month') return date('F Y', mktime(0,0,0,(int)$month,1,$year));
    if ($period === 'year')  return "Year $year";
    return "All Time";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Financial Summary — <?= htmlspecialchars($association_name) ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=EB+Garamond:wght@400;500;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
/* ══════════════════════════════════════
   RESET & BASE
══════════════════════════════════════ */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --gold:        #9a7b1e;
    --gold-mid:    #b8940a;
    --gold-light:  #f0e4b0;
    --gold-bg:     #fdf8ec;
    --ink:         #1c1c1c;
    --ink2:        #3a3a3a;
    --ink3:        #6e6e6e;
    --ink4:        #9a9a9a;
    --white:       #ffffff;
    --surf:        #f6f4f0;
    --surf2:       #eeebe4;
    --surf3:       #e4e0d8;
    --border:      #ddd9d1;
    --border2:     #ccc8c0;
    --green:       #1a6b3a;
    --green-bg:    #eaf6f0;
    --green-bd:    #b2d9c4;
    --red:         #8b1a1a;
    --red-bg:      #fdf0f0;
    --red-bd:      #e0b8b8;
    --blue:        #0f3d6b;
    --blue-bg:     #eef4fb;
    --blue-bd:     #b5cfe8;
    --amber:       #7a4700;
    --amber-bg:    #fef4e4;
    --amber-bd:    #f5d499;
}

body {
    font-family: 'DM Sans', sans-serif;

    overflow: hidden;
}

/* ── SCROLL WRAP ── */
.fs-wrap {
    position: fixed;
    top: 120px; left: 0; right: 0; bottom: 0;
    overflow-y: scroll; overflow-x: hidden;
}
.fs-inner {
    max-width: 1200px;
    margin: 0 auto;
    padding: 28px 28px 80px;
}

/* ══════════════════════════════════════
   HOTEL HEADER BANNER
══════════════════════════════════════ */
.hotel-banner {
    background: var(--white);
    border: 1px solid var(--border);
    border-top: 4px solid var(--gold-mid);
    border-radius: 4px;
    padding: 28px 32px 24px;
    margin-bottom: 20px;
    display: flex; align-items: flex-start;
    justify-content: space-between; gap: 24px;
    flex-wrap: wrap;
}
.hb-left { display: flex; align-items: center; gap: 18px; }
.hb-crest {
    width: 62px; height: 62px;
    border: 2px solid var(--gold-mid);
    border-radius: 50%;
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    gap: 0; flex-shrink: 0; background: var(--gold-bg);
}
.hb-crest-star { font-size: 18px; color: var(--gold-mid); line-height: 1; }
.hb-crest-text { font-size: 7px; letter-spacing: 1.5px; color: var(--gold); text-transform: uppercase; font-family: 'EB Garamond', serif; font-weight: 600; }
.hb-welcome { font-size: 11px; color: var(--gold); letter-spacing: 2px; text-transform: uppercase; font-family: 'EB Garamond', serif; margin-bottom: 3px; }
.hb-title   { font-family: 'EB Garamond', serif; font-size: 28px; font-weight: 700; color: var(--ink); letter-spacing: -0.3px; line-height: 1.1; }
.hb-sub     { font-size: 10px; color: var(--gold); letter-spacing: 3px; text-transform: uppercase; margin-top: 4px; font-family: 'EB Garamond', serif; }
.hb-right { text-align: right; }
.hb-date  { font-size: 12px; color: var(--ink3); letter-spacing: 1px; font-family: 'EB Garamond', serif; }
.hb-period { font-size: 10px; color: var(--gold); text-transform: uppercase; letter-spacing: 2px; margin-top: 3px; }
.hb-actions { display: flex; gap: 10px; margin-top: 10px; justify-content: flex-end; flex-wrap: wrap; }

.btn-hb {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 8px 18px; border-radius: 3px;
    font-size: 12px; font-weight: 600;
    cursor: pointer; border: none;
    text-decoration: none; transition: all .18s;
    font-family: 'DM Sans', sans-serif; letter-spacing: 0.3px;
}
.btn-hb-outline {
    background: transparent;
    color: var(--ink2);
    border: 1px solid var(--border2);
}
.btn-hb-outline:hover { background: var(--surf2); }
.btn-hb-gold {
    background: var(--gold-mid);
    color: #fff; font-weight: 700;
}
.btn-hb-gold:hover { background: var(--gold); }

.hb-divider { width: 100%; height: 1px; background: var(--border); margin: 20px 0 16px; }

/* President strip */
.president-strip {
    display: flex; align-items: center; gap: 14px; flex-wrap: wrap;
}
.pres-avatar {
    width: 46px; height: 46px; border-radius: 50%;
    background: var(--gold-bg); border: 2px solid var(--gold-mid);
    display: flex; align-items: center; justify-content: center;
    font-family: 'EB Garamond', serif; font-size: 18px;
    font-weight: 700; color: var(--gold); flex-shrink: 0;
}
.pres-role  { font-size: 9px; text-transform: uppercase; letter-spacing: 2px; color: var(--ink4); }
.pres-name  { font-size: 15px; font-weight: 600; color: var(--ink); margin-top: 2px; }
.pres-email { font-size: 11px; color: var(--gold); margin-top: 2px; }
.badge-active {
    display: inline-flex; align-items: center; gap: 5px;
    background: var(--green-bg); color: var(--green);
    font-size: 10px; font-weight: 700; padding: 4px 12px;
    border-radius: 3px; border: 1px solid var(--green-bd);
    letter-spacing: 1px; text-transform: uppercase;
    margin-left: auto;
}
.pulse-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--green); }

.loc-badge {
    display: inline-flex; align-items: center; gap: 6px;
    background: var(--surf2); border: 1px solid var(--border);
    color: var(--ink3); font-size: 11px;
    padding: 4px 12px; border-radius: 3px;
}

/* ══════════════════════════════════════
   PERIOD INDICATOR PILL (in banner)
══════════════════════════════════════ */
.period-pill {
    display: inline-flex; align-items: center; gap: 6px;
    background: var(--gold-bg); border: 1px solid var(--amber-bd);
    color: var(--amber); font-size: 10px; font-weight: 700;
    padding: 4px 12px; border-radius: 3px; letter-spacing: 1px;
    text-transform: uppercase;
}

/* ══════════════════════════════════════
   SECTION LABELS
══════════════════════════════════════ */
.section-label {
    font-size: 9px; letter-spacing: 3px; text-transform: uppercase;
    color: var(--ink3); font-family: 'EB Garamond', serif;
    margin-bottom: 12px;
    padding-left: 10px;
    border-left: 2px solid var(--gold-mid);
    display: flex; align-items: center; justify-content: space-between;
}
.ornament { text-align: center; font-size: 14px; color: var(--gold); margin: 4px 0 14px; }

/* ══════════════════════════════════════
   FILTER BAR
══════════════════════════════════════ */
.filter-bar {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: 4px;
    padding: 13px 20px;
    margin-bottom: 20px;
    display: flex; align-items: center; gap: 14px; flex-wrap: wrap;
}
.filter-bar label {
    font-size: 10px; font-weight: 600; color: var(--ink3);
    text-transform: uppercase; letter-spacing: 1.5px;
}
.filter-bar select {
    padding: 7px 12px; font-size: 12px;
    background: var(--surf); color: var(--ink);
    border: 1px solid var(--border2); border-radius: 3px;
    cursor: pointer; font-family: 'DM Sans', sans-serif;
    min-width: 140px;
}
.filter-bar select:focus { outline: none; border-color: var(--gold-mid); }
.btn-filter {
    padding: 7px 18px;
    background: var(--gold-mid); color: #fff;
    border: none; border-radius: 3px;
    font-size: 12px; font-weight: 600; cursor: pointer;
    font-family: 'DM Sans', sans-serif;
}
.btn-filter:hover { background: var(--gold); }

/* Period highlight banner */
.period-highlight {
    background: var(--green-bg); border: 1px solid var(--green-bd);
    border-radius: 4px; padding: 13px 20px;
    margin-bottom: 20px;
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 10px;
}
.ph-left  { display: flex; flex-direction: column; gap: 3px; }
.ph-label { font-size: 12px; color: var(--green); font-weight: 600; }
.ph-sub   { font-size: 10px; color: var(--ink3); }
.ph-amount { font-family: 'EB Garamond', serif; font-size: 28px; font-weight: 700; color: var(--green); }

/* ══════════════════════════════════════
   MAIN STAT CARDS
══════════════════════════════════════ */
.stat-row {
    display: grid; grid-template-columns: repeat(4, 1fr);
    gap: 14px; margin-bottom: 20px;
}
.stat-card {
    background: var(--white); border: 1px solid var(--border);
    border-radius: 4px; padding: 22px 20px;
    position: relative; overflow: hidden;
    transition: box-shadow .2s;
}
.stat-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.08); }
.stat-card::after {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
    border-radius: 4px 4px 0 0;
}
.stat-card.sc-gold::after  { background: var(--gold-mid); }
.stat-card.sc-green::after { background: var(--green); }
.stat-card.sc-red::after   { background: var(--red); }
.stat-card.sc-blue::after  { background: var(--blue); }

.stat-icon {
    width: 36px; height: 36px; border-radius: 3px;
    display: flex; align-items: center; justify-content: center;
    font-size: 15px; margin-bottom: 14px;
}
.sc-gold  .stat-icon { background: var(--gold-bg);  color: var(--gold-mid); }
.sc-green .stat-icon { background: var(--green-bg); color: var(--green); }
.sc-red   .stat-icon { background: var(--red-bg);   color: var(--red); }
.sc-blue  .stat-icon { background: var(--blue-bg);  color: var(--blue); }

.stat-label { font-size: 9px; color: var(--ink3); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 6px; }
.stat-value { font-family: 'EB Garamond', serif; font-size: 30px; font-weight: 700; color: var(--ink); line-height: 1; }
.stat-sub   { font-size: 11px; color: var(--ink3); margin-top: 6px; }
.stat-period-tag {
    display: inline-block; font-size: 8px; color: var(--gold);
    background: var(--gold-bg); border: 1px solid var(--amber-bd);
    padding: 1px 6px; border-radius: 2px; margin-top: 5px;
    letter-spacing: 0.5px; text-transform: uppercase;
}

/* ══════════════════════════════════════
   RING METRICS ROW
══════════════════════════════════════ */
.metrics-row {
    display: grid; grid-template-columns: repeat(3, 1fr);
    gap: 14px; margin-bottom: 20px;
}
.metric-card {
    background: var(--white); border: 1px solid var(--border);
    border-radius: 4px; padding: 20px;
    display: flex; align-items: center; gap: 16px;
}
.metric-ring { position: relative; width: 64px; height: 64px; flex-shrink: 0; }
.metric-ring svg { transform: rotate(-90deg); }
.ring-bg   { fill: none; stroke: var(--surf3); stroke-width: 6; }
.ring-fill { fill: none; stroke-width: 6; stroke-linecap: round; }
.ring-fill.rg { stroke: var(--gold-mid); }
.ring-fill.rr { stroke: var(--green); }
.ring-fill.rb { stroke: var(--blue); }
.ring-pct {
    position: absolute; inset: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 12px; font-weight: 700; color: var(--ink);
    font-family: 'EB Garamond', serif;
}
.metric-name { font-size: 10px; color: var(--ink3); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px; }
.metric-val  { font-size: 17px; font-weight: 700; color: var(--ink); font-family: 'EB Garamond', serif; }
.metric-desc { font-size: 11px; color: var(--ink3); margin-top: 2px; }

/* ══════════════════════════════════════
   COLLECTION PROGRESS BAR
══════════════════════════════════════ */
.progress-card {
    background: var(--white); border: 1px solid var(--border);
    border-radius: 4px; padding: 18px 22px; margin-bottom: 20px;
}
.pc-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
.pc-title  { font-size: 12px; font-weight: 600; color: var(--ink); }
.pc-pct    { font-family: 'EB Garamond', serif; font-size: 22px; font-weight: 700; color: var(--gold-mid); }
.pc-track  { height: 8px; background: var(--surf3); border-radius: 4px; overflow: hidden; }
.pc-fill   { height: 100%; border-radius: 4px; background: var(--gold-mid); transition: width .8s ease; }
.pc-marks  { display: flex; justify-content: space-between; margin-top: 7px; font-size: 10px; color: var(--ink4); }

/* ══════════════════════════════════════
   RESOURCES STRIP
══════════════════════════════════════ */
.resources-strip {
    display: grid; grid-template-columns: repeat(4, 1fr);
    gap: 0; background: var(--white); border: 1px solid var(--border);
    border-radius: 4px; overflow: hidden; margin-bottom: 20px;
}
.res-item {
    padding: 16px 18px; border-right: 1px solid var(--border);
    display: flex; align-items: center; gap: 12px;
}
.res-item:last-child { border-right: none; }
.res-icon {
    width: 38px; height: 38px; border-radius: 3px;
    display: flex; align-items: center; justify-content: center;
    font-size: 16px; flex-shrink: 0;
}
.res-icon.ri-gold  { background: var(--gold-bg);  color: var(--gold-mid); }
.res-icon.ri-green { background: var(--green-bg); color: var(--green); }
.res-icon.ri-blue  { background: var(--blue-bg);  color: var(--blue); }
.res-icon.ri-amber { background: var(--amber-bg); color: var(--amber); }
.res-label { font-size: 9px; text-transform: uppercase; letter-spacing: 1px; color: var(--ink4); margin-bottom: 2px; }
.res-val   { font-family: 'EB Garamond', serif; font-size: 22px; font-weight: 700; color: var(--ink); }

/* ══════════════════════════════════════
   PANEL (generic card)
══════════════════════════════════════ */
.panel {
    background: var(--white); border: 1px solid var(--border); border-radius: 4px;
}
.panel-head {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 18px; border-bottom: 1px solid var(--border);
    background: var(--surf);
}
.panel-head h3 {
    font-size: 12px; font-weight: 700; color: var(--ink);
    display: flex; align-items: center; gap: 7px;
}
.panel-head h3 i { color: var(--gold-mid); }
.panel-badge {
    background: var(--surf2); border: 1px solid var(--border);
    font-size: 9px; font-weight: 700; color: var(--ink3);
    padding: 3px 9px; border-radius: 3px; letter-spacing: 1px; text-transform: uppercase;
}
.panel-body { padding: 14px 18px; }

/* ══════════════════════════════════════
   MACHINE ROWS
══════════════════════════════════════ */
.machine-grid {
    display: grid; grid-template-columns: 1fr 1fr; gap: 10px;
}
.machine-row {
    background: var(--surf); border: 1px solid var(--border);
    border-radius: 3px; padding: 12px 14px;
}
.machine-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
.machine-name { font-size: 13px; font-weight: 700; color: var(--ink); }
.machine-type-badge {
    font-size: 9px; padding: 2px 8px; border-radius: 3px; font-weight: 700;
    letter-spacing: 0.5px; text-transform: uppercase;
}
.type-tractor   { background: var(--gold-bg); color: var(--gold); border: 1px solid var(--amber-bd); }
.type-harvester { background: var(--blue-bg); color: var(--blue); border: 1px solid var(--blue-bd); }
.machine-stats { display: flex; gap: 14px; font-size: 11px; color: var(--ink3); }
.machine-stat-val { font-weight: 700; color: var(--ink2); }
.machine-status {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 9px; font-weight: 700; padding: 2px 8px;
    border-radius: 3px; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 8px;
}
.ms-active   { background: var(--green-bg); color: var(--green); border: 1px solid var(--green-bd); }
.ms-inactive { background: var(--red-bg);   color: var(--red);   border: 1px solid var(--red-bd); }
.ms-maint    { background: var(--amber-bg); color: var(--amber); border: 1px solid var(--amber-bd); }

/* ══════════════════════════════════════
   OPERATOR LEDGER — COMPACT
══════════════════════════════════════ */
.op-row {
    padding: 8px 0;
    border-bottom: 1px solid var(--surf3);
    display: flex; align-items: center; gap: 10px;
}
.op-row:last-child { border-bottom: none; }
.op-avatar {
    width: 32px; height: 32px; border-radius: 50%;
    background: var(--gold-bg); border: 1.5px solid var(--gold-light);
    display: flex; align-items: center; justify-content: center;
    font-size: 11px; font-weight: 700; color: var(--gold);
    flex-shrink: 0; font-family: 'EB Garamond', serif;
}
.op-name    { font-size: 12px; font-weight: 600; color: var(--ink); }
.op-detail  { font-size: 9px; color: var(--ink3); margin-top: 1px; }
.op-right   { margin-left: auto; text-align: right; flex-shrink: 0; }
.op-earned  { font-size: 13px; font-weight: 700; color: var(--gold); font-family: 'EB Garamond', serif; }
.op-paid    { font-size: 10px; color: var(--green); font-weight: 600; margin-top: 1px; }
.op-balance { font-size: 10px; color: var(--ink3); margin-top: 0; }
.op-pill {
    display: inline-block; padding: 2px 7px; border-radius: 3px;
    font-size: 8px; font-weight: 700; letter-spacing: 0.8px;
    text-transform: uppercase; border: 1px solid; margin-top: 3px;
}
.op-pill-paid    { color: var(--green); background: var(--green-bg); border-color: var(--green-bd); }
.op-pill-pending { color: var(--amber); background: var(--amber-bg); border-color: var(--amber-bd); }
.op-pill-noduty  { color: var(--ink3);  background: var(--surf2);    border-color: var(--border); }
.op-bar { height: 2px; background: var(--surf3); border-radius: 2px; overflow: hidden; margin-top: 4px; }
.op-bar-fill { height: 100%; border-radius: 2px; background: var(--gold-mid); }

/* Operator total footer */
.op-total-bar {
    margin-top: 10px; padding: 10px 14px;
    background: var(--gold-bg); border: 1px solid var(--amber-bd);
    border-radius: 3px; display: flex;
    justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;
}
.op-total-left { font-size: 11px; color: var(--amber); font-weight: 700; }
.op-total-vals { display: flex; gap: 20px; }
.op-total-item { text-align: right; }
.op-total-lbl  { font-size: 9px; color: var(--ink4); text-transform: uppercase; letter-spacing: 1px; }
.op-total-val  { font-family: 'EB Garamond', serif; font-size: 15px; font-weight: 700; }
.op-total-val.earned  { color: var(--gold); }
.op-total-val.paid-v  { color: var(--green); }
.op-total-val.balance { color: var(--red); }

/* info-note compact */
.info-note {
    font-size: 9px; color: var(--ink3); padding: 6px 10px;
    background: var(--surf2); border: 1px solid var(--border);
    border-radius: 3px; margin-bottom: 8px; line-height: 1.5;
}

/* ══════════════════════════════════════
   FARMER ROWS
══════════════════════════════════════ */
.farmer-row {
    padding: 11px 0;
    border-bottom: 1px solid var(--surf3);
    display: flex; align-items: center; gap: 12px;
}
.farmer-row:last-child { border-bottom: none; }
.f-avatar {
    width: 36px; height: 36px; border-radius: 3px;
    background: var(--surf2); border: 1px solid var(--border);
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; font-weight: 700; color: var(--ink2);
    flex-shrink: 0; font-family: 'EB Garamond', serif;
}
.f-name    { font-size: 12px; font-weight: 600; color: var(--ink); }
.f-detail  { font-size: 10px; color: var(--ink3); margin-top: 2px; }
.f-right   { margin-left: auto; text-align: right; }
.f-billed  { font-size: 10px; color: var(--ink3); }
.f-balance { font-size: 14px; font-weight: 700; font-family: 'EB Garamond', serif; }
.f-balance.fc-red   { color: var(--red); }
.f-balance.fc-green { color: var(--green); }
.f-pill {
    display: inline-block; padding: 2px 7px; border-radius: 3px;
    font-size: 9px; font-weight: 700; letter-spacing: 0.5px;
    text-transform: uppercase; border: 1px solid; margin-top: 3px;
}
.fp-green { color: var(--green); background: var(--green-bg); border-color: var(--green-bd); }
.fp-blue  { color: var(--blue);  background: var(--blue-bg);  border-color: var(--blue-bd); }
.fp-amber { color: var(--amber); background: var(--amber-bg); border-color: var(--amber-bd); }
.fp-red   { color: var(--red);   background: var(--red-bg);   border-color: var(--red-bd); }

/* ══════════════════════════════════════
   TRANSACTION ROWS
══════════════════════════════════════ */
.tx-row {
    padding: 11px 0;
    border-bottom: 1px solid var(--surf3);
    display: flex; align-items: center; gap: 11px;
}
.tx-row:last-child { border-bottom: none; }
.tx-icon {
    width: 32px; height: 32px; border-radius: 3px;
    background: var(--blue-bg); border: 1px solid var(--blue-bd);
    display: flex; align-items: center; justify-content: center;
    font-size: 12px; color: var(--blue); flex-shrink: 0;
}
.tx-farmer  { font-size: 12px; font-weight: 600; color: var(--ink); }
.tx-machine { font-size: 10px; color: var(--ink3); margin-top: 1px; }
.tx-or      { font-size: 9px; color: var(--gold); margin-top: 1px; letter-spacing: 0.5px; }
.tx-right   { margin-left: auto; text-align: right; }
.tx-amount  { font-size: 14px; font-weight: 700; color: var(--green); font-family: 'EB Garamond', serif; }
.tx-date    { font-size: 10px; color: var(--ink3); margin-top: 1px; }

/* ══════════════════════════════════════
   MAIN GRID
══════════════════════════════════════ */
.main-grid {
    display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;
}

/* ══════════════════════════════════════
   NO DATA STATE
══════════════════════════════════════ */
.no-data {
    text-align: center; padding: 28px 20px; color: var(--ink4);
}
.no-data i { font-size: 22px; margin-bottom: 8px; display: block; opacity: .4; }
.no-data p { font-size: 12px; }

/* ══════════════════════════════════════
   PRINT
══════════════════════════════════════ */
@media print {
    .fs-wrap { position: static; height: auto; overflow: visible; background: white; }
    body { overflow: visible; background: white; }
    .filter-bar, .hb-actions, .btn-hb { display: none !important; }
    .stat-card:hover { box-shadow: none; }
}
</style>
</head>
<body>
<div class="fs-wrap">
<div class="fs-inner">

  <!-- ══ HOTEL BANNER ══ -->
  <div class="hotel-banner">
    <div class="hb-left">
      <div class="hb-crest">
        <div class="hb-crest-star">✦</div>
        <div class="hb-crest-text"><?= strtoupper(substr($association_name, 0, 3)) ?></div>
      </div>
      <div>
        <div class="hb-welcome">Welcome, <?= htmlspecialchars($logged_in_name) ?></div>
        <div class="hb-title"><?= htmlspecialchars($association_name) ?> Association</div>
        <div style="font-size:13px;color:var(--ink3);margin-top:2px;">Agricultural Machineries Reservation & Monitoring System</div>
        <div class="hb-sub">Financial Performance Report</div>
      </div>
    </div>
    <div class="hb-right">
      <div class="hb-date"><?= date('F j, Y') ?></div>
      <div class="hb-period">
        <?php if ($period === 'month'): ?>
          <?= date('F Y', mktime(0,0,0,(int)$month,1,$year)) ?>
        <?php elseif ($period === 'year'): ?>
          Year <?= $year ?>
        <?php else: ?>
          All Time Report
        <?php endif; ?>
      </div>
      <div class="hb-actions">
        <a href="payment_management.php" class="btn-hb btn-hb-outline">
          <i class="fas fa-arrow-left"></i> Back
        </a>
        <button class="btn-hb btn-hb-gold" onclick="window.print()">
          <i class="fas fa-print"></i> Print Report
        </button>
      </div>
    </div>

    <div class="hb-divider"></div>

    <!-- President strip -->
    <div class="president-strip" style="width:100%;">
      <div class="pres-avatar"><?= $pres_init ?></div>
      <div>
        <div class="pres-role">Association President</div>
        <div class="pres-name"><?= htmlspecialchars($pres_name) ?></div>
        <?php if ($pres && $pres['email']): ?>
          <div class="pres-email"><i class="fas fa-envelope" style="font-size:9px;margin-right:3px;"></i><?= htmlspecialchars($pres['email']) ?></div>
        <?php endif; ?>
      </div>
      <?php if ($assoc_location !== ', , '): ?>
      <div class="loc-badge">
        <i class="fas fa-location-dot" style="font-size:10px;color:var(--gold-mid);"></i>
        <?= htmlspecialchars($assoc_location) ?>
      </div>
      <?php endif; ?>
      <?php if ($period !== 'all'): ?>
      <div class="period-pill">
        <i class="fas fa-calendar-filter" style="font-size:9px;"></i>
        Filtered: <?= periodLabel($period, $month, $year) ?>
      </div>
      <?php endif; ?>
      <div class="badge-active">
        <div class="pulse-dot"></div>
        Active Association
      </div>
    </div>
  </div>


  <!-- ══ FILTER BAR ══ -->
  <form class="filter-bar" method="GET">
    <label>Period:</label>
    <select name="period" id="periodSel" onchange="togglePeriodSelects()">
      <option value="all"   <?= $period==='all'   ?'selected':''?>>All Time</option>
      <option value="month" <?= $period==='month' ?'selected':''?>>Specific Month</option>
      <option value="year"  <?= $period==='year'  ?'selected':''?>>Specific Year</option>
    </select>
    <select name="month" id="monthSel" style="display:none;">
      <?php for ($m=1;$m<=12;$m++):
        $mv=str_pad($m,2,'0',STR_PAD_LEFT); ?>
        <option value="<?= $mv ?>" <?= $month==$mv?'selected':''?>><?= date('F',mktime(0,0,0,$m,1)) ?></option>
      <?php endfor; ?>
    </select>
    <select name="year" id="yearSel" style="display:none;">
      <?php for ($y=date('Y');$y>=2020;$y--): ?>
        <option value="<?= $y ?>" <?= $year==$y?'selected':''?>><?= $y ?></option>
      <?php endfor; ?>
    </select>
    <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply Filter</button>
    <?php if ($period !== 'all'): ?>
      <span style="font-size:11px;color:var(--ink3);margin-left:4px;">
        <i class="fas fa-circle-info" style="color:var(--gold-mid);"></i>
        Showing data for <strong><?= periodLabel($period, $month, $year) ?></strong>
      </span>
    <?php endif; ?>
  </form>

  <!-- Period collected highlight banner -->
  <?php if ($period !== 'all'): ?>
  <div class="period-highlight">
    <div class="ph-left">
      <div class="ph-label">
        <i class="fas fa-calendar-check" style="margin-right:6px;"></i>
        Payments Collected in <?= periodLabel($period, $month, $year) ?>
      </div>
      <div class="ph-sub">Actual cash received within this period (from payment_transactions)</div>
    </div>
    <div class="ph-amount">₱<?= number_format($period_collected, 2) ?></div>
  </div>
  <?php endif; ?>


  <!-- ══ STAT CARDS ══ -->
  <div class="section-label">
    Revenue Summary
    <span style="font-size:9px;color:var(--gold);letter-spacing:1px;"><?= periodLabel($period,$month,$year) ?></span>
  </div>
  <div class="stat-row">
    <div class="stat-card sc-gold">
      <div class="stat-icon"><i class="fas fa-peso-sign"></i></div>
      <div class="stat-label">Total Revenue Billed</div>
      <div class="stat-value">₱<?= number_format($summary['total_billed'], 0) ?></div>
      <div class="stat-sub"><?= $summary['total_accounts'] ?> billing account<?= $summary['total_accounts']!=1?'s':'' ?></div>
      <?php if ($period !== 'all'): ?><div class="stat-period-tag"><?= periodLabel($period,$month,$year) ?></div><?php endif; ?>
    </div>
    <div class="stat-card sc-green">
      <div class="stat-icon"><i class="fas fa-circle-check"></i></div>
      <div class="stat-label">Total Collected</div>
      <div class="stat-value">₱<?= number_format($summary['total_collected'], 0) ?></div>
      <div class="stat-sub"><?= $pct ?>% collection rate</div>
      <?php if ($period !== 'all'): ?><div class="stat-period-tag"><?= periodLabel($period,$month,$year) ?></div><?php endif; ?>
    </div>
    <div class="stat-card sc-red">
      <div class="stat-icon"><i class="fas fa-clock-rotate-left"></i></div>
      <div class="stat-label">Outstanding Balance</div>
      <div class="stat-value">₱<?= number_format($summary['total_outstanding'], 0) ?></div>
      <div class="stat-sub">
        <?= $summary['overdue_count'] > 0
          ? $summary['overdue_count'].' overdue account'.($summary['overdue_count']!=1?'s':'')
          : 'No overdue accounts' ?>
      </div>
      <?php if ($period !== 'all'): ?><div class="stat-period-tag"><?= periodLabel($period,$month,$year) ?></div><?php endif; ?>
    </div>
    <div class="stat-card sc-blue">
      <div class="stat-icon"><i class="fas fa-triangle-exclamation"></i></div>
      <div class="stat-label">Overdue Amount</div>
      <div class="stat-value">₱<?= number_format($summary['overdue_amount'], 0) ?></div>
      <div class="stat-sub"><?= $summary['overdue_count'] ?> account<?= $summary['overdue_count']!=1?'s':'' ?> past due</div>
      <?php if ($period !== 'all'): ?><div class="stat-period-tag"><?= periodLabel($period,$month,$year) ?></div><?php endif; ?>
    </div>
  </div>


  <!-- ══ RING METRICS ══ -->
  <?php
    $circ      = 2 * M_PI * 27;
    $dash_pct  = $circ - ($circ * min($pct, 100) / 100);
    $dash_occ  = $circ - ($circ * $occ_rate / 100);
    $dash_act  = $circ - ($circ * $active_rate / 100);
  ?>
  <div class="metrics-row">
    <div class="metric-card">
      <div class="metric-ring">
        <svg width="64" height="64" viewBox="0 0 64 64">
          <circle class="ring-bg" cx="32" cy="32" r="27"/>
          <circle class="ring-fill rg" cx="32" cy="32" r="27"
            style="stroke-dasharray:<?= $circ ?>;stroke-dashoffset:<?= $dash_pct ?>"/>
        </svg>
        <div class="ring-pct"><?= $pct ?>%</div>
      </div>
      <div>
        <div class="metric-name">Collection Rate</div>
        <div class="metric-val">₱<?= number_format($summary['total_collected'], 0) ?></div>
        <div class="metric-desc">of ₱<?= number_format($summary['total_billed'], 0) ?> billed</div>
      </div>
    </div>
    <div class="metric-card">
      <div class="metric-ring">
        <svg width="64" height="64" viewBox="0 0 64 64">
          <circle class="ring-bg" cx="32" cy="32" r="27"/>
          <circle class="ring-fill rr" cx="32" cy="32" r="27"
            style="stroke-dasharray:<?= $circ ?>;stroke-dashoffset:<?= $dash_occ ?>"/>
        </svg>
        <div class="ring-pct"><?= $occ_rate ?>%</div>
      </div>
      <div>
        <div class="metric-name">Fully Paid Rate</div>
        <div class="metric-val"><?= $summary['paid_count'] ?> / <?= $summary['total_accounts'] ?></div>
        <div class="metric-desc">accounts fully settled</div>
      </div>
    </div>
    <div class="metric-card">
      <div class="metric-ring">
        <svg width="64" height="64" viewBox="0 0 64 64">
          <circle class="ring-bg" cx="32" cy="32" r="27"/>
          <circle class="ring-fill rb" cx="32" cy="32" r="27"
            style="stroke-dasharray:<?= $circ ?>;stroke-dashoffset:<?= $dash_act ?>"/>
        </svg>
        <div class="ring-pct"><?= $active_rate ?>%</div>
      </div>
      <div>
        <div class="metric-name">Active Accounts</div>
        <div class="metric-val"><?= ($summary['partial_count'] + $summary['paid_count']) ?> / <?= $summary['total_accounts'] ?></div>
        <div class="metric-desc">paid or partial</div>
      </div>
    </div>
  </div>


  <!-- ══ PROGRESS BAR ══ -->
  <div class="progress-card">
    <div class="pc-header">
      <div class="pc-title">
        <i class="fas fa-chart-line" style="color:var(--gold-mid);margin-right:6px;"></i>
        Overall Collection Progress
        <?php if ($period !== 'all'): ?>
          <span style="font-size:10px;font-weight:400;color:var(--ink3);margin-left:8px;">— <?= periodLabel($period,$month,$year) ?></span>
        <?php endif; ?>
      </div>
      <div class="pc-pct"><?= $pct ?>% Collected</div>
    </div>
    <div class="pc-track">
      <div class="pc-fill" style="width:<?= min($pct, 100) ?>%;"></div>
    </div>
    <div class="pc-marks">
      <span>₱0</span>
      <span>₱<?= number_format($summary['total_billed'] / 2, 0) ?></span>
      <span>₱<?= number_format($summary['total_billed'], 0) ?></span>
    </div>
  </div>


  <!-- ══ RESOURCES STRIP ══ -->
  <div class="resources-strip" style="margin-bottom:20px;">
    <div class="res-item">
      <div class="res-icon ri-gold"><i class="fas fa-tractor"></i></div>
      <div>
        <div class="res-label">Active Machines</div>
        <div class="res-val"><?= $machine_count ?></div>
      </div>
    </div>
    <div class="res-item">
      <div class="res-icon ri-green"><i class="fas fa-user-gear"></i></div>
      <div>
        <div class="res-label">Active Operators</div>
        <div class="res-val"><?= $operator_count ?></div>
      </div>
    </div>
    <div class="res-item">
      <div class="res-icon ri-blue"><i class="fas fa-users"></i></div>
      <div>
        <div class="res-label">Farmers<?= $period!=='all'?' (Period)':'' ?></div>
        <div class="res-val"><?= $farmer_count ?></div>
      </div>
    </div>
    <div class="res-item">
      <div class="res-icon ri-amber"><i class="fas fa-calendar-check"></i></div>
      <div>
        <div class="res-label">Total Bookings</div>
        <div class="res-val"><?= $booking_count ?></div>
      </div>
    </div>
  </div>


  <!-- ══ OPERATOR COMPENSATION LEDGER ══ -->
  <div class="ornament">✦</div>
  <div class="section-label">Operator Compensation Ledger</div>
  <div class="panel" style="margin-bottom:20px;">
    <div class="panel-head">
      <h3><i class="fas fa-user-gear"></i> Operator Payment Dues</h3>
      <span class="panel-badge"><?= $operator_count ?> active operator<?= $operator_count!=1?'s':'' ?></span>
    </div>
    <div class="panel-body">
      <div class="info-note">
        <i class="fas fa-circle-info" style="color:var(--gold-mid);margin-right:5px;"></i>
        Operator dues = <strong>Completed bookings</strong> × <strong>operator_rate_per_hectare</strong>.
        Rate ₱0.00 = no compensation generated.
      </div>

      <?php if (!empty($operator_rows)): ?>
        <?php foreach ($operator_rows as $r):
          $op_id     = $r['op_id'];
          $earned    = (float)$r['total_earned'];
          $paid_out  = $op_paid_map[$op_id] ?? 0;
          $remaining = $earned - $paid_out;
          $bar_pct   = $earned > 0 ? min(round(($paid_out / $earned) * 100), 100) : 0;
          $initials  = implode('', array_map(fn($w)=>strtoupper(substr($w,0,1)), array_filter(explode(' ', $r['op_name']))));
          $initials  = substr($initials, 0, 2);

          if ($earned <= 0)        { $pill_class = 'op-pill-noduty'; $pill_text = 'No Dues'; }
          elseif ($remaining <= 0) { $pill_class = 'op-pill-paid';    $pill_text = 'Paid Out'; }
          else                     { $pill_class = 'op-pill-pending';  $pill_text = 'Pending'; }
        ?>
        <div class="op-row">
          <div class="op-avatar"><?= $initials ?></div>
          <div style="flex:1;min-width:0;">
            <div class="op-name"><?= htmlspecialchars($r['op_name']) ?></div>
            <div class="op-detail">
              <?php if ($r['machine_name']): ?>
                <i class="fas fa-tractor" style="font-size:8px;margin-right:2px;"></i>
                <?= htmlspecialchars($r['machine_name']) ?>
                (<?= htmlspecialchars($r['machine_type'] ?? '') ?>) ·
                ₱<?= number_format($r['operator_rate_per_hectare'], 2) ?>/ha ·
                <?= number_format($r['total_hectares'], 2) ?> ha
              <?php else: ?>
                No machine assigned
              <?php endif; ?>
            </div>
            <div class="op-bar">
              <div class="op-bar-fill" style="width:<?= $bar_pct ?>%;"></div>
            </div>
          </div>
          <div class="op-right">
            <div class="op-earned">₱<?= number_format($earned, 2) ?></div>
            <div class="op-paid">Paid: ₱<?= number_format($paid_out, 2) ?></div>
            <div class="op-balance">Bal: ₱<?= number_format(max($remaining, 0), 2) ?></div>
            <span class="op-pill <?= $pill_class ?>"><?= $pill_text ?></span>
          </div>
        </div>
        <?php endforeach; ?>

        <div class="op-total-bar">
          <div class="op-total-left"><i class="fas fa-calculator" style="margin-right:5px;"></i>Operator Compensation Totals</div>
          <div class="op-total-vals">
            <div class="op-total-item">
              <div class="op-total-lbl">Total Earned</div>
              <div class="op-total-val earned">₱<?= number_format($total_operator_due, 2) ?></div>
            </div>
            <div class="op-total-item">
              <div class="op-total-lbl">Paid Out</div>
              <div class="op-total-val paid-v">₱<?= number_format($total_op_paid_out, 2) ?></div>
            </div>
            <div class="op-total-item">
              <div class="op-total-lbl">Still Owed</div>
              <div class="op-total-val balance">₱<?= number_format(max($total_operator_due - $total_op_paid_out, 0), 2) ?></div>
            </div>
          </div>
        </div>
      <?php else: ?>
        <div class="no-data"><i class="fas fa-user-gear"></i><p>No operators found for this association.</p></div>
      <?php endif; ?>
    </div>
  </div>


  <!-- ══ MACHINES BREAKDOWN ══ -->
  <div class="section-label">Machine Fleet</div>
  <div class="panel" style="margin-bottom:20px;">
    <div class="panel-head">
      <h3><i class="fas fa-tractor"></i> Machine Performance</h3>
      <span class="panel-badge"><?= count($machines_rows) ?> machine<?= count($machines_rows)!=1?'s':'' ?></span>
    </div>
    <div class="panel-body">
      <?php if (!empty($machines_rows)): ?>
        <div class="machine-grid">
          <?php foreach ($machines_rows as $m):
            $status_class = $m['status']==='Active' ? 'ms-active' : ($m['status']==='Inactive' ? 'ms-inactive' : 'ms-maint');
            $type_class   = $m['type']==='Tractor'  ? 'type-tractor' : 'type-harvester';
          ?>
          <div class="machine-row">
            <div class="machine-header">
              <div class="machine-name"><?= htmlspecialchars($m['machine_name']) ?></div>
              <span class="machine-type-badge <?= $type_class ?>"><?= htmlspecialchars($m['type']) ?></span>
            </div>
            <div class="machine-stats">
              <div><span class="machine-stat-val"><?= $m['completed'] ?></span> completed</div>
              <div><span class="machine-stat-val"><?= number_format($m['total_ha'], 2) ?> ha</span> served</div>
              <div><span class="machine-stat-val">₱<?= number_format($m['price_per_hectare'], 0) ?></span>/ha</div>
            </div>
            <div>
              <span class="machine-status <?= $status_class ?>">
                <i class="fas fa-circle" style="font-size:5px;"></i>
                <?= htmlspecialchars($m['status']) ?>
              </span>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="no-data"><i class="fas fa-tractor"></i><p>No machines found.</p></div>
      <?php endif; ?>
    </div>
  </div>


  <!-- ══ FARMER + TRANSACTIONS GRID ══ -->
  <div class="ornament">✦</div>
  <div class="section-label">
    Farmer Accounts & Transactions
    <span style="font-size:9px;color:var(--gold);letter-spacing:1px;"><?= periodLabel($period,$month,$year) ?></span>
  </div>
  <div class="main-grid">

    <!-- Farmer Accounts -->
    <div class="panel">
      <div class="panel-head">
        <h3><i class="fas fa-users"></i> Farmer Billing Accounts</h3>
        <span class="panel-badge"><?= count($farmers_rows) ?> farmer<?= count($farmers_rows)!=1?'s':'' ?></span>
      </div>
      <div class="panel-body">
        <?php if (!empty($farmers_rows)): ?>
          <?php foreach ($farmers_rows as $r):
            $init = strtoupper(substr(trim($r['farmer_name']), 0, 1));
            if ($r['has_overdue'])          { $pc='fp-red';   $pl='Overdue'; }
            elseif ($r['outstanding'] <= 0) { $pc='fp-green'; $pl='Paid'; }
            elseif ($r['collected'] > 0)    { $pc='fp-blue';  $pl='Partial'; }
            else                            { $pc='fp-amber'; $pl='Unpaid'; }
          ?>
          <div class="farmer-row">
            <div class="f-avatar"><?= $init ?></div>
            <div>
              <div class="f-name"><?= htmlspecialchars(trim($r['farmer_name'])) ?></div>
              <div class="f-detail">
                <?= htmlspecialchars($r['barangay'] ?? '—') ?>, <?= htmlspecialchars($r['municipality'] ?? '—') ?>
                · <?= htmlspecialchars($r['phone'] ?? '—') ?>
              </div>
            </div>
            <div class="f-right">
              <div class="f-billed">Billed: ₱<?= number_format($r['billed'], 2) ?></div>
              <div class="f-balance <?= $r['outstanding']>0 ? 'fc-red' : 'fc-green' ?>">
                ₱<?= number_format($r['outstanding'], 2) ?>
              </div>
              <span class="f-pill <?= $pc ?>"><?= $pl ?></span>
            </div>
          </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="no-data"><i class="fas fa-users"></i><p>No farmer accounts for this period.</p></div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Recent Transactions -->
    <div class="panel">
      <div class="panel-head">
        <h3><i class="fas fa-receipt"></i> Recent Transactions</h3>
        <span class="panel-badge"><?= $period !== 'all' ? periodLabel($period,$month,$year) : 'Last 8' ?></span>
      </div>
      <div class="panel-body">
        <?php if (!empty($recent_rows)): ?>
          <?php foreach ($recent_rows as $r): ?>
          <div class="tx-row">
            <div class="tx-icon">
              <i class="fas fa-<?= $r['type']==='Tractor' ? 'tractor' : 'wheat-awn' ?>"></i>
            </div>
            <div>
              <div class="tx-farmer"><?= htmlspecialchars($r['farmer_name']) ?></div>
              <div class="tx-machine"><?= htmlspecialchars($r['machine_name']) ?> · <?= htmlspecialchars($r['type']) ?></div>
              <div class="tx-or"><i class="fas fa-hashtag" style="font-size:8px;"></i> OR <?= htmlspecialchars($r['or_number']) ?></div>
            </div>
            <div class="tx-right">
              <div class="tx-amount">+₱<?= number_format($r['amount'], 2) ?></div>
              <div class="tx-date"><?= date('M d, Y', strtotime($r['payment_date'])) ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="no-data"><i class="fas fa-receipt"></i><p>No transactions for this period.</p></div>
        <?php endif; ?>
      </div>
    </div>

  </div><!-- end main-grid -->

</div><!-- end fs-inner -->
</div><!-- end fs-wrap -->

<script>
function togglePeriodSelects() {
    const p = document.getElementById('periodSel').value;
    document.getElementById('monthSel').style.display = p === 'month' ? 'inline-block' : 'none';
    document.getElementById('yearSel').style.display  = (p === 'month' || p === 'year') ? 'inline-block' : 'none';
}
document.addEventListener('DOMContentLoaded', togglePeriodSelects);
</script>
</body>
</html>