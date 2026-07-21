<?php
// admin/profile.php
require_once '../config/config.php';
require_once '../config/language.php';

// Make sure $conn is available
global $conn;

if (!isLoggedIn() || !hasRole('admin')) {
    header('Location: /interntrack/auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Get user data
$user = getUserData($user_id);

// Get admin statistics
$stmt = $conn->prepare("SELECT COUNT(*) as total_users FROM users");
$stmt->execute();
$total_users = $stmt->fetch(PDO::FETCH_ASSOC)['total_users'];

$stmt = $conn->prepare("SELECT COUNT(*) as total_interns FROM users WHERE role = 'intern'");
$stmt->execute();
$total_interns = $stmt->fetch(PDO::FETCH_ASSOC)['total_interns'];

$stmt = $conn->prepare("SELECT COUNT(*) as total_supervisors FROM users WHERE role = 'supervisor'");
$stmt->execute();
$total_supervisors = $stmt->fetch(PDO::FETCH_ASSOC)['total_supervisors'];

$stmt = $conn->prepare("SELECT COUNT(*) as pending_requests FROM registration_requests WHERE status = 'pending'");
$stmt->execute();
$pending_requests = $stmt->fetch(PDO::FETCH_ASSOC)['pending_requests'];

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'update_profile') {
        $first_name = sanitize($_POST['first_name'] ?? '');
        $last_name = sanitize($_POST['last_name'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $address = sanitize($_POST['address'] ?? '');
        $bio = sanitize($_POST['bio'] ?? '');
        
        // Update users table
        $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ?, address = ?, bio = ? WHERE id = ?");
        if ($stmt->execute([$first_name, $last_name, $phone, $address, $bio, $user_id])) {
            // Update session name
            $_SESSION['user_name'] = $first_name . ' ' . $last_name;
            
            $message = t('profile_updated');
            $message_type = 'success';
            logAudit($user_id, 'update_profile', 'Updated profile');
            
            // Refresh user data
            $user = getUserData($user_id);
        } else {
            $message = t('error_occurred');
            $message_type = 'error';
        }
        
    } elseif ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (!password_verify($current_password, $user['password'])) {
            $message = t('current_password_incorrect');
            $message_type = 'error';
        } elseif (strlen($new_password) < 8) {
            $message = t('password_too_short');
            $message_type = 'error';
        } elseif ($new_password !== $confirm_password) {
            $message = t('password_mismatch');
            $message_type = 'error';
        } else {
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            if ($stmt->execute([$new_hash, $user_id])) {
                $message = t('password_updated');
                $message_type = 'success';
                logAudit($user_id, 'change_password', 'Changed password');
            } else {
                $message = t('error_occurred');
                $message_type = 'error';
            }
        }
    } elseif ($action === 'update_settings') {
        $language = $_POST['language'] ?? 'en';
        $theme = $_POST['theme'] ?? 'light';
        
        $stmt = $conn->prepare("UPDATE users SET language = ?, theme = ? WHERE id = ?");
        if ($stmt->execute([$language, $theme, $user_id])) {
            $_SESSION['language'] = $language;
            $_SESSION['theme'] = $theme;
            $message = t('settings_updated');
            $message_type = 'success';
            logAudit($user_id, 'update_settings', 'Updated user preferences');
            $user = getUserData($user_id);
        } else {
            $message = t('error_occurred');
            $message_type = 'error';
        }
    }
}

// Get profile picture
$profile_picture = $user['profile_picture'] ?? null;
$default_avatar = '/interntrack/assets/images/default-avatar.png';
$avatar_url = $profile_picture ? '/interntrack/uploads/profiles/' . $profile_picture : $default_avatar;

include_once '../includes/header.php';
?>

<div class="main-content">
    <?php if ($message): ?>
        <div class="toast toast-<?php echo $message_type; ?>"><?php echo $message; ?></div>
    <?php endif; ?>
    
    <!-- Admin Stats Cards -->
    <div class="stats-grid" style="margin-bottom: 24px;">
        <div class="stat-card">
            <div class="stat-icon">👤</div>
            <div class="stat-value"><?php echo $total_users; ?></div>
            <div class="stat-label"><?php echo t('total_users'); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">👨‍🎓</div>
            <div class="stat-value"><?php echo $total_interns; ?></div>
            <div class="stat-label"><?php echo t('total_interns'); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">👔</div>
            <div class="stat-value"><?php echo $total_supervisors; ?></div>
            <div class="stat-label"><?php echo t('total_supervisors'); ?></div>
        </div>
        <div class="stat-card" style="border-left-color: #f59e0b;">
            <div class="stat-icon">📝</div>
            <div class="stat-value"><?php echo $pending_requests; ?></div>
            <div class="stat-label"><?php echo t('pending_requests'); ?></div>
        </div>
    </div>

    <!-- Profile Information -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?php echo t('profile'); ?></h3>
            <span class="status-badge active"><?php echo t('admin'); ?></span>
        </div>
        
        <div style="display: flex; gap: 24px; flex-wrap: wrap;">
            <!-- Profile Picture Section -->
            <div style="text-align: center; min-width: 150px;">
                <div style="position: relative; width: 120px; height: 120px; margin: 0 auto 12px;">
                    <?php if ($profile_picture): ?>
                        <img src="<?php echo $avatar_url; ?>" 
                             alt="Profile" 
                             id="profilePreview"
                             data-default="<?php echo $default_avatar; ?>"
                             style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 3px solid var(--primary-color); box-shadow: var(--shadow-md);">
                    <?php else: ?>
                        <div id="profilePreview" 
                             data-default="<?php echo $default_avatar; ?>"
                             style="width: 120px; height: 120px; border-radius: 50%; background: var(--red-gradient); color: white; display: flex; align-items: center; justify-content: center; font-size: 48px; font-weight: 700; border: 3px solid var(--primary-color); box-shadow: var(--shadow-md);">
                            <?php 
                                $name = $user['first_name'] . ' ' . $user['last_name'];
                                $parts = explode(' ', $name);
                                echo strtoupper($parts[0][0] ?? 'A') . (isset($parts[1]) ? strtoupper($parts[1][0] ?? '') : '');
                            ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Upload Button Overlay -->
                    <div style="position: absolute; bottom: 0; right: 0; background: var(--primary-color); border-radius: 50%; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; cursor: pointer; border: 2px solid white; transition: all var(--transition-speed); box-shadow: 0 2px 8px rgba(0,0,0,0.2);" 
                         id="uploadProfileBtn"
                         title="<?php echo t('upload_photo'); ?>">
                        <span style="color: white; font-size: 16px;">📷</span>
                    </div>
                </div>
                
                <input type="file" id="profilePictureInput" accept="image/jpeg,image/png,image/gif,image/webp" style="display: none;">
                
                <div style="margin-top: 8px;">
                    <?php if ($profile_picture): ?>
                        <button id="removeProfileBtn" class="btn btn-sm btn-danger" style="margin-top: 8px;">
                            🗑️ <?php echo t('remove_photo'); ?>
                        </button>
                    <?php else: ?>
                        <button id="removeProfileBtn" class="btn btn-sm btn-danger" style="display: none; margin-top: 8px;">
                            🗑️ <?php echo t('remove_photo'); ?>
                        </button>
                    <?php endif; ?>
                </div>
                
                <div style="font-size: 18px; font-weight: 600; color: var(--gray-800); margin-top: 12px;">
                    <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                </div>
                <div style="color: var(--gray-500);">
                    <?php echo t('admin'); ?>
                </div>
                <div style="margin-top: 8px;">
                    <span class="status-badge <?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                        <?php echo $user['is_active'] ? t('active') : t('inactive'); ?>
                    </span>
                </div>
                <?php if ($user['created_at']): ?>
                    <div style="margin-top: 8px; font-size: 12px; color: var(--gray-400);">
                        <?php echo t('member_since'); ?>: <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Profile Details -->
            <div style="flex: 1; min-width: 300px;">
                <form method="POST" action="" id="profileForm">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name"><?php echo t('first_name'); ?></label>
                            <input type="text" id="first_name" name="first_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="last_name"><?php echo t('last_name'); ?></label>
                            <input type="text" id="last_name" name="last_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email"><?php echo t('email'); ?></label>
                        <input type="email" id="email" class="form-control" 
                               value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                        <small style="color: var(--gray-500); font-size: 12px;"><?php echo t('email_cannot_be_changed'); ?></small>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone"><?php echo t('phone_number'); ?></label>
                        <input type="tel" id="phone" name="phone" class="form-control" 
                               value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="address"><?php echo t('address'); ?></label>
                        <input type="text" id="address" name="address" class="form-control" 
                               value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="bio"><?php echo t('bio'); ?></label>
                        <textarea id="bio" name="bio" class="form-control" rows="3"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary"><?php echo t('update_profile'); ?></button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- User Preferences -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?php echo t('user_preferences'); ?></h3>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="update_settings">
            <div class="form-row">
                <div class="form-group">
                    <label for="language"><?php echo t('language'); ?></label>
                    <select id="language" name="language" class="form-control">
                        <option value="en" <?php echo ($user['language'] ?? 'en') === 'en' ? 'selected' : ''; ?>>
                            <?php echo t('english'); ?>
                        </option>
                        <option value="fr" <?php echo ($user['language'] ?? 'en') === 'fr' ? 'selected' : ''; ?>>
                            <?php echo t('french'); ?>
                        </option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="theme"><?php echo t('theme'); ?></label>
                    <select id="theme" name="theme" class="form-control">
                        <option value="light" <?php echo ($user['theme'] ?? 'light') === 'light' ? 'selected' : ''; ?>>
                            ☀️ <?php echo t('light_mode'); ?>
                        </option>
                        <option value="dark" <?php echo ($user['theme'] ?? 'light') === 'dark' ? 'selected' : ''; ?>>
                            🌙 <?php echo t('dark_mode'); ?>
                        </option>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><?php echo t('save_settings'); ?></button>
        </form>
    </div>
    
    <!-- Change Password -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?php echo t('change_password'); ?></h3>
        </div>
        <form method="POST" action="" id="passwordForm">
            <input type="hidden" name="action" value="change_password">
            <div class="form-group">
                <label for="current_password"><?php echo t('current_password'); ?></label>
                <div class="password-toggle">
                    <input type="password" id="current_password" name="current_password" class="form-control" required>
                    <button type="button" class="toggle-btn" onclick="togglePassword('current_password')" aria-label="Toggle password visibility">
                        👁️
                    </button>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="new_password"><?php echo t('new_password'); ?></label>
                    <div class="password-toggle">
                        <input type="password" id="new_password" name="new_password" class="form-control" required minlength="8">
                        <button type="button" class="toggle-btn" onclick="togglePassword('new_password')" aria-label="Toggle password visibility">
                            👁️
                        </button>
                    </div>
                    <small style="color: var(--gray-500); font-size: 12px;"><?php echo t('password_requirements'); ?></small>
                </div>
                <div class="form-group">
                    <label for="confirm_password"><?php echo t('confirm_new_password'); ?></label>
                    <div class="password-toggle">
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                        <button type="button" class="toggle-btn" onclick="togglePassword('confirm_password')" aria-label="Toggle password visibility">
                            👁️
                        </button>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><?php echo t('change_password'); ?></button>
        </form>
    </div>
    
    <!-- Admin Actions -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?php echo t('admin_actions'); ?></h3>
        </div>
        <div style="display: flex; flex-wrap: wrap; gap: 12px;">
            <a href="/interntrack/admin/users.php" class="btn btn-secondary">
                <span>👥</span> <?php echo t('manage_users'); ?>
            </a>
            <a href="/interntrack/admin/requests.php" class="btn btn-secondary">
                <span>📝</span> <?php echo t('registration_requests'); ?>
                <?php if ($pending_requests > 0): ?>
                    <span class="badge badge-warning"><?php echo $pending_requests; ?></span>
                <?php endif; ?>
            </a>
            <a href="/interntrack/admin/settings.php" class="btn btn-secondary">
                <span>⚙️</span> <?php echo t('system_settings'); ?>
            </a>
            <a href="/interntrack/admin/reports.php" class="btn btn-secondary">
                <span>📊</span> <?php echo t('system_reports'); ?>
            </a>
            <a href="/interntrack/admin/audit.php" class="btn btn-secondary">
                <span>📜</span> <?php echo t('audit_trail'); ?>
            </a>
        </div>
    </div>
</div>

<script>
// Toggle password visibility
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const type = field.getAttribute('type') === 'password' ? 'text' : 'password';
    field.setAttribute('type', type);
    const btn = field.parentElement.querySelector('.toggle-btn');
    btn.textContent = type === 'password' ? '👁️' : '👁️‍🗨️';
}

