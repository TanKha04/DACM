<?php
/**
 * Customer Messages - Trang tin nhắn cho Customer
 * Customer có thể kết bạn và nhắn tin với user khác (không phải admin)
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/messaging.php';

requireLogin();

$user = getCurrentUser();
$userId = $user['id'];
$isAdmin = $user['is_admin'] ?? 0;

// Redirect admin về trang admin messages
if ($isAdmin) {
    header('Location: ../admin/messages.php');
    exit;
}

// Lấy tab hiện tại
$tab = $_GET['tab'] ?? 'messages';

// Lấy danh sách conversations
$conversations = getConversations($userId);
$unreadCount = getUnreadMessageCount($userId);

// Lấy danh sách bạn bè
$friends = getFriends($userId);

// Lấy lời mời kết bạn
$pendingRequests = getPendingFriendRequests($userId);
$sentRequests = getSentFriendRequests($userId);

// Lấy danh sách người bị chặn
$blockedUsers = getBlockedUsers($userId);

// Lấy conversation_id từ URL nếu có
$currentConversationId = isset($_GET['conversation']) ? (int)$_GET['conversation'] : null;
$currentMessages = [];
$currentPartner = null;
$relationshipStatus = null;

if ($currentConversationId) {
    $currentMessages = getMessages($currentConversationId, $userId);
    
    // Lấy thông tin người đối thoại
    $conn = getConnection();
    $stmt = $conn->prepare("
        SELECT u.* FROM conversations c
        JOIN users u ON u.id = CASE WHEN c.user1_id = ? THEN c.user2_id ELSE c.user1_id END
        WHERE c.id = ?
    ");
    $stmt->execute([$userId, $currentConversationId]);
    $currentPartner = $stmt->fetch();
    
    if ($currentPartner) {
        $relationshipStatus = getRelationshipStatus($userId, $currentPartner['id']);
    }
}

$base = getBaseUrl();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tin nhắn - FastFood</title>
    <link rel="stylesheet" href="../assets/css/customer.css">
    <style>
        .messages-page {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .messages-layout {
            display: flex;
            gap: 20px;
            height: calc(100vh - 180px);
            min-height: 500px;
        }
        
        .sidebar-panel {
            width: 320px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        
        .tabs {
            display: flex;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .tab {
            flex: 1;
            padding: 15px 10px;
            text-align: center;
            cursor: pointer;
            font-size: 14px;
            border-bottom: 2px solid transparent;
            transition: all 0.3s;
            position: relative;
        }
        
        .tab:hover, .tab.active {
            background: #fff5f0;
            border-bottom-color: #ff6b35;
            color: #ff6b35;
        }
        
        .tab .badge {
            background: #ff6b35;
            color: white;
            border-radius: 50%;
            min-width: 18px;
            height: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            margin-left: 5px;
        }
        
        .sidebar-header {
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .sidebar-header h3 {
            margin: 0;
            font-size: 16px;
        }
        
        .btn-icon {
            background: #ff6b35;
            color: white;
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 18px;
        }
        
        .btn-icon:hover {
            background: #e55a2b;
        }
        
        .list-content {
            flex: 1;
            overflow-y: auto;
        }
        
        .list-item {
            padding: 12px 15px;
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.2s;
        }
        
        .list-item:hover, .list-item.active {
            background: #fff5f0;
        }
        
        .avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #ff6b35, #f7931e);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 16px;
            flex-shrink: 0;
        }
        
        .avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .item-info {
            flex: 1;
            overflow: hidden;
        }
        
        .item-name {
            font-weight: 600;
            margin-bottom: 3px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .item-meta {
            font-size: 13px;
            color: #666;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .role-badge {
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 8px;
            text-transform: capitalize;
        }
        
        .role-badge.customer { background: #e3f2fd; color: #1976d2; }
        .role-badge.seller { background: #fff3e0; color: #f57c00; }
        .role-badge.shipper { background: #e8f5e9; color: #388e3c; }
        
        .unread-dot {
            width: 10px;
            height: 10px;
            background: #ff6b35;
            border-radius: 50%;
        }
        
        .chat-panel {
            flex: 1;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .chat-header {
            padding: 15px 20px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .chat-header-info {
            flex: 1;
        }
        
        .chat-header-info h4 {
            margin: 0 0 4px 0;
        }
        
        .chat-actions {
            display: flex;
            gap: 8px;
        }
        
        .chat-actions button {
            padding: 8px 16px;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.3s;
        }
        
        .btn-friend {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .btn-friend:hover {
            background: #bbdefb;
        }
        
        .btn-block {
            background: #ffebee;
            color: #c62828;
        }
        
        .btn-block:hover {
            background: #ffcdd2;
        }
        
        .btn-unblock {
            background: #e8f5e9;
            color: #388e3c;
        }
        
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            background: #fafafa;
        }
        
        .message {
            max-width: 70%;
            padding: 12px 16px;
            border-radius: 18px;
            word-wrap: break-word;
        }
        
        .message.sent {
            align-self: flex-end;
            background: linear-gradient(135deg, #ff6b35, #f7931e);
            color: white;
            border-bottom-right-radius: 4px;
        }
        
        .message.received {
            align-self: flex-start;
            background: white;
            border-bottom-left-radius: 4px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        
        .message-time {
            font-size: 11px;
            opacity: 0.7;
            margin-top: 4px;
        }
        
        .chat-input {
            padding: 15px 20px;
            border-top: 1px solid #e0e0e0;
            display: flex;
            gap: 10px;
            background: white;
        }
        
        .chat-input input {
            flex: 1;
            padding: 12px 20px;
            border: 1px solid #e0e0e0;
            border-radius: 25px;
            outline: none;
            font-size: 14px;
        }
        
        .chat-input input:focus {
            border-color: #ff6b35;
        }
        
        .chat-input button {
            background: linear-gradient(135deg, #ff6b35, #f7931e);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 25px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .chat-input button:hover {
            transform: scale(1.05);
        }
        
        .empty-state {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
        }
        
        .empty-state-content {
            text-align: center;
        }
        
        .empty-state-content .icon {
            font-size: 64px;
            margin-bottom: 16px;
        }
        
        .blocked-notice {
            background: #ffebee;
            color: #c62828;
            padding: 15px 20px;
            text-align: center;
        }
        
        /* Friend request item */
        .request-actions {
            display: flex;
            gap: 6px;
        }
        
        .request-actions button {
            padding: 6px 12px;
            border: none;
            border-radius: 15px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .btn-accept {
            background: #4caf50;
            color: white;
        }
        
        .btn-reject {
            background: #f44336;
            color: white;
        }
        
        .btn-cancel {
            background: #9e9e9e;
            color: white;
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 12px;
            width: 420px;
            max-height: 80vh;
            overflow: hidden;
        }
        
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            margin: 0;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }
        
        .modal-body {
            padding: 20px;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .search-box {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #e0e0e0;
            border-radius: 25px;
            margin-bottom: 15px;
            font-size: 14px;
            outline: none;
        }
        
        .search-box:focus {
            border-color: #ff6b35;
        }
        
        @media (max-width: 768px) {
            .messages-layout {
                flex-direction: column;
                height: auto;
            }
            
            .sidebar-panel {
                width: 100%;
                height: 300px;
            }
            
            .chat-panel {
                height: 500px;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/customer_header.php'; ?>
    
    <div class="messages-page">
        <div class="messages-layout">
            <div class="sidebar-panel">
                <div class="tabs">
                    <div class="tab <?= $tab == 'messages' ? 'active' : '' ?>" onclick="switchTab('messages')">
                        💬 Tin nhắn
                        <?php if ($unreadCount > 0): ?><span class="badge"><?= $unreadCount ?></span><?php endif; ?>
                    </div>
                    <div class="tab <?= $tab == 'friends' ? 'active' : '' ?>" onclick="switchTab('friends')">
                        👥 Bạn bè
                        <?php if (count($pendingRequests) > 0): ?><span class="badge"><?= count($pendingRequests) ?></span><?php endif; ?>
                    </div>
                    <div class="tab <?= $tab == 'blocked' ? 'active' : '' ?>" onclick="switchTab('blocked')">
                        🚫 Đã chặn
                    </div>
                </div>
                
                <!-- Tab Messages -->
                <div id="tab-messages" class="tab-content" style="<?= $tab != 'messages' ? 'display:none' : '' ?>; display: flex; flex-direction: column; flex: 1;">
                    <div class="sidebar-header">
                        <h3>Cuộc hội thoại</h3>
                        <button class="btn-icon" onclick="openSearchModal()" title="Tìm người dùng">+</button>
                    </div>
                    <div class="list-content">
                        <?php if (empty($conversations)): ?>
                            <div style="padding: 40px; text-align: center; color: #999;">
                                <p>Chưa có cuộc hội thoại</p>
                                <p style="font-size: 13px;">Kết bạn để bắt đầu nhắn tin</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($conversations as $conv): ?>
                                <div class="list-item <?= $currentConversationId == $conv['id'] ? 'active' : '' ?>" 
                                     onclick="window.location.href='?conversation=<?= $conv['id'] ?>&tab=messages'">
                                    <div class="avatar">
                                        <?php if ($conv['other_user_avatar']): ?>
                                            <img src="../<?= htmlspecialchars($conv['other_user_avatar']) ?>" alt="">
                                        <?php else: ?>
                                            <?= strtoupper(substr($conv['other_user_name'], 0, 1)) ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="item-info">
                                        <div class="item-name"><?= htmlspecialchars($conv['other_user_name']) ?></div>
                                        <div class="item-meta"><?= htmlspecialchars($conv['last_message'] ?? 'Bắt đầu trò chuyện...') ?></div>
                                    </div>
                                    <?php if ($conv['unread_count'] > 0): ?>
                                        <div class="unread-dot"></div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Tab Friends -->
                <div id="tab-friends" class="tab-content" style="<?= $tab != 'friends' ? 'display:none' : '' ?>; display: flex; flex-direction: column; flex: 1;">
                    <div class="sidebar-header">
                        <h3>Bạn bè (<?= count($friends) ?>)</h3>
                        <button class="btn-icon" onclick="openSearchModal()" title="Thêm bạn">+</button>
                    </div>
                    <div class="list-content">
                        <?php if (count($pendingRequests) > 0): ?>
                            <div style="padding: 10px 15px; background: #fff3e0; font-weight: 600; font-size: 13px;">
                                📨 Lời mời kết bạn (<?= count($pendingRequests) ?>)
                            </div>
                            <?php foreach ($pendingRequests as $req): ?>
                                <div class="list-item">
                                    <div class="avatar">
                                        <?php if ($req['avatar']): ?>
                                            <img src="../<?= htmlspecialchars($req['avatar']) ?>" alt="">
                                        <?php else: ?>
                                            <?= strtoupper(substr($req['name'], 0, 1)) ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="item-info">
                                        <div class="item-name">
                                            <?= htmlspecialchars($req['name']) ?>
                                            <span class="role-badge <?= $req['role'] ?>"><?= $req['role'] ?></span>
                                        </div>
                                        <div class="item-meta"><?= htmlspecialchars($req['email']) ?></div>
                                    </div>
                                    <div class="request-actions">
                                        <button class="btn-accept" onclick="handleFriendRequest(<?= $req['id'] ?>, 'accept')">✓</button>
                                        <button class="btn-reject" onclick="handleFriendRequest(<?= $req['id'] ?>, 'reject')">✕</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <?php if (count($sentRequests) > 0): ?>
                            <div style="padding: 10px 15px; background: #e3f2fd; font-weight: 600; font-size: 13px;">
                                📤 Đã gửi (<?= count($sentRequests) ?>)
                            </div>
                            <?php foreach ($sentRequests as $req): ?>
                                <div class="list-item">
                                    <div class="avatar">
                                        <?php if ($req['avatar']): ?>
                                            <img src="../<?= htmlspecialchars($req['avatar']) ?>" alt="">
                                        <?php else: ?>
                                            <?= strtoupper(substr($req['name'], 0, 1)) ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="item-info">
                                        <div class="item-name"><?= htmlspecialchars($req['name']) ?></div>
                                        <div class="item-meta">Đang chờ chấp nhận...</div>
                                    </div>
                                    <button class="btn-cancel" onclick="cancelFriendRequest(<?= $req['id'] ?>)">Hủy</button>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <?php if (count($friends) > 0): ?>
                            <div style="padding: 10px 15px; background: #e8f5e9; font-weight: 600; font-size: 13px;">
                                👥 Danh sách bạn bè
                            </div>
                            <?php foreach ($friends as $friend): ?>
                                <div class="list-item" onclick="startConversation(<?= $friend['id'] ?>)">
                                    <div class="avatar">
                                        <?php if ($friend['avatar']): ?>
                                            <img src="../<?= htmlspecialchars($friend['avatar']) ?>" alt="">
                                        <?php else: ?>
                                            <?= strtoupper(substr($friend['name'], 0, 1)) ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="item-info">
                                        <div class="item-name">
                                            <?= htmlspecialchars($friend['name']) ?>
                                            <span class="role-badge <?= $friend['role'] ?>"><?= $friend['role'] ?></span>
                                        </div>
                                        <div class="item-meta"><?= htmlspecialchars($friend['email']) ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php elseif (count($pendingRequests) == 0 && count($sentRequests) == 0): ?>
                            <div style="padding: 40px; text-align: center; color: #999;">
                                <p>Chưa có bạn bè</p>
                                <p style="font-size: 13px;">Tìm và kết bạn với người dùng khác</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Tab Blocked -->
                <div id="tab-blocked" class="tab-content" style="<?= $tab != 'blocked' ? 'display:none' : '' ?>; display: flex; flex-direction: column; flex: 1;">
                    <div class="sidebar-header">
                        <h3>Đã chặn (<?= count($blockedUsers) ?>)</h3>
                    </div>
                    <div class="list-content">
                        <?php if (empty($blockedUsers)): ?>
                            <div style="padding: 40px; text-align: center; color: #999;">
                                <p>Chưa chặn ai</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($blockedUsers as $blocked): ?>
                                <div class="list-item">
                                    <div class="avatar">
                                        <?php if ($blocked['avatar']): ?>
                                            <img src="../<?= htmlspecialchars($blocked['avatar']) ?>" alt="">
                                        <?php else: ?>
                                            <?= strtoupper(substr($blocked['name'], 0, 1)) ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="item-info">
                                        <div class="item-name"><?= htmlspecialchars($blocked['name']) ?></div>
                                        <div class="item-meta"><?= htmlspecialchars($blocked['email']) ?></div>
                                    </div>
                                    <button class="btn-unblock" onclick="unblockUser(<?= $blocked['blocked_user_id'] ?>)">Bỏ chặn</button>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="chat-panel">
                <?php if ($currentPartner): ?>
                    <?php if ($relationshipStatus == 'blocked_by_them'): ?>
                        <div class="blocked-notice">
                            ⚠️ Bạn đã bị người này chặn và không thể gửi tin nhắn
                        </div>
                    <?php elseif ($relationshipStatus == 'blocked_by_me'): ?>
                        <div class="blocked-notice">
                            ⚠️ Bạn đã chặn người này. 
                            <button class="btn-unblock" style="margin-left: 10px;" onclick="unblockUser(<?= $currentPartner['id'] ?>)">Bỏ chặn</button>
                        </div>
                    <?php endif; ?>
                    
                    <div class="chat-header">
                        <div class="avatar">
                            <?php if ($currentPartner['avatar']): ?>
                                <img src="../<?= htmlspecialchars($currentPartner['avatar']) ?>" alt="">
                            <?php else: ?>
                                <?= strtoupper(substr($currentPartner['name'], 0, 1)) ?>
                            <?php endif; ?>
                        </div>
                        <div class="chat-header-info">
                            <h4>
                                <?= htmlspecialchars($currentPartner['name']) ?>
                                <span class="role-badge <?= $currentPartner['role'] ?>"><?= $currentPartner['role'] ?></span>
                            </h4>
                            <span style="font-size: 13px; color: #666;"><?= htmlspecialchars($currentPartner['email']) ?></span>
                        </div>
                        <div class="chat-actions">
                            <?php if ($relationshipStatus == 'none'): ?>
                                <button class="btn-friend" onclick="sendFriendRequest(<?= $currentPartner['id'] ?>)">➕ Kết bạn</button>
                            <?php elseif ($relationshipStatus == 'request_sent'): ?>
                                <button class="btn-friend" disabled>Đã gửi lời mời</button>
                            <?php elseif ($relationshipStatus == 'friends'): ?>
                                <button class="btn-friend" onclick="unfriend(<?= $currentPartner['id'] ?>)">✓ Bạn bè</button>
                            <?php endif; ?>
                            
                            <?php if ($relationshipStatus != 'blocked_by_me' && $relationshipStatus != 'blocked_by_them'): ?>
                                <button class="btn-block" onclick="blockUser(<?= $currentPartner['id'] ?>)">🚫 Chặn</button>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="chat-messages" id="chatMessages">
                        <?php foreach ($currentMessages as $msg): ?>
                            <div class="message <?= $msg['sender_id'] == $userId ? 'sent' : 'received' ?>">
                                <div><?= nl2br(htmlspecialchars($msg['content'])) ?></div>
                                <div class="message-time"><?= date('H:i d/m', strtotime($msg['created_at'])) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if ($relationshipStatus != 'blocked_by_them' && $relationshipStatus != 'blocked_by_me'): ?>
                        <form class="chat-input" id="messageForm">
                            <input type="hidden" name="receiver_id" value="<?= $currentPartner['id'] ?>">
                            <input type="text" name="content" placeholder="Nhập tin nhắn..." autocomplete="off" required>
                            <button type="submit">Gửi</button>
                        </form>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-content">
                            <div class="icon">💬</div>
                            <h3>Chào mừng đến với Tin nhắn</h3>
                            <p>Chọn một cuộc hội thoại hoặc kết bạn để bắt đầu nhắn tin</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Modal tìm kiếm người dùng -->
    <div class="modal" id="searchModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Tìm người dùng</h3>
                <button class="modal-close" onclick="closeSearchModal()">&times;</button>
            </div>
            <div class="modal-body">
                <input type="text" class="search-box" placeholder="Nhập tên hoặc email..." id="searchInput" onkeyup="searchUsers()">
                <div id="searchResults">
                    <p style="text-align: center; color: #999; padding: 20px;">Nhập để tìm kiếm người dùng</p>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../includes/customer_footer.php'; ?>
    
    <script>
        // Scroll to bottom of messages
        const chatMessages = document.getElementById('chatMessages');
        if (chatMessages) {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
        
        // Tab switching
        function switchTab(tab) {
            window.location.href = '?tab=' + tab;
        }
        
        // Send message
        const messageForm = document.getElementById('messageForm');
        if (messageForm) {
            messageForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                
                fetch('../api/messages.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.message);
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Lỗi gửi tin nhắn');
                });
            });
        }
        
        // Modal functions
        function openSearchModal() {
            document.getElementById('searchModal').classList.add('active');
            document.getElementById('searchInput').focus();
        }
        
        function closeSearchModal() {
            document.getElementById('searchModal').classList.remove('active');
        }
        
        // Search users
        let searchTimeout;
        function searchUsers() {
            clearTimeout(searchTimeout);
            const keyword = document.getElementById('searchInput').value;
            
            if (keyword.length < 2) {
                document.getElementById('searchResults').innerHTML = '<p style="text-align: center; color: #999; padding: 20px;">Nhập ít nhất 2 ký tự</p>';
                return;
            }
            
            searchTimeout = setTimeout(() => {
                fetch('../api/messages.php?action=search&keyword=' + encodeURIComponent(keyword))
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.users.length > 0) {
                        let html = '';
                        data.users.forEach(user => {
                            html += `
                                <div class="list-item" onclick="handleUserClick(${user.id}, '${user.relationship}')">
                                    <div class="avatar">${user.avatar ? '<img src="../' + user.avatar + '">' : user.name.charAt(0).toUpperCase()}</div>
                                    <div class="item-info">
                                        <div class="item-name">
                                            ${user.name}
                                            <span class="role-badge ${user.role}">${user.role}</span>
                                        </div>
                                        <div class="item-meta">${user.email}</div>
                                    </div>
                                    ${getActionButton(user.id, user.relationship)}
                                </div>
                            `;
                        });
                        document.getElementById('searchResults').innerHTML = html;
                    } else {
                        document.getElementById('searchResults').innerHTML = '<p style="text-align: center; color: #999; padding: 20px;">Không tìm thấy người dùng</p>';
                    }
                });
            }, 300);
        }
        
        function getActionButton(userId, relationship) {
            switch (relationship) {
                case 'friends':
                    return '<button class="btn-friend" onclick="event.stopPropagation(); startConversation(' + userId + ')">💬 Nhắn tin</button>';
                case 'request_sent':
                    return '<span style="color: #666; font-size: 12px;">Đã gửi lời mời</span>';
                case 'request_received':
                    return '<button class="btn-accept" onclick="event.stopPropagation(); acceptRequest(' + userId + ')">Chấp nhận</button>';
                case 'blocked_by_me':
                    return '<span style="color: #c62828; font-size: 12px;">Đã chặn</span>';
                default:
                    return '<button class="btn-friend" onclick="event.stopPropagation(); sendFriendRequest(' + userId + ')">➕ Kết bạn</button>';
            }
        }
        
        function handleUserClick(userId, relationship) {
            if (relationship === 'friends') {
                startConversation(userId);
            }
        }
        
        // Friend functions
        function sendFriendRequest(userId) {
            fetch('../api/messages.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=friend_request&user_id=' + userId
            })
            .then(res => res.json())
            .then(data => {
                alert(data.message);
                if (data.success) location.reload();
            });
        }
        
        function handleFriendRequest(requestId, action) {
            fetch('../api/messages.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=' + action + '_friend&request_id=' + requestId
            })
            .then(res => res.json())
            .then(data => {
                alert(data.message);
                if (data.success) location.reload();
            });
        }
        
        function cancelFriendRequest(requestId) {
            fetch('../api/messages.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=cancel_friend&request_id=' + requestId
            })
            .then(res => res.json())
            .then(data => {
                alert(data.message);
                if (data.success) location.reload();
            });
        }
        
        function unfriend(userId) {
            if (confirm('Bạn có chắc muốn hủy kết bạn?')) {
                fetch('../api/messages.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=unfriend&user_id=' + userId
                })
                .then(res => res.json())
                .then(data => {
                    alert(data.message);
                    if (data.success) location.reload();
                });
            }
        }
        
        // Block functions
        function blockUser(userId) {
            if (confirm('Bạn có chắc muốn chặn người này?')) {
                fetch('../api/messages.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=block&user_id=' + userId
                })
                .then(res => res.json())
                .then(data => {
                    alert(data.message);
                    if (data.success) location.reload();
                });
            }
        }
        
        function unblockUser(userId) {
            fetch('../api/messages.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=unblock&user_id=' + userId
            })
            .then(res => res.json())
            .then(data => {
                alert(data.message);
                if (data.success) location.reload();
            });
        }
        
        // Start conversation
        function startConversation(userId) {
            fetch('../api/messages.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=start_conversation&user_id=' + userId
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.href = '?conversation=' + data.conversation_id + '&tab=messages';
                } else {
                    alert(data.message);
                }
            });
        }
        
        // Auto refresh messages
        <?php if ($currentConversationId): ?>
        setInterval(() => {
            fetch('../api/messages.php?action=get_messages&conversation_id=<?= $currentConversationId ?>')
            .then(res => res.json())
            .then(data => {
                if (data.success && data.messages.length > 0) {
                    const currentCount = document.querySelectorAll('.message').length;
                    if (data.messages.length > currentCount) {
                        location.reload();
                    }
                }
            });
        }, 5000);
        <?php endif; ?>
    </script>
</body>
</html>
