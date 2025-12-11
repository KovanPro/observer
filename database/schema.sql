-- Exam Observer Assignment Management System Database Schema

-- Create database
CREATE DATABASE IF NOT EXISTS exam_observer_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE exam_observer_system;

-- Departments table
CREATE TABLE departments (
    dept_id INT PRIMARY KEY AUTO_INCREMENT,
    dept_name VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Insert default departments
INSERT INTO departments (dept_name) VALUES 
('Computer'),
('Business Administration'),
('Accounting'),
('Petrol');

-- Stages table
CREATE TABLE stages (
    stage_id INT PRIMARY KEY AUTO_INCREMENT,
    dept_id INT NOT NULL,
    stage_name VARCHAR(100) NOT NULL,
    stage_number INT NOT NULL,
    is_evening BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (dept_id) REFERENCES departments(dept_id) ON DELETE CASCADE,
    UNIQUE KEY unique_stage (dept_id, stage_name)
) ENGINE=InnoDB;

-- Insert stages for Computer Department (9 stages)
INSERT INTO stages (dept_id, stage_name, stage_number, is_evening) VALUES
(1, 'Stage 1', 1, FALSE),
(1, 'Stage 2', 2, FALSE),
(1, 'Stage 3', 3, FALSE),
(1, 'Stage 4 Program', 4, FALSE),
(1, 'Stage 4 Network', 4, FALSE),
(1, 'Stage 4 Web', 4, FALSE),
(1, 'Stage 5 Program', 5, FALSE),
(1, 'Stage 5 Network', 5, FALSE),
(1, 'Stage 5 Web', 5, FALSE),
(1, 'Stage 1 Evening', 1, TRUE),
(1, 'Stage 2 Evening', 2, TRUE);

-- Insert stages for Business Administration (5 stages + evening)
INSERT INTO stages (dept_id, stage_name, stage_number, is_evening) VALUES
(2, 'Stage 1', 1, FALSE),
(2, 'Stage 2', 2, FALSE),
(2, 'Stage 3', 3, FALSE),
(2, 'Stage 4', 4, FALSE),
(2, 'Stage 5', 5, FALSE),
(2, 'Stage 1 Evening', 1, TRUE),
(2, 'Stage 2 Evening', 2, TRUE),
(2, 'Stage 3 Evening', 3, TRUE),
(2, 'Stage 4 Evening', 4, TRUE),
(2, 'Stage 5 Evening', 5, TRUE);

-- Insert stages for Accounting (5 stages, no evening)
INSERT INTO stages (dept_id, stage_name, stage_number, is_evening) VALUES
(3, 'Stage 1', 1, FALSE),
(3, 'Stage 2', 2, FALSE),
(3, 'Stage 3', 3, FALSE),
(3, 'Stage 4', 4, FALSE),
(3, 'Stage 5', 5, FALSE);

-- Insert stages for Petrol (5 stages, no evening)
INSERT INTO stages (dept_id, stage_name, stage_number, is_evening) VALUES
(4, 'Stage 1', 1, FALSE),
(4, 'Stage 2', 2, FALSE),
(4, 'Stage 3', 3, FALSE),
(4, 'Stage 4', 4, FALSE),
(4, 'Stage 5', 5, FALSE);

-- Teachers table
CREATE TABLE teachers (
    teacher_id INT PRIMARY KEY AUTO_INCREMENT,
    teacher_name VARCHAR(200) NOT NULL,
    is_available BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Teacher-Department relationship (many-to-many)
CREATE TABLE teacher_department (
    id INT PRIMARY KEY AUTO_INCREMENT,
    teacher_id INT NOT NULL,
    dept_id INT NOT NULL,
    FOREIGN KEY (teacher_id) REFERENCES teachers(teacher_id) ON DELETE CASCADE,
    FOREIGN KEY (dept_id) REFERENCES departments(dept_id) ON DELETE CASCADE,
    UNIQUE KEY unique_teacher_dept (teacher_id, dept_id)
) ENGINE=InnoDB;

-- Subjects table
CREATE TABLE subjects (
    subject_id INT PRIMARY KEY AUTO_INCREMENT,
    subject_name VARCHAR(200) NOT NULL,
    dept_id INT NOT NULL,
    stage_id INT NOT NULL,
    FOREIGN KEY (dept_id) REFERENCES departments(dept_id) ON DELETE CASCADE,
    FOREIGN KEY (stage_id) REFERENCES stages(stage_id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Subject-Teacher relationship (many-to-many - supports multiple teachers per subject)
CREATE TABLE subject_teacher (
    id INT PRIMARY KEY AUTO_INCREMENT,
    subject_id INT NOT NULL,
    teacher_id INT NOT NULL,
    FOREIGN KEY (subject_id) REFERENCES subjects(subject_id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES teachers(teacher_id) ON DELETE CASCADE,
    UNIQUE KEY unique_subject_teacher (subject_id, teacher_id)
) ENGINE=InnoDB;

-- Exam shifts configuration
CREATE TABLE exam_shifts (
    shift_id INT PRIMARY KEY AUTO_INCREMENT,
    shift_number INT NOT NULL UNIQUE,
    shift_time TIME NOT NULL,
    sections_count INT NOT NULL,
    description TEXT
) ENGINE=InnoDB;

-- Insert default shifts
INSERT INTO exam_shifts (shift_number, shift_time, sections_count, description) VALUES
(1, '09:00:00', 20, 'Stage 1 (morning + evening) - All departments'),
(2, '10:30:00', 20, 'Stage 2 (morning + evening) - All departments'),
(3, '12:00:00', 17, 'Stage 3 - All departments'),
(4, '13:30:00', 21, 'Stages 4 & 5 - All departments'),
(5, '15:00:00', 10, 'All evening stages - Computer + Business only');

-- Sections table (to track sections per shift)
CREATE TABLE sections (
    section_id INT PRIMARY KEY AUTO_INCREMENT,
    shift_id INT NOT NULL,
    section_number INT NOT NULL,
    FOREIGN KEY (shift_id) REFERENCES exam_shifts(shift_id) ON DELETE CASCADE,
    UNIQUE KEY unique_section (shift_id, section_number)
) ENGINE=InnoDB;

-- Exams table
CREATE TABLE exams (
    exam_id INT PRIMARY KEY AUTO_INCREMENT,
    exam_date DATE NOT NULL,
    shift_id INT NOT NULL,
    subject_id INT NOT NULL,
    dept_id INT NOT NULL,
    stage_id INT NOT NULL,
    is_evening BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (shift_id) REFERENCES exam_shifts(shift_id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(subject_id) ON DELETE CASCADE,
    FOREIGN KEY (dept_id) REFERENCES departments(dept_id) ON DELETE CASCADE,
    FOREIGN KEY (stage_id) REFERENCES stages(stage_id) ON DELETE CASCADE,
    INDEX idx_exam_date_shift (exam_date, shift_id)
) ENGINE=InnoDB;

-- Manual exclusions table (manager-set exclusions)
CREATE TABLE manual_exclusions (
    exclusion_id INT PRIMARY KEY AUTO_INCREMENT,
    teacher_id INT NOT NULL,
    exclusion_date DATE NOT NULL,
    shift_id INT NOT NULL,
    reason TEXT,
    created_by VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(teacher_id) ON DELETE CASCADE,
    FOREIGN KEY (shift_id) REFERENCES exam_shifts(shift_id) ON DELETE CASCADE,
    UNIQUE KEY unique_exclusion (teacher_id, exclusion_date, shift_id)
) ENGINE=InnoDB;

-- Observer assignments table (current assignments)
CREATE TABLE observer_assignments (
    assignment_id INT PRIMARY KEY AUTO_INCREMENT,
    exam_date DATE NOT NULL,
    shift_id INT NOT NULL,
    section_id INT NOT NULL,
    teacher_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (shift_id) REFERENCES exam_shifts(shift_id) ON DELETE CASCADE,
    FOREIGN KEY (section_id) REFERENCES sections(section_id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES teachers(teacher_id) ON DELETE CASCADE,
    UNIQUE KEY unique_assignment (exam_date, shift_id, section_id, teacher_id),
    INDEX idx_date_shift (exam_date, shift_id)
) ENGINE=InnoDB;

-- Observer history table (for tracking past assignments)
CREATE TABLE observer_history (
    history_id INT PRIMARY KEY AUTO_INCREMENT,
    exam_date DATE NOT NULL,
    shift_id INT NOT NULL,
    section_id INT NOT NULL,
    teacher_id INT NOT NULL,
    assignment_id INT,
    action_type ENUM('assigned', 'removed', 'regenerated') DEFAULT 'assigned',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (shift_id) REFERENCES exam_shifts(shift_id) ON DELETE CASCADE,
    FOREIGN KEY (section_id) REFERENCES sections(section_id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES teachers(teacher_id) ON DELETE CASCADE,
    INDEX idx_date_shift (exam_date, shift_id)
) ENGINE=InnoDB;

