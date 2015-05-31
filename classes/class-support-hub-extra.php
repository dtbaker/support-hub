<?php



class SupportHubExtra{

	public function __construct($shub_extra_id = false){
		if($shub_extra_id){
			$this->load($shub_extra_id);
		}
	}

	private $shub_extra_id = false; // the current extra id in our system.
    private $details = array();
	private $json_fields = array();

	public $edit_link = ''; // for the table

	private function reset(){
		$this->shub_extra_id = false;
		$this->details = array(
			'shub_extra_id' => '',
			'extra_name' => '',
			'extra_description' => '',
			'extra_order' => 0,
			'extra_required' => 0,
		);
		foreach($this->details as $field_id => $field_data){
			$this->{$field_id} = $field_data;
		}
	}

	public function create_new(){
		$this->reset();
		$this->shub_extra_id = shub_update_insert('shub_extra_id',false,'shub_extra',array());
		$this->load($this->shub_extra_id);
	}


	public function load_by($field, $value){
		$this->reset();
		if(!empty($field) && !empty($value) && isset($this->details[$field])){
			$data = shub_get_single('shub_extra',$field,$value);
			if($data && isset($data[$field]) && $data[$field] == $value && $data['shub_extra_id']){
				$this->load($data['shub_extra_id']);
				return true;
			}
		}
		return false;
	}

    public function load($shub_extra_id = false){
	    if(!$shub_extra_id)$shub_extra_id = $this->shub_extra_id;
	    $this->reset();
	    $this->shub_extra_id = $shub_extra_id;
        if($this->shub_extra_id){
	        $data = shub_get_single('shub_extra','shub_extra_id',$this->shub_extra_id);
	        foreach($this->details as $key=>$val){
		        $this->details[$key] = $data && isset($data[$key]) ? $data[$key] : $val;
		        if(in_array($key,$this->json_fields)){
			        $this->details[$key] = @json_decode($this->details[$key],true);
			        if(!is_array($this->details[$key]))$this->details[$key] = array();
		        }
	        }
	        if(!is_array($this->details) || $this->details['shub_extra_id'] != $this->shub_extra_id){
		        $this->reset();
		        return false;
	        }
        }
        foreach($this->details as $key=>$val){
            $this->{$key} = $val;
        }
        return $this->shub_extra_id;
    }

	public function get($field){
		return isset($this->{$field}) ? $this->{$field} : false;
	}


    public function update($field,$value=false){
	    if(is_array($field)){
		    foreach($field as $key=>$val){
			    if(isset($this->details[$key])){
				    $this->update($key,$val);
			    }
		    }
		    return;
	    }
	    // what fields to we allow? or not allow?
	    if(in_array($field,array('shub_extra_id')))return;
        if($this->shub_extra_id){
            $this->{$field} = $value;
	        if(in_array($field,$this->json_fields)){
		        $value = json_encode($value);
	        }
	        if(in_array($field,$this->json_fields)) {
		        shub_update_insert( 'shub_extra_id', $this->shub_extra_id, 'shub_extra', array(
			        $field => $value,
		        ) );
	        }
        }
    }

	public function delete(){
		if($this->shub_extra_id) {
			shub_delete_from_db( 'shub_extra', 'shub_extra_id', $this->shub_extra_id );
		}
	}

	public function link_edit(){
		return 'admin.php?page=support_hub_settings&tab=extra&shub_extra_id='.$this->get('shub_extra_id');
	}

	// find out if there is any data saved against this particular extra field and message.
	public function get_data($shub_network, $shub_network_account_id, $shub_network_message_id, $shub_user_id){

		$return = array();

		// find any data items that are linked to this particular support message:
		$data = shub_get_multiple('shub_extra_data_rel', array(
			'shub_extra_id' => $this->shub_extra_id,
			'shub_network' => $shub_network,
			'shub_network_account_id' => $shub_network_account_id,
			'shub_network_message_id' => $shub_network_message_id
		), 'shub_extra_data_id');
		foreach($data as $d){
			$return[$d['shub_extra_data_id']] = new SupportHubExtraData($d['shub_extra_data_id']);
		}
		// find any data items that are linked to this particular user:
		$data = shub_get_multiple('shub_extra_data', array(
			'shub_user_id' => $shub_user_id,
		), 'shub_extra_data_id');
		foreach($data as $d){
			if(!isset($return[$d['shub_extra_data_id']])){
				$return[$d['shub_extra_data_id']] = new SupportHubExtraData($d['shub_extra_data_id']);
			}
		}
		return $return;
	}

