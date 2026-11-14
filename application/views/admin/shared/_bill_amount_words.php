<?php
$bill_words_amount = isset($bill_words_amount) ? $bill_words_amount : 0;
$bill_mode_source = isset($bill_payment_mode) ? $bill_payment_mode : '';
?>
<table width="100%" style="width:100%; margin-top:12px; border-collapse:collapse; page-break-inside:avoid;">
    <tr>
        <td style="width:22%; vertical-align:top; font-size:11px; font-weight:700; border:0; padding:2px 8px 2px 0;">Mode of Payment</td>
        <td style="vertical-align:top; font-size:11px; border:0; padding:2px 0;"><?php echo html_escape(sh_payment_mode_label($bill_mode_source)); ?></td>
    </tr>
    <tr>
        <td style="width:22%; vertical-align:top; font-size:11px; font-weight:700; border:0; padding:2px 8px 2px 0;">Amount in Words</td>
        <td style="vertical-align:top; font-size:11px; border:0; padding:2px 0;"><?php echo html_escape(amountInWords($bill_words_amount)); ?></td>
    </tr>
</table>
