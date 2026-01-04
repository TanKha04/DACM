<?php
/**
 * Messaging Helper Functions
 * Các hàm hỗ trợ cho hệ thống tin nhắn
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Kiểm tra xem 2 user có thể nhắn tin cho nhau không
 * Admin chỉ nhắn được với admin, user thường nhắn với user thường
 */
function canMessage($userId, $targetUserId) {
    $conn = getConnection();
    
    // Lấy thông tin 2 user
    $stmt = $conn->prepare("SELECT id, is_admin FROM users WHERE id IN (?, ?)");
    $stmt->execute([$userId, $targetUserId]);
    $users = $stmt->fetchAll();
    
    if (count($users) != 2) {
        return false;
    }
    
    $user1 = $users[0]['id'] == $userId ? $users[0] : $users[1];
    $user2 = $users[0]['id'] == $targetUserId ? $users[0] : $users[1];
    
    // Admin chỉ nhắn với admin
    if ($user1['is_admin'] == 1) {
        return $user2['is_admin'] == 1;
    }
    
    // User thường chỉ nhắn với user thường
    return $user2['is_admin'] != 1;
}

/**
 * Kiểm tra xem user có bị block không
 */
function isBlocked($userId, $blockedByUserId) {
    $conn = getConnection();
    $stmt = $conn->prepare("SELECT id FROM blocked_users WHERE user_id = ? AND blocked_user_id = ?");
    $stmt->execute([$blockedByUserId, $userId]);
    return $stmt->fetch() !== false;
}

/**
 * Kiểm tra xem 2 user có phải bạn bè không
 */
