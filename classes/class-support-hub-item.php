<?php

class SupportHub_item{

    public function __construct($account = false, $shub_item_id = false){
        $this->account = $account;
        $this->load($shub_item_id);
    }

    /* @var $account SupportHub_account */
    public $account = false;
    public $shub_item_id = false; // the current user id in our system.
    public $details = array();
    public $json_fields = array('item_data');

    private function reset(){
        $this->shub_item_id = false;
        $this->details = array(
            'shub_item_id' => '',
            'shub_account_id' => '',
            'shub_product_id' => '',
            'item_name' => '',
            'last_message' => '',
            'last_checked' => '',
            'network_key' => '',
            'item_data' => array(),
        );
        foreach($this->details as $field_id => $field_data){
            $this->{$field_id} = $field_data;
        }
    }

    public function create_new(){
        $this->reset();
        $this->shub_item_id = shub_update_insert('shub_item_id',false,'shub_item',array(
            'item_name' => '',
        ));
        $this->load($this->shub_item_id);
    }

    public function load($shub_item_id = false){
        if(!$shub_item_id)$shub_item_id = $this->shub_item_id;
        $this->reset();
        $this->shub_item_id = $shub_item_id;
        if($this->shub_item_id){
            $data = shub_get_single('shub_item','shub_item_id',$this->shub_item_id);
            foreach($this->details as $key=>$val){
                $this->details[$key] = $data && isset($data[$key]) ? $data[$key] : $val;
                if(in_array($key,$this->json_fields)){
                    $this->details[$key] = @json_decode($this->details[$key],true);
                    if(!is_array($this->details[$key]))$this->details[$key] = array();
                }
            }
            if(!is_array($this->details) || $this->details['shub_item_id'] != $this->shub_item_id){
                $this->reset();
                return false;
            }
        }
        foreach($this->details as $key=>$val){
            $this->{$key} = $val;
        }
        return $this->shub_item_id;
    }

    public function get($field){
        return isset($this->{$field}) ? $this->{$field} : false;
    }

    public function update($field,$value){
        // what fields to we allow? or not allow?
        if(in_array($field,array('shub_item_id')))return;
        if($this->shub_item_id){
            $this->{$field} = $value;
            if(in_array($field,$this->json_fields)){
                $value = json_encode($value);
            }
            shub_update_insert('shub_item_id',$this->shub_item_id,'shub_item',array(
                $field => $value,
            ));
        }
    }
    public function delete(){
        if($this->shub_item_id) {
            // delete all the messages for this item.
            $messages = shub_get_multiple('shub_message',array(
                'shub_item_id' => $this->shub_item_id,
            ),'shub_message_id');
            foreach($messages as $message){
                if($message && isset($message['shub_item_id']) && $message['shub_item_id'] == $this->shub_item_id){
                    shub_delete_from_db( 'shub_message', 'shub_message_id', $message['shub_message_id'] );
                    shub_delete_from_db( 'shub_message_comment', 'shub_message_id', $message['shub_message_id'] );
                    shub_delete_from_db( 'shub_message_link', 'shub_message_id', $message['shub_message_id'] );
                    shub_delete_from_db( 'shub_message_read', 'shub_message_id', $message['shub_message_id'] );
                }
            }
            shub_delete_from_db( 'shub_item', 'shub_item_id', $this->shub_item_id );
        }
    }

}