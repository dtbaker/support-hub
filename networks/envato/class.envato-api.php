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

	public function post_comment($comment_url, $parent_comment_id, $comment_text){
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
		$marketplace = 'themeforest';
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
	public function curl_init(){
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
		if(!$this->cookie_file){
			$this->cookie_file = tempnam(sys_get_temp_dir(),'SupportHub');
		}
		curl_setopt($this->ch, CURLOPT_COOKIEJAR, $this->cookie_file);
        curl_setopt($this->ch, CURLOPT_COOKIEFILE, $this->cookie_file);
		curl_setopt($this->ch, CURLOPT_HEADERFUNCTION, array($this, "curl_header_callback"));
	}
	public function curl_done(){
		@unlink($this->cookie_file);
	}
	public function get_url($url, $post = false) {

		if($this->ch){
			curl_close($this->ch);
		}
		$this->curl_init();

		$cookies = '';
		$this->cookies['envatosession'] = $this->_cookie;
		foreach($this->cookies as $key=>$val){
			if(!strpos($url,'account.envato') && $key == 'envatosession')continue;
			$cookies = $cookies . $key . '=' . $val.'; ';
		}
		curl_setopt($this->ch, CURLOPT_COOKIE, $cookies);

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
		//echo $headerLine."\n";
	    if (preg_match('/^Set-Cookie:\s*([^;]*)/mi', $headerLine, $cookie) == 1){
		    $bits = explode('=',$cookie[1]);
		    $this->cookies[$bits[0]] = $bits[1];
	    }
	    return strlen($headerLine); // Needed by curl
	}

}