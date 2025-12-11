<?php
/**
 * Exam Shifts API Endpoint
 */

require_once '../config/database.php';
require_once 'response.php';

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $result = $db->query("SELECT * FROM exam_shifts ORDER BY shift_number");
        $shifts = [];
        while ($row = $result->fetch_assoc()) {
            $shifts[] = $row;
        }
        sendSuccess('Shifts retrieved', $shifts);
        break;
        
    default:
        sendError('Method not allowed', 405);
        break;
}

