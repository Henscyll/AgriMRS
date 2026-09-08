<?php
session_start();
require '../includes/config.php';
include 'farmers_header.php';
include_once '../includes/farmer_auth.php';

$farmer_id = $_SESSION['user_id'];
$message = "";
$message_type = "";

/* Get farmer's lots for booking */
$lots_query = $conn->prepare("
    SELECT id, lot_number, farm_location, farm_size 
    FROM farmer_lots 
    WHERE farmer_id = ? AND status = 'Active'
    ORDER BY lot_number
");
$lots_query->bind_param("i", $farmer_id);
$lots_query->execute();
$farmer_lots = $lots_query->get_result();
$has_lots = $farmer_lots->num_rows > 0;

/* 🔍 Search */
$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? 'all';

/* ✅ Handle booking */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $machine_id = intval($_POST['machine_id']);
    $booking_date = $_POST['booking_date'];
    $lot_id = !empty($_POST['lot_id']) ? intval($_POST['lot_id']) : null;
    $notes = $_POST['notes'] ?? '';

    // Validate lot ownership
    if ($lot_id) {
        $verify_lot = $conn->prepare("SELECT id, farm_size FROM farmer_lots WHERE id = ? AND farmer_id = ?");
        $verify_lot->bind_param("ii", $lot_id, $farmer_id);
        $verify_lot->execute();
        $lot_result = $verify_lot->get_result();
        
        if ($lot_result->num_rows === 0) {
            $message = "❌ Invalid lot selection.";
            $message_type = "error";
        } else {
            $lot_data = $lot_result->fetch_assoc();
            $farm_size = $lot_data['farm_size'];
            
            // Check if machine is already booked
            $check = $conn->prepare("
                SELECT id FROM bookings 
                WHERE machine_id = ? 
                AND booking_date = ?
                AND status IN ('Pending','Approved')
            ");
            $check->bind_param("is", $machine_id, $booking_date);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                $message = "❌ This tractor is already booked on this date.";
                $message_type = "error";
            } else {
                // Insert booking with lot_id and farm_size
                $stmt = $conn->prepare("
                    INSERT INTO bookings (machine_id, farmer_id, lot_id, booking_date, farm_size, notes)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("iiisds", $machine_id, $farmer_id, $lot_id, $booking_date, $farm_size, $notes);
                
                if ($stmt->execute()) {
                    $message = "✅ Booking request submitted successfully! Your booking ID is #" . $stmt->insert_id;
                    $message_type = "success";
                } else {
                    $message = "❌ Error: " . $stmt->error;
                    $message_type = "error";
                }
            }
        }
    } else {
        $message = "❌ Please select a lot for this booking.";
        $message_type = "error";
    }
}

/* 🔍 Build search query */
$where = "WHERE m.type = 'Tractor' AND m.status = 'Active'";
$params = [];

if (!empty($search)) {
    if ($filter === 'association') {
        $where .= " AND a.name LIKE ?";
        $params[] = "%$search%";
    } elseif ($filter === 'machine') {
        $where .= " AND m.machine_name LIKE ?";
        $params[] = "%$search%";
    } elseif ($filter === 'municipality') {
        $where .= " AND a.municipality LIKE ?";
        $params[] = "%$search%";
    } else {
        $where .= " AND (m.machine_name LIKE ? OR a.name LIKE ? OR a.municipality LIKE ?)";
        $params = ["%$search%", "%$search%", "%$search%"];
    }
}

$sql = "
    SELECT m.*, a.name AS association_name, a.municipality
    FROM machines m
    LEFT JOIN associations a ON m.association_id = a.id
    $where
    ORDER BY m.created_at DESC
";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param(str_repeat("s", count($params)), ...$params);
}
$stmt->execute();
$tractors = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
<title>Book Tractor</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
    --primary-color: #2e7d32;
    --primary-dark: #1b5e20;
    --primary-light: #4caf50;
    --danger-color: #d32f2f;
    --warning-color: #f57c00;
    --info-color: #0288d1;
    --success-color: #388e3c;
    --bg-light: #f5f5f5;
    --border-color: #e0e0e0;
    --text-dark: #212121;
    --text-light: #757575;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: var(--bg-light);
    color: var(--text-dark);
}

