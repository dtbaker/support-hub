<?php

class shub_bbpress extends SupportHub_extension {


    public function init(){
        parent::init();
        add_filter('supporthub_message_user_sidebar',array($this,'filter_message_user_sidebar'),10,2);

    }


	public function page_assets($from_master=false){
		if(!$from_master)SupportHub::getInstance()->inbox_assets();

		wp_register_style( 'support-hub-bbpress-css', plugins_url('extensions/bbpress/shub_bbpress.css',_DTBAKER_SUPPORT_HUB_CORE_FILE_), array(), '1.0.0' );
		wp_enqueue_style( 'support-hub-bbpress-css' );
		wp_register_script( 'support-hub-bbpress', plugins_url('extensions/bbpress/shub_bbpress.js',_DTBAKER_SUPPORT_HUB_CORE_FILE_), array( 'jquery' ), '1.0.0' );
		wp_enqueue_script( 'support-hub-bbpress' );

	}

	public function settings_page(){
		include( dirname(__FILE__) . '/bbpress_settings.php');
	}



	public function compose_to(){
		$accounts = $this->get_accounts();
	    if(!count($accounts)){
		    _e('No accounts configured', 'support_hub');
	    }
		foreach ( $accounts as $account ) {
			$bbpress_account = new shub_bbpress_account( $account['shub_account_id'] );
			echo '<div class="bbpress_compose_account_select">' .
				     '<input type="checkbox" name="compose_bbpress_id[' . $account['shub_account_id'] . '][share]" value="1"> ' .
				     ($bbpress_account->get_picture() ? '<img src="'.$bbpress_account->get_picture().'">' : '' ) .
				     '<span>' . htmlspecialchars( $bbpress_account->get( 'bbpress_name' ) ) . ' (status update)</span>' .
				     '</div>';
			/*echo '<div class="bbpress_compose_account_select">' .
				     '<input type="checkbox" name="compose_bbpress_id[' . $account['shub_account_id'] . '][blog]" value="1"> ' .
				     ($bbpress_account->get_picture() ? '<img src="'.$bbpress_account->get_picture().'">' : '' ) .
				     '<span>' . htmlspecialchars( $bbpress_account->get( 'bbpress_name' ) ) . ' (blog post)</span>' .
				     '</div>';*/
			$forums            = $bbpress_account->get( 'items' );
			foreach ( $forums as $item_id => $forum ) {
				echo '<div class="bbpress_compose_account_select">' .
				     '<input type="checkbox" name="compose_bbpress_id[' . $account['shub_account_id'] . '][' . $item_id . ']" value="1"> ' .
				     ($bbpress_account->get_picture() ? '<img src="'.$bbpress_account->get_picture().'">' : '' ) .
				     '<span>' . htmlspecialchars( $forum->get( 'item_name' ) ) . ' (forum)</span>' .
				     '</div>';
			}
		}


	}
	public function compose_message($defaults){
		?>
		<textarea name="bbpress_message" rows="6" cols="50" id="bbpress_compose_message"><?php echo isset($defaults['bbpress_message']) ? esc_attr($defaults['bbpress_message']) : '';?></textarea>
		<?php
	}

