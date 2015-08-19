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

    public function update_purchase_history(){
        $tokens = shub_get_multiple('shub_envato_oauth',array('shub_user_id'=>$this->shub_user_id));
        // find the latest token for this user, per account.
        $account_tokens = array();
        // if any of them have expired, refresh the token from the api
        foreach($tokens as $token){
            if(!$token['shub_account_id'])continue;
            if(!isset($account_tokens[$token['shub_account_id']]) || $token['expire_time'] > $account_tokens[$token['shub_account_id']]['expire_time']){
                $account_tokens[$token['shub_account_id']] = $token;
            }
        }
        foreach($account_tokens as $account_token){
            $shub_envato_account = new shub_envato_account($account_token['shub_account_id']);
            // found the account, pull in the API and build the url
            $api = $shub_envato_account->get_api();
            $api->set_manual_token($account_token);
            if($account_token['expire_time'] <= time()){
                // renew this token!
                $new_access_token = $api->refresh_token();
                if($new_access_token){
                    shub_update_insert('shub_envato_oauth_id',$account_token['shub_envato_oauth_id'],'shub_envato_oauth',array(
                        'access_token' => $new_access_token,
                        'expire_time' => time() + 3600,
                    ));
                }else{
                    echo 'Token refresh failed';
                    return false;
                }
            }

            $api_result = $api->api('v1/market/private/user/username.json', array(), false);
            $api_result_email = $api->api('v1/market/private/user/email.json', array(), false);

            if($api_result && !empty($api_result['username'])){
                $this->add_unique_meta('envato_username',$api_result['username']);
            }
            if($api_result_email && !empty($api_result_email['email'])){
                $email = trim(strtolower($api_result_email['email']));
                // todo: not sure if best to update users eamail , if they change email accounts and stuff
                $this->update('user_email',$email);
            }

            $api_result_purchase_history = $api->api('v2/market/buyer/purchases', array(), false);
//                                echo 'her';print_r($api_result_purchase_history);exit;
            // store this purchase history in our db for later use.
            if($api_result_purchase_history && !empty($api_result_purchase_history['buyer']['id']) && !empty($api_result_purchase_history['buyer']['username']) && $api_result_purchase_history['buyer']['username'] == $api_result['username']) {
                // we have the buyer ID! yay! this is better than a username.
                $this->add_unique_meta('envato_user_id', $api_result_purchase_history['buyer']['id']);
                if (!empty($api_result_purchase_history['purchases']) && is_array($api_result_purchase_history['purchases'])) {
                    foreach ($api_result_purchase_history['purchases'] as $purchase) {
                        if (!empty($purchase['item']['id'])) {
                            // todo: beg envato to add the purchase code to this output so we can link it together correctly.
                            // find out which shub product this is for
                            // if we cannot find one then we create one. this helps when new items are made.
                            $existing_products = SupportHub::getInstance()->get_products();
                            // check if this item exists already
                            $exists = false;
                            foreach ($existing_products as $existing_product) {
                                if (isset($existing_product['product_data']['item_id']) && $existing_product['product_data']['item_id'] == $purchase['item']['id']) {
                                    $exists = $existing_product['shub_product_id'];
                                }
                            }
                            $newproduct = new SupportHubProduct();
                            if (!$exists) {
                                $newproduct->create_new();
                            } else {
                                $newproduct->load($exists);
                            }
                            if (!$newproduct->get('product_name')) {
                                $newproduct->update('product_name', $purchase['item']);
                            }
                            $existing_product_data = $newproduct->get('product_data');
                            if (!is_array($existing_product_data)) $existing_product_data = array();
                            if (empty($existing_product_data['item_id'])) {
                                $existing_product_data['item_id'] = $purchase['item']['id'];
                            }
                            if (empty($existing_product_data['item_data'])) {
                                $existing_product_data['item_data'] = $purchase['item'];
                            }
                            if (empty($existing_product_data['image'])) {
                                $existing_product_data['image'] = $purchase['item']['thumbnail'];
                            }
                            if (empty($existing_product_data['url'])) {
                                $existing_product_data['url'] = $purchase['item']['url'];
                            }
                            $newproduct->update('product_data', $existing_product_data);
                            if ($newproduct->get('shub_product_id')) {
                                // product has been added
                                // time to add it to the purchase db
                                // check if this already exists in the db
                                $existing_purchase = shub_get_single('shub_envato_purchase', array(
                                    'shub_user_id',
                                    'shub_product_id',
                                    'purchase_time',
                                ), array(
                                    $this->get('shub_user_id'),
                                    $newproduct->get('shub_product_id'),
                                    strtotime($purchase['sold_at']),
                                ));
                                if (!$existing_purchase) {
                                    $shub_envato_purchase_id = shub_update_insert('shub_envato_purchase_id', false, 'shub_envato_purchase', array(
                                        'shub_user_id' => $this->get('shub_user_id'),
                                        'shub_product_id' => $newproduct->get('shub_product_id'),
                                        'purchase_time' => strtotime($purchase['sold_at']),
                                        'envato_user_id' => $api_result_purchase_history['buyer']['id'],
                                        'purchase_code' => '', // todo: hopefully they add this in!
                                        'api_type' => 'buyer/purchases',
                                        'purchase_data' => json_encode($purchase),
                                    ));
                                } else {
                                    $shub_envato_purchase_id = $existing_purchase['shub_envato_purchase_id'];
                                }
                                if ($shub_envato_purchase_id) {
                                    // we have a purchase in the db
                                    // add or update the support expiry based on this purchase history.
                                    // work out when this purchase support expires
                                    // this is the expiry date returned in the api or just 6 months from the original purchase date.
                                    $support_expiry_time = strtotime("+6 months", strtotime($purchase['sold_at']));
                                    // todo - check for this expiry time in the new api results.

                                    $existing_support = shub_get_single('shub_envato_support', array('shub_user_id','shub_envato_purchase_id'), array($this->get('shub_user_id'), $shub_envato_purchase_id));
                                    if ($existing_support && $existing_support['shub_envato_support_id'] && $existing_support['start_time'] == strtotime($purchase['sold_at'])) {
                                        // check the existing support expiry matches the one we have in the database.
                                        if($existing_support['end_time'] < $support_expiry_time){
                                            // we have a support extension!
                                            $shub_envato_support_id = shub_update_insert('shub_envato_support_id', $existing_support['shub_envato_support_id'], 'shub_envato_support', array(
                                                'end_time' => $support_expiry_time,
                                                'api_type' => 'buyer/purchases',
                                                'support_data' => json_encode($purchase),
                                            ));
                                        }
                                    }else{
                                        // we are adding a new support entry
                                        $shub_envato_support_id = shub_update_insert('shub_envato_support_id', false, 'shub_envato_support', array(
                                            'shub_user_id' => $this->get('shub_user_id'),
                                            'shub_product_id' => $newproduct->get('shub_product_id'),
                                            'shub_envato_purchase_id' => $shub_envato_purchase_id,
                                            'start_time' => strtotime($purchase['sold_at']),
                                            'end_time' => $support_expiry_time,
                                            'api_type' => 'buyer/purchases',
                                            'support_data' => json_encode($purchase),
                                        ));
                                    }
                                }
                            }

                        }
                    }
                }

            }

        }
        return true;
    }

}