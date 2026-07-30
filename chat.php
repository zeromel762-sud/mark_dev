<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$userId = $_SESSION['user_id'];
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
$action = $_POST['action'] ?? '';

$GLOBALS['current_user_id'] = $userId;

switch ($action) {
    case 'get_conversations':
        if ($isAdmin) {
            // Admin: show ALL non-admin users with last message
            $stmt = $pdo->query("
                SELECT u.id, u.username, u.status, u.last_login,
                       IFNULL((SELECT COUNT(*) FROM chat_messages WHERE sender_id = u.id AND receiver_id = " . (int)$userId . " AND seen = 0), 0) as unread,
                       (SELECT message FROM chat_messages WHERE (sender_id = u.id AND receiver_id = " . (int)$userId . ") OR (sender_id = " . (int)$userId . " AND receiver_id = u.id) ORDER BY id DESC LIMIT 1) as last_message,
                       (SELECT created_at FROM chat_messages WHERE (sender_id = u.id AND receiver_id = " . (int)$userId . ") OR (sender_id = " . (int)$userId . " AND receiver_id = u.id) ORDER BY id DESC LIMIT 1) as last_message_time
                FROM users u
                WHERE u.id != " . (int)$userId . " AND u.is_admin = 0
                ORDER BY IFNULL(last_message_time, ''), u.username ASC
            ");
        } else {
            // Regular user: show conversation with admin (always available)
            $stmt = $pdo->query("
                SELECT u.id, u.username, u.status, u.last_login,
                       IFNULL((SELECT COUNT(*) FROM chat_messages WHERE sender_id = u.id AND receiver_id = " . (int)$userId . " AND seen = 0), 0) as unread,
                       (SELECT message FROM chat_messages WHERE (sender_id = u.id AND receiver_id = " . (int)$userId . ") OR (sender_id = " . (int)$userId . " AND receiver_id = u.id) ORDER BY id DESC LIMIT 1) as last_message,
                       (SELECT created_at FROM chat_messages WHERE (sender_id = u.id AND receiver_id = " . (int)$userId . ") OR (sender_id = " . (int)$userId . " AND receiver_id = u.id) ORDER BY id DESC LIMIT 1) as last_message_time
                FROM users u
                WHERE u.id = 1
            ");
        }
        $conversations = $stmt->fetchAll();
        echo json_encode(['success' => true, 'conversations' => $conversations]);
        break;

    case 'get_messages':
        $otherId = intval($_POST['user_id'] ?? 0);
        if ($otherId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid user']);
            exit;
        }

        // Security: verify the other user is valid
        if ($isAdmin) {
            // Admin can only view non-admin users
            $stmt = $pdo->prepare("SELECT id, is_admin FROM users WHERE id = ?");
            $stmt->execute([$otherId]);
            $other = $stmt->fetch();
            if (!$other || $other['is_admin'] == 1) {
                echo json_encode(['success' => false, 'message' => 'Access denied']);
                exit;
            }
        } else {
            // Regular users can only chat with admin
            if ($otherId != 1) {
                echo json_encode(['success' => false, 'message' => 'Access denied']);
                exit;
            }
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
            $stmt->execute([$lastId, $userId, $otherId, $otherId, $userId]);
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
            $stmt->execute([$userId, $otherId, $otherId, $userId]);
        }
        $messages = $stmt->fetchAll();

        // Mark messages as seen
        $stmt = $pdo->prepare("UPDATE chat_messages SET seen = 1 WHERE sender_id = ? AND receiver_id = ? AND seen = 0");
        $stmt->execute([$otherId, $userId]);

        echo json_encode(['success' => true, 'messages' => $messages]);
        break;

    case 'send':
        $message = trim($_POST['message'] ?? '');
        $toUserId = intval($_POST['to_user_id'] ?? 0);

        if (empty($message) && empty($_FILES['file']['name'])) {
            echo json_encode(['success' => false, 'message' => 'Message or file is required']);
            exit;
        }

        if ($toUserId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid recipient']);
            exit;
        }

        // Security: verify recipient and block status
        if ($isAdmin) {
            $stmt = $pdo->prepare("SELECT id, is_admin FROM users WHERE id = ?");
            $stmt->execute([$toUserId]);
            $recipient = $stmt->fetch();
            if (!$recipient || $recipient['is_admin'] == 1) {
                echo json_encode(['success' => false, 'message' => 'Access denied']);
                exit;
            }
        } else {
            if ($toUserId != 1) {
                echo json_encode(['success' => false, 'message' => 'Access denied']);
                exit;
            }
            // Check if the OTHER user (admin) blocked the sender
            $stmt = $pdo->prepare("SELECT blocked FROM users WHERE id = ?");
            $stmt->execute([$toUserId]);
            $other = $stmt->fetch();
            if ($other && $other['blocked'] == 1) {
                echo json_encode(['success' => false, 'message' => 'You have been blocked from sending messages']);
                exit;
            }
        }

        $fileId = null;

        // Handle file upload if present
        if (!empty($_FILES['file']['name'])) {
            $fileId = handleFileUpload($pdo, $userId, $toUserId);
            if ($fileId === false) {
                echo json_encode(['success' => false, 'message' => 'File upload failed. Invalid type or size.']);
                exit;
            }
        }

        $stmt = $pdo->prepare("INSERT INTO chat_messages (sender_id, receiver_id, message, file_id, seen) VALUES (?, ?, ?, ?, 0)");
        $stmt->execute([$userId, $toUserId, $message ?: null, $fileId]);

        echo json_encode(['success' => true, 'message' => 'Message sent']);
        break;

    case 'online_users':
        // Admin gets all online users, regular users see if admin is online
        if ($isAdmin) {
            $stmt = $pdo->query("SELECT id, username, status FROM users WHERE status = 'online' AND id != " . (int)$userId);
        } else {
            $stmt = $pdo->query("SELECT id, username, status FROM users WHERE status = 'online' AND id = 1");
        }
        $users = $stmt->fetchAll();
        echo json_encode(['success' => true, 'users' => $users]);
        break;

    case 'unread_count':
        if ($isAdmin) {
            $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM chat_messages WHERE receiver_id = 1 AND seen = 0");
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM chat_messages WHERE receiver_id = ? AND seen = 0");
            $stmt->execute([$userId]);
        }
        $result = $stmt->fetch();
        echo json_encode(['success' => true, 'unread' => $result['cnt']]);
        break;

    case 'edit_message':
        $messageId = intval($_POST['message_id'] ?? 0);
        $newMessage = trim($_POST['message'] ?? '');
        if ($messageId <= 0 || empty($newMessage)) {
            echo json_encode(['success' => false, 'message' => 'Invalid request']);
            exit;
        }
        // Verify ownership
        $stmt = $pdo->prepare("SELECT id, sender_id, receiver_id FROM chat_messages WHERE id = ?");
        $stmt->execute([$messageId]);
        $msg = $stmt->fetch();
        if (!$msg || $msg['sender_id'] != $userId) {
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
        // Verify ownership or admin/conversation access
        $stmt = $pdo->prepare("SELECT id, sender_id, receiver_id FROM chat_messages WHERE id = ?");
        $stmt->execute([$messageId]);
        $msg = $stmt->fetch();
        if (!$msg) {
            echo json_encode(['success' => false, 'message' => 'Message not found']);
            exit;
        }
        // Sender can delete; receiver can delete if admin? No, keep private: only sender can delete
        if ($msg['sender_id'] != $userId) {
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
        // Verify message exists and user is part of conversation
        $stmt = $pdo->prepare("SELECT id, sender_id, receiver_id FROM chat_messages WHERE id = ?");
        $stmt->execute([$messageId]);
        $msg = $stmt->fetch();
        if (!$msg || ($msg['sender_id'] != $userId && $msg['receiver_id'] != $userId)) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit;
        }
        $stmt = $pdo->prepare("INSERT IGNORE INTO message_reactions (message_id, user_id, reaction) VALUES (?, ?, ?)");
        $stmt->execute([$messageId, $userId, $reaction]);
        echo json_encode(['success' => true, 'message' => 'Reaction added']);
        break;

    case 'remove_reaction':
        $messageId = intval($_POST['message_id'] ?? 0);
        $reaction = trim($_POST['reaction'] ?? '');
        $stmt = $pdo->prepare("DELETE FROM message_reactions WHERE message_id = ? AND user_id = ? AND reaction = ?");
        $stmt->execute([$messageId, $userId, $reaction]);
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

function handleFileUpload($pdo, $senderId, $receiverId) {
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

    // Check by MIME type and extension
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