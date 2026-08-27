<?php
// intern/chat.php
require_once '../config/config.php';
require_once '../config/language.php';

global $conn;

if (!isLoggedIn() || !hasRole('intern')) {
    header('Location: /interntrack/auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$selected_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

// Get supervisor (only one supervisor per intern)
$stmt = $conn->prepare("SELECT supervisor_id FROM interns WHERE user_id = ?");
$stmt->execute([$user_id]);
$supervisor_id = $stmt->fetchColumn();

// Get contacts (supervisor only)
$contacts = [];
if ($supervisor_id) {
    $stmt = $conn->prepare("SELECT id, first_name, last_name, is_active FROM users WHERE id = ?");
    $stmt->execute([$supervisor_id]);
    $contact = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($contact) {
        // Get unread count
        $stmt = $conn->prepare("SELECT COUNT(*) FROM messages WHERE sender_id = ? AND receiver_id = ? AND is_read = FALSE");
        $stmt->execute([$supervisor_id, $user_id]);
        $contact['unread_count'] = $stmt->fetchColumn();
        $contacts[] = $contact;
    }
}

// Get messages for selected contact
$messages = [];
if ($selected_user_id > 0 && $selected_user_id == $supervisor_id) {
    $stmt = $conn->prepare("
        SELECT * FROM messages 
        WHERE (sender_id = ? AND receiver_id = ?) 
           OR (sender_id = ? AND receiver_id = ?)
        ORDER BY created_at ASC
    ");
    $stmt->execute([$user_id, $selected_user_id, $selected_user_id, $user_id]);
    $messages = $stmt->fetchAll();
    
    // Mark messages as read
    $stmt = $conn->prepare("UPDATE messages SET is_read = TRUE WHERE sender_id = ? AND receiver_id = ?");
    $stmt->execute([$selected_user_id, $user_id]);
}

include_once '../includes/header.php';
?>

<div class="main-content">
    <div class="card" style="padding: 0; overflow: hidden; height: calc(100vh - 100px);">
        <div class="chat-container">
            <!-- Sidebar -->
            <div class="chat-sidebar">
                <div class="chat-search">
                    <input type="text" placeholder="<?php echo t('search'); ?> contacts..." id="chat-search">
                </div>
                <div class="chat-contacts" id="chat-contacts">
                    <?php if (!empty($contacts)): ?>
                        <?php foreach ($contacts as $contact): ?>
                            <div class="chat-contact <?php echo $selected_user_id == $contact['id'] ? 'active' : ''; ?>" 
                                 data-contact-id="<?php echo $contact['id']; ?>">
                                <div class="contact-avatar">
                                    <?php 
                                        $name = $contact['first_name'] . ' ' . $contact['last_name'];
                                        $parts = explode(' ', $name);
                                        echo strtoupper($parts[0][0] ?? 'U') . (isset($parts[1]) ? strtoupper($parts[1][0] ?? '') : '');
                                    ?>
                                </div>
                                <div class="contact-info">
                                    <div class="contact-name">
                                        <?php echo htmlspecialchars($contact['first_name'] . ' ' . $contact['last_name']); ?>
                                        <?php if ($contact['unread_count'] > 0): ?>
                                            <span class="badge" style="background: var(--primary-color); color: white; font-size: 10px; padding: 1px 6px; border-radius: 50%; margin-left: 4px;">
                                                <?php echo $contact['unread_count']; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="contact-last-msg">
                                        <?php echo $contact['is_active'] ? '🟢 Online' : '⚪ Offline'; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="padding: 20px; text-align: center; color: var(--gray-500);">
                            <?php echo t('no_supervisor_assigned'); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Chat Main -->
            <div class="chat-main">
                <?php if ($selected_user_id > 0 && $selected_user_id == $supervisor_id): ?>
                    <?php 
                        $contact = $contacts[0] ?? null;
                    ?>
                    <div class="chat-header">
                        <div class="contact-avatar" style="width: 40px; height: 40px; border-radius: 50%; background: var(--primary-color); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; flex-shrink: 0;">
                            <?php 
                                $name = $contact['first_name'] . ' ' . $contact['last_name'];
                                $parts = explode(' ', $name);
                                echo strtoupper($parts[0][0] ?? 'U') . (isset($parts[1]) ? strtoupper($parts[1][0] ?? '') : '');
                            ?>
                        </div>
                        <div>
                            <div style="font-weight: 600;"><?php echo htmlspecialchars($contact['first_name'] . ' ' . $contact['last_name']); ?></div>
                            <div style="font-size: 12px; color: var(--gray-500);">
                                <?php echo $contact['is_active'] ? '🟢 Online' : '⚪ Offline'; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="chat-messages" id="chat-messages">
                        <?php 
                        $last_date = '';
                        if (!empty($messages)): 
                            foreach ($messages as $msg):
                                $msg_date = date('Y-m-d', strtotime($msg['created_at']));
                                $display_date = '';
                                
                                if ($msg_date != $last_date) {
                                    $last_date = $msg_date;
                                    $today = date('Y-m-d');
                                    $yesterday = date('Y-m-d', strtotime('-1 day'));
                                    if ($msg_date == $today) {
                                        $display_date = 'Today';
                                    } elseif ($msg_date == $yesterday) {
                                        $display_date = 'Yesterday';
                                    } else {
                                        $display_date = date('l, M d, Y', strtotime($msg['created_at']));
                                    }
                                }
                        ?>
                            <?php if ($display_date): ?>
                                <div class="date-separator">
                                    <span class="date-separator-text"><?php echo $display_date; ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="chat-message <?php echo $msg['sender_id'] == $user_id ? 'sent' : 'received'; ?>">
                                <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                                <div class="message-time">
                                    <?php echo date('H:i', strtotime($msg['created_at'])); ?>
                                    <?php if ($msg['sender_id'] == $user_id): ?>
                                        <?php echo $msg['is_read'] ? ' ✅' : ' ✓'; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php 
                            endforeach; 
                        else: 
                        ?>
                            <div style="display: flex; align-items: center; justify-content: center; height: 100%; color: var(--gray-400);">
                                <div style="text-align: center;">
                                    <div style="font-size: 36px; margin-bottom: 12px;">💬</div>
                                    <p><?php echo t('no_messages_yet'); ?></p>
                                    <p style="font-size: 13px;"><?php echo t('start_conversation'); ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="chat-input">
                        <input type="text" id="chat-input" placeholder="<?php echo t('type_message'); ?>" 
                               onkeypress="if(event.key==='Enter') sendMessage()">
                        <button id="send-message" class="btn btn-primary" onclick="sendMessage()">
                            <?php echo t('send'); ?>
                        </button>
                    </div>
                <?php else: ?>
                    <div style="display: flex; align-items: center; justify-content: center; height: 100%; color: var(--gray-400);">
                        <div style="text-align: center;">
                            <div style="font-size: 48px; margin-bottom: 16px;">💬</div>
                            <h3 style="color: var(--gray-600);"><?php echo t('select_contact'); ?></h3>
                            <p style="font-size: 14px;"><?php echo t('select_contact_to_start_chatting'); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<meta name="user-id" content="<?php echo $user_id; ?>">
<meta name="contact-id" content="<?php echo $selected_user_id; ?>">

<style>
    .card {
        height: calc(100vh - 120px) !important;
        max-height: calc(100vh - 120px);
        display: flex;
        flex-direction: column;
    }
    
    .chat-container {
        display: grid;
        grid-template-columns: 300px 1fr;
        gap: 0;
        height: 100%;
        flex: 1;
        min-height: 0;
    }
    
    .chat-sidebar {
        background: var(--white);
        border-right: 1px solid var(--gray-200);
        display: flex;
        flex-direction: column;
        height: 100%;
        overflow: hidden;
    }
    
    .chat-sidebar .chat-search {
        padding: 16px;
        border-bottom: 1px solid var(--gray-200);
        flex-shrink: 0;
    }
    
    .chat-sidebar .chat-search input {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid var(--gray-200);
        border-radius: 6px;
        font-size: 14px;
    }
    
    .chat-sidebar .chat-search input:focus {
        outline: none;
        border-color: var(--primary-color);
    }
    
    .chat-sidebar .chat-contacts {
        overflow-y: auto;
        flex: 1;
        min-height: 0;
    }
    
    .chat-contact {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
        cursor: pointer;
        transition: all 0.2s ease;
        border-left: 3px solid transparent;
    }
    
    .chat-contact:hover {
        background: var(--gray-50);
    }
    
    .chat-contact.active {
        background: var(--gray-50);
        border-left-color: var(--primary-color);
    }
    
    .chat-contact .contact-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: var(--primary-color);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        flex-shrink: 0;
    }
    
    .chat-contact .contact-info {
        flex: 1;
        min-width: 0;
    }
    
    .chat-contact .contact-name {
        font-weight: 500;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 4px;
    }
    
    .chat-contact .contact-last-msg {
        font-size: 12px;
        color: var(--gray-500);
    }
    
    /* ===== CHAT MAIN - FIXED STRUCTURE ===== */
    .chat-main {
        display: flex;
        flex-direction: column;
        background: var(--white);
        height: 100%;
        min-height: 0;
        overflow: hidden;
    }
    
    /* Header - FIXED at top */
    .chat-main .chat-header {
        padding: 16px 20px;
        border-bottom: 1px solid var(--gray-200);
        display: flex;
        align-items: center;
        gap: 12px;
        flex-shrink: 0;
        background: var(--white);
        z-index: 10;
    }
    
    /* Messages - SCROLLABLE area */
    .chat-main .chat-messages {
        flex: 1;
        overflow-y: auto;
        overflow-x: hidden;
        padding: 20px;
        display: flex;
        flex-direction: column;
        gap: 2px;
        min-height: 0;
        scroll-behavior: smooth;
    }
    
    /* Date Separator Styles */
    .date-separator {
        display: flex;
        justify-content: center;
        align-items: center;
        margin: 16px 0 12px 0;
        position: relative;
    }
    
    .date-separator .date-separator-text {
        background: var(--gray-200);
        color: var(--gray-600);
        padding: 4px 14px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 500;
        letter-spacing: 0.3px;
    }
    
    body.dark-mode .date-separator .date-separator-text {
        background: #3a3a3a;
        color: #9ca3af;
    }
    
    /* Chat Messages */
    .chat-message {
        max-width: 70%;
        padding: 8px 12px;
        border-radius: var(--border-radius);
        font-size: 14px;
        word-wrap: break-word;
        position: relative;
    }
    
    .chat-message.sent {
        align-self: flex-end;
        background: var(--primary-color);
        color: white;
        border-bottom-right-radius: 4px;
    }
    
    .chat-message.received {
        align-self: flex-start;
        background: var(--gray-100);
        border-bottom-left-radius: 4px;
    }
    
    .chat-message .message-time {
        font-size: 10px;
        opacity: 0.7;
        margin-top: 2px;
        text-align: right;
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 2px;
    }
    
    /* Chat Input - FIXED at bottom */
    .chat-main .chat-input {
        padding: 12px 16px;
        border-top: 1px solid var(--gray-200);
        display: flex;
        gap: 10px;
        flex-shrink: 0;
        background: var(--white);
        position: sticky;
        bottom: 0;
        z-index: 10;
    }
    
    .chat-main .chat-input input {
        flex: 1;
        padding: 10px 14px;
        border: 1px solid var(--gray-200);
        border-radius: 20px;
        font-size: 14px;
        outline: none;
        transition: border-color 0.3s;
    }
    
    .chat-main .chat-input input:focus {
        outline: none;
        border-color: var(--primary-color);
    }
    
    .chat-main .chat-input .btn {
        border-radius: 20px;
        padding: 10px 20px;
        flex-shrink: 0;
    }
    
    /* Scrollbar Styling */
    .chat-main .chat-messages::-webkit-scrollbar {
        width: 6px;
    }
    
    .chat-main .chat-messages::-webkit-scrollbar-track {
        background: var(--gray-100);
        border-radius: 3px;
    }
    
    .chat-main .chat-messages::-webkit-scrollbar-thumb {
        background: var(--primary-color);
        border-radius: 3px;
    }
    
    .chat-main .chat-messages::-webkit-scrollbar-thumb:hover {
        background: var(--primary-dark);
    }
    
    /* Firefox scrollbar */
    .chat-main .chat-messages {
        scrollbar-width: thin;
        scrollbar-color: var(--primary-color) var(--gray-100);
    }
    
    /* Dark Mode */
    body.dark-mode .chat-sidebar {
        background: #2d2d2d;
        border-color: #4a1a1a;
    }
    
    body.dark-mode .chat-sidebar .chat-search {
        border-bottom-color: #4a1a1a;
    }
    
    body.dark-mode .chat-sidebar .chat-search input {
        background: #1a1a1a;
        border-color: #4a1a1a;
        color: #f3f4f6;
    }
    
    body.dark-mode .chat-contact:hover {
        background: #3a3a3a;
    }
    
    body.dark-mode .chat-contact.active {
        background: #3a3a3a;
    }
    
    body.dark-mode .chat-main {
        background: #2d2d2d;
    }
    
    body.dark-mode .chat-main .chat-header {
        border-bottom-color: #4a1a1a;
        background: #2d2d2d;
    }
    
    body.dark-mode .chat-message.received {
        background: #3a3a3a;
        color: #f3f4f6;
    }
    
    body.dark-mode .chat-main .chat-input {
        border-top-color: #4a1a1a;
        background: #2d2d2d;
    }
    
    body.dark-mode .chat-main .chat-input input {
        background: #1a1a1a;
        border-color: #4a1a1a;
        color: #f3f4f6;
    }
    
    body.dark-mode .chat-main .chat-input input:focus {
        border-color: #dc2626;
    }
    
    body.dark-mode .chat-contact .contact-last-msg {
        color: #9ca3af;
    }
    
    body.dark-mode .chat-main .chat-messages::-webkit-scrollbar-track {
        background: #2d2d2d;
    }
    
    body.dark-mode .chat-main .chat-messages::-webkit-scrollbar-thumb {
        background: #dc2626;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .card {
            height: calc(100vh - 80px) !important;
            max-height: calc(100vh - 80px);
        }
        
        .chat-container {
            grid-template-columns: 1fr;
            height: 100%;
        }
        
        .chat-sidebar {
            height: 250px;
            border-right: none;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .chat-main {
            min-height: 0;
            height: calc(100% - 250px);
        }
    }
    
    @media (max-width: 480px) {
        .chat-message {
            max-width: 85%;
        }
        
        .chat-main .chat-header {
            padding: 12px 16px;
        }
        
        .chat-main .chat-messages {
            padding: 12px;
        }
        
        .chat-main .chat-input {
            padding: 10px 12px;
        }
        
        .chat-main .chat-input input {
            font-size: 13px;
            padding: 8px 12px;
        }
        
        .chat-main .chat-input .btn {
            padding: 8px 14px;
            font-size: 13px;
        }
    }
</style>

<script>
let currentContactId = '<?php echo $selected_user_id; ?>';
let chatSocket = null;
let reconnectAttempts = 0;
const maxReconnectAttempts = 5;

// Connect to WebSocket
function connectChat() {
    const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
    const wsUrl = `${protocol}//${window.location.host}/interntrack/ws/chat.php`;
    
    try {
        chatSocket = new WebSocket(wsUrl);
        
        chatSocket.onopen = function() {
            console.log('Chat WebSocket connected');
            reconnectAttempts = 0;
            chatSocket.send(JSON.stringify({
                type: 'auth',
                user_id: '<?php echo $user_id; ?>'
            }));
        };
        
        chatSocket.onmessage = function(event) {
            const data = JSON.parse(event.data);
            if (data.type === 'message') {
                displayMessage(data.message, data.sender_id);
            } else if (data.type === 'typing') {
                showTypingIndicator(data.sender_id);
            }
        };
        
        chatSocket.onclose = function() {
            console.log('Chat WebSocket disconnected');
            const delay = Math.min(1000 * Math.pow(2, reconnectAttempts), 30000);
            reconnectAttempts++;
            if (reconnectAttempts <= maxReconnectAttempts) {
                setTimeout(connectChat, delay);
            }
        };
        
        chatSocket.onerror = function(error) {
            console.error('Chat WebSocket error:', error);
        };
    } catch (error) {
        console.error('Failed to connect to chat:', error);
    }
}

// Scroll to bottom function
function scrollToBottom() {
    const container = document.getElementById('chat-messages');
    if (container) {
        container.scrollTop = container.scrollHeight;
    }
}

// Send message
function sendMessage() {
    const input = document.getElementById('chat-input');
    const message = input.value.trim();
    
    if (!message || !currentContactId) return;
    
    if (chatSocket && chatSocket.readyState === WebSocket.OPEN) {
        chatSocket.send(JSON.stringify({
            type: 'send',
            receiver_id: currentContactId,
            message: message
        }));
    }
    
    fetch('/interntrack/api/chat.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            action: 'send',
            receiver_id: currentContactId,
            message: message
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            input.value = '';
            displayMessage({ 
                message: message, 
                created_at: new Date().toISOString(),
                is_read: false
            }, '<?php echo $user_id; ?>');
            hideTypingIndicator();
            scrollToBottom();
        } else {
            showToast(data.message || '<?php echo t('error_occurred'); ?>', 'error');
        }
    })
    .catch(error => {
        console.error('Error sending message:', error);
        showToast('<?php echo t('error_occurred'); ?>', 'error');
    });
}

