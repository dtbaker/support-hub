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

		register_deactivation_hook( $file, array( 'SupportHub', 'deactivation') );

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
		add_action( 'wp_ajax_support_hub_queue-watch' , array( $this, 'admin_ajax' ) );
		add_action( 'wp_ajax_support_hub_resend_outbox_message' , array( $this, 'admin_ajax' ) );
		add_action( 'wp_ajax_support_hub_delete_outbox_message' , array( $this, 'admin_ajax' ) );
		add_action( 'wp_ajax_support_hub_next-continuous-message' , array( $this, 'admin_ajax' ) );
		add_action( 'wp_ajax_support_hub_load-message' , array( $this, 'admin_ajax' ) );

        add_filter('set-screen-option', array( $this, 'set_screen_options' ), 10, 3);

		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );

		//ini_set('display_errors',true);
		//ini_set('error_reporting',E_ALL);
		add_filter('cron_schedules', array( $this, 'cron_new_interval') );
		add_action( 'support_hub_cron_job', array( $this, 'cron_run') );

		add_action( 'init', array( $this, 'shub_init' ) );
//		add_action( 'init', array( $this, 'send_outbox_messages' ) );


	}


	public function shub_init(){

		if ( ! wp_next_scheduled( 'support_hub_cron_job' ) ) {
			wp_schedule_event( time(), 'minutes_5', 'support_hub_cron_job' );
		}

		SupportHubExtra::handle_request_extra();

		foreach($this->message_managers as $name => $message_manager){
			$message_manager->init();
		}

        if(isset($_REQUEST['debug_shub_cron'])){
            $this->cron_run(true);
            exit;
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
                    if(isset($_REQUEST['network']) && isset($_REQUEST['message_id']) && (int)$_REQUEST['message_id'] > 0) {
                        $network = isset($_GET['network']) ? $_GET['network'] : false;
                        $message_id = isset($_GET['message_id']) ? (int)$_GET['message_id'] : false;
                        $message_comment_id = isset($_GET['message_comment_id']) ? (int)$_GET['message_comment_id'] : false;
                        if($network && isset($this->message_managers[$network]) && $message_id > 0){
                            $shub_extension_message = $this->message_managers[$network]->get_message( false, false, $message_id);
                            if($shub_extension_message->get('shub_message_id') == $message_id){
                                extract(array(
                                    "shub_account_id" => $shub_extension_message->get('account')->get('shub_account_id'),
                                    "shub_message_id" => $message_id,
                                    "shub_message_comment_id" => $message_comment_id,
                                ));
                                include( trailingslashit( SupportHub::getInstance()->dir ) . 'extensions/'.$network.'/'.$network.'_message.php');
                            }else{
                                echo 'Failed to load message from database';
                            }
                        }else{
                            echo 'Failed network message ID';
                        }
                    }else{
                        echo 'Failed network params';
                    }
                    break;
                case 'next-continuous-message':

                    if(!empty($_SESSION['_shub_search_rules'])){
                        //$_SESSION['_shub_search_rules'] = array($this_search, $order, $message_ids);
                        $this_search = $_SESSION['_shub_search_rules'][0];
                        $message_ids = $_SESSION['_shub_search_rules'][2];
                        $this_search['not_in'] = $message_ids;;
                        SupportHub::getInstance()->load_all_messages($this_search, $_SESSION['_shub_search_rules'][1], 5);
                        $all_messages = SupportHub::getInstance()->all_messages;
                        foreach($all_messages as $all_message){
                            $message_ids[]=$all_message['shub_message_id'];
                        }
                        // this is used in class-support-hub.php to load the next batch of messages.
                        $_SESSION['_shub_search_rules'][2] = $message_ids;

                        $myListTable = new SupportHubMessageList(array(
                            'screen' => 'shub_inbox'
                        ));
                        $myListTable->set_layout_type('continuous');
                        $myListTable->set_data($all_messages);
                        $myListTable->prepare_items();
                        if ( $myListTable->has_items() ) {
                            $myListTable->display_rows();
                        } else {
                            echo '<div class="no-items" style="text-align:center">';
                            $myListTable->no_items();
                            echo '</div>';
                        }
                    }

                    break;
                case 'load-message':

                    if(!empty($_REQUEST['network']) && !empty($_REQUEST['message_id'])){
	                    $this_search = array();
                        $this_search['shub_message_id'] = (int)$_REQUEST['message_id'];;
                        SupportHub::getInstance()->load_all_messages($this_search, array(), 1);
                        $all_messages = SupportHub::getInstance()->all_messages;

                        $myListTable = new SupportHubMessageList(array(
                            'screen' => 'shub_inbox'
                        ));
                        $myListTable->set_layout_type('continuous');
                        $myListTable->set_only_inner(true);
                        $myListTable->set_data($all_messages);
                        $myListTable->prepare_items();
                        if ( $myListTable->has_items() ) {
                            $myListTable->display_rows();
                        } else {
                            echo 'No item found';
                        }
                    }

                    break;
                case 'set-answered':
                    if(isset($_REQUEST['network']) && isset($this->message_managers[$_REQUEST['network']]) && !empty($_REQUEST['shub_message_id'])) {
                        $shub_extension_message = $this->message_managers[$_REQUEST['network']]->get_message(false, false, $_REQUEST['shub_message_id']);
                        if ($shub_extension_message->get('shub_message_id') == $_REQUEST['shub_message_id']) {
                            if (!headers_sent()) header('Content-type: text/javascript');
                            // we hide the element and provide an 'undo' placeholder in its place.
                            // if it's a row we just hide it, if it's a div we slide it up nicely.

                            if (isset($_REQUEST['last_active']) && $_REQUEST['last_active'] != $shub_extension_message->get('last_active')) {
                                // a new message was received without updating the page.
                                // todo: ajax the shit out of live message updates instead of waiting for action.
                                // todo: do this check on bulk actions as well.
                                ?>
                                alert('There is an update to this message. Please refresh the page to see.');
                                <?php
                            } else {
                                $shub_extension_message->update('shub_status', _shub_MESSAGE_STATUS_ANSWERED);
                                ?>
	                            if(typeof ucm != 'undefined' && typeof ucm.social != 'undefined'){
	                                ucm.social.message_status_changed( '<?php echo esc_js($_REQUEST['network']);?>', <?php echo (int)$_REQUEST['shub_message_id'];?>,  <?php echo _shub_MESSAGE_STATUS_ANSWERED;?>);
	                            }else{
	                                alert('Failed to load scripts correctly');
	                            }

                                <?php
                            }
                        }
                    }
                    break;
                case 'set-unanswered':
                    if(isset($_REQUEST['network']) && isset($this->message_managers[$_REQUEST['network']]) && !empty($_REQUEST['shub_message_id'])) {
                        $shub_extension_message = $this->message_managers[$_REQUEST['network']]->get_message(false, false, $_REQUEST['shub_message_id']);
                        if ($shub_extension_message->get('shub_message_id') == $_REQUEST['shub_message_id']) {
                            $shub_extension_message->update('shub_status',_shub_MESSAGE_STATUS_UNANSWERED);
                            if (!headers_sent())header('Content-type: text/javascript');
                            // we hide the element and provide an 'undo' placeholder in its place.
                            // if it's a row we just hide it, if it's a div we slide it up nicely.
                            ?>
	                        if(typeof ucm != 'undefined' && typeof ucm.social != 'undefined'){
	                            ucm.social.message_status_changed( '<?php echo esc_js($_REQUEST['network']);?>', <?php echo (int)$_REQUEST['shub_message_id'];?>,  <?php echo _shub_MESSAGE_STATUS_UNANSWERED;?>);
	                        }else{
	                            alert('Failed to load scripts correctly');
	                        }
                            <?php
                        }
                    }
                    break;
                case 'send-message-reply':
                    /*
                    sample post data:
                        action:support_hub_send-message-reply
                        wp_nonce:dfd377374d
                        message:test
                        account-id:1
                        message-id:246
                        network:envato
                        debug:1
                    */
                    if(isset($_REQUEST['network']) && isset($this->message_managers[$_REQUEST['network']]) && !empty($_REQUEST['account-id']) && !empty($_REQUEST['message-id'])) {
                        $shub_extension_message = $this->message_managers[$_REQUEST['network']]->get_message( false, false, $_REQUEST['message-id']);
                        if($shub_extension_message->get('shub_message_id') == $_REQUEST['message-id']){
                            $return  = array(
                                'message' => '',
                                'error' => false,
                                'shub_outbox_id' => false,
                            );
                            if (isset($_REQUEST['last_active']) && $_REQUEST['last_active'] != $shub_extension_message->get('last_active')) {
                                $return['error'] = true;
                                $return['message'] = 'There is an update to this message. Please refresh the page to see.';
                            }else {

                                $message = isset($_POST['message']) && $_POST['message'] ? $_POST['message'] : '';
                                $account_id = $_REQUEST['account-id'];
                                $debug = isset($_POST['debug']) && (int)$_POST['debug'] > 0 ? true : false;
                                if ($message) {

                                    // we have a message and a message manager.
                                    // time to queue this baby into the outbox and send it swimming
                                    // what the hell did I just write? I need sleep!

                                    $outbox = new SupportHubOutbox();
                                    $outbox->create_new();
                                    if ($outbox->get('shub_outbox_id')) {
                                        if ($debug) ob_start();
                                        $extra_data = array();
                                        foreach ($_POST as $key => $val) {
                                            if (strpos($key, 'extra-') !== false) {
                                                $extra_data[substr($key, 6)] = $val; // remove the 'extra-' portion from this key.
                                            }
                                        }
//	                                    print_r($extra_data); print_r( $_POST ); exit;
	                                    $outbox->update_outbox_data(array(
                                            'debug' => $debug,
                                            'extra' => $extra_data,
                                        ));
	                                    if(!empty($_POST['private'])){
		                                    // we're just adding a private reply. don't send this to the extension message queue just add it to the comment database.
		                                    $message_comment_id = $shub_extension_message->queue_reply($account_id, $message, $debug, $extra_data, false, true);
		                                    if (!$message_comment_id) {
			                                    $return['message'] .= 'Failed to queue private comment reply in database.';
			                                    $return['error'] = true;
		                                    }else {

			                                    // now if it was a private message, do we send a public notice message as well?
			                                    if ( ! empty( $_POST['private_public_message'] ) && ! empty( $_POST['private_public_message_text'] ) ) {
				                                    // queue a new public message to this outbox!
				                                    $message_comment_id = $shub_extension_message->queue_reply( $account_id, $_POST['private_public_message_text'], $debug, $extra_data, $outbox->get( 'shub_outbox_id' ) );
				                                    $outbox->update( array(
					                                    'shub_extension'          => $_REQUEST['network'],
					                                    'shub_account_id'         => $account_id,
					                                    'shub_message_id'         => $_REQUEST['message-id'],
					                                    'shub_message_comment_id' => $message_comment_id,
				                                    ) );

				                                    if ( $debug ) {
					                                    // send the message straight away and show any debug output
					                                    echo $outbox->send_queued( true );
					                                    $return['message'] .= ob_get_clean();
					                                    // dont send an shub_outbox_id in debug mode
					                                    // this will keep the 'message' window open and not shrink it down so we can better display debug messages.

				                                    } else {
					                                    //set_message( _l( 'message sent and conversation archived.' ) );
					                                    $return['shub_outbox_id'] = $outbox->get( 'shub_outbox_id' );
				                                    }
			                                    }else{
				                                    // don't need the outbox queue because we're not sending anything externally.
				                                    $outbox->delete();
			                                    }
		                                    }
	                                    }else{
		                                    // just sending a normal public reply.
		                                    $message_comment_id = $shub_extension_message->queue_reply($account_id, $message, $debug, $extra_data, $outbox->get('shub_outbox_id'));
		                                    if (!$message_comment_id) {
			                                    $return['message'] .= 'Failed to queue comment reply in database.';
			                                    $return['error'] = true;
		                                    }else {
			                                    $outbox->update( array(
				                                    'shub_extension'          => $_REQUEST['network'],
				                                    'shub_account_id'         => $account_id,
				                                    'shub_message_id'         => $_REQUEST['message-id'],
				                                    'shub_message_comment_id' => $message_comment_id,
			                                    ) );

			                                    if ( $debug ) {
				                                    // send the message straight away and show any debug output
				                                    echo $outbox->send_queued( true );
				                                    $return['message'] .= ob_get_clean();
				                                    // dont send an shub_outbox_id in debug mode
				                                    // this will keep the 'message' window open and not shrink it down so we can better display debug messages.

			                                    } else {
				                                    //set_message( _l( 'message sent and conversation archived.' ) );
				                                    $return['shub_outbox_id'] = $outbox->get( 'shub_outbox_id' );
			                                    }
		                                    }
	                                    }

	                                    if(empty($return['error']) && !empty($_POST['archive'])){
		                                    // successfully queued. do we archive?
		                                    $shub_extension_message->update('shub_status',_shub_MESSAGE_STATUS_ANSWERED);
	                                    }




                                    }
                                }
                            }

                            if (!headers_sent())header('Content-type: text/javascript');
                            echo json_encode( $return );
                            exit;
                        }
                    }
                    break;
                case 'queue-watch':
                    // find out how many pending messages exist and display that result back to the browser.
                    // along with outbox_ids so we can update the UI when it is sent
                    $this->send_outbox_messages();
                    $pending = SupportHubOutbox::get_pending();
                    $failed = SupportHubOutbox::get_failed();
                    $return = array();
                    if (!headers_sent())header('Content-type: text/javascript');
                    $return['outbox_ids'] = array();
                    foreach($pending as $message){
                        $return['outbox_ids'][] = array(
                            'shub_outbox_id' => $message['shub_outbox_id'],
                            'shub_status' => $message['shub_status'],
                        );
                    }
                    foreach($failed as $message){
                        $return['outbox_ids'][] = array(
                            'shub_outbox_id' => $message['shub_outbox_id'],
                            'shub_status' => $message['shub_status'],
                        );
                    }
                    echo json_encode( $return );

                    break;
                case 'resend_outbox_message':
                    $shub_outbox_id = !empty($_REQUEST['shub_outbox_id']) ? (int)$_REQUEST['shub_outbox_id'] : false;
                    if($shub_outbox_id){
                        if (!headers_sent())header('Content-type: text/javascript');
                        $pending = new SupportHubOutbox($shub_outbox_id);
                        if($pending->get('shub_outbox_id') == $shub_outbox_id) {
                            ob_start();
                            echo $pending->send_queued(true);
                            $return = array(
                                'message' => 'Message Resent. Please refresh the page. ' . ob_get_clean()
                            );
                            echo json_encode($return);
                        }
                        exit;

                    }
                    break;
                case 'delete_outbox_message':
                    $shub_outbox_id = !empty($_REQUEST['shub_outbox_id']) ? (int)$_REQUEST['shub_outbox_id'] : false;
                    if($shub_outbox_id){
                        if (!headers_sent())header('Content-type: text/javascript');
                        // remove the comment from the database.
                        $pending = new SupportHubOutbox($shub_outbox_id);
                        if($pending->get('shub_outbox_id') == $shub_outbox_id) {
                            shub_delete_from_db('shub_message_comment', 'shub_message_comment_id', $pending->get('shub_message_comment_id'));
                            $pending->delete();
                            $return = array(
                                'message' => 'Deleted Successfully. Please re-load the page.'
                            );
                            echo json_encode($return);
                        }
                        exit;
                    }
                    break;

                case 'request_extra_details':

                    if(!empty($_REQUEST['network']) && isset($this->message_managers[$_REQUEST['network']])){
                        if (!headers_sent())header('Content-type: text/javascript');

                        $debug = isset( $_POST['debug'] ) && $_POST['debug'] ? $_POST['debug'] : false;
                        $response = array();
                        $extra_ids = isset($_REQUEST['extra_ids']) && is_array($_REQUEST['extra_ids']) ? $_REQUEST['extra_ids']  : array();
                        $account_id = isset($_REQUEST['accountId']) ? (int)$_REQUEST['accountId'] : (isset($_REQUEST['account-id']) ? (int)$_REQUEST['account-id'] : false);
                        $message_id = isset($_REQUEST['messageId']) ? (int)$_REQUEST['messageId'] : (isset($_REQUEST['message-id']) ? (int)$_REQUEST['message-id'] : false);
                        if(empty($extra_ids)){
                            $response['message'] = 'Please request at least one Extra Detail';
                        }else{

                            $shub_message = $this->get_message_object( $message_id );
                            if($message_id && $shub_message && $shub_message->get('shub_message_id') == $message_id){
                                // build the message up
                                $message = SupportHubExtra::build_message(array(
                                    'network' => $_REQUEST['network'],
                                    'account_id' => $account_id,
                                    'message_id' => $message_id,
                                    'extra_ids' => $extra_ids,
                                ));
                                $response['message'] = $message;
//							if($debug)ob_start();
//							$shub_message->send_reply( $shub_message->get('envato_id'), $message, $debug );
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


	public function screen_options() {
        $screen = get_current_screen();
        if(!is_object($screen) || !$screen->id)
            return;
        $args = array(
            'label' => __('Messages per page', 'shub'),
            'default' => 20,
            'option' => 'shub_items_per_page'
        );
        add_screen_option( 'per_page', $args );
    }

    public function set_screen_options($status, $option, $value) {
        if ( 'shub_items_per_page' == $option ) return $value;
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

			}else if($process_action == 'save_product_details'){

				$shub_product_id = !empty($_REQUEST['shub_product_id']) ? (int)$_REQUEST['shub_product_id'] : 0;
				if(check_admin_referer( 'save-product' . $shub_product_id )){

					$shub_product = new SupportHubProduct($shub_product_id);

					if(isset($_REQUEST['butt_delete'])){

						$shub_product->delete();
						header( "Location: admin.php?page=support_hub_settings&tab=products" );
						exit;
					}

					if(!$shub_product->get('shub_product_id')){
						$shub_product->create_new();
					}
					$shub_product->update($_POST);

					$shub_product_id = $shub_product->get('shub_product_id');

					header( "Location: admin.php?page=support_hub_settings&tab=products" );//&shub_product_id=" . $shub_product_id );
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

		$message_count = $this->get_unread_count();;
		$menu_label = sprintf( __( 'Support Hub %s', 'support_hub' ), $message_count > 0 ? "<span class='update-plugins count-$message_count' title='$message_count'><span class='update-count'>" . (int)$message_count . "</span></span>" : '');

        add_menu_page( __( 'Support Hub Inbox', 'support_hub' ), $menu_label, 'edit_pages', 'support_hub_main', array($this, 'show_inbox'), 'dashicons-format-chat', "21.1" );


        // hack to rmeove default submenu
        $menu_label = sprintf( __( 'Inbox %s', 'support_hub' ), $message_count > 0 ? "<span class='update-plugins count-$message_count' title='$message_count'><span class='update-count'>" . number_format_i18n($message_count) . "</span></span>" : '' );
		$page = add_submenu_page('support_hub_main', __( 'Support Hub Inbox', 'support_hub' ), $menu_label, 'edit_pages',  'support_hub_main' , array($this, 'show_inbox'));
		add_action( 'admin_print_styles-'.$page, array( $this, 'inbox_assets' ) );
        add_action("load-$page", array( $this, 'screen_options' ));

		//$page = add_submenu_page('support_hub_main', __( 'Compose', 'support_hub' ), __('Compose' ,'support_hub'), 'edit_pages',  'support_hub_compose' , array($this, 'show_compose'));
		//add_action( 'admin_print_styles-'.$page, array( $this, 'inbox_assets' ) );

		//$page = add_submenu_page('support_hub_main', __( 'Sent', 'support_hub' ), __('Sent' ,'support_hub'), 'edit_pages',  'support_hub_sent' , array($this, 'show_sent'));
		//add_action( 'admin_print_styles-'.$page, array( $this, 'inbox_assets' ) );

        $pending_messages = SupportHubOutbox::get_pending();
        $failed_messages = SupportHubOutbox::get_failed();
        $outbox_message_count = count($pending_messages) + count($failed_messages);
        $menu_label = sprintf( __( 'Outbox %s', 'support_hub' ), "<span class='update-plugins' title='$outbox_message_count'><span class='update-count' id='shub_menu_outbox_count' data-count='$outbox_message_count'>" . $outbox_message_count . "</span></span>" );
		$page = add_submenu_page('support_hub_main', __( 'Outbox', 'support_hub' ), $menu_label, 'edit_pages',  'support_hub_outbox' , array($this, 'show_outbox'));
		add_action( 'admin_print_styles-'.$page, array( $this, 'inbox_assets' ) );


        $page = add_submenu_page('support_hub_main', __( 'Dashboard', 'support_hub' ), __('Dashboard' ,'support_hub'), 'edit_pages',  'support_hub_dashboard' , array($this, 'show_dashboard'));
        add_action( 'admin_print_styles-'.$page, array( $this, 'inbox_assets' ) );

		$page = add_submenu_page('support_hub_main', __( 'Settings', 'support_hub' ), __( 'Settings', 'support_hub' ), 'edit_pages',  'support_hub_settings' , array($this, 'show_settings'));
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
	public function show_dashboard(){
		if($this->is_setup()){
            include( trailingslashit( $this->dir ) . 'pages/dashboard.php');
		}else{
			include( trailingslashit( $this->dir ) . 'pages/setup.php');
		}
	}
	public function show_outbox(){
		if($this->is_setup()){
            if(isset($_GET['network'])){
                include( trailingslashit( $this->dir ) . 'pages/message.php');
            }else{
                include( trailingslashit( $this->dir ) . 'pages/outbox.php');
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

    public function send_outbox_messages($debug = false){
        // find any pending outbox messages and send them
        // return stats on the messages left for update in the UI
        $pending_messages = SupportHubOutbox::get_pending();
        if(count($pending_messages)){
            foreach($pending_messages as $pending_message){
                $pending = new SupportHubOutbox($pending_message['shub_outbox_id']);
                $pending->send_queued();
            }
        }
    }

	public function cron_new_interval($interval){
		$interval['minutes_5'] = array('interval' => 5 * 60, 'display' => 'Once 5 minutes');
	    return $interval;
	}

	function cron_run( $debug = false ){
		// running the cron job every 10 minutes.
		// we get a list of accounts and refresh them all.
		@set_time_limit(0);
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
			'from' => $last_cron_task ? $last_cron_task : 'start',
		));

        // send any messages from the outbox
        $this->send_outbox_messages($debug);

		foreach($this->message_managers as $name => $message_manager) {
            if ($message_manager->is_enabled()) {
                if ($last_cron_task) {
                    if ($last_cron_task['name'] == $name) {
                        // we got here last time, continue off from where we left
                        $last_cron_task = false;
                    } else {
                        // keep hunting for the cron job we were up to last time.
                        continue;
                    }
                }
                // recording where we get up to in the (sometimes very long) cron tasks.
                update_option('last_support_hub_cron', array(
                    'name' => $name,
                    'time' => time(),
                ));
                $this->log_data(0, 'cron', 'Starting Extension Cron: ' . $name);
                if(!isset($_REQUEST['debug_shub_cron'])){
                    $message_manager->run_cron($debug, $cron_timeout, $cron_start);
                }else{
                    echo 'Starting Extension Cron: ' . $name."<br>\n";
                }
                // this cron job has completed successfully.
                // if we've been running more than timeout, quit.
                if ($cron_start + $cron_timeout < time()) {
                    $cron_completed = false;
                    break;
                }
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

	public function get_message_user_summary($user_hints, $current_extension, $message_object){
		// here we hunt down other messages from this user and bring them back to the UI

        $return = array(
            'extra_datas' => array(),
            'user_details' => array(),
        );

		$user_details = array();
		$other_messages = array(); // messages started by this user

        if(!empty($user_hints['shub_user_id']) && !is_array($user_hints['shub_user_id'])){
            $user_hints['shub_user_id'] = array($user_hints['shub_user_id']);
        }

		$user_ids = count($user_hints['shub_user_id']);
		?>
		<div class="shub_debug">
			Starting User Hints: <br/>
			<?php print_r($user_hints); ?>
		</div>
		<?php

        $possible_new_user_ids = array();
        do {
            $this_user_hints = $user_hints;
            if(count($possible_new_user_ids)) {
                $this_user_hints['shub_user_id'] = array();
                foreach($possible_new_user_ids as $possible_new_user_id){
                    if(!in_array($possible_new_user_id, $user_hints['shub_user_id'])){
                        $user_hints['shub_user_id'][] = $possible_new_user_id;
                        $this_user_hints['shub_user_id'][] = $possible_new_user_id;
                    }
                }
                $possible_new_user_ids = array();
            }
            foreach ($this->message_managers as $name => $message_manager) {
                $details = $message_manager->find_other_user_details($this_user_hints, $current_extension, $message_object);


                if ($details && isset($details['messages']) && is_array($details['messages'])) {
                    $other_messages = array_merge_recursive($other_messages, $details['messages']);
                }
                if ($details && isset($details['user']) && is_array($details['user'])) {
                    $user_details = array_merge_recursive($user_details, $details['user']);
                }
                if ($details && isset($details['user_ids']) && is_array($details['user_ids'])) {
                    foreach ($details['user_ids'] as $possible_new_user_id) {
                        if((int)$possible_new_user_id > 0 && !in_array($possible_new_user_id, $user_hints['shub_user_id'])){
                            //echo "GOT A NEW USER ID BACK $possible_new_user_id <br><br>";
                            // re-run the search loop with this new user id t oget even more data.
                            $possible_new_user_ids[] = $possible_new_user_id;
                        }
                    }
                }
            }
        }while(count($possible_new_user_ids));

		if(count($user_hints['shub_user_id']) != $user_ids) {
			?>
			<div class="shub_debug">
				Possible New User Ids: <br/>
				<?php print_r( $user_hints ); ?>
			</div>
			<?php
		}

		// pull out the 'extra data' linked to this ticket
		$extras = SupportHubExtra::get_all_extras();
		$extra_data_duplicate_check = array();
		foreach($extras as $extra_id => $extra){
            $shub_user_ids = !empty($user_hints['shub_user_id']) ? $user_hints['shub_user_id'] : array(0);
            // stop duplicate values (if a user submits two support tickets with different email addresses and the same data, the extra data will be inserted into the database under his new user account, but it will show up here as a linked data info account, so we prevent duplicate data showing in this step)
            // todo: highlight data related to this support message, fade out data related to other support messages
            foreach($shub_user_ids as $shub_user_id) {
                $this_extras = array();
                foreach($extra->get_data($current_extension, $message_object->get('shub_account_id'), $message_object->get('shub_message_id'), $shub_user_id) as $this_extra){
	                //echo " $shub_user_id - ".$this_extra->get('extra_value')."<br>";
                    if(!in_array($this_extra->get('extra_value'),$extra_data_duplicate_check)){
                        $this_extras[] = $this_extra;
	                    $extra_data_duplicate_check[] = $this_extra->get('extra_value');
                    }
                    // build up the list of linked user accounts based on user data.
                    // example: if two users submit
                    $extra_shub_user_id = $this_extra->get('shub_user_id');
                    if(!empty($extra_shub_user_id) && !in_array($extra_shub_user_id,$user_hints['shub_user_id'])){
	                    // this happens when the admin might reply in the 'extra data' area and cause an extra data entry to be generated for the admin shub_user_id
                        echo " <br><br><Br>Error: Did the admin reply to this Extra Data Request? Displaying extra data for the user '$extra_shub_user_id' when that user ID isn't already in the list. Please report this bug to dtbaker.<br><br><br> ";
                        // moved the user_hints loop to the top to build up the user id list first.
                        $user_hints['shub_user_id'][] = $extra_shub_user_id;
                    }
                }
	            $return['extra_datas'] = array_merge($return['extra_datas'],$this_extras);
            }
		}

        $user_bits = array();
        //$user_bits[] = array('Support Pack','UNKNOWN');
        if(!empty($user_hints['shub_user_id'])){
	        $user_hints['shub_user_id'] = array_unique($user_hints['shub_user_id']);
            foreach($user_hints['shub_user_id'] as $shub_user_id) {
                $user = new SupportHubUser($shub_user_id);
                if($user->get('shub_user_id')) {

                    // find messages and comments from this user
                    /*$messages = shub_get_multiple('shub_message', array(
                        'shub_user_id' => $shub_user_id
                    ), 'shub_message_id');*/
                    global $wpdb;
                    $sql = "SELECT sm.*, sa.shub_extension FROM `" . _support_hub_DB_PREFIX . "shub_message` sm ";
                    $sql .= " LEFT JOIN `" . _support_hub_DB_PREFIX . "shub_account` sa USING (shub_account_id) WHERE 1 ";
                    $sql .= " AND sm.`shub_user_id` = " . (int)$user->get('shub_user_id');
                    $sql .= " AND sm.`shub_message_id` != " . (int)$message_object->get('shub_message_id');
                    $sql .= ' ORDER BY shub_message_id';
                    $messages = $wpdb->get_results($sql, ARRAY_A);

                    if (is_array($messages)) {
                        foreach ($messages as $message) {
                            if (!isset($other_messages[$message['shub_message_id']])) {
                                $other_messages[$message['shub_message_id']] = array(
                                    'link' => '?page=support_hub_main&network='.$message['shub_extension'].'&message_id='.$message['shub_message_id'],
                                    'summary' => $message['title'],
                                    'time' => $message['last_active'],
                                    'network' => $message['shub_extension'],
                                    'icon' => $this->message_managers[$message['shub_extension']]->get_friendly_icon(),
                                    'message_id' => $message['shub_message_id'],
                                    'message_comment_id' => 0,
                                    'message_status' => $message['shub_status'],
	                                'primary' => true, // this is the main message created by this user.
                                );
                            }
                        }
                    }
                    /*$comments = shub_get_multiple('shub_message_comment', array(
                        'shub_user_id' => $shub_user_id
                    ), 'shub_message_comment_id');*/

                    $sql = "SELECT smc.*, sa.shub_extension, sm.shub_status, sm.shub_user_id AS parent_shub_user_id FROM `" . _support_hub_DB_PREFIX . "shub_message_comment` smc ";
                    $sql .= " LEFT JOIN `" . _support_hub_DB_PREFIX . "shub_message` sm USING (shub_message_id)  ";
                    $sql .= " LEFT JOIN `" . _support_hub_DB_PREFIX . "shub_account` sa USING (shub_account_id) WHERE 1 ";
                    $sql .= " AND smc.`shub_user_id` = " . (int)$user->get('shub_user_id');
                    $sql .= " AND sm.`shub_message_id` != " . (int)$message_object->get('shub_message_id');
                    $sql .= ' ORDER BY shub_message_comment_id';
                    $comments = $wpdb->get_results($sql, ARRAY_A);

                    if (is_array($comments)) {
                        foreach ($comments as $comment) {
                            if (!isset($other_messages[$comment['shub_message_id']])) {
	                            $other_messages[$comment['shub_message_id']] = array(
                                    'link' => '?page=support_hub_main&network='.$comment['shub_extension'].'&message_id='.$comment['shub_message_id'].'&message_comment_id='.$comment['shub_message_comment_id'],
                                    'summary' => $comment['message_text'],
                                    'time' => $comment['time'],
                                    'network' => $comment['shub_extension'],
                                    'icon' => $this->message_managers[$comment['shub_extension']]->get_friendly_icon(),
                                    'message_id' => $comment['shub_message_id'],
                                    'message_comment_id' => $comment['shub_message_comment_id'],
                                    'message_status' => $comment['shub_status'],
		                            'primary' => ($comment['parent_shub_user_id'] == $user->get('shub_user_id'))
                                );
                            }
                        }
                    }

                    // for debugging:
                    //$user_bits[] = array('shub_user_id',$user->get('shub_user_id'));

                    if (!empty($user->details['user_fname']) && !empty($user->details['user_lname'])) {
                        $user_bits[] = array('Name', esc_html($user->details['user_fname'].' '.$user->details['user_lname']));
                    }else if (!empty($user->details['user_fname'])) {
                        $user_bits[] = array('Name', esc_html($user->details['user_fname']));
                    }else if (!empty($user->details['user_lname'])) {
                        $user_bits[] = array('LName', esc_html($user->details['user_lname']));
                    }
                    if (!empty($user->details['user_username'])) {
                        // add this code in here ( as well as below ) so we don't duplicate up on the 'username' field display
                        if (isset($user_details['url']) && isset($user_details['username']) && $user->details['user_username'] == $user_details['username']) {
                            $user_bits[] = array('Username', '<a href="' . esc_url($user_details['url']) . '" target="_blank">' . esc_html($user_details['username']) . '</a>');
                            unset($user_details['username']); // stop it displaying again below
                        } else if (isset($user_details['username']) && $user->details['user_username'] == $user_details['username']) {
                            $user_bits[] = array('Username', esc_html($user_details['username']));
                            unset($user_details['username']); // stop it displaying again below
                        } else {
                            $user_bits[] = array('Username', esc_html($user->details['user_username']));
                        }
                    }
                    if (!empty($user->details['user_email'])) {
                        $user_bits[] = array('Email', '<a href="mailto:' . esc_html($user->details['user_email']) . '">' . esc_html($user->details['user_email']) . '</a>');
                    }
                }
            }
        }


        //var_export($this->message_managers['envato']->pull_purchase_code(false,'61782ac6-1f67-4302-b34a-17c9e4d4f123',array(),3452)); echo 'done';exit;

		if(isset($user_details['url']) && isset($user_details['username'])){
            if(!is_array($user_details['url']))$user_details['url'] = array($user_details['url']);
            if(!is_array($user_details['username']))$user_details['username'] = array($user_details['username']);
            foreach($user_details['url'] as $key => $url){
                $user_bits[] = array('Username','<a href="'.esc_url($url).'" target="_blank">' . esc_html(isset($user_details['username'][$key]) ? $user_details['username'][$key] : 'N/A') . '</a>');
            }
		}else if(isset($user_details['username'])){
            if(!is_array($user_details['username']))$user_details['username'] = array($user_details['username']);
            foreach($user_details['username'] as $key => $username){
                $user_bits[] =  array('Username',esc_html($username));
            }
		}
        /*
        if(isset($user_details['codes'])){
            // todo: pull in information about this purchase code via ajax after the page has loaded.
            // cache and re-validate the purchase code from time to time.
            $purchase_codes = (is_array($user_details['codes']) ? implode(', ',$user_details['codes']) : $user_details['codes']);
            if(!empty($purchase_codes)){
                $user_bits[] = array('Purchase Codes',$purchase_codes);
            }
        }
        */
        $user_bits = apply_filters('supporthub_message_user_sidebar', $user_bits, $user_hints['shub_user_id']);

        // todo - group user output together nicely (e.g. Name <email>) so it looks better when there are multiple linked user accounts
        foreach($user_bits as $key=>$val){
            // check if this one exists yet?
            foreach($user_bits as $key_check=>$val_check){
                if($key != $key_check && $val[0] == $val_check[0]){
                    // we're matching something like 'Username' with 'Username'
                    if(trim(strip_tags($val[1])) == trim(strip_tags($val_check[1]))){
                        // same same! keep one, the longer one (which might contian a link)
                        if(strlen($val[1]) > strlen($val_check[1])){
                            unset($user_bits[$key_check]);
                        }else{
                            unset($user_bits[$key]);
                        }
                    }
                }
            }
        }

        $return['user_bits'] = $user_bits;
        $return['user_details'] = $user_details;
        $return['other_messages'] = $other_messages;
        $return['shub_user_id'] = $user_hints['shub_user_id'];

        return $return;

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


    public $all_messages = array();
    public $search_params = array();
    public $search_order = array();

    public function load_all_messages($search=array(),$order=array(),$limit_batch=0,$limit_start=0){
        $this->search_params = $search;
        $this->search_order = $order;

        $sql = "SELECT m.*, m.last_active AS `message_time`, mr.read_time, sa.shub_extension FROM `"._support_hub_DB_PREFIX."shub_message` m ";
        $sql .= " LEFT JOIN `"._support_hub_DB_PREFIX."shub_message_read` mr ON ( m.shub_message_id = mr.shub_message_id AND mr.user_id = ".get_current_user_id()." )";
        $sql .= " LEFT JOIN `"._support_hub_DB_PREFIX."shub_account` sa ON ( m.shub_account_id = sa.shub_account_id )";
        $sql .= " LEFT JOIN `"._support_hub_DB_PREFIX."shub_item` si ON ( m.shub_item_id = si.shub_item_id )";
        $sql .= " WHERE 1 ";
        if(!empty($search['extension'])){
            $sql .= " AND sa.`shub_extension` = '".esc_sql($search['extension']) ."'";
        }
        if(isset($search['shub_status']) && $search['shub_status'] !== false){
            $sql .= " AND `shub_status` = ".(int)$search['shub_status'];
        }
        if(isset($search['shub_product_id']) && (int)$search['shub_product_id']){
            $sql .= " AND ( m.`shub_product_id` = ".(int)$search['shub_product_id'];
            $sql .= " OR (m.`shub_product_id` = -1 AND si.shub_product_id = ".(int)$search['shub_product_id'].") )";
        }
        if(isset($search['shub_message_id']) && $search['shub_message_id'] !== false){
            $sql .= " AND m.`shub_message_id` = ".(int)$search['shub_message_id'];
        }
        if(isset($search['shub_account_id']) && $search['shub_account_id'] !== false){
            $sql .= " AND m.`shub_account_id` = ".(int)$search['shub_account_id'];
        }
        if(isset($search['generic']) && !empty($search['generic'])){
            // todo: search item comments too.. not just title (first comment) and summary (last comment)
            $sql .= " AND (`title` LIKE '%".esc_sql($search['generic'])."%'";
            $sql .= " OR `summary` LIKE '%".esc_sql($search['generic'])."%' )";
        }
        if(isset($search['not_in']) && is_array($search['not_in']) && count($search['not_in'])){
            foreach($search['not_in'] as $key=>$val){
                $search['not_in'][$key] = (int)$val;
            }
            $sql .= " AND m.shub_message_id NOT IN ( " . implode(',',$search['not_in']) . " ) ";
        }
        if(empty($order)){
            $sql .= " ORDER BY `priority` DESC, `last_active` ASC ";
        }else{
            switch($order['orderby']){
                case 'shub_column_time':
                    $sql .= " ORDER BY `priority` DESC, `last_active` ";
                    $sql .= $order['order'] == 'asc' ? 'ASC' : 'DESC';
                    break;
            }
        }
        if((int)$limit_batch>0){
            $sql .= " LIMIT ".(int)$limit_start.', '.(int)$limit_batch;
        }
        global $wpdb;
        $this->all_messages = $wpdb->get_results($sql, ARRAY_A);
        return $this->all_messages;
    }

    public function get_unread_count($search=array()){
        if(!get_current_user_id())return 0;
        $sql = "SELECT count(*) AS `unread` FROM `"._support_hub_DB_PREFIX."shub_message` m ";
        $sql .= " WHERE 1 ";
        $sql .= " AND m.shub_message_id NOT IN (SELECT mr.shub_message_id FROM `"._support_hub_DB_PREFIX."shub_message_read` mr WHERE mr.user_id = '".(int)get_current_user_id()."' AND mr.shub_message_id = m.shub_message_id)";
        $sql .= " AND m.`shub_status` = "._shub_MESSAGE_STATUS_UNANSWERED;
        $res = shub_qa1($sql);
        return $res ? $res['unread'] : 0;
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


CREATE TABLE {$wpdb->prefix}shub_account (
  shub_account_id int(11) NOT NULL AUTO_INCREMENT,
  shub_extension varchar(40) NOT NULL DEFAULT '',
  account_name varchar(50) NOT NULL,
  shub_user_id int(11) NOT NULL DEFAULT '0',
  last_checked int(11) NOT NULL DEFAULT '0',
  account_data longtext NOT NULL,
  PRIMARY KEY  shub_account_id (shub_account_id)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;

CREATE TABLE {$wpdb->prefix}shub_message (
  shub_message_id int(11) NOT NULL AUTO_INCREMENT,
  shub_account_id int(11) NOT NULL,
  shub_product_id int(11) NOT NULL DEFAULT '-1',
  shub_item_id int(11) NOT NULL DEFAULT '-1',
  post_id int(11) NOT NULL,
  network_key varchar(255) NOT NULL,
  summary text NOT NULL,
  title text NOT NULL,
  last_active int(11) NOT NULL DEFAULT '0',
  comments text NOT NULL,
  shub_type varchar(20) NOT NULL,
  shub_link varchar(255) NOT NULL,
  shub_data text NOT NULL,
  shub_status tinyint(1) NOT NULL DEFAULT '0',
  priority int(2) NOT NULL DEFAULT '0',
  user_id int(11) NOT NULL DEFAULT '0',
  shub_user_id int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY  shub_message_id (shub_message_id),
  KEY shub_account_id (shub_account_id),
  KEY last_active (last_active),
  KEY shub_item_id (shub_item_id),
  KEY shub_product_id (shub_product_id),
  KEY network_key (network_key),
  KEY shub_user_id (shub_user_id),
  KEY post_id (post_id),
  KEY shub_status (shub_status)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;



CREATE TABLE {$wpdb->prefix}shub_message_read (
  shub_message_id int(11) NOT NULL,
  read_time int(11) NOT NULL DEFAULT '0',
  user_id int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY  shub_message_id (shub_message_id,user_id)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;


CREATE TABLE {$wpdb->prefix}shub_message_comment (
  shub_message_comment_id int(11) NOT NULL AUTO_INCREMENT,
  shub_message_id int(11) NOT NULL,
  network_key varchar(255) NOT NULL,
  time int(11) NOT NULL,
  message_from text NOT NULL,
  message_to text NOT NULL,
  message_text text NOT NULL,
  data text NOT NULL,
  user_id int(11) NOT NULL DEFAULT '0',
  private tinyint(1) NOT NULL DEFAULT '0',
  shub_user_id int(11) NOT NULL DEFAULT '0',
  shub_outbox_id int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY  shub_message_comment_id (shub_message_comment_id),
  KEY shub_message_id (shub_message_id),
  KEY shub_user_id (shub_user_id),
  KEY network_key (network_key)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;


CREATE TABLE {$wpdb->prefix}shub_message_link (
  shub_message_link_id int(11) NOT NULL AUTO_INCREMENT,
  shub_message_id int(11) NOT NULL DEFAULT '0',
  link varchar(255) NOT NULL,
  PRIMARY KEY  shub_message_link_id (shub_message_link_id),
  KEY shub_message_id (shub_message_id)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;

CREATE TABLE {$wpdb->prefix}shub_message_link_click (
  shub_message_link_click_id int(11) NOT NULL AUTO_INCREMENT,
  shub_message_link_id int(11) NOT NULL DEFAULT '0',
  click_time int(11) NOT NULL,
  ip_address varchar(20) NOT NULL,
  user_agent varchar(100) NOT NULL,
  url_referrer varchar(255) NOT NULL,
  PRIMARY KEY  shub_message_link_click_id (shub_message_link_click_id),
  KEY shub_message_link_id (shub_message_link_id)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;


CREATE TABLE {$wpdb->prefix}shub_outbox (
  shub_outbox_id int(11) NOT NULL AUTO_INCREMENT,
  shub_extension varchar(40) NOT NULL DEFAULT '',
  shub_account_id int(11) NOT NULL DEFAULT '0',
  shub_message_id int(11) NOT NULL DEFAULT '0',
  shub_message_comment_id int(11) NOT NULL DEFAULT '0',
  queue_time int(11) NOT NULL DEFAULT '0',
  shub_status int(11) NOT NULL DEFAULT '0',
  message_data text NOT NULL,
  PRIMARY KEY  shub_outbox_id (shub_outbox_id),
  KEY shub_status (shub_status),
  KEY shub_message_id (shub_message_id),
  KEY shub_account_id (shub_account_id)
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


CREATE TABLE {$wpdb->prefix}shub_item (
  shub_item_id int(11) NOT NULL AUTO_INCREMENT,
  network_key varchar(255) NOT NULL,
  shub_account_id int(11) NOT NULL DEFAULT '0',
  shub_product_id int(11) NOT NULL DEFAULT '0',
  item_name varchar(50) NOT NULL,
  last_message int(11) NOT NULL DEFAULT '0',
  last_checked int(11) NOT NULL,
  item_data text NOT NULL,
  PRIMARY KEY  shub_item_id (shub_item_id),
  KEY shub_account_id (shub_account_id)
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
  shub_extension varchar(40) NOT NULL DEFAULT '',
  shub_account_id int(11) NOT NULL DEFAULT '0',
  shub_message_id int(11) NOT NULL DEFAULT '0',
  shub_extension_user_id int(11) NOT NULL DEFAULT '0',
  KEY shub_extra_data_id (shub_extra_data_id),
  KEY shub_extension (shub_extension),
  KEY shub_account_id (shub_account_id),
  KEY shub_message_id (shub_message_id),
  KEY shub_extension_user_id (shub_extension_user_id),
  KEY shub_extra_id (shub_extra_id)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;

EOT;
        $hash = md5($sql);
        if(get_option("support_hub_db_hash") != $hash){

            // rename some table fields. opps!
            $rename = array(
                'shub_message' => array(
                    "type"=>array(
                        "shub_type",
                        "varchar(20) NOT NULL DEFAULT ''",
                    ),
                    "status"=>array(
                        "shub_status",
                        "tinyint(1) NOT NULL DEFAULT '0'",
                    ),
                    "link"=>array(
                        "shub_link",
                        "varchar(255) NOT NULL DEFAULT ''",
                    ),
                    "data"=>array(
                        "shub_data",
                        "longtext NOT NULL",
                    ),
                ),
                'shub_outbox' => array(
                    "status"=>array(
                        "shub_status",
                        "tinyint(1) NOT NULL DEFAULT '0'",
                    ),
                ),
            );

            foreach($rename as $table_name => $fields_to_rename) {

                $suppress = $wpdb->suppress_errors();
                $tablefields = $wpdb->get_results("DESCRIBE {$wpdb->prefix}{$table_name};");
                $wpdb->suppress_errors($suppress);

                if ($tablefields) {
                    foreach ($fields_to_rename as $rename_from => $rename_to_settings) {
                        $rename_to = $rename_to_settings[0];
                        $rename_args = $rename_to_settings[1];
                        $rename_done = false;
                        $field_exists = false;
                        foreach ($tablefields as $tablefield) {
                            if ($tablefield->Field == $rename_to) {
                                $rename_done = true;
                            }
                            if ($tablefield->Field == $rename_from) {
                                $field_exists = true;
                            }
                        }
                        if (!$rename_done && $field_exists) {
                            // do the rename.
                            $change_sql = "ALTER TABLE {$wpdb->prefix}{$table_name} CHANGE COLUMN `{$rename_from}` `{$rename_to}` {$rename_args}";
                            $wpdb->query($change_sql);
                        }
                    }
                }
            }

            $this->activation($sql);
            $this->log_data(0,'core','Ran SQL Update');
            update_option( "support_hub_db_hash", $hash );
        }
    }
    public static function deactivation(){
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


	public function get_message_object($shub_message_id){
		$message_temp = shub_get_single('shub_message','shub_message_id',$shub_message_id);
		if($message_temp && !empty($message_temp['shub_account_id'])) {
			$account_temp = shub_get_single( 'shub_account', 'shub_account_id', $message_temp['shub_account_id'] );
			if ( $account_temp && ! empty( $account_temp['shub_extension'] ) ) {
				$network = $account_temp['shub_extension'];
				if(isset($this->message_managers[$network])){
					return $this->message_managers[$network]->get_message(false, false, $shub_message_id);
				}
			}
		}
		return false;
	}


}
