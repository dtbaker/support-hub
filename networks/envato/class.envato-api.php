<?php

class envato_api_basic{

	private static $instance = null;
	public static function getInstance () {
        if (is_null(self::$instance)) { self::$instance = new self(); }
        return self::$instance;
    }

	private $_api_url = 'https://api.envato.com/v1/';

	private $_personal_token = false;
	private $_cookie = false;

	public function set_personal_token($token){
		$this->_personal_token = $token;
	}
	public function set_cookie($cookie){
		$this->_cookie = $cookie;
	}
	public function api($endpoint, $params=array()){
		$response     = wp_remote_get($this->_api_url . $endpoint, array(
		    'user-agent' => 'SupportHub WP Plugin',
		    'headers' => array(
		        'Authorization' => 'Bearer ' . $this->_personal_token,
		    ),
		));
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

	public function post_comment($url, $comment_id, $comment_text){
		/**
		 * POST: http://codecanyon.net/item/ultimate-client-manager-crm-pro-edition/2621629/comments
utf8:✓
authenticity_token:84GHCY+pDGkuuqMKHtY2WWwkg2Q1dnK41vY27pIlxeM=
parent_id:10086214
content:Could you setup the 2€ stamp under Settings > Products, and then you can quickly add the "stamp" product to every invoice that is sent out?
		 *
		 * Response: {"status":"ok","partial":"\u003Cdiv class=\"comment-reply \" id=\"comment_10105971\"\u003E\n  \u003Ca href=\"/user/dtbaker\" class='comment-reply__avatar'\u003E\n    \u003Cimg alt=\"dtbaker\" height=\"30\" src=\"https://0.s3.envato.com/files/111547951/dtbaker-php-scripts-wordpress-themes-and-plugins.png\" width=\"30\" /\u003E\n\u003C/a\u003E\n\n\n  \u003Cdiv class=\"comment-reply__body js-comment\"\u003E\n      \u003Cdiv class=\"comment__header\"\u003E\n        \u003Ca href=\"/user/dtbaker\" class=\"comment__username\"\u003Edtbaker\u003C/a\u003E\n          \u003Cspan class=\"text-label-beta -color-grey -size-small -position-top -link\"\u003EAuthor\u003C/span\u003E\n\n\n        \u003Cdiv class=\"js-comment-tools comment__meta\"\u003E\n          \u003Ca href=\"#comment_10105971\" class=\"comment__date\"\u003E1 minute ago\u003C/a\u003E\n          \n    \u003Ca href=\"/complaints/new?complainable_id=10105971\u0026amp;complainable_type=Comment\u0026amp;ret=http%3A%2F%2Fcodecanyon.net%2Fitem%2Fultimate-client-manager-crm-pro-edition%2F2621629%2Fcomments\" class=\"comment__action js-post-flag\"\u003E\n      \u003Ci class=\"e-icon -icon-flag\"\u003E\u003Cspan class=\"e-icon__alt\"\u003EFlag\u003C/span\u003E\u003C/i\u003E\n\u003C/a\u003E\n    \u003Ca href=\"/comments/10105971/edit\" class=\"comment__action js-comment-edit\"\u003E\n      \u003Ci class=\"e-icon -icon-pencil\"\u003E\u003Cspan class=\"e-icon__alt\"\u003EEdit\u003C/span\u003E\u003C/i\u003E\n\u003C/a\u003E\n\n        \u003C/div\u003E\n      \u003C/div\u003E\n\n      \u003Cdiv class=\"js-comment-content\"\u003E\n        \u003Cdiv class=\"user-html\"\u003E\u003Cp\u003ECould you setup the 2\u20ac stamp under Settings \u0026gt; Products, and then you can quickly add the \u0026#8220;stamp\u0026#8221; product to every invoice that is sent out?\u003C/p\u003E\u003C/div\u003E\n      \u003C/div\u003E\n\n      \u003Cdiv class=\"js-inject-target comment-inline-form\"\u003E\u003C/div\u003E\n  \u003C/div\u003E\n\u003C/div\u003E"}
		 */

		// login to the market using our account cookie.
		$url = 'https://account.envato.com/sign_in?auto=true&to=themeforest';
		$data = $this->get_url($url);
		echo $data;exit;
	}

	private $ch = false;
	private $cookies = array();
	public function get_url($url, $post = false) {

		if ( $this->ch ) {
			curl_close( $this->ch );
		}
		if ( ! function_exists( 'curl_init' ) ) {
			echo 'Please contact hosting provider and enable CURL for PHP';
			return false;
		}
		$this->cookies['envatosession'] = $this->_cookie;
		$this->ch = curl_init();
		curl_setopt( $this->ch, CURLOPT_RETURNTRANSFER, true );
		@curl_setopt( $this->ch, CURLOPT_FOLLOWLOCATION, true );
		curl_setopt( $this->ch, CURLOPT_CONNECTTIMEOUT, 3 );
		curl_setopt( $this->ch, CURLOPT_TIMEOUT, 10 );
		curl_setopt( $this->ch, CURLOPT_USERAGENT, "dtbaker Support Hub Envato Comment" );
		curl_setopt($this->ch, CURLOPT_COOKIE, implode('; ',$this->cookies));
		curl_setopt($this->ch, CURLOPT_HEADERFUNCTION, array($this, "curl_header_callback"));

		curl_setopt( $this->ch, CURLOPT_URL, $url );

		if ( is_array( $post ) && count( $post ) ) {
			curl_setopt( $this->ch, CURLOPT_POST, true );
			curl_setopt( $this->ch, CURLOPT_POSTFIELDS, $post );
		} else {
			curl_setopt( $this->ch, CURLOPT_POST, 0 );
			//curl_setopt($this->ch, CURLOPT_POSTFIELDS, '');
		}
		return curl_exec( $this->ch );
	}

	public function curl_header_callback($ch, $headerLine) {
	    if (preg_match('/^Set-Cookie:\s*([^;]*)/mi', $headerLine, $cookie) == 1){
		    $bits = explode('=',$cookie);
		    $this->cookies[$bits[0]] = $bits[1];
		    print_r($this->cookies);
	    }
	    return strlen($headerLine); // Needed by curl
	}

}