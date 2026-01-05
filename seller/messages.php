<?php
/**
 * Seller Messages - Trang tin nhắn cho Seller
 * Seller có thể kết bạn và nhắn tin với user khác (không phải admin)
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/messaging.php';

requireLogin();

// Kiểm tra role seller hoặc admin có quyền truy cập
$user = getCurrentUser();
$userId = $user['id'];

// Redirect admin về trang admin messages
if ($user['is_admin']) {
    header('Location: ../admin/messages.php');
    exit;
}

// Nếu không phải seller thì redirect về trang messages tương ứng
if ($user['role'] !== 'seller') {
    if ($user['role'] === 'shipper') {
        header('Location: ../shipper/messages.php');
    } else {
        header('Location: ../customer/messages.php');
    }
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
    <title>Tin nhắn - Seller Panel</title>
    <link rel="stylesheet" href="../assets/css/seller.css?v=<?= time() ?>">
    <style>
        .messages-container {
            display: flex;
            height: calc(100vh - 60px);
            background: #f8fafc;
        }
        
        .sidebar-panel {
            width: 380px;
            background: white;
            border-right: 1px solid #e5e7eb;
            display: flex;
            flex-direction: column;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
        }
        
        .tabs {
            display: flex;
            border-bottom: 2px solid #e5e7eb;
            background: #f9fafb;
        }
        
        .tab {
            flex: 1;
            padding: 18px 12px;
            text-align: center;
            cursor: pointer;
            font-size: 15px;
            font-weight: 500;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
            color: #6b7280;
        }
        
        .tab:hover, .tab.active {
            background: #ecfdf5;
            border-bottom-color: #059669;
            color: #059669;
        }
        
        .tab .badge {
            background: #059669;
            color: white;
            border-radius: 50%;
            min-width: 22px;
            height: 22px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            margin-left: 8px;
            font-weight: 600;
        }
        
        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: white;
        }
        
        .sidebar-header h3 {
            margin: 0;
            font-size: 20px;
            font-weight: 600;
            color: #1f2937;
        }
        
        .btn-icon {
            background: linear-gradient(135deg, #059669, #047857);
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 22px;
            box-shadow: 0 4px 12px rgba(5,150,105,0.3);
            transition: all 0.3s;
        }
        
        .btn-icon:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 16px rgba(5,150,105,0.4);
        }
        
        .list-content {
            flex: 1;
            overflow-y: auto;
        }
        
        .list-item {
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            cursor: pointer;
            border-bottom: 1px solid #f3f4f6;
            transition: all 0.2s;
        }
        
        .list-item:hover, .list-item.active {
            background: #ecfdf5;
        }
        
        .avatar {
            width: 55px;
            height: 55px;
            border-radius: 50%;
            background: linear-gradient(135deg, #059669, #10b981);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 20px;
            flex-shrink: 0;
            box-shadow: 0 4px 10px rgba(5,150,105,0.2);
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
            font-size: 16px;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
            color: #1f2937;
        }
        
        .item-meta {
            font-size: 14px;
            color: #6b7280;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .role-badge {
            font-size: 11px;
            padding: 3px 8px;
            border-radius: 10px;
            text-transform: capitalize;
            font-weight: 500;
        }
        
        .role-badge.customer { background: #dbeafe; color: #1e40af; }
        .role-badge.seller { background: #dcfce7; color: #166534; }
        .role-badge.shipper { background: #fef3c7; color: #92400e; }
        
        .unread-dot {
            width: 12px;
            height: 12px;
            background: #059669;
            border-radius: 50%;
            box-shadow: 0 0 0 3px rgba(5,150,105,0.2);
        }
        
        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #f8fafc;
        }
        
        .chat-header {
            padding: 20px 25px;
            background: white;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .chat-header-info {
            flex: 1;
        }
        
        .chat-header-info h4 {
            margin: 0 0 6px 0;
            font-size: 18px;
            color: #1f2937;
        }
        
        .chat-actions {
            display: flex;
            gap: 10px;
        }
        
        .chat-actions button {
            padding: 10px 20px;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-friend {
            background: #ecfdf5;
            color: #059669;
        }
        
        .btn-friend:hover {
            background: #d1fae5;
        }
        
        .btn-block {
            background: #fee2e2;
            color: #dc2626;
        }
        
        .btn-block:hover {
            background: #fecaca;
        }
        
        .btn-unblock {
            background: #dcfce7;
            color: #166534;
        }
        
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 25px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            background: #f8fafc;
        }
        
        .message {
            max-width: 70%;
            padding: 14px 20px;
            border-radius: 20px;
            word-wrap: break-word;
            font-size: 15px;
            line-height: 1.5;
        }
        
        .message.sent {
            align-self: flex-end;
            background: linear-gradient(135deg, #059669, #10b981);
            color: white;
            border-bottom-right-radius: 6px;
            box-shadow: 0 4px 12px rgba(5,150,105,0.25);
        }
        
        .message.received {
            align-self: flex-start;
            background: white;
            border-bottom-left-radius: 6px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            color: #1f2937;
        }
        
        .message-time {
            font-size: 12px;
            opacity: 0.7;
            margin-top: 6px;
        }
        
        .chat-input {
            padding: 20px 25px;
            background: white;
            border-top: 1px solid #e5e7eb;
            display: flex;
            gap: 12px;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.05);
        }
        
        .chat-input input {
            flex: 1;
            padding: 14px 24px;
            border: 2px solid #e5e7eb;
            border-radius: 30px;
            outline: none;
            font-size: 15px;
            transition: all 0.3s;
        }
        
        .chat-input input:focus {
            border-color: #059669;
            box-shadow: 0 0 0 3px rgba(5,150,105,0.1);
        }
        
        .chat-input button {
            background: linear-gradient(135deg, #059669, #047857);
            color: white;
            border: none;
            padding: 14px 30px;
            border-radius: 30px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(5,150,105,0.3);
            transition: all 0.3s;
        }
        
        .chat-input button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(5,150,105,0.4);
        }
        
        .empty-chat {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #9ca3af;
        }
        
        .empty-chat-content {
            text-align: center;
        }
        
        .empty-chat-content .icon {
            font-size: 80px;
            margin-bottom: 20px;
        }
        
        .empty-chat-content h3 {
            font-size: 24px;
            color: #374151;
            margin-bottom: 10px;
        }
        
        .empty-chat-content p {
            font-size: 16px;
        }
        
        .blocked-notice {
            background: #fee2e2;
            color: #dc2626;
            padding: 18px 25px;
            text-align: center;
            font-size: 15px;
            font-weight: 500;
        }
        
        /* Request actions */
        .request-actions {
            display: flex;
            gap: 8px;
        }
        
        .request-actions button {
            padding: 8px 16px;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-accept { background: #059669; color: white; }
        .btn-accept:hover { background: #047857; }
        .btn-reject { background: #ef4444; color: white; }
        .btn-reject:hover { background: #dc2626; }
        .btn-cancel { background: #9ca3af; color: white; }
        
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
            border-radius: 20px;
            width: 480px;
            max-height: 80vh;
            overflow: hidden;
            box-shadow: 0 25px 50px rgba(0,0,0,0.2);
        }
        
        .modal-header {
            padding: 25px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            margin: 0;
            font-size: 20px;
            color: #1f2937;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: #9ca3af;
            transition: color 0.3s;
        }
        
        .modal-close:hover {
            color: #ef4444;
        }
        
        .modal-body {
            padding: 25px;
            max-height: 450px;
            overflow-y: auto;
        }
        
        .search-box {
            width: 100%;
            padding: 14px 20px;
            border: 2px solid #e5e7eb;
            border-radius: 30px;
            margin-bottom: 20px;
            font-size: 15px;
            outline: none;
            transition: all 0.3s;
        }
        
        .search-box:focus {
            border-color: #059669;
            box-shadow: 0 0 0 3px rgba(5,150,105,0.1);
        }
    </style>
</head>
<body>
    <div class="seller-layout">
        <?php include '../includes/seller_sidebar.php'; ?>
        
        <main class="main-content" style="padding: 0;">
            <div class="messages-container">
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
                            🚫 Chặn
                        </div>
                    </div>
                    
                    <!-- Tab Messages -->
                    <div id="tab-messages" style="<?= $tab != 'messages' ? 'display:none' : '' ?>; display: flex; flex-direction: column; flex: 1;">
                        <div class="sidebar-header">
                            <h3>Cuộc hội thoại</h3>
                            <button class="btn-icon" onclick="openSearchModal()">+</button>
                        </div>
                        <div class="list-content">
                            <?php if (empty($conversations)): ?>
                                <div style="padding: 40px; text-align: center; color: #999;">
                                    <p>Chưa có cuộc hội thoại</p>
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
                                            <div class="item-meta"><?= htmlspecialchars($conv['last_message'] ?? '...') ?></div>
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
                    <div id="tab-friends" style="<?= $tab != 'friends' ? 'display:none' : '' ?>; display: flex; flex-direction: column; flex: 1;">
                        <div class="sidebar-header">
                            <h3>Bạn bè (<?= count($friends) ?>)</h3>
                            <button class="btn-icon" onclick="openSearchModal()">+</button>
                        </div>
                        <div class="list-content">
                            <?php if (count($pendingRequests) > 0): ?>
                                <div style="padding: 10px 15px; background: #fff3e0; font-weight: 600; font-size: 13px;">
                                    📨 Lời mời (<?= count($pendingRequests) ?>)
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
                                        </div>
                                        <div class="request-actions">
                                            <button class="btn-accept" onclick="handleFriendRequest(<?= $req['id'] ?>, 'accept')">✓</button>
                                            <button class="btn-reject" onclick="handleFriendRequest(<?= $req['id'] ?>, 'reject')">✕</button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            
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
                            
                            <?php if (empty($friends) && empty($pendingRequests)): ?>
                                <div style="padding: 40px; text-align: center; color: #999;">
                                    <p>Chưa có bạn bè</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Tab Blocked -->
                    <div id="tab-blocked" style="<?= $tab != 'blocked' ? 'display:none' : '' ?>; display: flex; flex-direction: column; flex: 1;">
                        <div class="sidebar-header">
                            <h3>Đã chặn</h3>
                        </div>
                        <div class="list-content">
                            <?php if (empty($blockedUsers)): ?>
                                <div style="padding: 40px; text-align: center; color: #999;">
                                    <p>Chưa chặn ai</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($blockedUsers as $blocked): ?>
                                    <div class="list-item">
                                        <div class="avatar"><?= strtoupper(substr($blocked['name'], 0, 1)) ?></div>
                                        <div class="item-info">
                                            <div class="item-name"><?= htmlspecialchars($blocked['name']) ?></div>
                                        </div>
                                        <button class="btn-unblock" onclick="unblockUser(<?= $blocked['blocked_user_id'] ?>)">Bỏ chặn</button>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="chat-area">
                    <?php if ($currentPartner): ?>
                        <?php if ($relationshipStatus == 'blocked_by_them' || $relationshipStatus == 'blocked_by_me'): ?>
                            <div class="blocked-notice">
                                ⚠️ <?= $relationshipStatus == 'blocked_by_them' ? 'Bạn đã bị chặn' : 'Bạn đã chặn người này' ?>
                                <?php if ($relationshipStatus == 'blocked_by_me'): ?>
                                    <button class="btn-unblock" style="margin-left: 10px;" onclick="unblockUser(<?= $currentPartner['id'] ?>)">Bỏ chặn</button>
                                <?php endif; ?>
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
                                <?php elseif ($relationshipStatus == 'friends'): ?>
                                    <button class="btn-friend" onclick="unfriend(<?= $currentPartner['id'] ?>)">✓ Bạn bè</button>
                                <?php endif; ?>
                                
                                <?php if ($relationshipStatus != 'blocked_by_me'): ?>
                                    <button class="btn-block" onclick="blockUser(<?= $currentPartner['id'] ?>)">🚫</button>
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
                        <div class="empty-chat">
                            <div class="empty-chat-content">
                                <div class="icon">💬</div>
                                <h3>Tin nhắn</h3>
                                <p>Chọn cuộc hội thoại để bắt đầu</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Modal -->
    <div class="modal" id="searchModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Tìm người dùng</h3>
                <button class="modal-close" onclick="closeSearchModal()">&times;</button>
            </div>
            <div class="modal-body">
                <input type="text" class="search-box" placeholder="Nhập tên hoặc email..." id="searchInput" onkeyup="searchUsers()">
                <div id="searchResults">
                    <p style="text-align: center; color: #999;">Nhập để tìm kiếm</p>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        const chatMessages = document.getElementById('chatMessages');
        if (chatMessages) chatMessages.scrollTop = chatMessages.scrollHeight;
        
        function switchTab(tab) {
            window.location.href = '?tab=' + tab;
        }
        
        const messageForm = document.getElementById('messageForm');
        if (messageForm) {
            messageForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                fetch('../api/messages.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) location.reload();
                    else alert(data.message);
                });
            });
        }
        
        function openSearchModal() {
            document.getElementById('searchModal').classList.add('active');
        }
        
        function closeSearchModal() {
            document.getElementById('searchModal').classList.remove('active');
        }
        
        let searchTimeout;
        function searchUsers() {
            clearTimeout(searchTimeout);
            const keyword = document.getElementById('searchInput').value;
            if (keyword.length < 2) return;
            
            searchTimeout = setTimeout(() => {
                fetch('../api/messages.php?action=search&keyword=' + encodeURIComponent(keyword))
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.users.length > 0) {
                        let html = '';
                        data.users.forEach(user => {
                            html += `<div class="list-item" onclick="startConversation(${user.id})">
                                <div class="avatar">${user.name.charAt(0).toUpperCase()}</div>
                                <div class="item-info">
                                    <div class="item-name">${user.name} <span class="role-badge ${user.role}">${user.role}</span></div>
                                    <div class="item-meta">${user.email}</div>
                                </div>
                            </div>`;
                        });
                        document.getElementById('searchResults').innerHTML = html;
                    } else {
                        document.getElementById('searchResults').innerHTML = '<p style="text-align: center; color: #999;">Không tìm thấy</p>';
                    }
                });
            }, 300);
        }
        
        function sendFriendRequest(userId) {
            fetch('../api/messages.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=friend_request&user_id=' + userId
            }).then(res => res.json()).then(data => { alert(data.message); if (data.success) location.reload(); });
        }
        
        function handleFriendRequest(requestId, action) {
            fetch('../api/messages.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=' + action + '_friend&request_id=' + requestId
            }).then(res => res.json()).then(data => { alert(data.message); if (data.success) location.reload(); });
        }
        
        function unfriend(userId) {
            if (confirm('Hủy kết bạn?')) {
                fetch('../api/messages.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=unfriend&user_id=' + userId
                }).then(res => res.json()).then(data => { alert(data.message); if (data.success) location.reload(); });
            }
        }
        
        function blockUser(userId) {
            if (confirm('Chặn người này?')) {
                fetch('../api/messages.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=block&user_id=' + userId
                }).then(res => res.json()).then(data => { alert(data.message); if (data.success) location.reload(); });
            }
        }
        
        function unblockUser(userId) {
            fetch('../api/messages.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=unblock&user_id=' + userId
            }).then(res => res.json()).then(data => { alert(data.message); if (data.success) location.reload(); });
        }
        
        function startConversation(userId) {
            fetch('../api/messages.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=start_conversation&user_id=' + userId
            }).then(res => res.json()).then(data => {
                if (data.success) window.location.href = '?conversation=' + data.conversation_id + '&tab=messages';
                else alert(data.message);
            });
        }
        
        <?php if ($currentConversationId): ?>
        setInterval(() => {
            fetch('../api/messages.php?action=get_messages&conversation_id=<?= $currentConversationId ?>')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const currentCount = document.querySelectorAll('.message').length;
                    if (data.messages.length > currentCount) location.reload();
                }
            });
        }, 5000);
        <?php endif; ?>
    </script>
</body>
</html>
