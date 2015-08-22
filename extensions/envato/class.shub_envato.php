<?php

class shub_envato extends SupportHub_extension {



	public function init(){
		if(isset($_GET[_SHUB_ENVATO_OAUTH_DOING_FLAG]) && strlen($_GET[_SHUB_ENVATO_OAUTH_DOING_FLAG]) > 0){
			// we're doing an oauth callback, grab the code and redirect back to the login url.
			if(!headers_sent() && !session_id()){
				session_start();
			}
			if(!empty($_SESSION['shub_oauth_doing_envato'])){
				$_SESSION['shub_oauth_doing_envato']['code'] = isset($_GET['code']) ? $_GET['code'] : false;
				header("Location: ".$_SESSION['shub_oauth_doing_envato']['url']);
				exit;
			}
			echo "Oauth failed, please go back and try again.";
			exit;
		}
        parent::init();
        add_filter('supporthub_message_user_sidebar',array($this,'filter_message_user_sidebar'),10,2);

	}

	public function page_assets($from_master=false){
		if(!$from_master)SupportHub::getInstance()->inbox_assets();

		wp_register_style( 'support-hub-envato-css', plugins_url('extensions/envato/shub_envato.css',_DTBAKER_SUPPORT_HUB_CORE_FILE_), array(), '1.0.0' );
		wp_enqueue_style( 'support-hub-envato-css' );
		wp_register_script( 'support-hub-envato', plugins_url('extensions/envato/shub_envato.js',_DTBAKER_SUPPORT_HUB_CORE_FILE_), array( 'jquery' ), '1.0.0' );
		wp_enqueue_script( 'support-hub-envato' );

	}

	public function settings_page(){
		include( dirname(__FILE__) . '/envato_settings.php');
	}


	public function compose_to(){
		$accounts = $this->get_accounts();
	    if(!count($accounts)){
		    _e('No accounts configured', 'support_hub');
	    }
		foreach ( $accounts as $account ) {
			$envato_account = new shub_envato_account( $account['shub_account_id'] );
			echo '<div class="envato_compose_account_select">' .
				     '<input type="checkbox" name="compose_envato_id[' . $account['shub_account_id'] . '][share]" value="1"> ' .
				     ($envato_account->get_picture() ? '<img src="'.$envato_account->get_picture().'">' : '' ) .
				     '<span>' . htmlspecialchars( $envato_account->get( 'account_name' ) ) . ' (status update)</span>' .
				     '</div>';
			/*echo '<div class="envato_compose_account_select">' .
				     '<input type="checkbox" name="compose_envato_id[' . $account['shub_account_id'] . '][blog]" value="1"> ' .
				     ($envato_account->get_picture() ? '<img src="'.$envato_account->get_picture().'">' : '' ) .
				     '<span>' . htmlspecialchars( $envato_account->get( 'account_name' ) ) . ' (blog post)</span>' .
				     '</div>';*/
			$items            = $envato_account->get( 'items' );
			foreach ( $items as $item_id => $item ) {
				echo '<div class="envato_compose_account_select">' .
				     '<input type="checkbox" name="compose_envato_id[' . $account['shub_account_id'] . '][' . $item_id . ']" value="1"> ' .
				     ($envato_account->get_picture() ? '<img src="'.$envato_account->get_picture().'">' : '' ) .
				     '<span>' . htmlspecialchars( $item->get( 'item_name' ) ) . ' (item)</span>' .
				     '</div>';
			}
		}

	}
	public function compose_message($defaults){
		?>
		<textarea name="envato_message" rows="6" cols="50" id="envato_compose_message"><?php echo isset($defaults['envato_message']) ? esc_attr($defaults['envato_message']) : '';?></textarea>
		<?php
	}

