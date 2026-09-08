<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_role = $_SESSION['user_role'] ?? '';
$email_address = $_SESSION['user_email'] ?? '';
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Agricultural Machinery Reservation and Monitoring System</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?php echo '/agri_system/styles/header.css'; ?>">
</head>
<body>
  <div class="container-fluid d-none d-lg-block mb-3">
    <div class="container">
        <div class="row">
            <div class="col">
                <div class="d-flex flex-row align-items-center pt-2 header">
                    <div class="mr-2"> 
                                              <img src="/agri_system/images/da5.png" 
                   class="header-logo img-fluid coatOfArms" alt="Coat of Arms">
                    </div>
                    <div class="col-12 col-md-8">
                        <h5>Department of Agriculture</h5>
                        <h4 style="font-weight: bold;">Agricultural Machineries Reservation & Monitoring System</h4>
                        <p>Lenienza, Pagadian City, Z.D.S.</p>
                        <div class="font-italic small">CLSU Compound, Science City of Muñoz, Nueva Ecija</div>
                    </div>
                </div>
            </div> 
        </div>
    </div>
  </div>

  <div class="cover">
    <div class="auth-links">
      <?php if (empty($user_role)): ?>
        <a href="login.php">Login</a> | 
        <a href="register.php">Register</a>
      <?php else: ?>
        <span>Hello, <?= htmlspecialchars($email_address) ?></span> | 
        <a href="../logout.php">Logout</a>
      <?php endif; ?>
    </div>
  </div>

  <nav class="navbar navbar-expand-lg navbar-dark" style="background-color: #10b132;">
    <div class="container-fluid">
      <a class="navbar-brand" href="#"></a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavDropdown" aria-controls="navbarNavDropdown" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse justify-content-center" id="navbarNavDropdown">
        <ul class="navbar-nav">
          
          <?php if ($user_role === 'association'): ?>
            <li class="nav-item px-2"><a class="nav-link <?= $current_page === 'dashboard_president.php' ? 'active' : '' ?>" href="dashboard_president.php">Dashboard</a></li>
            <li class="nav-item px-2"><a class="nav-link <?= $current_page === 'members.php' ? 'active' : '' ?>" href="members.php">Members</a></li>
            <li class="nav-item px-2"><a class="nav-link <?= $current_page === 'association_reservation.php' ? 'active' : '' ?>" href="association_reservation.php">Reservation</a></li>
            <li class="nav-item px-2"><a class="nav-link <?= $current_page === 'association_agriculturalmachine.php' ? 'active' : '' ?>" href="association_agriculturalmachine.php">Agricultural Machineries</a></li>
            <li class="nav-item px-2"><a class="nav-link <?= $current_page === 'association_aboutus.php' ? 'active' : '' ?>" href="association_aboutus.php">About us</a></li>
            <li class="nav-item px-2"><a class="nav-link <?= $current_page === 'association_contactus.php' ? 'active' : '' ?>" href="association_contactus.php">Contact us</a></li>
            <li class="nav-item px-2"><a class="nav-link <?= $current_page === 'association_myaccount.php' ? 'active' : '' ?>" href="association_myaccount.php">My Account</a></li>

          <?php elseif ($user_role === 'operator'): ?>
            <li class="nav-item px-2"><a class="nav-link <?= $current_page === 'assigned_tasks.php' ? 'active' : '' ?>" href="assigned_tasks.php">My Reservation</a></li>
            <li class="nav-item px-2"><a class="nav-link <?= $current_page === 'machinery_logs.php' ? 'active' : '' ?>" href="machinery_logs.php">Agricultural Machineries</a></li>
            <li class="nav-item px-2"><a class="nav-link <?= $current_page === 'operator_aboutus.php' ? 'active' : '' ?>" href="operator_aboutus.php">About us</a></li>
            <li class="nav-item px-2"><a class="nav-link <?= $current_page === 'operator_contactus.php' ? 'active' : '' ?>" href="operator_contactus.php">Contact us</a></li>
            <li class="nav-item px-2"><a class="nav-link <?= $current_page === 'operator_myaccount.php' ? 'active' : '' ?>" href="operator_myaccount.php">My Account</a></li>
          <?php endif; ?>
          
        </ul>
      </div>
    </div>
  </nav>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
