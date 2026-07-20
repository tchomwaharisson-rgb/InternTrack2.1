CREATE DATABASE IF NOT EXISTS interntrack;
USE interntrack;

-- Users table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    role ENUM('admin', 'supervisor', 'intern') NOT NULL,
    profile_picture VARCHAR(255) DEFAULT NULL,
    phone VARCHAR(20),
    address TEXT,
    bio TEXT,
    language ENUM('en', 'fr') DEFAULT 'en',
    theme ENUM('light', 'dark') DEFAULT 'light',
    is_active BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Interns additional info
CREATE TABLE interns (
    user_id INT PRIMARY KEY,
    school VARCHAR(100),
    field_of_study VARCHAR(100),
    internship_duration_months INT,
    start_date DATE,
    end_date DATE,
    supervisor_id INT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (supervisor_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Supervisors additional info
CREATE TABLE supervisors (
    user_id INT PRIMARY KEY,
    department VARCHAR(100),
    position VARCHAR(100),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Registration requests
CREATE TABLE registration_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(100) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    role ENUM('supervisor', 'intern') NOT NULL,
    school VARCHAR(100),
    field_of_study VARCHAR(100),
    department VARCHAR(100),
    position VARCHAR(100),
    password_hash VARCHAR(255) NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    admin_comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    reviewed_by INT,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Time logs
CREATE TABLE time_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    intern_id INT NOT NULL,
    date DATE NOT NULL,
    clock_in TIME,
    clock_out TIME,
    break_start TIME,
    break_end TIME,
    total_break_minutes INT DEFAULT 0,
    total_hours DECIMAL(5,2) DEFAULT 0,
    status ENUM('active', 'completed', 'missed') DEFAULT 'active',
    notes TEXT,
    FOREIGN KEY (intern_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_intern_date (intern_id, date)
);

-- Break logs
CREATE TABLE break_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    time_log_id INT NOT NULL,
    break_start TIME NOT NULL,
    break_end TIME,
    duration_minutes INT DEFAULT 0,
    FOREIGN KEY (time_log_id) REFERENCES time_logs(id) ON DELETE CASCADE
);

-- Messages (chat)
CREATE TABLE messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Goals
CREATE TABLE goals (
    id INT PRIMARY KEY AUTO_INCREMENT,
    intern_id INT NOT NULL,
    supervisor_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('pending', 'in_progress', 'completed', 'overdue') DEFAULT 'pending',
    progress INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (intern_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (supervisor_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Goal updates
CREATE TABLE goal_updates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    goal_id INT NOT NULL,
    progress INT NOT NULL,
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (goal_id) REFERENCES goals(id) ON DELETE CASCADE
);

-- Leave requests
CREATE TABLE leave_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    intern_id INT NOT NULL,
    leave_date DATE NOT NULL,
    type ENUM('vacation', 'sick', 'personal', 'other') NOT NULL,
    reason TEXT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    supervisor_comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    reviewed_by INT,
    FOREIGN KEY (intern_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
);

-- System settings
CREATE TABLE settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(50) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Audit logs
CREATE TABLE audit_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Notifications
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    link VARCHAR(255),
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Insert default settings
INSERT INTO settings (setting_key, setting_value, description) VALUES
('work_start', '08:00:00', 'Work day start time'),
('work_end', '18:00:00', 'Work day end time'),
('break_start', '12:00:00', 'Break start time'),
('break_end', '14:00:00', 'Break end time'),
('max_break_minutes', '120', 'Maximum break duration in minutes'),
('clock_in_reminder_time', '08:00:00', 'Time to send clock-in reminder'),
('maintenance_mode', 'false', 'System maintenance mode');

-- Insert default admin user (password: Admin@2024!)
INSERT INTO users (email, password, first_name, last_name, role, is_active) VALUES
('admin@interntrack.com', '$2y$10$YOUR_HASH_HERE', 'System', 'Administrator', 'admin', TRUE);