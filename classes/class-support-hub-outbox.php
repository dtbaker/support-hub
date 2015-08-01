<?php

define('_SHUB_OUTBOX_STATUS_QUEUED',0);
define('_SHUB_OUTBOX_STATUS_SENDING',1);
define('_SHUB_OUTBOX_STATUS_FAILED',2);
define('_SHUB_OUTBOX_STATUS_SENT',3);

class SupportHubOutbox{

	public function __construct($shub_outbox_id = false){
		if($shub_outbox_id){
			$this->load($shub_outbox_id);
		}
	}

	private $shub_outbox_id = false; // the current outbox id in our system.
    public $details = array();
	private $json_fields = array('message_data');

	public $db_table = 'shub_outbox';
	public $db_primary_key = 'shub_outbox_id';

	public function reset(){
		$this->{$this->db_primary_key} = false;
		$this->details = array(
			'shub_outbox_id' => '',
			'shub_network' => '',
			'shub_network_account_id' => '',
			'shub_network_message_id' => '',
			'shub_network_message_comment_id' => '',
			'queue_time' => '',
			'status' => '',
			'message_data' => array(),
		);
		foreach($this->details as $field_id => $field_data){
			$this->{$field_id} = $field_data;
		}
	}

	public function create_new(){
		$this->reset();
		$this->{$this->db_primary_key} = shub_update_insert($this->db_primary_key,false,$this->db_table,array(
            'queue_time' => time(),
            'status' => _SHUB_OUTBOX_STATUS_QUEUED,
        ));
		$this->load($this->{$this->db_primary_key});
	}

	public function load_by($field, $value){
		$this->reset();
		if(!empty($field) && !empty($value) && isset($this->details[$field])){
			$data = shub_get_single($this->db_table,$field,$value);
			if($data && isset($data[$field]) && $data[$field] == $value && $data[$this->db_primary_key]){
				$this->load($data[$this->db_primary_key]);
				return true;
			}
		}
		return false;
	}

    public function load($shub_outbox_id = false){
	    if(!$shub_outbox_id)$shub_outbox_id = $this->{$this->db_primary_key};
	    $this->reset();
	    $this->{$this->db_primary_key} = $shub_outbox_id;
        if($this->{$this->db_primary_key}){
	        $data = shub_get_single($this->db_table,$this->db_primary_key,$this->{$this->db_primary_key});
	        foreach($this->details as $key=>$val){
		        $this->details[$key] = $data && isset($data[$key]) ? $data[$key] : $val;
		        if(in_array($key,$this->json_fields)){
			        $this->details[$key] = @json_decode($this->details[$key],true);
			        if(!is_array($this->details[$key]))$this->details[$key] = array();
		        }
	        }
	        if(!is_array($this->details) || $this->details[$this->db_primary_key] != $this->{$this->db_primary_key}){
		        $this->reset();
		        return false;
	        }
        }
        foreach($this->details as $key=>$val){
            $this->{$key} = $val;
        }
        return $this->{$this->db_primary_key};
    }

	public function get($field){
		return isset($this->{$field}) ? $this->{$field} : false;
	}

    public function update($field,$value=false){
        if(is_array($field)){
            foreach($field as $key=>$val){
                $this->update($key,$val);
            }
            return;
        }
	    // what fields to we allow? or not allow?
	    if(in_array($field,array($this->db_primary_key)))return;
        if($this->{$this->db_primary_key} && isset($this->details[$field])){
            $this->{$field} = $value;
            $this->details[$field] = $value;
	        if(in_array($field,$this->json_fields)){
		        $value = json_encode($value);
	        }
            shub_update_insert($this->db_primary_key,$this->{$this->db_primary_key},$this->db_table,array(
	            $field => $value,
            ));
        }
    }

	public function update_outbox_data($outbox_data){
		if(is_array($outbox_data)){
			// yes, this member has some items, save these items to the account ready for selection in the settings area.
			$save_data = $this->get('message_data');
			if(!is_array($save_data))$save_data=array();
			$save_data = array_merge($save_data,$outbox_data);
			$this->update('message_data',$save_data);
		}
	}
	public function delete(){
		if($this->{$this->db_primary_key}) {
			shub_delete_from_db( $this->db_table, $this->db_primary_key, $this->{$this->db_primary_key} );
		}
	}

    public static function get_pending(){
        return shub_get_multiple('shub_outbox',array('status'=>_SHUB_OUTBOX_STATUS_QUEUED),'shub_outbox_id');
    }
    public static function get_failed(){
        return shub_get_multiple('shub_outbox',array('status'=>_SHUB_OUTBOX_STATUS_FAILED),'shub_outbox_id');
    }

}
