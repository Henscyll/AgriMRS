<?php 
if (session_status() === PHP_SESSION_NONE) { 
  session_start(); 
} 
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'da staff') { 
  header("Location: ../login.php"); 
  exit; 
} 
$current_page = basename($_SERVER['PHP_SELF']); 
$base_url = '/agri_system/DA_staff/'; 
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="../images/da3.png" rel="icon">

  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      margin: 0;
      font-family: "Segoe UI", Arial, sans-serif;
      background-color: #f4f6f9;
      background-size: cover;
      background-position: center;
      background-attachment: fixed;
      min-height: 100vh;
    }

    /* Fixed Green Header */
    .header {
      background-color: #2e7d32;
      color: white;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      z-index: 1000;
    }

    .header-top {
      align-items: center;
      justify-content: center;
      padding: 10px 10px;
      position: relative;
    }

    .header-top img {
      width: 80px;
      height: auto;
      object-fit: contain;
      position: absolute;
      left: 220px;
      top: 60%;
      transform: translateY(-50%);
    }

    .header-top h1 {
      font-size: 24px;
      font-weight: bold;
      line-height: 1.2;
      text-align: center;
      color: #fff;
    }

    .header-top .subtitle {
      font-size: 16px;
      font-weight: 400;
      text-align: center;
      color: #e8f5e9;
      margin-top: 4px;
    }

    .header-bottom {
      display: flex;
      justify-content: center;
      align-items: center;
      padding: 8px 25px;
      flex-wrap: wrap;
    }

    .header-bottom nav {
      display: flex;
      gap: 15px;
      flex-wrap: wrap;
    }

    .header-bottom nav a {
      text-decoration: none;
      color: #e8f5e9;
      font-size: 15px;
      font-weight: 500;
      padding: 6px 10px;
      border-radius: 6px;
      transition: background 0.2s ease, color 0.2s ease;
    }

    .header-bottom nav a:hover {
      background: #388e3c;
      color: #fff;
    }

    .header-bottom nav a.active {
      background: #a5d6a7;
      color: #1b5e20;
    }

    /* ✅ Sticky Welcome & Logout Bar */
    .usernames-bar {
      position: fixed;
      top: 118px; /* Positioned right below the green header */
      left: 0;
      width: 100%;
      padding: 20px 25px;
      color: #333;
      font-size: 16px;
      display: flex;
      align-items: center;
      gap: 15px;
      z-index: 999;
    }

    .usernames-bar a {
      color: #000;
      text-decoration: underline;
      font-weight: 500;
    }

    /* Offset the page content to clear both fixed top bars (~165px combined height) */
    .main-content {
      padding: 165px 20px 20px 20px;
      min-height: auto;
    }

    .main-content:has(.welcome-card) {
      height: calc(100vh - 165px);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 165px 0 0 0;
    }

    .welcome-card {
      background: #ffffff;
      padding: 40px 50px;
      border-radius: 12px;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
      text-align: center;
      max-width: 600px;
      width: 100%;
      border-top: 5px solid #2e7d32;
    }

    .welcome-card h2 {
      color: #2e7d32;
      font-size: 32px;
      margin-bottom: 12px;
      font-weight: 700;
    }

    .welcome-card p {
      color: #555;
      font-size: 16px;
      line-height: 1.6;
    }
  </style>
</head>

<body>

  <header class="header">
    <div class="header-top">
      <img src="../images/123.png" alt="DA Logo">
      <h1>Agricultural Machineries Reservation & Monitoring System</h1>
      <p class="subtitle">Department of Agriculture Region IX - Zamboanga Peninsula</p>
    </div>

    <div class="header-bottom">
      <nav>
        <a href="<?= $base_url ?>staff_farmers.php" class="<?= in_array($current_page, ['staff_farmers.php', 'staff_farmers_profile_updated.php', 'staff_farmers_lots.php', 'farmer_transactions.php']) ? 'active' : '' ?>">Farmers</a>
        <a href="<?= $base_url ?>staff_reservation.php" class="<?= $current_page === 'staff_reservation.php' ? 'active' : '' ?>">Reservation </a>
        <a href="<?= $base_url ?>staff_machines.php" class="<?= in_array($current_page, ['staff_machines.php', 'machine_profile.php']) ? 'active' : '' ?>">Machines</a>
        <a href="<?= $base_url ?>staff_associations.php" class="<?= in_array($current_page, ['staff_associations.php', 'association_profile.php']) ? 'active' : '' ?>">Associations</a>
        <a href="<?= $base_url ?>staff_aboutus.php" class="<?= $current_page === 'staff_aboutus.php' ? 'active' : '' ?>">About Us</a>
        <a href="<?= $base_url ?>staff_contactus.php" class="<?= $current_page === 'staff_contactus.php' ? 'active' : '' ?>">Contact Us</a>
        <a href="<?= $base_url ?>staff_account_setting.php" class="<?= $current_page === 'staff_account_setting.php' ? 'active' : '' ?>">Account Settings</a>
      </nav>
    </div>
  </header>

  <!-- Welcome & Logout Section below header -->
  <div class="usernames-bar">
    Welcome DA Staff
    <a href="/agri_system/logout.php">Logout</a>
  </div>
  
  <main class="main-content">
    <?php
    $home_pages = ['da_header.php',
      'dashboard_president.php',
      'operator_dashboard.php',
      'tractor.php',
      'da_header.php',
      'dastaff_header.php'];

    if (in_array($current_page, $home_pages)):
      ?>
      <div class="welcome-card">
        <h2>Welcome, DA Staff!</h2>
        <p>You have successfully logged in.</p>
      </div>
    <?php else: ?>
    <?php endif; ?>
  </main>

</body>
</html>