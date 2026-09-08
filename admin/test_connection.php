<?php
// Test database connection and file paths
echo "<h2>Connection Test</h2>";

// Test 1: Check if config.php exists and can be included
echo "<h3>Test 1: Including config.php</h3>";
$config_path = '../includes/config.php';
if (file_exists($config_path)) {
    echo "✓ Config file exists at: " . realpath($config_path) . "<br>";
    require_once $config_path;
    echo "✓ Config file loaded successfully<br>";
} else {
    echo "✗ Config file NOT found at: " . $config_path . "<br>";
    echo "Current directory: " . __DIR__ . "<br>";
    die();
}

// Test 2: Check database connection
echo "<h3>Test 2: Database Connection</h3>";
if ($conn->connect_error) {
    echo "✗ Connection failed: " . $conn->connect_error . "<br>";
    die();
} else {
    echo "✓ Database connected successfully<br>";
    echo "✓ Database name: agri_machinery<br>";
}

// Test 3: Query associations for labangan
echo "<h3>Test 3: Query Associations</h3>";
$municipality = 'labangan';
$query = "SELECT id, name, municipality FROM associations WHERE LOWER(TRIM(municipality)) = LOWER(TRIM(?))";
echo "Query: " . $query . "<br>";
echo "Parameter: " . $municipality . "<br><br>";

$stmt = $conn->prepare($query);
if (!$stmt) {
    echo "✗ Prepare failed: " . $conn->error . "<br>";
    die();
}

$stmt->bind_param("s", $municipality);
$stmt->execute();
$result = $stmt->get_result();

$associations = [];
while ($row = $result->fetch_assoc()) {
    $associations[] = $row;
}

if (count($associations) > 0) {
    echo "✓ Found " . count($associations) . " association(s):<br>";
    echo "<pre>";
    print_r($associations);
    echo "</pre>";
} else {
    echo "✗ No associations found for municipality: " . $municipality . "<br>";
}

// Test 4: Check if get_associations.php exists
echo "<h3>Test 4: Check PHP Files</h3>";
$files = ['get_associations.php', 'get_machines.php', 'get_reservations.php'];
foreach ($files as $file) {
    if (file_exists($file)) {
        echo "✓ " . $file . " exists at: " . realpath($file) . "<br>";
    } else {
        echo "✗ " . $file . " NOT found<br>";
    }
}

// Test 5: Test JSON output
echo "<h3>Test 5: JSON Output Test</h3>";
$test_data = [
    'success' => true,
    'associations' => $associations,
    'count' => count($associations)
];
echo "JSON output:<br>";
echo "<pre>" . json_encode($test_data, JSON_PRETTY_PRINT) . "</pre>";

$conn->close();
?>