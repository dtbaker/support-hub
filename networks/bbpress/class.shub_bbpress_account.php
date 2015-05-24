<?php

class shub_bbpress_account{

	public function __construct($shub_bbpress_id){
		$this->load($shub_bbpress_id);
	}

	private $shub_bbpress_id = false; // the current user id in our system.
    private $details = array();

	/* @var $forums shub_bbpress_forum[] */
    private $forums = array();


	private $json_fields = array('bbpress_data');

	private function reset(){
		$this->shub_bbpress_id = false;
		$this->details = array(
			'shub_bbpress_id' => false,
			'bbpress_name' => false,
			'last_checked' => false,
			'bbpress_data' => array(),
			'bbpress_wordpress_xmlrpc' => false,
			'bbpress_username' => false,
			'bbpress_password' => false,
		);
	    $this->forums = array();
		foreach($this->details as $field_id => $field_data){
			$this->{$field_id} = $field_data;
		}
	}

	public function create_new(){
		$this->reset();
		$this->shub_bbpress_id = shub_update_insert('shub_bbpress_id',false,'shub_bbpress',array());
		$this->load($this->shub_bbpress_id);
	}

    public function load($shub_bbpress_id = false){
	    if(!$shub_bbpress_id)$shub_bbpress_id = $this->shub_bbpress_id;
	    $this->reset();
	    $this->shub_bbpress_id = (int)$shub_bbpress_id;
        if($this->shub_bbpress_id){
            $data = shub_get_single('shub_bbpress','shub_bbpress_id',$this->shub_bbpress_id);
	        foreach($this->details as $key=>$val){
		        $this->details[$key] = $data && isset($data[$key]) ? $data[$key] : $val;
		        if(in_array($key,$this->json_fields)){
			        $this->details[$key] = @json_decode($this->details[$key],true);
			        if(!is_array($this->details[$key]))$this->details[$key] = array();
		        }
	        }
	        if(!is_array($this->details) || $this->details['shub_bbpress_id'] != $this->shub_bbpress_id){
		        $this->reset();
		        return false;
	        }
        }
        foreach($this->details as $key=>$val){
            $this->{$key} = $val;
        }
	    $this->forums = array();
	    if(!$this->shub_bbpress_id)return false;
	    foreach(shub_get_multiple('shub_bbpress_forum',array('shub_bbpress_id'=>$this->shub_bbpress_id),'shub_bbpress_forum_id') as $forum){
		    $forum = new shub_bbpress_forum($this, $forum['shub_bbpress_forum_id']);
		    $this->forums[$forum->get('forum_id')] = $forum;
	    }
        return $this->shub_bbpress_id;
    }

	public function get($field){
		return isset($this->{$field}) ? $this->{$field} : false;
	}

	public function save_data($post_data){
		if(!$this->get('shub_bbpress_id')){
			$this->create_new();
		}
		if(is_array($post_data)){
			foreach($this->details as $details_key => $details_val){
				if(isset($post_data[$details_key])){
					if(($details_key == 'bbpress_password') && $post_data[$details_key] == 'password')continue;
					$this->update($details_key,$post_data[$details_key]);
				}
			}
		}
		if(!isset($post_data['import_stream'])){
			$this->update('import_stream', 0);
		}
		// save the active bbpress forums.
		if(isset($post_data['save_bbpress_forums']) && $post_data['save_bbpress_forums'] == 'yep') {
			$currently_active_forums = $this->forums;
			$data = $this->get('bbpress_data');
			$available_forums = isset($data['forums']) && is_array($data['forums']) ? $data['forums'] : array();
			if(isset($post_data['bbpress_forum']) && is_array($post_data['bbpress_forum'])){
				foreach($post_data['bbpress_forum'] as $bbpress_forum_id => $yesno){
					if(isset($currently_active_forums[$bbpress_forum_id])){
						if(isset($post_data['bbpress_forum_product'][$bbpress_forum_id])){
							$currently_active_forums[$bbpress_forum_id]->update('shub_product_id',$post_data['bbpress_forum_product'][$bbpress_forum_id]);
						}
						unset($currently_active_forums[$bbpress_forum_id]);
					}
					if($yesno && isset($available_forums[$bbpress_forum_id])){
						// we are adding this forum to the list. check if it doesn't already exist.
						if(!isset($this->forums[$bbpress_forum_id])){
							$forum = new shub_bbpress_forum($this);
							$forum->create_new();
							$forum->update('shub_bbpress_id', $this->shub_bbpress_id);
							$forum->update('bbpress_token', 'same'); // $available_forums[$bbpress_forum_id]['access_token']
							$forum->update('forum_name', $available_forums[$bbpress_forum_id]['forum']);
							$forum->update('forum_id', $bbpress_forum_id);
							$forum->update('bbpress_data', $available_forums[$bbpress_forum_id]);
							$forum->update('shub_product_id', isset($post_data['bbpress_forum_product'][$bbpress_forum_id]) ? $post_data['bbpress_forum_product'][$bbpress_forum_id] : 0);
						}
					}
				}
			}
			// remove any forums that are no longer active.
			foreach($currently_active_forums as $forum){
				$forum->delete();
			}
		}
		$this->load();
		return $this->get('shub_bbpress_id');
	}
    public function update($field,$value){
	    // what fields to we allow? or not allow?
	    if(in_array($field,array('shub_bbpress_id')))return;
        if($this->shub_bbpress_id){
            $this->{$field} = $value;
	        if(in_array($field,$this->json_fields)){
		        $value = json_encode($value);
	        }
            shub_update_insert('shub_bbpress_id',$this->shub_bbpress_id,'shub_bbpress',array(
	            $field => $value,
            ));
        }
    }
	public function delete(){
		if($this->shub_bbpress_id) {
			// delete all the forums for this twitter account.
			$forums = $this->get('forums');
			foreach($forums as $forum){
				$forum->delete();
			}
			shub_delete_from_db( 'shub_bbpress', 'shub_bbpress_id', $this->shub_bbpress_id );
		}
	}

