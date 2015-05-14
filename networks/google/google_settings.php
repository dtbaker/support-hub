<?php

$current_account = isset($_REQUEST['shub_google_id']) ? (int)$_REQUEST['shub_google_id'] : false;
$shub_google = new shub_google();
if($current_account !== false){
	$shub_google_account = new shub_google_account($current_account);
	if(isset($_GET['do_google_refresh'])) {

		?>
		<div class="wrap">
			<h2>
				<?php _e( 'Google Account', 'support_hub' ); ?>
			</h2>
		Manually refreshing page data...
		<?php
		$shub_google_account->api_login(true);
		$shub_google_account->api_get_page_comments($shub_google_account->get('google_id'),true);
		?>
		</div>
		<?php

	}else if(isset($_GET['do_google_connect'])){

		?>
		<div class="wrap">
		<h2>
			<?php _e( 'Google Connect', 'support_hub' ); ?>
		</h2>
			<pre>
			<?php
			// connect this account to google:
			$shub_google_account->api_login(true);
			echo "Current Google Page ID: ".$shub_google_account->get('google_id')."\n<br>";
			if(!$shub_google_account->is_active()){
				echo "Doing initial refresh... <br>";
				$shub_google_account->api_get_page_comments($shub_google_account->get('google_id'),true);
			}else{
				echo "All done";
			}
			//$shub_google_account->api_post_comment_reply("z12ddfpjkxregptdv22cihioszyxybjxb04","new api respose ..");
			//$shub_google_account->api_post_page_status($shub_google_account->get('google_id'),"Posting a new public message from PHP API");
			?>
			</pre>
		</div>
		<?php

	}else {
		?>
		<div class="wrap">
			<h2>
				<?php _e( 'Google Account', 'support_hub' ); ?>
			</h2>

			<form action="" method="post">
				<input type="hidden" name="_process" value="save_google">
				<input type="hidden" name="shub_google_id"
				       value="<?php echo (int) $shub_google_account->get( 'shub_google_id' ); ?>">
				<?php wp_nonce_field( 'save-google' . (int) $shub_google_account->get( 'shub_google_id' ) ); ?>

				<hr>
				<?php
				$fieldset_data = array(
				    'class' => 'tableclass tableclass_form tableclass_full',
				    'elements' => array(
				        array(
				            'title' => __('Account Name', 'support_hub'),
				            'field' => array(
				                'type' => 'text',
					            'name' => 'account_name',
					            'value' => $shub_google_account->get('account_name'),
					            'help' => 'Choose a name for this account. This name will be shown here in the system.',
				            ),
				        ),
				    )
				);
				$fieldset_data['elements'][] = array(
					'title' => __('Last Checked', 'support_hub'),
			            'fields' => array(
			                !$shub_google_account->is_active() ? __('Pending, please click connect button below') : shub_print_date($shub_google_account->get('last_checked'),true),
				            $shub_google_account->is_active() ? '(<a href="'.$shub_google_account->link_refresh().'" target="_blank">'.__('Refresh', 'support_hub').'</a>)' : '',
			            ),
			        );
				$fieldset_data['elements'][] = array(
					'title' => __('Google Page Name', 'support_hub'),
			            'fields' => array(
			                $shub_google_account->is_active() ? htmlspecialchars($shub_google_account->get('google_name')) : __('Pending'),
			            ),
			        );
				$fieldset_data['elements'][] = array(
					'title' => __('Google Page ID', 'support_hub'),
			            'fields' => array(
			                $shub_google_account->is_active() ? htmlspecialchars($shub_google_account->get('google_id')) : __('Pending'),
			            ),
			        );
				echo shub_module_form::generate_fieldset($fieldset_data);
				/*?>
				<hr>
				<p>Please choose which items you would like to manage:</p>
				<?php
				$fieldset_data = array(
				    'class' => 'tableclass tableclass_form tableclass_full',
				    'elements' => array(

				    )
				);
				// check if this is active, if not prmopt the user to re-connect.


				$fieldset_data['elements'][] = array(
					'title' => __('Import Comments', 'support_hub'),
			            'fields' => array(
			                array(
				                'type' => 'checkbox',
				                'value' => $shub_google_account->get('import_comments'),
				                'name' => 'import_comments',
			                )
			            ),
			        );
				$fieldset_data['elements'][] = array(
					'title' => __('Import PlusOnes', 'support_hub'),
			            'fields' => array(
			                array(
				                'type' => 'checkbox',
				                'name' => 'import_plusones',
				                'value' => $shub_google_account->get('import_plusones'),
			                )
			            ),
			        );
				$fieldset_data['elements'][] = array(
					'title' => __('Import Mentions', 'support_hub'),
			            'fields' => array(
			                array(
				                'type' => 'checkbox',
				                'name' => 'import_mentions',
				                'value' => $shub_google_account->get('import_mentions'),
			                )
			            ),
			        );
				echo shub_module_form::generate_fieldset($fieldset_data);
				*/?>
				<hr>
		<p>In order to write messages to Google+ pages then please create a new "Page" email address/password.
		Follow these instructions:
		</p>
		<ol>
			<li>Login to your Google+ account: <a href="https://plus.google.com" target="_blank">https://plus.google.com</a></li>
			<li>Hover over the main menu (Home) and choose "Pages" to see a list of your available pages</li>
			<li>Click "Manage this page" on the page you wish to setup </li>
			<li>Hover over the main menu again (Dashboard) and choose "Settings"</li>
			<li>Scroll down to "Third-party Tools" and click "Set up a password"</li>
			<li>Note down your "Page Username" (it should look like: support-hub-1234@pages.plusgoogle.com )</li>
			<li>Choose a new unique password for your page.</li>
			<li>Enter these into the Page Username and Page Password boxes below.</li>
		</ol>
		<p>More details <a href="https://support.google.com/plus/answer/2882201?hl=en" target="_blank">here</a>.</p>
				<?php
				$google_data = @json_decode($shub_google_account->get('google_data'),true);
			    if(!is_array($google_data))$google_data = array();

				$fieldset_data = array(
				    'class' => 'tableclass tableclass_form tableclass_full',
				    'elements' => array(
				        array(
				            'title' => __('Page Username', 'support_hub'),
				            'field' => array(
				                'type' => 'text',
					            'name' => 'username',
					            'value' => $shub_google_account->get('username'),
					            'help' => 'Username for this account, from instructions.',
				            ),
				        ),
				        array(
				            'title' => __('Page Password', 'support_hub'),
				            'field' => array(
				                'type' => 'password',
					            'name' => 'password',
					            'value' => $shub_google_account->get('password'),
					            'help' => 'Password for this account, from instructions.',
				            ),
				        ),
				        array(
				            'title' => __('GAPS Cookie (optional)', 'support_hub'),
				            'field' => array(
				                'type' => 'text',
					            'name' => 'gaps_cookie',
					            'value' => isset($google_data['gaps_cookie']) ? $google_data['gaps_cookie'] : '',
					            'help' => 'GAPS cookie if login does not work.',
				            ),
				        ),
				    )
				);
				echo shub_module_form::generate_fieldset($fieldset_data);
				?>
				<p class="submit">
					<?php if ( $shub_google_account->get( 'shub_google_id' ) ) { ?>
						<input name="butt_save" type="submit" class="button-primary"
						       value="<?php echo esc_attr( __( 'Save', 'support_hub' ) ); ?>"/>
						<input name="butt_save_reconnect" type="submit" class="button"
						       value="<?php echo esc_attr( __( 'Re-Connect to Google', 'support_hub' ) ); ?>"/>
						<input name="butt_delete" type="submit" class="button"
						       value="<?php echo esc_attr( __( 'Delete', 'support_hub' ) ); ?>"
						       onclick="return confirm('<?php _e( 'Really delete this Google account and all associated data?', 'support_hub' ); ?>');"/>
					<?php } else { ?>
						<input name="butt_save_reconnect" type="submit" class="button-primary"
						       value="<?php echo esc_attr( __( 'Save and Connect to Google', 'support_hub' ) ); ?>"/>
					<?php } ?>
				</p>


			</form>
		</div>
	<?php
	}
}else{
	// show account overview:
	$myListTable = new support_hub_Account_Data_List_Table();
	$accounts = $shub_google->get_accounts();
	foreach($accounts as $account_id => $account){
		$a = new shub_google_account($account['shub_google_id']);
		$accounts[$account_id]['edit_link'] = $a->link_edit();
		$accounts[$account_id]['title'] = $a->get('account_name');
		$accounts[$account_id]['last_checked'] = $a->get('last_checked') ? shub_print_date( $a->get('last_checked') ) : 'N/A';
	}
	$myListTable->set_data($accounts);
	$myListTable->prepare_items();
	?>
	<div class="wrap">
		<h2>
			<?php _e('Google Page Accounts','support_hub');?>
			<a href="?page=<?php echo htmlspecialchars($_GET['page']);?>&shub_google_id=new" class="add-new-h2"><?php _e('Add New','support_hub');?></a>
		</h2>
	    <?php
	    //$myListTable->search_box( 'search', 'search_id' );
	     $myListTable->display();
		?>

	</div>
	<?php
}
