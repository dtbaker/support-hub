<?php

if ( ! defined( 'ABSPATH' ) ) exit;


class SupportHub {
	public $dir;
	private $file;
	private $assets_dir;
	private $assets_url;

	/* @var $message_managers shub_facebook[] */
	public $message_managers = array();

	private static $instance = null;
	public static function getInstance ( $file = false ) {
        if (is_null(self::$instance)) { self::$instance = new self( $file ); }
        return self::$instance;
    }

	public function __construct( $file ) {
		$this->file = $file;
		$this->dir = dirname( $this->file );
		$this->assets_dir = trailingslashit( $this->dir ) . 'assets';
		$this->assets_url = esc_url( trailingslashit( plugins_url( '/assets/', $this->file ) ) );

		// Handle localisation
		$this->load_plugin_textdomain();
		add_action( 'init', array( $this, 'load_localisation' ), 0 );
		add_action( 'admin_init', array( $this, 'register_session' ));
		add_action( 'admin_init', array( $this, 'init' ) );


		add_action( 'plugins_loaded', array( $this, 'db_upgrade_check') );

		register_activation_hook( $file, array( $this, 'activation') );
		register_deactivation_hook( $file, array( $this, 'deactivation') );

		global $wpdb;
		define('_support_hub_DB_PREFIX',$wpdb->prefix);

		$this->message_managers = array();
		$this->message_managers = apply_filters( 'shub_managers', $this->message_managers);

		// Add settings page to menu
		add_action( 'admin_menu' , array( $this, 'add_menu_item' ) );
		add_action( 'wp_ajax_support_hub_send-message-reply' , array( $this, 'admin_ajax' ) );
		add_action( 'wp_ajax_support_hub_set-answered' , array( $this, 'admin_ajax' ) );
		add_action( 'wp_ajax_support_hub_set-unanswered' , array( $this, 'admin_ajax' ) );
		add_action( 'wp_ajax_support_hub_modal' , array( $this, 'admin_ajax' ) );
		add_action( 'wp_ajax_support_hub_fb_url_info' , array( $this, 'admin_ajax' ) );
		add_action( 'wp_ajax_support_hub_request_extra_details' , array( $this, 'admin_ajax' ) );

		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );

		//ini_set('display_errors',true);
		//ini_set('error_reporting',E_ALL);
		add_filter('cron_schedules', array( $this, 'cron_new_interval') );
		add_action( 'support_hub_cron_job', array( $this, 'cron_run') );

