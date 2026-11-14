<div class="content-wrapper">
    <section class="content-header">
        <h1><?php echo ucfirst($department); ?> IPD Indents</h1>
    </section>
    <section class="content">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Requested items</h3></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-bordered">
                        <thead><tr>
                            <th>Date</th><th>Patient / IPD</th><th>Item</th><th>Request details</th><th>Status</th><th>Action</th>
                        </tr></thead>
                        <tbody>
                        <?php if (empty($indents)) { ?>
                            <tr><td colspan="6" class="text-center">No indents found.</td></tr>
                        <?php } foreach ($indents as $item) { ?>
                            <tr>
                                <td><?php echo html_escape($item['indent_date']); ?></td>
                                <td>
                                    <?php echo html_escape($item['patient_name']); ?><br>
                                    <small>IPD: <?php echo (int) $item['ipd_id']; ?> | Patient: <?php echo (int) $item['patient_id']; ?></small>
                                </td>
                                <td>
                                    <?php echo html_escape($item['item_name']); ?>
                                    <?php if ($item_type === 'medicine') { ?><br><strong>Requested: <?php echo html_escape($item['requested_quantity']); ?></strong><?php } ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo $item['priority'] === 'urgent' ? 'bg-danger' : 'bg-secondary'; ?>"><?php echo ucfirst(html_escape($item['priority'])); ?></span>
                                    <?php if (!empty($item['clinical_indication'])) { ?><br><?php echo nl2br(html_escape($item['clinical_indication'])); ?><?php } ?>
                                    <?php if (!empty($item['note'])) { ?><br><small><?php echo nl2br(html_escape($item['note'])); ?></small><?php } ?>
                                    <br><small>Requested by <?php echo html_escape(trim($item['requested_by_name'] . ' ' . $item['requested_by_surname'])); ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo html_escape(ucwords(str_replace('_', ' ', $item['status']))); ?></span>
                                    <?php if (!empty($item['billing_id'])) { ?><br><small>Bill #<?php echo (int) $item['billing_id']; ?></small><?php } ?>
                                    <?php if (!empty($item['rejection_reason'])) { ?><br><small class="text-danger"><?php echo html_escape($item['rejection_reason']); ?></small><?php } ?>
                                </td>
                                <td style="min-width:250px">
                                <?php if (in_array($item['status'], array('pending', 'partially_accepted'))) { ?>
                                    <form class="indent-process-form">
                                        <input type="hidden" name="item_id" value="<?php echo (int) $item['id']; ?>">
                                        <input type="hidden" name="department" value="<?php echo html_escape($department); ?>">
                                        <?php if ($item_type === 'medicine') { ?>
                                            <select name="batch_id" class="form-control mb-2" required>
                                                <option value="">Select stock batch</option>
                                                <?php foreach ($item['batches'] as $batch) { ?>
                                                    <option value="<?php echo (int) $batch['id']; ?>">
                                                        <?php echo html_escape($batch['batch_no']); ?> — stock <?php echo html_escape($batch['available_quantity']); ?> — <?php echo html_escape($batch['sale_rate']); ?>
                                                    </option>
                                                <?php } ?>
                                            </select>
                                            <?php $remaining = (float) $item['requested_quantity'] - (float) $item['accepted_quantity']; ?>
                                            <input type="number" name="accepted_quantity" min="1" max="<?php echo html_escape($remaining); ?>" step="1" value="<?php echo html_escape($remaining); ?>" class="form-control mb-2" required>
                                        <?php } ?>
                                        <button name="action" value="accept" class="btn btn-success btn-sm">Accept &amp; bill</button>
                                        <?php if ($item['status'] === 'pending') { ?>
                                            <button type="button" class="btn btn-danger btn-sm reject-indent">Reject</button>
                                            <input type="text" name="rejection_reason" class="form-control mt-2 rejection-reason d-none" placeholder="Rejection reason">
                                        <?php } ?>
                                    </form>
                                <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>
<script>
$(document).on('click', '.reject-indent', function () {
    var $form = $(this).closest('form');
    var $reason = $form.find('.rejection-reason');
    if ($reason.hasClass('d-none')) { $reason.removeClass('d-none').focus(); return; }
    if (!$reason.val().trim()) { errorMsg('A rejection reason is required.'); return; }
    submitIndentDecision($form, 'reject');
});
$(document).on('submit', '.indent-process-form', function (e) {
    e.preventDefault(); submitIndentDecision($(this), 'accept');
});
function submitIndentDecision($form, action) {
    var data = $form.serializeArray(); data.push({name: 'action', value: action});
    $form.find('button').prop('disabled', true);
    $.post('<?php echo base_url('admin/patient/process_indent_item'); ?>', data, function (response) {
        if (response.status === 'success') { successMsg(response.message); window.location.reload(); }
        else { errorMsg(response.message); $form.find('button').prop('disabled', false); }
    }, 'json').fail(function () { errorMsg('Unable to process the indent.'); $form.find('button').prop('disabled', false); });
}
</script>
