<?php

class shub_linkedin extends SupportHub_network {

	public function init(){
		if(isset($_GET[_support_hub_LINKEDIN_LINK_REWRITE_PREFIX]) && strlen($_GET[_support_hub_LINKEDIN_LINK_REWRITE_PREFIX]) > 0){
			// check hash
			$bits = explode(':',$_GET[_support_hub_LINKEDIN_LINK_REWRITE_PREFIX]);
			if(defined('AUTH_KEY') && isset($bits[1])){
				$shub_linkedin_message_link_id = (int)$bits[0];
				if($shub_linkedin_message_link_id > 0){
					$correct_hash = substr(md5(AUTH_KEY.' linkedin link '.$shub_linkedin_message_link_id),1,5);
					if($correct_hash == $bits[1]){
						// link worked! log a visit and redirect.
						$link = shub_get_single('shub_linkedin_message_link','shub_linkedin_message_link_id',$shub_linkedin_message_link_id);
						if($link){
							if(!preg_match('#^http#',$link['link'])){
								$link['link'] = 'http://'.trim($link['link']);
							}
							shub_update_insert('shub_linkedin_message_link_click_id',false,'shub_linkedin_message_link_click',array(
								'shub_linkedin_message_link_id' => $shub_linkedin_message_link_id,
								'click_time' => time(),
								'ip_address' => $_SERVER['REMOTE_ADDR'],
								'user_agent' => $_SERVER['HTTP_USER_AGENT'],
								'url_referrer' => $_SERVER['HTTP_REFERER'],
							));
							header("Location: ".$link['link']);
							exit;
						}
					}
				}
			}
		}
	}

	public function init_menu(){

	}

	public function page_assets($from_master=false){
		if(!$from_master)SupportHub::getInstance()->inbox_assets();

		wp_register_style( 'support-hub-linkedin-css', plugins_url('networks/linkedin/shub_linkedin.css',_DTBAKER_SUPPORT_HUB_CORE_FILE_), array(), '1.0.0' );
		wp_enqueue_style( 'support-hub-linkedin-css' );
		wp_register_script( 'support-hub-linkedin', plugins_url('networks/linkedin/shub_linkedin.js',_DTBAKER_SUPPORT_HUB_CORE_FILE_), array( 'jquery' ), '1.0.0' );
		wp_enqueue_script( 'support-hub-linkedin' );

	}

	public function settings_page(){
		include( dirname(__FILE__) . '/linkedin_settings.php');
	}



	private $accounts = array();

	private function reset() {
		$this->accounts = array();
	}


	public function compose_to(){
		$accounts = $this->get_accounts();
	    if(!count($accounts)){
		    _e('No accounts configured', 'support_hub');
	    }
		foreach ( $accounts as $account ) {
			$linkedin_account = new shub_linkedin_account( $account['shub_linkedin_id'] );
			echo '<div class="linkedin_compose_account_select">' .
				     '<input type="checkbox" name="compose_linkedin_id[' . $account['shub_linkedin_id'] . '][share]" value="1"> ' .
				     ($linkedin_account->get_picture() ? '<img src="'.$linkedin_account->get_picture().'">' : '' ) .
				     '<span>' . htmlspecialchars( $linkedin_account->get( 'linkedin_name' ) ) . ' (status update)</span>' .
				     '</div>';
			/*echo '<div class="linkedin_compose_account_select">' .
				     '<input type="checkbox" name="compose_linkedin_id[' . $account['shub_linkedin_id'] . '][blog]" value="1"> ' .
				     ($linkedin_account->get_picture() ? '<img src="'.$linkedin_account->get_picture().'">' : '' ) .
				     '<span>' . htmlspecialchars( $linkedin_account->get( 'linkedin_name' ) ) . ' (blog post)</span>' .
				     '</div>';*/
			$groups            = $linkedin_account->get( 'groups' );
			foreach ( $groups as $linkedin_group_id => $group ) {
				echo '<div class="linkedin_compose_account_select">' .
				     '<input type="checkbox" name="compose_linkedin_id[' . $account['shub_linkedin_id'] . '][' . $linkedin_group_id . ']" value="1"> ' .
				     ($linkedin_account->get_picture() ? '<img src="'.$linkedin_account->get_picture().'">' : '' ) .
				     '<span>' . htmlspecialchars( $group->get( 'group_name' ) ) . ' (group)</span>' .
				     '</div>';
			}
		}


	}
	public function compose_message($defaults){
		?>
		<textarea name="linkedin_message" rows="6" cols="50" id="linkedin_compose_message"><?php echo isset($defaults['linkedin_message']) ? esc_attr($defaults['linkedin_message']) : '';?></textarea>
		<?php
	}

