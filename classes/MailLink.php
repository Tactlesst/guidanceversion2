<?php
// Email links carry only an allowlisted topic, never a student ID or record data.
final class MailLink {
    public static function topic($value): string {
        return is_string($value) && in_array($value, ['counseling','exam','pds','survey','notifications'], true) ? $value : 'notifications';
    }
    public static function context(?string $table): array {
        if ($table === 'counseling_appointments') return ['counseling', 'View appointment'];
        if ($table === 'entrance_exam_appointments') return ['exam', 'View appointment'];
        if (in_array($table, ['pds_versions','pds','pds_college','pds_highschool','pds_seniorhigh'], true)) return ['pds', 'View personal data sheet'];
        if (in_array($table, ['multiple_intelligence_survey','learning_style_inventory','learning_style_inventory_intermediate'], true)) return ['survey', 'View survey results'];
        return ['notifications', 'View office updates'];
    }
    public static function destination($topic, string $role): string {
        $topic = self::topic($topic);
        $staff = in_array($role, ['admin','guidance_advocate','super_admin'], true);
        $page = 'notifications';
        if ($topic === 'counseling') $page = $staff ? 'manage_counseling' : ($role === 'student' ? 'view_appointments' : 'notifications');
        elseif ($topic === 'exam') $page = $staff ? 'manage_exams' : (in_array($role,['student','examinee'],true) ? 'view_application' : 'notifications');
        elseif ($topic === 'pds') $page = in_array($role, ['admin','super_admin'], true) ? 'admin_view_pds' : (in_array($role, ['student','examinee'], true) ? 'view_pds' : 'notifications');
        elseif ($topic === 'survey') $page = $staff ? 'survey_results' : ($role === 'student' ? 'survey_history' : 'notifications');
        return '../dashboard/layout.php?page=' . $page;
    }
    public static function consume(array &$session, string $role): string {
        if (!isset($session['mail_topic'])) return '../dashboard/layout.php';
        $topic = $session['mail_topic'];
        unset($session['mail_topic']);
        return self::destination($topic, $role);
    }
}