	public function compose_type($defaults){
		?>
		<input type="radio" name="envato_post_type" id="envato_post_type_normal" value="normal" checked>
		<label for="envato_post_type_normal">Normal Post</label>
		<table>
		    <tr>
			    <th class="width1">
				    Subject
			    </th>
			    <td class="">
				    <input name="envato_title" id="envato_compose_title" type="text" value="<?php echo isset($defaults['envato_title']) ? esc_attr($defaults['envato_title']) : '';?>">
				    <span class="envato-type-normal envato-type-option"></span>
			    </td>
		    </tr>
		    <tr>
			    <th class="width1">
				    Picture
			    </th>
			    <td class="">
				    <input type="text" name="envato_picture_url" value="<?php echo isset($defaults['envato_picture_url']) ? esc_attr($defaults['envato_picture_url']) : '';?>">
				    <br/><small>Full URL (eg: http://) to the picture to use for this link preview</small>
				    <span class="envato-type-normal envato-type-option"></span>
			    </td>
		    </tr>
	    </table>
		<?php
	}

	public static function format_person($data,$envato_account){
		$return = '';
		if($data && isset($data['username'])){
			$return .= '<a href="http://themeforest.net/user/' . $data['username'].'" target="_blank">';
		}
		if($data && isset($data['username'])){
			$return .= htmlspecialchars($data['username']);
		}
		if($data && isset($data['username'])){
			$return .= '</a>';
		}
		return $return;
	}



	public function init_js(){
		?>
		    ucm.social.envato.init();
		<?php
	}

	public function handle_process($process, $options = array()){
		switch($process){
			case 'send_shub_message':
				$message_count = 0;
				if(check_admin_referer( 'shub_send-message' ) && isset($options['shub_message_id']) && (int)$options['shub_message_id'] > 0 && isset($_POST['envato_message']) && !empty($_POST['envato_message'])){
					// we have a social message id, ready to send!
					// which envato accounts are we sending too?
					$envato_accounts = isset($_POST['compose_envato_id']) && is_array($_POST['compose_envato_id']) ? $_POST['compose_envato_id'] : array();
					foreach($envato_accounts as $envato_account_id => $send_items){
						$envato_account = new shub_envato_account($envato_account_id);
						if($envato_account->get('shub_account_id') == $envato_account_id){
							/* @var $available_items shub_item[] */
				            $available_items = $envato_account->get('items');
							if($send_items){
							    foreach($send_items as $item_id => $tf){
								    if(!$tf)continue;// shouldnt happen
								    switch($item_id){
									    case 'share':
										    // doing a status update to this envato account
											$envato_message = new shub_message($envato_account, false, false);
										    $envato_message->create_new();
										    $envato_message->update('shub_item_id',0);
							                $envato_message->update('shub_message_id',$options['shub_message_id']);
										    $envato_message->update('shub_account_id',$envato_account->get('shub_account_id'));
										    $envato_message->update('summary',isset($_POST['envato_message']) ? $_POST['envato_message'] : '');
										    $envato_message->update('title',isset($_POST['envato_title']) ? $_POST['envato_title'] : '');
										    $envato_message->update('link',isset($_POST['envato_link']) ? $_POST['envato_link'] : '');
										    if(isset($_POST['track_links']) && $_POST['track_links']){
												$envato_message->parse_links();
											}
										    $envato_message->update('type','share');
										    $envato_message->update('data',json_encode($_POST));
										    $envato_message->update('user_id',get_current_user_id());
										    // do we send this one now? or schedule it later.
										    $envato_message->update('status',_shub_MESSAGE_STATUS_PENDINGSEND);
										    if(isset($options['send_time']) && !empty($options['send_time'])){
											    // schedule for sending at a different time (now or in the past)
											    $envato_message->update('last_active',$options['send_time']);
										    }else{
											    // send it now.
											    $envato_message->update('last_active',0);
										    }
										    if(isset($_FILES['envato_picture']['tmp_name']) && is_uploaded_file($_FILES['envato_picture']['tmp_name'])){
											    $envato_message->add_attachment($_FILES['envato_picture']['tmp_name']);
										    }
											$now = time();
											if(!$envato_message->get('last_active') || $envato_message->get('last_active') <= $now){
												// send now! otherwise we wait for cron job..
												if($envato_message->send_queued(isset($_POST['debug']) && $_POST['debug'])){
										            $message_count ++;
												}
											}else{
										        $message_count ++;
												if(isset($_POST['debug']) && $_POST['debug']){
													echo "message will be sent in cron job after ".shub_print_date($envato_message->get('last_active'),true);
												}
											}
										    break;
									    case 'blog':
											// doing a blog post to this envato account
											// not possible through api

										    break;
									    default:
										    // posting to one of our available items:

										    // see if this is an available item.
										    if(isset($available_items[$item_id])){
											    // push to db! then send.
											    $envato_message = new shub_message($envato_account, $available_items[$item_id], false);
											    $envato_message->create_new();
											    $envato_message->update('shub_item_id',$available_items[$item_id]->get('shub_item_id'));
								                $envato_message->update('shub_message_id',$options['shub_message_id']);
											    $envato_message->update('shub_account_id',$envato_account->get('shub_account_id'));
											    $envato_message->update('summary',isset($_POST['envato_message']) ? $_POST['envato_message'] : '');
											    $envato_message->update('title',isset($_POST['envato_title']) ? $_POST['envato_title'] : '');
											    if(isset($_POST['track_links']) && $_POST['track_links']){
													$envato_message->parse_links();
												}
											    $envato_message->update('type','item_post');
											    $envato_message->update('link',isset($_POST['link']) ? $_POST['link'] : '');
											    $envato_message->update('data',json_encode($_POST));
											    $envato_message->update('user_id',get_current_user_id());
											    // do we send this one now? or schedule it later.
											    $envato_message->update('status',_shub_MESSAGE_STATUS_PENDINGSEND);
											    if(isset($options['send_time']) && !empty($options['send_time'])){
												    // schedule for sending at a different time (now or in the past)
												    $envato_message->update('last_active',$options['send_time']);
											    }else{
												    // send it now.
												    $envato_message->update('last_active',0);
											    }
											    if(isset($_FILES['envato_picture']['tmp_name']) && is_uploaded_file($_FILES['envato_picture']['tmp_name'])){
												    $envato_message->add_attachment($_FILES['envato_picture']['tmp_name']);
											    }
												$now = time();
												if(!$envato_message->get('last_active') || $envato_message->get('last_active') <= $now){
													// send now! otherwise we wait for cron job..
													if($envato_message->send_queued(isset($_POST['debug']) && $_POST['debug'])){
											            $message_count ++;
													}
												}else{
											        $message_count ++;
													if(isset($_POST['debug']) && $_POST['debug']){
														echo "message will be sent in cron job after ".shub_print_date($envato_message->get('last_active'),true);
													}
												}

										    }else{
											    // log error?
										    }
								    }
							    }
						    }
						}
					}
				}
				return $message_count;
				break;
		}
        parent::handle_process($process, $options);
	}

    public function get_account($shub_account_id){
        return new shub_envato_account($shub_account_id);
    }

	public function get_message($envato_account = false, $item = false, $shub_message_id = false){
		return new shub_message($envato_account, $item, $shub_message_id);
	}


	public function run_cron( $debug = false, $cron_timeout = false, $cron_start = false){
		if($debug)echo "Starting envato Cron Job \n";
		$accounts = $this->get_accounts();

        $last_cron_task = get_option('last_support_hub_cron_envato',false);
        $cron_completed = true;
		foreach($accounts as $account){
            if($last_cron_task){
                if($last_cron_task['shub_account_id'] == $account['shub_account_id']) {
                    // we got here last time, continue off from where we left
                    SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_INFO,'envato','Cron resuming operation from account : '.$account['shub_account_id']);
                }else{
                    // keep hunting for the cron job we were up to last time.
                    continue;
                }
            }
			$shub_envato_account = new shub_envato_account( $account['shub_account_id'] );
			$shub_envato_account->run_cron($debug);
			$items = $shub_envato_account->get('items');
			/* @var $items shub_item[] */
			foreach($items as $item){

                if($last_cron_task){
                    if($last_cron_task['shub_item_id'] == $item->get('shub_item_id')) {
                        // we got here last time, continue on the next item.
                        $last_cron_task = false;
                        SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_INFO,'envato','Cron resuming operation from item : '.$item->get('shub_item_id'));
                    }
                    continue;
                }

                // recording where we get up to in the (sometimes very long) cron tasks.
                update_option('last_support_hub_cron_envato',array(
                    'shub_account_id' => $account['shub_account_id'],
                    'shub_item_id' => $item->get('shub_item_id'),
                    'time' => time(),
                ));

				$item->run_cron($debug);

                if($cron_start + $cron_timeout < time()){
                    $cron_completed = false;
                    break;
                }
			}
		}
        // finished everything successfully so we clear the last cache magiggy
        if($cron_completed){
            SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_INFO,'envato','Cron completed successfully');
            update_option('last_support_hub_cron_envato',false);
        }
		if($debug)echo "Finished envato Cron Job \n";
	}

	public function find_other_user_details($user_hints, $current_extension, $message_object){
		$details = array(
			'messages' => array(),
			'user' => array(),
            'user_ids' => array(),
		);
        // todo: find user meta for envato purchase code details.
		if(!empty($user_hints['shub_user_id'])){
            if(!is_array($user_hints['shub_user_id']))$user_hints['shub_user_id'] = array($user_hints['shub_user_id']);
            foreach($user_hints['shub_user_id'] as $shub_user_id) {
                if((int)$shub_user_id > 0) {
                    $details['user_ids'][$shub_user_id] = $shub_user_id;
                    $shub_user = new SupportHubUser_Envato($shub_user_id);
                    $envato_username = $shub_user->get_meta('envato_username');
                    if($envato_username){
                        foreach($envato_username as $envato_username1) {
                            if (!empty($envato_username1)) {
                                // todo - display multiple.
                                $details['user']['username'] = $envato_username1;
                                $details['user']['url'] = 'http://themeforest.net/user/' . $envato_username1;
                                // see if we can find any other matching user accounts for this username
                                $other_users = new SupportHubUser_Envato();
                                $other_users->load_by('user_username',$envato_username1);
                                if($other_users->get('shub_user_id') && !in_array($other_users->get('shub_user_id'),$user_hints['shub_user_id'])){
                                    // pass these back to the calling method so we can get the correct values.
                                    $details['user_ids'][$other_users->get('shub_user_id')] = $other_users->get('shub_user_id');
                                }
                            }
                        }
                    }

                    /*$envato_codes = $shub_user->get_meta('envato_license_code');
                    if(is_array($envato_codes)){
                        $details['user']['codes'] = $envato_codes;
                    }*/

                    /*$user_data = $shub_user->get('user_data');
                    if (isset($user_data['envato_codes'])) {
                        // these come in from bbPress (and hopefully other places)
                        // array of purchase code info
                        $details['user']['codes'] = implode(', ', array_keys($user_data['envato_codes']));
                        $details['user']['products'] = array();
                        foreach ($user_data['envato_codes'] as $code => $purchase_data) {
                            print_r($purchase_data);
                            $details['user']['products'][] = $purchase_data['item_name'];
                        }
                        $details['user']['products'] = implode(', ', $details['user']['products']);
                    }*/

                }
            }
		}

		return $details;
	}



	public function extra_process_login($network, $account_id, $message_id, $extra_ids){
		if($network != 'envato')dir('Incorrect network in request_extra_login() - this should not happen');
		$accounts = $this->get_accounts();
		if(!isset($accounts[$account_id])){
			die('Invalid account, please report this error.');
		}
		if(false) {
			// for testing without doing a full login:
			$shub_message = new shub_message( false, false, $message_id );
			ob_start();
			$shub_message->output_message_list( false );
			return array(
				'message' => ob_get_clean(),
			);
		}

		// check if the user is already logged in via oauth.
		if(!empty($_SESSION['shub_oauth_envato']) && is_array($_SESSION['shub_oauth_envato']) && $_SESSION['shub_oauth_envato']['expires'] > time() && $_SESSION['shub_oauth_envato']['account_id'] == $account_id && $_SESSION['shub_oauth_envato']['message_id'] == $message_id){
			// user is logged in
			$shub_message = new shub_message(false, false, $message_id);
			if($shub_message->get('account')->get('shub_account_id') == $account_id && $shub_message->get('shub_message_id') == $message_id){
				ob_start();
                if(!empty($_SESSION['shub_oauth_envato']['is_admin'])){
                    echo "<p>You are currently logged in as the Administrator account. You can see all message history.</p>";
                }
				$shub_message->output_message_list(false);
                if(isset($_GET['done'])){
                    // submission of extra data was successful, clear the token so the user has to login again
                    $_SESSION['shub_oauth_envato'] = false;
                }
				return array(
					'message' => ob_get_clean(),
				);

			}
		}else{
			// user isn't logged in or the token has expired. show the login url again.
			// find the account.
			if(isset($accounts[$account_id])){
				$shub_envato_account = new shub_envato_account($accounts[$account_id]['shub_account_id']);
				// found the account, pull in the API and build the url
				$api = $shub_envato_account->get_api();
				// check if we have a code from a previous redirect:
				if(!empty($_SESSION['shub_oauth_doing_envato']['code'])){
					// grab a token from the api
					$token = $api->get_authentication($_SESSION['shub_oauth_doing_envato']['code']);
					unset($_SESSION['shub_oauth_doing_envato']['code']);
					if(!empty($token) && !empty($token['access_token'])) {
						// good so far, time to check their username matches from the api
						$shub_message = new shub_message(false, false, $message_id);
						if($shub_message->get('account')->get('shub_account_id') == $shub_envato_account->get('shub_account_id')){
							// grab the details from the envato message:
							$envato_comments = $shub_message->get_comments();
							$first_comment = current($envato_comments);
							if(!empty($first_comment)){
                                $api_result = $api->api('v1/market/private/user/username.json', array(), false);
                                $api_result_email = $api->api('v1/market/private/user/email.json', array(), false);
                                $api_user = new SupportHubUser_Envato();

                                if($api_result && !empty($api_result['username'])){
                                    if($api_result_email && !empty($api_result_email['email'])) {
                                        $email = trim(strtolower($api_result_email['email']));
                                        $api_user->load_by('user_email', $email);
                                        if(!$api_user->get('shub_user_id')) {
                                            // see if we can load by envato username instead
                                            $api_user->load_by_meta('envato_username', $api_result['username']);
                                            if(!$api_user->get('shub_user_id')) {
                                                // no match on envato username
                                                // try to find a match by plain old username instead
                                                // no existing match by email, find a match by username
                                                $api_user->load_by( 'user_username', $api_result['username']);
                                                if(!$api_user->get('shub_user_id')) {
                                                    // no existing match by email, envato_username or plain username, pump a new entry in the db
                                                    $api_user->create_new();
                                                    $api_user->add_meta('envato_username',$api_result['username']);
                                                    $api_user->update('user_email',$email);
                                                    $api_user->update('user_username',$api_result['username']);
                                                }else{
                                                    // we got a match by username
                                                }
                                            }else{
                                                // yes! we got a match by envato username.
                                            }
                                        }
                                    }else{
                                        // no email from the user, strange! we should always get an email from the API.
                                        // well just incase we fall back and try to load based on username.

                                        // (COPIED CODE FROM ABOVE )
                                        // see if we can load by envato username instead
                                        $api_user->load_by_meta('envato_username', $api_result['username']);
                                        if(!$api_user->get('shub_user_id')) {
                                            // no match on envato username
                                            // try to find a match by plain old username instead
                                            // no existing match by email, find a match by username
                                            $api_user->load_by( 'user_username', $api_result['username']);
                                            if(!$api_user->get('shub_user_id')) {
                                                // no existing match by email, envato_username or plain username, pump a new entry in the db
                                                $api_user->create_new();
                                                $api_user->add_meta('envato_username',$api_result['username']);
                                                $api_user->update('user_username',$api_result['username']);
                                            }else{
                                                // we got a match by username
                                            }
                                        }else{
                                            // yes! we got a match by envato username.
                                        }
                                    }
                                }
                                if(!$api_result || empty($api_result['username']) || !$api_user->get('shub_user_id')){
                                    // we got an API error, should always have a username.
                                    SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_ERROR,'envato','OAuth Login Fail - No Username From API','API Result '.var_export($api_result,true).' tried to login and gain access to ticket message ' .$message_id);
                                    echo "Sorry, unable to login with Envato.  <br><br> ";
                                    $item_data = $shub_message->get('item')->get('item_data');
                                    if($item_data && $item_data['url']) {
                                        echo '<a href="' . $item_data['url'].'/comments' . (!empty($comment_data['id']) ? '/'.$comment_data['id'] : '') .'">Please click here to return to the Item Comment</a>';
                                    }
                                    return false;
                                }

                                if(!$api_user->get('user_email') && !empty($api_result_email['email'])){
                                    $api_user->update('user_email',trim(strtolower($api_result_email['email'])));
                                }
                                $api_user->add_unique_meta('envato_username',$api_result['username']);


                                // if we get this far then we have a successul api result and we should store it so we can use the refresh token at a later date
                                $shub_envato_oauth_id = shub_update_insert('shub_envato_oauth_id',false,'shub_envato_oauth',array(
                                    'expire_time' => time() + $token['expires_in'],
                                    'shub_account_id' => $accounts[$account_id]['shub_account_id'],
                                    'shub_user_id' => $api_user->get('shub_user_id'),
                                    'access_token' => $token['access_token'],
                                    'refresh_token' => $token['refresh_token'],
                                ));

                                // this also updates their username/email from the API. not sure if that's a good idea.
                                $api_user->update_purchase_history();


                                // NOTE AT THIS STAGE WE HAVE NOT VERIFIED THAT THE LOGGING IN USER IS INFACT THE USER WHO POSTED THE COMMENT
                                // ANYONE COULD BE LOGGING IN NOW

                                $comment_data = @json_decode($first_comment['data'],true);

                                $account_data = $shub_envato_account->get('account_data');

                                // todo: THIS WILL FAIL IF THE USER CHANGES THEIR USERNAME. maybe? maybe not? we should refresh the comment from the API serach if a username change is detected. this will load our serialized comment data back into the db so we can confirm new username.
								if($comment_data && $api_result && !empty($api_result['username']) && (($account_data && isset($account_data['user']['username']) && $api_result['username'] == $account_data['user']['username']) || $api_user->get('shub_user_id') == $shub_message->get('shub_user_id'))){
								//if($comment_data && $api_result && !empty($api_result['username']) && !empty($comment_data['username']) && (($account_data && isset($account_data['user']['username']) && $api_result['username'] == $account_data['user']['username']) || $comment_data['username'] == $api_result['username'])){
									SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_ERROR,'envato','OAuth Login Success - request extra','User '.$api_result['username'] .' has logged in to provide extra details');

									$comment_user = new SupportHubUser_Envato($shub_message->get('shub_user_id'));

									$_SESSION['shub_oauth_envato']            = $token;
									$_SESSION['shub_oauth_envato']['shib_envato_oauth_id']            = $shub_envato_oauth_id;
									$_SESSION['shub_oauth_envato']['account_id']            = $account_id;
									$_SESSION['shub_oauth_envato']['message_id']            = $message_id;
									$_SESSION['shub_oauth_envato']['is_admin']            = ($account_data && isset($account_data['user']['username']) && $api_result['username'] == $account_data['user']['username']);
									$_SESSION['shub_oauth_envato']['expires'] = time() + $token['expires_in'];
									$_SESSION['shub_oauth_envato']['shub_user_id'] = $comment_user->get('shub_user_id');
									ob_start();
                                    if($_SESSION['shub_oauth_envato']['is_admin']){
                                        echo "<p>You are currently logged in as the Administrator account. You can see all message history.</p>";
                                    }
									$shub_message->output_message_list(false);
									return array(
										'message' => ob_get_clean(),
									);

								}else{
									SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_ERROR,'envato','OAuth Login Fail - Username mismatch','User '.var_export($api_result,true).' tried to login and gain access to ticket message ' .$message_id.': '.var_export($comment_data,true));
									echo "Sorry, unable to verify identity. Please submit a new support message if you require assistance. <br><br> ";
									$item_data = $shub_message->get('item')->get('item_data');
									if($item_data && $item_data['url']) {
										echo '<a href="' . $item_data['url'].'/comments' . (!empty($comment_data['id']) ? '/'.$comment_data['id'] : '') .'">Please click here to return to the Item Comment</a>';
									}
									return false;
								}
							}

						}

					}else{
						echo 'Failed to get access token, please try again and report this error.';
						//print_r($token);
					}

				}else {
					$login_url                           = $api->get_authorization_url();
					$_SESSION['shub_oauth_doing_envato'] = array(
						'url' => str_replace('&done','',$_SERVER['REQUEST_URI']),
					);
					?>
                    <p>
                        To continue please login using your Envato account.
                    </p>
					<a href="<?php echo esc_attr( $login_url );?>" class="submit_button">Login with Envato</a>
				<?php
				}
			}
		}
		return false;
	}

    public function pull_purchase_code($api, $purchase_code, $api_raw_data = array()){
        $result = $api->api( 'v1/market/private/user/verify-purchase:' . $purchase_code . '.json' );
//        $result = $api->api( 'v2/market/author/sale?code='.$purchase_code );
        /*
        {
          "amount": "12.60",
          "sold_at": "2015-08-18T21:30:02+10:00",
          "item": {
            "id": 1299019,
            "name": "WooCommerce Australia Post Shipping Calculator",
            "description": ...
            "site": "codecanyon.net",
            "classification": "wordpress/ecommerce/woocommerce/shipping",
            "classification_url": "http://codecanyon.net/category/wordpress/ecommerce/woocommerce/shipping",
            "price_cents": 1800,
            "number_of_sales": 1054,
            "author_username": "dtbaker",
            "author_url": "http://codecanyon.net/user/dtbaker",
            "author_image": "https://0.s3.envato.com/files/111547951/dtbaker-php-scripts-wordpress-themes-and-plugins.png",
            "url": "http://codecanyon.net/item/woocommerce-australia-post-shipping-calculator/1299019",
            "thumbnail_url": "https://0.s3.envato.com/files/15047665/thumb.png",
            "summary": "High Resolution: No, Compatible With: WooCommerce 2.3.x, Software Version: WordPress 4.2, WordPress 4.1, WordPress 4.0, WordPress 3.9, WordPress 3.8, WordPress 3.7",
            "rating": {
              "rating": 3.82,
              "count": 49
            },
            "updated_at": "2015-08-13T12:34:59+10:00",
            "published_at": "2012-01-15T17:59:18+11:00",
            "trending": false,
            "previews": {
              "landscape_preview": {
                "landscape_url": "https://image-cc.s3.envato.com/files/15047663/preview.jpg"
              }
            }
          },
          "license": "Regular License",
          "code": "c77ff344-9be4-4ba1-9e50-ce92e37c33e0",
          "support_amount": ""
        }
        }*/
        if($result && !empty($result['verify-purchase']) && !empty($result['verify-purchase']['item_id'])) {
            // valid purchase code.
            // what is the username attached to this purchase result?
            $envato_username = $result['verify-purchase']['buyer'];
            // find this user in our system.
            $shub_user = new SupportHubUser_Envato();
            $shub_user->load_by_meta('envato_username', $envato_username);
            if (!$shub_user->get('shub_user_id')) {
                // no users exists in our system with this username.
                // add a new one.
                $shub_user->create_new();
                $shub_user->update('user_username',$envato_username);
                $shub_user->add_meta('envato_username',$envato_username);
            }
            // find out which product this purchase code is relating to
            $existing_products = SupportHub::getInstance()->get_products();
            // check if this item exists already
            $exists = false;
            foreach ($existing_products as $existing_product) {
                if (isset($existing_product['product_data']['envato_item_id']) && $existing_product['product_data']['envato_item_id'] == $result['verify-purchase']['item_id']) {
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
                $newproduct->update('product_name', $result['verify-purchase']['item_name']);
            }
            $existing_product_data = $newproduct->get('product_data');
            if (!is_array($existing_product_data)) $existing_product_data = array();
            if (empty($existing_product_data['envato_item_id'])) {
                $existing_product_data['envato_item_id'] = $result['verify-purchase']['item_id'];
            }
            if (empty($existing_product_data['envato_item_data'])) {
                // get these item details from api
                $item_data = $api->api('v2/market/catalog/item?id='.$result['verify-purchase']['item_id']);
                if(!empty($item_data) && $item_data['id'] == $result['verify-purchase']['item_id']){
                    $existing_product_data['envato_item_data'] = $item_data;
                    if (empty($existing_product_data['image'])) {
                        $existing_product_data['image'] = $item_data['thumbnail_url'];
                    }
                    if (empty($existing_product_data['url'])) {
                        $existing_product_data['url'] = $item_data['url'];
                    }
                }
            }
            $newproduct->update('product_data', $existing_product_data);
            if ($newproduct->get('shub_product_id')) {

            }
            $shub_envato_purchase_id = false;
            // store this in our purchase code database so we can access it easier later on
            $existing_purchase = shub_get_single('shub_envato_purchase', 'purchase_code', $purchase_code);
            if(!$existing_purchase){
                // see if we can find an existing purchase by this user at the same time, without a purchase code.
                // (because results from the purchase api do not contian purchase codes)
                $possible_purchases = shub_get_multiple('shub_envato_purchase',array(
                    'shub_user_id' => $shub_user->get('shub_user_id'),
                    'shub_product_id' => $newproduct->get('shub_product_id'),
                    'purchase_time' => strtotime($result['verify-purchase']['created_at']),
                ));
                foreach($possible_purchases as $possible_purchase){
                    if(empty($possible_purchases['purchase_code'])){
                        // this purchase came from the other api and doesn't have a purchase code.
                        // add it in!
                        $shub_envato_purchase_id = shub_update_insert('shub_envato_purchase_id', $possible_purchase['shub_envato_purchase_id'], 'shub_envato_purchase', array(
                            'purchase_code' => $purchase_code,
                        ));
                    }
                }
            }else{
                // we do have an existing purchase.
                $shub_envato_purchase_id = $existing_purchase['shub_envato_purchase_id'];
                if(empty($existing_purchase['shub_user_id'])){
                    shub_update_insert('shub_envato_purchase_id', $shub_envato_purchase_id, 'shub_envato_purchase', array(
                        'shub_user_id' => $shub_user->get('shub_user_id'),
                    ));
                }
            }
            if (!$shub_envato_purchase_id){
                // add new one
                $raw_purchase_data = array_merge(array('verify-purchase' => $result['verify-purchase']), is_array($api_raw_data) ? $api_raw_data : array() );
                $shub_envato_purchase_id = shub_update_insert('shub_envato_purchase_id', false, 'shub_envato_purchase', array(
                    'shub_user_id' => $shub_user->get('shub_user_id'),
                    'shub_product_id' => $newproduct->get('shub_product_id'),
                    'envato_user_id' => 0,
                    'api_type' => 'verify-purchase',
                    'purchase_time' => strtotime($result['verify-purchase']['created_at']),
                    'purchase_code' => $purchase_code,
                    'purchase_data' => json_encode($raw_purchase_data),
                ));
            }
            if($shub_envato_purchase_id){

                // support expiry time is 6 months from the purchase date, or as specified by the api result.
                $support_expiry_time = strtotime("+6 months", strtotime($result['verify-purchase']['created_at']));
                if(!empty($result['verify-purchase']['supported_until'])){
                    $support_expiry_time = strtotime($result['verify-purchase']['supported_until']);
                }

                $existing_support = shub_get_single('shub_envato_support', array(
                    'shub_envato_purchase_id',
                ), array(
                    $shub_envato_purchase_id,
                ));
                if($existing_support && empty($existing_support['shub_user_id'])){
                    shub_update_insert('shub_envato_support_id', $existing_support['shub_envato_support_id'], 'shub_envato_support', array(
                        'shub_user_id' => $shub_user->get('shub_user_id'),
                    ));
                }
                if ($existing_support && $existing_support['shub_envato_support_id'] && $existing_support['start_time'] == strtotime($result['verify-purchase']['created_at'])) {
                    // check the existing support expiry matches the one we have in the database.
                    if($existing_support['end_time'] < $support_expiry_time){
                        // we have a support extension!
                        $shub_envato_support_id = shub_update_insert('shub_envato_support_id', $existing_support['shub_envato_support_id'], 'shub_envato_support', array(
                            'end_time' => $support_expiry_time,
                            'api_type' => 'verify-purchase',
                            'support_data' => json_encode($result['verify-purchase']),
                        ));
                    }
                }else{
                    // we are adding a new support entry
                    $shub_envato_support_id = shub_update_insert('shub_envato_support_id', false, 'shub_envato_support', array(
                        'shub_user_id' => $shub_user->get('shub_user_id'),
                        'shub_product_id' => $newproduct->get('shub_product_id'),
                        'shub_envato_purchase_id' => $shub_envato_purchase_id,
                        'api_type' => 'verify-purchase',
                        'start_time' => strtotime($result['verify-purchase']['created_at']),
                        'end_time' => $support_expiry_time,
                        'support_data' => json_encode($result['verify-purchase']),
                    ));
                }
            }
        }
        return $result;
    }

	public function extra_validate_data($status, $extra, $value, $network, $account_id, $message_id){
		if(!is_string($value))return $status;
		if(!empty($status['data'])){
			$value = $status['data'];
		}
		$possible_purchase_code = strtolower(preg_replace('#([a-z0-9]{8})-?([a-z0-9]{4})-?([a-z0-9]{4})-?([a-z0-9]{4})-?([a-z0-9]{12})#','$1-$2-$3-$4-$5',$value));
        // todo: add documentation that it needs to be named "Purchase Code"
        // todo: add a default extra value called Purchase Code.
        if(!empty($value) && ($extra->get('extra_name') == 'Purchase Code' || strlen($possible_purchase_code)==36)) { // should be 36
	        // great! we have a purchase code.
	        // see if it validates, if it does we return a success along with extra data that will be saved and eventually displayed
	        $shub_message = new shub_message( false, false, $message_id );
	        if(strlen($possible_purchase_code)==36) {

                $api    = $shub_message->get( 'account' )->get_api();
                $result = $this->pull_purchase_code($api, $possible_purchase_code);

	        }else{
		        $result = false;
	        }
            if($result && !empty($result['verify-purchase'])){
                $status['success'] = true;
                $status['data'] = $possible_purchase_code;
                $result['verify-purchase']['time'] = time();
                $result['verify-purchase']['valid_purchase_code'] = true;
                $status['extra_data'] = $result['verify-purchase'];
            }else{
                $status['success'] = false;
                $status['message'] = 'Invalid purchase code, please try again.';
            }


        }
		return $status;

	}
	public function extra_save_data($extra, $value, $network, $account_id, $message_id){
		$shub_message = new shub_message( false, false, $message_id );
		$shub_user_id = !empty($_SESSION['shub_oauth_envato']['shub_user_id']) ? $_SESSION['shub_oauth_envato']['shub_user_id'] : $shub_message->get('shub_user_id');
		if(is_array($value) && !empty($value['extra_data']['valid_purchase_code'])){
			// we're saving a previously validated (Above) purchase code.
			// create a shub user for this purchase and return success along with the purchase data to show
			$comment_user = new SupportHubUser_Envato();
		    $res = false;
		    if(!empty($value['extra_data']['buyer'])){
			    $res = $comment_user->load_by( 'user_username', $value['extra_data']['buyer']);
		    }
		    if(!$res) {
			    $comment_user->create_new();
			    $comment_user->update( 'user_username', $value['extra_data']['buyer'] );
		    }
		    $user_data = $comment_user->get('user_data');
			if(!is_array($user_data))$user_data=array();
		    if(!isset($user_data['envato_codes']))$user_data['envato_codes']=array();
			$user_data_codes = array();
			$user_data_codes[$value['data']] = $value['extra_data'];
		    $user_data['envato_codes'] = array_merge($user_data['envato_codes'], $user_data_codes);
		    $comment_user->update_user_data($user_data);
			$shub_user_id = $comment_user->get('shub_user_id');
		}

		$extra->save_and_link(
			array(
				'extra_value' => is_array($value) && !empty($value['data']) ? $value['data'] : $value,
				'extra_data' => is_array($value) && !empty($value['extra_data']) ? $value['extra_data'] : false,
			),
			$network,
			$account_id,
			$message_id,
			$shub_user_id
		);

	}
	public function extra_send_message($message, $network, $account_id, $message_id){
		// save this message in the database as a new comment.
		// set the 'private' flag so we know this comment has been added externally to the API scrape.
		$shub_message = new shub_message( false, false, $message_id );
		$existing_comments = $shub_message->get_comments();
		shub_update_insert('shub_message_comment_id',false,'shub_message_comment',array(
		    'shub_message_id' => $shub_message->get('shub_message_id'),
		    'private' => 1,
		    'message_text' => $message,
			'time' => time(),
		    'shub_user_id' => !empty($_SESSION['shub_oauth_envato']['shub_user_id']) ? $_SESSION['shub_oauth_envato']['shub_user_id'] : $shub_message->get('shub_user_id'),
	    ));
		// mark the main message as unread so it appears at the top.
		$shub_message->update('status',_shub_MESSAGE_STATUS_UNANSWERED);
		$shub_message->update('last_active',time());
		// todo: update the 'summary' to reflect this latest message?
		$shub_message->update('summary',$message);

		// todo: post a "Thanks for providing information, we will reply soon" message on Envato comment page

	}

    public function filter_message_user_sidebar($user_bits, $shub_user_ids){
        // find purchases for these user ids and if they are in a valid support term.
        if(!empty($shub_user_ids) && is_array($shub_user_ids)){
            foreach($shub_user_ids as $shub_user_id){
                if((int)$shub_user_id > 0){
                    // find purchases.
                    $total = 0;
                    $purchases = shub_get_multiple('shub_envato_purchase',array('shub_user_id'=>$shub_user_id));
                    foreach($purchases as $purchase){
                        if($purchase['shub_product_id']){
                            $purchase_product = new SupportHubProduct($purchase['shub_product_id']);
                            $data = $purchase_product->get('product_data');
                            if(!empty($data['envato_item_data']['item'])){
                                $support_text = '<strong>EXPIRED</strong>';
                                $support = shub_get_single('shub_envato_support','shub_envato_purchase_id',$purchase['shub_envato_purchase_id']);
                                if($support && !empty($support['end_time']) && $support['end_time'] > time()){
                                    $support_text = shub_print_date($support['end_time']);
                                }
                                $user_bits[] = array(
                                    'Purchase',
                                    esc_html($data['envato_item_data']['item']).' on '.shub_print_date($purchase['purchase_time']) .
                                    ' support until ' . $support_text
                                );
                                $sale_data = @json_decode($purchase['purchase_data'],true);
                                if($sale_data && !empty($sale_data['amount'])){
                                    $total +=  $sale_data['amount'];
                                }
                            }
                        }
                    }
                    if($total){
                        $user_bits[] = array(
                            'Total',
                            '$'.$total,
                        );
                    }
                }
            }
        }
        return $user_bits;
    }

	public function get_install_sql() {

		global $wpdb;

        // we need a database table to store verification codes
        // and a database table to store envato usernames (because they can change) so we can keep our own history

		$sql = <<< EOT

CREATE TABLE {$wpdb->prefix}shub_envato_oauth (
  shub_envato_oauth_id int(11) NOT NULL AUTO_INCREMENT,
  shub_user_id int(11) NOT NULL,
  shub_account_id int(11) NOT NULL,
  access_token varchar(255) NOT NULL,
  refresh_token varchar(255) NOT NULL,
  expire_time int(11) NOT NULL,
  PRIMARY KEY  shub_envato_oauth_id (shub_envato_oauth_id),
  KEY shub_user_id (shub_user_id)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;

CREATE TABLE {$wpdb->prefix}shub_envato_purchase (
  shub_envato_purchase_id int(11) NOT NULL AUTO_INCREMENT,
  shub_user_id int(11) NOT NULL,
  shub_product_id int(11) NOT NULL,
  envato_user_id int(11) NOT NULL,
  purchase_time int(11) NOT NULL,
  api_type varchar(20) NOT NULL DEFAULT '',
  purchase_code varchar(255) NOT NULL DEFAULT '',
  purchase_data longtext NOT NULL,
  PRIMARY KEY  shub_envato_purchase_id (shub_envato_purchase_id),
  KEY shub_user_id (shub_user_id),
  KEY envato_user_id (envato_user_id),
  KEY purchase_code (purchase_code)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;

CREATE TABLE {$wpdb->prefix}shub_envato_support (
  shub_envato_support_id int(11) NOT NULL AUTO_INCREMENT,
  shub_user_id int(11) NOT NULL,
  shub_product_id int(11) NOT NULL,
  shub_envato_purchase_id int(11) NOT NULL,
  start_time int(11) NOT NULL,
  end_time int(11) NOT NULL,
  api_type varchar(20) NOT NULL DEFAULT '',
  support_data longtext NOT NULL,
  PRIMARY KEY  shub_envato_support_id (shub_envato_support_id),
  KEY shub_envato_purchase_id (shub_envato_purchase_id),
  KEY shub_user_id (shub_user_id)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;


EOT;
		return $sql;
	}

}
