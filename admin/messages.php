<?php
/**
 * Admin Messages - Trang tin nhắn cho Admin
 * Admin chỉ có thể nhắn tin với admin khác
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/messaging.php';

requireAdmin();

$user = getCurrentUser();
$userId = $user['id'];

// Lấy danh sách conversations
$conversations = getConversations($userId);
$unreadCount = getUnreadMessageCount($userId);

// Lấy conversation_id từ URL nếu có
$currentConversationId = isset($_GET['conversation']) ? (int)$_GET['conversation'] : null;
$currentMessages = [];
$currentPartner = null;

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
}

// Lấy danh sách admin khác để nhắn tin
$conn = getConnection();
$stmt = $conn->prepare("SELECT id, name, email, avatar FROM users WHERE is_admin = 1 AND id != ? AND status = 'active'");
$stmt->execute([$userId]);
$otherAdmins = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tin nhắn - Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .messages-container {
            display: flex;
            height: calc(100vh - 80px);
            background: #f5f5f5;
        }
        
        .conversations-list {
            width: 320px;
            background: white;
            border-right: 1px solid #e0e0e0;
            overflow-y: auto;
        }
        
        .conversations-header {
            padding: 20px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .conversations-header h3 {
            margin: 0;
            font-size: 18px;
        }
        
        .new-message-btn {
            background: #ff6b35;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .new-message-btn:hover {
            background: #e55a2b;
        }
        
        .conversation-item {
            padding: 15px 20px;
            border-bottom: 1px solid #f0f0f0;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: background 0.2s;
        }
        
        .conversation-item:hover, .conversation-item.active {
            background: #fff5f0;
        }
        
        .conversation-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #ff6b35;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 18px;
        }
        
        .conversation-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .conversation-info {
            flex: 1;
            overflow: hidden;
        }
        
        .conversation-name {
            font-weight: 600;
            margin-bottom: 4px;
        }
        
        .conversation-preview {
            font-size: 13px;
            color: #666;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .unread-badge {
            background: #ff6b35;
            color: white;
            border-radius: 50%;
            min-width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
        }
        
        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .chat-header {
            padding: 15px 20px;
            background: white;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .chat-header-info h4 {
            margin: 0 0 4px 0;
        }
        
        .chat-header-info span {
            font-size: 13px;
            color: #666;
        }
        
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .message {
            max-width: 70%;
            padding: 12px 16px;
            border-radius: 18px;
            word-wrap: break-word;
        }
        
        .message.sent {
            align-self: flex-end;
            background: #ff6b35;
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
            background: white;
            border-top: 1px solid #e0e0e0;
            display: flex;
            gap: 10px;
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
            background: #ff6b35;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 25px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .chat-input button:hover {
            background: #e55a2b;
        }
        
        .empty-chat {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            font-size: 16px;
        }
        
        .admin-badge {
            background: #ff6b35;
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 11px;
            margin-left: 8px;
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
            width: 400px;
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
        
        .admin-list-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .admin-list-item:hover {
            background: #f5f5f5;
        }
        
        .search-input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include '../includes/admin_sidebar.php'; ?>
        
        <main class="main-content" style="padding: 0;">
            <div class="messages-container">
                <div class="conversations-list">
                    <div class="conversations-header">
                        <h3>💬 Tin nhắn <?php if ($unreadCount > 0): ?><span class="unread-badge"><?= $unreadCount ?></span><?php endif; ?></h3>
                        <button class="new-message-btn" onclick="openNewMessageModal()">+ Mới</button>
                    </div>
                    
                    <?php if (empty($conversations)): ?>
                        <div style="padding: 40px; text-align: center; color: #999;">
                            <p>Chưa có cuộc hội thoại nào</p>
                            <p style="font-size: 13px;">Bắt đầu nhắn tin với admin khác</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($conversations as $conv): ?>
                            <div class="conversation-item <?= $currentConversationId == $conv['id'] ? 'active' : '' ?>" 
                                 onclick="window.location.href='?conversation=<?= $conv['id'] ?>'">
                                <div class="conversation-avatar">
                                    <?php if ($conv['other_user_avatar']): ?>
                                        <img src="../<?= htmlspecialchars($conv['other_user_avatar']) ?>" alt="">
                                    <?php else: ?>
                                        <?= strtoupper(substr($conv['other_user_name'], 0, 1)) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="conversation-info">
                                    <div class="conversation-name">
                                        <?= htmlspecialchars($conv['other_user_name']) ?>
                                        <?php if ($conv['other_is_admin']): ?>
                                            <span class="admin-badge">Admin</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="conversation-preview">
                                        <?= htmlspecialchars($conv['last_message'] ?? 'Bắt đầu cuộc trò chuyện...') ?>
                                    </div>
                                </div>
                                <?php if ($conv['unread_count'] > 0): ?>
                                    <div class="unread-badge"><?= $conv['unread_count'] ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div class="chat-area">
                    <?php if ($currentPartner): ?>
                        <div class="chat-header">
                            <div class="conversation-avatar">
                                <?php if ($currentPartner['avatar']): ?>
                                    <img src="../<?= htmlspecialchars($currentPartner['avatar']) ?>" alt="">
                                <?php else: ?>
                                    <?= strtoupper(substr($currentPartner['name'], 0, 1)) ?>
                                <?php endif; ?>
                            </div>
                            <div class="chat-header-info">
                                <h4>
                                    <?= htmlspecialchars($currentPartner['name']) ?>
                                    <span class="admin-badge">Admin</span>
                                </h4>
                                <span><?= htmlspecialchars($currentPartner['email']) ?></span>
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
                        
                        <form class="chat-input" id="messageForm">
                            <input type="hidden" name="receiver_id" value="<?= $currentPartner['id'] ?>">
                            <input type="text" name="content" placeholder="Nhập tin nhắn..." autocomplete="off" required>
                            <button type="submit">Gửi</button>
                        </form>
                    <?php else: ?>
                        <div class="empty-chat">
                            <div style="text-align: center;">
                                <div style="font-size: 48px; margin-bottom: 16px;">💬</div>
                                <p>Chọn một cuộc hội thoại hoặc bắt đầu cuộc trò chuyện mới</p>
                                <p style="font-size: 13px; margin-top: 8px;">Admin chỉ có thể nhắn tin với admin khác</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Modal chọn admin để nhắn tin -->
    <div class="modal" id="newMessageModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Tin nhắn mới</h3>
                <button class="modal-close" onclick="closeNewMessageModal()">&times;</button>
            </div>
            <div class="modal-body">
                <input type="text" class="search-input" placeholder="Tìm admin..." id="searchAdmin" onkeyup="filterAdmins()">
                <div id="adminList">
                    <?php if (empty($otherAdmins)): ?>
                        <p style="text-align: center; color: #999;">Không có admin khác</p>
                    <?php else: ?>
                        <?php foreach ($otherAdmins as $admin): ?>
                            <div class="admin-list-item" onclick="startConversation(<?= $admin['id'] ?>)" data-name="<?= strtolower($admin['name']) ?>">
                                <div class="conversation-avatar" style="width: 40px; height: 40px; font-size: 14px;">
                                    <?php if ($admin['avatar']): ?>
                                        <img src="../<?= htmlspecialchars($admin['avatar']) ?>" alt="">
                                    <?php else: ?>
                                        <?= strtoupper(substr($admin['name'], 0, 1)) ?>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div style="font-weight: 600;"><?= htmlspecialchars($admin['name']) ?></div>
                                    <div style="font-size: 13px; color: #666;"><?= htmlspecialchars($admin['email']) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Scroll to bottom of messages
        const chatMessages = document.getElementById('chatMessages');
        if (chatMessages) {
            chatMessages.scrollTop = chatMessages.scrollHeight;
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
        
        function openNewMessageModal() {
            document.getElementById('newMessageModal').classList.add('active');
        }
        
        function closeNewMessageModal() {
            document.getElementById('newMessageModal').classList.remove('active');
        }
        
        function filterAdmins() {
            const search = document.getElementById('searchAdmin').value.toLowerCase();
            const items = document.querySelectorAll('.admin-list-item');
            items.forEach(item => {
                const name = item.getAttribute('data-name');
                item.style.display = name.includes(search) ? 'flex' : 'none';
            });
        }
        
        function startConversation(adminId) {
            fetch('../api/messages.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'action=start_conversation&user_id=' + adminId
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.href = '?conversation=' + data.conversation_id;
                } else {
                    alert(data.message);
                }
            });
        }
        
        // Auto refresh messages every 5 seconds
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
