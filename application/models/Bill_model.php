<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Bill_model extends MY_Model
{

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Ensure credit settlement columns and reporting_doctor exist.
     */
    public function ensureCreditBillingSchema()
    {
        if (!$this->db->field_exists('credit_settled_at', 'transactions')) {
            $this->db->query("ALTER TABLE `transactions` ADD COLUMN `credit_settled_at` datetime DEFAULT NULL");
        }
        if (!$this->db->field_exists('credit_settled_by', 'transactions')) {
            $this->db->query("ALTER TABLE `transactions` ADD COLUMN `credit_settled_by` int(11) DEFAULT NULL");
        }
        if (!$this->db->field_exists('credit_doctor_id', 'transactions')) {
            $this->db->query("ALTER TABLE `transactions` ADD COLUMN `credit_doctor_id` int(11) DEFAULT NULL");
        }
        if (!$this->db->field_exists('reporting_doctor', 'ipd_details')) {
            $this->db->query("ALTER TABLE `ipd_details` ADD COLUMN `reporting_doctor` int(11) DEFAULT NULL");
        }

        $this->db->query("CREATE TABLE IF NOT EXISTS `service_posting_line_meta` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `case_reference_id` int(11) NOT NULL,
            `module` varchar(30) NOT NULL,
            `module_id` int(11) NOT NULL,
            `reporting_doctor` int(11) DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `case_module_line` (`case_reference_id`,`module`,`module_id`),
            KEY `reporting_doctor` (`reporting_doctor`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

        $this->ensureUnifiedBillTables();
        if ($this->db->table_exists('unified_bill_confirmation_items')
            && !$this->db->field_exists('reporting_doctor', 'unified_bill_confirmation_items')) {
            $this->db->query("ALTER TABLE `unified_bill_confirmation_items` ADD COLUMN `reporting_doctor` int(11) DEFAULT NULL");
        }

        $this->ensureServicePostingPermissions();
    }

    /**
     * Ensure OPD/IPD Service Posting permission categories exist under Billing group.
     * No role grants are inserted — Super Admin bypasses RBAC; others must be assigned in Roles.
     */
    public function ensureServicePostingPermissions()
    {
        if (!$this->db->table_exists('permission_group') || !$this->db->table_exists('permission_category')) {
            return;
        }

        $group = $this->db->select('id')->where('short_code', 'bill')->get('permission_group')->row_array();
        if (empty($group['id'])) {
            $group = $this->db->select('id')->where('name', 'Billing')->get('permission_group')->row_array();
        }
        if (empty($group['id'])) {
            return;
        }
        $group_id = (int) $group['id'];

        $permissions = array(
            array('name' => 'OPD Service Posting', 'short_code' => 'opd_service_posting', 'enable_view' => 1, 'enable_add' => 0, 'enable_edit' => 0, 'enable_delete' => 0),
            array('name' => 'OPD Service Posting Edit', 'short_code' => 'opd_service_posting_edit', 'enable_view' => 1, 'enable_add' => 0, 'enable_edit' => 0, 'enable_delete' => 0),
            array('name' => 'OPD Service Posting Save', 'short_code' => 'opd_service_posting_save', 'enable_view' => 1, 'enable_add' => 0, 'enable_edit' => 0, 'enable_delete' => 0),
            array('name' => 'OPD Service Posting Confirm', 'short_code' => 'opd_service_posting_confirm', 'enable_view' => 1, 'enable_add' => 0, 'enable_edit' => 0, 'enable_delete' => 0),
            array('name' => 'IPD Service Posting', 'short_code' => 'ipd_service_posting', 'enable_view' => 1, 'enable_add' => 0, 'enable_edit' => 0, 'enable_delete' => 0),
            array('name' => 'IPD Service Posting Edit', 'short_code' => 'ipd_service_posting_edit', 'enable_view' => 1, 'enable_add' => 0, 'enable_edit' => 0, 'enable_delete' => 0),
            array('name' => 'IPD Service Posting Save', 'short_code' => 'ipd_service_posting_save', 'enable_view' => 1, 'enable_add' => 0, 'enable_edit' => 0, 'enable_delete' => 0),
            array('name' => 'IPD Service Posting Confirm', 'short_code' => 'ipd_service_posting_confirm', 'enable_view' => 1, 'enable_add' => 0, 'enable_edit' => 0, 'enable_delete' => 0),
        );

        foreach ($permissions as $perm) {
            $exists = $this->db->select('id')
                ->where('short_code', $perm['short_code'])
                ->get('permission_category')
                ->row_array();
            if (!empty($exists['id'])) {
                continue;
            }
            $this->db->insert('permission_category', array(
                'perm_group_id'  => $group_id,
                'name'           => $perm['name'],
                'short_code'     => $perm['short_code'],
                'enable_view'    => $perm['enable_view'],
                'enable_add'     => $perm['enable_add'],
                'enable_edit'    => $perm['enable_edit'],
                'enable_delete'  => $perm['enable_delete'],
            ));
        }
    }

    /**
     * Upsert reporting doctor for one Service Posting line.
     */
    public function setLineReportingDoctor($case_id, $module, $module_id, $reporting_doctor)
    {
        $this->ensureCreditBillingSchema();
        $case_id = (int) $case_id;
        $module_id = (int) $module_id;
        $module = trim((string) $module);
        $reporting_doctor = ($reporting_doctor === '' || $reporting_doctor === null) ? null : (int) $reporting_doctor;
        if ($case_id < 1 || $module_id < 1 || $module === '') {
            return false;
        }

        $existing = $this->db->where(array(
            'case_reference_id' => $case_id,
            'module'            => $module,
            'module_id'         => $module_id,
        ))->get('service_posting_line_meta')->row_array();

        if (!empty($existing['id'])) {
            $this->db->where('id', (int) $existing['id'])->update('service_posting_line_meta', array(
                'reporting_doctor' => $reporting_doctor,
            ));
        } else {
            $this->db->insert('service_posting_line_meta', array(
                'case_reference_id' => $case_id,
                'module'            => $module,
                'module_id'         => $module_id,
                'reporting_doctor'  => $reporting_doctor,
            ));
        }
        return true;
    }

    public function deleteLineReportingDoctor($case_id, $module, $module_id)
    {
        $this->ensureCreditBillingSchema();
        $this->db->where(array(
            'case_reference_id' => (int) $case_id,
            'module'            => trim((string) $module),
            'module_id'         => (int) $module_id,
        ))->delete('service_posting_line_meta');
    }

    public function getLineReportingDoctorMap($case_id)
    {
        $this->ensureCreditBillingSchema();
        $rows = $this->db->where('case_reference_id', (int) $case_id)
            ->get('service_posting_line_meta')
            ->result_array();
        $map = array();
        foreach ($rows as $row) {
            $map[$row['module'] . '-' . $row['module_id']] = $row['reporting_doctor'];
        }
        return $map;
    }

    public function add($bill_data,$module_data,$opd_ipd_transaction,$discount_percentage)
    {
        $this->db->trans_start(); # Starting Transaction
        $this->db->trans_strict(false); # See Note 01. If you wish can remove as well
        //=======================Code Start===========================
        $this->db->insert("bill", $bill_data);

         $bill_id = $this->db->insert_id();

         if($opd_ipd_transaction['amount'] > 0){
         $opd_ipd_transaction['bill_id']=$bill_id;
         $this->db->insert("transactions", $opd_ipd_transaction);
         }
        $this->db->where("id", $bill_data["case_id"])->update("case_references", array('bill_id'=>$bill_id,'discount_percentage'=>$discount_percentage));

        if (!empty($module_data)) {
            # code...
                 foreach ($module_data as $m_key => $m_value) {
                    $module_data[$m_key]['bill_id']=$bill_id;
                 }
               
                $this->db->insert_batch('transactions', $module_data); 
        } 

        //======================Code End==============================

        $this->db->trans_complete(); # Completing transaction
        /* Optional */

        if ($this->db->trans_status() === false) {
            # Something went wrong.
            $this->db->trans_rollback();
            return false;
        } else {
            return true;
        }
    }

    /**
     * Patient dashboard — returns module-wise pending balance for a given patient.
     *
     * Mirrors the logic of Report_model::getmodulewisebalance_report (used by
     * /admin/report/balanceamountreport) but is patient-scoped and aggregated
     * per module so the dashboard's "Pending Bills" card shows ONE row per
     * module with the total outstanding balance across all bills in that
     * module.
     *
     * Raw SQL is unavoidable here: a UNION ALL across 7 module billing tables
     * with subquery aggregates, an outer GROUP BY and HAVING is not cleanly
     * expressible in CI Query Builder. The inner UNION block is ported from
     * Report_model line 519+ with these deliberate differences:
     *   - WHERE patients.id = ? added to every subquery (patient scope)
     *   - blood_bank uses ONLY the 'blood_bank_all_recored' variant — the admin
     *     report has 3 variants of the same blood_issue records (component
     *     breakdown) which would triple-count on a per-patient summary
     *   - outer SELECT wraps with GROUP BY type, SUM, HAVING balance > 0
     *
     * Balance formula matches the admin report exactly: net_amount - paid_amount.
     * Refunds are not subtracted (consistent with how the admin report computes
     * its balance column).
     *
     * Returns array of rows: [type, balance_amount, bill_count, latest_date]
     * sorted by balance_amount DESC. Empty array if no module has balance > 0.
     */
    public function getPatientModuleBalance($patient_id)
    {
        $patient_id = (int) $patient_id;
        if ($patient_id <= 0) {
            return array();
        }

        $sql = "
            SELECT type,
                   SUM(net_amount - paid_amount) AS balance_amount,
                   MAX(date_) AS latest_date,
                   COUNT(*) AS bill_count
            FROM (

                SELECT 'radiology_test' AS type,
                       patients.id AS patient_id,
                       radiology_billing.date AS date_,
                       IFNULL(radiology_billing.net_amount, 0) AS net_amount,
                       (SELECT IFNULL(SUM(transactions.amount),0)
                        FROM transactions
                        WHERE transactions.radiology_billing_id = radiology_billing.id) AS paid_amount
                FROM radiology_billing
                INNER JOIN patients ON patients.id = radiology_billing.patient_id
                WHERE patients.id = ?

                UNION ALL

                SELECT 'pathology_test' AS type,
                       patients.id AS patient_id,
                       pathology_billing.date AS date_,
                       IFNULL(pathology_billing.net_amount, 0) AS net_amount,
                       (SELECT IFNULL(SUM(transactions.amount),0)
                        FROM transactions
                        WHERE transactions.pathology_billing_id = pathology_billing.id) AS paid_amount
                FROM pathology_billing
                INNER JOIN patients ON patients.id = pathology_billing.patient_id
                WHERE patients.id = ?

                UNION ALL

                SELECT 'blood_bank' AS type,
                       patients.id AS patient_id,
                       NULL AS date_,
                       IFNULL(blood_issue.net_amount, 0) AS net_amount,
                       (SELECT IFNULL(SUM(transactions.amount),0)
                        FROM transactions
                        WHERE transactions.blood_issue_id = blood_issue.id) AS paid_amount
                FROM blood_issue
                INNER JOIN patients ON patients.id = blood_issue.patient_id
                WHERE patients.id = ?

                UNION ALL

                SELECT 'pharmacy_bill' AS type,
                       patients.id AS patient_id,
                       pharmacy_bill_basic.date AS date_,
                       IFNULL(pharmacy_bill_basic.net_amount, 0) AS net_amount,
                       (SELECT IFNULL(SUM(transactions.amount),0)
                        FROM transactions
                        WHERE transactions.pharmacy_bill_basic_id = pharmacy_bill_basic.id
                          AND transactions.type = 'payment') AS paid_amount
                FROM pharmacy_bill_basic
                INNER JOIN patients ON patients.id = pharmacy_bill_basic.patient_id
                WHERE patients.id = ?

                UNION ALL

                SELECT 'ambulance_call' AS type,
                       patients.id AS patient_id,
                       NULL AS date_,
                       IFNULL(ambulance_call.net_amount, 0) AS net_amount,
                       (SELECT IFNULL(SUM(transactions.amount),0)
                        FROM transactions
                        WHERE transactions.ambulance_call_id = ambulance_call.id) AS paid_amount
                FROM ambulance_call
                INNER JOIN patients ON patients.id = ambulance_call.patient_id
                WHERE patients.id = ?

                UNION ALL

                SELECT 'opd_patient' AS type,
                       patients.id AS patient_id,
                       NULL AS date_,
                       IFNULL((SELECT SUM(amount) FROM patient_charges
                        WHERE patient_charges.opd_id = opd_details.id), 0) AS net_amount,
                       (SELECT IFNULL(SUM(amount),0) FROM transactions
                        WHERE transactions.opd_id = opd_details.id) AS paid_amount
                FROM opd_details
                INNER JOIN patients ON patients.id = opd_details.patient_id
                WHERE patients.id = ?

                UNION ALL

                SELECT 'ipd_patient' AS type,
                       patients.id AS patient_id,
                       NULL AS date_,
                       IFNULL((SELECT SUM(amount) FROM patient_charges
                        WHERE patient_charges.ipd_id = ipd_details.id), 0) AS net_amount,
                       (SELECT IFNULL(SUM(amount),0) FROM transactions
                        WHERE transactions.ipd_id = ipd_details.id) AS paid_amount
                FROM ipd_details
                INNER JOIN patients ON patients.id = ipd_details.patient_id
                WHERE patients.id = ?

            ) AS module_bills
            GROUP BY type
            HAVING balance_amount > 0
            ORDER BY balance_amount DESC
        ";

        $bindings = array_fill(0, 7, $patient_id);
        return $this->db->query($sql, $bindings)->result_array();
    }

    /**
     * Dashboard widget: hospital-wide outstanding balance.
     * Sums balance (net_amount - paid_amount) across all 7 module-billing
     * sources where balance > 0. Counts bills + overdue (>30 days old).
     *
     * Raw SQL required because the formula UNIONs 7 disparate billing tables
     * with the same balance formula, then filters/aggregates the derived rows.
     * CI Query Builder cannot express this cleanly.
     * Used by admin/admin/dashboard Variant B (2026-05-15).
     */
    public function getOutstandingTotal()
    {
        $sql = "
            SELECT
                IFNULL(SUM(balance_amount), 0) AS total_balance,
                COUNT(*)                       AS bill_count,
                SUM(CASE WHEN latest_date IS NOT NULL
                           AND latest_date < DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                         THEN 1 ELSE 0 END)    AS overdue_count
            FROM (
                SELECT IFNULL(radiology_billing.net_amount, 0)
                       - IFNULL((SELECT SUM(amount) FROM transactions WHERE radiology_billing_id = radiology_billing.id), 0) AS balance_amount,
                       radiology_billing.date AS latest_date
                FROM radiology_billing
                UNION ALL
                SELECT IFNULL(pathology_billing.net_amount, 0)
                       - IFNULL((SELECT SUM(amount) FROM transactions WHERE pathology_billing_id = pathology_billing.id), 0),
                       pathology_billing.date
                FROM pathology_billing
                UNION ALL
                SELECT IFNULL(blood_issue.net_amount, 0)
                       - IFNULL((SELECT SUM(amount) FROM transactions WHERE blood_issue_id = blood_issue.id), 0),
                       NULL
                FROM blood_issue
                UNION ALL
                SELECT IFNULL(pharmacy_bill_basic.net_amount, 0)
                       - IFNULL((SELECT SUM(amount) FROM transactions WHERE pharmacy_bill_basic_id = pharmacy_bill_basic.id AND type = 'payment'), 0),
                       pharmacy_bill_basic.date
                FROM pharmacy_bill_basic
                UNION ALL
                SELECT IFNULL(ambulance_call.net_amount, 0)
                       - IFNULL((SELECT SUM(amount) FROM transactions WHERE ambulance_call_id = ambulance_call.id), 0),
                       NULL
                FROM ambulance_call
                UNION ALL
                SELECT IFNULL((SELECT SUM(amount) FROM patient_charges WHERE opd_id = opd_details.id), 0)
                       - IFNULL((SELECT SUM(amount) FROM transactions WHERE opd_id = opd_details.id), 0),
                       NULL
                FROM opd_details
                UNION ALL
                SELECT IFNULL((SELECT SUM(amount) FROM patient_charges WHERE ipd_id = ipd_details.id), 0)
                       - IFNULL((SELECT SUM(amount) FROM transactions WHERE ipd_id = ipd_details.id), 0),
                       NULL
                FROM ipd_details
            ) AS all_bills
            WHERE balance_amount > 0
        ";
        $row = $this->db->query($sql)->row_array();
        return array(
            'total_balance' => (float)($row['total_balance'] ?? 0),
            'bill_count'    => (int)($row['bill_count'] ?? 0),
            'overdue_count' => (int)($row['overdue_count'] ?? 0),
        );
    }

    /**
     * Ensure Service Posting confirmation tables exist (idempotent).
     */
    public function ensureUnifiedBillTables()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS `unified_bill_confirmations` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

        if ($this->db->table_exists('unified_bill_confirmations')) {
            if (!$this->db->field_exists('overall_discount_percent', 'unified_bill_confirmations')) {
                $this->db->query("ALTER TABLE `unified_bill_confirmations` ADD COLUMN `overall_discount_percent` decimal(12,2) NOT NULL DEFAULT 0.00");
            }
            if (!$this->db->field_exists('overall_discount_amount', 'unified_bill_confirmations')) {
                $this->db->query("ALTER TABLE `unified_bill_confirmations` ADD COLUMN `overall_discount_amount` decimal(12,2) NOT NULL DEFAULT 0.00");
            }
            if (!$this->db->field_exists('overall_discount_mode', 'unified_bill_confirmations')) {
                $this->db->query("ALTER TABLE `unified_bill_confirmations` ADD COLUMN `overall_discount_mode` varchar(10) NOT NULL DEFAULT 'percent'");
            }
        }

        $this->db->query("CREATE TABLE IF NOT EXISTS `unified_bill_confirmation_items` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
    }

    public function getActiveConfirmationByCaseId($case_id)
    {
        $this->ensureUnifiedBillTables();
        return $this->db->where('case_reference_id', (int) $case_id)
            ->where('status', 'confirmed')
            ->order_by('id', 'desc')
            ->limit(1)
            ->get('unified_bill_confirmations')
            ->row_array();
    }

    public function getLatestConfirmationByCaseId($case_id)
    {
        $this->ensureUnifiedBillTables();
        return $this->db->where('case_reference_id', (int) $case_id)
            ->order_by('id', 'desc')
            ->limit(1)
            ->get('unified_bill_confirmations')
            ->row_array();
    }

    public function isCaseBillConfirmed($case_id)
    {
        $row = $this->getActiveConfirmationByCaseId($case_id);
        return !empty($row);
    }

    public function getDraftConfirmationByCaseId($case_id)
    {
        $this->ensureUnifiedBillTables();
        return $this->db->where('case_reference_id', (int) $case_id)
            ->where('status', 'draft')
            ->order_by('id', 'desc')
            ->limit(1)
            ->get('unified_bill_confirmations')
            ->row_array();
    }

    /**
     * Resolve overall bill discount from percent and/or amount against line net.
     */
    public function resolveOverallDiscount($line_net, $percent = 0, $amount = 0, $mode = 'percent')
    {
        $line_net = max(0, (float) $line_net);
        $percent = (float) $percent;
        $amount = (float) $amount;
        $mode = ($mode === 'amount') ? 'amount' : 'percent';

        if ($percent < 0) {
            $percent = 0;
        }
        if ($percent > 100) {
            $percent = 100;
        }
        if ($amount < 0) {
            $amount = 0;
        }

        if ($mode === 'percent') {
            $amount = round(($line_net * $percent) / 100, 2);
        } else {
            if ($amount > $line_net) {
                $amount = $line_net;
            }
            $percent = ($line_net > 0) ? round(($amount / $line_net) * 100, 2) : 0;
        }

        if ($amount > $line_net) {
            $amount = $line_net;
        }

        return array(
            'percent' => $percent,
            'amount'  => $amount,
            'mode'    => $mode,
        );
    }

    public function getOverallDiscountForCase($case_id)
    {
        $row = $this->getActiveConfirmationByCaseId($case_id);
        if (empty($row)) {
            $row = $this->getDraftConfirmationByCaseId($case_id);
        }
        if (empty($row)) {
            $row = $this->getLatestConfirmationByCaseId($case_id);
        }
        if (empty($row)) {
            return array('percent' => 0, 'amount' => 0, 'mode' => 'percent');
        }
        return array(
            'percent' => isset($row['overall_discount_percent']) ? (float) $row['overall_discount_percent'] : 0,
            'amount'  => isset($row['overall_discount_amount']) ? (float) $row['overall_discount_amount'] : 0,
            'mode'    => (!empty($row['overall_discount_mode']) && $row['overall_discount_mode'] === 'amount') ? 'amount' : 'percent',
        );
    }

    /**
     * Line + overall discount + case payments for Service Posting totals bar.
     */
    public function getServicePostingFinancials($case_id, $lines, $overall_percent = null, $overall_amount = null, $overall_mode = null)
    {
        $line_amount = 0;
        $line_discount = 0;
        $line_net = 0;
        if (!empty($lines)) {
            foreach ($lines as $line) {
                $line_amount += (float) $line['amount'];
                $line_discount += (float) $line['discount_amount'];
                $line_net += (float) $line['net_amount'];
            }
        }

        $stored = $this->getOverallDiscountForCase($case_id);
        if ($overall_mode === false || $overall_mode === null || $overall_mode === '') {
            $mode = $stored['mode'];
        } else {
            $mode = $overall_mode;
        }
        if ($overall_percent === false || $overall_percent === null || $overall_percent === '') {
            $percent = $stored['percent'];
        } else {
            $percent = $overall_percent;
        }
        if ($overall_amount === false || $overall_amount === null || $overall_amount === '') {
            $amount = $stored['amount'];
        } else {
            $amount = $overall_amount;
        }
        $overall = $this->resolveOverallDiscount($line_net, $percent, $amount, $mode);

        $payments = array('total_paid' => 0, 'total_advance' => 0, 'total_refund' => 0);
        $CI = &get_instance();
        $CI->load->model('transaction_model');
        $payments = $CI->transaction_model->getCasePaymentSummary($case_id);

        $total_discount = $line_discount + $overall['amount'];
        $total_net = max(0, $line_net - $overall['amount']);
        $paid = (float) $payments['total_paid'];
        $advance = (float) $payments['total_advance'];
        $refund = (float) $payments['total_refund'];

        return array(
            'total_amount'              => $line_amount,
            'line_discount'             => $line_discount,
            'line_net'                  => $line_net,
            'overall_discount_percent'  => $overall['percent'],
            'overall_discount_amount'   => $overall['amount'],
            'overall_discount_mode'     => $overall['mode'],
            'total_discount'            => $total_discount,
            'total_net'                 => $total_net,
            'total_paid'                => $paid,
            'advance_amount'            => $advance,
            'total_refund'              => $refund,
            'balance'                   => max(0, $total_net - $paid - $advance + $refund),
        );
    }

    /**
     * Save / update an editable draft bill for multi-day admissions.
     * Does not lock editing; Confirm is still required before payment.
     */
    public function saveDraftUnifiedBill($case_id, $staff_id, $lines, $remarks = '', $overall_percent = null, $overall_amount = null, $overall_mode = null)
    {
        $this->ensureUnifiedBillTables();
        $case_id = (int) $case_id;
        if ($case_id < 1) {
            return array('status' => false, 'message' => 'Invalid case.');
        }
        if ($this->isCaseBillConfirmed($case_id)) {
            return array('status' => false, 'message' => 'Bill already confirmed. Reopen to save changes.');
        }

        $finance = $this->getServicePostingFinancials($case_id, $lines, $overall_percent, $overall_amount, $overall_mode);
        $total_amount = $finance['total_amount'];
        $total_discount = $finance['total_discount'];
        $total_net = $finance['total_net'];

        $this->db->trans_start();
        $draft = $this->getDraftConfirmationByCaseId($case_id);
        $now = date('Y-m-d H:i:s');
        $header = array(
            'case_reference_id'         => $case_id,
            'confirmed_by'              => (int) $staff_id,
            'confirmed_at'              => $now,
            'status'                    => 'draft',
            'total_amount'              => $total_amount,
            'total_discount'            => $total_discount,
            'total_net'                 => $total_net,
            'overall_discount_percent'  => $finance['overall_discount_percent'],
            'overall_discount_amount'   => $finance['overall_discount_amount'],
            'overall_discount_mode'     => $finance['overall_discount_mode'],
            'remarks'                   => $remarks,
        );

        if (!empty($draft['id'])) {
            $this->db->where('id', (int) $draft['id'])->update('unified_bill_confirmations', $header);
            $confirmation_id = (int) $draft['id'];
            $this->db->where('confirmation_id', $confirmation_id)->delete('unified_bill_confirmation_items');
        } else {
            $header['created_at'] = $now;
            $this->db->insert('unified_bill_confirmations', $header);
            $confirmation_id = $this->db->insert_id();
        }

        $items = array();
        if (!empty($lines)) {
            foreach ($lines as $line) {
                $items[] = array(
                    'confirmation_id'  => $confirmation_id,
                    'module'           => $line['module'],
                    'module_id'        => (int) $line['module_id'],
                    'service_code'     => $line['service_code'],
                    'service_name'     => $line['service_name'],
                    'qty'              => (float) $line['qty'],
                    'rate'             => (float) $line['rate'],
                    'amount'           => (float) $line['amount'],
                    'discount_percent' => (float) $line['discount_percent'],
                    'discount_amount'  => (float) $line['discount_amount'],
                    'net_amount'       => (float) $line['net_amount'],
                    'reporting_doctor' => !empty($line['reporting_doctor']) ? (int) $line['reporting_doctor'] : null,
                );
            }
            if (!empty($items)) {
                $this->db->insert_batch('unified_bill_confirmation_items', $items);
            }
        }

        $this->db->trans_complete();
        if ($this->db->trans_status() === false) {
            return array('status' => false, 'message' => 'Unable to save bill draft.');
        }

        return array(
            'status'          => true,
            'confirmation_id' => $confirmation_id,
            'saved_at'        => $now,
            'total_net'       => $total_net,
            'total_amount'    => $total_amount,
            'total_discount'  => $total_discount,
            'line_count'                  => count($lines),
            'overall_discount_percent'    => $finance['overall_discount_percent'],
            'overall_discount_amount'     => $finance['overall_discount_amount'],
        );
    }

    public function confirmUnifiedBill($case_id, $staff_id, $lines, $remarks = '', $overall_percent = null, $overall_amount = null, $overall_mode = null)
    {
        $this->ensureUnifiedBillTables();
        $case_id = (int) $case_id;
        if ($case_id < 1 || empty($lines)) {
            return array('status' => false, 'message' => 'No bill lines to confirm.');
        }

        if ($this->isCaseBillConfirmed($case_id)) {
            return array('status' => false, 'message' => 'Bill already confirmed for this case.');
        }

        $finance = $this->getServicePostingFinancials($case_id, $lines, $overall_percent, $overall_amount, $overall_mode);
        $total_amount = $finance['total_amount'];
        $total_discount = $finance['total_discount'];
        $total_net = $finance['total_net'];

        $this->db->trans_start();
        $draft = $this->getDraftConfirmationByCaseId($case_id);
        $now = date('Y-m-d H:i:s');
        $header = array(
            'case_reference_id'         => $case_id,
            'confirmed_by'              => (int) $staff_id,
            'confirmed_at'              => $now,
            'status'                    => 'confirmed',
            'total_amount'              => $total_amount,
            'total_discount'            => $total_discount,
            'total_net'                 => $total_net,
            'overall_discount_percent'  => $finance['overall_discount_percent'],
            'overall_discount_amount'   => $finance['overall_discount_amount'],
            'overall_discount_mode'     => $finance['overall_discount_mode'],
            'remarks'                   => $remarks,
        );

        if (!empty($draft['id'])) {
            $this->db->where('id', (int) $draft['id'])->update('unified_bill_confirmations', $header);
            $confirmation_id = (int) $draft['id'];
            $this->db->where('confirmation_id', $confirmation_id)->delete('unified_bill_confirmation_items');
        } else {
            $header['created_at'] = $now;
            $this->db->insert('unified_bill_confirmations', $header);
            $confirmation_id = $this->db->insert_id();
        }

        $items = array();
        foreach ($lines as $line) {
            $items[] = array(
                'confirmation_id'  => $confirmation_id,
                'module'           => $line['module'],
                'module_id'        => (int) $line['module_id'],
                'service_code'     => $line['service_code'],
                'service_name'     => $line['service_name'],
                'qty'              => (float) $line['qty'],
                'rate'             => (float) $line['rate'],
                'amount'           => (float) $line['amount'],
                'discount_percent' => (float) $line['discount_percent'],
                'discount_amount'  => (float) $line['discount_amount'],
                'net_amount'       => (float) $line['net_amount'],
                'reporting_doctor' => !empty($line['reporting_doctor']) ? (int) $line['reporting_doctor'] : null,
            );
        }
        if (!empty($items)) {
            $this->db->insert_batch('unified_bill_confirmation_items', $items);
        }

        $this->db->trans_complete();
        if ($this->db->trans_status() === false) {
            return array('status' => false, 'message' => 'Unable to confirm bill.');
        }

        return array(
            'status'                     => true,
            'confirmation_id'            => $confirmation_id,
            'total_net'                  => $total_net,
            'total_amount'               => $total_amount,
            'total_discount'             => $total_discount,
            'overall_discount_percent'   => $finance['overall_discount_percent'],
            'overall_discount_amount'    => $finance['overall_discount_amount'],
        );
    }

    public function reopenUnifiedBill($case_id, $staff_id)
    {
        $this->ensureUnifiedBillTables();
        $case_id = (int) $case_id;
        $active  = $this->getActiveConfirmationByCaseId($case_id);
        if (empty($active)) {
            return array('status' => false, 'message' => 'No confirmed bill found.');
        }

        $paid = $this->db->select('IFNULL(SUM(amount),0) as paid', false)
            ->where('case_reference_id', $case_id)
            ->where('type', 'payment')
            ->where('section', 'Service Posting')
            ->get('transactions')
            ->row_array();
        if (!empty($paid) && (float) $paid['paid'] > 0) {
            return array('status' => false, 'message' => 'Cannot reopen after payment has been recorded.');
        }

        $this->db->where('id', (int) $active['id'])->update('unified_bill_confirmations', array(
            'status' => 'draft',
        ));
        $this->log('Reopened unified bill confirmation id ' . $active['id'] . ' by staff ' . (int) $staff_id, $active['id'], 'Update');
        return array('status' => true, 'message' => 'Bill reopened for editing.');
    }

    /**
     * Aggregate OPD/IPD/Pathology/Radiology/Pharmacy lines for Service Posting grid.
     */
    public function getServicePostingLines($case_id)
    {
        $case_id = (int) $case_id;
        $lines   = array();
        if ($case_id < 1) {
            return $lines;
        }

        $CI = &get_instance();
        $CI->load->model(array('charge_model', 'pathology_model', 'radio_model', 'pharmacy_model', 'indent_model'));
        $this->ensureCreditBillingSchema();
        $doctor_map = $this->getLineReportingDoctorMap($case_id);

        $indent_map = array();
        if (method_exists($CI->indent_model, 'getAcceptedBillingLinesByCaseId')) {
            foreach ($CI->indent_model->getAcceptedBillingLinesByCaseId($case_id) as $indent_line) {
                $key = $indent_line['module'] . '-' . $indent_line['module_id'];
                $indent_map[$key] = $indent_line;
            }
        }

        $opd = $CI->charge_model->getopdChargesbyCaseId($case_id);
        if (!empty($opd)) {
            foreach ($opd as $row) {
                $qty      = (float) $row['qty'];
                $rate     = (float) $row['apply_charge'];
                $amount   = $rate;
                $disc_pct = (float) $row['discount_percentage'];
                $disc_amt = ($amount * $disc_pct) / 100;
                $net      = (float) $row['amount'];
                $lines[]  = array(
                    'module'           => 'opd',
                    'module_id'        => (int) $row['id'],
                    'service_code'     => !empty($row['charge_type']) ? $row['charge_type'] : 'OPD',
                    'service_name'     => $row['name'],
                    'qty'              => $qty,
                    'rate'             => $rate,
                    'amount'           => $amount,
                    'discount_percent' => $disc_pct,
                    'discount_amount'  => $disc_amt,
                    'net_amount'       => $net,
                    'service_date'     => $this->formatServicePostingDate(isset($row['date']) ? $row['date'] : ''),
                    'remarks'          => isset($row['note']) ? $row['note'] : '',
                    'editable'         => true,
                    'from_indent'      => false,
                    'paid_amount'      => 0,
                );
            }
        }

        $ipd = $CI->charge_model->getipdChargesbyCaseId($case_id);
        if (!empty($ipd)) {
            foreach ($ipd as $row) {
                $qty      = (float) $row['qty'];
                $rate     = (float) $row['apply_charge'];
                $amount   = $rate;
                $disc_pct = (float) $row['discount_percentage'];
                $disc_amt = ($amount * $disc_pct) / 100;
                $net      = (float) $row['amount'];
                $key      = 'ipd-' . (int) $row['id'];
                $from_indent = isset($indent_map['ipd_charge-' . (int) $row['id']]) || isset($indent_map[$key]);
                $lines[]  = array(
                    'module'           => 'ipd',
                    'module_id'        => (int) $row['id'],
                    'service_code'     => !empty($row['charge_type']) ? $row['charge_type'] : 'IPD',
                    'service_name'     => $row['name'],
                    'qty'              => $qty,
                    'rate'             => $rate,
                    'amount'           => $amount,
                    'discount_percent' => $disc_pct,
                    'discount_amount'  => $disc_amt,
                    'net_amount'       => $net,
                    'service_date'     => $this->formatServicePostingDate(isset($row['date']) ? $row['date'] : ''),
                    'remarks'          => isset($row['note']) ? $row['note'] : '',
                    'editable'         => true,
                    'from_indent'      => $from_indent,
                    'paid_amount'      => 0,
                );
            }
        }

        $pathology = $CI->pathology_model->getpathologyByCaseId($case_id);
        if (!empty($pathology)) {
            foreach ($pathology as $row) {
                $names = $this->getModuleBillServiceNames('pathology', $row->id);
                $amount   = (float) $row->total;
                $disc_pct = (float) $row->discount_percentage;
                $disc_amt = (float) $row->discount;
                $net      = (float) $row->net_amount;
                $paid     = (float) $row->amount_paid;
                $key      = 'pathology-' . (int) $row->id;
                $lines[]  = array(
                    'module'           => 'pathology',
                    'module_id'        => (int) $row->id,
                    'service_code'     => 'Pathology',
                    'service_name'     => $names,
                    'qty'              => 1,
                    'rate'             => $amount,
                    'amount'           => $amount,
                    'discount_percent' => $disc_pct,
                    'discount_amount'  => $disc_amt,
                    'net_amount'       => $net,
                    'service_date'     => $this->formatServicePostingDate($row->date),
                    'remarks'          => isset($row->note) ? $row->note : '',
                    'editable'         => true,
                    'from_indent'      => isset($indent_map[$key]),
                    'paid_amount'      => $paid,
                );
            }
        }

        $radiology = $CI->radio_model->getradiologyByCaseId($case_id);
        if (!empty($radiology)) {
            foreach ($radiology as $row) {
                $names = $this->getModuleBillServiceNames('radiology', $row->id);
                $amount   = (float) $row->total;
                $disc_pct = (float) $row->discount_percentage;
                $disc_amt = (float) $row->discount;
                $net      = (float) $row->net_amount;
                $paid     = (float) $row->amount_paid;
                $key      = 'radiology-' . (int) $row->id;
                $lines[]  = array(
                    'module'           => 'radiology',
                    'module_id'        => (int) $row->id,
                    'service_code'     => 'Radiology',
                    'service_name'     => $names,
                    'qty'              => 1,
                    'rate'             => $amount,
                    'amount'           => $amount,
                    'discount_percent' => $disc_pct,
                    'discount_amount'  => $disc_amt,
                    'net_amount'       => $net,
                    'service_date'     => $this->formatServicePostingDate($row->date),
                    'remarks'          => isset($row->note) ? $row->note : '',
                    'editable'         => true,
                    'from_indent'      => isset($indent_map[$key]),
                    'paid_amount'      => $paid,
                );
            }
        }

        $pharmacy = $CI->pharmacy_model->getpharmacybillByCaseId($case_id);
        if (!empty($pharmacy)) {
            foreach ($pharmacy as $row) {
                $detail = $this->db->select('quantity, sale_price')
                    ->from('pharmacy_bill_detail')
                    ->where('pharmacy_bill_basic_id', (int) $row->id)
                    ->order_by('id', 'asc')->limit(1)->get()->row_array();
                $names = $this->getPharmacyBillServiceNames($row->id);
                $qty      = !empty($detail) ? (float) $detail['quantity'] : 1;
                $rate     = !empty($detail) ? (float) $detail['sale_price'] : (float) $row->total;
                $amount   = (float) $row->total;
                $disc_pct = (float) $row->discount_percentage;
                $disc_amt = (float) $row->discount;
                $net      = (float) $row->net_amount;
                $paid     = (float) $row->amount_paid - (float) $row->refund_amount;
                $key      = 'pharmacy-' . (int) $row->id;
                $lines[]  = array(
                    'module'           => 'pharmacy',
                    'module_id'        => (int) $row->id,
                    'service_code'     => 'Pharmacy',
                    'service_name'     => $names,
                    'qty'              => $qty,
                    'rate'             => $rate,
                    'amount'           => $amount,
                    'discount_percent' => $disc_pct,
                    'discount_amount'  => $disc_amt,
                    'net_amount'       => $net,
                    'service_date'     => $this->formatServicePostingDate($row->date),
                    'remarks'          => isset($row->note) ? $row->note : '',
                    'editable'         => true,
                    'from_indent'      => isset($indent_map[$key]),
                    'paid_amount'      => $paid,
                );
            }
        }

        foreach ($lines as &$line) {
            $key = $line['module'] . '-' . $line['module_id'];
            $line['reporting_doctor'] = isset($doctor_map[$key]) ? $doctor_map[$key] : '';
        }
        unset($line);

        return $lines;
    }

    /**
     * Update pharmacy bill header/detail for Service Posting edit.
     */
    public function updatePharmacyBillLine($bill_id, $qty, $rate, $discount_percentage = null, $note = null)
    {
        $bill_id = (int) $bill_id;
        $bill = $this->db->where('id', $bill_id)->get('pharmacy_bill_basic')->row_array();
        if (empty($bill)) {
            return array('status' => false, 'message' => 'Pharmacy bill not found.');
        }

        $detail = $this->db->where('pharmacy_bill_basic_id', $bill_id)->order_by('id', 'asc')->limit(1)->get('pharmacy_bill_detail')->row_array();
        if (empty($detail)) {
            return array('status' => false, 'message' => 'Pharmacy bill detail not found.');
        }

        $old_qty = (float) $detail['quantity'];
        $qty = ($qty !== null && $qty !== '') ? (float) $qty : $old_qty;
        if ($qty <= 0) {
            return array('status' => false, 'message' => 'Invalid quantity.');
        }

        $rate = ($rate !== null && $rate !== '') ? (float) $rate : (float) $detail['sale_price'];
        $discount_percentage = ($discount_percentage !== null && $discount_percentage !== '')
            ? (float) $discount_percentage
            : (float) $bill['discount_percentage'];

        $qty_diff = $qty - $old_qty;
        if ($qty_diff > 0) {
            $batch = $this->db->where('id', (int) $detail['medicine_batch_detail_id'])->get('medicine_batch_details')->row_array();
            if (empty($batch) || (float) $batch['available_quantity'] < $qty_diff) {
                return array('status' => false, 'message' => 'Not enough stock for the increased quantity.');
            }
        }

        $this->db->trans_start();
        if ($qty_diff != 0) {
            if ($qty_diff > 0) {
                $this->db->set('available_quantity', 'available_quantity - ' . $this->db->escape($qty_diff), false)
                    ->where('id', (int) $detail['medicine_batch_detail_id'])
                    ->where('available_quantity >=', $qty_diff)
                    ->update('medicine_batch_details');
                if ($this->db->affected_rows() !== 1) {
                    $this->db->trans_rollback();
                    return array('status' => false, 'message' => 'Stock changed. Please try again.');
                }
            } else {
                $this->db->set('available_quantity', 'available_quantity + ' . $this->db->escape(abs($qty_diff)), false)
                    ->where('id', (int) $detail['medicine_batch_detail_id'])
                    ->update('medicine_batch_details');
            }
        }

        $subtotal = round($qty * $rate, 2);
        $discount = round(($subtotal * $discount_percentage) / 100, 2);
        $tax_pct = (float) $bill['tax_percentage'];
        $tax = round(($subtotal - $discount) * $tax_pct / 100, 2);
        $net = $subtotal - $discount + $tax;

        $this->db->where('id', (int) $detail['id'])->update('pharmacy_bill_detail', array(
            'quantity'   => $qty,
            'sale_price' => $rate,
            'discount'   => $discount,
            'amount'     => $net,
        ));

        $update_bill = array(
            'total'               => $subtotal,
            'discount'            => $discount,
            'discount_percentage' => $discount_percentage,
            'tax'                 => $tax,
            'net_amount'          => $net,
        );
        if ($note !== null) {
            $update_bill['note'] = $note;
        }
        $this->db->where('id', $bill_id)->update('pharmacy_bill_basic', $update_bill);

        $this->db->trans_complete();
        if ($this->db->trans_status() === false) {
            return array('status' => false, 'message' => 'Unable to update pharmacy line.');
        }

        return array('status' => true, 'amount' => $net);
    }

    /**
     * Delete pharmacy bill and restore stock for Service Posting.
     */
    public function deletePharmacyBillLine($bill_id)
    {
        $bill_id = (int) $bill_id;
        $details = $this->db->where('pharmacy_bill_basic_id', $bill_id)->get('pharmacy_bill_detail')->result_array();
        $exists  = $this->db->where('id', $bill_id)->count_all_results('pharmacy_bill_basic');
        if (!$exists) {
            return array('status' => false, 'message' => 'Pharmacy bill not found.');
        }

        $this->db->trans_start();
        foreach ($details as $detail) {
            $this->db->set('available_quantity', 'available_quantity + ' . $this->db->escape((float) $detail['quantity']), false)
                ->where('id', (int) $detail['medicine_batch_detail_id'])
                ->update('medicine_batch_details');
        }

        $this->db->where('pharmacy_bill_basic_id', $bill_id)->delete('pharmacy_bill_detail');
        $this->db->where('pharmacy_bill_basic_id', $bill_id)->delete('transactions');
        $this->db->where('id', $bill_id)->delete('pharmacy_bill_basic');

        $this->db->trans_complete();
        if ($this->db->trans_status() === false) {
            return array('status' => false, 'message' => 'Unable to remove pharmacy line.');
        }
        return array('status' => true);
    }

    private function getModuleBillServiceNames($module, $bill_id)
    {
        if ($module === 'pathology') {
            $rows = $this->db->select('pathology.test_name')
                ->from('pathology_report')
                ->join('pathology', 'pathology.id = pathology_report.pathology_id', 'left')
                ->where('pathology_report.pathology_bill_id', (int) $bill_id)
                ->get()->result_array();
        } else {
            $rows = $this->db->select('radio.test_name')
                ->from('radiology_report')
                ->join('radio', 'radio.id = radiology_report.radiology_id', 'left')
                ->where('radiology_report.radiology_bill_id', (int) $bill_id)
                ->get()->result_array();
        }
        $names = array();
        foreach ($rows as $row) {
            if (!empty($row['test_name'])) {
                $names[] = $row['test_name'];
            }
        }
        if (empty($names)) {
            return ucfirst($module) . ' Bill #' . $bill_id;
        }
        return implode(', ', $names);
    }

    private function getPharmacyBillServiceNames($bill_id)
    {
        $rows = $this->db->select('pharmacy.medicine_name, pharmacy_bill_detail.quantity')
            ->from('pharmacy_bill_detail')
            ->join('medicine_batch_details', 'medicine_batch_details.id = pharmacy_bill_detail.medicine_batch_detail_id', 'left')
            ->join('pharmacy', 'pharmacy.id = medicine_batch_details.pharmacy_id', 'left')
            ->where('pharmacy_bill_detail.pharmacy_bill_basic_id', (int) $bill_id)
            ->get()->result_array();
        $names = array();
        foreach ($rows as $row) {
            if (!empty($row['medicine_name'])) {
                $names[] = $row['medicine_name'] . ' x' . $row['quantity'];
            }
        }
        if (empty($names)) {
            return 'Pharmacy Bill #' . $bill_id;
        }
        return implode(', ', $names);
    }

    /**
     * Format a stored datetime for the Service Posting grid.
     */
    private function formatServicePostingDate($date)
    {
        if (empty($date) || substr((string) $date, 0, 10) === '0000-00-00') {
            return '';
        }
        $CI = &get_instance();
        return $CI->customlib->YYYYMMDDHisTodateFormat($date, $CI->customlib->getHospitalTimeFormat());
    }

    /**
     * Normalize a Y-m-d or Y-m-d H:i:s value for bill date columns.
     */
    private function normalizeServiceDateTime($service_date)
    {
        $service_date = trim((string) $service_date);
        if ($service_date === '') {
            return date('Y-m-d H:i:s');
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $service_date)) {
            return $service_date;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $service_date)) {
            return $service_date . ':00';
        }
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $service_date, $m)) {
            return $m[1] . ' ' . date('H:i:s');
        }
        return date('Y-m-d H:i:s');
    }

    /**
     * Create a pathology or radiology billing row for Service Posting manual add.
     */
    public function createTestBillLine($type, $case_id, $patient_id, $test_id, $staff_id, $qty = 1, $rate = null, $discount_percentage = 0, $note = '', $service_date = null)
    {
        $type = ($type === 'radiology') ? 'radiology' : 'pathology';
        $master = ($type === 'pathology') ? 'pathology' : 'radio';
        $test = $this->db->select($master . '.id, ' . $master . '.test_name, charges.standard_charge, tax_category.percentage as tax_percentage')
            ->from($master)
            ->join('charges', 'charges.id = ' . $master . '.charge_id', 'left')
            ->join('tax_category', 'tax_category.id = charges.tax_category_id', 'left')
            ->where($master . '.id', (int) $test_id)
            ->get()->row_array();
        if (empty($test)) {
            return array('status' => false, 'message' => 'Test not found.');
        }

        $qty = max(1, (float) $qty);
        $unit_rate = ($rate !== null && $rate !== '') ? (float) $rate : (float) $test['standard_charge'];
        $amount = round($unit_rate * $qty, 2);
        $discount_percentage = (float) $discount_percentage;
        $discount = round(($amount * $discount_percentage) / 100, 2);
        $tax_percentage = (float) $test['tax_percentage'];
        $tax = round(($amount - $discount) * $tax_percentage / 100, 2);
        $net = $amount - $discount + $tax;

        $ipd = $this->db->select('cons_doctor, organisation_id')->from('ipd_details')->where('case_reference_id', (int) $case_id)->order_by('id', 'desc')->limit(1)->get()->row_array();
        $opd = empty($ipd) ? $this->db->select('organisation_id')->from('opd_details')->where('case_reference_id', (int) $case_id)->order_by('id', 'desc')->limit(1)->get()->row_array() : null;
        $doctor_id = !empty($ipd['cons_doctor']) ? $ipd['cons_doctor'] : null;
        $org_id = !empty($ipd['organisation_id']) ? $ipd['organisation_id'] : (!empty($opd['organisation_id']) ? $opd['organisation_id'] : null);
        $bill_date = $this->normalizeServiceDateTime($service_date);

        $billing_table = ($type === 'pathology') ? 'pathology_billing' : 'radiology_billing';
        $this->db->insert($billing_table, array(
            'date'                => $bill_date,
            'patient_id'          => (int) $patient_id,
            'doctor_id'           => $doctor_id,
            'doctor_name'         => '',
            'case_reference_id'   => (int) $case_id,
            'total'               => $amount,
            'discount_percentage' => $discount_percentage,
            'discount'            => $discount,
            'tax_percentage'      => $tax_percentage,
            'tax'                 => $tax,
            'net_amount'          => $net,
            'note'                => $note !== '' ? $note : 'Added from Service Posting',
            'organisation_id'     => $org_id ?: null,
            'generated_by'        => (int) $staff_id,
        ));
        $bill_id = $this->db->insert_id();
        if (!$bill_id) {
            return array('status' => false, 'message' => 'Unable to create bill.');
        }

        $report_table = ($type === 'pathology') ? 'pathology_report' : 'radiology_report';
        $report = array(
            ($type === 'pathology' ? 'pathology_bill_id' : 'radiology_bill_id') => $bill_id,
            ($type === 'pathology' ? 'pathology_id' : 'radiology_id') => (int) $test_id,
            'patient_id'     => (int) $patient_id,
            'reporting_date' => substr($bill_date, 0, 10),
            'apply_charge'   => $unit_rate,
            'tax_percentage' => $tax_percentage,
            'description'    => $note,
        );
        if ($type === 'radiology') {
            $report['consultant_doctor'] = (string) $doctor_id;
            $report['radiology_center']  = '';
            $report['generated_by']      = (int) $staff_id;
        }
        $this->db->insert($report_table, $report);

        return array(
            'status'       => true,
            'module'       => $type,
            'module_id'    => $bill_id,
            'service_name' => $test['test_name'],
            'net_amount'   => $net,
        );
    }

    public function resolveCaseReference($case_id = '', $ip_number = '', $mr_number = '')
    {
        $case_id = trim((string) $case_id);
        $ip_number = trim((string) $ip_number);
        $mr_number = trim((string) $mr_number);

        if ($case_id !== '' && is_numeric($case_id)) {
            $exists = $this->db->where('id', (int) $case_id)->count_all_results('case_references');
            if ($exists) {
                return (int) $case_id;
            }
        }

        if ($ip_number !== '') {
            $ip_id = preg_replace('/\D+/', '', $ip_number);
            if ($ip_id !== '') {
                $row = $this->db->select('case_reference_id')->from('ipd_details')->where('id', (int) $ip_id)->get()->row_array();
                if (!empty($row['case_reference_id'])) {
                    return (int) $row['case_reference_id'];
                }
            }
        }

        if ($mr_number !== '') {
            $patient = $this->db->select('id')->from('patients')
                ->group_start()
                    ->where('id', is_numeric($mr_number) ? (int) $mr_number : 0)
                    ->or_where('patient_unique_id', $mr_number)
                ->group_end()
                ->limit(1)->get()->row_array();
            if (!empty($patient['id'])) {
                $ipd = $this->db->select('case_reference_id')->from('ipd_details')
                    ->where('patient_id', (int) $patient['id'])
                    ->order_by('id', 'desc')->limit(1)->get()->row_array();
                if (!empty($ipd['case_reference_id'])) {
                    return (int) $ipd['case_reference_id'];
                }
                $opd = $this->db->select('case_reference_id')->from('opd_details')
                    ->where('patient_id', (int) $patient['id'])
                    ->order_by('id', 'desc')->limit(1)->get()->row_array();
                if (!empty($opd['case_reference_id'])) {
                    return (int) $opd['case_reference_id'];
                }
            }
        }

        return 0;
    }

    public function searchServiceItems($service_code, $term = '')
    {
        $service_code = strtolower(trim($service_code));
        $term = trim($term);
        $list = array();

        // Pathology / Radiology use charge_type_master → charges (same as other charge types).
        // Do not search empty radio/pathology test catalogs from Service Posting.

        $charge_type_id = $this->resolveChargeTypeIdByServiceCode($service_code);
        if ($charge_type_id > 0) {
            $this->db->select('charges.id, charges.name, charges.standard_charge as rate, tax_category.percentage as tax');
            $this->db->from('charges');
            $this->db->join('charge_categories', 'charge_categories.id = charges.charge_category_id', 'inner');
            $this->db->join('tax_category', 'tax_category.id = charges.tax_category_id', 'left');
            $this->db->where('charge_categories.charge_type_id', $charge_type_id);
            if ($term !== '') {
                $this->db->like('charges.name', $term);
            }
            $this->db->order_by('charges.name', 'asc');
            $this->db->limit(30);
            foreach ($this->db->get()->result_array() as $row) {
                $list[] = array(
                    'id'   => (int) $row['id'],
                    'name' => $row['name'],
                    'rate' => (float) $row['rate'],
                    'tax'  => (float) $row['tax'],
                    'source' => 'charge',
                );
            }
            return $list;
        }

        if ($service_code === 'pharmacy') {
            $this->db->select('medicine_batch_details.id, pharmacy.medicine_name, medicine_batch_details.batch_no, medicine_batch_details.available_quantity, medicine_batch_details.sale_rate, medicine_batch_details.unit_sale_rate, medicine_batch_details.tax, medicine_batch_details.expiry, medicine_batch_details.packing_qty');
            $this->db->from('medicine_batch_details');
            $this->db->join('pharmacy', 'pharmacy.id = medicine_batch_details.pharmacy_id', 'inner');
            $this->db->where('medicine_batch_details.available_quantity >', 0);
            $this->db->where('medicine_batch_details.expiry >=', date('Y-m-d'));
            if ($term !== '') {
                $this->db->group_start()
                    ->like('pharmacy.medicine_name', $term)
                    ->or_like('medicine_batch_details.batch_no', $term)
                    ->group_end();
            }
            $this->db->order_by('pharmacy.medicine_name', 'asc');
            $this->db->limit(30);
            foreach ($this->db->get()->result_array() as $row) {
                $rate = 0;
                if (isset($row['unit_sale_rate']) && $row['unit_sale_rate'] !== null && $row['unit_sale_rate'] !== '') {
                    $rate = (float) $row['unit_sale_rate'];
                } else {
                    $pack = (float) $row['packing_qty'];
                    $rate = ($pack > 0) ? round(((float) $row['sale_rate']) / $pack, 2) : (float) $row['sale_rate'];
                }
                $list[] = array(
                    'id'      => (int) $row['id'],
                    'name'    => $row['medicine_name'] . ' / Batch ' . $row['batch_no'] . ' (Qty ' . $row['available_quantity'] . ')',
                    'rate'    => $rate,
                    'tax'     => (float) $row['tax'],
                    'stock'   => (float) $row['available_quantity'],
                    'source'  => 'pharmacy_batch',
                );
            }
        }

        return $list;
    }

    /**
     * Whether service_code maps to an active charge type (charges table lookup).
     */
    public function isChargeTypeServiceCode($service_code)
    {
        return $this->resolveChargeTypeIdByServiceCode($service_code) > 0;
    }

    /**
     * Resolve charge_type_master.id from Service Posting service_code label.
     */
    public function resolveChargeTypeIdByServiceCode($service_code)
    {
        $service_code = trim((string) $service_code);
        if ($service_code === '' || strtolower($service_code) === 'pharmacy') {
            return 0;
        }
        $types = $this->db->select('id, charge_type')
            ->from('charge_type_master')
            ->where('is_active', 'yes')
            ->get()
            ->result_array();
        foreach ($types as $row) {
            if (strcasecmp($row['charge_type'], $service_code) === 0) {
                return (int) $row['id'];
            }
        }
        return 0;
    }

    /**
     * Create pharmacy bill from Service Posting. item_id is medicine_batch_details.id.
     */
    public function createPharmacyBillLine($case_id, $patient_id, $batch_id, $staff_id, $qty = 1, $rate = null, $discount_percentage = 0, $note = '', $service_date = null)
    {
        $qty = (float) $qty;
        $batch_id = (int) $batch_id;
        if ($qty <= 0 || $batch_id < 1) {
            return array('status' => false, 'message' => 'Invalid pharmacy quantity or batch.');
        }

        $this->db->trans_start();
        $batch = $this->db->select('medicine_batch_details.*, pharmacy.medicine_name, pharmacy.id as pharmacy_id')
            ->from('medicine_batch_details')
            ->join('pharmacy', 'pharmacy.id = medicine_batch_details.pharmacy_id', 'inner')
            ->where('medicine_batch_details.id', $batch_id)
            ->get()->row_array();

        if (empty($batch) || (float) $batch['available_quantity'] < $qty || $batch['expiry'] < date('Y-m-d')) {
            $this->db->trans_rollback();
            return array('status' => false, 'message' => 'Selected medicine batch is expired or does not have enough stock.');
        }

        if ($rate !== null && $rate !== '') {
            $unit_rate = (float) $rate;
        } elseif (isset($batch['unit_sale_rate']) && $batch['unit_sale_rate'] !== null && $batch['unit_sale_rate'] !== '') {
            $unit_rate = (float) $batch['unit_sale_rate'];
        } else {
            $pack = (float) $batch['packing_qty'];
            $unit_rate = ($pack > 0) ? round(((float) $batch['sale_rate']) / $pack, 2) : (float) $batch['sale_rate'];
        }

        $subtotal = round($qty * $unit_rate, 2);
        $discount_percentage = (float) $discount_percentage;
        $discount = round(($subtotal * $discount_percentage) / 100, 2);
        $tax_percentage = (float) $batch['tax'];
        $tax = round(($subtotal - $discount) * $tax_percentage / 100, 2);
        $net = $subtotal - $discount + $tax;

        $patient = $this->db->select('patient_name, organisation_id')->from('patients')->where('id', (int) $patient_id)->get()->row_array();
        $ipd = $this->db->select('staff.name as doctor_name, staff.surname as doctor_surname')
            ->from('ipd_details')
            ->join('staff', 'staff.id = ipd_details.cons_doctor', 'left')
            ->where('ipd_details.case_reference_id', (int) $case_id)
            ->order_by('ipd_details.id', 'desc')->limit(1)->get()->row_array();

        $this->db->insert('pharmacy_bill_basic', array(
            'date'                => $this->normalizeServiceDateTime($service_date),
            'patient_id'          => (int) $patient_id,
            'case_reference_id'   => (int) $case_id,
            'customer_name'       => !empty($patient['patient_name']) ? $patient['patient_name'] : '',
            'customer_type'       => 'patient',
            'doctor_name'         => !empty($ipd) ? trim($ipd['doctor_name'] . ' ' . $ipd['doctor_surname']) : '',
            'file'                => '',
            'total'               => $subtotal,
            'discount_percentage' => $discount_percentage,
            'discount'            => $discount,
            'tax_percentage'      => $tax_percentage,
            'tax'                 => $tax,
            'net_amount'          => $net,
            'note'                => $note !== '' ? $note : 'Added from Service Posting',
            'organisation_id'     => !empty($patient['organisation_id']) ? $patient['organisation_id'] : null,
            'generated_by'        => (int) $staff_id,
        ));
        $bill_id = $this->db->insert_id();
        if (!$bill_id) {
            $this->db->trans_rollback();
            return array('status' => false, 'message' => 'Unable to create pharmacy bill.');
        }

        $this->db->insert('pharmacy_bill_detail', array(
            'pharmacy_bill_basic_id'   => $bill_id,
            'medicine_batch_detail_id' => $batch_id,
            'quantity'                 => $qty,
            'sale_price'               => $unit_rate,
            'discount'                 => $discount,
            'amount'                   => $net,
        ));

        $this->db->set('available_quantity', 'available_quantity - ' . $this->db->escape($qty), false)
            ->where('id', $batch_id)
            ->where('available_quantity >=', $qty)
            ->update('medicine_batch_details');

        if ($this->db->affected_rows() !== 1) {
            $this->db->trans_rollback();
            return array('status' => false, 'message' => 'Stock changed while adding medicine. Please try again.');
        }

        $this->db->trans_complete();
        if ($this->db->trans_status() === false) {
            return array('status' => false, 'message' => 'Unable to create pharmacy bill.');
        }

        return array(
            'status'       => true,
            'module'       => 'pharmacy',
            'module_id'    => $bill_id,
            'service_name' => $batch['medicine_name'],
            'net_amount'   => $net,
        );
    }

}
