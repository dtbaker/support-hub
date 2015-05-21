<?php


add_filter('shub_managers', 'shub_managers_linkedin');
function shub_managers_linkedin( $shub ){
	if(get_option('shub_manager_enabled_linkedin',0)){
		define('_support_hub_LINKEDIN_LINK_REWRITE_PREFIX','sslilnk');
		require_once 'class.shub_linkedin.php';
		require_once 'class.shub_linkedin_account.php';
		require_once 'class.shub_linkedin_group.php';
		require_once 'class.shub_linkedin_message.php';
		$shub['linkedin'] = new shub_linkedin();
	}else{
		$shub['linkedin'] = new SupportHub_network();
	}
	$shub['linkedin']->id = 'linkedin';
	$shub['linkedin']->friendly_name = 'LinkedIn';
	$shub['linkedin']->desc = 'Reply to Group and Social Stream messages.';

	return $shub;
}
