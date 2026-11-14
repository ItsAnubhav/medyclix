<?php
$currency_symbol = $this->customlib->getHospitalCurrencyFormat();
$patient = isset($patient) ? $patient : array();
$case_id = isset($case_id) ? $case_id : '';
$charge_details = isset($charge_details) ? $charge_details : array();
$pharmacy_medicines = isset($pharmacy_medicines) ? $pharmacy_medicines : array();
$pathology_tests = isset($pathology_tests) ? $pathology_tests : array();
$paid_amount = isset($paid_amount) ? $paid_amount : array('total_pay' => 0);
$transaction_data = isset($transaction_data) ? $transaction_data : array();
$refund = isset($refund) ? (float) $refund : 0;
$advance_amount = isset($advance_amount) ? (float) $advance_amount : 0;
$hospital = isset($hospital) ? $hospital : null;
$printed_by = isset($printed_by) ? $printed_by : '';

$uhid = !empty($patient['patient_unique_id']) ? $patient['patient_unique_id'] : '-';
$bill_title = !empty($bill_title) ? $bill_title : $this->lang->line('inpatient_bill');
$is_ipd_bill = isset($is_ipd_bill) ? (bool) $is_ipd_bill : (!empty($patient['ipdid']) && (int) $patient['ipdid'] > 0);
$bill_no_prefix = $is_ipd_bill
    ? $this->customlib->getSessionPrefixByType('ipd_no')
    : $this->customlib->getSessionPrefixByType('opd_no');
$bill_no = $bill_no_prefix . 'BILL-' . $case_id;
if ($is_ipd_bill && !empty($patient['ipdid'])) {
    $ip_no = $this->customlib->getSessionPrefixByType('ipd_no') . $patient['ipdid'];
} else {
    $ip_no = '-';
}

$doctor_display = '-';
if (!empty($patient['doctor_name'])) {
    $doctor_display = composeStaffNameByString(
        $patient['doctor_name'],
        isset($patient['doctor_surname']) ? $patient['doctor_surname'] : '',
        isset($patient['doctor_employee_id']) ? $patient['doctor_employee_id'] : ''
    );
}

$age_display = $this->customlib->get_patient_current_age(isset($patient['patient_id']) ? $patient['patient_id'] : 0);
$gender_display = !empty($patient['gender']) ? $this->lang->line(strtolower($patient['gender'])) : '-';
$tariff = !empty($patient['organisation_name']) ? $patient['organisation_name'] : 'Hospital';
$patient_category = !empty($patient['patient_type']) ? $patient['patient_type'] : (!empty($patient['case_type']) ? $patient['case_type'] : 'PAYING');
$bed_category = !empty($patient['bedgroup_name']) ? $patient['bedgroup_name'] : '-';

// Group charges by category
$grouped = array();
foreach ($charge_details as $row) {
    $cat = !empty($row['charge_category_name']) ? $row['charge_category_name'] : 'Others';
    if (!isset($grouped[$cat])) {
        $grouped[$cat] = array();
    }
    $grouped[$cat][] = $row;
}

// Add pharmacy medicines as their own category (medicine names)
if (!empty($pharmacy_medicines)) {
    $pharm_rows = array();
    foreach ($pharmacy_medicines as $med) {
        $qty = (float) $med['quantity'];
        $rate = (float) $med['sale_price'];
        $line_amt = isset($med['amount']) && $med['amount'] !== null && $med['amount'] !== ''
            ? (float) $med['amount']
            : ($qty * $rate);
        $tax_pct = !empty($med['batch_tax']) ? (float) $med['batch_tax'] : 0;
        $pharm_rows[] = array(
            'date' => $med['bill_date'],
            'charge_name' => !empty($med['medicine_name']) ? $med['medicine_name'] : ('Pharmacy #' . $med['bill_id']),
            'qty' => $qty,
            'apply_charge' => $line_amt,
            'discount_percentage' => 0,
            'tax' => $tax_pct,
            'amount' => $line_amt,
            'note' => '',
            'hsn' => '-',
        );
    }
    if (!empty($pharm_rows)) {
        $grouped['Pharmacy / Medicines'] = $pharm_rows;
    }
}

