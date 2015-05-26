<?php

class shub_bbpress_forum{

	public function __construct($bbpress_account = false, $shub_bbpress_forum_id = false){
		$this->bbpress_account = $bbpress_account;
		$this->load($shub_bbpress_forum_id);
	}

	/* @var $bbpress_account shub_bbpress_account */
	private $bbpress_account = false;
	private $shub_bbpress_forum_id = false; // the current user id in our system.
    private $details = array();
	private $json_fields = array('bbpress_data');

	private function reset(){
		$this->shub_bbpress_forum_id = false;
		$this->details = array(
			'shub_bbpress_forum_id' => '',
			'shub_bbpress_id' => '',
			'shub_product_id' => '',
			'forum_name' => '',
			'last_message' => '',
			'last_checked' => '',
			'forum_id' => '',
			'bbpress_data' => array(),
		);
		foreach($this->details as $field_id => $field_data){
			$this->{$field_id} = $field_data;
		}
	}

	public function create_new(){
		$this->reset();
		$this->shub_bbpress_forum_id = shub_update_insert('shub_bbpress_forum_id',false,'shub_bbpress_forum',array());
		$this->load($this->shub_bbpress_forum_id);
	}

    public function load($shub_bbpress_forum_id = false){
	    if(!$shub_bbpress_forum_id)$shub_bbpress_forum_id = $this->shub_bbpress_forum_id;
	    $this->reset();
	    $this->shub_bbpress_forum_id = $shub_bbpress_forum_id;
        if($this->shub_bbpress_forum_id){
	        $data = shub_get_single('shub_bbpress_forum','shub_bbpress_forum_id',$this->shub_bbpress_forum_id);
	        foreach($this->details as $key=>$val){
		        $this->details[$key] = $data && isset($data[$key]) ? $data[$key] : $val;
		        if(in_array($key,$this->json_fields)){
			        $this->details[$key] = @json_decode($this->details[$key],true);
			        if(!is_array($this->details[$key]))$this->details[$key] = array();
		        }
	        }
	        if(!is_array($this->details) || $this->details['shub_bbpress_forum_id'] != $this->shub_bbpress_forum_id){
		        $this->reset();
		        return false;
	        }
        }
        foreach($this->details as $key=>$val){
            $this->{$key} = $val;
        }
        return $this->shub_bbpress_forum_id;
    }

	public function get($field){
		return isset($this->{$field}) ? $this->{$field} : false;
	}

    public function update($field,$value){
	    // what fields to we allow? or not allow?
	    if(in_array($field,array('shub_bbpress_forum_id')))return;
        if($this->shub_bbpress_forum_id){
            $this->{$field} = $value;
	        if(in_array($field,$this->json_fields)){
		        $value = json_encode($value);
	        }
            shub_update_insert('shub_bbpress_forum_id',$this->shub_bbpress_forum_id,'shub_bbpress_forum',array(
	            $field => $value,
            ));
        }
    }
	public function delete(){
		if($this->shub_bbpress_forum_id) {
			// delete all the messages for this twitter account.
			$messages = shub_get_multiple('shub_bbpress_message',array(
				'shub_bbpress_forum_id' => $this->shub_bbpress_forum_id,
			),'shub_bbpress_message_id');
			foreach($messages as $message){
				if($message && isset($message['shub_bbpress_forum_id']) && $message['shub_bbpress_forum_id'] == $this->shub_bbpress_forum_id){
					shub_delete_from_db( 'shub_bbpress_message', 'shub_bbpress_message_id', $message['shub_bbpress_message_id'] );
					shub_delete_from_db( 'shub_bbpress_message_link', 'shub_bbpress_message_id', $message['shub_bbpress_message_id'] );
					shub_delete_from_db( 'shub_bbpress_message_read', 'shub_bbpress_message_id', $message['shub_bbpress_message_id'] );
				}
			}
			shub_delete_from_db( 'shub_bbpress_forum', 'shub_bbpress_forum_id', $this->shub_bbpress_forum_id );
		}
	}

	public function get_messages($search=array()){
		$bbpress = new shub_bbpress();
		$search['shub_bbpress_forum_id'] = $this->shub_bbpress_forum_id;
		return $bbpress->load_all_messages($search);
		//return get_m ultiple('shub_bbpress_message',$search,'shub_bbpress_message_id','exact','last_active');
	}

	public function run_cron($debug = false){
		// find all messages that haven't been sent yet.
		$messages = $this->get_messages(array(
			'status' => _shub_MESSAGE_STATUS_PENDINGSEND,
		));
		$now = time();
		foreach($messages as $message){
			if(isset($message['message_time']) && $message['message_time'] < $now){
				$shub_bbpress_message = new shub_bbpress_message(false, $this, $message['shub_bbpress_message_id']);
				$shub_bbpress_message->send_queued($debug);
			}
		}

		$this->load_latest_forum_data($debug);
	}

