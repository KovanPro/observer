// API Base URL
const API_BASE = 'api/';

// Global data cache
let teachers = [];
let subjects = [];
let departments = [];
let stages = [];
let shifts = [];
let exams = [];

// Initialize app
document.addEventListener('DOMContentLoaded', function() {
    loadInitialData();
    setupEventListeners();
    loadDashboard();
});

// Load initial data
async function loadInitialData() {
    try {
        // Load departments, shifts, teachers, and all stages
        [departments, shifts, teachers, stages] = await Promise.all([
            fetchData('departments.php'),
            fetchData('shifts.php'),
            fetchData('teachers.php'),
            fetchData('stages.php')
        ]);
        
        populateSelects();
        loadTeachers();
        loadSubjects();
        loadExams();
        loadShifts();
    } catch (error) {
        console.error('Error loading initial data:', error);
    }
}

// Setup event listeners
function setupEventListeners() {
    // Navigation
    document.querySelectorAll('.nav-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const page = this.getAttribute('data-page');
            showPage(page);
        });
    });
    
    // Forms
    document.getElementById('teacher-form').addEventListener('submit', saveTeacher);
    document.getElementById('subject-form').addEventListener('submit', saveSubject);
    document.getElementById('exam-form').addEventListener('submit', saveExam);
    document.getElementById('exclusion-form').addEventListener('submit', saveExclusion);
    
    // Department change listener for subject modal (add subject)
    const subjectDeptSelect = document.getElementById('subject-dept');
    if (subjectDeptSelect) {
        subjectDeptSelect.addEventListener('change', function() {
            console.log('Subject modal department changed:', this.value);
            loadStagesForSubject();
        });
    }
    
    // Department change listener for Subjects filter (list page)
    const filterDeptSelect = document.getElementById('filter-dept');
    if (filterDeptSelect) {
        filterDeptSelect.addEventListener('change', function() {
            console.log('Subjects filter department changed:', this.value);
            // Update stage filter options when department filter changes
            populateStageFilterOptions();
            // Reload subjects list based on new filters
            loadSubjects();
        });
    }

    // Auto-fetch assignments when date/shift change
    const assignmentDate = document.getElementById('assignment-date');
    const assignmentShift = document.getElementById('assignment-shift');
    [assignmentDate, assignmentShift].forEach(el => {
        if (el) {
            el.addEventListener('change', () => {
                // Only load when both are selected
                if (assignmentDate.value && assignmentShift.value) {
                    loadAssignments();
                }
            });
        }
    });
}

// Navigation
function showPage(pageName) {
    document.querySelectorAll('.page').forEach(page => {
        page.classList.remove('active');
    });
    document.querySelectorAll('.nav-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    document.getElementById(pageName).classList.add('active');
    document.querySelector(`[data-page="${pageName}"]`).classList.add('active');
    
    // Load page-specific data
    if (pageName === 'assignments') {
        loadAssignments();
    } else if (pageName === 'exclusions') {
        loadExclusions();
    } else if (pageName === 'history') {
        loadHistory();
    }
}

// API Helper
async function fetchData(endpoint, options = {}) {
    try {
        const response = await fetch(API_BASE + endpoint, options);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const text = await response.text();
        let data;
        
        try {
            data = JSON.parse(text);
        } catch (parseError) {
            console.error('JSON Parse Error:', parseError);
            console.error('Response text:', text);
            throw new Error('Invalid JSON response from server');
        }
        
        if (!data.success) {
            throw new Error(data.message || 'Request failed');
        }
        
        return data.data || [];
    } catch (error) {
        console.error('API Error:', error);
        console.error('Endpoint:', API_BASE + endpoint);
        throw error;
    }
}

async function postData(endpoint, data) {
    return fetchData(endpoint, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    });
}

async function putData(endpoint, data) {
    return fetchData(endpoint, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    });
}

async function deleteData(endpoint, data) {
    return fetchData(endpoint, {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    });
}

// Dashboard
async function loadDashboard() {
    try {
        const [teachersData, subjectsData, examsData] = await Promise.all([
            fetchData('teachers.php'),
            fetchData('subjects.php'),
            fetchData('exams.php')
        ]);
        
        document.getElementById('stat-teachers').textContent = teachersData.length;
        document.getElementById('stat-subjects').textContent = subjectsData.length;
        
        const today = new Date().toISOString().split('T')[0];
        const upcomingExams = examsData.filter(e => e.exam_date >= today);
        document.getElementById('stat-exams').textContent = upcomingExams.length;
        
        // Load assignments count
        try {
            const assignments = await fetchData(`assignments.php?date=${today}`);
            document.getElementById('stat-assignments').textContent = assignments.length;
        } catch (e) {
            document.getElementById('stat-assignments').textContent = '0';
        }
    } catch (error) {
        console.error('Error loading dashboard:', error);
    }
}

