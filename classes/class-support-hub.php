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

		// todo: hook these into loop
		// todo: option to disable a social method completely (tickboxes on settings page)
		$this->message_managers = array(
			'facebook' => new shub_facebook(),
			'twitter' => new shub_twitter(),
			'google' => new shub_google(),
			'linkedin' => new shub_linkedin(),
		);

		// Add settings page to menu
		add_action( 'admin_menu' , array( $this, 'add_menu_item' ) );
		add_action( 'wp_ajax_support_hub_send-message-reply' , array( $this, 'admin_ajax' ) );
		add_action( 'wp_ajax_support_hub_set-answered' , array( $this, 'admin_ajax' ) );
		add_action( 'wp_ajax_support_hub_set-unanswered' , array( $this, 'admin_ajax' ) );
		add_action( 'wp_ajax_support_hub_modal' , array( $this, 'admin_ajax' ) );
		add_action( 'wp_ajax_support_hub_fb_url_info' , array( $this, 'admin_ajax' ) );

		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );

		//ini_set('display_errors',true);
		//ini_set('error_reporting',E_ALL);
		add_filter('cron_schedules', array( $this, 'cron_new_interval') );
		add_action( 'support_hub_cron_job', array( $this, 'cron_run') );

		add_action( 'init', array( $this, 'shub_init' ) );


	}


	public function shub_init(){

		if ( ! wp_next_scheduled( 'support_hub_cron_job' ) ) {
			wp_schedule_event( time(), 'minutes_10', 'support_hub_cron_job' );
		}
		foreach($this->message_managers as $name => $message_manager){
			$message_manager->init();
		}
	}

	public function register_session(){
	    if( !session_id() )
	        session_start();
	}

	public function admin_ajax(){
		check_ajax_referer( 'support-hub-nonce', 'wp_nonce' );

		$action = isset($_REQUEST['action']) ? str_replace('support_hub_','',$_REQUEST['action']) : false;
		// pass off the ajax handling to our media managers:
		foreach($this->message_managers as $name => $message_manager){
			if($message_manager->handle_ajax($action, $this)){
				// success!
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

			foreach($_POST as $key=>$val){
				if(!is_array($val)){
					$_POST[$key] = stripslashes($val);
				}
			}
			$process_action = $_REQUEST['_process'];
			$process_options = array();
			$shub_message_id = false;
			if($process_action == 'send_shub_message'){
				check_admin_referer( 'shub_send-message' );
				// we are sending a social message! yay!

			    $send_time = time(); // default: now
                if(isset($_POST['schedule_date']) && isset($_POST['schedule_time']) && !empty($_POST['schedule_date']) && !empty($_POST['schedule_time'])){
                    $date = $_POST['schedule_date'];
                    $time_hack = $_POST['schedule_time'];
                    $time_hack = str_ireplace('am','',$time_hack);
                    $time_hack = str_ireplace('pm','',$time_hack);
                    $bits = explode(':',$time_hack);
                    if(strpos($_POST['schedule_time'],'pm')){
                        $bits[0] += 12;
                    }
                    // add the time if it exists
                    $date .= ' '.implode(':',$bits).':00';
	                $send_time = strtotime($date);
	                //echo $date."<br>".$send_time."<br>".shub_print_date($send_time,true);exit;
                }else if(isset($_POST['schedule_date']) && !empty($_POST['schedule_date'])){
                    $send_time = strtotime($_POST['schedule_date']);
                }
				// wack a new entry into the shub_message database table and pass that onto our message_managers below
				$shub_message_id = shub_update_insert('shub_message_id',false,'shub_message',array(
					'post_id' => isset($_POST['post_id']) ? $_POST['post_id'] : 0, //todo
					'sent_time' => $send_time,
				));
				if($shub_message_id){
					$process_options['shub_message_id'] = $shub_message_id;
					$process_options['send_time'] = $send_time;
				}else{
					die('Failed to create social message');
				}
				/* @var $message_manager shub_facebook */
				$message_count = 0;
				foreach($this->message_managers as $name => $message_manager){
					$message_count += $message_manager->handle_process($process_action, $process_options);
				}
				if($shub_message_id && !$message_count){
					// remove the gobal social message as nothing worked.
					shub_delete_from_db('shub_message','shub_message_id',$shub_message_id);
				}else if($shub_message_id){
					shub_update_insert('shub_message_id',$shub_message_id,'shub_message',array(
						'message_count' => $message_count,
					));
				}
				if(isset($_POST['debug']) && $_POST['debug']){
					echo "<br><hr> Successfully sent $message_count messages <hr><br><pre>";
					print_r($_POST);
					print_r($process_options);
					echo "</pre><hr><br>Completed";
					exit;
				}
				header("Location: admin.php?page=support_hub_sent");
				exit;
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

		$page = add_submenu_page('support_hub_main', __( 'Compose', 'support_hub' ), __('Compose' ,'support_hub'), 'edit_pages',  'support_hub_compose' , array($this, 'show_compose'));
		add_action( 'admin_print_styles-'.$page, array( $this, 'inbox_assets' ) );

		$page = add_submenu_page('support_hub_main', __( 'Sent', 'support_hub' ), __('Sent' ,'support_hub'), 'edit_pages',  'support_hub_sent' , array($this, 'show_sent'));
		add_action( 'admin_print_styles-'.$page, array( $this, 'inbox_assets' ) );

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
			include( trailingslashit( $this->dir ) . 'pages/inbox.php');
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
		$interval['minutes_10'] = array('interval' => 10 * 60, 'display' => 'Once 10 minutes');
	    return $interval;
	}
	function cron_run( $debug = false ){
		// running the cron job every 10 minutes.
		// we get a list of accounts and refresh them all.
		//@set_time_limit(0);
		//mail('dtbaker@gmail.com','cron job running','test');
		//ob_start();
		//echo 'Checking cron job...';

		// todo: $debug as config variable in settings

		foreach($this->message_managers as $name => $message_manager) {
			$message_manager->run_cron( $debug );
		}

		//echo 'completed cron job';
		//mail('dtbaker@gmail.com','Support Hub cron',ob_get_clean());
	}

	public function db_upgrade_check(){
		// hash the SQL used for install.
		// if it has changed at all then we run the activation again.
		$sql = '';
		foreach($this->message_managers as $name => $message_manager) {
			$sql .= $message_manager->get_install_sql();
		}
		$hash = md5($sql);
        if(get_option("support_hub_db_hash") != $hash){
	        $this->activation();
        }
	}
	public function deactivation(){
		wp_clear_scheduled_hook( 'support_hub_cron_job' );
	}
	public function activation() {


		global $wpdb;

		$sql = '';
		foreach ( $this->message_managers as $name => $message_manager ) {
			$sql .= $message_manager->get_install_sql();
		}
		$hash = md5( $sql );

		// add our core stuff:
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


EOT;

		$bits = explode( ';', $sql );

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		foreach ( $bits as $sql ) {
			if ( trim( $sql ) ) {
				dbDelta( trim( $sql ) . ';' );
			}
		}

		$wpdb->hide_errors();

		update_option( "support_hub_db_hash", $hash );
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

}
