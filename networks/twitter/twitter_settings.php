<?php

$current_account = isset($_REQUEST['shub_twitter_id']) ? (int)$_REQUEST['shub_twitter_id'] : false;
$shub_twitter = new shub_twitter();
if($current_account !== false){
	$shub_twitter_account = new shub_twitter_account($current_account);
	if(isset($_GET['do_twitter_refresh'])) {

		?>
		<div class="wrap">
			<h2>
				<?php _e( 'Twitter Account', 'support_hub' ); ?>
			</h2>
		Manually refreshing page data...
		<?php
		$shub_twitter_account->import_data(true);
		$shub_twitter_account->run_cron(true);
		?>
		</div>
		<?php

	}else if(isset($_GET['do_twitter_connect'])){

		include('twitter_connect.php');

	}else {
		?>
		<div class="wrap">
			<h2>
				<?php _e( 'Twitter Account', 'support_hub' ); ?>
			</h2>

			<form action="" method="post">
				<input type="hidden" name="_process" value="save_twitter">
				<input type="hidden" name="shub_twitter_id"
				       value="<?php echo (int) $shub_twitter_account->get( 'shub_twitter_id' ); ?>">
				<?php wp_nonce_field( 'save-twitter' . (int) $shub_twitter_account->get( 'shub_twitter_id' ) ); ?>

				<?php 
				$fieldset_data = array(
				    'class' => 'tableclass tableclass_form tableclass_full',
				    'elements' => array(
				        array(
				            'title' => __('Account Name', 'support_hub'),
				            'field' => array(
				                'type' => 'text',
					            'name' => 'account_name',
					            'value' => $shub_twitter_account->get('account_name'),
					            'help' => 'Choose a name for this account. This name will be shown here in the system.',
				            ),
				        ),
				    )
				);
				// check if this is active, if not prmopt the user to re-connect.
				if($shub_twitter_account->is_active()){
					$fieldset_data['elements'][] = array(
						'title' => __('Last Checked', 'support_hub'),
				            'fields' => array(
				                shub_print_date($shub_twitter_account->get('last_checked'),true),
					            '(<a href="'.$shub_twitter_account->link_refresh().'" target="_blank">'.__('Refresh', 'support_hub').'</a>)',
				            ),
				        );
					$fieldset_data['elements'][] = array(
						'title' => __('Twitter Name', 'support_hub'),
				            'fields' => array(
				                htmlspecialchars($shub_twitter_account->get('twitter_name')),
				            ),
				        );
					$fieldset_data['elements'][] = array(
						'title' => __('Twitter ID', 'support_hub'),
				            'fields' => array(
				                htmlspecialchars($shub_twitter_account->get('twitter_id')),
				            ),
				        );
					$fieldset_data['elements'][] = array(
						'title' => __('Import DM\'s', 'support_hub'),
				            'fields' => array(
				                array(
					                'type' => 'checkbox',
					                'value' => $shub_twitter_account->get('import_dm'),
					                'name' => 'import_dm',
					                'help' => 'Enable this to import Direct Messages from this twitter account',
				                )
				            ),
				        );
					$fieldset_data['elements'][] = array(
						'title' => __('Import Mentions', 'support_hub'),
				            'fields' => array(
				                array(
					                'type' => 'checkbox',
					                'value' => $shub_twitter_account->get('import_mentions'),
					                'name' => 'import_mentions',
					                'help' => 'Enable this to import any tweets that mention your name',
				                )
				            ),
				        );
					$fieldset_data['elements'][] = array(
						'title' => __('Import Tweets', 'support_hub'),
				            'fields' => array(
				                array(
					                'type' => 'checkbox',
					                'name' => 'import_tweets',
					                'value' => $shub_twitter_account->get('import_tweets'),
					                'help' => 'Enable this to import any tweets that originated from this account',
				                )
				            ),
				        );
			
				}else{
			
				}
				echo shub_module_form::generate_fieldset($fieldset_data);
				?>

				<p class="submit">
					<?php if ( $shub_twitter_account->get( 'shub_twitter_id' ) ) { ?>
						<input name="butt_save" type="submit" class="button-primary"
						       value="<?php echo esc_attr( __( 'Save', 'support_hub' ) ); ?>"/>
						<input name="butt_save_reconnect" type="submit" class="button"
						       value="<?php echo esc_attr( __( 'Re-Connect to Twitter', 'support_hub' ) ); ?>"/>
						<input name="butt_delete" type="submit" class="button"
						       value="<?php echo esc_attr( __( 'Delete', 'support_hub' ) ); ?>"
						       onclick="return confirm('<?php _e( 'Really delete this Twitter account and all associated data?', 'support_hub' ); ?>');"/>
					<?php } else { ?>
						<input name="butt_save_reconnect" type="submit" class="button-primary"
						       value="<?php echo esc_attr( __( 'Save and Connect to Twitter', 'support_hub' ) ); ?>"/>
					<?php } ?>
				</p>


			</form>
		</div>
	<?php
	}
}else{
	// show account overview:
	$myListTable = new support_hub_Account_Data_List_Table();
	$accounts = $shub_twitter->get_accounts();
	foreach($accounts as $account_id => $account){
		$a = new shub_twitter_account($account['shub_twitter_id']);
		$accounts[$account_id]['edit_link'] = $a->link_edit();
		$accounts[$account_id]['title'] = $a->get('account_name');
		$accounts[$account_id]['last_checked'] = $a->get('last_checked') ? shub_print_date( $a->get('last_checked') ) : 'N/A';
	}
	$myListTable->set_data($accounts);
	$myListTable->prepare_items();
	?>
	<div class="wrap">
		<h2>
			<?php _e('Twitter Accounts','support_hub');?>
			<a href="?page=<?php echo htmlspecialchars($_GET['page']);?>&shub_twitter_id=new" class="add-new-h2"><?php _e('Add New','support_hub');?></a>
		</h2>
	    <?php
	    //$myListTable->search_box( 'search', 'search_id' );
	     $myListTable->display();
		?>
		<hr>
		<h2>
			<?php _e('Twitter App Settings','support_hub');?>
		</h2>
		<p>Please go to <a href="https://apps.twitter.com/" target="_blank">https://apps.twitter.com/</a> and sign in using your Twitter account. Then click the Create New App button. Enter a Name, Description, Website (and in the Callback URL just put your website address again). Once created, go to Permissions and choose "Read, write, and direct messages" then go to API Keys and copy your API Key and API Secret from here into the below form.</p>
		<form action="" method="post">
				<input type="hidden" name="_process" value="save_twitter_settings">
				<?php wp_nonce_field( 'save-twitter-settings' ); ?>

				<table class="form-table">
					<tbody>
					<tr>
						<th class="width1">
							<?php _e( 'App API Key', 'support_hub' ); ?>
						</th>
						<td class="">
							<input type="text" name="twitter_app_api_key" value="<?php echo esc_attr( $shub_twitter->get('api_key') ); ?>">
						</td>
					</tr>
					<tr>
						<th class="width1">
							<?php _e( 'App API Secret', 'support_hub' ); ?>
						</th>
						<td class="">
							<input type="text" name="twitter_app_api_secret" value="<?php echo esc_attr( $shub_twitter->get('api_secret') ); ?>">
						</td>
					</tr>
					</tbody>
				</table>

				<p class="submit">
					<input name="butt_save" type="submit" class="button-primary" value="<?php echo esc_attr( __( 'Save', 'support_hub' ) ); ?>"/>
				</p>


			</form>
	</div>
	<?php
}
