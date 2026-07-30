<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'register':
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($email) || empty($password)) {
            echo json_encode(['success' => false, 'message' => 'All fields are required']);
            exit;
        }

        if (strlen($password) < 6) {
            echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Username or email already exists']);
            exit;
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Check if admin has enabled auto-approve
        $admin = $pdo->query("SELECT auto_approve FROM users WHERE is_admin = 1 LIMIT 1")->fetch();
        $autoApprove = $admin ? (int)$admin['auto_approve'] : 0;
        $approved = $autoApprove ? 1 : 0;
        $approvedAt = $autoApprove ? date('Y-m-d H:i:s') : null;

        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, status, approved, approved_at, auto_approve) VALUES (?, ?, ?, 'offline', ?, ?, ?)");
        $stmt->execute([$username, $email, $hashedPassword, $approved, $approvedAt, 0]);

        if ($autoApprove) {
            echo json_encode(['success' => true, 'message' => 'Registration successful! You can now login.']);
        } else {
            echo json_encode(['success' => true, 'message' => 'Registration successful! Please wait for admin approval.']);
        }
        break;
    
    case 'login':
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            echo json_encode(['success' => false, 'message' => 'All fields are required']);
            exit;
        }
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($password, $user['password'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
            exit;
        }
        
        // Check if user is blocked
        if ((int)$user['blocked'] === 1) {
            echo json_encode(['success' => false, 'message' => 'Your account has been blocked by the admin.']);
            exit;
        }
        
        // Check if user is approved
        if ((int)$user['approved'] !== 1) {
            echo json_encode(['success' => false, 'message' => 'Your account is pending approval. Please wait for admin approval.']);
            exit;
        }
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['is_admin'] = $user['is_admin'];
        
        $stmt = $pdo->prepare("UPDATE users SET status = 'online', last_login = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);
        
        echo json_encode(['success' => true, 'message' => 'Login successful!', 'username' => $user['username'], 'is_admin' => (int)$user['is_admin']]);
        break;
    
    case 'logout':
        if (isset($_SESSION['user_id'])) {
            $stmt = $pdo->prepare("UPDATE users SET status = 'offline' WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
        }
        session_destroy();
        echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
        break;
    
    case 'check':
        if (isset($_SESSION['user_id'])) {
            echo json_encode(['success' => true, 'username' => $_SESSION['username']]);
        } else {
            echo json_encode(['success' => false]);
        }
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>