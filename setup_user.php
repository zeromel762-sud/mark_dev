<?php
// Run this file once to set up the mark_123 user with correct password
// Access: http://localhost/lemon_dev_system/setup_user.php

require_once 'db.php';

// Set mark_123 password to "mark21***"
$hashedPassword = password_hash('mark21***', PASSWORD_DEFAULT);

$stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = 'mark_123'");
$stmt->execute([$hashedPassword]);

echo "mark_123 password has been set to: mark21***<br>";
echo "Hash: " . $hashedPassword . "<br>";
echo "<br>You can now delete this file for security.";
?>