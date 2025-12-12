# Exam Observer Assignment Management System

A comprehensive PHP + MySQL system for managing exam observer assignments with automatic exclusion rules, evening student support, and multi-teacher exam handling.

## Features

✅ **Complete Institute Structure**
- 4 Departments: Computer, Business Administration, Accounting, Petrol
- Multiple stages per department (Computer has 9 stages, others have 5)
- Evening student support for Computer and Business Administration

✅ **Exam Shift Management**
- 5 predefined shifts with specific times and section counts
- Automatic shift assignment based on stage and department
- Evening-only shift (Shift 5) for evening students

✅ **Teacher Management**
- Add/edit/delete teachers
- Assign teachers to multiple departments
- Track teacher availability
- Manual exclusions by managers

✅ **Subject Management**
- Link subjects to departments and stages
- Support for multiple teachers per subject
- Automatic evening detection

✅ **Exam Schedule Management**
- Create exam schedules with date, shift, and subject
- Automatic evening detection based on stage
- Support for multiple exams per day

✅ **Observer Assignment Algorithm**
- **Rule 1**: Exclude teachers with exams in current shift
- **Rule 2**: Exclude teachers with exams in next shift (shift + 1)
- **Rule 3**: Exclude all teachers from multi-teacher subjects
- **Rule 4**: Exclude manually excluded teachers
- **Rule 5**: Exclude teachers already assigned in same shift
- **Rule 6**: Special handling for evening students and Shift 5
- Random fair assignment with duplicate prevention

✅ **Additional Features**
- Assignment history tracking
- Export functionality
- Print observer lists
- Real-time validation and error handling

## Installation

### Prerequisites
- XAMPP (or any PHP/MySQL environment)
- PHP 7.4 or higher
- MySQL 5.7 or higher

### Setup Steps

1. **Copy Files**
   - Copy all files to your web server directory (e.g., `C:\xampp\htdocs\obs`)

2. **Database Configuration**
   - Open `config/database.php`
   - Update database credentials if needed:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_USER', 'root');
     define('DB_PASS', '');
     define('DB_NAME', 'exam_observer_system');
     ```

3. **Create Database**
   - Open phpMyAdmin (http://localhost/phpmyadmin)
   - Import `database/schema.sql` to create the database and all tables
   - Or run the SQL file directly in MySQL

4. **Access the System**
   - Open your browser and navigate to: `http://localhost/obs/`
   - The system should load with the dashboard

## Database Structure

The system includes the following tables:

- `departments` - Department information
- `stages` - Stage information per department (including evening stages)
- `teachers` - Teacher information
- `teacher_department` - Many-to-many relationship (teachers ↔ departments)
- `subjects` - Subject information
- `subject_teacher` - Many-to-many relationship (subjects ↔ teachers)
- `exam_shifts` - Shift configuration (5 shifts)
- `sections` - Sections per shift
- `exams` - Exam schedule
- `manual_exclusions` - Manager-set exclusions
- `observer_assignments` - Current observer assignments
- `observer_history` - Assignment history

## Usage Guide

### 1. Add Teachers
- Navigate to **Teachers** page
- Click **+ Add Teacher**
- Enter teacher name, select departments, set availability
- Save

### 2. Add Subjects
- Navigate to **Subjects** page
- Click **+ Add Subject**
- Enter subject name, select department and stage
- Select one or multiple teachers for the subject
- Save

### 3. Create Exam Schedule
- Navigate to **Exams** page
- Click **+ Add Exam**
- Select date, shift, and subject
- The system automatically determines department, stage, and evening status
- Save

### 4. Generate Observer Assignments
- Navigate to **Assignments** page
- Select exam date and shift
- Click **Generate Assignments**
- The system will:
  - Apply all exclusion rules
  - Randomly assign available teachers
  - Assign 1 observer per section
  - Display results or error if not enough teachers

### 5. Manual Exclusions
- Navigate to **Exclusions** page
- Click **+ Add Exclusion**
- Select teacher, date, shift, and reason
- This exclusion will override all automatic rules

### 6. View History
- Navigate to **History** page
- Filter by date and/or shift
- View all assignment changes (assigned, removed, regenerated)

## Exam Shifts Configuration

| Shift | Time | Sections | Description |
|-------|------|----------|-------------|
| Shift 1 | 9:00 AM | 20 | Stage 1 (morning + evening) - All departments |
| Shift 2 | 10:30 AM | 20 | Stage 2 (morning + evening) - All departments |
| Shift 3 | 12:00 PM | 17 | Stage 3 - All departments |
| Shift 4 | 1:30 PM | 21 | Stages 4 & 5 - All departments |
| Shift 5 | 3:00 PM | 10 | All evening stages - Computer + Business only |

## Exclusion Rules Explained

1. **Exam Conflict**: If a teacher has an exam in Shift X, they cannot observe Shift X or Shift X+1
2. **Multi-Teacher Exams**: If a subject has multiple teachers, ALL teachers are excluded from that shift and the next
3. **Manual Exclusion**: Manager-set exclusions override all other rules
4. **Already Assigned**: A teacher can only observe one section per shift
5. **Evening Logic**: Teachers with evening exams are excluded from Shift 5 (evening shift)

## API Endpoints

All API endpoints are in the `api/` directory:

- `teachers.php` - Teacher CRUD operations
- `subjects.php` - Subject CRUD operations
- `exams.php` - Exam CRUD operations
- `assignments.php` - Generate and manage observer assignments
- `manual_exclusions.php` - Manage manual exclusions
- `departments.php` - Get departments
- `stages.php` - Get stages
- `shifts.php` - Get shifts
- `history.php` - Get assignment history

## Troubleshooting

### Database Connection Error
- Check `config/database.php` credentials
- Ensure MySQL service is running
- Verify database exists

### Not Enough Teachers Error
- Add more teachers to the system
- Check teacher availability status
- Review exclusions and exam conflicts
- Ensure teachers are assigned to appropriate departments

### Assignment Not Generating
- Verify exams are created for the selected date
- Check that teachers exist and are available
- Review exclusion rules that might be blocking assignments

## File Structure

```
obs/
├── api/
│   ├── assignments.php
│   ├── departments.php
│   ├── exams.php
│   ├── history.php
│   ├── manual_exclusions.php
│   ├── response.php
│   ├── shifts.php
│   ├── stages.php
│   ├── subjects.php
│   └── teachers.php
├── assets/
│   ├── css/
│   │   └── style.css
│   └── js/
│       └── app.js
├── config/
│   └── database.php
├── database/
│   └── schema.sql
├── index.html
└── README.md
```

## Support

For issues or questions, please review the code comments in the API files and JavaScript files for detailed implementation notes.

## License

This system is provided as-is for educational and institutional use.

