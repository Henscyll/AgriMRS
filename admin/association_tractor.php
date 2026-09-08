<?php
include('dashboard_itadmin.php');
require_once '../includes/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"> 
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Reservation List</title>
<style>
    body {
        margin: 0;
        font-family: Arial, sans-serif;
        background-color: #f4f4f4;
        overflow: hidden;
    }

    .content-wrapper {
        position: absolute;
        top: 80px;
        left: 0;
        right: 0;
        bottom: 0;
        overflow-y: auto;
        padding: 20px;
    }

    .main-container {
        max-width: 1100px;
        margin: 0 auto 30px;
        background: #fff;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 3px 10px rgba(0,0,0,0.1);
    }

    h1 {
        text-align: center;
        color: black;
        font-size: 22px;
        margin-top: 0;
        padding: 10px;
    }

    .btn-back {
        display: inline-block;
        margin-left: 10px;
        margin-bottom: 15px;
        padding: 8px 14px;
        background: #2d7a2d;
        color: white;
        text-decoration: none;
        border-radius: 5px;
        font-weight: bold;
    }

    .btn-back:hover {
        background: #256526;
    }

    .filter-container {
        margin: 0 auto 20px;
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: center;
        gap: 15px;
    }

    .filter-container label {
        font-weight: bold;
        color: #333;
    }

    .filter-container select,
    .filter-container input[type="date"] {
        padding: 8px 12px;
        border-radius: 4px;
        border: 1px solid #ccc;
        font-size: 15px;
        cursor: pointer;
    }

    .btn-search {
        padding: 8px 14px;
        background: #0e902a;
        color: white;
        border: none;
        border-radius: 5px;
        font-weight: bold;
        cursor: pointer;
    }

    .btn-search:hover {
        background: #0c7e25;
    }

    /* ✅ Scrollable table body with visible scrollbar */
    .table-wrapper {
        border: 1px solid #ddd;
        border-radius: 5px;
        overflow: hidden;
    }

    .table-header {
        overflow: hidden;
    }

    .table-body {
        display: block;
        max-height: 300px; /* scroll height */
        overflow-y: auto;  /* ✅ vertical scrollbar */
    }

    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 15px;
        min-width: 900px;
    }

    thead th {
        background-color: #0e902a;
        color: white;
        text-align: left;
        padding: 10px;
        position: sticky;
        top: 0;
        z-index: 2;
    }

    td {
        padding: 10px;
        border-bottom: 1px solid #ddd;
        color: #333;
        background-color: white;
    }

    tr:hover td {
        background-color: #f9f9f9;
    }

    /* ✅ Centered print button */
    .print-container {
        text-align: center;
        margin-top: 15px;
    }

    .btn-print {
        display: inline-block;
        background-color: #0e902a;
        color: white;
        padding: 8px 14px;
        border: none;
        border-radius: 5px;
        font-weight: bold;
        cursor: pointer;
    }

    .btn-print:hover {
        background-color: #0c7e25;
    }

    @media (max-width: 768px) {
        .filter-container {
            flex-direction: column;
        }
        .table-body {
            max-height: 250px;
        }
    }
</style>
</head>
<body>

<div class="content-wrapper">
    <a href="dashboard_itadmin.php" class="btn-back">&laquo; Back to Dashboard</a>

    <div class="main-container">
        <h1>Reservation List</h1>

        <div class="filter-container">
            <label>Type of Machinery:</label>
            <select onchange="handleMachineChange(this)">
                <option value="all">All</option>
                <option value="association_harvester.php">Harvester</option>
                <option value="association_tractor.php">Tractor</option>
            </select>

            <label>From:</label>
            <input type="date" id="fromDate">

            <label>To:</label>
            <input type="date" id="toDate">

            <button class="btn-search" onclick="searchByDate()">Search</button>
        </div>

        <!-- ✅ Scrollable Table -->
        <div class="table-wrapper">
          <div class="table-header">
            <table>
              <thead>
                <tr>
                  <th>Date of Booking</th>
                  <th>Name of Client</th>
                  <th>Contact Number</th>
                  <th>Farm Location</th>
                  <th>Farm Size</th>
                  <th>Preferred Schedule</th>
                  <th>Approved Schedule</th>
                  <th>Amount Due</th>
                </tr>
              </thead>
            </table>
          </div>

          <div class="table-body">
            <table>
              <tbody id="reservationTable">
                <tr>
                    <td>2025-10-05</td>
                    <td>Juan Montañez</td>
                    <td>09123456789</td>
                    <td>Brgy. Lantian</td>
                    <td>2.5 hectares</td>
                    <td>2025-10-10</td>
                    <td>2025-10-12</td>
                    <td>₱4,500</td>
                </tr>
                <tr>
                    <td>2025-10-8</td>
                    <td>Maria Pulmano</td>
                    <td>09987654321</td>
                    <td>Brgy. Lantian</td>
                    <td>1.8 hectares</td>
                    <td>2025-10-14</td>
                    <td>2025-10-18</td>
                    <td>₱3,200</td>
                </tr>
                <tr>
                    <td>2025-10-14</td>
                    <td>Pedro Montañez</td>
                    <td>09124561234</td>
                    <td>Brgy. Lantian</td>
                    <td>3.0 hectares</td>
                    <td>2025-10-16</td>
                    <td>2025-10-20</td>
                    <td>₱5,800</td>
                </tr>
                <tr>
                    <td>2025-10-16</td>
                    <td>Ana Pulmano</td>
                    <td>09213456789</td>
                    <td>Brgy. Lantian</td>
                    <td>1.2 hectares</td>
                    <td>2025-10-16</td>
                    <td>2025-10-22</td>
                    <td>₱2,000</td>
                </tr>
                <tr>
                    <td>2025-10-20</td>
                    <td>Corazon Corpuz</td>
                    <td>09213456789</td>
                    <td>Brgy. Lantian</td>
                    <td>1.9 hectares</td>
                    <td>2025-10-21</td>
                    <td>2025-10-24</td>
                    <td>₱2,000</td>
                </tr>
                <tr>
                    <td>2025-10-23</td>
                    <td>Renie Soriano</td>
                    <td>09124561234</td>
                    <td>Brgy. Lantian</td>
                    <td>3.0 hectares</td>
                    <td>2025-10-26</td>
                    <td>2025-10-26</td>
                    <td>₱5,800</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- ✅ Centered Print Button -->
        <div class="print-container">
          <button class="btn-print" onclick="window.print()">Print</button>
        </div>
    </div>
</div>

<script>
function handleMachineChange(select) {
    const value = select.value;
    if (value.endsWith(".php")) {
        // ✅ Redirect to file in the same directory
        window.location.href = "./" + value;
    }
}

function searchByDate() {
    const from = document.getElementById("fromDate").value;
    const to = document.getElementById("toDate").value;
    alert(`Searching records from ${from} to ${to}`);
}
</script>

</body>
</html>
