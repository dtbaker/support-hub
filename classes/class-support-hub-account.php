<?php

class SupportHub_account{

    public function __construct($shub_account_id){
        $this->load($shub_account_id);
    }

    public $shub_account_id = false; // the current user id in our system.
    public $shub_extension = ''; // the current extension name
    public $details = array();

    /* @var $items SupportHub_item[] */
    public $items = array();

    public $json_fields = array('account_data');

    public function reset(){
        $this->shub_account_id = false;
        $this->details = array(
            'shub_account_id' => false,
            'shub_extension' => '',
            'account_name' => false,
            'shub_user_id' => 0,
            'last_checked' => false,
            'account_data' => array(),
            'items' => array(),
        );
        foreach($this->details as $field_id => $field_data){
            $this->{$field_id} = $field_data;
        }
    }

    public function is_item_active($network_key){
        if(isset($this->items[$network_key]) && $this->items[$network_key]->get('network_key') == $network_key){
            return true;
        }else{
            return false;
        }
    }


    public function create_new(){
        $this->reset();
        $this->shub_account_id = shub_update_insert('shub_account_id',false,'shub_account',array(
            'account_name' => '',
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
            $item = $this->get_item($item['shub_item_id']);
            $this->items[$item->get('network_key')] = $item;
        }
        return $this->shub_account_id;
    }

    public function get($field){
        if(isset($this->{$field})){
            return $this->{$field};
        }else{
            // check in data
            $data = $this->get('account_data');
            if(!empty($data) && isset($data[$field])){
                return $data[$field];
            }
        }
        return false;
    }

    public function save_data($post_data){
        if(!$this->get('shub_account_id')){
            $this->create_new();
        }
        if(is_array($post_data)){
            foreach($this->details as $details_key => $details_val){
                if(isset($post_data[$details_key])){
                    if(is_array($post_data[$details_key])){
                        foreach($post_data[$details_key] as $key=>$val){
                            if($val == _SUPPORT_HUB_PASSWORD_FIELD_FUZZ){
                                unset($post_data[$details_key][$key]);
                            }
                        }
                    }else if($post_data[$details_key] == _SUPPORT_HUB_PASSWORD_FIELD_FUZZ)continue;

                    $this->update($details_key,$post_data[$details_key]);
                }
            }
        }
        // save the active envato items.
        if(isset($post_data['save_account_items']) && $post_data['save_account_items'] == 'yep') {
            $currently_active_items = $this->items;
            $data = $this->get('account_data');
            $available_items = isset($data['items']) && is_array($data['items']) ? $data['items'] : array();
            if(isset($post_data['item']) && is_array($post_data['item'])){
                foreach($post_data['item'] as $network_key => $yesno){
                    if(isset($currently_active_items[$network_key])){
                        if(isset($post_data['item_product'][$network_key])){
                            $currently_active_items[$network_key]->update('shub_product_id',$post_data['item_product'][$network_key]);
                        }
                        unset($currently_active_items[$network_key]);
                    }
                    if($yesno && isset($available_items[$network_key])){
                        // we are adding this item to the list. check if it doesn't already exist.
                        if(!isset($this->items[$network_key])){
                            $item = new SupportHub_item($this);
                            $item->create_new();
                            $item->update('shub_account_id', $this->shub_account_id);
                            //$item->update('envato_token', 'same'); // $available_items[$network_key]['access_token']
                            $item->update('item_name', $post_data['item_name'][$network_key]); //$available_items[$network_key]['item']);
                            $item->update('network_key', $network_key);
                            $item->update('item_data', $available_items[$network_key]);
                            $item->update('shub_product_id', isset($post_data['item_product'][$network_key]) ? $post_data['item_product'][$network_key] : 0);
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
            if($field == 'account_data'){
                if(is_array($value)){
                    // merge data with existing.
                    $existing_data = $this->get('account_data');
                    if(!is_array($existing_data))$existing_data=array();
                    $value = array_merge($existing_data,$value);
                }
            }
            $this->{$field} = $value;
            if (in_array($field, $this->json_fields)) {
                $value = json_encode($value);
            }
            shub_update_insert('shub_account_id', $this->shub_account_id, 'shub_account', array(
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
            return true;
        }
    }

    public function save_account_data($user_data){
        $this->update('account_data',$user_data);

    }

    public function run_cron( $debug = false ){


    }

    /**
     * Links for wordpress
     */
    public function link_connect(){
        return 'admin.php?page=support_hub_settings&tab='.$this->shub_extension.'&do_connect&shub_account_id='.$this->get('shub_account_id');
    }
    public function link_edit(){
        return 'admin.php?page=support_hub_settings&tab='.$this->shub_extension.'&shub_account_id='.$this->get('shub_account_id');
    }
    public function link_new_message(){
        return 'admin.php?page=support_hub_main&shub_account_id='.$this->get('shub_account_id').'&shub_message_id=new';
    }
    public function link_refresh(){
        return 'admin.php?page=support_hub_settings&tab='.$this->shub_extension.'&manualrefresh&shub_account_id='.$this->get('shub_account_id').'';
    }


}