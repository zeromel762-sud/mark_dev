<?php
// Database Connection - LEMON_DEV System
// Hosting: InfinityFree

$host = 'sql310.infinityfree.com';
$dbname = 'if0_37899523_dev';
$username = 'if0_37899523';
$password = 'zdCQvLY6Ie';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
