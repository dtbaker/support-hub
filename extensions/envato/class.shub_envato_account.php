<?php

class shub_envato_account extends SupportHub_account{

    public function __construct($shub_account_id){
        parent::__construct($shub_account_id);
        $this->shub_extension = 'envato';
        //$this->run_cron();
    }

	public function confirm_token(){
        // we quickly confirm our envato token by loading the my accounts page
        // we pull in the users email and picture from the account page and convert it into a shub_user
        // store this in the shub_user_id field so we can use it when rendering the UI for comments etc..
        $api = $this->get_api();
        $account_data = $api->get_token_account();
        if($account_data && !empty($account_data['username'])){
            // success
            $comment_user = new SupportHubUser_Envato();
            $res = $comment_user->load_by( 'user_username', $account_data['username']);
            if(!$res){
                $comment_user -> create_new();
                if(!$comment_user->get('user_username'))$comment_user -> update('user_username', $account_data['username']);
                if(!empty($account_data['image'])){
                    $comment_user -> update_user_data(array(
                        'image' => $account_data['image'],
                    ));
                }
            }
            $shub_user_id = $comment_user->get('shub_user_id');
            if($shub_user_id){
                $this->update('shub_user_id',$shub_user_id);
            }
            return $shub_user_id;
        }else{
            echo "Failed to get account data. Please confirm the envato token is correct as per the help documentation. Without this you will be unable to post comment replies.";
            return false;
        }
    }

	public function load_available_items(){
		// serialise this result into envato_data.

		$api = $this->get_api();

		// get user details and confirm username works.
		$api_result = $api->api('v1/market/user:'.$this->get('account_name').'.json');
		if($api_result && isset($api_result['user']) && is_array($api_result['user']) && isset($api_result['user']['username']) && $api_result['user']['username'] == $this->get('account_name')){
			$this->save_account_data($api_result);
		}else{
			echo 'Failed to verify username '.htmlspecialchars($this->get('account_name')).'. Please ensure this is correct and try again.';
			return false;
		}
		$api_result = $api->api('v1/market/user-items-by-site:' . $this->get('account_name') . '.json');
		if($api_result && isset($api_result['user-items-by-site']) && is_array($api_result['user-items-by-site'])){
			$items = array();
			foreach($api_result['user-items-by-site'] as $items_by_site){
				$site_api_result = $api->api('v1/market/new-files-from-user:' . $this->get('account_name') . ',' . strtolower($items_by_site['site']) .  '.json');
				if($site_api_result && isset($site_api_result['new-files-from-user']) && is_array($site_api_result['new-files-from-user'])){
					foreach($site_api_result['new-files-from-user'] as $item){
						$item['site'] = $items_by_site['site'];
						$items[$item['id']] = $item;
					}
				}
			}
			// yes, this member has some items, save these items to the account ready for selection in the settings area.
			$save_data = $this->get('account_data');
			if(!is_array($save_data))$save_data=array();
			// create a product for each of these items (if a matching one doesn't already exist)
			$existing_products = SupportHub::getInstance()->get_products();
			foreach($items as $key => $item){
				// check if this item exists already
				$exists = false;
				foreach($existing_products as $existing_product){
					if(isset($existing_product['product_data']['envato_item_id']) && $existing_product['product_data']['envato_item_id'] == $item['id']){
						$exists = $existing_product['shub_product_id'];
					}
				}
				$newproduct = new SupportHubProduct();
				if(!$exists) {
					$newproduct->create_new();
				}else {
					$newproduct->load( $exists );
				}
				$newproduct->update('product_name',$item['item']);
				$newproduct->update('product_data',array(
					'envato_item_id' => $item['id'],
					'envato_item_data' => $item,
					'image' => isset($item['thumbnail']) ? $item['thumbnail'] : false,
					'url' => isset($item['url']) ? $item['url'] : false,
				));
				$items[$key]['shub_product_id'] = $newproduct->get('shub_product_id');
			}
			$save_data['items'] = $items;
			$this->update('account_data',$save_data);
		}
	}

