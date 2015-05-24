<?php

class shub_bbpress_message{

	public function __construct($bbpress_account = false, $bbpress_forum = false, $shub_bbpress_message_id = false){
		$this->bbpress_account = $bbpress_account;
		$this->bbpress_forum = $bbpress_forum;
		$this->load($shub_bbpress_message_id);
	}

	/* @var $bbpress_forum shub_bbpress_forum */
	private $bbpress_forum= false;
	/* @var $bbpress_account shub_bbpress_account */
	private $bbpress_account = false;
	private $shub_bbpress_message_id = false; // the current user id in our system.
    private $details = array();

	private function reset(){
		$this->shub_bbpress_message_id = false;
		$this->details = array(
			'shub_bbpress_message_id' => '',
			'shub_bbpress_forum_id' => '',
			'shub_bbpress_id' => '',
			'bbpress_id' => '',
			'title' => '',
			'summary' => '',
			'last_active' => '',
			'comments' => '',
			'type' => '',
			'link' => '',
			'data' => '',
			'status' => '',
			'user_id' => '',
		);
		foreach($this->details as $key=>$val){
			$this->{$key} = '';
		}
	}

	public function create_new(){
		$this->reset();
		$this->shub_bbpress_message_id = shub_update_insert('shub_bbpress_message_id',false,'shub_bbpress_message',array());
		$this->load($this->shub_bbpress_message_id);
	}

	public function load_by_bbpress_id($bbpress_id, $message_data, $type, $debug = false){

		switch($type){
			case 'forum_comment':
				$existing = shub_get_single('shub_bbpress_message', 'bbpress_id', $bbpress_id);
				if($existing){
					// load it up.
					$this->load($existing['shub_bbpress_message_id']);
				}
				if($message_data && isset($message_data['id']) && $message_data['id'] == $bbpress_id){
					if(!$existing){
						$this->create_new();
					}
					$this->update('shub_bbpress_id',$this->bbpress_account->get('shub_bbpress_id'));
					$this->update('shub_bbpress_forum_id',$this->bbpress_forum->get('shub_bbpress_forum_id'));
					$comments = $message_data['conversation'];
					$this->update('title',$comments[0]['content']);
					$this->update('summary',$comments[count($comments)-1]['content']);
					$this->update('last_active',strtotime($message_data['last_comment_at']));
					$this->update('type',$type);
					$this->update('data',json_encode($message_data));
					$this->update('link',$message_data['url'] . '/' . $message_data['id']);
					$this->update('bbpress_id', $bbpress_id);
					$this->update('status',_shub_MESSAGE_STATUS_UNANSWERED);
					$this->update('comments',json_encode($comments));

					return $existing;
				}
				break;

		}

	}

    public function load($shub_bbpress_message_id = false){
	    if(!$shub_bbpress_message_id)$shub_bbpress_message_id = $this->shub_bbpress_message_id;
	    $this->reset();
	    $this->shub_bbpress_message_id = $shub_bbpress_message_id;
        if($this->shub_bbpress_message_id){
	        $data = shub_get_single('shub_bbpress_message','shub_bbpress_message_id',$this->shub_bbpress_message_id);
	        foreach($this->details as $key=>$val){
		        $this->details[$key] = $data && isset($data[$key]) ? $data[$key] : $val;
	        }
	        if(!is_array($this->details) || !isset($this->details['shub_bbpress_message_id']) || $this->details['shub_bbpress_message_id'] != $this->shub_bbpress_message_id){
		        $this->reset();
		        return false;
	        }
        }
        foreach($this->details as $key=>$val){
            $this->{$key} = $val;
        }
	    if(!$this->bbpress_account && $this->get('shub_bbpress_id')){
		    $this->bbpress_account = new shub_bbpress_account($this->get('shub_bbpress_id'));
	    }
	    if(!$this->bbpress_forum && $this->get('shub_bbpress_forum_id')) {
		    $this->bbpress_forum = new shub_bbpress_forum($this->bbpress_account, $this->get('shub_bbpress_forum_id'));
	    }
        return $this->shub_bbpress_message_id;
    }

	public function get($field){
		return isset($this->{$field}) ? $this->{$field} : false;
	}