// Teachers Management
async function loadTeachers() {
    try {
        teachers = await fetchData('teachers.php');
        const tbody = document.querySelector('#teachers-table tbody');
        tbody.innerHTML = '';
        
        teachers.forEach(teacher => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${teacher.teacher_id}</td>
                <td>${teacher.teacher_name}</td>
                <td>${teacher.is_available ? 'Available' : 'Unavailable'}</td>
                <td>
                    <button class="btn btn-secondary" onclick="editTeacher(${teacher.teacher_id})">Edit</button>
                    <button class="btn btn-danger" onclick="deleteTeacher(${teacher.teacher_id})">Delete</button>
                </td>
            `;
            tbody.appendChild(row);
        });
    } catch (error) {
        alert('Error loading teachers: ' + error.message);
    }
}

function openTeacherModal(teacherId = null) {
    document.getElementById('teacher-id').value = teacherId || '';
    document.getElementById('teacher-name').value = '';
    document.getElementById('teacher-status').value = '1';
    document.getElementById('teacher-modal-title').textContent = teacherId ? 'Edit Teacher' : 'Add Teacher';
    
    if (teacherId) {
        const teacher = teachers.find(t => t.teacher_id == teacherId);
        if (teacher) {
            document.getElementById('teacher-name').value = teacher.teacher_name;
            document.getElementById('teacher-status').value = teacher.is_available ? '1' : '0';
        }
    }
    
    document.getElementById('teacher-modal').style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

async function saveTeacher(e) {
    e.preventDefault();
    
    const teacherId = document.getElementById('teacher-id').value;
    const teacherName = document.getElementById('teacher-name').value;
    const isAvailable = document.getElementById('teacher-status').value;
    
    const data = {
        teacher_name: teacherName,
        is_available: parseInt(isAvailable),
        dept_ids: [] // Teachers can teach in any department, no need to specify
    };
    
    try {
        if (teacherId) {
            data.teacher_id = parseInt(teacherId);
            await putData('teachers.php', data);
            alert('Teacher updated successfully!');
        } else {
            await postData('teachers.php', data);
            alert('Teacher created successfully!');
        }
        
        closeModal('teacher-modal');
        loadTeachers();
        loadDashboard();
    } catch (error) {
        alert('Error saving teacher: ' + error.message);
    }
}

function editTeacher(teacherId) {
    openTeacherModal(teacherId);
}

async function deleteTeacher(teacherId) {
    if (!confirm('Are you sure you want to delete this teacher?')) return;
    
    try {
        await deleteData('teachers.php', { teacher_id: teacherId });
        alert('Teacher deleted successfully!');
        loadTeachers();
        loadDashboard();
    } catch (error) {
        alert('Error deleting teacher: ' + error.message);
    }
}

// Subjects Management
async function loadSubjects() {
    try {
        const deptId = document.getElementById('filter-dept').value;
        const stageId = document.getElementById('filter-stage').value;
        
        let url = 'subjects.php';
        if (deptId) url += `?dept_id=${deptId}`;
        if (stageId) url += deptId ? `&stage_id=${stageId}` : `?stage_id=${stageId}`;
        
        subjects = await fetchData(url);
        const tbody = document.querySelector('#subjects-table tbody');
        tbody.innerHTML = '';
        
        subjects.forEach(subject => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${subject.subject_id}</td>
                <td>${subject.subject_name}</td>
                <td>${subject.dept_name}</td>
                <td>${subject.stage_name}</td>
                <td>${subject.teacher_names ? subject.teacher_names.join(', ') : 'None'}</td>
                <td>
                    <button class="btn btn-secondary" onclick="editSubject(${subject.subject_id})">Edit</button>
                    <button class="btn btn-danger" onclick="deleteSubject(${subject.subject_id})">Delete</button>
                </td>
            `;
            tbody.appendChild(row);
        });
    } catch (error) {
        alert('Error loading subjects: ' + error.message);
    }
}

function populateSelects() {
    // Populate department filters
    const deptSelects = ['filter-dept', 'subject-dept', 'exam-subject'];
    deptSelects.forEach(selectId => {
        const select = document.getElementById(selectId);
        if (select) {
            select.innerHTML = '<option value="">Select Department</option>';
            departments.forEach(dept => {
                const option = document.createElement('option');
                option.value = dept.dept_id;
                option.textContent = dept.dept_name;
                select.appendChild(option);
            });
        }
    });
    
    // Populate stage filter (for Subjects page)
    populateStageFilterOptions();
    
    // Populate shift selects
    const shiftSelects = ['assignment-shift', 'exam-shift', 'exclusion-shift', 'history-shift'];
    shiftSelects.forEach(selectId => {
        const select = document.getElementById(selectId);
        if (select) {
            const firstOption = select.querySelector('option[value=""]') || document.createElement('option');
            firstOption.value = '';
            firstOption.textContent = selectId === 'history-shift' ? 'All Shifts' : 'Select Shift';
            select.innerHTML = '';
            select.appendChild(firstOption);
            shifts.forEach(shift => {
                const option = document.createElement('option');
                option.value = shift.shift_id;
                option.textContent = `Shift ${shift.shift_number} (${shift.shift_time})`;
                select.appendChild(option);
            });
        }
    });
}

// Populate the stage filter dropdown based on selected department (or all)
function populateStageFilterOptions() {
    const stageFilter = document.getElementById('filter-stage');
    const deptFilter = document.getElementById('filter-dept');
    
    if (!stageFilter) return;
    
    // Reset options
    stageFilter.innerHTML = '<option value=\"\">All Stages</option>';
    
    if (!stages || stages.length === 0) {
        return;
    }
    
    const selectedDeptId = deptFilter ? deptFilter.value : '';
    
    stages.forEach(stage => {
        // If a department is selected, only show its stages
        if (!selectedDeptId || stage.dept_id == selectedDeptId) {
            const option = document.createElement('option');
            option.value = stage.stage_id;
            option.textContent = stage.stage_name;
            stageFilter.appendChild(option);
        }
    });
}

