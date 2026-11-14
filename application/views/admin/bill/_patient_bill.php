<?php
$currency_symbol = $this->customlib->getHospitalCurrencyFormat();
$editable = !empty($editable);
$case_id_attr = isset($case_id) ? html_escape($case_id) : '';

if (!function_exists('bill_summary_action_btns')) {
    function bill_summary_action_btns($module, $id, $can_edit = true, $can_delete = true, $show_qty = true)
    {
        $html = '<td class="text-end white-space-nowrap bill-summary-actions" style="min-width:90px;">';
        if ($can_edit) {
            $html .= '<a href="javascript:void(0);" class="btn btn-primary btn-sm edit_final_bill_item me-1" data-module="' . html_escape($module) . '" data-id="' . (int) $id . '" data-show-qty="' . ($show_qty ? '1' : '0') . '" title="Edit"><i class="fas fa-pencil-alt"></i></a>';
        }
        if ($can_delete) {
            $html .= '<a href="javascript:void(0);" class="btn btn-danger btn-sm delete_final_bill_item" data-module="' . html_escape($module) . '" data-id="' . (int) $id . '" title="Delete"><i class="fas fa-trash"></i></a>';
        }
        $html .= '</td>';
        return $html;
    }
}
?>

<!-- TPA Info -->
<div class="sh-form-card mb-2" id="final_bill_summary" data-case-id="<?php echo $case_id_attr; ?>">
    <div class="sh-card-header">
        <span class="sh-card-header-title"><i class="fas fa-shield-alt me-1"></i><?php echo $this->lang->line('tpa'); ?></span>
    </div>
    <div class="sh-info-grid">
        <div class="row g-0">
            <div class="col-6 col-md-4 sh-info-item">
                <span class="sh-info-label"><?php echo $this->lang->line('tpa'); ?></span>
                <span class="sh-info-value"><?php if(isset($patient['organisation_name'])){ echo $patient['organisation_name']; } ?></span>
            </div>
            <div class="col-6 col-md-4 sh-info-item">
                <span class="sh-info-label"><?php echo $this->lang->line('tpa_validity'); ?></span>
                <span class="sh-info-value"><?php if(isset($patient['insurance_validity'])){ echo $this->customlib->YYYYMMDDTodateFormat($patient['insurance_validity']); } ?></span>
            </div>
            <div class="col-6 col-md-4 sh-info-item">
                <span class="sh-info-label"><?php echo $this->lang->line('tpa_id'); ?></span>
                <span class="sh-info-value"><?php if(isset($patient['insurance_id'])){ echo $patient['insurance_id']; } ?></span>
            </div>
        </div>
    </div>
</div>

<?php
$total_amount  = 0;
$amount_paid   = 0;
$amount_refund = 0;
$amount_advance = 0;

?>

<!-- OPD Charges -->
<?php
$has_opd = !empty($opd_data) || (!empty($opd_id) && $editable);
if ($has_opd) { ?>
<div class="sh-form-card mb-2">
    <div class="sh-card-header d-flex align-items-center">
        <span class="sh-card-header-title"><?php echo $this->lang->line('opd_charges'); ?></span>
        <?php if ($editable && !empty($opd_id)) { ?>
        <button type="button" class="btn btn-primary btn-sm ms-auto add_final_bill_item" data-module="opd" data-case-id="<?php echo $case_id_attr; ?>">
            <i class="fa fa-plus"></i> <?php echo $this->lang->line('add'); ?>
        </button>
        <?php } ?>
    </div>
    <div class="p-2">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead>
                    <tr>
                        <th width="20%"><?php echo $this->lang->line('service'); ?></th>
                        <th width="20%"><?php echo $this->lang->line('charge'); ?></th>
                        <th width="10%"><?php echo $this->lang->line('qty'); ?></th>
                        <th width="15%" class="text-end"><?php echo $this->lang->line('discount'); ?></th>
                        <th class="text-end" width="15%"><?php echo $this->lang->line('tax'); ?></th>
                        <th class="text-end"><?php echo $this->lang->line('amount'); ?></th>
                        <?php if ($editable) { ?><th class="text-end"><?php echo $this->lang->line('action'); ?></th><?php } ?>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($opd_data)) {
                    foreach ($opd_data as $opd_key => $opd_value) {
                    $total_amount += $opd_value['amount']; ?>
                    <tr>
                        <td width="20%"><?php echo $opd_value['name']; ?></td>
                        <td width="20%"><?php echo $currency_symbol.$opd_value['apply_charge']; ?></td>
                        <td width="10%"><?php echo $opd_value['qty']." ".$opd_value['unit']; ?></td>
                        <td width="15%" class="text-end"><?php echo $currency_symbol.amountFormat(($opd_value['apply_charge'] * ($opd_value['discount_percentage']/100)))." (".$opd_value['discount_percentage']."%) "; ?></td>
                        <td class="text-end"><?php
                            $tax_raw = ($opd_value["apply_charge"] - (($opd_value["apply_charge"] * $opd_value["discount_percentage"]) / 100));
                            $tax = (($tax_raw * $opd_value["tax"]) / 100);
                            echo $currency_symbol.amountFormat($tax)." (".$opd_value['tax']."%) ";
                        ?></td>
                        <td class="text-end"><?php echo $currency_symbol.$opd_value['amount']; ?></td>
                        <?php if ($editable) { echo bill_summary_action_btns('opd', $opd_value['id']); } ?>
                    </tr>
                <?php }
                } else { ?>
                    <tr><td colspan="<?php echo $editable ? 7 : 6; ?>" class="text-center text-muted"><?php echo $this->lang->line('no_record_found'); ?></td></tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php } ?>

