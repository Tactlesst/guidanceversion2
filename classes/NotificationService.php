<?php
/**
 * NotificationService Class
 * 
 * Handles notification creation and email sending
 * 
 * @version 2.0 - Optimized for guidanceversion2
 */

require_once __DIR__ . '/Notification.php';

class NotificationService {
    private $db;
    private $notification;

    public function __construct($db) {
        $this->db = $db;
        $this->notification = new Notification($db);
    }

    /**
     * Create a notification and optionally send email
     * 
     * @param int $user_id User ID to notify
     * @param string $title Notification title
     * @param string $message Notification message
     * @param string $type Notification type (info, success, warning, error, etc.)
     * @param string|null $related_table Related database table
     * @param int|null $related_id Related record ID
     * @param bool $send_email Whether to send email notification
     * @param string|null $email_subject Email subject (defaults to title)
     * @param string|null $email_body_html Email HTML body (defaults to generated template)
     * @param string|null $dedupe_key Deduplication key to prevent duplicate emails
     * @param string|null $send_after Datetime to send email after (for scheduling)
     * @param string|null $to_email_override Override user's email address
     * @return bool Success status
     */
    public function notify(
        $user_id,
        $title,
        $message,
        $type = 'info',
        $related_table = null,
        $related_id = null,
        $send_email = false,
        $email_subject = null,
        $email_body_html = null,
        $dedupe_key = null,
        $send_after = null,
        $to_email_override = null
    ) {
        // Create notification in database
        $created = $this->notification->createNotification(
            $user_id,
            $title,
            $message,
            $type,
            $related_table,
            $related_id
        );

        // Send email if requested
        if ($send_email) {
            $to_email = $to_email_override ?: $this->getUserEmailById($user_id);
            if ($to_email) {
                $subject = $email_subject ?: $title;
                $body_html = $email_body_html ?: $this->defaultEmailBody($title, $message);

                // Try to send immediately
                $sent = $this->sendEmailNow($to_email, $subject, $body_html);
                
                // If immediate send fails, queue for later
                if (!$sent) {
                    $this->queueEmail(
                        $user_id,
                        $to_email,
                        $subject,
                        $body_html,
                        $dedupe_key,
                        $send_after
                    );
                }
            }
        }

        return $created;
    }

