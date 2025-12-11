<?php
/**
 * Departments API Endpoint
 */

require_once '../config/database.php';
require_once 'response.php';

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $result = $db->query("SELECT * FROM departments ORDER BY dept_name");
        $departments = [];
        while ($row = $result->fetch_assoc()) {
            $departments[] = $row;
        }
        sendSuccess('Departments retrieved', $departments);
        break;
        
    default:
        sendError('Method not allowed', 405);
        break;
}