<!-- IPD Charges -->
<?php
$has_ipd = !empty($ipd_data) || (!empty($ipd_id) && $editable);
if ($has_ipd) { ?>
<div class="sh-form-card mb-2">
    <div class="sh-card-header d-flex align-items-center">
        <span class="sh-card-header-title"><?php echo $this->lang->line('ipd_charges'); ?></span>
        <?php if ($editable && !empty($ipd_id)) { ?>
        <button type="button" class="btn btn-primary btn-sm ms-auto add_final_bill_item" data-module="ipd" data-case-id="<?php echo $case_id_attr; ?>">
            <i class="fa fa-plus"></i> <?php echo $this->lang->line('add'); ?>
        </button>
        <?php } ?>
    </div>
    <div class="p-2">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead>
                    <tr>
                        <th><?php echo $this->lang->line('service'); ?></th>
                        <th><?php echo $this->lang->line('charge'); ?></th>
                        <th><?php echo $this->lang->line('qty'); ?></th>
                        <th width="15%" class="text-end"><?php echo $this->lang->line('discount'); ?></th>
                        <th class="text-end" width="15%"><?php echo $this->lang->line('tax'); ?></th>
                        <th class="text-end"><?php echo $this->lang->line('amount'); ?></th>
                        <?php if ($editable) { ?><th class="text-end"><?php echo $this->lang->line('action'); ?></th><?php } ?>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($ipd_data)) {
                    foreach ($ipd_data as $ipd_key => $ipd_value) {
                    $total_amount += $ipd_value['amount']; ?>
                    <tr>
                        <td width="20%"><?php echo $ipd_value['name']; ?></td>
                        <td width="20%"><?php echo $currency_symbol.$ipd_value['apply_charge']; ?></td>
                        <td width="10%"><?php echo $ipd_value['qty']." ".$ipd_value['unit']; ?></td>
                        <td width="15%" class="text-end"><?php echo $currency_symbol.amountFormat(($ipd_value['apply_charge'] * $ipd_value['discount_percentage'])/100)." (".$ipd_value['discount_percentage']."%) "; ?></td>
                        <td class="text-end" width="15%"><?php
                            $tax_raw = ($ipd_value["apply_charge"] - (($ipd_value["apply_charge"] * $ipd_value["discount_percentage"]) / 100));
                            $tax = (($tax_raw * $ipd_value["tax"]) / 100);
                            echo $currency_symbol.amountFormat($tax)." (".$ipd_value['tax']."%) ";
                        ?></td>
                        <td class="text-end"><?php echo $currency_symbol.$ipd_value['amount']; ?></td>
                        <?php if ($editable) { echo bill_summary_action_btns('ipd', $ipd_value['id']); } ?>
                    </tr>
                <?php }
                } else { ?>
                    <tr><td colspan="<?php echo $editable ? 7 : 6; ?>" class="text-center text-muted"><?php echo $this->lang->line('no_record_found'); ?></td></tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php } ?>