async function loadStagesForSubject() {
    console.log('loadStagesForSubject called');
    const deptId = document.getElementById('subject-dept').value;
    console.log('Selected department ID:', deptId);
    const stageSelect = document.getElementById('subject-stage');
    
    if (!stageSelect) {
        console.error('Stage select element not found!');
        return;
    }
    
    stageSelect.innerHTML = '<option value="">Loading stages...</option>';
    
    if (deptId) {
        try {
            console.log('Fetching stages for department:', deptId);
            const stagesData = await fetchData(`stages.php?dept_id=${deptId}`);
            console.log('Stages data received:', stagesData);
            
            if (stagesData && Array.isArray(stagesData) && stagesData.length > 0) {
                stageSelect.innerHTML = '<option value="">Select Stage</option>';
                stagesData.forEach(stage => {
                    const option = document.createElement('option');
                    option.value = stage.stage_id;
                    option.textContent = stage.stage_name;
                    stageSelect.appendChild(option);
                });
                console.log('Stages populated:', stagesData.length, 'stages');
            } else {
                console.warn('No stages found for department:', deptId);
                stageSelect.innerHTML = '<option value="">No stages available</option>';
            }
        } catch (error) {
            console.error('Error loading stages:', error);
            alert('Error loading stages: ' + error.message);
            stageSelect.innerHTML = '<option value="">Error loading stages</option>';
        }
    } else {
        stageSelect.innerHTML = '<option value="">Select Stage</option>';
    }
}

// Make function globally accessible
window.loadStagesForSubject = loadStagesForSubject;

async function openSubjectModal(subjectId = null) {
    document.getElementById('subject-id').value = subjectId || '';
    document.getElementById('subject-name').value = '';
    document.getElementById('subject-dept').value = '';
    document.getElementById('subject-stage').innerHTML = '<option value="">Select Stage</option>';
    document.getElementById('subject-modal-title').textContent = subjectId ? 'Edit Subject' : 'Add Subject';
    
    // Ensure department select is populated
    const deptSelect = document.getElementById('subject-dept');
    if (deptSelect) {
        deptSelect.innerHTML = '<option value="">Select Department</option>';
        if (departments && departments.length > 0) {
            departments.forEach(dept => {
                const option = document.createElement('option');
                option.value = dept.dept_id;
                option.textContent = dept.dept_name;
                deptSelect.appendChild(option);
            });
        } else {
            console.warn('Departments not loaded yet');
        }
    }
    
    // Populate teachers
    const teacherContainer = document.getElementById('subject-teachers');
    teacherContainer.innerHTML = '';
    if (teachers && teachers.length > 0) {
        teachers.forEach(teacher => {
            const label = document.createElement('label');
            label.innerHTML = `
                <input type="checkbox" value="${teacher.teacher_id}" class="teacher-checkbox">
                ${teacher.teacher_name}
            `;
            teacherContainer.appendChild(label);
        });
    }
    
    if (subjectId) {
        const subject = subjects.find(s => s.subject_id == subjectId);
        if (subject) {
            document.getElementById('subject-name').value = subject.subject_name;
            document.getElementById('subject-dept').value = subject.dept_id;
            await loadStagesForSubject();
            setTimeout(() => {
                document.getElementById('subject-stage').value = subject.stage_id;
                if (subject.teacher_ids) {
                    subject.teacher_ids.forEach(teacherId => {
                        const checkbox = teacherContainer.querySelector(`input[value="${teacherId}"]`);
                        if (checkbox) checkbox.checked = true;
                    });
                }
            }, 100);
        }
    }
    
    document.getElementById('subject-modal').style.display = 'block';
}

async function saveSubject(e) {
    e.preventDefault();
    
    const subjectId = document.getElementById('subject-id').value;
    const subjectName = document.getElementById('subject-name').value;
    const deptId = document.getElementById('subject-dept').value;
    const stageId = document.getElementById('subject-stage').value;
    const teacherCheckboxes = document.querySelectorAll('.teacher-checkbox:checked');
    const teacherIds = Array.from(teacherCheckboxes).map(cb => parseInt(cb.value));
    
    const data = {
        subject_name: subjectName,
        dept_id: parseInt(deptId),
        stage_id: parseInt(stageId),
        teacher_ids: teacherIds
    };
    
    try {
        if (subjectId) {
            data.subject_id = parseInt(subjectId);
            await putData('subjects.php', data);
            alert('Subject updated successfully!');
        } else {
            await postData('subjects.php', data);
            alert('Subject created successfully!');
        }
        
        closeModal('subject-modal');
        loadSubjects();
        loadDashboard();
    } catch (error) {
        alert('Error saving subject: ' + error.message);
    }
}

function editSubject(subjectId) {
    openSubjectModal(subjectId);
}

async function deleteSubject(subjectId) {
    if (!confirm('Are you sure you want to delete this subject?')) return;
    
    try {
        await deleteData('subjects.php', { subject_id: subjectId });
        alert('Subject deleted successfully!');
        loadSubjects();
        loadDashboard();
    } catch (error) {
        alert('Error deleting subject: ' + error.message);
    }
}

