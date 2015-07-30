<?php

class shub_facebook_account{

	public function __construct($shub_facebook_id){
		$this->load($shub_facebook_id);
	}

	private $shub_facebook_id = false; // the current user id in our system.
    private $details = array();

	/* @var $pages shub_facebook_page[] */
    private $pages = array();
	/* @var $groups shub_facebook_group[] */
    private $groups = array();

	private function reset(){
		$this->shub_facebook_id = false;
		$this->details = array(
			'shub_facebook_id' => false,
			'shub_user_id' => 0,
			'facebook_name' => false,
			'last_checked' => false,
			'facebook_data' => false,
			'facebook_app_id' => false,
			'facebook_app_secret' => false,
			'facebook_token' => false,
			'machine_id' => false,
			'import_personal' => false,
			'last_message' => false,
		);
	    $this->pages = array();
	    $this->groups = array();
		foreach($this->details as $field_id => $field_data){
			$this->{$field_id} = '';
		}
	}

	public function create_new(){
		$this->reset();
		$this->shub_facebook_id = shub_update_insert('shub_facebook_id',false,'shub_facebook',array());
		$this->load($this->shub_facebook_id);
	}

    public function load($shub_facebook_id = false){
	    if(!$shub_facebook_id)$shub_facebook_id = $this->shub_facebook_id;
	    $this->reset();
	    $this->shub_facebook_id = (int)$shub_facebook_id;
        if($this->shub_facebook_id){
            $data = shub_get_single('shub_facebook','shub_facebook_id',$this->shub_facebook_id);
	        foreach($this->details as $key=>$val){
		        $this->details[$key] = $data && isset($data[$key]) ? $data[$key] : $val;
	        }
	        if(!is_array($this->details) || $this->details['shub_facebook_id'] != $this->shub_facebook_id){
		        $this->reset();
		        return false;
	        }
        }
        foreach($this->details as $key=>$val){
            $this->{$key} = $val;
        }
	    $this->pages = array();
	    if(!$this->shub_facebook_id)return false;
	    foreach(shub_get_multiple('shub_facebook_page',array('shub_facebook_id'=>$this->shub_facebook_id),'shub_facebook_page_id') as $page){
		    $page = new shub_facebook_page($this, $page['shub_facebook_page_id']);
		    $this->pages[$page->get('page_id')] = $page;
	    }
	    $this->groups = array();
	    if(!$this->shub_facebook_id)return false;
	    foreach(shub_get_multiple('shub_facebook_group',array('shub_facebook_id'=>$this->shub_facebook_id),'shub_facebook_group_id') as $group){
		    $group = new shub_facebook_group($this, $group['shub_facebook_group_id']);
		    $this->groups[$group->get('group_id')] = $group;
	    }
        return $this->shub_facebook_id;
    }

	public function get($field){
		return isset($this->{$field}) ? $this->{$field} : false;
	}

