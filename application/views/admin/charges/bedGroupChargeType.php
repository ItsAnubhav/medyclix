<div class="row">
    <div class="col-md-2">
        <div class="card border0">
            <?php $this->load->view("admin/charges/sidebar"); ?>
        </div>
    </div>
    <div class="col-md-10">
        <div class="card" id="tachelist">
            <div class="card-header ptbnull">
                <h3 class="card-title titlefix"><?php echo $this->lang->line('bed_group_charge_type'); ?></h3>
            </div>
            <div class="card-body">
                <p class="text-muted"><?php echo $this->lang->line('bed_group_charge_type_note'); ?></p>
                <form id="bed_group_charge_type_form" method="post" action="<?php echo site_url('admin/bedgroupchargetype/save'); ?>">
                    <div class="table-responsive mailbox-messages overflow-visible">
                        <table class="table table-striped table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th><?php echo $this->lang->line('bed_group'); ?></th>
                                    <th><?php echo $this->lang->line('floor'); ?></th>
                                    <th><?php echo $this->lang->line('charge_type'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $bed_groups = isset($bed_groups) ? $bed_groups : array();
                                $charge_types = isset($charge_types) ? $charge_types : array();
                                if (empty($bed_groups)) {
                                    echo '<tr><td colspan="3">' . $this->lang->line('no_record_found') . '</td></tr>';
                                } else {
                                    foreach ($bed_groups as $group) {
                                        $selected = isset($group['charge_type_id']) ? (int) $group['charge_type_id'] : 0;
                                        ?>
                                        <tr>
                                            <td><?php echo html_escape($group['name']); ?></td>
                                            <td><?php echo html_escape($group['floor_name']); ?></td>
                                            <td>
                                                <select class="form-control" name="charge_type_id[<?php echo (int) $group['id']; ?>]" <?php echo $this->rbac->hasPrivilege('charge_type', 'can_edit') || $this->rbac->hasPrivilege('charge_type', 'can_add') ? '' : 'disabled'; ?>>
                                                    <option value=""><?php echo $this->lang->line('select'); ?></option>
                                                    <?php foreach ($charge_types as $ct) { ?>
                                                        <option value="<?php echo (int) $ct->id; ?>" <?php echo ((int) $ct->id === $selected) ? 'selected' : ''; ?>>
                                                            <?php echo html_escape($ct->charge_type); ?>
                                                        </option>
                                                    <?php } ?>
                                                </select>
                                            </td>
                                        </tr>
                                        <?php
                                    }
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if (!empty($bed_groups) && ($this->rbac->hasPrivilege('charge_type', 'can_edit') || $this->rbac->hasPrivilege('charge_type', 'can_add'))) { ?>
                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary" id="bed_group_charge_type_save">
                                <i class="fa fa-save"></i> <?php echo $this->lang->line('save'); ?>
                            </button>
                        </div>
                    <?php } ?>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
$(document).ready(function () {
    $('#bed_group_charge_type_form').on('submit', function (e) {
        e.preventDefault();
        var $btn = $('#bed_group_charge_type_save');
        $btn.prop('disabled', true);
        $.ajax({
            url: $(this).attr('action'),
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (data) {
                if (data.status === 'fail' || data.status == 0) {
                    errorMsg(data.message || '');
                } else {
                    successMsg(data.message);
                    window.location.reload(true);
                }
            },
            error: function () {
                errorMsg(<?php echo json_encode($this->lang->line('warning_message')); ?>);
            },
            complete: function () {
                $btn.prop('disabled', false);
            }
        });
    });
});
</script>
