<?php

class SupportHubUser_bbPress extends SupportHubUser{

    public $db_table = 'shub_bbpress_user';
    public $db_primary_key = 'shub_bbpress_user_id';
    public $shub_bbpress_user_id = false;

    public function reset(){
        $this->{$this->db_primary_key} = false;
        $this->details = array(
            'shub_bbpress_user_id' => '',
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

}