// Exams Management
async function loadExams() {
    try {
        const examDate = document.getElementById('filter-exam-date').value;
        let url = 'exams.php';
        if (examDate) url += `?date=${examDate}`;
        
        exams = await fetchData(url);
        const tbody = document.querySelector('#exams-table tbody');
        tbody.innerHTML = '';
        
        exams.forEach(exam => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${exam.exam_id}</td>
                <td>${exam.exam_date}</td>
                <td>Shift ${exam.shift_number} (${exam.shift_time})</td>
                <td>${exam.subject_name}</td>
                <td>${exam.dept_name}</td>
                <td>${exam.stage_name}</td>
                <td>${exam.is_evening ? 'Yes' : 'No'}</td>
                <td>
                    <button class="btn btn-secondary" onclick="editExam(${exam.exam_id})">Edit</button>
                    <button class="btn btn-danger" onclick="deleteExam(${exam.exam_id})">Delete</button>
                </td>
            `;
            tbody.appendChild(row);
        });
    } catch (error) {
        alert('Error loading exams: ' + error.message);
    }
}
function filterSubjects() {
    const filterValue = (document.getElementById('subject-filter')?.value || "").toLowerCase();
    const select = document.getElementById('exam-subject');

    Array.from(select.options).forEach(opt => {
        const text = opt.textContent.toLowerCase();
        opt.style.display = text.includes(filterValue) ? "" : "none";
    });
}
async function loadSubjectsForExam() {
    const examSubjectSelect = document.getElementById('exam-subject');
    examSubjectSelect.innerHTML = '<option value="">Select Subject</option>';
    
    try {
        const subjectsData = await fetchData('subjects.php');
        subjectsData.forEach(subject => {
            const option = document.createElement('option');
            option.value = subject.subject_id;
            option.textContent = `${subject.subject_name} (${subject.dept_name} - ${subject.stage_name})`;
            examSubjectSelect.appendChild(option);
        });
    } catch (error) {
        console.error('Error loading subjects:', error);
    }
}

// function openExamModal(examId = null) {
//     document.getElementById('exam-id').value = examId || '';
//     document.getElementById('exam-date').value = '';
//     document.getElementById('exam-shift').value = '';
//     document.getElementById('exam-modal-title').textContent = examId ? 'Edit Exam' : 'Add Exam';
    
//     loadSubjectsForExam();
    
//     if (examId) {
//         const exam = exams.find(e => e.exam_id == examId);
//         if (exam) {
//             document.getElementById('exam-date').value = exam.exam_date;
//             document.getElementById('exam-shift').value = exam.shift_id;
//             setTimeout(() => {
//                 document.getElementById('exam-subject').value = exam.subject_id;
//             }, 100);
//         }
//     }
    
//     document.getElementById('exam-modal').style.display = 'block';
// }
function openExamModal(examId = null) {
    document.getElementById('exam-id').value = examId || '';
    document.getElementById('exam-date').value = '';
    document.getElementById('exam-shift').value = '';
    document.getElementById('exam-modal-title').textContent = examId ? 'Edit Exam' : 'Add Exam';

    // --- Add filter input above subject dropdown (JS ONLY) ---
    const subjectSelect = document.getElementById('exam-subject');
    let filterInput = document.getElementById('subject-filter');

    if (!filterInput) {
        filterInput = document.createElement('input');
        filterInput.id = "subject-filter";
        filterInput.placeholder = "Search subject...";
        filterInput.style.margin = "5px 0";

        // Insert filter input before subject dropdown
        subjectSelect.parentNode.insertBefore(filterInput, subjectSelect);

        // Add filter event
        filterInput.addEventListener('input', filterSubjects);
    }

    // Load subjects
    loadSubjectsForExam().then(() => {
        if (examId) {
            const exam = exams.find(e => e.exam_id == examId);
            if (exam) {
                document.getElementById('exam-date').value = exam.exam_date;
                document.getElementById('exam-shift').value = exam.shift_id;

                setTimeout(() => {
                    document.getElementById('exam-subject').value = exam.subject_id;
                }, 100);
            }
        }
    });

    document.getElementById('exam-modal').style.display = 'block';
}

async function saveExam(e) {
    e.preventDefault();
    
    const examId = document.getElementById('exam-id').value;
    const examDate = document.getElementById('exam-date').value;
    const shiftId = document.getElementById('exam-shift').value;
    const subjectId = document.getElementById('exam-subject').value;
    
    const data = {
        exam_date: examDate,
        shift_id: parseInt(shiftId),
        subject_id: parseInt(subjectId)
    };
    
    try {
        if (examId) {
            data.exam_id = parseInt(examId);
            await putData('exams.php', data);
            alert('Exam updated successfully!');
        } else {
            await postData('exams.php', data);
            alert('Exam created successfully!');
        }
        
        closeModal('exam-modal');
        loadExams();
        loadDashboard();
    } catch (error) {
        alert('Error saving exam: ' + error.message);
    }
}

function editExam(examId) {
    openExamModal(examId);
}

async function deleteExam(examId) {
    if (!confirm('Are you sure you want to delete this exam?')) return;
    
    try {
        await deleteData('exams.php', { exam_id: examId });
        alert('Exam deleted successfully!');
        loadExams();
        loadDashboard();
    } catch (error) {
        alert('Error deleting exam: ' + error.message);
    }
}

// Assignments
async function loadAssignments() {
    const date = document.getElementById('assignment-date').value;
    const shiftId = document.getElementById('assignment-shift').value;
    
    if (!date) return;
    
    try {
        let url = `assignments.php?date=${date}`;
        if (shiftId) url += `&shift_id=${shiftId}`;
        
        const assignments = await fetchData(url);
        displayAssignments(assignments);
    } catch (error) {
        console.error('Error loading assignments:', error);
    }
}

function displayAssignments(assignments) {
    const tbody = document.querySelector('#assignments-table tbody');
    tbody.innerHTML = '';
    
    // Group by section
    const sections = {};
    assignments.forEach(assignment => {
        if (!sections[assignment.section_number]) {
            sections[assignment.section_number] = [];
        }
        sections[assignment.section_number].push(assignment);
    });
    
    Object.keys(sections).sort((a, b) => a - b).forEach(sectionNum => {
        const sectionAssignments = sections[sectionNum];
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>Shift ${sectionAssignments[0].shift_number}</td>
            <td>Section ${sectionNum}</td>
            <td>${sectionAssignments[0]?.teacher_name || 'N/A'}</td>
            <td>${sectionAssignments[1]?.teacher_name || 'N/A'}</td>
        `;
        tbody.appendChild(row);
    });
}

