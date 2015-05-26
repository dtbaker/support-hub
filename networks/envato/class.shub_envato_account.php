<?php

class shub_envato_account{

	public function __construct($shub_envato_id){
		$this->load($shub_envato_id);
	}

	private $shub_envato_id = false; // the current user id in our system.
    private $details = array();

	/* @var $items shub_envato_item[] */
    private $items = array();


	private $json_fields = array('envato_data');

	private function reset(){
		$this->shub_envato_id = false;
		$this->details = array(
			'shub_envato_id' => false,
			'envato_name' => false,
			'last_checked' => false,
			'envato_data' => array(),
			'envato_app_id' => false,
			'envato_app_secret' => false,
			'envato_token' => false,
			'envato_cookie' => false,
			'import_stream' => false,
		);
	    $this->items = array();
		foreach($this->details as $field_id => $field_data){
			$this->{$field_id} = $field_data;
		}
	}

	public function create_new(){
		$this->reset();
		$this->shub_envato_id = shub_update_insert('shub_envato_id',false,'shub_envato',array());
		$this->load($this->shub_envato_id);
	}

    public function load($shub_envato_id = false){
	    if(!$shub_envato_id)$shub_envato_id = $this->shub_envato_id;
	    $this->reset();
	    $this->shub_envato_id = (int)$shub_envato_id;
        if($this->shub_envato_id){
            $data = shub_get_single('shub_envato','shub_envato_id',$this->shub_envato_id);
	        foreach($this->details as $key=>$val){
		        $this->details[$key] = $data && isset($data[$key]) ? $data[$key] : $val;
		        if(in_array($key,$this->json_fields)){
			        $this->details[$key] = @json_decode($this->details[$key],true);
			        if(!is_array($this->details[$key]))$this->details[$key] = array();
		        }
	        }
	        if(!is_array($this->details) || $this->details['shub_envato_id'] != $this->shub_envato_id){
		        $this->reset();
		        return false;
	        }
        }
        foreach($this->details as $key=>$val){
            $this->{$key} = $val;
        }
	    $this->items = array();
	    if(!$this->shub_envato_id)return false;
	    foreach(shub_get_multiple('shub_envato_item',array('shub_envato_id'=>$this->shub_envato_id),'shub_envato_item_id') as $item){
		    $item = new shub_envato_item($this, $item['shub_envato_item_id']);
		    $this->items[$item->get('item_id')] = $item;
	    }
        return $this->shub_envato_id;
    }

	public function get($field){
		return isset($this->{$field}) ? $this->{$field} : false;
	}