.page-content {
    margin-top: 120px;
    padding: 20px;
    max-width: 1400px;
    margin-left: auto;
    margin-right: auto;
}

/* Header */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    flex-wrap: wrap;
    gap: 15px;
}

.page-header h2 {
    font-size: 28px;
    color: var(--primary-dark);
    display: flex;
    align-items: center;
    gap: 10px;
}

.page-header h2 i {
    color: var(--primary-color);
}

/* Alert Messages */
.alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    animation: slideDown 0.3s ease;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.alert i {
    font-size: 20px;
}

.alert.success {
    background: #e8f5e9;
    color: var(--success-color);
    border-left: 4px solid var(--success-color);
}

.alert.error {
    background: #ffebee;
    color: var(--danger-color);
    border-left: 4px solid var(--danger-color);
}

.alert.warning {
    background: #fff3e0;
    color: var(--warning-color);
    border-left: 4px solid var(--warning-color);
}

.alert.info {
    background: #e3f2fd;
    color: var(--info-color);
    border-left: 4px solid var(--info-color);
}

/* Search Bar */
.search-container {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin-bottom: 25px;
}

.search-box {
    display: grid;
    grid-template-columns: 1fr auto auto auto;
    gap: 12px;
    align-items: end;
}

.search-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.search-group label {
    font-weight: 600;
    font-size: 13px;
    color: var(--text-light);
    text-transform: uppercase;
}

.search-box input,
.search-box select {
    padding: 10px 14px;
    border-radius: 6px;
    border: 2px solid var(--border-color);
    font-size: 14px;
    transition: all 0.3s ease;
    font-family: inherit;
}

.search-box input:focus,
.search-box select:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(46, 125, 50, 0.1);
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
    color: white;
    box-shadow: 0 2px 8px rgba(46, 125, 50, 0.3);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(46, 125, 50, 0.4);
}

.btn-secondary {
    background: white;
    color: var(--text-dark);
    border: 2px solid var(--border-color);
}

.btn-secondary:hover {
    background: var(--bg-light);
    border-color: var(--primary-color);
}

/* Tractor Grid */
.tractor-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 20px;
}

/* Card */
.card {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.15);
    border-color: var(--primary-color);
}

.card-image {
    position: relative;
    height: 200px;
    overflow: hidden;
}

.card-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.card:hover .card-image img {
    transform: scale(1.05);
}

.card-badge {
    position: absolute;
    top: 12px;
    right: 12px;
    background: rgba(46, 125, 50, 0.95);
    color: white;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.card-content {
    padding: 20px;
}

.card-title {
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 8px;
    color: var(--text-dark);
    display: flex;
    align-items: center;
    gap: 8px;
}

.card-title i {
    color: var(--primary-color);
}

.card-info {
    margin-bottom: 15px;
}

.info-item {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 6px 0;
    font-size: 14px;
    color: var(--text-light);
}

.info-item i {
    width: 16px;
    color: var(--primary-color);
}

.card-description {
    font-size: 13px;
    color: var(--text-light);
    line-height: 1.5;
    margin-bottom: 15px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.booking-form {
    border-top: 2px solid var(--bg-light);
    padding-top: 15px;
}

.form-group {
    margin-bottom: 12px;
}

.form-group label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: var(--text-dark);
    margin-bottom: 6px;
}

.form-group label .required {
    color: var(--danger-color);
}

.form-group select,
.form-group input[type="date"],
.form-group textarea {
    width: 100%;
    padding: 10px;
    border: 2px solid var(--border-color);
    border-radius: 6px;
    font-size: 14px;
    transition: all 0.3s ease;
    font-family: inherit;
}

.form-group select:focus,
.form-group input[type="date"]:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(46, 125, 50, 0.1);
}

.form-group textarea {
    resize: vertical;
    min-height: 60px;
}

.lot-info {
    background: var(--bg-light);
    padding: 10px;
    border-radius: 6px;
    font-size: 12px;
    color: var(--text-light);
    margin-top: 6px;
}