    public function update($field,$value){
	    // what fields to we allow? or not allow?
	    if(in_array($field,array('shub_bbpress_message_id')))return;
        if($this->shub_bbpress_message_id){
            $this->{$field} = $value;
            shub_update_insert('shub_bbpress_message_id',$this->shub_bbpress_message_id,'shub_bbpress_message',array(
	            $field => $value,
            ));
		    // special processing for certain fields.
		    if($field == 'comments'){
			    // we push all thsee messages into a shub_bbpress_message_comment database table
			    // this is so we can do quick lookups on message ids so we dont import duplicate forums from graph (ie: a reply on a message comes in as a separate forum sometimes)
			    $data = @json_decode($value,true);
			    if(is_array($data)) {
				    // clear previous message history.
				    $existing_messages = $this->get_comments(); //shub_get_multiple('shub_bbpress_message_comment',array('shub_bbpress_message_id'=>$this->shub_bbpress_message_id),'shub_bbpress_message_comment_id');
				    //shub_delete_from_db('shub_bbpress_message_comment','shub_bbpress_message_id',$this->shub_bbpress_message_id);
				    $remaining_messages = $this->_update_comments( $data , $existing_messages);
				    // $remaining_messages contains any messages that no longer exist...
				    // todo: remove these? yer prolly. do a quick test on removing a message - i think the only thing is it will show the 'from' name still.
			    }
		    }
        }
    }

