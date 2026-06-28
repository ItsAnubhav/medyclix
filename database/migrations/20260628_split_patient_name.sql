ALTER TABLE `patients`
    ADD COLUMN `first_name` VARCHAR(100) NULL AFTER `patient_name`,
    ADD COLUMN `middle_name` VARCHAR(100) NULL AFTER `first_name`,
    ADD COLUMN `last_name` VARCHAR(100) NULL AFTER `middle_name`,
    ADD INDEX `idx_patient_first_name` (`first_name`),
    ADD INDEX `idx_patient_last_name` (`last_name`);

-- Best-effort backfill: legacy names do not identify middle names separately.
UPDATE `patients`
SET
    `first_name` = NULLIF(SUBSTRING_INDEX(TRIM(`patient_name`), ' ', 1), ''),
    `middle_name` = NULL,
    `last_name` = NULLIF(
        TRIM(SUBSTRING(TRIM(`patient_name`), LENGTH(SUBSTRING_INDEX(TRIM(`patient_name`), ' ', 1)) + 1)),
        ''
    )
WHERE `patient_name` IS NOT NULL
  AND TRIM(`patient_name`) <> '';
