<?php

class shub_bbpress_message extends SupportHub_message{

    protected $network = 'bbpress';


	public function load_by_bbpress_id($bbpress_id, $topic_data, $type, $debug = false){

		switch($type){
			case 'forum_topic':
				/*{"post_id":"3381","post_title":"Child THEME IMPOSSIBLE","post_date":{"scalar":"20150304T00:19:51","xmlrpc_type":"datetime","timestamp":1425428391},"post_date_gmt":{"scalar":"20150304T00:19:51","xmlrpc_type":"datetime","timestamp":1425428391},"post_modified":{"scalar":"20150304T00:19:51","xmlrpc_type":"datetime","timestamp":1425428391},"post_modified_gmt":{"scalar":"20150304T00:19:51","xmlrpc_type":"datetime","timestamp":1425428391},"post_status":"publish","post_type":"topic","post_name":"child-theme-impossible","post_author":"1442","post_password":"","post_excerpt":"","post_content":"Has anyone been able to create a child theme that keeps the animations please help","post_parent":"2613","post_mime_type":"","link":"http:\/\/dtbaker.net\/forums\/topic\/child-theme-impossible\/","guid":"http:\/\/dtbaker.net\/forums\/topic\/child-theme-impossible\/","menu_order":0,"comment_status":"closed","ping_status":"closed","sticky":false,"post_thumbnail":[],"post_format":"standard","terms":[],"custom_fields":[{"id":"9897","key":"author","value":""},{"id":"9898","key":"stars","value":""}],"replies":[{"post_id":"3409","post_title":"","post_date":{"scalar":"20150413T18:16:41","xmlrpc_type":"datetime","timestamp":1428949001},"post_date_gmt":{"scalar":"20150413T18:16:41","xmlrpc_type":"datetime","timestamp":1428949001},"post_modified":{"scalar":"20150413T18:16:41","xmlrpc_type":"datetime","timestamp":1428949001},"post_modified_gmt":{"scalar":"20150413T18:16:41","xmlrpc_type":"datetime","timestamp":1428949001},"post_status":"publish","post_type":"reply","post_name":"3409","post_author":"1692","post_password":"","post_excerpt":"","post_content":"I just purchased this last week and am having problems too. Support for this template seems to be nonexistent. This design has been approved by my client but I'm unable to get the theme to function correctly and I can't get any support from the developer. The deadline for this site is approaching and I'm dead in the water. Hopefully I can get a refund on this one.","post_parent":"3381","post_mime_type":"","link":"http:\/\/dtbaker.net\/forums\/reply\/3409\/","guid":"http:\/\/dtbaker.net\/forums\/reply\/3409\/","menu_order":2,"comment_status":"closed","ping_status":"closed","sticky":false,"post_thumbnail":[],"post_format":"standard","terms":[],"custom_fields":[]},{"post_id":"3394","post_title":"","post_date":{"scalar":"20150318T22:42:54","xmlrpc_type":"datetime","timestamp":1426718574},"post_date_gmt":{"scalar":"20150318T22:42:54","xmlrpc_type":"datetime","timestamp":1426718574},"post_modified":{"scalar":"20150318T22:42:54","xmlrpc_type":"datetime","timestamp":1426718574},"post_modified_gmt":{"scalar":"20150318T22:42:54","xmlrpc_type":"datetime","timestamp":1426718574},"post_status":"publish","post_type":"reply","post_name":"3394","post_author":"1458","post_password":"","post_excerpt":"","post_content":"I have not been able to and just posted that same question before I found your question..","post_parent":"3381","post_mime_type":"","link":"http:\/\/dtbaker.net\/forums\/reply\/3394\/","guid":"http:\/\/dtbaker.net\/forums\/reply\/3394\/","menu_order":1,"comment_status":"closed","ping_status":"closed","sticky":false,"post_thumbnail":[],"post_format":"standard","terms":[],"custom_fields":[]}],"timestamp":1428949001}*/
				$existing = shub_get_single('shub_message', 'network_key', $bbpress_id);
				if($existing){
					// load it up.
					$this->load($existing['shub_message_id']);
				}
				if($topic_data && isset($topic_data['post_id']) && $topic_data['post_id'] == $bbpress_id){
					if(!$existing){
						$this->create_new();
					}
					$this->update('shub_account_id',$this->account->get('shub_account_id'));
					$this->update('shub_item_id',$this->item->get('shub_item_id'));
                    /* Array
                    (
                        [post_id] => 3549
                        [post_title] => slider
                        [post_date] => stdClass Object
                            (
                                [scalar] => 20150716T12:51:14
                                [xmlrpc_type] => datetime
                                [timestamp] => 1437051074
                            )

                        [post_date_gmt] => stdClass Object
                            (
                                [scalar] => 20150716T12:51:14
                                [xmlrpc_type] => datetime
                                [timestamp] => 1437051074
                            )

                        [post_modified] => stdClass Object
                            (
                                [scalar] => 20150716T12:51:14
                                [xmlrpc_type] => datetime
                                [timestamp] => 1437051074
                            )

                        [post_modified_gmt] => stdClass Object
                            (
                                [scalar] => 20150716T12:51:14
                                [xmlrpc_type] => datetime
                                [timestamp] => 1437051074
                            )

                        [post_status] => publish
                        [post_type] => topic
                        [post_name] => slider
                        [post_author] => 1466
                        [post_password] =>
                        [post_excerpt] =>
                        [post_content] => i would like to add the revolution slider to the top of the content on the home page but i am unsure where to paste the slider shortcode in the main theme php file
                        [post_parent] => 2398
                        [post_mime_type] =>
                        [link] => http://dtbaker.net/forums/topic/slider/
                        [guid] => http://dtbaker.net/forums/topic/slider/
                        [menu_order] => 0
                        [comment_status] => closed
                        [ping_status] => closed
                        [sticky] =>
                        [post_thumbnail] => Array
                            (
                            )

                        [post_format] => standard
                        [terms] => Array
                            (
                         $comments   )

                        [custom_fields] => Array */
					$comments = $topic_data['replies'];
                    foreach($comments as $id => $comment){
                        if(empty($comment['post_id']))continue;
                        $comments[$id]['id'] = $comment['post_id'];
                        if(!empty($comment['post_author'])){
                            $comments[$id]['shub_user_id'] = $this->account->get_api_user_to_id($comment['post_author']);
                        }
                        // timestamp is handled in forum class
                        $comments[$id]['content'] = $comment['post_content'];
                    }
					$this->update('title',$topic_data['post_content']);
					// latest comment goes in summary
					$this->update('summary',isset($comments[0]) ? $comments[0]['post_content'] : $topic_data['post_content']);
					$this->update('last_active',!empty($topic_data['timestamp']) ? $topic_data['timestamp'] : (is_array($topic_data['post_date']) ? $topic_data['post_date']['timestamp'] : (isset($topic_data['post_date']->timestamp) ? $topic_data['post_date']->timestamp : 0)));
					$this->update('shub_type',$type);
					$this->update('shub_data',$topic_data);
					$this->update('shub_link',$topic_data['link'].'#post-'.(isset($comments[0]) ? $comments[0]['post_id'] : $topic_data['post_id']));
					$this->update('network_key', $bbpress_id);
                    if($this->get('shub_status')!=_shub_MESSAGE_STATUS_HIDDEN) $this->update('shub_status', _shub_MESSAGE_STATUS_UNANSWERED);
					$this->update('comments',$comments);
					// create/update a user entry for this comments.
				    $shub_user_id = $this->account->get_api_user_to_id($topic_data['post_author']);
					$this->update('shub_user_id',$shub_user_id);

					return $this->get('shub_message_id');
				}
				break;

		}
		return false;

	}

    
    public function reply_actions(){
        $user_data = $this->account->get('account_data');
        if(isset($user_data['reply_options']) && is_array($user_data['reply_options'])){
            foreach($user_data['reply_options'] as $reply_option){
                if(isset($reply_option['title'])){
                    echo '<div>';
                    echo '<label for="">'.htmlspecialchars($reply_option['title']).'</label>';
                    if(isset($reply_option['field']) && is_array($reply_option['field'])){
                        $reply_option['field']['name'] = 'extra-'.$reply_option['field']['name'];
                        $reply_option['field']['data'] = array(
                            'reply' => 'yes'
                        );
                        shub_module_form::generate_form_element($reply_option['field']);
                    }
                    echo '</div>';
                }
            }
        }
    }

