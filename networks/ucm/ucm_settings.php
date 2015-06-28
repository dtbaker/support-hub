<?php

$current_account = isset($_REQUEST['shub_ucm_id']) ? (int)$_REQUEST['shub_ucm_id'] : false;
$shub_ucm = new shub_ucm();
if($current_account !== false){
	$shub_ucm_account = new shub_ucm_account($current_account);
	if(isset($_GET['manualrefresh'])){

		$ucm_product_id = isset( $_REQUEST['ucm_product_id'] ) ? (int) $_REQUEST['ucm_product_id'] : 0;
		/* @var $products shub_ucm_product[] */
		$products = $shub_ucm_account->get( 'products' );
		if ( ! $ucm_product_id || ! $products || ! isset( $products[ $ucm_product_id ] ) ) {
			die( 'No products found to refresh' );
		}
		?>
		Manually refreshing product data... please wait...
		<?php
		$products[ $ucm_product_id ]->run_cron( true );

	}else if(isset($_GET['ucm_do_oauth_connect'])){
		// connect to ucm. and if that isnt' found
		?>
		<div class="wrap">
			<h2>
				<?php _e( 'UCM Account', 'support_hub' ); ?>
			</h2>
		<?php
		if($shub_ucm_account->get('shub_ucm_id') && $shub_ucm_account->get('shub_ucm_id') == $current_account && $shub_ucm_account->get( 'ucm_api_url' ) &&
			$shub_ucm_account->get( 'ucm_api_key' )) {

            // now we load in a list of ucm products to manage and redirect the user back to the 'edit' screen where they can continue managing the account.
            $shub_ucm_account->load_available_products();
            $url = $shub_ucm_account->link_edit();
            ?>
            <p>You have successfully connected UCM with the Support Hub plugin. Please click the button below:</p>
            <p><a href="<?php echo $shub_ucm_account->link_edit(); ?>" class="button">Click here to continue.</a></p>
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
				<?php _e( 'UCM Account', 'support_hub' ); ?>
			</h2>

			<form action="" method="post">
				<input type="hidden" name="_process" value="save_ucm">
				<input type="hidden" name="shub_ucm_id"
				       value="<?php echo (int) $shub_ucm_account->get( 'shub_ucm_id' ); ?>">
				<?php wp_nonce_field( 'save-ucm' . (int) $shub_ucm_account->get( 'shub_ucm_id' ) ); ?>

                <p>Setup Instructions:</p>
                <ul>
                    <li>Login to your Ultimate Client Manager installation</li>
                    <li>Go to Settings > API</li>
					<li>Copy your API URL into the box below</li>
					<li>Copy your unique API key into the box below</li>
                    <li>Click "Save and Connect to UCM"</li>
                </ul>
				<table class="form-table">
					<tbody>
					<tr>
						<th class="width1">
							<?php _e( 'Website Name', 'support_hub' ); ?>
						</th>
						<td class="">
							<input type="text" name="ucm_name" value="<?php echo esc_attr( $shub_ucm_account->get( 'ucm_name' ) ); ?>">
							(e.g. My UCM Install)
						</td>
					</tr>
                    <tr>
                        <th class="width1">
                            <?php _e( 'API URL', 'support_hub' ); ?>
                        </th>
                        <td class="">
                            <input type="text" name="ucm_api_url" value="<?php echo esc_attr($shub_ucm_account->get( 'ucm_api_url' )); ?>">
	                        (e.g. http://mysite.com/ucm/ext.php?m=api&amp;h=v1&amp;)
                        </td>
                    </tr>
                    <tr>
                        <th class="width1">
                            <?php _e( 'API Key', 'support_hub' ); ?>
                        </th>
                        <td class="">
							<input type="password" name="ucm_api_key" value="<?php echo $shub_ucm_account->get( 'ucm_api_key' ) ? 'password' : ''; ?>">
                        </td>
                    </tr>
					<?php if ( $shub_ucm_account->get( 'shub_ucm_id' ) ) { ?>
						<tr>
							<th class="width1">
								<?php _e( 'Last Checked', 'support_hub' ); ?>
							</th>
							<td class="">
								<?php echo $shub_ucm_account->get( 'last_checked' ) ? shub_print_date( $shub_ucm_account->get( 'last_checked' ), true ) : __( 'N/A', 'support_hub' ); ?>
							</td>
						</tr>
						<tr>
							<th class="width1">
								<?php _e( 'Available UCM Support products', 'support_hub' ); ?>
							</th>
							<td class="">
								<input type="hidden" name="save_ucm_products" value="yep">
								<strong><?php _e( 'Choose which UCM products you would like to manage tickets for:', 'support_hub' ); ?></strong><br>
								<?php
								$data = $shub_ucm_account->get( 'ucm_data' );
								if ( $data && isset( $data['products'] ) && is_array( $data['products'] ) && count( $data['products'] ) > 0 ) {
									$ucm_products = $shub_ucm_account->get('products');
									?>
									<div>
										<input type="checkbox" name="all" value="1" class="ucm_check_all"> - check all -
									</div>
									<br/><br/>

									<table class="wp-list-table widefat fixed striped">
										<thead>
										<tr>
											<th>Enabled</th>
											<th>UCM Product</th>
											<th>Support Hub Product</th>
											<th>Last Checked</th>
											<th>Action</th>
										</tr>
										</thead>
										<tbody>
										<?php
										$products = SupportHub::getInstance()->get_products();
										foreach ( $data['products'] as $product_id => $product_data ) {
											?>
											<tr>
												<td>
													<input type="checkbox" name="ucm_product[<?php echo $product_id; ?>]" class="check_ucm_product"
													       value="1" <?php echo $shub_ucm_account->is_product_active( $product_id ) ? ' checked' : ''; ?>>
												</td>
												<td>
													<?php echo htmlspecialchars( $product_data['name'] ); ?>
												</td>
												<td>
													<?php shub_module_form::generate_form_element(array(
														'name' => 'ucm_product_product['.$product_id.']',
														'type' => 'select',
														'blank' => __('- None -','support_hub'),
														'value' => $shub_ucm_account->is_product_active( $product_id ) ? $ucm_products[ $product_id ]->get( 'shub_product_id' ) : (isset($product_data['shub_product_id']) ? $product_data['shub_product_id'] : 0),
														'options' => $products,
														'options_array_id' => 'product_name',
														'class' => 'shub_product_dropdown',
													)); ?>
												</td>
												<td>
													<?php echo $shub_ucm_account->is_product_active( $product_id ) && $ucm_products[ $product_id ]->get( 'last_checked' ) ? shub_print_date( $ucm_products[ $product_id ]->get( 'last_checked' ), true ): 'N/A';?>
												</td>
												<td>
													<?php
													if ( $shub_ucm_account->is_product_active( $product_id ) ) {
														echo '<a href="' . $ucm_products[ $product_id ]->link_refresh() . '" target="_blank">re-load product topics</a>';
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
									_e( 'No ucm products Found to Manage, click re-connect button below', 'support_hub' );
								}
								?>
							</td>
						</tr>
					<?php } ?>
					</tbody>
				</table>

				<p class="submit">
					<?php if ( $shub_ucm_account->get( 'shub_ucm_id' ) ) { ?>
						<input name="butt_save" type="submit" class="button-primary"
						       value="<?php echo esc_attr( __( 'Save', 'support_hub' ) ); ?>"/>
						<input name="butt_save_reconnect" type="submit" class="button"
						       value="<?php echo esc_attr( __( 'Re-Connect to UCM', 'support_hub' ) ); ?>"/>
						<input name="butt_delete" type="submit" class="button"
						       value="<?php echo esc_attr( __( 'Delete', 'support_hub' ) ); ?>"
						       onclick="return confirm('<?php _e( 'Really delete this UCM account and all associated data?', 'support_hub' ); ?>');"/>
					<?php } else { ?>
						<input name="butt_save_reconnect" type="submit" class="button-primary"
						       value="<?php echo esc_attr( __( 'Save and Connect to UCM', 'support_hub' ) ); ?>"/>
					<?php } ?>
				</p>


			</form>
		</div>
	<?php
	}
}else{
	// show account overview:
	$myListTable = new SupportHub_Account_Data_List_Table();
	$accounts = $shub_ucm->get_accounts();
	foreach($accounts as $account_id => $account){
		$a = new shub_ucm_account($account['shub_ucm_id']);
		$accounts[$account_id]['edit_link'] = $a->link_edit();
		$accounts[$account_id]['title'] = $a->get('ucm_name');
		$accounts[$account_id]['last_checked'] = $a->get('last_checked') ? shub_print_date( $a->get('last_checked') ) : 'N/A';
	}
	$myListTable->set_data($accounts);
	$myListTable->prepare_items();
	?>
	<div class="wrap">
		<h2>
			<?php _e('UCM Accounts','support_hub');?>
			<a href="?page=<?php echo esc_attr($_GET['page']);?>&tab=<?php echo esc_attr($_GET['tab']);?>&shub_ucm_id=new" class="add-new-h2"><?php _e('Add New','support_hub');?></a>
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
	<?php $shub_ucm->init_js(); ?>
</script>