<?php


add_filter('shub_managers', 'shub_managers_twitter');
function shub_managers_twitter( $shub ){
	if(get_option('shub_manager_enabled_twitter',0)){
		define('_support_hub_twitter_LINK_REWRITE_PREFIX','ssflnk');
		require_once 'twitter.class.php';
		$shub['twitter'] = new shub_twitter();
	}else{
		$shub['twitter'] = new SupportHub_network();
	}
	$shub['twitter']->id = 'twitter';
	$shub['twitter']->friendly_name = 'Twitter';
	$shub['twitter']->desc = 'Tweet and Reply to DMs and Mentions.';

	return $shub;
}
