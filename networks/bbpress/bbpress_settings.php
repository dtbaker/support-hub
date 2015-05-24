<?php

$current_account = isset($_REQUEST['shub_bbpress_id']) ? (int)$_REQUEST['shub_bbpress_id'] : false;
$shub_bbpress = new shub_bbpress();
if($current_account !== false){
	$shub_bbpress_account = new shub_bbpress_account($current_account);
	if(isset($_GET['manualrefresh'])){

		$bbpress_forum_id = isset( $_REQUEST['bbpress_forum_id'] ) ? (int) $_REQUEST['bbpress_forum_id'] : 0;
		/* @var $forums shub_bbpress_forum[] */
		$forums = $shub_bbpress_account->get( 'forums' );
		if ( ! $bbpress_forum_id || ! $forums || ! isset( $forums[ $bbpress_forum_id ] ) ) {
			die( 'No forums found to refresh' );
		}
		?>
		Manually refreshing forum data... please wait...
		<?php
		$forums[ $bbpress_forum_id ]->run_cron( true );

	}else if(isset($_GET['bbpress_do_oauth_connect'])){
		// connect to bbpress. and if that isnt' found
		?>
		<div class="wrap">
			<h2>
				<?php _e( 'bbpress Account', 'support_hub' ); ?>
			</h2>
		<?php
		if($shub_bbpress_account->get('shub_bbpress_id') && $shub_bbpress_account->get('shub_bbpress_id') == $current_account && $shub_bbpress_account->get( 'bbpress_app_id' ) && $shub_bbpress_account->get( 'bbpress_app_secret' ) && $shub_bbpress_account->get( 'bbpress_token' )) {

            // now we load in a list of bbpress forums to manage and redirect the user back to the 'edit' screen where they can continue managing the account.
            $shub_bbpress_account->load_available_forums();
            $url = $shub_bbpress_account->link_edit();
            ?>
            <p>You have successfully connected bbpress with the Support Hub plugin. Please click the button below:</p>
            <p><a href="<?php echo $shub_bbpress_account->link_edit(); ?>" class="button">Click here to continue.</a></p>
			<p>&nbsp;</p>
			<p>&nbsp;</p>
			<p>&nbsp;</p>
			<p>&nbsp;</p>
            <?php

		} else {
            // no app / secret defined, use the default Support Hub API ones.
			?>
			Please go back and set an API Secret, Client ID and Personal Token.
			<?php
		}
		?> </div> <?php
	}else {
		?>
		<div class="wrap">
			<h2>
				<?php _e( 'bbpress Account', 'support_hub' ); ?>
			</h2>

			<form action="" method="post">
				<input type="hidden" name="_process" value="save_bbpress">
				<input type="hidden" name="shub_bbpress_id"
				       value="<?php echo (int) $shub_bbpress_account->get( 'shub_bbpress_id' ); ?>">
				<?php wp_nonce_field( 'save-bbpress' . (int) $shub_bbpress_account->get( 'shub_bbpress_id' ) ); ?>

                <p>Setup Instructions:</p>
                <ul>
                    <li>Go to <a href="http://build.bbpress.com/" target="_blank">http://build.bbpress.com/</a> </li>
                    <li>Login using your existing bbpress Account</li>
                    <li>Click My Apps at the top</li>
                    <li>Click Register a new App</li>
                    <li>In the app name put "SupportHub" (or anything really)</li>
                    <li>Tick "View username", "View email", "View sales history" and "Verify purchase" options</li>
                    <li>In the "Confirmation URL" put <strong><?php echo get_home_url();?></strong></li>
                    <li>Click Register App</li>
                    <li>Copy your secret key and paste it into the box below</li>
                    <li>Click OK</li>
                    <li>Then look for the "Client ID" for your App and copy that into the box below as well</li>
	                <li>Click the "Create a new Token" button</li>
	                <li>Enter a name again like "SupportHub"</li>
	                <li>Tick the "Verify purchase" option</li>
	                <li>Click "Create Token"</li>
	                <li>Copy this token and paste it into the box below</li>
                    <li>Click the Save and Connect to bbpress button below</li>
                </ul>
				<table class="form-table">
					<tbody>
					<tr>
						<th class="width1">
							<?php _e( 'bbpress Username', 'support_hub' ); ?>
						</th>
						<td class="">
							<input type="text" name="bbpress_name" value="<?php echo esc_attr( $shub_bbpress_account->get( 'bbpress_name' ) ); ?>">

						</td>
					</tr>
                    <tr>
                        <th class="width1">
                            <?php _e( 'App Secret Key', 'support_hub' ); ?>
                        </th>
                        <td class="">
                            <input type="password" name="bbpress_app_secret" value="<?php echo $shub_bbpress_account->get( 'bbpress_app_secret' ) ? 'password' : ''; ?>">
                        </td>
                    </tr>
                    <tr>
                        <th class="width1">
                            <?php _e( 'App Client ID', 'support_hub' ); ?>
                        </th>
                        <td class="">
                            <input type="text" name="bbpress_app_id" value="<?php echo esc_attr( $shub_bbpress_account->get( 'bbpress_app_id' ) ); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th class="width1">
                            <?php _e( 'Personal Token', 'support_hub' ); ?>
                        </th>
                        <td class="">
                            <input type="password" name="bbpress_token" value="<?php echo $shub_bbpress_account->get( 'bbpress_token' ) ? 'password' : ''; ?>">
                        </td>
                    </tr>
					<?php if ( $shub_bbpress_account->get( 'shub_bbpress_id' ) ) { ?>
						<tr>
							<th class="width1">
								<?php _e( 'Last Checked', 'support_hub' ); ?>
							</th>
							<td class="">
								<?php echo $shub_bbpress_account->get( 'last_checked' ) ? shub_print_date( $shub_bbpress_account->get( 'last_checked' ), true ) : __( 'N/A', 'support_hub' ); ?>
							</td>
						</tr>
						<tr>
							<th class="width1">
								<?php _e( 'Available forums', 'support_hub' ); ?>
							</th>
							<td class="">
								<input type="hidden" name="save_bbpress_forums" value="yep">
								<strong><?php _e( 'Choose which bbpress forums you would like to manage:', 'support_hub' ); ?></strong><br>
								(note: We haven't yet tested this with more than 50 forums, but it should work ok) <br/><br/>
								<?php
								$data = $shub_bbpress_account->get( 'bbpress_data' );
								if ( $data && isset( $data['forums'] ) && is_array( $data['forums'] ) && count( $data['forums'] ) > 0 ) {
									$bbpress_forums = $shub_bbpress_account->get('forums');
									?>
									<div>
										<input type="checkbox" name="all" value="1" class="bbpress_check_all"> - check all -
									</div>
									<br/><br/>

									<table class="wp-list-table widefat fixed striped">
										<thead>
										<tr>
											<th>Enabled</th>
											<th>Market</th>
											<th>bbpress forum</th>
											<th>Support Hub Product</th>
											<th>Last Checked</th>
											<th>Action</th>
										</tr>
										</thead>
										<tbody>
										<?php
										$products = SupportHub::getInstance()->get_products();
										foreach ( $data['forums'] as $forum_id => $forum_data ) {
											?>
											<tr>
												<td>
													<input type="checkbox" name="bbpress_forum[<?php echo $forum_id; ?>]" class="check_bbpress_forum"
													       value="1" <?php echo $shub_bbpress_account->is_forum_active( $forum_id ) ? ' checked' : ''; ?>>
												</td>
												<td>
													<?php echo htmlspecialchars( $forum_data['site'] ); ?>
												</td>
												<td>
													<?php echo htmlspecialchars( $forum_data['forum'] ); ?>
												</td>
												<td>
													<?php shub_module_form::generate_form_element(array(
														'name' => 'bbpress_forum_product['.$forum_id.']',
														'type' => 'select',
														'blank' => __('- None -','support_hub'),
														'value' => $shub_bbpress_account->is_forum_active( $forum_id ) ? $bbpress_forums[ $forum_id ]->get( 'shub_product_id' ) : $forum_data['shub_product_id'],
														'options' => $products,
														'options_array_id' => 'product_name',
														'class' => 'shub_product_dropdown',
													)); ?>
												</td>
												<td>
													<?php echo $shub_bbpress_account->is_forum_active( $forum_id ) && $bbpress_forums[ $forum_id ]->get( 'last_checked' ) ? shub_print_date( $bbpress_forums[ $forum_id ]->get( 'last_checked' ), true ): 'N/A';?>
												</td>
												<td>
													<?php
													if ( $shub_bbpress_account->is_forum_active( $forum_id ) ) {
														echo '<a href="' . $bbpress_forums[ $forum_id ]->link_refresh() . '" target="_blank">re-load forum comments</a>';
													} ?>
												</td>
											</tr>
										<?php
										}
										?>
										</tbody>
									</table>
									<?php
								} else {
									_e( 'No bbpress forums Found to Manage, click re-connect button below', 'support_hub' );
								}
								?>
							</td>
						</tr>
					<?php } ?>
					</tbody>
				</table>

				<p class="submit">
					<?php if ( $shub_bbpress_account->get( 'shub_bbpress_id' ) ) { ?>
						<input name="butt_save" type="submit" class="button-primary"
						       value="<?php echo esc_attr( __( 'Save', 'support_hub' ) ); ?>"/>
						<input name="butt_save_reconnect" type="submit" class="button"
						       value="<?php echo esc_attr( __( 'Re-Connect to bbpress', 'support_hub' ) ); ?>"/>
						<input name="butt_delete" type="submit" class="button"
						       value="<?php echo esc_attr( __( 'Delete', 'support_hub' ) ); ?>"
						       onclick="return confirm('<?php _e( 'Really delete this bbpress account and all associated data?', 'support_hub' ); ?>');"/>
					<?php } else { ?>
						<input name="butt_save_reconnect" type="submit" class="button-primary"
						       value="<?php echo esc_attr( __( 'Save and Connect to bbpress', 'support_hub' ) ); ?>"/>
					<?php } ?>
				</p>


			</form>
		</div>
	<?php
	}
}else{
	// show account overview:
	$myListTable = new SupportHub_Account_Data_List_Table();
	$accounts = $shub_bbpress->get_accounts();
	foreach($accounts as $account_id => $account){
		$a = new shub_bbpress_account($account['shub_bbpress_id']);
		$accounts[$account_id]['edit_link'] = $a->link_edit();
		$accounts[$account_id]['title'] = $a->get('bbpress_name');
		$accounts[$account_id]['last_checked'] = $a->get('last_checked') ? shub_print_date( $a->get('last_checked') ) : 'N/A';
	}
	$myListTable->set_data($accounts);
	$myListTable->prepare_forums();
	?>
	<div class="wrap">
		<h2>
			<?php _e('bbpress Accounts','support_hub');?>
			<a href="?page=<?php echo esc_attr($_GET['page']);?>&tab=<?php echo esc_attr($_GET['tab']);?>&shub_bbpress_id=new" class="add-new-h2"><?php _e('Add New','support_hub');?></a>
		</h2>
	    <?php
	    //$myListTable->search_box( 'search', 'search_id' );
	     $myListTable->display();
		?>
	</div>
	<?php
}

?>
<script type="text/javascript">
	<?php $shub_bbpress->init_js(); ?>
</script>