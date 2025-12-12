# Quick Start Guide

## Installation (5 Minutes)

1. **Ensure XAMPP is running** (Apache + MySQL)

2. **Copy files** to `C:\xampp\htdocs\obs\`

3. **Create database:**
   - Open phpMyAdmin: http://localhost/phpmyadmin
   - Click "Import" tab
   - Select `database/schema.sql`
   - Click "Go"
   - Database and all tables will be created automatically

4. **Verify installation:**
   - Visit: http://localhost/obs/install.php
   - Should show "✓ All tables exist"

5. **Access system:**
   - Visit: http://localhost/obs/
   - You should see the dashboard

## First Steps

### Step 1: Add Teachers
1. Click "Teachers" in navigation
2. Click "+ Add Teacher"
3. Enter name, select departments, set status
4. Save

### Step 2: Add Subjects
1. Click "Subjects" in navigation
2. Click "+ Add Subject"
3. Enter subject name
4. Select department and stage
5. Select one or more teachers
6. Save

### Step 3: Create Exams
1. Click "Exams" in navigation
2. Click "+ Add Exam"
3. Select date, shift, and subject
4. Save (evening status is auto-detected)

### Step 4: Generate Assignments
1. Click "Assignments" in navigation
2. Select exam date and shift
3. Click "Generate Assignments"
4. System will automatically:
   - Apply all exclusion rules
   - Assign 1 observer per section
   - Show results or error message

## Important Notes

- **Evening Students**: Automatically detected based on stage
- **Shift 5**: Dedicated to evening exams (3:00 PM)
- **Multi-Teacher Subjects**: All teachers are excluded from that shift
- **Manual Exclusions**: Override all automatic rules
- **History**: All assignments are tracked automatically

## Troubleshooting

**"Not enough teachers" error:**
- Add more teachers
- Check teacher availability
- Review exclusions
- Check exam conflicts

**Database connection error:**
- Check MySQL is running
- Verify credentials in `config/database.php`
- Ensure database exists

**Assignments not showing:**
- Verify exams exist for selected date
- Check shift selection
- Review exclusion rules

## System Requirements

- PHP 7.4+
- MySQL 5.7+
- Apache web server
- Modern web browser

## Support

See `README.md` for detailed documentation.

