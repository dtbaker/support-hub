<?php

class shub_message extends SupportHub_message{

    protected $network = 'envato';


	public function load_by_network_key($network_key, $message_data, $type, $debug = false){

		switch($type){
			case 'item_comment':
				$existing = shub_get_single('shub_message', 'network_key', $network_key);
				if($existing){
					// load it up.
					$this->load($existing['shub_message_id']);
				}
				if($message_data && isset($message_data['id']) && $message_data['id'] == $network_key){
					if(!$existing){
						$this->create_new();
					}
					$this->update('shub_account_id',$this->account->get('shub_account_id'));
					$this->update('shub_item_id',$this->item->get('shub_item_id'));
					$comments = $message_data['conversation'];
					$this->update('title',$comments[0]['content']);
					$this->update('summary',$comments[count($comments)-1]['content']);
					$this->update('last_active',strtotime($message_data['last_comment_at']));
					$this->update('shub_type',$type);
					$this->update('shub_data',$message_data);
					$this->update('shub_link',$message_data['url'] . '/' . $message_data['id']);
					$this->update('network_key', $network_key);
                    SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_INFO,'envato','Updating message '.$network_key.' from API.',array(
                        'Current Comment Count: ' => count($this->get_comments()),
                        'New Comment Count: ' => count($comments),
                        'Current Status: ' => $this->get('shub_status'),
                    ));
                    if($this->get('shub_status')!=_shub_MESSAGE_STATUS_HIDDEN){
                        // we have to decide if we're updating the message status from answered to unanswered.
                        // if this message status is already answered and the existing comment count matches the new comment count then we don't update the status
                        // this is because we insert a placeholder "comment" into the db while the API does the push, and when we read again a few minutes later it overwrites this placeholder comment, so really it's not a new comment coming in just the one we posted through the API that takes a while to come back through.
                        if($this->get('shub_status') == _shub_MESSAGE_STATUS_ANSWERED && count($comments) == count($this->get_comments())){
                            // don't do anything
                        }else{
                            // comment count is different
                            $this->update('shub_status', _shub_MESSAGE_STATUS_UNANSWERED);
                        }
                    }
					$this->update('comments',$comments);

					// create/update a user entry for this comments.
				    $shub_user_id = 0;
					$first_comment = current($comments);
				    if(!empty($first_comment['username'])) {
					    $comment_user = new SupportHubUser_Envato();
					    $res = $comment_user->load_by( 'envato_username', $first_comment['username']);
					    if(!$res){
                            $res = $comment_user->load_by( 'user_username', $first_comment['username']);
                            if(!$res) {
                                $comment_user->create_new();
                            }
					    }
                        if (!$comment_user->get('user_username')) $comment_user->update('user_username', $first_comment['username']);
                        if (!$comment_user->get('envato_username')) $comment_user->update('envato_username', $first_comment['username']);
                        $comment_user->update_user_data(array(
                            'image' => $first_comment['profile_image_url'],
                            'envato' => $first_comment,
                        ));
					    $shub_user_id = $comment_user->get('shub_user_id');
				    }
					$this->update('shub_user_id', $shub_user_id);


					return $existing;
				}
				break;

		}

	}


	public function get_link() {
        $item = $this->get('item');
        if($item){
            $shub_product_id = $item->get('shub_product_id');
            $item_data = $item->get('item_data');
            if(!is_array($item_data))$item_data = array();
            if(!empty($item_data['url'])) {
                return $item_data['url'] .'/comments/'. $this->get('network_key');
            }
        }
        return '#';
	}


	public function send_queued($debug = false){
		if($this->account && $this->shub_message_id) {
			// send this message out to envato.
			// this is run when user is composing a new message from the UI,
			if ( $this->get( 'shub_status' ) == _shub_MESSAGE_STATUS_SENDING )
				return; // dont double up on cron.


			switch($this->get('shub_type')){
				case 'item_post':


					if(!$this->item) {
						echo 'No envato item defined';
						return false;
					}

					$this->update( 'shub_status', _shub_MESSAGE_STATUS_SENDING );
					$api = $this->account->get_api();
					$item_id = $this->item->get('item_id');
					if($debug)echo "Sending a new message to envato item ID: $item_id <br>\n";
					$result = false;
					$post_data = array();
					$post_data['summary'] = $this->get('summary');
					$post_data['title'] = $this->get('title');
					$now = time();
					$send_time = $this->get('last_active');
					$result = $api->api('v1/items/'.$item_id.'/posts',array(),'POST',$post_data,'location');
					if($debug)echo "API Post Result: <br>\n".var_export($result,true)." <br>\n";
					if($result && preg_match('#https://api.envato.com/v1/posts/(.*)$#',$result,$matches)){
						// we have a result from the API! this should be an API call in itself:
						$new_post_id = $matches[1];
						$this->update('network_key',$new_post_id);
						// reload this message and messages from the graph api.
						$this->load_by_network_key($this->get('network_key'),false,$this->get('shub_type'),$debug);
					}else{
						echo 'Failed to send message. Error was: '.var_export($result,true);
						// remove from database.
						$this->delete();
						return false;
					}

					// successfully sent, mark is as answered.
					$this->update( 'shub_status', _shub_MESSAGE_STATUS_ANSWERED );
					return true;

				default:
					if($debug)echo "Unknown post type: ".$this->get('shub_type');
			}

		}
		return false;
	}
	public function send_queued_comment_reply($envato_message_comment_id, $shub_outbox, $debug = false){
        $comments = $this->get_comments();
        if(isset($comments[$envato_message_comment_id]) && !empty($comments[$envato_message_comment_id]['message_text'])){
            $api = $this->account->get_api();
            $item_data = $this->get('item')->get('item_data');
            if($item_data && $item_data['url']) {
                $api_result = $api->post_comment($item_data['url'] . '/comments', $this->get('network_key'), $comments[$envato_message_comment_id]['message_text']);
                if ($api_result) {
                    // add a placeholder in the comments table, next time the cron runs it should pick this up and fill in all the details correctly from the API
                    shub_update_insert('shub_message_comment_id', $envato_message_comment_id, 'shub_message_comment', array(
                        'network_key' => $api_result,
                        'time' => time(),
                    ));
                    return true;
                } else {
                    echo "Failed to send comment, check debug log.";
                    return false;
                }
            }
        }
        return false;
    }


	public function get_type_pretty() {
		$type = $this->get('shub_type');
		switch($type){
			case 'item_comment':
				return 'Item Comment';
				break;
			default:
				return ucwords($type);
		}
	}

    public function get_from() {
        if($this->shub_message_id){
            $from = array();
            if($this->get('shub_user_id')){
                $from[$this->get('shub_user_id')] = new SupportHubUser_Envato($this->get('shub_user_id'));
            }
            $messages = $this->get_comments();
            foreach($messages as $message){
                if($message['shub_user_id'] && !isset($from[$message['shub_user_id']])){
                    $from[$message['shub_user_id']] = new SupportHubUser_Envato($message['shub_user_id']);
                }
            }
            return $from;
        }
        return array();
    }


    public function get_from2() {
		if($this->shub_message_id){
			$from = array();
			$messages = $this->get_comments(); //shub_get_multiple('shub_message_comment',array('shub_message_id'=>$this->shub_message_id),'shub_message_comment_id');
			foreach($messages as $message){
				if($message['message_from']){
					$data = @json_decode($message['message_from'],true);
					if(isset($data['username'])){
						$from[$data['username']] = array(
							'name' => $data['username'],
							'image' => isset($data['profile_image_url']) ? $data['profile_image_url'] : plugins_url('extensions/envato/default-user.jpg',_DTBAKER_SUPPORT_HUB_CORE_FILE_),
							'link' => 'http://themeforest.net/user/' . $data['username'],
						);
					}
				}
			}
			return $from;
		}
		return array();
	}

    public function message_sidebar_data(){

        // find if there is a product here
        $shub_product_id = $this->get_product_id();
        $product_data = array();
        $item_data = array();
        $item = $this->get('item');
        if(!$shub_product_id && $item){
            $shub_product_id = $item->get('shub_product_id');
            $item_data = $item->get('item_data');
            if(!is_array($item_data))$item_data = array();
        }
        if($shub_product_id) {
            $shub_product = new SupportHubProduct();
            $shub_product->load( $shub_product_id );
            $product_data = $shub_product->get( 'product_data' );
        }
        ?>
        <img src="<?php echo plugins_url('extensions/envato/logo.png', _DTBAKER_SUPPORT_HUB_CORE_FILE_);?>" class="shub_message_account_icon">
        <?php
        if($shub_product_id && !empty($product_data['image'])) {
            ?>
            <img src="<?php echo $product_data['image'];?>" class="shub_message_account_icon">
            <?php
        }
        ?>
        <br/>
        <strong><?php _e('Account:');?></strong> <a href="<?php echo $this->get_link(); ?>" target="_blank"><?php echo htmlspecialchars( $this->get('account') ? $this->get('account')->get( 'account_name' ) : 'N/A' ); ?></a> <br/>
        <strong><?php _e('Time:');?></strong> <?php echo shub_print_date( $this->get('last_active'), true ); ?>  <br/>

        <?php
        if($item_data){
            ?>
            <strong><?php _e('Item:');?></strong>
            <a href="<?php echo isset( $item_data['url'] ) ? $item_data['url'] : $this->get_link(); ?>"
               target="_blank"><?php
                echo htmlspecialchars( $item_data['item'] ); ?></a>
            <br/>
            <?php
        }

        /*$data = $this->get('shub_data');
        if(!empty($data['buyer_and_author']) && $data['buyer_and_author'] && $data['buyer_and_author'] !== 'false'){
            // hmm - this doesn't seem to be a "purchased" flag.
            ?>
            <strong>PURCHASED</strong><br/>
            <?php
        }*/
    }

    public function get_user_hints($user_hints = array()){
        $user_hints['shub_user_id'][] = $this->get('shub_user_id');
        $comments         = $this->get_comments();
        $first_comment = current($comments);
        if(isset($first_comment['shub_user_id']) && $first_comment['shub_user_id']){
            $user_hints['shub_user_id'][] = $first_comment['shub_user_id'];
        }
        $message_from = @json_decode($first_comment['message_from'],true);
        if($message_from && isset($message_from['username'])){ //} && $message_from['username'] != $envato_message->get('account')->get( 'account_name' )){
            // this wont work if user changes their username, oh well.
            $other_users = new SupportHubUser_Envato();
            $other_users->load_by_meta('envato_username',$message_from['username']);
            if($other_users->get('shub_user_id') && !in_array($other_users->get('shub_user_id'),$user_hints['shub_user_id'])){
                // pass these back to the calling method so we can get the correct values.
                $user_hints['shub_user_id'][] = $other_users->get('shub_user_id');
            }
        }
        return $user_hints;
    }
    public function get_user($shub_user_id){
        return new SupportHubUser_Envato($shub_user_id);
    }
    public function get_reply_user(){
        return new SupportHubUser_Envato($this->account->get('shub_user_id'));
    }


}