-- BatangPortal Database Schema
-- Philippine Elementary School Portal

CREATE DATABASE IF NOT EXISTS batangportal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE batangportal;

-- ============================================================
-- USERS TABLE (Admin, Teacher, Parent/Guardian)
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','teacher','parent') NOT NULL DEFAULT 'parent',
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150),
    contact_number VARCHAR(20),
    address TEXT,
    profile_photo VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================================
-- SCHOOL INFO TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS school_info (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_name VARCHAR(200) NOT NULL,
    school_id VARCHAR(50),
    address TEXT,
    district VARCHAR(100),
    division VARCHAR(100),
    region VARCHAR(100),
    principal_name VARCHAR(150),
    contact_number VARCHAR(20),
    email VARCHAR(150),
    school_year VARCHAR(20),
    logo VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================================
-- GRADE LEVELS & SECTIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS grade_levels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    grade_name VARCHAR(50) NOT NULL,
    description VARCHAR(100)
);

CREATE TABLE IF NOT EXISTS sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    grade_level_id INT NOT NULL,
    section_name VARCHAR(50) NOT NULL,
    adviser_id INT,
    school_year VARCHAR(20),
    FOREIGN KEY (grade_level_id) REFERENCES grade_levels(id),
    FOREIGN KEY (adviser_id) REFERENCES users(id)
);

-- ============================================================
-- STUDENTS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lrn VARCHAR(12) UNIQUE,
    first_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100),
    last_name VARCHAR(100) NOT NULL,
    suffix VARCHAR(10),
    gender ENUM('Male','Female') NOT NULL,
    date_of_birth DATE NOT NULL,
    place_of_birth VARCHAR(200),
    nationality VARCHAR(50) DEFAULT 'Filipino',
    religion VARCHAR(100),
    address TEXT,
    barangay VARCHAR(100),
    municipality VARCHAR(100),
    province VARCHAR(100),
    zip_code VARCHAR(10),
    mother_tongue VARCHAR(50),
    ip_group VARCHAR(100),
    -- Parent/Guardian Info
    father_name VARCHAR(150),
    father_occupation VARCHAR(100),
    father_contact VARCHAR(20),
    mother_name VARCHAR(150),
    mother_occupation VARCHAR(100),
    mother_contact VARCHAR(20),
    guardian_name VARCHAR(150),
    guardian_relationship VARCHAR(50),
    guardian_contact VARCHAR(20),
    -- Enrollment Info
    section_id INT,
    enrollment_status ENUM('enrolled','transferred','dropped','graduated') DEFAULT 'enrolled',
    date_enrolled DATE,
    school_year VARCHAR(20),
    -- Health Info
    height_cm DECIMAL(5,2),
    weight_kg DECIMAL(5,2),
    blood_type VARCHAR(5),
    -- Linked parent account
    parent_user_id INT,
    profile_photo VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (section_id) REFERENCES sections(id),
    FOREIGN KEY (parent_user_id) REFERENCES users(id)
);

-- ============================================================
-- ACADEMIC RECORDS (Grades)
-- ============================================================
CREATE TABLE IF NOT EXISTS subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_name VARCHAR(100) NOT NULL,
    subject_code VARCHAR(20),
    grade_level_id INT,
    UNIQUE KEY unique_subject_per_grade (subject_name, grade_level_id),
    FOREIGN KEY (grade_level_id) REFERENCES grade_levels(id)
);

CREATE TABLE IF NOT EXISTS grades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    subject_id INT NOT NULL,
    school_year VARCHAR(20) NOT NULL,
    quarter TINYINT NOT NULL COMMENT '1=Q1, 2=Q2, 3=Q3, 4=Q4',
    grade DECIMAL(5,2),
    remarks VARCHAR(50),
    encoded_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (subject_id) REFERENCES subjects(id),
    FOREIGN KEY (encoded_by) REFERENCES users(id)
);

-- ============================================================
-- ATTENDANCE
-- ============================================================
CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    date DATE NOT NULL,
    status ENUM('present','absent','late','excused') NOT NULL,
    remarks VARCHAR(200),
    recorded_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (recorded_by) REFERENCES users(id)
);

-- ============================================================
-- DOCUMENT TYPES
-- ============================================================
CREATE TABLE IF NOT EXISTS document_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    doc_name VARCHAR(150) NOT NULL,
    doc_code VARCHAR(50),
    description TEXT,
    processing_days INT DEFAULT 3,
    fee DECIMAL(8,2) DEFAULT 0.00,
    requirements TEXT COMMENT 'JSON list of requirements',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- DOCUMENT REQUESTS
