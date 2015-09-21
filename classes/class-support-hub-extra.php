<?php



class SupportHubExtra{

	public function __construct($shub_extra_id = false){
		if($shub_extra_id){
			$this->load($shub_extra_id);
		}
	}

	private $shub_extra_id = false; // the current extra id in our system.
    private $details = array();
	private $json_fields = array('field_settings');

	public $edit_link = ''; // for the table

	private function reset(){
		$this->shub_extra_id = false;
		$this->details = array(
			'shub_extra_id' => '',
			'extra_name' => '',
			'extra_description' => '',
			'extra_order' => 0,
			'extra_required' => 0,
			'field_type' => 'text',
			'field_settings' => array(),
		);
		foreach($this->details as $field_id => $field_data){
			$this->{$field_id} = $field_data;
		}
	}

	public function create_new(){
		$this->reset();
		$this->shub_extra_id = shub_update_insert('shub_extra_id',false,'shub_extra',array(
            'extra_required' => 0,
            'field_type' => 'text',
        ));
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
	        if(isset($this->details[$field])) {
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
	public function get_data($shub_extension, $shub_account_id, $shub_message_id, $shub_user_id){

		$return = array();

		// find any data items that are linked to this particular support message:
		if($shub_extension && $shub_account_id && $shub_message_id) {
			$data = shub_get_multiple( 'shub_extra_data_rel', array(
				'shub_extra_id'           => $this->shub_extra_id,
				'shub_extension'            => $shub_extension,
				'shub_account_id' => $shub_account_id,
				'shub_message_id' => $shub_message_id
			), 'shub_extra_data_id' );
			foreach ( $data as $d ) {
				$return[ $d['shub_extra_data_id'] ] = new SupportHubExtraData( $d['shub_extra_data_id'] );
			}
		}
		// find any data items that are linked to this particular user from other
		if($shub_user_id) {
			$data = shub_get_multiple( 'shub_extra_data', array(
				'shub_user_id' => $shub_user_id,
			), 'shub_extra_data_id' );
			foreach ( $data as $d ) {
				if ( ! isset( $return[ $d['shub_extra_data_id'] ] ) ) {
					$return[ $d['shub_extra_data_id'] ] = new SupportHubExtraData( $d['shub_extra_data_id'] );
				}
			}
		}
		return $return;
	}

    /**
     * @param $data array('extra_value'=>'SOMETHING', 'extra_data'=>array(SOMETHING))
     * @param $shub_extension
     * @param $shub_account_id
     * @param $shub_message_id
     * @param $shub_user_id
     *
     * This method will save a set of extra data against a message/user
     *
     */
	public function save_and_link($data, $shub_extension, $shub_account_id, $shub_message_id, $shub_user_id ){
		// find if this data exists already in the table.
		$shub_extra_data_ids = shub_get_multiple('shub_extra_data',array(
			'shub_extra_id' => $this->shub_extra_id,
			'extra_value' => $data['extra_value'],
			'shub_user_id' => $shub_user_id,
		),'shub_extra_data_id');
		if(!$shub_extra_data_ids){
			// create a new data entry!
			$data['shub_user_id'] = $shub_user_id;
			$shub_extra_data_id = shub_update_insert('shub_extra_data_id',false,'shub_extra_data',array(
				'shub_extra_id' => $this->shub_extra_id,
				'shub_user_id' => $shub_user_id,
				'extra_value' => $data['extra_value'],
				'extra_data' => !empty($data['extra_data']) ? json_encode($data['extra_data']) : false,
				'extra_time' => time(),
			));
			$shub_extra_data_ids = array();
			$shub_extra_data_ids[$shub_extra_data_id] = true;
		}else{
			// we have one (or more) existing entries
		}
		// link these existing (or new) entries with this network/account/message (if they're not already linked)
		$existing_linked = shub_get_multiple('shub_extra_data_rel', array(
			'shub_extra_id' => $this->shub_extra_id,
			'shub_extension' => $shub_extension,
			'shub_account_id' => $shub_account_id,
			'shub_message_id' => $shub_message_id
		), 'shub_extra_data_id');
		foreach($shub_extra_data_ids as $shub_extra_data_id => $tf){
			if(!isset($existing_linked[$shub_extra_data_id])){
				// not linked yet, add a new link.
				global $wpdb;
				$result = $wpdb->insert(_support_hub_DB_PREFIX.'shub_extra_data_rel', array(
					'shub_extra_data_id' => $shub_extra_data_id,
					'shub_extra_id' => $this->get('shub_extra_id'),
					'shub_extension' => $shub_extension,
					'shub_account_id' => $shub_account_id,
					'shub_message_id' => $shub_message_id,
				));
				// shweet. should all be linked up.
			}
		}
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
		if(!empty($value)) {
			$str = '<p>
<strong>' . htmlspecialchars( $this->get( 'extra_name' ) ) . ':</strong> ';
			switch($this->get('field_type')){
				case 'encrypted':
					$str .= '(encrypted)';
					break;
				default:
					$str .= htmlspecialchars( $value );
			}
			$str .= '</p>';
			return $str;
		}
		return '';
	}

	/** static stuff **/

	/**
	 * @return SupportHubExtra[]
	 */
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
        $done_actions = false;
        ?>
        <p><strong>Note: this feature is still early beta, it may not work.</strong></p>
        <?php
        foreach($extras as $extra){
            $field_settings = $extra->get('field_settings');
            if(!empty($field_settings['is_action'])){
                if(!$done_actions){
                    $done_actions = true;
                    ?>
                    <div>
                        <?php _e('Actions:','shub'); ?>
                    </div>
                    <ul>
                    <?php
                }
                $id = 'extra_request_' . (isset($data['message-id']) ? (int)$data['message-id'] : 0) . '_'.$extra->get('shub_extra_id');
                ?>
                <li>
                    <input type="checkbox" name="extra" class="request_extra" id="<?php echo $id;?>" data-extra-id="<?php echo $extra->get('shub_extra_id');?>" />
                    <label for="<?php echo $id;?>"><?php echo htmlspecialchars($extra->get('extra_name'));?></label>
                </li>
                <?php
            }
        }
        if($done_actions){
            ?>
            </ul>
            <?php
        }
        ?>
		<div>
			<?php _e('Request Extra Details:','shub'); ?>
		</div>
		<ul>
			<?php foreach($extras as $extra){
                $field_settings = $extra->get('field_settings');
                if(!empty($field_settings['is_action'])){
                    continue;// already done these above
                }
                $id = 'extra_request_' . (isset($data['message-id']) ? (int)$data['message-id'] : 0) . '_'.$extra->get('shub_extra_id');
                ?>
			<li>
				<input type="checkbox" name="extra" class="request_extra" id="<?php echo $id;?>" data-extra-id="<?php echo $extra->get('shub_extra_id');?>" />
				<label for="<?php echo $id;?>"><?php echo htmlspecialchars($extra->get('extra_name'));?></label>
			</li>
			<?php } ?>
		</ul>
		<div class="extra_details_message"></div>
		<p>
			<a href="#" class="shub_request_extra_generate btn btn-primary btn-xs button shub_button_loading"<?php foreach($data as $key=>$val){
				echo ' data-'.$key.'="'.esc_attr($val).'"';
			} ?>><?php _e( 'Generate Message' ); ?></a>
			<a href="#" class="shub_request_extra btn btn-default btn-xs button"><?php _e( 'Cancel' ); ?></a>
		</p>
		<?php
	}
	public static function build_message_hash($network, $account_id, $message_id, $extra_ids){
		return $network.':'.$account_id.':'.$message_id.':'.implode(',',$extra_ids).':'.md5(NONCE_SALT.serialize(func_get_args()));
	}
	public static function build_message($data){
		return 'Hello,

please send through some more details and we can assist:

<a href="' . add_query_arg(_SUPPORT_HUB_LINK_REQUEST_EXTRA,self::build_message_hash(
			$data['network'],
			$data['account_id'],
			$data['message_id'],
			$data['extra_ids']
		),home_url()) . '">click here</a>.

Thanks.';
	}
	public static function handle_request_extra(){
		if(isset($_REQUEST[_SUPPORT_HUB_LINK_REQUEST_EXTRA]) && !empty($_REQUEST[_SUPPORT_HUB_LINK_REQUEST_EXTRA])){

			// todo: don't overwrite default superglobals, run stripslashes every time before we use the content, because another plugin might be stripslashing already
			$_POST    = stripslashes_deep( $_POST );
			$_GET     = stripslashes_deep( $_GET );
			$_REQUEST = stripslashes_deep( $_REQUEST );

			// verify this extra link is valid.
			$bits = explode(':',$_REQUEST[_SUPPORT_HUB_LINK_REQUEST_EXTRA]);
			if(count($bits) == 5){
				$network = $bits[0];
				$account_id = (int)$bits[1];
				$message_id = (int)$bits[2];
				$extra_ids = explode(',',$bits[3]);
				$legit_hash = self::build_message_hash($network, $account_id, $message_id, $extra_ids);
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

					ob_start();

					include $SupportHub->get_template('shub_external_header.php');
                    $all_login_methods = array();
                    $login_form_actions = '';
					if(isset($SupportHub->message_managers[$network])){
                        // todo: offer them another way to login to the system.
                        // e.g. someone might want to login using Facebook to access their Envato feed
                        // if the email matches between accounts this should be possible
                        // if no match is found then we can just show a not found error.
                        // but for now we only allow login from the network we started with.
                        // ooooooooooo maybe we can have the generic extra_process_login method show a list of available login methods? and the individual networks can override this if needed
                        // hmm.. ideas ideas..
						$login_methods = $SupportHub->message_managers[$network]->extra_get_login_methods($network, $account_id, $message_id, $extra_ids);
                        $allow_other_logins = true;
                        if($login_methods && !empty($login_methods['account_buttons'])){
                            $all_login_methods[] = $login_methods;
                            if(isset($login_methods['allow_others']) && !$login_methods['allow_others']){
                                $allow_other_logins = false;
                            }
                        }
                        if($allow_other_logins){
                            // loop over other message managers and find any other login methods.
                            foreach($SupportHub->message_managers as $this_network => $message_manager) {
                                $login_methods = $message_manager->extra_get_login_methods($network, $account_id, $message_id, $extra_ids);
                                if ($login_methods && !empty($login_methods['account_buttons'])) {
                                    if (isset($login_methods['allow_others']) && !$login_methods['allow_others']) {
                                        // this 3rd party login method (e.g. envato) is taking over and forcing the user to login using it, rather than the current extensions login method.
                                        // this happens when for example a "tweet" needs to verify a purchase, so we need to force the user to login with Envato to do that.
                                        $all_login_methods = array();
                                        $all_login_methods[] = $login_methods;
                                        break;
                                    } else {
                                        $all_login_methods[] = $login_methods;
                                    }
                                }
                            }
                        }
                        foreach($all_login_methods as $all_login_method){

                            // generate the login form to display below (if the user hasn't logged in before yet.
                            $login_form_actions .= wpautop($all_login_method['message']);
                            foreach($all_login_method['account_buttons'] as $this_account_id => $this_account_button){

                                // we check if the user has logged in using this account before.
                                if($all_login_method['network'] && isset($SupportHub->message_managers[$all_login_method['network']])){
                                    $login_status = $SupportHub->message_managers[$all_login_method['network']]->extra_process_login($network, $this_account_id, $message_id, $extra_ids);
                                    if($login_status && !empty($login_status['logged_in'])){
                                        // we have a successful login.
                                        break;
                                    }else if($login_status){
	                                    if(!empty($login_status['message'])){
		                                    $login_form_actions = $login_status['message'].$login_form_actions;
	                                    }
                                    }
                                }
                                // login button for this particular account.
                                $login_form_actions .= $this_account_button;
                            }
                        }


					}else{
						die('Invalid message manager');
					}

                    if($login_status && !empty($login_status['logged_in'])){
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
								//$extra_data = $extra->get_data($network, $account_id, $message_id);
								if(isset($extra_previous_data[$extra_id]) && is_string($extra_previous_data[$extra_id])){
									$status = array(
										'success' => true,
									);
									// validate the data, we have to filter it because Twitter might want to validate a Purchase code against the envato plugin.
									$status = apply_filters('shub_extra_validate_data', $status, $extra, $extra_previous_data[$extra_id], $network, $account_id, $message_id);

									if($status && $status['success']){
										// all good ready to save!
										$extra_previous_data_validated[$extra_id] = !empty($status['data']) ? $status : $extra_previous_data[$extra_id]; // doing it this way so we can save additional details such as license code verification
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

							$shub_message = $SupportHub->get_message_object($message_id);
							if($shub_message) {
								$shub_user_id = !empty( $_SESSION['shub_oauth_envato']['shub_user_id'] ) ? $_SESSION['shub_oauth_envato']['shub_user_id'] : $shub_message->get( 'shub_user_id' );
								// user has input something
								// build up the private message to store in the system
								$message = '';
								foreach ( $extras as $extra_id => $extra ) {
									if ( isset( $extra_previous_data_validated[ $extra_id ] ) ) {
										$message .= $extra->add_message_segment( $extra_previous_data_validated[ $extra_id ] );
									}
								}
								if ( ! empty( $extra_previous_notes ) ) {
									$message .= '<p>' . shub_forum_text( $extra_previous_notes ) . '</p>';
									$extra_previous_notes = false;
								}
								// pass it through to the message managers to store this information!
								// (e.g. envato module will validate the 'purchase_code' and return a possible error)
								foreach ( $extras as $extra_id => $extra ) {
									if ( isset( $extra_previous_data_validated[ $extra_id ] ) ) {
										//$SupportHub->message_managers[ $network ]->extra_save_data( $extra, $extra_previous_data_validated[ $extra_id ], $network, $account_id, $message_id, $shub_message, $shub_user_id );
										$extra->save_and_link(
											array(
												'extra_value' => is_array($extra_previous_data_validated[ $extra_id ]) && !empty($extra_previous_data_validated[ $extra_id ]['data']) ? $extra_previous_data_validated[ $extra_id ]['data'] : $extra_previous_data_validated[ $extra_id ],
												'extra_data' => is_array($extra_previous_data_validated[ $extra_id ]) && !empty($extra_previous_data_validated[ $extra_id ]['extra_data']) ? $extra_previous_data_validated[ $extra_id ]['extra_data'] : false,
											),
											$network,
											$account_id,
											$message_id,
											$shub_user_id
										);
										unset( $extra_previous_data[ $extra_id ] );
									}
								}
								// all done! save our message in the db
								$SupportHub->message_managers[ $network ]->extra_send_message( $message, $network, $account_id, $message_id, $shub_message, $shub_user_id );
								// redirect browser to a done page.
								header( "Location: " . $_SERVER['REQUEST_URI'] . '&done' );
								exit;
							}


						}
						include $SupportHub->get_template('shub_extra_request_form.php');
					}else{
						// we build up this login form in the above loop.
                        echo $login_form_actions;
					}
					include $SupportHub->get_template('shub_external_footer.php');
					echo ob_get_clean();
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
			'shub_extension'        => '',
			'shub_account_id'        => 0,
			'shub_message_id'        => 0,
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
			$data = shub_get_single( 'shub_extra_data', 'shub_extra_data_id', $this->shub_extra_data_id );
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
            shub_update_insert( 'shub_extra_data_id', $this->shub_extra_data_id, 'shub_extra_data', array(
                $field => $value,
            ) );
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