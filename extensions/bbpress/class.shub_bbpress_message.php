<?php

class shub_bbpress_message extends SupportHub_message{

    protected $network = 'bbpress';

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

    public $json_fields = array('data','comments');

	private function reset(){
		$this->shub_bbpress_message_id = false;
		$this->details = array(
			'shub_bbpress_message_id' => '',
			'shub_bbpress_forum_id' => '',
			'shub_bbpress_id' => '',
			'shub_product_id' => -1,
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
			'shub_user_id' => 0,
		);
		foreach($this->details as $key=>$val){
			$this->{$key} = '';
		}
	}

	public function create_new(){
		$this->reset();
		$this->shub_bbpress_message_id = shub_update_insert('shub_bbpress_message_id',false,'shub_bbpress_message',array(
            'title' => '',
        ));
		$this->load($this->shub_bbpress_message_id);
	}

	public function load_by_bbpress_id($bbpress_id, $topic_data, $type, $debug = false){

		switch($type){
			case 'forum_topic':
				/*{"post_id":"3381","post_title":"Child THEME IMPOSSIBLE","post_date":{"scalar":"20150304T00:19:51","xmlrpc_type":"datetime","timestamp":1425428391},"post_date_gmt":{"scalar":"20150304T00:19:51","xmlrpc_type":"datetime","timestamp":1425428391},"post_modified":{"scalar":"20150304T00:19:51","xmlrpc_type":"datetime","timestamp":1425428391},"post_modified_gmt":{"scalar":"20150304T00:19:51","xmlrpc_type":"datetime","timestamp":1425428391},"post_status":"publish","post_type":"topic","post_name":"child-theme-impossible","post_author":"1442","post_password":"","post_excerpt":"","post_content":"Has anyone been able to create a child theme that keeps the animations please help","post_parent":"2613","post_mime_type":"","link":"http:\/\/dtbaker.net\/forums\/topic\/child-theme-impossible\/","guid":"http:\/\/dtbaker.net\/forums\/topic\/child-theme-impossible\/","menu_order":0,"comment_status":"closed","ping_status":"closed","sticky":false,"post_thumbnail":[],"post_format":"standard","terms":[],"custom_fields":[{"id":"9897","key":"author","value":""},{"id":"9898","key":"stars","value":""}],"replies":[{"post_id":"3409","post_title":"","post_date":{"scalar":"20150413T18:16:41","xmlrpc_type":"datetime","timestamp":1428949001},"post_date_gmt":{"scalar":"20150413T18:16:41","xmlrpc_type":"datetime","timestamp":1428949001},"post_modified":{"scalar":"20150413T18:16:41","xmlrpc_type":"datetime","timestamp":1428949001},"post_modified_gmt":{"scalar":"20150413T18:16:41","xmlrpc_type":"datetime","timestamp":1428949001},"post_status":"publish","post_type":"reply","post_name":"3409","post_author":"1692","post_password":"","post_excerpt":"","post_content":"I just purchased this last week and am having problems too. Support for this template seems to be nonexistent. This design has been approved by my client but I'm unable to get the theme to function correctly and I can't get any support from the developer. The deadline for this site is approaching and I'm dead in the water. Hopefully I can get a refund on this one.","post_parent":"3381","post_mime_type":"","link":"http:\/\/dtbaker.net\/forums\/reply\/3409\/","guid":"http:\/\/dtbaker.net\/forums\/reply\/3409\/","menu_order":2,"comment_status":"closed","ping_status":"closed","sticky":false,"post_thumbnail":[],"post_format":"standard","terms":[],"custom_fields":[]},{"post_id":"3394","post_title":"","post_date":{"scalar":"20150318T22:42:54","xmlrpc_type":"datetime","timestamp":1426718574},"post_date_gmt":{"scalar":"20150318T22:42:54","xmlrpc_type":"datetime","timestamp":1426718574},"post_modified":{"scalar":"20150318T22:42:54","xmlrpc_type":"datetime","timestamp":1426718574},"post_modified_gmt":{"scalar":"20150318T22:42:54","xmlrpc_type":"datetime","timestamp":1426718574},"post_status":"publish","post_type":"reply","post_name":"3394","post_author":"1458","post_password":"","post_excerpt":"","post_content":"I have not been able to and just posted that same question before I found your question..","post_parent":"3381","post_mime_type":"","link":"http:\/\/dtbaker.net\/forums\/reply\/3394\/","guid":"http:\/\/dtbaker.net\/forums\/reply\/3394\/","menu_order":1,"comment_status":"closed","ping_status":"closed","sticky":false,"post_thumbnail":[],"post_format":"standard","terms":[],"custom_fields":[]}],"timestamp":1428949001}*/
				$existing = shub_get_single('shub_bbpress_message', 'bbpress_id', $bbpress_id);
				if($existing){
					// load it up.
					$this->load($existing['shub_bbpress_message_id']);
				}
				if($topic_data && isset($topic_data['post_id']) && $topic_data['post_id'] == $bbpress_id){
					if(!$existing){
						$this->create_new();
					}
					$this->update('shub_bbpress_id',$this->bbpress_account->get('shub_bbpress_id'));
					$this->update('shub_bbpress_forum_id',$this->bbpress_forum->get('shub_bbpress_forum_id'));
					$comments = $topic_data['replies'];
					$this->update('title',$topic_data['post_content']);
					// latest comment goes in summary
					$this->update('summary',isset($comments[0]) ? $comments[0]['post_content'] : $topic_data['post_content']);
					$this->update('last_active',!empty($topic_data['timestamp']) ? $topic_data['timestamp'] : (is_array($topic_data['post_date']) ? $topic_data['post_date']['timestamp'] : (isset($topic_data['post_date']->timestamp) ? $topic_data['post_date']->timestamp : 0)));
					$this->update('type',$type);
					$this->update('data',$topic_data);
					$this->update('link',$topic_data['link'].'#post-'.(isset($comments[0]) ? $comments[0]['post_id'] : $topic_data['post_id']));
					$this->update('bbpress_id', $bbpress_id);
                    if($this->get('status')!=_shub_MESSAGE_STATUS_HIDDEN) $this->update('status', _shub_MESSAGE_STATUS_UNANSWERED);
					$this->update('comments',$comments);
					// create/update a user entry for this comments.
				    $shub_user_id = $this->bbpress_account->get_api_user_to_id($topic_data['post_author']);
					$this->update('shub_user_id',$shub_user_id);

					return $this->get('shub_bbpress_message_id');
				}
				break;

		}
		return false;

	}

