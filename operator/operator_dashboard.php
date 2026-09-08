<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'operator') {
    header("Location: ../login.php");
    exit;
}
$current_page   = basename($_SERVER['PHP_SELF']);
$user_role      = $_SESSION['user_role'] ?? '';
$email_address  = $_SESSION['user_email'] ?? '';
$base_url       = "/agri_system/operator/";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
  <title>Operator Dashboard</title>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      overflow: hidden;
      margin: 0;
      font-family: "Segoe UI", Arial, sans-serif;
      
      background-size: cover;
      background-position: center;
      background-attachment: fixed;
      overflow: hidden;
    }

    /* ✅ Unified Header (Top + Bottom merged) */
    .header {
      background-color: #2e7d32;
      color: white;
      box-shadow: 0 2px 8px rgba(0,0,0,0.3);
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      z-index: 1000;
    }

    .header-top {
      align-items: center;
      justify-content: center;
      padding: 20px 20px;
      position: relative;
    }

    .header-top img {
      width: 110px;
      height: auto;
      object-fit: contain;
      position: absolute;
      left: 220px;
      top: 70%;
      transform: translateY(-50%); 
    }

    .header-top h1 {
      font-size: 24px;
      font-weight: bold;
      line-height: 1.3;
      text-align: center;
      color: #fff;
      margin: 0 150px;
      padding: 0 20px;
    }

    .header-bottom {
      display: flex;
      justify-content: center;
      align-items: center;
      padding: 10px 25px;
      position: relative;
    }

    .header-bottom nav {
      display: flex;
      gap: 15px;
      flex-wrap: wrap;
      justify-content: center;
    }

    .header-bottom nav a {
      text-decoration: none;
      color: #e8f5e9;
      font-size: 15px;
      font-weight: 500;
      padding: 8px 12px;
      border-radius: 6px;
      transition: background 0.2s ease, color 0.2s ease;
      white-space: nowrap;
    }

    .header-bottom nav a:hover {
      background: #388e3c;
      color: #fff;
    }

    .header-bottom nav a.active {
      background: #a5d6a7;
      color: #1b5e20;
    }

    /* Dropdown Styles */
    .dropdown {
      position: relative;
      display: inline-block;
      cursor: pointer;
    }

    .dropdown-toggle {
      display: flex;
      align-items: center;
      gap: 5px;
      color: #e8f5e9;
      text-decoration: none;
    }

    .dropdown-toggle:after {
      content: '▼';
      font-size: 10px;
    }

    .dropdown-menu {
      display: none;
      position: absolute;
      top: 100%;
      left: 0;
      background: #1b5e20;
      min-width: 160px;
      box-shadow: 0 4px 8px rgba(0,0,0,0.3);
      border-radius: 6px;
      margin-top: 5px;
      z-index: 1001;
    }

    .header-bottom .right {
      position: absolute;
      right: 25px;
    }



    .header-bottom .right a {
      text-decoration: none;
      color: #f9fbe7;
      font-weight: 600;
      padding: 6px 12px;
      border: 1px solid #f9fbe7;
      border-radius: 6px;
      transition: all 0.2s ease;
      display: inline-block;
    }

    .header-bottom .right a:hover {
      background: #fdd835;
      color: #2e7d32;
      border-color: #fdd835;
    }

    /*  Hamburger Menu Button */
