<?php

class ucm_facebook_group{

	public function __construct($facebook_account = false, $social_facebook_group_id = false){
		$this->facebook_account = $facebook_account;
		$this->load($social_facebook_group_id);
	}

	/* @var $facebook_account ucm_facebook_account */
	private $facebook_account = false;
	private $social_facebook_group_id = false; // the current user id in our system.
    private $details = array();

	private function reset(){
		$this->social_facebook_group_id = false;
		$this->details = array(
			'social_facebook_group_id' => '',
			'social_facebook_id' => '',
			'group_name' => '',
			'last_message' => '',
			'last_checked' => '',
			'group_id' => '',
			'administrator' => '',
		);
		foreach($this->details as $field_id => $field_data){
			$this->{$field_id} = '';
		}
	}

	public function create_new(){
		$this->reset();
		$this->social_facebook_group_id = ucm_update_insert('social_facebook_group_id',false,'social_facebook_group',array());
		$this->load($this->social_facebook_group_id);
	}

    public function load($social_facebook_group_id = false){
	    if(!$social_facebook_group_id)$social_facebook_group_id = $this->social_facebook_group_id;
	    $this->reset();
	    $this->social_facebook_group_id = $social_facebook_group_id;
        if($this->social_facebook_group_id){
	        $data = ucm_get_single('social_facebook_group','social_facebook_group_id',$this->social_facebook_group_id);
	        foreach($this->details as $key=>$val){
		        $this->details[$key] = $data && isset($data[$key]) ? $data[$key] : $val;
	        }
	        if(!is_array($this->details) || $this->details['social_facebook_group_id'] != $this->social_facebook_group_id){
		        $this->reset();
		        return false;
	        }
        }
        foreach($this->details as $key=>$val){
            $this->{$key} = $val;
        }
        return $this->social_facebook_group_id;
    }

	public function get($field){
		return isset($this->{$field}) ? $this->{$field} : false;
	}

    public function update($field,$value){
	    // what fields to we allow? or not allow?
	    if(in_array($field,array('social_facebook_group_id')))return;
        if($this->social_facebook_group_id){
            $this->{$field} = $value;
            ucm_update_insert('social_facebook_group_id',$this->social_facebook_group_id,'social_facebook_group',array(
	            $field => $value,
            ));
        }
    }
	public function delete(){
		if($this->social_facebook_group_id) {
			// delete all the messages for this twitter account.
			$messages = ucm_get_multiple('social_facebook_message',array(
				'social_facebook_group_id' => $this->social_facebook_group_id,
			),'social_facebook_message_id');
			foreach($messages as $message){
				if($message && isset($message['social_facebook_group_id']) && $message['social_facebook_group_id'] == $this->social_facebook_group_id){
					ucm_delete_from_db( 'social_facebook_message', 'social_facebook_message_id', $message['social_facebook_message_id'] );
					ucm_delete_from_db( 'social_facebook_message_link', 'social_facebook_message_id', $message['social_facebook_message_id'] );
					ucm_delete_from_db( 'social_facebook_message_read', 'social_facebook_message_id', $message['social_facebook_message_id'] );
				}
			}
			ucm_delete_from_db( 'social_facebook_group', 'social_facebook_group_id', $this->social_facebook_group_id );
		}
	}

	public function get_messages($search=array()){
		$facebook = new ucm_facebook();
		$search['social_facebook_group_id'] = $this->social_facebook_group_id;
		return $facebook->load_all_messages($search);
		//return get_m ultiple('social_facebook_message',$search,'social_facebook_message_id','exact','last_active');
	}

	public function run_cron($debug = false){
		// find all messages that haven't been sent yet.
		$messages = $this->get_messages(array(
			'status' => _SOCIAL_MESSAGE_STATUS_PENDINGSEND,
		));
		$now = time();
		foreach($messages as $message){
			if(isset($message['message_time']) && $message['message_time'] < $now){
				$ucm_facebook_message = new ucm_facebook_message(false, $this, $message['social_facebook_message_id']);
				$ucm_facebook_message->send_queued($debug);
			}
		}
	}

	/* start FB graph calls */
	public function graph_load_latest_group_data($debug = false){
		// serialise this result into facebook_data.
		if(!$this->facebook_account){
			echo 'No facebook account linked, please try again';
			return;
		}
		$access_token = $this->facebook_account->get('facebook_token');
		// get the machine id from the parent facebook_account
		$machine_id = $this->facebook_account->get('machine_id');
		if(!$access_token){
			echo 'No access token for facebook found, please login again';
			return;
		}

		$facebook_group_id = $this->get('group_id');
		if(!$facebook_group_id){
			echo 'No facebook group id found';
			return;
		}

		if($debug)echo "Getting the latest group data for FB group: ".$facebook_group_id."<br>";

		// we keep a record of the last message received so we know where to stop checking in the FB feed
		$last_message_received = (int)$this->get('last_message');

		if($debug)echo "The last message we received for this group was on: ".ucm_print_date($last_message_received,true).'<br>';

		$newest_message_received = 0;

		if($debug)echo "Getting /feed group posts... <br>";
		$facebook_api = new ucm_facebook();
		$group_feed = $facebook_api->graph('/'.$facebook_group_id.'/feed',array(
			'access_token' => $access_token,
			'machine_id' => $machine_id,
		));
		$count = 0;
		if(isset($group_feed['error']) && !empty($group_feed['error'])){
            if($debug)echo " FACEBOOK ERROR : ".$group_feed['error']['message'] ."<br>";
        }
		if(isset($group_feed['data']) && !empty($group_feed['data'])){
			foreach($group_feed['data'] as $group_feed_message){
				if(!$group_feed_message['id'])continue;
				$message_time = strtotime(isset($group_feed_message['updated_time']) && strlen($group_feed_message['updated_time']) ? $group_feed_message['updated_time'] : $group_feed_message['created_time']);
				$newest_message_received = max($message_time, $newest_message_received);
				if($last_message_received && $last_message_received >= $message_time){
					// we've already processed messages after this time.
					if($debug)echo " - Skipping this message (".$group_feed_message['id'].") because it was received on ".ucm_print_date($message_time,true).' and we only want ones after '.ucm_print_date($last_message_received,true).'<br>';
					break;
				}else{
					if($debug)echo ' - storing message received on '.ucm_print_date($message_time,true).'<br>';
				}
				// check if we have this message in our database already.
				$facebook_message = new ucm_facebook_message($this->facebook_account, $this, false);
				if($facebook_message -> load_by_facebook_id($group_feed_message['id'], $group_feed_message, 'feed')){
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


		if($debug)echo "The last message we received for this group was now on: ".ucm_print_date($newest_message_received,true).'<br>';
		if($debug)echo "Finished checking this group messages at ".ucm_print_date(time(),true)."<br>";

		$this->update('last_message',$newest_message_received);
		$this->update('last_checked',time());
	}

	public function link_refresh(){
		return 'admin.php?page=support_hub_facebook_settings&manualrefresh&social_facebook_id='.$this->get('social_facebook_id').'&facebook_group_id='.$this->get('group_id');
	}


}
