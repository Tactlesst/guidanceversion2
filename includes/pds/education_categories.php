<?php
// Keep summary counts and category filtering on the same education classification.
function pdsEducationCategorySQL(): string {
    $grade = "LOWER(TRIM(CONVERT(COALESCE(NULLIF(sp.grade_level, ''), p.grade_level, '') USING utf8mb4))) COLLATE utf8mb4_unicode_ci";
    $level = "LOWER(TRIM(CONVERT(COALESCE(p.education_level, '') USING utf8mb4))) COLLATE utf8mb4_unicode_ci";
    $department = "LOWER(TRIM(CONVERT(COALESCE(sp.department, '') USING utf8mb4))) COLLATE utf8mb4_unicode_ci";
    return "CASE
        WHEN $grade REGEXP 'kindergarten|^kinder|^nursery|^prep' THEN 'kinder'
        WHEN $grade REGEXP '^grade[[:space:]]*[1-6]([[:space:]]|$)' THEN 'elementary'
        WHEN $grade REGEXP '^grade[[:space:]]*(7|8|9|10)([[:space:]]|$)' THEN 'junior_high'
        WHEN $grade REGEXP '^grade[[:space:]]*(11|12)([[:space:]]|$)' THEN 'senior_high'
        WHEN $grade LIKE '%college%' OR $grade REGEXP '^[1-9][0-9]*(st|nd|rd|th)?[[:space:]]+year' THEN 'college'
        WHEN $level IN ('kinder','kindergarten') OR $department LIKE '%kindergarten%' THEN 'kinder'
        WHEN $level IN ('highered','college','higher education','higher_education') OR $department IN ('college','higher education') THEN 'college'
        WHEN $level IN ('seniorhigh','senior_high','senior high','senior high school') OR $department LIKE '%senior high%' THEN 'senior_high'
        WHEN $level IN ('elementary','grade school') OR $department LIKE '%elementary%' THEN 'elementary'
        WHEN $level IN ('highschool','juniorhigh','junior_high','junior high','junior high school') OR $department IN ('high school','junior high school') THEN 'junior_high'
        ELSE NULL END";
}
