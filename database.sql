CREATE DATABASE IF NOT EXISTS neu_library CHARACTER SET utf8mb4;
USE neu_library;

CREATE TABLE visitors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rfid VARCHAR(20) DEFAULT '',
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    year_level VARCHAR(50) DEFAULT '',
    program VARCHAR(100) DEFAULT '',
    type VARCHAR(20) DEFAULT 'student',
    blocked TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE visitor_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    visitor_id INT DEFAULT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) DEFAULT '',
    rfid VARCHAR(20) DEFAULT '',
    year_level VARCHAR(50) DEFAULT '',
    program VARCHAR(100) DEFAULT '',
    type VARCHAR(20) DEFAULT 'student',
    reason VARCHAR(200) NOT NULL,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO visitors (rfid, name, email, year_level, program, type) VALUES
('24-98463-352','Maria Santos','msantos@neu.edu.ph','2nd Year','BSIT','student'),
('21-30001-001','Prof. Reyes','preyes@neu.edu.ph','Faculty','N/A','faculty'),
('22-40012-338','Carlos Bautista','cbautista@neu.edu.ph','Staff','N/A','staff');