    public function load($shub_bbpress_message_id = false){
	    if(!$shub_bbpress_message_id)$shub_bbpress_message_id = $this->shub_bbpress_message_id;
	    $this->reset();
	    $this->shub_bbpress_message_id = $shub_bbpress_message_id;
        if($this->shub_bbpress_message_id){
	        $data = shub_get_single('shub_bbpress_message','shub_bbpress_message_id',$this->shub_bbpress_message_id);
	        foreach($this->details as $key=>$val){
		        $this->details[$key] = $data && isset($data[$key]) ? $data[$key] : $val;
                if(in_array($key,$this->json_fields)){
                    $this->details[$key] = @json_decode($this->details[$key],true);
                    if(!is_array($this->details[$key]))$this->details[$key] = array();
                }
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
            if(in_array($field,$this->json_fields)){
                $value = json_encode($value);
            }
            shub_update_insert('shub_bbpress_message_id',$this->shub_bbpress_message_id,'shub_bbpress_message',array(
	            $field => $value,
            ));
		    // special processing for certain fields.
		    if($field == 'comments'){
			    // we push all thsee messages into a shub_bbpress_message_comment database table
			    // this is so we can do quick lookups on message ids so we dont import duplicate forums from graph (ie: a reply on a message comes in as a separate forum sometimes)
                $data = is_array($value) ? $value : @json_decode($value,true);
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
			    if($message['post_id']){
				    // does this id exist in the db already?
				    $exists = shub_get_single('shub_bbpress_message_comment',array('bbpress_id','shub_bbpress_message_id'),array($message['post_id'],$this->shub_bbpress_message_id));

				    // create/update a user entry for this comments.
				    // create/update a user entry for this comments.
				    $shub_user_id = $this->bbpress_account->get_api_user_to_id($message['post_author']);

				    $shub_bbpress_message_comment_id = shub_update_insert('shub_bbpress_message_comment_id',$exists ? $exists['shub_bbpress_message_comment_id'] : false,'shub_bbpress_message_comment',array(
					    'shub_bbpress_message_id' => $this->shub_bbpress_message_id,
					    'bbpress_id' => $message['post_id'],
					    'time' => isset($message['post_date']) ? (is_array($message['post_date']) ? $message['post_date']['timestamp'] : $message['post_date']->timestamp) : 0,
					    'data' => json_encode($message),
					    'message_from' => '',
					    'message_to' => '',
					    'message_text' => isset($message['post_content']) ? $message['post_content'] : '',
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
			// display the info from the main 'message' table
			$comments = $this->get_comments();
			$comment = array(
				'title' => $bbpress_data['post_title'],
				'shub_user_id' => $this->get('shub_user_id'),
				'time' => $this->get('last_active'),
				'message_text' => $this->get('title'),
				'user_id' => $this->get('user_id'),
			);
		}else{
			// display the info from the 'comments' table.
			$comment = $bbpress_data;
			$comments = array();
		}
//		echo '<pre>';echo $level;print_r($comments);echo '</pre>';
		//echo '<pre>';print_r($bbpress_data);echo '</pre>';
		$from = new SupportHubUser_bbPress($comment['shub_user_id']);
		?>
		<div class="shub_message">
			<div class="shub_message_picture">
				<?php if($from->get_image()){ ?>
				<img src="<?php echo $from->get_image() ? $from->get_image() : plugins_url('extensions/bbpress/default-user.jpg',_DTBAKER_SUPPORT_HUB_CORE_FILE_);?>">
				<?php } ?>
			</div>
			<div class="shub_message_header">
				<?php echo shub_bbpress::format_person($from, $this->bbpress_account); ?>
				<span><?php $time = isset($comment['time']) ? $comment['time'] : false;
				echo $time ? ' @ ' . shub_print_date($time,true) : '';

				// todo - better this! don't call on every message, load list in main loop and pass through all results.
				if ( isset( $comment['user_id'] ) && $comment['user_id'] ) {
					$user_info = get_userdata($comment['user_id']);
					echo ' (sent by ' . htmlspecialchars($user_info->display_name) . ')';
				}
				?>
				</span>
			</div>
			<div class="shub_message_body">
				<?php if(!empty($comment['title'])){ ?>
				<div class="shub_message_title">
					<?php echo htmlspecialchars($comment['title']);?>
				</div>
				<?php } ?>
				<div>
					<?php echo shub_forum_text($comment['message_text']);?>
				</div>
			</div>
			<div class="shub_message_actions">
			</div>
		</div>
		<div class="shub_message_replies">
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
			if ( $this->can_reply && isset( $bbpress_data['post_id'] ) && $bbpress_data['post_id'] ) {
				$this->reply_box( $bbpress_data['post_id'], $level );
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
			$from = new SupportHubUser_bbPress($user_data['user']['shub_user_id']);
			?>
			<div class="shub_message shub_message_reply_box shub_message_reply_box_level<?php echo $level;?>">
				<div class="shub_message_picture">
					<img src="<?php echo $from->get_image();?>">
				</div>
				<div class="shub_message_header">
					<?php echo shub_bbpress::format_person( $from, $this->bbpress_account ); ?>
				</div>
				<div class="shub_message_reply bbpress_message_reply">
					<textarea placeholder="Write a reply..."></textarea>
					<button data-bbpress-id="<?php echo htmlspecialchars($bbpress_id);?>" data-post="<?php echo esc_attr(json_encode(array(
						'id' => (int)$this->shub_bbpress_message_id,
                        'network' => 'bbpress',
						'bbpress_id' => htmlspecialchars($bbpress_id),
					)));?>"><?php _e('Send');?></button>
				</div>
				<div class="shub_message_actions">
					<!-- (debug) <input type="checkbox" name="debug" data-reply="yes" value="1"> <br/> -->
					<?php
					if(isset($user_data['reply_options']) && is_array($user_data['reply_options'])){
						foreach($user_data['reply_options'] as $reply_option){
							if(isset($reply_option['title'])){
								echo htmlspecialchars($reply_option['title']);
								if(isset($reply_option['field']) && is_array($reply_option['field'])){
									$reply_option['field']['name'] = 'extra-'.$reply_option['field']['name'];
									$reply_option['field']['data'] = array(
										'reply' => 'yes'
									);
									shub_module_form::generate_form_element($reply_option['field']);
								}
								echo '<br/>';
							}
						}
					}
					?>
				</div>
			</div>
		<?php
		}else{
			?>
			<div class="shub_message shub_message_reply_box">
				(incorrect settings, please report this bug)
			</div>
			<?php
		}
	}

    public function reply_actions(){
        $user_data = $this->bbpress_account->get('bbpress_data');
        if(isset($user_data['reply_options']) && is_array($user_data['reply_options'])){
            foreach($user_data['reply_options'] as $reply_option){
                if(isset($reply_option['title'])){
                    echo '<div>';
                    if(isset($reply_option['field']) && is_array($reply_option['field'])){
                        $reply_option['field']['name'] = 'extra-'.$reply_option['field']['name'];
                        $reply_option['field']['data'] = array(
                            'reply' => 'yes'
                        );
                        shub_module_form::generate_form_element($reply_option['field']);
                    }
                    echo '<label for="">'.htmlspecialchars($reply_option['title']).'</label>';
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
						$this->load_by_bbpress_id($this->get('bbpress_id'),false,$this->get('type'),$debug);
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
	public function send_reply($bbpress_id, $message, $debug = false, $extra_data = array()){
		if($this->bbpress_account && $this->shub_bbpress_message_id) {


			$api = $this->bbpress_account->get_api();
			if($debug)echo "Type: ".$this->get('type')." <br>\n";
			switch($this->get('type')) {
				case 'forum_topic':

					if(!$this->bbpress_forum){
						echo 'Error no forum, report this';
						return false;
					}
					if(!$bbpress_id)$bbpress_id = $this->get('bbpress_id');

					$bbpress_post_data = $this->get('data');

					if($debug)echo "Sending a reply to bbPress Topic ID: $bbpress_id <br>\n";
					$api_result = false;
					try{
						$extra_data['api'] = 1;
						$api_result = $api->newPost('Reply to: '.((isset($bbpress_post_data['post_title'])) ? $bbpress_post_data['post_title'] : 'Post'),$message,array(
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
						// we have a post id for our reply!
						// add this reply to the 'comments' array of our existing 'message' object.

						// grab the updated post details for both the parent topic and the newly created reply:
						$parent_topic = $api->getPost($this->get('bbpress_id'));
						SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_INFO, 'bbpress', 'API Result: ', $api_result);
						$reply_post = $api->getPost($api_result);
						SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_INFO, 'bbpress', 'API Result: ', $api_result);

						if($parent_topic && $parent_topic['post_id'] == $this->get('bbpress_id') && $reply_post && $reply_post['post_id'] == $api_result && $reply_post['post_parent'] == $this->get('bbpress_id')){
							// all looks hunky dory
							$comments = $this->get('comments');
							if(!is_array($comments))$comments = array();
							array_unshift($comments, $reply_post);
							$parent_topic['replies'] = $comments;
							// save this updated data to the db
							$this->load_by_bbpress_id($this->get('bbpress_id'),$parent_topic,$this->get('type'),$debug);
							$existing_messages = $this->get_comments();
							foreach($existing_messages as $existing_message){
								if(!$existing_message['user_id'] && $existing_message['message_text'] == $message){
									shub_update_insert('shub_bbpress_message_comment_id',$existing_message['shub_bbpress_message_comment_id'],'shub_bbpress_message_comment',array(
										'user_id' => get_current_user_id(),
									));
								}
							}
							$this->update('status', _shub_MESSAGE_STATUS_ANSWERED);
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
			$messages = shub_get_multiple('shub_bbpress_message_comment',array('shub_bbpress_message_id'=>$this->shub_bbpress_message_id),'shub_bbpress_message_comment_id','time'); //@json_decode($this->get('comments'),true);
		}
		return $messages;
	}

	public function get_type_pretty() {
		$type = $this->get('type');
		switch($type){
			case 'forum_topic':
				return 'Forum Topic';
				break;
			default:
				return ucwords($type);
		}
	}

	public function get_from() {
		if($this->shub_bbpress_message_id){
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

	public function get_product_id(){
		// if local product is id -1 (default) then we use the parent forum product id
		// this allows individual products to be overrideen with new one
		if($this->get('shub_product_id') >= 0){
			return $this->get('shub_product_id');
		}else{
			return $this->bbpress_forum->get('shub_product_id');
		}
	}

	public function save_product_id($new_product_id){
		if($new_product_id == $this->bbpress_forum->get('shub_product_id')){
			// setting it back to default.
			$this->update('shub_product_id', -1);
		}else{
			$this->update('shub_product_id', $new_product_id);
		}
	}


	public function link_open(){
        return 'admin.php?page=support_hub_main&network=bbpress&message_id='.$this->shub_bbpress_message_id;
	}

    public function message_sidebar_data(){

        // find if there is a product here
        $shub_product_id = $this->get_product_id();
        $product_data = array();
        $bbpress_item_data = array();
        $bbpress_item = $this->get('bbpress_item');
        if(!$shub_product_id && $bbpress_item){
            $shub_product_id = $bbpress_item->get('shub_product_id');
            $bbpress_item_data = $bbpress_item->get('bbpress_data');
            if(!is_array($bbpress_item_data))$bbpress_item_data = array();
        }
        if($shub_product_id) {
            $shub_product = new SupportHubProduct();
            $shub_product->load( $shub_product_id );
            $product_data = $shub_product->get( 'product_data' );
        }
        ?>
        <img src="<?php echo plugins_url('extensions/bbpress/bbpress-logo.png', _DTBAKER_SUPPORT_HUB_CORE_FILE_);?>" class="shub_message_account_icon">
        <?php
        if($shub_product_id && !empty($product_data['image'])) {
            ?>
            <img src="<?php echo $product_data['image'];?>" class="shub_message_account_icon">
            <?php
        }
        ?>
        <br/>


        <strong><?php _e('Account:');?></strong> <a href="<?php echo $this->get_link(); ?>" target="_blank"><?php echo htmlspecialchars( $this->get('bbpress_account') ? $this->get('bbpress_account')->get( 'bbpress_name' ) : 'N/A' ); ?></a> <br/>

        <strong><?php _e('Time:');?></strong> <?php echo shub_print_date( $this->get('last_active'), true ); ?>  <br/>

        <?php
        if($bbpress_item_data){
            ?>
            <strong><?php _e('Item:');?></strong>
            <a href="<?php echo isset( $bbpress_item_data['url'] ) ? $bbpress_item_data['url'] : $this->get_link(); ?>"
               target="_blank"><?php
                echo htmlspecialchars( $bbpress_item_data['item'] ); ?></a>
            <br/>
            <?php
        }

        $data = $this->get('data');
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
        if($message_from && isset($message_from['username'])){ //} && $message_from['username'] != $bbpress_message->get('bbpress_account')->get( 'bbpress_name' )){
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
        return new SupportHubUser_bbPress($this->bbpress_account->get('shub_user_id'));
    }

}