    public function login_to_own_app(){


        $api = $this->get_api();
        // check if we have a code from a previous redirect:
        if(!empty($_SESSION['shub_oauth_doing_envato']['code'])) {
            // grab a token from the api
            $token = $api->get_authentication($_SESSION['shub_oauth_doing_envato']['code']);
            unset($_SESSION['shub_oauth_doing_envato']['code']);
            if(!empty($token) && !empty($token['access_token'])) {
                $api_result = $api->api('v1/market/private/user/username.json', array(), false);
                $api_result_email = $api->api('v1/market/private/user/email.json', array(), false);
                $api_user = new SupportHubUser_Envato();

                if($api_result && !empty($api_result['username'])){
                    if($api_result_email && !empty($api_result_email['email'])) {
                        $email = trim(strtolower($api_result_email['email']));
                        $api_user->load_by('user_email', $email);
                        if(!$api_user->get('shub_user_id')) {
                            // see if we can load by envato username instead
                            $api_user->load_by_meta('envato_username', $api_result['username']);
                            if(!$api_user->get('shub_user_id')) {
                                // no match on envato username
                                // try to find a match by plain old username instead
                                // no existing match by email, find a match by username
                                $api_user->load_by( 'user_username', $api_result['username']);
                                if(!$api_user->get('shub_user_id')) {
                                    // no existing match by email, envato_username or plain username, pump a new entry in the db
                                    $api_user->create_new();
                                    $api_user->add_meta('envato_username',$api_result['username']);
                                    $api_user->update('user_email',$email);
                                    $api_user->update('user_username',$api_result['username']);
                                }else{
                                    // we got a match by username
                                }
                            }else{
                                // yes! we got a match by envato username.
                            }
                        }
                    }else{
                        // no email from the user, strange! we should always get an email from the API.
                        // well just incase we fall back and try to load based on username.

                        // (COPIED CODE FROM ABOVE )
                        // see if we can load by envato username instead
                        $api_user->load_by_meta('envato_username', $api_result['username']);
                        if(!$api_user->get('shub_user_id')) {
                            // no match on envato username
                            // try to find a match by plain old username instead
                            // no existing match by email, find a match by username
                            $api_user->load_by( 'user_username', $api_result['username']);
                            if(!$api_user->get('shub_user_id')) {
                                // no existing match by email, envato_username or plain username, pump a new entry in the db
                                $api_user->create_new();
                                $api_user->add_meta('envato_username',$api_result['username']);
                                $api_user->update('user_username',$api_result['username']);
                            }else{
                                // we got a match by username
                            }
                        }else{
                            // yes! we got a match by envato username.
                        }
                    }
                }
                if(!$api_result || empty($api_result['username']) || !$api_user->get('shub_user_id')){
                    // we got an API error, should always have a username.
                    echo "Sorry, unable to login with Envato to own app.  <br><br> ";
                    return false;
                }

                if(!$api_user->get('user_email') && !empty($api_result_email['email'])){
                    $api_user->update('user_email',trim(strtolower($api_result_email['email'])));
                }
                $api_user->add_unique_meta('envato_username',$api_result['username']);

                $shub_envato_oauth_id = shub_update_insert('shub_envato_oauth_id',false,'shub_envato_oauth',array(
                    'expire_time' => time() + $token['expires_in'],
                    'shub_account_id' => $this->shub_account_id,
                    'shub_user_id' => $api_user->get('shub_user_id'),
                    'access_token' => $token['access_token'],
                    'refresh_token' => $token['refresh_token'],
                ));

                $this->update('account_data',array(
                    'shub_envato_oauth_id' => $shub_envato_oauth_id,
                ));
            }else{
                echo "Failed to get token during login.";
                return false;
            }
        }else{
            // set our redirect session variable and go to envato for login
            $login_url                           = $api->get_authorization_url();
            $_SESSION['shub_oauth_doing_envato'] = array(
                'url' => $_SERVER['REQUEST_URI'],
            );
            ?>
            <p>
                To continue please login using your Envato account.
            </p>
            <a href="<?php echo esc_attr( $login_url );?>" class="button">Login with Envato</a>
            <?php
            return false;
        }
        return true;
    }
	private $api = false;
	public function get_api(){
		if(!$this->api){

			require_once trailingslashit(dirname(_DTBAKER_SUPPORT_HUB_CORE_FILE_)) . 'extensions/envato/class.envato-api.php';

            $this->api = envato_api_basic::getInstance();
            $this->api->set_personal_token($this->get( 'envato_token' ));
            $this->api->set_client_id($this->get( 'envato_app_id' ));
            $this->api->set_client_secret($this->get( 'envato_app_secret' ));
            $this->api->set_redirect_url($this->generate_oauth_redirect_url());
            $this->api->set_cookie($this->get( 'envato_cookie' ));

		}
		return $this->api;
	}
	public function generate_oauth_redirect_url(){
		return add_query_arg(_SHUB_ENVATO_OAUTH_DOING_FLAG,'ok',home_url());
	}

