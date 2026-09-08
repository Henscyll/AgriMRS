<?php
include('da_header.php');
require_once '../includes/config.php';

$search_field  = $_GET['search_field']  ?? 'All';
$search_term   = $_GET['search_term']   ?? '';
$status_filter = $_GET['statusDropdown'] ?? '';
$from_date     = $_GET['from_date']     ?? '';
$to_date       = $_GET['to_date']       ?? '';
$field_changed = $_GET['field_changed'] ?? '0';

$search_term   = trim($search_term);
$status_filter = trim($status_filter);
$from_date     = trim($from_date);
$to_date       = trim($to_date);

$where = [];
$col_check = $conn->query("SHOW COLUMNS FROM associations LIKE 'status'");
$has_status = ($col_check && $col_check->num_rows > 0);

if ($field_changed !== '1') {
    if ($search_field === 'name' && $search_term !== '') {
        $safe = $conn->real_escape_string($search_term);
        $where[] = "a.name LIKE '%$safe%'";
    } elseif ($search_field === 'address' && $search_term !== '') {
        $parts = array_map('trim', explode(',', $search_term));
        if (count($parts) > 1 && !empty($parts[1])) {
            $safeBrgy = $conn->real_escape_string($parts[0]);
            $safeMuni = $conn->real_escape_string($parts[1]);
            $where[] = "a.barangay LIKE '%$safeBrgy%' AND a.municipality LIKE '%$safeMuni%'";
        } else {
            $safe = $conn->real_escape_string($search_term);
            $where[] = "(a.barangay LIKE '%$safe%' OR a.municipality LIKE '%$safe%' OR a.province LIKE '%$safe%')";
        }
    } elseif ($search_field === 'president' && $search_term !== '') {
        $safe = $conn->real_escape_string($search_term);
        $where[] = "(p.first_name LIKE '%$safe%' OR p.middle_name LIKE '%$safe%' OR p.last_name LIKE '%$safe%' OR CONCAT(p.first_name, ' ', COALESCE(p.middle_name,''), ' ', p.last_name) LIKE '%$safe%')";
    } elseif ($search_field === 'Status') {
        if (!empty($status_filter) && $has_status) {
            $safeStatus = $conn->real_escape_string($status_filter);
            $where[] = "a.status = '$safeStatus'";
        }
        if (!empty($from_date) && !empty($to_date)) {
            $safeFrom = $conn->real_escape_string($from_date);
            $safeTo   = $conn->real_escape_string($to_date);
            $where[]  = "DATE(a.created_at) BETWEEN '$safeFrom' AND '$safeTo'";
        }
    } elseif ($search_field === 'registered_date') {
        if (!empty($from_date) && !empty($to_date)) {
            $safeFrom = $conn->real_escape_string($from_date);
            $safeTo   = $conn->real_escape_string($to_date);
            $where[]  = "DATE(a.created_at) BETWEEN '$safeFrom' AND '$safeTo'";
        }
    }
}

$where_sql  = !empty($where) ? "WHERE " . implode(" AND ", $where) : '';
$status_col = $has_status ? ", a.status" : ", 'Active' AS status";

$query = "SELECT 
            a.id, a.name, a.province, a.municipality, a.barangay, a.phone,
            DATE_FORMAT(a.created_at, '%m/%d/%Y') AS registered_at
            $status_col,
            CONCAT(p.first_name, ' ', COALESCE(p.middle_name,''), ' ', p.last_name) AS president_name
          FROM associations a
          LEFT JOIN presidents p ON p.id = a.president_id
          $where_sql
          ORDER BY a.name";
$result = $conn->query($query);
$totalRows = ($result) ? $result->num_rows : 0;