    /**
     * Send email immediately using PHPMailer
     * 
     * @param string $to_email Recipient email address
     * @param string $subject Email subject
     * @param string $body_html Email HTML body
     * @return bool Success status
     */
    private function sendEmailNow($to_email, $subject, $body_html) {
        try {
            $project_root = dirname(__DIR__);

            // Load email configuration
            $email_config_path = $project_root . '/config/email_config.php';
            if (!file_exists($email_config_path)) {
                error_log('NotificationService: email_config.php not found');
                return false;
            }
            
            $email_config = require $email_config_path;

            // Check if PHPMailer exists
            $phpmailer_path = $project_root . '/PHPMailer/src/PHPMailer.php';
            if (!file_exists($phpmailer_path)) {
                error_log('NotificationService: PHPMailer not found');
                return false;
            }

            require_once $project_root . '/PHPMailer/src/PHPMailer.php';
            require_once $project_root . '/PHPMailer/src/SMTP.php';
            require_once $project_root . '/PHPMailer/src/Exception.php';

            // Clean SMTP password (remove whitespace)
            $smtpPassword = preg_replace('/\s+/', '', (string)($email_config['smtp_password'] ?? ''));
            $smtpDebug = getenv('EMAIL_OUTBOX_SMTP_DEBUG');

            // Configure PHPMailer
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $email_config['smtp_host'] ?? 'smtp.gmail.com';
            $mail->SMTPAuth = (bool)($email_config['smtp_auth'] ?? true);
            $mail->Username = $email_config['smtp_username'] ?? '';
            $mail->Password = $smtpPassword;
            
            $secure = $email_config['smtp_secure'] ?? 'tls';
            $mail->SMTPSecure = ($secure === 'ssl') 
                ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS 
                : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = (int)($email_config['smtp_port'] ?? 587);

            // Enable debug output if requested
            if ($smtpDebug) {
                $mail->SMTPDebug = \PHPMailer\PHPMailer\SMTP::DEBUG_SERVER;
                $mail->Debugoutput = function ($str, $level) use ($to_email) {
                    error_log('PHPMailer SMTP[' . $level . '] to ' . $to_email . ': ' . $str);
                };
            }

            // Set sender and recipient
            $from_email = $email_config['from_email'] ?? ($email_config['smtp_username'] ?? '');
            $from_name = $email_config['from_name'] ?? 'Guidance System';

            $mail->setFrom($from_email, $from_name);
            $mail->addAddress($to_email);
            $mail->isHTML((bool)($email_config['is_html'] ?? true));
            $mail->CharSet = $email_config['charset'] ?? 'UTF-8';
            $mail->Subject = (string)$subject;
            $mail->Body = (string)$body_html;

            // Send email
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log('NotificationService sendEmailNow error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get user email by user ID
     * 
     * @param int $user_id User ID
     * @return string|null Email address or null if not found
     */
    private function getUserEmailById($user_id) {
        try {
            $stmt = $this->db->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$user_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row['email'] ?? null;
        } catch (Exception $e) {
            error_log("NotificationService getUserEmailById error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Queue email for later sending
     * 
     * @param int $user_id User ID
     * @param string $to_email Recipient email
     * @param string $subject Email subject
     * @param string $body_html Email HTML body
     * @param string|null $dedupe_key Deduplication key
     * @param string|null $send_after Datetime to send after
     * @return bool Success status
     */
    private function queueEmail($user_id, $to_email, $subject, $body_html, $dedupe_key = null, $send_after = null) {
        try {
            $sql = "INSERT INTO email_outbox (user_id, to_email, subject, body_html, status, dedupe_key, send_after)
                    VALUES (:user_id, :to_email, :subject, :body_html, 'pending', :dedupe_key, :send_after)";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':user_id', $user_id);
            $stmt->bindValue(':to_email', $to_email);
            $stmt->bindValue(':subject', $subject);
            $stmt->bindValue(':body_html', $body_html);
            $stmt->bindValue(':dedupe_key', $dedupe_key);
            $stmt->bindValue(':send_after', $send_after);

            return $stmt->execute();
        } catch (Exception $e) {
            $msg = $e->getMessage();

            // If duplicate entry (dedupe_key constraint), consider it success
            if (stripos($msg, 'uq_outbox_dedupe') !== false || stripos($msg, 'Duplicate entry') !== false) {
                return true;
            }

            error_log("NotificationService queueEmail error: " . $msg);
            return false;
        }
    }

    /**
     * Generate default email HTML body
     * 
     * @param string $title Email title
     * @param string $message Email message
     * @return string HTML email body
     */
    public function defaultEmailBody($title, $message) {
        $safeTitle = htmlspecialchars($title ?? '', ENT_QUOTES, 'UTF-8');
        $safeMessage = nl2br(htmlspecialchars($message ?? '', ENT_QUOTES, 'UTF-8'));

        $project_root = dirname(__DIR__);
        $email_config_path = $project_root . '/config/email_config.php';
        
        // Load email config if exists, otherwise use defaults
        if (file_exists($email_config_path)) {
            $email_config = require $email_config_path;
        } else {
            $email_config = [];
        }

        $base_url = rtrim((string)($email_config['base_url'] ?? ''), '/');
        $logo_url = (string)($email_config['logo_url'] ?? '');
        if ($logo_url === '' && $base_url) {
            $logo_url = $base_url . '/assets/images/srcblogo.png';
        }
        $brand_name = (string)($email_config['from_name'] ?? 'SRCB Guidance Management System');

        // Generate logo HTML if logo URL exists
        $logo_html = '';
        if ($logo_url) {
            $logo_html = '<div style="text-align:center; margin: 0 0 12px 0;">'
                . '<img src="' . htmlspecialchars($logo_url, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($brand_name, ENT_QUOTES, 'UTF-8') . '" style="max-width: 72px; height: auto; display:inline-block;" />'
                . '</div>';
        }

        // Generate HTML email template
        return '<!DOCTYPE html>'
            . '<html><head><meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
            . '</head>'
            . '<body style="margin:0; padding:0; background:#f3f4f6; font-family: Arial, sans-serif; line-height: 1.6; color:#111;">'
            . '<div style="max-width: 600px; margin: 0 auto; padding: 20px;">'
            . '<div style="background: linear-gradient(135deg, #163269 0%, #3a56c4 100%); color: #fff; padding: 24px; text-align: center; border-radius: 10px 10px 0 0;">'
            . $logo_html
            . '<h2 style="margin: 0; font-size: 20px; font-weight: 700;">' . $safeTitle . '</h2>'
            . '<div style="margin-top: 6px; font-size: 13px; opacity: 0.95;">' . htmlspecialchars($brand_name, ENT_QUOTES, 'UTF-8') . '</div>'
            . '</div>'
            . '<div style="background:#ffffff; padding: 24px; border-radius: 0 0 10px 10px;">'
            . '<div style="padding: 14px; border: 1px solid #e5e7eb; border-radius: 10px; background: #f9fafb;">'
            . $safeMessage
            . '</div>'
            . '<div style="margin-top: 18px; color: #6b7280; font-size: 12px; text-align: center;">'
            . 'This is an automated message.'
            . '</div>'
            . '</div>'
            . '</div>'
            . '</body></html>';
    }
}