	public function get_picture(){
		$data = $this->get('account_data');
		return $data && isset($data['pictureUrl']) && !empty($data['pictureUrl']) ? $data['pictureUrl'] : false;
	}

    public function get_item($shub_item_id){
        return new shub_envato_item($this, $shub_item_id);
    }


    public function run_cron( $debug = false ){

        // we pull in the buyer/sales API results so we can link these up with item comments to determine if a buyer has purchase the item or not
        // this is the only reliable way to do this because no purchase information is available via the comments API search
        // hopefully they include some "has purchased" or "is supported" information in the comments search so we can speed things up a little bit.

        $api = $this->get_api();

        $account_data = $this->get('account_data');
        if(empty($account_data['shub_envato_oauth_id'])){
            echo 'Please reconnect this account from the Support Hub settings page';
            return false;
        }
        $account_token = shub_get_single('shub_envato_oauth','shub_envato_oauth_id',$account_data['shub_envato_oauth_id']);
        if(!$account_token['shub_envato_oauth_id']){
            echo 'Failed to get self account token';
            return false;
        }

        $api->set_manual_token($account_token);
        if($account_token['expire_time'] <= time()){
            // renew this token!
            $new_access_token = $api->refresh_token();
            if($new_access_token){
                shub_update_insert('shub_envato_oauth_id',$account_token['shub_envato_oauth_id'],'shub_envato_oauth',array(
                    'access_token' => $new_access_token,
                    'expire_time' => time() + 3600,
                ));
            }else{
                echo 'Token refresh failed';
                return false;
            }
        }

        // how many days do we want to go back? maybe 60 days to start with?
        $last_sale = get_option('supporthub_envato_author_sales_last',false);
        if(!$last_sale)$last_sale = strtotime('-60 days');
        $last_sale_in_this_batch = 0;
        $page = 1;
        while(true){
            $recent_sales = $api->api('v2/market/author/sales?page='.$page,array(),false);
//            echo "Recent sales are: ";print_r($recent_sales);exit;
            $page++;
            if(!$recent_sales || !is_array($recent_sales)){
                break;
            }
            while($recent_sales){
                $recent_sale = array_pop($recent_sales);
                if($recent_sale && !empty($recent_sale['sold_at']) && !empty($recent_sale['code']) && !empty($recent_sale['item']['id'])) {
//                    echo $recent_sale['sold_at']."<br>";
                    // add this to the database, or break if we already have this one in the db.
                    $sale_time = strtotime($recent_sale['sold_at']);
                    if ($sale_time < $last_sale) break;
                    $last_sale_in_this_batch = max($last_sale_in_this_batch,$sale_time);

                    // todo: check if they add username to the system, for now we use a 0 shub_user_id because we're unsure which user this purchase is related to (without doing another separate purchase call)
                    // for now we do all processing based on this purchase code. SLOW. but until we get usernames in the buyer result there is no other way.
//                    echo "Query this code: ".$recent_sale['code'];exit;
                    SupportHub::getInstance()->message_managers['envato']->pull_purchase_code($api, $recent_sale['code'], $recent_sale);
                    update_option('supporthub_envato_author_sales_last',$last_sale_in_this_batch);
                    continue;

                    // save this purchase code into the db

                    // find the product this purchase is related to
                    $existing_products = SupportHub::getInstance()->get_products();
                    // check if this item exists already
                    $exists = false;
                    foreach ($existing_products as $existing_product) {
                        if (isset($existing_product['product_data']['envato_item_id']) && $existing_product['product_data']['envato_item_id'] == $recent_sale['item']['id']) {
                            $exists = $existing_product['shub_product_id'];
                        }
                    }
                    $newproduct = new SupportHubProduct();
                    if (!$exists) {
                        $newproduct->create_new();
                        if (!$newproduct->get('product_name')) {
                            $newproduct->update('product_name', $recent_sale['item']['name']);
                        }
                        $existing_product_data = $newproduct->get('product_data');
                        if (!is_array($existing_product_data)) $existing_product_data = array();
                        if (empty($existing_product_data['envato_item_id'])) {
                            $existing_product_data['envato_item_id'] = $recent_sale['item']['id'];
                        }
                        if (empty($existing_product_data['envato_item_data'])) {
                            $existing_product_data['envato_item_data'] = $recent_sale['item'];
                        }
                        if (empty($existing_product_data['image'])) {
                            $existing_product_data['image'] = $recent_sale['item']['thumbnail_url'];
                        }
                        if (empty($existing_product_data['url'])) {
                            $existing_product_data['url'] = $recent_sale['item']['url'];
                        }
                        $newproduct->update('product_data', $existing_product_data);
                    } else {
                        $newproduct->load($exists);
                    }
                    if ($newproduct->get('shub_product_id')) {
                        // product has been added
                        // time to add it to the purchase db
                        // check if this already exists in the db
                        $existing_purchase = shub_get_single('shub_envato_purchase', array(
//                                    'shub_user_id',
//                                    'shub_product_id',
//                                    'purchase_time',
                            'purchase_code',
                        ), array(
//                                    $this->get('shub_user_id'),
//                                    $newproduct->get('shub_product_id'),
//                                    strtotime($purchase['sold_at']),
                            $recent_sale['code'],
                        ));
                        if (!$existing_purchase) {
                            $shub_envato_purchase_id = shub_update_insert('shub_envato_purchase_id', false, 'shub_envato_purchase', array(
                                'shub_user_id' => 0,
                                'shub_product_id' => $newproduct->get('shub_product_id'),
                                'purchase_time' => $sale_time,
                                'envato_user_id' => 0,
                                'purchase_code' => $recent_sale['code'],
                                'api_type' => 'author/sales',
                                'purchase_data' => json_encode($recent_sale),
                            ));
                        } else {
                            $shub_envato_purchase_id = $existing_purchase['shub_envato_purchase_id'];
                        }
                        if ($shub_envato_purchase_id) {
                            // we have a purchase in the db
                            // add or update the support expiry based on this purchase history.
                            // work out when this purchase support expires
                            // this is the expiry date returned in the api or just 6 months from the original purchase date.
                            $support_expiry_time = strtotime("+6 months", $sale_time);
                            // todo - check for this expiry time in the new api results.

                            $existing_support = shub_get_single('shub_envato_support', array(
                                'shub_envato_purchase_id'
                            ), array(
                                $shub_envato_purchase_id,
                            ));
                            if ($existing_support && $existing_support['shub_envato_support_id'] && $existing_support['start_time'] == $sale_time) {
                                // check the existing support expiry matches the one we have in the database.
                                if ($existing_support['end_time'] < $support_expiry_time) {
                                    // we have a support extension!
                                    $shub_envato_support_id = shub_update_insert('shub_envato_support_id', $existing_support['shub_envato_support_id'], 'shub_envato_support', array(
                                        'end_time' => $support_expiry_time,
                                        'api_type' => 'buyer/purchases',
                                        'support_data' => json_encode($recent_sale),
                                    ));
                                }
                            } else {
                                // we are adding a new support entry
                                $shub_envato_support_id = shub_update_insert('shub_envato_support_id', false, 'shub_envato_support', array(
                                    'shub_user_id' => 0,
                                    'shub_product_id' => $newproduct->get('shub_product_id'),
                                    'shub_envato_purchase_id' => $shub_envato_purchase_id,
                                    'start_time' => $sale_time,
                                    'end_time' => $support_expiry_time,
                                    'api_type' => 'author/sales',
                                    'support_data' => json_encode($recent_sale),
                                ));
                            }
                        }
                    }
                }

            }
        }

    }
}