// Password confirmation validation
document.getElementById('confirm_password')?.addEventListener('input', function() {
    const password = document.getElementById('new_password');
    if (this.value !== password.value) {
        this.setCustomValidity('<?php echo t('password_mismatch'); ?>');
    } else {
        this.setCustomValidity('');
    }
});

// Profile Picture Upload
document.addEventListener('DOMContentLoaded', function() {
    const uploadBtn = document.getElementById('uploadProfileBtn');
    const fileInput = document.getElementById('profilePictureInput');
    const removeBtn = document.getElementById('removeProfileBtn');
    const preview = document.getElementById('profilePreview');
    
    if (uploadBtn && fileInput) {
        uploadBtn.addEventListener('click', function() {
            fileInput.click();
        });
    }
    
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const file = this.files[0];
                const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!allowedTypes.includes(file.type)) {
                    showToast('<?php echo t('invalid_file_type'); ?>', 'error');
                    this.value = '';
                    return;
                }
                if (file.size > 5 * 1024 * 1024) {
                    showToast('<?php echo t('file_too_large'); ?>', 'error');
                    this.value = '';
                    return;
                }
                uploadProfilePicture(file);
            }
        });
    }
    
    if (removeBtn) {
        removeBtn.addEventListener('click', function() {
            if (confirm('Are you sure you want to remove your profile picture?')) {
                removeProfilePicture();
            }
        });
    }
});