.btn-book {
    width: 100%;
    padding: 12px;
    font-size: 15px;
    background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all 0.3s ease;
}

.btn-book:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(46, 125, 50, 0.4);
}

.btn-book:disabled {
    background: var(--border-color);
    cursor: not-allowed;
    opacity: 0.6;
}

.no-lots-warning {
    background: #fff3e0;
    border: 2px solid var(--warning-color);
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 20px;
    display: flex;
    align-items: start;
    gap: 12px;
}

.no-lots-warning i {
    color: var(--warning-color);
    font-size: 24px;
    margin-top: 2px;
}

.no-lots-warning-content h3 {
    color: var(--warning-color);
    margin-bottom: 8px;
    font-size: 16px;
}

.no-lots-warning-content p {
    color: var(--text-light);
    margin-bottom: 12px;
    font-size: 14px;
}

.no-lots-warning-content a {
    color: white;
    background: var(--warning-color);
    padding: 8px 16px;
    border-radius: 6px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.3s ease;
}

.no-lots-warning-content a:hover {
    background: #e64a19;
    transform: translateY(-2px);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.empty-state i {
    font-size: 64px;
    color: var(--border-color);
    margin-bottom: 20px;
}

.empty-state h3 {
    color: var(--text-dark);
    margin-bottom: 10px;
}

.empty-state p {
    color: var(--text-light);
    font-size: 14px;
}

/* Responsive */
@media (max-width: 768px) {
    .page-content {
        padding: 15px;
        margin-top: 100px;
    }

    .search-box {
        grid-template-columns: 1fr;
    }

    .tractor-grid {
        grid-template-columns: 1fr;
    }

    .page-header {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>
</head>

<body>

<div class="page-content">
    <div class="page-header">
        <h2><i class="fas fa-tractor"></i> Available Tractors</h2>
    </div>

    <?php if ($message): ?>
        <div class="alert <?= $message_type ?>">
            <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : ($message_type === 'error' ? 'exclamation-circle' : 'info-circle') ?>"></i>
            <span><?= $message ?></span>
        </div>
    <?php endif; ?>

    <?php if (!$has_lots): ?>
        <div class="no-lots-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <div class="no-lots-warning-content">
                <h3>No Farm Lots Registered</h3>
                <p>You need to register at least one farm lot before you can book a tractor. Please add your farm lot information first.</p>
                <a href="my_lots.php">
                    <i class="fas fa-plus-circle"></i>
                    Add Farm Lot
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Search -->
    <div class="search-container">
        <form method="GET" class="search-box">
            <div class="search-group">
                <label><i class="fas fa-search"></i> Search</label>
                <input type="text" name="search" placeholder="Search tractors..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="search-group">
                <label><i class="fas fa-filter"></i> Filter By</label>
                <select name="filter">
                    <option value="all" <?= $filter=='all'?'selected':'' ?>>All Fields</option>
                    <option value="association" <?= $filter=='association'?'selected':'' ?>>Association</option>
                    <option value="machine" <?= $filter=='machine'?'selected':'' ?>>Machine Name</option>
                    <option value="municipality" <?= $filter=='municipality'?'selected':'' ?>>Municipality</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i>
                Search
            </button>
            <?php if (!empty($search)): ?>
                <a href="tractor.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i>
                    Clear
                </a>
            <?php endif; ?>
        </form>
    </div>

    <div class="tractor-grid">
        <?php if ($tractors->num_rows > 0): ?>
            <?php 
            // Reset lots result for each card
            $lots_query->execute();
            $farmer_lots = $lots_query->get_result();
            
            while ($row = $tractors->fetch_assoc()): 
            ?>
            <div class="card">
                <div class="card-image">
                    <img src="<?= !empty($row['image_path']) ? htmlspecialchars($row['image_path']) : '../uploads/default-tractor.jpg' ?>" 
                         alt="<?= htmlspecialchars($row['machine_name']) ?>"
                         onerror="this.src='../uploads/default-tractor.jpg'">
                    <span class="card-badge">
                        <i class="fas fa-check-circle"></i>
                        <?= htmlspecialchars($row['status']) ?>
                    </span>
                </div>

                <div class="card-content">
                    <h3 class="card-title">
                        <i class="fas fa-tractor"></i>
                        <?= htmlspecialchars($row['machine_name']) ?>
                    </h3>

                    <div class="card-info">
                        <div class="info-item">
                            <i class="fas fa-users"></i>
                            <span><?= htmlspecialchars($row['association_name']) ?></span>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <span><?= htmlspecialchars($row['municipality']) ?></span>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-peso-sign"></i>
                            <span><strong>₱<?= number_format($row['price_per_hectare'], 2) ?></strong> per hectare</span>
                        </div>
                    </div>

                    <?php if (!empty($row['description'])): ?>
                        <p class="card-description"><?= htmlspecialchars($row['description']) ?></p>
                    <?php endif; ?>

                    <form method="POST" class="booking-form">
                        <input type="hidden" name="machine_id" value="<?= $row['id'] ?>">

                        <div class="form-group">
                            <label>
                                Select Your Farm Lot <span class="required">*</span>
                            </label>
                            <select name="lot_id" id="lot-<?= $row['id'] ?>" required <?= !$has_lots ? 'disabled' : '' ?>>
                                <option value="">-- Select a lot --</option>
                                <?php 
                                $lots_query->data_seek(0); // Reset pointer
                                while ($lot = $lots_query->fetch_assoc()): 
                                ?>
                                    <option value="<?= $lot['id'] ?>" 
                                            data-location="<?= htmlspecialchars($lot['farm_location']) ?>"
                                            data-size="<?= $lot['farm_size'] ?>">
                                        Lot #<?= htmlspecialchars($lot['lot_number']) ?> (<?= number_format($lot['farm_size'], 2) ?> ha)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <div class="lot-info" id="lot-info-<?= $row['id'] ?>" style="display: none;">
                                <strong>Location:</strong> <span class="lot-location"></span><br>
                                <strong>Size:</strong> <span class="lot-size"></span> hectares<br>
                                <strong>Estimated Cost:</strong> ₱<span class="lot-cost"></span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>
                                Booking Date <span class="required">*</span>
                            </label>
                            <input type="date" name="booking_date" required min="<?= date('Y-m-d') ?>" <?= !$has_lots ? 'disabled' : '' ?>>
                        </div>

                        <div class="form-group">
                            <label>Notes (Optional)</label>
                            <textarea name="notes" placeholder="Any special instructions or requirements..." <?= !$has_lots ? 'disabled' : '' ?>></textarea>
                        </div>

                        <button type="submit" class="btn-book" <?= !$has_lots ? 'disabled' : '' ?>>
                            <i class="fas fa-calendar-check"></i>
                            <?= $has_lots ? 'Book This Tractor' : 'Add Farm Lot First' ?>
                        </button>
                    </form>
                </div>
            </div>

            <script>
            document.getElementById('lot-<?= $row['id'] ?>').addEventListener('change', function() {
                const selected = this.options[this.selectedIndex];
                const infoDiv = document.getElementById('lot-info-<?= $row['id'] ?>');
                
                if (this.value) {
                    const location = selected.getAttribute('data-location');
                    const size = parseFloat(selected.getAttribute('data-size'));
                    const pricePerHa = <?= $row['price_per_hectare'] ?>;
                    const cost = (size * pricePerHa).toFixed(2);
                    
                    infoDiv.querySelector('.lot-location').textContent = location;
                    infoDiv.querySelector('.lot-size').textContent = size.toFixed(2);
                    infoDiv.querySelector('.lot-cost').textContent = parseFloat(cost).toLocaleString('en-PH', {minimumFractionDigits: 2});
                    infoDiv.style.display = 'block';
                } else {
                    infoDiv.style.display = 'none';
                }
            });
            </script>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state" style="grid-column: 1 / -1;">
                <i class="fas fa-search"></i>
                <h3>No Tractors Found</h3>
                <p>No active tractors match your search criteria. Try adjusting your filters.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>