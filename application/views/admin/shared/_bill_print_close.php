<?php
$bill_printed_by = '';
if (!empty($printed_by)) {
    $bill_printed_by = $printed_by;
} else {
    $admin_session = $this->session->userdata('hospitaladmin');
    if (!empty($admin_session['username'])) {
        $bill_printed_by = $admin_session['username'];
    }
}
$bill_generated_by = !empty($generated_by) ? $generated_by : $bill_printed_by;
$bill_print_time = $this->customlib->YYYYMMDDHisTodateFormat(date('Y-m-d H:i:s'), $this->customlib->getHospitalTimeFormat());
$bill_by_line = trim(preg_replace('/\s*\([^)]*\)/', '', (string) $bill_generated_by));
$bill_by_line = $bill_by_line !== '' ? $bill_by_line : '-';
if (isset($bill_words_amount)) {
    include(APPPATH . 'views/admin/shared/_bill_amount_words.php');
}
?>
<table class="bill-close" width="100%" style="width:100%; margin-top:28px; border-collapse:collapse; page-break-inside:avoid;">
    <tr>
        <td style="width:62%; vertical-align:bottom; text-align:left; font-size:11px; border:0; padding:0;">
            <div>Bill generated and Printed By</div>
            <div><?php echo html_escape($bill_by_line); ?></div>
            <div><?php echo html_escape($bill_print_time); ?></div>
        </td>
        <td style="width:38%; vertical-align:bottom; text-align:right; font-size:11px; border:0; padding:0;">
            <div style="display:inline-block; min-width:180px; margin-top:36px; border-top:1px solid #333; padding-top:4px; text-align:center;">Authorized signatory</div>
        </td>
    </tr>
</table>
