<?php
// ✅ Security check FIRST, before any includes
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'farmer') {
    header('Location: ../login.php');
    exit();
}

// ✅ NOW include the header
include('farmers_header.php');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farmer Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .dashboard-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .welcome-banner {
            background: linear-gradient(135deg, #16a34a, #15803d);
            color: white;
            padding: 40px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .welcome-banner h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .welcome-banner p {
            font-size: 18px;
            opacity: 0.9;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .action-card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align: center;
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }

        .action-card i {
            font-size: 48px;
            color: #16a34a;
            margin-bottom: 15px;
        }

        .action-card h3 {
            font-size: 20px;
            margin-bottom: 10px;
            color: #1f2937;
        }

        .action-card p {
            color: #6b7280;
            font-size: 14px;
        }

        .info-section {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .info-section h2 {
            color: #1f2937;
            margin-bottom: 20px;
            font-size: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-section h2 i {
            color: #16a34a;
        }

        .info-list {
            list-style: none;
            padding: 0;
        }

        .info-list li {
            padding: 15px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .info-list li:last-child {
            border-bottom: none;
        }

        .info-list li i {
            color: #16a34a;
            font-size: 20px;
            width: 24px;
        }

        @media (max-width: 768px) {
            .welcome-banner h1 {
                font-size: 24px;
            }

            .welcome-banner p {
                font-size: 16px;
            }

            .quick-actions {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<div class="dashboard-container">
    <div class="welcome-banner">
        <h1>Welcome, <?= htmlspecialchars($_SESSION['user_name'] ?? 'Farmer') ?>!</h1>
        <p>Manage your agricultural machinery bookings with ease</p>
    </div>

    <div class="quick-actions">
        <a href="tractor.php" class="action-card">
            <i class="fas fa-tractor"></i>
            <h3>Book Machinery</h3>
            <p>Reserve tractors and harvesters for your farm</p>
        </a>

        <a href="my_reservation.php" class="action-card">
            <i class="fas fa-calendar-check"></i>
            <h3>My Reservations</h3>
            <p>View and manage your booking history</p>
        </a>

        <a href="my_account.php" class="action-card">
            <i class="fas fa-user-circle"></i>
            <h3>My Account</h3>
            <p>Update your profile and settings</p>
        </a>
    </div>

    <div class="info-section">
        <h2>
            <i class="fas fa-info-circle"></i>
            How It Works
        </h2>
        <ul class="info-list">
            <li>
                <i class="fas fa-search"></i>
                <div>
                    <strong>Browse Available Machines</strong><br>
                    <small>View all tractors and harvesters available for booking</small>
                </div>
            </li>
            <li>
                <i class="fas fa-calendar-plus"></i>
                <div>
                    <strong>Select Your Date</strong><br>
                    <small>Choose the date you need the machinery</small>
                </div>
            </li>
            <li>
                <i class="fas fa-clock"></i>
                <div>
                    <strong>Wait for Approval</strong><br>
                    <small>Your booking will be reviewed by the association</small>
                </div>
            </li>
            <li>
                <i class="fas fa-check-circle"></i>
                <div>
                    <strong>Get Confirmation</strong><br>
                    <small>Receive approval and use the machinery on your scheduled date</small>
                </div>
            </li>
        </ul>
    </div>
</div>

</body>
</html>

<?php include('../footer.php'); ?>