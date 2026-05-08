-- Multiple Intelligence Survey Table
CREATE TABLE IF NOT EXISTS multiple_intelligence_survey (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(20) NOT NULL,
    section1_score INT DEFAULT 0,
    section2_score INT DEFAULT 0,
    section3_score INT DEFAULT 0,
    section4_score INT DEFAULT 0,
    section5_score INT DEFAULT 0,
    section6_score INT DEFAULT 0,
    section7_score INT DEFAULT 0,
    section8_score INT DEFAULT 0,
    section9_score INT DEFAULT 0,
    completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_student_survey (student_id),
    INDEX idx_student_id (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
