<?php
/**
 * Messages API
 * API xử lý tin nhắn, kết bạn, chặn người dùng
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/messaging.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập']);
    exit;
}

$user = getCurrentUser();
$userId = $user['id'];
$isAdmin = $user['is_admin'] ?? 0;

// Handle GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'get_messages':
            $conversationId = (int)($_GET['conversation_id'] ?? 0);
            if (!$conversationId) {
                echo json_encode(['success' => false, 'message' => 'Thiếu conversation_id']);
                exit;
            }
            
            $messages = getMessages($conversationId, $userId);
            echo json_encode(['success' => true, 'messages' => $messages]);
            break;
            
        case 'get_conversations':
            $conversations = getConversations($userId);
            echo json_encode(['success' => true, 'conversations' => $conversations]);
            break;
            
        case 'get_friends':
            $friends = getFriends($userId);
            echo json_encode(['success' => true, 'friends' => $friends]);
            break;
            
        case 'get_pending_requests':
            $requests = getPendingFriendRequests($userId);
            echo json_encode(['success' => true, 'requests' => $requests]);
            break;
            
        case 'get_blocked':
            $blocked = getBlockedUsers($userId);
            echo json_encode(['success' => true, 'blocked' => $blocked]);
            break;
            
        case 'unread_count':
            $count = getUnreadMessageCount($userId);
            echo json_encode(['success' => true, 'count' => $count]);
            break;
            
        case 'search':
            $keyword = $_GET['keyword'] ?? '';
            if (strlen($keyword) < 2) {
                echo json_encode(['success' => false, 'message' => 'Từ khóa quá ngắn']);
                exit;
            }
            
            $users = searchUsersForMessaging($userId, $keyword, $isAdmin);
            
            // Thêm thông tin relationship cho mỗi user
            foreach ($users as &$u) {
                $u['relationship'] = getRelationshipStatus($userId, $u['id']);
            }
            
            echo json_encode(['success' => true, 'users' => $users]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Action không hợp lệ']);
    }
    exit;
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Gửi tin nhắn mặc định
    if (empty($action) && isset($_POST['receiver_id']) && isset($_POST['content'])) {
        $receiverId = (int)$_POST['receiver_id'];
        $content = trim($_POST['content']);
        
        if (empty($content)) {
            echo json_encode(['success' => false, 'message' => 'Nội dung tin nhắn trống']);
            exit;
        }
        
        $result = sendMessage($userId, $receiverId, $content);
        echo json_encode($result);
        exit;
    }
    
    switch ($action) {
        case 'send_message':
            $receiverId = (int)($_POST['receiver_id'] ?? 0);
            $content = trim($_POST['content'] ?? '');
            
            if (!$receiverId || empty($content)) {
                echo json_encode(['success' => false, 'message' => 'Thiếu thông tin']);
                exit;
            }
            
            $result = sendMessage($userId, $receiverId, $content);
            echo json_encode($result);
            break;
            
        case 'start_conversation':
            $targetUserId = (int)($_POST['user_id'] ?? 0);
            if (!$targetUserId) {
                echo json_encode(['success' => false, 'message' => 'Thiếu user_id']);
                exit;
            }
            
            // Kiểm tra quyền nhắn tin
            if (!canMessage($userId, $targetUserId)) {
                echo json_encode(['success' => false, 'message' => 'Bạn không thể nhắn tin cho người này']);
                exit;
            }
            
            // Kiểm tra block
            if (isBlocked($userId, $targetUserId) || isBlocked($targetUserId, $userId)) {
                echo json_encode(['success' => false, 'message' => 'Không thể bắt đầu cuộc hội thoại']);
                exit;
            }
            
            $conversationId = getOrCreateConversation($userId, $targetUserId);
            echo json_encode(['success' => true, 'conversation_id' => $conversationId]);
            break;
            
        case 'friend_request':
            $friendId = (int)($_POST['user_id'] ?? 0);
            if (!$friendId) {
                echo json_encode(['success' => false, 'message' => 'Thiếu user_id']);
                exit;
            }
            
            $result = sendFriendRequest($userId, $friendId);
            echo json_encode($result);
            break;
            
        case 'accept_friend':
            $requestId = (int)($_POST['request_id'] ?? 0);
            if (!$requestId) {
                echo json_encode(['success' => false, 'message' => 'Thiếu request_id']);
                exit;
            }
            
            $result = acceptFriendRequest($userId, $requestId);
            echo json_encode($result);
            break;
            
        case 'reject_friend':
            $requestId = (int)($_POST['request_id'] ?? 0);
            if (!$requestId) {
                echo json_encode(['success' => false, 'message' => 'Thiếu request_id']);
                exit;
            }
            
            $result = rejectFriendRequest($userId, $requestId);
            echo json_encode($result);
            break;
            
        case 'cancel_friend':
            $requestId = (int)($_POST['request_id'] ?? 0);
            if (!$requestId) {
                echo json_encode(['success' => false, 'message' => 'Thiếu request_id']);
                exit;
            }
            
            // Hủy lời mời kết bạn đã gửi
            $conn = getConnection();
            $stmt = $conn->prepare("DELETE FROM friendships WHERE id = ? AND user_id = ? AND status = 'pending'");
            $stmt->execute([$requestId, $userId]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Đã hủy lời mời kết bạn']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Không tìm thấy lời mời']);
            }
            break;
            
        case 'unfriend':
            $friendId = (int)($_POST['user_id'] ?? 0);
            if (!$friendId) {
                echo json_encode(['success' => false, 'message' => 'Thiếu user_id']);
                exit;
            }
            
            $result = unfriend($userId, $friendId);
            echo json_encode($result);
            break;
            
        case 'block':
            $blockedUserId = (int)($_POST['user_id'] ?? 0);
            $reason = trim($_POST['reason'] ?? '');
            
            if (!$blockedUserId) {
                echo json_encode(['success' => false, 'message' => 'Thiếu user_id']);
                exit;
            }
            
            // Hủy kết bạn nếu đang là bạn
            unfriend($userId, $blockedUserId);
            
            $result = blockUser($userId, $blockedUserId, $reason);
            echo json_encode($result);
            break;
            
        case 'unblock':
            $blockedUserId = (int)($_POST['user_id'] ?? 0);
            if (!$blockedUserId) {
                echo json_encode(['success' => false, 'message' => 'Thiếu user_id']);
                exit;
            }
            
            $result = unblockUser($userId, $blockedUserId);
            echo json_encode($result);
            break;
            
        case 'mark_read':
            $conversationId = (int)($_POST['conversation_id'] ?? 0);
            if (!$conversationId) {
                echo json_encode(['success' => false, 'message' => 'Thiếu conversation_id']);
                exit;
            }
            
            $conn = getConnection();
            $stmt = $conn->prepare("
                UPDATE messages SET is_read = 1 
                WHERE conversation_id = ? AND receiver_id = ? AND is_read = 0
            ");
            $stmt->execute([$conversationId, $userId]);
            
            echo json_encode(['success' => true, 'message' => 'Đã đánh dấu đã đọc']);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Action không hợp lệ']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Method không hỗ trợ']);
