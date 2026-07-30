<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$userId = $_SESSION['user_id'];
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'get_info':
        $fileId = intval($_POST['file_id'] ?? 0);
        if ($fileId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid file']);
            exit;
        }

        // Get file info and verify access
        $stmt = $pdo->prepare("
            SELECT cf.id, cf.file_name, cf.original_name, cf.file_type, cf.file_size, cf.file_ext,
                   cf.sender_id, cf.receiver_id
            FROM chat_files cf
            WHERE cf.id = ?
        ");
        $stmt->execute([$fileId]);
        $file = $stmt->fetch();

        if (!$file) {
            echo json_encode(['success' => false, 'message' => 'File not found']);
            exit;
        }

        // Verify access: user must be sender or receiver
        if ($userId != $file['sender_id'] && $userId != $file['receiver_id']) {
            // Admin exception: admin can access files in conversations they're part of
            if (!$isAdmin || $file['sender_id'] != 1 || $file['receiver_id'] == 1) {
                echo json_encode(['success' => false, 'message' => 'Access denied']);
                exit;
            }
        }

        echo json_encode(['success' => true, 'file' => $file]);
        break;

    case 'download':
        $fileId = intval($_POST['file_id'] ?? 0);
        if ($fileId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid file']);
            exit;
        }

        // Get file info and verify access
        $stmt = $pdo->prepare("
            SELECT cf.id, cf.file_name, cf.original_name, cf.file_type, cf.file_size, cf.file_ext,
                   cf.sender_id, cf.receiver_id
            FROM chat_files cf
            WHERE cf.id = ?
        ");
        $stmt->execute([$fileId]);
        $file = $stmt->fetch();

        if (!$file) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'File not found']);
            exit;
        }

        // Verify access
        if ($userId != $file['sender_id'] && $userId != $file['receiver_id']) {
            if (!$isAdmin) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Access denied']);
                exit;
            }
        }

        $filePath = __DIR__ . '/uploads/chat_files/' . $file['file_name'];
        if (!file_exists($filePath)) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'File not found on server']);
            exit;
        }

        // Set headers for file download
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($file['original_name']) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));

        // Clean output buffer
        ob_clean();
        flush();
        readfile($filePath);
        exit;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>