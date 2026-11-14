<?php
$currency_symbol = $this->customlib->getHospitalCurrencyFormat();
include(APPPATH . 'views/admin/shared/_print_css.php');

$patient = isset($patient) ? $patient : array();
$pharmacy_medicines = isset($pharmacy_medicines) ? $pharmacy_medicines : array();
$pathology_tests = isset($pathology_tests) ? $pathology_tests : array();

$doctor_display = '-';
if (!empty($patient['doctor_name'])) {
    $doctor_display = composeStaffNameByString(
        $patient['doctor_name'],
        isset($patient['doctor_surname']) ? $patient['doctor_surname'] : '',
        isset($patient['doctor_employee_id']) ? $patient['doctor_employee_id'] : ''
    );
}

$age_display = !empty($patient['patient_id']) ? $this->customlib->get_patient_current_age($patient['patient_id']) : '-';
$gender_display = !empty($patient['gender']) ? $this->lang->line(strtolower($patient['gender'])) : '-';
$uhid = !empty($patient['patient_unique_id']) ? $patient['patient_unique_id'] : (!empty($patient['patient_id']) ? $patient['patient_id'] : '-');
$ip_no = (!empty($patient['ipdid']) && $patient['ipdid'] != 0) ? ($this->customlib->getSessionPrefixByType('ipd_no') . $patient['ipdid']) : '-';
$bed_category = !empty($patient['bedgroup_name']) ? $patient['bedgroup_name'] : '-';
$bed_display = trim((isset($patient['bed_name']) ? $patient['bed_name'] : '') . (!empty($patient['bedgroup_name']) ? ' - ' . $patient['bedgroup_name'] : '') . (!empty($patient['floor_name']) ? ' - ' . $patient['floor_name'] : ''), ' -');
$patient_category = !empty($patient['patient_type']) ? $patient['patient_type'] : (!empty($patient['case_type']) ? $patient['case_type'] : 'PAYING');
$tariff = !empty($patient['organisation_name']) ? $patient['organisation_name'] : 'Hospital';
$admit_date = (!empty($patient['date']) && $patient['date'] != '0000-00-00')
    ? $this->customlib->YYYYMMDDHisTodateFormat($patient['date'], $this->customlib->getHospitalTimeFormat())
    : ((!empty($patient['appointment_date']) && $patient['appointment_date'] != '0000-00-00')
        ? $this->customlib->YYYYMMDDHisTodateFormat($patient['appointment_date'], $this->customlib->getHospitalTimeFormat())
        : '-');
$discharge_date = (!empty($patient['discharge_date']) && $patient['discharge_date'] != '0000-00-00' && $patient['discharge_date'] != '0000-00-00 00:00:00')
    ? $this->customlib->YYYYMMDDHisTodateFormat($patient['discharge_date'], $this->customlib->getHospitalTimeFormat())
    : '-';
$bill_title = !empty($bill_title) ? $bill_title : $this->lang->line('inpatient_bill');
$is_ipd_bill = isset($is_ipd_bill) ? (bool) $is_ipd_bill : (!empty($patient['ipdid']) && (int) $patient['ipdid'] > 0);
$bill_no_prefix = $is_ipd_bill
    ? $this->customlib->getSessionPrefixByType('ipd_no')
    : $this->customlib->getSessionPrefixByType('opd_no');
$bill_no = $bill_no_prefix . 'BILL-' . $case_id;
?>

<style>
.patient-bill-letterhead .header-space { height: 130px; }
.patient-bill-letterhead .footer-space { height: 85px; }
.patient-bill-letterhead .letterhead-top-spacer { height: 130px; }
.patient-bill-letterhead .letterhead-bottom-spacer { height: 85px; }
@media print {
    .patient-bill-letterhead .header-space { height: 130px !important; }
    .patient-bill-letterhead .footer-space { height: 85px !important; }
    @page { margin-top: 38mm; margin-bottom: 22mm; }
}
</style>

<div class="patient-bill-letterhead">
<div class="letterhead-top-spacer"></div>

