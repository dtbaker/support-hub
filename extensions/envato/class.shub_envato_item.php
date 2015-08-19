<?php

class shub_envato_item extends SupportHub_item{



	public function run_cron($debug = false){
		// find all messages that haven't been sent yet.
		/*$messages = $this->get_messages(array(
			'status' => _shub_MESSAGE_STATUS_PENDINGSEND,
		));
		$now = time();
		foreach($messages as $message){
			if(isset($message['message_time']) && $message['message_time'] < $now){
				$shub_message = new shub_message(false, $this, $message['shub_message_id']);
				$shub_message->send_queued($debug);
			}
		}*/

		$this->load_latest_item_data($debug);
	}

	public function load_latest_item_data($debug = false){
		// serialise this result into envato_data.
		if(!$this->account){
			echo 'No envato account linked, please try again';
			return;
		}

		$api = $this->account->get_api();

		$network_key = $this->get('network_key');
		if(!$network_key){
			echo 'No envato item id found';
			return;
		}

		// we keep a record of the last message received so we know where to stop checking the feed
		$last_message_received = (int)$this->get('last_message');
		if($debug)echo "Getting the latest 60 comments for item: ".$network_key." (last message in database is from ".shub_print_date($last_message_received,true).")<br>\n";

		$newest_message_received = 0;

		$endpoint = 'v1/discovery/search/search/comment?term=&item_id='.$network_key.'&sort_by=newest&page_size=60';
		$api_result = $api->api($endpoint);
		if($debug){
			echo "API Result took :".$api_result['took'].' seconds and produced '.count($api_result['matches']).' results';
		}

		$count = 0;
		if(isset($api_result['matches']) && is_array($api_result['matches'])){
			//foreach($api_result['matches'] as $item_message){
            while($api_result['matches']){
                $item_message = array_pop($api_result['matches']);
				if(!$item_message['id'])continue;
				$message_time = strtotime($item_message['last_comment_at']);
				$newest_message_received = max($newest_message_received,$message_time);
				if($message_time <= $last_message_received)continue; // all done here.

				// check if we have this message in our database already.
				$envato_message = new shub_message($this->account, $this, false);
				$envato_message -> load_by_network_key($item_message['id'], $item_message, 'item_comment', $debug);
				$count++;
				if($debug) {
					?>
					<div>
					<pre> Imported message ID: <?php echo $envato_message->get( 'network_key' ); ?> </pre>
					</div>
				<?php
				}
			}
		}
		SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_INFO, 'envato', 'Imported  '.$count.' new messages into database');
		if($debug)echo " imported $count new item comments <br>";

		$this->update('last_message',$newest_message_received);
		$this->update('last_checked',time());
	}

	public function link_refresh(){
		return 'admin.php?page=support_hub_settings&tab=envato&manualrefresh&shub_account_id='.$this->get('shub_account_id').'&network_key='.$this->get('network_key');
	}


}
