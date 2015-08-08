<?php

class shub_envato_account extends SupportHub_account{

    public function reset(){
        parent::reset();
        $this->items = array();
    }


	public function is_item_active($envato_item_id){
		if(isset($this->items[$envato_item_id]) && $this->items[$envato_item_id]->get('item_id') == $envato_item_id){
			return true;
		}else{
			return false;
		}
	}


	public function confirm_token(){
        // we quickly confirm our envato token by loading the my accounts page
        // we pull in the users email and picture from the account page and convert it into a shub_user
        // store this in the shub_user_id field so we can use it when rendering the UI for comments etc..
        $api = $this->get_api();
        $account_data = $api->get_token_account();
        if($account_data && !empty($account_data['username'])){
            // success
            $comment_user = new SupportHubUser_Envato();
            $res = $comment_user->load_by( 'user_username', $account_data['username']);
            if(!$res){
                $comment_user -> create_new();
                if(!$comment_user->get('user_username'))$comment_user -> update('user_username', $account_data['username']);
                if(!empty($account_data['image'])){
                    $comment_user -> update_user_data(array(
                        'image' => $account_data['image'],
                    ));
                }
            }
            $shub_user_id = $comment_user->get('shub_user_id');
            if($shub_user_id){
                $this->update('shub_user_id',$shub_user_id);
            }
            return $shub_user_id;
        }else{
            echo "Failed to get account data. Please confirm the envato token is correct as per the help documentation. Without this you will be unable to post comment replies.";
            return false;
        }
    }
	public function load_available_items(){
		// serialise this result into envato_data.

		$api = $this->get_api();

		// get user details and confirm username works.
		$api_result = $api->api('v1/market/user:'.$this->get('envato_name').'.json');
		if($api_result && isset($api_result['user']) && is_array($api_result['user']) && isset($api_result['user']['username']) && $api_result['user']['username'] == $this->get('envato_name')){
			$this->save_account_data($api_result);
		}else{
			echo 'Failed to verify username '.htmlspecialchars($this->get('envato_name')).'. Please ensure this is correct and try again.';
			return false;
		}
		$api_result = $api->api('v1/market/user-items-by-site:' . $this->get('envato_name') . '.json');
		if($api_result && isset($api_result['user-items-by-site']) && is_array($api_result['user-items-by-site'])){
			$items = array();
			foreach($api_result['user-items-by-site'] as $items_by_site){
				$site_api_result = $api->api('v1/market/new-files-from-user:' . $this->get('envato_name') . ',' . strtolower($items_by_site['site']) .  '.json');
				if($site_api_result && isset($site_api_result['new-files-from-user']) && is_array($site_api_result['new-files-from-user'])){
					foreach($site_api_result['new-files-from-user'] as $item){
						$item['site'] = $items_by_site['site'];
						$items[$item['id']] = $item;
					}
				}
			}
			// yes, this member has some items, save these items to the account ready for selection in the settings area.
			$save_data = $this->get('envato_data');
			if(!is_array($save_data))$save_data=array();
			// create a product for each of these items (if a matching one doesn't already exist)
			$existing_products = SupportHub::getInstance()->get_products();
			foreach($items as $key => $envato_item){
				// check if this item exists already
				$exists = false;
				foreach($existing_products as $existing_product){
					if(isset($existing_product['product_data']['envato_item_id']) && $existing_product['product_data']['envato_item_id'] == $envato_item['id']){
						$exists = $existing_product['shub_product_id'];
					}
				}
				$newproduct = new SupportHubProduct();
				if(!$exists) {
					$newproduct->create_new();
				}else {
					$newproduct->load( $exists );
				}
				$newproduct->update('product_name',$envato_item['item']);
				$newproduct->update('product_data',array(
					'envato_item_id' => $envato_item['id'],
					'envato_item_data' => $envato_item,
					'image' => isset($envato_item['thumbnail']) ? $envato_item['thumbnail'] : false,
					'url' => isset($envato_item['url']) ? $envato_item['url'] : false,
				));
				$items[$key]['shub_product_id'] = $newproduct->get('shub_product_id');
			}
			$save_data['items'] = $items;
			$this->update('envato_data',$save_data);
		}
	}

	private $api = false;
	public function get_api(){
		if(!$this->api){

			require_once trailingslashit(dirname(_DTBAKER_SUPPORT_HUB_CORE_FILE_)) . 'extensions/envato/class.envato-api.php';

            $this->api = envato_api_basic::getInstance();
            $this->api->set_personal_token($this->get( 'envato_token' ));
            $this->api->set_client_id($this->get( 'envato_app_id' ));
            $this->api->set_client_secret($this->get( 'envato_app_secret' ));
            $this->api->set_redirect_url($this->generate_oauth_redirect_url());
            $this->api->set_cookie($this->get( 'envato_cookie' ));

		}
		return $this->api;
	}
	public function generate_oauth_redirect_url(){
		return add_query_arg(_SHUB_ENVATO_OAUTH_DOING_FLAG,'ok',home_url());
	}

	public function get_picture(){
		$data = $this->get('envato_data');
		return $data && isset($data['pictureUrl']) && !empty($data['pictureUrl']) ? $data['pictureUrl'] : false;
	}

	/**
	 * Links for wordpress
	 */
	public function link_connect(){
		return 'admin.php?page=support_hub_settings&tab=envato&envato_do_oauth_connect&shub_account_id='.$this->get('shub_account_id');
	}
	public function link_edit(){
		return 'admin.php?page=support_hub_settings&tab=envato&shub_account_id='.$this->get('shub_account_id');
	}
	public function link_new_message(){
		return 'admin.php?page=support_hub_main&shub_account_id='.$this->get('shub_account_id').'&shub_message_id=new';
	}


	public function link_refresh(){
		return 'admin.php?page=support_hub_settings&tab=envato&manualrefresh&shub_account_id='.$this->get('shub_account_id').'&envato_stream=true';
	}

}
