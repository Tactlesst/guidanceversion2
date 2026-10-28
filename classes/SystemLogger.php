<?php
class SystemLogger {
    private $db;

    public function __construct($database) { $this->db = $database; }

    public function log($type, $message, $user_id = null, $additional_data = null) {
        try {
            if($user_id){$actorQuery=$this->db->prepare('SELECT role FROM users WHERE id=?');$actorQuery->execute([$user_id]);$actorRole=$actorQuery->fetchColumn();if($actorRole){$additional_data=is_array($additional_data)?$additional_data:[];$additional_data['actor_role']=$actorRole;}}
            $stmt = $this->db->prepare("INSERT INTO system_logs (user_id, log_type, message, ip_address, user_agent, additional_data) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $type, $message, $this->getClientIP(), $_SERVER['HTTP_USER_AGENT'] ?? null, $additional_data ? json_encode($additional_data) : null]);
            return true;
        } catch (Exception $e) { error_log("SystemLogger Error: " . $e->getMessage()); return false; }
    }

    public function error($message, $user_id = null, $additional_data = null) { return $this->log('error', $message, $user_id, $additional_data); }
    public function warning($message, $user_id = null, $additional_data = null) { return $this->log('warning', $message, $user_id, $additional_data); }
    public function info($message, $user_id = null, $additional_data = null) { return $this->log('info', $message, $user_id, $additional_data); }
    public function success($message, $user_id = null, $additional_data = null) { return $this->log('success', $message, $user_id, $additional_data); }
    public function login($user_id, $additional_data = null) { return $this->log('login', 'User logged in successfully', $user_id, $additional_data); }
    public function logout($user_id, $additional_data = null) { return $this->log('logout', 'User logged out', $user_id, $additional_data); }
    public function adminAction($message, $user_id = null, $additional_data = null) { return $this->log('admin_action', $message, $user_id, $additional_data); }

    public function getRecentLogs($limit = 10, $type = null) {
        try {
            $where = $type ? "WHERE log_type = ?" : "";
            $sql = "SELECT sl.*, u.first_name, u.last_name FROM system_logs sl LEFT JOIN users u ON sl.user_id = u.id $where ORDER BY sl.created_at DESC LIMIT ?";
            $stmt = $this->db->prepare($sql);
            $params = $type ? [$type, $limit] : [$limit];
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) { return []; }
    }

    public function getLogStats($days = 7) {
        try {
            $stmt = $this->db->prepare("SELECT log_type, COUNT(*) as count, DATE(created_at) as log_date FROM system_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) GROUP BY log_type, DATE(created_at) ORDER BY log_date DESC, log_type");
            $stmt->execute([$days]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) { return []; }
    }

    public function cleanOldLogs($days = 30) {
        try {
            $stmt = $this->db->prepare("DELETE FROM system_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
            $stmt->execute([$days]);
            return $stmt->rowCount();
        } catch (Exception $e) { return false; }
    }

    public static function isLoopbackAddress($address) {
        if (!is_string($address) || filter_var(trim($address), FILTER_VALIDATE_IP) === false) return false;
        $packed = inet_pton(trim($address));
        if (strlen($packed) === 4) return ord($packed[0]) === 127;
        if ($packed === inet_pton('::1')) return true;
        // IPv4-mapped IPv6 requests can also originate on this computer.
        return substr($packed, 0, 12) === hex2bin('00000000000000000000ffff') && ord($packed[12]) === 127;
    }

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