		add_action( 'init', array( $this, 'shub_init' ) );


	}


	public function shub_init(){

		if ( ! wp_next_scheduled( 'support_hub_cron_job' ) ) {
			wp_schedule_event( time(), 'minutes_5', 'support_hub_cron_job' );
		}

		SupportHubExtra::handle_request_extra();

		foreach($this->message_managers as $name => $message_manager){
			$message_manager->init();
		}
	}

	public function register_session(){
	    if( !session_id() )
	        session_start();
	}

	public function admin_ajax(){
		if(check_ajax_referer( 'support-hub-nonce', 'wp_nonce' )) {

			// todo: don't overwrite default superglobals, run stripslashes every time before we use the content, because another plugin might be stripslashing already
			$_POST    = stripslashes_deep( $_POST );
			$_GET     = stripslashes_deep( $_GET );
			$_REQUEST = stripslashes_deep( $_REQUEST );
			$action = isset( $_REQUEST['action'] ) ? str_replace( 'support_hub_', '', $_REQUEST['action'] ) : false;
			switch($action){
                case 'modal':
                    // open a modal popup with the message in it (similar to pages/message.php)
                    if(isset($_REQUEST['network']) && isset($_REQUEST['network_message_id']) && (int)$_REQUEST['network_message_id'] > 0) {
                        $network = isset($_GET['network']) ? $_GET['network'] : false;
                        $network_message_id = isset($_GET['network_message_id']) ? (int)$_GET['network_message_id'] : false;
                        $network_message_comment_id = isset($_GET['network_message_comment_id']) ? (int)$_GET['network_message_comment_id'] : false;
                        if($network && isset($this->message_managers[$network]) && $network_message_id > 0){
                            $shub_network_message = $this->message_managers[$network]->get_message( false, false, $network_message_id);
                            if($shub_network_message->get('shub_'.$network.'_message_id') == $network_message_id){
                                extract(array(
                                    "shub_{$network}_id" => $shub_network_message->get($network.'_account')->get('shub_'.$network.'_id'),
                                    "shub_{$network}_message_id" => $network_message_id,
                                    "shub_{$network}_message_comment_id" => $network_message_comment_id,
                                ));
                                include( trailingslashit( SupportHub::getInstance()->dir ) . 'networks/'.$network.'/'.$network.'_message.php');
                            }
                        }

                    }
                    break;
                case 'set-answered':
                    if(isset($_REQUEST['network']) && isset($this->message_managers[$_REQUEST['network']]) && !empty($_REQUEST['shub_'.$_REQUEST['network'].'_message_id'])) {
                        $shub_network_message = $this->message_managers[$_REQUEST['network']]->get_message(false, false, $_REQUEST['shub_'.$_REQUEST['network'].'_message_id']);
                        if ($shub_network_message->get('shub_' . $_REQUEST['network'] . '_message_id') == $_REQUEST['shub_'.$_REQUEST['network'].'_message_id']) {
                            $shub_network_message->update('status',_shub_MESSAGE_STATUS_ANSWERED);
                            if (!headers_sent())header('Content-type: text/javascript');
                            ?>
                            jQuery('tr.shub_network_message[data-network=<?php echo $_REQUEST['network']; ?>][data-network-message-id=<?php echo (int)$_REQUEST['shub_'.$_REQUEST['network'].'_message_id']; ?>]').hide();
                            <?php
                        }
                    }
                    break;
                case 'set-unanswered':
                    if(isset($_REQUEST['network']) && isset($this->message_managers[$_REQUEST['network']]) && !empty($_REQUEST['shub_'.$_REQUEST['network'].'_message_id'])) {
                        $shub_network_message = $this->message_managers[$_REQUEST['network']]->get_message(false, false, $_REQUEST['shub_'.$_REQUEST['network'].'_message_id']);
                        if ($shub_network_message->get('shub_' . $_REQUEST['network'] . '_message_id') == $_REQUEST['shub_'.$_REQUEST['network'].'_message_id']) {
                            $shub_network_message->update('status',_shub_MESSAGE_STATUS_UNANSWERED);
                            if (!headers_sent())header('Content-type: text/javascript');
                            ?>
                            jQuery('tr.shub_network_message[data-network=<?php echo $_REQUEST['network']; ?>][data-network-message-id=<?php echo (int)$_REQUEST['shub_'.$_REQUEST['network'].'_message_id']; ?>]').hide();
                            <?php
                        }
                    }
                    break;
                case 'send-message-reply':
                    // handle this for bbpress and envato,
                    // todo: move the other modules into this 'generic' format here.
                    if(isset($_REQUEST['network']) && isset($this->message_managers[$_REQUEST['network']]) && !empty($_REQUEST[$_REQUEST['network'].'_id']) && isset($_REQUEST['id']) && (int)$_REQUEST['id'] > 0) {
                        $shub_network_message = $this->message_managers[$_REQUEST['network']]->get_message( false, false, $_REQUEST['id']);
                        if($shub_network_message->get('shub_'.$_REQUEST['network'].'_message_id') == $_REQUEST['id']){
                            $return  = array();
                            $message = isset( $_POST['message'] ) && $_POST['message'] ? $_POST['message'] : '';
                            $network_id = $_REQUEST[$_REQUEST['network'].'_id'];
                            $debug = isset( $_POST['debug'] ) && (int)$_POST['debug'] > 0 ? $_POST['debug'] : false;
                            if ( $message ) {
                                if($debug)ob_start();
                                $extra_data = array();
                                foreach($_POST as $key=>$val){
                                    if(strpos($key,'extra-') !== false){
                                        $extra_data[substr($key,6)] = $val;
                                    }
                                }
                                $shub_network_message->send_reply( $network_id, $message, $debug, $extra_data );
                                if($debug){
                                    $return['message'] = ob_get_clean();
                                }else {
                                    //set_message( _l( 'message sent and conversation archived.' ) );
                                    $return['redirect'] = 'admin.php?page=support_hub_main';

                                }
                            }
                            if (!headers_sent())header('Content-type: text/javascript');
                            echo json_encode( $return );
                            exit;
                        }
                    }
                    break;
			}
			// pass off the ajax handling to our media managers:
			foreach ( $this->message_managers as $name => $message_manager ) {
				if ( $message_manager->handle_ajax( $action, $this ) ) {
					// success!
				}
			}
		}

		exit;
	}

	function add_meta_box() {

		$screens = array( 'post', 'page' );

		foreach ( $screens as $screen ) {
			add_meta_box(
				'support_hub_meta',
				__( 'Support Hub', 'support_hub' ),
				array($this,'meta_box_callback'),
				$screen,
				'side'
			);
		}
	}

	/**
	 * Prints the box content.
	 *
	 * @param WP_Post $post The object for the current post/page.
	 */
	function meta_box_callback( $post ) {
		include( trailingslashit( $this->dir ) . 'pages/metabox.php');

	}


	public function inbox_assets() {

		add_thickbox();

		wp_register_style( 'support-hub-css', $this->assets_url . 'css/social.css', array(), '1.0.0' );
		wp_register_style( 'jquery-timepicker', $this->assets_url . 'css/jquery.timepicker.css', array(), '1.0.0' );
		wp_enqueue_style( 'support-hub-css' );
		wp_enqueue_style( 'jquery-timepicker' );
		wp_enqueue_style('jquery-style', '//ajax.googleapis.com/ajax/libs/jqueryui/1.8.2/themes/smoothness/jquery-ui.css');

    	wp_register_script( 'support-hub', $this->assets_url . 'js/social.js', array( 'jquery' ), '1.0.0' );

    	wp_register_script( 'jquery-timepicker', $this->assets_url . 'js/jquery.timepicker.min.js', array( 'jquery','jquery-ui-datepicker' ), '1.0.0' );

		wp_localize_script( 'support-hub', 'support_hub', array(
			'wp_nonce' => wp_create_nonce('support-hub-nonce'),
		) );

    	wp_enqueue_script( 'support-hub' );
    	wp_enqueue_script( 'jquery-timepicker' );

		foreach($this->message_managers as $name => $message_manager) {
			$message_manager->page_assets(true);
		}

	}


	public function init() {
		if(isset($_REQUEST['_process'])){

			// fix up WordPress bork:
			// todo: don't overwrite default superglobals, run stripslashes every time before we use the content, because another plugin might be stripslashing already
			$_POST = stripslashes_deep($_POST);
			$_GET = stripslashes_deep($_GET);
			$_REQUEST = stripslashes_deep($_REQUEST);

			$process_action = $_REQUEST['_process'];
			$process_options = array();
			$shub_message_id = false;
			if($process_action == 'send_shub_message' && check_admin_referer( 'shub_send-message' )) {
				// we are sending a social message! yay!

				$send_time = time(); // default: now
				if ( isset( $_POST['schedule_date'] ) && isset( $_POST['schedule_time'] ) && ! empty( $_POST['schedule_date'] ) && ! empty( $_POST['schedule_time'] ) ) {
					$date      = $_POST['schedule_date'];
					$time_hack = $_POST['schedule_time'];
					$time_hack = str_ireplace( 'am', '', $time_hack );
					$time_hack = str_ireplace( 'pm', '', $time_hack );
					$bits      = explode( ':', $time_hack );
					if ( strpos( $_POST['schedule_time'], 'pm' ) ) {
						$bits[0] += 12;
					}
					// add the time if it exists
					$date .= ' ' . implode( ':', $bits ) . ':00';
					$send_time = strtotime( $date );
					//echo $date."<br>".$send_time."<br>".shub_print_date($send_time,true);exit;
				} else if ( isset( $_POST['schedule_date'] ) && ! empty( $_POST['schedule_date'] ) ) {
					$send_time = strtotime( $_POST['schedule_date'] );
				}
				// wack a new entry into the shub_message database table and pass that onto our message_managers below
				$shub_message_id = shub_update_insert( 'shub_message_id', false, 'shub_message', array(
					'post_id'   => isset( $_POST['post_id'] ) ? $_POST['post_id'] : 0, //todo
					'sent_time' => $send_time,
				) );
				if ( $shub_message_id ) {
					$process_options['shub_message_id'] = $shub_message_id;
					$process_options['send_time']       = $send_time;
				} else {
					die( 'Failed to create social message' );
				}
				/* @var $message_manager shub_facebook */
				$message_count = 0;
				foreach ( $this->message_managers as $name => $message_manager ) {
					$message_count += $message_manager->handle_process( $process_action, $process_options );
				}
				if ( $shub_message_id && ! $message_count ) {
					// remove the gobal social message as nothing worked.
					shub_delete_from_db( 'shub_message', 'shub_message_id', $shub_message_id );
				} else if ( $shub_message_id ) {
					shub_update_insert( 'shub_message_id', $shub_message_id, 'shub_message', array(
						'message_count' => $message_count,
					) );
				}
				if ( isset( $_POST['debug'] ) && $_POST['debug'] ) {
					echo "<br><hr> Successfully sent $message_count messages <hr><br><pre>";
					print_r( $_POST );
					print_r( $process_options );
					echo "</pre><hr><br>Completed";
					exit;
				}
				header( "Location: admin.php?page=support_hub_sent" );
				exit;
			}else if($process_action == 'save_general_settings'){
				if(check_admin_referer( 'save-general-settings' )){
					if(isset($_POST['possible_shub_manager_enabled'])) {
						foreach ( $_POST['possible_shub_manager_enabled'] as $id => $tf ) {
							if ( isset( $_POST['shub_manager_enabled'][ $id ] ) && $_POST['shub_manager_enabled'][ $id ] ) {
								update_option( 'shub_manager_enabled_' . $id, 1 );
							} else {
								update_option( 'shub_manager_enabled_' . $id, 0 );
							}
						}
						header( "Location: admin.php?page=support_hub_settings" );
						exit;
					}
				}

			}else if($process_action == 'save_log_settings'){

				if(check_admin_referer( 'save-log-settings' )){

                    if(!empty($_POST['enable_logging'])){
                        update_option('shub_logging_enabled',time() + (3600 * 24));
                    }
                    if(!empty($_POST['remove_logs'])){
                        global $wpdb;
                        $wpdb->query(
                            $wpdb->prepare(
                                "DELETE FROM `"._support_hub_DB_PREFIX."shub_log` WHERE log_time <= %d",
                                $_POST['remove_logs']
                            )
                        );

                    }

					header( "Location: admin.php?page=support_hub_settings&tab=logs" );
					exit;
				}

			}else if($process_action == 'save_encrypted_vault'){

				if(check_admin_referer( 'save-encrypted-vault' ) && !empty($_POST['public_key']) && !empty($_POST['private_key'])){

					update_option('shub_encrypt_public_key',$_POST['public_key']);
					update_option('shub_encrypt_private_key',$_POST['private_key']);

					header( "Location: admin.php?page=support_hub_settings&tab=extra" );
					exit;
				}

			}else if($process_action == 'save_extra_details'){

				$shub_extra_id = !empty($_REQUEST['shub_extra_id']) ? (int)$_REQUEST['shub_extra_id'] : 0;
				if(check_admin_referer( 'save-extra' . $shub_extra_id )){

					$shub_extra = new SupportHubExtra($shub_extra_id);

					if(isset($_REQUEST['butt_delete'])){

						$shub_extra->delete();
						header( "Location: admin.php?page=support_hub_settings&tab=extra" );
						exit;
					}

					if(!$shub_extra->get('shub_extra_id')){
						$shub_extra->create_new();
					}
					$shub_extra->update($_POST);

					$shub_extra_id = $shub_extra->get('shub_extra_id');

					header( "Location: admin.php?page=support_hub_settings&tab=extra" );//&shub_extra_id=" . $shub_extra_id );
					exit;
				}

			}else{
				// just process each request normally:

				/* @var $message_manager shub_facebook */
				foreach($this->message_managers as $name => $message_manager){
					$message_manager->handle_process($process_action, $process_options);
				}
			}

		}
	}

	public function add_menu_item() {

		$message_count = 0;
		foreach($this->message_managers as $name => $message_manager) {
			$message_count += $message_manager->get_unread_count();
		}
		$menu_label = sprintf( __( 'Support Hub %s', 'support_hub' ), $message_count > 0 ? "<span class='update-plugins count-$message_count' title='$message_count'><span class='update-count'>" . number_format_i18n( $message_count ) . "</span></span>" : '');

        add_menu_page( __( 'Support Hub Inbox', 'support_hub' ), $menu_label, 'edit_pages', 'support_hub_main', array($this, 'show_inbox'), 'dashicons-format-chat', "21.1" );

		// hack to rmeove default submenu
		$menu_label = sprintf( __( 'Inbox %s', 'support_hub' ), $message_count > 0 ? "<span class='update-plugins count-$message_count' title='$message_count'><span class='update-count'>" . number_format_i18n($message_count) . "</span></span>" : '' );
		$page = add_submenu_page('support_hub_main', __( 'Support Hub Inbox', 'support_hub' ), $menu_label, 'edit_pages',  'support_hub_main' , array($this, 'show_inbox'));
		add_action( 'admin_print_styles-'.$page, array( $this, 'inbox_assets' ) );

		//$page = add_submenu_page('support_hub_main', __( 'Interactions', 'support_hub' ), __('Interactions' ,'support_hub'), 'edit_pages',  'support_hub_interactions' , array($this, 'show_interactions'));
		//add_action( 'admin_print_styles-'.$page, array( $this, 'inbox_assets' ) );

		//$page = add_submenu_page('support_hub_main', __( 'Compose', 'support_hub' ), __('Compose' ,'support_hub'), 'edit_pages',  'support_hub_compose' , array($this, 'show_compose'));
		//add_action( 'admin_print_styles-'.$page, array( $this, 'inbox_assets' ) );

		//$page = add_submenu_page('support_hub_main', __( 'Sent', 'support_hub' ), __('Sent' ,'support_hub'), 'edit_pages',  'support_hub_sent' , array($this, 'show_sent'));
		//add_action( 'admin_print_styles-'.$page, array( $this, 'inbox_assets' ) );

		$page = add_submenu_page('support_hub_main', __( 'Settings', 'support_hub' ), __('Settings' ,'support_hub'), 'edit_pages',  'support_hub_settings' , array($this, 'show_settings'));
		add_action( 'admin_print_styles-'.$page, array( $this, 'inbox_assets' ) );

		foreach($this->message_managers as $name => $message_manager) {
			$message_manager->init_menu();
		}

	}

	private function is_setup(){
		foreach($this->message_managers as $name => $message_manager){
			$accounts = $message_manager->get_accounts();
			if(count($accounts)){
				return true;
			}
		}
		return false;
	}

	public function show_interactions(){
		if($this->is_setup()){
			include( trailingslashit( $this->dir ) . 'pages/interactions.php');
		}else{
			include( trailingslashit( $this->dir ) . 'pages/setup.php');
		}
	}
	public function show_inbox(){
		if($this->is_setup()){
            if(isset($_GET['network'])){
                include( trailingslashit( $this->dir ) . 'pages/message.php');
            }else{
                include( trailingslashit( $this->dir ) . 'pages/inbox.php');
            }
		}else{
			include( trailingslashit( $this->dir ) . 'pages/setup.php');
		}
	}
	public function show_compose(){
		if($this->is_setup()){
			include( trailingslashit( $this->dir ) . 'pages/compose.php');
		}else{
			include( trailingslashit( $this->dir ) . 'pages/setup.php');
		}
	}
	public function show_sent(){
		if($this->is_setup()){
			include( trailingslashit( $this->dir ) . 'pages/sent.php');
		}else{
			include( trailingslashit( $this->dir ) . 'pages/setup.php');
		}
	}
	public function show_settings(){
		include( trailingslashit( $this->dir ) . 'pages/settings.php');
	}

	public function plugin_updates_page(){
		include( trailingslashit( $this->dir ) . 'pages/plugin_updates.php');
	}
	public function cron_new_interval($interval){
		$interval['minutes_5'] = array('interval' => 5 * 60, 'display' => 'Once 5 minutes');
	    return $interval;
	}
	function cron_run( $debug = false ){
		// running the cron job every 10 minutes.
		// we get a list of accounts and refresh them all.
		@set_time_limit(0);
		//mail('dtbaker@gmail.com','cron job running','test');
		//ob_start();
		//echo 'Checking cron job...';
		$cron_timeout = 5 * 60; // 5 mins
		$cron_start = time();
		$last_cron_task = get_option('last_support_hub_cron',false);
		if($last_cron_task && $last_cron_task['time'] < time() - $cron_timeout){
			// the last cron job didn't complete fully or timed out.
			// start where we left off:
		}else{
			$last_cron_task = false;
		}
		$cron_completed = true;
		$this->log_data(0,'cron','Starting Cron Jobs',array(
			'from' => $last_cron_task ? 'start' : $last_cron_task,
		));

		foreach($this->message_managers as $name => $message_manager) {
			if($last_cron_task){
				if($last_cron_task['name'] == $name) {
					// we got here last time, start at the next cron task.
					$last_cron_task = false;
				}
				continue;
			}
			// recording where we get up to in the (sometimes very long) cron tasks.
			update_option('last_support_hub_cron',array(
				'name' => $name,
				'time' => time(),
			));
			$this->log_data(0,'cron','Starting Extension Cron: '.$name);
			$message_manager->run_cron( $debug );
			// this cron job has completed successfully.
			// if we've been running more than timeout, quit.
			if($cron_start + $cron_timeout < time()){
				$cron_completed = false;
				break;
			}
		}
		if($cron_completed){
			update_option('last_support_hub_cron',false);
		}
		$this->log_data(0,'cron','Finished Cron Jobs',array(
			'all' => $cron_completed ? 'yes' : 'no, finished at '.$name,
		));

		//echo 'completed cron job';
		//mail('dtbaker@gmail.com','Support Hub cron',ob_get_clean());
	}

	public function get_products(){
		$products = shub_get_multiple('shub_product',array(),'shub_product_id', 'product_name ASC');
		foreach($products as $key=>$val){
			if(isset($val['product_data'])){
				$products[$key]['product_data'] = @json_decode($val['product_data'],true);
			}
		}
		return $products;
	}

	public function db_upgrade_check(){
		// hash the SQL used for install.
		// if it has changed at all then we run the activation again.
		$sql = '';
		foreach($this->message_managers as $name => $message_manager) {
			$sql .= $message_manager->get_install_sql();
		}
		// add our core stuff:
		global $wpdb;
		$sql .= <<< EOT

CREATE TABLE {$wpdb->prefix}shub_message (
  shub_message_id int(11) NOT NULL AUTO_INCREMENT,
  post_id int(11) NOT NULL,
  sent_time int(11) NOT NULL DEFAULT '0',
  message_data text NOT NULL,
  message_count int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY  shub_message_id (shub_message_id),
  KEY post_id (post_id)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;

CREATE TABLE {$wpdb->prefix}shub_log (
  shub_log_id int(11) NOT NULL AUTO_INCREMENT,
  log_error_level int(11) NOT NULL DEFAULT '0',
  log_extension varchar(50) NOT NULL,
  log_subject varchar(255) NOT NULL DEFAULT '',
  log_time int(11) NOT NULL DEFAULT '0',
  log_data mediumtext NOT NULL,
  PRIMARY KEY  shub_log_id (shub_log_id),
  KEY log_time (log_time)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;

CREATE TABLE {$wpdb->prefix}shub_product (
  shub_product_id int(11) NOT NULL AUTO_INCREMENT,
  product_name varchar(100) NOT NULL DEFAULT '',
  product_data text NOT NULL,
  PRIMARY KEY  shub_product_id (shub_product_id),
  KEY product_name (product_name)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;

CREATE TABLE {$wpdb->prefix}shub_user (
  shub_user_id int(11) NOT NULL AUTO_INCREMENT,
  shub_linked_user_id int(11) NOT NULL DEFAULT '0',
  user_fname varchar(255) NOT NULL,
  user_lname varchar(255) NOT NULL,
  user_username varchar(255) NOT NULL,
  user_email varchar(255) NOT NULL,
  user_data mediumtext NOT NULL,
  user_id_key1 int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY  shub_user_id (shub_user_id),
  KEY user_email (user_email),
  KEY user_username (user_username),
  KEY user_id_key1 (user_id_key1),
  KEY shub_linked_user_id (shub_linked_user_id)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;

CREATE TABLE {$wpdb->prefix}shub_user_meta (
  shub_user_id int(11) NOT NULL,
  meta_key varchar(255) NOT NULL,
  meta_val varchar(255) NOT NULL,
  KEY shub_user_id (shub_user_id),
  KEY meta_key (meta_key),
  KEY meta_key_val (meta_key,meta_val)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;

CREATE TABLE {$wpdb->prefix}shub_timer (
  shub_timer_id int(11) NOT NULL AUTO_INCREMENT,
  wp_user_id int(11) NOT NULL DEFAULT '0',
  how_long int(11) NOT NULL DEFAULT '0',
  timer_comment text NOT NULL,
  PRIMARY KEY  shub_timer_id (shub_timer_id),
  KEY wp_user_id (wp_user_id)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;

CREATE TABLE {$wpdb->prefix}shub_extra (
  shub_extra_id int(11) NOT NULL AUTO_INCREMENT,
  extra_name varchar(255) NOT NULL DEFAULT '',
  extra_description text NOT NULL,
  extra_order int(11) NOT NULL DEFAULT '0',
  extra_required int(11) NOT NULL DEFAULT '0',
  field_type varchar(50) NOT NULL DEFAULT '',
  field_settings text NOT NULL,
  PRIMARY KEY  shub_extra_id (shub_extra_id),
  KEY extra_order (extra_order)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;


CREATE TABLE {$wpdb->prefix}shub_extra_data (
  shub_extra_data_id int(11) NOT NULL AUTO_INCREMENT,
  shub_extra_id int(11) NOT NULL DEFAULT '0',
  extra_value mediumtext NOT NULL,
  extra_data mediumtext NOT NULL,
  extra_time int(11) NOT NULL DEFAULT '0',
  shub_user_id int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY  shub_extra_data_id (shub_extra_data_id),
  KEY shub_user_id (shub_user_id),
  KEY shub_extra_id (shub_extra_id)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;


CREATE TABLE {$wpdb->prefix}shub_extra_data_rel (
  shub_extra_data_id int(11) NOT NULL DEFAULT '0',
  shub_extra_id int(11) NOT NULL DEFAULT '0',
  shub_network varchar(40) NOT NULL DEFAULT '',
  shub_network_account_id int(11) NOT NULL DEFAULT '0',
  shub_network_message_id int(11) NOT NULL DEFAULT '0',
  shub_network_user_id int(11) NOT NULL DEFAULT '0',
  KEY shub_extra_data_id (shub_extra_data_id),
  KEY shub_network (shub_network),
  KEY shub_network_account_id (shub_network_account_id),
  KEY shub_network_message_id (shub_network_message_id),
  KEY shub_network_user_id (shub_network_user_id),
  KEY shub_extra_id (shub_extra_id)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;



EOT;
		$hash = md5($sql);
        if(get_option("support_hub_db_hash") != $hash){
	        $this->activation($sql);
	        $this->log_data(0,'core','Ran SQL Update');
	        update_option( "support_hub_db_hash", $hash );
        }
	}
	public function deactivation(){
		wp_clear_scheduled_hook( 'support_hub_cron_job' );
	}
	public function activation($sql) {
		global $wpdb;

		$bits = explode( ';', $sql );

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		foreach ( $bits as $sql ) {
			if ( trim( $sql ) ) {
				dbDelta( trim( $sql ) . ';' );
			}
		}

		$wpdb->hide_errors();
	}

	public function log_data($error_level, $extension, $subject, $data = array()){
        if(get_option('shub_logging_enabled',0) > time() || $error_level>0) {
            shub_update_insert('shub_log_id', false, 'shub_log', array(
                'log_time' => time(),
                'log_error_level' => $error_level,
                'log_extension' => $extension,
                'log_subject' => $subject,
                'log_data' => $data ? serialize($data) : '',
            ));
        }
	}

	/**
	 * Load plugin localisation
	 * @return void
	 */
	public function load_localisation () {
		load_plugin_textdomain( 'support_hub' , false , dirname( plugin_basename( $this->file ) ) . '/lang/' );
	}

	/**
	 * Load plugin textdomain
	 * @return void
	 */
	public function load_plugin_textdomain () {
	    $domain = 'support_hub';

	    $locale = apply_filters( 'plugin_locale' , get_locale() , $domain );

	    load_textdomain( $domain , WP_LANG_DIR . '/' . $domain . '/' . $domain . '-' . $locale . '.mo' );
	    load_plugin_textdomain( $domain , FALSE , dirname( plugin_basename( $this->file ) ) . '/lang/' );
	}


	public function message_user_summary($user_hints, $current_extension, $message_object){
		// here we hunt down other messages from this user and bring them back to the UI
		$user_details = array();
		$other_messages = array();

		// pull out the 'extra data' linked to this ticket
		$extras = SupportHubExtra::get_all_extras();
		$extra_datas = array();
		foreach($extras as $extra_id => $extra){
			$extra_datas = $extra_datas + $extra->get_data($current_extension, $message_object->get('shub_'.$current_extension.'_id'), $message_object->get('shub_'.$current_extension.'_message_id'), !empty($user_hints['shub_user_id']) ? $user_hints['shub_user_id'] : 0);
		}
		foreach($extra_datas as $extra_data){
			//echo $extra_data->get('shub_extra_data_id') . ' ';
			?>
			<div>
				<strong><?php echo htmlspecialchars($extras[$extra_data->get('shub_extra_id')]->get('extra_name'));?>:</strong>
				<?php
                switch($extras[$extra_data->get('shub_extra_id')]->get('field_type')){
                    case 'encrypted':
                        echo '(encrypted)';
                        break;
                    default:
                        echo shub_forum_text($extra_data->get('extra_value'),false);
                }
                ?>
			</div>
			<?php
		}


		foreach($this->message_managers as $name => $message_manager){
			$details = $message_manager->find_other_user_details($user_hints, $current_extension, $message_object);
			if($details && isset($details['messages']) && is_array($details['messages'])){
				$other_messages = array_merge($other_messages,$details['messages']);
			}
			if($details && isset($details['user']) && is_array($details['user'])){
				$user_details = array_merge($user_details,$details['user']);
			}
		}

		?>
		<strong><?php _e('User:');?></strong>
		<?php
        $user_bits = array();
        if(!empty($user_hints['shub_user_id'])){
            $user = new SupportHubUser($user_hints['shub_user_id']);
            if(!empty($user->details['user_fname'])){
                $user_bits[] =  esc_html($user->details['user_fname']);
            }
            if(!empty($user->details['user_lname'])){
                $user_bits[] =  esc_html($user->details['user_lname']);
            }
            if(!empty($user->details['user_username'])){
                $user_bits[] =  esc_html($user->details['user_username']);
            }
            if(!empty($user->details['user_email'])){
                $user_bits[] =  esc_html($user->details['user_email']);
            }
        }
		if(isset($user_details['url']) && isset($user_details['username'])){
            $user_bits[] = '<a href="'.esc_url($user_details['url']).'" target="_blank">' . esc_html($user_details['username']) . '</a>';
		}else if(isset($user_details['username'])){
            $user_bits[] =  esc_html($user_details['username']);
		}
        echo implode(' ',$user_bits);
		foreach(array('codes','products') as $key){
			if(isset($user_details[$key]))echo $user_details[$key] .' ';
		}
        if(count($other_messages)){
            ?>
            <br/>
            <strong><?php _e('Other Messages:');?></strong><br/>
            <ul>
            <?php
            foreach($other_messages as $other_message){
                ?>
                <li><a href="#" class="shub_modal" data-network="<?php echo esc_attr($other_message['network']);?>" data-network_message_id="<?php echo (int)$other_message['network_message_id'];?>" data-network_message_comment_id="<?php echo isset($other_message['network_message_comment_id']) ? (int)$other_message['network_message_comment_id'] : '';?>"><?php echo shub_print_date($other_message['time']);?> - <?php echo esc_html($other_message['summary']);?></a></li>
                <?php
            }
            ?></ul><?php
        }

	}


	public function get_template($template_name){
	    if( file_exists( get_stylesheet_directory() .'/'.$template_name)){
	        return get_stylesheet_directory() .'/'.$template_name;
	    }else if( file_exists( get_template_directory() .'/'.$template_name)){
	        return get_template_directory() .'/'.$template_name;
	    }else if (file_exists(dirname( _DTBAKER_SUPPORT_HUB_CORE_FILE_ ) . '/templates/' . $template_name)) {
	        return dirname( _DTBAKER_SUPPORT_HUB_CORE_FILE_ ) . '/templates/' . $template_name;
	    }
	    return false;
	}


}