	public function compose_type($defaults){
		?>
		<input type="radio" name="bbpress_post_type" id="bbpress_post_type_normal" value="normal" checked>
		<label for="bbpress_post_type_normal">Normal Post</label>
		<table>
		    <tr>
			    <th class="width1">
				    Subject
			    </th>
			    <td class="">
				    <input name="bbpress_title" id="bbpress_compose_title" type="text" value="<?php echo isset($defaults['bbpress_title']) ? esc_attr($defaults['bbpress_title']) : '';?>">
				    <span class="bbpress-type-normal bbpress-type-option"></span>
			    </td>
		    </tr>
		    <tr>
			    <th class="width1">
				    Picture
			    </th>
			    <td class="">
				    <input type="text" name="bbpress_picture_url" value="<?php echo isset($defaults['bbpress_picture_url']) ? esc_attr($defaults['bbpress_picture_url']) : '';?>">
				    <br/><small>Full URL (eg: http://) to the picture to use for this link preview</small>
				    <span class="bbpress-type-normal bbpress-type-option"></span>
			    </td>
		    </tr>
	    </table>
		<?php
	}


	public static function format_person($data,$bbpress_account){
		$return = '';
		if($data->get_link()){
			$return .= '<a href="' . $data->get_link() . '" target="_blank">';
		}
		$return .= htmlspecialchars($data->get_name());
		if($data->get_link()){
			$return .= '</a>';
		}
		return $return;
	}



    public function init_js(){
        ?>
        ucm.social.bbpress.init();
        <?php
    }


	public function handle_process($process, $options = array()){
		switch($process){
			case 'send_shub_message':
				$message_count = 0;
				if(check_admin_referer( 'shub_send-message' ) && isset($options['shub_message_id']) && (int)$options['shub_message_id'] > 0 && isset($_POST['bbpress_message']) && !empty($_POST['bbpress_message'])){
					// we have a social message id, ready to send!
					// which bbpress accounts are we sending too?
					$bbpress_accounts = isset($_POST['compose_bbpress_id']) && is_array($_POST['compose_bbpress_id']) ? $_POST['compose_bbpress_id'] : array();
					foreach($bbpress_accounts as $bbpress_account_id => $send_forums){
						$bbpress_account = new shub_bbpress_account($bbpress_account_id);
						if($bbpress_account->get('shub_account_id') == $bbpress_account_id){
							/* @var $available_forums shub_bbpress_item[] */
				            $available_forums = $bbpress_account->get('items');
							if($send_forums){
							    foreach($send_forums as $item_id => $tf){
								    if(!$tf)continue;// shouldnt happen
								    switch($item_id){
									    case 'share':
										    // doing a status update to this bbpress account
											$bbpress_message = new shub_bbpress_message($bbpress_account, false, false);
										    $bbpress_message->create_new();
										    $bbpress_message->update('shub_item_id',0);
							                $bbpress_message->update('shub_message_id',$options['shub_message_id']);
										    $bbpress_message->update('shub_account_id',$bbpress_account->get('shub_account_id'));
										    $bbpress_message->update('summary',isset($_POST['bbpress_message']) ? $_POST['bbpress_message'] : '');
										    $bbpress_message->update('title',isset($_POST['bbpress_title']) ? $_POST['bbpress_title'] : '');
										    $bbpress_message->update('shub_link',isset($_POST['bbpress_link']) ? $_POST['bbpress_link'] : '');
										    if(isset($_POST['track_links']) && $_POST['track_links']){
												$bbpress_message->parse_links();
											}
										    $bbpress_message->update('shub_type','share');
										    $bbpress_message->update('shub_data',json_encode($_POST));
										    $bbpress_message->update('user_id',get_current_user_id());
										    // do we send this one now? or schedule it later.
										    $bbpress_message->update('shub_status',_shub_MESSAGE_STATUS_PENDINGSEND);
										    if(isset($options['send_time']) && !empty($options['send_time'])){
											    // schedule for sending at a different time (now or in the past)
											    $bbpress_message->update('last_active',$options['send_time']);
										    }else{
											    // send it now.
											    $bbpress_message->update('last_active',0);
										    }
										    if(isset($_FILES['bbpress_picture']['tmp_name']) && is_uploaded_file($_FILES['bbpress_picture']['tmp_name'])){
											    $bbpress_message->add_attachment($_FILES['bbpress_picture']['tmp_name']);
										    }
											$now = time();
											if(!$bbpress_message->get('last_active') || $bbpress_message->get('last_active') <= $now){
												// send now! otherwise we wait for cron job..
												if($bbpress_message->send_queued(isset($_POST['debug']) && $_POST['debug'])){
										            $message_count ++;
												}
											}else{
										        $message_count ++;
												if(isset($_POST['debug']) && $_POST['debug']){
													echo "message will be sent in cron job after ".shub_print_date($bbpress_message->get('last_active'),true);
												}
											}
										    break;
									    case 'blog':
											// doing a blog post to this bbpress account
											// not possible through api

										    break;
									    default:
										    // posting to one of our available forums:

										    // see if this is an available forum.
										    if(isset($available_forums[$item_id])){
											    // push to db! then send.
											    $bbpress_message = new shub_bbpress_message($bbpress_account, $available_forums[$item_id], false);
											    $bbpress_message->create_new();
											    $bbpress_message->update('shub_item_id',$available_forums[$item_id]->get('shub_item_id'));
								                $bbpress_message->update('shub_message_id',$options['shub_message_id']);
											    $bbpress_message->update('shub_account_id',$bbpress_account->get('shub_account_id'));
											    $bbpress_message->update('summary',isset($_POST['bbpress_message']) ? $_POST['bbpress_message'] : '');
											    $bbpress_message->update('title',isset($_POST['bbpress_title']) ? $_POST['bbpress_title'] : '');
											    if(isset($_POST['track_links']) && $_POST['track_links']){
													$bbpress_message->parse_links();
												}
											    $bbpress_message->update('shub_type','forum_post');
											    $bbpress_message->update('shub_link',isset($_POST['link']) ? $_POST['link'] : '');
											    $bbpress_message->update('shub_data',json_encode($_POST));
											    $bbpress_message->update('user_id',get_current_user_id());
											    // do we send this one now? or schedule it later.
											    $bbpress_message->update('shub_status',_shub_MESSAGE_STATUS_PENDINGSEND);
											    if(isset($options['send_time']) && !empty($options['send_time'])){
												    // schedule for sending at a different time (now or in the past)
												    $bbpress_message->update('last_active',$options['send_time']);
											    }else{
												    // send it now.
												    $bbpress_message->update('last_active',0);
											    }
											    if(isset($_FILES['bbpress_picture']['tmp_name']) && is_uploaded_file($_FILES['bbpress_picture']['tmp_name'])){
												    $bbpress_message->add_attachment($_FILES['bbpress_picture']['tmp_name']);
											    }
												$now = time();
												if(!$bbpress_message->get('last_active') || $bbpress_message->get('last_active') <= $now){
													// send now! otherwise we wait for cron job..
													if($bbpress_message->send_queued(isset($_POST['debug']) && $_POST['debug'])){
											            $message_count ++;
													}
												}else{
											        $message_count ++;
													if(isset($_POST['debug']) && $_POST['debug']){
														echo "message will be sent in cron job after ".shub_print_date($bbpress_message->get('last_active'),true);
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
        return new shub_bbpress_account($shub_account_id);
    }

	public function get_message($bbpress_account = false, $item = false, $shub_message_id = false){
		return new shub_bbpress_message($bbpress_account, $item, $shub_message_id);
	}

	public function run_cron( $debug = false ){
		if($debug)echo "Starting bbpress Cron Job \n";
		$accounts = $this->get_accounts();
		foreach($accounts as $account){
			$shub_bbpress_account = new shub_bbpress_account( $account['shub_account_id'] );
			$shub_bbpress_account->run_cron($debug);
			$forums = $shub_bbpress_account->get('items');
			/* @var $forums shub_bbpress_item[] */
			foreach($forums as $forum){
				$forum->run_cron($debug);
			}
		}
		if($debug)echo "Finished bbpress Cron Job \n";
	}

	public function find_other_user_details($user_hints, $current_extension, $message_object){
		$details = array(
			'messages' => array(),
			'user' => array(),
            'user_ids' => array(),
		);
		/*if(isset($user_hints['bbpress_username'])){
			$details['user']['username'] = $user_hints['bbpress_username'];
		}*/

		// find other bbpress messages by this user.
        if(!empty($user_hints['shub_user_id'])) {
            if (!is_array($user_hints['shub_user_id'])) $user_hints['shub_user_id'] = array($user_hints['shub_user_id']);
            foreach ($user_hints['shub_user_id'] as $shub_user_id) {
                if ((int)$shub_user_id > 0) {

                }
            }
        }

		return $details;
	}

    public function filter_message_user_sidebar($user_bits, $shub_user_ids){
        return $user_bits;
        // find purchases for these user ids and if they are in a valid support term.
        if(!empty($shub_user_ids) && is_array($shub_user_ids)){
            foreach($shub_user_ids as $shub_user_id){
                if((int)$shub_user_id > 0){

                    if(true){
                        $user_bits[] = array(
                            'bbPress TEST',
                            'FOO',
                        );
                    }
                }
            }
        }
        return $user_bits;
    }


}
