<?php

class shub_ucm_account extends SupportHub_account{

    public function __construct($shub_account_id){
        parent::__construct($shub_account_id);
        $this->shub_extension = 'ucm';
    }

    public function confirm_api(){
        // confirm API and do a call to get the ucm user id and save it in the account shub_user_id field so we can display when composing a message.

        $api = $this->get_api();

        $api_result = $api->api('user','get');
        if($api_result && !empty($api_result['email'])){
            $shub_user_id = $this->get_api_user_to_id($api_result);
            if($shub_user_id){
                $this->update('shub_user_id',$shub_user_id);
                return true;
            }
        }
        echo 'Failed to get User ID from api. Please confirm API details.';
        exit;
    }

	public function load_available_items(){
		// serialise this result into ucm_data.

		$api = $this->get_api();

		$api_result = $api->api('faq','list_products');
        /*Array
(
    [version] => 1
    [user_id] => 1
    [faq] => 1
    [faq_products] => Array
        (
            [4] => Array
                (
                    [faq_product_id] => 4
                    [item_ids] => 48670|144064
                    [default_type_id] => 3
                    [name] => asdfasdf333
                    [date_created] => 2012-12-11 14:22:12
                    [date_updated] => 2015-03-14 20:19:19
                    [id] => 4
                    [default_type] => Array
                        (
                            [ticket_type_id] => 3
                            [name] => CodeCanyon PHP Support
                            [public] => 1
                            [create_user_id] => 1
                            [update_user_id] => 1
                            [date_updated] => 2012-04-20
                            [date_created] => 2012
                            [default_user_id] => 0
                        )

                    [items] => Array
                        (
                            [0] => Array
                                (
                                    [item_id] => 230
                                    [envato_account_id] => 1
                                    [item_id] => 48670
                                    [marketplace] => themeforest
                                    [name] => Blue Business - 3 Pages - HTML & PSD
                                    [url] => http://themeforest.net/item/blue-business-3-pages-html-psd/48670
                                    [launch_date] => 2009-07-07
                                    [cost] => 12.00
                                    [cache] => a:16:{s:2:"id";s:5:"48670";s:4:"item";s:36:"Blue Business - 3 Pages - HTML & PSD";s:3:"url";s:64:"http://themeforest.net/item/blue-business-3-pages-html-psd/48670";s:4:"user";s:7:"dtbaker";s:9:"thumbnail";s:39:"http://3.s3.envato.com/files/140746.jpg";s:5:"sales";s:3:"246";s:6:"rating";s:1:"4";s:4:"cost";s:5:"12.00";s:11:"uploaded_on";s:30:"Tue Jul 07 04:26:11 +1000 2009";s:11:"last_update";s:30:"Tue Jul 07 04:26:11 +1000 2009";s:4:"tags";s:129:"blue, business, clean, clean, clear, corporate, crisp, education, google maps, html, icons, medical, php contact form, psd, white";s:8:"category";s:24:"site-templates/corporate";s:16:"live_preview_url";s:57:"http://3.s3.envato.com/files/142413/1.__large_preview.jpg";s:11:"marketplace";s:11:"themeforest";s:4:"name";s:36:"Blue Business - 3 Pages - HTML & PSD";s:4:"data";a:0:{}}
                                    [date_created] => 2012-10-20 12:53:46
                                    [date_updated] => 2012-11-29 20:06:00
                                    [id] => 230
                                )*/

        if(is_array($api_result) && isset($api_result['faq_products']) && count($api_result['faq_products'])){
            $this->save_account_data(array(
                'items' => $api_result['faq_products'],
            ));
        }else{
            echo 'Failed to find any FAQ products, please create some in UCM first. Please check logs for any errors.';
        }

	}

	public function run_cron( $debug = false ){


	}

