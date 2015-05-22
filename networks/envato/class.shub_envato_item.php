<?php

class shub_envato_item{

	public function __construct($envato_account = false, $shub_envato_item_id = false){
		$this->envato_account = $envato_account;
		$this->load($shub_envato_item_id);
	}

	/* @var $envato_account shub_envato_account */
	private $envato_account = false;
	private $shub_envato_item_id = false; // the current user id in our system.
    private $details = array();
	private $json_fields = array('envato_data');

	private function reset(){
		$this->shub_envato_item_id = false;
		$this->details = array(
			'shub_envato_item_id' => '',
			'shub_envato_id' => '',
			'shub_product_id' => '',
			'item_name' => '',
			'last_message' => '',
			'last_checked' => '',
			'item_id' => '',
			'envato_data' => array(),
		);
		foreach($this->details as $field_id => $field_data){
			$this->{$field_id} = $field_data;
		}
	}

	public function create_new(){
		$this->reset();
		$this->shub_envato_item_id = shub_update_insert('shub_envato_item_id',false,'shub_envato_item',array());
		$this->load($this->shub_envato_item_id);
	}

    public function load($shub_envato_item_id = false){
	    if(!$shub_envato_item_id)$shub_envato_item_id = $this->shub_envato_item_id;
	    $this->reset();
	    $this->shub_envato_item_id = $shub_envato_item_id;
        if($this->shub_envato_item_id){
	        $data = shub_get_single('shub_envato_item','shub_envato_item_id',$this->shub_envato_item_id);
	        foreach($this->details as $key=>$val){
		        $this->details[$key] = $data && isset($data[$key]) ? $data[$key] : $val;
		        if(in_array($key,$this->json_fields)){
			        $this->details[$key] = @json_decode($this->details[$key],true);
			        if(!is_array($this->details[$key]))$this->details[$key] = array();
		        }
	        }
	        if(!is_array($this->details) || $this->details['shub_envato_item_id'] != $this->shub_envato_item_id){
		        $this->reset();
		        return false;
	        }
        }
        foreach($this->details as $key=>$val){
            $this->{$key} = $val;
        }
        return $this->shub_envato_item_id;
    }

	public function get($field){
		return isset($this->{$field}) ? $this->{$field} : false;
	}

    public function update($field,$value){
	    // what fields to we allow? or not allow?
	    if(in_array($field,array('shub_envato_item_id')))return;
        if($this->shub_envato_item_id){
            $this->{$field} = $value;
	        if(in_array($field,$this->json_fields)){
		        $value = json_encode($value);
	        }
            shub_update_insert('shub_envato_item_id',$this->shub_envato_item_id,'shub_envato_item',array(
	            $field => $value,
            ));
        }
    }
	public function delete(){
		if($this->shub_envato_item_id) {
			// delete all the messages for this twitter account.
			$messages = shub_get_multiple('shub_envato_message',array(
				'shub_envato_item_id' => $this->shub_envato_item_id,
			),'shub_envato_message_id');
			foreach($messages as $message){
				if($message && isset($message['shub_envato_item_id']) && $message['shub_envato_item_id'] == $this->shub_envato_item_id){
					shub_delete_from_db( 'shub_envato_message', 'shub_envato_message_id', $message['shub_envato_message_id'] );
					shub_delete_from_db( 'shub_envato_message_link', 'shub_envato_message_id', $message['shub_envato_message_id'] );
					shub_delete_from_db( 'shub_envato_message_read', 'shub_envato_message_id', $message['shub_envato_message_id'] );
				}
			}
			shub_delete_from_db( 'shub_envato_item', 'shub_envato_item_id', $this->shub_envato_item_id );
		}
	}

	public function get_messages($search=array()){
		$envato = new shub_envato();
		$search['shub_envato_item_id'] = $this->shub_envato_item_id;
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

		$this->load_latest_item_data($debug);
	}

	public function load_latest_item_data($debug = false){
		// serialise this result into envato_data.
		if(!$this->envato_account){
			echo 'No envato account linked, please try again';
			return;
		}

		$api = $this->envato_account->get_api();

		$envato_item_id = $this->get('item_id');
		if(!$envato_item_id){
			echo 'No envato item id found';
			return;
		}

		// we keep a record of the last message received so we know where to stop checking the feed
		$last_message_received = (int)$this->get('last_message');
		if($debug)echo "Getting the latest 20 comments for item: ".$envato_item_id." (last message in database is from ".shub_print_date($last_message_received,true).")<br>\n";

		$newest_message_received = 0;

		$endpoint = 'discovery/search/search/comment?term=&item_id='.$envato_item_id.'&sort_by=newest&page_size=20';
		$api_result = $api->api($endpoint);
		if($debug){
			echo "API Result took :".$api_result['took'].' seconds and produced '.count($api_result['matches']).' results';
		}

		$count = 0;
		if(isset($api_result['matches']) && is_array($api_result['matches'])){
			foreach($api_result['matches'] as $item_message){
				if(!$item_message['id'])continue;
				$message_time = strtotime($item_message['last_comment_at']);
				$newest_message_received = max($newest_message_received,$message_time);
				if($message_time <= $last_message_received)break; // all done here.

				// check if we have this message in our database already.
				$envato_message = new shub_envato_message($this->envato_account, $this, false);
				$envato_message -> load_by_envato_id($item_message['id'], $item_message, 'item_comment', $debug);
				$count++;
				if($debug) {
					?>
					<div>
					<pre> Imported message ID: <?php echo $envato_message->get( 'envato_id' ); ?> </pre>
					</div>
				<?php
				}
			}
		}
		SupportHub::getInstance()->log_data(0, 'envato', 'Imported  '.$count.' new messages into database');
		if($debug)echo " imported $count new item comments <br>";

		$this->update('last_message',$newest_message_received);
		$this->update('last_checked',time());
	}

	public function link_refresh(){
		return 'admin.php?page=support_hub_settings&tab=envato&manualrefresh&shub_envato_id='.$this->get('shub_envato_id').'&envato_item_id='.$this->get('item_id');
	}


}
