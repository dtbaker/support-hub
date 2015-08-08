<?php


add_filter('shub_managers', 'shub_managers_facebook');
function shub_managers_facebook( $shub ){
	if(get_option('shub_manager_enabled_facebook',0)){
		define('_support_hub_FACEBOOK_LINK_REWRITE_PREFIX','ssflnk');
		require_once 'class.shub_facebook.php';
		require_once 'class.shub_facebook_account.php';
		require_once 'class.shub_facebook_group.php';
		require_once 'class.shub_facebook_page.php';
		require_once 'class.shub_facebook_message.php';
		$shub['facebook'] = new shub_facebook();
	}else{
		$shub['facebook'] = new SupportHub_extension();
	}
	$shub['facebook']->id = 'facebook';
	$shub['facebook']->friendly_name = 'Facebook';
	$shub['facebook']->desc = 'Post and Reply to Page, Group and Personal Facebook Messages.';
	return $shub;
}