	public function save_data($post_data){
		if(!$this->get('shub_facebook_id')){
			$this->create_new();
		}
		if(!isset($post_data['import_personal'])){
			$post_data['import_personal'] = 0;
		}
		if(is_array($post_data)){
			foreach($this->details as $details_key => $details_val){
				if(isset($post_data[$details_key])){
					$this->update($details_key,$post_data[$details_key]);
				}
			}
		}
		// save the active facebook pages.
		if(isset($post_data['save_facebook_pages']) && $post_data['save_facebook_pages'] == 'yep') {
			$currently_active_pages = $this->pages;
			$data = @json_decode($this->get('facebook_data'),true);
			$available_pages = isset($data['pages']) && is_array($data['pages']) ? $data['pages'] : array();
			if(isset($post_data['facebook_page']) && is_array($post_data['facebook_page'])){
				foreach($post_data['facebook_page'] as $facebook_page_id => $yesno){
					if(isset($currently_active_pages[$facebook_page_id])){
						unset($currently_active_pages[$facebook_page_id]);
					}
					if($yesno && isset($available_pages[$facebook_page_id])){
						// we are adding this page to the list. check if it doesn't already exist.
						if(!isset($this->pages[$facebook_page_id])){
							$page = new shub_facebook_page($this);
							$page->create_new();
							$page->update('shub_facebook_id', $this->shub_facebook_id);
							$page->update('facebook_token', $available_pages[$facebook_page_id]['access_token']);
							$page->update('page_name', $available_pages[$facebook_page_id]['name']);
							$page->update('page_id', $facebook_page_id);
						}
					}
				}
			}
			// remove any pages that are no longer active.
			foreach($currently_active_pages as $page){
				$page->delete();
			}
		}
		// save the active facebook groups.
		if(isset($post_data['save_facebook_groups']) && $post_data['save_facebook_groups'] == 'yep') {
			$currently_active_groups = $this->groups;
			$data = @json_decode($this->get('facebook_data'),true);
			$available_groups = isset($data['groups']) && is_array($data['groups']) ? $data['groups'] : array();
			if(isset($post_data['facebook_group']) && is_array($post_data['facebook_group'])){
				foreach($post_data['facebook_group'] as $facebook_group_id => $yesno){
					if(isset($currently_active_groups[$facebook_group_id])){
						unset($currently_active_groups[$facebook_group_id]);
					}
					if($yesno && isset($available_groups[$facebook_group_id])){
						// we are adding this group to the list. check if it doesn't already exist.
						if(!isset($this->groups[$facebook_group_id])){
							$group = new shub_facebook_group($this);
							$group->create_new();
							$group->update('shub_facebook_id', $this->shub_facebook_id);
							$group->update('administrator', $available_groups[$facebook_group_id]['administrator']);
							$group->update('group_name', $available_groups[$facebook_group_id]['name']);
							$group->update('group_id', $facebook_group_id);
						}
					}
				}
			}
			// remove any groups that are no longer active.
			foreach($currently_active_groups as $group){
				$group->delete();
			}
		}
		$this->load();
		return $this->get('shub_facebook_id');
	}
    public function update($field,$value){
	    // what fields to we allow? or not allow?
	    if(in_array($field,array('shub_facebook_id')))return;
        if($this->shub_facebook_id){
            $this->{$field} = $value;
            shub_update_insert('shub_facebook_id',$this->shub_facebook_id,'shub_facebook',array(
	            $field => $value,
            ));
        }
    }
	public function delete(){
		if($this->shub_facebook_id) {
			// delete all the pages for this facebook account.
			$pages = $this->get('pages');
			foreach($pages as $page){
				$page->delete();
			}
			// delete all the groups for this facebook account.
			$groups = $this->get('groups');
			foreach($groups as $group){
				$group->delete();
			}
			shub_delete_from_db( 'shub_facebook', 'shub_facebook_id', $this->shub_facebook_id );
		}
	}

	public function is_active(){
		// is there a 'last_checked' date?
		if(!$this->get('last_checked')){
			return false; // never checked this account, not active yet.
		}else{
			// do we have a token?
			if($this->get('facebook_token')){
				// assume we have access, we remove the token if we get a facebook failure at any point.
				return true;
			}
		}
		return false;
	}

	public function is_page_active($facebook_page_id){
		if(isset($this->pages[$facebook_page_id]) && $this->pages[$facebook_page_id]->get('page_id') == $facebook_page_id && $this->pages[$facebook_page_id]->get('facebook_token')){
			return true;
		}else{
			return false;
		}
	}

	public function is_group_active($facebook_group_id){
		if(isset($this->groups[$facebook_group_id]) && $this->groups[$facebook_group_id]->get('group_id') == $facebook_group_id){
			return true;
		}else{
			return false;
		}
	}

