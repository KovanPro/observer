<?php
/**
 * Installation Helper Script
 * Run this once to set up the database
 ReySDtCs2Rk6t5
*/ 
require_once 'config/database.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Installation - Exam Observer System</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { color: green; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; margin: 10px 0; }
        .error { color: red; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; margin: 10px 0; }
        .info { color: blue; padding: 10px; background: #d1ecf1; border: 1px solid #bee5eb; margin: 10px 0; }
        pre { background: #f5f5f5; padding: 10px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>Exam Observer System - Installation</h1>";

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Check if database exists
    $result = $conn->query("SHOW DATABASES LIKE 'exam_observer_system'");
    
    if ($result->num_rows > 0) {
        echo "<div class='info'>Database 'exam_observer_system' already exists.</div>";
        
        // Check if tables exist
        $conn->select_db('exam_observer_system');
        $tables = ['departments', 'stages', 'teachers', 'subjects', 'exams', 'observer_assignments'];
        $missing_tables = [];
        
        foreach ($tables as $table) {
            $result = $conn->query("SHOW TABLES LIKE '$table'");
            if ($result->num_rows == 0) {
                $missing_tables[] = $table;
            }
        }
        
        if (empty($missing_tables)) {
            echo "<div class='success'>✓ All tables exist. System is ready to use!</div>";
            echo "<p><a href='index.html'>Go to System</a></p>";
        } else {
            echo "<div class='error'>Missing tables: " . implode(', ', $missing_tables) . "</div>";
            echo "<div class='info'>Please import database/schema.sql to create missing tables.</div>";
        }
    } else {
        echo "<div class='info'>Database does not exist. Please import database/schema.sql first.</div>";
        echo "<div class='info'>You can do this by:</div>";
        echo "<ol>";
        echo "<li>Open phpMyAdmin (http://localhost/phpmyadmin)</li>";
        echo "<li>Click 'Import' tab</li>";
        echo "<li>Select database/schema.sql file</li>";
        echo "<li>Click 'Go'</li>";
        echo "</ol>";
    }
    
    // Test connection
    echo "<div class='success'>✓ Database connection successful!</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>✗ Database connection failed: " . $e->getMessage() . "</div>";
    echo "<div class='info'>Please check config/database.php and ensure MySQL is running.</div>";
}

echo "</body></html>";
?>

