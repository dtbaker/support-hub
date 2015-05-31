<?php
/*
 * Plugin Name: Support Hub
 * Version: 1.1
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
define('_SUPPORT_HUB_LOG_INFO',0);
define('_SUPPORT_HUB_LOG_ERROR',2);
define('_DTBAKER_SUPPORT_HUB_CORE_FILE_',__FILE__);
define('_SUPPORT_HUB_LINK_REQUEST_EXTRA','shrequestextra');

// Include core files that do all the magic
require_once( 'classes/class-support-hub.php' );
require_once( 'classes/class-support-hub-table.php' );
require_once( 'classes/class-support-hub-network.php' );
require_once( 'classes/class-support-hub-product.php' );
require_once( 'classes/class-support-hub-user.php' );
require_once( 'classes/class-support-hub-extra.php' );
require_once( 'classes/ucm.database.php' );
require_once( 'classes/ucm.form.php' );
require_once( 'vendor/autoload.php' );

// include the different network plugins:
// these plugins hook on 'shub_init' to add their instance to the global 'message_manager' variable
// 3rd party plugins can hook into shub_init to add their own 'message_manager'
require_once( 'networks/facebook/init.facebook.php' );
require_once( 'networks/twitter/init.twitter.php' );
require_once( 'networks/google/init.google.php' );
require_once( 'networks/linkedin/init.linkedin.php' );
require_once( 'networks/envato/init.envato.php' );
require_once( 'networks/bbpress/init.bbpress.php' );

// commence the awesome:
SupportHub::getInstance( _DTBAKER_SUPPORT_HUB_CORE_FILE_ );