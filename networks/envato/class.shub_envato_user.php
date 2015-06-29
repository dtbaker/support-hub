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
        return '';
    }

}