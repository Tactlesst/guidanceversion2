-- Additive history only. The existing PDS and related tables remain the working copy.
CREATE TABLE IF NOT EXISTS pds_versions (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 user_id INT NOT NULL,
 pds_id INT NULL,
 version_no INT UNSIGNED NOT NULL,
 school_year VARCHAR(20) NOT NULL DEFAULT '',
 event_type VARCHAR(30) NOT NULL,
 actor_id INT NULL,
 actor_label VARCHAR(160) NOT NULL,
 captured_at DATETIME NOT NULL,
 snapshot_json LONGTEXT NOT NULL,
 changes_json LONGTEXT NOT NULL,
 previous_hash CHAR(64) NULL,
 content_hash CHAR(64) NOT NULL,
 UNIQUE KEY pds_user_version(user_id,version_no),
 KEY pds_version_year(user_id,school_year,id),
 CONSTRAINT pds_version_snapshot_json CHECK(JSON_VALID(snapshot_json)),
 CONSTRAINT pds_version_changes_json CHECK(JSON_VALID(changes_json))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS pds_annual_snapshots (
 user_id INT NOT NULL,
 school_year VARCHAR(20) NOT NULL DEFAULT '',
 version_id BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 reviewed_at DATETIME NULL,
 closed_at DATETIME NULL,
 PRIMARY KEY(user_id,school_year),
 KEY pds_annual_version(version_id),
 CONSTRAINT pds_annual_version_fk FOREIGN KEY(version_id) REFERENCES pds_versions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
