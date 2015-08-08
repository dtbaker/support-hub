<?php

class SupportHub_account{

    public $extension_id = false;
    public function __construct($shub_account_id){
        if(!$this->extension_id){
            // we're going in blind! find out what account this is from and load the correct class
            $shub_account_id = (int)$shub_account_id;
            if($shub_account_id > 0) {
                $temp = shub_get_single('shub_account', 'shub_account_id', $shub_account_id);
                if(!empty($temp['network']))
            }

            return false;
        }
        $this->load($shub_account_id);
    }

    public $shub_account_id = false; // the current user id in our system.
    public $details = array();


    /* @var $items SupportHub_item[] */
    public $items = array();

    public $json_fields = array('account_data');

    public function reset(){
        $this->shub_account_id = false;
        $this->details = array(
            'shub_account_id' => false,
            'shub_user_id' => 0,
            'account_name' => false,
            'last_checked' => false,
            'account_data' => array(),
        );
        foreach($this->details as $field_id => $field_data){
            $this->{$field_id} = $field_data;
        }
    }

    public function create_new(){
        $this->reset();
        $this->shub_account_id = shub_update_insert('shub_account_id',false,'shub_account',array(
            'envato_name' => '',
        ));
        $this->load($this->shub_account_id);
    }

    public function load($shub_account_id = false){
        if(!$shub_account_id)$shub_account_id = $this->shub_account_id;
        $this->reset();
        $this->shub_account_id = (int)$shub_account_id;
        if($this->shub_account_id){
            $data = shub_get_single('shub_account','shub_account_id',$this->shub_account_id);
            foreach($this->details as $key=>$val){
                $this->details[$key] = $data && isset($data[$key]) ? $data[$key] : $val;
                if(in_array($key,$this->json_fields)){
                    $this->details[$key] = @json_decode($this->details[$key],true);
                    if(!is_array($this->details[$key]))$this->details[$key] = array();
                }
            }
            if(!is_array($this->details) || $this->details['shub_account_id'] != $this->shub_account_id){
                $this->reset();
                return false;
            }
        }
        foreach($this->details as $key=>$val){
            $this->{$key} = $val;
        }

        $this->items = array();
        if(!$this->shub_account_id)return false;
        foreach(shub_get_multiple('shub_item',array('shub_account_id'=>$this->shub_account_id),'shub_item_id') as $item){
            $item = new SupportHub_item($this, $item['shub_item_id']);
            $this->items[$item->get('item_id')] = $item;
        }
        return $this->shub_account_id;
    }

    public function get($field){
        return isset($this->{$field}) ? $this->{$field} : false;
    }

    public function save_data($post_data){
        if(!$this->get('shub_account_id')){
            $this->create_new();
        }
        if(is_array($post_data)){
            foreach($this->details as $details_key => $details_val){
                if(isset($post_data[$details_key])){
                    if(($details_key == 'envato_app_secret' || $details_key == 'envato_token' || $details_key == 'envato_cookie') && $post_data[$details_key] == 'password')continue;
                    $this->update($details_key,$post_data[$details_key]);
                }
            }
        }
        if(!isset($post_data['import_stream'])){
            $this->update('import_stream', 0);
        }
        // save the active envato items.
        if(isset($post_data['save_envato_items']) && $post_data['save_envato_items'] == 'yep') {
            $currently_active_items = $this->items;
            $data = $this->get('envato_data');
            $available_items = isset($data['items']) && is_array($data['items']) ? $data['items'] : array();
            if(isset($post_data['envato_item']) && is_array($post_data['envato_item'])){
                foreach($post_data['envato_item'] as $envato_item_id => $yesno){
                    if(isset($currently_active_items[$envato_item_id])){
                        if(isset($post_data['envato_item_product'][$envato_item_id])){
                            $currently_active_items[$envato_item_id]->update('shub_product_id',$post_data['envato_item_product'][$envato_item_id]);
                        }
                        unset($currently_active_items[$envato_item_id]);
                    }
                    if($yesno && isset($available_items[$envato_item_id])){
                        // we are adding this item to the list. check if it doesn't already exist.
                        if(!isset($this->items[$envato_item_id])){
                            $item = new shub_account_item($this);
                            $item->create_new();
                            $item->update('shub_account_id', $this->shub_account_id);
                            //$item->update('envato_token', 'same'); // $available_items[$envato_item_id]['access_token']
                            $item->update('item_name', $available_items[$envato_item_id]['item']);
                            $item->update('item_id', $envato_item_id);
                            $item->update('envato_data', $available_items[$envato_item_id]);
                            $item->update('shub_product_id', isset($post_data['envato_item_product'][$envato_item_id]) ? $post_data['envato_item_product'][$envato_item_id] : 0);
                        }
                    }
                }
            }
            // remove any items that are no longer active.
            foreach($currently_active_items as $item){
                $item->delete();
            }
        }
        $this->load();
        return $this->get('shub_account_id');
    }
    public function update($field,$value){
        // what fields to we allow? or not allow?
        if(in_array($field,array('shub_account_id')))return;
        if($this->shub_account_id){
            $this->{$field} = $value;
            if(in_array($field,$this->json_fields)){
                $value = json_encode($value);
            }
            shub_update_insert('shub_account_id',$this->shub_account_id,'shub_account',array(
                $field => $value,
            ));
        }
    }
    public function delete(){
        if($this->shub_account_id) {
            // delete all the items for this twitter account.
            $items = $this->get('items');
            foreach($items as $item){
                $item->delete();
            }
            shub_delete_from_db( 'shub_account', 'shub_account_id', $this->shub_account_id );
        }
    }

    public function is_active(){
        // is there a 'last_checked' date?
        if(!$this->get('last_checked')){
            return false; // never checked this account, not active yet.
        }else{
            // do we have a token?
            if($this->get('envato_token')){
                // assume we have access, we remove the token if we get a envato failure at any point.
                return true;
            }
        }
        return false;
    }

    public function save_account_data($user_data){
        // serialise this result into envato_data.
        if(is_array($user_data)){
            // yes, this member has some items, save these items to the account ready for selection in the settings area.
            $save_data = $this->get('envato_data');
            if(!is_array($save_data))$save_data=array();
            $save_data = array_merge($save_data,$user_data);
            $this->update('envato_data',$save_data);
        }
    }

    public function run_cron( $debug = false ){


    }


}