-- LEMON_DEV System Database
-- Run this in phpMyAdmin or MySQL console

CREATE DATABASE IF NOT EXISTS if0_37899523_dev;
USE if0_37899523_dev;

-- Users table for registration and login
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    avatar VARCHAR(255) DEFAULT 'default.png',
    status VARCHAR(20) DEFAULT 'offline',
    is_admin TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL DEFAULT NULL,
    blocked TINYINT(1) DEFAULT 0,
    approved TINYINT(1) DEFAULT 0,
    approved_at TIMESTAMP NULL DEFAULT NULL,
    auto_approve TINYINT(1) DEFAULT 0
);

-- Chat messages table (private messaging)
CREATE TABLE IF NOT EXISTS chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT,
    file_id INT NULL,
    seen TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Chat files table
CREATE TABLE IF NOT EXISTS chat_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_type VARCHAR(100) NOT NULL,
    file_size INT NOT NULL,
    file_ext VARCHAR(10) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Message reactions table
CREATE TABLE IF NOT EXISTS message_reactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    user_id INT NOT NULL,
    reaction VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES chat_messages(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_reaction (message_id, user_id, reaction)
);

-- Transactions table
CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    service_type VARCHAR(100) NOT NULL,
    description TEXT,
    status VARCHAR(20) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Insert admin user (password: admin123)
-- Hash generated via PHP: password_hash('admin123', PASSWORD_DEFAULT)
INSERT INTO users (username, email, password, status, is_admin) VALUES
('admin', 'admin@lemondev.com', '$2y$10$yT6fr33z0IkBNTmXBG5nNOyT/Ij5oQwsUT9lGjJgU1DBcVSSGV9ee', 'offline', 1);

-- Insert mark_123 user (password: mark21***)
-- Hash generated via PHP: password_hash('mark21***', PASSWORD_DEFAULT)
INSERT INTO users (username, email, password, status, is_admin) VALUES
('mark_123', 'mark123@example.com', '$2y$10$U4z1Zhacv.PG.QyF4y5B/OsqXqACFhZdOtMyp19m.D6N8nmtuXHO6', 'offline', 0);
