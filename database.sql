CREATE DATABASE IF NOT EXISTS campusfix;
USE campusfix;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('requester','admin_staff','maintenance','itso','system_admin') NOT NULL DEFAULT 'requester',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    requester_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    category VARCHAR(100) NOT NULL,
    location VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    photo VARCHAR(255) NOT NULL,
    completion_photo VARCHAR(255) NULL,
    assigned_to INT NULL,
    status ENUM('Pending','Assigned','In Progress','Completed') NOT NULL DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_requester
        FOREIGN KEY (requester_id) REFERENCES users(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_assigned
        FOREIGN KEY (assigned_to) REFERENCES users(id)
        ON DELETE SET NULL
);

CREATE TABLE request_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    stage ENUM('reviewed','assigned','repair','resolved') NOT NULL,
    completed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_request_progress_stage (request_id, stage),
    CONSTRAINT fk_progress_request
        FOREIGN KEY (request_id) REFERENCES requests(id)
        ON DELETE CASCADE
);

CREATE TABLE password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token_hash (token_hash),
    CONSTRAINT fk_reset_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
);

-- ============================================================
-- MOCKUP ACCOUNTS (1 per role)
-- Default password for all mockup accounts: CampusFix@123
-- ============================================================
INSERT INTO users (name, email, password, role) VALUES
('Juan Dela Cruz', 'requester.demo@tip.edu.ph', '$2y$10$186COlJP51Dm0uXzM7GwguJeSpjfoGKJWVCKDoqk4V4uB6F1IOyEq', 'requester'),
('Maria Clara', 'adminstaff.demo@tip.edu.ph', '$2y$10$186COlJP51Dm0uXzM7GwguJeSpjfoGKJWVCKDoqk4V4uB6F1IOyEq', 'admin_staff'),
('Pedro Penduko', 'maintenance.demo@tip.edu.ph', '$2y$10$186COlJP51Dm0uXzM7GwguJeSpjfoGKJWVCKDoqk4V4uB6F1IOyEq', 'maintenance'),
('Jose Rizal', 'itso.demo@tip.edu.ph', '$2y$10$186COlJP51Dm0uXzM7GwguJeSpjfoGKJWVCKDoqk4V4uB6F1IOyEq', 'itso'),
('Andres Bonifacio', 'sysadmin.demo@tip.edu.ph', '$2y$10$186COlJP51Dm0uXzM7GwguJeSpjfoGKJWVCKDoqk4V4uB6F1IOyEq', 'system_admin')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    password = VALUES(password),
    role = VALUES(role);


-- Vercel/serverless: photos and PHP sessions are stored in MySQL (no writable disk).
CREATE TABLE IF NOT EXISTS photos (
    name CHAR(36) PRIMARY KEY,
    mime VARCHAR(50) NOT NULL,
    data MEDIUMBLOB NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS sessions (
    id VARCHAR(128) PRIMARY KEY,
    data MEDIUMTEXT NOT NULL,
    expires_at INT NOT NULL,
    INDEX idx_sessions_expires (expires_at)
);