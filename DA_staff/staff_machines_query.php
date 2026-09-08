<?php
$associations = $conn->query("SELECT id, name, province, municipality, barangay, phone FROM associations ORDER BY name");

$search_field = $_GET['search_field'] ?? 'All';
$search_term = $_GET['search_term'] ?? '';
$type_filter = $_GET['typeDropdown'] ?? '';

$where = [];

if (!empty($search_term)) {
    if ($search_field === 'name') {
        $where[] = "m.machine_name LIKE '%" . $conn->real_escape_string($search_term) . "%'";
    } elseif ($search_field === 'type') {
        $where[] = "m.type LIKE '%" . $conn->real_escape_string($search_term) . "%'";
    } elseif ($search_field === 'association') {
        $where[] = "a.name LIKE '%" . $conn->real_escape_string($search_term) . "%'";
    } elseif ($search_field === 'municipality') {
        $where[] = "a.municipality LIKE '%" . $conn->real_escape_string($search_term) . "%'";
    } elseif ($search_field === 'Status') {
        $where[] = "m.status LIKE '%" . $conn->real_escape_string($search_term) . "%'";
    } else { // All
        $where[] = "(m.machine_name LIKE '%" . $conn->real_escape_string($search_term) . "%' 
                    OR m.type LIKE '%" . $conn->real_escape_string($search_term) . "%'
                    OR a.name LIKE '%" . $conn->real_escape_string($search_term) . "%'
                    OR a.municipality LIKE '%" . $conn->real_escape_string($search_term) . "%'
                    OR m.status LIKE '%" . $conn->real_escape_string($search_term) . "%')";
    }
}

if (!empty($type_filter)) {
    $where[] = "m.type='" . $conn->real_escape_string($type_filter) . "'";
}

$where_sql = '';
if (!empty($where)) {
    $where_sql = "WHERE " . implode(" AND ", $where);
}

$sql = "SELECT m.*, 
               a.name AS association_name, 
               a.province, 
               a.municipality, 
               a.barangay,
               a.phone
        FROM machines m 
        LEFT JOIN associations a ON m.association_id = a.id
        $where_sql
        ORDER BY m.created_at DESC";

$machines = $conn->query($sql);
?>