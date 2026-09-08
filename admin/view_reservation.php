<?php
require_once '../includes/config.php';

$bookingId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$isPrint = isset($_GET['print']) && $_GET['print'] == '1';

if (!$bookingId) {
    die('<p style="text-align:center; color:red; padding:40px;">Invalid reservation ID.</p>');
}

// Fetch full reservation details
$query = "
    SELECT 
        b.id AS booking_id,
        b.booking_date,
        b.status,
        b.farm_location,
        b.farm_size,
        b.notes,
        b.created_at,
        f.name AS farmer_name,
        f.email AS farmer_email,
        f.phone AS farmer_phone,
        f.province AS farmer_province,
        f.municipality AS farmer_municipality,
        f.barangay AS farmer_barangay,
        fl.lot_number,
        fl.farm_location AS lot_location,
        fl.farm_size AS lot_size,
        m.machine_name,
        m.type AS machine_type,
        m.price_per_hectare,
        a.name AS association_name,
        a.municipality AS assoc_municipality,
        a.province AS assoc_province,
        a.phone AS assoc_phone,
        a.email AS assoc_email
    FROM bookings b
    JOIN farmers f ON b.farmer_id = f.id
    LEFT JOIN farmer_lots fl ON b.lot_id = fl.id
    JOIN machines m ON b.machine_id = m.id
    JOIN associations a ON m.association_id = a.id
    WHERE b.id = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param('i', $bookingId);
$stmt->execute();
$result = $stmt->get_result();
$res = $result->fetch_assoc();

if (!$res) {
    die('<p style="text-align:center; color:red; padding:40px;">Reservation not found.</p>');
}

$farmSize = floatval($res['farm_size']);
$pricePerHa = floatval($res['price_per_hectare']);
$totalAmount = $farmSize * $pricePerHa;

$statusColors = [
    'Pending'   => ['bg' => '#dbeafe', 'color' => '#1e40af'],
    'Approved'  => ['bg' => '#d1fae5', 'color' => '#065f46'],
    'Completed' => ['bg' => '#d1fae5', 'color' => '#065f46'],
    'Cancelled' => ['bg' => '#fee2e2', 'color' => '#991b1b'],
];
$statusStyle = $statusColors[$res['status']] ?? ['bg' => '#f3f4f6', 'color' => '#374151'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reservation #<?= $bookingId ?> | Details</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    :root {
        --primary-color: #16a34a;
        --primary-dark: #15803d;
        --primary-light: #dcfce7;
        --text-primary: #1f2937;
        --text-secondary: #6b7280;
        --border-color: #d1d5db;
        --bg-light: #f0fdf4;
        --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1);
        --shadow-xl: 0 20px 25px -5px rgba(0,0,0,0.1);
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: #f3f4f6;
    color: var(--text-primary);
    padding: 14px 12px;
    min-height: 100vh;
}
    .page-wrapper {
        max-width: 860px;
        margin: 0 auto;
    }

    /* Top bar with buttons (hidden on print) */
    .top-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
    flex-wrap: wrap;
    gap: 8px;
}

    .top-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
    flex-wrap: wrap;
    gap: 8px;
}

    .top-bar-title i {
        color: var(--primary-color);
    }

    .btn-group {
        display: flex;
        gap: 10px;
    }

    .btn {
    padding: 6px 13px;
    border: none;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    text-decoration: none;
    transition: all 0.2s ease;
    box-shadow: var(--shadow-md);
}

    .btn-back {
        background: white;
        color: var(--text-primary);
        border: 1.5px solid var(--border-color);
    }

    .btn-back:hover {
        background: var(--primary-light);
        border-color: var(--primary-color);
        color: var(--primary-dark);
    }

    .btn-print {
        background: #2d7d46;
        color: white;
    }

    .btn-print:hover {
        transform: translateY(-1px);
        
    }

    /* Main Card */
    .card {
        background: white;
        border-radius: 14px;
        box-shadow: var(--shadow-xl);
        overflow: hidden;
    }

    /* Header */
    .card-header {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    padding: 14px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}

    .header-brand {
        display: flex;
        align-items: center;
        gap: 14px;
    }

    .header-icon {
    width: 36px;
    height: 36px;
    background: rgba(255,255,255,0.2);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    color: white;
    flex-shrink: 0;
}

    .header-text h1 {
    color: white;
    font-size: 15px;
    font-weight: 700;
    margin-bottom: 2px;
}

