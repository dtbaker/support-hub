<?php

class shub_linkedin_account{

	public function __construct($shub_linkedin_id){
		$this->load($shub_linkedin_id);
	}

	private $shub_linkedin_id = false; // the current user id in our system.
    private $details = array();

	/* @var $groups shub_linkedin_group[] */
    private $groups = array();

	private function reset(){
		$this->shub_linkedin_id = false;
		$this->details = array(
			'shub_linkedin_id' => false,
			'linkedin_name' => false,
			'last_checked' => false,
			'linkedin_data' => false,
			'linkedin_app_id' => false,
			'linkedin_app_secret' => false,
			'linkedin_token' => false,
			'machine_id' => false,
			'import_stream' => false,
		);
	    $this->groups = array();
		foreach($this->details as $field_id => $field_data){
			$this->{$field_id} = '';
		}
	}

	public function create_new(){
		$this->reset();
		$this->shub_linkedin_id = shub_update_insert('shub_linkedin_id',false,'shub_linkedin',array());
		$this->load($this->shub_linkedin_id);
	}

    public function load($shub_linkedin_id = false){
	    if(!$shub_linkedin_id)$shub_linkedin_id = $this->shub_linkedin_id;
	    $this->reset();
	    $this->shub_linkedin_id = (int)$shub_linkedin_id;
        if($this->shub_linkedin_id){
            $data = shub_get_single('shub_linkedin','shub_linkedin_id',$this->shub_linkedin_id);
	        foreach($this->details as $key=>$val){
		        $this->details[$key] = $data && isset($data[$key]) ? $data[$key] : $val;
	        }
	        if(!is_array($this->details) || $this->details['shub_linkedin_id'] != $this->shub_linkedin_id){
		        $this->reset();
		        return false;
	        }
        }
        foreach($this->details as $key=>$val){
            $this->{$key} = $val;
        }
	    $this->groups = array();
	    if(!$this->shub_linkedin_id)return false;
	    foreach(shub_get_multiple('shub_linkedin_group',array('shub_linkedin_id'=>$this->shub_linkedin_id),'shub_linkedin_group_id') as $group){
		    $group = new shub_linkedin_group($this, $group['shub_linkedin_group_id']);
		    $this->groups[$group->get('group_id')] = $group;
	    }
        return $this->shub_linkedin_id;
    }

	public function get($field){
		return isset($this->{$field}) ? $this->{$field} : false;
	}

