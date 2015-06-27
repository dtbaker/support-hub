<div class="wrap">
	<h2>
		<?php _e('Support Hub Settings','support_hub');?>
	</h2>
	<?php
	// find out what tab we're on.
	// is it the main tab or one of the individual plugin settings pages.
	$SupportHub = SupportHub::getInstance();
	$tab = isset($_REQUEST['tab']) ? $_REQUEST['tab'] : false;
	?>
	<h2 class="nav-tab-wrapper woo-nav-tab-wrapper">
		<a href="?page=support_hub_settings" class="nav-tab <?php echo !$tab ? ' nav-tab-active' : '';?>"><?php _e('General','support_hub');?></a>
		<a href="?page=support_hub_settings&amp;tab=products" class="nav-tab <?php echo $tab == 'products' ? ' nav-tab-active' : '';?>"><?php _e('Products','support_hub');?></a>
		<a href="?page=support_hub_settings&amp;tab=extra" class="nav-tab <?php echo $tab == 'extra' ? ' nav-tab-active' : '';?>"><?php _e('Extra Details','support_hub');?></a>
		<?php foreach($SupportHub->message_managers as $message_manager_id => $message_manager) {
			if ( $message_manager->is_enabled() ) {
				?>
				<a href="?page=support_hub_settings&amp;tab=<?php echo $message_manager_id;?>"
				   class="nav-tab <?php echo $tab == $message_manager_id ? ' nav-tab-active' : '';?>"><?php echo $message_manager->get_friendly_icon();?><?php echo $message_manager->friendly_name;?></a>
			<?php }
		}?>
		<a href="?page=support_hub_settings&amp;tab=logs" class="nav-tab <?php echo $tab == 'logs' ? ' nav-tab-active' : '';?>"><?php _e('Logs','support_hub');?></a>
		<!-- <a href="?page=support_hub_settings&amp;tab=pending" class="nav-tab">Ticksy</a>
		<a href="?page=support_hub_settings&amp;tab=pending" class="nav-tab">Help Scout</a>
		<a href="?page=support_hub_settings&amp;tab=pending" class="nav-tab">Zendesk</a>
		<a href="?page=support_hub_settings&amp;tab=pending" class="nav-tab">bbPress Forum</a>
		<a href="?page=support_hub_settings&amp;tab=pending" class="nav-tab">POP3/IMAP</a> -->
	</h2>
	<br class="clear"/>

	<?php if($tab){
		if(isset($SupportHub->message_managers[$tab])){
			$SupportHub->message_managers[$tab]->settings_page();
		}else{
			if(is_file(dirname(_DTBAKER_SUPPORT_HUB_CORE_FILE_).'/pages/settings-'.basename($tab).'.php')){
				include dirname(_DTBAKER_SUPPORT_HUB_CORE_FILE_).'/pages/settings-'.basename($tab).'.php';
			}
		}
	}else{
		?>
		<form action="" method="post">
			<input type="hidden" name="_process" value="save_general_settings">
			<?php wp_nonce_field( 'save-general-settings' ); ?>
			<table class="form-table">
				<tbody>
				<tr>
					<th>
						<?php _e('Enabled Extensions', 'support_hub') ;?>
					</th>
					<td class="">
						<?php foreach($SupportHub->message_managers as $id => $message_manager){ ?>
							<div>
								<input type="hidden" name="possible_shub_manager_enabled[<?php echo $id;?>]" value="1">
								<input type="checkbox" name="shub_manager_enabled[<?php echo $id;?>]" value="1" <?php echo get_option('shub_manager_enabled_'.$id,0) ? ' checked' : '';?>>
								<strong><?php echo $message_manager->friendly_name;?></strong>  - <?php echo $message_manager->desc;?>
							</div>
						<?php } ?>
					</td>
				</tr>
				</tbody>
			</table>
			<p class="submit">
					<input name="butt_save" type="submit" class="button-primary"
					       value="<?php echo esc_attr( __( 'Save', 'support_hub' ) ); ?>"/>
			</p>
		</form>
		<?php
	}
	?>

</div>