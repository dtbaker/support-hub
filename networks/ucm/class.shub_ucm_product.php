<?php

class shub_ucm_product{

	public function __construct($ucm_account = false, $shub_ucm_product_id = false){
		$this->ucm_account = $ucm_account;
		$this->load($shub_ucm_product_id);
	}

	/* @var $ucm_account shub_ucm_account */
	private $ucm_account = false;
	private $shub_ucm_product_id = false; // the current user id in our system.
    private $details = array();
	private $json_fields = array('ucm_data');

	private function reset(){
		$this->shub_ucm_product_id = false;
		$this->details = array(
			'shub_ucm_product_id' => '',
			'shub_ucm_id' => '',
			'shub_product_id' => '',
			'product_name' => '',
			'last_message' => '',
			'last_checked' => '',
			'product_id' => '',
			'ucm_data' => array(),
		);
		foreach($this->details as $field_id => $field_data){
			$this->{$field_id} = $field_data;
		}
	}

	public function create_new(){
		$this->reset();
		$this->shub_ucm_product_id = shub_update_insert('shub_ucm_product_id',false,'shub_ucm_product',array(
            'product_name' => '',
        ));
		$this->load($this->shub_ucm_product_id);
	}

    public function load($shub_ucm_product_id = false){
	    if(!$shub_ucm_product_id)$shub_ucm_product_id = $this->shub_ucm_product_id;
	    $this->reset();
	    $this->shub_ucm_product_id = $shub_ucm_product_id;
        if($this->shub_ucm_product_id){
	        $data = shub_get_single('shub_ucm_product','shub_ucm_product_id',$this->shub_ucm_product_id);
	        foreach($this->details as $key=>$val){
		        $this->details[$key] = $data && isset($data[$key]) ? $data[$key] : $val;
		        if(in_array($key,$this->json_fields)){
			        $this->details[$key] = @json_decode($this->details[$key],true);
			        if(!is_array($this->details[$key]))$this->details[$key] = array();
		        }
	        }
	        if(!is_array($this->details) || $this->details['shub_ucm_product_id'] != $this->shub_ucm_product_id){
		        $this->reset();
		        return false;
	        }
        }
        foreach($this->details as $key=>$val){
            $this->{$key} = $val;
        }
        return $this->shub_ucm_product_id;
    }

	public function get($field){
		return isset($this->{$field}) ? $this->{$field} : false;
	}

    public function update($field,$value){
	    // what fields to we allow? or not allow?
	    if(in_array($field,array('shub_ucm_product_id')))return;
        if($this->shub_ucm_product_id){
            $this->{$field} = $value;
	        if(in_array($field,$this->json_fields)){
		        $value = json_encode($value);
	        }
            shub_update_insert('shub_ucm_product_id',$this->shub_ucm_product_id,'shub_ucm_product',array(
	            $field => $value,
            ));
        }
    }
	public function delete(){
		if($this->shub_ucm_product_id) {
			// delete all the messages for this twitter account.
			$messages = shub_get_multiple('shub_ucm_message',array(
				'shub_ucm_product_id' => $this->shub_ucm_product_id,
			),'shub_ucm_message_id');
			foreach($messages as $message){
				if($message && isset($message['shub_ucm_product_id']) && $message['shub_ucm_product_id'] == $this->shub_ucm_product_id){
					shub_delete_from_db( 'shub_ucm_message', 'shub_ucm_message_id', $message['shub_ucm_message_id'] );
					shub_delete_from_db( 'shub_ucm_message_comment', 'shub_ucm_message_id', $message['shub_ucm_message_id'] );
					shub_delete_from_db( 'shub_ucm_message_link', 'shub_ucm_message_id', $message['shub_ucm_message_id'] );
					shub_delete_from_db( 'shub_ucm_message_read', 'shub_ucm_message_id', $message['shub_ucm_message_id'] );
				}
			}
			shub_delete_from_db( 'shub_ucm_product', 'shub_ucm_product_id', $this->shub_ucm_product_id );
		}
	}

	public function get_messages($search=array()){
		$ucm = new shub_ucm();
		$search['shub_ucm_product_id'] = $this->shub_ucm_product_id;
		return $ucm->load_all_messages($search);
		//return get_m ultiple('shub_ucm_message',$search,'shub_ucm_message_id','exact','last_active');
	}

	public function run_cron($debug = false){
		// find all messages that haven't been sent yet.
		$messages = $this->get_messages(array(
			'status' => _shub_MESSAGE_STATUS_PENDINGSEND,
		));
		$now = time();
		foreach($messages as $message){
			if(isset($message['message_time']) && $message['message_time'] < $now){
				$shub_ucm_message = new shub_ucm_message(false, $this, $message['shub_ucm_message_id']);
				$shub_ucm_message->send_queued($debug);
			}
		}

		$this->load_latest_product_data($debug);
	}

	public function load_latest_product_data($debug = false){
		// serialise this result into ucm_data.
		if(!$this->ucm_account){
			echo 'No ucm account linked, please try again';
			return;
		}

		$api = $this->ucm_account->get_api();

		$ucm_product_id = $this->get('product_id');
		if(!$ucm_product_id){
			echo 'No ucm product id found';
			return;
		}

        // we keep a record of the last message received so we know where to stop checking the feed
        $last_message_received = (int)$this->get('last_message');

        // dont want to import ALL tickets, so we pick a 20 day limit if we haven't done this yet
        if(!$last_message_received){
            $last_message_received = strtotime('-20 days');
        }

        SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_INFO,'ucm','Loading latest tickets for product ('.$ucm_product_id.') "'.$this->get('product_name').'" modified since '.shub_print_date($last_message_received,true));
		// find any messages from this particular UCM product that have been updated since our last scrape time.
        $tickets = $api->api('ticket','list',array('search'=>array('faq_product_id'=>$ucm_product_id,'time_from'=>$last_message_received,'status_id'=>0)));
        if($ucm_product_id==20){

            print_r($tickets);
        }
		if($debug)echo "Getting the latest tickets for product: ".$ucm_product_id." (last message in database is from ".shub_print_date($last_message_received,true).")<br>\n";

		$newest_message_received = 0;

		$count = 0;
		foreach($tickets['tickets'] as $ticket){
			$message_time = $ticket['last_message_timestamp'];
			$newest_message_received = max($newest_message_received,$message_time);
			//if($message_time <= $last_message_received)break; // all done here.

			$ucm_message = new shub_ucm_message($this->ucm_account, $this, false);
			$ucm_message -> load_by_ucm_id($ticket['ticket_id'], $ticket, 'ticket', $debug);
			$count++;
			if($debug) {
				?>
				<div>
				<pre> Imported Ticket ID: <?php echo $ucm_message->get( 'ucm_id' ); ?> with <?php echo $ticket['message_count'];?> message. </pre>
				</div>
			<?php
			}

		}
		// get user, return envato_codes in meta
		SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_INFO, 'ucm', 'Imported  '.$count.' product tickets into database (from a total of '.count($tickets['tickets']).' returned by the api)');
		if($debug)echo " imported $count new product tickets <br>";

		$this->update('last_message',$newest_message_received);
		$this->update('last_checked',time());
	}

	public function link_refresh(){
		return 'admin.php?page=support_hub_settings&tab=ucm&manualrefresh&shub_ucm_id='.$this->get('shub_ucm_id').'&ucm_product_id='.$this->get('product_id');
	}


}