	public function is_active(){
		// is there a 'last_checked' date?
		if(!$this->get('last_checked')){
			return false; // never checked this account, not active yet.
		}else{
			// do we have a token?
			if($this->get('bbpress_token')){
				// assume we have access, we remove the token if we get a bbpress failure at any point.
				return true;
			}
		}
		return false;
	}

	public function is_forum_active($bbpress_forum_id){
		if(isset($this->forums[$bbpress_forum_id]) && $this->forums[$bbpress_forum_id]->get('forum_id') == $bbpress_forum_id){
			return true;
		}else{
			return false;
		}
	}

	public function save_account_data($user_data){
		// serialise this result into bbpress_data.
		if(is_array($user_data)){
			// yes, this member has some forums, save these forums to the account ready for selection in the settings area.
			$save_data = $this->get('bbpress_data');
			if(!is_array($save_data))$save_data=array();
			$save_data = array_merge($save_data,$user_data);
			$this->update('bbpress_data',$save_data);
		}
	}

	public function load_available_forums(){
		// serialise this result into bbpress_data.

		$api = $this->get_api();

		// get available post types, check for bbpress forum posts
		$api_result = $api->getPostTypes();
		print_r($api_result);exit;
		if($api_result && isset($api_result['user']) && is_array($api_result['user']) && isset($api_result['user']['username']) && $api_result['user']['username'] == $this->get('bbpress_name')){
			$this->save_account_data($api_result);
		}else{
			echo 'Failed to verify username '.htmlspecialchars($this->get('bbpress_name')).'. Please ensure this is correct and try again.';
			return false;
		}
		$api_result = $api->api('market/user-forums-by-site:' . $this->get('bbpress_name') . '.json');
		if($api_result && isset($api_result['user-forums-by-site']) && is_array($api_result['user-forums-by-site'])){
			$forums = array();
			foreach($api_result['user-forums-by-site'] as $forums_by_site){
				$site_api_result = $api->api('market/new-files-from-user:' . $this->get('bbpress_name') . ',' . strtolower($forums_by_site['site']) .  '.json');
				if($site_api_result && isset($site_api_result['new-files-from-user']) && is_array($site_api_result['new-files-from-user'])){
					foreach($site_api_result['new-files-from-user'] as $forum){
						$forum['site'] = $forums_by_site['site'];
						$forums[$forum['id']] = $forum;
					}
				}
			}
			// yes, this member has some forums, save these forums to the account ready for selection in the settings area.
			$save_data = $this->get('bbpress_data');
			if(!is_array($save_data))$save_data=array();
			// create a product for each of these forums (if a matching one doesn't already exist)
			$existing_products = SupportHub::getInstance()->get_products();
			foreach($forums as $key => $bbpress_forum){
				// check if this forum exists already
				$exists = false;
				foreach($existing_products as $existing_product){
					if(isset($existing_product['product_data']['bbpress_forum_id']) && $existing_product['product_data']['bbpress_forum_id'] == $bbpress_forum['id']){
						$exists = $existing_product['shub_product_id'];
					}
				}
				$newproduct = new SupportHubProduct();
				if(!$exists) {
					$newproduct->create_new();
				}else {
					$newproduct->load( $exists );
				}
				$newproduct->update('product_name',$bbpress_forum['forum']);
				$newproduct->update('product_data',array(
					'bbpress_forum_id' => $bbpress_forum['id'],
					'bbpress_forum_data' => $bbpress_forum,
				));
				$forums[$key]['shub_product_id'] = $newproduct->get('shub_product_id');
			}
			$save_data['forums'] = $forums;
			$this->update('bbpress_data',$save_data);
		}
	}

	public function run_cron( $debug = false ){


	}

	private static $api = false;
	public function get_api($use_db_code = true){
		if(!self::$api){

			//$wpLog = new Monolog\Logger('wp-xmlrpc');
			self::$api = new \HieuLe\WordpressXmlrpcClient\WordpressClient($this->get( 'bbpress_wordpress_xmlrpc' ), $this->get( 'bbpress_username' ), $this->get( 'bbpress_password' ));


			//$wpClient->setCredentials($this->get( 'bbpress_wordpress_xmlrpc' ), 'username', 'password');

		}
		return self::$api;
	}

	public function get_picture(){
		$data = $this->get('bbpress_data');
		return $data && isset($data['pictureUrl']) && !empty($data['pictureUrl']) ? $data['pictureUrl'] : false;
	}
	

	/**
	 * Links for wordpress
	 */
	public function link_connect(){
		return 'admin.php?page=support_hub_settings&tab=bbpress&bbpress_do_oauth_connect&shub_bbpress_id='.$this->get('shub_bbpress_id');
	}
	public function link_edit(){
		return 'admin.php?page=support_hub_settings&tab=bbpress&shub_bbpress_id='.$this->get('shub_bbpress_id');
	}
	public function link_new_message(){
		return 'admin.php?page=support_hub_main&shub_bbpress_id='.$this->get('shub_bbpress_id').'&shub_bbpress_message_id=new';
	}


	public function link_refresh(){
		return 'admin.php?page=support_hub_settings&tab=bbpress&manualrefresh&shub_bbpress_id='.$this->get('shub_bbpress_id').'&bbpress_stream=true';
	}

}
