<?php


add_filter('shub_managers', 'shub_managers_ucm');
function shub_managers_ucm( $shub ){
	if(get_option('shub_manager_enabled_ucm',0)){
		define('_support_hub_ucm_LINK_REWRITE_PREFIX','shucmlnk');
		require_once 'class.shub_ucm.php';
		require_once 'class.shub_ucm_user.php';
		require_once 'class.shub_ucm_account.php';
		require_once 'class.shub_ucm_product.php';
		require_once 'class.shub_ucm_message.php';
		$shub['ucm'] = new shub_ucm();
	}else{
		$shub['ucm'] = new SupportHub_extension();
	}
	$shub['ucm']->id = 'ucm';
	$shub['ucm']->friendly_name = 'UCM';
	$shub['ucm']->desc = 'View and Reply to Ultimate Client Manager Support Tickets.';
	return $shub;
}





