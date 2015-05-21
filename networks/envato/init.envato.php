<?php


add_filter('shub_managers', 'shub_managers_envato');
function shub_managers_envato( $shub ){
	if(get_option('shub_manager_enabled_envato',0)){
		define('_support_hub_envato_LINK_REWRITE_PREFIX','shenvatolnk');
		require_once 'class.shub_envato.php';
		require_once 'class.shub_envato_account.php';
		require_once 'class.shub_envato_item.php';
		require_once 'class.shub_envato_message.php';
		$shub['envato'] = new shub_envato();
	}else{
		$shub['envato'] = new SupportHub_network();
	}
	$shub['envato']->id = 'envato';
	$shub['envato']->friendly_name = 'Envato';
	$shub['envato']->desc = 'Import and Reply to Envato item messages.';
	return $shub;
}





