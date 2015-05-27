<?php

class SupportHubExtra{

	public function __construct($shub_extra_id = false){
		if($shub_extra_id){
			$this->load($shub_extra_id);
		}
	}

	private $shub_extra_id = false; // the current extra id in our system.
    private $details = array();
	private $json_fields = array();

	private function reset(){
		$this->shub_extra_id = false;
		$this->details = array(
			'shub_extra_id' => '',
			'extra_name' => '',
			'extra_description' => '',
			'extra_order' => 0,
			'extra_required' => 0,
		);
		foreach($this->details as $field_id => $field_data){
			$this->{$field_id} = $field_data;
		}
	}

	public function create_new(){
		$this->reset();
		$this->shub_extra_id = shub_update_insert('shub_extra_id',false,'shub_extra',array());
		$this->load($this->shub_extra_id);
	}

	public function get_all_extras(){
		return shub_get_multiple('shub_extra',array(),'shub_extra_id','extra_order');
	}

	public function load_by($field, $value){
		$this->reset();
		if(!empty($field) && !empty($value) && isset($this->details[$field])){
			$data = shub_get_single('shub_extra',$field,$value);
			if($data && isset($data[$field]) && $data[$field] == $value && $data['shub_extra_id']){
				$this->load($data['shub_extra_id']);
				return true;
			}
		}
		return false;
	}

    public function load($shub_extra_id = false){
	    if(!$shub_extra_id)$shub_extra_id = $this->shub_extra_id;
	    $this->reset();
	    $this->shub_extra_id = $shub_extra_id;
        if($this->shub_extra_id){
	        $data = shub_get_single('shub_extra','shub_extra_id',$this->shub_extra_id);
	        foreach($this->details as $key=>$val){
		        $this->details[$key] = $data && isset($data[$key]) ? $data[$key] : $val;
		        if(in_array($key,$this->json_fields)){
			        $this->details[$key] = @json_decode($this->details[$key],true);
			        if(!is_array($this->details[$key]))$this->details[$key] = array();
		        }
	        }
	        if(!is_array($this->details) || $this->details['shub_extra_id'] != $this->shub_extra_id){
		        $this->reset();
		        return false;
	        }
        }
        foreach($this->details as $key=>$val){
            $this->{$key} = $val;
        }
        return $this->shub_extra_id;
    }

	public function get($field){
		return isset($this->{$field}) ? $this->{$field} : false;
	}


    public function update($field,$value=false){
	    if(is_array($field)){
		    foreach($field as $key=>$val){
			    if(isset($this->details[$key])){
				    $this->update($key,$val);
			    }
		    }
		    return;
	    }
	    // what fields to we allow? or not allow?
	    if(in_array($field,array('shub_extra_id')))return;
        if($this->shub_extra_id){
            $this->{$field} = $value;
	        if(in_array($field,$this->json_fields)){
		        $value = json_encode($value);
	        }
            shub_update_insert('shub_extra_id',$this->shub_extra_id,'shub_extra',array(
	            $field => $value,
            ));
        }
    }

	public function delete(){
		if($this->shub_extra_id) {
			shub_delete_from_db( 'shub_extra', 'shub_extra_id', $this->shub_extra_id );
		}
	}

	public function link_edit(){
		return 'admin.php?page=support_hub_settings&tab=extra&shub_extra_id='.$this->get('shub_extra_id');
	}

}



class SupportHubExtraList extends SupportHub_Account_Data_List_Table{
    private $row_output = array();

	function __construct($args = array()) {
		$args = wp_parse_args( $args, array(
			'plural'   => __( 'extra_details', 'support_hub' ),
			'singular' => __( 'extra_detail', 'support_hub' ),
			'ajax'     => false,
		) );
		parent::__construct( $args );
	}


	private $message_managers = array();
	function set_message_managers($message_managers){
		$this->message_managers = $message_managers;
	}

	private $column_details = array();
    function column_default($item, $column_name){

	    if(isset($item[$column_name])){
		    return $item[$column_name];
	    }else{
		    return 'No';
	    }
   }
}