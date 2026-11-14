-- Credit / Insurance billing + per-line Reporting Doctor (Service Posting)
-- Prefer running via Bill_model::ensureCreditBillingSchema() which checks field_exists first.

ALTER TABLE `transactions`
  ADD COLUMN `credit_settled_at` datetime DEFAULT NULL,
  ADD COLUMN `credit_settled_by` int(11) DEFAULT NULL,
  ADD COLUMN `credit_doctor_id` int(11) DEFAULT NULL;

ALTER TABLE `ipd_details`
  ADD COLUMN `reporting_doctor` int(11) DEFAULT NULL;

CREATE TABLE IF NOT EXISTS `service_posting_line_meta` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `case_reference_id` int(11) NOT NULL,
  `module` varchar(30) NOT NULL,
  `module_id` int(11) NOT NULL,
  `reporting_doctor` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `case_module_line` (`case_reference_id`,`module`,`module_id`),
  KEY `reporting_doctor` (`reporting_doctor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `unified_bill_confirmation_items`
  ADD COLUMN `reporting_doctor` int(11) DEFAULT NULL;
