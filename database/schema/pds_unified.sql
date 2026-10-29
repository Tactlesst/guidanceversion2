-- Unified PDS Table (replaces pds_highschool, pds_seniorhigh, pds_college)
-- Run this in phpMyAdmin under the `guidancedb` database

CREATE TABLE `pds` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `education_level` varchar(50) NOT NULL DEFAULT 'highschool' COMMENT 'elementary, highschool, seniorhigh, highered',

  -- School Info
  `school_year` varchar(20) DEFAULT NULL,

  -- Personal Information
  `first_name` varchar(100) DEFAULT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `suffix` varchar(20) DEFAULT NULL,
  `nickname` varchar(50) DEFAULT NULL,
  `gender` varchar(20) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `birth_place` varchar(255) DEFAULT NULL,
  `age` int(3) DEFAULT NULL,
  `civil_status` varchar(50) DEFAULT NULL,
  `religion` varchar(100) DEFAULT NULL,
  `nationality` varchar(100) DEFAULT NULL,
  `citizenship` varchar(100) DEFAULT NULL,
  `citizenship_others` varchar(255) DEFAULT NULL,

  -- Academic Information
  `grade_level` varchar(20) DEFAULT NULL,
  `strand` varchar(50) DEFAULT NULL,
  `course` varchar(100) DEFAULT NULL,
  `year_level` varchar(20) DEFAULT NULL,
  `semester` varchar(20) DEFAULT NULL,
  `student_type` varchar(255) DEFAULT NULL,

  -- Contact Information
  `home_address` text DEFAULT NULL,
  `city_street` varchar(255) DEFAULT NULL,
  `city_purok` varchar(255) DEFAULT NULL,
  `city_barangay` varchar(255) DEFAULT NULL,
  `contact_number` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,

  -- Emergency Contact
  `emergency_contact_name` varchar(255) DEFAULT NULL,
  `emergency_relationship` varchar(100) DEFAULT NULL,
  `emergency_contact_number` varchar(50) DEFAULT NULL,

  -- Father
  `father_surname` varchar(100) DEFAULT NULL,
  `father_given_name` varchar(100) DEFAULT NULL,
  `father_middle_name` varchar(100) DEFAULT NULL,
  `father_contact` varchar(20) DEFAULT NULL,
  `father_occupation` varchar(255) DEFAULT NULL,
  `father_location` varchar(50) DEFAULT NULL,
  `father_type` varchar(50) DEFAULT NULL,
  `father_status` varchar(50) DEFAULT NULL,
  `father_education` varchar(100) DEFAULT NULL,
  `father_postgrad` varchar(100) DEFAULT NULL,
  `father_specialization` varchar(255) DEFAULT NULL,

  -- Mother
  `mother_surname` varchar(100) DEFAULT NULL,
  `mother_given_name` varchar(100) DEFAULT NULL,
  `mother_middle_name` varchar(100) DEFAULT NULL,
  `mother_contact` varchar(20) DEFAULT NULL,
  `mother_occupation` varchar(255) DEFAULT NULL,
  `mother_location` varchar(50) DEFAULT NULL,
  `mother_type` varchar(50) DEFAULT NULL,
  `mother_status` varchar(50) DEFAULT NULL,
  `mother_education` varchar(100) DEFAULT NULL,
  `mother_postgrad` varchar(100) DEFAULT NULL,
  `mother_specialization` varchar(255) DEFAULT NULL,

  -- Guardian
  `guardian_name` varchar(255) DEFAULT NULL,
  `guardian_address` varchar(255) DEFAULT NULL,
  `guardian_contact` varchar(50) DEFAULT NULL,
  `guardian_relationship` varchar(100) DEFAULT NULL,
  `guardian_relation` varchar(100) DEFAULT NULL,
  `guardian_relation_others` varchar(255) DEFAULT NULL,

  -- Family Details
  `parents_marital` varchar(50) DEFAULT NULL,
  `child_residing` varchar(100) DEFAULT NULL,
  `child_residing_others` varchar(255) DEFAULT NULL,
  `birth_order` varchar(20) DEFAULT NULL,
  `birth_order_others` varchar(50) DEFAULT NULL,
  `sibling_type` text DEFAULT NULL,
  `relatives_at_home` text DEFAULT NULL,
  `relatives_others` varchar(255) DEFAULT NULL,
  `total_relatives_at_home` int(3) DEFAULT NULL,
  `family_income` varchar(100) DEFAULT NULL,
  `residence_type` varchar(50) DEFAULT NULL,
  `languages_spoken` varchar(255) DEFAULT NULL,
  `financial_support` text DEFAULT NULL,
  `financial_support_others` varchar(255) DEFAULT NULL,
  `leisure_activities` text DEFAULT NULL,
  `leisure_activities_others` varchar(255) DEFAULT NULL,
  `special_talents` text DEFAULT NULL,

  -- Educational History
  `preschool_school` varchar(255) DEFAULT NULL,
  `preschool_awards` varchar(255) DEFAULT NULL,
  `preschool_year` varchar(50) DEFAULT NULL,
  `gradeschool_school` varchar(255) DEFAULT NULL,
  `gradeschool_awards` varchar(255) DEFAULT NULL,
  `gradeschool_year` varchar(50) DEFAULT NULL,
  `highschool_school` varchar(255) DEFAULT NULL,
  `highschool_awards` varchar(255) DEFAULT NULL,
  `highschool_year` varchar(50) DEFAULT NULL,
  `seniorhigh_school` varchar(255) DEFAULT NULL,
  `seniorhigh_awards` varchar(255) DEFAULT NULL,
  `seniorhigh_year` varchar(50) DEFAULT NULL,

  -- Health & Physical
  `height` varchar(20) DEFAULT NULL,
  `weight` varchar(20) DEFAULT NULL,
  `physical_condition` varchar(50) DEFAULT NULL,
  `health_problem` varchar(10) DEFAULT NULL,
  `health_problem_details` text DEFAULT NULL,
  `last_doctor_visit` date DEFAULT NULL,
  `doctor_visit_reason` varchar(255) DEFAULT NULL,
  `general_condition` varchar(100) DEFAULT NULL,

  -- Sacraments
  `baptism_status` varchar(10) DEFAULT NULL,
  `baptism_date` date DEFAULT NULL,
  `baptism_church` varchar(255) DEFAULT NULL,
  `communion_status` varchar(10) DEFAULT NULL,
  `communion_date` date DEFAULT NULL,
  `communion_church` varchar(255) DEFAULT NULL,
  `confirmation_status` varchar(10) DEFAULT NULL,
  `confirmation_date` date DEFAULT NULL,
  `confirmation_church` varchar(255) DEFAULT NULL,

  -- Signatures & Photo
  `student_signature` varchar(255) DEFAULT NULL,
  `student_date_signed` date DEFAULT NULL,
  `parent_signature` varchar(255) DEFAULT NULL,
  `parent_date_signed` date DEFAULT NULL,
  `student_photo` varchar(255) DEFAULT NULL,

  -- Privacy
  `privacy_agreement` tinyint(1) DEFAULT 0 COMMENT '1=Agreed, 0=Not agreed',
  `privacy_agreement_date` datetime DEFAULT NULL,

  -- Timestamps
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),

  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_pds_user_id` (`user_id`),
  KEY `idx_education_level` (`education_level`),
  KEY `idx_school_year` (`school_year`),
  KEY `idx_grade_level` (`grade_level`),
  KEY `idx_course` (`course`),
  KEY `idx_strand` (`strand`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Keep the auxiliary tables as-is (pds_siblings, pds_organizations, pds_test_results)
-- They already work fine with user_id references.
