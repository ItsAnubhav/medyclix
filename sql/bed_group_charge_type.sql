-- Map each IPD bed group to one charge type (charge_type_master).
-- Also created automatically by Bedgroup_model::ensureBedGroupChargeTypeTable().

CREATE TABLE IF NOT EXISTS `bed_group_charge_type` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bed_group_id` int(11) NOT NULL,
  `charge_type_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bed_group_id` (`bed_group_id`),
  KEY `charge_type_id` (`charge_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