.header-text p {
    color: rgba(255,255,255,0.8);
    font-size: 11px;
}

    .header-badge {
        display: inline-block;
        padding: 8px 18px;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        background: <?= $statusStyle['bg'] ?>;
        color: <?= $statusStyle['color'] ?>;
        align-self: center;
    }

    /* Body */
    .card-body {
        padding: 16px 20px;
    }

    /* Section */
    .section {
        margin-bottom: 16px;
    }

    .section-title {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 11px;
    font-weight: 700;
    color: var(--primary-dark);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
    padding-bottom: 6px;
    border-bottom: 2px solid var(--primary-light);
}

    .section-title i {
        font-size: 15px;
        color: var(--primary-color);
    }

    /* Info Grid */
    .info-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 8px;
    }

    .info-grid.two-col { grid-template-columns: repeat(2, 1fr); }
    .info-grid.four-col { grid-template-columns: repeat(4, 1fr); }

    .info-item {
    background: var(--bg-light);
    border-radius: 7px;
    padding: 8px 11px;
    border: 1px solid #e5f0e8;
}

    .info-label {
    font-size: 10px;
    font-weight: 600;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 3px;
    display: flex;
    align-items: center;
    gap: 4px;
}

    .info-label i { color: var(--primary-color); font-size: 11px; }

    .info-value {
    font-size: 12px;
    font-weight: 600;
    color: var(--text-primary);
    line-height: 1.4;
}

    .info-value.highlight {
    font-size: 14px;
    color: var(--primary-dark);
}

    .info-value.amount {
    font-size: 16px;
    font-weight: 700;
    color: var(--primary-color);
}

    /* Notes box */
    .notes-box {
        background: #fffbeb;
        border: 1px solid #fde68a;
        border-radius: 10px;
        padding: 14px 16px;
    }

    .notes-box p {
        font-size: 14px;
        color: #92400e;
        line-height: 1.6;
    }

    /* Footer */
    .card-footer {
    background: var(--bg-light);
    padding: 9px 20px;
    border-top: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}

