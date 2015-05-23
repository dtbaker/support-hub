<?php

class SupportHubUser{

	public function __construct(){
	}

	private $shub_user_id = false; // the current user id in our system.
    private $details = array();
	private $json_fields = array('user_data');

	private function reset(){
		$this->shub_user_id = false;
		$this->details = array(
			'shub_user_id' => '',
			'user_fname' => '',
			'user_lname' => '',
			'user_username' => '',
			'user_email' => '',
			'shub_linked_user_id' => 0,
			'user_data' => array(),
		);
		foreach($this->details as $field_id => $field_data){
			$this->{$field_id} = $field_data;
		}
	}

	public function create_new(){
		$this->reset();
		$this->shub_user_id = shub_update_insert('shub_user_id',false,'shub_user',array());
		$this->load($this->shub_user_id);
	}

	private static $_latest_load_create = array();
	public function load_by($field, $value){
		$this->reset();
		if(!empty($field) && !empty($value) && isset($this->details[$field])){
			if(isset(self::$_latest_load_create[$field][$value]) && self::$_latest_load_create[$field][$value] > 0){
				$this->load(self::$_latest_load_create[$field][$value]);
				return true;
			}
			$data = shub_get_single('shub_user',$field,$value);
			if(!$data){
				// check if it was recently created? gets around weird WP caching issue, resuling in mass duplicate of user details on bulk import
				if(!isset(self::$_latest_load_create[$field]))self::$_latest_load_create[$field]=array();
				self::$_latest_load_create[$field][$value] = false; // pending creating maybe?
			}else if($data && isset($data[$field]) && $data[$field] == $value && $data['shub_user_id']){
				$this->load($data['shub_user_id']);
				return true;
			}
		}
		return false;
	}

    public function load($shub_user_id = false){
	    if(!$shub_user_id)$shub_user_id = $this->shub_user_id;
	    $this->reset();
	    $this->shub_user_id = $shub_user_id;
        if($this->shub_user_id){
	        $data = shub_get_single('shub_user','shub_user_id',$this->shub_user_id);
	        foreach($this->details as $key=>$val){
		        $this->details[$key] = $data && isset($data[$key]) ? $data[$key] : $val;
		        if(in_array($key,$this->json_fields)){
			        $this->details[$key] = @json_decode($this->details[$key],true);
			        if(!is_array($this->details[$key]))$this->details[$key] = array();
		        }
	        }
	        if(!is_array($this->details) || $this->details['shub_user_id'] != $this->shub_user_id){
		        $this->reset();
		        return false;
	        }
        }
        foreach($this->details as $key=>$val){
            $this->{$key} = $val;
        }
        return $this->shub_user_id;
    }

	public function get($field){
		return isset($this->{$field}) ? $this->{$field} : false;
	}

    public function update($field,$value){
	    // what fields to we allow? or not allow?
	    if(in_array($field,array('shub_user_id')))return;
        if($this->shub_user_id){
            $this->{$field} = $value;
	        if(isset(self::$_latest_load_create[$field][$value])){
		        self::$_latest_load_create[$field][$value] = $this->shub_user_id;
	        }
	        if(in_array($field,$this->json_fields)){
		        $value = json_encode($value);
	        }
            shub_update_insert('shub_user_id',$this->shub_user_id,'shub_user',array(
	            $field => $value,
            ));
        }
    }

	public function update_user_data($user_data){
		if(is_array($user_data)){
			// yes, this member has some items, save these items to the account ready for selection in the settings area.
			$save_data = $this->get('user_data');
			if(!is_array($save_data))$save_data=array();
			$save_data = array_merge($save_data,$user_data);
			$this->update('user_data',$save_data);
		}
	}
	public function delete(){
		if($this->shub_user_id) {
			shub_delete_from_db( 'shub_user', 'shub_user_id', $this->shub_user_id );
		}
	}

}
