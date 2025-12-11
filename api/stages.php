<?php
/**
 * Stages API Endpoint
 */

require_once '../config/database.php';
require_once 'response.php';

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $dept_id = isset($_GET['dept_id']) ? (int)$_GET['dept_id'] : null;
        
        $sql = "
            SELECT s.*, d.dept_name
            FROM stages s
            JOIN departments d ON s.dept_id = d.dept_id
        ";
        
        if ($dept_id) {
            $sql .= " WHERE s.dept_id = ?";
            $sql .= " ORDER BY s.stage_number, s.is_evening, s.stage_name";
            $stmt = $db->prepare($sql);
            if (!$stmt) {
                $conn = $db->getConnection();
                sendError('Database prepare error: ' . $conn->error);
            }
            $stmt->bind_param("i", $dept_id);
            if (!$stmt->execute()) {
                sendError('Database execute error: ' . $stmt->error);
            }
            $result = $stmt->get_result();
        } else {
            $sql .= " ORDER BY d.dept_name, s.stage_number, s.is_evening, s.stage_name";
            $result = $db->query($sql);
            if (!$result) {
                $conn = $db->getConnection();
                sendError('Database query error: ' . $conn->error);
            }
        }
        
        $stages = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $stages[] = $row;
            }
        }
        sendSuccess('Stages retrieved', $stages);
        break;
        
    default:
        sendError('Method not allowed', 405);
        break;
}