	public function save_data($post_data){
		if(!$this->get('shub_envato_id')){
			$this->create_new();
		}
		if(is_array($post_data)){
			foreach($this->details as $details_key => $details_val){
				if(isset($post_data[$details_key])){
					if(($details_key == 'envato_app_secret' || $details_key == 'envato_token' || $details_key == 'envato_cookie') && $post_data[$details_key] == 'password')continue;
					$this->update($details_key,$post_data[$details_key]);
				}
			}
		}
		if(!isset($post_data['import_stream'])){
			$this->update('import_stream', 0);
		}
		// save the active envato items.
		if(isset($post_data['save_envato_items']) && $post_data['save_envato_items'] == 'yep') {
			$currently_active_items = $this->items;
			$data = $this->get('envato_data');
			$available_items = isset($data['items']) && is_array($data['items']) ? $data['items'] : array();
			if(isset($post_data['envato_item']) && is_array($post_data['envato_item'])){
				foreach($post_data['envato_item'] as $envato_item_id => $yesno){
					if(isset($currently_active_items[$envato_item_id])){
						if(isset($post_data['envato_item_product'][$envato_item_id])){
							$currently_active_items[$envato_item_id]->update('shub_product_id',$post_data['envato_item_product'][$envato_item_id]);
						}
						unset($currently_active_items[$envato_item_id]);
					}
					if($yesno && isset($available_items[$envato_item_id])){
						// we are adding this item to the list. check if it doesn't already exist.
						if(!isset($this->items[$envato_item_id])){
							$item = new shub_envato_item($this);
							$item->create_new();
							$item->update('shub_envato_id', $this->shub_envato_id);
							$item->update('envato_token', 'same'); // $available_items[$envato_item_id]['access_token']
							$item->update('item_name', $available_items[$envato_item_id]['item']);
							$item->update('item_id', $envato_item_id);
							$item->update('envato_data', $available_items[$envato_item_id]);
							$item->update('shub_product_id', isset($post_data['envato_item_product'][$envato_item_id]) ? $post_data['envato_item_product'][$envato_item_id] : 0);
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
		return $this->get('shub_envato_id');
	}
    public function update($field,$value){
	    // what fields to we allow? or not allow?
	    if(in_array($field,array('shub_envato_id')))return;
        if($this->shub_envato_id){
            $this->{$field} = $value;
	        if(in_array($field,$this->json_fields)){
		        $value = json_encode($value);
	        }
            shub_update_insert('shub_envato_id',$this->shub_envato_id,'shub_envato',array(
	            $field => $value,
            ));
        }
    }
	public function delete(){
		if($this->shub_envato_id) {
			// delete all the items for this twitter account.
			$items = $this->get('items');
			foreach($items as $item){
				$item->delete();
			}
			shub_delete_from_db( 'shub_envato', 'shub_envato_id', $this->shub_envato_id );
		}
	}

	public function is_active(){
		// is there a 'last_checked' date?
		if(!$this->get('last_checked')){
			return false; // never checked this account, not active yet.
		}else{
			// do we have a token?
			if($this->get('envato_token')){
				// assume we have access, we remove the token if we get a envato failure at any point.
				return true;
			}
		}
		return false;
	}

	public function is_item_active($envato_item_id){
		if(isset($this->items[$envato_item_id]) && $this->items[$envato_item_id]->get('item_id') == $envato_item_id){
			return true;
		}else{
			return false;
		}
	}

	public function save_account_data($user_data){
		// serialise this result into envato_data.
		if(is_array($user_data)){
			// yes, this member has some items, save these items to the account ready for selection in the settings area.
			$save_data = $this->get('envato_data');
			if(!is_array($save_data))$save_data=array();
			$save_data = array_merge($save_data,$user_data);
			$this->update('envato_data',$save_data);
		}
	}

	public function load_available_items(){
		// serialise this result into envato_data.

		$api = $this->get_api();

		// get user details and confirm username works.
		$api_result = $api->api('market/user:'.$this->get('envato_name').'.json');
		if($api_result && isset($api_result['user']) && is_array($api_result['user']) && isset($api_result['user']['username']) && $api_result['user']['username'] == $this->get('envato_name')){
			$this->save_account_data($api_result);
		}else{
			echo 'Failed to verify username '.htmlspecialchars($this->get('envato_name')).'. Please ensure this is correct and try again.';
			return false;
		}
		$api_result = $api->api('market/user-items-by-site:' . $this->get('envato_name') . '.json');
		if($api_result && isset($api_result['user-items-by-site']) && is_array($api_result['user-items-by-site'])){
			$items = array();
			foreach($api_result['user-items-by-site'] as $items_by_site){
				$site_api_result = $api->api('market/new-files-from-user:' . $this->get('envato_name') . ',' . strtolower($items_by_site['site']) .  '.json');
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

	public function run_cron( $debug = false ){


	}

	private static $api = false;
	public function get_api($use_db_code = true){
		if(!self::$api){

			require_once trailingslashit(dirname(_DTBAKER_SUPPORT_HUB_CORE_FILE_)) . 'networks/envato/class.envato-api.php';

			self::$api = envato_api_basic::getInstance();
			self::$api->set_personal_token($this->get( 'envato_token' ));
			self::$api->set_cookie($this->get( 'envato_cookie' ));

		}
		return self::$api;
	}

	public function get_picture(){
		$data = $this->get('envato_data');
		return $data && isset($data['pictureUrl']) && !empty($data['pictureUrl']) ? $data['pictureUrl'] : false;
	}
	

	/**
	 * Links for wordpress
	 */
	public function link_connect(){
		return 'admin.php?page=support_hub_settings&tab=envato&envato_do_oauth_connect&shub_envato_id='.$this->get('shub_envato_id');
	}
	public function link_edit(){
		return 'admin.php?page=support_hub_settings&tab=envato&shub_envato_id='.$this->get('shub_envato_id');
	}
	public function link_new_message(){
		return 'admin.php?page=support_hub_main&shub_envato_id='.$this->get('shub_envato_id').'&shub_envato_message_id=new';
	}


	public function link_refresh(){
		return 'admin.php?page=support_hub_settings&tab=envato&manualrefresh&shub_envato_id='.$this->get('shub_envato_id').'&envato_stream=true';
	}

}
