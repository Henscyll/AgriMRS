<?php
session_start();

if (isset($_SESSION['user_role'])) {
    switch ($_SESSION['user_role']) {
        case 'it admin':
            header("Location: admin/dashboard_itadmin.php");
            exit;
        case 'association president':
            header("Location: dashboard_president.php");
            exit;
        case 'operator':
            header("Location: dashboard_operator.php");
            exit;
        case 'farmer':
            header("Location: farmer/farmers_header.php");
            exit;
    }
} elseif (isset($_SESSION['farmer_logged_in'])) {
    header("Location: farmer/farmers_header.php");
    exit;
}

header("Location: login.php");
exit;