.hamburger {
    display: none;
    flex-direction: column;
    gap: 5px;
    cursor: pointer;
    padding: 10px;
    position: absolute;
    left: 15px;
    z-index: 1002;
  }

  .hamburger span {
    width: 28px;
    height: 3px;
    background: white;
    border-radius: 2px;
    transition: all 0.3s ease;
  }

  .hamburger.active span:nth-child(1) {
    transform: rotate(45deg) translate(8px, 8px);
  }

  .hamburger.active span:nth-child(2) {
    opacity: 0;
  }

  .hamburger.active span:nth-child(3) {
    transform: rotate(-45deg) translate(7px, -7px);
  }

  /* ✅ Sidebar Navigation (Slide from left) */
  .sidebar-nav {
    display: none;
    position: fixed;
    top: 0;
    left: -280px;
    width: 280px;
    height: 100vh;
    background: #2e7d32;
    box-shadow: 2px 0 10px rgba(0,0,0,0.3);
    z-index: 1001;
    transition: left 0.3s ease;
    overflow-y: auto;
    padding-top: 70px;
  }

  .sidebar-nav.active {
    left: 0;
  }

  .sidebar-nav a {
    display: block;
    text-decoration: none;
    color: #e8f5e9;
    font-size: 16px;
    font-weight: 500;
    padding: 15px 20px;
    transition: background 0.2s ease;
    border-left: 4px solid transparent;
  }

  .sidebar-nav a:hover {
    background: #388e3c;
    border-left-color: #a5d6a7;
  }

  .sidebar-nav a.active {
    background: #388e3c;
    border-left-color: #a5d6a7;
    color: #fff;
  }

  .sidebar-nav a i {
    margin-right: 10px;
    width: 20px;
    text-align: center;
  }

  .sidebar-nav .logout-link {
    border-top: 1px solid rgba(255,255,255,0.2);
    margin-top: 20px;
    background: rgba(0,0,0,0.1);
  }

  /* ✅ Overlay for sidebar */
  .sidebar-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 999;
    opacity: 0;
    transition: opacity 0.3s ease;
  }

  .sidebar-overlay.active {
    display: block;
    opacity: 1;
  }

  .main-content {
    margin-top: 140px;
    height: calc(100vh - 140px);
    padding: 20px;
    overflow-y: auto;
  }

  /* ✅ Tablet View (768px - 1024px) */
  @media (max-width: 480px) {
    .header-top {
      display: grid;
      grid-template-columns: 60px 1fr 80px;
      align-items: center;
      padding: 20px 15px;
      min-height: 100px;
    }

    .header-top img {
      width: 70px;
      left: 25px;
      top: 50%;
      transform: translateY(-50%);
    }

    .header-top h1 {
      font-size: 20px;
      margin: 0 120px;
    }

    .header-bottom nav {
      gap: 10px;
    }

    .header-bottom nav a {
      font-size: 14px;
      padding: 7px 10px;
    }
  }

  /* ✅ Mobile View (Below 768px) */
  @media (max-width: 768px) {
    .header-top {
      display: grid;
      grid-template-columns: 60px 1fr 80px;
      align-items: center;
      padding: 20px 15px;
      min-height: 100px;
      gap: 10px;
    }

    .header-top img {
      width: 70px;
      position: static;
      transform: none;
      grid-column: 3;
      justify-self: end;
    }

    .header-top h1 {
      font-size: 17px;
      margin: 0;
      padding: 0;
      text-align: center;
      line-height: 1.4;
      grid-column: 2;
    }

    /* ✅ Hide desktop navigation */
    .header-bottom {
      display: none;
    }

    /* ✅ Show hamburger menu on LEFT */
    .hamburger {
      display: flex;
      position: static;
      grid-column: 1;
    }

    /* ✅ Show sidebar navigation */
    .sidebar-nav {
      display: block;
    }

    .main-content {
      margin-top: 100px;
      height: calc(100vh - 100px);
      padding: 15px;
    }
  }

  /* ✅ Small Mobile View (Below 480px) */
  @media (max-width: 480px) {
    .header-top {
      grid-template-columns: 50px 1fr 70px;
      padding: 18px 12px;
      min-height: 95px;
      gap: 8px;
    }

    .header-top img {
      width: 65px;
    }

    .header-top h1 {
      font-size: 15px;
      line-height: 1.3;
    }

    .hamburger {
      padding: 8px;
    }

    .hamburger span {
      width: 25px;
      height: 2.5px;
    }

    .sidebar-nav {
      width: 250px;
      left: -250px;
    }

    .sidebar-nav a {
      font-size: 15px;
      padding: 12px 18px;
    }

    .main-content {
      margin-top: 95px;
      height: calc(100vh - 95px);
      padding: 10px;
    }
  }

  /* ✅ Extra Small Mobile (Below 375px) */
  @media (max-width: 375px) {
    .header-top {
      grid-template-columns: 45px 1fr 60px;
      padding: 15px 10px;
      min-height: 85px;
      gap: 6px;
    }

    .header-top img {
      width: 55px;
    }

    .header-top h1 {
      font-size: 13px;
      line-height: 1.2;
    }

    .hamburger {
      padding: 6px;
    }

    .main-content {
      margin-top: 85px;
      height: calc(100vh - 85px);
    }
  }
  </style>