async function generateAssignments() {
    const date = document.getElementById('assignment-date').value;
    const shiftId = document.getElementById('assignment-shift').value;
    
    if (!date || !shiftId) {
        alert('Please select both date and shift');
        return;
    }
    
    // First, check if exams exist for this date and shift
    try {
        const examsData = await fetchData(`exams.php?date=${date}&shift_id=${shiftId}`);
        
        if (!examsData || examsData.length === 0) {
            // Get shift number for better message
            const shift = shifts.find(s => s.shift_id == shiftId);
            const shiftNumber = shift ? shift.shift_number : shiftId;
            const shiftTime = shift ? shift.shift_time : '';
            
            alert(`Cannot assign observers: No exams scheduled for ${date} in Shift ${shiftNumber}${shiftTime ? ' (' + shiftTime + ')' : ''}.\n\nPlease create exams for this date and shift first.`);
            document.getElementById('assignment-result').innerHTML = 
                `<div class="error">No exams found for this date and shift. Please create exams first.</div>`;
            return;
        }
        
        // Exams exist, proceed with assignment generation
        const result = await postData('assignments.php', {
            exam_date: date,
            shift_id: parseInt(shiftId)
        });
        
        document.getElementById('assignment-result').innerHTML = 
            `<div class="success">${result.message || 'Assignments generated successfully!'}</div>`;
        
        loadAssignments();
    } catch (error) {
        document.getElementById('assignment-result').innerHTML = 
            `<div class="error">${error.message}</div>`;
    }
}

async function deleteAssignments() {
    const date = document.getElementById('assignment-date').value;
    const shiftId = document.getElementById('assignment-shift').value;
    
    if (!date || !shiftId) {
        alert('Please select both date and shift');
        return;
    }
    
    if (!confirm('Are you sure you want to delete all assignments for this shift?')) return;
    
    try {
        await deleteData('assignments.php', {
            exam_date: date,
            shift_id: parseInt(shiftId)
        });
        alert('Assignments deleted successfully!');
        loadAssignments();
    } catch (error) {
        alert('Error deleting assignments: ' + error.message);
    }
}

function exportAssignments() {
    const date = document.getElementById('assignment-date').value;
    if (!date) {
        alert('Please select a date');
        return;
    }
    
    // Implementation for CSV export
    alert('Export functionality will be implemented');
}

function printAssignments() {
    window.print();
}

async function exportAssignmentsExcel() {
    const date = document.getElementById('assignment-date').value;
    const shiftId = document.getElementById('assignment-shift').value;

    if (!date || !shiftId) {
        alert('Please select both date and shift');
        return;
    }

    try {
        // Load template.xlsx from same folder
        const resp = await fetch('./template.xlsx');
        if (!resp.ok) throw new Error('Could not load template.xlsx');
        const arrayBuffer = await resp.arrayBuffer();
        const workbook = XLSX.read(arrayBuffer, { type: "array" });

        // --- OBSERVERS SHEET ---
        const ws = workbook.Sheets[workbook.SheetNames[0]];

        // Write date and shift number while keeping styles
        ws["C9"]  = { ...ws["C9"], v: date };
        ws["C11"] = { ...ws["C11"], v: shiftId };

        // Fetch assignments and exams (JSON expected)
        const [assignments, examsData] = await Promise.all([
            fetchData(`assignments.php?date=${date}&shift_id=${shiftId}`),
            fetchData(`exams.php?date=${date}&shift_id=${shiftId}`)
        ]);

        // Build sections map
        const sections = {};
        assignments.forEach(a => {
            if (!sections[a.section_number]) sections[a.section_number] = [];
            sections[a.section_number].push(a);
        });

        // Fill observers table starting at B13
        let currentRow = 13;
        Object.keys(sections).sort((a,b)=>a-b).forEach(sectionNum=>{
            const [o1,o2] = sections[sectionNum];

            ws[`B${currentRow}`] = { ...ws[`B${currentRow}`], v: sectionNum };
            ws[`C${currentRow}`] = { ...ws[`C${currentRow}`], v: o1?.teacher_name || "-" };
            ws[`D${currentRow}`] = { ...ws[`D${currentRow}`], v: o2?.teacher_name || "-" };

            currentRow++;
        });

        // --- EXAMS SHEET ---
        let examsSheet;
        if (workbook.SheetNames.length > 1) {
            examsSheet = workbook.Sheets[workbook.SheetNames[1]];
        } else {
            examsSheet = XLSX.utils.aoa_to_sheet([]);
            XLSX.utils.book_append_sheet(workbook, examsSheet, "Exams");
        }

        // Fill exams starting from row 2 (assuming headers in row 1)
        let row = 2;
        examsData.forEach(exam=>{
            examsSheet[`A${row}`] = { ...examsSheet[`A${row}`], v: exam.stage_name || "-" };
            examsSheet[`B${row}`] = { ...examsSheet[`B${row}`], v: exam.subject_name || "-" };
            examsSheet[`C${row}`] = { ...examsSheet[`C${row}`], v: exam.teacher_names?.join(", ") || "-" };
            row++;
        });

        // Export final file
        XLSX.writeFile(workbook, `assignments_${date}_shift${shiftId}.xlsx`);

    } catch (err) {
        console.error("Excel export error:", err);
        alert("Error exporting Excel: " + err.message);
    }
}