function uploadProfilePicture(file) {
    const formData = new FormData();
    formData.append('action', 'upload_profile_picture');
    formData.append('profile_picture', file);
    
    const uploadBtn = document.getElementById('uploadProfileBtn');
    const originalText = uploadBtn.innerHTML;
    uploadBtn.innerHTML = '⏳';
    uploadBtn.disabled = true;
    
    fetch('/interntrack/api/upload.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('<?php echo t('photo_uploaded'); ?>', 'success');
            const preview = document.getElementById('profilePreview');
            if (preview) {
                preview.src = data.url + '?t=' + new Date().getTime();
                preview.style.display = 'block';
            }
            document.getElementById('removeProfileBtn').style.display = 'inline-block';
            updateHeaderAvatar(data.url);
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast(data.message || '<?php echo t('upload_failed'); ?>', 'error');
        }
    })
    .catch(error => {
        showToast('<?php echo t('upload_failed'); ?>', 'error');
        console.error('Upload error:', error);
    })
    .finally(() => {
        uploadBtn.innerHTML = originalText;
        uploadBtn.disabled = false;
        document.getElementById('profilePictureInput').value = '';
    });
}

function removeProfilePicture() {
    const removeBtn = document.getElementById('removeProfileBtn');
    removeBtn.innerHTML = '⏳';
    removeBtn.disabled = true;
    
    const formData = new FormData();
    formData.append('action', 'remove_profile_picture');
    
    fetch('/interntrack/api/upload.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('<?php echo t('photo_removed'); ?>', 'success');
            const preview = document.getElementById('profilePreview');
            if (preview) {
                const defaultAvatar = preview.dataset.default || '/interntrack/assets/images/default-avatar.png';
                preview.src = defaultAvatar;
            }
            removeBtn.style.display = 'none';
            updateHeaderAvatar(null);
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast(data.message || '<?php echo t('remove_failed'); ?>', 'error');
        }
    })
    .catch(error => {
        showToast('<?php echo t('remove_failed'); ?>', 'error');
        console.error('Remove error:', error);
    })
    .finally(() => {
        removeBtn.innerHTML = '🗑️ <?php echo t('remove_photo'); ?>';
        removeBtn.disabled = false;
    });
}

function updateHeaderAvatar(imageUrl) {
    const avatar = document.querySelector('.user-avatar');
    if (avatar) {
        if (imageUrl) {
            avatar.innerHTML = `<img src="${imageUrl}" alt="Profile" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">`;
        } else {
            const name = '<?php echo $user['first_name'] . ' ' . $user['last_name']; ?>';
            const parts = name.split(' ');
            const initials = parts.map(n => n[0]).join('').toUpperCase();
            avatar.innerHTML = initials;
        }
    }
}

// Toast notification helper
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
</script>

<?php include_once '../includes/footer.php'; ?>