// Display message
function displayMessage(message, senderId) {
    const container = document.getElementById('chat-messages');
    if (!container) return;
    
    hideTypingIndicator();
    
    const placeholder = container.querySelector('.no-messages-placeholder');
    if (placeholder) {
        placeholder.remove();
    }
    
    const isSent = senderId == '<?php echo $user_id; ?>';
    
    // Check if we need to add a date separator
    const msgDate = new Date(message.created_at);
    const msgDateStr = msgDate.toDateString();
    
    const today = new Date();
    const todayStr = today.toDateString();
    const yesterday = new Date(today);
    yesterday.setDate(yesterday.getDate() - 1);
    const yesterdayStr = yesterday.toDateString();
    
    let displayDate = '';
    if (msgDateStr === todayStr) {
        displayDate = 'Today';
    } else if (msgDateStr === yesterdayStr) {
        displayDate = 'Yesterday';
    } else {
        displayDate = msgDate.toLocaleDateString('en-US', { 
            weekday: 'long', 
            month: 'short', 
            day: 'numeric', 
            year: 'numeric' 
        });
    }
    
    const lastSeparator = container.querySelector('.date-separator:last-child');
    let needSeparator = false;
    
    if (!lastSeparator) {
        needSeparator = true;
    } else {
        const lastSeparatorText = lastSeparator.textContent.trim();
        if (lastSeparatorText !== displayDate) {
            needSeparator = true;
        }
    }
    
    if (needSeparator) {
        const separator = document.createElement('div');
        separator.className = 'date-separator';
        separator.innerHTML = `<span class="date-separator-text">${displayDate}</span>`;
        container.appendChild(separator);
    }
    
    const div = document.createElement('div');
    div.className = `chat-message ${isSent ? 'sent' : 'received'}`;
    
    const content = document.createElement('div');
    content.textContent = message.message;
    
    const time = document.createElement('div');
    time.className = 'message-time';
    const msgTime = new Date(message.created_at);
    time.textContent = msgTime.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    
    if (isSent) {
        const check = document.createElement('span');
        check.textContent = message.is_read ? ' ✅' : ' ✓';
        time.appendChild(check);
    }
    
    div.appendChild(content);
    div.appendChild(time);
    container.appendChild(div);
    scrollToBottom();
}

