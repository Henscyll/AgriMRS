<?php
include('da_header.php');
require_once '../includes/config.php';

$limit = 10; 
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$searchField = $_POST['search_field'] ?? $_GET['search_field'] ?? 'all';
$searchTerm  = $_POST['search_term']  ?? $_GET['search_term']  ?? '';
$statusTerm  = $_POST['search_status'] ?? $_GET['search_status'] ?? '';
$fromDate    = $_POST['from_date']     ?? $_GET['from_date']     ?? '';
$toDate      = $_POST['to_date']       ?? $_GET['to_date']       ?? '';
$where       = '';

$searchTerm  = trim($searchTerm);
$statusTerm  = trim($statusTerm);
$fromDate    = trim($fromDate);
$toDate      = trim($toDate);

$conditions = [];

if ($searchField !== 'all') {
    if ($searchField === 'name' && $searchTerm !== '') {
        $safe = $conn->real_escape_string($searchTerm);
        $conditions[] = "CONCAT(f.first_name, ' ', COALESCE(f.middle_name, ''), ' ', f.last_name) LIKE '%$safe%'";
    } elseif ($searchField === 'address' && $searchTerm !== '') {
        $parts = array_map('trim', explode(',', $searchTerm));
        if (count($parts) > 1 && !empty($parts[1])) {
            $safeBrgy = $conn->real_escape_string($parts[0]);
            $safeMuni = $conn->real_escape_string($parts[1]);
            $conditions[] = "f.barangay LIKE '%$safeBrgy%' AND f.municipality LIKE '%$safeMuni%'";
        } else {
            $safe = $conn->real_escape_string($searchTerm);
            $conditions[] = "(f.barangay LIKE '%$safe%' OR f.municipality LIKE '%$safe%')";
        }
    } elseif ($searchField === 'status') {
        if ($statusTerm !== '') {
            $safeStatus = $conn->real_escape_string($statusTerm);
            $conditions[] = "f.status = '$safeStatus'";
        }
        if ($fromDate !== '' && $toDate !== '') {
            $safeFrom = $conn->real_escape_string($fromDate);
            $safeTo   = $conn->real_escape_string($toDate);
            $conditions[] = "DATE(f.created_at) BETWEEN '$safeFrom' AND '$safeTo'";
        }
    } elseif ($searchField === 'date') {
        if ($fromDate !== '' && $toDate !== '') {
            $safeFrom = $conn->real_escape_string($fromDate);
            $safeTo   = $conn->real_escape_string($toDate);
            $conditions[] = "DATE(f.created_at) BETWEEN '$safeFrom' AND '$safeTo'";
        }
    }
}

if (count($conditions) > 0) {
    $where = "WHERE " . implode(" AND ", $conditions);
}

$totalResult = $conn->query("SELECT COUNT(DISTINCT f.id) AS total FROM farmers f $where");
$totalRows   = $totalResult->fetch_assoc()['total'];
$totalPages  = ceil($totalRows / $limit);

$query = "SELECT f.id,
          CONCAT(f.first_name, ' ',
                 CASE WHEN f.middle_name IS NOT NULL AND f.middle_name != '' 
                      THEN CONCAT(LEFT(f.middle_name,1), '. ') ELSE '' END,
                 f.last_name) AS full_name,
          f.email, f.province, f.municipality, f.barangay, f.phone, f.status,
          DATE_FORMAT(f.created_at, '%m/%d/%Y') AS registered_at,
          COUNT(DISTINCT fl.id) AS lot_count,
          COALESCE(latest_lot.farm_size, 0) AS display_farm_size,
          COALESCE(latest_assoc.name, direct_assoc.name) AS association_name
          FROM farmers f 
          LEFT JOIN farmer_lots fl ON f.id = fl.farmer_id
          LEFT JOIN associations direct_assoc ON f.association_id = direct_assoc.id
          LEFT JOIN (
              SELECT fl_latest.farmer_id, fl_latest.farm_size
              FROM farmer_lots fl_latest
              WHERE fl_latest.id = (
                  SELECT MAX(fl_sub.id) 
                  FROM farmer_lots fl_sub 
                  WHERE fl_sub.farmer_id = fl_latest.farmer_id
              )
          ) latest_lot ON f.id = latest_lot.farmer_id
          LEFT JOIN (
              SELECT fl1.farmer_id, a.name
              FROM farmer_lots fl1
              JOIN associations a ON fl1.association_id = a.id
              WHERE fl1.status = 'Active'
                AND fl1.id = (
                    SELECT MAX(fl2.id) 
                    FROM farmer_lots fl2 
                    WHERE fl2.farmer_id = fl1.farmer_id 
                      AND fl2.status = 'Active' 
                      AND fl2.association_id IS NOT NULL
                )
          ) latest_assoc ON f.id = latest_assoc.farmer_id
          $where 
          GROUP BY f.id
          ORDER BY f.created_at DESC
          LIMIT $limit OFFSET $offset";
