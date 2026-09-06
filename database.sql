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
    photo VARCHAR(255) NULL,
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

-- SECURITY NOTE:
-- Public registration creates requester accounts only.
-- Privileged roles must be created by a System Administrator through Manage Users.
-- For the first local prototype admin, you may register a normal account first,
-- then run the following manually in phpMyAdmin, replacing the email:
-- UPDATE users SET role = 'system_admin' WHERE email = 'your-admin-email@example.com';
