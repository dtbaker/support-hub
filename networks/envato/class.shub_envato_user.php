<?php

class SupportHubUser_Envato extends SupportHubUser{

    public $db_table = 'shub_envato_user';
    public $db_primary_key = 'shub_envato_user_id';
    public $shub_envato_user_id = false;

    public function reset(){
        $this->{$this->db_primary_key} = false;
        $this->details = array(
            'shub_envato_user_id' => '',
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


    public function get_image(){
        $data = $this->get('user_data');
        if(!empty($data['image'])){
            return $data['image'];
        }
        if($this->get('user_email')){
            $hash = md5(trim($this->get('user_email')));
            return '//www.gravatar.com/avatar/'.$hash.'?d=wavatar';
        }
        return '';
    }

}