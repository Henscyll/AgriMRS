<?php
// test_association_bookings.php
session_start();
require_once '../includes/config.php';

echo "<h2>Association Bookings Debug</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; background: white; }
    th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
    th { background-color: #2e7d32; color: white; }
    tr:nth-child(even) { background-color: #f2f2f2; }
    .section { margin: 30px 0; padding: 20px; background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .info { background: #e3f2fd; padding: 10px; border-left: 4px solid #2196f3; margin: 10px 0; }
</style>";

// Check if logged in as association
echo "<div class='section'>";
echo "<h3>1. Session Check</h3>";
if (isset($_SESSION['user_id']) && $_SESSION['user_role'] === 'associations') {
    echo "<p class='success'>✓ Logged in as association</p>";
    echo "<p>User ID: " . $_SESSION['user_id'] . "</p>";
    echo "<p>User Role: " . $_SESSION['user_role'] . "</p>";
    $user_id = $_SESSION['user_id'];
} else {
    echo "<p class='error'>✗ Not logged in as association</p>";
    echo "<p>Current role: " . ($_SESSION['user_role'] ?? 'NOT SET') . "</p>";
    die("Please login as an association user to continue.");
}
echo "</div>";

// Get association ID
echo "<div class='section'>";
echo "<h3>2. Association Information</h3>";
$stmt = $conn->prepare("SELECT * FROM associations WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$assoc_result = $stmt->get_result();

if ($assoc_result->num_rows > 0) {
    $association = $assoc_result->fetch_assoc();
    $association_id = $association['id'];
    echo "<p class='success'>✓ Association found</p>";
    echo "<table>";
    echo "<tr><th>Field</th><th>Value</th></tr>";
    foreach ($association as $key => $value) {
        echo "<tr><td>$key</td><td>" . htmlspecialchars($value ?? 'NULL') . "</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p class='error'>✗ No association found for this user_id</p>";
    die("Association not linked. Please contact admin.");
}
echo "</div>";

// Check machines for this association
echo "<div class='section'>";
echo "<h3>3. Machines Owned by This Association</h3>";
$machinesQuery = $conn->prepare("SELECT * FROM machines WHERE association_id = ?");
$machinesQuery->bind_param("i", $association_id);
$machinesQuery->execute();
$machines = $machinesQuery->get_result();

if ($machines->num_rows > 0) {
    echo "<p class='success'>✓ Found " . $machines->num_rows . " machine(s)</p>";
    echo "<table>";
    echo "<tr><th>ID</th><th>Name</th><th>Type</th><th>Status</th><th>Created</th></tr>";
    while ($m = $machines->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$m['id']}</td>";
        echo "<td>{$m['machine_name']}</td>";
        echo "<td>{$m['type']}</td>";
        echo "<td>{$m['status']}</td>";
        echo "<td>{$m['created_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p class='error'>✗ No machines found for this association</p>";
    echo "<p class='info'>This association needs to add machines before receiving bookings.</p>";
}
echo "</div>";

// Check all bookings for this association
echo "<div class='section'>";
echo "<h3>4. All Bookings for This Association's Machines</h3>";
$bookingsQuery = $conn->prepare("
    SELECT 
        b.*,
        m.machine_name,
        m.type as machine_type,
        u.name as farmer_name,
        u.email as farmer_email
    FROM bookings b
    JOIN machines m ON b.machine_id = m.id
    JOIN users u ON b.farmer_id = u.id
    WHERE m.association_id = ?
    ORDER BY b.created_at DESC
");
$bookingsQuery->bind_param("i", $association_id);
$bookingsQuery->execute();
$bookings = $bookingsQuery->get_result();

if ($bookings->num_rows > 0) {
    echo "<p class='success'>✓ Found " . $bookings->num_rows . " booking(s)</p>";
    echo "<table>";
    echo "<tr><th>ID</th><th>Farmer</th><th>Machine</th><th>Date</th><th>Location</th><th>Size (ha)</th><th>Status</th><th>Created</th></tr>";
    while ($b = $bookings->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$b['id']}</td>";
        echo "<td>{$b['farmer_name']}<br><small>{$b['farmer_email']}</small></td>";
        echo "<td>{$b['machine_name']}<br><small>{$b['machine_type']}</small></td>";
        echo "<td>{$b['booking_date']}</td>";
        echo "<td>" . ($b['farm_location'] ?? 'N/A') . "</td>";
        echo "<td>" . ($b['farm_size'] ?? 'N/A') . "</td>";
        echo "<td><strong>{$b['status']}</strong></td>";
        echo "<td>" . date('M d, Y', strtotime($b['created_at'])) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p class='error'>✗ No bookings found</p>";
    echo "<p class='info'>Possible reasons:</p>";
    echo "<ul>";
    echo "<li>No farmers have booked your machines yet</li>";
    echo "<li>Association ID mismatch in machines table</li>";
    echo "<li>Bookings table is empty</li>";
    echo "</ul>";
}
echo "</div>";

// Check all farmers in the system
echo "<div class='section'>";
echo "<h3>5. All Farmers in System (for reference)</h3>";
$farmersQuery = $conn->query("SELECT id, name, email FROM users WHERE user_role = 'farmer'");
if ($farmersQuery->num_rows > 0) {
    echo "<p class='success'>✓ Found " . $farmersQuery->num_rows . " farmer(s)</p>";
    echo "<table>";
    echo "<tr><th>ID</th><th>Name</th><th>Email</th></tr>";
    while ($f = $farmersQuery->fetch_assoc()) {
        echo "<tr><td>{$f['id']}</td><td>{$f['name']}</td><td>{$f['email']}</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p class='error'>✗ No farmers found in system</p>";
}
echo "</div>";

// Check booking counts by status
echo "<div class='section'>";
echo "<h3>6. Booking Summary</h3>";
$countQuery = $conn->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN b.status = 'Pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN b.status = 'Approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN b.status = 'Completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN b.status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled
    FROM bookings b
    JOIN machines m ON b.machine_id = m.id
    WHERE m.association_id = ?
");
$countQuery->bind_param("i", $association_id);
$countQuery->execute();
$counts = $countQuery->get_result()->fetch_assoc();

echo "<table>";
echo "<tr><th>Status</th><th>Count</th></tr>";
echo "<tr><td>Total</td><td><strong>" . ($counts['total'] ?? 0) . "</strong></td></tr>";
echo "<tr><td>Pending</td><td>" . ($counts['pending'] ?? 0) . "</td></tr>";
echo "<tr><td>Approved</td><td>" . ($counts['approved'] ?? 0) . "</td></tr>";
echo "<tr><td>Completed</td><td>" . ($counts['completed'] ?? 0) . "</td></tr>";
echo "<tr><td>Cancelled</td><td>" . ($counts['cancelled'] ?? 0) . "</td></tr>";
echo "</table>";
echo "</div>";

// Test the exact query from reservation_president.php
echo "<div class='section'>";
echo "<h3>7. Testing Exact Query from reservation_president.php</h3>";
$testQuery = "
    SELECT 
        b.id,
        b.booking_date,
        b.farm_location,
        b.farm_size,
        b.notes,
        b.status,
        b.created_at,
        u.name AS farmer_name,
        u.email AS farmer_email,
        m.machine_name,
        m.type AS machine_type
    FROM bookings b
    JOIN machines m ON b.machine_id = m.id
    JOIN users u ON b.farmer_id = u.id
    WHERE m.association_id = $association_id
    ORDER BY 
        CASE 
            WHEN b.status = 'Pending' THEN 1
            WHEN b.status = 'Approved' THEN 2
            WHEN b.status = 'Completed' THEN 3
            ELSE 4
        END,
        b.booking_date DESC
";

$testResult = $conn->query($testQuery);
if ($testResult) {
    echo "<p class='success'>Query executed successfully! Found {$testResult->num_rows} result(s)</p>";
    if ($testResult->num_rows > 0) {
        echo "<table>";
        echo "<tr><th>ID</th><th>Farmer</th><th>Machine</th><th>Date</th><th>Status</th></tr>";
        while ($row = $testResult->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$row['id']}</td>";
            echo "<td>{$row['farmer_name']}</td>";
            echo "<td>{$row['machine_name']}</td>";
            echo "<td>{$row['booking_date']}</td>";
            echo "<td>{$row['status']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} else {
    echo "<p class='error'>Query failed: " . $conn->error . "</p>";
}
echo "</div>";

echo "<div class='section'>";
echo "<h3>8. Troubleshooting Checklist</h3>";
echo "<ul>";
echo "<li>✓ Check that your association has machines added</li>";
echo "<li>✓ Check that farmers are booking machines that belong to your association</li>";
echo "<li>✓ Check that the association_id in machines table matches your association ID</li>";
echo "<li>✓ Check that bookings.machine_id references valid machines</li>";
echo "<li>✓ Check that bookings.farmer_id references valid users with role='farmer'</li>";
echo "</ul>";
echo "</div>";

$conn->close();
?>