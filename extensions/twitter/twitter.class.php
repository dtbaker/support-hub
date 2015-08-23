<?php

define('_TWITTER_MESSAGE_TYPE_MENTION',1);
define('_TWITTER_MESSAGE_TYPE_MYTWEET',2);
define('_TWITTER_MESSAGE_TYPE_OTHERTWEET',3);
define('_TWITTER_MESSAGE_TYPE_MYRETWEET',4);
define('_TWITTER_MESSAGE_TYPE_OTHERRETWEET',5);
define('_TWITTER_MESSAGE_TYPE_DIRECT',6);


define('_support_hub_TWITTER_LINK_REWRITE_PREFIX','sstlnk');

class shub_twitter extends SupportHub_extension {

	public function init(){
		if(isset($_GET[_support_hub_TWITTER_LINK_REWRITE_PREFIX]) && strlen($_GET[_support_hub_TWITTER_LINK_REWRITE_PREFIX]) > 0){
			// check hash
			$bits = explode(':',$_GET[_support_hub_TWITTER_LINK_REWRITE_PREFIX]);
			if(defined('AUTH_KEY') && isset($bits[1])){
				$shub_twitter_message_link_id = (int)$bits[0];
				if($shub_twitter_message_link_id > 0){
					$correct_hash = substr(md5(AUTH_KEY.' twitter link '.$shub_twitter_message_link_id),1,5);
					if($correct_hash == $bits[1]){
						// link worked! log a visit and redirect.
						$link = shub_get_single('shub_twitter_message_link','shub_twitter_message_link_id',$shub_twitter_message_link_id);
						if($link){
							if(!preg_match('#^http#',$link['link'])){
								$link['link'] = 'http://'.trim($link['link']);
							}
							shub_update_insert('shub_twitter_message_link_click_id',false,'shub_twitter_message_link_click',array(
								'shub_twitter_message_link_id' => $shub_twitter_message_link_id,
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


	public function get_friendly_icon(){
		return '<img src="'.plugins_url('extensions/twitter/twitter-logo.png', _DTBAKER_SUPPORT_HUB_CORE_FILE_).'" class="shub_friendly_icon">';
	}

	public function init_menu(){

	}

	public function page_assets($from_master=false){
		if(!$from_master)SupportHub::getInstance()->inbox_assets();

		wp_register_style( 'support-hub-twitter-css', plugins_url('extensions/twitter/shub_twitter.css',_DTBAKER_SUPPORT_HUB_CORE_FILE_), array(), '1.0.0' );
		wp_enqueue_style( 'support-hub-twitter-css' );
		wp_register_script( 'support-hub-twitter', plugins_url('extensions/twitter/shub_twitter.js',_DTBAKER_SUPPORT_HUB_CORE_FILE_), array( 'jquery' ), '1.0.0' );
		wp_enqueue_script( 'support-hub-twitter' );
		wp_register_script( 'twitter-text', plugins_url('extensions/twitter/twitter-text.js',_DTBAKER_SUPPORT_HUB_CORE_FILE_), array( 'jquery' ), '1.0.0' );
		wp_enqueue_script( 'twitter-text' );


	}


	public function settings_page(){
		include( dirname(__FILE__) . '/twitter_settings.php');
	}

	public function compose_to(){
		$accounts = $this->get_accounts();
	    if(!count($accounts)){
		    _e('No accounts configured', 'support_hub');
	    }
		foreach ( $accounts as $account ) {
			$twitter_account = new shub_twitter_account( $account['shub_twitter_id'] );
		    echo '<div class="twitter_compose_account_select">' .
				    '<input type="checkbox" name="compose_twitter_id['.$twitter_account->get('shub_twitter_id').']" value="1"> ' .
				    ($twitter_account->get_picture() ? '<img src="'.$twitter_account->get_picture().'">' : '' ).
				     '<span>' . htmlspecialchars( $twitter_account->get('twitter_name') ) . '</span>' .
				    '</div>'
			    ;
	    }
	}
	public function compose_message($defaults){
		?>
		<textarea name="twitter_message" class="twitter_compose_message" rows="6" cols="50" id="twitter_compose_message"><?php echo isset($defaults['twitter_message']) ? esc_attr($defaults['twitter_message']) : '';?></textarea>
				    <span class="twitter_characters_remain"><span>140</span> characters remaining.</span>
		<?php
	}
	public function compose_type($defaults){
		?>
		<input type="radio" name="twitter_post_type" id="twitter_post_type_normal" value="normal" checked>
		<label for="twitter_post_type_normal">Normal Tweet</label>
	    <input type="radio" name="twitter_post_type" id="twitter_post_type_picture" value="picture">
		<label for="twitter_post_type_picture">Picture Tweet</label>
	    <table>
		    <tr style="display: none">
			    <th>
				    Picture
			    </th>
			    <td>
				    <input type="file" name="twitter_picture" value="">
				    <br/><small>(ensure picture is smaller than 1200x1200 px)</small>
				    <span class="twitter-type-picture twitter-type-option"></span>
			    </td>
		    </tr>
	    </table>
		<?php
	}

	
	private function reset() {
		$this->accounts = array();
	}


	public function get_accounts() {
		$this->accounts = shub_get_multiple( 'shub_twitter', array(), 'shub_twitter_id' );
		return $this->accounts;
	}

	public function get($key){
		return get_option('support_hub_twitter_'.$key);
	}
	public function update($key,$value){
		update_option('support_hub_twitter_'.$key, $value);
	}

	public static function format_person($data){
		$return = '';
		if($data && isset($data['screen_name'])){
			$return .= '<a href="//twitter.com/'.$data['screen_name'].(isset($data['tweet_id']) && $data['tweet_id'] ? '/status/'.$data['tweet_id'] : '').'" target="_blank">';
		}
		if($data && isset($data['name'])){
			$return .= htmlspecialchars($data['name']);
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

		$sql = "SELECT m.*, mr.read_time FROM `"._support_hub_DB_PREFIX."shub_twitter_message` m ";
		$sql .= " LEFT OUTER JOIN `"._support_hub_DB_PREFIX."shub_twitter_message_read` mr ON ( m.shub_twitter_message_id = mr.shub_twitter_message_id AND mr.user_id = ".get_current_user_id()." )";
		$sql .= " WHERE 1 ";
		if(isset($search['shub_status']) && $search['shub_status'] !== false){
			$sql .= " AND `shub_status` = ".(int)$search['shub_status'];
		}
		if(isset($search['shub_message_id']) && $search['shub_message_id'] !== false){
			$sql .= " AND `shub_message_id` = ".(int)$search['shub_message_id'];
		}
		if(isset($search['shub_twitter_id']) && $search['shub_twitter_id'] !== false){
			$sql .= " AND `shub_twitter_id` = ".(int)$search['shub_twitter_id'];
		}
		if(isset($search['generic']) && !empty($search['generic'])){
			$sql .= " AND `summary` LIKE '%".esc_sql($search['generic'])."%'";
		}else{
			$sql .= " AND `shub_type` != "._TWITTER_MESSAGE_TYPE_OTHERTWEET;
		}
        if(empty($order)){
            $sql .= " ORDER BY `message_time` ASC ";
        }else{
            switch($order['orderby']){
                case 'shub_column_time':
                    $sql .= " ORDER BY `message_time` ";
                    $sql .= $order['order'] == 'asc' ? 'ASC' : 'DESC';
                    break;
            }
        }
		if($limit_batch){
			$sql .= " LIMIT ".$this->limit_start.', '.$limit_batch;
			$this->limit_start += $limit_batch;
		}
		//$this->all_messages = shub_query($sql);
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
			$twitter_message = new shub_twitter_message(false, $message['shub_twitter_message_id']);
			$data['message'] = $twitter_message;
			$data['shub_column_account'] .= '<div><img src="'.plugins_url('extensions/twitter/twitter-logo.png', _DTBAKER_SUPPORT_HUB_CORE_FILE_).'" class="twitter_icon small"><a href="'.$twitter_message->get_link().'" target="_blank">'.htmlspecialchars( $twitter_message->get('twitter_account')->get( 'account_name' ) ) .'</a></div>';
			$data['shub_column_summary'] .= '<div><img src="'.plugins_url('extensions/twitter/twitter-logo.png', _DTBAKER_SUPPORT_HUB_CORE_FILE_).'" class="twitter_icon small"><a href="'.$twitter_message->get_link().'" target="_blank">'.htmlspecialchars( $twitter_message->get_summary() ) .'</a></div>';
			// how many link clicks does this one have?
			$sql = "SELECT count(*) AS `link_clicks` FROM ";
			$sql .= " `"._support_hub_DB_PREFIX."shub_twitter_message` m ";
			$sql .= " LEFT JOIN `"._support_hub_DB_PREFIX."shub_twitter_message_link` ml USING (shub_twitter_message_id) ";
			$sql .= " LEFT JOIN `"._support_hub_DB_PREFIX."shub_twitter_message_link_click` lc USING (shub_twitter_message_link_id) ";
			$sql .= " WHERE 1 ";
			$sql .= " AND m.shub_twitter_message_id = ".(int)$message['shub_twitter_message_id'];
			$sql .= " AND lc.shub_twitter_message_link_id IS NOT NULL ";
			$sql .= " AND lc.user_agent NOT LIKE '%Google%' ";
			$sql .= " AND lc.user_agent NOT LIKE '%Yahoo%' ";
			$sql .= " AND lc.user_agent NOT LIKE '%Meta%' ";
			$sql .= " AND lc.user_agent NOT LIKE '%Slurp%' ";
			$sql .= " AND lc.user_agent NOT LIKE '%Bot%' ";
			$sql .= " AND lc.user_agent != 'Mozilla/5.0 ()' ";
			$res = shub_qa1($sql);
			$link_clicks = $res && $res['link_clicks'] ? $res['link_clicks'] : 0;
			$data['shub_column_links'] .= '<div><img src="'.plugins_url('extensions/twitter/twitter-logo.png', _DTBAKER_SUPPORT_HUB_CORE_FILE_).'" class="twitter_icon small">'. $link_clicks  .'</div>';
		}
		if(count($messages) && $link_clicks > 0){
			//$data['shub_column_links'] = '<div><img src="'.plugins_url('extensions/twitter/twitter-logo.png', _DTBAKER_SUPPORTHUB_CORE_FILE_).'" class="twitter_icon small">'. $link_clicks  .'</div>';
		}
		return $data;

	}

	public function get_unread_count($search=array()){
		if(!get_current_user_id())return 0;
		$sql = "SELECT count(*) AS `unread` FROM `"._support_hub_DB_PREFIX."shub_twitter_message` m ";
		$sql .= " WHERE 1 ";
		$sql .= " AND m.shub_twitter_message_id NOT IN (SELECT mr.shub_twitter_message_id FROM `"._support_hub_DB_PREFIX."shub_twitter_message_read` mr WHERE mr.user_id = '".(int)get_current_user_id()."' AND mr.shub_twitter_message_id = m.shub_twitter_message_id)";
		$sql .= " AND m.`shub_status` = "._shub_MESSAGE_STATUS_UNANSWERED;
		if(isset($search['shub_twitter_id']) && $search['shub_twitter_id'] !== false){
			$sql .= " AND m.`shub_twitter_id` = ".(int)$search['shub_twitter_id'];
		}
		$sql .= " AND m.`shub_type` != "._TWITTER_MESSAGE_TYPE_OTHERTWEET;
		$res = shub_qa1($sql);
		return $res ? $res['unread'] : 0;
	}

	public function output_row($message, $settings=array()){
		$twitter_message = new shub_twitter_message(false, $message['shub_twitter_message_id']);
		?>
		<tr class="<?php echo isset($settings['row_class']) ? $settings['row_class'] : '';?> twitter_message_row <?php echo !isset($message['read_time']) || !$message['read_time'] ? ' message_row_unread' : '';?>"
	        data-id="<?php echo (int) $message['shub_twitter_message_id']; ?>"
	        data-shub_twitter_id="<?php echo (int) $message['shub_twitter_id']; ?>">
		    <td class="shub_column_account">
			    <img src="<?php echo plugins_url('extensions/twitter/twitter-logo.png', _DTBAKER_SUPPORT_HUB_CORE_FILE_);?>" class="twitter_icon">
			    <a href="<?php echo $twitter_message->get_link(); ?>"
		           target="_blank"><?php echo htmlspecialchars( $twitter_message->get('twitter_account')->get( 'account_name' ) ); ?></a> <br/>
			    <?php echo htmlspecialchars( $twitter_message->get_type_pretty() ); ?>
		    </td>
		    <td class="shub_column_time"><?php echo shub_print_date( $message['message_time'], true ); ?></td>
		    <td class="shub_column_from">
			    <?php
		        // work out who this is from.
		        $from = $twitter_message->get_from();
			    ?>
			    <div class="shub_from_holder shub_twitter">
			    <div class="shub_from_full">
				    <?php
					foreach($from as $id => $from_data){
						?>
						<div>
							<a href="//twitter.com/<?php echo htmlspecialchars($from_data['screen_name']);?>" target="_blank"><img src="<?php echo $from_data['image'];?>" class="shub_from_picture"></a> <?php echo htmlspecialchars($from_data['screen_name']); ?>
						</div>
						<?php
					} ?>
			    </div>
		        <?php
		        reset($from);
		        $current = current($from);
		        echo '<a href="//twitter.com/'.htmlspecialchars($current['screen_name']).'" target="_blank">' . '<img src="'.$current['image'].'" class="shub_from_picture"></a> ';
		        echo '<span class="shub_from_count">';
		        if(count($from) > 1){
			        echo '+'.(count($from)-1);
		        }
		        echo '</span>';
		        ?>
			    </div>
		    </td>
		    <td class="shub_column_summary">
			    <div class="twitter_message_summary<?php echo !isset($message['read_time']) || !$message['read_time'] ? ' unread' : '';?>"> <?php
				    echo $twitter_message->get_summary();
				    ?>
			    </div>
		    </td>
		    <!--<td></td>-->
		    <td nowrap class="shub_column_action">

		        <a href="<?php echo $twitter_message->link_open();?>" class="socialtwitter_message_open shub_modal button" data-modaltitle="<?php echo __('Tweet','support_hub');?>" data-socialtwittermessageid="<?php echo (int)$twitter_message->get('shub_twitter_message_id');?>"><?php _e( 'Open' );?></a>

			    <?php if($twitter_message->get('shub_status') == _shub_MESSAGE_STATUS_ANSWERED){  ?>
				    <a href="#" class="socialtwitter_message_action button"
				       data-action="set-unanswered" data-id="<?php echo (int)$twitter_message->get('shub_twitter_message_id');?>" data-shub_twitter_id="<?php echo (int)$twitter_message->get('shub_twitter_id');?>"><?php _e( 'Inbox' ); ?></a>
			    <?php }else{ ?>
				    <a href="#" class="socialtwitter_message_action button"
				       data-action="set-answered" data-id="<?php echo (int)$twitter_message->get('shub_twitter_message_id');?>" data-shub_twitter_id="<?php echo (int)$twitter_message->get('shub_twitter_id');?>"><?php _e( 'Archive' ); ?></a>
			    <?php } ?>
		    </td>
	    </tr>
		<?php
	}

	public function init_js(){
		?>
		    ucm.social.twitter.api_url = ajaxurl;
		    ucm.social.twitter.init();
		<?php
	}

	public function handle_process($process, $options = array()){
		switch($process){
			case 'send_shub_message':
				$message_count = 0;
				if(check_admin_referer( 'shub_send-message' ) && isset($options['shub_message_id']) && (int)$options['shub_message_id'] > 0 && isset($_POST['twitter_message']) && !empty($_POST['twitter_message'])){
					// we have a social message id, ready to send!
					// which twitter accounts are we sending too?
					$twitter_accounts = isset($_POST['compose_twitter_id']) && is_array($_POST['compose_twitter_id']) ? $_POST['compose_twitter_id'] : array();
					foreach($twitter_accounts as $twitter_account_id => $tf){
						if(!$tf)continue; // shoulnd't happen, as checkbox shouldn't post.
						$twitter_account = new shub_twitter_account($twitter_account_id);
						if($twitter_account->get('shub_twitter_id') == $twitter_account_id){
							// good to go! send us a message!


							$twitter_message = new shub_twitter_message($twitter_account, false);
						    $twitter_message->create_new();
						    $twitter_message->update('shub_twitter_id',$twitter_account->get('shub_twitter_id'));
						    $twitter_message->update('shub_message_id',$options['shub_message_id']);
						    $twitter_message->update('summary',isset($_POST['twitter_message']) ? $_POST['twitter_message'] : '');
							if(isset($_POST['track_links']) && $_POST['track_links']){
								$twitter_message->parse_links();
							}
						    $twitter_message->update('type','pending');
						    $twitter_message->update('data',json_encode($_POST));
						    $twitter_message->update('user_id',get_current_user_id());
						    // do we send this one now? or schedule it later.
						    $twitter_message->update('shub_status',_shub_MESSAGE_STATUS_PENDINGSEND);
						    if(isset($options['send_time']) && !empty($options['send_time'])){
							    // schedule for sending at a different time (now or in the past)
							    $twitter_message->update('message_time',$options['send_time']);
						    }else{
							    // send it now.
							    $twitter_message->update('message_time',0);
						    }
						    if(isset($_FILES['twitter_picture']['tmp_name']) && is_uploaded_file($_FILES['twitter_picture']['tmp_name'])){
							    $twitter_message->add_attachment($_FILES['twitter_picture']['tmp_name']);
						    }
							$now = current_time('timestamp');
							//echo "Scheduled a tweet for ".$twitter_message->get('message_time')." ( ".shub_print_date($twitter_message->get('message_time'),true)." ) time now is $now ( ".shub_print_date($now,true)." )";exit;
							if(!$twitter_message->get('message_time') || $twitter_message->get('message_time') <= $now){
								// send now! otherwise we wait for cron job..
								if($twitter_message->send_queued(isset($_POST['debug']) && $_POST['debug'])){
									$message_count ++;
								}
							}else{
						        $message_count ++;
								if(isset($_POST['debug']) && $_POST['debug']){
									echo "Message will be sent in cron job after ".shub_print_date($twitter_message->get('message_time'),true);
								}
							}
						}
					}
				}
				return $message_count;
				break;
			case 'save_twitter_settings':
				if(check_admin_referer( 'save-twitter-settings' )) {
					if ( isset( $_POST['twitter_app_api_key'] ) ) {
						$this->update( 'api_key', $_POST['twitter_app_api_key'] );
					}
					if ( isset( $_POST['twitter_app_api_secret'] ) ) {
						$this->update( 'api_secret', $_POST['twitter_app_api_secret'] );
					}
				}
				break;
			case 'save_twitter':
				$shub_twitter_id = isset($_REQUEST['shub_twitter_id']) ? (int)$_REQUEST['shub_twitter_id'] : 0;
				if(check_admin_referer( 'save-twitter'.$shub_twitter_id )) {
					$twitter = new shub_twitter_account( $shub_twitter_id );
					if ( isset( $_POST['butt_delete'] ) ) {
						$twitter->delete();
						$redirect = 'admin.php?page=support_hub_settings&tab=twitter';
					} else {
						$twitter->save_data( $_POST );
						$shub_twitter_id = $twitter->get( 'shub_twitter_id' );
						if ( isset( $_POST['butt_save_reconnect'] ) ) {
							$redirect = $twitter->link_connect();
						} else {
							$redirect = $twitter->link_edit();
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
				if(isset($_REQUEST['shub_twitter_id']) && !empty($_REQUEST['shub_twitter_id']) && isset($_REQUEST['id']) && (int)$_REQUEST['id'] > 0) {
					$shub_twitter = new shub_twitter_account($_REQUEST['shub_twitter_id']);
					if($shub_twitter->get('shub_twitter_id') == $_REQUEST['shub_twitter_id']){
						$shub_twitter_message = new shub_twitter_message( $shub_twitter, $_REQUEST['id'] );
						if($shub_twitter_message->get('shub_twitter_message_id') == $_REQUEST['id']) {
							$return  = array();
							$message = isset( $_POST['message'] ) && $_POST['message'] ? $_POST['message'] : '';
							$debug   = isset( $_POST['debug'] ) && $_POST['debug'] ? $_POST['debug'] : false;
							if ( $message ) {
								ob_start();



								//$twitter_message->send_reply( $message, $debug );
								$new_twitter_message = new shub_twitter_message( $shub_twitter, false );
								$new_twitter_message->create_new();
								$new_twitter_message->update( 'reply_to_id', $shub_twitter_message->get( 'shub_twitter_message_id' ) );
								$new_twitter_message->update( 'shub_twitter_id', $shub_twitter->get( 'shub_twitter_id' ) );
								$new_twitter_message->update( 'summary', $message );
								//$new_twitter_message->update('type','pending');
								$new_twitter_message->update( 'data', json_encode( $_POST ) );
								$new_twitter_message->update( 'user_id', get_current_user_id() );
								// do we send this one now? or schedule it later.
								$new_twitter_message->update( 'shub_status', _shub_MESSAGE_STATUS_PENDINGSEND );
								if ( isset( $_FILES['twitter_picture']['tmp_name'] ) && is_uploaded_file( $_FILES['twitter_picture']['tmp_name'] ) ) {
									$new_twitter_message->add_attachment( $_FILES['twitter_picture']['tmp_name'] );
								}
								$worked            = $new_twitter_message->send_queued( isset( $_POST['debug'] ) && $_POST['debug'] );
								$return['message'] = ob_get_clean();
								if ( $debug ) {
									// just return message
								} else if ( $worked ) {
									// success, redicet!
									//set_message( _l( 'Message sent and conversation archived.' ) );
									//$return['redirect'] = module_shub_twitter::link_open_message_view( $shub_twitter_id );
									$return['redirect'] = 'admin.php?page=support_hub_main';
									//$return['success'] = 1;
								} else {
									// failed, no debug, force debug and show error.
								}
							}
							echo json_encode( $return );
						}
					}
				}

				break;
			case 'modal':
				if(isset($_REQUEST['socialtwittermessageid']) && (int)$_REQUEST['socialtwittermessageid'] > 0) {
					$shub_twitter_message = new shub_twitter_message( false,  $_REQUEST['socialtwittermessageid'] );
					if($shub_twitter_message->get('shub_twitter_message_id') == $_REQUEST['socialtwittermessageid']){

						$shub_twitter_id = $shub_twitter_message->get('twitter_account')->get('shub_twitter_id');
						$shub_twitter_message_id = $shub_twitter_message->get('shub_twitter_message_id');
						include( trailingslashit( $support_hub_wp->dir ) . 'extensions/twitter/twitter_message.php');
					}

				}
				break;
			case 'set-answered':
				if (!headers_sent())header('Content-type: text/javascript');
				if(isset($_REQUEST['shub_twitter_message_id']) && (int)$_REQUEST['shub_twitter_message_id'] > 0){
					$shub_twitter_message = new shub_twitter_message(false, $_REQUEST['shub_twitter_message_id']);
					if($shub_twitter_message->get('shub_twitter_message_id') == $_REQUEST['shub_twitter_message_id']){
						$shub_twitter_message->update('shub_status',_shub_MESSAGE_STATUS_ANSWERED);
						?>
						jQuery('.socialtwitter_message_action[data-id=<?php echo (int)$shub_twitter_message->get('shub_twitter_message_id'); ?>]').parents('tr').first().hide();
						<?php
						// if this is a direct message, we also archive all other messages in it.
						if($shub_twitter_message->get('type') == _TWITTER_MESSAGE_TYPE_DIRECT){
							$from = preg_replace('#[^0-9]#','',$shub_twitter_message->get('twitter_from_id'));
							$to = preg_replace('#[^0-9]#','',$shub_twitter_message->get('twitter_to_id'));
							if($from && $to) {
								$sql      = "SELECT * FROM `" . _support_hub_DB_PREFIX . "shub_twitter_message` WHERE `shub_type` = " . _TWITTER_MESSAGE_TYPE_DIRECT . " AND `shub_status` = " . (int) _shub_MESSAGE_STATUS_UNANSWERED . " AND shub_twitter_id = " . (int) $shub_twitter_message->get('twitter_account')->get( 'shub_twitter_id' ) . " AND ( (`twitter_from_id` = '$from' AND `twitter_to_id` = '$to') OR (`twitter_from_id` = '$to' AND `twitter_to_id` = '$from') ) ";
								global $wpdb;
								$others = $wpdb->get_results($sql, ARRAY_A);
								if(count($others)){
									foreach($others as $other_message){
										$shub_twitter_message = new shub_twitter_message(false, $other_message['shub_twitter_message_id']);
										if($shub_twitter_message->get('shub_twitter_message_id') == $other_message['shub_twitter_message_id']) {
											$shub_twitter_message->update( 'shub_status', _shub_MESSAGE_STATUS_ANSWERED );
											?>
											jQuery('.socialtwitter_message_action[data-id=<?php echo (int) $shub_twitter_message->get( 'shub_twitter_message_id' ); ?>]').parents('tr').first().hide();
										<?php
										}
									}
								}
							}
						}
					}
				}
				break;
			case 'set-unanswered':
				if (!headers_sent())header('Content-type: text/javascript');
				if(isset($_REQUEST['shub_twitter_message_id']) && (int)$_REQUEST['shub_twitter_message_id'] > 0){
					$shub_twitter_message = new shub_twitter_message(false, $_REQUEST['shub_twitter_message_id']);
					if($shub_twitter_message->get('shub_twitter_message_id') == $_REQUEST['shub_twitter_message_id']){
						$shub_twitter_message->update('shub_status',_shub_MESSAGE_STATUS_UNANSWERED);
						?>
						jQuery('.socialtwitter_message_action[data-id=<?php echo (int)$shub_twitter_message->get('shub_twitter_message_id'); ?>]').parents('tr').first().hide();
						<?php
					}
				}
				break;
		}
		return false;
	}


	public function run_cron( $debug = false ){
		if($debug)echo "Starting Twitter Cron Job \n";
		$accounts = $this->get_accounts();
		foreach($accounts as $account){
			$shub_twitter_account = new shub_twitter_account( $account['shub_twitter_id'] );
			$shub_twitter_account->import_data($debug);
			$shub_twitter_account->run_cron($debug);
		}
		if($debug)echo "Finished Twitter Cron Job \n";
	}

	public function get_install_sql() {

		global $wpdb;

		$sql = <<< EOT


CREATE TABLE {$wpdb->prefix}shub_twitter (
  shub_twitter_id int(11) NOT NULL AUTO_INCREMENT,
  shub_user_id int(11) NOT NULL DEFAULT '0',
  twitter_id varchar(255) NOT NULL,
  twitter_name varchar(50) NOT NULL,
  twitter_data text NOT NULL,
  last_checked int(11) NOT NULL DEFAULT '0',
  user_key varchar(255) NOT NULL,
  user_secret varchar(255) NOT NULL,
  import_dm tinyint(1) NOT NULL DEFAULT '0',
  import_mentions tinyint(1) NOT NULL DEFAULT '0',
  import_tweets tinyint(1) NOT NULL DEFAULT '0',
  user_data text NOT NULL,
  searches text NOT NULL,
  account_name varchar(80) NOT NULL,
  PRIMARY KEY  shub_twitter_id (shub_twitter_id),
  KEY twitter_id (twitter_id)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;


CREATE TABLE {$wpdb->prefix}shub_twitter_message (
  shub_twitter_message_id int(11) NOT NULL AUTO_INCREMENT,
  shub_twitter_id int(11) NOT NULL,
  shub_message_id int(11) NOT NULL DEFAULT '0',
  reply_to_id int(11) NOT NULL DEFAULT '0',
  twitter_message_id varchar(255) NOT NULL,
  twitter_from_id varchar(80) NOT NULL,
  twitter_to_id varchar(80) NOT NULL,
  twitter_from_name varchar(80) NOT NULL,
  twitter_to_name varchar(80) NOT NULL,
  type tinyint(1) NOT NULL DEFAULT '0',
  status tinyint(1) NOT NULL DEFAULT '0',
  summary text NOT NULL,
  message_time int(11) NOT NULL DEFAULT '0',
  data text NOT NULL,
  user_id int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY  shub_twitter_message_id (shub_twitter_message_id),
  KEY shub_twitter_id (shub_twitter_id),
  KEY shub_message_id (shub_message_id),
  KEY message_time (message_time),
  KEY status (status),
  KEY type (type),
  KEY twitter_message_id (twitter_message_id),
  KEY twitter_from_id (twitter_from_id,twitter_to_id)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;


CREATE TABLE {$wpdb->prefix}shub_twitter_message_read (
  shub_twitter_message_id int(11) NOT NULL,
  read_time int(11) NOT NULL DEFAULT '0',
  user_id int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY  shub_twitter_message_id (shub_twitter_message_id,user_id)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;


CREATE TABLE {$wpdb->prefix}shub_twitter_message_link (
  shub_twitter_message_link_id int(11) NOT NULL AUTO_INCREMENT,
  shub_twitter_message_id int(11) NOT NULL DEFAULT '0',
  link varchar(255) NOT NULL,
  PRIMARY KEY  shub_twitter_message_link_id (shub_twitter_message_link_id),
  KEY shub_twitter_message_id (shub_twitter_message_id)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;

CREATE TABLE {$wpdb->prefix}shub_twitter_message_link_click (
  shub_twitter_message_link_click_id int(11) NOT NULL AUTO_INCREMENT,
  shub_twitter_message_link_id int(11) NOT NULL DEFAULT '0',
  click_time int(11) NOT NULL,
  ip_address varchar(20) NOT NULL,
  user_agent varchar(100) NOT NULL,
  url_referrer varchar(255) NOT NULL,
  PRIMARY KEY  shub_twitter_message_link_click_id (shub_twitter_message_link_click_id),
  KEY shub_twitter_message_link_id (shub_twitter_message_link_id)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;

EOT;
		return $sql;
	}

}

class shub_twitter_account{

	public function __construct($shub_twitter_id){
		$this->load($shub_twitter_id);
	}

	private $shub_twitter_id = false; // the current user id in our system.
    private $details = array();

	private function reset(){
		$this->shub_twitter_id = false;
		$this->details = array(
			'shub_twitter_id' => false,
			'shub_user_id' => 0,
			'twitter_id' => false,
			'twitter_name' => false,
			'twitter_data' => false,
			'last_checked' => false,
			'user_key' => false,
			'user_secret' => false,
			'import_dm' => false,
			'import_mentions' => false,
			'import_tweets' => false,
			'user_data' => false,
			'searches' => false,
			'account_name' => false,
		);
	    $this->pages = array();
		foreach($this->details as $field_id => $field_data){
			$this->{$field_id} = '';
		}
	}

	public function create_new(){
		$this->reset();
		$this->shub_twitter_id = shub_update_insert('shub_twitter_id',false,'shub_twitter',array());
		$this->load($this->shub_twitter_id);
	}

    public function load($shub_twitter_id = false){
	    if(!$shub_twitter_id)$shub_twitter_id = $this->shub_twitter_id;
	    $this->reset();
	    $this->shub_twitter_id = $shub_twitter_id;
        if($this->shub_twitter_id){
            $this->details = shub_get_single('shub_twitter','shub_twitter_id',$this->shub_twitter_id);
	        if(!is_array($this->details) || $this->details['shub_twitter_id'] != $this->shub_twitter_id){
		        $this->reset();
		        return false;
	        }
        }
        foreach($this->details as $key=>$val){
            $this->{$key} = $val;
        }
        return $this->shub_twitter_id;
    }

	public function get_messages($search=array()){
		$twitter = new shub_twitter();
		$search['shub_twitter_id'] = $this->shub_twitter_id;
		return $twitter->load_all_messages($search);
		//return shub_get_multiple('shub_twitter_message',$search,'shub_twitter_message_id','exact','message_time DESC');
	}

	public function get($field){
		return isset($this->{$field}) ? $this->{$field} : false;
	}
	public function get_picture(){
		$data = @json_decode($this->get('twitter_data'),true);
		return $data && isset($data['profile_image_url_https']) && !empty($data['profile_image_url_https']) ? $data['profile_image_url_https'] : false;
	}

	public function save_data($post_data){
		if(!$this->get('shub_twitter_id')){
			$this->create_new();
		}
		if(is_array($post_data)){
			foreach($this->details as $details_key => $details_val){
				if(isset($post_data['default_'.$details_key]) && !isset($post_data[$details_key])){
					$post_data[$details_key] = 0;
				}
				// hack to get unchecked checkboxes in:
				if(isset($post_data[$details_key])){
					$this->update($details_key,$post_data[$details_key]);
				}
			}
		}
		$this->load();
		return $this->get('shub_twitter_id');
	}
    public function update($field,$value){
	    // what fields to we allow? or not allow?
	    if(in_array($field,array('shub_twitter_id')))return;
        if($this->shub_twitter_id){
            $this->{$field} = $value;
            shub_update_insert('shub_twitter_id',$this->shub_twitter_id,'shub_twitter',array(
	            $field => $value,
            ));
        }
    }
	public function delete(){
		if($this->shub_twitter_id) {
			// delete all the messages for this twitter account.
			$messages = shub_get_multiple('shub_twitter_message',array(
				'shub_twitter_id' => $this->shub_twitter_id,
			),'shub_twitter_message_id');
			foreach($messages as $message){
				if($message && isset($message['shub_twitter_id']) && $message['shub_twitter_id'] == $this->shub_twitter_id){
					shub_delete_from_db( 'shub_twitter_message', 'shub_twitter_message_id', $message['shub_twitter_message_id'] );
					shub_delete_from_db( 'shub_twitter_message_link', 'shub_twitter_message_id', $message['shub_twitter_message_id'] );
					shub_delete_from_db( 'shub_twitter_message_read', 'shub_twitter_message_id', $message['shub_twitter_message_id'] );
				}
			}
			shub_delete_from_db( 'shub_twitter', 'shub_twitter_id', $this->shub_twitter_id );
		}
	}

	public function is_active(){
		// is there a 'last_checked' date?
		if(!$this->get('last_checked')){
			return false; // never checked this account, not active yet.
		}else{
			// do we have a token?
			if($this->get('user_secret')){
				// assume we have access, we remove the token if we get a twitter failure at any point.
				// todo: remove on failure
				return true;
			}
		}
		return false;
	}

	private $api = false;
	public function get_api(){
		if(!$this->api){

			require_once trailingslashit( __DIR__ ) . 'tmhOAuth.php';
			$shub_twitter = new shub_twitter();

            $this->api = new tmhOAuth( array(
			    // change the values below to ones for your application
			    'consumer_key'    => $shub_twitter->get('api_key'),
			    'consumer_secret' => $shub_twitter->get('api_secret'),
				'token'           => $this->get('user_key'),
				'secret'          => $this->get('user_secret'),
			    'user_agent'      => 'UCM Twitter 0.1',
			  ));

		}
		return $this->api;
	}

	public function run_cron($debug = false){
		// find all messages that haven't been sent yet.
		$messages = $this->get_messages(array(
			'shub_status' => _shub_MESSAGE_STATUS_PENDINGSEND,
		));
		$now = time();
		foreach($messages as $message){
			if(isset($message['message_time']) && $message['message_time'] < $now){
				$shub_twitter_message = new shub_twitter_message($this, $message['shub_twitter_message_id']);
				$shub_twitter_message->send_queued($debug);
			}
		}
	}
	public function import_data($debug = false){
		// pull in data from the twitter api using our user_secret and user_key and api keys.

		if($debug)echo "<br><br>\n\nConnecting to twitter <br>\n";

		$tmhOAuth = $this->get_api();

		$code = $tmhOAuth->user_request(array(
			'url' => $tmhOAuth->url('1.1/account/verify_credentials')
		));

		$latest_user_data = false;

		$latest_search_values = array(
			'dm_sent' => 1,
			'dm_received' => 1,
			'mentions' => 1,
			'timeline' => 1,
			'retweets' => 1,
		);
		$searches_data = @json_decode($this->get('searches'),true);
		foreach($latest_search_values as $key=>$val){
			//if(isset($searches_data[$key]))$latest_search_values[$key] = $searches_data[$key];
		}

		if ($code == 200){
			$data = json_decode($tmhOAuth->response['response'], true);
			if (isset($data['shub_status'])) {
				$this->update('twitter_data',json_encode($data));

				if($debug) echo " Hello @".htmlspecialchars($data['screen_name']).", importing information...<br>\n";

				if($this->get('import_dm')){
					$timestart = microtime(true);
					if($debug) echo " Importing sent direct messages (after tweet ".$latest_search_values['dm_sent'].")<br>\n";
					$code_sent = $tmhOAuth->user_request(array(
						'url' => $tmhOAuth->url('1.1/direct_messages/sent'),
						'params' => array(
					      'since_id' => $latest_search_values['dm_sent'],
					      'count' => 20,//module_config::c('shub_twitter_search_count',20),
					    ),
					));
					if ($code_sent == 200){
						$dms_sent = json_decode($tmhOAuth->response['response'], true);
						foreach($dms_sent as $dm){
							if(isset($dm['id_str'])){
								if($debug) echo ' - importing DM ID: '.$dm['id_str']."<br>\n";
								$latest_search_values['dm_sent'] = max($latest_search_values['dm_sent'], $dm['id_str']);
								// does this exist in the database?
								$exists = shub_get_single('shub_twitter_message',array('shub_twitter_id','twitter_message_id'),array($this->shub_twitter_id,$dm['id_str']));
								// update/insert based on this item.
								shub_update_insert('shub_twitter_message_id',$exists && isset($exists['shub_twitter_message_id']) ? $exists['shub_twitter_message_id'] : false, 'shub_twitter_message', array(
									'shub_twitter_id' => $this->shub_twitter_id,
									'twitter_message_id' => $dm['id_str'],
									'twitter_from_id' => isset($dm['sender_id_str']) ? $dm['sender_id_str'] : '',
									'twitter_from_name' => isset($dm['sender_screen_name']) ? $dm['sender_screen_name'] : '',
									'twitter_to_id' => isset($dm['recipient_id_str']) ? $dm['recipient_id_str'] : '',
									'twitter_to_name' => isset($dm['recipient_screen_name']) ? $dm['recipient_screen_name'] : '',
									'summary' => isset($dm['text']) ? $dm['text'] : '', //todo: swap out shortened urls in 'entities' array.
									'message_time' => isset($dm['created_at']) ? strtotime($dm['created_at']) : '',
									'data' => json_encode($dm),
									'type' => _TWITTER_MESSAGE_TYPE_DIRECT,
								));

								if(isset($dm['sender']) && isset($dm['sender_id_str']) && $dm['sender_id_str'] == $this->get('twitter_id')){
									$latest_user_data = $dm['sender'];
								}
							}
						}
					}else{
						echo 'Failed to access sent direct messages: '.$tmhOAuth->response['response'];
					}
					if($debug) echo " - took: ".(microtime(true)-$timestart) . " <br>\n";

					$timestart = microtime(true);
					if($debug) echo " Importing received direct messages (after tweet ".$latest_search_values['dm_received'].")<br>\n";
					$code_received = $tmhOAuth->user_request(array(
						'url' => $tmhOAuth->url('1.1/direct_messages'),
						'params' => array(
					      'since_id' => $latest_search_values['dm_received'],
					      'count' => 20,//module_config::c('shub_twitter_search_count',20),
					    ),
					));
					if ($code_received == 200){
						$dms_received = json_decode($tmhOAuth->response['response'], true);
						foreach($dms_received as $dm){
							if(isset($dm['id_str'])){
								if($debug) echo ' - importing DM ID: '.$dm['id_str']."<br>\n";
								$latest_search_values['dm_received'] = max($latest_search_values['dm_received'], $dm['id_str']);
								// does this exist in the database?
								$exists = shub_get_single('shub_twitter_message',array('shub_twitter_id','twitter_message_id'),array($this->shub_twitter_id,$dm['id_str']));
								// update/insert based on this item.
								shub_update_insert('shub_twitter_message_id',$exists && isset($exists['shub_twitter_message_id']) ? $exists['shub_twitter_message_id'] : false, 'shub_twitter_message', array(
									'shub_twitter_id' => $this->shub_twitter_id,
									'twitter_message_id' => $dm['id_str'],
									'twitter_from_id' => isset($dm['sender_id_str']) ? $dm['sender_id_str'] : '',
									'twitter_from_name' => isset($dm['sender_screen_name']) ? $dm['sender_screen_name'] : '',
									'twitter_to_id' => isset($dm['recipient_id_str']) ? $dm['recipient_id_str'] : '',
									'twitter_to_name' => isset($dm['recipient_screen_name']) ? $dm['recipient_screen_name'] : '',
									'summary' => isset($dm['text']) ? $dm['text'] : '', //todo: swap out shortened urls in 'entities' array.
									'message_time' => isset($dm['created_at']) ? strtotime($dm['created_at']) : '',
									'data' => json_encode($dm),
									'type' => _TWITTER_MESSAGE_TYPE_DIRECT,
								));
								if(isset($dm['recipient']) && isset($dm['recipient_id_str']) && $dm['recipient_id_str'] == $this->get('twitter_id')){
									$latest_user_data = $dm['recipient'];
								}
							}
						}
					}else{
						echo 'Failed to access received direct messages: '.$tmhOAuth->response['response'];
					}
					if($latest_user_data){
						$this->update('user_data',json_encode($latest_user_data));
					}
					if($debug) echo " - took: ".(microtime(true)-$timestart) . " <br>\n";

				}
				if($this->get('import_mentions')){
					$timestart = microtime(true);
					if($debug) echo " Importing mentions (after tweet ".$latest_search_values['mentions'].")<br>\n";
					$code_sent = $tmhOAuth->user_request(array(
						'url' => $tmhOAuth->url('1.1/statuses/mentions_timeline'),
						'params' => array(
					      'since_id' => $latest_search_values['mentions'],
					      'count' => 20,//module_config::c('shub_twitter_search_count',20),
					    ),
					));
					if ($code_sent == 200){
						$mentions = json_decode($tmhOAuth->response['response'], true);
						foreach($mentions as $mention){
							if(isset($mention['id_str'])){
								$latest_search_values['mentions'] = max($latest_search_values['mentions'], $mention['id_str']);
								$new_tweet = new shub_twitter_message($this, false);
								$new_tweet->load_by_twitter_id($mention['id_str'], $mention, _TWITTER_MESSAGE_TYPE_MENTION);
								if($debug) echo ' - importing mention ID: '.$mention['id_str']."<br>\n";
								/*// does this exist in the database?
								$exists = shub_get_single('shub_twitter_message',array('shub_twitter_id','twitter_message_id'),array($this->shub_twitter_id,$mention['id_str']));
								// update/insert based on this item.
								shub_update_insert('shub_twitter_message_id',$exists && isset($exists['shub_twitter_message_id']) ? $exists['shub_twitter_message_id'] : false, 'shub_twitter_message', array(
									'shub_twitter_id' => $this->shub_twitter_id,
									'twitter_message_id' => $mention['id_str'],
									'twitter_from_id' => isset($mention['user']['id_str']) ? $mention['user']['id_str'] : '',
									'twitter_from_name' => isset($mention['user']['screen_name']) ? $mention['user']['screen_name'] : '',
									'twitter_to_id' => '',
									'twitter_to_name' => '',
									'summary' => isset($mention['text']) ? $mention['text'] : '', //todo: swap out shortened urls in 'entities' array.
									'message_time' => isset($mention['created_at']) ? strtotime($mention['created_at']) : '',
									'data' => json_encode($mention),
									'type' => _TWITTER_MESSAGE_TYPE_MENTION,
								));*/
							}
						}
					}else{
						echo 'Failed to access mentions: '.$tmhOAuth->response['response'];
					}
					if($debug) echo " - took: ".(microtime(true)-$timestart) . " <br>\n";
				}
				if($this->get('import_tweets')){
					$timestart = microtime(true);
					if($debug) echo " Importing my own tweets (after tweet ".$latest_search_values['timeline'].")<br>\n";
					$code_sent = $tmhOAuth->user_request(array(
						'url' => $tmhOAuth->url('1.1/statuses/user_timeline'),
						'params' => array(
					      'since_id' => $latest_search_values['timeline'],
					      'count' => 20,// module_config::c('shub_twitter_search_count',20),
					      'screen_name' => $this->get('twitter_name'),
					    ),
					));
					if ($code_sent == 200){
						$tweets = json_decode($tmhOAuth->response['response'], true);
						foreach($tweets as $tweet){
							if(isset($tweet['id_str'])){
								$latest_search_values['timeline'] = max($latest_search_values['timeline'], $tweet['id_str']);

								$new_tweet = new shub_twitter_message($this, false);
								$new_tweet->load_by_twitter_id($tweet['id_str'], $tweet);
								if($debug) echo ' - importing tweet ID: '.$tweet['id_str']."<br>\n";

								/*// does this exist in the database (it really shouldn't because weshould be doing 'since' search correctly)
								$exists = shub_get_single('shub_twitter_message',array('shub_twitter_id','twitter_message_id'),array($this->shub_twitter_id,$tweet['id_str']));
								// update/insert based on this item.
								shub_update_insert('shub_twitter_message_id',$exists && isset($exists['shub_twitter_message_id']) ? $exists['shub_twitter_message_id'] : false, 'shub_twitter_message', array(
									'shub_twitter_id' => $this->shub_twitter_id,
									'reply_to_id' => $reply_to_id,
									'twitter_message_id' => $tweet['id_str'],
									'twitter_from_id' => isset($tweet['user']['id_str']) ? $tweet['user']['id_str'] : '',
									'twitter_from_name' => isset($tweet['user']['screen_name']) ? $tweet['user']['screen_name'] : '',
									'twitter_to_id' => isset($tweet['in_reply_to_user_id_str']) ? $tweet['in_reply_to_user_id_str'] : '',
									'twitter_to_name' => isset($tweet['in_reply_to_screen_name']) ? $tweet['in_reply_to_screen_name'] : '',
									'summary' => isset($tweet['text']) ? $tweet['text'] : '', //todo: swap out shortened urls in 'entities' array.
									'message_time' => isset($tweet['created_at']) ? strtotime($tweet['created_at']) : '',
									'data' => json_encode($tweet),
									'type' => $type,
								));*/
							}
						}
					}else{
						echo 'Failed to access mentions: '.$tmhOAuth->response['response'];
					}
					if($debug) echo " - took: ".(microtime(true)-$timestart) . " <br>\n";

					$timestart = microtime(true);
					if($debug) echo " Importing retweets of my tweets (after tweet ".$latest_search_values['retweets'].")<br>\n";
					$code_sent = $tmhOAuth->user_request(array(
						'url' => $tmhOAuth->url('1.1/statuses/retweets_of_me'),
						'params' => array(
					      'since_id' => $latest_search_values['retweets'],
					      'count' => 20, //module_config::c('shub_twitter_search_count',20),
					    ),
					));
					// only query the list of retweets for tweets newer than 2 weeks.
					$time_limit = strtotime('-2 weeks');
					if ($code_sent == 200){
						$tweets = json_decode($tmhOAuth->response['response'], true);
						foreach($tweets as $tweet){
							if(isset($tweet['id_str'])){
								$tweet_time = strtotime($tweet['created_at']);
								if($tweet_time < $time_limit){
									$latest_search_values['retweets'] = max($latest_search_values['retweets'], $tweet['id_str']);
								}

								$new_tweet = new shub_twitter_message($this, false);
								// refresh these so the retweet_count and favorite_count get stored in the database again.
								$new_tweet->load_by_twitter_id($tweet['id_str'], $tweet, false, $debug, true);
								if($debug) echo ' - importing tweet ID: '.$tweet['id_str']."<br>\n";

								/*// does this exist in the database (it really shouldn't because weshould be doing 'since' search correctly)
								$exists = shub_get_single('shub_twitter_message',array('shub_twitter_id','twitter_message_id'),array($this->shub_twitter_id,$tweet['id_str']));
								// update/insert based on this item.
								shub_update_insert('shub_twitter_message_id',$exists && isset($exists['shub_twitter_message_id']) ? $exists['shub_twitter_message_id'] : false, 'shub_twitter_message', array(
									'shub_twitter_id' => $this->shub_twitter_id,
									'reply_to_id' => $reply_to_id,
									'twitter_message_id' => $tweet['id_str'],
									'twitter_from_id' => isset($tweet['user']['id_str']) ? $tweet['user']['id_str'] : '',
									'twitter_from_name' => isset($tweet['user']['screen_name']) ? $tweet['user']['screen_name'] : '',
									'twitter_to_id' => isset($tweet['in_reply_to_user_id_str']) ? $tweet['in_reply_to_user_id_str'] : '',
									'twitter_to_name' => isset($tweet['in_reply_to_screen_name']) ? $tweet['in_reply_to_screen_name'] : '',
									'summary' => isset($tweet['text']) ? $tweet['text'] : '', //todo: swap out shortened urls in 'entities' array.
									'message_time' => isset($tweet['created_at']) ? strtotime($tweet['created_at']) : '',
									'data' => json_encode($tweet),
									'type' => $type,
								));*/
							}
						}
					}else{
						echo 'Failed to access mentions: '.$tmhOAuth->response['response'];
					}
					if($debug) echo " - took: ".(microtime(true)-$timestart) . " <br>\n";
				}

				foreach($latest_search_values as $key=>$val){
					$searches_data[$key] = $val;
				}
				$this->update('searches',json_encode($searches_data));

			}else{
				if($debug)echo 'Twitter failed to check status, please try connecting to twitter again from settings: '.$tmhOAuth->response['response'];
				return;
			}
		}else{
			if($debug)echo 'Twitter failed to check authentication, please try connecting to twitter again from settings: '.$tmhOAuth->response['response'];
			return;
		}

	}

	public function get_reply_tweet($tweet_status_id){
		$reply_to_id = false;
		if(empty($tweet_status_id))return $reply_to_id;

		$tweet = new shub_twitter_message($this, false);
		$tweet->load_by_twitter_id($tweet_status_id);
		return $tweet->get('shub_twitter_message_id');
		/*
		// check if it exists in the database already:
		$exists = shub_get_single('shub_twitter_message','twitter_message_id',$tweet_status_id);
		if(!$exists || $exists['twitter_message_id'] != $tweet_status_id){
			// grab from api and save in database.
			$tmhOAuth = $this->get_api();
			$twitter_code = $tmhOAuth->user_request(array(
				'url' => $tmhOAuth->url('1.1/statuses/show'),
				'params' => array(
			      'id' => $tweet_status_id,
			    ),
			));
			if ($twitter_code == 200) {
				$tweet = json_decode( $tmhOAuth->response['response'], true );
				//echo 'reply';print_r($tweet);exit;
				$new_reply_to_id = 0;
				if(isset($tweet['in_reply_to_status_id_str']) && !empty($tweet['in_reply_to_status_id_str'])){
					// import / find reply tweeet from db or api:
					$new_reply_to_id = $this->get_reply_tweet($tweet['in_reply_to_status_id_str']);
				}
				$type = _TWITTER_MESSAGE_TYPE_OTHERTWEET;
				if(isset($tweet['in_reply_to_user_id_str']) && $tweet['in_reply_to_user_id_str'] == $this->get('twitter_id')){
					$type = _TWITTER_MESSAGE_TYPE_MENTION;
				}else if(isset($tweet['user']['id_str']) && $tweet['user']['id_str'] == $this->get('twitter_id')){
					$type = _TWITTER_MESSAGE_TYPE_MYTWEET;
				}
				shub_update_insert('shub_twitter_message_id',false, 'shub_twitter_message', array(
					'shub_twitter_id' => $this->shub_twitter_id,
					'reply_to_id' => $new_reply_to_id,
					'twitter_message_id' => $tweet['id_str'],
					'twitter_from_id' => isset($tweet['user']['id_str']) ? $tweet['user']['id_str'] : '',
					'twitter_from_name' => isset($tweet['user']['screen_name']) ? $tweet['user']['screen_name'] : '',
					'twitter_to_id' => isset($tweet['in_reply_to_user_id_str']) ? $tweet['in_reply_to_user_id_str'] : '',
					'twitter_to_name' => isset($tweet['in_reply_to_screen_name']) ? $tweet['in_reply_to_screen_name'] : '',
					'summary' => isset($tweet['text']) ? $tweet['text'] : '', //todo: swap out shortened urls in 'entities' array.
					'message_time' => isset($tweet['created_at']) ? strtotime($tweet['created_at']) : '',
					'data' => json_encode($tweet),
					'type' =>$type,
				));
			}
		}else{
			$reply_to_id = $exists['shub_twitter_message_id'];
		}

		return $reply_to_id;*/

	}
	
	
	/**
	 * Links for wordpress
	 */
	public function link_connect(){
		return 'admin.php?page=support_hub_settings&tab=twitter&do_twitter_connect=1&shub_twitter_id='.$this->get('shub_twitter_id');
	}
	public function link_edit(){
		return 'admin.php?page=support_hub_settings&tab=twitter&shub_twitter_id='.$this->get('shub_twitter_id');
	}
	public function link_refresh(){
		return 'admin.php?page=support_hub_settings&tab=twitter&do_twitter_refresh=1&shub_twitter_id='.$this->get('shub_twitter_id');
	}
	public function link_new_message(){
		return 'admin.php?page=support_hub_main&shub_twitter_id='.$this->get('shub_twitter_id').'&shub_twitter_message_id=new';
	}

}




class shub_twitter_message{

	public function __construct($twitter_account = false, $shub_twitter_message_id = false){
		$this->twitter_account = $twitter_account;
		$this->load($shub_twitter_message_id);
	}

	/* @var $twitter_account shub_twitter_account */
	private $twitter_account = false;
	private $shub_twitter_message_id = false; // the current user id in our system.
    private $details = array();

	private function reset(){
		$this->shub_twitter_message_id = false;
		$this->details = array();
	}

	public function create_new(){
		$this->reset();
		$this->shub_twitter_message_id = shub_update_insert('shub_twitter_message_id',false,'shub_twitter_message',array());
		$this->load($this->shub_twitter_message_id);
	}

	public function load_by_twitter_id($twitter_id, $tweet=false, $type = false, $debug = false, $force = false){

		if(!$this->twitter_account || !$this->twitter_account->get('shub_twitter_id')){
			return false;
		}
		$this->shub_twitter_message_id = 0;
		$exists = shub_get_single('shub_twitter_message',array('shub_twitter_id','twitter_message_id'),array($this->twitter_account->get('shub_twitter_id'),$twitter_id));
		if($exists && $exists['twitter_message_id'] == $twitter_id){
			$this->load($exists['shub_twitter_message_id']);
			if($this->shub_twitter_message_id != $exists['shub_twitter_message_id']){
				$this->reset(); // shouldn't happen.
			}
			if(!$force && $this->shub_twitter_message_id == $exists['shub_twitter_message_id']){
				return $this->shub_twitter_message_id;
			}
		}
		if(!$tweet || $force){
			$tmhOAuth = $this->twitter_account->get_api();
			if($type == _TWITTER_MESSAGE_TYPE_DIRECT){
				$twitter_code = $tmhOAuth->user_request( array(
					'url'    => $tmhOAuth->url( '1.1/direct_messages/show' ),
					'params' => array(
						'id' => $twitter_id,
					),
				) );
			}else {
				$twitter_code = $tmhOAuth->user_request( array(
					'url'    => $tmhOAuth->url( '1.1/statuses/show' ),
					'params' => array(
						'id' => $twitter_id,
					),
				) );
			}
			if ($twitter_code == 200) {
				$tweet = json_decode( $tmhOAuth->response['response'], true );
			}
		}
		if($tweet){
			//echo 'reply';print_r($tweet);exit;
			$new_reply_to_id = 0;
			if(isset($tweet['in_reply_to_status_id_str']) && !empty($tweet['in_reply_to_status_id_str'])){
				// import / find reply tweeet from db or api:
				$new_reply_to_id = $this->twitter_account->get_reply_tweet($tweet['in_reply_to_status_id_str']);
			}
			if($type === false){
				// type should be a MYTWEET or a MYRETWEET if it's on the user_timeline:
				if(isset($tweet['retweeted_status']['id_str']) && !empty($tweet['retweeted_status']['id_str'])){
					if(isset($tweet['user']['id_str']) && $tweet['user']['id_str'] == $this->twitter_account->get('twitter_id')){
						$type = _TWITTER_MESSAGE_TYPE_MYRETWEET;
					}else{
						$type = _TWITTER_MESSAGE_TYPE_OTHERRETWEET;
					}
					$new_reply_to_id = $this->twitter_account->get_reply_tweet($tweet['retweeted_status']['id_str']);
				}else if(isset($tweet['in_reply_to_user_id_str']) && $tweet['in_reply_to_user_id_str'] == $this->twitter_account->get('twitter_id')){
					$type = _TWITTER_MESSAGE_TYPE_MENTION;
				}else if(isset($tweet['user']['id_str']) && $tweet['user']['id_str'] == $this->twitter_account->get('twitter_id')){
					$type = _TWITTER_MESSAGE_TYPE_MYTWEET;
				}else{
					$type = _TWITTER_MESSAGE_TYPE_OTHERTWEET;
				}
			}
			// todo: unarchive tweet if the retweet or fav action happens
			$this->shub_twitter_message_id = shub_update_insert('shub_twitter_message_id',$this->shub_twitter_message_id, 'shub_twitter_message', array(
				'shub_twitter_id' => $this->twitter_account->get('shub_twitter_id'),
				'reply_to_id' => $new_reply_to_id,
				'twitter_message_id' => $tweet['id_str'],
				'twitter_from_id' => isset($tweet['user']['id_str']) ? $tweet['user']['id_str'] : (isset($tweet['sender_id_str']) ? $tweet['sender_id_str'] : ''),
				'twitter_from_name' => isset($tweet['user']['screen_name']) ? $tweet['user']['screen_name'] : (isset($tweet['sender_screen_name']) ? $tweet['sender_screen_name'] : ''),
				'twitter_to_id' => isset($tweet['in_reply_to_user_id_str']) ? $tweet['in_reply_to_user_id_str'] : (isset($tweet['recipient_id_str']) ? $tweet['recipient_id_str'] : ''),
				'twitter_to_name' => isset($tweet['in_reply_to_screen_name']) ? $tweet['in_reply_to_screen_name'] : (isset($tweet['recipient_screen_name']) ? $tweet['recipient_screen_name'] : ''),
				'summary' => isset($tweet['text']) ? $tweet['text'] : '', //todo: swap out shortened urls in 'entities' array.
				'message_time' => isset($tweet['created_at']) ? strtotime($tweet['created_at']) : '',
				'data' => json_encode($tweet),
				'type' =>$type,
			));
			$this->load($this->shub_twitter_message_id);
		}



		return $this->shub_twitter_message_id;
	}

    public function load($shub_twitter_message_id = false){
	    if(!$shub_twitter_message_id)$shub_twitter_message_id = $this->shub_twitter_message_id;
	    $this->reset();
	    $this->shub_twitter_message_id = $shub_twitter_message_id;
        if($this->shub_twitter_message_id){
            $this->details = shub_get_single('shub_twitter_message','shub_twitter_message_id',$this->shub_twitter_message_id);
	        if(!is_array($this->details) || !isset($this->details['shub_twitter_message_id']) || $this->details['shub_twitter_message_id'] != $this->shub_twitter_message_id){
		        $this->reset();
		        return false;
	        }
        }
        foreach($this->details as $key=>$val){
            $this->{$key} = $val;
        }
	    if(!$this->twitter_account && $this->get('shub_twitter_id')){
		    $this->twitter_account = new shub_twitter_account($this->get('shub_twitter_id'));
	    }
        return $this->shub_twitter_message_id;
    }

	public function get($field){
		return isset($this->{$field}) ? $this->{$field} : false;
	}


    public function update($field,$value){
	    // what fields to we allow? or not allow?
	    if(in_array($field,array('shub_twitter_message_id')))return;
        if($this->shub_twitter_message_id){
            $this->{$field} = $value;
            shub_update_insert('shub_twitter_message_id',$this->shub_twitter_message_id,'shub_twitter_message',array(
	            $field => $value,
            ));
        }
    }

	public function parse_links(){
		if(!$this->get('shub_twitter_message_id'))return;
		// strip out any links in the tweet and write them to the twitter_message_link table.
		$url_clickable = '~
		            ([\\s(<.,;:!?])                                        # 1: Leading whitespace, or punctuation
		            (                                                      # 2: URL
		                    [\\w]{1,20}+://                                # Scheme and hier-part prefix
		                    (?=\S{1,2000}\s)                               # Limit to URLs less than about 2000 characters long
		                    [\\w\\x80-\\xff#%\\~/@\\[\\]*(+=&$-]*+         # Non-punctuation URL character
		                    (?:                                            # Unroll the Loop: Only allow puctuation URL character if followed by a non-punctuation URL character
		                            [\'.,;:!?)]                            # Punctuation URL character
		                            [\\w\\x80-\\xff#%\\~/@\\[\\]*(+=&$-]++ # Non-punctuation URL character
		                    )*
		            )
		            (\)?)                                                  # 3: Trailing closing parenthesis (for parethesis balancing post processing)
		    ~xS'; // The regex is a non-anchored pattern and does not have a single fixed starting character.
		          // Tell PCRE to spend more time optimizing since, when used on a page load, it will probably be used several times.
		$summary = ' ' . $this->get('summary') . ' ';
		if(strlen($summary) && preg_match_all($url_clickable,$summary,$matches)){
			foreach($matches[2] as $id => $url){
				$url = trim($url);
				if(strlen($url)) {
					// wack this url into the database and replace it with our rewritten url.
					$shub_twitter_message_link_id = shub_update_insert( 'shub_twitter_message_link_id', false, 'shub_twitter_message_link', array(
						'shub_twitter_message_id' => $this->get('shub_twitter_message_id'),
						'link' => $url,
					) );
					if($shub_twitter_message_link_id) {
						$new_link = trailingslashit( get_site_url() );
						$new_link .= strpos( $new_link, '?' ) === false ? '?' : '&';
						$new_link .= _support_hub_TWITTER_LINK_REWRITE_PREFIX . '=' . $shub_twitter_message_link_id;
						// basic hash to stop brute force.
						if(defined('AUTH_KEY')){
							$new_link .= ':'.substr(md5(AUTH_KEY.' twitter link '.$shub_twitter_message_link_id),1,5);
						}
						$newsummary = trim(preg_replace('#'.preg_quote($url,'#').'#',$new_link,$summary, 1));
						if(strlen($newsummary)){// just incase.
							$summary = $newsummary;
						}
					}
				}
			}
		}
		$this->update('summary',$summary);
	}

	public function delete(){
		if($this->shub_twitter_message_id) {
			shub_delete_from_db( 'shub_twitter_message', 'shub_twitter_message_id', $this->shub_twitter_message_id );
		}
	}

	public function mark_as_read(){
		if($this->shub_twitter_message_id && get_current_user_id()){
			$sql = "REPLACE INTO `"._support_hub_DB_PREFIX."shub_twitter_message_read` SET `shub_twitter_message_id` = ".(int)$this->shub_twitter_message_id.", `user_id` = ".(int)get_current_user_id().", read_time = ".(int)time();
			shub_query($sql);
		}
	}

	public function get_summary() {
		// who was the last person to contribute to this post? show their details here instead of the 'summary' box maybe?
		$summary = $this->get( 'summary' );
	    if(empty($summary))$summary = __('N/A','support_hub');
	    $return = htmlspecialchars( strlen( $summary ) > 80 ? substr( $summary, 0, 80 ) . '...' : $summary );
		$data = @json_decode($this->get('data'),true);
		//print_r($data);
		if($data && ((isset($data['retweet_count']) && $data['retweet_count'] > 0) || (isset($data['favorite_count']) && $data['favorite_count'] > 0))){
			$return .= '<br/>( ';
			if(isset($data['retweet_count']) && $data['retweet_count'] > 0){
				$return .= sprintf(__('Retweets: %s', 'support_hub'),$data['retweet_count']);
			}
			$return .=  ' ';
			if(isset($data['favorite_count']) && $data['favorite_count'] > 0){
				$return .= sprintf(__('Favorites: %s', 'support_hub'),$data['favorite_count']);
			}
			$return .=  ' )';
		}
		return $return;
	}

	private $can_reply = false;
	public function output_block($level){

		if(!$this->get('shub_twitter_message_id') || $level < -3)return;

		$twitter_data = @json_decode($this->get('data'),true);

		// any previous messages?
		if($level <= 0){
			if($this->get('reply_to_id')){
				// this tweet is a reply to a previous tweet!
				?>
				<div class="twitter_previous_messages">
					<?php
					$reply_message = new shub_twitter_message($this->twitter_account, $this->get('reply_to_id'));
					$reply_message->output_block($level-1);
					?>
				</div>
				<?php
			}else if($this->get('type') == _TWITTER_MESSAGE_TYPE_DIRECT){
				// find previous message(s)
				$from = preg_replace('#[^0-9]#','',$this->get('twitter_from_id'));
				$to = preg_replace('#[^0-9]#','',$this->get('twitter_to_id'));
				if($from && $to){
					$sql = "SELECT * FROM `"._support_hub_DB_PREFIX."shub_twitter_message` WHERE `shub_type` = "._TWITTER_MESSAGE_TYPE_DIRECT." AND message_time <= ".(int)$this->get('message_time')." AND shub_twitter_message_id != ".(int)$this->shub_twitter_message_id." AND shub_twitter_id = ".(int)$this->twitter_account->get('shub_twitter_id')." AND ( (`twitter_from_id` = '$from' AND `twitter_to_id` = '$to') OR (`twitter_from_id` = '$to' AND `twitter_to_id` = '$from') ) ORDER BY `message_time` DESC LIMIT 1";
					$previous = shub_qa1($sql);
					if($previous && $previous['shub_twitter_message_id']){
						?>
						<div class="twitter_previous_messages twitter_direct">
							<?php
							$reply_message = new shub_twitter_message($this->twitter_account, $previous['shub_twitter_message_id']);
							$reply_message->output_block($level-1);
							?>
						</div>
						<?php
					}
				}
			}
		}

		$message_from = isset($twitter_data['user']) ? $twitter_data['user'] : (isset($twitter_data['sender']) ? $twitter_data['sender'] : false);

		if($this->get('summary')){
			if($message_from && $this->get('type') != _TWITTER_MESSAGE_TYPE_DIRECT){
				$message_from['tweet_id'] = isset($twitter_data['id_str']) ? $twitter_data['id_str'] : false;
			}
			//echo '<pre>'; print_r($twitter_data); echo '</pre>';
			?>
			<div class="twitter_comment <?php echo $level != 0 ? ' twitter_comment_clickable' : 'twitter_comment_current';?>" data-id="<?php echo $this->shub_twitter_message_id;?>" data-link="<?php echo $this->link_open();?>" data-title="<?php echo __('Tweet','support_hub');?>" data-socialtwittermessageid="<?php echo (int)$this->get('shub_twitter_message_id');?>">
				<div class="twitter_comment_picture">
					<?php 
					if(isset($twitter_data['user']['id_str'])){
						$pic = array(
							'screen_name' => isset($twitter_data['user']['screen_name']) ? $twitter_data['user']['screen_name'] : '',
							'image' => isset($twitter_data['user']['profile_image_url_https']) ? $twitter_data['user']['profile_image_url_https'] : '',
						);
					}else if(isset($twitter_data['sender']['id_str'])){
						$pic = array(
							'screen_name' => isset($twitter_data['sender']['screen_name']) ? $twitter_data['sender']['screen_name'] : '',
							'image' => isset($twitter_data['sender']['profile_image_url_https']) ? $twitter_data['sender']['profile_image_url_https'] : '',
						);
					}else{
						$pic = false;
					}
					if($pic){
						?>
						<img src="<?php echo $pic['image'];?>">
						<?php
					}
					?>
				</div>
				<div class="twitter_comment_header">
					<?php _e('From:'); echo ' '; echo $message_from ? shub_twitter::format_person($message_from) : 'N/A'; ?>
					<span><?php $time = strtotime($this->get('message_time'));
					echo $time ? ' @ ' . shub_print_date($time,true) : '';

					if ( $this->get('user_id') ) {
						$user_info = get_userdata($this->get('user_id'));
						echo ' (sent by ' . htmlspecialchars($user_info->display_name) . ')';
						//echo ' (sent by ' . module_user::link_open( $this->get('user_id'), true ) . ')';
					}
					?>
					</span>
				</div>
				<div class="twitter_comment_body">
					<?php if(isset($twitter_data['entities']['media']) && is_array($twitter_data['entities']['media'])){
						foreach($twitter_data['entities']['media'] as $media) {
							if ( $media['type'] == 'photo' ) {
								?>
								<div class="twitter_picture">
									<?php if (isset( $media['url'] ) && $media['url']){ ?> <a
										href="<?php echo htmlspecialchars( $media['url'] ); ?>"
										target="_blank"> <?php } ?>
										<img src="<?php echo htmlspecialchars( $media['media_url_https'] ); ?>">
										<?php if (isset( $media['url'] ) && $media['url']){ ?> </a> <?php } ?>
								</div>
							<?php
							}
						}
					} ?>
					<div>
						<?php echo shub_forum_text($this->get('summary'));?>
					</div>
					<div class="twitter_comment_stats">
						<?php
						$data = @json_decode($this->get('data'),true);
						//print_r($data);
						if($data && ((isset($data['retweet_count']) && $data['retweet_count'] > 0) || (isset($data['favorite_count']) && $data['favorite_count'] > 0))){
							if(isset($data['retweet_count']) && $data['retweet_count'] > 0){
								echo sprintf(__('Retweets: %s', 'support_hub'),$data['retweet_count']);
							}
							echo ' ';
							if(isset($data['favorite_count']) && $data['favorite_count'] > 0){
								echo sprintf(__('Favorites: %s', 'support_hub'),$data['favorite_count']);
							}
						} ?>
					</div>
				</div>
				<div class="twitter_comment_actions">
					<?php if($this->can_reply){ ?>
						<a href="#" class="twitter_reply_button"><?php _e('Reply');?></a>
					<?php } ?>
				</div>
			</div>
		<?php } ?>
		<?php if($level == 0){ ?>
			<div class="twitter_comment_replies">
			<?php
			//if(strpos($twitter_data['message'],'picture')){
				//echo '<pre>'; print_r($twitter_data); echo '</pre>';
			//}

			if($this->can_reply){
				$this->reply_box($level, $message_from);
			}
			?>
			</div>
		<?php
		}

		if($level >= 0){
			// any follow up messages?
			if($this->get('type') == _TWITTER_MESSAGE_TYPE_DIRECT) {
				$from = preg_replace('#[^0-9]#','',$this->get('twitter_from_id'));
				$to = preg_replace('#[^0-9]#','',$this->get('twitter_to_id'));
				if($from && $to){
					$sql = "SELECT * FROM `"._support_hub_DB_PREFIX."shub_twitter_message` WHERE `shub_type` = "._TWITTER_MESSAGE_TYPE_DIRECT." AND message_time >= ".(int)$this->get('message_time')." AND shub_twitter_message_id != ".(int)$this->shub_twitter_message_id." AND shub_twitter_id = ".(int)$this->twitter_account->get('shub_twitter_id')." AND ( (`twitter_from_id` = '$from' AND `twitter_to_id` = '$to') OR (`twitter_from_id` = '$to' AND `twitter_to_id` = '$from') ) ORDER BY `message_time` ASC LIMIT 1";
					$next = shub_qa1($sql);
					if($next && $next['shub_twitter_message_id']){
						?>
						<div class="twitter_next_messages twitter_direct">
							<?php
							$reply_message = new shub_twitter_message($this->twitter_account, $next['shub_twitter_message_id']);
							$reply_message->output_block($level + 1);
							?>
						</div>
						<?php
					}
				}
			}else{
				$next = shub_get_multiple( 'shub_twitter_message', array(
						'shub_twitter_id' => $this->twitter_account->get( 'shub_twitter_id' ),
						'reply_to_id' => $this->shub_twitter_message_id,
					), 'shub_twitter_message_id' );
				if ( $next ) {
					foreach($next as $n) {
						// this tweet is a reply to a previous tweet!
						if($n['shub_twitter_message_id']) {
							?>
							<div class="twitter_next_messages">
								<?php
								$reply_message = new shub_twitter_message( $this->twitter_account, $n['shub_twitter_message_id'] );
								$reply_message->output_block( $level + 1 );
								?>
							</div>
							<?php
						}
					}
				}
			}
		}

	}

	public function full_message_output($can_reply = false){
		$this->can_reply = $can_reply;
		// used in shub_twitter_list.php to display the full message and its comments


		$this->output_block(0);
	}

	public function reply_box($level=0, $message_from = array()){
		if($this->twitter_account &&  $this->shub_twitter_message_id && (int)$this->get('shub_twitter_id') > 0 && $this->get('shub_twitter_id') == $this->twitter_account->get('shub_twitter_id')) {
			// who are we replying to?
			$account_data = @json_decode($this->twitter_account->get('twitter_data'),true);
			?>
			<div class="twitter_comment twitter_comment_reply_box twitter_comment_reply_box_level<?php echo $level;?>">
				<div class="twitter_comment_picture">
					<?php if($account_data && isset($account_data['id_str'])){
						$pic = array(
							'screen_name' => isset($account_data['screen_name']) ? $account_data['screen_name'] : '',
							'image' => isset($account_data['profile_image_url_https']) ? $account_data['profile_image_url_https'] : '',
						);
					}else{
						$pic = false;
					}
					if($pic){
						?>
						<img src="<?php echo $pic['image'];?>">
						<?php
					} ?>
				</div>
				<div class="twitter_comment_header">
					<?php echo shub_twitter::format_person( $account_data ); ?>
				</div>
				<div class="twitter_comment_reply">
					<textarea placeholder="Write a reply..." class="twitter_compose_message"><?php
						if($message_from && isset($message_from['screen_name']) && $this->get('type') != _TWITTER_MESSAGE_TYPE_DIRECT){
							echo '@'.htmlspecialchars($message_from['screen_name']).' ';
						}
						?></textarea>
					<button data-id="<?php echo (int)$this->shub_twitter_message_id;?>" data-account-id="<?php echo (int)$this->get('shub_twitter_id');?>"><?php _e('Send');?></button>
					<div style="clear:both;">
				    <span class="twitter_characters_remain"><span>140</span> characters remaining.</span>
					<br/>
					(debug) <input type="checkbox" name="debug" class="reply-debug" value="1">
						</div>
				</div>
				<div class="twitter_comment_actions"></div>
			</div>
		<?php
		}else{
			?>
			<div class="twitter_comment twitter_comment_reply_box">
				(incorrect settings, please report this bug)
			</div>
			<?php
		}
	}

	public function get_link() {
		return '//twitter.com/'.htmlspecialchars($this->twitter_account->get('twitter_name')).'/status/'.$this->get('twitter_message_id');
	}

	private $attachment_name = '';
	public function add_attachment($local_filename){
		if(is_file($local_filename)){
			$this->attachment_name = $local_filename;
		}
	}
	public function send_queued($debug = false){
		if($this->twitter_account && $this->shub_twitter_message_id) {
			// send this message out to twitter.
			// this is run when user is composing a new message from the UI,
			if ( $this->get( 'shub_status' ) == _shub_MESSAGE_STATUS_SENDING )
				return; // dont double up on cron.
			$this->update( 'shub_status', _shub_MESSAGE_STATUS_SENDING );

			$user_post_data = @json_decode($this->get('data'),true);

			if($debug)echo "Sending a new message to twitter account ID: ".$this->twitter_account->get('twitter_name')." <br>\n";
			$result = false;

			$tmhOAuth = $this->twitter_account->get_api();


			$post_data = array(
				'shub_status' => $this->get('summary'),
			);
			$reply_message = false;
			if($this->get('reply_to_id')){
				$reply_message = new shub_twitter_message(false, $this->get('reply_to_id'));
				if($reply_message && $reply_message->get('twitter_message_id')){
					$post_data['in_reply_to_status_id'] = $reply_message->get('twitter_message_id');
				}else{
					$reply_message = false;
				}
			}

			// todo: message or link are required.
			$now = time();
			$send_time = $this->get('message_time');

			if($reply_message && $reply_message->get('type') == _TWITTER_MESSAGE_TYPE_DIRECT){
				// send a direct reply, not a public tweet

				// a hack for DM, we dont reply to the most recent message (because we could reply to ourselves) we reply to the most recent message with a different author.
				$send_dm_to_id = $reply_message->get('twitter_from_id');
				if(!$send_dm_to_id){
					echo "Failed, no DM reply ID ";
					$this->delete();
					return false;
				}
				$our_twitter_id = preg_replace('#[^0-9]#','',$this->twitter_account->get('twitter_id'));
				if($our_twitter_id && $send_dm_to_id == $our_twitter_id){
					// dont reply to ourselves!
					$to = preg_replace('#[^0-9]#','',$reply_message->get('twitter_to_id'));
					if($our_twitter_id != $to){
						$send_dm_to_id = $to;
					}
				}

				if($debug){
					echo "Posting to 1.1/direct_messagse/new to user id ".$send_dm_to_id."   <br>";
				}

				$this->update('type',_TWITTER_MESSAGE_TYPE_DIRECT);

				$twitter_code = $tmhOAuth->user_request(array(
					'method' => 'POST',
					'url' => $tmhOAuth->url('1.1/direct_messages/new'),
					'params' => array(
						'user_id' => $send_dm_to_id,
						'text' => $this->get('summary'),
					),
				));
				if ($twitter_code == 200) {
					$result = json_decode( $tmhOAuth->response['response'], true );
				}else{
					$result = false;
				}

			}else{


                //if($post_data['in_reply_to_status_id']){
					$this->update('type',_TWITTER_MESSAGE_TYPE_MYTWEET);
				//}
				if(isset($user_post_data['twitter_post_type']) && $user_post_data['twitter_post_type'] == 'picture' && !empty($this->attachment_name) && is_file($this->attachment_name)){
					// we're posting a photo! change the post source from /feed to /photos

					//$post_data['source'] = new CURLFile($this->attachment_name, 'image/jpg'); //'@'.$this->attachment_name;
					$post_data['media[]'] = file_get_contents($this->attachment_name);

					if($debug){
						echo "Posting to 1.1/statuses/update_with_media with data: <br>";
						print_r($post_data);
					}

					$twitter_code = $tmhOAuth->user_request(array(
						'method' => 'POST',
						'multipart' => true,
						'url' => $tmhOAuth->url('1.1/statuses/update_with_media'),
						'params' => $post_data,
					));
					if ($twitter_code == 200) {
						$result = json_decode( $tmhOAuth->response['response'], true );
					}else{
						$result = false;
					}

				}else{

					if($debug){
						echo "Posting to 1.1/statuses/update with data: <br>";
						print_r($post_data);
					}

					$twitter_code = $tmhOAuth->user_request(array(
						'method' => 'POST',
						'url' => $tmhOAuth->url('1.1/statuses/update'),
						'params' => $post_data,
					));
					if ($twitter_code == 200) {
						$result = json_decode( $tmhOAuth->response['response'], true );
					}else{
						$result = false;
					}

				}
			}
			if($debug)echo "API Post Result: <br>\n".var_export($result,true)." <br>\n";
			if($result && isset($result['id_str'])){
				$this->update('twitter_message_id',$result['id_str']);
				// reload this message and comments from the graph api.
				$this->load_by_twitter_id($this->get('twitter_message_id'),false, $this->get('type') == _TWITTER_MESSAGE_TYPE_DIRECT ? _TWITTER_MESSAGE_TYPE_DIRECT : false, $debug, true);
			}else{
				echo 'Failed to send message. Error was: '.var_export($tmhOAuth->response['response'],true);
				// remove from database.
				$this->delete();
				return false;
			}

			// successfully sent, mark is as answered.
			$this->update( 'shub_status', _shub_MESSAGE_STATUS_ANSWERED );
			if($reply_message){
				//archive the message we replied to as well
				$reply_message->update( 'shub_status', _shub_MESSAGE_STATUS_ANSWERED );
			}
			return true;
		}
		return false;
	}


	public function get_type_pretty() {
		$type = $this->get('type');
		switch($type){
			case _TWITTER_MESSAGE_TYPE_MENTION:
				return 'Mention';
				break;
			case _TWITTER_MESSAGE_TYPE_OTHERTWEET:
				return 'Tweet';
				break;
			case _TWITTER_MESSAGE_TYPE_MYTWEET:
				return 'My Tweet';
				break;
			case _TWITTER_MESSAGE_TYPE_MYRETWEET:
				return 'My Retweet';
				break;
			case _TWITTER_MESSAGE_TYPE_OTHERRETWEET:
				return 'Retweet';
				break;
			case _TWITTER_MESSAGE_TYPE_DIRECT:
				return 'Direct';
				break;
			default:
				return ucwords($type);
		}
	}

	public function get_from() {
		if($this->shub_twitter_message_id){
			$from = array();
			$data = @json_decode($this->get('data'),true);
			if(isset($data['user']['id_str'])){
				$from[$data['user']['id_str']] = array(
					'screen_name' => isset($data['user']['screen_name']) ? $data['user']['screen_name'] : '',
					'image' => isset($data['user']['profile_image_url_https']) ? $data['user']['profile_image_url_https'] : '',
				);
			}
			if(isset($data['sender']['id_str'])){
				$from[$data['sender']['id_str']] = array(
					'screen_name' => isset($data['sender']['screen_name']) ? $data['sender']['screen_name'] : '',
					'image' => isset($data['sender']['profile_image_url_https']) ? $data['sender']['profile_image_url_https'] : '',
				);
			}
			if(isset($data['recipient']['id_str'])){
				$from[$data['recipient']['id_str']] = array(
					'screen_name' => isset($data['recipient']['screen_name']) ? $data['recipient']['screen_name'] : '',
					'image' => isset($data['recipient']['profile_image_url_https']) ? $data['recipient']['profile_image_url_https'] : '',
				);
			}
			return $from;
		}
		return array();
	}

	public function link_open(){
		return 'admin.php?page=support_hub_main&shub_twitter_id='.$this->twitter_account->get('shub_twitter_id').'&shub_twitter_message_id='.$this->shub_twitter_message_id;
	}


}