	public function add_message_segment($value_all){
		$value = '';
		if(is_array($value_all)){
			if(isset($value_all['data'])){
				$value = $value_all['data'];
			}
		}else{
			$value = $value_all;
		}
		return '<div>
<strong>' . htmlspecialchars($this->get('extra_name')) .':</strong> ' . htmlspecialchars($value) .'
</div>';
	}

	/** static stuff **/
	public static function get_all_extras(){
		$return = array();
		$data = shub_get_multiple('shub_extra',array(),'shub_extra_id','extra_order');
		foreach($data as $d){
			$return[$d['shub_extra_id']] = new SupportHubExtra($d['shub_extra_id']);
		}
		return $return;
	}
	public static function form_request_extra($data=array()){
		$extras = self::get_all_extras();
		?>
		<div>
			Request Extra Details:
		</div>
		<ul>
			<?php foreach($extras as $extra){ ?>
			<li>
				<input type="checkbox" name="extra" class="request_extra" data-extra-id="<?php echo $extra->get('shub_extra_id');?>" /> <?php echo htmlspecialchars($extra->get('extra_name'));?>
			</li>
			<?php } ?>
		</ul>
		<div class="extra_details_message"></div>
		<p>
			<a href="#" class="shub_request_extra_send btn btn-primary btn-xs button"<?php foreach($data as $key=>$val){
				echo ' data-'.$key.'="'.esc_attr($val).'"';
			} ?>><?php _e( 'Send Request' ); ?></a>
			<a href="#" class="shub_request_extra btn btn-default btn-xs button"><?php _e( 'Cancel' ); ?></a>
		</p>
		<?php
	}
	public static function build_message_hash($network, $network_account_id, $network_message_id, $extra_ids){
		return $network.':'.$network_account_id.':'.$network_message_id.':'.implode(',',$extra_ids).':'.md5(NONCE_SALT.serialize(func_get_args()));
	}
	public static function build_message($data){
		return 'Hello,<br/> please send through some more details and we can assist: <a href="' . add_query_arg(_SUPPORT_HUB_LINK_REQUEST_EXTRA,self::build_message_hash(
			$data['network'],
			$data['network_account_id'],
			$data['network_message_id'],
			$data['extra_ids']
		),home_url()) . '">click here</a>. Thanks,<br/>dtbaker';
	}
	public static function handle_request_extra(){
		if(isset($_REQUEST[_SUPPORT_HUB_LINK_REQUEST_EXTRA]) && !empty($_REQUEST[_SUPPORT_HUB_LINK_REQUEST_EXTRA])){

			// verify this extra link is valid.
			$bits = explode(':',$_REQUEST[_SUPPORT_HUB_LINK_REQUEST_EXTRA]);
			if(count($bits) == 5){
				$network = $bits[0];
				$network_account_id = (int)$bits[1];
				$network_message_id = (int)$bits[2];
				$extra_ids = explode(',',$bits[3]);
				$legit_hash = self::build_message_hash($network, $network_account_id, $network_message_id, $extra_ids);
				if($legit_hash == $_REQUEST[_SUPPORT_HUB_LINK_REQUEST_EXTRA]){
					// woo we have a legit hash. continue.

					if (!session_id()) {
						if(headers_sent()){
							echo "Warning: session headers already sent, unable to proceed, please report this error.";
							exit;
						}
					    session_start();
					}

					// user has landed on this page from a tweet or item comment
					// we have to verify their identify first in order to provide the form and then do futher stuff.
					// pass this off in an action to grab any login/verification from the various networks.

					$login_status = false;
					$SupportHub = SupportHub::getInstance();

					include $SupportHub->get_template('shub_external_header.php');
					if(isset($SupportHub->message_managers[$network])){
						$login_status = $SupportHub->message_managers[$network]->extra_process_login($network, $network_account_id, $network_message_id, $extra_ids);
					}else{
						die('Invalid message manager');
					}

					if($login_status) {
						// the user is logged in and their identity has been verified by one of the 3rd party plugins.
						// we can now safely accept their additoinal information and append it to this ticket.
						$extras = self::get_all_extras();
						$extra_previous_data = isset($_POST['extra']) && is_array($_POST['extra']) ? $_POST['extra'] : array();
						$extra_previous_data_errors = array();
						$extra_previous_data_validated = array();
						$extra_previous_notes = isset($_POST['extra_notes']) ? $_POST['extra_notes'] : '';
						// check if the user is submitting extra information:
						$has_data_error = false;
						$missing_required_information = false; // todo, work out which fields are required, maybe mark them in the original request along with $extra_ids ?
						foreach($extras as $extra_id => $extra){
							if(!in_array($extra->get('shub_extra_id'), $extra_ids)){
								unset($extras[$extra_id]);
							}else {
								// this extra is to be shown on the page. load in any existing data for this extra item.
								// todo: hmm nah, dont re-show that information here in the form, the form is only for adding new information.
								// only show information that was an error and needs to be corrected again before submission/save.
								//$extra_data = $extra->get_data($network, $network_account_id, $network_message_id);
								if(isset($extra_previous_data[$extra_id])){
									$status = array(
										'success' => true,
									);
									// validate the data, we have to filter it because Twitter might want to validate a Purchase code against the envato plugin.
									$status = apply_filters('shub_extra_validate_data', $status, $extra, $extra_previous_data[$extra_id], $network, $network_account_id, $network_message_id);

									if($status && $status['success']){
										// all good ready to save!
										$extra_previous_data_validated[$extra_id] = isset($status['data']) ? $status['data'] : $extra_previous_data[$extra_id]; // doing it this way so we can save additional details such as license code verification
									}else{
										$has_data_error = true;
										$extra_previous_data_errors[$extra_id] = isset($status['message']) ? $status['message'] : 'Error';
									}
								}else{
									// todo: figureo ut if this field ismissing?
									if(!empty($extra_previous_notes) || !empty($extra_previous_data)){
										$missing_required_information = true;
									}
								}
							}
						}
						if(!$has_data_error && (!empty($extra_previous_notes) || !empty($extra_previous_data_validated))) {
							// user has input something
							// build up the private message to store in the system
							$message = '';
							foreach($extras as $extra_id => $extra) {
								if ( isset( $extra_previous_data_validated[ $extra_id ] ) ) {
									$message .= $extra->add_message_segment($extra_previous_data_validated[ $extra_id ]);
								}
							}
							if(!empty($extra_previous_notes)){
								$message .= '<div>'. shub_forum_text($extra_previous_notes).'</div>';
							}
							// pass it through to the message managers to store this information!
							// (e.g. envato module will validate the 'purchase_code' and return a possible error)
							foreach($extras as $extra_id => $extra){
								if(isset($extra_previous_data_validated[$extra_id])) {
									$status = $SupportHub->message_managers[ $network ]->extra_save_data( $extra, $extra_previous_data_validated[$extra_id], $network, $network_account_id, $network_message_id );
									unset($extra_previous_data[$extra_id]);
								}
							}
							// all done! save our message in the db
							$SupportHub->message_managers[ $network ]->extra_send_message( $message, $network, $network_account_id, $network_message_id );

						}
						include $SupportHub->get_template('shub_extra_request_form.php');
					}else{
						// we display the login form during request_extra_login()
					}
					include $SupportHub->get_template('shub_external_footer.php');
					exit;
				}
			}
		}
	}

}



