<?php
$currency_symbol = $this->customlib->getHospitalCurrencyFormat();
$payment_mode    = isset($payment_mode) ? $payment_mode : array();
$prefill_case    = isset($case_id) ? html_escape($case_id) : '';
$posting_type    = (isset($posting_type) && $posting_type === 'opd') ? 'opd' : 'ipd';
$sp_permissions  = isset($sp_permissions) ? $sp_permissions : array(
    'can_view' => true, 'can_edit' => true, 'can_save' => true, 'can_confirm' => true,
);
$can_edit     = !empty($sp_permissions['can_edit']);
$can_save     = !empty($sp_permissions['can_save']);
$can_confirm  = !empty($sp_permissions['can_confirm']);
$charge_types = isset($charge_types) ? $charge_types : array();
$page_title  = ($posting_type === 'opd')
    ? $this->lang->line('opd_service_posting')
    : $this->lang->line('ipd_service_posting');
?>
<link rel="stylesheet" href="<?php echo base_url(); ?>backend/dist/css/jquery-ui.css">
<style>
.sp-toolbar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; padding: 10px 12px; border-bottom: 1px solid var(--border, rgba(255,255,255,.08)); }
.sp-table th, .sp-table td { vertical-align: middle !important; }
.sp-table .form-control { height: 30px; padding: 2px 6px; font-size: 12px; width: 100%; min-width: 56px; }
.sp-table .sp-name-input { min-width: 160px; }
.sp-table .sp-code-input { min-width: 100px; }
.sp-table .sp-date-cell { min-width: 168px; white-space: nowrap; }
#sp_service_date { width: 175px; min-width: 175px; }
.sp-totals-bar { display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end; justify-content: space-between; }
.sp-totals-inline { display: flex; flex-wrap: wrap; gap: 18px; align-items: center; }
.sp-totals-inline .sp-titem.sp-disc-edit { min-width: 92px; }
.sp-totals-inline .sp-titem.sp-disc-edit input { height: 28px; padding: 2px 6px; font-size: 13px; font-weight: 600; width: 100%; }
.sp-totals-inline .sp-titem span { display: block; font-size: 11px; opacity: .75; }
.sp-totals-inline .sp-titem strong { font-size: 14px; }
.sp-badge-indent { display: inline-block; margin-left: 6px; padding: 1px 7px; border-radius: 3px; font-size: 10px; font-weight: 600; background: rgba(46, 125, 50, .18); color: #66bb6a; }
.sp-locked #sp_add_toolbar { display: none !important; }
.sp-actions .btn { margin-right: 8px; margin-bottom: 8px; }
.sp-search-row label { font-size: 12px; font-weight: 600; margin-bottom: 4px; display: block; }
.sp-placeholder-row td { height: 36px; opacity: .45; }
#sp_payment_modes .btn { min-width: 88px; }
</style>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex align-items-center flex-wrap gap-2">
                <h3 class="card-title mb-0">
                    <i class="fas fa-receipt me-1"></i><?php echo html_escape($page_title); ?>
                </h3>
                <div class="ms-auto">
                    <a href="<?php echo site_url('admin/bill/dashboard'); ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="fa fa-arrow-left"></i> <?php echo $this->lang->line('billing'); ?>
                    </a>
                </div>
            </div>
            <div class="card-body">
                <form id="sp_search_form" class="sp-search-row mb-3" onsubmit="return false;">
                    <div class="row g-2 align-items-end">
                        <div class="col-sm-6 col-md-3 col-lg-2">
                            <label for="sp_case_id"><?php echo $this->lang->line('case_id'); ?></label>
                            <input type="text" class="form-control form-control-sm" id="sp_case_id" value="<?php echo $prefill_case; ?>" placeholder="<?php echo $this->lang->line('enter_case_id'); ?>">
                        </div>
                        <?php if ($posting_type === 'ipd') { ?>
                        <div class="col-sm-6 col-md-3 col-lg-2">
                            <label for="sp_ip_number"><?php echo $this->lang->line('ip_number'); ?></label>
                            <input type="text" class="form-control form-control-sm" id="sp_ip_number" placeholder="<?php echo $this->lang->line('ip_number'); ?>">
                        </div>
                        <?php } else { ?>
                        <input type="hidden" id="sp_ip_number" value="">
                        <?php } ?>
                        <div class="col-sm-6 col-md-3 col-lg-2">
                            <label for="sp_mr_number"><?php echo $this->lang->line('mr_number'); ?></label>
                            <input type="text" class="form-control form-control-sm" id="sp_mr_number" placeholder="<?php echo $this->lang->line('mr_number'); ?>">
                        </div>
                        <div class="col-sm-6 col-md-3 col-lg-4">
                            <button type="button" class="btn btn-primary btn-sm" id="sp_btn_search">
                                <i class="fa fa-search"></i> <?php echo $this->lang->line('search'); ?>
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="sp_btn_clear">
                                <?php echo $this->lang->line('clear'); ?>
                            </button>
                        </div>
                    </div>
                </form>

                <div class="sh-form-card mb-3" id="sp_patient_card">
                    <div class="sh-card-header d-flex align-items-center">
                        <span class="sh-card-header-title"><i class="fa fa-user me-1"></i> <?php echo $this->lang->line('patient_details'); ?></span>
                        <span class="badge bg-secondary ms-auto" id="sp_disp_status"><?php echo $this->lang->line('draft'); ?></span>
                    </div>
                    <div class="sh-info-grid">
                        <div class="row g-0">
                            <div class="col-6 col-md-3 sh-info-item">
                                <span class="sh-info-label"><?php echo $this->lang->line('patient'); ?></span>
                                <span class="sh-info-value highlight" id="sp_patient_name">—</span>
                            </div>
                            <div class="col-6 col-md-2 sh-info-item">
                                <span class="sh-info-label"><?php echo $this->lang->line('case_id'); ?></span>
                                <span class="sh-info-value" id="sp_disp_case">—</span>
                            </div>
                            <div class="col-6 col-md-2 sh-info-item">
                                <span class="sh-info-label"><?php echo $this->lang->line('uhid'); ?></span>
                                <span class="sh-info-value" id="sp_disp_uhid">—</span>
                            </div>
                            <div class="col-6 col-md-2 sh-info-item">
                                <span class="sh-info-label"><?php echo $this->lang->line('ip_number'); ?></span>
                                <span class="sh-info-value" id="sp_disp_ip">—</span>
                            </div>
                            <div class="col-6 col-md-2 sh-info-item">
                                <span class="sh-info-label"><?php echo $this->lang->line('mr_number'); ?></span>
                                <span class="sh-info-value" id="sp_disp_mr">—</span>
                            </div>
                            <div class="col-6 col-md-3 sh-info-item">
                                <span class="sh-info-label"><?php echo $this->lang->line('bed'); ?></span>
                                <span class="sh-info-value" id="sp_disp_bed">—</span>
                            </div>
                            <div class="col-6 col-md-3 sh-info-item">
                                <span class="sh-info-label"><?php echo $this->lang->line('department'); ?></span>
                                <span class="sh-info-value" id="sp_disp_dept">—</span>
                            </div>
                            <div class="col-6 col-md-3 sh-info-item">
                                <span class="sh-info-label"><?php echo $this->lang->line('consultant_doctor'); ?></span>
                                <span class="sh-info-value" id="sp_disp_doctor">—</span>
                            </div>
                            <div class="col-6 col-md-3 sh-info-item">
                                <span class="sh-info-label"><?php echo $this->lang->line('tpa'); ?></span>
                                <span class="sh-info-value" id="sp_disp_org">—</span>
                            </div>
                            <div class="col-6 col-md-3 sh-info-item">
                                <span class="sh-info-label"><?php echo $this->lang->line('insurance_id'); ?></span>
                                <span class="sh-info-value" id="sp_disp_insurance">—</span>
                            </div>
                            <div class="col-6 col-md-3 sh-info-item">
                                <span class="sh-info-label"><?php echo $this->lang->line('discharge_date'); ?></span>
                                <span class="sh-info-value" id="sp_disp_discharge">—</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="sh-form-card mb-3" id="sp_grid_wrap">
                        <div class="sh-card-header">
                            <span class="sh-card-header-title"><i class="fa fa-list me-1"></i> <?php echo $this->lang->line('service'); ?></span>
                        </div>
                        <div class="sp-toolbar" id="sp_add_toolbar">
                            <select class="form-select form-select-sm" id="sp_service_code" style="width:160px;">
                                <option value=""><?php echo $this->lang->line('charge_type'); ?></option>
                                <?php foreach ($charge_types as $ct) { ?>
                                <option value="<?php echo html_escape($ct->charge_type); ?>"><?php echo html_escape($ct->charge_type); ?></option>
                                <?php } ?>
                                <option value="Pharmacy"><?php echo $this->lang->line('pharmacy'); ?></option>
                            </select>
                            <input type="text" class="form-control form-control-sm" id="sp_service_search" style="width:240px;" placeholder="<?php echo $this->lang->line('service_name'); ?>" disabled>
                            <input type="hidden" id="sp_item_id">
                            <input type="number" class="form-control form-control-sm" id="sp_qty" value="1" min="1" step="1" style="width:70px;" title="<?php echo $this->lang->line('qty'); ?>" placeholder="Qty">
                            <input type="number" class="form-control form-control-sm" id="sp_rate" value="" min="0" step="0.01" style="width:90px;" title="<?php echo $this->lang->line('rate'); ?>" placeholder="Rate">
                            <input type="number" class="form-control form-control-sm" id="sp_disc_pct" value="0" min="0" step="0.01" style="width:80px;" title="Disc %" placeholder="Disc %">
                            <select class="form-select form-select-sm" id="sp_add_reporting_doctor" style="width:180px;" title="<?php echo $this->lang->line('reporting_doctor'); ?>">
                                <option value=""><?php echo $this->lang->line('reporting_doctor'); ?></option>
                                <?php
                                $doctors = isset($doctors) ? $doctors : array();
                                foreach ($doctors as $dvalue) {
                                    echo '<option value="' . (int) $dvalue['id'] . '">' . html_escape(composeStaffNameByString($dvalue['name'], $dvalue['surname'], $dvalue['employee_id'])) . '</option>';
                                }
                                ?>
                            </select>
                            <input type="text" class="form-control form-control-sm datetime" id="sp_service_date" placeholder="<?php echo $this->lang->line('date'); ?>" title="<?php echo $this->lang->line('date'); ?>" value="<?php echo html_escape($this->customlib->YYYYMMDDHisTodateFormat(date('Y-m-d H:i:s'), $this->customlib->getHospitalTimeFormat())); ?>" autocomplete="off">
                            <button type="button" class="btn btn-primary btn-sm" id="sp_btn_add_line"><i class="fa fa-plus"></i> <?php echo $this->lang->line('add'); ?></button>
                        </div>
                        <div class="p-2">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover table-sm sp-table mb-0" id="sp_lines_table">
                                    <thead>
                                        <tr>
                                            <th><?php echo $this->lang->line('s_no'); ?></th>
                                            <th style="width:42px;"></th>
                                            <th><?php echo $this->lang->line('service_code'); ?></th>
                                            <th><?php echo $this->lang->line('service_name'); ?></th>
                                            <th><?php echo $this->lang->line('qty'); ?></th>
                                            <th><?php echo $this->lang->line('rate'); ?></th>
                                            <th class="text-end"><?php echo $this->lang->line('amount'); ?></th>
                                            <th>Disc %</th>
                                            <th class="text-end"><?php echo $this->lang->line('discount'); ?></th>
                                            <th class="text-end"><?php echo $this->lang->line('net_amount'); ?></th>
                                            <th><?php echo $this->lang->line('reporting_doctor'); ?></th>
                                            <th style="min-width:168px;"><?php echo $this->lang->line('date'); ?></th>
                                            <th><?php echo $this->lang->line('remark'); ?></th>
                                            <th style="width:70px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="sp_lines_body"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="sh-form-card mb-0">
                        <div class="sh-card-header">
                            <span class="sh-card-header-title"><i class="fas fa-calculator me-1"></i> <?php echo $this->lang->line('total'); ?></span>
                        </div>
                        <div class="p-3">
                            <div class="sp-totals-bar">
                                <div class="sp-totals-inline">
                                    <div class="sp-titem">
                                        <span><?php echo $this->lang->line('total'); ?></span>
                                        <strong id="sp_total_amount"><?php echo $currency_symbol; ?>0.00</strong>
                                    </div>
                                    <div class="sp-titem">
                                        <span><?php echo $this->lang->line('discount'); ?></span>
                                        <strong id="sp_total_discount"><?php echo $currency_symbol; ?>0.00</strong>
                                    </div>
                                    <div class="sp-titem sp-disc-edit">
                                        <span><?php echo $this->lang->line('discount'); ?> %</span>
                                        <input type="number" class="form-control form-control-sm" id="sp_overall_disc_pct" min="0" max="100" step="0.01" value="0" title="<?php echo $this->lang->line('discount'); ?> %">
                                    </div>
                                    <div class="sp-titem sp-disc-edit">
                                        <span><?php echo $this->lang->line('discount'); ?> (<?php echo html_escape($currency_symbol); ?>)</span>
                                        <input type="number" class="form-control form-control-sm" id="sp_overall_disc_amt" min="0" step="0.01" value="0" title="<?php echo $this->lang->line('discount'); ?> (<?php echo html_escape($currency_symbol); ?>)">
                                    </div>
                                    <div class="sp-titem">
                                        <span><?php echo $this->lang->line('net_amount'); ?></span>
                                        <strong id="sp_total_net"><?php echo $currency_symbol; ?>0.00</strong>
                                    </div>
                                    <div class="sp-titem">
                                        <span><?php echo $this->lang->line('paid_amount'); ?></span>
                                        <strong id="sp_total_paid"><?php echo $currency_symbol; ?>0.00</strong>
                                    </div>
                                    <div class="sp-titem">
                                        <span><?php echo $this->lang->line('advance'); ?></span>
                                        <strong id="sp_advance"><?php echo $currency_symbol; ?>0.00</strong>
                                    </div>
                                    <div class="sp-titem">
                                        <span><?php echo $this->lang->line('balance'); ?></span>
                                        <strong id="sp_balance"><?php echo $currency_symbol; ?>0.00</strong>
                                    </div>
                                </div>
                                <div style="min-width:220px; max-width:320px; flex:1;">
                                    <label class="form-label mb-1"><?php echo $this->lang->line('remark'); ?></label>
                                    <textarea class="form-control form-control-sm" id="sp_remarks" rows="2"></textarea>
                                </div>
                            </div>
                            <div class="sp-actions mt-3">
                                <button type="button" class="btn btn-outline-primary" id="sp_btn_save">
                                    <i class="fa fa-save"></i> <?php echo $this->lang->line('save_bill'); ?>
                                </button>
                                <button type="button" class="btn btn-primary" id="sp_btn_confirm"><?php echo $this->lang->line('confirm_bill'); ?></button>
                                <button type="button" class="btn btn-success" id="sp_btn_payment" style="display:none;"><?php echo $this->lang->line('payment'); ?></button>
                                <button type="button" class="btn btn-warning" id="sp_btn_reopen" style="display:none;"><?php echo $this->lang->line('reopen_bill'); ?></button>
                                <button type="button" class="btn btn-outline-secondary" id="sp_btn_print" style="display:none;" title="<?php echo $this->lang->line('print'); ?>">
                                    <i class="fa fa-print"></i> <?php echo $this->lang->line('print'); ?>
                                </button>
                                <a href="javascript:void(0);" class="btn btn-outline-danger" id="sp_btn_pdf" style="display:none;" target="_blank" title="<?php echo $this->lang->line('view_pdf'); ?>">
                                    <i class="fa fa-file-pdf"></i> <?php echo $this->lang->line('view_pdf'); ?>
                                </a>
                                <small class="text-muted ms-2" id="sp_last_saved"></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade sh-modal" id="spPaymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo $this->lang->line('payment'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="sp_payment_form">
                <div class="modal-body">
                    <input type="hidden" name="case_reference_id" id="sp_pay_case_id">
                    <input type="hidden" name="posting_type" id="sp_pay_posting_type" value="<?php echo html_escape($posting_type); ?>">
                    <div class="mb-3">
                        <label class="form-label"><?php echo $this->lang->line('amount'); ?> <small class="req">*</small></label>
                        <input type="number" step="0.01" min="0" class="form-control" name="amount" id="sp_pay_amount" required>
                        <span class="text-danger" id="error_amount"></span>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo $this->lang->line('payment_date'); ?> <small class="req">*</small></label>
                        <input type="text" class="form-control datetime" name="payment_date" id="sp_pay_date" required>
                        <span class="text-danger" id="error_payment_date"></span>
                    </div>
                    <div class="mb-3">
                        <label class="form-label d-block"><?php echo $this->lang->line('payment_mode'); ?> <small class="req">*</small></label>
                        <div id="sp_payment_modes" class="d-flex flex-wrap gap-2">
                            <?php
                            $preferred = array('Cash', 'UPI', 'Credit', 'Insurance');
                            foreach ($preferred as $mode_key) {
                                if (isset($payment_mode[$mode_key])) {
                                    echo '<button type="button" class="btn btn-sm btn-outline-primary sp-mode-btn" data-mode="' . html_escape($mode_key) . '">' . $payment_mode[$mode_key] . '</button>';
                                }
                            }
                            foreach ($payment_mode as $mode_key => $mode_label) {
                                if (in_array($mode_key, $preferred, true)) {
                                    continue;
                                }
                                echo '<button type="button" class="btn btn-sm btn-outline-secondary sp-mode-btn" data-mode="' . html_escape($mode_key) . '">' . $mode_label . '</button>';
                            }
                            ?>
                        </div>
                        <input type="hidden" name="payment_mode" id="sp_pay_mode" value="Cash">
                        <span class="text-danger" id="error_payment_mode"></span>
                    </div>
                    <div class="mb-3" id="sp_credit_doctor_wrap" style="display:none;">
                        <label class="form-label"><?php echo $this->lang->line('credit_doctor'); ?> <small class="req">*</small></label>
                        <select class="form-select" name="credit_doctor_id" id="sp_credit_doctor_id">
                            <option value=""><?php echo $this->lang->line('select'); ?></option>
                            <?php
                            $doctors = isset($doctors) ? $doctors : array();
                            foreach ($doctors as $dvalue) {
                                echo '<option value="' . (int) $dvalue['id'] . '">' . html_escape(composeStaffNameByString($dvalue['name'], $dvalue['surname'], $dvalue['employee_id'])) . '</option>';
                            }
                            ?>
                        </select>
                        <span class="text-danger" id="error_credit_doctor_id"></span>
                    </div>
                    <div class="mb-3" id="sp_insurance_wrap" style="display:none;">
                        <div class="mb-3">
                            <label class="form-label"><?php echo $this->lang->line('tpa'); ?> / <?php echo $this->lang->line('insurance'); ?> <small class="req">*</small></label>
                            <select class="form-select" name="organisation_id" id="sp_organisation_id">
                                <option value=""><?php echo $this->lang->line('select'); ?></option>
                                <?php
                                $organisations = isset($organisation) ? $organisation : array();
                                foreach ($organisations as $org) {
                                    echo '<option value="' . (int) $org['id'] . '">' . html_escape($org['organisation_name']) . '</option>';
                                }
                                ?>
                            </select>
                            <span class="text-danger" id="error_organisation_id"></span>
                        </div>
                        <div class="mb-0">
                            <label class="form-label"><?php echo $this->lang->line('insurance_id'); ?> <small class="req">*</small></label>
                            <input type="text" class="form-control" name="insurance_id" id="sp_insurance_id" placeholder="<?php echo $this->lang->line('insurance_id'); ?>">
                            <span class="text-danger" id="error_insurance_id"></span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo $this->lang->line('payment_type'); ?></label>
                        <select class="form-select" name="payment_type" id="sp_pay_type">
                            <option value="payment"><?php echo $this->lang->line('payment'); ?></option>
                            <option value="advance"><?php echo $this->lang->line('advance'); ?></option>
                        </select>
                        <small class="text-muted"><?php echo $this->lang->line('advance_balance'); ?>: <span id="sp_pay_advance_bal">0</span></small>
                    </div>
                    <div class="mb-0">
                        <label class="form-label"><?php echo $this->lang->line('note'); ?></label>
                        <textarea class="form-control" name="note" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?php echo $this->lang->line('cancel'); ?></button>
                    <button type="submit" class="btn btn-primary" id="sp_pay_submit"><?php echo $this->lang->line('save'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script type="text/javascript">
(function ($) {
    var currency = <?php echo json_encode($currency_symbol); ?>;
    var currentCaseId = <?php echo json_encode($prefill_case); ?>;
    var postingType = <?php echo json_encode($posting_type); ?>;
    var spPerms = <?php echo json_encode(array(
        'can_edit' => (bool) $can_edit,
        'can_save' => (bool) $can_save,
        'can_confirm' => (bool) $can_confirm,
    )); ?>;
    var isConfirmed = false;
    var currentLines = [];
    var currentTotals = {};
    var currentAdvance = 0;
    var currentPatient = {};
    var overallDiscMode = 'percent';
    var overallDiscSaveTimer = null;
    var doctorOptions = <?php
        $doc_opts = array();
        $doctors_list = isset($doctors) ? $doctors : array();
        foreach ($doctors_list as $dvalue) {
            $doc_opts[] = array(
                'id'   => (int) $dvalue['id'],
                'name' => composeStaffNameByString($dvalue['name'], $dvalue['surname'], $dvalue['employee_id']),
            );
        }
        echo json_encode($doc_opts);
    ?>;

    function withPostingType(data) {
        data = data || {};
        data.posting_type = postingType;
        return data;
    }

    function doctorSelectHtml(selectedId, canEdit) {
        selectedId = selectedId ? String(selectedId) : '';
        if (!canEdit) {
            var label = '—';
            $.each(doctorOptions, function (i, d) {
                if (String(d.id) === selectedId) { label = d.name; }
            });
            return $('<div>').text(label).html();
        }
        var html = '<select class="form-select form-select-sm sp-reporting-doctor" style="min-width:140px;">';
        html += '<option value=""><?php echo $this->lang->line('select'); ?></option>';
        $.each(doctorOptions, function (i, d) {
            html += '<option value="' + d.id + '"' + (String(d.id) === selectedId ? ' selected' : '') + '>' + $('<div>').text(d.name).html() + '</option>';
        });
        html += '</select>';
        return html;
    }

    function money(v) {
        var n = parseFloat(v);
        if (isNaN(n)) n = 0;
        return currency + n.toFixed(2);
    }

    function renderPlaceholderRows() {
        var html = '';
        for (var i = 1; i <= 8; i++) {
            html += '<tr class="sp-placeholder-row"><td>' + i + '</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>';
        }
        $('#sp_lines_body').html(html);
    }

    function setConfirmedUI(confirmed) {
        isConfirmed = !!confirmed;
        var $badge = $('#sp_disp_status');
        if (isConfirmed) {
            $('#sp_grid_wrap').addClass('sp-locked');
            $('#sp_add_toolbar').hide();
            $('#sp_btn_save').hide();
            $('#sp_btn_confirm').hide();
            $('#sp_btn_payment').show();
            if (spPerms.can_edit) {
                $('#sp_btn_reopen').show();
            } else {
                $('#sp_btn_reopen').hide();
            }
            $badge.removeClass('bg-secondary bg-info').addClass('bg-success').text(<?php echo json_encode($this->lang->line('confirmed')); ?>);
            $('#sp_lines_body .sp-del-btn, #sp_lines_body .sp-save-btn').prop('disabled', true).hide();
            $('#sp_lines_body input, #sp_lines_body textarea, #sp_lines_body select').prop('disabled', true);
            $('#sp_overall_disc_pct, #sp_overall_disc_amt').prop('disabled', true);
        } else {
            $('#sp_grid_wrap').removeClass('sp-locked');
            if (spPerms.can_edit) {
                $('#sp_add_toolbar').show();
            } else {
                $('#sp_add_toolbar').hide();
            }
            if (spPerms.can_save) {
                $('#sp_btn_save').show();
            } else {
                $('#sp_btn_save').hide();
            }
            if (spPerms.can_confirm) {
                $('#sp_btn_confirm').show();
            } else {
                $('#sp_btn_confirm').hide();
            }
            $('#sp_btn_payment').hide();
            $('#sp_btn_reopen').hide();
            $badge.removeClass('bg-success').addClass('bg-secondary').text(<?php echo json_encode($this->lang->line('draft')); ?>);
            $('#sp_overall_disc_pct, #sp_overall_disc_amt').prop('disabled', !spPerms.can_edit);
        }
    }

    function showLastSaved(draftOrConfirm) {
        if (!draftOrConfirm || !draftOrConfirm.confirmed_at) {
            $('#sp_last_saved').text('');
            return;
        }
        var label = (draftOrConfirm.status === 'confirmed')
            ? <?php echo json_encode($this->lang->line('confirmed')); ?>
            : <?php echo json_encode($this->lang->line('last_saved')); ?>;
        $('#sp_last_saved').text(label + ': ' + draftOrConfirm.confirmed_at);
        if (!isConfirmed && draftOrConfirm.status === 'draft') {
            $('#sp_disp_status').removeClass('bg-secondary bg-success').addClass('bg-info').text(<?php echo json_encode($this->lang->line('draft_saved')); ?>);
        }
    }

    function calcRowAmounts($tr) {
        var qty = parseFloat($tr.find('.sp-qty').val()) || 0;
        var rate = parseFloat($tr.find('.sp-rate').val()) || 0;
        var discPct = parseFloat($tr.find('.sp-disc').val()) || 0;
        var amount = rate * (qty > 0 ? qty : 1);
        var discAmt = (amount * discPct) / 100;
        var net = amount - discAmt;
        $tr.find('.sp-amount-disp').text(money(amount));
        $tr.find('.sp-disc-amt-disp').text(money(discAmt));
        $tr.find('.sp-net-disp').text(money(net));
    }

    function renderLines(lines) {
        currentLines = lines || [];
        var $body = $('#sp_lines_body').empty();
        if (!currentLines.length) {
            renderPlaceholderRows();
            return;
        }
        $.each(currentLines, function (i, row) {
            var canEdit = !isConfirmed && spPerms.can_edit;
            var indentBadge = row.from_indent ? '<span class="sp-badge-indent"><?php echo $this->lang->line('from_indent'); ?></span>' : '';
            var delBtn = canEdit
                ? '<button type="button" class="btn btn-danger btn-sm sp-del-btn" data-module="' + row.module + '" data-id="' + row.module_id + '" title="Remove"><i class="fa fa-times"></i></button>'
                : '';
            var saveBtn = canEdit
                ? '<button type="button" class="btn btn-primary btn-sm sp-save-btn" data-module="' + row.module + '" data-id="' + row.module_id + '" title="Save"><i class="fa fa-save"></i></button>'
                : '';

            var codeInput = canEdit
                ? '<input type="text" class="form-control form-control-sm sp-code-input" value="' + $('<div>').text(row.service_code || '').html() + '" readonly>'
                : $('<div>').text(row.service_code || '').html();
            var nameInput = canEdit
                ? '<input type="text" class="form-control form-control-sm sp-name-input sp-service-name" value="' + $('<div>').text(row.service_name || '').html() + '">'
                : $('<div>').text(row.service_name || '').html();
            var qtyInput = canEdit
                ? '<input type="number" class="form-control form-control-sm sp-qty" min="0.01" step="0.01" value="' + row.qty + '">'
                : row.qty;
            var rateInput = canEdit
                ? '<input type="number" class="form-control form-control-sm sp-rate" min="0" step="0.01" value="' + row.rate + '">'
                : money(row.rate);
            var discInput = canEdit
                ? '<input type="number" class="form-control form-control-sm sp-disc" min="0" step="0.01" value="' + row.discount_percent + '">'
                : row.discount_percent;
            var remarksInput = canEdit
                ? '<input type="text" class="form-control form-control-sm sp-remarks" value="' + $('<div>').text(row.remarks || '').html() + '">'
                : $('<div>').text(row.remarks || '').html();
            var doctorInput = doctorSelectHtml(row.reporting_doctor, canEdit);

            $body.append(
                '<tr data-module="' + row.module + '" data-id="' + row.module_id + '">' +
                '<td>' + (i + 1) + '</td>' +
                '<td>' + delBtn + '</td>' +
                '<td>' + codeInput + indentBadge + '</td>' +
                '<td>' + nameInput + '</td>' +
                '<td style="width:80px;">' + qtyInput + '</td>' +
                '<td style="width:100px;">' + rateInput + '</td>' +
                '<td class="text-end sp-amount-disp">' + money(row.amount) + '</td>' +
                '<td style="width:80px;">' + discInput + '</td>' +
                '<td class="text-end sp-disc-amt-disp">' + money(row.discount_amount) + '</td>' +
                '<td class="text-end fw-semibold sp-net-disp">' + money(row.net_amount) + '</td>' +
                '<td>' + doctorInput + '</td>' +
                '<td class="sp-date-cell">' + $('<div>').text(row.service_date || '').html() + '</td>' +
                '<td>' + remarksInput + '</td>' +
                '<td class="text-nowrap">' + saveBtn + '</td>' +
                '</tr>'
            );
        });
    }

    function overallDiscountPayload() {
        return {
            overall_discount_percent: $('#sp_overall_disc_pct').val() || 0,
            overall_discount_amount: $('#sp_overall_disc_amt').val() || 0,
            overall_discount_mode: overallDiscMode
        };
    }

    function lineNetFromTotals() {
        if (currentTotals.line_net !== undefined && currentTotals.line_net !== null) {
            return Math.max(0, parseFloat(currentTotals.line_net) || 0);
        }
        var gross = parseFloat(currentTotals.total_amount) || 0;
        var lineDisc = parseFloat(currentTotals.line_discount) || 0;
        return Math.max(0, gross - lineDisc);
    }

    function applyOverallDiscountToTotals() {
        var lineDisc = parseFloat(currentTotals.line_discount);
        if (isNaN(lineDisc)) { lineDisc = 0; }
        var pct = parseFloat($('#sp_overall_disc_pct').val()) || 0;
        var amt = parseFloat($('#sp_overall_disc_amt').val()) || 0;
        var lineNet = lineNetFromTotals();
        if (pct < 0) { pct = 0; }
        if (pct > 100) { pct = 100; }
        if (amt < 0) { amt = 0; }
        if (overallDiscMode === 'percent') {
            amt = (lineNet * pct) / 100;
            $('#sp_overall_disc_amt').val(amt.toFixed(2));
        } else {
            if (amt > lineNet) { amt = lineNet; }
            pct = lineNet > 0 ? (amt / lineNet) * 100 : 0;
            $('#sp_overall_disc_pct').val(pct.toFixed(2));
            $('#sp_overall_disc_amt').val(amt.toFixed(2));
        }
        var totalDisc = lineDisc + amt;
        var net = Math.max(0, lineNet - amt);
        var paid = parseFloat(currentTotals.total_paid) || 0;
        var refund = parseFloat(currentTotals.total_refund) || 0;
        var bal = Math.max(0, net - paid - currentAdvance + refund);
        currentTotals.overall_discount_percent = pct;
        currentTotals.overall_discount_amount = amt;
        currentTotals.overall_discount_mode = overallDiscMode;
        currentTotals.total_discount = totalDisc;
        currentTotals.total_net = net;
        currentTotals.balance = bal;
        $('#sp_total_discount').text(money(totalDisc));
        $('#sp_total_net').text(money(net));
        $('#sp_total_paid').text(money(paid));
        $('#sp_advance').text(money(currentAdvance));
        $('#sp_balance').text(money(bal));
    }

    function renderTotals(totals, advance) {
        currentTotals = totals || {};
        currentAdvance = parseFloat(advance || 0);
        if (currentTotals.advance_amount !== undefined && !isNaN(parseFloat(currentTotals.advance_amount))) {
            currentAdvance = parseFloat(currentTotals.advance_amount);
        }
        overallDiscMode = currentTotals.overall_discount_mode === 'amount' ? 'amount' : 'percent';
        $('#sp_overall_disc_pct').val(parseFloat(currentTotals.overall_discount_percent || 0).toFixed(2));
        $('#sp_overall_disc_amt').val(parseFloat(currentTotals.overall_discount_amount || 0).toFixed(2));
        $('#sp_total_amount').text(money(currentTotals.total_amount || 0));
        $('#sp_total_discount').text(money(currentTotals.total_discount || 0));
        $('#sp_total_net').text(money(currentTotals.total_net || 0));
        $('#sp_total_paid').text(money(currentTotals.total_paid || 0));
        $('#sp_advance').text(money(currentAdvance));
        $('#sp_balance').text(money(currentTotals.balance || 0));
    }

    function clearPatientHeader() {
        currentPatient = {};
        $('#sp_patient_name, #sp_disp_case, #sp_disp_uhid, #sp_disp_ip, #sp_disp_mr, #sp_disp_bed, #sp_disp_dept, #sp_disp_doctor, #sp_disp_org, #sp_disp_insurance, #sp_disp_discharge').text('—');
        $('#sp_btn_print, #sp_btn_pdf').hide();
        $('#sp_btn_pdf').attr('href', 'javascript:void(0);');
        $('#sp_remarks').val('');
        $('#sp_last_saved').text('');
        $('#sp_overall_disc_pct').val('0.00');
        $('#sp_overall_disc_amt').val('0.00');
        overallDiscMode = 'percent';
        $('#sp_service_code').val('').prop('disabled', false);
        $('#sp_item_id').val('');
        $('#sp_service_search').val('').prop('disabled', true);
        $('#sp_rate').val('');
        setConfirmedUI(false);
        renderLines([]);
        renderTotals({}, 0);
    }

    function updatePrintPdfButtons() {
        if (currentCaseId) {
            $('#sp_btn_print').show();
            $('#sp_btn_pdf').show().attr('href', base_url + 'admin/bill/download_patient_bill_pdf/' + currentCaseId + '?posting_type=' + encodeURIComponent(postingType));
        } else {
            $('#sp_btn_print, #sp_btn_pdf').hide();
            $('#sp_btn_pdf').attr('href', 'javascript:void(0);');
        }
    }

    function doctorDisplayName(p) {
        if (!p) { return '—'; }
        var name = $.trim((p.doctor_name || '') + ' ' + (p.doctor_surname || ''));
        if (p.doctor_employee_id) {
            name += (name ? ' (' + p.doctor_employee_id + ')' : p.doctor_employee_id);
        }
        return name || '—';
    }

    function applyMappedChargeType(mappedType, isNewCase) {
        if (postingType !== 'ipd') {
            return;
        }
        mappedType = $.trim(mappedType || '');
        var $select = $('#sp_service_code');
        if (mappedType && $select.find('option').filter(function () {
            return $(this).val() === mappedType;
        }).length) {
            if (isNewCase || !$select.val()) {
                $select.prop('disabled', false);
                $select.val(mappedType).trigger('change');
            }
            $select.prop('disabled', true);
            return;
        }
        $select.prop('disabled', false);
        if (isNewCase) {
            $select.val('').trigger('change');
        }
    }

    function formatDischargeDate(val) {
        if (!val || val === '0000-00-00' || val === '0000-00-00 00:00:00') {
            return '—';
        }
        return val;
    }

    function loadPosting() {
        var payload = withPostingType({
            case_id: $('#sp_case_id').val(),
            ip_number: $('#sp_ip_number').val(),
            mr_number: $('#sp_mr_number').val()
        });
        if (!payload.case_id && !payload.ip_number && !payload.mr_number) {
            errorMsg(<?php echo json_encode($this->lang->line('please_select_case_or_patient')); ?>);
            return;
        }
        $.ajax({
            url: base_url + 'admin/bill/load_service_posting',
            type: 'POST',
            data: payload,
            dataType: 'json',
            beforeSend: function () { $('#sp_btn_search').prop('disabled', true); },
            success: function (res) {
                if (!res.status) {
                    errorMsg(res.message || <?php echo json_encode($this->lang->line('no_record_found')); ?>);
                    clearPatientHeader();
                    currentCaseId = '';
                    return;
                }
                if (res.permissions) {
                    spPerms.can_edit = !!res.permissions.can_edit;
                    spPerms.can_save = !!res.permissions.can_save;
                    spPerms.can_confirm = !!res.permissions.can_confirm;
                }
                var previousCaseId = currentCaseId;
                currentCaseId = res.case_id;
                $('#sp_case_id').val(res.case_id);
                var p = res.patient || {};
                currentPatient = p;
                $('#sp_patient_name').text((p.patient_name || '') + (p.patient_id ? ' (' + p.patient_id + ')' : ''));
                $('#sp_disp_case').text(res.case_id);
                $('#sp_disp_uhid').text(p.patient_unique_id || p.patient_id || '—');
                $('#sp_disp_ip').text(res.ipd_no || res.ipd_id || '—');
                $('#sp_disp_mr').text(p.patient_unique_id || p.patient_id || '—');
                var bed = ((p.bedgroup_name || '') + (p.bed_name ? ' / ' + p.bed_name : '')).replace(/^ \/ /, '') || '—';
                $('#sp_disp_bed').text(bed);
                $('#sp_disp_dept').text(p.department_name || '—');
                $('#sp_disp_doctor').text(doctorDisplayName(p));
                $('#sp_disp_org').text(p.organisation_name || '—');
                $('#sp_disp_insurance').text(p.insurance_id || '—');
                $('#sp_disp_discharge').text(formatDischargeDate(p.discharge_date_display || p.discharge_date));
                updatePrintPdfButtons();
                setConfirmedUI(res.is_confirmed == 1);
                renderLines(res.lines);
                renderTotals(res.totals, res.advance_amount);
                $('#sp_remarks').val(res.remarks || '');
                showLastSaved(res.is_confirmed == 1 ? res.confirmation : (res.draft || res.confirmation));
                applyMappedChargeType(res.mapped_charge_type, String(previousCaseId) !== String(res.case_id));
            },
            error: function () { errorMsg(<?php echo json_encode($this->lang->line('warning_message')); ?>); },
            complete: function () { $('#sp_btn_search').prop('disabled', false); }
        });
    }

    renderPlaceholderRows();
    setConfirmedUI(false);

    $('#sp_btn_search').on('click', loadPosting);
    $('#sp_search_form').on('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            loadPosting();
        }
    });
    $('#sp_btn_clear').on('click', function () {
        $('#sp_case_id, #sp_ip_number, #sp_mr_number').val('');
        currentCaseId = '';
        clearPatientHeader();
    });

    if (currentCaseId) {
        loadPosting();
    }

    $('#sp_service_code').on('change', function () {
        var code = $(this).val();
        $('#sp_item_id').val('');
        $('#sp_service_search').val('').prop('disabled', !code);
        $('#sp_rate').val('');
    });

    $('#sp_service_search').autocomplete({
        source: function (request, response) {
            var code = $('#sp_service_code').val();
            if (!code) {
                response([]);
                return;
            }
            $.ajax({
                url: base_url + 'admin/bill/search_service_items',
                dataType: 'json',
                data: withPostingType({ service_code: code, term: request.term }),
                success: function (data) {
                    response($.map(data.result || [], function (item) {
                        return { label: item.name + ' — ' + item.rate, value: item.name, id: item.id, rate: item.rate };
                    }));
                }
            });
        },
        minLength: 1,
        select: function (e, ui) {
            $('#sp_item_id').val(ui.item.id);
            $('#sp_rate').val(ui.item.rate);
        }
    });

    $('#sp_btn_add_line').on('click', function () {
        if (!currentCaseId) {
            errorMsg(<?php echo json_encode($this->lang->line('please_select_case_or_patient')); ?>);
            return;
        }
        if (isConfirmed) return;
        var code = $('#sp_service_code').val();
        var itemId = $('#sp_item_id').val();
        if (!code || !itemId) {
            errorMsg(<?php echo json_encode($this->lang->line('please_select_service')); ?>);
            return;
        }
        $.ajax({
            url: base_url + 'admin/bill/add_service_line',
            type: 'POST',
            dataType: 'json',
            data: withPostingType({
                case_id: currentCaseId,
                service_code: code,
                item_id: itemId,
                qty: $('#sp_qty').val() || 1,
                rate: $('#sp_rate').val(),
                discount_percentage: $('#sp_disc_pct').val() || 0,
                reporting_doctor: $('#sp_add_reporting_doctor').val() || '',
                service_date: $('#sp_service_date').val() || '',
                remarks: ''
            }),
            success: function (res) {
                if (res.status == 1 || res.status === '1') {
                    successMsg(res.message);
                    $('#sp_item_id').val('');
                    $('#sp_service_search').val('');
                    loadPosting();
                } else {
                    errorMsg(res.message || <?php echo json_encode($this->lang->line('warning_message')); ?>);
                }
            }
        });
    });

    $(document).on('input change', '.sp-qty, .sp-rate, .sp-disc', function () {
        calcRowAmounts($(this).closest('tr'));
    });

    $(document).on('click', '.sp-save-btn', function () {
        if (isConfirmed) return;
        var $tr = $(this).closest('tr');
        $.ajax({
            url: base_url + 'admin/bill/update_service_line',
            type: 'POST',
            dataType: 'json',
            data: withPostingType({
                case_id: currentCaseId,
                module: $(this).data('module'),
                id: $(this).data('id'),
                qty: $tr.find('.sp-qty').val(),
                apply_charge: $tr.find('.sp-rate').val(),
                discount_percentage: $tr.find('.sp-disc').val(),
                remarks: $tr.find('.sp-remarks').val(),
                reporting_doctor: $tr.find('.sp-reporting-doctor').val() || ''
            }),
            success: function (res) {
                if (res.status == 1) {
                    successMsg(res.message);
                    loadPosting();
                } else {
                    errorMsg(res.message || <?php echo json_encode($this->lang->line('warning_message')); ?>);
                }
            }
        });
    });

    $(document).on('click', '.sp-del-btn', function () {
        if (isConfirmed) return;
        if (!confirm(<?php echo json_encode($this->lang->line('are_you_sure')); ?>)) return;
        $.ajax({
            url: base_url + 'admin/bill/delete_service_line',
            type: 'POST',
            dataType: 'json',
            data: withPostingType({ case_id: currentCaseId, module: $(this).data('module'), id: $(this).data('id') }),
            success: function (res) {
                if (res.status == 1) {
                    successMsg(res.message);
                    loadPosting();
                } else {
                    errorMsg(res.message || <?php echo json_encode($this->lang->line('warning_message')); ?>);
                }
            }
        });
    });

    $('#sp_btn_save').on('click', function () {
        if (!currentCaseId) {
            errorMsg(<?php echo json_encode($this->lang->line('please_select_case_or_patient')); ?>);
            return;
        }
        if (isConfirmed) return;
        var $btn = $(this).prop('disabled', true);
        $.ajax({
            url: base_url + 'admin/bill/save_service_bill',
            type: 'POST',
            dataType: 'json',
            data: withPostingType($.extend({ case_id: currentCaseId, remarks: $('#sp_remarks').val() }, overallDiscountPayload())),
            success: function (res) {
                if (res.status == 1) {
                    successMsg(res.message);
                    showLastSaved({ status: 'draft', confirmed_at: res.saved_at });
                    $('#sp_disp_status').removeClass('bg-secondary bg-success').addClass('bg-info').text(<?php echo json_encode($this->lang->line('draft_saved')); ?>);
                } else {
                    errorMsg(res.message || <?php echo json_encode($this->lang->line('warning_message')); ?>);
                }
            },
            complete: function () { $btn.prop('disabled', false); }
        });
    });

    $('#sp_btn_confirm').on('click', function () {
        if (!currentCaseId) {
            errorMsg(<?php echo json_encode($this->lang->line('please_select_case_or_patient')); ?>);
            return;
        }
        if (isConfirmed) return;
        if (!currentLines.length) {
            errorMsg(<?php echo json_encode($this->lang->line('no_record_found')); ?>);
            return;
        }
        if (!confirm(<?php echo json_encode($this->lang->line('confirm_bill_message')); ?>)) return;
        $.ajax({
            url: base_url + 'admin/bill/confirm_service_bill',
            type: 'POST',
            dataType: 'json',
            data: withPostingType($.extend({ case_id: currentCaseId, remarks: $('#sp_remarks').val() }, overallDiscountPayload())),
            success: function (res) {
                if (res.status == 1) {
                    successMsg(res.message);
                    setConfirmedUI(true);
                    openPaymentModal(res);
                } else {
                    errorMsg(res.message || <?php echo json_encode($this->lang->line('warning_message')); ?>);
                }
            }
        });
    });

    $('#sp_btn_reopen').on('click', function () {
        if (!currentCaseId) return;
        $.ajax({
            url: base_url + 'admin/bill/reopen_service_bill',
            type: 'POST',
            dataType: 'json',
            data: withPostingType({ case_id: currentCaseId }),
            success: function (res) {
                if (res.status == 1) {
                    successMsg(res.message);
                    loadPosting();
                } else {
                    errorMsg(res.message || <?php echo json_encode($this->lang->line('warning_message')); ?>);
                }
            }
        });
    });

    function persistOverallDiscount(silent) {
        if (!currentCaseId || isConfirmed || !spPerms.can_save) { return; }
        $.ajax({
            url: base_url + 'admin/bill/save_service_bill',
            type: 'POST',
            dataType: 'json',
            data: withPostingType($.extend({ case_id: currentCaseId, remarks: $('#sp_remarks').val() }, overallDiscountPayload())),
            success: function (res) {
                if (res.status == 1 && !silent) {
                    showLastSaved({ status: 'draft', confirmed_at: res.saved_at });
                }
            }
        });
    }

    $('#sp_overall_disc_pct').on('input', function () {
        if (isConfirmed) { return; }
        overallDiscMode = 'percent';
        applyOverallDiscountToTotals();
        clearTimeout(overallDiscSaveTimer);
        overallDiscSaveTimer = setTimeout(function () { persistOverallDiscount(true); }, 600);
    });

    $('#sp_overall_disc_amt').on('input', function () {
        if (isConfirmed) { return; }
        overallDiscMode = 'amount';
        applyOverallDiscountToTotals();
        clearTimeout(overallDiscSaveTimer);
        overallDiscSaveTimer = setTimeout(function () { persistOverallDiscount(true); }, 600);
    });

    $('#sp_btn_print').on('click', function () {
        if (!currentCaseId) { return; }
        var $this = $(this);
        $.ajax({
            url: base_url + 'admin/bill/print_patient_bill',
            type: 'POST',
            data: withPostingType({ case_id: currentCaseId }),
            dataType: 'json',
            beforeSend: function () { $this.prop('disabled', true); },
            success: function (data) {
                if (data.status == 1 && typeof popup === 'function') {
                    popup(data.page);
                } else {
                    errorMsg(<?php echo json_encode($this->lang->line('error_occurred_please_try_again')); ?>);
                }
            },
            error: function () {
                errorMsg(<?php echo json_encode($this->lang->line('error_occurred_please_try_again')); ?>);
            },
            complete: function () { $this.prop('disabled', false); }
        });
    });

    // Persist reporting doctor when changed on a line (without requiring full row save)
    $(document).on('change', '.sp-reporting-doctor', function () {
        if (isConfirmed || !currentCaseId) { return; }
        var $tr = $(this).closest('tr');
        var module = $tr.data('module');
        var id = $tr.data('id');
        if (!module || !id) { return; }
        $.ajax({
            url: base_url + 'admin/bill/update_service_line',
            type: 'POST',
            dataType: 'json',
            data: withPostingType({
                case_id: currentCaseId,
                module: module,
                id: id,
                qty: $tr.find('.sp-qty').val(),
                apply_charge: $tr.find('.sp-rate').val(),
                discount_percentage: $tr.find('.sp-disc').val(),
                remarks: $tr.find('.sp-remarks').val(),
                reporting_doctor: $(this).val() || ''
            }),
            success: function (res) {
                if (res.status == 1) {
                    successMsg(res.message);
                } else {
                    errorMsg(res.message || <?php echo json_encode($this->lang->line('warning_message')); ?>);
                }
            }
        });
    });

    function toggleCreditDoctorField(mode) {
        if (mode === 'Credit') {
            $('#sp_credit_doctor_wrap').show();
            $('#sp_credit_doctor_id').prop('required', true);
            var prefill = $('#sp_add_reporting_doctor').val() || '';
            if (!prefill) {
                var $first = $('#sp_lines_body .sp-reporting-doctor').first();
                if ($first.length) { prefill = $first.val() || ''; }
            }
            if (prefill && !$('#sp_credit_doctor_id').val()) {
                $('#sp_credit_doctor_id').val(prefill);
            }
        } else {
            $('#sp_credit_doctor_wrap').hide();
            $('#sp_credit_doctor_id').prop('required', false).val('');
            $('#error_credit_doctor_id').text('');
        }
    }

    function toggleInsuranceFields(mode) {
        if (mode === 'Insurance') {
            $('#sp_insurance_wrap').show();
            $('#sp_organisation_id').prop('required', true);
            $('#sp_insurance_id').prop('required', true);
            if (currentPatient.organisation_id) {
                $('#sp_organisation_id').val(String(currentPatient.organisation_id));
            }
            if (currentPatient.insurance_id) {
                $('#sp_insurance_id').val(currentPatient.insurance_id);
            }
        } else {
            $('#sp_insurance_wrap').hide();
            $('#sp_organisation_id').prop('required', false).val('');
            $('#sp_insurance_id').prop('required', false).val('');
            $('#error_organisation_id, #error_insurance_id').text('');
        }
    }

    function openPaymentModal(res) {
        $('#sp_pay_case_id').val(currentCaseId);
        var bal = res && res.balance !== undefined ? res.balance : (currentTotals.balance || 0);
        $('#sp_pay_amount').val(parseFloat(bal).toFixed(2));
        $('#sp_pay_advance_bal').text(money(res && res.advance_amount !== undefined ? res.advance_amount : currentAdvance));
        $('#sp_pay_mode').val('Cash');
        $('#error_amount, #error_payment_date, #error_payment_mode, #error_credit_doctor_id, #error_organisation_id, #error_insurance_id').text('');
        $('.sp-mode-btn').removeClass('active btn-primary').addClass('btn-outline-primary');
        $('.sp-mode-btn[data-mode="Cash"]').removeClass('btn-outline-primary').addClass('active btn-primary');
        toggleCreditDoctorField('Cash');
        toggleInsuranceFields('Cash');
        if (typeof $.fn.datetimepicker !== 'undefined') {
            $('#sp_pay_date').datetimepicker({ format: 'DD/MM/YYYY HH:mm' });
        }
        var now = new Date();
        var pad = function (n) { return n < 10 ? '0' + n : n; };
        $('#sp_pay_date').val(pad(now.getDate()) + '/' + pad(now.getMonth() + 1) + '/' + now.getFullYear() + ' ' + pad(now.getHours()) + ':' + pad(now.getMinutes()));
        $('#spPaymentModal').modal('show');
    }

    $('#sp_btn_payment').on('click', function () {
        openPaymentModal({ balance: currentTotals.balance, advance_amount: currentAdvance });
    });

    $(document).on('click', '.sp-mode-btn', function () {
        $('.sp-mode-btn').removeClass('active btn-primary').addClass('btn-outline-primary btn-outline-secondary');
        $(this).removeClass('btn-outline-primary btn-outline-secondary').addClass('active btn-primary');
        var mode = $(this).data('mode');
        $('#sp_pay_mode').val(mode);
        toggleCreditDoctorField(mode);
        toggleInsuranceFields(mode);
    });

    $('#sp_payment_form').on('submit', function (e) {
        e.preventDefault();
        $('#error_amount, #error_payment_date, #error_payment_mode, #error_credit_doctor_id, #error_organisation_id, #error_insurance_id').text('');
        if ($('#sp_pay_mode').val() === 'Credit' && !$('#sp_credit_doctor_id').val()) {
            $('#error_credit_doctor_id').text(<?php echo json_encode($this->lang->line('credit_doctor') . ' is required'); ?>);
            return;
        }
        if ($('#sp_pay_mode').val() === 'Insurance') {
            if (!$('#sp_organisation_id').val()) {
                $('#error_organisation_id').text(<?php echo json_encode($this->lang->line('tpa') . ' is required'); ?>);
                return;
            }
            if (!$.trim($('#sp_insurance_id').val() || '')) {
                $('#error_insurance_id').text(<?php echo json_encode($this->lang->line('insurance_id') . ' is required'); ?>);
                return;
            }
        }
        $.ajax({
            url: base_url + 'admin/bill/service_posting_payment',
            type: 'POST',
            dataType: 'json',
            data: $(this).serialize(),
            success: function (res) {
                if (res.status === 'success') {
                    successMsg(res.message);
                    $('#spPaymentModal').modal('hide');
                    loadPosting();
                } else {
                    if (res.error) {
                        $.each(res.error, function (k, v) { $('#error_' + k).html(v); });
                    }
                    errorMsg(res.message || <?php echo json_encode($this->lang->line('warning_message')); ?>);
                }
            }
        });
    });
})(jQuery);
</script>
