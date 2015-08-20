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
		$this->shub_bbpress_id = shub_update_insert('shub_bbpress_id',false,'shub_bbpress',array(
            'bbpress_name' => '',
        ));
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
							$forum->update('forum_name', $available_forums[$bbpress_forum_id]['post_title']);
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


		$api_result = $api->getProfile();
		SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_INFO, 'bbpress', 'API Result: ', $api_result);
		if($api_result && $api_result['user_id']){
			if(!isset($api_result['support_hub'])){
				echo "Error, the required PHP code has not been added to your bbPress installation. Please check the Support Hub documentation.";
				return;
			}
			// this api_result will contain additional actions that we presentto the user when composing messages
            // for example ( mark thread as resolved )
			$api_result['shub_user_id'] = $this->get_api_user_to_id($api_result['user_id']);
			$this->save_account_data(array(
				'user' => $api_result,
                'reply_options' => isset($api_result['support_hub']['reply_options']) ? $api_result['support_hub']['reply_options'] : array()
			));
		}else{
			echo 'Failed to get profile from WP Api';
			return;
		}

		// get available post types, check for bbpress forum posts
		$api_result = $api->getPostTypes();
		//echo '<pre>';print_r($api_result);exit;
		SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_INFO, 'bbpress', 'API Result: ', $api_result);
		if($api_result && isset($api_result['forum']) && isset($api_result['topic']) && isset($api_result['reply'])){
			// we have forum, topic and reply post types, so I guess bbpress is working.
			$api_result = $api->getPosts(array(
				'post_type' => 'forum',
                'number' => 50,
			));
			//echo '<pre>';print_r($api_result);exit;
			SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_INFO, 'bbpress', 'API Result: ', $api_result);
			if(count($api_result)){
				$forums = array();
				foreach($api_result as $forum){
					$forums[$forum['post_id']] = $forum;
				}
				$this->save_account_data(array(
					'forums' => $forums,
				));
				return true;
			}else{
				echo 'Failed to find any forums, please create some.';
				return false;
			}

		}else{
			echo 'Failed to find forums';
			SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_ERROR, 'bbpress', 'Unable to find TOPIC and REPLY post types through the WP API, maybe bbPress is not installed?');
			return false;
		}
	}

	public function run_cron( $debug = false ){


	}

	private $api = false;
	public function get_api($use_db_code = true){
		if(!$this->api){

			//$wpLog = new Monolog\Logger('wp-xmlrpc');
            $this->api = new \HieuLe\WordpressXmlrpcClient\WordpressClient($this->get( 'bbpress_wordpress_xmlrpc' ), $this->get( 'bbpress_username' ), $this->get( 'bbpress_password' ));
            $this->api->onSending(function($event){
			    SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_INFO, 'bbpress', 'API Call: '.$event['endpoint'], array(
				    'method' => $event['method'],
				    'params' => $event['params'],
				    'request' => $event['request'],
			    ));
			});

            $this->api->onError(function($error, $event){
			    SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_ERROR, 'bbpress', 'API Error: '.$event['endpoint']. ' ('.$error.')', $event);
			});


			//$wpClient->setCredentials($this->get( 'bbpress_wordpress_xmlrpc' ), 'username', 'password');

		}
		return $this->api;
	}
	public function get_api_user_to_id($wp_user_id){
		if((int)$wp_user_id > 0) {
		    $wordpress_user = $this->get_api_user($wp_user_id);
		    /* Array ( [user_id] => 1442 [username] => palumboe1 [registered] => stdClass Object ( [scalar] => 20150303T19:24:05 [xmlrpc_type] => datetime [timestamp] => 1425410645 ) [email] => palumboe1@gmail.com [nicename] => palumboe1 [display_name] => palumboe1 [support_hub] => done ) */
		    if($wordpress_user && !empty($wordpress_user['user_id']) && $wordpress_user['user_id'] == $wp_user_id){
			    $comment_user = new SupportHubUser_bbPress();
                $envato_username = false;
                if(!empty($wordpress_user['envato_codes'])){
                    foreach ($wordpress_user['envato_codes'] as $code => $purchase_data) {
                        if(!empty($purchase_data['buyer'])){
                            $envato_username = trim(strtolower($purchase_data['buyer']));
                        }
                        if ($comment_user->load_by_meta('envato_license_code', strtolower($code))) {
                            // found! yay!
                            SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_INFO,'bbpress','Found a user based on license code.',array(
                                'license_code' => $code,
                                'found_user_id' => $comment_user->get('shub_user_id'),
                            ));
                            break;
                        }
                    }
                    if(!$comment_user->get('shub_user_id')){
                        // didn't find one yet.
                        // find by envato username?
                        foreach ($wordpress_user['envato_codes'] as $code => $purchase_data) {
                            if (!empty($purchase_data['buyer']) && $comment_user->load_by_meta('envato_username', strtolower($purchase_data['buyer']))) {
                                // found! yay!
                                SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_INFO, 'bbpress', 'Found a user based on envato username.', array(
                                    'username' => $purchase_data['buyer'],
                                    'found_user_id' => $comment_user->get('shub_user_id'),
                                ));
                            }
                        }
                    }
                    if(!isset($user_data['envato_codes']))$user_data['envato_codes']=array();
                    $user_data['envato_codes'] = array_merge($user_data['envato_codes'], $wordpress_user['envato_codes']);
                }
			    $wordpress_user['email'] = trim(strtolower($wordpress_user['email']));
			    if(!empty($wordpress_user['email'])){
                    if(!$comment_user->get('shub_user_id')){
                        // find a match based on email.
                        if(!empty($wordpress_user['email'])){
                            $comment_user->load_by( 'user_email', $wordpress_user['email']);
                        }
                    }
			    }
			    if(!$comment_user->get('shub_user_id')) {
				    $comment_user->create_new();
			    }
                // now we add/update various meta/values of the user if anything is missing.
                if(!empty($wordpress_user['email']) && !$comment_user->get('user_email')) {
                    $comment_user->update('user_email', $wordpress_user['email']);
                }
                if($envato_username && !$comment_user->get_meta('envato_username',$envato_username)){
                    $comment_user->add_meta('envato_username', $envato_username);
                }
                if(!empty($wordpress_user['envato_codes'])) {
                    foreach ($wordpress_user['envato_codes'] as $code => $purchase_data) {
                        if(!$comment_user->get_meta('envato_license_code', strtolower($code))){
                            $comment_user->add_meta('envato_license_code', strtolower($code));
                        }
                    }
                }
                if ( ! $comment_user->get( 'user_username' ) && !empty($wordpress_user['username']) ) {
                    $comment_user->update( 'user_username', $wordpress_user['username'] );
                }

			    /*$user_data['source'] = array_merge(isset($user_data['source']) ? $user_data['source'] : array(), array(
				    'bbpress'
			    ));*/
			    return $comment_user->get('shub_user_id');
		    }
	    }
		return false;
	}
	public function get_api_user($wp_user_id){
		if(!(int)$wp_user_id)return false;
		// seed the cache with the latest existing user details.
		// generally the posts will come from recent users so it's quicker to get this bulk list of recent users and loop through that, compared to hitting the API for each user details on each forum post.
		$filter_user = array(
			'number' => 100,
		);
		$api = $this->get_api();
        try {
            //$api_user = $api->getUser($wp_user_id,array('username','basic','envato_codes')); print_r($api_user); exit;
            $api_result_latest_users = $this->get_api_cache($filter_user);
            if (!$api_result_latest_users) {
                $api_users = $api->getUsers($filter_user, array('basic', 'envato_codes'));
                $api_result_latest_users = array();
                foreach ($api_users as $api_user) {
                    $api_result_latest_users[$api_user['user_id']] = $api_user;
                }
                unset($api_users);
            }
            $this->set_api_cache($filter_user, $api_result_latest_users);
            // if this user doesn't exist in the latest listing we grab it
            if (!isset($api_result_latest_users[$wp_user_id])) {
                $api_user = $api->getUser($wp_user_id, array('basic', 'envato_codes'));
                if ($api_user && $api_user['user_id'] == $wp_user_id) {
                    $api_result_latest_users[$api_user['user_id']] = $api_user;
                }
            }
            $this->set_api_cache($filter_user, $api_result_latest_users);
        }catch(Exception $e){
            SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_ERROR,'bbpress','API Error during get_api_user() : '.$e->getMessage());
        }
		return isset($api_result_latest_users[$wp_user_id]) ? $api_result_latest_users[$wp_user_id] : false;

	}
	private static $api_cache = array();
	public function get_api_cache($filter){
		$key = md5(serialize(array($this->details,$filter)));
		if(isset(self::$api_cache[$key]))return self::$api_cache[$key];
		return false;
	}
	public function set_api_cache($filter,$data){
		$key = md5(serialize(array($this->details,$filter)));
		self::$api_cache[$key] = $data;
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