	public function save_data($post_data){
		if(!$this->get('shub_linkedin_id')){
			$this->create_new();
		}
		if(is_array($post_data)){
			foreach($this->details as $details_key => $details_val){
				if(isset($post_data[$details_key])){
					$this->update($details_key,$post_data[$details_key]);
				}
			}
		}
		if(!isset($post_data['import_stream'])){
			$this->update('import_stream', 0);
		}
		// save the active linkedin groups.
		if(isset($post_data['save_linkedin_groups']) && $post_data['save_linkedin_groups'] == 'yep') {
			$currently_active_groups = $this->groups;
			$data = @json_decode($this->get('linkedin_data'),true);
			$available_groups = isset($data['groups']) && is_array($data['groups']) ? $data['groups'] : array();
			if(isset($post_data['linkedin_group']) && is_array($post_data['linkedin_group'])){
				foreach($post_data['linkedin_group'] as $linkedin_group_id => $yesno){
					if(isset($currently_active_groups[$linkedin_group_id])){
						unset($currently_active_groups[$linkedin_group_id]);
					}
					if($yesno && isset($available_groups[$linkedin_group_id])){
						// we are adding this group to the list. check if it doesn't already exist.
						if(!isset($this->groups[$linkedin_group_id])){
							$group = new shub_linkedin_group($this);
							$group->create_new();
							$group->update('shub_linkedin_id', $this->shub_linkedin_id);
							$group->update('linkedin_token', 'same'); // $available_groups[$linkedin_group_id]['access_token']
							$group->update('group_name', $available_groups[$linkedin_group_id]['group']['name']);
							$group->update('group_id', $linkedin_group_id);
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
		return $this->get('shub_linkedin_id');
	}
    public function update($field,$value){
	    // what fields to we allow? or not allow?
	    if(in_array($field,array('shub_linkedin_id')))return;
        if($this->shub_linkedin_id){
            $this->{$field} = $value;
            shub_update_insert('shub_linkedin_id',$this->shub_linkedin_id,'shub_linkedin',array(
	            $field => $value,
            ));
        }
    }
	public function delete(){
		if($this->shub_linkedin_id) {
			// delete all the groups for this twitter account.
			$groups = $this->get('groups');
			foreach($groups as $group){
				$group->delete();
			}
			shub_delete_from_db( 'shub_linkedin', 'shub_linkedin_id', $this->shub_linkedin_id );
		}
	}

	public function is_active(){
		// is there a 'last_checked' date?
		if(!$this->get('last_checked')){
			return false; // never checked this account, not active yet.
		}else{
			// do we have a token?
			if($this->get('linkedin_token')){
				// assume we have access, we remove the token if we get a linkedin failure at any point.
				return true;
			}
		}
		return false;
	}

	public function is_group_active($linkedin_group_id){
		if(isset($this->groups[$linkedin_group_id]) && $this->groups[$linkedin_group_id]->get('group_id') == $linkedin_group_id && $this->groups[$linkedin_group_id]->get('linkedin_token')){
			return true;
		}else{
			return false;
		}
	}

	public function save_account_data($user_data){
		// serialise this result into linkedin_data.
		if(is_array($user_data)){
			// yes, this member has some groups, save these groups to the account ready for selection in the settings area.
			$save_data = @json_decode($this->get('linkedin_data'),true);
			if(!is_array($save_data))$save_data=array();
			$save_data = array_merge($save_data,$user_data);
			$this->update('linkedin_data',json_encode($save_data));
		}
	}

	public function load_available_groups(){
		// serialise this result into linkedin_data.

		$api = $this->get_api();
		$api_result = $api->api('v1/people/~/group-memberships');
		if($api_result && isset($api_result['values']) && is_array($api_result['values'])){
			$groups = array();
			foreach($api_result['values'] as $group){
				$groups[$group['_key']] = $group;
			}
			// yes, this member has some groups, save these groups to the account ready for selection in the settings area.
			$save_data = @json_decode($this->get('linkedin_data'),true);
			if(!is_array($save_data))$save_data=array();
			$save_data['groups'] = $groups;

			$this->update('linkedin_data',json_encode($save_data));
		}

		//$this->update('last_checked',time());
	}

	public function load_latest_stream_data( $debug = false ){
		$api = $this->get_api();
		$api_result = $api->api('v1/people/~/network',array(
			'type' => 'SHAR',
			'count' => 30, // only get the latest 30 network update message?
		));
		if($debug){
			echo "Updating stream data: \n ";
			echo isset($api_result['networkStats']) ? var_export($api_result['networkStats'],true) : '';
		}
		if($api_result && isset($api_result['updates']['values']) && is_array($api_result['updates']['values'])){
			foreach($api_result['updates']['values'] as $network_update){
				if(isset($network_update['updateKey']) && $network_update['updateKey']) {

					// skip private shares.
					if(!isset($network_update['updateContent']['person']['currentShare']) || (isset($network_update['updateContent']['person']['firstName']) && $network_update['updateContent']['person']['firstName'] == 'private'))continue;
					$share = new shub_linkedin_message( $this, false, false );
					$share->load_by_linkedin_id( $network_update['updateKey'], $network_update, 'share', $debug );
				}
			}
		}else{
			if($debug){
				echo 'No stream updates found';
			}
		}
	}

	public function run_cron( $debug = false ){

		$this->load_latest_stream_data($debug);

	}

	private static $api = false;
	public function get_api($use_db_code = true){
		if(!self::$api){

			self::$api = new Happyr\LinkedIn\LinkedIn($this->get( 'linkedin_app_id' ), $this->get( 'linkedin_app_secret' ));

			if($use_db_code){
				// user isn't logging in again, set the code from our db:
				//echo 'API set topen to '.$this->get('linkedin_token');
				self::$api->setAccessToken($this->get('linkedin_token'));
			}
		}
		return self::$api;
	}

	public function get_picture(){
		$data = @json_decode($this->get('linkedin_data'),true);
		return $data && isset($data['pictureUrl']) && !empty($data['pictureUrl']) ? $data['pictureUrl'] : false;
	}
	

	/**
	 * Links for wordpress
	 */
	public function link_connect(){
		return 'admin.php?page=support_hub_linkedin_settings&linkedin_do_oauth_connect&shub_linkedin_id='.$this->get('shub_linkedin_id');
	}
	public function link_edit(){
		return 'admin.php?page=support_hub_linkedin_settings&shub_linkedin_id='.$this->get('shub_linkedin_id');
	}
	public function link_new_message(){
		return 'admin.php?page=support_hub_main&shub_linkedin_id='.$this->get('shub_linkedin_id').'&shub_linkedin_message_id=new';
	}


	public function link_refresh(){
		return 'admin.php?page=support_hub_linkedin_settings&manualrefresh&shub_linkedin_id='.$this->get('shub_linkedin_id').'&linkedin_stream=true';
	}

}
