<?php
/**
 * Observer History API Endpoint
 */

require_once '../config/database.php';
require_once 'response.php';

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $exam_date = isset($_GET['date']) ? $db->escape($_GET['date']) : null;
        $shift_id = isset($_GET['shift_id']) ? (int)$_GET['shift_id'] : null;
        
        $sql = "
            SELECT oh.*, 
                   t.teacher_name,
                   es.shift_number,
                   es.shift_time,
                   s.section_number
            FROM observer_history oh
            JOIN teachers t ON oh.teacher_id = t.teacher_id
            JOIN exam_shifts es ON oh.shift_id = es.shift_id
            JOIN sections s ON oh.section_id = s.section_id
            WHERE 1=1
        ";
        
        $params = [];
        $types = "";
        
        if ($exam_date) {
            $sql .= " AND oh.exam_date = ?";
            $params[] = $exam_date;
            $types .= "s";
        }
        
        if ($shift_id) {
            $sql .= " AND oh.shift_id = ?";
            $params[] = $shift_id;
            $types .= "i";
        }
        
        $sql .= " ORDER BY oh.created_at DESC, es.shift_number, s.section_number";
        
        if (!empty($params)) {
            $stmt = $db->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $db->query($sql);
        }
        
        $history = [];
        while ($row = $result->fetch_assoc()) {
            $history[] = $row;
        }
        
        sendSuccess('History retrieved', $history);
        break;
        
    default:
        sendError('Method not allowed', 405);
        break;
}

