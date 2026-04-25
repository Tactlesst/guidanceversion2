<?php
class SystemLogger {
    private $db;

    public function __construct($database) { $this->db = $database; }

    public function log($type, $message, $user_id = null, $additional_data = null) {
        try {
            $stmt = $this->db->prepare("INSERT INTO system_logs (user_id, log_type, message, ip_address, user_agent, additional_data) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $type, $message, $this->getClientIP(), $_SERVER['HTTP_USER_AGENT'] ?? null, $additional_data ? json_encode($additional_data) : null]);
            return true;
        } catch (Exception $e) { error_log("SystemLogger Error: " . $e->getMessage()); return false; }
    }

    public function login($user_id, $additional_data = null) { return $this->log('login', 'User logged in successfully', $user_id, $additional_data); }
    public function warning($message, $user_id = null, $additional_data = null) { return $this->log('warning', $message, $user_id, $additional_data); }

    private function getClientIP() {
        foreach (['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
            if (array_key_exists($key, $_SERVER)) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) return $ip;
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
?>
