-- Add missing columns to counseling_appointments table
-- Run this in phpMyAdmin under the `guidancedb` database
-- These columns are required by guidanceversion2 and may have been added by old guidance migrations

-- Add assigned_advocate_id if not exists
SET @dbname = DATABASE();
SET @tablename = 'counseling_appointments';

-- assigned_advocate_id
SET @colname = 'assigned_advocate_id';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @colname) > 0,
  'SELECT 1',
  'ALTER TABLE counseling_appointments ADD COLUMN assigned_advocate_id INT(11) DEFAULT NULL COMMENT "Guidance counselor/advocate assigned to handle the session"'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- nature_of_contact
SET @colname = 'nature_of_contact';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @colname) > 0,
  'SELECT 1',
  'ALTER TABLE counseling_appointments ADD COLUMN nature_of_contact ENUM(''walk-in'', ''voluntary'', ''referral_interview'', ''consultation'', ''follow-up'', ''guidance_personnel_initiated'') DEFAULT ''walk-in'''
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- session_duration
SET @colname = 'session_duration';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @colname) > 0,
  'SELECT 1',
  'ALTER TABLE counseling_appointments ADD COLUMN session_duration INT DEFAULT 60 COMMENT "Duration in minutes"'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- is_follow_up
SET @colname = 'is_follow_up';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @colname) > 0,
  'SELECT 1',
  'ALTER TABLE counseling_appointments ADD COLUMN is_follow_up TINYINT(1) DEFAULT 0 COMMENT "Flag to identify follow-up appointments"'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- parent_appointment_id
SET @colname = 'parent_appointment_id';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @colname) > 0,
  'SELECT 1',
  'ALTER TABLE counseling_appointments ADD COLUMN parent_appointment_id INT(11) DEFAULT NULL COMMENT "Links follow-up to original appointment"'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- booking_type
SET @colname = 'booking_type';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @colname) > 0,
  'SELECT 1',
  'ALTER TABLE counseling_appointments ADD COLUMN booking_type ENUM(''regular'', ''follow_up'', ''walk_in'') DEFAULT ''regular'' COMMENT "Type of booking"'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- original_appointment_id
SET @colname = 'original_appointment_id';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @colname) > 0,
  'SELECT 1',
  'ALTER TABLE counseling_appointments ADD COLUMN original_appointment_id INT(11) DEFAULT NULL COMMENT "Original appointment for rescheduled appointments"'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- follow_up_needed
SET @colname = 'follow_up_needed';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @colname) > 0,
  'SELECT 1',
  'ALTER TABLE counseling_appointments ADD COLUMN follow_up_needed TINYINT(1) DEFAULT 0'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- follow_up_date
SET @colname = 'follow_up_date';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @colname) > 0,
  'SELECT 1',
  'ALTER TABLE counseling_appointments ADD COLUMN follow_up_date DATE DEFAULT NULL'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add missed status to ENUM if not already there
ALTER TABLE counseling_appointments MODIFY COLUMN status ENUM('pending', 'confirmed', 'in_progress', 'completed', 'cancelled', 'rescheduled', 'missed') DEFAULT 'pending';
