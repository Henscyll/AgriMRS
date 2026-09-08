<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'farmer') {
    header("Location: ../login.php");
    exit;
}
$current_page = basename($_SERVER['PHP_SELF']);
$user_role    = $_SESSION['user_role'] ?? '';
$base_url     = '/agri_system/farmers/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Farmer Dashboard</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
  * { margin:0; padding:0; box-sizing:border-box; }

  body {
    overflow: hidden;
    font-family: "Segoe UI", Arial, sans-serif;

    background-size: cover;
    background-position: center;
    background-attachment: fixed;
  }

  /* ── Header ── */
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

  /* Top bar */
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
    position: absolute; left: 220px; top: 70%; transform: translateY(-50%);
  }
  .header-top h1 {
    font-size: 24px; font-weight: bold;
    text-align: center; color: #fff;
    margin: 0 160px;
  }

  /* Bottom nav bar */
  .header-bottom {
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 8px 25px;
    position: relative;
  }
  .header-bottom nav {
    display: flex; gap: 6px; flex-wrap: wrap; justify-content: center;
  }
  .header-bottom nav a {
    text-decoration: none; color: #e8f5e9;
    font-size: 15px; font-weight: 500;
    padding: 7px 12px; border-radius: 6px;
    transition: background .2s, color .2s;
    white-space: nowrap;
  }
  .header-bottom nav a:hover { background: #388e3c; color: #fff; }
  .header-bottom nav a.active { background: #a5d6a7; color: #1b5e20; }

  /* Desktop Services dropdown */
  .desktop-dropdown {
    position: relative; display: inline-block;
  }
  .desktop-dropdown-toggle {
    text-decoration: none; color: #e8f5e9;
    font-size: 15px; font-weight: 500;
    padding: 7px 12px; border-radius: 6px;
    transition: background .2s, color .2s;
    cursor: pointer; display: flex; align-items: center; gap: 5px;
    white-space: nowrap;
  }
  .desktop-dropdown-toggle::after { content: '▼'; font-size: 10px; }
  .desktop-dropdown-toggle:hover,
  .desktop-dropdown-toggle.active-parent { background: #388e3c; color: #fff; }
  .desktop-dropdown-toggle.active-parent { background: #a5d6a7; color: #1b5e20; }

  .desktop-dropdown-menu {
    display: none; position: absolute;
    top: calc(100% + 4px); left: 0;
    background: #1b5e20; min-width: 150px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.3);
    border-radius: 6px; z-index: 1010;
  }
  .desktop-dropdown:hover .desktop-dropdown-menu { display: block; }
  .desktop-dropdown-menu a {
    display: block; padding: 10px 15px;
    color: #e8f5e9; text-decoration: none; font-size: 14px;
  }
  .desktop-dropdown-menu a:first-child { border-radius: 6px 6px 0 0; }
  .desktop-dropdown-menu a:last-child  { border-radius: 0 0 6px 6px; }
  .desktop-dropdown-menu a:hover { background: #388e3c; color: #fff; }
  .desktop-dropdown-menu a.active { background: #a5d6a7; color: #1b5e20; }

  /* Logout button */
  .header-bottom .right {
    position: absolute; right: 25px;
  }
  .header-bottom .right a {
    text-decoration: none; color: #f9fbe7; font-weight: 600;
    padding: 6px 12px; border: 1px solid #f9fbe7; border-radius: 6px;
    transition: all .2s; display: inline-block;
  }
  .header-bottom .right a:hover {
    background: #fdd835; color: #2e7d32; border-color: #fdd835;
  }

  /* ── Hamburger (mobile only) ── */
  .hamburger {
    display: none; flex-direction: column; gap: 5px;
    cursor: pointer; padding: 10px;
    position: absolute; left: 15px;
    z-index: 1002;
  }
  .hamburger span {
    width: 28px; height: 3px; background: white;
    border-radius: 2px; transition: all .3s;
  }
  .hamburger.active span:nth-child(1) { transform: rotate(45deg) translate(8px,8px); }
  .hamburger.active span:nth-child(2) { opacity: 0; }
  .hamburger.active span:nth-child(3) { transform: rotate(-45deg) translate(7px,-7px); }

  /* ── Sidebar (mobile) ── */
  .sidebar-nav {
    display: none;
    position: fixed; top: 0; left: -280px;
    width: 280px; height: 100vh;
    background: #2e7d32;
    box-shadow: 2px 0 10px rgba(0,0,0,0.3);
    z-index: 1001; transition: left .3s;
    overflow-y: auto; padding-top: 70px;
  }
  .sidebar-nav.active { left: 0; }

  .sidebar-nav > a {
    display: block; text-decoration: none;
    color: #e8f5e9; font-size: 16px; font-weight: 500;
    padding: 14px 20px; border-left: 4px solid transparent;
    transition: background .2s;
  }
  .sidebar-nav > a:hover { background: #388e3c; border-left-color: #a5d6a7; }
  .sidebar-nav > a.active { background: #388e3c; border-left-color: #a5d6a7; color: #fff; }
  .sidebar-nav > a i { margin-right: 10px; width: 20px; text-align: center; }

  /* Sidebar Services accordion */
  .sidebar-accordion { border-left: 4px solid transparent; }
  .sidebar-acc-toggle {
    display: flex; justify-content: space-between; align-items: center;
    padding: 14px 20px; cursor: pointer;
    color: #e8f5e9; font-size: 16px; font-weight: 500;
    transition: background .2s;
    user-select: none;
  }
  .sidebar-acc-toggle:hover { background: #388e3c; }
  .sidebar-acc-toggle.open { background: #388e3c; }
  .sidebar-acc-toggle .acc-left { display: flex; align-items: center; gap: 10px; }
  .sidebar-acc-toggle .acc-arrow { font-size: 12px; transition: transform .25s; }
  .sidebar-acc-toggle.open .acc-arrow { transform: rotate(180deg); }

  .sidebar-acc-body { display: none; background: #1b5e20; }
  .sidebar-acc-body.open { display: block; }
  .sidebar-acc-body a {
    display: block; text-decoration: none;
    color: #e8f5e9; font-size: 15px;
    padding: 12px 20px 12px 48px;
    transition: background .2s; border-left: 4px solid transparent;
  }
  .sidebar-acc-body a:hover { background: #256725; border-left-color: #a5d6a7; }
  .sidebar-acc-body a.active { background: #256725; border-left-color: #a5d6a7; }

  .sidebar-nav .logout-link {
    border-top: 1px solid rgba(255,255,255,.2);
    margin-top: 20px; background: rgba(0,0,0,.1);
  }

  /* Overlay */
  .sidebar-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,.5); z-index: 999;
    opacity: 0; transition: opacity .3s;
  }
  .sidebar-overlay.active { display: block; opacity: 1; }

  /* ── Responsive ── */
  @media (max-width: 968px) {
    .header-bottom { display: none; }

    .hamburger { display: flex; position: static; }

    .header-top {
      display: grid;
      grid-template-columns: 50px 1fr 80px;
      align-items: center;
      padding: 16px 14px; gap: 8px;
    }
    .header-top img {
      width: 70px; position: static;
      transform: none; grid-column: 3; justify-self: end;
    }
    .header-top h1 {
      font-size: 16px; margin: 0; padding: 0;
      text-align: center; grid-column: 2;
    }
    .hamburger { grid-column: 1; }

    .sidebar-nav { display: block; }
  }

  @media (max-width: 480px) {
    .header-top { grid-template-columns: 45px 1fr 65px; padding: 14px 10px; }
    .header-top img { width: 60px; }
    .header-top h1 { font-size: 14px; }
    .sidebar-nav { width: 260px; left: -260px; }
  }
  </style>
</head>
<body>

<!-- Sidebar overlay -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<header class="header">
  <!-- Top bar -->
  <div class="header-top">
    <div class="hamburger" id="hamburger" onclick="toggleSidebar()">
      <span></span><span></span><span></span>
    </div>
    <img src="../images/123.png" alt="DA Logo">
    <h1>Agricultural Machineries Reservation &amp; Monitoring System</h1>
  </div>

  <!-- Desktop nav -->
  <div class="header-bottom">
    <nav>
      <!-- Services dropdown -->
      <div class="desktop-dropdown">
        <span class="desktop-dropdown-toggle <?= in_array($current_page, ['tractor.php','harvester.php']) ? 'active-parent' : '' ?>">
           Services
        </span>
        <div class="desktop-dropdown-menu">
          <a href="<?= $base_url ?>tractor.php"   class="<?= $current_page==='tractor.php'   ? 'active':'' ?>"> Tractor</a>
          <a href="<?= $base_url ?>harvester.php" class="<?= $current_page==='harvester.php' ? 'active':'' ?>"> Harvester</a>
        </div>
      </div>

      <a href="<?= $base_url ?>my_reservation.php" class="<?= $current_page==='my_reservation.php' ? 'active':'' ?>"> My Reservation</a>
      <a href="<?= $base_url ?>aboutus.php"         class="<?= $current_page==='aboutus.php'         ? 'active':'' ?>"> About Us</a>
      <a href="<?= $base_url ?>contactus.php"       class="<?= $current_page==='contactus.php'       ? 'active':'' ?>"> Contact Us</a>
      <a href="<?= $base_url ?>my_account.php"      class="<?= $current_page==='my_account.php'      ? 'active':'' ?>"> My Account</a>
    </nav>

    <div class="right">
      <a href="/agri_system/logout.php"> Logout</a>
    </div>
  </div>
</header>

<!-- Sidebar (mobile) -->
<nav class="sidebar-nav" id="sidebarNav">

  <!-- Services accordion -->
  <div class="sidebar-accordion">
    <div class="sidebar-acc-toggle <?= in_array($current_page,['tractor.php','harvester.php']) ? 'open':'' ?>" id="servicesToggle" onclick="toggleServices()">
      <span class="acc-left"> Services</span>
      <span class="acc-arrow"><i class="fas fa-chevron-down"></i></span>
    </div>
    <div class="sidebar-acc-body <?= in_array($current_page,['tractor.php','harvester.php']) ? 'open':'' ?>" id="servicesBody">
      <a href="<?= $base_url ?>tractor.php"   class="<?= $current_page==='tractor.php'   ? 'active':'' ?>"> Tractor</a>
      <a href="<?= $base_url ?>harvester.php" class="<?= $current_page==='harvester.php' ? 'active':'' ?>"> Harvester</a>
    </div>
  </div>

  <a href="<?= $base_url ?>my_reservation.php" class="<?= $current_page==='my_reservation.php' ? 'active':'' ?>"> My Reservation</a>
  <a href="<?= $base_url ?>aboutus.php"         class="<?= $current_page==='aboutus.php'         ? 'active':'' ?>"> About Us</a>
  <a href="<?= $base_url ?>contactus.php"       class="<?= $current_page==='contactus.php'       ? 'active':'' ?>"> Contact Us</a>
  <a href="<?= $base_url ?>my_account.php"      class="<?= $current_page==='my_account.php'      ? 'active':'' ?>"> My Account</a>
  <a href="/agri_system/logout.php" class="logout-link"> Logout</a>
</nav>

<script>
/* ── Sidebar ── */
function toggleSidebar() {
  document.getElementById('sidebarNav').classList.toggle('active');
  document.getElementById('sidebarOverlay').classList.toggle('active');
  document.getElementById('hamburger').classList.toggle('active');
}
function closeSidebar() {
  document.getElementById('sidebarNav').classList.remove('active');
  document.getElementById('sidebarOverlay').classList.remove('active');
  document.getElementById('hamburger').classList.remove('active');
}

/* ── Services accordion ── */
function toggleServices() {
  const toggle = document.getElementById('servicesToggle');
  const body   = document.getElementById('servicesBody');
  toggle.classList.toggle('open');
  body.classList.toggle('open');
}

/* Close sidebar on link click */
document.querySelectorAll('.sidebar-nav a').forEach(function(a) {
  a.addEventListener('click', closeSidebar);
});

/* Handle resize */
window.addEventListener('resize', function() {
  if (window.innerWidth > 968) closeSidebar();
});
</script>

</body>
</html>