<?php
include('dastaff_header.php');
require_once '../includes/config.php';

$municipalityQuery = "SELECT DISTINCT municipality FROM associations ORDER BY municipality";
$municipalityResult = $conn->query($municipalityQuery);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"> 
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reservation Records | DA Staff</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.css">
<style>
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        font-size: 13px;
        margin: 0; padding: 0;
    }

    .main-content {
        height: calc(200vh - 150px);
        padding: 0 20px;
    }

    .usernames { right: 180px; color: #fff; font-size: 15px; }
    .usernames a { color: #ffffff; margin-left: 10px; text-decoration: underline; }

    h2 {
        font-size: 27px; color: #fff; font-weight: bold; text-align: center;
        text-shadow: 1px 1px 3px rgba(14,4,4,0.7); margin: 10px 0 0 0; padding-bottom: 1px;
    }

    /* ── Flash messages ── */
    .flash-msg {
        padding: 11px 16px; border-radius: 8px; margin: 10px 0;
        font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 10px;
    }
    .flash-success { background:#e8f5e9; color:#1b5e20; border-left:4px solid #2d7a2d; }
    .flash-error   { background:#fdecea; color:#b71c1c; border-left:4px solid #d32f2f; }

    /* ── Search bar — same style as farmer page ── */
    .search-bar {
        display: flex; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 15px;
    }
    .search-bar select,
    .search-bar input[type="text"] {
        padding: 7px 11px; font-size: 13px; border: 1px solid #ccc; border-radius: 6px;
        background: white; font-family: inherit;
    }
    .search-bar button {
        padding: 7px 14px; font-size: 13px; border: none; border-radius: 6px;
        background-color: #2d7a2d; color: white; cursor: pointer; transition: background-color 0.3s;
        font-family: inherit;
    }
    .search-bar button:hover { background-color: #256725; }
    .search-bar label {
        color: #fff; font-weight: bold; font-size: 13px; margin-right: 2px;
        text-shadow: 1px 1px 3px rgba(14,4,4,0.7);
    }

    /* Flatpickr match */
    .flatpickr-input {
        padding: 7px 11px !important; font-size: 13px !important;
        border: 1px solid #ccc !important; border-radius: 6px !important;
        background: white !important; color: #333 !important;
        width: 120px !important; box-sizing: border-box !important; cursor: pointer !important;
    }
    .flatpickr-input:focus { outline: none !important; border-color: #2d7a2d !important; }

    /* ── Table — exact same structure as farmer page ── */
    .table-container {
        border: 1px solid #ddd; border-radius: 8px; background-color: white;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-top: 10px;
    }

    table {
        width: 100%; border-collapse: collapse; background-color: white; table-layout: fixed;
    }

    /* Column widths */
    table th:nth-child(1),  table td:nth-child(1)  { width: 95px;  }
    table th:nth-child(2),  table td:nth-child(2)  { width: 130px; }
    table th:nth-child(3),  table td:nth-child(3)  { width: 90px;  }
    table th:nth-child(4),  table td:nth-child(4)  { width: 140px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    table th:nth-child(5),  table td:nth-child(5)  { width: 65px;  text-align:right; padding-right:10px; }
    table th:nth-child(6),  table td:nth-child(6)  { width: 110px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    table th:nth-child(7),  table td:nth-child(7)  { width: 75px;  text-align:right; padding-right:10px; }
    table th:nth-child(8),  table td:nth-child(8)  { width: 90px;  text-align:right; padding-right:10px; }
    table th:nth-child(9),  table td:nth-child(9)  { width: auto;  overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    table th:nth-child(10), table td:nth-child(10) { width: 80px;  text-align:center; }

    th, td {
        padding: 10px 8px; text-align: left; border-bottom: 1px solid #ddd;
        vertical-align: middle; overflow: hidden; text-overflow: ellipsis; font-size: 13px;
    }
    th {
        background-color: #2d7a2d; color: white; font-weight: normal;
        white-space: nowrap; font-size: 13px;
    }

    thead { display: table; width: 100%; table-layout: fixed; }
    tbody { display: block; max-height: 245px; overflow-y: auto; overflow-x: hidden; width: 100%; }
    tbody tr { display: table; width: 100%; table-layout: fixed; cursor: pointer; }
    tr:hover { background-color: #f1f1f1; }
    tr.selected { background-color: #c3e6cb !important; }

    .badge {
        display: inline-block; padding: 3px 8px; border-radius: 12px;
        font-size: 11px; font-weight: 600;
    }
    .badge-success   { background: #d1fae5; color: #065f46; }
    .badge-pending   { background: #dbeafe; color: #1e40af; }
    .badge-warning   { background: #fef3c7; color: #92400e; }
    .badge-cancelled { background: #fee2e2; color: #991b1b; }

    .loading { text-align: center; padding: 40px; color: #6b7280; }
    .spinner {
        border: 4px solid #e5e7eb; border-top: 4px solid #2d7a2d;
        border-radius: 50%; width: 36px; height: 36px;
        animation: spin 1s linear infinite; margin: 0 auto 12px;
    }
    @keyframes spin { 0%{transform:rotate(0deg)} 100%{transform:rotate(360deg)} }

    .no-data { text-align: center; padding: 40px; color: #6b7280; font-size: 13px; }

    /* ── Bottom action buttons — same as farmer page ── */
    .view-container {
        display: flex; justify-content: center; align-items: center;
        gap: 10px; margin-top: 20px;
    }
    .view-container button {
        background-color: #2d7a2d; color: white; border: none;
        padding: 9px 18px; border-radius: 6px; cursor: pointer; font-size: 14px;
        font-family: inherit; transition: background-color 0.3s;
    }
    .view-container button:hover:not(:disabled) { background-color: #256725; }
    .view-container button:disabled { background-color: #2d7a2d; opacity: 0.5; cursor: not-allowed; }

    /* ── View Modal ── */
    .modal-overlay {
        display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6);
        z-index: 9999; justify-content: center; align-items: center; backdrop-filter: blur(3px);
    }
    .modal-box {
        background: white; border-radius: 14px; width: 860px; max-width: 96%;
        max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 40px rgba(0,0,0,0.25);
        position: relative;
    }
    .modal-box::-webkit-scrollbar { width: 6px; }
    .modal-box::-webkit-scrollbar-thumb { background: #2d7a2d; border-radius: 4px; }

    .modal-header {
        background: linear-gradient(135deg,#16a34a,#15803d);
        padding: 16px 24px; border-radius: 14px 14px 0 0;
        display: flex; justify-content: space-between; align-items: center;
        position: sticky; top: 0; z-index: 10;
    }
    .modal-header-title { color: white; font-size: 17px; font-weight: 700; }
    .modal-header-sub   { color: rgba(255,255,255,0.8); font-size: 12px; margin-top: 2px; }
    .modal-btn-print {
        padding: 7px 16px; background: rgba(255,255,255,0.2); color: white;
        border: 1px solid rgba(255,255,255,0.35); border-radius: 6px;
        font-size: 13px; font-weight: 600; cursor: pointer;
    }
    .modal-btn-print:hover { background: rgba(255,255,255,0.3); }
    .modal-btn-close {
        width: 32px; height: 32px; background: rgba(255,255,255,0.2); border: none;
        border-radius: 50%; color: white; font-size: 18px; cursor: pointer;
        display: flex; align-items: center; justify-content: center;
    }
    .modal-btn-close:hover { background: rgba(255,255,255,0.3); }
    .modal-body { padding: 24px; }

    .info-section-title {
        font-size: 11px; font-weight: 700; color: #15803d; text-transform: uppercase;
        letter-spacing: .5px; margin-bottom: 10px; padding-bottom: 6px;
        border-bottom: 2px solid #dcfce7;
    }
    .info-grid-3 { display: grid; grid-template-columns: repeat(3,1fr); gap: 10px; margin-bottom: 20px; }
    .info-grid-4 { display: grid; grid-template-columns: repeat(4,1fr); gap: 10px; margin-bottom: 20px; }
    .info-box {
        background: #f0fdf4; border-radius: 8px; padding: 10px 13px;
        border: 1px solid #e5f0e8;
    }
    .info-box-label {
        font-size: 10px; font-weight: 600; color: #6b7280;
        text-transform: uppercase; letter-spacing: .4px; margin-bottom: 4px;
    }
    .info-box-value { font-size: 13px; font-weight: 600; color: #1f2937; }
    .info-box-total {
        background: #f0fdf4; border-radius: 8px; padding: 10px 13px; border: 1px solid #bbf7d0;
    }
    .info-box-total .info-box-value { font-size: 18px; font-weight: 800; color: #16a34a; }

    @media(max-width:640px) {
        .info-grid-3,.info-grid-4 { grid-template-columns:1fr; }
        .modal-body { padding: 16px; }
        .search-bar { flex-direction: column; align-items: flex-start; }
    }
</style>
</head>
<body>
<div class="main-content">
    <div class="usernames">
        Welcome <?= htmlspecialchars($_SESSION['user_role']) ?>
        <a href="/agri_system/logout.php">Logout</a>
    </div>

    <h2>List of Reservations</h2>

    <!-- ── Search bar matching farmer page style ── -->
    <div class="search-bar">
        <!-- Municipality -->
        <select id="municipalitySelect" onchange="loadAssociations()">
            <option value="">All Municipalities</option>
            <?php while($row = $municipalityResult->fetch_assoc()): ?>
                <option value="<?= htmlspecialchars($row['municipality']) ?>">
                    <?= htmlspecialchars($row['municipality']) ?>
                </option>
            <?php endwhile; ?>
        </select>

        <!-- Association (cascades from municipality) -->
        <select id="associationSelect" disabled>
            <option value="">All Associations</option>
        </select>

        <!-- Machine Type -->
        <select id="machineTypeSelect">
            <option value="">All Machine Types</option>
            <option value="Tractor">Tractor</option>
            <option value="Harvester">Harvester</option>
        </select>

        <!-- Status -->
        <select id="statusSelect">
            <option value="">All Status</option>
            <option value="Pending">Pending</option>
            <option value="Approved">Approved</option>
            <option value="Completed">Completed</option>
            <option value="Cancelled">Cancelled</option>
        </select>

        <!-- Date range -->
        <label id="from_label">From</label>
        <input type="text" id="fromDate" placeholder="mm/dd/yyyy" readonly>
        <label id="to_label">To</label>
        <input type="text" id="toDate" placeholder="mm/dd/yyyy" readonly>

        <button onclick="searchReservations()">Search</button>
        <button onclick="resetFilters()" style="background:#6b7280;">Reset</button>
    </div>

    <!-- ── Table ── -->
    <div class="table-container" id="printSection">
        <table id="reservationTable">
            <thead>
                <tr>
                    <th>Booking Date</th>
                    <th>Farmer Name</th>
                    <th>Contact</th>
                    <th>Farm Location</th>
                    <th>Farm Size</th>
                    <th>Machine</th>
                    <th>Price/ha</th>
                    <th>Total Amount</th>
                    <th>Association</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody id="reservationBody">
                <tr>
                    <td colspan="10" class="loading">
                        <div class="spinner"></div>
                        <p>Loading reservations...</p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- ── Action buttons same style as farmer page ── -->
    <div class="view-container">
        <button id="btnView"  onclick="viewSelected()"  disabled>View</button>
        <button id="btnPrint" onclick="printSelected()" disabled>Print</button>
    </div>
</div>

<!-- ══════════════════════════════════
     VIEW MODAL
══════════════════════════════════ -->
<div class="modal-overlay" id="viewModal">
  <div class="modal-box">
    <div class="modal-header">
      <div>
        <div class="modal-header-title" id="modalTitle">Reservation Details</div>
        <div class="modal-header-sub"   id="modalSubtitle">Loading...</div>
      </div>
      <div style="display:flex;gap:10px;align-items:center;">
        <button class="modal-btn-print" onclick="printFromModal()">Print</button>
        <button class="modal-btn-close" onclick="closeViewModal()">&times;</button>
      </div>
    </div>
    <div class="modal-body" id="modalBody">
      <div class="loading"><div class="spinner"></div><p>Loading...</p></div>
    </div>
  </div>
</div>

<script>
const pathParts = window.location.pathname.split('/');
pathParts.pop();
const baseUrl = pathParts.join('/') + '/';

let selectedBookingId = null;

window.addEventListener('DOMContentLoaded', loadAllReservations);

/* ── Row selection ── */
function selectRow(row, bookingId) {
    document.querySelectorAll('#reservationBody tr.selected')
            .forEach(r => r.classList.remove('selected'));
    row.classList.add('selected');
    selectedBookingId = bookingId;
    document.getElementById('btnView').disabled  = false;
    document.getElementById('btnPrint').disabled = false;
}

/* ── View modal ── */
function viewSelected() {
    if (!selectedBookingId) return;
    const modal = document.getElementById('viewModal');
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    document.getElementById('modalTitle').textContent    = 'Reservation Details';
    document.getElementById('modalSubtitle').textContent = 'Loading...';
    document.getElementById('modalBody').innerHTML =
        '<div class="loading"><div class="spinner"></div><p>Loading...</p></div>';

    fetch(baseUrl + 'get_reservation_detail.php?id=' + selectedBookingId)
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                document.getElementById('modalBody').innerHTML = '<p style="color:red;padding:20px;">Failed to load reservation.</p>';
                return;
            }
            const r = data.reservation;
            const farmSize   = parseFloat(r.farm_size)       || 0;
            const pricePerHa = parseFloat(r.price_per_hectare) || 0;
            const total      = farmSize * pricePerHa;

            const statusColors = {
                Pending:   { bg:'#dbeafe', color:'#1e40af' },
                Approved:  { bg:'#d1fae5', color:'#065f46' },
                Completed: { bg:'#d1fae5', color:'#065f46' },
                Cancelled: { bg:'#fee2e2', color:'#991b1b' },
                Declined:  { bg:'#fee2e2', color:'#991b1b' },
            };
            const sc = statusColors[r.status] || { bg:'#f3f4f6', color:'#374151' };

            document.getElementById('modalTitle').textContent    = 'Reservation #' + String(r.booking_id).padStart(5,'0');
            document.getElementById('modalSubtitle').textContent = 'Booking Date: ' + formatDate(r.booking_date);

            document.getElementById('modalBody').innerHTML = `
              <div style="display:flex;justify-content:flex-end;margin-bottom:16px;">
                <span style="background:${sc.bg};color:${sc.color};padding:5px 16px;border-radius:999px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;">${esc(r.status)}</span>
              </div>

              <div class="info-section-title">Farmer Information</div>
              <div class="info-grid-3">
                ${infoBox('Full Name', r.farmer_name)}
                ${infoBox('Contact No.', r.farmer_phone || 'N/A')}
                ${infoBox('Email', r.farmer_email || 'N/A')}
                ${infoBox('Province', r.farmer_province || 'N/A')}
                ${infoBox('Municipality', r.farmer_municipality || 'N/A')}
                ${infoBox('Barangay', r.farmer_barangay || 'N/A')}
              </div>

              <div class="info-section-title">Farm Details</div>
              <div class="info-grid-3">
                ${infoBox('Farm Location', r.farm_location || r.lot_location || 'N/A')}
                ${infoBox('Farm Size', farmSize.toFixed(2) + ' ha')}
                ${r.lot_number ? infoBox('Lot Number', r.lot_number) : ''}
              </div>

              <div class="info-section-title">Machine & Association</div>
              <div class="info-grid-4">
                ${infoBox('Machine Name', r.machine_name)}
                ${infoBox('Machine Type', r.machine_type)}
                ${infoBox('Association', r.association_name)}
                ${infoBox('Assoc. Location', (r.assoc_municipality||'') + ', ' + (r.assoc_province||''))}
              </div>

              <div class="info-section-title">Payment Summary</div>
              <div class="info-grid-3" style="margin-bottom:${r.notes?'20px':'0'};">
                ${infoBox('Price per Hectare', '₱' + fmt(pricePerHa))}
                ${infoBox('Farm Size', farmSize.toFixed(2) + ' ha')}
                <div class="info-box-total">
                  <div class="info-box-label">Total Amount</div>
                  <div class="info-box-value">₱${fmt(total)}</div>
                </div>
              </div>

              ${r.notes ? `
              <div class="info-section-title">Notes</div>
              <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:12px 14px;font-size:13px;color:#92400e;">${esc(r.notes)}</div>` : ''}

              <div style="margin-top:16px;padding-top:12px;border-top:1px solid #e5e7eb;display:flex;justify-content:space-between;align-items:center;font-size:11px;color:#9ca3af;">
                <span>Submitted: ${formatDate(r.created_at)}</span>
                <span>Booking ID: #${String(r.booking_id).padStart(5,'0')}</span>
              </div>
            `;
        })
        .catch(() => {
            document.getElementById('modalBody').innerHTML = '<p style="color:red;padding:20px;">Error loading reservation details.</p>';
        });
}

function infoBox(label, value) {
    return `<div class="info-box">
        <div class="info-box-label">${label}</div>
        <div class="info-box-value">${esc(String(value||''))}</div>
    </div>`;
}

function closeViewModal() {
    document.getElementById('viewModal').style.display = 'none';
    document.body.style.overflow = '';
}

function printFromModal() {
    if (selectedBookingId)
        window.open(baseUrl + 'view_reservation.php?id=' + selectedBookingId + '&print=1', '_blank');
}

function printSelected() {
    if (selectedBookingId)
        window.open(baseUrl + 'view_reservation.php?id=' + selectedBookingId + '&print=1', '_blank');
}

/* ── Load all on page load ── */
function loadAllReservations() {
    showLoading();
    fetch(baseUrl + 'get_all_reservations.php')
        .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.text(); })
        .then(text => {
            const data = JSON.parse(text);
            if (data.success && data.reservations && data.reservations.length > 0)
                displayReservations(data.reservations);
            else
                showEmpty('No reservations found', 'There are no booking records in the system');
        })
        .catch(err => showError(err.message));
}

/* ── Display rows ── */
function displayReservations(reservations) {
    const tbody = document.getElementById('reservationBody');
    tbody.innerHTML = '';
    resetActionButtons();

    reservations.forEach(r => {
        const row = document.createElement('tr');
        let badgeClass = 'badge-warning';
        if (r.status === 'Approved')  badgeClass = 'badge-success';
        if (r.status === 'Pending')   badgeClass = 'badge-pending';
        if (r.status === 'Cancelled') badgeClass = 'badge-cancelled';
        if (r.status === 'Completed') badgeClass = 'badge-success';

        const farmSize   = parseFloat(r.farm_size) || 0;
        const pricePerHa = parseFloat(r.price_per_hectare) || 0;
        const total      = farmSize * pricePerHa;

        row.innerHTML =
            '<td>' + formatDate(r.booking_date) + '</td>' +
            '<td><strong>' + esc(r.farmer_name) + '</strong></td>' +
            '<td>' + esc(r.phone) + '</td>' +
            '<td>' + esc(r.farm_location) + '</td>' +
            '<td>' + esc(r.farm_size) + ' ha</td>' +
            '<td>' + esc(r.machine_type) + '<br><small style="color:#6b7280;font-size:11px;">' + esc(r.machine_name) + '</small></td>' +
            '<td>₱' + fmt(pricePerHa) + '</td>' +
            '<td style="color:#16a34a;font-weight:700;">₱' + fmt(total) + '</td>' +
            '<td><small style="color:#6b7280;">' + esc(r.association_name) + '</small></td>' +
            '<td><span class="badge ' + badgeClass + '">' + esc(r.status) + '</span></td>';

        row.addEventListener('click', () => selectRow(row, r.booking_id));
        tbody.appendChild(row);
    });
}

/* ── Load associations cascade ── */
function loadAssociations() {
    const muni = document.getElementById('municipalitySelect').value;
    const sel  = document.getElementById('associationSelect');
    sel.innerHTML = '<option value="">All Associations</option>';
    sel.disabled = true;
    if (!muni) return;

    fetch(baseUrl + 'get_associations.php?municipality=' + encodeURIComponent(muni))
        .then(r => r.text())
        .then(text => {
            const data = JSON.parse(text);
            if (data.success && data.associations && data.associations.length > 0) {
                data.associations.forEach(a => {
                    const opt = document.createElement('option');
                    opt.value = a.id;
                    opt.textContent = a.name;
                    sel.appendChild(opt);
                });
                sel.disabled = false;
            }
        })
        .catch(() => {});
}

/* ── Search ── */
function toYMD(mmddyyyy) {
    if (!mmddyyyy) return '';
    const p = mmddyyyy.split('/');
    if (p.length !== 3) return '';
    return p[2] + '-' + p[0].padStart(2,'0') + '-' + p[1].padStart(2,'0');
}

function searchReservations() {
    const municipality  = document.getElementById('municipalitySelect').value;
    const associationId = document.getElementById('associationSelect').value;
    const machineType   = document.getElementById('machineTypeSelect').value;
    const status        = document.getElementById('statusSelect').value;
    const fromDate      = toYMD(document.getElementById('fromDate').value);
    const toDate        = toYMD(document.getElementById('toDate').value);

    showLoading();
    resetActionButtons();

    const params = new URLSearchParams({
        municipality, association_id: associationId,
        machine_type: machineType, status, from_date: fromDate, to_date: toDate
    });

    fetch(baseUrl + 'get_reservations.php?' + params.toString())
        .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.text(); })
        .then(text => {
            const data = JSON.parse(text);
            if (data.success && data.reservations && data.reservations.length > 0)
                displayReservations(data.reservations);
            else
                showEmpty('No reservations found', 'Try adjusting your search filters');
        })
        .catch(err => showError(err.message));
}

/* ── Reset ── */
function resetFilters() {
    document.getElementById('municipalitySelect').value = '';
    document.getElementById('associationSelect').innerHTML = '<option value="">All Associations</option>';
    document.getElementById('associationSelect').disabled = true;
    document.getElementById('machineTypeSelect').value = '';
    document.getElementById('statusSelect').value = '';
    fpFrom.clear();
    fpTo.clear();
    resetActionButtons();
    loadAllReservations();
}

function resetActionButtons() {
    selectedBookingId = null;
    document.getElementById('btnView').disabled  = true;
    document.getElementById('btnPrint').disabled = true;
}

/* ── UI helpers ── */
function showLoading() {
    document.getElementById('reservationBody').innerHTML =
        '<tr><td colspan="10" class="loading"><div class="spinner"></div><p>Loading reservations...</p></td></tr>';
}
function showEmpty(title, sub) {
    document.getElementById('reservationBody').innerHTML =
        '<tr><td colspan="10" class="no-data"><p>' + title + '</p><small>' + sub + '</small></td></tr>';
}
function showError(msg) {
    document.getElementById('reservationBody').innerHTML =
        '<tr><td colspan="10" class="no-data"><p>Error loading data</p><small>' + msg + '</small></td></tr>';
}

function formatDate(d) {
    if (!d) return 'N/A';
    const dt = new Date(d);
    const mm = String(dt.getMonth()+1).padStart(2,'0');
    const dd = String(dt.getDate()).padStart(2,'0');
    return mm + '/' + dd + '/' + dt.getFullYear();
}
function fmt(num) {
    if (!num) return '0.00';
    return parseFloat(num).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});
}
function esc(text) {
    if (!text) return '';
    const m = {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'};
    return String(text).replace(/[&<>"']/g, c => m[c]);
}

document.getElementById('viewModal').addEventListener('click', function(e) {
    if (e.target === this) closeViewModal();
});
document.addEventListener('keydown', e => { if (e.key==='Escape') closeViewModal(); });
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.js"></script>
<script>
const fpFrom = flatpickr('#fromDate', { dateFormat:'m/d/Y', allowInput:false });
const fpTo   = flatpickr('#toDate',   { dateFormat:'m/d/Y', allowInput:false });
</script>
</body>
</html>