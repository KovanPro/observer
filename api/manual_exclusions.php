<?php
/**
 * Manual Exclusions API Endpoint
 */

require_once '../config/database.php';
require_once 'response.php';

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $exclusion_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
        $exam_date = isset($_GET['date']) ? $db->escape($_GET['date']) : null;
        $shift_id = isset($_GET['shift_id']) ? (int)$_GET['shift_id'] : null;
        
        if ($exclusion_id) {
            $stmt = $db->prepare("
                SELECT me.*, 
                       t.teacher_name,
                       es.shift_number,
                       es.shift_time
                FROM manual_exclusions me
                JOIN teachers t ON me.teacher_id = t.teacher_id
                JOIN exam_shifts es ON me.shift_id = es.shift_id
                WHERE me.exclusion_id = ?
            ");
            $stmt->bind_param("i", $exclusion_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $exclusion = $result->fetch_assoc();
            
            if ($exclusion) {
                sendSuccess('Exclusion retrieved', $exclusion);
            } else {
                sendError('Exclusion not found', 404);
            }
        } else {
            $sql = "
                SELECT me.*, 
                       t.teacher_name,
                       es.shift_number,
                       es.shift_time
                FROM manual_exclusions me
                JOIN teachers t ON me.teacher_id = t.teacher_id
                JOIN exam_shifts es ON me.shift_id = es.shift_id
                WHERE 1=1
            ";
            
            $params = [];
            $types = "";
            
            if ($exam_date) {
                $sql .= " AND me.exclusion_date = ?";
                $params[] = $exam_date;
                $types .= "s";
            }
            
            if ($shift_id) {
                $sql .= " AND me.shift_id = ?";
                $params[] = $shift_id;
                $types .= "i";
            }
            
            $sql .= " ORDER BY me.exclusion_date, es.shift_number, t.teacher_name";
            
            if (!empty($params)) {
                $stmt = $db->prepare($sql);
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();
            } else {
                $result = $db->query($sql);
            }
            
            $exclusions = [];
            while ($row = $result->fetch_assoc()) {
                $exclusions[] = $row;
            }
            
            sendSuccess('Exclusions retrieved', $exclusions);
        }
        break;
        
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['teacher_id'])) {
            sendError('Teacher ID is required');
        }
        if (!isset($data['exclusion_date'])) {
            sendError('Exclusion date is required');
        }
        if (!isset($data['shift_id'])) {
            sendError('Shift ID is required');
        }
        
        $teacher_id = (int)$data['teacher_id'];
        $exclusion_date = $db->escape($data['exclusion_date']);
        $shift_id = (int)$data['shift_id'];
        $reason = isset($data['reason']) ? trim($data['reason']) : '';
        $created_by = isset($data['created_by']) ? trim($data['created_by']) : 'Manager';
        
        $stmt = $db->prepare("
            INSERT INTO manual_exclusions (teacher_id, exclusion_date, shift_id, reason, created_by)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("isiss", $teacher_id, $exclusion_date, $shift_id, $reason, $created_by);
        
        if ($stmt->execute()) {
            $exclusion_id = $db->getLastInsertId();
            sendSuccess('Exclusion created successfully', ['exclusion_id' => $exclusion_id]);
        } else {
            sendError('Failed to create exclusion: ' . $stmt->error);
        }
        break;
        
    case 'DELETE':
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['exclusion_id'])) {
            sendError('Exclusion ID is required');
        }
        
        $exclusion_id = (int)$data['exclusion_id'];
        
        $stmt = $db->prepare("DELETE FROM manual_exclusions WHERE exclusion_id = ?");
        $stmt->bind_param("i", $exclusion_id);
        
        if ($stmt->execute()) {
            sendSuccess('Exclusion deleted successfully');
        } else {
            sendError('Failed to delete exclusion: ' . $stmt->error);
        }
        break;
        
    default:
        sendError('Method not allowed', 405);
        break;
}

