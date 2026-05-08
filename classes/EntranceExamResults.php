<?php
/**
 * EntranceExamResults Class
 * 
 * Handles entrance exam results (OLSAT scores, qualifications, etc.)
 * 
 * @version 2.0 - Optimized for guidanceversion2
 */

class EntranceExamResults {
    private $conn;
    private $table_name = "entrance_exam_appointments";

    // Properties
    public $appointment_id;
    public $exam_date;
    public $olsat_level;
    public $olsat_form;
    public $verbal_abilities_score;
    public $verbal_comprehension_score;
    public $verbal_reasoning_score;
    public $nonverbal_abilities_score;
    public $figural_reasoning_score;
    public $quantitative_reasoning_score;
    public $total_score;
    public $total_items;
    public $stanine_score;
    public $interpretation;
    public $verbal_stanine;
    public $verbal_interpretation;
    public $nonverbal_stanine;
    public $nonverbal_interpretation;
    public $qualified_grade;
    public $academic_strand;
    public $qualified_program;
    public $school_year;
    public $testing_in_charge;
    public $assisted_by;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Save exam results to database
     * 
     * @return bool Success status
     */
    public function saveResults() {
        $query = "UPDATE " . $this->table_name . " 
                  SET exam_date = :exam_date,
                      olsat_level = :olsat_level,
                      olsat_form = :olsat_form,
                      verbal_abilities_score = :verbal_abilities_score,
                      verbal_comprehension_score = :verbal_comprehension_score,
                      verbal_reasoning_score = :verbal_reasoning_score,
                      nonverbal_abilities_score = :nonverbal_abilities_score,
                      figural_reasoning_score = :figural_reasoning_score,
                      quantitative_reasoning_score = :quantitative_reasoning_score,
                      total_score = :total_score,
                      total_items = :total_items,
                      stanine_score = :stanine_score,
                      interpretation = :interpretation,
                      verbal_stanine = :verbal_stanine,
                      verbal_interpretation = :verbal_interpretation,
                      nonverbal_stanine = :nonverbal_stanine,
                      nonverbal_interpretation = :nonverbal_interpretation,
                      qualified_grade = :qualified_grade,
                      academic_strand = :academic_strand,
                      qualified_program = :qualified_program,
                      school_year = :school_year,
                      testing_in_charge = :testing_in_charge,
                      assisted_by = :assisted_by,
                      status = 'completed',
                      exam_result = :exam_result
                  WHERE id = :appointment_id";

        $stmt = $this->conn->prepare($query);

        // Sanitize inputs
        $this->sanitizeInputs();

        // Determine exam result based on qualification
        $exam_result = !empty($this->qualified_grade) ? 'Qualified' : 'Not Qualified';

        // Bind parameters
        $stmt->bindParam(":appointment_id", $this->appointment_id);
        $stmt->bindParam(":exam_date", $this->exam_date);
        $stmt->bindParam(":olsat_level", $this->olsat_level);
        $stmt->bindParam(":olsat_form", $this->olsat_form);
        $stmt->bindParam(":verbal_abilities_score", $this->verbal_abilities_score);
        $stmt->bindParam(":verbal_comprehension_score", $this->verbal_comprehension_score);
        $stmt->bindParam(":verbal_reasoning_score", $this->verbal_reasoning_score);
        $stmt->bindParam(":nonverbal_abilities_score", $this->nonverbal_abilities_score);
        $stmt->bindParam(":figural_reasoning_score", $this->figural_reasoning_score);
        $stmt->bindParam(":quantitative_reasoning_score", $this->quantitative_reasoning_score);
        $stmt->bindParam(":total_score", $this->total_score);
        $stmt->bindParam(":total_items", $this->total_items);
        $stmt->bindParam(":stanine_score", $this->stanine_score);
        $stmt->bindParam(":interpretation", $this->interpretation);
        $stmt->bindParam(":verbal_stanine", $this->verbal_stanine);
        $stmt->bindParam(":verbal_interpretation", $this->verbal_interpretation);
        $stmt->bindParam(":nonverbal_stanine", $this->nonverbal_stanine);
        $stmt->bindParam(":nonverbal_interpretation", $this->nonverbal_interpretation);
        $stmt->bindParam(":qualified_grade", $this->qualified_grade);
        $stmt->bindParam(":academic_strand", $this->academic_strand);
        $stmt->bindParam(":qualified_program", $this->qualified_program);
        $stmt->bindParam(":school_year", $this->school_year);
        $stmt->bindParam(":testing_in_charge", $this->testing_in_charge);
        $stmt->bindParam(":assisted_by", $this->assisted_by);
        $stmt->bindParam(":exam_result", $exam_result);

        return $stmt->execute();
    }

