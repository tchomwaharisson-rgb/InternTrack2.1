<?php
// auth/register.php
require_once '../config/config.php';
require_once '../config/language.php';

global $conn ;

if (isLoggedIn()) {
    header('Location: /interntrack/dashboard.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role'] ?? '';
    $email = $_POST['email'] ?? '';
    $first_name = $_POST['first_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Additional fields based on role
    $school = $_POST['school'] ?? '';
    $field_of_study = $_POST['field_of_study'] ?? '';
    $department = $_POST['department'] ?? '';
    $position = $_POST['position'] ?? '';
    
    // Validation
    if (empty($role) || empty($email) || empty($first_name) || empty($last_name) || empty($password)) {
        $error = t('error_occurred') . ' - All fields are required';
    } elseif ($password !== $confirm_password) {
        $error = t('password_mismatch');
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long';
    } elseif ($role === 'intern' && (empty($school) || empty($field_of_study))) {
        $error = 'School and field of study are required for interns';
    } elseif ($role === 'supervisor' && (empty($department) || empty($position))) {
        $error = 'Department and position are required for supervisors';
    } else {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'This email is already registered';
        } else {
            // Check if there's a pending request with this email
            $stmt = $conn->prepare("SELECT id FROM registration_requests WHERE email = ? AND status = 'pending'");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'A registration request with this email is already pending approval';
            } else {
                // Create registration request
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $conn->prepare("INSERT INTO registration_requests 
                    (email, first_name, last_name, role, school, field_of_study, department, position, password_hash) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                if ($stmt->execute([$email, $first_name, $last_name, $role, $school, $field_of_study, $department, $position, $password_hash])) {
                    // Send notification to admin
                    $stmt = $conn->prepare("SELECT id FROM users WHERE role = 'admin' AND is_active = TRUE");
                    $stmt->execute();
                    $admins = $stmt->fetchAll();
                    
                    foreach ($admins as $admin) {
                        $message = "New registration request from " . $first_name . " " . $last_name . " (" . $email . ")";
                        createNotification($admin['id'], 'registration_request', $message, '/interntrack/admin/requests.php');
                    }
                    
                    $success = t('verification_sent') . ' - ' . t('awaiting_approval');
                } else {
                    $error = t('error_occurred');
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION['language'] ?? 'en'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('register'); ?> - <?php echo t('app_name'); ?></title>
    <link rel="stylesheet" href="/interntrack/assets/css/style.css">
    <link rel="icon" href="/interntrack/assets/images/logo-icon.png">
    <style>
        .password-toggle {
            position: relative;
        }
        .password-toggle .toggle-btn {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 18px;
            color: #999;
            padding: 4px;
            transition: color 0.3s;
        }
        .password-toggle .toggle-btn:hover {
            color: var(--primary-red);
        }
        .password-toggle .form-control {
            padding-right: 44px;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-box">
            <div class="auth-logo">
                <a href="/interntrack/" class="header-brand">
                    <!DOCTYPE html>
                    <html>
                    <head>
                    <style>
                    .logo-option1 {
                        display: flex;
                        align-items: center;
                        gap: 12px;
                        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                        text-decoration: none;
                    }

                    .logo-option1 .logo-icon {
                        position: relative;
                        width: 48px;
                        height: 48px;
                        background: linear-gradient(135deg, #D32F2F 0%, #B71C1C 100%);
                        border-radius: 12px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        box-shadow: 0 4px 15px rgba(211, 47, 47, 0.3);
                        transition: transform 0.3s ease;
                    }

                    .logo-option1 .logo-icon:hover {
                        transform: scale(1.05);
                    }

                    .logo-option1 .logo-icon .letter {
                        color: white;
                        font-size: 24px;
                        font-weight: 800;
                        letter-spacing: -1px;
                        position: relative;
                        z-index: 2;
                    }

                    .logo-option1 .logo-icon .track-line {
                        position: absolute;
                        bottom: 8px;
                        left: 8px;
                        right: 8px;
                        height: 3px;
                        background: rgba(255, 255, 255, 0.4);
                        border-radius: 2px;
                        overflow: hidden;
                    }

                    .logo-option1 .logo-icon .track-line::after {
                        content: '';
                        position: absolute;
                        left: -100%;
                        width: 50%;
                        height: 100%;
                        background: white;
                        border-radius: 2px;
                        animation: trackMove 3s ease-in-out infinite;
                    }

                    @keyframes trackMove {
                        0% { left: -100%; }
                        100% { left: 200%; }
                    }

                    .logo-option1 .logo-text {
                        display: flex;
                        flex-direction: column;
                    }

                    .logo-option1 .logo-text .main-text {
                        font-size: 28px;
                        font-weight: 800;
                        color: #1A1A1A;
                        line-height: 1;
                        letter-spacing: -0.5px;
                    }

                    .logo-option1 .logo-text .main-text .red {
                        color: #D32F2F;
                    }

                    .logo-option1 .logo-text .sub-text {
                        font-size: 10px;
                        font-weight: 600;
                        color: #666;
                        letter-spacing: 2.5px;
                        text-transform: uppercase;
                        margin-top: 2px;
                    }
                    </style>
                    </head>
                    <body>
                    <a href="#" class="logo-option1">
                        <div class="logo-icon">
                            <span class="letter">IT</span>
                            <div class="track-line"></div>
                        </div>
                        <div class="logo-text">
                            <span class="main-text">Intern<span class="red">Track</span></span>
                            <span class="sub-text">Management System</span>
                        </div>
                    </a>
                    </body>
                    </html>
                </a>
            </div>
            
            <h1 class="auth-subtitle"><?php echo t('create_account'); ?></h1>
            
            <?php if ($error): ?>
                <div class="toast toast-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="toast toast-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if (!$success): ?>
            <form method="POST" action="" data-validate>
                <div class="form-group">
                    <label><?php echo t('register_as'); ?></label>
                    <div style="display: flex; gap: 12px;">
                        <label style="display: flex; align-items: center; gap: 4px; cursor: pointer;">
                            <input type="radio" name="role" value="intern" required 
                                   <?php echo ($_POST['role'] ?? '') === 'intern' ? 'checked' : ''; ?> 
                                   onchange="toggleRoleFields()">
                            <?php echo t('intern'); ?>
                        </label>
                        <label style="display: flex; align-items: center; gap: 4px; cursor: pointer;">
                            <input type="radio" name="role" value="supervisor" required 
                                   <?php echo ($_POST['role'] ?? '') === 'supervisor' ? 'checked' : ''; ?> 
                                   onchange="toggleRoleFields()">
                            <?php echo t('supervisor'); ?>
                        </label>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name"><?php echo t('first_name'); ?></label>
                        <input type="text" id="first_name" name="first_name" class="form-control" required 
                               value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="last_name"><?php echo t('last_name'); ?></label>
                        <input type="text" id="last_name" name="last_name" class="form-control" required 
                               value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="email"><?php echo t('email'); ?></label>
                    <input type="email" id="email" name="email" class="form-control" required 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
                
                <div class="form-row">
                    <div class="form-group password-toggle">
                        <label for="password"><?php echo t('password'); ?></label>
                        <input type="password" id="password" name="password" class="form-control" required 
                               minlength="6">
                        <button type="button" class="toggle-btn" onclick="togglePassword('password')" aria-label="Toggle password visibility">
                            👁️
                        </button>
                        <small style="font-size: 12px; color: var(--secondary-text);">At least 6 characters</small>
                    </div>
                    <div class="form-group password-toggle">
                        <label for="confirm_password"><?php echo t('confirm_password'); ?></label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required 
                               data-confirm-password="password">
                        <button type="button" class="toggle-btn" onclick="togglePassword('confirm_password')" aria-label="Toggle password visibility">
                            👁️
                        </button>
                    </div>
                </div>
                
                <!-- Intern fields -->
                <div id="intern_fields" style="display: none;">
                    <div class="form-group">
                        <label for="school"><?php echo t('school'); ?></label>
                        <input type="text" id="school" name="school" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['school'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="field_of_study"><?php echo t('field_of_study'); ?></label>
                        <input type="text" id="field_of_study" name="field_of_study" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['field_of_study'] ?? ''); ?>">
                    </div>
                </div>
                
                <!-- Supervisor fields -->
                <div id="supervisor_fields" style="display: none;">
                    <div class="form-group">
                        <label for="department"><?php echo t('department'); ?></label>
                        <input type="text" id="department" name="department" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['department'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="position"><?php echo t('position'); ?></label>
                        <input type="text" id="position" name="position" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['position'] ?? ''); ?>">
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block"><?php echo t('register'); ?></button>
            </form>
            <?php endif; ?>
            
            <div class="auth-footer">
                <p><?php echo t('already_have_account'); ?> <a href="/interntrack/auth/login.php"><?php echo t('login'); ?></a></p>
                <p><?php ?> <a href="/interntrack"><?php echo t('Back to Home'); ?></a></p>
            </div>
            
            <div class="language-switcher" style="justify-content: center; margin-top: 20px;">
                <button class="<?php echo ($_SESSION['language'] ?? 'en') === 'en' ? 'active' : ''; ?>" data-lang="en">🇬🇧 EN</button>
                <button class="<?php echo ($_SESSION['language'] ?? 'en') === 'fr' ? 'active' : ''; ?>" data-lang="fr">🇫🇷 FR</button>
            </div>
        </div>
    </div>
    
    <script>
    function togglePassword(fieldId) {
        const field = document.getElementById(fieldId);
        const type = field.getAttribute('type') === 'password' ? 'text' : 'password';
        field.setAttribute('type', type);
        
        // Change button text/icon
        const btn = field.parentElement.querySelector('.toggle-btn');
        btn.textContent = type === 'password' ? '👁️' : '👁️‍🗨️';
    }
    
    function toggleRoleFields() {
        const role = document.querySelector('input[name="role"]:checked');
        const internFields = document.getElementById('intern_fields');
        const supervisorFields = document.getElementById('supervisor_fields');
        
        if (role) {
            if (role.value === 'intern') {
                internFields.style.display = 'block';
                supervisorFields.style.display = 'none';
                document.getElementById('school').required = true;
                document.getElementById('field_of_study').required = true;
                document.getElementById('department').required = false;
                document.getElementById('position').required = false;
            } else if (role.value === 'supervisor') {
                internFields.style.display = 'none';
                supervisorFields.style.display = 'block';
                document.getElementById('school').required = false;
                document.getElementById('field_of_study').required = false;
                document.getElementById('department').required = true;
                document.getElementById('position').required = true;
            }
        }
    }
    
    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        toggleRoleFields();
    });
    </script>
    
    <script src="/interntrack/assets/js/main.js"></script>
</body>
</html>