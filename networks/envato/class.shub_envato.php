<?php

class shub_envato extends SupportHub_network {


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
		if(isset($_GET[_support_hub_envato_LINK_REWRITE_PREFIX]) && strlen($_GET[_support_hub_envato_LINK_REWRITE_PREFIX]) > 0){
			// check hash
			$bits = explode(':',$_GET[_support_hub_envato_LINK_REWRITE_PREFIX]);
			if(defined('AUTH_KEY') && isset($bits[1])){
				$shub_envato_message_link_id = (int)$bits[0];
				if($shub_envato_message_link_id > 0){
					$correct_hash = substr(md5(AUTH_KEY.' envato link '.$shub_envato_message_link_id),1,5);
					if($correct_hash == $bits[1]){
						// link worked! log a visit and redirect.
						$link = shub_get_single('shub_envato_message_link','shub_envato_message_link_id',$shub_envato_message_link_id);
						if($link){
							if(!preg_match('#^http#',$link['link'])){
								$link['link'] = 'http://'.trim($link['link']);
							}
							shub_update_insert('shub_envato_message_link_click_id',false,'shub_envato_message_link_click',array(
								'shub_envato_message_link_id' => $shub_envato_message_link_id,
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
		return '<img src="'.plugins_url('networks/envato/envato-logo.png', _DTBAKER_SUPPORT_HUB_CORE_FILE_).'" class="shub_friendly_icon">';
	}

	public function init_menu(){

	}

	public function page_assets($from_master=false){
		if(!$from_master)SupportHub::getInstance()->inbox_assets();

		wp_register_style( 'support-hub-envato-css', plugins_url('networks/envato/shub_envato.css',_DTBAKER_SUPPORT_HUB_CORE_FILE_), array(), '1.0.0' );
		wp_enqueue_style( 'support-hub-envato-css' );
		wp_register_script( 'support-hub-envato', plugins_url('networks/envato/shub_envato.js',_DTBAKER_SUPPORT_HUB_CORE_FILE_), array( 'jquery' ), '1.0.0' );
		wp_enqueue_script( 'support-hub-envato' );

	}

	public function settings_page(){
		include( dirname(__FILE__) . '/envato_settings.php');
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
			$envato_account = new shub_envato_account( $account['shub_envato_id'] );
			echo '<div class="envato_compose_account_select">' .
				     '<input type="checkbox" name="compose_envato_id[' . $account['shub_envato_id'] . '][share]" value="1"> ' .
				     ($envato_account->get_picture() ? '<img src="'.$envato_account->get_picture().'">' : '' ) .
				     '<span>' . htmlspecialchars( $envato_account->get( 'envato_name' ) ) . ' (status update)</span>' .
				     '</div>';
			/*echo '<div class="envato_compose_account_select">' .
				     '<input type="checkbox" name="compose_envato_id[' . $account['shub_envato_id'] . '][blog]" value="1"> ' .
				     ($envato_account->get_picture() ? '<img src="'.$envato_account->get_picture().'">' : '' ) .
				     '<span>' . htmlspecialchars( $envato_account->get( 'envato_name' ) ) . ' (blog post)</span>' .
				     '</div>';*/
			$items            = $envato_account->get( 'items' );
			foreach ( $items as $envato_item_id => $item ) {
				echo '<div class="envato_compose_account_select">' .
				     '<input type="checkbox" name="compose_envato_id[' . $account['shub_envato_id'] . '][' . $envato_item_id . ']" value="1"> ' .
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


	public function get_accounts() {
		$this->accounts = shub_get_multiple( 'shub_envato', array(), 'shub_envato_id' );
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

	public function load_all_messages($search=array(),$order=array(),$limit_batch=0){
		$this->search_params = $search;
		$this->search_order = $order;
		$this->search_limit = $limit_batch;

		$sql = "SELECT m.*, m.last_active AS `message_time`, mr.read_time FROM `"._support_hub_DB_PREFIX."shub_envato_message` m ";
		$sql .= " LEFT JOIN `"._support_hub_DB_PREFIX."shub_envato_message_read` mr ON ( m.shub_envato_message_id = mr.shub_envato_message_id AND mr.user_id = ".get_current_user_id()." )";
		$sql .= " LEFT JOIN `"._support_hub_DB_PREFIX."shub_envato_item` ei ON ( m.shub_envato_item_id = ei.shub_envato_item_id )";
		$sql .= " WHERE 1 ";
		if(isset($search['status']) && $search['status'] !== false){
			$sql .= " AND `status` = ".(int)$search['status'];
		}
		if(isset($search['shub_envato_item_id']) && $search['shub_envato_item_id'] !== false){
			$sql .= " AND m.`shub_envato_item_id` = ".(int)$search['shub_envato_item_id'];
		}
		if(isset($search['shub_product_id']) && (int)$search['shub_product_id']){
			$sql .= " AND `shub_product_id` = ".(int)$search['shub_product_id'];
		}
		if(isset($search['shub_message_id']) && $search['shub_message_id'] !== false){
			$sql .= " AND `shub_message_id` = ".(int)$search['shub_message_id'];
		}
		if(isset($search['shub_envato_id']) && $search['shub_envato_id'] !== false){
			$sql .= " AND `shub_envato_id` = ".(int)$search['shub_envato_id'];
		}
		if(isset($search['generic']) && !empty($search['generic'])){
			// todo: search item comments too.. not just title (first comment) and summary (last comment)
			$sql .= " AND (`title` LIKE '%".mysql_real_escape_string($search['generic'])."%'";
			$sql .= " OR `summary` LIKE '%".mysql_real_escape_string($search['generic'])."%' )";
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
			$envato_message = new shub_envato_message(false, false, $message['shub_envato_message_id']);
			$data['message'] = $envato_message;
			$data['shub_column_account'] .= '<div><img src="'.plugins_url('networks/envato/envato-logo.png', _DTBAKER_SUPPORT_HUB_CORE_FILE_).'" class="envato_icon small"><a href="'.$envato_message->get_link().'" target="_blank">'.htmlspecialchars( $envato_message->get('envato_item') ? $envato_message->get('envato_item')->get( 'item_name' ) : 'Share' ) .'</a></div>';
			$data['shub_column_summary'] .= '<div><img src="'.plugins_url('networks/envato/envato-logo.png', _DTBAKER_SUPPORT_HUB_CORE_FILE_).'" class="envato_icon small"><a href="'.$envato_message->get_link().'" target="_blank">'.htmlspecialchars( $envato_message->get_summary() ) .'</a></div>';
			// how many link clicks does this one have?
			$sql = "SELECT count(*) AS `link_clicks` FROM ";
			$sql .= " `"._support_hub_DB_PREFIX."shub_envato_message` m ";
			$sql .= " LEFT JOIN `"._support_hub_DB_PREFIX."shub_envato_message_link` ml USING (shub_envato_message_id) ";
			$sql .= " LEFT JOIN `"._support_hub_DB_PREFIX."shub_envato_message_link_click` lc USING (shub_envato_message_link_id) ";
			$sql .= " WHERE 1 ";
			$sql .= " AND m.shub_envato_message_id = ".(int)$message['shub_envato_message_id'];
			$sql .= " AND lc.shub_envato_message_link_id IS NOT NULL ";
			$sql .= " AND lc.user_agent NOT LIKE '%Google%' ";
			$sql .= " AND lc.user_agent NOT LIKE '%Yahoo%' ";
			$sql .= " AND lc.user_agent NOT LIKE '%envatoexternalhit%' ";
			$sql .= " AND lc.user_agent NOT LIKE '%Meta%' ";
			$res = shub_qa1($sql);
			$link_clicks = $res && $res['link_clicks'] ? $res['link_clicks'] : 0;
			$data['shub_column_links'] .= '<div><img src="'.plugins_url('networks/envato/envato-logo.png', _DTBAKER_SUPPORT_HUB_CORE_FILE_).'" class="envato_icon small">'. $link_clicks  .'</div>';
		}
		if(count($messages) && $link_clicks > 0){
			//$data['shub_column_links'] = '<div><img src="'.plugins_url('networks/envato/envato-logo.png', _DTBAKER_SUPPORTHUB_CORE_FILE_).'" class="envato_icon small">'. $link_clicks  .'</div>';
		}
		return $data;

	}


	public function get_unread_count($search=array()){
		if(!get_current_user_id())return 0;
		$sql = "SELECT count(*) AS `unread` FROM `"._support_hub_DB_PREFIX."shub_envato_message` m ";
		$sql .= " WHERE 1 ";
		$sql .= " AND m.shub_envato_message_id NOT IN (SELECT mr.shub_envato_message_id FROM `"._support_hub_DB_PREFIX."shub_envato_message_read` mr WHERE mr.user_id = '".(int)get_current_user_id()."' AND mr.shub_envato_message_id = m.shub_envato_message_id)";
		$sql .= " AND m.`status` = "._shub_MESSAGE_STATUS_UNANSWERED;
		if(isset($search['shub_envato_item_id']) && $search['shub_envato_item_id'] !== false){
			$sql .= " AND m.`shub_envato_item_id` = ".(int)$search['shub_envato_item_id'];
		}
		if(isset($search['shub_envato_id']) && $search['shub_envato_id'] !== false){
			$sql .= " AND m.`shub_envato_id` = ".(int)$search['shub_envato_id'];
		}
		$res = shub_qa1($sql);
		return $res ? $res['unread'] : 0;
	}


	public function output_row($message){
		$envato_message = new shub_envato_message(false, false, $message['shub_envato_message_id']);
	    $messages         = $envato_message->get_comments();
		$return = array();

		ob_start();
		?>
			<img src="<?php echo plugins_url('networks/envato/envato-logo.png', _DTBAKER_SUPPORT_HUB_CORE_FILE_);?>" class="envato_icon">
		    <a href="<?php echo $envato_message->get_link(); ?>"
	           target="_blank"><?php
		    echo htmlspecialchars( $envato_message->get('envato_account') ? $envato_message->get('envato_account')->get( 'envato_name' ) : 'Item' ); ?></a> <br/>
		    <?php echo htmlspecialchars( $envato_message->get_type_pretty() ); ?>
		<?php
		$return['shub_column_account'] = ob_get_clean();

		ob_start();
		$shub_product_id = $envato_message->get('envato_item')->get('shub_product_id');
		if($shub_product_id) {
			$shub_product = new SupportHubProduct();
			$shub_product->load($shub_product_id);
			$product_data = $shub_product->get('product_data');
			if(!empty($product_data['image'])){
				?>
				<img src="<?php echo $product_data['image'];?>" class="envato_icon">
			<?php } ?>
			<?php if(!empty($product_data['url'])){ ?>
				<a href="<?php echo $product_data['url']; ?>" target="_blank"><?php echo htmlspecialchars( $shub_product->get('product_name') ); ?></a>
			<?php
			}else{
				?> <?php echo htmlspecialchars( $shub_product->get('product_name') ); ?> <?php
			}
		}
		$return['shub_column_product'] = ob_get_clean();

		$return['shub_column_time'] = shub_print_date( $message['message_time'], true );

		ob_start();
        // work out who this is from.
        $from = $envato_message->get_from();
	    ?>
	    <div class="shub_from_holder shub_envato">
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
	    <?php
		$return['shub_column_from'] = ob_get_clean();

		ob_start();
		?>
		<span style="float:right;">
		    <?php echo count( $messages ) > 0 ? '('.count( $messages ).')' : ''; ?>
	    </span>
	    <div class="envato_message_summary<?php echo !isset($message['read_time']) || !$message['read_time'] ? ' unread' : '';?>"> <?php
		    // todo - pull in comments here, not just title/summary
		    // todo - style customer and admin replies differently (eg <em> so we can easily see)
		    $title = strip_tags($envato_message->get( 'title' ));
			$summary = strip_tags($envato_message->get( 'summary' ));
		    echo htmlspecialchars( strlen( $title ) > 80 ? substr( $title, 0, 80 ) . '...' : $title ) . ($summary!=$title ? '<br/>' .htmlspecialchars( strlen( $summary ) > 80 ? substr( $summary, 0, 80 ) . '...' : $summary ) : '');
		    ?>
	    </div>
		<?php
		$return['shub_column_summary'] = ob_get_clean();

		ob_start();
		?>
		<a href="<?php echo $envato_message->link_open();?>" class="socialenvato_message_open shub_modal button" data-modaltitle="<?php echo htmlspecialchars($title);?>" data-socialenvatomessageid="<?php echo (int)$envato_message->get('shub_envato_message_id');?>"><?php _e( 'Open' );?></a>
	    <?php if($envato_message->get('status') == _shub_MESSAGE_STATUS_ANSWERED){  ?>
		    <a href="#" class="socialenvato_message_action shub_message_action button"
		       data-action="set-unanswered" data-post="<?php echo esc_attr(json_encode(array(
                'network' => 'envato',
                'shub_envato_message_id' => $envato_message->get('shub_envato_message_id'),
            )));;?>"><?php _e( 'Inbox' ); ?></a>
	    <?php }else{ ?>
		    <a href="#" class="socialenvato_message_action shub_message_action button"
		       data-action="set-answered" data-post="<?php echo esc_attr(json_encode(array(
                'network' => 'envato',
                'shub_envato_message_id' => $envato_message->get('shub_envato_message_id'),
            )));?>"><?php _e( 'Archive' ); ?></a>
	    <?php } ?>
		<?php
		$return['shub_column_action'] = ob_get_clean();

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
						if($envato_account->get('shub_envato_id') == $envato_account_id){
							/* @var $available_items shub_envato_item[] */
				            $available_items = $envato_account->get('items');
							if($send_items){
							    foreach($send_items as $envato_item_id => $tf){
								    if(!$tf)continue;// shouldnt happen
								    switch($envato_item_id){
									    case 'share':
										    // doing a status update to this envato account
											$envato_message = new shub_envato_message($envato_account, false, false);
										    $envato_message->create_new();
										    $envato_message->update('shub_envato_item_id',0);
							                $envato_message->update('shub_message_id',$options['shub_message_id']);
										    $envato_message->update('shub_envato_id',$envato_account->get('shub_envato_id'));
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
										    if(isset($available_items[$envato_item_id])){
											    // push to db! then send.
											    $envato_message = new shub_envato_message($envato_account, $available_items[$envato_item_id], false);
											    $envato_message->create_new();
											    $envato_message->update('shub_envato_item_id',$available_items[$envato_item_id]->get('shub_envato_item_id'));
								                $envato_message->update('shub_message_id',$options['shub_message_id']);
											    $envato_message->update('shub_envato_id',$envato_account->get('shub_envato_id'));
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
			case 'save_envato':
				$shub_envato_id = isset($_REQUEST['shub_envato_id']) ? (int)$_REQUEST['shub_envato_id'] : 0;
				if(check_admin_referer( 'save-envato'.$shub_envato_id )) {
					$envato = new shub_envato_account( $shub_envato_id );
					if ( isset( $_POST['butt_delete'] ) ) {
						$envato->delete();
						$redirect = 'admin.php?page=support_hub_settings&tab=envato';
					} else {
						$envato->save_data( $_POST );
						$shub_envato_id = $envato->get( 'shub_envato_id' );
						if ( isset( $_POST['butt_save_reconnect'] ) ) {
							$redirect = $envato->link_connect();
						} else {
							$redirect = $envato->link_edit();
						}
					}
					header( "Location: $redirect" );
					exit;
				}

				break;
		}
	}


	public function get_message($envato_account = false, $envato_item = false, $shub_envato_message_id = false){
		return new shub_envato_message($envato_account, $envato_item, $shub_envato_message_id);
	}

	public function handle_ajax($action, $support_hub_wp){
		switch($action){
			case 'request_extra_details':

				if(isset($_REQUEST['network']) && $_REQUEST['network'] == 'envato'){
					if (!headers_sent())header('Content-type: text/javascript');

					$debug = isset( $_POST['debug'] ) && $_POST['debug'] ? $_POST['debug'] : false;
					$response = array();
					$extra_ids = isset($_REQUEST['extra_ids']) && is_array($_REQUEST['extra_ids']) ? $_REQUEST['extra_ids']  : array();
					$network_account_id = isset($_REQUEST['networkAccountId']) ? (int)$_REQUEST['networkAccountId'] : (isset($_REQUEST['network-account-id']) ? (int)$_REQUEST['network-account-id'] : false);
					$network_message_id = isset($_REQUEST['networkMessageId']) ? (int)$_REQUEST['networkMessageId'] : (isset($_REQUEST['network-message-id']) ? (int)$_REQUEST['network-message-id'] : false);
					if(empty($extra_ids)){
						$response['message'] = 'Please request at least one Extra Detail';
					}else{

						$shub_envato_message = new shub_envato_message( false, false, $network_message_id );
						if($network_message_id && $shub_envato_message->get('shub_envato_message_id') == $network_message_id){
							// build the message up
							$message = SupportHubExtra::build_message(array(
								'network' => 'envato',
								'network_account_id' => $network_account_id,
								'network_message_id' => $network_message_id,
								'extra_ids' => $extra_ids,
							));
							$response['message'] = $message;
//							if($debug)ob_start();
//							$shub_envato_message->send_reply( $shub_envato_message->get('envato_id'), $message, $debug );
//							if($debug){
//								$response['message'] = ob_get_clean();
//							}else {
//								$response['redirect'] = 'admin.php?page=support_hub_main';
//							}
						}

					}

					echo json_encode($response);
					exit;
				}
				break;
			case 'modal':
				if(isset($_REQUEST['socialenvatomessageid']) && (int)$_REQUEST['socialenvatomessageid'] > 0) {
					$shub_envato_message = new shub_envato_message( false, false, $_REQUEST['socialenvatomessageid'] );
					if($shub_envato_message->get('shub_envato_message_id') == $_REQUEST['socialenvatomessageid']){

						$shub_envato_id = $shub_envato_message->get('envato_account')->get('shub_envato_id');
						$shub_envato_message_id = $shub_envato_message->get('shub_envato_message_id');
						include( trailingslashit( $support_hub_wp->dir ) . 'networks/envato/envato_message.php');
					}

				}
				break;
		}
		return false;
	}


	public function run_cron( $debug = false ){
		if($debug)echo "Starting envato Cron Job \n";
		$accounts = $this->get_accounts();
		foreach($accounts as $account){
			$shub_envato_account = new shub_envato_account( $account['shub_envato_id'] );
			$shub_envato_account->run_cron($debug);
			$items = $shub_envato_account->get('items');
			/* @var $items shub_envato_item[] */
			foreach($items as $item){
				$item->run_cron($debug);
			}
		}
		if($debug)echo "Finished envato Cron Job \n";
	}

	public function find_other_user_details($user_hints, $current_extension, $message_object){
		$details = array(
			'messages' => array(),
			'user' => array(),
		);
		if(isset($user_hints['envato_username'])){
			$details['user']['username'] = $user_hints['envato_username'];
			$details['user']['url'] = 'http://themeforest.net/user/'.$user_hints['envato_username'];
		}
		// todo: find any purchases here. display those in the details
		if($user_hints['shub_envato_user_id']){
			$shub_user = new SupportHubUser_Envato($user_hints['shub_envato_user_id']);
			$user_data = $shub_user->get('user_data');
			if(isset($user_data['envato_codes'])){
				// these come in from bbPress (and hopefully other places)
				// array of purchase code info
				$details['user']['codes'] = implode(', ',array_keys($user_data['envato_codes']));
				$details['user']['products'] = array();
				foreach($user_data['envato_codes'] as $code=>$purchase_data){
					$details['user']['products'][] = $purchase_data['item_name'];
				}
				$details['user']['products'] = implode(', ',$details['user']['products']);
			}
		}


		// find other envato messages by this user.
		if(isset($user_hints['shub_envato_user_id']) && (int)$user_hints['shub_envato_user_id']>0){
			$comments = shub_get_multiple('shub_envato_message_comment',array(
				'shub_envato_user_id' => (int)$user_hints['shub_envato_user_id']
			),'shub_envato_message_comment_id', '`time` DESC');
			if(is_array($comments)){
				foreach($comments as $comment){
					if(!isset($details['messages']['envato'.$comment['shub_envato_message_id']])){
//						$other_message = new shub_envato_message();
//						$other_message->load($comment['shub_envato_message_id']);
						$details['messages']['envato'.$comment['shub_envato_message_id']] = array(
							'summary' => $comment['message_text'],
							'time' => $comment['time'],
//							'message_status' => $other_message->get('status'),
						);
					}
				}
			}
		}

		return $details;
	}

	public function extra_process_login($network, $network_account_id, $network_message_id, $extra_ids){
		if($network != 'envato')dir('Incorrect network in request_extra_login() - this should not happen');
		$accounts = $this->get_accounts();
		if(!isset($accounts[$network_account_id])){
			die('Invalid account, please report this error.');
		}
		if(false) {
			// for testing without doing a full login:
			$shub_envato_message = new shub_envato_message( false, false, $network_message_id );
			ob_start();
			$shub_envato_message->full_message_output( false );
			return array(
				'message' => ob_get_clean(),
			);
		}

		// check if the user is already logged in via oauth.
		if(!empty($_SESSION['shub_oauth_envato']) && is_array($_SESSION['shub_oauth_envato']) && $_SESSION['shub_oauth_envato']['expires'] > time() && $_SESSION['shub_oauth_envato']['network_account_id'] == $network_account_id && $_SESSION['shub_oauth_envato']['network_message_id'] == $network_message_id){
			// user is logged in
			$shub_envato_message = new shub_envato_message(false, false, $network_message_id);
			if($shub_envato_message->get('envato_account')->get('shub_envato_id') == $network_account_id && $shub_envato_message->get('shub_envato_message_id') == $network_message_id){
				if(isset($_GET['done'])){
					// submission of extra data was successful, clear the token so the user has to login again
					$_SESSION['shub_oauth_envato'] = false;
				}
				ob_start();
				$shub_envato_message->full_message_output(false);
				return array(
					'message' => ob_get_clean(),
				);

			}
		}else{
			// user isn't logged in or the token has expired. show the login url again.
			// find the account.
			if(isset($accounts[$network_account_id])){
				$shub_envato_account = new shub_envato_account($accounts[$network_account_id]['shub_envato_id']);
				// found the account, pull in the API and build the url
				$api = $shub_envato_account->get_api();
				// check if we have a code from a previous redirect:
				if(!empty($_SESSION['shub_oauth_doing_envato']['code'])){
					// grab a token from the api
					$token = $api->get_authentication($_SESSION['shub_oauth_doing_envato']['code']);
					unset($_SESSION['shub_oauth_doing_envato']['code']);
					if(!empty($token) && !empty($token['access_token'])) {
						// good so far, time to check their username matches from the api
						$shub_envato_message = new shub_envato_message(false, false, $network_message_id);
						if($shub_envato_message->get('envato_account')->get('shub_envato_id') == $shub_envato_account->get('shub_envato_id')){
							// grab the details from the envato message:
							$envato_comments = $shub_envato_message->get_comments();
							$first_comment = current($envato_comments);
							if(!empty($first_comment)){
								$comment_data = @json_decode($first_comment['data'],true);
								$api_result = $api->api('market/private/user/username.json', array(), false);

                                $account_data = $shub_envato_account->get('envato_data');

								if($comment_data && $api_result && !empty($api_result['username']) && !empty($comment_data['username']) && (($account_data && isset($account_data['user']['username']) && $api_result['username'] == $account_data['user']['username']) || $comment_data['username'] == $api_result['username'])){ // the dtbaker is here for debugging..
									SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_ERROR,'envato','OAuth Login Success - request extra','User '.$api_result['username'] .' has logged in to provide extra details');
									// todo: load this api result into a shub user, pull in their email address as well so we can find any links to other social networks.
									$api_result_email = $api->api('market/private/user/email.json', array(), false);
									$comment_user = new SupportHubUser_Envato();
									if($api_result_email && !empty($api_result_email['email'])){
										$email = trim(strtolower($api_result_email['email']));
									    $comment_user->load_by( 'user_email', $email);
									    if(!$comment_user->get('shub_envato_user_id')) {
										    // no existing match by email, find a match by username
										    $comment_user->load_by( 'user_username', $api_result['username']);
											if(!$comment_user->get('shub_envato_user_id') || ($comment_user->get('user_email') && $comment_user->get('user_email') != $email)) {
												// no existing match by email or username, pump a new entry in
											    $comment_user->create_new();
										    }
									    }
										$comment_user->update( 'user_email', $email );
										$comment_user->update( 'user_username', $api_result['username'] );
									}else{
										// no email, only username
										$comment_user->load_by( 'user_username', $api_result['username']);
										if(!$comment_user->get('shub_envato_user_id')) {
										    $comment_user->create_new();
										    $comment_user->update( 'user_username', $api_result['username'] );
									    }
									}

									$_SESSION['shub_oauth_envato']            = $token;
									$_SESSION['shub_oauth_envato']['network_account_id']            = $network_account_id;
									$_SESSION['shub_oauth_envato']['network_message_id']            = $network_message_id;
									$_SESSION['shub_oauth_envato']['expires'] = time() + $token['expires_in'];
									$_SESSION['shub_oauth_envato']['shub_envato_user_id'] = $comment_user->get('shub_envato_user_id');
									ob_start();
									$shub_envato_message->full_message_output(false);
									return array(
										'message' => ob_get_clean(),
									);

								}else{
									SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_ERROR,'envato','OAuth Login Fail - Username mismatch','User '.var_export($api_result,true).' tried to login and gain access to ticket message ' .$network_message_id.': '.var_export($comment_data,true));
									echo "Sorry, unable to verify identity. Please submit a new support message if you require assistance. <br><br> ";
									$envato_item_data = $shub_envato_message->get('envato_item')->get('envato_data');
									if($envato_item_data && $envato_item_data['url']) {
										echo '<a href="' . $envato_item_data['url'].'/comments' . (!empty($comment_data['id']) ? '/'.$comment_data['id'] : '') .'">Please click here to return to the Item Comment</a>';
									}
									return false;
								}
							}

						}

					}else{
						echo 'Failed to get access token, please try again and report this error.';
						print_r($token);
					}

				}else {
					$login_url                           = $api->get_authorization_url();
					$_SESSION['shub_oauth_doing_envato'] = array(
						'url' => str_replace('&done','',$_SERVER['REQUEST_URI']),
					);
					?>
					<a href="<?php echo esc_attr( $login_url );?>">Login to Envato</a>
				<?php
				}
			}
		}
		return false;
	}

	public function extra_validate_data($status, $extra, $value, $network, $network_account_id, $network_message_id){
		if(!is_string($value))return $status;
		if(!empty($status['data'])){
			$value = $status['data'];
		}
		$possible_purchase_code = strtolower(preg_replace('#([a-z0-9]{8})-?([a-z0-9]{4})-?([a-z0-9]{4})-?([a-z0-9]{4})-?([a-z0-9]{12})#','$1-$2-$3-$4-$5',$value));
        if(!empty($value) && ($extra->get('extra_name') == 'Purchase Code' || strlen($possible_purchase_code)==36)) { // should be 36
	        // great! we have a purchase code.
	        // see if it validates, if it does we return a success along with extra data that will be saved and eventually displayed
	        $shub_envato_message = new shub_envato_message( false, false, $network_message_id );
	        if(strlen($possible_purchase_code)==36) {
		        $api    = $shub_envato_message->get( 'envato_account' )->get_api();
		        $result = $api->api( 'market/private/user/verify-purchase:' . $possible_purchase_code . '.json' );
	        }else{
		        $result = false;
	        }
	        if($result && !empty($result['verify-purchase'])){
		        // valid purchase code.
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
	public function extra_save_data($extra, $value, $network, $network_account_id, $network_message_id){
		$shub_envato_message = new shub_envato_message( false, false, $network_message_id );
		$shub_envato_user_id = !empty($_SESSION['shub_oauth_envato']['shub_envato_user_id']) ? $_SESSION['shub_oauth_envato']['shub_envato_user_id'] : $shub_envato_message->get('shub_envato_user_id');
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
			$shub_envato_user_id = $comment_user->get('shub_envato_user_id');
		}

		$extra->save_and_link(
			array(
				'extra_value' => is_array($value) && !empty($value['data']) ? $value['data'] : $value,
				'extra_data' => is_array($value) && !empty($value['extra_data']) ? $value['extra_data'] : false,
			),
			$network,
			$network_account_id,
			$network_message_id,
			$shub_envato_user_id
		);

	}
	public function extra_send_message($message, $network, $network_account_id, $network_message_id){
		// save this message in the database as a new comment.
		// set the 'private' flag so we know this comment has been added externally to the API scrape.
		$shub_envato_message = new shub_envato_message( false, false, $network_message_id );
		$existing_comments = $shub_envato_message->get_comments();
		shub_update_insert('shub_envato_message_comment_id',false,'shub_envato_message_comment',array(
		    'shub_envato_message_id' => $shub_envato_message->get('shub_envato_message_id'),
		    'private' => 1,
		    'message_text' => $message,
			'time' => time(),
		    'shub_envato_user_id' => !empty($_SESSION['shub_oauth_envato']['shub_envato_user_id']) ? $_SESSION['shub_oauth_envato']['shub_envato_user_id'] : $shub_envato_message->get('shub_envato_user_id'),
	    ));
		// mark the main message as unread so it appears at the top.
		$shub_envato_message->update('status',_shub_MESSAGE_STATUS_UNANSWERED);
		$shub_envato_message->update('last_active',time());
		// todo: update the 'summary' to reflect this latest message?
		$shub_envato_message->update('summary',$message);

		// todo: post a "Thanks for providing information, we will reply soon" message on Envato comment page

	}

	public function get_install_sql() {

		global $wpdb;

		$sql = <<< EOT



CREATE TABLE {$wpdb->prefix}shub_envato (
  shub_envato_id int(11) NOT NULL AUTO_INCREMENT,
  envato_name varchar(50) NOT NULL,
  last_checked int(11) NOT NULL DEFAULT '0',
  import_stream int(11) NOT NULL DEFAULT '0',
  post_stream int(11) NOT NULL DEFAULT '0',
  envato_data text NOT NULL,
  envato_token varchar(255) NOT NULL,
  envato_cookie mediumtext NOT NULL,
  envato_app_id varchar(255) NOT NULL,
  envato_app_secret varchar(255) NOT NULL,
  machine_id varchar(255) NOT NULL,
  PRIMARY KEY  shub_envato_id (shub_envato_id)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;


CREATE TABLE {$wpdb->prefix}shub_envato_user (
  shub_envato_user_id int(11) NOT NULL AUTO_INCREMENT,
  shub_user_id int(11) NOT NULL DEFAULT '0',
  user_fname varchar(255) NOT NULL,
  user_lname varchar(255) NOT NULL,
  user_username varchar(255) NOT NULL,
  user_email varchar(255) NOT NULL,
  user_data mediumtext NOT NULL,
  user_id_key1 int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY  shub_envato_user_id (shub_envato_user_id),
  KEY user_email (user_email),
  KEY user_username (user_username),
  KEY user_id_key1 (user_id_key1),
  KEY shub_user_id (shub_user_id)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;

CREATE TABLE {$wpdb->prefix}shub_envato_message (
  shub_envato_message_id int(11) NOT NULL AUTO_INCREMENT,
  shub_envato_id int(11) NOT NULL,
  shub_message_id int(11) NOT NULL DEFAULT '0',
  shub_envato_item_id int(11) NOT NULL,
  envato_id varchar(255) NOT NULL,
  summary text NOT NULL,
  title text NOT NULL,
  last_active int(11) NOT NULL DEFAULT '0',
  comments text NOT NULL,
  type varchar(20) NOT NULL,
  link varchar(255) NOT NULL,
  data text NOT NULL,
  status tinyint(1) NOT NULL DEFAULT '0',
  user_id int(11) NOT NULL DEFAULT '0',
  shub_envato_user_id int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY  shub_envato_message_id (shub_envato_message_id),
  KEY shub_envato_id (shub_envato_id),
  KEY shub_message_id (shub_message_id),
  KEY last_active (last_active),
  KEY shub_envato_item_id (shub_envato_item_id),
  KEY envato_id (envato_id),
  KEY shub_envato_user_id (shub_envato_user_id),
  KEY status (status)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;


CREATE TABLE {$wpdb->prefix}shub_envato_message_read (
  shub_envato_message_id int(11) NOT NULL,
  read_time int(11) NOT NULL DEFAULT '0',
  user_id int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY  shub_envato_message_id (shub_envato_message_id,user_id)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;


CREATE TABLE {$wpdb->prefix}shub_envato_message_comment (
  shub_envato_message_comment_id int(11) NOT NULL AUTO_INCREMENT,
  shub_envato_message_id int(11) NOT NULL,
  envato_id varchar(255) NOT NULL,
  time int(11) NOT NULL,
  message_from text NOT NULL,
  message_to text NOT NULL,
  message_text text NOT NULL,
  data text NOT NULL,
  user_id int(11) NOT NULL DEFAULT '0',
  private tinyint(1) NOT NULL DEFAULT '0',
  shub_envato_user_id int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY  shub_envato_message_comment_id (shub_envato_message_comment_id),
  KEY shub_envato_message_id (shub_envato_message_id),
  KEY shub_envato_user_id (shub_envato_user_id),
  KEY envato_id (envato_id)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;


CREATE TABLE {$wpdb->prefix}shub_envato_message_link (
  shub_envato_message_link_id int(11) NOT NULL AUTO_INCREMENT,
  shub_envato_message_id int(11) NOT NULL DEFAULT '0',
  link varchar(255) NOT NULL,
  PRIMARY KEY  shub_envato_message_link_id (shub_envato_message_link_id),
  KEY shub_envato_message_id (shub_envato_message_id)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;

CREATE TABLE {$wpdb->prefix}shub_envato_message_link_click (
  shub_envato_message_link_click_id int(11) NOT NULL AUTO_INCREMENT,
  shub_envato_message_link_id int(11) NOT NULL DEFAULT '0',
  click_time int(11) NOT NULL,
  ip_address varchar(20) NOT NULL,
  user_agent varchar(100) NOT NULL,
  url_referrer varchar(255) NOT NULL,
  PRIMARY KEY  shub_envato_message_link_click_id (shub_envato_message_link_click_id),
  KEY shub_envato_message_link_id (shub_envato_message_link_id)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;

CREATE TABLE {$wpdb->prefix}shub_envato_item (
  shub_envato_item_id int(11) NOT NULL AUTO_INCREMENT,
  shub_envato_id int(11) NOT NULL,
  shub_product_id int(11) NOT NULL DEFAULT '0',
  item_name varchar(50) NOT NULL,
  last_message int(11) NOT NULL DEFAULT '0',
  last_checked int(11) NOT NULL,
  item_id varchar(255) NOT NULL,
  envato_data text NOT NULL,
  PRIMARY KEY  shub_envato_item_id (shub_envato_item_id),
  KEY shub_envato_id (shub_envato_id)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;


EOT;
		return $sql;
	}

}
