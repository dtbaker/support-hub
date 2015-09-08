<?php

class shub_ucm extends SupportHub_extension {

    public function init(){
        parent::init();
//        add_filter('supporthub_message_user_sidebar',array($this,'filter_message_user_sidebar'),10,2);
    }

	public function page_assets($from_master=false){
		if(!$from_master)SupportHub::getInstance()->inbox_assets();

		wp_register_style( 'support-hub-ucm-css', plugins_url('extensions/ucm/shub_ucm.css',_DTBAKER_SUPPORT_HUB_CORE_FILE_), array(), '1.0.0' );
		wp_enqueue_style( 'support-hub-ucm-css' );
		wp_register_script( 'support-hub-ucm', plugins_url('extensions/ucm/shub_ucm.js',_DTBAKER_SUPPORT_HUB_CORE_FILE_), array( 'jquery' ), '1.0.0' );
		wp_enqueue_script( 'support-hub-ucm' );

	}

	public function settings_page(){
		include( dirname(__FILE__) . '/ucm_settings.php');
	}


	public function compose_to(){
		$accounts = $this->get_accounts();
	    if(!count($accounts)){
		    _e('No accounts configured', 'support_hub');
	    }
		foreach ( $accounts as $account ) {
			$ucm_account = new shub_ucm_account( $account['shub_ucm_id'] );
			echo '<div class="ucm_compose_account_select">' .
				     '<input type="checkbox" name="compose_ucm_id[' . $account['shub_ucm_id'] . '][share]" value="1"> ' .
				     ($ucm_account->get_picture() ? '<img src="'.$ucm_account->get_picture().'">' : '' ) .
				     '<span>' . htmlspecialchars( $ucm_account->get( 'ucm_name' ) ) . ' (status update)</span>' .
				     '</div>';
			/*echo '<div class="ucm_compose_account_select">' .
				     '<input type="checkbox" name="compose_ucm_id[' . $account['shub_ucm_id'] . '][blog]" value="1"> ' .
				     ($ucm_account->get_picture() ? '<img src="'.$ucm_account->get_picture().'">' : '' ) .
				     '<span>' . htmlspecialchars( $ucm_account->get( 'ucm_name' ) ) . ' (blog post)</span>' .
				     '</div>';*/
			$products            = $ucm_account->get( 'products' );
			foreach ( $products as $ucm_product_id => $product ) {
				echo '<div class="ucm_compose_account_select">' .
				     '<input type="checkbox" name="compose_ucm_id[' . $account['shub_ucm_id'] . '][' . $ucm_product_id . ']" value="1"> ' .
				     ($ucm_account->get_picture() ? '<img src="'.$ucm_account->get_picture().'">' : '' ) .
				     '<span>' . htmlspecialchars( $product->get( 'product_name' ) ) . ' (product)</span>' .
				     '</div>';
			}
		}


	}
	public function compose_message($defaults){
		?>
		<textarea name="ucm_message" rows="6" cols="50" id="ucm_compose_message"><?php echo isset($defaults['ucm_message']) ? esc_attr($defaults['ucm_message']) : '';?></textarea>
		<?php
	}

