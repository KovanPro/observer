<?php
/**
 * Exams API Endpoint
 */

require_once '../config/database.php';
require_once 'response.php';

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $exam_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
        $exam_date = isset($_GET['date']) ? $db->escape($_GET['date']) : null;
        $shift_id = isset($_GET['shift_id']) ? (int)$_GET['shift_id'] : null;
        
        if ($exam_id) {
            // Get single exam
            $stmt = $db->prepare("
                SELECT e.*, 
                       s.subject_name,
                       d.dept_name,
                       st.stage_name,
                       es.shift_number,
                       es.shift_time,
                       GROUP_CONCAT(t.teacher_id) as teacher_ids,
                       GROUP_CONCAT(t.teacher_name) as teacher_names
                FROM exams e
                JOIN subjects s ON e.subject_id = s.subject_id
                JOIN departments d ON e.dept_id = d.dept_id
                JOIN stages st ON e.stage_id = st.stage_id
                JOIN exam_shifts es ON e.shift_id = es.shift_id
                LEFT JOIN subject_teacher st_rel ON s.subject_id = st_rel.subject_id
                LEFT JOIN teachers t ON st_rel.teacher_id = t.teacher_id
                WHERE e.exam_id = ?
                GROUP BY e.exam_id
            ");
            $stmt->bind_param("i", $exam_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $exam = $result->fetch_assoc();
            
            if ($exam) {
                $exam['teacher_ids'] = $exam['teacher_ids'] ? explode(',', $exam['teacher_ids']) : [];
                $exam['teacher_names'] = $exam['teacher_names'] ? explode(',', $exam['teacher_names']) : [];
                sendSuccess('Exam retrieved', $exam);
            } else {
                sendError('Exam not found', 404);
            }
        } else {
            // Get exams with filters
            $sql = "
                SELECT e.*, 
                       s.subject_name,
                       d.dept_name,
                       st.stage_name,
                       es.shift_number,
                       es.shift_time
                FROM exams e
                JOIN subjects s ON e.subject_id = s.subject_id
                JOIN departments d ON e.dept_id = d.dept_id
                JOIN stages st ON e.stage_id = st.stage_id
                JOIN exam_shifts es ON e.shift_id = es.shift_id
                WHERE 1=1
            ";
            
            $params = [];
            $types = "";
            
            if ($exam_date) {
                $sql .= " AND e.exam_date = ?";
                $params[] = $exam_date;
                $types .= "s";
            }
            
            if ($shift_id) {
                $sql .= " AND e.shift_id = ?";
                $params[] = $shift_id;
                $types .= "i";
            }
            
            $sql .= " ORDER BY e.exam_date, es.shift_number, d.dept_name";
            
            if (!empty($params)) {
                $stmt = $db->prepare($sql);
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();
            } else {
                $result = $db->query($sql);
            }
            
            $exams = [];
            while ($row = $result->fetch_assoc()) {
                // Get teachers for each exam
                $stmt2 = $db->prepare("
                    SELECT t.teacher_id, t.teacher_name
                    FROM subject_teacher st_rel
                    JOIN teachers t ON st_rel.teacher_id = t.teacher_id
                    WHERE st_rel.subject_id = ?
                ");
                $stmt2->bind_param("i", $row['subject_id']);
                $stmt2->execute();
                $teachers_result = $stmt2->get_result();
                
                $teacher_ids = [];
                $teacher_names = [];
                while ($teacher = $teachers_result->fetch_assoc()) {
                    $teacher_ids[] = $teacher['teacher_id'];
                    $teacher_names[] = $teacher['teacher_name'];
                }
                $row['teacher_ids'] = $teacher_ids;
                $row['teacher_names'] = $teacher_names;
                $exams[] = $row;
            }
            
            sendSuccess('Exams retrieved', $exams);
        }
        break;
        
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['exam_date'])) {
            sendError('Exam date is required');
        }
        if (!isset($data['shift_id'])) {
            sendError('Shift ID is required');
        }
        if (!isset($data['subject_id'])) {
            sendError('Subject ID is required');
        }
        
        $exam_date = $db->escape($data['exam_date']);
        $shift_id = (int)$data['shift_id'];
        $subject_id = (int)$data['subject_id'];
        
        // Get subject details to determine dept_id, stage_id, and is_evening
        $stmt = $db->prepare("
            SELECT s.dept_id, s.stage_id, st.is_evening
            FROM subjects s
            JOIN stages st ON s.stage_id = st.stage_id
            WHERE s.subject_id = ?
        ");
        $stmt->bind_param("i", $subject_id);
        $stmt->execute();
        $subject_result = $stmt->get_result();
        $subject_data = $subject_result->fetch_assoc();
        
        if (!$subject_data) {
            sendError('Subject not found');
        }
        
        $dept_id = $subject_data['dept_id'];
        $stage_id = $subject_data['stage_id'];
        $is_evening = (int)$subject_data['is_evening'];
        
        $stmt = $db->prepare("
            INSERT INTO exams (exam_date, shift_id, subject_id, dept_id, stage_id, is_evening)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("siiiii", $exam_date, $shift_id, $subject_id, $dept_id, $stage_id, $is_evening);
        
        if ($stmt->execute()) {
            $exam_id = $db->getLastInsertId();
            sendSuccess('Exam created successfully', ['exam_id' => $exam_id]);
        } else {
            sendError('Failed to create exam: ' . $stmt->error);
        }
        break;
        
    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['exam_id'])) {
            sendError('Exam ID is required');
        }
        
        $exam_id = (int)$data['exam_id'];
        $exam_date = $db->escape($data['exam_date']);
        $shift_id = (int)$data['shift_id'];
        $subject_id = (int)$data['subject_id'];
        
        // Get subject details
        $stmt = $db->prepare("
            SELECT s.dept_id, s.stage_id, st.is_evening
            FROM subjects s
            JOIN stages st ON s.stage_id = st.stage_id
            WHERE s.subject_id = ?
        ");
        $stmt->bind_param("i", $subject_id);
        $stmt->execute();
        $subject_result = $stmt->get_result();
        $subject_data = $subject_result->fetch_assoc();
        
        if (!$subject_data) {
            sendError('Subject not found');
        }
        
        $dept_id = $subject_data['dept_id'];
        $stage_id = $subject_data['stage_id'];
        $is_evening = (int)$subject_data['is_evening'];
        
        $stmt = $db->prepare("
            UPDATE exams 
            SET exam_date = ?, shift_id = ?, subject_id = ?, dept_id = ?, stage_id = ?, is_evening = ?
            WHERE exam_id = ?
        ");
        $stmt->bind_param("siiiiii", $exam_date, $shift_id, $subject_id, $dept_id, $stage_id, $is_evening, $exam_id);
        
        if ($stmt->execute()) {
            sendSuccess('Exam updated successfully');
        } else {
            sendError('Failed to update exam: ' . $stmt->error);
        }
        break;
        
    case 'DELETE':
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['exam_id'])) {
            sendError('Exam ID is required');
        }
        
        $exam_id = (int)$data['exam_id'];
        
        $stmt = $db->prepare("DELETE FROM exams WHERE exam_id = ?");
        $stmt->bind_param("i", $exam_id);
        
        if ($stmt->execute()) {
            sendSuccess('Exam deleted successfully');
        } else {
            sendError('Failed to delete exam: ' . $stmt->error);
        }
        break;
        
    default:
        sendError('Method not allowed', 405);
        break;
}

