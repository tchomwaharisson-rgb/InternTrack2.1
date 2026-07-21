<?php
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

// Handle mark as read actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'mark_read') {
        $notification_id = $_POST['notification_id'] ?? 0;
        $stmt = $conn->prepare("UPDATE notifications SET is_read = TRUE WHERE id = ? AND user_id = ?");
        if ($stmt->execute([$notification_id, $user_id])) {
            $message = t('notification_marked_read');
            $message_type = 'success';
        }
    } elseif ($action === 'mark_all_read') {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ?");
        if ($stmt->execute([$user_id])) {
            $message = t('all_notifications_marked_read');
            $message_type = 'success';
        }
    } elseif ($action === 'delete_all_read') {
        $stmt = $conn->prepare("DELETE FROM notifications WHERE user_id = ? AND is_read = TRUE");
        if ($stmt->execute([$user_id])) {
            $message = t('read_notifications_deleted');
            $message_type = 'success';
        }
    }
}

// Get all notifications with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Get total count
$stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ?");
$stmt->execute([$user_id]);
$total_notifications = $stmt->fetchColumn();
$total_pages = ceil($total_notifications / $limit);

// Get notifications
$stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->execute([$user_id, $limit, $offset]);
$notifications = $stmt->fetchAll();

// Get counts
$stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = FALSE");
$stmt->execute([$user_id]);
$unread_count = $stmt->fetchColumn();

$stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = TRUE");
$stmt->execute([$user_id]);
$read_count = $stmt->fetchColumn();

include_once 'includes/header.php';
?>

<div class="main-content">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?php echo t('notifications'); ?></h3>
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <span style="font-size: 14px; color: var(--gray-500);">
                    <?php echo $unread_count; ?> <?php echo t('unread'); ?> | <?php echo $read_count; ?> <?php echo t('read'); ?>
                </span>
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="toast toast-<?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <!-- Action Buttons -->
        <div style="display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap;">
            <?php if ($unread_count > 0): ?>
                <form method="POST" action="" style="display: inline;">
                    <input type="hidden" name="action" value="mark_all_read">
                    <button type="submit" class="btn btn-sm btn-primary">
                        ✅ <?php echo t('mark_all_read'); ?>
                    </button>
                </form>
            <?php endif; ?>
            <?php if ($read_count > 0): ?>
                <form method="POST" action="" style="display: inline;" onsubmit="return confirm('<?php echo t('delete_read_notifications_confirmation'); ?>')">
                    <input type="hidden" name="action" value="delete_all_read">
                    <button type="submit" class="btn btn-sm btn-danger">
                        🗑️ <?php echo t('delete_read'); ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
        
        <?php if ($notifications): ?>
            <div class="notification-list">
                <?php foreach ($notifications as $notif): ?>
                    <div class="notification-item <?php echo $notif['is_read'] ? 'read' : 'unread'; ?>" 
                         style="display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; border-bottom: 1px solid var(--gray-100); <?php echo $notif['is_read'] ? '' : 'background: var(--red-50); border-left: 4px solid var(--primary-color);'; ?>">
                        <div style="flex: 1;">
                            <a href="<?php echo $notif['link'] ?? '#'; ?>" style="text-decoration: none; color: inherit; display: block;">
                                <div style="font-size: 14px; color: var(--gray-800);"><?php echo htmlspecialchars($notif['message']); ?></div>
                                <div style="font-size: 12px; color: var(--gray-500); margin-top: 4px;">
                                    <span><?php echo date('M d, Y H:i', strtotime($notif['created_at'])); ?></span>
                                    <span style="margin-left: 12px;"><?php echo timeAgo($notif['created_at']); ?></span>
                                </div>
                            </a>
                        </div>
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <?php if (!$notif['is_read']): ?>
                                <span style="background: var(--primary-color); width: 10px; height: 10px; border-radius: 50%; display: inline-block;"></span>
                            <?php endif; ?>
                            <?php if (!$notif['is_read']): ?>
                                <form method="POST" action="" style="display: inline;">
                                    <input type="hidden" name="action" value="mark_read">
                                    <input type="hidden" name="notification_id" value="<?php echo $notif['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-secondary" title="<?php echo t('mark_read'); ?>">
                                        ✅
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div style="display: flex; justify-content: center; gap: 4px; margin-top: 20px; flex-wrap: wrap;">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>" class="btn btn-sm btn-secondary">«</a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>" class="btn btn-sm <?php echo $i === $page ? 'btn-primary' : 'btn-secondary'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>" class="btn btn-sm btn-secondary">»</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div style="text-align: center; padding: 60px 0;">
                <div style="font-size: 48px; margin-bottom: 16px;">🔔</div>
                <h3><?php echo t('no_notifications'); ?></h3>
                <p style="color: var(--gray-500);"><?php echo t('no_notifications_message'); ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    .notification-item.unread {
        background: var(--red-50);
        border-left: 4px solid var(--primary-color);
    }
    
    .notification-item.unread .notification-message {
        font-weight: 500;
    }
    
    .notification-item:hover {
        background: var(--gray-50);
    }
    
    .notification-item.unread:hover {
        background: var(--red-100);
    }
    
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
</style>

<?php include_once 'includes/footer.php'; ?>