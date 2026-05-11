<?php
// Email Helper Functions
// Location: C:\xampp\htdocs\portfolio_watcher\config\email_helper.php

require_once __DIR__ . '/email.php';
$emailConfig = require __DIR__ . '/email.php';

/**
 * Send an email using PHPMailer (if available) or fallback to mail()
 * 
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $htmlBody HTML email body
 * @param string $toName Optional recipient name
 * @return array ['success' => bool, 'message' => string, 'error' => string|null]
 */
function sendEmail($to, $subject, $htmlBody, $toName = '') {
    global $emailConfig;
    
    // Try to use PHPMailer if available
    $phpmailerPath = __DIR__ . '/../vendor/phpmailer/src/PHPMailer.php';
    $composerPath = __DIR__ . '/../vendor/autoload.php';
    
    // Check if PHPMailer is available via Composer
    if (file_exists($composerPath)) {
        require_once $composerPath;
        
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            
            // SMTP Configuration
            $mail->isSMTP();
            $mail->Host = $emailConfig['smtp_host'];
            $mail->SMTPAuth = $emailConfig['smtp_auth'];
            $mail->Username = $emailConfig['smtp_username'];
            $mail->Password = $emailConfig['smtp_password'];
            $mail->SMTPSecure = $emailConfig['smtp_secure'];
            $mail->Port = $emailConfig['smtp_port'];
            $mail->CharSet = 'UTF-8';
            
            // Enable debug output for troubleshooting (disable in production)
            // Temporarily enable debug to see what's wrong - set to 0 in production
            $mail->SMTPDebug = 0; // 0 = off, 2 = verbose debug
            $mail->Debugoutput = function($str, $level) {
                error_log("PHPMailer Debug: $str");
            };
            
            // Validate credentials
            if (empty($emailConfig['smtp_username']) || empty($emailConfig['smtp_password'])) {
                return [
                    'success' => false,
                    'message' => 'Email not configured. Please fill in smtp_username and smtp_password in config/email.php',
                    'error' => 'Email credentials are missing'
                ];
            }
            
            // Sender
            $mail->setFrom($emailConfig['from_email'], $emailConfig['from_name']);
            
            // Recipient
            if ($toName) {
                $mail->addAddress($to, $toName);
            } else {
                $mail->addAddress($to);
            }
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = strip_tags($htmlBody); // Plain text version
            
            $mail->send();
            
            return ['success' => true, 'message' => 'Email sent successfully'];
            
        } catch (Exception $e) {
            $errorInfo = isset($mail) ? $mail->ErrorInfo : $e->getMessage();
            error_log("PHPMailer error (Composer): " . $errorInfo);
            return [
                'success' => false,
                'message' => 'Failed to send email',
                'error' => $errorInfo
            ];
        }
    }
    // Check if PHPMailer is available via manual installation
    elseif (file_exists($phpmailerPath)) {
        require_once $phpmailerPath;
        require_once __DIR__ . '/../vendor/phpmailer/src/Exception.php';
        require_once __DIR__ . '/../vendor/phpmailer/src/SMTP.php';
        
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            
            // SMTP Configuration
            $mail->isSMTP();
            $mail->Host = $emailConfig['smtp_host'];
            $mail->SMTPAuth = $emailConfig['smtp_auth'];
            $mail->Username = $emailConfig['smtp_username'];
            $mail->Password = $emailConfig['smtp_password'];
            $mail->SMTPSecure = $emailConfig['smtp_secure'];
            $mail->Port = $emailConfig['smtp_port'];
            $mail->CharSet = 'UTF-8';
            
            // Enable debug output for troubleshooting (disable in production)
            // Temporarily enable debug to see what's wrong - set to 0 in production
            $mail->SMTPDebug = 0; // 0 = off, 2 = verbose debug
            $mail->Debugoutput = function($str, $level) {
                error_log("PHPMailer Debug: $str");
            };
            
            // Validate credentials
            if (empty($emailConfig['smtp_username']) || empty($emailConfig['smtp_password'])) {
                return [
                    'success' => false,
                    'message' => 'Email not configured. Please fill in smtp_username and smtp_password in config/email.php',
                    'error' => 'Email credentials are missing'
                ];
            }
            
            // Sender
            $mail->setFrom($emailConfig['from_email'], $emailConfig['from_name']);
            
            // Recipient
            if ($toName) {
                $mail->addAddress($to, $toName);
            } else {
                $mail->addAddress($to);
            }
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = strip_tags($htmlBody);
            
            $mail->send();
            
            return ['success' => true, 'message' => 'Email sent successfully'];
            
        } catch (Exception $e) {
            $errorInfo = isset($mail) ? $mail->ErrorInfo : $e->getMessage();
            error_log("PHPMailer error (Manual): " . $errorInfo);
            return [
                'success' => false,
                'message' => 'Failed to send email',
                'error' => $errorInfo
            ];
        }
    }
    // Fallback to mail() function (usually doesn't work on localhost)
    else {
        // Fallback headers
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: " . $emailConfig['from_name'] . " <" . $emailConfig['from_email'] . ">" . "\r\n";
        
        $result = @mail($to, $subject, $htmlBody, $headers);
        
        if ($result) {
            return ['success' => true, 'message' => 'Email sent (using mail() function)'];
        } else {
            return [
                'success' => false,
                'message' => 'Email could not be sent. PHPMailer is not installed.',
                'error' => 'Please install PHPMailer using: composer require phpmailer/phpmailer'
            ];
        }
    }
}

