<?php

class SupportHubUser_bbPress extends SupportHubUser{


    public function get_image(){
        if($this->get('user_email')){
            $hash = md5(trim($this->get('user_email')));
            return '//www.gravatar.com/avatar/'.$hash.'?d=wavatar';
        }
        return plugins_url('extensions/bbpress/default-user.jpg',_DTBAKER_SUPPORT_HUB_CORE_FILE_);
    }

    public function get_full_link(){
        $return = '';
        $return .= '<a href="'.esc_url($this->get_link()).'" target="_blank">';
        $return .= htmlspecialchars($this->get('user_username'));
        $return .= '</a>';
        return $return;
    }

}