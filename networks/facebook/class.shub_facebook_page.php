<?php

class shub_facebook_page{

	public function __construct($facebook_account = false, $shub_facebook_page_id = false){
		$this->facebook_account = $facebook_account;
		$this->load($shub_facebook_page_id);
	}

	/* @var $facebook_account shub_facebook_account */
	private $facebook_account = false;
	private $shub_facebook_page_id = false; // the current user id in our system.
    private $details = array();

	private function reset(){
		$this->shub_facebook_page_id = false;
		$this->details = array(
			'shub_facebook_page_id' => '',
			'shub_facebook_id' => '',
			'page_name' => '',
			'last_message' => '',
			'last_checked' => '',
			'page_id' => '',
			'facebook_token' => '',
		);
		foreach($this->details as $field_id => $field_data){
			$this->{$field_id} = '';
		}
	}

	public function create_new(){
		$this->reset();
		$this->shub_facebook_page_id = shub_update_insert('shub_facebook_page_id',false,'shub_facebook_page',array());
		$this->load($this->shub_facebook_page_id);
	}

    public function load($shub_facebook_page_id = false){
	    if(!$shub_facebook_page_id)$shub_facebook_page_id = $this->shub_facebook_page_id;
	    $this->reset();
	    $this->shub_facebook_page_id = $shub_facebook_page_id;
        if($this->shub_facebook_page_id){
	        $data = shub_get_single('shub_facebook_page','shub_facebook_page_id',$this->shub_facebook_page_id);
	        foreach($this->details as $key=>$val){
		        $this->details[$key] = $data && isset($data[$key]) ? $data[$key] : $val;
	        }
	        if(!is_array($this->details) || $this->details['shub_facebook_page_id'] != $this->shub_facebook_page_id){
		        $this->reset();
		        return false;
	        }
        }
        foreach($this->details as $key=>$val){
            $this->{$key} = $val;
        }
        return $this->shub_facebook_page_id;
    }

	public function get($field){
		return isset($this->{$field}) ? $this->{$field} : false;
	}

    public function update($field,$value){
	    // what fields to we allow? or not allow?
	    if(in_array($field,array('shub_facebook_page_id')))return;
        if($this->shub_facebook_page_id){
            $this->{$field} = $value;
            shub_update_insert('shub_facebook_page_id',$this->shub_facebook_page_id,'shub_facebook_page',array(
	            $field => $value,
            ));
        }
    }
	public function delete(){
		if($this->shub_facebook_page_id) {
			// delete all the messages for this twitter account.
			$messages = shub_get_multiple('shub_facebook_message',array(
				'shub_facebook_page_id' => $this->shub_facebook_page_id,
			),'shub_facebook_message_id');
			foreach($messages as $message){
				if($message && isset($message['shub_facebook_page_id']) && $message['shub_facebook_page_id'] == $this->shub_facebook_page_id){
					shub_delete_from_db( 'shub_facebook_message', 'shub_facebook_message_id', $message['shub_facebook_message_id'] );
					shub_delete_from_db( 'shub_facebook_message_link', 'shub_facebook_message_id', $message['shub_facebook_message_id'] );
					shub_delete_from_db( 'shub_facebook_message_read', 'shub_facebook_message_id', $message['shub_facebook_message_id'] );
				}
			}
			shub_delete_from_db( 'shub_facebook_page', 'shub_facebook_page_id', $this->shub_facebook_page_id );
		}
	}

	public function get_messages($search=array()){
		$facebook = new shub_facebook();
		$search['shub_facebook_page_id'] = $this->shub_facebook_page_id;
		return $facebook->load_all_messages($search);
		//return get_m ultiple('shub_facebook_message',$search,'shub_facebook_message_id','exact','last_active');
	}

	public function run_cron($debug = false){
		// find all messages that haven't been sent yet.
		$messages = $this->get_messages(array(
			'status' => _shub_MESSAGE_STATUS_PENDINGSEND,
		));
		$now = time();
		foreach($messages as $message){
			if(isset($message['message_time']) && $message['message_time'] < $now){
				$shub_facebook_message = new shub_facebook_message(false, $this, $message['shub_facebook_message_id']);
				$shub_facebook_message->send_queued($debug);
			}
		}
	}

