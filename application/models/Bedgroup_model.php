<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Bedgroup_model extends MY_Model
{
    public function get_bedgroup($id = null)
    {
        $this->db->select('bed_group.*,floor.name as floor_name')->from('bed_group')
            ->join('floor', 'bed_group.floor = floor.id');
        if ($id != null) {
            $this->db->where('bed_group.id', $id);
        } else {
            $this->db->order_by('bed_group.id');
        }
        $query = $this->db->get();
        if ($id != null) {
            return $query->row_array();
        } else {
            return $query->result_array();
        }
    }

    public function valid_bed_group($str)
    {
        $name = $this->input->post('name');
        if ($this->check_bed_group_exists($name)) {
            $this->form_validation->set_message('check_exists', $this->lang->line('bed') . " " . $this->lang->line('group') . " " . $this->lang->line('record_already_exists'));
            return false;
        } else {
            return true;
        }
    }

    public function check_bed_group_exists($name)
    {
        if ($name != 0) {
            $data  = array('name' => $name);
            $query = $this->db->where($data)->get('bed_group');
            if ($query->num_rows() > 0) {
                return true;
            } else {
                return false;
            }
        } else {
            $this->db->where('name', $name);
            $query = $this->db->get('bed_group');
            if ($query->num_rows() > 0) {
                return true;
            } else {
                return false;
            }
        }
    }

    public function add_bed_group($data)
    {
        $this->db->trans_start(); # Starting Transaction
        $this->db->trans_strict(false); # See Note 01. If you wish can remove as well
        //=======================Code Start===========================
        if (isset($data["id"])) {
            $this->db->where("id", $data["id"])->update("bed_group", $data);
            $message   = UPDATE_RECORD_CONSTANT . " On Bed Group id " . $data['id'];
            $action    = "Update";
            $record_id = $data['id'];
            $this->log($message, $record_id, $action);
            //======================Code End==============================

            $this->db->trans_complete(); # Completing transaction
            /* Optional */

            if ($this->db->trans_status() === false) {
                # Something went wrong.
                $this->db->trans_rollback();
                return false;
            } else {
                return $record_id;
            }
        } else {
            $this->db->insert("bed_group", $data);

            $insert_id = $this->db->insert_id();
            $message   = INSERT_RECORD_CONSTANT . " On Bed Group id " . $insert_id;
            $action    = "Insert";
            $record_id = $insert_id;
            $this->log($message, $record_id, $action);
            //======================Code End==============================

            $this->db->trans_complete(); # Completing transaction
            /* Optional */

            if ($this->db->trans_status() === false) {
                # Something went wrong.
                $this->db->trans_rollback();
                return false;
            } else {
                return $record_id;
            }
            return $insert_id;
        }
    }

    public function bedgroup_list($id = null)
    {
        if (!empty($id)) {
            $query = $this->db->select("bed_group.*,floor.name as floor_name")->join("floor", "bed_group.floor = floor.id")->where("bed_group.id", $id)->get("bed_group");
            return $query->row_array();
        } else {
            $query = $this->db->select("bed_group.*,floor.name as floor_name")->join("floor", "bed_group.floor = floor.id")->get("bed_group");
            return $query->result_array();
        }
    }

    public function delete_bedgroup($id)
    {
        $this->db->trans_start(); # Starting Transaction
        $this->db->trans_strict(false); # See Note 01. If you wish can remove as well
        //=======================Code Start===========================
        $this->db->where("id", $id)->delete("bed_group");
        $message   = DELETE_RECORD_CONSTANT . " On bed group id " . $id;
        $action    = "Delete";
        $record_id = $id;
        $this->log($message, $record_id, $action);
        //======================Code End==============================

        $this->db->trans_complete(); # Completing transaction
        /* Optional */

        if ($this->db->trans_status() === false) {
            # Something went wrong.
            $this->db->trans_rollback();
            return false;
        } else {
            return $record_id;
        }
    }

    public function bedGroupFloor()
    {
        $query = $this->db->select('bed_group.*,floor.id as fid,floor.name as floor_name')
            ->join('floor', 'bed_group.floor = floor.id', "left")
            ->get('bed_group');
        return $query->result_array();
    }

    public function ensureBedGroupChargeTypeTable()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS `bed_group_charge_type` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `bed_group_id` int(11) NOT NULL,
            `charge_type_id` int(11) NOT NULL,
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `bed_group_id` (`bed_group_id`),
            KEY `charge_type_id` (`charge_type_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
    }

    public function getBedGroupChargeTypeMappings()
    {
        $this->ensureBedGroupChargeTypeTable();
        $this->db->select('bed_group.id, bed_group.name, floor.name as floor_name, bed_group_charge_type.charge_type_id');
        $this->db->from('bed_group');
        $this->db->join('floor', 'bed_group.floor = floor.id', 'left');
        $this->db->join('bed_group_charge_type', 'bed_group_charge_type.bed_group_id = bed_group.id', 'left');
        $this->db->order_by('bed_group.name', 'asc');
        return $this->db->get()->result_array();
    }

    public function getChargeTypeByBedGroupId($bed_group_id)
    {
        $this->ensureBedGroupChargeTypeTable();
        $bed_group_id = (int) $bed_group_id;
        if ($bed_group_id < 1) {
            return '';
        }
        $row = $this->db->select('charge_type_master.charge_type')
            ->from('bed_group_charge_type')
            ->join('charge_type_master', 'charge_type_master.id = bed_group_charge_type.charge_type_id', 'inner')
            ->where('bed_group_charge_type.bed_group_id', $bed_group_id)
            ->where('charge_type_master.is_active', 'yes')
            ->get()
            ->row_array();
        return !empty($row['charge_type']) ? $row['charge_type'] : '';
    }

    public function saveBedGroupChargeTypeMappings($mappings)
    {
        $this->ensureBedGroupChargeTypeTable();
        if (!is_array($mappings)) {
            return;
        }

        $valid_groups = array();
        foreach ($this->db->select('id')->get('bed_group')->result_array() as $row) {
            $valid_groups[(int) $row['id']] = true;
        }
        $valid_types = array();
        foreach ($this->db->select('id')->where('is_active', 'yes')->get('charge_type_master')->result_array() as $row) {
            $valid_types[(int) $row['id']] = true;
        }

        foreach ($mappings as $bed_group_id => $charge_type_id) {
            $bed_group_id = (int) $bed_group_id;
            $charge_type_id = (int) $charge_type_id;
            if ($bed_group_id < 1 || empty($valid_groups[$bed_group_id])) {
                continue;
            }

            $existing = $this->db->select('id')
                ->from('bed_group_charge_type')
                ->where('bed_group_id', $bed_group_id)
                ->get()
                ->row_array();

            if ($charge_type_id < 1 || empty($valid_types[$charge_type_id])) {
                if (!empty($existing['id'])) {
                    $this->db->where('id', (int) $existing['id'])->delete('bed_group_charge_type');
                }
                continue;
            }

            if (!empty($existing['id'])) {
                $this->db->where('id', (int) $existing['id'])->update('bed_group_charge_type', array(
                    'charge_type_id' => $charge_type_id,
                ));
            } else {
                $this->db->insert('bed_group_charge_type', array(
                    'bed_group_id'    => $bed_group_id,
                    'charge_type_id'  => $charge_type_id,
                ));
            }
        }
    }
}
