-- Pharmacy walk-in billing: optional customer phone + wider customer name.
-- Also applied automatically by Pharmacy_model::ensureWalkinBillingSchema().

ALTER TABLE `pharmacy_bill_basic`
  MODIFY COLUMN `customer_name` varchar(100) DEFAULT NULL;

ALTER TABLE `pharmacy_bill_basic`
  ADD COLUMN `customer_phone` varchar(20) DEFAULT NULL AFTER `customer_type`;
