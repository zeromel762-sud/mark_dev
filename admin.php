<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
if (!$isAdmin) {
    echo json_encode(['success' => false, 'message' => 'Access denied. Admin only.']);
    exit;
}

$adminId = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

switch ($action) {
    case 'get_all_conversations':
        // Admin: get list of all non-admin users who have conversations
        $stmt = $pdo->query("
            SELECT DISTINCT u.id, u.username, u.email, u.status, u.last_login,
                   (SELECT COUNT(*) FROM chat_messages WHERE sender_id = u.id AND receiver_id = $adminId AND seen = 0) as unread,
                       (SELECT MAX(created_at) FROM chat_messages WHERE (sender_id = u.id AND receiver_id = $adminId) OR (sender_id = $adminId AND receiver_id = u.id)) as last_message_time
            FROM users u
            INNER JOIN chat_messages cm ON (cm.sender_id = u.id AND cm.receiver_id = $adminId) OR (cm.sender_id = $adminId AND cm.receiver_id = u.id)
            WHERE u.id != $adminId AND u.is_admin = 0
            GROUP BY u.id
            ORDER BY last_message_time DESC
        ");
        $conversations = $stmt->fetchAll();
        echo json_encode(['success' => true, 'conversations' => $conversations]);
        break;

    case 'get_conversation':
        // Admin: get specific conversation with a user
        $userId = intval($_POST['user_id'] ?? 0);
        if ($userId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid user']);
            exit;
        }

        // Verify user exists and is not admin
        $stmt = $pdo->prepare("SELECT id, username, is_admin FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if (!$user || $user['is_admin'] == 1) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit;
        }

        $lastId = intval($_POST['last_id'] ?? 0);
        if ($lastId > 0) {
            $stmt = $pdo->prepare("
                SELECT cm.id, cm.sender_id, cm.receiver_id, cm.message, cm.file_id, cm.seen, cm.created_at, u.username
                FROM chat_messages cm
                JOIN users u ON cm.sender_id = u.id
                WHERE cm.id > ? AND (
                    (cm.sender_id = ? AND cm.receiver_id = ?) OR
                    (cm.sender_id = ? AND cm.receiver_id = ?)
                )
                ORDER BY cm.id ASC
                LIMIT 100
            ");
            $stmt->execute([$lastId, $userId, $adminId, $adminId, $userId]);
        } else {
            $stmt = $pdo->prepare("
                SELECT cm.id, cm.sender_id, cm.receiver_id, cm.message, cm.file_id, cm.seen, cm.created_at, u.username
                FROM chat_messages cm
                JOIN users u ON cm.sender_id = u.id
                WHERE (cm.sender_id = ? AND cm.receiver_id = ?) OR
                      (cm.sender_id = ? AND cm.receiver_id = ?)
                ORDER BY cm.id ASC
                LIMIT 100
            ");
            $stmt->execute([$userId, $adminId, $adminId, $userId]);
        }
        $messages = $stmt->fetchAll();

        // Mark messages as seen
        $stmt = $pdo->prepare("UPDATE chat_messages SET seen = 1 WHERE sender_id = ? AND receiver_id = ? AND seen = 0");
        $stmt->execute([$userId, $adminId]);

        echo json_encode(['success' => true, 'messages' => $messages]);
        break;

    case 'get_all_users':
        // Admin: get all users for reference
        $stmt = $pdo->query("SELECT id, username, email, status, is_admin, created_at, last_login FROM users ORDER BY created_at DESC");
        $users = $stmt->fetchAll();
        echo json_encode(['success' => true, 'users' => $users]);
        break;

    case 'get_online_users':
        // Admin: get all online users
        $stmt = $pdo->query("SELECT id, username, status FROM users WHERE status = 'online' AND id != " . (int)$adminId . " ORDER BY username ASC");
        $users = $stmt->fetchAll();
        echo json_encode(['success' => true, 'users' => $users]);
        break;

    case 'get_all_users_full':
        $stmt = $pdo->query("SELECT id, username, email, status, is_admin, blocked, approved, approved_at, created_at, last_login FROM users ORDER BY created_at DESC");
        $users = $stmt->fetchAll();
        echo json_encode(['success' => true, 'users' => $users]);
        break;

    case 'block_user':
        $userId = intval($_POST['user_id'] ?? 0);
        if ($userId <= 0) { echo json_encode(['success' => false, 'message' => 'Invalid user']); exit; }
        if ($userId == $adminId) { echo json_encode(['success' => false, 'message' => 'You cannot block yourself']); exit; }
        $stmt = $pdo->prepare("UPDATE users SET blocked = 1 WHERE id = ? AND is_admin = 0");
        $stmt->execute([$userId]);
        if ($stmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'message' => 'Cannot block admin users']);
            exit;
        }
        echo json_encode(['success' => true, 'message' => 'User blocked']);
        break;

    case 'unblock_user':
        $userId = intval($_POST['user_id'] ?? 0);
        if ($userId <= 0) { echo json_encode(['success' => false, 'message' => 'Invalid user']); exit; }
        if ($userId == $adminId) { echo json_encode(['success' => false, 'message' => 'You cannot unblock yourself']); exit; }
        $stmt = $pdo->prepare("UPDATE users SET blocked = 0 WHERE id = ? AND is_admin = 0");
        $stmt->execute([$userId]);
        if ($stmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'message' => 'Cannot unblock admin users']);
            exit;
        }
        echo json_encode(['success' => true, 'message' => 'User unblocked']);
        break;

    case 'reset_user_password':
        $userId = intval($_POST['user_id'] ?? 0);
        $newPassword = trim($_POST['new_password'] ?? '');
        if ($userId <= 0 || empty($newPassword)) { echo json_encode(['success' => false, 'message' => 'Invalid request']); exit; }
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ? AND is_admin = 0");
        $stmt->execute([$hash, $userId]);
        echo json_encode(['success' => true, 'message' => 'Password reset']);
        break;

    case 'delete_user':
        $userId = intval($_POST['user_id'] ?? 0);
        if ($userId <= 0) { echo json_encode(['success' => false, 'message' => 'Invalid user']); exit; }
        if ($userId == $adminId) { echo json_encode(['success' => false, 'message' => 'You cannot delete yourself']); exit; }
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND is_admin = 0");
        $stmt->execute([$userId]);
        if ($stmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'message' => 'Cannot delete admin users']);
            exit;
        }
        echo json_encode(['success' => true, 'message' => 'User deleted']);
        break;

    case 'approve_user':
        $userId = intval($_POST['user_id'] ?? 0);
        if ($userId <= 0) { echo json_encode(['success' => false, 'message' => 'Invalid user']); exit; }
        if ($userId == $adminId) { echo json_encode(['success' => false, 'message' => 'You cannot approve yourself']); exit; }
        $stmt = $pdo->prepare("UPDATE users SET approved = 1, approved_at = NOW() WHERE id = ? AND is_admin = 0");
        $stmt->execute([$userId]);
        if ($stmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'message' => 'Cannot approve admin users']);
            exit;
        }
        echo json_encode(['success' => true, 'message' => 'User approved']);
        break;

    case 'toggle_auto_approve':
        $auto = intval($_POST['auto_approve'] ?? 0);
        $stmt = $pdo->prepare("UPDATE users SET auto_approve = ? WHERE id = ? AND is_admin = 1");
        $stmt->execute([$auto, $adminId]);
        echo json_encode(['success' => true, 'message' => 'Auto-approve updated']);
        break;

    case 'auto_approve_pending':
        // Approve users who have been pending for more than 30 minutes
        $stmt = $pdo->query("UPDATE users SET approved = 1, approved_at = NOW() WHERE approved = 0 AND auto_approve = 1 AND created_at < NOW() - INTERVAL 30 MINUTE");
        $count = $stmt->rowCount();
        echo json_encode(['success' => true, 'message' => "Auto-approved $count users"]);
        break;

    case 'reply':
        // Admin replies to chat
        $message = trim($_POST['message'] ?? '');
        $userId = intval($_POST['user_id'] ?? 0);

        if (empty($message) && empty($_FILES['file']['name'])) {
            echo json_encode(['success' => false, 'message' => 'Message or file is required']);
            exit;
        }

        if ($userId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid recipient']);
            exit;
        }

        // Verify recipient is not admin
        $stmt = $pdo->prepare("SELECT id, is_admin FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $recipient = $stmt->fetch();
        if (!$recipient || $recipient['is_admin'] == 1) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit;
        }

        $fileId = null;
        if (!empty($_FILES['file']['name'])) {
            $fileId = handleFileUploadAdmin($pdo, $adminId, $userId);
            if ($fileId === false) {
                echo json_encode(['success' => false, 'message' => 'File upload failed']);
                exit;
            }
        }

        $adminMessage = '[ADMIN] ' . $message;
        $stmt = $pdo->prepare("INSERT INTO chat_messages (sender_id, receiver_id, message, file_id, seen) VALUES (?, ?, ?, ?, 0)");
        $stmt->execute([$adminId, $userId, $adminMessage ?: null, $fileId]);

        echo json_encode(['success' => true, 'message' => 'Reply sent']);
        break;

    case 'delete_message':
        $messageId = intval($_POST['message_id'] ?? 0);
        if ($messageId > 0) {
            // Verify the message belongs to a conversation with the admin
            $stmt = $pdo->prepare("SELECT id, sender_id, receiver_id FROM chat_messages WHERE id = ?");
            $stmt->execute([$messageId]);
            $msg = $stmt->fetch();

            if ($msg && ($msg['sender_id'] == $adminId || $msg['receiver_id'] == $adminId)) {
                $stmt = $pdo->prepare("DELETE FROM chat_messages WHERE id = ?");
                $stmt->execute([$messageId]);
                echo json_encode(['success' => true, 'message' => 'Message deleted']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Access denied']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid message ID']);
        }
        break;

    case 'user_count':
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE is_admin = 0");
        $total = $stmt->fetch();
        $stmt2 = $pdo->query("SELECT COUNT(*) as online FROM users WHERE status = 'online' AND id != " . (int)$adminId);
        $online = $stmt2->fetch();
        $stmt3 = $pdo->query("SELECT COUNT(*) as msgs FROM chat_messages WHERE sender_id = " . (int)$adminId . " OR receiver_id = " . (int)$adminId);
        $msgs = $stmt3->fetch();
        echo json_encode([
            'success' => true,
            'total_users' => $total['total'],
            'online_users' => $online['online'],
            'total_messages' => $msgs['msgs']
        ]);
        break;

    case 'get_visitor_stats':
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

        $totalVisits = $pdo->query("SELECT COUNT(*) as total FROM visitors")->fetch();
        $todayVisits = $pdo->query("SELECT COUNT(*) as today FROM visitors WHERE DATE(visit_date) = CURDATE()")->fetch();
        $uniqueVisitors = $pdo->query("SELECT COUNT(DISTINCT ip_address) as unique_ips FROM visitors")->fetch();
        $onlineUsers = $pdo->query("SELECT COUNT(*) as online FROM users WHERE status = 'online' AND id != " . (int)$adminId)->fetch();
        $totalUsers = $pdo->query("SELECT COUNT(*) as total_users FROM users WHERE is_admin = 0")->fetch();
        $totalMessages = $pdo->query("SELECT COUNT(*) as total_msgs FROM chat_messages WHERE sender_id = " . (int)$adminId . " OR receiver_id = " . (int)$adminId)->fetch();

        // Get visits for last 7 days
        $visitsByDay = $pdo->query("SELECT DATE(visit_date) as date, COUNT(*) as count FROM visitors WHERE visit_date >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY DATE(visit_date) ORDER BY date ASC")->fetchAll();

        echo json_encode([
            'success' => true,
            'total_visits' => $totalVisits['total'],
            'today_visits' => $todayVisits['today'],
            'unique_visitors' => $uniqueVisitors['unique_ips'],
            'online_users' => $onlineUsers['online'],
            'total_users' => $totalUsers['total_users'],
            'total_messages' => $totalMessages['total_msgs'],
            'visits_by_day' => $visitsByDay
        ]);
        break;

    case 'edit_message':
        $messageId = intval($_POST['message_id'] ?? 0);
        $newMessage = trim($_POST['message'] ?? '');
        if ($messageId <= 0 || empty($newMessage)) {
            echo json_encode(['success' => false, 'message' => 'Invalid request']);
            exit;
        }
        // Verify message belongs to admin conversation
        $stmt = $pdo->prepare("SELECT id, sender_id, receiver_id FROM chat_messages WHERE id = ?");
        $stmt->execute([$messageId]);
        $msg = $stmt->fetch();
        if (!$msg || ($msg['sender_id'] != $adminId && $msg['receiver_id'] != $adminId)) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit;
        }
        $stmt = $pdo->prepare("UPDATE chat_messages SET message = ?, edited_at = NOW() WHERE id = ?");
        $stmt->execute([$newMessage, $messageId]);
        echo json_encode(['success' => true, 'message' => 'Message updated']);
        break;

    case 'delete_message':
        $messageId = intval($_POST['message_id'] ?? 0);
        if ($messageId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid message ID']);
            exit;
        }
        $stmt = $pdo->prepare("SELECT id, sender_id, receiver_id FROM chat_messages WHERE id = ?");
        $stmt->execute([$messageId]);
        $msg = $stmt->fetch();
        if (!$msg || ($msg['sender_id'] != $adminId && $msg['receiver_id'] != $adminId)) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit;
        }
        $stmt = $pdo->prepare("DELETE FROM chat_messages WHERE id = ?");
        $stmt->execute([$messageId]);
        echo json_encode(['success' => true, 'message' => 'Message deleted']);
        break;

    case 'add_reaction':
        $messageId = intval($_POST['message_id'] ?? 0);
        $reaction = trim($_POST['reaction'] ?? '');
        if ($messageId <= 0 || empty($reaction)) {
            echo json_encode(['success' => false, 'message' => 'Invalid request']);
            exit;
        }
        $stmt = $pdo->prepare("SELECT id, sender_id, receiver_id FROM chat_messages WHERE id = ?");
        $stmt->execute([$messageId]);
        $msg = $stmt->fetch();
        if (!$msg || ($msg['sender_id'] != $adminId && $msg['receiver_id'] != $adminId)) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit;
        }
        $stmt = $pdo->prepare("INSERT IGNORE INTO message_reactions (message_id, user_id, reaction) VALUES (?, ?, ?)");
        $stmt->execute([$messageId, $adminId, $reaction]);
        echo json_encode(['success' => true, 'message' => 'Reaction added']);
        break;

    case 'remove_reaction':
        $messageId = intval($_POST['message_id'] ?? 0);
        $reaction = trim($_POST['reaction'] ?? '');
        $stmt = $pdo->prepare("DELETE FROM message_reactions WHERE message_id = ? AND user_id = ? AND reaction = ?");
        $stmt->execute([$messageId, $adminId, $reaction]);
        echo json_encode(['success' => true, 'message' => 'Reaction removed']);
        break;

    case 'get_reactions':
        $messageId = intval($_POST['message_id'] ?? 0);
        if ($messageId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid request']);
            exit;
        }
        $stmt = $pdo->prepare("
            SELECT r.reaction, r.user_id, u.username
            FROM message_reactions r
            JOIN users u ON r.user_id = u.id
            WHERE r.message_id = ?
        ");
        $stmt->execute([$messageId]);
        $reactions = $stmt->fetchAll();
        echo json_encode(['success' => true, 'reactions' => $reactions]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function handleFileUploadAdmin($pdo, $senderId, $receiverId) {
    $file = $_FILES['file'];
    if ($file['error'] !== UPLOAD_ERR_OK) return false;

    $maxSize = 20 * 1024 * 1024; // 20MB
    if ($file['size'] > $maxSize) return false;

    $allowedTypes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'image/jpeg',
        'image/jpg',
        'image/png',
        'application/zip',
        'application/x-zip-compressed',
        'application/octet-stream'
    ];

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'zip'];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    $validMime = in_array($mimeType, $allowedTypes);
    $validExt = in_array($ext, $allowedExts);

    if (!$validMime && !$validExt) return false;

    $uploadDir = __DIR__ . '/uploads/chat_files/';
    if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);

    $fileName = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file['name']);
    $dest = $uploadDir . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $dest)) return false;

    $sizeMap = [
        'pdf' => 'PDF Document',
        'doc' => 'Word Document',
        'docx' => 'Word Document',
        'xls' => 'Excel Sheet',
        'xlsx' => 'Excel Sheet',
        'jpg' => 'JPEG Image',
        'jpeg' => 'JPEG Image',
        'png' => 'PNG Image',
        'zip' => 'ZIP Archive'
    ];
    $fileType = $sizeMap[$ext] ?? ucfirst(strtoupper($ext));

    $stmt = $pdo->prepare("
        INSERT INTO chat_files (sender_id, receiver_id, file_name, original_name, file_type, file_size, file_ext)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$senderId, $receiverId, $fileName, $file['name'], $fileType, $file['size'], $ext]);

    return $pdo->lastInsertId();
}
?>