// Typing indicator
function showTypingIndicator(senderId) {
    if (currentContactId != senderId) return;
    
    const container = document.getElementById('chat-messages');
    if (!container) return;
    
    let typing = container.querySelector('.typing-indicator');
    if (!typing) {
        typing = document.createElement('div');
        typing.className = 'chat-message received typing-indicator';
        typing.innerHTML = '<span>...</span> <?php echo t('typing'); ?>';
        container.appendChild(typing);
        scrollToBottom();
    }
}

function hideTypingIndicator() {
    const container = document.getElementById('chat-messages');
    if (!container) return;
    const typing = container.querySelector('.typing-indicator');
    if (typing) {
        typing.remove();
    }
}

// Handle contact clicks
document.querySelectorAll('.chat-contact').forEach(contact => {
    contact.addEventListener('click', function() {
        const contactId = this.dataset.contactId;
        window.location.href = `/interntrack/intern/chat.php?user_id=${contactId}`;
    });
});

// Search contacts
document.getElementById('chat-search')?.addEventListener('input', function() {
    const query = this.value.toLowerCase();
    document.querySelectorAll('.chat-contact').forEach(contact => {
        const name = contact.querySelector('.contact-name')?.textContent?.toLowerCase() || '';
        contact.style.display = name.includes(query) ? '' : 'none';
    });
});

