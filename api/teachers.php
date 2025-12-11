<?php
/**
 * Teachers API Endpoint
 */

require_once '../config/database.php';
require_once 'response.php';

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Get all teachers or single teacher
        $teacher_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
        
        if ($teacher_id) {
            // Get single teacher with departments
            $stmt = $db->prepare("
                SELECT t.*, 
                       GROUP_CONCAT(d.dept_id) as dept_ids,
                       GROUP_CONCAT(d.dept_name) as dept_names
                FROM teachers t
                LEFT JOIN teacher_department td ON t.teacher_id = td.teacher_id
                LEFT JOIN departments d ON td.dept_id = d.dept_id
                WHERE t.teacher_id = ?
                GROUP BY t.teacher_id
            ");
            $stmt->bind_param("i", $teacher_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $teacher = $result->fetch_assoc();
            
            if ($teacher) {
                $teacher['dept_ids'] = $teacher['dept_ids'] ? explode(',', $teacher['dept_ids']) : [];
                $teacher['dept_names'] = $teacher['dept_names'] ? explode(',', $teacher['dept_names']) : [];
                sendSuccess('Teacher retrieved', $teacher);
            } else {
                sendError('Teacher not found', 404);
            }
        } else {
            // Get all teachers with departments
            $result = $db->query("
                SELECT t.*, 
                       GROUP_CONCAT(d.dept_id) as dept_ids,
                       GROUP_CONCAT(d.dept_name) as dept_names
                FROM teachers t
                LEFT JOIN teacher_department td ON t.teacher_id = td.teacher_id
                LEFT JOIN departments d ON td.dept_id = d.dept_id
                GROUP BY t.teacher_id
                ORDER BY t.teacher_name
            ");
            
            $teachers = [];
            while ($row = $result->fetch_assoc()) {
                $row['dept_ids'] = $row['dept_ids'] ? explode(',', $row['dept_ids']) : [];
                $row['dept_names'] = $row['dept_names'] ? explode(',', $row['dept_names']) : [];
                $teachers[] = $row;
            }
            
            sendSuccess('Teachers retrieved', $teachers);
        }
        break;
        
    case 'POST':
        // Create new teacher
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['teacher_name']) || empty($data['teacher_name'])) {
            sendError('Teacher name is required');
        }
        
        $teacher_name = trim($data['teacher_name']);
        $is_available = isset($data['is_available']) ? (int)$data['is_available'] : 1;
        $dept_ids = isset($data['dept_ids']) ? $data['dept_ids'] : [];
        
        $stmt = $db->prepare("INSERT INTO teachers (teacher_name, is_available) VALUES (?, ?)");
        $stmt->bind_param("si", $teacher_name, $is_available);
        
        if ($stmt->execute()) {
            $teacher_id = $db->getLastInsertId();
            
            // Add departments
            if (!empty($dept_ids)) {
                $stmt2 = $db->prepare("INSERT INTO teacher_department (teacher_id, dept_id) VALUES (?, ?)");
                foreach ($dept_ids as $dept_id) {
                    $dept_id = (int)$dept_id;
                    $stmt2->bind_param("ii", $teacher_id, $dept_id);
                    $stmt2->execute();
                }
                $stmt2->close();
            }
            
            sendSuccess('Teacher created successfully', ['teacher_id' => $teacher_id]);
        } else {
            sendError('Failed to create teacher: ' . $stmt->error);
        }
        break;
        
    case 'PUT':
        // Update teacher
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['teacher_id'])) {
            sendError('Teacher ID is required');
        }
        
        $teacher_id = (int)$data['teacher_id'];
        $teacher_name = trim($data['teacher_name']);
        $is_available = isset($data['is_available']) ? (int)$data['is_available'] : 1;
        $dept_ids = isset($data['dept_ids']) ? $data['dept_ids'] : [];
        
        $stmt = $db->prepare("UPDATE teachers SET teacher_name = ?, is_available = ? WHERE teacher_id = ?");
        $stmt->bind_param("sii", $teacher_name, $is_available, $teacher_id);
        
        if ($stmt->execute()) {
            // Update departments
            $db->query("DELETE FROM teacher_department WHERE teacher_id = $teacher_id");
            
            if (!empty($dept_ids)) {
                $stmt2 = $db->prepare("INSERT INTO teacher_department (teacher_id, dept_id) VALUES (?, ?)");
                foreach ($dept_ids as $dept_id) {
                    $dept_id = (int)$dept_id;
                    $stmt2->bind_param("ii", $teacher_id, $dept_id);
                    $stmt2->execute();
                }
                $stmt2->close();
            }
            
            sendSuccess('Teacher updated successfully');
        } else {
            sendError('Failed to update teacher: ' . $stmt->error);
        }
        break;
        
    case 'DELETE':
        // Delete teacher
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['teacher_id'])) {
            sendError('Teacher ID is required');
        }
        
        $teacher_id = (int)$data['teacher_id'];
        
        $stmt = $db->prepare("DELETE FROM teachers WHERE teacher_id = ?");
        $stmt->bind_param("i", $teacher_id);
        
        if ($stmt->execute()) {
            sendSuccess('Teacher deleted successfully');
        } else {
            sendError('Failed to delete teacher: ' . $stmt->error);
        }
        break;
        
    default:
        sendError('Method not allowed', 405);
        break;
}

