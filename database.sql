CREATE TABLE IF NOT EXISTS visitors (
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

CREATE TABLE IF NOT EXISTS visitor_logs (
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

INSERT INTO visitors (rfid, name, email, year_level, program, type, blocked) VALUES
('24-98463-352', 'Maria Santos',    'msantos@neu.edu.ph',   '2nd Year', 'BSIT', 'student', 0),
('24-10846-112', 'Juan dela Cruz',  'jdelacruz@neu.edu.ph', '3rd Year', 'BSCS', 'student', 0),
('21-30001-001', 'Prof. Reyes',     'preyes@neu.edu.ph',    'Faculty',  'N/A',  'faculty', 0),
('23-10933-774', 'Ana Gomez',       'agomez@neu.edu.ph',    '1st Year', 'BSA',  'student', 0),
('22-40012-338', 'Carlos Bautista', 'cbautista@neu.edu.ph', 'Staff',    'N/A',  'staff',   0),
('24-10847-205', 'Liza Reyes',      'lreyes@neu.edu.ph',    '4th Year', 'BSEE', 'student', 0);