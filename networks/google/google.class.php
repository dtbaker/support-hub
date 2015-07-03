<?php

define('_support_hub_GOOGLE_LINK_REWRITE_PREFIX','ssglnk');

class shub_google extends SupportHub_network {

	private $accounts = array();

	public function init(){
		if(isset($_GET[_support_hub_GOOGLE_LINK_REWRITE_PREFIX]) && strlen($_GET[_support_hub_GOOGLE_LINK_REWRITE_PREFIX]) > 0){
			// check hash
			$bits = explode(':',$_GET[_support_hub_GOOGLE_LINK_REWRITE_PREFIX]);
			if(defined('AUTH_KEY') && isset($bits[1])){
				$shub_google_message_link_id = (int)$bits[0];
				if($shub_google_message_link_id > 0){
					$correct_hash = substr(md5(AUTH_KEY.' google link '.$shub_google_message_link_id),1,5);
					if($correct_hash == $bits[1]){
						// link worked! log a visit and redirect.
						$link = shub_get_single('shub_google_message_link','shub_google_message_link_id',$shub_google_message_link_id);
						if($link){
							if(!preg_match('#^http#',$link['link'])){
								$link['link'] = 'http://'.trim($link['link']);
							}
							shub_update_insert('shub_google_message_link_click_id',false,'shub_google_message_link_click',array(
								'shub_google_message_link_id' => $shub_google_message_link_id,
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
		return '<img src="'.plugins_url('networks/google/google-logo.png', _DTBAKER_SUPPORT_HUB_CORE_FILE_).'" class="shub_friendly_icon">';
	}

	public function init_menu(){
	}

	public function page_assets($from_master=false){
		if(!$from_master)SupportHub::getInstance()->inbox_assets();

		wp_register_style( 'support-hub-google-css', plugins_url('networks/google/shub_google.css',_DTBAKER_SUPPORT_HUB_CORE_FILE_) , array(), '1.0.0' );
		wp_enqueue_style( 'support-hub-google-css' );
		wp_register_script( 'support-hub-google', plugins_url('networks/google/shub_google.js',_DTBAKER_SUPPORT_HUB_CORE_FILE_), array( 'jquery' ), '1.0.0' );
		wp_enqueue_script( 'support-hub-google' );

	}

	public function settings_page(){
		include( dirname(__FILE__) . '/google_settings.php');
	}


	private function reset() {
		$this->accounts = array();
	}

	public function compose_to(){
		$accounts = $this->get_accounts();
	    if(!count($accounts)){
		    _e('No accounts configured', 'support_hub');
	    }
		foreach ( $accounts as $account ) {
			$google_account = new shub_google_account( $account['shub_google_id'] );
		    echo '<div class="google_compose_account_select">' .
				    '<input type="checkbox" name="compose_google_id['.$google_account->get('shub_google_id').']" value="1"> ' .
				    ($google_account->get_picture() ? '<img src="'.$google_account->get_picture().'">' : '' ).
				     '<span>' . htmlspecialchars( $google_account->get('google_name') ) . '</span>' .
				    '</div>'
			    ;
	    }
	}

	public function compose_message($defaults){
		?>
		<textarea name="google_message" rows="6" cols="50" id="google_compose_message"><?php echo isset($defaults['google_message']) ? esc_attr($defaults['google_message']) : '';?></textarea>
		<?php
	}
	public function compose_type($defaults){
		?>
		<input type="radio" name="google_post_type" id="google_post_type_normal" value="normal" checked>
		<label for="google_post_type_normal">Normal Post</label>
		<?php
	}


	public function get_accounts() {
		$this->accounts = shub_get_multiple( 'shub_google', array(), 'shub_google_id' );
		return $this->accounts;
	}

	public function get($key){
		return get_option('support_hub_google_'.$key);
	}
	public function update($key,$value){
		update_option('support_hub_google_'.$key, $value);
	}

	public static function format_person($data){
		$return = '';
		if($data && isset($data['url'])){
			$return .= '<a href="'.$data['url'].'" target="_blank">';
		}
		if($data && isset($data['displayName'])){
			$return .= htmlspecialchars($data['displayName']);
		}
		if($data && isset($data['url'])){
			$return .= '</a>';
		}
		return $return;
	}


	public function load_all_messages($search=array(),$order=array(),$limit_batch=0){
		$this->search_params = $search;
		$this->search_order = $order;
		$this->search_limit = $limit_batch;

		$sql = "SELECT m.*, mr.read_time FROM `"._support_hub_DB_PREFIX."shub_google_message` m ";
		$sql .= " LEFT OUTER JOIN `"._support_hub_DB_PREFIX."shub_google_message_read` mr ON ( m.shub_google_message_id = mr.shub_google_message_id AND mr.user_id = ".get_current_user_id()." )";
		$sql .= " WHERE 1 ";
		if(isset($search['status']) && $search['status'] !== false){
			$sql .= " AND `status` = ".(int)$search['status'];
		}
		if(isset($search['shub_message_id']) && $search['shub_message_id'] !== false){
			$sql .= " AND `shub_message_id` = ".(int)$search['shub_message_id'];
		}
		if(isset($search['shub_google_id']) && $search['shub_google_id'] !== false){
			$sql .= " AND `shub_google_id` = ".(int)$search['shub_google_id'];
		}
		if(isset($search['generic']) && !empty($search['generic'])){
			$sql .= " AND `summary` LIKE '%".esc_sql($search['generic'])."%'";
		}else{
			//$sql .= " AND `type` != "._GOOGLE_MESSAGE_TYPE_OTHERTWEET;
		}
		$sql .= " ORDER BY `message_time` DESC ";
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
			$google_message = new shub_google_message(false, $message['shub_google_message_id']);
			$data['message'] = $google_message;
			$data['shub_column_account'] .= '<div><img src="'.plugins_url('networks/google/google-logo.png', _DTBAKER_SUPPORT_HUB_CORE_FILE_).'" class="google_icon small"><a href="'.$google_message->get_link().'" target="_blank">'.htmlspecialchars( $google_message->get('google_account')->get( 'account_name' ) ) .'</a></div>';
			$data['shub_column_summary'] .= '<div><img src="'.plugins_url('networks/google/google-logo.png', _DTBAKER_SUPPORT_HUB_CORE_FILE_).'" class="google_icon small"><a href="'.$google_message->get_link().'" target="_blank">'.htmlspecialchars( $google_message->get_summary() ) .'</a></div>';
			// how many link clicks does this one have?
			$sql = "SELECT count(*) AS `link_clicks` FROM ";
			$sql .= " `"._support_hub_DB_PREFIX."shub_google_message` m ";
			$sql .= " LEFT JOIN `"._support_hub_DB_PREFIX."shub_google_message_link` ml USING (shub_google_message_id) ";
			$sql .= " LEFT JOIN `"._support_hub_DB_PREFIX."shub_google_message_link_click` lc USING (shub_google_message_link_id) ";
			$sql .= " WHERE 1 ";
			$sql .= " AND m.shub_google_message_id = ".(int)$message['shub_google_message_id'];
			$sql .= " AND lc.shub_google_message_link_id IS NOT NULL ";
			$sql .= " AND lc.user_agent NOT LIKE '%Google%' ";
			$sql .= " AND lc.user_agent NOT LIKE '%Yahoo%' ";
			$sql .= " AND lc.user_agent NOT LIKE '%Meta%' ";
			$sql .= " AND lc.user_agent NOT LIKE '%Slurp%' ";
			$sql .= " AND lc.user_agent NOT LIKE '%Bot%' ";
			$sql .= " AND lc.user_agent != 'Mozilla/5.0 ()' ";
			$res = shub_qa1($sql);
			$link_clicks = $res && $res['link_clicks'] ? $res['link_clicks'] : 0;
			$data['shub_column_links'] .= '<div><img src="'.plugins_url('networks/google/google-logo.png', _DTBAKER_SUPPORT_HUB_CORE_FILE_).'" class="google_icon small">'. $link_clicks  .'</div>';
		}
		if(count($messages) && $link_clicks > 0){
			//$data['shub_column_links'] = '<div><img src="'.plugins_url('networks/google/google-logo.png', _DTBAKER_SUPPORTHUB_CORE_FILE_).'" class="google_icon small">'. $link_clicks  .'</div>';
		}
		return $data;

	}

	public function get_unread_count($search=array()){
		if(!get_current_user_id())return 0;
		$sql = "SELECT count(*) AS `unread` FROM `"._support_hub_DB_PREFIX."shub_google_message` m ";
		$sql .= " WHERE 1 ";
		$sql .= " AND m.shub_google_message_id NOT IN (SELECT mr.shub_google_message_id FROM `"._support_hub_DB_PREFIX."shub_google_message_read` mr WHERE mr.user_id = '".(int)get_current_user_id()."' AND mr.shub_google_message_id = m.shub_google_message_id)";
		$sql .= " AND m.`status` = "._shub_MESSAGE_STATUS_UNANSWERED;
		if(isset($search['shub_google_id']) && $search['shub_google_id'] !== false){
			$sql .= " AND m.`shub_google_id` = ".(int)$search['shub_google_id'];
		}
		//$sql .= " AND m.`type` != "._GOOGLE_MESSAGE_TYPE_OTHERTWEET;
		$res = shub_qa1($sql);
		return $res ? $res['unread'] : 0;
	}

	public function output_row($message, $settings){
		$google_message = new shub_google_message(false, $message['shub_google_message_id']);
		?>
		<tr class="<?php echo isset($settings['row_class']) ? $settings['row_class'] : '';?> google_message_row <?php echo !isset($message['read_time']) || !$message['read_time'] ? ' message_row_unread' : '';?>"
	        data-id="<?php echo (int) $message['shub_google_message_id']; ?>"
	        data-shub_google_id="<?php echo (int) $message['shub_google_id']; ?>">
		    <td class="shub_column_account">
			    <img src="<?php echo plugins_url('networks/google/google-logo.png', _DTBAKER_SUPPORT_HUB_CORE_FILE_);?>" class="google_icon">
			    <a href="<?php echo $google_message->get_link(); ?>"
		           target="_blank"><?php echo htmlspecialchars( $google_message->get('google_account')->get( 'account_name' ) ); ?></a> <br/>
			    <?php echo htmlspecialchars( $google_message->get_type_pretty() ); ?>
		    </td>
		    <td class="shub_column_time"><?php echo shub_print_date( $message['message_time'], true ); ?></td>
		    <td class="shub_column_from">
			    <?php
		        // work out who this is from.
		        $from = $google_message->get_from();
			    ?>
			    <div class="shub_from_holder shub_google">
			    <div class="shub_from_full">
				    <?php
					foreach($from as $id => $from_data){
						?>
						<div>
							<a href="https://plus.google.com/<?php echo $id;?>" target="_blank"><img src="<?php echo $from_data['image'];?>" class="shub_from_picture"></a> <?php echo htmlspecialchars($from_data['screen_name']); ?>
						</div>
						<?php
					} ?>
			    </div>
		        <?php
		        reset($from);
		        $current = current($from);
		        echo '<a href="https://plus.google.com/'.htmlspecialchars(key($from)).'" target="_blank">' . '<img src="'.$current['image'].'" class="shub_from_picture"></a> ';
		        echo '<span class="shub_from_count">';
		        if(count($from) > 1){
			        echo '+'.(count($from)-1);
		        }
		        echo '</span>';
		        ?>
			    </div>
		    </td>
		    <td class="shub_column_summary">
			    <div class="google_message_summary<?php echo !isset($message['read_time']) || !$message['read_time'] ? ' unread' : '';?>"> <?php
				    echo $google_message->get_summary();
				    ?>
			    </div>
		    </td>
		    <!--<td></td>-->
		    <td nowrap class="shub_column_action">

		        <a href="<?php echo $google_message->link_open();?>" class="socialgoogle_message_open shub_modal button" data-modaltitle="<?php echo __('Google+','support_hub');?>" data-socialgooglemessageid="<?php echo (int)$google_message->get('shub_google_message_id');?>"><?php _e( 'Open' );?></a>

			    <?php if($google_message->get('status') == _shub_MESSAGE_STATUS_ANSWERED){  ?>
				    <a href="#" class="socialgoogle_message_action button"
				       data-action="set-unanswered" data-id="<?php echo (int)$google_message->get('shub_google_message_id');?>" data-shub_google_id="<?php echo (int)$google_message->get('shub_google_id');?>"><?php _e( 'Inbox' ); ?></a>
			    <?php }else{ ?>
				    <a href="#" class="socialgoogle_message_action button"
				       data-action="set-answered" data-id="<?php echo (int)$google_message->get('shub_google_message_id');?>" data-shub_google_id="<?php echo (int)$google_message->get('shub_google_id');?>"><?php _e( 'Archive' ); ?></a>
			    <?php } ?>
		    </td>
	    </tr>
		<?php
	}

	public function init_js(){
		?>
		    ucm.social.google.api_url = ajaxurl;
		    ucm.social.google.init();
		<?php
	}

	public function handle_process($process, $options = array()){
		switch($process){
			case 'send_shub_message':
				$message_count = 0;
				if(check_admin_referer( 'shub_send-message' ) && isset($options['shub_message_id']) && (int)$options['shub_message_id'] > 0 && isset($_POST['google_message']) && !empty($_POST['google_message'])){
					// we have a social message id, ready to send!
					// which google accounts are we sending too?
					$google_accounts = isset($_POST['compose_google_id']) && is_array($_POST['compose_google_id']) ? $_POST['compose_google_id'] : array();
					foreach($google_accounts as $google_account_id => $tf){
						if(!$tf)continue; // shoulnd't happen, as checkbox shouldn't post.
						$google_account = new shub_google_account($google_account_id);
						if($google_account->get('shub_google_id') == $google_account_id){
							// good to go! send us a message!


							$google_message = new shub_google_message($google_account, false);
						    $google_message->create_new();
						    $google_message->update('shub_google_id',$google_account->get('shub_google_id'));
						    $google_message->update('shub_message_id',$options['shub_message_id']);
						    $google_message->update('summary',isset($_POST['google_message']) ? $_POST['google_message'] : '');
							if(isset($_POST['track_links']) && $_POST['track_links']){
								$google_message->parse_links();
							}
						    $google_message->update('type','pending');
						    $google_message->update('data',json_encode($_POST));
						    $google_message->update('user_id',get_current_user_id());
						    // do we send this one now? or schedule it later.
						    $google_message->update('status',_shub_MESSAGE_STATUS_PENDINGSEND);
						    if(isset($options['send_time']) && !empty($options['send_time'])){
							    // schedule for sending at a different time (now or in the past)
							    $google_message->update('message_time',$options['send_time']);
						    }else{
							    // send it now.
							    $google_message->update('message_time',0);
						    }
						    /*if(isset($_FILES['picture']['tmp_name']) && is_uploaded_file($_FILES['picture']['tmp_name'])){
							    $google_message->add_attachment($_FILES['picture']['tmp_name']);
						    }*/
							$now = time();
							if(!$google_message->get('message_time') || $google_message->get('message_time') <= $now){
								// send now! otherwise we wait for cron job..
								if($google_message->send_queued(isset($_POST['debug']) && $_POST['debug'])){
									$message_count ++;
								}
							}else{
						        $message_count ++;
								if(isset($_POST['debug']) && $_POST['debug']){
									echo "Message will be sent in cron job after ".shub_print_date($google_message->get('message_time'),true);
								}
							}
						}
					}
				}
				return $message_count;
				break;
			case 'save_google_settings':
				if(check_admin_referer( 'save-google-settings' )) {
					if ( isset( $_POST['google_app_api_key'] ) ) {
						$this->update( 'api_key', $_POST['google_app_api_key'] );
					}
					if ( isset( $_POST['google_app_api_secret'] ) ) {
						$this->update( 'api_secret', $_POST['google_app_api_secret'] );
					}
				}
				break;
			case 'save_google':
				$shub_google_id = isset($_REQUEST['shub_google_id']) ? (int)$_REQUEST['shub_google_id'] : 0;
				if(check_admin_referer( 'save-google'.$shub_google_id )) {
					$google = new shub_google_account( $shub_google_id );
					if ( isset( $_POST['butt_delete'] ) ) {
						$google->delete();
						$redirect = 'admin.php?page=support_hub_settings&tab=google';
					} else {
						$google->save_data( $_POST );
						$shub_google_id = $google->get( 'shub_google_id' );
						if ( isset( $_POST['butt_save_reconnect'] ) ) {
							$redirect = $google->link_connect();
						} else {
							$redirect = $google->link_edit();
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
				if(isset($_REQUEST['shub_google_id']) && !empty($_REQUEST['shub_google_id']) && isset($_REQUEST['id']) && (int)$_REQUEST['id'] > 0) {
					$shub_google = new shub_google_account($_REQUEST['shub_google_id']);
					if($shub_google->get('shub_google_id') == $_REQUEST['shub_google_id']){
						$shub_google_message = new shub_google_message( $shub_google, $_REQUEST['id'] );
						if($shub_google_message->get('shub_google_message_id') == $_REQUEST['id']) {
							$return  = array();
							$message = isset( $_POST['message'] ) && $_POST['message'] ? $_POST['message'] : '';
							$debug   = isset( $_POST['debug'] ) && $_POST['debug'] ? $_POST['debug'] : false;
							if ( $message ) {
								ob_start();


								$worked = $shub_google_message->api_add_reply(array(
									'message' => $message,
									'user_id' => get_current_user_id(),
								), $debug);

								$return['message'] = ob_get_clean();
								if ( $debug ) {
									// just return message
								} else if ( $worked ) {
									// success, redicet!
									//set_message( _l( 'Message sent and conversation archived.' ) );
									//$return['redirect'] = module_shub_google::link_open_message_view( $shub_google_id );
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
				if(isset($_REQUEST['socialgooglemessageid']) && (int)$_REQUEST['socialgooglemessageid'] > 0) {
					$shub_google_message = new shub_google_message( false,  $_REQUEST['socialgooglemessageid'] );
					if($shub_google_message->get('shub_google_message_id') == $_REQUEST['socialgooglemessageid']){

						$shub_google_id = $shub_google_message->get('google_account')->get('shub_google_id');
						$shub_google_message_id = $shub_google_message->get('shub_google_message_id');
						include( trailingslashit( $support_hub_wp->dir ) . 'networks/google/google_message.php');
					}

				}
				break;
			case 'set-answered':
				if (!headers_sent())header('Content-type: text/javascript');
				if(isset($_REQUEST['shub_google_message_id']) && (int)$_REQUEST['shub_google_message_id'] > 0){
					$shub_google_message = new shub_google_message(false, $_REQUEST['shub_google_message_id']);
					if($shub_google_message->get('shub_google_message_id') == $_REQUEST['shub_google_message_id']){
						$shub_google_message->update('status',_shub_MESSAGE_STATUS_ANSWERED);
						?>
						jQuery('.socialgoogle_message_action[data-id=<?php echo (int)$shub_google_message->get('shub_google_message_id'); ?>]').parents('tr').first().hide();
						<?php
					}
				}
				break;
			case 'set-unanswered':
				if (!headers_sent())header('Content-type: text/javascript');
				if(isset($_REQUEST['shub_google_message_id']) && (int)$_REQUEST['shub_google_message_id'] > 0){
					$shub_google_message = new shub_google_message(false, $_REQUEST['shub_google_message_id']);
					if($shub_google_message->get('shub_google_message_id') == $_REQUEST['shub_google_message_id']){
						$shub_google_message->update('status',_shub_MESSAGE_STATUS_UNANSWERED);
						?>
						jQuery('.socialgoogle_message_action[data-id=<?php echo (int)$shub_google_message->get('shub_google_message_id'); ?>]').parents('tr').first().hide();
						<?php
					}
				}
				break;
		}
		return false;
	}


	public function run_cron( $debug = false ){
		if($debug)echo "Starting Google Cron Job \n";
		$accounts = $this->get_accounts();
		foreach($accounts as $account){
			$shub_google_account = new shub_google_account( $account['shub_google_id'] );
			$shub_google_account->run_cron($debug);
		}
		if($debug)echo "Finished Google Cron Job \n";
	}

	public function get_install_sql() {

		global $wpdb;

		$sql = <<< EOT



CREATE TABLE {$wpdb->prefix}shub_google (
  shub_google_id int(11) NOT NULL AUTO_INCREMENT,
  username varchar(255) NOT NULL,
  password varchar(255) NOT NULL,
  google_id varchar(255) NOT NULL,
  google_name varchar(50) NOT NULL,
  google_data text NOT NULL,
  last_checked int(11) NOT NULL DEFAULT '0',
  import_comments tinyint(1) NOT NULL DEFAULT '0',
  import_plusones tinyint(1) NOT NULL DEFAULT '0',
  import_mentions tinyint(1) NOT NULL DEFAULT '0',
  user_data text NOT NULL,
  searches text NOT NULL,
  api_cookies text NOT NULL,
  account_name varchar(80) NOT NULL,
  PRIMARY KEY  shub_google_id (shub_google_id),
  KEY google_id (google_id)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;


CREATE TABLE {$wpdb->prefix}shub_google_message (
  shub_google_message_id int(11) NOT NULL AUTO_INCREMENT,
  shub_google_id int(11) NOT NULL,
  shub_message_id int(11) NOT NULL DEFAULT '0',
  google_message_id varchar(255) NOT NULL,
  comment_count int(11) NOT NULL,
  comments text NOT NULL,
  share_count int(11) NOT NULL,
  plusone_count int(11) NOT NULL,
  google_actor text NOT NULL,
  google_type varchar(80) NOT NULL,
  type tinyint(1) NOT NULL DEFAULT '0',
  status tinyint(1) NOT NULL DEFAULT '0',
  summary text NOT NULL,
  summary_latest text NOT NULL,
  message_time int(11) NOT NULL DEFAULT '0',
  data text NOT NULL,
  user_id int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY  shub_google_message_id (shub_google_message_id),
  KEY shub_google_id (shub_google_id),
  KEY shub_message_id (shub_message_id),
  KEY message_time (message_time),
  KEY status (status),
  KEY type (type),
  KEY google_message_id (google_message_id)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;


CREATE TABLE {$wpdb->prefix}shub_google_message_read (
  shub_google_message_id int(11) NOT NULL,
  read_time int(11) NOT NULL DEFAULT '0',
  user_id int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY  shub_google_message_id (shub_google_message_id,user_id)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;


CREATE TABLE {$wpdb->prefix}shub_google_message_link (
  shub_google_message_link_id int(11) NOT NULL AUTO_INCREMENT,
  shub_google_message_id int(11) NOT NULL DEFAULT '0',
  link varchar(255) NOT NULL,
  PRIMARY KEY  shub_google_message_link_id (shub_google_message_link_id),
  KEY shub_google_message_id (shub_google_message_id)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;

CREATE TABLE {$wpdb->prefix}shub_google_message_link_click (
  shub_google_message_link_click_id int(11) NOT NULL AUTO_INCREMENT,
  shub_google_message_link_id int(11) NOT NULL DEFAULT '0',
  click_time int(11) NOT NULL,
  ip_address varchar(20) NOT NULL,
  user_agent varchar(100) NOT NULL,
  url_referrer varchar(255) NOT NULL,
  PRIMARY KEY  shub_google_message_link_click_id (shub_google_message_link_click_id),
  KEY shub_google_message_link_id (shub_google_message_link_id)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;



EOT;
		return $sql;
	}

}

class shub_google_account{

	public function __construct($shub_google_id){
		$this->load($shub_google_id);
	}

	private $shub_google_id = false; // the current user id in our system.
    private $details = array();

	private function reset(){
		$this->shub_google_id = false;
		self::$ch_api = false;
		self::$cookie_file = false;
		$this->details = array(
			'shub_google_id' => false,
			'username' => false,
			'password' => false,
			'api_cookies' => false,
			'google_id' => false,
			'google_name' => false,
			'google_data' => false,
			'last_checked' => false,
			'import_comments' => false,
			'import_plusones' => false,
			'import_mentions' => false,
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
		$this->shub_google_id = shub_update_insert('shub_google_id',false,'shub_google',array());
		$this->load($this->shub_google_id);
	}

    public function load($shub_google_id = false){
	    if(!$shub_google_id)$shub_google_id = $this->shub_google_id;
	    $this->reset();
	    $this->shub_google_id = $shub_google_id;
        if($this->shub_google_id){
            $this->details = shub_get_single('shub_google','shub_google_id',$this->shub_google_id);
	        if(!is_array($this->details) || $this->details['shub_google_id'] != $this->shub_google_id){
		        $this->reset();
		        return false;
	        }
        }
        foreach($this->details as $key=>$val){
            $this->{$key} = $val;
        }
        return $this->shub_google_id;
    }

	public function get_messages($search=array()){
		$google = new shub_google();
		$search['shub_google_id'] = $this->shub_google_id;
		return $google->load_all_messages($search);
		//return shub_get_multiple('shub_google_message',$search,'shub_google_message_id','exact','message_time DESC');
	}

	public function get($field){
		return isset($this->{$field}) ? $this->{$field} : false;
	}
	public function get_picture(){
		$data = @json_decode($this->get('google_data'),true);
		return $data && isset($data['profile_photo']) && !empty($data['profile_photo']) ? $data['profile_photo'] : false;
	}

	public function save_data($post_data){
		if(!$this->get('shub_google_id')){
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
			// hack for storing gaps cookie in our serialized google data array
			if(isset($post_data['gaps_cookie'])){
				$google_data = @json_decode($this->get('google_data'),true);
			    if(!is_array($google_data))$google_data = array();
				$google_data['gaps_cookie'] = $post_data['gaps_cookie'];
				$this->update('google_data',json_encode($google_data));
			}
		}
		$this->load();
		return $this->get('shub_google_id');
	}
    public function update($field,$value){
	    // what fields to we allow? or not allow?
	    if(in_array($field,array('shub_google_id')))return;
        if($this->shub_google_id){
            $this->{$field} = $value;
            shub_update_insert('shub_google_id',$this->shub_google_id,'shub_google',array(
	            $field => $value,
            ));
        }
    }
	public function delete(){
		if($this->shub_google_id) {
			// delete all the messages for this google account.
			$messages = shub_get_multiple('shub_google_message',array(
				'shub_google_id' => $this->shub_google_id,
			),'shub_google_message_id');
			foreach($messages as $message){
				if($message && isset($message['shub_google_id']) && $message['shub_google_id'] == $this->shub_google_id){
					shub_delete_from_db( 'shub_google_message', 'shub_google_message_id', $message['shub_google_message_id'] );
					shub_delete_from_db( 'shub_google_message_link', 'shub_google_message_id', $message['shub_google_message_id'] );
					shub_delete_from_db( 'shub_google_message_read', 'shub_google_message_id', $message['shub_google_message_id'] );
				}
			}
			shub_delete_from_db( 'shub_google', 'shub_google_id', $this->shub_google_id );
		}
	}

	public function is_active(){
		// is there a 'last_checked' date?
		if(!$this->get('last_checked')){
			return false; // never checked this account, not active yet.
		}else{
			return true;
		}
	}


	public function api_get($url){
		$this->init_curl();
		curl_setopt(self::$ch_api, CURLOPT_URL, $url);
		//return curl_exec(self::$ch_api);
		return $this->_curl_redir_exec();
	}
	public function api_post($url, $postdata){
		$this->init_curl();
		curl_setopt(self::$ch_api, CURLOPT_URL, $url);
		curl_setopt(self::$ch_api, CURLOPT_POST, true);
		curl_setopt(self::$ch_api, CURLOPT_POSTFIELDS, $postdata);
		//return curl_exec(self::$ch_api);
		return $this->_curl_redir_exec();
	}
	private function _curl_redir_exec(){
        static $curl_loops = 0;
        static $curl_max_loops = 20;
        if ($curl_loops++ >= $curl_max_loops)
        {
            $curl_loops = 0;
            return FALSE;
        }
        curl_setopt(self::$ch_api, CURLOPT_HEADER, true);
        $data = curl_exec(self::$ch_api);
		//echo htmlspecialchars($data);
		if(strpos($data,"\r\n\r\n")!==false) {
			list( $header, $data ) = explode( "\r\n\r\n", $data, 2 );
		}else if(strpos($data,"\n\n")!==false){
			list($header, $data) = explode("\n\n", $data, 2);
			//echo " 1 $header ";
		/*}else if(strpos($data,"\n")!==false){
			list($header, $data) = explode("\n", $data, 2);
			echo " 2 $header ";*/
		}else{
			echo "Unable to find content position... please report this error... raw data is: \n";
			echo $data;
			$header = $data;
			$data = '';

			//echo " 3 $header ";
		}

		//echo "Header: ".$header.'<hr>Data: '.htmlspecialchars($data).'<hr><hr>';
        //list($header, $data) = explode("\n\n", $data, 2);
        $http_code = curl_getinfo(self::$ch_api, CURLINFO_HTTP_CODE);
        if ($http_code == 301 || $http_code == 302) {
	        $matches = array();
            preg_match('/Location:(.*?)\n/', $header, $matches);
            $url = @parse_url(trim(array_pop($matches)));
            if (!$url)
            {
                //couldn't process the url to redirect to
                $curl_loops = 0;
                return $data;
            }
            $last_url = parse_url(curl_getinfo(self::$ch_api, CURLINFO_EFFECTIVE_URL));
            if (!$url['scheme'])
                $url['scheme'] = $last_url['scheme'];
            if (!$url['host'])
                $url['host'] = $last_url['host'];
            if (!$url['path'])
                $url['path'] = $last_url['path'];
            $new_url = $url['scheme'] . '://' . $url['host'] . $url['path'] . ($url['query']?'?'.$url['query']:'');
            //curl_setopt(self::$ch_api, CURLOPT_URL, $new_url);
            //debug('Redirecting to', $new_url);
            return $this->api_get($new_url);
        } else {
            $curl_loops=0;
            return $data;
        }
	}
	public function api_complete(){
		if(!self::$cookie_file)return;
		$newcookies = file_get_contents(self::$cookie_file);
		//echo "Cookies: ".$newcookies;
		$this->update('api_cookies',$newcookies);
		file_put_contents(self::$cookie_file,'NO');
		@unlink(self::$cookie_file);
	}

	private $api_vars = array();

	private function _api_login_internal($data){

	}
	public function api_login($debug=false) {
		// use curl to login / check login and return true if success.
		$signed_in = false;
		if ( $debug ) echo 'Checking if we are already signed in...';
		$result = $this->api_get( 'https://plus.google.com/me' );
		if ( !$result || strpos( $result, 'Sign in to continue' ) !== false || strpos( $result, 'Please re-enter your') !== false ) {
			// not already signed in, do the signin..
			if ( $debug ) echo 'Not yet signed in, doing the signin...'."\n<br>";
			if(strpos( $result, 'Please re-enter your') !== false){
				if ( $debug ) echo ' prompt for re-entering password...'."\n<br>";
				$data = $result;
			}else{
				$data = $this->api_get('https://accounts.google.com/ServiceLogin?hl=en&service=oz&continue=https://plus.google.com');
			}

			if (preg_match('/(<form.*?id=.?gaia_loginform.*?<\/form>)/is', $data, $form_matches)) {
				$formFields = array();
			    $elements = preg_match_all('/(<input[^>]+>)/is', $form_matches[1], $matches);
			    if ($elements > 0) {
			        for($i = 0; $i < $elements; $i++) {
			            $el = preg_replace('/\s{2,}/', ' ', $matches[1][$i]);
			            if (preg_match('/name=(?:["\'])?([^"\'\s]*)/i', $el, $name)) {
			                $name  = $name[1];
			                $value = '';
			                if (preg_match('/value=(?:["\'])?([^"\'\s]*)/i', $el, $value)) {
			                    $value = $value[1];
			                }else{
				                $value = '';
			                }
			                $formFields[$name] = $value;
			            }
			        }
			    }
	//			if($debug) echo 'Inputs:';
				if($debug) print_r($formFields);

		    } else {
				if($debug) echo 'No login form found, google has changed something! Or cURl is not working on the hosting account. Please ask the hosting provider if cURL is enabled.';
				return false;
		    }

			$formFields['Email']  = $this->get('username');
			$formFields['Passwd'] = $this->get('password');
			//unset($formFields['PersistentCookie']);

			$post_string = '';
			foreach($formFields as $key => $value) {
			    $post_string .= $key . '=' . urlencode($value) . '&';
			}

			$post_string = substr($post_string, 0, -1);

			$result = $this->api_post('https://accounts.google.com/ServiceLoginAuth', $post_string);
			if(strpos($result, 'https://accounts.google.com/LoginVerification') !== false){
				echo "Login has failed. Please <a href='https://accounts.google.com/ServiceLogin?hl=en&service=oz&continue=https://plus.google.com' target='_blank'>open a new browser window</a> and try to login using the Google+ Page Details ( ".$this->get('username').' ). If that does not work please try resetting the password.'."<br>\n";
			}else if(strpos($result, 'https://accounts.google.com/ServiceLoginAuth') !== false){
				if ( $debug ) {
					if (strpos($result, 'Sign in to continue') !== false) {
						echo "Login failed, please check username and password. Try to login using these details in a browser to double check.";
						//echo $result;
					}else{
						echo "Login check failed, please check username and password. Try to login using these details in a browser to double check.";
					}
				}
			}else{
				$signed_in = true;
				$result = $this->api_get('https://plus.google.com/me');
				if (strpos($result, 'Sign in to continue') !== false) {
					if($debug) {
					    echo "Login #2 failed, please check username and password. Try to login using these details in a browser to double check it works.";
					    //echo $result;
						$signed_in = false;
			        }
				}
			}
		}else {
			if($debug) echo ' yep! signed in<br>';
			$signed_in = true;
		}

		if($signed_in && $result){
			if($debug) echo 'Signin complete! <br>';
		    //if(preg_match('#oid="(\d+)"#',$result,$matches)){
			if(preg_match('#data:\["(\d+)",\[,,"https://plus\.google\.com/([^"]+)"#',$result,$matches)){
				// success! we have a page id number.
				// lets hope this ID is correct.
				$this->update('google_id',$matches[1]);
				// google alias name: $matches[2]
				if($debug) echo 'Found google page id '.$matches[1].' <br>';
				if($debug) echo 'Found google page name '.$matches[2].' <br>';
				// grab the meta name
				if(preg_match('#itemprop="name" content="([^"]+)"#',$result,$matches)){
					$this->update('google_name',$matches[1]);
					if($debug) echo 'Found google page name '.$matches[1].' <br>';
				}
				if(preg_match('#OZ_afsid = \'([^\']+)\'#',$result,$matches)){
					$this->api_vars['f.sid'] = $matches[1];
					if($debug) echo 'Found session id '.$matches[1].' <br>';
				}
				if(preg_match('#OZ_buildLabel = \'([^\']+)\'#',$result,$matches)){
					$this->api_vars['ozv'] = $matches[1];
					if($debug) echo 'Found build label '.$matches[1].' <br>';
				}
				if(preg_match('#https://csi.gstatic.com/csi","([^"]+)"#',$result,$matches)){
					$this->api_vars['csi'] = $matches[1];
					if($debug) echo 'Found CSI code '.$matches[1].' <br>';
				}
			    $google_data = @json_decode($this->get('google_data'),true);
			    if(!is_array($google_data))$google_data = array();
				if(preg_match('#"profile_photo"><img src="([^"]+)"#',$result,$matches)){
					if($debug) echo 'Found profile photo: '.$matches[1].' <br>';
					$google_data['profile_photo'] = $matches[1];
				}
				// save these api_vars into google_data database table so that we can use them again later without going through this login process.
			    // find the profile id on this page.
			    $this->update('google_data',json_encode($google_data));
				$this->api_complete();
				return true;
			}else{
				if($debug) echo 'No google page ID found, please try again. Make sure your Google+ page has at least 1 public post on it already. <br>';
			    //echo htmlspecialchars($result);
		    }
		    //echo htmlspecialchars($result);
			//print_r(curl_getinfo(self::$ch_api));
		}

		$this->api_complete();
		return false;
	}

	public function api_get_comment($comment_id, $debug = false){
		$url = "https://www.googleapis.com/plus/v1/activities/$comment_id?key=AIzaSyCf5LnSQ3GaPpLpsqgOs9MYASGmml__8j0";
		$data = $this->api_get($url);
		return @json_decode($data,true);
	}
	public function api_get_activity_comments($activity_id, $debug = false){
		if(!$activity_id)return array();
		$url = "https://content.googleapis.com/plus/v1/activities/$activity_id/comments?key=AIzaSyCf5LnSQ3GaPpLpsqgOs9MYASGmml__8j0";
		$data = $this->api_get($url);
		$comments = @json_decode($data,true);
		if(!$comments || isset($comments['error'])){
			// try via the js api.
			/*
			$result = $this->api_post('https://plus.google.com/_/stream/getactivity?soc-app=6&cid=0&soc-platform=1&hl=en&ozv='.(isset($this->api_vars['ozv']) ? $this->api_vars['ozv'] : 'es_oz_20140501.12_p0').'&avw=nots%3A2&f.sid='.(isset($this->api_vars['f.sid']) ? $this->api_vars['f.sid'] : '-1295571870751942386').'&_reqid=1061921&rt=j', array(
				'f.req' => '["'.$activity_id.'",null,null,null,null,null,null,[6,null,null,null,null,null,null,true,null,null,null,null,null,[],null,null,true,null,[null,null,null,true]],2]',
				'at' => $this->api_vars['csi'],
			));
			$data = trim(substr($result, 5));
			$data = preg_replace('#\n#','',$data);
			$data = preg_replace('@,,@',',null,',$data);
			$data = preg_replace('@,,@',',null,',$data);
			$data = preg_replace('@\[,@','[null,',$data);
	//		echo "My data<br>\n";
	//		echo "{{" . $data . "}}<br>\n";
			$foo = @json_decode($data,true);
			echo 'error via public, trying private';
			print_r($foo);*/
		}
		return $comments;
	}
	public function api_get_page_comments($page_id, $debug = false){
		$last_checked_time = $this->get('last_checked');
		$latest_activity_time = $last_checked_time;
		$url = "https://www.googleapis.com/plus/v1/people/$page_id/activities/public?key=AIzaSyCf5LnSQ3GaPpLpsqgOs9MYASGmml__8j0";
		$data = $this->api_get($url);
		//$data = file_get_contents($url);
		$data = @json_decode($data,true);
		if($debug){
			echo "<br><br>\nGetting page public comments...<br>";
		}
		$comment_ids = array();
		if($data && is_array($data)){
			$comments = isset($data['items']) && is_array($data['items']) ? $data['items'] : array();
			foreach($comments as $comment){
				if(isset($comment['id']) && !empty($comment['id'])) {
					if($last_checked_time && strtotime($comment['updated']) < $last_checked_time){
						if($debug){
							echo "Skipping comment ".$comment['id']." because it occured at time ".strtotime($comment['updated'])." and we only want ones after $last_checked_time<br>\n";
						}
						continue;
					}
					$latest_activity_time = max($latest_activity_time,strtotime($comment['updated']));
					$new_comment = new shub_google_message( $this, false );
					$comment_ids[$comment['id']] = true;
					$new_comment->load_by_google_id( $comment['id'], $comment, $debug, true );
					if($debug){
						echo "Loaded comment id ".$comment['id']." with our db id of ".$new_comment->get('shub_google_message_id')."<br>\n";
					}
				}
			}
		}
		if($debug){
			echo "<br><br>\nGetting page notifications (comments, +1, etc..)<br>";
		}
		// now we get any previous notifications and refresh those comments too
		$notifications = $this->api_get_notifications($debug, $last_checked_time);
		// any of these comments need a refresh?
		foreach($notifications as $notification){
			if($notification['id'] && !isset($comment_ids[$notification['id']])){
				if($last_checked_time && $notification['updated'] < $last_checked_time){
					if($debug){
						echo "Skipping notification ".$notification['id']." because it occured at time ".$notification['updated']." and we only want ones after $last_checked_time<br>\n";
					}
					continue;
				}
				$latest_activity_time = max($latest_activity_time,$notification['updated']);
				// this comment wasnt' process in the previous comment loop, so it's probably an old comment that just got a refresh.
				// process it again to import all the new data:
				$comment_ids[$notification['id']] = true;
				$new_comment = new shub_google_message( $this, false );
				$new_comment->load_by_google_id( $notification['id'], false, $debug, true );
				if($debug){
					echo "Loaded comment from notification id ".$notification['id']." with our db id of ".$new_comment->get('shub_google_message_id')."<br>\n";
				}
			}
		}
		if($debug){
			echo "<br>\nFinished.\n<br>";
		}
		$this->update('last_checked',$latest_activity_time);

	}
	private function api_decode_js($js){
		$data = trim(substr($js, 5));
		$data = preg_replace('#\n#','',$data);
		$data = preg_replace('@,,@',',null,',$data);
		$data = preg_replace('@,,@',',null,',$data);
		$data = preg_replace('@\[,@','[null,',$data);
//		echo "My data<br>\n";
//		echo "{{" . $data . "}}<br>\n";
		return @json_decode($data,true);
	}
	public function api_get_notifications($debug = false, $last_checked_time = false){
		$result = $this->api_post('https://plus.google.com/_/notifications/getnotificationsdata?hl=en&ozv='.(isset($this->api_vars['ozv']) ? $this->api_vars['ozv'] : 'es_oz_20140501.12_p0').'&avw=nots%3A2&f.sid='.(isset($this->api_vars['f.sid']) ? $this->api_vars['f.sid'] : '-1295571870751942386').'&_reqid=1347787&rt=j', array(
			//'f.req' => '[null,[],6,null,[],null,null,[],null,null,15,null,null,null,null,null,[11],null,null,2]',
			'f.req' => '[null,[],6,null,[],null,null,[],null,null,15,null,null,null,null,null,[18],null,null,2]',
			'at' => isset($this->api_vars['csi']) ? $this->api_vars['csi'] : false,
		));
		$foo = $this->api_decode_js($result);
		if($debug)echo '</pre>';
		$actions = array(
             1=> 'wrote on your profile',
             2=> 'commented on your {thing}',
             3=> 'commented on a {thing} you commented on',
             4=> 'liked your {thing}',
             5=> 'reshared your {thing}',
             6=> 'added you on Google+',
             10=> 'tagged you in a photo',
             13=> 'tagged your photo',
             14=> 'commented on a {thing} that you were mentioned in',
             15=> 'mentioned you in a comment on a {thing}',
             16=> 'mentioned you in a {thing}',
             20=> "+1'd your {thing}",
             21=> "+1'd your comment on a {thing}",
             24=> 'shared a {thing} with you',
             25=> "commented on a {thing} you're tagged in",
             26=> "commented on a {thing} you tagged",
             29=> "invited you to a new conversation on Google+ Mobile",
             32=> 'invited you to join Google+',
             33=> "invites you to a hangout"
			);
		$our_activities = array();
		if(is_array($foo) && isset($foo[0][1][1][0])){
			$js_activities = $foo[0][1][1][0];
			/*?> <pre style="font-size:10px; line-height: 9px"><?php print_r($foo);?></pre> <?php*/
			foreach($js_activities as $activity){
				$activity_id = $activity[10];
				if(!$activity_id)continue;
				// parse our activite js data into something usable.
				$last_activity = round($activity[18][0][0][5]/1000);
				$replies = array();
				if(isset($activity[18][0][0][7]) && is_array($activity[18][0][0][7])){
					foreach($activity[18][0][0][7] as $reply){
						$replies[] = array(
							'id' => $reply[4],
							'message' => $reply[2],
							'author' => $reply[25],
							'time' => round($reply[3]/1000),
						);
						$last_activity = max($last_activity,round($reply[3]/1000));
					}
				}

				if($last_checked_time && $last_activity < $last_checked_time){
					if($debug){
						echo "Skipping notification ".$activity_id." because it occured at time ".$last_activity." and we only want ones after $last_checked_time<br>\n";
					}
					continue;
				}

				$actors = $activity[2][0][1];

				if($activity[18][0][0][93] > 0 && $activity[18][0][0][93] > count($replies)){
					// we need to load our replies to this activity, we do this via js because the api doesn't support private comment/mentions
					$new_replies = $this->api_get_activity_comments($activity_id, $debug);
					if(count($new_replies)){
						$replies = $new_replies;
					}
				}

				$our_activity = array(
					'action_code' => $actors[0][1],
					'action_word' => $actions[$actors[0][1]],
					'action' => $activity[18][6][0],
					'id' => $activity_id,
					'updated' => $last_activity,
					'actor' => $actors[0][2],
					'actors_count' => count($actors),
					'message' => $activity[18][0][0][4],
					'message_txt' => $activity[18][0][0][20],
					'replies' => $replies,
					'reply_count' => $activity[18][0][0][93],
					//'url' => 'https://plus.google.com/'.$activity[18][0][0][21],
					'url' => $activity[18][0][0][131],
				);
				if($debug === 2){
					?>
					<!--<pre style="font-size:10px;"><?php /*print_r($our_activity);*/?></pre>-->
					<table><tr><td valign="top"><pre style="font-size:10px;line-height: 9px"><?php print_r($activity);?></pre></td><td valign="top"><pre style="font-size:10px;line-height: 9px"><?php print_r($our_activity);?></pre></td></tr></table>
					<?php
				}
				$our_activities[] = $our_activity;
			}
		}
		if($debug)echo '<pre>';
		return $our_activities;

	}


	public function api_post_comment_reply($comment_id, $message, $debug=false){

		/*
		f.req:["z13fv1ewjuabhnw4q04cd1tpun3zvrsxzow0k","os:z13fv1ewjuabhnw4q04cd1tpun3zvrsxzow0k:1399277528462","@110489175763354115513 test reply tagging a user... ",1399277543476,null,2,null,"!A0JjTila1ujJvURCLUtR3B5h2AIAAAAmUgAAAAgqAQbk0MiOGP8eHVfOtN0F3sotBds7IE85wbyVqVdBmWvXPXTfrxHDa7ZlKTcQqewSvPP2tgjdPg95fEhvnncEwLbRWAYyClFTmZIdL_BUYglczXN9Q6JwvOeUdyzM4FHXF7EVpIjfxI0M6e3vwOv5d20_5MFIevu_TlJIQyeN5gM8_Ye8tlkEx14Qltn081LQlVZHQTgCB6rzNCJztf5dLBlDw2l8uVfqOdvojOlMyFnJLUShYhh8lY_z7GJJJCNZ1iaQKMJ7JeWvnlOsyiFWYk4l9-3qzsK5AZ7pnhNpf64OMB0rO0C7huvthSvdsV91PbmZqaIIvGauVrxKj5Wf2ZzWvWgp6Fsm",[3,null,null,null,null,null,null,1,null,null,null,null,null,[],null,[0,440,520]]]
		*/
		$url = 'https://plus.google.com/_/stream/comment/?rt=j';
		$post_data = array(
			'f.req' => json_encode(
				array(
					"$comment_id",
					"os:$comment_id:".time()."359",
					$message,
					(int)((time()+9)."149"),
					null,
					null,
					1
				)
			),
			'at' => $this->api_vars['csi'],

		);
		$result = $this->api_post($url, $post_data);
		if($debug) {
			echo $url;
			print_r( $post_data );
			echo $result;
		}
		return strpos($result, $comment_id);

	}


	public function api_post_page_status($page_id, $message, $debug = false){
		$url = 'https://plus.google.com/_/sharebox/post/?spam=20&rt=j';

		$req = array();
		for($x=0;$x<=37;$x++)$req[$x]=null;
		$req[0] = $message;
		$req[1] = "oz:".$page_id.".".base_convert(time(), 10, 16).".0";
		$req[9] = true;
		$req[11] = false;
		$req[12] = false;
		$req[14] = array();
		$req[16] = false;
		$req[27] = false;
		$req[28] = false;
		$req[29] = false;
		$req[36] = array(); //community
		$req[37] = array(array(array(null,null,1)),null); // public


		$post_data = array(
			'f.req' => json_encode(
				$req
			),
			'at' => $this->api_vars['csi'],

		);
		$result = $this->api_post($url, $post_data);

		if($debug) {
			echo $url;
			print_r( $post_data );
			echo $result;
		}
		return $this->api_decode_js($result);

	}

	private static $cookie_file = false;
	private static $ch_api = false;
	public function init_curl(){
		//if(!self::$ch_api){

			if(!self::$cookie_file) {
				$cookie_contents   = $this->get( 'api_cookies' );
				self::$cookie_file = tempnam( sys_get_temp_dir(), 'SupportHub' );
				// is there a manually set GAPS cookie?
				$google_data = @json_decode($this->get('google_data'),true);
			    if(!is_array($google_data))$google_data = array();
				if(isset($google_data['gaps_cookie']) && strlen($google_data['gaps_cookie'])){
					// remove existing cookie from content.
					$bits = explode("\n",$cookie_contents);
					foreach($bits as $key=>$val){
						$bits[$key] = trim($val);
						if(preg_match('#\sGAPS\s#',$val)){
							unset($bits[$key]);
						}
					}
					$bits[] = "#HttpOnly_accounts.google.com	FALSE	/	TRUE	1479253220	GAPS	".$google_data['gaps_cookie'];
					$cookie_contents = implode("\n",$bits);
				}
				file_put_contents( self::$cookie_file, $cookie_contents );
			}

			self::$ch_api = curl_init();
			curl_setopt(self::$ch_api, CURLOPT_CONNECTTIMEOUT, 10);
			curl_setopt(self::$ch_api, CURLOPT_USERAGENT, "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/32.0.1700.107 Safari/537.36");
			curl_setopt(self::$ch_api, CURLOPT_RETURNTRANSFER, true);
			curl_setopt(self::$ch_api, CURLOPT_SSL_VERIFYPEER, false);
			@curl_setopt(self::$ch_api, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt(self::$ch_api, CURLOPT_COOKIEJAR, self::$cookie_file);
			curl_setopt(self::$ch_api, CURLOPT_COOKIEFILE, self::$cookie_file);
			curl_setopt(self::$ch_api, CURLOPT_HEADER, 0);
			curl_setopt(self::$ch_api, CURLOPT_REFERER, 'http://SupportHub.com/api/js');
			curl_setopt(self::$ch_api, CURLOPT_RETURNTRANSFER,1);
			curl_setopt(self::$ch_api, CURLOPT_CONNECTTIMEOUT, 120);
			curl_setopt(self::$ch_api, CURLOPT_TIMEOUT, 20);
			//curl_setopt(self::$ch_api, CURLOPT_VERBOSE, 0);
			//curl_setopt(self::$ch_api, CURLINFO_HEADER_OUT, 1);

		//}
		return self::$ch_api;
	}

	public function run_cron($debug = false){
		$this->api_login($debug);
		$this->api_get_page_comments($this->get('google_id'),$debug);
		// find all messages that haven't been sent yet.
		$messages = $this->get_messages(array(
			'status' => _shub_MESSAGE_STATUS_PENDINGSEND,
		));
		$now = time();
		foreach($messages as $message){
			if(isset($message['message_time']) && $message['message_time'] < $now){
				$shub_google_message = new shub_google_message($this, $message['shub_google_message_id']);
				$shub_google_message->send_queued($debug);
			}
		}
	}


	
	/**
	 * Links for wordpress
	 */
	public function link_connect(){
		return 'admin.php?page=support_hub_settings&tab=google&do_google_connect=1&shub_google_id='.$this->get('shub_google_id');
	}
	public function link_edit(){
		return 'admin.php?page=support_hub_settings&tab=google&shub_google_id='.$this->get('shub_google_id');
	}
	public function link_refresh(){
		return 'admin.php?page=support_hub_settings&tab=google&do_google_refresh=1&shub_google_id='.$this->get('shub_google_id');
	}
	public function link_new_message(){
		return 'admin.php?page=support_hub_main&shub_google_id='.$this->get('shub_google_id').'&shub_google_message_id=new';
	}

}




class shub_google_message{

	public function __construct($google_account = false, $shub_google_message_id = false){
		$this->google_account = $google_account;
		$this->load($shub_google_message_id);
	}

	/* @var $google_account shub_google_account */
	private $google_account = false;
	private $shub_google_message_id = false; // the current user id in our system.
    private $details = array();

	private function reset(){
		$this->shub_google_message_id = false;
		$this->details = array();
	}

	public function create_new(){
		$this->reset();
		$this->shub_google_message_id = shub_update_insert('shub_google_message_id',false,'shub_google_message',array());
		$this->load($this->shub_google_message_id);
	}

	public function load_by_google_id($google_id, $google_message=false, $debug = false, $force = false){

		if(!$this->google_account || !$this->google_account->get('shub_google_id')){
			return false;
		}
		$this->shub_google_message_id = 0;
		$exists = shub_get_single('shub_google_message',array('shub_google_id','google_message_id'),array($this->google_account->get('shub_google_id'),$google_id));
		if($exists && $exists['google_message_id'] == $google_id){
			$this->load($exists['shub_google_message_id']);
			if($this->shub_google_message_id != $exists['shub_google_message_id']){
				$this->reset(); // shouldn't happen.
			}
			if(!$force && $this->shub_google_message_id == $exists['shub_google_message_id']){
				return $this->shub_google_message_id;
			}
		}
		if(!$google_message || $force){
			// todo: Get google_message from api.
			$original_message = $google_message;
			$google_message = $this->google_account->api_get_comment($google_id);
			if(!$google_message){
				// this must be a private message - don't support these at the moment.
				// todo - pull this information from the javascript api:
				//$google_message = $original_message;
			}

		}
		if($google_message && isset($google_message['id']) && $google_message['id']){


			$content = utf8_decode(isset($google_message['object']) && isset($google_message['object']['content']) ? trim($google_message['object']['content']) : '');
			$content = substr($content,0,strlen($content)-1);

			// todo: unarchive tweet if the retweet or fav action happens
			$this->shub_google_message_id = shub_update_insert('shub_google_message_id',$this->shub_google_message_id, 'shub_google_message', array(
				'shub_google_id' => $this->google_account->get('shub_google_id'),
				'google_message_id' => $google_message['id'],
				'google_actor' => isset($google_message['actor']) ? json_encode($google_message['actor']) : '',
				'google_type' => isset($google_message['object']) && isset($google_message['object']['objectType']) ? $google_message['object']['objectType'] : '',
				'summary' => $content,
				'comment_count' => isset($google_message['object']) && isset($google_message['object']['replies']) && isset($google_message['object']['replies']['totalItems']) ? $google_message['object']['replies']['totalItems'] : 0,
				'share_count' => isset($google_message['object']) && isset($google_message['object']['reshares']) && isset($google_message['object']['reshares']['totalItems']) ? $google_message['object']['reshares']['totalItems'] : 0,
				'plusone_count' => isset($google_message['object']) && isset($google_message['object']['plusoners']) && isset($google_message['object']['plusoners']['totalItems']) ? $google_message['object']['plusoners']['totalItems'] : 0,
				'message_time' => isset($google_message['updated']) ? strtotime($google_message['updated']) : '',
				'data' => json_encode($google_message),
			));
			$this->load($this->shub_google_message_id);
			$this->api_import_comments_and_stuff();
		}

		return $this->shub_google_message_id;
	}

    public function load($shub_google_message_id = false){
	    if(!$shub_google_message_id)$shub_google_message_id = $this->shub_google_message_id;
	    $this->reset();
	    $this->shub_google_message_id = $shub_google_message_id;
        if($this->shub_google_message_id){
            $this->details = shub_get_single('shub_google_message','shub_google_message_id',$this->shub_google_message_id);
	        if(!is_array($this->details) || !isset($this->details['shub_google_message_id']) || $this->details['shub_google_message_id'] != $this->shub_google_message_id){
		        $this->reset();
		        return false;
	        }
        }
        foreach($this->details as $key=>$val){
            $this->{$key} = $val;
        }
	    if(!$this->google_account && $this->get('shub_google_id')){
		    $this->google_account = new shub_google_account($this->get('shub_google_id'));
	    }
        return $this->shub_google_message_id;
    }

	public function api_import_comments_and_stuff($debug=false){
		if($this->shub_google_message_id){
			if($this->comment_count > 0){
				$comments = $this->google_account->api_get_activity_comments($this->google_message_id);
				if($comments && isset($comments['items'])){
					foreach($comments['items'] as $comment){
						// check if we have a new comment past the last updated time on this post.
						$comment_time = strtotime($comment['updated']);
						if($comment_time > $this->message_time){
							// new comment!
							$new_comment = $comment;
							$this->message_time = $comment_time;
							$this->update('message_time',$comment_time);

                            if($this->get('status')!=_shub_MESSAGE_STATUS_HIDDEN) $this->update('status',_shub_MESSAGE_STATUS_UNANSWERED);// move back to inbox as well if archived.

							$this->mark_as_unread();
						}
					}
					$this->update('comments',json_encode($comments['items']));
				}
			}
		}
	}

	public function api_add_reply($options, $debug = false){
		$message = trim($options['message']);
		if(strlen($message)>0){
			$this->google_account->api_login($debug);
			$worked = $this->google_account->api_post_comment_reply($this->google_message_id, $message, $debug);
			$this->load_by_google_id($this->google_message_id, false, $debug, true );

            if($this->get('status')!=_shub_MESSAGE_STATUS_HIDDEN)$this->update('status',_shub_MESSAGE_STATUS_ANSWERED);
			return $worked;
		}
		return false;
	}

	public function get($field){
		return isset($this->{$field}) ? $this->{$field} : false;
	}


    public function update($field,$value){
	    // what fields to we allow? or not allow?
	    if(in_array($field,array('shub_google_message_id')))return;
        if($this->shub_google_message_id){
            $this->{$field} = $value;
            shub_update_insert('shub_google_message_id',$this->shub_google_message_id,'shub_google_message',array(
	            $field => $value,
            ));
        }
    }

	public function parse_links(){
		if(!$this->get('shub_google_message_id'))return;
		// strip out any links in the message and write them to the google_message_link table.
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
					$shub_google_message_link_id = shub_update_insert( 'shub_google_message_link_id', false, 'shub_google_message_link', array(
						'shub_google_message_id' => $this->get('shub_google_message_id'),
						'link' => $url,
					) );
					if($shub_google_message_link_id) {
						$new_link = trailingslashit( get_site_url() );
						$new_link .= strpos( $new_link, '?' ) === false ? '?' : '&';
						$new_link .= _support_hub_GOOGLE_LINK_REWRITE_PREFIX . '=' . $shub_google_message_link_id;
						// basic hash to stop brute force.
						if(defined('AUTH_KEY')){
							$new_link .= ':'.substr(md5(AUTH_KEY.' google link '.$shub_google_message_link_id),1,5);
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
		if($this->shub_google_message_id) {
			shub_delete_from_db( 'shub_google_message', 'shub_google_message_id', $this->shub_google_message_id );
		}
	}

	public function mark_as_read(){
		if($this->shub_google_message_id && get_current_user_id()){
			$sql = "REPLACE INTO `"._support_hub_DB_PREFIX."shub_google_message_read` SET `shub_google_message_id` = ".(int)$this->shub_google_message_id.", `user_id` = ".(int)get_current_user_id().", read_time = ".(int)time();
			shub_query($sql);
		}
	}
	public function mark_as_unread(){
		if($this->shub_google_message_id && get_current_user_id()){
			// for everyone
			shub_delete_from_db( 'shub_google_message_read', 'shub_google_message_id', $this->shub_google_message_id );
		}
	}

	public function get_summary() {
		// who was the last person to contribute to this post? show their details here instead of the 'summary' box maybe?
		$summary = $this->get( 'summary' );
	    if(empty($summary))$summary = __('N/A','support_hub');
	    $return = htmlspecialchars( strlen( $summary ) > 80 ? substr( $summary, 0, 80 ) . '...' : $summary );
		$data = @json_decode($this->get('data'),true);
		//print_r($data);
		$extra = array();
		if($this->get('comment_count') > 0){
			$extra[] = sprintf(__('Comments: %s', 'support_hub'),$this->get('comment_count'));
		}
		if($this->get('share_count') > 0){
			$extra[] = sprintf(__('Reshares: %s', 'support_hub'),$this->get('share_count'));
		}
		if($this->get('plusone_count') > 0){
			$extra[] = sprintf(__('+1: %s', 'support_hub'),$this->get('plusone_count'));
		}
		if(count($extra)){
			$return .= '<br/>( ';
			$return .= implode(', ',$extra);
			$return .= ' )';
		}
		return $return;
	}

	private $can_reply = false;
	public function output_block($level){

		if(!$this->get('shub_google_message_id'))return;

		$google_data = @json_decode($this->get('data'),true);

		$message_from = isset($google_data['actor']) ? $google_data['actor'] : false;

		if($this->get('summary')){
			//echo '<pre>'; print_r($google_data); echo '</pre>';
			?>
			<div class="google_comment" data-id="<?php echo $this->shub_google_message_id;?>">
				<div class="google_comment_picture">
					<?php 
					if($message_from && isset($message_from['image']['url'])){
						$pic = array(
							'image' => $message_from['image']['url'],
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
				<div class="google_comment_header">
					<?php _e('From:'); echo ' '; echo $message_from ? shub_google::format_person($message_from) : 'N/A'; ?>
					<span><?php $time = $this->get('message_time');
					echo $time ? ' @ ' . shub_print_date($time,true) : '';

					if ( $this->get('user_id') ) {
						$user_info = get_userdata($this->get('user_id'));
						echo ' (sent by ' . htmlspecialchars($user_info->display_name) . ')';
						//echo ' (sent by ' . module_user::link_open( $this->get('user_id'), true ) . ')';
					}
					?>
					</span>
				</div>
				<div class="google_comment_body">
					<?php if(isset($google_data['entities']['media']) && is_array($google_data['entities']['media'])){
						foreach($google_data['entities']['media'] as $media) {
							if ( $media['type'] == 'photo' ) {
								?>
								<div class="google_picture">
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
					<div class="google_comment_stats">
						<?php
						$extra = array();
						if($this->get('comment_count') > 0){
							$extra[] = sprintf(__('Comments: %s', 'support_hub'),$this->get('comment_count'));
						}
						if($this->get('share_count') > 0){
							$extra[] = sprintf(__('Reshares: %s', 'support_hub'),$this->get('share_count'));
						}
						if($this->get('plusone_count') > 0){
							$extra[] = sprintf(__('+1: %s', 'support_hub'),$this->get('plusone_count'));
						}
						if(count($extra)){
							echo '<br/>( ';
							echo implode(', ',$extra);
							echo ' )';
						} ?>
					</div>
				</div>
				<div class="google_comment_actions">
					<?php if($this->can_reply){ ?>
						<a href="#" class="google_reply_button"><?php _e('Reply');?></a>
					<?php } ?>
				</div>
			</div>
		<?php } ?>
		<?php if($level == 0){ ?>
			<div class="google_comment_replies">
			<?php
			//if(strpos($google_data['message'],'picture')){
				//echo '<pre>'; print_r($google_data); echo '</pre>';
			//}
			$comments = $this->get_comments();
			if(count($comments)){
				// recursively print out our comments!
				//$comments = array_reverse($comments);
				foreach($comments as $comment){
					$message_from = isset($comment['actor']) ? $comment['actor'] : false;
					?>
					<div class="google_comment">
						<div class="google_comment_picture">
							<?php
							if($message_from && isset($message_from['image']['url'])){
								$pic = array(
									'image' => $message_from['image']['url'],
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
						<div class="google_comment_header">
							<?php _e('From:'); echo ' '; echo $message_from ? shub_google::format_person($message_from) : 'N/A'; ?>
							<span><?php $time = strtotime($comment['updated']);
							echo $time ? ' @ ' . shub_print_date($time,true) : '';
							/*if ( $this->get('user_id') ) {
								$user_info = get_userdata($this->get('user_id'));
								echo ' (sent by ' . htmlspecialchars($user_info->display_name) . ')';
								//echo ' (sent by ' . module_user::link_open( $this->get('user_id'), true ) . ')';
							}*/
							?>
							</span>
						</div>
						<div class="google_comment_body">
							<div>
								<?php echo isset($comment['object']['content']) ? shub_forum_text($comment['object']['content']) : 'N/A';?>
							</div>
						</div>
						<div class="google_comment_actions">
							<?php /*if($this->can_reply){ ?>
								<a href="#" class="google_reply_button"><?php _e('Reply');?></a>
							<?php }*/ ?>
						</div>
					</div>
					<?php
				}
			}
			if($this->can_reply){
				$this->reply_box($level, $message_from);
			}
			?>
			</div>
		<?php
		}



	}
	public function get_comments(){
		return @json_decode($this->get('comments'),true);
	}

	public function full_message_output($can_reply = false){
		$this->can_reply = $can_reply;
		// used in shub_google_list.php to display the full message and its comments


		$this->output_block(0);
	}

	public function reply_box($level=0, $message_from = array()){
		if($this->google_account &&  $this->shub_google_message_id && (int)$this->get('shub_google_id') > 0 && $this->get('shub_google_id') == $this->google_account->get('shub_google_id')) {
			// who are we replying to?
			$google_data = @json_decode($this->get('data'),true);

		$message_from = isset($google_data['actor']) ? $google_data['actor'] : false;
			?>
			<div class="google_comment google_comment_reply_box google_comment_reply_box_level<?php echo $level;?>">
				<div class="google_comment_picture">
					<?php if($message_from && isset($message_from['image']['url'])){
						$pic = array(
							'image' => $message_from['image']['url'],
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
				<div class="google_comment_header">
					<?php echo shub_google::format_person( $message_from ); ?>
				</div>
				<div class="google_comment_reply">
					<textarea placeholder="Write a reply..." class="google_compose_message"><?php
						/*if($message_from && isset($message_from['screen_name']) && $this->get('type') != _GOOGLE_MESSAGE_TYPE_DIRECT){
							echo '@'.htmlspecialchars($message_from['screen_name']).' ';
						}*/
						?></textarea>
					<button data-id="<?php echo (int)$this->shub_google_message_id;?>" data-account-id="<?php echo (int)$this->get('shub_google_id');?>"><?php _e('Send');?></button>
					<div style="clear:both;">
					(debug) <input type="checkbox" name="debug" class="reply-debug" value="1">
						</div>
				</div>
				<div class="google_comment_actions"></div>
			</div>
		<?php
		}else{
			?>
			<div class="google_comment google_comment_reply_box">
				(incorrect settings, please report this bug)
			</div>
			<?php
		}
	}

	public function get_link() {
		return '#';//https://google.com/'.htmlspecialchars($this->google_account->get('google_name')).'/status/'.$this->get('google_message_id');
	}

	private $attachment_name = '';
	public function add_attachment($local_filename){
		if(is_file($local_filename)){
			$this->attachment_name = $local_filename;
		}
	}
	public function send_queued($debug = false){
		if($this->google_account && $this->shub_google_message_id) {
			// send this message out to google.
			// this is run when user is composing a new message from the UI,
			if ( $this->get( 'status' ) == _shub_MESSAGE_STATUS_SENDING )
				return; // dont double up on cron.
			$this->update( 'status', _shub_MESSAGE_STATUS_SENDING );

			$user_post_data = @json_decode($this->get('data'),true);

			if($debug)echo "Sending a new message to google account ID: ".$this->google_account->get('google_name')." <br>\n";
			$result = false;

			if(isset($user_post_data['google_post_type']) && $user_post_data['google_post_type'] == 'picture' && !empty($this->attachment_name) && is_file($this->attachment_name)){
				// we're posting a photo! c
				// todo

			}else{
				if($debug){
					echo "Posting message to api: <br>";
				}
				$this->google_account->api_login($debug);
				$result = $this->google_account->api_post_page_status($this->google_account->get('google_id'),$this->get('summary'), $debug);

			}
			if($debug)echo "API Post Result: <br>\n".var_export($result,true)." <br>\n";
			$post_id = $result && isset($result[0][1][1][0][0][8]) && $result[0][1][1][0][0][8] ? $result[0][1][1][0][0][8] : false;
			if(!$post_id){
				$post_id = $result && isset($result[0][0][1][0][0][8]) && $result[0][0][1][0][0][8] ? $result[0][0][1][0][0][8] : false;
			}
			if($post_id) {
				$this->update('google_message_id',$post_id);
				// reload this message and comments from the graph api.
				$this->load_by_google_id($this->get('google_message_id'),false, $debug, true);
			}else{
				// failed to post message.
				echo 'Failed to send message. Error was (please send this error to support for assistance) <textarea cols="40" rows="200">'.var_export($result,true).'</textarea>';
				// remove from database.
				$this->delete();
				return false;
			}
			// successfully sent, mark is as answered.
			$this->update( 'status', _shub_MESSAGE_STATUS_ANSWERED );
			return true;
		}
		return false;
	}


	public function get_type_pretty() {
		$type = $this->get('google_type');
		switch($type){
			case 'note':
				return 'Page Post';
				break;
			default:
				return ucwords($type);
		}
	}

	public function get_from() {
		if($this->shub_google_message_id){
			$from = array();
			$data = @json_decode($this->get('google_actor'),true);
			if($data && isset($data['id'])){
				$from[$data['id']] = array(
					'screen_name' => isset($data['displayName']) ? $data['displayName'] : '',
					'image' => isset($data['image']['url']) ? $data['image']['url'] : '',
				);
			}
			return $from;
		}
		return array();
	}

	public function link_open(){
		return 'admin.php?page=support_hub_main&shub_google_id='.$this->google_account->get('shub_google_id').'&shub_google_message_id='.$this->shub_google_message_id;
	}


}