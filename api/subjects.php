<?php
/**
 * Subjects API Endpoint
 */

require_once '../config/database.php';
require_once 'response.php';

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $subject_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
        $dept_id = isset($_GET['dept_id']) ? (int)$_GET['dept_id'] : null;
        $stage_id = isset($_GET['stage_id']) ? (int)$_GET['stage_id'] : null;
        
        if ($subject_id) {
            // Get single subject with teachers
            $stmt = $db->prepare("
                SELECT s.*, 
                       d.dept_name,
                       st.stage_name,
                       GROUP_CONCAT(t.teacher_id) as teacher_ids,
                       GROUP_CONCAT(t.teacher_name) as teacher_names
                FROM subjects s
                JOIN departments d ON s.dept_id = d.dept_id
                JOIN stages st ON s.stage_id = st.stage_id
                LEFT JOIN subject_teacher st_rel ON s.subject_id = st_rel.subject_id
                LEFT JOIN teachers t ON st_rel.teacher_id = t.teacher_id
                WHERE s.subject_id = ?
                GROUP BY s.subject_id
            ");
            $stmt->bind_param("i", $subject_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $subject = $result->fetch_assoc();
            
            if ($subject) {
                $subject['teacher_ids'] = $subject['teacher_ids'] ? explode(',', $subject['teacher_ids']) : [];
                $subject['teacher_names'] = $subject['teacher_names'] ? explode(',', $subject['teacher_names']) : [];
                sendSuccess('Subject retrieved', $subject);
            } else {
                sendError('Subject not found', 404);
            }
        } else {
            // Get all subjects with filters
            $sql = "
                SELECT s.*, 
                       d.dept_name,
                       st.stage_name,
                       GROUP_CONCAT(t.teacher_id) as teacher_ids,
                       GROUP_CONCAT(t.teacher_name) as teacher_names
                FROM subjects s
                JOIN departments d ON s.dept_id = d.dept_id
                JOIN stages st ON s.stage_id = st.stage_id
                LEFT JOIN subject_teacher st_rel ON s.subject_id = st_rel.subject_id
                LEFT JOIN teachers t ON st_rel.teacher_id = t.teacher_id
                WHERE 1=1
            ";
            
            $params = [];
            $types = "";
            
            if ($dept_id) {
                $sql .= " AND s.dept_id = ?";
                $params[] = $dept_id;
                $types .= "i";
            }
            
            if ($stage_id) {
                $sql .= " AND s.stage_id = ?";
                $params[] = $stage_id;
                $types .= "i";
            }
            
            $sql .= " GROUP BY s.subject_id ORDER BY d.dept_name, st.stage_name, s.subject_name";
            
            if (!empty($params)) {
                $stmt = $db->prepare($sql);
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();
            } else {
                $result = $db->query($sql);
            }
            
            $subjects = [];
            while ($row = $result->fetch_assoc()) {
                $row['teacher_ids'] = $row['teacher_ids'] ? explode(',', $row['teacher_ids']) : [];
                $row['teacher_names'] = $row['teacher_names'] ? explode(',', $row['teacher_names']) : [];
                $subjects[] = $row;
            }
            
            sendSuccess('Subjects retrieved', $subjects);
        }
        break;
        
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['subject_name']) || empty($data['subject_name'])) {
            sendError('Subject name is required');
        }
        if (!isset($data['dept_id'])) {
            sendError('Department ID is required');
        }
        if (!isset($data['stage_id'])) {
            sendError('Stage ID is required');
        }
        
        $subject_name = trim($data['subject_name']);
        $dept_id = (int)$data['dept_id'];
        $stage_id = (int)$data['stage_id'];
        $teacher_ids = isset($data['teacher_ids']) ? $data['teacher_ids'] : [];
        
        $stmt = $db->prepare("INSERT INTO subjects (subject_name, dept_id, stage_id) VALUES (?, ?, ?)");
        $stmt->bind_param("sii", $subject_name, $dept_id, $stage_id);
        
        if ($stmt->execute()) {
            $subject_id = $db->getLastInsertId();
            
            // Add teachers
            if (!empty($teacher_ids)) {
                $stmt2 = $db->prepare("INSERT INTO subject_teacher (subject_id, teacher_id) VALUES (?, ?)");
                foreach ($teacher_ids as $teacher_id) {
                    $teacher_id = (int)$teacher_id;
                    $stmt2->bind_param("ii", $subject_id, $teacher_id);
                    $stmt2->execute();
                }
                $stmt2->close();
            }
            
            sendSuccess('Subject created successfully', ['subject_id' => $subject_id]);
        } else {
            sendError('Failed to create subject: ' . $stmt->error);
        }
        break;
        
    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['subject_id'])) {
            sendError('Subject ID is required');
        }
        
        $subject_id = (int)$data['subject_id'];
        $subject_name = trim($data['subject_name']);
        $dept_id = (int)$data['dept_id'];
        $stage_id = (int)$data['stage_id'];
        $teacher_ids = isset($data['teacher_ids']) ? $data['teacher_ids'] : [];
        
        $stmt = $db->prepare("UPDATE subjects SET subject_name = ?, dept_id = ?, stage_id = ? WHERE subject_id = ?");
        $stmt->bind_param("siii", $subject_name, $dept_id, $stage_id, $subject_id);
        
        if ($stmt->execute()) {
            // Update teachers
            $db->query("DELETE FROM subject_teacher WHERE subject_id = $subject_id");
            
            if (!empty($teacher_ids)) {
                $stmt2 = $db->prepare("INSERT INTO subject_teacher (subject_id, teacher_id) VALUES (?, ?)");
                foreach ($teacher_ids as $teacher_id) {
                    $teacher_id = (int)$teacher_id;
                    $stmt2->bind_param("ii", $subject_id, $teacher_id);
                    $stmt2->execute();
                }
                $stmt2->close();
            }
            
            sendSuccess('Subject updated successfully');
        } else {
            sendError('Failed to update subject: ' . $stmt->error);
        }
        break;
        
    case 'DELETE':
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['subject_id'])) {
            sendError('Subject ID is required');
        }
        
        $subject_id = (int)$data['subject_id'];
        
        $stmt = $db->prepare("DELETE FROM subjects WHERE subject_id = ?");
        $stmt->bind_param("i", $subject_id);
        
        if ($stmt->execute()) {
            sendSuccess('Subject deleted successfully');
        } else {
            sendError('Failed to delete subject: ' . $stmt->error);
        }
        break;
        
    default:
        sendError('Method not allowed', 405);
        break;
}

