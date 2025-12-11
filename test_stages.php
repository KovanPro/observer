<?php
/**
 * Test Stages API
 * Run this file directly to test if stages are loading correctly
 */

require_once 'config/database.php';
require_once 'api/response.php';

$db = Database::getInstance();

echo "<h2>Testing Stages API</h2>";

// Test 1: Check if stages table exists
echo "<h3>Test 1: Check stages table</h3>";
$result = $db->query("SHOW TABLES LIKE 'stages'");
if ($result && $result->num_rows > 0) {
    echo "✓ Stages table exists<br>";
} else {
    echo "✗ Stages table does NOT exist<br>";
    echo "Please import database/schema.sql<br>";
    exit;
}

// Test 2: Count stages
echo "<h3>Test 2: Count stages</h3>";
$result = $db->query("SELECT COUNT(*) as count FROM stages");
if ($result) {
    $row = $result->fetch_assoc();
    echo "Total stages in database: " . $row['count'] . "<br>";
} else {
    echo "✗ Error counting stages: " . $db->getConnection()->error . "<br>";
}

// Test 3: Get all stages
echo "<h3>Test 3: Get all stages</h3>";
$result = $db->query("
    SELECT s.*, d.dept_name
    FROM stages s
    JOIN departments d ON s.dept_id = d.dept_id
    ORDER BY d.dept_name, s.stage_number, s.is_evening, s.stage_name
");

if ($result) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Stage ID</th><th>Department</th><th>Stage Name</th><th>Stage Number</th><th>Evening</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['stage_id'] . "</td>";
        echo "<td>" . $row['dept_name'] . "</td>";
        echo "<td>" . $row['stage_name'] . "</td>";
        echo "<td>" . $row['stage_number'] . "</td>";
        echo "<td>" . ($row['is_evening'] ? 'Yes' : 'No') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "✗ Error fetching stages: " . $db->getConnection()->error . "<br>";
}

// Test 4: Test API endpoint
echo "<h3>Test 4: Test API endpoint</h3>";
echo "<a href='api/stages.php' target='_blank'>Test: api/stages.php</a><br>";
echo "<a href='api/stages.php?dept_id=1' target='_blank'>Test: api/stages.php?dept_id=1</a><br>";

// Test 5: Check departments
echo "<h3>Test 5: Check departments</h3>";
$result = $db->query("SELECT * FROM departments ORDER BY dept_name");
if ($result) {
    echo "<ul>";
    while ($row = $result->fetch_assoc()) {
        echo "<li>ID: " . $row['dept_id'] . " - " . $row['dept_name'] . "</li>";
    }
    echo "</ul>";
} else {
    echo "✗ Error fetching departments: " . $db->getConnection()->error . "<br>";
}

?>