class SupportHubExtraData {

	public function __construct( $shub_extra_data_id = false ) {
		if ( $shub_extra_data_id ) {
			$this->load( $shub_extra_data_id );
		}
	}

	private $shub_extra_data_id = false; // the current extra id in our system.
	private $details = array();
	private $json_fields = array('extra_data');

	private function reset() {
		$this->shub_extra_data_id = false;
		$this->details       = array(
			'shub_extra_data_id'     => '',
			'shub_extra_id'     => '',
			'shub_network'        => '',
			'shub_network_account_id'        => 0,
			'shub_network_message_id'        => 0,
			'extra_value' => '',
			'extra_data' => '',
			'shub_user_id'       => 0,
		);
		foreach ( $this->details as $field_id => $field_data ) {
			$this->{$field_id} = $field_data;
		}
	}

	public function create_new() {
		$this->reset();
		$this->shub_extra_data_id = shub_update_insert( 'shub_extra_data_id', false, 'shub_extra', array() );
		$this->load( $this->shub_extra_data_id );
	}


	public function load_by( $field, $value ) {
		$this->reset();
		if ( ! empty( $field ) && ! empty( $value ) && isset( $this->details[ $field ] ) ) {
			$data = shub_get_single( 'shub_extra', $field, $value );
			if ( $data && isset( $data[ $field ] ) && $data[ $field ] == $value && $data['shub_extra_data_id'] ) {
				$this->load( $data['shub_extra_data_id'] );

				return true;
			}
		}

		return false;
	}

