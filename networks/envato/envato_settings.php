<?php

$current_account = isset($_REQUEST['shub_envato_id']) ? (int)$_REQUEST['shub_envato_id'] : false;
$shub_envato = new shub_envato();
if($current_account !== false){
	$shub_envato_account = new shub_envato_account($current_account);
	if(isset($_GET['manualrefresh'])){

		if(isset($_REQUEST['envato_stream'])){

			$shub_envato_account->load_latest_stream_data( true );


		}else {

			$envato_group_id = isset( $_REQUEST['envato_group_id'] ) ? (int) $_REQUEST['envato_group_id'] : 0;
			/* @var $groups shub_envato_group[] */
			$groups = $shub_envato_account->get( 'groups' );
			if ( ! $envato_group_id || ! $groups || ! isset( $groups[ $envato_group_id ] ) ) {
				die( 'No groups found to refresh' );
			}
			?>
			Manually refreshing group data... please wait...
			<?php
			$groups[ $envato_group_id ]->load_latest_group_data( true );
			$groups[ $envato_group_id ]->run_cron( true );
		}

	}else if(isset($_GET['envato_do_oauth_connect'])){
		// connect to envato.
		?>
		<div class="wrap">
			<h2>
				<?php _e( 'envato Account', 'support_hub' ); ?>
			</h2>
		<?php
		if($shub_envato_account->get('shub_envato_id') && $shub_envato_account->get('shub_envato_id') == $current_account && $shub_envato_account->get( 'envato_app_id' ) && $shub_envato_account->get( 'envato_app_secret' )) {



			$envato = $shub_envato_account->get_api(false);

			if ($envato->isAuthenticated()) {
			    //we know that the user is authenticated now. Start query the API
				// available fields here: https://developer.envato.com/docs/fields/basic-profile
			    $user=$envato->api('v1/people/~:(firstName,lastName,picture-url,public-profile-url)');
				$access_token = $envato->getAccessToken();
				if($access_token){
//					echo "Saving token as: <br> $access_token <br>";
	                $shub_envato_account->update( 'envato_token', $access_token );
	                // success!

	                // now we load in a list of envato groups to manage and redirect the user back to the 'edit' screen where they can continue managing the account.
	                $shub_envato_account->save_account_data($user);
	                $shub_envato_account->load_available_groups();
	                $url = $shub_envato_account->link_edit();
	                ?>
	                <p>Welcome <?php echo htmlspecialchars($user['firstName']);?>! You have successfully connected envato with the Support Hub plugin. Please click the button below:</p>
	                <p><a href="<?php echo $shub_envato_account->link_edit(); ?>" class="button">Click here to continue.</a></p>
					<p>&nbsp;</p>
					<p>&nbsp;</p>
					<p>&nbsp;</p>
					<p>&nbsp;</p>
					<p>&nbsp;</p>
					<p>&nbsp;</p>
	                <?php

	            }else{
	                echo 'Error getting accesscode from API. Please press back and try again.';
	            }
			} elseif ($envato->hasError()) {
			    $url = $shub_envato_account->link_edit();
                ?>
                <p>Login was cancelled.</p>
                <p><a href="<?php echo $shub_envato_account->link_edit(); ?>" class="button">Click here to return.</a></p>
                <?php
			}

			//if not authenticated
			$url = $envato->getLoginUrl(array(
				'scope' => array(
					'w_share',
					'w_messages',
					'r_network',
					'r_fullprofile',
					'rw_nus',
					'r_contactinfo',
					'r_emailaddress',
					'rw_groups',
				)
			));

			?>
			<p>Please click the button below to connect your envato account:</p>

			<a href="<?php echo $url; ?>" id="envato-login-button"><img
					src="<?php echo plugins_url( 'networks/envato/signin-button.png', _DTBAKER_SUPPORT_HUB_CORE_FILE_ ); ?>"
					title="Connect to envato" border="0"></a>

			<?php
		} else {
            // no app / secret defined, use the default Support Hub API ones.
			?>
			Please go back and set an API Key and Secret.
			<?php
		}
		?> </div> <?php
	}else {
		?>
		<div class="wrap">
			<h2>
				<?php _e( 'envato Account', 'support_hub' ); ?>
			</h2>

			<form action="" method="post">
				<input type="hidden" name="_process" value="save_envato">
				<input type="hidden" name="shub_envato_id"
				       value="<?php echo (int) $shub_envato_account->get( 'shub_envato_id' ); ?>">
				<?php wp_nonce_field( 'save-envato' . (int) $shub_envato_account->get( 'shub_envato_id' ) ); ?>

                <p>Setup Instructions:</p>
                <ul>
                    <li>Go to <a href="https://www.envato.com/secure/developer" target="_blank">https://www.envato.com/secure/developer</a>  and click Add New Application</li>
                    <li>Fill out the details similar to this screenshot (use your own Company Name etc..) <a href="<?php echo plugins_url('networks/envato/app-setup.png', _DTBAKER_SUPPORT_HUB_CORE_FILE_ );?>" target="_blank">click here for screenshot</a> </li>
	                <li>In the OAuth 2.0 Redirect URLs box, put this address: <strong><?php echo admin_url('admin.php?page=support_hub_settings&tab=envato&envato_do_oauth_connect');?></strong></li>
                    <li>After creating the application, copy the <strong>API Key</strong> and <strong>Secret Key</strong> into the boxes below.</li>
                </ul>
				<table class="form-table">
					<tbody>
					<tr>
						<th class="width1">
							<?php _e( 'Account Name', 'support_hub' ); ?>
						</th>
						<td class="">
							<input type="text" name="envato_name" value="<?php echo esc_attr( $shub_envato_account->get( 'envato_name' ) ); ?>">

						</td>
					</tr>
                    <tr>
                        <th class="width1">
                            <?php _e( 'API Key', 'support_hub' ); ?>
                        </th>
                        <td class="">
                            <input type="text" name="envato_app_id"
							       value="<?php echo esc_attr( $shub_envato_account->get( 'envato_app_id' ) ); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th class="width1">
                            <?php _e( 'Secret Key', 'support_hub' ); ?>
                        </th>
                        <td class="">
                            <input type="text" name="envato_app_secret"
							       value="<?php echo esc_attr( $shub_envato_account->get( 'envato_app_secret' ) ); ?>">
                        </td>
                    </tr>
					<?php if ( $shub_envato_account->get( 'shub_envato_id' ) ) { ?>
						<tr>
							<th class="width1">
								<?php _e( 'Last Checked', 'support_hub' ); ?>
							</th>
							<td class="">
								<?php echo $shub_envato_account->get( 'last_checked' ) ? shub_print_date( $shub_envato_account->get( 'last_checked' ), true ) : __( 'N/A', 'support_hub' ); ?>
							</td>
						</tr>
						<tr>
							<th class="width1">
								<?php _e( 'Network', 'support_hub' ); ?>
							</th>
							<td class="">
								<div>
									<input type="checkbox" name="import_stream" value="1" <?php echo $shub_envato_account->get( 'import_stream' ) ? ' checked' : ''; ?>>
									Import Network Stream
									<?php
									if ( $shub_envato_account->get( 'import_stream' ) ) {
										echo ' (<a href="' . $shub_envato_account->link_refresh() . '" target="_blank">manually re-load stream data</a>)';
									} ?>
								</div>
							</td>
						</tr>
						<tr>
							<th class="width1">
								<?php _e( 'Available Groups', 'support_hub' ); ?>
							</th>
							<td class="">
								<input type="hidden" name="save_envato_groups" value="yep">
								<strong><?php _e( 'Choose which envato groups you would like to manage:', 'support_hub' ); ?></strong><br>
								<?php
								$data = @json_decode( $shub_envato_account->get( 'envato_data' ), true );
								if ( $data && isset( $data['groups'] ) && is_array( $data['groups'] ) && count( $data['groups'] ) > 0 ) {
									$envato_groups = $shub_envato_account->get('groups');
									foreach ( $data['groups'] as $group_id => $group_data ) {
										?>
										<div>
											<input type="checkbox" name="envato_group[<?php echo $group_id; ?>]"
											       value="1" <?php echo $shub_envato_account->is_group_active( $group_id ) ? ' checked' : ''; ?>>
											<?php echo htmlspecialchars( $group_data['group']['name'] ); ?>
											(<?php echo htmlspecialchars( $group_data['membershipState']['code'] ); ?>)
											<?php
											if ( $shub_envato_account->is_group_active( $group_id ) ) {
												echo ' (<a href="' . $envato_groups[ $group_id ]->link_refresh() . '" target="_blank">manually re-load group data</a>)';
											} ?>
										</div>
									<?php
									}
								} else {
									_e( 'No envato groups Found to Manage', 'support_hub' );
								}
								?>
							</td>
						</tr>
					<?php } ?>
					</tbody>
				</table>

				<p class="submit">
					<?php if ( $shub_envato_account->get( 'shub_envato_id' ) ) { ?>
						<input name="butt_save" type="submit" class="button-primary"
						       value="<?php echo esc_attr( __( 'Save', 'support_hub' ) ); ?>"/>
						<input name="butt_save_reconnect" type="submit" class="button"
						       value="<?php echo esc_attr( __( 'Re-Connect to envato', 'support_hub' ) ); ?>"/>
						<input name="butt_delete" type="submit" class="button"
						       value="<?php echo esc_attr( __( 'Delete', 'support_hub' ) ); ?>"
						       onclick="return confirm('<?php _e( 'Really delete this envato account and all associated data?', 'support_hub' ); ?>');"/>
					<?php } else { ?>
						<input name="butt_save_reconnect" type="submit" class="button-primary"
						       value="<?php echo esc_attr( __( 'Save and Connect to envato', 'support_hub' ) ); ?>"/>
					<?php } ?>
				</p>


			</form>
		</div>
	<?php
	}
}else{
	// show account overview:
	$myListTable = new SupportHub_Account_Data_List_Table();
	$accounts = $shub_envato->get_accounts();
	foreach($accounts as $account_id => $account){
		$a = new shub_envato_account($account['shub_envato_id']);
		$accounts[$account_id]['edit_link'] = $a->link_edit();
		$accounts[$account_id]['title'] = $a->get('envato_name');
		$accounts[$account_id]['last_checked'] = $a->get('last_checked') ? shub_print_date( $a->get('last_checked') ) : 'N/A';
	}
	$myListTable->set_data($accounts);
	$myListTable->prepare_items();
	?>
	<div class="wrap">
		<h2>
			<?php _e('envato Accounts','support_hub');?>
			<a href="?page=<?php echo esc_attr($_GET['page']);?>&tab=<?php echo esc_attr($_GET['tab']);?>&shub_envato_id=new" class="add-new-h2"><?php _e('Add New','support_hub');?></a>
		</h2>
		<p>
			<strong>Please Note:</strong> On May 12th, 2015 the envato API will be changing. envato integration will <em>stop working</em> on this date. We are working with envato to try and find an alternative in order to keep the product running smoothly. Check our website for updates.
		</p>
	    <?php
	    //$myListTable->search_box( 'search', 'search_id' );
	     $myListTable->display();
		?>
	</div>
	<?php
}
