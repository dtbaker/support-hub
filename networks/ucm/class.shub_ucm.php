<?php

class shub_ucm extends SupportHub_network {

	public function init(){
		if(isset($_GET[_support_hub_ucm_LINK_REWRITE_PREFIX]) && strlen($_GET[_support_hub_ucm_LINK_REWRITE_PREFIX]) > 0){
			// check hash
			$bits = explode(':',$_GET[_support_hub_ucm_LINK_REWRITE_PREFIX]);
			if(defined('AUTH_KEY') && isset($bits[1])){
				$shub_ucm_message_link_id = (int)$bits[0];
				if($shub_ucm_message_link_id > 0){
					$correct_hash = substr(md5(AUTH_KEY.' ucm link '.$shub_ucm_message_link_id),1,5);
					if($correct_hash == $bits[1]){
						// link worked! log a visit and redirect.
						$link = shub_get_single('shub_ucm_message_link','shub_ucm_message_link_id',$shub_ucm_message_link_id);
						if($link){
							if(!preg_match('#^http#',$link['link'])){
								$link['link'] = 'http://'.trim($link['link']);
							}
							shub_update_insert('shub_ucm_message_link_click_id',false,'shub_ucm_message_link_click',array(
								'shub_ucm_message_link_id' => $shub_ucm_message_link_id,
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

	public function get_friendly_icon(){
		return '<img src="'.plugins_url('networks/ucm/ucm-logo.png', _DTBAKER_SUPPORT_HUB_CORE_FILE_).'" class="shub_friendly_icon">';
	}

	public function page_assets($from_master=false){
		if(!$from_master)SupportHub::getInstance()->inbox_assets();

		wp_register_style( 'support-hub-ucm-css', plugins_url('networks/ucm/shub_ucm.css',_DTBAKER_SUPPORT_HUB_CORE_FILE_), array(), '1.0.0' );
		wp_enqueue_style( 'support-hub-ucm-css' );
		wp_register_script( 'support-hub-ucm', plugins_url('networks/ucm/shub_ucm.js',_DTBAKER_SUPPORT_HUB_CORE_FILE_), array( 'jquery' ), '1.0.0' );
		wp_enqueue_script( 'support-hub-ucm' );

	}

	public function settings_page(){
		include( dirname(__FILE__) . '/ucm_settings.php');
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


	public function get_accounts() {
		$this->accounts = shub_get_multiple( 'shub_ucm', array(), 'shub_ucm_id' );
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

	public static function format_person($data,$ucm_account){
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

	public function load_all_messages($search=array(),$order=array(),$limit_batch=0){
		$this->search_params = $search;
		$this->search_order = $order;
		$this->search_limit = $limit_batch;

		$sql = "SELECT m.*, m.last_active AS `message_time`, mr.read_time FROM `"._support_hub_DB_PREFIX."shub_ucm_message` m ";
		$sql .= " LEFT JOIN `"._support_hub_DB_PREFIX."shub_ucm_message_read` mr ON ( m.shub_ucm_message_id = mr.shub_ucm_message_id AND mr.user_id = ".get_current_user_id()." )";
		$sql .= " LEFT JOIN `"._support_hub_DB_PREFIX."shub_ucm_product` ei ON ( m.shub_ucm_product_id = ei.shub_ucm_product_id )";
		$sql .= " WHERE 1 ";
		if(isset($search['status']) && $search['status'] !== false){
			$sql .= " AND m.`status` = ".(int)$search['status'];
		}
		if(isset($search['shub_ucm_product_id']) && $search['shub_ucm_product_id'] !== false){
			$sql .= " AND m.`shub_ucm_product_id` = ".(int)$search['shub_ucm_product_id'];
		}
		if(isset($search['shub_product_id']) && (int)$search['shub_product_id']){
			$sql .= " AND (m.`shub_product_id` = ".(int)$search['shub_product_id'];
			$sql .= " OR ei.`shub_product_id` = ".(int)$search['shub_product_id'] . " )";
		}
		if(isset($search['shub_message_id']) && $search['shub_message_id'] !== false){
			$sql .= " AND m.`shub_message_id` = ".(int)$search['shub_message_id'];
		}
		if(isset($search['shub_ucm_id']) && $search['shub_ucm_id'] !== false){
			$sql .= " AND m.`shub_ucm_id` = ".(int)$search['shub_ucm_id'];
		}
		if(isset($search['generic']) && !empty($search['generic'])){
			// todo: search product comments too.. not just title (first comment) and summary (last comment)
			$sql .= " AND (`title` LIKE '%".esc_sql($search['generic'])."%'";
			$sql .= " OR `summary` LIKE '%".esc_sql($search['generic'])."%' )";
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
			$ucm_message = new shub_ucm_message(false, false, $message['shub_ucm_message_id']);
			$data['message'] = $ucm_message;
			$data['shub_column_account'] .= '<div><img src="'.plugins_url('networks/ucm/ucm-logo.png', _DTBAKER_SUPPORT_HUB_CORE_FILE_).'" class="ucm_icon small"><a href="'.$ucm_message->get_link().'" target="_blank">'.htmlspecialchars( $ucm_message->get('ucm_product') ? $ucm_message->get('ucm_product')->get( 'product_name' ) : 'Share' ) .'</a></div>';
			$data['shub_column_summary'] .= '<div><img src="'.plugins_url('networks/ucm/ucm-logo.png', _DTBAKER_SUPPORT_HUB_CORE_FILE_).'" class="ucm_icon small"><a href="'.$ucm_message->get_link().'" target="_blank">'.htmlspecialchars( $ucm_message->get_summary() ) .'</a></div>';
			// how many link clicks does this one have?
			$sql = "SELECT count(*) AS `link_clicks` FROM ";
			$sql .= " `"._support_hub_DB_PREFIX."shub_ucm_message` m ";
			$sql .= " LEFT JOIN `"._support_hub_DB_PREFIX."shub_ucm_message_link` ml USING (shub_ucm_message_id) ";
			$sql .= " LEFT JOIN `"._support_hub_DB_PREFIX."shub_ucm_message_link_click` lc USING (shub_ucm_message_link_id) ";
			$sql .= " WHERE 1 ";
			$sql .= " AND m.shub_ucm_message_id = ".(int)$message['shub_ucm_message_id'];
			$sql .= " AND lc.shub_ucm_message_link_id IS NOT NULL ";
			$sql .= " AND lc.user_agent NOT LIKE '%Google%' ";
			$sql .= " AND lc.user_agent NOT LIKE '%Yahoo%' ";
			$sql .= " AND lc.user_agent NOT LIKE '%ucmexternalhit%' ";
			$sql .= " AND lc.user_agent NOT LIKE '%Meta%' ";
			$res = shub_qa1($sql);
			$link_clicks = $res && $res['link_clicks'] ? $res['link_clicks'] : 0;
			$data['shub_column_links'] .= '<div><img src="'.plugins_url('networks/ucm/ucm-logo.png', _DTBAKER_SUPPORT_HUB_CORE_FILE_).'" class="ucm_icon small">'. $link_clicks  .'</div>';
		}
		if(count($messages) && $link_clicks > 0){
			//$data['shub_column_links'] = '<div><img src="'.plugins_url('networks/ucm/ucm-logo.png', _DTBAKER_SUPPORTHUB_CORE_FILE_).'" class="ucm_icon small">'. $link_clicks  .'</div>';
		}
		return $data;

	}


	public function get_unread_count($search=array()){
		if(!get_current_user_id())return 0;
		$sql = "SELECT count(*) AS `unread` FROM `"._support_hub_DB_PREFIX."shub_ucm_message` m ";
		$sql .= " WHERE 1 ";
		$sql .= " AND m.shub_ucm_message_id NOT IN (SELECT mr.shub_ucm_message_id FROM `"._support_hub_DB_PREFIX."shub_ucm_message_read` mr WHERE mr.user_id = '".(int)get_current_user_id()."' AND mr.shub_ucm_message_id = m.shub_ucm_message_id)";
		$sql .= " AND m.`status` = "._shub_MESSAGE_STATUS_UNANSWERED;
		if(isset($search['shub_ucm_product_id']) && $search['shub_ucm_product_id'] !== false){
			$sql .= " AND m.`shub_ucm_product_id` = ".(int)$search['shub_ucm_product_id'];
		}
		if(isset($search['shub_ucm_id']) && $search['shub_ucm_id'] !== false){
			$sql .= " AND m.`shub_ucm_id` = ".(int)$search['shub_ucm_id'];
		}
		$res = shub_qa1($sql);
		return $res ? $res['unread'] : 0;
	}


	public function output_row($message){
		$ucm_message = new shub_ucm_message(false, false, $message['shub_ucm_message_id']);
	    $messages         = $ucm_message->get_comments();
		$return = array();

		ob_start();
		?>
			<img src="<?php echo plugins_url('networks/ucm/ucm-logo.png', _DTBAKER_SUPPORT_HUB_CORE_FILE_);?>" class="ucm_icon">
		    <a href="<?php echo $ucm_message->get_link(); ?>"
	           target="_blank"><?php
		    echo htmlspecialchars( $ucm_message->get('ucm_account') ? $ucm_message->get('ucm_account')->get( 'ucm_name' ) : 'product' ); ?></a> <br/>
		    <?php echo htmlspecialchars( $ucm_message->get_type_pretty() ); ?>
		<?php
		$return['shub_column_account'] = ob_get_clean();

		ob_start();
		$shub_product_id = $ucm_message->get_product_id();
		if($shub_product_id) {
			$shub_product = new SupportHubProduct();
			$shub_product->load($shub_product_id);
			$product_data = $shub_product->get('product_data');
			if(!empty($product_data['image'])){
				?>
				<img src="<?php echo $product_data['image'];?>" class="ucm_icon">
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
        $from = $ucm_message->get_from();
	    ?>
	    <div class="shub_from_holder shub_ucm">
	    <div class="shub_from_full">
		    <?php
			foreach($from as $id => $from_shub_user){
				?>
				<div>
					<?php if($from_shub_user->get_image()){ ?>
					<a href="<?php echo $from_shub_user->get_link();?>" target="_blank"><img src="<?php echo $from_shub_user->get_image();?>" class="shub_from_picture"></a>
					<?php } ?> <?php echo htmlspecialchars($from_shub_user->get_name()); ?>
				</div>
				<?php
			} ?>
	    </div>
        <?php
        reset($from);
        if(isset($from_shub_user)) {
	        if($from_shub_user->get_image()){ ?>
				<a href="<?php echo $from_shub_user->get_link();?>" target="_blank"><img src="<?php echo $from_shub_user->get_image();?>" class="shub_from_picture"></a>
			<?php } ?> <?php echo htmlspecialchars($from_shub_user->get_name());
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
	    <div class="ucm_message_summary<?php echo !isset($message['read_time']) || !$message['read_time'] ? ' unread' : '';?>"> <?php
		    // todo - pull in comments here, not just title/summary
		    // todo - style customer and admin replies differently (eg <em> so we can easily see)
		    $title = strip_tags($ucm_message->get( 'title' ));
			$summary = strip_tags($ucm_message->get( 'summary' ));
		    echo htmlspecialchars( strlen( $title ) > 80 ? substr( $title, 0, 80 ) . '...' : $title ) . ($summary!=$title ? '<br/>' .htmlspecialchars( strlen( $summary ) > 80 ? substr( $summary, 0, 80 ) . '...' : $summary ) : '');
		    ?>
	    </div>
		<?php
		$return['shub_column_summary'] = ob_get_clean();

		ob_start();
		?>
		<a href="<?php echo $ucm_message->link_open();?>" class="socialucm_message_open shub_modal button" data-modaltitle="<?php echo htmlspecialchars($title);?>" data-network="ucm" data-network_message_id="<?php echo (int)$ucm_message->get('shub_ucm_message_id');?>"><?php _e( 'Open' );?></a>
	    <?php if($ucm_message->get('status') == _shub_MESSAGE_STATUS_ANSWERED){  ?>
		    <a href="#" class="socialucm_message_action shub_message_action button"
		       data-action="set-unanswered" data-post="<?php echo esc_attr(json_encode(array(
                'network' => 'ucm',
                'shub_ucm_message_id' => $ucm_message->get('shub_ucm_message_id'),
            )));?>"><?php _e( 'Inbox' ); ?></a>
	    <?php }else{ ?>
		    <a href="#" class="socialucm_message_action shub_message_action button"
		       data-action="set-answered" data-post="<?php echo esc_attr(json_encode(array(
                'network' => 'ucm',
                'shub_ucm_message_id' => $ucm_message->get('shub_ucm_message_id'),
            )));?>"><?php _e( 'Archive' ); ?></a>
	    <?php } ?>
		<?php
		$return['shub_column_action'] = ob_get_clean();

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
										    $ucm_message->update('status',_shub_MESSAGE_STATUS_PENDINGSEND);
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
											    $ucm_message->update('status',_shub_MESSAGE_STATUS_PENDINGSEND);
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
			case 'save_ucm':
				$shub_ucm_id = isset($_REQUEST['shub_ucm_id']) ? (int)$_REQUEST['shub_ucm_id'] : 0;
				if(check_admin_referer( 'save-ucm'.$shub_ucm_id )) {
					$ucm = new shub_ucm_account( $shub_ucm_id );
					if ( isset( $_POST['butt_delete'] ) ) {
						$ucm->delete();
						$redirect = 'admin.php?page=support_hub_settings&tab=ucm';
					} else {
						$ucm->save_data( $_POST );
						$shub_ucm_id = $ucm->get( 'shub_ucm_id' );
						if ( isset( $_POST['butt_save_reconnect'] ) ) {
							$redirect = $ucm->link_connect();
						} else {
							$redirect = $ucm->link_edit();
						}
					}
					header( "Location: $redirect" );
					exit;
				}

				break;
		}
	}

	public function get_message($ucm_account = false, $ucm_product = false, $shub_ucm_message_id = false){
		return new shub_ucm_message($ucm_account, $ucm_product, $shub_ucm_message_id);
	}

	public function run_cron( $debug = false ){
		if($debug)echo "Starting ucm Cron Job \n";
		$accounts = $this->get_accounts();
		foreach($accounts as $account){
			$shub_ucm_account = new shub_ucm_account( $account['shub_ucm_id'] );
			$shub_ucm_account->run_cron($debug);
			$products = $shub_ucm_account->get('products');
			/* @var $products shub_ucm_product[] */
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
		);

		// find other ucm messages by this user.
        if(!empty($user_hints['shub_user_id'])) {
            if (!is_array($user_hints['shub_user_id'])) $user_hints['shub_user_id'] = array($user_hints['shub_user_id']);
            foreach ($user_hints['shub_user_id'] as $shub_user_id) {
                if ((int)$shub_user_id > 0) {
                    $messages = shub_get_multiple('shub_ucm_message', array(
                        'shub_user_id' => $shub_user_id
                    ), 'shub_ucm_message_id', '`last_active` DESC');
                    if (is_array($messages)) {
                        foreach ($messages as $message) {
                            if ($current_extension == 'ucm' && $message_object->get('shub_ucm_message_id') == $message['shub_ucm_message_id']) continue;
                            if (!isset($details['messages']['ucm' . $message['shub_ucm_message_id']])) {
        //						$other_message = new shub_ucm_message();
        //						$other_message->load($message['shub_ucm_message_id']);
                                $details['messages']['ucm' . $message['shub_ucm_message_id']] = array(
                                    'summary' => $message['title'],
                                    'time' => $message['last_active'],
                                    'network' => 'ucm',
                                    'network_message_id' => $message['shub_ucm_message_id'],
                                    'network_message_comment_id' => 0,
//							'message_status' => $other_message->get('status'),
                                );
                            }
                        }
                    }
                }
            }
        }

		return $details;
	}

	public function get_install_sql() {

		global $wpdb;

		$sql = <<< EOT



CREATE TABLE {$wpdb->prefix}shub_ucm (
  shub_ucm_id int(11) NOT NULL AUTO_INCREMENT,
  ucm_name varchar(50) NOT NULL,
  last_checked int(11) NOT NULL DEFAULT '0',
  import_stream int(11) NOT NULL DEFAULT '0',
  post_stream int(11) NOT NULL DEFAULT '0',
  ucm_data longtext NOT NULL,
  ucm_api_url varchar(255) NOT NULL,
  ucm_username varchar(255) NOT NULL,
  ucm_api_key varchar(255) NOT NULL,
  PRIMARY KEY  shub_ucm_id (shub_ucm_id)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;


CREATE TABLE {$wpdb->prefix}shub_ucm_message (
  shub_ucm_message_id int(11) NOT NULL AUTO_INCREMENT,
  shub_ucm_id int(11) NOT NULL,
  shub_message_id int(11) NOT NULL DEFAULT '0',
  shub_ucm_product_id int(11) NOT NULL,
  shub_product_id int(11) NOT NULL DEFAULT '-1',
  ucm_ticket_id varchar(255) NOT NULL,
  summary text NOT NULL,
  title text NOT NULL,
  last_active int(11) NOT NULL DEFAULT '0',
  comments longtext NOT NULL,
  type varchar(20) NOT NULL,
  link varchar(255) NOT NULL,
  data text NOT NULL,
  status tinyint(1) NOT NULL DEFAULT '0',
  user_id int(11) NOT NULL DEFAULT '0',
  shub_user_id int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY  shub_ucm_message_id (shub_ucm_message_id),
  KEY shub_ucm_id (shub_ucm_id),
  KEY shub_message_id (shub_message_id),
  KEY shub_product_id (shub_product_id),
  KEY shub_user_id (shub_user_id),
  KEY last_active (last_active),
  KEY shub_ucm_product_id (shub_ucm_product_id),
  KEY ucm_ticket_id (ucm_ticket_id),
  KEY status (status)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;


CREATE TABLE {$wpdb->prefix}shub_ucm_message_read (
  shub_ucm_message_id int(11) NOT NULL,
  read_time int(11) NOT NULL DEFAULT '0',
  user_id int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY  shub_ucm_message_id (shub_ucm_message_id,user_id)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;


CREATE TABLE {$wpdb->prefix}shub_ucm_message_comment (
  shub_ucm_message_comment_id int(11) NOT NULL AUTO_INCREMENT,
  shub_ucm_message_id int(11) NOT NULL,
  ucm_ticket_message_id varchar(255) NOT NULL,
  time int(11) NOT NULL,
  message_from text NOT NULL,
  message_to text NOT NULL,
  message_text text NOT NULL,
  data text NOT NULL,
  user_id int(11) NOT NULL DEFAULT '0',
  shub_user_id int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY  shub_ucm_message_comment_id (shub_ucm_message_comment_id),
  KEY shub_ucm_message_id (shub_ucm_message_id),
  KEY shub_user_id (shub_user_id),
  KEY ucm_ticket_message_id (ucm_ticket_message_id)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;


CREATE TABLE {$wpdb->prefix}shub_ucm_message_link (
  shub_ucm_message_link_id int(11) NOT NULL AUTO_INCREMENT,
  shub_ucm_message_id int(11) NOT NULL DEFAULT '0',
  link varchar(255) NOT NULL,
  PRIMARY KEY  shub_ucm_message_link_id (shub_ucm_message_link_id),
  KEY shub_ucm_message_id (shub_ucm_message_id)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;

CREATE TABLE {$wpdb->prefix}shub_ucm_message_link_click (
  shub_ucm_message_link_click_id int(11) NOT NULL AUTO_INCREMENT,
  shub_ucm_message_link_id int(11) NOT NULL DEFAULT '0',
  click_time int(11) NOT NULL,
  ip_address varchar(20) NOT NULL,
  user_agent varchar(100) NOT NULL,
  url_referrer varchar(255) NOT NULL,
  PRIMARY KEY  shub_ucm_message_link_click_id (shub_ucm_message_link_click_id),
  KEY shub_ucm_message_link_id (shub_ucm_message_link_id)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;

CREATE TABLE {$wpdb->prefix}shub_ucm_product (
  shub_ucm_product_id int(11) NOT NULL AUTO_INCREMENT,
  shub_ucm_id int(11) NOT NULL,
  shub_product_id int(11) NOT NULL DEFAULT '0',
  product_name varchar(50) NOT NULL,
  last_message int(11) NOT NULL DEFAULT '0',
  last_checked int(11) NOT NULL,
  product_id varchar(255) NOT NULL,
  ucm_data text NOT NULL,
  PRIMARY KEY  shub_ucm_product_id (shub_ucm_product_id),
  KEY shub_ucm_id (shub_ucm_id)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;

EOT;
		return $sql;
	}

}