// Add pathology tests as their own category (test/service names, not bill numbers)
if (!empty($pathology_tests)) {
    $path_rows = array();
    foreach ($pathology_tests as $test) {
        $apply = (float) $test['apply_charge'];
        $disc_pct = !empty($test['discount_percentage']) ? (float) $test['discount_percentage'] : 0;
        $tax_pct = !empty($test['tax_percentage']) ? (float) $test['tax_percentage'] : 0;
        $discount_amt = ($apply * $disc_pct) / 100;
        $tax_amt = (($apply - $discount_amt) * $tax_pct) / 100;
        $line_amt = $apply - $discount_amt + $tax_amt;
        $path_rows[] = array(
            'date' => $test['bill_date'],
            'charge_name' => !empty($test['test_name']) ? $test['test_name'] : ('Pathology #' . $test['bill_id']),
            'qty' => 1,
            'apply_charge' => $apply,
            'discount_percentage' => $disc_pct,
            'tax' => $tax_pct,
            'amount' => $line_amt,
            'note' => '',
            'hsn' => '-',
        );
    }
    if (!empty($path_rows)) {
        $grouped['Pathology'] = $path_rows;
    }
}

$total_amount = 0;
$total_tax = 0;
$total_discount = 0;
$transaction_prefix = $this->customlib->getSessionPrefixByType('transaction_id');
$net_payments = 0;
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; padding-top: 8mm; padding-bottom: 10mm; }
.letterhead-top { height: 18mm; }
.letterhead-bottom { height: 12mm; }
.title { text-align: center; font-size: 13px; font-weight: bold; margin: 6px 0 0; text-transform: uppercase; letter-spacing: .5px; }
.subtitle { text-align: center; font-size: 11px; font-weight: bold; margin: 2px 0 10px; text-transform: uppercase; }
.meta-table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
.meta-table td { padding: 2px 3px; vertical-align: top; font-size: 10px; }
.meta-label { font-weight: bold; width: 28%; }
.meta-sep { width: 2%; }
.section-title { font-size: 11px; font-weight: bold; margin: 10px 0 4px; text-transform: uppercase; border-bottom: 1px solid #333; padding-bottom: 2px; }
.charges { width: 100%; border-collapse: collapse; margin-top: 2px; }
.charges th, .charges td { border: 1px solid #888; padding: 3px 4px; font-size: 9px; }
.charges th { background: #e8e8e8; text-align: left; font-weight: bold; }
.cat-title td { background: #f0f0f0; font-weight: bold; font-size: 10px; }
.subtotal td { font-weight: bold; background: #fafafa; }
.text-right { text-align: right; }
.summary-wrap { width: 100%; margin-top: 12px; }
.summary-table { width: 45%; float: right; border-collapse: collapse; }
.summary-table td { padding: 3px 6px; border: 1px solid #888; font-size: 10px; }
.summary-table td.label { font-weight: bold; width: 65%; }
.section-clear { clear: both; }
.footer-meta { margin-top: 18px; font-size: 9px; }
.sign-table { width: 100%; margin-top: 24px; border-collapse: collapse; }
.sign-table td { width: 33%; text-align: center; vertical-align: top; font-size: 9px; }
.sign-line { border-top: 1px solid #333; margin-top: 28px; padding-top: 4px; }
</style>
</head>
<body>
<div class="letterhead-top"></div>

<div class="title"><?php echo html_escape($bill_title); ?></div>
<?php if ($is_ipd_bill) { ?>
<div class="subtitle"><?php echo $this->lang->line('final_bill'); ?></div>
<?php } ?>

<table class="meta-table">
<tr>
    <td class="meta-label"><?php echo $this->lang->line('bill_no'); ?></td>
    <td>: <?php echo html_escape($bill_no); ?></td>
    <td class="meta-sep"></td>
    <td class="meta-label"><?php echo $this->lang->line('date'); ?></td>
    <td>: <?php echo $this->customlib->YYYYMMDDHisTodateFormat(date('Y-m-d H:i:s'), $this->customlib->getHospitalTimeFormat()); ?></td>
</tr>
<tr>
    <td class="meta-label"><?php echo $this->lang->line('patient_name'); ?></td>
    <td>: <?php echo html_escape(composePatientName(isset($patient['patient_name']) ? $patient['patient_name'] : '', isset($patient['patient_id']) ? $patient['patient_id'] : '')); ?></td>
    <td class="meta-sep"></td>
    <td class="meta-label"><?php echo $this->lang->line('age') . ' / ' . $this->lang->line('gender'); ?></td>
    <td>: <?php echo html_escape($age_display . ' / ' . $gender_display); ?></td>
</tr>
<?php if ($is_ipd_bill) { ?>
<tr>
    <td class="meta-label"><?php echo $this->lang->line('ip_number'); ?></td>
    <td>: <?php echo html_escape($ip_no); ?></td>
    <td class="meta-sep"></td>
    <td class="meta-label"><?php echo $this->lang->line('uhid'); ?></td>
    <td>: <?php echo html_escape($uhid); ?></td>
</tr>
<tr>
    <td class="meta-label"><?php echo $this->lang->line('patient_type'); ?></td>
    <td>: <?php echo html_escape($patient_category); ?></td>
    <td class="meta-sep"></td>
    <td class="meta-label"><?php echo $this->lang->line('tpa'); ?></td>
    <td>: <?php echo html_escape($tariff); ?><?php if (!empty($patient['insurance_id'])) { echo ' / ' . html_escape($patient['insurance_id']); } ?></td>
</tr>
<tr>
    <td class="meta-label"><?php echo $this->lang->line('bed'); ?></td>
    <td>: <?php echo html_escape($bed_category); ?><?php if (!empty($patient['bed_name'])) { echo ' / ' . html_escape($patient['bed_name']); } ?></td>
    <td class="meta-sep"></td>
    <td class="meta-label"><?php echo $this->lang->line('consultant_doctor'); ?></td>
    <td>: <?php echo html_escape($doctor_display); ?></td>
</tr>
<tr>
    <td class="meta-label"><?php echo $this->lang->line('department'); ?></td>
    <td>: <?php echo html_escape(!empty($patient['department_name']) ? $patient['department_name'] : '-'); ?></td>
    <td class="meta-sep"></td>
    <td class="meta-label"><?php echo $this->lang->line('mobile'); ?></td>
    <td>: <?php echo html_escape(!empty($patient['mobileno']) ? $patient['mobileno'] : '-'); ?></td>
</tr>
<tr>
    <td class="meta-label"><?php echo $this->lang->line('admission_date'); ?></td>
    <td>: <?php echo (!empty($patient['date']) && $patient['date'] != '0000-00-00') ? $this->customlib->YYYYMMDDHisTodateFormat($patient['date'], $this->customlib->getHospitalTimeFormat()) : '-'; ?></td>
    <td class="meta-sep"></td>
    <td class="meta-label"><?php echo $this->lang->line('discharge_date'); ?></td>
    <td>: <?php echo (!empty($patient['discharge_date']) && $patient['discharge_date'] != '0000-00-00' && $patient['discharge_date'] != '0000-00-00 00:00:00') ? $this->customlib->YYYYMMDDHisTodateFormat($patient['discharge_date'], $this->customlib->getHospitalTimeFormat()) : '-'; ?></td>
</tr>
<tr>
    <td class="meta-label"><?php echo $this->lang->line('address'); ?></td>
    <td colspan="4">: <?php echo html_escape(!empty($patient['address']) ? $patient['address'] : '-'); ?></td>
</tr>
<?php } else { ?>
<tr>
    <td class="meta-label"><?php echo $this->lang->line('uhid'); ?></td>
    <td>: <?php echo html_escape($uhid); ?></td>
    <td class="meta-sep"></td>
    <td class="meta-label"><?php echo $this->lang->line('tpa'); ?></td>
    <td>: <?php echo html_escape($tariff); ?><?php if (!empty($patient['insurance_id'])) { echo ' / ' . html_escape($patient['insurance_id']); } ?></td>
</tr>
<tr>
    <td class="meta-label"><?php echo $this->lang->line('consultant_doctor'); ?></td>
    <td>: <?php echo html_escape($doctor_display); ?></td>
    <td class="meta-sep"></td>
    <td class="meta-label"><?php echo $this->lang->line('department'); ?></td>
    <td>: <?php echo html_escape(!empty($patient['department_name']) ? $patient['department_name'] : '-'); ?></td>
</tr>
<tr>
    <td class="meta-label"><?php echo $this->lang->line('mobile'); ?></td>
    <td>: <?php echo html_escape(!empty($patient['mobileno']) ? $patient['mobileno'] : '-'); ?></td>
    <td class="meta-sep"></td>
    <td class="meta-label"><?php echo $this->lang->line('address'); ?></td>
    <td>: <?php echo html_escape(!empty($patient['address']) ? $patient['address'] : '-'); ?></td>
</tr>
<?php } ?>
</table>

<div class="section-title"><?php echo $this->lang->line('service'); ?> Details</div>
<table class="charges">
<thead>
<tr>
    <th style="width:12%"><?php echo $this->lang->line('date'); ?></th>
    <th>Head</th>
    <th style="width:8%"><?php echo $this->lang->line('qty'); ?></th>
    <th style="width:10%" class="text-right"><?php echo $this->lang->line('rate'); ?></th>
    <th style="width:8%" class="text-right">GST %</th>
    <th style="width:9%" class="text-right">GST Amt</th>
    <th style="width:10%">HSN/SAC</th>
    <th style="width:12%" class="text-right"><?php echo $this->lang->line('amount'); ?></th>
</tr>
</thead>
<tbody>
<?php
if (empty($grouped)) {
    echo '<tr><td colspan="8" style="text-align:center">' . $this->lang->line('no_record_found') . '</td></tr>';
} else {
    foreach ($grouped as $category => $rows) {
        $subtotal = 0;
        echo '<tr class="cat-title"><td colspan="8">' . html_escape($category) . '</td></tr>';
        foreach ($rows as $value) {
            $discount_amount = (($value['apply_charge'] * $value['discount_percentage']) / 100);
            $tax_amount = (($value['apply_charge'] - $discount_amount) * $value['tax'] / 100);
            $qty = (float) $value['qty'];
            $rate = $qty > 0 ? ((float) $value['apply_charge'] / $qty) : (float) $value['apply_charge'];
            $amount = (float) $value['amount'];
            $subtotal += $amount;
            $total_amount += $amount;
            $total_tax += $tax_amount;
            $total_discount += $discount_amount;
            $serv_date = (!empty($value['date']) && $value['date'] != '0000-00-00') ? $this->customlib->YYYYMMDDTodateFormat($value['date']) : '-';
            $hsn = !empty($value['hsn']) ? $value['hsn'] : '-';
            $tax_pct_show = ((float) $value['tax'] > 0) ? amountFormat($value['tax']) : '';
            $tax_amt_show = ($tax_amount > 0) ? amountFormat($tax_amount) : '';
            ?>
            <tr>
                <td><?php echo $serv_date; ?></td>
                <td><?php echo html_escape($value['charge_name']); ?><?php if (!empty($value['note'])) { echo '<br><small>' . html_escape($value['note']) . '</small>'; } ?></td>
                <td class="text-right"><?php echo amountFormat($qty); ?></td>
                <td class="text-right"><?php echo amountFormat($rate); ?></td>
                <td class="text-right"><?php echo $tax_pct_show; ?></td>
                <td class="text-right"><?php echo $tax_amt_show; ?></td>
                <td><?php echo html_escape($hsn); ?></td>
                <td class="text-right"><?php echo amountFormat($amount); ?></td>
            </tr>
            <?php
        }
        ?>
        <tr class="subtotal">
            <td colspan="7" class="text-right">Totals:</td>
            <td class="text-right"><?php echo amountFormat($subtotal); ?></td>
        </tr>
        <?php
    }
}
?>
</tbody>
</table>

<?php if (!empty($transaction_data)) { ?>
<div class="section-title"><?php echo $this->lang->line('payment_details'); ?></div>
<table class="charges">
<thead>
<tr>
    <th>Receipt Number</th>
    <th><?php echo $this->lang->line('date'); ?></th>
    <th><?php echo $this->lang->line('payment_type'); ?></th>
    <th><?php echo $this->lang->line('payment_mode'); ?></th>
    <th class="text-right"><?php echo $this->lang->line('amount'); ?></th>
    <th class="text-right"><?php echo $this->lang->line('refund'); ?></th>
    <th class="text-right">Net</th>
</tr>
</thead>
<tbody>
<?php foreach ($transaction_data as $txn) {
    $mode_key = $txn->payment_mode ? strtolower($txn->payment_mode) : '';
    $mode_label = $mode_key ? $this->lang->line($mode_key) : '-';
    if ($mode_label === $mode_key || $mode_label === false) {
        $mode_label = $txn->payment_mode;
    }
    $txn_amt = (float) $txn->amount;
    $is_refund = (isset($txn->type) && strtolower($txn->type) === 'refund');
    $pay_amt = $is_refund ? 0 : $txn_amt;
    $ref_amt = $is_refund ? $txn_amt : 0;
    $net = $pay_amt - $ref_amt;
    $net_payments += $net;
    $type_label = !empty($txn->amount_type) && $txn->amount_type === 'advance'
        ? $this->lang->line('advance')
        : ($is_refund ? $this->lang->line('refund') : $this->lang->line('payment'));
    ?>
<tr>
    <td><?php echo html_escape($transaction_prefix . $txn->id); ?></td>
    <td><?php echo $this->customlib->YYYYMMDDHisTodateFormat($txn->payment_date, $this->customlib->getHospitalTimeFormat()); ?></td>
    <td><?php echo html_escape($type_label); ?></td>
    <td><?php echo html_escape($mode_label); ?></td>
    <td class="text-right"><?php echo amountFormat($pay_amt); ?></td>
    <td class="text-right"><?php echo amountFormat($ref_amt); ?></td>
    <td class="text-right"><?php echo amountFormat($net); ?></td>
</tr>
<?php } ?>
<tr class="subtotal">
    <td colspan="6" class="text-right">Net Payments</td>
    <td class="text-right"><?php echo amountFormat($net_payments); ?></td>
</tr>
</tbody>
</table>
<?php } ?>

<?php
$overall_discount = isset($overall_discount) ? $overall_discount : array('amount' => 0, 'percent' => 0);
$overall_disc_amt = !empty($overall_discount['amount']) ? (float) $overall_discount['amount'] : 0;
$paid = isset($paid_amount['total_pay']) ? (float) $paid_amount['total_pay'] : 0;
if ($net_payments > 0) {
    $deposited = $net_payments;
} else {
    $deposited = $paid + $advance_amount;
}
$net_due = $total_amount - $total_discount - $overall_disc_amt - $deposited + $refund;
?>

<div class="section-title"><?php echo $this->lang->line('bill_summary'); ?></div>
<div class="summary-wrap">
<table class="summary-table">
<tr>
    <td class="label">Total Bill Amount</td>
    <td class="text-right"><?php echo amountFormat($total_amount); ?></td>
</tr>
<tr>
    <td class="label">Less Discount</td>
    <td class="text-right"><?php echo amountFormat($total_discount); ?></td>
</tr>
<?php if ($overall_disc_amt > 0) { ?>
<tr>
    <td class="label">Less Bill Discount<?php if (!empty($overall_discount['percent'])) { echo ' (' . amountFormat($overall_discount['percent']) . '%)'; } ?></td>
    <td class="text-right"><?php echo amountFormat($overall_disc_amt); ?></td>
</tr>
<?php } ?>
<tr>
    <td class="label">Deposited Amount</td>
    <td class="text-right"><?php echo amountFormat($deposited); ?></td>
</tr>
<tr>
    <td class="label">Refund Amount</td>
    <td class="text-right"><?php echo amountFormat($refund); ?></td>
</tr>
<tr>
    <td class="label"><strong>Net Amount</strong></td>
    <td class="text-right"><strong><?php echo amountFormat(max(0, $net_due)); ?></strong></td>
</tr>
</table>
</div>
<div class="section-clear"></div>

<?php include(APPPATH . 'views/admin/shared/_bill_print_close.php'); ?>
<div class="letterhead-bottom"></div>
</body>
</html>