</head>
<body>
<!-- Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<header class="header">
  <div class="header-top">
    <!-- Hamburger Menu (Mobile - LEFT side) -->
    <div class="hamburger" id="hamburger" onclick="toggleSidebar()">
      <span></span>
      <span></span>
      <span></span>
    </div>

    <img src="../images/123.png" alt="DA Logo">
    <h1>Agricultural Machineries Reservation & Monitoring System</h1>
  </div>

  <div class="header-bottom">

    <nav>
    
      <a href="<?= $base_url ?>operator_my_reservations.php" class="<?= $current_page === 'operator_my_reservations.php' ? 'active' : '' ?>">My Reservation</a>
      <a href="<?= $base_url ?>operator_machines.php" class="<?= $current_page === 'operator_machines.php' ? 'active' : '' ?>">Machines</a>
      <a href="<?= $base_url ?>operator_aboutus.php" class="<?= $current_page === 'operator_aboutus.php' ? 'active' : '' ?>">About Us</a>
      <a href="<?= $base_url ?>operator_contactus.php" class="<?= $current_page === 'operator_contactus.php' ? 'active' : '' ?>">Contact Us</a>
      <a href="<?= $base_url ?>my_account.php" class="<?= $current_page === 'my_account.php' ? 'active' : '' ?>">My Account</a>

    </nav>
    
  </div>
</header>

<!-- Sidebar Navigation (Mobile - Slides from left) -->
<nav class="sidebar-nav" id="sidebarNav">
  <a href="<?= $base_url ?>operator_my_reservations.php" class="<?= $current_page === 'operator_my_reservations.php' ? 'active' : '' ?>">
    <i class="fas fa-users"></i> My Reservations
  </a>
  <a href="<?= $base_url ?>association_reservation.php" class="<?= $current_page === 'association_reservation.php' ? 'active' : '' ?>">
    <i class="fas fa-calendar-check"></i> Reservation Records
  </a>
  <a href="<?= $base_url ?>operator_machines.php" class="<?= $current_page === 'operator_machines.php' ? 'active' : '' ?>">
    <i class="fas fa-tractor"></i> Machines
  </a>
  <a href="<?= $base_url ?>payment_management.php" class="<?= $current_page === 'payment_management.php' ? 'active' : '' ?>">
    <i class="fas fa-money-bill-wave"></i> Payment Management
  </a>
  <a href="<?= $base_url ?>association_aboutus.php" class="<?= $current_page === 'association_aboutus.php' ? 'active' : '' ?>">
    <i class="fas fa-info-circle"></i> About Us
  </a>
  <a href="<?= $base_url ?>association_contactus.php" class="<?= $current_page === 'association_contactus.php' ? 'active' : '' ?>">
    <i class="fas fa-envelope"></i> Contact Us
  </a>
  <a href="<?= $base_url ?>admin_users.php" class="<?= $current_page === 'admin_users.php' ? 'active' : '' ?>">
    <i class="fas fa-user-cog"></i> Account Setting
  </a>
  
</nav>

<script>
function toggleSidebar() {
  const sidebar = document.getElementById('sidebarNav');
  const overlay = document.getElementById('sidebarOverlay');
  const hamburger = document.getElementById('hamburger');
  
  sidebar.classList.toggle('active');
  overlay.classList.toggle('active');
  hamburger.classList.toggle('active');
}

function closeSidebar() {
  const sidebar = document.getElementById('sidebarNav');
  const overlay = document.getElementById('sidebarOverlay');
  const hamburger = document.getElementById('hamburger');
  
  sidebar.classList.remove('active');
  overlay.classList.remove('active');
  hamburger.classList.remove('active');
}
</script>

</body>
</html>