<table class="table-print-full" width="100%">
    <thead>
        <tr><td><div class="header-space">&nbsp;</div></td></tr>
    </thead>
    <tbody>
        <tr><td>
            <div class="content-body sh-px-12" >
            <div class="print-area">

                <!-- ① Document title -->
                <div class="sh-print-title"><?php echo html_escape($bill_title); ?></div>
                <div class="text-center fw-bold mb-2" style="font-size:13px; text-transform:uppercase;"><?php echo $this->lang->line('final_bill'); ?></div>

                <!-- ② Patient info block — full details -->
                <div class="sh-print-info-block">
                    <table class="sh-print-info-table">
                        <colgroup>
                            <col style="width:16%"><col style="width:34%">
                            <col style="width:16%"><col style="width:34%">
                        </colgroup>
                        <tr>
                            <th><?php echo $this->lang->line('bill_no'); ?></th>
                            <td><?php echo html_escape($bill_no); ?></td>
                            <th><?php echo $this->lang->line('date'); ?></th>
                            <td><?php echo $this->customlib->YYYYMMDDHisTodateFormat(date('Y-m-d H:i:s'), $this->customlib->getHospitalTimeFormat()); ?></td>
                        </tr>
                        <tr>
                            <th><?php echo $this->lang->line('patient_name'); ?></th>
                            <td><?php echo ($patient['patient_name'] ? composePatientName($patient['patient_name'], $patient['patient_id']) : '-'); ?></td>
                            <th><?php echo $this->lang->line('age') . ' / ' . $this->lang->line('gender'); ?></th>
                            <td><?php echo html_escape($age_display . ' / ' . $gender_display); ?></td>
                        </tr>
                        <tr>
                            <th><?php echo $this->lang->line('ip_number'); ?></th>
                            <td>
                                <?php echo html_escape($ip_no); ?>
                                <?php if (!empty($patient['discharged']) && $patient['discharged'] == 'yes') { ?>
                                    <span class="sh-badge-warn"><?php echo $this->lang->line('discharged'); ?></span>
                                <?php } ?>
                            </td>
                            <th><?php echo $this->lang->line('uhid'); ?> / <?php echo $this->lang->line('mr_number'); ?></th>
                            <td><?php echo html_escape($uhid); ?></td>
                        </tr>
                        <tr>
                            <th><?php echo $this->lang->line('patient_type'); ?></th>
                            <td><?php echo html_escape($patient_category); ?></td>
                            <th><?php echo $this->lang->line('tpa'); ?></th>
                            <td><?php echo html_escape($tariff); ?><?php if (!empty($patient['insurance_id'])) { echo ' / ' . html_escape($patient['insurance_id']); } ?></td>
                        </tr>
                        <tr>
                            <th><?php echo $this->lang->line('bed'); ?></th>
                            <td><?php echo html_escape($bed_display ?: $bed_category); ?></td>
                            <th><?php echo $this->lang->line('consultant_doctor'); ?></th>
                            <td><?php echo html_escape($doctor_display); ?></td>
                        </tr>
                        <tr>
                            <th><?php echo $this->lang->line('department'); ?></th>
                            <td><?php echo html_escape(!empty($patient['department_name']) ? $patient['department_name'] : '-'); ?></td>
                            <th><?php echo $this->lang->line('mobile'); ?></th>
                            <td><?php echo html_escape(!empty($patient['mobileno']) ? $patient['mobileno'] : '-'); ?></td>
                        </tr>
                        <tr>
                            <th><?php echo $this->lang->line('admission_date'); ?></th>
                            <td><?php echo $admit_date; ?></td>
                            <th><?php echo $this->lang->line('discharge_date'); ?></th>
                            <td><?php echo $discharge_date; ?></td>
                        </tr>
                        <tr>
                            <th><?php echo $this->lang->line('case_id'); ?></th>
                            <td><?php echo ($case_id ?: '-'); ?></td>
                            <th><?php echo $this->lang->line('address'); ?></th>
                            <td><?php echo html_escape(!empty($patient['address']) ? $patient['address'] : '-'); ?></td>
                        </tr>
                        <?php if (!empty($patient['guardian_name'])) { ?>
                        <tr>
                            <th><?php echo $this->lang->line('guardian_name'); ?></th>
                            <td colspan="3"><?php echo html_escape($patient['guardian_name']); ?></td>
                        </tr>
                        <?php } ?>
                    </table>
                </div>

                <!-- Divider for clarity -->
                <div class="sh-section-divider"></div>

                <?php
                $total_amount = 0;
                $amount_paid  = 0;
                ?>

                <!-- ③ OPD Charges -->
                <?php if (!empty($opd_data)) { ?>
                <div class="sh-print-section-title"><?php echo $this->lang->line('opd_charges'); ?></div>
                <table class="sh-print-table">
                    <thead>
                        <tr>
                            <th><?php echo $this->lang->line('service'); ?></th>
                            <th style="width:16%"><?php echo $this->lang->line('charge') . ' (' . $currency_symbol . ')'; ?></th>
                            <th style="width:8%"><?php echo $this->lang->line('qty'); ?></th>
                            <th class="sh-col-18 sh-text-right"><?php echo $this->lang->line('discount') . ' (' . $currency_symbol . ')'; ?></th>
                            <th class="sh-col-16 sh-text-right"><?php echo $this->lang->line('tax') . ' (' . $currency_symbol . ')'; ?></th>
                            <th class="sh-col-16 sh-text-right"><?php echo $this->lang->line('amount') . ' (' . $currency_symbol . ')'; ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($opd_data as $opd_key => $opd_value) {
                            $total_amount += (float)$opd_value['amount'];
                            $opd_discount = (float)$opd_value['apply_charge'] * ((float)$opd_value['discount_percentage'] / 100);
                            $opd_tax_base = (float)$opd_value['apply_charge'] - $opd_discount;
                            $opd_tax      = ($opd_tax_base * (float)$opd_value['tax']) / 100;
                        ?>
                        <tr>
                            <td class="fw-bold"><?php echo html_escape($opd_value['name']); ?></td>
                            <td><?php echo $currency_symbol . amountFormat((float)$opd_value['apply_charge']); ?></td>
                            <td><?php echo html_escape($opd_value['qty'] . ' ' . $opd_value['unit']); ?></td>
                            <td class="sh-text-right"><?php echo $currency_symbol . amountFormat($opd_discount) . ' (' . $opd_value['discount_percentage'] . '%)'; ?></td>
                            <td class="sh-text-right"><?php echo $currency_symbol . amountFormat($opd_tax) . ' (' . $opd_value['tax'] . '%)'; ?></td>
                            <td class="sh-text-right fw-bold"><?php echo $currency_symbol . amountFormat((float)$opd_value['amount']); ?></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
                <?php } ?>

                <!-- ④ IPD Charges -->
                <?php if (!empty($ipd_data)) { ?>
                <div class="sh-print-section-title"><?php echo $this->lang->line('ipd_charges'); ?></div>
                <table class="sh-print-table">
                    <thead>
                        <tr>
                            <th><?php echo $this->lang->line('service'); ?></th>
                            <th style="width:16%"><?php echo $this->lang->line('charge') . ' (' . $currency_symbol . ')'; ?></th>
                            <th style="width:8%"><?php echo $this->lang->line('qty'); ?></th>
                            <th class="sh-col-18 sh-text-right"><?php echo $this->lang->line('discount') . ' (' . $currency_symbol . ')'; ?></th>
                            <th class="sh-col-16 sh-text-right"><?php echo $this->lang->line('tax') . ' (' . $currency_symbol . ')'; ?></th>
                            <th class="sh-col-16 sh-text-right"><?php echo $this->lang->line('amount') . ' (' . $currency_symbol . ')'; ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ipd_data as $ipd_key => $ipd_value) {
                            $total_amount += (float)$ipd_value['amount'];
                            $ipd_discount = ((float)$ipd_value['apply_charge'] * (float)$ipd_value['discount_percentage']) / 100;
                            $ipd_tax_base = (float)$ipd_value['apply_charge'] - $ipd_discount;
                            $ipd_tax      = ($ipd_tax_base * (float)$ipd_value['tax']) / 100;
                        ?>
                        <tr>
                            <td class="fw-bold"><?php echo html_escape($ipd_value['name']); ?></td>
                            <td><?php echo $currency_symbol . amountFormat((float)$ipd_value['apply_charge']); ?></td>
                            <td><?php echo html_escape($ipd_value['qty'] . ' ' . $ipd_value['unit']); ?></td>
                            <td class="sh-text-right"><?php echo $currency_symbol . amountFormat($ipd_discount) . ' (' . $ipd_value['discount_percentage'] . '%)'; ?></td>
                            <td class="sh-text-right"><?php echo $currency_symbol . amountFormat($ipd_tax) . ' (' . $ipd_value['tax'] . '%)'; ?></td>
                            <td class="sh-text-right fw-bold"><?php echo $currency_symbol . amountFormat((float)$ipd_value['amount']); ?></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
                <?php } ?>

                <!-- ⑤ Pharmacy Medicines (medicine names, not bill numbers) -->
                <?php if (!empty($pharmacy_medicines)) { ?>
                <div class="sh-print-section-title"><?php echo $this->lang->line('pharmacy_bill'); ?></div>
                <table class="sh-print-table">
                    <thead>
                        <tr>
                            <th style="width:12%"><?php echo $this->lang->line('date'); ?></th>
                            <th><?php echo $this->lang->line('medicine_name'); ?></th>
                            <th style="width:8%"><?php echo $this->lang->line('qty'); ?></th>
                            <th class="sh-col-16 sh-text-right"><?php echo $this->lang->line('rate') . ' (' . $currency_symbol . ')'; ?></th>
                            <th class="sh-col-16 sh-text-right"><?php echo $this->lang->line('amount') . ' (' . $currency_symbol . ')'; ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $pharm_section_total = 0;
                        foreach ($pharmacy_medicines as $med) {
                            $qty = (float) $med['quantity'];
                            $rate = (float) $med['sale_price'];
                            $line_amt = isset($med['amount']) && $med['amount'] !== null && $med['amount'] !== ''
                                ? (float) $med['amount']
                                : ($qty * $rate);
                            $pharm_section_total += $line_amt;
                            $total_amount += $line_amt;
                            $med_date = (!empty($med['bill_date']) && $med['bill_date'] != '0000-00-00')
                                ? $this->customlib->YYYYMMDDTodateFormat($med['bill_date'])
                                : '-';
                            $med_name = !empty($med['medicine_name']) ? $med['medicine_name'] : ('Pharmacy #' . $med['bill_id']);
                        ?>
                        <tr>
                            <td><?php echo $med_date; ?></td>
                            <td class="fw-bold"><?php echo html_escape($med_name); ?></td>
                            <td><?php echo html_escape($qty); ?></td>
                            <td class="sh-text-right"><?php echo $currency_symbol . amountFormat($rate); ?></td>
                            <td class="sh-text-right fw-bold"><?php echo $currency_symbol . amountFormat($line_amt); ?></td>
                        </tr>
                        <?php } ?>
                        <tr>
                            <td colspan="4" class="sh-text-right fw-medium"><?php echo $this->lang->line('total'); ?></td>
                            <td class="sh-text-right fw-bold"><?php echo $currency_symbol . amountFormat($pharm_section_total); ?></td>
                        </tr>
                    </tbody>
                </table>
                <?php } elseif (!empty($pharmacy_data)) { ?>
                <div class="sh-print-section-title"><?php echo $this->lang->line('pharmacy_bill'); ?></div>
                <table class="sh-print-table">
                    <thead>
                        <tr>
                            <th><?php echo $this->lang->line('bill_no'); ?></th>
                            <th class="sh-col-16 sh-text-right"><?php echo $this->lang->line('charge') . ' (' . $currency_symbol . ')'; ?></th>
                            <th class="sh-col-18 sh-text-right"><?php echo $this->lang->line('discount') . ' (' . $currency_symbol . ')'; ?></th>
                            <th class="sh-col-16 sh-text-right"><?php echo $this->lang->line('tax') . ' (' . $currency_symbol . ')'; ?></th>
                            <th class="sh-col-12 sh-text-right"><?php echo $this->lang->line('refund') . ' (' . $currency_symbol . ')'; ?></th>
                            <th class="sh-col-16 sh-text-right"><?php echo $this->lang->line('amount') . ' (' . $currency_symbol . ')'; ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pharmacy_data as $pharmacy_key => $pharmacy_value) {
                            $total_amount += (float)$pharmacy_value->net_amount;
                            $pharm_total    = (float)$pharmacy_value->total;
                            $pharm_discount = (float)$pharmacy_value->discount;
                            $pharm_tax_base = $pharm_total - $pharm_discount;
                            $pharm_tax_pct  = ($pharm_tax_base > 0) ? (((float)$pharmacy_value->tax * 100) / $pharm_tax_base) : 0;
                        ?>
                        <tr>
                            <td class="fw-bold"><?php echo $pharmacy_bill_prefix . $pharmacy_value->id; ?></td>
                            <td class="sh-text-right"><?php echo $currency_symbol . amountFormat($pharm_total); ?></td>
                            <td class="sh-text-right"><?php echo $currency_symbol . amountFormat($pharm_discount) . ' (' . amountFormat((float)$pharmacy_value->discount_percentage) . '%)'; ?></td>
                            <td class="sh-text-right"><?php echo $currency_symbol . amountFormat((float)$pharmacy_value->tax) . ' (' . amountFormat($pharm_tax_pct) . '%)'; ?></td>
                            <td class="sh-text-right"><?php echo $currency_symbol . amountFormat((float)$pharmacy_value->refund_amount); ?></td>
                            <td class="sh-text-right fw-bold"><?php echo $currency_symbol . amountFormat((float)$pharmacy_value->net_amount); ?></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
                <?php } ?>

                <!-- ⑥ Pathology Tests (service names, not bill numbers) -->
                <?php if (!empty($pathology_tests)) { ?>
                <div class="sh-print-section-title"><?php echo $this->lang->line('pathology_bill'); ?></div>
                <table class="sh-print-table">
                    <thead>
                        <tr>
                            <th style="width:12%"><?php echo $this->lang->line('date'); ?></th>
                            <th><?php echo $this->lang->line('service'); ?></th>
                            <th style="width:8%"><?php echo $this->lang->line('qty'); ?></th>
                            <th class="sh-col-16 sh-text-right"><?php echo $this->lang->line('charge') . ' (' . $currency_symbol . ')'; ?></th>
                            <th class="sh-col-18 sh-text-right"><?php echo $this->lang->line('discount') . ' (' . $currency_symbol . ')'; ?></th>
                            <th class="sh-col-16 sh-text-right"><?php echo $this->lang->line('tax') . ' (' . $currency_symbol . ')'; ?></th>
                            <th class="sh-col-16 sh-text-right"><?php echo $this->lang->line('amount') . ' (' . $currency_symbol . ')'; ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $path_section_total = 0;
                        foreach ($pathology_tests as $test) {
                            $apply = (float) $test['apply_charge'];
                            $disc_pct = !empty($test['discount_percentage']) ? (float) $test['discount_percentage'] : 0;
                            $tax_pct = !empty($test['tax_percentage']) ? (float) $test['tax_percentage'] : 0;
                            $discount_amt = ($apply * $disc_pct) / 100;
                            $tax_amt = (($apply - $discount_amt) * $tax_pct) / 100;
                            $line_amt = $apply - $discount_amt + $tax_amt;
                            $path_section_total += $line_amt;
                            $total_amount += $line_amt;
                            $test_date = (!empty($test['bill_date']) && $test['bill_date'] != '0000-00-00')
                                ? $this->customlib->YYYYMMDDTodateFormat($test['bill_date'])
                                : '-';
                            $test_name = !empty($test['test_name']) ? $test['test_name'] : ('Pathology #' . $test['bill_id']);
                        ?>
                        <tr>
                            <td><?php echo $test_date; ?></td>
                            <td class="fw-bold"><?php echo html_escape($test_name); ?></td>
                            <td>1</td>
                            <td class="sh-text-right"><?php echo $currency_symbol . amountFormat($apply); ?></td>
                            <td class="sh-text-right"><?php echo $currency_symbol . amountFormat($discount_amt) . ' (' . amountFormat($disc_pct) . '%)'; ?></td>
                            <td class="sh-text-right"><?php echo $currency_symbol . amountFormat($tax_amt) . ' (' . amountFormat($tax_pct) . '%)'; ?></td>
                            <td class="sh-text-right fw-bold"><?php echo $currency_symbol . amountFormat($line_amt); ?></td>
                        </tr>
                        <?php } ?>
                        <tr>
                            <td colspan="6" class="sh-text-right fw-medium"><?php echo $this->lang->line('total'); ?></td>
                            <td class="sh-text-right fw-bold"><?php echo $currency_symbol . amountFormat($path_section_total); ?></td>
                        </tr>
                    </tbody>
                </table>
                <?php } elseif (!empty($pathology_data)) { ?>
                <div class="sh-print-section-title"><?php echo $this->lang->line('pathology_bill'); ?></div>
                <table class="sh-print-table">
                    <thead>
                        <tr>
                            <th><?php echo $this->lang->line('bill_no'); ?></th>
                            <th style="width:8%"><?php echo $this->lang->line('qty'); ?></th>
                            <th class="sh-col-16 sh-text-right"><?php echo $this->lang->line('charge') . ' (' . $currency_symbol . ')'; ?></th>
                            <th class="sh-col-18 sh-text-right"><?php echo $this->lang->line('discount') . ' (' . $currency_symbol . ')'; ?></th>
                            <th class="sh-col-16 sh-text-right"><?php echo $this->lang->line('tax') . ' (' . $currency_symbol . ')'; ?></th>
                            <th class="sh-col-16 sh-text-right"><?php echo $this->lang->line('amount') . ' (' . $currency_symbol . ')'; ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pathology_data as $pathology_key => $pathology_value) {
                            $total_amount += (float)$pathology_value->net_amount;
                            $path_total    = (float)$pathology_value->total;
                            $path_discount = (float)$pathology_value->discount;
                            $path_base     = $path_total - $path_discount;
                            $path_tax_pct  = ($path_base > 0) ? (((float)$pathology_value->tax * 100) / $path_base) : 0;
                        ?>
                        <tr>
                            <td class="fw-bold"><?php echo $pathology_bill_prefix . $pathology_value->id; ?></td>
                            <td>1</td>
                            <td class="sh-text-right"><?php echo $currency_symbol . amountFormat($path_total); ?></td>
                            <td class="sh-text-right"><?php echo $currency_symbol . amountFormat($path_discount) . ' (' . amountFormat((float)$pathology_value->discount_percentage) . '%)'; ?></td>
                            <td class="sh-text-right"><?php echo $currency_symbol . amountFormat((float)$pathology_value->tax) . ' (' . amountFormat($path_tax_pct) . '%)'; ?></td>
                            <td class="sh-text-right fw-bold"><?php echo $currency_symbol . amountFormat((float)$pathology_value->net_amount); ?></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
                <?php } ?>

                <!-- ⑦ Radiology Bills -->
                <?php if (!empty($radiology_data)) { ?>
                <div class="sh-print-section-title"><?php echo $this->lang->line('radiology_bill'); ?></div>
                <table class="sh-print-table">
                    <thead>
                        <tr>
                            <th><?php echo $this->lang->line('bill_no'); ?></th>
                            <th style="width:8%"><?php echo $this->lang->line('qty'); ?></th>
                            <th class="sh-col-16 sh-text-right"><?php echo $this->lang->line('charge') . ' (' . $currency_symbol . ')'; ?></th>
                            <th class="sh-col-18 sh-text-right"><?php echo $this->lang->line('discount') . ' (' . $currency_symbol . ')'; ?></th>
                            <th class="sh-col-16 sh-text-right"><?php echo $this->lang->line('tax') . ' (' . $currency_symbol . ')'; ?></th>
                            <th class="sh-col-16 sh-text-right"><?php echo $this->lang->line('amount') . ' (' . $currency_symbol . ')'; ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($radiology_data as $radiology_key => $radiology_value) {
                            $total_amount += (float)$radiology_value->net_amount;
                            $rad_total    = (float)$radiology_value->total;
                            $rad_discount = (float)$radiology_value->discount;
                            $rad_base     = $rad_total - $rad_discount;
                            $rad_tax_pct  = ($rad_base > 0) ? (((float)$radiology_value->tax * 100) / $rad_base) : 0;
                        ?>
                        <tr>
                            <td class="fw-bold"><?php echo $radiology_bill_prefix . $radiology_value->id; ?></td>
                            <td>1</td>
                            <td class="sh-text-right"><?php echo $currency_symbol . amountFormat($rad_total); ?></td>
                            <td class="sh-text-right"><?php echo $currency_symbol . amountFormat($rad_discount) . ' (' . amountFormat((float)$radiology_value->discount_percentage) . '%)'; ?></td>
                            <td class="sh-text-right"><?php echo $currency_symbol . amountFormat((float)$radiology_value->tax) . ' (' . amountFormat($rad_tax_pct) . '%)'; ?></td>
                            <td class="sh-text-right fw-bold"><?php echo $currency_symbol . amountFormat((float)$radiology_value->net_amount); ?></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
                <?php } ?>

                <!-- ⑧ Blood Issue Bills -->
                <?php if (!empty($bloodissue_data)) { ?>
                <div class="sh-print-section-title"><?php echo $this->lang->line('blood_issue'); ?></div>
                <table class="sh-print-table">
                    <thead>
                        <tr>
                            <th><?php echo $this->lang->line('bill_no'); ?></th>
                            <th style="width:8%"><?php echo $this->lang->line('qty'); ?></th>
                            <th class="sh-col-16 sh-text-right"><?php echo $this->lang->line('charge') . ' (' . $currency_symbol . ')'; ?></th>
                            <th class="sh-col-18 sh-text-right"><?php echo $this->lang->line('discount') . ' (' . $currency_symbol . ')'; ?></th>
                            <th class="sh-col-16 sh-text-right"><?php echo $this->lang->line('tax') . ' (' . $currency_symbol . ')'; ?></th>
                            <th class="sh-col-16 sh-text-right"><?php echo $this->lang->line('amount') . ' (' . $currency_symbol . ')'; ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bloodissue_data as $blood_issue_key => $blood_issue_value) {
                            $blood_issue_value->standard_charge = $blood_issue_value->amount;
                            $total_amount += (float)$blood_issue_value->net_amount;
                            $blood_std      = (float)$blood_issue_value->standard_charge;
                            $blood_discount = calculatePercent($blood_std, (float)$blood_issue_value->discount_percentage);
                            $blood_tax      = calculatePercent($blood_std - $blood_discount, (float)$blood_issue_value->tax_percentage);
                        ?>
                        <tr>
                            <td class="fw-bold"><?php echo $blood_bank_bill_prefix . $blood_issue_value->id; ?></td>
                            <td>1</td>
                            <td class="sh-text-right"><?php echo $currency_symbol . amountFormat($blood_std); ?></td>
                            <td class="sh-text-right"><?php echo $currency_symbol . amountFormat($blood_discount) . ' (' . $blood_issue_value->discount_percentage . '%)'; ?></td>
                            <td class="sh-text-right"><?php echo $currency_symbol . amountFormat($blood_tax) . ' (' . $blood_issue_value->tax_percentage . '%)'; ?></td>
                            <td class="sh-text-right fw-bold"><?php echo $currency_symbol . amountFormat((float)$blood_issue_value->net_amount); ?></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
                <?php } ?>

                <!-- ⑨ Component Issue Bills -->
                <?php if (!empty($componentissue_data)) { ?>
                <div class="sh-print-section-title"><?php echo $this->lang->line('component_issue'); ?></div>
                <table class="sh-print-table">
                    <thead>
                        <tr>
                            <th><?php echo $this->lang->line('bill_no'); ?></th>
                            <th class="sh-col-16 sh-text-right"><?php echo $this->lang->line('charge') . ' (' . $currency_symbol . ')'; ?></th>
                            <th class="sh-col-18 sh-text-right"><?php echo $this->lang->line('discount') . ' (' . $currency_symbol . ')'; ?></th>
                            <th class="sh-col-16 sh-text-right"><?php echo $this->lang->line('tax') . ' (' . $currency_symbol . ')'; ?></th>
                            <th class="sh-col-16 sh-text-right"><?php echo $this->lang->line('amount') . ' (' . $currency_symbol . ')'; ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($componentissue_data as $componentissue_key => $componentissue_value) {
                            $total_amount += (float)$componentissue_value['net_amount'];
                            $comp_discount = calculatePercent((float)$componentissue_value['amount'], (float)$componentissue_value['discount_percentage']);
                            $comp_tax      = calculatePercent((float)$componentissue_value['amount'] - $comp_discount, (float)$componentissue_value['tax_percentage']);
                        ?>
                        <tr>
                            <td class="fw-bold"><?php echo $blood_bank_bill_prefix . $componentissue_value['id']; ?></td>
                            <td class="sh-text-right"><?php echo $currency_symbol . amountFormat((float)$componentissue_value['standard_charge']); ?></td>
                            <td class="sh-text-right"><?php echo $currency_symbol . amountFormat($comp_discount) . ' (' . $componentissue_value['discount_percentage'] . '%)'; ?></td>
                            <td class="sh-text-right"><?php echo $currency_symbol . amountFormat($comp_tax) . ' (' . $componentissue_value['tax_percentage'] . '%)'; ?></td>
                            <td class="sh-text-right fw-bold"><?php echo $currency_symbol . amountFormat((float)$componentissue_value['net_amount']); ?></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
                <?php } ?>

                <!-- ⑩ Ambulance Bills -->
                <?php if (!empty($ambulance_data)) { ?>
                <div class="sh-print-section-title"><?php echo $this->lang->line('ambulance'); ?></div>
                <table class="sh-print-table">
                    <thead>
                        <tr>
                            <th><?php echo $this->lang->line('bill_no'); ?></th>
                            <th style="width:14%"><?php echo $this->lang->line('vehicle_number'); ?></th>
                            <th class="sh-col-16 sh-text-right"><?php echo $this->lang->line('charge') . ' (' . $currency_symbol . ')'; ?></th>
                            <th class="sh-col-16 sh-text-right"><?php echo $this->lang->line('discount') . ' (' . $currency_symbol . ')'; ?></th>
                            <th class="sh-col-16 sh-text-right"><?php echo $this->lang->line('tax') . ' (' . $currency_symbol . ')'; ?></th>
                            <th class="sh-col-16 sh-text-right"><?php echo $this->lang->line('amount') . ' (' . $currency_symbol . ')'; ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ambulance_data as $ambulance_key => $ambulance_value) {
                            if ($ambulance_value['case_reference_id'] == $case_id) {
                                $total_amount += (float)$ambulance_value['net_amount'];
                                $amb_tax = (((float)$ambulance_value['amount'] - (float)$ambulance_value['discount']) * (float)$ambulance_value['tax_percentage']) / 100;
                        ?>
                        <tr>
                            <td class="fw-bold"><?php echo $this->customlib->getSessionPrefixByType('ambulance_call_billing') . $ambulance_value['id']; ?></td>
                            <td><?php echo html_escape($ambulance_value['vehicle_no']); ?></td>
                            <td class="sh-text-right"><?php echo $currency_symbol . amountFormat((float)$ambulance_value['amount']); ?></td>
                            <td class="sh-text-right"><?php echo $currency_symbol . amountFormat((float)$ambulance_value['discount']) . ' (' . amountFormat((float)$ambulance_value['discount_percentage']) . '%)'; ?></td>
                            <td class="sh-text-right"><?php echo $currency_symbol . amountFormat($amb_tax) . ' (' . amountFormat((float)$ambulance_value['tax_percentage']) . '%)'; ?></td>
                            <td class="sh-text-right fw-bold"><?php echo $currency_symbol . amountFormat((float)$ambulance_value['net_amount']); ?></td>
                        </tr>
                        <?php }
                        } ?>
                    </tbody>
                </table>
                <?php } ?>

                <!-- ⑪ Transactions -->
                <?php if (!empty($transaction_data)) { ?>
                <div class="sh-print-section-title"><?php echo $this->lang->line('payment_details'); ?></div>
                <table class="sh-print-table">
                    <thead>
                        <tr>
                            <th><?php echo $this->lang->line('transaction_id'); ?></th>
                            <th style="width:22%"><?php echo $this->lang->line('payment_date'); ?></th>
                            <th style="width:22%"><?php echo $this->lang->line('payment_mode'); ?></th>
                            <th class="sh-col-20 sh-text-right"><?php echo $this->lang->line('amount') . ' (' . $currency_symbol . ')'; ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transaction_data as $transaction_key => $transaction_value) {
                            $amount_paid += (float)$transaction_value->amount;
                        ?>
                        <tr>
                            <td><?php echo $transaction_prefix . $transaction_value->id; ?></td>
                            <td><?php echo $this->customlib->YYYYMMDDHisTodateFormat($transaction_value->payment_date); ?></td>
                            <td><?php echo ($transaction_value->payment_mode ? $this->lang->line(strtolower($transaction_value->payment_mode)) : '-'); ?></td>
                            <td class="sh-text-right fw-bold"><?php echo $currency_symbol . amountFormat((float)$transaction_value->amount); ?></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
                <?php } ?>

                <!-- ⑫ Grand total summary -->
                <?php
                $overall_discount = isset($overall_discount) ? $overall_discount : array('amount' => 0, 'percent' => 0);
                $overall_disc_amt = !empty($overall_discount['amount']) ? (float) $overall_discount['amount'] : 0;
                $print_balance = $total_amount - $overall_disc_amt - $amount_paid;
                ?>
                <table class="sh-print-table sh-mt-12" >
                    <tbody>
                        <tr class="sh-row-first">
                            <td class="sh-col-80 sh-text-right fw-medium"><?php echo $this->lang->line('grand_total'); ?></td>
                            <td class="sh-text-right"><?php echo $currency_symbol . amountFormat($total_amount); ?></td>
                        </tr>
                        <?php if ($overall_disc_amt > 0) { ?>
                        <tr>
                            <td class="sh-text-right fw-medium"><?php echo $this->lang->line('discount'); ?><?php if (!empty($overall_discount['percent'])) { echo ' (' . amountFormat($overall_discount['percent']) . '%)'; } ?></td>
                            <td class="sh-text-right"><?php echo $currency_symbol . amountFormat($overall_disc_amt); ?></td>
                        </tr>
                        <?php } ?>
                        <tr>
                            <td class="sh-text-right fw-medium"><?php echo $this->lang->line('amount_paid'); ?></td>
                            <td class="sh-text-right"><?php echo $currency_symbol . amountFormat($amount_paid); ?></td>
                        </tr>
                        <tr class="sh-row-total">
                            <td class="sh-text-right fw-medium"><?php echo $this->lang->line('balance_amount'); ?></td>
                            <td><?php echo $currency_symbol . amountFormat($print_balance); ?></td>
                        </tr>
                    </tbody>
                </table>


            </div>
            </div>
        </td></tr>
    </tbody>
    <tfoot>
        <tr><td>
            <div class="footer-space">&nbsp;</div>
            <div class="letterhead-bottom-spacer"></div>
        </td></tr>
    </tfoot>
</table>

</div>
