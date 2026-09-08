<?php
include('dashboard_itadmin.php');
require_once '../includes/config.php';

// Get farmer ID from URL
$farmer_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($farmer_id <= 0) {
    header("Location: admin_farmers.php");
    exit();
}

// Get farmer information
$farmer_query = "SELECT * FROM farmers WHERE id = ?";
$stmt = $conn->prepare($farmer_query);
$stmt->bind_param("i", $farmer_id);
$stmt->execute();
$farmer_result = $stmt->get_result();
$farmer = $farmer_result->fetch_assoc();

if (!$farmer) {
    header("Location: admin_farmers.php");
    exit();
}

// Get farmer's lots
$lots_query = "SELECT * FROM farmer_lots WHERE farmer_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($lots_query);
$stmt->bind_param("i", $farmer_id);
$stmt->execute();
$lots_result = $stmt->get_result();
$lots = $lots_result->fetch_all(MYSQLI_ASSOC);

// Calculate statistics
$total_lots = count($lots);
$active_lots = count(array_filter($lots, fn($lot) => $lot['status'] === 'Active'));
$total_farm_size = array_sum(array_column($lots, 'farm_size'));
$average_lot_size = $total_lots > 0 ? $total_farm_size / $total_lots : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Farm Lots - <?= htmlspecialchars($farmer['name']) ?></title>
<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        overflow-x: hidden;
    }

    .main-content {
        margin-top: 120px;
        padding: 20px;
        max-width: 1400px;
        margin-left: auto;
        margin-right: auto;
        height: calc(100vh - 120px);
        overflow-y: auto;
        overflow-x: hidden;
    }

    /* Custom Scrollbar */
    .main-content::-webkit-scrollbar {
        width: 12px;
    }

    .main-content::-webkit-scrollbar-track {
        background: #ffffff;
        border-left: 1px solid #e0e0e0;
    }

    .main-content::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 6px;
        border: 2px solid #ffffff;
    }

    .main-content::-webkit-scrollbar-thumb:hover {
        background: #555;
    }

    .back-nav {
        margin-bottom: 20px;
    }

    .btn-back {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        background: white;
        color: #2d7d46;
        text-decoration: none;
        border-radius: 8px;
        font-weight: 500;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        transition: all 0.2s;
        border: 1px solid #e0e0e0;
    }

    .btn-back:hover {
        background: #2d7d46;
        color: white;
        transform: translateX(-3px);
    }

    .page-header {
        background: white;
        border-radius: 12px;
        padding: 25px;
        margin-bottom: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .header-info h1 {
        font-size: 1.8rem;
        color: #333;
        margin-bottom: 5px;
    }

    .header-subtitle {
        color: #666;
        font-size: 0.95rem;
    }

    .btn {
        padding: 12px 24px;
        border: none;
        border-radius: 8px;
        font-size: 0.95rem;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s;
    }

    .btn-primary {
        background: linear-gradient(135deg, #2d7d46 0%, #388e3c 100%);
        color: white;
        box-shadow: 0 2px 8px rgba(45, 125, 70, 0.2);
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(45, 125, 70, 0.3);
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }

    .stat-card {
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .stat-icon {
        width: 55px;
        height: 55px;
        border-radius: 12px;
        background: linear-gradient(135deg, #2d7d46 0%, #388e3c 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.6rem;
        flex-shrink: 0;
    }

    .stat-info {
        flex: 1;
    }

    .stat-label {
        font-size: 0.75rem;
        color: #666;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 3px;
    }

    .stat-value {
        font-size: 1.4rem;
        font-weight: 700;
        color: #333;
    }

    .lots-container {
        background: white;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }

    .lots-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid #f0f0f0;
    }

    .lots-title {
        font-size: 1.2rem;
        font-weight: 600;
        color: #333;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .lots-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
        gap: 20px;
    }

    .lot-card {
        background: #f9f9f9;
        border-radius: 12px;
        padding: 20px;
        border-left: 4px solid #2d7d46;
        transition: all 0.3s;
        position: relative;
    }

    .lot-card:hover {
        background: #f0f8f1;
        transform: translateY(-3px);
        box-shadow: 0 4px 12px rgba(45, 125, 70, 0.15);
    }

    .lot-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 15px;
    }

    .lot-number {
        font-size: 1.15rem;
        font-weight: 700;
        color: #2d7d46;
    }

    .lot-status {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
    }

    .lot-status.active {
        background: #d4edda;
        color: #155724;
    }

    .lot-status.inactive {
        background: #f8d7da;
        color: #721c24;
    }

    .lot-details {
        display: flex;
        flex-direction: column;
        gap: 10px;
        margin-bottom: 15px;
    }

    .lot-detail {
        display: flex;
        align-items: start;
        gap: 10px;
        font-size: 0.9rem;
    }

    .lot-detail .icon {
        font-size: 1.1rem;
        color: #2d7d46;
        flex-shrink: 0;
        margin-top: 2px;
    }

    .lot-detail .label {
        font-weight: 600;
        color: #666;
        min-width: 70px;
    }

    .lot-detail .value {
        color: #333;
        flex: 1;
    }

    .lot-actions {
        display: flex;
        gap: 8px;
        padding-top: 15px;
        border-top: 1px solid #e0e0e0;
    }

    .lot-btn {
        flex: 1;
        padding: 10px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-size: 0.85rem;
        font-weight: 600;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
    }

    .btn-edit {
        background: #2d7d46;
        color: white;
    }

    .btn-edit:hover {
        background: #2d7d46;
        transform: translateY(-2px);
    }

    .btn-delete {
        background: #2d7d46;
        color: white;
    }

    .btn-delete:hover {
        background: #2c7e46;
        transform: translateY(-2px);
    }

    .btn-toggle {
        background: #2d7d46;
        color: white;
    }

    .btn-toggle:hover {
        background: #2c7e46;
        transform: translateY(-2px);
    }

    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #999;
    }

    .empty-state .icon {
        font-size: 4rem;
        margin-bottom: 20px;
        opacity: 0.5;
    }

    /* Modal Styles */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.6);
        backdrop-filter: blur(5px);
        justify-content: center;
        align-items: center;
        z-index: 9999;
        animation: fadeIn 0.3s ease;
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    .modal-content {
        background: white;
        padding: 0;
        border-radius: 16px;
        width: 90%;
        max-width: 650px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        animation: slideUp 0.3s ease;
    }

    @keyframes slideUp {
        from {
            transform: translateY(30px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    .modal-header {
        background: linear-gradient(135deg, #2d7d46 0%, #388e3c 100%);
        padding: 25px 30px;
        position: relative;
        border-radius: 16px 16px 0 0;
    }

    .modal-header h2 {
        margin: 0;
        font-size: 1.5rem;
        color: white;
    }

    .close-modal {
        position: absolute;
        top: 20px;
        right: 20px;
        background: rgba(255,255,255,0.2);
        border: none;
        color: white;
        font-size: 24px;
        width: 35px;
        height: 35px;
        border-radius: 50%;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
    }

    .close-modal:hover {
        background: rgba(255,255,255,0.3);
        transform: rotate(90deg);
    }

    .modal-body {
        padding: 30px;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        font-weight: 600;
        margin-bottom: 8px;
        color: #333;
        font-size: 0.9rem;
    }

    .form-group label .required {
        color: #d32f2f;
        font-weight: bold;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 12px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 0.95rem;
        transition: border-color 0.2s;
        font-family: inherit;
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #2d7d46;
        background: #f9fff9;
    }

    .form-group textarea {
        resize: vertical;
        min-height: 80px;
    }

    .modal-footer {
        padding: 20px 30px;
        background: #f9f9f9;
        border-top: 1px solid #e0e0e0;
        display: flex;
        gap: 12px;
        justify-content: flex-end;
        border-radius: 0 0 16px 16px;
    }

    .btn-secondary {
        background: white;
        color: #666;
        border: 2px solid #e0e0e0;
    }

    .btn-secondary:hover {
        background: #f5f5f5;
        border-color: #ccc;
    }

    .info-box {
        background: #e8f5e9;
        border-left: 4px solid #2d7d46;
        padding: 12px 15px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-size: 0.85rem;
        color: #1b5e20;
        display: flex;
        align-items: start;
        gap: 10px;
    }

    .info-box .icon {
        font-size: 1.2rem;
        flex-shrink: 0;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .main-content {
            margin-top: 80px;
            height: calc(100vh - 80px);
        }

        .page-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 15px;
        }

        .lots-grid {
            grid-template-columns: 1fr;
        }

        .stats-grid {
            grid-template-columns: 1fr;
        }

        .modal-content {
            width: 95%;
        }
    }
</style>
</head>
<body>
<div class="main-content">
    <div class="back-nav">
        <a href="farmers_profile.php?id=<?= $farmer_id ?>" class="btn-back">
            <span>←</span> Back to Profile
        </a>
    </div>

    <div class="page-header">
        <div class="header-info">
            <h1>Farm Lots Management</h1>
            <p class="header-subtitle">
                Managing lots for <strong><?= htmlspecialchars($farmer['name']) ?></strong>
            </p>
        </div>
    </div>

    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">📍</div>
            <div class="stat-info">
                <div class="stat-label">Total Lots</div>
                <div class="stat-value"><?= $total_lots ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">✓</div>
            <div class="stat-info">
                <div class="stat-label">Active Lots</div>
                <div class="stat-value"><?= $active_lots ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">🌾</div>
            <div class="stat-info">
                <div class="stat-label">Total Area</div>
                <div class="stat-value"><?= number_format($total_farm_size, 2) ?> ha</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">📊</div>
            <div class="stat-info">
                <div class="stat-label">Avg. Lot Size</div>
                <div class="stat-value"><?= number_format($average_lot_size, 2) ?> ha</div>
            </div>
        </div>
    </div>

    <!-- Lots Container -->
    <div class="lots-container">
        <div class="lots-header">
            <div class="lots-title">
                <span>🗺️</span> Farm Lots (<?= $total_lots ?>)
            </div>
        </div>

        <?php if (count($lots) > 0): ?>
            <div class="lots-grid">
                <?php foreach ($lots as $lot): ?>
                    <div class="lot-card">
                        <div class="lot-header">
                            <div class="lot-number"><?= htmlspecialchars($lot['lot_number']) ?></div>
                            <span class="lot-status <?= strtolower($lot['status']) ?>">
                                <?= htmlspecialchars($lot['status']) ?>
                            </span>
                        </div>
                        <div class="lot-details">
                            <div class="lot-detail">
                                <span class="icon">🌾</span>
                                <span class="label">Size:</span>
                                <span class="value"><?= number_format($lot['farm_size'], 2) ?> hectares</span>
                            </div>
                            <div class="lot-detail">
                                <span class="icon">📍</span>
                                <span class="label">Location:</span>
                                <span class="value"><?= htmlspecialchars($lot['farm_location']) ?></span>
                            </div>
                            <?php if ($lot['municipality'] || $lot['barangay']): ?>
                            <div class="lot-detail">
                                <span class="icon">🗺️</span>
                                <span class="label">Address:</span>
                                <span class="value">
                                    <?php
                                    $address_parts = array_filter([
                                        $lot['barangay'],
                                        $lot['municipality'],
                                        $lot['province']
                                    ]);
                                    echo htmlspecialchars(implode(', ', $address_parts));
                                    ?>
                                </span>
                            </div>
                            <?php endif; ?>
                            <div class="lot-detail">
                                <span class="icon">📅</span>
                                <span class="label">Created:</span>
                                <span class="value"><?= date('M d, Y', strtotime($lot['created_at'])) ?></span>
                            </div>
                        </div>
                        
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="icon">📭</div>
                <h3>No Lots Found</h3>
                <p>This farmer doesn't have any lots yet. Click "Add New Lot" to get started.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add/Edit Lot Modal -->
<div id="lotModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <button class="close-modal" onclick="closeLotModal()">&times;</button>
            <h2 id="modalTitle">Add New Lot</h2>
        </div>
        <div class="modal-body">
            <div class="info-box">
                <span class="icon">ℹ️</span>
                <span>Fill in the lot details below. Fields marked with <span style="color: #d32f2f;">*</span> are required.</span>
            </div>

            <form id="lotForm" method="POST" action="process_lot.php">
                <input type="hidden" name="farmer_id" value="<?= $farmer_id ?>">
                <input type="hidden" name="lot_id" id="lot_id" value="">
                <input type="hidden" name="action" id="formAction" value="add">

                <div class="form-group">
                    <label>Lot Number <span class="required">*</span></label>
                    <input type="text" name="lot_number" id="lot_number" placeholder="e.g., LOT-001, FIELD-A" required>
                </div>

                <div class="form-group">
                    <label>Farm Size (Hectares) <span class="required">*</span></label>
                    <input type="number" step="0.01" name="farm_size" id="farm_size" placeholder="0.00" required>
                </div>

                <div class="form-group">
                    <label>Farm Location <span class="required">*</span></label>
                    <textarea name="farm_location" id="farm_location" placeholder="Specific location/address of this lot" required></textarea>
                </div>

                <div class="form-group">
                    <label>Province</label>
                    <input type="text" name="province" id="province" placeholder="Province (optional)" value="<?= htmlspecialchars($farmer['province']) ?>">
                </div>

                <div class="form-group">
                    <label>Municipality</label>
                    <input type="text" name="municipality" id="municipality" placeholder="Municipality (optional)" value="<?= htmlspecialchars($farmer['municipality']) ?>">
                </div>

                <div class="form-group">
                    <label>Barangay</label>
                    <input type="text" name="barangay" id="barangay" placeholder="Barangay (optional)" value="<?= htmlspecialchars($farmer['barangay']) ?>">
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeLotModal()">
                <span>✕</span> Cancel
            </button>
            <button type="submit" form="lotForm" class="btn btn-primary">
                <span>✓</span> Save Lot
            </button>
        </div>
    </div>
</div>

<script>
function openAddLotModal() {
    document.getElementById('modalTitle').textContent = 'Add New Lot';
    document.getElementById('formAction').value = 'add';
    document.getElementById('lot_id').value = '';
    document.getElementById('lotForm').reset();
    document.getElementById('province').value = '<?= htmlspecialchars($farmer['province']) ?>';
    document.getElementById('municipality').value = '<?= htmlspecialchars($farmer['municipality']) ?>';
    document.getElementById('barangay').value = '<?= htmlspecialchars($farmer['barangay']) ?>';
    document.getElementById('lotModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function editLot(lot) {
    document.getElementById('modalTitle').textContent = 'Edit Lot';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('lot_id').value = lot.id;
    document.getElementById('lot_number').value = lot.lot_number;
    document.getElementById('farm_size').value = lot.farm_size;
    document.getElementById('farm_location').value = lot.farm_location;
    document.getElementById('province').value = lot.province || '';
    document.getElementById('municipality').value = lot.municipality || '';
    document.getElementById('barangay').value = lot.barangay || '';
    document.getElementById('lotModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeLotModal() {
    document.getElementById('lotModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

function toggleLotStatus(lotId, currentStatus) {
    const newStatus = currentStatus === 'Active' ? 'Inactive' : 'Active';
    if (confirm(`Are you sure you want to ${newStatus.toLowerCase()} this lot?`)) {
        window.location.href = `process_lot.php?action=toggle&lot_id=${lotId}&farmer_id=<?= $farmer_id ?>`;
    }
}

function deleteLot(lotId) {
    if (confirm('Are you sure you want to delete this lot? This action cannot be undone.')) {
        window.location.href = `process_lot.php?action=delete&lot_id=${lotId}&farmer_id=<?= $farmer_id ?>`;
    }
}

// Close modal when clicking outside
window.onclick = function(e) {
    const modal = document.getElementById('lotModal');
    if (e.target === modal) {
        closeLotModal();
    }
}

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeLotModal();
    }
});

// Show success/error messages if present
<?php if (isset($_SESSION['success'])): ?>
    alert('<?= addslashes($_SESSION['success']) ?>');
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    alert('<?= addslashes($_SESSION['error']) ?>');
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>
</script>
</body>
</html>