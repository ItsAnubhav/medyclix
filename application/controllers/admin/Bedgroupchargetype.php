<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Bedgroupchargetype extends Admin_Controller
{
    public function index()
    {
        if (!$this->rbac->hasPrivilege('charge_type', 'can_view')) {
            access_denied();
        }
        $this->session->set_userdata('top_menu', 'setup');
        $this->session->set_userdata('sub_sidebar_menu', 'admin/bedgroupchargetype/index');
        $this->session->set_userdata('sub_menu', 'charges/index');

        $charge_types = $this->chargetype_model->get();
        $active = array();
        if (!empty($charge_types)) {
            foreach ($charge_types as $row) {
                if (isset($row->is_active) && $row->is_active === 'yes') {
                    $active[] = $row;
                }
            }
            usort($active, function ($a, $b) {
                return strcasecmp($a->charge_type, $b->charge_type);
            });
        }

        $data['charge_types'] = $active;
        $data['bed_groups']    = $this->bedgroup_model->getBedGroupChargeTypeMappings();

        $this->load->view('layout/header');
        $this->load->view('admin/charges/bedGroupChargeType', $data);
        $this->load->view('layout/footer');
    }

    public function save()
    {
        if (!$this->rbac->hasPrivilege('charge_type', 'can_edit') && !$this->rbac->hasPrivilege('charge_type', 'can_add')) {
            echo json_encode(array('status' => 'fail', 'message' => $this->lang->line('access_denied')));
            return;
        }

        $mappings = $this->input->post('charge_type_id');
        $this->bedgroup_model->saveBedGroupChargeTypeMappings($mappings);

        echo json_encode(array(
            'status'  => 'success',
            'message' => $this->lang->line('success_message'),
        ));
    }
}