	public function get_link() {
		return $this->get('link');
	}

	private $attachment_name = '';
	public function add_attachment($local_filename){
		if(is_file($local_filename)){
			$this->attachment_name = $local_filename;
		}
	}
	public function send_queued($debug = false){
		if($this->account && $this->shub_message_id) {
			// send this message out to bbpress.
			// this is run when user is composing a new message from the UI,
			if ( $this->get( 'shub_status' ) == _shub_MESSAGE_STATUS_SENDING )
				return; // dont double up on cron.


			switch($this->get('shub_type')){
				case 'forum_post':


					if(!$this->item) {
						echo 'No bbpress forum defined';
						return false;
					}

					$this->update( 'shub_status', _shub_MESSAGE_STATUS_SENDING );
					$api = $this->account->get_api();
					$item_id = $this->item->get('forum_id');
					if($debug)echo "Sending a new message to bbpress forum ID: $item_id <br>\n";
					$result = false;
					$post_data = array();
					$post_data['summary'] = $this->get('summary');
					$post_data['title'] = $this->get('title');
					$now = time();
					$send_time = $this->get('last_active');
					$result = $api->api('v1/forums/'.$item_id.'/posts',array(),'POST',$post_data,'location');
					if($debug)echo "API Post Result: <br>\n".var_export($result,true)." <br>\n";
					if($result && preg_match('#https://api.bbpress.com/v1/posts/(.*)$#',$result,$matches)){
						// we have a result from the API! this should be an API call in itself:
						$new_post_id = $matches[1];
						$this->update('network_key',$new_post_id);
						// reload this message and messages from the graph api.
						$this->load_by_bbpress_id($this->get('network_key'),false,$this->get('shub_type'),$debug);
					}else{
						echo 'Failed to send message. Error was: '.var_export($result,true);
						// remove from database.
						$this->delete();
						return false;
					}

					// successfully sent, mark is as answered.
					$this->update( 'shub_status', _shub_MESSAGE_STATUS_ANSWERED );
					return true;

					break;
				default:
					if($debug)echo "Unknown post type: ".$this->get('shub_type');
			}

		}
		return false;
	}