    /**
     * Get results by appointment ID
     * 
     * @param int $appointment_id Appointment ID
     * @return array|false Results or false if not found
     */
    public function getResultsByAppointmentId($appointment_id) {
        $query = "SELECT e.*, u.first_name, u.last_name, u.email,
                         a.first_name as assisted_by_name, a.last_name as assisted_by_lastname
                  FROM " . $this->table_name . " e
                  JOIN users u ON e.user_id = u.id
                  LEFT JOIN users a ON e.assisted_by = a.id
                  WHERE e.id = ? AND e.status = 'completed'";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $appointment_id);
        $stmt->execute();

        if($stmt->rowCount() > 0) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
        return false;
    }

    /**
     * Get results by user ID
     * 
     * @param int $user_id User ID
     * @return PDOStatement Results statement
     */
    public function getResultsByUserId($user_id) {
        $query = "SELECT e.*, u.first_name, u.last_name, u.email,
                         a.first_name as assisted_by_name, a.last_name as assisted_by_lastname
                  FROM " . $this->table_name . " e
                  JOIN users u ON e.user_id = u.id
                  LEFT JOIN users a ON e.assisted_by = a.id
                  WHERE e.user_id = ? AND e.status = 'completed'
                  ORDER BY e.exam_date DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $user_id);
        $stmt->execute();
        return $stmt;
    }

    /**
     * Get all results
     * 
     * @return PDOStatement Results statement
     */
    public function getAllResults() {
        $query = "SELECT e.*, u.first_name, u.last_name, u.email,
                         a.first_name as assisted_by_name, a.last_name as assisted_by_lastname
                  FROM " . $this->table_name . " e
                  JOIN users u ON e.user_id = u.id
                  LEFT JOIN users a ON e.assisted_by = a.id
                  WHERE e.status = 'completed'
                  ORDER BY e.exam_date DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    /**
     * Calculate percentage score
     * 
     * @param int $score Score achieved
     * @param int $total Total possible score
     * @return float Percentage
     */
    public function calculatePercentage($score, $total = 72) {
        if($total == 0) return 0;
        return round(($score / $total) * 100, 2);
    }

    /**
     * Get stanine interpretation
     * 
     * @param int $stanine Stanine score (1-9)
     * @return string Interpretation
     */
    public function getStanineInterpretation($stanine) {
        $interpretations = [
            1 => "Very Low",
            2 => "Low",
            3 => "Below Average",
            4 => "Below Average",
            5 => "Average",
            6 => "Above Average",
            7 => "Above Average",
            8 => "High",
            9 => "Very High"
        ];
        
        return isset($interpretations[$stanine]) ? $interpretations[$stanine] : "Not Available";
    }

    /**
     * Sanitize input properties
     */
    private function sanitizeInputs() {
        $this->olsat_level = htmlspecialchars(strip_tags($this->olsat_level));
        $this->olsat_form = htmlspecialchars(strip_tags($this->olsat_form));
        $this->interpretation = htmlspecialchars(strip_tags($this->interpretation));
        $this->verbal_interpretation = htmlspecialchars(strip_tags($this->verbal_interpretation));
        $this->nonverbal_interpretation = htmlspecialchars(strip_tags($this->nonverbal_interpretation));
        $this->qualified_grade = htmlspecialchars(strip_tags($this->qualified_grade));
        $this->academic_strand = htmlspecialchars(strip_tags($this->academic_strand));
        $this->qualified_program = htmlspecialchars(strip_tags($this->qualified_program));
        $this->school_year = htmlspecialchars(strip_tags($this->school_year));
        $this->testing_in_charge = htmlspecialchars(strip_tags($this->testing_in_charge));
    }
}