	public function parse_links($content = false){
		if(!$this->get('shub_bbpress_message_id'))return;
		// strip out any links in the tweet and write them to the bbpress_message_link table.
		$url_clickable = '~
		            ([\\s(<.,;:!?])                                        # 1: Leading whitespace, or punctuation
		            (                                                      # 2: URL
		                    [\\w]{1,20}+://                                # Scheme and hier-part prefix
		                    (?=\S{1,2000}\s)                               # Limit to URLs less than about 2000 characters long
		                    [\\w\\x80-\\xff#%\\~/@\\[\\]*(+=&$-]*+         # Non-punctuation URL character
		                    (?:                                            # Unroll the Loop: Only allow puctuation URL character if followed by a non-punctuation URL character
		                            [\'.,;:!?)]                            # Punctuation URL character
		                            [\\w\\x80-\\xff#%\\~/@\\[\\]*(+=&$-]++ # Non-punctuation URL character
		                    )*
		            )
		            (\)?)                                                  # 3: Trailing closing parenthesis (for parethesis balancing post processing)
		    ~xS'; // The regex is a non-anchored pattern and does not have a single fixed starting character.
		          // Tell PCRE to spend more time optimizing since, when used on a page load, it will probably be used several times.
		if(!$content){
			$content = $this->get('summary');
			$doing_summary = true;
		}
		$summary = ' ' . $content . ' ';
		if(strlen($summary) && preg_match_all($url_clickable,$summary,$matches)){
			foreach($matches[2] as $id => $url){
				$url = trim($url);
				if(strlen($url)) {
					// wack this url into the database and replace it with our rewritten url.
					$shub_bbpress_message_link_id = shub_update_insert( 'shub_bbpress_message_link_id', false, 'shub_bbpress_message_link', array(
						'shub_bbpress_message_id' => $this->get('shub_bbpress_message_id'),
						'link' => $url,
					) );
					if($shub_bbpress_message_link_id) {
						$new_link = trailingslashit( get_site_url() );
						$new_link .= strpos( $new_link, '?' ) === false ? '?' : '&';
						$new_link .= _support_hub_bbpress_LINK_REWRITE_PREFIX . '=' . $shub_bbpress_message_link_id;
						// basic hash to stop brute force.
						if(defined('AUTH_KEY')){
							$new_link .= ':'.substr(md5(AUTH_KEY.' bbpress link '.$shub_bbpress_message_link_id),1,5);
						}
						$newsummary = trim(preg_replace('#'.preg_quote($url,'#').'#',$new_link,$summary, 1));
						if(strlen($newsummary)){// just incase.
							$summary = $newsummary;
						}
					}
				}
			}
		}
		if(isset($doing_summary) && $doing_summary){
			$this->update('summary',$summary);
		}
		return trim($summary);
	}

	private function _update_comments($data, $existing_messages){
	    if(is_array($data)){
		    foreach($data as $message){
			    if($message['id']){
				    // does this id exist in the db already?
				    $exists = shub_get_single('shub_bbpress_message_comment',array('bbpress_id','shub_bbpress_message_id'),array($message['id'],$this->shub_bbpress_message_id));

				    // create/update a user entry for this comments.
				    $shub_user_id = 0;
				    if(!empty($message['username'])) {
					    $comment_user = new SupportHubUser();
					    $res = $comment_user->load_by( 'user_username', $message['username']);
					    if(!$res){
						    $comment_user -> create_new();
						    $comment_user -> update('user_username', $message['username']);
						    $comment_user -> update_user_data(array(
							    'image' => $message['profile_image_url'],
							    'bbpress' => $message,
						    ));
					    }
					    $shub_user_id = $comment_user->get('shub_user_id');
				    }

				    $shub_bbpress_message_comment_id = shub_update_insert('shub_bbpress_message_comment_id',$exists ? $exists['shub_bbpress_message_comment_id'] : false,'shub_bbpress_message_comment',array(
					    'shub_bbpress_message_id' => $this->shub_bbpress_message_id,
					    'bbpress_id' => $message['id'],
					    'time' => isset($message['created_at']) ? strtotime($message['created_at']) : 0,
					    'data' => json_encode($message),
					    'message_from' => isset($message['username']) ? json_encode(array("username"=>$message['username'],"profile_image_url"=>$message['profile_image_url'])) : '',
					    'message_to' => '',
					    'message_text' => isset($message['content']) ? $message['content'] : '',
					    'shub_user_id' => $shub_user_id,
				    ));
				    if(isset($existing_messages[$shub_bbpress_message_comment_id])){
					    unset($existing_messages[$shub_bbpress_message_comment_id]);
				    }
				    /*if(isset($message['comments']) && is_array($message['comments'])){
					    $existing_messages = $this->_update_messages($message['comments'], $existing_messages);
				    }*/
			    }
		    }
	    }
		return $existing_messages;
	}

	public function delete(){
		if($this->shub_bbpress_message_id) {
			shub_delete_from_db( 'shub_bbpress_message', 'shub_bbpress_message_id', $this->shub_bbpress_message_id );
		}
	}


	public function mark_as_read(){
		if($this->shub_bbpress_message_id && get_current_user_id()){
			$sql = "REPLACE INTO `"._support_hub_DB_PREFIX."shub_bbpress_message_read` SET `shub_bbpress_message_id` = ".(int)$this->shub_bbpress_message_id.", `user_id` = ".(int)get_current_user_id().", read_time = ".(int)time();
			shub_query($sql);
		}
	}

	public function get_summary() {
		// who was the last person to contribute to this post? show their details here instead of the 'summary' box maybe?
		$title = $this->get( 'title' );
		return htmlspecialchars( strlen( $title ) > 80 ? substr( $title, 0, 80 ) . '...' : $title );
//		$summary = $this->get( 'summary' );
//	    return htmlspecialchars( strlen( $title ) > 80 ? substr( $title, 0, 80 ) . '...' : $title ) . ($summary!=$title ? '<br/>' .htmlspecialchars( strlen( $summary ) > 80 ? substr( $summary, 0, 80 ) . '...' : $summary ) : '');
	}

	private $can_reply = false;
	private function _output_block($bbpress_data,$level){
		if($level == 1){
			$comments = $this->get_comments();
			$comment = array_shift($comments);
		}else{
			$comments = array();
			$comment = $bbpress_data;
		}
//		echo '<pre>';echo $level;print_r($comments);echo '</pre>';
//		echo '<pre>';print_r($bbpress_data);echo '</pre>';
		$from = @json_decode($comment['message_from'],true);
		?>
		<div class="bbpress_message">
			<div class="bbpress_message_picture">
				<img src="<?php echo isset($from['profile_image_url']) && $from['profile_image_url'] ? $from['profile_image_url'] : plugins_url('networks/bbpress/default-user.jpg',_DTBAKER_SUPPORT_HUB_CORE_FILE_);?>">
			</div>
			<div class="bbpress_message_header">
				<?php echo shub_bbpress::format_person($from, $this->bbpress_account); ?>
				<span><?php $time = isset($bbpress_data['time']) ? $bbpress_data['time'] : false;
				echo $time ? ' @ ' . shub_print_date($time,true) : '';

				// todo - better this! don't call on every message, load list in main loop and pass through all results.
				if ( isset( $bbpress_data['user_id'] ) && $bbpress_data['user_id'] ) {
					$user_info = get_userdata($bbpress_data['user_id']);
					echo ' (sent by ' . htmlspecialchars($user_info->display_name) . ')';
				}
				?>
				</span>
			</div>
			<div class="bbpress_message_body">
				<div>
					<?php
					echo shub_forum_text($comment['message_text']);?>
				</div>
			</div>
			<div class="bbpress_message_actions">
			</div>
		</div>
		<div class="bbpress_message_replies">
		<?php
		//if(strpos($bbpress_data['message'],'picture')){
			//echo '<pre>'; print_r($bbpress_data); echo '</pre>';
		//}
		if(count($comments)){
			// recursively print out our messages!
			//$messages = array_reverse($messages);
			foreach($comments as $comment){
				$this->_output_block($comment,$level+1);
			}
		}
		if($level <= 1) {
			if ( $this->can_reply && isset( $bbpress_data['id'] ) && $bbpress_data['id'] ) {
				$this->reply_box( $bbpress_data['id'], $level );
			}
		}
		?>
		</div>
		<?php
	}

	public function full_message_output($can_reply = false){
		$this->can_reply = $can_reply;
		// used in shub_bbpress_list.php to display the full message and its messages
		switch($this->get('type')){
			default:
				$bbpress_data = @json_decode($this->get('data'),true);
				$bbpress_data['message'] = $this->get('title');
				$bbpress_data['user_id'] = $this->get('user_id');
//				$bbpress_data['comments'] = array_reverse($this->get_comments());
				//echo '<pre>'; print_r($bbpress_data['comments']); echo '</pre>';
				$this->_output_block($bbpress_data,1);

				break;
		}
	}

	public function reply_box($bbpress_id,$level=1){
		if($this->bbpress_account && $this->shub_bbpress_message_id) {
			$user_data = $this->bbpress_account->get('bbpress_data');

			?>
			<div class="bbpress_message bbpress_message_reply_box bbpress_message_reply_box_level<?php echo $level;?>">
				<div class="bbpress_message_picture">
					<img src="<?php echo isset($user_data['user'],$user_data['user']['image']) ? $user_data['user']['image'] : '#';?>">
				</div>
				<div class="bbpress_message_header">
					<?php echo isset($user_data['user']) ? shub_bbpress::format_person( $user_data['user'], $this->bbpress_account ) : 'Error'; ?>
				</div>
				<div class="bbpress_message_reply">
					<textarea placeholder="Write a reply..."></textarea>
					<button data-bbpress-id="<?php echo htmlspecialchars($bbpress_id);?>" data-id="<?php echo (int)$this->shub_bbpress_message_id;?>"><?php _e('Send');?></button>
					<br/>
					(debug) <input type="checkbox" name="debug" class="reply-debug" value="1">
				</div>
				<div class="bbpress_message_actions"></div>
			</div>
		<?php
		}else{
			?>
			<div class="bbpress_message bbpress_message_reply_box">
				(incorrect settings, please report this bug)
			</div>
			<?php
		}
	}

	public function get_link() {
		return '//themeforest.net/';
	}

	private $attachment_name = '';
	public function add_attachment($local_filename){
		if(is_file($local_filename)){
			$this->attachment_name = $local_filename;
		}
	}
	public function send_queued($debug = false){
		if($this->bbpress_account && $this->shub_bbpress_message_id) {
			// send this message out to bbpress.
			// this is run when user is composing a new message from the UI,
			if ( $this->get( 'status' ) == _shub_MESSAGE_STATUS_SENDING )
				return; // dont double up on cron.


			switch($this->get('type')){
				case 'forum_post':


					if(!$this->bbpress_forum) {
						echo 'No bbpress forum defined';
						return false;
					}

					$this->update( 'status', _shub_MESSAGE_STATUS_SENDING );
					$api = $this->bbpress_account->get_api();
					$bbpress_forum_id = $this->bbpress_forum->get('forum_id');
					if($debug)echo "Sending a new message to bbpress forum ID: $bbpress_forum_id <br>\n";
					$result = false;
					$post_data = array();
					$post_data['summary'] = $this->get('summary');
					$post_data['title'] = $this->get('title');
					$now = time();
					$send_time = $this->get('last_active');
					$result = $api->api('v1/forums/'.$bbpress_forum_id.'/posts',array(),'POST',$post_data,'location');
					if($debug)echo "API Post Result: <br>\n".var_export($result,true)." <br>\n";
					if($result && preg_match('#https://api.bbpress.com/v1/posts/(.*)$#',$result,$matches)){
						// we have a result from the API! this should be an API call in itself:
						$new_post_id = $matches[1];
						$this->update('bbpress_id',$new_post_id);
						// reload this message and messages from the graph api.
						$this->load_by_bbpress_id($this->get('bbpress_id'),false,$this->get('type'),$debug, true);
					}else{
						echo 'Failed to send message. Error was: '.var_export($result,true);
						// remove from database.
						$this->delete();
						return false;
					}

					// successfully sent, mark is as answered.
					$this->update( 'status', _shub_MESSAGE_STATUS_ANSWERED );
					return true;

					break;
				case 'share':

					$this->update( 'status', _shub_MESSAGE_STATUS_SENDING );
					$api = $this->bbpress_account->get_api();
					if($debug)echo "Sending a new share update to bbpress account: " . $this->bbpress_account->get('bbpress_name') ."<br>\n";
					$result = false;
					/*
					 *
					 *  {
						  "message": "Check out developer.bbpress.com!",
						  "content": {
						    "title": "bbpress Developers Resources",
						    "description": "Leverage bbpress's APIs to maximize engagement",
						    "submitted-url": "https://developer.bbpress.com",
						    "submitted-image-url": "https://example.com/logo.png"
						  },
						  "visibility": {
						    "code": "anyone"
						  }
						}
					 */
					$user_post_data = @json_decode($this->get('data'),true);
					if(isset($user_post_data['link_picture']) && !empty($user_post_data['link_picture'])){
						$post_data['picture'] = $user_post_data['link_picture'];
					}
					$post_data = array();
					$post_data['message'] = $this->get('summary');

					if($this->get('link')){
						$post_data['content'] = array(
							'title' => $this->get('title'),
							//'description' => $this->get('summary'),
							'submitted-url' => $this->get('link'),
							'submitted-image-url' => isset($user_post_data['bbpress_picture_url']) && !empty($user_post_data['bbpress_picture_url']) ? $user_post_data['bbpress_picture_url'] : '',
						);
					}
					$post_data['visibility'] = array(
						'code' => 'anyone',
					);
					$now = time();
					$send_time = $this->get('last_active');
					$result = $api->api('v1/people/~/shares',array(),'POST',$post_data);
					if($debug)echo "API Post Result: <br>\n".var_export($result,true)." <br>\n";
					if(is_array($result) && isset($result['updateKey']) && !empty($result['updateKey'])){
						$this->update('bbpress_id',$result['updateKey']);
						// reload this message and messages from the graph api.
						$this->load_by_bbpress_id($this->get('bbpress_id'),false,$this->get('type'),$debug, true);
					}else{
						echo 'Failed to send message. Error was: '.var_export($result,true);
						// remove from database.
						$this->delete();
						return false;
					}

					// successfully sent, mark is as answered.
					$this->update( 'status', _shub_MESSAGE_STATUS_ANSWERED );
					return true;

					break;
				default:
					if($debug)echo "Unknown post type: ".$this->get('type');
			}

		}
		return false;
	}
	public function send_reply($bbpress_id, $message, $debug = false){
		if($this->bbpress_account && $this->shub_bbpress_message_id) {


			$api = $this->bbpress_account->get_api();
			if($debug)echo "Type: ".$this->get('type')." <br>\n";
			switch($this->get('type')) {
				case 'share':
					if(!$bbpress_id)$bbpress_id = $this->get('bbpress_id');

					if($debug)echo "Sending a reply to bbpress share ID: $bbpress_id <br>\n";

					$result = false;
					// send via api
					$api_result = $api->api('v1/people/~/network/updates/key='.$this->get('bbpress_id').'/update-messages',array(),'POST',array(
						'message' => $message,
					));
					if($debug){
						echo "API Result:";
						print_r($api_result);
					}
					// reload this message and messages from the bbpress api
					$this->load_by_bbpress_id($this->get('bbpress_id'),false,$this->get('type'),$debug,true);

					// hack to add the 'user_id' of who created this reply to the db for logging.
					// the message is added to our db in the "load_bu_bbpress_id" api call.
					$existing_messages = $this->get_comments(); //shub_get_multiple('shub_bbpress_message_comment',array('shub_bbpress_message_id'=>$this->get('shub_bbpress_message_id')),'shub_bbpress_message_comment_id');
					foreach($existing_messages as $existing_message){
						if(!$existing_message['user_id'] && $existing_message['message_text'] == $message){
							shub_update_insert('shub_bbpress_message_comment_id',$existing_message['shub_bbpress_message_comment_id'],'shub_bbpress_message_comment',array(
								'user_id' => get_current_user_id(),
							));
						}
					}

					break;
				case 'forum_post':

					if($this->bbpress_forum){
						echo 'Error no forum, report this';
						return false;
					}
					if(!$bbpress_id)$bbpress_id = $this->get('bbpress_id');

					if($debug)echo "Sending a reply to bbpress ID: $bbpress_id <br>\n";
					$result = false;
					// send via api
					$api_result = $api->api('v1/posts/'.$bbpress_id.'/messages',array(),'POST',array(
						'text' => $message,
					));
					if($debug){
						echo "API Result:";
						print_r($api_result);
					}
					// reload this message and messages from the bbpress api
					$this->load_by_bbpress_id($this->get('bbpress_id'),false,$this->get('type'),$debug,true);

					// hack to add the 'user_id' of who created this reply to the db for logging.
					// the message is added to our db in the "load_bu_bbpress_id" api call.
					$existing_messages = $this->get_comments(); //shub_get_multiple('shub_bbpress_message_comment',array('shub_bbpress_message_id'=>$this->get('shub_bbpress_message_id')),'shub_bbpress_message_comment_id');
					foreach($existing_messages as $existing_message){
						if(!$existing_message['user_id'] && $existing_message['message_text'] == $message){
							shub_update_insert('shub_bbpress_message_comment_id',$existing_message['shub_bbpress_message_comment_id'],'shub_bbpress_message_comment',array(
								'user_id' => get_current_user_id(),
							));
						}
					}
					break;
			}



		}
	}
	public function get_comments($message_data = false) {
		if($message_data){
			$messages = $message_data;
			if(!is_array($messages))$messages=array();
			usort($messages,function($a,$b){
				if(isset($a['id'])){
					return $a['id'] > $b['id'];
				}
				return strtotime($a['created_at']) > strtotime($b['created_at']);
			});
		}else{
			$messages = shub_get_multiple('shub_bbpress_message_comment',array('shub_bbpress_message_id'=>$this->shub_bbpress_message_id),'shub_bbpress_message_comment_id'); //@json_decode($this->get('comments'),true);
		}
		return $messages;
	}

	public function get_type_pretty() {
		$type = $this->get('type');
		switch($type){
			case 'forum_comment':
				return 'forum Comment';
				break;
			default:
				return ucwords($type);
		}
	}

	public function get_from() {
		if($this->shub_bbpress_message_id){
			$from = array();
			$messages = $this->get_comments(); //shub_get_multiple('shub_bbpress_message_comment',array('shub_bbpress_message_id'=>$this->shub_bbpress_message_id),'shub_bbpress_message_comment_id');
			foreach($messages as $message){
				if($message['message_from']){
					$data = @json_decode($message['message_from'],true);
					if(isset($data['username'])){
						$from[$data['username']] = array(
							'name' => $data['username'],
							'image' => isset($data['profile_image_url']) ? $data['profile_image_url'] : plugins_url('networks/bbpress/default-user.jpg',_DTBAKER_SUPPORT_HUB_CORE_FILE_),
							'link' => 'http://themeforest.net/user/' . $data['username'],
						);
					}
				}
			}
			return $from;
		}
		return array();
	}


	public function link_open(){
		return 'admin.php?page=support_hub_main&shub_bbpress_id='.$this->bbpress_account->get('shub_bbpress_id').'&shub_bbpress_message_id='.$this->shub_bbpress_message_id;
	}


}