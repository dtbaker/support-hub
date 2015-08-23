<?php

class shub_bbpress_item extends SupportHub_item{

	public function run_cron($debug = false){
		// find all messages that haven't been sent yet.
		/*$messages = $this->get_messages(array(
			'shub_status' => _shub_MESSAGE_STATUS_PENDINGSEND,
		));
		$now = time();
		foreach($messages as $message){
			if(isset($message['message_time']) && $message['message_time'] < $now){
				$shub_bbpress_message = new shub_bbpress_message(false, $this, $message['shub_message_id']);
				$shub_bbpress_message->send_queued($debug);
			}
		}*/

        SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_INFO,'bbpress','Starting bbPress Cron for Forum: '.$this->get('item_name'));
		$this->load_latest_item_data($debug);
	}

	public function load_latest_item_data($debug = false){
		// serialise this result into account_data.
		if(!$this->account){
			echo 'No bbpress account linked, please try again';
			return;
		}

		$api = $this->account->get_api();

		$network_key = $this->get('network_key');
		if(!$network_key){
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
        $api_result_latest_replies = $this->account->get_api_cache($filter_replies);
		$api_result_latest_replies = $api_result_latest_replies ? $api_result_latest_replies : $api->getPosts($filter_replies);

		$filter_topics = array(
			'post_type' => 'topic',
			'number' => 100,
			'post_status' => 'publish',
			//'post_parent' =>
		);
		$api_result_latest_topics = $this->account->get_api_cache($filter_topics);
		$api_result_latest_topics = $api_result_latest_topics ? $api_result_latest_topics : $api->getPosts($filter_topics);


		// loop through our latest replies and see if any of them are from a thread that sits under this forum
		// COMPLETELY THE REVERSE WAY THAT WE SHOULD BE DOING IT! rar!

		$forum_topics = array();

		foreach($api_result_latest_topics as $forum_topic){
			if($forum_topic['post_parent'] == $network_key){
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
				if($found_parent['post_parent'] == $network_key){
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
			}else{

            }
		}
		uasort($forum_topics,function($a,$b){
			return $a['timestamp'] < $b['timestamp'];
		});
		// cache them for any other bbpress forum calls that are run during the same cron job process.
		$this->account->set_api_cache($filter_replies,$api_result_latest_replies);
		$this->account->set_api_cache($filter_topics,$api_result_latest_topics);


		// we keep a record of the last message received so we know where to stop checking the feed
		$last_message_received = (int)$this->get('last_message');
		if($debug)echo "Getting the latest replies for forum: ".$network_key." (last message in database is from ".shub_print_date($last_message_received,true).")<br>\n";

		$newest_message_received = 0;

        SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_INFO,'bbpress','Found total of '.count($forum_topics)." forum topics from API calls");
		$count = 0;
		foreach($forum_topics as $forum_topic){
			$message_time = $forum_topic['timestamp'];
			$newest_message_received = max($newest_message_received,$message_time);
			if($message_time <= $last_message_received)break; // all done here.

			$bbpress_message = new shub_bbpress_message($this->account, $this, false);
			$bbpress_message -> load_by_bbpress_id($forum_topic['post_id'], $forum_topic, 'forum_topic', $debug);
			$count++;
            SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_INFO,'bbpress','Imported forum topic ID '.$bbpress_message->get( 'bbpress_id' )." with ".count($forum_topic['replies']).' replies');
			if($debug) {
				?>
				<div>
				<pre> Imported forum topic ID: <?php echo $bbpress_message->get( 'bbpress_id' ); ?> with <?php echo count($forum_topic['replies']);?> replies. </pre>
				</div>
			<?php
			}

		}
		// get user, return envato_codes in meta
		SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_INFO, 'bbpress', 'Completed Cron Import: '.$count.' new forum topics');
		if($debug)echo " imported $count new forum comments <br>";

		$this->update('last_message',$newest_message_received);
		$this->update('last_checked',time());
	}

	public function link_refresh(){
		return 'admin.php?page=support_hub_settings&tab=bbpress&manualrefresh&shub_account_id='.$this->get('shub_account_id').'&network_key='.$this->get('network_key');
	}


}