function areFriends($userId1, $userId2) {
    $conn = getConnection();
    $stmt = $conn->prepare("
        SELECT id FROM friendships 
        WHERE ((user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?))
        AND status = 'accepted'
    ");
    $stmt->execute([$userId1, $userId2, $userId2, $userId1]);
    return $stmt->fetch() !== false;
}

/**
 * Lấy hoặc tạo conversation giữa 2 user
 */
function getOrCreateConversation($userId1, $userId2) {
    $conn = getConnection();
    
    // Sắp xếp ID để đảm bảo tính nhất quán
    $minId = min($userId1, $userId2);
    $maxId = max($userId1, $userId2);
    
    // Tìm conversation hiện có
    $stmt = $conn->prepare("
        SELECT id FROM conversations 
        WHERE (user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?)
    ");
    $stmt->execute([$minId, $maxId, $maxId, $minId]);
    $conversation = $stmt->fetch();
    
    if ($conversation) {
        return $conversation['id'];
    }
    
    // Tạo conversation mới
    $stmt = $conn->prepare("INSERT INTO conversations (user1_id, user2_id) VALUES (?, ?)");
    $stmt->execute([$minId, $maxId]);
    
    return $conn->lastInsertId();
}

/**
 * Gửi tin nhắn
 */
function sendMessage($senderId, $receiverId, $content) {
    // Kiểm tra quyền nhắn tin
    if (!canMessage($senderId, $receiverId)) {
        return ['success' => false, 'message' => 'Bạn không thể nhắn tin cho người này'];
    }
    
    // Kiểm tra block
    if (isBlocked($senderId, $receiverId)) {
        return ['success' => false, 'message' => 'Bạn đã bị người này chặn'];
    }
    
    if (isBlocked($receiverId, $senderId)) {
        return ['success' => false, 'message' => 'Bạn đã chặn người này'];
    }
    
    $conn = getConnection();
    
    try {
        $conn->beginTransaction();
        
        // Lấy hoặc tạo conversation
        $conversationId = getOrCreateConversation($senderId, $receiverId);
        
        // Chèn tin nhắn
        $stmt = $conn->prepare("
            INSERT INTO messages (conversation_id, sender_id, receiver_id, content) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$conversationId, $senderId, $receiverId, $content]);
        $messageId = $conn->lastInsertId();
        
        // Cập nhật last_message_at của conversation
        $stmt = $conn->prepare("UPDATE conversations SET last_message_at = NOW() WHERE id = ?");
        $stmt->execute([$conversationId]);
        
        $conn->commit();
        
        return ['success' => true, 'message_id' => $messageId, 'conversation_id' => $conversationId];
    } catch (Exception $e) {
        $conn->rollBack();
        return ['success' => false, 'message' => 'Lỗi khi gửi tin nhắn: ' . $e->getMessage()];
    }
}

/**
 * Lấy danh sách conversations của user
 */
function getConversations($userId) {
    $conn = getConnection();
    $stmt = $conn->prepare("
        SELECT c.*, 
            CASE 
                WHEN c.user1_id = ? THEN c.user2_id 
                ELSE c.user1_id 
            END as other_user_id,
            u.name as other_user_name,
            u.avatar as other_user_avatar,
            u.is_admin as other_is_admin,
            (SELECT content FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message,
            (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id AND receiver_id = ? AND is_read = 0) as unread_count
        FROM conversations c
        JOIN users u ON u.id = CASE WHEN c.user1_id = ? THEN c.user2_id ELSE c.user1_id END
        WHERE c.user1_id = ? OR c.user2_id = ?
        ORDER BY c.last_message_at DESC
    ");
    $stmt->execute([$userId, $userId, $userId, $userId, $userId]);
    return $stmt->fetchAll();
}

/**
 * Lấy tin nhắn trong conversation
 */
function getMessages($conversationId, $userId, $limit = 50, $offset = 0) {
    $conn = getConnection();
    
    // Đánh dấu đã đọc các tin nhắn
    $stmt = $conn->prepare("
        UPDATE messages SET is_read = 1 
        WHERE conversation_id = ? AND receiver_id = ? AND is_read = 0
    ");
    $stmt->execute([$conversationId, $userId]);
    
    // Lấy tin nhắn
    $stmt = $conn->prepare("
        SELECT m.*, u.name as sender_name, u.avatar as sender_avatar
        FROM messages m
        JOIN users u ON u.id = m.sender_id
        WHERE m.conversation_id = ?
        ORDER BY m.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$conversationId, $limit, $offset]);
    return array_reverse($stmt->fetchAll());
}

/**
 * Gửi lời mời kết bạn
 */
function sendFriendRequest($userId, $friendId) {
    if (!canMessage($userId, $friendId)) {
        return ['success' => false, 'message' => 'Bạn không thể kết bạn với người này'];
    }
    
    $conn = getConnection();
    
    // Kiểm tra đã có request chưa
    $stmt = $conn->prepare("
        SELECT * FROM friendships 
        WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)
    ");
    $stmt->execute([$userId, $friendId, $friendId, $userId]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        if ($existing['status'] == 'accepted') {
            return ['success' => false, 'message' => 'Các bạn đã là bạn bè'];
        }
        if ($existing['status'] == 'pending') {
            return ['success' => false, 'message' => 'Đã có lời mời kết bạn'];
        }
    }
    
    try {
        $stmt = $conn->prepare("INSERT INTO friendships (user_id, friend_id, status) VALUES (?, ?, 'pending')");
        $stmt->execute([$userId, $friendId]);
        return ['success' => true, 'message' => 'Đã gửi lời mời kết bạn'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
    }
}

/**
 * Chấp nhận lời mời kết bạn
 */
function acceptFriendRequest($userId, $requestId) {
    $conn = getConnection();
    $stmt = $conn->prepare("
        UPDATE friendships SET status = 'accepted', updated_at = NOW() 
        WHERE id = ? AND friend_id = ? AND status = 'pending'
    ");
    $stmt->execute([$requestId, $userId]);
    
    if ($stmt->rowCount() > 0) {
        return ['success' => true, 'message' => 'Đã chấp nhận lời mời kết bạn'];
    }
    return ['success' => false, 'message' => 'Không tìm thấy lời mời'];
}

/**
 * Từ chối lời mời kết bạn
 */
function rejectFriendRequest($userId, $requestId) {
    $conn = getConnection();
    $stmt = $conn->prepare("
        UPDATE friendships SET status = 'rejected', updated_at = NOW() 
        WHERE id = ? AND friend_id = ? AND status = 'pending'
    ");
    $stmt->execute([$requestId, $userId]);
    
    if ($stmt->rowCount() > 0) {
        return ['success' => true, 'message' => 'Đã từ chối lời mời kết bạn'];
    }
    return ['success' => false, 'message' => 'Không tìm thấy lời mời'];
}

/**
 * Hủy kết bạn
 */
function unfriend($userId, $friendId) {
    $conn = getConnection();
    $stmt = $conn->prepare("
        DELETE FROM friendships 
        WHERE ((user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?))
        AND status = 'accepted'
    ");
    $stmt->execute([$userId, $friendId, $friendId, $userId]);
    
    if ($stmt->rowCount() > 0) {
        return ['success' => true, 'message' => 'Đã hủy kết bạn'];
    }
    return ['success' => false, 'message' => 'Không tìm thấy quan hệ bạn bè'];
}

/**
 * Lấy danh sách bạn bè
 */
function getFriends($userId) {
    $conn = getConnection();
    $stmt = $conn->prepare("
        SELECT u.id, u.name, u.avatar, u.email, u.role, u.is_admin, f.created_at as friends_since
        FROM friendships f
        JOIN users u ON u.id = CASE WHEN f.user_id = ? THEN f.friend_id ELSE f.user_id END
        WHERE (f.user_id = ? OR f.friend_id = ?) AND f.status = 'accepted'
        ORDER BY u.name
    ");
    $stmt->execute([$userId, $userId, $userId]);
    return $stmt->fetchAll();
}

/**
 * Lấy lời mời kết bạn đang chờ
 */
function getPendingFriendRequests($userId) {
    $conn = getConnection();
    $stmt = $conn->prepare("
        SELECT f.*, u.name, u.avatar, u.email, u.role
        FROM friendships f
        JOIN users u ON u.id = f.user_id
        WHERE f.friend_id = ? AND f.status = 'pending'
        ORDER BY f.created_at DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/**
 * Lấy lời mời kết bạn đã gửi
 */
function getSentFriendRequests($userId) {
    $conn = getConnection();
    $stmt = $conn->prepare("
        SELECT f.*, u.name, u.avatar, u.email, u.role
        FROM friendships f
        JOIN users u ON u.id = f.friend_id
        WHERE f.user_id = ? AND f.status = 'pending'
        ORDER BY f.created_at DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/**
 * Chặn người dùng
 */
function blockUser($userId, $blockedUserId, $reason = '') {
    $conn = getConnection();
    
    try {
        $stmt = $conn->prepare("
            INSERT INTO blocked_users (user_id, blocked_user_id, reason) 
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE reason = ?, created_at = NOW()
        ");
        $stmt->execute([$userId, $blockedUserId, $reason, $reason]);
        return ['success' => true, 'message' => 'Đã chặn người dùng'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
    }
}

/**
 * Bỏ chặn người dùng
 */
function unblockUser($userId, $blockedUserId) {
    $conn = getConnection();
    $stmt = $conn->prepare("DELETE FROM blocked_users WHERE user_id = ? AND blocked_user_id = ?");
    $stmt->execute([$userId, $blockedUserId]);
    
    if ($stmt->rowCount() > 0) {
        return ['success' => true, 'message' => 'Đã bỏ chặn người dùng'];
    }
    return ['success' => false, 'message' => 'Người dùng không bị chặn'];
}

/**
 * Lấy danh sách người bị chặn
 */
function getBlockedUsers($userId) {
    $conn = getConnection();
    $stmt = $conn->prepare("
        SELECT b.*, u.name, u.avatar, u.email, u.role
        FROM blocked_users b
        JOIN users u ON u.id = b.blocked_user_id
        WHERE b.user_id = ?
        ORDER BY b.created_at DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/**
 * Đếm tin nhắn chưa đọc
 */
function getUnreadMessageCount($userId) {
    $conn = getConnection();
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM messages WHERE receiver_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    $result = $stmt->fetch();
    return $result['count'];
}

/**
 * Tìm kiếm user để nhắn tin
 */
function searchUsersForMessaging($userId, $keyword, $isAdmin = false) {
    $conn = getConnection();
    
    if ($isAdmin) {
        // Admin chỉ tìm admin khác
        $stmt = $conn->prepare("
            SELECT id, name, email, avatar, role 
            FROM users 
            WHERE id != ? AND is_admin = 1 AND status = 'active'
            AND (name LIKE ? OR email LIKE ?)
            LIMIT 20
        ");
    } else {
        // User thường tìm user thường
        $stmt = $conn->prepare("
            SELECT id, name, email, avatar, role 
            FROM users 
            WHERE id != ? AND is_admin = 0 AND status = 'active'
            AND (name LIKE ? OR email LIKE ?)
            LIMIT 20
        ");
    }
    
    $keyword = "%$keyword%";
    $stmt->execute([$userId, $keyword, $keyword]);
    return $stmt->fetchAll();
}

/**
 * Lấy trạng thái quan hệ giữa 2 user
 */
function getRelationshipStatus($userId, $targetUserId) {
    $conn = getConnection();
    
    // Kiểm tra block
    if (isBlocked($targetUserId, $userId)) {
        return 'blocked_by_me';
    }
    if (isBlocked($userId, $targetUserId)) {
        return 'blocked_by_them';
    }
    
    // Kiểm tra bạn bè
    $stmt = $conn->prepare("
        SELECT * FROM friendships 
        WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)
    ");
    $stmt->execute([$userId, $targetUserId, $targetUserId, $userId]);
    $friendship = $stmt->fetch();
    
    if ($friendship) {
        if ($friendship['status'] == 'accepted') {
            return 'friends';
        }
        if ($friendship['status'] == 'pending') {
            if ($friendship['user_id'] == $userId) {
                return 'request_sent';
            }
            return 'request_received';
        }
    }
    
    return 'none';
}
