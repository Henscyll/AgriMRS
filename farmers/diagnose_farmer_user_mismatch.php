<?php
// diagnose_farmer_user_mismatch.php
session_start();
require_once '../includes/config.php';

echo "<h1>Farmer/User Mismatch Diagnostic</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; background: white; }
    th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
    th { background-color: #2e7d32; color: white; }
    tr:nth-child(even) { background-color: #f9fafb; }
    .section { margin: 30px 0; padding: 20px; background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .warning { color: orange; font-weight: bold; }
    .info { background: #e3f2fd; padding: 10px; border-left: 4px solid #2196f3; margin: 10px 0; }
</style>";

// 1. Check users table for farmers
echo "<div class='section'>";
echo "<h2>1. Farmers in USERS Table (Correct Location)</h2>";
$usersQuery = $conn->query("SELECT id, name, email, user_role, created_at FROM users WHERE user_role = 'farmer'");
if ($usersQuery && $usersQuery->num_rows > 0) {
    echo "<p class='success'>✓ Found " . $usersQuery->num_rows . " farmer(s) in users table</p>";
    echo "<table>";
    echo "<tr><th>User ID</th><th>Name</th><th>Email</th><th>Role</th><th>Created</th></tr>";
    while ($user = $usersQuery->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$user['id']}</td>";
        echo "<td>{$user['name']}</td>";
        echo "<td>{$user['email']}</td>";
        echo "<td>{$user['user_role']}</td>";
        echo "<td>{$user['created_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p class='error'>✗ No farmers found in users table!</p>";
    echo "<p class='info'>This is the problem! Farmers should be in the users table with user_role='farmer'</p>";
}
echo "</div>";

// 2. Check OLD farmers table
echo "<div class='section'>";
echo "<h2>2. Farmers in OLD FARMERS Table (Legacy)</h2>";
$farmersQuery = $conn->query("SELECT id, name, email, created_at FROM farmers");
if ($farmersQuery && $farmersQuery->num_rows > 0) {
    echo "<p class='warning'>⚠ Found " . $farmersQuery->num_rows . " farmer(s) in OLD farmers table</p>";
    echo "<p class='info'>These farmers are NOT being used by the booking system! They need to be migrated to users table.</p>";
    echo "<table>";
    echo "<tr><th>Farmer ID</th><th>Name</th><th>Email</th><th>Created</th></tr>";
    while ($farmer = $farmersQuery->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$farmer['id']}</td>";
        echo "<td>{$farmer['name']}</td>";
        echo "<td>{$farmer['email']}</td>";
        echo "<td>{$farmer['created_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p class='success'>✓ No legacy farmers found (good!)</p>";
}
echo "</div>";

// 3. Check bookings and their farmer_id references
echo "<div class='section'>";
echo "<h2>3. Bookings and Their Farmer References</h2>";
$bookingsQuery = $conn->query("
    SELECT 
        b.id,
        b.farmer_id,
        b.booking_date,
        b.status,
        u.name as farmer_name,
        u.email as farmer_email,
        m.machine_name
    FROM bookings b
    LEFT JOIN users u ON b.farmer_id = u.id
    LEFT JOIN machines m ON b.machine_id = m.id
    ORDER BY b.created_at DESC
");

if ($bookingsQuery && $bookingsQuery->num_rows > 0) {
    echo "<p class='success'>✓ Found " . $bookingsQuery->num_rows . " booking(s)</p>";
    echo "<table>";
    echo "<tr><th>Booking ID</th><th>Farmer ID</th><th>Farmer Name</th><th>Email</th><th>Machine</th><th>Date</th><th>Status</th></tr>";
    while ($booking = $bookingsQuery->fetch_assoc()) {
        $hasIssue = empty($booking['farmer_name']);
        echo "<tr style='background: " . ($hasIssue ? '#fee2e2' : 'inherit') . "'>";
        echo "<td>{$booking['id']}</td>";
        echo "<td>{$booking['farmer_id']}</td>";
        echo "<td>" . ($booking['farmer_name'] ?? '<span class="error">❌ NOT FOUND</span>') . "</td>";
        echo "<td>" . ($booking['farmer_email'] ?? '<span class="error">Missing</span>') . "</td>";
        echo "<td>" . ($booking['machine_name'] ?? 'N/A') . "</td>";
        echo "<td>{$booking['booking_date']}</td>";
        echo "<td>{$booking['status']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check for orphaned bookings
    $orphanedQuery = $conn->query("
        SELECT b.id, b.farmer_id 
        FROM bookings b
        LEFT JOIN users u ON b.farmer_id = u.id
        WHERE u.id IS NULL
    ");
    
    if ($orphanedQuery && $orphanedQuery->num_rows > 0) {
        echo "<p class='error'>⚠ PROBLEM FOUND: " . $orphanedQuery->num_rows . " booking(s) have invalid farmer_id!</p>";
        echo "<p class='info'>These bookings reference farmer IDs that don't exist in the users table.</p>";
        echo "<table>";
        echo "<tr><th>Booking ID</th><th>Invalid Farmer ID</th></tr>";
        while ($orphan = $orphanedQuery->fetch_assoc()) {
            echo "<tr><td>{$orphan['id']}</td><td>{$orphan['farmer_id']}</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='success'>✓ All bookings have valid farmer references!</p>";
    }
} else {
    echo "<p class='warning'>⚠ No bookings found in system</p>";
}
echo "</div>";

// 4. Check session for current user
echo "<div class='section'>";
echo "<h2>4. Current Session Information</h2>";
if (isset($_SESSION['user_id'])) {
    echo "<table>";
    echo "<tr><th>Session Variable</th><th>Value</th></tr>";
    echo "<tr><td>user_id</td><td>" . ($_SESSION['user_id'] ?? 'NOT SET') . "</td></tr>";
    echo "<tr><td>user_role</td><td>" . ($_SESSION['user_role'] ?? 'NOT SET') . "</td></tr>";
    echo "<tr><td>user_name</td><td>" . ($_SESSION['user_name'] ?? 'NOT SET') . "</td></tr>";
    echo "<tr><td>user_email</td><td>" . ($_SESSION['user_email'] ?? 'NOT SET') . "</td></tr>";
    echo "</table>";
    
    // Verify this user exists
    $userId = $_SESSION['user_id'];
    $userCheck = $conn->query("SELECT * FROM users WHERE id = $userId");
    if ($userCheck && $userCheck->num_rows > 0) {
        $user = $userCheck->fetch_assoc();
        echo "<p class='success'>✓ Session user exists in database</p>";
        echo "<p>Name: {$user['name']}, Email: {$user['email']}, Role: {$user['user_role']}</p>";
    } else {
        echo "<p class='error'>✗ Session user_id ($userId) does NOT exist in users table!</p>";
    }
} else {
    echo "<p class='warning'>⚠ No user logged in (not an error if you're testing as admin)</p>";
}
echo "</div>";

// 5. Migration Check - Compare old farmers vs new users
echo "<div class='section'>";
echo "<h2>5. Migration Status Check</h2>";
$migrationCheck = $conn->query("
    SELECT 
        f.id as farmer_id,
        f.name as farmer_name,
        f.email as farmer_email,
        u.id as user_id,
        u.name as user_name
    FROM farmers f
    LEFT JOIN users u ON f.email = u.email AND u.user_role = 'farmer'
");

if ($migrationCheck && $migrationCheck->num_rows > 0) {
    echo "<p>Checking if old farmers have been migrated to users table...</p>";
    echo "<table>";
    echo "<tr><th>Old Farmer ID</th><th>Name</th><th>Email</th><th>User ID</th><th>Status</th></tr>";
    $needsMigration = 0;
    while ($row = $migrationCheck->fetch_assoc()) {
        $migrated = !empty($row['user_id']);
        if (!$migrated) $needsMigration++;
        echo "<tr>";
        echo "<td>{$row['farmer_id']}</td>";
        echo "<td>{$row['farmer_name']}</td>";
        echo "<td>{$row['farmer_email']}</td>";
        echo "<td>" . ($row['user_id'] ?? '<span class="error">NOT MIGRATED</span>') . "</td>";
        echo "<td>" . ($migrated ? '<span class="success">✓ Migrated</span>' : '<span class="error">✗ Needs Migration</span>') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    if ($needsMigration > 0) {
        echo "<p class='error'>⚠ {$needsMigration} farmer(s) need to be migrated to users table!</p>";
    } else {
        echo "<p class='success'>✓ All farmers have been migrated!</p>";
    }
} else {
    echo "<p class='success'>✓ No old farmers to migrate</p>";
}
echo "</div>";

// 6. Provide Solutions
echo "<div class='section'>";
echo "<h2>6. Recommended Solutions</h2>";

// Check if there are issues
$hasOrphanedBookings = $conn->query("
    SELECT COUNT(*) as count FROM bookings b
    LEFT JOIN users u ON b.farmer_id = u.id
    WHERE u.id IS NULL
")->fetch_assoc()['count'] > 0;

$hasUnmigratedFarmers = $conn->query("
    SELECT COUNT(*) as count FROM farmers f
    LEFT JOIN users u ON f.email = u.email AND u.user_role = 'farmer'
    WHERE u.id IS NULL
")->fetch_assoc()['count'] > 0;

if ($hasOrphanedBookings) {
    echo "<div class='info'>";
    echo "<h3>Problem: Orphaned Bookings</h3>";
    echo "<p>Some bookings have farmer_ids that don't exist in the users table.</p>";
    echo "<p><strong>Solution:</strong> Run the migration SQL provided below to fix this.</p>";
    echo "</div>";
}

if ($hasUnmigratedFarmers) {
    echo "<div class='info'>";
    echo "<h3>Problem: Farmers Not in Users Table</h3>";
    echo "<p>Some farmers exist in the old 'farmers' table but not in 'users' table.</p>";
    echo "<p><strong>Solution:</strong> Run the migration SQL to move them to users table.</p>";
    echo "</div>";
}

if (!$hasOrphanedBookings && !$hasUnmigratedFarmers) {
    echo "<p class='success'>✓ No migration issues found! Your database structure is correct.</p>";
    echo "<p class='info'>If you're still not seeing the correct farmer names in bookings, the issue might be:</p>";
    echo "<ul>";
    echo "<li>The farmer logged in with the old farmers table credentials (should use users table)</li>";
    echo "<li>The booking was created before the farmer was added to users table</li>";
    echo "<li>Session is using wrong user_id</li>";
    echo "</ul>";
}
echo "</div>";

$conn->close();
?>