// Print subtitle date formatting logic
$printFromFormatted = (!empty($from_date)) ? date('F j, Y', strtotime($from_date)) : '';
$printToFormatted   = (!empty($to_date))   ? date('F j, Y', strtotime($to_date))   : '';
$hasDateRange       = ($search_field === 'Status' || $search_field === 'registered_date') && !empty($from_date) && !empty($to_date);
?>
<!DOCTYPE html>
<html lang="en-US">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Associations | AMRMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.css">
    <style>
        .main-content {
            height: auto !important;
            padding: 50px 20px;
        }

        h2 {
            font-size: 28px;
            color: #2d7a2d;
            font-weight: bold;
            text-align: center;
            text-shadow: 1px 1px 3px rgba(14, 4, 4, 0.7);
            margin: 10px 0 0 0;
            padding-bottom: 1px;
        }

        .search-form {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
        }

        select,
        input[type="text"],
        input[type="date"],
        button {
            padding: 8px 12px;
            font-size: 13px;
            border: 1px solid #ccc;
            border-radius: 6px;
        }

        button {
            background-color: #2d7a2d;
            color: white;
            border: none;
            cursor: pointer;
            transition: background-color 0.3s, opacity 0.3s;
        }

        button:hover:not(:disabled) {
            background-color: #256725;
        }

        button:disabled {
            background-color: #a5d6a5 !important;
            color: #ffffff !important;
            cursor: not-allowed !important;
            opacity: 0.6;
        }

        .flatpickr-input {
            padding: 8px 12px !important;
            font-size: 14px !important;
            border: 1px solid #ccc !important;
            border-radius: 6px !important;
            background: white !important;
            color: #333 !important;
            cursor: pointer !important;
            width: 130px !important;
            box-sizing: border-box !important;
        }

        .flatpickr-input:focus {
            outline: none !important;
            border-color: #2d7a2d !important;
        }

        label {
            color: #000000;
            font-weight: bold;
            font-size: 14px;
            margin-right: 5px;
            text-shadow: 1px 1px 3px rgba(14, 4, 4, 0.7);
        }

        #from_label,
        #to_label {
            color: #000000;
        }

        .table-container {
            border: 1px solid #ddd;
            border-radius: 8px;
            background-color: white;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            margin-top: 10px;
            max-height: 330px;
            overflow-y: auto;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            table-layout: fixed;
        }

        table th:nth-child(1), table td:nth-child(1) { width: 120px; white-space: nowrap; }
        table th:nth-child(2), table td:nth-child(2) { width: 220px; word-break: break-word; }
        table th:nth-child(3), table td:nth-child(3) { width: 250px; word-break: break-word; }
        table th:nth-child(4), table td:nth-child(4) { width: 180px; word-break: break-word; }
        table th:nth-child(5), table td:nth-child(5) { width: 120px; white-space: nowrap; }
        table th:nth-child(6), table td:nth-child(6) { width: 90px; }

        th, td {
            padding: 10px 10px;
            text-align: center !important;
            border-bottom: 1px solid #ddd;
            vertical-align: middle;
            line-height: 1.35;
            font-size: 13px;
            box-sizing: border-box;
        }

        thead {
            position: sticky;
            top: 0;
            z-index: 5;
        }

        th {
            background-color: #2d7a2d;
            color: white;
            font-weight: 600;
            white-space: nowrap;
        }

        tbody tr { cursor: pointer; }
        tr:hover { background-color: #f9fafb; }
        .table-active { background-color: #d9fdd9 !important; }

        .view-container {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
        }

        .view-container button {
            background-color: #2d7a2d;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 15px;
            transition: .2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-weight: 600;
        }

        .view-container button:hover:not(:disabled) {
            background-color: #1a5c1a;
            transform: scale(1.03);
        }

        .preparedBy, .print-subtitle, .report-date { display: none; }

        @media print {
            .search-form, .view-container, .usernames-bar, header, nav, .header, .navbar, .dashboard-header, .top-bar {
                display: none !important;
            }

            .main-content h2 {
                display: block !important;
                color: #2d7a2d !important;
                text-shadow: none !important;
                margin: 0 0 5px 0 !important;
                padding: 0 !important;
                text-align: center !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .print-subtitle {
                display: block !important;
                text-align: center;
                font-size: 14px;
                font-weight: bold;
                color: #333;
                margin-bottom: 15px;
            }

            .report-date {
                display: block !important;
                text-align: left;
                font-size: 12px;
                color: #333;
                margin-bottom: 10px;
                font-weight: bold;
            }

            body {
                background: white !important;
                overflow: visible !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .main-content {
                height: auto !important;
                overflow: visible !important;
                padding: 0 !important;
                margin-top: 0 !important;
            }

            .table-container {
                border: none !important;
                box-shadow: none !important;
                overflow: visible !important;
                margin-top: 0 !important;
                max-height: none !important;
            }

            table { table-layout: auto !important; width: 100% !important; }
            thead { position: static !important; display: table-header-group !important; }
            tbody tr { display: table-row !important; }

            table th, table td, table th:nth-child(n), table td:nth-child(n) {
                width: auto !important;
                white-space: normal !important;
                word-break: normal !important;
                word-wrap: normal !important;
                padding: 8px 6px !important;
                font-size: 11px !important;
                text-align: center !important;
            }

            th {
                background-color: #2d7a2d !important;
                color: white !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .preparedBy {
                display: block !important;
                text-align: left;
                margin-top: 85px !important;
                font-size: 15px !important;
                color: #000000 !important;
            }
        }

        @media(max-width:640px) {
            .main-content { padding: 10px; }
        }
    </style>
</head>

<body>
    <div class="main-content">
        <h2>List of Associations</h2>

        <?php if ($hasDateRange): ?>
            <div class="print-subtitle">From <?= htmlspecialchars($printFromFormatted) ?> to <?= htmlspecialchars($printToFormatted) ?></div>
        <?php endif; ?>

        <form class="search-form" method="GET" id="searchForm">
            <input type="hidden" name="field_changed" id="field_changed" value="0">
            <select name="search_field" id="search_field" onchange="handleFieldChange()">
                <option value="All" <?= ($search_field === 'All') ? 'selected' : '' ?>>All</option>
                <option value="name" <?= ($search_field === 'name') ? 'selected' : '' ?>>Association Name</option>
                <option value="address" <?= ($search_field === 'address') ? 'selected' : '' ?>>Address</option>
                <option value="president" <?= ($search_field === 'president') ? 'selected' : '' ?>>Association President</option>
                <option value="Status" <?= ($search_field === 'Status') ? 'selected' : '' ?>>Status</option>
                <option value="registered_date" <?= ($search_field === 'registered_date') ? 'selected' : '' ?>>Registered Date</option>
            </select>

            <input type="text" name="search_term" id="textInput" placeholder="Enter search..."
                value="<?= htmlspecialchars($search_term) ?>" style="display:none;" oninput="validateAndCheckState()">

            <select name="statusDropdown" id="statusDropdown" style="display:none;" onchange="validateAndCheckState()">
                <option value="" disabled <?= ($status_filter === '') ? 'selected' : '' ?> hidden>Select Status</option>
                <option value="Active" <?= ($status_filter === 'Active') ? 'selected' : '' ?>>Active</option>
                <option value="Inactive" <?= ($status_filter === 'Inactive') ? 'selected' : '' ?>>Inactive</option>
            </select>

            <label for="from_date_display" id="from_label" style="display:none;">From</label>
            <input type="text" id="from_date_display" placeholder="mm/dd/yyyy" readonly
                style="display:none; width:130px;">
            <input type="hidden" name="from_date" id="from_date" value="<?= htmlspecialchars($from_date) ?>">

            <label for="to_date_display" id="to_label" style="display:none;">To</label>
            <input type="text" id="to_date_display" placeholder="mm/dd/yyyy" readonly
                style="display:none; width:130px;">
            <input type="hidden" name="to_date" id="to_date" value="<?= htmlspecialchars($to_date) ?>">

            <button type="submit" id="searchBtn" style="display:none;" disabled>Search</button>
        </form>

        <div class="report-date">Report Date: <?= date('F j, Y g:i A') ?></div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Registered Date</th>
                        <th>Association Name</th>
                        <th>Address</th>
                        <th>Association President</th>
                        <th>Association Contact No.</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($result && $result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            $address  = trim($row['barangay'] . ', ' . $row['municipality'] . ', ' . $row['province'], ', ');
                            $status   = htmlspecialchars($row['status'] ?? 'Active');
                            $regAt    = htmlspecialchars($row['registered_at'] ?? '—');
                            $presName = trim((string) ($row['president_name'] ?? ''));
                            $presName = ($presName === '') ? '—' : htmlspecialchars($presName);
                            echo "<tr class='clickable-row' data-id='{$row['id']}'>
                                <td>{$regAt}</td>
                                <td>" . htmlspecialchars($row['name']) . "</td>
                                <td>" . htmlspecialchars($address) . "</td>
                                <td>{$presName}</td>
                                <td>" . htmlspecialchars($row['phone']) . "</td>
                                <td><span style='font-weight:bold;'>{$status}</span></td>
                              </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='6' style='text-align:center; padding:20px; color:#555;'>No records found</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
            <div class="preparedBy">
                Prepared By:
                <span style="font-weight:bold;">DA Offcial</span>
            </div>
        </div>

        <div class="view-container">
            <button id="printBtn" <?= ($totalRows == 0) ? 'disabled' : '' ?>>Print</button>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.js"></script>
    <script>
        let wasSearched = <?= ($search_field !== 'All' && ($search_term !== '' || $status_filter !== '' || ($from_date !== '' && $to_date !== ''))) ? 'true' : 'false' ?>;

        function formatLocalDate(dateObj) {
            const year = dateObj.getFullYear();
            const month = String(dateObj.getMonth() + 1).padStart(2, '0');
            const day = String(dateObj.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }

        window.fpFrom = flatpickr('#from_date_display', {
            dateFormat: 'm/d/Y',
            allowInput: false,
            onChange: function (dates) {
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
                validateAndCheckState();
            }
        });

        window.fpTo = flatpickr('#to_date_display', {
            dateFormat: 'm/d/Y',
            allowInput: false,
            onChange: function (dates) {
                document.getElementById('to_date').value = dates.length ? formatLocalDate(dates[0]) : '';
                validateAndCheckState();
            }
        });

        <?php if ($from_date): ?>
            window.fpFrom.setDate('<?= htmlspecialchars($from_date) ?>', true, 'Y-m-d');
            const initMinDate = new Date('<?= htmlspecialchars($from_date) ?>T00:00:00');
            initMinDate.setDate(initMinDate.getDate() + 1);
            window.fpTo.set('minDate', initMinDate);
        <?php endif; ?>

        <?php if ($to_date): ?>
            window.fpTo.setDate('<?= htmlspecialchars($to_date) ?>', true, 'Y-m-d');
        <?php endif; ?>

        function handleFieldChange() {
            document.getElementById('textInput').value = '';
            document.getElementById('statusDropdown').value = '';
            document.getElementById('from_date').value = '';
            document.getElementById('to_date').value = '';
            if (window.fpFrom) window.fpFrom.clear();
            if (window.fpTo) {
                window.fpTo.clear();
                window.fpTo.set('minDate', null);
            }

            if (wasSearched) {
                document.getElementById('field_changed').value = '1';
                document.getElementById('searchForm').submit();
                return;
            }

            toggleInputs();

            if (document.getElementById('search_field').value === 'All') {
                document.getElementById('field_changed').value = '1';
                document.getElementById('searchForm').submit();
            } else {
                validateAndCheckState();
            }
        }

        function toggleInputs() {
            const field = document.getElementById('search_field').value;
            const isStatus = (field === 'Status');
            const isAll = (field === 'All');
            const isRegDate = (field === 'registered_date');

            document.getElementById('textInput').style.display = (!isStatus && !isAll && !isRegDate) ? 'inline-block' : 'none';
            document.getElementById('statusDropdown').style.display = isStatus ? 'inline-block' : 'none';
            document.getElementById('from_date_display').style.display = (isStatus || isRegDate) ? 'inline-block' : 'none';
            document.getElementById('to_date_display').style.display = (isStatus || isRegDate) ? 'inline-block' : 'none';
            document.getElementById('from_label').style.display = (isStatus || isRegDate) ? 'inline-block' : 'none';
            document.getElementById('to_label').style.display = (isStatus || isRegDate) ? 'inline-block' : 'none';
            document.getElementById('searchBtn').style.display = !isAll ? 'inline-block' : 'none';

            const ph = document.getElementById('textInput');
            if (field === 'president') {
                ph.placeholder = 'Search by president name...';
            } else if (field === 'name') {
                ph.placeholder = 'Search by association name...';
            } else if (field === 'address') {
                ph.placeholder = 'barangay, municipality';
            } else {
                ph.placeholder = 'Enter search...';
            }
        }

        function validateAndCheckState() {
            const field = document.getElementById('search_field').value;
            const btn = document.getElementById('searchBtn');
            let isValid = false;

            if (field === 'name' || field === 'address' || field === 'president') {
                const val = document.getElementById('textInput').value.trim();
                isValid = val.length > 0;

                if (val.length === 0 && wasSearched) {
                    document.getElementById('field_changed').value = '1';
                    document.getElementById('searchForm').submit();
                    return;
                }
            } else if (field === 'Status') {
                const st = document.getElementById('statusDropdown').value;
                const fd = document.getElementById('from_date').value;
                const td = document.getElementById('to_date').value;
                isValid = st !== '' || (fd !== '' && td !== '');
            } else if (field === 'registered_date') {
                const fd = document.getElementById('from_date').value;
                const td = document.getElementById('to_date').value;
                isValid = fd !== '' && td !== '';
            }

            btn.disabled = !isValid;
        }

        document.addEventListener('DOMContentLoaded', () => {
            toggleInputs();
            validateAndCheckState();
        });

        const rows = document.querySelectorAll('.clickable-row');
        rows.forEach(row => {
            row.addEventListener('click', () => {
                rows.forEach(r => r.classList.remove('table-active'));
                row.classList.add('table-active');
            });
            row.addEventListener('dblclick', () => {
                const id = row.getAttribute('data-id');
                if (id) window.location.href = 'da_association_profile.php?id=' + id;
            });
        });

        document.getElementById('printBtn').addEventListener('click', () => {
            window.print();
        });
    </script>
</body>

</html>