<?php
// admin/test_email.php - Use this to test email functionality
// Remove this file after testing!

require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/auth.php';

// Only admin can access this
requireAdmin('../login.php');

$test_results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $test_email = trim($_POST['test_email']) ?: ADMIN_EMAIL;
    
    // Test 1: Check PHP mail function
    $test_results['mail_function'] = function_exists('mail') ? 'Available' : 'NOT AVAILABLE';
    
    // Test 2: Check server configuration
    $test_results['sendmail_path'] = ini_get('sendmail_path') ?: 'Not set';
    $test_results['smtp'] = ini_get('SMTP') ?: 'Not set';
    $test_results['smtp_port'] = ini_get('smtp_port') ?: 'Not set';
    
    // Test 3: Try to send test email
    $subject = 'Email Test - ' . SITE_NAME;
    $message = "This is a test email sent at " . date('Y-m-d H:i:s') . "\n\n";
    $message .= "Server: " . $_SERVER['HTTP_HOST'] . "\n";
    $message .= "IP: " . $_SERVER['REMOTE_ADDR'] . "\n";
    
    $headers = array();
    $headers[] = "From: " . SITE_NAME . " <" . (SITE_EMAIL ?? 'noreply@' . $_SERVER['HTTP_HOST']) . ">";
    $headers[] = "Reply-To: " . (SITE_EMAIL ?? 'noreply@' . $_SERVER['HTTP_HOST']);
    $headers[] = "Return-Path: " . (SITE_EMAIL ?? 'noreply@' . $_SERVER['HTTP_HOST']);
    $headers[] = "X-Mailer: PHP/" . phpversion();
    $headers[] = "MIME-Version: 1.0";
    $headers[] = "Content-type: text/plain; charset=UTF-8";
    
    $headers_string = implode("\r\n", $headers);
    
    $mail_result = mail($test_email, $subject, $message, $headers_string);
    $test_results['mail_sent'] = $mail_result ? 'SUCCESS' : 'FAILED';
    
    if (!$mail_result) {
        $error = error_get_last();
        $test_results['mail_error'] = $error['message'] ?? 'Unknown error';
    }
    
    // Test 4: Check if PHPMailer is available
    $test_results['phpmailer'] = class_exists('PHPMailer\PHPMailer\PHPMailer') ? 'Available' : 'Not available';
    
    // Test 5: Check SMTP settings if defined
    if (defined('SMTP_HOST')) {
        $test_results['smtp_config'] = [
            'Host' => SMTP_HOST,
            'Port' => SMTP_PORT ?? 'Not set',
            'Username' => defined('SMTP_USERNAME') ? 'Set' : 'Not set',
            'Password' => defined('SMTP_PASSWORD') ? 'Set' : 'Not set',
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Email Test - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>body{background:#f8f9fa}</style>
</head>
<body>
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-warning">
                    <h5><i class="fas fa-envelope-open-text"></i> Email System Test</h5>
                    <small class="text-danger">⚠️ Remove this file after testing!</small>
                </div>
                
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label for="test_email" class="form-label">Test Email Address</label>
                            <input type="email" 
                                   id="test_email" 
                                   name="test_email" 
                                   class="form-control" 
                                   value="<?= htmlspecialchars(ADMIN_EMAIL) ?>"
                                   required>
                        </div>
                        <button type="submit" class="btn btn-primary">Run Email Test</button>
                        <a href="superuser.php" class="btn btn-secondary">Back to Superuser</a>
                    </form>
                    
                    <?php if (!empty($test_results)): ?>
                        <hr>
                        <h6>Test Results:</h6>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-muted">System Info</h6>
                                <ul class="list-unstyled">
                                    <li><strong>Mail Function:</strong> 
                                        <span class="<?= $test_results['mail_function'] === 'Available' ? 'text-success' : 'text-danger' ?>">
                                            <?= $test_results['mail_function'] ?>
                                        </span>
                                    </li>
                                    <li><strong>Sendmail Path:</strong> <code><?= htmlspecialchars($test_results['sendmail_path']) ?></code></li>
                                    <li><strong>SMTP Server:</strong> <code><?= htmlspecialchars($test_results['smtp']) ?></code></li>
                                    <li><strong>SMTP Port:</strong> <code><?= htmlspecialchars($test_results['smtp_port']) ?></code></li>
                                    <li><strong>PHPMailer:</strong> 
                                        <span class="<?= $test_results['phpmailer'] === 'Available' ? 'text-success' : 'text-warning' ?>">
                                            <?= $test_results['phpmailer'] ?>
                                        </span>
                                    </li>
                                </ul>
                            </div>
                            
                            <div class="col-md-6">
                                <h6 class="text-muted">Test Results</h6>
                                <ul class="list-unstyled">
                                    <li><strong>Mail Sent:</strong> 
                                        <span class="<?= $test_results['mail_sent'] === 'SUCCESS' ? 'text-success' : 'text-danger' ?>">
                                            <?= $test_results['mail_sent'] ?>
                                        </span>
                                    </li>
                                    <?php if (isset($test_results['mail_error'])): ?>
                                        <li><strong>Error:</strong> <span class="text-danger"><?= htmlspecialchars($test_results['mail_error']) ?></span></li>
                                    <?php endif; ?>
                                </ul>
                                
                                <?php if (isset($test_results['smtp_config'])): ?>
                                    <h6 class="text-muted mt-3">SMTP Config</h6>
                                    <ul class="list-unstyled">
                                        <?php foreach ($test_results['smtp_config'] as $key => $value): ?>
                                            <li><strong><?= $key ?>:</strong> <code><?= htmlspecialchars($value) ?></code></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if ($test_results['mail_sent'] === 'FAILED'): ?>
                            <div class="alert alert-danger mt-3">
                                <h6>Email Failed - Common Solutions:</h6>
                                <ul class="mb-0">
                                    <li>Check if your hosting provider allows PHP mail() function</li>
                                    <li>Verify your server has a mail server (Postfix/Sendmail) configured</li>
                                    <li>Consider using PHPMailer with SMTP instead</li>
                                    <li>Check spam folders - emails might be filtered</li>
                                    <li>Ensure FROM email domain matches your server domain</li>
                                </ul>
                            </div>
                        <?php elseif ($test_results['mail_sent'] === 'SUCCESS'): ?>
                            <div class="alert alert-success mt-3">
                                <strong>Email sent successfully!</strong> Check your inbox (and spam folder).
                                If you received the email, your superuser system should work.
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>