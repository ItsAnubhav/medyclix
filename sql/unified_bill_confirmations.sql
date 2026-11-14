-- Service Posting confirmation tables (also auto-created by Bill_model::ensureUnifiedBillTables)
CREATE TABLE IF NOT EXISTS `unified_bill_confirmations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `case_reference_id` int(11) NOT NULL,
  `confirmed_by` int(11) DEFAULT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_discount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_net` decimal(12,2) NOT NULL DEFAULT 0.00,
  `remarks` text,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `case_reference_id` (`case_reference_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `unified_bill_confirmation_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `confirmation_id` int(11) NOT NULL,
  `module` varchar(30) NOT NULL,
  `module_id` int(11) NOT NULL,
  `service_code` varchar(50) DEFAULT NULL,
  `service_name` varchar(255) DEFAULT NULL,
  `qty` decimal(12,2) DEFAULT 1.00,
  `rate` decimal(12,2) DEFAULT 0.00,
  `amount` decimal(12,2) DEFAULT 0.00,
  `discount_percent` decimal(12,2) DEFAULT 0.00,
  `discount_amount` decimal(12,2) DEFAULT 0.00,
  `net_amount` decimal(12,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `confirmation_id` (`confirmation_id`),
  KEY `module_module_id` (`module`,`module_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
