<?php


class ucm_api_basic{

	private static $instance = null;
	public static function getInstance () {
        if (is_null(self::$instance)) { self::$instance = new self(); }
        return self::$instance;
    }

	private $_api_url = 'https://api.ucm.com/v1/';

	private $_api_key = false;

	public function set_api_url($api_url){
		$this->_api_url = $api_url;
	}
	public function set_api_key($token){
		$this->_api_key  = $token;
	}

	public function api($endpoint, $method=false, $params=array()){
		$headers = array(
		    'user-agent' => 'SupportHub WP Plugin',
		);
		//$headers['headers'] = array('Authorization' => $this->_api_key,);
        $params['auth'] = $this->_api_key;
		if($params){
			$headers['body'] = $params;
			$response     = wp_remote_post($this->_api_url . (strpos($this->_api_url,'?') ? '&' : '?') . "endpoint=$endpoint&method=$method", $headers);
		}else{
			$response     = wp_remote_get($this->_api_url . (strpos($this->_api_url,'?') ? '&' : '?') . "endpoint=$endpoint&method=$method", $headers);
		}
		if( is_array($response) && isset($response['body']) && isset($response['response']['code']) && $response['response']['code'] == 200 ) {
			SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_INFO, 'ucm', 'API Call: '.$endpoint .'/' .$method,$response['body']);
		    $header = $response['headers'];
		    $body = @json_decode($response['body'],true);
			return $body;
		}else if(is_array($response) && isset($response['response']['code']) && $response['response']['code']){
			SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_ERROR, 'ucm', 'API Error: '.$endpoint. ' '.(isset($response['response']['code']) ? $response['response']['code'] .' / ': '').(isset($response['body']) ? $response['body'] : ''), $response);
		}else if(is_wp_error($response)){
			SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_ERROR, 'ucm', 'API Error: '.$endpoint. ' '.$response->get_error_message());
		}
		return false;
	}



}