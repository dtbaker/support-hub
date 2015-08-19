<?php

class SupportHubProduct{

	public function __construct($shub_product_id = false){
        if($shub_product_id){
            $this->load($shub_product_id);
        }
	}

	private $shub_product_id = false; // the current user id in our system.
    private $details = array();
	private $json_fields = array('product_data');

	private function reset(){
		$this->shub_product_id = false;
		$this->details = array(
			'shub_product_id' => '',
			'product_name' => '',
			'product_data' => array(),
		);
		foreach($this->details as $field_id => $field_data){
			$this->{$field_id} = $field_data;
		}
	}

	public function create_new(){
		$this->reset();
		$this->shub_product_id = shub_update_insert('shub_product_id',false,'shub_product',array(
            'product_name' => '',
        ));
		$this->load($this->shub_product_id);
	}

    public function load($shub_product_id = false){
	    if(!$shub_product_id)$shub_product_id = $this->shub_product_id;
	    $this->reset();
	    $this->shub_product_id = $shub_product_id;
        if($this->shub_product_id){
	        $data = shub_get_single('shub_product','shub_product_id',$this->shub_product_id);
	        foreach($this->details as $key=>$val){
		        $this->details[$key] = $data && isset($data[$key]) ? $data[$key] : $val;
		        if(in_array($key,$this->json_fields)){
			        $this->details[$key] = @json_decode($this->details[$key],true);
			        if(!is_array($this->details[$key]))$this->details[$key] = array();
		        }
	        }
	        if(!is_array($this->details) || $this->details['shub_product_id'] != $this->shub_product_id){
		        $this->reset();
		        return false;
	        }
        }
        foreach($this->details as $key=>$val){
            $this->{$key} = $val;
        }
        return $this->shub_product_id;
    }

	public function get($field){
		return isset($this->{$field}) ? $this->{$field} : false;
	}

    public function update($field,$value){
	    // what fields to we allow? or not allow?
	    if(in_array($field,array('shub_product_id')))return;
        if($this->shub_product_id){
            $this->{$field} = $value;
	        if(in_array($field,$this->json_fields)){
		        $value = json_encode($value);
	        }
            shub_update_insert('shub_product_id',$this->shub_product_id,'shub_product',array(
	            $field => $value,
            ));
        }
    }
	public function delete(){
		if($this->shub_product_id) {
			shub_delete_from_db( 'shub_product', 'shub_product_id', $this->shub_product_id );
		}
	}

}