async function exportAssignmentsExcel2() {
    const date = document.getElementById('assignment-date').value;
    const shiftId = document.getElementById('assignment-shift').value;

    if (!date || !shiftId) {
        alert('Please select both date and shift');
        return;
    }

    try {
        const [assignments, examsData] = await Promise.all([
            fetchData(`assignments.php?date=${date}&shift_id=${shiftId}`),
            fetchData(`exams.php?date=${date}&shift_id=${shiftId}`)
        ]);

        // Build sections map
        const sections = {};
        assignments.forEach(a => {
            if (!sections[a.section_number]) sections[a.section_number] = [];
            sections[a.section_number].push(a);
        });

        // Prepare Observers sheet
        const observersSheet = [
            ["Section", "Observer 1", "Observer 2"]
        ];

        Object.keys(sections).sort((a, b) => a - b).forEach(sectionNum => {
            const [o1, o2] = sections[sectionNum];
            observersSheet.push([
                sectionNum,
                o1 ? o1.teacher_name : "-",
                o2 ? o2.teacher_name : "-"
            ]);
        });

        // Prepare Exams sheet
        const examsSheet = [
            ["Stage", "Subject", "Teachers"]
        ];

        examsData.forEach(exam => {
            examsSheet.push([
                exam.stage_name || "-",
                exam.subject_name || "-",
                exam.teacher_names && exam.teacher_names.length ? exam.teacher_names.join(", ") : "-"
            ]);
        });

        // Create Workbook
        const wb = XLSX.utils.book_new();

        // Convert sheets
        const observersWS = XLSX.utils.aoa_to_sheet(observersSheet);
        const examsWS = XLSX.utils.aoa_to_sheet(examsSheet);

        // Add sheets to workbook
        XLSX.utils.book_append_sheet(wb, observersWS, "Observers");
        XLSX.utils.book_append_sheet(wb, examsWS, "Exams");

        // Export as real XLSX file
        XLSX.writeFile(wb, `assignments_${date}_shift${shiftId}.xlsx`);

    } catch (error) {
        console.error("Excel Export Error:", error);
        alert("Error exporting Excel: " + error.message);
    }
}