	/* start FB graph calls */
	public function graph_load_latest_page_data($debug = false){
		// serialise this result into facebook_data.
		if(!$this->facebook_account){
			echo 'No facebook account linked, please try again';
			return;
		}

		$access_token = $this->get('facebook_token');
		// get the machine id from the parent facebook_account
		$machine_id = $this->facebook_account->get('machine_id');
		if(!$access_token){
			echo 'No access token for facebook page found';
			return;
		}

		$facebook_page_id = $this->get('page_id');
		if(!$facebook_page_id){
			echo 'No facebook page id found';
			return;
		}

		if($debug)echo "Getting the latest page data for FB Page: ".$facebook_page_id."<br>";

		// we keep a record of the last message received so we know where to stop checking in the FB feed
		$last_message_received = (int)$this->get('last_message');

		if($debug)echo "The last message we received for this page was on: ".shub_print_date($last_message_received,true).'<br>';

		$newest_message_received = 0;

		if($debug)echo "Getting /tagged page posts... <br>";
		$facebook_api = new shub_facebook();
		$page_feed = $facebook_api->graph('/'.$facebook_page_id.'/tagged',array(
			'access_token' => $access_token,
			'machine_id' => $machine_id,
		));
		$count = 0;
		if(isset($page_feed['error']) && !empty($page_feed['error'])){
            if($debug)echo " FACEBOOK ERROR : ".$page_feed['error']['message'] ."<br>";
        }
		if(isset($page_feed['data']) && !empty($page_feed['data'])){
			foreach($page_feed['data'] as $page_feed_message){
				if(!$page_feed_message['id'])continue;
				$message_time = strtotime(isset($page_feed_message['updated_time']) && strlen($page_feed_message['updated_time']) ? $page_feed_message['updated_time'] : $page_feed_message['created_time']);
				$newest_message_received = max($message_time, $newest_message_received);
				if($last_message_received && $last_message_received >= $message_time){
					// we've already processed messages after this time.
					if($debug)echo " - Skipping this message because it was received on ".shub_print_date($message_time,true).' and we only want ones after '.shub_print_date($last_message_received,true).'<br>';
					break;
				}else{
					if($debug)echo ' - storing message received on '.shub_print_date($message_time,true).'<br>';
				}
				// check if we have this message in our database already.
				$facebook_message = new shub_facebook_message($this->facebook_account, $this, false);
				if($facebook_message -> load_by_facebook_id($page_feed_message['id'], $page_feed_message, 'feed')){
					$count++;
				}
				if($debug) {
					?>
					<div>
					<pre> <?php echo $facebook_message->get( 'facebook_id' ); ?>
						<?php print_r( $facebook_message->get( 'data' ) ); ?>
					</pre>
					</div>
				<?php
				}
			}
		}
		if($debug)echo " got $count new posts <br>";


		// instead of /feed
		if($debug)echo "Getting /posts page posts... <br>";
		$facebook_api = new shub_facebook();
		$page_feed = $facebook_api->graph('/'.$facebook_page_id.'/posts',array(
			'access_token' => $access_token,
			'machine_id' => $machine_id,
		));
		$count = 0;
		if(isset($page_feed['error']) && !empty($page_feed['error'])){
            if($debug)echo " FACEBOOK ERROR: ".$page_feed['error']['message'] ."<br>";
        }
		if(isset($page_feed['data']) && !empty($page_feed['data'])){
			foreach($page_feed['data'] as $page_feed_message){
				if(!$page_feed_message['id'])continue;
				$message_time = strtotime(isset($page_feed_message['updated_time']) && strlen($page_feed_message['updated_time']) ? $page_feed_message['updated_time'] : $page_feed_message['created_time']);
				$newest_message_received = max($message_time, $newest_message_received);
				if($last_message_received && $last_message_received >= $message_time){
					// we've already processed messages after this time.
					if($debug)echo " - Skipping this message because it was received on ".shub_print_date($message_time,true).' and we only want ones after '.shub_print_date($last_message_received,true).'<br>';
					break;
				}else{
					if($debug)echo ' - storing message received on '.shub_print_date($message_time,true).'<br>';
				}
				// check if we have this message in our database already.
				$facebook_message = new shub_facebook_message($this->facebook_account, $this, false);
				if($facebook_message -> load_by_facebook_id($page_feed_message['id'], $page_feed_message, 'feed')){
					$count++;
				}
				if($debug) {
					?>
					<div>
					<pre> <?php echo $facebook_message->get( 'facebook_id' ); ?>
						<?php print_r( $facebook_message->get( 'data' ) ); ?>
					</pre>
					</div>
				<?php
				}
			}
		}
		if($debug)echo " got $count new posts <br>";

		if($debug)echo "Getting /conversations inbox messages... <br>";
		// get conversations (inbox) from fb:
		$conversation_feed = $facebook_api->graph('/'.$facebook_page_id.'/conversations',array(
			'access_token' => $access_token,
			'machine_id' => $machine_id,
		));
		$count = 0;
		if(isset($conversation_feed['error']) && !empty($conversation_feed['error'])){
            if($debug)echo " FACEBOOK ERROR: ".$conversation_feed['error']['message'] ."<br>";
        }
		if(isset($conversation_feed['data']) && !empty($conversation_feed['data'])){
			foreach($conversation_feed['data'] as $conversation){
				if(!$conversation['id'])continue;
				$message_time = strtotime(isset($conversation['updated_time']) && strlen($conversation['updated_time']) ? $conversation['updated_time'] : $conversation['created_time']);
				$newest_message_received = max($message_time, $newest_message_received);
				if($last_message_received && $last_message_received >= $message_time){
					// we've already processed messages after this time.
					if($debug)echo " - Skipping this message because it was received on ".shub_print_date($message_time,true).' and we only want ones after '.shub_print_date($last_message_received,true).'<br>';
					break;
				}else{
					if($debug)echo ' - storing message received on '.shub_print_date($message_time,true).'<br>';
				}
				// check if we have this message in our database already.
				$facebook_message = new shub_facebook_message($this->facebook_account, $this, false);
				if($facebook_message -> load_by_facebook_id($conversation['id'], $conversation, 'conversation')){
					$count ++;
				}
			}
		}
		if($debug)echo " got $count new messages <br>";

		if($debug)echo "The last message we received for this page was now on: ".shub_print_date($newest_message_received,true).'<br>';
		if($debug)echo "Finished checking this page messages at ".shub_print_date(time(),true)."<br>";

		$this->update('last_message',$newest_message_received);
		$this->update('last_checked',time());
	}

	public function link_refresh(){
		return 'admin.php?page=support_hub_settings&tab=facebook&manualrefresh&shub_facebook_id='.$this->get('shub_facebook_id').'&facebook_page_id='.$this->get('page_id');
	}


}
