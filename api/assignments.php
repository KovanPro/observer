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
 * Ensure sections exist for a shift and return section_id mapped by section_number
 */
function ensureSections($db, $shift_id, $sections_count) {
    $stmt = $db->prepare("SELECT section_id, section_number FROM sections WHERE shift_id = ? ORDER BY section_number");
    $stmt->bind_param("i", $shift_id);
    $stmt->execute();
    $sections_result = $stmt->get_result();
    $sections_map = [];
    $existing_count = 0;
    while ($row = $sections_result->fetch_assoc()) {
        $sections_map[(int)$row['section_number']] = (int)$row['section_id'];
        $existing_count++;
    }

    if ($existing_count < $sections_count) {
        for ($i = $existing_count + 1; $i <= $sections_count; $i++) {
            $stmtIns = $db->prepare("INSERT INTO sections (shift_id, section_number) VALUES (?, ?)");
            $stmtIns->bind_param("ii", $shift_id, $i);
            $stmtIns->execute();
            $sections_map[$i] = $db->getLastInsertId();
        }
    }

    return $sections_map;
}

/**
 * Generate observer assignments for a specific date and shift
 * If $preview = true, do NOT write to DB; return assignments only.
 */
function generateAssignments($db, $exam_date, $shift_id, $preview = false) {
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
    $observers_needed = $sections_count * 2; // 2 observers per section
    
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
    
    // Get or create sections for this shift (map section_number -> section_id)
    $sections_map = ensureSections($db, $shift_id, $sections_count);
    
    // Prepare assignment pairs (section_number based)
    $assignments = [];
    $teacher_index = 0;
    for ($sectionNum = 1; $sectionNum <= $sections_count; $sectionNum++) {
        for ($obs = 0; $obs < 2; $obs++) {
            if ($teacher_index >= count($available_teachers)) {
                $teacher_index = 0;
            }
            $teacher = $available_teachers[$teacher_index];
            $assignments[] = [
                'section_number' => $sectionNum,
                'section_id' => $sections_map[$sectionNum] ?? null,
                'teacher_id' => $teacher['teacher_id'],
                'teacher_name' => $teacher['teacher_name']
            ];
            $teacher_index++;
        }
    }

    if ($preview) {
        return [
            'success' => true,
            'message' => "Preview: $observers_needed observers assigned to $sections_count sections",
            'assignments' => $assignments
        ];
    }

    // Delete existing assignments for this date and shift
    $stmt = $db->prepare("DELETE FROM observer_assignments WHERE exam_date = ? AND shift_id = ?");
    $stmt->bind_param("si", $exam_date, $shift_id);
    $stmt->execute();

    // Insert assignments
    $insert_stmt = $db->prepare("
        INSERT INTO observer_assignments (exam_date, shift_id, section_id, teacher_id)
        VALUES (?, ?, ?, ?)
    ");

    foreach ($assignments as $assignment) {
        $section_id = $assignment['section_id'];
        $teacher_id = $assignment['teacher_id'];

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
    }

    return [
        'success' => true,
        'message' => "Assigned $observers_needed observers to $sections_count sections",
        'assignments' => $assignments
    ];
}

switch ($method) {
    case 'GET':
        $exam_date = isset($_GET['date']) ? $db->escape($_GET['date']) : null;
        $shift_id = isset($_GET['shift_id']) ? (int)$_GET['shift_id'] : null;
        
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
            JOIN teachers t ON oa.teacher_id = t.teacher_id
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
        
        if (!isset($data['exam_date'])) {
            sendError('Exam date is required');
        }
        if (!isset($data['shift_id'])) {
            sendError('Shift ID is required');
        }
        
        $exam_date = $db->escape($data['exam_date']);
        $shift_id = (int)$data['shift_id'];

        // If explicit assignments are provided (commit preview), save them directly
        if (isset($data['assignments']) && is_array($data['assignments'])) {
            // Get shift details
            $stmt = $db->prepare("SELECT sections_count FROM exam_shifts WHERE shift_id = ?");
            $stmt->bind_param("i", $shift_id);
            $stmt->execute();
            $shift_result = $stmt->get_result();
            $shift_data = $shift_result->fetch_assoc();
            if (!$shift_data) {
                sendError('Shift not found');
            }
            $sections_count = $shift_data['sections_count'];
            $sections_map = ensureSections($db, $shift_id, $sections_count);

            // Delete existing assignments
            $stmtDel = $db->prepare("DELETE FROM observer_assignments WHERE exam_date = ? AND shift_id = ?");
            $stmtDel->bind_param("si", $exam_date, $shift_id);
            $stmtDel->execute();

            // Insert provided assignments
            $insert_stmt = $db->prepare("
                INSERT INTO observer_assignments (exam_date, shift_id, section_id, teacher_id)
                VALUES (?, ?, ?, ?)
            ");

            foreach ($data['assignments'] as $assignment) {
                $section_number = (int)$assignment['section_number'];
                $teacher_id = (int)$assignment['teacher_id'];
                if (!isset($sections_map[$section_number])) {
                    sendError("Invalid section number: $section_number");
                }
                $section_id = $sections_map[$section_number];

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
            }

            sendSuccess('Assignments saved successfully', $data['assignments']);
        }

        // Preview mode
        $preview = isset($data['preview']) ? (bool)$data['preview'] : false;
        
        $result = generateAssignments($db, $exam_date, $shift_id, $preview);
        
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

