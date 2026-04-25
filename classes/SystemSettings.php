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

    public function isCounselingEnabled() {
        return $this->getSettingValue('counseling_enabled') == 1;
    }

    public function isPDSEnabled() {
        return $this->getSettingValue('pds_enabled') == 1;
    }

    public function isEntranceExamEnabled() {
        return $this->getSettingValue('entrance_exam_enabled') == 1;
    }
}
