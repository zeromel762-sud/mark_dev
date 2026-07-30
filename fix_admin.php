<?php
// Run this file to create new admin account
// Access: http://localhost/lemon_dev_system/fix_admin.php

require_once 'db.php';

echo "<h2>Create New Admin Account</h2>";

// Check if lemon_dev already exists
$stmt = $pdo->prepare("SELECT id FROM users WHERE username = 'lemon_dev'");
$stmt->execute();
$existing = $stmt->fetch();

if ($existing) {
    // Update existing user to admin
    $hash = password_hash('mark123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password = ?, is_admin = 1, blocked = 0, approved = 1, approved_at = NOW() WHERE username = 'lemon_dev'");
    $stmt->execute([$hash]);
    echo "<p style='color:green;'>✅ Existing user 'lemon_dev' updated to admin!</p>";
} else {
    // Create new admin user
    $hash = password_hash('mark123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, status, is_admin, blocked, approved, approved_at) VALUES (?, ?, ?, 'offline', 1, 0, 1, NOW())");
    $stmt->execute(['lemon_dev', 'www.lemon@gmail.com', $hash]);
    echo "<p style='color:green;'>✅ New admin account created!</p>";
}

// Also fix the original admin account
$stmt = $pdo->prepare("UPDATE users SET blocked = 0, approved = 1, approved_at = NOW() WHERE username = 'admin'");
$stmt->execute();

echo "<h3>Admin Credentials:</h3>";
echo "<table border='1' cellpadding='8' style='border-collapse:collapse;font-family:monospace;'>";
echo "<tr><th>Username</th><th>Email</th><th>Password</th><th>Status</th></tr>";

// Show lemon_dev
$stmt = $pdo->query("SELECT username, email, is_admin, blocked, approved FROM users WHERE username = 'lemon_dev'");
$user = $stmt->fetch();
if ($user) {
    echo "<tr>";
    echo "<td><strong>" . $user['username'] . "</strong></td>";
    echo "<td>" . $user['email'] . "</td>";
    echo "<td><strong>mark123</strong></td>";
    echo "<td>" . ($user['is_admin'] ? '✅ Admin' : '❌ Not admin') . " | " . ($user['blocked'] ? '❌ Blocked' : '✅ Active') . " | " . ($user['approved'] ? '✅ Approved' : '❌ Pending') . "</td>";
    echo "</tr>";
}

// Show admin
$stmt = $pdo->query("SELECT username, email, is_admin, blocked, approved FROM users WHERE username = 'admin'");
$user = $stmt->fetch();
if ($user) {
    echo "<tr>";
    echo "<td><strong>" . $user['username'] . "</strong></td>";
    echo "<td>" . $user['email'] . "</td>";
    echo "<td><strong>admin123</strong></td>";
    echo "<td>" . ($user['is_admin'] ? '✅ Admin' : '❌ Not admin') . " | " . ($user['blocked'] ? '❌ Blocked' : '✅ Active') . " | " . ($user['approved'] ? '✅ Approved' : '❌ Pending') . "</td>";
    echo "</tr>";
}

echo "</table>";

echo "<br><p><strong>Login URL:</strong> <a href='login.html' target='_blank'>login.html</a></p>";
echo "<p><strong>Admin Panel:</strong> <a href='admin.html' target='_blank'>admin.html</a></p>";
echo "<br><p><em>You can delete this file after fixing.</em></p>";
?>