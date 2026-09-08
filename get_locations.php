<?php
header('Content-Type: application/json');
$conn = new mysqli('localhost','root','','agri_machinery');
if ($conn->connect_error) exit(json_encode([]));

if (isset($_GET['province']) && !isset($_GET['municipality'])) {
  $prov = $conn->real_escape_string($_GET['province']);
  $res = $conn->query("SELECT DISTINCT municipality FROM barangays WHERE province='$prov' ORDER BY municipality");
  echo json_encode($res->fetch_all(MYSQLI_ASSOC));
}

if (isset($_GET['municipality'])) {
  $muni = $conn->real_escape_string($_GET['municipality']);
  $res = $conn->query("SELECT barangay FROM barangays WHERE municipality='$muni' ORDER BY barangay");
  echo json_encode($res->fetch_all(MYSQLI_ASSOC));
}
$conn->close();
