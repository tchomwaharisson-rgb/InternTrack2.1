<?php
// admin/requests.php
require_once '../config/config.php';
require_once '../config/language.php';
require_once '../includes/email_templates.php';
require_once '../config/email.php';

// Make sure $conn is available
global $conn;

if (!isLoggedIn() || !hasRole('admin')) {
    header('Location: /interntrack/auth/login.php');
    exit;
}

$message = '';
$message_type = '';

// Get filter from URL
$filter = $_GET['filter'] ?? 'pending';

// Handle request actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $request_id = $_POST['request_id'] ?? 0;
    $admin_comment = $_POST['admin_comment'] ?? '';
    
    switch ($action) {
        case 'approve':
            // Get the request data
            $stmt = $conn->prepare("SELECT * FROM registration_requests WHERE id = ? AND status = 'pending'");
            $stmt->execute([$request_id]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($request) {
                // CHECK: Does the email already exist in users table?
                $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$request['email']]);
                $existing_user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existing_user) {
                    $stmt = $conn->prepare("UPDATE registration_requests SET status = 'rejected', 
                                           reviewed_at = NOW(), reviewed_by = ?, admin_comment = ? WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id'], 'Email already exists in the system.', $request_id]);
                    
                    $message = 'Email "' . $request['email'] . '" already exists in the system. Request has been rejected.';
                    $message_type = 'error';
                    logAudit($_SESSION['user_id'], 'reject_registration', 
                             'Rejected request ID: ' . $request_id . ' - Email already exists');
                    break;
                }
                
                // CHECK: Is there another pending request with the same email?
                $stmt = $conn->prepare("SELECT id FROM registration_requests WHERE email = ? AND id != ? AND status = 'pending'");
                $stmt->execute([$request['email'], $request_id]);
                $duplicate_request = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($duplicate_request) {
                    $stmt = $conn->prepare("UPDATE registration_requests SET status = 'rejected', 
                                           reviewed_at = NOW(), reviewed_by = ?, admin_comment = ? WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id'], 'Duplicate pending request exists.', $request_id]);
                    
                    $message = 'Another pending request with this email already exists.';
                    $message_type = 'error';
                    logAudit($_SESSION['user_id'], 'reject_registration', 
                             'Rejected request ID: ' . $request_id . ' - Duplicate pending request');
                    break;
                }
                
                // All checks passed - proceed with approval
                // USE THE USER'S ORIGINAL PASSWORD
                $password_hash = $request['password_hash'];
                $temp_password = ''; // No temporary password needed, using original
                
                // Create the user account
                $stmt = $conn->prepare("INSERT INTO users (email, password, first_name, last_name, role, is_active) 
                                       VALUES (?, ?, ?, ?, ?, TRUE)");
                try {
                    $stmt->execute([
                        $request['email'],
                        $password_hash,
                        $request['first_name'],
                        $request['last_name'],
                        $request['role']
                    ]);
                } catch (PDOException $e) {
                    $stmt = $conn->prepare("UPDATE registration_requests SET status = 'rejected', 
                                           reviewed_at = NOW(), reviewed_by = ?, admin_comment = ? WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id'], 'Database error: ' . $e->getMessage(), $request_id]);
                    
                    $message = 'Error creating user: ' . $e->getMessage();
                    $message_type = 'error';
                    logAudit($_SESSION['user_id'], 'reject_registration', 
                             'Rejected request ID: ' . $request_id . ' - Database error');
                    break;
                }
                
                $user_id = $conn->lastInsertId();
                
                // Create role-specific record
                if ($request['role'] === 'intern') {
                    try {
                        $stmt = $conn->prepare("INSERT INTO interns (user_id, school, field_of_study, theme, start_date, end_date) 
                                                VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $user_id, 
                            $request['school'] ?? null, 
                            $request['field_of_study'] ?? null,
                            $request['theme'] ?? null,
                            $request['start_date'] ?? date('Y-m-d'),
                            $request['end_date'] ?? date('Y-m-d', strtotime('+3 months'))
                        ]);
                    } catch (PDOException $e) {
                        if (strpos($e->getMessage(), 'theme') !== false) {
                            $stmt = $conn->prepare("INSERT INTO interns (user_id, school, field_of_study, start_date, end_date) 
                                                    VALUES (?, ?, ?, ?, ?)");
                            $stmt->execute([
                                $user_id, 
                                $request['school'] ?? null, 
                                $request['field_of_study'] ?? null,
                                $request['start_date'] ?? date('Y-m-d'),
                                $request['end_date'] ?? date('Y-m-d', strtotime('+3 months'))
                            ]);
                        } else {
                            throw $e;
                        }
                    }
                } elseif ($request['role'] === 'supervisor') {
                    $stmt = $conn->prepare("INSERT INTO supervisors (user_id, department, position) VALUES (?, ?, ?)");
                    $stmt->execute([
                        $user_id, 
                        $request['department'] ?? null, 
                        $request['position'] ?? null
                    ]);
                }
                
                // Update request status
                $stmt = $conn->prepare("UPDATE registration_requests SET status = 'approved', 
                                       reviewed_at = NOW(), reviewed_by = ?, admin_comment = ? WHERE id = ?");
                $stmt->execute([$_SESSION['user_id'], $admin_comment, $request_id]);
                
                // ================================================
                // SEND EMAIL TO THE USER
                // ================================================
                $userName = $request['first_name'] . ' ' . $request['last_name'];
                $email = $request['email'];
                $role = $request['role'];
                
                // Get the subject
                $subject = "Welcome to " . SITE_NAME . " - Your Registration Has Been Approved";
                
                // Generate email content
                $htmlBody = getRegistrationApprovalEmail($userName, $email, null, $role);
                
                // Send the email
                $emailResult = sendEmail($email, $subject, $htmlBody);
                
                // Log the email sending
                logAudit($_SESSION['user_id'], 'send_approval_email', 
                         'Sent approval email to ' . $email . ' - Result: ' . ($emailResult['success'] ? 'Success' : 'Failed'));
                
                // Create notification for the user
                $notification = "Your registration has been approved! Welcome to " . SITE_NAME . ". Check your email for details.";
                createNotification($user_id, 'registration_approved', $notification);
                
                $message = t('request_approved') . ($emailResult['success'] ? ' - Email sent to user.' : ' - Email sending failed.');
                $message_type = 'success';
                logAudit($_SESSION['user_id'], 'approve_registration', 
                         'Approved request ID: ' . $request_id . ' - Comment: ' . $admin_comment);
            } else {
                $message = 'Request not found or already processed.';
                $message_type = 'error';
            }
            break;
            
        case 'reject':
            $stmt = $conn->prepare("SELECT * FROM registration_requests WHERE id = ? AND status = 'pending'");
            $stmt->execute([$request_id]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($request) {
                $stmt = $conn->prepare("UPDATE registration_requests SET status = 'rejected', reviewed_at = NOW(), reviewed_by = ?, admin_comment = ? WHERE id = ?");
                $stmt->execute([$_SESSION['user_id'], $admin_comment, $request_id]);

                $message = t('request_rejected');
                $message_type = 'success';
                logAudit($_SESSION['user_id'], 'reject_registration', 'Rejected request ID: ' . $request_id . ' - Comment: ' . $admin_comment);
            } else {
                $message = 'Request not found or already processed.';
                $message_type = 'error';
            }
            break;

        default:
            $message = 'Invalid request action.';
            $message_type = 'error';
            break;
    }
}

// ... rest of the file (queries, display, etc.) ...
?>