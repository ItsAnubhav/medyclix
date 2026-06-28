ALTER TABLE `sch_settings`
    ADD COLUMN `uhid_prefix` CHAR(4) NOT NULL DEFAULT 'AHID' AFTER `dise_code`;

ALTER TABLE `patients`
    ADD COLUMN `patient_unique_id` VARCHAR(12) NULL AFTER `id`,
    ADD UNIQUE INDEX `uq_patients_patient_unique_id` (`patient_unique_id`);

-- Give existing patients a UHID using the configured prefix and their
-- database ID as the collision-free numeric sequence.
UPDATE `patients`
SET `patient_unique_id` = CONCAT(
    (SELECT UPPER(`uhid_prefix`) FROM `sch_settings` ORDER BY `id` LIMIT 1),
    LPAD(`id`, 6, '0')
)
WHERE `patient_unique_id` IS NULL;

U@u615931815
