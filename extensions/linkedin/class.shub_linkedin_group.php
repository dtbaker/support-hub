<?php

class shub_linkedin_group{

	public function __construct($linkedin_account = false, $shub_linkedin_group_id = false){
		$this->linkedin_account = $linkedin_account;
		$this->load($shub_linkedin_group_id);
	}

	/* @var $linkedin_account shub_linkedin_account */
	private $linkedin_account = false;
	private $shub_linkedin_group_id = false; // the current user id in our system.
    private $details = array();

	private function reset(){
		$this->shub_linkedin_group_id = false;
		$this->details = array(
			'shub_linkedin_group_id' => '',
			'shub_linkedin_id' => '',
			'group_name' => '',
			'last_message' => '',
			'last_checked' => '',
			'group_id' => '',
			'linkedin_token' => '',
		);
		foreach($this->details as $field_id => $field_data){
			$this->{$field_id} = '';
		}
	}

	public function create_new(){
		$this->reset();
		$this->shub_linkedin_group_id = shub_update_insert('shub_linkedin_group_id',false,'shub_linkedin_group',array());
		$this->load($this->shub_linkedin_group_id);
	}

    public function load($shub_linkedin_group_id = false){
	    if(!$shub_linkedin_group_id)$shub_linkedin_group_id = $this->shub_linkedin_group_id;
	    $this->reset();
	    $this->shub_linkedin_group_id = $shub_linkedin_group_id;
        if($this->shub_linkedin_group_id){
	        $data = shub_get_single('shub_linkedin_group','shub_linkedin_group_id',$this->shub_linkedin_group_id);
	        foreach($this->details as $key=>$val){
		        $this->details[$key] = $data && isset($data[$key]) ? $data[$key] : $val;
	        }
	        if(!is_array($this->details) || $this->details['shub_linkedin_group_id'] != $this->shub_linkedin_group_id){
		        $this->reset();
		        return false;
	        }
        }
        foreach($this->details as $key=>$val){
            $this->{$key} = $val;
        }
        return $this->shub_linkedin_group_id;
    }

	public function get($field){
		return isset($this->{$field}) ? $this->{$field} : false;
	}

    public function update($field,$value){
	    // what fields to we allow? or not allow?
	    if(in_array($field,array('shub_linkedin_group_id')))return;
        if($this->shub_linkedin_group_id){
            $this->{$field} = $value;
            shub_update_insert('shub_linkedin_group_id',$this->shub_linkedin_group_id,'shub_linkedin_group',array(
	            $field => $value,
            ));
        }
    }
	public function delete(){
		if($this->shub_linkedin_group_id) {
			// delete all the messages for this twitter account.
			$messages = shub_get_multiple('shub_linkedin_message',array(
				'shub_linkedin_group_id' => $this->shub_linkedin_group_id,
			),'shub_linkedin_message_id');
			foreach($messages as $message){
				if($message && isset($message['shub_linkedin_group_id']) && $message['shub_linkedin_group_id'] == $this->shub_linkedin_group_id){
					shub_delete_from_db( 'shub_linkedin_message', 'shub_linkedin_message_id', $message['shub_linkedin_message_id'] );
					shub_delete_from_db( 'shub_linkedin_message_link', 'shub_linkedin_message_id', $message['shub_linkedin_message_id'] );
					shub_delete_from_db( 'shub_linkedin_message_read', 'shub_linkedin_message_id', $message['shub_linkedin_message_id'] );
				}
			}
			shub_delete_from_db( 'shub_linkedin_group', 'shub_linkedin_group_id', $this->shub_linkedin_group_id );
		}
	}

	public function get_messages($search=array()){
		$linkedin = new shub_linkedin();
		$search['shub_linkedin_group_id'] = $this->shub_linkedin_group_id;
		return $linkedin->load_all_messages($search);
		//return get_m ultiple('shub_linkedin_message',$search,'shub_linkedin_message_id','exact','last_active');
	}

	public function run_cron($debug = false){
		// find all messages that haven't been sent yet.
		$messages = $this->get_messages(array(
			'shub_status' => _shub_MESSAGE_STATUS_PENDINGSEND,
		));
		$now = time();
		foreach($messages as $message){
			if(isset($message['message_time']) && $message['message_time'] < $now){
				$shub_linkedin_message = new shub_linkedin_message(false, $this, $message['shub_linkedin_message_id']);
				$shub_linkedin_message->send_queued($debug);
			}
		}

		$this->load_latest_group_data($debug);
	}

	/* start FB graph calls */
	public function load_latest_group_data($debug = false){
		// serialise this result into linkedin_data.
		if(!$this->linkedin_account){
			echo 'No linkedin account linked, please try again';
			return;
		}

		$api = $this->linkedin_account->get_api();

		$linkedin_group_id = $this->get('group_id');
		if(!$linkedin_group_id){
			echo 'No linkedin group id found';
			return;
		}

		if($debug)echo "Getting the latest data for group: ".$linkedin_group_id."<br>";

		// we keep a record of the last message received so we know where to stop checking in the FB feed
		$last_message_received = (int)$this->get('last_message');

		//if($debug)echo "The last message we received for this group was on: ".shub_print_date($last_message_received,true).'<br>';

		$newest_message_received = 0;

//		$api_result = $api->api('v1/groups/'.$linkedin_group_id.'/posts?order=recency&category=discussion');
		$api_result = $api->api('v1/people/~/group-memberships/'.$linkedin_group_id.'/posts' , array(
			'order' => 'recency',
			'role' => 'creator',
			'category' => 'discussion'
		));
		if($debug){
			echo "API Result: <pre>";
			print_r($api_result);
			echo '</pre>';
		}

		$count = 0;
		if(isset($api_result['values']) && is_array($api_result['values'])){
			foreach($api_result['values'] as $group_message){
				if(!$group_message['id'])continue;

				// check if we have this message in our database already.
				$linkedin_message = new shub_linkedin_message($this->linkedin_account, $this, false);
				if($linkedin_message -> load_by_linkedin_id($group_message['id'], $group_message, 'group_post', $debug)){
					// already have this group post in our database, so skip this and any future ones (which we assume are already in our database too)
					break;
				}
				// must be new
				$count++;
				if($debug) {
					?>
					<div>
					<pre> Imported Message: <?php echo $linkedin_message->get( 'linkedin_id' ); ?>
						<?php print_r( $linkedin_message->get( 'data' ) ); ?>
					</pre>
					</div>
				<?php
				}
			}
		}
		if($debug)echo " got $count new posts <br>";


		$this->update('last_checked',time());
	}

	public function link_refresh(){
		return 'admin.php?page=support_hub_settings&tab=linkedin&manualrefresh&shub_linkedin_id='.$this->get('shub_linkedin_id').'&linkedin_group_id='.$this->get('group_id');
	}


}
