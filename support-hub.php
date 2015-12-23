<?php
/*
 * Plugin Name: Support Hub
 * Version: 1.1
 * Plugin URI: http://supporthub.co
 * GitHub Plugin URI: dtbaker/support-hub
 * Description: Provide support from within WordPress
 * Author: dtbaker
 * Author URI: http://dtbaker.net
 * Requires at least: 4.2
 * Tested up to: 4.3
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
define('_shub_MESSAGE_STATUS_HIDDEN',9);

define('_SUPPORT_HUB_LOG_INFO',0);
define('_SUPPORT_HUB_LOG_ERROR',2);
define('_SUPPORT_HUB_LINK_REQUEST_EXTRA','shrequestextra');
define('_SUPPORT_HUB_LINK_REWRITE_PREFIX','shublnk');
define('_SUPPORT_HUB_PASSWORD_FIELD_FUZZ','-password-');

define('_DTBAKER_SUPPORT_HUB_CORE_FILE_',__FILE__);


define('SUPPORT_HUB_DEBUG',true);

// Include core files that do all the magic
require_once( 'classes/class-support-hub.php' );
require_once( 'classes/class-support-hub-table.php' );
require_once( 'classes/class-support-hub-extension.php' );
require_once( 'classes/class-support-hub-account.php' );
require_once( 'classes/class-support-hub-message.php' );
require_once( 'classes/class-support-hub-item.php' );
require_once( 'classes/class-support-hub-outbox.php' );
require_once( 'classes/class-support-hub-product.php' );
require_once( 'classes/class-support-hub-user.php' );
require_once( 'classes/class-support-hub-extra.php' );
require_once( 'classes/ucm.database.php' );
require_once( 'classes/ucm.form.php' );
require_once( 'vendor/autoload.php' );

// include the different network plugins:
// these plugins hook on 'shub_init' to add their instance to the global 'message_manager' variable
// 3rd party plugins can hook into shub_init to add their own 'message_manager'
$base_extensions = array(
    'envato',
    'twitter',
    'facebook',
    'google',
    'bbpress',
    'ucm',
);
foreach($base_extensions as $base_extension){
    if(file_exists(__DIR__.'/extensions/'.$base_extension.'/init.'.$base_extension.'.php')){
        require_once __DIR__.'/extensions/'.$base_extension.'/init.'.$base_extension.'.php';
    }
}


// commence the awesome:
SupportHub::getInstance( _DTBAKER_SUPPORT_HUB_CORE_FILE_ );