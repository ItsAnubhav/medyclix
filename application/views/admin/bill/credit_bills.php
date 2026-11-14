<?php
$currency_symbol = isset($currency_symbol) ? $currency_symbol : $this->customlib->getHospitalCurrencyFormat();
$status = isset($status) ? $status : 'outstanding';
$credit_bills = isset($credit_bills) ? $credit_bills : array();
$total_to_collect = isset($total_to_collect) ? $total_to_collect : 0;
?>
<div class="content-wrapper">
    <section class="content-header">
        <h1><?php echo $this->lang->line('credit_bills'); ?></h1>
    </section>
    <section class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex flex-wrap align-items-center gap-2">
                        <h3 class="card-title mb-0 me-auto"><?php echo $this->lang->line('credit_bills'); ?></h3>
                        <div class="btn-group btn-group-sm" role="group">
                            <a href="<?php echo site_url('admin/bill/credit_bills?status=outstanding'); ?>" class="btn <?php echo $status === 'outstanding' ? 'btn-primary' : 'btn-outline-primary'; ?>"><?php echo $this->lang->line('unpaid'); ?></a>
                            <a href="<?php echo site_url('admin/bill/credit_bills?status=settled'); ?>" class="btn <?php echo $status === 'settled' ? 'btn-primary' : 'btn-outline-primary'; ?>"><?php echo $this->lang->line('paid'); ?></a>
                            <a href="<?php echo site_url('admin/bill/credit_bills?status=all'); ?>" class="btn <?php echo $status === 'all' ? 'btn-primary' : 'btn-outline-primary'; ?>"><?php echo $this->lang->line('all'); ?></a>
                        </div>
                        <a href="<?php echo site_url('admin/bill/dashboard'); ?>" class="btn btn-sm btn-outline-secondary"><?php echo $this->lang->line('dashboard'); ?></a>
                    </div>
                    <div class="card-body">
                        <?php if ($status === 'outstanding' || $status === 'all') { ?>
                        <div class="alert alert-info py-2">
                            <strong><?php echo $this->lang->line('total_to_collect'); ?>:</strong>
                            <?php echo $currency_symbol . amountFormat($total_to_collect); ?>
                        </div>
                        <?php } ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped" id="credit_bills_table">
                                <thead>
                                    <tr>
                                        <th><?php echo $this->lang->line('patient_name'); ?></th>
                                        <th><?php echo $this->lang->line('case_id'); ?></th>
                                        <th><?php echo $this->lang->line('ipd_no'); ?></th>
                                        <th><?php echo $this->lang->line('credit_doctor'); ?></th>
                                        <th><?php echo $this->lang->line('payment_date'); ?></th>
                                        <th><?php echo $this->lang->line('days_outstanding'); ?></th>
                                        <th class="text-end"><?php echo $this->lang->line('amount'); ?></th>
                                        <th><?php echo $this->lang->line('payment_mode'); ?></th>
                                        <th><?php echo $this->lang->line('action'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php
                                if (empty($credit_bills)) {
                                    echo '<tr><td colspan="9" class="text-center text-muted">' . $this->lang->line('no_record_found') . '</td></tr>';
                                } else {
                                    foreach ($credit_bills as $row) {
                                        $pay_ts = !empty($row['payment_date']) ? strtotime($row['payment_date']) : time();
                                        $days = (int) floor((time() - $pay_ts) / 86400);
                                        if ($days < 0) {
                                            $days = 0;
                                        }
                                        $is_outstanding = ($row['payment_mode'] === 'Credit' && empty($row['credit_settled_at']));
                                        $overdue = $is_outstanding && $days > 7;
                                        $row_class = $overdue ? 'table-warning' : '';
                                        $mode_key = strtolower($row['payment_mode']);
                                        $mode_label = $this->lang->line($mode_key);
                                        if ($mode_label === $mode_key || $mode_label === false) {
                                            $mode_label = $row['payment_mode'];
                                        }
                                        if (!empty($row['credit_settled_at']) && $row['payment_mode'] === 'Cash') {
                                            $mode_label = $this->lang->line('cash') . ' (' . $this->lang->line('paid') . ')';
                                        }
                                        $credit_doctor = '-';
                                        if (!empty($row['credit_doctor_name'])) {
                                            $credit_doctor = composeStaffNameByString(
                                                $row['credit_doctor_name'],
                                                isset($row['credit_doctor_surname']) ? $row['credit_doctor_surname'] : '',
                                                isset($row['credit_doctor_employee_id']) ? $row['credit_doctor_employee_id'] : ''
                                            );
                                        }
                                        ?>
                                        <tr class="<?php echo $row_class; ?>">
                                            <td>
                                                <?php echo html_escape($row['patient_name']); ?>
                                                <?php if ($overdue) { ?>
                                                    <span class="badge bg-warning text-dark ms-1"><?php echo $this->lang->line('credit_overdue'); ?></span>
                                                <?php } ?>
                                            </td>
                                            <td><?php echo (int) $row['case_reference_id']; ?></td>
                                            <td><?php echo !empty($row['ipdid']) ? html_escape($this->customlib->getSessionPrefixByType('ipd_no') . $row['ipdid']) : '-'; ?></td>
                                            <td><?php echo html_escape($credit_doctor); ?></td>
                                            <td><?php echo $this->customlib->YYYYMMDDHisTodateFormat($row['payment_date'], $this->customlib->getHospitalTimeFormat()); ?></td>
                                            <td><?php echo $is_outstanding ? $days : '-'; ?></td>
                                            <td class="text-end"><?php echo $currency_symbol . amountFormat($row['amount']); ?></td>
                                            <td><?php echo html_escape($mode_label); ?></td>
                                            <td class="white-space-nowrap">
                                                <?php if (!empty($row['case_reference_id'])) { ?>
                                                <a href="<?php echo site_url('admin/bill/service_posting/' . $row['case_reference_id']); ?>" class="btn btn-sm btn-primary" title="<?php echo $this->lang->line('view'); ?>"><i class="fa fa-eye"></i></a>
                                                <a href="<?php echo site_url('admin/bill/download_patient_bill_pdf/' . $row['case_reference_id']); ?>" target="_blank" class="btn btn-sm btn-light" title="<?php echo $this->lang->line('view_pdf'); ?>"><i class="fa fa-file-pdf"></i></a>
                                                <?php } ?>
                                                <?php if ($is_outstanding) { ?>
                                                <button type="button" class="btn btn-sm btn-success settle-credit-btn" data-id="<?php echo (int) $row['id']; ?>" title="<?php echo $this->lang->line('settle_credit'); ?>"><i class="fa fa-check"></i> <?php echo $this->lang->line('settle_credit'); ?></button>
                                                <?php } ?>
                                            </td>
                                        </tr>
                                        <?php
                                    }
                                }
                                ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
<script type="text/javascript">
(function ($) {
    $(document).on('click', '.settle-credit-btn', function () {
        var $btn = $(this);
        var id = $btn.data('id');
        if (!id) { return; }
        if (!confirm('<?php echo $this->lang->line('settle_credit'); ?>?')) { return; }
        $.ajax({
            url: base_url + 'admin/bill/settle_credit_payment',
            type: 'POST',
            dataType: 'json',
            data: { transaction_id: id },
            beforeSend: function () { $btn.prop('disabled', true); },
            success: function (res) {
                if (res.status === 'success') {
                    successMsg(res.message);
                    window.location.reload();
                } else {
                    errorMsg(res.message || '<?php echo $this->lang->line('error_occurred_please_try_again'); ?>');
                    $btn.prop('disabled', false);
                }
            },
            error: function () {
                errorMsg('<?php echo $this->lang->line('error_occurred_please_try_again'); ?>');
                $btn.prop('disabled', false);
            }
        });
    });
})(jQuery);
</script>