	public function compose_type($defaults){
		?>
		<input type="radio" name="ucm_post_type" id="ucm_post_type_normal" value="normal" checked>
		<label for="ucm_post_type_normal">Normal Post</label>
		<table>
		    <tr>
			    <th class="width1">
				    Subject
			    </th>
			    <td class="">
				    <input name="ucm_title" id="ucm_compose_title" type="text" value="<?php echo isset($defaults['ucm_title']) ? esc_attr($defaults['ucm_title']) : '';?>">
				    <span class="ucm-type-normal ucm-type-option"></span>
			    </td>
		    </tr>
		    <tr>
			    <th class="width1">
				    Picture
			    </th>
			    <td class="">
				    <input type="text" name="ucm_picture_url" value="<?php echo isset($defaults['ucm_picture_url']) ? esc_attr($defaults['ucm_picture_url']) : '';?>">
				    <br/><small>Full URL (eg: http://) to the picture to use for this link preview</small>
				    <span class="ucm-type-normal ucm-type-option"></span>
			    </td>
		    </tr>
	    </table>
		<?php
	}


    public static function format_person($data,$ucm_account){
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
		    ucm.social.ucm.init();
		<?php
	}

	public function handle_process($process, $options = array()){
		switch($process){
			case 'send_shub_message':
				$message_count = 0;
				if(check_admin_referer( 'shub_send-message' ) && isset($options['shub_message_id']) && (int)$options['shub_message_id'] > 0 && isset($_POST['ucm_message']) && !empty($_POST['ucm_message'])){
					// we have a social message id, ready to send!
					// which ucm accounts are we sending too?
					$ucm_accounts = isset($_POST['compose_ucm_id']) && is_array($_POST['compose_ucm_id']) ? $_POST['compose_ucm_id'] : array();
					foreach($ucm_accounts as $ucm_account_id => $send_products){
						$ucm_account = new shub_ucm_account($ucm_account_id);
						if($ucm_account->get('shub_ucm_id') == $ucm_account_id){
							/* @var $available_products shub_ucm_product[] */
				            $available_products = $ucm_account->get('products');
							if($send_products){
							    foreach($send_products as $ucm_product_id => $tf){
								    if(!$tf)continue;// shouldnt happen
								    switch($ucm_product_id){
									    case 'share':
										    // doing a status update to this ucm account
											$ucm_message = new shub_ucm_message($ucm_account, false, false);
										    $ucm_message->create_new();
										    $ucm_message->update('shub_ucm_product_id',0);
							                $ucm_message->update('shub_message_id',$options['shub_message_id']);
										    $ucm_message->update('shub_ucm_id',$ucm_account->get('shub_ucm_id'));
										    $ucm_message->update('summary',isset($_POST['ucm_message']) ? $_POST['ucm_message'] : '');
										    $ucm_message->update('title',isset($_POST['ucm_title']) ? $_POST['ucm_title'] : '');
										    $ucm_message->update('link',isset($_POST['ucm_link']) ? $_POST['ucm_link'] : '');
										    if(isset($_POST['track_links']) && $_POST['track_links']){
												$ucm_message->parse_links();
											}
										    $ucm_message->update('type','share');
										    $ucm_message->update('data',json_encode($_POST));
										    $ucm_message->update('user_id',get_current_user_id());
										    // do we send this one now? or schedule it later.
										    $ucm_message->update('shub_status',_shub_MESSAGE_STATUS_PENDINGSEND);
										    if(isset($options['send_time']) && !empty($options['send_time'])){
											    // schedule for sending at a different time (now or in the past)
											    $ucm_message->update('last_active',$options['send_time']);
										    }else{
											    // send it now.
											    $ucm_message->update('last_active',0);
										    }
										    if(isset($_FILES['ucm_picture']['tmp_name']) && is_uploaded_file($_FILES['ucm_picture']['tmp_name'])){
											    $ucm_message->add_attachment($_FILES['ucm_picture']['tmp_name']);
										    }
											$now = time();
											if(!$ucm_message->get('last_active') || $ucm_message->get('last_active') <= $now){
												// send now! otherwise we wait for cron job..
												if($ucm_message->send_queued(isset($_POST['debug']) && $_POST['debug'])){
										            $message_count ++;
												}
											}else{
										        $message_count ++;
												if(isset($_POST['debug']) && $_POST['debug']){
													echo "message will be sent in cron job after ".shub_print_date($ucm_message->get('last_active'),true);
												}
											}
										    break;
									    case 'blog':
											// doing a blog post to this ucm account
											// not possible through api

										    break;
									    default:
										    // posting to one of our available products:

										    // see if this is an available product.
										    if(isset($available_products[$ucm_product_id])){
											    // push to db! then send.
											    $ucm_message = new shub_ucm_message($ucm_account, $available_products[$ucm_product_id], false);
											    $ucm_message->create_new();
											    $ucm_message->update('shub_ucm_product_id',$available_products[$ucm_product_id]->get('shub_ucm_product_id'));
								                $ucm_message->update('shub_message_id',$options['shub_message_id']);
											    $ucm_message->update('shub_ucm_id',$ucm_account->get('shub_ucm_id'));
											    $ucm_message->update('summary',isset($_POST['ucm_message']) ? $_POST['ucm_message'] : '');
											    $ucm_message->update('title',isset($_POST['ucm_title']) ? $_POST['ucm_title'] : '');
											    if(isset($_POST['track_links']) && $_POST['track_links']){
													$ucm_message->parse_links();
												}
											    $ucm_message->update('type','product_post');
											    $ucm_message->update('link',isset($_POST['link']) ? $_POST['link'] : '');
											    $ucm_message->update('data',json_encode($_POST));
											    $ucm_message->update('user_id',get_current_user_id());
											    // do we send this one now? or schedule it later.
											    $ucm_message->update('shub_status',_shub_MESSAGE_STATUS_PENDINGSEND);
											    if(isset($options['send_time']) && !empty($options['send_time'])){
												    // schedule for sending at a different time (now or in the past)
												    $ucm_message->update('last_active',$options['send_time']);
											    }else{
												    // send it now.
												    $ucm_message->update('last_active',0);
											    }
											    if(isset($_FILES['ucm_picture']['tmp_name']) && is_uploaded_file($_FILES['ucm_picture']['tmp_name'])){
												    $ucm_message->add_attachment($_FILES['ucm_picture']['tmp_name']);
											    }
												$now = time();
												if(!$ucm_message->get('last_active') || $ucm_message->get('last_active') <= $now){
													// send now! otherwise we wait for cron job..
													if($ucm_message->send_queued(isset($_POST['debug']) && $_POST['debug'])){
											            $message_count ++;
													}
												}else{
											        $message_count ++;
													if(isset($_POST['debug']) && $_POST['debug']){
														echo "message will be sent in cron job after ".shub_print_date($ucm_message->get('last_active'),true);
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
        return new shub_ucm_account($shub_account_id);
    }

	public function get_message($ucm_account = false, $ucm_product = false, $shub_ucm_message_id = false){
		return new shub_ucm_message($ucm_account, $ucm_product, $shub_ucm_message_id);
	}

	public function run_cron( $debug = false ){
		if($debug)echo "Starting ucm Cron Job \n";
		$accounts = $this->get_accounts();
		foreach($accounts as $account){
			$shub_ucm_account = new shub_ucm_account( $account['shub_account_id'] );
			$shub_ucm_account->run_cron($debug);
			$products = $shub_ucm_account->get('items');
			/* @var $products shub_ucm_item[] */
			foreach($products as $product){
				$product->run_cron($debug);
			}
		}
		if($debug)echo "Finished ucm Cron Job \n";
	}

	public function find_other_user_details($user_hints, $current_extension, $message_object){
		$details = array(
			'messages' => array(),
			'user' => array(),
            'user_ids' => array(),
		);

		// find other ucm messages by this user.
        if(!empty($user_hints['shub_user_id'])){
            if(!is_array($user_hints['shub_user_id']))$user_hints['shub_user_id'] = array($user_hints['shub_user_id']);
            foreach($user_hints['shub_user_id'] as $shub_user_id) {
                if((int)$shub_user_id > 0) {
                    $details['user_ids'][$shub_user_id] = $shub_user_id;
                    $shub_user = new SupportHubUser_ucm($shub_user_id);
                    $envato_username = $shub_user->get_meta('envato_username');
                    if ($envato_username) {
                        foreach ($envato_username as $envato_username1) {
                            if (!empty($envato_username1)) {
                                // todo - display multiple.
                                $details['user']['username'] = $envato_username1;
                                $details['user']['url'] = 'http://themeforest.net/user/' . $envato_username1;
                                // see if we can find any other matching user accounts for this username
                                $other_users = new SupportHubUser_ucm();
                                $other_users->load_by('user_username', $envato_username1);
                                if ($other_users->get('shub_user_id') && !in_array($other_users->get('shub_user_id'), $user_hints['shub_user_id'])) {
                                    // pass these back to the calling method so we can get the correct values.
                                    $details['user_ids'][$other_users->get('shub_user_id')] = $other_users->get('shub_user_id');
                                }
                            }
                        }
                    }
                }
            }
        }

		return $details;
	}

}