<!-- Pharmacy Bill (read-only) -->
<?php if(!empty($pharmacy_data)){ ?>
<div class="sh-form-card mb-2">
    <div class="sh-card-header d-flex align-items-center">
        <span class="sh-card-header-title"><?php echo $this->lang->line('pharmacy_bill'); ?></span>
        <span class="badge bg-secondary ms-auto">Read only</span>
    </div>
    <div class="p-2">
        <div class="alert alert-info py-1 px-2 mb-2 small"><?php echo $this->lang->line('pharmacy'); ?> items cannot be modified from final billing.</div>
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead>
                    <tr>
                        <th><?php echo $this->lang->line('bill_no'); ?></th>
                        <th><?php echo $this->lang->line('charge'); ?></th>
                        <th class="text-end" width="15%"><?php echo $this->lang->line('discount'); ?></th>
                        <th class="text-end" width="15%"><?php echo $this->lang->line('tax'); ?></th>
                        <th class="text-end" width="15%"><?php echo $this->lang->line('refund'); ?></th>
                        <th class="text-end"><?php echo $this->lang->line('amount'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($pharmacy_data as $pharmacy_key => $pharmacy_value) {
                    $total_amount += $pharmacy_value->net_amount; ?>
                    <tr>
                        <td width="20%" class="white-space-nowrap"><?php echo $pharmacy_bill_prefix.$pharmacy_value->id; ?></td>
                        <td width="20%"><?php echo $currency_symbol.$pharmacy_value->total; ?></td>
                        <td class="text-end"><?php echo $currency_symbol.$pharmacy_value->discount." (".$pharmacy_value->discount_percentage."%) "; ?></td>
                        <td width="15%" class="text-end"><?php echo $currency_symbol.$pharmacy_value->tax." (".amountFormat(($pharmacy_value->tax * 100) / ($pharmacy_value->total - $pharmacy_value->discount))."%) "; ?></td>
                        <td width="15%" class="text-end"><?php echo $currency_symbol.$pharmacy_value->refund_amount; ?></td>
                        <td class="text-end"><?php echo $currency_symbol.$pharmacy_value->net_amount; ?></td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php } ?>

<!-- Pathology Bill -->
<?php if(!empty($pathology_data)){ ?>
<div class="sh-form-card mb-2">
    <div class="sh-card-header">
        <span class="sh-card-header-title"><?php echo $this->lang->line('pathology_bill'); ?></span>
    </div>
    <div class="p-2">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead>
                    <tr>
                        <th><?php echo $this->lang->line('bill_no'); ?></th>
                        <th><?php echo $this->lang->line('charge'); ?></th>
                        <th><?php echo $this->lang->line('qty'); ?></th>
                        <th class="text-end" width="15%"><?php echo $this->lang->line('discount'); ?></th>
                        <th class="text-end" width="15%"><?php echo $this->lang->line('tax'); ?></th>
                        <th class="text-end"><?php echo $this->lang->line('amount'); ?></th>
                        <?php if ($editable) { ?><th class="text-end"><?php echo $this->lang->line('action'); ?></th><?php } ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($pathology_data as $pathology_key => $pathology_value) {
                    $total_amount += $pathology_value->net_amount; ?>
                    <tr>
                        <td width="20%" class="white-space-nowrap"><?php echo $pathology_bill_prefix.$pathology_value->id; ?></td>
                        <td width="20%"><?php echo $currency_symbol.$pathology_value->total; ?></td>
                        <td width="10%">1</td>
                        <td width="15%" class="text-end"><?php echo $currency_symbol.$pathology_value->discount." (".$pathology_value->discount_percentage."%) "; ?></td>
                        <td width="15%" class="text-end"><?php
                            if($pathology_value->total > 0){
                                echo $currency_symbol.$pathology_value->tax." (".amountFormat(($pathology_value->tax * 100) / ($pathology_value->total - $pathology_value->discount), 2)."%)";
                            }
                        ?></td>
                        <td class="text-end"><?php echo $currency_symbol.$pathology_value->net_amount; ?></td>
                        <?php if ($editable) { echo bill_summary_action_btns('pathology', $pathology_value->id, true, true, false); } ?>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php } ?>

<!-- Radiology Bill -->
<?php if(!empty($radiology_data)){ ?>
<div class="sh-form-card mb-2">
    <div class="sh-card-header">
        <span class="sh-card-header-title"><?php echo $this->lang->line('radiology_bill'); ?></span>
    </div>
    <div class="p-2">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead>
                    <tr>
                        <th><?php echo $this->lang->line('bill_no'); ?></th>
                        <th><?php echo $this->lang->line('charge'); ?></th>
                        <th><?php echo $this->lang->line('qty'); ?></th>
                        <th class="text-end" width="15%"><?php echo $this->lang->line('discount'); ?></th>
                        <th class="text-end" width="15%"><?php echo $this->lang->line('tax'); ?></th>
                        <th class="text-end"><?php echo $this->lang->line('amount'); ?></th>
                        <?php if ($editable) { ?><th class="text-end"><?php echo $this->lang->line('action'); ?></th><?php } ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($radiology_data as $radiology_key => $radiology_value) {
                    $total_amount += $radiology_value->net_amount; ?>
                    <tr>
                        <td width="20%" class="white-space-nowrap"><?php echo $radiology_bill_prefix.$radiology_value->id; ?></td>
                        <td width="20%"><?php echo $currency_symbol.$radiology_value->total; ?></td>
                        <td width="10%">1</td>
                        <td width="15%" class="text-end"><?php echo $currency_symbol.$radiology_value->discount." (".$radiology_value->discount_percentage."%) "; ?></td>
                        <td class="text-end" width="15%"><?php
                            $discount_amt = ($radiology_value->total * $radiology_value->discount_percentage) / 100;
                            $div_by = $radiology_value->total - $discount_amt;
                            echo $currency_symbol.$radiology_value->tax." (".amountFormat(($radiology_value->tax * 100) / $div_by)."%)";
                        ?></td>
                        <td class="text-end"><?php echo $currency_symbol.$radiology_value->net_amount; ?></td>
                        <?php if ($editable) { echo bill_summary_action_btns('radiology', $radiology_value->id, true, true, false); } ?>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php } ?>

<!-- Blood Issue -->
<?php if(!empty($bloodissue_data)){ ?>
<div class="sh-form-card mb-2">
    <div class="sh-card-header">
        <span class="sh-card-header-title"><?php echo $this->lang->line('blood_issue'); ?></span>
    </div>
    <div class="p-2">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead>
                    <tr>
                        <th width="20%"><?php echo $this->lang->line('bill_no'); ?></th>
                        <th width="20%"><?php echo $this->lang->line('charge'); ?></th>
                        <th width="10%"><?php echo $this->lang->line('qty'); ?></th>
                        <th class="text-end" width="15%"><?php echo $this->lang->line('discount'); ?></th>
                        <th class="text-end" width="15%"><?php echo $this->lang->line('tax'); ?></th>
                        <th class="text-end"><?php echo $this->lang->line('amount'); ?></th>
                        <?php if ($editable) { ?><th class="text-end"><?php echo $this->lang->line('action'); ?></th><?php } ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($bloodissue_data as $blood_issue_key => $blood_issue_value) {
                    $total_amount += $blood_issue_value->net_amount;
                    $blood_issue_value->standard_charge = $blood_issue_value->amount;
                    $discount_amount = calculatePercent($blood_issue_value->standard_charge, $blood_issue_value->discount_percentage); ?>
                    <tr>
                        <td width="20%"><?php echo $blood_bank_bill_prefix.$blood_issue_value->id; ?></td>
                        <td width="20%"><?php echo $currency_symbol.$blood_issue_value->standard_charge; ?></td>
                        <td width="10%">1</td>
                        <td width="15%" class="text-end"><?php echo $discount_amount." (".$blood_issue_value->discount_percentage."%) "; ?></td>
                        <td width="15%" class="text-end"><?php echo $currency_symbol.calculatePercent(($blood_issue_value->standard_charge - $discount_amount), $blood_issue_value->tax_percentage)." (".$blood_issue_value->tax_percentage."%) "; ?></td>
                        <td class="text-end"><?php echo $currency_symbol.$blood_issue_value->net_amount; ?></td>
                        <?php if ($editable) { echo bill_summary_action_btns('bloodissue', $blood_issue_value->id, true, true, false); } ?>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php } ?>

<!-- Component Issue -->
<?php if(!empty($componentissue_data)){ ?>
<div class="sh-form-card mb-2">
    <div class="sh-card-header">
        <span class="sh-card-header-title"><?php echo $this->lang->line('component_issue'); ?></span>
    </div>
    <div class="p-2">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead>
                    <tr>
                        <th width="20%"><?php echo $this->lang->line('bill_no'); ?></th>
                        <th width="20%"><?php echo $this->lang->line('charge'); ?></th>
                        <th width="20%" class="text-end"><?php echo $this->lang->line('discount'); ?></th>
                        <th width="20%" class="text-end"><?php echo $this->lang->line('tax'); ?></th>
                        <th class="text-end"><?php echo $this->lang->line('amount'); ?></th>
                        <?php if ($editable) { ?><th class="text-end"><?php echo $this->lang->line('action'); ?></th><?php } ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($componentissue_data as $componentissue_key => $componentissue_value) {
                    $total_amount += $componentissue_value['net_amount'];
                    $discount_amount = calculatePercent($componentissue_value['amount'], $componentissue_value['discount_percentage']);
                    $tax_amount = calculatePercent(($componentissue_value['amount'] - $discount_amount), $componentissue_value['tax_percentage']); ?>
                    <tr>
                        <td width="20%"><?php echo $blood_bank_bill_prefix.$componentissue_value['id']; ?></td>
                        <td width="20%"><?php echo $currency_symbol.amountFormat($componentissue_value['standard_charge']); ?></td>
                        <td width="20%" class="text-end"><?php echo $discount_amount.' ('.$componentissue_value['discount_percentage'].'%)'; ?></td>
                        <td width="20%" class="text-end"><?php echo $tax_amount.' ('.$componentissue_value['tax_percentage'].'%)'; ?></td>
                        <td class="text-end"><?php echo $currency_symbol.$componentissue_value['net_amount']; ?></td>
                        <?php if ($editable) { echo bill_summary_action_btns('componentissue', $componentissue_value['id'], true, true, false); } ?>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php } ?>

<!-- Ambulance -->
<?php if(!empty($ambulance_data)){ ?>
<div class="sh-form-card mb-2">
    <div class="sh-card-header">
        <span class="sh-card-header-title"><?php echo $this->lang->line('ambulance'); ?></span>
    </div>
    <div class="p-2">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead>
                    <tr>
                        <th width="20%"><?php echo $this->lang->line('bill_no'); ?></th>
                        <th width="20%"><?php echo $this->lang->line('charge'); ?></th>
                        <th><?php echo $this->lang->line('vehicle_number'); ?></th>
                        <th class="text-end"><?php echo $this->lang->line('discount'); ?></th>
                        <th class="text-end"><?php echo $this->lang->line('tax'); ?></th>
                        <th class="text-end"><?php echo $this->lang->line('amount'); ?></th>
                        <?php if ($editable) { ?><th class="text-end"><?php echo $this->lang->line('action'); ?></th><?php } ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($ambulance_data as $ambulance_key => $ambulance_value) {
                    if($ambulance_value['case_reference_id'] == $case_id){
                        $total_amount += $ambulance_value['net_amount']; ?>
                        <tr>
                            <td class="white-space-nowrap"><?php echo $this->customlib->getSessionPrefixByType('ambulance_call_billing').$ambulance_value['id']; ?></td>
                            <td><?php echo $currency_symbol.amountFormat($ambulance_value['amount']); ?></td>
                            <td><?php echo $ambulance_value['vehicle_no']; ?></td>
                            <td class="text-end"><?php echo amountFormat($ambulance_value['discount']); ?> (<?php echo amountFormat($ambulance_value['discount_percentage']); ?>%)</td>
                            <td class="text-end"><?php
                                $tax = (($ambulance_value['amount'] - $ambulance_value['discount']) * $ambulance_value['tax_percentage']) / 100;
                                echo amountFormat($tax); ?> (<?php echo amountFormat($ambulance_value['tax_percentage']); ?>%)</td>
                            <td class="text-end"><?php echo $currency_symbol.amountFormat($ambulance_value['net_amount']); ?></td>
                            <?php if ($editable) { echo bill_summary_action_btns('ambulance', $ambulance_value['id'], true, true, false); } ?>
                        </tr>
                    <?php }
                } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php } ?>

<!-- Transactions -->
<?php if(!empty($transaction_data)){ ?>
<div class="sh-form-card mb-2">
    <div class="sh-card-header">
        <span class="sh-card-header-title"><?php echo $this->lang->line('transactions'); ?></span>
    </div>
    <div class="p-2">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead>
                    <tr>
                        <th><?php echo $this->lang->line('transaction_id'); ?></th>
                        <th><?php echo $this->lang->line('payment_date'); ?></th>
                        <th><?php echo $this->lang->line('payment_mode'); ?></th>
                        <th class="text-end"><?php echo $this->lang->line('amount'); ?></th>
                        <th><?php echo $this->lang->line('action'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($transaction_data as $transaction_key => $transaction_value) {
                    $amount_paid += $transaction_value->amount;
                    if (!empty($transaction_value->amount_type) && $transaction_value->amount_type === 'advance') {
                        $amount_advance += $transaction_value->amount;
                    }
                    $mode_key = $transaction_value->payment_mode ? strtolower($transaction_value->payment_mode) : '';
                    $mode_label = $mode_key ? $this->lang->line($mode_key) : '';
                    if ($mode_label === $mode_key || $mode_label === false) {
                        $mode_label = $transaction_value->payment_mode;
                    }
                    ?>
                    <tr>
                        <td width="20%" class="white-space-nowrap"><?php echo $transaction_prefix.$transaction_value->id; ?></td>
                        <td width="25%"><?php echo $this->customlib->YYYYMMDDHisTodateFormat($transaction_value->payment_date); ?></td>
                        <td><?php
                            if (!empty($transaction_value->amount_type) && $transaction_value->amount_type === 'advance') {
                                echo $this->lang->line('advance');
                                if ($transaction_value->payment_mode) { echo ' / '; }
                            }
                            echo html_escape($mode_label);
                        ?></td>
                        <td class="text-end"><?php echo $currency_symbol.$transaction_value->amount; ?></td>
                        <td>
                            <?php if ($transaction_value->payment_mode === 'Credit' && empty($transaction_value->credit_settled_at)) { ?>
                            <button type="button" class="btn btn-sm btn-success settle-credit-btn" data-id="<?php echo (int) $transaction_value->id; ?>" title="<?php echo $this->lang->line('settle_credit'); ?>"><i class="fa fa-check"></i></button>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php } ?>

<!-- Refund -->
<?php if(!empty($refund_data)){ ?>
<div class="sh-form-card mb-2">
    <div class="sh-card-header">
        <span class="sh-card-header-title"><?php echo $this->lang->line('refund'); ?></span>
    </div>
    <div class="p-2">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead>
                    <tr>
                        <th><?php echo $this->lang->line('transaction_id'); ?></th>
                        <th><?php echo $this->lang->line('payment_date'); ?></th>
                        <th><?php echo $this->lang->line('payment_mode'); ?></th>
                        <th class="text-end"><?php echo $this->lang->line('amount'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($refund_data as $transaction_key => $transaction_value) {
                    $amount_refund += $transaction_value->amount; ?>
                    <tr>
                        <td width="20%" class="white-space-nowrap"><?php echo $transaction_prefix.$transaction_value->id; ?></td>
                        <td width="30%"><?php echo $this->customlib->YYYYMMDDHisTodateFormat($transaction_value->payment_date); ?></td>
                        <td><?php echo $this->lang->line(strtolower($transaction_value->payment_mode)); ?></td>
                        <td class="text-end"><?php echo $currency_symbol.$transaction_value->amount; ?></td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php } ?>

<!-- Amount Summary -->
<div class="sh-form-card mb-2">
    <div class="sh-card-header">
        <span class="sh-card-header-title"><?php echo $this->lang->line('amount_summary'); ?></span>
    </div>
    <div class="p-2">
        <div class="row">
            <div class="col-md-6 offset-md-6">
                <table class="table table-hover table-sm mb-0">
                    <tbody>
                        <tr>
                            <th class="w-50"><?php echo $this->lang->line('grand_total'); ?>:</th>
                            <td class="text-end"><?php echo $currency_symbol.amountFormat($total_amount); ?></td>
                        </tr>
                        <?php if ($amount_advance > 0) { ?>
                        <tr>
                            <th><?php echo $this->lang->line('advance_adjusted'); ?>:</th>
                            <td class="text-end"><?php echo $currency_symbol.amountFormat($amount_advance); ?></td>
                        </tr>
                        <tr>
                            <th><?php echo $this->lang->line('other_payments'); ?>:</th>
                            <td class="text-end"><?php echo $currency_symbol.amountFormat($amount_paid - $amount_advance); ?></td>
                        </tr>
                        <?php } ?>
                        <tr>
                            <th><?php echo $this->lang->line('amount_paid'); ?>:</th>
                            <td class="text-end"><?php echo $currency_symbol.amountFormat($amount_paid); ?></td>
                        </tr>
                        <tr>
                            <th><?php echo $this->lang->line('refund_amount'); ?>:</th>
                            <td class="text-end"><?php echo $currency_symbol.amountFormat($amount_refund); ?></td>
                        </tr>
                        <tr>
                            <th><?php echo $this->lang->line('balance_amount'); ?>:</th>
                            <td class="text-end"><?php echo $currency_symbol.amountFormat(($total_amount - $amount_paid + $amount_refund)); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
