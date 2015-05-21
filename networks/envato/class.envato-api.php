<?php

class envato_api_basic{

	private static $instance = null;
	public static function getInstance () {
        if (is_null(self::$instance)) { self::$instance = new self(); }
        return self::$instance;
    }

	private $_api_url = 'https://api.envato.com/v1/';

	private $_personal_token = false;

	public function set_personal_token($token){
		$this->_personal_token = $token;
	}
	public function api($endpoint, $params=array()){
		$response     = wp_remote_get($this->_api_url . $endpoint, array(
		    'user-agent' => 'SupportHub WP Plugin',
		    'headers' => array(
		        'Authorization' => 'Bearer ' . $this->_personal_token,
		    ),
		));
		if( is_array($response) && isset($response['body']) && isset($response['response']['code']) && $response['response']['code'] == 200 ) {
		    $header = $response['headers'];
		    $body = @json_decode($response['body'],true);
			return $body;
		}else{
			SupportHub::getInstance()->log_data(2, 'envato', 'API Error: '.$endpoint. ' '.(isset($response['response']['code']) ? $response['response']['code'] .' / ': '').(isset($response['body']) ? $response['body'] : ''), $response);
		}
		return false;
	}
}