function showToast(message, type = 'info') {
    const container = document.querySelector('.toast-container') || (() => {
        const el = document.createElement('div');
        el.className = 'toast-container';
        document.body.appendChild(el);
        return el;
    })();
    
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, 5000);
}

// Initialize chat
document.addEventListener('DOMContentLoaded', function() {
    connectChat();
    
    // Scroll to bottom on load
    setTimeout(scrollToBottom, 100);
    
    const urlParams = new URLSearchParams(window.location.search);
    const userId = urlParams.get('user_id');
    if (userId) {
        currentContactId = userId;
        fetch(`/interntrack/api/chat.php?action=get_messages&contact_id=${userId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const container = document.getElementById('chat-messages');
                    if (container) {
                        container.innerHTML = '';
                        data.messages.forEach(msg => {
                            displayMessage(msg, msg.sender_id);
                        });
                        scrollToBottom();
                    }
                }
            })
            .catch(error => console.error('Error loading messages:', error));
    }
    
    // Auto-refresh messages every 30 seconds
    if (currentContactId) {
        setInterval(() => {
            fetch(`/interntrack/api/chat.php?action=get_messages&contact_id=${currentContactId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const container = document.getElementById('chat-messages');
                        if (container) {
                            const scrollPos = container.scrollTop;
                            const scrollHeight = container.scrollHeight;
                            const isAtBottom = scrollPos >= scrollHeight - container.clientHeight - 50;
                            
                            container.innerHTML = '';
                            data.messages.forEach(msg => {
                                displayMessage(msg, msg.sender_id);
                            });
                            
                            if (isAtBottom) {
                                scrollToBottom();
                            } else {
                                container.scrollTop = scrollPos;
                            }
                        }
                    }
                })
                .catch(error => console.error('Error refreshing messages:', error));
        }, 30000);
    }
    
    // Resize observer to handle height changes
    const chatMessages = document.getElementById('chat-messages');
    if (chatMessages) {
        const resizeObserver = new ResizeObserver(() => {
            scrollToBottom();
        });
        resizeObserver.observe(chatMessages);
    }
});
</script>

<?php include_once '../includes/footer.php'; ?>