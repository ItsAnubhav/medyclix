<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Indent_model extends MY_Model
{
    public function add($data, $items)
    {
        $this->db->trans_start();
        $this->db->trans_strict(false);
        $this->db->insert('ipd_indent', $data);
        $indent_id = $this->db->insert_id();

        if (!empty($items)) {
            foreach ($items as $key => $item) {
                $items[$key]['indent_id'] = $indent_id;
            }
            $this->db->insert_batch('ipd_indent_items', $items);
        }

        $this->log(INSERT_RECORD_CONSTANT . " On IPD Indent id " . $indent_id, $indent_id, "Insert");
        $this->db->trans_complete();

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return false;
        }

        return $indent_id;
    }

    public function getByIpd($ipd_id)
    {
        $indents = $this->db->select('ipd_indent.*, staff.name, staff.surname, staff.employee_id')
            ->from('ipd_indent')
            ->join('staff', 'staff.id = ipd_indent.created_by', 'left')
            ->where('ipd_indent.ipd_id', $ipd_id)
            ->order_by('ipd_indent.id', 'desc')
            ->get()
            ->result_array();

        if (empty($indents)) {
            return array();
        }

        $ids = array();
        foreach ($indents as $row) {
            $ids[] = $row['id'];
        }

        $items = $this->db->select('*')
            ->from('ipd_indent_items')
            ->where_in('indent_id', $ids)
            ->order_by('id', 'asc')
            ->get()
            ->result_array();

        $grouped = array();
        foreach ($items as $item) {
            $grouped[$item['indent_id']][] = $item;
        }

        foreach ($indents as $key => $indent) {
            $indents[$key]['items'] = isset($grouped[$indent['id']]) ? $grouped[$indent['id']] : array();
        }

        return $indents;
    }

    public function getChargeLedgerByIpd($ipd_id)
    {
        $rows = $this->db->select("ipd_indent_fulfilments.id, ipd_indent_fulfilments.processed_at as date, ipd_indent_fulfilments.quantity as qty, ipd_indent_fulfilments.billing_type, ipd_indent_fulfilments.billing_id, ipd_indent_items.item_name as name, ipd_indent_items.note, ipd_indent_items.clinical_indication, pharmacy_bill_detail.sale_price, pharmacy_bill_detail.amount, pharmacy_bill_basic.tax_percentage")
            ->from('ipd_indent_fulfilments')
            ->join('ipd_indent_items', 'ipd_indent_items.id = ipd_indent_fulfilments.indent_item_id')
            ->join('ipd_indent', 'ipd_indent.id = ipd_indent_items.indent_id')
            ->join('pharmacy_bill_detail', 'pharmacy_bill_detail.id = ipd_indent_fulfilments.billing_detail_id', 'left')
            ->join('pharmacy_bill_basic', 'pharmacy_bill_basic.id = ipd_indent_fulfilments.billing_id AND ipd_indent_fulfilments.billing_type = "pharmacy"', 'left', false)
            ->where('ipd_indent.ipd_id', (int) $ipd_id)
            ->where('ipd_indent_fulfilments.billing_type', 'pharmacy')
            ->get()->result_array();

        $ledger = array();
        foreach ($rows as $row) {
            $ledger[] = $this->formatLedgerRow($row, 'Pharmacy', 'Medicine');
        }

        foreach (array('pathology', 'radiology') as $type) {
            $report_table = $type === 'pathology' ? 'pathology_report' : 'radiology_report';
            $bill_column = $type === 'pathology' ? 'pathology_bill_id' : 'radiology_bill_id';
            $test_rows = $this->db->select("ipd_indent_fulfilments.id, ipd_indent_fulfilments.processed_at as date, ipd_indent_fulfilments.quantity as qty, ipd_indent_fulfilments.billing_type, ipd_indent_fulfilments.billing_id, ipd_indent_items.item_name as name, ipd_indent_items.note, ipd_indent_items.clinical_indication, {$report_table}.apply_charge as sale_price, {$report_table}.tax_percentage")
                ->from('ipd_indent_fulfilments')
                ->join('ipd_indent_items', 'ipd_indent_items.id = ipd_indent_fulfilments.indent_item_id')
                ->join('ipd_indent', 'ipd_indent.id = ipd_indent_items.indent_id')
                ->join($report_table, $report_table . '.id = ipd_indent_fulfilments.billing_detail_id AND ' . $report_table . '.' . $bill_column . ' = ipd_indent_fulfilments.billing_id', 'inner')
                ->where('ipd_indent.ipd_id', (int) $ipd_id)
                ->where('ipd_indent_fulfilments.billing_type', $type)
                ->get()->result_array();
            foreach ($test_rows as $row) {
                $row['amount'] = (float) $row['sale_price'] * (float) $row['qty'] * (1 + ((float) $row['tax_percentage'] / 100));
                $ledger[] = $this->formatLedgerRow($row, ucfirst($type), 'Diagnostic Test');
            }
        }

        return $ledger;
    }

    private function formatLedgerRow($row, $charge_type, $category)
    {
        $note = trim($row['clinical_indication'] . ($row['clinical_indication'] && $row['note'] ? ' - ' : '') . $row['note']);
        return array(
            'id' => 'indent-' . $row['id'], 'date' => $row['date'], 'name' => $row['name'],
            'note' => $note, 'charge_type' => $charge_type, 'charge_category_name' => $category . ' (IPD Indent)',
            'qty' => (float) $row['qty'], 'standard_charge' => (float) $row['sale_price'],
            'apply_charge' => (float) $row['sale_price'], 'tpa_charge' => 0,
            'discount_percentage' => 0, 'tax' => (float) $row['tax_percentage'],
            'amount' => (float) $row['amount'], 'is_indent_charge' => true,
            'billing_type' => $row['billing_type'], 'billing_id' => $row['billing_id'],
        );
    }

    public function delete($id, $ipd_id)
    {
        $processed = $this->db->where('indent_id', (int) $id)->where('status !=', 'pending')->count_all_results('ipd_indent_items');
        if ($processed > 0) {
            return false;
        }
        $this->db->where('id', $id);
        $this->db->where('ipd_id', $ipd_id);
        $this->db->delete('ipd_indent');
        $this->log(DELETE_RECORD_CONSTANT . " On IPD Indent id " . $id, $id, "Delete");
        return true;
    }

    public function getDropdownTests($type)
    {
        $list = array();
        if ($type === 'pathology') {
            $rows = $this->db->select('id, test_name, short_name')->from('pathology')->order_by('test_name', 'asc')->get()->result_array();
            $list = $this->formatTestRows($rows, 'master');
            if (empty($list)) {
                $list = $this->getChargeTestsByKeyword('Pathology');
            }
        } elseif ($type === 'radiology') {
            $rows = $this->db->select('id, test_name, short_name')->from('radio')->order_by('test_name', 'asc')->get()->result_array();
            $list = $this->formatTestRows($rows, 'master');
            if (empty($list)) {
                $list = $this->getChargeTestsByKeyword('Radiology');
            }
        }
        return $list;
    }

    private function formatTestRows($rows, $source = 'master')
    {
        $list = array();
        if (empty($rows)) {
            return $list;
        }
        foreach ($rows as $row) {
            $name = isset($row['test_name']) ? $row['test_name'] : '';
            if ($name === '' && isset($row['name'])) {
                $name = $row['name'];
            }
            if ($name === '') {
                continue;
            }
            if (!empty($row['short_name'])) {
                $name .= ' (' . $row['short_name'] . ')';
            }
            $list[] = array('id' => (int) $row['id'], 'name' => $name, 'source' => $source);
        }
        return $list;
    }

    private function getChargeTestsByKeyword($keyword)
    {
        $rows = $this->db->select('charges.id, charges.name as test_name, charge_categories.name as short_name')
            ->from('charges')
            ->join('charge_categories', 'charge_categories.id = charges.charge_category_id', 'inner')
            ->join('charge_type_master', 'charge_type_master.id = charge_categories.charge_type_id', 'left')
            ->group_start()
                ->like('charge_categories.name', $keyword)
                ->or_where('charge_type_master.charge_type', $keyword)
            ->group_end()
            ->order_by('charges.name', 'asc')
            ->get()
            ->result_array();
        return $this->formatTestRows($rows, 'charge');
    }

    public function getItemName($type, $id, $source = 'master')
    {
        $id = (int) $id;
        if ($type === 'medicine') {
            $row = $this->db->select('medicine_name as name')->from('pharmacy')->where('id', $id)->get()->row_array();
        } elseif ($source === 'charge' && in_array($type, array('radiology', 'pathology'))) {
            $row = $this->db->select('name')->from('charges')->where('id', $id)->get()->row_array();
        } elseif ($type === 'radiology') {
            $row = $this->db->select('test_name as name')->from('radio')->where('id', $id)->get()->row_array();
        } elseif ($type === 'pathology') {
            $row = $this->db->select('test_name as name')->from('pathology')->where('id', $id)->get()->row_array();
        } else {
            return '';
        }

        return !empty($row['name']) ? $row['name'] : '';
    }

    public function getDepartmentItems($type)
    {
        return $this->db->select('ipd_indent_items.*, ipd_indent.ipd_id, ipd_indent.patient_id, ipd_indent.note as indent_note, ipd_indent.created_at as indent_date, patients.patient_name, patients.patient_unique_id, ipd_details.case_reference_id, staff.name as requested_by_name, staff.surname as requested_by_surname, staff.employee_id as requested_by_employee_id')
            ->from('ipd_indent_items')
            ->join('ipd_indent', 'ipd_indent.id = ipd_indent_items.indent_id')
            ->join('ipd_details', 'ipd_details.id = ipd_indent.ipd_id')
            ->join('patients', 'patients.id = ipd_indent.patient_id')
            ->join('staff', 'staff.id = ipd_indent.created_by', 'left')
            ->where('ipd_indent_items.item_type', $type)
            ->order_by("FIELD(ipd_indent_items.status, 'pending', 'partially_accepted', 'accepted', 'rejected', 'cancelled')", '', false)
            ->order_by('ipd_indent_items.id', 'desc')->get()->result_array();
    }

    public function getMedicineBatches($medicine_id)
    {
        return $this->db->select('id, batch_no, expiry, sale_rate, tax, available_quantity')
            ->from('medicine_batch_details')->where('pharmacy_id', (int) $medicine_id)
            ->where('available_quantity >', 0)->where('expiry >=', date('Y-m-d'))
            ->order_by('expiry', 'asc')->get()->result_array();
    }

    public function rejectItem($item_id, $type, $staff_id, $reason)
    {
        $this->db->where(array('id' => (int) $item_id, 'item_type' => $type, 'status' => 'pending'));
        $this->db->update('ipd_indent_items', array(
            'status' => 'rejected', 'accepted_by' => $staff_id, 'accepted_at' => date('Y-m-d H:i:s'),
            'rejection_reason' => $reason,
        ));
        return $this->db->affected_rows() === 1;
    }

    public function processItem($item_id, $type, $staff_id, $accepted_quantity = 1, $batch_id = 0)
    {
        $this->db->trans_begin();
        $locked_item = $this->db->query('SELECT id FROM ipd_indent_items WHERE id = ? FOR UPDATE', array((int) $item_id))->row_array();
        if (empty($locked_item)) {
            $this->db->trans_rollback();
            return array('status' => false, 'message' => 'The indent item was not found.');
        }
        $item = $this->db->select('ipd_indent_items.*, ipd_indent.ipd_id, ipd_indent.patient_id, ipd_details.case_reference_id, ipd_details.cons_doctor, ipd_details.discharged, patients.patient_name, patients.organisation_id, staff.name as doctor_name, staff.surname as doctor_surname')
            ->from('ipd_indent_items')->join('ipd_indent', 'ipd_indent.id = ipd_indent_items.indent_id')
            ->join('ipd_details', 'ipd_details.id = ipd_indent.ipd_id')->join('patients', 'patients.id = ipd_indent.patient_id')
            ->join('staff', 'staff.id = ipd_details.cons_doctor', 'left')
            ->where(array('ipd_indent_items.id' => (int) $item_id, 'ipd_indent_items.item_type' => $type))
            ->where_in('ipd_indent_items.status', array('pending', 'partially_accepted'))
            ->get()->row_array();
        if (empty($item) || $item['discharged'] === 'yes') {
            $this->db->trans_rollback();
            return array('status' => false, 'message' => 'This indent is no longer available for acceptance.');
        }
        $bill = $type === 'medicine'
            ? $this->createPharmacyBill($item, $staff_id, $accepted_quantity, $batch_id)
            : ($item['item_source'] === 'charge' ? $this->createIpdCharge($item) : $this->createTestBill($item, $type, $staff_id));
        if (empty($bill['billing_id'])) {
            $this->db->trans_rollback();
            return array('status' => false, 'message' => isset($bill['message']) ? $bill['message'] : 'Unable to generate the bill.');
        }
        $previously_accepted = (float) $item['accepted_quantity'];
        $total_accepted = $type === 'medicine' ? $previously_accepted + (float) $accepted_quantity : 1;
        $status = ($type === 'medicine' && $total_accepted < (float) $item['requested_quantity']) ? 'partially_accepted' : 'accepted';
        $this->db->where('id', (int) $item_id)->where_in('status', array('pending', 'partially_accepted'))->update('ipd_indent_items', array(
            'accepted_quantity' => $total_accepted, 'status' => $status,
            'accepted_by' => $staff_id, 'accepted_at' => date('Y-m-d H:i:s'),
            'billing_type' => $type === 'medicine' ? 'pharmacy' : ($item['item_source'] === 'charge' ? 'ipd_charge' : $type),
            'billing_id' => $bill['billing_id'], 'billing_detail_id' => $bill['billing_detail_id'],
        ));
        if ($this->db->affected_rows() !== 1 || $this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return array('status' => false, 'message' => 'The indent was already processed or billing failed.');
        }
        $this->db->insert('ipd_indent_fulfilments', array(
            'indent_item_id' => $item_id, 'quantity' => $type === 'medicine' ? $accepted_quantity : 1,
            'billing_type' => $type === 'medicine' ? 'pharmacy' : ($item['item_source'] === 'charge' ? 'ipd_charge' : $type), 'billing_id' => $bill['billing_id'],
            'billing_detail_id' => $bill['billing_detail_id'], 'processed_by' => $staff_id, 'processed_at' => date('Y-m-d H:i:s'),
        ));
        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return array('status' => false, 'message' => 'Unable to record indent fulfilment.');
        }
        $this->db->trans_commit();
        return array('status' => true, 'billing_id' => $bill['billing_id']);
    }

    private function createPharmacyBill($item, $staff_id, $quantity, $batch_id)
    {
        $quantity = (float) $quantity;
        $remaining = (float) $item['requested_quantity'] - (float) $item['accepted_quantity'];
        if ($quantity <= 0 || $quantity > $remaining) {
            return array('message' => 'Accepted quantity must be positive and cannot exceed the remaining requested quantity.');
        }
        $batch = $this->db->from('medicine_batch_details')->where(array('id' => (int) $batch_id, 'pharmacy_id' => (int) $item['item_id']))->get()->row_array();
        if (empty($batch) || (float) $batch['available_quantity'] < $quantity || $batch['expiry'] < date('Y-m-d')) {
            return array('message' => 'The selected batch is expired or does not have enough stock.');
        }
        $subtotal = round($quantity * (float) $batch['sale_rate'], 2);
        $tax_percentage = (float) $batch['tax'];
        $tax = round($subtotal * $tax_percentage / 100, 2);
        $this->db->insert('pharmacy_bill_basic', array(
            'date' => date('Y-m-d H:i:s'), 'patient_id' => $item['patient_id'], 'case_reference_id' => $item['case_reference_id'],
            'customer_name' => $item['patient_name'], 'customer_type' => 'patient', 'doctor_name' => trim($item['doctor_name'] . ' ' . $item['doctor_surname']),
            'file' => '', 'total' => $subtotal, 'discount_percentage' => 0, 'discount' => 0, 'tax_percentage' => $tax_percentage,
            'tax' => $tax, 'net_amount' => $subtotal + $tax, 'note' => 'Generated from IPD indent #' . $item['indent_id'],
            'organisation_id' => $item['organisation_id'] ?: null, 'generated_by' => $staff_id,
        ));
        $bill_id = $this->db->insert_id();
        $this->db->insert('pharmacy_bill_detail', array(
            'pharmacy_bill_basic_id' => $bill_id, 'medicine_batch_detail_id' => $batch_id, 'quantity' => $quantity,
            'sale_price' => $batch['sale_rate'], 'discount' => 0, 'amount' => $subtotal + $tax,
        ));
        $detail_id = $this->db->insert_id();
        $this->db->set('available_quantity', 'available_quantity - ' . $this->db->escape($quantity), false)
            ->where('id', (int) $batch_id)->where('available_quantity >=', $quantity)->update('medicine_batch_details');
        return $this->db->affected_rows() === 1
            ? array('billing_id' => $bill_id, 'billing_detail_id' => $detail_id)
            : array('message' => 'Stock changed while accepting the indent. Please try again.');
    }

    private function createTestBill($item, $type, $staff_id)
    {
        $master = $type === 'pathology' ? 'pathology' : 'radio';
        $test = $this->db->select($master . '.id, charges.standard_charge, tax_category.percentage as tax_percentage')
            ->from($master)->join('charges', 'charges.id = ' . $master . '.charge_id', 'left')
            ->join('tax_category', 'tax_category.id = charges.tax_category_id', 'left')
            ->where($master . '.id', (int) $item['item_id'])->get()->row_array();
        if (empty($test)) {
            return array('message' => 'The selected test is no longer available.');
        }
        $amount = (float) $test['standard_charge'];
        $tax_percentage = (float) $test['tax_percentage'];
        $tax = round($amount * $tax_percentage / 100, 2);
        $billing_table = $type === 'pathology' ? 'pathology_billing' : 'radiology_billing';
        $this->db->insert($billing_table, array(
            'date' => date('Y-m-d H:i:s'), 'patient_id' => $item['patient_id'], 'doctor_id' => $item['cons_doctor'],
            'doctor_name' => trim($item['doctor_name'] . ' ' . $item['doctor_surname']), 'case_reference_id' => $item['case_reference_id'],
            'total' => $amount, 'discount_percentage' => 0, 'discount' => 0, 'tax_percentage' => $tax_percentage,
            'tax' => $tax, 'net_amount' => $amount + $tax, 'note' => 'Generated from IPD indent #' . $item['indent_id'],
            'organisation_id' => $item['organisation_id'] ?: null, 'generated_by' => $staff_id,
        ));
        $bill_id = $this->db->insert_id();
        $report_table = $type === 'pathology' ? 'pathology_report' : 'radiology_report';
        $report = array(
            ($type === 'pathology' ? 'pathology_bill_id' : 'radiology_bill_id') => $bill_id,
            ($type === 'pathology' ? 'pathology_id' : 'radiology_id') => $item['item_id'],
            'patient_id' => $item['patient_id'], 'reporting_date' => date('Y-m-d'), 'apply_charge' => $amount,
            'tax_percentage' => $tax_percentage, 'description' => trim($item['clinical_indication'] . "\n" . $item['note']),
        );
        if ($type === 'radiology') {
            $report['consultant_doctor'] = (string) $item['cons_doctor'];
            $report['radiology_center'] = '';
            $report['generated_by'] = $staff_id;
        }
        $this->db->insert($report_table, $report);
        return array('billing_id' => $bill_id, 'billing_detail_id' => $this->db->insert_id());
    }

    private function createIpdCharge($item)
    {
        $charge = $this->db->select('charges.standard_charge, tax_category.percentage as tax_percentage')
            ->from('charges')->join('tax_category', 'tax_category.id = charges.tax_category_id', 'left')
            ->where('charges.id', (int) $item['item_id'])->get()->row_array();
        if (empty($charge)) {
            return array('message' => 'The selected charge is no longer available.');
        }
        $amount = (float) $charge['standard_charge'];
        $tax_percentage = (float) $charge['tax_percentage'];
        $tax = round($amount * $tax_percentage / 100, 2);
        $this->db->insert('patient_charges', array(
            'date' => date('Y-m-d H:i:s'), 'ipd_id' => $item['ipd_id'], 'qty' => 1, 'charge_id' => $item['item_id'],
            'standard_charge' => $amount, 'tpa_charge' => 0, 'discount_percentage' => 0, 'tax' => $tax_percentage,
            'apply_charge' => $amount, 'amount' => $amount + $tax,
            'note' => 'Generated from ' . ucfirst($item['item_type']) . ' IPD indent #' . $item['indent_id'],
            'organisation_id' => $item['organisation_id'] ?: null,
        ));
        $charge_id = $this->db->insert_id();
        return array('billing_id' => $charge_id, 'billing_detail_id' => $charge_id);
    }

    /**
     * Accepted indent fulfilments for a case, mapped to billing module + id.
     * Used by Service Posting to flag auto-added department lines.
     */
    public function getAcceptedBillingLinesByCaseId($case_id)
    {
        $case_id = (int) $case_id;
        if ($case_id < 1) {
            return array();
        }

        if (!$this->db->table_exists('ipd_indent_items')) {
            return array();
        }

        $rows = $this->db->select('ipd_indent_items.id as indent_item_id, ipd_indent_items.item_name, ipd_indent_items.item_type, ipd_indent_items.billing_type, ipd_indent_items.billing_id, ipd_indent_items.billing_detail_id, ipd_indent_items.status, ipd_indent_items.accepted_at, ipd_indent_items.note, ipd_indent.ipd_id, ipd_details.case_reference_id')
            ->from('ipd_indent_items')
            ->join('ipd_indent', 'ipd_indent.id = ipd_indent_items.indent_id')
            ->join('ipd_details', 'ipd_details.id = ipd_indent.ipd_id')
            ->where('ipd_details.case_reference_id', $case_id)
            ->where_in('ipd_indent_items.status', array('accepted', 'partially_accepted'))
            ->where('ipd_indent_items.billing_id IS NOT NULL', null, false)
            ->where('ipd_indent_items.billing_id >', 0)
            ->order_by('ipd_indent_items.accepted_at', 'asc')
            ->get()
            ->result_array();

        $lines = array();
        foreach ($rows as $row) {
            $billing_type = $row['billing_type'];
            $module = $billing_type;
            if ($billing_type === 'medicine' || $billing_type === 'pharmacy') {
                $module = 'pharmacy';
            } elseif ($billing_type === 'ipd_charge') {
                $module = 'ipd';
            }

            $lines[] = array(
                'module'          => $module,
                'module_id'       => (int) $row['billing_id'],
                'service_code'    => $module === 'pharmacy' ? 'Pharmacy' : ($module === 'pathology' ? 'Pathology' : ($module === 'radiology' ? 'Radiology' : 'IPD')),
                'service_name'    => $row['item_name'],
                'indent_item_id'  => (int) $row['indent_item_id'],
                'from_indent'     => true,
                'accepted_at'     => $row['accepted_at'],
            );
        }

        return $lines;
    }
}
