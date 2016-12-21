<?php

class SupportHubUser_bbPress extends SupportHubUser{


    public function get_image(){
        if($this->get('user_email')){
            $hash = md5(trim($this->get('user_email')));
            return '//www.gravatar.com/avatar/'.$hash.'?d=identicon';
        }
        $default = parent::get_image();
        if(!$default){
            return plugins_url('extensions/bbpress/default-user.jpg',_DTBAKER_SUPPORT_HUB_CORE_FILE_);
        }
        return $default;
    }


}