<?php

class SystemSettings {
    private $conn;
    private $table_name = "system_settings";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getSettingValue($key) {
        $query = "SELECT setting_value FROM " . $this->table_name . " WHERE setting_key = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $key);
        $stmt->execute();

        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row['setting_value'];
        }
        return false;
    }

    public function setSettingValue($key, $value) {
        $query = "INSERT INTO " . $this->table_name . " (setting_key, setting_value, updated_at) 
                  VALUES (?, ?, NOW()) 
                  ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$key, $value, $value]);
    }

    public function getAllSettings() {
        $query = "SELECT * FROM " . $this->table_name . " ORDER BY setting_key ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    public function isCounselingEnabled() {
        return $this->getSettingValue('counseling_enabled') == 1;
    }

    public function isPDSEnabled() {
        return $this->getSettingValue('pds_enabled') == 1;
    }

    public function isEntranceExamEnabled() {
        return $this->getSettingValue('entrance_exam_enabled') == 1;
    }

    public function getDailyAppointmentLimit() {
        $val = $this->getSettingValue('daily_appointment_limit');
        return $val ? (int)$val : 4;
    }

    public function getHomepageAnnouncement() {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM announcements WHERE is_active = 1 AND location = '__HOMEPAGE__' ORDER BY updated_at DESC LIMIT 1");
            $stmt->execute();
            return $stmt->rowCount() > 0 ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
        } catch (Exception $e) { return null; }
    }
}
