<?php
// PASSWORD RESET SCRIPT
// Access: http://localhost/lemon_dev_system/reset_password.php
// This will reset the admin password to "admin123"

require_once 'db.php';

// Reset admin password
$newPassword = 'admin123';
$hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = 'admin'");
$stmt->execute([$hashedPassword]);

$affected = $stmt->rowCount();

echo "<div style='font-family: monospace; background: #0d1117; color: #3fb950; padding: 2rem; border-radius: 8px; max-width: 500px; margin: 2rem auto;'>";
echo "<h2 style='color: #58a6ff;'>// Password Reset Complete</h2><br>";
echo "<p>Admin password has been reset!</p><br>";
echo "<p><strong>Username:</strong> admin</p>";
echo "<p><strong>Email:</strong> admin@lemondev.com</p>";
echo "<p><strong>New Password:</strong> admin123</p><br>";
echo "<p style='color: #d29922;'>[!] Please delete this file after resetting for security.</p><br>";
echo "<a href='login.html' style='color: #58a6ff; text-decoration: none;'>→ Go to Login</a>";
echo "</div>";

// Also reset mark_123 password
$markPass = 'mark21***';
$markHash = password_hash($markPass, PASSWORD_DEFAULT);
$stmt2 = $pdo->prepare("UPDATE users SET password = ? WHERE username = 'mark_123'");
$stmt2->execute([$markHash]);

echo "<div style='font-family: monospace; background: #0d1117; color: #8b949e; padding: 1rem; border-radius: 8px; max-width: 500px; margin: 1rem auto; font-size: 0.8rem;'>";
echo "<p style='color: #3fb950;'>[OK] mark_123 password also reset to: mark21***</p>";
echo "</div>";
?>