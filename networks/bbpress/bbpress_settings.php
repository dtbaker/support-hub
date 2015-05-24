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
				<?php _e( 'bbPress Account', 'support_hub' ); ?>
			</h2>
		<?php
		if($shub_bbpress_account->get('shub_bbpress_id') && $shub_bbpress_account->get('shub_bbpress_id') == $current_account && $shub_bbpress_account->get( 'bbpress_wordpress_xmlrpc' ) && $shub_bbpress_account->get( 'bbpress_username' ) && $shub_bbpress_account->get( 'bbpress_password' )) {

            // now we load in a list of bbpress forums to manage and redirect the user back to the 'edit' screen where they can continue managing the account.
            $shub_bbpress_account->load_available_forums();
            $url = $shub_bbpress_account->link_edit();
            ?>
            <p>You have successfully connected bbPress with the Support Hub plugin. Please click the button below:</p>
            <p><a href="<?php echo $shub_bbpress_account->link_edit(); ?>" class="button">Click here to continue.</a></p>
			<p>&nbsp;</p>
			<p>&nbsp;</p>
			<p>&nbsp;</p>
			<p>&nbsp;</p>
            <?php
		} else {
			?>
			Please go back and make sure all fields are entered.
			<?php
		}
		?> </div> <?php
	}else {
		?>
		<div class="wrap">
			<h2>
				<?php _e( 'bbPress Account', 'support_hub' ); ?>
			</h2>

			<form action="" method="post">
				<input type="hidden" name="_process" value="save_bbpress">
				<input type="hidden" name="shub_bbpress_id"
				       value="<?php echo (int) $shub_bbpress_account->get( 'shub_bbpress_id' ); ?>">
				<?php wp_nonce_field( 'save-bbpress' . (int) $shub_bbpress_account->get( 'shub_bbpress_id' ) ); ?>

                <p>Setup Instructions:</p>
                <ul>
                    <li>Login to your WordPress website</li>
                    <li>Create a new WordPress user as a Forum Moderator (this user should only have access to replying to forum posts).</li>
                    <li>Type in your WordPress XML-PRC url below (usually http://yourwebsite.com/xmlrpc.php)</li>
                    <li>Type in your new WordPress username and password below (the one you just created)</li>
                    <li>Click "Save and Connect to bbPress"</li>
                </ul>
				<table class="form-table">
					<tbody>
					<tr>
						<th class="width1">
							<?php _e( 'Website Name', 'support_hub' ); ?>
						</th>
						<td class="">
							<input type="text" name="bbpress_name" value="<?php echo esc_attr( $shub_bbpress_account->get( 'bbpress_name' ) ); ?>">
							(e.g. My Support Forum)
						</td>
					</tr>
                    <tr>
                        <th class="width1">
                            <?php _e( 'WordPress XML-RPC URL', 'support_hub' ); ?>
                        </th>
                        <td class="">
                            <input type="text" name="bbpress_wordpress_xmlrpc" value="<?php echo esc_attr($shub_bbpress_account->get( 'bbpress_wordpress_xmlrpc' )); ?>">
	                        (e.g. http://mysite.com/xmlrpc.php)
                        </td>
                    </tr>
                    <tr>
                        <th class="width1">
                            <?php _e( 'WordPress Username', 'support_hub' ); ?>
                        </th>
                        <td class="">
                            <input type="text" name="bbpress_username" value="<?php echo esc_attr( $shub_bbpress_account->get( 'bbpress_username' ) ); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th class="width1">
                            <?php _e( 'WordPress Password', 'support_hub' ); ?>
                        </th>
                        <td class="">
                            <input type="password" name="bbpress_password" value="<?php echo $shub_bbpress_account->get( 'bbpress_password' ) ? 'password' : ''; ?>">
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
								<?php _e( 'Available bbPress Forums', 'support_hub' ); ?>
							</th>
							<td class="">
								<input type="hidden" name="save_bbpress_forums" value="yep">
								<strong><?php _e( 'Choose which bbPress forums you would like to manage:', 'support_hub' ); ?></strong><br>
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
											<th>bbPress Forum</th>
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
									_e( 'No bbPress forums Found to Manage, click re-connect button below', 'support_hub' );
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
						       value="<?php echo esc_attr( __( 'Re-Connect to bbPress', 'support_hub' ) ); ?>"/>
						<input name="butt_delete" type="submit" class="button"
						       value="<?php echo esc_attr( __( 'Delete', 'support_hub' ) ); ?>"
						       onclick="return confirm('<?php _e( 'Really delete this bbPress account and all associated data?', 'support_hub' ); ?>');"/>
					<?php } else { ?>
						<input name="butt_save_reconnect" type="submit" class="button-primary"
						       value="<?php echo esc_attr( __( 'Save and Connect to bbPress', 'support_hub' ) ); ?>"/>
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
			<?php _e('bbPress Accounts','support_hub');?>
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