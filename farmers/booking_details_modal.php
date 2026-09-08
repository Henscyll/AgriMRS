<?php
session_start();
require_once '../includes/config.php';
include_once '../includes/farmer_auth.php';

$booking_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$farmer_id = $_SESSION['user_id'];

// Fetch booking details
$query = "SELECT b.*, 
                 m.machine_name, 
                 m.type, 
                 m.image_path,
                 m.description,
                 m.rental_price,
                 a.name AS association_name,
                 a.phone AS association_phone,
                 a.email AS association_email,
                 a.address,
                 a.municipality,
                 a.province
          FROM bookings b
          INNER JOIN machines m ON b.machine_id = m.id
          INNER JOIN associations a ON m.association_id = a.id
          WHERE b.id = ? AND b.farmer_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $booking_id, $farmer_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo '<div style="padding: 30px; text-align: center;">
            <i class="fas fa-exclamation-triangle" style="font-size: 48px; color: #ef4444; margin-bottom: 16px;"></i>
            <h3 style="color: #1f2937; margin-bottom: 8px;">Booking Not Found</h3>
            <p style="color: #6b7280; margin-bottom: 20px;">This booking does not exist or you do not have permission to view it.</p>
            <button onclick="closeModal()" style="padding: 10px 20px; background: #16a34a; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">Close</button>
          </div>';
    exit;
}

$booking = $result->fetch_assoc();

// Get status color
$statusColors = [
    'Pending' => '#f59e0b',
    'Approved' => '#3b82f6',
    'Completed' => '#10b981',
    'Cancelled' => '#ef4444'
];
$statusColor = $statusColors[$booking['status']] ?? '#6b7280';
?>

