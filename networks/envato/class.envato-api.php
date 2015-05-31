<?php


/**
 * Exception handling class.
 */
class EnvatoException extends Exception {}


class envato_api_basic{

	private static $instance = null;
	public static function getInstance () {
        if (is_null(self::$instance)) { self::$instance = new self(); }
        return self::$instance;
    }

	private $_api_url = 'https://api.envato.com/v1/';

	private $_client_id = false;
	private $_client_secret = false;
	private $_personal_token = false;
	private $_redirect_url = false;
	private $_cookie = false;
	private $token = false; // token returned from oauth

	public function set_client_id($token){
		$this->_client_id = $token;
	}
	public function set_client_secret($token){
		$this->_client_secret = $token;
	}
	public function set_personal_token($token){
		$this->_personal_token = $token;
	}
	public function set_redirect_url($token){
		$this->_redirect_url = $token;
	}
	public function set_cookie($cookie){
		$this->_cookie = $cookie;
	}
	public function api($endpoint, $params=array(), $personal = true){
		$headers = array(
		    'user-agent' => 'SupportHub WP Plugin',
		);
		if($personal && !empty($this->_personal_token)){
			$headers['headers'] = array(
		        'Authorization' => 'Bearer ' . $this->_personal_token,
		    );
		}else if(!empty($this->token['access_token'])){
			$headers['headers'] = array(
		        'Authorization' => 'Bearer ' . $this->token['access_token'],
		    );
		}
		$response     = wp_remote_get($this->_api_url . $endpoint, $headers);
		if( is_array($response) && isset($response['body']) && isset($response['response']['code']) && $response['response']['code'] == 200 ) {
			SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_INFO, 'envato', 'API Call: '.$endpoint,$response['body']);
		    $header = $response['headers'];
		    $body = @json_decode($response['body'],true);
			return $body;
		}else if(is_array($response) && isset($response['response']['code']) && $response['response']['code']){
			SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_ERROR, 'envato', 'API Error: '.$endpoint. ' '.(isset($response['response']['code']) ? $response['response']['code'] .' / ': '').(isset($response['body']) ? $response['body'] : ''), $response);
		}else if(is_wp_error($response)){
			SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_ERROR, 'envato', 'API Error: '.$endpoint. ' '.$response->get_error_message());
		}
		return false;
	}

	public function post_comment($comment_url, $parent_comment_id, $comment_text){

		// login to the market using our account cookie.
		$bits = parse_url($comment_url);
		$marketplace = str_replace('.net','',$bits['host']);
		SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_INFO,'envato','API Comment Auth on '.$marketplace);
