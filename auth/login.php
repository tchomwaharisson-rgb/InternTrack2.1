<?php
// auth/login.php
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    if (empty($email) || empty($password)) {
        $error = t('invalid_credentials');
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            if (!$user['is_active']) {
                // Check if there's a pending registration request
                $stmt = $conn->prepare("SELECT * FROM registration_requests WHERE email = ? AND status = 'pending'");
                $stmt->execute([$email]);
                $request = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($request) {
                    $error = t('account_awaiting_approval');
                } else {
                    $error = t('account_inactive');
                }
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['language'] = $user['language'] ?? 'en';
                $_SESSION['theme'] = $user['theme'] ?? 'light';
                
                // Log login
                logAudit($user['id'], 'login', 'User logged in');
                
                // Redirect based on role
                $redirect = '/interntrack/';
                if ($user['role'] === 'admin') {
                    $redirect .= 'admin/dashboard.php';
                } elseif ($user['role'] === 'supervisor') {
                    $redirect .= 'supervisor/dashboard.php';
                } else {
                    $redirect .= 'intern/dashboard.php';
                }
                
                header('Location: ' . $redirect);
                exit;
            }
        } else {
            $error = t('invalid_credentials');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION['language'] ?? 'en'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('login'); ?> - <?php echo t('app_name'); ?></title>
    <link rel="stylesheet" href="/interntrack/assets/css/style.css">
    <link rel="icon" href="/interntrack/assets/images/logo-icon.png">
    <style>
        .password-toggle {
            position: relative;
        }
        .password-toggle .toggle-btn {
            position: absolute;
            right: 12px;
            top: 70%;
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
            
            <h1 class="auth-subtitle"><?php echo t('welcome_back'); ?></h1>
            <p class="auth-subtitle"><?php echo t('login_subtitle'); ?></p>
            
            <?php if ($error): ?>
                <div class="toast toast-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="" id="loginForm">
                <div class="form-group">
                    <label for="email"><?php echo t('email'); ?></label>
                    <input type="email" id="email" name="email" class="form-control" required 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
                
                <div class="form-group password-toggle">
                    <label for="password"><?php echo t('password'); ?></label>
                    <input type="password" id="password" name="password" class="form-control" required>
                    <button type="button" class="toggle-btn" onclick="togglePassword('password')" aria-label="Toggle password visibility">
                        👁️
                    </button>
                </div>
                
                <div class="form-group" style="display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" id="remember" name="remember" <?php echo isset($_POST['remember']) ? 'checked' : ''; ?>>
                    <label for="remember" style="margin: 0;"><?php echo t('remember_me'); ?></label>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block"><?php echo t('login'); ?></button>
            </form>
            
            <div class="auth-footer">
                <p><?php echo t('dont_have_account'); ?> <a href="/interntrack/auth/register.php"><?php echo t('register'); ?></a></p>
                <p><?php echo t('back_to_home'); ?> <a href="/interntrack"><?php echo t('back_to_home'); ?></a></p>
                <p style="margin-top: 8px;"><a href="/interntrack/auth/forgot_password.php"><?php echo t('forgot_password'); ?></a></p>
            </div>
            
            <style>
                .flag-svg {
                    display: inline-block;
                    width: 24px;
                    height: 16px;
                    vertical-align: middle;
                    margin-right: 6px;
                    border-radius: 2px;
                    border: 1px solid rgba(0,0,0,0.1);
                }

                .language-switcher button {
                    display: inline-flex;
                    align-items: center;
                    padding: 6px 12px;
                    border: 1px solid var(--gray-300);
                    border-radius: 6px;
                    background: transparent;
                    cursor: pointer;
                    font-size: 13px;
                    font-weight: 500;
                    transition: all 0.3s ease;
                    color: var(--gray-700);
                    gap: 4px;
                }

                .language-switcher button.active {
                    background: var(--primary-color);
                    color: white;
                    border-color: var(--primary-color);
                }

                .language-switcher button:hover:not(.active) {
                    background: var(--gray-100);
                }
            </style>

                <div class="language-switcher" style="justify-content: center; margin-top: 20px;">
                    <button class="<?php echo ($_SESSION['language'] ?? 'en') === 'en' ? 'active' : ''; ?>" data-lang="en" onclick="switchLanguage('en')">
                        <svg class="flag-svg" viewBox="0 0 60 30" xmlns="http://www.w3.org/2000/svg">
                            <rect width="60" height="30" fill="#FFFFFF"/>
                            <rect width="30" height="30" fill="#012169"/>
                            <line x1="0" y1="0" x2="60" y2="30" stroke="#FFFFFF" stroke-width="3"/>
                            <line x1="60" y1="0" x2="0" y2="30" stroke="#FFFFFF" stroke-width="3"/>
                            <line x1="0" y1="0" x2="60" y2="30" stroke="#C8102E" stroke-width="1.5"/>
                            <line x1="60" y1="0" x2="0" y2="30" stroke="#C8102E" stroke-width="1.5"/>
                            <rect x="0" y="14" width="60" height="2" fill="#FFFFFF"/>
                            <rect x="29" y="0" width="2" height="30" fill="#FFFFFF"/>
                            <rect x="0" y="15" width="60" height="1" fill="#C8102E"/>
                            <rect x="30" y="0" width="1" height="30" fill="#C8102E"/>
                        </svg>
                        EN
                    </button>
                    <button class="<?php echo ($_SESSION['language'] ?? 'en') === 'fr' ? 'active' : ''; ?>" data-lang="fr" onclick="switchLanguage('fr')">
                        <svg class="flag-svg" viewBox="0 0 60 40" xmlns="http://www.w3.org/2000/svg">
                            <rect width="20" height="40" fill="#0055A4"/>
                            <rect x="20" width="20" height="40" fill="#FFFFFF"/>
                            <rect x="40" width="20" height="40" fill="#EF4135"/>
                        </svg>
                        FR
                    </button>
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
            });
        });
    });
    </script>
    <script src="/interntrack/assets/js/main.js"></script>
</body>
</html>