	private $api = false;
	public function get_api($use_db_code = true){
		if(!$this->api){

			require_once trailingslashit(dirname(_DTBAKER_SUPPORT_HUB_CORE_FILE_)) . 'extensions/ucm/class.ucm-api.php';

            $this->api = ucm_api_basic::getInstance();
            $this->api->set_api_url($this->get( 'ucm_api_url' ));
            $this->api->set_api_key($this->get( 'ucm_api_key' ));

		}
		return $this->api;
	}
	public function get_api_user_to_id($ucm_user_data){
		//print_r($ucm_user_data);exit;
        $comment_user = new SupportHubUser_ucm();
        if(!empty($ucm_user_data['email'])){
            $comment_user->load_by( 'user_email', trim(strtolower($ucm_user_data['email'])));
        }
        if(!$comment_user->get('shub_user_id')){
            // didn't find one yet.
            // find by envato username?
            if(isset($ucm_user_data['envato']['user'])){
                $first = current($ucm_user_data['envato']['user']);
                if($first && !empty($first['envato_username'])){
                    if ($comment_user->load_by_meta('envato_username', strtolower($first['envato_username']))) {
                        // found! yay!
                        SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_INFO,'ucm','Found a user based on envato username.',array(
                            'username' => $first['envato_username'],
                            'found_user_id' => $comment_user->get('shub_user_id'),
                        ));
                    }
                }
            }
        }


        if(isset($ucm_user_data['envato']['purchases']) && is_array($ucm_user_data['envato']['purchases'])){
            // find a matching user account with these purchases.
            foreach($ucm_user_data['envato']['purchases'] as $purchase){
                if(!empty($purchase['license_code'])) {
                    // pull in the license code using the envato module if it's enabled.
                    if(isset(SupportHub::getInstance()->message_managers['envato'])) {
                        $result = SupportHub::getInstance()->message_managers['envato']->pull_purchase_code(false, $purchase['license_code'], array(), $comment_user->get('shub_user_id'));
                        if ($result && !empty($result['shub_user_id'])) {
                            $comment_user->load($result['shub_user_id']);
                            SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_INFO,'ucm','Found a user based on license code.',array(
                                'license_code' => $purchase['license_code'],
                                'found_user_id' => $comment_user->get('shub_user_id'),
                            ));
                            break;
                        }
                    }
                }
            }
        }

        if(!$comment_user->get('shub_user_id')){
            // find a match based on email.
            if(!empty($ucm_user_data['email'])){
                $comment_user->load_by( 'user_email', trim(strtolower($ucm_user_data['email'])));
            }
        }
        if(!$comment_user->get('shub_user_id')){
            // no existing matches yet, create a new user with the above meta values so that we can find them again in the future.
            $comment_user->create_new();
        }
        // now we add/update various meta/values of the user if anything is missing.
        if(!empty($ucm_user_data['email']) && !$comment_user->get('user_email')) {
            $comment_user->update('user_email', trim(strtolower($ucm_user_data['email'])));
        }
        if(isset($ucm_user_data['envato']['user'])){
            $first = current($ucm_user_data['envato']['user']);
            if($first && !empty($first['envato_username']) && !$comment_user->get_meta('envato_username',strtolower($first['envato_username']))){
                $comment_user->add_meta('envato_username', strtolower($first['envato_username']));
                if(!$comment_user->get('user_username')){
                    $comment_user->update('user_username',strtolower($first['envato_username']));
                }
            }
        }
        if(isset($ucm_user_data['envato']['purchases'])) {
            foreach ($ucm_user_data['envato']['purchases'] as $purchase) {
                if (!empty($purchase['license_code']) && !$comment_user->get_meta('envato_license_code', strtolower($purchase['license_code']))) {
                    $comment_user->add_meta('envato_license_code', strtolower($purchase['license_code']));
                }
            }
        }
        if(!empty($ucm_user_data['name'])){
            if(empty($ucm_user_data['last_name'])){
                $bits = explode(" ",$ucm_user_data['name']);
                $ucm_user_data['name'] = array_shift($bits);
                $ucm_user_data['last_name'] = implode(" ",$bits);
            }
        }
        if(!$comment_user->get('user_fname') && !empty($ucm_user_data['name'])){
            $comment_user->update('user_fname',$ucm_user_data['name']);
        }
        if(!$comment_user->get('user_lname') && !empty($ucm_user_data['last_name'])){
            $comment_user->update('user_lname',$ucm_user_data['last_name']);
        }
        $comment_user->update_user_data($ucm_user_data);
        return $comment_user->get('shub_user_id');
	}

	public function get_picture(){
		$data = $this->get('ucm_data');
		return $data && isset($data['pictureUrl']) && !empty($data['pictureUrl']) ? $data['pictureUrl'] : false;
	}


    public function get_item($shub_item_id){
        return new shub_ucm_item($this, $shub_item_id);
    }

}
