<?php

class shub_bbpress_account extends SupportHub_account{

    public function __construct($shub_account_id){
        parent::__construct($shub_account_id);
        $this->shub_extension = 'bbpress';
    }


	public function load_available_items(){
		// serialise this result into account_data.

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
					'items' => $forums,
				));
				return true;
			}else{
				echo 'Failed to find any forums, please create some.';
				return false;
			}

		}else{
			echo 'Failed to find any forums';
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

    // todo: move this into the bbpress_user class.
	public function get_api_user_to_id($wp_user_id){
		if((int)$wp_user_id > 0) {
		    $wordpress_user = $this->get_api_user($wp_user_id);
		    /* Array ( [user_id] => 1442 [username] => palumboe1 [registered] => stdClass Object ( [scalar] => 20150303T19:24:05 [xmlrpc_type] => datetime [timestamp] => 1425410645 ) [email] => palumboe1@gmail.com [nicename] => palumboe1 [display_name] => palumboe1 [support_hub] => done ) */
		    if($wordpress_user && !empty($wordpress_user['user_id']) && $wordpress_user['user_id'] == $wp_user_id){
			    $comment_user = new SupportHubUser_bbPress();
                $envato_username = false;
                if(!empty($wordpress_user['envato_codes'])){
                    foreach ($wordpress_user['envato_codes'] as $code => $purchase_data) {
                        // check if this code is legit with the envato module
                        // only if the envato module is active
                        if(isset(SupportHub::getInstance()->message_managers['envato'])){
                            $result = SupportHub::getInstance()->message_managers['envato']->pull_purchase_code(false, $code, array());
                            if($result && !empty($result['shub_user_id'])){
                                $comment_user->load($result['shub_user_id']);
                                SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_INFO, 'bbpress', 'Found a user based on envato code.', array(
                                    'code' => $code,
                                    'found_user_id' => $comment_user->get('shub_user_id'),
                                ));
                                break;
                            }else{
                                SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_INFO, 'bbpress', 'DIDNT Find a user based on envato username.', array(
                                    'code' => $code,
                                ));
                            }
                        }
                        /*if(!empty($purchase_data['buyer'])){
                            $envato_username = trim(strtolower($purchase_data['buyer']));
                        }
                        if ($comment_user->load_by_meta('envato_license_code', strtolower($code))) {
                            // found! yay!
                            SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_INFO,'bbpress','Found a user based on license code.',array(
                                'license_code' => $code,
                                'found_user_id' => $comment_user->get('shub_user_id'),
                            ));
                            break;
                        }*/
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
                    //if(!isset($user_data['envato_codes']))$user_data['envato_codes']=array();
                    //$user_data['envato_codes'] = array_merge($user_data['envato_codes'], $wordpress_user['envato_codes']);
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
                    // find one based on wordpress user id meta. should never reach here though
                    $comment_user->load_by_meta('wordpress_user_id',$wordpress_user['user_id']);
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
                $comment_user->add_unique_meta('wordpress_user_id',$wordpress_user['user_id']);
                /*if(!empty($wordpress_user['envato_codes'])) {
                    foreach ($wordpress_user['envato_codes'] as $code => $purchase_data) {
                        if(!$comment_user->get_meta('envato_license_code', strtolower($code))){
                            $comment_user->add_meta('envato_license_code', strtolower($code));
                        }
                    }
                }*/
                if ( ! $comment_user->get( 'user_username' ) && !empty($wordpress_user['username']) ) {
                    $comment_user->update( 'user_username', $wordpress_user['username'] );
                }

                $comment_user->update_user_data(array('wordpress'=>$wordpress_user));

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
		$data = $this->get('account_data');
		return $data && isset($data['pictureUrl']) && !empty($data['pictureUrl']) ? $data['pictureUrl'] : false;
	}


    public function get_item($shub_item_id){
        return new shub_bbpress_item($this, $shub_item_id);
    }
	

}
