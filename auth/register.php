<?php
// auth/register.php
require_once '../config/config.php';
require_once '../config/language.php';

// Make sure language is set
if (!isset($_SESSION['language'])) {
    $_SESSION['language'] = 'en';
}

global $conn;

if (isLoggedIn()) {
    header('Location: /interntrack/dashboard.php');
    exit;
}



$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $first_name = sanitize($_POST['first_name'] ?? '');
    $last_name = sanitize($_POST['last_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Intern specific fields
    $school = sanitize($_POST['school'] ?? '');
    $field_of_study = sanitize($_POST['field_of_study'] ?? '');
    $theme = sanitize($_POST['theme'] ?? ''); // Optional theme field
    $internship_duration = (int)($_POST['internship_duration'] ?? 3);
    $start_date = $_POST['start_date'] ?? date('Y-m-d');
    $end_date = $_POST['end_date'] ?? date('Y-m-d', strtotime('+3 months'));
    
    // Validation
    if (empty($email) || empty($first_name) || empty($last_name) || empty($password)) {
        $error = t('please_fill_all_fields');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = t('invalid_email_format');
    } elseif ($password !== $confirm_password) {
        $error = t('password_mismatch');
    } elseif (strlen($password) < 8) {
        $error = t('password_too_short');
    } elseif (empty($school) || empty($field_of_study)) {
        $error = 'School and field of study are required for interns';
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
                // Create registration request - only interns now
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $role = 'intern'; // Always intern
                
                $stmt = $conn->prepare("INSERT INTO registration_requests 
                    (email, first_name, last_name, role, school, field_of_study, theme, internship_duration, start_date, end_date, password_hash) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                if ($stmt->execute([
                    $email, 
                    $first_name, 
                    $last_name, 
                    $role, 
                    $school, 
                    $field_of_study, 
                    $theme, 
                    $internship_duration, 
                    $start_date, 
                    $end_date, 
                    $password_hash
                ])) {
                    // Send notification to admin
                    $stmt = $conn->prepare("SELECT id FROM users WHERE role = 'admin' AND is_active = TRUE");
                    $stmt->execute();
                    $admins = $stmt->fetchAll();
                    
                    foreach ($admins as $admin) {
                        $message = "New registration request from " . $first_name . " " . $last_name . " (" . $email . ")";
                        if (!empty($theme)) {
                            $message .= " - Theme: " . $theme;
                        }
                        createNotification($admin['id'], 'registration_request', $message, '/interntrack/admin/requests.php');
                    }
                    
                    $success = t('registration_success');
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
                               minlength="8">
                        <button type="button" class="toggle-btn" onclick="togglePassword('password')" aria-label="Toggle password visibility">
                            👁️
                        </button>
                        <small style="font-size: 12px; color: var(--secondary-text);"><?php echo t('password_length'); ?></small>
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
            
                <div class="form-group">
                    <label for="school"><?php echo t('school'); ?></label>
                    <input type="text" id="school" name="school" class="form-control" 
                           value="<?php echo htmlspecialchars($_POST['school'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="field_of_study"><?php echo t('field_of_study'); ?></label>
                    <input type="text" id="field_of_study" name="field_of_study" class="form-control" 
                           value="<?php echo htmlspecialchars($_POST['field of study'] ?? ''); ?>">
                </div>
                <!-- Theme Field - Optional -->
                <div class="form-group">
                    <label for="theme">
                        <?php echo t('internship_theme'); ?>
                        <span class="optional">(<?php echo t('optional'); ?>)</span>
                    </label>
                    <input type="text" id="theme" name="theme" class="form-control" 
                           placeholder="<?php echo t('enter_internship_theme'); ?>" 
                           value="<?php echo htmlspecialchars($_POST['theme'] ?? ''); ?>">
                    <small style="color: var(--gray-500); font-size: 12px; display: block; margin-top: 4px;">
                        <?php echo t('theme_help_text'); ?>
                    </small>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="internship_duration"><?php echo t('internship_duration_months'); ?></label>
                        <select id="internship_duration" name="internship_duration" class="form-control" required>
                            <option value="1" <?php echo ($_POST['internship_duration'] ?? 3) == 1 ? 'selected' : ''; ?>>1 <?php echo t('month'); ?></option>
                            <option value="2" <?php echo ($_POST['internship_duration'] ?? 3) == 2 ? 'selected' : ''; ?>>2 <?php echo t('months'); ?></option>
                            <option value="3" <?php echo ($_POST['internship_duration'] ?? 3) == 3 ? 'selected' : ''; ?>>3 <?php echo t('months'); ?></option>
                            <option value="4" <?php echo ($_POST['internship_duration'] ?? 3) == 4 ? 'selected' : ''; ?>>4 <?php echo t('months'); ?></option>
                            <option value="6" <?php echo ($_POST['internship_duration'] ?? 3) == 6 ? 'selected' : ''; ?>>6 <?php echo t('months'); ?></option>
                            <option value="12" <?php echo ($_POST['internship_duration'] ?? 3) == 12 ? 'selected' : ''; ?>>12 <?php echo t('months'); ?></option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="start_date"><?php echo t('start_date'); ?></label>
                        <input type="date" id="start_date" name="start_date" class="form-control" required 
                               value="<?php echo $_POST['start_date'] ?? date('Y-m-d'); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="end_date"><?php echo t('end_date'); ?></label>
                    <input type="date" id="end_date" name="end_date" class="form-control" required 
                           value="<?php echo $_POST['end_date'] ?? date('Y-m-d', strtotime('+3 months')); ?>">
                </div>
                
                <button type="submit" class="btn btn-primary btn-block"><?php echo t('register'); ?></button>
            </form>
            <?php endif; ?>
            
            <div class="auth-footer">
                <p><?php echo t('already_have_account'); ?> <a href="/interntrack/auth/login.php"><?php echo t('login'); ?></a></p>
                <p><?php echo t('back_to_home'); ?> <a href="/interntrack"><?php echo t('back_to_home'); ?></a></p>
            </div>
            
            <div class="language-switcher" style="justify-content: center; margin-top: 20px;">
                <button class="<?php echo ($_SESSION['language'] ?? 'en') === 'en' ? 'active' : ''; ?>" data-lang="en">EN</button>
                <button class="<?php echo ($_SESSION['language'] ?? 'en') === 'fr' ? 'active' : ''; ?>" data-lang="fr">FR</button>
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

    function switchLanguage(lang) {
        fetch('/interntrack/api/settings.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ action: 'language', language: lang })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        });
    }

    // Auto-calculate end date based on start date and duration
    document.getElementById('start_date')?.addEventListener('change', function() {
        calculateEndDate();
    });

    document.getElementById('internship_duration')?.addEventListener('change', function() {
        calculateEndDate();
    });

    function calculateEndDate() {
        const startDate = document.getElementById('start_date').value;
        const duration = parseInt(document.getElementById('internship_duration').value) || 3;
        
        if (startDate) {
            const date = new Date(startDate);
            date.setMonth(date.getMonth() + duration);
            // Subtract 1 day to get the correct end date
            date.setDate(date.getDate() - 1);
            const endDate = date.toISOString().split('T')[0];
            document.getElementById('end_date').value = endDate;
        }
    }

    // Password confirmation validation
    document.getElementById('confirm_password')?.addEventListener('input', function() {
        const password = document.getElementById('password');
        if (this.value !== password.value) {
            this.setCustomValidity('<?php echo t('password_mismatch'); ?>');
        } else {
            this.setCustomValidity('');
        }
    });

    // Form validation
    document.querySelector('form')?.addEventListener('submit', function(e) {
        const password = document.getElementById('password');
        const confirm = document.getElementById('confirm_password');
        
        if (password.value.length < 8) {
            e.preventDefault();
            alert('<?php echo t('password_too_short'); ?>');
            return false;
        }
        
        if (password.value !== confirm.value) {
            e.preventDefault();
            alert('<?php echo t('password_mismatch'); ?>');
            return false;
        }
    });

    // Language switcher
    document.querySelectorAll('.language-switcher button').forEach(btn => {
        btn.addEventListener('click', function() {
            const lang = this.dataset.lang;
            document.querySelectorAll('.language-switcher button').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            fetch('/interntrack/api/settings.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ action: 'language', language: lang })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            })
            .catch(error => console.error('Error switching language:', error));
        });
    });

    // Initialize dark mode
    document.addEventListener('DOMContentLoaded', function() {
        const theme = document.documentElement.getAttribute('data-theme') || 'light';
        if (theme === 'dark') {
            document.body.classList.add('dark-mode');
            const darkModeCss = document.querySelector('link[href*="dark-mode.css"]');
            if (darkModeCss) {
                darkModeCss.disabled = false;
            }
        }
        
        // Calculate end date on load if start date is set
        if (document.getElementById('start_date').value) {
            calculateEndDate();
        }
    });
    </script>
</body>
</html>