<?php


add_filter('shub_managers', 'shub_managers_bbpress');
function shub_managers_bbpress( $shub ){
	if(get_option('shub_manager_enabled_bbpress',0)){
		define('_support_hub_bbpress_LINK_REWRITE_PREFIX','shbbpresslnk');
		require_once 'class.shub_bbpress.php';
		require_once 'class.shub_bbpress_user.php';
		require_once 'class.shub_bbpress_account.php';
		require_once 'class.shub_item.php';
		require_once 'class.shub_bbpress_message.php';
		$shub['bbpress'] = new shub_bbpress();
	}else{
		$shub['bbpress'] = new SupportHub_extension();
	}
	$shub['bbpress']->id = 'bbpress';
	$shub['bbpress']->friendly_name = 'bbPress';
	$shub['bbpress']->desc = 'Import and Reply to WordPress bbPress forum posts.';
	return $shub;
}





