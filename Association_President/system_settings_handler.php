<?php
session_start();
require_once '../includes/config.php';

// Only associations can access this page
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'associations') {
    header('Location: ../login.php');
    exit;
}

$user_id       = $_SESSION['user_id'];
$association_id = null;
$association    = null;

// Get association linked to this user
$stmt = $conn->prepare("SELECT * FROM associations WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$association = $result->fetch_assoc();
$stmt->close();

if (!$association) {
    die("Association not found for this account.");
}
$association_id = $association['id'];

// ── Fetch machines owned by this association ──────────────────────────────────
$machines = [];
$stmt = $conn->prepare("
    SELECT m.*, mo.operator_id, o.name AS operator_name
    FROM machines m
    LEFT JOIN machine_operators mo ON mo.machine_id = m.id AND mo.status = 'Active'
    LEFT JOIN operators o ON o.id = mo.operator_id
    WHERE m.association_id = ?
    ORDER BY m.id ASC
");
$stmt->bind_param("i", $association_id);
$stmt->execute();
$machines = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Fetch operators belonging to this association ─────────────────────────────
$operators = [];
$stmt = $conn->prepare("
    SELECT * FROM operators WHERE association_id = ? AND status = 'Active' ORDER BY name ASC
");
$stmt->bind_param("i", $association_id);
$stmt->execute();
$operators = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Fetch operator payment history ────────────────────────────────────────────
$op_payments = [];
$stmt = $conn->prepare("
    SELECT op.*, o.name AS operator_name
    FROM operator_payments op
    JOIN operators o ON o.id = op.operator_id
    WHERE op.association_id = ?
    ORDER BY op.year DESC, FIELD(op.month,'January','February','March','April','May','June','July','August','September','October','November','December') DESC
    LIMIT 20
");
$stmt->bind_param("i", $association_id);
$stmt->execute();
$op_payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Flash messages ────────────────────────────────────────────────────────────
$success = $_SESSION['success'] ?? null;
$error   = $_SESSION['error']   ?? null;
unset($_SESSION['success'], $_SESSION['error']);

$months = ['January','February','March','April','May','June',
           'July','August','September','October','November','December'];
$current_month = date('F');
$current_year  = date('Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>System Settings – <?= htmlspecialchars($association['name']) ?></title>
<style>
/* ── Reset & base ── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: 'Segoe UI', Arial, sans-serif;
    font-size: 14px;
    background: #f4f6f8;
    color: #1a1a2e;
}

/* ── Layout ── */
.page-wrapper { max-width: 960px; margin: 0 auto; padding: 28px 16px 60px; }
.page-header { margin-bottom: 24px; }
.page-header h1 { font-size: 22px; font-weight: 600; color: #1a1a2e; display: flex; align-items: center; gap: 10px; }
.page-header p  { font-size: 13px; color: #6b7280; margin-top: 4px; }

/* ── Tabs ── */
.tabs { display: flex; gap: 2px; border-bottom: 2px solid #e5e7eb; margin-bottom: 24px; }
.tab-btn {
    padding: 10px 18px;
    font-size: 13px;
    font-weight: 500;
    background: none;
    border: none;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    cursor: pointer;
    color: #6b7280;
    display: flex;
    align-items: center;
    gap: 7px;
    transition: color .15s;
}
.tab-btn:hover { color: #1a1a2e; }
.tab-btn.active { color: #16a34a; border-bottom-color: #16a34a; }

/* ── Tab panels ── */
.tab-panel { display: none; }
.tab-panel.active { display: block; }

/* ── Cards ── */
.card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 20px 22px;
    margin-bottom: 18px;
}
.card-title {
    font-size: 15px;
    font-weight: 600;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.card-desc {
    font-size: 12px;
    color: #6b7280;
    margin-bottom: 16px;
}

/* ── Forms ── */
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px; }
.form-grid.cols-3 { grid-template-columns: 1fr 1fr 1fr; }
.form-grid.full   { grid-template-columns: 1fr; }
.form-group { display: flex; flex-direction: column; gap: 5px; }
.form-group label { font-size: 12px; font-weight: 600; color: #374151; }
.form-group input,
.form-group select,
.form-group textarea {
    border: 1px solid #d1d5db;
    border-radius: 7px;
    padding: 8px 11px;
    font-size: 13px;
    color: #1a1a2e;
    background: #fff;
    width: 100%;
    transition: border-color .15s, box-shadow .15s;
}
.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #16a34a;
    box-shadow: 0 0 0 3px rgba(22,163,74,.1);
}
.form-group textarea { resize: vertical; min-height: 72px; }
.input-prefix { display: flex; }
.input-prefix .prefix-label {
    background: #f3f4f6;
    border: 1px solid #d1d5db;
    border-right: none;
    border-radius: 7px 0 0 7px;
    padding: 8px 10px;
    font-size: 12px;
    color: #6b7280;
    white-space: nowrap;
    display: flex;
    align-items: center;
}
.input-prefix input {
    border-radius: 0 7px 7px 0;
}

/* ── Buttons ── */
.btn {
    padding: 8px 16px;
    border-radius: 7px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    border: 1px solid #d1d5db;
    background: #fff;
    color: #374151;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: background .15s, border-color .15s;
}
.btn:hover { background: #f3f4f6; }
.btn-primary {
    background: #16a34a;
    color: #fff;
    border-color: #16a34a;
}
.btn-primary:hover { background: #15803d; border-color: #15803d; }
.btn-danger  { color: #dc2626; border-color: #dc2626; }
.btn-danger:hover  { background: #fef2f2; }
.btn-sm { padding: 5px 10px; font-size: 12px; }
.form-footer { display: flex; justify-content: flex-end; margin-top: 6px; }

/* ── Alerts ── */
.alert {
    padding: 10px 14px;
    border-radius: 8px;
    font-size: 13px;
    margin-bottom: 16px;
    display: flex;
    align-items: flex-start;
    gap: 8px;
}
.alert-success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
.alert-error   { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
.alert-info    { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }

/* ── Table ── */
.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; font-size: 13px; }
th {
    text-align: left;
    font-size: 11px;
    font-weight: 600;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: .5px;
    padding: 9px 12px;
    border-bottom: 1px solid #e5e7eb;
    background: #f9fafb;
}
td { padding: 11px 12px; border-bottom: 1px solid #f3f4f6; vertical-align: middle; }
tr:last-child td { border-bottom: none; }
tr:hover td { background: #f9fafb; }

/* ── Badges ── */
.badge {
    display: inline-flex;
    align-items: center;
    padding: 3px 9px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}
.badge-active      { background: #dcfce7; color: #166534; }
.badge-inactive    { background: #f3f4f6; color: #6b7280; }
.badge-maintenance { background: #fef9c3; color: #854d0e; }
.badge-paid        { background: #dcfce7; color: #166534; }
.badge-pending     { background: #fef9c3; color: #854d0e; }
.badge-tractor     { background: #eff6ff; color: #1d4ed8; }
.badge-harvester   { background: #f5f3ff; color: #6d28d9; }

/* ── Inline editable inputs inside table ── */
td input[type="number"] {
    width: 110px;
    padding: 5px 8px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 13px;
}
td input[type="number"]:focus {
    outline: none;
    border-color: #16a34a;
    box-shadow: 0 0 0 2px rgba(22,163,74,.1);
}

/* ── Summary metrics ── */
.metric-row { display: grid; grid-template-columns: repeat(3,1fr); gap: 12px; margin-bottom: 18px; }
.metric {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 9px;
    padding: 14px 16px;
}
.metric-label { font-size: 11px; color: #6b7280; font-weight: 600; text-transform: uppercase; letter-spacing: .4px; margin-bottom: 6px; }
.metric-val   { font-size: 22px; font-weight: 700; color: #1a1a2e; }
.metric-sub   { font-size: 11px; color: #9ca3af; margin-top: 2px; }

/* ── Divider ── */
.divider { border: none; border-top: 1px solid #f3f4f6; margin: 14px 0; }

/* ── Icons (inline svg) ── */
.icon { width: 16px; height: 16px; vertical-align: -2px; flex-shrink: 0; }

/* ── Responsive ── */
@media (max-width: 600px) {
    .form-grid         { grid-template-columns: 1fr; }
    .form-grid.cols-3  { grid-template-columns: 1fr; }
    .metric-row        { grid-template-columns: 1fr 1fr; }
    .tabs              { overflow-x: auto; }
}
</style>
</head>
<body>

<div class="page-wrapper">

  <!-- Page header -->
  <div class="page-header">
    <h1>
      <svg class="icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><circle cx="12" cy="12" r="3"/></svg>
      System Settings
    </h1>
    <p><?= htmlspecialchars($association['name']) ?> — Machine pricing &amp; operator pay</p>
  </div>

  <!-- Flash messages -->
  <?php if ($success): ?>
    <div class="alert alert-success">
      <svg class="icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
      <?= htmlspecialchars($success) ?>
    </div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-error">
      <svg class="icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
      <?= htmlspecialchars($error) ?>
    </div>
  <?php endif; ?>

  <!-- Tabs -->
  <div class="tabs">
    <button class="tab-btn active" onclick="switchTab('pricing', this)">
      <svg class="icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/></svg>
      Machine pricing
    </button>
    <button class="tab-btn" onclick="switchTab('operators', this)">
      <svg class="icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
      Operator pay
    </button>
  </div>

  <!-- ══════════════════════════════════════════════════════════
       TAB 1 — MACHINE PRICING
  ══════════════════════════════════════════════════════════════ -->
  <div id="tab-pricing" class="tab-panel active">

    <div class="alert alert-info">
      <svg class="icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      Price changes apply to new bookings only. Completed bookings are not affected.
    </div>

    <!-- Bulk update form -->
    <div class="card">
      <div class="card-title">
        <svg class="icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 11h.01M12 11h.01M15 11h.01M4 19h16a2 2 0 002-2V7a2 2 0 00-2-2H4a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
        Machine rates
      </div>
      <div class="card-desc">Edit price per hectare and operator rate per hectare for each machine. Click Save rates to apply.</div>

      <?php if (empty($machines)): ?>
        <p style="color:#6b7280;font-size:13px">No machines found for this association.</p>
      <?php else: ?>
      <form method="POST" action="system_settings_handler.php">
        <input type="hidden" name="action" value="update_machine_prices">
        <input type="hidden" name="association_id" value="<?= $association_id ?>">
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Machine</th>
                <th>Type</th>
                <th>Status</th>
                <th>Assigned operator</th>
                <th>Price / hectare (₱)</th>
                <th>Operator rate / ha (₱)</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($machines as $m): ?>
              <tr>
                <td>
                  <strong><?= htmlspecialchars($m['machine_name']) ?></strong>
                  <br><span style="font-size:11px;color:#9ca3af">ID: <?= $m['id'] ?></span>
                </td>
                <td>
                  <span class="badge <?= $m['type'] === 'Tractor' ? 'badge-tractor' : 'badge-harvester' ?>">
                    <?= htmlspecialchars($m['type']) ?>
                  </span>
                </td>
                <td>
                  <?php
                    $sc = match($m['status']) {
                      'Active'           => 'badge-active',
                      'Inactive'         => 'badge-inactive',
                      'Under Maintenance'=> 'badge-maintenance',
                      default            => 'badge-inactive'
                    };
                  ?>
                  <span class="badge <?= $sc ?>"><?= htmlspecialchars($m['status']) ?></span>
                </td>
                <td style="color:#6b7280;font-size:12px">
                  <?= $m['operator_name'] ? htmlspecialchars($m['operator_name']) : '<em>Unassigned</em>' ?>
                </td>
                <td>
                  <div class="input-prefix">
                    <span class="prefix-label">₱</span>
                    <input type="number"
                           name="price[<?= $m['id'] ?>]"
                           value="<?= number_format($m['price_per_hectare'], 2, '.', '') ?>"
                           min="0" step="0.01" required>
                  </div>
                </td>
                <td>
                  <div class="input-prefix">
                    <span class="prefix-label">₱</span>
                    <input type="number"
                           name="op_rate[<?= $m['id'] ?>]"
                           value="<?= number_format($m['operator_rate_per_hectare'], 2, '.', '') ?>"
                           min="0" step="0.01">
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="form-footer" style="margin-top:14px">
          <button type="submit" class="btn btn-primary">
            <svg class="icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            Save rates
          </button>
        </div>
      </form>
      <?php endif; ?>
    </div>

    <!-- Member discount -->
    <div class="card">
      <div class="card-title">
        <svg class="icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/></svg>
        Member discount rule
      </div>
      <div class="card-desc">
        Farmers who belong to your association automatically receive a discount when a booking is completed.
        This is enforced by the database trigger <code>trg_create_payment_ledger</code>.
      </div>
      <div class="alert alert-info" style="margin-bottom:0">
        <svg class="icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        The current discount is fixed at <strong>5%</strong> and is applied automatically via a database trigger.
        To change the percentage, update the trigger value in your database or ask your IT admin.
      </div>
    </div>

  </div><!-- /tab-pricing -->


  <!-- ══════════════════════════════════════════════════════════
       TAB 2 — OPERATOR PAY
  ══════════════════════════════════════════════════════════════ -->
  <div id="tab-operators" class="tab-panel">

    <!-- Summary metrics -->
    <?php
      $total_paid_month = 0;
      $pending_count    = 0;
      foreach ($op_payments as $p) {
          if ($p['month'] === $current_month && $p['year'] == $current_year) {
              if ($p['payment_status'] === 'Paid')    $total_paid_month += $p['total_amount'];
              if ($p['payment_status'] === 'Pending') $pending_count++;
          }
      }
    ?>
    <div class="metric-row">
      <div class="metric">
        <div class="metric-label">Active operators</div>
        <div class="metric-val"><?= count($operators) ?></div>
        <div class="metric-sub">In this association</div>
      </div>
      <div class="metric">
        <div class="metric-label">Paid this month</div>
        <div class="metric-val">₱<?= number_format($total_paid_month, 2) ?></div>
        <div class="metric-sub"><?= $current_month ?> <?= $current_year ?></div>
      </div>
      <div class="metric">
        <div class="metric-label">Pending payments</div>
        <div class="metric-val"><?= $pending_count ?></div>
        <div class="metric-sub">This month</div>
      </div>
    </div>

    <!-- Record new payment -->
    <div class="card">
      <div class="card-title">
        <svg class="icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
        Record operator payment
      </div>
      <div class="card-desc">Issue a monthly payment to an operator. This will be saved to the operator payments log.</div>

      <?php if (empty($operators)): ?>
        <div class="alert alert-info">No active operators found for this association.</div>
      <?php else: ?>
      <form method="POST" action="system_settings_handler.php" id="pay-form">
        <input type="hidden" name="action"         value="record_operator_payment">
        <input type="hidden" name="association_id" value="<?= $association_id ?>">

        <div class="form-grid">
          <div class="form-group">
            <label>Operator *</label>
            <select name="operator_id" required>
              <option value="">— Select operator —</option>
              <?php foreach ($operators as $op): ?>
                <option value="<?= $op['id'] ?>"><?= htmlspecialchars($op['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Payment date *</label>
            <input type="date" name="payment_date" value="<?= date('Y-m-d') ?>" required>
          </div>
        </div>

        <div class="form-grid cols-3">
          <div class="form-group">
            <label>Month *</label>
            <select name="month" required>
              <?php foreach ($months as $m): ?>
                <option value="<?= $m ?>" <?= $m === $current_month ? 'selected' : '' ?>><?= $m ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Year *</label>
            <input type="number" name="year" value="<?= $current_year ?>" min="2020" max="2099" required>
          </div>
          <div class="form-group">
            <label>Payment status *</label>
            <select name="payment_status" required>
              <option value="Paid">Paid</option>
              <option value="Pending">Pending</option>
            </select>
          </div>
        </div>

        <div class="form-grid">
          <div class="form-group">
            <label>Total amount (₱) *</label>
            <div class="input-prefix">
              <span class="prefix-label">₱</span>
              <input type="number" name="total_amount" placeholder="0.00" min="0" step="0.01" required>
            </div>
          </div>
        </div>

        <div class="form-grid full">
          <div class="form-group">
            <label>Notes</label>
            <textarea name="notes" placeholder="e.g. Full payment for May field operations…"></textarea>
          </div>
        </div>

        <div class="form-footer">
          <button type="submit" class="btn btn-primary">
            <svg class="icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            Submit payment
          </button>
        </div>
      </form>
      <?php endif; ?>
    </div>

    <!-- Payment history -->
    <div class="card">
      <div class="card-title">
        <svg class="icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
        Payment history
      </div>
      <div class="card-desc">Last 20 operator payment records for this association.</div>

      <?php if (empty($op_payments)): ?>
        <p style="color:#6b7280;font-size:13px">No payment records found.</p>
      <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Operator</th>
              <th>Period</th>
              <th>Amount</th>
              <th>Payment date</th>
              <th>Status</th>
              <th>Notes</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($op_payments as $p): ?>
            <tr>
              <td style="color:#9ca3af;font-size:12px"><?= $p['id'] ?></td>
              <td><strong><?= htmlspecialchars($p['operator_name']) ?></strong></td>
              <td><?= htmlspecialchars($p['month']) ?> <?= $p['year'] ?></td>
              <td><strong>₱<?= number_format($p['total_amount'], 2) ?></strong></td>
              <td><?= htmlspecialchars($p['payment_date']) ?></td>
              <td>
                <span class="badge <?= $p['payment_status'] === 'Paid' ? 'badge-paid' : 'badge-pending' ?>">
                  <?= htmlspecialchars($p['payment_status']) ?>
                </span>
              </td>
              <td style="font-size:12px;color:#6b7280;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                <?= htmlspecialchars($p['notes'] ?? '—') ?>
              </td>
              <td>
                <?php if ($p['payment_status'] === 'Pending'): ?>
                <form method="POST" action="system_settings_handler.php" style="display:inline">
                  <input type="hidden" name="action"     value="mark_op_payment_paid">
                  <input type="hidden" name="payment_id" value="<?= $p['id'] ?>">
                  <input type="hidden" name="association_id" value="<?= $association_id ?>">
                  <button type="submit" class="btn btn-sm btn-primary"
                          onclick="return confirm('Mark this payment as Paid?')">
                    Mark paid
                  </button>
                </form>
                <?php else: ?>
                  <form method="POST" action="system_settings_handler.php" style="display:inline">
                    <input type="hidden" name="action"     value="delete_op_payment">
                    <input type="hidden" name="payment_id" value="<?= $p['id'] ?>">
                    <input type="hidden" name="association_id" value="<?= $association_id ?>">
                    <button type="submit" class="btn btn-sm btn-danger"
                            onclick="return confirm('Delete this payment record?')">
                      Delete
                    </button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <!-- Operator rate quick-view -->
    <div class="card">
      <div class="card-title">
        <svg class="icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 11h.01M12 11h.01M15 11h.01M4 19h16a2 2 0 002-2V7a2 2 0 00-2-2H4a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
        Operator rates per machine
      </div>
      <div class="card-desc">
        Quick reference — these rates are set in the <strong>Machine pricing</strong> tab.
      </div>
      <?php if (empty($machines)): ?>
        <p style="color:#6b7280;font-size:13px">No machines found.</p>
      <?php else: ?>
      <div style="display:flex;flex-direction:column;gap:10px">
        <?php foreach ($machines as $m): ?>
        <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 14px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px">
          <div>
            <p style="font-weight:600;font-size:13px"><?= htmlspecialchars($m['machine_name']) ?> <span style="color:#9ca3af;font-weight:400">(ID <?= $m['id'] ?>)</span></p>
            <p style="font-size:11px;color:#6b7280;margin-top:2px">
              <?= htmlspecialchars($m['type']) ?>
              <?= $m['operator_name'] ? ' · ' . htmlspecialchars($m['operator_name']) : ' · <em>Unassigned</em>' ?>
            </p>
          </div>
          <div style="text-align:right">
            <p style="font-weight:600;font-size:14px">₱<?= number_format($m['operator_rate_per_hectare'], 2) ?> / ha</p>
            <p style="font-size:11px;color:#9ca3af">Farmer rate: ₱<?= number_format($m['price_per_hectare'], 2) ?> / ha</p>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

  </div><!-- /tab-operators -->

</div><!-- /page-wrapper -->

<script>
function switchTab(id, btn) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + id).classList.add('active');
    btn.classList.add('active');
}

// Highlight changed inputs in machine pricing table
document.querySelectorAll('td input[type="number"]').forEach(function(inp) {
    var original = inp.value;
    inp.addEventListener('input', function() {
        inp.style.borderColor = inp.value !== original ? '#f59e0b' : '';
    });
});

// Auto-open the operators tab if redirected with ?tab=operators
(function() {
    var params = new URLSearchParams(window.location.search);
    if (params.get('tab') === 'operators') {
        var btn = document.querySelectorAll('.tab-btn')[1];
        switchTab('operators', btn);
    }
})();
</script>
</body>
</html>