<?php

class shub_envato_group{

	public function __construct($envato_account = false, $shub_envato_group_id = false){
		$this->envato_account = $envato_account;
		$this->load($shub_envato_group_id);
	}

	/* @var $envato_account shub_envato_account */
	private $envato_account = false;
	private $shub_envato_group_id = false; // the current user id in our system.
    private $details = array();

	private function reset(){
		$this->shub_envato_group_id = false;
		$this->details = array(
			'shub_envato_group_id' => '',
			'shub_envato_id' => '',
			'group_name' => '',
			'last_message' => '',
			'last_checked' => '',
			'group_id' => '',
			'envato_token' => '',
		);
		foreach($this->details as $field_id => $field_data){
			$this->{$field_id} = '';
		}
	}

	public function create_new(){
		$this->reset();
		$this->shub_envato_group_id = shub_update_insert('shub_envato_group_id',false,'shub_envato_group',array());
		$this->load($this->shub_envato_group_id);
	}

    public function load($shub_envato_group_id = false){
	    if(!$shub_envato_group_id)$shub_envato_group_id = $this->shub_envato_group_id;
	    $this->reset();
	    $this->shub_envato_group_id = $shub_envato_group_id;
        if($this->shub_envato_group_id){
	        $data = shub_get_single('shub_envato_group','shub_envato_group_id',$this->shub_envato_group_id);
	        foreach($this->details as $key=>$val){
		        $this->details[$key] = $data && isset($data[$key]) ? $data[$key] : $val;
	        }
	        if(!is_array($this->details) || $this->details['shub_envato_group_id'] != $this->shub_envato_group_id){
		        $this->reset();
		        return false;
	        }
        }
        foreach($this->details as $key=>$val){
            $this->{$key} = $val;
        }
        return $this->shub_envato_group_id;
    }

	public function get($field){
		return isset($this->{$field}) ? $this->{$field} : false;
	}

    public function update($field,$value){
	    // what fields to we allow? or not allow?
	    if(in_array($field,array('shub_envato_group_id')))return;
        if($this->shub_envato_group_id){
            $this->{$field} = $value;
            shub_update_insert('shub_envato_group_id',$this->shub_envato_group_id,'shub_envato_group',array(
	            $field => $value,
            ));
        }
    }
	public function delete(){
		if($this->shub_envato_group_id) {
			// delete all the messages for this twitter account.
			$messages = shub_get_multiple('shub_envato_message',array(
				'shub_envato_group_id' => $this->shub_envato_group_id,
			),'shub_envato_message_id');
			foreach($messages as $message){
				if($message && isset($message['shub_envato_group_id']) && $message['shub_envato_group_id'] == $this->shub_envato_group_id){
					shub_delete_from_db( 'shub_envato_message', 'shub_envato_message_id', $message['shub_envato_message_id'] );
					shub_delete_from_db( 'shub_envato_message_link', 'shub_envato_message_id', $message['shub_envato_message_id'] );
					shub_delete_from_db( 'shub_envato_message_read', 'shub_envato_message_id', $message['shub_envato_message_id'] );
				}
			}
			shub_delete_from_db( 'shub_envato_group', 'shub_envato_group_id', $this->shub_envato_group_id );
		}
	}

	public function get_messages($search=array()){
		$envato = new shub_envato();
		$search['shub_envato_group_id'] = $this->shub_envato_group_id;
		return $envato->load_all_messages($search);
		//return get_m ultiple('shub_envato_message',$search,'shub_envato_message_id','exact','last_active');
	}

	public function run_cron($debug = false){
		// find all messages that haven't been sent yet.
		$messages = $this->get_messages(array(
			'status' => _shub_MESSAGE_STATUS_PENDINGSEND,
		));
		$now = time();
		foreach($messages as $message){
			if(isset($message['message_time']) && $message['message_time'] < $now){
				$shub_envato_message = new shub_envato_message(false, $this, $message['shub_envato_message_id']);
				$shub_envato_message->send_queued($debug);
			}
		}

		$this->load_latest_group_data($debug);
	}

	/* start FB graph calls */
	public function load_latest_group_data($debug = false){
		// serialise this result into envato_data.
		if(!$this->envato_account){
			echo 'No envato account linked, please try again';
			return;
		}

		$api = $this->envato_account->get_api();

		$envato_group_id = $this->get('group_id');
		if(!$envato_group_id){
			echo 'No envato group id found';
			return;
		}

		if($debug)echo "Getting the latest data for group: ".$envato_group_id."<br>";

		// we keep a record of the last message received so we know where to stop checking in the FB feed
		$last_message_received = (int)$this->get('last_message');

		//if($debug)echo "The last message we received for this group was on: ".shub_print_date($last_message_received,true).'<br>';

		$newest_message_received = 0;

//		$api_result = $api->api('v1/groups/'.$envato_group_id.'/posts?order=recency&category=discussion');
		$api_result = $api->api('v1/people/~/group-memberships/'.$envato_group_id.'/posts' , array(
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
				$envato_message = new shub_envato_message($this->envato_account, $this, false);
				if($envato_message -> load_by_envato_id($group_message['id'], $group_message, 'group_post', $debug)){
					// already have this group post in our database, so skip this and any future ones (which we assume are already in our database too)
					break;
				}
				// must be new
				$count++;
				if($debug) {
					?>
					<div>
					<pre> Imported Message: <?php echo $envato_message->get( 'envato_id' ); ?>
						<?php print_r( $envato_message->get( 'data' ) ); ?>
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
		return 'admin.php?page=support_hub_settings&tab=envato&manualrefresh&shub_envato_id='.$this->get('shub_envato_id').'&envato_group_id='.$this->get('group_id');
	}


}
