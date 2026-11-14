<?php

$certificate[0]->certificate_text = str_replace('[name]', '[patient_name]', $certificate[0]->certificate_text);
$certificate[0]->certificate_text = str_replace('[phone]', '[mobileno]', $certificate[0]->certificate_text);
$certificate[0]->certificate_text = str_replace('[consultant_doctor]', '[doctorname]', $certificate[0]->certificate_text);

$formatted_date_time = date('d-m-Y h:i A');
$formatted_date      = date('d-m-Y');

$certificate[0]->certificate_text = str_replace('[created_at]', $formatted_date_time, $certificate[0]->certificate_text);
$certificate[0]->certificate_text = str_replace('[present_date]', $formatted_date, $certificate[0]->certificate_text);
$certificate[0]->certificate_text = str_replace('[current_date]', $formatted_date, $certificate[0]->certificate_text);
$certificate[0]->certificate_text = str_replace('[date]', $formatted_date, $certificate[0]->certificate_text);

if ($certificate[0]->module == "opd") {
    $certificate[0]->certificate_text = str_replace('[opd_ipd_no]', '[opd_no]', $certificate[0]->certificate_text);
    $certificate[0]->certificate_text = str_replace('[opd_checkup_id]', '[checkup_id]', $certificate[0]->certificate_text);
} elseif ($certificate[0]->module == "ipd") {
    $certificate[0]->certificate_text = str_replace('[opd_ipd_no]', '[ipd_no]', $certificate[0]->certificate_text);
}

$known_shortcodes = array(
    'patient_name', 'patient_id', 'dob', 'age', 'gender', 'email', 'phone', 'mobileno',
    'address', 'guardian_name', 'opd_ipd_no', 'opd_no', 'ipd_no', 'opd_checkup_id',
    'checkup_id', 'consultant_doctor', 'doctorname', 'date_time', 'department_name',
    'bed_group', 'bed_name', 'unit_name', 'case_type', 'attendant_phone', 'abha_id',
    'id_proof', 'secondary_doctor', 'present_date', 'current_date', 'created_at',
);

foreach ($patients as $patient) {

    $certificate_body = $certificate[0]->certificate_text;
    $patient_id       = isset($patient->patient_id) ? $patient->patient_id : '';
    $opd_no           = '';
    $ipd_no           = '';
    $checkup_id       = '';

    if ($certificate[0]->module == "opd") {
        $opd_no = $this->customlib->getSessionPrefixByType('opd_no') . $patient->id;
    } else {
        $ipd_no = $this->customlib->getSessionPrefixByType('ipd_no') . $patient->id;
        if (!empty($patient->related_opd_id)) {
            $opd_no = $this->customlib->getSessionPrefixByType('opd_no') . $patient->related_opd_id;
        }
    }

    $admission_raw = '';
    if (!empty($patient->admission_date) && $patient->admission_date != '0000-00-00' && $patient->admission_date != '0000-00-00 00:00:00') {
        $admission_raw = $patient->admission_date;
    }

    $formatted_admission = $formatted_date_time;
    if ($admission_raw) {
        $formatted_admission = $this->customlib->YYYYMMDDHisTodateFormat($admission_raw);
        if ($formatted_admission === null || $formatted_admission === '') {
            $formatted_admission = $this->customlib->YYYYMMDDTodateFormat(substr($admission_raw, 0, 10));
        }
        if ($formatted_admission === null || $formatted_admission === '') {
            $formatted_admission = $formatted_date_time;
        }
    }

    $patient->age       = $this->customlib->get_patient_current_age($patient_id);
    $patient->ipd_no    = $ipd_no;
    $patient->opd_no    = $opd_no;
    $patient->phone     = isset($patient->mobileno) ? $patient->mobileno : '';
    $patient->date_time = $formatted_admission;

    foreach ($patient as $pat_key => $pat_value) {

        if ($pat_key == "dob") {
            if ($pat_value != "0000-00-00" && $pat_value) {
                $pat_value = $this->customlib->YYYYMMDDTodateFormat($pat_value);
            } else {
                $pat_value = '';
            }
        }

        if ($pat_key == "checkup_id" && $pat_value !== '' && $pat_value !== null) {
            $checkup_id = $this->customlib->getSessionPrefixByType('checkup_id') . $pat_value;
            $pat_value  = $checkup_id;
        }

        if ($pat_key == "ipd_no") {
            $pat_value = $ipd_no;
        }
        if ($pat_key == "opd_no") {
            $pat_value = $opd_no;
        }

        if (is_array($pat_value) || is_object($pat_value)) {
            continue;
        }

        $certificate_body = str_replace('[' . $pat_key . ']', ($pat_value === null) ? '' : (string) $pat_value, $certificate_body);
    }

    foreach ($known_shortcodes as $shortcode) {
        $certificate_body = str_replace('[' . $shortcode . ']', '', $certificate_body);
    }
    ?>

    <div class="sh-certificate-print" style="position: relative; text-align: center;">
        <?php if (!empty($certificate[0]->background_image)) {?>
            <img src="<?php echo $this->media_storage->getImageURL('uploads/certificate/' . $certificate[0]->background_image); ?>" style="width: 100%; height: 100vh" />
        <?php }?>

        <table width="100%" cellspacing="0" cellpadding="0" style="position: absolute;top: 0; margin-left: auto;margin-right: auto;left: 0;right: 0;<?php echo "width:" . (int)$certificate[0]->content_width . "px" ?>">
            <tr>
                <td style="position: absolute;left:0;">
                    <?php if ($certificate[0]->enable_patient_image == 1) {?>
                        <img style="position: relative; <?php echo "top:" . (int)$certificate[0]->enable_image_height . "px" ?>;" src="<?php echo $this->media_storage->getImageURL($patient->image); ?>" width="100" height="auto">
                    <?php }?>
                </td>
            </tr>
            <tr>
                <td valign="top" style="text-align:left; position: relative; <?php echo "top:" . (int)$certificate[0]->header_height . "px" ?>"><?php echo $certificate[0]->left_header ?></td>
                <td valign="top" style="text-align:center; position: relative; <?php echo "top:" . (int)$certificate[0]->header_height . "px" ?>"><?php echo $certificate[0]->center_header ?></td>
                <td valign="top" style="text-align:right; position: relative; <?php echo "top:" . (int)$certificate[0]->header_height . "px" ?>"><?php echo $certificate[0]->right_header ?></td>
            </tr>
            <tr>
                <td colspan="3" valign="top" style="position: relative; <?php echo "top:" . (int)$certificate[0]->content_height . "px" ?>">
                    <p style="font-size: 14px; line-height: 24px; text-align:center;"><?php echo $certificate_body; ?></p>
                </td>
            </tr>
            <tr>
                <td valign="top" style="text-align:left;position: relative; <?php echo "top:" . (int)$certificate[0]->footer_height . "px" ?>"><?php echo $certificate[0]->left_footer ?></td>
                <td valign="top" style="text-align:center;position: relative; <?php echo "top:" . (int)$certificate[0]->footer_height . "px" ?>"><?php echo $certificate[0]->center_footer ?></td>
                <td valign="top" style="text-align:right;position: relative; <?php echo "top:" . (int)$certificate[0]->footer_height . "px" ?>"><?php echo $certificate[0]->right_footer ?></td>
            </tr>
        </table>
    </div>
    <?php
}
?>
