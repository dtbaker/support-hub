<?php

class SupportHub_item{

    public $extension_id = false;

    public function __construct($account = false, $shub_item_id = false){
        $this->account = $account;
        $this->load($shub_item_id);
    }



    
}