	public function compose_type($defaults){
		?>
		<input type="radio" name="linkedin_post_type" id="linkedin_post_type_normal" value="normal" checked>
		<label for="linkedin_post_type_normal">Normal Post</label>
		<table>
		    <tr>
			    <th class="width1">
				    Subject
			    </th>
			    <td class="">
				    <input name="linkedin_title" id="linkedin_compose_title" type="text" value="<?php echo isset($defaults['linkedin_title']) ? esc_attr($defaults['linkedin_title']) : '';?>">
				    <span class="linkedin-type-normal linkedin-type-option"></span>
			    </td>
		    </tr>
		    <tr>
			    <th class="width1">
				    Picture
			    </th>
			    <td class="">
				    <input type="text" name="linkedin_picture_url" value="<?php echo isset($defaults['linkedin_picture_url']) ? esc_attr($defaults['linkedin_picture_url']) : '';?>">
				    <br/><small>Full URL (eg: http://) to the picture to use for this link preview</small>
				    <span class="linkedin-type-normal linkedin-type-option"></span>
			    </td>
		    </tr>
	    </table>
		<?php
	}


	public function get_accounts() {
		$this->accounts = shub_get_multiple( 'shub_linkedin', array(), 'shub_linkedin_id' );
		return $this->accounts;
	}


	private function get_url($url, $post_data = false){
		// get feed from fb:

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
		if($post_data){
			curl_setopt($ch, CURLOPT_POST,true);
			curl_setopt($ch, CURLOPT_POSTFIELDS,$post_data);
		}
		$data = curl_exec($ch);
		$feed = @json_decode($data,true);
		//print_r($feed);
		return $feed;

	}

	public static function format_person($data,$linkedin_account){
		$return = '';
		if($data && isset($data['id'])){
			$return .= '<a href="http://www.linkedin.com/x/profile/' . $linkedin_account->get('linkedin_app_id').'/'.$data['id'].'" target="_blank">';
		}
		if($data && isset($data['firstName'])){
			$return .= htmlspecialchars($data['firstName']);
		}
		if($data && isset($data['id'])){
			$return .= '</a>';
		}
		return $return;
	}

	public function load_all_messages($search=array(),$order=array(),$limit_batch=0){
		$this->search_params = $search;
		$this->search_order = $order;
		$this->search_limit = $limit_batch;

		$sql = "SELECT m.*, m.last_active AS `message_time`, mr.read_time FROM `"._support_hub_DB_PREFIX."shub_linkedin_message` m ";
		$sql .= " LEFT JOIN `"._support_hub_DB_PREFIX."shub_linkedin_message_read` mr ON ( m.shub_linkedin_message_id = mr.shub_linkedin_message_id AND mr.user_id = ".get_current_user_id()." )";
		$sql .= " WHERE 1 ";
		if(isset($search['status']) && $search['status'] !== false){
			$sql .= " AND `status` = ".(int)$search['status'];
		}
		if(isset($search['shub_linkedin_group_id']) && $search['shub_linkedin_group_id'] !== false){
			$sql .= " AND `shub_linkedin_group_id` = ".(int)$search['shub_linkedin_group_id'];
		}
		if(isset($search['shub_message_id']) && $search['shub_message_id'] !== false){
			$sql .= " AND `shub_message_id` = ".(int)$search['shub_message_id'];
		}
		if(isset($search['shub_linkedin_id']) && $search['shub_linkedin_id'] !== false){
			$sql .= " AND `shub_linkedin_id` = ".(int)$search['shub_linkedin_id'];
		}
		if(isset($search['generic']) && !empty($search['generic'])){
			$sql .= " AND `summary` LIKE '%".mysql_real_escape_string($search['generic'])."%'";
		}
		$sql .= " ORDER BY `last_active` DESC ";
		if($limit_batch){
			$sql .= " LIMIT ".$this->limit_start.', '.$limit_batch;
			$this->limit_start += $limit_batch;
		}
		//$this->all_messages = query($sql);
		global $wpdb;
		$this->all_messages = $wpdb->get_results($sql, ARRAY_A);
		return $this->all_messages;
	}

	// used in our Wp "outbox" view showing combined messages.
	public function get_message_details($shub_message_id){
		if(!$shub_message_id)return array();
		$messages = $this->load_all_messages(array('shub_message_id'=>$shub_message_id));
		// we want data for our colum outputs in the WP table:
		/*'shub_column_time'    => __( 'Date/Time', 'support_hub' ),
	    'shub_column_account' => __( 'Social Accounts', 'support_hub' ),
		'shub_column_summary'    => __( 'Summary', 'support_hub' ),
		'shub_column_links'    => __( 'Link Clicks', 'support_hub' ),
		'shub_column_stats'    => __( 'Stats', 'support_hub' ),
		'shub_column_action'    => __( 'Action', 'support_hub' ),*/
		$data = array(
			'shub_column_account' => '',
			'shub_column_summary' => '',
			'shub_column_links' => '',
		);
		$link_clicks = 0;
		foreach($messages as $message){
			$linkedin_message = new shub_linkedin_message(false, false, $message['shub_linkedin_message_id']);
			$data['message'] = $linkedin_message;
			$data['shub_column_account'] .= '<div><img src="'.plugins_url('networks/linkedin/linkedin-logo.png', _DTBAKER_SUPPORT_HUB_CORE_FILE_).'" class="linkedin_icon small"><a href="'.$linkedin_message->get_link().'" target="_blank">'.htmlspecialchars( $linkedin_message->get('linkedin_group') ? $linkedin_message->get('linkedin_group')->get( 'group_name' ) : 'Share' ) .'</a></div>';
			$data['shub_column_summary'] .= '<div><img src="'.plugins_url('networks/linkedin/linkedin-logo.png', _DTBAKER_SUPPORT_HUB_CORE_FILE_).'" class="linkedin_icon small"><a href="'.$linkedin_message->get_link().'" target="_blank">'.htmlspecialchars( $linkedin_message->get_summary() ) .'</a></div>';
			// how many link clicks does this one have?
			$sql = "SELECT count(*) AS `link_clicks` FROM ";
			$sql .= " `"._support_hub_DB_PREFIX."shub_linkedin_message` m ";
			$sql .= " LEFT JOIN `"._support_hub_DB_PREFIX."shub_linkedin_message_link` ml USING (shub_linkedin_message_id) ";
			$sql .= " LEFT JOIN `"._support_hub_DB_PREFIX."shub_linkedin_message_link_click` lc USING (shub_linkedin_message_link_id) ";
			$sql .= " WHERE 1 ";
			$sql .= " AND m.shub_linkedin_message_id = ".(int)$message['shub_linkedin_message_id'];
			$sql .= " AND lc.shub_linkedin_message_link_id IS NOT NULL ";
			$sql .= " AND lc.user_agent NOT LIKE '%Google%' ";
			$sql .= " AND lc.user_agent NOT LIKE '%Yahoo%' ";
			$sql .= " AND lc.user_agent NOT LIKE '%linkedinexternalhit%' ";
			$sql .= " AND lc.user_agent NOT LIKE '%Meta%' ";
			$res = shub_qa1($sql);
			$link_clicks = $res && $res['link_clicks'] ? $res['link_clicks'] : 0;
			$data['shub_column_links'] .= '<div><img src="'.plugins_url('networks/linkedin/linkedin-logo.png', _DTBAKER_SUPPORT_HUB_CORE_FILE_).'" class="linkedin_icon small">'. $link_clicks  .'</div>';
		}
		if(count($messages) && $link_clicks > 0){
			//$data['shub_column_links'] = '<div><img src="'.plugins_url('networks/linkedin/linkedin-logo.png', _DTBAKER_SUPPORTHUB_CORE_FILE_).'" class="linkedin_icon small">'. $link_clicks  .'</div>';
		}
		return $data;

	}


	public function get_unread_count($search=array()){
		if(!get_current_user_id())return 0;
		$sql = "SELECT count(*) AS `unread` FROM `"._support_hub_DB_PREFIX."shub_linkedin_message` m ";
		$sql .= " WHERE 1 ";
		$sql .= " AND m.shub_linkedin_message_id NOT IN (SELECT mr.shub_linkedin_message_id FROM `"._support_hub_DB_PREFIX."shub_linkedin_message_read` mr WHERE mr.user_id = '".(int)get_current_user_id()."' AND mr.shub_linkedin_message_id = m.shub_linkedin_message_id)";
		$sql .= " AND m.`status` = "._shub_MESSAGE_STATUS_UNANSWERED;
		if(isset($search['shub_linkedin_group_id']) && $search['shub_linkedin_group_id'] !== false){
			$sql .= " AND m.`shub_linkedin_group_id` = ".(int)$search['shub_linkedin_group_id'];
		}
		if(isset($search['shub_linkedin_id']) && $search['shub_linkedin_id'] !== false){
			$sql .= " AND m.`shub_linkedin_id` = ".(int)$search['shub_linkedin_id'];
		}
		$res = shub_qa1($sql);
		return $res ? $res['unread'] : 0;
	}


	public function output_row($message, $settings){
		$linkedin_message = new shub_linkedin_message(false, false, $message['shub_linkedin_message_id']);
		    $comments         = $linkedin_message->get_comments();
		?>
		<tr class="<?php echo isset($settings['row_class']) ? $settings['row_class'] : '';?> linkedin_message_row <?php echo !isset($message['read_time']) || !$message['read_time'] ? ' message_row_unread' : '';?>"
	        data-id="<?php echo (int) $message['shub_linkedin_message_id']; ?>">
		    <td class="shub_column_account">
			    <img src="<?php echo plugins_url('networks/linkedin/linkedin-logo.png', _DTBAKER_SUPPORT_HUB_CORE_FILE_);?>" class="linkedin_icon">
			    <a href="<?php echo $linkedin_message->get_link(); ?>"
		           target="_blank"><?php
				    echo htmlspecialchars( $linkedin_message->get('linkedin_group') ? $linkedin_message->get('linkedin_group')->get( 'group_name' ) : 'Share' ); ?></a> <br/>
			    <?php echo htmlspecialchars( $linkedin_message->get_type_pretty() ); ?>
		    </td>
		    <td class="shub_column_time"><?php echo shub_print_date( $message['message_time'], true ); ?></td>
		    <td class="shub_column_from">
			    <?php
		        // work out who this is from.
		        $from = $linkedin_message->get_from();
			    ?>
			    <div class="shub_from_holder shub_linkedin">
			    <div class="shub_from_full">
				    <?php
					foreach($from as $id => $from_data){
						?>
						<div>
							<a href="<?php echo $from_data['link'];?>" target="_blank"><img src="<?php echo $from_data['image'];?>" class="shub_from_picture"></a> <?php echo htmlspecialchars($from_data['name']); ?>
						</div>
						<?php
					} ?>
			    </div>
		        <?php
		        reset($from);
		        if(isset($from_data)) {
			        echo '<a href="' . $from_data['link'] . '" target="_blank">' . '<img src="' . $from_data['image'] . '" class="shub_from_picture"></a> ';
			        echo '<span class="shub_from_count">';
			        if ( count( $from ) > 1 ) {
				        echo '+' . ( count( $from ) - 1 );
			        }
			        echo '</span>';
		        }
		        ?>
			    </div>
		    </td>
		    <td class="shub_column_summary">
			    <span style="float:right;">
				    <?php echo count( $comments ) > 0 ? '('.count( $comments ).')' : ''; ?>
			    </span>
			    <div class="linkedin_message_summary<?php echo !isset($message['read_time']) || !$message['read_time'] ? ' unread' : '';?>"> <?php
				    $summary = $linkedin_message->get_summary();
				    echo $summary;
				    ?>
			    </div>
		    </td>
			<!--<td></td>-->
		    <td nowrap class="shub_column_action">

			        <a href="<?php echo $linkedin_message->link_open();?>" class="sociallinkedin_message_open shub_modal button" data-modaltitle="<?php echo htmlspecialchars($summary);?>" data-sociallinkedinmessageid="<?php echo (int)$linkedin_message->get('shub_linkedin_message_id');?>"><?php _e( 'Open' );?></a>

				    <?php if($linkedin_message->get('status') == _shub_MESSAGE_STATUS_ANSWERED){  ?>
					    <a href="#" class="sociallinkedin_message_action  button"
					       data-action="set-unanswered" data-id="<?php echo (int)$linkedin_message->get('shub_linkedin_message_id');?>"><?php _e( 'Inbox' ); ?></a>
				    <?php }else{ ?>
					    <a href="#" class="sociallinkedin_message_action  button"
					       data-action="set-answered" data-id="<?php echo (int)$linkedin_message->get('shub_linkedin_message_id');?>"><?php _e( 'Archive' ); ?></a>
				    <?php } ?>
		    </td>
	    </tr>
		<?php
	}

	public function init_js(){
		?>
		    ucm.social.linkedin.api_url = ajaxurl;
		    ucm.social.linkedin.init();
		<?php
	}

	public function handle_process($process, $options = array()){
		switch($process){
			case 'send_shub_message':
				$message_count = 0;
				if(check_admin_referer( 'shub_send-message' ) && isset($options['shub_message_id']) && (int)$options['shub_message_id'] > 0 && isset($_POST['linkedin_message']) && !empty($_POST['linkedin_message'])){
					// we have a social message id, ready to send!
					// which linkedin accounts are we sending too?
					$linkedin_accounts = isset($_POST['compose_linkedin_id']) && is_array($_POST['compose_linkedin_id']) ? $_POST['compose_linkedin_id'] : array();
					foreach($linkedin_accounts as $linkedin_account_id => $send_groups){
						$linkedin_account = new shub_linkedin_account($linkedin_account_id);
						if($linkedin_account->get('shub_linkedin_id') == $linkedin_account_id){
							/* @var $available_groups shub_linkedin_group[] */
				            $available_groups = $linkedin_account->get('groups');
							if($send_groups){
							    foreach($send_groups as $linkedin_group_id => $tf){
								    if(!$tf)continue;// shouldnt happen
								    switch($linkedin_group_id){
									    case 'share':
										    // doing a status update to this linkedin account
											$linkedin_message = new shub_linkedin_message($linkedin_account, false, false);
										    $linkedin_message->create_new();
										    $linkedin_message->update('shub_linkedin_group_id',0);
							                $linkedin_message->update('shub_message_id',$options['shub_message_id']);
										    $linkedin_message->update('shub_linkedin_id',$linkedin_account->get('shub_linkedin_id'));
										    $linkedin_message->update('summary',isset($_POST['linkedin_message']) ? $_POST['linkedin_message'] : '');
										    $linkedin_message->update('title',isset($_POST['linkedin_title']) ? $_POST['linkedin_title'] : '');
										    $linkedin_message->update('link',isset($_POST['linkedin_link']) ? $_POST['linkedin_link'] : '');
										    if(isset($_POST['track_links']) && $_POST['track_links']){
												$linkedin_message->parse_links();
											}
										    $linkedin_message->update('type','share');
										    $linkedin_message->update('data',json_encode($_POST));
										    $linkedin_message->update('user_id',get_current_user_id());
										    // do we send this one now? or schedule it later.
										    $linkedin_message->update('status',_shub_MESSAGE_STATUS_PENDINGSEND);
										    if(isset($options['send_time']) && !empty($options['send_time'])){
											    // schedule for sending at a different time (now or in the past)
											    $linkedin_message->update('last_active',$options['send_time']);
										    }else{
											    // send it now.
											    $linkedin_message->update('last_active',0);
										    }
										    if(isset($_FILES['linkedin_picture']['tmp_name']) && is_uploaded_file($_FILES['linkedin_picture']['tmp_name'])){
											    $linkedin_message->add_attachment($_FILES['linkedin_picture']['tmp_name']);
										    }
											$now = time();
											if(!$linkedin_message->get('last_active') || $linkedin_message->get('last_active') <= $now){
												// send now! otherwise we wait for cron job..
												if($linkedin_message->send_queued(isset($_POST['debug']) && $_POST['debug'])){
										            $message_count ++;
												}
											}else{
										        $message_count ++;
												if(isset($_POST['debug']) && $_POST['debug']){
													echo "Message will be sent in cron job after ".shub_print_date($linkedin_message->get('last_active'),true);
												}
											}
										    break;
									    case 'blog':
											// doing a blog post to this linkedin account
											// not possible through api

										    break;
									    default:
										    // posting to one of our available groups:

										    // see if this is an available group.
										    if(isset($available_groups[$linkedin_group_id])){
											    // push to db! then send.
											    $linkedin_message = new shub_linkedin_message($linkedin_account, $available_groups[$linkedin_group_id], false);
											    $linkedin_message->create_new();
											    $linkedin_message->update('shub_linkedin_group_id',$available_groups[$linkedin_group_id]->get('shub_linkedin_group_id'));
								                $linkedin_message->update('shub_message_id',$options['shub_message_id']);
											    $linkedin_message->update('shub_linkedin_id',$linkedin_account->get('shub_linkedin_id'));
											    $linkedin_message->update('summary',isset($_POST['linkedin_message']) ? $_POST['linkedin_message'] : '');
											    $linkedin_message->update('title',isset($_POST['linkedin_title']) ? $_POST['linkedin_title'] : '');
											    if(isset($_POST['track_links']) && $_POST['track_links']){
													$linkedin_message->parse_links();
												}
											    $linkedin_message->update('type','group_post');
											    $linkedin_message->update('link',isset($_POST['link']) ? $_POST['link'] : '');
											    $linkedin_message->update('data',json_encode($_POST));
											    $linkedin_message->update('user_id',get_current_user_id());
											    // do we send this one now? or schedule it later.
											    $linkedin_message->update('status',_shub_MESSAGE_STATUS_PENDINGSEND);
											    if(isset($options['send_time']) && !empty($options['send_time'])){
												    // schedule for sending at a different time (now or in the past)
												    $linkedin_message->update('last_active',$options['send_time']);
											    }else{
												    // send it now.
												    $linkedin_message->update('last_active',0);
											    }
											    if(isset($_FILES['linkedin_picture']['tmp_name']) && is_uploaded_file($_FILES['linkedin_picture']['tmp_name'])){
												    $linkedin_message->add_attachment($_FILES['linkedin_picture']['tmp_name']);
											    }
												$now = time();
												if(!$linkedin_message->get('last_active') || $linkedin_message->get('last_active') <= $now){
													// send now! otherwise we wait for cron job..
													if($linkedin_message->send_queued(isset($_POST['debug']) && $_POST['debug'])){
											            $message_count ++;
													}
												}else{
											        $message_count ++;
													if(isset($_POST['debug']) && $_POST['debug']){
														echo "Message will be sent in cron job after ".shub_print_date($linkedin_message->get('last_active'),true);
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
			case 'save_linkedin':
				$shub_linkedin_id = isset($_REQUEST['shub_linkedin_id']) ? (int)$_REQUEST['shub_linkedin_id'] : 0;
				if(check_admin_referer( 'save-linkedin'.$shub_linkedin_id )) {
					$linkedin = new shub_linkedin_account( $shub_linkedin_id );
					if ( isset( $_POST['butt_delete'] ) ) {
						$linkedin->delete();
						$redirect = 'admin.php?page=support_hub_settings&tab=linkedin';
					} else {
						$linkedin->save_data( $_POST );
						$shub_linkedin_id = $linkedin->get( 'shub_linkedin_id' );
						if ( isset( $_POST['butt_save_reconnect'] ) ) {
							$redirect = $linkedin->link_connect();
						} else {
							$redirect = $linkedin->link_edit();
						}
					}
					header( "Location: $redirect" );
					exit;
				}

				break;
		}
	}

	public function handle_ajax($action, $support_hub_wp){
		switch($action){
			case 'send-message-reply':
				if (!headers_sent())header('Content-type: text/javascript');
				if(isset($_REQUEST['linkedin_id']) && !empty($_REQUEST['linkedin_id']) && isset($_REQUEST['id']) && (int)$_REQUEST['id'] > 0) {
					$shub_linkedin_message = new shub_linkedin_message( false, false, $_REQUEST['id'] );
					if($shub_linkedin_message->get('shub_linkedin_message_id') == $_REQUEST['id']){
						$return  = array();
						$message = isset( $_POST['message'] ) && $_POST['message'] ? $_POST['message'] : '';
						$linkedin_id = isset( $_REQUEST['linkedin_id'] ) && $_REQUEST['linkedin_id'] ? $_REQUEST['linkedin_id'] : false;
						$debug = isset( $_POST['debug'] ) && $_POST['debug'] ? $_POST['debug'] : false;
						if ( $message ) {
							if($debug)ob_start();
							$shub_linkedin_message->send_reply( $linkedin_id, $message, $debug );
							if($debug){
								$return['message'] = ob_get_clean();
							}else {
								//set_message( _l( 'Message sent and conversation archived.' ) );
								$return['redirect'] = 'admin.php?page=support_hub_main';

							}
						}
						echo json_encode( $return );
					}

				}
				break;
			case 'modal':
				if(isset($_REQUEST['sociallinkedinmessageid']) && (int)$_REQUEST['sociallinkedinmessageid'] > 0) {
					$shub_linkedin_message = new shub_linkedin_message( false, false, $_REQUEST['sociallinkedinmessageid'] );
					if($shub_linkedin_message->get('shub_linkedin_message_id') == $_REQUEST['sociallinkedinmessageid']){

						$shub_linkedin_id = $shub_linkedin_message->get('linkedin_account')->get('shub_linkedin_id');
						$shub_linkedin_message_id = $shub_linkedin_message->get('shub_linkedin_message_id');
						include( trailingslashit( $support_hub_wp->dir ) . 'networks/linkedin/linkedin_message.php');
					}

				}
				break;
			case 'set-answered':
				if (!headers_sent())header('Content-type: text/javascript');
				if(isset($_REQUEST['shub_linkedin_message_id']) && (int)$_REQUEST['shub_linkedin_message_id'] > 0){
					$shub_linkedin_message = new shub_linkedin_message(false, false, $_REQUEST['shub_linkedin_message_id']);
					if($shub_linkedin_message->get('shub_linkedin_message_id') == $_REQUEST['shub_linkedin_message_id']){
						$shub_linkedin_message->update('status',_shub_MESSAGE_STATUS_ANSWERED);
						?>
						jQuery('.sociallinkedin_message_action[data-id=<?php echo (int)$shub_linkedin_message->get('shub_linkedin_message_id'); ?>]').parents('tr').first().hide();
						<?php
					}
				}
				break;
			case 'set-unanswered':
				if (!headers_sent())header('Content-type: text/javascript');
				if(isset($_REQUEST['shub_linkedin_message_id']) && (int)$_REQUEST['shub_linkedin_message_id'] > 0){
					$shub_linkedin_message = new shub_linkedin_message(false, false, $_REQUEST['shub_linkedin_message_id']);
					if($shub_linkedin_message->get('shub_linkedin_message_id') == $_REQUEST['shub_linkedin_message_id']){
						$shub_linkedin_message->update('status',_shub_MESSAGE_STATUS_UNANSWERED);
						?>
						jQuery('.sociallinkedin_message_action[data-id=<?php echo (int)$shub_linkedin_message->get('shub_linkedin_message_id'); ?>]').parents('tr').first().hide();
						<?php
					}
				}
				break;
		}
		return false;
	}


	public function run_cron( $debug = false ){
		if($debug)echo "Starting LinkedIn Cron Job \n";
		$accounts = $this->get_accounts();
		foreach($accounts as $account){
			$shub_linkedin_account = new shub_linkedin_account( $account['shub_linkedin_id'] );
			$shub_linkedin_account->run_cron($debug);
			$groups = $shub_linkedin_account->get('groups');
			/* @var $groups shub_linkedin_group[] */
			foreach($groups as $group){
				$group->run_cron($debug);
			}
		}
		if($debug)echo "Finished LinkedIn Cron Job \n";
	}

	public function get_install_sql() {

		global $wpdb;

		$sql = <<< EOT



CREATE TABLE {$wpdb->prefix}shub_linkedin (
  shub_linkedin_id int(11) NOT NULL AUTO_INCREMENT,
  linkedin_name varchar(50) NOT NULL,
  last_checked int(11) NOT NULL DEFAULT '0',
  import_stream int(11) NOT NULL DEFAULT '0',
  post_stream int(11) NOT NULL DEFAULT '0',
  linkedin_data text NOT NULL,
  linkedin_token varchar(255) NOT NULL,
  linkedin_app_id varchar(255) NOT NULL,
  linkedin_app_secret varchar(255) NOT NULL,
  machine_id varchar(255) NOT NULL,
  PRIMARY KEY  shub_linkedin_id (shub_linkedin_id)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;

CREATE TABLE {$wpdb->prefix}shub_linkedin_message (
  shub_linkedin_message_id int(11) NOT NULL AUTO_INCREMENT,
  shub_linkedin_id int(11) NOT NULL,
  shub_message_id int(11) NOT NULL DEFAULT '0',
  shub_linkedin_group_id int(11) NOT NULL,
  linkedin_id varchar(255) NOT NULL,
  summary text NOT NULL,
  title text NOT NULL,
  last_active int(11) NOT NULL DEFAULT '0',
  comments text NOT NULL,
  type varchar(20) NOT NULL,
  link varchar(255) NOT NULL,
  data text NOT NULL,
  status tinyint(1) NOT NULL DEFAULT '0',
  user_id int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY  shub_linkedin_message_id (shub_linkedin_message_id),
  KEY shub_linkedin_id (shub_linkedin_id),
  KEY shub_message_id (shub_message_id),
  KEY last_active (last_active),
  KEY shub_linkedin_group_id (shub_linkedin_group_id),
  KEY linkedin_id (linkedin_id),
  KEY status (status)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;


CREATE TABLE {$wpdb->prefix}shub_linkedin_message_read (
  shub_linkedin_message_id int(11) NOT NULL,
  read_time int(11) NOT NULL DEFAULT '0',
  user_id int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY  shub_linkedin_message_id (shub_linkedin_message_id,user_id)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;


CREATE TABLE {$wpdb->prefix}shub_linkedin_message_comment (
  shub_linkedin_message_comment_id int(11) NOT NULL AUTO_INCREMENT,
  shub_linkedin_message_id int(11) NOT NULL,
  linkedin_id varchar(255) NOT NULL,
  time int(11) NOT NULL,
  message_from text NOT NULL,
  message_to text NOT NULL,
  comment_text text NOT NULL,
  data text NOT NULL,
  user_id int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY  shub_linkedin_message_comment_id (shub_linkedin_message_comment_id),
  KEY shub_linkedin_message_id (shub_linkedin_message_id),
  KEY linkedin_id (linkedin_id)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;


CREATE TABLE {$wpdb->prefix}shub_linkedin_message_link (
  shub_linkedin_message_link_id int(11) NOT NULL AUTO_INCREMENT,
  shub_linkedin_message_id int(11) NOT NULL DEFAULT '0',
  link varchar(255) NOT NULL,
  PRIMARY KEY  shub_linkedin_message_link_id (shub_linkedin_message_link_id),
  KEY shub_linkedin_message_id (shub_linkedin_message_id)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;

CREATE TABLE {$wpdb->prefix}shub_linkedin_message_link_click (
  shub_linkedin_message_link_click_id int(11) NOT NULL AUTO_INCREMENT,
  shub_linkedin_message_link_id int(11) NOT NULL DEFAULT '0',
  click_time int(11) NOT NULL,
  ip_address varchar(20) NOT NULL,
  user_agent varchar(100) NOT NULL,
  url_referrer varchar(255) NOT NULL,
  PRIMARY KEY  shub_linkedin_message_link_click_id (shub_linkedin_message_link_click_id),
  KEY shub_linkedin_message_link_id (shub_linkedin_message_link_id)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;

CREATE TABLE {$wpdb->prefix}shub_linkedin_group (
  shub_linkedin_group_id int(11) NOT NULL AUTO_INCREMENT,
  shub_linkedin_id int(11) NOT NULL,
  group_name varchar(50) NOT NULL,
  last_message int(11) NOT NULL DEFAULT '0',
  last_checked int(11) NOT NULL,
  group_id varchar(255) NOT NULL,
  linkedin_token varchar(255) NOT NULL,
  PRIMARY KEY  shub_linkedin_group_id (shub_linkedin_group_id),
  KEY shub_linkedin_id (shub_linkedin_id)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;

EOT;
		return $sql;
	}

}