    public function send_queued_comment_reply($bbpress_message_comment_id, $shub_outbox, $debug = false){
        $comments = $this->get_comments();
        if(isset($comments[$bbpress_message_comment_id]) && !empty($comments[$bbpress_message_comment_id]['message_text'])){
            $api = $this->account->get_api();
            //$item_data = $this->get('item')->get('item_data');

            $bbpress_post_data = $this->get('shub_data');
            $bbpress_id = $this->get('network_key');

            if($debug)echo "Sending a reply to bbPress Topic ID: $bbpress_id <br>\n";

            $outbox_data = $shub_outbox->get('message_data');
            if(isset($outbox_data['extra']) && is_array($outbox_data['extra'])){
                $extra_data = $outbox_data['extra'];
            }else{
                $extra_data = array();
            }
            $api_result = false;
            try{
                $extra_data['api'] = 1;
                $api_result = $api->newPost('Reply to: '.((isset($bbpress_post_data['post_title'])) ? $bbpress_post_data['post_title'] : 'Post'),$comments[$bbpress_message_comment_id]['message_text'],array(
                    'post_type' => 'reply',
                    'post_parent' => $bbpress_id,
                    'custom_fields' => array(
                        array(
                            'key' => 'support_hub',
                            'value' => json_encode($extra_data),
                        )
                    )
                ));
                SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_INFO, 'bbpress', 'API Result: ', $api_result);
            }catch(Exception $e){
                SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_ERROR, 'bbpress', 'API Error: ', $e);
                if($debug){
                    echo "API Error: ".$e;
                }
            }
            if((int) $api_result > 0){
                shub_update_insert('shub_message_comment_id', $bbpress_message_comment_id, 'shub_message_comment', array(
                    'network_key' => $api_result,
                    'time' => time(),
                ));
                return true;
            }else{
                echo "Failed to send comment, check debug log.";
                return false;
            }
            /*if((int) $api_result > 0){
                // we have a post id for our reply!
                // add this reply to the 'comments' array of our existing 'message' object.

                // grab the updated post details for both the parent topic and the newly created reply:
                $parent_topic = $api->getPost($this->get('network_key'));
                SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_INFO, 'bbpress', 'API Result: ', $api_result);
                $reply_post = $api->getPost($api_result);
                SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_INFO, 'bbpress', 'API Result: ', $api_result);

                if($parent_topic && $parent_topic['post_id'] == $this->get('network_key') && $reply_post && $reply_post['post_id'] == $api_result && $reply_post['post_parent'] == $this->get('network_key')){
                    // all looks hunky dory
                    $comments = $this->get('comments');
                    if(!is_array($comments))$comments = array();
                    array_unshift($comments, $reply_post);
                    $parent_topic['replies'] = $comments;
                    // save this updated data to the db
                    $this->load_by_bbpress_id($this->get('network_key'),$parent_topic,$this->get('shub_type'),$debug);
                    $existing_messages = $this->get_comments();
                    foreach($existing_messages as $existing_message){
                        if(!$existing_message['user_id'] && $existing_message['message_text'] == $comments[$bbpress_message_comment_id]['message_text']){
                            shub_update_insert('shub_message_comment_id',$existing_message['shub_message_comment_id'],'shub_message_comment',array(
                                'user_id' => get_current_user_id(),
                            ));
                        }
                    }
                    $this->update('shub_status', _shub_MESSAGE_STATUS_ANSWERED);
                }

            }*/

        }
        return false;
    }


    
    public function send_reply($bbpress_id, $message, $debug = false, $extra_data = array()){
		if($this->account && $this->shub_message_id) {


			$api = $this->account->get_api();
			if($debug)echo "Type: ".$this->get('shub_type')." <br>\n";
			switch($this->get('shub_type')) {
				case 'forum_topic':

					if(!$this->item){
						echo 'Error no forum, report this';
						return false;
					}
					if(!$bbpress_id)$bbpress_id = $this->get('network_key');


					break;
			}



		}
	}


	public function get_type_pretty() {
		$type = $this->get('shub_type');
		switch($type){
			case 'forum_topic':
				return 'Forum Topic';
				break;
			default:
				return ucwords($type);
		}
	}

	public function get_from() {
		if($this->shub_message_id){
			$from = array();
			if($this->get('shub_user_id')){
				$from[$this->get('shub_user_id')] = new SupportHubUser_bbPress($this->get('shub_user_id'));
			}
			$messages = $this->get_comments();
			foreach($messages as $message){
				if($message['shub_user_id'] && !isset($from[$message['shub_user_id']])){
					$from[$message['shub_user_id']] = new SupportHubUser_bbPress($message['shub_user_id']);
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
        $bbpress_item = $this->get('item');
        if(!$shub_product_id && $bbpress_item){
            $shub_product_id = $bbpress_item->get('shub_product_id');
            $item_data = $bbpress_item->get('item_data');
            if(!is_array($item_data))$item_data = array();
        }
        if($shub_product_id) {
            $shub_product = new SupportHubProduct();
            $shub_product->load( $shub_product_id );
            $product_data = $shub_product->get( 'product_data' );
        }
        ?>
        <img src="<?php echo plugins_url('extensions/bbpress/logo.png', _DTBAKER_SUPPORT_HUB_CORE_FILE_);?>" class="shub_message_account_icon">
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
                echo htmlspecialchars( !empty($item_data['post_title']) ? $item_data['post_title'] : '' ); ?></a>
            <br/>
            <?php
        }

        $data = $this->get('shub_data');
        if(!empty($data['buyer_and_author']) && $data['buyer_and_author'] && $data['buyer_and_author'] !== 'false'){
            // hmm - this doesn't seem to be a "purchased" flag.
            /*?>
            <strong>PURCHASED</strong><br/>
            <?php*/
        }
    }

    
    public function get_user_hints($user_hints = array()){
        $user_hints['shub_user_id'][] = $this->get('shub_user_id');
        /*
        $comments         = $this->get_comments();
        $first_comment = current($comments);
        if(isset($first_comment['shub_user_id']) && $first_comment['shub_user_id']){
            $user_hints['shub_user_id'][] = $first_comment['shub_user_id'];
        }
        $message_from = @json_decode($first_comment['message_from'],true);
        if($message_from && isset($message_from['username'])){ //} && $message_from['username'] != $bbpress_message->get('account')->get( 'bbpress_name' )){
            // this wont work if user changes their username, oh well.
            $other_users = new SupportHubUser_bbPress();
            $other_users->load_by_meta('bbpress_username',$message_from['username']);
            if($other_users->get('shub_user_id') && !in_array($other_users->get('shub_user_id'),$user_hints['shub_user_id'])){
                // pass these back to the calling method so we can get the correct values.
                $user_hints['shub_user_id'][] = $other_users->get('shub_user_id');
            }
        }*/
        return $user_hints;
    }

    public function get_user($shub_user_id){
        return new SupportHubUser_bbPress($shub_user_id);
    }
    public function get_reply_user(){
        return new SupportHubUser_bbPress($this->account->get('shub_user_id'));
    }

}