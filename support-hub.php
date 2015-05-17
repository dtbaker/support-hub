<?php
/*
 * Plugin Name: Support Hub
 * Version: 1.6
 * Plugin URI: http://supporthub.co
 * Description: Provide support from within WordPress
 * Author: dtbaker
 * Author URI: http://dtbaker.net
 * Requires at least: 4.2
 * Tested up to: 4.2
 *
 * Version 1.1 - 2015-05-15 - initial work
 *
 * @package support-hub
 * @author dtbaker
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

defined('__DIR__') or define('__DIR__', dirname(__FILE__));

define('_shub_MESSAGE_STATUS_UNANSWERED',0);
define('_shub_MESSAGE_STATUS_ANSWERED',1);
define('_shub_MESSAGE_STATUS_PENDINGSEND',3);
define('_shub_MESSAGE_STATUS_SENDING',4);
define('_DTBAKER_SUPPORT_HUB_CORE_FILE_',__FILE__);

// Include plugin class files
require_once( 'classes/class-support-hub.php' );
require_once( 'classes/class-support-hub-table.php' );
require_once( 'classes/class-support-hub-network.php' );
require_once( 'classes/ucm.database.php' );
require_once( 'classes/ucm.form.php' );
require_once( 'vendor/autoload.php' );

// include the different network plugins:
require_once( 'networks/facebook/facebook.class.php' );
require_once( 'networks/twitter/twitter.class.php' );
require_once( 'networks/google/google.class.php' );
require_once( 'networks/linkedin/linkedin.class.php' );

// commence the awesome:
SupportHub::getInstance( _DTBAKER_SUPPORT_HUB_CORE_FILE_ );