$result = $conn->query($query);

$printFromFormatted = (!empty($fromDate)) ? date('F j, Y', strtotime($fromDate)) : '';
$printToFormatted   = (!empty($toDate)) ? date('F j, Y', strtotime($toDate)) : '';
$hasDateRange       = ($searchField === 'status' || $searchField === 'date') && !empty($fromDate) && !empty($toDate);
?>
<!DOCTYPE html>
<html lang="en-US">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Farmers | AMRMS</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.css">
<style>
    .main-content { height: auto !important; padding: 50px 20px; }
    h2 { font-size: 28px; color: #2d7a2d; font-weight: bold; text-align: center; text-shadow: 1px 1px 3px rgba(14,4,4,0.7); margin: 10px 0 0 0; padding-bottom: 1px; }
    form { display: flex; align-items: center; flex-wrap: wrap; gap: 10px; margin-top: 15px; }
    select, input[type="text"], input[type="date"], button { padding: 8px 12px; font-size: 13px; border: 1px solid #ccc; border-radius: 6px; }
    button { background-color: #2d7a2d; color: white; border: none; cursor: pointer; transition: background-color 0.3s; }
    button:hover:not(:disabled) { background-color: #256725; }
    button:disabled { background-color: #a5d6a5; cursor: not-allowed; opacity: 0.6; }
    .flatpickr-input { padding: 8px 12px !important; font-size: 14px !important; border: 1px solid #ccc !important; border-radius: 6px !important; background: white !important; color: #333 !important; cursor: pointer !important; width: 130px !important; box-sizing: border-box !important; }
    .flatpickr-input:focus { outline: none !important; border-color: #2d7a2d !important; }
    
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
    
    table th:nth-child(1), table td:nth-child(1) { width: 100px; white-space: nowrap; }
    table th:nth-child(2), table td:nth-child(2) { width: 140px; }
    table th:nth-child(3), table td:nth-child(3) { width: 170px; word-break: break-all; }
    table th:nth-child(4), table td:nth-child(4) { width: 110px; white-space: nowrap; }
    table th:nth-child(5), table td:nth-child(5) { width: 220px; word-break: break-word; }
    table th:nth-child(6), table td:nth-child(6) { width: 120px; }
    table th:nth-child(7), table td:nth-child(7) { width: 80px; }
    table th:nth-child(8), table td:nth-child(8) { width: 80px; }
    table th:nth-child(9), table td:nth-child(9) { width: 70px; }
    
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
    label { color: #000000; font-weight: bold; font-size: 14px; margin-right: 5px; text-shadow: 1px 1px 3px rgba(14,4,4,0.7); }
    .badge { display: inline-block; padding: 3px 7px; border-radius: 12px; font-size: 11px; font-weight: 600; } 
    .badge-assoc { display: inline-block; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; color: #000000; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 110px; }
    .badge-no-assoc { display: inline-block; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; background: #f3f4f6; color: #9ca3af; }
    .preparedBy, .print-subtitle, .report-date { display: none;}
    
    @media print {
      form, .view-container, .usernames-bar, header, nav, .header, .navbar, .dashboard-header, .top-bar { display: none !important; }
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
      .preparedBy { display: block !important; text-align: left; margin-top: 85px !important; font-size: 15px !important; color: #000000 !important; }
    }

    @media(max-width:640px) { .main-content { padding: 10px; } }
</style>
</head>
<body>
<div class="main-content">
    <h2>List of Farmers</h2>

    <?php if ($hasDateRange): ?>
        <div class="print-subtitle">From <?= htmlspecialchars($printFromFormatted) ?> to <?= htmlspecialchars($printToFormatted) ?></div>
    <?php endif; ?>

    <form method="POST" action="" id="searchForm">
        <select name="search_field" id="search_field" onchange="handleFieldChange()">
            <option value="all"     <?= $searchField==='all'     ? 'selected':'' ?>>All</option>
            <option value="name"    <?= $searchField==='name'    ? 'selected':'' ?>>Farmer Name</option>
            <option value="address" <?= $searchField==='address' ? 'selected':'' ?>>Farmer Address</option>
            <option value="status"  <?= $searchField==='status'  ? 'selected':'' ?>>Status</option>
            <option value="date"    <?= $searchField==='date'    ? 'selected':'' ?>>Date Registered</option>
        </select>

        <input type="text" name="search_term" id="search_term" placeholder="Search..." value="<?= htmlspecialchars($searchTerm) ?>" style="display:none;" oninput="validateForm()">

        <select name="search_status" id="search_status" style="display:none;" onchange="validateForm()">
            <option value="" disabled <?= $statusTerm==='' ? 'selected':'' ?> hidden>Select Status</option>
            <option value="Active"   <?= $statusTerm==='Active'   ? 'selected':'' ?>>Active</option>
            <option value="Inactive" <?= $statusTerm==='Inactive' ? 'selected':'' ?>>Inactive</option>
        </select>

        <label for="from_date_display" id="from_label" style="display:none;">From</label>
        <input type="text" id="from_date_display" placeholder="mm/dd/yyyy" readonly style="display:none; width:130px;">
        <input type="hidden" name="from_date" id="from_date" value="<?= htmlspecialchars($fromDate) ?>">

        <label for="to_date_display" id="to_label" style="display:none;">To</label>
        <input type="text" id="to_date_display" placeholder="mm/dd/yyyy" readonly style="display:none; width:130px;">
        <input type="hidden" name="to_date" id="to_date" value="<?= htmlspecialchars($toDate) ?>">

        <button type="submit" id="searchBtn" style="display:none;" disabled>Search</button>
    </form>

    <div class="report-date">Report Date: <?= date('F j, Y g:i A') ?></div>

    <div class="table-container" id="printSection">
        <table id="farmerTable">
            <thead>
                <tr>
                    <th>Date Registered</th>
                    <th>Farmer Name</th>
                    <th>Email</th>
                    <th>Contact No.</th>
                    <th>Farmer Address</th>
                    <th>Association</th>
                    <th>Farmer Lots</th>
                    <th>Farm Size</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($result && $result->num_rows > 0):
                while ($row = $result->fetch_assoc()): ?>
                <tr data-id="<?= $row['id'] ?>" data-status="<?= htmlspecialchars($row['status']) ?>">
                    <td><?= htmlspecialchars($row['registered_at']) ?></td>
                    <td><?= htmlspecialchars($row['full_name']) ?></td>
                    <td><?= htmlspecialchars($row['email']) ?></td>
                    <td><?= htmlspecialchars($row['phone']) ?></td>
                    <td><?= htmlspecialchars($row['barangay'].', '.$row['municipality'].', '.$row['province']) ?></td>
                    <td>
                        <?php if ($row['association_name']): ?>
                            <span class="badge-assoc" title="<?= htmlspecialchars($row['association_name']) ?>"><?= htmlspecialchars($row['association_name']) ?></span>
                        <?php else: ?>
                            <span class="badge-no-assoc">None</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge"><?= $row['lot_count'] ?> lot(s)</span></td>
                    <td><?= number_format($row['display_farm_size'] ?? 0, 2) ?> ha</td>
                    <td><span style="font-weight:bold;"><?= htmlspecialchars($row['status']) ?></span></td>
                </tr>
            <?php endwhile; else: ?>
                <tr><td colspan="9" style="text-align:center; padding:20px; color:#555;">No records found</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        
        <div class="preparedBy">
            Prepared By:
            <span style="font-weight:bold;">DA Official</span>
        </div>
    </div>

    <div class="view-container">
        <button id="printBtn" <?= ($totalRows == 0) ? 'disabled' : '' ?>>Print</button>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.js"></script>
<script>
let wasSearched = <?= ($searchField !== 'all' && ($searchTerm !== '' || $statusTerm !== '' || ($fromDate !== '' && $toDate !== ''))) ? 'true' : 'false' ?>;

function formatLocalDate(dateObj) {
    const year = dateObj.getFullYear();
    const month = String(dateObj.getMonth() + 1).padStart(2, '0');
    const day = String(dateObj.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

document.addEventListener('DOMContentLoaded', () => {
    const tableBody = document.querySelector('#farmerTable tbody');
    if (tableBody) {
        tableBody.addEventListener('dblclick', (e) => {
            const tr = e.target.closest('tr[data-id]');
            if (tr) {
                const farmerId = tr.getAttribute('data-id');
                window.location.href = `da_farmers_profile.php?id=${farmerId}`;
            }
        });
    }

    toggleInputs();
    validateForm();
});

document.getElementById('printBtn').addEventListener('click', () => {
    window.print();
});

function handleFieldChange() {
    document.getElementById('search_term').value = '';
    document.getElementById('search_status').value = '';
    document.getElementById('from_date').value = '';
    document.getElementById('to_date').value = '';
    if(window.fpFrom) window.fpFrom.clear();
    if(window.fpTo)   {
        window.fpTo.clear();
        window.fpTo.set('minDate', null);
    }
    
    if (wasSearched) {
        document.getElementById('searchForm').submit();
        return;
    }

    toggleInputs();
    
    if (document.getElementById('search_field').value === 'all') {
        document.getElementById('searchForm').submit();
    } else {
        validateForm();
    }
}

function toggleInputs() {
    const field = document.getElementById('search_field').value;
    const isStatus = field === 'status';
    const isDate   = field === 'date';
    const isAll    = field === 'all';
    const isText   = field === 'name' || field === 'address';

    document.getElementById('searchBtn').style.display         = isAll ? 'none' : 'inline-block';
    document.getElementById('search_term').style.display       = isText ? 'inline-block' : 'none';
    document.getElementById('search_status').style.display     = isStatus ? 'inline-block' : 'none';
    document.getElementById('from_date_display').style.display = (isStatus || isDate) ? 'inline-block' : 'none';
    document.getElementById('to_date_display').style.display   = (isStatus || isDate) ? 'inline-block' : 'none';
    document.getElementById('from_label').style.display        = (isStatus || isDate) ? 'inline-block' : 'none';
    document.getElementById('to_label').style.display          = (isStatus || isDate) ? 'inline-block' : 'none';

    const inp = document.getElementById('search_term');
    if (field === 'name') {
        inp.placeholder = 'Enter farmer name...';
    } else if (field === 'address') {
        inp.placeholder = 'barangay, municipality';
    } else {
        inp.placeholder = 'Search...';
    }
}

function validateForm() {
    const field = document.getElementById('search_field').value;
    const btn   = document.getElementById('searchBtn');
    let isValid = false;

    if (field === 'name' || field === 'address') {
        const val = document.getElementById('search_term').value.trim();
        isValid = val.length > 0;
        
        if (val.length === 0 && wasSearched) {
            document.getElementById('searchForm').submit();
            return;
        }
    } else if (field === 'status') {
        const st = document.getElementById('search_status').value;
        const fd = document.getElementById('from_date').value;
        const td = document.getElementById('to_date').value;
        isValid = st !== '' || (fd !== '' && td !== '');
    } else if (field === 'date') {
        const fd = document.getElementById('from_date').value;
        const td = document.getElementById('to_date').value;
        isValid = fd !== '' && td !== '';
    }

    btn.disabled = !isValid;
}

window.fpFrom = flatpickr('#from_date_display', {
    dateFormat:'m/d/Y',
    allowInput:false,
    onChange(dates) { 
        if (dates.length) {
            const formattedDate = formatLocalDate(dates[0]);
            document.getElementById('from_date').value = formattedDate; 
            
            const minToDate = new Date(dates[0]);
            minToDate.setDate(minToDate.getDate() + 1);
            window.fpTo.set('minDate', minToDate);
            
            if (window.fpTo.selectedDates[0] && window.fpTo.selectedDates[0] <= dates[0]) {
                window.fpTo.clear();
                document.getElementById('to_date').value = '';
            }
        } else {
            document.getElementById('from_date').value = ''; 
            window.fpTo.set('minDate', null);
        }
        validateForm();
    }
});

window.fpTo = flatpickr('#to_date_display', {
    dateFormat:'m/d/Y',
    allowInput:false,
    onChange(dates) { 
        document.getElementById('to_date').value = dates.length ? formatLocalDate(dates[0]) : ''; 
        validateForm();
    }
});

<?php if ($fromDate): ?>
    window.fpFrom.setDate('<?= htmlspecialchars($fromDate) ?>', true, 'Y-m-d');
    const initMinDate = new Date('<?= htmlspecialchars($fromDate) ?>T00:00:00');
    initMinDate.setDate(initMinDate.getDate() + 1);
    window.fpTo.set('minDate', initMinDate);
<?php endif; ?>

<?php if ($toDate): ?>
    window.fpTo.setDate('<?= htmlspecialchars($toDate) ?>', true, 'Y-m-d');
<?php endif; ?>
</script>
</body>
</html>