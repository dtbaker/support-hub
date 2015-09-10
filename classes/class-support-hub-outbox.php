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
			'shub_extension' => '',
			'shub_account_id' => '',
			'shub_message_id' => '',
			'shub_message_comment_id' => '',
			'queue_time' => '',
			'shub_status' => '',
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
            'shub_status' => _SHUB_OUTBOX_STATUS_QUEUED,
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

    public function send_queued($force=false){
        if($this->shub_outbox_id){
            // check the status of it.
            // todo - find any ones that are stuck in 'SENDING' status for too long and send those as well.
            if($force || $this->get('shub_status') == _SHUB_OUTBOX_STATUS_QUEUED){
                $managers = SupportHub::getInstance()->message_managers;
                if(!empty($this->shub_extension) && isset($managers[$this->shub_extension]) && $managers[$this->shub_extension]->is_enabled()){
                    // find the message manager responsible for this message and fire off the reply.
                    $message = $managers[$this->shub_extension]->get_message(false, false, $this->shub_message_id);
                    if($message->get('shub_message_id') == $this->shub_message_id){
                        SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_INFO,'sending','Starting Send: '.$this->shub_message_id);
                        // todo: look at adding a better "lock" so we don't sent duplicate messages between the QUEUE/SENDING get/update
                        $this->update('shub_status', _SHUB_OUTBOX_STATUS_SENDING);
                        // sweet! we're here, send the reply.
                        ob_start();
                        $status = $message->send_queued_comment_reply($this->shub_message_comment_id, $this, true);
                        $errors = ob_get_clean();
                        if($status){
                            // success! it worked! flag it as sent.
                            // todo: remove from this table? not sure.
                            $this->update('shub_status', _SHUB_OUTBOX_STATUS_SENT);
                        }else{
                            $this->update('shub_status', _SHUB_OUTBOX_STATUS_FAILED);
                            SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_ERROR,'sending','Failed to Send: '.$this->shub_message_id.': error: '.$errors);
                        }
                        SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_INFO,'sending','Finished Send: '.$this->shub_message_id);
                        return $errors;


                    }
                }
            }
        }
        return false;
    }

    public static function get_pending(){
        return array_merge(shub_get_multiple('shub_outbox',array('shub_status'=>_SHUB_OUTBOX_STATUS_QUEUED),'shub_outbox_id'),shub_get_multiple('shub_outbox',array('shub_status'=>_SHUB_OUTBOX_STATUS_SENDING),'shub_outbox_id'));
    }
    public static function get_failed(){
        return shub_get_multiple('shub_outbox',array('shub_status'=>_SHUB_OUTBOX_STATUS_FAILED),'shub_outbox_id');
    }

}
