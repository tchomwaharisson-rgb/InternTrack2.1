<?php
// supervisor/chat.php
require_once '../config/config.php';
require_once '../config/language.php';

// Make sure $conn is available
global $conn;

if (!isLoggedIn() || !hasRole('supervisor')) {
    header('Location: /interntrack/auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$selected_user_id = $_GET['user_id'] ?? '';
$message = '';
$message_type = '';

// Get assigned interns
$stmt = $conn->prepare("
    SELECT u.id, u.first_name, u.last_name, u.is_active, u.profile_picture,
           (SELECT COUNT(*) FROM messages WHERE sender_id = u.id AND receiver_id = ? AND is_read = FALSE) as unread_count
    FROM users u
    JOIN interns i ON u.id = i.user_id
    WHERE i.supervisor_id = ?
    ORDER BY u.first_name
");
$stmt->execute([$user_id, $user_id]);
$contacts = $stmt->fetchAll();

// Get messages for selected contact
$messages = [];
$contact_info = null;
if ($selected_user_id) {
    // Verify this is an assigned intern
    $stmt = $conn->prepare("SELECT user_id FROM interns WHERE user_id = ? AND supervisor_id = ?");
    $stmt->execute([$selected_user_id, $user_id]);
    if ($stmt->fetch()) {
        // Get contact info
        $stmt = $conn->prepare("SELECT first_name, last_name, is_active FROM users WHERE id = ?");
        $stmt->execute([$selected_user_id]);
        $contact_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get messages
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
    } else {
        $selected_user_id = '';
    }
}

include_once '../includes/header.php';
?>

<div class="main-content">
    <div class="card" style="padding: 0; overflow: hidden;">
        <div class="chat-container">
            <!-- Sidebar -->
            <div class="chat-sidebar">
                <div class="chat-search">
                    <input type="text" placeholder="<?php echo t('search_contacts'); ?>" id="chat-search">
                </div>
                <div class="chat-contacts" id="chat-contacts">
                    <?php if ($contacts): ?>
                        <?php foreach ($contacts as $contact): ?>
                            <div class="chat-contact <?php echo $selected_user_id == $contact['id'] ? 'active' : ''; ?>" 
                                 data-contact-id="<?php echo $contact['id']; ?>">
                                <div class="contact-avatar">
                                    <?php if ($contact['profile_picture']): ?>
                                        <img src="/interntrack/uploads/profiles/<?php echo $contact['profile_picture']; ?>" 
                                             alt="Profile" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                                    <?php else: 
                                        $name = $contact['first_name'] . ' ' . $contact['last_name'];
                                        $parts = explode(' ', $name);
                                        echo strtoupper($parts[0][0] ?? 'U') . (isset($parts[1]) ? strtoupper($parts[1][0] ?? '') : '');
                                    endif; ?>
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
                                    <div class="contact-status">
                                        <?php echo $contact['is_active'] ? '🟢 ' . t('online') : '⚪ ' . t('offline'); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="padding: 30px 20px; text-align: center; color: var(--gray-500);">
                            <div style="font-size: 36px; margin-bottom: 12px;">💬</div>
                            <p><?php echo t('no_interns_assigned'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Chat Main -->
            <div class="chat-main">
                <?php if ($selected_user_id && $contact_info): ?>
                    <div class="chat-header">
                        <div class="contact-avatar" style="width: 40px; height: 40px; border-radius: 50%; background: var(--primary-red); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; flex-shrink: 0; font-size: 16px;">
                            <?php 
                                $name = $contact_info['first_name'] . ' ' . $contact_info['last_name'];
                                $parts = explode(' ', $name);
                                echo strtoupper($parts[0][0] ?? 'U') . (isset($parts[1]) ? strtoupper($parts[1][0] ?? '') : '');
                            ?>
                        </div>
                        <div>
                            <div style="font-weight: 600; font-size: 16px;">
                                <?php echo htmlspecialchars($contact_info['first_name'] . ' ' . $contact_info['last_name']); ?>
                            </div>
                            <div style="font-size: 12px; color: var(--gray-500);">
                                <?php echo $contact_info['is_active'] ? '🟢 ' . t('online') : '⚪ ' . t('offline'); ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="chat-messages" id="chat-messages">
                        <?php if ($messages): ?>
                            <?php foreach ($messages as $msg): ?>
                                <div class="chat-message <?php echo $msg['sender_id'] == $user_id ? 'sent' : 'received'; ?>">
                                    <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                                    <div class="message-time">
                                        <?php echo date('H:i', strtotime($msg['created_at'])); ?>
                                        <?php if ($msg['sender_id'] == $user_id): ?>
                                            <?php echo $msg['is_read'] ? ' ✅' : ' ✓'; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
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
                            <?php if (!$contacts): ?>
                                <p style="font-size: 13px; margin-top: 8px; color: var(--gray-400);">
                                    <?php echo t('no_interns_assigned_for_chat'); ?>
                                </p>
                            <?php endif; ?>
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
    .chat-container {
        display: grid;
        grid-template-columns: 300px 1fr;
        gap: 0;
        height: 600px;
    }
    
    .chat-sidebar {
        background: var(--white);
        border-right: 1px solid var(--gray-200);
        display: flex;
        flex-direction: column;
    }
    
    .chat-sidebar .chat-search {
        padding: 16px;
        border-bottom: 1px solid var(--gray-200);
    }
    
    .chat-sidebar .chat-search input {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid var(--gray-200);
        border-radius: 6px;
        font-size: 14px;
        transition: border-color 0.3s;
    }
    
    .chat-sidebar .chat-search input:focus {
        outline: none;
        border-color: var(--primary-color);
    }
    
    .chat-sidebar .chat-contacts {
        overflow-y: auto;
        flex: 1;
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
        background: var(--primary-red);
        color: var(--primary-white);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        flex-shrink: 0;
        overflow: hidden;
    }
    
    .chat-contact .contact-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
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
        color: var(--gray-900);
    }
    
    .chat-contact .contact-status {
        font-size: 12px;
        color: var(--gray-500);
    }
    
    .chat-main {
        display: flex;
        flex-direction: column;
        background: var(--white);
    }
    
    .chat-main .chat-header {
        padding: 16px 20px;
        border-bottom: 1px solid var(--gray-200);
        display: flex;
        align-items: center;
        gap: 12px;
        flex-shrink: 0;
    }
    
    .chat-main .chat-messages {
        flex: 1;
        overflow-y: auto;
        padding: 20px;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    
    .chat-message {
        max-width: 70%;
        padding: 10px 14px;
        border-radius: var(--border-radius);
        font-size: 14px;
        word-wrap: break-word;
    }
    
    .chat-message.sent {
        align-self: flex-end;
        background: var(--primary-red);
        color: solid white;
    }
    
    .chat-message.received {
        align-self: flex-start;
        background: var(--primary-gray);
        color: solid white;
    }
    
    .chat-message .message-time {
        font-size: 10px;
        opacity: 0.7;
        margin-top: 4px;
        text-align: right;
    }
    
    .chat-main .chat-input {
        padding: 16px 20px;
        border-top: 1px solid var(--gray-200);
        display: flex;
        gap: 12px;
        flex-shrink: 0;
    }
    
    .chat-main .chat-input input {
        flex: 1;
        padding: 10px 12px;
        border: 1px solid var(--gray-200);
        border-radius: var(--border-radius);
        font-size: 14px;
        transition: border-color 0.3s;
    }
    
    .chat-main .chat-input input:focus {
        outline: none;
        border-color: var(--primary-color);
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
    }
    
    body.dark-mode .chat-message.received {
        background: #3a3a3a;
        color: #f3f4f6;
    }
    
    body.dark-mode .chat-main .chat-input {
        border-top-color: #4a1a1a;
    }
    
    body.dark-mode .chat-main .chat-input input {
        background: #1a1a1a;
        border-color: #4a1a1a;
        color: #f3f4f6;
    }
    
    body.dark-mode .chat-main .chat-input input:focus {
        border-color: #dc2626;
    }
    
    body.dark-mode .chat-contact .contact-status {
        color: #9ca3af;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .chat-container {
            grid-template-columns: 1fr;
            height: auto;
        }
        
        .chat-sidebar {
            height: 300px;
            border-right: none;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .chat-main {
            min-height: 400px;
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
            padding: 12px 16px;
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
            // Reconnect with exponential backoff
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

// Send message
function sendMessage() {
    const input = document.getElementById('chat-input');
    const message = input.value.trim();
    
    if (!message || !currentContactId) return;
    
    // Send via WebSocket
    if (chatSocket && chatSocket.readyState === WebSocket.OPEN) {
        chatSocket.send(JSON.stringify({
            type: 'send',
            receiver_id: currentContactId,
            message: message
        }));
    }
    
    // Also send via HTTP as fallback
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
            displayMessage({ message: message, created_at: new Date().toISOString() }, '<?php echo $user_id; ?>');
            // Hide typing indicator
            hideTypingIndicator();
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
    
    // Remove typing indicator
    hideTypingIndicator();
    
    const isSent = senderId == '<?php echo $user_id; ?>';
    const div = document.createElement('div');
    div.className = `chat-message ${isSent ? 'sent' : 'received'}`;
    
    const content = document.createElement('div');
    content.textContent = message.message;
    
    const time = document.createElement('div');
    time.className = 'message-time';
    const msgDate = new Date(message.created_at);
    time.textContent = msgDate.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    
    div.appendChild(content);
    div.appendChild(time);
    container.appendChild(div);
    container.scrollTop = container.scrollHeight;
}

// Typing indicator
let typingTimeout = null;

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
        container.scrollTop = container.scrollHeight;
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
        window.location.href = `/interntrack/supervisor/chat.php?user_id=${contactId}`;
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

// Toast notification
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
    
    // Auto-refresh messages every 30 seconds
    if (currentContactId) {
        setInterval(() => {
            fetch(`/interntrack/api/chat.php?action=get_messages&contact_id=${currentContactId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const container = document.getElementById('chat-messages');
                        if (container) {
                            container.innerHTML = '';
                            data.messages.forEach(msg => {
                                displayMessage(msg, msg.sender_id);
                            });
                        }
                    }
                })
                .catch(error => console.error('Error refreshing messages:', error));
        }, 30000);
    }
});
</script>

<?php include_once '../includes/footer.php'; ?>