// Export assignments to PDF (via print dialog) with logo and exam details
async function exportAssignmentsPdf() {
    const date = document.getElementById('assignment-date').value;
    const shiftId = document.getElementById('assignment-shift').value;
    
    if (!date || !shiftId) {
        alert('Please select both date and shift');
        return;
    }
    
    try {
        // Fetch assignments and exams for the selected date/shift
        const [assignments, examsData] = await Promise.all([
            fetchData(`assignments.php?date=${date}&shift_id=${shiftId}`),
            fetchData(`exams.php?date=${date}&shift_id=${shiftId}`)
        ]);
        
        const shift = shifts.find(s => s.shift_id == shiftId) || {};
        const shiftLabel = shift.shift_number ? `گەرا ${shift.shift_number}` : `گەرا ${shiftId}`;
        const shiftTime = shift.shift_time || '';
        
        // Build observers table grouped by section
        const sections = {};
        assignments.forEach(a => {
            if (!sections[a.section_number]) sections[a.section_number] = [];
            sections[a.section_number].push(a);
        });
        
        let observersRows = '';
        Object.keys(sections).sort((a, b) => a - b).forEach(sectionNum => {
            const [o1, o2] = sections[sectionNum];
            observersRows += `
                <tr>
                    <td>${sectionNum}</td>
                    <td>${o1 ? o1.teacher_name : '-'}</td>
                    <td>${o2 ? o2.teacher_name : '-'}</td>
                </tr>
            `;
        });
        
        // Build exams table (stage, subject, teachers)
        let examsRows = '';
        examsData.forEach(exam => {
            const teachersList = exam.teacher_names && exam.teacher_names.length
                ? exam.teacher_names.join(', ')
                : '-';
            examsRows += `
                <tr>
                    <td>${exam.stage_name || '-'}</td>
                    <td>${exam.subject_name || '-'}</td>
                    <td>${teachersList}</td>
                </tr>
            `;
        });
        
        // Simple inline SVG logo (placeholder). Replace href with real logo if available.
        const logoSvg = 'data:image/svg+xml;utf8,' + encodeURIComponent(`
            <svg xmlns="http://www.w3.org/2000/svg" width="140" height="40" viewBox="0 0 140 40">
              <rect rx="8" ry="8" width="140" height="40" fill="#667eea"/>
              <text x="70" y="25" font-size="16" font-family="Arial" fill="white" text-anchor="middle">Institute</text>
            </svg>
        `);
        
        html = `
    <!DOCTYPE html>
    <html lang="ku" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <title>Observers - ${date} ${shiftLabel}</title>
        <style>
            @page { size: A4; margin: 10mm; }
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                color: #000;
                margin: 0;
                padding: 10px;
                text-align: right;
            }
            .header {
                text-align: center;
                margin-bottom: 20px;
            }
            .logo-container {
                margin: 0 auto;
                width: 120px; /* Size for the logo container */
                height: 90px;
            }
            .logo-container img {
                height: 100%; 
                width: 100%; 
                object-fit: contain;
            }
            .brand-english {
                font-family: 'Times New Roman', Times, serif;
                text-transform: uppercase;
                line-height: 1.2;
            }
            .brand-name {
                font-size: 32px;
                font-weight: bold;
                letter-spacing: 1px;
                margin: 0;
            }
            .brand-sub {
                font-size: 14px;
                letter-spacing: 4px;
                margin: 0;
            }
            .kurdish-titles {
                margin-top: 10px;
                font-weight: bold;
            }
            .kurdish-main {
                font-size: 18px;
                margin: 2px 0;
            }
            .kurdish-sub {
                font-size: 14px;
                margin: 2px 0;
                font-weight: normal;
            }
            .report-title {
                font-size: 18px;
                text-decoration: underline;
                margin-top: 15px;
                font-weight: bold;
            }
            
            /* Info Row (Date / Shift) - Replaces .meta */
            .info-row {
                display: flex;
                justify-content: space-between;
                width: 90%;
                margin: 20px auto 10px auto;
                font-weight: bold;
                font-size: 16px;
                padding-bottom: 5px;
            }
            
            /* Table Styling */
            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 5px;
            }
            th, td {
                border: 1px solid #000; /* Black solid border for PDF look */
                padding: 6px 8px;
                text-align: right; /* RTL alignment */
                font-size: 14px;
            }
            th {
                background-color: #f2f2f2; /* Light gray header */
                text-align: center;
                font-weight: bold;
            }
            .section-cell {
                text-align: center;
                width: 50px;
                font-weight: bold;
            }
            
            @media print {
                /* Ensure background color prints */
                th { background-color: #f2f2f2 !important; -webkit-print-color-adjust: exact; }
                body { margin: 10mm; }
            }
        </style>
    </head>
    <body>
        <div class="header">
            <div class="logo-container">
                <img src="${logoSvg}" alt="Institute Logo" />
            </div>
            <div class="brand-english">
                <div class="brand-name">RAND</div>
                <div class="brand-sub">INSTITUTE</div>
            </div>
            <div class="kurdish-titles">
                <div class="kurdish-main">پەیمانگەها رەند یا ناحکومی</div>
                <div class="kurdish-sub">ئەزمونێن تیوری وەرزی ئێکی سالا خواندنی (٢٠٢٥ - ٢٠٢٦)</div>
                <div class="report-title">چاقدێرێن ئەزمونان</div>
            </div>
        </div>

        <div class="info-row">
            <span>${shiftLabel}${shiftTime ? ' (' + shiftTime + ')' : ''}</span>
            <span>بەرواری: ${date}</span>
        </div>

        <table>
            <thead>
                <tr>
                    <th>کەرت</th>
                    <th>چاقدێر</th>
                    <th>چاقدێر</th>
                </tr>
            </thead>
            <tbody>
                ${observersRows || '<tr><td colspan="3" style="text-align:center;">No assignments</td></tr>'}
            </tbody>
        </table>
        
    </body>
    </html>
`;
        
        const win = window.open('', '_blank');
        if (!win) {
            alert('Popup blocked. Please allow popups for this site to export PDF.');
            return;
        }
        win.document.write(html);
        win.document.close();
        win.focus();
        win.print();
    } catch (error) {
        console.error('Error exporting PDF:', error);
        alert('Error exporting PDF: ' + error.message);
    }
}
async function exportAssignmentsPdf2() {
    const date = document.getElementById('assignment-date').value;
    const shiftId = document.getElementById('assignment-shift').value;
    
    if (!date || !shiftId) {
        alert('Please select both date and shift');
        return;
    }
    
    try {
        // Fetch assignments and exams for the selected date/shift
        const [assignments, examsData] = await Promise.all([
            fetchData(`assignments.php?date=${date}&shift_id=${shiftId}`),
            fetchData(`exams.php?date=${date}&shift_id=${shiftId}`)
        ]);
        
        const shift = shifts.find(s => s.shift_id == shiftId) || {};
        const shiftLabel = shift.shift_number ? `گەرا ${shift.shift_number}` : `گەرا ${shiftId}`;
        const shiftTime = shift.shift_time || '';
        
        // Build observers table grouped by section
        const sections = {};
        assignments.forEach(a => {
            if (!sections[a.section_number]) sections[a.section_number] = [];
            sections[a.section_number].push(a);
        });
        
        let observersRows = '';
        Object.keys(sections).sort((a, b) => a - b).forEach(sectionNum => {
            const [o1, o2] = sections[sectionNum];
            observersRows += `
                <tr>
                    <td>${sectionNum}</td>
                    <td>${o1 ? o1.teacher_name : '-'}</td>
                    <td>${o2 ? o2.teacher_name : '-'}</td>
                </tr>
            `;
        });
        
        // Build exams table (stage, subject, teachers)
        let examsRows = '';
        examsData.forEach(exam => {
            const teachersList = exam.teacher_names && exam.teacher_names.length
                ? exam.teacher_names.join(', ')
                : '-';
            examsRows += `
                <tr>
                    <td>${exam.stage_name || '-'}</td>
                    <td>${exam.subject_name || '-'}</td>
                    <td>${teachersList}</td>
                </tr>
            `;
        });
        
        // Simple inline SVG logo (placeholder). Replace href with real logo if available.
        const logoSvg = 'data:image/svg+xml;utf8,' + encodeURIComponent(`
            <svg xmlns="http://www.w3.org/2000/svg" width="140" height="40" viewBox="0 0 140 40">
              <rect rx="8" ry="8" width="140" height="40" fill="#667eea"/>
              <text x="70" y="25" font-size="16" font-family="Arial" fill="white" text-anchor="middle">Institute</text>
            </svg>
        `);
        
        const html = `
            <html>
            <head>
                <title>Observers - ${date} ${shiftLabel}</title>
                <style>
                    body { font-family: 'Segoe UI', Arial, sans-serif; margin: 24px; color: #111; }
                    .header { display: flex; align-items: center; gap: 16px; margin-bottom: 12px; }
                    .header img { height: 40px; }
                    .title { font-size: 22px; font-weight: 700; margin: 0; }
                    .subtitle { margin: 4px 0 0 0; color: #444; }
                    .meta { margin: 16px 0; font-size: 14px; }
                    table { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
                    th, td { border: 1px solid #ddd; padding: 8px; font-size: 13px; }
                    th { background: #f3f4f6; text-align: left; }
                    .section-title { font-size: 16px; font-weight: 700; margin: 16px 0 8px; }
                    @media print { body { margin: 16px; } }
                </style>
            </head>
            <body>
                <div class="header">
                    <img src="${logoSvg}" alt="Institute Logo" />
                    <div>
                        <p class="title">چاڤدێرێن کەرتان</p>
                        <p class="subtitle">${shiftLabel}${shiftTime ? ' · ' + shiftTime : ''}</p>
                    </div>
                </div>
                <div class="meta">
                    <div><strong>Date:</strong> ${date}</div>
                    <div><strong>Shift:</strong> ${shiftLabel}${shiftTime ? ' (' + shiftTime + ')' : ''}</div>
                </div>
                
                <div class="section-title">چاڤدێرێن کەرتان</div>
                <table>
                    <thead>
                        <tr>
                            <th>کەرت</th>
                            <th> چاڤدێر</th>
                            <th> چاڤدێر</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${observersRows || '<tr><td colspan="3">No assignments</td></tr>'}
                    </tbody>
                </table>
                

            </body>
            </html>
        `;
        
        const win = window.open('', '_blank');
        if (!win) {
            alert('Popup blocked. Please allow popups for this site to export PDF.');
            return;
        }
        win.document.write(html);
        win.document.close();
        win.focus();
        win.print();
    } catch (error) {
        console.error('Error exporting PDF:', error);
        alert('Error exporting PDF: ' + error.message);
    }
}
 // Function definition (Pure JavaScript)
 
