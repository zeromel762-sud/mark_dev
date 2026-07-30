<?php
// Complete Setup Script - Run this first
// Access: Run on hosting server

$host = 'sql310.infinityfree.com';
$username = 'if0_37899523';
$password = 'zdCQvLY6Ie';

try {
    // Connect without database first
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>LEMON_DEV System Setup</h2>";
    
    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS if0_37899523_dev CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "<p style='color:green;'>✅ Database 'if0_37899523_dev' created or already exists.</p>";
    
    // Switch to the database
    $pdo->exec("USE if0_37899523_dev");
    
    // Create users table
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
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
    )");
    echo "<p style='color:green;'>✅ Users table created.</p>";
    
    // Create chat_messages table
    $pdo->exec("CREATE TABLE IF NOT EXISTS chat_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NOT NULL,
        receiver_id INT NOT NULL,
        message TEXT,
        file_id INT NULL,
        seen TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    echo "<p style='color:green;'>✅ Chat messages table created.</p>";
    
    // Create chat_files table
    $pdo->exec("CREATE TABLE IF NOT EXISTS chat_files (
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
    )");
    echo "<p style='color:green;'>✅ Chat files table created.</p>";
    
    // Create message_reactions table
    $pdo->exec("CREATE TABLE IF NOT EXISTS message_reactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        message_id INT NOT NULL,
        user_id INT NOT NULL,
        reaction VARCHAR(20) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (message_id) REFERENCES chat_messages(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_reaction (message_id, user_id, reaction)
    )");
    echo "<p style='color:green;'>✅ Message reactions table created.</p>";
    
    // Create transactions table
    $pdo->exec("CREATE TABLE IF NOT EXISTS transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        service_type VARCHAR(100) NOT NULL,
        description TEXT,
        status VARCHAR(20) DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    echo "<p style='color:green;'>✅ Transactions table created.</p>";
    
    // Create admin users
    $hash = password_hash('mark123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, email, password, status, is_admin, blocked, approved, approved_at) VALUES (?, ?, ?, 'offline', 1, 0, 1, NOW())");
    $stmt->execute(['lemon_dev', 'www.lemon@gmail.com', $hash]);
    echo "<p style='color:green;'>✅ Admin 'lemon_dev' created/updated (password: mark123)</p>";
    
    $hash2 = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, email, password, status, is_admin, blocked, approved, approved_at) VALUES (?, ?, ?, 'offline', 1, 0, 1, NOW())");
    $stmt->execute(['admin', 'admin@lemondev.com', $hash2]);
    echo "<p style='color:green;'>✅ Admin 'admin' created/updated (password: admin123)</p>";
    
    // Ensure both admins are unblocked and approved
    $pdo->exec("UPDATE users SET blocked = 0, approved = 1 WHERE is_admin = 1");
    echo "<p style='color:green;'>✅ All admin accounts are unblocked and approved.</p>";
    
    echo "<hr>";
    echo "<h3>✅ Setup Complete!</h3>";
    echo "<h4>Admin Credentials:</h4>";
    echo "<table border='1' cellpadding='8' style='border-collapse:collapse;font-family:monospace;'>";
    echo "<tr><th>Username</th><th>Email</th><th>Password</th></tr>";
    echo "<tr><td><strong>lemon_dev</strong></td><td>www.lemon@gmail.com</td><td><strong>mark123</strong></td></tr>";
    echo "<tr><td><strong>admin</strong></td><td>admin@lemondev.com</td><td><strong>admin123</strong></td></tr>";
    echo "</table>";
    echo "<br><p><strong>Login:</strong> <a href='login.html' target='_blank'>login.html</a></p>";
    echo "<p><strong>Admin Panel:</strong> <a href='admin.html' target='_blank'>admin.html</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color:red;'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<p>Make sure XAMPP MySQL is running (check XAMPP Control Panel).</p>";
}
?>