//		$url = 'https://account.envato.com/';
//		$response = $this->get_url($url);
		$url = 'https://account.envato.com/sign_in?auto=true&to='.$marketplace;
		$response = $this->get_url($url);
		if(
            preg_match('#verify_token#',$response) &&
            preg_match('#"authenticity_token" type="hidden" value="([^"]+)"#',$response,$verify_auth) &&
            preg_match('#"token" type="hidden" value="([^"]+)"#',$response,$verify_token)
        ){
            $verify_result = $this->get_url('http://'.$marketplace.'.net/sso/verify_token',array(
                "authenticity_token" => $verify_auth[1],
                "token" => $verify_token[1],
                "utf8" => '&#x2713;',
                "commit" => 'Click here',
            )); //
			if(strpos($verify_result,'/account.envato.com/sign_out')){
				// it worked! we are now authenticated with $marketplace
				SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_INFO,'envato','API Comment Auth Success with '.$marketplace);
				// send a comment.
				$page = $this->get_url($comment_url);
				if(preg_match('#content="([^"]+)" name="csrf-token"#',$page,$matches)){
		            $new_auth_token = $matches[1];
					$comment_result = $this->get_url($comment_url,array(
		                "authenticity_token" => $new_auth_token,
		                "utf8" => '&#x2713;',
		                "parent_id" => $parent_comment_id,
		                "content" => $comment_text,
		            ),array(
						'X-Requested-With: XMLHttpRequest',
					));

					$json = json_decode($comment_result,true);
					if(!empty($json['status']) && $json['status'] == 'ok' && !empty($json['partial'])){
						if(preg_match('#id="comment_(\d+)"#',$json['partial'],$matches)){
							SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_INFO,'envato','API Comment Posting Success Reply to '.$parent_comment_id, $json);
							$new_comment_id = $matches[1];
							$this->curl_done();
							return $new_comment_id;
						}
					}else{
						SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_ERROR,'envato','API Comment Posting Failure', $comment_result);
					}
		        }else{
					SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_ERROR,'envato','Failed to get CSRF token', $page);
				}
			}else{
				SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_ERROR,'envato','API Comment Failed To Get Second Verify Auth');
			}
        }else{
			SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_ERROR,'envato','API Comment Failed To Get Initial Auth');
		}
		$this->curl_done();
		return false;
	}

	private $ch = false;
	private $cookies = array();
	private $cookie_file = false;
	public function curl_init($cookies = true) {
		if ( ! function_exists( 'curl_init' ) ) {
			echo 'Please contact hosting provider and enable CURL for PHP';

			return false;
		}
		$this->ch = curl_init();
		curl_setopt( $this->ch, CURLOPT_RETURNTRANSFER, true );
		@curl_setopt( $this->ch, CURLOPT_FOLLOWLOCATION, true );
		curl_setopt( $this->ch, CURLOPT_CONNECTTIMEOUT, 10 );
		curl_setopt( $this->ch, CURLOPT_TIMEOUT, 20 );
		curl_setopt( $this->ch, CURLOPT_HEADER, false );
		curl_setopt( $this->ch, CURLOPT_USERAGENT, "Support Hub dtbaker" );
		if ( $cookies ) {
			if ( ! $this->cookie_file ) {
				$this->cookie_file = tempnam( sys_get_temp_dir(), 'SupportHub' );
			}
			curl_setopt( $this->ch, CURLOPT_COOKIEJAR, $this->cookie_file );
			curl_setopt( $this->ch, CURLOPT_COOKIEFILE, $this->cookie_file );
			curl_setopt( $this->ch, CURLOPT_HEADERFUNCTION, array( $this, "curl_header_callback" ) );
		}
	}
	public function curl_done(){
		@unlink($this->cookie_file);
	}
	public function get_url($url, $post = false, $extra_headers = array(), $cookies = true) {

		if($this->ch){
			curl_close($this->ch);
		}
		$this->curl_init($cookies);

		if($cookies) {
			$cookies                        = '';
			$this->cookies['envatosession'] = $this->_cookie;
			foreach ( $this->cookies as $key => $val ) {
				if ( ! strpos( $url, 'account.envato' ) && $key == 'envatosession' ) {
					continue;
				}
				$cookies = $cookies . $key . '=' . $val . '; ';
			}
			curl_setopt( $this->ch, CURLOPT_COOKIE, $cookies );
		}

		curl_setopt( $this->ch, CURLOPT_URL, $url );
		if($extra_headers){
			curl_setopt( $this->ch, CURLOPT_HTTPHEADER, $extra_headers);
		}

		if ( is_string( $post ) && strlen( $post ) ) {
			curl_setopt( $this->ch, CURLOPT_POST, true );
			curl_setopt( $this->ch, CURLOPT_POSTFIELDS, $post );
		}else if ( is_array( $post ) && count( $post ) ) {
			curl_setopt( $this->ch, CURLOPT_POST, true );
			curl_setopt( $this->ch, CURLOPT_POSTFIELDS, $post );
		} else {
			curl_setopt( $this->ch, CURLOPT_POST, 0 );
		}
		return curl_exec( $this->ch );
	}

	public function curl_header_callback($ch, $headerLine) {
		//echo $headerLine."\n";
	    if (preg_match('/^Set-Cookie:\s*([^;]*)/mi', $headerLine, $cookie) == 1){
		    $bits = explode('=',$cookie[1]);
		    $this->cookies[$bits[0]] = $bits[1];
	    }
	    return strlen($headerLine); // Needed by curl
	}

	/**
	 * OAUTH STUFF
	 */

	public function get_authorization_url() {
	    return 'https://api.envato.com/authorization?response_type=code&client_id='.$this->_client_id."&redirect_uri=".urlencode($this->_redirect_url);
	  }
	public function get_token_url() {
		return 'https://api.envato.com/token';
    }
	public function get_authentication($code) {
		$url = $this->get_token_url();
		$parameters = array();
		$parameters['grant_type']    = "authorization_code";
		$parameters['code']          = $code;
		$parameters['redirect_uri']  = $this->_redirect_url;
		$parameters['client_id']     = $this->_client_id;
		$parameters['client_secret'] = $this->_client_secret;
		$fields_string = '';
		foreach ( $parameters as $key => $value ) {
			$fields_string .= $key . '=' . urlencode($value) . '&';
		}
		try {
			$response = $this->get_url($url, $fields_string, false, false);
		} catch ( EnvatoException $e ) {
			SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_ERROR, 'envato', 'OAuth API Fail', $e->__toString());
			return false;
		}
		$this->token = json_decode( $response, true );
		return $this->token;
	}
	protected function refresh_token(){
	    $url = $this->get_token_url();

	    $parameters = array();
	    $parameters['grant_type'] = "refresh_token";

	    $parameters['refresh_token']  = $this->token['refresh_token'];
	    $parameters['redirect_uri']   = $this->_redirect_url;
	    $parameters['client_id']      = $this->_client_id;
	    $parameters['client_secret']  = $this->_client_secret;

		$fields_string = '';
		foreach ( $parameters as $key => $value ) {
			$fields_string .= $key . '=' . urlencode($value) . '&';
		}
	    try {
	      $response = $this->get_url($url, $fields_string, false, false);
	    }
	    catch (EnvatoException $e) {
	      SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_ERROR, 'envato', 'OAuth API Fail', $e->__toString());
	      return false;
	    }
	    $new_token = json_decode($response, true);
	    $this->token['access_token'] = $new_token['access_token'];
		return $this->token['access_token'];
	  }



}