-- ============================================================
CREATE TABLE IF NOT EXISTS document_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_number VARCHAR(20) UNIQUE,
    student_id INT NOT NULL,
    requested_by INT NOT NULL COMMENT 'user_id of parent/guardian',
    document_type_id INT NOT NULL,
    purpose VARCHAR(255),
    copies INT DEFAULT 1,
    status ENUM('pending','processing','ready','released','rejected','cancelled') DEFAULT 'pending',
    priority ENUM('normal','urgent') DEFAULT 'normal',
    remarks TEXT,
    admin_notes TEXT,
    fee_amount DECIMAL(8,2) DEFAULT 0.00,
    payment_status ENUM('unpaid','paid','waived') DEFAULT 'unpaid',
    payment_reference VARCHAR(100),
    date_requested TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_processed TIMESTAMP NULL,
    date_released TIMESTAMP NULL,
    processed_by INT,
    released_by INT,
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (requested_by) REFERENCES users(id),
    FOREIGN KEY (document_type_id) REFERENCES document_types(id),
    FOREIGN KEY (processed_by) REFERENCES users(id),
    FOREIGN KEY (released_by) REFERENCES users(id)
);

-- ============================================================
-- SCHOOL DOCUMENTS (uploaded files)
-- ============================================================
CREATE TABLE IF NOT EXISTS school_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    category ENUM('memorandum','policy','form','report','announcement','other') DEFAULT 'other',
    description TEXT,
    file_path VARCHAR(255) NOT NULL,
    file_name VARCHAR(255),
    file_size INT,
    file_type VARCHAR(50),
    school_year VARCHAR(20),
    uploaded_by INT,
    is_public TINYINT(1) DEFAULT 0 COMMENT '1=visible to parents',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
);

-- ============================================================
-- STUDENT DOCUMENTS (uploaded per student)
-- ============================================================
CREATE TABLE IF NOT EXISTS student_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    doc_type VARCHAR(100) NOT NULL COMMENT 'e.g. Birth Certificate, Report Card',
    title VARCHAR(200),
    file_path VARCHAR(255) NOT NULL,
    file_name VARCHAR(255),
    file_size INT,
    file_type VARCHAR(50),
    school_year VARCHAR(20),
    uploaded_by INT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
);

-- ============================================================
-- ANNOUNCEMENTS
-- ============================================================
CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    category ENUM('general','academic','event','health','other') DEFAULT 'general',
    target_audience ENUM('all','parents','teachers','admin') DEFAULT 'all',
    is_published TINYINT(1) DEFAULT 1,
    posted_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (posted_by) REFERENCES users(id)
);

-- ============================================================
-- NOTIFICATIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(200),
    message TEXT NOT NULL,
    type ENUM('request_update','announcement','reminder','system') DEFAULT 'system',
    reference_id INT COMMENT 'e.g. request id',
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- ============================================================
-- AUDIT LOG
-- ============================================================
CREATE TABLE IF NOT EXISTS audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(50),
    record_id INT,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- ============================================================
-- SEED DATA
-- ============================================================

-- Default admin account (password: Admin@123)
INSERT IGNORE INTO users (username, password, role, full_name, email) VALUES
('admin', '$2y$10$Yo19T/o4vVMjI87FRoGx/ekYKicaYV1kC3HjeDb72oPF6e6k9.HUG', 'admin', 'School Administrator', 'admin@batangportal.edu.ph');

-- School Info
INSERT IGNORE INTO school_info (school_name, school_id, address, district, division, region, principal_name, school_year) VALUES
('Atel-Batang Elementary School', '123456', 'Barangay Sample, Municipality, Province', 'District I', 'Division of Sample', 'Region IV-A', 'Principal Name', '2025-2026');

-- Grade Levels (K-6 Philippine Elementary)
INSERT IGNORE INTO grade_levels (id, grade_name, description) VALUES
(1, 'Kindergarten', 'Kindergarten Level'),
(2, 'Grade 1', 'Grade 1'),
(3, 'Grade 2', 'Grade 2'),
(4, 'Grade 3', 'Grade 3'),
(5, 'Grade 4', 'Grade 4'),
(6, 'Grade 5', 'Grade 5'),
(7, 'Grade 6', 'Grade 6');