	/* start FB graph calls */
	public function graph_load_available_pages( $debug = false ){
		// serialise this result into facebook_data.

		$access_token = $this->get('facebook_token');
		$machine_id = $this->get('machine_id');
		if(!$access_token)return;
		// grab the users details.
		$url = 'https://graph.facebook.com/me?access_token='.$access_token.'';
		if($machine_id){
		    $url .= '&machine_id='.$machine_id;
		}
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
		$data = curl_exec($ch);
		$facebook_account_data = @json_decode($data,true);
		if(!$facebook_account_data || !$facebook_account_data['id']){
			die('Failed to get facebook user account: '.$data);
		}
		$facebook_user_id = $facebook_account_data['id'];
		$facebook_user_name = isset($facebook_account_data['name']) ? $facebook_account_data['name'] : '';

		//echo "Hello $facebook_user_id - $facebook_user_name <br>";
		// get list of pages we hav eaccess to:
		$url = 'https://graph.facebook.com/'.$facebook_user_id.'/accounts?access_token='.$access_token.'';
		if($machine_id){
		    $url .= '&machine_id='.$machine_id;
		}
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
		$data = curl_exec($ch);
		$result = @json_decode($data,true);
		$pages = array();
		do {
			$go = false;
			if($debug){
				echo "Found ".count($result['data']).' entries to process <br>';
			}
			if($result && $result['data']){
				foreach ( $result['data'] as $page ) {
					if($debug){
						echo "Found page '".$page['name']."'. Adding...<br>";
					}
					$pages[ $page['id'] ] = array(
						'name'         => $page['name'],
						'access_token' => $page['access_token'],
					);
				}
				if(isset($result['paging']) && isset($result['paging']['next'])){
					$go = true;
					$url = $result['paging']['next'];
					if($machine_id){
					    $url .= '&machine_id='.$machine_id;
					}
					$ch = curl_init($url);
					curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
					$data = curl_exec($ch);
					$result = @json_decode($data,true);
				}
			}
		}while($go);
		//print_r($pages);

		$facebook_data = @json_decode($this->get('facebook_data'),true);
		if(!is_array($facebook_data))$facebook_data=array();
		$facebook_data['me'] = $facebook_account_data;
		$facebook_data['pages'] = $pages;
		$this->update('facebook_data',json_encode($facebook_data));
		$this->update('last_checked',time());
	}

	/* start FB graph calls */
	public function graph_load_available_groups( $debug = false ){
		// serialise this result into facebook_data.

		$access_token = $this->get('facebook_token');
		$machine_id = $this->get('machine_id');
		if(!$access_token)return;
		// grab the users details.
		$url = 'https://graph.facebook.com/me?access_token='.$access_token.'';
		if($machine_id){
		    $url .= '&machine_id='.$machine_id;
		}
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
		$data = curl_exec($ch);
		$facebook_account_data = @json_decode($data,true);
		if(!$facebook_account_data || !$facebook_account_data['id']){
			die('Failed to get facebook user account: '.$data);
		}
		$facebook_user_id = $facebook_account_data['id'];
		$facebook_user_name = isset($facebook_account_data['name']) ? $facebook_account_data['name'] : '';

		//echo "Hello $facebook_user_id - $facebook_user_name <br>";
		// get list of groups we hav eaccess to:
		$url = 'https://graph.facebook.com/'.$facebook_user_id.'/groups?access_token='.$access_token.'';
		if($machine_id){
		    $url .= '&machine_id='.$machine_id;
		}
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
		$data = curl_exec($ch);
		$result = @json_decode($data,true);
		$groups = array();
		do {
			$go = false;
			if($debug){
				echo "Found ".count($result['data']).' entries to process <br>';
			}
			if($result && $result['data']){
				foreach ( $result['data'] as $group ) {
					if($group['administrator']) {
						// only show groups we are administrator for.
						if($debug){
							echo "Found group '".$group['name']."' that we are administrator for. Adding...<br>";
						}
						$groups[ $group['id'] ] = array(
							'name'          => $group['name'],
							'administrator' => $group['administrator'],
						);
					}
				}
				if(isset($result['paging']) && isset($result['paging']['next'])){
					$go = true;
					$url = $result['paging']['next'];
					if($machine_id){
					    $url .= '&machine_id='.$machine_id;
					}
					$ch = curl_init($url);
					curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
					$data = curl_exec($ch);
					$result = @json_decode($data,true);
				}
			}
		}while($go);
		//print_r($groups);
		$facebook_data = @json_decode($this->get('facebook_data'),true);
		if(!is_array($facebook_data))$facebook_data=array();
		$facebook_data['me'] = $facebook_account_data;
		$facebook_data['groups'] = $groups;
		$this->update('facebook_data',json_encode($facebook_data));
		$this->update('last_checked',time());
	}


