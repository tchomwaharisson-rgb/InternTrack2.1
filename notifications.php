<?php
// notifications.php
require_once 'config/config.php';
require_once 'config/language.php';

// Make sure $conn is available
global $conn;

if (!isLoggedIn()) {
    header('Location: /interntrack/auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Get filter from URL
$filter = $_GET['filter'] ?? 'all'; // all, unread, read
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Handle mark as read actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'mark_read') {
        $notification_id = $_POST['notification_id'] ?? 0;
        $stmt = $conn->prepare("UPDATE notifications SET is_read = TRUE WHERE id = ? AND user_id = ?");
        if ($stmt->execute([$notification_id, $user_id])) {
            $message = t('notification_marked_read');
            $message_type = 'success';
            logAudit($user_id, 'mark_notification_read', 'Marked notification ' . $notification_id . ' as read');
        }
    } elseif ($action === 'mark_all_read') {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ?");
        if ($stmt->execute([$user_id])) {
            $message = t('all_notifications_marked_read');
            $message_type = 'success';
            logAudit($user_id, 'mark_all_notifications_read', 'Marked all notifications as read');
        }
    } elseif ($action === 'delete_all_read') {
        $stmt = $conn->prepare("DELETE FROM notifications WHERE user_id = ? AND is_read = TRUE");
        if ($stmt->execute([$user_id])) {
            $message = t('read_notifications_deleted');
            $message_type = 'success';
            logAudit($user_id, 'delete_read_notifications', 'Deleted all read notifications');
        }
    } elseif ($action === 'delete_all') {
        $stmt = $conn->prepare("DELETE FROM notifications WHERE user_id = ?");
        if ($stmt->execute([$user_id])) {
            $message = t('all_notifications_deleted');
            $message_type = 'success';
            logAudit($user_id, 'delete_all_notifications', 'Deleted all notifications');
        }
    }
}

// Build query based on filter
$sql = "SELECT * FROM notifications WHERE user_id = ?";
$params = [$user_id];

if ($filter === 'unread') {
    $sql .= " AND is_read = FALSE";
} elseif ($filter === 'read') {
    $sql .= " AND is_read = TRUE";
}

// Get total count for pagination
$count_sql = str_replace("SELECT *", "SELECT COUNT(*) as total", $sql);
$stmt = $conn->prepare($count_sql);
$stmt->execute($params);
$total_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_notifications / $limit);

// Get notifications with pagination
$sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$notifications = $stmt->fetchAll();

// Get counts
$stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = FALSE");
$stmt->execute([$user_id]);
$unread_count = $stmt->fetchColumn();

$stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = TRUE");
$stmt->execute([$user_id]);
$read_count = $stmt->fetchColumn();

// Get notification types for filtering (if needed in future)
$stmt = $conn->prepare("SELECT DISTINCT type FROM notifications WHERE user_id = ?");
$stmt->execute([$user_id]);
$notification_types = $stmt->fetchAll(PDO::FETCH_COLUMN);

include_once 'includes/header.php';
?>

