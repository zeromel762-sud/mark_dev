<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit;
}

$action = $_POST['action'] ?? '';
$userId = $_SESSION['user_id'];

switch ($action) {
    case 'create':
        $serviceType = trim($_POST['service_type'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        if (empty($serviceType)) {
            echo json_encode(['success' => false, 'message' => 'Service type is required']);
            exit;
        }
        
        $stmt = $pdo->prepare("INSERT INTO transactions (user_id, service_type, description) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $serviceType, $description]);
        
        echo json_encode(['success' => true, 'message' => 'Transaction request submitted! We will contact you soon.']);
        break;
    
    case 'list':
        $stmt = $pdo->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$userId]);
        $transactions = $stmt->fetchAll();
        echo json_encode(['success' => true, 'transactions' => $transactions]);
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>