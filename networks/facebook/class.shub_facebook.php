<?php

class shub_facebook extends SupportHub_network {


	public function init(){
		if(isset($_GET[_support_hub_FACEBOOK_LINK_REWRITE_PREFIX]) && strlen($_GET[_support_hub_FACEBOOK_LINK_REWRITE_PREFIX]) > 0){
			// check hash
			$bits = explode(':',$_GET[_support_hub_FACEBOOK_LINK_REWRITE_PREFIX]);
			if(defined('AUTH_KEY') && isset($bits[1])){
				$shub_facebook_message_link_id = (int)$bits[0];
				if($shub_facebook_message_link_id > 0){
					$correct_hash = substr(md5(AUTH_KEY.' facebook link '.$shub_facebook_message_link_id),1,5);
					if($correct_hash == $bits[1]){
						// link worked! log a visit and redirect.
						$link = shub_get_single('shub_facebook_message_link','shub_facebook_message_link_id',$shub_facebook_message_link_id);
						if($link){
							if(!preg_match('#^http#',$link['link'])){
								$link['link'] = 'http://'.trim($link['link']);
							}
							shub_update_insert('shub_facebook_message_link_click_id',false,'shub_facebook_message_link_click',array(
								'shub_facebook_message_link_id' => $shub_facebook_message_link_id,
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

		wp_register_style( 'support-hub-facebook-css', plugins_url('networks/facebook/shub_facebook.css',_DTBAKER_SUPPORT_HUB_CORE_FILE_), array(), '1.0.0' );
		wp_enqueue_style( 'support-hub-facebook-css' );
		wp_register_script( 'support-hub-facebook', plugins_url('networks/facebook/shub_facebook.js',_DTBAKER_SUPPORT_HUB_CORE_FILE_), array( 'jquery' ), '1.0.0' );
		wp_enqueue_script( 'support-hub-facebook' );

	}

	public function settings_page(){
		include( dirname(__FILE__) . '/facebook_settings.php');
	}

	public function compose_to(){
		$pages = array();
	    $accounts = $this->get_accounts();
	    if(!count($accounts)){
		    _e('No accounts configured', 'support_hub');
	    }
		foreach ( $accounts as $account ) {
			$facebook_account = new shub_facebook_account( $account['shub_facebook_id'] );
			if($facebook_account->get('import_personal')){
				$me = @json_decode($facebook_account->get('facebook_data'),true);
				if(is_array($me) && isset($me['me']['id'])) {
					echo '<div class="facebook_compose_personal_select">' .
					     '<input type="checkbox" name="compose_facebook_id[' . $account['shub_facebook_id'] . '][personal]" value="1"> ' .
					     '<img src="//graph.facebook.com/' . $me['me']['id'] . '/picture"> ' .
					     '<span>' . htmlspecialchars( $me['me']['name'] ) . ' (personal feed)</span>' .
					     '</div>';
				}
			}
			$pages            = $facebook_account->get( 'pages' );
			foreach ( $pages as $facebook_page_id => $page ) {
				echo '<div class="facebook_compose_page_select">' .
				     '<input type="checkbox" name="compose_facebook_id[' . $account['shub_facebook_id'] . '][page][' . $facebook_page_id . ']" value="1"> ' .
				     '<img src="//graph.facebook.com/' . $facebook_page_id . '/picture"> ' .
				     '<span>' . htmlspecialchars( $page->get( 'page_name' ) ) . ' (page)</span>' .
				     '</div>';
			}
			$groups            = $facebook_account->get( 'groups' );
			foreach ( $groups as $facebook_group_id => $group ) {
				echo '<div class="facebook_compose_group_select">' .
				     '<input type="checkbox" name="compose_facebook_id[' . $account['shub_facebook_id'] . '][group][' . $facebook_group_id . ']" value="1"> ' .
				     '<img src="//graph.facebook.com/' . $facebook_group_id . '/picture"> ' .
				     '<span>' . htmlspecialchars( $group->get( 'group_name' ) ) . ' (group)</span>' .
				     '</div>';
			}
		}
	}
	public function compose_message($defaults){
		?>
		<textarea name="facebook_message" rows="6" cols="50" id="facebook_compose_message"><?php echo isset($defaults['facebook_message']) ? esc_attr($defaults['facebook_message']) : '';?></textarea>
		<?php
	}
	public function compose_type($defaults){
		?>
		<input type="radio" name="facebook_post_type" id="facebook_post_type_wall" value="wall" <?php echo !isset($defaults['facebook_type']) || $defaults['facebook_type'] == 'wall' ? 'checked' : '';?>> <label
		    for="facebook_post_type_wall"> Wall Post </label>
	    <input type="radio" name="facebook_post_type"
	                                                        id="facebook_post_type_link" value="link" <?php echo isset($defaults['facebook_type']) && $defaults['facebook_type'] == 'link' ? 'checked' : '';?>> <label
		    for="facebook_post_type_link"> Link Post </label>
	    <input type="radio" name="facebook_post_type"
	                                                        id="facebook_post_type_picture" value="picture" <?php echo isset($defaults['facebook_type']) && $defaults['facebook_type'] == 'picture' ? 'checked' : '';?>> <label
		    for="facebook_post_type_picture"> Picture Post </label>
	    <table>
		    <tr style="display: none;">
			    <th class="width1">
				    Link
			    </th>
			    <td class="">
				    <input type="text" name="link" value="<?php echo isset($defaults['facebook_link']) ? esc_attr($defaults['facebook_link']) : '';?>" id="message_link_url">
				    <br/><small>eg: http://yoursite.com</small>
				    <div id="facebook_link_loading_message"></div>
				    <span class="facebook-type-link facebook-type-option"></span>
			    </td>
		    </tr>
		    <tr style="display: none;">
			    <th class="width1">
				    Picture
			    </th>
			    <td class="">
				    <input type="text" name="link_picture" value="<?php echo isset($defaults['facebook_link_picture']) ? esc_attr($defaults['facebook_link_picture']) : '';?>">
				    <br/><small>Full URL (eg: http://) to the picture to use for this link preview</small>
				    <span class="facebook-type-link facebook-type-option"></span>
			    </td>
		    </tr>
		    <tr style="display: none;">
			    <th class="width1">
				    Title
			    </th>
			    <td class="">
				    <input type="text" name="link_name" value="<?php echo isset($defaults['facebook_title']) ? esc_attr($defaults['facebook_title']) : '';?>">
				    <br/><small>Title to use instead of the automatically generated one from the Link page</small>
					    <span class="facebook-type-link facebook-type-option"></span></td>
		    </tr>
		    <tr style="display: none;">
			    <th class="width1">
				    Caption
			    </th>
			    <td class="">
				    <input type="text" name="link_caption" value="<?php echo isset($defaults['facebook_caption']) ? esc_attr($defaults['facebook_caption']) : '';?>">
				    <br/><small>Caption to use instead of the automatically generated one from the Link page</small>
				    <span class="facebook-type-link facebook-type-option"></span>
			    </td>
		    </tr>
		    <tr style="display: none;">
			    <th class="width1">
				    Description
			    </th>
			    <td class="">
				    <textarea name="link_description"><?php echo isset($defaults['facebook_description']) ? esc_attr($defaults['facebook_description']) : '';?></textarea>
				    <br/><small>Description to use instead of the automatically generated one from the Link page</small>
				    <span class="facebook-type-link facebook-type-option"></span>
			    </td>
		    </tr>
		    <tr style="display: none;">
			    <th class="width1">
				    Picture
			    </th>
			    <td class="">
				    <input type="file" name="picture" value=""> <span
					    class="facebook-type-picture facebook-type-option"></span></td>
		    </tr>
	    </table>
		<?php
	}


	private $accounts = array();

	private function reset() {
		$this->accounts = array();
	}


	public function get_accounts() {
		$this->accounts = shub_get_multiple( 'shub_facebook', array(), 'shub_facebook_id' );
		return $this->accounts;
	}

	public function get_url_info($url){
		$data = $this->graph_post('',array(
			'id' => $url,
			'scrape' => true,
		));
		return $data;
	}

	public function graph($endpoint, $args=array()){
		$url = 'https://graph.facebook.com/'.$endpoint.'?';
		foreach($args as $key=>$val){
			if($val !== false){
				$url .= $key . '=' . urlencode($val) . '&';
			}
		}
		$data = $this->get_url($url);
		return $data;
	}

	public function graph_post($endpoint, $args=array()){
		$url = 'https://graph.facebook.com/'.$endpoint.'';
		$data = $this->get_url($url, $args);
		return $data;
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
	public function get_paged_data($data,$pagination){

	}

	public static function format_person($data){
		$return = '';
		if($data && isset($data['id'])){
			$return .= '<a href="//facebook.com/'.$data['id'].'" target="_blank">';
		}
		if($data && isset($data['name'])){
			$return .= htmlspecialchars($data['name']);
		}
		if($data && isset($data['id'])){
			$return .= '</a>';
		}
		return $return;
	}

	private $all_messages = false;
	public function load_all_messages($search=array(),$order=array()){
		$sql = "SELECT m.*, m.last_active AS `message_time`, mr.read_time FROM `"._support_hub_DB_PREFIX."shub_facebook_message` m ";
		$sql .= " LEFT JOIN `"._support_hub_DB_PREFIX."shub_facebook_message_read` mr ON m.shub_facebook_message_id = mr.shub_facebook_message_id";
		$sql .= " WHERE 1 ";
		if(isset($search['status']) && $search['status'] !== false){
			$sql .= " AND `status` = ".(int)$search['status'];
		}
		if(isset($search['shub_facebook_page_id']) && $search['shub_facebook_page_id'] !== false){
			$sql .= " AND `shub_facebook_page_id` = ".(int)$search['shub_facebook_page_id'];
		}
		if(isset($search['shub_message_id']) && $search['shub_message_id'] !== false){
			$sql .= " AND `shub_message_id` = ".(int)$search['shub_message_id'];
		}
		if(isset($search['shub_facebook_id']) && $search['shub_facebook_id'] !== false){
			$sql .= " AND `shub_facebook_id` = ".(int)$search['shub_facebook_id'];
		}
		if(isset($search['generic']) && !empty($search['generic'])){
			$sql .= " AND `summary` LIKE '%".mysql_real_escape_string($search['generic'])."%'";
		}
		$sql .= " ORDER BY `last_active` DESC ";
		//$this->all_messages = query($sql);
		global $wpdb;
		$this->all_messages = $wpdb->get_results($sql, ARRAY_A);
		return $this->all_messages;
	}
	public function get_next_message(){
		return !empty($this->all_messages) ? array_shift($this->all_messages) : false;
		/*if(mysql_num_rows($this->all_messages)){
			return mysql_fetch_assoc($this->all_messages);
		}
		return false;*/
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
			$facebook_message = new shub_facebook_message(false, false, $message['shub_facebook_message_id']);
			$data['message'] = $facebook_message;
			$page_or_group = $facebook_message->get('facebook_page_or_group');
			$data['shub_column_account'] .= '<div><img src="'.plugins_url('networks/facebook/facebook.png', _DTBAKER_SUPPORT_HUB_CORE_FILE_).'" class="facebook_icon small"><a href="'.$facebook_message->get_link().'" target="_blank">'.htmlspecialchars( $page_or_group ? $page_or_group->get( 'page_name' ) : 'Feed' ) .'</a></div>';
			$data['shub_column_summary'] .= '<div><img src="'.plugins_url('networks/facebook/facebook.png', _DTBAKER_SUPPORT_HUB_CORE_FILE_).'" class="facebook_icon small"><a href="'.$facebook_message->get_link().'" target="_blank">'.htmlspecialchars( $facebook_message->get_summary() ) .'</a></div>';
			// how many link clicks does this one have?
			$sql = "SELECT count(*) AS `link_clicks` FROM ";
			$sql .= " `"._support_hub_DB_PREFIX."shub_facebook_message` m ";
			$sql .= " LEFT JOIN `"._support_hub_DB_PREFIX."shub_facebook_message_link` ml USING (shub_facebook_message_id) ";
			$sql .= " LEFT JOIN `"._support_hub_DB_PREFIX."shub_facebook_message_link_click` lc USING (shub_facebook_message_link_id) ";
			$sql .= " WHERE 1 ";
			$sql .= " AND m.shub_facebook_message_id = ".(int)$message['shub_facebook_message_id'];
			$sql .= " AND lc.shub_facebook_message_link_id IS NOT NULL ";
			$sql .= " AND lc.user_agent NOT LIKE '%Google%' ";
			$sql .= " AND lc.user_agent NOT LIKE '%Yahoo%' ";
			$sql .= " AND lc.user_agent NOT LIKE '%facebookexternalhit%' ";
			$sql .= " AND lc.user_agent NOT LIKE '%Meta%' ";
			$res = shub_qa1($sql);
			$link_clicks = $res && $res['link_clicks'] ? $res['link_clicks'] : 0;
			$data['shub_column_links'] .= '<div><img src="'.plugins_url('networks/facebook/facebook.png', _DTBAKER_SUPPORT_HUB_CORE_FILE_).'" class="facebook_icon small">'. $link_clicks  .'</div>';
		}
		if(count($messages) && $link_clicks > 0){
			//$data['shub_column_links'] = '<div><img src="'.plugins_url('networks/facebook/facebook.png', _DTBAKER_SUPPORTHUB_CORE_FILE_).'" class="facebook_icon small">'. $link_clicks  .'</div>';
		}
		return $data;

	}


	public function get_unread_count($search=array()){
		if(!get_current_user_id())return 0;
		$sql = "SELECT count(*) AS `unread` FROM `"._support_hub_DB_PREFIX."shub_facebook_message` m ";
		$sql .= " WHERE 1 ";
		$sql .= " AND m.shub_facebook_message_id NOT IN (SELECT mr.shub_facebook_message_id FROM `"._support_hub_DB_PREFIX."shub_facebook_message_read` mr WHERE mr.user_id = '".(int)get_current_user_id()."' AND mr.shub_facebook_message_id = m.shub_facebook_message_id)";
		$sql .= " AND m.`status` = "._shub_MESSAGE_STATUS_UNANSWERED;
		if(isset($search['shub_facebook_page_id']) && $search['shub_facebook_page_id'] !== false){
			$sql .= " AND m.`shub_facebook_page_id` = ".(int)$search['shub_facebook_page_id'];
		}
		if(isset($search['shub_facebook_id']) && $search['shub_facebook_id'] !== false){
			$sql .= " AND m.`shub_facebook_id` = ".(int)$search['shub_facebook_id'];
		}
		$res = shub_qa1($sql);
		return $res ? $res['unread'] : 0;
	}


	public function output_row($message, $settings){
		$facebook_message = new shub_facebook_message(false, false, $message['shub_facebook_message_id']);
		    $comments         = $facebook_message->get_comments();
		?>
		<tr class="<?php echo isset($settings['row_class']) ? $settings['row_class'] : '';?> facebook_message_row <?php echo !isset($message['read_time']) || !$message['read_time'] ? ' message_row_unread' : '';?>"
	        data-id="<?php echo (int) $message['shub_facebook_message_id']; ?>">
		    <td class="shub_column_account">
			    <img src="<?php echo plugins_url('networks/facebook/facebook.png', _DTBAKER_SUPPORT_HUB_CORE_FILE_);?>" class="facebook_icon">
			    <?php $page_or_group = $facebook_message->get('facebook_page_or_group'); ?>
			    <a href="<?php echo $facebook_message->get_link(); ?>" target="_blank"><?php echo htmlspecialchars( $page_or_group ? ($page_or_group->get( 'page_name' ) ? $page_or_group->get( 'page_name' ) : $page_or_group->get('group_name') )  : 'Feed' ); ?></a> <br/>
			    <?php echo htmlspecialchars( $facebook_message->get_type_pretty() ); ?>
		    </td>
		    <td class="shub_column_time"><?php echo shub_print_date( $message['message_time'], true ); ?></td>
		    <td class="shub_column_from">
			    <?php
		        // work out who this is from.
		        $from = $facebook_message->get_from();
			    ?>
			    <div class="shub_from_holder shub_facebook">
			    <div class="shub_from_full">
				    <?php
					foreach($from as $id => $name){
						?>
						<div>
							<a href="//facebook.com/<?php echo $id;?>" target="_blank"><img src="//graph.facebook.com/<?php echo $id;?>/picture" class="shub_from_picture"></a> <?php echo htmlspecialchars($name); ?>
						</div>
						<?php
					} ?>
			    </div>
		        <?php
		        reset($from);
		        echo '<a href="//facebook.com/'.key($from).'" target="_blank">' . '<img src="//graph.facebook.com/'.key($from).'/picture" class="shub_from_picture"></a> ';
		        echo '<span class="shub_from_count">';
		        if(count($from) > 1){
			        echo '+'.(count($from)-1);
		        }
		        echo '</span>';
		        ?>
			    </div>
		    </td>
		    <td class="shub_column_summary">
			    <span style="float:right;">
				    <?php echo count( $comments ) > 0 ? '('.count( $comments ).')' : ''; ?>
			    </span>
			    <div class="facebook_message_summary<?php echo !isset($message['read_time']) || !$message['read_time'] ? ' unread' : '';?>"> <?php
				    $summary = $facebook_message->get_summary();
				    echo $summary;
				    ?>
			    </div>
		    </td>
			<!--<td></td>-->
		    <td nowrap class="shub_column_action">

			        <a href="<?php echo $facebook_message->link_open();?>" class="socialfacebook_message_open shub_modal button" data-modaltitle="<?php echo htmlspecialchars($summary);?>" data-socialfacebookmessageid="<?php echo (int)$facebook_message->get('shub_facebook_message_id');?>"><?php _e( 'Open' );?></a>

				    <?php if($facebook_message->get('status') == _shub_MESSAGE_STATUS_ANSWERED){  ?>
					    <a href="#" class="socialfacebook_message_action  button"
					       data-action="set-unanswered" data-id="<?php echo (int)$facebook_message->get('shub_facebook_message_id');?>"><?php _e( 'Inbox' ); ?></a>
				    <?php }else{ ?>
					    <a href="#" class="socialfacebook_message_action  button"
					       data-action="set-answered" data-id="<?php echo (int)$facebook_message->get('shub_facebook_message_id');?>"><?php _e( 'Archive' ); ?></a>
				    <?php } ?>
		    </td>
	    </tr>
		<?php
	}

	public function init_js(){
		?>
		    ucm.social.facebook.api_url = ajaxurl;
		    ucm.social.facebook.init();
		<?php
	}

	public function handle_process($process, $options = array()){
		switch($process){
			case 'send_shub_message':
				$message_count = 0;
				if(check_admin_referer( 'shub_send-message' ) && isset($options['shub_message_id']) && (int)$options['shub_message_id'] > 0 && isset($_POST['facebook_message']) && !empty($_POST['facebook_message'])){
					// we have a social message id, ready to send!
					// which facebook accounts are we sending too?
					$facebook_accounts = isset($_POST['compose_facebook_id']) && is_array($_POST['compose_facebook_id']) ? $_POST['compose_facebook_id'] : array();
					foreach($facebook_accounts as $facebook_account_id => $send_pages_or_groups){
						foreach($send_pages_or_groups as $page_or_group => $send_page_or_group) {
							$facebook_account = new shub_facebook_account( $facebook_account_id );
							if ( $facebook_account->get( 'shub_facebook_id' ) == $facebook_account_id ) {

								if($page_or_group == 'personal') {

									// push to db! then send.
									$facebook_message = new shub_facebook_message( $facebook_account, false, false );
									$facebook_message->create_new();
									$facebook_message->update( 'shub_message_id', $options['shub_message_id'] );
									$facebook_message->update( 'shub_facebook_id', $facebook_account->get( 'shub_facebook_id' ) );
									$facebook_message->update( 'summary', isset( $_POST['facebook_message'] ) ? $_POST['facebook_message'] : '' );
									if ( isset( $_POST['track_links'] ) && $_POST['track_links'] ) {
										$facebook_message->parse_links();
									}
									$facebook_message->update( 'type', 'personal' );
									$facebook_message->update( 'link', isset( $_POST['link'] ) ? $_POST['link'] : '' );
									$facebook_message->update( 'data', json_encode( $_POST ) );
									$facebook_message->update( 'user_id', get_current_user_id() );
									// do we send this one now? or schedule it later.
									$facebook_message->update( 'status', _shub_MESSAGE_STATUS_PENDINGSEND );
									if ( isset( $options['send_time'] ) && ! empty( $options['send_time'] ) ) {
										// schedule for sending at a different time (now or in the past)
										$facebook_message->update( 'last_active', $options['send_time'] );
									} else {
										// send it now.
										$facebook_message->update( 'last_active', 0 );
									}
									if ( isset( $_FILES['picture']['tmp_name'] ) && is_uploaded_file( $_FILES['picture']['tmp_name'] ) ) {
										$facebook_message->add_attachment( $_FILES['picture']['tmp_name'] );
									}
									$now = time();
									if ( ! $facebook_message->get( 'last_active' ) || $facebook_message->get( 'last_active' ) <= $now ) {
										// send now! otherwise we wait for cron job..
										if ( $facebook_message->send_queued( isset( $_POST['debug'] ) && $_POST['debug'] ) ) {
											$message_count ++;
										}
									} else {
										$message_count ++;
										if ( isset( $_POST['debug'] ) && $_POST['debug'] ) {
											echo "Message will be sent in cron job after " . shub_print_date( $facebook_message->get( 'last_active' ), true );
										}
									}

								}else if($page_or_group == 'page') {
									/* @var $available_pages shub_facebook_page[] */
									$available_pages = $facebook_account->get( 'pages' );
									if ( $send_page_or_group ) {
										foreach ( $send_page_or_group as $facebook_page_id => $tf ) {
											if ( ! $tf ) {
												continue;
											}// shouldnt happen
											// see if this is an available page.
											if ( isset( $available_pages[ $facebook_page_id ] ) ) {
												// push to db! then send.
												$facebook_message = new shub_facebook_message( $facebook_account, $available_pages[ $facebook_page_id ], false );
												$facebook_message->create_new();
												$facebook_message->update( 'shub_facebook_page_id', $available_pages[ $facebook_page_id ]->get( 'shub_facebook_page_id' ) );
												$facebook_message->update( 'shub_message_id', $options['shub_message_id'] );
												$facebook_message->update( 'shub_facebook_id', $facebook_account->get( 'shub_facebook_id' ) );
												$facebook_message->update( 'summary', isset( $_POST['facebook_message'] ) ? $_POST['facebook_message'] : '' );
												if ( isset( $_POST['track_links'] ) && $_POST['track_links'] ) {
													$facebook_message->parse_links();
												}
												$facebook_message->update( 'type', 'page_message' );
												$facebook_message->update( 'link', isset( $_POST['link'] ) ? $_POST['link'] : '' );
												$facebook_message->update( 'data', json_encode( $_POST ) );
												$facebook_message->update( 'user_id', get_current_user_id() );
												// do we send this one now? or schedule it later.
												$facebook_message->update( 'status', _shub_MESSAGE_STATUS_PENDINGSEND );
												if ( isset( $options['send_time'] ) && ! empty( $options['send_time'] ) ) {
													// schedule for sending at a different time (now or in the past)
													$facebook_message->update( 'last_active', $options['send_time'] );
												} else {
													// send it now.
													$facebook_message->update( 'last_active', 0 );
												}
												if ( isset( $_FILES['picture']['tmp_name'] ) && is_uploaded_file( $_FILES['picture']['tmp_name'] ) ) {
													$facebook_message->add_attachment( $_FILES['picture']['tmp_name'] );
												}
												$now = time();
												if ( ! $facebook_message->get( 'last_active' ) || $facebook_message->get( 'last_active' ) <= $now ) {
													// send now! otherwise we wait for cron job..
													if ( $facebook_message->send_queued( isset( $_POST['debug'] ) && $_POST['debug'] ) ) {
														$message_count ++;
													}
												} else {
													$message_count ++;
													if ( isset( $_POST['debug'] ) && $_POST['debug'] ) {
														echo "Message will be sent in cron job after " . shub_print_date( $facebook_message->get( 'last_active' ), true );
													}
												}

											} else {
												// log error?
											}
										}
									}
								}else if($page_or_group == 'group') {
									/* @var $available_groups shub_facebook_group[] */
									$available_groups = $facebook_account->get( 'groups' );
									if ( $send_page_or_group ) {
										foreach ( $send_page_or_group as $facebook_group_id => $tf ) {
											if ( ! $tf ) {
												continue;
											}// shouldnt happen
											// see if this is an available group.
											if ( isset( $available_groups[ $facebook_group_id ] ) ) {
												// push to db! then send.
												$facebook_message = new shub_facebook_message( $facebook_account, $available_groups[ $facebook_group_id ], false );
												$facebook_message->create_new();
												$facebook_message->update( 'shub_facebook_group_id', $available_groups[ $facebook_group_id ]->get( 'shub_facebook_group_id' ) );
												$facebook_message->update( 'shub_message_id', $options['shub_message_id'] );
												$facebook_message->update( 'shub_facebook_id', $facebook_account->get( 'shub_facebook_id' ) );
												$facebook_message->update( 'summary', isset( $_POST['facebook_message'] ) ? $_POST['facebook_message'] : '' );
												if ( isset( $_POST['track_links'] ) && $_POST['track_links'] ) {
													$facebook_message->parse_links();
												}
												$facebook_message->update( 'type', 'group_message' );
												$facebook_message->update( 'link', isset( $_POST['link'] ) ? $_POST['link'] : '' );
												$facebook_message->update( 'data', json_encode( $_POST ) );
												$facebook_message->update( 'user_id', get_current_user_id() );
												// do we send this one now? or schedule it later.
												$facebook_message->update( 'status', _shub_MESSAGE_STATUS_PENDINGSEND );
												if ( isset( $options['send_time'] ) && ! empty( $options['send_time'] ) ) {
													// schedule for sending at a different time (now or in the past)
													$facebook_message->update( 'last_active', $options['send_time'] );
												} else {
													// send it now.
													$facebook_message->update( 'last_active', 0 );
												}
												if ( isset( $_FILES['picture']['tmp_name'] ) && is_uploaded_file( $_FILES['picture']['tmp_name'] ) ) {
													$facebook_message->add_attachment( $_FILES['picture']['tmp_name'] );
												}
												$now = time();
												if ( ! $facebook_message->get( 'last_active' ) || $facebook_message->get( 'last_active' ) <= $now ) {
													// send now! otherwise we wait for cron job..
													if ( $facebook_message->send_queued( isset( $_POST['debug'] ) && $_POST['debug'] ) ) {
														$message_count ++;
													}
												} else {
													$message_count ++;
													if ( isset( $_POST['debug'] ) && $_POST['debug'] ) {
														echo "Message will be sent in cron job after " . shub_print_date( $facebook_message->get( 'last_active' ), true );
													}
												}

											} else {
												// log error?
											}
										}
									}
								}
							}
						}
					}
				}
				return $message_count;
				break;
			case 'save_facebook':
				$shub_facebook_id = isset($_REQUEST['shub_facebook_id']) ? (int)$_REQUEST['shub_facebook_id'] : 0;
				if(check_admin_referer( 'save-facebook'.$shub_facebook_id )) {
					$facebook = new shub_facebook_account( $shub_facebook_id );
					if ( isset( $_POST['butt_delete'] ) ) {
						$facebook->delete();
						$redirect = 'admin.php?page=support_hub_settings&tab=facebook';
					} else {
						$data = $_POST;
						if ( isset( $_POST['butt_save_reconnect'] ) ) {
							// clear access tokens for a fresh re-login
							$data['facebook_token'] = '';
							$data['machine_id']     = '';
						}
						$facebook->save_data( $data );
						$shub_facebook_id = $facebook->get( 'shub_facebook_id' );
						if ( isset( $_POST['butt_save_reconnect'] ) ) {
							$redirect = $facebook->link_connect();
						} else {
							$redirect = $facebook->link_edit();
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
			case 'fb_url_info':
				if (!headers_sent())header('Content-type: text/javascript');
				$url = isset($_REQUEST['url']) ? $_REQUEST['url'] : false;
		        if(strlen($url) > 4 && preg_match('#https?://#',$url)){
			        // pass this into graph api debugger to get some information back about the URL
			        $facebook = new shub_facebook();
			        $data = $facebook->get_url_info($url);
			        // return the data formatted in json ready to be added into the relevant input boxes.
			        $data['link_picture'] = isset($data['image'][0]['url']) ? $data['image'][0]['url'] : '';
			        $data['link_name'] = isset($data['title']) ? $data['title'] : '';
			        $data['link_caption'] = isset($data['caption']) ? $data['caption'] : '';
			        $data['link_description'] = isset($data['description']) ? $data['description'] : '';
			        echo json_encode($data);
		        }
		        exit;
				break;
			case 'send-message-reply':
				if (!headers_sent())header('Content-type: text/javascript');
				if(isset($_REQUEST['facebook_id']) && !empty($_REQUEST['facebook_id']) && isset($_REQUEST['id']) && (int)$_REQUEST['id'] > 0) {
					$shub_facebook_message = new shub_facebook_message( false, false, $_REQUEST['id'] );
					if($shub_facebook_message->get('shub_facebook_message_id') == $_REQUEST['id']){
						$return  = array();
						$message = isset( $_POST['message'] ) && $_POST['message'] ? $_POST['message'] : '';
						$facebook_id = isset( $_REQUEST['facebook_id'] ) && $_REQUEST['facebook_id'] ? $_REQUEST['facebook_id'] : false;
						$debug = isset( $_POST['debug'] ) && $_POST['debug'] ? $_POST['debug'] : false;
						if ( $message ) {
							if($debug)ob_start();
							$shub_facebook_message->send_reply( $facebook_id, $message, $debug );
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
				if(isset($_REQUEST['socialfacebookmessageid']) && (int)$_REQUEST['socialfacebookmessageid'] > 0) {
					$shub_facebook_message = new shub_facebook_message( false, false, $_REQUEST['socialfacebookmessageid'] );
					if($shub_facebook_message->get('shub_facebook_message_id') == $_REQUEST['socialfacebookmessageid']){

						$shub_facebook_id = $shub_facebook_message->get('facebook_account')->get('shub_facebook_id');
						$shub_facebook_message_id = $shub_facebook_message->get('shub_facebook_message_id');
						include( trailingslashit( $support_hub_wp->dir ) . 'networks/facebook/facebook_message.php');
					}

				}
				break;
			case 'set-answered':
				if (!headers_sent())header('Content-type: text/javascript');
				if(isset($_REQUEST['shub_facebook_message_id']) && (int)$_REQUEST['shub_facebook_message_id'] > 0){
					$shub_facebook_message = new shub_facebook_message(false, false, $_REQUEST['shub_facebook_message_id']);
					if($shub_facebook_message->get('shub_facebook_message_id') == $_REQUEST['shub_facebook_message_id']){
						$shub_facebook_message->update('status',_shub_MESSAGE_STATUS_ANSWERED);
						?>
						jQuery('.socialfacebook_message_action[data-id=<?php echo (int)$shub_facebook_message->get('shub_facebook_message_id'); ?>]').parents('tr').first().hide();
						<?php
					}
				}
				break;
			case 'set-unanswered':
				if (!headers_sent())header('Content-type: text/javascript');
				if(isset($_REQUEST['shub_facebook_message_id']) && (int)$_REQUEST['shub_facebook_message_id'] > 0){
					$shub_facebook_message = new shub_facebook_message(false, false, $_REQUEST['shub_facebook_message_id']);
					if($shub_facebook_message->get('shub_facebook_message_id') == $_REQUEST['shub_facebook_message_id']){
						$shub_facebook_message->update('status',_shub_MESSAGE_STATUS_UNANSWERED);
						?>
						jQuery('.socialfacebook_message_action[data-id=<?php echo (int)$shub_facebook_message->get('shub_facebook_message_id'); ?>]').parents('tr').first().hide();
						<?php
					}
				}
				break;
		}
		return false;
	}

	public function run_cron( $debug = false ){
		if($debug)echo "Starting Facebook Cron Job \n";
		$accounts = $this->get_accounts();
		foreach($accounts as $account){
			$shub_facebook_account = new shub_facebook_account( $account['shub_facebook_id'] );
			$pages = $shub_facebook_account->get('pages');
			/* @var $pages shub_facebook_page[] */
			foreach($pages as $page){
				$page->graph_load_latest_page_data($debug);
				$page->run_cron($debug);
			}
		}
		if($debug)echo "Finished Facebook Cron Job \n";
	}

	public function get_install_sql(){

		global $wpdb;

		$sql = <<< EOT

CREATE TABLE {$wpdb->prefix}shub_facebook (
  shub_facebook_id int(11) NOT NULL AUTO_INCREMENT,
  facebook_name varchar(50) NOT NULL,
  last_checked int(11) NOT NULL DEFAULT '0',
  last_message int(11) NOT NULL DEFAULT '0',
  facebook_data text NOT NULL,
  facebook_token varchar(255) NOT NULL,
  facebook_app_id varchar(255) NOT NULL,
  facebook_app_secret varchar(255) NOT NULL,
  import_personal int(1) NOT NULL DEFAULT '0',
  machine_id varchar(255) NOT NULL,
  PRIMARY KEY  shub_facebook_id (shub_facebook_id)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;

CREATE TABLE {$wpdb->prefix}shub_facebook_message (
  shub_facebook_message_id int(11) NOT NULL AUTO_INCREMENT,
  shub_facebook_id int(11) NOT NULL,
  shub_message_id int(11) NOT NULL DEFAULT '0',
  shub_facebook_page_id int(11) NOT NULL,
  shub_facebook_group_id int(11) NOT NULL,
  facebook_id varchar(255) NOT NULL,
  summary text NOT NULL,
  last_active int(11) NOT NULL DEFAULT '0',
  comments text NOT NULL,
  type varchar(20) NOT NULL,
  link varchar(255) NOT NULL,
  data text NOT NULL,
  status tinyint(1) NOT NULL DEFAULT '0',
  user_id int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY  shub_facebook_message_id (shub_facebook_message_id),
  KEY shub_facebook_id (shub_facebook_id),
  KEY shub_message_id (shub_message_id),
  KEY last_active (last_active),
  KEY shub_facebook_page_id (shub_facebook_page_id),
  KEY facebook_id (facebook_id),
  KEY status (status)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;


CREATE TABLE {$wpdb->prefix}shub_facebook_message_read (
  shub_facebook_message_id int(11) NOT NULL,
  read_time int(11) NOT NULL DEFAULT '0',
  user_id int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY  shub_facebook_message_id (shub_facebook_message_id,user_id)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;


CREATE TABLE {$wpdb->prefix}shub_facebook_message_comment (
  shub_facebook_message_comment_id int(11) NOT NULL AUTO_INCREMENT,
  shub_facebook_message_id int(11) NOT NULL,
  facebook_id varchar(255) NOT NULL,
  time int(11) NOT NULL,
  message_from text NOT NULL,
  message_to text NOT NULL,
  data text NOT NULL,
  user_id int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY  shub_facebook_message_comment_id (shub_facebook_message_comment_id),
  KEY shub_facebook_message_id (shub_facebook_message_id),
  KEY facebook_id (facebook_id)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;


CREATE TABLE {$wpdb->prefix}shub_facebook_message_link (
  shub_facebook_message_link_id int(11) NOT NULL AUTO_INCREMENT,
  shub_facebook_message_id int(11) NOT NULL DEFAULT '0',
  link varchar(255) NOT NULL,
  PRIMARY KEY  shub_facebook_message_link_id (shub_facebook_message_link_id),
  KEY shub_facebook_message_id (shub_facebook_message_id)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;

CREATE TABLE {$wpdb->prefix}shub_facebook_message_link_click (
  shub_facebook_message_link_click_id int(11) NOT NULL AUTO_INCREMENT,
  shub_facebook_message_link_id int(11) NOT NULL DEFAULT '0',
  click_time int(11) NOT NULL,
  ip_address varchar(20) NOT NULL,
  user_agent varchar(100) NOT NULL,
  url_referrer varchar(255) NOT NULL,
  PRIMARY KEY  shub_facebook_message_link_click_id (shub_facebook_message_link_click_id),
  KEY shub_facebook_message_link_id (shub_facebook_message_link_id)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;



CREATE TABLE {$wpdb->prefix}shub_facebook_page (
  shub_facebook_page_id int(11) NOT NULL AUTO_INCREMENT,
  shub_facebook_id int(11) NOT NULL,
  page_name varchar(50) NOT NULL,
  last_message int(11) NOT NULL DEFAULT '0',
  last_checked int(11) NOT NULL,
  page_id varchar(255) NOT NULL,
  facebook_token varchar(255) NOT NULL,
  PRIMARY KEY  shub_facebook_page_id (shub_facebook_page_id),
  KEY shub_facebook_id (shub_facebook_id)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;

CREATE TABLE {$wpdb->prefix}shub_facebook_group (
  shub_facebook_group_id int(11) NOT NULL AUTO_INCREMENT,
  shub_facebook_id int(11) NOT NULL,
  group_name varchar(50) NOT NULL,
  last_message int(11) NOT NULL DEFAULT '0',
  last_checked int(11) NOT NULL,
  group_id varchar(255) NOT NULL,
  administrator int(2) NOT NULL DEFAULT '0',
  PRIMARY KEY  shub_facebook_group_id (shub_facebook_group_id),
  KEY shub_facebook_id (shub_facebook_id)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;

EOT;
		return $sql;

	}

}
