<?php
/**
 * Observer Assignments API - Core Algorithm
 */

require_once '../config/database.php';
require_once 'response.php';

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

/**
 * Get available teachers for a shift after applying all exclusion rules
 */
function getAvailableTeachers($db, $exam_date, $shift_id) {
    // Step 1: Get all available teachers
    $teachers_result = $db->query("
        SELECT t.teacher_id, t.teacher_name, t.is_available
        FROM teachers t
        WHERE t.is_available = 1
        ORDER BY t.teacher_name
    ");
    
    $all_teachers = [];
    while ($row = $teachers_result->fetch_assoc()) {
        $all_teachers[$row['teacher_id']] = $row;
    }
    
    if (empty($all_teachers)) {
        return [];
    }
    
    // Step 2: Remove teachers with exam in this shift
    $stmt = $db->prepare("
        SELECT DISTINCT st_rel.teacher_id
        FROM exams e
        JOIN subjects s ON e.subject_id = s.subject_id
        JOIN subject_teacher st_rel ON s.subject_id = st_rel.subject_id
        WHERE e.exam_date = ? AND e.shift_id = ?
    ");
    $stmt->bind_param("si", $exam_date, $shift_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        unset($all_teachers[$row['teacher_id']]);
    }
    
    // Step 3: Remove teachers with exam in next shift (shift + 1)
    $next_shift = $shift_id + 1;
    if ($next_shift <= 5) {
        $stmt = $db->prepare("
            SELECT DISTINCT st_rel.teacher_id
            FROM exams e
            JOIN subjects s ON e.subject_id = s.subject_id
            JOIN subject_teacher st_rel ON s.subject_id = st_rel.subject_id
            WHERE e.exam_date = ? AND e.shift_id = ?
        ");
        $stmt->bind_param("si", $exam_date, $next_shift);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            unset($all_teachers[$row['teacher_id']]);
        }
    }
    
    // Step 4: Remove teachers from multi-teacher exams in this shift
    // (All teachers of a subject with multiple teachers are excluded)
    $stmt = $db->prepare("
        SELECT DISTINCT st_rel.teacher_id
        FROM exams e
        JOIN subjects s ON e.subject_id = s.subject_id
        JOIN subject_teacher st_rel ON s.subject_id = st_rel.subject_id
        WHERE e.exam_date = ? AND e.shift_id = ?
        AND (
            SELECT COUNT(*) 
            FROM subject_teacher st2 
            WHERE st2.subject_id = s.subject_id
        ) > 1
    ");
    $stmt->bind_param("si", $exam_date, $shift_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        unset($all_teachers[$row['teacher_id']]);
    }
    
    // Step 5: Remove manually excluded teachers
    $stmt = $db->prepare("
        SELECT teacher_id
        FROM manual_exclusions
        WHERE exclusion_date = ? AND shift_id = ?
    ");
    $stmt->bind_param("si", $exam_date, $shift_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        unset($all_teachers[$row['teacher_id']]);
    }
    
    // Step 6: Remove teachers already assigned in this shift
    $stmt = $db->prepare("
        SELECT DISTINCT teacher_id
        FROM observer_assignments
        WHERE exam_date = ? AND shift_id = ?
    ");
    $stmt->bind_param("si", $exam_date, $shift_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        unset($all_teachers[$row['teacher_id']]);
    }
    
    // Step 7: Special rule for Shift 5 (evening shift)
    // If teacher has ANY evening exam on this date, exclude from Shift 5
    if ($shift_id == 5) {
        $stmt = $db->prepare("
            SELECT DISTINCT st_rel.teacher_id
            FROM exams e
            JOIN subjects s ON e.subject_id = s.subject_id
            JOIN subject_teacher st_rel ON s.subject_id = st_rel.subject_id
            WHERE e.exam_date = ? AND e.is_evening = 1
        ");
        $stmt->bind_param("s", $exam_date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            unset($all_teachers[$row['teacher_id']]);
        }
    }
    
    return array_values($all_teachers);
}

/**
 * Generate observer assignments for a specific date and shift
 */
function generateAssignments($db, $exam_date, $shift_id) {
    // First, check if there are any exams scheduled for this date and shift
    $stmt = $db->prepare("SELECT COUNT(*) as exam_count FROM exams WHERE exam_date = ? AND shift_id = ?");
    $stmt->bind_param("si", $exam_date, $shift_id);
    $stmt->execute();
    $exam_result = $stmt->get_result();
    $exam_data = $exam_result->fetch_assoc();
    
    if (!$exam_data || $exam_data['exam_count'] == 0) {
        // Get shift number for better error message
        $stmt2 = $db->prepare("SELECT shift_number FROM exam_shifts WHERE shift_id = ?");
        $stmt2->bind_param("i", $shift_id);
        $stmt2->execute();
        $shift_info = $stmt2->get_result()->fetch_assoc();
        $shift_number = $shift_info ? $shift_info['shift_number'] : $shift_id;
        
        return [
            'success' => false,
            'message' => "Cannot assign observers: No exams scheduled for this date and shift. Please create exams for date $exam_date and Shift $shift_number first."
        ];
    }
    
    // Get shift details
    $stmt = $db->prepare("SELECT sections_count FROM exam_shifts WHERE shift_id = ?");
    $stmt->bind_param("i", $shift_id);
    $stmt->execute();
    $shift_result = $stmt->get_result();
    $shift_data = $shift_result->fetch_assoc();
    
    if (!$shift_data) {
        return ['success' => false, 'message' => 'Shift not found'];
    }
    
    $sections_count = $shift_data['sections_count'];
    $observers_needed = $sections_count * 1; // 1 observer per section
    
    // Get available teachers
    $available_teachers = getAvailableTeachers($db, $exam_date, $shift_id);
    
    if (count($available_teachers) < $observers_needed) {
        return [
            'success' => false,
            'message' => "Not enough available teachers for shift $shift_id on date $exam_date. Needed: $observers_needed, Available: " . count($available_teachers)
        ];
    }
    
    // Shuffle teachers for randomization
    shuffle($available_teachers);
    
    // Get or create sections for this shift
    $stmt = $db->prepare("SELECT section_id FROM sections WHERE shift_id = ? ORDER BY section_number");
    $stmt->bind_param("i", $shift_id);
    $stmt->execute();
    $sections_result = $stmt->get_result();
    $sections = [];
    while ($row = $sections_result->fetch_assoc()) {
        $sections[] = $row['section_id'];
    }
    
    // Create sections if they don't exist
    if (count($sections) < $sections_count) {
        for ($i = count($sections) + 1; $i <= $sections_count; $i++) {
            $stmt = $db->prepare("INSERT INTO sections (shift_id, section_number) VALUES (?, ?)");
            $stmt->bind_param("ii", $shift_id, $i);
            $stmt->execute();
            $sections[] = $db->getLastInsertId();
        }
    }
    
    // Delete existing assignments for this date and shift
    $stmt = $db->prepare("DELETE FROM observer_assignments WHERE exam_date = ? AND shift_id = ?");
    $stmt->bind_param("si", $exam_date, $shift_id);
    $stmt->execute();
    
    // Assign 1 observer per section
    $assignments = [];
    $teacher_index = 0;
    
    $insert_stmt = $db->prepare("
        INSERT INTO observer_assignments (exam_date, shift_id, section_id, teacher_id)
        VALUES (?, ?, ?, ?)
    ");
    
    foreach ($sections as $section_id) {
        for ($obs = 0; $obs < 1; $obs++) {
            if ($teacher_index >= count($available_teachers)) {
                // If we run out, cycle back (shouldn't happen due to check above)
                $teacher_index = 0;
            }
            
            $teacher_id = $available_teachers[$teacher_index]['teacher_id'];
            
            $insert_stmt->bind_param("siii", $exam_date, $shift_id, $section_id, $teacher_id);
            $insert_stmt->execute();
            
            $assignment_id = $db->getLastInsertId();
            
            // Save to history
            $history_stmt = $db->prepare("
                INSERT INTO observer_history (exam_date, shift_id, section_id, teacher_id, assignment_id, action_type)
                VALUES (?, ?, ?, ?, ?, 'assigned')
            ");
            $history_stmt->bind_param("siiii", $exam_date, $shift_id, $section_id, $teacher_id, $assignment_id);
            $history_stmt->execute();
            
            $assignments[] = [
                'section_id' => $section_id,
                'teacher_id' => $teacher_id,
                'teacher_name' => $available_teachers[$teacher_index]['teacher_name']
            ];
            
            $teacher_index++;
        }
    }
    
    return [
        'success' => true,
        'message' => "Assigned $observers_needed observers to $sections_count sections",
        'assignments' => $assignments
    ];
}

switch ($method) {
    case 'GET':
        $action = isset($_GET['action']) ? $_GET['action'] : null;
        $exam_date = isset($_GET['date']) ? $db->escape($_GET['date']) : null;
        $shift_id = isset($_GET['shift_id']) ? (int)$_GET['shift_id'] : null;
        
        if ($action === 'get_available_teachers') {
            if (!$exam_date || !$shift_id) {
                sendError('Exam date and shift ID are required');
            }
            
            $availableTeachers = getAvailableTeachers($db, $exam_date, $shift_id);
            
            // If for_edit=1, also include currently assigned teachers for that date/shift
            $forEdit = isset($_GET['for_edit']) && $_GET['for_edit'] == '1';
            if ($forEdit) {
                $stmt = $db->prepare("
                    SELECT DISTINCT t.teacher_id, t.teacher_name
                    FROM observer_assignments oa
                    JOIN teachers t ON oa.teacher_id = t.teacher_id
                    WHERE oa.exam_date = ? AND oa.shift_id = ?
                ");
                $stmt->bind_param("si", $exam_date, $shift_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                while ($row = $result->fetch_assoc()) {
                    $availableTeachers[$row['teacher_id']] = $row;
                }
            }
            
            sendSuccess('Available teachers retrieved', array_values($availableTeachers));
            break;
        }
        
        if (!$exam_date) {
            sendError('Exam date is required');
        }
        
        $sql = "
            SELECT oa.*, 
                   t.teacher_name,
                   es.shift_number,
                   es.shift_time,
                   s.section_number
            FROM observer_assignments oa
            LEFT JOIN teachers t ON oa.teacher_id = t.teacher_id
            JOIN exam_shifts es ON oa.shift_id = es.shift_id
            JOIN sections s ON oa.section_id = s.section_id
            WHERE oa.exam_date = ?
        ";
        
        $params = [$exam_date];
        $types = "s";
        
        if ($shift_id) {
            $sql .= " AND oa.shift_id = ?";
            $params[] = $shift_id;
            $types .= "i";
        }
        
        $sql .= " ORDER BY es.shift_number, s.section_number, t.teacher_name";
        
        $stmt = $db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $assignments = [];
        while ($row = $result->fetch_assoc()) {
            $assignments[] = $row;
        }
        
        sendSuccess('Assignments retrieved', $assignments);
        break;
        
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        $action = isset($data['action']) ? $data['action'] : null;
        
        if ($action === 'update_assignments') {
            if (!isset($data['date']) || !isset($data['shift_id']) || !isset($data['updates'])) {
                sendError('Date, shift_id, and updates are required');
            }
            
            $exam_date = $db->escape($data['date']);
            $shift_id = (int)$data['shift_id'];
            $updates = $data['updates'];
            
            $db->getConnection()->autocommit(false);
            
            try {
                foreach ($updates as $update) {
                    $section_number = (int)$update['section_number'];
                    $teacher_id = isset($update['teacher_id']) ? (int)$update['teacher_id'] : null;
                    
                    // Get section_id
                    $stmt = $db->prepare("SELECT section_id FROM sections WHERE section_number = ?");
                    if (!$stmt) {
                        throw new Exception('Database prepare error: ' . $db->getConnection()->error);
                    }
                    $stmt->bind_param("i", $section_number);
                    if (!$stmt->execute()) {
                        throw new Exception('Database execute error: ' . $stmt->error);
                    }
                    $section_result = $stmt->get_result();
                    $section = $section_result->fetch_assoc();
                    
                    if (!$section) {
                        throw new Exception("Section $section_number not found");
                    }
                    
                    $section_id = $section['section_id'];
                    
                    // Delete existing assignment
                    $stmt = $db->prepare("DELETE FROM observer_assignments WHERE exam_date = ? AND shift_id = ? AND section_id = ?");
                    if (!$stmt) {
                        throw new Exception('Database prepare error: ' . $db->getConnection()->error);
                    }
                    $stmt->bind_param("sii", $exam_date, $shift_id, $section_id);
                    if (!$stmt->execute()) {
                        throw new Exception('Database execute error: ' . $stmt->error);
                    }
                    
                    // Insert new assignment if teacher_id is not null
                    if ($teacher_id) {
                        $stmt = $db->prepare("INSERT INTO observer_assignments (exam_date, shift_id, section_id, teacher_id) VALUES (?, ?, ?, ?)");
                        if (!$stmt) {
                            throw new Exception('Database prepare error: ' . $db->getConnection()->error);
                        }
                        $stmt->bind_param("siii", $exam_date, $shift_id, $section_id, $teacher_id);
                        if (!$stmt->execute()) {
                            throw new Exception('Database execute error: ' . $stmt->error);
                        }
                    }
                }
                
                $db->getConnection()->commit();
                $db->getConnection()->autocommit(true);
                sendSuccess('Assignments updated successfully');
                
            } catch (Exception $e) {
                $db->getConnection()->rollback();
                $db->getConnection()->autocommit(true);
                sendError('Error updating assignments: ' . $e->getMessage());
            }
            break;
        }
        
        if (!isset($data['exam_date'])) {
            sendError('Exam date is required');
        }
        if (!isset($data['shift_id'])) {
            sendError('Shift ID is required');
        }
        
        $exam_date = $db->escape($data['exam_date']);
        $shift_id = (int)$data['shift_id'];
        
        $result = generateAssignments($db, $exam_date, $shift_id);
        
        if ($result['success']) {
            sendSuccess($result['message'], $result['assignments']);
        } else {
            sendError($result['message']);
        }
        break;
        
    case 'DELETE':
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['exam_date'])) {
            sendError('Exam date is required');
        }
        if (!isset($data['shift_id'])) {
            sendError('Shift ID is required');
        }
        
        $exam_date = $db->escape($data['exam_date']);
        $shift_id = (int)$data['shift_id'];
        
        // Move to history before deleting
        $stmt = $db->prepare("
            INSERT INTO observer_history (exam_date, shift_id, section_id, teacher_id, assignment_id, action_type)
            SELECT exam_date, shift_id, section_id, teacher_id, assignment_id, 'removed'
            FROM observer_assignments
            WHERE exam_date = ? AND shift_id = ?
        ");
        $stmt->bind_param("si", $exam_date, $shift_id);
        $stmt->execute();
        
        // Delete assignments
        $stmt = $db->prepare("DELETE FROM observer_assignments WHERE exam_date = ? AND shift_id = ?");
        $stmt->bind_param("si", $exam_date, $shift_id);
        
        if ($stmt->execute()) {
            sendSuccess('Assignments deleted successfully');
        } else {
            sendError('Failed to delete assignments: ' . $stmt->error);
        }
        break;
        
    default:
        sendError('Method not allowed', 405);
        break;
}