-- Document Types
INSERT IGNORE INTO document_types (doc_name, doc_code, description, processing_days, fee) VALUES
('Form 137 (Permanent Record)', 'F137', 'Official permanent school record of the student', 5, 0.00),
('Form 138 (Report Card)', 'F138', 'Official report card showing quarterly grades', 3, 0.00),
('Certificate of Enrollment', 'COE', 'Certifies that the student is currently enrolled', 1, 0.00),
('Good Moral Certificate', 'GMC', 'Certificate of good moral character', 2, 0.00),
('Certificate of Completion', 'COC', 'Certificate of completion for Grade 6 graduates', 3, 0.00),
('Diploma', 'DIP', 'Official diploma for Grade 6 graduates', 5, 0.00),
('Transcript of Records', 'TOR', 'Complete academic transcript', 5, 0.00),
('Certificate of Ranking', 'COR', 'Certificate showing academic ranking', 3, 0.00),
('Certification (General)', 'CERT', 'General school certification', 2, 0.00),
('SF9 (Report Card)', 'SF9', 'School Form 9 - Learner\'s Progress Report Card', 3, 0.00),
('SF10 (Learner\'s Permanent Record)', 'SF10', 'School Form 10 - Learner\'s Permanent Academic Record', 5, 0.00);

-- Subjects per grade level (one row per subject per grade — unique constraint enforced)
INSERT IGNORE INTO subjects (id, subject_name, subject_code, grade_level_id) VALUES
-- Kindergarten (grade_level_id=1)
(1,  'Mother Tongue',                    'MT',    1),
(2,  'Numeracy',                         'NUM',   1),
(3,  'Literacy',                         'LIT',   1),
-- Grade 1 (grade_level_id=2)
(4,  'Filipino',                         'FIL',   2),
(5,  'English',                          'ENG',   2),
(6,  'Mathematics',                      'MATH',  2),
(7,  'Araling Panlipunan',               'AP',    2),
(8,  'Mother Tongue',                    'MT',    2),
(9,  'MAPEH',                            'MAPEH', 2),
(10, 'Edukasyon sa Pagpapakatao',        'ESP',   2),
-- Grade 2 (grade_level_id=3)
(11, 'Filipino',                         'FIL',   3),
(12, 'English',                          'ENG',   3),
(13, 'Mathematics',                      'MATH',  3),
(14, 'Araling Panlipunan',               'AP',    3),
(15, 'Mother Tongue',                    'MT',    3),
(16, 'MAPEH',                            'MAPEH', 3),
(17, 'Edukasyon sa Pagpapakatao',        'ESP',   3),
-- Grade 3 (grade_level_id=4)
(18, 'Filipino',                         'FIL',   4),
(19, 'English',                          'ENG',   4),
(20, 'Mathematics',                      'MATH',  4),
(21, 'Araling Panlipunan',               'AP',    4),
(22, 'Mother Tongue',                    'MT',    4),
(23, 'MAPEH',                            'MAPEH', 4),
(24, 'Edukasyon sa Pagpapakatao',        'ESP',   4),
-- Grade 4 (grade_level_id=5)
(25, 'Filipino',                         'FIL',   5),
(26, 'English',                          'ENG',   5),
(27, 'Mathematics',                      'MATH',  5),
(28, 'Araling Panlipunan',               'AP',    5),
(29, 'Science',                          'SCI',   5),
(30, 'MAPEH',                            'MAPEH', 5),
(31, 'Edukasyon sa Pagpapakatao',        'ESP',   5),
(32, 'Technology and Livelihood Education', 'TLE', 5),
-- Grade 5 (grade_level_id=6)
(33, 'Filipino',                         'FIL',   6),
(34, 'English',                          'ENG',   6),
(35, 'Mathematics',                      'MATH',  6),
(36, 'Araling Panlipunan',               'AP',    6),
(37, 'Science',                          'SCI',   6),
(38, 'MAPEH',                            'MAPEH', 6),
(39, 'Edukasyon sa Pagpapakatao',        'ESP',   6),
(40, 'Technology and Livelihood Education', 'TLE', 6),
-- Grade 6 (grade_level_id=7)
(41, 'Filipino',                         'FIL',   7),
(42, 'English',                          'ENG',   7),
(43, 'Mathematics',                      'MATH',  7),
(44, 'Araling Panlipunan',               'AP',    7),
(45, 'Science',                          'SCI',   7),
(46, 'MAPEH',                            'MAPEH', 7),
(47, 'Edukasyon sa Pagpapakatao',        'ESP',   7),
(48, 'Technology and Livelihood Education', 'TLE', 7);
