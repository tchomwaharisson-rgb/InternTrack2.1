<?php
require_once '../config/config.php';
require_once '../config/language.php';

global $conn;

if (!isLoggedIn() || !hasRole('intern')) {
    header('Location: /interntrack/auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Get user data
$user = getUserData($user_id);
$stmt = $conn->prepare("SELECT * FROM interns WHERE user_id = ?");
$stmt->execute([$user_id]);
$intern = $stmt->fetch(PDO::FETCH_ASSOC);

// Get supervisor info
$supervisor = null;
if ($intern && $intern['supervisor_id']) {
    $supervisor = getUserData($intern['supervisor_id']);
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'update_profile') {
        $first_name = sanitize($_POST['first_name'] ?? '');
        $last_name = sanitize($_POST['last_name'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $address = sanitize($_POST['address'] ?? '');
        $bio = sanitize($_POST['bio'] ?? '');
        $school = sanitize($_POST['school'] ?? '');
        $field_of_study = sanitize($_POST['field_of_study'] ?? '');
        
        // Update users table
        $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ?, address = ?, bio = ? WHERE id = ?");
        $stmt->execute([$first_name, $last_name, $phone, $address, $bio, $user_id]);
        
        // Update interns table
        $stmt = $conn->prepare("UPDATE interns SET school = ?, field_of_study = ? WHERE user_id = ?");
        $stmt->execute([$school, $field_of_study, $user_id]);
        
        // Update session name
        $_SESSION['user_name'] = $first_name . ' ' . $last_name;
        
        $message = t('profile_updated');
        $message_type = 'success';
        logAudit($user_id, 'update_profile', 'Updated profile');
        
        // Refresh user data
        $user = getUserData($user_id);
        $stmt = $conn->prepare("SELECT * FROM interns WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $intern = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } elseif ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (!password_verify($current_password, $user['password'])) {
            $message = 'Current password is incorrect';
            $message_type = 'error';
        } elseif (strlen($new_password) < 8) {
            $message = 'New password must be at least 8 characters';
            $message_type = 'error';
        } elseif ($new_password !== $confirm_password) {
            $message = t('password_mismatch');
            $message_type = 'error';
        } else {
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$new_hash, $user_id]);
            $message = 'Password changed successfully';
            $message_type = 'success';
            logAudit($user_id, 'change_password', 'Changed password');
        }
    }
}

include_once '../includes/header.php';
?>

<div class="main-content">
    <?php if ($message): ?>
        <div class="toast toast-<?php echo $message_type; ?>"><?php echo $message; ?></div>
    <?php endif; ?>
    
    <!-- Profile Info -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?php echo t('profile'); ?></h3>
        </div>
        
        <div style="display: flex; gap: 24px; flex-wrap: wrap;">
            <!-- Profile Picture -->
            <div style="text-align: center; min-width: 150px;">
                <div style="width: 120px; height: 120px; border-radius: 50%; background: var(--primary-red); color: white; display: flex; align-items: center; justify-content: center; font-size: 48px; font-weight: 700; margin: 0 auto 12px;">
                    <?php 
                        $name = $user['first_name'] . ' ' . $user['last_name'];
                        $parts = explode(' ', $name);
                        echo strtoupper($parts[0][0] ?? 'U') . (isset($parts[1]) ? strtoupper($parts[1][0] ?? '') : '');
                    ?>
                </div>
                <?php if ($user['profile_picture']): ?>
                    <img src="/interntrack/uploads/<?php echo $user['profile_picture']; ?>" 
                         alt="Profile" style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; margin: 0 auto 12px;">
                <?php endif; ?>
                <div style="font-size: 18px; font-weight: 600;">
                    <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                </div>
                <div style="color: var(--secondary-text);">Intern</div>
                <div style="margin-top: 8px;">
                    <span class="status-badge <?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                        <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                    </span>
                </div>
            </div>
            
            <!-- Profile Details -->
            <div style="flex: 1; min-width: 300px;">
                <form method="POST" action="">
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
                        <input type="email" id="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="phone"><?php echo t('phone_number'); ?></label>
                            <input type="tel" id="phone" name="phone" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="school"><?php echo t('school'); ?></label>
                            <input type="text" id="school" name="school" class="form-control" 
                                   value="<?php echo htmlspecialchars($intern['school'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="field_of_study"><?php echo t('field_of_study'); ?></label>
                            <input type="text" id="field_of_study" name="field_of_study" class="form-control" 
                                   value="<?php echo htmlspecialchars($intern['field_of_study'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Internship Duration</label>
                            <input type="text" class="form-control" 
                                   value="<?php echo ($intern['start_date'] ?? '') ? date('M d, Y', strtotime($intern['start_date'])) . ' - ' . date('M d, Y', strtotime($intern['end_date'])) : 'Not set'; ?>" disabled>
                        </div>
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
                    
                    <?php if ($supervisor): ?>
                        <div class="form-group">
                            <label><?php echo t('supervisor'); ?></label>
                            <input type="text" class="form-control" 
                                   value="<?php echo htmlspecialchars($supervisor['first_name'] . ' ' . $supervisor['last_name']); ?>" disabled>
                        </div>
                    <?php endif; ?>
                    
                    <button type="submit" class="btn btn-primary"><?php echo t('update_profile'); ?></button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Change Password -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?php echo t('change_password'); ?></h3>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="change_password">
            <div class="form-row">
                <div class="form-group">
                    <label for="current_password"><?php echo t('current_password'); ?></label>
                    <input type="password" id="current_password" name="current_password" class="form-control" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="new_password"><?php echo t('new_password'); ?></label>
                    <input type="password" id="new_password" name="new_password" class="form-control" required minlength="8">
                </div>
                <div class="form-group">
                    <label for="confirm_password"><?php echo t('confirm_new_password'); ?></label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><?php echo t('change_password'); ?></button>
        </form>
    </div>
</div>
<!-- Profile Picture Section -->
<div style="text-align: center; min-width: 150px;">
    <?php 
    $profile_picture = $user['profile_picture'] ?? null;
    $default_avatar = '/interntrack/assets/images/default-avatar.png';
    $avatar_url = $profile_picture ? '/interntrack/uploads/profiles/' . $profile_picture : $default_avatar;
    ?>
    <div style="position: relative; width: 120px; height: 120px; margin: 0 auto 12px;">
        <?php if ($profile_picture): ?>
            <img src="<?php echo $avatar_url; ?>" 
                 alt="Profile" 
                 id="profilePreview"
                 data-default="<?php echo $default_avatar; ?>"
                 style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 3px solid var(--primary-color);">
        <?php else: ?>
            <div id="profilePreview" 
                 data-default="<?php echo $default_avatar; ?>"
                 style="width: 120px; height: 120px; border-radius: 50%; background: var(--red-gradient); color: white; display: flex; align-items: center; justify-content: center; font-size: 48px; font-weight: 700; border: 3px solid var(--primary-color);">
                <?php 
                    $name = $user['first_name'] . ' ' . $user['last_name'];
                    $parts = explode(' ', $name);
                    echo strtoupper($parts[0][0] ?? 'U') . (isset($parts[1]) ? strtoupper($parts[1][0] ?? '') : '');
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
        <?php 
            $role = $user['role'] ?? '';
            echo t($role);
        ?>
    </div>
    <div style="margin-top: 8px;">
        <span class="status-badge <?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
            <?php echo $user['is_active'] ? t('active') : t('inactive'); ?>
        </span>
    </div>
</div>

<!-- Add JavaScript at the bottom of the page -->
<script src="/interntrack/assets/js/profile-picture.js"></script>

<?php include_once '../includes/footer.php'; ?>