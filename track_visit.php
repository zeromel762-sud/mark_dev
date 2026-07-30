<?php
require_once 'db.php';

header('Content-Type: application/json');

// Create visitors table if it doesn't exist
$pdo->exec("CREATE TABLE IF NOT EXISTS visitors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(500),
    page_visited VARCHAR(255),
    visit_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX (visit_date),
    INDEX (ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Get visitor info
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
    $ip = $_SERVER['HTTP_CLIENT_IP'];
} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
}
$ua = substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 500);
$page = $_POST['page'] ?? $_GET['page'] ?? 'index';

// Insert visit record
$stmt = $pdo->prepare("INSERT INTO visitors (ip_address, user_agent, page_visited) VALUES (?, ?, ?)");
$stmt->execute([$ip, $ua, $page]);

echo json_encode(['success' => true]);
?>