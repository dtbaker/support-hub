<?php

$current_account = isset($_REQUEST['shub_account_id']) ? (int)$_REQUEST['shub_account_id'] : false;
$shub_ucm = SupportHub::getInstance()->message_managers['ucm'];
if($current_account !== false){
	$shub_ucm_account = new shub_ucm_account($current_account);
    if($shub_ucm_account->get('shub_extension') != 'ucm')die('Wrong extension:' .$shub_ucm_account->get('shub_extension'));
	if(isset($_GET['manualrefresh'])){

        $network_key = isset( $_REQUEST['network_key'] ) ? (int) $_REQUEST['network_key'] : 0;
        if(!$network_key){
            // update?? products?
            $shub_ucm_account->confirm_api();
            $shub_ucm_account->load_available_items();
        }else {
            /* @var $items shub_item[] */
            $items = $shub_ucm_account->get('items');
            if (!$network_key || !$items || !isset($items[$network_key])) {
                die('No items found to refresh');
            }
            ?>
            Manually refreshing item data... please wait...
            <?php
            $items[$network_key]->run_cron(true);
        }

	}else if(isset($_GET['do_connect'])){
		// connect to ucm. and if that isnt' found
		?>
		<div class="wrap">
			<h2>
				<?php _e( 'UCM Account', 'support_hub' ); ?>
			</h2>
		<?php
		if($shub_ucm_account->get('shub_account_id') && $shub_ucm_account->get('shub_account_id') == $current_account && $shub_ucm_account->get( 'ucm_api_url' ) &&
			$shub_ucm_account->get( 'ucm_api_key' )) {

            // now we load in a list of ucm products to manage and redirect the user back to the 'edit' screen where they can continue managing the account.
            $shub_ucm_account->confirm_api();
            $shub_ucm_account->load_available_items();
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
                <input type="hidden" name="_process" value="save_account_details">
                <input type="hidden" name="shub_extension" value="ucm">
                <input type="hidden" name="shub_account_id" value="<?php echo (int) $shub_ucm_account->get( 'shub_account_id' ); ?>">
                <?php wp_nonce_field( 'save-account' . (int) $shub_ucm_account->get( 'shub_account_id' ) ); ?>

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
							<input type="text" name="account_name" value="<?php echo esc_attr( $shub_ucm_account->get( 'account_name' ) ); ?>">
							(e.g. My UCM Install)
						</td>
					</tr>
                    <tr>
                        <th class="width1">
                            <?php _e( 'API URL', 'support_hub' ); ?>
                        </th>
                        <td class="">
                            <input type="text" name="account_data[ucm_api_url]" value="<?php echo esc_attr($shub_ucm_account->get( 'ucm_api_url' )); ?>">
	                        (e.g. http://mysite.com/ucm/ext.php?m=api&amp;h=v1&amp;)
                        </td>
                    </tr>
                    <tr>
                        <th class="width1">
                            <?php _e( 'API Key', 'support_hub' ); ?>
                        </th>
                        <td class="">
							<input type="password" name="account_data[ucm_api_key]" value="<?php echo esc_attr($shub_ucm_account->get('ucm_api_key') ? _SUPPORT_HUB_PASSWORD_FIELD_FUZZ : ''); ?>">
                        </td>
                    </tr>
					<?php if ( $shub_ucm_account->get( 'shub_account_id' ) ) { ?>
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
								<input type="hidden" name="save_account_items" value="yep">
								<strong><?php _e( 'Choose which UCM products you would like to manage tickets for:', 'support_hub' ); ?></strong><br>
								<?php
								$data = $shub_ucm_account->get( 'account_data' );
								if ( $data && isset( $data['items'] ) && is_array( $data['items'] ) && count( $data['items'] ) > 0 ) {
									$ucm_products = $shub_ucm_account->get('items');
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
										foreach ( $data['items'] as $product_id => $product_data ) {
											?>
											<tr>
												<td>
													<input type="checkbox" name="item[<?php echo $product_id; ?>]" class="check_item"
													       value="1" <?php echo $shub_ucm_account->is_item_active( $product_id ) ? ' checked' : ''; ?>>
                                                    <input type="hidden" name="item_name[<?php echo $product_id;?>]" value="<?php echo esc_attr($product_data['name']);?>">
												</td>
												<td>
													<?php echo htmlspecialchars( $product_data['name'] ); ?>
												</td>
												<td>
													<?php shub_module_form::generate_form_element(array(
														'name' => 'item_product['.$product_id.']',
														'type' => 'select',
														'blank' => __('- None -','support_hub'),
														'value' => $shub_ucm_account->is_item_active( $product_id ) ? $ucm_products[ $product_id ]->get( 'shub_product_id' ) : (isset($product_data['shub_product_id']) ? $product_data['shub_product_id'] : 0),
														'options' => $products,
														'options_array_id' => 'product_name',
														'class' => 'shub_product_dropdown',
													)); ?>
												</td>
												<td>
													<?php echo $shub_ucm_account->is_item_active( $product_id ) && $ucm_products[ $product_id ]->get( 'last_checked' ) ? shub_print_date( $ucm_products[ $product_id ]->get( 'last_checked' ), true ): 'N/A';?>
												</td>
												<td>
													<?php
													if ( $shub_ucm_account->is_item_active( $product_id ) ) {
														echo '<a href="' . $ucm_products[ $product_id ]->link_refresh() . '" target="_blank">re-load product tickets</a>';
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
					<?php if ( $shub_ucm_account->get( 'shub_account_id' ) ) { ?>
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
		$a = new shub_ucm_account($account['shub_account_id']);
		$accounts[$account_id]['edit_link'] = $a->link_edit();
		$accounts[$account_id]['title'] = $a->get('account_name');
		$accounts[$account_id]['last_checked'] = $a->get('last_checked') ? shub_print_date( $a->get('last_checked') ) : 'N/A';
	}
	$myListTable->set_data($accounts);
	$myListTable->prepare_items();
	?>
	<div class="wrap">
		<h2>
			<?php _e('UCM Accounts','support_hub');?>
			<a href="?page=<?php echo esc_attr($_GET['page']);?>&tab=<?php echo esc_attr($_GET['tab']);?>&shub_account_id=new" class="add-new-h2"><?php _e('Add New','support_hub');?></a>
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