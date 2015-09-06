<?php

class shub_ucm_item extends SupportHub_item{


	public function run_cron($debug = false){
		// find all messages that haven't been sent yet.
		/*$messages = $this->get_messages(array(
			'shub_status' => _shub_MESSAGE_STATUS_PENDINGSEND,
		));
		$now = time();
		foreach($messages as $message){
			if(isset($message['message_time']) && $message['message_time'] < $now){
				$shub_ucm_message = new shub_ucm_message(false, $this, $message['shub_ucm_message_id']);
				$shub_ucm_message->send_queued($debug);
			}
		}*/

		$this->load_latest_item_data($debug);
	}

	public function load_latest_item_data($debug = false){
		// serialise this result into ucm_data.
		if(!$this->account){
			echo 'No ucm account linked, please try again';
			return;
		}

		$api = $this->account->get_api();

		$ucm_product_id = $this->get('network_key');
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
//        $last_message_received = false;

        SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_INFO,'ucm','Loading latest tickets for product ('.$ucm_product_id.') "'.$this->get('product_name').'" modified since '.shub_print_date($last_message_received,true));
		// find any messages from this particular UCM product that have been updated since our last scrape time.
        $tickets = $api->api('ticket','list',array('search'=>array('faq_product_id'=>$ucm_product_id,'time_from'=>$last_message_received,'status_id'=>0)));
		if($debug)echo "Getting the latest tickets for product: ".$ucm_product_id." (last message in database is from ".shub_print_date($last_message_received,true).")<br>\n";

		$newest_message_received = 0;

		$count = 0;
		foreach($tickets['tickets'] as $ticket){
			$message_time = $ticket['last_message_timestamp'];
			$newest_message_received = max($newest_message_received,$message_time);
			//if($message_time <= $last_message_received)break; // all done here.

			$ucm_message = new shub_ucm_message($this->account, $this, false);
			$ucm_message -> load_by_network_key($ticket['ticket_id'], $ticket, 'ticket', $debug);
			$count++;
			if($debug) {
				?>
				<div>
				<pre> Imported Ticket ID: <?php echo $ucm_message->get( 'network_key' ); ?> with <?php echo $ticket['message_count'];?> message. </pre>
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
        return 'admin.php?page=support_hub_settings&tab=ucm&manualrefresh&shub_account_id='.$this->get('shub_account_id').'&network_key='.$this->get('network_key');
    }


}