	public function load_latest_feed_data($debug = false){
		$access_token = $this->get('facebook_token');
		// get the machine id from the parent facebook_account
		$machine_id = $this->get('machine_id');
		if(!$access_token){
			echo 'No access token for facebook found, please login again';
			return;
		}

		if($debug)echo "Getting the latest feed data for personal FB account:<br>";

		// we keep a record of the last message received so we know where to stop checking in the FB feed
		$last_message_received = (int)$this->get('last_message');

		if($debug)echo "The last message we received for this group was on: ".shub_print_date($last_message_received,true).'<br>';

		$newest_message_received = 0;

		if($debug)echo "Getting /feed personal posts... <br>";
		$facebook_api = new shub_facebook();
		$personal_feed = $facebook_api->graph('/me/feed',array(
			'access_token' => $access_token,
			'machine_id' => $machine_id,
		));
		$count = 0;
		if(isset($personal_feed['error']) && !empty($personal_feed['error'])){
            if($debug)echo " FACEBOOK ERROR : ".$personal_feed['error']['message'] ."<br>";
        }
		if(isset($personal_feed['data']) && !empty($personal_feed['data'])){
			foreach($personal_feed['data'] as $personal_feed_message){
				if(!$personal_feed_message['id'])continue;
				$message_time = strtotime(isset($personal_feed_message['updated_time']) && strlen($personal_feed_message['updated_time']) ? $personal_feed_message['updated_time'] : $personal_feed_message['created_time']);
				$newest_message_received = max($message_time, $newest_message_received);
				if($last_message_received && $last_message_received >= $message_time){
					// we've already processed messages after this time.
					if($debug)echo " - Skipping this message (".$personal_feed_message['id'].") because it was received on ".shub_print_date($message_time,true).' and we only want ones after '.shub_print_date($last_message_received,true).'<br>';
					break;
				}else{
					if($debug)echo ' - storing message received on '.shub_print_date($message_time,true).'<br>';
				}
				// check if we have this message in our database already.
				$facebook_message = new shub_facebook_message($this, false, false);
				if($facebook_message -> load_by_facebook_id($personal_feed_message['id'], $personal_feed_message, 'feed')){
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


		if($debug)echo "The last message we received for this group was now on: ".shub_print_date($newest_message_received,true).'<br>';
		if($debug)echo "Finished checking this group messages at ".shub_print_date(time(),true)."<br>";

		$this->update('last_message',$newest_message_received);
		$this->update('last_checked',time());
	}

	/**
	 * Links for wordpress
	 */
	public function link_connect(){
		return 'admin.php?page=support_hub_settings&tab=facebook&fbconnect&shub_facebook_id='.$this->get('shub_facebook_id');
	}
	public function link_edit(){
		return 'admin.php?page=support_hub_settings&tab=facebook&shub_facebook_id='.$this->get('shub_facebook_id');
	}
	public function link_new_message(){
		return 'admin.php?page=support_hub_main&shub_facebook_id='.$this->get('shub_facebook_id').'&shub_facebook_message_id=new';
	}

	public function link_refresh(){
		return 'admin.php?page=support_hub_settings&tab=facebook&manualrefresh&shub_facebook_id='.$this->get('shub_facebook_id').'&facebook_feed=true';
	}
	public function link_refresh_groups(){
		return 'admin.php?page=support_hub_settings&tab=facebook&manualrefresh&shub_facebook_id='.$this->get('shub_facebook_id').'&refresh_data=groups';
	}
	public function link_refresh_pages(){
		return 'admin.php?page=support_hub_settings&tab=facebook&manualrefresh&shub_facebook_id='.$this->get('shub_facebook_id').'&refresh_data=pages';
	}

}