	public function load( $shub_extra_data_id ) {
		if ( ! $shub_extra_data_id ) {
			$shub_extra_data_id = $this->shub_extra_data_id;
		}
		$this->reset();
		$this->shub_extra_data_id = $shub_extra_data_id;
		if ( $this->shub_extra_data_id ) {
			$data = shub_get_single( 'shub_extra', 'shub_extra_data_id', $this->shub_extra_data_id );
			foreach ( $this->details as $key => $val ) {
				$this->details[ $key ] = $data && isset( $data[ $key ] ) ? $data[ $key ] : $val;
				if ( in_array( $key, $this->json_fields ) ) {
					$this->details[ $key ] = @json_decode( $this->details[ $key ], true );
					if ( ! is_array( $this->details[ $key ] ) ) {
						$this->details[ $key ] = array();
					}
				}
			}
			if ( ! is_array( $this->details ) || $this->details['shub_extra_data_id'] != $this->shub_extra_data_id ) {
				$this->reset();

				return false;
			}
		}
		foreach ( $this->details as $key => $val ) {
			$this->{$key} = $val;
		}

		return $this->shub_extra_data_id;
	}

	public function get( $field ) {
		return isset( $this->{$field} ) ? $this->{$field} : false;
	}


	public function update( $field, $value = false ) {
		if ( is_array( $field ) ) {
			foreach ( $field as $key => $val ) {
				if ( isset( $this->details[ $key ] ) ) {
					$this->update( $key, $val );
				}
			}

			return;
		}
		// what fields to we allow? or not allow?
		if ( in_array( $field, array( 'shub_extra_data_id' ) ) ) {
			return;
		}
		if ( $this->shub_extra_data_id ) {
			$this->{$field} = $value;
			if ( in_array( $field, $this->json_fields ) ) {
				$value = json_encode( $value );
			}
			if ( in_array( $field, $this->json_fields ) ) {
				shub_update_insert( 'shub_extra_data_id', $this->shub_extra_data_id, 'shub_extra', array(
					$field => $value,
				) );
			}
		}
	}

	public function delete() {
		if ( $this->shub_extra_data_id ) {
			shub_delete_from_db( 'shub_extra_data', 'shub_extra_data_id', $this->shub_extra_data_id );
		}
	}

	public function link_edit() {
		return 'admin.php?page=support_hub_settings&tab=extra&shub_extra_data_id=' . $this->get( 'shub_extra_data_id' );
	}

}


class SupportHubExtraList extends SupportHub_Account_Data_List_Table{
    private $row_output = array();

	function __construct($args = array()) {
		$args = wp_parse_args( $args, array(
			'plural'   => __( 'extra_details', 'support_hub' ),
			'singular' => __( 'extra_detail', 'support_hub' ),
			'ajax'     => false,
		) );
		parent::__construct( $args );
	}


	private $message_managers = array();
	function set_message_managers($message_managers){
		$this->message_managers = $message_managers;
	}

	private $column_details = array();
    function column_default($item, $column_name){

	    if(is_object($item)){
			return $item->get($column_name);
	    }else if( is_array($item) && isset($item[$column_name])){
		    return $item[$column_name];
	    }else{
		    return 'No';
	    }
   }
}