<style>
.modal-header {
    background: linear-gradient(135deg, #16a34a, #15803d);
    color: white;
    padding: 20px 24px;
    border-radius: 12px 12px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h2 {
    margin: 0;
    font-size: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.close-btn {
    background: rgba(255, 255, 255, 0.2);
    border: none;
    color: white;
    width: 32px;
    height: 32px;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    font-size: 18px;
}

.close-btn:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: rotate(90deg);
}

.modal-body {
    padding: 24px;
    max-height: calc(85vh - 140px);
    overflow-y: auto;
}

.detail-section {
    margin-bottom: 24px;
}

.detail-section:last-child {
    margin-bottom: 0;
}

.section-title {
    font-size: 14px;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.section-title i {
    color: #16a34a;
}

.machine-showcase {
    display: grid;
    grid-template-columns: 150px 1fr;
    gap: 20px;
    background: #f3f4f6;
    padding: 16px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.machine-image-large {
    width: 150px;
    height: 150px;
    border-radius: 8px;
    object-fit: cover;
    background: white;
}

.machine-info h3 {
    margin: 0 0 8px 0;
    font-size: 20px;
    color: #1f2937;
}

.machine-meta {
    display: flex;
    gap: 12px;
    margin-bottom: 12px;
    flex-wrap: wrap;
}

.meta-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    background: white;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    color: #16a34a;
}

.machine-description {
    font-size: 13px;
    color: #6b7280;
    line-height: 1.5;
}

.detail-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
}

.detail-item {
    background: #f3f4f6;
    padding: 12px;
    border-radius: 6px;
    border-left: 3px solid #16a34a;
}

.detail-label {
    font-size: 11px;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
    font-weight: 600;
}

.detail-value {
    font-size: 14px;
    color: #1f2937;
    font-weight: 600;
}

.status-display {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 16px;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
}

.association-card {
    background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
    padding: 16px;
    border-radius: 8px;
    border: 2px solid #d1d5db;
}

.association-card h4 {
    margin: 0 0 12px 0;
    color: #1f2937;
    font-size: 16px;
}

.contact-info {
    display: grid;
    gap: 8px;
}

.contact-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: #374151;
}

.contact-item i {
    color: #16a34a;
    width: 16px;
}

.notes-box {
    background: #fffbeb;
    border: 2px solid #fbbf24;
    border-radius: 6px;
    padding: 12px;
    font-size: 13px;
    color: #92400e;
    line-height: 1.5;
}

.modal-footer {
    padding: 16px 24px;
    background: #f9fafb;
    border-top: 2px solid #e5e7eb;
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    border-radius: 0 0 12px 12px;
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 14px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.btn-primary {
    background: #16a34a;
    color: white;
}

.btn-primary:hover {
    background: #15803d;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(22, 163, 74, 0.3);
}

.btn-danger {
    background: #ef4444;
    color: white;
}

.btn-danger:hover {
    background: #dc2626;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}

.btn-secondary {
    background: white;
    color: #6b7280;
    border: 2px solid #d1d5db;
}

.btn-secondary:hover {
    background: #f3f4f6;
    border-color: #9ca3af;
}

@media (max-width: 768px) {
    .machine-showcase {
        grid-template-columns: 1fr;
        text-align: center;
    }

    .machine-image-large {
        margin: 0 auto;
    }

    .detail-grid {
        grid-template-columns: 1fr;
    }

    .modal-footer {
        flex-direction: column;
    }

    .btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<div class="modal-header">
    <h2>
        <i class="fas fa-file-alt"></i>
        Booking Details #<?= $booking['id'] ?>
    </h2>
    <button class="close-btn" onclick="closeModal()">
        <i class="fas fa-times"></i>
    </button>
</div>

<div class="modal-body">
    <!-- Machine Showcase -->
    <div class="machine-showcase">
        <img src="<?= htmlspecialchars($booking['image_path'] ?? '../images/default-tractor.jpg') ?>" 
             alt="<?= htmlspecialchars($booking['machine_name']) ?>" 
             class="machine-image-large">
        
        <div class="machine-info">
            <h3><?= htmlspecialchars($booking['machine_name']) ?></h3>
            <div class="machine-meta">
                <span class="meta-badge">
                    <i class="fas fa-<?= $booking['type'] === 'Tractor' ? 'tractor' : 'cogs' ?>"></i>
                    <?= htmlspecialchars($booking['type']) ?>
                </span>
                <?php if ($booking['rental_price']): ?>
                <span class="meta-badge">
                    <i class="fas fa-tag"></i>
                    ₱<?= number_format($booking['rental_price'], 2) ?>/day
                </span>
                <?php endif; ?>
            </div>
            <?php if ($booking['description']): ?>
            <p class="machine-description"><?= htmlspecialchars($booking['description']) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Booking Information -->
    <div class="detail-section">
        <div class="section-title">
            <i class="fas fa-info-circle"></i>
            Booking Information
        </div>
        <div class="detail-grid">
            <div class="detail-item">
                <div class="detail-label">Status</div>
                <div class="detail-value">
                    <span class="status-display" style="background-color: <?= $statusColor ?>20; color: <?= $statusColor ?>;">
                        <i class="fas fa-circle" style="font-size: 8px;"></i>
                        <?= htmlspecialchars($booking['status']) ?>
                    </span>
                </div>
            </div>
            
            <div class="detail-item">
                <div class="detail-label">Booking Date</div>
                <div class="detail-value">
                    <i class="fas fa-calendar"></i>
                    <?= date('F d, Y', strtotime($booking['booking_date'])) ?>
                </div>
            </div>
            
            <div class="detail-item">
                <div class="detail-label">Farm Location</div>
                <div class="detail-value">
                    <i class="fas fa-map-marker-alt"></i>
                    <?= htmlspecialchars($booking['farm_location']) ?>
                </div>
            </div>
            
            <div class="detail-item">
                <div class="detail-label">Farm Size</div>
                <div class="detail-value">
                    <i class="fas fa-ruler-combined"></i>
                    <?= htmlspecialchars($booking['farm_size']) ?> hectares
                </div>
            </div>
            
            <div class="detail-item">
                <div class="detail-label">Request Date</div>
                <div class="detail-value">
                    <i class="fas fa-clock"></i>
                    <?= date('F d, Y - g:i A', strtotime($booking['created_at'])) ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Association Information -->
    <div class="detail-section">
        <div class="section-title">
            <i class="fas fa-users"></i>
            Association Information
        </div>
        <div class="association-card">
            <h4><?= htmlspecialchars($booking['association_name']) ?></h4>
            <div class="contact-info">
                <div class="contact-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <span><?= htmlspecialchars($booking['address']) ?>, <?= htmlspecialchars($booking['municipality']) ?>, <?= htmlspecialchars($booking['province']) ?></span>
                </div>
                <div class="contact-item">
                    <i class="fas fa-phone"></i>
                    <span><?= htmlspecialchars($booking['association_phone']) ?></span>
                </div>
                <?php if ($booking['association_email']): ?>
                <div class="contact-item">
                    <i class="fas fa-envelope"></i>
                    <span><?= htmlspecialchars($booking['association_email']) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Additional Notes -->
    <?php if (!empty($booking['notes'])): ?>
    <div class="detail-section">
        <div class="section-title">
            <i class="fas fa-sticky-note"></i>
            Additional Notes
        </div>
        <div class="notes-box">
            <?= nl2br(htmlspecialchars($booking['notes'])) ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="modal-footer">
    <?php if ($booking['status'] === 'Pending'): ?>
    <button class="btn btn-danger" onclick="if(confirm('Are you sure you want to cancel this booking?')) window.location.href='cancel_booking.php?id=<?= $booking['id'] ?>';">
        <i class="fas fa-times"></i>
        Cancel Booking
    </button>
    <?php endif; ?>
    
    <button class="btn btn-secondary" onclick="closeModal()">
        <i class="fas fa-arrow-left"></i>
        Back to List
    </button>
    
    <button class="btn btn-primary" onclick="window.print()">
        <i class="fas fa-print"></i>
        Print Details
    </button>
</div>