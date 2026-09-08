<?php
include('dashboard_itadmin.php');
require_once '../includes/config.php';
?>
<!DOCTYPE html>
<html lang="en-US">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reservation | AMRMS</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.css">
<style>
    .main-content { height: auto !important; padding: 50px 20px; }
    h2 { font-size: 28px; color: #2d7a2d; font-weight: bold; text-align: center; text-shadow: 1px 1px 3px rgba(14,4,4,0.7); margin: 10px 0 0 0; padding-bottom: 1px; }
    
    .search-bar { display: flex; align-items: center; flex-wrap: wrap; gap: 10px; margin-top: 15px; }
    select, input[type="text"], input[type="date"], button { padding: 8px 12px; font-size: 13px; border: 1px solid #ccc; border-radius: 6px; }
    button { background-color: #2d7a2d; color: white; border: none; cursor: pointer; transition: background-color 0.3s; }
    button:hover:not(:disabled) { background-color: #256725; }
    button:disabled { background-color: #a0a0a0; cursor: not-allowed; opacity: 0.6; }
    
    .flatpickr-input { padding: 8px 12px !important; font-size: 14px !important; border: 1px solid #ccc !important; border-radius: 6px !important; background: white !important; color: #333 !important; cursor: pointer !important; width: 130px !important; box-sizing: border-box !important; }
    .flatpickr-input:focus { outline: none !important; border-color: #2d7a2d !important; }
    label { color: #000000; font-weight: bold; font-size: 14px; margin-right: 5px; text-shadow: 1px 1px 3px rgba(14,4,4,0.7); }
    
    .table-container {
      border: 1px solid #ddd; 
      border-radius: 8px; 
      background-color: white;
      box-shadow: 0 2px 5px rgba(0,0,0,0.1); 
      margin-top: 10px; 
      max-height: 330px; 
      overflow-y: auto;
      overflow-x: auto;
    }
    table { width: 100%; border-collapse: collapse; background-color: white; table-layout: fixed; }
    
    table th:nth-child(1),  table td:nth-child(1)  { width: 100px; white-space: nowrap; } 
    table th:nth-child(2),  table td:nth-child(2)  { width: 130px; }                      
    table th:nth-child(3),  table td:nth-child(3)  { width: 150px; word-break: break-word; } 
    table th:nth-child(4),  table td:nth-child(4)  { width: 100px; white-space: nowrap; } 
    table th:nth-child(5),  table td:nth-child(5)  { width: 140px; word-break: break-word; } 
    table th:nth-child(6),  table td:nth-child(6)  { width: 70px;  }                      
    table th:nth-child(7),  table td:nth-child(7)  { width: 80px;  }                      
    table th:nth-child(8),  table td:nth-child(8)  { width: 100px; }                      
    table th:nth-child(9),  table td:nth-child(9)  { width: 110px; }                      
    table th:nth-child(10), table td:nth-child(10) { width: 90px;  }                      
    table th:nth-child(11), table td:nth-child(11) { width: 100px; }                      
    table th:nth-child(12), table td:nth-child(12) { width: 110px; }                      
    table th:nth-child(13), table td:nth-child(13) { width: 90px;  white-space: nowrap; } 
    table th:nth-child(14), table td:nth-child(14) { width: 80px;  }                      
    
    th, td { 
      padding: 10px 10px; 
      text-align: center !important; 
      border-bottom: 1px solid #ddd; 
      vertical-align: middle; 
      line-height: 1.35;
      font-size: 13px; 
      box-sizing: border-box;
    }
    
    thead { position: sticky; top: 0; z-index: 5; }
    th { background-color: #2d7a2d; color: white; font-weight: 600; white-space: nowrap; }

    tbody tr { cursor: pointer; }
    tr:hover { background-color: #f9fafb; }
    
    .view-container { display: flex; justify-content: center; align-items: center; gap: 10px; margin-top: 20px; }
    .view-container button { background-color: #2d7a2d; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-size: 15px; transition: .2s; display: inline-flex; align-items: center; gap: 6px; font-weight: 600; }
    .view-container button:hover:not(:disabled) { background-color: #1a5c1a; transform: scale(1.03); }
    .view-container button:disabled { opacity: 0.5; cursor: not-allowed; transform: none !important; }

    .badge { display: inline-block; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; text-align: center; }
    .badge-Pending   { background: #fef3c7; color: #92400e; }
    .badge-Approved  { background: #d1fae5; color: #065f46; }
    .badge-Completed { background: #dbeafe; color: #1e40af; }
    .badge-Declined  { background: #fee2e2; color: #991b1b; }

    .ibox { background: #f0fdf4; border-radius: 8px; padding: 10px 13px; border: 1px solid #e5f0e8; text-align: left; }
    .ibox-label { font-size: 10px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px; }
    .ibox-value { font-size: 13px; font-weight: 600; color: #1f2937; line-height: 1.35; }
    .ibox-total { background: #f0fdf4; border-radius: 8px; padding: 10px 13px; border: 1px solid #bbf7d0; text-align: left; }

    .spinner { border: 4px solid #ddd; border-top: 4px solid #2d7a2d; border-radius: 50%; width: 36px; height: 36px; animation: spin 1s linear infinite; margin: 0 auto 10px; }
    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

    .preparedBy, .print-subtitle, .report-date { display: none;}

    @media print {
      .search-bar, .view-container, .usernames-bar, header, nav, .header, .navbar, .dashboard-header, .top-bar { display: none !important; }
      .main-content h2 { display: block !important; color: #2d7a2d !important; text-shadow: none !important; margin: 0 0 5px 0 !important; padding: 0 !important; text-align: center !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
      .print-subtitle { display: block !important; text-align: center; font-size: 14px; font-weight: bold; color: #333; margin-bottom: 15px; }
      .report-date { display: block !important; text-align: left; font-size: 12px; color: #333; margin-bottom: 10px; font-weight: bold; }
      body { background: white !important; overflow: visible !important; margin: 0 !important; padding: 0 !important; }
      .main-content { height: auto !important; overflow: visible !important; padding: 0 !important; margin-top: 0 !important; }
      .table-container { border: none !important; box-shadow: none !important; overflow: visible !important; margin-top: 0 !important; max-height: none !important; }
      table { table-layout: auto !important; width: 100% !important; }
      thead { position: static !important; display: table-header-group !important; }
      tbody tr { display: table-row !important; }
      table th, table td, table th:nth-child(n), table td:nth-child(n) { width: auto !important; white-space: normal !important; word-break: normal !important; word-wrap: normal !important; padding: 8px 6px !important; font-size: 11px !important; text-align: center !important; }
      th { background-color: #2d7a2d !important; color: white !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
      .preparedBy { display: block !important;text-align:left;  margin-top: 85px !important; font-size: 15px !important; color: #000000 !important; }
    }

    @media(max-width:640px) {
        .main-content { padding: 10px; }
    }
</style>
</head>
<body>
<div class="main-content">

    <h2>List of Reservations</h2>

    <div class="print-subtitle" id="printSubtitle"></div>

    <div class="search-bar">
        <select id="searchField" onchange="handleFieldChange()">
            <option value="all">All</option>
            <option value="name">Farmer Name</option>
            <option value="association">Association</option>
            <option value="farm_location">Farm Location</option>
            <option value="status">Status</option>
            <option value="date">Reservation Date</option>
        </select>
        <div id="dynamicInputs" style="display:inline-flex; gap:10px; align-items:center;"></div>
        <button id="searchBtn" onclick="doSearch()" style="display:none;" disabled>Search</button>
        <span style="margin-left:auto; color:#ffffff;">
            <span id="totalCount"></span>
        </span>
    </div>

    <div class="report-date" id="reportDateDisplay"></div>

    <div class="table-container" id="printSection">
        <table id="reservationTable">
            <thead>
                <tr>
                    <th>Reservation Date</th>
                    <th>Farmer Name</th>
                    <th>Farmer Address</th>
                    <th>Contact No.</th>
                    <th>Farm Location</th>
                    <th>Farm Lot</th>
                    <th>Farm Size</th>
                    <th>Machine Type</th>
                    <th>Machine Name</th>
                    <th>Rent/Ha</th>
                    <th>Total Amount</th>
                    <th>Association</th>
                    <th>Schedule</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody id="reservationTableBody">
                <tr><td colspan="14" style="text-align:center; padding:30px; color:#555;"><div class="spinner"></div>Loading reservations...</td></tr>
            </tbody>
        </table>

        <div class="preparedBy">
            Prepared By:
            <span style="font-weight:bold;">IT Admin</span>
        </div>
    </div>

    <div class="view-container">
        <button id="btnPrint" onclick="printList()">Print</button>
    </div>
</div>

<div id="viewModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:9999; justify-content:center; align-items:center; backdrop-filter:blur(3px);">
  <div style="background:white; border-radius:14px; width:750px; max-width:96%; max-height:90vh; overflow-y:auto; box-shadow:0 20px 40px rgba(0,0,0,0.25);">
    <div style="background:linear-gradient(135deg,#2d7a2d,#1a5c1a); padding:16px 24px; border-radius:14px 14px 0 0; display:flex; justify-content:space-between; align-items:center; position:sticky; top:0; z-index:10;">
      <div>
        <div style="color:white; font-size:17px; font-weight:700;" id="modalTitle">Reservation Details</div>
        <!-- <div style="color:rgba(255,255,255,0.8); font-size:12px; margin-top:2px;" id="modalSubtitle">Loading...</div> -->
      </div>
      <div style="display:flex; gap:10px; align-items:center;">
        <button onclick="closeViewModal()" style="width:32px; height:32px; background:rgba(255,255,255,0.2); border:none; border-radius:50%; color:white; font-size:18px; cursor:pointer; display:flex; align-items:center; justify-content:center;">&times;</button>
      </div>
    </div>
    <div id="modalBody" style="padding:18px 20px 20px;">
      <div style="text-align:center; padding:40px; color:#6b7280;"><div class="spinner"></div>Loading...</div>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.js"></script>
<script>
const pathParts = window.location.pathname.split('/');
pathParts.pop();
const baseUrl = pathParts.join('/') + '/';

let selectedBookingId   = null;
let selectedBooking     = null;
let allReservations = [];
let currentReservations = [];
let fpFrom = null, fpTo = null;

function formatReadableDate(dateStr) {
    if (!dateStr) return '';
    const parts = dateStr.split('/');
    if (parts.length !== 3) return dateStr;
    const dateObj = new Date(parts[2], parts[0] - 1, parts[1]);
    return dateObj.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
}

function formatLocalDate(dateObj) {
    const year = dateObj.getFullYear();
    const month = String(dateObj.getMonth() + 1).padStart(2, '0');
    const day = String(dateObj.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function buildFarmerName(r) {
    if (r.farmer_first_name || r.farmer_last_name) {
        return [r.farmer_first_name || '', r.farmer_middle_name || '', r.farmer_last_name || ''].filter(Boolean).join(' ');
    }
    return r.farmer_name || '—';
}

function buildFarmerAddr(r) {
    const parts = [r.farmer_barangay || '', r.farmer_municipality || '', r.farmer_province || ''].filter(Boolean);
    return parts.length ? parts.join(', ') : '—';
}

function buildFarmLocation(r) {
    const parts = [r.lot_barangay || '', r.lot_municipality || '', r.lot_province || ''].filter(Boolean);
    return parts.length ? parts.join(', ') : '—';
}

function setupFlatpickrs() {
    fpFrom = flatpickr('#filterFromDate', {
        dateFormat: 'm/d/Y',
        allowInput: false,
        onChange(selectedDates) {
            if (selectedDates.length > 0) {
                const selected = selectedDates[0];
                const minToDate = new Date(selected);
                minToDate.setDate(minToDate.getDate() + 1);
                
                if (fpTo) {
                    fpTo.set('minDate', minToDate);
                    if (fpTo.selectedDates[0] && fpTo.selectedDates[0] <= selected) {
                        fpTo.clear();
                    }
                }
            } else if (fpTo) {
                fpTo.set('minDate', null);
            }
            validateInput();
        }
    });

    fpTo = flatpickr('#filterToDate', {
        dateFormat: 'm/d/Y',
        allowInput: false,
        onChange: validateInput
    });
}

function handleFieldChange() {
    const field = document.getElementById('searchField').value;
    const area  = document.getElementById('dynamicInputs');
    const btn   = document.getElementById('searchBtn');

    document.getElementById('printSubtitle').textContent = '';

    if (fpFrom) { fpFrom.destroy(); fpFrom = null; }
    if (fpTo)   { fpTo.destroy();   fpTo   = null; }
    area.innerHTML = '';
    btn.style.display = (field === 'all') ? 'none' : 'inline-block';
    btn.disabled = true;

    if (field === 'name') {
        area.innerHTML = `<input type="text" id="inputName" placeholder="Enter farmer name..." oninput="validateInput()">`;
    } else if (field === 'association') {
        area.innerHTML = `<input type="text" id="inputAssociation" placeholder="Enter association name..." oninput="validateInput()">`;
    } else if (field === 'farm_location') {
        area.innerHTML = `<input type="text" id="inputFarmLoc" placeholder="barangay, municipality" oninput="validateInput()">`;
    } else if (field === 'status') {
        area.innerHTML = `
            <select id="filterStatus" onchange="validateInput()">
                <option value="" disabled selected hidden>Select Status</option>
                <option value="Pending">Pending</option>
                <option value="Approved">Approved</option>
                <option value="Completed">Completed</option>
                <option value="Declined">Declined</option>
            </select>
            <label>From</label>
            <input type="text" id="filterFromDate" placeholder="mm/dd/yyyy" readonly>
            <label>To</label>
            <input type="text" id="filterToDate" placeholder="mm/dd/yyyy" readonly>`;
        setTimeout(setupFlatpickrs, 0);
    } else if (field === 'date') {
        area.innerHTML = `
            <label>From</label>
            <input type="text" id="filterFromDate" placeholder="mm/dd/yyyy" readonly>
            <label>To</label>
            <input type="text" id="filterToDate" placeholder="mm/dd/yyyy" readonly>`;
        setTimeout(setupFlatpickrs, 0);
    }

    displayReservations(allReservations);
}

function validateInput() {
    const field = document.getElementById('searchField').value;
    const btn   = document.getElementById('searchBtn');
    let hasValue = false;

    if (field === 'name') {
        const val = (document.getElementById('inputName')||{}).value || '';
        hasValue = val.trim() !== '';
    } else if (field === 'association') {
        const val = (document.getElementById('inputAssociation')||{}).value || '';
        hasValue = val.trim() !== '';
    } else if (field === 'farm_location') {
        const val = (document.getElementById('inputFarmLoc')||{}).value || '';
        hasValue = val.trim() !== '';
    } else if (field === 'status') {
        const status = (document.getElementById('filterStatus')||{}).value || '';
        const fromDate = (document.getElementById('filterFromDate')||{}).value || '';
        const toDate = (document.getElementById('filterToDate')||{}).value || '';
        hasValue = status !== '' || (fromDate !== '' && toDate !== '');
    } else if (field === 'date') {
        const fromDate = (document.getElementById('filterFromDate')||{}).value || '';
        const toDate = (document.getElementById('filterToDate')||{}).value || '';
        hasValue = fromDate !== '' && toDate !== '';
    }

    btn.disabled = !hasValue;

    if (!hasValue && field !== 'all') {
        document.getElementById('printSubtitle').textContent = '';
        displayReservations(allReservations);
    }
}

function toYMD(s) {
    if (!s) return '';
    const p = s.split('/');
    return p.length !== 3 ? '' : p[2]+'-'+p[0].padStart(2,'0')+'-'+p[1].padStart(2,'0');
}

function doSearch() {
    const field = document.getElementById('searchField').value;
    const subtitleEl = document.getElementById('printSubtitle');
    subtitleEl.textContent = '';

    if (field === 'all') { displayReservations(allReservations); return; }

    const rawFrom = (document.getElementById('filterFromDate')||{}).value || '';
    const rawTo   = (document.getElementById('filterToDate')||{}).value || '';

    if ((field === 'status' || field === 'date') && rawFrom && rawTo) {
        subtitleEl.textContent = `From ${formatReadableDate(rawFrom)} to ${formatReadableDate(rawTo)}`;
    }

    let results = allReservations.filter(r => {
        if (field === 'name') {
            const query = (document.getElementById('inputName').value || '').toLowerCase().trim();
            return buildFarmerName(r).toLowerCase().includes(query);
        }
        if (field === 'association') {
            const query = (document.getElementById('inputAssociation').value || '').toLowerCase().trim();
            return (r.association_name || '').toLowerCase().includes(query);
        }
        if (field === 'farm_location') {
            const query = (document.getElementById('inputFarmLoc').value || '').toLowerCase().trim();
            const parts = query.split(',').map(p => p.trim());
            const brgy = (r.lot_barangay || '').toLowerCase();
            const muni = (r.lot_municipality || '').toLowerCase();

            if (parts.length > 1 && parts[1] !== '') {
                return brgy.includes(parts[0]) && muni.includes(parts[1]);
            } else {
                return brgy.includes(query) || muni.includes(query);
            }
        }
        if (field === 'status') {
            const statusVal = (document.getElementById('filterStatus').value || '');
            const fromDateVal = toYMD(rawFrom);
            const toDateVal = toYMD(rawTo);

            let matchStatus = statusVal ? (r.status === statusVal) : true;
            let matchDate = true;
            
            const rDate = r.booking_date ? r.booking_date.split(' ')[0] : '';
            if (fromDateVal && rDate < fromDateVal) matchDate = false;
            if (toDateVal && rDate > toDateVal) matchDate = false;

            return matchStatus && matchDate;
        }
        if (field === 'date') {
            const fromDateVal = toYMD(rawFrom);
            const toDateVal = toYMD(rawTo);
            
            const rDate = r.booking_date ? r.booking_date.split(' ')[0] : '';
            if (fromDateVal && rDate < fromDateVal) return false;
            if (toDateVal && rDate > toDateVal) return false;
            return true;
        }
        return true;
    });

    if (results.length > 0) {
        displayReservations(results);
    } else {
        showEmpty('No records found');
    }
}

function loadAll() {
    showLoading(); resetSelection();
    fetch(baseUrl + 'get_all_reservations.php')
        .then(r => { if(!r.ok) throw new Error('HTTP '+r.status); return r.text(); })
        .then(text => {
            try {
                const data = JSON.parse(text);
                if (data.success && data.reservations?.length) {
                    allReservations = data.reservations;
                    displayReservations(allReservations);
                } else showEmpty('No records found');
            } catch(e) { showError('Invalid response from server'); }
        })
        .catch(err => showError(err.message));
}

function displayReservations(reservations) {
    currentReservations = reservations;
    const tbody = document.getElementById('reservationTableBody');
    const printBtn = document.getElementById('btnPrint');
    tbody.innerHTML = '';
    resetSelection();

    if (!reservations || !reservations.length) {
        showEmpty('No records found');
        return;
    }

    printBtn.disabled = false;
    document.getElementById('totalCount').textContent = reservations.length;

    reservations.forEach((r) => {
        const row = document.createElement('tr');

        const sz         = parseFloat(r.farm_size) || 0;
        const price      = parseFloat(r.price_per_hectare) || 0;
        const total      = sz * price;
        const farmerName = buildFarmerName(r);
        const farmerAddr = buildFarmerAddr(r);
        const farmLoc    = buildFarmLocation(r);
        const phone      = r.farmer_phone || '—';
        const status     = r.status || '';

        row.innerHTML =
            '<td>'+fmtDate(r.created_at)+'</td>'+
            '<td>'+esc(farmerName)+'</td>'+
            '<td>'+esc(farmerAddr)+'</td>'+
            '<td>'+esc(phone)+'</td>'+
            '<td>'+esc(farmLoc)+'</td>'+
            '<td>'+esc(r.lot_number||'—')+'</td>'+
            '<td>'+sz.toFixed(2)+' ha</td>'+
            '<td>'+esc(r.machine_type||'—')+'</td>'+
            '<td>'+esc(r.machine_name||'—')+'</td>'+
            '<td>₱'+fmt(price)+'</td>'+
            '<td>₱'+fmt(total)+'</td>'+
            '<td>'+esc(r.association_name||'—')+'</td>'+
            '<td>'+fmtDate(r.booking_date)+'</td>'+
            '<td><span style="font-weight:bold;">'+esc(status)+'</span></td>';

        row.addEventListener('click', () => { 
            selectRow(row, r); 
            openViewModal(); 
        });

        tbody.appendChild(row);
    });
}

function selectRow(row, booking) {
    document.querySelectorAll('#reservationTable tbody tr.selected-row').forEach(r => r.classList.remove('selected-row'));
    row.classList.add('selected-row');
    selectedBooking   = booking;
    selectedBookingId = booking.booking_id;
}

function resetSelection() {
    document.querySelectorAll('#reservationTable tbody tr.selected-row').forEach(r => r.classList.remove('selected-row'));
    selectedBooking   = null;
    selectedBookingId = null;
}

function openViewModal() {
    if (!selectedBookingId) return;
    document.getElementById('viewModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
    /* document.getElementById('modalTitle').textContent    = 'Reservation Details';
    document.getElementById('modalSubtitle').textContent = 'Loading...'; */
    document.getElementById('modalBody').innerHTML =
        '<div style="text-align:center; padding:40px; color:#6b7280;"><div class="spinner"></div>Loading...</div>';

    fetch(baseUrl + 'get_reservation_detail.php?id=' + selectedBookingId)
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                document.getElementById('modalBody').innerHTML = '<p style="color:red; padding:20px;">Failed to load reservation.</p>';
                return;
            }
            const r = data.reservation;

            const farmSize   = parseFloat(r.farm_size)        || 0;
            const pricePerHa = parseFloat(r.price_per_hectare) || 0;
            const total      = farmSize * pricePerHa;
            const farmerName = buildFarmerName(r);
            const farmerAddr = buildFarmerAddr(r);
            const farmLoc    = buildFarmLocation(r);
            const specificLoc = r.farm_location || '—';

            /* document.getElementById('modalTitle').textContent = 'Reservation #' + String(r.booking_id).padStart(5,'0');
            document.getElementById('modalSubtitle').textContent = 'Applied: ' + fmtDate(r.created_at) + '  |  Schedule: ' + fmtDate(r.booking_date); */

            document.getElementById('modalBody').innerHTML = `
                <div style="display:flex; justify-content:flex-end; margin-bottom:12px;">
                    <span class="badge badge-${esc(r.status)}">${esc(r.status)}</span>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:8px;">
                    ${iBox('Date Applied', fmtDate(r.created_at))}
                    ${iBox('Schedule', fmtDate(r.booking_date))}
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px; margin-bottom:8px;">
                    ${iBox('Farmer Name', esc(farmerName))}
                    ${iBox('Machine Type', esc(r.machine_type || '—'))}
                    ${iBox('Machine Name', esc(r.machine_name || '—'))}
                </div>

                <div style="display:grid; grid-template-columns:2fr 1fr 1fr; gap:8px; margin-bottom:8px;">
                    ${iBox('Farmer Address', esc(farmerAddr))}
                    ${iBox('Contact No.', esc(r.farmer_phone || '—'))}
                    ${iBox('Email', esc(r.farmer_email || '—'))}
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr 1fr 1fr 1fr; gap:8px; margin-bottom:8px;">
                    ${iBox('Farm Lot', esc(r.lot_number || '—'))}
                    ${iBox('Farm Size', farmSize.toFixed(2) + ' ha')}
                    ${iBox('Price / ha', '₱' + fmt(pricePerHa))}
                    <div class="ibox-total">
                        <div class="ibox-label">Total Amount</div>
                        <div style="font-size:18px; font-weight:800; color:#16a34a; margin-top:2px;">₱${fmt(total)}</div>
                    </div>
                    ${iBox('Association', esc(r.association_name || 'N/A'))}
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:8px;">
                    <div class="ibox">
                        <div class="ibox-label">Farm Location</div>
                        <div class="ibox-value">${esc(farmLoc)}</div>
                    </div>
                    <div class="ibox">
                        <div class="ibox-label">Specific Location</div>
                        <div class="ibox-value">${esc(specificLoc)}</div>
                    </div>
                </div>

                ${r.notes ? `
                <div style="margin-top:8px; background:#fffbeb; border:1px solid #fde68a; border-radius:8px; padding:10px 13px; font-size:12px; color:#92400e;">
                    <span style="font-weight:700; font-size:10px; text-transform:uppercase;">Notes:&nbsp;</span>${esc(r.notes)}
                </div>` : ''}`;
        })
        .catch(() => {
            document.getElementById('modalBody').innerHTML = '<p style="color:red; padding:20px;">Error loading reservation details.</p>';
        });
}

function iBox(label, value) {
    return `<div class="ibox">
        <div class="ibox-label">${label}</div>
        <div class="ibox-value">${value}</div>
    </div>`;
}

function closeViewModal() {
    document.getElementById('viewModal').style.display = 'none';
    document.body.style.overflow = '';
    resetSelection();
}

function printList() {
    const now = new Date();
    const formattedReportDate = now.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }) + 
        ' ' + now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    document.getElementById('reportDateDisplay').textContent = 'Report Date: ' + formattedReportDate;
    
    window.print();
}

function showLoading() {
    const printBtn = document.getElementById('btnPrint');
    if (printBtn) printBtn.disabled = true;
    document.getElementById('totalCount').textContent = '0';
    document.getElementById('reservationTableBody').innerHTML =
        '<tr><td colspan="14" style="text-align:center; padding:30px; color:#555;"><div class="spinner"></div>Loading reservations...</td></tr>';
}
function showEmpty(msg) {
    const printBtn = document.getElementById('btnPrint');
    if (printBtn) printBtn.disabled = true;
    document.getElementById('totalCount').textContent = '0';
    document.getElementById('reservationTableBody').innerHTML =
        `<tr><td colspan="14" style="text-align:center; padding:20px; color:#555;">${msg}</td></tr>`;
}
function showError(m) {
    const printBtn = document.getElementById('btnPrint');
    if (printBtn) printBtn.disabled = true;
    document.getElementById('totalCount').textContent = '0';
    document.getElementById('reservationTableBody').innerHTML =
        `<tr><td colspan="14" style="text-align:center; padding:20px; color:red;">Error loading data: ${esc(m)}</td></tr>`;
}
function fmtDate(d) {
    if (!d) return 'N/A';
    const dt = new Date(d);
    return String(dt.getMonth()+1).padStart(2,'0')+'/'+String(dt.getDate()).padStart(2,'0')+'/'+dt.getFullYear();
}
function fmt(n) {
    return parseFloat(n||0).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});
}
function esc(t) {
    if (!t) return '';
    return String(t).replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
}

window.addEventListener('DOMContentLoaded', () => { loadAll(); });
document.getElementById('viewModal').addEventListener('click', function(e) { if(e.target===this) closeViewModal(); });
document.addEventListener('keydown', e => { if(e.key==='Escape') closeViewModal(); });
</script>
</body>
</html>