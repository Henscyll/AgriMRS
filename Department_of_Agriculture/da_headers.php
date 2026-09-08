<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'department of agriculture') {
    header("Location: ../login.php");
    exit;
}

$current_page = basename($_SERVER['PHP_SELF']);
$base_url = '/agri_system/Department_of_Agriculture/';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>DA Dashboard</title>
<style>
  * {
    margin: 0;
    padding: 0;
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

.header {
  background-color: #2e7d32;
  color: white;
  box-shadow: 0 2px 8px rgba(0,0,0,0.3);
  position: fixed; /* keep it on top */
  top: 0;
  left: 0;
  width: 100%;
  z-index: 1000;
}

header-top {
  align-items: center;
  justify-content: center;
  padding: 5px 5px;
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
  line-height: 1.2;
  text-align: center;
  color: #fff;
}
.header-top .subtitle {
  font-size: 16px;
  font-weight: 400;
  text-align: center;
  color: #e8f5e9;
  margin-top: 5px;
}


.header-bottom {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 25px;
  flex-wrap: wrap;
}

.header-bottom .left {
  font-size: 15px;
  font-weight: bold;
  color: #c8e6c9;
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

.main-content {
  margin-top: 150px; /* adjust this to match your header height */
  height: calc(200vh - 150px);
  padding: 20px;
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
    <div class="logo">Department of Agriculture</div>
    <nav>
        <a href="<?= $base_url ?>da_farmers.php" class="<?= $current_page === 'da_farmers.php' ? 'active' : '' ?>">Farmers</a>
        <a href="<?= $base_url ?>da_reservations.php" class="<?= $current_page === 'da_reservations.php' ? 'active' : '' ?>">Reservations</a>
        <a href="<?= $base_url ?>da_farmers.php" class="<?= $current_page === 'da_farmers.php' ? 'active' : '' ?>">Association</a>
        <a href="<?= $base_url ?>da_farmers.php" class="<?= $current_page === 'da_farmers.php' ? 'active' : '' ?>">Machines</a>
        <a href="<?= $base_url ?>da_farmers.php" class="<?= $current_page === 'da_farmers.php' ? 'active' : '' ?>">About Us</a>
        <a href="<?= $base_url ?>da_farmers.php" class="<?= $current_page === 'da_farmers.php' ? 'active' : '' ?>">Contact Us</a>
        <a href="<?= $base_url ?>da_users.php" class="<?= $current_page === 'da_users.php' ? 'active' : '' ?>">Account Settings</a>
    </nav>
    <div class="right">
        <a href="/agri_system/logout.php">Logout</a>
    </div>
</header>
</body>
</html>
