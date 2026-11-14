<?php
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Bill extends Admin_Controller
{
	public $notificationurl;
	public $time_format;

    public $marital_status;
    public $payment_mode;
    public $yesno_condition;
    public $blood_group;
    public $opd_prefix;
    public $appointment_status;

    public function __construct()
    {
        parent::__construct();
        $this->config->load("payroll");
        $this->config->load("image_valid");
        $this->load->library('Customlib');
        $this->load->helper('url');
        $this->marital_status     = $this->config->item('marital_status');
        $this->payment_mode       = $this->config->item('payment_mode');
        $this->yesno_condition    = $this->config->item('yesno_condition');
        $this->blood_group        = $this->config->item('bloodgroup');
        $this->notificationurl    = $this->config->item('notification_url');
        $this->opd_prefix         = $this->customlib->getSessionPrefixByType('opd_no');
        $this->appointment_status = $this->config->item('appointment_status');
        $this->load->model(array('charge_model', 'patient_model', 'appoint_priority_model', 'onlineappointment_model', 'transaction_model', 'conference_model', 'transaction_model', 'casereference_model'));

        $this->load->library('datatables');
        $this->payment_mode = $this->config->item('payment_mode');
        $this->load->model("transaction_model");
        $this->load->helper('customfield_helper');
        $this->time_format = $this->customlib->getHospitalTimeFormat();
        $this->load->library('system_notification');
        $this->load->library('mailsmsconf');
        $this->load->library('SaasValidation');
        $this->load->model('bill_model');
        $this->bill_model->ensureCreditBillingSchema();
    }

    public function index($case_id = null)
    {
        $this->session->set_userdata('top_menu', 'bill');
        $data["payment_mode"] = $this->payment_mode;
        $data['case_id']=$case_id;
        $data['module'] = 'billing';
        $this->load->view("layout/header", $data);
        $this->load->view("admin/bill/index", $data);
        $this->load->view("layout/footer", $data);
    } 
 
    public function dashboard()
    {
        $this->session->set_userdata('top_menu', 'bill');
        $this->form_validation->set_rules('case_id', $this->lang->line('case_id'), 'required|trim|xss_clean|numeric');
        $data['error_message']="";
        if ($this->form_validation->run() == false) {
            
		} else {
			
			$patient = $this->patient_model->getDetailsByCaseId($this->input->post('case_id'));
			if (!empty($patient['patient_id'])) {
				redirect('admin/bill/index/'.$this->input->post('case_id'));
			} else {
				$data['error_message']=$this->lang->line('no_record_found');
			}
            
        }

        $data['module'] = 'billing';
        $this->load->model('bill_model');
        $this->bill_model->ensureServicePostingPermissions();
        $this->load->view("layout/header", $data);
        $this->load->view("admin/bill/dashboard", $data);
        $this->load->view("layout/footer", $data);
    }

    public function get()
    {
        $this->form_validation->set_rules('case_id', $this->lang->line('case_id'), 'required|trim|xss_clean|numeric');
        if ($this->form_validation->run() == false) {
            $msg = array(
                'case_id' => form_error('case_id'),
            );
            $array = array('status' => 'fail', 'error' => $msg, 'message' => '');
        } else {
            $array = array('status' => 'success', 'error' => '', 'case_id' => $_POST['case_id']);
        }
        echo json_encode($array);
    }

    public function getopd($case_id)
    {
        $charges                = $this->charge_model->getopdChargesbyCaseId($case_id);
        $data["charges_detail"] = $charges;
        $data["case_id"]        = $case_id;
        $page                   = $this->load->view('admin/bill/_opd_charges', $data, true);
        echo json_encode(array('status' => 1, 'page' => $page));
    }

    public function getipd($case_id)
    {
        $charges                = $this->charge_model->getipdChargesbyCaseId($case_id);
        $data["charges_detail"] = $charges;
        $data["case_id"]        = $case_id;
        $page                   = $this->load->view('admin/bill/_ipd_charges', $data, true);
        echo json_encode(array('status' => 1, 'page' => $page));
    } 

    public function getpharmacy($case_id)
    {
        $dt_response = $this->pharmacy_model->getAllpharmacybillByCaseIdSql($case_id);
        $dt_response = json_decode($dt_response);
        $dt_data     = array();
        if (!empty($dt_response->data)) {
            foreach ($dt_response->data as $key => $value) {
               
                $row            = array();
                $balance_amount = ($value->net_amount) - ($value->amt_payment-$value->amt_refund);
                //====================================
                $action = "<div class='rowoptionview'>";
                if ($balance_amount > 0) {
                    if ($this->rbac->hasPrivilege('pharmacy_billing_payment', 'can_view')) {
                        $action .= "<a href='#'  data-loading-text='" . $this->lang->line('please_wait') . "' data-record-id='" . $value->id . "' class='btn btn-secondary btn-sm add_pharmacypayment' data-bs-toggle='tooltip' title='" . $this->lang->line('add_view_payments') . "' ><i class='fa fa-money'></i></a>";
                    }
                }
                $action .= "<a href='#' onclick='viewPharmacyDetail(" . $value->id . ")' class='btn btn-secondary btn-sm' data-bs-toggle='tooltip' title='" . $this->lang->line('show') . "' ><i class='fa fa-reorder'></i></a>";

                $action .= "<div>";

                //==============================
                $row[] = $this->customlib->getSessionPrefixByType('pharmacy_billing') . $value->id;
                $row[] = $this->customlib->YYYYMMDDHisTodateFormat($value->date, $this->customlib->getHospitalTimeFormat());
                $row[] = $value->doctor_name;
                $row[] = amountFormat($value->total);
				$row[] = $value->discount . " (" . $value->discount_percentage . "%)";
                $discount_amt = ( $value->total * $value->discount_percentage ) / 100 ; 
                $row[]  =  $value->tax ." (".amountFormat(($value->tax * 100) / ($value->total - $discount_amt), 2)."%)"; 
                $row[] = amountFormat($value->net_amount);
                $row[] = amountFormat($value->amt_payment);
                $row[] = amountFormat($value->amt_refund);
                $row[] = amountFormat($balance_amount);
                $row[] = $action;
                //====================

                $dt_data[] = $row;
            }
        }
        $json_data = array(
            "draw"            => intval($dt_response->draw),
            "recordsTotal"    => intval($dt_response->recordsTotal),
            "recordsFiltered" => intval($dt_response->recordsFiltered),
            "data"            => $dt_data,
        );
        echo json_encode($json_data);
    }

    public function getpathology($case_id)
    {
        $dt_response = $this->pathology_model->getpathologybillByCaseId($case_id);
        $dt_response = json_decode($dt_response);
        $dt_data     = array();
        if (!empty($dt_response->data)) {
            foreach ($dt_response->data as $key => $value) {

                $row            = array();
                $balance_amount = ($value->net_amount) - ($value->paid_amount);
                //====================================

                $action = "<div class='rowoptionview rowview-mt-19'>";
                $action .= "<a href='javascript:void(0)' data-loading-text='" . $this->lang->line('please_wait') . "' class='print_bill' data-bs-toggle='tooltip' data-record-id=\"" . $value->id . "\"   data-original-title='" . $this->lang->line('print_bill') . "'><i class='fa fa-print'></i></a>";
                $action .= "</div>";
                $action = "<div class='rowoptionview rowview-mt-19'>";
                $action .= "<a href='javascript:void(0)'  data-loading-text='" . $this->lang->line('please_wait') . "' data-record-id='" . $value->id . "' class='btn btn-secondary btn-sm view_pathology_detail' data-bs-toggle='tooltip' title='" . $this->lang->line('view_reports') . "' ><i class='fa fa-reorder'></i></a>";
                if ($balance_amount > 0) {
                    if ($this->rbac->hasPrivilege('pathology_billing_payment', 'can_view')) {
                        $action .= "<a href='javascript:void(0)'  data-loading-text='" . $this->lang->line('please_wait') . "' data-record-id='" . $value->id . "' class='btn btn-secondary btn-sm add_pathology_payment' data-bs-toggle='tooltip' title='" . $this->lang->line('add_view_payments') . "' ><i class='fa fa-money'></i></a>";
                    }
                }
                if ($value->case_reference_id > 0) {
                    $case_id = $value->case_reference_id;
                } else {
                    $case_id = '';
                }

                $action .= "</div>";

                //==============================
                $row[] = $this->customlib->getSessionPrefixByType('pathology_billing') . $value->id;
                $row[] = $this->customlib->YYYYMMDDHisTodateFormat($value->date, $this->customlib->getHospitalTimeFormat());
                $row[] = $value->doctor_name;
                $row[] = $value->note;
                $row[] = amountFormat($value->total);
                $row[] = $value->discount.' ('.$value->discount_percentage.'%)';
                $row[] = $value->tax;
                $row[] = amountFormat($value->net_amount);
                $row[] = amountFormat($value->paid_amount);
                $row[] = amountFormat($balance_amount) . $action;
                //====================

                $dt_data[] = $row;
            }
        }
        $json_data = array(
            "draw"            => intval($dt_response->draw),
            "recordsTotal"    => intval($dt_response->recordsTotal),
            "recordsFiltered" => intval($dt_response->recordsFiltered),
            "data"            => $dt_data,
        );
        echo json_encode($json_data);
    }

    public function getradiology($case_id)
    {
        $dt_response = $this->radio_model->getradiologybillByCaseId($case_id);
        $dt_response = json_decode($dt_response);
        $dt_data     = array();
        if (!empty($dt_response->data)) {
            foreach ($dt_response->data as $key => $value) {
                $row            = array();
                $balance_amount = ($value->net_amount) - ($value->paid_amount);
                //====================================
                $action = "<div class='rowoptionview rowview-mt-19'>";

                $action .= "<a href='javascript:void(0)'  data-loading-text='" . $this->lang->line('please_wait') . "' data-record-id='" . $value->id . "' class='btn btn-secondary btn-sm view_radiodetail' data-bs-toggle='tooltip' title='" . $this->lang->line('view_reports') . "' ><i class='fa fa-reorder'></i></a>";
                if ($balance_amount > 0) {
                    if ($this->rbac->hasPrivilege('radiology_billing_payment', 'can_view')) {
                        $action .= "<a href='javascript:void(0)'  data-loading-text='" . $this->lang->line('please_wait') . "' data-record-id='" . $value->id . "' class='btn btn-secondary btn-sm add_radio_payment' data-bs-toggle='tooltip' title='" . $this->lang->line('add_view_payments') . "' ><i class='fa fa-money'></i></a>";
                    }
                }

                $action .= "</div>";

                //==============================
                $row[] = $this->customlib->getSessionPrefixByType('radiology_billing') . $value->id;
                $row[] = $this->customlib->YYYYMMDDHisTodateFormat($value->date, $this->customlib->getHospitalTimeFormat());
                $row[] = $value->doctor_name;
                $row[] = $value->note;
                $row[] = amountFormat($value->total);
                $row[] = $value->discount." (".$value->discount_percentage." %)";
				$tax_per = ($value->tax*100)/($value->total-$value->discount) ;
                $row[] = $value->tax . " (".  amountFormat($tax_per) .'%)' ;
                $row[] = amountFormat($value->net_amount);
                $row[] = amountFormat($value->paid_amount);
                $row[] = amountFormat($balance_amount) . $action;
                //====================

                $dt_data[] = $row;
            }
        }
        $json_data = array(
            "draw"            => intval($dt_response->draw),
            "recordsTotal"    => intval($dt_response->recordsTotal),
            "recordsFiltered" => intval($dt_response->recordsFiltered),
            "data"            => $dt_data,
        );
        echo json_encode($json_data);
    }

    public function getbloodbank($case_id)
    {
        $dt_response = $this->bloodissue_model->getBloodIssueRecordByCaseId($case_id);
        $dt_response = json_decode($dt_response);
        $dt_data     = array();
        if (!empty($dt_response->data)) {
            foreach ($dt_response->data as $key => $value) {

                $row = array();
                //====================================
                $action = "<div class='rowoptionview rowview-mt-19'>";

                $action .= "<a href='#' onclick='printbloodbankData(" . $value->id . ")' class='btn btn-secondary btn-sm print_blood_issue'  data-bs-toggle='tooltip' title='" . $this->lang->line('print') . "' ><i class='fa fa-print'></i></a>";
                if ($this->rbac->hasPrivilege('blood_bank_billing_payment', 'can_view')) {
                    $action .= "<a href='javascript:void(0)'  data-caseid='' data-module='blood_bank'  data-record-id='" . $value->id . "' data-patient-id='" . $value->patient_id . "' class='btn btn-secondary btn-sm add_bloodbankpayment' data-bs-toggle='tooltip' title='" . $this->lang->line('add_view_payments') . "' ><i class='fa fa-plus'></i></a>";
                }

                $action .= "</div>";
                
                //==============================
                $row[] = $this->customlib->getSessionPrefixByType('blood_bank_billing') . $value->id;
                $row[] = $this->customlib->YYYYMMDDHisTodateFormat($value->date_of_issue, $this->time_format);

                $row[]     = composePatientName($value->patient_name,$value->patient_id);
                $row[]     = $value->blood_group;
                $row[]     = $value->gender;
                $row[]     = $value->donor_name;
                $row[]     = $this->customlib->bag_string($value->bag_no,$value->volume,$value->charge_unit);
                $row[]     = $value->amount;
                $percentage_amt = amountFormat(($value->amount * $value->discount_percentage)/100);
                $row[]     = $percentage_amt.'('.$value->discount_percentage.'%)';
                $tax_amt = amountFormat((($value->amount - $percentage_amt) * $value->tax_percentage)/100);
                $row[]     = $tax_amt.'('.$value->tax_percentage.'%)';
                $row[]     = $value->net_amount;
                $row[]     = $value->paid_amount;
                $row[]     = amountFormat($value->net_amount - $value->paid_amount) . $action;
                $dt_data[] = $row;
            }
        }
        $json_data = array(
            "draw"            => intval($dt_response->draw),
            "recordsTotal"    => intval($dt_response->recordsTotal),
            "recordsFiltered" => intval($dt_response->recordsFiltered),
            "data"            => $dt_data,
        );
        echo json_encode($json_data);
    }
	
	 public function getcomponentissueDatatable($case_id=null)
    {
        $fields      = $this->customfield_model->get_custom_fields('component_issue', 1);
		
        $dt_response = $this->bloodissue_model->getAllcomponentissueRecord($case_id);
        $dt_response = json_decode($dt_response);
        $dt_data     = array();
        if (!empty($dt_response->data)) {
            foreach ($dt_response->data as $key => $value) {
                $row = array();
                //====================================
                $action = "<div class='rowoptionview rowview-mt-19'>";
                $action .= "<a href='#' data-record-id='" . $value->id . "' class='btn btn-secondary btn-sm viewDetail'  data-bs-toggle='tooltip' title='" . $this->lang->line('show') . "' ><i class='fa fa-reorder'></i></a>";

                if ($this->rbac->hasPrivilege('blood_bank_partial_payment', 'can_view')) {
                    $action .= "<a href='javascript:void(0)'  data-caseid='' data-module='blood_bank' data-record-id='" . $value->id . "' class='btn btn-secondary btn-sm add_payment' data-bs-toggle='tooltip' title='" . $this->lang->line('add_payment') . "' ><i class='fa fa-plus'></i></a>";
                }
                if ($this->rbac->hasPrivilege('issue_component', 'can_delete')) {
                    $action .= "<a class='btn btn-default btn-xs' data-bs-toggle='tooltip' title='" . $this->lang->line('delete') . "' onclick='delete_recordById(\"" . 'admin/bloodbank/deleteIssue/' . "$value->id\", \"" . $this->lang->line('delete_message') . "\")'><i class='fa fa-trash'></i></a>";
                }
                $action .= "</div>";

                //==============================
                $row[] = $this->customlib->getSessionPrefixByType('blood_bank_billing') . $value->id;
                $row[] = $value->case_reference_id;
                $row[] = $this->customlib->dateyyyymmddToDateTimeformat($value->date_of_issue, false);
                $row[] = composeStaffNameByString($value->name, $value->surname, $value->employee_id);
                $row[] = $value->patient_name . " (" . $value->patient_id . ")";
                $row[] = $value->blood_group_name;
                $row[] = $value->component_name;
                $row[] = (isset($value->gender) && $value->gender!="") ? $this->lang->line(strtolower($value->gender)) : "";
                $row[] = $value->donor_name;
                $row[] = $this->customlib->bag_string($value->bag_no, $value->volume, $value->unit_name);
                //====================
                if (!empty($fields)) {
                    foreach ($fields as $fields_key => $fields_value) {
                        $display_field = $value->{"$fields_value->name"};
                        if ($fields_value->type == "link") {
                            $display_field = "<a href=" . $value->{"$fields_value->name"} . " target='_blank'>" . $value->{"$fields_value->name"} . "</a>";
                        }
                        $row[] = $display_field;
                    }
                }
                //====================
               
                $row[]     = amountFormat($value->amount);
                $discount_amt = amountFormat(($value->amount * $value->discount_percentage)/100);
                $row[]      = $discount_amt.' ('.$value->discount_percentage.'%)';               
                $tax_amt    = amountFormat((($value->amount - $discount_amt) * $value->tax_percentage)/100);        
                $row[]      = $tax_amt . '(' . $value->tax_percentage.'%)';                 
                $row[]      = amountFormat($value->net_amount);
                $row[]      = amountFormat($value->paid_amount);
                $row[]      = amountFormat($value->net_amount - $value->paid_amount) . $action;
                $dt_data[]  = $row;
            }
        }
        $json_data = array(
            "draw"            => intval($dt_response->draw),
            "recordsTotal"    => intval($dt_response->recordsTotal),
            "recordsFiltered" => intval($dt_response->recordsFiltered),
            "data"            => $dt_data,
        );
        echo json_encode($json_data);
    }


    public function getambulance($case_id)
    {
        $dt_response = $this->vehicle_model->getAmbulanceCallRecord($case_id);

        $dt_response = json_decode($dt_response);
        $dt_data     = array();
        if (!empty($dt_response->data)) {
            foreach ($dt_response->data as $key => $value) {
                $row = array();
                //====================================
                $action = "<div class='rowoptionview rowview-mt-19'>";

                $action .= "<a href='#' onclick='printAmbulanceData(" . $value->id . ")'
                       class='btn btn-default btn-xs'  data-bs-toggle='tooltip' title='" . $this->lang->line('print') . "' ><i class='fa fa-print'></i></a>";
                if ($this->rbac->hasPrivilege('ambulance_billing_payment', 'can_view')) {
                    $action .= "<a href='javascript:void(0)'  data-caseid='' data-module='ambulance'  data-record-id='" . $value->id . "' class='btn btn-default btn-xs add_ambulancecallpayment' data-bs-toggle='tooltip' title='" . $this->lang->line('add_payment') . "' ><i class='fa fa-plus'></i></a>";
                }

                $action .= "</div>";
                //==============================
                $row[] = $this->customlib->getSessionPrefixByType('ambulance_call_billing') . $value->id;
                $row[] = $value->vehicle_no;
                $row[] = $this->customlib->YYYYMMDDHisTodateFormat($value->date, $this->time_format);              
                $row[]     = amountFormat($value->amount);

                if($value->discount){
                    $row[]     = $value->discount." (".$value->discount_percentage." %)";
                }else{
                    $row[]     = '';
                }

                $taxamt	=	0;                
                $taxamt	=	(($value->amount - $value->discount) * $value->tax_percentage)/100;
                $row[]  =  amountFormat($taxamt) ." (".$value->tax_percentage."%)";   
                $row[]     = amountFormat($value->net_amount);
                $row[]     = amountFormat($value->paid_amount);
                $row[]     = amountFormat($value->net_amount - $value->paid_amount);
                $row[]     = $action;
                $dt_data[] = $row;
            }
        }
        $json_data = array(
            "draw"            => intval($dt_response->draw),
            "recordsTotal"    => intval($dt_response->recordsTotal),
            "recordsFiltered" => intval($dt_response->recordsFiltered),
            "data"            => $dt_data,
        );
        echo json_encode($json_data);
    }


    public function getDetailsByCaseId($case_id = null)
    {
        if (empty($case_id)) {
            echo json_encode(array('status' => 0, 'page' => ''));
            return;
        }
        $patient = $this->patient_model->getDetailsByCaseId($case_id);

        if (!array_filter($patient)) {
            $data['result'] = '';
        } else {
            $data['result'] = $patient;
        }

        $data['case_id'] = $case_id;
        if (!empty($patient)) {
            $status = 1;
        } else {
            $status = 0;
        }

        $page = $this->load->view('admin/bill/_patient_details', $data, true);
        echo json_encode(array('status' => $status, 'page' => $page));
    }

    public function makepayment()
    {
        $this->form_validation->set_rules('amount', $this->lang->line('amount'), 'trim|required|xss_clean');
        $this->form_validation->set_rules('payment_date', $this->lang->line('payment_date'), 'trim|required|xss_clean');
        $this->form_validation->set_rules('payment_mode', $this->lang->line('payment_mode'), 'trim|required|xss_clean');
        $this->form_validation->set_rules('note', $this->lang->line('note'), 'trim|xss_clean');
        if ($_POST['payment_mode'] == "Cheque") {
            $this->form_validation->set_rules('cheque_no', $this->lang->line('cheque_no'), 'trim|required|xss_clean');
            $this->form_validation->set_rules('cheque_date', $this->lang->line('cheque_date'), 'trim|required|xss_clean');
            $this->form_validation->set_rules('document', $this->lang->line('documents'), 'callback_handle_doc_upload[document]|callback_validateCanUploadFile[document]');
        }

        if ($this->form_validation->run() == false) {
            $msg = array(
                'amount'       => form_error('amount'),
                'payment_mode' => form_error('payment_mode'),
                'payment_date' => form_error('payment_date'),
                'cheque_date'  => form_error('cheque_date'),
                'cheque_no'    => form_error('cheque_no'),
                'document'     => form_error('document'),
            );

            $array = array('status' => 'fail', 'error' => $msg, 'message' => '');
        } else {

            $payment_date    = $this->customlib->dateFormatToYYYYMMDDHis($this->input->post("payment_date", TRUE), $this->time_format);
            $cheque_date     = $this->customlib->dateFormatToYYYYMMDD($this->input->post("cheque_date", TRUE));
            $amount          = $this->input->post('amount', TRUE);
            $module_id       = $this->input->post('module_id', TRUE);
            $module_name     = $this->input->post('module_name', TRUE);
            $case_ref_id     = $this->input->post('case_reference_id', TRUE);
            $patient         = $this->patient_model->getDetailsByCaseId($case_ref_id);
            $patient_id      = $patient['patient_id'];
            $module_data     = array(
                'opd'        => array('opd_id', $module_id),
                'ipd'        => array('ipd_id', $module_id),
                'pharmacy'   => array('pharmacy_bill_basic_id', $module_id),
                'radiology'  => array('radiology_billing_id', $module_id),
                'pathology'  => array('pathology_billing_id', $module_id),
                'blood_bank' => array('blood_issue_id', $module_id),
            );

            $payment_type = $this->input->post('payment_type', TRUE);
            $amount_type  = ($payment_type === 'advance') ? 'advance' : null;

            $data = array(
                'case_reference_id' => $case_ref_id,
                'amount'            => $amount,
                'type'              => 'payment',
                'amount_type'       => $amount_type,
                'payment_mode'      => $this->input->post('payment_mode', TRUE),
                'note'              => $this->input->post('note', TRUE),
                'payment_date'      => $payment_date,
                'received_by'       => $this->customlib->getLoggedInUserID(),
                'patient_id'        => $patient_id,
                'section'           => $module_name,
            );

            $attachment      = "";
            $attachment_name = "";
            if (isset($_FILES["document"]) && !empty($_FILES['document']['name'])) {
                $fileInfo        = pathinfo($_FILES["document"]["name"]);
                $attachment      = uniqueFileName() . '.' . $fileInfo['extension'];
                $attachment_name = $_FILES["document"]["name"];
                if (move_uploaded_file($_FILES["document"]["tmp_name"], "./uploads/payment_document/" . $attachment)) {
                    // SaaS: add the uploaded document's size to the storage quota usage.
                    try {
                        $this->saasvalidation->updateStorageLimit('storage', ['document']);
                    } catch (Exception $e) {
                        log_message('error', 'SaaS storage quota update failed (bill makepayment_or_revisit): ' . $e->getMessage());
                    }
                }
            }

            $cheque_date = $this->input->post("cheque_date", TRUE);
            if ($this->input->post('payment_mode', TRUE) == "Cheque") {
                $data['cheque_date']     = $this->customlib->dateFormatToYYYYMMDD($cheque_date);
                $data['cheque_no']       = $this->input->post('cheque_no', TRUE);
                $data['attachment']      = $attachment;
                $data['attachment_name'] = $attachment_name;
            }

            $this->load->model('bill_model');
            $this->bill_model->ensureCreditBillingSchema();

            $data[$module_data[$module_name][0]] = $module_data[$module_name][1];
            $insert_id                                                = $this->transaction_model->add($data);

            $array = array('status' => 'success', 'error' => '', 'message' => $this->lang->line('record_saved_successfully'));
        }

        echo json_encode($array);
    }

    public function printCharge()
    {		 
        switch ($this->input->post("type")) {
            case 'opd':
                $billing_header = "opd";
                break;
            case 'ipd':
                $billing_header = "ipd";
                break;
        }
        $print_details = $this->printing_model->get('', $billing_header);
	
        $id            = $this->input->post('id');
        $charge        = array();
        $charge        = $this->charge_model->getChargeById($id);
	  
        $data['print_details'] = $print_details;
        $data['charge']        = $charge;

        $page = $this->load->view('admin/patient/_printCharge', $data, true);
        echo json_encode(array('status' => 1, 'page' => $page));
    }

    public function getpayment()
    {
        $case_id                 = $this->input->post('case_id');
        $module_type             = $this->input->post('module_type');
        $id                      = $this->input->post('id');
        $payment_details         = array();
        $payment_details         = $this->transaction_model->getPatientPaymentsByCaseId($case_id, $module_type, $id);
        $data['module']          = $module_type;
        $data['payment_details'] = $payment_details;
        $page                    = $this->load->view('admin/bill/_view_payments', $data, true);
        echo json_encode(array('status' => 1, 'page' => $page));
    }

    public function printTransaction()
    {
        $case_id       = $this->input->post('case_id');
        $module_type   = $this->input->post('module_type');
        $id            = $this->input->post('id');
        $print_details = $this->printing_model->get('', 'paymentreceipt');
        $transaction   = $this->transaction_model->allPaymentByCaseId($case_id);

        $data['charge_details']           = array();
        $all_moduledata['bill']['result'] = array();
        if ($module_type == 'ipd_opd') {
            $data['charge_details'] = $this->transaction_model->get_ipdopdchargebycaseId($case_id);
            foreach ($transaction as $key => $value) {
                if (($value['ipd_id'] != '') && ($value['ipd_id'] != 0)) {
                    $all_moduledata['bill']['result'][] = $value;
                } elseif (($value['opd_id'] != '') && ($value['opd_id'] != 0)) {
                    $all_moduledata['bill']['result'][] = $value;
                }
            }
        }

        $data['all_paymets'] = $all_moduledata;
        $patient               = $this->patient_model->getDetailsByCaseId($case_id);
        $data['patient']       = $patient;
        $data['module_type']   = $module_type;
        $data['print_details'] = $print_details;
        $data['transaction']   = $transaction;
        $data['case_id']       = $case_id;
        $page                  = $this->load->view('admin/bill/_printTransaction', $data, true);
        echo json_encode(array('status' => 1, 'page' => $page));
    }

    public function patient_bill()
    {
        $this->session->set_userdata('top_menu', 'bill');

        $data['pathology_bill_prefix']  = $this->customlib->getSessionPrefixByType('pathology_billing');
        $data['radiology_bill_prefix']  = $this->customlib->getSessionPrefixByType('radiology_billing');
        $data['blood_bank_bill_prefix'] = $this->customlib->getSessionPrefixByType('blood_bank_billing');
        $data['pharmacy_bill_prefix']   = $this->customlib->getSessionPrefixByType('pharmacy_billing');
        $data['transaction_prefix']     = $this->customlib->getSessionPrefixByType('transaction_id');
        $case_id                  = $this->input->post('case_reference_id');
        $data['opd_data']         = $this->charge_model->getopdChargesbyCaseId($case_id);
        $data['ipd_data']         = $this->charge_model->getipdChargesbyCaseId($case_id);
        $data['pharmacy_data']    = $this->pharmacy_model->getpharmacybillByCaseId($case_id);
        $data['radiology_data']   = $this->radio_model->getradiologyByCaseId($case_id);
        $data['pathology_data']   = $this->pathology_model->getpathologyByCaseId($case_id);
        $data['bloodissue_data']  = $this->bloodissue_model->getbloodissueByCaseId($case_id);
        $data['componentissue_data']  = $this->bloodissue_model->getcomponentissueByCashId($case_id);		
        $data['transaction_data'] = $this->transaction_model->getTransactionByCaseId($case_id);
        $data['refund_data']      = $this->transaction_model->getRefundByCaseId($case_id);
        $patient                  = $this->patient_model->getDetailsByCaseId($case_id);
	 
        $data['patient']          = $patient;
		$data["ambulance_data"]  =  $this->patient_model->getPatientAmbulanceVisitDetails($patient['patient_id']);
		$data["case_id"]  =  $case_id;
        $data['editable'] = true;

        $opd_details = $this->patient_model->getDetailsopdByCaseId($case_id);
        $ipd_details = $this->patient_model->getDetailsipdByCaseId($case_id);
        $data['opd_id'] = !empty($opd_details['opdid']) ? $opd_details['opdid'] : '';
        $data['ipd_id'] = !empty($ipd_details['ipdid']) ? $ipd_details['ipdid'] : '';
		 
        $page                     = $this->load->view('admin/bill/_patient_bill', $data, true);
        $modal_action             = "<a href='javascript:void(0);' data-case-id=" . $case_id . " class='btn btn-sm btn-light print_bill' title='".$this->lang->line('print')."'><i class='fa fa-print'></i></a> <a href='".site_url('admin/bill/download_patient_bill_pdf/'.$case_id)."' target='_blank' class='btn btn-sm btn-light' title='".$this->lang->line('view_pdf')."'><i class='fa fa-file-pdf'></i></a>";
        echo json_encode(array(
            'status'       => 1,
            'page'         => $page,
            'modal_action' => $modal_action,
            'patient_name' => isset($patient['patient_name']) ? composePatientName($patient['patient_name'], $patient['patient_id']) : '',
            'case_id'      => $case_id,
            'patient_id'   => isset($patient['patient_id']) ? $patient['patient_id'] : '',
            'opd_id'       => $data['opd_id'],
            'ipd_id'       => $data['ipd_id'],
        ));
    }

    /**
     * Fetch a bill line for edit modal (final billing).
     */
    public function get_bill_item()
    {
        $module = $this->input->post('module');
        $id     = (int) $this->input->post('id');

        if ($module === 'pharmacy' || $id < 1) {
            echo json_encode(array('status' => 0, 'message' => $this->lang->line('access_denied')));
            return;
        }

        $result = null;
        if ($module === 'opd' || $module === 'ipd') {
            $row = $this->charge_model->getChargeById($id);
            if ($row) {
                $result = array(
                    'id'                  => $row->id,
                    'name'                => $row->charge_name,
                    'qty'                 => $row->qty,
                    'apply_charge'        => $row->apply_charge,
                    'standard_charge'     => $row->standard_charge,
                    'discount_percentage' => $row->discount_percentage,
                    'tax'                 => $row->tax,
                    'amount'              => $row->amount,
                    'opd_id'              => $row->opd_id,
                    'ipd_id'              => $row->ipd_id,
                    'charge_id'           => $row->charge_id,
                );
            }
        } elseif ($module === 'pathology') {
            $row = $this->db->where('id', $id)->get('pathology_billing')->row();
            if ($row) {
                $tax_base = ($row->total - $row->discount);
                $tax_pct  = ($tax_base > 0) ? (($row->tax * 100) / $tax_base) : 0;
                $result   = array(
                    'id'                  => $row->id,
                    'name'                => $this->customlib->getSessionPrefixByType('pathology_billing') . $row->id,
                    'qty'                 => 1,
                    'apply_charge'        => $row->total,
                    'discount_percentage' => $row->discount_percentage,
                    'tax'                 => $tax_pct,
                    'amount'              => $row->net_amount,
                );
            }
        } elseif ($module === 'radiology') {
            $row = $this->db->where('id', $id)->get('radiology_billing')->row();
            if ($row) {
                $tax_base = ($row->total - $row->discount);
                $tax_pct  = ($tax_base > 0) ? (($row->tax * 100) / $tax_base) : 0;
                $result   = array(
                    'id'                  => $row->id,
                    'name'                => $this->customlib->getSessionPrefixByType('radiology_billing') . $row->id,
                    'qty'                 => 1,
                    'apply_charge'        => $row->total,
                    'discount_percentage' => $row->discount_percentage,
                    'tax'                 => $tax_pct,
                    'amount'              => $row->net_amount,
                );
            }
        } elseif ($module === 'bloodissue' || $module === 'componentissue') {
            $row = $this->db->where('id', $id)->get('blood_issue')->row();
            if ($row) {
                $result = array(
                    'id'                  => $row->id,
                    'name'                => $this->customlib->getSessionPrefixByType('blood_bank_billing') . $row->id,
                    'qty'                 => 1,
                    'apply_charge'        => $row->amount,
                    'discount_percentage' => $row->discount_percentage,
                    'tax'                 => $row->tax_percentage,
                    'amount'              => $row->net_amount,
                );
            }
        } elseif ($module === 'ambulance') {
            $row = $this->db->where('id', $id)->get('ambulance_call')->row();
            if ($row) {
                $result = array(
                    'id'                  => $row->id,
                    'name'                => $this->customlib->getSessionPrefixByType('ambulance_call_billing') . $row->id,
                    'qty'                 => 1,
                    'apply_charge'        => $row->amount,
                    'discount_percentage' => $row->discount_percentage,
                    'tax'                 => $row->tax_percentage,
                    'amount'              => $row->net_amount,
                );
            }
        } else {
            echo json_encode(array('status' => 0, 'message' => $this->lang->line('invalid_input')));
            return;
        }

        if (empty($result)) {
            echo json_encode(array('status' => 0, 'message' => $this->lang->line('no_record_found')));
            return;
        }

        echo json_encode(array('status' => 1, 'result' => $result));
    }

    /**
     * Update qty / price for a final-bill line. Pharmacy blocked.
     */
    public function update_bill_item()
    {
        $module              = $this->input->post('module');
        $id                  = (int) $this->input->post('id');
        $qty                 = $this->input->post('qty');
        $apply_charge        = (float) $this->input->post('apply_charge');
        $discount_percentage = $this->input->post('discount_percentage');

        if ($module === 'pharmacy') {
            echo json_encode(array('status' => 0, 'message' => $this->lang->line('access_denied')));
            return;
        }

        if ($id < 1 || $apply_charge < 0) {
            echo json_encode(array('status' => 0, 'message' => $this->lang->line('invalid_input')));
            return;
        }

        if ($module === 'opd' || $module === 'ipd') {
            $row = $this->charge_model->getChargeById($id);
            if (empty($row)) {
                echo json_encode(array('status' => 0, 'message' => $this->lang->line('no_record_found')));
                return;
            }
            $qty = ($qty !== null && $qty !== '') ? (float) $qty : (float) $row->qty;
            if ($qty <= 0) {
                echo json_encode(array('status' => 0, 'message' => $this->lang->line('invalid_input')));
                return;
            }
            $discount_percentage = ($discount_percentage !== null && $discount_percentage !== '')
                ? (float) $discount_percentage
                : (float) $row->discount_percentage;
            $tax_pct         = (float) $row->tax;
            $discount_amount = ($apply_charge * $discount_percentage) / 100;
            $tax_amount      = (($apply_charge - $discount_amount) * $tax_pct) / 100;
            $amount          = ($apply_charge - $discount_amount) + $tax_amount;

            $update = array(
                'id'                  => $id,
                'qty'                 => $qty,
                'apply_charge'        => $apply_charge,
                'discount_percentage' => $discount_percentage,
                'amount'              => $amount,
            );
            $this->charge_model->add_charges($update);
            echo json_encode(array('status' => 1, 'message' => $this->lang->line('success_message'), 'amount' => $amount));
            return;
        }

        if ($module === 'pathology' || $module === 'radiology') {
            $table = ($module === 'pathology') ? 'pathology_billing' : 'radiology_billing';
            $row   = $this->db->where('id', $id)->get($table)->row();
            if (empty($row)) {
                echo json_encode(array('status' => 0, 'message' => $this->lang->line('no_record_found')));
                return;
            }
            $discount_percentage = ($this->input->post('discount_percentage') !== null && $this->input->post('discount_percentage') !== '')
                ? (float) $this->input->post('discount_percentage')
                : (float) $row->discount_percentage;
            $old_base = ($row->total - $row->discount);
            $tax_pct  = ($old_base > 0) ? (($row->tax * 100) / $old_base) : 0;
            $discount = ($apply_charge * $discount_percentage) / 100;
            $tax      = (($apply_charge - $discount) * $tax_pct) / 100;
            $net      = ($apply_charge - $discount) + $tax;
            $this->db->where('id', $id)->update($table, array(
                'total'               => $apply_charge,
                'discount'            => $discount,
                'discount_percentage' => $discount_percentage,
                'tax'                 => $tax,
                'net_amount'          => $net,
            ));
            echo json_encode(array('status' => 1, 'message' => $this->lang->line('success_message'), 'amount' => $net));
            return;
        }

        if ($module === 'bloodissue' || $module === 'componentissue') {
            $row = $this->db->where('id', $id)->get('blood_issue')->row();
            if (empty($row)) {
                echo json_encode(array('status' => 0, 'message' => $this->lang->line('no_record_found')));
                return;
            }
            $discount_percentage = ($this->input->post('discount_percentage') !== null && $this->input->post('discount_percentage') !== '')
                ? (float) $this->input->post('discount_percentage')
                : (float) $row->discount_percentage;
            $tax_pct  = (float) $row->tax_percentage;
            $discount = ($apply_charge * $discount_percentage) / 100;
            $tax      = (($apply_charge - $discount) * $tax_pct) / 100;
            $net      = ($apply_charge - $discount) + $tax;
            $this->bloodissue_model->update(array(
                'id'                  => $id,
                'amount'              => $apply_charge,
                'discount_percentage' => $discount_percentage,
                'net_amount'          => $net,
            ));
            echo json_encode(array('status' => 1, 'message' => $this->lang->line('success_message'), 'amount' => $net));
            return;
        }

        if ($module === 'ambulance') {
            $row = $this->db->where('id', $id)->get('ambulance_call')->row();
            if (empty($row)) {
                echo json_encode(array('status' => 0, 'message' => $this->lang->line('no_record_found')));
                return;
            }
            $discount_percentage = ($this->input->post('discount_percentage') !== null && $this->input->post('discount_percentage') !== '')
                ? (float) $this->input->post('discount_percentage')
                : (float) $row->discount_percentage;
            $tax_pct  = (float) $row->tax_percentage;
            $discount = ($apply_charge * $discount_percentage) / 100;
            $tax      = (($apply_charge - $discount) * $tax_pct) / 100;
            $net      = ($apply_charge - $discount) + $tax;
            $this->db->where('id', $id)->update('ambulance_call', array(
                'amount'              => $apply_charge,
                'discount'            => $discount,
                'discount_percentage' => $discount_percentage,
                'net_amount'          => $net,
            ));
            echo json_encode(array('status' => 1, 'message' => $this->lang->line('success_message'), 'amount' => $net));
            return;
        }

        echo json_encode(array('status' => 0, 'message' => $this->lang->line('invalid_input')));
    }

    /**
     * Delete a final-bill line. Pharmacy blocked.
     */
    public function delete_bill_item()
    {
        $module = $this->input->post('module');
        $id     = (int) $this->input->post('id');

        if ($module === 'pharmacy' || $id < 1) {
            echo json_encode(array('status' => 0, 'message' => $this->lang->line('access_denied')));
            return;
        }

        if ($module === 'opd') {
            $this->charge_model->deleteOpdPatientCharge($id);
        } elseif ($module === 'ipd') {
            $this->charge_model->deleteIpdPatientCharge($id);
        } elseif ($module === 'pathology') {
            $this->pathology_model->deletePathologyBill($id);
        } elseif ($module === 'radiology') {
            $this->radio_model->deleteRadiologyBill($id);
        } elseif ($module === 'bloodissue' || $module === 'componentissue') {
            $this->bloodissue_model->delete($id);
        } elseif ($module === 'ambulance') {
            $this->vehicle_model->delete($id);
        } else {
            echo json_encode(array('status' => 0, 'message' => $this->lang->line('invalid_input')));
            return;
        }

        echo json_encode(array('status' => 1, 'message' => $this->lang->line('delete_message')));
    }

    /**
     * Add OPD/IPD charge to case from final billing. Other modules use their own create flows.
     */
    public function add_bill_item()
    {
        $module       = $this->input->post('module');
        $case_id      = $this->input->post('case_id');
        $charge_id    = (int) $this->input->post('charge_id');
        $qty          = (float) $this->input->post('qty');
        $apply_charge = (float) $this->input->post('apply_charge');
        $discount_percentage = (float) $this->input->post('discount_percentage');
        $note         = $this->input->post('note');

        if ($module === 'pharmacy') {
            echo json_encode(array('status' => 0, 'message' => $this->lang->line('access_denied')));
            return;
        }

        if (!in_array($module, array('opd', 'ipd'), true)) {
            echo json_encode(array('status' => 0, 'message' => $this->lang->line('invalid_input')));
            return;
        }

        if ($charge_id < 1 || $qty <= 0 || $apply_charge < 0 || empty($case_id)) {
            echo json_encode(array('status' => 0, 'message' => $this->lang->line('invalid_input')));
            return;
        }

        $charge = $this->charge_model->getDetails($charge_id, "");
        if (empty($charge)) {
            echo json_encode(array('status' => 0, 'message' => $this->lang->line('no_record_found')));
            return;
        }

        $tax_pct         = isset($charge->percentage) ? (float) $charge->percentage : 0;
        $discount_amount = ($apply_charge * $discount_percentage) / 100;
        $tax_amount      = (($apply_charge - $discount_amount) * $tax_pct) / 100;
        $amount          = ($apply_charge - $discount_amount) + $tax_amount;
        $charge_date     = $this->parsePostedServiceDate($this->input->post('service_date'));

        $insert = array(
            'date'                => $charge_date,
            'charge_id'           => $charge_id,
            'qty'                 => $qty,
            'apply_charge'        => $apply_charge,
            'standard_charge'     => $charge->standard_charge,
            'tpa_charge'          => 0,
            'amount'              => $amount,
            'discount_percentage' => $discount_percentage,
            'tax'                 => $tax_pct,
            'note'                => $note,
            'created_at'          => date('Y-m-d'),
        );

        if ($module === 'opd') {
            $opd = $this->patient_model->getDetailsopdByCaseId($case_id);
            if (empty($opd['opdid'])) {
                echo json_encode(array('status' => 0, 'message' => $this->lang->line('no_record_found')));
                return;
            }
            $insert['opd_id'] = $opd['opdid'];
        } else {
            $ipd = $this->patient_model->getDetailsipdByCaseId($case_id);
            if (empty($ipd['ipdid'])) {
                echo json_encode(array('status' => 0, 'message' => $this->lang->line('no_record_found')));
                return;
            }
            $insert['ipd_id'] = $ipd['ipdid'];
        }

        $this->charge_model->add_charges($insert);
        echo json_encode(array('status' => 1, 'message' => $this->lang->line('record_saved_successfully')));
    }

    public function print_patient_bill()
    {
        $this->session->set_userdata('top_menu', 'bill');

        $data['pathology_bill_prefix']  = $this->customlib->getSessionPrefixByType('pathology_billing');
        $data['radiology_bill_prefix']  = $this->customlib->getSessionPrefixByType('radiology_billing');
        $data['blood_bank_bill_prefix'] = $this->customlib->getSessionPrefixByType('blood_bank_billing');
        $data['pharmacy_bill_prefix']   = $this->customlib->getSessionPrefixByType('pharmacy_billing');
        $data['transaction_prefix']     = $this->customlib->getSessionPrefixByType('transaction_id');
        $case_id         = $this->input->post('case_id');
        $data['case_id'] = $case_id;

        $print_details         = $this->printing_model->get('', 'bill');
        $data['print_details'] = $print_details;

        $patient         = $this->enrichPatientBillDetails($case_id);
        $data['patient'] = $patient;

        $data['opd_data']         = $this->charge_model->getopdChargesbyCaseId($case_id);
        $data['ipd_data']         = $this->charge_model->getipdChargesbyCaseId($case_id);
        $data['pharmacy_data']    = $this->pharmacy_model->getpharmacybillByCaseId($case_id);
        $data['pharmacy_medicines'] = $this->getPharmacyMedicinesByCaseId($case_id);
        $data['radiology_data']   = $this->radio_model->getradiologyByCaseId($case_id);
        $data['pathology_data']   = $this->pathology_model->getpathologyByCaseId($case_id);
        $data['pathology_tests']  = $this->getPathologyTestsByCaseId($case_id);
        $data['bloodissue_data']  = $this->bloodissue_model->getbloodissueByCaseId($case_id);
        $data['transaction_data'] = $this->transaction_model->getTransactionByCaseId($case_id);

        $data['componentissue_data']  = $this->bloodissue_model->getcomponentissueByCashId($case_id);
        $data["ambulance_data"]  =  $this->patient_model->getPatientAmbulanceVisitDetails($patient['patient_id']);
        $this->load->model('bill_model');
        $data['overall_discount'] = $this->bill_model->getOverallDiscountForCase($case_id);

        $posting_type = strtolower(trim((string) $this->input->post('posting_type')));
        if (!in_array($posting_type, array('opd', 'ipd'), true)) {
            $posting_type = null;
        }
        $bill_presentation = $this->getPatientBillPresentationData($patient, $case_id, $posting_type);
        $data = array_merge($data, $bill_presentation);

        $page                     = $this->load->view('admin/bill/_print_patient_bill', $data, true);
        echo json_encode(array('status' => 1, 'page' => $page));
    }

    public function generate_bill()
    {
        $case_id               = $this->input->post('case_id');
        $module_type           = $this->input->post('module_type');
        $id                    = $this->input->post('id');
        $print_details         = $this->printing_model->get('', 'paymentreceipt');
        $patient               = $this->patient_model->getDetailsByCaseId($case_id);
        $data['patient']       = $patient;
        $data['print_details'] = $print_details;
        $data['case_id']       = $case_id;
        $transaction           = $this->transaction_model->ipdopdPaymentByCaseId($case_id);
        $data['paid_amount']   = $transaction;
        $data['advance_amount'] = $this->transaction_model->getAdvanceTotalByCaseId($case_id);
        $refund                = $this->transaction_model->getopdIpdrefundbyCaseId($case_id);
        if (!empty($refund)) {
            $data['refund'] = $refund['amount'];
        } else {
            $data['refund'] = 0;
        }

        $data['charge_details'] = $this->transaction_model->get_ipdopdchargebycaseId($case_id);
        $data['module_type']    = $module_type;
        $data['bill_payment_mode'] = $this->transaction_model->getPaymentModesByCaseId($case_id);
        $page                   = $this->load->view('admin/bill/_generatebill', $data, true);
        echo json_encode(array('status' => 1, 'page' => $page));
    }

    public function generate_bill_result()
    {
        $case_id                = $this->input->post('case_id');
        $module_type            = $this->input->post('module_type');
        $id                     = $this->input->post('id');
        $print_details          = $this->printing_model->get('', 'paymentreceipt');
        $patient                = $this->patient_model->getDetailsByCaseId($case_id);
        $data['patient']        = $patient;
        $data['print_details']  = $print_details;
        $data['case_id']        = $case_id;
        $transaction            = $this->transaction_model->ipdopdPaymentByCaseId($case_id);
        $data['paid_amount']    = $transaction;
        $data['advance_amount'] = $this->transaction_model->getAdvanceTotalByCaseId($case_id);
        $data['charge_details'] = $this->transaction_model->get_ipdopdchargebycaseId($case_id);

        $data['module_type'] = $module_type;
        $refund              = $this->transaction_model->getopdIpdrefundbyCaseId($case_id);
        if (!empty($refund)) {
            $data['refund'] = $refund['amount'];
        } else {
            $data['refund']   = 0;
            $refund['amount'] = 0;
        }

        $data['discharge_card'] = $this->patient_model->get_dischargeCard(array('case_reference_id' => $case_id));
        $data['bill_payment_mode'] = $this->transaction_model->getPaymentModesByCaseId($case_id);
        $page                   = $this->load->view('admin/bill/_generatebill_result', $data, true);

        if ($data['charge_details'][0]['tax'] > 0) {
            $tax = (($data['charge_details'][0]['apply_charge'] * $data['charge_details'][0]['tax']) / 100);
        } else {
            $tax = 0;
        }

        if ($refund['amount'] > 0) {
            $due_amount = amountFormat(($data['charge_details'][0]['amount'] - $data['paid_amount']['total_pay']) - $refund['amount']);
        } else {
            $due_amount = amountFormat($data['charge_details'][0]['amount'] - $data['paid_amount']['total_pay']);
        }

        if ($data['charge_details'][0]['ipd_id'] != '') {
            $event_data = array(
                'patient_id' => $patient['patient_id'],
                'ipd_no'     => $this->customlib->getSessionPrefixByType('ipd_no') . $data['charge_details'][0]['ipd_id'],
                'case_id'    => $case_id,
                'net_amount' => $data['charge_details'][0]['apply_charge'],
                'total'      => $data['charge_details'][0]['amount'],
                'tax'        => $tax,
                'paid'       => $data['paid_amount']['total_pay'],
                'due'        => $due_amount,
            );
            $this->system_notification->send_system_notification('add_ipd_generate_bill', $event_data);
        }

        if ($data['charge_details'][0]['opd_id'] != '') {
            $event_data = array(
                'patient_id' => $patient['patient_id'],
                'opd_id'     => $this->customlib->getSessionPrefixByType('opd_no') . $data['charge_details'][0]['opd_id'],
                'case_id'    => $case_id,
                'net_amount' => $data['charge_details'][0]['apply_charge'],
                'total'      => $data['charge_details'][0]['amount'],
                'tax'        => $tax,
                'paid'       => $data['paid_amount']['total_pay'],
                'due'        => $due_amount,
            );
            $this->system_notification->send_system_notification('add_opd_generate_bill', $event_data);
        }

        echo json_encode(array('status' => 1, 'page' => $page));
    }

    public function getBloodbankTransaction()
    {
        $billing_id                 = $this->input->post('id');
        $data['patient_id']         = $this->input->post('patient_id');
        $data['blood_issue_detail'] = $this->bloodissue_model->getDetail($billing_id);
        $transaction                = $this->transaction_model->bloodbankPayments($billing_id);
        $data["billing_id"]         = $billing_id;
        $data["payment_mode"]       = $this->payment_mode;
        $data['transaction']        = $transaction;
        $page                       = $this->load->view("admin/bill/_getBloodbankTransaction", $data, true);
        echo json_encode(array('status' => 1, 'page' => $page));
    }

    public function getRadiologyTransaction()
    {
        $radiology_billing_id          = $this->input->post('id');
        $radiology_transaction         = $this->transaction_model->radiologyPayments($radiology_billing_id);
        $data["radiology_billing_id"]  = $radiology_billing_id;
        $data["payment_mode"]          = $this->payment_mode;
        $data['radiology_transaction'] = $radiology_transaction;
        $page                          = $this->load->view("admin/bill/_getRadiologyTransaction", $data, true);
        echo json_encode(array('status' => 1, 'page' => $page));
    }

    public function getrefund($case_id)
    {
        $data['case_id']      = $case_id;
        $patient              = $this->patient_model->getDetailsByCaseId($case_id);
        $data['opd_id']       = $patient['opdid'];
        $data['ipd_id']       = $patient['ipdid'];
        $refund               = $this->transaction_model->getopdIpdrefundbyCaseId($case_id);
        if(!empty($refund )){
            $data['id']           = $refund['id'];        
            $data["refund"]       = $refund;
        }else{
            $data['id']           = '';        
            $data["refund"]       = '';            
        }
        $data["payment_mode"] = $this->payment_mode;
        $page                 = $this->load->view('admin/bill/_opdipd_refund', $data, true);
        echo json_encode(array('status' => 1, 'page' => $page));

    }

    public function add_refund()
    {       
        $this->form_validation->set_rules('amount', $this->lang->line('amount'), 'trim|required|xss_clean');
        $this->form_validation->set_rules('payment_date', $this->lang->line('payment_date'), 'trim|required|xss_clean');
        $this->form_validation->set_rules('payment_mode', $this->lang->line('payment_mode'), 'trim|required|xss_clean');
        $this->form_validation->set_rules('note', $this->lang->line('note'), 'trim|xss_clean');
        if ($_POST['payment_mode'] == "Cheque") {
            $this->form_validation->set_rules('cheque_no', $this->lang->line('cheque_no'), 'trim|required|xss_clean');
            $this->form_validation->set_rules('cheque_date', $this->lang->line('cheque_date'), 'trim|required|xss_clean');
        }

        $this->form_validation->set_rules('document', $this->lang->line('document'), 'callback_validateCanUploadFile[document]');

        if ($this->form_validation->run() == false) {
            $msg = array(
                'amount'       => form_error('amount'),
                'payment_mode' => form_error('payment_mode'),
                'payment_date' => form_error('payment_date'),
                'cheque_date'  => form_error('cheque_date'),
                'cheque_no'    => form_error('cheque_no'),
                'document'     => form_error('document'),
            );

            $array = array('status' => 'fail', 'error' => $msg, 'message' => '');
        } else {

            $payment_date = $this->customlib->dateFormatToYYYYMMDD($this->input->post("payment_date", TRUE));
            $cheque_date  = $this->customlib->dateFormatToYYYYMMDD($this->input->post("cheque_date", TRUE));
            $amount       = $this->input->post('amount', TRUE);

            $data = array(
                'case_reference_id' => $this->input->post('case_reference_id', TRUE),
                'id'                => $this->input->post('id', TRUE),
                'amount'            => $amount,
                'type'              => 'refund',
                'payment_mode'      => $this->input->post('payment_mode', TRUE),
                'note'              => $this->input->post('note', TRUE),
                'payment_date'      => $payment_date,
                'received_by'       => $this->customlib->getLoggedInUserID(),
            );

            if ($this->input->post('ipd_id', TRUE) != "") {
                $data['ipd_id'] = $this->input->post('ipd_id', TRUE);
            }
            if ($this->input->post('opd_id', TRUE) != "") {
                $data['opd_id'] = $this->input->post('opd_id', TRUE);
            }

            $insert_id = $this->transaction_model->add($data);
            $img_name  = "";
            if (isset($_FILES["document"]) && !empty($_FILES['document']['name'])) {
                $fileInfo = pathinfo($_FILES["document"]["name"]);
                $img_name = $insert_id . '.' . $fileInfo['extension'];
                if (move_uploaded_file($_FILES["document"]["tmp_name"], "./uploads/payment_document/" . $img_name)) {
                    $data_img = array('id' => $insert_id, 'document' => $img_name);

                    // SaaS: add the uploaded refund document size to the storage quota usage.
                    try {
                        $this->saasvalidation->updateStorageLimit('storage', ['document']);
                    } catch (Exception $e) {
                        log_message('error', 'SaaS storage quota update failed (bill add_refund): ' . $e->getMessage());
                    }
                }
            }

            if ($this->input->post('payment_mode', TRUE) == "Cheque") {
                $data['id']          = $insert_id;
                $data['cheque_date'] = $cheque_date;
                $data['cheque_no']   = $this->input->post('cheque_no', TRUE);
                $data['attachment']  = $img_name;
                $this->transaction_model->add($data);
            }

            $array = array('status' => 'success', 'error' => '', 'message' => $this->lang->line('record_saved_successfully'));

        }

        echo json_encode($array);
    }

    public function patient_discharge($case_id)
    {
        $data['case_id']        = $case_id;
		$type                   = $this->input->post('module_type');
        $patient                = $this->patient_model->getDetailsByCaseId($case_id,$type);
        		
		if($type != 'ipd'){
			$data['opd_id']         = $patient['opdid'];
			$data['ipd_id']         = '';
            $data['discharge_card'] = $this->patient_model->get_dischargeCard(array('case_reference_id' => $case_id,'opd_details_id' => $patient['opdid']));
		}else{
			$data['opd_id']         = '';
			$data['ipd_id']         = $patient['ipdid'];
            $data['discharge_card'] = $this->patient_model->get_dischargeCard(array('case_reference_id' => $case_id,'ipd_details_id' => $patient['ipdid']));
		}
	
        $data['guardian_name']  = "";
        $data['deathrecord']    = array();
        $data['patient_id']     = $patient['patient_id'];
        $data['guardian_name']  = $patient['guardian_name'];        
        
        if (!empty($data['discharge_card']) && $data['discharge_card']['discharge_status'] == '1') {
            $death_record = $this->birthordeath_model->getDeDetailsbycaseId($case_id);
            $id           = $death_record['id'];
            $this->load->helper('customfield_helper');
            $cutom_fields_data = get_custom_table_values($id, 'discharge_card');
            $deathrecord       = $this->birthordeath_model->getDeDetails($id);
            if (!empty($deathrecord)) {
                $deathrecord["death_date"] = $this->customlib->YYYYMMDDHisTodateFormat($deathrecord['death_date'], $this->time_format);
                $deathrecord['field_data'] = $cutom_fields_data;
                $data['guardian_name']     = $deathrecord['guardian_name'];
            }
            $data['deathrecord'] = $deathrecord;
        }
        $data['death_status'] = $this->customlib->discharge_status();   
        
        $page                 = $this->load->view('admin/bill/_patient_discharge', $data, true);
        echo json_encode(array('status' => 1, 'page' => $page));
    }

    public function add_discharge()
    {
        $this->form_validation->set_rules('discharge_date', $this->lang->line('discharge_date'), 'trim|required|xss_clean');
        $this->form_validation->set_rules('death_status', $this->lang->line('discharge_status'), 'trim|required|xss_clean');
        $this->form_validation->set_rules('document', $this->lang->line('document'), 'callback_handle_doc_upload[document]|callback_validateCanUploadFile[document]');

        if ($_POST['death_status'] == "1") {
            $this->form_validation->set_rules('death_date', $this->lang->line('death_date'), 'trim|required|xss_clean');
            $this->form_validation->set_rules('guardian_name', $this->lang->line('guardian_name'), 'trim|required|xss_clean');
            $custom_fields = $this->customfield_model->getByBelong('death_report');
            if (!empty($custom_fields)) {
                foreach ($custom_fields as $custom_fields_key => $custom_fields_value) {
                    if ($custom_fields_value['validation']) {
                        $custom_fields_id   = $custom_fields_value['id'];
                        $custom_fields_name = $custom_fields_value['name'];

                        $this->form_validation->set_rules("custom_fields[death_report][" . $custom_fields_id . "]", $custom_fields_name, 'trim|required');
                    }
                }
            }

        } elseif ($_POST['death_status'] == "2") {
            $this->form_validation->set_rules('referral_date', $this->lang->line('referral_date'), 'trim|required|xss_clean');
            $this->form_validation->set_rules('hospital_name', $this->lang->line('hospital_name'), 'trim|required|xss_clean');
        }
        if ($this->form_validation->run() == false) {
            $msg = array(
                'discharge_date' => form_error('discharge_date'),
                'death_status'   => form_error('death_status'),
                'death_date'     => form_error('death_date'),
                'referral_date'  => form_error('referral_date'),
                'hospital_name'  => form_error('hospital_name'),
                'guardian_name'  => form_error('guardian_name'),
                'document'       => form_error('document'),
            );

            if (!empty($custom_fields)) {
                foreach ($custom_fields as $custom_fields_key => $custom_fields_value) {
                    if ($custom_fields_value['validation']) {
                        $custom_fields_id                                                     = $custom_fields_value['id'];
                        $custom_fields_name                                                   = $custom_fields_value['name'];
                        $error_msg2["custom_fields[death_report][" . $custom_fields_id . "]"] = form_error("custom_fields[death_report][" . $custom_fields_id . "]");
                    }
                }
            }
            if (!empty($error_msg2)) {
                $error_msg = array_merge($msg, $error_msg2);
            } else {
                $error_msg = $msg;
            }
            $array = array('status' => 'fail', 'error' => $error_msg, 'message' => '');
        } else {

            $discharge_date = $this->input->post("discharge_date", TRUE);

            if ($discharge_date != "") {
                $discharge_date = $this->customlib->dateFormatToYYYYMMDDHis($discharge_date, $this->time_format);
            } else {
                $discharge_date != "";
            }

            $death_date = $this->customlib->dateFormatToYYYYMMDDHis($this->input->post("death_date", TRUE), $this->time_format);
            $refer_date = $this->customlib->dateFormatToYYYYMMDDHis($this->input->post("referral_date", TRUE), $this->time_format);
            $opd_id     = $this->input->post('opd_id', TRUE);
            $ipd_id     = $this->input->post('ipd_id', TRUE);

            $data = array(
                'id'                  => $this->input->post('id', TRUE),
                'case_reference_id'   => $this->input->post('case_reference_id', TRUE),
                'discharge_status'    => $this->input->post('death_status', TRUE),
                'operation'           => $this->input->post('operation', TRUE),
                'diagnosis'           => $this->input->post('diagnosis', TRUE),
                'investigations'      => $this->input->post('investigations', TRUE),
                'treatment_home'      => $this->input->post('treatment_home', TRUE),
                'note'                => $this->input->post('note', TRUE),
                'discharge_date'      => $discharge_date,
                'death_date'          => $death_date,
                'refer_date'          => $refer_date,
                'refer_to_hospital'   => $this->input->post('hospital_name', TRUE),
                'reason_for_referral' => $this->input->post('reason_for_referral', TRUE),
                'discharge_by'        => $this->customlib->getLoggedInUserID(),
            );

            if ($opd_id != '') {
                $data['opd_details_id'] = $opd_id;
            }

            if ($ipd_id != '') {
                $data['ipd_details_id'] = $ipd_id;
            }

            $insert_id = $this->patient_model->add_discharge($data);
            if ($ipd_id != '') {
                $bed_history_data['case_reference_id'] = $this->input->post('case_reference_id');
                $bed_history_data['to_date']           = $discharge_date;
                $bed_history_data['is_active']         = "no";
                $this->bed_model->updateBedHistory($bed_history_data);
            }
            if ($_POST['death_status'] == "1") {
                $custom_field_post  = $this->input->post("custom_fields[death_report]");
                $custom_value_array = array();
                if (!empty($custom_field_post)) {
                    foreach ($custom_field_post as $key => $value) {
                        $check_field_type = $this->input->post("custom_fields[death_report][" . $key . "]", TRUE);
                        $field_value      = is_array($check_field_type) ? implode(",", $check_field_type) : $check_field_type;
                        $array_custom     = array(
                            'belong_table_id' => 0,
                            'custom_field_id' => $key,
                            'field_value'     => $field_value,
                        );
                        $custom_value_array[] = $array_custom;
                    }
                }
                $deathrecord_id = $this->input->post('deathrecord_id', TRUE);
                $death_data     = array(
                    'id'                => $deathrecord_id,
                    'patient_id'        => $this->input->post('patient_id', TRUE),
                    'guardian_name'     => $this->input->post('guardian_name', TRUE),
                    'case_reference_id' => $this->input->post('case_reference_id', TRUE),
                    'death_date'        => $death_date,
                    'death_report'      => $this->input->post('death_report', TRUE),
                    'is_active'         => 'yes',
                );

                $attachment      = "";
                $attachment_name = "";
                if (isset($_FILES["document"]) && !empty($_FILES['document']['name'])) {
                    $fileInfo        = pathinfo($_FILES["document"]["name"]);
                    $attachment      = uniqueFileName() . '.' . $fileInfo['extension'];
                    $attachment_name = $_FILES["document"]["name"];
                    if (move_uploaded_file($_FILES["document"]["tmp_name"], "./uploads/death_image/" . $attachment)) {
                        $death_data['attachment']      = $attachment;
                        $death_data['attachment_name'] = $attachment_name;

                        // SaaS: add the uploaded death-image document size to the storage quota usage.
                        try {
                            $this->saasvalidation->updateStorageLimit('storage', ['document']);
                        } catch (Exception $e) {
                            log_message('error', 'SaaS storage quota update failed (bill add_discharge): ' . $e->getMessage());
                        }
                    }
                    // closing braces of the wrapping move-success branch
                    // (original $death_data assignments moved inside success block above)
                }

                $insert_ids = $this->birthordeath_model->addDeathdata($death_data);

                //update death status in patient table
                $patient_data = array('id' => $this->input->post('patient_id', TRUE), 'is_dead' => 'yes');
                $this->patient_model->add($patient_data);

                $this->customfield_model->insertRecord($custom_value_array, $insert_id);

                $event_data = array(
                    'patient_id' => $this->input->post('patient_id', TRUE),
                    'case_id'    => $this->input->post('case_reference_id', TRUE),
                    'death_date' => $this->customlib->YYYYMMDDHisTodateFormat($this->input->post("death_date", TRUE), $this->time_format),
                );

                $this->system_notification->send_system_notification('add_death_record', $event_data);
            }

            $array = array('status' => 'success', 'error' => '', 'message' => $this->lang->line('record_saved_successfully'));

            $doctor_list  = $this->patient_model->getDoctorsipd($ipd_id);
            $death_status = $this->customlib->discharge_status($this->input->post('death_status'));

            $total_amount   = 0;
            $case_id        = $this->input->post('case_reference_id');
            $transaction    = $this->transaction_model->ipdopdPaymentByCaseId($case_id);
            $charge_details = $this->transaction_model->get_ipdopdchargebycaseId($case_id);
            foreach ($charge_details as $key => $value) {
                $total_amount += amountFormat($value['amount']);
            }

            $paid_amount    = amountFormat($transaction['total_pay']);
            $balance_amount = amountFormat($total_amount - $paid_amount);

            if ($opd_id != '') {
                $event_data = array(
                    'patient_id'       => $this->input->post('patient_id'),
                    'opd_no'           => $this->customlib->getSessionPrefixByType('opd_no') . $opd_id,
                    'case_id'          => $this->input->post('case_reference_id'),
                    'discharge_date'   => $this->customlib->YYYYMMDDHisTodateFormat($discharge_date, $this->time_format),
                    'discharge_status' => $death_status,
                );

                $this->system_notification->send_system_notification('add_opd_discharge_patient', $event_data);
                $sender_details = array('patient_id' => $this->input->post('patient_id'), 'total_amount' => $total_amount, 'paid_amount' => $paid_amount, 'balance_amount' => $balance_amount, 'opd_no' => $opd_id);
                $this->mailsmsconf->mailsms('opd_patient_discharged', $sender_details);
            }

            if ($ipd_id != '') {
                $event_data = array(
                    'patient_id'       => $this->input->post('patient_id'),
                    'ipd_no'           => $this->customlib->getSessionPrefixByType('ipd_no') . $ipd_id,
                    'case_id'          => $this->input->post('case_reference_id'),
                    'discharge_date'   => $this->customlib->YYYYMMDDHisTodateFormat($discharge_date, $this->time_format),
                    'discharge_status' => $death_status,
                );

                $this->system_notification->send_system_notification('add_ipd_discharge_patient', $event_data, $doctor_list);
                $sender_details = array('patient_id' => $this->input->post('patient_id'), 'total_amount' => $total_amount, 'paid_amount' => $paid_amount, 'balance_amount' => $balance_amount); 
                $this->mailsmsconf->mailsms('ipd_patient_discharged', $sender_details);
            }
        }

        echo json_encode($array);
    }

    public function print_dischargecard()
    {
        $print_details          = $this->printing_model->get('', 'discharge_card');
        $id                     = $this->input->post('id');
        $case_id                = $this->input->post('case_id');
        $patient                = $this->patient_model->getDetailsByCaseId($case_id);
        $data['print_details']  = $print_details;
        $data['case_id']        = $case_id;
        $data['result']         = $patient;
        $type                   = $this->input->post('module_type');
        $data['discharge_card'] = $this->patient_model->get_dischargeCard(array('case_reference_id' => $case_id));
        $data['deathrecord']    = array();
        $data['patient_id']     = $patient['patient_id'];
        $data['guardian_name']  = $patient['guardian_name'];
        $download               = "";
        if (!empty($data['discharge_card']) && $data['discharge_card']['discharge_status'] == '1') {
            $death_record = $this->birthordeath_model->getDeDetailsbycaseId($case_id);
            $id           = $death_record['id'];

            $deathrecord = $this->birthordeath_model->getDeDetails($id);
            if ($deathrecord['guardian_name'] != '') {
                $cutom_fields_data = get_custom_table_values($id, 'discharge_card');
                $deathrecord["death_date"] = $this->customlib->YYYYMMDDHisTodateFormat($deathrecord['death_date'], $this->time_format);
                $deathrecord['field_data'] = $cutom_fields_data;
                $data['guardian_name']     = $deathrecord['guardian_name'];
            }
            $data['deathrecord'] = $deathrecord;
        }
        if ((!empty($deathrecord)) && $deathrecord['attachment'] != "") {
            $download = '&nbsp;<a title= ' . $this->lang->line('download') . ' href=' . site_url('admin/birthordeath/download_deathrecord/' . $deathrecord['id']) . '   class="" data-recordId=""  data-original-title=""><i class="fa fa-download"></i> </a>';
        }

        $action = '<a href="javascript:void(0);" class="btn btn-sm btn-light print_dischargecard" data-bs-toggle="tooltip" title="' . $this->lang->line('print') . '" data-module_type="' . $type . '" data-case_id="' . $case_id . '" data-recordId="' . $id . '"><i class="fa fa-print"></i></a>' . $download;
        $page   = $this->load->view('admin/bill/_printDischargeCard', $data, true);
        echo json_encode(array('status' => 1, 'page' => $page, 'action' => $action));
    }

    public function printBillReport()
    {
        $case_reference_id = $this->input->post("case_reference_id");
        $patient           = $this->patient_model->getDetailsByCaseId($case_reference_id);

        $opd_data                    = $this->patient_model->getPatientChargePaymentOPD($case_reference_id);
        $ipd_data                    = $this->patient_model->getPatientChargePaymentIPD($case_reference_id);
        $pharmacy_data               = $this->patient_model->getPatientChargePaymentPharmacy($case_reference_id);
        $pathology_data              = $this->patient_model->getPatientChargePaymentPathology($case_reference_id);
        $radiology_data              = $this->patient_model->getPatientChargePaymentRadiology($case_reference_id);
        $ambulance_data              = $this->patient_model->getPatientChargePaymentAmbulance($case_reference_id);
        $bloodbank_data              = $this->patient_model->getPatientChargePaymentBloodBank($case_reference_id);
        $data["charge_payment_data"] = array_merge($opd_data, $ipd_data, $pharmacy_data, $pathology_data, $radiology_data, $ambulance_data, $bloodbank_data);
        $page                        = $this->load->view("admin/bill/_print_bill_report", $data, true);
        echo json_encode(array('status' => 1, 'patient_name' => composePatientName($patient['patient_name'], $patient['patient_id']), 'page' => $page));
    }

    public function getAmbulanceCallTransaction()
    {
        $billing_id                    = $this->input->post('id');
        $patient_referance_case_id     = $this->input->post('patient_referance_case_id');        
        $transaction    = $this->transaction_model->ambulanceCallPayments($billing_id);
        $total_payment  = $this->vehicle_model->getBillDetailsAmbulance($billing_id);
        $balance_amount = $total_payment['net_amount'] - $total_payment['paid_amount'];

        $data["patient_referance_case_id"]     = $patient_referance_case_id;
        $data["balance_amount"] = $balance_amount;
        $data["patient_id"]     = $total_payment['patient_id'];
        $data["billing_id"]     = $billing_id;
        $data["payment_mode"]   = $this->payment_mode;
        $data['transaction']    = $transaction;
        $data['time_format']    = $this->time_format;
        $data['ambullance_call_detail'] =$total_payment;
        $page                   = $this->load->view("admin/bill/_getambulanceTransaction", $data, true);
        echo json_encode(array('status' => 1, 'page' => $page));
    }

    public function print_ambulance_Transaction()
    {
        $print_details              = $this->printing_model->get('', 'paymentreceipt');
        $id                         = $this->input->post('id');
        $charge                     = array();
        $data['transaction_prefix'] = $this->customlib->getSessionPrefixByType('transaction_id');
        $transaction                = $this->transaction_model->ambulanceCallPaymentByTransactionId($id);
        $data['print_details']      = $print_details;
        $data['transaction']        = $transaction;
        $page                       = $this->load->view("admin/bill/print_ambulance_Transaction", $data, true);
        echo json_encode(array('status' => 1, 'page' => $page));
    }

    public function discharge_revert()
    {
        $discharge_module       = $this->input->post('module_type');
        $case_id                = $this->input->post('case_id');
        $data['discharge_card'] = $this->patient_model->get_dischargeCard(array('case_reference_id' => $case_id));

        if ($data['discharge_card']['discharge_status'] != '') {
            $death_status = $this->customlib->discharge_status($data['discharge_card']['discharge_status']);
        } else {
            $death_status = '';
        }
        
        $revert_date = date('Y-m-d H:i:s');
        
        if ($discharge_module == 'ipd') {
            $discharge_data = $this->patient_model->get_patientbed($data['discharge_card']['ipd_details_id']);

            if ($data['discharge_card']['ipd_details_id'] != '') {
                $patientid = $this->patient_model->get_patientidbyIpdId($data['discharge_card']['ipd_details_id']);
                $patientid = $patientid['patient_id'];
            } else {
                $patientid = '';
            }

            if ($discharge_data['is_active'] == 'no') {
                $array = array('status' => 'fail', 'message' => $this->lang->line('previous_bed_already_alloted_to_another_patient'), 'data' => '');
            } else {
                $discharge_data['opd_details_id'] = $data['discharge_card']['opd_details_id'];
                $array                            = array('status' => 'success', 'message' => '', 'data' => $discharge_data);

                $patient_details = $this->patient_model->get_patientidbyIpdId($ipd_details_id);
                 
                $event_data     = array(
                    'revert_date'     => $this->customlib->YYYYMMDDHisTodateFormat($revert_date, $this->customlib->getHospitalTimeFormat()),
                    'patient_id'      => $patientid['patient_id'],
                    'ipd_no'          => $this->customlib->getSessionPrefixByType('ipd_no').$data['discharge_card']['ipd_details_id'],
                    'case_id'         => $case_id,
                    'bed_group'       => $discharge_data['bed_group_name'],
                    'bed_no'          => $discharge_data['bed_name'],
                    'revert_reason'   => $this->input->post("discharge_revert_reason"),
                );

                $this->system_notification->send_system_notification('ipd_patient_discharge_revert', $event_data);
            }

        } else {
            if (!empty($data['discharge_card']['ipd_details_id'])) {
                $array = array('status' => 'fail', 'message' => $this->lang->line('please_discharge_revert_from_ipd_then_it_will_be_revert_from_opd'), 'data' => '');

            } else {
                if (!empty($data['discharge_card']['opd_details_id'])) {
                    $this->patient_model->opd_discharge_revert($data['discharge_card']['opd_details_id']);
                    $this->patient_model->remove_dischargeCard($data['discharge_card']);
					
                    $this->birthordeath_model->remove_death($case_id);					
					
                    $array = array('status' => 'success', 'message' => $this->lang->line('patient_has_been_discharged_from_opd'));

                    if ($data['discharge_card']['opd_details_id'] != '') {
                        $patientid = $this->patient_model->get_patientidbyopdid($data['discharge_card']['opd_details_id']);
                        $patientid = $patientid['patient_id'];
                    } else {
                        $patientid = '';
                    }

                    $event_data = array(
                        'revert_date'     => $this->customlib->YYYYMMDDHisTodateFormat($revert_date, $this->customlib->getHospitalTimeFormat()),
                        'patient_id'       => $patientid,
                        'opd_no'           => $this->customlib->getSessionPrefixByType('opd_no') . $data['discharge_card']['opd_details_id'],
                        'discharge_status' => $death_status,
                        'discharge_date'   => $this->customlib->YYYYMMDDHisTodateFormat($data['discharge_card']['discharge_date'], $this->customlib->getHospitalTimeFormat()),
                        'case_id'          => $case_id,
                    );

                    $this->system_notification->send_system_notification('opd_patient_discharge_revert', $event_data);
                }
            }

        }
        echo json_encode($array);
    }

    public function discharged_bed_revert()
    {
        $this->form_validation->set_rules('bed_no', $this->lang->line('bed_no'), 'trim|required|xss_clean');
        $this->form_validation->set_rules('discharge_revert_reason', $this->lang->line('revert_reason'), 'trim|required|xss_clean');

        if ($this->form_validation->run() == false) {
            $msg = array(
                'bed_no'                  => form_error('bed_no'),
                'discharge_revert_reason' => form_error('discharge_revert_reason'),
            );

            $array = array('status' => 'fail', 'error' => $msg, 'message' => '');
        } else {
            $ipd_details_id    = $this->input->post('ipd_details_id');
            $opd_details_id    = $this->input->post('opd_details_id');
            $case_reference_id = $this->patient_model->getReferenceByIpdId($ipd_details_id);
            $patient_details = $this->patient_model->get_patientidbyIpdId($ipd_details_id);
            $bed_data          = array('id' => $this->input->post('bed_no'), 'is_active' => 'no');
            $this->bed_model->savebed($bed_data);
            $this->bed_model->updateBedHistoryStatus($case_reference_id);
            $bed_history = array(
                "case_reference_id" => $case_reference_id,
                "bed_group_id"      => $this->input->post("bed_group_id"),
                "bed_id"            => $this->input->post("bed_no"),
                "from_date"         => date("Y-m-d H:i:s"),
                "is_active"         => "yes",
                "revert_reason"     => $this->input->post("discharge_revert_reason"),
            );

            $this->bed_model->saveBedHistory($bed_history);
           
            if($ipd_details_id!=0 && $ipd_details_id!="" && !empty($ipd_details_id)){
                 $this->patient_model->ipd_discharge_revert($ipd_details_id);
            }
            if($opd_details_id!=0 && $opd_details_id!="" && !empty($opd_details_id)){
                $this->patient_model->opd_discharge_revert($opd_details_id);
            }           
            
            $discharge_data = $this->patient_model->get_dischargeCard(array('case_reference_id' => $case_reference_id));
            $this->patient_model->remove_dischargeCard($discharge_data);
            
            $patient_bed_data = $this->patient_model->get_patientbed($this->input->post('ipd_details_id'));
            $revert_date = date('Y-m-d H:i:s'); 
            $event_data     = array(
                'revert_date'     => $this->customlib->YYYYMMDDHisTodateFormat($revert_date, $this->customlib->getHospitalTimeFormat()),
                'patient_id'      => $patient_details['patient_id'],
                'ipd_no'          => $this->customlib->getSessionPrefixByType('ipd_no').$this->input->post('ipd_details_id'),
                'case_id'         => $case_reference_id,
                'bed_group'       => $patient_bed_data['bed_group_name'],
                'bed_no'          => $patient_bed_data['bed_name'],
                'revert_reason'   => $this->input->post("discharge_revert_reason"),
            );

            $this->system_notification->send_system_notification('ipd_patient_discharge_revert', $event_data);
            $array = array('status' => 'success', 'error' => '', 'message' => $this->lang->line('record_saved_successfully'));
        }

        echo json_encode($array);
    }

//=============pathology=====================
    public function pathology()
    {
        if (!$this->rbac->hasPrivilege('pathology_billing', 'can_view')) {
            access_denied();
        }
        $this->session->set_userdata('top_menu', 'bill');
        $id                  = $this->input->post("id");
        $patients            = $this->patient_model->getPatientListall();
        $data["patients"]    = $patients;
        $pathologist         = $this->staff_model->getStaffbyrole(5);
        $data["pathologist"] = $pathologist;
        $testlist            = $this->pathology_model->getpathotestDetails();
        $data["testlist"]    = $testlist;
        $data["bloodgroup"]  = $this->bloodbankstatus_model->get_product(null, 1);
        $data['fields']      = $this->customfield_model->get_custom_fields('pathology', 1);
        $data['organisation']   = $this->organisation_model->get();
        $data['module'] = 'pathology';
        $this->load->view('layout/header', $data);
        $this->load->view('admin/bill/pathology/pathology', $data);
        $this->load->view('layout/footer', $data);
    }

    public function getPatientPathologyDetails()
    {
        $is_bill    = $this->input->post('is_bill');
        $id         = $this->input->post('id');
        $data['id'] = $id;
        $result     = $this->pathology_model->getPathologyBillByID($id);

        if($result->ipd_prescription_basic_id != ''){
            $ipd_opd = $this->pharmacy_model->getIpdPrescriptionBasic($result->ipd_prescription_basic_id);            
            if(!empty($ipd_opd)){
                if($ipd_opd->ipd_id != ''){             
                $data['prescription']   =   $this->customlib->getSessionPrefixByType('ipd_prescription').$ipd_opd->id;  
            }else{
                $data['prescription']   =   $this->customlib->getSessionPrefixByType('opd_prescription').$ipd_opd->id ; 
            } 
            }else{
                $data['prescription']   = '';
            }       
                   
        }else{
            $data['prescription']   = '';
        }

        $data['result'] = $result;
        if (isset($is_bill)) {
            $data['is_bill'] = false;
        } else {
            $data['is_bill'] = true;
        }
        $data['bill_prefix']           = $this->customlib->getSessionPrefixByType('pathology_billing');
        $data['fields']                = $this->customfield_model->get_custom_fields('pathology');
        $data['pathology_test_fields'] = $this->customfield_model->get_custom_fields('pathologytest');
        $page                          = $this->load->view('admin/bill/pathology/_getPatientPathologyDetails', $data, true);
        $actions                       = "";

        if (isset($is_bill)) {
            $actions .= "<a href='javascript:void(0)' data-loading-text='<i class=\"fa fa-circle-o-notch fa-spin\"></i>' class='print_pathology_bill btn btn-sm btn-light' data-bs-toggle='tooltip' data-record-id=\"" . $id . "\" title='" . $this->lang->line('print_bill') . "'><i class='fa fa-print'></i></a>";
        } else {
            $actions .= "<a href='javascript:void(0)' data-loading-text='<i class=\"fa fa-circle-o-notch fa-spin\"></i>' class='print_bill btn btn-sm btn-light' data-bs-toggle='tooltip' data-record-id=\"" . $id . "\" title='" . $this->lang->line('print_bill') . "'><i class='fa fa-print'></i></a>";
            $actions .= "<a href='javascript:void(0)' data-loading-text='<i class=\"fa fa-circle-o-notch fa-spin\"></i>' class='edit_pathology btn btn-sm btn-light' data-bs-toggle='tooltip' data-record-id=\"" . $id . "\" title='" . $this->lang->line('edit_pathology') . "'><i class='fa fa-pencil'></i></a>";
            $actions .= "<a href='javascript:void(0)' data-loading-text='<i class=\"fa fa-circle-o-notch fa-spin\"></i>' class='delete_pathology btn btn-sm btn-light' data-bs-toggle='tooltip' data-record-id=\"" . $id . "\" title='" . $this->lang->line('delete_pathology') . "'><i class='fa fa-trash'></i></a>";
        }

        echo json_encode(array('status' => 1, 'page' => $page, 'actions' => $actions));
    }

    public function getPathologyTransaction()
    {
        $pathology_billing_id  = $this->input->post('id');
        $pathology_transaction = $this->transaction_model->pathologyPayments($pathology_billing_id);
        $is_bill               = $this->input->post('is_bill');
        if (isset($is_bill)) {
            $data['is_bill'] = true;
            $data['form_id'] = "add_pathopartial_payment";
        } else {
            $data['is_bill'] = false;
            $data['form_id'] = "add_partial_payment";
        }
        $data["pathology_billing_id"]    = $pathology_billing_id;
        $data["payment_mode"]            = $this->payment_mode;
        $data['pathology_transaction']   = $pathology_transaction;
        $pathology_billing               = $this->pathology_model->getPathologyBillByID($pathology_billing_id);
        $data['pathology_billing']       = $pathology_billing;
        $data['pathology_total_payment'] = $this->transaction_model->pathologyTotalPayments($pathology_billing_id)->total_paid;
        $page                            = $this->load->view("admin/bill/pathology/_getPathologyTransaction", $data, true);
        echo json_encode(array('status' => 1, 'page' => $page));
    }

    public function editpathology()
    {
        $id                          = $this->input->post('id', TRUE);
        $pathology_data              = $this->pathology_model->getPathologyBillByID($id);
        $data["pathology_data"]      = $pathology_data;
        $testlist                    = $this->pathology_model->getpathotestDetails();
        $data["testlist"]            = $testlist;
        $patients                    = $this->patient_model->getPatientListall();
        $data["patients"]            = $patients;
        $patient_names               = array_column($patients, 'patient_name', 'id');
        $doctors                     = $this->staff_model->getStaffbyrole(3);
        $data['custom_fields_value'] = display_custom_fields('pathology', $id);
        $data["doctors"]             = $doctors;
        $data["payment_mode"]        = $this->payment_mode;
        $page                        = $this->load->view("admin/bill/pathology/_editpathology", $data, true);
        $total_rows                  = count($pathology_data->pathology_report);
        $case_reference_id           = $pathology_data->case_reference_id;
        $patient_id                  = $pathology_data->patient_id;
        $date                        = $pathology_data->date;
        $bill_no                     = $pathology_data->id;
        $organisation_id             = $pathology_data->organisation_id;

        if($pathology_data->ipd_prescription_basic_id != ''){
            $ipd_opd = $this->pharmacy_model->getIpdPrescriptionBasic($pathology_data->ipd_prescription_basic_id);            
            if(!empty($ipd_opd)){
                if($ipd_opd->ipd_id != ''){             
                $ipd_prescription_basic_id   =   $this->customlib->getSessionPrefixByType('ipd_prescription').$ipd_opd->id;  
            }else{
                $ipd_prescription_basic_id   =   $this->customlib->getSessionPrefixByType('opd_prescription').$ipd_opd->id ; 
            } 
            }else{
                $ipd_prescription_basic_id   = '';
            }       
                   
        }else{
            $ipd_prescription_basic_id   = '';
        }

        if($organisation_id!=null || $organisation_id!=0 || $organisation_id!=""){
            $tpa_apply_status=1;
        }else{
            $tpa_apply_status=0;
        }

        echo json_encode(array('status' => 1, 'page' => $page, 'bill_prefix' => $this->customlib->getSessionPrefixByType('pathology_billing'), 'bill_no' => $bill_no, 'pathology_date' => $date, 'total_rows' => $total_rows, 'case_reference_id' => $case_reference_id, 'patient_id' => $patient_id,'patient_name' => $patient_names[$patient_id] . " (" . $patient_id . ")","organisation_id"=>$organisation_id,'tpa_apply_status'=>$tpa_apply_status,'ipd_prescription_basic_id'=>$ipd_prescription_basic_id));       
    }

    public function add_pathology_bill()
    {        
        $custom_fields = $this->customfield_model->getByBelong('pathology');
        foreach ($custom_fields as $custom_fields_key => $custom_fields_value) {
            if ($custom_fields_value['validation']) {
                $custom_fields_id   = $custom_fields_value['id'];
                $custom_fields_name = $custom_fields_value['name'];
                $this->form_validation->set_rules("custom_fields[pathology][" . $custom_fields_id . "]", $custom_fields_name, 'trim|required');
            }
        }
		
        $transaction_data     = array();
        $pathology_billing_id = $this->input->post('pathology_billing_id', TRUE);
        $prescription_no      = $this->input->post('is_prescription_no_exist', TRUE);

        $this->form_validation->set_rules('net_amount', $this->lang->line('net_amount'), 'trim|required|xss_clean');
        $this->form_validation->set_rules('total', $this->lang->line('total'), 'trim|required|xss_clean');
        $this->form_validation->set_rules('patientid', $this->lang->line('patient'), 'trim|required|xss_clean');
        $this->form_validation->set_rules('discount', $this->lang->line('discount'), 'trim|required|xss_clean');
        $this->form_validation->set_rules('tax', $this->lang->line('tax'), 'trim|required|xss_clean');
        $this->form_validation->set_rules('note', $this->lang->line('note'), 'trim|xss_clean');
        $this->form_validation->set_rules('doctor_name', $this->lang->line('doctor'), 'trim|xss_clean');
        if (empty($pathology_billing_id)) {
            $this->form_validation->set_rules(
                'amount', $this->lang->line('amount'), array('trim', 'required', 'xss_clean', 'valid_amount',
                    array('check_exists', array($this->pathology_model, 'validate_paymentamount')),
                )
            );
			
            if ($this->input->post("payment_mode", TRUE) == "Cheque") {
                $this->form_validation->set_rules('cheque_no', $this->lang->line('cheque_no'), 'trim|required');
                $this->form_validation->set_rules('cheque_date', $this->lang->line('cheque_date'), 'trim|required');
                $this->form_validation->set_rules('document', $this->lang->line('documents'), 'callback_handle_doc_upload[document]|callback_validateCanUploadFile[document]');
            }
        }

        $this->form_validation->set_rules('date', $this->lang->line('date'), 'trim|required|xss_clean');
        $total_rows = $this->input->post('total_rows', TRUE);
        if (!isset($total_rows) && !isset($pathology) && !isset($radiology)) {
            $this->form_validation->set_rules('no_records', $this->lang->line('no_records'), 'trim|required|xss_clean',
                array('required' => $this->lang->line('no_test_selected')));
        }
        $check_duplicate_test = array();
        if (isset($total_rows) && !empty($total_rows)) {
            foreach ($total_rows as $row_key => $row_value) {

                $test_name              = $this->input->post('test_name_' . $row_value, TRUE);
                $reportdate             = $this->input->post('reportdate_' . $row_value, TRUE);
                $check_duplicate_test[] = $test_name;

                if ($test_name == "") {
                    $this->form_validation->set_rules('test_name', $this->lang->line('test_name'), 'trim|required|xss_clean');
                }
                if ($reportdate == "") {
                    $this->form_validation->set_rules('reportdate', $this->lang->line('report_date'), 'trim|required|xss_clean');
                }

            }
        }
        if (!empty($check_duplicate_test)) {
            if (has_duplicate_array($check_duplicate_test)) {
                $this->form_validation->set_rules('duplicate_test', $this->lang->line("duplicate_test"), 'trim|required|xss_clean', array('required' => 'The %s not allowed.'));
            }
        }

        if ($this->form_validation->run() == false) {
            $msg = array(
                'no_records'     => form_error('no_records'),
                'patientid'      => form_error('patientid'),
                'discount'       => form_error('discount'),
                'tax'            => form_error('tax'),
                'test_name'      => form_error('test_name'),
                'reportdate'     => form_error('reportdate'),
                'amount'         => form_error('amount'),
                'duplicate_test' => form_error('duplicate_test'),
                'document'       => form_error('document'),
                'date'           => form_error('date'),
                'net_amount'     => form_error('net_amount'),
                'total'          => form_error('total'),
            );

            if (empty($pathology_billing_id)) {

                if ($this->input->post("payment_mode", TRUE) == "Cheque") {
                    $msg['cheque_no']   = form_error('cheque_no');
                    $msg['cheque_date'] = form_error('cheque_date');
                }
            }

            if (!empty($custom_fields)) {
                foreach ($custom_fields as $custom_fields_key => $custom_fields_value) {
                    if ($custom_fields_value['validation']) {
                        $custom_fields_id                                                  = $custom_fields_value['id'];
                        $custom_fields_name                                                = $custom_fields_value['name'];
                        $error_msg2["custom_fields[pathology][" . $custom_fields_id . "]"] = form_error("custom_fields[pathology][" . $custom_fields_id . "]");
                    }
                }
            }
            if (!empty($error_msg2)) {
                $error_msg = array_merge($msg, $error_msg2);
            } else {
                $error_msg = $msg;
            }
            $array = array('status' => 'fail', 'error' => $error_msg, 'message' => '');
        } else {
            $patient_id        = $this->input->post('patientid', TRUE);
            $bill_date         = $this->customlib->dateFormatToYYYYMMDDHis($this->input->post('date', TRUE));
            $doctor_name       = $this->input->post('doctor_name', TRUE);
            $doctor_id         = $this->input->post('consultant_doctor', TRUE);
            $case_reference_id = $this->input->post('case_reference_id', TRUE);
            if (empty($doctor_id)) {
                $doctor_id = null;
            }
            if (empty($case_reference_id)) {
                $case_reference_id = null;
            }

            if ($prescription_no != "") {
                $prescription_prefix = splitPrefixType($prescription_no);
                $prescription_no     = splitPrefixID($prescription_no);
            } else {
                $prescription_no = null;
            }

            $is_tpa = $this->input->post('is_tpa', TRUE);
            if($is_tpa=="" || $is_tpa==0){
                $organisation_id    = null;
                $insurance_id       = null;
                $insurance_validity = null;
            }else{           
                $organisation_id    = $this->input->post('organisation_id', TRUE);
                $insurance_id       = $this->input->post('insurance_id', TRUE);
                $insurance_validity = $this->customlib->dateFormatToYYYYMMDD($this->input->post('insurance_validity', TRUE));
            }
            if (empty($organisation_id)) {
                $organisation_id = null;
            }

            $data = array(
                'date'                => $bill_date,
                'patient_id'          => $patient_id,
                'doctor_name'         => $doctor_name,
                'doctor_id'           => $doctor_id,
                'case_reference_id'   => $case_reference_id,
                'ipd_prescription_basic_id' => $prescription_no,
                'total'               => $this->input->post('total', TRUE),
                'discount'            => $this->input->post('discount', TRUE),
                'discount_percentage' => $this->input->post('discount_percent', TRUE),
                'tax'                 => $this->input->post('tax', TRUE),
                'net_amount'          => $this->input->post('net_amount', TRUE),
                'note'                => $this->input->post('note', TRUE),
                'generated_by'        => $this->customlib->getLoggedInUserID(),
                'organisation_id'     => $organisation_id,
                'insurance_validity'  => $insurance_validity,
                'insurance_id'        => $insurance_id,
            );

            $custom_field_post  = $this->input->post("custom_fields[pathology]", TRUE);
            $custom_value_array = array();
            if (!empty($custom_field_post)) {
                foreach ($custom_field_post as $key => $value) {
                    $check_field_type = $this->input->post("custom_fields[pathology][" . $key . "]", TRUE);
                    $field_value      = is_array($check_field_type) ? implode(",", $check_field_type) : $check_field_type;
                    $array_custom     = array(
                        'belong_table_id' => 0,
                        'custom_field_id' => $key,
                        'field_value'     => $field_value,
                    );
                    $custom_value_array[] = $array_custom;
                }
            }

            if ($pathology_billing_id > 0) {
                $data['id'] = $pathology_billing_id;
            }

            $total_rows                = $this->input->post('total_rows', TRUE);
            $prev_reports              = $this->input->post('prev_reports', TRUE);
            $insert_array              = array();
            $update_array              = array();
            $prev_reports_array        = array();
            $prev_reports_update_array = array();
            if (isset($prev_reports)) {
                $prev_reports_array = $prev_reports;
            }

            foreach ($total_rows as $row_key => $row_value) {
                $test_report_id = $this->input->post('inserted_id_' . $row_value, TRUE);
                if ($test_report_id == 0) {
                    $report = array(
                        'pathology_bill_id' => 0,
                        'patient_id'        => $patient_id,
                        'pathology_id'      => $this->input->post('test_name_' . $row_value, TRUE),
                        'tax_percentage'    => $this->input->post('taxpercent_' . $row_value, TRUE),
                        'reporting_date'    => $this->customlib->dateFormatToYYYYMMDD($this->input->post('reportdate_' . $row_value, TRUE)),
                        'apply_charge'      => $this->input->post('amount_' . $row_value, TRUE),
                    );
                    $insert_array[] = $report;
                } else if ($test_report_id > 0) {
                    $report = array(
                        'id'             => $test_report_id,
                        'patient_id'     => $patient_id,
                        'pathology_id'   => $this->input->post('test_name_' . $row_value, TRUE),
                        'tax_percentage' => $this->input->post('taxpercent_' . $row_value, TRUE),
                        'reporting_date' => $this->customlib->dateFormatToYYYYMMDD($this->input->post('reportdate_' . $row_value, TRUE)),
                        'apply_charge'   => $this->input->post('amount_' . $row_value, TRUE),
                    );
                    $prev_reports_update_array[] = $test_report_id;
                    $update_array[]              = $report;
                }
            }

            if (empty($pathology_billing_id)) {

                $cheque_date      = $this->customlib->dateFormatToYYYYMMDD($this->input->post("cheque_date", TRUE));
                $payment_section  = $this->config->item('payment_section');
                $transaction_data = array(
                    'pathology_billing_id' => 0,
                    'patient_id'           => $patient_id,
                    'case_reference_id'    => $case_reference_id,
                    'section'              => $payment_section['pathology'],
                    'amount'               => $this->input->post('amount', TRUE),
                    'type'                 => 'payment',
                    'ipd_id'               => $this->input->post('ipdid', TRUE),
                    'payment_mode'         => $this->input->post('payment_mode', TRUE),
                    'payment_date'         => $bill_date,
                    'received_by'          => $this->customlib->getLoggedInUserID(),
                );
                if ($this->input->post('payment_mode', TRUE) == "Cheque") {
                    $transaction_data['cheque_date'] = $cheque_date;
                    $transaction_data['cheque_no']   = $this->input->post('cheque_no', TRUE);
                    if (isset($_FILES["document"]) && !empty($_FILES['document']['name'])) {
                        $file_name = $this->media_storage->fileupload("document",'./uploads/payment_document/');
                        $transaction_data['attachment']      = $file_name;
                        $transaction_data['attachment_name'] =  $_FILES["document"]["name"];

                        // SaaS: add the uploaded document's size to the storage quota usage.
                        try {
                            $this->saasvalidation->updateStorageLimit('storage', ['document']);
                        } catch (Exception $e) {
                            log_message('error', 'SaaS storage quota update failed (bill add_pathology_bill): ' . $e->getMessage());
                        }
                    }
                }
            }

            $array_delete = array_diff($prev_reports_array, $prev_reports_update_array);
            $inserted     = $this->pathology_model->addBill($data, $insert_array, $update_array, $array_delete, $pathology_billing_id, $transaction_data);

            if ($pathology_billing_id > 0) {
                if (!empty($custom_fields)) {
                    foreach ($custom_field_post as $key => $value) {
                        $check_field_type = $this->input->post("custom_fields[pathology][" . $key . "]", TRUE);
                        $field_value      = is_array($check_field_type) ? implode(",", $check_field_type) : $check_field_type;
                        $array_custom     = array(
                            'belong_table_id' => $inserted,
                            'custom_field_id' => $key,
                            'field_value'     => $field_value,
                        );
                        $custom_value_array[] = $array_custom;
                    }
                    $this->customfield_model->updateRecord($custom_value_array, $inserted, 'pathology');
                }
            } else {
                if (!empty($custom_value_array)) {
                    $this->customfield_model->insertRecord($custom_value_array, $inserted);
                }
            }
            if ($inserted) {
                $patientlist    = $this->notificationsetting_model->getpatientDetails($patient_id);

                $event_data     = array(
                    'patient_id'  => $patient_id,
                    'case_id'     => $this->input->post('case_reference_id', TRUE),
                    'bill_no'     => $this->input->post('bill_no', TRUE),
                    'date'        => $this->customlib->YYYYMMDDTodateFormat($this->input->post('date', TRUE)),                    
                    'total'       => $this->input->post('total', TRUE),
                    'discount'    => $this->input->post('discount', TRUE),
                    'tax'         => $this->input->post('tax', TRUE),
                    'net_amount'  => $this->input->post('net_amount', TRUE),
                    'paid_amount' => $this->input->post('amount', TRUE),
                );

                if ($doctor_id != "") {
                    $doctor_details            = $this->notificationsetting_model->getstaffDetails($doctor_id);
                    $event_data['doctor_name'] = composeStaffNameByString($doctor_details['name'], $doctor_details['surname'], $doctor_details['employee_id']);
                    $event_data['doctor_id']   = $doctor_id;
                } else {
                    $event_data['doctor_name'] = $doctor_name;
                    $event_data['doctor_id']   = $doctor_id;
                }

                $this->system_notification->send_system_notification('pathology_investigation', $event_data);

                $array = array('status' => 'success', 'error' => '', 'message' => $this->lang->line('success_message'), 'insert_id' => $inserted);
            } else {
                $array = array('status' => 'success', 'error' => '', 'message' => $this->lang->line('something_went_wrong'), 'insert_id' => $inserted);
            }

        }
        echo json_encode($array);
    }

    /**
     * SaaS storage pre-check (form_validation callback).
     * Returns false (blocking the save) when the upload would push the
     * tenant over its storage quota. SaasValidation sets the error message.
     */
    public function validateCanUploadFile($str, $params_string)
    {
        $storage_array = array_map('trim', explode(',', $params_string));
        return $this->saasvalidation->validateCanUploadFile($str, $storage_array);
    }

    public function delete_pathology_bill()
    {
        $id = $this->input->post('id');
        if (!$this->rbac->hasPrivilege('pathology_test', 'can_delete')) {
            access_denied();
        }
        if (!empty($id)) {
            // SaaS: release every linked transaction's attachment from storage quota before deletion.
            $saas_transactions = $this->transaction_model->pathologyPayments($id);
            if (!empty($saas_transactions)) {
                foreach ($saas_transactions as $saas_transaction) {
                    if (!empty($saas_transaction->attachment)) {
                        $doc_path = $saas_transaction->attachment;
                        $dir      = $this->media_storage->resolveAttachmentDir($doc_path);
                        $kb       = $this->media_storage->getUploadedFileSize($doc_path, $dir);
                        if ($kb > 0) {
                            try {
                                $this->saasvalidation->deleteResouceQuota('storage', $kb);
                            } catch (Exception $e) {
                                log_message('error', 'SaaS storage quota release failed (bill delete_pathology_bill transaction_id=' . $saas_transaction->id . '): ' . $e->getMessage());
                            }
                        }
                        $this->media_storage->filedelete($doc_path, $dir);
                    }
                }
            }

            $this->pathology_model->deletePathologyBill($id);
            $array = array('status' => 'success', 'error' => '', 'message' => $this->lang->line('delete_message'));
        } else {
            $array = array('status' => 'fail', 'error' => '', 'message' => $this->lang->line('something_went_wrong'));
        }
        echo json_encode($array);
    }

    public function partial_pathology_bill()
    {
        if (!$this->rbac->hasPrivilege('pathology_bill', 'can_add')) {
            access_denied();
        }

        $this->form_validation->set_rules('payment_date', $this->lang->line('date'), 'trim|required|xss_clean');
        $this->form_validation->set_rules('amount', $this->lang->line('amount'), 'trim|required|xss_clean|valid_amount');
        $this->form_validation->set_rules('payment_mode', $this->lang->line('payment_mode'), 'trim|required|xss_clean');
        $this->form_validation->set_rules('note', $this->lang->line('note'), 'trim|xss_clean');
        if ($this->input->post('payment_mode', TRUE) == "Cheque") {
            $this->form_validation->set_rules('cheque_no', $this->lang->line('cheque_no'), 'trim|required|xss_clean');
            $this->form_validation->set_rules('cheque_date', $this->lang->line('cheque_date'), 'trim|required|xss_clean');
            $this->form_validation->set_rules('document', $this->lang->line('document'), 'callback_handle_upload_document|callback_validateCanUploadFile[document]');
        }

        if ($this->form_validation->run() == false) {
            $msg = array(
                'payment_date' => form_error('payment_date'),
                'amount'       => form_error('amount'),
                'payment_mode' => form_error('payment_mode'),
                'cheque_no'    => form_error('cheque_no'),
                'cheque_date'  => form_error('cheque_date'),
                'document'     => form_error('document'),
            );

            $array = array('status' => 'fail', 'error' => $msg, 'message' => '');
        } else {
            $pathology_billing_id     = $this->input->post('pathology_billing_id', TRUE);
            $pathology_billing_detail = $this->transaction_model->pathologyTotalPayments($pathology_billing_id);
            $net_amount               = $pathology_billing_detail->net_amount;
            $amount_paying            = $this->input->post('amount', TRUE);
            $total_paid               = $pathology_billing_detail->total_paid;

            if ($net_amount >= ($total_paid + $amount_paying)) {
                $picture         = "";
                $bill_date       = $this->input->post("payment_date", TRUE);
                $payment_section = $this->config->item('payment_section');
                $payment_array   = array(
                    'amount'               => $this->input->post('amount', TRUE),
                    'type'                 => 'payment',
                    'patient_id'           => $this->input->post('patient_id', TRUE),
                    'section'              => $payment_section['pathology'],
                    'pathology_billing_id' => $this->input->post('pathology_billing_id', TRUE),
                    'payment_mode'         => $this->input->post('payment_mode', TRUE),
                    'note'                 => $this->input->post('note', TRUE),
                    'payment_date'         => $this->customlib->dateFormatToYYYYMMDDHis($bill_date, $this->customlib->getHospitalTimeFormat()),
                    'received_by'          => $this->customlib->getLoggedInUserID(),
                );

                if (!empty($this->input->post('case_reference_id', TRUE)) && $this->input->post('case_reference_id', TRUE) != "") {
                    $payment_array['case_reference_id'] = $this->input->post('case_reference_id', TRUE);
                }

                $attachment      = "";
                $attachment_name = "";

                $cheque_date = $this->input->post("cheque_date", TRUE);
                if ($this->input->post('payment_mode', TRUE) == "Cheque") {

                    $payment_array['cheque_date'] = $this->customlib->dateFormatToYYYYMMDD($cheque_date);
                    $payment_array['cheque_no']   = $this->input->post('cheque_no', TRUE);
               
                    if (isset($_FILES["document"]) && !empty($_FILES['document']['name'])) {
                        $file_name = $this->media_storage->fileupload("document",'./uploads/payment_document/');
                        $payment_array['attachment']      = $file_name;
                        $payment_array['attachment_name'] = $_FILES["document"]["name"];

                        // SaaS: add the uploaded document's size to the storage quota usage.
                        try {
                            $this->saasvalidation->updateStorageLimit('storage', ['document']);
                        } catch (Exception $e) {
                            log_message('error', 'SaaS storage quota update failed (bill partial_bill): ' . $e->getMessage());
                        }
                    }   
                }

                $this->transaction_model->add($payment_array);
                $array = array('status' => 'success', 'error' => '', 'message' => $this->lang->line('success_message'));
            } else {
                $array = array('status' => 'fail', 'error' => array('amount_invalid' => $this->lang->line('amount_should_not_be_greater_than_balance') . ' ' . amountFormat($net_amount - $total_paid)), 'message' => '');

            }

        }
        echo json_encode($array);
    }

   public function handle_upload_document()
    {
        $image_validate = $this->config->item('file_validate');

        if (isset($_FILES["document"]) && !empty($_FILES["document"]['name'])) {

            $file_type         = $_FILES["document"]['type'];
            $file_size         = $_FILES["document"]["size"];
            $file_name         = $_FILES["document"]["name"];
            $allowed_extension = $image_validate['allowed_extension'];
            $ext               = pathinfo($file_name, PATHINFO_EXTENSION);

            $allowed_mime_type = $image_validate['allowed_mime_type'];

            if ($files = @getimagesize($_FILES['document']['tmp_name'])) {
                if (!in_array($files['mime'], $allowed_mime_type)) {
                    $this->form_validation->set_message('handle_upload_document', $this->lang->line('file_type_not_allowed'));
                    return false;
                }
                if (!in_array(strtolower($ext), $allowed_extension) || !in_array($file_type, $allowed_mime_type)) {
                    $this->form_validation->set_message('handle_upload_document', $this->lang->line('file_extension_not_allowed'));
                    return false;
                }
                if ($file_size > $image_validate['upload_size']) {
                    $this->form_validation->set_message('handle_upload_document', $this->lang->line('file_size_shoud_be_less_than') . number_format($image_validate['upload_size'] / 1048576, 2) . " MB");
                    return false;
                }
            } else {
                $this->form_validation->set_message('handle_upload_document', $this->lang->line('file_type_extension_not_allowed'));
                return false;
            }

            return true;
        }
        return true;
    }
    //============end pathology=============================

    //============ radiology=============================
    public function radiology()
    {
        if (!$this->rbac->hasPrivilege('radiology_billing', 'can_view')) {
            access_denied();
        }

        $this->session->set_userdata('top_menu', 'bill');
        $id                         = $this->input->post("radiology_id");
        $radiologist                = $this->staff_model->getStaffbyrole(6);
        $data["radiologist"]        = $radiologist;
        $data['radiologist_select'] = '';
        $testlist                   = $this->radio_model->getradiotestDetails();
        $data["testlist"]           = $testlist;
        $patients                   = $this->patient_model->getPatientListall();
        $data["patients"]           = $patients;
        $data["bloodgroup"]         = $this->bloodbankstatus_model->get_product(null, 1);
        $data['fields']             = $this->customfield_model->get_custom_fields('radiology', 1);
        $data['module'] = 'radiology';
        $this->load->view('layout/header', $data);
        $this->load->view('admin/bill/radiology/radiology', $data);
        $this->load->view('layout/footer', $data);
    }

    public function getPatientRadiologyDetails()
    {
        $id                  = $this->input->post('id');
        $data['id']          = $id;
        $result              = $this->radio_model->getRadiologyBillByID($id);

        if ($result->ipd_prescription_basic_id != '') {
            $ipd_opd = $this->pharmacy_model->getIpdPrescriptionBasic($result->ipd_prescription_basic_id);
            if (!empty($ipd_opd)) {
                if ($ipd_opd->ipd_id != '') {
                    $data['prescription']   =   $this->customlib->getSessionPrefixByType('ipd_prescription') . $ipd_opd->id;
                } else {
                    $data['prescription']   =    $this->customlib->getSessionPrefixByType('opd_prescription') . $ipd_opd->id;
                }
            } else {
                $data['prescription']   = '';
            }
        } else {
            $data['prescription']   = '';
        }

        $data['bill_prefix'] = $this->customlib->getSessionPrefixByType('radiology_billing');
        $is_bill             = $this->input->post('is_bill');
        if (isset($is_bill)) {
            $data['is_bill'] = false;
        } else {
            $data['is_bill'] = true;
        }

        $data['fields'] = $this->customfield_model->get_custom_fields('radiology');
        $data['result'] = $result;
        $page           = $this->load->view('admin/bill/radiology/_getPatientRadiologyDetails', $data, true);
        $actions        = "";
        if (isset($is_bill)) {
            $actions .= "<a href='javascript:void(0)' data-loading-text='<i class=\"fa fa-circle-o-notch fa-spin\"></i>' class='print_radiology_bill btn btn-sm btn-light' data-bs-toggle='tooltip' data-record-id=\"" . $id . "\" title='" . $this->lang->line('print_bill') . "'><i class='fa fa-print'></i></a>";
        } else {
            $actions .= "<a href='javascript:void(0)' data-loading-text='<i class=\"fa fa-circle-o-notch fa-spin\"></i>' class='print_bill btn btn-sm btn-light' data-bs-toggle='tooltip' data-record-id=\"" . $id . "\" title='" . $this->lang->line('print_bill') . "'><i class='fa fa-print'></i></a>";
            $actions .= "<a href='javascript:void(0)' data-loading-text='<i class=\"fa fa-circle-o-notch fa-spin\"></i>' class='edit_radiology btn btn-sm btn-light' data-bs-toggle='tooltip' data-record-id=\"" . $id . "\" title='" . $this->lang->line('edit_radiology') . "'><i class='fa fa-pencil'></i></a>";
            $actions .= "<a href='javascript:void(0)' data-loading-text='<i class=\"fa fa-circle-o-notch fa-spin\"></i>' class='delete_radiology btn btn-sm btn-light' data-bs-toggle='tooltip' data-record-id=\"" . $id . "\" title='" . $this->lang->line('delete') . "'><i class='fa fa-trash'></i></a>";
        }
        echo json_encode(array('status' => 1, 'page' => $page, 'actions' => $actions));
    }

    public function getRadiologyTransactions()
    {
        $radiology_billing_id  = $this->input->post('id');
        $is_bill               = $this->input->post('is_bill');
        $radiology_transaction = $this->transaction_model->radiologyPayments($radiology_billing_id);
        if (isset($is_bill)) {
            $data['is_bill'] = true;
            $data['form_id'] = "add_radio_partial_payment";
        } else {
            $data['is_bill'] = false;
            $data['form_id'] = "add_partial_payment";
        }
        $radio_billing         = $this->radio_model->getRadiologyBillByID($radiology_billing_id);
        $data['radio_billing'] = $radio_billing;
        $data["radiology_billing_id"]  = $radiology_billing_id;
        $data["payment_mode"]          = $this->payment_mode;
        $data['radiology_transaction'] = $radiology_transaction;
        $page                          = $this->load->view("admin/bill/radiology/_getRadiologyTransactions", $data, true);
        echo json_encode(array('status' => 1, 'page' => $page));
    }

    public function partial_radiology_bill()
    {
        if (!$this->rbac->hasPrivilege('medicine', 'can_add')) {
            access_denied();
        }

        $this->form_validation->set_rules('payment_date', $this->lang->line('date'), 'trim|required|xss_clean');
        $this->form_validation->set_rules('amount', $this->lang->line('amount'), 'trim|required|xss_clean|valid_amount');
        $this->form_validation->set_rules('payment_mode', $this->lang->line('payment_mode'), 'trim|required|xss_clean');
        $this->form_validation->set_rules('note', $this->lang->line('note'), 'trim|xss_clean');

        if ($this->input->post('payment_mode', TRUE) == "Cheque") {
            $this->form_validation->set_rules('cheque_no', $this->lang->line('cheque_no'), 'trim|required|xss_clean');
            $this->form_validation->set_rules('cheque_date', $this->lang->line('cheque_date'), 'trim|required|xss_clean');
            $this->form_validation->set_rules('document', $this->lang->line("document"), 'callback_handle_doc_upload[document]|callback_validateCanUploadFile[document]');
        }

        if ($this->form_validation->run() == false) {
            $msg = array(
                'payment_date' => form_error('payment_date'),
                'amount'       => form_error('amount'),
                'payment_mode' => form_error('payment_mode'),
                'cheque_no'    => form_error('cheque_no'),
                'cheque_date'  => form_error('cheque_date'),
                'document'     => form_error('document'),
            );

            $array = array('status' => 'fail', 'error' => $msg, 'message' => '');
        } else {

            $radiology_billing_id     = $this->input->post('radiology_billing_id', TRUE);
            $radiology_billing_detail = $this->transaction_model->radiologyTotalPayments($radiology_billing_id);
            $net_amount               = $radiology_billing_detail->net_amount;
            $amount_paying            = $this->input->post('amount', TRUE);
            $total_paid               = $radiology_billing_detail->total_paid;

            if ($net_amount >= ($total_paid + $amount_paying)) {

                $picture         = "";
                $bill_date       = $this->input->post("payment_date", TRUE);
                $payment_section = $this->config->item('payment_section');
                $payment_array   = array(
                    'amount'               => $this->input->post('amount', TRUE),
                    'type'                 => 'payment',
                    'patient_id'           => $this->input->post('patient_id', TRUE),
                    'section'              => $payment_section['radiology'],
                    'radiology_billing_id' => $this->input->post('radiology_billing_id', TRUE),
                    'payment_mode'         => $this->input->post('payment_mode', TRUE),
                    'note'                 => $this->input->post('note', TRUE),
                    'payment_date'         => $this->customlib->dateFormatToYYYYMMDDHis($bill_date, $this->customlib->getHospitalTimeFormat()),
                    'received_by'          => $this->customlib->getLoggedInUserID(),
                );

                if (!empty($this->input->post('case_reference_id', TRUE)) && $this->input->post('case_reference_id', TRUE) != "") {
                    $payment_array['case_reference_id'] = $this->input->post('case_reference_id', TRUE);
                }

                $cheque_date = $this->input->post("cheque_date", TRUE);
                if ($this->input->post('payment_mode', TRUE) == "Cheque") {
                    $payment_array['cheque_date']     = $this->customlib->dateFormatToYYYYMMDD($cheque_date);
                    $payment_array['cheque_no']       = $this->input->post('cheque_no', TRUE);
                    if (isset($_FILES["document"]) && !empty($_FILES['document']['name'])) {
                        $file_name = $this->media_storage->fileupload("document",'./uploads/payment_document/');
                        $payment_array['attachment']      = $file_name;
                        $payment_array['attachment_name'] = $_FILES["document"]["name"];

                        // SaaS: add the uploaded document's size to the storage quota usage.
                        try {
                            $this->saasvalidation->updateStorageLimit('storage', ['document']);
                        } catch (Exception $e) {
                            log_message('error', 'SaaS storage quota update failed (bill partial_bill): ' . $e->getMessage());
                        }
                    }   
                }

                $this->transaction_model->add($payment_array);
                $array = array('status' => 'success', 'error' => '', 'message' => $this->lang->line('success_message'));
            } else {

                $array = array('status' => 'fail', 'error' => array('amount_invalid' => $this->lang->line('amount_should_not_be_greater_than_balance') . ' ' . amountFormat($net_amount - $total_paid)), 'message' => '');
            }

        }
        echo json_encode($array);
    }

    public function editradiology()
    {
        $id                          = $this->input->post('id');
        $radiology_data              = $this->radio_model->getRadiologyBillByID($id);
        $data["radiology_data"]      = $radiology_data;
        $testlist                    = $this->radio_model->getradiotestDetails();
        $data["testlist"]            = $testlist;
        $patients                    = $this->patient_model->getPatientListall();
        $data["patients"]            = $patients;
        $patient_names               = array_column($patients, 'patient_name', 'id');
        $doctors                     = $this->staff_model->getStaffbyrole(3);
        $data['custom_fields_value'] = display_custom_fields('radiology', $id);
        $data["doctors"]             = $doctors;
        $data["payment_mode"]        = $this->payment_mode;
        $page                        = $this->load->view("admin/bill/radiology/_editradiology", $data, true);
        $total_rows                  = count($radiology_data->radiology_report);
        $case_reference_id           = $radiology_data->case_reference_id;
        $patient_id                  = $radiology_data->patient_id;
        $bill_no                     = $radiology_data->id;
        $date                        = $radiology_data->date;
        $organisation_id             = $radiology_data->organisation_id;

        if ($radiology_data->ipd_prescription_basic_id != '') {
            $ipd_opd = $this->pharmacy_model->getIpdPrescriptionBasic($radiology_data->ipd_prescription_basic_id);
            if (!empty($ipd_opd)) {
                if ($ipd_opd->ipd_id != '') {
                    $ipd_prescription_basic_id   =   $this->customlib->getSessionPrefixByType('ipd_prescription') . $ipd_opd->id;
                } else {
                    $ipd_prescription_basic_id   =    $this->customlib->getSessionPrefixByType('opd_prescription') . $ipd_opd->id;
                }
            } else {
                $ipd_prescription_basic_id   = '';
            }
        } else {
            $ipd_prescription_basic_id   = '';
        }

        if($organisation_id!=null || $organisation_id!=0 || $organisation_id!=""){
            $tpa_apply_status=1;
        }else{
            $tpa_apply_status=0;
        }

        echo json_encode(array( 'status' => 1, 'page' => $page, 'bill_no' => $bill_no, 'radiology_date' => $date, 'total_rows' => $total_rows, 'case_reference_id' => $case_reference_id, 'patient_id' => $patient_id, 'patient_name' => $patient_names[$patient_id] . " (" . $patient_id . ")","organisation_id"=>$organisation_id,'tpa_apply_status'=>$tpa_apply_status ,'ipd_prescription_basic_id'=>$ipd_prescription_basic_id));        
    }

    public function add_radiology_bill()
    {
        $custom_fields = $this->customfield_model->getByBelong('radiology');
        foreach ($custom_fields as $custom_fields_key => $custom_fields_value) {
            if ($custom_fields_value['validation']) {
                $custom_fields_id   = $custom_fields_value['id'];
                $custom_fields_name = $custom_fields_value['name'];
                $this->form_validation->set_rules("custom_fields[radiology][" . $custom_fields_id . "]", $custom_fields_name, 'trim|required');
            }
        }
        $transaction_data     = array();
        $radiology_billing_id = $this->input->post('radiology_billing_id');
        $prescription_no      = $this->input->post('is_prescription_no_exist');

        $this->form_validation->set_rules('net_amount', $this->lang->line('net_amount'), 'trim|required|xss_clean');
        $this->form_validation->set_rules('total', $this->lang->line('total'), 'trim|required|xss_clean');
        $this->form_validation->set_rules('discount', $this->lang->line('discount'), 'trim|required|xss_clean');
        $this->form_validation->set_rules('patientid', $this->lang->line('patient'), 'trim|required|xss_clean');
        $this->form_validation->set_rules('tax', $this->lang->line('tax'), 'trim|required|xss_clean');
        $this->form_validation->set_rules('note', $this->lang->line('note'), 'trim|xss_clean');
        $this->form_validation->set_rules('doctor_name', $this->lang->line('doctor'), 'trim|xss_clean');

        if (empty($radiology_billing_id)) {
            $this->form_validation->set_rules(
                'amount', $this->lang->line('amount'), array('trim', 'required', 'xss_clean', 'valid_amount',
                    array('check_exists', array($this->radio_model, 'validate_paymentamount')),
                )
            );

            if ($this->input->post("payment_mode") == "Cheque") {
                $this->form_validation->set_rules('cheque_no', $this->lang->line('cheque_no'), 'trim|required');
                $this->form_validation->set_rules('cheque_date', $this->lang->line('cheque_date'), 'trim|required');
                $this->form_validation->set_rules('document', $this->lang->line("document"), 'callback_handle_doc_upload[document]|callback_validateCanUploadFile[document]');
            }
        }

        $this->form_validation->set_rules('date', $this->lang->line('date'), 'trim|required|xss_clean');

        $total_rows = $this->input->post('total_rows');
        if (!isset($total_rows) && !isset($radiology) && !isset($radiology)) {
            $this->form_validation->set_rules('no_records', $this->lang->line('no_records'), 'trim|required|xss_clean',
                array('required' => $this->lang->line('no_test_selected')));
        }
        $check_duplicate_test = array();
        if (isset($total_rows) && !empty($total_rows)) {
            foreach ($total_rows as $row_key => $row_value) {

                $test_name              = $this->input->post('test_name_' . $row_value);
                $reportdate             = $this->input->post('reportdate_' . $row_value);
                $check_duplicate_test[] = $test_name;
                if ($test_name == "") {
                    $this->form_validation->set_rules('test_name', $this->lang->line('test_name'), 'trim|required|xss_clean');
                }
                if ($reportdate == "") {
                    $this->form_validation->set_rules('reportdate', $this->lang->line('report_date'), 'trim|required|xss_clean');
                }
            }
        }

        if (!empty($check_duplicate_test)) {
            if (has_duplicate_array($check_duplicate_test)) {
                $this->form_validation->set_rules('duplicate_test', ' ', 'trim|required|xss_clean',
                    array('required' => $this->lang->line('duplicate_test_name_found')));
            }
        }

        if ($this->form_validation->run() == false) {
            $msg = array(
                'no_records'     => form_error('no_records'),
                'patientid'      => form_error('patientid'),
                'test_name'      => form_error('test_name'),
                'reportdate'     => form_error('reportdate'),
                'date'           => form_error('date'),
                'net_amount'     => form_error('net_amount'),
                'total'          => form_error('total'),
                'discount'       => form_error('discount'),
                'amount'         => form_error('amount'),
                'duplicate_test' => form_error('duplicate_test'),
                'tax'            => form_error('tax'),
            );

            if (empty($radiology_billing_id)) {
                if ($this->input->post("payment_mode") == "Cheque") {
                    $msg['cheque_no']   = form_error('cheque_no');
                    $msg['cheque_date'] = form_error('cheque_date');
                    $msg['document']    = form_error('document');
                }
            }
			
            if (!empty($custom_fields)) {
                foreach ($custom_fields as $custom_fields_key => $custom_fields_value) {
                    if ($custom_fields_value['validation']) {
                        $custom_fields_id                                                  = $custom_fields_value['id'];
                        $custom_fields_name                                                = $custom_fields_value['name'];
                        $error_msg2["custom_fields[radiology][" . $custom_fields_id . "]"] = form_error("custom_fields[radiology][" . $custom_fields_id . "]");
                    }
                }
            }
			
            if (!empty($error_msg2)) {
                $error_msg = array_merge($msg, $error_msg2);
            } else {
                $error_msg = $msg;
            }
			
            $array = array('status' => 'fail', 'error' => $error_msg, 'message' => '');

        } else {

            $patient_id        = $this->input->post('patientid', TRUE);
            $bill_date         = $this->customlib->dateFormatToYYYYMMDDHis($this->input->post('date', TRUE));
            $doctor_name       = $this->input->post('doctor_name', TRUE);
            $doctor_id         = $this->input->post('consultant_doctor', TRUE);
            $case_reference_id = $this->input->post('case_reference_id', TRUE);

            if (empty($case_reference_id)) {
                $case_reference_id = null;
            }

            if (empty($doctor_id)) {
                $doctor_id = null;
            }

            if ($prescription_no != "") {
                $prescription_prefix = splitPrefixType($prescription_no);
                $prescription_no     = splitPrefixID($prescription_no);
            } else {
                $prescription_no = null;
            }

            $is_tpa = $this->input->post('is_tpa', TRUE);
            if($is_tpa=="" || $is_tpa==0){
                $organisation_id    = null;
                $insurance_id       = null;
                $insurance_validity = null;
            }else{
                $organisation_id    = $this->input->post('organisation_id', TRUE);
                $insurance_id       = $this->input->post('insurance_id', TRUE);
                $insurance_validity = $this->customlib->dateFormatToYYYYMMDD($this->input->post('insurance_validity', TRUE));
            }
            if (empty($organisation_id)) {
                $organisation_id = null;
            }

            $data = array(
                'date'                => ($bill_date),
                'patient_id'          => $patient_id,
                'doctor_name'         => $doctor_name,
                'doctor_id'           => $doctor_id,
                'case_reference_id'   => $case_reference_id,
                'ipd_prescription_basic_id' => $prescription_no,
                'total'               => $this->input->post('total', TRUE),
                'discount'            => $this->input->post('discount', TRUE),
                'discount_percentage' => $this->input->post('discount_percent', TRUE),
                'tax'                 => $this->input->post('tax', TRUE),
                'net_amount'          => $this->input->post('net_amount', TRUE),
                'note'                => $this->input->post('note', TRUE),
                'generated_by'        => $this->customlib->getLoggedInUserID(),
                'organisation_id'     => $organisation_id,
                'insurance_id'        => $insurance_id,
                'insurance_validity'  => $insurance_validity,
            );
			
            $custom_field_post  = $this->input->post("custom_fields[radiology]", TRUE);
            $custom_value_array = array();
            if (!empty($custom_field_post)) {
                foreach ($custom_field_post as $key => $value) {
                    $check_field_type = $this->input->post("custom_fields[radiology][" . $key . "]", TRUE);
                    $field_value      = is_array($check_field_type) ? implode(",", $check_field_type) : $check_field_type;
                    $array_custom     = array(
                        'belong_table_id' => 0,
                        'custom_field_id' => $key,
                        'field_value'     => $field_value,
                    );
                    $custom_value_array[] = $array_custom;
                }
            }
			
            if ($radiology_billing_id > 0) {
                $data['id'] = $radiology_billing_id;
            }

            $total_rows   = $this->input->post('total_rows', TRUE);
            $prev_reports = $this->input->post('prev_reports', TRUE);

            $insert_array              = array();
            $update_array              = array();
            $prev_reports_array        = array();
            $prev_reports_update_array = array();
            if (isset($prev_reports)) {
                $prev_reports_array = $prev_reports;
            }

            foreach ($total_rows as $row_key => $row_value) {
                $test_report_id = $this->input->post('inserted_id_' . $row_value);
                if ($test_report_id == 0) {
                    $report = array(
                        'radiology_bill_id' => 0,
                        'radiology_id'      => $this->input->post('test_name_' . $row_value),
                        'reporting_date'    => $this->customlib->dateFormatToYYYYMMDD($this->input->post('reportdate_' . $row_value)),
                        'patient_id'        => $patient_id,
                        'apply_charge'      => $this->input->post('amount_' . $row_value),
                        'tax_percentage'    => $this->input->post('taxpercent_' . $row_value),
                    );
                    $insert_array[] = $report;
                } else if ($test_report_id > 0) {
                    $report = array(
                        'id'             => $test_report_id,
                        'radiology_id'   => $this->input->post('test_name_' . $row_value),
                        'reporting_date' => $this->customlib->dateFormatToYYYYMMDD($this->input->post('reportdate_' . $row_value)),
                        'patient_id'     => $patient_id,
                        'apply_charge'   => $this->input->post('amount_' . $row_value),
                        'tax_percentage' => $this->input->post('taxpercent_' . $row_value),
                    );
                    $prev_reports_update_array[] = $test_report_id;
                    $update_array[]              = $report;
                }
            }

            if (empty($radiology_billing_id)) {
                $cheque_date      = $this->customlib->dateFormatToYYYYMMDD($this->input->post("cheque_date"));
                $payment_section  = $this->config->item('payment_section');
                $transaction_data = array(
                    'patient_id'        => $patient_id,
                    'case_reference_id' => $case_reference_id,
                    'section'           => $payment_section['radiology'],
                    'amount'            => $this->input->post('amount'),
                    'type'              => 'payment',
                    'ipd_id'            => $this->input->post('ipdid'),
                    'payment_mode'      => $this->input->post('payment_mode'),
                    'note'              => $this->input->post('note'),
                    'payment_date'      => $bill_date,
                    'received_by'       => $this->customlib->getLoggedInUserID(),
                );

                if ($this->input->post('payment_mode') == "Cheque") {
                    $transaction_data['cheque_date']     = $cheque_date;
                    $transaction_data['cheque_no']       = $this->input->post('cheque_no');
                    if (isset($_FILES["document"]) && !empty($_FILES['document']['name'])) {
                        $file_name = $this->media_storage->fileupload("document",'./uploads/payment_document/');
                        $transaction_data['attachment']      = $file_name;
                        $transaction_data['attachment_name'] = $_FILES["document"]["name"];

                        // SaaS: add the uploaded document's size to the storage quota usage.
                        try {
                            $this->saasvalidation->updateStorageLimit('storage', ['document']);
                        } catch (Exception $e) {
                            log_message('error', 'SaaS storage quota update failed (bill add_radiology_bill): ' . $e->getMessage());
                        }
                    }   
                }
            }
			
            $array_delete = array_diff($prev_reports_array, $prev_reports_update_array);
            $inserted = $this->radio_model->addBill($data, $insert_array, $update_array, $array_delete, $radiology_billing_id, $transaction_data);

            if ($radiology_billing_id > 0) {
                if (!empty($custom_fields)) {
                    foreach ($custom_field_post as $key => $value) {
                        $check_field_type = $this->input->post("custom_fields[radiology][" . $key . "]");
                        $field_value      = is_array($check_field_type) ? implode(",", $check_field_type) : $check_field_type;
                        $array_custom     = array(
                            'belong_table_id' => $inserted,
                            'custom_field_id' => $key,
                            'field_value'     => $field_value,
                        );
                        $custom_value_array[] = $array_custom;
                    }
                    $this->customfield_model->updateRecord($custom_value_array, $inserted, 'radiology');
                }
            } else {
                if (!empty($custom_value_array)) {
                    $this->customfield_model->insertRecord($custom_value_array, $inserted);
                }
            }
            if ($inserted) {

                $patient_name   = $this->notificationsetting_model->getpatientDetails($patient_id);
                $doctor_details = $this->notificationsetting_model->getstaffDetails($doctor_id);
				if(!empty($doctor_details)){
					$doctor_name	=	composeStaffNameByString($doctor_details['name'], $doctor_details['surname'], $doctor_details['employee_id']);
				}else{
					$doctor_name	= '';
				}
                $event_data = array(
                    'patient_id'  => $patient_id,
                    'case_id'     => $case_reference_id,
                    'bill_no'     => $this->input->post('doctorid'),
                    'date'        => $this->customlib->YYYYMMDDTodateFormat($bill_date),
                    'doctor_id'   => $doctor_id,
                    'doctor_name' => $doctor_name,
                    'total'       => $this->input->post('total'),
                    'discount'    => number_format((float) $this->input->post('discount'), 2, '.', ''),
                    'tax'         => number_format((float) $this->input->post('tax'), 2, '.', ''),
                    'net_amount'  => $this->input->post('net_amount'),
                    'paid'        => $this->input->post('amount'),
                );

                $this->system_notification->send_system_notification('radiology_investigation', $event_data);
                $array = array('status' => 'success', 'error' => '', 'message' => $this->lang->line('success_message'), 'insert_id' => $inserted);
            } else {
                $array = array('status' => 'success', 'error' => '', 'message' => $this->lang->line('something_went_wrong'));
            }
        }
        echo json_encode($array);
    }
    //============end radiology=============================
	
    //==================opd========================
    public function opd()
    {
        if (!$this->rbac->hasPrivilege('opd_billing', 'can_view')) {
            access_denied();
        }

        $opd_data         = $this->session->flashdata('opd_data');
        $data['opd_data'] = $opd_data;
        $data["title"]    = $this->lang->line('opd_patient');
        $this->session->set_userdata('top_menu', 'bill');
        $setting                    = $this->setting_model->get();
        $data['setting']            = $setting;
        $opd_month                  = $setting[0]['opd_record_month'];
        $data["marital_status"]     = $this->marital_status;
        $data["payment_mode"]       = $this->payment_mode;
        $data["yesno_condition"]    = $this->yesno_condition;
        $data["bloodgroup"]         = $this->bloodbankstatus_model->get_product(null, 1);
        $doctors                    = $this->staff_model->getStaffbyrole(3);
        $data["doctors"]            = $doctors;
        $patients                   = $this->patient_model->getPatientListall();
        $data["patients"]           = $patients;
        $userdata                   = $this->customlib->getUserData();
        $role_id                    = $userdata['role_id'];
        $symptoms_result            = $this->symptoms_model->get();
        $data['symptomsresult']     = $symptoms_result;
        $symptoms_resulttype        = $this->symptoms_model->getsymtype();
        $data['symptomsresulttype'] = $symptoms_resulttype;
        $charge_category            = $this->charge_category_model->getCategoryByModule("opd");
        $data['charge_category']    = $charge_category;
        $doctorid                   = "";
        $doctor_restriction         = $this->session->userdata['hospitaladmin']['doctor_restriction'];
        $disable_option             = false;
        if ($doctor_restriction == 'enabled') {
            if ($role_id == 3) {
                $disable_option = true;
                $doctorid       = $userdata['id'];
            }
        }

        $data['fields']         = $this->customfield_model->get_custom_fields('opd', 1);
        $data["doctor_select"]  = $doctorid;
        $data["disable_option"] = $disable_option;
        $data['organisation']   = $this->organisation_model->get();
        $data['module'] = 'opd';
        $this->load->view('layout/header', $data);
        $this->load->view('admin/bill/opd/opd', $data);
        $this->load->view('layout/footer', $data);
    }

    public function add_opd(){      

        $patient_type   = $this->customlib->getPatienttype();
        //Validation for custom fields
        $custom_fields  = $this->customfield_model->getByBelong('opd');
        foreach ($custom_fields as $custom_fields_key => $custom_fields_value) {
            if ($custom_fields_value['validation']) {
                $custom_fields_id   = $custom_fields_value['id'];
                $custom_fields_name = $custom_fields_value['name'];
                $this->form_validation->set_rules("custom_fields[opd][" . $custom_fields_id . "]", $custom_fields_name, 'trim|required');
            }
        }
        //Validation for custom fields

        $this->form_validation->set_rules('appointment_date', $this->lang->line('appointment_date'), 'trim|required|xss_clean');
        $this->form_validation->set_rules('consultant_doctor', $this->lang->line('consultant_doctor'), 'trim|required|xss_clean');
        $this->form_validation->set_rules('patient_id', $this->lang->line('patient_id'), 'trim|required|xss_clean');
        $this->form_validation->set_rules('amount', $this->lang->line('applied_charge'), 'trim|required|xss_clean');
        $this->form_validation->set_rules('charge_id', $this->lang->line('charge'), 'trim|required|xss_clean');
        $this->form_validation->set_rules('paid_amount', $this->lang->line('paid_amount'), 'trim|required|valid_amount|xss_clean');
        $this->form_validation->set_rules('discount_percentage', $this->lang->line('discount'), 'trim|required|valid_amount|xss_clean');

        $payment_mode = $this->input->post('payment_mode');
        if ($payment_mode == 'Cheque') {
            $this->form_validation->set_rules('cheque_no', $this->lang->line('cheque_no'), 'trim|required|xss_clean');
            $this->form_validation->set_rules('cheque_date', $this->lang->line('cheque_date'), 'trim|required|xss_clean');
            $this->form_validation->set_rules('document', $this->lang->line('document'), 'callback_handle_doc_upload[document]|callback_validateCanUploadFile[document]');
        }
		
		$consult      = $this->input->post('live_consult');
		if($consult == 'yes'){
			if (!$this->session->has_userdata('zoom_access_token')) {
				$this->form_validation->set_rules('zoom_live', $this->lang->line('zoom_access_token_is_invalid_or_not_generated_from_zoom_setting'), 'trim|required|xss_clean', array('required' => $this->lang->line('zoom_access_token_is_invalid_or_not_generated_from_zoom_setting')));
			}
		}
		
        if ($this->form_validation->run() == false) {
            $msg = array(
                'appointment_date'    => form_error('appointment_date'),
                'consultant_doctor'   => form_error('consultant_doctor'),
                'patient_id'          => form_error('patient_id'),
                'amount'              => form_error('amount'),
                'charge_id'           => form_error('charge_id'),
                'paid_amount'         => form_error('paid_amount'),
                'cheque_no'           => form_error('cheque_no'),
                'cheque_date'         => form_error('cheque_date'),
                'document'            => form_error('document'),
                'discount_percentage' => form_error('discount_percentage'),
                'zoom_live' 		  => form_error('zoom_live'),
            );

            $array = array('status' => 'fail', 'error' => $msg, 'message' => '');
        } else {

            $payment_section  = $this->config->item('payment_section');
            $doctor_id        = $this->input->post('consultant_doctor');
            $patient_id       = $this->input->post('patient_id');
            $password         = $this->input->post('password');
            $email            = $this->input->post('email');
            $mobileno         = $this->input->post('mobileno');
            $patient_name     = $this->input->post('patient_name');
            $appointment_date = $this->input->post('appointment_date');
            $isopd            = $this->input->post('is_opd');
            $appointmentid    = $this->input->post('appointment_id');
            $live_consult     = $this->input->post('live_consult');
            $date             = $this->customlib->dateFormatToYYYYMMDDHis($appointment_date, $this->time_format);
            $opd_data = array(
                'patient_id'   => $patient_id,
                'generated_by' => $this->customlib->getStaffID(),
            );

            $custom_field_post  = $this->input->post("custom_fields[opd]");
            $custom_value_array = array();
            if (!empty($custom_field_post)) {
                foreach ($custom_field_post as $key => $value) {
                    $check_field_type = $this->input->post("custom_fields[opd][" . $key . "]");
                    $field_value      = is_array($check_field_type) ? implode(",", $check_field_type) : $check_field_type;
                    $array_custom     = array(
                        'belong_table_id' => 0,
                        'custom_field_id' => $key,
                        'field_value'     => $field_value,
                    );
                    $custom_value_array[] = $array_custom;
                }
            }

            $this->opd_prefix = $this->customlib->getSessionPrefixByType('opd_no');
            $transaction_data = array(
                'case_reference_id' => 0,
                'opd_id'            => 0,
                'patient_id'        => $this->input->post('patient_id'),
                'amount'            => $this->input->post('paid_amount'),
                'type'              => 'payment',
                'section'           => $payment_section['opd'],
                'payment_mode'      => $this->input->post('payment_mode'),
                'note'              => $this->input->post('note'),
                'payment_date'      => $date,
                'received_by'       => $this->customlib->getLoggedInUserID(),
            );

            $cheque_date = $this->input->post("cheque_date");
            if ($this->input->post('payment_mode') == "Cheque") {
                $transaction_data['cheque_date']     = $this->customlib->dateFormatToYYYYMMDD($cheque_date);
                $transaction_data['cheque_no']       = $this->input->post('cheque_no');
                if (isset($_FILES["document"]) && !empty($_FILES['document']['name'])) {
                    $file_name = $this->media_storage->fileupload("document",'./uploads/payment_document/');
                    $transaction_data['attachment']      = $file_name;
                    $transaction_data['attachment_name'] = $_FILES["document"]["name"];

                    // SaaS: add the uploaded document's size to the storage quota usage.
                    try {
                        $this->saasvalidation->updateStorageLimit('storage', ['document']);
                    } catch (Exception $e) {
                        log_message('error', 'SaaS storage quota update failed (bill payment_attachment): ' . $e->getMessage());
                    }
                }   
            }
			
            $staff_data = $this->staff_model->getStaffByID($doctor_id);
            $staff_name = composeStaffName($staff_data);

            $is_tpa = $this->input->post('is_tpa');
            if($is_tpa=="" || $is_tpa==0){
                $organisation_id = null;
                $insurance_validity = null;
                $insurance_id = null;
            }else{           
                $organisation_id    = $this->input->post('organisation_id');
                $insurance_id       = $this->input->post('insurance_id');
                $insurance_validity = $this->customlib->dateFormatToYYYYMMDD($this->input->post('insurance_validity'));
            }
			
            if (empty($organisation_id)) {
                $organisation_id = null;
            }

            $charge     = array(
                'opd_id'                          => 0,
                'date'                            => $date,
                'charge_id'                       => $this->input->post('charge_id'),
                'qty'                             => 1,
                'apply_charge'                    => $this->input->post('amount'),
                'standard_charge'                 => $this->input->post('standard_charge'),
                'tpa_charge'                      => $this->input->post('schedule_charge'),
                'amount'                          => $this->input->post('apply_amount'),
                'created_at'                      => date('Y-m-d'),
                'note'                            => $staff_name,
                'tax'                             => $this->input->post('percentage'),
                'discount_percentage'             => $this->input->post('discount_percentage'),
                'organisation_id'                 => $organisation_id,
                'insurance_validity'              => $insurance_validity,
                'insurance_id'                    => $insurance_id,
            );            

            $opd_visit_data = array(
                'appointment_date'  => $date,
                'opd_details_id'    => 0,              
                'symptoms'          => $this->input->post('symptoms'),
                'refference'        => $this->input->post('refference'),
                'cons_doctor'       => $this->input->post('consultant_doctor'),
                'casualty'          => $this->input->post('casualty'),
                'case_type'         => $this->input->post('case'),
                'payment_mode'      => $this->input->post('payment_mode'),
                'note'              => $this->input->post('note'),
                'live_consult'      => $live_consult,
                'generated_by'      => $this->customlib->getLoggedInUserID(),
                'patient_charge_id' => 0,
                'transaction_id'    => 0,
                'can_delete'        => 'no',
                'known_allergies'   => $this->input->post('known_allergies'),
            );

            if($this->input->post('is_for_antenatal')!=""){
                $opd_visit_data['is_antenatal'] = $this->input->post('is_for_antenatal');
            }

            $patient_data = array('is_antenatal' => $this->input->post('is_for_antenatal'),'id'=> $patient_id);
            $this->patient_model->add($patient_data);       
 
            if ($this->input->post('symptoms_type') != "") {
                $opd_visit_data['symptoms_type'] = $this->input->post('symptoms_type');
            }

            $opdn_id          = $this->patient_model->add_opd($opd_data, $transaction_data, $charge, $opd_visit_data);
            $icd_ids = $this->input->post('icd_code_ids', TRUE);
            if (!empty($icd_ids) && is_array($icd_ids)) {
                $this->load->model('icd10_model');
                $this->icd10_model->saveOpdCodes($opdn_id, $icd_ids);
            }
            $visit_details_id = $this->patient_model->getvisitminid($opdn_id);
            $notificationurl  = $this->notificationurl;
            $url_link         = $notificationurl["opd"];
            $setting_result   = $this->setting_model->getzoomsetting();
            $opdduration      = $setting_result->opd_duration;

            if (!empty($custom_value_array)) {
                $this->customfield_model->insertRecord($custom_value_array, $opdn_id);
            }
            if ($live_consult == 'yes') {
                $api_type = 'global';
                $params   = array(
                    'zoom_api_key'    => "",
                    'zoom_api_secret' => "",
                );

                $title = 'Online consult for ' . $this->customlib->getSessionPrefixByType('opd_no') . $opdn_id . " Checkup ID " . $visit_details_id['visitid'];
                $this->load->library('zoom_api', $params);
                $insert_array = array(
                    'staff_id'         => $doctor_id,
                    'patient_id'       => $patient_id,
                    'visit_details_id' => $visit_details_id['visitid'],
                    'title'            => $title,
                    'date'             => $date,
                    'duration'         => $opdduration,
                    'created_id'       => $this->customlib->getStaffID(),
                    'password'         => $password,
                    'api_type'         => $api_type,
                    'host_video'       => 1,
                    'client_video'     => 1,
                    'purpose'          => 'consult',
                    'timezone'         => $this->customlib->getTimeZone(),
                );

                $response = $this->zoom_api->createAMeeting($insert_array);			 
				
                if ($response['status']) {
					 
                    if (isset($response['data'])) {
                        $insert_array['return_response'] = json_encode($response['data']);
                        $conferenceid                    = $this->conference_model->add($insert_array);

                        $sender_details = array('patient_id' => $patient_id, 'conference_id' => $conferenceid, 'contact_no' => $mobileno, 'email' => $email);
                        $this->mailsmsconf->mailsms('live_consult', $sender_details);
                    }
                }
            }

            $url   = base_url() . $url_link . '/' . $patient_id . '/' . $opdn_id;
            $array = array('status' => 'success', 'error' => '', 'message' => $this->lang->line('success_message'), 'id' => $patient_id, 'opd_id' => $opdn_id);

            if ($this->session->has_userdata("appointment_id")) {
                $appointment_id = $this->session->userdata("appointment_id");
                $updateData     = array('id' => $appointment_id, 'is_opd' => 'yes');
                $this->appointment_model->update($updateData);
                $this->session->unset_userdata('appointment_id');
            }

            $doctor_details = $this->notificationsetting_model->getstaffDetails($this->input->post('consultant_doctor'));
            $event_data     = array(
                'patient_id'           => $patient_id,
                'symptoms_description' => $this->input->post('symptoms'),
                'any_known_allergies'  => $this->input->post('known_allergies'),
                'appointment_date'     => $this->customlib->YYYYMMDDHisTodateFormat($date, $this->time_format),
                'doctor_id'            => $this->input->post('consultant_doctor'),
                'doctor_name'          => composeStaffNameByString($doctor_details['name'], $doctor_details['surname'], $doctor_details['employee_id']),
            );

            $this->system_notification->send_system_notification('opd_visit_created', $event_data);

            if (isset($_FILES["file"]) && !empty($_FILES['file']['name'])) {
                $fileInfo = pathinfo($_FILES["file"]["name"]);
                $img_name = $patient_id . '.' . $fileInfo['extension'];
                if (move_uploaded_file($_FILES["file"]["tmp_name"], "./uploads/patient_images/" . $img_name)) {
                    $data_img = array('id' => $patient_id, 'image' => 'uploads/patient_images/' . $img_name);
                    $this->patient_model->add($data_img);

                    // SaaS: add the uploaded patient image size to the storage quota usage.
                    try {
                        $this->saasvalidation->updateStorageLimit('storage', ['file']);
                    } catch (Exception $e) {
                        log_message('error', 'SaaS storage quota update failed (bill add_opd): ' . $e->getMessage());
                    }
                }
            }

            $sender_details = array('patient_id' => $patient_id, 'patient_name' => $patient_name, 'opd_details_id' => $opdn_id, 'contact_no' => $mobileno, 'email' => $email, 'appointment_date' => $appointment_date);
            $result         = $this->mailsmsconf->mailsms('opd_patient_registration', $sender_details);

        }
        echo json_encode($array);
    }

    public function getopddatatable($type)
    {
        $show_antenatal = $this->rbac->hasPrivilege('antenatal', 'can_view');
        $dt_response = $this->patient_model->getallopdvisits($type, $show_antenatal);
        $fields      = $this->customfield_model->get_custom_fields('opd', 1);
        $dt_response = json_decode($dt_response);
        $dt_data     = array();
        if (!empty($dt_response->data)) {
            foreach ($dt_response->data as $key => $value) {

                $row = array();
                //====================================
                $action = "<div class='rowoptionview rowview-mt-19'>";
                $action .= "<a href=" . base_url() . 'admin/bill/patient_profile/' . $value->pid . " class='btn btn-default btn-xs'  data-bs-toggle='tooltip' title='" . $this->lang->line('show') . "'><i class='fa fa-reorder' aria-hidden='true'></i></a>";
                $action .= "</div'>";
                $first_action = "<a href=" . base_url() . 'admin/bill/patient_profile/' . $value->pid . ">";
				
				if ($value->gender) {
                    $gender = $this->lang->line(strtolower($value->gender));
                } else {
                    $gender = '';
                }
				
                //==============================
                $row[] = $first_action . $value->patient_name . "</a>";
                $row[] = $value->patientid;
                $row[] = $value->guardian_name;
                $row[] = $gender;
                $row[] = $value->mobileno;
                $row[] = composeStaffNameByString($value->generated_byname, $value->generated_bysurname, $value->generated_byemployee_id);
                $row[] = composeStaffNameByString($value->name, $value->surname, $value->employee_id);
                $row[] = $this->customlib->YYYYMMDDHisTodateFormat($value->last_visit, $this->time_format);

                //====================
                if (!empty($fields)) {
                    foreach ($fields as $fields_key => $fields_value) {

                        $display_field = $value->{"$fields_value->name"};
                        if ($fields_value->type == "link") {
                            $display_field = "<a href=" . $value->{"$fields_value->name"} . " target='_blank'>" . $value->{"$fields_value->name"} . "</a>";
                        }
                        $row[] = $display_field;
                    }
                }
                //====================
                $row[]  =  $value->total_visit;
				
				if ($this->rbac->hasPrivilege('antenatal', 'can_view')) {
                $row[]  =  $value->is_antenatal==1 ?  "Yes" :  "No";
				}

                $row[] = $action;
                $dt_data[] = $row;
            }
        }
        $json_data = array(
            "draw"            => intval($dt_response->draw),
            "recordsTotal"    => intval($dt_response->recordsTotal),
            "recordsFiltered" => intval($dt_response->recordsFiltered),
            "data"            => $dt_data,
        );
        echo json_encode($json_data);
    }

    public function getopdvisitdatatable()
    {
        $patientid   = $this->uri->segment(4);
        $dt_response = $this->patient_model->getAllopdvisitRecord($patientid);
        $fields      = $this->customfield_model->get_custom_fields('opd', 1);
        $dt_response = json_decode($dt_response);
        $dt_data     = array();
        if (!empty($dt_response->data)) {
            foreach ($dt_response->data as $key => $value) {
                $row = array();
                //====================================
                $opd_id           = $value->opd_id;
                $visit_details_id = $value->visit_id;
                $check            = $this->db->where("visit_details_id", $visit_details_id)->get('ipd_prescription_basic');
                if ($check->num_rows() > 0) {
                    $result[$key]['prescription'] = 'yes';
                } else {
                    $result[$key]['prescription'] = 'no';
                    $userdata                     = $this->customlib->getUserData();
                    if ($this->session->has_userdata('hospitaladmin')) {
                        $doctor_restriction = $this->session->userdata['hospitaladmin']['doctor_restriction'];
                        if ($doctor_restriction == 'enabled') {
                            if ($userdata["role_id"] == 3) {
                                if ($userdata["id"] == $value->staff_id) {

                                } else {
                                    $result[$key]['prescription'] = 'not_applicable';
                                }
                            }
                        }
                    }
                }

                $action = "<div class='d-inline-flex gap-1'>";
                if ($this->rbac->hasPrivilege('opd_print_bill', 'can_view')) {
                    $action .= "<a href='javascript:void(0)' data-loading-text='<i class=\"fa fa-circle-o-notch fa-spin\"></i>' data-opd-id=" . $opd_id . " data-record-id=" . $visit_details_id . " class='btn btn-sm btn-outline-secondary print_visit_bill'  data-bs-toggle='tooltip' title='" . $this->lang->line('print_bill') . "'><i class='fa fa-print'></i></a>";
                }

                $action .= "<a href='javascript:void(0)' data-loading-text='" . $this->lang->line('please_wait') . "' data-opd-id=" . $opd_id . " data-record-id=" . $visit_details_id . " class='btn btn-sm btn-outline-secondary get_opd_detail'  data-bs-toggle='tooltip' title='" . $this->lang->line('show') . "'><i class='fa fa-reorder'></i></a>";

                $action .= "</div>";
                $first_action = "<a href=" . base_url() . 'admin/bill/opd_visit_detail/' . $value->pid . '/' . $opd_id . ">";
                    
                if($value->symptoms){
                    $symptoms   = nl2br($value->symptoms);
                }else{
                    $symptoms   = '';
                } 
                //==============================
                $row[] = $first_action . $this->opd_prefix . $opd_id . "</a>";
                $row[] = $value->case_reference_id;
                $row[] = $this->customlib->YYYYMMDDHisTodateFormat($value->appointment_date, $this->time_format);
                $row[] = composeStaffNameByString($value->name, $value->surname, $value->employee_id);
                $row[] = $value->refference;
                $row[] = $symptoms;
                $row[] = composeStaffNameByString($value->generated_byname, $value->generated_bysurname, $value->generated_byemployee_id);

                //====================
                if (!empty($fields)) {
                    foreach ($fields as $fields_key => $fields_value) {
                        $display_field = $value->{"$fields_value->name"};
                        if ($fields_value->type == "link") {
                            $display_field = "<a href=" . $value->{"$fields_value->name"} . " target='_blank'>" . $value->{"$fields_value->name"} . "</a>";

                        }
                        $row[] = $display_field;
                    }
                }
                //====================
                $row[]     = $action;
                $dt_data[] = $row;
            }
        }
        $json_data = array(
            "draw"            => intval($dt_response->draw),
            "recordsTotal"    => intval($dt_response->recordsTotal),
            "recordsFiltered" => intval($dt_response->recordsFiltered),
            "data"            => $dt_data,
        );
        echo json_encode($json_data);
    }

    public function patient_profile($id)
    {

        if (!$this->rbac->hasPrivilege('opd_patient', 'can_view')) {
            access_denied();
        }
        $this->session->set_userdata('top_menu','bill');
        $data["marital_status"]     = $this->marital_status;
        $data["payment_mode"]       = $this->payment_mode;
        $data["yesno_condition"]    = $this->yesno_condition;
        $data["bloodgroup"]         = $this->blood_group;
        $data['medicineCategory']   = $this->medicine_category_model->getMedicineCategory();
        $category_dosage            = $this->medicine_dosage_model->getCategoryDosages();
        $data['category_dosage']    = $category_dosage;
        $data['medicineName']       = $this->pharmacy_model->getMedicineName();
        $symptoms_resulttype        = $this->symptoms_model->getsymtype();
        $data['symptomsresulttype'] = $symptoms_resulttype;
        $pathology                  = $this->pathology_model->getpathologytest();
        $data['pathology']          = $pathology;
        $radiology                  = $this->radio_model->getradiologytest();
        $data['radiology']          = $radiology;
        $data["id"]                 = $id;
        $doctors                    = $this->staff_model->getStaffbyrole(3);
        $data["doctors"]            = $doctors;
        $userdata                   = $this->customlib->getUserData();
        $data['fields']             = $this->customfield_model->get_custom_fields('opd', 1);
        $role_id                    = $userdata['role_id'];
        $doctorid                   = "";
        $doctor_restriction         = $this->session->userdata['hospitaladmin']['doctor_restriction'];
        $disable_option             = false;
        if ($doctor_restriction == 'enabled') {
            if ($role_id == 3) {
                $disable_option = true;
                $doctorid       = $userdata['id'];
            }
        }
        $nurseid                = "";
        $data["doctor_select"]  = $doctorid;
        $data["nurse_select"]   = $nurseid;
        $data["disable_option"] = $disable_option;
        $data['roles']          = $this->role_model->get();
        $result                 = array();
        $diagnosis_details      = array();
        $opd_details            = array();

        $timeline_list = array();
        if (!empty($id)) {
            $result            = $this->patient_model->getpatientDetails($id);
            $opd_details_id    = $this->patient_model->getopdmaxid($id);

            $timeline_list     = $this->timeline_model->getPatientTimeline($id, $timeline_status = '');
        }
        $data["result"]           = $result;
        $data["opd_details_id"]   = $opd_details_id;
        $staff_id                = $this->customlib->getStaffID();
        $data['logged_staff_id'] = $staff_id;
        $data["opd_details"]     = $opd_details;
        $data["timeline_list"]   = $timeline_list;
        $data['organisation']    = $this->organisation_model->get();
        $orgid                   = "";
        $data['org_select']      = $orgid;
        $charge_category         = $this->charge_category_model->getCategoryByModule("opd");
        $data['charge_category'] = $charge_category;
        $data['intervaldosage']  = $this->medicine_dosage_model->getIntervalDosage();
        $data['durationdosage']  = $this->medicine_dosage_model->getDurationDosage();
        $data['investigations']  = $this->patient_model->allinvestigationbypatientid($id);
        $data['module'] = 'opd';
        $this->load->view("layout/header", $data);
        $this->load->view("admin/bill/opd/patient_profile", $data);
        $this->load->view("layout/footer", $data);
    }
 
    public function opd_visit_detail($id, $opdid)
    {
        if (!empty($id)) {
            $result         = $this->patient_model->getDetails($opdid);
            $data['result'] = $result;
            $data["id"]     = $id;
            $data["opdid"]  = $opdid;
            $visit_max_id   = $this->patient_model->getvisitmaxid($opdid);
            $data['visitdata']          = $visit_max_id;
            $symptoms_resulttype        = $this->symptoms_model->getsymtype();
            $data['symptomsresulttype'] = $symptoms_resulttype;
            $doctors                    = $this->staff_model->getStaffbyrole(3);
            $data["doctors"]            = $doctors;
            $pathology                  = $this->pathology_model->getpathologytest();
            $data['pathology']          = $pathology;
            $radiology                  = $this->radio_model->getradiologytest();
            $data['radiology']          = $radiology;
            $medicationreport           = $this->patient_model->getmedicationdetailsbydateopd($opdid);
            $max_dose                   = $this->patient_model->getMaxByopdid($opdid);
            $data['max_dose']           = $max_dose->max_dose;
            $data["medication"]         = $medicationreport;
            $userdata                   = $this->customlib->getUserData();
            $role_id                    = $userdata['role_id'];
            $category_dosage            = $this->medicine_dosage_model->getCategoryDosages();
            $data['category_dosage']    = $category_dosage;
            $doctorid                   = "";
            $doctor_restriction         = $this->session->userdata['hospitaladmin']['doctor_restriction'];
            $disable_option             = false;
            if ($doctor_restriction == 'enabled') {
                if ($role_id == 3) {
                    $disable_option = true;
                    $doctorid       = $userdata['id'];
                }
            }
            $staff_id                  = $this->customlib->getStaffID();
            $data['logged_staff_id']   = $staff_id;
            $data['organisation']      = $this->organisation_model->get();
            $orgid                     = '';
            $data['org_select']        = $orgid;
            $data["doctor_select"]     = $doctorid;
            $data["disable_option"]    = $disable_option;
            $data["payment_mode"]      = $this->payment_mode;
            $data["yesno_condition"]   = $this->yesno_condition;
            $data["charge_type"]       = $this->chargetype_model->getChargeTypeByModule("opd");
            $operation_theatre         = $this->operationtheatre_model->getopdoperationDetails($opdid);
            $timeline_list             = $this->timeline_model->getPatientTimeline($id, $timeline_status = '');
            $data["timeline_list"]     = $timeline_list;
            $data['operation_theatre'] = $operation_theatre;
            $data['medicineCategory']  = $this->medicine_category_model->getMedicineCategory();
            $data['intervaldosage']    = $this->medicine_dosage_model->getIntervalDosage();
            $data['durationdosage']    = $this->medicine_dosage_model->getDurationDosage();
            $data['dosage']            = $this->medicine_dosage_model->getMedicineDosage();
            $data['medicineName']      = $this->pharmacy_model->getMedicineName();
            $charges                   = $this->charge_model->getopdCharges($opdid);            
            $paymentDetails            = $this->transaction_model->OPDPatientPayments($opdid);
            $data["charges_detail"]    = $charges;
            $data["payment_details"]   = $paymentDetails;
            $data['roles']             = $this->role_model->get();
            $getVisitDetailsid         = $this->patient_model->getVisitDetailsid($opdid);
            $data['fields']            = $this->customfield_model->get_custom_fields('opdrecheckup', 1);
            $data['ot_fields']         = $this->customfield_model->get_custom_fields('operationtheatre', 1);
            $data['opd_prefix']        = $this->opd_prefix;
            $charge_category           = $this->charge_category_model->getCategoryByModule("opd");
            $data['charge_category']   = $charge_category;
            $data['categorylist']      = $this->operationtheatre_model->category_list();
            $data["opd_data"]       = $this->patient_model->getPatientVisitDetails($id);
            $data['investigations'] = $this->patient_model->getallinvestigation($result['case_reference_id']);
            $data["bloodgroup"]     = $this->bloodbankstatus_model->get_product(null, 1);
            $data["marital_status"] = $this->marital_status;
            $data['is_discharge']   = $this->customlib->checkDischargePatient($data["result"]['discharged']);
            $data['module'] = 'opd';
            $this->load->view("layout/header", $data);
            $this->load->view("admin/bill/opd/opd_visit_detail", $data);
            $this->load->view("layout/footer", $data);
        }
    }
    
    public function getvisitdatatable($opdid)
    {
        $dt_response = $this->patient_model->getAllvisitRecord($opdid);
        $fields      = $this->customfield_model->get_custom_fields('opdrecheckup', 1);
        $dt_response = json_decode($dt_response);
        $dt_data     = array();
        if (!empty($dt_response->data)) {
            foreach ($dt_response->data as $key => $value) {
                $row = array();
                //====================================
                $opd_id           = $value->opd_id;
                $visit_details_id = $value->visit_id;
                $check = $this->db->where("visit_details_id", $visit_details_id)->get('ipd_prescription_basic');
                if ($check->num_rows() > 0) {
                    $result[$key]['prescription'] = 'yes';
                } else {
                    $result[$key]['prescription'] = 'no';
                    $userdata                     = $this->customlib->getUserData();
                    if ($this->session->has_userdata('hospitaladmin')) {
                        $doctor_restriction = $this->session->userdata['hospitaladmin']['doctor_restriction'];
                        if ($doctor_restriction == 'enabled') {
                            if ($userdata["role_id"] == 3) {
                                if ($userdata["id"] == $value->staff_id) {

                                } else {
                                    $result[$key]['prescription'] = 'not_applicable';
                                }
                            }
                        }
                    }
                }

                $action = "<div class='d-inline-flex gap-1'>";

                $action .= "<a href='javascript:void(0)'  data-loading-text='" . $this->lang->line('please_wait') . "' data-record-id=" . $visit_details_id . " class='btn btn-sm btn-outline-secondary get_opd_detail'  data-bs-toggle='tooltip' title='" . $this->lang->line('show') . "'><i class='fa fa-reorder'></i></a>";

                $action .= "</div>";
                //=====================
                $row[] = $this->customlib->getSessionPrefixByType('checkup_id') . $visit_details_id  ;
                $row[] = $this->customlib->YYYYMMDDHisTodateFormat($value->appointment_date, $this->time_format);
                $row[] = composeStaffNameByString($value->name, $value->surname, $value->employee_id);
                $row[] = $value->refference;
                
                if($value->symptoms){
                    $row[] = nl2br($value->symptoms);
                }else{
                    $row[] ='';
                }
                //====================
                if (!empty($fields)) {
                    foreach ($fields as $fields_key => $fields_value) {
                        $display_field = $value->{"$fields_value->name"};
                        if ($fields_value->type == "link") {
                            $display_field = "<a href=" . $value->{"$fields_value->name"} . " target='_blank'>" . $value->{"$fields_value->name"} . "</a>";

                        }
                        $row[] = $display_field;
                    }
                }
                //====================
                $row[]     = $action;
                $dt_data[] = $row;
            }
        }
        $json_data = array(
            "draw"            => intval($dt_response->draw),
            "recordsTotal"    => intval($dt_response->recordsTotal),
            "recordsFiltered" => intval($dt_response->recordsFiltered),
            "data"            => $dt_data,
        );
        echo json_encode($json_data);
    }

    public function add_revisit()
    {      
        $custom_fields = $this->customfield_model->getByBelong('opdrecheckup');  
        foreach ($custom_fields as $custom_fields_key => $custom_fields_value) {
            if ($custom_fields_value['validation']) {
                $custom_fields_id   = $custom_fields_value['id'];
                $custom_fields_name = $custom_fields_value['name'];
                $this->form_validation->set_rules("custom_fields[opdrecheckup][" . $custom_fields_id . "]", $custom_fields_name, 'trim|required');
            }
        }

        $payment_mode = $this->input->post('payment_mode');

        if ($payment_mode == 'Cheque') {
            $this->form_validation->set_rules('cheque_no', $this->lang->line('cheque_no'), 'trim|required|xss_clean');
            $this->form_validation->set_rules('cheque_date', $this->lang->line('cheque_date'), 'trim|required|xss_clean');
            $this->form_validation->set_rules('document', $this->lang->line('document'), 'callback_handle_doc_upload[document]|callback_validateCanUploadFile[document]');
        }

        $this->form_validation->set_rules('amount', $this->lang->line('amount'), 'trim|required|xss_clean|valid_amount');
        $this->form_validation->set_rules('paid_amount', $this->lang->line('paid_amount'), 'trim|required|xss_clean|valid_amount');
        $this->form_validation->set_rules('charge_id', $this->lang->line('charge_id'), 'trim|required|xss_clean');
        $this->form_validation->set_rules('appointment_date', $this->lang->line('appointment_date'), 'trim|required|xss_clean');
        $this->form_validation->set_rules('consultant_doctor', $this->lang->line('consultant_doctor'), 'trim|required|xss_clean');
        $this->form_validation->set_rules('discount_percentage', $this->lang->line('discount_percentage'), 'trim|required|xss_clean');
        $this->form_validation->set_rules('document', $this->lang->line('document'), 'callback_handle_doc_upload[document]|callback_validateCanUploadFile[document]');	

		$consult      = $this->input->post('live_consult');
		if($consult == 'yes'){
			if (!$this->session->has_userdata('zoom_access_token')) {
				$this->form_validation->set_rules('zoom_live', $this->lang->line('zoom_access_token_is_invalid_or_not_generated_from_zoom_setting'), 'trim|required|xss_clean', array('required' => $this->lang->line('zoom_access_token_is_invalid_or_not_generated_from_zoom_setting')));
			}
		}
		
        if ($this->form_validation->run() == false) {
            $msg = array(
                'charge_id'         => form_error('charge_id'),
                'amount'            => form_error('amount'),
                'paid_amount'       => form_error('paid_amount'),
                'appointment_date'  => form_error('appointment_date'),
                'consultant_doctor' => form_error('consultant_doctor'),
                'cheque_no'         => form_error('cheque_no'),
                'cheque_date'       => form_error('cheque_date'),
                'document'          => form_error('document'),
                'discount_percentage'          => form_error('discount_percentage'),
                'zoom_live'         => form_error('zoom_live'),
            );
            $msg['cheque_no']       = form_error('cheque_no');
            $msg['cheque_date']     = form_error('cheque_date');
			
            //custom fields validation
            if (!empty($custom_fields)) {
                foreach ($custom_fields as $custom_fields_key => $custom_fields_value) {
                    if ($custom_fields_value['validation']) {
                        $custom_fields_id                                                     = $custom_fields_value['id'];
                        $custom_fields_name                                                   = $custom_fields_value['name'];
                        $error_msg2["custom_fields[opdrecheckup][" . $custom_fields_id . "]"] = form_error("custom_fields[opdrecheckup][" . $custom_fields_id . "]");
                    }
                }
            }

            if (!empty($error_msg2)) {
                $error_msg = array_merge($msg, $error_msg2);
            } else {
                $error_msg = $msg;
            }
            //custom fields validation

            $array = array('status' => 'fail', 'error' => $error_msg, 'message' => '');				
			
        } else {			 
				
            $check_patient_id = $this->patient_model->getMaxOPDId();
            if (empty($check_patient_id)) {
                $check_patient_id = 0;
            }
            $patient_id        = $this->input->post('patientid', TRUE);
            $password          = $this->input->post('password', TRUE);
            $email             = $this->input->post('email', TRUE);
            $mobileno          = $this->input->post('mobileno', TRUE);
            $opdn_id           = $check_patient_id + 1;
            $custom_field_post = $this->input->post("custom_fields[opd]");
            $appointment_date  = $this->input->post('appointment_date', TRUE);
            $consult           = $this->input->post('live_consult', TRUE);
            if ($consult) {
                $live_consult = $this->input->post('live_consult', TRUE);
            } else {
                $live_consult = 'no';
            }
            $doctor_id = $this->input->post("consultant_doctor", TRUE);
            $date      = $this->customlib->dateFormatToYYYYMMDDHis($appointment_date, $this->time_format);
            $opd_data  = array(
                'patient_id'   => $patient_id,
                'generated_by' => $this->customlib->getLoggedInUserID(),
            );

            $custom_value_array = array();
            if (!empty($custom_field_post)) {
                foreach ($custom_field_post as $key => $value) {
                    $check_field_type = $this->input->post("custom_fields[opd][" . $key . "]", TRUE);
                    $field_value      = is_array($check_field_type) ? implode(",", $check_field_type) : $check_field_type;
                    $array_custom     = array(
                        'belong_table_id' => 0,
                        'custom_field_id' => $key,
                        'field_value'     => $field_value,
                    );
                    $custom_value_array[] = $array_custom;
                }
            }
            $payment_section = $this->config->item('payment_section');

            $attachment      = "";
            $attachment_name = "";
            if (isset($_FILES["document"]) && !empty($_FILES['document']['name'])) {
                $fileInfo        = pathinfo($_FILES["document"]["name"]);
                $attachment      = uniqueFileName() . '.' . $fileInfo['extension'];
                $attachment_name = $_FILES["document"]["name"];
                if (move_uploaded_file($_FILES["document"]["tmp_name"], "./uploads/payment_document/" . $attachment)) {
                    // SaaS: add the uploaded document's size to the storage quota usage.
                    try {
                        $this->saasvalidation->updateStorageLimit('storage', ['document']);
                    } catch (Exception $e) {
                        log_message('error', 'SaaS storage quota update failed (bill makepayment_or_revisit): ' . $e->getMessage());
                    }
                }
            }

            $cheque_date      = $this->input->post("cheque_date", TRUE);
            $transaction_data = array(
                'case_reference_id' => 0,
                'opd_id'            => 0,
                'amount'            => $this->input->post('paid_amount', TRUE),
                'type'              => 'payment',
                'payment_mode'      => $this->input->post('payment_mode', TRUE),
                'note'              => $this->input->post('note', TRUE),
                'payment_date'      => $date,
                'patient_id'        => $patient_id,
                'section'           => $payment_section['opd'],
                'received_by'       => $this->customlib->getLoggedInUserID(),
            );

            if ($this->input->post('payment_mode', TRUE) == "Cheque") {
                $transaction_data['cheque_date']     = $this->customlib->dateFormatToYYYYMMDD($cheque_date);
                $transaction_data['cheque_no']       = $this->input->post('cheque_no', TRUE);
                $transaction_data['attachment']      = $attachment;
                $transaction_data['attachment_name'] = $attachment_name;
            }

            $staff_data = $this->staff_model->getStaffByID($doctor_id);
            $staff_name = composeStaffName($staff_data);

            $is_tpa = $this->input->post('is_tpa', TRUE);
            if ($is_tpa == "" || $is_tpa == 0) {
                $organisation_id    = null;
                $insurance_validity = null;
                $insurance_id       = null;
            } else {
                $organisation_id    = $this->input->post('organisation', TRUE);
                $insurance_id       = $this->input->post('insurance_id', TRUE);
                $insurance_validity = $this->customlib->dateFormatToYYYYMMDD($this->input->post('insurance_validity', TRUE));
            }

            if (empty($organisation_id)) {
                $organisation_id = null;
            }

            $charge = array(
                'opd_id'              => 0,
                'date'                => $date,
                'charge_id'           => $this->input->post('charge_id', TRUE),
                'qty'                 => 1,
                'apply_charge'        => $this->input->post('amount', TRUE),
                'standard_charge'     => $this->input->post('standard_charge', TRUE),
                'tpa_charge'          => $this->input->post('schedule_charge', TRUE),
                'amount'              => $this->input->post('apply_amount', TRUE),
                'tax'                 => $this->input->post('percentage', TRUE),
                'discount_percentage' => $this->input->post('discount_percentage', TRUE),
                'created_at'          => date('Y-m-d'),
                'note'                => $staff_name,
                'organisation_id'     => $organisation_id,
                'insurance_validity'  => $insurance_validity,
                'insurance_id'        => $insurance_id,
            );

            $opd_visit_data = array(
                'appointment_date' => $this->customlib->dateFormatToYYYYMMDDHis($appointment_date, $this->time_format),
                'symptoms'         => $this->input->post('symptoms', TRUE),
                'known_allergies'  => $this->input->post('known_allergies', TRUE),
                'patient_old'      => $this->input->post('old_patient', TRUE),
                'refference'       => $this->input->post('refference', TRUE),
                'cons_doctor'      => $doctor_id,
                'symptoms_type'    => $this->input->post('symptoms_type', TRUE),
                'casualty'         => $this->input->post('casualty', TRUE),
                'payment_mode'     => $this->input->post('payment_mode', TRUE),
                'note'             => $this->input->post('note_remark', TRUE),
                'live_consult'     => $live_consult,
                'can_delete'       => 'no',
                'generated_by'     => $this->customlib->getLoggedInUserID(),
            );

            if ($this->input->post('is_for_antenatal', TRUE) != "") {
                $opd_visit_data['is_antenatal'] = $this->input->post('is_for_antenatal', TRUE);
            }

            $opdn_id         = $this->patient_model->add_opd($opd_data, $transaction_data, $charge, $opd_visit_data);
            $visit_max_id    = $this->patient_model->getvisitmaxid($opdn_id);
            $visitid         = $visit_max_id['visitid'];
            $notificationurl = $this->notificationurl;
            $url_link        = $notificationurl["opd"];
            $url             = base_url() . $url_link . '/' . $patient_id . '/' . $opdn_id;
            $setting_result  = $this->setting_model->getzoomsetting();
            $opdduration     = $setting_result->opd_duration;
            
            if ($live_consult != 'no') {
                $api_type = 'global';
                $params   = array(
                    'zoom_api_key'    => "",
                    'zoom_api_secret' => "",
                );
                $this->load->library('zoom_api', $params);
                $insert_array = array(
                    'staff_id'         => $doctor_id,
                    'visit_details_id' => $visitid,
                    'visit_details_id' => $visitid,
                    'title'            => 'Online consult for Revisit OPDN' . $opdn_id,
                    'date'             => $date,
                    'duration'         => $opdduration,
                    'created_id'       => $this->customlib->getStaffID(),
                    'password'         => $password,
                    'api_type'         => $api_type,
                    'host_video'       => 1,
                    'client_video'     => 1,
                    'purpose'          => 'consult',
                    'timezone'         => $this->customlib->getTimeZone(),
                );
                $response         = $this->zoom_api->createAMeeting($insert_array);
                $appointment_date = $this->customlib->dateFormatToYYYYMMDDHis($appointment_date, $this->time_format);
				
				if ($response['status']) {
					 
                    if (isset($response['data'])) {
                        $insert_array['return_response'] = json_encode($response['data']);
						 
                        $conferenceid   = $this->conference_model->add($insert_array);
                        $sender_details = array('patient_id' => $patient_id, 'conference_id' => $conferenceid, 'contact_no' => $mobileno, 'email' => $email);
                        $this->mailsmsconf->mailsms('live_consult', $sender_details);
						 
                    }
                }                 
            }
            
            $sender_details = array('patient_id' => $patient_id, 'opd_details_id' => $opdn_id, 'contact_no' => $mobileno, 'email' => $email, 'appointment_date' => $appointment_date);
            $this->mailsmsconf->mailsms('opd_patient_registration', $sender_details);
            $array = array('status' => 'success', 'error' => '', 'id' => $opdn_id, 'message' => $this->lang->line('success_message'));
        }		
	 
        echo json_encode($array);
    }

    public function addvisitDetails()
    {
        $custom_fields = $this->customfield_model->getByBelong('opdrecheckup'); 

        foreach ($custom_fields as $custom_fields_key => $custom_fields_value) {
            if ($custom_fields_value['validation']) {
                $custom_fields_id   = $custom_fields_value['id'];
                $custom_fields_name = $custom_fields_value['name'];
                $this->form_validation->set_rules("custom_fields[opdrecheckup][" . $custom_fields_id . "]", $custom_fields_name, 'trim|required');
            }
        }

        $this->form_validation->set_rules('charge_id', $this->lang->line('charge'), 'trim|required|xss_clean');
        $this->form_validation->set_rules('apply_amount', $this->lang->line('amount'), 'trim|required|xss_clean|valid_amount');
        $this->form_validation->set_rules('name', $this->lang->line('name'), 'trim|required|xss_clean');
        $this->form_validation->set_rules('amount', $this->lang->line('applied_charge'), 'trim|required|xss_clean|valid_amount');
        $this->form_validation->set_rules('appointment_date', $this->lang->line('appointment_date'), 'trim|required|xss_clean');
        $this->form_validation->set_rules('paid_amount', $this->lang->line('paid_amount'), 'trim|required|xss_clean|valid_amount');
		
		$consult      = $this->input->post('live_consult');
		if($consult == 'yes'){
			if (!$this->session->has_userdata('zoom_access_token')) {
				$this->form_validation->set_rules('zoom_live', $this->lang->line('zoom_access_token_is_invalid_or_not_generated_from_zoom_setting'), 'trim|required|xss_clean', array('required' => $this->lang->line('zoom_access_token_is_invalid_or_not_generated_from_zoom_setting')));
			}
		}
		
        $this->form_validation->set_rules('document', $this->lang->line('document'), 'callback_validateCanUploadFile[document]');

        if ($this->form_validation->run() == false) {
            $msg = array(
                'firstname'        => form_error('name'),
                'appointment_date' => form_error('appointment_date'),
                'amount'           => form_error('amount'),
                'charge_id'        => form_error('charge_id'),
                'apply_amount'     => form_error('apply_amount'),
                'paid_amount'      => form_error('paid_amount'),
                'zoom_live'        => form_error('zoom_live'),
                'document'         => form_error('document'),
            );

            $array = array('status' => 'fail', 'error' => $msg, 'message' => '');

        } else {
            $check_patient_id = $this->patient_model->getMaxOPDId();
            if (empty($check_patient_id)) {
                $check_patient_id = 0;
            }
            $opdn_id           = $check_patient_id + 1;
            $patient_id        = $this->input->post('id', TRUE);
            $password          = $this->input->post('password', TRUE);
            $custom_field_post = $this->input->post("custom_fields[opdrecheckup]");
            $appointment_date  = $this->input->post('appointment_date', TRUE);
            $consult           = $this->input->post('live_consult', TRUE);
            $doctor_id         = $this->input->post('consultant_doctor', TRUE);
            $opd_id            = $this->input->post('opd_id', TRUE);
            if ($consult) {
                $live_consult = $this->input->post('live_consult', TRUE);
            } else {
                $live_consult = "no";
            }

            $date            = $this->customlib->dateFormatToYYYYMMDDHis($appointment_date, $this->time_format);          
            $revisit_is_tpa = $this->input->post('revisit_is_tpa', TRUE);

            if ($revisit_is_tpa == "" || $revisit_is_tpa == 0) {
                $organisation_id    = null;
                $insurance_validity = null;
                $insurance_id       = null;
            } else {
                $organisation_id    = $this->input->post('organisation', TRUE);
                $insurance_validity = $this->input->post('insurance_validity', TRUE);
                $insurance_id       = $this->input->post('insurance_id', TRUE);
            }

            $opd_data = array(
                'appointment_date'  => $date,
                'opd_details_id'    => $opd_id,
                'case_type'         => $this->input->post('revisit_case', TRUE),
                'symptoms'          => $this->input->post('symptoms', TRUE),
                'known_allergies'   => $this->input->post('known_allergies', TRUE),
                'refference'        => $this->input->post('refference', TRUE),
                'cons_doctor'       => $doctor_id,
                'casualty'          => $this->input->post('casualty', TRUE),
                'payment_mode'      => $this->input->post('payment_mode', TRUE),
                'note'              => $this->input->post('note_remark', TRUE),
                'live_consult'      => $live_consult,
                'patient_charge_id' => null,
                'transaction_id'    => null,
                'can_delete'        => 'yes',
                'generated_by'      => $this->customlib->getLoggedInUserID(),
            );

            if ($this->input->post('is_for_antenatal', TRUE) != "") {
                $opd_data['is_antenatal'] = $this->input->post('is_for_antenatal', TRUE);
            }

            $payment_section  = $this->config->item('payment_section');
            $transaction_data = array(
                'case_reference_id' => $this->input->post('case_reference_id', TRUE),
                'opd_id'            => $opd_id,
                'amount'            => $this->input->post('paid_amount', TRUE),
                'type'              => 'payment',
                'payment_mode'      => $this->input->post('payment_mode', TRUE),
                'note'              => $this->input->post('note', TRUE),
                'patient_id'        => $patient_id,
                'section'           => $payment_section['opd'],
                'payment_date'      => $date,
                'cheque_date'       => $date,
                'cheque_no'         => $this->input->post('cheque_no', TRUE),
                'received_by'       => $this->customlib->getLoggedInUserID(),
            );

            if ($this->input->post('payment_mode', TRUE) == "Cheque") {
                $cheque_date                     = $this->customlib->dateFormatToYYYYMMDD($this->input->post("cheque_date", TRUE));
                $transaction_data['cheque_date'] = $cheque_date;
                $transaction_data['cheque_no']   = $this->input->post('cheque_no');
                if (isset($_FILES["document"]) && !empty($_FILES['document']['name'])) {
                    $fileInfo        = pathinfo($_FILES["document"]["name"]);
                    $attachment      = uniqueFileName() . '.' . $fileInfo['extension'];
                    $attachment_name = $_FILES["document"]["name"];
                    if (move_uploaded_file($_FILES["document"]["tmp_name"], "./uploads/payment_document/" . $attachment)) {
                        $transaction_data['attachment']      = $attachment;
                        $transaction_data['attachment_name'] = $attachment_name;

                        // SaaS: add the uploaded document's size to the storage quota usage.
                        try {
                            $this->saasvalidation->updateStorageLimit('storage', ['document']);
                        } catch (Exception $e) {
                            log_message('error', 'SaaS storage quota update failed (bill addvisitDetails): ' . $e->getMessage());
                        }
                    }
                }
            }

            $staff_data = $this->staff_model->getStaffByID($doctor_id);
            $staff_name = composeStaffName($staff_data);
            $charge     = array(
                'opd_id'             => $opd_id,
                'date'               => $date,
                'charge_id'          => $this->input->post('charge_id', TRUE),
                'qty'                => 1,
                'apply_charge'       => $this->input->post('amount', TRUE),
                'standard_charge'    => $this->input->post('standard_charge', TRUE),
                'tpa_charge'         => $this->input->post('schedule_charge', TRUE),
                'amount'             => $this->input->post('apply_amount', TRUE),
                'created_at'         => date('Y-m-d'),
                'note'               => $staff_name,
                'organisation_id'    => $organisation_id,
                'insurance_validity' => $insurance_validity,
                'insurance_id'       => $insurance_id,
            );

            $custom_value_array = array();
            if (!empty($custom_field_post)) {
                foreach ($custom_field_post as $key => $value) {
                    $check_field_type = $this->input->post("custom_fields[opdrecheckup][" . $key . "]", TRUE);
                    $field_value      = is_array($check_field_type) ? implode(",", $check_field_type) : $check_field_type;
                    $array_custom     = array(
                        'belong_table_id' => 0,
                        'custom_field_id' => $key,
                        'field_value'     => $field_value,
                    );
                    $custom_value_array[] = $array_custom;
                }
            }
			
            $opdvisit_id = $this->patient_model->add_visit_recheckup($opd_data, $transaction_data, $charge);

            if (!empty($custom_value_array)) {
                $this->customfield_model->insertRecord($custom_value_array, $opdvisit_id);
            }

            $live_consult   = $this->input->post('live_consult');
            $doctor_id      = $this->input->post('consultant_doctor');
            $setting_result = $this->setting_model->getzoomsetting();
            $opdduration    = $setting_result->opd_duration;
            if ($live_consult = 'yes') {
                $api_type = 'global';
                $params   = array(
                    'zoom_api_key'    => "",
                    'zoom_api_secret' => "",
                );
                $this->load->library('zoom_api', $params);
                $insert_array = array(
                    'staff_id'         => $doctor_id,
                    'visit_details_id' => $opdvisit_id,
                    'title'            => 'Online consult for Checkup ID ' . $opdvisit_id,
                    'date'             => $this->customlib->dateFormatToYYYYMMDDHis($appointment_date, $this->time_format),
                    'duration'         => $opdduration,
                    'created_id'       => $this->customlib->getStaffID(),
                    'password'         => $password,
                    'api_type'         => $api_type,
                    'host_video'       => 1,
                    'client_video'     => 1,
                    'purpose'          => 'consult',
                    'timezone'         => $this->customlib->getTimeZone(),
                );
                $response = $this->zoom_api->createAMeeting($insert_array);

                if (!empty($response)) {
                    if (isset($response->id)) {
                        $insert_array['return_response'] = json_encode($response);
                        $conferenceid                    = $this->conference_model->add($insert_array);
                        $sender_details                  = array('patient_id' => $patient_id, 'conference_id' => $conferenceid, 'contact_no' => $this->input->post('contact', TRUE), 'email' => $this->input->post('email', TRUE));

                        $this->mailsmsconf->mailsms('live_consult', $sender_details);
                    }
                }
            }

            $sender_details = array('patient_id' => $patient_id, 'opd_no' => $this->customlib->getSessionPrefixByType('opd_no') . $opd_id, 'contact_no' => $this->input->post('contact', TRUE), 'email' => $this->input->post('email', TRUE));

            $array = array('status' => 'success', 'error' => '', 'message' => $this->lang->line('success_message'));
        }
        echo json_encode($array);
    }

    //==========Blood Bank========

    public function issueblood()
    {
        if (!$this->rbac->hasPrivilege('blood_bank_billing', 'can_view')) {
            access_denied();
        }
        $doctors              = $this->staff_model->getStaffbyrole(3);
        $data["doctors"]      = $doctors;
        $patients             = $this->patient_model->getPatientListall();
        $data["patients"]     = $patients;
        $data["payment_mode"] = $this->payment_mode;
        $data["charge_type"]  = $this->chargetype_model->get();
        $data["bloodgroup"]   = $this->bloodbankstatus_model->get_product('', 1);
        $data['fields']       = $this->customfield_model->get_custom_fields('blood_issue', 1);
        $result               = $this->bloodissue_model->getBloodIssue();
        $data['result']       = $result;
        $data['module'] = 'blood_bank';
        $this->load->view('layout/header', $data);
        $this->load->view('admin/bill/bloodbank/bloodissue', $data);
        $this->load->view('layout/footer', $data);
    }

    public function getbloodissueDatatable()
    {
        $fields      = $this->customfield_model->get_custom_fields('blood_issue', 1);
        $dt_response = $this->bloodissue_model->getbloodissueDatatable();
        $dt_response = json_decode($dt_response);
        $dt_data     = array();

        if (!empty($dt_response->data)) {
            foreach ($dt_response->data as $key => $value) {

                $row = array();
                //====================================
                $action = "<div class='rowoptionview rowview-btn-top'>";
                $action .= "<a href='#' data-record-id='" . $value->id . "' class='btn btn-default btn-xs viewDetail' data-bs-toggle='tooltip' title='" . $this->lang->line('show') . "' ><i class='fa fa-reorder'></i></a>";

                $action .= "<a href='javascript:void(0)'  data-caseid='' data-module='blood_bank' data-record-id='" . $value->id . "' class='btn btn-default btn-xs add_payment' data-bs-toggle='tooltip' title='" . $this->lang->line('add_payment') . "' ><i class='fa fa-plus'></i></a>";

                if ($this->rbac->hasPrivilege('blood_issue', 'can_delete')) {
                    $action .= "<a class='btn btn-default btn-xs delete_blood_issue' data-bs-toggle='tooltip' title='" . $this->lang->line('delete') . "' data-record-id='" . $value->id . "'><i class='fa fa-trash'></i></a>";
                }

                $action .= "</div>";
				
				if ($value->gender) {
                    $gender = $this->lang->line(strtolower($value->gender));
                } else {
                    $gender = '';
                }
				
                //==============================
                $row[] = $this->customlib->getSessionPrefixByType('blood_bank_billing') . $value->id;
                $row[] = $value->case_reference_id;
                $row[] = $this->customlib->dateyyyymmddToDateTimeformat($value->date_of_issue, false);
                $row[] = $value->patient_name . " (" . $value->patient_id . ")";
                $row[] = $value->blood_group;
                $row[] = $gender;
                $row[] = $value->donor_name;
                $row[] = $this->customlib->bag_string($value->bag_no, $value->volume, $value->unit_name);
                 $row[] = composeStaffNameByString($value->generated_byname, $value->generated_bysurname, $value->generated_byemployee_id);
                //====================
                if (!empty($fields)) {
                    foreach ($fields as $fields_key => $fields_value) {
                        $display_field = $value->{"$fields_value->name"};
                        if ($fields_value->type == "link") {
                            $display_field = "<a href=" . $value->{"$fields_value->name"} . " target='_blank'>" . $value->{"$fields_value->name"} . "</a>";

                        }
                        $row[] = $display_field;
                    }
                }
                //====================

                $row[]     = $value->amount;
				
				$percentage_amt = amountFormat(($value->amount * $value->discount_percentage)/100);
				
                $row[]     = $percentage_amt.'('.$value->discount_percentage.'%)';
				
				$tax_amt = amountFormat((($value->amount - $percentage_amt) * $value->tax_percentage)/100);
				
                $row[]     = $tax_amt.'('.$value->tax_percentage.'%)';				
                $row[]     = $value->net_amount;
                $row[]     = $value->paid_amount;
                $row[]     = amountFormat($value->net_amount - $value->paid_amount) . $action;

                $dt_data[] = $row;
            }
        }
        $json_data = array(
            "draw"            => intval($dt_response->draw),
            "recordsTotal"    => intval($dt_response->recordsTotal),
            "recordsFiltered" => intval($dt_response->recordsFiltered),
            "data"            => $dt_data,
        );
        echo json_encode($json_data);
    }

    public function allotblood()
    {
        $data                    = array();
        $doctors                 = $this->staff_model->getStaffbyrole(3);
        $data["doctors"]         = $doctors;
        $patients                = $this->patient_model->getPatientListall();
        $data["patients"]        = $patients;
        $data["payment_mode"]    = $this->payment_mode;
        $data["charge_type"]     = $this->chargetype_model->get();
        $data["stockbloodgroup"] = $this->bloodbankstatus_model->get_stock_bloodgroup();
        $page                    = $this->load->view('admin/bill/bloodbank/_allotblood', $data, true);
        echo json_encode(array('status' => 1, 'page' => $page));
    }

    public function save_blood_issue()
    {
        $id = $this->input->post('id', TRUE);
        $this->form_validation->set_rules('date_of_issue', $this->lang->line('issue_date'), 'trim|required|xss_clean');
        $this->form_validation->set_rules('patient_id', $this->lang->line('patient'), 'trim|required|xss_clean');
        $this->form_validation->set_rules('reference', $this->lang->line('reference_name'), 'trim|required|xss_clean');
        $this->form_validation->set_rules('bag_no', $this->lang->line('bag_no'), 'trim|required|xss_clean');
        $this->form_validation->set_rules('total', $this->lang->line('total'), 'trim|required|xss_clean|numeric');
        $this->form_validation->set_rules('charge_id', $this->lang->line('charges'), 'trim|required|xss_clean|numeric');
        $this->form_validation->set_rules('charge_category', $this->lang->line('charge_category'), 'trim|required|xss_clean|numeric');
        $this->form_validation->set_rules('net_amount', $this->lang->line('net_amount'), 'trim|required|xss_clean|numeric');
        $this->form_validation->set_rules('discount', $this->lang->line('discount'), 'trim|required|xss_clean|numeric');
        $this->form_validation->set_rules('discount_percent', $this->lang->line('discount_percentage'), 'trim|required|xss_clean|numeric');
        $this->form_validation->set_rules('note', $this->lang->line('note'), 'trim|xss_clean');
        $this->form_validation->set_rules('technician', $this->lang->line('technician'), 'trim|xss_clean');
        $this->form_validation->set_rules('consultant_doctor', $this->lang->line('doctor'), 'trim|xss_clean');
        if (empty($id)) {
            $this->form_validation->set_rules(
                'payment_amount', $this->lang->line('payment_amount'), array('trim', 'required', 'xss_clean', 'valid_amount',
                    array('check_exists', array($this->bloodbankstatus_model, 'validate_paymentamount')),
                )
            );

            if ($this->input->post('payment_mode', TRUE) == "Cheque") {
                $this->form_validation->set_rules('cheque_no', $this->lang->line('cheque_no'), 'trim|required|xss_clean');
                $this->form_validation->set_rules('cheque_date', $this->lang->line('cheque_date'), 'trim|required|xss_clean');
                $this->form_validation->set_rules('document', $this->lang->line('document'), 'callback_handle_upload|callback_validateCanUploadFile[document]');
            }
        }

        $custom_fields = $this->customfield_model->getByBelong('blood_issue');
        foreach ($custom_fields as $custom_fields_key => $custom_fields_value) {
            if ($custom_fields_value['validation']) {
                $custom_fields_id   = $custom_fields_value['id'];
                $custom_fields_name = $custom_fields_value['name'];
                $this->form_validation->set_rules("custom_fields[blood_issue][" . $custom_fields_id . "]", $custom_fields_name, 'trim|required');
            }
        }

        if ($this->form_validation->run() == false) {
            $msg = array(
                'date_of_issue'    => form_error('date_of_issue'),
                'patient_id'       => form_error('patient_id'),
                'reference'        => form_error('reference'),
                'bag_no'           => form_error('bag_no'),
                'total'            => form_error('total'),
                'net_amount'       => form_error('net_amount'),
                'tax'              => form_error('tax'),
                'tax_percentage'   => form_error('tax_percentage'),
                'discount'         => form_error('discount'),
                'discount_percent' => form_error('discount_percent'),
                'charge_id'        => form_error('charge_id'),
                'charge_category'  => form_error('charge_category'),
            );
            if (empty($id)) {
                $msg['cheque_no']      = form_error('cheque_no');
                $msg['cheque_date']    = form_error('cheque_date');
                $msg['payment_amount'] = form_error('payment_amount');
                $msg['document']       = form_error('document');
            }
            if (!empty($custom_fields)) {
                foreach ($custom_fields as $custom_fields_key => $custom_fields_value) {
                    if ($custom_fields_value['validation']) {
                        $custom_fields_id                                                    = $custom_fields_value['id'];
                        $custom_fields_name                                                  = $custom_fields_value['name'];
                        $error_msg2["custom_fields[blood_issue][" . $custom_fields_id . "]"] = form_error("custom_fields[blood_issue][" . $custom_fields_id . "]");
                    }
                }
            }
            if (!empty($error_msg2)) {
                $error_msg = array_merge($msg, $error_msg2);
            } else {
                $error_msg = $msg;
            }
            $array = array('status' => 'fail', 'error' => $error_msg, 'message' => '');
        } else {
            $case_reference_id = $this->input->post('id_case_reference_id_exist', TRUE);
            $issue_date        = $this->customlib->dateFormatToYYYYMMDDHis($this->input->post('date_of_issue', TRUE), $this->time_format);
            $patient_id        = $this->input->post('patient_id', TRUE);
            $is_tpa            = $this->input->post('is_tpa', TRUE);
            if($is_tpa=="" || $is_tpa==0){
                $organisation_id    = null;
                $insurance_validity = null;
                $insurance_id       = null;
            }else{
                $organisation_id    = $this->input->post('organisation_id', TRUE);
                $insurance_id       = $this->input->post('insurance_id', TRUE);
                $insurance_validity = $this->customlib->dateFormatToYYYYMMDD($this->input->post('insurance_validity', TRUE));
            }
            if (empty($organisation_id)) {
                $organisation_id = null;
            }

            $bloodissue        = array(
                'date_of_issue'        => $issue_date,
                'patient_id'           => $patient_id,
                'technician'           => $this->input->post('technician', TRUE),
                'hospital_doctor'      => $this->input->post('consultant_doctor', TRUE),
                'reference'            => $this->input->post('reference', TRUE),
                'blood_donor_cycle_id' => $this->input->post('bag_no', TRUE),
                'generated_by'         => $this->session->userdata('hospitaladmin')['id'],
                'remark'               => $this->input->post('note', TRUE),
                'charge_id'            => $this->input->post('charge_id', TRUE),
                'standard_charge'      => $this->input->post('standard_charge', TRUE),
                'amount'               => $this->input->post('total', TRUE),
                'net_amount'           => $this->input->post('net_amount', TRUE),
                'tax_percentage'       => $this->input->post('tax_percentage', TRUE),
                'discount_percentage'  => $this->input->post('discount_percent', TRUE),
                'organisation_id'      => $organisation_id,
                'insurance_id'         => $insurance_id,
                'insurance_validity'   => $insurance_validity,
            );

            if ($case_reference_id != '') {
                $bloodissue['case_reference_id'] = $case_reference_id;
            }

            $chequedate       = $this->input->post('cheque_date', TRUE);
            $payment_section  = $this->config->item('payment_section');
            $cheque_date      = $this->customlib->dateFormatToYYYYMMDD($chequedate);
            $transaction_data = array(
                'patient_id'   => $patient_id,
                'section'      => $payment_section['blood_bank'],
                'amount'       => $this->input->post('payment_amount', TRUE),
                'type'         => 'payment',
                'payment_mode' => $this->input->post('payment_mode', TRUE),
                'note'         => $this->input->post('note', TRUE),
                'payment_date' => $issue_date,
                'received_by'  => $this->session->userdata('hospitaladmin')['id'],
            );
            if (!empty($this->input->post('case_reference_id', TRUE)) && $this->input->post('case_reference_id', TRUE) != "") {
                $payment_array['case_reference_id'] = $this->input->post('case_reference_id', TRUE);
            }

            if ($this->input->post('payment_mode', TRUE) == "Cheque") {
                $transaction_data['cheque_date']     = $this->customlib->dateFormatToYYYYMMDD($cheque_date);
                $transaction_data['cheque_no']       = $this->input->post('cheque_no', TRUE);
            
                if (isset($_FILES["document"]) && !empty($_FILES['document']['name'])) {
                    $file_name = $this->media_storage->fileupload("document",'./uploads/payment_document/');
                    $transaction_data['attachment']      = $file_name;
                    $transaction_data['attachment_name'] = $_FILES["document"]["name"];

                    // SaaS: add the uploaded document's size to the storage quota usage.
                    try {
                        $saas_quota_result = $this->saasvalidation->updateStorageLimit('storage', ['document']);
                        // Capture silent API failures (lib returns JSON status:false instead of throwing).
                        if (is_string($saas_quota_result)) {
                            $saas_quota_decoded = json_decode($saas_quota_result);
                            if (isset($saas_quota_decoded->status) && $saas_quota_decoded->status === false) {
                                log_message('error', 'SaaS storage quota update returned failure (bill save_blood_issue): ' . ($saas_quota_decoded->message ?? 'unknown'));
                            }
                        }
                    } catch (Exception $e) {
                        log_message('error', 'SaaS storage quota update failed (bill save_blood_issue): ' . $e->getMessage());
                    }
                }
            }

            if (!empty($id)) {
                $bloodissue['id'] = $id;
                $transaction_data = array();
            }
            $insert_id          = $this->bloodissue_model->add($bloodissue, $transaction_data);
            $custom_field_post  = $this->input->post("custom_fields[blood_issue]", TRUE);
            $custom_value_array = array();
            if (!empty($custom_field_post)) {
                if ($id > 0) {
                    foreach ($custom_field_post as $key => $value) {
                        $check_field_type = $this->input->post("custom_fields[blood_issue][" . $key . "]", TRUE);
                        $field_value      = is_array($check_field_type) ? implode(",", $check_field_type) : $check_field_type;
                        $array_custom     = array(
                            'belong_table_id' => $id,
                            'custom_field_id' => $key,
                            'field_value'     => $field_value,
                        );
                        $custom_value_array[] = $array_custom;
                    }
                } else {
                    foreach ($custom_field_post as $key => $value) {
                        $check_field_type = $this->input->post("custom_fields[blood_issue][" . $key . "]");
                        $field_value      = is_array($check_field_type) ? implode(",", $check_field_type) : $check_field_type;
                        $array_custom     = array(
                            'belong_table_id' => $insert_id,
                            'custom_field_id' => $key,
                            'field_value'     => $field_value,
                        );
                        $custom_value_array[] = $array_custom;
                    }
                }
            }

            if (!empty($custom_value_array)) {
                if ($id > 0) {
                    $this->customfield_model->updateRecord($custom_value_array, $id, 'blood_issue');
                } else {
                    $this->customfield_model->insertRecord($custom_value_array, $insert_id);
                }
            }

            $charge_details = $this->notificationsetting_model->getchargeDetails($this->input->post('charge_id'));
            $issue_details  = $this->bloodissue_model->getDetail($insert_id);

            $event_data = array(
                'case_id'        => $case_reference_id,
                'patient_id'     => $patient_id,
                'bill_no'        => $this->customlib->getSessionPrefixByType('blood_bank_billing') . $insert_id,
                'issue_date'     => $issue_date,
                'reference_name' => $this->input->post('reference'),
                'blood_group'    => $issue_details['blood_group'],
                'bag'            => $issue_details['bag_no'] . " (" . $issue_details['volume'] . " " . $issue_details['unit_name'] . ")",
                'charge_name'    => $charge_details['name'],
                'total'          => $this->input->post('total'),
                'discount'       => $this->input->post('discount_percent'),
                'tax'            => $this->input->post('tax_percentage'),
                'net_amount'     => $this->input->post('net_amount'),
            );

            $this->system_notification->send_system_notification('blood_issue', $event_data);
            $array = array('status' => 'success', 'id' => $insert_id, 'error' => '', 'message' => $this->lang->line('success_message'));
        }
        echo json_encode($array);
    }

    public function handle_upload()
    {
        $image_validate = $this->config->item('file_validate');
        if (isset($_FILES["file"]) && !empty($_FILES['file']['name'])) {

            $file_type         = $_FILES["file"]['type'];
            $file_size         = $_FILES["file"]["size"];
            $file_name         = $_FILES["file"]["name"];
            $allowed_extension = $image_validate['allowed_extension'];
            $ext               = pathinfo($file_name, PATHINFO_EXTENSION);
            $allowed_mime_type = $image_validate['allowed_mime_type'];
            if ($files = @getimagesize($_FILES['file']['tmp_name'])) {
                if (!in_array($files['mime'], $allowed_mime_type)) {
                    $this->form_validation->set_message('handle_upload', $this->lang->line('file_type_not_allowed'));
                    return false;
                }
                if (!in_array(strtolower($ext), $allowed_extension) || !in_array($file_type, $allowed_mime_type)) {
                    $this->form_validation->set_message('handle_upload', $this->lang->line('file_extension_not_allowed'));
                    return false;
                }
                if ($file_size > $image_validate['upload_size']) {
                    $this->form_validation->set_message('handle_upload', $this->lang->line('file_size_shoud_be_less_than') . number_format($image_validate['upload_size'] / 1048576, 2) . " MB");
                    return false;
                }
            } else {
                $this->form_validation->set_message('handle_upload', $this->lang->line('file_type_extension_not_allowed'));
                return false;
            }

            return true;
        }
        return true;
    }
	
    //============blood component==========
    public function issuecomponent()
    {
        if (!$this->rbac->hasPrivilege('blood_bank_billing', 'can_view')) {
            access_denied();
        }
        $doctors              = $this->staff_model->getStaffbyrole(3);
        $data["doctors"]      = $doctors;
        $patients             = $this->patient_model->getPatientListall();
        $data["patients"]     = $patients;
        $data["payment_mode"] = $this->payment_mode;
        $data["charge_type"]  = $this->chargetype_model->get();
        $data["bloodgroup"]   = $this->bloodbankstatus_model->get_product('', 1);
        $result               = $this->bloodissue_model->getBloodIssue();
        $data['fields']       = $this->customfield_model->get_custom_fields('component_issue', 1);
        $data['result']       = $result;
        $data['module'] = 'blood_bank';
        $this->load->view('layout/header', $data);
        $this->load->view('admin/bill/bloodbank/issuecomponent', $data);
        $this->load->view('layout/footer', $data);
    }

    public function getcomponentissuebycashidDatatable($case_id)
    {
        $fields      = $this->customfield_model->get_custom_fields('component_issue', 1);
        $dt_response = $this->bloodissue_model->getcomponentissuebycashidDatatable($case_id);
        $dt_response = json_decode($dt_response);
        $dt_data     = array();
        if (!empty($dt_response->data)) {
            foreach ($dt_response->data as $key => $value) {
                $row = array();
                //====================================
                $action = "<div class='rowoptionview rowview-mt-19'>";
                $action .= "<a href='#' data-record-id='" . $value->id . "' class='btn btn-default btn-xs printcomponentIssueBill'  data-bs-toggle='tooltip' title='" . $this->lang->line('print') . "' ><i class='fa fa-print'></i></a>";

                if ($this->rbac->hasPrivilege('blood_bank_partial_payment', 'can_view')) {
                    $action .= "<a href='javascript:void(0)'  data-caseid='' data-module='blood_bank' data-record-id='" . $value->id . "' class='btn btn-default btn-xs add_payment_blood_component' data-bs-toggle='tooltip' title='" . $this->lang->line('add_payment') . "' ><i class='fa fa-plus'></i></a>";
                }                

                $action .= "</div>";
                $prefix = $this->customlib->getSessionPrefixByType('blood_bank_billing') . $value->id;

                //==============================
                $row[] = $prefix;
                $row[] = $value->case_reference_id;
                $row[] = $this->customlib->dateyyyymmddToDateTimeformat($value->date_of_issue, false);
                $row[] = composeStaffNameByString($value->name, $value->surname, $value->employee_id);
                $row[] = $value->patient_name . " (" . $value->patient_id . ")";
                $row[] = $value->blood_group_name;
                $row[] = $value->component_name;
                $row[] = (isset($value->gender) && $value->gender!="") ? $this->lang->line(strtolower($value->gender)) : "";
                $row[] = $value->donor_name;
                $row[] = $this->customlib->bag_string($value->bag_no, $value->volume, $value->unit_name);
               
                //====================
                $row[]     = $value->amount;
                $percentage_amt = amountFormat(($value->amount * $value->discount_percentage)/100);
                $row[]     = $percentage_amt.'('.$value->discount_percentage.'%)';
                $tax_amt = amountFormat((($value->amount - $percentage_amt) * $value->tax_percentage)/100);
                $row[]     = $tax_amt.'('.$value->tax_percentage.'%)';      
                $row[]     = $value->net_amount;
                $row[]     = $value->paid_amount;
                $row[]     = amountFormat($value->net_amount - $value->paid_amount) . $action;
                $dt_data[] = $row;
            }
        }
        $json_data = array(
            "draw"            => intval($dt_response->draw),
            "recordsTotal"    => intval($dt_response->recordsTotal),
            "recordsFiltered" => intval($dt_response->recordsFiltered),
            "data"            => $dt_data,
        );
        echo json_encode($json_data);
    }
    public function allotcomponent()
    {
        $data                    = array();
        $doctors                 = $this->staff_model->getStaffbyrole(3);
        $data["doctors"]         = $doctors;
        $patients                = $this->patient_model->getPatientListall();
        $data["patients"]        = $patients;
        $data["payment_mode"]    = $this->payment_mode;
        $data["charge_type"]     = $this->chargetype_model->get();
        $data["stockbloodgroup"] = $this->bloodbankstatus_model->get_stock_bloodgroup();
        $data["blood_component"] = $this->bloodbankstatus_model->get_product(null, 2);
        $page                    = $this->load->view('admin/bill/bloodbank/_allotcomponent', $data, true);
        echo json_encode(array('status' => 1, 'page' => $page));
    }

    public function bloodbank_transactions()
    {
        $billing_id                 = $this->input->post('id');
        $data['patient_id']         = $this->input->post('patient_id');
        $data['blood_issue_detail'] = $this->bloodissue_model->getDetail($billing_id);
        $transaction                = $this->transaction_model->bloodbankPayments($billing_id);
        $data["billing_id"]         = $billing_id;
        $data["payment_mode"]       = $this->payment_mode;
        $data['transaction']        = $transaction;
        $page                       = $this->load->view("admin/bill/bloodbank/_bloodbank_transactions", $data, true);
        echo json_encode(array('status' => 1, 'page' => $page));
    }

    public function save_issue_component()
    {
        $id = $this->input->post('id');
        $this->form_validation->set_rules('date_of_issue', $this->lang->line('issue_date'), 'trim|required|xss_clean');
        $this->form_validation->set_rules('patient_id', $this->lang->line('patient'), 'trim|required|xss_clean');
        $this->form_validation->set_rules('reference', $this->lang->line('reference_name'), 'trim|required|xss_clean');
        $this->form_validation->set_rules('bag_no', $this->lang->line('bag_no'), 'trim|required|xss_clean');
        $this->form_validation->set_rules('total', $this->lang->line('total'), 'trim|required|xss_clean|numeric');
        $this->form_validation->set_rules('charge_id', $this->lang->line('charges'), 'trim|required|xss_clean|numeric');
        $this->form_validation->set_rules('charge_category', $this->lang->line('charge_category'), 'trim|required|xss_clean|numeric');
        $this->form_validation->set_rules('net_amount', $this->lang->line('net_amount'), 'trim|required|xss_clean|numeric');
        $this->form_validation->set_rules('discount', $this->lang->line('discount'), 'trim|required|xss_clean|numeric');
        $this->form_validation->set_rules('discount_percent', $this->lang->line('discount_percentage'), 'required|numeric');
        $this->form_validation->set_rules('note', $this->lang->line('note'), 'trim|xss_clean');
        $this->form_validation->set_rules('technician', $this->lang->line('technician'), 'trim|xss_clean');
        if (empty($id)) {
            $this->form_validation->set_rules(
                'payment_amount', $this->lang->line('payment_amount'), array('required', 'xss_clean', 'valid_amount',
                    array('check_exists', array($this->bloodbankstatus_model, 'validate_paymentamount')),
                )
            );
            if ($this->input->post('payment_mode', TRUE) == "Cheque") {
                $this->form_validation->set_rules('cheque_no', $this->lang->line('cheque_no'), 'trim|required|xss_clean');
                $this->form_validation->set_rules('cheque_date', $this->lang->line('cheque_date'), 'trim|required|xss_clean');
                $this->form_validation->set_rules('document', $this->lang->line('document'), 'callback_handle_upload|callback_validateCanUploadFile[document]');
            }
        }

        $custom_fields = $this->customfield_model->getByBelong('component_issue');
        foreach ($custom_fields as $custom_fields_key => $custom_fields_value) {
            if ($custom_fields_value['validation']) {
                $custom_fields_id   = $custom_fields_value['id'];
                $custom_fields_name = $custom_fields_value['name'];
                $this->form_validation->set_rules("custom_fields[component_issue][" . $custom_fields_id . "]", $custom_fields_name, 'trim|required');
            }
        }
		
        if ($this->form_validation->run() == false) {
            $msg = array(
                'date_of_issue'    => form_error('date_of_issue'),
                'patient_id'       => form_error('patient_id'),
                'reference'        => form_error('reference'),
                'bag_no'           => form_error('bag_no'),
                'total'            => form_error('total'),
                'net_amount'       => form_error('net_amount'),
                'tax'              => form_error('tax'),
                'tax_percentage'   => form_error('tax_percentage'),
                'discount'         => form_error('discount'),
                'discount_percent' => form_error('discount_percent'),
                'charge_id'        => form_error('charge_id'),
                'charge_category'  => form_error('charge_category'),
            );

            if (empty($id)) {
                $msg['cheque_no']      = form_error('cheque_no');
                $msg['cheque_date']    = form_error('cheque_date');
                $msg['payment_amount'] = form_error('payment_amount');
                $msg['document']       = form_error('document');
            }

            if (!empty($custom_fields)) {
                foreach ($custom_fields as $custom_fields_key => $custom_fields_value) {
                    if ($custom_fields_value['validation']) {
                        $custom_fields_id                                                        = $custom_fields_value['id'];
                        $custom_fields_name                                                      = $custom_fields_value['name'];
                        $error_msg2["custom_fields[component_issue][" . $custom_fields_id . "]"] = form_error("custom_fields[component_issue][" . $custom_fields_id . "]");
                    }
                }
            }

            if (!empty($error_msg2)) {
                $error_msg = array_merge($msg, $error_msg2);
            } else {
                $error_msg = $msg;
            }
            $array = array('status' => 'fail', 'error' => $error_msg, 'message' => '');
        } else {

            $case_reference_id = $this->input->post('is_case_reference_idd', TRUE);
            $issue_date        = $this->input->post('date_of_issue', TRUE);
            $patient_id        = $this->input->post('patient_id', TRUE);
              $is_tpa = $this->input->post('is_tpa', TRUE);
            if($is_tpa=="" || $is_tpa==0){
                $organisation_id    = null;
                $insurance_validity = null;
                $insurance_id       = null;
            }else{
                $organisation_id    = $this->input->post('organisation_id', TRUE);
                $insurance_id       = $this->input->post('insurance_id', TRUE);
                $insurance_validity = $this->customlib->dateFormatToYYYYMMDD($this->input->post('insurance_validity', TRUE));
            }
            if (empty($organisation_id)) {
                $organisation_id = null;
            }
            $bloodissue        = array(
                'date_of_issue'        => $this->customlib->dateFormatToYYYYMMDDHis($issue_date, $this->time_format),
                'patient_id'           => $patient_id,
                'technician'           => $this->input->post('technician', TRUE),
                'reference'            => $this->input->post('reference', TRUE),
                'blood_donor_cycle_id' => $this->input->post('bag_no', TRUE),
                'generated_by'         => $this->session->userdata('hospitaladmin')['id'],
                'remark'               => $this->input->post('note', TRUE),
                'charge_id'            => $this->input->post('charge_id', TRUE),
                'standard_charge'      => $this->input->post('standard_charge', TRUE),
                'amount'               => $this->input->post('total', TRUE),
                'net_amount'           => $this->input->post('net_amount', TRUE),
                'tax_percentage'       => $this->input->post('tax_percentage', TRUE),
                'remark'               => $this->input->post('note', TRUE),
                'discount_percentage'  => $this->input->post('discount_percent', TRUE),
                'organisation_id'      => $organisation_id,
                'insurance_id'         => $insurance_id,
                'insurance_validity'   => $insurance_validity,
            );

            if ($case_reference_id != '') {
                $bloodissue['case_reference_id'] = $case_reference_id;
            }

            $chequedate       = $this->input->post('cheque_date', TRUE);
            $cheque_date      = $this->customlib->dateFormatToYYYYMMDD($chequedate);
            $payment_section  = $this->config->item('payment_section');
            $transaction_data = array(
                'amount'       => $this->input->post('payment_amount', TRUE),
                'patient_id'   => $this->input->post('patient_id', TRUE),
                'section'      => $payment_section['blood_bank'],
                'type'         => 'payment',
                'payment_mode' => $this->input->post('payment_mode', TRUE),
                'payment_date' => $this->customlib->dateFormatToYYYYMMDD($issue_date),
                'received_by'  => $this->session->userdata('hospitaladmin')['id'],
            );

            if (!empty($this->input->post('case_reference_id', TRUE)) && $this->input->post('case_reference_id', TRUE) != "") {
                $transaction_data['case_reference_id'] = $this->input->post('case_reference_id', TRUE);
            }

            if ($this->input->post('payment_mode', TRUE) == "Cheque") {
                $transaction_data['cheque_date']     = $this->customlib->dateFormatToYYYYMMDD($cheque_date);
                $transaction_data['cheque_no']       = $this->input->post('cheque_no', TRUE);
               
                if (isset($_FILES["document"]) && !empty($_FILES['document']['name'])) {
                    $file_name = $this->media_storage->fileupload("document",'./uploads/payment_document/');
                    $transaction_data['attachment']      = $file_name;
                    $transaction_data['attachment_name'] = $_FILES["document"]["name"];

                    // SaaS: add the uploaded document's size to the storage quota usage.
                    try {
                        $saas_quota_result = $this->saasvalidation->updateStorageLimit('storage', ['document']);
                        // Capture silent API failures (lib returns JSON status:false instead of throwing).
                        if (is_string($saas_quota_result)) {
                            $saas_quota_decoded = json_decode($saas_quota_result);
                            if (isset($saas_quota_decoded->status) && $saas_quota_decoded->status === false) {
                                log_message('error', 'SaaS storage quota update returned failure (bill save_issue_component): ' . ($saas_quota_decoded->message ?? 'unknown'));
                            }
                        }
                    } catch (Exception $e) {
                        log_message('error', 'SaaS storage quota update failed (bill save_issue_component): ' . $e->getMessage());
                    }
                }

            }

            if (!empty($id)) {
                $bloodissue['id'] = $id;
                $transaction_data = array();
            }

            $insert_id           = $this->bloodissue_model->add($bloodissue, $transaction_data);
            $blood_issue_details = $this->bloodissue_model->getcomponentDetail($insert_id);
            $custom_field_post   = $this->input->post("custom_fields[component_issue]", TRUE);
            $custom_value_array  = array();
            if (!empty($custom_field_post)) {
                if ($id > 0) {
                    foreach ($custom_field_post as $key => $value) {
                        $check_field_type = $this->input->post("custom_fields[component_issue][" . $key . "]", TRUE);
                        $field_value      = is_array($check_field_type) ? implode(",", $check_field_type) : $check_field_type;
                        $array_custom     = array(
                            'belong_table_id' => $id,
                            'custom_field_id' => $key,
                            'field_value'     => $field_value,
                        );
                        $custom_value_array[] = $array_custom;
                    }
                } else {
                    foreach ($custom_field_post as $key => $value) {
                        $check_field_type = $this->input->post("custom_fields[component_issue][" . $key . "]", TRUE);
                        $field_value      = is_array($check_field_type) ? implode(",", $check_field_type) : $check_field_type;
                        $array_custom     = array(
                            'belong_table_id' => $insert_id,
                            'custom_field_id' => $key,
                            'field_value'     => $field_value,
                        );
                        $custom_value_array[] = $array_custom;
                    }
                }
            }

            if (!empty($custom_value_array)) {
                if ($id > 0) {
                    $this->customfield_model->updateRecord($custom_value_array, $id, 'component_issue');
                } else {
                    $this->customfield_model->insertRecord($custom_value_array, $insert_id);
                }
            }

            $charge_details = $this->notificationsetting_model->getchargeDetails($this->input->post('charge_id'));

            $event_data = array(
                'case_id'        => $case_reference_id,
                'patient_id'     => $patient_id,
                'bill_no'        => $this->customlib->getSessionPrefixByType('blood_bank_billing') . $insert_id,
                'issue_date'     => $this->customlib->YYYYMMDDHisTodateFormat($issue_date, $this->time_format),
                'reference_name' => $this->input->post('reference'),
                'blood_group'    => $blood_issue_details['blood_group_name'],
                'component'      => $blood_issue_details['component_name'],
                'bag'            => $this->customlib->bag_string($blood_issue_details['bag_no'], $blood_issue_details['volume'], $blood_issue_details['unit']),
                'charge_name'    => $charge_details['name'],
                'total'          => number_format((float) $this->input->post('total'), 2, '.', ''),
                'discount'       => number_format((float) $this->input->post('discount_percent'), 2, '.', ''),
                'tax'            => $this->input->post('tax_percentage'),
                'net_amount'     => number_format((float) $this->input->post('net_amount'), 2, '.', ''),
            );

            $this->system_notification->send_system_notification('component_issue', $event_data);
            $array = array('status' => 'success', 'id' => $insert_id, 'error' => '', 'message' => $this->lang->line('success_message'));
        }

        echo json_encode($array);
    } 

    //========Appointment==============

    public function appointment()
    {
        $this->session->set_userdata('top_menu', 'bill');
        $app_data                      = $this->session->flashdata('app_data');
        $data['app_data']              = $app_data;
        $doctors                       = $this->staff_model->getStaffbyrole(3);
        $data["doctors"]               = $doctors;
        $patients                      = $this->patient_model->getPatientListall();
        $data["patients"]              = $patients;
        $data["appointment_status"]    = $this->appointment_status;
        $data["yesno_condition"]       = $this->yesno_condition;
        $userdata                      = $this->customlib->getUserData();
        $role_id                       = $userdata['role_id'];
        $data["bloodgroup"]            = $this->bloodbankstatus_model->get_product(null, 1);
        $doctorid                      = "";
        $data['appoint_priority_list'] = $this->appoint_priority_model->appoint_priority_list();
        $doctor_restriction            = $this->session->userdata['hospitaladmin']['doctor_restriction'];
        $disable_option                = false;

        if ($doctor_restriction == 'enabled') {
            if ($role_id == 3) {
                $disable_option = true;
                $doctorid       = $userdata['id'];
            }
        }

        $data["doctor_select"]  = $doctorid;
        $data["disable_option"] = $disable_option;
        $data['fields']         = $this->customfield_model->get_custom_fields('appointment', 1);
        $data['payment_mode']   = $this->payment_mode;        
        $data['organisation']       = $this->organisation_model->get();
        $data['module'] = 'opd';
        $this->load->view('layout/header', $data);
        $this->load->view('admin/bill/appointment/appointment', $data);
        $this->load->view('layout/footer', $data);
    }

    public function getappointmentdatatabletodays()
    {
        $dt_response = $this->appointment_model->getAllappointmentRecord(1);

        $fields      = $this->customfield_model->get_custom_fields('appointment', 1);
        $dt_response = json_decode($dt_response);
        $dt_data     = array();
        if (!empty($dt_response->data)) {
            foreach ($dt_response->data as $key => $value) {

                $row = array();
                $label = "";
                $status = "";

                if ($value->appointment_status == "approved") {
                    $label  = "class='badge bg-success text-white'";
                    $status = $this->customlib->getSessionPrefixByType('appointment') . $value->id;
                } else if ($value->appointment_status == "pending") {
                    $label  = "class='badge bg-warning text-dark'";
                    $status = $this->lang->line($value->appointment_status);
                } else if ($value->appointment_status == "cancel") {
                    $label  = "class='badge bg-danger text-white'";
                    $status = $this->lang->line($value->appointment_status);
                }

                $action = "<div class='rowoptionview rowview-btn-top'>";
                $action .= "<a href='#' data-bs-toggle='tooltip' title='" . $this->lang->line('show') . "' class='btn btn-secondary btn-sm'   data-bs-target='#viewModal' onclick='viewDetail(" . $value->id . ")'>  <i class='fa fa-reorder'></i> </a>";
                $action .="<a href='#'  class='btn btn-secondary btn-sm' data-bs-toggle='tooltip'  onclick='printAppointment(" . $value->id .")' title='".$this->lang->line('print')."'><i class='fa fa-print'></i></a>";

                $action .= " <a href='#' data-bs-toggle='tooltip' title='" . $this->lang->line('reschedule') . "' class='btn btn-secondary btn-sm'   data-bs-target='#rescheduleModal' onclick='viewreschedule(" . $value->id . ",1)'>  <i class='fa fa-calendar'></i> </a>";

                if ($value->appointment_status == 'pending') {
                    if ($value->source != 'Online') {
                        if ($this->rbac->hasPrivilege('appointment_approve', 'can_view')) {

                            $action .= "<span class='large-tooltip'><a href='#' class='btn btn-secondary btn-sm'  data-bs-toggle='tooltip' data-bs-target='#rescheduleModal' onclick='viewreschedule(" . $value->id . ",2)' title='" . $this->lang->line('approve_appointment') . "'><i class='fa fa-check' aria-hidden='true'></i></a></span>";

                        }
                    }
                }

                $action .= "</div>";
                $first_action = "<a  href='javascript:void(0)' data-bs-toggle='tooltip'  data-bs-target='#viewModal' title=''  onclick='viewDetail(" . $value->id . ")'>";

                if (!empty($value->live_consult)) {$live_consult = $this->lang->line($value->live_consult);} else { $live_consult = '';};

				if ($value->gender) {
                    $gender = $this->lang->line(strtolower($value->gender));
                } else {
                    $gender = '';
                }

                //==============================
                $row[] = $first_action . composePatientName($value->patient_name, $value->pid) . "</a>";
                $row[] = $status;
                $row[] = $this->customlib->YYYYMMDDHisTodateFormat($value->date, $this->time_format);
                $row[] = $value->mobileno;
                $row[] = $gender;
                $row[] = composeStaffNameByString($value->name, $value->surname, $value->employee_id);
                $row[] = $this->lang->line(strtolower($value->source));

                $row[] = $value->priorityname;
                if ($this->module_lib->hasActive('live_consultation')) {
                    $row[] = $live_consult;
                }
                //====================
                if (!empty($fields)) {
                    foreach ($fields as $fields_key => $fields_value) {
                        $display_field = $value->{"$fields_value->name"};
                        if ($fields_value->type == "link") {
                            $display_field = "<a href=" . $value->{"$fields_value->name"} . " target='_blank'>" . $value->{"$fields_value->name"} . "</a>";
                        }
                        $row[] = $display_field;
                    }
                }
                //====================
				$row[] = composeStaffNameByString($value->created_by_name, $value->created_by_surname, $value->created_by_employee_id);
                $row[]     = "<small " . $label . ">" . $this->lang->line($value->appointment_status) . "</small>";
                $dicount_amt=0;
                $dicount_amt=(($value->standard_amount*$value->discount_percentage)/100);
                $row[]     = amountFormat($value->standard_amount);
                $row[]     = amountFormat($dicount_amt)." (".$value->discount_percentage." %)";
                $row[]     = amountFormat($value->paid_amount) . $action;
                $dt_data[] = $row;
            }
        }
        $json_data = array(
            "draw"            => intval($dt_response->draw),
            "recordsTotal"    => intval($dt_response->recordsTotal),
            "recordsFiltered" => intval($dt_response->recordsFiltered),
            "data"            => $dt_data,
        );
        echo json_encode($json_data);
    }

	public function getappointmentdatatableupcoming()
    {
        $dt_response = $this->appointment_model->getAllappointmentRecord(2);

        $fields      = $this->customfield_model->get_custom_fields('appointment', 1);
        $dt_response = json_decode($dt_response);
        $dt_data     = array();
        if (!empty($dt_response->data)) {
            foreach ($dt_response->data as $key => $value) {

                $row = array();
                $label = "";
                $status = "";

                if ($value->appointment_status == "approved") {
                    $label  = "class='badge bg-success text-white'";
                    $status = $this->customlib->getSessionPrefixByType('appointment') . $value->id;
                } else if ($value->appointment_status == "pending") {
                    $label  = "class='badge bg-warning text-dark'";
                    $status = $this->lang->line($value->appointment_status);
                } else if ($value->appointment_status == "cancel") {
                    $label  = "class='badge bg-danger text-white'";
                    $status = $this->lang->line($value->appointment_status);
                }

                $action = "<div class='rowoptionview rowview-btn-top'>";
                $action .= "<a href='#' data-bs-toggle='tooltip' title='" . $this->lang->line('show') . "' class='btn btn-secondary btn-sm'   data-bs-target='#viewModal' onclick='viewDetail(" . $value->id . ")'>  <i class='fa fa-reorder'></i> </a>";
                $action .="<a href='#'  class='btn btn-secondary btn-sm' data-bs-toggle='tooltip'  onclick='printAppointment(" . $value->id .")' title='".$this->lang->line('print')."'><i class='fa fa-print'></i></a>";

                $action .= " <a href='#' data-bs-toggle='tooltip' title='" . $this->lang->line('reschedule') . "' class='btn btn-secondary btn-sm'   data-bs-target='#rescheduleModal' onclick='viewreschedule(" . $value->id . ",1)'>  <i class='fa fa-calendar'></i> </a>";

                if ($value->appointment_status == 'pending') {
                    if ($value->source != 'Online') {
                        if ($this->rbac->hasPrivilege('appointment_approve', 'can_view')) {

                            $action .= "<span class='large-tooltip'><a href='#' class='btn btn-secondary btn-sm'  data-bs-toggle='tooltip' data-bs-target='#rescheduleModal' onclick='viewreschedule(" . $value->id . ",2)' title='" . $this->lang->line('approve_appointment') . "'><i class='fa fa-check' aria-hidden='true'></i></a></span>";

                        }
                    }
                }

                $action .= "</div>";
                $first_action = "<a  href='javascript:void(0)' data-bs-toggle='tooltip'  data-bs-target='#viewModal' title=''  onclick='viewDetail(" . $value->id . ")'>";

                if (!empty($value->live_consult)) {$live_consult = $this->lang->line($value->live_consult);} else { $live_consult = '';};

				if ($value->gender) {
                    $gender = $this->lang->line(strtolower($value->gender));
                } else {
                    $gender = '';
                }

                //==============================
                $row[] = $first_action . composePatientName($value->patient_name, $value->pid) . "</a>";
                $row[] = $status;
                $row[] = $this->customlib->YYYYMMDDHisTodateFormat($value->date, $this->time_format);
                $row[] = $value->mobileno;
                $row[] = $gender;
                $row[] = composeStaffNameByString($value->name, $value->surname, $value->employee_id);
                $row[] = $this->lang->line(strtolower($value->source));

                $row[] = $value->priorityname;
                if ($this->module_lib->hasActive('live_consultation')) {
                    $row[] = $live_consult;
                }
                //====================
                if (!empty($fields)) {
                    foreach ($fields as $fields_key => $fields_value) {
                        $display_field = $value->{"$fields_value->name"};
                        if ($fields_value->type == "link") {
                            $display_field = "<a href=" . $value->{"$fields_value->name"} . " target='_blank'>" . $value->{"$fields_value->name"} . "</a>";
                        }
                        $row[] = $display_field;
                    }
                }
                //====================
			    $row[] = composeStaffNameByString($value->created_by_name, $value->created_by_surname, $value->created_by_employee_id);
                $row[]     = "<small " . $label . ">" . $this->lang->line($value->appointment_status) . "</small>";
                $dicount_amt=0;
                $dicount_amt=(($value->standard_amount*$value->discount_percentage)/100);
                $row[]     = amountFormat($value->standard_amount);
                $row[]     = amountFormat($dicount_amt)." (".$value->discount_percentage." %)";
                $row[]     = amountFormat($value->paid_amount) . $action;
                $dt_data[] = $row;
            }
        }
        $json_data = array(
            "draw"            => intval($dt_response->draw),
            "recordsTotal"    => intval($dt_response->recordsTotal),
            "recordsFiltered" => intval($dt_response->recordsFiltered),
            "data"            => $dt_data,
        );
        echo json_encode($json_data);
    }

	public function getappointmentdatatableold()
    {
        $dt_response = $this->appointment_model->getAllappointmentRecord(3);

        $fields      = $this->customfield_model->get_custom_fields('appointment', 1);
        $dt_response = json_decode($dt_response);
        $dt_data     = array();
        if (!empty($dt_response->data)) {
            foreach ($dt_response->data as $key => $value) {

                $row = array();
                $label = "";
                $status = "";

                if ($value->appointment_status == "approved") {
                    $label  = "class='badge bg-success text-white'";
                    $status = $this->customlib->getSessionPrefixByType('appointment') . $value->id;
                } else if ($value->appointment_status == "pending") {
                    $label  = "class='badge bg-warning text-dark'";
                    $status = $this->lang->line($value->appointment_status);
                } else if ($value->appointment_status == "cancel") {
                    $label  = "class='badge bg-danger text-white'";
                    $status = $this->lang->line($value->appointment_status);
                }

                $action = "<div class='rowoptionview rowview-btn-top'>";
                $action .= "<a href='#' data-bs-toggle='tooltip' title='" . $this->lang->line('show') . "' class='btn btn-secondary btn-sm'   data-bs-target='#viewModal' onclick='viewDetail(" . $value->id . ")'>  <i class='fa fa-reorder'></i> </a>";
                $action .="<a href='#'  class='btn btn-secondary btn-sm' data-bs-toggle='tooltip'  onclick='printAppointment(" . $value->id .")' title='".$this->lang->line('print')."'><i class='fa fa-print'></i></a>";

                $action .= " <a href='#' data-bs-toggle='tooltip' title='" . $this->lang->line('reschedule') . "' class='btn btn-secondary btn-sm'   data-bs-target='#rescheduleModal' onclick='viewreschedule(" . $value->id . ",1)'>  <i class='fa fa-calendar'></i> </a>";

                if ($value->appointment_status == 'pending') {
                    if ($value->source != 'Online') {
                        if ($this->rbac->hasPrivilege('appointment_approve', 'can_view')) {

                            $action .= "<span class='large-tooltip'><a href='#' class='btn btn-secondary btn-sm'  data-bs-toggle='tooltip' data-bs-target='#rescheduleModal' onclick='viewreschedule(" . $value->id . ",2)' title='" . $this->lang->line('approve_appointment') . "'><i class='fa fa-check' aria-hidden='true'></i></a></span>";

                        }
                    }
                }

                $action .= "</div>";
                $first_action = "<a  href='javascript:void(0)' data-bs-toggle='tooltip'  data-bs-target='#viewModal' title=''  onclick='viewDetail(" . $value->id . ")'>";

                if (!empty($value->live_consult)) {$live_consult = $this->lang->line($value->live_consult);} else { $live_consult = '';};
				
				if ($value->gender) {
                    $gender = $this->lang->line(strtolower($value->gender));
                } else {
                    $gender = '';
                }
				
                //==============================
                $row[] = $first_action . composePatientName($value->patient_name, $value->pid) . "</a>";
                $row[] = $status;
                $row[] = $this->customlib->YYYYMMDDHisTodateFormat($value->date, $this->time_format);
                $row[] = $value->mobileno;
                $row[] = $gender;
                $row[] = composeStaffNameByString($value->name, $value->surname, $value->employee_id);
                $row[] = $this->lang->line(strtolower($value->source));
                $row[] = $value->priorityname;
                if ($this->module_lib->hasActive('live_consultation')) {
                    $row[] = $live_consult;
                }
                //====================
                if (!empty($fields)) {
                    foreach ($fields as $fields_key => $fields_value) {
                        $display_field = $value->{"$fields_value->name"};
                        if ($fields_value->type == "link") {
                            $display_field = "<a href=" . $value->{"$fields_value->name"} . " target='_blank'>" . $value->{"$fields_value->name"} . "</a>";
                        }
                        $row[] = $display_field;
                    }
                }
                //====================
			    $row[] = composeStaffNameByString($value->created_by_name, $value->created_by_surname, $value->created_by_employee_id);
                $row[]     = "<small " . $label . ">" . $this->lang->line($value->appointment_status) . "</small>";
                $dicount_amt=0;
                $dicount_amt=(($value->standard_amount*$value->discount_percentage)/100);
                $row[]     = amountFormat($value->standard_amount);
                $row[]     = amountFormat($dicount_amt)." (".$value->discount_percentage." %)";
                $row[]     = amountFormat($value->paid_amount) . $action;
                $dt_data[] = $row;
            }
        }
        $json_data = array(
            "draw"            => intval($dt_response->draw),
            "recordsTotal"    => intval($dt_response->recordsTotal),
            "recordsFiltered" => intval($dt_response->recordsFiltered),
            "data"            => $dt_data,
        );
        echo json_encode($json_data);
    }

    public function get_appointment_detail()
    {
        $id     = $this->input->get("appointment_id", TRUE);
        $result = $this->appointment_model->getDetailsAppointment($id);   
        
		if ($result['appointment_status'] == 'approved') {
			$result['appointment_no'] = $this->customlib->getSessionPrefixByType('appointment') . $id;        
        }else{
            $result['appointment_no']="";
        }

	   if($result['start_time']){
            if($this->time_format){
                $result["doctor_shift_name"]       = date('G:i:s', strtotime($result['start_time'])) ." - ". date('G:i:s', strtotime($result['end_time'])) ;
            }else{
                $result["doctor_shift_name"]       = date('g:i A', strtotime($result['start_time'])) ." - ". date('g:i A', strtotime($result['end_time'])) ;
            }  
        }else{
            $result["doctor_shift_name"]       = '';
        }        
        $result["patients_name"]       = composePatientName($result['patients_name'], $result['patient_id']);
        $result["edit_live_consult"]   = $this->lang->line($result['live_consult']);
        $result["live_consult"]        = $result['live_consult'];
        $result["date"]                = $this->customlib->YYYYMMDDHisTodateFormat($result['date'], $this->time_format);
        $result['custom_fields_value'] = display_custom_fields('appointment', $id);
        $cutom_fields_data             = get_custom_table_values($id, 'appointment');
        $result['field_data']          = $cutom_fields_data;		
		
		if ($result['patients_gender']) {
                  $result['patients_gender'] = $this->lang->line(strtolower($result['patients_gender']));
        } else {
                  $result['patients_gender'] = '';
        }         
		
        $result['amount']              = amountFormat($result['paid_amount']);
        
        if($result['payment_mode']){
            $result['payment_mode']        = $this->lang->line(strtolower($result['payment_mode']));
        }else{
            $result['payment_mode']        = '';
        }
        
        $result['cheque_no']           = $result['cheque_no'];
        if($result['cheque_date']){
            $result['cheque_date']         = $this->customlib->YYYYMMDDTodateFormat($result['cheque_date']);
        }else{
            $result['cheque_date']         =  '';
        }
		
        $result['attachment']          = $result['attachment'];
        $transaction_id                = $result['transaction_id'];
        if($result['transaction_id']!=""){
            $result['transaction_id']      =  $this->customlib->getSessionPrefixByType('transaction_id').$result['transaction_id'];
        }else{
            $result['transaction_id']="";
        }
        
        $result['department_name']     = $result['department_name'];
        $result['age']                 = $result['age'];
        $result['day']                 = $result['day'];
        $result['month']               = $result['month'];
        
        if($result['appointment_status']){
            $result['appointmentstatus']  = $this->lang->line(strtolower($result['appointment_status']));
        }else{
            $result['appointmentstatus']  =  '';
        }
        
        if($result['source']){
            $result['source']  = $this->lang->line(strtolower($result['source']));
        }else{
            $result['source']  = '';
        }      
        
        $result['patient_age']         = $this->customlib->get_patient_current_age($result['patient_id']);

        if ($result['attachment'] != "") {
            $result["doc"] = "<a href='" . site_url('admin/transaction/download/') . $transaction_id . "' class='btn btn-default btn-xs'  title=" . $this->lang->line('download') . "><i class='fa fa-download'></i></a>";
        } else {
            $result["doc"] = "";
        }	  
				
        echo json_encode($result);
    }

//=========================

    /**
     * This function is used to validate document for upload
     **/
    public function handle_doc_upload($str, $var)
    {
        $image_validate = $this->config->item('file_validate');
        if (isset($_FILES[$var]) && !empty($_FILES[$var]['name'])) {

            $file_type = $_FILES[$var]['type'];
            $file_size = $_FILES[$var]["size"];
            $file_name = $_FILES[$var]["name"];

            $allowed_extension = $image_validate["allowed_extension"];
            $allowed_mime_type = $image_validate["allowed_mime_type"];
            $ext               = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            if ($files = filesize($_FILES[$var]['tmp_name'])) {
                if (!in_array($file_type, $allowed_mime_type)) {
                    $this->form_validation->set_message('handle_doc_upload', $this->lang->line('file_type_extension_error_uploading_document'));
                    return false;
                }

                if (!in_array($ext, $allowed_extension) || !in_array($file_type, $allowed_mime_type)) {
                    $this->form_validation->set_message('handle_doc_upload', $this->lang->line('extension_error_while_uploading_document'));
                    return false;
                }
				
                if ($file_size > 2097152) {
                    $this->form_validation->set_message('handle_doc_upload', $this->lang->line('file_size_shoud_be_less_than') . "2MB");
                    return false;
                }
            } else {
                $this->form_validation->set_message('handle_doc_upload', $this->lang->line('error_while_uploading_document'));
                return false;
            }

            return true;
        }
        return true;
    }

    public function getcaseid(){
        $caseidlist = $this->casereference_model->get($this->input->get('caseid'));
        echo json_encode($caseidlist);
    }

    /**
     * Unified Service Posting page (cashier desk).
     * Prefer typed entry points: opd_service_posting / ipd_service_posting.
     */
    public function service_posting($case_id = null)
    {
        $this->load->model('bill_model');
        $this->bill_model->ensureServicePostingPermissions();

        if ($this->rbac->hasPrivilege('ipd_service_posting', 'can_view')) {
            redirect('admin/bill/ipd_service_posting' . ($case_id ? '/' . $case_id : ''));
            return;
        }
        if ($this->rbac->hasPrivilege('opd_service_posting', 'can_view')) {
            redirect('admin/bill/opd_service_posting' . ($case_id ? '/' . $case_id : ''));
            return;
        }
        access_denied();
    }

    public function opd_service_posting($case_id = null)
    {
        $this->renderServicePostingPage('opd', $case_id);
    }

    public function ipd_service_posting($case_id = null)
    {
        $this->renderServicePostingPage('ipd', $case_id);
    }

    /**
     * Render OPD or IPD Service Posting page.
     */
    private function renderServicePostingPage($type, $case_id = null)
    {
        $type = ($type === 'opd') ? 'opd' : 'ipd';
        $this->load->model('bill_model');
        $this->bill_model->ensureUnifiedBillTables();
        $this->bill_model->ensureCreditBillingSchema();
        $this->bill_model->ensureServicePostingPermissions();

        if (!$this->rbac->hasPrivilege($type . '_service_posting', 'can_view')) {
            access_denied();
            return;
        }

        $this->session->set_userdata('top_menu', 'bill');
        $perms = $this->getServicePostingPermissionFlags($type);

        $data['payment_mode'] = $this->payment_mode;
        $data['case_id']      = $case_id;
        $data['module']       = 'billing';
        $data['posting_type'] = $type;
        $data['sp_permissions'] = $perms;
        $data['title']        = ($type === 'opd')
            ? $this->lang->line('opd_service_posting')
            : $this->lang->line('ipd_service_posting');
        $data['doctors']      = $this->staff_model->getStaffbyrole(3);
        $data['organisation'] = $this->organisation_model->get();
        $data['charge_types'] = $this->getServicePostingChargeTypes();

        $this->load->view('layout/header', $data);
        $this->load->view('admin/bill/service_posting', $data);
        $this->load->view('layout/footer', $data);
    }

    private function getServicePostingPermissionFlags($type)
    {
        $type = ($type === 'opd') ? 'opd' : 'ipd';
        $prefix = $type . '_service_posting';
        return array(
            'can_view'    => $this->rbac->hasPrivilege($prefix, 'can_view'),
            'can_edit'    => $this->rbac->hasPrivilege($prefix . '_edit', 'can_view'),
            'can_save'    => $this->rbac->hasPrivilege($prefix . '_save', 'can_view'),
            'can_confirm' => $this->rbac->hasPrivilege($prefix . '_confirm', 'can_view'),
        );
    }

    /**
     * Active charge types for Service Posting service-code dropdown.
     */
    private function getServicePostingChargeTypes()
    {
        $types = $this->chargetype_model->get();
        if (empty($types)) {
            return array();
        }
        $active = array();
        foreach ($types as $row) {
            if (isset($row->is_active) && $row->is_active === 'yes') {
                $active[] = $row;
            }
        }
        usort($active, function ($a, $b) {
            return strcasecmp($a->charge_type, $b->charge_type);
        });
        return $active;
    }

    private function resolveServicePostingType($fallback = 'ipd')
    {
        $type = strtolower(trim((string) $this->input->post('posting_type')));
        if ($type === '' || $type === null) {
            $type = strtolower(trim((string) $this->input->get('posting_type')));
        }
        if (!in_array($type, array('opd', 'ipd'), true)) {
            $type = ($fallback === 'opd') ? 'opd' : 'ipd';
        }
        return $type;
    }

    /**
     * Ensure caller has the given service posting privilege. Returns false after JSON error when $json.
     */
    private function requireServicePostingPrivilege($type, $action = 'view', $json = true)
    {
        $type = ($type === 'opd') ? 'opd' : 'ipd';
        $code = $type . '_service_posting';
        if ($action !== 'view' && $action !== '') {
            $code .= '_' . $action;
        }
        if ($this->rbac->hasPrivilege($code, 'can_view')) {
            return true;
        }
        if ($json) {
            echo json_encode(array('status' => 0, 'message' => $this->lang->line('access_denied')));
            return false;
        }
        access_denied();
        return false;
    }

    /**
     * Verify case has OPD or IPD matching posting type.
     */
    private function caseMatchesPostingType($case_id, $type)
    {
        $case_id = (int) $case_id;
        if ($case_id < 1) {
            return false;
        }
        if ($type === 'ipd') {
            $ipd = $this->patient_model->getDetailsipdByCaseId($case_id);
            return !empty($ipd['ipdid']);
        }
        $opd = $this->patient_model->getDetailsopdByCaseId($case_id);
        return !empty($opd);
    }

    /**
     * Resolve Case ID from case / IP / MR and return patient + lines.
     */
    public function load_service_posting()
    {
        $this->load->model(array('bill_model', 'indent_model', 'transaction_model'));
        $this->bill_model->ensureServicePostingPermissions();

        $posting_type = $this->resolveServicePostingType('ipd');
        if (!$this->requireServicePostingPrivilege($posting_type, 'view', true)) {
            return;
        }

        $case_id = $this->bill_model->resolveCaseReference(
            $this->input->post('case_id'),
            $this->input->post('ip_number'),
            $this->input->post('mr_number')
        );

        if ($case_id < 1) {
            echo json_encode(array('status' => 0, 'message' => $this->lang->line('no_record_found')));
            return;
        }

        if (!$this->caseMatchesPostingType($case_id, $posting_type)) {
            $msg = ($posting_type === 'ipd')
                ? $this->lang->line('case_not_ipd_service_posting')
                : $this->lang->line('case_not_opd_service_posting');
            if ($msg === 'case_not_ipd_service_posting' || $msg === 'case_not_opd_service_posting') {
                $msg = ($posting_type === 'ipd')
                    ? 'This case has no IPD admission. Use OPD Service Posting.'
                    : 'This case has no OPD visit. Use IPD Service Posting.';
            }
            echo json_encode(array('status' => 0, 'message' => $msg));
            return;
        }

        $patient = $this->patient_model->getDetailsByCaseId($case_id, $posting_type === 'ipd' ? 'ipd' : null);
        if (empty($patient['patient_id'])) {
            echo json_encode(array('status' => 0, 'message' => $this->lang->line('no_record_found')));
            return;
        }

        $lines = $this->bill_model->getServicePostingLines($case_id);
        $confirmation = $this->bill_model->getLatestConfirmationByCaseId($case_id);
        $draft = $this->bill_model->getDraftConfirmationByCaseId($case_id);
        $is_confirmed = $this->bill_model->isCaseBillConfirmed($case_id);
        $finance = $this->bill_model->getServicePostingFinancials($case_id, $lines);
        $advance = $finance['advance_amount'];

        $ipd = $this->patient_model->getDetailsipdByCaseId($case_id);
        $patient['patient_unique_id'] = '';
        $prow = $this->db->select('patient_unique_id,insurance_id,insurance_validity,organisation_id')->where('id', (int) $patient['patient_id'])->get('patients')->row_array();
        if (!empty($prow['patient_unique_id'])) {
            $patient['patient_unique_id'] = $prow['patient_unique_id'];
        }
        if (isset($prow['insurance_id'])) {
            $patient['insurance_id'] = $prow['insurance_id'];
        }
        if (isset($prow['insurance_validity'])) {
            $patient['insurance_validity'] = $prow['insurance_validity'];
        }
        if (isset($prow['organisation_id'])) {
            $patient['organisation_id'] = $prow['organisation_id'];
        }

        // Prefer IPD doctor / reporting doctor fields when IPD exists on the case
        if (!empty($ipd['ipdid'])) {
            $patient['ipdid'] = $ipd['ipdid'];
            $patient['bed_name'] = isset($ipd['bed_name']) ? $ipd['bed_name'] : (isset($patient['bed_name']) ? $patient['bed_name'] : '');
            $patient['bed_group_id'] = isset($ipd['bed_group_id']) ? $ipd['bed_group_id'] : (isset($patient['bed_group_id']) ? $patient['bed_group_id'] : '');
            $patient['bedgroup_name'] = isset($ipd['bedgroup_name']) ? $ipd['bedgroup_name'] : (isset($patient['bedgroup_name']) ? $patient['bedgroup_name'] : '');
            $patient['floor_name'] = isset($ipd['floor_name']) ? $ipd['floor_name'] : (isset($patient['floor_name']) ? $patient['floor_name'] : '');
            $patient['cons_doctor'] = isset($ipd['cons_doctor']) ? $ipd['cons_doctor'] : '';
            $patient['doctor_name'] = isset($ipd['doctor_name']) ? $ipd['doctor_name'] : '';
            $patient['doctor_surname'] = isset($ipd['doctor_surname']) ? $ipd['doctor_surname'] : '';
            $patient['doctor_employee_id'] = isset($ipd['doctor_employee_id']) ? $ipd['doctor_employee_id'] : '';
            $patient['reporting_doctor'] = isset($ipd['reporting_doctor']) ? $ipd['reporting_doctor'] : '';
            $patient['reporting_doctor_name'] = isset($ipd['reporting_doctor_name']) ? $ipd['reporting_doctor_name'] : '';
            $patient['reporting_doctor_surname'] = isset($ipd['reporting_doctor_surname']) ? $ipd['reporting_doctor_surname'] : '';
            $patient['reporting_doctor_employee_id'] = isset($ipd['reporting_doctor_employee_id']) ? $ipd['reporting_doctor_employee_id'] : '';
            $patient['department_name'] = isset($ipd['department_name']) ? $ipd['department_name'] : '';
            $patient['discharge_date'] = isset($ipd['discharge_date']) ? $ipd['discharge_date'] : '';
            $patient['discharged'] = isset($ipd['discharged']) ? $ipd['discharged'] : (isset($patient['discharged']) ? $patient['discharged'] : '');
            $patient['patient_type'] = !empty($ipd['patient_type']) ? $ipd['patient_type'] : (isset($patient['patient_type']) ? $patient['patient_type'] : '');
            $patient['case_type'] = !empty($ipd['case_type']) ? $ipd['case_type'] : (isset($patient['case_type']) ? $patient['case_type'] : '');
            if (!empty($ipd['organisation_name'])) {
                $patient['organisation_name'] = $ipd['organisation_name'];
            }
            if (!empty($ipd['organisation_id'])) {
                $patient['organisation_id'] = $ipd['organisation_id'];
            }
            if (!empty($ipd['insurance_id'])) {
                $patient['insurance_id'] = $ipd['insurance_id'];
            }
            if (!empty($ipd['date'])) {
                $patient['date'] = $ipd['date'];
            }
            if (!empty($ipd['mobileno'])) {
                $patient['mobileno'] = $ipd['mobileno'];
            }
            if (!empty($ipd['address'])) {
                $patient['address'] = $ipd['address'];
            }
            if (!empty($ipd['gender'])) {
                $patient['gender'] = $ipd['gender'];
            }
        }

        $discharge_display = '';
        if (!empty($patient['discharge_date']) && $patient['discharge_date'] != '0000-00-00' && $patient['discharge_date'] != '0000-00-00 00:00:00') {
            $discharge_display = $this->customlib->YYYYMMDDHisTodateFormat($patient['discharge_date'], $this->customlib->getHospitalTimeFormat());
        }
        $patient['discharge_date_display'] = $discharge_display;

        $ipd_id = !empty($ipd['ipdid']) ? $ipd['ipdid'] : '';
        $ipd_no = $ipd_id !== '' ? ($this->customlib->getSessionPrefixByType('ipd_no') . $ipd_id) : '';

        $mapped_charge_type = '';
        if ($posting_type === 'ipd') {
            $bed_group_id = 0;
            if (!empty($ipd['bed_group_id'])) {
                $bed_group_id = (int) $ipd['bed_group_id'];
            } elseif (!empty($patient['bed_group_id'])) {
                $bed_group_id = (int) $patient['bed_group_id'];
            }
            if ($bed_group_id > 0) {
                $mapped_charge_type = $this->bedgroup_model->getChargeTypeByBedGroupId($bed_group_id);
            }
        }

        echo json_encode(array(
            'status'         => 1,
            'case_id'        => $case_id,
            'posting_type'   => $posting_type,
            'patient'        => $patient,
            'ipd_id'         => $ipd_id,
            'ipd_no'         => $ipd_no,
            'mapped_charge_type' => $mapped_charge_type,
            'lines'          => $lines,
            'is_confirmed'   => $is_confirmed ? 1 : 0,
            'confirmation'   => $confirmation,
            'draft'          => $draft,
            'remarks'        => !empty($draft['remarks']) ? $draft['remarks'] : (!empty($confirmation['remarks']) ? $confirmation['remarks'] : ''),
            'advance_amount' => $advance,
            'permissions'    => $this->getServicePostingPermissionFlags($posting_type),
            'totals'         => array(
                'total_amount'             => $finance['total_amount'],
                'line_discount'            => $finance['line_discount'],
                'line_net'                 => $finance['line_net'],
                'overall_discount_percent' => $finance['overall_discount_percent'],
                'overall_discount_amount'  => $finance['overall_discount_amount'],
                'overall_discount_mode'    => $finance['overall_discount_mode'],
                'total_discount'           => $finance['total_discount'],
                'total_net'                => $finance['total_net'],
                'total_paid'               => $finance['total_paid'],
                'total_refund'             => $finance['total_refund'],
                'balance'                  => $finance['balance'],
            ),
        ));
    }

    public function search_service_items()
    {
        $posting_type = $this->resolveServicePostingType('ipd');
        if (!$this->requireServicePostingPrivilege($posting_type, 'view', true)) {
            return;
        }
        $this->load->model('bill_model');
        $service_code = $this->input->get_post('service_code');
        $term         = $this->input->get_post('term');
        $list         = $this->bill_model->searchServiceItems($service_code, $term);
        echo json_encode(array('status' => 1, 'result' => $list));
    }

    public function add_service_line()
    {
        $this->load->model('bill_model');
        $posting_type = $this->resolveServicePostingType('ipd');
        if (!$this->requireServicePostingPrivilege($posting_type, 'edit', true)) {
            return;
        }
        $case_id = (int) $this->input->post('case_id');
        if ($this->bill_model->isCaseBillConfirmed($case_id)) {
            echo json_encode(array('status' => 0, 'message' => $this->lang->line('bill_already_confirmed')));
            return;
        }

        $service_code = strtolower(trim($this->input->post('service_code')));
        $item_id      = (int) $this->input->post('item_id');
        $qty          = (float) $this->input->post('qty');
        $rate         = $this->input->post('rate');
        $discount_pct = (float) $this->input->post('discount_percentage');
        $note         = $this->input->post('remarks');
        $reporting_doctor = $this->input->post('reporting_doctor');
        $service_date = $this->parsePostedServiceDate($this->input->post('service_date'));
        $patient      = $this->patient_model->getDetailsByCaseId($case_id);

        if ($case_id < 1 || empty($patient['patient_id']) || $item_id < 1) {
            echo json_encode(array('status' => 0, 'message' => $this->lang->line('invalid_input')));
            return;
        }

        if ($qty <= 0) {
            $qty = 1;
        }

        $staff_id = $this->customlib->getLoggedInUserID();
        $created_module = null;
        $created_id = null;

        if ($service_code === 'pharmacy') {
            $result = $this->bill_model->createPharmacyBillLine(
                $case_id,
                $patient['patient_id'],
                $item_id,
                $staff_id,
                $qty,
                $rate,
                $discount_pct,
                $note,
                $service_date
            );
            if (!empty($result['status'])) {
                $created_id = !empty($result['module_id']) ? $result['module_id'] : null;
                if ($created_id) {
                    $this->bill_model->setLineReportingDoctor($case_id, 'pharmacy', $created_id, $reporting_doctor);
                }
            }
            echo json_encode($result['status']
                ? array('status' => 1, 'message' => $this->lang->line('record_saved_successfully'), 'result' => $result)
                : array('status' => 0, 'message' => $result['message']));
            return;
        }

        if ($this->bill_model->isChargeTypeServiceCode($service_code)) {
            $_POST['module'] = $posting_type;
            $_POST['charge_id'] = $item_id;
            $_POST['qty'] = $qty;
            $_POST['apply_charge'] = ($rate !== null && $rate !== '') ? (float) $rate : 0;
            $_POST['discount_percentage'] = $discount_pct;
            $_POST['note'] = $note;
            $_POST['case_id'] = $case_id;
            $_POST['reporting_doctor'] = $reporting_doctor;
            $_POST['service_date'] = $service_date;

            if ($_POST['apply_charge'] <= 0) {
                $charge = $this->charge_model->getDetails($item_id, '');
                if (empty($charge)) {
                    echo json_encode(array('status' => 0, 'message' => $this->lang->line('no_record_found')));
                    return;
                }
                $_POST['apply_charge'] = (float) $charge->standard_charge;
            }

            // Capture output from add_bill_item to attach reporting doctor after insert
            ob_start();
            $this->add_bill_item();
            $raw = ob_get_clean();
            $decoded = json_decode($raw, true);
            if (!empty($decoded['status']) && (int) $decoded['status'] === 1) {
                // Reload latest line for this module to get module_id
                $lines = $this->bill_model->getServicePostingLines($case_id);
                $latest = null;
                foreach ($lines as $line) {
                    if ($line['module'] === $posting_type) {
                        $latest = $line;
                    }
                }
                if ($latest) {
                    $this->bill_model->setLineReportingDoctor($case_id, $posting_type, $latest['module_id'], $reporting_doctor);
                }
            }
            echo $raw;
            return;
        }

        echo json_encode(array('status' => 0, 'message' => $this->lang->line('invalid_input')));
    }

    public function update_service_line()
    {
        $this->load->model('bill_model');
        $posting_type = $this->resolveServicePostingType('ipd');
        if (!$this->requireServicePostingPrivilege($posting_type, 'edit', true)) {
            return;
        }
        $case_id = (int) $this->input->post('case_id');
        if ($this->bill_model->isCaseBillConfirmed($case_id)) {
            echo json_encode(array('status' => 0, 'message' => $this->lang->line('bill_already_confirmed')));
            return;
        }

        $module = $this->input->post('module');
        $id     = (int) $this->input->post('id');
        $reporting_doctor = $this->input->post('reporting_doctor');

        // Always persist per-line reporting doctor (even if only doctor changed)
        if ($module && $id > 0) {
            $this->bill_model->setLineReportingDoctor($case_id, $module, $id, $reporting_doctor);
        }

        if ($module === 'pharmacy') {
            $result = $this->bill_model->updatePharmacyBillLine(
                $id,
                $this->input->post('qty'),
                $this->input->post('apply_charge'),
                $this->input->post('discount_percentage'),
                $this->input->post('remarks')
            );
            echo json_encode($result['status']
                ? array('status' => 1, 'message' => $this->lang->line('success_message'), 'amount' => isset($result['amount']) ? $result['amount'] : null)
                : array('status' => 0, 'message' => $result['message']));
            return;
        }

        // For OPD/IPD charges (and other modules handled by update_bill_item)
        if ($module === 'opd' || $module === 'ipd') {
            $this->update_bill_item();
            return;
        }

        // Pathology / Radiology: reporting doctor already saved above
        echo json_encode(array('status' => 1, 'message' => $this->lang->line('success_message')));
    }

    public function delete_service_line()
    {
        $this->load->model('bill_model');
        $posting_type = $this->resolveServicePostingType('ipd');
        if (!$this->requireServicePostingPrivilege($posting_type, 'edit', true)) {
            return;
        }
        $case_id = (int) $this->input->post('case_id');
        if ($this->bill_model->isCaseBillConfirmed($case_id)) {
            echo json_encode(array('status' => 0, 'message' => $this->lang->line('bill_already_confirmed')));
            return;
        }

        $module = $this->input->post('module');
        $id     = (int) $this->input->post('id');
        if ($module === 'pharmacy') {
            $result = $this->bill_model->deletePharmacyBillLine($id);
            if (!empty($result['status'])) {
                $this->bill_model->deleteLineReportingDoctor($case_id, 'pharmacy', $id);
            }
            echo json_encode($result['status']
                ? array('status' => 1, 'message' => $this->lang->line('delete_message'))
                : array('status' => 0, 'message' => $result['message']));
            return;
        }

        ob_start();
        $this->delete_bill_item();
        $raw = ob_get_clean();
        $decoded = json_decode($raw, true);
        if (!empty($decoded['status']) && ((int) $decoded['status'] === 1 || $decoded['status'] === '1')) {
            $this->bill_model->deleteLineReportingDoctor($case_id, $module, $id);
        }
        echo $raw;
    }

    public function save_service_bill()
    {
        $this->load->model('bill_model');
        $posting_type = $this->resolveServicePostingType('ipd');
        if (!$this->requireServicePostingPrivilege($posting_type, 'save', true)) {
            return;
        }
        $case_id = (int) $this->input->post('case_id');
        $remarks = $this->input->post('remarks');
        $overall_percent = $this->input->post('overall_discount_percent');
        $overall_amount = $this->input->post('overall_discount_amount');
        $overall_mode = $this->input->post('overall_discount_mode');

        if ($case_id < 1) {
            echo json_encode(array('status' => 0, 'message' => $this->lang->line('invalid_input')));
            return;
        }

        $lines = $this->bill_model->getServicePostingLines($case_id);
        $result = $this->bill_model->saveDraftUnifiedBill(
            $case_id,
            $this->customlib->getLoggedInUserID(),
            $lines,
            $remarks,
            $overall_percent,
            $overall_amount,
            $overall_mode
        );

        if (empty($result['status'])) {
            echo json_encode(array('status' => 0, 'message' => $result['message']));
            return;
        }

        echo json_encode(array(
            'status'          => 1,
            'message'         => $this->lang->line('bill_saved_successfully'),
            'confirmation_id' => $result['confirmation_id'],
            'saved_at'        => $result['saved_at'],
            'line_count'      => $result['line_count'],
            'total_net'       => $result['total_net'],
        ));
    }

    public function confirm_service_bill()
    {
        $this->load->model('bill_model');
        $posting_type = $this->resolveServicePostingType('ipd');
        if (!$this->requireServicePostingPrivilege($posting_type, 'confirm', true)) {
            return;
        }
        $case_id = (int) $this->input->post('case_id');
        $remarks = $this->input->post('remarks');
        $overall_percent = $this->input->post('overall_discount_percent');
        $overall_amount = $this->input->post('overall_discount_amount');
        $overall_mode = $this->input->post('overall_discount_mode');

        if ($case_id < 1) {
            echo json_encode(array('status' => 0, 'message' => $this->lang->line('invalid_input')));
            return;
        }

        $lines = $this->bill_model->getServicePostingLines($case_id);
        if (empty($lines)) {
            echo json_encode(array('status' => 0, 'message' => $this->lang->line('no_record_found')));
            return;
        }

        $result = $this->bill_model->confirmUnifiedBill(
            $case_id,
            $this->customlib->getLoggedInUserID(),
            $lines,
            $remarks,
            $overall_percent,
            $overall_amount,
            $overall_mode
        );

        if (empty($result['status'])) {
            echo json_encode(array('status' => 0, 'message' => $result['message']));
            return;
        }

        $finance = $this->bill_model->getServicePostingFinancials($case_id, $lines);

        echo json_encode(array(
            'status'          => 1,
            'message'         => $this->lang->line('bill_confirmed_successfully'),
            'confirmation_id' => $result['confirmation_id'],
            'total_net'       => $finance['total_net'],
            'total_amount'    => $finance['total_amount'],
            'total_discount'  => $finance['total_discount'],
            'advance_amount'  => $finance['advance_amount'],
            'balance'         => $finance['balance'],
        ));
        return;
    }

    public function reopen_service_bill()
    {
        $this->load->model('bill_model');
        $posting_type = $this->resolveServicePostingType('ipd');
        // Reopen requires edit permission
        if (!$this->requireServicePostingPrivilege($posting_type, 'edit', true)) {
            return;
        }
        $case_id = (int) $this->input->post('case_id');
        $result  = $this->bill_model->reopenUnifiedBill($case_id, $this->customlib->getLoggedInUserID());
        echo json_encode(array(
            'status'  => !empty($result['status']) ? 1 : 0,
            'message' => $result['message'],
        ));
    }

    /**
     * Record payment after Service Posting confirmation (Cash / UPI / Credit / Insurance / etc).
     */
    public function service_posting_payment()
    {
        $this->load->model('bill_model');
        $this->bill_model->ensureCreditBillingSchema();

        $posting_type = $this->resolveServicePostingType('ipd');
        if (!$this->requireServicePostingPrivilege($posting_type, 'view', true)) {
            return;
        }

        $case_id = (int) $this->input->post('case_reference_id');
        if ($case_id < 1) {
            echo json_encode(array('status' => 'fail', 'error' => array('case_id' => $this->lang->line('invalid_input')), 'message' => ''));
            return;
        }

        if (!$this->bill_model->isCaseBillConfirmed($case_id)) {
            echo json_encode(array('status' => 'fail', 'error' => array('confirm' => $this->lang->line('confirm_bill_first')), 'message' => ''));
            return;
        }

        $payment_mode = $this->input->post('payment_mode', true);
        $this->form_validation->set_rules('amount', $this->lang->line('amount'), 'trim|required|xss_clean');
        $this->form_validation->set_rules('payment_date', $this->lang->line('payment_date'), 'trim|required|xss_clean');
        $this->form_validation->set_rules('payment_mode', $this->lang->line('payment_mode'), 'trim|required|xss_clean');
        if ($payment_mode === 'Credit') {
            $this->form_validation->set_rules('credit_doctor_id', $this->lang->line('credit_doctor'), 'trim|required|xss_clean');
        }
        if ($payment_mode === 'Insurance') {
            $this->form_validation->set_rules('organisation_id', $this->lang->line('tpa'), 'trim|required|xss_clean');
            $this->form_validation->set_rules('insurance_id', $this->lang->line('insurance_id'), 'trim|required|xss_clean');
        }

        if ($this->form_validation->run() == false) {
            echo json_encode(array(
                'status'  => 'fail',
                'error'   => array(
                    'amount'            => form_error('amount'),
                    'payment_mode'      => form_error('payment_mode'),
                    'payment_date'      => form_error('payment_date'),
                    'credit_doctor_id'  => form_error('credit_doctor_id'),
                    'organisation_id'   => form_error('organisation_id'),
                    'insurance_id'      => form_error('insurance_id'),
                ),
                'message' => '',
            ));
            return;
        }

        $patient = $this->patient_model->getDetailsByCaseId($case_id);
        if (empty($patient['patient_id'])) {
            echo json_encode(array('status' => 'fail', 'error' => array('patient' => $this->lang->line('no_record_found')), 'message' => ''));
            return;
        }

        // Prefer IPD linkage when present on case
        $ipd = $this->patient_model->getDetailsipdByCaseId($case_id);
        if (!empty($ipd['ipdid'])) {
            $patient['ipdid'] = $ipd['ipdid'];
        }

        if ($payment_mode === 'Insurance') {
            $organisation_id = (int) $this->input->post('organisation_id', true);
            $insurance_id    = trim($this->input->post('insurance_id', true));
            $this->db->where('id', (int) $patient['patient_id'])->update('patients', array(
                'organisation_id' => $organisation_id > 0 ? $organisation_id : null,
                'insurance_id'    => $insurance_id,
            ));
        }

        $payment_type = $this->input->post('payment_type');
        $amount_type  = ($payment_type === 'advance') ? 'advance' : null;
        $payment_date = $this->customlib->dateFormatToYYYYMMDDHis($this->input->post('payment_date', true), $this->time_format);

        $data = array(
            'case_reference_id' => $case_id,
            'amount'            => $this->input->post('amount', true),
            'type'              => 'payment',
            'amount_type'       => $amount_type,
            'payment_mode'      => $payment_mode,
            'note'              => $this->input->post('note', true),
            'payment_date'      => $payment_date,
            'received_by'       => $this->customlib->getLoggedInUserID(),
            'patient_id'        => $patient['patient_id'],
            'section'           => 'Service Posting',
        );

        if ($payment_mode === 'Credit') {
            $data['credit_doctor_id'] = (int) $this->input->post('credit_doctor_id', true);
        }

        if (!empty($patient['ipdid'])) {
            $data['ipd_id'] = $patient['ipdid'];
        } elseif (!empty($patient['opdid'])) {
            $data['opd_id'] = $patient['opdid'];
        }

        $this->transaction_model->add($data);
        echo json_encode(array('status' => 'success', 'error' => '', 'message' => $this->lang->line('record_saved_successfully')));
    }

    /**
     * Download categorized inpatient final bill as PDF.
     */
    public function download_patient_bill_pdf($case_id = null)
    {
        $case_id = $case_id ? (int) $case_id : (int) $this->input->get_post('case_id');
        if ($case_id < 1) {
            show_error($this->lang->line('invalid_input'));
            return;
        }

        $this->load->model('bill_model');
        $this->bill_model->ensureCreditBillingSchema();

        $print_details         = $this->printing_model->get('', 'bill');
        $data['print_details'] = $print_details;
        $data['case_id']       = $case_id;
        $data['patient']       = $this->enrichPatientBillDetails($case_id);
        $data['charge_details'] = $this->transaction_model->get_ipdopdchargebycaseId($case_id);
        $data['pharmacy_medicines'] = $this->getPharmacyMedicinesByCaseId($case_id);
        $data['pathology_tests'] = $this->getPathologyTestsByCaseId($case_id);
        $data['paid_amount']   = $this->transaction_model->ipdopdPaymentByCaseId($case_id);
        $data['advance_amount'] = $this->transaction_model->getAdvanceTotalByCaseId($case_id);
        $data['transaction_data'] = $this->transaction_model->getTransactionByCaseId($case_id);
        $refund = $this->transaction_model->getopdIpdrefundbyCaseId($case_id);
        $data['refund'] = !empty($refund) ? $refund['amount'] : 0;
        $data['hospital'] = $this->setting_model->getSetting();
        $data['printed_by'] = $this->customlib->getAdminSessionUserName();
        $data['overall_discount'] = $this->bill_model->getOverallDiscountForCase($case_id);

        $posting_type = strtolower(trim((string) $this->input->get('posting_type')));
        $bill_presentation = $this->getPatientBillPresentationData($data['patient'], $case_id, $posting_type);
        $data = array_merge($data, $bill_presentation);

        $html = $this->load->view('admin/bill/_pdf_inpatient_final_bill', $data, true);

        require_once APPPATH . 'third_party/omnipay/vendor/autoload.php';
        try {
            $mpdf = new \Mpdf\Mpdf(array(
                'mode'          => 'utf-8',
                'format'        => 'A4',
                'margin_left'   => 10,
                'margin_right'  => 10,
                'margin_top'    => 40,
                'margin_bottom' => 24,
            ));
            $mpdf->SetTitle($this->customlib->getAppName());
            $mpdf->WriteHTML($html);
            $mpdf->Output($bill_presentation['pdf_filename'], \Mpdf\Output\Destination::INLINE);
        } catch (Exception $e) {
            log_message('error', 'Final bill PDF failed: ' . $e->getMessage());
            show_error($this->lang->line('error_occurred_please_try_again'));
        }
    }

    /**
     * Merge IPD admission fields onto case patient details for billing print/PDF.
     */
    private function enrichPatientBillDetails($case_id)
    {
        $case_id = (int) $case_id;
        $patient = $this->patient_model->getDetailsByCaseId($case_id, 'ipd');
        if (empty($patient['patient_id'])) {
            $patient = $this->patient_model->getDetailsByCaseId($case_id);
        }

        $ipd = $this->patient_model->getDetailsipdByCaseId($case_id);
        if (!empty($ipd['ipdid'])) {
            $merge_keys = array(
                'ipdid', 'bed_name', 'bed_id', 'bedgroup_name', 'floor_name', 'cons_doctor',
                'doctor_name', 'doctor_surname', 'doctor_employee_id', 'reporting_doctor',
                'reporting_doctor_name', 'reporting_doctor_surname', 'reporting_doctor_employee_id',
                'department_name', 'discharge_date', 'discharged', 'date', 'patient_type',
                'case_type', 'organisation_name', 'organisation_id', 'insurance_id',
                'insurance_validity', 'patient_unique_id', 'mobileno', 'address', 'gender',
                'credit_limit', 'case_reference_id', 'refference',
            );
            foreach ($merge_keys as $key) {
                if (isset($ipd[$key]) && ($ipd[$key] !== '' && $ipd[$key] !== null)) {
                    $patient[$key] = $ipd[$key];
                }
            }
            if (empty($patient['appointment_date']) && !empty($ipd['date'])) {
                $patient['appointment_date'] = $ipd['date'];
            }
        }

        if (empty($patient['patient_unique_id']) && !empty($patient['patient_id'])) {
            $prow = $this->db->select('patient_unique_id,insurance_id,organisation_id')->where('id', (int) $patient['patient_id'])->get('patients')->row_array();
            if (!empty($prow['patient_unique_id'])) {
                $patient['patient_unique_id'] = $prow['patient_unique_id'];
            }
            if (empty($patient['insurance_id']) && !empty($prow['insurance_id'])) {
                $patient['insurance_id'] = $prow['insurance_id'];
            }
            if (empty($patient['organisation_id']) && !empty($prow['organisation_id'])) {
                $patient['organisation_id'] = $prow['organisation_id'];
            }
        }

        return $patient;
    }

    /**
     * Bill title and filename for patient print/PDF (IPD vs OPD).
     */
    private function getPatientBillPresentationData($patient, $case_id = 0, $posting_type = null)
    {
        $patient = is_array($patient) ? $patient : array();
        $posting_type = strtolower(trim((string) $posting_type));
        if ($posting_type === 'opd') {
            $is_ipd = false;
        } elseif ($posting_type === 'ipd') {
            $is_ipd = true;
        } else {
            $is_ipd = !empty($patient['ipdid']) && (int) $patient['ipdid'] > 0;
        }
        $case_id = (int) $case_id;
        if ($case_id < 1 && !empty($patient['case_reference_id'])) {
            $case_id = (int) $patient['case_reference_id'];
        }

        return array(
            'is_ipd_bill'  => $is_ipd,
            'bill_title'   => $is_ipd ? $this->lang->line('inpatient_bill') : $this->lang->line('opd_bill'),
            'pdf_filename' => ($is_ipd ? 'Inpatient' : 'OPD') . '_Final_Bill_' . $case_id . '.pdf',
        );
    }

    /**
     * Flatten pathology bill tests for a case (test/service name rows, not bill numbers).
     */
    private function getPathologyTestsByCaseId($case_id)
    {
        $case_id = (int) $case_id;
        if ($case_id < 1) {
            return array();
        }

        $rows = $this->db->select(
            'pathology_billing.id as bill_id, pathology_billing.date as bill_date,
             pathology_billing.discount_percentage,
             pathology_report.apply_charge, pathology_report.tax_percentage,
             pathology.test_name'
        )
            ->from('pathology_billing')
            ->join('pathology_report', 'pathology_report.pathology_bill_id = pathology_billing.id', 'inner')
            ->join('pathology', 'pathology.id = pathology_report.pathology_id', 'left')
            ->where('pathology_billing.case_reference_id', $case_id)
            ->order_by('pathology_billing.date', 'asc')
            ->order_by('pathology_report.id', 'asc')
            ->get()
            ->result_array();

        return $rows ? $rows : array();
    }

    /**
     * Flatten pharmacy bill medicines for a case (medicine name rows, not bill numbers).
     */
    private function getPharmacyMedicinesByCaseId($case_id)
    {
        $case_id = (int) $case_id;
        if ($case_id < 1) {
            return array();
        }

        $rows = $this->db->select(
            'pharmacy_bill_basic.id as bill_id, pharmacy_bill_basic.date as bill_date,
             pharmacy_bill_detail.quantity, pharmacy_bill_detail.sale_price, pharmacy_bill_detail.amount,
             medicine_batch_details.tax as batch_tax,
             pharmacy.medicine_name'
        )
            ->from('pharmacy_bill_basic')
            ->join('pharmacy_bill_detail', 'pharmacy_bill_detail.pharmacy_bill_basic_id = pharmacy_bill_basic.id', 'inner')
            ->join('medicine_batch_details', 'medicine_batch_details.id = pharmacy_bill_detail.medicine_batch_detail_id', 'left')
            ->join('pharmacy', 'pharmacy.id = medicine_batch_details.pharmacy_id', 'left')
            ->where('pharmacy_bill_basic.case_reference_id', $case_id)
            ->order_by('pharmacy_bill_basic.date', 'asc')
            ->order_by('pharmacy_bill_detail.id', 'asc')
            ->get()
            ->result_array();

        return $rows ? $rows : array();
    }

    /**
     * Admin list of Credit payment bills.
     */
    public function credit_bills()
    {
        if (!$this->rbac->hasPrivilege('opd_ipd_billing_through_case_id', 'can_view') && !$this->rbac->hasPrivilege('bill', 'can_view')) {
            // Fall through with top menu; still allow if billing accessible
        }
        $this->session->set_userdata('top_menu', 'bill');
        $this->load->model('bill_model');
        $this->bill_model->ensureCreditBillingSchema();

        $status = $this->input->get('status');
        if (!in_array($status, array('outstanding', 'settled', 'all'), true)) {
            $status = 'outstanding';
        }

        $data['status']            = $status;
        $data['credit_bills']      = $this->transaction_model->getCreditBills($status);
        $data['total_to_collect']  = $this->transaction_model->getCreditOutstandingTotal();
        $data['currency_symbol']   = $this->customlib->getHospitalCurrencyFormat();
        $this->load->view('layout/header');
        $this->load->view('admin/bill/credit_bills', $data);
        $this->load->view('layout/footer');
    }

    /**
     * Update reporting doctor for IPD linked to a case (Service Posting).
     */
    public function update_reporting_doctor()
    {
        $this->load->model('bill_model');
        $this->bill_model->ensureCreditBillingSchema();

        $case_id = (int) $this->input->post('case_reference_id');
        $reporting_doctor = $this->input->post('reporting_doctor');
        $reporting_doctor = ($reporting_doctor === '' || $reporting_doctor === null) ? null : (int) $reporting_doctor;

        if ($case_id < 1) {
            echo json_encode(array('status' => 'fail', 'message' => $this->lang->line('invalid_input')));
            return;
        }

        $ipd = $this->patient_model->getDetailsipdByCaseId($case_id);
        if (empty($ipd['ipdid'])) {
            echo json_encode(array('status' => 'fail', 'message' => $this->lang->line('no_record_found')));
            return;
        }

        $this->db->where('id', (int) $ipd['ipdid'])->update('ipd_details', array(
            'reporting_doctor' => $reporting_doctor,
        ));

        echo json_encode(array('status' => 'success', 'message' => $this->lang->line('record_saved_successfully')));
    }

    /**
     * Settle a Credit transaction to Cash.
     */
    public function settle_credit_payment()
    {
        $this->load->model('bill_model');
        $this->bill_model->ensureCreditBillingSchema();

        $transaction_id = (int) $this->input->post('transaction_id');
        if ($transaction_id < 1) {
            echo json_encode(array('status' => 'fail', 'message' => $this->lang->line('invalid_input')));
            return;
        }

        $ok = $this->transaction_model->settleCreditPayment($transaction_id, $this->customlib->getLoggedInUserID());
        if ($ok) {
            echo json_encode(array('status' => 'success', 'message' => $this->lang->line('credit_settled_successfully')));
        } else {
            echo json_encode(array('status' => 'fail', 'message' => $this->lang->line('no_record_found')));
        }
    }

    /**
     * Convert posted hospital-format service date to Y-m-d H:i:s.
     */
    private function parsePostedServiceDate($posted)
    {
        $posted = trim((string) $posted);
        if ($posted === '' || $posted === 'false') {
            return date('Y-m-d H:i:s');
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $posted)) {
            return $posted;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $posted)) {
            return $posted . ':00';
        }

        $parsed = $this->customlib->dateFormatToYYYYMMDDHis($posted, $this->time_format);
        if (!empty($parsed) && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $parsed) && strpos($parsed, '0000') !== 0) {
            return $parsed;
        }

        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $posted, $m)) {
            return $m[1] . ' ' . date('H:i:s');
        }

        return date('Y-m-d H:i:s');
    }

}