<?php
/**
 * Email Outbox Sender
 * 
 * Processes queued emails from the email_outbox table
 * Sends pending emails using PHPMailer
 * 
 * Usage: Add to Windows Task Scheduler to run every 5-15 minutes
 * Command: php "C:\xampp\htdocs\guidanceversion2\cron\send_email_outbox.php"
 * 
 * @version 2.0 - Optimized for guidanceversion2
 */

// Prevent direct web access - only allow CLI execution
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('This script can only be run from the command line.');
}

$project_root = dirname(__DIR__);

require_once $project_root . '/config/database.php';

// Check if PHPMailer exists before requiring
$phpmailer_path = $project_root . '/PHPMailer/src/PHPMailer.php';
if (!file_exists($phpmailer_path)) {
    error_log('[' . date('Y-m-d H:i:s') . '] PHPMailer not found. Email queue processing skipped.');
    exit(0);
}

require_once $project_root . '/PHPMailer/src/PHPMailer.php';
require_once $project_root . '/PHPMailer/src/SMTP.php';
require_once $project_root . '/PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Set up logging
$log_file = $project_root . '/logs/email_outbox_sender.log';
$logs_dir = dirname($log_file);
if (!file_exists($logs_dir)) {
    mkdir($logs_dir, 0755, true);
}

/**
 * Log message to file and console
 */
function log_outbox($log_file, $message) {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
    file_put_contents($log_file, $line, FILE_APPEND | LOCK_EX);
    echo $line;
}

try {
    // Connect to database
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        throw new Exception('Database connection failed');
    }

    // Load email configuration
    $email_config_path = $project_root . '/config/email_config.php';
    if (!file_exists($email_config_path)) {
        log_outbox($log_file, 'Email config not found. Email queue processing skipped.');
        exit(0);
    }
    
    $email_config = require $email_config_path;

    // Clean SMTP password
    $smtpPassword = preg_replace('/\s+/', '', (string)($email_config['smtp_password'] ?? ''));
    $smtpDebug = getenv('EMAIL_OUTBOX_SMTP_DEBUG');

    // Configuration
    $batchSize = 20;        // Process 20 emails per run
    $maxAttempts = 5;       // Max retry attempts before marking as failed

    // Select pending emails
    $selectSql = "SELECT id, user_id, to_email, subject, body_html, attempts
                  FROM email_outbox
                  WHERE status = 'pending'
                    AND (send_after IS NULL OR send_after <= NOW())
                    AND attempts < :maxAttempts
                  ORDER BY created_at ASC
                  LIMIT :batchSize";

    $selectStmt = $db->prepare($selectSql);
    $selectStmt->bindValue(':maxAttempts', $maxAttempts, PDO::PARAM_INT);
    $selectStmt->bindValue(':batchSize', $batchSize, PDO::PARAM_INT);
    $selectStmt->execute();

    $rows = $selectStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows || count($rows) === 0) {
        log_outbox($log_file, 'No pending emails.');
        exit(0);
    }

    log_outbox($log_file, 'Processing ' . count($rows) . ' queued email(s)...');

    // Prepare update statements
    $markSentSql = "UPDATE email_outbox
                    SET status = 'sent', sent_at = NOW(), last_error = NULL
                    WHERE id = ?";

    $markFailSql = "UPDATE email_outbox
                    SET status = ?, attempts = attempts + 1, last_error = ?
                    WHERE id = ?";

    $markSentStmt = $db->prepare($markSentSql);
    $markFailStmt = $db->prepare($markFailSql);

    $sentCount = 0;
    $failedCount = 0;

    // Process each email
    foreach ($rows as $row) {
        $id = (int)($row['id'] ?? 0);
        $to = $row['to_email'] ?? null;
        $subject = $row['subject'] ?? '';
        $body = $row['body_html'] ?? '';
        $attempts = (int)($row['attempts'] ?? 0);

        if (!$id || !$to) {
            continue;
        }

        try {
            // Configure PHPMailer
            $mail = new PHPMailer(true);

            $mail->isSMTP();
            $mail->Host = $email_config['smtp_host'] ?? 'smtp.gmail.com';
            $mail->SMTPAuth = (bool)($email_config['smtp_auth'] ?? true);
            $mail->Username = $email_config['smtp_username'] ?? '';
            $mail->Password = $smtpPassword;
            
            $secure = $email_config['smtp_secure'] ?? 'tls';
            $mail->SMTPSecure = ($secure === 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = (int)($email_config['smtp_port'] ?? 587);

            // Enable debug output if requested
            if ($smtpDebug) {
                $mail->SMTPDebug = SMTP::DEBUG_SERVER;
                $mail->Debugoutput = function ($str, $level) use ($log_file, $id) {
                    log_outbox($log_file, 'SMTP[' . $level . '] (ID: ' . $id . '): ' . $str);
                };
            }

            // Set sender and recipient
            $from_email = $email_config['from_email'] ?? ($email_config['smtp_username'] ?? '');
            $from_name = $email_config['from_name'] ?? 'Guidance System';
            
            $mail->setFrom($from_email, $from_name);
            $mail->addAddress($to);

            // Set email content
            $mail->isHTML((bool)($email_config['is_html'] ?? true));
            $mail->CharSet = $email_config['charset'] ?? 'UTF-8';
            $mail->Subject = $subject;
            $mail->Body = $body;

            // Send email
            $mail->send();

            // Mark as sent
            $markSentStmt->execute([$id]);
            log_outbox($log_file, "[✓] Sent (ID: {$id}) to {$to}");
            $sentCount++;

        } catch (Exception $e) {
            // Mark as failed or pending for retry
            $newAttempts = $attempts + 1;
            $status = ($newAttempts >= $maxAttempts) ? 'failed' : 'pending';

            $markFailStmt->execute([$status, $e->getMessage(), $id]);
            log_outbox($log_file, "[✗] Failed (ID: {$id}) to {$to} - Attempt {$newAttempts}/{$maxAttempts} - {$e->getMessage()}");
            $failedCount++;
        }
    }

    // Log summary
    log_outbox($log_file, "Done. Sent: {$sentCount}, Failed: {$failedCount}");
    exit(0);

} catch (Exception $e) {
    log_outbox($log_file, 'ERROR: ' . $e->getMessage());
    exit(1);
}
