<?php


add_filter('shub_managers', 'shub_managers_google');
function shub_managers_google( $shub ){
	if(get_option('shub_manager_enabled_google',0)){
		require_once 'google.class.php';
		$shub['google'] = new shub_google();
	}else{
		$shub['google'] = new SupportHub_network();
	}
	$shub['google']->id = 'google';
	$shub['google']->friendly_name = 'Google+';
	$shub['google']->desc = 'Post and Reply to Google+ Page Messages.';
	return $shub;
}
