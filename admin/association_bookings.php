<?php
include('dashboard_itadmin.php');
require_once '../includes/config.php';

// Fetch municipalities
$municipalityQuery = "SELECT DISTINCT municipality FROM associations ORDER BY municipality";
$municipalityResult = $conn->query($municipalityQuery);

// fetch associations for dropdown
$associationsQuery = "SELECT id, name FROM associations ORDER BY name";
$associationsResult = $conn->query($associationsQuery);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"> 
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reservation Management | Admin Dashboard</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    :root {
        --primary-color: #16a34a;
        --primary-dark: #15803d;
        --primary-light: #dcfce7;
        --secondary-color: #6366f1;
        --danger-color: #ef4444;
        --warning-color: #f59e0b;
        --text-primary: #1f2937;
        --text-secondary: #6b7280;
        --text-head: #ffffff;
        --bg-light: #f0fdf4;
        --bg-white: #ffffff;
        --border-color: #d1d5db;
        --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
    }


    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        height: calc(100vh - 150px);
        color: var(--text-primary);
    }

    .content-wrapper {
        position: absolute;
        top: 120px;
        left: 0;
        right: 0;
        bottom: 0;
        overflow-y: auto;
        padding: 16px;
    }

    /* Header Section */
    .page-header {
        max-width: 1500px;
        margin: 0 auto 16px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
    }

    .header-left {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .btn-back {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 14px;
        background: var(--bg-white);
        color: var(--text-primary);
        text-decoration: none;
        border-radius: 6px;
        font-weight: 500;
        font-size: 14px;
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
        box-shadow: var(--shadow-sm);
    }

    .btn-back:hover {
        background: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }

    .page-title {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .page-title i {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        color: white;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        box-shadow: var(--shadow-md);
    }

    .page-title h1 {
        font-size: 24px;
        font-weight: 700;
        color: var(--text-head);
    }

    /* Main Container */
    .main-container {
        max-width: 1400px;
        margin: 0 auto;
        background: var(--bg-white);
        border-radius: 12px;
        box-shadow: var(--shadow-xl);
        overflow: hidden;
    }

    /* Filter Section */
    .filter-section {
        background: var(--bg-light);
        padding: 16px 20px;
        border-bottom: 1px solid var(--border-color);
    }

    .filter-header {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 12px;
        color: var(--text-primary);
    }

    .filter-header i {
        color: var(--primary-color);
        font-size: 16px;
    }

    .filter-header h2 {
        font-size: 16px;
        font-weight: 600;
    }

    .filter-grid {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 12px;
        margin-bottom: 12px;
    }

    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .filter-group label {
        font-weight: 600;
        color: var(--text-primary);
        font-size: 13px;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .filter-group label i {
        color: var(--primary-color);
        font-size: 12px;
    }

    .filter-group select,
    .filter-group input[type="date"] {
        padding: 8px 12px;
        border: 1px solid var(--border-color);
        border-radius: 6px;
        font-size: 14px;
        transition: all 0.3s ease;
        background: white;
        color: var(--text-primary);
        font-family: inherit;
    }

    .filter-group select:focus,
    .filter-group input[type="date"]:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
    }

    .filter-group select:disabled {
        background: var(--bg-light);
        cursor: not-allowed;
        opacity: 0.6;
    }

    .filter-actions {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        flex-wrap: wrap;
    }

    .btn {
        padding: 8px 16px;
        border: none;
        border-radius: 6px;
        font-weight: 600;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        text-decoration: none;
        box-shadow: var(--shadow-sm);
    }

    .btn-primary {
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        color: white;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
    }

    .btn-secondary {
        background: white;
        color: var(--text-primary);
        border: 2px solid var(--border-color);
    }

    .btn-secondary:hover {
        background: var(--bg-light);
        border-color: var(--primary-color);
    }

    /* Results Section */
    .results-section {
        padding: 20px;
    }

    .results-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
        flex-wrap: wrap;
        gap: 12px;
    }

    .results-info {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .results-count {
        padding: 6px 14px;
        background: var(--primary-light);
        color: var(--primary-dark);
        border-radius: 16px;
        font-weight: 600;
        font-size: 13px;
    }

    /* Table */
    .table-container {
        border: 1px solid var(--border-color);
        border-radius: 12px;
        overflow: hidden;
        background: white;
    }

    .table-scroll {
        overflow-x: auto;
        max-height: 400px;
        overflow-y: auto;
    }

    /* Custom Scrollbar */
    .table-scroll::-webkit-scrollbar {
        width: 10px;
        height: 10px;
    }

    .table-scroll::-webkit-scrollbar-track {
        background: var(--bg-light);
    }

    .table-scroll::-webkit-scrollbar-thumb {
        background: var(--primary-color);
        border-radius: 5px;
    }

    .table-scroll::-webkit-scrollbar-thumb:hover {
        background: var(--primary-dark);
    }

    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
        min-width: 1000px;
    }

    thead {
        position: sticky;
        top: 0;
        z-index: 10;
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    }

    thead th {
        color: white;
        text-align: left;
        padding: 12px;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 12px;
        letter-spacing: 0.5px;
        white-space: nowrap;
    }

    tbody tr {
        transition: all 0.2s ease;
        border-bottom: 1px solid var(--border-color);
    }

    tbody tr:hover {
        background: var(--primary-light);
        transform: scale(1.01);
        box-shadow: var(--shadow-md);
    }

    tbody td {
        padding: 12px;
        color: var(--text-primary);
        vertical-align: middle;
        font-size: 13px;
    }

    tbody tr:last-child {
        border-bottom: none;
    }

    .no-data {
        text-align: center;
        padding: 60px 20px;
        color: var(--text-secondary);
    }

    .no-data i {
        font-size: 64px;
        color: var(--border-color);
        margin-bottom: 16px;
        display: block;
    }

    .no-data p {
        font-size: 16px;
        margin-bottom: 8px;
    }

    .no-data small {
        color: var(--text-secondary);
        font-size: 14px;
    }

    /* Status Badge */
    .badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .badge-success {
        background: #d1fae5;
        color: #065f46;
    }

    .badge-warning {
        background: #fef3c7;
        color: #92400e;
    }

    /* Loading State */
    .loading {
        text-align: center;
        padding: 40px;
        color: var(--text-secondary);
    }

    .spinner {
        border: 4px solid var(--border-color);
        border-top: 4px solid var(--primary-color);
        border-radius: 50%;
        width: 40px;
        height: 40px;
        animation: spin 1s linear infinite;
        margin: 0 auto 16px;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    /* Print Styles */
    @media print {
        body {
            background: white;
        }

        .content-wrapper {
            position: static;
            padding: 0;
        }

        .btn-back,
        .filter-section,
        .filter-actions,
        .btn-print {
            display: none !important;
        }

        .main-container {
            box-shadow: none;
            border: 1px solid #000;
        }

        .table-scroll {
            max-height: none;
            overflow: visible;
        }

        thead {
            background: #10b981 !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        tbody tr {
            page-break-inside: avoid;
        }
    }

    /* Responsive Design */
    @media (max-width: 1200px) {
        .filter-grid {
            grid-template-columns: repeat(3, 1fr);
        }
    }

    @media (max-width: 1024px) {
        .filter-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 768px) {
        .content-wrapper {
            padding: 16px;
        }

        .page-header {
            flex-direction: column;
            align-items: flex-start;
        }

        .page-title h1 {
            font-size: 24px;
        }

        .filter-grid {
            grid-template-columns: 1fr;
        }

        .filter-section,
        .results-section {
            padding: 20px;
        }

        .results-header {
            flex-direction: column;
            align-items: flex-start;
        }

        .table-scroll {
            max-height: 400px;
        }

        table {
            font-size: 13px;
        }

        thead th,
        tbody td {
            padding: 12px 8px;
        }
    }
</style>
</head>
<body>

<div class="content-wrapper">
    <div class="page-header">
        <div class="header-left">
            <a href="dashboard_itadmin.php" class="btn-back">
                <i class="fas fa-arrow-left"></i>
                Back to Dashboard
            </a>
            <div class="page-title">
                <i class="fas fa-calendar-check"></i>
                <h1>Reservation Management</h1>
            </div>
        </div>
    </div>

    <div class="main-container">
        <!-- Filter Section -->
        <div class="filter-section">
            <div class="filter-header">
                <i class="fas fa-filter"></i>
                <h2>Search Filters</h2>
            </div>

            <div class="filter-grid">
                <div class="filter-group">
                    <label>
                        <i class="fas fa-map-marker-alt"></i>
                        Municipality
                    </label>
                    <select id="municipalitySelect" onchange="loadAssociations()">
                        <option value="">Select Municipality</option>
                        <?php while($row = $municipalityResult->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($row['municipality']) ?>">
                                <?= htmlspecialchars($row['municipality']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>
                        <i class="fas fa-users"></i>
                        Association
                    </label>
                    <select id="associationSelect" onchange="loadAssociations()" disabled>
                        <option value="">Select Association</option>
                        
                    </select>
                </div>

                <div class="filter-group">
                    <label>
                        <i class="fas fa-tractor"></i>
                        Type of Machinery
                    </label>
                    <select id="machineTypeSelect" disabled>
                        <option value="">Select Machine Type</option>
                        <option value="Tractor">Tractor</option>
                        <option value="Harvester">Harvester</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>
                        <i class="fas fa-calendar-day"></i>
                        From Date
                    </label>
                    <input type="date" id="fromDate">
                </div>

                <div class="filter-group">
                    <label>
                        <i class="fas fa-calendar-day"></i>
                        To Date
                    </label>
                    <input type="date" id="toDate">
                </div>
            </div>

            <div class="filter-actions">
                <button class="btn btn-secondary" onclick="resetFilters()">
                    <i class="fas fa-redo"></i>
                    Reset
                </button>
                <button class="btn btn-primary" onclick="searchReservations()">
                    <i class="fas fa-search"></i>
                    Search Reservations
                </button>
            </div>
        </div>

        <!-- Results Section -->
        <div class="results-section">
            <div class="results-header">
                <div class="results-info">
                    <h3 style="color: var(--text-primary); font-size: 16px; font-weight: 600;">Reservation Results</h3>
                    <span class="results-count" id="resultsCount">0 reservations</span>
                </div>
                <button class="btn btn-primary" onclick="window.print()">
                    <i class="fas fa-print"></i>
                    Print Report
                </button>
            </div>

            <div class="table-container">
                <div class="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th><i class="fas fa-calendar"></i> Date of Booking</th>
                                <th><i class="fas fa-user"></i> Client Name</th>
                                <th><i class="fas fa-phone"></i> Contact Number</th>
                                <th><i class="fas fa-map-pin"></i> Farm Location</th>
                                <th><i class="fas fa-ruler-combined"></i> Farm Size</th>
                                <th><i class="fas fa-clock"></i> Preferred Schedule</th>
                                <th><i class="fas fa-check-circle"></i> Approved Schedule</th>
                                <th><i class="fas fa-money-bill-wave"></i> Amount Due</th>
                            </tr>
                        </thead>
                        <tbody id="reservationTable">
                            <tr>
                                <td colspan="8" class="no-data">
                                    <i class="fas fa-search"></i>
                                    <p>No data available</p>
                                    <small>Please select filters above and click "Search Reservations"</small>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Reset all filters
function resetFilters() {
    document.getElementById('municipalitySelect').value = '';
    document.getElementById('associationSelect').innerHTML = '<option value="">Select Association</option>';
    document.getElementById('machineTypeSelect').innerHTML = '<option value="">Select Machine Type</option>';
    document.getElementById('associationSelect').disabled = true;
    document.getElementById('machineTypeSelect').disabled = true;
    document.getElementById('fromDate').value = '';
    document.getElementById('toDate').value = '';
    
    // Reset table
    const tableBody = document.getElementById('reservationTable');
    tableBody.innerHTML = `
        <tr>
            <td colspan="8" class="no-data">
                <i class="fas fa-search"></i>
                <p>No data available</p>
                <small>Please select filters above and click "Search Reservations"</small>
            </td>
        </tr>
    `;
    
    // Reset count
    document.getElementById('resultsCount').textContent = '0 reservations';
}

// Load associations based on selected municipality
function loadAssociations() {
    const municipality = document.getElementById('municipalitySelect').value;
    const associationSelect = document.getElementById('associationSelect');
    const machineTypeSelect = document.getElementById('machineTypeSelect');
    
    
    // Reset dependent dropdowns
    associationSelect.innerHTML = '<option value="">Select Association</option>';
    machineTypeSelect.innerHTML = '<option value="">Select Machine Type</option>';
    associationSelect.disabled = true;
    machineTypeSelect.disabled = true;
    
    if (!municipality) return;
    
    // Show loading state
    associationSelect.innerHTML = '<option value="">Loading...</option>';
    
    // Fetch associations for selected municipality
    fetch(`get_associations.php?municipality=${encodeURIComponent(municipality)}`)
        .then(response => response.json())
        .then(data => {
            associationSelect.innerHTML = '<option value="">Select Association</option>';
            if (data.success && data.associations.length > 0) {
                data.associations.forEach(assoc => {
                    const option = document.createElement('option');
                    option.value = assoc.id;
                    option.textContent = assoc.name;
                    associationSelect.appendChild(option);
                });
                associationSelect.disabled = false;
            } else {
                associationSelect.innerHTML = '<option value="">No associations found</option>';
            }
        })
        .catch(error => {
            console.error('Error loading associations:', error);
            associationSelect.innerHTML = '<option value="">Error loading data</option>';
        });
}

// Load machines based on selected association
function loadMachines() {
    const associationId = document.getElementById('associationSelect').value;
    const machineTypeSelect = document.getElementById('machineTypeSelect');
    
    machineTypeSelect.innerHTML = '<option value="">Select Machine Type</option>';
    machineTypeSelect.disabled = true;
    
    if (!associationId) return;
    
    // Show loading state
    machineTypeSelect.innerHTML = '<option value="">Loading...</option>';
    
    // Fetch machines for selected association
    fetch(`get_machines.php?association_id=${associationId}`)
        .then(response => response.json())
        .then(data => {
            machineTypeSelect.innerHTML = '<option value="">Select Machine Type</option>';
            if (data.success && data.machines.length > 0) {
                // Add "All Machines" option
                const allOption = document.createElement('option');
                allOption.value = 'all';
                allOption.textContent = 'All Machines';
                machineTypeSelect.appendChild(allOption);
                
                // Add individual machines
                data.machines.forEach(machine => {
                    const option = document.createElement('option');
                    option.value = machine.id;
                    option.textContent = `${machine.type} - ${machine.machine_name}`;
                    machineTypeSelect.appendChild(option);
                });
                machineTypeSelect.disabled = false;
            } else {
                machineTypeSelect.innerHTML = '<option value="">No machines found</option>';
            }
        })
        .catch(error => {
            console.error('Error loading machines:', error);
            machineTypeSelect.innerHTML = '<option value="">Error loading data</option>';
        });
}

// Search reservations based on filters
function searchReservations() {
    const municipality = document.getElementById('municipalitySelect').value;
    const associationId = document.getElementById('associationSelect').value;
    const machineId = document.getElementById('machineTypeSelect').value;
    const fromDate = document.getElementById('fromDate').value;
    const toDate = document.getElementById('toDate').value;
    
    const tableBody = document.getElementById('reservationTable');
    
    if (!municipality || !associationId || !machineId) {
        alert('Please select Municipality, Association, and Machine Type');
        return;
    }
    
    // Show loading state
    tableBody.innerHTML = `
        <tr>
            <td colspan="8" class="loading">
                <div class="spinner"></div>
                <p>Loading reservations...</p>
            </td>
        </tr>
    `;
    
    // Build query string
    const params = new URLSearchParams({
        municipality: municipality,
        association_id: associationId,
        machine_id: machineId,
        from_date: fromDate,
        to_date: toDate
    });
    
    // Fetch reservations
    fetch(`get_reservations.php?${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.reservations.length > 0) {
                tableBody.innerHTML = '';
                data.reservations.forEach(reservation => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${formatDate(reservation.booking_date)}</td>
                        <td><strong>${escapeHtml(reservation.farmer_name)}</strong></td>
                        <td>${escapeHtml(reservation.phone)}</td>
                        <td>${escapeHtml(reservation.farm_location)}</td>
                        <td>${escapeHtml(reservation.farm_size)}</td>
                        <td>${formatDate(reservation.preferred_schedule)}</td>
                        <td>${reservation.approved_schedule ? formatDate(reservation.approved_schedule) : '<span style="color: var(--text-secondary);">Pending</span>'}</td>
                        <td><strong style="color: var(--primary-color);">₱${parseFloat(reservation.amount_due).toLocaleString('en-US', {minimumFractionDigits: 2})}</strong></td>
                    `;
                    tableBody.appendChild(row);
                });
                
                // Update count
                document.getElementById('resultsCount').textContent = `${data.reservations.length} reservation${data.reservations.length !== 1 ? 's' : ''}`;
            } else {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="8" class="no-data">
                            <i class="fas fa-inbox"></i>
                            <p>No reservations found</p>
                            <small>Try adjusting your search filters</small>
                        </td>
                    </tr>
                `;
                document.getElementById('resultsCount').textContent = '0 reservations';
            }
        })
        .catch(error => {
            console.error('Error loading reservations:', error);
            tableBody.innerHTML = `
                <tr>
                    <td colspan="8" class="no-data">
                        <i class="fas fa-exclamation-triangle"></i>
                        <p>Error loading data</p>
                        <small>Please try again later</small>
                    </td>
                </tr>
            `;
            document.getElementById('resultsCount').textContent = '0 reservations';
        });
}

// Reset all filters
function resetFilters() {
    document.getElementById('municipalitySelect').value = '';
    document.getElementById('associationSelect').innerHTML = '<option value="">Select Association</option>';
    document.getElementById('machineTypeSelect').innerHTML = '<option value="">Select Machine Type</option>';
    document.getElementById('associationSelect').disabled = true;
    document.getElementById('machineTypeSelect').disabled = true;
    document.getElementById('fromDate').value = '';
    document.getElementById('toDate').value = '';
    
    // Reset table
    const tableBody = document.getElementById('reservationTable');
    tableBody.innerHTML = `
        <tr>
            <td colspan="8" class="no-data">
                <i class="fas fa-search"></i>
                <p>No data available</p>
                <small>Please select filters above and click "Search Reservations"</small>
            </td>
        </tr>
    `;
    
    // Reset count
    document.getElementById('resultsCount').textContent = '0 reservations';
}

// Helper function to format dates
function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return date.toLocaleDateString('en-US', options);
}

// Helper function to escape HTML
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}
</script>

</body>
</html>