	public function load_latest_forum_data($debug = false){
		// serialise this result into bbpress_data.
		if(!$this->bbpress_account){
			echo 'No bbpress account linked, please try again';
			return;
		}

		$api = $this->bbpress_account->get_api();

		$bbpress_forum_id = $this->get('forum_id');
		if(!$bbpress_forum_id){
			echo 'No bbpress forum id found';
			return;
		}

		// first we seed the cache with the latest bbpress replies and topics
		// we do this because it's not possible to filter based on "post_parent" through the WordPress API (SILLY!)
		// so this saves us calling getPost() a lot of times.
		$filter_replies = array(
			'post_type' => 'reply',
			'number' => 100,
			'post_status' => 'publish',
			//'post_parent' =>
		);
		$api_result_latest_replies = $this->bbpress_account->get_api_cache($filter_replies);
		$api_result_latest_replies = $api_result_latest_replies ? $api_result_latest_replies : $api->getPosts($filter_replies);

		$filter_topics = array(
			'post_type' => 'topic',
			'number' => 100,
			'post_status' => 'publish',
			//'post_parent' =>
		);
		$api_result_latest_topics = $this->bbpress_account->get_api_cache($filter_topics);
		$api_result_latest_topics = $api_result_latest_topics ? $api_result_latest_topics : $api->getPosts($filter_topics);


		// loop through our latest replies and see if any of them are from a thread that sits under this forum
		// COMPLETELY THE REVERSE WAY THAT WE SHOULD BE DOING IT! rar!

		$forum_topics = array();

		foreach($api_result_latest_topics as $forum_topic){
			if($forum_topic['post_parent'] == $bbpress_forum_id){
				// yay! this reply is part of a topic that is part of this forum. keep it.
				if(!isset($forum_topics[$forum_topic['post_id']])){
					$forum_topics[$forum_topic['post_id']] = $forum_topic;
				}
				if(!isset($forum_topics[$forum_topic['post_id']]['replies'])){
					$forum_topics[$forum_topic['post_id']]['replies'] = array();
				}
				$forum_topics[$forum_topic['post_id']]['timestamp'] = $forum_topic['post_date']->timestamp;
			}
		}
		foreach($api_result_latest_replies as $forum_reply){

			// find its parent and see if it is from this forum.
			$found_parent = false;
			foreach($api_result_latest_topics as $forum_topic){
				if($forum_topic['post_id'] == $forum_reply['post_parent']){
					$found_parent = $forum_topic;
					break;
				}
			}
			if(!$found_parent){
				$api_result_parent = $api->getPost($forum_reply['post_parent']);
				if($api_result_parent){
					$found_parent = $api_result_parent;
					$api_result_latest_topics[] = $api_result_parent; // add to cache so we hopefully dont have to hit it again if it's a popular topic
				}
			}
			if($found_parent){
				// found a parent post, check if it's part of this forum.
				if($found_parent['post_parent'] == $bbpress_forum_id){
					// yay! this reply is part of a topic that is part of this forum. keep it.
					if(!isset($forum_topics[$found_parent['post_id']])){
						$forum_topics[$found_parent['post_id']] = $found_parent;
					}
					if(!isset($forum_topics[$found_parent['post_id']]['replies'])){
						$forum_topics[$found_parent['post_id']]['replies'] = array();
					}
					$forum_topics[$found_parent['post_id']]['replies'][] = $forum_reply;
					if(!isset($forum_topics[$found_parent['post_id']]['timestamp'])){
						$forum_topics[$found_parent['post_id']]['timestamp'] = $found_parent['post_date']->timestamp;
					}
					$forum_topics[$found_parent['post_id']]['timestamp'] = max($forum_reply['post_date']->timestamp,$forum_topics[$found_parent['post_id']]['timestamp']);
				}

				/*echo date('Y-m-d',$forum_reply['post_date']->timestamp);
				echo " <a href='".$forum_reply['link']."'>'".$forum_reply['link'].'</a> ';
				echo $forum_reply['post_content'];
				echo "Parent is: ";
				echo date('Y-m-d',$found_parent['post_date']->timestamp);
				echo " <a href='".$found_parent['link']."'>'".$found_parent['link'].'</a> ';
				echo '<hr>';*/
			}
		}
		uasort($forum_topics,function($a,$b){
			return $a['timestamp'] < $b['timestamp'];
		});

		// cache them for any other bbpress forum calls that are run during the same cron job process.
		$this->bbpress_account->set_api_cache($filter_replies,$api_result_latest_replies);
		$this->bbpress_account->set_api_cache($filter_topics,$api_result_latest_topics);


		// we keep a record of the last message received so we know where to stop checking the feed
		$last_message_received = (int)$this->get('last_message');
		if($debug)echo "Getting the latest replies for forum: ".$bbpress_forum_id." (last message in database is from ".shub_print_date($last_message_received,true).")<br>\n";

		$newest_message_received = 0;

		$count = 0;
		foreach($forum_topics as $forum_topic){
			$message_time = $forum_topic['timestamp'];
			$newest_message_received = max($newest_message_received,$message_time);
			if($message_time <= $last_message_received)break; // all done here.

			$bbpress_message = new shub_bbpress_message($this->bbpress_account, $this, false);
			$bbpress_message -> load_by_bbpress_id($forum_topic['post_id'], $forum_topic, 'forum_topic', $debug);
			$count++;
			if($debug) {
				?>
				<div>
				<pre> Imported forum topic ID: <?php echo $bbpress_message->get( 'bbpress_id' ); ?> with <?php echo count($forum_topic['replies']);?> replies. </pre>
				</div>
			<?php
			}

		}
		// get user, return envato_codes in meta
		SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_INFO, 'bbpress', 'Imported  '.$count.' forum topics into database');
		if($debug)echo " imported $count new forum comments <br>";

		$this->update('last_message',$newest_message_received);
		$this->update('last_checked',time());
	}

	public function link_refresh(){
		return 'admin.php?page=support_hub_settings&tab=bbpress&manualrefresh&shub_bbpress_id='.$this->get('shub_bbpress_id').'&bbpress_forum_id='.$this->get('forum_id');
	}


}