.footer-meta {
    font-size: 11px;
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    gap: 5px;
}

    .footer-meta i { color: var(--primary-color); }

    /* Print Styles */
    @media print {
        body { background: white; padding: 0; }
        .top-bar { display: none !important; }
        .card { box-shadow: none; border-radius: 0; }
        .card-header { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .info-item { background: #f9fafb !important; border: 1px solid #d1d5db !important; }
    }

    @media (max-width: 640px) {
        .info-grid { grid-template-columns: repeat(2, 1fr); }
        .info-grid.four-col { grid-template-columns: repeat(2, 1fr); }
        .card-header { flex-direction: column; }
        .card-body { padding: 20px; }
    }
</style>
</head>
<body>

<div class="page-wrapper">

    <!-- Top Bar -->
    <div class="top-bar no-print">
        <div class="top-bar-title">
            <i class="fas fa-calendar-check"></i>
            Reservation Details
        </div>
        <div class="btn-group">
            <button class="btn btn-back" onclick="window.close()">
                <i class="fas fa-arrow-left"></i> Close
            </button>
            <button class="btn btn-print" onclick="window.print()">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <!-- Main Card -->
    <div class="card">

        <!-- Card Header -->
        <div class="card-header">
            <div class="header-brand">
                <div class="header-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="header-text">
                    <h1>Reservation #<?= str_pad($bookingId, 5, '0', STR_PAD_LEFT) ?></h1>
                    <p>Booking Date: <?= date('F j, Y', strtotime($res['booking_date'])) ?></p>
                </div>
            </div>
            <span class="header-badge"><?= htmlspecialchars($res['status']) ?></span>
        </div>

        <!-- Card Body -->
        <div class="card-body">

            <!-- Farmer Information -->
            <div class="section">
                <div class="section-title">
                    <i class="fas fa-user"></i> Farmer Information
                </div>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-user"></i> Full Name</div>
                        <div class="info-value"><?= htmlspecialchars($res['farmer_name']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-phone"></i> Contact No.</div>
                        <div class="info-value"><?= htmlspecialchars($res['farmer_phone'] ?? 'N/A') ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-envelope"></i> Email</div>
                        <div class="info-value"><?= htmlspecialchars($res['farmer_email'] ?? 'N/A') ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-map-marker-alt"></i> Province</div>
                        <div class="info-value"><?= htmlspecialchars($res['farmer_province'] ?? 'N/A') ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-map-pin"></i> Municipality</div>
                        <div class="info-value"><?= htmlspecialchars($res['farmer_municipality'] ?? 'N/A') ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-home"></i> Barangay</div>
                        <div class="info-value"><?= htmlspecialchars($res['farmer_barangay'] ?? 'N/A') ?></div>
                    </div>
                </div>
            </div>

            <!-- Farm Details -->
            <div class="section">
                <div class="section-title">
                    <i class="fas fa-seedling"></i> Farm Details
                </div>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-map-pin"></i> Farm Location</div>
                        <div class="info-value"><?= htmlspecialchars($res['farm_location'] ?: ($res['lot_location'] ?? 'N/A')) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-ruler-combined"></i> Farm Size</div>
                        <div class="info-value highlight"><?= number_format($farmSize, 2) ?> ha</div>
                    </div>
                    <?php if ($res['lot_number']): ?>
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-tag"></i> Lot Number</div>
                        <div class="info-value"><?= htmlspecialchars($res['lot_number']) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Machine & Association -->
            <div class="section">
                <div class="section-title">
                    <i class="fas fa-tractor"></i> Machine & Association
                </div>
                <div class="info-grid four-col">
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-tractor"></i> Machine Name</div>
                        <div class="info-value"><?= htmlspecialchars($res['machine_name']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-cog"></i> Machine Type</div>
                        <div class="info-value"><?= htmlspecialchars($res['machine_type']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-building"></i> Association</div>
                        <div class="info-value"><?= htmlspecialchars($res['association_name']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-map-marker-alt"></i> Assoc. Location</div>
                        <div class="info-value"><?= htmlspecialchars(($res['assoc_municipality'] ?? '') . ', ' . ($res['assoc_province'] ?? '')) ?></div>
                    </div>
                </div>
            </div>

            <!-- Payment Summary -->
            <div class="section">
                <div class="section-title">
                    <i class="fas fa-money-bill-wave"></i> Payment Summary
                </div>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-peso-sign"></i> Price per Hectare</div>
                        <div class="info-value highlight">₱<?= number_format($pricePerHa, 2) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-ruler-combined"></i> Farm Size</div>
                        <div class="info-value highlight"><?= number_format($farmSize, 2) ?> ha</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-calculator"></i> Total Amount</div>
                        <div class="info-value amount">₱<?= number_format($totalAmount, 2) ?></div>
                    </div>
                </div>
            </div>

            <!-- Notes (if any) -->
            <?php if (!empty($res['notes'])): ?>
            <div class="section">
                <div class="section-title">
                    <i class="fas fa-sticky-note"></i> Notes
                </div>
                <div class="notes-box">
                    <p><?= nl2br(htmlspecialchars($res['notes'])) ?></p>
                </div>
            </div>
            <?php endif; ?>

        </div>

        <!-- Card Footer -->
        <div class="card-footer">
            <div class="footer-meta">
                <i class="fas fa-clock"></i>
                Reservation submitted on <?= date('F j, Y \a\t g:i A', strtotime($res['created_at'])) ?>
            </div>
            <div class="footer-meta">
                <i class="fas fa-hashtag"></i>
                Booking ID: <?= str_pad($bookingId, 5, '0', STR_PAD_LEFT) ?>
            </div>
        </div>

    </div>
</div>

<?php if ($isPrint): ?>
<script>
    window.addEventListener('load', function() {
        window.print();
    });
</script>
<?php endif; ?>

</body>
</html>