<div class="main-content">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">🔔 <?php echo t('notifications'); ?></h3>
            <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                <span style="font-size: 14px; color: var(--gray-500);">
                    <?php echo $unread_count; ?> <?php echo t('unread'); ?> • 
                    <?php echo $read_count; ?> <?php echo t('read'); ?>
                </span>
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="toast toast-<?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <!-- Filter Tabs -->
        <div style="display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; border-bottom: 2px solid var(--gray-200); padding-bottom: 12px;">
            <a href="?filter=all" class="btn <?php echo $filter === 'all' ? 'btn-primary' : 'btn-secondary'; ?>" style="border-radius: 20px;">
                📋 <?php echo t('all'); ?> (<?php echo $unread_count + $read_count; ?>)
            </a>
            <a href="?filter=unread" class="btn <?php echo $filter === 'unread' ? 'btn-primary' : 'btn-secondary'; ?>" style="border-radius: 20px;">
                🔴 <?php echo t('unread'); ?> (<?php echo $unread_count; ?>)
            </a>
            <a href="?filter=read" class="btn <?php echo $filter === 'read' ? 'btn-primary' : 'btn-secondary'; ?>" style="border-radius: 20px;">
                ✅ <?php echo t('read'); ?> (<?php echo $read_count; ?>)
            </a>
        </div>
        
        <!-- Action Buttons -->
        <?php if ($notifications): ?>
            <div style="display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap;">
                <?php if ($unread_count > 0): ?>
                    <form method="POST" action="" style="display: inline;">
                        <input type="hidden" name="action" value="mark_all_read">
                        <button type="submit" class="btn btn-sm btn-primary" onclick="return confirm('<?php echo t('mark_all_read_confirmation'); ?>')">
                            ✅ <?php echo t('mark_all_read'); ?>
                        </button>
                    </form>
                <?php endif; ?>
                <?php if ($read_count > 0): ?>
                    <form method="POST" action="" style="display: inline;" onsubmit="return confirm('<?php echo t('delete_read_confirmation'); ?>')">
                        <input type="hidden" name="action" value="delete_all_read">
                        <button type="submit" class="btn btn-sm btn-danger">
                            🗑️ <?php echo t('delete_read'); ?>
                        </button>
                    </form>
                <?php endif; ?>
                <?php if ($unread_count + $read_count > 0): ?>
                    <form method="POST" action="" style="display: inline;" onsubmit="return confirm('<?php echo t('delete_all_confirmation'); ?>')">
                        <input type="hidden" name="action" value="delete_all">
                        <button type="submit" class="btn btn-sm btn-danger" style="background: #6b7280;">
                            🗑️ <?php echo t('delete_all'); ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <!-- Notifications List -->
        <?php if ($notifications): ?>
            <div class="notification-list">
                <?php foreach ($notifications as $notif): ?>
                    <div class="notification-item <?php echo $notif['is_read'] ? 'read' : 'unread'; ?>" 
                         style="display: flex; justify-content: space-between; align-items: center; padding: 14px 16px; border-bottom: 1px solid var(--gray-100); <?php echo $notif['is_read'] ? '' : 'background: var(--red-50); border-left: 4px solid var(--primary-color);'; ?> transition: background 0.3s ease;">
                        
                        <div style="flex: 1; min-width: 0;">
                            <a href="<?php echo $notif['link'] ?? '#'; ?>" style="text-decoration: none; color: inherit; display: block;">
                                <div style="display: flex; align-items: flex-start; gap: 12px;">
                                    <!-- Notification Icon based on type -->
                                    <div style="font-size: 24px; flex-shrink: 0; margin-top: 2px;">
                                        <?php 
                                            $icon = '📋';
                                            if (strpos($notif['type'], 'leave') !== false) {
                                                $icon = '📅';
                                            } elseif (strpos($notif['type'], 'goal') !== false) {
                                                $icon = '🎯';
                                            } elseif (strpos($notif['type'], 'message') !== false) {
                                                $icon = '💬';
                                            } elseif (strpos($notif['type'], 'clock') !== false || strpos($notif['type'], 'arrival') !== false) {
                                                $icon = '⏱️';
                                            } elseif (strpos($notif['type'], 'registration') !== false || strpos($notif['type'], 'approve') !== false) {
                                                $icon = '✅';
                                            } elseif (strpos($notif['type'], 'reject') !== false) {
                                                $icon = '❌';
                                            } elseif (strpos($notif['type'], 'system') !== false) {
                                                $icon = '⚙️';
                                            }
                                            echo $icon;
                                        ?>
                                    </div>
                                    <div style="flex: 1; min-width: 0;">
                                        <div style="font-size: 14px; color: var(--gray-800); <?php echo $notif['is_read'] ? '' : 'font-weight: 600;'; ?>">
                                            <?php echo htmlspecialchars($notif['message']); ?>
                                        </div>
                                        <div style="font-size: 12px; color: var(--gray-500); margin-top: 4px; display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                                            <span><?php echo date('M d, Y H:i', strtotime($notif['created_at'])); ?></span>
                                            <span>•</span>
                                            <span><?php echo timeAgo($notif['created_at']); ?></span>
                                            <?php if ($notif['type']): ?>
                                                <span>•</span>
                                                <span style="background: var(--gray-200); padding: 1px 8px; border-radius: 10px; font-size: 10px; text-transform: capitalize;">
                                                    <?php echo str_replace('_', ' ', $notif['type']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>
                        
                        <div style="display: flex; gap: 8px; align-items: center; flex-shrink: 0; margin-left: 12px;">
                            <?php if (!$notif['is_read']): ?>
                                <span style="background: var(--primary-color); width: 10px; height: 10px; border-radius: 50%; display: inline-block;"></span>
                            <?php endif; ?>
                            
                            <?php if (!$notif['is_read']): ?>
                                <form method="POST" action="" style="display: inline;">
                                    <input type="hidden" name="action" value="mark_read">
                                    <input type="hidden" name="notification_id" value="<?php echo $notif['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-secondary" title="<?php echo t('mark_read'); ?>" style="padding: 4px 10px;">
                                        ✅
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <?php if ($notif['link']): ?>
                                <a href="<?php echo $notif['link']; ?>" class="btn btn-sm btn-primary" style="padding: 4px 10px;">
                                    👁️
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div style="display: flex; justify-content: center; gap: 4px; margin-top: 20px; flex-wrap: wrap;">
                    <?php if ($page > 1): ?>
                        <a href="?filter=<?php echo $filter; ?>&page=<?php echo $page - 1; ?>" class="btn btn-sm btn-secondary">«</a>
                    <?php endif; ?>
                    
                    <?php 
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    ?>
                    
                    <?php if ($start_page > 1): ?>
                        <a href="?filter=<?php echo $filter; ?>&page=1" class="btn btn-sm btn-secondary">1</a>
                        <?php if ($start_page > 2): ?>
                            <span style="padding: 0 4px; color: var(--gray-400);">…</span>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <a href="?filter=<?php echo $filter; ?>&page=<?php echo $i; ?>" class="btn btn-sm <?php echo $i === $page ? 'btn-primary' : 'btn-secondary'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($end_page < $total_pages): ?>
                        <?php if ($end_page < $total_pages - 1): ?>
                            <span style="padding: 0 4px; color: var(--gray-400);">…</span>
                        <?php endif; ?>
                        <a href="?filter=<?php echo $filter; ?>&page=<?php echo $total_pages; ?>" class="btn btn-sm btn-secondary"><?php echo $total_pages; ?></a>
                    <?php endif; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?filter=<?php echo $filter; ?>&page=<?php echo $page + 1; ?>" class="btn btn-sm btn-secondary">»</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div style="text-align: center; padding: 60px 0;">
                <?php if ($filter === 'unread'): ?>
                    <div style="font-size: 48px; margin-bottom: 16px;">✅</div>
                    <h3><?php echo t('no_unread_notifications'); ?></h3>
                    <p style="color: var(--gray-500);"><?php echo t('no_unread_notifications_message'); ?></p>
                <?php elseif ($filter === 'read'): ?>
                    <div style="font-size: 48px; margin-bottom: 16px;">📋</div>
                    <h3><?php echo t('no_read_notifications'); ?></h3>
                    <p style="color: var(--gray-500);"><?php echo t('no_read_notifications_message'); ?></p>
                <?php else: ?>
                    <div style="font-size: 48px; margin-bottom: 16px;">🔔</div>
                    <h3><?php echo t('no_notifications'); ?></h3>
                    <p style="color: var(--gray-500);"><?php echo t('no_notifications_message'); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    .notification-item {
        transition: background 0.3s ease, box-shadow 0.3s ease;
    }
    
    .notification-item:hover {
        background: var(--gray-50);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }
    
    .notification-item.unread:hover {
        background: var(--red-100);
    }
    
    .notification-item .btn {
        transition: all 0.2s ease;
    }
    
    .notification-item .btn:hover {
        transform: scale(1.05);
    }
    
    /* Dark Mode Support */
    body.dark-mode .notification-item {
        border-bottom-color: #4a1a1a;
    }
    
    body.dark-mode .notification-item:hover {
        background: #3a3a3a;
    }
    
    body.dark-mode .notification-item.unread {
        background: #2d1a1a;
        border-left-color: #dc2626;
    }
    
    body.dark-mode .notification-item.unread:hover {
        background: #3d1a1a;
    }
    
    body.dark-mode .notification-item .notification-message {
        color: #e5e7eb;
    }
    
    body.dark-mode .notification-item .notification-time {
        color: #9ca3af;
    }
    
    body.dark-mode .notification-item .btn-secondary {
        background: #4a4a4a;
        color: #f3f4f6;
    }
    
    body.dark-mode .notification-item .btn-secondary:hover {
        background: #5a5a5a;
    }
    
    body.dark-mode .btn-secondary {
        background: #4a4a4a;
        color: #f3f4f6;
    }
    
    body.dark-mode .btn-secondary:hover {
        background: #5a5a5a;
    }
    
    body.dark-mode .notification-item .btn-primary {
        background: #dc2626;
    }
    
    body.dark-mode .notification-item .btn-primary:hover {
        background: #b91c1c;
    }
</style>

<script>
// Auto-refresh notifications every 60 seconds
let refreshInterval = setInterval(function() {
    // Only refresh if the page is visible
    if (!document.hidden) {
        fetch('/interntrack/api/notifications.php?action=get_unread_count')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update the badge in the header
                    const badge = document.querySelector('.notification-badge');
                    if (data.count > 0) {
                        if (badge) {
                            badge.textContent = data.count;
                            badge.style.display = 'inline';
                        } else {
                            // Create badge if it doesn't exist
                            const btn = document.querySelector('.notification-btn');
                            if (btn) {
                                const newBadge = document.createElement('span');
                                newBadge.className = 'notification-badge';
                                newBadge.textContent = data.count;
                                btn.appendChild(newBadge);
                            }
                        }
                    } else {
                        if (badge) {
                            badge.style.display = 'none';
                        }
                    }
                }
            })
            .catch(error => console.error('Error fetching notification count:', error));
    }
}, 60000);

// Clean up interval when page unloads
window.addEventListener('beforeunload', function() {
    clearInterval(refreshInterval);
});

// Mark notification as read when clicked
document.querySelectorAll('.notification-item a').forEach(link => {
    link.addEventListener('click', function() {
        const item = this.closest('.notification-item');
        if (item && item.classList.contains('unread')) {
            // Find the mark read button
            const markBtn = item.querySelector('form button[title="<?php echo t('mark_read'); ?>"]');
            if (markBtn) {
                markBtn.click();
            }
        }
    });
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl+Shift+M: Mark all as read
    if (e.ctrlKey && e.shiftKey && e.key === 'M') {
        const markAllBtn = document.querySelector('form button[value="mark_all_read"]');
        if (markAllBtn) {
            markAllBtn.click();
        }
    }
    
    // Escape: Close modals or dialogs
    if (e.key === 'Escape') {
        // Any modals would be closed here
    }
});
</script>

<?php include_once 'includes/footer.php'; ?>