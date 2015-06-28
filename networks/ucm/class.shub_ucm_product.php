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
		$this->shub_ucm_product_id = shub_update_insert('shub_ucm_product_id',false,'shub_ucm_product',array());
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

		// first we seed the cache with the latest ucm replies and topics
		// we do this because it's not possible to filter based on "post_parent" through the WordPress API (SILLY!)
		// so this saves us calling getPost() a lot of times.
		$filter_replies = array(
			'post_type' => 'reply',
			'number' => 100,
			'post_status' => 'publish',
			//'post_parent' =>
		);
		$api_result_latest_replies = $this->ucm_account->get_api_cache($filter_replies);
		$api_result_latest_replies = $api_result_latest_replies ? $api_result_latest_replies : $api->getPosts($filter_replies);

		$filter_topics = array(
			'post_type' => 'topic',
			'number' => 100,
			'post_status' => 'publish',
			//'post_parent' =>
		);
		$api_result_latest_topics = $this->ucm_account->get_api_cache($filter_topics);
		$api_result_latest_topics = $api_result_latest_topics ? $api_result_latest_topics : $api->getPosts($filter_topics);


		// loop through our latest replies and see if any of them are from a thread that sits under this product
		// COMPLETELY THE REVERSE WAY THAT WE SHOULD BE DOING IT! rar!

		$product_topics = array();

		foreach($api_result_latest_topics as $product_topic){
			if($product_topic['post_parent'] == $ucm_product_id){
				// yay! this reply is part of a topic that is part of this product. keep it.
				if(!isset($product_topics[$product_topic['post_id']])){
					$product_topics[$product_topic['post_id']] = $product_topic;
				}
				if(!isset($product_topics[$product_topic['post_id']]['replies'])){
					$product_topics[$product_topic['post_id']]['replies'] = array();
				}
				$product_topics[$product_topic['post_id']]['timestamp'] = $product_topic['post_date']->timestamp;
			}
		}
		foreach($api_result_latest_replies as $product_reply){

			// find its parent and see if it is from this product.
			$found_parent = false;
			foreach($api_result_latest_topics as $product_topic){
				if($product_topic['post_id'] == $product_reply['post_parent']){
					$found_parent = $product_topic;
					break;
				}
			}
			if(!$found_parent){
				$api_result_parent = $api->getPost($product_reply['post_parent']);
				if($api_result_parent){
					$found_parent = $api_result_parent;
					$api_result_latest_topics[] = $api_result_parent; // add to cache so we hopefully dont have to hit it again if it's a popular topic
				}
			}
			if($found_parent){
				// found a parent post, check if it's part of this product.
				if($found_parent['post_parent'] == $ucm_product_id){
					// yay! this reply is part of a topic that is part of this product. keep it.
					if(!isset($product_topics[$found_parent['post_id']])){
						$product_topics[$found_parent['post_id']] = $found_parent;
					}
					if(!isset($product_topics[$found_parent['post_id']]['replies'])){
						$product_topics[$found_parent['post_id']]['replies'] = array();
					}
					$product_topics[$found_parent['post_id']]['replies'][] = $product_reply;
					if(!isset($product_topics[$found_parent['post_id']]['timestamp'])){
						$product_topics[$found_parent['post_id']]['timestamp'] = $found_parent['post_date']->timestamp;
					}
					$product_topics[$found_parent['post_id']]['timestamp'] = max($product_reply['post_date']->timestamp,$product_topics[$found_parent['post_id']]['timestamp']);
				}

				/*echo date('Y-m-d',$product_reply['post_date']->timestamp);
				echo " <a href='".$product_reply['link']."'>'".$product_reply['link'].'</a> ';
				echo $product_reply['post_content'];
				echo "Parent is: ";
				echo date('Y-m-d',$found_parent['post_date']->timestamp);
				echo " <a href='".$found_parent['link']."'>'".$found_parent['link'].'</a> ';
				echo '<hr>';*/
			}
		}
		uasort($product_topics,function($a,$b){
			return $a['timestamp'] < $b['timestamp'];
		});

		// cache them for any other ucm product calls that are run during the same cron job process.
		$this->ucm_account->set_api_cache($filter_replies,$api_result_latest_replies);
		$this->ucm_account->set_api_cache($filter_topics,$api_result_latest_topics);


		// we keep a record of the last message received so we know where to stop checking the feed
		$last_message_received = (int)$this->get('last_message');
		if($debug)echo "Getting the latest replies for product: ".$ucm_product_id." (last message in database is from ".shub_print_date($last_message_received,true).")<br>\n";

		$newest_message_received = 0;

		$count = 0;
		foreach($product_topics as $product_topic){
			$message_time = $product_topic['timestamp'];
			$newest_message_received = max($newest_message_received,$message_time);
			if($message_time <= $last_message_received)break; // all done here.

			$ucm_message = new shub_ucm_message($this->ucm_account, $this, false);
			$ucm_message -> load_by_ucm_id($product_topic['post_id'], $product_topic, 'product_topic', $debug);
			$count++;
			if($debug) {
				?>
				<div>
				<pre> Imported product topic ID: <?php echo $ucm_message->get( 'ucm_id' ); ?> with <?php echo count($product_topic['replies']);?> replies. </pre>
				</div>
			<?php
			}

		}
		// get user, return envato_codes in meta
		SupportHub::getInstance()->log_data(_SUPPORT_HUB_LOG_INFO, 'ucm', 'Imported  '.$count.' product topics into database');
		if($debug)echo " imported $count new product comments <br>";

		$this->update('last_message',$newest_message_received);
		$this->update('last_checked',time());
	}

	public function link_refresh(){
		return 'admin.php?page=support_hub_settings&tab=ucm&manualrefresh&shub_ucm_id='.$this->get('shub_ucm_id').'&ucm_product_id='.$this->get('product_id');
	}


}
