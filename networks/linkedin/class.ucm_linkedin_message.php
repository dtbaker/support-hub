<?php

class ucm_linkedin_message{

	public function __construct($linkedin_account = false, $linkedin_group = false, $social_linkedin_message_id = false){
		$this->linkedin_account = $linkedin_account;
		$this->linkedin_group = $linkedin_group;
		$this->load($social_linkedin_message_id);
	}

	/* @var $linkedin_group ucm_linkedin_group */
	private $linkedin_group= false;
	/* @var $linkedin_account ucm_linkedin_account */
	private $linkedin_account = false;
	private $social_linkedin_message_id = false; // the current user id in our system.
    private $details = array();

	private function reset(){
		$this->social_linkedin_message_id = false;
		$this->details = array(
			'social_linkedin_message_id' => '',
			'marketing_message_id' => '',
			'social_linkedin_group_id' => '',
			'social_linkedin_id' => '',
			'linkedin_id' => '',
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
		$this->social_linkedin_message_id = ucm_update_insert('social_linkedin_message_id',false,'social_linkedin_message',array());
		$this->load($this->social_linkedin_message_id);
	}

	public function load_by_linkedin_id($linkedin_id, $message_data, $type, $debug = false, $refresh_from_api = false){

		switch($type){
			case 'share':

				if($debug){
					echo "Processing share $linkedin_id <br>\n";
				}
				$existing = ucm_get_single('social_linkedin_message', 'linkedin_id', $linkedin_id);
				if($existing && !$refresh_from_api){
					// load it up.
					$this->load($existing['social_linkedin_message_id']);
					// check if comments have changed from original.
					if(isset($message_data['updateComments']['_total']) && $message_data['updateComments']['_total'] > 0){
						// api has comments, check if matches.
						$existing_comments = @json_decode($this->get('comments'),true);
						if(!is_array($existing_comments) || count($existing_comments) != $message_data['updateComments']['_total']){
							// comment count mismatches, continue below to update it via the API

						}else{
							// matches, we're up to date.
							return true;
						}
					}else{
						return true;  // already exists in database and not updating
					}
				}

				$api        = $this->linkedin_account->get_api();
				if(!$message_data) {
					$api_result = $api->api( 'v1/people/~/network/updates/key=' . $linkedin_id, array() );
					if ( $debug ) {
						echo "API Result for share: ";
						print_r( $api_result );
					}
					$message_data = $api_result;
				}
				if(!$message_data || !isset($message_data['updateContent']['person']['currentShare']) || (isset($message_data['updateContent']['person']['firstName']) && $message_data['updateContent']['person']['firstName'] == 'private')){
					$this->delete();
					return false;
				}

				if(isset($message_data['updateKey']) && $message_data['updateKey'] == $linkedin_id){
					if(!$existing){
						$this->create_new();
					}else{
						$this->load($existing['social_linkedin_message_id']);
						if($debug) {
							echo "Updating existing message from api: ";
						}
					}
					$this->update('social_linkedin_id',$this->linkedin_account->get('social_linkedin_id'));
					$this->update('social_linkedin_group_id',0);
					$this->update('summary',$message_data['updateContent']['person']['currentShare']['comment'] ?: '');
					$this->update('title',isset($message_data['updateContent']['person']['currentShare']['content']['description']) ? $message_data['updateContent']['person']['currentShare']['content']['description'] : '');
					$this->update('last_active',round($message_data['timestamp']/1000));
					$this->update('type',$type);
					$this->update('data',json_encode($message_data));
					$this->update('link',isset($message_data['updateContent']['person']['currentShare']['content']['submittedUrl']) ? $message_data['updateContent']['person']['currentShare']['content']['submittedUrl'] : '');
					$this->update('linkedin_id', $message_data['updateKey']);
					$this->update('status',_SOCIAL_MESSAGE_STATUS_UNANSWERED);
					// find any comments.
					$max_per_api_call = 20;
					$api_result = $api->api('v1/people/~/network/updates/key='.$linkedin_id.'/update-comments' , array(
						'start' => 0,
						'count' => $max_per_api_call,
					));
					if($debug){
						echo "API Result for post comments: ";
						print_r($api_result);
					}
					if($api_result && isset($api_result['values']) && is_array($api_result['values'])){
						// do pagination
						if(isset($api_result['_count']) && $api_result['_total'] && $api_result['_count'] < $api_result['_total']){
							while(count($api_result['values']) < $api_result['_total']){
								$paged_api_result = $api->api('v1/people/~/network/updates/key='.$linkedin_id.'/update-comments' , array(
									'start' => count($api_result['values']),
									'count' => $max_per_api_call,
								));
								if($debug){
									echo "Query for ".$max_per_api_call." comments starting at ".count($api_result['values']).":";
									print_r($paged_api_result);
								}
								if($paged_api_result && isset($paged_api_result['values']) && is_array($paged_api_result['values'])){
									$api_result['values'] = array_merge($paged_api_result['values'],$api_result['values']);
								}else{
									break;
								}
							}
						}
						if($debug){
							echo "Final comments ".(count($api_result['values'])).":";
							print_r($api_result['values']);
						}
						$max_comment_time = 0;
						foreach($api_result['values'] as $comment){
							$comment_time = round($comment['timestamp']/1000);
							$max_comment_time = max($comment_time,$max_comment_time);
						}
						$this->update('last_active',$max_comment_time);
						$this->update('comments',json_encode($api_result['values']));
					}
				}

				return $existing;

				break;
			case 'group_post':

				$existing = ucm_get_single('social_linkedin_message', 'linkedin_id', $linkedin_id);
				if($existing && !$refresh_from_api){
					// load it up.
					$this->load($existing['social_linkedin_message_id']);
					return true; // already exists in database.
				}else{

					$api = $this->linkedin_account->get_api();
					//$linkedin_id = 'g-8283043-S-5987203693211041793';
					$api_result = $api->api('v1/posts/'.$linkedin_id , array(
					));
					if($debug){
						echo "API Result for post: ";
						print_r($api_result);
					}
					if(isset($api_result['id']) && $api_result['id'] == $linkedin_id){
						if(!$existing){
							$this->create_new();
						}else{
							$this->load($existing['social_linkedin_message_id']);
							if($debug) {
								echo "Updating existing message from api: ";
							}
						}
						$this->update('social_linkedin_id',$this->linkedin_account->get('social_linkedin_id'));
						$this->update('social_linkedin_group_id',$this->linkedin_group->get('social_linkedin_group_id'));
						$this->update('summary',$api_result['title']); // todo, figure out where teh summary is available in the API?
						$this->update('title',$api_result['title']);
						$this->update('last_active',time());
						$this->update('type',$type);
						$this->update('data',json_encode($api_result));
						if(preg_match('#g-(\d+)-S-(\d+)#',$linkedin_id,$matches)){
							$this->update('link','http://www.linkedin.com/groupItem?view=&gid='.$matches[1].'&type=member&item='.$matches[2]);
						}
						$this->update('linkedin_id', $api_result['id']);
						$this->update('status',_SOCIAL_MESSAGE_STATUS_UNANSWERED);
						// find any comments.
						$max_per_api_call = 20;
						$api_result = $api->api('v1/posts/'.$linkedin_id.'/comments' , array(
							'start' => 0,
							'count' => $max_per_api_call,
						));
						if($debug){
							echo "API Result for post comments: ";
							print_r($api_result);
						}
						if($api_result && isset($api_result['values']) && is_array($api_result['values'])){
							// do pagination
							if(isset($api_result['_count']) && $api_result['_total'] && $api_result['_count'] < $api_result['_total']){
								while(count($api_result['values']) < $api_result['_total']){
									$paged_api_result = $api->api('v1/posts/'.$linkedin_id.'/comments' , array(
										'start' => count($api_result['values']),
										'count' => $max_per_api_call,
									));
									if($debug){
										echo "Query for ".$max_per_api_call." comments starting at ".count($api_result['values']).":";
										print_r($paged_api_result);
									}
									if($paged_api_result && isset($paged_api_result['values']) && is_array($paged_api_result['values'])){
										$api_result['values'] = array_merge($paged_api_result['values'],$api_result['values']);
									}else{
										break;
									}
								}
							}
							if($debug){
								echo "Final comments ".(count($api_result['values'])).":";
								print_r($api_result['values']);
							}
							$max_comment_time = 0;
							foreach($api_result['values'] as $comment){
								$comment_time = round($comment['creationTimestamp']/1000);
								$max_comment_time = max($comment_time,$max_comment_time);
							}
							$this->update('last_active',$max_comment_time);
							$this->update('comments',json_encode($api_result['values']));
						}


					}
					return $existing; // doesn't exist in our database yet, adding it new.
				}
				break;

		}

	}

    public function load($social_linkedin_message_id = false){
	    if(!$social_linkedin_message_id)$social_linkedin_message_id = $this->social_linkedin_message_id;
	    $this->reset();
	    $this->social_linkedin_message_id = $social_linkedin_message_id;
        if($this->social_linkedin_message_id){
	        $data = ucm_get_single('social_linkedin_message','social_linkedin_message_id',$this->social_linkedin_message_id);
	        foreach($this->details as $key=>$val){
		        $this->details[$key] = $data && isset($data[$key]) ? $data[$key] : $val;
	        }
	        if(!is_array($this->details) || !isset($this->details['social_linkedin_message_id']) || $this->details['social_linkedin_message_id'] != $this->social_linkedin_message_id){
		        $this->reset();
		        return false;
	        }
        }
        foreach($this->details as $key=>$val){
            $this->{$key} = $val;
        }
	    if(!$this->linkedin_account && $this->get('social_linkedin_id')){
		    $this->linkedin_account = new ucm_linkedin_account($this->get('social_linkedin_id'));
	    }
	    if(!$this->linkedin_group && $this->get('social_linkedin_group_id')) {
		    $this->linkedin_group = new ucm_linkedin_group($this->linkedin_account, $this->get('social_linkedin_group_id'));
	    }
        return $this->social_linkedin_message_id;
    }

	public function get($field){
		return isset($this->{$field}) ? $this->{$field} : false;
	}


    public function update($field,$value){
	    // what fields to we allow? or not allow?
	    if(in_array($field,array('social_linkedin_message_id')))return;
        if($this->social_linkedin_message_id){
            $this->{$field} = $value;
            ucm_update_insert('social_linkedin_message_id',$this->social_linkedin_message_id,'social_linkedin_message',array(
	            $field => $value,
            ));
		    // special processing for certain fields.
		    if($field == 'comments'){
			    // we push all thsee comments into a social_linkedin_message_comment database table
			    // this is so we can do quick lookups on comment ids so we dont import duplicate items from graph (ie: a reply on a comment comes in as a separate item sometimes)
			    $data = @json_decode($value,true);
			    if(is_array($data)) {
				    // clear previous comment history.
				    $existing_comments = ucm_get_multiple('social_linkedin_message_comment',array('social_linkedin_message_id'=>$this->social_linkedin_message_id),'social_linkedin_message_comment_id');
				    //ucm_delete_from_db('social_linkedin_message_comment','social_linkedin_message_id',$this->social_linkedin_message_id);
				    $remaining_comments = $this->_update_comments( $data , $existing_comments);
				    // $remaining_comments contains any comments that no longer exist...
				    // todo: remove these? yer prolly. do a quick test on removing a comment - i think the only thing is it will show the 'from' name still.
			    }
		    }
        }
    }

	public function parse_links($content = false){
		if(!$this->get('social_linkedin_message_id'))return;
		// strip out any links in the tweet and write them to the linkedin_message_link table.
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
					$social_linkedin_message_link_id = ucm_update_insert( 'social_linkedin_message_link_id', false, 'social_linkedin_message_link', array(
						'social_linkedin_message_id' => $this->get('social_linkedin_message_id'),
						'link' => $url,
					) );
					if($social_linkedin_message_link_id) {
						$new_link = trailingslashit( get_site_url() );
						$new_link .= strpos( $new_link, '?' ) === false ? '?' : '&';
						$new_link .= _support_hub_LINKEDIN_LINK_REWRITE_PREFIX . '=' . $social_linkedin_message_link_id;
						// basic hash to stop brute force.
						if(defined('AUTH_KEY')){
							$new_link .= ':'.substr(md5(AUTH_KEY.' linkedin link '.$social_linkedin_message_link_id),1,5);
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
	    if(is_array($data)){
		    foreach($data as $comment){
			    if($comment['id']){
				    // does this id exist in the db already?
				    $exists = ucm_get_single('social_linkedin_message_comment',array('linkedin_id','social_linkedin_message_id'),array($comment['id'],$this->social_linkedin_message_id));

				    $social_linkedin_message_comment_id = ucm_update_insert('social_linkedin_message_comment_id',$exists ? $exists['social_linkedin_message_comment_id'] : false,'social_linkedin_message_comment',array(
					    'social_linkedin_message_id' => $this->social_linkedin_message_id,
					    'linkedin_id' => $comment['id'],
					    'time' => isset($comment['timestamp']) ? round($comment['timestamp']/1000) : (isset($comment['creationTimestamp']) ? round($comment['creationTimestamp']/1000) :0),
					    'data' => json_encode($comment),
					    'message_from' => isset($comment['creator']) ? json_encode($comment['creator']) : (isset($comment['person']) ? json_encode($comment['person']) : ''),
					    'message_to' => '',
					    'comment_text' => isset($comment['text']) ? $comment['text'] : (isset($comment['comment']) ? $comment['comment'] : ''),
				    ));
				    if(isset($existing_comments[$social_linkedin_message_comment_id])){
					    unset($existing_comments[$social_linkedin_message_comment_id]);
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
		if($this->social_linkedin_message_id) {
			ucm_delete_from_db( 'social_linkedin_message', 'social_linkedin_message_id', $this->social_linkedin_message_id );
		}
	}


	public function mark_as_read(){
		if($this->social_linkedin_message_id && get_current_user_id()){
			$sql = "REPLACE INTO `"._support_hub_DB_PREFIX."social_linkedin_message_read` SET `social_linkedin_message_id` = ".(int)$this->social_linkedin_message_id.", `user_id` = ".(int)get_current_user_id().", read_time = ".(int)time();
			ucm_query($sql);
		}
	}

	public function get_summary() {
		// who was the last person to contribute to this post? show their details here instead of the 'summary' box maybe?
		$summary = $this->get( 'summary' );
	    if(empty($summary))$summary = __('N/A');
	    return htmlspecialchars( strlen( $summary ) > 80 ? substr( $summary, 0, 80 ) . '...' : $summary );
	}

	private $can_reply = false;
	private function _output_block($linkedin_data,$level){
		if(!isset($linkedin_data['picture']) && isset($linkedin_data['attachment'],$linkedin_data['attachment']['type'],$linkedin_data['attachment']['media']['image']['src'])){
			$linkedin_data['picture'] = $linkedin_data['attachment']['media']['image']['src'];
			$linkedin_data['link'] = isset($linkedin_data['attachment']['url']) ? $linkedin_data['attachment']['url'] : false;
		}
		if(isset($linkedin_data['comments'])) {
			$comments = $this->get_comments( $linkedin_data['comments'] );
		}else{
			$comments = array();
		}
//		echo '<pre>';print_r($comments);echo '</pre>';
//		echo '<pre>';print_r($linkedin_data);echo '</pre>';
		if((isset($linkedin_data['comment']) && $linkedin_data['comment'] !== false)){
			?>
			<div class="linkedin_comment">
				<div class="linkedin_comment_picture">
					<?php if(isset($linkedin_data['person']['pictureUrl'])){ ?>
					<img src="<?php echo $linkedin_data['person']['pictureUrl'];?>">
					<?php } ?>
				</div>
				<div class="linkedin_comment_header">
					<?php echo isset($linkedin_data,$linkedin_data['person']) ? ucm_linkedin::format_person($linkedin_data['person'], $this->linkedin_account) : 'N/A'; ?>
					<span><?php $time = isset($linkedin_data['timestamp']) ? round($linkedin_data['timestamp']/1000) : false;
					echo $time ? ' @ ' . ucm_print_date($time,true) : '';

					// todo - better this! don't call on every comment, load list in main loop and pass through all results.
					if ( isset( $linkedin_data['user_id'] ) && $linkedin_data['user_id'] ) {
						$user_info = get_userdata($linkedin_data['user_id']);
						echo ' (sent by ' . htmlspecialchars($user_info->display_name) . ')';
					}else if(isset($linkedin_data['id']) && $linkedin_data['id']) {
						$exists = ucm_get_single( 'social_linkedin_message_comment', array( 'linkedin_id', 'social_linkedin_message_id' ), array( $linkedin_data['id'], $this->social_linkedin_message_id ) );
						if ( $exists && isset( $exists['user_id'] ) && $exists['user_id'] ) {
							$user_info = get_userdata($exists['user_id']);
							echo ' (sent by ' . htmlspecialchars($user_info->display_name) . ')';
						}
					}
					?>
					</span>
				</div>
				<div class="linkedin_comment_body">
					<div>
						<?php echo ucm_forum_text($linkedin_data['comment']);?>
					</div>
				</div>
				<div class="linkedin_comment_actions">
				</div>
			</div>
			<?php
		}else if((isset($linkedin_data['message']) && $linkedin_data['message'] !== false) || isset($linkedin_data['text']) && $linkedin_data['text'] !== false){
		?>
		<div class="linkedin_comment">
			<div class="linkedin_comment_picture">
				<?php if(isset($linkedin_data['creator']['pictureUrl'])){ ?>
				<img src="<?php echo $linkedin_data['creator']['pictureUrl'];?>">
				<?php }else if(isset($linkedin_data['updateContent']['person']['pictureUrl'])){ ?>
				<img src="<?php echo $linkedin_data['updateContent']['person']['pictureUrl'];?>">
				<?php } ?>
			</div>
			<div class="linkedin_comment_header">
				<?php echo isset($linkedin_data['creator']) ? ucm_linkedin::format_person($linkedin_data['creator'], $this->linkedin_account) : (
					isset($linkedin_data['updateContent']['person']) ? ucm_linkedin::format_person($linkedin_data['updateContent']['person'], $this->linkedin_account) : 'N/A'
				); ?>
				<span><?php $time = isset($linkedin_data['creationTimestamp']) ? round($linkedin_data['creationTimestamp']/1000) : false;
				echo $time ? ' @ ' . ucm_print_date($time,true) : '';

				// todo - better this! don't call on every comment, load list in main loop and pass through all results.
				if ( isset( $linkedin_data['user_id'] ) && $linkedin_data['user_id'] ) {
					$user_info = get_userdata($linkedin_data['user_id']);
					echo ' (sent by ' . htmlspecialchars($user_info->display_name) . ')';
				}else if(isset($linkedin_data['id']) && $linkedin_data['id']) {
					$exists = ucm_get_single( 'social_linkedin_message_comment', array( 'linkedin_id', 'social_linkedin_message_id' ), array( $linkedin_data['id'], $this->social_linkedin_message_id ) );
					if ( $exists && isset( $exists['user_id'] ) && $exists['user_id'] ) {
						$user_info = get_userdata($exists['user_id']);
						echo ' (sent by ' . htmlspecialchars($user_info->display_name) . ')';
					}
				}
				?>
				</span>
			</div>
			<div class="linkedin_comment_body">
				<div>
					<?php echo ucm_forum_text(isset($linkedin_data['text']) ? $linkedin_data['text'] : (isset($linkedin_data['message']) ? $linkedin_data['message'] : 'Unknown Comment'));?>
				</div>
				<?php if(isset($linkedin_data['updateContent']['person']['currentShare']['content']['thumbnailUrl']) && $linkedin_data['updateContent']['person']['currentShare']['content']['thumbnailUrl']){ ?>
				<div class="linkedin_picture">
					<?php if(isset($linkedin_data['updateContent']['person']['currentShare']['content']['submittedUrl']) && $linkedin_data['updateContent']['person']['currentShare']['content']['submittedUrl']){ ?> <a href="<?php echo htmlspecialchars($linkedin_data['updateContent']['person']['currentShare']['content']['submittedUrl']);?>" target="_blank"> <?php } ?>
					<img src="<?php echo htmlspecialchars($linkedin_data['updateContent']['person']['currentShare']['content']['thumbnailUrl']);?>">
					<?php if(isset($linkedin_data['updateContent']['person']['currentShare']['content']['submittedUrl']) && $linkedin_data['updateContent']['person']['currentShare']['content']['submittedUrl']){ ?> </a> <?php } ?>
				</div>
				<?php } ?>
				<?php if(isset($linkedin_data['updateContent']['person']['currentShare']['content'])){ ?>
				<div>
					<strong><?php echo htmlspecialchars($linkedin_data['updateContent']['person']['currentShare']['content']['title']);?></strong> <br/>
					<?php echo htmlspecialchars($linkedin_data['updateContent']['person']['currentShare']['content']['description']);?> <br/>
					<a href="<?php echo htmlspecialchars($linkedin_data['updateContent']['person']['currentShare']['content']['submittedUrl']);?>" target="_blank"><?php echo htmlspecialchars($linkedin_data['updateContent']['person']['currentShare']['content']['submittedUrl']);?></a>
				</div>
				<?php } ?>
			</div>
			<div class="linkedin_comment_actions">
				<?php if($this->can_reply && (($this->get('type') != 'conversation' && $level == 1))){ ?>
					<a href="#" class="linkedin_reply_button"><?php _e('Reply');?></a>
				<?php } ?>
			</div>
		</div>
		<?php } ?>
		<div class="linkedin_comment_replies">
		<?php
		//if(strpos($linkedin_data['message'],'picture')){
			//echo '<pre>'; print_r($linkedin_data); echo '</pre>';
		//}
		if(count($comments)){
			// recursively print out our comments!
			//$comments = array_reverse($comments);
			foreach($comments as $comment){
				$this->_output_block($comment,$level+1);
			}
		}
		if($level <= 1) {
			if ( $this->can_reply && isset( $linkedin_data['updateKey'] ) && $linkedin_data['updateKey'] ) {
				$this->reply_box( $linkedin_data['updateKey'], $level );
			}
			if ( $this->can_reply && isset( $linkedin_data['id'] ) && $linkedin_data['id'] ) {
				$this->reply_box( $linkedin_data['id'], $level );
			}
		}
		?>
		</div>
		<?php
	}

	public function full_message_output($can_reply = false){
		$this->can_reply = $can_reply;
		// used in social_linkedin_list.php to display the full message and its comments
		switch($this->get('type')){
			default:
				$linkedin_data = @json_decode($this->get('data'),true);
				$linkedin_data['message'] = $this->get('summary');
				$linkedin_data['user_id'] = $this->get('user_id');
				$linkedin_data['comments'] = array_reverse($this->get_comments());
				//echo '<pre>'; print_r($linkedin_data['comments']); echo '</pre>';
				$this->_output_block($linkedin_data,1);

				break;
		}
	}

	public function reply_box($linkedin_id,$level=1){
		if($this->linkedin_account && $this->social_linkedin_message_id) {
			$user_data = @json_decode($this->linkedin_account->get('linkedin_data'),true);

			?>
			<div class="linkedin_comment linkedin_comment_reply_box linkedin_comment_reply_box_level<?php echo $level;?>">
				<div class="linkedin_comment_picture">
					<img src="<?php echo $user_data && isset($user_data['pictureUrl']) ? $user_data['pictureUrl'] : '#';?>">
				</div>
				<div class="linkedin_comment_header">
					<?php echo ucm_linkedin::format_person( $user_data, $this->linkedin_account ); ?>
				</div>
				<div class="linkedin_comment_reply">
					<textarea placeholder="Write a reply..."></textarea>
					<button data-linkedin-id="<?php echo htmlspecialchars($linkedin_id);?>" data-id="<?php echo (int)$this->social_linkedin_message_id;?>"><?php _e('Send');?></button>
					<br/>
					(debug) <input type="checkbox" name="debug" class="reply-debug" value="1">
				</div>
				<div class="linkedin_comment_actions"></div>
			</div>
		<?php
		}else{
			?>
			<div class="linkedin_comment linkedin_comment_reply_box">
				(incorrect settings, please report this bug)
			</div>
			<?php
		}
	}

	public function get_link() {
		// todo: doesn't work on image uploads by admin
		return '//linkedin.com/'.htmlspecialchars($this->get('linkedin_id'));
	}

	private $attachment_name = '';
	public function add_attachment($local_filename){
		if(is_file($local_filename)){
			$this->attachment_name = $local_filename;
		}
	}
	public function send_queued($debug = false){
		if($this->linkedin_account && $this->social_linkedin_message_id) {
			// send this message out to linkedin.
			// this is run when user is composing a new message from the UI,
			if ( $this->get( 'status' ) == _SOCIAL_MESSAGE_STATUS_SENDING )
				return; // dont double up on cron.


			switch($this->get('type')){
				case 'group_post':


					if(!$this->linkedin_group) {
						echo 'No linkedin group defined';
						return false;
					}

					$this->update( 'status', _SOCIAL_MESSAGE_STATUS_SENDING );
					$api = $this->linkedin_account->get_api();
					$linkedin_group_id = $this->linkedin_group->get('group_id');
					if($debug)echo "Sending a new message to linkedin Group ID: $linkedin_group_id <br>\n";
					$result = false;
					$post_data = array();
					$post_data['summary'] = $this->get('summary');
					$post_data['title'] = $this->get('title');
					$now = time();
					$send_time = $this->get('last_active');
					$result = $api->api('v1/groups/'.$linkedin_group_id.'/posts',array(),'POST',$post_data,'location');
					if($debug)echo "API Post Result: <br>\n".var_export($result,true)." <br>\n";
					if($result && preg_match('#https://api.linkedin.com/v1/posts/(.*)$#',$result,$matches)){
						// we have a result from the API! this should be an API call in itself:
						$new_post_id = $matches[1];
						$this->update('linkedin_id',$new_post_id);
						// reload this message and comments from the graph api.
						$this->load_by_linkedin_id($this->get('linkedin_id'),false,$this->get('type'),$debug, true);
					}else{
						echo 'Failed to send message. Error was: '.var_export($result,true);
						// remove from database.
						$this->delete();
						return false;
					}

					// successfully sent, mark is as answered.
					$this->update( 'status', _SOCIAL_MESSAGE_STATUS_ANSWERED );
					return true;

					break;
				case 'share':

					$this->update( 'status', _SOCIAL_MESSAGE_STATUS_SENDING );
					$api = $this->linkedin_account->get_api();
					if($debug)echo "Sending a new share update to linkedin account: " . $this->linkedin_account->get('linkedin_name') ."<br>\n";
					$result = false;
					/*
					 *
					 *  {
						  "comment": "Check out developer.linkedin.com!",
						  "content": {
						    "title": "LinkedIn Developers Resources",
						    "description": "Leverage LinkedIn's APIs to maximize engagement",
						    "submitted-url": "https://developer.linkedin.com",
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
					$post_data['comment'] = $this->get('summary');

					if($this->get('link')){
						$post_data['content'] = array(
							'title' => $this->get('title'),
							//'description' => $this->get('summary'),
							'submitted-url' => $this->get('link'),
							'submitted-image-url' => isset($user_post_data['linkedin_picture_url']) && !empty($user_post_data['linkedin_picture_url']) ? $user_post_data['linkedin_picture_url'] : '',
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
						$this->update('linkedin_id',$result['updateKey']);
						// reload this message and comments from the graph api.
						$this->load_by_linkedin_id($this->get('linkedin_id'),false,$this->get('type'),$debug, true);
					}else{
						echo 'Failed to send message. Error was: '.var_export($result,true);
						// remove from database.
						$this->delete();
						return false;
					}

					// successfully sent, mark is as answered.
					$this->update( 'status', _SOCIAL_MESSAGE_STATUS_ANSWERED );
					return true;

					break;
				default:
					if($debug)echo "Unknown post type: ".$this->get('type');
			}

		}
		return false;
	}
	public function send_reply($linkedin_id, $message, $debug = false){
		if($this->linkedin_account && $this->social_linkedin_message_id) {


			$api = $this->linkedin_account->get_api();
			if($debug)echo "Type: ".$this->get('type')." <br>\n";
			switch($this->get('type')) {
				case 'share':
					if(!$linkedin_id)$linkedin_id = $this->get('linkedin_id');

					if($debug)echo "Sending a reply to linkedin share ID: $linkedin_id <br>\n";

					$result = false;
					// send via api
					$api_result = $api->api('v1/people/~/network/updates/key='.$this->get('linkedin_id').'/update-comments',array(),'POST',array(
						'comment' => $message,
					));
					if($debug){
						echo "API Result:";
						print_r($api_result);
					}
					// reload this message and comments from the linkedin api
					$this->load_by_linkedin_id($this->get('linkedin_id'),false,$this->get('type'),$debug,true);

					// hack to add the 'user_id' of who created this reply to the db for logging.
					// the comment is added to our db in the "load_bu_linkedin_id" api call.
					$existing_comments = ucm_get_multiple('social_linkedin_message_comment',array('social_linkedin_message_id'=>$this->get('social_linkedin_message_id')),'social_linkedin_message_comment_id');
					foreach($existing_comments as $existing_comment){
						if(!$existing_comment['user_id'] && $existing_comment['comment_text'] == $message){
							ucm_update_insert('social_linkedin_message_comment_id',$existing_comment['social_linkedin_message_comment_id'],'social_linkedin_message_comment',array(
								'user_id' => get_current_user_id(),
							));
						}
					}

					break;
				case 'group_post':

					if($this->linkedin_group){
						echo 'Error no group, report this';
						return false;
					}
					if(!$linkedin_id)$linkedin_id = $this->get('linkedin_id');

					if($debug)echo "Sending a reply to linkedin ID: $linkedin_id <br>\n";
					$result = false;
					// send via api
					$api_result = $api->api('v1/posts/'.$linkedin_id.'/comments',array(),'POST',array(
						'text' => $message,
					));
					if($debug){
						echo "API Result:";
						print_r($api_result);
					}
					// reload this message and comments from the linkedin api
					$this->load_by_linkedin_id($this->get('linkedin_id'),false,$this->get('type'),$debug,true);

					// hack to add the 'user_id' of who created this reply to the db for logging.
					// the comment is added to our db in the "load_bu_linkedin_id" api call.
					$existing_comments = ucm_get_multiple('social_linkedin_message_comment',array('social_linkedin_message_id'=>$this->get('social_linkedin_message_id')),'social_linkedin_message_comment_id');
					foreach($existing_comments as $existing_comment){
						if(!$existing_comment['user_id'] && $existing_comment['comment_text'] == $message){
							ucm_update_insert('social_linkedin_message_comment_id',$existing_comment['social_linkedin_message_comment_id'],'social_linkedin_message_comment',array(
								'user_id' => get_current_user_id(),
							));
						}
					}
					break;
			}



		}
	}
	public function get_comments($comment_data = false) {
		$data = $comment_data ? $comment_data  : @json_decode($this->get('comments'),true);
		if($data && isset($data['data'])){
			$comments = $data['data'];
			// format them up nicely.
		}else{
			$comments = $data;
		}
		if(!is_array($comments))$comments=array();
		usort($comments,function($a,$b){
			if(isset($a['timestamp'])){
				return $a['timestamp'] > $b['timestamp'];
			}
			return $a['creationTimestamp'] > $b['creationTimestamp'];
		});
		return $comments;
	}

	public function get_type_pretty() {
		$type = $this->get('type');
		switch($type){
			case 'group_post':
				return 'Group Message';
				break;
			case 'share':
				return 'Network Share';
				break;
			default:
				return ucwords($type);
		}
	}

	public function get_from() {
		if($this->social_linkedin_message_id){
			$from = array();
			$data = @json_decode($this->get('data'),true);
			if(isset($data['creator']['id'])){
				$from[$data['creator']['id']] = array(
					'name' => $data['creator']['firstName'],
					'image' => isset($data['creator']['pictureUrl']) ? $data['creator']['pictureUrl'] : '',
					'link' => 'http://www.linkedin.com/x/profile/' . $this->linkedin_account->get('linkedin_app_id').'/'.$data['creator']['id'],
				);
			}
			if(isset($data['updateContent']['person'])){
				$from[$data['updateContent']['person']['id']] = array(
					'name' => $data['updateContent']['person']['firstName'],
					'image' => isset($data['updateContent']['person']['pictureUrl']) ? $data['updateContent']['person']['pictureUrl'] : '',
					'link' => 'http://www.linkedin.com/x/profile/' . $this->linkedin_account->get('linkedin_app_id').'/'.$data['updateContent']['person']['id'],
				);
			}

			$messages = ucm_get_multiple('social_linkedin_message_comment',array('social_linkedin_message_id'=>$this->social_linkedin_message_id),'social_linkedin_message_comment_id');
			foreach($messages as $message){
				if($message['message_from']){
					$data = @json_decode($message['message_from'],true);
					if(isset($data['id'])){
						$from[$data['id']] = array(
							'name' => $data['firstName'],
							'image' => isset($data['pictureUrl']) ? $data['pictureUrl'] : '',
							'link' => 'http://www.linkedin.com/x/profile/' . $this->linkedin_account->get('linkedin_app_id').'/'.$data['id'],
						);
					}
				}
			}
			return $from;
		}
		return array();
	}


	public function link_open(){
		return 'admin.php?page=support_hub_main&social_linkedin_id='.$this->linkedin_account->get('social_linkedin_id').'&social_linkedin_message_id='.$this->social_linkedin_message_id;
	}


}