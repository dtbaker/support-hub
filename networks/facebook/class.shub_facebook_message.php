<?php

class shub_facebook_message{

	public function __construct($facebook_account = false, $facebook_page_or_group = false, $shub_facebook_message_id = false){
		$this->facebook_account = $facebook_account;
		$this->facebook_page_or_group = $facebook_page_or_group;
		$this->load($shub_facebook_message_id);
	}

	/* @var $facebook_page_or_group  shub_facebook_page shub_facebook_group */
	private $facebook_page_or_group= false;
	/* @var $facebook_account shub_facebook_account */
	private $facebook_account = false;
	private $shub_facebook_message_id = false; // the current user id in our system.
    private $details = array();

	private function reset(){
		$this->shub_facebook_message_id = false;
		$this->details = array(
			'shub_facebook_message_id' => '',
			'marketing_message_id' => '',
			'shub_facebook_page_id' => '',
			'shub_facebook_group_id' => '',
			'shub_facebook_id' => '',
			'facebook_id' => '',
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
		$this->shub_facebook_message_id = shub_update_insert('shub_facebook_message_id',false,'shub_facebook_message',array());
		$this->load($this->shub_facebook_message_id);
	}

	public function load_by_facebook_id($facebook_id, $message_data, $type, $debug = false){

		$message_type = 'personal';
		$access_token = $this->facebook_account->get( 'facebook_token' );
		if($this->facebook_page_or_group) {
			switch ( get_class( $this->facebook_page_or_group ) ) {
				case 'shub_facebook_group':
					$access_token = $this->facebook_account->get( 'facebook_token' );
					$message_type = 'group';
					break;
				case 'shub_facebook_page':
					$access_token = $this->facebook_page_or_group ? $this->facebook_page_or_group->get( 'facebook_token' ) : '';
					$message_type = 'page';
					break;
			}
		}

		// get the machine id from the parent facebook_account
		$machine_id = $this->facebook_account ? $this->facebook_account->get('machine_id') : '';

		if(!$message_data){
			$facebook_api = new shub_facebook();
			$data = $facebook_api->graph($facebook_id,array(
				'access_token' => $access_token,
				'machine_id' => $machine_id,
			));
			if($data && isset($data['id'])){
				$message_data = $data;
			}else{
				return false;
			}
		}

		// check if exists already
		$existing = shub_get_single('shub_facebook_message', 'facebook_id', $facebook_id);
		if($existing){
			// load it up.
			$this->load($existing['shub_facebook_message_id']);
		}else{
			// ignore if status and feeds match a user
			if($message_type == 'page' && isset($message_data['type']) && $message_data['type'] == 'status' && $message_data['from']['id'] == $this->facebook_page_or_group->get('page_id') && isset($message_data['story_tags']) && $message_data['story_tags']){
				$tags = current($message_data['story_tags']);
				if($tags[0]['type'] == 'user'){
					return false;
				}
			}
			// create
			$this->create_new();
		}
		// wack out message data into the database.
		if($type == 'conversation'){
			$message_time = strtotime(isset($message_data['updated_time']) && strlen($message_data['updated_time']) ? $message_data['updated_time'] : $message_data['created_time']);
			$this->update('last_active', $message_time);
			$this->update('facebook_id', $message_data['id']);
			$this->update('summary', $message_data['snippet']);
			$this->update('comments', isset($message_data['messages']) ? json_encode( $message_data['messages'] ) : '');
            if($this->get('status')!=_shub_MESSAGE_STATUS_HIDDEN) {
                if (isset($message_data['messages']['data'][0]['from']['id']) && $this->facebook_page_or_group && $message_data['messages']['data'][0]['from']['id'] == $this->facebook_page_or_group->get('page_id')) {
                    // was the last comment from us?
                    $this->update('status', _shub_MESSAGE_STATUS_ANSWERED);
                } else {
                    $this->update('status', _shub_MESSAGE_STATUS_UNANSWERED);
                }
            }
			$this->update('data',json_encode($message_data));
			$this->update('type',isset($message_data['type']) ? $message_data['type'] : $type);
			if($this->facebook_page_or_group){
				$this->update('shub_facebook_page_id',$this->facebook_page_or_group->get('shub_facebook_page_id'));
			}
			if($this->facebook_account){
				$this->update('shub_facebook_id',$this->facebook_account->get('shub_facebook_id'));
			}
		}else{
			$message_time = strtotime(isset($message_data['updated_time']) && strlen($message_data['updated_time']) ? $message_data['updated_time'] : $message_data['created_time']);
			$this->update('last_active', $message_time);
			$this->update('facebook_id', $message_data['id']);
			$this->update('summary', isset($message_data['message']) ? $message_data['message'] : (isset($message_data['story']) ? $message_data['story'] : 'N/A'));
			// grab the comments rom the api again.
			$facebook_api = new shub_facebook();
			$data = $facebook_api->graph($message_data['id'].'/comments',array(
				//'filter'=>'stream',
				//'fields'=>'from,message,id,attachment,created_time',
				'fields'=>'from,message,id,attachment,created_time,comments.fields(from,message,id,attachment,created_time)',
				'access_token' => $access_token,
				'machine_id' => $machine_id,
			));
			$comments = isset($data) ? $data : (isset($message_data['comments']) ? $message_data['comments'] : false);
			$this->update('comments', json_encode($comments));
            if($this->get('status')!=_shub_MESSAGE_STATUS_HIDDEN) {
                if ($message_type == 'page') {
                    if (isset($message_data['comments']['data'][0]['from']['id']) && $this->facebook_page_or_group && $message_data['comments']['data'][0]['from']['id'] == $this->facebook_page_or_group->get('page_id')) {
                        // was the last comment from us?
                        $this->update('status', _shub_MESSAGE_STATUS_ANSWERED);
                    } else {
                        $this->update('status', _shub_MESSAGE_STATUS_UNANSWERED);
                    }
                    if (isset($message_data['messages']['data'][0]['from']['id']) && $this->facebook_page_or_group && $message_data['messages']['data'][0]['from']['id'] == $this->facebook_page_or_group->get('page_id')) {
                        // was the last comment from us?
                        $this->update('status', _shub_MESSAGE_STATUS_ANSWERED);
                    } else {
                        $this->update('status', _shub_MESSAGE_STATUS_UNANSWERED);
                    }
                } else {
                    $me = @json_decode($this->facebook_account->get('facebook_data'), true);
                    if (is_array($me) && isset($me['me']['id'])) {
                        if (isset($message_data['comments']['data'][0]['from']['id']) && $this->facebook_page_or_group && $message_data['comments']['data'][0]['from']['id'] == $me['me']['id']) {
                            // was the last comment from us?
                            $this->update('status', _shub_MESSAGE_STATUS_ANSWERED);
                        } else {
                            $this->update('status', _shub_MESSAGE_STATUS_UNANSWERED);
                        }
                        if (isset($message_data['messages']['data'][0]['from']['id']) && $this->facebook_page_or_group && $message_data['messages']['data'][0]['from']['id'] == $me['me']['id']) {
                            // was the last comment from us?
                            $this->update('status', _shub_MESSAGE_STATUS_ANSWERED);
                        } else {
                            $this->update('status', _shub_MESSAGE_STATUS_UNANSWERED);
                        }
                    } else {
                        $this->update('status', _shub_MESSAGE_STATUS_UNANSWERED);
                    }

                }
            }
			$this->update('data',json_encode($message_data));
			$this->update('type',isset($message_data['type']) ? $message_data['type'] : $type);
			if($this->facebook_page_or_group){
				if($message_type == 'page'){
					$this->update('shub_facebook_page_id',$this->facebook_page_or_group->get('shub_facebook_page_id'));
				}elseif($message_type == 'group'){
					$this->update('shub_facebook_group_id',$this->facebook_page_or_group->get('shub_facebook_group_id'));
				}

			}
			if($this->facebook_account){
				$this->update('shub_facebook_id',$this->facebook_account->get('shub_facebook_id'));
			}
		}

		// work out if this message is answered or not.


		return $this->shub_facebook_message_id;
	}

    public function load($shub_facebook_message_id = false){
	    if(!$shub_facebook_message_id)$shub_facebook_message_id = $this->shub_facebook_message_id;
	    $this->reset();
	    $this->shub_facebook_message_id = $shub_facebook_message_id;
        if($this->shub_facebook_message_id){
	        $data = shub_get_single('shub_facebook_message','shub_facebook_message_id',$this->shub_facebook_message_id);
	        foreach($this->details as $key=>$val){
		        $this->details[$key] = $data && isset($data[$key]) ? $data[$key] : $val;
	        }
	        if(!is_array($this->details) || !isset($this->details['shub_facebook_message_id']) || $this->details['shub_facebook_message_id'] != $this->shub_facebook_message_id){
		        $this->reset();
		        return false;
	        }
        }
        foreach($this->details as $key=>$val){
            $this->{$key} = $val;
        }
	    if(!$this->facebook_account && $this->get('shub_facebook_id')){
		    $this->facebook_account = new shub_facebook_account($this->get('shub_facebook_id'));
	    }
	    if(!$this->facebook_page_or_group) {
		    if($this->get('shub_facebook_page_id')){
			    $this->facebook_page_or_group = new shub_facebook_page($this->facebook_account, $this->get('shub_facebook_page_id'));
		    }else if($this->get('shub_facebook_group_id')){
			    $this->facebook_page_or_group = new shub_facebook_group($this->facebook_account, $this->get('shub_facebook_group_id'));
		    }
	    }
        return $this->shub_facebook_message_id;
    }

	public function get($field){
		return isset($this->{$field}) ? $this->{$field} : false;
	}


    public function update($field,$value){
	    // what fields to we allow? or not allow?
	    if(in_array($field,array('shub_facebook_message_id')))return;
        if($this->shub_facebook_message_id){
            $this->{$field} = $value;
            shub_update_insert('shub_facebook_message_id',$this->shub_facebook_message_id,'shub_facebook_message',array(
	            $field => $value,
            ));
		    // special processing for certain fields.
		    if($field == 'comments'){
			    // we push all thsee comments into a shub_facebook_message_comment database table
			    // this is so we can do quick lookups on comment ids so we dont import duplicate items from graph (ie: a reply on a comment comes in as a separate item sometimes)
			    $data = @json_decode($value,true);
			    if($data && isset($data['data'])) {
				    // clear previous comment history.
				    $existing_comments = shub_get_multiple('shub_facebook_message_comment',array('shub_facebook_message_id'=>$this->shub_facebook_message_id),'shub_facebook_message_comment_id');
				    //shub_delete_from_db('shub_facebook_message_comment','shub_facebook_message_id',$this->shub_facebook_message_id);
				    $remaining_comments = $this->_update_comments( $data , $existing_comments);
				    // $remaining_comments contains any comments that no longer exist...
				    // todo: remove these? yer prolly. do a quick test on removing a comment - i think the only thing is it will show the 'from' name still.
			    }
		    }
        }
    }

	public function parse_links($content = false){
		if(!$this->get('shub_facebook_message_id'))return;
		// strip out any links in the tweet and write them to the facebook_message_link table.
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
					$shub_facebook_message_link_id = shub_update_insert( 'shub_facebook_message_link_id', false, 'shub_facebook_message_link', array(
						'shub_facebook_message_id' => $this->get('shub_facebook_message_id'),
						'link' => $url,
					) );
					if($shub_facebook_message_link_id) {
						$new_link = trailingslashit( get_site_url() );
						$new_link .= strpos( $new_link, '?' ) === false ? '?' : '&';
						$new_link .= _support_hub_FACEBOOK_LINK_REWRITE_PREFIX . '=' . $shub_facebook_message_link_id;
						// basic hash to stop brute force.
						if(defined('AUTH_KEY')){
							$new_link .= ':'.substr(md5(AUTH_KEY.' facebook link '.$shub_facebook_message_link_id),1,5);
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

	private function _update_comments($data, $existing_comments){
	    if($data && isset($data['data']) && is_array($data['data'])){
		    foreach($data['data'] as $comment){
			    if($comment['id']){
				    // does this id exist in the db already?
				    $exists = shub_get_single('shub_facebook_message_comment',array('facebook_id','shub_facebook_message_id'),array($comment['id'],$this->shub_facebook_message_id));

				    $shub_facebook_message_comment_id = shub_update_insert('shub_facebook_message_comment_id',$exists ? $exists['shub_facebook_message_comment_id'] : false,'shub_facebook_message_comment',array(
					    'shub_facebook_message_id' => $this->shub_facebook_message_id,
					    'facebook_id' => $comment['id'],
					    'time' => isset($comment['updated_time']) ? strtotime($comment['updated_time']) : (isset($comment['created_time']) ? strtotime($comment['created_time']) : 0),
					    'data' => json_encode($comment),
					    'message_from' => isset($comment['from']) ? json_encode($comment['from']) : '',
					    'message_to' => isset($comment['to']) ? json_encode($comment['to']) : '',
				    ));
				    if(isset($existing_comments[$shub_facebook_message_comment_id])){
					    unset($existing_comments[$shub_facebook_message_comment_id]);
				    }
				    if(isset($comment['comments']) && is_array($comment['comments'])){
					    $existing_comments = $this->_update_comments($comment['comments'], $existing_comments);
				    }
			    }
		    }
	    }
		return $existing_comments;
	}

	public function delete(){
		if($this->shub_facebook_message_id) {
			shub_delete_from_db( 'shub_facebook_message', 'shub_facebook_message_id', $this->shub_facebook_message_id );
		}
	}


	public function mark_as_read(){
		if($this->shub_facebook_message_id && get_current_user_id()){
			$sql = "REPLACE INTO `"._support_hub_DB_PREFIX."shub_facebook_message_read` SET `shub_facebook_message_id` = ".(int)$this->shub_facebook_message_id.", `user_id` = ".(int)get_current_user_id().", read_time = ".(int)time();
			shub_query($sql);
		}
	}

	public function get_summary() {
		// who was the last person to contribute to this post? show their details here instead of the 'summary' box maybe?
		$summary = $this->get( 'summary' );
	    if(empty($summary))$summary = _l('N/A');
	    return htmlspecialchars( strlen( $summary ) > 80 ? substr( $summary, 0, 80 ) . '...' : $summary );
	}

	private $can_reply = false;
	private function _output_block($facebook_data,$level){
		if(!isset($facebook_data['picture']) && isset($facebook_data['attachment'],$facebook_data['attachment']['type'],$facebook_data['attachment']['media']['image']['src'])){
			$facebook_data['picture'] = $facebook_data['attachment']['media']['image']['src'];
			$facebook_data['link'] = isset($facebook_data['attachment']['url']) ? $facebook_data['attachment']['url'] : false;
		}
		if(isset($facebook_data['comments'])) {
			$comments = $this->get_comments( $facebook_data['comments'] );
		}else{
			$comments = array();
		}
		//echo '<pre>';print_r($facebook_data);echo '</pre>';
		if($facebook_data['message'] !== false){
		?>
		<div class="facebook_comment">
			<div class="facebook_comment_picture">
				<?php if(isset($facebook_data['from']['id'])){ ?>
				<img src="//graph.facebook.com/<?php echo $facebook_data['from']['id'];?>/picture">
				<?php } ?>
			</div>
			<div class="facebook_comment_header">
				<?php echo isset($facebook_data,$facebook_data['from']) ? shub_facebook::format_person($facebook_data['from']) : 'N/A'; ?>
				<span><?php $time = strtotime(isset($facebook_data['updated_time']) ? $facebook_data['updated_time'] : (isset($facebook_data['created_time']) ? $facebook_data['created_time'] : false));
				echo $time ? ' @ ' . shub_print_date($time,true) : '';

				// todo - better this! don't call on every comment, load list in main loop and pass through all results.
				if ( isset( $facebook_data['user_id'] ) && $facebook_data['user_id'] ) {
					$user_info = get_userdata($facebook_data['user_id']);
					echo ' (sent by ' . htmlspecialchars($user_info->display_name) . ')';
				}else if(isset($facebook_data['id']) && $facebook_data['id']) {
					$exists = shub_get_single( 'shub_facebook_message_comment', array( 'facebook_id', 'shub_facebook_message_id' ), array( $facebook_data['id'], $this->shub_facebook_message_id ) );
					if ( $exists && isset( $exists['user_id'] ) && $exists['user_id'] ) {
						$user_info = get_userdata($exists['user_id']);
						echo ' (sent by ' . htmlspecialchars($user_info->display_name) . ')';
					}
				}
				?>
				</span>
			</div>
			<div class="facebook_comment_body">
				<?php if(isset($facebook_data['picture']) && $facebook_data['picture']){ ?>
				<div class="facebook_picture">
					<?php if(isset($facebook_data['link']) && $facebook_data['link']){ ?> <a href="<?php echo htmlspecialchars($facebook_data['link']);?>" target="_blank"> <?php } ?>
					<img src="<?php echo htmlspecialchars($facebook_data['picture']);?>">
					<?php if(isset($facebook_data['link']) && $facebook_data['link']){ ?> </a> <?php } ?>
				</div>
				<?php } ?>
				<div>
					<?php echo shub_forum_text($facebook_data['message']);?>
				</div>
			</div>
			<div class="facebook_comment_actions">
				<?php if($this->can_reply && (($this->get('type') != 'conversation' && !$this->get('shub_facebook_group_id') && $level == 2))){ ?>
					<a href="#" class="facebook_reply_button"><?php _e('Reply');?></a>
				<?php } ?>
			</div>
		</div>
		<?php } ?>
		<div class="facebook_comment_replies">
		<?php
		//if(strpos($facebook_data['message'],'picture')){
			//echo '<pre>'; print_r($facebook_data); echo '</pre>';
		//}
		if(count($comments)){
			// recursively print out our comments!
			//$comments = array_reverse($comments);
			foreach($comments as $comment){
				$this->_output_block($comment,$level+1);
			}
		}
		if($this->can_reply && isset($facebook_data['id']) && $facebook_data['id'] && (($this->get('type') == 'conversation' && $level == 1) || ($this->get('type') != 'conversation' && $level <= 2))){
			$this->reply_box($facebook_data['id'],$level);
		}
		?>
		</div>
		<?php
	}

	public function full_message_output($can_reply = false){
		$this->can_reply = $can_reply;
		// used in shub_facebook_list.php to display the full message and its comments
		switch($this->get('type')){
			case 'conversation':
				$facebook_data['id'] = $this->get('facebook_id');
				$facebook_data['message'] = false;
				$facebook_data['comments'] = array_reverse($this->get_comments());
				$this->_output_block($facebook_data,1);
				break;
			default:
				$facebook_data = @json_decode($this->get('data'),true);
				$facebook_data['message'] = $this->get('summary');
				$facebook_data['user_id'] = $this->get('user_id');
				$facebook_data['comments'] = array_reverse($this->get_comments());
				//echo '<pre>'; print_r($facebook_data['comments']); echo '</pre>';
				$this->_output_block($facebook_data,1);

				break;
		}
	}

	public function reply_box($facebook_id,$level=1){
		if($this->facebook_account && $this->shub_facebook_message_id) {
			?>
			<div class="facebook_comment facebook_comment_reply_box facebook_comment_reply_box_level<?php echo $level;?>">
				<div class="facebook_comment_picture">
					<?php
					if($this->facebook_page_or_group && $this->facebook_page_or_group->get('page_id')){
						?> <img src="//graph.facebook.com/<?php echo $this->facebook_page_or_group->get('page_id'); ?>/picture"> <?php
					}else{
						$me = @json_decode($this->facebook_account->get('facebook_data'),true);
						?> <img src="//graph.facebook.com/<?php echo $me['me']['id']; ?>/picture"> <?php
					}
					?>
				</div>
				<div class="facebook_comment_header">
					<?php
					if($this->facebook_page_or_group && $this->facebook_page_or_group->get('page_id')){
						echo shub_facebook::format_person( array('id'=>$this->facebook_page_or_group->get('page_id'), 'name'=>$this->facebook_page_or_group->get('page_name')) );
					}else{
						$me = @json_decode($this->facebook_account->get('facebook_data'),true);
						echo shub_facebook::format_person($me['me']);
					}
					?>
				</div>
				<div class="facebook_comment_reply">
					<textarea placeholder="Write a reply..."></textarea>
					<button data-facebook-id="<?php echo htmlspecialchars($facebook_id);?>" data-id="<?php echo (int)$this->shub_facebook_message_id;?>"><?php _e('Send');?></button>
					<br/>
					(debug) <input type="checkbox" name="debug" class="reply-debug" value="1">
				</div>
				<div class="facebook_comment_actions"></div>
			</div>
		<?php
		}else{
			?>
			<div class="facebook_comment facebook_comment_reply_box">
				(incorrect settings, please report this bug)
			</div>
			<?php
		}
	}

	public function get_link() {
		// todo: doesn't work on image uploads by admin
		return '//facebook.com/'.htmlspecialchars($this->get('facebook_id'));
	}

	private $attachment_name = '';
	public function add_attachment($local_filename){
		if(is_file($local_filename)){
			$this->attachment_name = $local_filename;
		}
	}
	public function send_queued($debug = false){
		if($this->facebook_account && $this->shub_facebook_message_id) {
			// send this message out to facebook.
			// this is run when user is composing a new message from the UI,
			if ( $this->get( 'status' ) == _shub_MESSAGE_STATUS_SENDING )
				return; // dont double up on cron.
			$this->update( 'status', _shub_MESSAGE_STATUS_SENDING );

			$access_token = $this->facebook_page_or_group && $this->facebook_page_or_group->get('facebook_token') ? $this->facebook_page_or_group->get('facebook_token') :$this->facebook_account->get('facebook_token');
			// get the machine id from the parent facebook_account
			$machine_id = $this->facebook_account->get('machine_id');

			$user_post_data = @json_decode($this->get('data'),true);

			if(!$this->facebook_page_or_group){
				// it's a personal message.

				if ( $debug ) {
					echo "Sending a new message to the personal facebook feed:<br>\n";
				}
				$result    = false;
				$facebook  = new shub_facebook();
				$post_data = array(
					'access_token' => $access_token,
					'machine_id'   => $machine_id,
				);

				// todo: message or link are required.
				$message = $this->get( 'summary' );
				if ( ! empty( $message ) ) {
					$post_data['message'] = $message;
				}
				$now       = time();
				$send_time = $this->get( 'last_active' );

				if ( isset( $user_post_data['facebook_post_type'] ) && $user_post_data['facebook_post_type'] == 'picture' && ! empty( $this->attachment_name ) && is_file( $this->attachment_name ) ) {
					// we're posting a photo! change the post source from /feed to /photos

					//$post_data['source'] = new CURLFile($this->attachment_name, 'image/jpg'); //'@'.$this->attachment_name;
					$post_data['source'] = '@' . $this->attachment_name;
					/*if($send_time && $send_time > $now) {
						// schedule in the future / image posts dont support backdating.
						$post_data['scheduled_publish_time'] = $send_time; //date('c',$send_time);
						$post_data['published']              = 0;
					}*/

					$result = $facebook->graph_post( 'me/photos', $post_data );

				} else if ( isset( $user_post_data['facebook_post_type'] ) && $user_post_data['facebook_post_type'] == 'link' ) {
					// sending a normal wall post, support links.
					$link = $this->get( 'link' );
					if ( ! empty( $link ) ) {
						// do we format this link into something trackable?
						if ( isset( $user_post_data['track_links'] ) && $user_post_data['track_links'] ) {
							$link = $this->parse_links( $link );
						}
						$post_data['link'] = $link;
						if ( isset( $user_post_data['link_picture'] ) && ! empty( $user_post_data['link_picture'] ) ) {
							$post_data['picture'] = $user_post_data['link_picture'];
						}
						if ( isset( $user_post_data['link_name'] ) && ! empty( $user_post_data['link_name'] ) ) {
							$post_data['name'] = $user_post_data['link_name'];
						}
						if ( isset( $user_post_data['link_caption'] ) && ! empty( $user_post_data['link_caption'] ) ) {
							$post_data['caption'] = $user_post_data['link_caption'];
						}
						if ( isset( $user_post_data['link_description'] ) && ! empty( $user_post_data['link_description'] ) ) {
							$post_data['description'] = $user_post_data['link_description'];
						}
					}
					/*if($send_time && $send_time > $now){
						// schedule in the future.
						$post_data['scheduled_publish_time'] = $send_time; //date('c',$send_time);
						$post_data['published'] = 0;
					}else if($send_time && $send_time < $now){
						$post_data['backdated_time'] = date('c',$send_time);
					}else{

					}*/
					$result = $facebook->graph_post( 'me/feed', $post_data );
				} else {
					// standard wall post, no link or picture..
					/*if($send_time && $send_time > $now){
						// schedule in the future.
						$post_data['scheduled_publish_time'] = $send_time; //date('c',$send_time);
						$post_data['published'] = 0;
					}else if($send_time && $send_time < $now){
						$post_data['backdated_time'] = date('c',$send_time);
					}else{

					}*/
					$result = $facebook->graph_post( 'me/feed', $post_data );
				}
				if ( $debug ) {
					echo "Graph Post Result: <br>\n" . var_export( $result, true ) . " <br>\n";
				}
				if ( $result && isset( $result['id'] ) ) {
					$this->update( 'facebook_id', $result['id'] );
					// reload this message and comments from the graph api.
					$this->load_by_facebook_id( $this->get( 'facebook_id' ), false, $this->get( 'type' ), $debug );
				} else {
					echo 'Failed to send message. Error was: ' . var_export( $result, true );
					// remove from database.
					$this->delete();

					return false;
				}

			}else {
				$facebook_page_id = $this->facebook_page_or_group->get( 'page_id' );
				if ( ! $facebook_page_id ) {
					// must be a group.
					$facebook_group_id = $this->facebook_page_or_group->get( 'group_id' );

					if ( $debug ) {
						echo "Sending a new message to facebook group ID: $facebook_group_id <br>\n";
					}
					$result    = false;
					$facebook  = new shub_facebook();
					$post_data = array(
						'access_token' => $access_token,
						'machine_id'   => $machine_id,
					);

					// todo: message or link are required.
					$message = $this->get( 'summary' );
					if ( ! empty( $message ) ) {
						$post_data['message'] = $message;
					}
					$now       = time();
					$send_time = $this->get( 'last_active' );

					if ( isset( $user_post_data['facebook_post_type'] ) && $user_post_data['facebook_post_type'] == 'picture' && ! empty( $this->attachment_name ) && is_file( $this->attachment_name ) ) {
						// we're posting a photo! change the post source from /feed to /photos

						//$post_data['source'] = new CURLFile($this->attachment_name, 'image/jpg'); //'@'.$this->attachment_name;
						$post_data['source'] = '@' . $this->attachment_name;
						/*if($send_time && $send_time > $now) {
							// schedule in the future / image posts dont support backdating.
							$post_data['scheduled_publish_time'] = $send_time; //date('c',$send_time);
							$post_data['published']              = 0;
						}*/

						$result = $facebook->graph_post( '' . $facebook_group_id . '/photos', $post_data );

					} else if ( isset( $user_post_data['facebook_post_type'] ) && $user_post_data['facebook_post_type'] == 'link' ) {
						// sending a normal wall post, support links.
						$link = $this->get( 'link' );
						if ( ! empty( $link ) ) {
							// do we format this link into something trackable?
							if ( isset( $user_post_data['track_links'] ) && $user_post_data['track_links'] ) {
								$link = $this->parse_links( $link );
							}
							$post_data['link'] = $link;
							if ( isset( $user_post_data['link_picture'] ) && ! empty( $user_post_data['link_picture'] ) ) {
								$post_data['picture'] = $user_post_data['link_picture'];
							}
							if ( isset( $user_post_data['link_name'] ) && ! empty( $user_post_data['link_name'] ) ) {
								$post_data['name'] = $user_post_data['link_name'];
							}
							if ( isset( $user_post_data['link_caption'] ) && ! empty( $user_post_data['link_caption'] ) ) {
								$post_data['caption'] = $user_post_data['link_caption'];
							}
							if ( isset( $user_post_data['link_description'] ) && ! empty( $user_post_data['link_description'] ) ) {
								$post_data['description'] = $user_post_data['link_description'];
							}
						}
						/*if($send_time && $send_time > $now){
							// schedule in the future.
							$post_data['scheduled_publish_time'] = $send_time; //date('c',$send_time);
							$post_data['published'] = 0;
						}else if($send_time && $send_time < $now){
							$post_data['backdated_time'] = date('c',$send_time);
						}else{

						}*/
						$result = $facebook->graph_post( '' . $facebook_group_id . '/feed', $post_data );
					} else {
						// standard wall post, no link or picture..
						/*if($send_time && $send_time > $now){
							// schedule in the future.
							$post_data['scheduled_publish_time'] = $send_time; //date('c',$send_time);
							$post_data['published'] = 0;
						}else if($send_time && $send_time < $now){
							$post_data['backdated_time'] = date('c',$send_time);
						}else{

						}*/
						$result = $facebook->graph_post( '' . $facebook_group_id . '/feed', $post_data );
					}
					if ( $debug ) {
						echo "Graph Post Result: <br>\n" . var_export( $result, true ) . " <br>\n";
					}
					if ( $result && isset( $result['id'] ) ) {
						$this->update( 'facebook_id', $result['id'] );
						// reload this message and comments from the graph api.
						$this->load_by_facebook_id( $this->get( 'facebook_id' ), false, $this->get( 'type' ), $debug );
					} else {
						echo 'Failed to send message. Error was: ' . var_export( $result, true );
						// remove from database.
						$this->delete();

						return false;
					}

				} else {

					if ( $debug ) {
						echo "Sending a new message to facebook Page ID: $facebook_page_id <br>\n";
					}
					$result    = false;
					$facebook  = new shub_facebook();
					$post_data = array(
						'access_token' => $access_token,
						'machine_id'   => $machine_id,
					);

					// todo: message or link are required.
					$message = $this->get( 'summary' );
					if ( ! empty( $message ) ) {
						$post_data['message'] = $message;
					}
					$now       = time();
					$send_time = $this->get( 'last_active' );

					if ( isset( $user_post_data['facebook_post_type'] ) && $user_post_data['facebook_post_type'] == 'picture' && ! empty( $this->attachment_name ) && is_file( $this->attachment_name ) ) {
						// we're posting a photo! change the post source from /feed to /photos

						//$post_data['source'] = new CURLFile($this->attachment_name, 'image/jpg'); //'@'.$this->attachment_name;
						$post_data['source'] = '@' . $this->attachment_name;
						/*if($send_time && $send_time > $now) {
							// schedule in the future / image posts dont support backdating.
							$post_data['scheduled_publish_time'] = $send_time; //date('c',$send_time);
							$post_data['published']              = 0;
						}*/

						$result = $facebook->graph_post( '' . $facebook_page_id . '/photos', $post_data );

					} else if ( isset( $user_post_data['facebook_post_type'] ) && $user_post_data['facebook_post_type'] == 'link' ) {
						// sending a normal wall post, support links.
						$link = $this->get( 'link' );
						if ( ! empty( $link ) ) {
							// do we format this link into something trackable?
							if ( isset( $user_post_data['track_links'] ) && $user_post_data['track_links'] ) {
								$link = $this->parse_links( $link );
							}
							$post_data['link'] = $link;
							if ( isset( $user_post_data['link_picture'] ) && ! empty( $user_post_data['link_picture'] ) ) {
								$post_data['picture'] = $user_post_data['link_picture'];
							}
							if ( isset( $user_post_data['link_name'] ) && ! empty( $user_post_data['link_name'] ) ) {
								$post_data['name'] = $user_post_data['link_name'];
							}
							if ( isset( $user_post_data['link_caption'] ) && ! empty( $user_post_data['link_caption'] ) ) {
								$post_data['caption'] = $user_post_data['link_caption'];
							}
							if ( isset( $user_post_data['link_description'] ) && ! empty( $user_post_data['link_description'] ) ) {
								$post_data['description'] = $user_post_data['link_description'];
							}
						}
						/*if($send_time && $send_time > $now){
							// schedule in the future.
							$post_data['scheduled_publish_time'] = $send_time; //date('c',$send_time);
							$post_data['published'] = 0;
						}else if($send_time && $send_time < $now){
							$post_data['backdated_time'] = date('c',$send_time);
						}else{

						}*/
						$result = $facebook->graph_post( '' . $facebook_page_id . '/feed', $post_data );
					} else {
						// standard wall post, no link or picture..
						/*if($send_time && $send_time > $now){
							// schedule in the future.
							$post_data['scheduled_publish_time'] = $send_time; //date('c',$send_time);
							$post_data['published'] = 0;
						}else if($send_time && $send_time < $now){
							$post_data['backdated_time'] = date('c',$send_time);
						}else{

						}*/
						$result = $facebook->graph_post( '' . $facebook_page_id . '/feed', $post_data );
					}
					if ( $debug ) {
						echo "Graph Post Result: <br>\n" . var_export( $result, true ) . " <br>\n";
					}
					if ( $result && isset( $result['id'] ) ) {
						$this->update( 'facebook_id', $result['id'] );
						// reload this message and comments from the graph api.
						$this->load_by_facebook_id( $this->get( 'facebook_id' ), false, $this->get( 'type' ), $debug );
					} else {
						echo 'Failed to send message. Error was: ' . var_export( $result, true );
						// remove from database.
						$this->delete();

						return false;
					}
				}
			}

			// successfully sent, mark is as answered.
			$this->update( 'status', _shub_MESSAGE_STATUS_ANSWERED );
			return true;
		}
		return false;
	}
	public function send_reply($facebook_id, $message, $debug = false){
		if($this->facebook_account && $this->shub_facebook_message_id) {
			$access_token = $this->facebook_page_or_group && $this->facebook_page_or_group->get('facebook_token') ? $this->facebook_page_or_group->get('facebook_token') :$this->facebook_account->get('facebook_token');
			// get the machine id from the parent facebook_account
			$machine_id = $this->facebook_account->get('machine_id');

			if(!$facebook_id)$facebook_id = $this->get('facebook_id');

			if($debug)echo "Sending a reply to facebook ID: $facebook_id <br>\n";
			if($debug)echo "Type: ".$this->get('type')." <br>\n";
			$result = false;
			switch($this->get('type')) {
				case 'conversation':
					// do we reply to the previous comment?
					// nah for now we just add it to the bottom of the list.
					$facebook = new shub_facebook();
					$result = $facebook->graph_post(''.$facebook_id.'/messages',array(
						'message' => $message,
						'access_token' => $access_token,
						'machine_id' => $machine_id,
					));
					if($debug)echo "Graph Post Result: <br>\n".var_export($result,true)." <br>\n";
					// reload this message and comments from the graph api.
					$this->load_by_facebook_id($this->get('facebook_id'),false,$this->get('type'),$debug);
					break;
					break;
				default:
					// do we reply to the previous comment?
					// nah for now we just add it to the bottom of the list.
					$facebook = new shub_facebook();
					$result = $facebook->graph_post(''.$facebook_id.'/comments',array(
						'message' => $message,
						'access_token' => $access_token,
						'machine_id' => $machine_id,
					));
					if($debug)echo "Graph Post Result: <br>\n".var_export($result,true)." <br>\n";
					// reload this message and comments from the graph api.
					$this->load_by_facebook_id($this->get('facebook_id'),false,$this->get('type'),$debug);
					break;
			}
			// hack to add the 'user_id' of who created this reply to the db for logging.
			if($result && isset($result['id'])){
				// find this comment id in our facebook comment database.
				$exists = shub_get_single('shub_facebook_message_comment',array('facebook_id','shub_facebook_message_id'),array($result['id'],$this->shub_facebook_message_id));
				if($exists && $exists['shub_facebook_message_comment_id']){
					// it really should exist after we've done the 'load_by_facebook_id' above. however with a post with lots of comments it may not appear without graph api pagination.
					// todo - pagination!
					shub_update_insert('shub_facebook_message_comment_id',$exists['shub_facebook_message_comment_id'],'shub_facebook_message_comment',array(
						'user_id' => get_current_user_id(),
					));
				}
			}

		}
	}
	public function get_comments($comment_data = false) {
		$data = $comment_data ? $comment_data  : @json_decode($this->get('comments'),true);
		$comments = array();
		if($data && isset($data['data'])){
			$comments = $data['data'];
			// format them up nicely.
		}else{
			$comments = $data;
		}
		return $comments;
	}

	public function get_type_pretty() {
		$type = $this->get('type');
		switch($type){
			case 'conversation':
				return 'Inbox Message';
				break;
			case 'status':
				return 'Wall Post';
				break;
			default:
				return ucwords($type);
		}
	}

	public function get_from() {
		if($this->shub_facebook_message_id){
			$from = array();
			$data = @json_decode($this->get('data'),true);
			if(isset($data['from']['id'])){
				$from[$data['from']['id']] = $data['from']['name'];
			}
			if(isset($data['senders']['data']) && is_array($data['senders']['data'])){
				foreach($data['senders']['data'] as $sender){
					$from[$sender['id']] = $sender['name'];
				}
			}

			$messages = shub_get_multiple('shub_facebook_message_comment',array('shub_facebook_message_id'=>$this->shub_facebook_message_id),'shub_facebook_message_comment_id');
			foreach($messages as $message){
				if($message['message_from']){
					$data = @json_decode($message['message_from'],true);
					if(isset($data['id'])){
						$from[$data['id']] = $data['name'];
					}
				}
			}
			return $from;
		}
		return array();
	}


	public function link_open(){
		return 'admin.php?page=support_hub_main&shub_facebook_id='.$this->facebook_account->get('shub_facebook_id').'&shub_facebook_message_id='.$this->shub_facebook_message_id;
	}


}