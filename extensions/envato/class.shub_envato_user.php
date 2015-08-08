<?php

class SupportHubUser_Envato extends SupportHubUser{

    public function get_image(){
        $data = $this->get('user_data');
        if(!empty($data['image'])){
            return $data['image'];
        }
        if($this->get('user_email')){
            $hash = md5(trim($this->get('user_email')));
            return '//www.gravatar.com/avatar/'.$hash.'?d=wavatar';
        }
        return plugins_url('extensions/envato/default-user.jpg',_DTBAKER_SUPPORT_HUB_CORE_FILE_);
    }

    public function get_full_link(){
        $data = $this->get('user_data');
        $return = '';
        if(!empty($data['envato']['username'])){
            $return .= '<a href="'.esc_url($this->get_link()).'" target="_blank">';
            $return .= htmlspecialchars($data['envato']['username']);
            $return .= '</a>';
        }
        return $return;
    }

    public function get_link(){
        $data = $this->get('user_data');
        $return = '#';
        if(!empty($data['envato']['username'])){
            $return = 'http://themeforest.net/user/' . esc_attr($data['envato']['username']);
        }
        return $return;
    }

}