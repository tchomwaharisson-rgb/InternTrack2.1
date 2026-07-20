<?php
require_once '../config/config.php';
require_once '../config/language.php';

global $conn;

if (!isLoggedIn() || !hasRole('intern')) {
    header('Location: /interntrack/auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$selected_user_id = $_GET['user_id'] ?? '';

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

// If no supervisor assigned, show message
if (empty($contacts)) {
    // Show a message that no supervisor is assigned yet
}

// Get messages for selected contact
$messages = [];
if ($selected_user_id && $selected_user_id == $supervisor_id) {
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
    <div class="card" style="padding: 0; overflow: hidden;">
        <div class="chat-container">
            <!-- Sidebar -->
            <div class="chat-sidebar">
                <div class="chat-search">
                    <input type="text" placeholder="<?php echo t('search'); ?> contacts..." id="chat-search">
                </div>
                <div class="chat-contacts" id="chat-contacts">
                    <?php if ($contacts): ?>
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
                                            <span class="badge" style="background: var(--primary-red); color: white; font-size: 10px; padding: 1px 6px; border-radius: 50%; margin-left: 4px;">
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
                        <div style="padding: 20px; text-align: center; color: var(--secondary-text);">
                            <?php echo t('no_supervisor_assigned'); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Chat Main -->
            <div class="chat-main">
                <?php if ($selected_user_id && $selected_user_id == $supervisor_id): ?>
                    <?php 
                        $contact = $contacts[0] ?? null;
                    ?>
                    <div class="chat-header">
                        <div class="contact-avatar" style="width: 40px; height: 40px; border-radius: 50%; background: var(--primary-red); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; flex-shrink: 0;">
                            <?php 
                                $name = $contact['first_name'] . ' ' . $contact['last_name'];
                                $parts = explode(' ', $name);
                                echo strtoupper($parts[0][0] ?? 'U') . (isset($parts[1]) ? strtoupper($parts[1][0] ?? '') : '');
                            ?>
                        </div>
                        <div>
                            <div style="font-weight: 600;"><?php echo htmlspecialchars($contact['first_name'] . ' ' . $contact['last_name']); ?></div>
                            <div style="font-size: 12px; color: var(--secondary-text);">
                                <?php echo $contact['is_active'] ? '🟢 Online' : '⚪ Offline'; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="chat-messages" id="chat-messages">
                        <?php foreach ($messages as $msg): ?>
                            <div class="chat-message <?php echo $msg['sender_id'] == $user_id ? 'sent' : 'received'; ?>">
                                <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                                <div class="message-time">
                                    <?php echo date('H:i', strtotime($msg['created_at'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="chat-input">
                        <input type="text" id="chat-input" placeholder="<?php echo t('type_message'); ?>" 
                               onkeypress="if(event.key==='Enter') sendMessage()">
                        <button id="send-message" class="btn btn-primary" onclick="sendMessage()">
                            <?php echo t('send'); ?>
                        </button>
                    </div>
                <?php else: ?>
                    <div style="display: flex; align-items: center; justify-content: center; height: 100%; color: var(--secondary-text);">
                        <div style="text-align: center;">
                            <div style="font-size: 48px; margin-bottom: 16px;">💬</div>
                            <h3><?php echo t('select_contact'); ?></h3>
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

<script>
let currentContactId = '<?php echo $selected_user_id; ?>';
let chatSocket = null;

// Connect to WebSocket
function connectChat() {
    const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
    const wsUrl = `${protocol}//${window.location.host}/interntrack/ws/chat.php`;
    
    try {
        chatSocket = new WebSocket(wsUrl);
        
        chatSocket.onopen = function() {
            console.log('Chat WebSocket connected');
            chatSocket.send(JSON.stringify({
                type: 'auth',
                user_id: '<?php echo $user_id; ?>'
            }));
        };
        
        chatSocket.onmessage = function(event) {
            const data = JSON.parse(event.data);
            if (data.type === 'message') {
                displayMessage(data.message, data.sender_id);
            }
        };
        
        chatSocket.onclose = function() {
            console.log('Chat WebSocket disconnected');
            setTimeout(connectChat, 5000);
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
    
    // Also send via HTTP
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
        } else {
            showToast(data.message || 'Error sending message', 'error');
        }
    })
    .catch(error => {
        console.error('Error sending message:', error);
        showToast('Error sending message', 'error');
    });
}

// Display message
function displayMessage(message, senderId) {
    const container = document.getElementById('chat-messages');
    if (!container) return;
    
    const isSent = senderId == '<?php echo $user_id; ?>';
    const div = document.createElement('div');
    div.className = `chat-message ${isSent ? 'sent' : 'received'}`;
    
    const content = document.createElement('div');
    content.textContent = message.message;
    
    const time = document.createElement('div');
    time.className = 'message-time';
    time.textContent = new Date(message.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    
    div.appendChild(content);
    div.appendChild(time);
    container.appendChild(div);
    container.scrollTop = container.scrollHeight;
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