// Exclusions
async function loadExclusions() {
    try {
        const exclusions = await fetchData('manual_exclusions.php');
        const tbody = document.querySelector('#exclusions-table tbody');
        tbody.innerHTML = '';
        
        exclusions.forEach(exclusion => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${exclusion.exclusion_id}</td>
                <td>${exclusion.teacher_name}</td>
                <td>${exclusion.exclusion_date}</td>
                <td>Shift ${exclusion.shift_number}</td>
                <td>${exclusion.reason || '-'}</td>
                <td>
                    <button class="btn btn-danger" onclick="deleteExclusion(${exclusion.exclusion_id})">Delete</button>
                </td>
            `;
            tbody.appendChild(row);
        });
    } catch (error) {
        alert('Error loading exclusions: ' + error.message);
    }
}

function openExclusionModal() {
    document.getElementById('exclusion-id').value = '';
    document.getElementById('exclusion-teacher').innerHTML = '<option value="">Select Teacher</option>';
    document.getElementById('exclusion-date').value = '';
    document.getElementById('exclusion-shift').value = '';
    document.getElementById('exclusion-reason').value = '';
    
    // Populate teachers
    teachers.forEach(teacher => {
        const option = document.createElement('option');
        option.value = teacher.teacher_id;
        option.textContent = teacher.teacher_name;
        document.getElementById('exclusion-teacher').appendChild(option);
    });
    
    document.getElementById('exclusion-modal').style.display = 'block';
}

async function saveExclusion(e) {
    e.preventDefault();
    
    const data = {
        teacher_id: parseInt(document.getElementById('exclusion-teacher').value),
        exclusion_date: document.getElementById('exclusion-date').value,
        shift_id: parseInt(document.getElementById('exclusion-shift').value),
        reason: document.getElementById('exclusion-reason').value
    };
    
    try {
        await postData('manual_exclusions.php', data);
        alert('Exclusion created successfully!');
        closeModal('exclusion-modal');
        loadExclusions();
    } catch (error) {
        alert('Error saving exclusion: ' + error.message);
    }
}

async function deleteExclusion(exclusionId) {
    if (!confirm('Are you sure you want to delete this exclusion?')) return;
    
    try {
        await deleteData('manual_exclusions.php', { exclusion_id: exclusionId });
        alert('Exclusion deleted successfully!');
        loadExclusions();
    } catch (error) {
        alert('Error deleting exclusion: ' + error.message);
    }
}

// History
async function loadHistory() {
    try {
        const date = document.getElementById('history-date').value;
        const shiftId = document.getElementById('history-shift').value;
        
        let url = 'history.php';
        if (date) url += `?date=${date}`;
        if (shiftId) url += date ? `&shift_id=${shiftId}` : `?shift_id=${shiftId}`;
        
        const history = await fetchData(url);
        const tbody = document.querySelector('#history-table tbody');
        tbody.innerHTML = '';
        
        history.forEach(record => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${record.exam_date}</td>
                <td>Shift ${record.shift_number}</td>
                <td>Section ${record.section_number}</td>
                <td>${record.teacher_name}</td>
                <td>${record.action_type}</td>
                <td>${new Date(record.created_at).toLocaleString()}</td>
            `;
            tbody.appendChild(row);
        });
    } catch (error) {
        console.error('Error loading history:', error);
    }
}

function loadShifts() {
    // Shifts are already loaded in loadInitialData
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}

